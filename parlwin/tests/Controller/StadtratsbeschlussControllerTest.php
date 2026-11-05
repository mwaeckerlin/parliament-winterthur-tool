<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Controller;

use OCA\ParliamentWinterthur\Controller\StadtratsbeschlussController;
use OCA\ParliamentWinterthur\Db\GeschaeftAktion;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\NotizRevisionMapper;
use OCA\ParliamentWinterthur\Db\Stadtratsbeschluss;
use OCA\ParliamentWinterthur\Db\StadtratsbeschlussMapper;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCA\ParliamentWinterthur\Service\RealtimePublisherService;
use OCA\ParliamentWinterthur\Service\ScraperService;
use OCA\ParliamentWinterthur\Service\StadtratsbeschlussService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Die Notiz-Endpunkte der Stadtratsbeschlüsse (F146) antworten auf jeden
 * Fehler des geteilten Notiz-Codes mit einer Meldung und dem passenden Status:
 * 404 für eine Notiz, die es an diesem Beschluss nicht gibt, 403 für die Notiz
 * eines anderen Autors, 400 für einen leeren Text. Der Controller läuft mit
 * dem echten `StadtratsbeschlussService` und dem echten `NotizService`; nur die
 * Mapper und die Anmeldung sind ersetzt.
 */
class StadtratsbeschlussControllerTest extends TestCase
{
    private const BESCHLUSS = 5;
    private const ANDERER_BESCHLUSS = 6;
    private const NOTIZ_VON_ANNA = 11;
    private const NOTIZ_AM_ANDEREN_BESCHLUSS = 12;
    private const UNBEKANNTE_NOTIZ = 99;

    /** @var array<int, GeschaeftAktion> */
    private array $aktionen = [];

    private function notiz(int $id, int $beschluss, string $autor): GeschaeftAktion
    {
        $aktion = new GeschaeftAktion();
        $aktion->setId($id);
        $aktion->setObjektTyp('stadtratsbeschluss');
        $aktion->setGeschaeftId($beschluss);
        $aktion->setAktionTyp('notiz');
        $aktion->setText('Notiz von ' . $autor);
        $aktion->setAutorUid($autor);
        $aktion->setAutorName($autor);
        $aktion->setErstelltAm('2026-11-01 10:00:00');
        $aktion->setGeloescht(false);
        return $aktion;
    }

    private function controller(string $angemeldet, string $text = 'Neuer Text'): StadtratsbeschlussController
    {
        $this->aktionen = [
            self::NOTIZ_VON_ANNA => $this->notiz(self::NOTIZ_VON_ANNA, self::BESCHLUSS, 'anna'),
            self::NOTIZ_AM_ANDEREN_BESCHLUSS => $this->notiz(self::NOTIZ_AM_ANDEREN_BESCHLUSS, self::ANDERER_BESCHLUSS, $angemeldet),
        ];
        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $aktionMapper->method('findById')->willReturnCallback(function (int $id): GeschaeftAktion {
            if (!isset($this->aktionen[$id])) {
                throw new DoesNotExistException('keine Aktion ' . $id);
            }
            return $this->aktionen[$id];
        });
        $aktionMapper->method('insert')->willReturnArgument(0);
        $aktionMapper->method('update')->willReturnArgument(0);

        $beschluesse = $this->createStub(StadtratsbeschlussMapper::class);
        $beschluesse->method('find')->willReturnCallback(static function (int $id): Stadtratsbeschluss {
            if ($id !== self::BESCHLUSS && $id !== self::ANDERER_BESCHLUSS) {
                throw new DoesNotExistException('kein Beschluss ' . $id);
            }
            $beschluss = new Stadtratsbeschluss();
            $beschluss->setId($id);
            return $beschluss;
        });

        $benutzer = $this->createStub(IUser::class);
        $benutzer->method('getUID')->willReturn($angemeldet);
        $benutzer->method('getDisplayName')->willReturn($angemeldet);
        $sitzung = $this->createStub(IUserSession::class);
        $sitzung->method('getUser')->willReturn($benutzer);

        $request = $this->createStub(IRequest::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name, mixed $standard = null): mixed => $name === 'text' ? $text : $standard
        );

