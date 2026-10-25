<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Eine Tabellenkalkulationsdatei (.xlsx) aus Zeilen und Spalten, ohne weitere
 * Abhängigkeit (F138).
 *
 * Eine .xlsx-Datei ist ein ZIP mit XML darin. Für eine Liste aus Text und
 * Zahlen genügen fünf Teile: die Typenliste, zwei Beziehungsdateien, die
 * Arbeitsmappe mit ihrem einen Blatt und das Blatt selbst; die Formate stehen
 * in einem sechsten. Dieselbe Entscheidung trägt schon das Einlesen einer
 * Tabellendatei im Browser (`tabellen-datei.js`): Die Anwendung liest und
 * schreibt dieses Format selbst, statt eine Bibliothek dafür zu tragen.
 *
 * Der Text steht in der Zelle selbst (`inlineStr`), nicht in einer gemeinsamen
 * Zeichenkettentabelle — damit bleibt die Datei in einem Stück lesbar und der
 * Leser in `tabellen-datei.js` kommt mit ihr zurecht.
 */
final class XlsxDatei
{
    /** Kopfzeile: fett. */
    public const STIL_KOPF = 1;
    /** Text: oben ausgerichtet, umgebrochen. */
    public const STIL_TEXT = 2;
    /** Zahl: Tausendertrennzeichen, rechts. */
    public const STIL_ZAHL = 3;
    /** Das eigene Zahlenformat; eigene Formate beginnen bei 164. */
    private const NUMFMT_ZAHL = 164;

    /**
     * Die Datei als Zeichenkette. Die erste Zeile ist die Kopfzeile; sie bleibt
     * beim Rollen stehen und trägt den Filter.
     *
     * @param list<list<string|int|float|null>> $zeilen
     * @param list<float> $breiten Spaltenbreiten in Zeichen, fehlende bleiben Standard
     */
    public static function erzeugen(string $blattName, array $zeilen, array $breiten = []): string
    {
        $datei = tempnam(sys_get_temp_dir(), 'parlwin-xlsx-');
        if ($datei === false) {
            throw new \RuntimeException('Keine temporäre Datei für die Tabelle');
        }
        $zip = new \ZipArchive();
        if ($zip->open($datei, \ZipArchive::OVERWRITE) !== true) {
            unlink($datei);
            throw new \RuntimeException('Tabellendatei lässt sich nicht schreiben');
        }
        $zip->addFromString('[Content_Types].xml', self::typen());
        $zip->addFromString('_rels/.rels', self::beziehungenMappe());
        $zip->addFromString('xl/workbook.xml', self::mappe($blattName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::beziehungenBlatt());
        $zip->addFromString('xl/styles.xml', self::formate());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::blatt($zeilen, $breiten));
        $geschlossen = $zip->close();
        $inhalt = $geschlossen ? (string) file_get_contents($datei) : '';
        unlink($datei);
        if (!$geschlossen || $inhalt === '') {
            throw new \RuntimeException('Tabellendatei konnte nicht geschrieben werden');
        }
        return $inhalt;
    }

    /** «A», «B», … «AA» zur Spaltennummer (0-basiert). */
    public static function spaltenName(int $nummer): string
    {
        $name = '';
        $n = $nummer + 1;
        while ($n > 0) {
            $rest = ($n - 1) % 26;
            $name = chr(65 + $rest) . $name;
            $n = (int) (($n - $rest - 1) / 26);
        }
        return $name;
    }

    /**
     * Ein Blattname trägt höchstens 31 Zeichen und keines der Zeichen, die
     * Tabellenprogramme für Bezüge brauchen.
     */
    private static function sauberBlattName(string $name): string
    {
        $sauber = (string) preg_replace('/[\\\\\\/\\*\\?\\[\\]:]/u', ' ', trim($name));
        $sauber = trim((string) preg_replace('/\s+/u', ' ', $sauber));
        if ($sauber === '') {
            $sauber = 'Tabelle';
        }
        return mb_substr($sauber, 0, 31);
    }

