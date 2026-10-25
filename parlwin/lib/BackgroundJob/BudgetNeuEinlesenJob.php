<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\BackgroundJob;

use OCA\ParliamentWinterthur\Service\BudgetImportService;
use OCA\ParliamentWinterthur\Service\BudgetService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Liest nach einem Update jedes Budgetjahr neu ein, das mit einem älteren Stand
 * des Einlesers gelesen wurde (F130).
 *
 * Die Einträge der Fraktion bleiben dabei unberührt: Ersetzt werden allein die
 * Zahlen des Buchs. Anträge, Notizen, Dokumente, Entscheide und
 * Pauschalverteilungen hängen an der Nummer der Produktegruppe und am
 * Investitionsvorhaben, und beide stehen im Buch unverändert.
 */
class BudgetNeuEinlesenJob extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private readonly BudgetImportService $import,
        private readonly BudgetService $budget,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($time);
        $this->setInterval(3600);
    }

    /**
     * EIN Jahr je Lauf. Das Einlesen eines Budgetjahrs lädt beide Bücher und
     * liest sie; das dauert eine knappe Minute und belegt so lange den Cron von
     * Nextcloud, der seine Aufträge nacheinander abarbeitet. Mit allen Jahren in
     * einem Lauf stand der Cron minutenlang still, und die übrigen Aufträge —
     * die Synchronisation etwa — kamen erst danach (gemessen am 25.10.2026).
     * Stündlich ein Jahr arbeitet den Rückstand ebenso ab, ohne etwas
     * aufzuhalten.
     */
    protected function run(mixed $argument): void {
        $jahre = $this->import->jahreMitAltemEinleser();
        $jahr = $jahre[0] ?? null;
        if ($jahr === null) {
            return;
        }
        try {
            $this->import->erneuereEinlesen($jahr);
            $this->budget->automatikNeuBerechnen($jahr);
            $this->logger->info(
                'Parlament Winterthur: Budget ' . $jahr . ' mit dem neuen Einleser neu gelesen; '
                . 'Anträge, Notizen und Entscheide unverändert'
            );
        } catch (\Throwable $e) {
            // Ein Jahr, dessen Buch gerade nicht erreichbar ist, bleibt auf
            // seinem Stand und wird beim nächsten Lauf wieder versucht.
            $this->logger->warning(
                'Parlament Winterthur: Budget ' . $jahr . ' konnte nicht neu gelesen werden: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
