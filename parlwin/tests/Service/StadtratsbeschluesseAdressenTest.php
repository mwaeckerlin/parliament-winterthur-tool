<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\ScraperService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Welche Adressen der Abgleich der Stadtratsbeschlüsse anfragt (F146).
 *
 * Der Server holt die Seiten selbst. Er fragt darum nur http und https an, nur
 * unter der eingestellten Basis der Stadt-Webseite, und verwirft eine Antwort,
 * die über eine Weiterleitung von einem anderen Rechner kam. Die Tests mit
 * echter Verbindung laufen gegen einen PHP-Webserver auf 127.0.0.1, den der
 * Test selbst startet und beendet.
 */
class StadtratsbeschluesseAdressenTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../Fixtures/html';

    /** @var resource|null */
    private $server = null;
    private string $verzeichnis = '';

    protected function setUp(): void
    {
        putenv('PARLWIN_STADT_BASE_URL');
        putenv('PARLWIN_SRB_SEITEN=1');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
        if ($this->verzeichnis !== '') {
            @unlink($this->verzeichnis . '/router.php');
            @unlink($this->verzeichnis . '/anfragen.log');
            @rmdir($this->verzeichnis);
        }
        foreach (['PARLWIN_STADT_BASE_URL', 'PARLWIN_SRB_SEITEN', 'PARLWIN_SYNC_HTTP_TIMEOUT', 'PARLWIN_SYNC_HTTP_LOW_SPEED_TIME'] as $name) {
            putenv($name);
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function ungueltigeBasen(): array
    {
        return [
            'Datei' => ['file:///etc', 'http:// oder https://'],
            'anderes Protokoll' => ['gopher://stadt.winterthur.ch', 'http:// oder https://'],
            'ohne Rechner' => ['http:///stadtratsbeschluesse', 'nicht lesen'],
            'mit Benutzer' => ['https://jemand:geheim@stadt.winterthur.ch', 'Benutzer oder Passwort'],
            'mit Abfrage' => ['https://stadt.winterthur.ch/?umleiten=1', 'Abfrage'],
            'mit Anker' => ['https://stadt.winterthur.ch/#oben', 'Anker'],
        ];
    }

    /**
     * Eine Basis, die keine Adresse der Stadt-Webseite sein kann, hält den
     * Abgleich vor der ersten Anfrage an, und die Meldung nennt die Einstellung.
     */
    #[DataProvider('ungueltigeBasen')]
    public function testEineUngueltigeBasisWirdVorDerErstenAnfrageAbgewiesen(string $basis, string $grund): void
    {
        putenv('PARLWIN_STADT_BASE_URL=' . $basis);
        $clientService = $this->createMock(IClientService::class);
        $clientService->expects(self::never())->method('newClient');
        $dienst = new ScraperService($clientService, $this->createStub(LoggerInterface::class));

        try {
            $dienst->ladeStadtratsbeschluesse();
            self::fail('die Basis ' . $basis . ' wurde angenommen');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('PARLWIN_STADT_BASE_URL', $e->getMessage());
            self::assertStringContainsString($grund, $e->getMessage());
            self::assertStringNotContainsString('geheim', $e->getMessage(), 'die Meldung zeigt das Passwort');
        }
    }

    public function testEineGueltigeBasisMitPfadUndPortBleibtStehen(): void
    {
        putenv('PARLWIN_STADT_BASE_URL=http://parlwin-quelle:8080/stadt/');
        self::assertSame('http://parlwin-quelle:8080/stadt', ScraperService::stadtBasisUrl());
    }

    /**
     * Ein Rechner, dessen Name mit dem der Stadt beginnt, ist ein anderer
     * Rechner: Seine Detailseite wird nicht angefragt.
     */
    public function testEinFremderRechnerMitGleichemAnfangWirdNichtAngefragt(): void
    {
        $angefragt = [];
        $dienst = new ScraperServiceMitFixtures(
            $this->createStub(IClientService::class),
            $this->createStub(LoggerInterface::class),
        );
        $detail = (string) file_get_contents(self::FIXTURES . '/stadtratsbeschluss-detail-20261279.html');
        $dienst->setzeResolver(static function (string $url) use (&$angefragt, $detail): string {
            $angefragt[] = $url;
            return $detail;
        });
        $eigene = 'https://stadt.winterthur.ch/stadtratsbeschluesse/2026-10-21/20261279';
        $fremde = 'https://stadt.winterthur.ch.boese.example/stadtratsbeschluesse/2026-10-21/20261278';

        $ergebnis = (new \ReflectionMethod(ScraperService::class, 'reichereBeschluesseMitDetailsAn'))->invoke(
            $dienst,
            [['externId' => '20261279', 'url' => $eigene], ['externId' => '20261278', 'url' => $fremde]]
        );

        self::assertSame([$eigene], $angefragt);
        self::assertTrue($ergebnis[0]['detailseiteGelesen']);
        self::assertCount(2, $ergebnis[0]['dokumente']);
        self::assertFalse($ergebnis[1]['detailseiteGelesen']);
        self::assertArrayNotHasKey('dokumente', $ergebnis[1]);
    }

    /**
     * Leitet die Stadt eine Detailseite auf einen anderen Rechner weiter, wird
     * die Antwort verworfen. Gemessen über curl gegen einen echten Webserver:
     * Er antwortet auf 127.0.0.1 und auf 127.0.0.2, und nur 127.0.0.1 ist die
     * Basis.
     */
    public function testEineWeiterleitungAufEinenAnderenRechnerWirdVerworfen(): void
    {
        $port = $this->starteServer();
        putenv('PARLWIN_STADT_BASE_URL=http://127.0.0.1:' . $port);
        $dienst = new ScraperService($this->dateiClientService(), $this->createStub(LoggerInterface::class));

        $beschluesse = [];
        foreach ($dienst->ladeStadtratsbeschluesse() as $beschluss) {
            $beschluesse[$beschluss['externId']] = $beschluss;
        }

        self::assertCount(12, $beschluesse);
        $umgeleitet = $beschluesse['20261279'];
        self::assertFalse($umgeleitet['detailseiteGelesen'], 'die Antwort des anderen Rechners wurde übernommen');
        self::assertSame([], $umgeleitet['dokumente']);
        self::assertSame('', $umgeleitet['publiziertAm']);
        $direkt = array_values(array_filter($beschluesse, static fn (array $b): bool => $b['externId'] !== '20261279'))[0];
        self::assertTrue($direkt['detailseiteGelesen'], 'eine Detailseite unter der Basis wurde nicht gelesen');
        self::assertCount(2, $direkt['dokumente']);
        self::assertStringStartsWith('http://127.0.0.1:' . $port . '/download/', $direkt['dokumente'][0]['url']);
    }

    /**
     * Eine Weiterleitung auf einen anderen Rechner erreicht diesen Rechner
     * nicht: Der Testserver hält jede Anfrage an 127.0.0.2 fest. Eine
     * Weiterleitung unter der Basis folgt der Abruf und liest die Seite.
     */
    public function testEineWeiterleitungAufEinenAnderenRechnerErreichtIhnNicht(): void
    {
        $port = $this->starteServer();
        putenv('PARLWIN_STADT_BASE_URL=http://127.0.0.1:' . $port);
        $dienst = new ScraperService($this->dateiClientService(), $this->createStub(LoggerInterface::class));

        $beschluesse = [];
        foreach ($dienst->ladeStadtratsbeschluesse() as $beschluss) {
            $beschluesse[$beschluss['externId']] = $beschluss;
        }

        $angekommen = (string) @file_get_contents($this->verzeichnis . '/anfragen.log');
        self::assertSame('', $angekommen, 'die Weiterleitung hat den anderen Rechner erreicht');
        self::assertFalse($beschluesse['20261279']['detailseiteGelesen']);
        self::assertTrue($beschluesse['20261285']['detailseiteGelesen'], 'der Weiterleitung unter der Basis ist der Abruf nicht gefolgt');
        self::assertCount(2, $beschluesse['20261285']['dokumente']);
    }

    /**
     * Antwortet die Liste mit einer Fehlerseite, nennt die Meldung Adresse und
     * HTTP-Status und nichts aus dem Rumpf. Der Client wirft wie Guzzle eine
     * Ausnahme, deren Meldung den Anfang des Rumpfs trägt.
     */
    public function testEineFehlerseiteDerStadtErscheintNichtInDerMeldung(): void
    {
        $port = $this->starteServer();
        putenv('PARLWIN_STADT_BASE_URL=http://127.0.0.1:' . $port . '/kaputt');
        $dienst = new ScraperService($this->guzzleClientService(), $this->createStub(LoggerInterface::class));

        try {
            $dienst->ladeStadtratsbeschluesse();
            self::fail('die Fehlerseite wurde als Liste gelesen');
        } catch (\RuntimeException $e) {
            self::assertStringNotContainsString('<', $e->getMessage(), 'die Meldung trägt HTML aus dem Rumpf');
            self::assertSame(
                'Die Liste der Stadtratsbeschlüsse unter http://127.0.0.1:' . $port . '/kaputt/stadtratsbeschluesse ist nicht erreichbar (HTTP 500).',
                $e->getMessage()
            );
        }
    }

    /** Kommt keine Antwort, nennt die Meldung den Grund von curl. */
    public function testEinNetzfehlerNenntDenGrundVonCurl(): void
    {
        $frei = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($frei);
        $name = (string) stream_socket_get_name($frei, false);
        $port = (int) substr($name, strrpos($name, ':') + 1);
        fclose($frei);
        putenv('PARLWIN_STADT_BASE_URL=http://127.0.0.1:' . $port);
        $dienst = new ScraperService($this->guzzleClientService(), $this->createStub(LoggerInterface::class));

        try {
            $dienst->ladeStadtratsbeschluesse();
            self::fail('ein geschlossener Port lieferte eine Liste');
        } catch (\RuntimeException $e) {
            self::assertSame(
                'Die Liste der Stadtratsbeschlüsse unter http://127.0.0.1:' . $port . '/stadtratsbeschluesse ist nicht erreichbar (keine Verbindung zum Rechner).',
                $e->getMessage()
            );
        }
    }

    /**
     * Ein HTTP-Client, der über curl holt und Fehler meldet wie Guzzle hinter
     * dem Client von Nextcloud: Bei einem Status ab 400 eine Ausnahme mit
     * `getResponse()` und dem Anfang des Rumpfs in der Meldung, ohne Antwort
     * eine mit `getHandlerContext()` und der Fehlernummer von curl.
     */
    private function guzzleClientService(): IClientService
    {
        return new class implements IClientService {
            public function newClient(): IClient
            {
                return new class implements IClient {
                    public function get(string $uri, array $options = [])
                    {
                        $ch = curl_init($uri);
                        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
                        $rumpf = curl_exec($ch);
                        $nummer = curl_errno($ch);
                        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                        if ($nummer !== 0) {
                            $text = curl_error($ch);
                            throw new class('cURL error ' . $nummer . ': ' . $text . ' (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for ' . $uri, ['errno' => $nummer, 'error' => $text]) extends \RuntimeException {
                                public function __construct(string $meldung, private array $kontext)
                                {
                                    parent::__construct($meldung);
                                }

                                public function getHandlerContext(): array
                                {
                                    return $this->kontext;
                                }
                            };
                        }
                        if ($code >= 400) {
                            $antwort = new class($code) {
                                public function __construct(private int $code)
                                {
                                }

                                public function getStatusCode(): int
                                {
                                    return $this->code;
                                }
                            };
                            throw new class('Server error: `GET ' . $uri . '` resulted in a `' . $code . ' Internal Server Error` response:' . "\n" . substr((string) $rumpf, 0, 120) . ' (truncated...)', $antwort) extends \RuntimeException {
                                public function __construct(string $meldung, private object $antwort)
                                {
                                    parent::__construct($meldung);
                                }

                                public function getResponse(): object
                                {
                                    return $this->antwort;
                                }
                            };
                        }
                        return new class((string) $rumpf) implements IResponse {
                            public function __construct(private string $inhalt)
                            {
                            }

                            public function getBody(): string
                            {
                                return $this->inhalt;
                            }
                        };
                    }

                    public function post(string $uri, array $options = [])
                    {
                        throw new \LogicException('post wird hier nicht gebraucht');
                    }
                };
            }
        };
    }

    /**
     * Auch der Abruf über den HTTP-Client von Nextcloud folgt nur einer
     * Weiterleitung unter der Basis: Guzzle ruft `on_redirect`, bevor es das
     * Ziel anfragt.
     */
    public function testDerHttpClientFolgtNurEinerWeiterleitungUnterDerBasis(): void
    {
        putenv('PARLWIN_STADT_BASE_URL=http://127.0.0.1:8080');
        $optionen = [];
        $client = $this->createStub(IClient::class);
        $client->method('get')->willReturnCallback(function (string $uri, array $o) use (&$optionen): IResponse {
            $optionen = $o;
            $antwort = $this->createStub(IResponse::class);
            $antwort->method('getBody')->willReturn((string) file_get_contents(self::FIXTURES . '/stadtratsbeschluesse-list.html'));
            return $antwort;
        });
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);
        $dienst = new class($clientService, $this->createStub(LoggerInterface::class)) extends ScraperService {
            protected function ladeHtmlParallel(array $urls, int $parallel, bool $mitSequenziellemFallback = true, ?callable $onComplete = null): array
            {
                return [];
            }
        };
        $dienst->ladeStadtratsbeschluesse();

        $weiter = $optionen['allow_redirects']['on_redirect'] ?? null;
        self::assertIsCallable($weiter, 'der Abruf übergibt keine Prüfung der Weiterleitung');
        self::assertSame(['http', 'https'], $optionen['allow_redirects']['protocols'] ?? null);
        $weiter(null, null, 'http://127.0.0.1:8080/stadtratsbeschluesse?page=1');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('verlässt die Quelle');
        $weiter(null, null, 'http://127.0.0.1:8080.boese.example/stadtratsbeschluesse');
    }

    /**
     * Der Abgleich fragt nur über http und https an. Eine Adresse mit einem
     * anderen Protokoll öffnet keine Verbindung: Der Test lauscht auf dem Port,
     * den die Adresse nennt, und misst, ob dort eine Verbindung ankam.
     */
    public function testEinAnderesProtokollOeffnetKeineVerbindung(): void
    {
        $protokolle = array_values(array_intersect(['dict', 'gopher', 'telnet'], curl_version()['protocols']));
        self::assertNotSame([], $protokolle, 'curl kennt keines der Protokolle dict, gopher oder telnet');
        // Grenzen für den Fall, dass curl die Verbindung doch öffnet: Die
        // Gegenstelle antwortet nie, und der Lauf soll nach Sekunden enden.
        putenv('PARLWIN_SYNC_HTTP_TIMEOUT=5');
        putenv('PARLWIN_SYNC_HTTP_LOW_SPEED_TIME=3');

        $lauscher = stream_socket_server('tcp://127.0.0.1:0', $fehlerNummer, $fehlerText);
        self::assertNotFalse($lauscher, $fehlerText);
        $name = (string) stream_socket_get_name($lauscher, false);
        $port = (int) substr($name, strrpos($name, ':') + 1);

        $dienst = new class($this->dateiClientService(), $this->createStub(LoggerInterface::class)) extends ScraperService {
            /** @param list<string> $urls */
            public function lade(array $urls): array
            {
                return $this->ladeHtmlParallel($urls, 2, false);
            }
        };
        $ergebnis = $dienst->lade([$protokolle[0] . '://127.0.0.1:' . $port . '/stadtratsbeschluesse']);

        $lesen = [$lauscher];
        $schreiben = null;
        $ausnahmen = null;
        $angekommen = stream_select($lesen, $schreiben, $ausnahmen, 0);
        fclose($lauscher);
        self::assertSame([], $ergebnis);
        self::assertSame(0, $angekommen, 'curl hat eine Verbindung über ' . $protokolle[0] . ' geöffnet');
    }

    /**
     * Startet einen PHP-Webserver, der die gespeicherten Seiten ausliefert. Die
     * Detailseite 20261279 leitet er auf 127.0.0.2 weiter.
     */
    private function starteServer(): int
    {
        $frei = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($frei);
        $name = (string) stream_socket_get_name($frei, false);
        $port = (int) substr($name, strrpos($name, ':') + 1);
        fclose($frei);

        $this->verzeichnis = sys_get_temp_dir() . '/parlwin-stadtquelle-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->verzeichnis));
        file_put_contents($this->verzeichnis . '/router.php', <<<'PHP'
            <?php
            $pfad = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $rechner = (string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
            $fixtures = (string) getenv('FIXTURES');
            if ($rechner !== '127.0.0.1') {
                file_put_contents((string) getenv('ANFRAGEN'), $rechner . ' ' . $pfad . "\n", FILE_APPEND);
            }
            if (str_starts_with($pfad, '/kaputt/')) {
                http_response_code(500);
                echo '<!DOCTYPE html><html><body><h1>Interner Fehler</h1><p>nginx</p></body></html>';
                return true;
            }
            if ($pfad === '/stadtratsbeschluesse') {
                readfile($fixtures . '/stadtratsbeschluesse-list.html');
                return true;
            }
            if ($rechner === '127.0.0.1' && str_ends_with($pfad, '/20261279')) {
                header('Location: http://127.0.0.2:' . getenv('PORT') . '/anderswo/20261279', true, 302);
                return true;
            }
            if ($rechner === '127.0.0.1' && $pfad === '/stadtratsbeschluesse/2026-10-21/20261285') {
                header('Location: /stadtratsbeschluesse/umgezogen/20261285', true, 301);
                return true;
            }
            if (str_starts_with($pfad, '/stadtratsbeschluesse/') || str_starts_with($pfad, '/anderswo/')) {
                readfile($fixtures . '/stadtratsbeschluss-detail-20261279.html');
                return true;
            }
            http_response_code(404);
            return true;
            PHP);

        $this->server = proc_open(
            [PHP_BINARY, '-S', '0.0.0.0:' . $port, $this->verzeichnis . '/router.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $rohre,
            $this->verzeichnis,
            ['FIXTURES' => realpath(self::FIXTURES), 'PORT' => (string) $port, 'ANFRAGEN' => $this->verzeichnis . '/anfragen.log']
        );
        self::assertIsResource($this->server, 'der PHP-Webserver liess sich nicht starten');

        // Der Server meldet seine Bereitschaft nicht: Der Test versucht die
        // Verbindung alle 50ms, höchstens 200 Mal (10s).
        for ($versuch = 0; $versuch < 200; $versuch++) {
            $verbindung = @stream_socket_client('tcp://127.0.0.1:' . $port, $nummer, $text, 1);
            if ($verbindung !== false) {
                fclose($verbindung);
                return $port;
            }
            usleep(50000);
        }
        self::fail('der PHP-Webserver nimmt auf Port ' . $port . ' keine Verbindung an');
    }

    /** Ein HTTP-Client, der die Liste über PHP selbst holt, ohne Weiterleitungen. */
    private function dateiClientService(): IClientService
    {
        return new class implements IClientService {
            public function newClient(): IClient
            {
                return new class implements IClient {
                    public function get(string $uri, array $options = [])
                    {
                        $kontext = stream_context_create(['http' => ['follow_location' => 0, 'timeout' => 10]]);
                        $inhalt = @file_get_contents($uri, false, $kontext);
                        if ($inhalt === false) {
                            throw new \RuntimeException('nicht erreichbar: ' . $uri);
                        }
                        return new class($inhalt) implements IResponse {
                            public function __construct(private string $inhalt)
                            {
                            }

                            public function getBody(): string
                            {
                                return $this->inhalt;
                            }
                        };
                    }

                    public function post(string $uri, array $options = [])
                    {
                        throw new \LogicException('post wird hier nicht gebraucht');
                    }
                };
            }
        };
    }
}
