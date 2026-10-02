<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\DokumentInhaltParser;
use OCA\ParliamentWinterthur\Service\PdfZeilenLeser;
use PHPUnit\Framework\TestCase;

/**
 * Der Inhalt eines amtlichen Dokuments (F121): Aus dem PDF entstehen
 * Abschnitte mit Art, Stufe und Seite; daraus Markdown für die Anzeige und
 * Volltext für die Suche. Gelesen wird gegen vier echte Dokumente des
 * Parlaments — eine Schriftliche Anfrage mit nummerierter Liste, eine
 * Medienmitteilung mit fettem Lauftext, eine Motion mit der Rückseite der
 * Unterzeichnenden, die Antwort des Stadtrats mit der kursiv wiederholten
 * Anfrage und den Novemberbrief mit seinen Zahlentabellen.
 *
 * @group pdf
 */
class DokumentInhaltParserTest extends TestCase
{
    private function parser(): DokumentInhaltParser
    {
        return new DokumentInhaltParser(new PdfZeilenLeser());
    }

    private function pfad(string $datei): string
    {
        return \dirname(__DIR__, 1) . '/Fixtures/dokumente/' . $datei;
    }

    /** @return array<int, array<string, mixed>> */
    private function anfrage(): array
    {
        return $this->parser()->abschnitte($this->pfad('vorstoss-2026-15.pdf'));
    }

    /** @return array<int, array<string, mixed>> */
    private function arten(array $abschnitte): array
    {
        return array_column($abschnitte, 'art');
    }

    public function testDerTitelStehtAlsErsterAbschnitt(): void
    {
        $erster = $this->anfrage()[0] ?? [];
        self::assertSame('titel', $erster['art'] ?? '');
        self::assertSame('Schriftliche Anfrage', $erster['text'] ?? '');
        self::assertSame(1, $erster['seite'] ?? 0);
    }

    public function testDieFettenEinzelzeilenSindUeberschriften(): void
    {
        $ueberschriften = [];
        foreach ($this->anfrage() as $abschnitt) {
            if (($abschnitt['art'] ?? '') === 'ueberschrift') {
                $ueberschriften[] = $abschnitt['text'];
            }
        }
        self::assertSame(['Anfrage', 'Begründung'], $ueberschriften);
    }

    public function testDieNummerierteListeStehtAlsListe(): void
    {
        $listen = array_values(array_filter(
            $this->anfrage(),
            static fn (array $a): bool => ($a['art'] ?? '') === 'liste',
        ));
        self::assertCount(1, $listen, 'die Anfrage führt genau eine Liste');
        self::assertCount(7, $listen[0]['punkte'], 'sieben Fragen');
        self::assertStringStartsWith('Deren Typ, wie Grundstück', $listen[0]['punkte'][0]);
        self::assertStringEndsWith(
            'Restaurant, Gewerbe, …?',
            $listen[0]['punkte'][0],
            'die Folgezeile gehört zum selben Punkt',
        );
        self::assertSame('Was ist der Grund dieser erheblichen Schwankungen?', $listen[0]['punkte'][6]);
    }

    public function testEinUmbrochenerAbsatzWirdWiederEinAbsatz(): void
    {
        $treffer = array_values(array_filter(
            array_column($this->anfrage(), 'text'),
            static fn (string $t): bool => str_starts_with($t, 'Immobilien im Finanzvermögen dienen'),
        ));
        self::assertCount(1, $treffer);
        self::assertStringContainsString(
            'Restaurants, Büros oder Wohnungen.',
            $treffer[0],
            'die sieben Zeilen des Absatzes stehen in einem Stück',
        );
        self::assertStringNotContainsString("\n", $treffer[0]);
    }

    public function testDerAbschnittTraegtSeineSeite(): void
    {
        $abschnitte = $this->anfrage();
        self::assertSame(2, $abschnitte[array_key_last($abschnitte)]['seite'], 'der Schluss steht auf Seite 2');
    }