        return new StadtratsbeschlussController(
            $request,
            new StadtratsbeschlussService(
                $beschluesse,
                $this->createStub(ScraperService::class),
                $this->createStub(LoggerInterface::class),
                $this->createStub(RealtimePublisherService::class),
                new NotizService($aktionMapper, $this->createStub(NotizRevisionMapper::class), $sitzung),
            ),
            $this->createStub(RealtimePublisherService::class),
        );
    }

    /** @return array<string, array{0: string}> */
    public static function endpunkteMitNotiz(): array
    {
        return [
            'bearbeiten' => ['updateNotiz'],
            'löschen' => ['deleteNotiz'],
            'wiederherstellen' => ['restoreNotiz'],
            'Fassungen' => ['notizRevisionen'],
        ];
    }

    #[DataProvider('endpunkteMitNotiz')]
    public function testDieNotizEinesAnderenAutorsErgibt403(string $endpunkt): void
    {
        $antwort = $this->controller('bruno')->$endpunkt(self::BESCHLUSS, self::NOTIZ_VON_ANNA);

        self::assertSame(Http::STATUS_FORBIDDEN, $antwort->getStatus());
        self::assertStringContainsString('Nur der Autor', $antwort->getData()['fehler'] ?? '');
        self::assertSame('Notiz von anna', $this->aktionen[self::NOTIZ_VON_ANNA]->getText());
        self::assertFalse($this->aktionen[self::NOTIZ_VON_ANNA]->getGeloescht());
    }

    #[DataProvider('endpunkteMitNotiz')]
    public function testEineUnbekannteNotizErgibt404(string $endpunkt): void
    {
        $antwort = $this->controller('anna')->$endpunkt(self::BESCHLUSS, self::UNBEKANNTE_NOTIZ);

        self::assertSame(Http::STATUS_NOT_FOUND, $antwort->getStatus());
        self::assertSame('Diese Notiz gibt es nicht mehr.', $antwort->getData()['fehler'] ?? null);
    }

    #[DataProvider('endpunkteMitNotiz')]
    public function testDieNotizEinesAnderenBeschlussesErgibt404(string $endpunkt): void
    {
        $controller = $this->controller('anna');
        $antwort = $controller->$endpunkt(self::BESCHLUSS, self::NOTIZ_AM_ANDEREN_BESCHLUSS);

        self::assertSame(Http::STATUS_NOT_FOUND, $antwort->getStatus());
        self::assertSame('Notiz von anna', $this->aktionen[self::NOTIZ_AM_ANDEREN_BESCHLUSS]->getText());
        self::assertFalse($this->aktionen[self::NOTIZ_AM_ANDEREN_BESCHLUSS]->getGeloescht());
    }

    #[DataProvider('endpunkteMitNotiz')]
    public function testEinUnbekannterBeschlussErgibt404(string $endpunkt): void
    {
        $antwort = $this->controller('anna')->$endpunkt(404, self::NOTIZ_VON_ANNA);

        self::assertSame(Http::STATUS_NOT_FOUND, $antwort->getStatus());
        self::assertStringContainsString('steht nicht mehr in der Liste', $antwort->getData()['fehler'] ?? '');
    }

    public function testEinLeererTextErgibt400(): void
    {
        $antwort = $this->controller('anna', '  ')->updateNotiz(self::BESCHLUSS, self::NOTIZ_VON_ANNA);

        self::assertSame(Http::STATUS_BAD_REQUEST, $antwort->getStatus());
        self::assertSame('Notiz von anna', $this->aktionen[self::NOTIZ_VON_ANNA]->getText());
    }

    public function testEineNeueNotizOhneTextErgibt400(): void
    {
        $antwort = $this->controller('anna', '')->addNotiz(self::BESCHLUSS);

        self::assertSame(Http::STATUS_BAD_REQUEST, $antwort->getStatus());
    }

    public function testEineNeueNotizAnEinemUnbekanntenBeschlussErgibt404(): void
    {
        $antwort = $this->controller('anna')->addNotiz(404);

        self::assertSame(Http::STATUS_NOT_FOUND, $antwort->getStatus());
    }

    public function testDerAutorBearbeitetUndLoeschtSeineNotiz(): void
    {
        $controller = $this->controller('anna');

        $bearbeitet = $controller->updateNotiz(self::BESCHLUSS, self::NOTIZ_VON_ANNA);
        self::assertSame(Http::STATUS_OK, $bearbeitet->getStatus());
        self::assertSame('Neuer Text', $this->aktionen[self::NOTIZ_VON_ANNA]->getText());

        $geloescht = $controller->deleteNotiz(self::BESCHLUSS, self::NOTIZ_VON_ANNA);
        self::assertSame(Http::STATUS_OK, $geloescht->getStatus());
        self::assertTrue($this->aktionen[self::NOTIZ_VON_ANNA]->getGeloescht());

        $zurueck = $controller->restoreNotiz(self::BESCHLUSS, self::NOTIZ_VON_ANNA);
        self::assertSame(Http::STATUS_OK, $zurueck->getStatus());
        self::assertFalse($this->aktionen[self::NOTIZ_VON_ANNA]->getGeloescht());
    }
}
