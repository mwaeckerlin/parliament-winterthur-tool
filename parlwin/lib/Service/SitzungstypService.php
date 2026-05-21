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
use OCA\ParliamentWinterthur\Db\SitzungstypTeilnehmer;
use OCA\ParliamentWinterthur\Db\SitzungstypTeilnehmerMapper;
use OCA\ParliamentWinterthur\Db\SitzungstypTraktandum;
use OCA\ParliamentWinterthur\Db\SitzungstypTraktandumMapper;
use OCA\ParliamentWinterthur\Db\Traktandum;
use OCA\ParliamentWinterthur\Db\TraktandumMapper;
use OCP\AppFramework\Db\DoesNotExistException;
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
    $typ->setKalenderAnlegen((bool) ($daten['kalenderAnlegen'] ?? true));
    $typ->setEinladungVersenden((bool) ($daten['einladungVersenden'] ?? false));
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
   * Erstellt aus einer Vorlage eine konkrete Sitzung. Die Standard-Traktanden
   * werden als reine Vorlagen-Traktanden (geschaeftId=0) kopiert; die
   * Teilnehmerliste wird materialisiert (Gruppen → Einzelpersonen).
   *
   * @param array<string, mixed> $overrides
   * @throws DoesNotExistException wenn der Typ nicht existiert
   */
  public function sitzungAusTyp(int $typId, array $overrides = []): Sitzung
  {
    $typ = $this->typMapper->find($typId);
    $jetzt = (new \DateTime())->format('Y-m-d H:i:s');

    $sitzung = new Sitzung();
    $sitzung->setExternId('typ-' . $typId . '-' . bin2hex(random_bytes(4)));
    $sitzung->setTitel((string) ($overrides['titel'] ?? $typ->getName()));
    $sitzung->setDatum((string) ($overrides['datum'] ?? ''));
    $sitzung->setZeitVon((string) ($overrides['zeitVon'] ?? $typ->getStandardZeitVon()));
    $sitzung->setZeitBis((string) ($overrides['zeitBis'] ?? $typ->getStandardZeitBis()));
    $sitzung->setOrt((string) ($overrides['ort'] ?? $typ->getStandardOrt()));
    $sitzung->setUrl('');
    $sitzung->setGeloescht(false);
    $sitzung->setBemerkungen((string) ($overrides['bemerkungen'] ?? ''));
    $sitzung->setTypId($typId);
    $sitzung->setTeilnehmer(json_encode($this->materialisiereTeilnehmer($typId), JSON_UNESCAPED_UNICODE));
    $sitzung->setErstelltAm($jetzt);
    $sitzung->setAktualisiertAm($jetzt);

    $sitzung = $this->sitzungMapper->insert($sitzung);

    // Standard-Traktanden kopieren.
    $position = 1;
    foreach ($this->typTraktandenMapper->findByTyp($typId) as $vorlage) {
      $t = new Traktandum();
      $t->setSitzungId((int) $sitzung->getId());
      $t->setGeschaeftId(0);
      $t->setNummer($position);
      $t->setTitel($vorlage->getTitel());
      $t->setBeschreibung($vorlage->getBeschreibung());
      $t->setGeloescht(false);
      $t->setBemerkungen('');
      $t->setNotizen('[]');
      $t->setErstelltAm($jetzt);
      $t->setAktualisiertAm($jetzt);
      $this->traktandumMapper->insert($t);
      $position++;
    }

    return $sitzung;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function materialisiereTeilnehmer(int $typId): array
  {
    $regeln = $this->typTeilnehmerMapper->findByTyp($typId);
    /** @var array<string, array<string, mixed>> $kombiniert */
    $kombiniert = [];

    foreach ($regeln as $regel) {
      foreach ($this->mitgliederZuRegel($regel) as $mitglied) {
        $key = (string) ($mitglied['mitgliedId'] ?? $mitglied['email'] ?? $mitglied['name']);
        if ($key === '') {
          continue;
        }
        if (!isset($kombiniert[$key])) {
          $kombiniert[$key] = $mitglied;
        }
      }
    }

    return array_values($kombiniert);
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private function mitgliederZuRegel(SitzungstypTeilnehmer $regel): array
  {
    $art = $regel->getArt();
    try {
      switch ($art) {
        case 'mitglied':
          if ($regel->getReferenzId() > 0) {
            $m = $this->mitgliedMapper->find($regel->getReferenzId());
            return [$this->mitgliedZuEintrag($m)];
          }
          return [];

        case 'fraktion':
          $name = $regel->getReferenzName() !== ''
            ? $regel->getReferenzName()
            : '';
          if ($name === '') {
            return [];
          }
          return array_map(
            fn($m) => $this->mitgliedZuEintrag($m),
            $this->mitgliedMapper->findByFraktion($name)
          );

        case 'kommission':
          if ($regel->getReferenzId() > 0) {
            $kom = $this->kommissionMapper->find($regel->getReferenzId());
            $externIds = $kom->getMitgliederArray();
            $mitglieder = [];
            foreach ($externIds as $externId) {
              try {
                $mitglieder[] = $this->mitgliedZuEintrag(
                  $this->mitgliedMapper->findByExternId((string) $externId)
                );
              } catch (DoesNotExistException) {
                // ignorieren
              }
            }
            return $mitglieder;
          }
          return [];

        case 'rolle':
          $rollen = $this->fraktionsrolleMapper->findAktiveByRolle($regel->getReferenzName());
          $mitglieder = [];
          foreach ($rollen as $rolle) {
            $uid = $rolle->getUid();
            if ($uid === '') {
              continue;
            }
            $m = $this->mitgliedMapper->findByNextcloudUid($uid);
            if ($m !== null) {
              $mitglieder[] = $this->mitgliedZuEintrag($m);
            } else {
              $mitglieder[] = [
                'mitgliedId' => 0,
                'name' => $rolle->getName(),
                'email' => '',
                'rolle' => $regel->getReferenzName(),
                'uid' => $uid,
              ];
            }
          }
          return $mitglieder;

        case 'eigeneFraktion':
          $aktUser = $this->userSession->getUser();
          if ($aktUser === null) {
            return [];
          }
          $eigen = $this->mitgliedMapper->findByNextcloudUid($aktUser->getUID());
          $fraktionsName = $eigen?->getFraktion() ?? '';
          if ($fraktionsName === '') {
            return [];
          }
          return array_map(
            fn($m) => $this->mitgliedZuEintrag($m),
            $this->mitgliedMapper->findByFraktion($fraktionsName)
          );

        case 'ncGruppe':
          $gid = $regel->getReferenzName();
          if ($gid === '') {
            return [];
          }
          $group = $this->groupManager->get($gid);
          if ($group === null) {
            return [];
          }
          $mitglieder = [];
          foreach ($group->getUsers() as $user) {
            $uid = $user->getUID();
            $m = $this->mitgliedMapper->findByNextcloudUid($uid);
            if ($m !== null) {
              $mitglieder[] = $this->mitgliedZuEintrag($m);
            } else {
              $mitglieder[] = [
                'mitgliedId' => 0,
                'name' => $user->getDisplayName(),
                'email' => method_exists($user, 'getEMailAddress') ? ((string) $user->getEMailAddress()) : '',
                'rolle' => '',
                'uid' => $uid,
              ];
            }
          }
          return $mitglieder;

        case 'ncUser':
          $uid = $regel->getReferenzName();
          if ($uid === '') {
            return [];
          }
          $m = $this->mitgliedMapper->findByNextcloudUid($uid);
          if ($m !== null) {
            return [$this->mitgliedZuEintrag($m)];
          }
          $user = $this->userManager->get($uid);
          if ($user === null) {
            return [];
          }
          return [[
            'mitgliedId' => 0,
            'name' => $user->getDisplayName(),
            'email' => method_exists($user, 'getEMailAddress') ? ((string) $user->getEMailAddress()) : '',
            'rolle' => '',
            'uid' => $uid,
          ]];
      }
    } catch (\Throwable $e) {
      $this->logger->warning(
        'Parlament Winterthur: Konnte Teilnehmerregel nicht auflösen: ' . $e->getMessage(),
        ['exception' => $e]
      );
    }
    return [];
  }

  /**
   * @return array<string, mixed>
   */
  private function mitgliedZuEintrag(\OCA\ParliamentWinterthur\Db\Mitglied $m): array
  {
    return [
      'mitgliedId' => (int) $m->getId(),
      'name' => trim($m->getVorname() . ' ' . $m->getName()),
      'email' => $m->getEmail(),
      'rolle' => '',
      'uid' => $m->getNextcloudUid(),
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
