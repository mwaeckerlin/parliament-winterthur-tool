<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftDokumentMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftZustaendigkeitMapper;
use OCA\ParliamentWinterthur\Db\FraktionsrolleMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCA\ParliamentWinterthur\Db\NotizRevisionMapper;
use OCA\ParliamentWinterthur\Service\DokumentInhaltParser;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\GeschaeftDokumentService;
use OCA\ParliamentWinterthur\Tests\Fakes\AufzeichnendeDatenbank;
use OCA\ParliamentWinterthur\Tests\Fakes\GrosserText;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\ITempManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Die Übersicht der Geschäfte (F121, F149): Sie liest die amtlichen Dokumente
 * ohne ihren Text, und sie liest die Beschlüsse und Zuständigkeiten mit einer
 * Abfrage je Block statt mit drei Abfragen je Geschäft. Lädt sie jedes
 * Dokument mit seinem ganzen Text, belegt der erste Block von 1000 Geschäften
 * 362'176KB PHP-Speicher.
 *
 * Die Datenbank ist {@see AufzeichnendeDatenbank}: Sie wertet die Abfragen der
 * echten Mapper aus und erzeugt einen langen Text erst, wenn eine Abfrage seine
 * Spalte liest.
 */
class GeschaeftListeAbfragenTest extends TestCase
{
    private const DOKUMENTE = 'pw_geschaeft_dokumente';

    /**
     * Gemessen mit 1000 Geschäften zu je einem Dokument mit 100'000 Zeichen
     * Markdown und 100'000 Zeichen Volltext: ohne den Text 7'471KB, mit dem
     * Text 207'627KB. Die Schwelle lässt der Liste ohne
     * Text gut das Doppelte und liegt weit unter dem Wert mit Text.
     */
    private const SPEICHER_SCHWELLE = 16 * 1024 * 1024;

    private AufzeichnendeDatenbank $db;

    /** @return Geschaeft[] */
    private function bestand(int $anzahl, int $textLaenge = 2000): array
    {
        $this->db = new AufzeichnendeDatenbank();
        $geschaefte = [];
        for ($i = 1; $i <= $anzahl; $i++) {
            $geschaeft = new Geschaeft();
            $geschaeft->setId($i);
            $geschaeft->setNummer('2026.' . $i);
            $geschaeft->setTitel('Geschäft ' . $i);
            $geschaefte[] = $geschaeft;
            $this->db->tabellen[self::DOKUMENTE][] = [
                'id' => 10000 + $i,
                'geschaeft_id' => $i,
                'extern_id' => (string) (6800000 + $i),
                'titel' => $i . 'V',
                'kategorie' => 'Vorstoss',
                'datum' => '2026-03-02',
                'url' => 'https://parlament.winterthur.ch/_doc/' . (6800000 + $i),
                'struktur' => '[{"art":"titel","seite":1,"text":"Anfrage"}]',
                'markdown' => new GrosserText($textLaenge),
                'volltext' => new GrosserText($textLaenge),
                'seiten' => 3,
                'quelle_hash' => 'abc',
                'gelesen_am' => '2026-03-03 10:00:00',
                'fehler' => '',
            ];
        }
        return $geschaefte;
    }

    private function dienst(): FraktionsarbeitService
    {
        $dokumente = new GeschaeftDokumentService(
            new GeschaeftDokumentMapper($this->db),
            $this->createStub(DokumentInhaltParser::class),
            $this->createStub(IClientService::class),
            $this->createStub(ITempManager::class),
            $this->createStub(LoggerInterface::class),
        );
        return new FraktionsarbeitService(
            $this->createStub(GeschaeftMapper::class),
            new GeschaeftAktionMapper($this->db),
            new GeschaeftZustaendigkeitMapper($this->db),
            $this->createStub(FraktionsrolleMapper::class),
            $this->createStub(MitgliedMapper::class),
            $this->createStub(KommissionMapper::class),
            $this->createStub(GeschaeftEreignisMapper::class),
            $this->createStub(IConfig::class),
            $this->createStub(IUserSession::class),
            $this->createStub(IGroupManager::class),
            $this->createStub(NotizRevisionMapper::class),
            $dokumente,
        );
    }

