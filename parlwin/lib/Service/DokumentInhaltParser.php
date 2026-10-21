<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Macht aus einem amtlichen Dokument des Parlaments seinen Inhalt (F121).
 *
 * Die PDF sind ungetaggt: Sie tragen keine Überschriften, keine Absätze und
 * keine Tabellen, sondern Textstücke mit Position, Schrift und Schriftgrösse.
 * Gelesen werden sie darum über denselben `PdfZeilenLeser` und dieselbe
 * Spaltenlogik wie die Budgetbücher und das Drehbuch: Eine Tabelle erkennt man
 * daran, dass mehrere Zeilen ihre Textstücke an denselben x-Positionen
 * beginnen.
 *
 * Was dabei entsteht:
 *
 * - `titel` und `ueberschrift` aus Schriftgrösse und Fettschrift,
 * - `liste` aus der führenden Nummer und dem Einzug,
 * - `tabelle` aus den wiederkehrenden Spaltenpositionen,
 * - `zitat` für die kursiv wiederholte Anfrage in der Antwort des Stadtrats,
 * - `unterzeichnende` für die Rückseite des Vorstosses,
 * - `absatz` für alles übrige.
 *
 * Angezeigt und durchsucht wird, was der Leser wirklich braucht: Das Zitat
 * steht schon im Vorstoss selbst, und die Liste der Unterzeichnenden ist ein
 * Formular. Beide bleiben in der Struktur, aber aus Markdown und Volltext
 * heraus.
 */
class DokumentInhaltParser
{
    /** Ab diesem Vielfachen des üblichen Zeilenabstands beginnt ein neuer Block. */
    private const ABSTAND_FAKTOR = 1.25;

    /** So viel grösser als der Lauftext muss eine Schrift für einen Titel sein. */
    private const TITEL_FAKTOR = 1.2;

    /** Eine Überschrift ist kurz; darüber liest sich eine Zeile als Lauftext. */
    private const UEBERSCHRIFT_MAX_ZEICHEN = 120;

    /** So nah beieinander gelten zwei x-Positionen als dieselbe Spalte. */
    private const SPALTEN_TOLERANZ = 6.0;

    /** So weit stehen zwei Stücke auseinander, damit es zwei Spalten sind. */
    private const SPALTEN_ABSTAND = 40.0;

    /** So viele Zeilen braucht eine Tabelle mindestens. */
    private const TABELLE_MIN_ZEILEN = 3;

    /** So breit ist höchstens eine Spalte, in der Zahlen rechtsbündig stehen. */
    private const SPALTEN_BREITE = 60.0;

    /** Arten, die zum Inhalt gehören, aber nicht in die Anzeige. */
    private const NICHT_ANZEIGEN = ['zitat', 'unterzeichnende'];

    public function __construct(private PdfZeilenLeser $leser)
    {
    }

    /**
     * Die Abschnitte des Dokuments, in der Reihenfolge des Textes.
     *
     * @return list<array<string, mixed>>
     */
    public function abschnitte(string $pfad): array
    {
        $seiten = $this->leser->seitenZeilen($pfad);
        $zeilen = $this->zeilenMitSeite($seiten);
        if ($zeilen === []) {
            return [];
        }
        $lauftext = $this->haeufigsteGroesse($zeilen);
        $rand = $this->linkerRand($zeilen);
        $abstand = $this->ueblicherAbstand($zeilen) * self::ABSTAND_FAKTOR;

        $abschnitte = [];
        $titelVergeben = false;
        foreach ($this->bloecke($zeilen, $abstand, $rand) as $block) {
            $abschnitt = $this->abschnitt($block, $lauftext, $rand, $titelVergeben);
            if ($abschnitt['art'] === 'titel') {
                $titelVergeben = true;
            }
            $abschnitte[] = $abschnitt;
        }
        return $this->markiereUnterzeichnende($abschnitte);
    }

