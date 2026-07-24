<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftAktion;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\FraktionsrolleMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftZustaendigkeit;
use OCA\ParliamentWinterthur\Db\GeschaeftZustaendigkeitMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCA\ParliamentWinterthur\Db\NotizRevisionMapper;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class FraktionsarbeitServiceTest extends TestCase
{
    private function makeService(
        GeschaeftMapper $geschaeftMapper,
        GeschaeftAktionMapper $aktionMapper,
        GeschaeftZustaendigkeitMapper $zustaendigkeitMapper,
    ): FraktionsarbeitService {
        $rollenMapper = $this->createStub(FraktionsrolleMapper::class);
        $mitgliedMapper = $this->createStub(MitgliedMapper::class);
        $kommissionMapper = $this->createStub(KommissionMapper::class);
        $ereignisMapper = $this->createStub(GeschaeftEreignisMapper::class);
        $config = $this->createStub(IConfig::class);
        $userSession = $this->createStub(IUserSession::class);
        $userSession->method('getUser')->willReturn(null);
        $groupManager = $this->createStub(IGroupManager::class);

        return new FraktionsarbeitService(
            $geschaeftMapper,
            $aktionMapper,
            $zustaendigkeitMapper,
            $rollenMapper,
            $mitgliedMapper,
            $kommissionMapper,
            $ereignisMapper,
            $config,
            $userSession,
            $groupManager,
            $this->createStub(NotizRevisionMapper::class),
        );
    }

    /**
     * Stellvertretungen sind zeitlich befristbar: ein reines Datum gilt ab
     * Tagesbeginn (von) bzw. bis Tagesende (bis); ein Ende vor dem Beginn und
     * unlesbare Daten werden abgelehnt.
     */
    public function testGueltigkeitNormalisiertDatumAufTagesgrenzen(): void
    {
        $service = $this->makeService(
            $this->createStub(GeschaeftMapper::class),
            $this->createStub(GeschaeftAktionMapper::class),
            $this->createStub(GeschaeftZustaendigkeitMapper::class),
        );
        $methode = new \ReflectionMethod($service, 'normalisiereGueltigkeit');

        [$von, $bis] = $methode->invoke($service, '2026-07-20', '2026-07-21');
        self::assertSame('2026-07-20 00:00:00', $von);
        self::assertSame('2026-07-21 23:59:59', $bis);

        // Unbefristet: leere Angaben bleiben offen.
        self::assertSame([null, null], $methode->invoke($service, '', ''));
    }

    public function testGueltigkeitLehntEndeVorBeginnUndUnsinnAb(): void
    {
        $service = $this->makeService(
            $this->createStub(GeschaeftMapper::class),
            $this->createStub(GeschaeftAktionMapper::class),
            $this->createStub(GeschaeftZustaendigkeitMapper::class),
        );
        $methode = new \ReflectionMethod($service, 'normalisiereGueltigkeit');

        $this->expectException(\InvalidArgumentException::class);
        $methode->invoke($service, '2026-07-21', '2026-07-20');
    }

    public function testGueltigkeitLehntUnlesbaresDatumAb(): void
    {
        $service = $this->makeService(
            $this->createStub(GeschaeftMapper::class),
            $this->createStub(GeschaeftAktionMapper::class),
            $this->createStub(GeschaeftZustaendigkeitMapper::class),
        );
        $methode = new \ReflectionMethod($service, 'normalisiereGueltigkeit');

        $this->expectException(\InvalidArgumentException::class);
        $methode->invoke($service, 'quatsch', '');
    }

    private function makeZustaendigkeit(string $key, string $name, bool $istHaupt = false): GeschaeftZustaendigkeit
    {
        $z = new GeschaeftZustaendigkeit();
        $z->setPersonKey($key);
        $z->setPersonName($name);
        $z->setIstHaupt($istHaupt);
        return $z;
    }

    public function testZustaendigkeitenTextVonNach(): void
    {
        $geschaeftMapper = $this->createStub(GeschaeftMapper::class);
        $geschaeftMapper->method('find')->willReturn(new Geschaeft());

        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $erfassteAktion = null;
        $aktionMapper->method('insert')->willReturnCallback(function (GeschaeftAktion $a) use (&$erfassteAktion): GeschaeftAktion {
            $erfassteAktion = $a;
            return $a;
        });

        $zustaendigkeitMapper = $this->createStub(GeschaeftZustaendigkeitMapper::class);
        // Vorher: Marc war zuständig
        $zustaendigkeitMapper->method('findAktiveByGeschaeft')->willReturnOnConsecutiveCalls(
            [$this->makeZustaendigkeit('mitglied:marc', 'Marc Muster', true)],
            [$this->makeZustaendigkeit('mitglied:jana', 'Jana Beispiel', true)],
        );
        $zustaendigkeitMapper->method('ersetzeAktive');

        $service = $this->makeService($geschaeftMapper, $aktionMapper, $zustaendigkeitMapper);

        $service->zustaendigkeitenSetzen(1, [
            ['mitgliedExternId' => 'jana', 'personName' => 'Jana Beispiel'],
        ]);

        $this->assertNotNull($erfassteAktion);
        $this->assertSame('zuweisung', $erfassteAktion->getAktionTyp());
        $text = $erfassteAktion->getText();
        $this->assertStringContainsString('Von:', $text);
        $this->assertStringContainsString('Marc Muster', $text);
        $this->assertStringContainsString('→', $text);
        $this->assertStringContainsString('Nach:', $text);
        $this->assertStringContainsString('Jana Beispiel', $text);
    }

    public function testZustaendigkeitenTextWennNiemandVorher(): void
    {
        $geschaeftMapper = $this->createStub(GeschaeftMapper::class);
        $geschaeftMapper->method('find')->willReturn(new Geschaeft());

        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $erfassteAktion = null;
        $aktionMapper->method('insert')->willReturnCallback(function (GeschaeftAktion $a) use (&$erfassteAktion): GeschaeftAktion {
            $erfassteAktion = $a;
            return $a;
        });

        $zustaendigkeitMapper = $this->createStub(GeschaeftZustaendigkeitMapper::class);
        $zustaendigkeitMapper->method('findAktiveByGeschaeft')->willReturnOnConsecutiveCalls(
            [],
            [$this->makeZustaendigkeit('mitglied:jana', 'Jana Beispiel', true)],
        );
        $zustaendigkeitMapper->method('ersetzeAktive');

        $service = $this->makeService($geschaeftMapper, $aktionMapper, $zustaendigkeitMapper);

        $service->zustaendigkeitenSetzen(1, [
            ['mitgliedExternId' => 'jana', 'personName' => 'Jana Beispiel'],
        ]);

        $this->assertNotNull($erfassteAktion);
        $text = $erfassteAktion->getText();
        $this->assertStringContainsString('Von:', $text);
        $this->assertStringContainsString('(niemand)', $text);
        $this->assertStringContainsString('Jana Beispiel', $text);
    }

    public function testZustaendigkeitenUnveraendertBestaetigt(): void
    {
        $geschaeftMapper = $this->createStub(GeschaeftMapper::class);
        $geschaeftMapper->method('find')->willReturn(new Geschaeft());

        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $erfassteAktion = null;
        $aktionMapper->method('insert')->willReturnCallback(function (GeschaeftAktion $a) use (&$erfassteAktion): GeschaeftAktion {
            $erfassteAktion = $a;
            return $a;
        });

        $zustaendigkeitMapper = $this->createStub(GeschaeftZustaendigkeitMapper::class);
        $zustaendig = $this->makeZustaendigkeit('mitglied:marc', 'Marc Muster', true);
        $zustaendigkeitMapper->method('findAktiveByGeschaeft')->willReturn([$zustaendig]);
        $zustaendigkeitMapper->method('ersetzeAktive');

        $service = $this->makeService($geschaeftMapper, $aktionMapper, $zustaendigkeitMapper);

        $service->zustaendigkeitenSetzen(1, [
            ['mitgliedExternId' => 'marc', 'personName' => 'Marc Muster'],
        ]);

        $this->assertNotNull($erfassteAktion);
        $text = $erfassteAktion->getText();
        $this->assertStringContainsString('unverändert', $text);
    }

    private function makeServiceMitUser(
        GeschaeftMapper $geschaeftMapper,
        GeschaeftAktionMapper $aktionMapper,
        string $uid,
    ): FraktionsarbeitService {
        $rollenMapper = $this->createStub(FraktionsrolleMapper::class);
        $mitgliedMapper = $this->createStub(MitgliedMapper::class);
        $kommissionMapper = $this->createStub(KommissionMapper::class);
        $ereignisMapper = $this->createStub(GeschaeftEreignisMapper::class);
        $zustaendigkeitMapper = $this->createStub(GeschaeftZustaendigkeitMapper::class);
        $config = $this->createStub(IConfig::class);
        $config->method('getAppValue')->willReturnCallback(
            static fn(string $app, string $key, string $default = ''): string => $default,
        );
        $user = $this->createStub(IUser::class);
        $user->method('getUID')->willReturn($uid);
        $userSession = $this->createStub(IUserSession::class);
        $userSession->method('getUser')->willReturn($user);
        $groupManager = $this->createStub(IGroupManager::class);

        return new FraktionsarbeitService(
            $geschaeftMapper,
            $aktionMapper,
            $zustaendigkeitMapper,
            $rollenMapper,
            $mitgliedMapper,
            $kommissionMapper,
            $ereignisMapper,
            $config,
            $userSession,
            $groupManager,
            $this->createStub(NotizRevisionMapper::class),
        );
    }

    private function makeAktion(int $id, int $geschaeftId, string $typ, string $autorUid): GeschaeftAktion
    {
        $aktion = new GeschaeftAktion();
        $aktion->setId($id);
        $aktion->setGeschaeftId($geschaeftId);
        $aktion->setAktionTyp($typ);
        $aktion->setAktionCode('');
        $aktion->setTitel('Alt');
        $aktion->setText('Alt');
        $aktion->setEntscheidGueltig(true);
        $aktion->setAutorUid($autorUid);
        $aktion->setAutorName('Marc Muster');
        $aktion->setErstelltAm('2026-07-06 14:35:00');
        return $aktion;
    }

    public function testBeschlussAktualisierenErsetztFreitextStattNeueAktion(): void
    {
        $geschaeft = new Geschaeft();
        $geschaeft->setTyp('Motion');
        $geschaeft->setStatus('');
        $geschaeftMapper = $this->createStub(GeschaeftMapper::class);
        $geschaeftMapper->method('find')->willReturn($geschaeft);

        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $aktionMapper->method('findById')->willReturn($this->makeAktion(77, 1, 'beschluss', 'marc'));
        $eingefuegt = false;
        $aktionMapper->method('insert')->willReturnCallback(function (GeschaeftAktion $a) use (&$eingefuegt): GeschaeftAktion {
            $eingefuegt = true;
            return $a;
        });
        $aktualisiert = null;
        $aktionMapper->method('update')->willReturnCallback(function (GeschaeftAktion $a) use (&$aktualisiert): GeschaeftAktion {
            $aktualisiert = $a;
            return $a;
        });

        $service = $this->makeServiceMitUser($geschaeftMapper, $aktionMapper, 'marc');
        $resultat = $service->beschlussAktualisieren(1, 77, '', 'Abschreiben, aber begründet');

        $this->assertFalse($eingefuegt, 'Zwischenspeichern darf keine neue Aktion anlegen');
        $this->assertNotNull($aktualisiert);
        $this->assertSame(77, $resultat['id']);
        $this->assertSame('Abschreiben, aber begründet', $resultat['text']);
    }

    public function testBeschlussAktualisierenSetztCodeUndLabel(): void
    {
        $geschaeft = new Geschaeft();
        $geschaeft->setTyp('Motion');
        $geschaeft->setStatus('');
        $geschaeftMapper = $this->createStub(GeschaeftMapper::class);
        $geschaeftMapper->method('find')->willReturn($geschaeft);

        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $aktionMapper->method('findById')->willReturn($this->makeAktion(77, 1, 'beschluss', 'marc'));
        $aktionMapper->method('update')->willReturnArgument(0);

        $service = $this->makeServiceMitUser($geschaeftMapper, $aktionMapper, 'marc');
        $resultat = $service->beschlussAktualisieren(1, 77, 'erheblich_erklaeren', '');

        $this->assertSame('erheblich_erklaeren', $resultat['aktionCode']);
        $this->assertNotSame('Alt', $resultat['titel']);
    }

    public function testBeschlussAktualisierenVerweigertFremdenAutor(): void
    {
        $geschaeftMapper = $this->createStub(GeschaeftMapper::class);
        $geschaeftMapper->method('find')->willReturn(new Geschaeft());

        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $aktionMapper->method('findById')->willReturn($this->makeAktion(77, 1, 'beschluss', 'jemand_anders'));

        $service = $this->makeServiceMitUser($geschaeftMapper, $aktionMapper, 'marc');

        $this->expectException(\RuntimeException::class);
        $service->beschlussAktualisieren(1, 77, '', 'Neuer Text');
    }

    public function testBeschlussAktualisierenVerweigertFalschenAktionTyp(): void
    {
        $geschaeftMapper = $this->createStub(GeschaeftMapper::class);
        $geschaeftMapper->method('find')->willReturn(new Geschaeft());

        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $aktionMapper->method('findById')->willReturn($this->makeAktion(78, 1, 'notiz', 'marc'));

        $service = $this->makeServiceMitUser($geschaeftMapper, $aktionMapper, 'marc');

        $this->expectException(\InvalidArgumentException::class);
        $service->beschlussAktualisieren(1, 78, '', 'Neuer Text');
    }
}
