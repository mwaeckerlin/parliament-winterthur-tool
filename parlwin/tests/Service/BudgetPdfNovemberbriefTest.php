<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\BudgetAntrag;
use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetInvestition;
use OCA\ParliamentWinterthur\Db\BudgetInvestitionMapper;
use OCA\ParliamentWinterthur\Db\BudgetJahr;
use OCA\ParliamentWinterthur\Db\BudgetJahrMapper;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppe;
use OCA\ParliamentWinterthur\Db\BudgetProduktegruppeMapper;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCA\ParliamentWinterthur\Service\BudgetBuchParser;
use OCA\ParliamentWinterthur\Service\BudgetDrehbuchParser;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use PHPUnit\Framework\TestCase;

/**
 * Antrags-PDF (F92/F97/F100): nur die tatsächlich einzureichenden Anträge stehen
 * im PDF; ein nicht eingereichter Pauschalantrag erzeugt keine Zeile. Steuerfuss-
 * Anträge stehen am Ende (F88).
 *
 * Novemberbrief-Verfügbarkeit (F91): der «Novemberbrief einlesen»-Knopf erscheint
 * nur, wenn eine noch nicht eingelesene, mindestens zwei Tage alte Novemberbrief-
 * Quelle vorliegt.
 */
class BudgetPdfNovemberbriefTest extends TestCase {
    private function zeit(int $jetzt): ITimeFactory {
        return new class($jetzt) implements ITimeFactory {
            public function __construct(private readonly int $jetzt) {
            }

            public function getTime(): int {
                return $this->jetzt;
            }
        };
    }

    private function antrag(string $bereich, string $zielRef, string $herkunft, string $haltung, string $quelle = 'manuell'): BudgetAntrag {
        $a = new BudgetAntrag();
        $a->setJahr(2026);
        $a->setBereich($bereich);
        $a->setZielRef($zielRef);
        $a->setHerkunft($herkunft);
        $a->setHaltung($haltung);
        $a->setQuelle($quelle);
        return $a;
    }

    private function gruppe(string $code): BudgetProduktegruppe {
        $g = new BudgetProduktegruppe();
        $g->setJahr(2026);
        $g->setCode($code);
        $g->setName('Gruppe ' . $code);
        $g->setDepartement('Finanzen');
        return $g;
    }

    public function testPdfEnthaeltNurEinzureichendeAntraege(): void {
        $antraege = $this->createStub(BudgetAntragMapper::class);
        $antraege->method('findByJahr')->willReturn([
            $this->antrag('globalbudget', '121', 'eigene', 'einreichen'),
            // nicht eingereicht (z.B. Pauschalantrag aus) → darf nicht im PDF sein.
            $this->antrag('globalbudget', '121', 'eigene', 'nicht_einreichen', 'pauschal'),
            // fremder Antrag, offen (Standard) → nicht einreichen → nicht im PDF.
            $this->antrag('globalbudget', '142', 'fremde', ''),
            // automatische Steuerfusssenkung → einreichen → im PDF, ganz am Ende.
            $this->antrag('steuerfuss', '', 'eigene', 'einreichen', 'pauschal'),
        ]);
        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('findByJahr')->willReturn([$this->gruppe('121'), $this->gruppe('142')]);

        $service = new BudgetImportService(
            $this->createStub(BudgetJahrMapper::class),
            $gruppen,
            $this->createStub(BudgetInvestitionMapper::class),
            $antraege,
            $this->createStub(BudgetBuchParser::class),
            $this->zeit(1000),
            $this->createStub(\OCA\ParliamentWinterthur\Db\GeschaeftMapper::class),
            $this->createStub(IClientService::class),
            $this->createStub(TraktandumMapper::class),
            $this->createStub(SitzungMapper::class),
            $this->createStub(BudgetDrehbuchParser::class),
        );

        $daten = $service->antraegePdf(2026, null);
        $eintraege = $daten['eintraege'];
        self::assertCount(2, $eintraege, 'nur die zwei einzureichenden Anträge (F92/F97)');
        $bereiche = array_map(static fn ($e) => $e['antrag']['bereich'], $eintraege);
        self::assertSame(['globalbudget', 'steuerfuss'], $bereiche, 'Steuerfuss steht am Ende (F88)');
        self::assertSame('Steuerfuss', $eintraege[1]['produktegruppe'], 'Steuerfuss-Zeile ist beschriftet');
    }

