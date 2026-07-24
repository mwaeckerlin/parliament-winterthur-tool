<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Tests\Db;

use OCA\ParliamentWinterthur\Db\Vorstoss;
use PHPUnit\Framework\TestCase;

/**
 * Feature: Zuständigkeit ist eine LISTE von Personen; ein fremder Vorstoss hat
 * zusätzlich eine Herkunftsfraktion und eine Ansprechpartner-Liste. Diese Felder
 * müssen in der API-Ausgabe (jsonSerialize) als Array/String erscheinen.
 */
class VorstossTest extends TestCase
{
    public function testJsonSerializeLiefertListenUndHerkunftsfraktion(): void
    {
        $v = new Vorstoss();
        $v->setZustaendigkeit(json_encode([['key' => 'a', 'name' => 'Anna']]));
        $v->setAnsprechpartner(json_encode([['key' => 'b', 'name' => 'Bob']]));
        $v->setHerkunftFraktion('Grüne');

        $j = $v->jsonSerialize();
        self::assertSame([['key' => 'a', 'name' => 'Anna']], $j['zustaendigkeit']);
        self::assertSame([['key' => 'b', 'name' => 'Bob']], $j['ansprechpartner']);
        self::assertSame('Grüne', $j['herkunftFraktion']);
    }

    public function testJsonSerializeDefaultsSindLeereListen(): void
    {
        $j = (new Vorstoss())->jsonSerialize();
        self::assertSame([], $j['zustaendigkeit']);
        self::assertSame([], $j['ansprechpartner']);
        self::assertSame('', $j['herkunftFraktion']);
    }

    /** Robustheit: eine alte Plain-String-Zuständigkeit erscheint als Ein-Personen-Liste. */
    public function testLegacyStringZustaendigkeitWirdZurListe(): void
    {
        $v = new Vorstoss();
        $v->setZustaendigkeit('Müller');
        $j = $v->jsonSerialize();
        self::assertSame([['key' => '', 'name' => 'Müller']], $j['zustaendigkeit']);
    }

    /**
     * Regression: Der Listen-Endpunkt gibt Entities direkt in der DataResponse
     * zurück, die sie via json_encode serialisiert. json_encode ruft
     * jsonSerialize() nur auf, wenn die Klasse JsonSerializable implementiert –
     * sonst gehen beim Laden ALLE Felder ausser der id verloren (leere Karten,
     * «beim Bearbeiten alles leer»).
     */
    public function testEntityIstJsonSerializable(): void
    {
        self::assertInstanceOf(\JsonSerializable::class, new Vorstoss());
    }

    public function testJsonEncodeLiefertAlleFelder(): void
    {
        $v = new Vorstoss();
        $v->setTitel('Test');
        $v->setHerkunft('fremde');
        $dekodiert = json_decode(json_encode($v), true);
        self::assertSame('Test', $dekodiert['titel'] ?? null);
        self::assertSame('fremde', $dekodiert['herkunft'] ?? null);
    }

    /** Feature: Vorstoss hat wie das Geschäft eine Priorität, Default undefiniert (leer). */
    public function testPrioritaetDefaultLeerUndSerialisiert(): void
    {
        $v = new Vorstoss();
        self::assertSame('', $v->jsonSerialize()['prioritaet']);
        $v->setPrioritaet('hoch');
        self::assertSame('hoch', $v->jsonSerialize()['prioritaet']);
    }

    /**
     * Regression: V27 fügte notizen als «LONGTEXT NULL» hinzu; bestehende Zeilen
     * und per insert (Feld nicht dirty) erzeugte Zeilen haben NULL in der Spalte.
     * Der QBMapper setzt diesen NULL-Wert über den Setter in die Property. Eine
     * non-nullable string-Property wirft dabei einen TypeError, der findAll()
     * abbricht → die gesamte Vorstoss-Liste bleibt leer («Keine Vorstösse
     * vorhanden» trotz vorhandener Zeilen). Ein NULL muss als leere Liste laden.
     */
    public function testNullNotizenAusDatenbankBrichtNichtUndWirdLeereListe(): void
    {
        $v = new Vorstoss();
        $v->setNotizen(null); // so übergibt der QBMapper einen DB-NULL-Wert
        self::assertSame([], $v->getNotizenArray());
        self::assertSame([], $v->jsonSerialize()['notizen']);
    }

    /**
     * Regression (gleiche Fehlklasse wie notizen): V25 ergänzte zustaendigkeit als
     * «TEXT NULL» und ansprechpartner als «LONGTEXT NULL». Ein Vorstoss ohne diese
     * Werte (z. B. ein eigener Vorstoss ohne Ansprechpartner) hat NULL in der
     * Spalte. Non-nullable Properties werfen dann beim Mappen einen TypeError und
     * killen die ganze Liste. Alle drei Listenfelder müssen NULL tolerieren.
     */
    public function testNullListenfelderAusDatenbankBrechenNicht(): void
    {
        $v = new Vorstoss();
        $v->setZustaendigkeit(null);
        $v->setAnsprechpartner(null);
        $v->setNotizen(null);
        $j = $v->jsonSerialize();
        self::assertSame([], $j['zustaendigkeit']);
        self::assertSame([], $j['ansprechpartner']);
        self::assertSame([], $j['notizen']);
    }

    /**
     * Regression: ALLE Textspalten der Tabelle sind nullable — jede Zeile mit
     * NULL (z.B. aus dem Dokument-Import, der nicht alle Felder setzte) muss
     * ohne TypeError laden und in der API mit Leerwerten erscheinen.
     */
    public function testAlleTextfelderTolerierenNullAusDerDatenbank(): void
    {
        $v = new Vorstoss();
        foreach ([
            'Titel', 'Art', 'Herkunft', 'Status', 'Prioritaet', 'Beschluss',
            'Zustaendigkeit', 'HerkunftFraktion', 'Ansprechpartner', 'Inhalt',
            'Dokument', 'Notizen', 'ErstelltAm', 'AktualisiertAm',
        ] as $feld) {
            $v->{'set' . $feld}(null);
        }
        $j = $v->jsonSerialize();
        self::assertSame('', $j['titel']);
        self::assertSame('', $j['inhalt']);
        self::assertSame('', $j['dokument']);
        self::assertSame([], $j['zustaendigkeit']);
        self::assertSame([], $j['notizen']);
    }

    /** Feature: Vorstoss hat Notizen wie das Geschäft; jsonSerialize liefert sie als Liste. */
    public function testNotizenDefaultLeereListeUndAddNotiz(): void
    {
        $v = new Vorstoss();
        self::assertSame([], $v->jsonSerialize()['notizen']);

        $v->addNotiz('Erste Notiz', 'amueller', 'Anna Müller');
        $notizen = $v->jsonSerialize()['notizen'];
        self::assertCount(1, $notizen);
        self::assertSame('Erste Notiz', $notizen[0]['text']);
        self::assertSame('amueller', $notizen[0]['autorUid']);
        self::assertSame('Anna Müller', $notizen[0]['autorName']);
        self::assertArrayHasKey('erstelltAm', $notizen[0]);
    }
}
