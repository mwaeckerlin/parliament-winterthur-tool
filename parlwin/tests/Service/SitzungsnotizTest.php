<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\GeschaeftAktion;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\NotizRevisionMapper;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Sitzungsnotizen sind Notizen, die AM GESCHÄFT haften (nicht an der Sitzung),
 * damit sie an allen aktuellen und künftigen mit dem Geschäft verknüpften
 * Sitzungen erscheinen. Technisch dieselbe geteilte Notiz-Logik, unterschieden
 * durch die Kategorie (aktion_typ «sitzungsnotiz» statt «notiz») — beide
 * Kategorien am selben Geschäft bleiben getrennt.
 */
class SitzungsnotizTest extends TestCase
{
    /** @var array<int, GeschaeftAktion> */
    private array $aktionen = [];
    private int $naechsteId = 1;

    private function service(string $autor = 'u1'): NotizService
    {
        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $aktionMapper->method('insert')->willReturnCallback(function (GeschaeftAktion $a): GeschaeftAktion {
            $a->setId($this->naechsteId++);
            $this->aktionen[$a->getId()] = $a;
            return $a;
        });
        $aktionMapper->method('findById')->willReturnCallback(function (int $id): GeschaeftAktion {
            if (!isset($this->aktionen[$id])) {
                throw new DoesNotExistException("Aktion {$id}");
            }
            return $this->aktionen[$id];
        });
        $aktionMapper->method('update')->willReturnCallback(function (GeschaeftAktion $a): GeschaeftAktion {
            $this->aktionen[$a->getId()] = $a;
            return $a;
        });
        $aktionMapper->method('findNotizen')->willReturnCallback(
            function (string $typ, int $objektId, string $kategorie = 'notiz'): array {
                return array_values(array_filter(
                    $this->aktionen,
                    static fn (GeschaeftAktion $a): bool => $a->getObjektTyp() === $typ
                        && $a->getGeschaeftId() === $objektId
                        && $a->getAktionTyp() === $kategorie
                ));
            }
        );

        $user = $this->createStub(IUser::class);
        $user->method('getUID')->willReturn($autor);
        $user->method('getDisplayName')->willReturn('Autor');
        $userSession = $this->createStub(IUserSession::class);
        $userSession->method('getUser')->willReturn($user);

        return new NotizService($aktionMapper, $this->createStub(NotizRevisionMapper::class), $userSession);
    }

    public function testSitzungsnotizWirdMitEigenerKategorieAmGeschaeftGespeichert(): void
    {
        $service = $this->service();
        $aktion = $service->hinzufuegen('geschaeft', 42, 'In der Sitzung besprochen', 'sitzungsnotiz');

        self::assertSame('sitzungsnotiz', $aktion['aktionTyp']);
        self::assertSame('sitzungsnotiz', $this->aktionen[$aktion['id']]->getAktionTyp());
        self::assertSame(42, $this->aktionen[$aktion['id']]->getGeschaeftId());
    }

    public function testNotizUndSitzungsnotizBleibenGetrennt(): void
    {
        $service = $this->service();
        $service->hinzufuegen('geschaeft', 42, 'Reguläre Notiz', 'notiz');
        $service->hinzufuegen('geschaeft', 42, 'Sitzungsnotiz A', 'sitzungsnotiz');
        $service->hinzufuegen('geschaeft', 42, 'Sitzungsnotiz B', 'sitzungsnotiz');

        self::assertSame(['Reguläre Notiz'], array_column($service->liste('geschaeft', 42, 'notiz'), 'text'));
        self::assertSame(
            ['Sitzungsnotiz A', 'Sitzungsnotiz B'],
            array_column($service->liste('geschaeft', 42, 'sitzungsnotiz'), 'text')
        );
    }

    public function testSitzungsnotizHatDenTitelSitzungsnotiz(): void
    {
        $service = $this->service();
        $aktion = $service->hinzufuegen('geschaeft', 7, 'Text', 'sitzungsnotiz');
        self::assertSame('Sitzungsnotiz', $aktion['titel']);
    }
}
