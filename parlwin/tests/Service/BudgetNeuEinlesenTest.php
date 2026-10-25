<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Neu einlesen nach einer Verbesserung am Einleser (F130).
 *
 * Marc am 24.10.2026: «Stelle sicher, dass bei Verbesserungen im Parser auch
 * bereits eingelesene Dokumente nach dem Update neu eingelesen werden, und
 * bestehende Einträge der Fraktion nicht verloren gehen und auch am richtigen
 * Ort bleiben. Es darf sich an unseren Einträgen nichts ändern, nichts kaputt
 * gehen kann. Das musst du mit Beispielen testen.»
 *
 * Geprüft wird mit dem echten Buch 2027: Alles, woran ein Antrag der Fraktion
 * hängt, muss über zwei Lesevorgänge hinweg gleich bleiben — die Nummer der
 * Produktegruppe, das Investitionsvorhaben und die Zielvorgabe mit ihrem Namen.
 */
#[Group('pdf')]
final class BudgetNeuEinlesenTest extends TestCase {
    private function fixture(string $name): string {
        return \dirname(__DIR__, 1) . '/Fixtures/budget/' . $name;
    }

    /** @return array<string, mixed> */
    private function struktur2027(): array {
        return (new BudgetBuchParser())->struktur($this->fixture('2027/teil-b.pdf'), $this->fixture('2027/teil-a.pdf'), 2027);
    }

    public function testDerEinleserTraegtEinenStandDerErhoehtWird(): void {
        // Ohne diesen Stand merkt die Installation nicht, dass sie neu lesen
        // muss; der Hintergrundauftrag hängt daran.
        self::assertGreaterThanOrEqual(2, BudgetBuchParser::VERSION);
    }

    public function testZweiLesevorgaengeLiefernDieselbenProduktegruppen(): void {
        // Woran ein Antrag hängt: die Nummer der Produktegruppe. Sie darf sich
        // zwischen zwei Lesevorgängen nicht verschieben.
        $erste = $this->codes($this->struktur2027());
        $zweite = $this->codes($this->struktur2027());
        self::assertSame($erste, $zweite, 'die Nummern der Produktegruppen wandern');
        self::assertNotEmpty($erste);
    }

    public function testZweiLesevorgaengeLiefernDieselbenInvestitionen(): void {
        // Woran ein Investitionsantrag hängt: die Kennung des Vorhabens.
        $erste = $this->investitionen($this->struktur2027());
        $zweite = $this->investitionen($this->struktur2027());
        self::assertSame($erste, $zweite, 'die Kennungen der Vorhaben wandern');
    }

    public function testZweiLesevorgaengeLiefernDieselbenZielvorgabenNamen(): void {
        // Woran eine Zielvorgaben-Änderung hängt: Zielnummer, Unterabschnitt und
        // Messgrösse. Ändert sich dieser Name beim Neulesen, trifft der Antrag
        // der Fraktion eine andere Vorgabe.
        $erste = $this->zielnamen($this->struktur2027());
        $zweite = $this->zielnamen($this->struktur2027());
        self::assertSame($erste, $zweite, 'die Namen der Zielvorgaben wandern');
        self::assertNotEmpty($erste);
    }

    public function testJedeZielvorgabeIstImmerNochEindeutig(): void {
        // Der Antrag der Fraktion findet seine Vorgabe über den Namen. Zwei
        // gleich benannte Vorgaben derselben Produktegruppe machen die
        // Zuordnung mehrdeutig, und der Antrag träfe die falsche.
        foreach ($this->struktur2027()['produktegruppen'] as $g) {
            $namen = [];
            foreach ($g['zielvorgaben'] as $z) {
                $namen[] = $z['zielNummer'] . '|' . ($z['unterTitel'] ?? '') . '|' . $z['messgroesse'];
            }
            $doppelt = array_keys(array_filter(array_count_values($namen), static fn ($n) => $n > 1));
            self::assertSame([], $doppelt, "Produktegruppe {$g['code']}: " . implode(' / ', $doppelt));
        }
    }

    public function testEinAntragFindetSeineZielvorgabeAuchOhneUnterabschnitt(): void {
        // Ein Antrag, der vor der Verbesserung angelegt wurde, trägt keinen
        // Unterabschnitt. Er muss seine Vorgabe weiterhin finden — über
        // Zielnummer und Messgrösse allein.
        $pg = null;
        foreach ($this->struktur2027()['produktegruppen'] as $g) {
            if ((string) $g['code'] === '240') {
                $pg = $g;
            }
        }
        self::assertNotNull($pg);
        $alterAntrag = ['zielNummer' => 1, 'unterTitel' => '', 'messgroesse' => 'Wohnliegenschaften (in %)'];
        $treffer = array_values(array_filter(
            $pg['zielvorgaben'],
            static fn ($z) => (int) $z['zielNummer'] === $alterAntrag['zielNummer']
                && (string) $z['messgroesse'] === $alterAntrag['messgroesse']
        ));
        self::assertNotEmpty($treffer, 'ein Antrag aus der Zeit vor dem Unterabschnitt findet seine Vorgabe nicht mehr');
    }

    /** @return list<string> */
    private function codes(array $struktur): array {
        $codes = array_map(static fn ($g) => (string) $g['code'], $struktur['produktegruppen']);
        sort($codes);
        return $codes;
    }

    /** @return list<string> */
    private function investitionen(array $struktur): array {
        $ids = array_map(static fn ($i) => (string) ($i['projekt'] ?? $i['id'] ?? ''), $struktur['investitionen']);
        sort($ids);
        return $ids;
    }

    /** @return list<string> */
    private function zielnamen(array $struktur): array {
        $namen = [];
        foreach ($struktur['produktegruppen'] as $g) {
            foreach ($g['zielvorgaben'] as $z) {
                $namen[] = $g['code'] . '|' . $z['zielNummer'] . '|' . ($z['unterTitel'] ?? '') . '|' . $z['messgroesse'];
            }
        }
        sort($namen);
        return $namen;
    }
}
