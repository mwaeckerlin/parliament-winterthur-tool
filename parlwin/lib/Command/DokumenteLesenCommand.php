<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Command;

use OCA\ParliamentWinterthur\Service\GeschaeftDokumentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * OCC-Befehl, der die amtlichen Dokumente nachliest, die der Abgleich nur
 * verzeichnet hat (F121).
 *
 * Der Abgleich liest je Lauf nur ein Kontingent, damit er nicht stundenlang
 * läuft, und ein stündlicher Auftrag holt den Rest nach. Wer nicht warten will
 * — nach einer neuen Fassung des Lesers oder beim ersten Einrichten —, ruft
 * diesen Befehl auf.
 *
 * Verwendung:
 *   php occ parlwin:dokumente-lesen
 *   php occ parlwin:dokumente-lesen --anzahl=200
 */
#[AsCommand(name: 'parlwin:dokumente-lesen')]
class DokumenteLesenCommand extends Command
{
    protected static $defaultName = 'parlwin:dokumente-lesen';

    public function __construct(private readonly GeschaeftDokumentService $dokumente)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Liest die amtlichen Dokumente nach, die noch keinen Inhalt tragen')
            ->addOption(
                'anzahl',
                null,
                InputOption::VALUE_REQUIRED,
                'Wie viele Dokumente höchstens gelesen werden',
                '50'
            )
            ->addOption(
                'auch-gescheiterte',
                null,
                InputOption::VALUE_NONE,
                'Auch die Dokumente noch einmal lesen, die einen Grund tragen'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $anzahl = max(1, (int) $input->getOption('anzahl'));
        if ($input->getOption('auch-gescheiterte')) {
            $wieder = $this->dokumente->vergisseGescheiterte();
            $output->writeln("<info>{$wieder} gescheiterte Dokumente stehen wieder offen.</info>");
        }
        $gelesen = $this->dokumente->leseOffene($anzahl);
        $output->writeln("<info>{$gelesen} von höchstens {$anzahl} Dokumenten gelesen.</info>");
        return Command::SUCCESS;
    }
}
