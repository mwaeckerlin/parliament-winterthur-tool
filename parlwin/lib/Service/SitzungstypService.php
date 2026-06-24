<?php

declare(strict_types=1);

namespace OCA\ParliamentWinterthur\Service;

use OCA\ParliamentWinterthur\Db\Fraktionsrolle;
use OCA\ParliamentWinterthur\Db\FraktionsrolleMapper;
use OCA\ParliamentWinterthur\Db\KommissionMapper;
use OCA\ParliamentWinterthur\Db\Mitglied;
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
    private readonly KalenderService $kalenderService,
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
    $typ->setVerknuepfen((bool) ($daten['verknuepfen'] ?? false));
    $typ->setKommissionen(json_encode(array_values(array_map('intval', (array) ($daten['kommissionen'] ?? [])))));
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
   * Erstellt eine interne Sitzung aus einer Vorlage.
   *
   * Felder in $daten:
   *  - typId (int, required)
   *  - datum (string YYYY-MM-DD, required)
   *  - titel, ort, zeitVon, zeitBis, bemerkungen (strings, optional – Fallback auf Vorlage)
   *  - traktanden (array of {titel, beschreibung}, optional)
   *
   * @throws \OCP\AppFramework\Db\DoesNotExistException wenn der Typ nicht gefunden wird
   */
  public function erstelleAusTyp(array $daten): Sitzung
  {
    $typId = (int) ($daten['typId'] ?? 0);
    $typ = $this->typMapper->find($typId);
    $jetzt = (new \DateTime())->format('Y-m-d H:i:s');

    $traktandenDaten = is_array($daten['traktanden'] ?? null) ? $daten['traktanden'] : [];
    $zweck = (string) ($daten['bemerkungen'] ?? $typ->getZweck());

    // Kalender-Beschreibung: Zweck + Traktanden-Liste (wie vorschau() aufbaut)
    $beschreibungTeile = [];
    if ($zweck !== '') {
      $beschreibungTeile[] = $zweck;
    }
    if (!empty($traktandenDaten)) {
      if (!empty($beschreibungTeile)) {
        $beschreibungTeile[] = '';
      }
      $beschreibungTeile[] = 'Traktanden:';
      foreach ($traktandenDaten as $i => $td) {
        $zeile = ($i + 1) . '. ' . trim((string) ($td['titel'] ?? ''));
        if (!empty($td['beschreibung'])) {
          $zeile .= ': ' . $td['beschreibung'];
        }
        $beschreibungTeile[] = $zeile;
      }
    }
    $kalenderBeschreibung = implode("\n", $beschreibungTeile);

    $sitzung = new Sitzung();
    $sitzung->setTitel((string) (($daten['titel'] ?? '') !== '' ? $daten['titel'] : $typ->getName()));
    $sitzung->setDatum((string) ($daten['datum'] ?? ''));
    $sitzung->setZeitVon((string) ($daten['zeitVon'] ?? $typ->getStandardZeitVon()));
    $sitzung->setZeitBis((string) ($daten['zeitBis'] ?? $typ->getStandardZeitBis()));
    $sitzung->setOrt((string) ($daten['ort'] ?? $typ->getStandardOrt()));
    $sitzung->setBemerkungen($zweck);
    $sitzung->setTypId($typId);
    $sitzung->setExternId(null);
    $sitzung->setUrl('');
    $sitzung->setGeloescht(false);
    $sitzung->setNotizen('[]');
    $sitzung->setTeilnehmer('[]');
    $sitzung->setErstelltAm($jetzt);
    $sitzung->setAktualisiertAm($jetzt);

    // Wenn der Client Teilnehmer-Regeln mitschickt, diese materialisieren; sonst aus Vorlage
    $teilnehmerRegeln = is_array($daten['teilnehmer'] ?? null) ? $daten['teilnehmer'] : null;
    if ($teilnehmerRegeln !== null) {
      $teilnehmer = $this->materialisiereRegeln($teilnehmerRegeln);
    } else {
      $teilnehmer = $this->materialisiereTeilnehmer($typ);
    }
    $sitzung->setTeilnehmer(json_encode($teilnehmer, JSON_UNESCAPED_UNICODE));

    $sitzung = $this->sitzungMapper->insert($sitzung);

    $nummer = 1;
    foreach ($traktandenDaten as $td) {
      $t = new Traktandum();
      $t->setSitzungId((int) $sitzung->getId());
      $t->setNummer($nummer++);
      $t->setTitel(trim((string) ($td['titel'] ?? '')));
      $t->setBeschreibung(trim((string) ($td['beschreibung'] ?? '')));
      $t->setGeschaeftId(0);
      $t->setUrl('');
      $t->setGeloescht(false);
      $t->setBemerkungen('');
      $t->setNotizen('[]');
      $t->setErstelltAm($jetzt);
      $t->setAktualisiertAm($jetzt);
      $this->traktandumMapper->insert($t);
    }

    if ($typ->getKalenderAnlegen()) {
      $this->kalenderService->erstelleInterneSitzung($sitzung, $kalenderBeschreibung);
    }

    return $sitzung;
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
   * Löst alle Teilnehmer-Regeln eines Sitzungstyps zu konkreten Personen auf.
   * Dedupliziert nach E-Mail-Adresse.
   *
   * @return array<int, array{email: string, displayName: string, ncUid: string, gruppe: bool}>
   */
  public function materialisiereTeilnehmer(Sitzungstyp $typ): array
  {
    $regeln = $this->typTeilnehmerMapper->findByTyp((int) $typ->getId());
    $result = [];
    $seenEmails = [];

    $seenGroupIds = [];

    foreach ($regeln as $regel) {
      $art = $regel->getArt();
      $refId = $regel->getReferenzId();
      $refName = $regel->getReferenzName();

      $personen = [];
      try {
        $personen = match ($art) {
          'mitglied'     => $this->mitgliedAlsPersonen($refId),
          'fraktion'     => $this->fraktionAlsPersonen($refName),
          'kommission'   => $this->kommissionAlsPersonen($refId),
          'rolle'        => $this->rolleAlsPersonen($refName),
          'eigeneFraktion' => $this->eigeneFraktionAlsPersonen(),
          'ncGruppe'     => $this->ncGruppeAlsPersonen($refName),
          'ncUser'       => $this->ncUserAlsPersonen($refName),
          default        => [],
        };
      } catch (\Throwable $e) {
        $this->logger->warning('parlwin: Teilnehmer-Regel fehlgeschlagen', [
          'art' => $art, 'refId' => $refId, 'refName' => $refName,
          'exception' => $e,
        ]);
      }

      foreach ($personen as $person) {
        if (!empty($person['gruppe'])) {
          $gid = $person['groupId'] ?? '';
          if (!$gid || isset($seenGroupIds[$gid])) continue;
          $seenGroupIds[$gid] = true;
          $result[] = $person;
        } else {
          $email = $person['email'] ?? '';
          if (!$email) continue;
          if (isset($seenEmails[$email])) continue;
          $seenEmails[$email] = true;
          $result[] = $person;
        }
      }
    }

    return $result;
  }

  /**
   * Löst Teilnehmer-Regeln als Array (statt DB-Objekte) zu konkreten Personen auf.
   *
   * @param array<int, array{art: string, referenzId?: int, referenzName?: string}> $regeln
   * @return array<int, array{email: string, displayName: string, ncUid: string, gruppe: bool}>
   */
  private function materialisiereRegeln(array $regeln): array
  {
    $result = [];
    $seenEmails = [];
    $seenGroupIds = [];

    foreach ($regeln as $regel) {
      $art    = (string) ($regel['art'] ?? '');
      $refId  = (int) ($regel['referenzId'] ?? 0);
      $refName = (string) ($regel['referenzName'] ?? '');

      $personen = [];
      try {
        $personen = match ($art) {
          'mitglied'       => $this->mitgliedAlsPersonen($refId),
          'fraktion'       => $this->fraktionAlsPersonen($refName),
          'kommission'     => $this->kommissionAlsPersonen($refId),
          'rolle'          => $this->rolleAlsPersonen($refName),
          'eigeneFraktion' => $this->eigeneFraktionAlsPersonen(),
          'ncGruppe'       => $this->ncGruppeAlsPersonen($refName),
          'ncUser'         => $this->ncUserAlsPersonen($refName),
          default          => [],
        };
      } catch (\Throwable $e) {
        $this->logger->warning('parlwin: Teilnehmer-Regel fehlgeschlagen', [
          'art' => $art, 'refId' => $refId, 'refName' => $refName, 'exception' => $e,
        ]);
      }

      foreach ($personen as $person) {
        if (!empty($person['gruppe'])) {
          $gid = $person['groupId'] ?? '';
          if (!$gid || isset($seenGroupIds[$gid])) continue;
          $seenGroupIds[$gid] = true;
          $result[] = $person;
        } else {
          $email = $person['email'] ?? '';
          if (!$email) continue;
          if (isset($seenEmails[$email])) continue;
          $seenEmails[$email] = true;
          $result[] = $person;
        }
      }
    }

    return $result;
  }

  /**
   * Baut die Vorschau-Nutzlast für das Öffnen des NC-Kalender-Editors.
   *
   * @throws DoesNotExistException
   * @return array<string, mixed>
   */
  public function vorschau(int $id): array
  {
    $typ = $this->typMapper->find($id);
    $traktanden = $this->typTraktandenMapper->findByTyp($id);

    $beschreibungTeile = [];
    if ($typ->getZweck() !== '') {
      $beschreibungTeile[] = $typ->getZweck();
    }
    if (!empty($traktanden)) {
      if (!empty($beschreibungTeile)) {
        $beschreibungTeile[] = '';
      }
      $beschreibungTeile[] = 'Traktanden:';
      foreach ($traktanden as $i => $t) {
        $zeile = ($i + 1) . '. ' . $t->getTitel();
        if ($t->getBeschreibung() !== '') {
          $zeile .= ': ' . $t->getBeschreibung();
        }
        $beschreibungTeile[] = $zeile;
      }
    }

    return [
      'titel'         => $typ->getName(),
      'ort'           => $typ->getStandardOrt(),
      'zeitVon'       => $typ->getStandardZeitVon(),
      'zeitBis'       => $typ->getStandardZeitBis(),
      'beschreibung'  => implode("\n", $beschreibungTeile),
      'teilnehmer'    => $this->materialisiereTeilnehmer($typ),
      'kalenderUri'   => 'parlwin-fraktion-kalender',
      'kalenderNutzer' => 'admin',
    ];
  }

  /** @return array<int, array{email: string, displayName: string, ncUid: string, gruppe: bool}> */
  private function mitgliedAlsPersonen(int $id): array
  {
    try {
      $m = $this->mitgliedMapper->find($id);
      return array_filter([$this->mitgliedZuPerson($m)]);
    } catch (DoesNotExistException) {
      return [];
    }
  }

  /** @return array<int, array{email: string, displayName: string, ncUid: string, gruppe: bool}> */
  private function fraktionAlsPersonen(string $fraktionName): array
  {
    $mitglieder = $this->mitgliedMapper->findByFraktion($fraktionName);
    return array_values(array_filter(array_map([$this, 'mitgliedZuPerson'], $mitglieder)));
  }

  /** @return array<int, array{email: string, displayName: string, ncUid: string, gruppe: bool}> */
  private function kommissionAlsPersonen(int $id): array
  {
    try {
      $kommission = $this->kommissionMapper->find($id);
    } catch (DoesNotExistException) {
      return [];
    }
    $result = [];
    foreach ($kommission->getMitgliederArray() as $externId) {
      try {
        $m = $this->mitgliedMapper->findByExternId((string) $externId);
        $p = $this->mitgliedZuPerson($m);
        if ($p !== null) {
          $result[] = $p;
        }
      } catch (DoesNotExistException) {
        // ignore
      }
    }
    return $result;
  }

  /** @return array<int, array{email: string, displayName: string, ncUid: string, gruppe: bool}> */
  private function rolleAlsPersonen(string $rolleCode): array
  {
    $rollen = $this->fraktionsrolleMapper->findAktiveByRolle($rolleCode);
    return array_values(array_filter(array_map([$this, 'fraktionsrolleZuPerson'], $rollen)));
  }

  /** @return array<int, array{email: string, displayName: string, ncUid: string, gruppe: bool}> */
  private function eigeneFraktionAlsPersonen(): array
  {
    $user = $this->userSession->getUser();
    if ($user === null) {
      return [];
    }
    $mitglied = $this->mitgliedMapper->findByNextcloudUid($user->getUID());
    if ($mitglied === null) {
      return [];
    }
    $fraktion = $mitglied->getFraktion();
    if ($fraktion === '') {
      return [];
    }
    return $this->fraktionAlsPersonen($fraktion);
  }

  /** @return array<int, array{email: string, displayName: string, ncUid: string, gruppe: bool}> */
  private function ncGruppeAlsPersonen(string $gid): array
  {
    $group = $this->groupManager->get($gid);
    if ($group === null) {
      return [];
    }
    $result = [];
    foreach ($group->getUsers() as $user) {
      $email = method_exists($user, 'getEMailAddress') ? (string) $user->getEMailAddress() : '';
      if (!$email) {
        continue;
      }
      $result[] = [
        'email'       => $email,
        'displayName' => $user->getDisplayName(),
        'ncUid'       => $user->getUID(),
        'gruppe'      => false,
      ];
    }
    return $result;
  }

  /** @return list<array{email: string, displayName: string, ncUid: string, gruppe: bool}> */
  private function ncUserAlsPersonen(string $uid): array
  {
    $user = $this->userManager->get($uid);
    if ($user === null) {
      return [];
    }
    $email = method_exists($user, 'getEMailAddress') ? (string) $user->getEMailAddress() : '';
    return [[
      'email'       => $email,
      'displayName' => $user->getDisplayName(),
      'ncUid'       => $uid,
      'gruppe'      => false,
    ]];
  }

  /**
   * @return array{email: string, displayName: string, ncUid: string, gruppe: bool}|null
   */
  private function mitgliedZuPerson(Mitglied $m): ?array
  {
    if (!$m->getAktiv() || $m->getGeloescht()) {
      return null;
    }
    return [
      'email'       => $m->getEmail(),
      'displayName' => $m->getVollerName(),
      'ncUid'       => $m->getNextcloudUid(),
      'gruppe'      => false,
    ];
  }

  /**
   * @return array{email: string, displayName: string, ncUid: string, gruppe: bool}|null
   */
  private function fraktionsrolleZuPerson(Fraktionsrolle $rolle): ?array
  {
    $uid = $rolle->getUid();
    $user = $this->userManager->get($uid);
    $email = '';
    $displayName = $rolle->getName();
    if ($user !== null) {
      $email = method_exists($user, 'getEMailAddress') ? (string) $user->getEMailAddress() : '';
      $displayName = $user->getDisplayName() ?: $displayName;
    }
    return [
      'email'       => $email,
      'displayName' => $displayName,
      'ncUid'       => $uid,
      'gruppe'      => false,
    ];
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

