<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Db;

use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class GeschaeftMapperTest extends TestCase
{
    /**
     * Regression: Eine Datenbankzeile mit NULL in einer (nullable) Spalte, die auf
     * eine als string typisierte Entity-Property abgebildet wird (z.B.
     * quelle_aktualisiert_am), darf nicht zu einem TypeError führen. Andernfalls
     * bricht das Laden der gesamten Geschäftsliste mit HTTP 500 ab und der Nutzer
     * sieht «Keine Geschäfte gefunden».
     */
    public function testMapRowToEntityToleriertNullSpalten(): void
    {
        $mapper = new class ($this->createStub(IDBConnection::class)) extends GeschaeftMapper {
            public function mapPublic(array $row): \OCA\ParliamentWinterthur\Db\Geschaeft
            {
                /** @var \OCA\ParliamentWinterthur\Db\Geschaeft $g */
                $g = $this->mapRowToEntity($row);
                return $g;
            }
        };

        $geschaeft = $mapper->mapPublic([
            'id' => 7,
            'titel' => 'Beispielgeschäft',
            'status' => 'Pendent',
            'quelle_aktualisiert_am' => null,
            'erstellt_am' => null,
            'aktualisiert_am' => null,
            'quelle_hash' => null,
        ]);

        self::assertSame('', $geschaeft->getQuelleAktualisiertAm());
        self::assertSame('', $geschaeft->getErstelltAm());
        self::assertSame('', $geschaeft->getAktualisiertAm());
        self::assertSame('Pendent', $geschaeft->getStatus());
    }
}
