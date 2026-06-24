<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\DeckService;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/** Test-Double für ein Deck-Board. */
class FakeDeckBoard {
    public function __construct(public int $id, public string $titel, public array $acls = []) {
    }
    public function getId(): int {
        return $this->id;
    }
    public function getTitle(): string {
        return $this->titel;
    }
    public function getAcl(): array {
        return $this->acls;
    }
}

/** Test-Double für einen Deck-ACL-Eintrag. */
class FakeDeckAcl {
    public function __construct(public int $type, public string $participant) {
    }
    public function getType(): int {
        return $this->type;
    }
    public function getParticipant(): string {
        return $this->participant;
    }
}

/** Test-Double für OCA\Deck\Service\BoardService – protokolliert Aufrufe. */
class FakeBoardService {
    public array $created = [];
    public array $acls = [];
    public ?string $userId = null;

    public function __construct(public array $boards = []) {
    }
    public function setUserId(string $userId): void {
        $this->userId = $userId;
    }
    public function findAll(...$args): array {
        return $this->boards;
    }
    public function create(string $titel, string $owner, string $farbe): FakeDeckBoard {
        $this->created[] = [$titel, $owner, $farbe];
        $board = new FakeDeckBoard(99, $titel, []);
        $this->boards[] = $board;
        return $board;
    }
    public function find(int $id, bool $full = true): FakeDeckBoard {
        foreach ($this->boards as $board) {
            if ($board->getId() === $id) {
                return $board;
            }
        }
        return new FakeDeckBoard($id, DeckService::BOARD_TITEL, []);
    }
    public function addAcl(int $boardId, int $type, $participant, bool $edit, bool $share, bool $manage): void {
        $this->acls[] = [$boardId, $type, $participant];
    }
}

/** Test-Double für OCA\Deck\Service\StackService. */
class FakeStackService {
    public array $created = [];
    public function create(string $titel, int $boardId, int $order): void {
        $this->created[] = [$titel, $boardId, $order];
    }
}

class DeckServiceTest extends TestCase {
    private ?FakeStackService $stackService = null;

    private function service(FakeBoardService $bs, bool $deckInstalliert = true): DeckService {
        $this->stackService = new FakeStackService();
        $appManager = $this->createStub(IAppManager::class);
        $appManager->method('isInstalled')->willReturn($deckInstalliert);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            fn (string $id) => str_contains($id, 'StackService') ? $this->stackService : $bs
        );
        $logger = $this->createStub(LoggerInterface::class);
        return new DeckService($appManager, $container, $logger);
    }

    public function testErstelltBoardUndTeiltMitGruppe(): void {
        $bs = new FakeBoardService([]);
        $id = $this->service($bs)->sicherstellenBoard('admin', 'fraktion');

        $this->assertSame(99, $id);
        $this->assertCount(1, $bs->created);
        $this->assertSame('Fraktion', $bs->created[0][0]);
        $this->assertSame('admin', $bs->userId);
        // ACL-Typ 1 = Gruppe
        $this->assertSame([99, 1, 'fraktion'], $bs->acls[0]);
        // Standard-Spalten angelegt
        $this->assertCount(3, $this->stackService->created);
        $this->assertSame('To-do', $this->stackService->created[0][0]);
        $this->assertSame('Erledigt', $this->stackService->created[2][0]);
    }

    public function testNutztVorhandenesBoardOhneNeuesAnzulegen(): void {
        $bs = new FakeBoardService([new FakeDeckBoard(5, 'Fraktion', [])]);
        $id = $this->service($bs)->sicherstellenBoard('admin', 'fraktion');

        $this->assertSame(5, $id);
        $this->assertCount(0, $bs->created);
        $this->assertSame([5, 1, 'fraktion'], $bs->acls[0]);
    }

    public function testTeiltNichtDoppeltWennAclSchonVorhanden(): void {
        $bs = new FakeBoardService([new FakeDeckBoard(5, 'Fraktion', [new FakeDeckAcl(1, 'fraktion')])]);
        $this->service($bs)->sicherstellenBoard('admin', 'fraktion');

        $this->assertCount(0, $bs->acls);
    }

    public function testInaktivWennDeckNichtInstalliert(): void {
        $bs = new FakeBoardService([]);
        $this->assertNull($this->service($bs, false)->sicherstellenBoard('admin', 'fraktion'));
        $this->assertCount(0, $bs->created);
    }

    public function testInaktivOhneGruppe(): void {
        $bs = new FakeBoardService([]);
        $this->assertNull($this->service($bs)->sicherstellenBoard('admin', ''));
        $this->assertCount(0, $bs->created);
    }
}
