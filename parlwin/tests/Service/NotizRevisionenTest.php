<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\FraktionsrolleMapper;
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftAktion;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftZustaendigkeitMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCA\ParliamentWinterthur\Db\NotizRevision;
use OCA\ParliamentWinterthur\Db\NotizRevisionMapper;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Notizen führen eine Versions-History (wie git): Beim Bearbeiten wird der
 * bisherige Text als Revision archiviert. Löschen ist kein echtes Löschen mehr,
 * sondern nur ein Flag — Wiederherstellen (Undo) bringt Notiz samt History zurück.
 */
class NotizRevisionenTest extends TestCase
{
    private const AUTOR = 'testuser';

    private function makeAktion(int $id, string $text, string $autor = self::AUTOR): GeschaeftAktion
    {
        $a = new GeschaeftAktion();
        $a->setId($id);
        $a->setGeschaeftId(1);
        $a->setAktionTyp('notiz');
        $a->setText($text);
        $a->setAutorUid($autor);
        $a->setAutorName('Test User');
        return $a;
    }

    /**
     * @param array<int, GeschaeftAktion> $aktualisiert
     * @param array<int, GeschaeftAktion> $geloescht
     * @param array<int, NotizRevision> $revisionen
     */
    private function makeService(
        GeschaeftAktion $aktion,
        array &$aktualisiert,
        array &$geloescht,
        array &$revisionen,
    ): FraktionsarbeitService {
        $geschaeftMapper = $this->createStub(GeschaeftMapper::class);
        $geschaeftMapper->method('find')->willReturn(new Geschaeft());

        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $aktionMapper->method('findById')->willReturn($aktion);
        $aktionMapper->method('update')->willReturnCallback(
            function (GeschaeftAktion $a) use (&$aktualisiert): GeschaeftAktion {
                $aktualisiert[] = $a;
                return $a;
            }
        );
        $aktionMapper->method('loeschen')->willReturnCallback(
            function (GeschaeftAktion $a) use (&$geloescht): void {
                $geloescht[] = $a;
            }
        );

        $revisionMapper = $this->createStub(NotizRevisionMapper::class);
        $revisionMapper->method('insert')->willReturnCallback(
            function (NotizRevision $r) use (&$revisionen): NotizRevision {
                $revisionen[] = $r;
                return $r;
            }
        );

        $user = $this->createStub(IUser::class);
        $user->method('getUID')->willReturn(self::AUTOR);
        $user->method('getDisplayName')->willReturn('Test User');
        $userSession = $this->createStub(IUserSession::class);
        $userSession->method('getUser')->willReturn($user);

        return new FraktionsarbeitService(
            $geschaeftMapper,
            $aktionMapper,
            $this->createStub(GeschaeftZustaendigkeitMapper::class),
            $this->createStub(FraktionsrolleMapper::class),
            $this->createStub(MitgliedMapper::class),
            $this->createStub(KommissionMapper::class),
            $this->createStub(GeschaeftEreignisMapper::class),
            $this->createStub(IConfig::class),
            $userSession,
            $this->createStub(IGroupManager::class),
            $revisionMapper,
        );
    }

    public function testBearbeitenArchiviertDenBisherigenTextAlsRevision(): void
    {
        $aktion = $this->makeAktion(42, 'Alter Text');
        $aktualisiert = [];
        $geloescht = [];
        $revisionen = [];
        $service = $this->makeService($aktion, $aktualisiert, $geloescht, $revisionen);

        $service->notizAktualisieren(1, 42, 'Neuer Text');

        self::assertCount(1, $revisionen, 'Beim Bearbeiten muss eine Revision archiviert werden');
        self::assertSame('Alter Text', $revisionen[0]->getText(), 'Die Revision muss den BISHERIGEN Text festhalten');
        self::assertSame(42, $revisionen[0]->getAktionId());
        self::assertSame('Neuer Text', $aktion->getText(), 'Die Notiz selbst trägt den neuen Text');
    }