    public function testDasMarkdownZeichnetTitelUeberschriftUndListeAus(): void
    {
        $markdown = $this->parser()->markdown($this->anfrage());
        self::assertStringStartsWith('# Schriftliche Anfrage', $markdown);
        self::assertStringContainsString("\n## Anfrage\n", $markdown);
        self::assertStringContainsString("\n## Begründung\n", $markdown);
        self::assertStringContainsString('1. Deren Typ, wie Grundstück', $markdown);
        self::assertStringContainsString('7. Was ist der Grund dieser erheblichen Schwankungen?', $markdown);
    }

    public function testDerVolltextTraegtDenInhaltOhneAuszeichnung(): void
    {
        $volltext = $this->parser()->volltext($this->anfrage());
        self::assertStringContainsString('Zweck der Immobilien im Finanzhaushalt', $volltext);
        self::assertStringContainsString('Was ist der Grund dieser erheblichen Schwankungen?', $volltext);
        self::assertStringNotContainsString('#', $volltext);
        self::assertStringNotContainsString('1. Deren Typ', $volltext, 'die Nummern gehören zur Auszeichnung');
    }

    public function testEinFetterLauftextBleibtEinAbsatz(): void
    {
        $abschnitte = $this->parser()->abschnitte($this->pfad('medienmitteilung-budget-2027.pdf'));
        $lead = null;
        foreach ($abschnitte as $abschnitt) {
            if (str_starts_with($abschnitt['text'], 'Der Stadtrat legt dem Parlament')) {
                $lead = $abschnitt;
                break;
            }
        }
        self::assertNotNull($lead, 'der Lauftext der Mitteilung steht im Inhalt');
        self::assertSame('absatz', $lead['art'], 'fett über mehrere Zeilen und mit Punkt am Ende ist ein Absatz');
    }

    public function testDieFetteEinzelzeileDerMitteilungIstEineUeberschrift(): void
    {
        $abschnitte = $this->parser()->abschnitte($this->pfad('medienmitteilung-budget-2027.pdf'));
        $arten = [];
        foreach ($abschnitte as $abschnitt) {
            $arten[$abschnitt['text']] = $abschnitt['art'];
        }
        self::assertSame(
            'ueberschrift',
            $arten['Erneut stark steigende Kosten in den Bereichen Bildung, Soziales und Pflege'] ?? '',
        );
    }

    public function testDieGroessteSchriftWirdZumTitel(): void
    {
        $abschnitte = $this->parser()->abschnitte($this->pfad('medienmitteilung-budget-2027.pdf'));
        $titel = array_values(array_filter(
            $abschnitte,
            static fn (array $a): bool => $a['art'] === 'titel',
        ));
        self::assertNotSame([], $titel);
        self::assertSame('Medienmitteilung', $titel[0]['text']);
    }

    /**
     * Die Antwort des Stadtrats wiederholt die Anfrage kursiv. Sie steht schon
     * im Vorstoss selbst; in der Antwort ist sie ein Zitat und gehört nicht in
     * die Anzeige.
     */
    public function testDieWiederholteAnfrageWirdAlsZitatErkannt(): void
    {
        $abschnitte = $this->parser()->abschnitte($this->pfad('antwort-stadtrat-2026-15.pdf'));
        $zitate = array_values(array_filter(
            $abschnitte,
            static fn (array $a): bool => $a['art'] === 'zitat',
        ));
        self::assertNotSame([], $zitate, 'die kursive Wiederholung steht als Zitat in der Struktur');
        self::assertStringStartsWith('«Welche Immobilien im Finanzvermögen', $zitate[0]['text']);
    }

    public function testDasZitatFehltInAnzeigeUndSuche(): void
    {
        $abschnitte = $this->parser()->abschnitte($this->pfad('antwort-stadtrat-2026-15.pdf'));
        $markdown = $this->parser()->markdown($abschnitte);
        $volltext = $this->parser()->volltext($abschnitte);
        self::assertStringNotContainsString('«Welche Immobilien im Finanzvermögen', $markdown);
        self::assertStringNotContainsString('«Welche Immobilien im Finanzvermögen', $volltext);
        self::assertStringContainsString('Der Stadtrat erteilt folgende Antwort', $markdown, 'die Antwort selbst bleibt');
    }

