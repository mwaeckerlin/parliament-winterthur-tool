<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\GeschaeftAktion;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\NotizRevision;
use OCA\ParliamentWinterthur\Db\NotizRevisionMapper;
use OCA\ParliamentWinterthur\Db\Vorstoss;
use OCA\ParliamentWinterthur\Db\VorstossMapper;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCA\ParliamentWinterthur\Service\VorstossService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * Vorstoss-Notizen müssen sich zu 100 % wie Geschäfts-Notizen verhalten, weil
 * beide denselben Code (NotizService) nutzen: Bearbeiten archiviert eine
 * Version, Löschen ist ein Soft-Delete mit Undo, nur der Autor darf ändern.
 * Kein eigener Vorstoss-Notiz-Code.
 */
class VorstossNotizenTest extends TestCase
{
    /** @var array<int, GeschaeftAktion> In-Memory-Aktionen (id => Aktion). */
    private array $aktionen = [];
    /** @var array<int, NotizRevision> */
    private array $revisionen = [];
    private int $naechsteId = 100;

    private function makeService(string $autor = 'u1'): VorstossService
    {
        $vorstossMapper = $this->createStub(VorstossMapper::class);
        $vorstossMapper->method('find')->willReturnCallback(function (int $id): Vorstoss {
            $v = new Vorstoss();
            $v->setId($id);
            return $v;
        });

        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $aktionMapper->method('insert')->willReturnCallback(function (GeschaeftAktion $a): GeschaeftAktion {
            $a->setId($this->naechsteId++);
            $this->aktionen[$a->getId()] = $a;
            return $a;
        });
        $aktionMapper->method('update')->willReturnCallback(function (GeschaeftAktion $a): GeschaeftAktion {
            $this->aktionen[$a->getId()] = $a;
            return $a;
        });
        $aktionMapper->method('findById')->willReturnCallback(function (int $id): GeschaeftAktion {
            if (!isset($this->aktionen[$id])) {
                throw new DoesNotExistException("Aktion {$id} fehlt");
            }
            return $this->aktionen[$id];
        });
        $aktionMapper->method('findNotizen')->willReturnCallback(function (string $typ, int $objektId): array {
            return array_values(array_filter(
                $this->aktionen,
                static fn (GeschaeftAktion $a): bool => $a->getObjektTyp() === $typ
                    && $a->getGeschaeftId() === $objektId
                    && $a->getAktionTyp() === 'notiz'
            ));
        });

        $revisionMapper = $this->createStub(NotizRevisionMapper::class);
        $revisionMapper->method('insert')->willReturnCallback(function (NotizRevision $r): NotizRevision {
            $r->setId($this->naechsteId++);
            $this->revisionen[$r->getId()] = $r;
            return $r;
        });
        $revisionMapper->method('findByAktion')->willReturnCallback(function (int $aktionId): array {
            return array_values(array_filter(
                $this->revisionen,
                static fn (NotizRevision $r): bool => $r->getAktionId() === $aktionId
            ));
        });

        $user = $this->createStub(IUser::class);
        $user->method('getUID')->willReturn($autor);
        $user->method('getDisplayName')->willReturn('Autor ' . $autor);
        $userSession = $this->createStub(IUserSession::class);
        $userSession->method('getUser')->willReturn($user);

        return new VorstossService(
            $vorstossMapper,
            $this->createStub(GeschaeftService::class),
            new NotizService($aktionMapper, $revisionMapper, $userSession),
        );
    }

    public function testNotizWirdAlsVorstossAktionAngelegt(): void
    {
        $service = $this->makeService();
        $aktion = $service->notizHinzufuegen(3, 'Erste Notiz');

        self::assertSame('notiz', $aktion['aktionTyp']);
        self::assertSame('Erste Notiz', $aktion['text']);
        self::assertSame('vorstoss', $this->aktionen[$aktion['id']]->getObjektTyp());
        self::assertSame(3, $this->aktionen[$aktion['id']]->getGeschaeftId());
    }

    public function testBearbeitenArchiviertVersionWieBeimGeschaeft(): void
    {
        $service = $this->makeService();
        $aktion = $service->notizHinzufuegen(3, 'Alter Text');

        $service->notizAktualisieren(3, $aktion['id'], 'Neuer Text');

        self::assertCount(1, $this->revisionen, 'Bearbeiten muss eine Version archivieren');
        $revision = array_values($this->revisionen)[0];
        self::assertSame('Alter Text', $revision->getText());
        self::assertSame('Neuer Text', $this->aktionen[$aktion['id']]->getText());
    }

    public function testZwischenspeichernErzeugtKeineVersion(): void
    {
        $service = $this->makeService();
        $aktion = $service->notizHinzufuegen(3, 'Start');

        $service->notizAktualisieren(3, $aktion['id'], 'Tipp', false);
        $service->notizAktualisieren(3, $aktion['id'], 'Tipptipp', false);

        self::assertSame([], $this->revisionen, 'Autosave darf keine Version anlegen');
    }

    public function testLoeschenIstSoftDeleteMitUndo(): void
    {
        $service = $this->makeService();
        $aktion = $service->notizHinzufuegen(3, 'Bleibt erhalten');

        $service->notizLoeschen(3, $aktion['id']);
        self::assertTrue($this->aktionen[$aktion['id']]->getGeloescht(), 'Löschen setzt nur das Flag');
        self::assertSame('Bleibt erhalten', $this->aktionen[$aktion['id']]->getText());

        $wieder = $service->notizWiederherstellen(3, $aktion['id']);
        self::assertFalse($wieder['geloescht'], 'Undo stellt die Notiz wieder her');
    }

    public function testNurDerAutorDarfBearbeiten(): void
    {
        $service = $this->makeService('autor');
        $aktion = $service->notizHinzufuegen(3, 'Meins');

        $fremd = $this->makeServiceMitBestehendenAktionen('jemand_anderes');
        $this->expectException(\RuntimeException::class);
        $fremd->notizAktualisieren(3, $aktion['id'], 'Fremd');
    }

    /** Zweiter Service auf denselben In-Memory-Aktionen, aber mit anderem Nutzer. */
    private function makeServiceMitBestehendenAktionen(string $autor): VorstossService
    {
        $service = $this->makeService($autor);
        return $service;
    }

    public function testListeLiefertAktiveUndGeloeschteNotizen(): void
    {
        $service = $this->makeService();
        $a = $service->notizHinzufuegen(3, 'A');
        $service->notizHinzufuegen(3, 'B');
        $service->notizLoeschen(3, $a['id']);

        $liste = $service->notizen(3);
        self::assertCount(2, $liste, 'Liste enthält aktive UND gelöschte Notizen (für Undo)');
    }
}
