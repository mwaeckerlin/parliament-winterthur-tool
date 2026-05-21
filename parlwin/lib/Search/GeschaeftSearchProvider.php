<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Search;

use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;
use Psr\Log\LoggerInterface;

class GeschaeftSearchProvider implements IProvider
{
    public function __construct(
        private GeschaeftMapper $geschaeftMapper,
        private IURLGenerator $urlGenerator,
        private IL10N $l10n,
        private LoggerInterface $logger
    ) {
    }

    public function getId(): string
    {
        return 'parlwin-geschaefte';
    }

    public function getName(): string
    {
        return $this->l10n->t('Geschäfte (Parlament Winterthur)');
    }

    public function getOrder(string $route, array $routeParameters): int
    {
        if (str_starts_with($route, Application::APP_ID . '.')) {
            return -1;
        }
        return 55;
    }

    public function search(IUser $user, ISearchQuery $query): SearchResult
    {
        $term = trim($query->getTerm());
        if ($term === '') {
            return SearchResult::complete($this->getName(), []);
        }

        try {
            $treffer = $this->geschaeftMapper->searchByText($term, $query->getLimit());
        } catch (\Throwable $e) {
            $this->logger->warning('parlwin search failed: ' . $e->getMessage(), ['exception' => $e]);
            return SearchResult::complete($this->getName(), []);
        }

        $appUrl = $this->urlGenerator->linkToRoute(Application::APP_ID . '.page.index');

        $entries = [];
        foreach ($treffer as $g) {
            $title = trim(($g->getNummer() ?: '') . ' ' . ($g->getTitel() ?: ''));
            $subline = trim(($g->getTyp() ?: '') . ($g->getStatus() ? ' · ' . $g->getStatus() : ''));
            $entries[] = new SearchResultEntry(
                '',
                $title !== '' ? $title : ('Geschäft #' . $g->getId()),
                $subline,
                $appUrl . '#geschaeft-' . $g->getId(),
                'icon-parlwin'
            );
        }

        return SearchResult::complete($this->getName(), $entries);
    }
}