    /**
     * Der Inhalt als Markdown — das, was in der Anzeige steht.
     *
     * @param list<array<string, mixed>> $abschnitte
     */
    public function markdown(array $abschnitte): string
    {
        $stuecke = [];
        foreach ($abschnitte as $abschnitt) {
            if (\in_array($abschnitt['art'], self::NICHT_ANZEIGEN, true)) {
                continue;
            }
            $stuecke[] = match ($abschnitt['art']) {
                'titel' => '# ' . $abschnitt['text'],
                'ueberschrift' => '## ' . $abschnitt['text'],
                'liste' => $this->listeAlsMarkdown($abschnitt['punkte'] ?? []),
                'tabelle' => $this->tabelleAlsMarkdown($abschnitt['zeilen'] ?? []),
                default => $abschnitt['text'],
            };
        }
        return implode("\n\n", array_filter($stuecke, static fn (string $s): bool => $s !== '')) . "\n";
    }

    /**
     * Der Inhalt als reiner Text — das, worin die Suche sucht.
     *
     * @param list<array<string, mixed>> $abschnitte
     */
    public function volltext(array $abschnitte): string
    {
        $stuecke = [];
        foreach ($abschnitte as $abschnitt) {
            if (\in_array($abschnitt['art'], self::NICHT_ANZEIGEN, true)) {
                continue;
            }
            if ($abschnitt['art'] === 'liste') {
                foreach ($abschnitt['punkte'] ?? [] as $punkt) {
                    $stuecke[] = $punkt;
                }
                continue;
            }
            if ($abschnitt['art'] === 'tabelle') {
                foreach ($abschnitt['zeilen'] ?? [] as $zeile) {
                    $stuecke[] = implode(' ', $zeile);
                }
                continue;
            }
            $stuecke[] = (string) $abschnitt['text'];
        }
        return implode("\n", array_filter($stuecke, static fn (string $s): bool => $s !== ''));
    }

    /**
     * @param list<string> $punkte
     */
    private function listeAlsMarkdown(array $punkte): string
    {
        $zeilen = [];
        foreach ($punkte as $nr => $punkt) {
            $zeilen[] = ($nr + 1) . '. ' . $punkt;
        }
        return implode("\n", $zeilen);
    }

    /**
     * Eine Tabelle als Markdown-Tabelle. Die erste Zeile wird zum Kopf: Eine
     * Tabelle ohne Kopf zeigt Markdown nicht an.
     *
     * @param list<list<string>> $zeilen
     */
    private function tabelleAlsMarkdown(array $zeilen): string
    {
        if ($zeilen === []) {
            return '';
        }
        $breite = 0;
        foreach ($zeilen as $zeile) {
            $breite = max($breite, \count($zeile));
        }
        $fuellen = static function (array $zeile) use ($breite): string {
            $zellen = array_pad($zeile, $breite, '');
            $zellen = array_map(
                static fn (string $z): string => str_replace('|', '\\|', trim($z)),
                $zellen
            );
            return '| ' . implode(' | ', $zellen) . ' |';
        };
        $ausgabe = [$fuellen($zeilen[0]), '|' . str_repeat(' --- |', $breite)];
        foreach (\array_slice($zeilen, 1) as $zeile) {
            $ausgabe[] = $fuellen($zeile);
        }
        return implode("\n", $ausgabe);
    }

    /**
     * Alle Zeilen aller Seiten hintereinander, jede mit ihrer Seitenzahl. Was
     * auf jeder Seite gleich wiederkehrt — Kopf- und Fusszeilen — fällt weg.
     *
     * @param list<list<array<string, mixed>>> $seiten
     * @return list<array<string, mixed>>
     */
    private function zeilenMitSeite(array $seiten): array
    {
        $wiederkehrend = $this->wiederkehrendeZeilen($seiten);
        $alle = [];
        foreach ($seiten as $nr => $zeilen) {
            foreach ($zeilen as $zeile) {
                if (isset($wiederkehrend[$zeile['text']])) {
                    continue;
                }
                $zeile['seite'] = $nr + 1;
                $alle[] = $zeile;
            }
        }
        return $alle;
    }