    public function testPdfGruppiertNachDepartementBudgetVorInvestition(): void {
        // F92: innerhalb eines Departements Budget (Globalbudget, Personal) vor
        // Investition; der Steuerfuss steht in einem eigenen Abschnitt am Ende.
        $antraege = $this->createStub(BudgetAntragMapper::class);
        $antraege->method('findByJahr')->willReturn([
            // Investition zuerst in der Liste — muss aber HINTER das Budget sortiert werden.
            $this->antrag('investition', '6', 'eigene', 'einreichen'),
            $this->antrag('globalbudget', '121', 'eigene', 'einreichen'),
            $this->antrag('personal', '121', 'eigene', 'einreichen'),
            $this->antrag('steuerfuss', '', 'eigene', 'einreichen', 'pauschal'),
        ]);
        $gruppen = $this->createStub(BudgetProduktegruppeMapper::class);
        $gruppen->method('findByJahr')->willReturn([$this->gruppe('121')]);
        $inv = new BudgetInvestition();
        $inv->setId(6);
        $inv->setJahr(2026);
        $inv->setDepartement('Finanzen');
        $inv->setProjekt('Projekt A');
        $investitionen = $this->createStub(BudgetInvestitionMapper::class);
        $investitionen->method('findByJahr')->willReturn([$inv]);

        $service = new BudgetImportService(
            $this->createStub(BudgetJahrMapper::class),
            $gruppen,
            $investitionen,
            $antraege,
            $this->createStub(BudgetBuchParser::class),
            $this->zeit(1000),
            $this->createStub(\OCA\ParliamentWinterthur\Db\GeschaeftMapper::class),
            $this->createStub(IClientService::class),
            $this->createStub(TraktandumMapper::class),
            $this->createStub(SitzungMapper::class),
            $this->createStub(BudgetDrehbuchParser::class),
        );

        $eintraege = $service->antraegePdf(2026, null)['eintraege'];
        $bereiche = array_map(static fn ($e) => $e['antrag']['bereich'], $eintraege);
        self::assertSame(['globalbudget', 'personal', 'investition', 'steuerfuss'], $bereiche, 'Budget vor Investition, Steuerfuss am Ende (F92)');
        $depts = array_map(static fn ($e) => $e['departement'], $eintraege);
        self::assertSame(['Finanzen', 'Finanzen', 'Finanzen', 'Steuerfuss'], $depts, 'Investition unter ihrem Departement, Steuerfuss eigener Abschnitt');
        self::assertSame('Projekt A', $eintraege[2]['produktegruppe'], 'Investitionsantrag mit Projektnamen beschriftet');
    }

    private function serviceMitGeschaeften(\OCA\ParliamentWinterthur\Db\GeschaeftMapper $geschaefte): BudgetImportService {
        return new BudgetImportService(
            $this->createStub(BudgetJahrMapper::class),
            $this->createStub(BudgetProduktegruppeMapper::class),
            $this->createStub(BudgetInvestitionMapper::class),
            $this->createStub(BudgetAntragMapper::class),
            $this->createStub(BudgetBuchParser::class),
            $this->zeit(1000),
            $geschaefte,
            $this->createStub(IClientService::class),
            $this->createStub(TraktandumMapper::class),
            $this->createStub(SitzungMapper::class),
            $this->createStub(BudgetDrehbuchParser::class),
        );
    }

    public function testWeisungLinkLiefertUrlUndNummerAusDemBudgetGeschaeft(): void {
        // F89: das gescrapte Budget-Geschäft liefert Nummer und Link zur Weisung.
        $g = new \OCA\ParliamentWinterthur\Db\Geschaeft();
        $g->setNummer('2025.110');
        $g->setUrl('https://parlament.winterthur.ch/_rte/information/2572570');
        $g->setTitel('Budget 2026 und Festsetzung des Steuerfusses');
        $geschaefte = $this->createStub(\OCA\ParliamentWinterthur\Db\GeschaeftMapper::class);
        $geschaefte->method('findeBudgetWeisung')->willReturn($g);

        $link = $this->serviceMitGeschaeften($geschaefte)->weisungLink(2026);
        self::assertNotNull($link);
        self::assertSame('https://parlament.winterthur.ch/_rte/information/2572570', $link['url']);
        self::assertSame('2025.110', $link['nummer']);
    }

    public function testWeisungLinkNullOhneBudgetGeschaeft(): void {
        $geschaefte = $this->createStub(\OCA\ParliamentWinterthur\Db\GeschaeftMapper::class);
        $geschaefte->method('findeBudgetWeisung')->willReturn(null);
        self::assertNull($this->serviceMitGeschaeften($geschaefte)->weisungLink(2026));
    }

    public function testNovemberbriefNichtVerfuegbarOhneImportiertesJahr(): void {
        // Der «Novemberbrief einlesen»-Knopf erscheint nur, wenn das Budgetjahr in
        // der Datenbank liegt und das Drehbuch NB-Korrekturen führt (F91). Ist das
        // Jahr nicht importiert, ist er nie verfügbar — unabhängig vom Drehbuch.
        $geschaefte = $this->createStub(\OCA\ParliamentWinterthur\Db\GeschaeftMapper::class);
        self::assertFalse($this->serviceMitGeschaeften($geschaefte)->novemberbriefVerfuegbar(2026));
    }
}
