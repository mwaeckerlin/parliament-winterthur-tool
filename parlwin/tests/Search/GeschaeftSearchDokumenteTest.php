<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Search;

use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Search\GeschaeftSearchProvider;
use OCA\ParliamentWinterthur\Service\GeschaeftDokumentService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Die Suche greift auch in die amtlichen Dokumente (F121): Ein Wort, das nur im
 * PDF steht und in keinem Titel, findet das Geschäft trotzdem.
 */
class GeschaeftSearchDokumenteTest extends TestCase
{
    /** @var int[] */
    private array $gefragteIds = [];

    private function geschaeft(int $id, string $nummer, string $titel): Geschaeft
    {
        $geschaeft = new Geschaeft();
        $geschaeft->setId($id);
        $geschaeft->setNummer($nummer);
        $geschaeft->setTitel($titel);
        return $geschaeft;
    }

    private function anbieter(): GeschaeftSearchProvider
    {
        $mapper = $this->createStub(GeschaeftMapper::class);
        $mapper->method('searchByText')->willReturnCallback(
            function (string $text, int $limit = 20, array $zusatzIds = []): array {
                $this->gefragteIds = $zusatzIds;
                $treffer = [];
                if (str_contains('immobilien', mb_strtolower($text))) {
                    $treffer[] = $this->geschaeft(42, '2026.15', 'Zweck der Immobilien');
                }
                foreach ($zusatzIds as $id) {
                    $treffer[] = $this->geschaeft($id, '2026.99', 'Aus dem Dokument');
                }
                return $treffer;
            },
        );

        $dokumente = $this->createStub(GeschaeftDokumentService::class);
        $dokumente->method('geschaeftIdsMitText')->willReturn([77]);

        $urlGenerator = $this->createStub(IURLGenerator::class);
        $urlGenerator->method('linkToRoute')->willReturn('/apps/parlwin/');
        $l10n = $this->createStub(IL10N::class);
        $l10n->method('t')->willReturnArgument(0);

        return new GeschaeftSearchProvider(
            $mapper,
            $urlGenerator,
            $l10n,
            $this->createStub(LoggerInterface::class),
            $dokumente,
        );
    }

    private function frage(string $begriff): ISearchQuery
    {
        $query = $this->createStub(ISearchQuery::class);
        $query->method('getTerm')->willReturn($begriff);
        $query->method('getLimit')->willReturn(20);
        return $query;
    }

    public function testEinWortAusDemDokumentFindetDasGeschaeft(): void
    {
        $ergebnis = $this->anbieter()->search($this->createStub(IUser::class), $this->frage('Nettorendite'));
        self::assertSame([77], $this->gefragteIds, 'die Dokumenttreffer gehen in die Abfrage ein');
        self::assertNotSame([], $ergebnis->entries);
    }
}
