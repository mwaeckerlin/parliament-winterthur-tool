<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Controller;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Command\SyncCommand;
use OCA\ParliamentWinterthur\Db\FraktionMapper;
use OCA\ParliamentWinterthur\Db\Mitglied;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCA\ParliamentWinterthur\Service\SyncLockService;
use OCA\ParliamentWinterthur\Service\SyncProcessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;

/**
 * REST-Controller für Einstellungen und manuelle Synchronisation.
 */
class SettingsController extends Controller
{
    private const SYNC_PROGRESS_KEY = 'sync_progress';
    private const SYNC_CANCEL_REQUESTED_KEY = SyncCommand::SYNC_CANCEL_REQUESTED_KEY;
    private const SYNC_WORKER_PID_KEY = SyncCommand::SYNC_WORKER_PID_KEY;
    private const SYNC_STALE_SECONDS = 900;
    private const SYNC_ABORT_SIGNAL = '__parlwin_sync_abort__';
    private const SYNC_QUEUE_GRACE_SECONDS = 10;
    private const SYNC_CANCEL_GRACE_MS = 900;
    private const SYNC_CANCEL_TERM_WAIT_MS = 250;
    private const SYNC_CANCEL_KILL_WAIT_MS = 450;
    private const SYNC_CANCEL_STEP_MS = 50;
    private string $letzterCreateUserFehler = '';
    private const SYNC_SECTION_META = [
        'mitglieder' => ['label' => 'Mitglieder', 'db' => 'pw_mitglieder'],
        'fraktionen' => ['label' => 'Fraktionen', 'db' => 'pw_fraktionen'],
        'kommissionen' => ['label' => 'Kommissionen', 'db' => 'pw_kommissionen'],
        'geschaefte' => ['label' => 'Geschäfte', 'db' => 'pw_geschaefte (+ pw_geschaeft_ereignisse)'],
        'sitzungen' => ['label' => 'Sitzungen', 'db' => 'pw_sitzungen (+ pw_traktanden)'],
    ];

    /** Erlaubte Einstellungsschlüssel und ihre Standardwerte */
    private const EINSTELLUNGEN = [
        'fraktion' => '',
        'nextcloud_gruppe' => '',
        'kalender_nutzer' => '',
        'absender_email' => '',
        'absender_name' => 'Parlament Winterthur Tool',
    ];

