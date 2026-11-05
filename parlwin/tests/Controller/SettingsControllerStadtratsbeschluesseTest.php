<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCA\ParliamentWinterthur\Controller\SettingsController;
use OCA\ParliamentWinterthur\Db\Ereignis;
use OCA\ParliamentWinterthur\Db\FraktionMapper;
use OCA\ParliamentWinterthur\Db\StadtratsbeschlussMapper;
use OCA\ParliamentWinterthur\Service\EreignisService;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\FraktionsraumService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCA\ParliamentWinterthur\Service\StadtratsbeschlussService;
use OCA\ParliamentWinterthur\Service\SyncLockService;
use OCA\ParliamentWinterthur\Service\SyncProcessService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Der Ausweichweg der Synchronisation (`SettingsController::fuehreSynchronisationAus`)
 * und der Stand, den die Verwaltung über `GET /sync/status` liest. Der
 * Ausweichweg ist derselbe Lauf wie `occ parlwin:sync`: Der Controller ruft den
 * echten `SyncCommand`, und nur die Dienste dahinter sind ersetzt.
 */
class SettingsControllerStadtratsbeschluesseTest extends TestCase
{
    private string $lockFile;
    /** @var array<string, string> */
    private array $werte = [];
    /** @var list<array<int, mixed>> */
    private array $protokoll = [];
    /** @var array<string, array<string, mixed>> */
    private array $ereignisse = [];

    protected function setUp(): void
    {
        parent::setUp();
        putenv('PARLWIN_STADT_BASE_URL');
        $this->lockFile = sys_get_temp_dir() . '/parlwin-settings-srb-test-' . uniqid('', true) . '.lock';
    }

    protected function tearDown(): void
    {
        @unlink($this->lockFile);
        @unlink($this->lockFile . '.abbruch');
        @unlink($this->lockFile . '.pid');
        parent::tearDown();
    }

