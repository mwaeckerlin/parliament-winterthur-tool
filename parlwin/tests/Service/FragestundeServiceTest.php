<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\Frage;
use OCA\ParliamentWinterthur\Db\FrageMapper;
use OCA\ParliamentWinterthur\Db\Fragestunde;
use OCA\ParliamentWinterthur\Db\FragestundeMapper;
use OCA\ParliamentWinterthur\Db\Geschaeft;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCA\ParliamentWinterthur\Service\FragestundeService;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Die Fragestunde (F114) und die zwei Regeln des Parlaments, die sie prägen
 * (Art. 103 Abs. 2 der Organisationsverordnung des Stadtparlaments):
 * Eingabefrist am Donnerstag davor und höchstens 1'000 Zeichen je Frage. Dazu die
 * Regel des Rats, dass jedes Mitglied nur eine Frage einreicht.
 */
class FragestundeServiceTest extends TestCase {
    private function dienst(
        ?FragestundeMapper $fragestunden = null,
        ?FrageMapper $fragen = null,
        ?IUserSession $session = null,
        ?GeschaeftMapper $geschaefte = null,
    ): FragestundeService {
        $notizen = $this->createStub(NotizService::class);
        $notizen->method('liste')->willReturn([]);
        return new FragestundeService(
            $fragestunden ?? $this->createStub(FragestundeMapper::class),
            $fragen ?? $this->createStub(FrageMapper::class),
            $this->createStub(MitgliedMapper::class),
            $geschaefte ?? $this->createStub(GeschaeftMapper::class),
            $session ?? $this->createStub(IUserSession::class),
            $notizen,
        );
    }

    /**
     * Die Frist ist der Donnerstag vor der Fragestunde. Die Daten sind echte
     * Fragestunden des Parlaments: der 2. März 2026 und der 24. Februar 2025
     * (beide Montage), dazu ein Donnerstag als Randfall — dann gilt der
     * Donnerstag der Woche davor.
     *
     * @return list<array{string, string}>
     */
    public static function fristen(): array {
        return [
            ['2026-03-02', '2026-02-26'],
            ['2025-02-24', '2025-02-20'],
            ['2026-02-26', '2026-02-19'],
            ['2026-02-27', '2026-02-26'],
        ];
    }

    #[DataProvider('fristen')]
    public function testDieFristIstDerDonnerstagVorDerFragestunde(string $datum, string $frist): void {
        self::assertSame($frist, $this->dienst()->donnerstagDavor($datum));
    }

    /**
     * Eine Frage mit mehr als 1'000 Zeichen nimmt der Parlamentsdienst nicht
     * entgegen. Die Meldung sagt, wie lang sie ist, wie lang sie sein darf und
     * woher die Grenze kommt — sonst weiss niemand, was zu tun ist.
     */
    public function testEineZuLangeFrageWirdMitBegruendungAbgewiesen(): void {
        $fragestunden = $this->createStub(FragestundeMapper::class);
        $fragestunden->method('find')->willReturn(new Fragestunde());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/1001 Zeichen.*1000 Zeichen.*Art\. 103 Abs\. 2/su');
        $this->dienst($fragestunden)->erstelleFrage(1, ['frage' => str_repeat('a', 1001)]);
    }

    /** Genau 1'000 Zeichen sind zulässig — die Grenze schliesst sie ein. */
    public function testGenauTausendZeichenSindZulaessig(): void {
        $fragestunden = $this->createStub(FragestundeMapper::class);
        $fragestunden->method('find')->willReturn(new Fragestunde());
        $fragen = $this->createStub(FrageMapper::class);
        $fragen->method('insert')->willReturnArgument(0);

        $frage = $this->dienst($fragestunden, $fragen)->erstelleFrage(1, ['frage' => str_repeat('a', 1000)]);
        self::assertSame(1000, mb_strlen((string) $frage->getFrage()));
    }

    /**
     * Wer die Frage einbringt, steht standardmässig als Urheber da: das
     * angemeldete Mitglied. Der Einreicher bleibt leer — die Fraktion teilt ihn
     * erst zu, wenn sie die Frage stellt.
     */
    public function testDerUrheberIstStandardmaessigDasAngemeldeteMitglied(): void {
        $fragestunden = $this->createStub(FragestundeMapper::class);
        $fragestunden->method('find')->willReturn(new Fragestunde());
        $fragen = $this->createStub(FrageMapper::class);
        $fragen->method('insert')->willReturnArgument(0);
        $user = $this->createStub(IUser::class);
        $user->method('getUID')->willReturn('rkeller');
        $user->method('getDisplayName')->willReturn('Regula Keller');
        $session = $this->createStub(IUserSession::class);
        $session->method('getUser')->willReturn($user);

        $frage = $this->dienst($fragestunden, $fragen, $session)->erstelleFrage(1, ['frage' => 'Warum?']);
        self::assertSame('Regula Keller', $frage->getUrheberName());
        self::assertSame('', $frage->getEinreicherKey());
        self::assertSame('neu', $frage->getStatus());
    }

