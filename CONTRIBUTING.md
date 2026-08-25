# Contributing

Verbindliche Anforderungen für jede Änderung an diesem Projekt. Projektsprache
der Dokumentation ist Deutsch (Schweizer Rechtschreibung, kein «ß», kein
Gendering); UI-Texte sind Deutsch.

## Globale Design-Anforderungen (verbindlich)

Diese Regeln gelten für JEDE Ansicht und JEDES Element der App — ohne Ausnahme.

1. **Konsistenz ist die erste Designregel.** Gleiches sieht überall gleich aus
   und verhält sich überall gleich: dasselbe Feld → dasselbe Widget → dasselbe
   Verhalten, an allen Orten. Vor jedem neuen UI-Element zuerst den
   Nextcloud-Standard und ALLE vergleichbaren Stellen der App prüfen und exakt
   übernehmen — Feld für Feld, nie nur die ganze Seite grob.
   - **Ein Datentyp = EIN Eingabe-Widget, von einer Basis abgeleitet, überall
     verwendet.** Einzel-Auswahl-Basis ist `PwSelect` (Label-Formatter, Rohwert
     rein/raus); davon abgeleitet `PwKommissionSelect` (zeigt Kürzel, speichert
     den vollen Namen), `PwPrioritaetSelect` (kapselt `PRIORITAETEN`),
     `PwTypSelect`; Datum → `PwDatumInput`; Mehrfach/Personen → `PwMultiSelect`;
     Auswahl-mit-Freitext → `BeschlussWidget`. **Anzeige-Regeln (z.B. Kürzel)
     sitzen IM Widget**, nie in der Einbaustelle — sonst «vergisst» eine Stelle
     sie. Fehlt für einen Datentyp ein Widget, zuerst eines anlegen; NIE roh
     `NcSelect`/`<input>` inline für einen Datentyp bauen.
2. **Beim BEARBEITEN speichert jede Eingabe SOFORT.** Dort gibt es keine
   Abbrechen/Speichern-Buttons: Auswahlen speichern beim Wählen, Text beim
   Verlassen des Feldes (Blur), Editor-Inhalte (Votum, Beschreibung) beim
   Verlassen des Editors. Dialoge werden mit ✕ geschlossen — Schliessen verwirft
   nie Daten, denn alles ist bereits gespeichert. Erfolgsmeldung als
   Nextcloud-Standard-Toast. **Ausnahme Notizen:** der Notiz-Editor speichert
   NICHT beim Verlassen und hat keinen Autosave — gespeichert wird nur bewusst
   über ✓ (Häkchen), ✕ verwirft. Weil Schliessen hier Daten verwerfen KANN,
   warnt der Editor beim Verlassen der Seite oder des Dialogs vor ungespeicherten
   Änderungen (siehe Abschnitt «Notizen»). Für das **Anlegen** gilt stattdessen
   Anforderung 5: dort wird nichts vorab gespeichert, und die Maske trägt
   «Speichern» und «Abbrechen».
3. **Eingaben gehen NIE verloren.** Ein Fokus-Verlust ist nur dann ein
   «Feld verlassen», wenn der Fokus die Komponente wirklich verlässt
   (`relatedTarget` prüfen); Toolbar-Klicks per `@mousedown.prevent` abfangen;
   auch programmatische Blurs dürfen keine Eingaben verwerfen.
4. **Bearbeiten durch Klick auf den Eintrag** (Karte/Zeile) — keine separaten
   Bearbeiten-Buttons. **Selbst angelegte Objekte sind löschbar** (eigenes
   Geschäft, Vorstoss, Sitzungstyp): Löschen-Knopf mit Rückfrage. Objekte aus
   der Parlamentsquelle sind NICHT löschbar — ihr Lebenszyklus läuft über den
   Status (z.B. «erledigt»), weil sie beim nächsten Abgleich zurückkämen.
   Notizen-Löschen bleibt ein Soft-Delete mit Undo.
5. **Neu-Anlegen nutzt DIESELBE Maske wie das Bearbeiten** (geteilter Code, kein
   zweites Formular). Ein reduziertes «Neu»-Formular ist verboten. Unterschiede
   zum Bearbeiten:
   - Beim Anlegen wird **nichts vorab angelegt oder gespeichert**; die Maske
     sammelt die Eingaben. Unten stehen **«Speichern»** (primär, links) und
     **«Abbrechen»** (rechts) in `.pw-modal-footer`; im Neu-Modus gibt es
     **kein «✕»**. «Speichern» ist gesperrt, solange die Pflichtangabe fehlt.
   - Nach dem Speichern geht dieselbe Maske unmittelbar in den Bearbeiten-Modus
     über (Titelzeile wechselt, ID-gebundene Bereiche erscheinen).
   - **Ein Klick neben die Maske darf im Neu-Modus nichts bewirken** — kein
     Schliessen, kein Verwerfen. Verworfen wird nur über «Abbrechen». Beim
     Bearbeiten schliesst der Klick daneben wie bisher.
   - **Begründete Ausnahme:** Bereiche, die eine bestehende ID brauchen
     (Notizen, Dokumente, Aktionszeitleiste, Beschluss, Votum, Verknüpfungen),
     werden im Neu-Modus ausgeblendet und durch einen Hinweis ersetzt.
   - Stehen Vorlagen zur Auswahl (Sitzungstypen), öffnet der Neu-Knopf ein
     Aktionsmenü mit je einem Eintrag pro Vorlage — der Nextcloud-Standard aus
     der App «files», kein eigener Auswahl-Dialog.
6. **Keine Änderung ohne Spur.** Jede Änderung wird protokolliert: Notizen über
   ihren Versionsverlauf, alles Übrige als Eintrag in der Aktionszeitleiste.
7. **In der offenen Detailansicht ist immer ALLES sichtbar** — jedes Feld des
   Objekts erscheint in der Detail-/Bearbeitungsansicht (z.B. auch die
   Priorität), nichts ist nur in der Übersicht editierbar.
8. **Auswahllisten zeigen nur Aktive:** nur aktive Fraktionen (mit ihrer
   eingetragenen Abkürzung/Bezeichnung), nur aktive Mitglieder; Ansprechpartner
   nur Mitglieder der gewählten Fraktion. Das gilt immer und ohne besondere
   Erwähnung in Aufträgen.
9. **Nextcloud-Standard 1:1:** CSS und Komponenten so weit wie möglich von
   Nextcloud und den Standard-Apps (Referenz: App «files») übernehmen — kein
   eigenes Design-System, keine eigenen Icon-Formate, NC-CSS-Variablen nutzen.
   **Keine eigenen `z-index` in Komponenten:** Overlays und Dialoge nutzen den
   gemeinsamen Hintergrund `.pw-modal-overlay`. Die an den Seitenkörper
   geteleportierte Auswahlliste (`.vs__dropdown-menu`) liegt bewusst knapp über
   diesem Hintergrund — ein eigener, höherer `z-index` deckt sie zu und macht
   die Auswahl unsichtbar (Bug «Verknüpfen mit», 2026-07-24).
10. **Priorität:** dreistufig (hoch/mittel/tief), Default ist NICHT gesetzt
   (leer, wirkt wie mittel); nicht gesetzt wird als «—» angezeigt und ist
   wieder abwählbar. In Übersichten wird hoch dezent hervorgehoben, tief
   abgeschwächt. Gilt für Geschäfte UND Vorstösse; bei der Verknüpfung eines
   Vorstosses mit einem Geschäft wird die Priorität übernommen.
11. **Gemeinsame Logik teilen (DRY):** identische Konstanten/Funktionen liegen
    in `parlwin/src/js/utils.js` (z.B. `PRIORITAETEN`), nie kopiert.
12. **Kein Container bekommt ein Verzeichnis des Wirts zu sehen** — weder in
    Entwicklung, Test noch Auslieferung, und auch nicht `read-only`. Was ein
    Container braucht, kommt beim Bauen per `COPY` ins Abbild (nie `ADD`:
    entpackt Archive und lädt URLs); Ergebnisse holt man danach mit
    `docker cp` heraus. Persistenz läuft über **benannte** Volumes. Der
    Docker-Socket `/var/run/docker.sock` wird nie eingeblendet — Werkzeuge, die
    ihn verlangen (z.B. der Docker-Provider von Traefik), werden abgeschaltet
    und durch eingebackene Konfiguration ersetzt. Festgenagelt in
    `docker-keine-bind-mounts.test.js`.
13. **Gespeicherter Rich-Text wird serverseitig über eine Positivliste
    ausgegeben** (`Service\HtmlSanitizer`): erlaubt sind nur die dort
    aufgezählten Gestaltungselemente und Attribute, Verweise nur mit den
    Schemata `http`, `https`, `mailto`, `tel`. Eine Negativliste («entferne
    `script`, entferne `on…`-Attribute») ist verboten — sie lässt sich mit
    `<img/onerror=…>` oder `href="javascript:…"` umgehen.

## Tests (verbindlich)

