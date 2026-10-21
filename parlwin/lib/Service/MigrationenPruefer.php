<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCP\IDBConnection;

/**
 * Vergleicht die Migrationen im Code mit denen, die in der Instanz gelaufen
 * sind.
 *
 * Nextcloud führt die Migrationen einer App nur aus, wenn die Version in
 * `info.xml` höher ist als die installierte. Bleibt die Version gleich, läuft
 * eine neu hinzugekommene Migration schlicht nicht, und die Instanz arbeitet
 * still mit dem alten Schema weiter: Am 24.09.2026 behielt die Spalte
 * `quelle_hash` 64 Zeichen, und jedes Lesen eines Dokuments endete mit «Data
 * too long for column». Weder ein Test noch der Start meldeten etwas.
 */
class MigrationenPruefer
{
    public function __construct(private readonly IDBConnection $db)
    {
    }

    /**
     * Die Namen der Migrationen, die im Code stehen: «000053Date20260924090000»
     * aus der Klasse `Version000053Date20260924090000`.
     *
     * @return list<string>
     */
    public function imCode(string $verzeichnis): array
    {
        $namen = [];
        foreach (glob($verzeichnis . '/Version*.php') ?: [] as $datei) {
            $namen[] = substr(basename($datei, '.php'), \strlen('Version'));
        }
        sort($namen);

        return $namen;
    }

    /**
     * Die Namen der Migrationen, die in dieser Instanz gelaufen sind.
     *
     * @return list<string>
     */
    public function ausgefuehrt(string $app = 'parlwin'): array
    {
        $abfrage = $this->db->getQueryBuilder();
        $abfrage->select('version')
            ->from('migrations')
            ->where($abfrage->expr()->eq('app', $abfrage->createNamedParameter($app)));
        $ergebnis = $abfrage->executeQuery();
        $namen = [];
        foreach ($ergebnis->fetchAll() as $zeile) {
            $namen[] = (string) $zeile['version'];
        }
        $ergebnis->closeCursor();
        sort($namen);

        return $namen;
    }

    /**
     * Was im Code steht und nie gelaufen ist.
     *
     * @param list<string> $imCode
     * @param list<string> $ausgefuehrt
     *
     * @return list<string>
     */
    public function fehlende(array $imCode, array $ausgefuehrt): array
    {
        return array_values(array_diff($imCode, $ausgefuehrt));
    }
}