    public function testDieListeLiestMarkdownUndAbschnitteNicht(): void
    {
        $geschaefte = $this->bestand(3);
        $this->dienst()->angereicherteGeschaefte($geschaefte);

        $abfragen = $this->db->spaltenAuf(self::DOKUMENTE);
        self::assertNotSame([], $abfragen, 'die Liste liest die Köpfe der Dokumente');
        foreach ($abfragen as $spalten) {
            foreach (['*', 'markdown', 'struktur', 'volltext'] as $verboten) {
                self::assertNotContains(
                    $verboten,
                    $spalten,
                    'die Übersicht lädt den Text eines Dokuments erst beim Aufklappen: ' . implode(', ', $spalten),
                );
            }
        }
    }

    public function testDerKopfTraegtJedesFeldWieZuvorUndHatInhaltAusDerDatenbank(): void
    {
        $geschaefte = $this->bestand(3);
        // Ein Dokument ohne gelesenen Inhalt und eines, dessen Inhalt leer ist.
        $this->db->tabellen[self::DOKUMENTE][1]['markdown'] = null;
        $this->db->tabellen[self::DOKUMENTE][1]['fehler'] = 'Zu gross';
        $this->db->tabellen[self::DOKUMENTE][2]['markdown'] = '';

        $liste = $this->dienst()->angereicherteGeschaefte($geschaefte);

        self::assertSame([
            'id' => 10001,
            'geschaeftId' => 1,
            'externId' => '6800001',
            'titel' => '1V',
            'kategorie' => 'Vorstoss',
            'datum' => '2026-03-02',
            'url' => 'https://parlament.winterthur.ch/_doc/6800001',
            'seiten' => 3,
            'gelesenAm' => '2026-03-03 10:00:00',
            'fehler' => '',
            'hatInhalt' => true,
        ], $liste[0]['amtlicheDokumente'][0]);
        self::assertFalse($liste[1]['amtlicheDokumente'][0]['hatInhalt']);
        self::assertSame('Zu gross', $liste[1]['amtlicheDokumente'][0]['fehler']);
        self::assertFalse($liste[2]['amtlicheDokumente'][0]['hatInhalt']);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function blockgroessen(): array
    {
        return [
            'ein Geschäft' => [1],
            'ein voller Block' => [500],
            'ein Geschäft über dem Block' => [501],
            'zweieinhalb Blöcke' => [1200],
        ];
    }

    /**
     * Je Block von 500 Geschäften liest die Liste drei Tabellen mit je einer
     * Abfrage: die Köpfe der Dokumente, die gültigen Beschlüsse und die
     * aktiven Zuständigkeiten.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('blockgroessen')]
    public function testDieZahlDerAbfragenHaengtNurAnDenBloecken(int $anzahl): void
    {
        $geschaefte = $this->bestand($anzahl, 10);
        $this->dienst()->angereicherteGeschaefte($geschaefte);

        self::assertCount(
            3 * (int) ceil($anzahl / 500),
            $this->db->abfragen,
            'Abfragen für ' . $anzahl . ' Geschäfte',
        );
    }

    public function testBeschlussUndZustaendigkeitenStehenWieBeiDerAbfrageJeGeschaeft(): void
    {
        $geschaefte = $this->bestand(2, 10);
        $this->db->tabellen['pw_geschaeft_aktionen'] = [
            ['id' => 1, 'geschaeft_id' => 1, 'objekt_typ' => 'geschaeft', 'aktion_typ' => 'beschluss', 'aktion_code' => 'ablehnen', 'titel' => 'Ablehnen', 'text' => '', 'entscheid_gueltig' => true, 'autor_uid' => 'a', 'autor_name' => 'A', 'erstellt_am' => '2026-03-01 10:00:00', 'geloescht' => false],
            ['id' => 2, 'geschaeft_id' => 1, 'objekt_typ' => 'geschaeft', 'aktion_typ' => 'beschluss', 'aktion_code' => 'unterstuetzen', 'titel' => 'Zustimmen', 'text' => '', 'entscheid_gueltig' => true, 'autor_uid' => 'a', 'autor_name' => 'A', 'erstellt_am' => '2026-03-05 10:00:00', 'geloescht' => false],
            ['id' => 3, 'geschaeft_id' => 1, 'objekt_typ' => 'geschaeft', 'aktion_typ' => 'beschluss', 'aktion_code' => 'stimmfreigabe', 'titel' => 'Stimmfreigabe', 'text' => '', 'entscheid_gueltig' => false, 'autor_uid' => 'a', 'autor_name' => 'A', 'erstellt_am' => '2026-03-09 10:00:00', 'geloescht' => false],
            ['id' => 4, 'geschaeft_id' => 1, 'objekt_typ' => 'geschaeft', 'aktion_typ' => 'votum', 'aktion_code' => '', 'titel' => 'Votum', 'text' => '', 'entscheid_gueltig' => true, 'autor_uid' => 'a', 'autor_name' => 'A', 'erstellt_am' => '2026-03-10 10:00:00', 'geloescht' => false],
        ];
        $this->db->tabellen['pw_geschaeft_zustaendigkeiten'] = [
            ['id' => 1, 'geschaeft_id' => 1, 'person_key' => 'zoller', 'mitglied_extern_id' => '1', 'person_name' => 'Zoller Urs', 'ist_haupt' => false, 'aktiv' => true, 'erstellt_am' => '', 'aktualisiert_am' => ''],
            ['id' => 2, 'geschaeft_id' => 1, 'person_key' => 'muster', 'mitglied_extern_id' => '2', 'person_name' => 'Muster Anna', 'ist_haupt' => true, 'aktiv' => true, 'erstellt_am' => '', 'aktualisiert_am' => ''],
            ['id' => 3, 'geschaeft_id' => 1, 'person_key' => 'alt', 'mitglied_extern_id' => '3', 'person_name' => 'Alt Beat', 'ist_haupt' => false, 'aktiv' => false, 'erstellt_am' => '', 'aktualisiert_am' => ''],
            ['id' => 4, 'geschaeft_id' => 1, 'person_key' => 'brun', 'mitglied_extern_id' => '4', 'person_name' => 'Brun Eva', 'ist_haupt' => false, 'aktiv' => true, 'erstellt_am' => '', 'aktualisiert_am' => ''],
        ];

        $liste = $this->dienst()->angereicherteGeschaefte($geschaefte);

        self::assertSame('unterstuetzen', $liste[0]['letzterBeschluss']['aktionCode']);
        self::assertSame(2, $liste[0]['letzterBeschluss']['id']);
        self::assertSame('Muster Anna', $liste[0]['hauptZustaendigePerson']);
        self::assertSame(
            ['Muster Anna', 'Brun Eva', 'Zoller Urs'],
            array_column($liste[0]['zustaendigkeiten'], 'personName'),
        );
        self::assertNull($liste[1]['letzterBeschluss']);
        self::assertSame('', $liste[1]['hauptZustaendigePerson']);
        self::assertSame([], $liste[1]['zustaendigkeiten']);
    }

    public function testDerSpeicherFuerTausendGeschaefteBleibtUnterDerSchwelle(): void
    {
        $geschaefte = $this->bestand(1000, 100000);
        $dienst = $this->dienst();
        gc_collect_cycles();
        $vorher = memory_get_usage();
        memory_reset_peak_usage();

        $liste = $dienst->angereicherteGeschaefte($geschaefte);

        $spitze = memory_get_peak_usage() - $vorher;
        self::assertCount(1000, $liste);
        fwrite(STDERR, sprintf("\nSpeicher der Liste für 1000 Geschäfte: %d KB\n", intdiv($spitze, 1024)));
        self::assertLessThan(
            self::SPEICHER_SCHWELLE,
            $spitze,
            sprintf('Spitze %d KB für 1000 Geschäfte', intdiv($spitze, 1024)),
        );
    }
}
