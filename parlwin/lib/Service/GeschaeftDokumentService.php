<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\GeschaeftDokument;
use OCA\ParliamentWinterthur\Db\GeschaeftDokumentMapper;
use OCP\Http\Client\IClientService;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;

/**
 * Holt die amtlichen Dokumente eines Geschäfts und legt ihren Inhalt ab (F121).
 *
 * Jedes Dokument wird einmal gelesen: Beim Abgleich lädt der Dienst das PDF,
 * bildet seine Prüfsumme und liest es nur dann neu, wenn die Datei sich
 * geändert hat. Damit steht der Text im Geschäft, die Suche findet ihn, und
 * der Abgleich bleibt auch bei tausend Geschäften bezahlbar.
 */
class GeschaeftDokumentService
{
    /**
     * Die Fassung des Lesers, vorne an der Prüfsumme.
     *
     * Die Prüfsumme entscheidet, ob ein Dokument neu gelesen wird. Ändert sich
     * der Leser — Tabellen, Zitate, die Rückseite des Vorstosses —, ist der
     * abgelegte Inhalt veraltet, obwohl die Datei dieselbe ist. Mit der Fassung
     * vor der Prüfsumme unterscheiden sich alle Einträge von selbst, und der
     * Bestand liest sich über die nächsten Läufe neu ein.
     */
    private const LESER_FASSUNG = 'v2';

    /** So gross darf ein Dokument höchstens sein, bevor es nur verzeichnet wird. */
    private const MAX_MB_STANDARD = 150;

    /**
     * So viele Dokumente liest ein Abgleich höchstens. Der Bestand zählt über
     * tausend Geschäfte mit mehreren PDF je Geschäft; würde der Abgleich sie
     * alle auf einmal holen, liefe er stundenlang. Verzeichnet werden sie
     * trotzdem sofort, und den Rest liest der Hintergrundauftrag nach.
     */
    private const PRO_LAUF_STANDARD = 25;

    /**
     * Ab diesem Anteil des Speicherlimits fängt ein Lauf kein weiteres Dokument
     * mehr an. Der Leser eines PDF entpackt dessen Ströme im Speicher, und der
     * Verbrauch eines Laufs wächst mit jedem Dokument: Am 24.09.2026 stand ein
     * Lauf nach 200 Dokumenten bei 627 MB von 1024 MB, und das nächste, grössere
     * Dokument beendete den ganzen Prozess mit «Allowed memory size exhausted» —
     * ein unabfangbarer Fehler, der auch alle noch offenen Dokumente kostet.
     */
    private const SPEICHER_GRENZE = 0.5;

    /**
     * So viel Speicher bekommt der Prozess, der ein einzelnes Dokument liest.
     * Er stirbt daran, der Lauf nicht.
     */
    private const LESER_SPEICHER_STANDARD = '4096M';

    /** So viele Zeichen fasst die Spalte `fehler`. */
    private const FEHLER_MAX_ZEICHEN = 500;

    /**
     * So lange liest ein Abgleich höchstens Dokumente, in Sekunden.
     *
     * Der Abgleich meldet dem Status regelmässig, dass er lebt; bleibt diese
     * Meldung länger als 15 Minuten aus, gilt er als abgestürzt und wird
     * abgebrochen. Ein einzelnes Dokument kann Minuten brauchen, und das
     * Kontingent von 25 Dokumenten reicht aus, um dieses Fenster zu
     * überschreiten — gemessen am 25.09.2026, als ein Abgleich nach 15 Minuten
     * mit «Kein Heartbeat innerhalb des zulässigen Zeitfensters» endete.
     */
    private const ZEIT_PRO_LAUF_STANDARD = 120;

    private int $gelesenImLauf = 0;

    private ?float $laufBegonnen = null;

    public function __construct(
        private readonly GeschaeftDokumentMapper $mapper,
        private readonly DokumentInhaltParser $parser,
        private readonly IClientService $clientService,
        private readonly ITempManager $tempManager,
        private readonly LoggerInterface $logger,
        private readonly ?SyncLockService $sperre = null,
    ) {
    }