    /**
     * Zeilen, die auf jeder Seite eines mehrseitigen Dokuments stehen: Absender,
     * Geschäftsnummer im Fuss, Seitenzahl. Sie tragen nichts zum Inhalt bei und
     * zerschneiden die Absätze, die über einen Seitenwechsel laufen.
     *
     * @param list<list<array<string, mixed>>> $seiten
     * @return array<string, true>
     */
    private function wiederkehrendeZeilen(array $seiten): array
    {
        if (\count($seiten) < 2) {
            return [];
        }
        $zaehler = [];
        foreach ($seiten as $zeilen) {
            foreach (array_unique(array_column($zeilen, 'text')) as $text) {
                $zaehler[$text] = ($zaehler[$text] ?? 0) + 1;
            }
        }
        $wiederkehrend = [];
        foreach ($zaehler as $text => $anzahl) {
            if ($anzahl === \count($seiten)) {
                $wiederkehrend[(string) $text] = true;
            }
        }
        return $wiederkehrend;
    }

    /**
     * Die Schriftgrösse, in der die meisten Zeichen des Dokuments stehen — der
     * Lauftext, an dem sich Titel und Überschriften messen.
     *
     * @param list<array<string, mixed>> $zeilen
     */
    private function haeufigsteGroesse(array $zeilen): float
    {
        // Gezählt wird nur der Lauftext: In einem Budgetbuch stellen die
        // Tabellen die Masse der Zeichen und in einer kleineren Schrift, und
        // gemessen an ihr wäre jeder Satz des Fliesstexts eine Überschrift.
        $zeichen = [];
        foreach ($zeilen as $zeile) {
            if ($this->istTabellenzeile($zeile)) {
                continue;
            }
            $schluessel = (string) round((float) $zeile['groesse'], 1);
            $zeichen[$schluessel] = ($zeichen[$schluessel] ?? 0) + mb_strlen((string) $zeile['text']);
        }
        if ($zeichen === []) {
            foreach ($zeilen as $zeile) {
                $schluessel = (string) round((float) $zeile['groesse'], 1);
                $zeichen[$schluessel] = ($zeichen[$schluessel] ?? 0) + mb_strlen((string) $zeile['text']);
            }
        }
        arsort($zeichen);
        return (float) (array_key_first($zeichen) ?? 0);
    }

    /**
     * Der linke Rand des Lauftextes: der Einzug, an dem die meisten Zeilen
     * beginnen. Alles weiter rechts ist eingezogen.
     *
     * @param list<array<string, mixed>> $zeilen
     */
    private function linkerRand(array $zeilen): float
    {
        $haeufig = [];
        foreach ($zeilen as $zeile) {
            $schluessel = (string) round((float) $zeile['x'], 0);
            $haeufig[$schluessel] = ($haeufig[$schluessel] ?? 0) + 1;
        }
        arsort($haeufig);
        return (float) (array_key_first($haeufig) ?? 0);
    }

    /**
     * Der Zeilenabstand innerhalb eines Absatzes: der Median aller Abstände.
     *
     * @param list<array<string, mixed>> $zeilen
     */
    private function ueblicherAbstand(array $zeilen): float
    {
        $abstaende = [];
        for ($i = 1, $n = \count($zeilen); $i < $n; ++$i) {
            if ($zeilen[$i]['seite'] !== $zeilen[$i - 1]['seite']) {
                continue;
            }
            $d = round((float) $zeilen[$i - 1]['y'] - (float) $zeilen[$i]['y'], 1);
            if ($d > 0) {
                $abstaende[] = $d;
            }
        }
        if ($abstaende === []) {
            return 0.0;
        }
        sort($abstaende);
        return $abstaende[intdiv(\count($abstaende), 2)];
    }

