<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Middleware;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;

/**
 * Übersetzt jede Ausnahme eines Controllers in eine Antwort, die der Benutzer
 * lesen kann (F139). Ohne sie beantwortet Nextcloud eine ungefangene Ausnahme
 * mit dem allgemeinen Status 500, und im Browser steht «Request failed with
 * status code 500» — der Grund bleibt im Serverprotokoll, an das der Benutzer
 * nicht kommt.
 *
 * Die Antwort trägt den Grund im Feld «fehler», das die Oberfläche schon liest
 * und hinter ihre eigene Zeile setzt. Der Status unterscheidet, was der
 * Benutzer tun kann:
 *
 *  - 404 die angesprochene Sache gibt es nicht,
 *  - 400 die Eingabe passt nicht — der Text der Ausnahme sagt, was fehlt,
 *  - 500 alles übrige; dort steht eine allgemeine Zeile samt Kennung, mit der
 *    sich der Eintrag im Serverprotokoll finden lässt, denn die Meldung einer
 *    unerwarteten Ausnahme nennt oft eine Datei und eine Zeile und gehört
 *    nicht auf den Schirm.
 */
class FehlerMiddleware extends Middleware
{
    /**
     * Die Ausnahmen, die dieser Code selbst wirft, um eine Eingabe abzulehnen.
     * Geprüft wird die Klasse GENAU und nicht über die Vererbung: `PDOException`
     * erbt von `RuntimeException`, und ihr Text nennt die SQL-Anweisung samt
     * Datei und Zeile — das gehört ins Protokoll und nicht auf den Schirm.
     */
    private const EIGENE = [
        \RuntimeException::class,
        \InvalidArgumentException::class,
        \DomainException::class,
        \LengthException::class,
        \UnexpectedValueException::class,
    ];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function afterException($controller, $methodName, \Exception $exception): Response
    {
        $kennung = bin2hex(random_bytes(4));
        $this->logger->error(
            sprintf('parlwin %s::%s fehlgeschlagen [%s]', get_debug_type($controller), $methodName, $kennung),
            ['exception' => $exception],
        );

        if ($exception instanceof DoesNotExistException) {
            return new DataResponse(
                ['fehler' => 'Der Eintrag ist nicht mehr vorhanden. Die Ansicht neu laden.'],
                Http::STATUS_NOT_FOUND,
            );
        }
        if (in_array($exception::class, self::EIGENE, true)) {
            return new DataResponse(
                ['fehler' => $this->text($exception)],
                Http::STATUS_BAD_REQUEST,
            );
        }
        return new DataResponse(
            ['fehler' => sprintf(
                'Unerwarteter Fehler im Server (%s). Die Einzelheiten stehen unter dieser Kennung im Protokoll '
                . 'der Nextcloud-Installation.',
                $kennung,
            )],
            Http::STATUS_INTERNAL_SERVER_ERROR,
        );
    }

    /**
     * Der Text einer erwarteten Ausnahme, mit einem Rückfall: eine Ausnahme
     * ohne Meldung ergäbe sonst eine leere Zeile neben dem «fehlgeschlagen»
     * der Oberfläche.
     */
    private function text(\Exception $exception): string
    {
        $text = trim($exception->getMessage());
        return $text === '' ? 'Die Eingabe konnte nicht verarbeitet werden.' : $text;
    }
}
