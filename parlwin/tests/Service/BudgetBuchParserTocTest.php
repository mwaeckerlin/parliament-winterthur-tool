<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\TestCase;

/**
 * Inhaltsverzeichnis-Parsing (F80) ohne PDF: prüft die Departements-Zuordnung
 * über den privaten parseTeilB() an gestelltem Text. Reproduziert den Screenshot-
 * Bug, bei dem eine umgebrochene TOC-Zeile aus Füllpunkten und Seitenzahl
 * («……… 175») als Departement der folgenden Produktegruppe (480) landete.
 */
class BudgetBuchParserTocTest extends TestCase {
    /** @return list<array<string, mixed>> */
    private function parseTeilB(string $text): array {
        $parser = new BudgetBuchParser();
        // Seit PHP 8.1 sind private Methoden ohne setAccessible() aufrufbar.
        return (new \ReflectionMethod($parser, 'parseTeilB'))->invoke($parser, $text);
    }

    public function testUmgebrocheneFuellpunktZeileWirdNichtZumDepartement(): void {
        // Zwischen zwei sauberen TOC-Einträgen steht eine umgebrochene Zeile aus
        // nur Füllpunkten und einer Seitenzahl. Ohne den Fix übernahm der Parser
        // sie als Departementskopf und hängte die nächste Produktegruppe (480)
        // darunter.
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Sicherheit und Umwelt',
            'Schutz und Intervention Winterthur (470) ....................... 174',
            '............................. 175',
            'Umwelt- und Gesundheitsschutz (480) ................... 176',
            'Behörden und Stadtkanzlei',
            'Stadtkanzlei (110) .................. 12',
            'Einleitung Produktegruppe',
            // Kapitel-Körper: die Überschriften ohne Füllpunkte, jede registriert ihre Gruppe.
            'Schutz und Intervention Winterthur (470)',
            'Umwelt- und Gesundheitsschutz (480)',
            'Stadtkanzlei (110)',
        ]);

        $nachCode = [];
        foreach ($this->parseTeilB($text) as $g) {
            $nachCode[$g['code']] = $g;
        }

        self::assertArrayHasKey('480', $nachCode, 'Produktegruppe 480 wurde geparst');
        self::assertSame(
            'Sicherheit und Umwelt',
            $nachCode['480']['departement'],
            'PG 480 gehört zu «Sicherheit und Umwelt», nie zur Füllpunkt-/Seitenzahlzeile',
        );
        self::assertSame('Sicherheit und Umwelt', $nachCode['470']['departement']);
        self::assertSame('Behörden und Stadtkanzlei', $nachCode['110']['departement']);
    }

    /**
     * Jedes Produkt bekommt SEINE Kostentabelle. Im Buch folgt auf jedes «Produkt
     * <N> <Name>» eine Tabelle «Nettokosten / Kosten / Erlös / Nettokosten /
     * Kostendeckungsgrad». Der Parser hängte die Zeilen an das zuletzt ANGELEGTE
     * Produkt statt an das zuletzt gelesene: Weil der Anhang am Buchende alle
     * Produkte einer Gruppe bereits aufführt, landeten im Informationsteil sämtliche
     * Tabellen beim letzten Produkt — gemessen am Budget 2026 hatte «Individuelle
     * Unterstützung» (622) fünf Produkte ohne Tabelle und eines mit 24 Zeilen.
     */
    public function testJedesProduktBekommtSeineEigeneKostentabelle(): void {
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Soziales',
            'Individuelle Unterstützung (622) ....................... 255',
            'Einleitung Produktegruppe',
            'Individuelle Unterstützung (622)',
            // Der Anhang/die Übersicht nennt die Produkte einmal ohne Zahlen …
            'Produkt 1 Sozialhilfe gemäss SHG',
            'Produkt 2 Asylfürsorge',
            // … der Informationsteil bringt sie dann je mit ihrer Kostentabelle.
            'Produkt 1 Sozialhilfe gemäss SHG',
            'Leistungen',
            'Finanzielle Leistungen an Bezügerinnen und Bezüger von Sozialhilfe.',
            'Nettokosten                       Ist 2024   Soll 2025   Soll 2026',
            'Kosten                        117’643’652 120’568’000 120’981’224',
            'Erlös                          72’143’270  69’318’000  68’811’022',
            'Nettokosten                    45’500’381  51’250’000  52’170’202',
            'Kostendeckungsgrad in %                61          57          57',
            'Operative Ziele                   Ist 2024   Soll 2025   Soll 2026',
            'Produkt 2 Asylfürsorge',
            'Leistungen',
            'Finanzielle Leistungen an Asylsuchende.',
            'Nettokosten                       Ist 2024   Soll 2025   Soll 2026',
            'Kosten                         42’151’166  32’469’698  44’792’583',
            'Erlös                          32’070’625  26’035’937  36’773’370',
            'Nettokosten                    10’080’541   6’433’760   8’019’213',
            'Kostendeckungsgrad in %                76          80          82',
        ]);

        $nachCode = [];
        foreach ($this->parseTeilB($text) as $g) {
            $nachCode[$g['code']] = $g;
        }
        $produkte = $nachCode['622']['produkte'] ?? [];

        self::assertCount(2, $produkte, 'zwei Produkte, jedes genau einmal');
        $kosten = static function (array $produkt): int {
            foreach ($produkt['kostentabelle'] as $z) {
                if ($z['label'] === 'Kosten') {
                    return (int) $z['werte'][2];
                }
            }
            return 0;
        };
        self::assertCount(4, $produkte[0]['kostentabelle'], 'Produkt 1 trägt seine eigene Tabelle');
        self::assertCount(4, $produkte[1]['kostentabelle'], 'Produkt 2 trägt seine eigene Tabelle');
        self::assertSame(120981224, $kosten($produkte[0]), 'Kosten von Produkt 1');
        self::assertSame(44792583, $kosten($produkte[1]), 'Kosten von Produkt 2');
        self::assertSame(52170202, $produkte[0]['nettokosten']['soll'], 'Nettokosten von Produkt 1');
    }

    /**
     * Ein Fliesstext, der zufällig auf eine Produktegruppen-Nummer endet, ist keine
     * Überschrift. Im Budget 2026 steht in der Begründung der Stadtkanzlei (865):
     * «Der Jahresbeitrag … wurde aus der PG Stadtkanzlei in die PG Stadtrat (805)»
     * — der Satz geht auf der nächsten Zeile weiter. Der Parser wechselte dort
     * zurück zu 805 und schrieb die Kostentabelle des nächsten Produkts (865,
     * Kanzleifunktionen) dem Stadtparlament gut (+532%).
     */
    public function testFliesstextMitGruppennummerAmZeilenendeWechseltNichtDieGruppe(): void {
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Behörden und Stadtkanzlei',
            'Stadtparlament (805) ....................... 339',
            'Stadtkanzlei (865) ......................... 365',
            'Einleitung Produktegruppe',
            'Stadtparlament (805)',
            'Produkt 1 Stadtparlament',
            'Nettokosten                       Ist 2024   Soll 2025   Soll 2026',
            'Kosten                          1’439’212   1’773’592   1’737’564',
            'Stadtkanzlei (865)',
            'Begründung Abweichung Budget 2025/2026',
            'Der Jahresbeitrag für die Stiftung Winterthur wurde aus der PG Stadtkanzlei in die PG Stadtrat (805)',
            'übertragen.',
            'Produkt 1 Kanzleifunktionen',
            'Nettokosten                       Ist 2024   Soll 2025   Soll 2026',
            'Kosten                          5’721’290   6’188’326   7’924’292',
        ]);

        $nachCode = [];
        foreach ($this->parseTeilB($text) as $g) {
            $nachCode[$g['code']] = $g;
        }

        $kosten = static function (array $gruppe, int $produkt): int {
            foreach (($gruppe['produkte'][$produkt]['kostentabelle'] ?? []) as $z) {
                if ($z['label'] === 'Kosten') {
                    return (int) $z['werte'][2];
                }
            }
            return 0;
        };
        self::assertSame(1737564, $kosten($nachCode['805'], 0), 'Das Stadtparlament behält seine eigene Tabelle');
        self::assertCount(1, $nachCode['805']['produkte'][0]['kostentabelle'], 'und bekommt keine fremde dazu');
        self::assertSame(7924292, $kosten($nachCode['865'], 0), 'Die Kanzleifunktionen bekommen ihre Tabelle');
    }

    /**
     * Ein umgebrochener Satz, der mit «Produkt <N> …» beginnt, ist keine
     * Produktüberschrift. Im Budget 2026 endet der Leistungstext von PG 651,
     * Produkt 3 mit «Die Kosten für die Pflegeleistungen werden im | Produkt 4
     * abgebildet.» — der Parser wechselte dort auf Produkt 4 und schrieb ihm die
     * Kostentabelle von Produkt 3 gut.
     */
    public function testUmgebrochenerSatzIstKeineProduktUeberschrift(): void {
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Soziales',
            'Beiträge an Organisationen (651) ....................... 295',
            'Einleitung Produktegruppe',
            'Beiträge an Organisationen (651)',
            'Produkt 3 Alter und Gesundheit',
            'Leistungen',
            'Beiträge an Organisationen im Gesundheitsbereich. Die Kosten für die Pflegeleistungen werden im',
            'Produkt 4 abgebildet.',
            'Nettokosten                       Ist 2024   Soll 2025   Soll 2026',
            'Kosten                          1’639’455   1’727’473   1’831’090',
            'Erlös                             118’525     120’400     109’150',
            'Produkt 4 Pflegefinanzierung',
            'Leistungen',
            'Beiträge an die Pflegekosten.',
            'Nettokosten                       Ist 2024   Soll 2025   Soll 2026',
            'Kosten                         59’683’779  64’008’001  65’630’500',
        ]);

        $nachCode = [];
        foreach ($this->parseTeilB($text) as $g) {
            $nachCode[$g['code']] = $g;
        }
        $produkte = $nachCode['651']['produkte'] ?? [];

        self::assertCount(2, $produkte, 'genau die zwei echten Produkte');
        self::assertSame('Alter und Gesundheit', $produkte[0]['name']);
        self::assertSame('Pflegefinanzierung', $produkte[1]['name']);
        $kosten = static function (array $p): int {
            foreach ($p['kostentabelle'] as $z) {
                if ($z['label'] === 'Kosten') {
                    return (int) $z['werte'][2];
                }
            }
            return 0;
        };
        self::assertSame(1831090, $kosten($produkte[0]), 'Produkt 3 behält seine Tabelle');
        self::assertSame(65630500, $kosten($produkte[1]), 'Produkt 4 bekommt seine eigene');
    }

    /**
     * Ein Produktname darf klein geschrieben sein: Die IDW (222) führen «Produkt 2
     * elektronischer Arbeitsplatz». Er steht — wie jeder echte Produktname — an
     * mehreren Stellen im Buch (Informationsteil und Anhang «Gliederung»), ein
     * umgebrochener Satz dagegen nur an einer.
     */
    public function testKleingeschriebenerProduktnameBleibtEinProdukt(): void {
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Finanzen',
            'Informatikdienste (IDW) (222) ....................... 60',
            'Einleitung Produktegruppe',
            'Informatikdienste (IDW) (222)',
            'Produkt 2 elektronischer Arbeitsplatz',
            'Leistungen',
            'Betrieb der Arbeitsplätze. Die Kosten stehen im',
            'Produkt 3 und sind dort ausgewiesen.',
            'Nettokosten                       Ist 2024   Soll 2025   Soll 2026',
            'Kosten                          9’332’336   8’812’053  10’897’629',
            'Anhang',
            'Informatikdienste (IDW) (222)',
            'Produkt 2 elektronischer Arbeitsplatz',
        ]);

        $nachCode = [];
        foreach ($this->parseTeilB($text) as $g) {
            $nachCode[$g['code']] = $g;
        }
        $produkte = $nachCode['222']['produkte'] ?? [];

        self::assertCount(1, $produkte, 'nur das echte Produkt, nicht der umgebrochene Satz');
        self::assertSame('elektronischer Arbeitsplatz', $produkte[0]['name']);
        self::assertSame(10897629, (int) $produkte[0]['kostentabelle'][0]['werte'][2]);
    }

    /**
     * Nicht jede Kostentabelle hat alle drei Jahre: Ein neues Produkt trägt nur
     * die aktuellen Zahlen. Im Budget 2026 hat PG 855 «Schulpflege» keine
     * Ist-Werte (zwei Spalten) und PG 731 «Anteil Pensionskassenstabilisierung»
     * nur den Soll-Wert (eine Spalte). Der Parser verlangte drei Zahlen und
     * verwarf beide Tabellen ganz — die Produkte standen ohne Kosten da. Die
     * vorhandenen Werte füllen die Spalten von rechts: Der letzte Wert ist immer
     * das Budgetjahr.
     */
    public function testUnvollstaendigeKostentabelleWirdRechtsbuendigGelesen(): void {
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Behörden und Stadtkanzlei',
            'Schulpflege (855) ....................... 364',
            'Einleitung Produktegruppe',
            'Schulpflege (855)',
            'Produkt 1 Schulpflege',
            'Nettokosten                       Ist 2024   Soll 2025   Soll 2026',
            'Kosten                                        689’041     660’884',
            'Erlös                                               0           0',
            'Nettokosten                                   689’041     660’884',
            'Produkt 2 Anteil Pensionskassenstabilisierung',
            'Nettokosten                       Ist 2024   Soll 2025   Soll 2026',
            'Kosten                                                     96’786',
            'Erlös                                                      96’786',
            'Nettokosten                                                     0',
        ]);

        $nachCode = [];
        foreach ($this->parseTeilB($text) as $g) {
            $nachCode[$g['code']] = $g;
        }
        $produkte = $nachCode['855']['produkte'] ?? [];

        $werte = static function (array $p, string $label): array {
            foreach ($p['kostentabelle'] as $z) {
                if ($z['label'] === $label) {
                    return $z['werte'];
                }
            }
            return [];
        };
        self::assertSame([0, 689041, 660884], $werte($produkte[0], 'Kosten'), 'zwei Werte: Ist bleibt leer');
        self::assertSame([0, 689041, 660884], $werte($produkte[0], 'Nettokosten'), 'Nettokosten-Zeile');
        self::assertSame(660884, $produkte[0]['nettokosten']['soll'], 'Nettokosten des Budgetjahres');
        self::assertSame([0, 0, 96786], $werte($produkte[1], 'Kosten'), 'ein Wert: nur das Budgetjahr');
    }

    /**
     * Im Buchtext klebt die erste Kostenzeile manchmal an der Kopfzeile:
     * «Nettokosten Ist 2024 Soll 2025 Soll 2026 Kosten 689'041 660'884» steht im
     * Budget 2026 (PG 855) als EINE Zeile. Der Parser verwarf die Kopfzeile samt
     * der angehängten Kosten — das Produkt stand ohne Kosten da.
     */
    public function testKostenzeileAnDerKopfzeileWirdGelesen(): void {
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Behörden und Stadtkanzlei',
            'Schulpflege (855) ....................... 364',
            'Einleitung Produktegruppe',
            'Schulpflege (855)',
            'Produkt 1 Schulpflege',
            "Nettokosten \tIst 2024 Soll 2025 Soll 2026 Kosten  \t689'041 660’884",
            "Erlös  \t0 0",
            "Nettokosten  \t689'041 660’884",
        ]);

        $nachCode = [];
        foreach ($this->parseTeilB($text) as $g) {
            $nachCode[$g['code']] = $g;
        }
        $tabelle = $nachCode['855']['produkte'][0]['kostentabelle'] ?? [];
        $labels = array_column($tabelle, 'label');

        self::assertContains('Kosten', $labels, 'die an der Kopfzeile klebende Kostenzeile fehlt');
        $kosten = $tabelle[array_search('Kosten', $labels, true)]['werte'];
        self::assertSame([0, 689041, 660884], $kosten);
    }

    public function testParstParlamentarischeZielvorgabenJeMessgroesse(): void {
        // Struktur wie im Buch (PG 121): nummerierte Ziele, je Messgrösse eine
        // Wertzeile aus sechs Jahresspalten; entscheidbar ist «Soll aktuell» (Index 2).
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Präsidiales',
            'Personalamt (121) ................... 6',
            'Einleitung Produktegruppe',
            'Personalamt (121)',
            'Auftrag',
            'Das Personalamt bearbeitet die personalrechtlichen Fragen.',
            'Rechtsgrundlagen und verwaltungsinterne Grundlagen',
            'Parlamentarische Zielvorgaben Ist 2024 Soll 2025 Soll 2026 Plan 2027 Plan 2028 Plan 2029',
            '2 Kundenorientierung zentrales Personalmanagement',
            'Die Kundschaft ist mit den Dienstleistungen zufrieden.',
            'Messung / Bewertung:',
            'Der Zufriedenheitsgrad wird ermittelt.',
            'Messgrösse:',
            'Prozentsatz der zufrieden Antwortenden',
            '90 85 85 85 85 85',
            '3 Kundenorientierung Personalentwicklung',
            'Die Personalentwicklung ist bedarfsgerecht.',
            'Messgrösse:',
            " Anzahl Kurstage \t1'339 1'000 1'000 1'000 1'000 1'000",
            ' Durchführungsquote der internen Weiterbildungen',
            '87 80 80 80 80 80',
            'Globalkredit \tIst 2024 Soll 2025 Soll 2026',
            "Nettokosten / Globalkredit 4'061'060 4'395'510 4'866'124",
        ]);

        $nachCode = [];
        foreach ($this->parseTeilB($text) as $g) {
            $nachCode[$g['code']] = $g;
        }
        self::assertArrayHasKey('121', $nachCode);
        $zv = $nachCode['121']['zielvorgaben'];
        self::assertCount(3, $zv, 'drei Messgrössen (Ziel 2 eine, Ziel 3 zwei)');

        $kundenorientierung = array_values(array_filter($zv, static fn ($z) => $z['zielNummer'] === 2))[0];
        self::assertSame('2 Kundenorientierung zentrales Personalmanagement', $kundenorientierung['zielTitel']);
        self::assertSame('Prozentsatz der zufrieden Antwortenden', $kundenorientierung['messgroesse']);
        self::assertSame('90', $kundenorientierung['werte'][0], 'Ist 2024');
        self::assertSame('85', $kundenorientierung['soll'], 'Soll aktuell (Index 2) ist der beantragbare Wert');

        $kurstage = array_values(array_filter($zv, static fn ($z) => $z['messgroesse'] === 'Anzahl Kurstage'))[0];
        self::assertSame(3, $kurstage['zielNummer']);
        self::assertSame("1'000", $kurstage['soll']);

        $quote = array_values(array_filter($zv, static fn ($z) => str_starts_with($z['messgroesse'], 'Durchführungsquote')))[0];
        self::assertSame('80', $quote['soll']);
    }

    public function testParstKostenzeilenAusDemInformationsteil(): void {
        // Kostentabelle im Informationsteil: Label + «Soll aktuell» (Zahlenindex 4,
        // wegen der «in%»-Spalten); Summen-/Verrechnungszeilen sind keine Positionen;
        // ein umgebrochenes Label wird zusammengeführt.
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Präsidiales',
            'Personalamt (121) ................... 6',
            'Einleitung Produktegruppe',
            'Personalamt (121)',
            '▼Informationsteil▼',
            'Nettokosten / Globalkredit Ist 2024 in% Soll 2025 in% Soll 2026 in% Plan 2027 Plan 2028 Plan 2029',
            "Personalkosten 4'610'991 71 4'654'220 68 5'178'974 70 5'337'045 4'909'047 4'825'682",
            "Sachkosten 889'959 14 1'093'912 16 990'017 13 850'017 880'017 825'017",
            'Kalk. Abschreibungen und',
            'Zinsen / Finanzaufwand',
            "58'823 1 58'256 1 57'518 1 0 85'200 84'160",
            "Total effektive Kosten 6'514'117 100 6'863'996 100 7'428'985 100 7'338'254 7'023'816 6'884'411",
            'Stellenplan Ist 2024 Soll 2025 Soll 2026',
        ]);
        $nachCode = [];
        foreach ($this->parseTeilB($text) as $g) {
            $nachCode[$g['code']] = $g;
        }
        $kz = $nachCode['121']['kostenzeilen'];
        $nachLabel = [];
        foreach ($kz as $z) {
            $nachLabel[$z['label']] = $z['soll'];
        }
        self::assertArrayHasKey('Personalkosten', $nachLabel);
        self::assertSame(5178974, $nachLabel['Personalkosten'], 'Soll aktuell (Zahlenindex 4)');
        self::assertSame(990017, $nachLabel['Sachkosten']);
        self::assertArrayHasKey('Kalk. Abschreibungen und Zinsen / Finanzaufwand', $nachLabel, 'umgebrochenes Label zusammengeführt');
        self::assertSame(57518, $nachLabel['Kalk. Abschreibungen und Zinsen / Finanzaufwand']);
        self::assertArrayNotHasKey('Total effektive Kosten', $nachLabel, 'Summenzeile ist keine Position');
    }

    public function testParstLeistungenJeProdukt(): void {
        // Leistungen je Produkt (F110): im Buch beginnt jede Leistung mit einem
        // Bullet-Glyph (smalot liefert U+F0A7) — teils auf derselben Zeile wie der
        // Text, teils allein; eine umgebrochene Leistung läuft ohne Glyph auf der
        // Folgezeile weiter. Erwartet: saubere Leistungen ohne Glyph, Fortsetzungen
        // zusammengeführt, keine leeren Einträge. Die Nettokosten-Wertzeile bleibt intakt.
        $g = "\u{F0A7}";
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Präsidiales',
            'Personalamt (121) ................... 6',
            'Einleitung Produktegruppe',
            'Personalamt (121)',
            'Produkt 1 Personalpolitik / Personalrecht',
            'Leistungen',
            $g . ' Erarbeitung der personalpolitischen Grundsätze',   // Glyph + Text auf einer Zeile
            $g . ' Überwachung des Vollzugs und Koordination der',    // Glyph + Text, umgebrochen
            'personalrechtlichen Praxis',                             // Fortsetzung (kein Glyph)
            $g . ' Bearbeitung personalrechtlicher Geschäfte',
            'Nettokosten Ist 2024 Soll 2025 Soll 2026',
            "Kosten 1'442'744 1'511'500 1'540'766",
            "Nettokosten 1'057'730 1'145'451 1'135'916",
            'Produkt 2 Zentrales Personalmanagement',
            'Leistungen',
            $g,                                                       // Glyph allein (Live-Buch)
            'Beratung der Bereiche in Personalfragen',                // Text nach dem Glyph
            'Nettokosten Ist 2024 Soll 2025 Soll 2026',
            "Nettokosten 1'318'580 1'300'000 1'318'580",
        ]);
        $nachCode = [];
        foreach ($this->parseTeilB($text) as $gr) {
            $nachCode[$gr['code']] = $gr;
        }
        $produkte = $nachCode['121']['produkte'];
        self::assertCount(2, $produkte);
        self::assertSame([
            'Erarbeitung der personalpolitischen Grundsätze',
            'Überwachung des Vollzugs und Koordination der personalrechtlichen Praxis',
            'Bearbeitung personalrechtlicher Geschäfte',
        ], $produkte[0]['leistungen'], 'Glyph entfernt, Fortsetzung zusammengeführt');
        self::assertSame(1135916, $produkte[0]['nettokosten']['soll'], 'die Nettokosten bleiben korrekt');
        self::assertSame(['Beratung der Bereiche in Personalfragen'], $produkte[1]['leistungen']);
        foreach ($produkte as $p) {
            foreach ($p['leistungen'] as $l) {
                self::assertNotSame('', trim($l), 'keine leere Leistung');
                self::assertStringNotContainsString("\u{F0A7}", $l, 'kein Bullet-Glyph im Text');
            }
        }
    }

    public function testAnhangDupliziertProdukteNicht(): void {
        // Der Anhang «Gliederung von Budget und Jahresrechnung» am Buchende listet
        // jede Produktegruppe mit ihren Produkten noch einmal, ohne Zahlen. Diese
        // Wiederholung darf die Produkte NICHT doppeln (F110-Bug: Produkte doppelt,
        // die zweite Reihe ohne Nettokosten/Leistungen).
        $g = "\u{F0A7}";
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Präsidiales',
            'Personalamt (121) ................... 6',
            'Einleitung Produktegruppe',
            'Personalamt (121)',
            'Produkt 1 Personalpolitik / Personalrecht',
            'Leistungen',
            $g . ' Erarbeitung der personalpolitischen Grundsätze',
            'Nettokosten Ist 2024 Soll 2025 Soll 2026',
            "Nettokosten 1'057'730 1'145'451 1'135'916",
            'Produkt 2 Zentrales Personalmanagement',
            'Leistungen',
            $g . ' Beratung der Bereiche in Personalfragen',
            'Nettokosten Ist 2024 Soll 2025 Soll 2026',
            "Nettokosten 1'318'580 1'300'000 1'318'580",
            // Anhang: dieselbe Produktegruppe mit ihren Produkten, ohne Zahlen.
            'Gliederung von Budget und Jahresrechnung',
            'Personalamt (121)',
            'Produkt 1 Personalpolitik / Personalrecht',
            'Produkt 2 Zentrales Personalmanagement',
        ]);
        $nachCode = [];
        foreach ($this->parseTeilB($text) as $gr) {
            $nachCode[$gr['code']] = $gr;
        }
        $produkte = $nachCode['121']['produkte'];
        self::assertCount(2, $produkte, 'der Anhang doppelt die Produkte nicht');
        self::assertSame(1135916, $produkte[0]['nettokosten']['soll'], 'die Zahlen des echten Produkts bleiben');
        self::assertSame(['Erarbeitung der personalpolitischen Grundsätze'], $produkte[0]['leistungen']);
        self::assertSame([1, 2], array_map(static fn ($p) => $p['nummer'], $produkte), 'jede Produktnummer genau einmal');
    }

    public function testParstProduktKostentabelle(): void {
        // Je Produkt führt das Buch eine Kostentabelle (Kosten, Erlös, Nettokosten,
        // Kostendeckungsgrad) über Ist / Soll Vorjahr / Soll aktuell — sie gehört als
        // Budgetzahlen in die Produkt-Karte (F110).
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Präsidiales',
            'Personalamt (121) ................... 6',
            'Einleitung Produktegruppe',
            'Personalamt (121)',
            'Produkt 1 Personalpolitik / Personalrecht',
            'Nettokosten Ist 2024 Soll 2025 Soll 2026',
            "Kosten 1'442'744 1'511'500 1'540'766",
            "Erlös 385'014 366'049 404'850",
            "Nettokosten 1'057'730 1'145'451 1'135'916",
            'Kostendeckungsgrad in % 27 24 26',
            'Operative Ziele Ist 2024 Soll 2025 Soll 2026',
            'Siehe Massnahmen und Projekte',
        ]);
        $nachCode = [];
        foreach ($this->parseTeilB($text) as $gr) {
            $nachCode[$gr['code']] = $gr;
        }
        $p1 = $nachCode['121']['produkte'][0];
        $nachLabel = [];
        foreach ($p1['kostentabelle'] as $z) {
            $nachLabel[$z['label']] = $z['werte'];
        }
        self::assertSame([1442744, 1511500, 1540766], $nachLabel['Kosten']);
        self::assertSame([385014, 366049, 404850], $nachLabel['Erlös']);
        self::assertSame([1057730, 1145451, 1135916], $nachLabel['Nettokosten']);
        self::assertSame([27, 24, 26], $nachLabel['Kostendeckungsgrad in %']);
        // «Operative Ziele …» beendet die Tabelle, ist selbst keine Kostenzeile.
        self::assertArrayNotHasKey('Operative Ziele Ist', $nachLabel);
        // Die prominente Nettokosten-Soll-Zahl der Karte bleibt korrekt.
        self::assertSame(1135916, $p1['nettokosten']['soll']);
    }

    /**
     * Eine Zeile, in der auf den ersten Betrag lauter Nullen folgen. Wörtlich aus
     * dem Buch 2017, Produktegruppe «Beiträge an Organisationen» (645): «Total
     * effektive Erlöse -565 0 0 0 0 0 0 0 0» — die Gruppe hat einmalig einen
     * kleinen negativen Ist-Wert und danach nichts mehr.
     *
     * Der Ertrag wird paarweise gelesen (Betrag, Anteil). Zählt ein Paar erst, wenn
     * der Anteil ECHT kleiner ist als der Betrag, endet die Zählung hier nach dem
     * ersten Paar — und der einzige gefundene Betrag rutschte als Soll-Wert des
     * Budgetjahres ans Ende, statt Ist zu bleiben.
     */
    public function testNullspaltenBeendenDieWertzeileNicht(): void {
        $text = implode("\n", [
            'Inhaltsverzeichnis',
            'Soziales',
            'Beiträge an Organisationen (645) ....................... 273',
            'Einleitung Produktegruppe',
            'Beiträge an Organisationen (645)',
            'Nettokosten / Globalkredit Ist 2015 in% Soll 2016 in% Soll 2017 in% Plan 2018 Plan 2019 Plan 2020',
            "Total effektive Kosten 20'902'180 100 43'404'548 100 45'360'076 100 46'555'076 47'784'076 49'046'076",
            'Total effektive Erlöse -565 0 0 0 0 0 0 0 0',
        ]);
        $nachCode = [];
        foreach ($this->parseTeilB($text) as $gr) {
            $nachCode[$gr['code']] = $gr;
        }

        $g = $nachCode['645'];
        self::assertSame(-565, $g['ertrag']['ist'], 'Ist-Wert bleibt in der ersten Spalte');
        self::assertSame(0, $g['ertrag']['sollVorjahr'], 'Soll Vorjahr ist null');
        self::assertSame(0, $g['ertrag']['soll'], 'Soll des Budgetjahres ist null');
        self::assertSame(45360076, $g['aufwand']['soll'], 'Kosten des Budgetjahres unverändert');
    }

    /**
     * Das PDF zerreisst Beträge mitten in der Zahl: «960’00» und «0» stehen als
     * zwei Fragmente nebeneinander und ergeben 960'000 — einzeln gelesen 960 und
     * 0, also den tausendsten Teil. Erkennbar ist der Bruch am Ende des ersten
     * Stücks: Nach einem Tausender-Apostroph stehen dort weniger als drei Ziffern.
     * In der Investitionsplanung 2025 betraf das 24 Zeilen.
     *
     * @param list<array{x: float, t: string}> $frags
     * @return list<string>
     */
    private function verschmolzen(array $frags): array {
        $parser = new BudgetBuchParser();
        $zusammen = (new \ReflectionMethod($parser, 'fragmenteVerschmelzen'))->invoke($parser, $frags);
        return array_map(static fn ($f) => $f['t'], $zusammen);
    }

    public function testZerrisseneZahlWirdZusammengesetzt(): void {
        self::assertSame(
            ["960’000", " 240’000", ' 0'],
            $this->verschmolzen([
                ['x' => 300.0, 't' => '960’00'],
                ['x' => 330.0, 't' => '0'],
                ['x' => 370.0, 't' => ' 240’00'],
                ['x' => 400.0, 't' => '0'],
                ['x' => 440.0, 't' => ' 0'],
            ]),
            'Ein nach dem Apostroph abgeschnittener Betrag wird mit seinem Rest zusammengesetzt'
        );
    }

    public function testVollstaendigeBetraegeBleibenGetrennt(): void {
        // Gegenprobe: Wo keine Zahl zerrissen ist, wird nichts zusammengezogen —
        // sonst verschmölzen benachbarte Spalten zu einem Betrag.
        self::assertSame(
            ["960’000", " 240’000", ' 0'],
            $this->verschmolzen([
                ['x' => 300.0, 't' => '960’000'],
                ['x' => 370.0, 't' => ' 240’000'],
                ['x' => 440.0, 't' => ' 0'],
            ]),
            'Vollständige Beträge bleiben eigene Fragmente'
        );
    }
}
