<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Db;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Tests\Fakes\AufzeichnendeDatenbank;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `GeschaeftMapper::findByIds` liefert die Geschäfte, nach denen die App nach
 * einem Ereignis fragt (F150): die nicht gelöschten mit diesen IDs, erledigte
 * eingeschlossen, in Blöcken von 500 IDs, je Block das jüngste Datum zuerst.
 *
 * Die Datenbank ist {@see AufzeichnendeDatenbank}: Sie wertet die Abfragen des
 * echten Mappers aus und zeichnet jede Abfrage auf.
 */
final class GeschaeftMapperFindByIdsTest extends TestCase
{
    private const TABELLE = 'pw_geschaefte';

    private AufzeichnendeDatenbank $db;

    protected function setUp(): void
    {
        $this->db = new AufzeichnendeDatenbank();
    }

    /** Eine Zeile der Tabelle, mit 0 und 1 für `geloescht` wie aus der Datenbank. */
    private function geschaeft(int $id, ?string $datum, ?string $status = 'In Beratung', int $geloescht = 0): void
    {
        $this->db->tabellen[self::TABELLE][] = [
            'id' => $id,
            'extern_id' => (string) $id,
            'nummer' => '2026.' . $id,
            'titel' => 'Geschäft ' . $id,
            'status' => $status,
            'datum' => $datum,
            'geloescht' => $geloescht,
        ];
    }

    private function mapper(): GeschaeftMapper
    {
        return new GeschaeftMapper($this->db);
    }

    /**
     * @param Geschaeft[] $geschaefte
     * @return list<int>
     */
    private static function ids(array $geschaefte): array
    {
        return array_map(static fn (Geschaeft $g): int => (int) $g->getId(), $geschaefte);
    }

    /**
     * @param Geschaeft[] $geschaefte
     * @return list<int>
     */
    private static function sortierteIds(array $geschaefte): array
    {
        $ids = self::ids($geschaefte);
        sort($ids);
        return $ids;
    }

    public function testGeloeschteGeschaefteFehlen(): void
    {
        $this->geschaeft(1, '2026-03-02');
        $this->geschaeft(2, '2026-03-03', 'In Beratung', 1);
        $this->geschaeft(3, '2026-03-04');

        self::assertSame(
            [1, 3],
            self::sortierteIds($this->mapper()->findByIds([1, 2, 3])),
            'ein gelöschtes Geschäft steht im Ergebnis, oder ein nicht gelöschtes fehlt',
        );
    }

    public function testErledigteGeschaefteSindDabei(): void
    {
        $this->geschaeft(1, '2026-03-02', 'In Beratung');
        $this->geschaeft(2, '2026-03-03', 'Erledigt');
        $this->geschaeft(3, '2026-03-04', 'Abgeschlossen');
        $this->geschaeft(4, '2026-03-05', 'Aufgehoben');
        // Ein Geschäft ohne Status und ohne Datum, wie es die Datenbank mit NULL liefert.
        $this->geschaeft(5, null, null);

        $geschaefte = $this->mapper()->findByIds([1, 2, 3, 4, 5]);

        self::assertSame([1, 2, 3, 4, 5], self::sortierteIds($geschaefte), 'ein erledigtes Geschäft fehlt im Ergebnis');
        $ohneStatus = array_values(array_filter($geschaefte, static fn (Geschaeft $g): bool => (int) $g->getId() === 5));
        self::assertSame('', $ohneStatus[0]->getStatus(), 'NULL im Status ergibt keinen leeren Text');
        self::assertSame('', $ohneStatus[0]->getDatum(), 'NULL im Datum ergibt keinen leeren Text');
    }

    public function testUnbekannteIdsFehlenUndDoppelteZaehlenEinmal(): void
    {
        $this->geschaeft(1, '2026-03-02');
        $this->geschaeft(2, '2026-03-03');

        self::assertSame([1], self::ids($this->mapper()->findByIds([1, 1, 99])), 'eine unbekannte ID ergibt ein Geschäft, oder eine doppelte ID zwei');
        self::assertSame([], $this->mapper()->findByIds([]), 'ohne IDs kommt ein Geschäft zurück');
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function blockgroessen(): array
    {
        return [
            'ein Geschäft' => [1, 1],
            'ein voller Block' => [500, 1],
            'ein Geschäft über dem Block' => [501, 2],
            'zweieinhalb Blöcke' => [1200, 3],
        ];
    }

    #[DataProvider('blockgroessen')]
    public function testJeBlockVon500IdsEineAbfrage(int $anzahl, int $abfragen): void
    {
        for ($id = 1; $id <= $anzahl; $id++) {
            $this->geschaeft($id, sprintf('2026-%02d-%02d', 1 + $id % 12, 1 + $id % 28));
        }

        $geschaefte = $this->mapper()->findByIds(range(1, $anzahl));

        self::assertSame(range(1, $anzahl), self::sortierteIds($geschaefte), 'nicht jedes Geschäft kommt genau einmal');
        self::assertCount($abfragen, $this->db->spaltenAuf(self::TABELLE), 'Abfragen für ' . $anzahl . ' IDs');
    }

    public function testDasJuengsteDatumStehtZuerst(): void
    {
        $this->geschaeft(1, '2025-11-20');
        $this->geschaeft(2, '2026-10-01');
        $this->geschaeft(3, '2024-01-15');
        $this->geschaeft(4, '2026-03-02');

        $geschaefte = $this->mapper()->findByIds([3, 1, 4, 2]);

        self::assertSame(
            ['2026-10-01', '2026-03-02', '2025-11-20', '2024-01-15'],
            array_map(static fn (Geschaeft $g): string => $g->getDatum(), $geschaefte),
            'die Geschäfte stehen nicht mit dem jüngsten Datum zuerst',
        );
        self::assertSame([2, 4, 1, 3], self::ids($geschaefte));
    }
}