    /**
     * Gleicht die Dokumente eines Geschäfts mit der Quelle ab.
     *
     * @param list<array<string, mixed>> $dokumente wie sie `ScraperService::extrahiereGeschaeftDokumenteAusHtml` liefert
     * @return int Zahl der neu gelesenen Dokumente
     */
    public function aktualisiere(int $geschaeftId, array $dokumente): int
    {
        $gelesen = 0;
        foreach ($dokumente as $daten) {
            $externId = trim((string) ($daten['externId'] ?? ''));
            $url = trim((string) ($daten['url'] ?? ''));
            if ($externId === '' || $url === '') {
                continue;
            }
            $bestand = $this->mapper->findByExternId($externId);
            $dokument = $bestand ?? new GeschaeftDokument();
            $dokument->setGeschaeftId($geschaeftId);
            $dokument->setExternId($externId);
            $dokument->setTitel((string) ($daten['titel'] ?? ''));
            $dokument->setKategorie((string) ($daten['kategorie'] ?? ''));
            $dokument->setDatum((string) ($daten['datum'] ?? ''));
            $dokument->setUrl($url);

            // Verzeichnet wird jedes Dokument sofort; gelesen nur, solange das
            // Kontingent dieses Laufs reicht. Gezählt wird der VERSUCH: Ein
            // Dokument, das zu gross ist oder nicht antwortet, kostet den Lauf
            // genauso viel Zeit wie ein gelesenes, und wer nur die Erfolge
            // zählt, hält den Abgleich bei einer Reihe solcher Dokumente
            // stundenlang auf.
            if ($this->darfLesen($dokument) && !$this->abgebrochen()) {
                ++$this->gelesenImLauf;
                if ($this->lies($dokument)) {
                    ++$gelesen;
                }
            }
            if ($bestand === null) {
                $this->mapper->insert($dokument);
            } else {
                $this->mapper->update($dokument);
            }
        }
        return $gelesen;
    }

    /**
     * Liest die Dokumente nach, die beim Abgleich nur verzeichnet wurden. Der
     * Hintergrundauftrag ruft das regelmässig auf, bis der Bestand steht.
     *
     * @return int Zahl der gelesenen Dokumente
     */
    public function leseOffene(?int $hoechstens = null): int
    {
        $hoechstens = max(1, $hoechstens ?? $this->proLauf());
        $gelesen = 0;
        foreach ($this->mapper->findOhneInhalt($hoechstens, self::LESER_FASSUNG . ':') as $dokument) {
            if (!$this->speicherReichtFuerWeitere()) {
                $this->logger->info(
                    'Parlament Winterthur: Lauf beendet, weil der Speicher zur Hälfte belegt ist;'
                    . ' der nächste Lauf fängt frisch an.',
                    ['gelesen' => $gelesen],
                );
                break;
            }
            if ($this->lies($dokument)) {
                ++$gelesen;
            }
            // Auch ein Fehlschlag wird geschrieben: Sein Grund steht am
            // Dokument, und die Prüfsumme verhindert, dass derselbe Abruf im
            // nächsten Lauf wieder ganz vorne steht.
            $this->mapper->update($dokument);
        }
        return $gelesen;
    }

    /**
     * Ob jemand den laufenden Abgleich abgebrochen hat.
     *
     * Ein Dokument zu lesen dauert bis zu einer Minute, und der Abbruch-Knopf
     * soll nicht auf das Kontingent des Laufs warten müssen.
     */
    private function abgebrochen(): bool
    {
        return $this->sperre !== null && $this->sperre->abbruchAngefordert();
    }

    /**
     * Ein Dokument wird gelesen, solange das Kontingent und die Zeit des Laufs
     * reichen — was übrig bleibt, liest der nächste Lauf oder der stündliche
     * Auftrag.
     */
    private function darfLesen(GeschaeftDokument $dokument): bool
    {
        return $this->gelesenImLauf < $this->proLauf() && $this->zeitReicht();
    }

    /**
     * Ob der Lauf noch innerhalb seiner Zeit liegt. Die Uhr beginnt beim ersten
     * Dokument.
     */
    private function zeitReicht(): bool
    {
        if ($this->laufBegonnen === null) {
            $this->laufBegonnen = $this->jetzt();

            return true;
        }

        return ($this->jetzt() - $this->laufBegonnen) < $this->zeitProLauf();
    }

    /**
     * Die laufende Zeit in Sekunden. Der Test lässt sie springen, statt zu
     * warten.
     */
    protected function jetzt(): float
    {
        return microtime(true);
    }

    private function zeitProLauf(): int
    {
        $wert = getenv('PARLWIN_DOKUMENT_ZEIT_PRO_LAUF');
        $zahl = is_string($wert) && $wert !== '' ? (int) $wert : self::ZEIT_PRO_LAUF_STANDARD;

        return max(1, $zahl);
    }

    private function proLauf(): int
    {
        $wert = getenv('PARLWIN_DOKUMENT_PRO_LAUF');
        $zahl = is_string($wert) && $wert !== '' ? (int) $wert : self::PRO_LAUF_STANDARD;
        return max(1, $zahl);
    }

