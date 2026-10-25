<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\DokumentInhaltParser;
use OCA\ParliamentWinterthur\Service\PdfZeilenLeser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * WERKZEUG, kein Regressionstest (Gruppe «messung», in keinem Testlauf
 * enthalten, Aufruf mit `npm run test:messung`).
 *
 * Zeigt je Dokument, das die lokale Gegenstelle ausliefert, die ersten langen
 * Buchstabenfolgen des Volltexts. Anlass: Der E2E-Lauf nahm «nbetreffend» als
 * Wort aus dem Volltext und fand es mit der Dokumentsuche nicht. Ob ein Wort
 * im Dokument so steht oder beim Lesen zusammengezogen wurde, sagt diese
 * Messung.
 */
#[Group('messung')]
class DokumentTextMessungTest extends TestCase
{
    public function testVolltextJedesDokuments(): void
    {
        $basis = \dirname(__DIR__, 1) . '/Fixtures';
        $dokumente = [
            'vorstoss-2026-86' => $basis . '/dokumente/vorstoss-2026-86.pdf',
            'vorstoss-2026-15' => $basis . '/dokumente/vorstoss-2026-15.pdf',
            'antwort-stadtrat-2026-15' => $basis . '/dokumente/antwort-stadtrat-2026-15.pdf',
            'medienmitteilung-budget-2027' => $basis . '/dokumente/medienmitteilung-budget-2027.pdf',
            'drehbuch-2026' => $basis . '/drehbuch/2026/drehbuch.pdf',
        ];

        $parser = new DokumentInhaltParser(new PdfZeilenLeser());
        foreach ($dokumente as $name => $datei) {
            self::assertFileExists($datei);
            $volltext = $parser->volltext($parser->abschnitte($datei));
            preg_match_all('/[A-Za-zÄÖÜäöü]{8,}/u', mb_substr($volltext, 0, 2000), $treffer);
            $erste = array_slice($treffer[0], 0, 6);
            echo "\n--- {$name}: erste langen Folgen ---\n";
            echo implode(' | ', $erste) . "\n";
            echo "--- erste 160 Zeichen ---\n";
            echo str_replace("\n", ' ⏎ ', mb_substr($volltext, 0, 160)) . "\n";
            self::assertNotSame('', $volltext, $name);
        }
    }
}
