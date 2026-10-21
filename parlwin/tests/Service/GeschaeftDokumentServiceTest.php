<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\GeschaeftDokument;
use OCA\ParliamentWinterthur\Db\GeschaeftDokumentMapper;
use OCA\ParliamentWinterthur\Service\DokumentInhaltParser;
use OCA\ParliamentWinterthur\Service\GeschaeftDokumentService;
use OCA\ParliamentWinterthur\Service\PdfZeilenLeser;
use OCA\ParliamentWinterthur\Service\SyncLockService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\ITempManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Der Abgleich der Dokumente (F121): Ein neues Dokument wird geladen, gelesen
 * und abgelegt; ein unverändertes wird nicht noch einmal gelesen; ein Dokument,
 * das die Quelle nicht hergibt, trägt seinen Fehler am Datensatz.
 *
 * @group pdf
 */
class GeschaeftDokumentServiceTest extends TestCase
{
    /** @var GeschaeftDokument[] */
    private array $geschrieben = [];

    /** @var array<string, GeschaeftDokument> */
    private array $bestand = [];

    /** @var GeschaeftDokument[] */
    private array $offene = [];

    private int $abrufe = 0;

    /** Welche Fassung des Lesers der Dienst beim Nachlesen verlangt hat. */
    private string $gefragteFassung = '';

    private function quelle(): string
    {
        return \dirname(__DIR__, 1) . '/Fixtures/dokumente/vorstoss-2026-15.pdf';
    }

    private function dienst(?\Throwable $fehler = null, ?SyncLockService $sperre = null): GeschaeftDokumentService
    {
        $mapper = $this->createStub(GeschaeftDokumentMapper::class);
        $mapper->method('findByExternId')->willReturnCallback(
            fn (string $externId): ?GeschaeftDokument => $this->bestand[$externId] ?? null,
        );
        $mapper->method('insert')->willReturnCallback(function (GeschaeftDokument $d): GeschaeftDokument {
            $this->geschrieben[] = $d;
            return $d;
        });
        $mapper->method('update')->willReturnCallback(function (GeschaeftDokument $d): GeschaeftDokument {
            $this->geschrieben[] = $d;
            return $d;
        });
        $mapper->method('findOhneInhalt')->willReturnCallback(
            function (int $limit, string $fassung = ''): array {
                $this->gefragteFassung = $fassung;
                return \array_slice($this->offene, 0, $limit);
            },
        );

        $antwort = $this->createStub(IResponse::class);
        $antwort->method('getBody')->willReturn((string) file_get_contents($this->quelle()));
        $client = $this->createStub(IClient::class);
        $client->method('get')->willReturnCallback(function () use ($antwort, $fehler): IResponse {
            ++$this->abrufe;
            if ($fehler !== null) {
                throw $fehler;
            }
            return $antwort;
        });
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);

        $tempManager = $this->createStub(ITempManager::class);
        $tempManager->method('getTemporaryFile')->willReturnCallback(
            static fn (string $endung = ''): string => (string) tempnam(sys_get_temp_dir(), 'pwdok') . $endung,
        );

