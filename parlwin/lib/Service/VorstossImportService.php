<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\Vorstoss;
use OCA\ParliamentWinterthur\Db\VorstossMapper;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use Psr\Log\LoggerInterface;

/**
 * Übernimmt neue Dokumente aus «Fraktion/40_Vorstösse» automatisch als
 * Vorstösse. Die Herkunft ergibt sich aus dem Unterordner
 * (10_Eigene → eigene, 20_Fremde → fremde). Bereits importierte Dokumente
 * (gleicher Pfad) werden übersprungen.
 */
class VorstossImportService
{
    private const ADMIN_USER = 'admin';
    private const BASIS = 'Fraktion/40_Vorstösse';
    private const UNTERORDNER = ['10_Eigene' => 'eigene', '20_Fremde' => 'fremde'];

    public function __construct(
        private readonly IRootFolder $rootFolder,
        private readonly VorstossMapper $mapper,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return int Anzahl neu importierter Vorstösse
     */
    public function importiere(): int
    {
        $anzahl = 0;
        try {
            $folder = $this->rootFolder->getUserFolder(self::ADMIN_USER);
            foreach (self::UNTERORDNER as $unter => $herkunft) {
                $pfad = self::BASIS . '/' . $unter;
                if (!$folder->nodeExists($pfad)) {
                    continue;
                }
                $node = $folder->get($pfad);
                if (!$node instanceof Folder) {
                    continue;
                }
                foreach ($node->getDirectoryListing() as $datei) {
                    if ($datei instanceof Folder) {
                        continue;
                    }
                    $relPfad = $pfad . '/' . $datei->getName();
                    if ($this->mapper->findByDokument($relPfad) !== null) {
                        continue;
                    }
                    $this->mapper->insert($this->baueVorstoss($datei->getName(), $herkunft, $relPfad));
                    $anzahl++;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin: Vorstoss-Import fehlgeschlagen: ' . $e->getMessage());
        }
        return $anzahl;
    }

    private function baueVorstoss(string $dateiname, string $herkunft, string $relPfad): Vorstoss
    {
        $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
        $vorstoss = new Vorstoss();
        $vorstoss->setTitel($this->titelAusDateiname($dateiname));
        $vorstoss->setHerkunft($herkunft);
        $vorstoss->setStatus('neu');
        $vorstoss->setDokument($relPfad);
        $vorstoss->setErstelltAm($jetzt);
        $vorstoss->setAktualisiertAm($jetzt);
        return $vorstoss;
    }

    /** Dateiname ohne Endung als Titel. */
    public function titelAusDateiname(string $name): string
    {
        $ohneEndung = preg_replace('/\.[^.]+$/', '', $name);
        return ($ohneEndung === null || $ohneEndung === '') ? $name : $ohneEndung;
    }
}
