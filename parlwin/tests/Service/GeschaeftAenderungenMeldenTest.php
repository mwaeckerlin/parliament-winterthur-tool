<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftEreignisMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\VorstossEntwurfMapper;
use OCA\ParliamentWinterthur\Service\GeschaeftDokumentService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * F150: Der Abgleich nennt im Ereignis `sync.completed` die Geschäfte, die er
 * angelegt oder geändert hat, und die Oberfläche lädt nur sie nach. Ein
 * hängiges Geschäft schreibt der Abgleich bei jedem Lauf; geändert ist es nur,
 * wenn die Quelle etwas anderes hergibt als beim letzten Mal.
 */
class GeschaeftAenderungenMeldenTest extends TestCase
{
    /** @return array<string, mixed> */
    private function quelldaten(string $status = 'In Kommission'): array
    {
        return [
            'id' => '2777407',
            'number' => '2026.15',
            'title' => 'Zweck der Immobilien im Finanzhaushalt',
            'type' => 'Schriftliche Anfrage',
            'status' => $status,
            'date' => '2026-03-02',
            'url' => 'https://parlament.winterthur.ch/_rte/information/2777407',
        ];
    }

    private function bestand(string $hash): Geschaeft
    {
        $g = new Geschaeft();
        $g->setId(2777407);
        $g->setExternId('2777407');
        $g->setNummer('2026.15');
        $g->setStatus('In Kommission');
        $g->setQuelleHash($hash);
        $g->setQuelleAktualisiertAm('2026-03-02 10:00:00');
        $g->setGeloescht(false);
        return $g;
    }

    /**
     * Der Abgleich gegen einen Testersatz des Mappers. Wie der echte setzt
     * `harmonisiereIdMitExternId` die ID auf die Kennung der Quelle.
     *
     * @param list<array<string, mixed>> $quelle
     */
    private function service(?Geschaeft $bestand, array $quelle, ?GeschaeftDokumentService $dokumente = null): GeschaeftService
    {
        $mapper = $this->createStub(GeschaeftMapper::class);
        if ($bestand === null) {
            $mapper->method('findByExternId')->willThrowException(new DoesNotExistException('neu'));
        } else {
            $mapper->method('findByExternId')->willReturn($bestand);
        }
        $mapper->method('harmonisiereIdMitExternId')->willReturnCallback(
            static function (Geschaeft $geschaeft, int $id): void {
                $geschaeft->setId($id);
            }
        );
        $mapper->method('update')->willReturnArgument(0);
        $mapper->method('insert')->willReturnArgument(0);
        $scraper = $this->createStub(ScraperService::class);
        $scraper->method('ladeGeschaefte')->willReturn($quelle);
        return new GeschaeftService(
            $mapper,
            $this->createStub(VorstossEntwurfMapper::class),
            $this->createStub(GeschaeftEreignisMapper::class),
            $scraper,
            $this->createStub(LoggerInterface::class),
            $dokumente,
        );
    }

    /**
     * Liest der Abgleich ein Dokument eines hängigen Geschäfts neu, steht das
     * Geschäft in der Liste, auch wenn die Quelle sonst dasselbe hergibt.
     */
    public function testEinGeschaeftMitNeuGelesenemDokumentStehtInDerListe(): void
    {
        $daten = $this->quelldaten() + ['dokumente' => [[
            'externId' => '501',
            'titel' => 'Antwort Stadtrat',
            'url' => 'https://parlament.winterthur.ch/_doc/501',
            'kategorie' => 'Antwort Stadtrat',
            'datum' => '2026-03-10',
        ]]];
        $bestand = $this->bestand('hash-von-gestern');
        $nichtsNeu = $this->createStub(GeschaeftDokumentService::class);
        $nichtsNeu->method('aktualisiere')->willReturn(0);
        $this->service($bestand, [$daten], $nichtsNeu)->synchronisieren();

        $einesNeu = $this->createStub(GeschaeftDokumentService::class);
        $einesNeu->method('aktualisiere')->willReturn(1);
        $zweiter = $this->service($bestand, [$daten], $einesNeu);
        $zweiter->synchronisieren();

        self::assertSame([2777407], $zweiter->zuletztGeaenderteIds());
    }

