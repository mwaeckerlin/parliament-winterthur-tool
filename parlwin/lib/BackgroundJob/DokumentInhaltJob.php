<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\BackgroundJob;

use OCA\ParliamentWinterthur\Service\GeschaeftDokumentService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Liest stündlich die amtlichen Dokumente nach, die der Abgleich nur
 * verzeichnet hat (F121).
 *
 * Der Abgleich selbst liest nur ein Kontingent je Lauf: Über tausend Geschäfte
 * mit mehreren PDF je Geschäft würden ihn sonst stundenlang aufhalten. So steht
 * jedes neue Dokument sofort mit Titel und Datum am Geschäft, und sein Inhalt
 * kommt kurz darauf dazu.
 */
class DokumentInhaltJob extends TimedJob
{
    public function __construct(
        ITimeFactory $time,
        private readonly GeschaeftDokumentService $service,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($time);
        $this->setInterval(3600);
    }

    protected function run(mixed $argument): void
    {
        try {
            $anzahl = $this->service->leseOffene();
            if ($anzahl > 0) {
                $this->logger->info('Parlament Winterthur: ' . $anzahl . ' amtliche Dokumente gelesen');
            }
        } catch (\Throwable $e) {
            $this->logger->error('Parlament Winterthur: Dokumente nicht gelesen: ' . $e->getMessage());
        }
    }
}
