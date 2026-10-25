<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\GeschaeftAktion;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\NotizRevisionMapper;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Notizen am übergeordneten Zusammenhang (F124): Das ganze Budgetjahr, ein
 * Departement und ein Tab tragen Notizen. Das Jahr ist die Kennung, der Name
 * des Departements oder des Tabs der Schlüssel; zwei Ebenen mit demselben Jahr
 * und verschiedenen Schlüsseln führen getrennte Notizen.
 *
 * Marc am 24.10.2026: «Notizen sollte man überall anlegen, auch in einem
 * übergeordneten Zusammenhang.»
 */
final class BudgetEbenenNotizenTest extends TestCase {
    private GeschaeftAktionMapper $aktionen;
    private NotizService $service;

    protected function setUp(): void {
        // Nur der Mapper der Aktionen trägt Erwartungen; alles andere ist
        // Beiwerk und darum ein Stub. Ein Mock ohne Erwartung meldet in
        // PHPUnit 13 einen Hinweis, und jeder Hinweis lässt den Lauf scheitern.
        $this->aktionen = $this->createMock(GeschaeftAktionMapper::class);
        $revisionen = $this->createStub(NotizRevisionMapper::class);
        $user = $this->createStub(IUser::class);
        $user->method('getUID')->willReturn('mw');
        $user->method('getDisplayName')->willReturn('Marc Wäckerlin');
        $session = $this->createStub(IUserSession::class);
        $session->method('getUser')->willReturn($user);
        $this->service = new NotizService($this->aktionen, $revisionen, $session);
    }

    public function testNotizDesDepartementsTraegtSeinenNamenAlsSchluessel(): void {
        $this->aktionen->expects(self::once())
            ->method('insert')
            ->willReturnCallback(static function (GeschaeftAktion $a): GeschaeftAktion {
                self::assertSame('budget_departement', $a->getObjektTyp());
                self::assertSame(2027, $a->getGeschaeftId());
                self::assertSame('Präsidiales', $a->getObjektSchluessel());
                self::assertSame('Vor der Sitzung besprechen', $a->getText());
                return $a;
            });

        $this->service->hinzufuegen('budget_departement', 2027, 'Vor der Sitzung besprechen', 'notiz', 'Präsidiales');
    }

    public function testNotizDesGanzenBudgetsFuehrtDenLeerenSchluessel(): void {
        $this->aktionen->expects(self::once())
            ->method('insert')
            ->willReturnCallback(static function (GeschaeftAktion $a): GeschaeftAktion {
                self::assertSame('budget_jahr', $a->getObjektTyp());
                self::assertSame('', $a->getObjektSchluessel());
                return $a;
            });

        $this->service->hinzufuegen('budget_jahr', 2027, 'Unsere Haltung zum Budget');
    }

    public function testListeFragtDenSchluesselMitAb(): void {
        $this->aktionen->expects(self::once())
            ->method('findNotizen')
            ->with('budget_tab', 2027, 'notiz', 'investition')
            ->willReturn([]);

        self::assertSame([], $this->service->liste('budget_tab', 2027, 'notiz', 'investition'));
    }

    /**
     * Eine Aktion, die eine andere Stelle als die Notizen schreibt — die
     * Protokollierung einer Änderung an den Stammdaten etwa —, setzt den neuen
     * Schlüssel nicht, und die Datenbank führt ihn dann als NULL. Beim Lesen
     * muss das ein leerer Schlüssel sein.
     *
     * Ohne diese Duldung endete jede Antwort, die eine solche Aktion mitliefert,
     * mit Status 500: «Cannot assign null to property objektSchluessel of type
     * string» (gemessen am 24.10.2026 im e2e-Lauf bei PUT
     * /geschaefte/1/stammdaten).
     */
    public function testEineAktionOhneSchluesselLaesstSichLesen(): void {
        $aktion = new GeschaeftAktion();
        $aktion->setObjektTyp('geschaeft');
        $aktion->setGeschaeftId(1);
        $aktion->setObjektSchluessel(null);
        $aktion->setAktionTyp('notiz');
        $aktion->setText('Vor der Migration geschrieben');
        $this->aktionen->expects(self::once())->method('findNotizen')->willReturn([$aktion]);

        $liste = $this->service->liste('geschaeft', 1);
        self::assertSame('', $liste[0]['objektSchluessel'], 'ein fehlender Schlüssel ist ein leerer Schlüssel');
    }

    public function testNotizTraegtIhrenSchluesselNachAussen(): void {
        $aktion = new GeschaeftAktion();
        $aktion->setObjektTyp('budget_tab');
        $aktion->setGeschaeftId(2027);
        $aktion->setObjektSchluessel('steuerfuss');
        $aktion->setAktionTyp('notiz');
        $aktion->setText('Senkung prüfen');
        $this->aktionen->expects(self::once())->method('findNotizen')->willReturn([$aktion]);

        $liste = $this->service->liste('budget_tab', 2027, 'notiz', 'steuerfuss');
        self::assertSame('steuerfuss', $liste[0]['objektSchluessel']);
    }
}
