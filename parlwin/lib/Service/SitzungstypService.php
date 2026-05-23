<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\FraktionsrolleMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\MitgliedMapper;
use OCA\ParliamentWinterthur\Db\Sitzung;
use OCA\ParliamentWinterthur\Db\SitzungMapper;
use OCA\ParliamentWinterthur\Db\Sitzungstyp;
use OCA\ParliamentWinterthur\Db\SitzungstypMapper;
use OCA\ParliamentWinterthur\AppInfo\Application;
use OCA\ParliamentWinterthur\Db\SitzungstypTeilnehmer;
use OCA\ParliamentWinterthur\Db\SitzungstypTeilnehmerMapper;
use OCA\ParliamentWinterthur\Db\SitzungstypTraktandum;
use OCA\ParliamentWinterthur\Db\SitzungstypTraktandumMapper;
use OCA\ParliamentWinterthur\Db\Traktandum;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Verwaltung von Sitzungs-Vorlagen und Materialisierung konkreter Sitzungen.
 */
class SitzungstypService
{
  public function __construct(
    private readonly SitzungstypMapper $typMapper,
    private readonly SitzungstypTraktandumMapper $typTraktandenMapper,
    private readonly SitzungstypTeilnehmerMapper $typTeilnehmerMapper,
    private readonly SitzungMapper $sitzungMapper,
    private readonly TraktandumMapper $traktandumMapper,
    private readonly MitgliedMapper $mitgliedMapper,
    private readonly KommissionMapper $kommissionMapper,
    private readonly FraktionsrolleMapper $fraktionsrolleMapper,
    private readonly IGroupManager $groupManager,
    private readonly IUserManager $userManager,
    private readonly IUserSession $userSession,
    private readonly IConfig $config,
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function alle(): array
  {
    $typen = $this->typMapper->findAll();
    $ergebnis = [];
    foreach ($typen as $typ) {
      $ergebnis[] = $this->typMitDetails($typ);
    }
    return $ergebnis;
  }

  /**
   * @throws DoesNotExistException
   * @return array<string, mixed>
   */
  public function eins(int $id): array
  {
    $typ = $this->typMapper->find($id);
    return $this->typMitDetails($typ);
  }

  /**
   * Legt einen neuen Sitzungstyp an oder aktualisiert einen bestehenden.
   *
   * Erwartete Felder:
   *  - id (optional, sonst neu)
   *  - name, zweck, kalenderAnlegen, einladungVersenden
   *  - standardOrt, standardZeitVon, standardZeitBis
   *  - traktanden: Array<{titel,beschreibung,position?}>
   *  - teilnehmer: Array<{art, referenzId?, referenzName?}>
   *
   * @param array<string, mixed> $daten
   * @return array<string, mixed>
   */
  public function speichern(array $daten): array
  {
    $jetzt = (new \DateTime())->format('Y-m-d H:i:s');
    $id = (int) ($daten['id'] ?? 0);

    if ($id > 0) {
      $typ = $this->typMapper->find($id);
    } else {
      $typ = new Sitzungstyp();
      $typ->setErstelltAm($jetzt);
    }

    $typ->setName(trim((string) ($daten['name'] ?? '')));
    $typ->setZweck((string) ($daten['zweck'] ?? ''));
    $typ->setKalenderAnlegen(true);
    $typ->setEinladungVersenden((bool) ($daten['einladungVersenden'] ?? true));
    $typ->setStandardOrt((string) ($daten['standardOrt'] ?? ''));
    $typ->setStandardZeitVon((string) ($daten['standardZeitVon'] ?? ''));
    $typ->setStandardZeitBis((string) ($daten['standardZeitBis'] ?? ''));
    $typ->setGeloescht(false);
    $typ->setAktualisiertAm($jetzt);

    if ($id > 0) {
      $this->typMapper->update($typ);
    } else {
      $typ = $this->typMapper->insert($typ);
    }

    $typId = (int) $typ->getId();

    // Traktanden komplett neu setzen.
    $this->typTraktandenMapper->deleteByTyp($typId);
    $traktanden = is_array($daten['traktanden'] ?? null) ? $daten['traktanden'] : [];
    $position = 1;
    foreach ($traktanden as $eintrag) {
      $tt = new SitzungstypTraktandum();
      $tt->setTypId($typId);
      $tt->setPosition((int) ($eintrag['position'] ?? $position));
      $tt->setTitel((string) ($eintrag['titel'] ?? ''));
      $tt->setBeschreibung((string) ($eintrag['beschreibung'] ?? ''));
      $this->typTraktandenMapper->insert($tt);
      $position++;
    }

    // Teilnehmer komplett neu setzen.
    $this->typTeilnehmerMapper->deleteByTyp($typId);
    $teilnehmer = is_array($daten['teilnehmer'] ?? null) ? $daten['teilnehmer'] : [];
    foreach ($teilnehmer as $eintrag) {
      $art = (string) ($eintrag['art'] ?? 'mitglied');
      if (!in_array($art, ['mitglied', 'fraktion', 'kommission', 'rolle', 'eigeneFraktion', 'ncGruppe', 'ncUser'], true)) {
        continue;
      }
      $tt = new SitzungstypTeilnehmer();
      $tt->setTypId($typId);
      $tt->setArt($art);
      $tt->setReferenzId((int) ($eintrag['referenzId'] ?? 0));
      $tt->setReferenzName((string) ($eintrag['referenzName'] ?? ''));
      $this->typTeilnehmerMapper->insert($tt);
    }

    return $this->typMitDetails($this->typMapper->find($typId));
  }

  /**
   * Markiert einen Sitzungstyp als gelöscht (Soft-Delete – konkrete Sitzungen
   * bleiben erhalten).
   */
  public function loeschen(int $id): void
  {
    try {
      $typ = $this->typMapper->find($id);
    } catch (DoesNotExistException) {
      return;
    }
    $typ->setGeloescht(true);
    $typ->setAktualisiertAm((new \DateTime())->format('Y-m-d H:i:s'));
    $this->typMapper->update($typ);
  }

  /**
   * @return array<string, mixed>
   */
  private function typMitDetails(Sitzungstyp $typ): array
  {
    $typId = (int) $typ->getId();
    return array_merge($typ->jsonSerialize(), [
      'traktanden' => array_map(
        fn(SitzungstypTraktandum $t) => $t->jsonSerialize(),
        $this->typTraktandenMapper->findByTyp($typId)
      ),
      'teilnehmer' => array_map(
        fn(SitzungstypTeilnehmer $t) => $t->jsonSerialize(),
        $this->typTeilnehmerMapper->findByTyp($typId)
      ),
    ]);
  }
}

