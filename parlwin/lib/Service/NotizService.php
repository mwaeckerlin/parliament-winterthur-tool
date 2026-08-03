<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\GeschaeftAktion;
use OCA\ParliamentWinterthur\Db\GeschaeftAktionMapper;
use OCA\ParliamentWinterthur\Db\NotizRevision;
use OCA\ParliamentWinterthur\Db\NotizRevisionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserSession;

/**
 * Der EINE, geteilte Notiz-Code für alle Objektarten (Geschäft und Vorstoss).
 *
 * Notizen führen eine Versions-History (wie git): beim Bearbeiten wird der
 * bisherige Text als Revision archiviert. Löschen ist ein Soft-Delete (nur ein
 * Flag) mit Undo. Alles liegt in pw_geschaeft_aktionen (aktion_typ = «notiz»),
 * getrennt nach objekt_typ. Weder Geschäft noch Vorstoss haben eigenen
 * Notiz-Code — beide rufen ausschliesslich diesen Service.
 */
class NotizService
{
    public function __construct(
        private readonly GeschaeftAktionMapper $aktionMapper,
        private readonly NotizRevisionMapper $revisionMapper,
        private readonly IUserSession $userSession,
    ) {
    }

    /**
     * Alle Notizen eines Objekts (aktive und gelöschte), neueste zuerst.
     *
     * @return array<int, array<string, mixed>>
     */
    public function liste(string $objektTyp, int $objektId, string $kategorie = 'notiz'): array
    {
        return array_map(
            fn(GeschaeftAktion $a): array => $this->mapNotiz($a),
            $this->aktionMapper->findNotizen($objektTyp, $objektId, $kategorie)
        );
    }