    /**
     * Setzt der Abgleich die ID eines Geschäfts auf die Kennung der Quelle,
     * stehen die alte und die neue ID in der Liste: Die Oberfläche hält das
     * Geschäft unter der alten.
     */
    public function testEinGeschaeftMitNeuerIdStehtMitBeidenIdsInDerListe(): void
    {
        $bestand = $this->bestand('hash-von-gestern');
        $this->service($bestand, [$this->quelldaten()])->synchronisieren();
        $bestand->setId(99);

        $zweiter = $this->service($bestand, [$this->quelldaten()]);
        $zweiter->synchronisieren();

        self::assertSame(2777407, (int) $bestand->getId());
        self::assertSame([99, 2777407], $zweiter->zuletztGeaenderteIds());
    }

    public function testEinGeaendertesGeschaeftStehtInDerListe(): void
    {
        $dienst = $this->service($this->bestand('hash-von-gestern'), [$this->quelldaten('Erledigt')]);
        $dienst->synchronisieren();

        self::assertSame([2777407], $dienst->zuletztGeaenderteIds());
    }

    public function testEinNeuesGeschaeftStehtInDerListe(): void
    {
        $dienst = $this->service(null, [$this->quelldaten()]);
        $dienst->synchronisieren();

        self::assertSame([2777407], $dienst->zuletztGeaenderteIds());
    }

    public function testEinUnveraendertesHaengigesGeschaeftFehlt(): void
    {
        // Der erste Lauf schreibt den Hash des aktuellen Stands ins Geschäft.
        $bestand = $this->bestand('hash-von-gestern');
        $this->service($bestand, [$this->quelldaten()])->synchronisieren();

        $zweiter = $this->service($bestand, [$this->quelldaten()]);
        $zweiter->synchronisieren();

        self::assertSame([], $zweiter->zuletztGeaenderteIds());
    }

    /**
     * Ein Geschäft, das als gelöscht markiert war und wieder in der Quelle
     * steht, erscheint wieder in der Liste. Die Oberfläche lädt es nach, auch
     * wenn die Quelle dasselbe hergibt wie vor der Löschung.
     */
    public function testEinWiederAuftauchendesGeschaeftStehtInDerListe(): void
    {
        $bestand = $this->bestand('hash-von-gestern');
        $this->service($bestand, [$this->quelldaten()])->synchronisieren();
        $bestand->setGeloescht(true);

        $zweiter = $this->service($bestand, [$this->quelldaten()]);
        $zweiter->synchronisieren();

        self::assertFalse((bool) $bestand->getGeloescht(), 'das Geschäft ist weiter als gelöscht markiert');
        self::assertSame([2777407], $zweiter->zuletztGeaenderteIds());
    }

    public function testDasEreignisNenntDieGeaendertenUndZugewiesenenGeschaefteEinmal(): void
    {
        self::assertSame(
            [3, 7, 9],
            RealtimePublisherService::geaenderteGeschaefte(['neu' => 1, 'aktualisiert' => 2, 'geloescht' => 0], [3, 7], [7, 9]),
        );
        self::assertSame([], RealtimePublisherService::geaenderteGeschaefte(null, [], []));
    }

    public function testNachEinerLoeschungLaedtDieOberflaecheAlles(): void
    {
        self::assertNull(
            RealtimePublisherService::geaenderteGeschaefte(['neu' => 0, 'aktualisiert' => 0, 'geloescht' => 2], [3], []),
            'gelöschte Geschäfte nennt der Abgleich nicht mit Namen',
        );
    }
}
