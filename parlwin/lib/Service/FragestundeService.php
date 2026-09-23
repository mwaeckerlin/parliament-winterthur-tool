<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\Frage;
use OCA\ParliamentWinterthur\Db\FrageMapper;
use OCA\ParliamentWinterthur\Db\Fragestunde;
use OCA\ParliamentWinterthur\Db\FragestundeMapper;
use OCA\ParliamentWinterthur\Db\GeschaeftMapper;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserSession;

/**
 * Die Fragestunde des Stadtparlaments (F114): Die Fraktionsmitglieder sammeln
 * hier ihre Fragen, die Fraktion bespricht sie in ihrer Sitzung und teilt sie
 * einander zum Einreichen zu.
 *
 * Zwei Regeln des Parlaments prägen den Ablauf (Art. 103 Abs. 2 der
 * Organisationsverordnung des Stadtparlaments):
 *
 *  - «Fragen müssen bis spätestens am Donnerstag vor der Fragestunde schriftlich
 *    beim Parlamentsdienst eingereicht werden.»
 *  - «Die Fragen dürfen nicht mehr als 1'000 Zeichen umfassen.»
 *
 * Dazu kommt die Regel des Rats, dass jedes Mitglied nur EINE Frage einreicht;
 * deshalb kann eine Frage jemand anderem zum Einreichen zugeteilt werden als dem,
 * der sie eingebracht hat.
 */
class FragestundeService {
    public function __construct(
        private readonly FragestundeMapper $fragestunden,
        private readonly FrageMapper $fragen,
        private readonly MitgliedMapper $mitglieder,
        private readonly GeschaeftMapper $geschaefte,
        private readonly IUserSession $userSession,
        private readonly NotizService $notizen,
    ) {
    }

    /**
     * Der Stand der Ansicht: die FRAGEN als Hauptsache und die Fragestunden
     * daneben.
     *
     * Die Fraktion sammelt jederzeit Fragen, auch wenn keine Fragestunde
     * angesetzt ist, und nutzt sie später in irgendeiner. Jede Frage nennt
     * darum ihre Fragestunde, sofern sie einer zugeteilt ist, sonst null.
     *
     * @return array{fragestunden: list<array<string, mixed>>, fragen: list<array<string, mixed>>}
     */
    public function alle(): array {
        $fragestunden = [];
        $nachId = [];
        foreach ($this->fragestunden->findAll() as $fragestunde) {
            $fragestunden[] = $this->mitFragen($fragestunde);
            $nachId[(int) $fragestunde->getId()] = [
                'id' => (int) $fragestunde->getId(),
                'titel' => (string) $fragestunde->getTitel(),
                'datum' => (string) $fragestunde->getDatum(),
                'frist' => (string) $fragestunde->getFrist(),
            ];
        }

        $fragen = [];
        foreach ($this->fragen->findAlle() as $frage) {
            $eintrag = $frage->jsonSerialize();
            $eintrag['notizen'] = $this->notizen->liste('frage', (int) $frage->getId());
            $eintrag['fragestunde'] = $nachId[(int) $frage->getFragestundeId()] ?? null;
            $fragen[] = $eintrag;
        }

        return ['fragestunden' => $fragestunden, 'fragen' => $fragen];
    }