    /**
     * Notizen mehrerer Objekte in EINER Abfrage, gruppiert nach Objekt-ID.
     *
     * @param int[] $objektIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function listeGruppiert(string $objektTyp, array $objektIds, string $kategorie = 'notiz'): array
    {
        $gruppen = [];
        foreach ($objektIds as $id) {
            $gruppen[$id] = [];
        }
        foreach ($this->aktionMapper->findNotizenFuerObjekte($objektTyp, $objektIds, $kategorie) as $aktion) {
            $gruppen[$aktion->getGeschaeftId()][] = $this->mapNotiz($aktion);
        }
        return $gruppen;
    }

    /**
     * @return array<string, mixed>
     */
    public function hinzufuegen(string $objektTyp, int $objektId, string $text, string $kategorie = 'notiz'): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Notiztext darf nicht leer sein');
        }

        $user = $this->userSession->getUser();
        $uid = $user?->getUID() ?? '';
        $name = ($user !== null && method_exists($user, 'getDisplayName'))
            ? (string) $user->getDisplayName()
            : $uid;

        $aktion = new GeschaeftAktion();
        $aktion->setObjektTyp($objektTyp);
        $aktion->setGeschaeftId($objektId);
        $aktion->setAktionTyp($kategorie);
        $aktion->setAktionCode('');
        $aktion->setTitel(ucfirst($kategorie));
        $aktion->setText($text);
        $aktion->setEntscheidGueltig(false);
        $aktion->setAutorUid($uid);
        $aktion->setAutorName($name);
        $aktion->setErstelltAm((new \DateTime())->format('Y-m-d H:i:s'));
        $aktion->setGeloescht(false);

        return $this->mapNotiz($this->aktionMapper->insert($aktion));
    }

    /**
     * Jede bewusste Speicherung (✓) legt die bisherige Fassung als Version an;
     * unveränderter Text erzeugt keine Version.
     *
     * @return array<string, mixed>
     */
    public function aktualisieren(
        string $objektTyp,
        int $objektId,
        int $aktionId,
        string $text,
        string $kategorie = 'notiz'
    ): array {
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('Notiztext darf nicht leer sein');
        }

        $aktion = $this->eigeneNotiz($objektTyp, $objektId, $aktionId, 'bearbeiten', $kategorie);

        // Bisherige Fassung archivieren, bevor der neue Text die Notiz überschreibt.
        // Unveränderter Text erzeugt keine Revision.
        $bisher = $aktion->getText();
        if ($bisher !== '' && $bisher !== $text) {
            $revision = new NotizRevision();
            $revision->setAktionId($aktion->getId());
            $revision->setText($bisher);
            $revision->setAutorUid($aktion->getAutorUid());
            $revision->setAutorName($aktion->getAutorName());
            $revision->setErstelltAm((new \DateTimeImmutable())->format(DATE_ATOM));
            $this->revisionMapper->insert($revision);
        }

        $aktion->setText($text);
        $this->aktionMapper->update($aktion);
        return $this->mapNotiz($aktion);
    }

    /**
     * Löschen entfernt die Notiz NICHT aus der Datenbank: es wird nur das
     * Lösch-Flag gesetzt. Text und History bleiben erhalten (Undo möglich).
     */
    public function loeschen(string $objektTyp, int $objektId, int $aktionId, string $kategorie = 'notiz'): void
    {
        $aktion = $this->eigeneNotiz($objektTyp, $objektId, $aktionId, 'löschen', $kategorie);
        $aktion->setGeloescht(true);
        $this->aktionMapper->update($aktion);
    }

    /**
     * Macht das Löschen rückgängig — die Notiz erscheint samt History wieder.
     *
     * @return array<string, mixed>
     */
    public function wiederherstellen(string $objektTyp, int $objektId, int $aktionId, string $kategorie = 'notiz'): array
    {
        $aktion = $this->eigeneNotiz($objektTyp, $objektId, $aktionId, 'wiederherstellen', $kategorie);
        $aktion->setGeloescht(false);
        $this->aktionMapper->update($aktion);
        return $this->mapNotiz($aktion);
    }

    /**
     * Archivierte Vorversionen einer Notiz, älteste zuerst.
     *
     * @return array<int, array<string, mixed>>
     */
    public function revisionen(string $objektTyp, int $objektId, int $aktionId, string $kategorie = 'notiz'): array
    {
        $this->eigeneNotiz($objektTyp, $objektId, $aktionId, 'einsehen', $kategorie);

        return array_map(
            static fn(NotizRevision $r): array => [
                'id' => $r->getId(),
                'aktionId' => $r->getAktionId(),
                'text' => $r->getText(),
                'autorUid' => $r->getAutorUid(),
                'autorName' => $r->getAutorName(),
                'erstelltAm' => $r->getErstelltAm(),
            ],
            $this->revisionMapper->findByAktion($aktionId)
        );
    }

    /**
     * Lädt eine Notiz und stellt sicher, dass sie zum Objekt gehört und dem
     * aktuellen Nutzer. Nur der Autor darf seine Notizen verändern.
     */
    private function eigeneNotiz(string $objektTyp, int $objektId, int $aktionId, string $aktion_, string $kategorie = 'notiz'): GeschaeftAktion
    {
        try {
            $aktion = $this->aktionMapper->findById($aktionId);
        } catch (DoesNotExistException) {
            throw new \InvalidArgumentException('Notiz nicht gefunden');
        }

        if (
            $aktion->getObjektTyp() !== $objektTyp
            || $aktion->getGeschaeftId() !== $objektId
            || $aktion->getAktionTyp() !== $kategorie
        ) {
            throw new \InvalidArgumentException('Notiz gehört nicht zu diesem Objekt');
        }

        $uid = $this->userSession->getUser()?->getUID() ?? '';
        if ($aktion->getAutorUid() !== $uid) {
            throw new \RuntimeException('Nur der Autor darf eigene Notizen ' . $aktion_);
        }

        return $aktion;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapNotiz(GeschaeftAktion $aktion): array
    {
        return [
            'id' => $aktion->getId(),
            'geschaeftId' => $aktion->getGeschaeftId(),
            'aktionTyp' => $aktion->getAktionTyp(),
            'aktionCode' => $aktion->getAktionCode(),
            'titel' => $aktion->getTitel(),
            'text' => $aktion->getText(),
            'entscheidGueltig' => $aktion->getEntscheidGueltig(),
            'autorUid' => $aktion->getAutorUid(),
            'autorName' => $aktion->getAutorName(),
            'erstelltAm' => $aktion->getErstelltAm(),
            'geloescht' => $aktion->getGeloescht(),
        ];
    }
}
