<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\BackgroundJob;

use OCA\ParliamentWinterthur\Service\KommissionsVerknuepfungService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Verknüpft regelmässig (alle 6 Stunden) die in den «beratenen» Kommissionen
 * eines Sitzungstyps hängigen Geschäfte mit den künftigen Sitzungen – so sind
 * kurz vor einer Sitzung automatisch alle relevanten Geschäfte verknüpft.
 */
class KommissionsVerknuepfungJob extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private readonly KommissionsVerknuepfungService $service,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($time);
        $this->setInterval(6 * 3600);
    }

    protected function run(mixed $argument): void {
        try {
            $anzahl = $this->service->verlinkeAlleKuenftigen();
            if ($anzahl > 0) {
                $this->logger->info('Parlament Winterthur: ' . $anzahl . ' hängige Geschäfte automatisch mit künftigen Sitzungen verknüpft');
            }
        } catch (\Throwable $e) {
            $this->logger->error('Parlament Winterthur: Kommissions-Verknüpfung fehlgeschlagen: ' . $e->getMessage());
        }
    }
}