    /**
     * Jedes Mitglied reicht nur eine Frage ein. Sind einem zwei zugeteilt, meldet
     * die Ansicht ihn — die Fraktion muss umverteilen.
     */
    public function testMehrfachZugeteilteEinreicherWerdenGemeldet(): void {
        $fragestunde = new Fragestunde();
        $fragestunde->setId(7);
        $fragen = $this->createStub(FrageMapper::class);
        $fragen->method('findByFragestunde')->willReturn([
            $this->frage(1, '12', 'M. Bachmann'),
            $this->frage(2, '12', 'M. Bachmann'),
            $this->frage(3, '13', 'R. Keller'),
            $this->frage(4, '', ''),
        ]);

        $ansicht = $this->dienst(null, $fragen)->mitFragen($fragestunde);
        self::assertSame(['12'], $ansicht['mehrfachZugeteilt']);
        self::assertCount(4, $ansicht['fragen']);
    }

    /**
     * Fragen werden JEDERZEIT gesammelt, auch wenn noch keine Fragestunde
     * angesetzt ist: Die Fraktion trägt ein, was ihr auffällt, und nutzt es
     * später in irgendeiner Fragestunde. Eine Frage ohne Fragestunde fragt
     * deshalb gar nicht erst nach einer.
     */
    public function testEineFrageEntstehtAuchOhneFragestunde(): void {
        $fragen = $this->createStub(FrageMapper::class);
        $fragen->method('insert')->willReturnArgument(0);
        $fragestunden = $this->createMock(FragestundeMapper::class);
        $fragestunden->expects(self::never())->method('find');

        $frage = $this->dienst($fragestunden, $fragen)
            ->erstelleFrage(0, ['frage' => 'Wann wird der Veloweg an der Seenerstrasse markiert?']);

        self::assertSame(0, (int) $frage->getFragestundeId());
        self::assertSame('Wann wird der Veloweg an der Seenerstrasse markiert?', $frage->getFrage());
    }

    /**
     * Die Ansicht führt die Fragen als Hauptsache und die Fragestunden daneben.
     * Jede Frage nennt ihre Fragestunde, sofern sie einer zugeteilt ist.
     */
    public function testDieAnsichtFuehrtAlleFragenUndDieFragestundenDaneben(): void {
        $fragestunde = new Fragestunde();
        $fragestunde->setId(7);
        $fragestunde->setDatum('2026-03-02');
        $fragestunde->setTitel('Fragestunde vom 2. März 2026');
        $fragestunde->setFrist('2026-02-26');
        $fragestunden = $this->createStub(FragestundeMapper::class);
        $fragestunden->method('findAll')->willReturn([$fragestunde]);

        $zugeteilt = $this->frage(1, '', '');
        $zugeteilt->setFragestundeId(7);
        $frei = $this->frage(2, '', '');
        $fragen = $this->createStub(FrageMapper::class);
        $fragen->method('findAlle')->willReturn([$zugeteilt, $frei]);
        $fragen->method('findByFragestunde')->willReturn([$zugeteilt]);

        $ansicht = $this->dienst($fragestunden, $fragen)->alle();

        self::assertCount(1, $ansicht['fragestunden'], 'die geplanten Fragestunden');
        self::assertCount(2, $ansicht['fragen'], 'alle Fragen, zugeteilt und frei');
        self::assertSame(7, $ansicht['fragen'][0]['fragestunde']['id']);
        self::assertSame('Fragestunde vom 2. März 2026', $ansicht['fragen'][0]['fragestunde']['titel']);
        self::assertNull($ansicht['fragen'][1]['fragestunde'], 'eine Frage ohne Fragestunde');
    }

    /**
     * Eine gesammelte Frage wird später einer Fragestunde zugeteilt, und die
     * Zuteilung lässt sich wieder lösen.
     */
    public function testEineFrageLaesstSichEinerFragestundeZuteilenUndWiederLoesen(): void {
        $frage = new Frage();
        $frage->setId(5);
        $frage->setFragestundeId(0);
        $fragen = $this->createStub(FrageMapper::class);
        $fragen->method('find')->willReturn($frage);
        $fragen->method('update')->willReturnArgument(0);
        $fragestunde = new Fragestunde();
        $fragestunde->setId(7);
        $fragestunden = $this->createStub(FragestundeMapper::class);
        $fragestunden->method('find')->willReturn($fragestunde);

        $dienst = $this->dienst($fragestunden, $fragen);
        $dienst->aktualisiereFrage(5, ['fragestundeId' => 7]);
        self::assertSame(7, (int) $frage->getFragestundeId(), 'die Zuteilung greift nicht');

        $dienst->aktualisiereFrage(5, ['fragestundeId' => 0]);
        self::assertSame(0, (int) $frage->getFragestundeId(), 'die Zuteilung lässt sich nicht lösen');
    }

