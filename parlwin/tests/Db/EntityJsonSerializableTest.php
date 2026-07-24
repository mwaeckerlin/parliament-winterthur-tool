<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Db;

use PHPUnit\Framework\TestCase;

/**
 * Guard gegen einen Datenverlust-Bug: Eine Entity, die eine eigene
 * jsonSerialize()-Methode hat, muss auch JsonSerializable implementieren.
 *
 * Ab Nextcloud 33/34 stellt die Basis-Entity kein jsonSerialize() mehr bereit
 * und implementiert JsonSerializable nicht. Wird eine solche Entity direkt in
 * eine DataResponse gegeben (die per json_encode serialisiert), ruft
 * json_encode die eigene jsonSerialize()-Methode NUR auf, wenn die Klasse das
 * Interface implementiert – sonst gehen alle Felder ausser der id verloren
 * (leere Listen, «beim Bearbeiten alles leer»).
 */
class EntityJsonSerializableTest extends TestCase
{
    public function testEntitiesMitJsonSerializeImplementierenDasInterface(): void
    {
        $verzeichnis = __DIR__ . '/../../lib/Db';
        $verstoesse = [];

        foreach (glob($verzeichnis . '/*.php') ?: [] as $datei) {
            $code = (string) file_get_contents($datei);
            if (!preg_match('/function\s+jsonSerialize\s*\(/', $code)) {
                continue;
            }
            if (!preg_match('/\bimplements\b[^{]*\bJsonSerializable\b/', $code)) {
                $verstoesse[] = basename($datei);
            }
        }

        $this->assertSame(
            [],
            $verstoesse,
            "Entities mit jsonSerialize() müssen JsonSerializable implementieren, "
            . "sonst verliert eine DataResponse alle Felder ausser der id:\n"
            . implode("\n", $verstoesse)
        );
    }
}
