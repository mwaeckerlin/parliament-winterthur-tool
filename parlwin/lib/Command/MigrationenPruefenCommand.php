<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Command;

use OCA\ParliamentWinterthur\Service\MigrationenPruefer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Meldet jede Migration, die im Code steht und in dieser Instanz nie gelaufen
 * ist. Der Watcher ruft den Befehl beim Start auf und hält den Container an,
 * wenn er etwas findet — sonst arbeitet die Instanz still mit dem alten Schema.
 *
 * Verwendung:
 *   php occ parlwin:migrationen-pruefen
 */
#[AsCommand(name: 'parlwin:migrationen-pruefen')]
class MigrationenPruefenCommand extends Command
{
    protected static $defaultName = 'parlwin:migrationen-pruefen';

    public function __construct(private readonly MigrationenPruefer $pruefer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Prüft, ob jede Migration der App in dieser Instanz gelaufen ist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fehlende = $this->pruefer->fehlende(
            $this->pruefer->imCode(\dirname(__DIR__) . '/Migration'),
            $this->pruefer->ausgefuehrt(),
        );
        if ($fehlende === []) {
            $output->writeln('<info>Alle Migrationen sind gelaufen.</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>Nicht gelaufene Migrationen: ' . implode(', ', $fehlende) . '</error>');
        $output->writeln(
            'Nextcloud führt Migrationen nur bei einer höheren Version in info.xml aus.'
            . ' Version erhöhen und die App neu ausliefern.'
        );

        return Command::FAILURE;
    }
}
