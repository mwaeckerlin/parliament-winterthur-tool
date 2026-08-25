<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Migration;

use OCA\ParliamentWinterthur\Migration\Version000043Date20260824120000;
use PHPUnit\Framework\TestCase;

/**
 * Regression: Die Notiz-Vereinheitlichungs-Migration überführt gespeicherte
 * Notiz-Daten in die `datetime`-Spalte `pw_geschaeft_aktionen.erstellt_am`. Die
 * Alt-Daten stehen u.a. im Schweizer `toLocaleString`-Format «21.5.2026,
 * 10:34:56». Die frühere Konvertierung kannte nur «d.m.Y H:i» und schrieb bei
 * allem anderen den ROHSTRING in die datetime-Spalte → «Incorrect datetime
 * value» (SQLSTATE 22007), was die App-Migration und damit das ganze
 * Nextcloud-Upgrade abbrach und die Instanz im Wartungsmodus stehen liess.
 */
class Version000043Test extends TestCase
{
    private const MYSQL_DATETIME = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

    /** Genau der Wert, der das Upgrade zerlegte. */
    public function testSchweizerLocaleFormatWirdKorrektKonvertiert(): void
    {
        $this->assertSame(
            '2026-05-21 10:34:56',
            Version000043Date20260824120000::konvertiereDatum('21.5.2026, 10:34:56'),
        );
    }

    public function testWeitereBekannteFormate(): void
    {
        // Früheres Anzeigeformat d.m.Y H:i.
        $this->assertSame('2026-05-21 10:34:00', Version000043Date20260824120000::konvertiereDatum('21.05.2026 10:34'));
        // ISO.
        $this->assertSame('2026-05-21 10:34:56', Version000043Date20260824120000::konvertiereDatum('2026-05-21 10:34:56'));
        // Einstellige Stunde im Locale-Format.
        $this->assertSame('2026-05-21 09:04:06', Version000043Date20260824120000::konvertiereDatum('21.5.2026, 9:04:06'));
    }

    /**
     * Kern der Absicherung: Ein nicht parsbarer oder leerer Wert ergibt IMMER
     * ein gültiges MySQL-datetime — NIE den ungültigen Rohstring (der die
     * Migration sonst mit SQLSTATE 22007 abbricht).
     */
    public function testUnparsbaresDatumErgibtGueltigesDatetimeNieRohstring(): void
    {
        $roh = 'kein datum';
        $ergebnis = Version000043Date20260824120000::konvertiereDatum($roh);
        $this->assertNotSame($roh, $ergebnis, 'Rohstring darf nicht in die datetime-Spalte gelangen');
        $this->assertMatchesRegularExpression(self::MYSQL_DATETIME, $ergebnis);

        $this->assertMatchesRegularExpression(self::MYSQL_DATETIME, Version000043Date20260824120000::konvertiereDatum(''));
    }
}