    public function testZwischenspeichernWaehrendDesTippensErzeugtKeineRevision(): void
    {
        // Autosave während der laufenden Eingabe (Debounce) führt die Stände in
        // EINEM Eintrag zusammen — es darf dabei keine Version entstehen.
        $aktion = $this->makeAktion(42, 'Alter Text');
        $aktualisiert = [];
        $geloescht = [];
        $revisionen = [];
        $service = $this->makeService($aktion, $aktualisiert, $geloescht, $revisionen);

        $service->notizAktualisieren(1, 42, 'Tipp', false);
        $service->notizAktualisieren(1, 42, 'Tipptipp', false);

        self::assertSame([], $revisionen, 'Zwischenspeichern darf keine Revision anlegen');
        self::assertSame('Tipptipp', $aktion->getText());
    }

    public function testTippenUndAbschliessenErzeugtGenauEineRevision(): void
    {
        // Langsames Tippen (mehrere Autosaves) darf sich NICHT als mehrere
        // Versionen im Verlauf niederschlagen — es bleibt bei genau einer.
        $aktion = $this->makeAktion(42, 'Alter Text');
        $aktualisiert = [];
        $geloescht = [];
        $revisionen = [];
        $service = $this->makeService($aktion, $aktualisiert, $geloescht, $revisionen);

        $service->notizAktualisieren(1, 42, 'Zwi', false);
        $service->notizAktualisieren(1, 42, 'Zwischen', false);
        $service->notizAktualisieren(1, 42, 'Zwischenstand', false);
        $service->notizAktualisieren(1, 42, 'Endfassung', true);

        self::assertCount(1, $revisionen, 'Mehrfaches Zwischenspeichern darf nur EINE Version ergeben');
        self::assertSame('Endfassung', $aktion->getText());
    }

    public function testLoeschenEntferntNichtsAusDerDatenbankSondernSetztNurDasFlag(): void
    {
        $aktion = $this->makeAktion(42, 'Text');
        $aktualisiert = [];
        $geloescht = [];
        $revisionen = [];
        $service = $this->makeService($aktion, $aktualisiert, $geloescht, $revisionen);

        $service->notizLoeschen(1, 42);

        self::assertSame([], $geloescht, 'Löschen darf die Notiz NICHT aus der Datenbank entfernen');
        self::assertCount(1, $aktualisiert, 'Löschen setzt nur das Flag (update)');
        self::assertTrue($aktion->getGeloescht(), 'Das Lösch-Flag muss gesetzt sein');
        self::assertSame('Text', $aktion->getText(), 'Der Text bleibt unverändert erhalten');
    }

    public function testWiederherstellenSetztDasFlagZurueck(): void
    {
        $aktion = $this->makeAktion(42, 'Text');
        $aktion->setGeloescht(true);
        $aktualisiert = [];
        $geloescht = [];
        $revisionen = [];
        $service = $this->makeService($aktion, $aktualisiert, $geloescht, $revisionen);

        $service->notizWiederherstellen(1, 42);

        self::assertFalse($aktion->getGeloescht(), 'Undo muss das Lösch-Flag zurücksetzen');
        self::assertCount(1, $aktualisiert);
        self::assertSame([], $revisionen, 'Wiederherstellen erzeugt keine neue Revision');
        self::assertSame('Text', $aktion->getText(), 'Der Text bleibt unverändert');
    }

    public function testNurDerAutorDarfWiederherstellen(): void
    {
        $aktion = $this->makeAktion(42, 'Text', 'jemand_anderes');
        $aktion->setGeloescht(true);
        $aktualisiert = [];
        $geloescht = [];
        $revisionen = [];
        $service = $this->makeService($aktion, $aktualisiert, $geloescht, $revisionen);

        $this->expectException(\RuntimeException::class);
        $service->notizWiederherstellen(1, 42);
    }
}
