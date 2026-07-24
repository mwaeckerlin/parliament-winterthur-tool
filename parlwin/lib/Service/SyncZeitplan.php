<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

/**
 * Zeitplan der automatischen Synchronisation: beliebige Einträge mit
 * Wochentagen (1 = Montag … 7 = Sonntag, ISO-8601) und Uhrzeit «HH:MM».
 * Fällig ist ein Lauf, wenn zwischen zwei Prüfzeitpunkten ein Zeitplan-Punkt
 * liegt — so gehen Punkte auch bei Ausfällen oder groben Cron-Rastern nicht
 * verloren.
 */
class SyncZeitplan
{
    public const TIMEZONE = 'Europe/Zurich';

    /**
     * Standard-Zeitplan, wenn nichts konfiguriert ist: zwei Läufe an ALLEN
     * Wochentagen, um 10:00 und um 18:00 Uhr.
     *
     * @return list<array{tage: list<int>, zeit: string}>
     */
    public static function standard(): array
    {
        return [
            ['tage' => [1, 2, 3, 4, 5, 6, 7], 'zeit' => '10:00'],
            ['tage' => [1, 2, 3, 4, 5, 6, 7], 'zeit' => '18:00'],
        ];
    }

    /**
     * Wirksamer Zeitplan: der gespeicherte, sonst der Standard. Ein leerer oder
     * ungültiger gespeicherter Wert fällt auf den Standard zurück.
     *
     * @return list<array{tage: list<int>, zeit: string}>
     */
    public static function mitStandard(string $json): array
    {
        $plan = self::parse($json);
        return $plan === [] ? self::standard() : $plan;
    }

    /**
     * Normalisiert einen gespeicherten/empfangenen Zeitplan. Ungültige
     * Einträge (keine Tage, Tage ausserhalb 1..7, ungültige Zeit) werden
     * verworfen; Tage werden dedupliziert und sortiert, Zeiten auf «HH:MM»
     * formatiert.
     *
     * @return list<array{tage: list<int>, zeit: string}>
     */
    public static function parse(string $json): array
    {
        $roh = json_decode($json, true);
        if (!is_array($roh)) {
            return [];
        }
        $plan = [];
        foreach ($roh as $eintrag) {
            if (!is_array($eintrag)) {
                continue;
            }
            $tage = [];
            $alleGueltig = true;
            foreach ((array) ($eintrag['tage'] ?? []) as $tag) {
                if (!is_numeric($tag)) {
                    $alleGueltig = false;
                    break;
                }
                $t = (int) $tag;
                if ($t < 1 || $t > 7) {
                    $alleGueltig = false;
                    break;
                }
                $tage[$t] = $t;
            }
            if (!$alleGueltig || $tage === []) {
                continue;
            }
            sort($tage);

            $zeit = trim((string) ($eintrag['zeit'] ?? ''));
            if (!preg_match('/^(\d{1,2}):(\d{2})$/', $zeit, $m)) {
                continue;
            }
            $stunde = (int) $m[1];
            $minute = (int) $m[2];
            if ($stunde > 23 || $minute > 59) {
                continue;
            }
            $plan[] = [
                'tage' => array_values($tage),
                'zeit' => sprintf('%02d:%02d', $stunde, $minute),
            ];
        }
        return $plan;
    }

    /**
     * Liegt zwischen $vonTs (exklusiv) und $bisTs (inklusiv) ein
     * Zeitplan-Punkt? Deckt auch mehrtägige Fenster (Ausfälle) ab.
     *
     * @param list<array{tage: list<int>, zeit: string}> $plan
     */
    public static function faellig(array $plan, int $vonTs, int $bisTs): bool
    {
        if ($plan === [] || $bisTs <= $vonTs) {
            return false;
        }
        $tz = new \DateTimeZone(self::TIMEZONE);
        $tag = (new \DateTimeImmutable('@' . $vonTs))->setTimezone($tz)->setTime(0, 0);
        $ende = (new \DateTimeImmutable('@' . $bisTs))->setTimezone($tz)->setTime(0, 0);
        for (; $tag <= $ende; $tag = $tag->modify('+1 day')) {
            $wochentag = (int) $tag->format('N');
            foreach ($plan as $eintrag) {
                if (!in_array($wochentag, $eintrag['tage'], true)) {
                    continue;
                }
                [$stunde, $minute] = array_map('intval', explode(':', $eintrag['zeit']));
                $punkt = $tag->setTime($stunde, $minute)->getTimestamp();
                if ($punkt > $vonTs && $punkt <= $bisTs) {
                    return true;
                }
            }
        }
        return false;
    }
}
