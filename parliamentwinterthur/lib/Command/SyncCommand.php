<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Command;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\KalenderService;
use OCA\ParliamentWinterthur\Service\MitgliedService;
use OCA\ParliamentWinterthur\Service\SitzungService;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * OCC-Befehl für die manuelle Synchronisation.
 *
 * Verwendung:
 *   php occ parliamentwinterthur:sync
 *   php occ parliamentwinterthur:sync --nur-geschaefte
 *   php occ parliamentwinterthur:sync --nur-sitzungen
 *   php occ parliamentwinterthur:sync --nur-mitglieder
 */
class SyncCommand extends Command {
    protected static $defaultName = 'parliamentwinterthur:sync';

    public function __construct(
        private readonly GeschaeftService $geschaeftService,
        private readonly SitzungService $sitzungService,
        private readonly MitgliedService $mitgliedService,
        private readonly KalenderService $kalenderService,
        private readonly IConfig $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setDescription('Synchronisiert Daten vom Parlament Winterthur')
            ->addOption('nur-geschaefte', null, InputOption::VALUE_NONE, 'Nur Geschäfte synchronisieren')
            ->addOption('nur-sitzungen', null, InputOption::VALUE_NONE, 'Nur Sitzungen synchronisieren')
            ->addOption('nur-mitglieder', null, InputOption::VALUE_NONE, 'Nur Mitglieder/Fraktionen/Kommissionen synchronisieren');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $nurGeschaefte = $input->getOption('nur-geschaefte');
        $nurSitzungen = $input->getOption('nur-sitzungen');
        $nurMitglieder = $input->getOption('nur-mitglieder');
        $alles = !$nurGeschaefte && !$nurSitzungen && !$nurMitglieder;

        $output->writeln('<info>Parliament Winterthur: Starte Synchronisation...</info>');

        if ($alles || $nurMitglieder) {
            $output->writeln('  Synchronisiere Mitglieder, Fraktionen und Kommissionen...');
            $statistik = $this->mitgliedService->synchronisieren();
            $output->writeln(sprintf(
                '  Mitglieder: %d neu, %d aktualisiert, %d inaktiv',
                $statistik['mitglieder']['neu'],
                $statistik['mitglieder']['aktualisiert'],
                $statistik['mitglieder']['inaktiv'],
            ));
        }

        if ($alles || $nurGeschaefte) {
            $output->writeln('  Synchronisiere Geschäfte...');
            $statistik = $this->geschaeftService->synchronisieren();
            $output->writeln(sprintf(
                '  Geschäfte: %d neu, %d aktualisiert, %d als gelöscht markiert',
                $statistik['neu'],
                $statistik['aktualisiert'],
                $statistik['geloescht'],
            ));
        }

        if ($alles || $nurSitzungen) {
            $output->writeln('  Synchronisiere Sitzungen und Traktanden...');
            $statistik = $this->sitzungService->synchronisieren();
            $output->writeln(sprintf(
                '  Sitzungen: %d neu, %d aktualisiert, %d als gelöscht markiert',
                $statistik['neu'],
                $statistik['aktualisiert'],
                $statistik['geloescht'],
            ));

            $output->writeln('  Aktualisiere Kalendereinträge...');
            $this->kalenderService->sitzungenAktualisieren(
                $this->sitzungService->alleAktiven()
            );
        }

        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $this->config->setAppValue(Application::APP_ID, 'letzte_synchronisation', $jetzt);

        $output->writeln("<info>Synchronisation abgeschlossen um {$jetzt}</info>");
        return Command::SUCCESS;
    }
}
