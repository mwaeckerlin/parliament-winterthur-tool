<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Extrahiert die strukturierten Budgetdaten aus den städtischen PDF (Teil A + B).
 *
 * Die Textextraktion nutzt zur Laufzeit die reine PHP-Bibliothek
 * smalot/pdfparser (im Image-Build via Composer installiert). Das Parsen ist
 * label-basiert und positionsunabhängig (F89-Toleranz): es sucht nach den
 * Beschriftungen der Bücher, nicht nach festen Zeilen/Spalten, und normalisiert
 * das Schweizer Zahlenformat (Apostroph als Tausendertrenner).
 *
 * HINWEIS: Die exakte Zeilenstruktur der smalot-Extraktion wird im e2e-Kontext
 * gegen die echten, im Repo liegenden Bücher (tests/fixtures/budget/<jahr>/)
 * validiert und dort feinjustiert — nicht anhand erfundener Beispieltexte.
 */
class BudgetBuchParser {
    /**
     * Die Speichergrenze, unter der ein Budgetbuch nicht zu lesen ist. Sie gilt
     * nur während des Lesens; der Betrieb kann sie über
     * `PARLWIN_BUDGET_MEMORY_LIMIT` ändern (siehe `mitSpeichergrenze()`).
     */
    private const SPEICHER_STANDARD = '1024M';

    /**
     * Eine Frankenzahl des Buchs: mindestens zwei Tausendergruppen, getrennt
     * durch Leerzeichen (bis 2020) oder Apostroph (ab 2021). Die zwei Gruppen
     * halten die Millionenangaben des gestuften Erfolgsausweises («1 642.6»)
     * heraus, die im selben Buch weiter vorne stehen.
     */
    private const FRANKEN_ZAHL = "-?\s?\d{1,3}(?:[ '’]\d{3}){2,}";

    // Gehalten wird immer nur EIN Buch: das zuletzt gelesene. Ein geparstes
    // Budgetbuch belegt mehrere hundert Megabyte, und zwei davon zugleich
    // sprengten die Speichergrenze von PHP — der Import eines Jahrgangs endete
    // mit «Allowed memory size exhausted», und ein laufender Sync starb still.
    // Für den Zweck genügt eines: Ein Buch wird mehrfach hintereinander gelesen
    // (Text, Fragmente, Kreditkontrolle, zwei Durchgänge), danach nie wieder.
    private string $gehaltenerPfad = '';
    /** @var object|null das geparste Buch zu `gehaltenerPfad` */
    private ?object $gehaltenesDokument = null;
    /** @var list<list<array{x: float, t: string}>>|null dessen Fragmentzeilen */
    private ?array $gehalteneFragmente = null;

    /**
     * Baut die vollständige Jahresstruktur aus Teil B (+ optional Teil A).
     *
     * @return array<string, mixed>
     */
    public function struktur(string $teilBPfad, ?string $teilAPfad, int $jahr): array {
        return $this->mitSpeichergrenze(function () use ($teilBPfad, $teilAPfad, $jahr): array {
            $teilB = $this->text($teilBPfad);
            $struktur = ['produktegruppen' => $this->parseTeilB($teilB)];
            if ($teilAPfad !== null) {
                $struktur = array_merge($struktur, $this->parseTeilA($this->text($teilAPfad), $jahr));
                // Der Investitionsanhang wird koordinatenbasiert gelesen: Seine
                // Zellen sind oft leer, und im reinen Text ist dann nicht mehr
                // erkennbar, zu welchem Jahr ein Betrag gehört.
                $struktur['investitionen'] = $this->parseInvestitionen($teilAPfad, $jahr);
            }
            return $struktur;
        });
    }

    /**
     * Führt das Lesen eines Budgetbuchs mit einer Speichergrenze aus, die dafür
     * reicht, und stellt danach die Grenze der Installation wieder her.
     *
     * Ein Budgetbuch belegt beim Parsen mehrere hundert Megabyte — mehr, als eine
     * Nextcloud-Installation einer Anfrage standardmässig zugesteht. Gemessen am
     * 22.09.2026 im ausgelieferten Abbild: Das Budget 2027 brach nach 503,8 MB mit
     * «Allowed memory size of 536870912 bytes exhausted» ab, und das Jahr blieb
     * ungelesen. Wie viel es sein darf, entscheidet der Betrieb über
     * `PARLWIN_BUDGET_MEMORY_LIMIT` (Standard 1024M); eine bereits höhere oder
     * ganz aufgehobene Grenze bleibt unangetastet.
     *
     * @template T
     * @param callable():T $arbeit
     * @return T
     */
    public function mitSpeichergrenze(callable $arbeit): mixed {
        $vorher = (string) ini_get('memory_limit');
        $gewuenscht = (string) (getenv('PARLWIN_BUDGET_MEMORY_LIMIT') ?: self::SPEICHER_STANDARD);
        if ($vorher !== '-1' && $this->bytes($gewuenscht) > $this->bytes($vorher)) {
            ini_set('memory_limit', $gewuenscht);
        }
        try {
            return $arbeit();
        } finally {
            // Erst das gelesene Buch freigeben, dann die Grenze zurücksetzen:
            // Liegt der belegte Speicher noch über der alten Grenze, lehnt PHP
            // das Setzen ab und schreibt «Failed to set memory limit» ins Log.
            $this->gehaltenerPfad = '';
            $this->gehaltenesDokument = null;
            $this->gehalteneFragmente = null;
            gc_collect_cycles();
            if ($vorher === '-1' || memory_get_usage(true) < $this->bytes($vorher)) {
                ini_set('memory_limit', $vorher);
            }
        }
    }

    /** Eine PHP-Speicherangabe («512M», «1G», «-1») in Bytes. */
    private function bytes(string $angabe): int {
        $angabe = trim($angabe);
        if ($angabe === '-1') {
            return PHP_INT_MAX;
        }
        $zahl = (int) $angabe;
        return match (strtoupper(substr($angabe, -1))) {
            'G' => $zahl * 1024 * 1024 * 1024,
            'M' => $zahl * 1024 * 1024,
            'K' => $zahl * 1024,
            default => $zahl,
        };
    }

    /** @return array<string, mixed> */
    public function parseNovemberbrief(string $pfad): array {
        // Der Novemberbrief listet Anpassungen je Produktegruppe (Code + neuer
        // Globalkredit/Aufwand). Gleiche Anker wie Teil B, hier nur die Deltas.
        return ['produktegruppen' => $this->parseTeilB($this->text($pfad))];
    }

    /** Extrahiert den reinen Text eines PDF über smalot/pdfparser. */
    private function text(string $pfad): string {
        return (string) $this->dokument($pfad)->getText();
    }

    /**
     * Das geparste PDF. Ein Budgetbuch zu parsen dauert Sekunden, und derselbe
     * Lauf liest jedes Buch mehrfach — als Text für die Produktegruppen, als
     * Fragmente für die Investitionsplanung und noch einmal für die
     * Kreditkontrolle. Innerhalb eines Laufs ändert sich das Buch nicht.
     */
    private function dokument(string $pfad): object {
        if ($this->gehaltenerPfad === $pfad && $this->gehaltenesDokument !== null) {
            return $this->gehaltenesDokument;
        }
        $klasse = 'Smalot\\PdfParser\\Parser';
        if (!class_exists($klasse)) {
            throw new \RuntimeException(
                'PDF-Bibliothek smalot/pdfparser nicht verfügbar — im Image via Composer installieren'
            );
        }
        // Das vorige Buch zuerst freigeben, dann das neue lesen: Andernfalls
        // lägen beide gleichzeitig im Speicher, und genau daran scheiterte der
        // Import.
        $this->gehaltenerPfad = '';
        $this->gehaltenesDokument = null;
        $this->gehalteneFragmente = null;
        /** @var object $parser */
        $parser = new $klasse();
        $dokument = $parser->parseFile($pfad);
        $this->gehaltenerPfad = $pfad;
        $this->gehaltenesDokument = $dokument;
        return $dokument;
    }