- Jedes Feature und jeder Bugfix ist test-getrieben: zuerst ein fehlschlagender
  Test (npnp-Doppelcheck: rot → grün → rot → grün), dann der Code.
- Tests laufen IMMER vollständig und in jedem Lauf mit — nie skippen, kürzen,
  abschwächen oder ungelaufen lassen. Ein Test wird nur angepasst, wenn das
  getestete Feature geändert wurde, und nur gelöscht, wenn das Feature entfernt
  wurde; sonst wird der Code an den Test angepasst.
- Der reale End-zu-End-Pfad jedes Features läuft in einem Playwright-e2e-Test
  gegen das echte System (`npm run test:e2e`); Unit-Tests mit Mocks ersetzen ihn
  nie. Entity↔DB-Round-trips zusätzlich mit realen DB-Werten (inkl. NULL) testen.
- Vor jedem Commit: `npm test` (alle Suiten, 0 Fehler), Version in
  `parlwin/appinfo/info.xml` erhöhen, `CHANGELOG.md` (aus Benutzersicht) und
  betroffene Doku ([README](README.md), [FEATURES](FEATURES.md)) nachführen.

## Architektur (Kurzüberblick)

Je ein Container, ein Docker-Hub-Repo mit Tags:

- **nginx** (`:nginx`) — Webserver/Edge, proxyt PHP und den WebSocket-Pfad
  `/ws/parlwin/` zum Realtime-Broker; Host-Port `NEXTCLOUD_HTTP_PORT`
  (Default 29824).
- **php-fpm** (`:php-fpm`) — Nextcloud mit eingebauter parlwin-App; enthält den
  Watcher-Prozess (App-Aktivierung, `occ upgrade` vor `app:enable`, Cron-Tick
  alle `PARLWIN_CRON_INTERVAL` Sekunden; bei Migrationsfehler sichtbarer Stopp
  statt hängendem Wartungsmodus).
- **realtime** (`:realtime`) — Node-WebSocket-Broker (Port 3001), jeder
  Handshake wird gegen die Nextcloud-Anmeldung validiert; läuft non-root mit
  read-only Artefakten.
- Begleitdienste: MariaDB, Collabora (Office), fake-SMTP (Dev),
  access-fix (Volume-Rechte).

