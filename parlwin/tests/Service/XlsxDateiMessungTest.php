<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\BudgetAntraegeTabelle;
use OCA\ParliamentWinterthur\Service\XlsxDatei;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * WERKZEUG, kein Regressionstest (Gruppe «messung», `npm run test:messung`).
 *
 * Gibt eine erzeugte Tabellenkalkulationsdatei (F138) base64-kodiert aus,
 * damit sie sich mit einem echten Leser öffnen lässt. Die Tests der Suite
 * prüfen das XML; ob ein Tabellenprogramm die Datei annimmt, zeigt erst ein
 * Programm, das sie liest.
 */
#[Group('messung')]
final class XlsxDateiMessungTest extends TestCase
{
    public function testEineDateiZumNachlesen(): void
    {
        $daten = [
            'jahr' => 2027,
            'kommission' => 'Sachkommission Bau und Betriebe',
            'eintraege' => [
                [
                    'departement' => 'Finanzen',
                    'produktegruppe' => 'Immobilien',
                    'buchseite' => 243,
                    'antrag' => [
                        'bereich' => 'globalbudget',
                        'betragDelta' => -250000,
                        'stellenDelta' => -1.5,
                        'prozentDelta' => 0,
                        'neuerWert' => '',
                        'antragsteller' => 'Unsere Fraktion',
                        'haltung' => 'einreichen',
                        'begruendung' => "Die Kürzung ist **nötig** & richtig.\n\n- erstens\n- zweitens",
                        'zielAenderungen' => [
                            ['zielNummer' => 1, 'unterTitel' => 'Minimale Nettorendite', 'messgroesse' => 'Wohnliegenschaften (in %)', 'soll' => '1.00', 'neuerWert' => '2'],
                        ],
                        'aufteilung' => [['ebene' => 'produkt', 'ref' => '3', 'betrag' => -250000]],
                    ],
                ],
                [
                    'departement' => 'Bau',
                    'produktegruppe' => 'Schulhaus Neuwiesen · Kredit 3000.1 Ausführungskredit',
                    'buchseite' => 0,
                    'antrag' => [
                        'bereich' => 'bewilligungsorgan',
                        'betragDelta' => 0,
                        'stellenDelta' => 0,
                        'prozentDelta' => 0,
                        'neuerWert' => '#',
                        'antragsteller' => 'Unsere Fraktion',
                        'haltung' => 'unterstuetzen',
                        'begruendung' => 'Der Kredit fällt in die Kompetenz des Parlaments.',
                        'zielAenderungen' => [],
                        'aufteilung' => [],
                    ],
                ],
            ],
        ];
        $inhalt = XlsxDatei::erzeugen(
            BudgetAntraegeTabelle::name($daten),
            BudgetAntraegeTabelle::zeilen($daten),
            BudgetAntraegeTabelle::BREITEN,
        );
        fwrite(STDERR, "[xlsx-base64-anfang]\n" . base64_encode($inhalt) . "\n[xlsx-base64-ende]\n");
        self::assertNotSame('', $inhalt);
    }
}
