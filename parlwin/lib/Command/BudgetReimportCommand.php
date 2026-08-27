<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Command;

use OCA\ParliamentWinterthur\Db\BudgetAntragMapper;
use OCA\ParliamentWinterthur\Db\BudgetVerteilungMapper;
use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCA\ParliamentWinterthur\Service\EreignisService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * OCC-Befehl, um ein Budgetjahr sauber neu aus dem Budgetbuch (PDF) einzulesen —
 * etwa nachdem eine frühere, fehlerhafte Fassung in der Datenbank liegt.
 *
 * Der Re-Import ersetzt die Produktegruppen und Investitionen des Jahres
 * vollständig und aktualisiert die Jahres-Kennzahlen (Steuerfuss, Totale,
 * Gesamtergebnis, künstliche Produktegruppe). Die eigenen Anträge und
 * Pauschalanträge der Fraktion bleiben erhalten — ausser mit «--purge».
 *
 * Verwendung:
 *   php occ parlwin:budget-reimport 2026
 *   php occ parlwin:budget-reimport 2026 --purge   (auch Anträge/Pauschalanträge löschen)
 */
class BudgetReimportCommand extends Command
{
    protected static $defaultName = 'parlwin:budget-reimport';

    public function __construct(
        private readonly BudgetImportService $import,
        private readonly BudgetAntragMapper $antraege,
        private readonly BudgetVerteilungMapper $verteilungen,
        private readonly EreignisService $ereignisse,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Liest ein Budgetjahr sauber neu aus dem Budgetbuch (PDF) ein')
            ->addArgument('jahr', InputArgument::REQUIRED, 'Budgetjahr, z.B. 2026')
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Zusätzlich alle Anträge und Pauschalanträge des Jahres löschen (voller Clean-Slate)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jahr = (int) $input->getArgument('jahr');
        $purge = (bool) $input->getOption('purge');
        if ($jahr < 2000 || $jahr > 2100) {
            $output->writeln('<error>Ungültiges Jahr: ' . $jahr . '</error>');
            return Command::FAILURE;
        }
        if ($purge) {
            $output->writeln('  Anträge und Pauschalanträge für ' . $jahr . ' werden gelöscht.');
        }
        $output->writeln('<info>Lese Budget ' . $jahr . ' neu aus dem Budgetbuch ein...</info>');
        $code = $this->reimportiere($jahr, $purge);
        if ($code === Command::SUCCESS) {
            $output->writeln('<info>Budget ' . $jahr . ' neu eingelesen (Produktegruppen, Investitionen und Kennzahlen ersetzt).</info>');
        } else {
            $output->writeln('<error>Re-Import für ' . $jahr . ' fehlgeschlagen (Budgetbuch vorhanden?).</error>');
        }
        return $code;
    }

    /**
     * Kern des Re-Imports (ohne Konsolen-Ein-/Ausgabe, darum testbar): optional die
     * Anträge/Pauschalanträge des Jahres löschen, dann aus dem Budgetbuch neu einlesen.
     */
    public function reimportiere(int $jahr, bool $purge): int
    {
        if ($jahr < 2000 || $jahr > 2100) {
            return Command::FAILURE;
        }
        if ($purge) {
            $this->antraege->deleteByJahr($jahr);
            $this->verteilungen->deleteByJahr($jahr);
        }
        try {
            $this->import->importiereJahr($jahr);
        } catch (\Throwable $e) {
            $this->ereignisse->protokolliere('fehler', 'budget', false,
                'Budget ' . $jahr . ' neu einlesen fehlgeschlagen (occ)', $e->getMessage(), 'occ');
            return Command::FAILURE;
        }
        $this->ereignisse->protokolliere('budget_reimport', 'budget', true,
            'Budget ' . $jahr . ' neu eingelesen (occ)', '', 'occ');
        return Command::SUCCESS;
    }
}
