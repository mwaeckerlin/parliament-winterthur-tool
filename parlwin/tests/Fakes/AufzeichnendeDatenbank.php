<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Fakes;

use OCP\IDBConnection;

/**
 * Eine Datenbank im Speicher für die Mapper-Tests: Sie hält Tabellen als
 * Zeilen, wertet die Bedingungen des Query-Builders aus, die die Mapper
 * benutzen, und zeichnet jede Abfrage mit ihren Spalten auf. Ein langer Text
 * steht als {@see GrosserText} in der Tabelle und entsteht erst, wenn eine
 * Abfrage seine Spalte liest; so misst der Speicher genau, was die Abfrage
 * holt.
 */
class AufzeichnendeDatenbank implements IDBConnection
{
    /** @var array<string, list<array<string, mixed>>> */
    public array $tabellen = [];

    /** @var list<array{tabelle: string, spalten: list<string>}> */
    public array $abfragen = [];

    public function getQueryBuilder()
    {
        return new FakeQueryBuilder($this);
    }

    public function executeStatement($sql, array $params = [], array $types = []): int
    {
        return 0;
    }

    /** @param list<string> $spalten */
    public function aufzeichnen(string $tabelle, array $spalten): void
    {
        $this->abfragen[] = ['tabelle' => $tabelle, 'spalten' => $spalten];
    }

    /** @return list<list<string>> die Spalten jeder Abfrage auf die Tabelle */
    public function spaltenAuf(string $tabelle): array
    {
        return array_values(array_map(
            static fn (array $a): array => $a['spalten'],
            array_filter($this->abfragen, static fn (array $a): bool => $a['tabelle'] === $tabelle)
        ));
    }
}

/** Ein Text, der erst entsteht, wenn eine Abfrage ihn liest. */
final class GrosserText
{
    public function __construct(public readonly int $laenge, private readonly string $zeichen = 'x')
    {
    }

    public function text(): string
    {
        return str_repeat($this->zeichen, $this->laenge);
    }
}

/** Ein benannter Parameter des Query-Builders. */
final class FakeParameter
{
    public function __construct(public readonly mixed $wert)
    {
    }
}

/** Eine Spaltenfunktion, die die Datenbank rechnet, ohne den Wert zu liefern. */
final class FakeFunktion
{
    /** @param \Closure(array<string, mixed>): mixed $rechnen */
    public function __construct(public readonly string $name, public readonly \Closure $rechnen)
    {
    }
}

final class FakeFunktionen
{
    public function octetLength(string $spalte): FakeFunktion
    {
        return new FakeFunktion('octet_length(' . $spalte . ')', static function (array $zeile) use ($spalte): ?int {
            $wert = $zeile[$spalte] ?? null;
            if ($wert === null) {
                return null;
            }
            return $wert instanceof GrosserText ? $wert->laenge : strlen((string) $wert);
        });
    }

    public function lower(string $spalte): FakeFunktion
    {
        return new FakeFunktion('lower(' . $spalte . ')', static fn (array $zeile): string => strtolower((string) ($zeile[$spalte] ?? '')));
    }
}

final class FakeAusdruecke
{
    private static function wert(mixed $x, array $zeile): mixed
    {
        if ($x instanceof FakeParameter) {
            return $x->wert;
        }
        if ($x instanceof FakeFunktion) {
            return ($x->rechnen)($zeile);
        }
        $wert = $zeile[$x] ?? null;
        return $wert instanceof GrosserText ? $wert->text() : $wert;
    }

    public function eq(mixed $links, mixed $rechts): \Closure
    {
        return static fn (array $z): bool => self::wert($links, $z) == self::wert($rechts, $z);
    }

    public function in(mixed $links, mixed $rechts): \Closure
    {
        return static fn (array $z): bool => in_array(self::wert($links, $z), (array) self::wert($rechts, $z), false);
    }

    public function isNull(mixed $spalte): \Closure
    {
        return static fn (array $z): bool => self::wert($spalte, $z) === null;
    }

    public function orX(\Closure ...$teile): \Closure
    {
        return static function (array $z) use ($teile): bool {
            foreach ($teile as $teil) {
                if ($teil($z)) {
                    return true;
                }
            }
            return false;
        };
    }

    public function andX(\Closure ...$teile): \Closure
    {
        return static function (array $z) use ($teile): bool {
            foreach ($teile as $teil) {
                if (!$teil($z)) {
                    return false;
                }
            }
            return true;
        };
    }
}

final class FakeErgebnis
{
    /** @param list<array<string, mixed>> $zeilen */
    public function __construct(private array $zeilen)
    {
    }