    /**
     * Parst die Produktegruppen aus Teil B.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseTeilB(string $text): array {
        $zeilen = $this->zeilen($text);

        // 1. Departement je Produktegruppe aus dem Inhaltsverzeichnis. Nur dort
        //    stehen die Einträge „<Name> (<Code>) …… <Seite>“ (Code, dann
        //    Füllpunkte/Leerraum, dann Seitenzahl am Zeilenende) unter einem
        //    „Departement <Name>“-Kopf — so trifft die Zuordnung nicht auf einen
        //    Prosasatz, der zufällig „Departement …“ enthält.
        $codeZuDept = [];
        $codeZuName = [];
        $tocDept = '';
        $imToc = false;
        foreach ($zeilen as $zeile) {
            if (!$imToc) {
                if (preg_match('/^Inhaltsverzeichnis/u', $zeile)) {
                    $imToc = true;
                }
                continue;
            }
            // Das Inhaltsverzeichnis endet, sobald das erste Kapitel beginnt
            // (laufende Kopfzeile „… Einleitung Produktegruppe …“).
            if (str_contains($zeile, 'Einleitung Produktegruppe')) {
                break;
            }
            // TOC-Eintrag: „<Name> (<Code>) …Füllpunkte… <Seite>“. Die Füllpunkte
            // unterscheiden ihn von laufenden Kopf-/Fusszeilen, die ebenfalls
            // „(Code)“ enthalten, aber keine Punkte. Der vollständige Name steht
            // hier auf einer Zeile — im Kapitel selbst kann die Überschrift auf
            // zwei Zeilen umbrechen.
            if (preg_match('/^(.+?)\s*\((\d{3})\)\s*\.{2,}/u', $zeile, $m)) {
                $codeZuDept[$m[2]] = $tocDept;
                $codeZuName[$m[2]] = trim($m[1]);
                continue;
            }
            // Departements-/Bereichskopf (auch ohne „Departement“-Präfix, z.B.
            // „Behörden und Stadtkanzlei“); der Hinweis in Klammern zählt nicht.
            // Eine umgebrochene TOC-Zeile aus nur Füllpunkten und einer Seitenzahl
            // (z.B. „……… 175“) ist KEIN Departementsname — ein Name enthält immer
            // Buchstaben. Ohne diese Prüfung landete eine solche Zeile als
            // Departement der nächsten Produktegruppe (Screenshot-Bug PG 480).
            if (!str_starts_with($zeile, '(') && preg_match('/\p{L}/u', $zeile)) {
                $tocDept = trim(preg_replace('/^Departement\s+/u', '', $zeile));
            }
        }

        // 2. Produktegruppen aus den Kapiteln. Die Überschrift ist „<Name>
        //    (<Code>)“ ohne Tabulator — laufende Kopf-/Fusszeilen enthalten einen
        //    Tab (und Zusätze wie „Einleitung/Zum Beschluss … (Code)“) und werden
        //    so nicht als Überschrift gewertet, fliessen aber in die Zahlensuche.
        // Wie oft jede «Produkt <N> <Name>»-Zeile im Buch vorkommt. Ein echtes
        // Produkt steht mindestens zweimal da (Informationsteil und der Anhang
        // «Gliederung von Budget und Jahresrechnung»); ein umgebrochener Satz, der
        // zufällig so beginnt («Produkt 2 ist im Durchschnitt»), nur einmal. Das
        // trägt auch klein geschriebene Produktnamen wie «elektronischer
        // Arbeitsplatz» (222), die eine Grossschreibungs-Regel verwerfen würde.
        $produktzeilen = [];
        foreach ($zeilen as $zeile) {
            if (preg_match('/^Produkt\s+(\d+)\s+(\S.*)$/u', $zeile, $pz)) {
                $schluessel = $pz[1] . '|' . mb_strtolower(trim($pz[2]));
                $produktzeilen[$schluessel] = ($produktzeilen[$schluessel] ?? 0) + 1;
            }
        }

        $nachCode = [];
        $reihenfolge = [];
        $aktuellCode = null;
        $auftragModus = false;
        $textFeld = null;
        $zvModus = false;
        $kostenModus = false;
        $leistungenAktiv = false;
        $leistungBullet = false;
        $produktKostenModus = false;
        // Das Produkt, dessen Abschnitt gerade gelesen wird. Ohne diesen Index
        // landeten Leistungen und Kostentabelle beim ZULETZT ANGELEGTEN Produkt:
        // Weil der Anhang am Buchende alle Produkte einer Gruppe schon aufführt,
        // sammelten sich im Informationsteil sämtliche Tabellen beim letzten
        // Produkt (Budget 2026, PG 622: fünf Produkte leer, eines mit 24 Zeilen).
        $produktIndex = null;
        foreach ($zeilen as $zeile) {
            // Eine Überschrift, die im Satz umbricht, trägt an der Bruchstelle einen
            // Tabulator: «Subventionsverträge und →Beiträge an Dritte (157)» (Buch
            // 2024). Sie zählt trotzdem als Kapitelanfang — dann aber nur, wenn sie
            // dem Namen aus dem Inhaltsverzeichnis WÖRTLICH entspricht, damit keine
            // laufende Kopfzeile («Präsidiales →Informationsteil Bibliotheken (155)»)
            // durchrutscht. Ohne das lief das Kapitel 155 bis 158 weiter und
            // schluckte die Beträge von 157.
            if (
                preg_match('/^(.+?)\s*\((\d{3})\)\s*$/u', $zeile, $m)
                && isset($codeZuDept[$m[2]])
                && $this->istGruppenUeberschrift($m[1], $codeZuName[$m[2]] ?? null, str_contains($zeile, "\t"))
            ) {
                $code = $m[2];
                if (!isset($nachCode[$code])) {
                    // Vollständiger Name aus dem Inhaltsverzeichnis; die Body-
                    // Überschrift ($m[1]) nur als Rückfall.
                    $name = $codeZuName[$code] ?? trim($m[1]);
                    $nachCode[$code] = $this->leereGruppe($name, $code, $codeZuDept[$code]);
                    $reihenfolge[] = $code;
                }
                $aktuellCode = $code;
                $auftragModus = false;
                $textFeld = null;
                $zvModus = false;
                $kostenModus = false;
                $leistungenAktiv = false;
                $produktKostenModus = false;
                $produktIndex = null;
                continue;
            }
            if ($aktuellCode === null) {
                continue;
            }
            // Parlamentarische Zielvorgaben (F109): den ganzen Block bis zur
            // Globalkredit-Tabelle / zum Informationsteil sammeln; die Feinauswertung
            // (Ziele, Messgrössen, Wertspalten) folgt nach dem Loop, weil ein Ziel
            // über mehrere Zeilen und Seiten läuft.
            if (str_starts_with($zeile, 'Parlamentarische Zielvorgaben')) {
                $zvModus = true;
                continue; // die (auf jeder Seite wiederholte) Kopfzeile nicht sammeln
            }
            if ($zvModus) {
                if (
                    str_starts_with($zeile, 'Globalkredit')
                    || str_starts_with($zeile, 'Nettokosten / Globalkredit')
                    || str_contains($zeile, 'Informationsteil')
                ) {
                    $zvModus = false;
                    // fällt durch zur normalen Verarbeitung dieser Zeile
                } else {
                    // Laufende Kopf-/Fusszeilen und Deko überspringen; alles andere
                    // (Ziele, Beschreibung, Messgrössen, Wertzeilen) sammeln.
                    if (
                        !str_starts_with($zeile, 'Stadt Winterthur / Budget')
                        && !str_starts_with($zeile, '▼')
                        && !str_contains($zeile, 'Zum Beschluss')
                        && !preg_match('/\(\d{3}\)\s*$/u', $zeile)
                    ) {
                        $nachCode[$aktuellCode]['_zvZeilen'][] = $zeile;
                    }
                    continue;
                }
            }
            // Kostentabelle im Informationsteil (F109): ihre Kopfzeile trägt die
            // «in%»-Spalten und unterscheidet sie so von der Beschluss-Globalkredit-
            // Zeile. Die Zeilen (Personalkosten, Sachkosten …) werden für die
            // Antrags-Aufteilung gesammelt.
            if (preg_match('/^Nettokosten \/ Globalkredit\s+Ist\s+\d+\s+in%/u', $zeile)) {
                $kostenModus = true;
                continue;
            }
            if ($kostenModus) {
                if (
                    str_starts_with($zeile, 'Stellenplan')
                    || str_starts_with($zeile, 'Kostendeckungsgrad')
                    || str_starts_with($zeile, 'Erläuterungen zum Stellenplan')
                ) {
                    $kostenModus = false;
                } elseif (
                    !str_starts_with($zeile, 'Stadt Winterthur / Budget')
                    && !str_starts_with($zeile, '▼')
                    && !str_contains($zeile, 'Informationsteil')
                    && !preg_match('/\(\d{3}\)\s*$/u', $zeile)
                ) {
                    $nachCode[$aktuellCode]['_kostenZeilen'][] = $zeile;
                }
                // KEIN continue: die Zeile fällt zu fuelleZeile durch, damit Aufwand
                // und Ertrag (Total effektive Kosten/Erlöse) weiterhin gelesen werden.
            }
            // Auftragstext: der Block zwischen der Überschrift „Auftrag“ und
            // „Rechtsgrundlagen …“ (Mission der Produktegruppe, Info in Tab 1).
            if ($zeile === 'Auftrag' && $nachCode[$aktuellCode]['auftrag'] === '') {
                $auftragModus = true;
                continue;
            }
            if ($auftragModus) {
                if (str_starts_with($zeile, 'Rechtsgrundlagen')) {
                    $auftragModus = false;
                } else {
                    $nachCode[$aktuellCode]['auftrag'] = trim(
                        $nachCode[$aktuellCode]['auftrag'] . ' ' . $zeile
                    );
                }
                continue;
            }
            // Erläuterungs-/Begründungs-Abschnitte (F80): Fliesstext zwischen einer
            // Abschnittsüberschrift und der nächsten Struktur-/Abschnittszeile.
            $startFeld = $this->erlaeuterungsFeld($zeile);
            if ($startFeld !== null) {
                $textFeld = $startFeld;
                continue;
            }
            if ($textFeld !== null) {
                if ($this->istAbschnittsende($zeile)) {
                    $textFeld = null;
                    // fällt durch zur normalen Verarbeitung dieser Zeile
                } else {
                    $nachCode[$aktuellCode][$textFeld] = $this->erlaeuterungAnfuegen(
                        (string) $nachCode[$aktuellCode][$textFeld],
                        $zeile
                    );
                    continue;
                }
            }
            // Produkt-Überschrift „Produkt <N> <Name>“ (Produkte als Information,
            // F80). Leistungstext wie „… (zu Gunsten Produkt 1)“ beginnt nicht am
            // Zeilenanfang mit „Produkt <Ziffer> <Text>“ und wird nicht getroffen.
            if (
                preg_match('/^Produkt\s+(\d+)\s+(\S.*)$/u', $zeile, $pm)
                && $this->istProduktUeberschrift(
                    $pm[2],
                    $produktzeilen[$pm[1] . '|' . mb_strtolower(trim($pm[2]))] ?? 0
                )
            ) {
                $nummer = (int) $pm[1];
                // Der Anhang («Gliederung von Budget und Jahresrechnung») listet am
                // Buchende jede Produktegruppe mit ihren Produkten noch einmal ohne
                // Zahlen. Ein Produkt mit schon vorhandener Nummer wird darum nicht
                // erneut angelegt (sonst erschiene es doppelt, das zweite ohne Werte).
                $produktIndex = null;
                foreach ($nachCode[$aktuellCode]['produkte'] as $i => $vorhanden) {
                    if ($vorhanden['nummer'] === $nummer) { $produktIndex = $i; break; }
                }
                if ($produktIndex === null) {
                    $nachCode[$aktuellCode]['produkte'][] = [
                        'nummer' => $nummer,
                        'name' => trim($pm[2]),
                        'nettokosten' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0],
                        'kostentabelle' => [],
                        'leistungen' => [],
                    ];
                    $produktIndex = \count($nachCode[$aktuellCode]['produkte']) - 1;
                } else {
                    // Im Kapitel bricht die Überschrift im Satz um («Produkt 1
                    // Ausführung von Vermessungsaufträgen sowie Unterhalt und» /
                    // «Erneuerung des Vermessungswerks»), im Anhang steht der
                    // ganze Name auf einer Zeile. Setzt der spätere Name den
                    // gelesenen fort, gewinnt der vollständige — sonst stand der
                    // Name mitten im Satz abgeschnitten in der Anwendung.
                    $bisher = (string) $nachCode[$aktuellCode]['produkte'][$produktIndex]['name'];
                    $jetzt = trim($pm[2]);
                    if ($bisher !== $jetzt && str_starts_with($jetzt, $bisher)) {
                        $nachCode[$aktuellCode]['produkte'][$produktIndex]['name'] = $jetzt;
                    }
                }
                $leistungenAktiv = false;
                $produktKostenModus = false;
                continue;
            }
            // Leistungen eines Produkts (Information, F110): die Aufzählung zwischen
            // «Leistungen» und der Nettokosten-Tabelle des Produkts.
            if ($zeile === 'Leistungen' && $nachCode[$aktuellCode]['produkte'] !== []) {
                $leistungenAktiv = true;
                $leistungBullet = false;
                continue;
            }
            if ($leistungenAktiv) {
                if (
                    str_starts_with($zeile, 'Nettokosten')
                    || str_starts_with($zeile, 'Operative Ziele')
                    || str_starts_with($zeile, 'Leistungsmengen')
                    || str_starts_with($zeile, 'Kostendeckungsgrad')
                ) {
                    $leistungenAktiv = false;
                    // fällt durch zur normalen Verarbeitung dieser Zeile
                } else {
                    // Ein Bullet-Glyph am Zeilenanfang (smalot liefert z.B. U+F0A7)
                    // markiert eine NEUE Leistung und wird entfernt. Eine Zeile ohne
                    // Glyph ist die Fortsetzung der vorigen (Umbruch im Buch) und wird
                    // angehängt. Steht der Glyph allein auf der Zeile, folgt der Text
                    // erst danach — das Kennzeichen überträgt den Neubeginn auf die nächste Zeile.
                    $neu = preg_match('/^[^\p{L}\d]/u', $zeile) === 1;
                    $text = trim((string) preg_replace('/^[^\p{L}\d]+/u', '', $zeile));
                    $ziel = $produktIndex ?? \count($nachCode[$aktuellCode]['produkte']) - 1;
                    $liste = &$nachCode[$aktuellCode]['produkte'][$ziel]['leistungen'];
                    if ($text === '') {
                        $leistungBullet = true;
                    } elseif ($neu || $leistungBullet || $liste === []) {
                        $liste[] = $text;
                        $leistungBullet = false;
                    } else {
                        $liste[\count($liste) - 1] = trim($liste[\count($liste) - 1] . ' ' . $text);
                    }
                    unset($liste);
                    continue;
                }
            }
            // Produkt-Kostentabelle: die Kopfzeile „Nettokosten Ist … Soll …“ eines
            // Produkts (nicht die PG-Globalkredit-Kopfzeile mit „Globalkredit“/„in%“)
            // startet die Sammlung der Kostenzeilen (Kosten, Erlös, Nettokosten,
            // Kostendeckungsgrad) des zuletzt gelesenen Produkts — als Budgetzahlen in
            // der Produkt-Karte (F110).
            if (
                $nachCode[$aktuellCode]['produkte'] !== []
                && str_starts_with($zeile, 'Nettokosten')
                && !str_contains($zeile, 'Globalkredit')
                && !str_contains($zeile, 'in%')
                && preg_match('/\b(Ist|Soll)\b/u', $zeile)
            ) {
                $produktKostenModus = true;
                // Manchmal klebt die erste Kostenzeile an der Kopfzeile:
                // «Nettokosten Ist 2024 Soll 2025 Soll 2026 Kosten 689'041 660'884»
                // steht im Budget 2026 (PG 855) als eine einzige Zeile. Der Rest
                // hinter der letzten Jahresangabe ist dann diese Kostenzeile.
                // Alle Spaltenköpfe («Ist 2024 Soll 2025 Soll 2026») wegschneiden;
                // was danach übrig bleibt, ist die angehängte Kostenzeile — bei
                // einer Kopfzeile ohne Anhang bleibt nichts übrig.
                $rest = trim((string) preg_replace(
                    '/^.*?(?:\b(?:Ist|Soll|Plan)\s+20\d\d\s*)+/u',
                    '',
                    $zeile
                ));
                if ($rest === '') {
                    continue;
                }
                $zeile = $rest;
            }
            if ($produktKostenModus) {
                // Eine Kostenzeile ist „<Label> <Zahl> [<Zahl> [<Zahl>]]“ (der Label
                // darf ein „in %“ tragen); jede andere Zeile beendet die Tabelle.
                // Ein neues Produkt hat noch keine Vergangenheit: Dann stehen nur
                // ein oder zwei Werte da, und sie füllen die Spalten von rechts —
                // der letzte Wert ist immer das Budgetjahr (Budget 2026: PG 855
                // ohne Ist-Werte, PG 731 nur mit dem Soll des Budgetjahres).
                // Eine Kopfzeile («Operative Ziele Ist 2024 Soll 2025 Soll 2026»)
                // endet ebenfalls auf einer Jahreszahl und ist keine Kostenzeile.
                if (
                    !preg_match('/\b(Ist|Soll|Plan)\s+20\d\d\b/u', $zeile)
                    && preg_match('/^(\p{L}.*?)\s+((?:-?[\d’\'.,]+\s+){0,2}-?[\d’\'.,]+)\s*$/u', $zeile, $km)
                ) {
                    $werte = $this->zahlen($km[2]);
                    while (\count($werte) > 0 && \count($werte) < 3) {
                        array_unshift($werte, 0);
                    }
                    $label = trim($km[1]);
                    // Die Kostentabelle eines Produkts führt genau vier Zeilen. Ein
                    // Hinweis darunter endet ebenfalls auf einer Zahl («Die Produkte
                    // wurden Anfang 2023 neu definiert. Die Beträge der Rechnung
                    // 2022 …», Stadtentwicklung 2024) und stand sonst als fünfte
                    // Zeile mit den Werten 0/0/2023 in der Tabelle.
                    $istKostenzeile = preg_match(
                        '/^(?:Kosten|Erlös|Nettokosten|Kostendeckungsgrad in %)\**$/u',
                        $label
                    ) === 1;
                    if ($istKostenzeile && \count($werte) >= 3) {
                        $ziel = $produktIndex ?? \count($nachCode[$aktuellCode]['produkte']) - 1;
                        $nachCode[$aktuellCode]['produkte'][$ziel]['kostentabelle'][] = [
                            'label' => $label,
                            'werte' => [$werte[0], $werte[1], $werte[2]],
                        ];
                        if ($label === 'Nettokosten') {
                            $nachCode[$aktuellCode]['produkte'][$ziel]['nettokosten'] = [
                                'ist' => $werte[0], 'sollVorjahr' => $werte[1], 'soll' => $werte[2],
                            ];
                        }
                        continue;
                    }
                }
                $produktKostenModus = false;
                // fällt durch zur normalen Verarbeitung dieser Zeile
            }
            // Die Kopfzeile der Globalkredit-Tabelle sagt, wie viele Spalten die
            // folgende Wertzeile hat: «Globalkredit  Ist 2024  Soll 2025  Soll
            // 2026  Plan 2027  Plan 2028  Plan 2029» sind sechs, bei den
            // Eigenwirtschaftsbetrieben stehen nur die ersten drei. Ohne diese
            // Zahl liesse sich eine Zeile mit leeren Zellen nicht zuordnen.
            if (preg_match('/^Globalkredit\s+(?:Ist|Soll|Plan)\s+20\d\d/u', $zeile)) {
                $nachCode[$aktuellCode]['_gkSpalten'] = preg_match_all('/\b(?:Ist|Soll|Plan)\s+20\d\d/u', $zeile);
            }
            $this->fuelleZeile($nachCode[$aktuellCode], $zeile);
        }
        $gruppen = [];
        foreach ($reihenfolge as $code) {
            $g = $nachCode[$code];
            $g['zielvorgaben'] = $this->parseZielvorgabenBlock($g['_zvZeilen']);
            $g['kostenzeilen'] = $this->parseKostenzeilen($g['_kostenZeilen']);
            unset($g['_zvZeilen'], $g['_kostenZeilen'], $g['_gkSpalten']);
            $gruppen[] = $g;
        }
        return $gruppen;
    }

    /**
     * Wertet den gesammelten Zielvorgaben-Block einer Produktegruppe aus (F109,
     * WoV). Struktur im Buch: nummerierte Ziele («2 Kundenorientierung …»), je Ziel
     * eine Beschreibung und eine oder mehrere Messgrössen, jede mit einer Wertzeile
     * aus sechs Jahresspalten (Ist Vorjahr, Soll Vorjahr, Soll aktuell, 3× Plan).
     * Das Parlament entscheidet über die Spalte «Soll aktuell» (Index 2). Rein
     * qualitative Ziele (Wertzeile «erfüllt / zu erfüllen …») tragen keine Zahl und
     * werden nicht als beantragbare Zielvorgabe erfasst.
     *
     * @param list<string> $zeilen
     * @return list<array<string, mixed>>
     */
    private function parseZielvorgabenBlock(array $zeilen): array {
        $ziele = [];
        $goalNr = 0;
        $goalTitel = '';
        $titelOffen = false;   // direkt nach dem Zieltitel: eine Wortzeile ist Titel-Fortsetzung
        $mgBuf = null;         // Messgrösse-Label ab «Messgrösse:» bis zur Wertzeile
        $letzteTextzeile = ''; // Rückfall, wenn kein «Messgrösse:» vorausging
        // Wertzeile: sechs Spalten am Zeilenende. Eine Spalte trägt eine Zahl
        // (Schweizer Format) oder einen Platzhalter — «N/A», wo in diesem Jahr
        // nicht gemessen wird, «--» oder «-», wo es nichts zu messen gibt. Dazu
        // hängen Fussnotenzeichen an den Werten: «N/A** 188*** 188 N/A***» ist
        // die Wirtschaftlichkeit der Informatikdienste, deren Soll 188 beträgt.
        // Ohne die Platzhalter fiel die ganze Zielvorgabe weg.
        // Ein Wert ist eine Zahl im Schweizer Format — auch mit Prozentzeichen
        // («100%»), als Spanne («1 bis 2») oder als Vergleich («>22000», «> 50%»),
        // wie das Buch seine Sollwerte schreibt — oder ein Platzhalter, wo nichts
        // gemessen wird. Fussnotenzeichen hängen daran, im Buch bis zu sechs
        // («17*****»); mit der früheren Grenze von drei fiel die ganze Zeile durch,
        // und die Messgrösse «Anzahl Unfallschwerpunkte» hatte keine Vorgabe.
        // Ein Minus zählt als Vorzeichen, wenn es an der Ziffer klebt («-2.64»);
        // mit Abstand davor ist es der Platzhalter einer leeren Spalte.
        $zahl = "[-−]?[\\d’']+(?:[.,]\\d+)?(?:\\s?%)?";
        // Dazu die zusammengesetzten Formen des Buchs: ein Verhältnis («9 / 19»,
        // auch wenn die zweite Zahl in der Nachbarzelle steht und nur «32 /»
        // übrig bleibt), eine Spanne («1 bis 2») und eine Fussnote in Klammern
        // («12'300 1)»).
        $wert = "(?:[<>≤≥]\\s?)?(?:" . $zahl . "(?:\\s*\\/\\s*(?:" . $zahl . ")?)?"
            . "(?:\\s+bis\\s+" . $zahl . ")?|N\\/A|n\\.a\\.|--|—|-)(?:\\**|\\s?\\d\\))";
        // Vier bis sechs Spalten: Eine Zielvorgabe, die das Buch erst mit dem
        // Budgetjahr einführt, steht nur in den letzten vier Spalten (Soll
        // Budgetjahr und die drei Planjahre) — «… Langzeitabteilungen
        // Sozialhilfe  69 69 69 69» (Buch 2027, Sozial- und Erwachsenenhilfe).
        // Die leeren Spalten fehlen LINKS, wie bei den Stellen im Buch 2026.
        $wertzeile = '/^(.*?)((?:' . $wert . '\s+){3,5}' . $wert . ')\s*$/u';
        foreach ($zeilen as $zeile) {
            // Neues Ziel: Zahl + Leerzeichen + Buchstabe (eine Wertzeile beginnt mit
            // einer Zahl gefolgt von einer Zahl und wird hier nicht getroffen).
            // Steht ein Messgrössen-Abschnitt offen, ist eine Zeile mit führender
            // Zahl seine Fortsetzung, kein neues Ziel: «Vergleich Tarif H4 in %
            // des Mittelwerts der» / «10 grössten CH Städte.» (Buch 2027,
            // Stadtwerk) eröffnete sonst ein Ziel 10 und nahm der folgenden
            // Wertzeile ihren Namen — im Werkzeug stand eine Vorgabe ohne
            // Bezeichnung.
            // Dasselbe gilt für eine Fortsetzung im Rückfall-Puffer: «… in dünn
            // besiedeltes Gebiet (Land) innerhalb» / «15 Minuten» (Buch 2024,
            // Schutz und Intervention) eröffnete ein Ziel 15 und nahm der
            // folgenden Wertzeile ihren Namen. Ein echter Zieltitel steht immer
            // nach einer Wertzeile oder nach dem Tabellenkopf — dort ist der
            // Puffer leer.
            if ($mgBuf === null && $letzteTextzeile === ''
                && preg_match('/^(\d{1,2})\s+(\p{L}.*)$/u', $zeile, $gm)) {
                $goalNr = (int) $gm[1];
                $goalTitel = trim($gm[1] . ' ' . $gm[2]);
                $titelOffen = true;
                $mgBuf = null;
                $letzteTextzeile = '';
                continue;
            }
            // Titel-Fortsetzung: das Buch bricht lange Titel um — die unmittelbar
            // folgende einzelne Wortzeile («… zentrales» / «Personalmanagement»)
            // gehört noch zum Titel. Ein Doppelpunkt (Struktur-Label) zählt nicht.
            if ($titelOffen && preg_match('/^\p{L}[^\s:]*$/u', trim($zeile))) {
                $goalTitel = trim($goalTitel . ' ' . trim($zeile));
                continue;
            }
            $titelOffen = false;
            // «Messgrösse:» UND «Messung / Bewertung:» starten je einen Label-Abschnitt.
            // Der Abschnitt, der unmittelbar vor der Wertzeile steht, liefert das Label —
            // so gewinnt bei Ziel 1 (wo «Messgrösse:» vor «Messung / Bewertung:» steht)
            // die kurze Messgrösse statt der langen Beschreibung.
            // Der Plural steht in Produktegruppen mit mehreren Kennzahlen je Ziel
            // («Messgrössen:» bei Einkauf und Logistik Winterthur); ohne ihn wurde
            // die ganze Zielbeschreibung zum Namen der Messgrösse.
            if (str_starts_with($zeile, 'Messgrösse:') || str_starts_with($zeile, 'Messgrössen:')) {
                $mgBuf = trim(substr($zeile, \strlen(str_starts_with($zeile, 'Messgrössen:') ? 'Messgrössen:' : 'Messgrösse:')));
                $letzteTextzeile = '';
                continue;
            }
            if (str_starts_with($zeile, 'Messung / Bewertung:')) {
                $mgBuf = trim(substr($zeile, \strlen('Messung / Bewertung:')));
                $letzteTextzeile = '';
                continue;
            }
            if (preg_match($wertzeile, $zeile, $wm)) {
                // Fussnotenzeichen gehören nicht zum Wert: «50*» ist 50.
                // Nach dem Wertmuster zerlegen statt an jedem Leerzeichen: eine
                // Spanne («1 bis 2») und ein Vergleich («> 50%») tragen selbst
                // Leerzeichen und sind trotzdem EIN Wert.
                preg_match_all('/' . $wert . '/u', trim($wm[2]), $einzeln);
                $werte = array_map(static fn ($w) => rtrim(trim($w), '*'), $einzeln[0]);
                // Fehlende Spalten stehen links (siehe Kommentar am Muster).
                $werte = array_pad($werte, -6, '');
                // Beantragbar ist eine Vorgabe nur, wenn im Soll des Budgetjahres
                // wirklich eine Zahl steht — ein «N/A» dort ist nichts, worüber
                // das Parlament beschliessen könnte.
                $sollIstZahl = isset($werte[2]) && preg_match("/^[\\d’']+(?:[.,]\\d+)?$/u", $werte[2]) === 1;
                if (\count($werte) === 6 && $goalNr > 0 && $sollIstZahl) {
                    $vorne = trim($wm[1]);
                    $label = $vorne !== ''
                        ? $vorne
                        : (($mgBuf !== null && $mgBuf !== '') ? $mgBuf : $letzteTextzeile);
                    // Führende Aufzählungs-/Störglyphen (z.B. ein Bullet aus dem PDF)
                    // vor der Messgrösse entfernen.
                    $saeubern = static fn (string $s): string => trim(
                        (string) preg_replace('/^[^\p{L}\d]+/u', '', trim($s))
                    );
                    $label = $saeubern($label);
                    // Steht vor den Werten nur ein Fussnotenzeichen («* 5 5 5 5 5»,
                    // Buch 2024, Schutz und Intervention), bleibt nach dem Säubern
                    // nichts übrig — dann gilt der Text der Zeilen davor, sonst
                    // stünde die Vorgabe ohne Bezeichnung da.
                    if ($label === '') {
                        $label = $saeubern(
                            ($mgBuf !== null && $mgBuf !== '') ? $mgBuf : $letzteTextzeile
                        );
                    }
                    $ziele[] = [
                        'zielNummer' => $goalNr,
                        'zielTitel' => $goalTitel,
                        'messgroesse' => $label,
                        'werte' => $werte,
                        'soll' => $werte[2],
                    ];
                }
                // Nach einer Wertzeile ist kein Abschnitt mehr offen: die nächste
                // Textzeile gehört zum Rückfall (eine zweite Messgrösse ohne
                // «Messgrösse:»-Kopf), und die nächste Zahl am Zeilenanfang darf
                // wieder ein Ziel eröffnen. Das gilt auch, wenn die Zeile keine
                // beantragbare Vorgabe ergab — sonst hinge ihr Text am nächsten.
                $mgBuf = null;
                $letzteTextzeile = '';
                continue;
            }
            // Gewöhnliche Textzeile: im offenen Abschnitt ans Messgrösse-Label anhängen,
            // sonst als (mehrzeiligen) Rückfall akkumulieren.
            $text = trim($zeile);
            if ($text === '') {
                continue;
            }
            if ($mgBuf !== null) {
                $mgBuf = trim($mgBuf . ' ' . $text);
            } else {
                $letzteTextzeile = trim($letzteTextzeile . ' ' . $text);
            }
        }
        return $ziele;
    }

