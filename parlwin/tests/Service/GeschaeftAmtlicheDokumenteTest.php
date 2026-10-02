<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftDokument;
use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftZustaendigkeitMapper;
use OCA\ParliamentWinterthur\Db\FraktionsrolleMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCA\ParliamentWinterthur\Db\NotizRevisionMapper;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\GeschaeftDokumentService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Die amtlichen Dokumente stehen an jedem Geschäft (F121): In der Übersicht mit
 * Titel, Kategorie und Datum, damit die Liste leicht bleibt; im geöffneten
 * Geschäft zusätzlich mit dem gelesenen Inhalt.
 */
class GeschaeftAmtlicheDokumenteTest extends TestCase
{
    private function dokument(): GeschaeftDokument
    {
        $dokument = new GeschaeftDokument();
        $dokument->setId(7);
        $dokument->setGeschaeftId(42);
        $dokument->setExternId('6821653');
        $dokument->setTitel('2026.15V');
        $dokument->setKategorie('Vorstoss');
        $dokument->setDatum('2026-03-02');
        $dokument->setUrl('https://parlament.winterthur.ch/_doc/6821653');
        $dokument->setMarkdown("# Schriftliche Anfrage\n\nWelche Immobilien …\n");
        $dokument->setStruktur('[{"art":"titel","seite":1,"text":"Schriftliche Anfrage"}]');
        $dokument->setSeiten(2);
        return $dokument;
    }

    private function geschaeft(): Geschaeft
    {
        $geschaeft = new Geschaeft();
        $geschaeft->setId(42);
        $geschaeft->setNummer('2026.15');
        $geschaeft->setTitel('Zweck der Immobilien im Finanzhaushalt');
        return $geschaeft;
    }

    private function dienst(): FraktionsarbeitService
    {
        $geschaeftMapper = $this->createStub(GeschaeftMapper::class);
        $geschaeftMapper->method('find')->willReturn($this->geschaeft());

        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $aktionMapper->method('findByGeschaeft')->willReturn([]);
        $aktionMapper->method('findLetzterGueltigerBeschluss')->willReturn(null);
        $aktionMapper->method('findAktuellesVotum')->willReturn(null);

        $zustaendigkeitMapper = $this->createStub(GeschaeftZustaendigkeitMapper::class);
        $zustaendigkeitMapper->method('findAktiveByGeschaeft')->willReturn([]);
        $zustaendigkeitMapper->method('findHauptByGeschaeft')->willReturn(null);

        $dokumente = $this->createStub(GeschaeftDokumentService::class);
        $dokumente->method('zuGeschaeft')->willReturn([$this->dokument()]);
        $dokumente->method('zuGeschaeften')->willReturn([42 => [$this->dokument()]]);

        return new FraktionsarbeitService(
            $geschaeftMapper,
            $aktionMapper,
            $zustaendigkeitMapper,
            $this->createStub(FraktionsrolleMapper::class),
            $this->createStub(MitgliedMapper::class),
            $this->createStub(KommissionMapper::class),
            $this->createStub(GeschaeftEreignisMapper::class),
            $this->createStub(IConfig::class),
            $this->createStub(IUserSession::class),
            $this->createStub(IGroupManager::class),
            $this->createStub(NotizRevisionMapper::class),
            $dokumente,
        );
    }

    public function testDieUebersichtTraegtDieDokumenteOhneInhalt(): void
    {
        $liste = $this->dienst()->angereicherteGeschaefte([$this->geschaeft()]);
        $dokumente = $liste[0]['amtlicheDokumente'] ?? [];
        self::assertCount(1, $dokumente);
        self::assertSame('2026.15V', $dokumente[0]['titel']);
        self::assertSame('Vorstoss', $dokumente[0]['kategorie']);
        self::assertSame(2, $dokumente[0]['seiten']);
        self::assertArrayNotHasKey(
            'markdown',
            $dokumente[0],
            'der Inhalt kommt erst beim Aufklappen — sonst trägt die Liste Megabyte',
        );
    }

    public function testDasGeoeffneteGeschaeftTraegtDenInhalt(): void
    {
        $daten = $this->dienst()->angereichertesGeschaeft(42);
        $dokumente = $daten['amtlicheDokumente'] ?? [];
        self::assertCount(1, $dokumente);
        self::assertStringContainsString('# Schriftliche Anfrage', $dokumente[0]['markdown']);
        self::assertSame('Schriftliche Anfrage', $dokumente[0]['abschnitte'][0]['text']);
    }
}
