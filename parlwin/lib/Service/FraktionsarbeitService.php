<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftAktion;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\Fraktionsrolle;
use OCA\ParliamentWinterthur\Db\FraktionsrolleMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftZustaendigkeit;
use OCA\ParliamentWinterthur\Db\GeschaeftZustaendigkeitMapper;
use OCA\ParliamentWinterthur\Db\Kommission;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * Kapselt die fraktionsinterne Arbeit: Aktionen, Beschlüsse, Zuständigkeiten,
 * Fraktionssitzungsmodus und Protokollführer-Regeln.
 */
class FraktionsarbeitService
{
    private const APP_ID = 'parlwin';

    private const CFG_SITZUNGSMODUS = 'fraktionssitzung_modus';
    private const CFG_PROTOKOLLFUEHRER_UID = 'fraktionssitzung_protokollfuehrer_uid';
    private const CFG_PROTOKOLLFUEHRER_NAME = 'fraktionssitzung_protokollfuehrer_name';

    public const ROLLE_KOMMISSIONSMITGLIED = 'kommissionsmitglied';
    public const ROLLE_FRAKTIONSPRAESIDENT = 'fraktionspraesident';
    public const ROLLE_FRAKTIONSPRAESIDENT_STV = 'fraktionspraesident_stellvertretung';
    public const ROLLE_PROTOKOLLFUEHRER = 'protokollfuehrer';
    public const ROLLE_PROTOKOLLFUEHRER_STV = 'protokollfuehrer_stellvertretung';

    /**
     * Beschriftungen für die internen Beschluss-Codes. Hart kodiert gemäss
     * Gemeindeordnung; eine spätere Erweiterung muss zusammen mit
     * {@see GeschaeftWorkflow::erlaubteBeschluesse()} angepasst werden.
     *
     * @var array<string, string>
     */
    private const BESCHLUSS_LABELS = [
        'unterstuetzen' => 'Unterstützen',
        'ablehnen' => 'Ablehnen',
        'stimmfreigabe' => 'Stimmfreigabe',
        'miteinreichen_fraktion' => 'Miteinreichen als Fraktion',
        'miteinreichen_einzel' => 'Miteinreichen einzelne Personen',
        'ueberweisung_befuerworten' => 'Überweisung befürworten',
        'ueberweisung_ablehnen' => 'Überweisung ablehnen',
        'kenntnisnahme_positiv' => 'Kenntnisnahme positiv',
        'kenntnisnahme_negativ' => 'Kenntnisnahme negativ',
        'nachbericht_verlangen' => 'Nachbericht verlangen',
        'erheblich_erklaeren' => 'Erheblich erklären',
        'abschreiben' => 'Abschreiben',
    ];

    public function __construct(
        private readonly GeschaeftMapper $geschaeftMapper,
        private readonly GeschaeftAktionMapper $aktionMapper,
        private readonly GeschaeftZustaendigkeitMapper $zustaendigkeitMapper,
        private readonly FraktionsrolleMapper $rollenMapper,
        private readonly MitgliedMapper $mitgliedMapper,
        private readonly KommissionMapper $kommissionMapper,
        private readonly GeschaeftEreignisMapper $ereignisMapper,
        private readonly IConfig $config,
        private readonly IUserSession $userSession,
        private readonly IGroupManager $groupManager,
    ) {
    }