    public function fetch(): array|false
    {
        return array_shift($this->zeilen) ?? false;
    }

    public function fetchAll(): array
    {
        $alle = $this->zeilen;
        $this->zeilen = [];
        return $alle;
    }

    public function fetchOne(): mixed
    {
        $zeile = $this->fetch();
        return $zeile === false ? false : reset($zeile);
    }

    public function closeCursor(): bool
    {
        return true;
    }
}

final class FakeQueryBuilder
{
    /** @var list<string|FakeFunktion> */
    private array $spalten = [];
    /** @var array<string, FakeFunktion> */
    private array $aliasse = [];
    private string $tabelle = '';
    /** @var list<\Closure> */
    private array $bedingungen = [];
    /** @var list<array{0: string, 1: string}> */
    private array $ordnung = [];
    private ?int $hoechstens = null;
    private int $ab = 0;

    public function __construct(private readonly AufzeichnendeDatenbank $db)
    {
    }

    public function select(string ...$spalten): self
    {
        $this->spalten = array_values($spalten);
        return $this;
    }

    public function addSelect(string ...$spalten): self
    {
        $this->spalten = [...$this->spalten, ...array_values($spalten)];
        return $this;
    }

    public function selectAlias(FakeFunktion|string $ausdruck, string $alias): self
    {
        $this->aliasse[$alias] = $ausdruck instanceof FakeFunktion
            ? $ausdruck
            : new FakeFunktion($ausdruck, static fn (array $z): mixed => $z[$ausdruck] ?? null);
        return $this;
    }

    public function from(string $tabelle): self
    {
        $this->tabelle = $tabelle;
        return $this;
    }

    public function where(\Closure $bedingung): self
    {
        $this->bedingungen = [$bedingung];
        return $this;
    }

    public function andWhere(\Closure $bedingung): self
    {
        $this->bedingungen[] = $bedingung;
        return $this;
    }

    public function orderBy(string $spalte, string $richtung = 'ASC'): self
    {
        $this->ordnung = [[$spalte, strtoupper($richtung)]];
        return $this;
    }

    public function addOrderBy(string $spalte, string $richtung = 'ASC'): self
    {
        $this->ordnung[] = [$spalte, strtoupper($richtung)];
        return $this;
    }

    public function setMaxResults(?int $anzahl): self
    {
        $this->hoechstens = $anzahl;
        return $this;
    }

    public function setFirstResult(int $ab): self
    {
        $this->ab = $ab;
        return $this;
    }

    public function expr(): FakeAusdruecke
    {
        return new FakeAusdruecke();
    }

    public function func(): FakeFunktionen
    {
        return new FakeFunktionen();
    }

    public function createNamedParameter(mixed $wert, mixed $typ = null): FakeParameter
    {
        return new FakeParameter($wert);
    }

    public function executeQuery(): FakeErgebnis
    {
        $this->db->aufzeichnen($this->tabelle, array_map(
            static fn ($s): string => $s instanceof FakeFunktion ? $s->name : (string) $s,
            $this->spalten
        ));
        $zeilen = array_values(array_filter(
            $this->db->tabellen[$this->tabelle] ?? [],
            function (array $zeile): bool {
                foreach ($this->bedingungen as $bedingung) {
                    if (!$bedingung($zeile)) {
                        return false;
                    }
                }
                return true;
            }
        ));
        $ordnung = $this->ordnung;
        usort($zeilen, static function (array $a, array $b) use ($ordnung): int {
            foreach ($ordnung as [$spalte, $richtung]) {
                $vergleich = ($a[$spalte] ?? null) <=> ($b[$spalte] ?? null);
                if ($vergleich !== 0) {
                    return $richtung === 'DESC' ? -$vergleich : $vergleich;
                }
            }
            return 0;
        });
        $zeilen = array_slice($zeilen, $this->ab, $this->hoechstens);
        return new FakeErgebnis(array_map(fn (array $zeile): array => $this->auswaehlen($zeile), $zeilen));
    }

    /** @return array<string, mixed> */
    private function auswaehlen(array $zeile): array
    {
        $ergebnis = [];
        foreach ($this->spalten as $spalte) {
            if ($spalte === '*') {
                foreach ($zeile as $name => $wert) {
                    $ergebnis[$name] = $wert instanceof GrosserText ? $wert->text() : $wert;
                }
                continue;
            }
            $wert = $zeile[$spalte] ?? null;
            $ergebnis[$spalte] = $wert instanceof GrosserText ? $wert->text() : $wert;
        }
        foreach ($this->aliasse as $alias => $funktion) {
            $ergebnis[$alias] = ($funktion->rechnen)($zeile);
        }
        return $ergebnis;
    }
}
