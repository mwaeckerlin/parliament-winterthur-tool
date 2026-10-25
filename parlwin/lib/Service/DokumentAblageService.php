<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUserSession;

/**
 * Dateien eines Objekts im Fraktionsordner: auflisten, neu anlegen, hochladen.
 *
 * Die Ablage folgt überall demselben Schema «Fraktion/<NN>_<Bereich>/<Jahr>».
 * Geschäft, Vorstoss und Sitzung führen diesen Code heute je für sich im
 * eigenen Controller; der Budgetantrag nutzt diesen Dienst.
 */
class DokumentAblageService
{
    public function __construct(
        private readonly IRootFolder $rootFolder,
        private readonly IUserSession $userSession,
    ) {
    }

    /**
     * Die Dateien eines Ordners, Name und Verknüpfung; ein Präfix grenzt auf die
     * Dateien EINES Objekts im gemeinsamen Jahresordner ein.
     *
     * @return array<int, array<string, mixed>>
     */
    public function liste(string $ordnerPfad, string $praefix = ''): array
    {
        $ordner = $this->ordner($ordnerPfad);
        if ($ordner === null) {
            return [];
        }
        $eintraege = [];
        foreach ($ordner->getDirectoryListing() as $node) {
            $name = $node->getName();
            if ($praefix !== '' && !str_starts_with($name, $praefix)) {
                continue;
            }
            $eintraege[] = [
                'name' => $name,
                'pfad' => $ordnerPfad . '/' . $name,
                'groesse' => $node->getSize(),
                'geaendert' => $node->getMTime(),
                'id' => $node->getId(),
            ];
        }
        usort($eintraege, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));
        return $eintraege;
    }

    /**
     * Legt eine leere Datei an und gibt ihren Eintrag zurück. Ein vorhandener
     * Name bleibt unangetastet: die bestehende Datei wird zurückgegeben.
     *
     * @return array<string, mixed>
     */
    public function anlegen(string $ordnerPfad, string $name, string $extension, string $inhalt = ''): array
    {
        $dateiName = $this->sauberName($name) . '.' . ltrim($extension, '.');
        $ordner = $this->ordnerAnlegen($ordnerPfad);
        $zielPfad = $ordnerPfad . '/' . $dateiName;
        $userFolder = $this->userFolder();
        $node = $userFolder->nodeExists($zielPfad)
            ? $userFolder->get($zielPfad)
            : $ordner->newFile($dateiName, $inhalt);
        return [
            'name' => $node->getName(),
            'pfad' => $zielPfad,
            'id' => $node->getId(),
        ];
    }

    /**
     * Legt eine hochgeladene Datei ab. Eine bestehende Datei gleichen Namens
     * wird überschrieben — derselbe Name meint dieselbe Datei.
     *
     * @param array<string, mixed> $datei die Angaben aus `getUploadedFile`
     * @return array<string, mixed>
     */
    public function hochladen(string $ordnerPfad, array $datei, string $praefix = ''): array
    {
        $tmp = (string) ($datei['tmp_name'] ?? '');
        if ($tmp === '' || !is_readable($tmp)) {
            throw new \InvalidArgumentException('Keine Datei hochgeladen');
        }
        $name = $praefix . basename((string) ($datei['name'] ?? 'upload'));
        $name = (string) preg_replace('/[\/\\\\]/', '_', $name);
        $ordner = $this->ordnerAnlegen($ordnerPfad);
        $zielPfad = $ordnerPfad . '/' . $name;
        $userFolder = $this->userFolder();
        $inhalt = (string) file_get_contents($tmp);
        $node = $userFolder->nodeExists($zielPfad)
            ? $userFolder->get($zielPfad)
            : $ordner->newFile($name);
        if (method_exists($node, 'putContent')) {
            $node->putContent($inhalt);
        }
        return [
            'name' => $node->getName(),
            'pfad' => $zielPfad,
            'id' => $node->getId(),
        ];
    }

    /**
     * Schreibt eine Datei mit dem gegebenen Inhalt; eine bestehende Datei
     * gleichen Namens wird überschrieben. Für einen Export, der den heutigen
     * Stand zeigen muss (F138) — «anlegen» lässt eine bestehende Datei
     * absichtlich unangetastet und wäre dafür falsch.
     *
     * @return array<string, mixed>
     */
    public function schreiben(string $ordnerPfad, string $dateiName, string $inhalt): array
    {
        // Der Name darf den Ordner nicht verlassen: Trennzeichen werden ersetzt,
        // und ein Name, der nur aus Punkten besteht, zeigt eine Ebene höher.
        $name = (string) preg_replace('/[\/\\\\]/', '_', trim($dateiName));
        if ($name === '' || preg_match('/^\.+$/', $name) === 1) {
            throw new \InvalidArgumentException('Kein gültiger Dateiname');
        }
        $ordner = $this->ordnerAnlegen($ordnerPfad);
        $zielPfad = $ordnerPfad . '/' . $name;
        $userFolder = $this->userFolder();
        $node = $userFolder->nodeExists($zielPfad)
            ? $userFolder->get($zielPfad)
            : $ordner->newFile($name);
        if (method_exists($node, 'putContent')) {
            $node->putContent($inhalt);
        }
        return [
            'name' => $node->getName(),
            'pfad' => $zielPfad,
            'id' => $node->getId(),
        ];
    }

    private function sauberName(string $name): string
    {
        $sauber = trim(str_replace([' ', '/', '\\'], ['_', '_', '_'], $name));
        return $sauber === '' ? 'Dokument' : $sauber;
    }

    private function userFolder(): Folder
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new \RuntimeException('Nicht angemeldet');
        }
        return $this->rootFolder->getUserFolder($user->getUID());
    }

    private function ordner(string $pfad): ?Folder
    {
        try {
            $userFolder = $this->userFolder();
            if (!$userFolder->nodeExists($pfad)) {
                return null;
            }
            $ordner = $userFolder->get($pfad);
            return $ordner instanceof Folder ? $ordner : null;
        } catch (NotFoundException) {
            return null;
        }
    }

    /** Legt die ganze Ordnerkette an, soweit sie fehlt. */
    private function ordnerAnlegen(string $pfad): Folder
    {
        $userFolder = $this->userFolder();
        $aktuell = '';
        foreach (explode('/', trim($pfad, '/')) as $teil) {
            $aktuell = $aktuell === '' ? $teil : $aktuell . '/' . $teil;
            if (!$userFolder->nodeExists($aktuell)) {
                $userFolder->newFolder($aktuell);
            }
        }
        $ordner = $userFolder->get($pfad);
        if (!($ordner instanceof Folder)) {
            throw new \RuntimeException('Ablageordner ist keine Mappe: ' . $pfad);
        }
        return $ordner;
    }
}
