<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\FraktionMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\Mitglied;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MitgliedServiceTest extends TestCase {
    public function testSynchronisiereMitgliederUebernimmtAktivstatusAusImportdaten(): void {
        $mitgliedMapper = $this->createMock(MitgliedMapper::class);
        $fraktionMapper = $this->createMock(FraktionMapper::class);
        $kommissionMapper = $this->createMock(KommissionMapper::class);
        $scraper = $this->createMock(ScraperService::class);
        $groupManager = $this->createMock(IGroupManager::class);
        $userManager = $this->createMock(IUserManager::class);
        $mailer = $this->createMock(IMailer::class);
        $config = $this->createMock(IConfig::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new MitgliedService(
            $mitgliedMapper,
            $fraktionMapper,
            $kommissionMapper,
            $scraper,
            $groupManager,
            $userManager,
            $mailer,
            $config,
            $logger
        );

        $scraper->expects($this->once())
            ->method('ladeMitglieder')
            ->willReturn([
                ['id' => '100', 'name' => 'Inaktiv', 'vorname' => 'A', 'aktiv' => false],
                ['id' => '200', 'name' => 'Aktiv', 'vorname' => 'B', 'aktiv' => true],
                ['id' => '300', 'name' => 'StatusString', 'vorname' => 'C', 'status' => 'inactive'],
            ]);

        $mitgliedMapper->expects($this->exactly(3))
            ->method('findByExternId')
            ->willThrowException(new DoesNotExistException('nicht gefunden'));

        $inserted = [];
        $mitgliedMapper->expects($this->exactly(3))
            ->method('insert')
            ->willReturnCallback(static function (Mitglied $mitglied) use (&$inserted): Mitglied {
                $inserted[$mitglied->getExternId()] = $mitglied;
                return $mitglied;
            });

        $mitgliedMapper->expects($this->once())
            ->method('markiereNichtMehrAktive')
            ->with(['100', '200', '300'])
            ->willReturn(0);

        $statistik = $service->synchronisiereMitglieder();

        $this->assertSame(['neu' => 3, 'aktualisiert' => 0, 'inaktiv' => 0], $statistik);
        $this->assertArrayHasKey('100', $inserted);
        $this->assertArrayHasKey('200', $inserted);
        $this->assertArrayHasKey('300', $inserted);
        $this->assertFalse($inserted['100']->getAktiv());
        $this->assertTrue($inserted['200']->getAktiv());
        $this->assertFalse($inserted['300']->getAktiv());
    }
}