    /** @return array<string, mixed> */
    /**
     * Ob nach «Produkt <N> » wirklich ein Produktname folgt. Ein umgebrochener
     * Satz kann genauso beginnen: Im Budget 2026 endet der Leistungstext von PG
     * 651, Produkt 3 mit «… werden im | Produkt 4 abgebildet.» — als Überschrift
     * gelesen, bekäme Produkt 4 die Kostentabelle von Produkt 3. Ein Produktname
     * beginnt gross, endet ohne Satzzeichen und ist kurz.
     */
    private function istProduktUeberschrift(string $name, int $vorkommen): bool {
        $name = trim($name);
        // Die Grenze trennt eine Überschrift vom Fliesstext. Sie lag bei 80 Zeichen
        // und schnitt damit echte Produktnamen weg: «Ausführung von
        // Vermessungsaufträgen sowie Unterhalt und Erneuerung des Vermessungswerks»
        // (Geomatik, 88 Zeichen) stand nur im Anhang vollständig, und das Kapitel
        // lieferte die abgebrochene erste Zeile.
        if ($name === '' || mb_strlen($name) > 120) {
            return false;
        }
        // Ein Satzzeichen am Ende verrät den Satzrest («… Produkt 4 abgebildet.»).
        if (preg_match('/[.;:]\s*$/u', $name)) {
            return false;
        }
        // Sonst zählt, ob der Name im Buch mehrfach als Produktzeile steht; nur
        // ein einzelnes Vorkommen mit Kleinschreibung ist verdächtig («Produkt 2
        // ist im Durchschnitt»).
        return $vorkommen > 1 || (bool) preg_match('/^\p{Lu}/u', $name);
    }

    /**
     * Ob eine Zeile «<Text> (<Code>)» wirklich die Überschrift einer
     * Produktegruppe ist. Ein Fliesstext kann zufällig auf eine Gruppennummer
     * enden — im Budget 2026 bricht die Begründung der Stadtkanzlei so um, dass
     * eine Zeile mit «… in die PG Stadtrat (805)» schliesst. Wer sie als
     * Überschrift liest, schreibt alles Folgende der falschen Gruppe gut.
     *
     * Eine Überschrift trägt den Namen aus dem Inhaltsverzeichnis (Umbrüche im
     * Buch können ihn kürzen, darum genügt der Anfang). Ist kein Name bekannt,
     * entscheidet die Form: eine Überschrift ist kurz und trägt keinen Satzbau.
     */
    /**
     * @param bool $woertlich verlangt Gleichheit mit dem Namen aus dem Inhalts-
     *                        verzeichnis statt blosser Übereinstimmung im Anfang.
     *                        Gilt für Zeilen mit Tabulator: dort steht neben einer
     *                        umgebrochenen Überschrift auch jede laufende Kopfzeile.
     */
    private function istGruppenUeberschrift(string $text, ?string $nameAusToc, bool $woertlich = false): bool {
        $normal = static fn (string $s): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
        $kandidat = $normal($text);
        if ($kandidat === '') {
            return false;
        }
        if ($nameAusToc !== null && $nameAusToc !== '') {
            $name = $normal($nameAusToc);
            if ($woertlich) {
                return $kandidat === $name;
            }
            return $kandidat === $name || str_starts_with($name, $kandidat) || str_starts_with($kandidat, $name);
        }
        // Ohne Namen aus dem Inhaltsverzeichnis bleibt nur die Form der Zeile — eine
        // Zeile mit Tabulator ist dann immer eine laufende Kopfzeile.
        return !$woertlich
            && mb_strlen($kandidat) <= 60
            && !preg_match('/[.;:]|\bder\b|\bdie\b|\bdas\b|\bin\b|\bfür\b/u', $kandidat);
    }