    /**
     * Eine Fragestunde mit ihren Fragen, den Notizen je Frage und den Hinweisen
     * auf mehrfach zugeteilte Einreicher.
     *
     * @return array<string, mixed>
     */
    public function mitFragen(Fragestunde $fragestunde): array {
        $daten = $fragestunde->jsonSerialize();
        $daten['geschaeft'] = $this->geschaeftDazu($fragestunde);
        $fragen = [];
        $jeEinreicher = [];
        foreach ($this->fragen->findByFragestunde((int) $fragestunde->getId()) as $frage) {
            $eintrag = $frage->jsonSerialize();
            $eintrag['notizen'] = $this->notizen->liste('frage', (int) $frage->getId());
            $fragen[] = $eintrag;
            $key = (string) $frage->getEinreicherKey();
            if ($key !== '') {
                $jeEinreicher[$key] = ($jeEinreicher[$key] ?? 0) + 1;
            }
        }
        $daten['fragen'] = $fragen;
        // Jedes Mitglied reicht nur eine Frage ein: Wer mehrere zugeteilt hat,
        // steht hier — die Fraktion muss umverteilen. Die Schlüssel bleiben
        // Zeichenketten: PHP macht aus einem Array-Schlüssel «12» sonst die Zahl
        // 12, und die Ansicht vergleicht sie mit dem Schlüssel der Frage.
        $daten['mehrfachZugeteilt'] = array_map('strval', array_keys(array_filter(
            $jeEinreicher,
            static fn (int $anzahl): bool => $anzahl > 1
        )));
        return $daten;
    }

    /** @return array<string, mixed> */
    public function eine(int $id): array {
        return $this->mitFragen($this->fragestunden->find($id));
    }

    /**
     * Das Geschäft des Parlaments zu dieser Fragestunde, sofern es schon
     * abgerufen ist: Das Parlament führt jede Fragestunde als eigenes Geschäft
     * («Fragestunde vom 2. März 2026»), veröffentlicht es aber erst kurz vorher —
     * die Fraktion sammelt ihre Fragen längst davor. Deshalb wird die Verknüpfung
     * nachgeholt, sobald das Geschäft da ist, und dann festgehalten.
     *
     * @return array{id:int, nummer:string, titel:string, url:string}|null
     */
    private function geschaeftDazu(Fragestunde $fragestunde): ?array {
        $id = (int) $fragestunde->getGeschaeftId();
        $geschaeft = null;
        if ($id > 0) {
            try {
                $geschaeft = $this->geschaefte->find($id);
            } catch (DoesNotExistException) {
                $geschaeft = null;
            }
        }
        if ($geschaeft === null && (string) $fragestunde->getDatum() !== '') {
            $geschaeft = $this->geschaefte->findeFragestunde($this->datumLang((string) $fragestunde->getDatum()));
            if ($geschaeft !== null) {
                $fragestunde->setGeschaeftId((int) $geschaeft->getId());
                $fragestunde->setAktualisiertAm($this->jetzt());
                $this->fragestunden->update($fragestunde);
            }
        }
        if ($geschaeft === null) {
            return null;
        }
        return [
            'id' => (int) $geschaeft->getId(),
            'nummer' => (string) $geschaeft->getNummer(),
            'titel' => (string) $geschaeft->getTitel(),
            'url' => (string) $geschaeft->getUrl(),
        ];
    }

    /**
     * Legt eine Fragestunde an. Titel und Frist ergeben sich aus dem Datum, lassen
     * sich aber setzen.
     *
     * @param array<string, mixed> $daten
     */
    public function erstelleFragestunde(array $daten): Fragestunde {
        $datum = trim((string) ($daten['datum'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) !== 1) {
            throw new \InvalidArgumentException('Das Datum der Fragestunde fehlt (Format JJJJ-MM-TT).');
        }
        $vorhanden = $this->fragestunden->findByDatum($datum);
        if ($vorhanden !== null) {
            return $vorhanden;
        }
        $fragestunde = new Fragestunde();
        $fragestunde->setDatum($datum);
        $titel = trim((string) ($daten['titel'] ?? ''));
        $fragestunde->setTitel($titel !== '' ? $titel : 'Fragestunde vom ' . $this->datumLang($datum));
        $frist = trim((string) ($daten['frist'] ?? ''));
        $fragestunde->setFrist($frist !== '' ? $frist : $this->donnerstagDavor($datum));
        $fragestunde->setGeschaeftId((int) ($daten['geschaeftId'] ?? 0));
        $jetzt = $this->jetzt();
        $fragestunde->setErstelltAm($jetzt);
        $fragestunde->setAktualisiertAm($jetzt);
        return $this->fragestunden->insert($fragestunde);
    }

    /**
     * @param array<string, mixed> $daten
     * @throws DoesNotExistException
     */
    public function aktualisiereFragestunde(int $id, array $daten): Fragestunde {
        $fragestunde = $this->fragestunden->find($id);
        if (array_key_exists('datum', $daten)) {
            $datum = trim((string) $daten['datum']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum) !== 1) {
                throw new \InvalidArgumentException('Das Datum der Fragestunde fehlt (Format JJJJ-MM-TT).');
            }
            $fragestunde->setDatum($datum);
            if (!array_key_exists('frist', $daten)) {
                $fragestunde->setFrist($this->donnerstagDavor($datum));
            }
        }
        foreach (['titel' => 'setTitel', 'frist' => 'setFrist'] as $feld => $setter) {
            if (array_key_exists($feld, $daten)) {
                $fragestunde->$setter(trim((string) $daten[$feld]));
            }
        }
        if (array_key_exists('geschaeftId', $daten)) {
            $fragestunde->setGeschaeftId((int) $daten['geschaeftId']);
        }
        $fragestunde->setAktualisiertAm($this->jetzt());
        return $this->fragestunden->update($fragestunde);
    }

