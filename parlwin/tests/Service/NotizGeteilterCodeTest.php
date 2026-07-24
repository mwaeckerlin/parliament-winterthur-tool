<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use PHPUnit\Framework\TestCase;

/**
 * Guard gegen erneute Divergenz: Notizen dürfen NICHT je Objektart eigen
 * implementiert werden. Sowohl die Geschäfts- als auch die Vorstoss-Seite
 * müssen den EINEN geteilten NotizService verwenden — kein zweiter Notiz-Code.
 */
class NotizGeteilterCodeTest extends TestCase
{
    private static function quelle(string $relPfad): string
    {
        return (string) file_get_contents(__DIR__ . '/../../lib/' . $relPfad);
    }

    public function testGeschaeftUndVorstossNutzenDenselbenNotizService(): void
    {
        foreach ([
            'Service/FraktionsarbeitService.php',
            'Service/VorstossService.php',
        ] as $datei) {
            self::assertStringContainsString(
                'notizService',
                self::quelle($datei),
                "{$datei} muss die Notizen an den geteilten NotizService delegieren"
            );
        }
    }

    public function testVorstossHatKeinenEigenenNotizCodeMehr(): void
    {
        $vorstossService = self::quelle('Service/VorstossService.php');
        self::assertStringNotContainsString(
            'addNotiz(',
            $vorstossService,
            'VorstossService darf die alte JSON-Notiz-Implementierung (addNotiz) nicht mehr aufrufen'
        );
    }

    public function testNotizServiceIstDieEinzigeQuelleFuerRevisionen(): void
    {
        // Revisionen werden ausschliesslich im geteilten Service angelegt.
        self::assertStringContainsString(
            'new NotizRevision()',
            self::quelle('Service/NotizService.php'),
            'Der geteilte NotizService kapselt das Anlegen von Revisionen'
        );
        self::assertStringNotContainsString(
            'new NotizRevision()',
            self::quelle('Service/FraktionsarbeitService.php'),
            'FraktionsarbeitService darf keine Revisionen mehr selbst anlegen'
        );
    }
}
