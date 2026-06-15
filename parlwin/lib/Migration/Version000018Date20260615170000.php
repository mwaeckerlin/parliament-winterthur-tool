<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000018Date20260615170000 extends SimpleMigrationStep {
    public function __construct(
        private readonly IDBConnection $db,
        private readonly IConfig $config,
        private readonly IGroupManager $groupManager,
    ) {
    }

    public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options): ?Schema
    {
        return null;
    }

    public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void
    {
        $prefix = (string) $this->config->getSystemValue('dbtableprefix', 'oc_');
        $kalenderNutzer = (string) $this->config->getAppValue('parlwin', 'kalender_nutzer', '');
        $gruppe = (string) $this->config->getAppValue('parlwin', 'nextcloud_gruppe', '');

        if (!$kalenderNutzer || !$gruppe || !$this->groupManager->groupExists($gruppe)) {
            return;
        }

        try {
            // Fraktions-Kalender finden
            $res = $this->db->executeQuery(
                "SELECT id FROM `{$prefix}calendars` WHERE principaluri = ? AND uri = ? LIMIT 1",
                ["principals/users/{$kalenderNutzer}", 'parlwin-fraktion-kalender']
            );
            $cal = $res->fetchOne();
            if (!$cal) {
                return;
            }

            // Share bereits vorhanden?
            $res = $this->db->executeQuery(
                "SELECT id FROM `{$prefix}dav_shares` WHERE resourceid = ? AND principaluri = ? LIMIT 1",
                [$cal, "principals/groups/{$gruppe}"]
            );
            if ($res->fetchOne()) {
                return;
            }

            // Eintragen via executeQuery (kann keine INSERT, aber Syntax ist SQL direkt)
            $this->db->executeQuery(
                "INSERT INTO `{$prefix}dav_shares` (principaluri, type, access, resourceid) VALUES (?, ?, ?, ?)",
                ["principals/groups/{$gruppe}", 1, 3, $cal]
            );
            $output->info("parlwin: Fraktionskalender mit Gruppe geteilt");
        } catch (\Throwable $e) {
            $output->info("parlwin: Kalender-Sharing übersprungen: " . $e->getMessage());
        }
    }
}