    public function __construct(
        IRequest $request,
        private readonly IConfig $config,
        private readonly GeschaeftService $geschaeftService,
        private readonly SitzungService $sitzungService,
        private readonly MitgliedService $mitgliedService,
        private readonly ScraperService $scraperService,
        private readonly KalenderService $kalenderService,
        private readonly FraktionsarbeitService $fraktionsarbeitService,
        private readonly RealtimePublisherService $realtimePublisher,
        private readonly SyncLockService $syncLockService,
        private readonly SyncProcessService $syncProcessService,
        private readonly FraktionMapper $fraktionMapper,
        private readonly IGroupManager $groupManager,
        private readonly IUserManager $userManager,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Gibt alle Einstellungen zurück.
     */
    #[AuthorizedAdminSetting(settings: \OCA\ParliamentWinterthur\Settings\AdminSettings::class)]
    public function get(): DataResponse
    {
        $daten = [];
        foreach (self::EINSTELLUNGEN as $schluessel => $standard) {
            $daten[$schluessel] = $this->config->getAppValue(Application::APP_ID, $schluessel, $standard);
        }
        $daten['letzte_synchronisation'] = $this->config->getAppValue(Application::APP_ID, 'letzte_synchronisation', '');
        $daten['fraktionssitzung'] = $this->fraktionsarbeitService->fraktionssitzungKontext();
        $daten['optionen'] = [
            'fraktionen' => $this->fraktionsOptionen(),
        ];
        return new DataResponse($daten);
    }

    /**
     * Speichert Einstellungen.
     */
    #[AuthorizedAdminSetting(settings: \OCA\ParliamentWinterthur\Settings\AdminSettings::class)]
    public function set(): DataResponse
    {
        $fraktionsOptionen = $this->fraktionsOptionen();
        $sentinel = new \stdClass();
        $zuSpeichern = [];
        foreach (self::EINSTELLUNGEN as $schluessel => $standard) {
            $raw = $this->request->getParam($schluessel, $sentinel);
            if ($raw === $sentinel) {
                continue;
            }
            $wert = trim((string) $raw);
            if ($schluessel === 'fraktion' && $wert !== '' && !$this->istGueltigeFraktion($wert, $fraktionsOptionen)) {
                return new DataResponse([
                    'fehler' => 'Bitte eine vorhandene Fraktion aus der Liste wählen.',
                ], Http::STATUS_BAD_REQUEST);
            }
            if ($schluessel === 'kalender_nutzer' && $wert !== '' && $this->userManager->get($wert) === null) {
                return new DataResponse([
                    'fehler' => 'Der gewählte Kalender-Benutzer existiert nicht.',
                ], Http::STATUS_BAD_REQUEST);
            }
            $zuSpeichern[$schluessel] = $wert;
        }
        foreach ($zuSpeichern as $schluessel => $wert) {
            $this->config->setAppValue(Application::APP_ID, $schluessel, (string) $wert);
        }
        $this->realtimePublisher->publish('settings.updated');
        return $this->get();
    }

    /**
     * Liefert Mitglieder der gewählten Fraktion inkl. Mapping auf lokale User.
     */
    #[AuthorizedAdminSetting(settings: \OCA\ParliamentWinterthur\Settings\AdminSettings::class)]
    public function fraktionMitglieder(): DataResponse
    {
        $fraktion = trim((string) $this->request->getParam(
            'fraktion',
            $this->config->getAppValue(Application::APP_ID, 'fraktion', '')
        ));

        if ($fraktion === '') {
            return new DataResponse([
                'fraktion' => '',
                'mitglieder' => [],
                'summary' => [
                    'anzahl' => 0,
                    'mitLokalemUser' => 0,
                ],
            ]);
        }

        return new DataResponse($this->baueFraktionsMitgliederPayload($fraktion));
    }

    /**
     * Speichert Username-Mappings für Fraktionsmitglieder.
     */
    #[AuthorizedAdminSetting(settings: \OCA\ParliamentWinterthur\Settings\AdminSettings::class)]
    public function saveFraktionMitgliederMapping(): DataResponse
    {
        $fraktion = trim((string) $this->request->getParam('fraktion', ''));
        if ($fraktion === '') {
            return new DataResponse(['fehler' => 'Fraktion ist erforderlich.'], Http::STATUS_BAD_REQUEST);
        }

        $mappings = $this->leseMappingsAusRequest();
        $aktualisiert = 0;
        $warnungen = [];

        foreach ($mappings as $mapping) {
            $mitgliedId = (int) $mapping['mitgliedId'];
            $rohUsername = (string) $mapping['username'];
            try {
                $mitglied = $this->mitgliedService->eins($mitgliedId);
            } catch (DoesNotExistException) {
                $warnungen[] = sprintf('Mitglied mit ID %d wurde nicht gefunden.', $mitgliedId);
                continue;
            }

            if (!$this->gehoertMitgliedZuFraktion($mitglied, $fraktion)) {
                continue;
            }

            $username = $this->normalisiereUsername($rohUsername, $mitglied);
            $this->mitgliedService->setzeNextcloudUid($mitgliedId, $username);
            $aktualisiert++;
        }

        $payload = $this->baueFraktionsMitgliederPayload($fraktion);
        $payload['aktualisiert'] = $aktualisiert;
        $payload['warnungen'] = $warnungen;
        return new DataResponse($payload);
    }

    /**
     * Legt ausgewählte Fraktionsmitglieder als Nextcloud-User an (falls nötig)
     * und weist sie der gewählten Nextcloud-Gruppe zu.
     */
    #[AuthorizedAdminSetting(settings: \OCA\ParliamentWinterthur\Settings\AdminSettings::class)]
    public function provisionFraktionMitglieder(): DataResponse
    {
        $fraktion = trim((string) $this->request->getParam('fraktion', ''));
        $gruppeName = trim((string) $this->request->getParam(
            'nextcloud_gruppe',
            $this->config->getAppValue(Application::APP_ID, 'nextcloud_gruppe', '')
        ));

        if ($fraktion === '') {
            return new DataResponse(['fehler' => 'Fraktion ist erforderlich.'], Http::STATUS_BAD_REQUEST);
        }
        if ($gruppeName === '') {
            return new DataResponse(['fehler' => 'Nextcloud-Gruppe ist erforderlich.'], Http::STATUS_BAD_REQUEST);
        }

        $auswahlIds = $this->leseAuswahlIdsAusRequest();
        if ($auswahlIds === []) {
            return new DataResponse(['fehler' => 'Bitte mindestens ein Mitglied auswählen.'], Http::STATUS_BAD_REQUEST);
        }

        $mappingsById = [];
        foreach ($this->leseMappingsAusRequest() as $mapping) {
            $mappingsById[(int) $mapping['mitgliedId']] = (string) $mapping['username'];
        }

        $gruppe = $this->ladeOderErzeugeGruppe($gruppeName);
        if ($gruppe === null) {
            return new DataResponse(['fehler' => 'Nextcloud-Gruppe konnte nicht geladen werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        $statistik = [
            'ausgewaehlt' => count($auswahlIds),
            'angelegt' => 0,
            'zurGruppeHinzugefuegt' => 0,
            'bereitsVorhanden' => 0,
            'warnungen' => [],
        ];

        foreach ($auswahlIds as $mitgliedId) {
            try {
                $mitglied = $this->mitgliedService->eins($mitgliedId);
            } catch (DoesNotExistException) {
                $statistik['warnungen'][] = sprintf('Mitglied mit ID %d wurde nicht gefunden.', $mitgliedId);
                continue;
            }

            if (!$this->gehoertMitgliedZuFraktion($mitglied, $fraktion)) {
                $statistik['warnungen'][] = sprintf(
                    'Mitglied "%s" (ID %d) gehört nicht zur Fraktion "%s" und wurde übersprungen.',
                    trim((string) $mitglied->getName()) !== '' ? $mitglied->getName() : '?',
                    $mitgliedId,
                    $fraktion
                );
                continue;
            }

            $username = $this->normalisiereUsername((string) ($mappingsById[$mitgliedId] ?? ''), $mitglied);
            $this->mitgliedService->setzeNextcloudUid($mitgliedId, $username);

            $user = $this->userManager->get($username);
            if ($user === null) {
                $this->letzterCreateUserFehler = '';
                $user = $this->erstelleNextcloudUser($username, $mitglied);
                if ($user === null) {
                    $statistik['warnungen'][] = sprintf(
                        'User "%s" konnte nicht angelegt werden%s.',
                        $username,
                        $this->letzterCreateUserFehler !== ''
                            ? ': ' . $this->letzterCreateUserFehler
                            : ''
                    );
                    continue;
                }
                $statistik['angelegt']++;
            } else {
                $statistik['bereitsVorhanden']++;
            }

            if ($this->fuegeUserZuGruppeHinzu($gruppe, $user)) {
                $statistik['zurGruppeHinzugefuegt']++;
            }
        }

        $payload = $this->baueFraktionsMitgliederPayload($fraktion);
        $payload['provision'] = $statistik;

        $this->realtimePublisher->publish('settings.members.provisioned', [
            'fraktion' => $fraktion,
            'gruppe' => $gruppeName,
            'anzahl' => $statistik['ausgewaehlt'],
        ]);

        return new DataResponse($payload);
    }

    /**
     * Löst eine manuelle Synchronisation aus.
     * Nur für Administratoren.
     */
    #[AuthorizedAdminSetting(settings: \OCA\ParliamentWinterthur\Settings\AdminSettings::class)]
    public function run(): DataResponse
    {
        $vorherigerStatus = $this->getSyncProgress();
        $warStaleAbbruch = false;

        if ($this->syncLockService->isLocked() || $this->istWorkerProzessLebendig()) {
            return new DataResponse([
                'erfolg' => true,
                'asynchron' => true,
                'bereits_laufend' => true,
                'zeitpunkt' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], Http::STATUS_ACCEPTED);
        }

        if (($vorherigerStatus['running'] ?? false) === true) {
            $phase = (string) ($vorherigerStatus['phase'] ?? '');
            $syncAktiv = $this->syncLockService->isLocked() || $this->istWorkerProzessLebendig();
            if (!$syncAktiv) {
                if ($phase === 'queued' && $this->istQueueStartFrisch($vorherigerStatus)) {
                    return new DataResponse([
                        'erfolg' => true,
                        'asynchron' => true,
                        'bereits_laufend' => true,
                        'zeitpunkt' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ], Http::STATUS_ACCEPTED);
                }
                $vorherigerStatus = $this->markiereSyncAlsAbgebrochen($vorherigerStatus, 'Synchronisationsprozess nicht mehr aktiv');
                $warStaleAbbruch = true;
            } elseif (!$this->istSyncStatusVeraltet($vorherigerStatus)) {
                return new DataResponse([
                    'erfolg' => true,
                    'asynchron' => true,
                    'bereits_laufend' => true,
                    'zeitpunkt' => (new \DateTime())->format('Y-m-d H:i:s'),
                ], Http::STATUS_ACCEPTED);
            } else {
                $vorherigerStatus = $this->markiereSyncAlsAbgebrochen($vorherigerStatus, 'Vorheriger Lauf ohne Heartbeat beendet');
                $warStaleAbbruch = true;
            }
        }

        $resumeCursors = [];
        $phase = (string) ($vorherigerStatus['phase'] ?? '');
        if ($warStaleAbbruch || $phase === 'fehler' || $phase === 'abgebrochen') {
            $resumeCursors = $this->ermittleResumeCursors($vorherigerStatus);
        }
        $hatResume = false;
        foreach ($resumeCursors as $cursor) {
            if ($cursor !== '') {
                $hatResume = true;
                break;
            }
        }

        $jetztIso = (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->setCancelRequested(false);
        $status = [
            'running' => true,
            'phase' => 'queued',
            'phaseLabel' => $hatResume ? 'Synchronisation wird fortgesetzt' : 'Synchronisation gestartet',
            'startedAt' => $jetztIso,
            'finishedAt' => null,
            'updatedAt' => $jetztIso,
            'elapsed' => '00:00:00',
            'eta' => '--:--:--',
            'error' => null,
            'source' => 'admin-ui',
            'current' => [
                'scope' => 'queued',
                'label' => 'Wartet auf Worker',
                'db' => '-',
                'processed' => 0,
                'total' => 0,
                'cursor' => '',
            ],
            'sections' => $this->initialisiereSections($resumeCursors),
        ];
        $this->setSyncProgress($status);

        try {
            if (!$this->starteSyncImHintergrund()) {
                throw new \RuntimeException('Konnte Sync-Worker nicht starten');
            }
            return new DataResponse([
                'erfolg' => true,
                'asynchron' => true,
                'zeitpunkt' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], Http::STATUS_ACCEPTED);
        } catch (\Throwable $e) {
            $status['running'] = false;
            $status['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            $status['updatedAt'] = $status['finishedAt'];
            $status['error'] = 'Synchronisation fehlgeschlagen: ' . $e->getMessage();
            $status['elapsed'] = '00:00:00';
            $status['phase'] = 'fehler';
            $status['phaseLabel'] = 'Fehler';
            $status['current'] = [
                'scope' => 'fehler',
                'label' => 'Fehler',
                'db' => '-',
                'processed' => 0,
                'total' => 0,
                'cursor' => '',
            ];
            $status['eta'] = '--:--:--';
            $this->setSyncProgress($status);

            return new DataResponse(
                ['fehler' => 'Synchronisation fehlgeschlagen: ' . $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Fordert den Abbruch einer laufenden Synchronisation an.
     */
    #[AuthorizedAdminSetting(settings: \OCA\ParliamentWinterthur\Settings\AdminSettings::class)]
    public function cancelSync(): DataResponse
    {
        $status = $this->getSyncProgress();
        $laeuft = ($status['running'] ?? false) === true
            || $this->syncLockService->isLocked()
            || $this->istWorkerProzessLebendig();

        if (!$laeuft) {
            $this->setCancelRequested(false);
            $this->setCurrentWorkerPid(null);
            return new DataResponse([
                'erfolg' => true,
                'bereits_beendet' => true,
                'zeitpunkt' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        }

        $this->setCancelRequested(true);
        $status['running'] = true;
        $status['phase'] = 'abbruch_angefragt';
        $status['phaseLabel'] = 'Abbruch angefragt';
        $status['updatedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $status['error'] = null;
        if (!isset($status['current']) || !is_array($status['current'])) {
            $status['current'] = [
                'scope' => 'running',
                'label' => 'Synchronisation läuft',
                'db' => '-',
                'processed' => 0,
                'total' => 0,
                'cursor' => '',
            ];
        }
        $this->setSyncProgress($status);
        $this->realtimePublisher->publish('sync.cancel.requested', [
            'quelle' => 'admin-ui',
            'zeitpunkt' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        $stopResult = $this->syncProcessService->ensureStopped(
            $this->getCurrentWorkerPid(),
            fn(): bool => $this->syncLockService->isLocked(),
            self::SYNC_CANCEL_GRACE_MS,
            self::SYNC_CANCEL_TERM_WAIT_MS,
            self::SYNC_CANCEL_KILL_WAIT_MS,
            self::SYNC_CANCEL_STEP_MS,
        );

        if (($stopResult['stopped'] ?? false) === true) {
            $this->setCancelRequested(false);
            $this->setCurrentWorkerPid(null);
            $status = $this->markiereSyncAlsAbgebrochen(
                $status,
                (($stopResult['forced'] ?? false) === true)
                ? 'Synchronisation wurde manuell abgebrochen (hart beendet)'
                : 'Synchronisation wurde manuell abgebrochen'
            );
            $this->realtimePublisher->publish('sync.cancelled', [
                'quelle' => 'admin-ui',
                'zeitpunkt' => (new \DateTime())->format('Y-m-d H:i:s'),
                'forced' => (bool) ($stopResult['forced'] ?? false),
                'signalled' => (bool) ($stopResult['signalled'] ?? false),
            ]);

            return new DataResponse([
                'erfolg' => true,
                'abgebrochen' => true,
                'forced' => (bool) ($stopResult['forced'] ?? false),
                'signalled' => (bool) ($stopResult['signalled'] ?? false),
                'zeitpunkt' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
        }

        return new DataResponse([
            'erfolg' => true,
            'abbruch_angefragt' => true,
            'zeitpunkt' => (new \DateTime())->format('Y-m-d H:i:s'),
        ], Http::STATUS_ACCEPTED);
    }

    /**
     * Liefert den aktuellen Fortschritt des manuellen Synchronisationslaufs.
     */
    #[AuthorizedAdminSetting(settings: \OCA\ParliamentWinterthur\Settings\AdminSettings::class)]
    public function syncStatus(): DataResponse
    {
        $status = $this->getSyncProgress();
        $lockAktiv = $this->syncLockService->isLocked();
        $workerLebt = $this->istWorkerProzessLebendig();
        $syncAktiv = $lockAktiv || $workerLebt;

        if (($status['running'] ?? false) !== true && $syncAktiv) {
            $status['running'] = true;
            $status['phase'] = 'running';
            $status['phaseLabel'] = 'Synchronisation läuft';
            $status['source'] = (string) ($status['source'] ?? 'unbekannt');
            $status['current'] = [
                'scope' => 'running',
                'label' => 'Synchronisation läuft',
                'db' => '-',
                'processed' => 0,
                'total' => 0,
                'cursor' => '',
            ];
            $status['updatedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        }
        if (($status['running'] ?? false) === true) {
            $phase = (string) ($status['phase'] ?? '');
            if (!$syncAktiv) {
                if (
                    $phase === 'queued'
                    && $this->istQueueStartFrisch($status)
                ) {
                    // Worker kann kurz nach Start noch nicht sichtbar sein.
                } else {
                    $status = $this->markiereSyncAlsAbgebrochen(
                        $status,
                        $phase === 'abbruch_angefragt'
                        ? 'Synchronisation wurde manuell abgebrochen'
                        : 'Synchronisationsprozess nicht mehr aktiv'
                    );
                    $this->setCancelRequested(false);
                    $this->setCurrentWorkerPid(null);
                }
            } elseif ($this->istSyncStatusVeraltet($status)) {
                $status = $this->markiereSyncAlsAbgebrochen($status, 'Kein Heartbeat innerhalb des zulässigen Zeitfensters');
                $this->setCancelRequested(false);
                $this->setCurrentWorkerPid(null);
            }
            $startedAt = \DateTimeImmutable::createFromFormat(DATE_ATOM, (string) ($status['startedAt'] ?? ''));
            if ($startedAt instanceof \DateTimeImmutable) {
                $status['elapsed'] = $this->formatiereDauer(max(0, time() - $startedAt->getTimestamp()));
            }
        }
        return new DataResponse($status);
    }

    /**
     * Aktiviert/deaktiviert den Fraktionssitzungsmodus.
     *
     * Zugriff: Fraktionspräsidium (inkl. aktiver Stellvertretung).
     */
    #[NoAdminRequired]
    public function setFraktionssitzung(): DataResponse
    {
        $aktiv = (bool) $this->request->getParam('aktiv', false);

        try {
            $this->fraktionsarbeitService->setzeFraktionssitzungModus($aktiv);
            $kontext = $this->fraktionsarbeitService->fraktionssitzungKontext();
            $this->realtimePublisher->publish('fraktionssitzung.updated', $kontext);
            return new DataResponse($kontext);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Liefert den aktuellen Fraktionssitzungs-Kontext (lesend).
     */
    #[NoAdminRequired]
    public function getFraktionssitzung(): DataResponse
    {
        return new DataResponse($this->fraktionsarbeitService->fraktionssitzungKontext());
    }

    /**
     * Setzt den Protokollführer im Fraktionssitzungsmodus.
     *
     * Zugriff: Fraktionspräsidium (inkl. aktiver Stellvertretung).
     */
    #[NoAdminRequired]
    public function setProtokollfuehrer(): DataResponse
    {
        $uid = trim((string) $this->request->getParam('uid', ''));
        $name = trim((string) $this->request->getParam('name', ''));
        if ($uid === '') {
            return new DataResponse(['fehler' => 'uid ist erforderlich'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $this->fraktionsarbeitService->setzeProtokollfuehrer($uid, $name);
            $kontext = $this->fraktionsarbeitService->fraktionssitzungKontext();
            $this->realtimePublisher->publish('fraktion.roles.updated', $kontext);
            return new DataResponse($kontext);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Setzt den Fraktionspräsidenten.
     *
     * Zugriff: Fraktions-Gruppen-Admin.
     */
    #[NoAdminRequired]
    public function setFraktionspraesident(): DataResponse
    {
        $uid = trim((string) $this->request->getParam('uid', ''));
        $name = trim((string) $this->request->getParam('name', ''));
        if ($uid === '') {
            return new DataResponse(['fehler' => 'uid ist erforderlich'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $this->fraktionsarbeitService->setzeFraktionspraesident($uid, $name);
            $kontext = $this->fraktionsarbeitService->fraktionssitzungKontext();
            $this->realtimePublisher->publish('fraktion.roles.updated', $kontext);
            return new DataResponse($kontext);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Ergänzt eine zeitlich befristete Stellvertretung des Fraktionspräsidiums.
     *
     * Zugriff: Fraktionspräsidium (inkl. aktiver Stellvertretung).
     */
    #[NoAdminRequired]
    public function addPraesidiumStellvertretung(): DataResponse
    {
        $uid = trim((string) $this->request->getParam('uid', ''));
        $name = trim((string) $this->request->getParam('name', ''));
        $von = trim((string) $this->request->getParam('gueltig_von', ''));
        $bis = trim((string) $this->request->getParam('gueltig_bis', ''));
        if ($uid === '') {
            return new DataResponse(['fehler' => 'uid ist erforderlich'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $this->fraktionsarbeitService->setzePraesidiumStellvertretung($uid, $name, $von, $bis);
            $kontext = $this->fraktionsarbeitService->fraktionssitzungKontext();
            $this->realtimePublisher->publish('fraktion.roles.updated', $kontext);
            return new DataResponse($kontext);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Ergänzt eine zeitlich befristete Stellvertretung für die Protokollführung.
     *
     * Zugriff: Protokollführung oder Fraktionspräsidium.
     */
    #[NoAdminRequired]
    public function addProtokollfuehrerStellvertretung(): DataResponse
    {
        $uid = trim((string) $this->request->getParam('uid', ''));
        $name = trim((string) $this->request->getParam('name', ''));
        $von = trim((string) $this->request->getParam('gueltig_von', ''));
        $bis = trim((string) $this->request->getParam('gueltig_bis', ''));
        if ($uid === '') {
            return new DataResponse(['fehler' => 'uid ist erforderlich'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $this->fraktionsarbeitService->setzeProtokollfuehrerStellvertretung($uid, $name, $von, $bis);
            $kontext = $this->fraktionsarbeitService->fraktionssitzungKontext();
            $this->realtimePublisher->publish('fraktion.roles.updated', $kontext);
            return new DataResponse($kontext);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * Markiert ein Fraktionsmitglied als Kommissionsmitglied (optional befristet).
     *
     * Zugriff: Fraktionspräsidium.
     */
    #[NoAdminRequired]
    public function addKommissionsmitglied(): DataResponse
    {
        $uid = trim((string) $this->request->getParam('uid', ''));
        $name = trim((string) $this->request->getParam('name', ''));
        $von = trim((string) $this->request->getParam('gueltig_von', ''));
        $bis = trim((string) $this->request->getParam('gueltig_bis', ''));
        if ($uid === '') {
            return new DataResponse(['fehler' => 'uid ist erforderlich'], Http::STATUS_BAD_REQUEST);
        }

        try {
            $this->fraktionsarbeitService->setzeKommissionsmitglied($uid, $name, $von, $bis);
            $kontext = $this->fraktionsarbeitService->fraktionssitzungKontext();
            $this->realtimePublisher->publish('fraktion.roles.updated', $kontext);
            return new DataResponse($kontext);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return new DataResponse(['fehler' => $e->getMessage()], Http::STATUS_FORBIDDEN);
        }
    }

    /**
     * @return string[]
     */
    private function fraktionsOptionen(): array
    {
        $optionen = [];
        foreach ($this->fraktionMapper->findAll() as $fraktion) {
            if ($fraktion->getAktiv() !== true) {
                continue;
            }
            $name = trim((string) $fraktion->getName());
            if ($name === '') {
                continue;
            }
            $optionen[] = $name;
        }
        $optionen = array_values(array_unique($optionen));
        natcasesort($optionen);
        return array_values($optionen);
    }

    /**
     * @param string[] $optionen
     */
    private function istGueltigeFraktion(string $fraktion, array $optionen): bool
    {
        foreach ($optionen as $option) {
            if (strcasecmp($option, $fraktion) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array{fraktion: string, mitglieder: array<int, array<string, mixed>>, verwaiste: array<int, array<string, mixed>>, summary: array{anzahl: int, mitLokalemUser: int, verwaiste: int}}
     */
    private function baueFraktionsMitgliederPayload(string $fraktion): array
    {
        $gruppeName = trim($this->config->getAppValue(Application::APP_ID, 'nextcloud_gruppe', ''));
        $mitglieder = $this->mitgliedService->aktiveDerFraktion($fraktion);
        $eintraege = [];
        $mitLokalemUser = 0;
        $matchedUids = [];

        foreach ($mitglieder as $mitglied) {
            $eintrag = $this->baueFraktionsMitgliedEintrag($mitglied);
            if (($eintrag['lokalerUserExistiert'] ?? false) === true) {
                $mitLokalemUser++;
                $matchedUids[strtolower((string) $eintrag['lokaleUid'])] = true;
            }
            $eintraege[] = $eintrag;
        }

        $verwaiste = $gruppeName !== ''
            ? $this->findeVerwaisteGruppenmitglieder($gruppeName, $matchedUids)
            : [];

        return [
            'fraktion' => $fraktion,
            'mitglieder' => $eintraege,
            'verwaiste' => $verwaiste,
            'summary' => [
                'anzahl' => count($eintraege),
                'mitLokalemUser' => $mitLokalemUser,
                'verwaiste' => count($verwaiste),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baueFraktionsMitgliedEintrag(Mitglied $mitglied): array
    {
        $vorschlag = $this->vorschlagUsernameFuerMitglied($mitglied);
        $gespeichert = trim((string) $mitglied->getNextcloudUid());
        $username = $gespeichert !== '' ? $this->normalisiereUsername($gespeichert, $mitglied) : $vorschlag;

        $match = $this->findeLokalenUserFuerMitglied($mitglied, $username);
        $user = $match['user'] ?? null;
        $gruppen = $user instanceof IUser ? $this->gruppenIdsFuerUser($user) : [];

        $effektiverUsername = $user instanceof IUser ? $user->getUID() : $username;

        return [
            'id' => $mitglied->getId(),
            'externId' => $mitglied->getExternId(),
            'vorname' => $mitglied->getVorname(),
            'name' => $mitglied->getName(),
            'displayName' => $mitglied->getVollerName(),
            'email' => $mitglied->getEmail(),
            'username' => $effektiverUsername,
            'vorschlagUsername' => $vorschlag,
            'lokalerUserExistiert' => $user instanceof IUser,
            'lokaleUid' => $user instanceof IUser ? $user->getUID() : '',
            'lokaleMatchStrategie' => $match['strategie'] ?? null,
            'lokaleDisplayName' => $user instanceof IUser ? (string) $user->getDisplayName() : '',
            'lokaleEmail' => $user instanceof IUser ? (string) $user->getEMailAddress() : '',
            'lokalerUserAktiv' => $user instanceof IUser ? $user->isEnabled() : false,
            'lokaleGruppen' => $gruppen,
        ];
    }

    /**
     * Sucht einen passenden lokalen Nextcloud-User für ein Mitglied via drei Strategien:
     * 1. Gespeicherte/normalisierte UID stimmt überein.
     * 2. E-Mail-Adresse stimmt überein.
     * 3. Anzeigename stimmt überein.
     *
     * @return array{user: IUser|null, strategie: ?string}
     */
    private function findeLokalenUserFuerMitglied(Mitglied $mitglied, string $username): array
    {
        if ($username !== '') {
            $user = $this->userManager->get($username);
            if ($user instanceof IUser) {
                return ['user' => $user, 'strategie' => 'uid'];
            }
        }

        $email = trim((string) $mitglied->getEmail());
        if ($email !== '' && method_exists($this->userManager, 'getByEmail')) {
            try {
                $treffer = $this->userManager->getByEmail($email);
                if (is_array($treffer)) {
                    foreach ($treffer as $user) {
                        if ($user instanceof IUser) {
                            return ['user' => $user, 'strategie' => 'email'];
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        $displayName = trim((string) $mitglied->getVollerName());
        if ($displayName !== '' && method_exists($this->userManager, 'searchDisplayName')) {
            try {
                $treffer = $this->userManager->searchDisplayName($displayName);
                if (is_array($treffer)) {
                    $needle = mb_strtolower($displayName, 'UTF-8');
                    foreach ($treffer as $user) {
                        if (!$user instanceof IUser) {
                            continue;
                        }
                        $kandidat = mb_strtolower(trim((string) $user->getDisplayName()), 'UTF-8');
                        if ($kandidat === $needle) {
                            return ['user' => $user, 'strategie' => 'displayName'];
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        return ['user' => null, 'strategie' => null];
    }

    /**
     * Lokale User in der konfigurierten Fraktions-Gruppe, denen kein Mitglied
     * der aktuellen Fraktion zugeordnet ist. Solche User sollen beim Abgleich
     * deaktiviert und aus der Gruppe entfernt werden.
     *
     * @param array<string, true> $matchedUidsLower
     * @return array<int, array<string, mixed>>
     */
    private function findeVerwaisteGruppenmitglieder(string $gruppeName, array $matchedUidsLower): array
    {
        $gruppe = $this->groupManager->get($gruppeName);
        if (!is_object($gruppe) || !method_exists($gruppe, 'getUsers')) {
            return [];
        }
        try {
            $users = $gruppe->getUsers();
        } catch (\Throwable) {
            return [];
        }
        if (!is_array($users)) {
            return [];
        }

        $verwaiste = [];
        foreach ($users as $user) {
            if (!$user instanceof IUser) {
                continue;
            }
            $uid = $user->getUID();
            if (isset($matchedUidsLower[strtolower($uid)])) {
                continue;
            }
            $verwaiste[] = [
                'uid' => $uid,
                'displayName' => (string) $user->getDisplayName(),
                'email' => (string) $user->getEMailAddress(),
                'aktiv' => $user->isEnabled(),
            ];
        }
        return $verwaiste;
    }

    /**
     * @return array<int, array{mitgliedId: int, username: string}>
     */
    private function leseMappingsAusRequest(): array
    {
        $raw = $this->request->getParam('mappings', []);
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }
            $mitgliedId = (int) ($mapping['mitgliedId'] ?? $mapping['id'] ?? 0);
            if ($mitgliedId <= 0) {
                continue;
            }
            $result[] = [
                'mitgliedId' => $mitgliedId,
                'username' => trim((string) ($mapping['username'] ?? '')),
            ];
        }
        return $result;
    }

    /**
     * @return int[]
     */
    private function leseAuswahlIdsAusRequest(): array
    {
        $raw = $this->request->getParam('mitglied_ids', []);
        if (!is_array($raw)) {
            return [];
        }
        $result = [];
        foreach ($raw as $id) {
            $parsed = (int) $id;
            if ($parsed > 0) {
                $result[] = $parsed;
            }
        }
        return array_values(array_unique($result));
    }

    private function gehoertMitgliedZuFraktion(Mitglied $mitglied, string $fraktion): bool
    {
        return $this->mitgliedService->gehoertZurFraktion($mitglied, $fraktion);
    }

    private function vorschlagUsernameFuerMitglied(Mitglied $mitglied): string
    {
        $basis = trim($mitglied->getVorname() . '-' . $mitglied->getName());
        if ($basis === '') {
            $basis = trim($mitglied->getExternId());
        }
        if ($basis === '') {
            $basis = 'mitglied-' . (string) $mitglied->getId();
        }
        return $this->normalisiereUsername($basis, $mitglied);
    }

    private function normalisiereUsername(string $username, Mitglied $mitglied): string
    {
        $wert = trim($username);
        if ($wert === '') {
            $wert = trim($mitglied->getVorname() . '-' . $mitglied->getName());
        }
        if ($wert === '') {
            $wert = trim($mitglied->getExternId());
        }
        if ($wert === '') {
            $wert = 'mitglied-' . (string) $mitglied->getId();
        }

        $wert = function_exists('mb_strtolower') ? mb_strtolower($wert, 'UTF-8') : strtolower($wert);
        $umlautMap = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];
        $wert = strtr($wert, $umlautMap);
        $wert = (string) preg_replace('/\s+/u', '-', $wert);
        $wert = (string) preg_replace('/[^a-z0-9._-]+/', '-', $wert);
        $wert = (string) preg_replace('/-+/', '-', $wert);
        $wert = trim($wert, '-._');
        if ($wert === '') {
            $wert = 'mitglied-' . (string) $mitglied->getId();
        }
        return function_exists('mb_substr') ? mb_substr($wert, 0, 64, 'UTF-8') : substr($wert, 0, 64);
    }

    private function ladeOderErzeugeGruppe(string $gruppeName): ?object
    {
        try {
            if (!$this->groupManager->groupExists($gruppeName)) {
                $this->groupManager->createGroup($gruppeName);
            }
            $gruppe = $this->groupManager->get($gruppeName);
            return is_object($gruppe) ? $gruppe : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function erstelleNextcloudUser(string $username, Mitglied $mitglied): ?IUser
    {
        if (!method_exists($this->userManager, 'createUser')) {
            return null;
        }

        $password = $this->generiereInitialPasswort();
        try {
            /** @var mixed $created */
            $created = $this->userManager->createUser($username, $password);
        } catch (\Throwable $e) {
            $this->letzterCreateUserFehler = $e->getMessage();
            return null;
        }

        if ($created instanceof IUser) {
            $user = $created;
        } else {
            $user = $this->userManager->get($username);
        }

        if (!$user instanceof IUser) {
            return null;
        }

        $this->setzeUserProfilfelder($user, $mitglied);
        $this->sendeWillkommensMail($user);
        return $user;
    }

    /**
     * Sendet die Standard-Nextcloud-Willkommensmail mit einem Passwort-Setz-Link,
     * sodass der neue Benutzer sein eigenes Passwort vergeben kann.
     */
    private function sendeWillkommensMail(IUser $user): void
    {
        $email = trim((string) $user->getEMailAddress());
        if ($email === '') {
            return;
        }
        $helperClass = '\\OCA\\Settings\\Mailer\\NewUserMailHelper';
        if (!class_exists($helperClass)) {
            return;
        }
        try {
            /** @var object $helper */
            $helper = \OCP\Server::get($helperClass);
            $template = $helper->generateTemplate($user, true);
            $helper->sendMail($user, $template);
        } catch (\Throwable $e) {
            $this->letzterCreateUserFehler = 'Willkommensmail konnte nicht gesendet werden: ' . $e->getMessage();
        }
    }

    private function setzeUserProfilfelder(IUser $user, Mitglied $mitglied): void
    {
        $displayName = trim($mitglied->getVollerName());
        if ($displayName !== '' && method_exists($user, 'setDisplayName')) {
            try {
                $user->setDisplayName($displayName);
            } catch (\Throwable) {
            }
        }

        $email = trim($mitglied->getEmail());
        if ($email !== '') {
            if (method_exists($user, 'setEMailAddress')) {
                try {
                    $user->setEMailAddress($email);
                } catch (\Throwable) {
                }
            } elseif (method_exists($user, 'setEmailAddress')) {
                try {
                    $user->setEmailAddress($email);
                } catch (\Throwable) {
                }
            }
        }

        if (method_exists($user, 'setQuota')) {
            try {
                $user->setQuota('default');
            } catch (\Throwable) {
            }
        }

        try {
            $this->config->setUserValue($user->getUID(), 'core', 'lang', 'de');
            $this->config->setUserValue($user->getUID(), 'core', 'locale', 'de_CH');
        } catch (\Throwable) {
        }
    }

    private function generiereInitialPasswort(): string
    {
        try {
            return bin2hex(random_bytes(24));
        } catch (\Throwable) {
            return hash('sha256', (string) microtime(true) . (string) mt_rand());
        }
    }

    private function fuegeUserZuGruppeHinzu(object $gruppe, IUser $user): bool
    {
        if (!method_exists($gruppe, 'addUser')) {
            return false;
        }
        if (method_exists($gruppe, 'inGroup')) {
            try {
                if ($gruppe->inGroup($user)) {
                    return false;
                }
            } catch (\Throwable) {
            }
        }
        try {
            $gruppe->addUser($user);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return string[]
     */
    private function gruppenIdsFuerUser(IUser $user): array
    {
        $gruppen = [];
        if (method_exists($this->groupManager, 'getUserGroupIds')) {
            try {
                $raw = $this->groupManager->getUserGroupIds($user);
                if (is_array($raw)) {
                    $gruppen = $raw;
                }
            } catch (\Throwable) {
            }
        } elseif (method_exists($this->groupManager, 'getUserGroups')) {
            try {
                $raw = $this->groupManager->getUserGroups($user);
                if (is_array($raw)) {
                    foreach ($raw as $gruppe) {
                        if (is_object($gruppe) && method_exists($gruppe, 'getGID')) {
                            $gruppen[] = (string) $gruppe->getGID();
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        $gruppen = array_values(array_filter(array_map('trim', $gruppen), static fn(string $value): bool => $value !== ''));
        $gruppen = array_values(array_unique($gruppen));
        natcasesort($gruppen);
        return array_values($gruppen);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSyncProgress(): array
    {
        $raw = trim((string) $this->config->getAppValue(Application::APP_ID, self::SYNC_PROGRESS_KEY, ''));
        if ($raw === '') {
            return [
                'running' => false,
                'phase' => 'idle',
                'phaseLabel' => 'Keine laufende Synchronisation',
                'startedAt' => null,
                'finishedAt' => null,
                'updatedAt' => null,
                'elapsed' => '00:00:00',
                'eta' => '--:--:--',
                'error' => null,
                'source' => null,
                'current' => [
                    'scope' => 'idle',
                    'label' => 'Keine laufende Synchronisation',
                    'db' => '-',
                    'processed' => 0,
                    'total' => 0,
                    'cursor' => '',
                ],
                'sections' => $this->initialisiereSections(),
            ];
        }

        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            return [];
        }
        if (!isset($parsed['sections']) || !is_array($parsed['sections'])) {
            $parsed['sections'] = $this->initialisiereSections();
        } else {
            $parsed['sections'] = $this->normalisiereSections($parsed['sections']);
        }
        return $parsed;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function setSyncProgress(array $status): void
    {
        $json = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }
        $this->config->setAppValue(Application::APP_ID, self::SYNC_PROGRESS_KEY, $json);
    }

    private function starteSyncImHintergrund(): bool
    {
        // Der Sync läuft im aktuellen FPM-Worker weiter, NACHDEM die HTTP-Antwort an
        // den Browser geschickt wurde (fastcgi_finish_request). Damit erbt der Sync
        // automatisch fd1/fd2 des FPM-Workers; in Kombination mit
        // `catch_workers_output=yes` in der Pool-Config landen alle Ausgaben (stdout
        // und stderr, inkl. error_log()) im docker-logs-Stream. Ein separat per
        // proc_open gestarteter Child-Prozess würde demgegenüber stdout/stderr auf
        // /dev/null abgleiten lassen, weil PHP-FPM die Worker-Pipes nicht an Kinder
        // weiterreicht. Singleton wird über SyncLockService garantiert.
        @ignore_user_abort(true);
        @set_time_limit(0);

        try {
            register_shutdown_function(function (): void {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    @session_write_close();
                }
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                @ignore_user_abort(true);
                @set_time_limit(0);
                $pid = getmypid() ?: 0;
                error_log('[parlwin] sync-worker gestartet (FPM-Worker PID=' . $pid . ', Quelle=admin-ui)');
                try {
                    $this->fuehreSynchronisationAus();
                    error_log('[parlwin] sync-worker beendet (FPM-Worker PID=' . $pid . ')');
                } catch (\Throwable $e) {
                    error_log('[parlwin] sync-worker Fehler (PID=' . $pid . '): ' . $e->getMessage());
                }
            });
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function starteSyncUeberOccProzess(): bool
    {
        if (!$this->funktionVerfuegbar('proc_open')) {
            return false;
        }

        $occPfad = $this->ermittleOccPfad();
        if ($occPfad === '') {
            return false;
        }

        $phpBinary = $this->ermittlePhpCliBinary();
        if ($phpBinary === '') {
            return false;
        }

        // Array-Form von proc_open umgeht jede Shell (in diesem Container fehlt /bin/sh).
        // stdout/stderr werden via php://fd/{1,2} an die fd1/fd2 des FPM-Workers gebunden;
        // FPM ist mit `catch_workers_output=yes` konfiguriert, sodass die Sync-Ausgaben in
        // `docker logs` sichtbar werden. Stdin geht nach /dev/null. Sobald proc_open
        // zurückkehrt, lassen wir das Process-Handle bewusst fallen (kein proc_close),
        // damit der Sync-Prozess nach Beenden des FPM-Workers von init (PID 1) adoptiert
        // wird und im Hintergrund weiterläuft.
        $kommando = [
            $phpBinary,
            '-d',
            'max_execution_time=0',
            $occPfad,
            'parlwin:sync',
            '--update-progress',
            '--source=admin-ui',
            '--no-ansi',
            '--no-interaction',
        ];
        $stdoutResource = @fopen('php://fd/1', 'w');
        $stderrResource = @fopen('php://fd/2', 'w');
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => is_resource($stdoutResource) ? $stdoutResource : ['file', '/dev/null', 'a'],
            2 => is_resource($stderrResource) ? $stderrResource : ['file', '/dev/null', 'a'],
        ];
        $pipes = [];

        $handle = @proc_open($kommando, $descriptors, $pipes, '/tmp', null);
        if (!is_resource($handle)) {
            if (is_resource($stdoutResource)) {
                @fclose($stdoutResource);
            }
            if (is_resource($stderrResource)) {
                @fclose($stderrResource);
            }
            return false;
        }

        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }
        if (is_resource($stdoutResource)) {
            @fclose($stdoutResource);
        }
        if (is_resource($stderrResource)) {
            @fclose($stderrResource);
        }

        $status = @proc_get_status($handle);
        $pid = is_array($status) ? (int) ($status['pid'] ?? 0) : 0;
        if ($pid > 1) {
            $this->setCurrentWorkerPid($pid);
        }

        // Detach: Handle bewusst nicht via proc_close abwarten.
        unset($handle);

        return $pid > 1;
    }

    /**
     * Liefert einen schreibbaren Pfad, der auf den Container-Stdout (fd=1) bzw. -Stderr (fd=2)
     * verweist, sodass `docker logs` die Ausgaben des detached Sync-Prozesses einsammelt.
     * Wir bevorzugen /proc/1/fd/<n>; existiert das nicht, fallen wir auf /dev/stdout bzw.
     * /dev/stderr zurück und im Notfall auf /dev/null.
     */
    private function ermittleContainerLogZiel(int $fd): string
    {
        $kandidaten = [
            '/proc/1/fd/' . $fd,
            $fd === 1 ? '/dev/stdout' : '/dev/stderr',
            '/proc/self/fd/' . $fd,
        ];
        foreach ($kandidaten as $pfad) {
            if (@file_exists($pfad) && @is_writable($pfad)) {
                return $pfad;
            }
        }
        return '/dev/null';
    }

    private function ermittlePhpCliBinary(): string
    {
        // In einem PHP-FPM-Worker ist PHP_BINARY oft das FPM-Binary (z.B. /usr/sbin/php-fpm),
        // welches OCC-Argumente nicht interpretieren kann. Wir bevorzugen daher das CLI-Binary.
        $kandidaten = [];
        if (PHP_SAPI === 'cli' && PHP_BINARY !== '' && @is_executable(PHP_BINARY)) {
            $kandidaten[] = PHP_BINARY;
        }
        $kandidaten[] = '/usr/bin/php';
        $kandidaten[] = '/usr/local/bin/php';
        if (defined('PHP_BINDIR') && PHP_BINDIR !== '') {
            $kandidaten[] = rtrim(PHP_BINDIR, '/') . '/php';
        }
        // PHP_BINARY zuletzt als Fallback, falls nichts anderes verfügbar.
        if (PHP_BINARY !== '') {
            $kandidaten[] = PHP_BINARY;
        }
        foreach ($kandidaten as $kandidat) {
            if ($kandidat === '' || !@is_file($kandidat) || !@is_executable($kandidat)) {
                continue;
            }
            // FPM/CGI-Binaries explizit aussortieren.
            $basis = basename($kandidat);
            if (str_contains($basis, 'fpm') || str_contains($basis, 'cgi')) {
                continue;
            }
            return $kandidat;
        }
        return '';
    }

    private function ermittleOccPfad(): string
    {
        if (class_exists('\\OC') && isset(\OC::$SERVERROOT) && is_string(\OC::$SERVERROOT)) {
            $kandidat = rtrim(\OC::$SERVERROOT, '/') . '/occ';
            if (is_file($kandidat)) {
                return $kandidat;
            }
        }

        foreach (['/app/occ', '/var/www/html/occ'] as $kandidat) {
            if (is_file($kandidat)) {
                return $kandidat;
            }
        }

        return '';
    }

    private function funktionVerfuegbar(string $name): bool
    {
        if (!function_exists($name)) {
            return false;
        }
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        return !in_array($name, $disabled, true);
    }

    private function fuehreSynchronisationAus(): void
    {
        if (!$this->syncLockService->acquire()) {
            return;
        }

        $this->setCurrentWorkerPid(getmypid() ?: null);
        $status = $this->getSyncProgress();
        $startIso = (string) ($status['startedAt'] ?? (new \DateTimeImmutable())->format(DATE_ATOM));
        $start = \DateTimeImmutable::createFromFormat(DATE_ATOM, $startIso) ?: new \DateTimeImmutable();
        @ignore_user_abort(true);
        @set_time_limit(0);

        try {
            $this->scraperService->prefetchTopLevelListen([
                'geschaefte',
                'sitzungen',
                'mitglieder',
                'kommissionen',
                'fraktionen',
            ]);
            $vorabTotals = $this->scraperService->vorabTotalsFuerSync();
            foreach ($vorabTotals as $scope => $total) {
                if (!isset($status['sections'][$scope]) || !is_array($status['sections'][$scope])) {
                    continue;
                }
                $status['sections'][$scope]['total'] = max(0, (int) $total);
            }
            $status['updatedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            $this->setSyncProgress($status);

            $fortschritt = function (array $event) use (&$status, $start): void {
                $scope = (string) ($event['scope'] ?? '');
                if ($scope === '' || !isset(self::SYNC_SECTION_META[$scope])) {
                    return;
                }
                if (!isset($status['sections'][$scope]) || !is_array($status['sections'][$scope])) {
                    $status['sections'][$scope] = [
                        'label' => self::SYNC_SECTION_META[$scope]['label'],
                        'db' => self::SYNC_SECTION_META[$scope]['db'],
                        'processed' => 0,
                        'total' => 0,
                        'cursor' => '',
                    ];
                }

                $processed = max(0, (int) ($event['processed'] ?? 0));
                $total = max(0, (int) ($event['total'] ?? 0));
                $cursor = trim((string) ($event['cursor'] ?? ''));
                if ($total > 0) {
                    $processed = min($processed, $total);
                }

                $status['sections'][$scope]['processed'] = $processed;
                $status['sections'][$scope]['total'] = $total;
                if ($cursor !== '') {
                    $status['sections'][$scope]['cursor'] = $cursor;
                }
                $status['phase'] = 'running';
                $status['phaseLabel'] = 'Synchronisiere ' . self::SYNC_SECTION_META[$scope]['label'];
                $status['error'] = null;
                $status['current'] = [
                    'scope' => $scope,
                    'label' => self::SYNC_SECTION_META[$scope]['label'],
                    'db' => self::SYNC_SECTION_META[$scope]['db'],
                    'processed' => $processed,
                    'total' => $total,
                    'cursor' => $cursor,
                ];

                $elapsedSekunden = max(0, time() - $start->getTimestamp());
                $status['elapsed'] = $this->formatiereDauer($elapsedSekunden);
                [$globalProcessed, $globalTotal] = $this->berechneGlobalenFortschritt($status['sections']);
                $etaSekunden = null;
                if ($globalTotal > 0 && $globalProcessed > 0 && $globalProcessed < $globalTotal && $elapsedSekunden >= 5 && $globalProcessed >= 5) {
                    $etaSekunden = (int) round(($elapsedSekunden / $globalProcessed) * ($globalTotal - $globalProcessed));
                } elseif ($total > 0 && $processed > 0 && $processed < $total && $elapsedSekunden >= 5) {
                    $etaSekunden = (int) round(($elapsedSekunden / $processed) * ($total - $processed));
                }

                if ($globalTotal > 0 && $globalProcessed >= $globalTotal) {
                    $status['eta'] = '00:00:00';
                } elseif ($etaSekunden !== null && $etaSekunden >= 0) {
                    $status['eta'] = $this->formatiereDauer($etaSekunden);
                } else {
                    $status['eta'] = '--:--:--';
                }
                $status['updatedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
                $this->setSyncProgress($status);
                if ($this->isCancelRequested()) {
                    throw new \RuntimeException(self::SYNC_ABORT_SIGNAL);
                }
            };

            $resumeCursors = $this->ermittleResumeCursors($status);
            $this->setzePhase($status, 'mitglieder', $start);
            if ($this->isCancelRequested()) {
                throw new \RuntimeException(self::SYNC_ABORT_SIGNAL);
            }
            $mitglieder = $this->mitgliedService->synchronisieren($fortschritt, [
                'mitglieder' => ['resume_cursor' => $resumeCursors['mitglieder'] ?? ''],
                'fraktionen' => ['resume_cursor' => $resumeCursors['fraktionen'] ?? ''],
                'kommissionen' => ['resume_cursor' => $resumeCursors['kommissionen'] ?? ''],
            ]);
            $this->setzePhase($status, 'geschaefte', $start);
            if ($this->isCancelRequested()) {
                throw new \RuntimeException(self::SYNC_ABORT_SIGNAL);
            }
            $geschaefte = $this->geschaeftService->synchronisieren($fortschritt, [
                'resume_cursor' => $resumeCursors['geschaefte'] ?? '',
            ]);
            $this->setzePhase($status, 'sitzungen', $start);
            if ($this->isCancelRequested()) {
                throw new \RuntimeException(self::SYNC_ABORT_SIGNAL);
            }
            $sitzungen = $this->sitzungService->synchronisieren($fortschritt, [
                'resume_cursor' => $resumeCursors['sitzungen'] ?? '',
            ]);
            $this->kalenderService->sitzungenAktualisieren(
                $this->sitzungService->alleAktiven()
            );

            $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
            $this->config->setAppValue(Application::APP_ID, 'letzte_synchronisation', $jetzt);
            $statistik = [
                'mitglieder' => $mitglieder,
                'geschaefte' => $geschaefte,
                'sitzungen' => $sitzungen,
            ];
            $this->realtimePublisher->publish('sync.completed', [
                'quelle' => 'admin-ui',
                'zeitpunkt' => $jetzt,
                'statistik' => $statistik,
            ]);

            $status['running'] = false;
            $status['phase'] = 'abgeschlossen';
            $status['phaseLabel'] = 'Synchronisation abgeschlossen';
            $status['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            $status['updatedAt'] = $status['finishedAt'];
            $status['elapsed'] = $this->formatiereDauer(max(0, time() - $start->getTimestamp()));
            $status['eta'] = '00:00:00';
            $status['error'] = null;
            $status['current'] = [
                'scope' => 'done',
                'label' => 'Synchronisation abgeschlossen',
                'db' => '-',
                'processed' => 0,
                'total' => 0,
                'cursor' => '',
            ];
            $status['statistik'] = $statistik;
            $status['sections'] = $this->initialisiereSections();
            $this->setSyncProgress($status);
        } catch (\Throwable $e) {
            if ($e->getMessage() === self::SYNC_ABORT_SIGNAL) {
                $status['running'] = false;
                $status['phase'] = 'abgebrochen';
                $status['phaseLabel'] = 'Synchronisation abgebrochen';
                $status['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
                $status['updatedAt'] = $status['finishedAt'];
                $status['elapsed'] = $this->formatiereDauer(max(0, time() - $start->getTimestamp()));
                $status['eta'] = '--:--:--';
                $status['error'] = 'Synchronisation wurde manuell abgebrochen';
                $status['current'] = [
                    'scope' => 'abgebrochen',
                    'label' => 'Abgebrochen',
                    'db' => '-',
                    'processed' => 0,
                    'total' => 0,
                    'cursor' => '',
                ];
                $this->setSyncProgress($status);
                $this->realtimePublisher->publish('sync.cancelled', [
                    'quelle' => 'admin-ui',
                    'zeitpunkt' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]);
                return;
            }
            $status['running'] = false;
            $status['phase'] = 'fehler';
            $status['phaseLabel'] = 'Synchronisation fehlgeschlagen';
            $status['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            $status['updatedAt'] = $status['finishedAt'];
            $status['elapsed'] = $this->formatiereDauer(max(0, time() - $start->getTimestamp()));
            $status['eta'] = '--:--:--';
            $status['error'] = $e->getMessage();
            $status['current'] = [
                'scope' => 'error',
                'label' => 'Fehler',
                'db' => '-',
                'processed' => 0,
                'total' => 0,
                'cursor' => '',
            ];
            $this->setSyncProgress($status);
            $this->realtimePublisher->publish('sync.failed', [
                'quelle' => 'admin-ui',
                'fehler' => $e->getMessage(),
            ]);
        } finally {
            $this->setCancelRequested(false);
            $this->setCurrentWorkerPid(null);
            $this->syncLockService->release();
        }
    }

    /**
     * @param array<string, string> $resumeCursors
     * @return array<string, array<string, mixed>>
     */
    private function initialisiereSections(array $resumeCursors = []): array
    {
        $sections = [];
        foreach (self::SYNC_SECTION_META as $scope => $meta) {
            $sections[$scope] = [
                'label' => $meta['label'],
                'db' => $meta['db'],
                'processed' => 0,
                'total' => 0,
                'cursor' => (string) ($resumeCursors[$scope] ?? ''),
            ];
        }
        return $sections;
    }

    /**
     * @param array<string, mixed> $sections
     * @return array<string, array<string, mixed>>
     */
    private function normalisiereSections(array $sections): array
    {
        $normalisiert = $this->initialisiereSections();
        foreach ($sections as $scope => $werte) {
            if (!is_array($werte) || !isset($normalisiert[$scope])) {
                continue;
            }
            $normalisiert[$scope]['processed'] = max(0, (int) ($werte['processed'] ?? 0));
            $normalisiert[$scope]['total'] = max(0, (int) ($werte['total'] ?? 0));
            $normalisiert[$scope]['cursor'] = trim((string) ($werte['cursor'] ?? $normalisiert[$scope]['cursor']));
        }
        return $normalisiert;
    }

    /**
     * @param array<string, array<string, mixed>> $sections
     * @return array{0: int, 1: int}
     */
    private function berechneGlobalenFortschritt(array $sections): array
    {
        $processed = 0;
        $total = 0;
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionProcessed = max(0, (int) ($section['processed'] ?? 0));
            $sectionTotal = max(0, (int) ($section['total'] ?? 0));
            if ($sectionTotal <= 0) {
                continue;
            }
            $processed += min($sectionProcessed, $sectionTotal);
            $total += $sectionTotal;
        }
        return [$processed, $total];
    }

    /**
     * @param array<string, mixed> $status
     */
    private function istSyncStatusVeraltet(array $status): bool
    {
        if (($status['running'] ?? false) !== true) {
            return false;
        }

        $heartbeat = (string) ($status['updatedAt'] ?? $status['startedAt'] ?? '');
        if ($heartbeat === '') {
            return false;
        }
        $heartbeatAt = \DateTimeImmutable::createFromFormat(DATE_ATOM, $heartbeat);
        if (!$heartbeatAt instanceof \DateTimeImmutable) {
            return false;
        }

        return (time() - $heartbeatAt->getTimestamp()) > self::SYNC_STALE_SECONDS;
    }

    /**
     * @param array<string, mixed> $status
     * @return array<string, mixed>
     */
    private function markiereSyncAlsAbgebrochen(array $status, string $grund): array
    {
        $status['running'] = false;
        $status['phase'] = 'abgebrochen';
        $status['phaseLabel'] = 'Synchronisation abgebrochen';
        $status['error'] = $grund;
        $status['finishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $status['updatedAt'] = $status['finishedAt'];
        $status['eta'] = '--:--:--';
        $status['current'] = [
            'scope' => 'abgebrochen',
            'label' => 'Abgebrochen',
            'db' => '-',
            'processed' => 0,
            'total' => 0,
            'cursor' => '',
        ];
        $this->setSyncProgress($status);
        return $status;
    }

    /**
     * @param array<string, mixed> $status
     * @return array<string, string>
     */
    private function ermittleResumeCursors(array $status): array
    {
        $result = [];
        $sections = $status['sections'] ?? [];
        if (!is_array($sections)) {
            return $result;
        }
        foreach ($sections as $scope => $section) {
            if (!is_array($section) || !isset(self::SYNC_SECTION_META[$scope])) {
                continue;
            }
            $cursor = trim((string) ($section['cursor'] ?? ''));
            $processed = max(0, (int) ($section['processed'] ?? 0));
            $total = max(0, (int) ($section['total'] ?? 0));
            if ($cursor === '' || $processed <= 0 || ($total > 0 && $processed >= $total)) {
                continue;
            }
            $result[$scope] = $cursor;
        }
        return $result;
    }

    private function formatiereDauer(int $sekunden): string
    {
        $sekunden = max(0, $sekunden);
        $h = intdiv($sekunden, 3600);
        $m = intdiv($sekunden % 3600, 60);
        $s = $sekunden % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    /**
     * @param array<string, mixed> $status
     */
    private function setzePhase(array &$status, string $scope, \DateTimeImmutable $start): void
    {
        if (!isset(self::SYNC_SECTION_META[$scope])) {
            return;
        }
        $status['phase'] = 'running';
        $status['phaseLabel'] = 'Synchronisiere ' . self::SYNC_SECTION_META[$scope]['label'];
        $status['current'] = [
            'scope' => $scope,
            'label' => self::SYNC_SECTION_META[$scope]['label'],
            'db' => self::SYNC_SECTION_META[$scope]['db'],
            'processed' => (int) ($status['sections'][$scope]['processed'] ?? 0),
            'total' => (int) ($status['sections'][$scope]['total'] ?? 0),
            'cursor' => (string) ($status['sections'][$scope]['cursor'] ?? ''),
        ];
        $status['updatedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $status['elapsed'] = $this->formatiereDauer(max(0, time() - $start->getTimestamp()));
        $this->setSyncProgress($status);
        error_log('[parlwin] sync-phase: ' . self::SYNC_SECTION_META[$scope]['label']
            . ' (db=' . self::SYNC_SECTION_META[$scope]['db'] . ')');
    }

    private function isCancelRequested(): bool
    {
        return trim($this->config->getAppValue(Application::APP_ID, self::SYNC_CANCEL_REQUESTED_KEY, '0')) === '1';
    }

    private function setCancelRequested(bool $requested): void
    {
        $this->config->setAppValue(
            Application::APP_ID,
            self::SYNC_CANCEL_REQUESTED_KEY,
            $requested ? '1' : '0'
        );
    }

    private function getCurrentWorkerPid(): ?int
    {
        $raw = trim((string) $this->config->getAppValue(Application::APP_ID, self::SYNC_WORKER_PID_KEY, ''));
        if ($raw === '') {
            return null;
        }
        $pid = (int) $raw;
        return $pid > 1 ? $pid : null;
    }

    /**
     * Prüft, ob der zuletzt gespeicherte Worker-Prozess noch läuft.
     * Wir bevorzugen `posix_kill($pid, 0)`, fallen sonst auf `/proc/<pid>` zurück.
     */
    private function istWorkerProzessLebendig(): bool
    {
        $pid = $this->getCurrentWorkerPid();
        if ($pid === null) {
            return false;
        }
        if (function_exists('posix_kill')) {
            if (@posix_kill($pid, 0)) {
                return true;
            }
            // ESRCH oder EPERM: bei EPERM lebt der Prozess noch (gehört nur jemand anderem).
            if (function_exists('posix_get_last_error') && posix_get_last_error() === 1 /* EPERM */) {
                return true;
            }
        }
        if (@is_dir('/proc/' . $pid)) {
            return true;
        }
        return false;
    }

    private function setCurrentWorkerPid(?int $pid): void
    {
        $this->config->setAppValue(
            Application::APP_ID,
            self::SYNC_WORKER_PID_KEY,
            ($pid !== null && $pid > 1) ? (string) $pid : ''
        );
    }

    /**
     * @param array<string, mixed> $status
     */
    private function istQueueStartFrisch(array $status): bool
    {
        if (((string) ($status['phase'] ?? '')) !== 'queued') {
            return false;
        }
        $startedRaw = (string) ($status['startedAt'] ?? '');
        if ($startedRaw === '') {
            return false;
        }
        $startedAt = \DateTimeImmutable::createFromFormat(DATE_ATOM, $startedRaw);
        if (!$startedAt instanceof \DateTimeImmutable) {
            return false;
        }
        return (time() - $startedAt->getTimestamp()) <= self::SYNC_QUEUE_GRACE_SECONDS;
    }
}
