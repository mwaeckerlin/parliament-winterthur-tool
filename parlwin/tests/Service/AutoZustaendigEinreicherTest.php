<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\FraktionsrolleMapper;
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftZustaendigkeit;
use OCA\ParliamentWinterthur\Db\GeschaeftZustaendigkeitMapper;
use OCA\ParliamentWinterthur\Db\Kommission;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\Mitglied;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCA\ParliamentWinterthur\Db\NotizRevisionMapper;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Sind Mitglieder der eigenen Fraktion Einreicher eines Geschäfts und ist noch
 * keine Zuständigkeit gesetzt, werden sie automatisch zuständig — in derselben
 * Reihenfolge wie bei den Einreichern. Das hat Vorrang vor der Zuweisung nach
 * Kommission.
 */
class AutoZustaendigEinreicherTest extends TestCase
{
    private function mitglied(string $externId, string $vorname, string $name): Mitglied
    {
        $m = new Mitglied();
        $m->setExternId($externId);
        $m->setVorname($vorname);
        $m->setName($name);
        $m->setFraktion('SVP');
        return $m;
    }

    /**
     * @param array<int, array{name: string, rolle: string}> $einreicher
     */
    private function geschaeft(int $id, array $einreicher, string $status = 'In Kommission'): Geschaeft
    {
        $g = new Geschaeft();
        $g->setId($id);
        $g->setStatus($status);
        $g->setEinreicher(json_encode($einreicher, JSON_THROW_ON_ERROR));
        return $g;
    }

    /**
     * @param array<int, array<string, mixed>> $gesetzt
     * @param array<int, Mitglied>|null $mitglieder
     */
    private function makeService(Geschaeft $geschaeft, array &$gesetzt, ?array $mitglieder = null): FraktionsarbeitService
    {
        $geschaeftMapper = $this->createStub(GeschaeftMapper::class);
        $geschaeftMapper->method('findAll')->willReturn([$geschaeft]);
        $geschaeftMapper->method('find')->willReturn($geschaeft);

        // Noch keine Zuständigkeit gesetzt.
        $zustaendigkeitMapper = $this->createStub(GeschaeftZustaendigkeitMapper::class);
        $zustaendigkeitMapper->method('findAktiveByGeschaeft')->willReturn([]);
        $zustaendigkeitMapper->method('ersetzeAktive')->willReturnCallback(
            function (int $geschaeftId, array $personen) use (&$gesetzt): void {
                foreach ($personen as $p) {
                    $gesetzt[] = [
                        'personKey' => (string) ($p['person_key'] ?? ''),
                        'personName' => (string) ($p['person_name'] ?? ''),
                        'istHaupt' => (bool) ($p['ist_haupt'] ?? false),
                    ];
                }
            }
        );

        // Kommission, die zum Status passt und ein anderes Fraktionsmitglied enthält.
        $kommission = new Kommission();
        $kommission->setName('In Kommission');
        $kommission->setAktiv(true);
        $kommission->setMitglieder(json_encode([['externId' => 'M3']], JSON_THROW_ON_ERROR));
        $kommissionMapper = $this->createStub(KommissionMapper::class);
        $kommissionMapper->method('findAll')->willReturn([$kommission]);

        $mitgliedMapper = $this->createStub(MitgliedMapper::class);
        $mitgliedMapper->method('findByFraktion')->willReturn($mitglieder ?? [
            $this->mitglied('M1', 'Anna', 'Muster'),
            $this->mitglied('M2', 'Beat', 'Beispiel'),
            $this->mitglied('M3', 'Carla', 'Kommission'),
        ]);

        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn(string $app, string $key, string $default = ''): string
                => $key === 'fraktion' ? 'SVP' : $default
        );

        $userSession = $this->createStub(IUserSession::class);
        $userSession->method('getUser')->willReturn(null);