    /**
     * Trägt eine Frage ein. Urheber ist standardmässig das Mitglied, das gerade
     * angemeldet ist.
     *
     * Ohne Fragestunde (`$fragestundeId === 0`) wird die Frage gesammelt: Die
     * Fraktion trägt ein, was ihr auffällt, und teilt sie später einer
     * Fragestunde zu.
     *
     * @param array<string, mixed> $daten
     */
    public function erstelleFrage(int $fragestundeId, array $daten): Frage {
        if ($fragestundeId > 0) {
            $this->fragestunden->find($fragestundeId);
        }
        $frage = new Frage();
        $frage->setFragestundeId(max(0, $fragestundeId));
        $frage->setStatus('neu');
        $urheber = $this->person($daten['urheber'] ?? null) ?? $this->angemeldetesMitglied();
        $frage->setUrheberKey($urheber['key']);
        $frage->setUrheberName($urheber['name']);
        $frage->setFrage($this->gepruefteFrage((string) ($daten['frage'] ?? '')));
        $frage->setKommentar(trim((string) ($daten['kommentar'] ?? '')));
        $einreicher = $this->person($daten['einreicher'] ?? null) ?? ['key' => '', 'name' => ''];
        $frage->setEinreicherKey($einreicher['key']);
        $frage->setEinreicherName($einreicher['name']);
        $jetzt = $this->jetzt();
        $frage->setErstelltAm($jetzt);
        $frage->setAktualisiertAm($jetzt);
        return $this->fragen->insert($frage);
    }

    /**
     * @param array<string, mixed> $daten
     * @throws DoesNotExistException
     */
    public function aktualisiereFrage(int $id, array $daten): Frage {
        $frage = $this->fragen->find($id);
        if (array_key_exists('frage', $daten)) {
            $frage->setFrage($this->gepruefteFrage((string) $daten['frage']));
        }
        if (array_key_exists('kommentar', $daten)) {
            $frage->setKommentar(trim((string) $daten['kommentar']));
        }
        if (array_key_exists('status', $daten)) {
            $frage->setStatus($this->gepruefterStatus((string) $daten['status']));
        }
        // Zuteilen und wieder lösen: 0 heisst «noch keiner Fragestunde
        // zugeteilt». Eine angegebene Fragestunde muss es geben.
        if (array_key_exists('fragestundeId', $daten)) {
            $ziel = max(0, (int) $daten['fragestundeId']);
            if ($ziel > 0) {
                $this->fragestunden->find($ziel);
            }
            $frage->setFragestundeId($ziel);
        }
        foreach (['urheber' => 'Urheber', 'einreicher' => 'Einreicher'] as $feld => $teil) {
            if (!array_key_exists($feld, $daten)) {
                continue;
            }
            $person = $this->person($daten[$feld]) ?? ['key' => '', 'name' => ''];
            $frage->{'set' . $teil . 'Key'}($person['key']);
            $frage->{'set' . $teil . 'Name'}($person['name']);
        }
        $frage->setAktualisiertAm($this->jetzt());
        return $this->fragen->update($frage);
    }