    /**
     * Stellt die gescheiterten Dokumente wieder auf offen, damit der nächste
     * Lauf sie liest — nach einer angehobenen Grenze oder einem neuen Leser.
     *
     * @return int Zahl der Dokumente, die wieder offen sind
     */
    public function vergisseGescheiterte(): int
    {
        return $this->mapper->vergisseGescheiterte();
    }

    /**
     * Die Dokumente eines Geschäfts, wie sie die Anzeige braucht.
     *
     * @return GeschaeftDokument[]
     */
    public function zuGeschaeft(int $geschaeftId): array
    {
        return $this->mapper->findByGeschaeft($geschaeftId);
    }

    /**
     * Die Dokumente mehrerer Geschäfte, nach Geschäft gruppiert.
     *
     * @param int[] $geschaeftIds
     * @return array<int, GeschaeftDokument[]>
     */
    public function zuGeschaeften(array $geschaeftIds): array
    {
        return $this->mapper->findByGeschaefte($geschaeftIds);
    }

    /**
     * Die Geschäfte, in deren Dokumenten der Begriff steht.
     *
     * @return int[]
     */
    public function geschaeftIdsMitText(string $begriff): array
    {
        return $this->mapper->findGeschaeftIdsMitText($begriff);
    }

    /**
     * Lädt das PDF und liest seinen Inhalt, sofern es sich geändert hat.
     *
     * @return bool true, wenn der Inhalt neu gelesen wurde
     */
    private function lies(GeschaeftDokument $dokument): bool
    {
        $pfad = $this->tempManager->getTemporaryFile('.pdf');
        if ($pfad === false) {
            $dokument->setFehler('Kein Platz für die Zwischenablage des Dokuments');
            return false;
        }
        try {
            $this->lade((string) $dokument->getUrl(), $pfad);
            $hash = self::LESER_FASSUNG . ':' . hash_file('sha256', $pfad);
            if ($hash !== '' && $hash === $dokument->getQuelleHash() && ($dokument->getMarkdown() ?? '') !== '') {
                return false;
            }
            $groesseMb = filesize($pfad) / 1024 / 1024;
            if ($groesseMb > $this->maxMb()) {
                $dokument->setQuelleHash($hash);
                $dokument->setFehler(sprintf(
                    'Das Dokument ist %.1f MB gross und wird nur verzeichnet; die Grenze steht in PARLWIN_DOKUMENT_MAX_MB (%d MB).',
                    $groesseMb,
                    $this->maxMb(),
                ));
                return false;
            }
            $abschnitte = $this->abschnitteAusEigenemProzess($pfad);
            $dokument->setStruktur((string) json_encode($abschnitte, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $dokument->setMarkdown($this->parser->markdown($abschnitte));
            $dokument->setVolltext($this->parser->volltext($abschnitte));
            $dokument->setSeiten($this->seiten($abschnitte));
            $dokument->setQuelleHash($hash);
            $dokument->setGelesenAm((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
            $dokument->setFehler('');
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Parlament Winterthur: Dokument nicht lesbar: ' . $e->getMessage(),
                ['url' => $dokument->getUrl(), 'exception' => $e],
            );
            // Der Fehler steht am Dokument, damit die Anzeige sagen kann, was
            // fehlt — ein stilles Auslassen sähe aus wie ein leeres Dokument.
            // Die Spalte fasst 500 Zeichen; eine längere Meldung würde den
            // ganzen Lauf mit «Data too long» beenden.
            $dokument->setFehler(mb_substr($e->getMessage(), 0, self::FEHLER_MAX_ZEICHEN));
            return false;
        } finally {
            $this->tempManager->clean();
            // Der Leser lässt grosse Zwischenstrukturen zurück; ohne diesen
            // Schritt wächst der Verbrauch eines Laufs über die Dokumente hinweg.
            gc_collect_cycles();
        }
    }

    /**
     * Liest ein PDF in einem eigenen Prozess und gibt seine Abschnitte zurück.
     *
     * Das Entpacken eines PDF braucht beliebig viel Speicher, und ein
     * überschrittenes Limit beendet den Prozess ohne abfangbaren Fehler: Am
     * 24.09.2026 kostete ein einziges Dokument den ganzen Lesevorgang samt allen
     * noch offenen Dokumenten. Stirbt der eigene Prozess, steht der Grund am
     * Dokument und der Lauf geht weiter.
     *
     * @return list<array<string, mixed>>
     */
    protected function abschnitteAusEigenemProzess(string $pfad): array
    {
        $skript = \dirname(__DIR__, 2) . '/bin/dokument-lesen.php';
        $prozess = proc_open(
            [PHP_BINARY, '-d', 'memory_limit=' . $this->leserSpeicher(), $skript, $pfad],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $roehren,
        );
        if (!\is_resource($prozess)) {
            throw new \RuntimeException('Der Leser liess sich nicht starten');
        }
        $ausgabe = (string) stream_get_contents($roehren[1]);
        $fehler = trim((string) stream_get_contents($roehren[2]));
        fclose($roehren[1]);
        fclose($roehren[2]);
        $code = proc_close($prozess);

        if ($code !== 0) {
            // Die technische Spur steht im Protokoll; am Dokument steht ein Satz,
            // den der Leser der Anwendung versteht, und er passt in seine Spalte.
            $this->logger->warning(
                'Parlament Winterthur: Leser eines Dokuments gescheitert',
                ['pfad' => $pfad, 'code' => $code, 'ausgabe' => $fehler],
            );
            throw new \RuntimeException(
                str_contains($fehler, 'memory size')
                    ? 'Das Dokument liess sich nicht lesen: Es braucht mehr als '
                      . $this->leserSpeicher() . ' Speicher (PARLWIN_DOKUMENT_LESER_SPEICHER).'
                    : 'Das Dokument liess sich nicht lesen: '
                      . mb_substr(trim(explode("\n", $fehler)[0] ?: 'unbekannter Grund'), 0, 200),
            );
        }
        $abschnitte = json_decode($ausgabe, true);
        if (!\is_array($abschnitte)) {
            throw new \RuntimeException('Der Leser lieferte keine verwertbare Antwort');
        }

        return $abschnitte;
    }

    /**
     * So viel Speicher bekommt der Leser eines einzelnen Dokuments.
     */
    private function leserSpeicher(): string
    {
        $wert = getenv('PARLWIN_DOKUMENT_LESER_SPEICHER');

        return is_string($wert) && $wert !== '' ? $wert : self::LESER_SPEICHER_STANDARD;
    }

    /**
     * Das Speicherlimit dieses Prozesses in Bytes, 0 wenn keines gesetzt ist.
     * Der Test setzt hier eine feste Zahl ein, statt am laufenden Prozess zu
     * drehen.
     */
    protected function speicherLimit(): int
    {
        $wert = trim((string) ini_get('memory_limit'));
        if ($wert === '' || $wert === '-1') {
            return 0;
        }
        $faktor = match (strtolower(substr($wert, -1))) {
            'g' => 1024 * 1024 * 1024,
            'm' => 1024 * 1024,
            'k' => 1024,
            default => 1,
        };
        $zahl = (int) $wert;

        return $faktor === 1 ? $zahl : $zahl * $faktor;
    }

    /**
     * Ob der Lauf noch ein weiteres Dokument anfangen darf.
     */
    private function speicherReichtFuerWeitere(): bool
    {
        $limit = $this->speicherLimit();

        return $limit === 0 || memory_get_usage(true) < (int) ($limit * self::SPEICHER_GRENZE);
    }

    private function lade(string $url, string $ziel): void
    {
        $client = $this->clientService->newClient();
        $antwort = $client->get($url, [
            'timeout' => 120,
            'connect_timeout' => 8,
            'sink' => $ziel,
            'headers' => [
                'User-Agent' => 'Nextcloud/ParliamentWinterthur (+https://github.com/mwaeckerlin/parliament-winterthur-tool)',
                'Accept' => 'application/pdf,*/*',
                'Accept-Language' => 'de-CH,de;q=0.9',
            ],
        ]);
        // Schreibt der Client nicht selbst in die Datei, steht der Inhalt im Rumpf.
        if (!file_exists($ziel) || filesize($ziel) === 0) {
            file_put_contents($ziel, $antwort->getBody());
        }
    }

    /**
     * @param list<array<string, mixed>> $abschnitte
     */
    private function seiten(array $abschnitte): int
    {
        $seiten = 0;
        foreach ($abschnitte as $abschnitt) {
            $seiten = max($seiten, (int) ($abschnitt['seite'] ?? 0));
        }
        return $seiten;
    }

    private function maxMb(): int
    {
        $wert = getenv('PARLWIN_DOKUMENT_MAX_MB');
        $zahl = is_string($wert) && $wert !== '' ? (int) $wert : self::MAX_MB_STANDARD;
        return max(1, $zahl);
    }
}
