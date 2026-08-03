<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Db;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use PHPUnit\Framework\TestCase;

/**
 * Feature: Ein selbst angelegtes Geschäft trägt einen Beschreibungstext
 * («Inhalt») und optional genau eine Kommission. Beides muss in der
 * API-Ausgabe erscheinen, sonst erreicht es das Frontend nie.
 *
 * Ohne Angabe sind beide leer — «keine Kommission» ist ein gültiger Zustand.
 */
class EigenesGeschaeftFelderTest extends TestCase
{
    public function testInhaltErscheintInDerApiAusgabe(): void
    {
        $g = new Geschaeft();
        $g->setInhalt('<p>Worum es geht</p>');

        self::assertSame('<p>Worum es geht</p>', $g->jsonSerialize()['inhalt']);
    }

    public function testInhaltIstStandardmaessigLeer(): void
    {
        $g = new Geschaeft();

        self::assertArrayHasKey('inhalt', $g->jsonSerialize());
        self::assertSame('', $g->jsonSerialize()['inhalt']);
    }

    public function testKommissionErscheintInDerApiAusgabe(): void
    {
        $g = new Geschaeft();
        $g->setKommission('Aufsichtskommission');

        self::assertSame('Aufsichtskommission', $g->jsonSerialize()['kommission']);
    }

    public function testKommissionIstStandardmaessigLeer(): void
    {
        $g = new Geschaeft();

        self::assertArrayHasKey('kommission', $g->jsonSerialize());
        self::assertSame('', $g->jsonSerialize()['kommission']);
    }

    /**
     * Die Spalten müssen per Doctrine-Migration angelegt werden — eine fehlende
     * Spalte lässt jedes Laden eines Geschäfts scheitern.
     */
    public function testMigrationLegtBeideSpaltenAn(): void
    {
        foreach (['inhalt', 'kommission'] as $spalte) {
            self::assertTrue(
                $this->spalteWirdAngelegt($spalte),
                'Keine Migration legt pw_geschaefte.' . $spalte . ' an'
            );
        }
    }

    /** Sucht in den Migrationen nach dem Anlegen einer Spalte (ohne den Code auszugeben). */
    private function spalteWirdAngelegt(string $spalte): bool
    {
        foreach (glob(__DIR__ . '/../../lib/Migration/*.php') ?: [] as $datei) {
            $code = (string) file_get_contents($datei);
            if (preg_match("/addColumn\(\s*'" . preg_quote($spalte, '/') . "'/", $code) === 1) {
                return true;
            }
        }

        return false;
    }
}
