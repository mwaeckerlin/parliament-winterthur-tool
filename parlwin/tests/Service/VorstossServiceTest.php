<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Service;

use OCA\ParliamentWinterthur\Db\GeschaeftAktion;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\NotizRevisionMapper;
use OCA\ParliamentWinterthur\Db\Vorstoss;
use OCA\ParliamentWinterthur\Db\VorstossMapper;
use OCA\ParliamentWinterthur\Service\GeschaeftService;
use OCA\ParliamentWinterthur\Service\NotizService;
use OCA\ParliamentWinterthur\Service\VorstossService;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class VorstossServiceTest extends TestCase
{
    private function service(
        VorstossMapper $mapper,
        ?GeschaeftService $geschaeftService = null,
        ?NotizService $notizService = null,
    ): VorstossService {
        return new VorstossService(
            $mapper,
            $geschaeftService ?? $this->createStub(GeschaeftService::class),
            $notizService ?? $this->createStub(NotizService::class),
        );
    }

    public function testErstelleUebernimmtFelderUndSetztZeit(): void
    {
        $mapper = $this->createStub(VorstossMapper::class);
        $mapper->method('insert')->willReturnArgument(0);

        $vorstoss = $this->service($mapper)->erstelle([
            'titel' => '  Mein Vorstoss  ',
            'herkunft' => 'fremde',
            'status' => 'bereit',
            'zustaendigkeit' => [['key' => 'mueller', 'name' => 'Anna Müller']],
            'herkunftFraktion' => 'Grüne',
            'ansprechpartner' => [['key' => 'meier', 'name' => 'Bob Meier']],
        ]);

        $this->assertSame('Mein Vorstoss', $vorstoss->getTitel());
        $this->assertSame('fremde', $vorstoss->getHerkunft());
        $this->assertSame('bereit', $vorstoss->getStatus());
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $vorstoss->getErstelltAm());

        // Zuständigkeit ist jetzt eine Liste; die neuen Felder für fremde Vorstösse
        // erscheinen in der API-Ausgabe.
        $json = $vorstoss->jsonSerialize();
        $this->assertSame([['key' => 'mueller', 'name' => 'Anna Müller']], $json['zustaendigkeit']);
        $this->assertSame('Grüne', $json['herkunftFraktion']);
        $this->assertSame([['key' => 'meier', 'name' => 'Bob Meier']], $json['ansprechpartner']);
    }

    /**
     * Regression: Der QBMapper schreibt beim INSERT nur die «dirty» Felder.
     * Beim minimalen Anlegen (nur Titel, neuer Erstellen-Dialog) blieben alle
     * übrigen Spalten weg — NOT-NULL-Spalten ohne DB-Default brachen den
     * INSERT (HTTP 500, im e2e-API-Smoke gefangen; die gemockten Tests sahen
     * es nicht). erstelle() muss deshalb IMMER alle Felder explizit setzen.
     */
    public function testErstelleMitNurTitelSetztAlleSpaltenDirty(): void
    {
        $mapper = $this->createStub(VorstossMapper::class);
        $mapper->method('insert')->willReturnArgument(0);

        $vorstoss = $this->service($mapper)->erstelle(['titel' => 'Nur Titel']);

        $dirty = array_keys($vorstoss->getUpdatedFields());
        foreach ([
            'titel', 'art', 'herkunft', 'status', 'prioritaet', 'beschluss',
            'zustaendigkeit', 'herkunftFraktion', 'ansprechpartner', 'inhalt',
            'dokument', 'notizen', 'geschaeftId', 'geloescht',
            'erstelltAm', 'aktualisiertAm',
        ] as $feld) {
            $this->assertContains($feld, $dirty, "Feld {$feld} fehlt im INSERT");
        }
        $this->assertSame('Nur Titel', $vorstoss->getTitel());
        $this->assertSame('eigene', $vorstoss->getHerkunft());
        $this->assertSame('neu', $vorstoss->getStatus());
        $this->assertSame('[]', $vorstoss->getNotizen());
    }

    public function testErstelleNormalisiertUngueltigeWerte(): void
    {
        $mapper = $this->createStub(VorstossMapper::class);
        $mapper->method('insert')->willReturnArgument(0);

        $vorstoss = $this->service($mapper)->erstelle([
            'titel' => 'X',
            'herkunft' => 'quatsch',
            'status' => 'unsinn',
        ]);

        $this->assertSame('eigene', $vorstoss->getHerkunft());
        $this->assertSame('neu', $vorstoss->getStatus());
    }

    public function testLoescheSetztGeloeschtUndAktualisiert(): void
    {
        $vorstoss = new Vorstoss();
        $vorstoss->setId(1);

        $mapper = $this->createMock(VorstossMapper::class);
        $mapper->method('find')->willReturn($vorstoss);
        $mapper->expects($this->once())
            ->method('update')
            ->with($this->callback(static fn (Vorstoss $v): bool => $v->getGeloescht() === true));

        $this->service($mapper)->loesche(1);
    }

    /**
     * Notizen laufen über den GETEILTEN NotizService (kein eigener Vorstoss-Code):
     * notizHinzufuegen delegiert mit objektTyp «vorstoss» und liefert die
     * erzeugte Notiz-Aktion zurück — dieselbe Struktur wie beim Geschäft.
     */
    public function testNotizHinzufuegenDelegiertAnGeteiltenNotizService(): void
    {
        $vorstoss = new Vorstoss();
        $vorstoss->setId(7);
        $mapper = $this->createStub(VorstossMapper::class);
        $mapper->method('find')->willReturn($vorstoss);

        $aktionMapper = $this->createStub(GeschaeftAktionMapper::class);
        $aktionMapper->method('insert')->willReturnCallback(static function (GeschaeftAktion $a): GeschaeftAktion {
            $a->setId(55);
            return $a;
        });
        $user = $this->createStub(IUser::class);
        $user->method('getUID')->willReturn('amueller');
        $user->method('getDisplayName')->willReturn('Anna Müller');
        $userSession = $this->createStub(IUserSession::class);
        $userSession->method('getUser')->willReturn($user);
        $notizService = new NotizService($aktionMapper, $this->createStub(NotizRevisionMapper::class), $userSession);

        $result = $this->service($mapper, null, $notizService)->notizHinzufuegen(7, 'Meine Notiz');

        $this->assertSame('notiz', $result['aktionTyp']);
        $this->assertSame('Meine Notiz', $result['text']);
        $this->assertSame('Anna Müller', $result['autorName']);
        $this->assertSame(55, $result['id']);
    }

    public function testVerknuepfenSetztGeschaeftSchliesstAbUndUebernimmtPrio(): void
    {
        $vorstoss = new Vorstoss();
        $vorstoss->setPrioritaet('hoch');
        $mapper = $this->createStub(VorstossMapper::class);
        $mapper->method('find')->willReturn($vorstoss);
        $mapper->method('update')->willReturnArgument(0);

        $geschaeftService = $this->createMock(GeschaeftService::class);
        $geschaeftService->expects($this->once())
            ->method('aktualisiereInterneFelder')
            ->with(42, ['prioritaet' => 'hoch']);

        $result = $this->service($mapper, $geschaeftService)->verknuepfen(1, 42);
        $this->assertSame(42, $result->getGeschaeftId());
        $this->assertSame('erledigt', $result->getStatus());
    }
}