    /** Ein unbekannter Status wird abgewiesen, statt still gespeichert zu werden. */
    public function testEinUnbekannterStatusWirdAbgewiesen(): void {
        $fragen = $this->createStub(FrageMapper::class);
        $fragen->method('find')->willReturn(new Frage());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/erledigt.*neu, besprochen, eingereicht, zurueckgezogen/su');
        $this->dienst(null, $fragen)->aktualisiereFrage(1, ['status' => 'erledigt']);
    }

    /**
     * Beim Anlegen entstehen Titel und Frist aus dem Datum — niemand tippt sie
     * ab.
     */
    public function testTitelUndFristEntstehenAusDemDatum(): void {
        $fragestunden = $this->createStub(FragestundeMapper::class);
        $fragestunden->method('findByDatum')->willReturn(null);
        $fragestunden->method('insert')->willReturnArgument(0);

        $fragestunde = $this->dienst($fragestunden)->erstelleFragestunde(['datum' => '2026-03-02']);
        self::assertSame('Fragestunde vom 2. März 2026', $fragestunde->getTitel());
        self::assertSame('2026-02-26', $fragestunde->getFrist());
    }

    /** Ohne Datum gibt es keine Fragestunde. */
    public function testOhneDatumKeineFragestunde(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->dienst()->erstelleFragestunde(['titel' => 'Fragestunde']);
    }

    /**
     * Das Parlament führt jede Fragestunde als eigenes Geschäft («Fragestunde vom
     * 2. März 2026»), veröffentlicht es aber erst kurz vorher. Sobald es
     * abgerufen ist, verknüpft die Ansicht es — und hält die Verknüpfung fest,
     * damit sie nicht bei jedem Aufruf neu gesucht wird.
     */
    public function testDasGeschaeftDesParlamentsWirdNachtraeglichVerknuepft(): void {
        $fragestunde = new Fragestunde();
        $fragestunde->setId(3);
        $fragestunde->setDatum('2026-03-02');

        $geschaeft = new Geschaeft();
        $geschaeft->setId(4711);
        $geschaeft->setNummer('2026.8');
        $geschaeft->setTitel('Fragestunde vom 2. März 2026 (Beginn 20.00 Uhr)');
        $geschaeft->setUrl('https://parlament.winterthur.ch/_rte/information/2744876');
        $geschaefte = $this->createMock(GeschaeftMapper::class);
        $geschaefte->expects(self::once())
            ->method('findeFragestunde')
            ->with('2. März 2026')
            ->willReturn($geschaeft);
        $fragestunden = $this->createMock(FragestundeMapper::class);
        $fragestunden->expects(self::once())->method('update')->willReturnArgument(0);

        $ansicht = $this->dienst($fragestunden, null, null, $geschaefte)->mitFragen($fragestunde);
        self::assertSame('2026.8', $ansicht['geschaeft']['nummer']);
        self::assertSame(
            'https://parlament.winterthur.ch/_rte/information/2744876',
            $ansicht['geschaeft']['url']
        );
        self::assertSame(4711, (int) $fragestunde->getGeschaeftId(), 'die Verknüpfung wird festgehalten');
    }

    /** Solange das Parlament die Fragestunde nicht veröffentlicht hat, gibt es kein Geschäft. */
    public function testOhneVeroeffentlichtesGeschaeftBleibtDieVerknuepfungLeer(): void {
        $fragestunde = new Fragestunde();
        $fragestunde->setId(3);
        $fragestunde->setDatum('2026-03-02');
        $geschaefte = $this->createStub(GeschaeftMapper::class);
        $geschaefte->method('findeFragestunde')->willReturn(null);

        $ansicht = $this->dienst(null, null, null, $geschaefte)->mitFragen($fragestunde);
        self::assertNull($ansicht['geschaeft']);
    }

    private function frage(int $id, string $einreicherKey, string $einreicherName): Frage {
        $frage = new Frage();
        $frage->setId($id);
        $frage->setFrage('Frage ' . $id);
        $frage->setEinreicherKey($einreicherKey);
        $frage->setEinreicherName($einreicherName);
        return $frage;
    }
}
