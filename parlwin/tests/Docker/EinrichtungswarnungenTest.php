<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Docker;

use PHPUnit\Framework\TestCase;

/**
 * Die Einrichtungsübersicht von Nextcloud soll beim Start sauber sein (F122).
 *
 * Alle Selbstprüfungen — Datenverzeichnis, JavaScript-Module, Quellkarten,
 * WebDAV, `.well-known`, Schriftarten — fragen den eigenen Server über HTTP an
 * einer vertrauten Domäne. Die Installation vertraut «nextcloud-nginx» ohne
 * Port, und dort antwortet nichts: nginx hört auf 8080. Darum trägt der Watcher
 * den Namen MIT Port nach.
 */
class EinrichtungswarnungenTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../../docker/parlwin-watcher.php';
    }

    private function watcherQuelltext(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../../docker/parlwin-watcher.php');
    }

    public function testDieVertrauteDomainTraegtDenPort(): void
    {
        self::assertSame('nextcloud-nginx:8080', pwVertrauteDomain('http://nextcloud-nginx:8080'));
        self::assertSame('nextcloud-nginx:8080', pwVertrauteDomain('http://nextcloud-nginx:8080/'));
    }

    public function testOhnePortStehtNurDerName(): void
    {
        self::assertSame('cloud.example.com', pwVertrauteDomain('https://cloud.example.com'));
    }

    public function testOhneErkennbarenNamenBleibtSieLeer(): void
    {
        self::assertSame('', pwVertrauteDomain(''));
        self::assertSame('', pwVertrauteDomain('kein-verweis'));
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function einstellungen(): array
    {
        return [
            ['trusted_domains', 'ohne sie erreicht der Server sich nicht selbst'],
            ['memcache.locking', 'sonst sperrt die Datenbank die Dateien'],
            ['serverid', 'sonst meldet die Übersicht eine fehlende Serverkennung'],
            ['db:add-missing-indices', 'Nextcloud legt die Indizes beim Upgrade nicht selbst an'],
            ['db:add-missing-primary-keys', 'die Übersicht prüft die Primärschlüssel eigens'],
            ['db:add-missing-columns', 'die Übersicht prüft die Spalten eigens'],
            ['db:convert-filecache-bigint', 'die Übersicht prüft auch die ausstehende Umstellung auf bigint'],
            ['maintenance:repair', 'die Mimetype-Migrationen laufen beim Upgrade nicht mit'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('einstellungen')]
    public function testDerWatcherSetztJedeEinstellung(string $schluessel, string $grund): void
    {
        self::assertStringContainsString(
            $schluessel,
            $this->watcherQuelltext(),
            'Der Watcher muss «' . $schluessel . '» setzen: ' . $grund,
        );
    }

    public function testDieAufraeumschritteLassenSichAbschalten(): void
    {
        self::assertStringContainsString(
            "getenv('PARLWIN_SETUP_REPAIR') === '0'",
            $this->watcherQuelltext(),
            'Die teuren Aufräumschritte brauchen einen Schalter für grosse Installationen',
        );
    }

    public function testDerOcmAnbieterWirdUmgeleitet(): void
    {
        $nginx = (string) file_get_contents(__DIR__ . '/../../../docker/nginx/parlwin.conf');
        self::assertStringContainsString('location ^~ /ocm-provider', $nginx);
        self::assertStringContainsString('/index.php$request_uri', $nginx);
    }

    /**
     * Die vollständige Synchronisation und der Import eines Budgetbuchs dauern
     * Minuten. Mit der Vorgabe von nginx (60s) bricht der Webserver sie mit
     * HTTP 504 ab — gemessen am 29.09.2026 am Import des Jahrgangs 2018, als
     * das Basis-Image diese Angabe nicht mehr mitbrachte.
     */
    public function testLangeVorgaengeLaufenNichtInDieZeitgrenze(): void
    {
        $nginx = (string) file_get_contents(__DIR__ . '/../../../docker/nginx/parlwin.conf');

        self::assertSame(
            1,
            preg_match('/^\s*fastcgi_read_timeout\s+(\d+)s;/m', $nginx, $treffer),
            'Ohne eigene Angabe gilt die Vorgabe von nginx: 60 Sekunden',
        );
        self::assertGreaterThanOrEqual(
            3600,
            (int) $treffer[1],
            'Eine Stunde ist das Mindeste für einen Budget-Import',
        );
    }
}
