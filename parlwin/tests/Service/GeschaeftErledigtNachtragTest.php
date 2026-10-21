<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\VorstossEntwurfMapper;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Ein erledigtes Geschäft bekommt nach, was die Quelle über es hergibt.
 *
 * Der Abgleich übersprang ein Geschäft, sobald sein gespeicherter Status als
 * abgeschlossen galt. Es wurde damit nie wieder geschrieben — auch dann nicht,
 * wenn die Anwendung inzwischen ein Feld mehr aus der Quelle liest. Gemessen am
 * 23.09.2026 in der laufenden Instanz: 1197 von 1276 Geschäften ohne Einreicher,
 * darunter 2026.15 «Zweck der Immobilien im Finanzhaushalt», das auf seiner
 * Seite «Wäckerlin Marc (Erstunterzeichner/-in)» führt. Der Filter «Einreicher»
 * zeigte es deshalb nicht.
 */
class GeschaeftErledigtNachtragTest extends TestCase
{
    /** @param array<string, mixed> $quelldaten */
    private function service(Geschaeft $bestand, array $quelldaten, ?Geschaeft &$geschrieben): GeschaeftService
    {
        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('findByExternId')->willReturn($bestand);
        $mapper->method('findAll')->willReturn([$bestand]);
        $mapper->method('update')->willReturnCallback(
            function (Geschaeft $g) use (&$geschrieben): Geschaeft {
                $geschrieben = $g;
                return $g;
            }
        );

        $scraper = $this->createStub(ScraperService::class);
        $scraper->method('ladeGeschaefte')->willReturn([$quelldaten]);

        return new GeschaeftService(
            $mapper,
            $this->createStub(VorstossEntwurfMapper::class),
            $this->createStub(GeschaeftEreignisMapper::class),
            $scraper,
            $this->createStub(LoggerInterface::class),
        );
    }

    /** @return array<string, mixed> */
    private function quelldaten(array $einreicher): array
    {
        return [
            'id' => '2777407',
            'number' => '2026.15',
            'title' => 'Zweck der Immobilien im Finanzhaushalt',
            'type' => 'Schriftliche Anfrage',
            'status' => 'Erledigt',
            'date' => '2026-03-02',
            'url' => 'https://parlament.winterthur.ch/_rte/information/2777407',
            'einreicher' => $einreicher,
        ];
    }

    private function bestand(string $einreicher, string $hash): Geschaeft
    {
        $g = new Geschaeft();
        $g->setId(1);
        $g->setExternId('2777407');
        $g->setNummer('2026.15');
        $g->setTitel('Zweck der Immobilien im Finanzhaushalt');
        $g->setTyp('Schriftliche Anfrage');
        $g->setStatus('Erledigt');
        $g->setDatum('2026-03-02');
        $g->setUrl('https://parlament.winterthur.ch/_rte/information/2777407');
        $g->setEinreicher($einreicher);
        $g->setQuelleHash($hash);
        $g->setGeloescht(false);
        return $g;
    }

    public function testErledigtesGeschaeftBekommtSeineEinreicherNachgetragen(): void
    {
        $einreicher = [['name' => 'Wäckerlin Marc', 'rolle' => 'Erstunterzeichner', 'externId' => '280925']];
        $bestand = $this->bestand('[]', 'hash-aus-der-zeit-ohne-einreicher');
        $geschrieben = null;

        $this->service($bestand, $this->quelldaten($einreicher), $geschrieben)->synchronisieren();

        self::assertNotNull($geschrieben, 'das erledigte Geschäft wurde gar nicht geschrieben');
        self::assertSame(
            $einreicher,
            json_decode($geschrieben->getEinreicher(), true),
            'die Einreicher der Quelle stehen nicht am Geschäft',
        );
    }

    public function testUnveraendertesErledigtesGeschaeftWirdNichtGeschrieben(): void
    {
        $einreicher = [['name' => 'Wäckerlin Marc', 'rolle' => 'Erstunterzeichner', 'externId' => '280925']];
        $daten = $this->quelldaten($einreicher);

        // Erster Lauf: das Geschäft wird nachgetragen und trägt danach den Hash
        // des aktuellen Quellstands.
        $bestand = $this->bestand('[]', 'hash-aus-der-zeit-ohne-einreicher');
        $geschrieben = null;
        $this->service($bestand, $daten, $geschrieben)->synchronisieren();
        self::assertNotNull($geschrieben);
        $hash = $geschrieben->getQuelleHash();
        self::assertNotSame('', $hash);

        // Zweiter Lauf auf demselben Stand: nichts mehr zu tun.
        $zweiter = $this->bestand(json_encode($einreicher, JSON_UNESCAPED_UNICODE), $hash);
        $nochmals = null;
        $this->service($zweiter, $daten, $nochmals)->synchronisieren();
        self::assertNull($nochmals, 'ein unverändertes erledigtes Geschäft wird erneut geschrieben');
    }
}
