<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\GeschaeftWorkflow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GeschaeftWorkflowTest extends TestCase {
    #[DataProvider('bekannteKategorieProvider')]
    public function testBekannteKategorienWerdenKanonischGemappt(string $kategorie, string $erwartet): void {
        $this->assertSame($erwartet, GeschaeftWorkflow::kanonischeKategorie($kategorie));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function bekannteKategorieProvider(): array {
        return [
            'bericht' => ['Bericht', 'bericht'],
            'beschlussantrag' => ['Beschlussantrag', 'vorlage'],
            'budget' => ['Budget', 'vorlage'],
            'budget-motion' => ['Budget-Motion', 'motion'],
            'budget-postulat' => ['Budget-Postulat', 'postulat'],
            'dringliche-interpellation' => ['Dringliche Interpellation', 'interpellation'],
            'dringliche-motion' => ['Dringliche Motion', 'motion'],
            'dringliches-postulat' => ['Dringliches Postulat', 'postulat'],
            'einzelinitiative' => ['Einzelinitiative', 'initiative'],
            'fragestunde' => ['Fragestunde', 'interpellation'],
            'interpellation' => ['Interpellation', 'interpellation'],
            'jahresrechnung' => ['Jahresrechnung', 'bericht'],
            'kreditabrechnung' => ['Kreditabrechnung', 'bericht'],
            'kreditantrag' => ['Kreditantrag', 'vorlage'],
            'motion' => ['Motion', 'motion'],
            'parlamentarische-initiative' => ['Parlamentarische Initiative', 'initiative'],
            'parlamentseigene-vorlage' => ['Parlamentseigene Vorlage', 'vorlage'],
            'postulat' => ['Postulat', 'postulat'],
            'rechtsmittel' => ['Rechtsmittel', 'vorlage'],
            'referendum' => ['Referendum', 'vorlage'],
            'schriftliche-anfrage' => ['Schriftliche Anfrage', 'schriftliche_anfrage'],
            'verordnung-rechtserlass' => ['Verordnung / Rechtserlass', 'vorlage'],
            'vertrag-vereinbarung' => ['Vertrag / Vereinbarung', 'vorlage'],
            'volksinitiative' => ['Volksinitiative', 'initiative'],
            'wahlen' => ['Wahlen', 'wahlen'],
            'uebrige-geschaefte' => ['übrige Geschäfte', 'vorlage'],
        ];
    }

    public function testUnbekannteKategorieFaelltAufDefaultZurueck(): void {
        $this->assertSame('default', GeschaeftWorkflow::kanonischeKategorie('Komplett unbekannt'));
    }

    public function testMotionHatMehrstufigeLifecycleBeschluesse(): void {
        $beschluesse = GeschaeftWorkflow::erlaubteBeschluesse('Motion');
        $this->assertContains('miteinreichen_fraktion', $beschluesse);
        $this->assertContains('ueberweisung_befuerworten', $beschluesse);
        $this->assertContains('erheblich_erklaeren', $beschluesse);
    }

    public function testPostulatHatNachberichtEntscheid(): void {
        $beschluesse = GeschaeftWorkflow::erlaubteBeschluesse('Postulat');
        $this->assertContains('nachbericht_verlangen', $beschluesse);
    }

    public function testErledigtErgaenztKenntnisnahmeEntscheide(): void {
        $beschluesse = GeschaeftWorkflow::erlaubteBeschluesse('Kreditantrag', 'Erledigt');
        $this->assertContains('kenntnisnahme_positiv', $beschluesse);
        $this->assertContains('kenntnisnahme_negativ', $beschluesse);
    }
}