    /**
     * Die Zeilen in Blöcke geschnitten: ein grösserer Sprung, ein Seitenwechsel,
     * ein Wechsel der Auszeichnung oder ein neuer Listenpunkt beginnt einen.
     * Zeilen, die dieselben Spalten benutzen, bleiben als Tabelle zusammen.
     *
     * @param list<array<string, mixed>> $zeilen
     * @return list<list<array<string, mixed>>>
     */
    private function bloecke(array $zeilen, float $abstand, float $rand): array
    {
        $bloecke = [];
        $block = [];
        foreach ($zeilen as $i => $zeile) {
            if ($block !== []) {
                $vorher = $zeilen[$i - 1];
                $neu = $zeile['seite'] !== $vorher['seite']
                    || round((float) $zeile['groesse'], 1) !== round((float) $vorher['groesse'], 1)
                    || $zeile['fett'] !== $vorher['fett']
                    || ($zeile['kursiv'] ?? false) !== ($vorher['kursiv'] ?? false)
                    || ($this->istListenpunkt($zeile, $rand) && !$this->istListenpunkt($vorher, $rand))
                    || ($abstand > 0
                        && $zeile['seite'] === $vorher['seite']
                        && (float) $vorher['y'] - (float) $zeile['y']
                            > max($abstand, (float) $zeile['groesse'] * 1.45));

                // Eine Liste bleibt ein Block: Ihre Punkte stehen weiter
                // auseinander als die Zeilen eines Absatzes, und die Folgezeile
                // eines Punktes ist tiefer eingezogen als der Punkt selbst.
                $gehoertZurListe = $this->istListenpunkt($block[0], $rand)
                    && $zeile['seite'] === $vorher['seite']
                    && (float) $zeile['x'] >= (float) $block[0]['x']
                    && round((float) $zeile['groesse'], 1) === round((float) $block[0]['groesse'], 1);
                if ($neu && $gehoertZurListe) {
                    $neu = false;
                }

                // Eine Tabelle entscheidet allein über ihre Spalten: Ihre Zeilen
                // stehen weiter auseinander als die eines Absatzes, ihre
                // Kopfzeile steht fett über den Datenzeilen, und eine
                // Zwischensumme wechselt die Schriftgrösse — nichts davon darf
                // sie zerschneiden. Sie endet dort, wo die Spalten aufhören.
                $teilenSpalten = $zeile['seite'] === $vorher['seite']
                    && $this->teilenSpalten($vorher, $zeile);
                $imTabellenblock = \count($block) >= 2
                    && $this->teilenSpalten($block[0], $block[1]);
                if ($teilenSpalten || $imTabellenblock) {
                    $neu = !$teilenSpalten;
                }

                if ($neu) {
                    $bloecke[] = $block;
                    $block = [];
                }
            }
            $block[] = $zeile;
        }
        if ($block !== []) {
            $bloecke[] = $block;
        }
        return $this->kopfzeilenAnTabellen($bloecke);
    }

    /**
     * Holt die Kopfzeilen an ihre Tabelle. Ein Tabellenkopf steht über zwei bis
     * drei Zeilen («Budget» oben, «2022» darunter, «in Mio. CHF» zuunterst), und
     * jede dieser Zeilen setzt ihre Stücke etwas anders — nach den Spalten allein
     * gehört sie darum noch nicht dazu. Dieselbe Beobachtung steht im
     * Drehbuch-Parser, der den Kopf ebenfalls über mehrere Zeilen liest.
     *
     * @param list<list<array<string, mixed>>> $bloecke
     * @return list<list<array<string, mixed>>>
     */
    private function kopfzeilenAnTabellen(array $bloecke): array
    {
        foreach ($bloecke as $index => $block) {
            if ($index === 0 || \count($block) < self::TABELLE_MIN_ZEILEN || !$this->istTabelle($block)) {
                continue;
            }
            $vorheriger = $bloecke[$index - 1];
            $kopf = [];
            while ($vorheriger !== [] && \count($kopf) < 3) {
                $kandidat = $vorheriger[\count($vorheriger) - 1];
                if (!$this->istTabellenzeile($kandidat)
                    || $kandidat['seite'] !== $block[0]['seite']) {
                    break;
                }
                array_pop($vorheriger);
                array_unshift($kopf, $kandidat);
            }
            if ($kopf === []) {
                continue;
            }
            $bloecke[$index - 1] = $vorheriger;
            $bloecke[$index] = array_merge($kopf, $block);
        }
        return array_values(array_filter($bloecke, static fn (array $b): bool => $b !== []));
    }

