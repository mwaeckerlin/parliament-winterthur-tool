<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Controller\GeschaeftController;
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Service\FraktionsarbeitService;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GeschaeftControllerTest extends TestCase
{
    public function testIndexBlendetErledigteStandardmaessigAus(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'limit' => '50',
                    'offset' => '5',
                    default => $default,
                };
            });

        $service = $this->createMock(GeschaeftService::class);
        $service->expects($this->once())
            ->method('alle')
            ->with(50, 5, false)
            ->willReturn([]);

        $fraktionsarbeit = $this->createMock(FraktionsarbeitService::class);
        $fraktionsarbeit->expects($this->once())
            ->method('angereicherteGeschaefte')
            ->with([], '', null)
            ->willReturn([]);

        $controller = new GeschaeftController(
            $request,
            $service,
            $fraktionsarbeit,
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(GeschaeftMapper::class),
        );

        $response = $controller->index();
        $this->assertSame([], $response->getData());
    }

    public function testIndexZeigtErledigteBeiShowErledigtFlag(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'show_erledigt' => '1',
                    default => $default,
                };
            });

        $geschaefte = [['id' => 1]];
        $service = $this->createMock(GeschaeftService::class);
        $service->expects($this->once())
            ->method('alle')
            ->with(100, 0, true)
            ->willReturn($geschaefte);

        $fraktionsarbeit = $this->createMock(FraktionsarbeitService::class);
        $fraktionsarbeit->expects($this->once())
            ->method('angereicherteGeschaefte')
            ->with($geschaefte, '', null)
            ->willReturn($geschaefte);

        $controller = new GeschaeftController(
            $request,
            $service,
            $fraktionsarbeit,
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(GeschaeftMapper::class),
        );

        $response = $controller->index();
        $this->assertSame($geschaefte, $response->getData());
    }

    /**
     * Regression: Der status-Parameter muss die Liste serverseitig auf genau
     * die passenden Geschäfte einschränken — sonst zeigt ein gefilterter View
     * fälschlicherweise alle oder gar keine Geschäfte an.
     */
    public function testIndexFiltertNachStatus(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'show_erledigt' => '1',
                    'status' => 'Pendent',
                    default => $default,
                };
            });

        $pendent = new Geschaeft();
        $pendent->setStatus('Pendent');
        $pendent->setNummer('2024.1');
        $erledigt = new Geschaeft();
        $erledigt->setStatus('Erledigt');
        $erledigt->setNummer('2024.2');

        $service = $this->createStub(GeschaeftService::class);
        $service->method('alle')->willReturn([$pendent, $erledigt]);

        // angereicherteGeschaefte echot die (gefilterte) Eingabe zurück, damit
        // wir prüfen können, welche Geschäfte der Controller durchgelassen hat.
        $fraktionsarbeit = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeit->method('angereicherteGeschaefte')
            ->willReturnCallback(static fn(array $g): array => $g);

        $controller = new GeschaeftController(
            $request,
            $service,
            $fraktionsarbeit,
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(GeschaeftMapper::class),
        );

        $daten = $controller->index()->getData();
        $this->assertCount(1, $daten);
        $this->assertSame('Pendent', $daten[0]->getStatus());
        $this->assertSame('2024.1', $daten[0]->getNummer());
    }

    /**
     * Regression: Selbst angelegte Geschäfte müssen eine explizite ID erhalten,
     * weil die Tabelle nicht überall AUTO_INCREMENT ist (sonst SQL-Fehler
     * «Field 'id' doesn't have a default value» und HTTP 500 beim Anlegen).
     */
    public function testCreateVergibtExpliziteId(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'titel' => 'Mein eigenes Geschäft',
                    default => $default,
                };
            });

        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('naechsteId')->willReturn(42);
        $gespeichert = null;
        $mapper->method('insert')->willReturnCallback(static function (Geschaeft $g) use (&$gespeichert): Geschaeft {
            $gespeichert = $g;
            return $g;
        });

        $fraktionsarbeit = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeit->method('angereichertesGeschaeft')
            ->willReturnCallback(static fn(int $id): array => ['id' => $id, 'status' => 'Pendent']);

        $controller = new GeschaeftController(
            $request,
            $this->createStub(GeschaeftService::class),
            $fraktionsarbeit,
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $mapper,
        );

        $response = $controller->create();
        $this->assertSame(201, $response->getStatus());
        $this->assertNotNull($gespeichert);
        $this->assertSame(42, $gespeichert->getId());
        $this->assertSame('Pendent', $response->getData()['status']);
    }

    /**
     * Feature: Beim Anlegen eines eigenen Geschäfts werden Beschreibungstext und
     * Kommission mitgegeben — sie gehören zur Erfassung und dürfen nicht erst
     * über einen zweiten Schritt setzbar sein.
     */
    public function testCreateUebernimmtInhaltUndKommission(): void
    {
        $parameter = [
            'titel' => 'Mein eigenes Geschäft',
            'inhalt' => '<p>Worum es geht</p>',
            'kommission' => 'Aufsichtskommission',
        ];
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null) use ($parameter): mixed {
                return array_key_exists($key, $parameter) ? $parameter[$key] : $default;
            });

        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('naechsteId')->willReturn(42);
        $gespeichert = null;
        $mapper->method('insert')->willReturnCallback(static function (Geschaeft $g) use (&$gespeichert): Geschaeft {
            $gespeichert = $g;
            return $g;
        });

        $fraktionsarbeit = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeit->method('angereichertesGeschaeft')
            ->willReturnCallback(static fn(int $id): array => ['id' => $id]);

        $controller = new GeschaeftController(
            $request,
            $this->createStub(GeschaeftService::class),
            $fraktionsarbeit,
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $mapper,
        );

        $controller->create();

        $this->assertNotNull($gespeichert);
        $this->assertSame('<p>Worum es geht</p>', $gespeichert->getInhalt());
        $this->assertSame('Aufsichtskommission', $gespeichert->getKommission());
    }

    /**
     * Feature: Beschreibungstext und Kommission sind auch nachträglich änderbar.
     */
    public function testStammdatenUebernehmenInhaltUndKommission(): void
    {
        $aktualisiert = null;
        $controller = $this->controllerFuerGeschaeft(
            $this->eigenesGeschaeft(),
            ['inhalt' => '<p>Neu beschrieben</p>', 'kommission' => 'Sachkommission Stadtbau'],
            $aktualisiert,
        );

        $response = $controller->updateStammdaten(7);

        $this->assertSame(200, $response->getStatus());
        $this->assertNotNull($aktualisiert);
        $this->assertSame('<p>Neu beschrieben</p>', $aktualisiert->getInhalt());
        $this->assertSame('Sachkommission Stadtbau', $aktualisiert->getKommission());
    }

    /**
     * Keine Kommission ist ein gültiger Zustand: eine vorhandene Zuordnung lässt
     * sich wieder entfernen.
     */
    public function testKommissionLaesstSichWiederEntfernen(): void
    {
        $geschaeft = $this->eigenesGeschaeft();
        $geschaeft->setKommission('Aufsichtskommission');
        $aktualisiert = null;
        $controller = $this->controllerFuerGeschaeft($geschaeft, ['kommission' => ''], $aktualisiert);

        $controller->updateStammdaten(7);

        $this->assertNotNull($aktualisiert);
        $this->assertSame('', $aktualisiert->getKommission());
    }

    /**
     * Baut einen Controller, der ein bestimmtes Geschäft findet, und protokolliert
     * Aktualisierungen und Löschungen. Für die Stammdaten- und Löschprüfungen.
     */
    private function controllerFuerGeschaeft(
        Geschaeft $geschaeft,
        array $parameter,
        ?Geschaeft &$aktualisiert = null,
        ?Geschaeft &$geloescht = null,
    ): GeschaeftController {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null) use ($parameter): mixed {
                return array_key_exists($key, $parameter) ? $parameter[$key] : $default;
            });

        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('find')->willReturn($geschaeft);
        $mapper->method('update')->willReturnCallback(static function (Geschaeft $g) use (&$aktualisiert): Geschaeft {
            $aktualisiert = $g;
            return $g;
        });
        $mapper->method('delete')->willReturnCallback(static function (Geschaeft $g) use (&$geloescht): Geschaeft {
            $geloescht = $g;
            return $g;
        });

        $fraktionsarbeit = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeit->method('angereichertesGeschaeft')
            ->willReturnCallback(static fn(int $id): array => ['id' => $id]);

        return new GeschaeftController(
            $request,
            $this->createStub(GeschaeftService::class),
            $fraktionsarbeit,
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $mapper,
        );
    }

    private function eigenesGeschaeft(): Geschaeft
    {
        $g = new Geschaeft();
        $g->setId(7);
        $g->setExternId('eigen:abc123');
        $g->setTitel('Alt');
        return $g;
    }

    private function parlamentsGeschaeft(): Geschaeft
    {
        $g = new Geschaeft();
        $g->setId(8);
        $g->setExternId('2026.1');
        $g->setTitel('Von der Webseite');
        return $g;
    }

    /**
     * Feature: Ein selbst angelegtes Geschäft ist in allen Stammdaten bearbeitbar.
     */
    public function testStammdatenEinesEigenenGeschaeftsWerdenGespeichert(): void
    {
        $aktualisiert = null;
        $controller = $this->controllerFuerGeschaeft(
            $this->eigenesGeschaeft(),
            ['titel' => 'Neuer Titel', 'typ' => 'Kommissionsgeschäft', 'status' => 'Pendent', 'datum' => '2026-03-04'],
            $aktualisiert,
        );

        $response = $controller->updateStammdaten(7);

        $this->assertSame(200, $response->getStatus());
        $this->assertNotNull($aktualisiert);
        $this->assertSame('Neuer Titel', $aktualisiert->getTitel());
        $this->assertSame('Kommissionsgeschäft', $aktualisiert->getTyp());
        $this->assertSame('Pendent', $aktualisiert->getStatus());
        $this->assertSame('2026-03-04', $aktualisiert->getDatum());
    }

    /**
     * Ein Geschäft von der Parlamentswebseite bleibt schreibgeschützt: seine
     * Angaben stammen aus der Quelle und würden beim Abgleich überschrieben.
     */
    public function testStammdatenEinesParlamentsgeschaeftsWerdenAbgelehnt(): void
    {
        $aktualisiert = null;
        $controller = $this->controllerFuerGeschaeft(
            $this->parlamentsGeschaeft(),
            ['titel' => 'Darf nicht gehen'],
            $aktualisiert,
        );

        $response = $controller->updateStammdaten(8);

        $this->assertSame(403, $response->getStatus());
        $this->assertNull($aktualisiert, 'Ein Parlamentsgeschäft wurde trotz Schreibschutz verändert');
    }

    /** Ein unbrauchbares Datum wird abgewiesen statt still übernommen. */
    public function testStammdatenLehnenUngueltigesDatumAb(): void
    {
        $aktualisiert = null;
        $controller = $this->controllerFuerGeschaeft(
            $this->eigenesGeschaeft(),
            ['datum' => '04.03.2026'],
            $aktualisiert,
        );

        $response = $controller->updateStammdaten(7);

        $this->assertSame(400, $response->getStatus());
        $this->assertNull($aktualisiert);
        $this->assertStringContainsString('JJJJ-MM-TT', (string) $response->getData()['fehler']);
    }

    /**
     * Keine Änderung ohne Spur: eine geänderte Angabe landet mit Vorher- und
     * Nachher-Wert in der Aktionszeitleiste.
     */
    public function testStammdatenAenderungWirdProtokolliert(): void
    {
        $geschaeft = $this->eigenesGeschaeft();

        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null): mixed {
                return $key === 'titel' ? 'Neuer Titel' : $default;
            });

        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('find')->willReturn($geschaeft);
        $mapper->method('update')->willReturnCallback(static fn(Geschaeft $g): Geschaeft => $g);

        $fraktionsarbeit = $this->createMock(FraktionsarbeitService::class);
        $fraktionsarbeit->method('angereichertesGeschaeft')->willReturn(['id' => 7]);
        $fraktionsarbeit->expects($this->once())
            ->method('protokolliereAenderung')
            ->with(7, ['Titel' => ['Alt', 'Neuer Titel']]);

        $controller = new GeschaeftController(
            $request,
            $this->createStub(GeschaeftService::class),
            $fraktionsarbeit,
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $mapper,
        );

        $this->assertSame(200, $controller->updateStammdaten(7)->getStatus());
    }

    /** Feature: Ein selbst angelegtes Geschäft lässt sich wieder löschen. */
    public function testEigenesGeschaeftWirdGeloescht(): void
    {
        $aktualisiert = null;
        $geloescht = null;
        $controller = $this->controllerFuerGeschaeft($this->eigenesGeschaeft(), [], $aktualisiert, $geloescht);

        $response = $controller->destroy(7);

        $this->assertSame(200, $response->getStatus());
        $this->assertNotNull($geloescht, 'Das eigene Geschäft wurde nicht gelöscht');
        $this->assertSame(7, $geloescht->getId());
    }

    /** Ein Geschäft von der Parlamentswebseite darf nicht gelöscht werden. */
    public function testParlamentsgeschaeftWirdNichtGeloescht(): void
    {
        $aktualisiert = null;
        $geloescht = null;
        $controller = $this->controllerFuerGeschaeft($this->parlamentsGeschaeft(), [], $aktualisiert, $geloescht);

        $response = $controller->destroy(8);

        $this->assertSame(403, $response->getStatus());
        $this->assertNull($geloescht, 'Ein Parlamentsgeschäft wurde trotz Löschschutz entfernt');
    }

    /**
     * Feature: Priorität filtert die Liste serverseitig. Nicht gesetzte
     * Priorität zählt als «mittel», muss also beim Filter «mittel» erscheinen.
     */
    public function testIndexFiltertNachPrioritaet(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'show_erledigt' => '1',
                    'prioritaet' => 'mittel',
                    default => $default,
                };
            });

        $hoch = new Geschaeft();
        $hoch->setPrioritaet('hoch');
        $hoch->setNummer('2024.1');
        $ohne = new Geschaeft(); // prioritaet default '' → gilt als «mittel»
        $ohne->setNummer('2024.2');

        $service = $this->createStub(GeschaeftService::class);
        $service->method('alle')->willReturn([$hoch, $ohne]);

        $fraktionsarbeit = $this->createStub(FraktionsarbeitService::class);
        $fraktionsarbeit->method('angereicherteGeschaefte')
            ->willReturnCallback(static fn(array $g): array => $g);

        $controller = new GeschaeftController(
            $request,
            $service,
            $fraktionsarbeit,
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(GeschaeftMapper::class),
        );

        $daten = $controller->index()->getData();
        $this->assertCount(1, $daten);
        $this->assertSame('2024.2', $daten[0]->getNummer());
    }

    /**
     * Feature: Priorität setzen speichert genau das erlaubte Feld und meldet
     * die Änderung per Realtime.
     */
    public function testSetPrioritaetSpeichertUndPublished(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'prioritaet' => 'hoch',
                    default => $default,
                };
            });

        $service = $this->createMock(GeschaeftService::class);
        $service->expects($this->once())
            ->method('aktualisiereInterneFelder')
            ->with(5, ['prioritaet' => 'hoch'])
            ->willReturn(new Geschaeft());

        $realtime = $this->createMock(RealtimePublisherService::class);
        $realtime->expects($this->once())
            ->method('publish')
            ->with('geschaefte.updated', ['id' => 5, 'grund' => 'prioritaet']);

        $controller = new GeschaeftController(
            $request,
            $service,
            $this->createStub(FraktionsarbeitService::class),
            $realtime,
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(GeschaeftMapper::class),
        );

        $response = $controller->setPrioritaet(5);
        $this->assertSame(['prioritaet' => 'hoch'], $response->getData());
    }

    /**
     * Feature: eine unbekannte Priorität wird abgewiesen und nicht gespeichert.
     */
    public function testSetPrioritaetLehntUngueltigenWertAb(): void
    {
        $request = $this->createStub(IRequest::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null): mixed {
                return match ($key) {
                    'prioritaet' => 'sofort',
                    default => $default,
                };
            });

        $service = $this->createMock(GeschaeftService::class);
        $service->expects($this->never())->method('aktualisiereInterneFelder');

        $controller = new GeschaeftController(
            $request,
            $service,
            $this->createStub(FraktionsarbeitService::class),
            $this->createStub(RealtimePublisherService::class),
            $this->createStub(IRootFolder::class),
            $this->createStub(IUserSession::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(GeschaeftMapper::class),
        );

        $response = $controller->setPrioritaet(5);
        $this->assertSame(400, $response->getStatus());
    }
}
