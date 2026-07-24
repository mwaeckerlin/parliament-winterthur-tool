<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Search;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Search\GeschaeftSearchProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Feature: Geschäfte sind über die zentrale Nextcloud-Suche auffindbar —
 * Treffer zeigen Nummer + Titel und verlinken in die App.
 */
class GeschaeftSearchProviderTest extends TestCase
{
    private function provider(GeschaeftMapper $mapper): GeschaeftSearchProvider
    {
        $url = $this->createStub(IURLGenerator::class);
        $url->method('linkToRoute')->willReturn('/apps/parlwin/');
        $l10n = $this->createStub(IL10N::class);
        $l10n->method('t')->willReturnArgument(0);
        return new GeschaeftSearchProvider($mapper, $url, $l10n, $this->createStub(LoggerInterface::class));
    }

    private function query(string $term): ISearchQuery
    {
        $query = $this->createStub(ISearchQuery::class);
        $query->method('getTerm')->willReturn($term);
        $query->method('getLimit')->willReturn(10);
        return $query;
    }

    public function testSucheLiefertTrefferMitNummerTitelUndAppLink(): void
    {
        $g = new Geschaeft();
        $g->setNummer('2026.42');
        $g->setTitel('Velowege ausbauen');
        $g->setTyp('Motion');
        $g->setStatus('Pendent');
        $g->setId(42);

        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('searchByText')->willReturn([$g]);

        $resultat = $this->provider($mapper)->search($this->createStub(IUser::class), $this->query('Velo'));

        $this->assertCount(1, $resultat->entries);
        $this->assertSame('2026.42 Velowege ausbauen', $resultat->entries[0]->title);
        $this->assertSame('Motion · Pendent', $resultat->entries[0]->subline);
        $this->assertStringContainsString('/apps/parlwin/', $resultat->entries[0]->resourceUrl);
        $this->assertStringContainsString('#geschaeft-42', $resultat->entries[0]->resourceUrl);
    }

    public function testLeererSuchbegriffLiefertKeineTreffer(): void
    {
        $mapper = $this->createStub(GeschaeftMapper::class);
        $resultat = $this->provider($mapper)->search($this->createStub(IUser::class), $this->query('   '));
        $this->assertSame([], $resultat->entries);
    }

    public function testDatenbankfehlerLiefertLeeresResultatStattAbsturz(): void
    {
        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('searchByText')->willThrowException(new \RuntimeException('DB weg'));
        $resultat = $this->provider($mapper)->search($this->createStub(IUser::class), $this->query('Velo'));
        $this->assertSame([], $resultat->entries);
    }
}