    /**
     * Die letzte Seite eines Vorstosses trägt das Formular mit den
     * Unterzeichnenden. Es ist kein Inhalt und gehört nicht in die Anzeige.
     */
    public function testDieRueckseiteMitDenUnterzeichnendenFaelltWeg(): void
    {
        $abschnitte = $this->parser()->abschnitte($this->pfad('vorstoss-2026-86.pdf'));
        self::assertContains('unterzeichnende', $this->arten($abschnitte));
        $markdown = $this->parser()->markdown($abschnitte);
        $volltext = $this->parser()->volltext($abschnitte);
        self::assertStringNotContainsString('Vorstoss-Rückseite', $markdown);
        // Die Kopfzeile des Formulars; «Anzahl Unterstützende: 26» steht
        // dagegen auf der Vorderseite und bleibt.
        self::assertStringNotContainsString('Unterstützende (X)', $markdown);
        self::assertStringNotContainsString('C. Brunel', $volltext);
        self::assertStringContainsString('Baureserven', $markdown, 'der Vorstoss selbst bleibt');
    }

    /**
     * Die Zahlentabellen des Novemberbriefs: Sie stehen im PDF als Textstücke
     * an festen x-Positionen, genau wie sie der Drehbuch-Parser liest. Die
     * Spalten müssen die Zeile überleben.
     */
    public function testEineZahlentabelleBleibtEineTabelle(): void
    {
        $abschnitte = $this->parser()->abschnitte(
            \dirname(__DIR__, 1) . '/Fixtures/novemberbrief/2022/novemberbrief.pdf'
        );
        $tabellen = array_values(array_filter(
            $abschnitte,
            static fn (array $a): bool => $a['art'] === 'tabelle',
        ));
        self::assertNotSame([], $tabellen, 'der Novemberbrief führt Tabellen');

        $departemente = null;
        foreach ($tabellen as $tabelle) {
            foreach ($tabelle['zeilen'] as $zeile) {
                if (($zeile[1] ?? '') === 'Total Stadt') {
                    $departemente = $tabelle;
                    break 2;
                }
            }
        }
        self::assertNotNull($departemente, 'die Tabelle der Departemente steht da');

        $zeile = null;
        foreach ($departemente['zeilen'] as $kandidat) {
            if (($kandidat[1] ?? '') === 'Total Stadt') {
                $zeile = $kandidat;
                break;
            }
        }
        self::assertSame('0', $zeile[0], 'die Nummer steht in der ersten Spalte');
        self::assertContains('-0.2', $zeile, 'das Budget steht in seiner Spalte');
        self::assertContains('1.2', $zeile, 'der Novemberbrief steht in seiner Spalte');
        self::assertContains('1.0', $zeile, 'das neue Budget steht in seiner Spalte');
    }

    public function testDieTabelleStehtAlsMarkdownTabelle(): void
    {
        $abschnitte = $this->parser()->abschnitte(
            \dirname(__DIR__, 1) . '/Fixtures/novemberbrief/2022/novemberbrief.pdf'
        );
        $markdown = $this->parser()->markdown($abschnitte);
        self::assertStringContainsString('| --- |', $markdown, 'eine Markdown-Tabelle trägt ihre Trennzeile');
        self::assertStringContainsString('| Total Stadt |', $markdown);
        self::assertStringContainsString('| -0.2 |', $markdown, 'das Minus gehört zur Zahl');
    }

    /**
     * Im Budgetbuch stellen die Tabellen die Masse der Zeichen und in einer
     * kleineren Schrift als der Lauftext. Wer die häufigste Schriftgrösse für
     * den Lauftext hält, liest jeden Satz als Überschrift.
     */
    public function testDerLauftextBestimmtDieUeberschriften(): void
    {
        $abschnitte = $this->parser()->abschnitte(
            \dirname(__DIR__, 1) . '/Fixtures/novemberbrief/2022/novemberbrief.pdf'
        );
        $arten = array_count_values($this->arten($abschnitte));
        self::assertGreaterThan(
            $arten['ueberschrift'] ?? 0,
            $arten['absatz'] ?? 0,
            'ein Brief hat mehr Absätze als Überschriften',
        );
        $ueberschriften = [];
        foreach ($abschnitte as $abschnitt) {
            if ($abschnitt['art'] === 'ueberschrift') {
                $ueberschriften[] = $abschnitt['text'];
            }
        }
        self::assertContains('1. Nachträge zum Budget 2022', $ueberschriften);
    }
}
