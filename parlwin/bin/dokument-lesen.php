<?php

declare(strict_types=1);

/**
 * Liest ein einzelnes PDF und schreibt seine Abschnitte als JSON nach stdout.
 *
 * Der Aufruf kommt aus `GeschaeftDokumentService` und läuft als eigener Prozess,
 * weil das Entpacken eines PDF beliebig viel Speicher brauchen kann: Am
 * 24.09.2026 beendete ein einzelnes Dokument den ganzen Lesevorgang mit «Allowed
 * memory size of 1073741824 bytes exhausted», und ein solcher Fehler lässt sich
 * in PHP nicht abfangen. Stirbt dieser Prozess, verliert der Aufrufer nur dieses
 * eine Dokument und liest weiter.
 *
 * Verwendung:
 *   php dokument-lesen.php <pfad-zum-pdf>
 */

$pfad = $argv[1] ?? '';
if ($pfad === '' || !is_readable($pfad)) {
    fwrite(STDERR, 'Das Dokument liegt nicht vor: ' . $pfad . PHP_EOL);
    exit(2);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/Service/PdfZeilenLeser.php';
require_once __DIR__ . '/../lib/Service/DokumentInhaltParser.php';

use OCA\ParliamentWinterthur\Service\DokumentInhaltParser;
use OCA\ParliamentWinterthur\Service\PdfZeilenLeser;

try {
    $abschnitte = (new DokumentInhaltParser(new PdfZeilenLeser()))->abschnitte($pfad);
} catch (\Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(3);
}

echo json_encode($abschnitte, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit(0);