        // Das Speicherlimit steht hier als Zahl, statt am laufenden Prozess
        // gedreht zu werden: 0 heisst «kein Limit» und gilt für alle Tests, die
        // vom Speicher nichts wissen wollen.
        return new class (
            $mapper,
            new DokumentInhaltParser(new PdfZeilenLeser()),
            $clientService,
            $tempManager,
            $this->createStub(LoggerInterface::class),
            $sperre,
            $this->speicherLimit,
        ) extends GeschaeftDokumentService {
            public function __construct(
                GeschaeftDokumentMapper $mapper,
                DokumentInhaltParser $parser,
                IClientService $clientService,
                ITempManager $tempManager,
                LoggerInterface $logger,
                ?SyncLockService $sperre,
                private readonly int $limit,
            ) {
                parent::__construct($mapper, $parser, $clientService, $tempManager, $logger, $sperre);
            }

            protected function speicherLimit(): int
            {
                return $this->limit;
            }

            /** Jeder Aufruf springt eine Stunde weiter, sobald der Test es verlangt. */
            public bool $zeitSpringt = false;

            private float $uhr = 0.0;

            protected function jetzt(): float
            {
                if ($this->zeitSpringt) {
                    $this->uhr += 3600.0;
                }

                return $this->uhr;
            }
        };
    }

    /** Das Speicherlimit, das der Dienst im Test sieht; 0 heisst «keines». */
    private int $speicherLimit = 0;

    /** @return list<array<string, string>> */
    private function daten(): array
    {
        return [[
            'externId' => '6821653',
            'titel' => '2026.15V',
            'url' => 'https://parlament.winterthur.ch/_doc/6821653',
            'kategorie' => 'Vorstoss',
            'datum' => '2026-03-02',
        ]];
    }

    public function testEinNeuesDokumentWirdGelesenUndAbgelegt(): void
    {
        $gelesen = $this->dienst()->aktualisiere(42, $this->daten());
        self::assertSame(1, $gelesen);
        self::assertCount(1, $this->geschrieben);
        $dokument = $this->geschrieben[0];
        self::assertSame(42, $dokument->getGeschaeftId());
        self::assertSame('Vorstoss', $dokument->getKategorie());
        self::assertSame(2, $dokument->getSeiten());
        self::assertStringContainsString('# Schriftliche Anfrage', (string) $dokument->getMarkdown());
        self::assertStringContainsString('Renditeobjekte', (string) $dokument->getVolltext());
        self::assertNotSame('', (string) $dokument->getQuelleHash());
        self::assertSame('', (string) $dokument->getFehler());
        self::assertNotSame([], $dokument->abschnitte(), 'die Struktur steht als JSON am Datensatz');
    }

    public function testEineNeueFassungDesLesersLiestDenBestandNeu(): void
    {
        $this->dienst()->aktualisiere(42, $this->daten());
        $bestand = $this->geschrieben[0];
        $this->bestand['6821653'] = $bestand;
        // Der Leser hat sich geändert: Tabellen, Zitate und die Rückseite des
        // Vorstosses kommen anders heraus. Die Datei ist dieselbe, der
        // abgelegte Inhalt trotzdem veraltet — daran erkennt es die Prüfsumme.
        $bestand->setQuelleHash('v1:' . substr((string) $bestand->getQuelleHash(), 3));
        $this->geschrieben = [];

        $gelesen = $this->dienst()->aktualisiere(42, $this->daten());
        self::assertSame(1, $gelesen, 'nach einer neuen Fassung wird neu gelesen');
        self::assertStringStartsWith('v2:', (string) $bestand->getQuelleHash());
    }

    public function testEinUnveraendertesDokumentWirdNichtNochEinmalGelesen(): void
    {
        $this->dienst()->aktualisiere(42, $this->daten());
        $bestand = $this->geschrieben[0];
        $this->bestand['6821653'] = $bestand;
        $markdownVorher = (string) $bestand->getMarkdown();
        $gelesenAmVorher = (string) $bestand->getGelesenAm();

        $gelesen = $this->dienst()->aktualisiere(42, $this->daten());
        self::assertSame(0, $gelesen, 'dieselbe Datei wird nicht neu zerlegt');
        self::assertSame($markdownVorher, (string) $bestand->getMarkdown());
        self::assertSame($gelesenAmVorher, (string) $bestand->getGelesenAm());
    }

    public function testEinNichtErreichbaresDokumentTraegtSeinenFehler(): void
    {
        $gelesen = $this->dienst(new \RuntimeException('404 Not Found'))->aktualisiere(42, $this->daten());
        self::assertSame(0, $gelesen);
        self::assertCount(1, $this->geschrieben, 'das Dokument bleibt verzeichnet');
        self::assertStringContainsString('404', (string) $this->geschrieben[0]->getFehler());
        self::assertSame('', (string) $this->geschrieben[0]->getMarkdown());
    }

    public function testDerAbgleichLiestHoechstensSeinKontingentUndVerzeichnetDenRest(): void
    {
        putenv('PARLWIN_DOKUMENT_PRO_LAUF=1');
        try {
            $zweiDokumente = [
                $this->daten()[0],
                ['externId' => '6821654', 'titel' => 'Beilage', 'url' => 'https://parlament.winterthur.ch/_doc/6821654', 'kategorie' => 'Beilage', 'datum' => '2026-03-02'],
            ];
            $gelesen = $this->dienst()->aktualisiere(42, $zweiDokumente);
            self::assertSame(1, $gelesen, 'nur eines wird gelesen');
            self::assertCount(2, $this->geschrieben, 'verzeichnet sind beide');
            self::assertSame('', (string) $this->geschrieben[1]->getMarkdown());
            self::assertSame('', (string) $this->geschrieben[1]->getGelesenAm(), 'das zweite bleibt offen');
        } finally {
            putenv('PARLWIN_DOKUMENT_PRO_LAUF');
        }
    }

    public function testDerHintergrundauftragLiestDieOffenenNach(): void
    {
        $offen = new GeschaeftDokument();
        $offen->setGeschaeftId(42);
        $offen->setExternId('6821653');
        $offen->setUrl('https://parlament.winterthur.ch/_doc/6821653');
        $this->offene = [$offen];

        $gelesen = $this->dienst()->leseOffene(10);
        self::assertSame(1, $gelesen);
        self::assertStringContainsString('# Schriftliche Anfrage', (string) $offen->getMarkdown());
        self::assertNotSame('', (string) $offen->getGelesenAm());
        self::assertSame([$offen], $this->geschrieben, 'das Gelesene wird geschrieben');
        self::assertSame(
            'v2:',
            $this->gefragteFassung,
            'der Auftrag holt auch, was eine frühere Fassung des Lesers abgelegt hat',
        );
    }

    /**
     * Am 24.09.2026 beendete ein Dokument den ganzen Lauf: «Allowed memory size
     * of 1073741824 bytes exhausted» beim Entpacken seiner Ströme. Ein solcher
     * Fehler lässt sich nicht abfangen, also darf es nicht dazu kommen — ist der
     * Speicher zur Hälfte belegt, hört der Lauf auf, und der nächste fängt
     * frisch an.
     */
    public function testEinLaufHoertAufBevorDerSpeicherAusgeht(): void
    {
        $offen = new GeschaeftDokument();
        $offen->setGeschaeftId(42);
        $offen->setExternId('6821653');
        $offen->setUrl('https://parlament.winterthur.ch/_doc/6821653');
        $this->offene = [$offen];
        // Ein Limit knapp über dem, was der Testprozess schon belegt: Damit ist
        // die Hälfte überschritten, bevor das erste Dokument anfängt.
        $this->speicherLimit = (int) (memory_get_usage(true) * 1.5);

        $gelesen = $this->dienst()->leseOffene(10);

        self::assertSame(0, $gelesen, 'der Lauf fängt kein Dokument mehr an');
        self::assertSame(0, $this->abrufe, 'und lädt auch nichts mehr herunter');
        self::assertSame('', (string) $offen->getMarkdown(), 'das Dokument bleibt offen');
    }

    /**
     * Reicht der Speicher für genau dieses Dokument nicht, stirbt der eigene
     * Leseprozess daran, das Dokument trägt seinen Grund, und der Lauf geht
     * weiter — statt den ganzen Vorgang mitzureissen.
     */
    public function testEinZuGrossesDokumentTraegtSeinenGrundStattDenLaufZuBeenden(): void
    {
        // So wenig Speicher, dass der Leser des Dokuments daran stirbt.
        putenv('PARLWIN_DOKUMENT_LESER_SPEICHER=2M');
        try {
            $gelesen = $this->dienst()->aktualisiere(42, $this->daten());
        } finally {
            putenv('PARLWIN_DOKUMENT_LESER_SPEICHER');
        }

        self::assertSame(0, $gelesen);
        self::assertCount(1, $this->geschrieben, 'das Dokument bleibt verzeichnet');
        $grund = (string) $this->geschrieben[0]->getFehler();
        self::assertStringContainsString('liess sich nicht lesen', $grund);
        self::assertStringContainsString('Speicher', $grund);
        // Die Spalte fasst 500 Zeichen. Die ganze Spur des gestorbenen Lesers
        // sprengte sie am 24.09.2026 und beendete den Lauf mit «Data too long».
        self::assertLessThanOrEqual(500, mb_strlen($grund));
        self::assertStringNotContainsString('Stack trace', $grund, 'die Spur steht im Protokoll');
        self::assertSame('', (string) $this->geschrieben[0]->getMarkdown());
    }

    public function testEineLangeFehlermeldungPasstInIhreSpalte(): void
    {
        $lang = str_repeat('sehr langer Grund. ', 100);

        $this->dienst(new \RuntimeException($lang))->aktualisiere(42, $this->daten());

        self::assertLessThanOrEqual(500, mb_strlen((string) $this->geschrieben[0]->getFehler()));
    }

    /**
     * Und der Aufrufer überlebt es: Das nächste Dokument wird gelesen.
     */
    public function testNachEinemGestorbenenLeserGehtDerLaufWeiter(): void
    {
        $this->dienst()->aktualisiere(42, $this->daten());

        self::assertStringContainsString(
            '# Schriftliche Anfrage',
            (string) $this->geschrieben[0]->getMarkdown(),
            'der Prozess des Aufrufers hat den Fehlschlag überstanden',
        );
    }

    /**
     * Ein Dokument zu lesen dauert bis zu einer Minute; wer abbricht, soll nicht
     * auf das Kontingent des Laufs warten. Der Abgleich verzeichnet die
     * Dokumente weiter und liest keines mehr.
     */
    public function testNachEinemAbbruchWirdKeinDokumentMehrGelesen(): void
    {
        $sperre = $this->createStub(SyncLockService::class);
        $sperre->method('abbruchAngefordert')->willReturn(true);

        $gelesen = $this->dienst(null, $sperre)->aktualisiere(42, $this->daten());

        self::assertSame(0, $gelesen);
        self::assertSame(0, $this->abrufe, 'es wird nichts mehr geladen');
        self::assertCount(1, $this->geschrieben, 'verzeichnet wird das Dokument trotzdem');
    }

    /**
     * Der Abgleich meldet dem Status regelmässig, dass er lebt; bleibt das
     * länger als 15 Minuten aus, gilt er als abgestürzt. Ein einzelnes Dokument
     * kann Minuten dauern, darum hört der Abgleich nach seiner Zeit auf zu
     * lesen und verzeichnet den Rest — gemessen am 25.09.2026, als ein Abgleich
     * mit «Kein Heartbeat innerhalb des zulässigen Zeitfensters» endete.
     */
    public function testNachSeinerZeitLiestDerAbgleichKeinDokumentMehr(): void
    {
        $dienst = $this->dienst();
        $dienst->zeitSpringt = true;
        $zwei = $this->daten();
        $zwei[] = ['externId' => '6821654'] + $zwei[0];

        $gelesen = $dienst->aktualisiere(42, $zwei);

        self::assertSame(1, $gelesen, 'das erste Dokument wird gelesen, dann ist die Zeit um');
        self::assertSame(1, $this->abrufe, 'für das zweite wird nichts mehr geladen');
        self::assertCount(2, $this->geschrieben, 'verzeichnet werden beide');
        self::assertSame('', (string) $this->geschrieben[1]->getMarkdown(), 'das zweite bleibt offen');
    }

    public function testEinDokumentOhneNummerWirdUebergangen(): void
    {
        $gelesen = $this->dienst()->aktualisiere(42, [['titel' => 'ohne Verweis', 'url' => '', 'externId' => '']]);
        self::assertSame(0, $gelesen);
        self::assertSame([], $this->geschrieben);
        self::assertSame(0, $this->abrufe, 'ohne Verweis wird nichts geladen');
    }
}