    /** @throws DoesNotExistException */
    public function loescheFrage(int $id): void {
        $frage = $this->fragen->find($id);
        $frage->setGeloescht(true);
        $frage->setAktualisiertAm($this->jetzt());
        $this->fragen->update($frage);
    }

    /**
     * Der Donnerstag vor der Fragestunde — die Frist des Parlamentsdienstes. Fällt
     * die Fragestunde selbst auf einen Donnerstag, gilt der Donnerstag der Woche
     * davor.
     */
    public function donnerstagDavor(string $datum): string {
        $tag = new \DateTimeImmutable($datum);
        do {
            $tag = $tag->modify('-1 day');
        } while ((int) $tag->format('N') !== 4);
        return $tag->format('Y-m-d');
    }

    /** Prüft die Frage gegen die Grenze von 1'000 Zeichen der Organisationsverordnung. */
    private function gepruefteFrage(string $roh): string {
        $frage = trim($roh);
        if (mb_strlen($frage) > Frage::MAX_ZEICHEN) {
            throw new \InvalidArgumentException(sprintf(
                'Die Frage umfasst %d Zeichen. Der Parlamentsdienst nimmt höchstens %d Zeichen entgegen '
                . '(Art. 103 Abs. 2 der Organisationsverordnung) — bitte kürzen.',
                mb_strlen($frage),
                Frage::MAX_ZEICHEN
            ));
        }
        return $frage;
    }

    private function gepruefterStatus(string $roh): string {
        $status = trim($roh);
        $erlaubt = ['neu', 'besprochen', 'eingereicht', 'zurueckgezogen'];
        if (!\in_array($status, $erlaubt, true)) {
            throw new \InvalidArgumentException(
                'Unbekannter Status «' . $status . '»; möglich sind: ' . implode(', ', $erlaubt) . '.'
            );
        }
        return $status;
    }

    /**
     * Liest eine Person aus den Daten («{key, name}» oder ein blosser Name).
     *
     * @return array{key: string, name: string}|null
     */
    private function person(mixed $roh): ?array {
        if (is_array($roh)) {
            $key = trim((string) ($roh['key'] ?? ''));
            $name = trim((string) ($roh['name'] ?? ''));
            // Leer heisst «keine Person»: Beim Eintragen greift dann das
            // angemeldete Mitglied, beim Ändern wird die Zuteilung aufgehoben.
            return $key === '' && $name === '' ? null : ['key' => $key, 'name' => $name];
        }
        $name = trim((string) ($roh ?? ''));
        return $name === '' ? null : ['key' => '', 'name' => $name];
    }

    /**
     * Das Fraktionsmitglied, das gerade angemeldet ist — oder der Benutzername,
     * wenn zu ihm kein Mitglied hinterlegt ist.
     *
     * @return array{key: string, name: string}
     */
    private function angemeldetesMitglied(): array {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return ['key' => '', 'name' => ''];
        }
        $mitglied = $this->mitglieder->findByNextcloudUid($user->getUID());
        if ($mitglied === null) {
            return ['key' => $user->getUID(), 'name' => $user->getDisplayName()];
        }
        return [
            'key' => (string) $mitglied->getId(),
            'name' => trim($mitglied->getVorname() . ' ' . $mitglied->getName()),
        ];
    }

    /** «2026-03-02» → «2. März 2026». */
    private function datumLang(string $datum): string {
        $monate = [
            1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
            'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
        ];
        $tag = new \DateTimeImmutable($datum);
        return (int) $tag->format('j') . '. ' . $monate[(int) $tag->format('n')] . ' ' . $tag->format('Y');
    }

    private function jetzt(): string {
        return (new \DateTime())->format('Y-m-d H:i:s');
    }
}
