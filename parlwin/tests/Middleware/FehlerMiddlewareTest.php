<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Middleware;

use OCA\ParliamentWinterthur\Middleware\FehlerMiddleware;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * F139: Jede Ausnahme wird zu einer Meldung, die der Benutzer lesen kann.
 * Vorher beantwortete Nextcloud eine ungefangene Ausnahme mit dem allgemeinen
 * Status 500, und im Browser stand «Antrag speichern fehlgeschlagen: Request
 * failed with status code 500» — ohne jeden Grund (gemeldet am 25.10.2026).
 */
final class FehlerMiddlewareTest extends TestCase
{
    private function middleware(): FehlerMiddleware
    {
        return new FehlerMiddleware($this->createStub(LoggerInterface::class));
    }

    private function controller(): Controller
    {
        return $this->createStub(Controller::class);
    }

    public function testEineErwarteteAusnahmeTraegtIhrenTextZumBenutzer(): void
    {
        $antwort = $this->middleware()->afterException(
            $this->controller(),
            'antragErstellen',
            new \RuntimeException('Auf eine künstliche Produktegruppe sind keine Anträge möglich'),
        );

        self::assertInstanceOf(DataResponse::class, $antwort);
        self::assertSame(Http::STATUS_BAD_REQUEST, $antwort->getStatus(), 'eine Eingabe, die nicht passt, ist kein Serverfehler');
        self::assertSame(
            'Auf eine künstliche Produktegruppe sind keine Anträge möglich',
            $antwort->getData()['fehler'],
            'der Grund steht im Feld, das die Oberfläche anzeigt',
        );
    }

    public function testEineFehlendeSacheWirdZu404(): void
    {
        $antwort = $this->middleware()->afterException(
            $this->controller(),
            'antragAendern',
            new DoesNotExistException('row not found'),
        );

        self::assertSame(Http::STATUS_NOT_FOUND, $antwort->getStatus());
        self::assertStringContainsString('nicht mehr vorhanden', $antwort->getData()['fehler']);
    }

    /**
     * Eine unerwartete Ausnahme nennt oft eine Datei und eine Zeile; das gehört
     * nicht auf den Schirm. Statt dessen steht dort eine Kennung, mit der sich
     * der Eintrag im Serverprotokoll finden lässt.
     */
    public function testEineUnerwarteteAusnahmeNenntEineKennungStattIhresTextes(): void
    {
        $antwort = $this->middleware()->afterException(
            $this->controller(),
            'antragErstellen',
            new \PDOException('SQLSTATE[23000] /app/lib/Db/Mapper.php:140'),
        );

        self::assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $antwort->getStatus());
        $text = (string) $antwort->getData()['fehler'];
        self::assertStringNotContainsString('SQLSTATE', $text, 'der technische Text bleibt im Protokoll');
        self::assertMatchesRegularExpression('/\([0-9a-f]{8}\)/', $text, 'die Kennung verbindet Meldung und Protokoll');
    }

    public function testEineAusnahmeOhneTextErgibtKeineLeereMeldung(): void
    {
        $antwort = $this->middleware()->afterException($this->controller(), 'antragErstellen', new \InvalidArgumentException(''));

        self::assertNotSame('', trim((string) $antwort->getData()['fehler']));
    }

    /** Jede Ausnahme wird protokolliert, samt der Kennung aus der Meldung. */
    public function testJedeAusnahmeWirdProtokolliert(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        (new FehlerMiddleware($logger))->afterException($this->controller(), 'antragErstellen', new \RuntimeException('kaputt'));
    }
}