    private function leereGruppe(string $name, string $code, string $departement): array {
        $leer = ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0, 'plan1' => 0, 'plan2' => 0, 'plan3' => 0];
        return [
            'code' => $code,
            'name' => $name,
            'departement' => $departement,
            'globalkredit' => $leer,
            'aufwand' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0],
            'ertrag' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0],
            'stellen' => ['ist' => 0, 'sollVorjahr' => 0, 'soll' => 0],
            'auftrag' => '',
            'erlaeuterungStellen' => '',
            'begruendungAbweichung' => '',
            'begruendungFap' => '',
            'massnahmen' => '',
            'produkte' => [],
            // Parlamentarische Zielvorgaben (WoV, F109): je Messgrösse ein Eintrag
            // mit Ziel-Nummer, Ziel-Titel, Messgrösse und den sechs Jahresspalten.
            'zielvorgaben' => [],
            // Kostenzeilen der Kostentabelle im Informationsteil (F109): je Zeile
            // ein Label und der Soll-Wert des Budgetjahres — für die Antrags-Aufteilung.
            'kostenzeilen' => [],
            // Sammelpuffer; nach dem Body-Loop geparst und wieder entfernt.
            '_zvZeilen' => [],
            '_kostenZeilen' => [],
        ];
    }

    /**
     * Ordnet eine Abschnittsüberschrift dem Textfeld zu, in das ihr Fliesstext
     * gesammelt wird (F80: Erläuterungen/Begründungen aus dem Buch). null, wenn
     * die Zeile keine solche Überschrift ist.
     */
    private function erlaeuterungsFeld(string $zeile): ?string {
        if (str_starts_with($zeile, 'Erläuterungen zum Stellenplan')) {
            return 'erlaeuterungStellen';
        }
        if (str_starts_with($zeile, 'Begründung Abweichung')) {
            return 'begruendungAbweichung';
        }
        if (str_starts_with($zeile, 'Begründung FAP')) {
            return 'begruendungFap';
        }
        if (str_starts_with($zeile, 'Wesentliche Massnahmen')) {
            return 'massnahmen';
        }
        return null;
    }

    /**
     * Wertet die Kostentabelle des Informationsteils aus (F109): je Zeile ein Label
     * (Personalkosten, Sachkosten …) und der Soll-Wert des Budgetjahres. Die
     * Spaltenfolge ist Ist, in%, Soll Vorjahr, in%, Soll aktuell, in%, 3× Plan — der
     * Soll-Wert steht also an Zahlenindex 4. Summen- und Verrechnungszeilen sind
     * keine beantragbaren Positionen und werden weggelassen. Umbrochene Labels
     * («Kalk. Abschreibungen und» / «Zinsen / Finanzaufwand») werden zusammengeführt.
     *
     * @param list<string> $zeilen
     * @return list<array{label:string,soll:int}>
     */
    private function parseKostenzeilen(array $zeilen): array {
        $ausschluss = [
            'Kosten inkl. Verrechnung', 'Total effektive Kosten', 'Verrechnungen innerhalb PG',
            'Erlöse inkl. Verrechnung', 'Total effektive Erlöse', 'Total Nettokosten', 'Nettokosten / Globalkredit',
        ];
        $out = [];
        $labelPuffer = '';
        foreach ($zeilen as $z) {
            $z = trim($z);
            if ($z === '') {
                continue;
            }
            // Zeile mit Label und/oder Zahlen am Ende.
            if (preg_match('/^(.*?)\s*((?:[-\d’\'][\d’\'.\s%]*)?)$/u', $z, $m) && trim($m[2]) !== '') {
                $vorne = trim($m[1]);
                // Reine Zahlenzeile → Werte zum gepufferten Label.
                if ($vorne === '' || preg_match('/^[-\d’\'.]+$/u', $vorne)) {
                    $label = trim($labelPuffer);
                } else {
                    $label = trim($labelPuffer . ' ' . $vorne);
                }
                $labelPuffer = '';
                $zahlen = $this->zahlen($z);
                if ($label === '' || $this->kostenAusschluss($label, $ausschluss) || $zahlen === []) {
                    continue;
                }
                $soll = \count($zahlen) >= 5 ? $zahlen[4] : $zahlen[\count($zahlen) - 1];
                $out[] = ['label' => $label, 'soll' => $soll];
            } else {
                // Reine Textzeile: Label-Fortsetzung puffern.
                $labelPuffer = trim($labelPuffer . ' ' . $z);
            }
        }
        return $out;
    }

    /** @param list<string> $ausschluss */
    private function kostenAusschluss(string $label, array $ausschluss): bool {
        foreach ($ausschluss as $a) {
            if (str_starts_with($label, $a)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Hängt eine Zeile an einen Erläuterungstext an und erhält dabei die Struktur
     * (F110): ein Aufzählungspunkt, ein Jahres-Zwischentitel («2027 (…)») oder eine
     * Titelzeile mit Doppelpunkt beginnt eine neue Zeile; alles andere setzt den
     * vorherigen (im Buch hart umbrochenen) Satz fort und wird mit Leerzeichen
     * angefügt. So bleiben Absätze und Listen erhalten statt zu einem Block zu
     * verschmelzen.
     */
    private function erlaeuterungAnfuegen(string $vorher, string $zeile): string {
        $zeile = trim($zeile);
        if ($vorher === '') {
            return $zeile;
        }
        $neueZeile = (bool) preg_match('/^[-–•]/u', $zeile)
            || (bool) preg_match('/^\d{4}\s*\(/u', $zeile)
            || (bool) preg_match('/^\p{Lu}[^.:]{0,48}:$/u', $zeile);
        return $vorher . ($neueZeile ? "\n" : ' ') . $zeile;
    }

    /**
     * Ende eines Erläuterungs-/Begründungs-Textblocks: eine strukturelle Zeile
     * (Tabellenlabel, Produkt-/Kapitelüberschrift, laufende Kopf-/Fusszeile mit
     * Tab). Die Muster sind am Zeilenanfang verankert, damit ein Fliesstext, der
     * ein solches Wort mittendrin enthält, den Block nicht abbricht.
     */
    private function istAbschnittsende(string $zeile): bool {
        if (str_contains($zeile, "\t") || str_starts_with($zeile, '▼')) {
            return true;
        }
        if (preg_match('/^(Produkt\s+\d+\s|Nettokosten\b|Total\b|Stelleneinheiten\b|'
            . 'Auszubildende\b|Stellenplan\b|Personal:|Parlamentarische Zielvorgaben\b|'
            . 'Leistungen$|Leistungsmengen\b|Auftrag$|Rechtsgrundlagen|'
            . 'Verantwortliche Leitung|Globalkredit\b)/u', $zeile)) {
            return true;
        }
        // Kapitelüberschrift „<Name> (<Code>)“ (ohne Tab bereits oben behandelt).
        return (bool) preg_match('/\(\d{3}\)\s*$/u', $zeile);
    }

    /**
     * Ordnet eine Zahlen-Zeile ihrer Kennzahl zu (label-basiert). Die ersten drei
     * Werte der Aufwand-/Ertrag-Zeilen sind mit Prozent-Spalten verschachtelt; die
     * genaue Positionswahl wird im e2e gegen den realen Text justiert.
     *
     * @param array<string, mixed> $gruppe
     */
    private function fuelleZeile(array &$gruppe, string $zeile): void {
        // Die beschlussfähige Globalkredit-Wertzeile (Abschnitt «Zum Beschluss»)
        // trägt sechs Beträge. Gleich heissen zwei Kopfzeilen der Informations-
        // teil-Tabellen: «… Ist 2024 in%» und, bei den Eigenwirtschaftsbetrieben,
        // «Nettokosten / Globalkredit der Betriebe  Ist 2024 in %» — mit
        // Leerzeichen vor dem Prozentzeichen. Beide tragen Jahreszahlen statt
        // Beträgen; bei Stadtwerk (Globalkredit 0) stand deshalb 2026 im Feld.
        // Ausgeschlossen wird deshalb jede Zeile, die eine Spaltenbeschriftung
        // «Ist/Soll/Plan <Jahr>» trägt.
        //
        // Die Wertzeile BEGINNT mit dem Begriff. Im Kapiteltext steht er auch
        // mitten im Satz: «Die Nettokosten/Globalkredit steigen gegenüber 2020 um
        // rund 1,31 Mio. Franken … 5 Projektleiterstellen … (0,7 Mio. Franken)»
        // (Buch 2021, Tiefbau). Ohne Anker am Zeilenanfang überschrieb dieser Satz
        // die echten Beträge mit 2020/1/31/5/0/7.
        // In manchen Produktegruppen klebt das PDF die Kopfzeile der Globalkredit-
        // Tabelle und ihre Wertzeile zu EINER Zeile zusammen: «Globalkredit  Ist
        // 2025 Soll 2026 … Plan 2030  Nettokosten / Globalkredit  7'892'389 …»
        // (Buch 2027, Berufsbildung). Die Wertzeile beginnt dann nicht am
        // Zeilenanfang, und die Jahreszahlen der Kopfzeile stehen mitten drin —
        // beides schloss die Zeile aus, und die Produktegruppe stand mit
        // Globalkredit 0 da. Der Kopfteil wird abgeschnitten; wie viele Spalten er
        // nennt, hat die Hauptschleife bereits gelesen (_gkSpalten).
        $wertzeile = $zeile;
        if (preg_match(
            '/^.*(?:Ist|Soll|Plan)\s+20\d\d\s+((?:Total\s+)?Nettokosten\s*\/\s*Globalkredit\b.*)$/u',
            $zeile,
            $geklebt
        )) {
            $wertzeile = $geklebt[1];
        }
        if (
            preg_match('/^\s*(?:Total\s+)?Nettokosten\s*\/\s*Globalkredit\b/u', $wertzeile)
            && !preg_match('/\bin\s?%/u', $wertzeile)
            && !preg_match('/\b(?:Ist|Soll|Plan)\s+20\d\d/u', $wertzeile)
        ) {
            $z = $this->zahlen($wertzeile);
            if (count($z) >= 3) {
                $gruppe['globalkredit'] = $this->globalkreditSpalten($z, $gruppe['_gkSpalten'] ?? 6);
            }
            return;
        }
        if (str_starts_with($zeile, 'Total effektive Kosten')) {
            $gruppe['aufwand'] = $this->dreiWerteOhneProzent($zeile);
            return;
        }
        if (str_starts_with($zeile, 'Total effektive Erlöse')) {
            $gruppe['ertrag'] = $this->dreiWerteOhneProzent($zeile);
            return;
        }
        // Die Stellenzeile BEGINNT mit dem Wort (vor ihm stehen höchstens ein
        // «Personal:»-Vorspann und ein Aufzählungszeichen); die drei Werte
        // (Ist/Soll_Vorjahr/Soll) sind die Dezimalzahlen. Dieselbe Produktegruppe
        // führt das Wort weiter hinten auch im Fliesstext und in Leistungsmengen
        // («Geschätzter Zeitaufwand umgerechnet in Stelleneinheiten 7 7 7» bei den
        // Informatikdiensten, «… dividiert durch total Stelleneinheiten» ohne jede
        // Zahl beim Stadtrichteramt). Wer bloss nach dem Wort sucht, meldet 7
        // Stellen oder löscht die gelesenen wieder auf 0 — deshalb der Anker und
        // die Bedingung, dass die Zeile überhaupt eine Zahl trägt.
        // Das Aufzählungszeichen der Bücher liegt im privaten Unicode-Bereich
        // (U+F0A7, ein Wingdings-Quadrat), daneben kommen ▪ • · und ein
        // geschütztes Leerzeichen vor. Hinter dem Wort stehen NUR die Zahlen der
        // drei Spalten: «Der Aufbau … (2.0 Stelleneinheiten) um rund 350'000
        // Franken» (Entsorgung 2026) beginnt nach dem Umbruch ebenfalls mit dem
        // Wort und brächte sonst 350'000 Stellen.
        if (preg_match(
            // Ein Wert kann einen Fussnoten-Stern tragen («2.60*»).
            '/^(?:Personal\s*:)?[\s\x{00A0}\x{E000}-\x{F8FF}▪•·-]*Stelleneinheiten'
                . '[\s\x{00A0}]*(?:[-\d][\d.,\'’]*\*{0,3}[\s\x{00A0}]*)+$/u',
            $zeile
        )) {
            // Die Werte stehen rechtsbündig unter «Ist», «Soll Vorjahr», «Soll».
            // Fehlt eine Spalte, fehlt sie links (eine neue Einheit hat keinen
            // Ist-Wert, das Budgetjahr steht immer da) — deshalb von rechts.
            $z = $this->zahlenMitKomma($zeile);
            if ($z !== []) {
                $z = \array_slice($z, -3);
                $z = array_merge(array_fill(0, 3 - \count($z), 0.0), $z);
                $gruppe['stellen'] = ['ist' => $z[0], 'sollVorjahr' => $z[1], 'soll' => $z[2]];
            }
        }
    }

    /**
     * Aus einer Aufwand-/Ertrag-Zeile die drei Jahreswerte (Ist/Soll_Vorjahr/Soll)
     * ohne die dazwischenstehenden Prozent-Spalten.
     *
     * Die Zeile ist paarweise gesetzt: Betrag, Anteil, Betrag, Anteil, Betrag,
     * Anteil — danach die Planjahre ohne Anteil. Gezählt werden deshalb die Paare
     * von links, und zwar so weit, wie das Muster trägt.
     *
     * Zwei Fälle, die eine Trennung nach Grösse falsch löst:
     * - Bei «Steuern und Finanzausgleich» (280) ist der Ertrag ein Vielfaches der
     *   Kosten, der Anteil deshalb vierstellig («805’963’318 1’130 772’727’840
     *   8’293 …») und sähe wie ein Betrag aus.
     * - Eine neue Produktegruppe hat keinen Ist-Wert; ihre Zeile führt nur zwei
     *   Paare («4’882’598 100  5’455’201 100  5’353’321  5’413’207  5’397’597»,
     *   Öffentliche Beleuchtung 2022). Die Werte stehen rechtsbündig unter ihren
     *   Spalten, es fehlt also die LINKE — die zwei Paare sind Soll Vorjahr und
     *   Soll, nicht Ist und Soll Vorjahr.
     *
     * Ein Paar ist eine Zahl, gefolgt von einem kleineren Anteil unter 20'000.
     * Ohne erkennbares Paar (ein Buch ohne Anteilsspalten) bleibt es bei der
     * Trennung nach Grösse.
     *
     * @return array<string, int>
     */
    private function dreiWerteOhneProzent(string $zeile): array {
        $zahlen = $this->zahlen($zeile);
        $betraege = [];
        for ($i = 0; $i + 1 < \count($zahlen); $i += 2) {
            // Gleich gross zählt noch als Paar: Wo eine Spalte leer ist, steht in
            // beiden Feldern eine Null («-565 0  0 0  0 0», Beiträge an
            // Organisationen 2017). Ein echter Betrag in der Anteilsspalte ist
            // dagegen grösser als sein Bezugswert oder liegt über 20'000.
            if (abs($zahlen[$i + 1]) > abs($zahlen[$i]) || abs($zahlen[$i + 1]) > 20000) {
                break;
            }
            $betraege[] = $zahlen[$i];
        }
        if ($betraege === []) {
            $betraege = array_values(array_filter($zahlen, static fn ($n) => abs($n) > 1000));
        }
        // Von rechts füllen: eine fehlende Spalte fehlt links.
        $betraege = \array_slice($betraege, 0, 3);
        $betraege = array_merge(array_fill(0, 3 - \count($betraege), 0), $betraege);
        return ['ist' => $betraege[0], 'sollVorjahr' => $betraege[1], 'soll' => $betraege[2]];
    }

    /**
     * Parst Teil A: Steuerfuss, Steuerertrag und Investitionsprojekte.
     *
     * @return array<string, mixed>
     */
    private function parseTeilA(string $text, int $jahr): array {
        // In welcher Wertspalte des gestuften Erfolgsausweises steht das
        // Budgetjahr? Die Stadt hat das Format gewechselt (siehe wertspalte()).
        $spalteJahr = $this->wertspalte($text, $jahr);
        $spalteVorjahr = $this->wertspalte($text, $jahr - 1);
        // Der Steuerfuss steht im Beschlussantrag, und der ist zweimal umformuliert
        // worden: bis zum Budget 2019 «Die ordentliche Gemeindesteuer wird auf 122
        // Prozent …», ab 2020 «Der Steuerfuss der ordentlichen Gemeindesteuern wird
        // auf 125 Prozent …». Der gemeinsame Anker ist die Gemeindesteuer.
        $steuerfuss = 0;
        if (preg_match('/ordentliche[nr]?\s+Gemeindesteuer\w*\s+wird\s+auf\s+(\d{2,3})\s+Prozent/u', $text, $m)) {
            $steuerfuss = (int) $m[1];
        } elseif (preg_match('/Steuerfuss[^.]*?auf\s+(\d{2,3})\s+Prozent/u', $text, $m)) {
            $steuerfuss = (int) $m[1];
        }
        $personalsteuer = 0;
        if (preg_match('/Personalsteuer\s*\((\d+)\s+Franken/u', $text, $m)) {
            $personalsteuer = (int) $m[1];
        }
        // Der Gesamt-Steuerertrag (natürliche + juristische Personen) steht als
        // Fliesstext: „Die erwarteten Steuererträge belaufen sich auf 521,2
        // Millionen Franken …“. Die frühere „^Steuerertrag“-Suche traf dagegen
        // die Inhaltsverzeichnis-Zeile „Steuerertrag und Steuerfuss  38“.
        $steuerertrag = 0;
        if (preg_match('/Steuererträge belaufen sich auf\s+([\d’\'., ]+?)\s+Millionen/u', $text, $m)) {
            $steuerertrag = $this->millionenZuFranken($m[1]);
        }
        if ($steuerertrag === 0) {
            // Ältere Bücher (2022) kennen diesen Satz nicht. Der Steuerertrag ist
            // der Fiskalertrag (Kostenart 40) des gestuften Erfolgsausweises; in
            // den Jahren mit beiden Angaben stimmen sie überein.
            $steuerertrag = $this->mioSpalteNachLabels($text, ['Fiskalertrag'], $spalteJahr);
        }
        // Offizielles Gesamtergebnis der Erfolgsrechnung (F89): «Das Ergebnis in der
        // Erfolgsrechnung wird mit einem Ertragsüberschuss von 113,8 Millionen Franken
        // …». Ertragsüberschuss = Überschuss (positiv), Aufwandüberschuss = Defizit
        // (negativ). Ist der Massstab, gegen den die errechnete Summe geprüft wird.
        $gesamtergebnis = 0;
        if (preg_match('/Erfolgsrechnung wird mit einem\s+(Ertragsüberschuss|Aufwandüberschuss) von\s+([\d’\'., ]+?)\s+Millionen/u', $text, $m)) {
            $betrag = $this->millionenZuFranken($m[2]);
            $gesamtergebnis = $m[1] === 'Aufwandüberschuss' ? -$betrag : $betrag;
        }
        if ($gesamtergebnis === 0) {
            // Ältere Bücher formulieren anders («Daraus resultiert ein Gesamt-
            // ergebnis mit einem geringfügigen Ertragsüberschuss von 0,2 Millionen»).
            // Die Zeile GESAMTERGEBNIS des Erfolgsausweises steht in jedem Buch.
            $gesamtergebnis = $this->mioSpalteNachLabels($text, ['GESAMTERGEBNIS'], $spalteJahr);
        }
        if ($gesamtergebnis === 0) {
            // Die Bücher bis 2019 kennen keine Zeile «GESAMTERGEBNIS». Sie führen
            // das Ergebnis bei der Prüfung der Ausgabenbremse auf den Franken
            // genau: «Jahresergebnis Erfolgsrechnung  Aufwandüberschuss (-) /
            // Ertragsüberschuss (+)  -37'111'533.25».
            if (preg_match('/Jahresergebnis\s+Erfolgsrechnung\s+Aufwandüberschuss[^\n]*?(-?[\d’\']+\.\d{2})/u', $text, $m)) {
                $gesamtergebnis = (int) round((float) str_replace(["'", '’'], '', $m[1]));
            }
        }
        if ($gesamtergebnis === 0) {
            // Das Buch 2021 schreibt die Vorzeichen als Buchhaltungszeichen:
            // «GESAMTERGEBNIS  28.1 H  1.9 H  11.4 H  9.5» — H heisst Haben
            // (Ertragsüberschuss), S heisst Soll (Aufwandüberschuss).
            if (preg_match('/GESAMTERGEBNIS((?:\s+[\d.,]+\s+[HS])+)/u', $text, $m)
                && preg_match_all('/([\d.,]+)\s+([HS])/u', $m[1], $werte, PREG_SET_ORDER)
                && isset($werte[$spalteJahr])) {
                $betrag = $this->millionenZuFranken($werte[$spalteJahr][1]);
                $gesamtergebnis = $werte[$spalteJahr][2] === 'S' ? -$betrag : $betrag;
            }
        }
        if ($gesamtergebnis === 0) {
            // Die Bücher 2017 und 2018 rechnen es in der Deckungsübersicht vor:
            // «Zu deckender Aufwandüberschuss 356 205 996», «Steuerertrag
            // Rechnungsjahr 356 090 000», «Ergebnis 115 996». Das Ergebnis ist die
            // Differenz — deckt der Steuerertrag den Aufwand nicht, ist es negativ.
            $zuDecken = $this->frankenNachLabels($text, ['Zu deckender Aufwandüberschuss']);
            $steuerJahr = $this->frankenNachLabels($text, ['Steuerertrag Rechnungsjahr']);
            if ($zuDecken > 0 && $steuerJahr > 0) {
                $gesamtergebnis = $steuerJahr - $zuDecken;
            }
        }
        // Gesamt-Aufwand und -Ertrag aus dem gestuften Erfolgsausweis (betrieblich +
        // Finanzierung + ausserordentlich), jeweils die BU-Spalte (erster Wert). Sie
        // sind die massgeblichen Totale (ohne interne Verrechnung), aus denen sich das
        // Gesamtergebnis ergibt (Ertrag − Aufwand).
        $aufwandLabels = ['Betrieblicher Aufwand', 'Finanzaufwand', 'Ausserordentlicher Aufwand'];
        $ertragLabels = ['Betrieblicher Ertrag', 'Finanzertrag', 'Ausserordentlicher Ertrag'];
        $totalAufwand = $this->mioSpalteNachLabels($text, $aufwandLabels, $spalteJahr);
        $totalErtrag = $this->mioSpalteNachLabels($text, $ertragLabels, $spalteJahr);
        // Vorjahr (Budget des Vorjahres) für die Differenz-Anzeige.
        $totalAufwandVorjahr = $this->mioSpalteNachLabels($text, $aufwandLabels, $spalteVorjahr);
        $totalErtragVorjahr = $this->mioSpalteNachLabels($text, $ertragLabels, $spalteVorjahr);
        // Die Bücher bis 2019 stellen dieselbe Rechnung anders auf: Die Beträge
        // stehen in Franken statt in Millionen, zwei Wertspalten LINKS vom Namen
        // (Rechnung und Budget des Vorjahres) und das Budgetjahr rechts davon.
        // Welche Lesart stimmt, entscheidet die Probe: Ertrag − Aufwand muss das
        // Gesamtergebnis ergeben. So bleibt keine Lesart stehen, die zwar Zahlen
        // liefert, aber die falschen — in den alten Büchern kam auf dem
        // Millionen-Weg ein Aufwand von 32 Millionen heraus statt 1,36 Milliarden.
        $frankenAufwand = $this->frankenNachLabels($text, $aufwandLabels);
        $frankenErtrag = $this->frankenNachLabels($text, $ertragLabels);
        $mioTrifft = abs(($totalErtrag - $totalAufwand) - $gesamtergebnis);
        $frankenTrifft = abs(($frankenErtrag - $frankenAufwand) - $gesamtergebnis);
        // Der Aufwand einer Stadt mit 120'000 Einwohnern liegt in Milliarden.
        // Liefert nur ein Weg eine Summe dieser Grössenordnung, ist er der
        // richtige; liefern beide eine, entscheidet die Probe gegen das
        // Gesamtergebnis.
        $milliarde = 1_000_000_000;
        $frankenPlausibel = $frankenAufwand >= $milliarde && $frankenErtrag >= $milliarde;
        $mioPlausibel = $totalAufwand >= $milliarde && $totalErtrag >= $milliarde;
        $nimmFranken = $frankenPlausibel && (!$mioPlausibel || $frankenTrifft < $mioTrifft);
        if ($nimmFranken) {
            $totalAufwand = $frankenAufwand;
            $totalErtrag = $frankenErtrag;
            $totalAufwandVorjahr = $this->frankenVorLabels($text, $aufwandLabels);
            $totalErtragVorjahr = $this->frankenVorLabels($text, $ertragLabels);
        }
        return [
            'steuerfuss' => $steuerfuss,
            'steuerertrag' => $steuerertrag,
            'personalsteuer' => $personalsteuer,
            'gesamtergebnis' => $gesamtergebnis,
            'totalAufwand' => $totalAufwand,
            'totalErtrag' => $totalErtrag,
            'totalAufwandVorjahr' => $totalAufwandVorjahr,
            'totalErtragVorjahr' => $totalErtragVorjahr,
        ];
    }

    /**
     * Ordnet die Beträge der Globalkredit-Zeile ihren Spalten zu.
     *
     * Die Tabelle hat sechs Spalten (Ist, Soll Vorjahr, Soll, drei Planjahre),
     * manchmal nur drei (ohne Planjahre). Leere Zellen fehlen im gelesenen Text
     * ersatzlos: «Nettokosten / Globalkredit   3'384'789  3'558'935  3'540'130
     * 3'633'104» sind vier Werte für sechs Spalten, und wer von links zählt,
     * schiebt jeden Wert um zwei Spalten nach vorn (Tiefbau 2021).
     *
     * Eindeutig wird es von RECHTS: Die Planjahre stehen immer am Zeilenende und
     * sind immer vollständig. Was davor liegt, sind Soll, Soll Vorjahr und Ist —
     * in dieser Reihenfolge nach links, und was fehlt, bleibt leer.
     *
     * @param int[] $werte
     * @param int $spalten Zahl der Spalten laut Kopfzeile (6 mit Planjahren, sonst 3)
     * @return array<string, int>
     */
    private function globalkreditSpalten(array $werte, int $spalten): array {
        $planZahl = max(0, $spalten - 3);
        $anzahl = \count($werte);
        // Mehr Werte als Spalten: Die Zeile trägt etwas Zusätzliches am Ende
        // (etwa eine Prozentangabe) — dann zählt der Anfang.
        if ($anzahl > $spalten) {
            $werte = \array_slice($werte, 0, $spalten);
            $anzahl = $spalten;
        }
        $plan = $planZahl > 0 ? \array_slice($werte, -min($planZahl, $anzahl)) : [];
        $vorne = \array_slice($werte, 0, max(0, $anzahl - \count($plan)));
        // Die letzten drei vor den Planjahren sind Soll, Soll Vorjahr, Ist.
        $soll = $vorne === [] ? 0 : $vorne[\count($vorne) - 1];
        $sollVorjahr = \count($vorne) >= 2 ? $vorne[\count($vorne) - 2] : 0;
        $ist = \count($vorne) >= 3 ? $vorne[\count($vorne) - 3] : 0;
        return [
            'ist' => $ist,
            'sollVorjahr' => $sollVorjahr,
            'soll' => $soll,
            'plan1' => $plan[0] ?? 0,
            'plan2' => $plan[1] ?? 0,
            'plan3' => $plan[2] ?? 0,
        ];
    }

    /**
     * Summiert je Beschriftung den Frankenbetrag, der im alten Format RECHTS vom
     * Namen steht — das Budgetjahr: «… Betrieblicher Aufwand  1 356 238 710  -49
     * 409 167  4».
     *
     * Die Tausender trennt das Buch 2021 mit einem Apostroph, die älteren mit
     * einem Leerzeichen; beides gilt. Verlangt sind MINDESTENS ZWEI
     * Tausendergruppen: Sonst trifft das Muster die Millionenangabe des gestuften
     * Erfolgsausweises, die im selben Buch weiter vorne steht («Betrieblicher
     * Aufwand 1 642.6 S …» ergäbe 1'642), und der Gesamtaufwand der Stadt kam als
     * 33 Millionen heraus.
     *
     * @param list<string> $labels
     */
    private function frankenNachLabels(string $text, array $labels): int {
        $summe = 0;
        foreach ($labels as $label) {
            if (preg_match_all('/' . preg_quote($label, '/') . '\s+(' . self::FRANKEN_ZAHL . ')/u', $text, $m) === 0) {
                continue;
            }
            // Das PDF klebt benachbarte Zellen zusammen («1 388 827 1431 259 160
            // 941»); daraus entstehen Zahlen mit zwölf Ziffern. Ein Posten der
            // Stadtrechnung bleibt unter zehn Milliarden — der erste Treffer, der
            // das erfüllt, ist der gesuchte.
            foreach ($m[1] as $roh) {
                $wert = $this->frankenZahl($roh);
                if (abs($wert) < 10_000_000_000) {
                    $summe += $wert;
                    break;
                }
            }
        }
        return $summe;
    }

    /**
     * Dasselbe für die zweite Zahl LINKS vom Namen: das Budget des Vorjahres.
     * Links stehen im alten Format zuerst die Rechnung, dann das Vorjahresbudget.
     *
     * @param list<string> $labels
     */
    private function frankenVorLabels(string $text, array $labels): int {
        $summe = 0;
        foreach ($labels as $label) {
            $muster = '/(' . self::FRANKEN_ZAHL . ')\s*(' . self::FRANKEN_ZAHL . ')\s+' . preg_quote($label, '/') . '/u';
            if (preg_match($muster, $text, $m)) {
                $summe += $this->frankenZahl($m[2]);
            }
        }
        return $summe;
    }

    /** Wandelt eine Frankenzahl des Buchs («1 359 178 024», «1'368'964'637») in eine Ganzzahl. */
    private function frankenZahl(string $roh): int {
        return (int) str_replace([' ', "'", '’'], '', trim($roh));
    }

    /**
     * Die 0-basierte Wertspalte eines Jahres im gestuften Erfolgsausweis.
     *
     * Die Stadt hat das Spaltenformat gewechselt: Bis zum Budget 2024 steht das
     * Budgetjahr HINTEN («Rechnung 2020 | Budget 2021 | Budget 2022» bzw.
     * «RE 2022 | BU 2023 | BU 2024»), ab dem Budget 2025 VORNE («BU 2025 |
     * BU 2024 | Abw. | RE 2023»). Wer fest die erste Spalte liest, bekommt für die
     * alten Bücher die Rechnung von vor zwei Jahren: plausible Zahlen aus dem
     * falschen Jahr, die niemandem auffallen (Prüfung von Hand, 2026-08-28).
     *
     * Massgebend ist die Kopfzeile unmittelbar vor «Betrieblicher Aufwand»: die
     * Reihenfolge ihrer vierstelligen Jahreszahlen ist die Reihenfolge der
     * Wertspalten. Die Abweichungsspalten stehen immer hinter den Jahresspalten
     * und stören die Zählung für Budgetjahr und Vorjahr deshalb nicht.
     */
    private function wertspalte(string $text, int $jahr): int {
        // Die Jahreszahlen des Kopfes, in ihrer Reihenfolge. Sie stehen nicht in
        // jedem Buch auf einer Zeile: Das Buch 2020 setzt «Rechnung», «2018»,
        // «Budget», «2019» … untereinander, und wer nur einzeilige Köpfe kennt,
        // liest dort die erste Spalte statt der des Budgetjahres — die Rechnung
        // von vor zwei Jahren.
        // Die einzeilige Kopfzeile (der Normalfall) und, als Rückfall, die über
        // mehrere Zeilen gesammelten Jahreszahlen.
        $kopf = [];
        $fenster = [];
        foreach ($this->zeilen($text) as $zeile) {
            if (preg_match('/^(?:Total\s+)?Betrieblicher Aufwand\b/u', $zeile) === 1) {
                if ($kopf !== []) {
                    $pos = array_search((string) $jahr, $kopf, true);
                    return $pos === false ? 0 : (int) $pos;
                }
                $pos = $fenster === [] ? false : array_search((string) $jahr, $fenster, true);
                if ($pos !== false) {
                    return (int) $pos;
                }
                continue;
            }
            $treffer = preg_match_all('/(?<![\d\'’.])(20\d{2})(?![\d\'’.])/u', $zeile, $m);
            // Eine Kopfzeile nennt mehrere Jahre und keine Beträge.
            if ($treffer >= 2) {
                $kandidat = array_values(array_unique($m[1]));
                if (\count($kandidat) >= 2) {
                    $kopf = $kandidat;
                }
            }
            // Eine Zeile mit Beträgen gehört nicht zum Kopf; sie beginnt das
            // Fenster neu.
            if (preg_match('/\d[\d\'’]{3,}|\d[ \'’]\d{3}/u', $zeile) === 1) {
                $fenster = [];
                continue;
            }
            foreach ($m[1] ?? [] as $gefunden) {
                if (!\in_array($gefunden, $fenster, true)) {
                    $fenster[] = $gefunden;
                }
            }
            $fenster = \array_slice($fenster, -8);
        }
        return 0;
    }

    /**
     * Summiert je Beschriftung den Millionen-Wert der (0-basierten) Wertspalte aus
     * dem gestuften Erfolgsausweis.
     *
     * @param list<string> $labels
     */
    private function mioSpalteNachLabels(string $text, array $labels, int $spalte): int {
        $summe = 0;
        foreach ($labels as $label) {
            // Die Werte müssen DIREKT auf die Beschriftung folgen. Sonst trifft das
            // Muster die Zeile des Inhaltsverzeichnisses («3.2.1. Fiskalertrag
            // (Kostenart 40) …… 12») und liefert deren Seitenzahl als Betrag.
            $muster = '/' . preg_quote($label, '/') . '[ \t]+((?:-?[\d’\'.,]+[ \t]*(?:[HS][ \t]+)?){1,8})/u';
            if (!preg_match($muster, $text, $m)) {
                continue;
            }
            $werte = $this->mioWerte($m[1]);
            if (isset($werte[$spalte])) {
                $summe += $this->millionenZuFranken($werte[$spalte]);
            }
        }
        return $summe;
    }

    /**
     * Die Millionen-Werte einer Zeile des gestuften Erfolgsausweises.
     *
     * Zwei Eigenheiten der Bücher: Hinter jedem Wert kann ein Buchhaltungszeichen
     * stehen («1'692.7 S 1'613.9 S …» — S für Soll, H für Haben), und bis 2020
     * trennt ein Leerzeichen die Tausender auch hier, sodass «1 642.6» als zwei
     * Stücke ankommt. Beides wird hier aufgelöst; die Zeichen selbst tragen für
     * Aufwand und Ertrag keine Bedeutung (das Vorzeichen des Gesamtergebnisses
     * liest parseTeilA gesondert).
     *
     * @return list<string>
     */
    private function mioWerte(string $zeile): array {
        $stuecke = preg_split('/\s+/u', trim($zeile)) ?: [];
        $werte = [];
        for ($i = 0; $i < \count($stuecke); ++$i) {
            $stueck = $stuecke[$i];
            if (preg_match('/^[HS]$/u', $stueck) === 1) {
                continue;
            }
            if (preg_match('/^-?[\d’\']+(?:[.,]\d+)?$/u', $stueck) !== 1) {
                continue;
            }
            // «1 642.6»: eine ein- bis dreistellige Ganzzahl, gefolgt von einer
            // Dreiergruppe mit Nachkommastelle, ist EINE Zahl.
            if (preg_match('/^-?\d{1,3}$/u', $stueck) === 1
                && isset($stuecke[$i + 1])
                && preg_match('/^\d{3}[.,]\d+$/u', $stuecke[$i + 1]) === 1) {
                $werte[] = $stueck . $stuecke[$i + 1];
                ++$i;
                continue;
            }
            $werte[] = $stueck;
        }
        return $werte;
    }

    /**
     * Investitionen je Projekt aus dem Anhang «Investitionsplanung
     * Verwaltungsvermögen» (Teil A). Die Tabelle listet pro Zeile:
     *   <Nr> <Bezeichnung> <Budget Vorjahr> <Budget Budgetjahr> <Plan+1> <Plan+2> <Plan+3>
     * gruppiert nach «Departement <Name>» und Produktegruppe «<Name> (PG)».
     * Projektzeilen tragen eine mindestens 6-stellige Konto-Nr (z.B. 5001750);
     * ein- bis dreistellige Nr sind Total-/Bereichs-/Produktegruppen-Zeilen.
     *
     * Robust gegen Jahreszahlen IM Bezeichnungstext (z.B. „… 2028“): die fünf
     * Wertspalten sind immer die LETZTEN fünf Zahlen der Zeile, die Nr die erste.
     * Der Budgetjahr-Wert (BU) ist die zweite Wertspalte (Vorjahr, Budgetjahr, …).
     *
     * Die Bücher unterscheiden sich in vier Punkten, die alle hier abgefangen
     * werden (Prüfung von Hand, 2026-08-28 — vorher lieferten 2022 bis 2024 gar
     * keine Projekte und 2025/2026 rund ein Drittel zu viel):
     *  - Die Tausendertrennung ist bis 2024 ein Leerzeichen («103 925 451»),
     *    danach ein Apostroph.
     *  - Projektnummern sind bis 2024 fünfstellig, danach siebenstellig;
     *    Strukturzeilen tragen bis 2024 runde sechsstellige Nummern (121000),
     *    danach ein- bis dreistellige (121).
     *  - Neben den Departementen gibt es «Behörden und Stadtkanzlei» — eine
     *    oberste Einheit OHNE das Wort «Departement». Ohne sie erben ihre
     *    Projekte das zuletzt gesehene Departement.
     *  - Zeilen «… - Planung» und «… - Dummy» schlüsseln den Betrag der Zeile
     *    darüber auf; sie zählen NICHT nochmals. Ebenso liefert das PDF manche
     *    Projektzeile doppelt (überlagerter Text), erkennbar an der wortgleichen
     *    Wiederholung unmittelbar davor.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseInvestitionen(string $pfad, int $jahr): array {
        // Zwei Durchgänge: Der erste liefert die Projektnummern — auch die der
        // Zeilen, deren Zelle im Buch mehrdeutig ist und deshalb noch keinen
        // Betrag hat. Mit ihnen lässt sich die «Kontrolle der Investitionskredite»
        // lesen, die für jedes Projekt den Betrag des Budgetjahres eindeutig führt;
        // der zweite Durchgang zerlegt damit auch die mehrdeutigen Zellen.
        $erst = $this->investitionsZeilen($pfad, $jahr, []);
        $projektNummern = [];
        foreach ($erst['inv'] as $p) {
            $projektNummern[$p['nr']] = true;
        }
        foreach ($erst['offen'] as $nr) {
            $projektNummern[$nr] = true;
        }
        $kredite = $this->parseInvestitionskredite($pfad, $projektNummern);
        // Der zweite Durchgang lohnt nur, wo eine Zelle offen blieb.
        $inv = $erst['offen'] === []
            ? $erst['inv']
            : $this->investitionsZeilen($pfad, $jahr, $kredite)['inv'];
        foreach ($inv as &$p) {
            $p['gesamtkosten'] = $kredite[$p['nr']]['gesamtkredit'] ?? 0;
            $p['konten'] = $kredite[$p['nr']]['konten'] ?? [];
            unset($p['nr'], $p['_unter']);
        }
        unset($p);
        return $inv;
    }

    /**
     * Ein Durchgang durch die Investitionsplanung.
     *
     * @param array<string, array{gesamtkredit:int, betrag:int, konten:list<array<string,mixed>>}> $kredite
     *        die Beträge aus der Kreditkontrolle, leer im ersten Durchgang
     * @return array{inv: list<array<string, mixed>>, offen: list<string>}
     *         `offen` sind die Nummern der Zeilen ohne lesbaren Betrag
     */
    private function investitionsZeilen(string $pfad, int $jahr, array $kredite): array {
        $inv = [];
        $offen = [];
        $imBereich = false;
        $departement = '';
        $produktegruppe = '';
        $reihenfolge = 0;
        $vorige = '';
        $nachgereichterName = '';
        // Steht gerade ein Sammelposten oder Rahmenkredit offen? Die Zeilen unter
        // ihm können zu ihm gehören; entschieden wird das beim Abschluss der
        // Produktegruppe an deren Summe im Buch.
        $sammelposten = false;
        $letzterKredit = '';     // Name des zuletzt gesehenen Sammelpostens
        $gruppe = [];            // Projekte der laufenden Produktegruppe
        $gruppeSumme = null;     // deren Summe im Budgetjahr laut Buch
        $nrLaenge = 0;           // Stellenzahl der Projektnummern dieses Jahrgangs
        /** @var list<float> $anker x-Position je Wertspalte */
        $anker = [];
        $halb = 0.0;
        $spalte = 1;
        foreach ($this->fragmentZeilen($pfad) as $frags) {
            // Alles links der ersten Wertspalte ist Nr und Bezeichnung, alles ab
            // dort sind Beträge. Solange die Kopfzeile fehlt, gilt die ganze
            // Zeile als Text (dort steht ohnehin nur der Titel des Anhangs).
            $links = $rechts = [];
            foreach ($frags as $f) {
                if ($anker !== [] && $f['x'] >= $anker[0] - $halb) {
                    $rechts[] = $f;
                } else {
                    $links[] = $f;
                }
            }
            $zeile = $this->fragmentText($links);
            // Titel- und Kopfzeilen stehen quer über die ganze Breite; sie werden
            // deshalb an der GANZEN Zeile erkannt, nie am linken Teil allein.
            $ganz = $this->fragmentText($frags);

            // Kopfzeile der Tabelle: fünf Jahreszahlen, jede ein eigenes Fragment.
            // Sie liefert die Spaltenanker UND die Spalte des Budgetjahres.
            $jahre = $this->jahresKopf($frags);
            if ($jahre !== []) {
                $anker = array_map(static fn ($j) => $j['x'], $jahre);
                $halb = \count($anker) > 1 ? ($anker[1] - $anker[0]) / 2 : 27.0;
                $pos = array_search($jahr, array_map(static fn ($j) => $j['jahr'], $jahre), true);
                $spalte = $pos === false ? 1 : (int) $pos;
                continue;
            }
            if (!$imBereich) {
                // Anhang beginnt mit der Verwaltungsvermögens-Investitionsplanung.
                // Der Titel steht als eigene Zeile — in den neuen Büchern samt
                // «Verwaltungsvermögen», in den alten allein (das Wort steht dort
                // in einer eigenen Textspalte und fehlt im gelesenen Text).
                if (preg_match('/^Investitionsplanung(\s+(Allgemeines\s+)?Verwaltungsvermögen)?$/u', $ganz) === 1) {
                    $imBereich = true;
                }
                continue;
            }
            // Ende: die nächste Investitionsplanung-Sektion (Eigenwirtschafts-
            // betriebe/Finanzvermögen) — die entscheidet nicht das Parlament über
            // den Steuerhaushalt.
            if (preg_match('/^(Investitionsplanung\s+)?(Eigenwirtschaftsbetriebe|Finanzvermögen)$/u', $ganz) === 1) {
                break;
            }
            // Laufende Kopf-/Fusszeilen der Tabelle.
            if (
                str_starts_with($ganz, 'Investitionsplanung')
                || str_starts_with($ganz, 'Nr.')
                || str_starts_with($ganz, 'Seite ')
                || preg_match('/^\d{4}\s+\d{4}\b/u', $ganz)
            ) {
                continue;
            }
            // Doppelt gesetzte Zeile im PDF. Verglichen wird der Text ohne Leerraum:
            // die zweite Fassung ist oft anders zerlegt («… Schützenwies» + «e»),
            // und ein Zeichenvergleich sähe darin zwei verschiedene Zeilen — das
            // Projekt zählte dann zweimal. Der Vergleich steht NACH den Kopf- und
            // Fusszeilen: Läuft eine Wiederholung über einen Seitenwechsel, stünde
            // sonst der Seitenkopf dazwischen und die Zeile zählte doppelt (Buch
            // 2026, «5019310 Wülflingerstr., Neftenbacherstrasse»).
            // Eine der beiden Fassungen kann dabei den Namen abschneiden («… -
            // Strassensanie...» gegen «… - Strassensanierung», Buch 2026, Projekt
            // 5019310); dann trägt die gekürzte Fassung ihre Auslassung, und die
            // andere beginnt mit demselben Text.
            $kern = (string) preg_replace('/\s+/u', '', $zeile);
            if ($kern !== '' && ($kern === $vorige || $this->istGekuerzteFassung($kern, $vorige))) {
                continue;
            }
            $vorige = $kern;
            // Eine Zeile ohne Nummer, die nur Text trägt, ist die Bezeichnung des
            // Projekts der NÄCHSTEN Zeile — in den alten Büchern bricht eine lange
            // Bezeichnung so um. Sie wird gemerkt, sonst geht das Projekt verloren.
            if ($zeile !== '' && preg_match('/^\d/u', $zeile) !== 1) {
                $nachgereichterName = $zeile;
                continue;
            }
            // Datenzeile: führende Nr, dann Name, dann fünf Wertspalten. In den
            // alten Büchern steht der Name ohne Abstand an der Nr («100000Depar-
            // tement Kulturelles und Dienste»), der Abstand ist deshalb optional.
            if (!preg_match('/^(\d+)\s*(.*)$/u', $zeile, $m)) {
                continue;
            }
            $nr = $m[1];
            $name = trim($m[2]);
            // Das PDF zerreisst auch Projektnummern: «13411» steht als «134 11»
            // (Buch 2023). Die kurze Nummer sähe wie ein Bereichs-Subtotal aus, und
            // die Zeile fiele weg. Zusammengesetzt wird nur, wenn beide Stücke
            // zusammen genau so lang sind wie die Projektnummern dieses Jahrgangs
            // — sonst verschmölze ein Name, der mit einer Zahl beginnt («13321 1000
            // Bäume für Winterthur»), mit seiner Nummer.
            if (
                $nrLaenge > 0 && \strlen($nr) < $nrLaenge
                && preg_match('/^(\d+)\s+(\D.*)$/u', $name, $t) === 1
                && \strlen($nr) + \strlen($t[1]) === $nrLaenge
            ) {
                $nr .= $t[1];
                $name = trim($t[2]);
            }
            if ($name === '') {
                // Die Bezeichnung stand eine Zeile höher.
                $name = $nachgereichterName;
                $nachgereichterName = '';
                if ($name === '') {
                    continue;
                }
            } else {
                $nachgereichterName = '';
            }
            if ($name === 'Stadt Winterthur') {
                continue; // Gesamttotal
            }
            // In der Zeile eines Departements oder Bereichs steht der Name mit
            // seinen Beträgen in EINEM Textstück, wenn das PDF die Zeile nicht an
            // den Spalten trennt. Dort endet der Name vor der ersten Zahl: sonst
            // hiesse das Departement «Behörden und Stadtkanzlei 2 670 000 1 287 000
            // …», und genau so stand es auf den Karten der Bücher 2022 und 2024.
            // Für Projektzeilen gilt das nicht — «Sanierung Halle 710» trägt seine
            // Zahl im Namen.
            if (\strlen($nr) === 1 || str_ends_with($nr, '000') || str_starts_with($name, 'Departement ')) {
                $ohneWerte = trim((string) preg_replace('/\s+-?\d[\d\s\'’]*$/u', '', $name));
                if ($ohneWerte !== '' && preg_match('/\p{L}/u', $ohneWerte) === 1) {
                    $name = $ohneWerte;
                }
                // Auch die Nummer einer Einheit setzt das PDF zerrissen: Im Buch
                // 2026 steht die Stadtkanzlei als «8» und «65 Stadtkanzlei», und
                // die Einheit hiess danach «65 Stadtkanzlei». Die führenden Ziffern
                // gehören zur Nummer, und «865» ist ein Bereich, kein Departement.
                if (preg_match('/^(\d+)\s+(\p{L}.*)$/u', $name, $z) === 1) {
                    $nr .= $z[1];
                    $name = trim($z[2]);
                }
            }
            if (str_starts_with($name, 'Departement ')) {
                $departement = trim(substr($name, \strlen('Departement ')));
                $inv = array_merge($inv, $this->gruppeNachBuchsumme($gruppe, $gruppeSumme));
                $gruppe = [];
                $gruppeSumme = null;
                $sammelposten = false;
                continue;
            }
            // «… (PG)» kennzeichnet die Zeile einer Produktegruppe. Das PDF setzt
            // die Klammern einzeln, im gelesenen Text steht deshalb oft «( PG )».
            // Ihr Betrag im Budgetjahr ist die Summe der Gruppe.
            if (preg_match('/\(\s*PG\s*\)$/u', $name) === 1) {
                $produktegruppe = trim((string) preg_replace('/\(\s*PG\s*\)$/u', '', $name));
                $inv = array_merge($inv, $this->gruppeNachBuchsumme($gruppe, $gruppeSumme));
                $gruppe = [];
                $summeZeile = $this->werteNachSpalten($rechts, $anker, $halb);
                $gruppeSumme = isset($summeZeile[$spalte]) ? (int) $summeZeile[$spalte] : null;
                $sammelposten = false;
                continue;
            }
            // Oberste Einheit ohne das Wort «Departement» («Behörden und
            // Stadtkanzlei»): einstellige Nr im neuen, runde sechsstellige im
            // alten Format.
            if (\strlen($nr) === 1 || str_ends_with($nr, '00000')) {
                $departement = $name;
                $produktegruppe = '';
                $inv = array_merge($inv, $this->gruppeNachBuchsumme($gruppe, $gruppeSumme));
                $gruppe = [];
                $gruppeSumme = null;
                $sammelposten = false;
                continue;
            }
            // Bereichs-Subtotal: kurze Nr (neu) bzw. runde sechsstellige (alt).
            if (\strlen($nr) <= 3 || (\strlen($nr) === 6 && str_ends_with($nr, '000'))) {
                $inv = array_merge($inv, $this->gruppeNachBuchsumme($gruppe, $gruppeSumme));
                $gruppe = [];
                $gruppeSumme = null;
                $sammelposten = false;
                continue;
            }
            // Eine Tranche nennt im Namen den Kredit, aus dem sie stammt («… Tranche
            // 11334», «… Tr. 11676»); ihr Betrag steckt schon im Rahmenkredit. Das
            // gilt über alle Jahrgänge, auch wo die Sammelposten am Anfang der
            // Produktegruppe stehen und die Summenprobe unten deshalb nicht greift.
            if (preg_match('/\bTranche\s*\d|\bTr\.\s*\d/u', $name) === 1) {
                continue;
            }
            // «… - Planung» schlüsselt den Planungsanteil des Projekts darüber auf.
            // Er zählt nicht als eigene Investition, gehört aber zu jenem Projekt
            // (F87: «laufende oder bereits getätigte Planungskosten»). Bis 2024
            // schreiben die Bücher dafür «IR-Plan: Rahmenkredit <Nr>» — mit der
            // Nummer des Kredits darüber — oder «IR-Plan: RK <Name>» mit seinem
            // Namen. Die Fassung mit Namen gehört nur dann dazu, wenn der Kredit
            // darüber wirklich so heisst: Im Buch 2024 steht «11677 IR-Plan: RK
            // Verbesserung der Veloinfrastruktur» unter «11676 RK: Verbesserung
            // der Veloinfrastruktur» (mit ihr lag das Departement Bau und
            // Mobilität 430'000 über seiner Summe), im Buch 2023 dagegen gibt es
            // diesen Kredit noch nicht, und dieselbe Zeile ist dort ein eigenes
            // Projekt über 250'000 Franken.
            $planungZuKredit = preg_match('/^IR[- ]Plan:\s*Rahmenkredit\s*\d/u', $name) === 1
                || (
                    preg_match('/^IR[- ]Plan:\s*(RK\b.*)$/u', $name, $pm) === 1
                    && $this->gleicherName($pm[1], $letzterKredit)
                );
            if (
                preg_match('/\s-\s(Planung|Dummy)$/u', $name) === 1
                || $planungZuKredit
            ) {
                $werte = $this->werteNachSpalten($rechts, $anker, $halb);
                if ($gruppe !== [] && $werte !== []) {
                    $gruppe[\count($gruppe) - 1]['planungskosten'] = $werte[$spalte] ?? 0;
                } elseif ($inv !== [] && $werte !== []) {
                    $inv[\count($inv) - 1]['planungskosten'] = $werte[$spalte] ?? 0;
                }
                continue;
            }
            // Ein Sammelposten («SP: …») und ein Rahmenkredit («RK: …»,
            // «RK 11924: …», «… Rahmenkredit ( 11334 )») nennen den ganzen Betrag;
            // die Zeilen darunter führen einzelne Vorhaben daraus auf. Beides zu
            // zählen verdoppelt den Kredit — in der Investitionsplanung 2025 um
            // 1'733'000 Franken im Departement Bau und Mobilität.
            //
            // Ob eine Zeile darunter dazugehört, ist ihr selbst nicht anzusehen: Nur
            // ein Teil trägt «Tr. 11676» im Namen, die Einrückung ist überall
            // dieselbe, und einen eigenen Kredit hat auch eine Tranche. Entschieden
            // wird es deshalb erst beim Abschluss der Produktegruppe — an deren
            // Summe im Buch (siehe gruppeNachBuchsumme).
            $unter = $sammelposten;
            if (preg_match('/^(?:SP|RK)\s*\d*\s*:|\bRahmenkredit\b/u', $name) === 1) {
                $sammelposten = true;
                $unter = false;
                $letzterKredit = $name;
            }
            $werte = $this->werteNachSpalten($rechts, $anker, $halb, $kredite[$nr]['betrag'] ?? null, $spalte);
            if ($werte === []) {
                // Die Zelle ist mehrdeutig. Ihre Nummer geht an den zweiten
                // Durchgang: mit dem Betrag aus der Kreditkontrolle wird sie
                // eindeutig.
                $offen[] = $nr;
                continue;
            }
            $gruppe[] = [
                '_unter' => $unter,
                'departement' => $departement,
                'cluster' => $produktegruppe,
                'projekt' => $nr . ' ' . $name,
                'bu' => $werte[$spalte] ?? 0,
                'fap1' => $werte[$spalte + 1] ?? 0,
                'fap2' => $werte[$spalte + 2] ?? 0,
                'fap3' => $werte[$spalte + 3] ?? 0,
                // Was vor dem Budgetjahr investiert wurde: die Spalte links davon
                // (bei Budget 2025 die Spalte «2024»).
                'bereitsGetaetigt' => $spalte > 0 ? ($werte[$spalte - 1] ?? 0) : 0,
                'gesamtkosten' => 0,
                'konten' => [],
                'planungskosten' => 0,
                'nr' => $nr,
                'reihenfolge' => $reihenfolge++,
            ];
            $nrLaenge = \strlen($nr);
        }
        $inv = array_merge($inv, $this->gruppeNachBuchsumme($gruppe, $gruppeSumme));
        return ['inv' => $inv, 'offen' => $offen];
    }

    /**
     * Nennen die beiden Bezeichnungen dieselbe Sache? Verglichen wird ohne
     * Satzzeichen, Gross- und Kleinschreibung und mehrfachen Leerraum, damit
     * «RK: Verbesserung der Veloinfrastruktur» und «RK Verbesserung der
     * Veloinfrastruktur» als dieselbe gelten.
     */
    private function gleicherName(string $a, string $b): bool {
        $norm = static fn (string $s): string => trim((string) preg_replace(
            '/\s+/u',
            ' ',
            mb_strtolower((string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s))
        ));
        return $a !== '' && $norm($a) === $norm($b);
    }

    /**
     * Sind die beiden Zeilen dieselbe, nur einmal mit abgeschnittenem Namen? Das
     * Buch kürzt eine zu lange Bezeichnung mit einer Auslassung; die gekürzte und
     * die vollständige Fassung stehen dann als zwei Zeilen mit denselben Beträgen
     * untereinander.
     */
    private function istGekuerzteFassung(string $a, string $b): bool {
        if ($a === '' || $b === '') {
            return false;
        }
        foreach ([[$a, $b], [$b, $a]] as [$kurz, $lang]) {
            if (preg_match('/(\.{3}|…)$/u', $kurz) !== 1) {
                continue;
            }
            $rumpf = (string) preg_replace('/(\.{3}|…)$/u', '', $kurz);
            if ($rumpf !== '' && str_starts_with($lang, $rumpf)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Schliesst eine Produktegruppe ab und entscheidet dabei, ob die Zeilen unter
     * einem Sammelposten eigene Projekte sind oder Vorhaben aus dessen Kredit.
     *
     * Das Buch beantwortet die Frage selbst: Die Zeile der Produktegruppe nennt
     * ihre Summe. Es gibt genau zwei Lesarten — alle Zeilen zählen, oder die unter
     * einem Sammelposten zählen nicht —, und in jeder Produktegruppe trifft nur
     * eine davon die Summe. Buch 2025: Im Tiefbauamt stehen unter «SP: Sanierung
     * von kommunalen Verkehrswegen» einzelne Strassen, die schon im Sammelposten
     * stecken (1'733'000 zu viel, wenn beide zählen); in der Volksschule und im
     * Sportamt folgen auf die Sammelposten dagegen eigene Projekte, darunter die
     * Stirntribünen der Schützenwiese mit 2'250'000.
     *
     * Trifft keine Lesart (fehlende Summe, unlesbare Zeile), zählen alle Zeilen —
     * ein zu hoher Wert fällt in der Departementsprüfung auf, eine stillschweigend
     * verschwundene Zeile nicht.
     *
     * @param list<array<string, mixed>> $gruppe
     * @return list<array<string, mixed>>
     */
    private function gruppeNachBuchsumme(array $gruppe, ?int $summe): array {
        if ($gruppe === [] || $summe === null) {
            return $gruppe;
        }
        $alle = 0;
        $ohne = 0;
        foreach ($gruppe as $p) {
            $alle += (int) $p['bu'];
            if ($p['_unter'] !== true) {
                $ohne += (int) $p['bu'];
            }
        }
        if ($alle !== $summe && $ohne === $summe) {
            return array_values(array_filter($gruppe, static fn ($p) => $p['_unter'] !== true));
        }
        return $gruppe;
    }

    /**
     * Der Anhang «Kontrolle der Investitionskredite»: Er führt je Projekt den
     * GESAMTKREDIT und darunter die einzelnen Konten mit Teilbetrag, Teilkredit
     * und dem Datum, an dem der Kredit bewilligt wurde.
     *
     *   5001240 QA Güterschuppen Töss: Instandhaltung   348'000    698'000
     *   504051  Freizeit- und Kulturanlagen, Projektierung   0   25'000  18.08.2021 §
     *   504052  Freizeit- und Kulturanlagen, Ausführung  348'000  623'000  12.06.2024 §
     *
     * Die Tabelle hat zwei Wertspalten (Betrag im Budgetjahr, Kredit) und keine
     * Jahres-Kopfzeile; die beiden letzten Zahlen einer Zeile sind die Werte.
     *
     * @param array<string, bool> $projektNummern die Nummern aus der Investitionsplanung
     * @return array<string, array{gesamtkredit:int, betrag:int, konten:list<array<string,mixed>>}>
     */
    /**
     * Die Fragmente der beiden Wertspalten einer Zeile der Kreditkontrolle: alles
     * rechts der Textspalte, wobei lückenlos aneinander anschliessende Stücke
     * zusammengezogen werden. Das PDF zerreisst dort Zahlen auch ohne Apostroph
     * («1» + «15'000» bei x=380 und x=387 ergeben 115'000, einzeln gelesen 1 und
     * 15'000 — Projekt 19755 im Buch 2022).
     *
     * @param list<array{x: float, t: string}> $frags
     * @return list<array{x: float, t: string}>
     */
    private function wertFragmente(array $frags): array {
        $rechts = [];
        foreach ($frags as $f) {
            if ($f['x'] < 300.0) {
                continue;
            }
            $letzte = \count($rechts) - 1;
            if ($letzte >= 0 && $f['x'] - $rechts[$letzte]['x'] < 20.0) {
                $rechts[$letzte]['t'] = rtrim($rechts[$letzte]['t']) . ltrim($f['t']);
                continue;
            }
            $rechts[] = $f;
        }
        return $rechts;
    }

    private function parseInvestitionskredite(string $pfad, array $projektNummern): array {
        $kredite = [];
        $imBereich = false;
        $aktuell = null;
        foreach ($this->fragmentZeilen($pfad) as $frags) {
            $ganz = $this->fragmentText($frags);
            if (!$imBereich) {
                if (str_starts_with($ganz, 'Kontrolle der Investitionskredite')) {
                    $imBereich = true;
                }
                continue;
            }
            // Die Kreditkontrolle endet mit der Investitionsplanung.
            if (preg_match('/^Investitionsplanung\b/u', $ganz) === 1) {
                break;
            }
            if (!preg_match('/^(\d{4,})\s+(\S.*)$/u', $ganz, $m)) {
                continue;
            }
            $nr = $m[1];
            $rest = $m[2];
            // Die beiden Werte stehen rechts in ihren Spalten; der Name steht links
            // und ist ein Fragment, das dort beginnt. Nur die rechten Fragmente zu
            // lesen hält die Zahlen des Namens heraus: «Masterplan Bahnhof:
            // Rahmenkredit (11334) AP1 + AP2» ergab sonst Betrag 1 und Kredit 2
            // (Buch 2022), «… -Querung Grüze AP2» den Betrag 2.
            $zahlen = $this->zahlen(preg_replace(
                '/\d{2}\.\d{2}\.\d{4}/u',
                '',
                $this->fragmentText($this->wertFragmente($frags))
            ) ?? $rest);
            if (\count($zahlen) < 2) {
                continue;
            }
            $betrag = $zahlen[\count($zahlen) - 2];
            $kredit = $zahlen[\count($zahlen) - 1];
            $name = trim((string) preg_replace('/\s+[-\d\'’.\s]+$/u', '', $rest));
            preg_match('/(\d{2}\.\d{2}\.\d{4})/u', $rest, $dm);
            if (isset($projektNummern[$nr])) {
                // Projektzeile: eigener Eintrag, die folgenden Konten hängen daran.
                // Der Betrag ist der des Budgetjahres — diese Tabelle hat nur zwei
                // Wertspalten und ist damit eindeutig, wo die Investitionsplanung
                // mit ihren fünf Spalten mehrdeutig bleibt.
                $aktuell = $nr;
                $kredite[$nr] = ['gesamtkredit' => $kredit, 'betrag' => $betrag, 'konten' => []];
                continue;
            }
            if ($aktuell !== null) {
                $kredite[$aktuell]['konten'][] = [
                    'konto' => $nr,
                    'name' => $name,
                    'betrag' => $betrag,
                    'kredit' => $kredit,
                    'bewilligt' => $dm[1] ?? '',
                ];
            }
        }
        return $kredite;
    }

    /**
     * Die Zeilen eines PDF als Textfragmente mit x-Position: je Seite von oben
     * nach unten, je Zeile von links nach rechts. Gleiche Technik wie im
     * Drehbuch-Parser — nötig, wo eine Tabelle leere Zellen hat und der reine
     * Text nicht mehr verrät, zu welcher Spalte ein Wert gehört.
     *
     * @return list<list<array{x: float, t: string}>>
     */
    private function fragmentZeilen(string $pfad): array {
        // Ein Budgetbuch zu parsen dauert Sekunden, und mehrere Durchgänge lesen
        // dasselbe Buch. Gehalten wird es nur, solange es das aktuelle ist.
        if ($this->gehaltenerPfad === $pfad && $this->gehalteneFragmente !== null) {
            return $this->gehalteneFragmente;
        }
        $zeilen = [];
        foreach ($this->dokument($pfad)->getPages() as $page) {
            $nachY = [];
            foreach ($page->getDataTm() as $r) {
                $m = $r[0];
                $nachY[(int) round(((float) ($m[5] ?? 0)) / 4)][] = [
                    'x' => (float) ($m[4] ?? 0),
                    't' => (string) $r[1],
                ];
            }
            krsort($nachY);
            foreach ($nachY as $frags) {
                usort($frags, static fn ($a, $b) => $a['x'] <=> $b['x']);
                $zeilen[] = array_values($frags);
            }
        }
        $this->gehalteneFragmente = $zeilen;
        return $zeilen;
    }

    /**
     * Der Text einer Fragmentfolge. Ein PDF zerlegt Wörter beim Kerning in
     * Bruchstücke («1D», «e», «p», «artement Präsidiales»); die gehören ohne
     * Abstand zusammen, sonst heisst das Departement «D e p artement» und wird
     * nicht mehr erkannt. Ein Bruchstück ist daran zu erkennen, dass es mit
     * einem Kleinbuchstaben an ein Wort anschliesst — entweder dicht daran oder
     * hinter einem Bruchstück von ein bis zwei Zeichen. Ein echtes kleines Wort
     * («und», «der») steht dagegen mit vollem Wortabstand hinter einem Wort.
     *
     * @param list<array{x: float, t: string}> $frags
     */
    private function fragmentText(array $frags): string {
        $text = '';
        foreach ($frags as $i => $f) {
            if ($i > 0) {
                $vorher = trim($frags[$i - 1]['t']);
                $abstand = $f['x'] - $frags[$i - 1]['x'];
                $aktuell = trim($f['t']);
                $eng = preg_match('/^\p{Ll}/u', $aktuell) === 1
                    && preg_match('/\p{L}$/u', $vorher) === 1
                    && (
                        // Ein einzelner Kleinbuchstabe ist nie ein Wort: «Ablösun»
                        // + «g», «Planun» + «g», «S» + «ystem».
                        mb_strlen($aktuell) === 1
                        || mb_strlen($vorher) <= 2
                        || $abstand < 8.0
                    );
                $text .= $eng ? '' : ' ';
            }
            $text .= $f['t'];
        }
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Ist diese Zeile der Jahres-Kopf einer Tabelle? Dann liefert sie je Jahr
     * dessen x-Position. Ein Kopf besteht aus mindestens vier Fragmenten, die
     * jeweils NUR eine vierstellige Jahreszahl enthalten.
     *
     * @param list<array{x: float, t: string}> $frags
     * @return list<array{jahr: int, x: float}>
     */
    private function jahresKopf(array $frags): array {
        $jahre = [];
        foreach ($frags as $f) {
            if (preg_match('/^\s*(20\d{2})\s*$/u', $f['t'], $m) !== 1) {
                return [];
            }
            $jahre[] = ['jahr' => (int) $m[1], 'x' => $f['x']];
        }
        return \count($jahre) >= 4 ? $jahre : [];
    }

    /**
     * Die Beträge einer Tabellenzelle. Steht ein Apostroph darin, ist die
     * Trennung eindeutig. Sonst (alte Bücher) werden die Ziffergruppen geliefert
     * und erst von betraegeAusGruppen() zu Beträgen zusammengesetzt — dafür
     * braucht es die Zahl der verbleibenden Spalten.
     *
     * @return array{apostroph: bool, werte: int[]}
     */
    private function zellenZahlen(string $text): array {
        // Eine Zelle kann BEIDE Trennungen tragen: Das Buch 2020 schreibt
        // «2 549 000 2'306 000» — Leerzeichen in der einen Spalte, Apostroph in
        // der nächsten. Die Apostroph-Lesart machte daraus 2, 549, 0, 2'306, 0,
        // und das Projekt stand mit dem Betrag 2 statt 2'549'000 da. Eindeutig
        // ist die Apostroph-Lesart nur, wenn keine alleinstehende Dreiergruppe in
        // der Zelle steht — eine solche ist immer der Tausenderteil der Zahl
        // davor.
        $stuecke = preg_split('/\s+/u', trim($text)) ?: [];
        $mitApostroph = false;
        $dreiergruppe = false;
        foreach ($stuecke as $stueck) {
            $mitApostroph = $mitApostroph || str_contains($stueck, "'") || str_contains($stueck, '’');
            $dreiergruppe = $dreiergruppe || preg_match('/^\d{3}$/u', $stueck) === 1;
        }
        if ($mitApostroph && !$dreiergruppe) {
            preg_match_all("/-?\\d{1,3}(?:['’]\\d{3})*/u", $text, $m);
            return [
                'apostroph' => true,
                'werte' => array_map(static fn ($s) => (int) str_replace(["'", '’'], '', $s), $m[0]),
            ];
        }
        // Als Zeichenketten, denn «000» darf seine Nullen nicht verlieren:
        // aus 190 + 000 wird 190000, nie 1900. Ein Apostroph trennt hier
        // ebenfalls Tausender und wird deshalb wie ein Leerzeichen behandelt.
        preg_match_all('/-?\d+/u', str_replace(["'", '’'], ' ', $text), $m);
        return ['apostroph' => false, 'werte' => $m[0]];
    }

    /**
     * Teilt die Ziffergruppen einer Zelle des alten Formats in Beträge auf.
     *
     * Dort trennt ein Leerzeichen sowohl Tausender als auch Spalten, «190 000
     * 230 000 …» ist deshalb für sich genommen mehrdeutig. Eindeutig wird es aus
     * zwei Tatsachen: Eine gefüllte Zelle reicht bis zum Ende der Zeile (die
     * Tabelle bricht rechts nicht ab), und alle Beträge einer Zeile haben gleich
     * viele Gruppen. Gesucht ist also die grösste Zahl von Beträgen, die in die
     * verbleibenden Spalten passt und die Gruppen glatt teilt — mit mindestens
     * zwei Gruppen je Betrag, denn die Beträge liegen über tausend Franken.
     *
     * @param string[] $gruppen
     * @return int[]
     */
    private function betraegeAusGruppen(array $gruppen, int $spalten, int $startspalte, ?int $bekannt = null, int $bekannteSpalte = 0): array {
        // Eine Tausendergruppe hat immer genau drei Ziffern. Eine Gruppe mit ein
        // oder zwei Ziffern — oder mit Minuszeichen — beginnt deshalb einen neuen
        // Betrag: «100 000 1 800 000 360 000 -250 000» sind vier Beträge, und
        // zwar mit unterschiedlich vielen Gruppen.
        $anzahl = \count($gruppen);
        // Eine Zelle, die in der ERSTEN Spalte beginnt, trägt die ganze Zeile;
        // verteilen sich ihre Gruppen glatt auf alle Spalten, ist die Aufteilung
        // eindeutig («190 000 230 000 250 000 250 000 250 000» → fünf Beträge).
        // Sie gilt aber nur, wenn sie den Regeln der Tabelle genügt: kein Betrag
        // beginnt mit «000», und unter tausend Franken kommt nur die Null vor.
        // «550 000 1 390 000 1 130 000 350 000» sind zehn Gruppen bei fünf Spalten
        // und damit glatt teilbar — in Paare geschnitten ergäbe das 1'390 statt
        // 1'390'000 (Buch 2022, Projekt 13031). Dort tragen vier Beträge die Zeile,
        // einer davon mit drei Gruppen; entschieden wird das unten.
        if ($startspalte === 0 && $spalten > 1 && $anzahl % $spalten === 0) {
            $je = intdiv($anzahl, $spalten);
            if ($je >= 2 && $je <= 3) {
                $werte = [];
                for ($i = 0; $i < $spalten; $i++) {
                    $teile = \array_slice($gruppen, $i * $je, $je);
                    $wert = (int) implode('', $teile);
                    if (preg_match('/^0\d/', $teile[0]) === 1 || ($wert !== 0 && abs($wert) < 1000)) {
                        $werte = [];
                        break;
                    }
                    $werte[] = $wert;
                }
                if ($werte !== []) {
                    return $werte;
                }
            }
        }
        // Ein einzelner Betrag hat höchstens drei Gruppen (unter einer Milliarde).
        if ($anzahl <= 3) {
            return [(int) implode('', $gruppen)];
        }
        // Alle möglichen Aufteilungen durchgehen und die eine nehmen, die als
        // einzige übrig bleibt. Ohne sie fiel 2024 die halbe Tabelle weg.
        $werte = $this->betraegeEindeutig($gruppen, max(1, $spalten - $startspalte), $bekannt, $bekannteSpalte);
        if ($werte !== []) {
            return $werte;
        }
        // Bleiben mehrere Lesarten, ist die Aufteilung ohne die Breite der Zelle
        // nicht zu entscheiden. Lieber kein Wert als ein erfundener — die Zeile
        // fällt weg.
        return [];
    }

    /**
     * Zerlegt die Ziffergruppen einer Zelle in Beträge, sofern genau eine Lesart
     * die Regeln der Tabelle erfüllt:
     *  - Eine Tausendergruppe hat genau drei Ziffern und kein Vorzeichen; eine
     *    Gruppe mit ein oder zwei Ziffern oder mit Minus beginnt deshalb immer
     *    einen neuen Betrag («200 000 -500 000 2 000 000» sind drei).
     *  - Ein Betrag hat höchstens drei Gruppen (unter einer Milliarde) und beginnt
     *    nie mit «000».
     *  - Die Tabelle führt Tausenderbeträge: unter 1000 kommt nur die Null vor.
     *  - Es können nicht mehr Beträge sein, als Spalten übrig sind.
     *
     * Ist der Betrag einer Spalte aus anderer Quelle bekannt — die «Kontrolle der
     * Investitionskredite» führt denselben Betrag in einer Tabelle mit nur zwei
     * Wertspalten und ist damit eindeutig —, entscheidet er zwischen den Lesarten:
     * «1 561 600 1 379 600 128 900 444 300 522 000» hat zwölf Gruppen auf fünf
     * Spalten und mehrere gültige Aufteilungen; mit 1'379'600 im Budgetjahr bleibt
     * genau eine übrig (Buch 2024, Projekt 19973, 1'349'601 Franken).
     *
     * @param string[] $gruppen
     * @return int[]
     */
    private function betraegeEindeutig(array $gruppen, int $freieSpalten, ?int $bekannt = null, int $bekannteSpalte = 0): array {
        if ($bekannteSpalte < 0 || $bekannteSpalte >= $freieSpalten) {
            $bekannt = null; // die Spalte des bekannten Betrags liegt nicht in dieser Zelle
        }
        $loesungen = [];
        // Ohne bekannten Betrag genügt die zweite Lesart als Beweis der
        // Mehrdeutigkeit; mit ihm müssen alle vorliegen, um zu filtern.
        $this->zerlegeGruppen($gruppen, 0, [], $freieSpalten, $loesungen, $bekannt === null ? 2 : 500);
        if ($bekannt !== null) {
            $loesungen = array_values(array_filter(
                $loesungen,
                static fn ($l) => ($l[$bekannteSpalte] ?? null) === $bekannt
            ));
            // Bleiben mehrere, entscheidet die Breite der Zelle: Sie beginnt in
            // ihrer Spalte und reicht bis zum Ende der Zeile, füllt also alle
            // übrigen Spalten. Bei Projekt 19973 (Buch 2024) blieben sonst zwei
            // Lesarten — «128 900 | 444 300 | 522 000» und «128 900 444 |
            // 300 522 000», die eine mit fünf, die andere mit vier Beträgen.
            if (\count($loesungen) > 1) {
                $voll = array_values(array_filter(
                    $loesungen,
                    static fn ($l) => \count($l) === $freieSpalten
                ));
                if (\count($voll) === 1) {
                    return $voll[0];
                }
            }
        }
        return \count($loesungen) === 1 ? $loesungen[0] : [];
    }

    /**
     * @param string[] $gruppen
     * @param int[] $bisher
     * @param list<int[]> $loesungen
     */
    private function zerlegeGruppen(array $gruppen, int $i, array $bisher, int $max, array &$loesungen, int $grenze = 2): void {
        if (\count($loesungen) >= $grenze) {
            return; // genug Lesarten gesammelt
        }
        if ($i === \count($gruppen)) {
            $loesungen[] = $bisher;
            return;
        }
        if (\count($bisher) >= $max) {
            return;
        }
        for ($len = 1; $len <= 3 && $i + $len <= \count($gruppen); $len++) {
            $teile = \array_slice($gruppen, $i, $len);
            // Nur eine echte Tausendergruppe setzt einen Betrag fort.
            if ($len > 1 && preg_match('/^\d{3}$/', $teile[$len - 1]) !== 1) {
                break;
            }
            // Ein Betrag beginnt nie mit einer führenden Null — die Tabelle schreibt
            // die Null als «0», jede andere Gruppe mit führender Null ist eine
            // Tausendergruppe. Ohne diese Regel liest sich «100 000 751 000» auch
            // als 100'000'751 und 0, und «261 500 530 050 670 050 517 900» bleibt
            // mehrdeutig, weil «050» einen Betrag beginnen könnte — die Zeile fiel
            // dann ganz weg (Buch 2022, Projekt 19828).
            if (preg_match('/^0\d/', $teile[0]) === 1) {
                continue;
            }
            $wert = (int) implode('', $teile);
            if ($wert !== 0 && abs($wert) < 1000) {
                continue;
            }
            $this->zerlegeGruppen($gruppen, $i + $len, array_merge($bisher, [$wert]), $max, $loesungen, $grenze);
        }
    }

    /**
     * Setzt Zahlen zusammen, die das PDF mitten im Betrag zerreisst: «960’00» und
     * «0» stehen als zwei Fragmente nebeneinander und ergeben 960'000 — einzeln
     * gelesen 960 und 0, also den tausendsten Teil. Erkennbar ist der Bruch am
     * Ende des ersten Stücks: Nach einem Tausender-Apostroph stehen dort weniger
     * als drei Ziffern, die Zahl ist also unfertig. In der Investitionsplanung
     * 2025 betraf das 24 Zeilen.
     *
     * @param list<array{x: float, t: string}> $frags
     * @return list<array{x: float, t: string}>
     */
    private function fragmenteVerschmelzen(array $frags): array {
        $zusammen = [];
        foreach ($frags as $f) {
            $letzte = \count($zusammen) - 1;
            if ($letzte >= 0 && preg_match("/[’']\\d{1,2}$/u", rtrim($zusammen[$letzte]['t'])) === 1) {
                $zusammen[$letzte]['t'] = rtrim($zusammen[$letzte]['t']) . ltrim($f['t']);
                continue;
            }
            $zusammen[] = $f;
        }
        return $zusammen;
    }

    /**
     * Ordnet die Beträge einer Zeile ihren Spalten zu. Ein Fragment kann mehrere
     * Beträge enthalten (dann stehen sie lückenlos nebeneinander); seine Spalte
     * ergibt sich aus der x-Position, die folgenden zählen von dort weiter.
     *
     * @param list<array{x: float, t: string}> $frags
     * @param list<float> $anker
     * @return array<int, int>
     */
    private function werteNachSpalten(array $frags, array $anker, float $halb, ?int $bekannt = null, int $bekannteSpalte = 0): array {
        if ($anker === []) {
            return [];
        }
        $werte = [];
        foreach ($this->fragmenteVerschmelzen($frags) as $f) {
            $zelle = $this->zellenZahlen($f['t']);
            if ($zelle['werte'] === []) {
                continue;
            }
            // Die Spalte, deren Anker der ERSTEN Zahl dieses Fragments am nächsten
            // liegt. Trägt das Fragment mehrere Beträge, ist es entsprechend
            // breiter — seine Mitte läge dann schon in der nächsten Spalte, und
            // alle Werte rutschten eine nach rechts («0 2'500'000» als ein Stück,
            // Projekt 5018220 im Buch 2025: 2,5 Millionen landeten im Planjahr).
            $mitte = $f['x'] + $halb / max(1, \count($zelle['werte']));
            $beste = 0;
            foreach ($anker as $i => $ax) {
                if (abs($mitte - $ax) < abs($mitte - $anker[$beste])) {
                    $beste = $i;
                }
            }
            $zahlen = $zelle['apostroph']
                ? $zelle['werte']
                : $this->betraegeAusGruppen(
                    $zelle['werte'],
                    \count($anker),
                    $beste,
                    $bekannt,
                    $bekannteSpalte - $beste
                );
            // Passen die Beträge ab dieser Spalte nicht mehr in die Tabelle, steht
            // das Fragment am Zeilenende und die Werte reichen bis zur letzten
            // Spalte: dann zählt von rechts. Sonst fiele der letzte Betrag weg
            // («… 0 -1'500'000» als ein Textstück, Projekt 5023660 im Buch 2026).
            if ($beste + \count($zahlen) > \count($anker)) {
                $beste = max(0, \count($anker) - \count($zahlen));
            }
            foreach ($zahlen as $k => $z) {
                $werte[$beste + $k] = $z;
            }
        }
        // Nennt die Kreditkontrolle den Betrag des Budgetjahres und steht er in
        // dieser Zeile in einer anderen Spalte, sind die Spalten um so viele
        // Stellen verschoben: Bei «13371 Erneuerung Przewalsky Pferdeanlage» im
        // Buch 2022 steht die einzelne «-1» eine Spalte zu weit rechts, und mit ihr
        // lag die Summe der Stadt um einen Franken daneben. Verschoben wird nur,
        // wenn der Betrag genau einmal vorkommt und alle Werte in der Tabelle
        // bleiben.
        if ($bekannt !== null && $bekannt !== 0 && ($werte[$bekannteSpalte] ?? null) !== $bekannt) {
            $stellen = array_keys($werte, $bekannt, true);
            if (\count($stellen) === 1) {
                $um = $bekannteSpalte - $stellen[0];
                $ziel = [];
                foreach ($werte as $i => $z) {
                    if ($i + $um < 0 || $i + $um >= \count($anker)) {
                        return $werte;
                    }
                    $ziel[$i + $um] = $z;
                }
                return $ziel;
            }
        }
        return $werte;
    }

    /**
     * Die letzten $n ganzen Zahlen einer Zeile (Schweizer Format), als int —
     * für Tabellen, deren Wertspalten rechtsbündig am Zeilenende stehen und
     * deren Bezeichnung selbst Zahlen enthalten kann.
     *
     * @return int[]
     */
    private function letzteZahlen(string $zeile, int $n): array {
        preg_match_all("/-?\\d[\\d'’]*/u", $zeile, $m);
        $alle = array_map(static fn ($s) => (int) str_replace(["'", '’'], '', $s), $m[0]);
        return array_slice($alle, -$n);
    }

    /** @return string[] */
    private function zeilen(string $text): array {
        $zeilen = preg_split('/\R/u', $text) ?: [];
        return array_values(array_filter(array_map('trim', $zeilen), static fn ($z) => $z !== ''));
    }

    /**
     * Alle ganzen Zahlen einer Zeile (Schweizer Format 1'234'567), als int.
     *
     * @return int[]
     */
    private function zahlen(string $zeile): array {
        preg_match_all("/-?\\d[\\d'’]*/u", $zeile, $m);
        return array_map(fn ($s) => (int) str_replace(["'", '’'], '', $s), $m[0]);
    }

    /**
     * Zahlen mit optionaler Nachkommastelle (Stelleneinheiten, z.B. 17.20), als
     * int-gerundete Werte reichen hier nicht — daher als float in ein int-Array
     * nicht sinnvoll; Stellen werden als float benötigt.
     *
     * @return float[]
     */
    private function zahlenMitKomma(string $zeile): array {
        preg_match_all("/-?\\d[\\d'’]*(?:[.,]\\d+)?/u", $zeile, $m);
        return array_map(static fn ($s) => (float) str_replace(["'", '’', ','], ['', '', '.'], $s), $m[0]);
    }

    /**
     * Wandelt eine als „Millionen“ angegebene Schweizer Zahl (z.B. „521,2“) in
     * ganze Franken (521'200'000). Apostroph-Tausender und Leerraum entfallen,
     * das Dezimalkomma wird zum Punkt.
     */
    private function millionenZuFranken(string $roh): int {
        $n = str_replace(["'", '’', ' '], '', trim($roh));
        $n = str_replace(',', '.', $n);
        return (int) round((float) $n * 1_000_000);
    }
}