    /**
     * Eine Zeile mit mehreren Textstücken, die deutlich auseinander stehen: der
     * Kandidat für eine Tabellenzeile.
     *
     * @param array<string, mixed> $zeile
     */
    private function istTabellenzeile(array $zeile): bool
    {
        $stuecke = $zeile['stuecke'] ?? [];
        if (\count($stuecke) < 2) {
            return false;
        }
        // Ein Listenpunkt trägt seine Nummer als eigenes Stück und stünde sonst
        // als zweispaltige Zeile da.
        if (preg_match('/^(\d+[.)]|[-–—•*])$/u', (string) $stuecke[0]['text']) === 1) {
            return false;
        }
        // Zwei Stücke direkt nebeneinander sind ein Wortwechsel innerhalb einer
        // Zeile (eine andere Schrift, ein Bindestrich), keine zweite Spalte.
        for ($i = 1, $n = \count($stuecke); $i < $n; ++$i) {
            if ((float) $stuecke[$i]['x'] - (float) $stuecke[$i - 1]['x'] > self::SPALTEN_ABSTAND) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ob zwei Zeilen mindestens zwei Spalten gemeinsam haben — das Merkmal einer
     * Tabelle, aus derselben Beobachtung wie im Drehbuch-Parser: Die Stücke
     * einer Spalte beginnen über die Zeilen hinweg an derselben x-Position.
     *
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function teilenSpalten(array $a, array $b): bool
    {
        if (!$this->istTabellenzeile($a) || !$this->istTabellenzeile($b)) {
            return false;
        }
        $gemeinsam = 0;
        foreach ($a['stuecke'] as $links) {
            foreach ($b['stuecke'] as $rechts) {
                if (abs((float) $links['x'] - (float) $rechts['x']) <= self::SPALTEN_TOLERANZ) {
                    ++$gemeinsam;
                    break;
                }
            }
        }
        return $gemeinsam >= 2;
    }

    /**
     * @param array<string, mixed> $zeile
     */
    private function istListenpunkt(array $zeile, float $rand): bool
    {
        return (float) $zeile['x'] > $rand + 1
            && preg_match('/^(\d+[.)]|[-–—•*])\s+\S/u', (string) $zeile['text']) === 1;
    }

    /**
     * @param list<array<string, mixed>> $block
     * @return array<string, mixed>
     */
    private function abschnitt(array $block, float $lauftext, float $rand, bool $titelVergeben): array
    {
        $seite = (int) $block[0]['seite'];
        $groesse = (float) $block[0]['groesse'];
        $fett = (bool) $block[0]['fett'];
        $kursiv = (bool) ($block[0]['kursiv'] ?? false);
        $text = $this->zusammengefuegt($block);

        if (\count($block) >= self::TABELLE_MIN_ZEILEN && $this->istTabelle($block)) {
            return [
                'art' => 'tabelle',
                'seite' => $seite,
                'text' => $text,
                'zeilen' => $this->tabellenzeilen($block),
            ];
        }
        if ($this->istListenpunkt($block[0], $rand)) {
            return ['art' => 'liste', 'seite' => $seite, 'text' => $text, 'punkte' => $this->punkte($block)];
        }
        // Die Antwort des Stadtrats wiederholt die Anfrage kursiv und in
        // Anführungszeichen. Sie steht schon im Vorstoss selbst, also gehört
        // sie in die Struktur, aber nicht in die Anzeige.
        if ($kursiv) {
            return ['art' => 'zitat', 'seite' => $seite, 'text' => $text];
        }
        if (!$titelVergeben && $seite === 1 && $groesse >= $lauftext * self::TITEL_FAKTOR) {
            return ['art' => 'titel', 'seite' => $seite, 'text' => $text];
        }
        if ($groesse > $lauftext * self::TITEL_FAKTOR) {
            return ['art' => 'ueberschrift', 'seite' => $seite, 'text' => $text];
        }
        if ($fett
            && \count($block) === 1
            && mb_strlen($text) <= self::UEBERSCHRIFT_MAX_ZEICHEN
            && preg_match('/[.;:!?]$/u', $text) !== 1) {
            return ['art' => 'ueberschrift', 'seite' => $seite, 'text' => $text];
        }
        return ['art' => 'absatz', 'seite' => $seite, 'text' => $text];
    }

    /**
     * @param list<array<string, mixed>> $block
     */
    private function istTabelle(array $block): bool
    {
        $n = \count($block);
        if ($n < self::TABELLE_MIN_ZEILEN) {
            return false;
        }
        // Die Kopfzeilen setzen ihre Stücke anders als die Datenzeilen darunter
        // («Budget» über «2022» über «in Mio. CHF»), darum entscheidet die
        // Mehrheit der Zeilenpaare und nicht jedes einzelne.
        $geteilt = 0;
        for ($i = 1; $i < $n; ++$i) {
            if ($this->teilenSpalten($block[$i - 1], $block[$i])) {
                ++$geteilt;
            }
        }
        return $geteilt >= 2 && $geteilt * 2 >= $n - 1;
    }

    /**
     * Die Zellen einer Tabelle: Die Spalten entstehen aus den x-Positionen aller
     * Zeilen, jedes Textstück gehört zur nächstgelegenen — dieselbe Zuordnung
     * wie im Drehbuch-Parser.
     *
     * @param list<array<string, mixed>> $block
     * @return list<list<string>>
     */
    private function tabellenzeilen(array $block): array
    {
        $positionen = [];
        foreach ($block as $zeile) {
            foreach ($zeile['stuecke'] as $stueck) {
                $x = (float) $stueck['x'];
                foreach ($positionen as $bekannt) {
                    if (abs($bekannt - $x) <= self::SPALTEN_TOLERANZ) {
                        continue 2;
                    }
                }
                $positionen[] = $x;
            }
        }
        sort($positionen);
        $positionen = $this->fasseSpaltenZusammen($block, $positionen);

        $zeilen = [];
        foreach ($block as $zeile) {
            $zellen = array_fill(0, \count($positionen), '');
            foreach ($zeile['stuecke'] as $stueck) {
                $index = $this->naechsteSpalte((float) $stueck['x'], $positionen);
                $zellen[$index] = $this->zelleErweitern($zellen[$index], (string) $stueck['text']);
            }
            $zeilen[] = array_values($zellen);
        }
        return $this->ohneLeereSpalten($zeilen);
    }

    /**
     * Hängt ein Textstück an eine Zelle. Ein alleinstehendes Minus gehört zur
     * folgenden Zahl («- 690.7» ist «-690.7»), und ein Bindestrich mitten im
     * Wort trennt nicht («Sach- und übriger»).
     */
    private function zelleErweitern(string $zelle, string $stueck): string
    {
        $stueck = trim($stueck);
        if ($zelle === '') {
            return $stueck;
        }
        if (preg_match('/[-–]$/u', $zelle) === 1) {
            return $zelle . $stueck;
        }
        return $zelle . ' ' . $stueck;
    }

    /**
     * Spalten, in denen keine Zeile etwas stehen hat, fallen weg: Sie entstehen
     * aus den Kopfzeilen, die ihre Wörter anders setzen als die Datenzeilen.
     *
     * @param list<list<string>> $zeilen
     * @return list<list<string>>
     */
    private function ohneLeereSpalten(array $zeilen): array
    {
        if ($zeilen === []) {
            return [];
        }
        $behalten = [];
        foreach (array_keys($zeilen[0]) as $spalte) {
            foreach ($zeilen as $zeile) {
                if (trim($zeile[$spalte] ?? '') !== '') {
                    $behalten[] = $spalte;
                    break;
                }
            }
        }
        $gefiltert = [];
        foreach ($zeilen as $zeile) {
            $neu = [];
            foreach ($behalten as $spalte) {
                $neu[] = $zeile[$spalte] ?? '';
            }
            $gefiltert[] = $neu;
        }
        return $gefiltert;
    }

    /**
     * Fasst benachbarte x-Positionen zu einer Spalte zusammen.
     *
     * Eine Zahlenspalte steht rechtsbündig: «45.3» beginnt weiter rechts als
     * «- 690.7», obwohl beide in derselben Spalte stehen. Zwei Positionen
     * gehören darum zusammen, wenn sie nahe beieinander liegen UND keine Zeile
     * beide zugleich benutzt — dann kann es keine zwei Spalten sein.
     *
     * @param list<array<string, mixed>> $block
     * @param list<float> $positionen
     * @return list<float>
     */
    private function fasseSpaltenZusammen(array $block, array $positionen): array
    {
        $belegung = [];
        foreach ($positionen as $index => $position) {
            $belegung[$index] = [];
            foreach ($block as $nr => $zeile) {
                foreach ($zeile['stuecke'] as $stueck) {
                    if (abs((float) $stueck['x'] - $position) <= self::SPALTEN_TOLERANZ) {
                        $belegung[$index][$nr] = true;
                    }
                }
            }
        }

        $zusammen = [];
        $offen = null;
        $offenBelegung = [];
        foreach ($positionen as $index => $position) {
            if ($offen === null) {
                $offen = $position;
                $offenBelegung = $belegung[$index];
                continue;
            }
            $nah = $position - $offen <= self::SPALTEN_BREITE;
            $ueberschneidung = array_intersect_key($offenBelegung, $belegung[$index]) !== [];
            if ($nah && !$ueberschneidung) {
                $offenBelegung += $belegung[$index];
                continue;
            }
            $zusammen[] = $offen;
            $offen = $position;
            $offenBelegung = $belegung[$index];
        }
        if ($offen !== null) {
            $zusammen[] = $offen;
        }
        return $zusammen;
    }

    /**
     * @param list<float> $positionen
     */
    private function naechsteSpalte(float $x, array $positionen): int
    {
        $beste = 0;
        $abstand = null;
        foreach ($positionen as $index => $position) {
            $d = abs($position - $x);
            if ($abstand === null || $d < $abstand) {
                $abstand = $d;
                $beste = $index;
            }
        }
        return $beste;
    }

    /**
     * Die Rückseite des Vorstosses: das Formular mit den Unterzeichnenden. Sie
     * beginnt mit «Vorstoss-Rückseite» oder mit der Zeile «Unterstützende (X)»
     * und reicht bis zum Ende des Dokuments.
     *
     * @param list<array<string, mixed>> $abschnitte
     * @return list<array<string, mixed>>
     */
    private function markiereUnterzeichnende(array $abschnitte): array
    {
        $ab = null;
        foreach ($abschnitte as $index => $abschnitt) {
            $text = (string) $abschnitt['text'];
            if (preg_match('/Vorstoss-Rückseite|Unterstützende\s*\(X\)/u', $text) === 1) {
                $ab = $index;
                break;
            }
        }
        if ($ab === null) {
            return $abschnitte;
        }
        for ($i = $ab, $n = \count($abschnitte); $i < $n; ++$i) {
            $abschnitte[$i]['art'] = 'unterzeichnende';
        }
        return $abschnitte;
    }

    /**
     * Die Punkte einer Liste, jeder ohne seine Nummer und mit seinen
     * Folgezeilen in einem Stück.
     *
     * @param list<array<string, mixed>> $block
     * @return list<string>
     */
    private function punkte(array $block): array
    {
        $punkte = [];
        $aktuell = '';
        foreach ($block as $zeile) {
            $text = (string) $zeile['text'];
            if (preg_match('/^(\d+[.)]|[-–—•*])\s+(.*)$/u', $text, $treffer) === 1) {
                if ($aktuell !== '') {
                    $punkte[] = $aktuell;
                }
                $aktuell = trim($treffer[2]);
                continue;
            }
            $aktuell = trim($aktuell . ' ' . $text);
        }
        if ($aktuell !== '') {
            $punkte[] = $aktuell;
        }
        return $punkte;
    }

    /**
     * Die Zeilen eines Blocks wieder zu einem Stück Text: Was das PDF umbrochen
     * hat, steht wieder in einer Zeile, und eine Silbentrennung am Zeilenende
     * wird aufgehoben.
     *
     * @param list<array<string, mixed>> $block
     */
    private function zusammengefuegt(array $block): string
    {
        $text = '';
        foreach ($block as $zeile) {
            $stueck = (string) $zeile['text'];
            if ($text === '') {
                $text = $stueck;
                continue;
            }
            if (preg_match('/\p{Ll}-$/u', $text) === 1) {
                $text = mb_substr($text, 0, -1) . $stueck;
                continue;
            }
            $text .= ' ' . $stueck;
        }
        return trim($text);
    }
}
