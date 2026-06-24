<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\SitzungstypMapper;
use Psr\Log\LoggerInterface;

/**
 * Verknüpft automatisch die in den «beratenen» Kommissionen eines Sitzungstyps
 * hängigen Geschäfte mit den künftigen Sitzungen dieses Typs. Wird von einem
 * Hintergrund-Job (kurz vor der Sitzung) sowie beim Anlegen einer Sitzung
 * aufgerufen.
 *
 * Die Geschäft→Kommission-Zuordnung erfolgt textbasiert über den Status,
 * identisch zur Frontend-Logik in Kommissionsliste.vue (geschaefteFuer).
 */
class KommissionsVerknuepfungService {
    private const STOPWORDS = [
        'und', 'der', 'die', 'das', 'den', 'dem', 'bei', 'pendent',
        'für', 'fuer', 'in', 'im', 'zur', 'zum', 'von', 'vom',
    ];

    public function __construct(
        private readonly SitzungMapper $sitzungMapper,
        private readonly SitzungstypMapper $typMapper,
        private readonly KommissionMapper $kommissionMapper,
        private readonly GeschaeftMapper $geschaeftMapper,
        private readonly SitzungGeschaeftService $sitzungGeschaeftService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Verknüpft für alle künftigen Sitzungen mit kommissions-konfiguriertem Typ
     * die hängigen Geschäfte der jeweiligen Kommissionen.
     *
     * @return int Anzahl neu erstellter Verknüpfungen
     */
    public function verlinkeAlleKuenftigen(): int {
        $anzahl = 0;
        try {
            $sitzungen = $this->sitzungMapper->findKuenftige();
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin: Kommissions-Verknüpfung fehlgeschlagen: ' . $e->getMessage());
            return 0;
        }
        foreach ($sitzungen as $sitzung) {
            $anzahl += $this->verlinkeFuerSitzung((int) $sitzung->getId(), (int) $sitzung->getTypId());
        }
        return $anzahl;
    }

    /** Verknüpft die Kommissions-Geschäfte für eine einzelne Sitzung. */
    public function verlinkeFuerSitzung(int $sitzungId, int $typId): int {
        if ($typId <= 0) {
            return 0;
        }
        try {
            $typ = $this->typMapper->find($typId);
        } catch (\Throwable) {
            return 0;
        }
        $kommIds = json_decode($typ->getKommissionen() ?: '[]', true);
        if (!is_array($kommIds) || $kommIds === []) {
            return 0;
        }
        $anzahl = 0;
        foreach ($kommIds as $kommId) {
            try {
                $kommission = $this->kommissionMapper->find((int) $kommId);
            } catch (\Throwable) {
                continue;
            }
            foreach ($this->geschaefteFuerKommission((string) $kommission->getName()) as $geschaeft) {
                $gid = (int) $geschaeft->getId();
                if (!$this->sitzungGeschaeftService->verlinkt($sitzungId, $gid)) {
                    $this->sitzungGeschaeftService->verlinke($sitzungId, $gid, true);
                    $anzahl++;
                }
            }
        }
        return $anzahl;
    }

    /**
     * Hängige (nicht erledigte) Geschäfte, deren Status auf die Kommission
     * verweist. PHP-Port von Kommissionsliste.vue → geschaefteFuer().
     *
     * @return \OCA\ParliamentWinterthur\Db\Geschaeft[]
     */
    public function geschaefteFuerKommission(string $kommissionName): array {
        $kommissionName = trim($kommissionName);
        if ($kommissionName === '') {
            return [];
        }
        $knameRaw = mb_strtolower($kommissionName);
        $knameTokens = $this->tokenize($kommissionName);

        $treffer = [];
        foreach ($this->geschaeftMapper->findAll(10000, 0, false) as $geschaeft) {
            if ($geschaeft->getGeloescht()) {
                continue;
            }
            $status = mb_strtolower((string) $geschaeft->getStatus());
            if (!preg_match('/\S*kommission\S*/u', $status)) {
                continue;
            }
            if ($knameTokens === []) {
                if (str_contains($status, $knameRaw)) {
                    $treffer[] = $geschaeft;
                }
                continue;
            }
            $statusStripped = $this->stripKommissionWords((string) $geschaeft->getStatus());
            $alle = true;
            foreach ($knameTokens as $token) {
                if (!str_contains($statusStripped, $token)) {
                    $alle = false;
                    break;
                }
            }
            if ($alle) {
                $treffer[] = $geschaeft;
            }
        }
        return $treffer;
    }

    private function stripKommissionWords(string $s): string {
        $s = mb_strtolower($s);
        $s = preg_replace('/[,.;:()\/]/u', ' ', $s) ?? $s;
        $s = preg_replace('/\S*kommission\S*/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    /** @return string[] */
    private function tokenize(string $s): array {
        $stop = array_flip(self::STOPWORDS);
        $tokens = [];
        foreach (explode(' ', $this->stripKommissionWords($s)) as $token) {
            if (mb_strlen($token) >= 3 && !isset($stop[$token])) {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }
}
