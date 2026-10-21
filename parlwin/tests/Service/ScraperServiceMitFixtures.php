<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Service\ScraperService;

/**
 * ScraperService, dessen Parallel-Download aus den Fixtures antwortet.
 *
 * Die Detailseiten holt der Dienst nicht über `IClient`, sondern über
 * `curl_multi` — eine Attrappe von `IClient` erreicht sie also nicht. Ohne
 * diese Klasse rief `testLadeGeschaefteAusLokalerFixture` 1229 Detailseiten
 * von parlament.winterthur.ch wirklich ab: Der Lauf dauerte Minuten, hing an
 * der Erreichbarkeit einer fremden Webseite, und geprüft war das Lesen der
 * Detailseiten damit gar nicht (gemessen am 2026-10-03).
 */
class ScraperServiceMitFixtures extends ScraperService
{
    /** @var (callable(string): string)|null */
    private $resolver = null;

    /** @param callable(string): string $resolver */
    public function setzeResolver(callable $resolver): void
    {
        $this->resolver = $resolver;
    }

    protected function ladeHtmlParallel(array $urls, int $parallel, bool $mitSequenziellemFallback = true, ?callable $onComplete = null): array
    {
        $resolver = $this->resolver;
        if ($resolver === null) {
            throw new \LogicException('Kein Resolver gesetzt: setzeResolver() fehlt');
        }

        $ergebnisse = [];
        $urls = array_values(array_unique(array_filter($urls, static fn(string $u): bool => $u !== '')));
        foreach ($urls as $url) {
            $erfolg = false;
            try {
                $ergebnisse[$url] = $resolver($url);
                $erfolg = true;
            } catch (\Throwable) {
                // Wie im Betrieb: Eine Seite, die nicht kommt, fehlt im
                // Ergebnis, und der Lauf geht weiter.
            }
            if ($onComplete !== null) {
                $onComplete($url, $erfolg, count($ergebnisse), count($urls));
            }
        }
        return $ergebnisse;
    }
}