    private function konfiguration(): IConfig
    {
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            fn (string $_app, string $key, string $standard = ''): string => $this->werte[$key] ?? $standard
        );
        $config->method('setAppValue')->willReturnCallback(
            function (string $_app, string $key, string $wert): void {
                $this->werte[$key] = $wert;
            }
        );
        return $config;
    }

    /** Der Abgleich der Stadtratsbeschlüsse gegen eine Webseite, die nicht antwortet. */
    private function unerreichbareStadt(): StadtratsbeschlussService
    {
        $client = $this->createStub(IClient::class);
        $client->method('get')->willThrowException(new \RuntimeException('Connection refused'));
        $clientService = $this->createStub(IClientService::class);
        $clientService->method('newClient')->willReturn($client);
        return new StadtratsbeschlussService(
            $this->createStub(StadtratsbeschlussMapper::class),
            new ScraperService($clientService, $this->createStub(LoggerInterface::class)),
            $this->createStub(LoggerInterface::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(NotizService::class),
        );
    }

    private function angemeldet(string $uid): IUserSession
    {
        $benutzer = $this->createStub(IUser::class);
        $benutzer->method('getUID')->willReturn($uid);
        $sitzung = $this->createStub(IUserSession::class);
        $sitzung->method('getUser')->willReturn($benutzer);
        return $sitzung;
    }

    /**
     * Ein Controller mit dem echten `SyncCommand`. Konfiguration, Sperre,
     * Echtzeit und Protokoll teilen beide.
     */
    private function controller(
        ?StadtratsbeschlussService $beschluesse = null,
        ?FraktionsarbeitService $fraktionsarbeit = null,
        ?MitgliedService $mitglieder = null,
        ?GeschaeftService $geschaefte = null,
    ): SettingsController {
        $config = $this->konfiguration();
        $sperre = new SyncLockService($this->lockFile);
        $realtime = $this->createStub(RealtimePublisherService::class);
        $realtime->method('publish')->willReturnCallback(
            function (string $typ, array $nutzlast = []): void {
                $this->ereignisse[$typ] = $nutzlast;
            }
        );
        $protokoll = $this->createStub(EreignisService::class);
        $protokoll->method('protokolliere')->willReturnCallback(
            function (...$argumente): Ereignis {
                $this->protokoll[] = $argumente;
                return new Ereignis();
            }
        );
        if ($mitglieder === null) {
            $mitglieder = $this->createStub(MitgliedService::class);
            $mitglieder->method('synchronisieren')->willReturn(['mitglieder' => ['neu' => 0, 'aktualisiert' => 0, 'inaktiv' => 0]]);
        }
        if ($geschaefte === null) {
            $geschaefte = $this->createStub(GeschaeftService::class);
            $geschaefte->method('synchronisieren')->willReturn(['neu' => 0, 'aktualisiert' => 0, 'geloescht' => 0]);
        }
        $sitzungen = $this->createStub(SitzungService::class);
        $sitzungen->method('synchronisieren')->willReturn(['neu' => 0, 'aktualisiert' => 0, 'geloescht' => 0]);
        $parlament = $this->createStub(ScraperService::class);
        $parlament->method('vorabTotalsFuerSync')->willReturn([]);
        if ($fraktionsarbeit === null) {
            $fraktionsarbeit = $this->createStub(FraktionsarbeitService::class);
            $fraktionsarbeit->method('autoZuweisenKommissionsmitglieder')->willReturn([
                'gepruet' => 0, 'zugewiesen' => 0, 'uebersprungen' => 0, 'ohne_kommission' => 0, 'ohne_passendes_mitglied' => 0,
            ]);
        }
        if ($beschluesse === null) {
            $beschluesse = $this->createStub(StadtratsbeschlussService::class);
            $beschluesse->method('synchronisierenMitFehlerInStatistik')->willReturn(
                ['neu' => 0, 'aktualisiert' => 0, 'gelesen' => 0, 'fehler' => 0, 'fehlermeldung' => '']
            );
        }

        $command = new SyncCommand(
            $geschaefte,
            $sitzungen,
            $mitglieder,
            $beschluesse,
            $this->createStub(KalenderService::class),
            $realtime,
            $parlament,
            $sperre,
            $fraktionsarbeit,
            $config,
            $protokoll,
        );
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturn(null);

        return new SettingsController(
            $request,
            $config,
            $mitglieder,
            $fraktionsarbeit,
            $realtime,
            $sperre,
            $this->createStub(SyncProcessService::class),
            $this->createStub(FraktionMapper::class),
            $this->createStub(IGroupManager::class),
            $this->createStub(IUserManager::class),
            $this->createStub(FraktionsraumService::class),
            $command,
            $this->angemeldet('anna'),
        );
    }

    private function ausweichweg(SettingsController $controller): void
    {
        (new \ReflectionMethod(SettingsController::class, 'fuehreSynchronisationAus'))->invoke($controller);
    }

    /**
     * Antwortet die Webseite der Stadt nicht, läuft auch der Ausweichweg zu
     * Ende: Die Zuständigkeit wird gesetzt, `sync.completed` kommt, und die
     * Meldung mit der Adresse steht in der Statistik und nach dem Abschluss
     * noch im Stand.
     */
    public function testEineUnerreichbareStadtquelleBeendetDenAusweichwegNicht(): void
    {
        $fraktionsarbeit = $this->createMock(FraktionsarbeitService::class);
        $fraktionsarbeit->expects(self::once())->method('autoZuweisenKommissionsmitglieder')->willReturn([
            'gepruet' => 0, 'zugewiesen' => 0, 'uebersprungen' => 0, 'ohne_kommission' => 0, 'ohne_passendes_mitglied' => 0,
        ]);

        $this->ausweichweg($this->controller($this->unerreichbareStadt(), $fraktionsarbeit));

        self::assertArrayNotHasKey('sync.failed', $this->ereignisse, (string) ($this->ereignisse['sync.failed']['fehler'] ?? ''));
        self::assertArrayHasKey('sync.completed', $this->ereignisse);
        $statistik = $this->ereignisse['sync.completed']['statistik']['stadtratsbeschluesse'];
        self::assertTrue($statistik['ausgefallen']);
        self::assertStringContainsString('https://stadt.winterthur.ch/stadtratsbeschluesse', $statistik['fehlermeldung']);
        self::assertNotSame('', $this->werte['letzte_synchronisation'] ?? '');
        $stand = json_decode($this->werte['sync_progress'] ?? '{}', true);
        self::assertSame('abgeschlossen', $stand['phase'] ?? null);
        self::assertStringContainsString(
            'https://stadt.winterthur.ch/stadtratsbeschluesse',
            (string) ($stand['sections']['stadtratsbeschluesse']['fehler'] ?? ''),
            'der Fehler des Bereichs fehlt nach dem Abschluss im Stand'
        );
    }

    /**
     * Scheitert der Ausweichweg, steht der Lauf im Protokoll, mit der Meldung
     * und dem Administrator als Auslöser, und `sync.failed` geht hinaus.
     */
    public function testEinFehlschlagImAusweichwegStehtImProtokoll(): void
    {
        $mitglieder = $this->createStub(MitgliedService::class);
        $mitglieder->method('synchronisieren')->willThrowException(new \RuntimeException('Die Mitgliederliste fehlt'));

        $this->ausweichweg($this->controller(null, null, $mitglieder));

        self::assertSame(
            [['fehler', '', false, 'Synchronisation fehlgeschlagen', 'Die Mitgliederliste fehlt', 'anna']],
            $this->protokoll
        );
        self::assertSame('Die Mitgliederliste fehlt', $this->ereignisse['sync.failed']['fehler'] ?? null);
        $stand = json_decode($this->werte['sync_progress'] ?? '{}', true);
        self::assertSame('fehler', $stand['phase'] ?? null);
    }

    /**
     * Ein Lauf, den die Verwaltung nach einem Abbruch fortsetzt, macht in jedem
     * Bereich am gespeicherten Punkt weiter.
     */
    public function testEinFortgesetzterLaufMachtAmGespeichertenPunktWeiter(): void
    {
        $this->werte['sync_progress'] = (string) json_encode([
            'running' => true,
            'phase' => 'queued',
            'sections' => [
                'geschaefte' => ['processed' => 40, 'total' => 100, 'cursor' => '2777407'],
                'sitzungen' => ['processed' => 0, 'total' => 0, 'cursor' => ''],
            ],
        ]);
        $geschaefte = $this->createMock(GeschaeftService::class);
        $geschaefte->expects(self::once())->method('synchronisieren')
            ->with(self::isCallable(), ['resume_cursor' => '2777407'])
            ->willReturn(['neu' => 0, 'aktualisiert' => 0, 'geloescht' => 0]);

        $this->ausweichweg($this->controller(null, null, null, $geschaefte));

        self::assertArrayHasKey('sync.completed', $this->ereignisse);
    }

    /**
     * F108: Der Aufruf von `occ parlwin:sync`, den «Jetzt synchronisieren»
     * startet, trägt die Benutzer-ID des Administrators für das Protokoll.
     */
    public function testDerSyncProzessKenntDenAusloeser(): void
    {
        $kommando = (new \ReflectionMethod(SettingsController::class, 'syncKommando'))
            ->invoke($this->controller(), '/usr/bin/php', '/app/occ');

        self::assertSame('parlwin:sync', $kommando[4]);
        self::assertContains('--source=admin-ui', $kommando);
        self::assertContains('--ausgeloest-von=anna', $kommando);
    }

    /**
     * Der Fehler, den die Synchronisation in den Bereich der
     * Stadtratsbeschlüsse schreibt, steht auch in der Antwort von
     * `GET /sync/status`.
     */
    public function testDerFehlerDesBereichsErreichtDenStatus(): void
    {
        $this->werte['sync_progress'] = (string) json_encode([
            'running' => false,
            'phase' => 'abgeschlossen',
            'sections' => [
                'stadtratsbeschluesse' => [
                    'processed' => 0,
                    'total' => 0,
                    'cursor' => '',
                    'fehler' => 'Die Liste der Stadtratsbeschlüsse unter https://stadt.winterthur.ch/stadtratsbeschluesse ist nicht erreichbar (HTTP 500).',
                ],
            ],
        ]);

        $status = $this->controller()->syncStatus()->getData();

        self::assertStringContainsString(
            'https://stadt.winterthur.ch/stadtratsbeschluesse',
            (string) ($status['sections']['stadtratsbeschluesse']['fehler'] ?? '')
        );
        self::assertArrayNotHasKey('fehler', $status['sections']['geschaefte']);
    }
}
