<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\XlsxDatei;
use PHPUnit\Framework\TestCase;

/**
 * Die erzeugte Tabellenkalkulationsdatei (F138) muss ein Tabellenprogramm
 * öffnen können: ein ZIP mit den Teilen, die das Format verlangt, Zahlen als
 * Zahlen und Text mit ersetzten Sonderzeichen. Eine Datei, die zwar entsteht
 * und beim Öffnen eine Fehlermeldung bringt, ist kein Export.
 */
final class XlsxDateiTest extends TestCase
{
    /** @return array<string, string> Teilname zu Inhalt */
    private function teile(string $datei): array
    {
        $pfad = tempnam(sys_get_temp_dir(), 'parlwin-test-xlsx-');
        self::assertNotFalse($pfad);
        file_put_contents($pfad, $datei);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($pfad) === true, 'die Datei ist kein lesbares ZIP');
        $teile = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $teile[$name] = (string) $zip->getFromIndex($i);
        }
        $zip->close();
        unlink($pfad);
        return $teile;
    }

    public function testDieDateiTraegtAlleTeileDesFormats(): void
    {
        $teile = $this->teile(XlsxDatei::erzeugen('Anträge', [['Spalte'], ['Wert']]));

        foreach ([
            '[Content_Types].xml',
            '_rels/.rels',
            'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/styles.xml',
            'xl/worksheets/sheet1.xml',
        ] as $teil) {
            self::assertArrayHasKey($teil, $teile, 'der Teil ' . $teil . ' fehlt in der Datei');
        }
        self::assertStringContainsString('name="Anträge"', $teile['xl/workbook.xml']);
        // Jede Beziehung, die die Mappe nennt, muss es auch geben — sonst
        // meldet das Tabellenprogramm eine beschädigte Datei.
        self::assertStringContainsString('Target="worksheets/sheet1.xml"', $teile['xl/_rels/workbook.xml.rels']);
        self::assertStringContainsString('Target="styles.xml"', $teile['xl/_rels/workbook.xml.rels']);
    }

    public function testZahlenBleibenZahlenUndTextBleibtText(): void
    {
        $blatt = $this->teile(XlsxDatei::erzeugen('Test', [
            ['Bereich', 'Betrag'],
            ['Globalbudget', -50000],
            ['Personal', 2.5],
        ]))['xl/worksheets/sheet1.xml'];

        // Eine Zahl steht als Wert da und lässt sich summieren.
        self::assertStringContainsString('<v>-50000</v>', $blatt, 'der Betrag steht nicht als Zahl in der Zelle');
        self::assertStringContainsString('<v>2.5</v>', $blatt, 'die Stellenzahl steht nicht als Zahl in der Zelle');
        // Text steht in der Zelle selbst.
        self::assertStringContainsString('t="inlineStr"', $blatt);
        self::assertStringContainsString('<t xml:space="preserve">Globalbudget</t>', $blatt);
        // Die Kopfzeile ist fett (Format 1) und bleibt beim Rollen stehen.
        self::assertStringContainsString('<c r="A1" s="1"', $blatt);
        self::assertStringContainsString('state="frozen"', $blatt);
        self::assertStringContainsString('<autoFilter ref="A1:B3"', $blatt, 'der Filter deckt nicht alle Zeilen und Spalten');
    }

    /**
     * Das Zahlenformat zeigt die Zahl, die in der Zelle steht. Mit dem
     * eingebauten Format «#,##0» (numFmtId 3) zeigte ein Tabellenleser für
     * −1.5 Stellen «−2» an — eine andere Zahl, als die Anwendung führt
     * (gemessen am 24.10.2026).
     */
    public function testDasZahlenformatRundetKeineHalbenStellenWeg(): void
    {
        $formate = $this->teile(XlsxDatei::erzeugen('Test', [['Stellen'], [-1.5]]))['xl/styles.xml'];

        self::assertStringContainsString('formatCode="#,##0.##"', $formate, 'das eigene Zahlenformat fehlt');
        preg_match('/<numFmt numFmtId="(\d+)"/', $formate, $treffer);
        self::assertNotEmpty($treffer, 'es ist kein eigenes Zahlenformat eingetragen');
        self::assertGreaterThanOrEqual(164, (int) $treffer[1], 'eigene Zahlenformate beginnen bei 164');
        self::assertStringContainsString(
            '<xf numFmtId="' . $treffer[1] . '"',
            $formate,
            'das Zahlenformat wird von keinem Format verwendet',
        );
    }

    public function testSonderzeichenUndSteuerzeichenBrechenDieDateiNicht(): void
    {
        $blatt = $this->teile(XlsxDatei::erzeugen('Test', [
            ['Begründung'],
            ["Kürzung < 5% & \u{201E}nötig\u{201C}\x07"],
        ]))['xl/worksheets/sheet1.xml'];

        self::assertStringContainsString('&lt; 5% &amp;', $blatt, 'spitze Klammer und Ampersand sind nicht ersetzt');
        self::assertStringNotContainsString("\x07", $blatt, 'ein Steuerzeichen steht in der Datei');
        self::assertStringContainsString('nötig', $blatt);
    }

    public function testEinLangerBlattnameWirdGekuerztUndGesaeubert(): void
    {
        $mappe = $this->teile(XlsxDatei::erzeugen('Budget/2027: Anträge der Fraktion [alle]', [['x']]))['xl/workbook.xml'];

        self::assertMatchesRegularExpression('/name="([^"]{1,31})"/u', $mappe);
        preg_match('/name="([^"]+)"/u', $mappe, $treffer);
        $name = html_entity_decode($treffer[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
        self::assertLessThanOrEqual(31, mb_strlen($name), 'der Blattname ist länger, als das Format erlaubt');
        foreach (['/', '\\', '*', '?', '[', ']', ':'] as $zeichen) {
            self::assertStringNotContainsString($zeichen, $name, 'der Blattname trägt ein verbotenes Zeichen');
        }
    }

    public function testSpaltenNamenFolgenDemFormat(): void
    {
        self::assertSame('A', XlsxDatei::spaltenName(0));
        self::assertSame('Z', XlsxDatei::spaltenName(25));
        self::assertSame('AA', XlsxDatei::spaltenName(26));
        self::assertSame('AB', XlsxDatei::spaltenName(27));
    }
}