Details (ENV-Variablen, Datenmodell, Sync-Interna, Realtime-Events,
Datenquellen-URLs, Deployment): [README, Abschnitte Administration und
Internas](README.md#administration). Härtung: ausgelieferte Images ohne Shell
und Skriptsprache (per Test geprüft), Compose-Netze verschlüsselt, Secrets als
Docker-Secrets.

## Fachlogik und Contracts (verbindlich)

Diese Regeln stehen nur im Code — sie sind hier autoritativ dokumentiert, damit
das Verhalten ohne Code-Zugriff exakt reproduzierbar ist. Bei jeder Änderung an
einer dieser Stellen wird der zugehörige Abschnitt nachgeführt.

### Ansichts-Gerüst (verbindlich für JEDE Hauptansicht)

Jede Ansicht (Geschäfte, Sitzungen, Kommissionen, Vorstösse, Budget, Mitglieder,
Sitzungstypen, Änderungsverlauf) nutzt **dasselbe Gerüst** — nie ein eigenes:

- **Suche und Filter** werden per `<Teleport to="#pw-search-slot">` bzw.
  `<Teleport to="#pw-filter-slot">` in die Navigationsspalte gehängt (Filter in
  `<div class="pw-filter-body">`), gesteuert über ein `filterReady`-Flag, das in
  `mounted()` per `$nextTick` gesetzt wird. **Filter erscheinen nie als eigenes
  Band im Seiteninhalt.**
- **Rahmen:** `<section class="pw-view-content pw-<name>">` mit
  `<header class="pw-view-header">` aus `<h2 class="pw-view-title">`,
  `<span class="pw-view-count">` und dem Neu-Knopf. Der Neu-Knopf folgt dem
  Bestand: `NcButton type="primary"` mit Text, oder — bei mehreren Typen —
  `NcActions`/`NcActionButton` (NC-Standard, wie «+ Neue Sitzung»).
- **Zustände:** `<div class="pw-laden"><NcLoadingIcon :size="32"/></div>` beim
  Laden, `NcEmptyContent` beim Leerzustand.
- **Karten/Listen** über `pw-card-grid`/`pw-data-card` (`pw-data-card-header`,
  `pw-data-card-kicker`, `pw-data-pair`), **Dialoge** über das geteilte
  `pw-modal-overlay`/`pw-modal`-Muster. Eingabefelder über die vorhandenen
  Widgets (`PwMultiSelect`, `PwSelect`-Ableitungen, `BeschlussWidget`,
  `GeschaeftDokumente`), rohe Inputs nur, wenn nachweislich kein Widget existiert.

Der schnelle Wächter `parlwin/src/js/tests/view-shell-konsistenz.test.js` erzwingt
dies (jede Ansicht muss `pw-view-content`/`pw-view-header`/`pw-view-title` haben,
kein eigenes Filterband). Hintergrund: Post-Mortem 2026-08-22 (Budget-Seite mit
selbst erfundenem Gerüst), Wiederholung von 2026-06-24.

### Fraktionsbeschlüsse: Codes, Labels und Zuordnung je Typ

Zwei zusammengehörende Quellen: `GeschaeftWorkflow::CATEGORY_DECISIONS`
(Kategorie → erlaubte Codes) und `FraktionsarbeitService::BESCHLUSS_LABELS`
(Code → Label). Änderungen an einer Seite müssen die andere mitziehen.

**Code → Label** (vollständig):

| Code | Label |
| --- | --- |
| `unterstuetzen` | Zustimmen |
| `ablehnen` | Ablehnen |
| `stimmfreigabe` | Stimmfreigabe |
| `miteinreichen_fraktion` | Miteinreichen als Fraktion |
| `miteinreichen_einzel` | Miteinreichen einzelne Personen |
| `ueberweisung_befuerworten` | Überweisung befürworten |
| `ueberweisung_ablehnen` | Überweisung ablehnen |
| `kenntnisnahme_positiv` | Kenntnisnahme positiv |
| `kenntnisnahme_negativ` | Kenntnisnahme negativ |
| `nachbericht_verlangen` | Nachbericht verlangen |
| `erheblich_erklaeren` | Erheblich erklären |
| `abschreiben` | Abschreiben |

**Kanonische Kategorie → erlaubte Codes** (vollständig, Reihenfolge = Anzeige):

- `motion`: `unterstuetzen`, `ablehnen`, `stimmfreigabe`,
  `miteinreichen_fraktion`, `miteinreichen_einzel`, `ueberweisung_befuerworten`,
  `ueberweisung_ablehnen`, `erheblich_erklaeren`, `abschreiben`
- `postulat`: `unterstuetzen`, `ablehnen`, `stimmfreigabe`,
  `miteinreichen_fraktion`, `miteinreichen_einzel`, `ueberweisung_befuerworten`,
  `ueberweisung_ablehnen`, `kenntnisnahme_positiv`, `kenntnisnahme_negativ`,
  `nachbericht_verlangen`, `abschreiben`
- `interpellation`: `unterstuetzen`, `ablehnen`, `stimmfreigabe`,
  `kenntnisnahme_positiv`, `kenntnisnahme_negativ`
- `schriftliche_anfrage`: identisch zu `interpellation`
- `bericht`: identisch zu `interpellation`
- `initiative`: `unterstuetzen`, `ablehnen`, `stimmfreigabe`,
  `ueberweisung_befuerworten`, `ueberweisung_ablehnen`
- `wahlen`: `unterstuetzen`, `ablehnen`, `stimmfreigabe`
- `vorlage`: `unterstuetzen`, `ablehnen`, `stimmfreigabe`
- `default` (jede unbekannte/leere Kategorie): `unterstuetzen`, `ablehnen`,
  `stimmfreigabe`

**Erledigt-Regel:** Enthält der Geschäftsstatus (case-insensitive) das Wort
`erledigt`, werden bei JEDER Kategorie zusätzlich `kenntnisnahme_positiv` und
`kenntnisnahme_negativ` angehängt (danach dedupliziert).

**Typ-Label → kanonische Kategorie:** Das Typ-Label (`Geschaeft::typ`) wird
normalisiert und über `CATEGORY_DECISIONS`/`CATEGORY_ALIASES` aufgelöst.
Normalisierung: `trim` → lowercase (UTF-8) → `ä→ae, ö→oe, ü→ue, ß→ss`, `/`
und `-` → Leerzeichen → alle Nicht-`[a-z0-9]` zu `_` → `_` aussen entfernen.
Trifft der normalisierte Wert direkt eine Kategorie, gilt diese; sonst der
Alias; sonst `default`. Alias-Zuordnung (`CATEGORY_ALIASES`): `dringliche_motion`,
`budget_motion` → `motion`; `dringliches_postulat`, `budget_postulat` →
`postulat`; `dringliche_interpellation`, `fragestunde` → `interpellation`;
`jahresrechnung`, `kreditabrechnung` → `bericht`; `kreditantrag`, `budget`,
`beschlussantrag`, `parlamentseigene_vorlage`, `verordnung_rechtserlass`,
`vertrag_vereinbarung`, `rechtsmittel`, `referendum`, `uebrige_geschaefte` →
`vorlage`; `volksinitiative`, `einzelinitiative`, `parlamentarische_initiative`
→ `initiative`. Die menschenlesbare Typ-Liste je Kategorie steht in
[README, Internas → Kategoriemapping](README.md#internas).

**Speichern eines Beschlusses** (`FraktionsarbeitService::beschlussFelder`):
Bei leerem Code muss ein Freitext vorhanden sein (sonst `InvalidArgumentException`);
der Titel ist dann die ersten 60 Zeichen des Textes, bei Überlänge mit `…`.
Bei gesetztem Code muss dieser in den für Typ+Status erlaubten Codes liegen,
sonst «Beschluss ist im aktuellen Status nicht zulässig». `beschlussZuruecknehmen`
setzt `entscheid_gueltig=false` und schreibt eine Audit-Aktion mit Code
`beschluss_zurueckgenommen`.

**Fraktionsstatus/Entscheidungsbedarf** (`FraktionsarbeitService::ableiteFraktionsstatus`):
`offen` + Bedarf, wenn kein gültiger Beschluss existiert; `neu_zu_entscheiden`
+ Bedarf, wenn `Geschaeft::quelleAktualisiertAm` zeitlich NACH der letzten
gültigen Beschluss-Aktion liegt; sonst `entschieden` ohne Bedarf.

### Vorstösse: Werte-Enumerationen

- **Arten** (`Vorstoesseliste.vue::VORSTOSS_ARTEN`, nur Vorschlag im
  BeschlussWidget, gespeichert als Freitext `art`): `Motion`, `Postulat`,
  `Interpellation`, `Schriftliche Anfrage`, `Dringliche Motion`, `Dringliches
  Postulat`, `Budgetmotion`, `Fragestunde`, `Einzelinitiative`,
  `Parlamentarische Initiative`.
- **Herkunft** (`VorstossService::HERKUENFTE`, Default `eigene`): `eigene`,
  `fremde`. Ungültige Eingaben werden auf `eigene` normalisiert.
- **Status** (`VorstossService::STATUS`, Default `neu`): `neu`, `entwurf`,
  `bereit`, `eingereicht`, `erledigt`, `pausiert`. Ungültig → `neu`.
- **Haltung zu fremdem Vorstoss** (`Vorstoesseliste.vue::FREMD_BESCHLUESSE`,
  nur Vorschlag, gespeichert als Freitext `beschluss`): `Miteinreichen`,
  `Unterstützen`, `Stimmfreigabe`, `Ablehnen`, `Ablehnungsantrag stellen`.
- **Priorität** (Vorstoss und Geschäft): `''`, `hoch`, `mittel`, `tief`;
  ungültig → `''`.
- **Import:** `VorstossImportService` übernimmt Dateien aus
  `Fraktion/40_Vorstösse` ohne Duplikate; der Dokument-Ordner-Hinweis in der
  Bearbeitung lautet `Fraktion/40_Vorstösse/{10_Eigene|20_Fremde}/V{id}-*`.
- **Verknüpfen** (`VorstossService::verknuepfen`): setzt `geschaeftId`, Status
  `erledigt`, und übernimmt die Vorstoss-Priorität via
  `GeschaeftService::aktualisiereInterneFelder`. Wirft dieser Schritt (Geschäft
  nicht mehr vorhanden), wird die Exception gefangen — die Verknüpfung bleibt,
  nur die Prioritäts-Übernahme entfällt.

### Notizen: EIN geteilter Code für Geschäft und Vorstoss

Notizen dürfen NIE je Objektart eigen implementiert werden. Sowohl Geschäfte als
auch Vorstösse nutzen denselben Code:

- **Backend** `Service/NotizService.php` — die einzige Quelle für Notiz-Logik
  (Hinzufügen, Bearbeiten mit Versions-Archivierung, Soft-Delete, Undo,
  Revisionen, Liste). Parametrisiert über `objektTyp` (`geschaeft` | `vorstoss`)
  und `objektId`. `FraktionsarbeitService` und `VorstossService` delegieren
  ausschliesslich hierher (kein eigener Notiz-Code); der Guard-Test
  `tests/Service/NotizGeteilterCodeTest.php` nagelt das fest.
- **Speicher:** gemeinsame Tabelle `pw_geschaeft_aktionen` mit `aktion_typ =
  'notiz'`, unterschieden durch die Spalte `objekt_typ` (Default `geschaeft`;
  `geschaeft_id` hält die Objekt-ID). Die geschäftseigenen Abfragen
  (`findByGeschaeft`, Beschluss/Votum) filtern `objekt_typ = 'geschaeft'`, damit
  sich Geschäft- und Vorstoss-IDs nicht überschneiden. Versionen liegen in
  `pw_notiz_revisionen` (`aktion_id`). Migration V31 ergänzt `objekt_typ`, V32
  überführt bestehende Vorstoss-JSON-Notizen (`pw_vorstoesse.notizen`) in die
  gemeinsame Tabelle.

  **Migrations-Regel (wichtig):** Schema-Änderungen IMMER über Doctrine
  (`changeSchema` / `ISchemaWrapper`), nie über Raw-SQL in `postSchemaChange`.
  Raw-`ALTER TABLE … ADD COLUMN` in `postSchemaChange` greift in frischen
  Installationen NICHT zuverlässig (nur beim Upgrade bestehender Instanzen) —
  dadurch fehlten `geloescht` (raw in V23) und anfangs `objekt_typ` (raw in V31)
  in frischen Installationen, worauf Soft-Delete/Undo bzw. das Laden von Notizen
  HTTP 500 warf. Konsequenz: V31 deklariert `objekt_typ` UND `geloescht` per
  Doctrine (idempotent via `hasColumn`); Doctrine legt fehlende Spalten an und
  fasst vorhandene nicht an. Dieselbe Fehlklasse traf zuvor ganze Tabellen
  (`pw_vorstoesse`, `pw_notiz_revisionen`, `pw_sitzung_geschaeft`) → über
  Doctrine-`createTable` in V29/V30 behoben. Raw-SQL in `postSchemaChange` nur
  noch für reine DATEN-Migrationen bestehender Instanzen (z.B. V32:
  JSON-Notizen → Aktionen).
- **Semantik:** jede bewusste Speicherung (✓) archiviert die bisherige Fassung
  als Revision (unveränderter Text erzeugt keine Revision); Löschen setzt nur
  `geloescht=true` (Text/History bleiben); Undo setzt es zurück. Nur der Autor
  (`autor_uid` = aktuelle UID) darf bearbeiten/löschen/wiederherstellen. Es gibt
  keinen Autosave und kein Zwischenspeichern — der frühere PUT-Parameter
  `zwischenspeichern` ist entfallen.
- **REST:** identische Endpunkte für beide Objektarten unter
  `/geschaefte/{id}/notizen…` bzw. `/vorstoesse/{id}/notizen…` (GET Liste,
  POST anlegen, PUT `{aktionId}` (nur `text`), DELETE `{aktionId}`,
  POST `{aktionId}/wiederherstellen`, GET `{aktionId}/revisionen`). Vorstoss-
  Listen liefern die Notizen vorab angereichert als `aktionen`
  (`VorstossService::mitNotizen`, Batch-Query, kein N+1).
- **Frontend** `components/NotizenListe.vue` — die eine geteilte Komponente
  (Liste, Inline-Editor mit Versions-Blättern, «+ Neue Notiz», Speichern nur
  über ✓/✕, Warnung vor ungespeicherten Änderungen beim Verlassen). Gelöschte
  Notizen erscheinen NICHT in dieser Liste, sondern als Vermerk «… hat seine
  Notiz gelöscht» samt Wiederherstellen in `components/Aktionszeitleiste.vue`.
  `GeschaeftDetail.vue` und `Vorstoesseliste.vue` binden `NotizenListe` mit
  `basis-url` (`geschaefte/{id}` bzw. `vorstoesse/{id}`) ein und reichen
  `basis-url`/`aktuelle-uid` an die Aktionszeitleiste weiter; keiner enthält
  eigenen Notiz-Editor-Code. **Sitzungsnotizen am Traktandum:**
  `Sitzungsliste.vue` bindet dieselbe `NotizenListe` (Kategorie `sitzungsnotiz`)
  je Geschäft ein — und daneben dieselbe geteilte `Aktionszeitleiste`, damit
  gelöschte Sitzungsnotizen auch dort (die Traktandenliste hat sonst keine
  Aktionszeitleiste) als Vermerk mit Wiederherstellen erscheinen; sie wird nur
  eingeblendet, wenn eine Sitzungsnotiz des Geschäfts gelöscht ist.
- **Kategorie (`notiz` | `sitzungsnotiz`):** alle Notiz-Methoden (Service,
  `FraktionsarbeitService`, `NotizService`, Mapper `findNotizen`/
  `findNotizenFuerObjekte`) nehmen einen `kategorie`-Parameter (Default `notiz`),
  der als `aktion_typ` gespeichert und gefiltert wird. Der `GeschaeftController`
  liest `kategorie` aus dem Request (Whitelist `notiz`/`sitzungsnotiz`); alle
  Geschäfts-Notiz-Endpunkte akzeptieren ihn (per Body oder Query `?kategorie=`),
  plus `GET /geschaefte/{id}/notizen?kategorie=…`. `NotizenListe.vue` hat eine
  `kategorie`-Prop und schickt sie bei jeder Anfrage mit. So verwaltet dieselbe
  Komponente reguläre Notizen ODER Sitzungsnotizen desselben Geschäfts getrennt.

  **Sitzungsnotizen** sind Notizen der Kategorie `sitzungsnotiz` am GESCHÄFT
  (nicht an der Sitzung), damit sie an allen aktuellen und künftigen mit dem
  Geschäft verknüpften Sitzungen erscheinen (auch nach Verschieben auf eine
  spätere Sitzung). Erfasst werden sie in `Sitzungsliste.vue` an Traktanden mit
  Geschäftsbezug (dort steht statt der Traktandum-Notiz eine `NotizenListe` mit
  `kategorie="sitzungsnotiz"` und `basis-url="geschaefte/{geschaeftId}"`; die
  ID liefert `geschaeftIdVon(t)`, geladen wird per `ladeSitzungsnotizen(gid)` in
  den State `sitzungsnotizen[gid]`). Traktanden **ohne** Geschäft und die
  Sitzungs-eigenen Notizen behalten ihre `SitzungNotizen.vue` — die leichte,
  `model-value`-basierte Inline-Liste, deren Einträge (`{ datum, uid,
  displayName, text }`) als JSON-Blob an der Sitzung/am Traktandum hängen (keine
  Revisionen, kein Soft-Delete, keine Backend-Aktionen). Die beiden Komponenten
  sind bewusst getrennt: `NotizenListe.vue` = backend-gebundene, revisionierte
  geteilte Notizen (Geschäft/Vorstoss/Sitzungsnotiz); `SitzungNotizen.vue` =
  Sitzungs-/Traktandum-Inline-Notizen ohne Geschäftsbezug. `GeschaeftDetail.vue`
  zeigt die Sitzungsnotizen separat unter den Notizen in einem `<details>`
  (standardmässig eingeklappt); die Aktionszeitleiste blendet `notiz` UND
  `sitzungsnotiz` aus. Die Spalte `pw_geschaeft_aktionen.objekt_typ`/`geloescht`
  werden per Doctrine (V31/V33) zuverlässig geführt.

### Verknüpfen mit einem Geschäft (Vorstoss→Geschäft UND eigenes→offizielles)

EIN geteilter Dialog `components/GeschaeftVerknuepfenDialog.vue` (kein
Copy-Paste): lädt die Geschäfte selbst (`GET /geschaefte?limit=500`), filtert
nicht gelöschte, optional per Suchtext über Titel/Nummer (lowercase `includes`),
sortiert nach absteigender Titel-Ähnlichkeit zum Quell-Titel; bei Gleichstand
`String(b.datum).localeCompare(a.datum)` (neueste zuerst). Props: `titel`
(Quell-Titel), `ausschlussId` (dieses Geschäft nicht anbieten), `nurOffizielle`
(nur Parlamentsgeschäfte, keine `eigen:`), `inklusiveErledigt` (lädt mit
`show_erledigt=1`, damit ein bereits erledigtes offizielles Geschäft auffindbar
bleibt), `kopf`; Events `verknuepfen(geschaeft)` und `schliessen`. Das
Ähnlichkeitsmass `titelAehnlichkeit(a, b)` liegt in `utils.js` (Anzahl gemeinsamer
kleingeschriebener Tokens aus `split(/\W+/)` mit Länge > 2; kein
Fuzzy-/Levenshtein-Mass) und wird von beiden Aufrufern genutzt.

- **Vorstoss→Geschäft** (`Vorstoesseliste.vue`): `POST /vorstoesse/{id}/verknuepfen`
  mit `geschaeftId`; der Vorstoss wird `erledigt`, seine Priorität wandert ins
  Geschäft (`VorstossService::verknuepfen`).
- **Eigenes→offizielles Geschäft** (`GeschaeftDetail.vue`, nur bei `eigen:` und
  nicht neu; `nurOffizielle=true`, `inklusiveErledigt=true`,
  `ausschlussId=geschaeftId`): `POST /geschaefte/{id}/verknuepfen` mit
  `zielGeschaeftId` → `FraktionsarbeitService::verknuepfe` (dort, weil Notizen,
  Zuständigkeit und Geschäftsfelder gebündelt sind). Ablauf:
    - Prüft, dass die Quelle ein `eigen:`-Geschäft ist (sonst 400); setzt am eigenen
      `verknuepft_geschaeft_id` (Spalte via Migration V35) und `status='erledigt'`.
    - **Übertrag «sofern beim Ziel leer»**: skalare Felder (`prioritaet`, `typ`,
      `kommission`, `datum`, `inhalt`) via `UEBERTRAG_FELDER` nur, wenn beim Ziel
      leer; Zuständigkeit via `zustaendigkeitenSetzen`, nur wenn das Ziel keine
      aktive hat.
    - **Notizen**: `GeschaeftAktionMapper::verschiebeNotizen(von, zu)` verschiebt die
      Notiz-Aktionen (`aktion_typ IN ('notiz','sitzungsnotiz')`, `objekt_typ='geschaeft'`)
      per `UPDATE geschaeft_id`. `NotizRevision` hängt an der Aktions-ID und wandert
      dadurch automatisch mit.
    - Der Controller publiziert `geschaefte.updated` für BEIDE Ids (eigenes + Ziel).
  Gegenseitige, anklickbare Verlinkung: das Ziel liefert seine verknüpften eigenen
  Geschäfte über `GET /geschaefte/{id}/verknuepfte-eigene` →
  `FraktionsarbeitService::verknuepfteEigene` (nutzt `GeschaeftMapper::findByVerknuepft`);
  das eigene Geschäft trägt in `angereichertesGeschaeft` das Feld `verknuepftGeschaeft`
  (`{id, nummer, titel}`). `GeschaeftDetail` rendert beide Verweise als
  `.pw-verweis-knopf` und meldet einen Klick über das Event `oeffneGeschaeft(id)`,
  das `Geschaeftsliste` mit `oeffneDetail(id)` in derselben Detail-Maske öffnet.

### Votum-PDF

Route `GET /apps/parlwin/geschaefte/{id}/votum/pdf` →
`GeschaeftController::votumPdf` (`#[NoAdminRequired]`, `#[NoCSRFRequired]`),
liefert `TemplateResponse('votum_pdf', $daten, 'blank')` aus
`templates/votum_pdf.php`. Kein serverseitiges PDF: eigenständige HTML-Seite,
`@page A4` mit Rand `2.2cm 2cm 2.4cm 2cm`, öffnet nach `document.fonts.ready`
automatisch `window.print()` (Nutzer wählt «Als PDF speichern»). Inhalt:
Kopf «Votum im Rat» + Geschäftstitel; Meta-Tabelle mit Geschäftsnummer
(`externId`), «Zuständig (Fraktion)» (Namen der aktiven Zuständigkeiten, die
Hauptzuständige mit Zusatz «(Hauptverantwortung)»), «Letzter Beschluss der
Fraktion» (roher `letzterBeschluss.aktionCode` — NICHT das Label — plus
`erstelltAm`), «Erfasst von» (`autorName`), «Stand» (`erstelltAm` des Votums
oder aktuelle Zeit); Abschnitt «Wortlaut» mit dem TipTap-HTML des Votums
(sanitisiert: `script/style/iframe/object/embed/link/meta` und `on*`-Attribute
entfernt) oder «— Noch kein Votum erfasst —»; Fusszeile mit Geschäftsnummer und
Ausdruckdatum.

### Filter-Grundprinzip: nur vorkommende Werte anbieten (verbindlich für JEDEN Filter)

Ein Werte-Filter (Auswahlliste/Mehrfachauswahl, die eine Liste einengt) bietet
**genau die Werte an, die im zugrunde liegenden Datenbestand für dieses Feld
tatsächlich vorkommen** — nie die ganze Domäne. Eine Option, die keinen einzigen
Treffer ergäbe, ist unnützer Ballast und führt zu leeren Trefferlisten; sie wird
weggelassen. Der neutrale Eintrag «Alle»/`''` (kein Filter) ist kein Datenwert und
bleibt immer.

- **Herleitung aus den geladenen Daten**, nicht aus einer Stammdaten- oder
  Konstantenliste: die Optionen entstehen als `new Set(...)` über die sichtbare
  Datenmenge (bzw. über die vom Server gelieferte Ansicht bei serverseitigen
  Filtern). Beispiele: `alleStatus`/`alleTypen`/`einreicherOptionen`/
  `parteiOptionen`/`alleBeschluesse` in `Geschaeftsliste.vue`,
  `jahrOptionen`/`kommissionOptionen`/`departementOptionen` in `Budgetliste.vue`.
- **Feste Reihenfolge bleibt erhalten:** wo eine Domänenliste eine sinnvolle
  Reihenfolge vorgibt (Prioritätsstufen, Vorstoss-Status), wird über diese Liste
  **gefiltert** (`DOMAENE.filter(o => vorhanden.has(o.value))`), statt die
  vorkommenden Werte neu zu sortieren.
- **Gegen dasselbe Feld gründen, gegen das gefiltert wird:** der
  Zuständigkeitsfilter bietet nur `hauptZustaendigePerson`-Werte, die an einem
  Geschäft gesetzt sind (nicht jedes Mitglied); der Mitglieder-Kommissionsfilter
  nur Kommissionen, denen ein sichtbares Mitglied angehört (nicht jede
  Kommission); der Funktionsfilter nur Funktionen, die ein Mitglied wirklich hat.
- **Abhängigkeit von vorgeschalteten Schaltern:** hängt die Datenmenge an einem
  Schalter (z.B. «Nur aktive Mitglieder» → `basisMitglieder`), gründen die
  Filteroptionen auf **dieser** Menge, nicht auf dem Rohbestand.
- **Nur für FILTER, nicht für Eingabe-/Bearbeitungsfelder:** ein Feld, das einen
  Wert **setzt** (Zuständigkeit/Beschluss/Herkunft im Bearbeiten-Dialog), bietet
  weiterhin die ganze zulässige Domäne an — dort geht es nicht ums Einengen.
- Jeder Filter wird mit einem npnp-Test festgenagelt, der ohne die Gründung rot
  ist (eine nicht vorkommende Option erscheint). Hintergrund: die Zuständigkeits-
  Filterliste bot alle Mitglieder an — post-mortems/2026-08-24-filter-bietet-nicht-vorhandene-werte.md.

### Standard-Sortierungen und Filter-Grundzustände (aus `data()`)

- **Geschäfte** (`Geschaeftsliste.vue`): `sortFeld='datum'`,
  `sortRichtung='desc'` (Standard nach Datum absteigend; `datum` ist keine
  klickbare Spalte). Klickbare Sortierspalten: `nummer`, `titel`, `status`
  (erster Klick `asc`, gleiche Spalte toggelt). `nummer` wird für die Sortierung
  auf `JAHR.NNNN` mit führenden Nullen normalisiert. `zeigeErledigte=false`
  (erledigte ausgeblendet). Mehrfach-Filter (Grundzustand jeweils leer):
  `filterStatus`, `filterTyp`, `filterZustaendige`, `filterBeschluss`,
  `filterPrioritaet` (alle `[]`), `filterEntscheidungsbedarf=''`
  (Alle/Nur Entscheid nötig/Nur ohne offenen Entscheid). `resetFilter` leert
  alle inkl. `zeigeErledigte=false`. Status-Spalte ausgeblendet, wenn genau ein
  Status gefiltert ist.
- **Mitglieder** (`Mitgliederliste.vue`): `sortierModus='funktion'`,
  `nurAktive=true`, `filterFunktion=''` («Alle Funktionen»). Sortieroptionen:
  `funktion`, `fraktion`, `partei`, `name`. Funktions-Sortierung: nach
  Fraktionsrolle-Rang, dann Partei, dann Name.
- **Kommissionen** (`Kommissionsliste.vue`): `nurAktive=true`,
  `nurAktiveMitglieder=true`.
- **Sitzungen** (`Sitzungsliste.vue`): `nurKuenftige=true`; kommende
  aufsteigend, vergangene absteigend.
- **Vorstösse** (`Vorstoesseliste.vue`): `herkunftOption` und `statusOption`
  beide Grundzustand `{value:''}` («Alle»).

### Berechtigungsmodell

- **Lesen und Schreiben der Fach-Daten** (Geschäfte, Vorstösse, Sitzungen,
  Traktanden, Mitglieder, Kommissionen, Fraktionen, Sitzungstypen; alle
  zugehörigen Controller): durchgängig `#[NoAdminRequired]` OHNE
  Gruppen-Prüfung im Code. Jede/r angemeldete Nextcloud-Nutzer/in, der/die die
  App aufrufen kann, sieht und bearbeitet alle Fach-Daten. Vertraulichkeit
  entsteht dadurch, dass die Nextcloud-Instanz der Fraktion gehört und/oder die
  App in Nextcloud auf die Fraktionsgruppe (`nextcloud_gruppe`) beschränkt
  wird — nicht durch einen In-App-Filter.
- **Nur Administrator** (`#[AuthorizedAdminSetting(AdminSettings::class)]`):
  Fraktion + Gruppe wählen, Mitglieder provisionieren/deaktivieren, Sync
  starten/abbrechen, Sync-Zeitplan, Status-/Namens-Kürzel.
- **Rollenabhängig** (Controller `#[NoAdminRequired]`, Prüfung im
  `FraktionsarbeitService`; Verletzung → HTTP 403 `{fehler}`):
    - Sitzungsmodus an/aus, Protokollführung setzen, Präsidiums-Stellvertretung,
      Kommissionsmitglied-Rolle: `kannPraesidiumHandeln` =
      `istFraktionsGruppenAdmin` ODER aktive Rolle `fraktionspraesident` ODER
      `fraktionspraesident_stellvertretung`.
    - Fraktionspräsidium setzen: nur `istFraktionsGruppenAdmin`.
    - Protokoll-Stellvertretung: `kannPraesidiumHandeln` ODER
      `kannProtokollfuehrungHandeln`.
    - Beschluss schreiben: erlaubt, ausser Sitzungsmodus aktiv → dann nur
      `kannProtokollfuehrungHandeln` (Rolle `protokollfuehrer` oder
      `protokollfuehrer_stellvertretung`, plus Legacy-Config-UID).
    - Votum schreiben/archivieren: nur `istNutzerZustaendig` (Mitglied über NC-UID
      gemappt, dessen `extern_id` in den aktiven Zuständigkeiten steht).
    - Notiz bearbeiten/löschen/wiederherstellen: nur wenn `autorUid` = aktuelle
      UID.
    - `istFraktionsGruppenAdmin` = Instanz-Admin ODER Gruppen-Admin/Sub-Admin
      der konfigurierten Fraktionsgruppe.
- **Rollen-Codes** (`FraktionsarbeitService`): `fraktionspraesident`,
  `fraktionspraesident_stellvertretung`, `protokollfuehrer`,
  `protokollfuehrer_stellvertretung`, `kommissionsmitglied`. Stellvertretungen
  tragen `gueltig_von`/`gueltig_bis`; `bis` vor `von` wird abgewiesen.

### Gemeinsamer Fraktionsraum (`FraktionsraumService`)

- Wird bei jedem App-Aufruf (`PageController::index` → `sicherstellen()`)
  idempotent geprüft; übersprungen, wenn `nextcloud_gruppe` leer ist oder die
  Gruppe nicht existiert.
- **Ordnerstruktur** (`ORDNER_STRUKTUR`, im `admin`-Konto angelegt, vollständig):
  `Fraktion`, `Fraktion/00_Allgemein`, `Fraktion/10_Sitzungen`,
  `Fraktion/10_Sitzungen/2026`, `Fraktion/20_Geschäfte`,
  `Fraktion/30_Kommissionen`, `Fraktion/30_Kommissionen/Aufsichtskommission`,
  `Fraktion/30_Kommissionen/Sachkommission Bildung Sport Kultur`,
  `Fraktion/40_Vorstösse`, `Fraktion/40_Vorstösse/10_Eigene`,
  `Fraktion/40_Vorstösse/20_Fremde`, `Fraktion/50_Wahlkampf`,
  `Fraktion/60_Medien`, `Fraktion/90_Archiv`.
- **Umbenennungen vor dem Anlegen** (`ORDNER_UMBENENNUNGEN`, damit die neue
  Nummer 40_Vorstösse nicht kollidiert): `Fraktion/40_Wahlkampf` →
  `Fraktion/50_Wahlkampf`, `Fraktion/50_Medien` → `Fraktion/60_Medien`. Liegen
  beide (alt + neu) vor, wird der Inhalt zusammengeführt (gleichnamige Ziele
  bleiben unberührt, der alte Ordner dann nicht gelöscht).
- **Freigabe** (`ORDNER_PERMISSIONS` = `READ|CREATE|UPDATE|DELETE`): Gruppen-Share
  über die Share-API (Typ GROUP) mit `nextcloud_gruppe`; bei JEDEM Lauf wird der
  Share für alle aktuellen Mitglieder explizit akzeptiert und der Mountpunkt auf
  `/Fraktion` gesetzt (idempotent; deckt später hinzugekommene Mitglieder ab).
- **Konflikt-Auflösung pro Mitglied:** eigener `Fraktion`-Ordner → nach
  `Fraktion.bak` (eindeutiger Name) sichern, eigene Gruppen-Freigaben lösen,
  Inhalt in den offiziellen Ordner kopieren (Namenskonflikt → `<name>.migrated`),
  Sicherung behalten; fremde Freigabe → verlassen; bereits admin-eigener Ordner
  → unverändert.
- Zusätzlich: gemeinsamer Kalender (`KalenderService`) und Deck-Board
  (`DeckService`) im `admin`-Konto, geteilt mit derselben Gruppe.

### Auto-Zuständigkeit nach Sync (`FraktionsarbeitService`)

Nach erfolgreichem Sync werden Geschäfte OHNE aktive Zuständigkeit automatisch
zugewiesen. Vorrang: einreichende Mitglieder der eigenen Fraktion (in
Einreicher-Reihenfolge; Match primär über `extern_id`, sonst über den Namen in
BEIDEN Reihenfolgen «Vorname Nachname» und «Nachname Vorname», da die Webseite
«Nachname Vorname» liefert). Sonst die Mitglieder der Kommission, auf die der
Geschäftsstatus verweist (Token-Match wie `Kommissionsliste.vue::geschaefteFuer`).
Bestehende Zuweisungen werden nie überschrieben. Details/Statusbezug:
[README, Internas](README.md#internas).

### Sync-Status, Abbruch und Resume

Fortschritt liegt in der App-Konfiguration `sync_progress` (JSON). Phasen:
`idle`, `queued`, `running`, `abbruch_angefragt`, `abgebrochen`, `abgeschlossen`,
`fehler`. Live-Anzeige (aktueller Bereich, `processed/total`, Laufzeit, ETA)
und Endpunkte `GET /apps/parlwin/sync/status`, `POST /apps/parlwin/sync/cancel`,
`POST /apps/parlwin/sync/run`: siehe [README, Administration →
Synchronisation](README.md#administration). Doppellauf-Schutz über
`SyncLockService` (Lock) plus lebender Worker-Prozess; ein erneuter Start
während eines Laufs liefert `bereits_laufend`. Ist die letzte Phase `fehler`
oder `abgebrochen` (oder ein Stale-Abbruch), ermittelt der nächste Start
Resume-Cursor je Bereich (`ermittleResumeCursors`) und setzt dort fort
(Label «Synchronisation wird fortgesetzt»); ein hängender Prozess wird über
`SyncProcessService::ensureStopped` (SIGTERM, dann SIGKILL) beendet.

Sync-Zeitplan (`SyncZeitplan`): gespeichert als `sync_zeitplan` (JSON-Liste
`[{tage:[1..7], zeit:"HH:MM"}]`, 1 = Montag … 7 = Sonntag, Europe/Zurich).
`SyncJob` (Intervall 300 s) ist fällig, wenn zwischen dem letzten Prüfzeitpunkt
(`sync_zeitplan_letzter_check`, exklusiv) und jetzt (inklusiv) ein Zeitplan-Punkt
liegt — so werden verpasste Punkte nach einem Ausfall nachgeholt und ein
Doppellauf verhindert; die erste Prüfung nach Aktivierung initialisiert nur.
Ohne konfigurierten Zeitplan gilt `SyncZeitplan::standard()`: zwei Einträge an
allen Wochentagen um 10:00 und 18:00 Uhr; die Admin-API liefert diesen Standard
vorbelegt (`mitStandard`), sodass er im UI editierbar erscheint.

### Budget: Datenmodell, Import und Engines

Fachlicher Hintergrund: [doc/budget-prozess-und-parlwin.md](doc/budget-prozess-und-parlwin.md).
Die Budgetbücher der Stadt Winterthur sind die Datenquelle: **Teil B**
(Produktegruppen-Globalbudgets) gliedert `Departement → Produktegruppe (Code) →
Produkt`; die Produktegruppe trägt das beschlussfähige **Globalbudget (Nettokosten)**,
das Produkt ist Informationsteil. **Teil A** enthält Beschluss, Steuerertrag/Steuerfuss,
Erfolgsrechnung-Übersichten und den Investitionsplan. Die PDF tragen sauberen,
eingebetteten Text (keine Scans) — die Extraktion erfolgt server-seitig in PHP.

#### Datenmodell (Tabellen `pw_budget_*`)

- **`pw_budget_jahr`** — ein importiertes Budgetjahr: `jahr` (PK), `steuerfuss_geltend`
  (Prozent, z.B. 125), `steuerertrag` (CHF, aus Teil A «Steuerertrag und Steuerfuss»),
  `personalsteuer_pro_person`, `weisung_quelle` (URL/fileId), `novemberbrief_quelle`,
  `novemberbrief_importiert` (bool), `erstellt_am`. `wert_pro_steuerprozent` wird
  berechnet: `steuerertrag / steuerfuss_geltend` (nicht gespeichert).
- **`pw_budget_departement`** — `jahr`, `code`, `name`, `reihenfolge` (Buchreihenfolge).
- **`pw_budget_produktegruppe`** — je `(jahr, code)`: `departement_code`, `name`,
  `reihenfolge`, `auftrag` (Text), Zielvorgaben-Block (Text). Zahlen je Spalte des
  Buches (`ist` = Jahr−2, `soll_vorjahr` = Jahr−1, `soll` = Budgetjahr, `plan1..plan3`
  = FAP): `globalkredit_*` (Nettokosten, die beschlussfähige Zahl), `aufwand_*`
  (Total effektive Kosten — Verteilbasis), `ertrag_*` (Total effektive Erlöse),
  `stellen_*` (Stelleneinheiten), `auszubildende_*`. Texte: `erlaeuterung_stellen`,
  `begruendung_abweichung`, `begruendung_fap`, `massnahmen`.
- **`pw_budget_produkt`** — `produktegruppe_id`, `nummer`, `name`, `leistungen` (Text),
  `kosten_*`/`erloes_*`/`nettokosten_*` (Ist/Soll_Vorjahr/Soll), `leistungsmengen`
  (Text). Reine Information, nicht beschlussfähig.
- **`pw_budget_investition`** — `jahr`, `departement_code`, `cluster`, `projekt`,
  `bu` (Budgetjahr), `fap1..fap3`, `gesamtkosten`, `bereits_getaetigt`,
  `planungskosten`, `reihenfolge`. Investitionen sind **nicht** nach Produktegruppe
  gegliedert, sondern nach Departement/Cluster/Projekt.
- **`pw_budget_antrag`** — ein Antrag: `jahr`, `bereich`
  (`globalbudget|personal|investition|steuerfuss`), `ziel_typ`
  (`produktegruppe|investition|steuerfuss`), `ziel_ref` (Produktegruppen-Code bzw.
  Investitions-Id bzw. leer), `betrag_delta` (CHF, negativ = Kürzung), `stellen_delta`
  (nur Personal), `betrag_pro_stelle`, `quelle` (`manuell|pauschal|steuerfuss`),
  `antragsteller` (Fraktion/Person; eigene wie fremde), `begruendung`,
  `verteilung_id` (gesetzt bei automatisch erzeugten Anträgen aus einer
  Verteilrechnung → erkennbar und filterbar, F85), `reihenfolge`, `erstellt_von`,
  `erstellt_am`. Anträge nur auf Produktegruppen (F81), Personal (F86), Investitionen
  (F87) und Steuerfuss (F88, ans Ende der Liste).
- **`pw_budget_verteilung`** — Zustand der automatischen Verteilung je `jahr`:
  `automatik_ein` (Default true, F84), `ziel_modus` (`schwarze_null|defizit|ertrag`),
  `ziel_betrag` (bei Defizit/Ertrag). Bei jeder Neuberechnung werden die
  `pw_budget_antrag`-Zeilen mit passender `verteilung_id` erzeugt/angepasst/gelöscht.
- **`pw_budget_antrag_entscheid`** — Live-Verfolgung (F93): `antrag_id`, `status`
  (`offen|angenommen|abgelehnt`, Default `offen`), `geaendert_von`, `geaendert_am`.
  Der Entscheid hängt am Antrag (nicht an einer einzelnen Sitzung) und erscheint
  darum in **allen** Sitzungen, die dasselbe Budget traktandieren — analog zur
  Sitzungsnotiz zum Geschäft (F39), über denselben geteilten Mechanismus
  (Budget-Geschäft ↔ Sitzungen via `SitzungGeschaeftService`/`SitzungVorstossService`,
  siehe «Verknüpfen mit einem Geschäft»).

#### Import-Pipeline (`BudgetImportService`)

- **Text-Extraktion:** PHP-Bibliothek `smalot/pdfparser` (rein PHP, läuft im
  shell-losen Runtime-Image). Kein `pdftotext`-Shell-Aufruf im Auslieferungs-Image.
- **Teil-B-Parser** (Anker, positionsunabhängig — F89-Toleranz):
  - **Inhaltsverzeichnis:** `Departement <Name>` als Gruppenkopf, darunter Zeilen
    `<Produktegruppenname> (<Code>) …… <Seite>` → Departement→Produktegruppe(Code)-Baum
    und Reihenfolge.
  - **Je Produktegruppe:** Überschrift `<Name> (<Code>)`; die **Globalkredit**-Zeile
    `Nettokosten / Globalkredit` mit sechs Zahlenspalten (Ist/Soll_Vorjahr/Soll/Plan×3)
    → `globalkredit_*`; im **Informationsteil** die Zeilen `Total effektive Kosten`
    → `aufwand_*`, `Total effektive Erlöse` → `ertrag_*`; die **Stellenplan**-Zeilen
    `Stelleneinheiten` → `stellen_*`, `Auszubildende` → `auszubildende_*`; die
    Textblöcke `Erläuterungen zum Stellenplan`, `Begründung Abweichung …`,
    `Begründung FAP`, `Wesentliche Massnahmen …`; die **Produkte** `Produkt <N> <Name>`
    mit ihren `Nettokosten`/`Leistungsmengen`.
  - **Marker** «Zum Beschluss» / «Informationsteil» kommen als gesperrter Text
    (`▼Z u m  B e s c h l u s s …`) → vor dem Matchen Leerzeichen zwischen
    Einzelbuchstaben zusammenziehen.
  - **Zahlen:** Schweizer Format `1'234'567` (Apostroph als Tausendertrenner) →
    normalisieren; leere Zellen = 0; Prozent-Spalten (`in%`) ignorieren.
- **Teil-A-Parser:** Beschluss (`Der Steuerfuss … auf <N> Prozent`, Personalsteuer);
  der **Gesamt-Steuerertrag** aus dem Fliesstext `Die erwarteten Steuererträge belaufen
  sich auf <N> Millionen Franken` → `steuerertrag` (Millionen → ganze Franken; die
  frühere Anker-Zeile «Steuerertrag und Steuerfuss» war eine Inhaltsverzeichnis-Zeile
  und lieferte die Seitenzahl statt des Betrags).
- **Investitionen je Projekt** aus dem Anhang «Investitionsplanung Verwaltungsvermögen»
  (`BudgetBuchParser::parseInvestitionen`): Die Tabelle trägt pro Zeile `<Nr>
  <Bezeichnung> <Budget Vorjahr> <Budget Budgetjahr> <Plan+1> <Plan+2> <Plan+3>`,
  gruppiert nach `Departement <Name>` und Produktegruppe `<Name> (PG)`. Zeilen mit
  mindestens 6-stelliger Konto-Nr (z.B. 5001750) sind Projekte; ein- bis dreistellige
  Nr sind Total-/Bereichs-/Produktegruppen-Summen und werden übersprungen. **Robust
  gegen Jahreszahlen IM Bezeichnungstext** (z.B. „… 2028“): die fünf Wertspalten sind
  immer die LETZTEN fünf Zahlen der Zeile, die Konto-Nr die erste — nie über feste
  Spaltenpositionen. Gemappt: `departement`, `cluster` (Produktegruppe), `projekt`
  (`<Nr> <Bezeichnung>`), `bu` (Budgetjahr = zweite Wertspalte), `fap1..fap3` (Planjahre).
  Die Sektion endet an der nächsten Investitionsplanung (Eigenwirtschaftsbetriebe/
  Finanzvermögen) — über den Steuerhaushalt entscheidet das Parlament. `gesamtkosten`/
  `bereits_getaetigt`/`planungskosten` stehen in dieser Tabelle nicht und bleiben 0
  (die Tabelle führt Budget-/Planwerte je Jahr, keine kumulierten Projektkosten). Der
  Parser ist projektweise gegen das Buch validiert (`BudgetBuchParserTest`).
- **Novemberbrief-Parser (F90):** kleineres PDF mit Anpassungen je Produktegruppe →
  Deltas auf die bestehenden `pw_budget_produktegruppe`-Zahlen; setzt
  `novemberbrief_importiert = true`.
- **Auslöser:** automatisch über `SyncJob` (`BudgetImportService::automatischerImport`):
  im geplanten Hintergrundlauf wird das neueste verfügbare Budgetjahr eingelesen, sobald
  dessen Weisung vorliegt und es noch nicht importiert ist; ein vorhandener, noch nicht
  eingelesener Novemberbrief wird nachgezogen. Vergangene Jahre bleiben dem manuellen
  Import über «Neu» vorbehalten (Jahr wählen, nur Budget oder mit Novemberbrief) plus
  dem Novemberbrief-Nachlese-Knopf (F91). **Verfügbarkeitsregeln (F75):** ein
  Jahr existiert nur, wenn importiert; ein neues Jahr entsteht Ende des Vorjahres;
  nach dem 1. Dezember des Vorjahres wird ein nicht erzeugtes Budgetjahr ignoriert
  (kein Nachladen). Das Jahr-Auswahlmenü listet nur DB-vorhandene Jahre, neuestes
  vorbelegt.
- **Test-Fixtures und ausgelieferte Daten:** die Budgetbücher der Jahre 2022–2026
  liegen als PDF in `tests/fixtures/budget/<jahr>/` (Git-eingecheckt, damit Parser-Tests
  reproduzierbar sind; **nicht** ins Image kopiert). Daraus erzeugt der Generator
  (`npm run test:budget-data`, Gruppe `generate`) die **ausgelieferten**
  `parlwin/reference/budget/<jahr>/budget.json` — kleine strukturierte Dateien, die ins
  Image gelangen und beim Import gelesen werden. `BudgetImportService.verfuegbareJahre()`
  listet die Jahre mit `budget.json` (oder PDF), neuestes zuerst. Neue Bücher: PDF nach
  `tests/fixtures/budget/<jahr>/` legen, `npm run test:budget-data` laufen lassen, die
  erzeugte `budget.json` committen.
- **Composer-Abhängigkeit:** `parlwin/vendor/` (smalot) wird im Image-Build (Dockerfile
  `php-deps`-Stage) erzeugt und ist git-ignoriert; lokal für `npm run test:pdf`/
  `test:budget-data` via `composer install` bereitstellen. Der schnelle Host-Lauf
  (`npm run test:unit`, `run-all.sh`) schliesst die Gruppen `pdf` und `generate` aus, die
  vendor brauchen bzw. Dateien erzeugen.

#### Engines (`BudgetRechnung`)

- **Summen (F79):** je aktivem Filter (Departement/Kommission) — Ausgaben = Σ
  `aufwand_soll`, Einnahmen = Σ `ertrag_soll` (zzgl. Steuern), Ertrag/Defizit =
  Einnahmen − Ausgaben, Total Stellen = Σ `stellen_soll`, jeweils mit Differenz zum
  Vorjahr (`*_soll_vorjahr`). Alle Anträge (manuell + pauschal + Personal + Steuerfuss)
  fliessen live in das angepasste Ergebnis ein.
- **Anteilige Verteilung (F83/F85):** Zielbetrag `benoetigte_kuerzung =
  aktuelles_ergebnis − ziel` (ziel = 0 bei schwarzer Null, sonst gewünschtes
  Defizit/Ertrag). Verteilung **proportional zum Aufwand** über die nach Filter
  beschlussfähigen Produktegruppen: `delta_i = benoetigte_kuerzung · aufwand_i /
  Σ aufwand`. Rundung Schweizer Franken; Rundungsrest auf die grösste Gruppe. Erzeugt/
  aktualisiert/löscht idempotent die `pw_budget_antrag`-Zeilen mit `quelle=pauschal`
  und gemeinsamer `verteilung_id`.
- **Automatik (F84):** Default `automatik_ein=true` → Überschuss bleibt 1:1 (nur
  Einsparungen werden verteilt, keine automatischen Mehrausgaben); ein Defizit wird
  automatisch als Pauschalkürzung verteilt. Bei `automatik_ein=false` erscheint der
  explizite Verteil-Knopf.
- **Steuerfuss (F88):** `wert_pro_prozent = steuerertrag / steuerfuss_geltend`. Bei
  Überschuss und Automatik: `neuer_steuerfuss = steuerfuss_geltend −
  floor(überschuss / wert_pro_prozent)` (ganze Prozent, abgerundet); der Überschuss
  sinkt um `gesenkte_prozente · wert_pro_prozent`. Manuelle Bearbeitung nur bei
  abgeschalteter Automatik; jede Steuerfuss-Änderung erzeugt einen `pw_budget_antrag`
  mit `bereich=steuerfuss` ans Ende der Antragsliste.

#### API und Frontend

- **`BudgetController`** (Routen in `appinfo/routes.php`): `jahre`,
  `produktegruppen/{jahr}`, `investitionen/{jahr}`, `steuerfuss/{jahr}`, Anträge-CRUD,
  `verteilung/{jahr}` (Ziel setzen/neu rechnen), Import-Trigger (`neu`,
  `novemberbrief`), `antraege-pdf` (F92), Entscheid setzen (F93). Realtime-Events
  `budget.updated` über den bestehenden Broker (F53-Mechanik).
- **Frontend `Budgetliste.vue`** mit vier Untertabs, gemeinsamer Filterleiste
  (Jahr/Kommission/Departement/Kostensteigerung) und sticky Summenzeile. Wiederverwendet
  `PwMultiSelect`, den Beschluss-/Antrags-Workflow und die Echtzeit-Aktualisierung.
- **Anträge-PDF (F92):** wiederverwendung des Votum-PDF-Mechanismus (siehe
  «Votum-PDF») — eine eigenständige Druckseite `TemplateResponse('budget_antraege_pdf',
  …, 'blank')` aus `templates/budget_antraege_pdf.php`, die sich per Browser-Druck
  als PDF speichern lässt. Kein serverseitiges PDF, keine zusätzliche Bibliothek.
  Gesamt oder je Kommission (Departement) gruppiert.
- **PDF-Import (F89):** die einzige zusätzliche Laufzeit-Abhängigkeit ist die reine
  PHP-Bibliothek `smalot/pdfparser` (Textextraktion), im Image-Build via Composer
  installiert; `BudgetBuchParser` lädt sie erst zur Laufzeit. Der Parser ist gegen die
  echten Bücher 2022–2026 justiert (`BudgetBuchParserTest`, Gruppe `pdf`) und erzeugt
  die ausgelieferten `budget.json`. Zur Laufzeit liest der Import bevorzugt die
  strukturierte `budget.json`; liegt für ein Jahr nur das PDF vor, parst er es direkt —
  beide Wege ergeben dieselbe DB-Befüllung (identische Struktur `BudgetBuchParser::struktur`).

#### Zuständige Kommission (F76)

- Die Zuordnung Departement→Sachkommission steht **nicht** in den gescrapten Daten und
  ist darum verwaltungsseitig konfigurierbar: App-Config `budget_kommission_zuordnung`
  (JSON `[{departement, kommission}]`), Editor im Admin (`admin.php` +
  `admin.js`), Endpunkte `settings#get/setBudgetKommissionZuordnung`. `BudgetService::
  ansicht` liefert die Zuordnung mit; ein gewählter `kommission`-Filter wird über
  `erlaubteDepartemente()` auf die zugeordneten Departemente aufgelöst. Frontend: zwei
  verknüpfte NcSelect (Kommission + Departement); ohne Zuordnung erscheint nur der
  Departement-Filter.

#### Phasen und Sitzungsmodus (F93)

- **`pw_budget_antrag.phase`** (Migration Version000039): `fraktion` = interne
  Vorbereitung (eigene und abgesprochene fremde Anträge, auto-verteilt), `sitzung` =
  offizielle Sitzungsanträge der Budgetdebatte. Der **Sitzungsmodus**-Schalter (unten im
  Budget-Filter) sendet `phase` an `ansicht`: dort fliessen nur Anträge der Phase in die
  Summen ein, in der Sitzungsphase zusätzlich nur die **angenommenen** (Live-Entscheid);
  die automatische Pauschalverteilung berücksichtigt nur Fraktions-Anträge.
- **Spiegelung in die Sitzung:** `BudgetSitzungsantraege.vue` (Prop `jahr`) lädt die
  Sitzungsanträge (`phase=sitzung`) und rendert dieselben Antrags-/Entscheid-Elemente
  wie die Budget-Ansicht, cross-session per Realtime. `Sitzungsliste` bindet sie ein,
  wenn eine Parlamentssitzung ein Budget-Traktandum trägt (`budgetJahrFuerSitzung` liest
  das Jahr aus dem Traktandum-Titel «Budget <Jahr>»).
- **Annahme (dokumentiert):** Die offiziellen Sitzungsanträge werden in der
  Sitzungsphase erfasst (aus den Einladungs-/Traktandendokumenten) und live verfolgt;
  ein automatisches Auslesen der Anträge aus beliebigen Einladungs-PDF ist nicht möglich
  und darum nicht implementiert.

#### Antragsmodell: Herkunft, Betrag, Haltung, Unterstützung, Pauschal, Verknüpfung (F94–F104)

Erweiterungen an `pw_budget_antrag` (Migration Version000040) und `pw_budget_verteilung`
(Version000041):

- **Herkunft (F94):** `herkunft` (`eigene|fremde`), wie beim Vorstoss. Der Antragsteller
  wird im Frontend aus einer Liste gewählt: bei eigenen die aktiven Mitglieder (vorbelegt
  mit dem aktuellen Nutzer via `getCurrentUser()`), bei fremden die aktiven Fraktionen
  zuoberst. Gespeichert wird der angezeigte Name in `antragsteller`. Die
  Antragsteller-/Fraktionslisten kommen als Props (`mitglieder`, `fraktionen`) aus `App.vue`.
- **Betrag in CHF und Prozent (F95):** `betrag_delta` (CHF, vorzeichenbehaftet) UND
  `prozent_delta` (Prozent). Die Richtung ist das Vorzeichen (Reduktion −, Mehrausgabe +);
  im Frontend steuert der Umschalter das Vorzeichen (`BudgetAntragForm`). Fehlt einer der
  Werte, berechnet ihn `BudgetService::betragSetzen` aus dem **Budgetwert der Position**
  (`basisFuerPosition`: Globalkredit-Soll der Produktegruppe bzw. `bu` des Projekts). Die
  Live-Rechnung CHF↔% erfolgt zusätzlich im Formular (`betragGeaendert`/`prozentGeaendert`).
- **Steuerfuss in Prozentpunkten (F96):** für `bereich=steuerfuss` sind `prozent_delta`
  Prozentpunkte; der CHF-Effekt = `Ertrag × Prozentpunkte / geltender Steuerfuss`
  (`betragSetzen`). Das Frontend sendet nur `prozentDelta`.
- **Haltung (F97):** `haltung`, getrennt vom Sitzungs-Beschluss. Eigene: `einreichen`
  (Standard) | `nicht_einreichen`; fremde: `unterstuetzen` | `nicht_unterstuetzen` |
  `offen` (Standard). `BudgetAntrag::haltungOderStandard()` liefert den Herkunfts-Standard,
  `wirdUnterstuetzt()` = «einreichen» oder «unterstuetzen».
- **Unterstützende Fraktionen (F98):** `unterstuetzer` (JSON `[{key,name}]`). Bei
  Zustimmung ist die eigene Fraktion automatisch dabei (`unterstuetzerMit`).
- **Übersicht zählt nur Unterstütztes (F102):** `BudgetService::ansicht` nimmt in der
  Vorbereitungsphase nur Anträge mit `wirdUnterstuetzt()` in die Summen; in der
  Sitzungsphase nur die angenommenen. Auch die Automatik (`pauschalNeu`) rechnet nur mit
  unterstützten manuellen Anträgen.
- **Pauschalantrag (F100/F101):** `pw_budget_verteilung.haltung` (Einreichen-Entscheid,
  vererbt sich auf die je Position erzeugten Kinder) und `ausnahmen` (JSON-Liste
  `zielRef`). `pauschalNeu` verteilt den Delta nur über die nicht ausgenommenen Gruppen —
  die Umverteilung auf die übrigen erledigt `verteileAnteiligAufwand` von selbst (Summe
  bleibt exakt). Frontend: Schalter «Pauschalantrag einreichen» und Checkbox «Ausnahme vom
  Pauschalantrag» je Produktegruppe.
- **Notizen (F103):** über den geteilten `NotizService` mit Objekttyp `budget-antrag`
  (Versionen/Soft-Delete/Undo wie bei Geschäft/Vorstoss). `ansicht` hängt die Notizen je
  Antrag als `aktionen` an (`listeGruppiert`); das Frontend bindet sie an `NotizenListe`.
  Endpunkte `budget#notizen/addNotiz/updateNotiz/deleteNotiz/restoreNotiz/notizRevisionen`.
- **Verknüpfung Vorbereitung↔Sitzung (F104):** `verknuepft_mit_id`.
  `verknuepfungenAktualisieren` (in `nachAenderung`) verknüpft automatisch, wenn auf beiden
  Seiten genau ein freier Kandidat mit gleichem `bereich/zielRef/betragDelta` besteht, und
  überträgt die Haltung in die Sitzung; Mehrdeutiges bleibt frei. `verknuepfungSetzen`
  (Endpunkt `budget#verknuepfen`) setzt/löst von Hand.

## Weitere Regeln

- Entwicklungs-Setup, Testkommandos: siehe
  [README, Abschnitt Entwicklung](README.md#entwicklung).
- Feature-Übersicht: [FEATURES.md](FEATURES.md) — bei jeder Änderung betroffene
  Einträge aktualisieren. **Features stehen dort ausschliesslich aus
  Nutzersicht** (was der Nutzer erlebt und kann); Technik (Architektur,
  Container, Tabellen, ENV, API, Komponentennamen) gehört hierher bzw. ins
  README.