    /**
     * @param list<list<string|int|float|null>> $zeilen
     * @param list<float> $breiten
     */
    private static function blatt(array $zeilen, array $breiten): string
    {
        $spalten = 0;
        foreach ($zeilen as $zeile) {
            $spalten = max($spalten, count($zeile));
        }
        $letzte = self::spaltenName(max(0, $spalten - 1));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $letzte . max(1, count($zeilen)) . '"/>'
            // Die Kopfzeile bleibt beim Rollen stehen — bei fünfzig Anträgen
            // weiss man sonst in der Mitte der Liste nicht mehr, welche Spalte
            // welche ist.
            . '<sheetViews><sheetView workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>';
        if ($breiten !== []) {
            $xml .= '<cols>';
            foreach ($breiten as $i => $breite) {
                $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="'
                    . number_format((float) $breite, 2, '.', '') . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';
        foreach ($zeilen as $nr => $zeile) {
            $xml .= '<row r="' . ($nr + 1) . '">';
            foreach ($zeile as $spalte => $wert) {
                $xml .= self::zelle(self::spaltenName($spalte) . ($nr + 1), $wert, $nr === 0);
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if (count($zeilen) > 1) {
            $xml .= '<autoFilter ref="A1:' . $letzte . count($zeilen) . '"/>';
        }
        return $xml . '</worksheet>';
    }

    private static function zelle(string $bezug, string|int|float|null $wert, bool $kopf): string
    {
        if ($kopf) {
            return '<c r="' . $bezug . '" s="' . self::STIL_KOPF . '" t="inlineStr"><is><t>'
                . self::text((string) $wert) . '</t></is></c>';
        }
        if (is_int($wert) || is_float($wert)) {
            // Eine gebrochene Zahl steht mit so vielen Stellen da, wie sie
            // braucht: «-1.5», nicht «-1.5000».
            $zahl = is_int($wert)
                ? (string) $wert
                : rtrim(rtrim(number_format($wert, 4, '.', ''), '0'), '.');
            return '<c r="' . $bezug . '" s="' . self::STIL_ZAHL . '"><v>' . $zahl . '</v></c>';
        }
        $text = (string) $wert;
        if ($text === '') {
            return '';
        }
        return '<c r="' . $bezug . '" s="' . self::STIL_TEXT . '" t="inlineStr"><is><t xml:space="preserve">'
            . self::text($text) . '</t></is></c>';
    }

    /**
     * Text für XML: Steuerzeichen weg, die fünf Sonderzeichen ersetzt. Der
     * Ausdruck arbeitet absichtlich ohne `u`: Er trifft nur Bytes unter 0x20,
     * und so bleibt ein Text, der kein gültiges UTF-8 ist, erhalten, statt
     * durch ein fehlgeschlagenes Ersetzen ganz zu verschwinden.
     */
    private static function text(string $text): string
    {
        $sauber = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
        return htmlspecialchars($sauber, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function typen(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function beziehungenMappe(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function beziehungenBlatt(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function mappe(string $blattName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::text(self::sauberBlattName($blattName)) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    /**
     * Die vier Formate: Standard, Kopfzeile, Text und Zahl. Arial, weil die
     * Datei auf jedem Rechner derselben Fraktion aufgeht.
     *
     * Das Zahlenformat trägt Tausendertrennzeichen und zwei optionale
     * Nachkommastellen: Ein Betrag steht damit als «-250'000» da und eine halbe
     * Stelle als «-1.5». Mit dem eingebauten Format «#,##0» zeigte das
     * Tabellenprogramm «-2», also eine andere Zahl als die Anwendung
     * (gemessen am 24.10.2026 mit einem Tabellenleser).
     */
    private static function formate(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="' . self::NUMFMT_ZAHL . '" formatCode="#,##0.##"/></numFmts>'
            . '<fonts count="2">'
            . '<font><sz val="10"/><name val="Arial"/></font>'
            . '<font><b/><sz val="10"/><name val="Arial"/></font>'
            . '</fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="4">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">'
            . '<alignment vertical="bottom" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1">'
            . '<alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="' . self::NUMFMT_ZAHL . '" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1">'
            . '<alignment horizontal="right" vertical="top"/></xf>'
            . '</cellXfs>'
            . '</styleSheet>';
    }
}