    /**
     * @param Geschaeft[] $geschaefte
     * @return array<int, array<string, mixed>>
     */
    public function angereicherteGeschaefte(
        array $geschaefte,
        string $filterLetzterBeschluss = '',
        ?bool $filterEntscheidungsbedarf = null
    ): array {
        $result = [];

        foreach ($geschaefte as $geschaeft) {
            $eintrag = $geschaeft->jsonSerialize();
            $letzterBeschluss = $this->aktionMapper->findLetzterGueltigerBeschluss((int) $geschaeft->getId());
            $haupt = $this->zustaendigkeitMapper->findHauptByGeschaeft((int) $geschaeft->getId());

            $eintrag['letzterBeschluss'] = $letzterBeschluss !== null ? $this->mapAktion($letzterBeschluss) : null;
            $eintrag['hauptZustaendigePerson'] = $haupt !== null ? $haupt->getPersonName() : '';
            $aktiveZustaendigkeiten = $this->zustaendigkeitMapper->findAktiveByGeschaeft((int) $geschaeft->getId());
            $eintrag['zustaendigkeiten'] = array_map(
                fn(GeschaeftZustaendigkeit $z): array => $this->mapZustaendigkeit($z),
                $aktiveZustaendigkeiten
            );
            $eintrag['erlaubteBeschluesse'] = $this->ermittleErlaubteBeschluesse($geschaeft);
            $this->fuelleFraktionsstatus($eintrag, $geschaeft, $letzterBeschluss);

            if ($filterLetzterBeschluss !== '') {
                $code = (string) (($eintrag['letzterBeschluss']['aktionCode'] ?? ''));
                if ($code !== $filterLetzterBeschluss) {
                    continue;
                }
            }

            if ($filterEntscheidungsbedarf !== null) {
                $bedarf = (bool) ($eintrag['entscheidungsbedarf'] ?? false);
                if ($bedarf !== $filterEntscheidungsbedarf) {
                    continue;
                }
            }

            $result[] = $eintrag;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function angereichertesGeschaeft(int $geschaeftId): array
    {
        $geschaeft = $this->geschaeftMapper->find($geschaeftId);
        $daten = $geschaeft->jsonSerialize();

        $aktionen = $this->aktionMapper->findByGeschaeft($geschaeftId);
        $zustaendigkeiten = $this->zustaendigkeitMapper->findAktiveByGeschaeft($geschaeftId);
        $letzterBeschluss = $this->aktionMapper->findLetzterGueltigerBeschluss($geschaeftId);

        $daten['aktionen'] = array_map(fn(GeschaeftAktion $a): array => $this->mapAktion($a), $aktionen);
        $daten['zustaendigkeiten'] = array_map(fn(GeschaeftZustaendigkeit $z): array => $this->mapZustaendigkeit($z), $zustaendigkeiten);
        $daten['letzterBeschluss'] = $letzterBeschluss !== null ? $this->mapAktion($letzterBeschluss) : null;
        $aktuellesVotum = $this->aktionMapper->findAktuellesVotum($geschaeftId);
        $daten['aktuellesVotum'] = $aktuellesVotum !== null ? $this->mapAktion($aktuellesVotum) : null;
        $daten['istNutzerZustaendig'] = $this->istNutzerZustaendig($geschaeftId, $zustaendigkeiten);
        $daten['erlaubteBeschluesse'] = $this->ermittleErlaubteBeschluesse($geschaeft);
        $daten['fraktionssitzung'] = $this->fraktionssitzungKontext();
        $this->fuelleFraktionsstatus($daten, $geschaeft, $letzterBeschluss);

        return $daten;
    }

    /**
     * @return array<string, mixed>
     */
    public function notizHinzufuegen(int $geschaeftId, string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Notiztext darf nicht leer sein');
        }

        $aktion = $this->erstelleAktion($geschaeftId, 'notiz', '', 'Notiz', $text, false);
        return $this->mapAktion($aktion);
    }

    /**
     * @return array<string, mixed>
     */
    public function votumHinzufuegen(int $geschaeftId, string $votum): array
    {
        $votum = trim($votum);
        if ($votum === '') {
            throw new \InvalidArgumentException('Votum darf nicht leer sein');
        }

        $aktion = $this->erstelleAktion($geschaeftId, 'votum', 'votum_im_rat', 'Votum im Rat', $votum, false);
        return $this->mapAktion($aktion);
    }

    /**
     * Erstellt oder aktualisiert das aktuell aktive Votum (entscheid_gueltig = true).
     * Nur die zuständige Person darf das Votum bearbeiten.
     *
     * Liefert das aktuelle Votum oder null, wenn der Text leer ist und kein
     * aktives Votum existierte.
     *
     * @return array<string, mixed>|null
     */
    public function votumAktualisieren(int $geschaeftId, string $text): ?array
    {
        $this->pruefeVotumSchreibrecht($geschaeftId);

        $text = trim($text);
        $aktuell = $this->aktionMapper->findAktuellesVotum($geschaeftId);

        if ($text === '') {
            // Leerer Text bei bestehendem Votum: nicht archivieren (das geht
            // explizit über archivieren) – nur Text auf leer setzen.
            if ($aktuell === null) {
                return null;
            }
            $aktuell->setText('');
            $this->aktionMapper->update($aktuell);
            return $this->mapAktion($aktuell);
        }

        if ($aktuell !== null) {
            $aktuell->setText($text);
            $this->aktionMapper->update($aktuell);
            return $this->mapAktion($aktuell);
        }

        $aktion = $this->erstelleAktion(
            $geschaeftId,
            'votum',
            'votum_im_rat',
            'Votum im Rat',
            $text,
            true,
        );
        return $this->mapAktion($aktion);
    }

    /**
     * Archiviert das aktuell aktive Votum (entscheid_gueltig -> false), so
     * dass es als historischer Eintrag in der Zeitleiste verbleibt und ein
     * neues Votum gestartet werden kann.
     *
     * @return array<string, mixed>|null Das archivierte Votum oder null,
     *                                   falls keines aktiv war.
     */
    public function votumArchivieren(int $geschaeftId): ?array
    {
        $this->pruefeVotumSchreibrecht($geschaeftId);

        $aktuell = $this->aktionMapper->findAktuellesVotum($geschaeftId);
        if ($aktuell === null) {
            return null;
        }

        $aktuell->setEntscheidGueltig(false);
        $this->aktionMapper->update($aktuell);

        return $this->mapAktion($aktuell);
    }

    /**
     * Prüft, ob der aktuelle Benutzer für das Geschäft zuständig ist.
     *
     * @param GeschaeftZustaendigkeit[]|null $zustaendigkeiten Optionaler Cache.
     */
    public function istNutzerZustaendig(int $geschaeftId, ?array $zustaendigkeiten = null): bool
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return false;
        }
        $uid = $user->getUID();
        $mitglied = $this->mitgliedMapper->findByNextcloudUid($uid);
        if ($mitglied === null) {
            return false;
        }
        $externId = (string) $mitglied->getExternId();
        if ($externId === '') {
            return false;
        }

