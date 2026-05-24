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
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class FraktionsarbeitServiceTest extends TestCase
{
    private function makeService(
        GeschaeftMapper $geschaeftMapper,
        GeschaeftAktionMapper $aktionMapper,
        GeschaeftZustaendigkeitMapper $zustaendigkeitMapper,
    ): FraktionsarbeitService {
        $rollenMapper = $this->createMock(FraktionsrolleMapper::class);
        $mitgliedMapper = $this->createMock(MitgliedMapper::class);
        $kommissionMapper = $this->createMock(KommissionMapper::class);
        $ereignisMapper = $this->createMock(GeschaeftEreignisMapper::class);
        $config = $this->createMock(IConfig::class);
        $userSession = $this->createMock(IUserSession::class);
        $userSession->method('getUser')->willReturn(null);
        $groupManager = $this->createMock(IGroupManager::class);

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
        );
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
        $geschaeftMapper = $this->createMock(GeschaeftMapper::class);
        $geschaeftMapper->method('find')->willReturn(new Geschaeft());

        $aktionMapper = $this->createMock(GeschaeftAktionMapper::class);
        $erfassteAktion = null;
        $aktionMapper->method('insert')->willReturnCallback(function (GeschaeftAktion $a) use (&$erfassteAktion): GeschaeftAktion {
            $erfassteAktion = $a;
            return $a;
        });

        $zustaendigkeitMapper = $this->createMock(GeschaeftZustaendigkeitMapper::class);
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
        $geschaeftMapper = $this->createMock(GeschaeftMapper::class);
        $geschaeftMapper->method('find')->willReturn(new Geschaeft());

        $aktionMapper = $this->createMock(GeschaeftAktionMapper::class);
        $erfassteAktion = null;
        $aktionMapper->method('insert')->willReturnCallback(function (GeschaeftAktion $a) use (&$erfassteAktion): GeschaeftAktion {
            $erfassteAktion = $a;
            return $a;
        });

        $zustaendigkeitMapper = $this->createMock(GeschaeftZustaendigkeitMapper::class);
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
        $geschaeftMapper = $this->createMock(GeschaeftMapper::class);
        $geschaeftMapper->method('find')->willReturn(new Geschaeft());

        $aktionMapper = $this->createMock(GeschaeftAktionMapper::class);
        $erfassteAktion = null;
        $aktionMapper->method('insert')->willReturnCallback(function (GeschaeftAktion $a) use (&$erfassteAktion): GeschaeftAktion {
            $erfassteAktion = $a;
            return $a;
        });

        $zustaendigkeitMapper = $this->createMock(GeschaeftZustaendigkeitMapper::class);
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
}
