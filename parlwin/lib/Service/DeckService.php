<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use OCP\App\IAppManager;

/**
 * Integriert die Deck-App (Kanban) für die Fraktionsarbeit.
 *
 * Analog zum geteilten Fraktionsordner und -kalender wird ein gemeinsames
 * Deck-Board «Fraktion» angelegt und mit der Fraktionsgruppe geteilt, sodass
 * To-dos und Beschluss-Verfolgung für alle Mitglieder sichtbar sind.
 *
 * Deck wird nur lose über den DI-Container angesprochen: ist die App nicht
 * installiert, bleibt das Feature inaktiv (keine harte Abhängigkeit).
 */
class DeckService {
    /** Titel des gemeinsamen Fraktions-Boards. */
    public const BOARD_TITEL = 'Fraktion';
    /** Standardfarbe des Boards (Nextcloud-Blau). */
    private const BOARD_FARBE = '0082C9';
    /** Deck-ACL-Typ für Gruppen (OCA\Deck\Db\Acl::PERMISSION_TYPE_GROUP). */
    private const ACL_TYPE_GROUP = 1;

    public function __construct(
        private readonly IAppManager $appManager,
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Ob die Deck-App installiert und damit die Integration nutzbar ist. */
    public function deckVerfuegbar(): bool {
        return $this->appManager->isInstalled('deck');
    }

    /**
     * Stellt sicher, dass das Fraktions-Board existiert und mit der Gruppe
     * geteilt ist. Gibt die Board-ID zurück oder null, wenn Deck fehlt bzw.
     * keine Gruppe konfiguriert ist.
     */
    public function sicherstellenBoard(string $owner, string $gruppe): ?int {
        if (!$this->deckVerfuegbar() || $gruppe === '') {
            return null;
        }
        try {
            $boardService = $this->boardService();
            $boardService->setUserId($owner);

            $boardId = null;
            foreach ($boardService->findAll() as $board) {
                if ($board->getTitle() === self::BOARD_TITEL) {
                    $boardId = $board->getId();
                    break;
                }
            }
            if ($boardId === null) {
                $boardId = $boardService->create(self::BOARD_TITEL, $owner, self::BOARD_FARBE)->getId();
                $this->legeStandardSpaltenAn($boardId);
            }
            $this->sicherstellenGruppenAcl($boardService, $boardId, $gruppe);
            return $boardId;
        } catch (\Throwable $e) {
            $this->logger->warning('Deck-Board konnte nicht sichergestellt werden: ' . $e->getMessage());
            return null;
        }
    }

    /** Teilt das Board mit der Gruppe (idempotent: nur wenn noch nicht geteilt). */
    private function sicherstellenGruppenAcl($boardService, int $boardId, string $gruppe): void {
        $board = $boardService->find($boardId, true);
        foreach (($board->getAcl() ?? []) as $acl) {
            if ((int) $acl->getType() === self::ACL_TYPE_GROUP && $acl->getParticipant() === $gruppe) {
                return;
            }
        }
        // edit=true, share=false, manage=false: Mitglieder dürfen Karten bearbeiten.
        $boardService->addAcl($boardId, self::ACL_TYPE_GROUP, $gruppe, true, false, false);
    }

    /** Legt die Standard-Spalten für ein frisch erstelltes Board an. */
    private function legeStandardSpaltenAn(int $boardId): void {
        $stackService = $this->container->get('OCA\\Deck\\Service\\StackService');
        $reihenfolge = 0;
        foreach (['To-do', 'In Arbeit', 'Erledigt'] as $titel) {
            $stackService->create($titel, $boardId, $reihenfolge++);
        }
    }

    /**
     * Legt eine To-do-Karte im Fraktions-Board (erste Spalte «To-do») an.
     * Gibt die Karten-ID zurück oder null, wenn Deck fehlt/keine Gruppe.
     */
    public function erstelleTodoKarte(string $owner, string $gruppe, string $titel, string $beschreibung = ''): ?int {
        if (!$this->deckVerfuegbar() || trim($titel) === '') {
            return null;
        }
        try {
            $boardId = $this->sicherstellenBoard($owner, $gruppe);
            if ($boardId === null) {
                return null;
            }
            $stackService = $this->container->get('OCA\\Deck\\Service\\StackService');
            $stacks = $stackService->findAll($boardId);
            if (!$stacks) {
                return null;
            }
            $stackId = (int) $stacks[0]->getId();
            $cardService = $this->container->get('OCA\\Deck\\Service\\CardService');
            return (int) $cardService->create($titel, $stackId, 'plain', 999, $owner, $beschreibung)->getId();
        } catch (\Throwable $e) {
            $this->logger->warning('Deck-Karte konnte nicht erstellt werden: ' . $e->getMessage());
            return null;
        }
    }

    /** Lazy-Auflösung des Deck-BoardService über den DI-Container. */
    private function boardService() {
        return $this->container->get('OCA\\Deck\\Service\\BoardService');
    }
}