        $zustaendigkeiten = $zustaendigkeiten ?? $this->zustaendigkeitMapper->findAktiveByGeschaeft($geschaeftId);
        foreach ($zustaendigkeiten as $z) {
            if ((string) $z->getMitgliedExternId() === $externId) {
                return true;
            }
        }
        return false;
    }

    private function pruefeVotumSchreibrecht(int $geschaeftId): void
    {
        if (!$this->istNutzerZustaendig($geschaeftId)) {
            throw new \RuntimeException('Nur die zuständige Person darf das Votum bearbeiten');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function beschlussHinzufuegen(int $geschaeftId, string $beschlussCode, string $begruendung = ''): array
    {
        $this->pruefeBeschlussSchreibrecht();

        $geschaeft = $this->geschaeftMapper->find($geschaeftId);
        $erlaubt = array_column($this->ermittleErlaubteBeschluesse($geschaeft), 'code');
        if (!in_array($beschlussCode, $erlaubt, true)) {
            throw new \InvalidArgumentException('Beschluss ist im aktuellen Status nicht zulässig');
        }

        $label = self::BESCHLUSS_LABELS[$beschlussCode] ?? $beschlussCode;
        $aktion = $this->erstelleAktion(
            $geschaeftId,
            'beschluss',
            $beschlussCode,
            $label,
            trim($begruendung),
            true,
        );

        return $this->mapAktion($aktion);
    }

    /**
     * Nimmt den aktuell gültigen Beschluss zurück (entscheid_gueltig = false)
     * und legt eine Audit-Aktion an. Liefert die neue Audit-Aktion oder null,
     * falls kein gültiger Beschluss vorhanden war.
     *
     * @return array<string, mixed>|null
     */
    public function beschlussZuruecknehmen(int $geschaeftId): ?array
    {
        $this->pruefeBeschlussSchreibrecht();

        $letzter = $this->aktionMapper->findLetzterGueltigerBeschluss($geschaeftId);
        if ($letzter === null) {
            return null;
        }

        $letzter->setEntscheidGueltig(false);
        $this->aktionMapper->update($letzter);

        $label = self::BESCHLUSS_LABELS[$letzter->getAktionCode()] ?? $letzter->getAktionCode();
        $aktion = $this->erstelleAktion(
            $geschaeftId,
            'beschluss',
            'beschluss_zurueckgenommen',
            'Beschluss zurückgenommen',
            sprintf('Beschluss "%s" zurückgenommen', $label),
            false,
        );

        return $this->mapAktion($aktion);
    }

    /**
     * @param array<int, array<string, string>> $personen
     * @return array<int, array<string, mixed>>
     */
    public function zustaendigkeitenSetzen(int $geschaeftId, array $personen, string $hauptPersonKey = ''): array
    {
        $normalisiert = [];

        foreach ($personen as $person) {
            $externId = trim((string) ($person['mitgliedExternId'] ?? ''));
            $name = trim((string) ($person['personName'] ?? ''));
            if ($name === '' && $externId === '') {
                continue;
            }

            $personKey = $externId !== ''
                ? 'mitglied:' . $externId
                : 'name:' . md5($name);

            $normalisiert[$personKey] = [
                'person_key' => $personKey,
                'mitglied_extern_id' => $externId,
                'person_name' => $name,
                'ist_haupt' => false,
            ];
        }

        if ($hauptPersonKey !== '' && isset($normalisiert[$hauptPersonKey])) {
            $normalisiert[$hauptPersonKey]['ist_haupt'] = true;
        }

        if ($hauptPersonKey === '' && $normalisiert !== []) {
            $firstKey = array_key_first($normalisiert);
            if ($firstKey !== null) {
                $normalisiert[$firstKey]['ist_haupt'] = true;
            }
        }

        // Vorher-Zustand für Diff ermitteln (wer war drin, wer war Haupt)
        $vorher = $this->zustaendigkeitMapper->findAktiveByGeschaeft($geschaeftId);
        $vorherMap = [];
        $vorherHauptKey = '';
        $vorherHauptName = '';
        foreach ($vorher as $z) {
            $key = (string) $z->getPersonKey();
            $vorherMap[$key] = (string) $z->getPersonName();
            if ($z->getIstHaupt()) {
                $vorherHauptKey = $key;
                $vorherHauptName = (string) $z->getPersonName();
            }
        }

        $this->zustaendigkeitMapper->ersetzeAktive($geschaeftId, array_values($normalisiert));

        // Diff berechnen: neu hinzugefügt vs. entfernt
        $nachherMap = [];
        $nachherHauptKey = '';
        $nachherHauptName = '';
        foreach ($normalisiert as $key => $p) {
            $nachherMap[$key] = $p['person_name'] !== '' ? $p['person_name'] : $p['mitglied_extern_id'];
            if (!empty($p['ist_haupt'])) {
                $nachherHauptKey = $key;
                $nachherHauptName = $nachherMap[$key];
            }
        }

        $hinzugefuegt = array_values(array_diff_key($nachherMap, $vorherMap));
        $entfernt = array_values(array_diff_key($vorherMap, $nachherMap));

        $teile = [];
        foreach ($hinzugefuegt as $name) {
            $teile[] = sprintf('Zuständigkeit "%s" gesetzt', $name);
        }
        foreach ($entfernt as $name) {
            $teile[] = sprintf('Zuständigkeit "%s" entfernt', $name);
        }
        if ($vorherHauptKey !== $nachherHauptKey && $nachherHauptKey !== '' && !in_array($nachherHauptKey, array_keys(array_diff_key($nachherMap, $vorherMap)), true)) {
            // Hauptzuständigkeit hat innerhalb bestehender Personen gewechselt
            $teile[] = sprintf('Hauptzuständigkeit auf "%s" geändert', $nachherHauptName);
        }

        if ($teile === []) {
            // Kein effektiver Diff – Aktion trotzdem festhalten (Audit)
            $teile[] = $nachherMap === []
                ? 'Alle Zuständigkeiten entfernt'
                : 'Zuständigkeiten unverändert bestätigt';
        }

        $zuweisungsText = implode('; ', $teile);
        $this->erstelleAktion($geschaeftId, 'zuweisung', 'zustaendigkeit_geaendert', 'Zuständigkeiten geändert', $zuweisungsText, false);

        $neu = $this->zustaendigkeitMapper->findAktiveByGeschaeft($geschaeftId);
        return array_map(fn(GeschaeftZustaendigkeit $z): array => $this->mapZustaendigkeit($z), $neu);
    }

    /**
     * Weist allen nicht-erledigten Geschäften OHNE aktuell zugewiesene
     * Zuständigkeit automatisch die Kommissionsmitglieder der eigenen
     * Fraktion zu, sofern der aktuelle Geschäftsstatus auf eine bekannte
     * Kommission verweist (z.B. „Bei der Aufsichtskommission pendent" oder
     * „Bei der Kommission Soziales und Sicherheit pendent").
     *
     * Wird vom Sync nach erfolgreicher Aktualisierung von Geschäften und
     * Mitgliedern aufgerufen.
     *
     * @return array{gepruet:int, zugewiesen:int, uebersprungen:int, ohne_kommission:int, ohne_passendes_mitglied:int}
     */
    public function autoZuweisenKommissionsmitglieder(): array
    {
        $statistik = [
            'gepruet' => 0,
            'zugewiesen' => 0,
            'uebersprungen' => 0,
            'ohne_kommission' => 0,
            'ohne_passendes_mitglied' => 0,
        ];

        $eigeneFraktion = $this->config->getAppValue(self::APP_ID, 'fraktion', '');
        if ($eigeneFraktion === '') {
            return $statistik;
        }

        // Aktive Kommissionen indizieren mit denselben Tokens, die auch das
        // Frontend (Kommissionsliste.vue → geschaefteFuer) verwendet, damit
        // hier und dort dieselben Status→Kommission-Zuordnungen gelten.
        $kommissionsIndex = [];
        foreach ($this->kommissionMapper->findAll() as $kommission) {
            if (!$kommission->getAktiv()) {
                continue;
            }
            $name = trim($kommission->getName());
            if ($name === '') {
                continue;
            }
            $kommissionsIndex[] = [
                'kommission' => $kommission,
                'nameLower' => mb_strtolower($name),
                'tokens' => self::tokensFuerKommissionsname($name),
            ];
        }
        if ($kommissionsIndex === []) {
            return $statistik;
        }

        // Mitglieder der eigenen Fraktion nach extern_id indizieren.
        $mitgliedByExternId = [];
        foreach ($this->mitgliedMapper->findByFraktion($eigeneFraktion) as $mitglied) {
            $extId = (string) $mitglied->getExternId();
            if ($extId !== '') {
                $mitgliedByExternId[$extId] = $mitglied;
            }
        }
        if ($mitgliedByExternId === []) {
            return $statistik;
        }

        // Nur Geschäfte mit nicht-finalem Status (= "hängig").
        $geschaefte = $this->geschaeftMapper->findAll(10000, 0, false);

        foreach ($geschaefte as $geschaeft) {
            if ($geschaeft->getGeloescht()) {
                continue;
            }
            $statistik['gepruet']++;
            $geschaeftId = (int) $geschaeft->getId();

            // Bereits zugewiesen? -> nichts tun.
            $aktiveZust = $this->zustaendigkeitMapper->findAktiveByGeschaeft($geschaeftId);
            if ($aktiveZust !== []) {
                $statistik['uebersprungen']++;
                continue;
            }

            $zugehoerigeKommission = self::findeKommissionFuerStatus(
                (string) $geschaeft->getStatus(),
                $kommissionsIndex
            );
            if ($zugehoerigeKommission === null) {
                $statistik['ohne_kommission']++;
                continue;
            }

            // Mitglieder dieser Kommission, die zugleich in unserer Fraktion sind.
            $personen = [];
            foreach ($zugehoerigeKommission->getMitgliederArray() as $eintrag) {
                if (is_array($eintrag)) {
                    if (array_key_exists('aktiv', $eintrag) && $eintrag['aktiv'] === false) {
                        continue;
                    }
                    $extId = (string) ($eintrag['externId'] ?? $eintrag['extern_id'] ?? '');
                } else {
                    $extId = (string) $eintrag;
                }
                if ($extId === '' || !isset($mitgliedByExternId[$extId])) {
                    continue;
                }
                $mitglied = $mitgliedByExternId[$extId];
                $angezeigterName = trim(
                    trim((string) $mitglied->getVorname()) . ' ' . trim((string) $mitglied->getName())
                );
                $personen[] = [
                    'mitgliedExternId' => $extId,
                    'personName' => $angezeigterName,
                ];
            }
            if ($personen === []) {
                $statistik['ohne_passendes_mitglied']++;
                continue;
            }

            $hauptKey = 'mitglied:' . $personen[0]['mitgliedExternId'];
            try {
                $this->zustaendigkeitenSetzen($geschaeftId, $personen, $hauptKey);
                $statistik['zugewiesen']++;
            } catch (\Throwable) {
                $statistik['uebersprungen']++;
            }
        }

        return $statistik;
    }

    /**
     * Entfernt aus einem String alle "*kommission*"-Tokens und liefert
     * den normalisierten Rest.  Identisch zur JS-Funktion
     * `stripKommissionWords` in `src/js/components/Kommissionsliste.vue`.
     */
    private static function stripKommissionWords(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/[,.;:()\/]/u', ' ', $s) ?? $s;
        $s = preg_replace('/\S*kommission\S*/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    /**
     * Tokenisiert einen Kommissionsnamen wie das Frontend
     * (`Kommissionsliste.vue → tokenize`): "Kommission"-Wörter entfernen,
     * deutsche Stopwords filtern, nur Tokens ≥ 3 Zeichen behalten.
     *
     * @return string[]
     */
    private static function tokensFuerKommissionsname(string $name): array
    {
        $stopwords = [
            'und' => true,
            'der' => true,
            'die' => true,
            'das' => true,
            'den' => true,
            'dem' => true,
            'bei' => true,
            'pendent' => true,
            'für' => true,
            'fuer' => true,
            'in' => true,
            'im' => true,
            'zur' => true,
            'zum' => true,
            'von' => true,
            'vom' => true,
        ];
        $tokens = [];
        foreach (preg_split('/\s+/u', self::stripKommissionWords($name)) ?: [] as $t) {
            if ($t === '' || mb_strlen($t) < 3 || isset($stopwords[$t])) {
                continue;
            }
            $tokens[] = $t;
        }
        return $tokens;
    }

    /**
     * Liefert die Kommission, auf die der Status-String verweist, oder null.
     * Spiegelt die Logik von `Kommissionsliste.vue → geschaefteFuer`:
     *   1. Status muss überhaupt ein "*kommission*"-Wort enthalten.
     *   2. Alle Tokens des Kommissionsnamens (ohne "*kommission*"-Wörter)
     *      müssen im strippten Status vorkommen.
     *   3. Bleibt nach dem Strip nichts vom Namen übrig (z.B. nur
     *      "Aufsichtskommission"), wird der Roh-Name direkt im Status
     *      gesucht.
     *
     * @param array<int, array{kommission: Kommission, nameLower: string, tokens: string[]}> $index
     */
    private static function findeKommissionFuerStatus(string $status, array $index): ?Kommission
    {
        if ($status === '') {
            return null;
        }
        $statusLower = mb_strtolower($status);
        if (!preg_match('/\S*kommission\S*/u', $statusLower)) {
            return null;
        }
        $stripped = self::stripKommissionWords($status);
        foreach ($index as $eintrag) {
            $tokens = $eintrag['tokens'];
            if ($tokens === []) {
                if (str_contains($statusLower, $eintrag['nameLower'])) {
                    return $eintrag['kommission'];
                }
                continue;
            }
            $alleVorhanden = true;
            foreach ($tokens as $t) {
                if (!str_contains($stripped, $t)) {
                    $alleVorhanden = false;
                    break;
                }
            }
            if ($alleVorhanden) {
                return $eintrag['kommission'];
            }
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function fraktionssitzungKontext(): array
    {
        $modus = $this->istFraktionssitzungModusAktiv();
        $protokollfuehrer = $this->rollenMapper->findAktiveByRolle(self::ROLLE_PROTOKOLLFUEHRER);
        $protokollfuehrerUid = '';
        $protokollfuehrerName = '';
        if ($protokollfuehrer !== []) {
            $protokollfuehrerUid = (string) $protokollfuehrer[0]->getUid();
            $protokollfuehrerName = (string) $protokollfuehrer[0]->getName();
        } else {
            // Rückwärtskompatibilität zu bereits gespeicherten Legacy-Settings.
            $protokollfuehrerUid = $this->config->getAppValue(self::APP_ID, self::CFG_PROTOKOLLFUEHRER_UID, '');
            $protokollfuehrerName = $this->config->getAppValue(self::APP_ID, self::CFG_PROTOKOLLFUEHRER_NAME, '');
        }

        $praesidenten = $this->rollenMapper->findAktiveByRolle(self::ROLLE_FRAKTIONSPRAESIDENT);
        $praesidiumStv = $this->rollenMapper->findAktiveByRolle(self::ROLLE_FRAKTIONSPRAESIDENT_STV);
        $protokollStv = $this->rollenMapper->findAktiveByRolle(self::ROLLE_PROTOKOLLFUEHRER_STV);
        $kommissionsmitglieder = $this->rollenMapper->findAktiveByRolle(self::ROLLE_KOMMISSIONSMITGLIED);

        return [
            'modusAktiv' => $modus,
            'protokollfuehrerUid' => $protokollfuehrerUid,
            'protokollfuehrerName' => $protokollfuehrerName,
            'beschlussSchreibbar' => $this->istBeschlussSchreibenErlaubt(),
            'kannProtokollfuehrerSetzen' => $this->kannPraesidiumHandeln(),
            'kannPraesidiumHandeln' => $this->kannPraesidiumHandeln(),
            'kannProtokollfuehrungHandeln' => $this->kannProtokollfuehrungHandeln(),
            'praesidenten' => array_map(fn($r): array => $this->mapRolle($r), $praesidenten),
            'praesidiumStellvertretungen' => array_map(fn($r): array => $this->mapRolle($r), $praesidiumStv),
            'protokollfuehrer' => array_map(fn($r): array => $this->mapRolle($r), $protokollfuehrer),
            'protokollfuehrerStellvertretungen' => array_map(fn($r): array => $this->mapRolle($r), $protokollStv),
            'kommissionsmitglieder' => array_map(fn($r): array => $this->mapRolle($r), $kommissionsmitglieder),
        ];
    }

    public function setzeFraktionssitzungModus(bool $aktiv): void
    {
        if (!$this->kannPraesidiumHandeln()) {
            throw new \RuntimeException('Nur Fraktionspräsidium (oder aktive Stellvertretung) darf den Modus ändern');
        }

        $this->config->setAppValue(self::APP_ID, self::CFG_SITZUNGSMODUS, $aktiv ? '1' : '0');
    }

    public function setzeFraktionspraesident(string $uid, string $name = ''): void
    {
        if (!$this->istFraktionsGruppenAdmin()) {
            throw new \RuntimeException('Nur Fraktions-Gruppen-Admin darf das Fraktionspräsidium setzen');
        }

        [$uid, $name] = $this->normalisiereRollenPerson($uid, $name);
        $this->rollenMapper->deaktiviereAktiveByRolle(self::ROLLE_FRAKTIONSPRAESIDENT);
        $this->rollenMapper->insert($this->erstelleRolle(self::ROLLE_FRAKTIONSPRAESIDENT, $uid, $name, null, null));
    }

    public function setzeProtokollfuehrer(string $uid, string $name = ''): void
    {
        if (!$this->kannPraesidiumHandeln()) {
            throw new \RuntimeException('Nur Fraktionspräsidium (oder aktive Stellvertretung) darf Protokollführer setzen');
        }

        [$uid, $name] = $this->normalisiereRollenPerson($uid, $name);
        $this->rollenMapper->deaktiviereAktiveByRolle(self::ROLLE_PROTOKOLLFUEHRER);
        $this->rollenMapper->insert($this->erstelleRolle(self::ROLLE_PROTOKOLLFUEHRER, $uid, $name, null, null));

        // Rückwärtskompatibel für bestehende Frontend-Logik.
        $this->config->setAppValue(self::APP_ID, self::CFG_PROTOKOLLFUEHRER_UID, $uid);
        $this->config->setAppValue(self::APP_ID, self::CFG_PROTOKOLLFUEHRER_NAME, $name);
    }

    public function setzePraesidiumStellvertretung(string $uid, string $name = '', string $gueltigVon = '', string $gueltigBis = ''): void
    {
        if (!$this->kannPraesidiumHandeln()) {
            throw new \RuntimeException('Nur Fraktionspräsidium darf eine Präsidiums-Stellvertretung setzen');
        }

        [$uid, $name] = $this->normalisiereRollenPerson($uid, $name);
        [$von, $bis] = $this->normalisiereGueltigkeit($gueltigVon, $gueltigBis);
        $this->rollenMapper->insert($this->erstelleRolle(self::ROLLE_FRAKTIONSPRAESIDENT_STV, $uid, $name, $von, $bis));
    }

    public function setzeProtokollfuehrerStellvertretung(string $uid, string $name = '', string $gueltigVon = '', string $gueltigBis = ''): void
    {
        if (!$this->kannPraesidiumHandeln() && !$this->kannProtokollfuehrungHandeln()) {
            throw new \RuntimeException('Nur Fraktionspräsidium oder Protokollführung darf eine Protokoll-Stellvertretung setzen');
        }

        [$uid, $name] = $this->normalisiereRollenPerson($uid, $name);
        [$von, $bis] = $this->normalisiereGueltigkeit($gueltigVon, $gueltigBis);
        $this->rollenMapper->insert($this->erstelleRolle(self::ROLLE_PROTOKOLLFUEHRER_STV, $uid, $name, $von, $bis));
    }

    public function setzeKommissionsmitglied(string $uid, string $name = '', string $gueltigVon = '', string $gueltigBis = ''): void
    {
        if (!$this->kannPraesidiumHandeln()) {
            throw new \RuntimeException('Nur Fraktionspräsidium darf Kommissionsrollen setzen');
        }

        [$uid, $name] = $this->normalisiereRollenPerson($uid, $name);
        [$von, $bis] = $this->normalisiereGueltigkeit($gueltigVon, $gueltigBis);
        $this->rollenMapper->insert($this->erstelleRolle(self::ROLLE_KOMMISSIONSMITGLIED, $uid, $name, $von, $bis));
    }

    public function istBeschlussSchreibenErlaubt(): bool
    {
        if (!$this->istFraktionssitzungModusAktiv()) {
            return true;
        }

        return $this->kannProtokollfuehrungHandeln();
    }

    private function istFraktionssitzungModusAktiv(): bool
    {
        return $this->config->getAppValue(self::APP_ID, self::CFG_SITZUNGSMODUS, '0') === '1';
    }

    private function kannPraesidiumHandeln(): bool
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return false;
        }

        $uid = $user->getUID();
        return $this->istFraktionsGruppenAdmin()
            || $this->rollenMapper->hasAktiveRolle($uid, self::ROLLE_FRAKTIONSPRAESIDENT)
            || $this->rollenMapper->hasAktiveRolle($uid, self::ROLLE_FRAKTIONSPRAESIDENT_STV);
    }

    private function kannProtokollfuehrungHandeln(): bool
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return false;
        }

        $uid = $user->getUID();
        if (
            $this->rollenMapper->hasAktiveRolle($uid, self::ROLLE_PROTOKOLLFUEHRER)
            || $this->rollenMapper->hasAktiveRolle($uid, self::ROLLE_PROTOKOLLFUEHRER_STV)
        ) {
            return true;
        }

        // Rückwärtskompatibel: alter Einzelwert in App-Konfiguration.
        $legacyUid = $this->config->getAppValue(self::APP_ID, self::CFG_PROTOKOLLFUEHRER_UID, '');
        return $legacyUid !== '' && $legacyUid === $uid;
    }

    private function pruefeBeschlussSchreibrecht(): void
    {
        if (!$this->istBeschlussSchreibenErlaubt()) {
            throw new \RuntimeException('Beschlüsse dürfen im Fraktionssitzungsmodus nur vom Protokollführer erfasst werden');
        }
    }

    private function istFraktionsGruppenAdmin(): bool
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return false;
        }

        $uid = $user->getUID();
        if (method_exists($this->groupManager, 'isAdmin') && $this->groupManager->isAdmin($uid)) {
            return true;
        }

        $gruppenId = $this->config->getAppValue(self::APP_ID, 'nextcloud_gruppe', '');
        if ($gruppenId === '' || !method_exists($this->groupManager, 'get')) {
            return false;
        }

        $gruppe = $this->groupManager->get($gruppenId);
        if ($gruppe === null) {
            return false;
        }

        if (method_exists($gruppe, 'canAdminister') && $gruppe->canAdminister($user)) {
            return true;
        }

        if (method_exists($this->groupManager, 'getSubAdmin')) {
            $subAdmin = $this->groupManager->getSubAdmin();
            if (is_object($subAdmin)) {
                if (method_exists($subAdmin, 'isSubAdminOfGroup') && $subAdmin->isSubAdminOfGroup($user, $gruppe)) {
                    return true;
                }
                if (method_exists($subAdmin, 'isSubAdminofGroup') && $subAdmin->isSubAdminofGroup($user, $gruppe)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalisiereRollenPerson(string $uid, string $name): array
    {
        $uid = trim($uid);
        $name = trim($name);
        if ($uid === '') {
            throw new \InvalidArgumentException('uid ist erforderlich');
        }
        return [$uid, $name];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function normalisiereGueltigkeit(string $gueltigVon, string $gueltigBis): array
    {
        $von = $this->parseZeitpunkt($gueltigVon, false);
        $bis = $this->parseZeitpunkt($gueltigBis, true);

        if ($von !== null && $bis !== null && strcmp($bis, $von) < 0) {
            throw new \InvalidArgumentException('gueltig_bis muss nach gueltig_von liegen');
        }

        return [$von, $bis];
    }

    private function parseZeitpunkt(string $wert, bool $endeDesTages): ?string
    {
        $wert = trim($wert);
        if ($wert === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert) === 1) {
            $wert .= $endeDesTages ? ' 23:59:59' : ' 00:00:00';
        }

        try {
            $zeitpunkt = new \DateTime($wert);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Ungültiges Datumsformat, erwartet z.B. 2026-05-12 oder 2026-05-12 15:00:00');
        }

        return $zeitpunkt->format('Y-m-d H:i:s');
    }

    private function erstelleRolle(
        string $rolleCode,
        string $uid,
        string $name,
        ?string $gueltigVon,
        ?string $gueltigBis
    ): Fraktionsrolle {
        $user = $this->userSession->getUser();
        $gesetztVonUid = $user?->getUID() ?? '';
        $gesetztVonName = '';
        if ($user !== null && method_exists($user, 'getDisplayName')) {
            $gesetztVonName = (string) $user->getDisplayName();
        }

        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');

        $rolle = new Fraktionsrolle();
        $rolle->setUid($uid);
        $rolle->setName($name);
        $rolle->setRolleCode($rolleCode);
        $rolle->setGueltigVon($gueltigVon);
        $rolle->setGueltigBis($gueltigBis);
        $rolle->setGesetztVonUid($gesetztVonUid);
        $rolle->setGesetztVonName($gesetztVonName);
        $rolle->setAktiv(true);
        $rolle->setErstelltAm($jetzt);
        $rolle->setAktualisiertAm($jetzt);

        return $rolle;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRolle(Fraktionsrolle $rolle): array
    {
        return [
            'id' => $rolle->getId(),
            'uid' => $rolle->getUid(),
            'name' => $rolle->getName(),
            'rolleCode' => $rolle->getRolleCode(),
            'gueltigVon' => $rolle->getGueltigVon(),
            'gueltigBis' => $rolle->getGueltigBis(),
            'gesetztVonUid' => $rolle->getGesetztVonUid(),
            'gesetztVonName' => $rolle->getGesetztVonName(),
            'aktiv' => $rolle->getAktiv(),
            'erstelltAm' => $rolle->getErstelltAm(),
            'aktualisiertAm' => $rolle->getAktualisiertAm(),
        ];
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    private function ermittleErlaubteBeschluesse(Geschaeft $geschaeft): array
    {
        $codes = GeschaeftWorkflow::erlaubteBeschluesse($geschaeft->getTyp(), $geschaeft->getStatus());

        $codes = array_values(array_unique($codes));

        return array_map(
            static fn(string $code): array => [
                'code' => $code,
                'label' => self::BESCHLUSS_LABELS[$code] ?? $code,
            ],
            $codes
        );
    }

    private function erstelleAktion(
        int $geschaeftId,
        string $aktionTyp,
        string $aktionCode,
        string $titel,
        string $text,
        bool $entscheidGueltig,
    ): GeschaeftAktion {
        try {
            $this->geschaeftMapper->find($geschaeftId);
        } catch (DoesNotExistException) {
            throw new \InvalidArgumentException('Geschäft nicht gefunden');
        }

        $user = $this->userSession->getUser();
        $uid = $user?->getUID() ?? '';
        $name = '';
        if ($user !== null && method_exists($user, 'getDisplayName')) {
            $name = (string) $user->getDisplayName();
        }

        $aktion = new GeschaeftAktion();
        $aktion->setGeschaeftId($geschaeftId);
        $aktion->setAktionTyp($aktionTyp);
        $aktion->setAktionCode($aktionCode);
        $aktion->setTitel($titel);
        $aktion->setText($text);
        $aktion->setEntscheidGueltig($entscheidGueltig);
        $aktion->setAutorUid($uid);
        $aktion->setAutorName($name);
        $aktion->setErstelltAm((new \DateTime())->format('Y-m-d H:i:s'));

        return $this->aktionMapper->insert($aktion);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAktion(GeschaeftAktion $aktion): array
    {
        return [
            'id' => $aktion->getId(),
            'geschaeftId' => $aktion->getGeschaeftId(),
            'aktionTyp' => $aktion->getAktionTyp(),
            'aktionCode' => $aktion->getAktionCode(),
            'titel' => $aktion->getTitel(),
            'text' => $aktion->getText(),
            'entscheidGueltig' => $aktion->getEntscheidGueltig(),
            'autorUid' => $aktion->getAutorUid(),
            'autorName' => $aktion->getAutorName(),
            'erstelltAm' => $aktion->getErstelltAm(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapZustaendigkeit(GeschaeftZustaendigkeit $zustaendigkeit): array
    {
        return [
            'id' => $zustaendigkeit->getId(),
            'geschaeftId' => $zustaendigkeit->getGeschaeftId(),
            'personKey' => $zustaendigkeit->getPersonKey(),
            'mitgliedExternId' => $zustaendigkeit->getMitgliedExternId(),
            'personName' => $zustaendigkeit->getPersonName(),
            'istHaupt' => $zustaendigkeit->getIstHaupt(),
            'aktiv' => $zustaendigkeit->getAktiv(),
            'erstelltAm' => $zustaendigkeit->getErstelltAm(),
            'aktualisiertAm' => $zustaendigkeit->getAktualisiertAm(),
        ];
    }

    /**
     * @param array<string, mixed> $eintrag
     */
    private function fuelleFraktionsstatus(array &$eintrag, Geschaeft $geschaeft, ?GeschaeftAktion $letzterBeschluss): void
    {
        $letzteFraktionsentscheidungAm = $letzterBeschluss?->getErstelltAm() ?? '';
        $letzteExterneAenderungAm = (string) $geschaeft->getQuelleAktualisiertAm();
        $status = self::ableiteFraktionsstatus($letzteFraktionsentscheidungAm, $letzteExterneAenderungAm);

        $eintrag['fraktionsstatus'] = $status['fraktionsstatus'];
        $eintrag['entscheidungsbedarf'] = $status['entscheidungsbedarf'];
        $eintrag['entscheidungsgrund'] = $status['entscheidungsgrund'];
        $eintrag['letzteFraktionsentscheidungAm'] = $letzteFraktionsentscheidungAm;
        $eintrag['letzteExterneAenderungAm'] = $letzteExterneAenderungAm;
    }

    /**
     * @return array{fraktionsstatus: string, entscheidungsbedarf: bool, entscheidungsgrund: string}
     */
    public static function ableiteFraktionsstatus(string $letzteFraktionsentscheidungAm, string $letzteExterneAenderungAm): array
    {
        $letzteFraktionsentscheidungAm = trim($letzteFraktionsentscheidungAm);
        $letzteExterneAenderungAm = trim($letzteExterneAenderungAm);

        if ($letzteFraktionsentscheidungAm === '') {
            return [
                'fraktionsstatus' => 'offen',
                'entscheidungsbedarf' => true,
                'entscheidungsgrund' => 'kein_beschluss',
            ];
        }

        if ($letzteExterneAenderungAm !== '' && self::istZeitpunktSpaeter($letzteExterneAenderungAm, $letzteFraktionsentscheidungAm)) {
            return [
                'fraktionsstatus' => 'neu_zu_entscheiden',
                'entscheidungsbedarf' => true,
                'entscheidungsgrund' => 'quelle_aktualisiert',
            ];
        }

        return [
            'fraktionsstatus' => 'entschieden',
            'entscheidungsbedarf' => false,
            'entscheidungsgrund' => 'kein_neuer_entscheid',
        ];
    }

    private static function istZeitpunktSpaeter(string $kandidat, string $referenz): bool
    {
        try {
            $kandidatDt = new \DateTime($kandidat);
            $referenzDt = new \DateTime($referenz);
            return $kandidatDt > $referenzDt;
        } catch (\Throwable) {
            // Fallback für bereits normalisierte SQL-Formate.
            return strcmp($kandidat, $referenz) > 0;
        }
    }
}