        return new FraktionsarbeitService(
            $geschaeftMapper,
            $this->createStub(GeschaeftAktionMapper::class),
            $zustaendigkeitMapper,
            $this->createStub(FraktionsrolleMapper::class),
            $mitgliedMapper,
            $kommissionMapper,
            $this->createStub(GeschaeftEreignisMapper::class),
            $config,
            $userSession,
            $this->createStub(IGroupManager::class),
            $this->createStub(NotizRevisionMapper::class),
        );
    }

    public function testEinreichendeFraktionsmitgliederWerdenAutomatischZustaendig(): void
    {
        $geschaeft = $this->geschaeft(1, [
            ['name' => 'Beat Beispiel', 'rolle' => 'Erstunterzeichner'],
            ['name' => 'Anna Muster', 'rolle' => 'Mitunterzeichner'],
        ]);
        $gesetzt = [];
        $service = $this->makeService($geschaeft, $gesetzt);

        $service->autoZuweisenEinreicher();

        $namen = array_column($gesetzt, 'personName');
        self::assertSame(
            ['Beat Beispiel', 'Anna Muster'],
            $namen,
            'Die Zuständigkeiten müssen der Reihenfolge der Einreicher folgen'
        );
    }

    public function testDerErsteEinreicherWirdHauptzustaendig(): void
    {
        $geschaeft = $this->geschaeft(1, [
            ['name' => 'Beat Beispiel', 'rolle' => 'Erstunterzeichner'],
            ['name' => 'Anna Muster', 'rolle' => 'Mitunterzeichner'],
        ]);
        $gesetzt = [];
        $service = $this->makeService($geschaeft, $gesetzt);

        $service->autoZuweisenEinreicher();

        self::assertTrue($gesetzt[0]['istHaupt'], 'Der erste Einreicher ist hauptzuständig');
    }

    /**
     * Regression: Die Parlamentswebseite liefert Einreicher als
     * «Nachname Vorname» («Wäckerlin Marc») — auch diese Form muss matchen,
     * sonst wird real NIE ein Einreicher zuständig.
     */
    public function testEinreicherInWebseitenFormNachnameVornameWirdZustaendig(): void
    {
        $geschaeft = $this->geschaeft(1, [
            ['name' => 'Beispiel Beat', 'rolle' => 'Erstunterzeichner', 'externId' => ''],
        ]);
        $gesetzt = [];
        $service = $this->makeService($geschaeft, $gesetzt);

        $service->autoZuweisenEinreicher();

        self::assertSame(
            ['Beat Beispiel'],
            array_column($gesetzt, 'personName'),
            'Einreicher in Webseiten-Form «Nachname Vorname» muss zugewiesen werden'
        );
    }

    /**
     * Die Webseite liefert zu verlinkten Einreichern die Personen-ID — sie
     * matcht unabhängig von der Namensschreibweise.
     */
    public function testEinreicherMatchtUeberExternIdAuchBeiAbweichendemNamen(): void
    {
        $geschaeft = $this->geschaeft(1, [
            ['name' => 'B. Beispiel-Anders', 'rolle' => 'Erstunterzeichner', 'externId' => 'M2'],
        ]);
        $gesetzt = [];
        $service = $this->makeService($geschaeft, $gesetzt);

        $service->autoZuweisenEinreicher();

        self::assertSame(
            ['Beat Beispiel'],
            array_column($gesetzt, 'personName'),
            'Einreicher mit Personen-ID muss über die ID zugewiesen werden'
        );
    }

    public function testFremdeEinreicherWerdenIgnoriert(): void
    {
        $geschaeft = $this->geschaeft(1, [
            ['name' => 'Fremde Person', 'rolle' => 'Erstunterzeichner'],
        ]);
        $gesetzt = [];
        $service = $this->makeService($geschaeft, $gesetzt);

        $service->autoZuweisenEinreicher();

        self::assertSame([], $gesetzt, 'Nur Mitglieder der eigenen Fraktion werden zuständig');
    }

    /**
     * Contract-Guard Scraper→Matching: Die Einreicher aus einer ECHTEN
     * Geschäfts-Detailseite (Fixture) müssen — unverändert, ohne
     * zurechtgelegtes Test-Format — zur Zuweisung führen. Verhindert, dass
     * Test-Fixtures eine Datenform annehmen, die die Webseite nie liefert.
     */
    public function testEchteWebseitenEinreicherFuehrenZurZuweisung(): void
    {
        $scraper = new ScraperService(
            $this->createStub(IClientService::class),
            $this->createStub(LoggerInterface::class),
        );
        $html = (string) file_get_contents(__DIR__ . '/../Fixtures/html/geschaeft-detail-2410948.html');
        $details = $scraper->extrahiereGeschaeftDetailsAusHtml($html);
        self::assertNotSame([], $details['einreicher'] ?? [], 'Fixture muss Einreicher liefern');

        $geschaeft = $this->geschaeft(1, $details['einreicher']);
        $gesetzt = [];
        $service = $this->makeService($geschaeft, $gesetzt, [
            $this->mitglied('280925', 'Marc', 'Wäckerlin'),
        ]);

        $service->autoZuweisenEinreicher();

        self::assertSame(
            ['Marc Wäckerlin'],
            array_column($gesetzt, 'personName'),
            'Der Einreicher aus dem echten Webseiten-HTML muss zugewiesen werden'
        );
    }

    public function testEinreicherHabenVorrangVorDerKommission(): void
    {
        // Das Geschäft hat einen Einreicher aus der Fraktion UND passt zu einer
        // Kommission mit einem anderen Fraktionsmitglied (Carla). Der Einreicher gewinnt.
        $geschaeft = $this->geschaeft(1, [
            ['name' => 'Anna Muster', 'rolle' => 'Erstunterzeichner'],
        ]);
        $gesetzt = [];
        $service = $this->makeService($geschaeft, $gesetzt);

        $service->autoZuweisenKommissionsmitglieder();

        $namen = array_column($gesetzt, 'personName');
        self::assertContains('Anna Muster', $namen, 'Der Einreicher muss zuständig werden');
        self::assertNotContains('Carla Kommission', $namen, 'Die Kommissions-Zuweisung darf den Einreicher nicht verdrängen');
    }
}
