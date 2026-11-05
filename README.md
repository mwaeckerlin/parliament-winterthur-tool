# Parlament Winterthur Tool

Nextcloud-Plugin für die Fraktionsarbeit im Winterthurer Parlament. Das Plugin synchronisiert nach Zeitplan, standardmässig um 10:00 und 18:00, die öffentlichen Daten des [Parlaments Winterthur](https://parlament.winterthur.ch/) und die Beschlüsse des Stadtrats und stellt sie als strukturierte Arbeitsoberfläche mit fraktionsinternen Notizen, Zuständigkeiten und Beschlüssen zur Verfügung.


## Zweck

Nextcloud ist eine Sammlung von Werkzeugen für Dateien, Dokumente, Termine und Chats, die auf einem Server der Fraktion läuft. Die Dokumente, Kalender und Chats sieht nur, wer auf diesem Server ein Konto hat. Nextcloud öffnet sich im Webbrowser (Chrome, Firefox, Safari), dazu gibt es Apps für Telefon und Computer.

Die Tabelle nennt zu jeder Aufgabe die App in Nextcloud und die Produkte von Google und Microsoft für dieselbe Aufgabe.

| Aufgabe                        | Google / Microsoft        | Nextcloud                          |
|--------------------------------|---------------------------|------------------------------------|
| Dateien ablegen und teilen     | Google Drive / OneDrive   | **Dateien**                        |
| Dokumente gemeinsam schreiben  | Google Docs / Word Online | **Nextcloud Office (Collabora)**   |
| Termine                        | Google Calendar / Outlook | **Kalender**                       |
| Adressbuch                     | Google Contacts           | **Kontakte**                       |
| Aufgabenlisten                 | Google Tasks / To Do      | **Aufgaben / Deck**                |
| Kanban-Board (Projektplanung)  | Trello / Planner          | **Deck**                           |
| Chat                           | Google Chat / Teams       | **Talk**                           |
| Video-Konferenz                | Meet / Teams              | **Talk**                           |
| Umfragen und Terminfindung     | Forms                     | **Forms / Polls**                  |

Dazu kommt dieses Plugin, das Parlament-Winterthur-Tool. Es holt die laufenden Geschäfte, Sitzungen, Traktanden, Kommissionen und Fraktionsmitglieder von der Webseite des Parlaments in die Nextcloud der Fraktion, und niemand muss sie dort selbst suchen.


## Funktionen

Das Plugin lädt nach Zeitplan, standardmässig zweimal am Tag, alle öffentlichen Daten des Winterthurer Parlaments herunter und zeigt sie an. Dazu erfasst die Fraktion eigene Notizen, Zuständigkeiten und Argumente, die nur innerhalb der Fraktion sichtbar sind.

[FEATURES.md](FEATURES.md) führt jede Funktion mit ihrer Nummer, und [TESTS.md](TESTS.md) nennt zu jeder Nummer ihre Tests.

[PARLAMENTSBETRIEB.md](PARLAMENTSBETRIEB.md) enthält die Regeln des Parlamentsbetriebs zu Vorstössen, Kommissionen, Informationsbeschaffung, Budget, Fragestunde und Sitzung.

### Überblick

- **Geschäfte.** Alle politischen Geschäfte (Anträge, Motionen, Interpellationen …) als Tabelle mit Suche und aktuellem Stand. Filter gibt es nach Status, Typ, Datum, Zuständigkeit, Priorität und letztem gültigem Beschluss; Status, Typ, Zuständige, Beschluss und Priorität sind Mehrfachauswahlen. Erledigte und abgeschlossene Geschäfte sind ausgeblendet, bis der Schalter «Erledigte anzeigen» sie einblendet.
- **Sitzungen.** Kommende Parlaments- und Kommissionssitzungen samt Traktandenliste. Zu jedem Traktandum erfasst die Fraktion Bemerkungen und Notizen. Jede Sitzung steht als Eintrag im gemeinsamen Kalender der Fraktion (`Fraktion <Name>`), der auch weitere Sitzungstypen aufnimmt.
- **Mitglieder.** Alle Parlamentarier mit Partei, Fraktion und Kommissionszugehörigkeit; sortierbar nach Funktion (Standard), Fraktion, Partei oder Name und filterbar nach Funktion (Fraktionspräsident oder Kommissionspräsident) und nach einzelner Kommission. Die Fraktionsmitglieder bilden eine Nextcloud-Gruppe und erhalten eine Einladung per E-Mail.
- **Kommissionen.** Welche Kommission welches Geschäft behandelt und wer in ihr sitzt.
- **Fraktionsarbeit.**
  - Jedem Geschäft weist die Fraktion Zuständige aus den eigenen Reihen zu, einen Hauptzuständigen und weitere Mitarbeiter. Die Auswahllisten führen die aktiven Mitglieder zuerst und die inaktiven getrennt darunter.
  - Bei Kommissionsgeschäften weist die Synchronisation die Fraktionsmitglieder der zuständigen Kommission automatisch zu.
  - Zu jedem Geschäft legt die Fraktion Notizen, Argumente und Hintergrunddokumente ab. Notizen zu Geschäften und Sitzungen lassen sich formatieren (Fett, Kursiv, Listen, Überschriften, Zitate, Code, Links); der Editor zeigt das Ergebnis beim Schreiben, gespeichert wird Markdown.
  - Die Beschlüsse der Fraktion stehen als Einträge in der Aktionszeitleiste des Geschäfts. Ein Filter zeigt die Geschäfte, die noch keinen gültigen Beschluss tragen oder deren Quelle sich nach dem letzten gültigen Beschluss geändert hat.
  - Im Fraktionssitzungsmodus erfasst nur der Protokollführer oder seine aktive Stellvertretung Beschlüsse; Notizen schreiben alle. Die Rollen sind Fraktionspräsident und Protokollführer, je mit zeitlich befristeter Stellvertretung, dazu Kommissionsmitglied.
- **Gemeinsames Aufgaben-Board (Deck).** Die App richtet ein mit der Fraktion geteiltes Kanban-Board «Fraktion» ein, wie den gemeinsamen Ordner und Kalender. Ohne die App Deck bleibt die Funktion aus.
- **Sitzungen verknüpfen und Dokumente ablegen.** Sitzungen lassen sich miteinander verknüpfen, etwa eine Kommissionssitzung mit der vorbereitenden Fraktionssitzung. Zu jeder Sitzung lassen sich Dokumente ablegen und einzelne Geschäfte verknüpfen.
- **Automatische Geschäfts-Verknüpfung.** Sitzungstypen können «beratene» Kommissionen festlegen; die in diesen Kommissionen hängigen Geschäfte verknüpft die App vor jeder Sitzung dieses Typs automatisch.
- **Aufgaben aus der Sitzung ins Deck-Board.** Aus einer Sitzung lassen sich Aufgaben direkt als Karte im Fraktions-Board «Fraktion» anlegen.
- **Stadtratsbeschlüsse.** Die Beschlüsse des Stadtrats in einer eigenen Ansicht, der jüngste zuerst. Jede Karte zeigt Nummer, Titel, die Felder «Beschlossen», «Departement», «Sitzung» und «Veröffentlicht», die amtlichen Dokumente als Verweise und hinter einem Schalter die Notizen der Fraktion, mit derselben Notizliste wie am Geschäft. Ein Pfeil führt auf den Beschluss bei der Stadt. Die Suche greift auf Nummer, Titel, Departement und Sitzung, dazu kommen die Auswahllisten «Departement» und «Jahr»; beide führen nur Werte, die in den Beschlüssen vorkommen. Die Synchronisation holt die Beschlüsse von der Webseite der Stadt, standardmässig die 120 jüngsten.
- **Vorstösse.** Eigene und fremde politische Vorstösse (Motion, Postulat, Interpellation …) erfassen und verwalten: Herkunft (eigene oder fremde), Status, Priorität, Zuständigkeit (Mehrfachauswahl), Notizen und Inhalt im Formatierungs-Editor; bei fremden Vorstössen dazu die eigene Haltung, die Herkunftsfraktion und deren Ansprechpartner. Beim Bearbeiten speichert jede Eingabe sofort. Dokumente lassen sich anlegen, hochladen oder aus «Fraktion/40_Vorstösse» wählen; von dort übernimmt die App sie auch automatisch. Die Liste filtert nach Herkunft und Status. Ein Vorstoss lässt sich durch Verknüpfung mit einem Geschäft abschliessen (Ähnlichkeitssuche über den Titel; die Priorität geht ans Geschäft). Gelöscht wird über die Karte, mit Rückfrage.
- **Budget.** Ein eigener Bereich für den städtischen Budgetprozess mit den Tabs Globalbudgets, Personalbestand, Investitionsrechnung, Steuerfuss, Anträge und Grafik, mit Filtern (Jahr, Kommission oder Departement, Kostensteigerung) und einer ständig mitlaufenden Summenzeile. Eigene und fremde Anträge je Produktegruppe; globale Kürzungen werden anteilig zum Aufwand verteilt (Defizit standardmässig zur schwarzen Null); Personal-, Investitions- und Steuerfuss-Anträge; dazu Anträge auf einen einzelnen Verpflichtungskredit, auf sein Bewilligungsorgan und auf ein Departementssekretariat. Unter jedem Betrag steht, wie viel davon beeinflussbar ist; jede Produktegruppe nennt ihre Seite im Budgetbuch. Viele Anträge lassen sich in einer Tabelle erfassen oder aus einer `.csv`- oder `.xlsx`-Datei übernehmen. Jeder Antrag trägt Dokumente, und Notizen gibt es auf jeder Ebene: zum ganzen Budgetjahr, zu jedem Departement und zu jedem Tab. Die Anträge gehen als PDF je Kommission und als Excel-Tabelle hinaus, die Tabelle wahlweise in den Fraktionsordner oder zum Herunterladen. Während der Sitzung werden die Beschlüsse mitgeführt. Die Budgetdaten stammen aus den städtischen Budgetbüchern.
- **Grafische Übersicht.** Der letzte Budget-Tab zeigt das ganze Budget als geschachtelte Kreise, deren Fläche dem Betrag entspricht: Einnahmen und Ausgaben, darin die Departemente, darin die Produktegruppen, darin die Produkte. Ein Klick öffnet einen Kreis, der Weg im Kopf und Escape führen zurück. Beim Öffnen kommen die Kreise von weit her zusammen, schieben einander beiseite und ordnen sich; wer mit dem Zeiger darüberfährt, sieht Name, Betrag und Anteil jedes einzelnen, auch der kleinsten.
- **Fragestunde.** Ein eigener Bereich für die Fragen der Fraktion. Gesammelt wird jederzeit, auch ohne angesetzte Fragestunde: Wem etwas auffällt, trägt es ein, und die Fraktion nutzt es später in irgendeiner Fragestunde. Sie bespricht die Fragen in ihrer Sitzung, passt sie an, teilt sie einer Fragestunde zu und bestimmt, wer jede einreicht: meist das Mitglied, das sie eingebracht hat, sonst ein anderes, weil jedes Mitglied nur eine Frage einreicht. Die Seite führt die Frist (der Donnerstag vor der Fragestunde) und die Grenze von 1'000 Zeichen mit und meldet, wenn einem Mitglied mehr als eine Frage zugeteilt ist.
- **Priorität pro Geschäft und Vorstoss.** Hoch, mittel oder tief; ohne Wahl zeigt die App «—», und der Eintrag zählt wie mittel. Die Priorität lässt sich in der Übersicht wie in der Detailansicht einstellen. In den Übersichten sind hohe Einträge dezent hervorgehoben und tiefe abgeschwächt, mit eigenem Filter.
- **Anlegen und Bearbeiten überall gleich.** Der Neu-Knopf öffnet dieselbe vollständige Maske wie das Bearbeiten, mit allen Feldern. Beim Anlegen wird nichts vorab gespeichert: Unten stehen «Speichern» und «Abbrechen»; «Speichern» ist gesperrt, solange die Pflichtangabe fehlt, und führt danach direkt in die Bearbeitung. Ein Klick neben die Maske bewirkt dabei nichts, damit keine Eingabe verloren geht, und verworfen wird ausschliesslich über «Abbrechen». Beim Bearbeiten speichert jede Eingabe sofort (keine Knöpfe, «✕» schliesst nur). Notizen, Dokumente und der Verlauf gehören zu einem bestehenden Eintrag und erscheinen deshalb erst nach dem Speichern; ein Hinweis in der Maske sagt das. Stehen Vorlagen zur Auswahl (Sitzungstypen), öffnet der Neu-Knopf ein Aktionsmenü mit je einem Eintrag pro Vorlage, nach dem Nextcloud-Standard aus der App «Dateien».
- **Keine Änderung ohne Spur.** Die App hält jede Änderung fest: Notizen in ihrem Versionsverlauf, alles Übrige als Eintrag in der Aktionszeitleiste, auch geänderte Angaben und Prioritäten mit dem Wert davor und danach.
- **Änderungsverlauf.** Ein eigener Bereich zeigt den Versionsverlauf der App als aufklappbare, formatierte Liste.
- **Aktualisierung in Echtzeit.** Ändert jemand in der Fraktion etwas, sehen alle anderen es sofort, ohne die Seite neu zu laden.
- **Suche und Filter.** Für alle Geschäfte, Sitzungen, Kommissionen, Stadtratsbeschlüsse, Mitglieder und Vorstösse.
- **Fehlermeldungen.** Schlägt eine Aktion fehl, nennt die Meldung oben am Schirm den Grund im Klartext; ist der Eintrag inzwischen verschwunden, sagt sie, dass die Ansicht neu zu laden ist. Bei einem unerwarteten Fehler steht dort eine achtstellige Kennung, unter der die Einzelheiten im Protokoll der Nextcloud-Installation zu finden sind.

### Geplante Funktionen

- **Abstimmung zu Vorlagen und Vorstössen.** Jedes Fraktionsmitglied kann zu jeder Vorlage (Geschäft) und jedem Vorstoss angeben, ob es **dafür, dagegen oder neutral** ist, und die Haltung begründen. Wo die Fraktion einig ist, entfällt die Besprechung in der Fraktionssitzung, und kurzfristige Entscheide fallen ohne Sitzung.

### Im Alltag

1. **Vor der Fraktionssitzung:** Der Hauptzuständige liest seine Geschäfte und schreibt eine Empfehlung in die Notiz.
2. **In der Fraktionssitzung:** Die Fraktion bespricht die Empfehlungen am Bildschirm und hält die Beschlüsse im Geschäft fest.
3. **Vor der Parlamentssitzung:** Jedes Mitglied ruft seine Geschäfte auf und hat die Argumente zur Hand.
4. **Nach der Sitzung:** Der neue Stand erscheint mit der nächsten Synchronisation, nach Zeitplan oder von Hand mit «Jetzt synchronisieren» in der Verwaltung.


## Bedienung

### Nextcloud-Funktionen für die Fraktionsarbeit

Neben diesem Plugin bringt Nextcloud weitere Werkzeuge mit. Die Tabelle nennt die Apps, die für die Fraktionsarbeit nützlich sind. Jede erscheint als Symbol oben in der Menüleiste, sobald sie aktiviert ist.

#### Empfohlene Apps

| App                              | Name auf Englisch           | Zweck                                                                                                |
|----------------------------------|-----------------------------|------------------------------------------------------------------------------------------------------|
| **Dateien**                      | Files                       | Zentrale Ablage der Dateien auf dem Server.                                                         |
| **Nextcloud Office** (Collabora) | Nextcloud Office (Collabora Online) | Word-, Excel- und PowerPoint-Dokumente im Browser gemeinsam bearbeiten.                    |
| **Kalender**                     | Calendar                    | Persönliche und gemeinsame Termine. Sitzungen aus dem Plugin erscheinen automatisch.                |
| **Kontakte**                     | Contacts                    | Gemeinsames Adressbuch der Fraktion.                                                                |
| **Talk**                         | Talk                        | Chat und Videokonferenz innerhalb der Fraktion.                                                     |
| **Deck**                         | Deck                        | Kanban-Board mit den Spalten «To-do», «In Arbeit» und «Erledigt», etwa eines je Geschäft oder Kampagne. |
| **Aufgaben**                     | Tasks                       | Einfache Aufgabenlisten, abgeglichen mit dem Telefon.                                               |
| **Notizen**                      | Notes                       | Notizen in Markdown.                                                                                |
| **Forms**                        | Forms                       | Umfragen innerhalb der Fraktion, zum Beispiel «Wer kommt am 15. Mai?».                              |
| **Polls**                        | Polls                       | Terminfindung.                                                                                      |
| **Mail**                         | Mail                        | E-Mail-Konto in Nextcloud einbinden (optional).                                                     |
| **Lesezeichen**                  | Bookmarks                   | Gemeinsame Linksammlung, zum Beispiel wichtige Artikel und Gesetzestexte.                           |

#### Apps je Aufgabe

- **Termine koordinieren:** Kalender und Polls
- **Dokumente gemeinsam erarbeiten:** Dateien und Nextcloud Office
- **Schnelle Abstimmung im Vorfeld:** Talk (Chat) oder Forms
- **Wahlkampf und Kampagnen planen:** Deck und Dateien
- **Fraktionsbeschlüsse festhalten:** Notizen am Geschäft, direkt im Plugin


### Organisation der Fraktionsarbeit

Die folgende Ablage hält Privates, Geteiltes und Öffentliches getrennt, und jedes Dokument hat einen festen Ort.

#### Bereiche der Ablage

1. **Persönlicher Bereich.** Nur der Eigentümer sieht ihn.
2. **Fraktions-Bereich.** Alle Fraktionsmitglieder sehen ihn.
3. **Öffentlicher Bereich.** Ein Link lässt sich nach aussen weitergeben, an Medien oder andere Parteien, und ist darum nur bewusst zu nutzen.

Das Symbol neben dem Namen einer Datei oder eines Ordners zeigt den Bereich: das Symbol mit Personen eine Freigabe, das Symbol mit der Weltkugel einen öffentlichen Link.

#### Dokumente in der Fraktion teilen

Die App legt diese Ordnerstruktur im Administratorkonto an und gibt sie der Fraktionsgruppe frei:

```text
Fraktion/
├── 00_Allgemein/           ← Statuten, Geschäftsordnung, Mitgliederliste
├── 10_Sitzungen/
│   └── 2026/               ← Dokumente der Sitzungen, mit dem Datum der Sitzung vor dem Namen
├── 20_Geschäfte/           ← Hintergrund pro Geschäft (alternativ direkt im Plugin)
├── 30_Kommissionen/
│   ├── Aufsichtskommission/
│   ├── Sachkommission Bildung Sport Kultur/
│   └── …
├── 40_Vorstösse/
│   ├── 10_Eigene/          ← Vorstösse werden von hier automatisch übernommen
│   └── 20_Fremde/
├── 50_Wahlkampf/
├── 60_Medien/              ← Pressemitteilungen, Communiqués
└── 90_Archiv/
```

Bestehende Ablagen werden beim Start automatisch auf diese Struktur gebracht: `40_Wahlkampf` wird zu `50_Wahlkampf`, `50_Medien` zu `60_Medien` (Inhalte bleiben erhalten). Erst danach entsteht `40_Vorstösse`, damit die Nummern nicht kollidieren. Die Nummern laufen in Zehnerschritten.

##### Berechtigungen

Die Freigabe gibt der ganzen Fraktionsgruppe Lesen, Anlegen, Ändern und Löschen im ganzen Ordner. Die Tabelle ist die Abmachung, wer wo schreibt; Nextcloud setzt sie nicht durch.

| Ordner                | Lesen          | Schreiben                              |
|-----------------------|----------------|----------------------------------------|
| `00_Allgemein/`       | Alle           | Fraktionspräsidium                     |
| `10_Sitzungen/`       | Alle           | Alle (Protokoll: Protokollführer)      |
| `20_Geschäfte/`       | Alle           | Hauptzuständige und Mitarbeiter        |
| `30_Kommissionen/X/`  | Alle           | Mitglieder der Kommission X            |
| `40_Vorstösse/`       | Alle           | Zuständige des Vorstosses              |
| `50_Wahlkampf/`       | Alle           | Wahlkampfleitung                       |
| `60_Medien/`          | Alle           | Mediensprecher                         |
| `90_Archiv/`          | Alle (nur lesen) | Fraktionspräsidium                  |

Ein Dokument liegt in Nextcloud, und geteilt wird sein Link. Ein Anhang einer E-Mail ist eine zweite Fassung, die neben der aktuellen veraltet.

#### Gemeinsamen Kalender teilen

Für die Fraktion eignen sich drei Kalender:

- **Fraktion `<Name>`** (vom Plugin verwaltet, mit der Fraktion geteilt). Der zentrale Kalender der Fraktion enthält automatisch alle Sitzungen, die das Plugin synchronisiert (Parlament, Kommissionen, weitere Sitzungstypen). Einträge des Plugins überschreibt die nächste Synchronisation, darum werden sie nicht von Hand bearbeitet. Eigene Termine der Fraktion (Fraktionssitzung, Fraktionsausflug, …) dürfen dazukommen.
- **Kommission X** (geteilt mit den Mitgliedern der Kommission), wenn die Kommissionsarbeit eng koordiniert wird.
- **Persönlich** (privat), für alles, was nur den Eigentümer betrifft.

Den Fraktionskalender fügt jedes Mitglied so hinzu:

1. Symbol «Kalender» oben anklicken.
2. Links unten «+ Neuer Kalender» oder «Kalender abonnieren» wählen; den Link gibt das Präsidium weiter.
3. Auf dem Telefon in der App von Nextcloud den Kalender auswählen; er erscheint danach in der Kalender-App des Telefons.

#### Geteilt und persönlich

##### Fraktions-Bereich

- Protokolle aller Fraktionssitzungen
- Fraktionsbeschlüsse, Positionen, Argumentarien
- Vorlagen (Briefkopf, Pressemitteilung, Vorstosstexte)
- Adressbuch mit Parlamentariern und wichtigen Kontakten
- Terminkalender (Sitzungen, Kampagnen)
- Hintergrundunterlagen zu Geschäften
- Medienspiegel

##### Persönlicher Bereich

- Eigene Recherchenotizen, Entwürfe von Reden
- Persönliche Termine
- Private Korrespondenz mit Wählern
- Eigene Lesezeichen / Quellen

Braucht ein zweites Mitglied das Dokument oder soll es dieses sehen, gehört es in den Fraktionsordner. Betrifft es nur den Eigentümer oder ist es unfertiges Material, bleibt es im persönlichen Bereich. Im Zweifel liegt es zuerst im persönlichen Bereich und kommt später in den geteilten Ordner.

#### Regeln der Ablage

1. **Eine Datei, ein Ort.** Keine Kopien per Mail, sondern der Link aus Nextcloud.
2. **Dateinamen mit Datum vorne,** die den Inhalt nennen: `2026-05-20_Antrag_Velobruecke.odt`.
3. **Nichts löschen.** Veraltetes kommt nach `90_Archiv/`. Nextcloud führt dazu eine Versionsgeschichte.
4. **Persönliche Daten nicht über öffentliche Links teilen,** sondern über eine Freigabe, die eine Anmeldung verlangt.
5. **Vor dem Schreiben nachsehen, wer das Dokument gerade geöffnet hat.** Nextcloud Office zeigt das oben rechts an; mehrere Benutzer können gleichzeitig schreiben.
6. **Talk statt WhatsApp** für Themen der Fraktion, denn Talk läuft auf dem Server der Fraktion.

### Fragestunde

Das Parlament hält zweimal im Jahr eine Fragestunde. Der Bereich **«Fragestunde»** (in der Navigation direkt hinter «Budget») führt den Ablauf der Fraktion:

1. **Fragen eintragen, jederzeit.** Jedes Mitglied trägt seine Fragen selbst ein («+ Neue Frage»), ohne dass eine Fragestunde angesetzt sein muss: wer sie eingebracht hat (vorbelegt), die Frage, ein Kommentar für die Fraktionssitzung und Notizen. Neben dem Feld steht die Zeichenzahl. Eine Frage über **1'000 Zeichen** nimmt der Parlamentsdienst nicht entgegen, und die App speichert sie dann auch nicht.
2. **Fragestunde anlegen, sobald sie feststeht.** «+ Neue Fragestunde» mit dem Datum. Titel und Frist ergeben sich daraus: Die Fragen müssen bis spätestens am **Donnerstag vor der Fragestunde** schriftlich beim Parlamentsdienst eingereicht werden (Art. 103 Abs. 2 der Organisationsverordnung des Stadtparlaments).
3. **In der Fraktionssitzung besprechen.** Jede Frage lässt sich anpassen, und ihr Status geht von «Neu» über «Besprochen» zu «Eingereicht» oder «Zurückgezogen».
4. **Einer Fragestunde und einem Mitglied zuteilen.** Im Feld «Fragestunde» entscheidet die Fraktion, in welcher Fragestunde die Frage gestellt wird, im Feld «Einzureichen von», wer sie einreicht. Meist reicht sie das Mitglied ein, das sie eingebracht hat, und weil **jedes Mitglied nur eine Frage einreicht**, manchmal ein anderes. Ist einem Mitglied mehr als eine Frage zugeteilt, meldet die Seite es mit Namen.

Jede Änderung speichert sofort, und alle in der Fraktion sehen sie in Echtzeit. Sobald das Parlament die Fragestunde als Geschäft veröffentlicht hat, steht der Verweis darauf auf der Karte.

### Budget-Fassungen

Im Budgetprozess entstehen mehrere Fassungen desselben Budgets. Die App verwendet diese Begriffe:

- **Stadtratsbudget.** Das Budget **1:1 aus der Weisung des Stadtrats**, mit dem Novemberbrief, sofern einer vorliegt. Es ist der unveränderte Ausgangsstand.
- **Fraktionsbudget.** Das Budget **mit allen erfassten Anträgen, welche die Fraktion einreicht oder unterstützt**. Es zeigt das Budget nach dem Willen der eigenen Fraktion.
- **Kommissionsbudget.** Das Budget **mit allen Mehrheitsbeschlüssen aus den Kommissionen**. Die App stellt es nicht eigens dar.
- **Parlamentsbudget.** Das vom **Parlament beschlossene, finale und gültige** Budget.

### Pauschalanträge und Steuerfuss

Im Budget-Bereich fasst der Kasten **«Pauschalanträge»** (Tab Globalbudgets) den automatischen Ausgleich und die frei angelegten Pauschalanträge zusammen:

- **Defizit automatisch verteilen:** Ist der Schalter «Defizit automatisch als Pauschalkürzung verteilen» eingeschaltet, wird ein Defizit anteilig zum Aufwand auf die Produktegruppen verteilt, bis das gewählte **Ziel** erreicht ist (Standard: schwarze Null; wahlweise ein Zielbetrag). Der Schalter «Pauschalantrag einreichen» entscheidet, ob die so erzeugten Einzelanträge eingereicht werden; nur dann stehen sie im Antrags-PDF.
- **Weitere Pauschalanträge:** Über «+ Pauschalantrag» lassen sich beliebig viele, voneinander unabhängige Pauschalanträge anlegen (Einsparung in Franken oder Prozent, Antragsteller-Fraktion, Ausnahmen, Begründung). Ihre Kürzungen summieren sich; der automatische Ausgleich rechnet zuletzt, auf dem bereits gekürzten Stand.

Eine Position lässt sich über ihr Budget hinaus kürzen. Ein Antrag, der mehr streicht, als die Position kostet, macht aus ihr einen Ertrag, und mehrere Anträge auf dieselbe Position dürfen sich zu mehr als 100% summieren. Unter jedem Betrag steht, wie viel davon beeinflussbar ist; diese Angabe sperrt keinen Antrag. Nur die automatische Pauschalverteilung hält eine Grenze ein: Sie verteilt nur auf den beeinflussbaren Aufwand, und was eine Position nicht mehr trägt, geht auf die übrigen. Reicht auch das nicht, bleibt der Rest liegen und ist an der Summe sichtbar.

Auf dem Tab Steuerfuss entscheidet sich, was mit einem Überschuss geschieht. Bleibt am Ende ein **Überschuss**, senkt die App den Steuerfuss automatisch in ganzen Prozent-Schritten, bis der Überschuss aufgebraucht ist; der Ertrag geht damit auf nahezu null. Ein Steuerprozent ist so viel wert wie «Steuerertrag geteilt durch Steuerfuss». Die Senkung ist ein **echter Antrag**: Sie reduziert die Einnahmen in der Summenzeile und steht am Ende der Antragsliste und des Antrags-PDF. Wer den Steuerfuss selbst festlegen will, schaltet die Automatik auf dem Steuerfuss-Tab ab und stellt einen manuellen Steuerfuss-Antrag; dieser verdrängt die Automatik.

Gewünschter Ertrag und Steuerfuss-Senkung kommen zusammen vor, und beide Automatiken dürfen gleichzeitig laufen. Der automatische Defizit-Ausgleich verteilt nur **Defizite** als Kürzungen und lässt einen Überschuss unberührt. Einen Überschuss, auch einen als Zielbetrag gewünschten Ertrag, gleicht die **Steuerfuss-Senkung** aus: Wer den Zielbetrag eingibt und den Überschuss verteilt, gibt diesen Betrag in die Steuerfuss-Senkung, und der Gesamtertrag ist wieder null. Damit ist festgelegt, was mit einem Überschuss geschieht, wenn beide Schalter aktiv sind.


## Administration

### Inbetriebnahme auf einer Ubuntu-VM (Beispiel)

Die Anleitung richtet das Tool auf einer Ubuntu-VM für den Betrieb ein, mit HTTPS über Let's Encrypt und Traefik.

#### Einmalige Einrichtung

##### Docker installieren

Auf der bereitgestellten Ubuntu-VM kommen Docker Engine und Compose aus der offiziellen Quelle:

```bash
# Offizielle Docker-Quelle einrichten (Ubuntu 22.04/24.04)
sudo apt-get update
sudo apt-get install -y ca-certificates curl pwgen git
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
```

##### Docker ohne Root-Rechte

Der Benutzer darf Docker danach ohne `sudo` aufrufen; dafür meldet er sich einmal neu an:

```bash
sudo usermod -aG docker $USER
newgrp docker
```

##### Projekt klonen

```bash
git clone git@github.com:mwaeckerlin/parliament-winterthur-tool.git
```

##### Konfigurationsdatei anlegen

Die Datei `.env` trägt Adresse, Mailadresse für Let's Encrypt und die erzeugten Kennwörter:

```bash
cd parliament-winterthur-tool/example
cat > .env << EOF
HOST=your-site.com
LETSENCRYPT_EMAIL=info@your-site.com
NEXTCLOUD_DB_PASSWORD=$(pwgen -s 40 1)
NEXTCLOUD_ADMIN_PASSWORD=$(pwgen -s 40 1)
EOF
```

`HOST` ist der öffentliche Domainname, zum Beispiel `fraktion.example.com`. Die Ports 80 und 443 erscheinen auf allen Adressen des Servers, IPv4 und IPv6. Wer sie auf eine Adresse beschränken will, ergänzt die Zeile `BIND_ADDRESS=<Adresse>`: `127.0.0.1` lässt nur den Server selbst zu, eine IPv6-Adresse steht in Klammern wie `[::1]`, und `0.0.0.0` öffnet alle IPv4-Adressen und keine IPv6-Adresse. An `LETSENCRYPT_EMAIL` schickt Let's Encrypt die Hinweise zu den Zertifikaten. Die beiden Kennwörter erzeugt `pwgen` als zufällige Zeichenketten von 40 Zeichen.

##### Speicher und Neustart

Ohne weitere Zeilen in `.env` passt der Stack auf einen Server mit 1,9GiB Speicher. Jede Variable steht mit ihrem Standard und ihrer Wirkung in `example/.env.sample`; wer einen Wert ändert, ergänzt die Zeile in `.env` und startet den Stack wie unten beschrieben neu.

- `RESTART_POLICY` (Standard: `unless-stopped`) Jeder Dienst startet nach einem Absturz und nach einem Neustart des Servers von selbst wieder. Nur ein Dienst, den `docker compose stop` angehalten hat, bleibt aus.
- `MAX_REQUEST_WORKERS` (Standard: `8`) So viele PHP-Prozesse beantworten gleichzeitig Anfragen (`pm.max_children` von php-fpm); jede weitere Anfrage wartet, bis ein Prozess frei ist. Der Wert muss mindestens `3` sein, weil das Image bis zu drei wartende Prozesse hält (`pm.max_spare_servers`); darunter startet php-fpm nicht. Ohne diese Zeile im Stack nähme das Image `100`.
- `PHP_FPM_MEM_LIMIT` (Standard: `1280m`) Die Obergrenze für den Speicher des Containers `nextcloud-php-fpm`, mit allem, was darin läuft: den PHP-Prozessen, dem Hintergrundauftrag und der Synchronisation samt dem Prozess, der ihre Dokumente liest. Sie liegt mindestens 256MiB über `PARLWIN_PHP_MEMORY_LIMIT`; sonst beendet der Kernel ein Einlesen, bevor PHP seine eigene Meldung schreibt.
- `MAX_INPUT_TIME` (Standard: `3600`) So viele Sekunden darf eine Anfrage an php-fpm eintreffen und in PHP rechnen: Das Image `mwaeckerlin/nextcloud:php-fpm` startet php-fpm mit `max_input_time` und `max_execution_time` auf diesem Wert. Ebenso lange wartet nginx auf die Antwort von PHP (`fastcgi_read_timeout`). `MAX_INPUT_TIME` ist die einzige Variable für `max_execution_time`: Die Variable `MAX_EXECUTION_TIME` des Basis-Images `mwaeckerlin/php-fpm` (Standard `30`) wirkt hier nicht, weil die Angabe beim Start von php-fpm jede ini-Datei übersteuert. Auf der Kommandozeile hat PHP keine Zeitgrenze (`max_execution_time` 0); das gilt für `occ`, den Hintergrundauftrag und die Synchronisation.

Füllt PHP den Speicher des ganzen Servers, beendet der Kernel den grössten einzelnen Prozess. Solange sich der Speicher von PHP auf viele Prozesse verteilt, ist das die Datenbank, und die ganze Instanz steht still. Mit `PHP_FPM_MEM_LIMIT` beendet der Kernel einen Prozess im Container `nextcloud-php-fpm`: Die Anfrage, die er gerade beantwortete, endet mit einem Fehler, die Datenbank und die übrigen Dienste laufen weiter, und endet der ganze Container, startet ihn `RESTART_POLICY` neu. Ein zu hoher Wert hebt diesen Schutz auf, weil PHP dann wieder den Speicher belegen kann, den die Datenbank braucht. Ein zu tiefer Wert trifft zuerst das Einlesen eines Budgetbuchs, das in einem einzigen Prozess 574,7MB braucht, und den Prozess, der in der Synchronisation ein Dokument liest: Das Dokument trägt dann den Abbruch als Grund, und der Lauf liest weiter. Mehr Prozesse beantworten mehr Anfragen gleichzeitig, und jeder belegt Speicher: Wer `MAX_REQUEST_WORKERS` erhöht, erhöht `PHP_FPM_MEM_LIMIT` mit, um etwa 100MiB je Prozess, und lässt daneben etwa 670MiB für die Datenbank, Collabora und die übrigen Dienste. Die 100MiB sind geschätzt; `docker stats` zeigt, was die Prozesse im Betrieb belegen.

`MAX_INPUT_TIME` bleibt bei einer Stunde, weil das Einlesen eines Budgetbuchs in der HTTP-Anfrage selbst läuft und Minuten dauert. Eine kürzere Grenze bricht es ab. «Budget neu einlesen» löscht vorher alle Anträge, Notizen, Pauschalanträge und Entscheide des Jahres; bricht das Lesen danach ab, sind sie weg, und das neue Buch ist nicht eingelesen. Eine Grenze nach verstrichener Zeit, die eine hängende Anfrage unabhängig von ihrer Rechenzeit beendet (`request_terminate_timeout` von php-fpm), bietet das Image nicht an.

#### Start und Aktualisierung

```bash
cd parliament-winterthur-tool/example
git pull
docker compose up -d --build --remove-orphans --force-recreate --pull always
```

Dieselben Befehle gelten für den ersten Start und für jede Aktualisierung: `git pull` holt die aktuelle Version, `--pull always` lädt die neuen Docker-Images, und `--build` baut den vorgelagerten Proxy neu.

Der vorgelagerte Proxy wird gebaut, weil `COPY` seine Weiterleitungen (`example/traefik/dynamic.yml`) in sein Image legt. So bekommt kein Container ein Verzeichnis des Rechners zu sehen, und der Docker-Socket bleibt ebenfalls draussen. Deshalb ist der Docker-Provider von Traefik abgeschaltet, und die Weiterleitungen kommen aus dieser Datei. Wer sie ändert, baut den Proxy neu:

```bash
docker compose up -d --build traefik
```

#### Konfiguration in Nextcloud

1. Nextcloud unter `https://your-site.com` aufrufen und mit dem Administratorkonto anmelden; das Kennwort steht in `NEXTCLOUD_ADMIN_PASSWORD`.
2. Unter **Administration**, Abschnitt **Parlament Winterthur**, die Fraktion, die Nextcloud-Gruppe und die E-Mail-Einstellungen festlegen.
3. **Jetzt synchronisieren** klicken; die erste Synchronisation lädt die Daten des Parlaments.

#### Verwaiste Benutzer

Die Verwaltung zeigt unter **«Fraktionsmitglieder ↔ Nextcloud-Benutzer»** alle Benutzer der konfigurierten Nextcloud-Gruppe. Wer in der Gruppe ist, aber keinen aktuellen Parlamentseintrag hat, erscheint **durchgestrichen** (Name und E-Mail); Benutzername und Gruppen stehen normal da. «Ausgewählte abgleichen» entfernt nur die angewählten verwaisten Benutzer aus der Gruppe und deaktiviert sie, die anderen bleiben unberührt.

Die automatische Synchronisation entfernt niemanden aus der Gruppe. Benutzer entfernt oder deaktiviert nur «Ausgewählte abgleichen», mit ausdrücklicher Auswahl durch die Verwaltung. Verwaist erscheint nur, wer von Hand in die Nextcloud-Gruppe aufgenommen wurde, etwa ein ehemaliger Mitarbeiter der Fraktion: Er erscheint als verwaister Eintrag, solange er kein aktives Parlamentsmandat hat.


### Images je Nextcloud-Linie

Die drei Images gibt es je Nextcloud-Hauptversion, weil eine Aktualisierung von Nextcloud keine Hauptversion auslassen darf: Eine Installation auf Nextcloud 33 geht auf 34 und erst danach auf 35. Welchen Tag eine Instanz braucht, sagt ihr eigener Stand.

| Tag | Inhalt |
| --- | --- |
| `mwaeckerlin/parliament-winterthur-tool:php-fpm` | parlwin auf der neuesten Nextcloud-Linie, rollend |
| `…:php-fpm-v1.10.0` | parlwin 1.10.0 auf der neuesten Linie, festgelegt |
| `…:php-fpm-nc34` | parlwin auf Nextcloud 34, rollend |
| `…:php-fpm-nc34-v1.8.18` | parlwin 1.8.18 auf Nextcloud 34, festgelegt |

Dasselbe gilt für `nginx` und `realtime`. Eine Version trägt in jedem Tag ihr `v`, gleich wie der Git-Tag derselben Auslieferung. Dazu gibt es jeden dieser Tags auch mit dem Datum des Baus am Ende (`…:php-fpm-v1.10.0-20261026`), wenn ein Bau desselben Stands wiederholbar sein muss. Festgelegt ist der Code von parlwin, die Basis nicht: Ein Git-Tag `v<X.Y.Z>-<n>` baut ein früheres Release auf seiner Nextcloud-Linie neu, etwa mit einem gepatchten PHP, und veröffentlicht es unter denselben Tags, zum Beispiel `…:php-fpm-v1.8.18` auf Nextcloud 34. In `example/docker-compose.yml` trägt jeder Dienst den Tag der Version, `…-v1.10.0`, auf der Linie, auf der die Installation steht; erst nach der Aktualisierung auf die nächste Hauptversion kommt der Tag der nächsten Linie. Läuft die Instanz auf einer älteren Linie als das Image, verweigert Nextcloud den Start («Downgrading Nextcloud … is not supported»), und der vorgelagerte Server antwortet mit 502.

Die Zweige der Linien erzeugt `./create-branches.sh` (auch `npm run branches`): Es baut je Version den Zweig `nc<version>` aus `master`, setzt darin die `FROM`-Zeilen auf `mwaeckerlin/nextcloud:php-fpm-nc<version>` und `:nginx-nc<version>` und schiebt ihn nach GitHub. Nach jedem Push auf `master` führt der Bau auf GitHub (`.github/workflows/docker.yml`) das Skript selbst aus, sobald die Images von `master` veröffentlicht sind, mit dem Commit dieses Pushes, und baut danach jeden Zweig für amd64 und arm64. Stündlich prüft der Bau, ob sich ein Basis-Image oder eine Datei geändert hat, die der Bau herunterlädt, oder ob ein veröffentlichtes Image sieben Tage alt ist, und baut dann neu, auf `master` und auf jedem Zweig. Von Hand braucht es das Skript nur für eine Linie ausserhalb von 33 bis 35.

### Voraussetzungen

- Nextcloud ≥ 25
- PHP ≥ 8.0
- Composer
- Node.js ≥ 22.11 und npm (verlangt von `sass-loader` 17)

### Installation im Nextcloud-Apps-Verzeichnis

Gebaut wird die Oberfläche im Wurzelverzeichnis des Repositorys, denn dort liegen `package.json` und der Aufruf von webpack; `parlwin/` hat kein `package.json`. Danach kommen die PHP-Abhängigkeiten in `parlwin/`, und `parlwin/` wird in das Verzeichnis `apps` von Nextcloud kopiert:

```bash
cd parliament-winterthur-tool
npm ci
npm run build:app
composer install --no-dev --working-dir=parlwin
cp -r parlwin /path/to/nextcloud/apps/
```

In der Nextcloud-Administrationsoberfläche unter **Apps** das Plugin **Parlament Winterthur Tool** aktivieren.

### Konfiguration

Nach der Aktivierung unter **Einstellungen**, Abschnitt **Parlament Winterthur**, die Fraktion und die Nextcloud-Gruppe festlegen.

Die Einstellungen des Plugins:

- **Fraktion**: die Fraktion, für die das Tool arbeitet; Pflichtauswahl aus den synchronisierten aktiven Fraktionen
- **Nextcloud-Gruppe**: die Nextcloud-Gruppe der Fraktionsmitglieder, die das Plugin anlegt und nachführt (Einladung per E-Mail)
- **Automatische Synchronisation**: Zeitplan, wann die Daten geholt werden
- **Fraktionssitzung**: Modus aktiv oder inaktiv
- **Fraktionspräsident**: primäre Rolle des Präsidiums
- **Protokollführer**: primäre Rolle für das Protokoll der Beschlüsse
- **Stellvertretungen**: befristete Delegation mit `gueltig_von` und `gueltig_bis`
- **Verbindung in Echtzeit (WebSocket)**: immer aktiv, ohne eigene Adresse in der Konfiguration; angemeldet wird über die laufende Nextcloud-Sitzung
- **Fraktionsmitglieder ↔ Nextcloud-Benutzer**: ordnet den Parlamentsmitgliedern die lokalen Nextcloud-Benutzer zu und zeigt die verwaisten Benutzer (siehe «Verwaiste Benutzer»)

Regeln der Verwaltungsseite:
- **Nextcloud-Gruppe** lässt sich als bestehende Gruppe wählen oder als neuer Gruppenname eintragen; die Seite zeigt an, ob der Name bereits existiert.
- **Fraktionsraum** (gemeinsamer Ordner und Kalender): Die App stellt ihn über das Administratorkonto bereit und teilt ihn mit der Fraktionsgruppe, beim Öffnen der App und bei jeder Änderung der Fraktionsgruppe. Es gibt dafür weder eine Einstellung «Kalender-Benutzer» noch einen Knopf. Hat ein Mitglied bereits einen eigenen Ordner «Fraktion» mit der Gruppe geteilt, übernimmt die App ihn: Der Ordner bleibt beim Eigentümer als «Fraktion.bak» erhalten, und sein Inhalt kommt in den Ordner der App.
- **Fraktionsmitglieder zuordnen:** Nach der Wahl der Fraktion erscheinen alle aktiven Mitglieder:
  - Vorgeschlagener Benutzername: `vorname-nachname` (klein, normalisiert)
  - Der Benutzername lässt sich ändern und wird in `pw_mitglieder.nextcloud_uid` gespeichert
  - Die Prüfung vor Ort zeigt, in welchen Gruppen der Benutzer bereits ist
  - Sammelaktion **Ausgewählte anlegen**: legt fehlende Benutzer an und nimmt sie in die gewählte Fraktionsgruppe auf
- Die Verwaltungsseite passt sich der Fensterbreite an und verwendet den Standardaufbau der Nextcloud-Einstellungen (`section` und die üblichen Formularfelder) ohne eigene Klassen für die Gestaltung der Eingabefelder.

### Lokale Docker-Umgebung (mwaeckerlin/nextcloud und Plugin)

Im Wurzelverzeichnis des Projekts liegt eine eigenständige `docker-compose.yml` ohne externe `extends`-Verweise.

Wie im Projekt `mwaeckerlin/nextcloud` hat jede Verbindung ihr eigenes Netz:
- `nginx-php`: `nextcloud-nginx` ↔ `nextcloud-php-fpm`
- `php-db`: `nextcloud-php-fpm` ↔ `nextcloud-db`
- `php-smtp`: `nextcloud-php-fpm` ↔ `smtp-relay`
- `nginx-collabora`: `nextcloud-nginx` ↔ `collabora`
- `php-collabora`: `nextcloud-php-fpm` ↔ `collabora`
- `php-realtime`: `nextcloud-php-fpm` ↔ `parlwin-realtime`
- `nginx-realtime`: `nextcloud-nginx` ↔ `parlwin-realtime`

Alle Netze sind wie im Projekt `mwaeckerlin/nextcloud` mit `driver_opts.encrypted=1` definiert. Die Subnetze vergibt Docker Compose; das Projekt legt keine festen `ipam`-Subnetze fest.

Pflichtvariablen, zum Beispiel in `.env` im Wurzelverzeichnis des Projekts:
- `NEXTCLOUD_DB_PASSWORD`
- `NEXTCLOUD_ADMIN_PASSWORD`

Variablen für die Verbindung in Echtzeit; `docker-compose.yml` belegt sie vor:
- `PARLWIN_REALTIME_WS_URL` (Dienst `nextcloud-php-fpm`, Standard: leer, dann aus der Herkunft der Seite: `ws(s)://<host>[<WEBROOT>]/ws/parlwin/`) Nur zu setzen, wenn Nextcloud unter einer anderen öffentlichen Adresse erreichbar ist.
- `PARLWIN_REALTIME_PUBLISH_URL` (Dienst `nextcloud-php-fpm`, Standard: `http://parlwin-realtime:3001/publish`)
- `PARLWIN_REALTIME_SECRET` (beide Dienste, optional) Gemeinsames Geheimnis für `/publish`.
- `PARLWIN_REALTIME_AUTH_REQUIRED` (Dienst `parlwin-realtime`, Standard: `1`) Die Anmeldung an der WebSocket-Verbindung ist verlangt.
- `PARLWIN_NEXTCLOUD_BASE_URL` (Dienst `parlwin-realtime`, Standard: `http://nextcloud-nginx:8080`) Die Adresse für die Prüfung dieser Anmeldung.
- `PARLWIN_NEXTCLOUD_AUTH_URL` (Dienst `parlwin-realtime`, optional) Ersetzt die Adresse für die Anmeldung vollständig.

Die folgenden Variablen liest der Code im Container `nextcloud-php-fpm`. Ausser `PARLWIN_PHP_MEMORY_LIMIT` reicht keine Compose-Datei des Projekts sie durch: Wer eine setzt, trägt sie unter `environment` des Dienstes `nextcloud-php-fpm` ein.

Variablen für die Geschwindigkeit der Synchronisation und den Start:
- `PARLWIN_SYNC_SECTION_PARALLEL` (Standard: `6`) Wie viele Hauptlisten gleichzeitig vorgeladen werden (`geschäfte`, `sitzungen`, `mitglieder`, `kommissionen`, `fraktionen`).
- `PARLWIN_SYNC_GESCHAEFTE_PARALLEL` (Standard: `10`) Anzahl gleichzeitiger Abfragen auf die Detailseiten der Geschäfte (`/_rte/information/{id}`).
- `PARLWIN_SYNC_HTTP_CONNECT_TIMEOUT` (Standard: `8`, von 1 bis 60), `PARLWIN_SYNC_HTTP_TIMEOUT` (Standard: `25`, von 5 bis 180), `PARLWIN_SYNC_HTTP_LOW_SPEED_TIME` (Standard: `12`, von 3 bis 120) und `PARLWIN_SYNC_HTTP_LOW_SPEED_LIMIT` (Standard: `1`, von 1 bis 1024) Jeder Abruf einer Webseite gibt nach so vielen Sekunden auf, wenn die Verbindung nicht steht oder die ganze Antwort nicht da ist. Ruft die Synchronisation mehrere Seiten gleichzeitig ab, gibt ein Abruf zudem auf, wenn er so viele Sekunden lang weniger als so viele Byte je Sekunde empfängt, höchstens so lange wie die Grenze für den ganzen Abruf. Ein Wert ausserhalb der Spanne gilt als die nächste Grenze der Spanne, ein Wert, der nicht nur aus Ziffern besteht, als der Standard.
- `PARLWIN_SETUP_REPAIR` (Standard: `1`) Beim Start legt der Container die fehlenden Datenbank-Indizes an und führt die Mimetype-Migrationen aus. Nextcloud lässt beides bei der Aktualisierung aus, weil es auf grossen Installationen lange dauert, und meldet es in der Übersicht. Mit `0` unterbleiben beide Schritte; sie gehören dann von Hand in ein Wartungsfenster (`occ db:add-missing-indices`, `occ maintenance:repair --include-expensive`).

Variablen für die amtlichen Dokumente:
- `PARLWIN_DOKUMENT_PRO_LAUF` (Standard: `25`) So viele amtliche Dokumente liest ein Lauf höchstens. Die Synchronisation verzeichnet jedes Dokument sofort mit Titel, Kategorie und Datum; den Inhalt liest sie nur bis zu dieser Zahl, und ein stündlicher Hintergrundauftrag holt den Rest nach. Ohne diese Grenze liefe die erste Synchronisation über tausend Geschäfte mit mehreren PDF je Geschäft stundenlang.
- `PARLWIN_DOKUMENT_ZEIT_PRO_LAUF` (Standard: `120`) So viele Sekunden liest eine Synchronisation höchstens Dokumente. Die Synchronisation meldet dem Status regelmässig, dass sie läuft; bleibt diese Meldung länger als 15 Minuten aus, gilt sie als abgestürzt und wird abgebrochen. Ein einzelnes grosses Dokument kann Minuten dauern, darum hört die Synchronisation nach dieser Zeit auf zu lesen. Verzeichnet werden die Dokumente weiterhin, und der stündliche Auftrag liest sie nach.
- `PARLWIN_DOKUMENT_MAX_MB` (Standard: `150`) Bis zu dieser Grösse wird ein amtliches Dokument gelesen und sein Inhalt abgelegt. Ein grösseres bleibt am Geschäft verzeichnet und trägt seine Grösse als Grund; der Verweis auf das PDF beim Parlament steht weiterhin da. Die Beilagen zum Budget sind die grössten Dokumente, die vorkommen.
- `PARLWIN_DOKUMENT_LESER_SPEICHER` (Standard: `4096M`) So viel Speicher bekommt der Prozess, der ein einzelnes Dokument liest. Jedes Dokument wird in einem eigenen Prozess gelesen, weil das Entpacken der Ströme eines PDF beliebig viel Speicher braucht und ein überschrittenes Limit den Prozess ohne abfangbaren Fehler beendet. Endet dieser Prozess, trägt das Dokument diesen Grund, und der Lauf liest weiter. Ein Lauf hört ausserdem auf, sobald die Hälfte von `PARLWIN_PHP_MEMORY_LIMIT` belegt ist.

Wer nicht auf den stündlichen Auftrag warten will, liest die Dokumente von Hand nach:

```bash
docker compose exec nextcloud-php-fpm php occ parlwin:dokumente-lesen --anzahl=200
```

Ein Dokument, das an einer Grenze gescheitert ist, trägt seinen Grund und wird nicht immer wieder versucht. Nach einer angehobenen Grenze holt `--auch-gescheiterte` genau diese zurück:

```bash
docker compose exec nextcloud-php-fpm php occ parlwin:dokumente-lesen --anzahl=200 --auch-gescheiterte
```

Variablen für den Speicher:
- `PARLWIN_PHP_MEMORY_LIMIT` (Standard: `1024M`) Die Speichergrenze von PHP auf der Kommandozeile im Container, gültig für `occ`, den Hintergrundauftrag und die Synchronisation; die Compose-Dateien reichen diese Variable durch. Der Start des Containers schreibt den Wert in ein eigenes ini-Verzeichnis, das `PHP_INI_SCAN_DIR` zusätzlich zum Standard nennt. Ohne diese Datei gälte dort `MEMORY_LIMIT` des Images (Standard `1000M`). Ein Budgetbuch braucht beim Einlesen 574,7MB, gemessen am Buch 2027. Für die Anfragen an php-fpm gilt `MEMORY_LIMIT` des Images `mwaeckerlin/nextcloud:php-fpm` (Standard `1000M`): Das Image setzt diese Grenze beim Start von php-fpm, und sie übersteuert jede ini-Datei.
- `PARLWIN_BUDGET_MEMORY_LIMIT` (Standard: `1024M`) Die Speichergrenze, die während des Lesens eines Budgetbuchs gilt; danach gilt wieder die der Installation. Sie greift dort, wo die Installation weniger gibt als das Buch braucht, etwa in einer Anfrage an php-fpm oder ausserhalb des mitgelieferten Containers. Ein Buch belegt beim Lesen mehrere hundert Megabyte, mehr, als Nextcloud einer Anfrage standardmässig zugesteht. Wächst das Buch weiter, gehört der Wert hier herauf; eine bereits höhere oder aufgehobene Grenze der Installation bleibt unangetastet.

Die Datei `.env` entsteht aus der Vorlage:

```bash
cd parliament-winterthur-tool
cp .env.sample .env
```

Starten:

```bash
cd parliament-winterthur-tool
npm run start:daemon
```

Die npm-Skripte tragen dieselben Namen wie in den übrigen Projekten der Familie:
- `npm run build`: `docker compose build`
- `npm start`: `docker compose up --build --force-recreate --remove-orphans`, im Vordergrund mit den Protokollen aller Container
- `npm run start:daemon`: dasselbe im Hintergrund
- `npm run start:dev`: dasselbe wie `npm start`
- `npm stop`: `docker compose down --remove-orphans`, entfernt die Container und Netze und lässt die Volumes stehen

Für den lokalen Betrieb gilt ausserdem:
- nginx wartet `MAX_INPUT_TIME` Sekunden auf die Antwort von PHP (Standard `3600`), damit eine vollständige Synchronisation und das Einlesen eines Budgetbuchs nicht nach den 60s, die nginx ohne Angabe wartet, mit HTTP 504 abbrechen; beide dauern Minuten. Diese Grenze setzt die Location für PHP im Basis-Image `mwaeckerlin/nextcloud:nginx`. Die `36000s` in `docker/nginx/parlwin.conf` stehen auf der Ebene des Servers und gelten für keine Anfrage an PHP, weil die Location für PHP ihre eigene Angabe trägt.
- Nach Änderungen an einem `Dockerfile` immer mit Neubau starten (`npm run start:daemon` oder `docker compose up -d --build`).
- `parlwin-watcher.php` im Container `nextcloud-php-fpm` aktiviert die App beim Start, sobald Nextcloud installiert ist.
- Das Volume `apps` auf `/app/custom_apps` hält die Apps von Nextcloud. Das Verzeichnis `parlwin` darin ersetzt der Start des Containers jedes Mal durch die Fassung aus dem Image (`docker/parlwin-bootstrap.php`), also läuft nach jedem Neubau die Oberfläche aus dem Image.

Stoppen:

```bash
cd parliament-winterthur-tool
npm stop
```

Der HTTP-Port ist lokal standardmässig `29824` (anpassbar über `NEXTCLOUD_HTTP_PORT`), auf allen Adressen des Rechners, IPv4 und IPv6; `BIND_ADDRESS` beschränkt ihn auf eine Adresse, `127.0.0.1` auf den eigenen Rechner. Den WebSocket-Server erreicht der Browser über denselben Port, unter `ws://localhost:29824/ws/parlwin/` (siehe «WebSocket-Dienst»).

Von Hand aktiviert wird die App so:

```bash
docker compose exec nextcloud-php-fpm php occ app:enable parlwin
```

### WebSocket-Dienst (`parlwin-realtime`)

Die App folgt der **Regel für WebSocket-Apps** von `mwaeckerlin/nextcloud:nginx` (siehe README dort, Abschnitt «WebSocket Apps»):

- Der Dienst im Compose heisst **`parlwin-realtime`** und hört intern auf Port **`3001`**.
- Kein Port muss nach aussen freigegeben werden: Der Browser erreicht den WebSocket-Server **am gleichen Rechner und Port wie Nextcloud selbst** über den Pfad **`/ws/parlwin/`**. `nextcloud-nginx` schaltet die Verbindung auf WebSocket um und reicht sie unverändert an `http://parlwin-realtime:3001/` weiter.
- Die Verbindung des Browsers geht an dieselbe Herkunft wie die Seite, und die vorgegebene CSP von Nextcloud (`connect-src 'self'`) deckt sie ab, ohne dass etwas überschrieben werden muss. Ein zweiter Port bräuchte eine Ausnahme in der CSP.
- PHP ruft den Dienst direkt über den internen Hostnamen auf (`http://parlwin-realtime:3001/publish`), etwa um ein Ereignis zu veröffentlichen; nginx liegt nicht auf diesem Weg.

Wer Parlwin in einer bestehenden Nextcloud-Installation einsetzt, muss **nginx nicht anpassen**: Es genügt, den Dienst `parlwin-realtime` in die eigene Compose-Datei aufzunehmen und ans gleiche Netz wie `nextcloud-nginx` zu hängen.

Die Dienste `nextcloud-nginx` und `nextcloud-php-fpm` werden aus `Dockerfile.nginx` und `Dockerfile.php-fpm` gebaut und bauen auf:
- `mwaeckerlin/nodejs-build` (Bau-Stufe für die Oberfläche in `Dockerfile.php-fpm` und `Dockerfile.realtime`)
  - Die gebauten Dateien entstehen unter einem eigenen Benutzer (`BUILD_USER`).
- `mwaeckerlin/nodejs` (Grundlage für den Betrieb von `parlwin-realtime`, mit eigenem Benutzer `RUN_USER`)
- `mwaeckerlin/nextcloud:nginx` (auf einem `nc`-Zweig der Tag der Linie, `:nginx-nc<version>`)
- `mwaeckerlin/nextcloud:php-fpm` (auf einem `nc`-Zweig `:php-fpm-nc<version>`)
- `mariadb` (`latest`)
- `collabora/code`
- `parlwin-realtime` (WebSocket-Verteiler auf Node, gebaut aus `Dockerfile.realtime`)

### Synchronisation

Von Hand startet der Administrator die Synchronisation auf der Kommandozeile:

```bash
php /path/to/nextcloud/occ parlwin:sync
```

Nur die Beschlüsse des Stadtrats holen:

```bash
php /path/to/nextcloud/occ parlwin:sync --nur-stadtratsbeschluesse
```

Antwortet die Webseite der Stadt nicht, laufen die übrigen Bereiche zu Ende, und die Meldung steht in der Ansicht «Protokoll» und in der Verwaltung unter der Statuszeile der Synchronisation, etwa «Die Liste der Stadtratsbeschlüsse unter https://stadt.winterthur.ch/stadtratsbeschluesse ist nicht erreichbar (HTTP 500).». Sie nennt die Adresse und den HTTP-Status oder den Grund des Netzfehlers, etwa «keine Verbindung zum Rechner».

Laufende Synchronisation abbrechen:

```bash
php /path/to/nextcloud/occ parlwin:sync:cancel
```

Ein Budgetjahr neu aus dem Budgetbuch einlesen, etwa wenn die Datenbank eine fehlerhafte Fassung hält. Der Befehl ersetzt Produktegruppen, Investitionen und Kennzahlen, und die eigenen Anträge bleiben erhalten:

```bash
php /path/to/nextcloud/occ parlwin:budget-reimport 2026
```

Mit `--purge` werden zusätzlich alle Anträge und Pauschalanträge des Jahres gelöscht, das Jahr beginnt also vollständig von vorn:

```bash
php /path/to/nextcloud/occ parlwin:budget-reimport 2026 --purge
```

In der Verwaltung startet «Jetzt synchronisieren» denselben Ablauf und zeigt den Fortschritt laufend an:
- den aktuellen Bereich samt betroffenen Tabellen
- `processed/total`
- die bisherige Laufzeit und die voraussichtliche Restzeit (`hh:mm:ss`)
- Stand über die Schnittstelle: `GET /apps/parlwin/sync/status`
- Abbruch über die Schnittstelle: `POST /apps/parlwin/sync/cancel`

#### Typen für eigene Geschäfte

Die Typen, die beim Anlegen eines eigenen Geschäfts zur Auswahl stehen, pflegt der Administrator im Bereich «Typen für eigene Geschäfte» (App-Einstellung `eigene_typen`, JSON-Liste von Bezeichnungen). Ohne Eintrag bleibt es beim Typ «Eigenes Geschäft». Für den Status eines eigenen Geschäfts schlägt die App die Werte vor, die in der Datenbank bereits vorkommen (`GET /apps/parlwin/geschaefte/statuswerte`); jeder andere Wert lässt sich eintragen.

#### Hintergrundauftrag

Das Plugin registriert einen Hintergrundauftrag, der die Daten automatisch synchronisiert. Den Zeitplan stellt die Verwaltung ein: beliebige Einträge mit Wochentagen (Mo–So) und Uhrzeit (App-Einstellung `sync_zeitplan`, JSON `[{"tage":[1,3],"zeit":"06:30"}]`, 1 = Montag … 7 = Sonntag, Europe/Zurich); verpasste Zeitpunkte werden nachgeholt. Ohne konfigurierten Zeitplan gilt der Standard: **zwei Läufe an allen Wochentagen, um 10:00 und um 18:00 Uhr**; dieser Standard erscheint in der Verwaltung vorbelegt und lässt sich dort bearbeiten.

Den Cron von Nextcloud ruft der mitgelieferte Container selbst auf: `parlwin-watcher.php` setzt `backgroundjobs_mode=cron` und startet den Cron in festem Abstand (`PARLWIN_CRON_INTERVAL`, Standard 300s). Ein Cron-Dienst auf dem Rechner ist nicht nötig.

Der Hintergrundauftrag nutzt denselben Befehl wie die Verwaltung (`--source=background-job`). Ein automatisch gestarteter Lauf erscheint darum ebenfalls im Fortschritt der Verwaltung (`/sync/status`), mit Quelle, Stand und voraussichtlicher Restzeit.


## Entwicklung

```bash
cd parliament-winterthur-tool
npm install
npm run composer:update   # holt parlwin/vendor samt PHPUnit aus dem Bau
npm run composer:update -- --lock   # erzeugt parlwin/composer.lock neu und prüft sie mit composer audit
npm run dev               # baut die Oberfläche bei jeder Änderung neu
npm run lock:aktualisieren   # erzeugt package-lock.json mit dem npm des Baus neu
```

`npm run lock:aktualisieren` gehört zu jeder Änderung an `dependencies`, `devDependencies` oder `overrides` in `package.json`. Das Skript löst die Abhängigkeiten in der Stufe `npm-lock` von `Dockerfile.php-fpm` mit dem npm von `mwaeckerlin/nodejs-build` auf, holt `package-lock.json` heraus und installiert sie mit derselben Fassung von npm in `node_modules`. npm 9 bricht beim Auflösen dieses Baums mit «Cannot read properties of null (reading 'edgesOut')» ab.

`parlwin/vendor` ist git-ignoriert und entsteht im Bau: Die Stufe `php-deps` von `Dockerfile.php-fpm` installiert mit `composer install --no-dev` genau die Fassungen aus `parlwin/composer.lock` für das ausgelieferte Image, die Stufe `php-deps-test` dieselben samt PHPUnit. `npm run composer:update` holt die zweite auf den Rechner, also braucht der Rechner weder Composer noch PHPUnit. Die Tests nehmen immer dieses PHPUnit. Ein PHPUnit des Rechners wäre eine zweite, andere Fassung und gäbe über denselben Tests ein anderes Ergebnis.

`npm run composer:update -- --lock` gehört zu jeder Änderung an `parlwin/composer.json`. Das Skript löst die Abhängigkeiten in der Stufe `composer-lock` von `Dockerfile.php-fpm` neu auf, schreibt `parlwin/composer.lock`, prüft sie in der Stufe `php-audit` mit `composer audit --locked --no-dev` und holt danach `parlwin/vendor`. Meldet das Audit eine Lücke, bricht das Skript ab, und `parlwin/vendor` bleibt unverändert. Jede Fassung in `parlwin/composer.lock` ist mindestens zwei Wochen alt; das Feld `time` jedes Pakets nennt ihr Erscheinungsdatum.

Die Obergrenzen in `parlwin/composer.json` halten Fassungen fern, die beim Festlegen jünger als zwei Wochen waren: `league/commonmark` unter 2.10.5, denn 2.10.5 bis 2.10.7 erschienen vom 2026-10-30 an; `phpunit/phpunit` unter 13.4, denn 13.4.0 bis 13.4.3 tragen die Lücke CVE-2026-119193, und die Behebung 13.4.4 erschien am 2026-10-27. Unter `conflict` stehen `nette/schema` ab 1.4.0 (2026-10-22), `phpunit/php-code-coverage` ab 14.5.0 (2026-10-30), `sebastian/git-state` ab 1.1.0 (2026-10-21) und `symfony/polyfill-mbstring` ab 1.43.1 (1.43.3 vom 2026-10-28). `conflict` begrenzt Pakete, die nur über eine andere Bibliothek kommen, ohne sie zu einer eigenen Abhängigkeit zu machen. Eine Obergrenze fällt weg, sobald die Fassungen, die sie ausschliesst, zwei Wochen alt sind, für alle spätestens am 2026-11-16. Dieses Datum steht in `extra.comment-obergrenzen`, und danach schlägt `parlwin/src/js/tests/abhaengigkeiten-sicherheit.test.js` fehl.

Die Architektur mit Diensten, Bausteinen, Abläufen und Entscheidungen steht in [ARCHITECTURE.md](ARCHITECTURE.md); `npm run build:doc` zeichnet ihre Diagramme nach `doc/`.

### Tests ausführen

Ein vollständiges Verzeichnis aller Tests, nach Testart gruppiert und je Test mit der abgedeckten Funktionsnummer aus [FEATURES.md](FEATURES.md), steht in [TESTS.md](TESTS.md).

Alle Tests (Klassen und Dienste, Abfragen gegen die echte Webseite, e2e) im Wurzelverzeichnis des Projekts:

```bash
cd parliament-winterthur-tool
npm test
```

Der Lauf ruft jede Gruppe aus `tests/run-all.sh` auf: `php-unit`, `php-pdf`, `js` (vitest), `php-live`, `compose` (Compose-Vertrag), `image` (Image-Vertrag), `quelle` (Vertrag der lokalen Quelle `parlwin-quelle`), `audit` (npm audit), `composer-audit` (composer audit über `parlwin/composer.lock`) und die E2E-Tests. Den Lint (`lint`) ruft `tests/run-all.sh lint` einzeln auf; im Lauf steht er nicht, weil ESLint im Projekt keine Konfiguration hat. Für die E2E-Tests baut er ein Compose-Projekt für Chromium (`parlwin_e2e_chromium`, mit dem iPhone-Projekt) und, wenn der Speicher reicht, eines für Firefox (`parlwin_e2e_firefox`) und hält sie bis zum Ende des Laufs. Jedes Image baut er nur, wenn sein Tag aus dem Hash seiner Eingaben noch fehlt. Sind alle Tests grün, schreibt er ihr Urteil ins Journal und nimmt danach auf dem Chromium-Stack die Bilder des Design-Laufs auf, nach `/tmp/parlwin-design/out`; die Aufnahme ist der Fall `bild:aufnahme` und gibt nur mit mindestens einem Bild frei.

Der Lauf schreibt jeden Test vor dem Start und nach dem Ergebnis in das Journal `tests/.regression/journal.jsonl`. Bricht er ab, setzt der nächste `npm test` mit dem unterbrochenen Test fort und behält jeden grünen. Ist ein Test rot, führt der nächste Aufruf nur die roten und die von ihnen abhängigen Tests aus, die noch nie grün waren; sind danach alle grün, folgt im selben Aufruf die vollständige Schlussregression. Nur sie gibt einen Stand frei, und nur, wenn auch die Aufnahme der Bilder grün ist und der Quellstand am Ende derselbe ist wie am Anfang. Bricht der Lauf in der Aufnahme ab, nimmt der nächste `npm test` nur die Bilder auf. `php-live`, `audit` und `composer-audit` fragen Dienste ausserhalb des Projekts und laufen in jeder Schlussregression; eine andere Gruppe, deren Eingaben seit ihrem letzten grünen Lauf gleich sind, läuft nicht erneut. Ein roter Test hält die von ihm unabhängigen nicht auf.

Ein Playwright-Test, der auf dem Ergebnis eines anderen Tests derselben Datei und desselben `describe`-Blocks aufbaut, nennt dessen Titel mit der Annotation `braucht`, und eine Reparaturrunde führt den Vorgänger mit aus. Steht im selben `describe`-Block kein Test mit diesem Titel, bricht das Inventar mit einer Meldung ab. Beendet ein Signal (SIGINT, SIGTERM, SIGHUP) den Lauf, entfernt er jeden Container der Tests, in dem gerade Playwright läuft; der nächste Aufruf legt ihn neu an.

| Aufruf | Wirkung |
| --- | --- |
| `npm test -- --list` | Inventar mit Nummer, Kennung und Voraussetzungen jedes Tests |
| `npm test -- --from <kennung> --to <kennung>` | nur dieser Bereich (Kennung oder Nummer aus `--list`), ohne Freigabe |
| `npm test -- --neu` | ein neuer Lauf statt Fortsetzen |
| `npm test -- --ohne-bild` | ohne die Bilder des Design-Laufs; ohne sie gibt der Lauf nicht frei |
| `npm test -- --behalten` | die Stacks bleiben nach einem freigegebenen Lauf stehen |
| `npm test -- --npnp <datei.json>` | zuerst die Doppelprüfung nach der Datei, dann die Schlussregression und die Bilder |
| `npm run test:stack -- ab` | baut die Stacks des Laufs ab |

Eine Gruppe allein, ohne Journal: `bash tests/run-all.sh <gruppe>`, etwa `bash tests/run-all.sh php-unit`. Nach `--` folgen Argumente für das Werkzeug, etwa `--filter` für PHPUnit. `npm run test:unit` und die übrigen Skripte `test:*` führen ihren Teil der Tests einzeln aus, ebenfalls ohne Journal. Zwei sind anders: `test:ci` ruft `npm test` ohne die E2E-Tests auf und schreibt das Journal, und `test:stack` ruft `tests/regression/stack.mjs` auf. Dort baut `auf` die Stacks für Chromium und Firefox auf, ohne zu prüfen, ob der Speicher für den zweiten reicht, und führt dabei jeden Abschnitt von `run-compose-e2e.sh` aus, der im vollen Lauf vor Playwright steht; ein roter Abschnitt des Aufbaus bricht ab, von den übrigen meldet es die roten. `ab` baut die Stacks ab, `status` nennt ihren Stand, und `bild [<ziel>]` nimmt die Bilder des Design-Laufs auf, ohne Angabe nach `/tmp/parlwin-design/out`. `--browser chromium` oder `--browser firefox` beschränkt `auf`, `ab` und `status` auf einen Stack.

Die Datei der Doppelprüfung nennt unter `schalter` die Schalter im Format `[Kennung, Datei, Zeile, alt, neu, Art]` und unter `phasen` je Phase ihren Namen (`name`), die Kennungen der eingeschalteten Schalter (`schalter`), die Tests und die Erwartung. Die Tests einer Phase stehen unter `vitest` (`dateien`, `t`), `php` (`filter`), `compose`, `lint` und `e2e` (`dateien`, `grep`). Die Erwartung steht unter `rot` und `gruen`, je mit Teilen von Kennungen, und `uebrige` sagt, ob jeder nicht genannte Test grün sein muss (`gruen`, der Wert, der gilt, wenn nichts gesetzt ist). Ein Schalter der Art `alle` gilt in jedem Schritt der Phase, einer der Art `vitest` oder `php` nur in diesem Schritt. Für den Schritt `e2e` baut ein Schalter in `parlwin/src` die Oberfläche in der Stufe `app-build` von `Dockerfile.php-fpm`, und ein Schalter in einer anderen Datei unter `parlwin/` geht als Datei mit; ein Schalter ausserhalb von `parlwin/` wirkt im Stack nicht, und der Lauf meldet das. Vor diesem Schritt entfernt der Lauf je Stack den Container `nextcloud-php-fpm`, spielt die Datenbank und das Volume `data` auf den Stand zurück, den die Browser-Tests im vollen Lauf vorfinden, legt den Container aus dem Image neu an, kopiert die neu gebaute Oberfläche (`js/`, `css/`) und die geschalteten Dateien hinein und startet ihn. Nach jedem Schritt liegen die geschalteten Dateien wieder so da wie vorher, auch nach einem Abbruch.

Speicher: Vor dem zweiten Stack misst der Lauf `MemAvailable` und den Bedarf des ersten Stacks (`docker stats`) und startet ihn nur, wenn danach 8GiB frei bleiben; sonst laufen alle Browser im Chromium-Stack. vitest läuft mit zwei Workern. Alle 30 Sekunden stehen `MemAvailable` und die Werte der eigenen cgroup (`memory.current`, `memory.high`, `memory.max`, `memory.events`) in `tests/.regression/speicher.jsonl` und im Protokoll `tests/.regression/lauf.log`. Die Dauer jeder Phase steht in beiden und im Journal.

Test des Parsers gegen die echten Adressen (ohne Datenbank):

```bash
cd parliament-winterthur-tool
npm run test:live
```

Dieser Test ruft `parlament.winterthur.ch` auf und prüft nur, wie das HTML gelesen wird (`data-entities`); die Datenbank bleibt aussen vor.

### Testlauf im Bau

Der Bau auf GitHub ruft `npm run test:ci` auf (Eingabe `test` der gemeinsamen Vorlage `mwaeckerlin/scratch`): zuerst `npm run composer:update`, dann derselbe `npm run test` wie auf dem Rechner. Der Runner hat weder Composer noch PHPUnit noch `parlwin/vendor`, und `npm test` allein könnte die PHP-Suiten darum nicht starten.

Die PHP-Suiten laufen in einem eigenen Image (`Dockerfile.php-test`, gebaut über `tests/docker-compose.php-test.yml` und gestartet über `tests/php-suite.sh`), also mit dem PHP der Anwendung. Das PHP des Rechners ist eine andere Fassung und prüft damit die falsche Laufzeit; auf dem Runner liegt PHP 8.3, und PHPUnit 13 braucht mindestens 8.4.1.

Die Gruppe `php-live` läuft im Bau mit. Sie fragt `parlament.winterthur.ch` lesend ab, mit acht Anfragen: Das Werkzeug hat diese Webseite als einzige Datenquelle, und eine geänderte Seitenstruktur fällt im wöchentlichen Bau auf, bevor eine Synchronisation damit scheitert.

Der E2E-Lauf fragt die Webseite des Parlaments nicht ab. Er bringt seine eigene Quelle mit: den Dienst `parlwin-quelle` (`Dockerfile.quelle`), einen Webserver, der die gespeicherten Seiten und PDF aus `parlwin/tests/Fixtures` unter denselben Adressen ausliefert. Die Synchronisation zeigt mit `PARLWIN_QUELLE_BASE_URL` dorthin, und eine Prüfung im Lauf misst am Zugriffsprotokoll von `parlwin-quelle`, dass sie dort war. Gegen die Webseite des Parlaments müsste ein Lauf 1236 Detailseiten holen und bräuchte dafür auf dem GitHub-Runner mehr als 34 Minuten.

`parlwin-quelle` erzeugt beim Bauen, was die gespeicherten Seiten nicht enthalten: je Budgetjahr eine Weisung aus der Vorlage von 2027 mit den Büchern des Jahres, und für jede Behörde der Fraktions- und der Kommissionsliste eine Detailseite mit heutigen Mitgliedern. Ihre Adressen prüft `npm run test:quelle` einzeln, ohne Stack.

`parlwin-quelle` hat einen Steuerweg für die Tests: `PUT http://parlwin-quelle:8080/_steuerung/stadtratsbeschluesse-fehler` lässt die Liste der Stadtratsbeschlüsse mit 500 antworten, `DELETE` auf dieselbe Adresse nimmt das zurück. Nur das Abbild der Quelle kennt diesen Weg.

Der E2E-Teil läuft im Bau **nicht** mit (`PARLWIN_TESTS_OHNE_E2E=1` im Befehl `test:ci`): Er startet acht Container samt Browsern und läuft auf dem Rechner der Entwicklung.

Mit `PARLWIN_TESTS_OHNE_E2E=1` lässt `npm test` die Stacks, die E2E-Tests und die Bilder weg; Image- und Quellen-Vertrag bauen ihre Images dann selbst.

`npm test` ist **streng** eingestellt: `skip`, `warning`, `deprecation`, `notice`, `risky` und `incomplete` gelten als **Fehler**.

### Automatischer E2E-Test (Compose-basiert)

Der E2E-Test nutzt dieselbe `docker-compose.yml` wie der lokale Betrieb, ergänzt um `tests/e2e/docker-compose.e2e.yml`:

```bash
cd parliament-winterthur-tool
./tests/e2e/run-compose-e2e.sh
```

Das Skript besteht aus Abschnitten mit eigener Kennung (`./tests/e2e/run-compose-e2e.sh --liste`). Ein roter Abschnitt beendet nur sich selbst; gesperrt sind die Abschnitte, die ihn voraussetzen, alle übrigen laufen weiter. `--nur <kennung,…>` führt einzelne Abschnitte gegen einen laufenden Stack aus; so ruft `npm test` sie auf. Was ein Abschnitt an Variablen setzt, liest der nächste aus `zustand/variablen.sh`; die Datei kann nur das eigene Konto lesen, und eine Variable, die das Skript erbt, steht nicht darin.

Die Browser-Tests melden jeden Testbenutzer, für den ein Passwort gesetzt ist, einmal je Lauf an, bis zu vier, und legen ihre Cookies als `storageState` ab (`tests/e2e/globale-anmeldung.js`); `login()` in `tests/e2e/anmeldung.js` übernimmt sie und meldet über das Formular an, wenn die Sitzung nicht mehr gilt. Der Container der Tests ist der Dienst `playwright` des Compose-Projekts; `npm run test:stack -- ab` entfernt ihn mit dem Stack und meldet einen Fehler, wenn danach noch ein Container des Projekts steht. Playwright läuft je Stack mit einem Worker; `PW_WORKERS=2` startet zwei. Chromium und die Tests am Telefon laufen in einem Stack nacheinander; reicht der Speicher des Rechners, läuft Firefox gleichzeitig in einem zweiten Stack, sonst im selben. `tests/e2e/sperre.js` führt in `ALLEIN` die Dateien, die globale Einstellungen schreiben, Anfragen und Zähler einer ganzen Ansicht messen oder ein synchronisiertes Geschäft ändern, das andere Dateien lesen; sie laufen allein, wenn ein Lauf mit `PW_WORKERS=2` mehrere Worker startet.

Direkt aufgerufen führt das Skript einen vollständigen End-to-End-Ablauf aus:
- erzeugt bei jedem Lauf eine **neue leere Datenbank** (`docker compose down -v` und `up`)
- aktiviert die App und legt eigene Benutzer für den Test an
- synchronisiert die gespeicherten Seiten des Parlaments aus `parlwin-quelle` über dieselbe Schnittstelle wie der Knopf `Jetzt synchronisieren` in der Oberfläche (`POST /apps/parlwin/sync`)
- prüft, ob die eingelesenen Listen plausibel sind (Geschäfte, Sitzungen, Mitglieder, Kommissionen, Fraktionen)
- prüft die Startseite der Oberfläche (`/apps/parlwin/`)
- prüft `parlwin-realtime` (`/health`) und ob die Oberfläche dessen Konfiguration ausgeliefert bekommt
- prüft das ausgelieferte CSS der App auf die Umschaltpunkte für breite und schmale Fenster und auf die zentralen Klassen des Layouts (`80rem`, `54rem`, die Karten der Verwaltung und die Tabellenansicht für schmale Fenster)
- prüft die Sicherheit des laufenden Containers `parlwin-realtime` (läuft nicht als root, die gebauten Dateien sind dort nicht schreibbar)
- prüft die von Hand ausgelöste Synchronisation samt Fortschritt (`POST /sync` und `GET /sync/status`) mit den laufenden Zählwerten `processed/total`
- schreibt über dieselbe Schnittstelle wie die Oberfläche (Notiz, Beschluss, Votum, Zuständigkeiten)
- prüft den Fraktionssitzungsmodus samt Rechten und Verboten (Mitglied gegenüber Protokollführung und Stellvertretung)
- ändert Felder von Sitzungen und Traktanden und prüft, dass sie gespeichert bleiben
- prüft die Daten über die Antworten der Schnittstelle und zusätzlich direkt per SQL auf den Tabellen
- räumt die Container am Ende wieder auf (`down -v`)
- läuft standardmässig in einem eigenen Compose-Projekt `parlwin_e2e` und ohne nach aussen veröffentlichte Ports (nur interne Docker-Netze), damit die Volumes der Entwicklung (`parlwin_dev`) nicht gelöscht werden

Die Synchronisation lässt sich je Bereich auf eine Zahl von Einträgen begrenzen. Jede Grenze ist standardmässig leer, und leer heisst ohne Grenze; auch `tests/e2e/run-compose-e2e.sh` setzt sie leer. Der Code liest die Grenzen in jeder Umgebung, `docker-compose.yml` reicht alle ausser `PARLWIN_SYNC_LIMIT_STADTRATSBESCHLUESSE` durch, und `example/docker-compose.yml` reicht keine durch.
- `PARLWIN_SYNC_LIMIT_GESCHAEFTE` Die Quelle liefert ihre Liste unsortiert, und jede Grenze schneidet irgendwann das Budget-Geschäft weg, aus dem der Budget-Import sein Buch holt.
- `PARLWIN_SYNC_LIMIT_SITZUNGEN`
- `PARLWIN_SYNC_LIMIT_MITGLIEDER`
- `PARLWIN_SYNC_LIMIT_KOMMISSIONEN`
- `PARLWIN_SYNC_LIMIT_FRAKTIONEN`
- `PARLWIN_SYNC_LIMIT_STADTRATSBESCHLUESSE` Die Zahl der Beschlüsse.
- `PARLWIN_SYNC_LIMIT_ALL` Eine Grenze für jeden Bereich, der keine eigene trägt.

Die Stadtratsbeschlüsse kommen von der Webseite der Stadt und haben darum zwei eigene Einstellungen. Der Code liest beide in jeder Umgebung; `docker-compose.yml` und der E2E-Lauf reichen sie durch, `example/docker-compose.yml` nicht.
- `PARLWIN_STADT_BASE_URL` Die Adresse der Quelle; leer heisst `https://stadt.winterthur.ch`. Der E2E-Lauf zeigt damit auf `parlwin-quelle`, das auch die Seiten des Parlaments ausliefert. Erlaubt ist eine Adresse mit `http://` oder `https://` und Rechnername, ohne Benutzer, Passwort, Abfrage und Anker; eine andere hält den Bereich der Stadtratsbeschlüsse mit einer Meldung an, die die Einstellung nennt.
- `PARLWIN_SRB_SEITEN` Wie viele Listenseiten gelesen werden, je zwölf Beschlüsse. Standard ist `10`, also die 120 jüngsten; `0` liest alle Seiten. Die Ansicht zeigt davon höchstens 500, weil die Schnittstelle ohne Angabe von `limit` nicht mehr liefert (`StadtratsbeschlussController::GRENZE`).

Eine Grenze für einen einzelnen Lauf steht vor dem Befehl. Sie schneidet Fälle weg, die der Lauf prüft; mit einer Grenze für die Sitzungen fehlt etwa die Sitzung, an der F115 das Protokoll prüft.

```bash
PARLWIN_SYNC_LIMIT_SITZUNGEN=30 ./tests/e2e/run-compose-e2e.sh
```

In Nextcloud 33 hat `OCP\AppFramework\Db\Entity` kein eingebautes `jsonSerialize()`. Die Entities der App implementieren `jsonSerialize()` darum selbst, damit die Endpunkte (`/geschaefte`, `/sitzungen`, `/mitglieder`, `/kommissionen`, `/fraktionen`) JSON liefern.

Wie ein Vorstoss seinen Weg nimmt, beschreibt `parlwin/docs/vorstoss-modell.md` im Einzelnen.

### Dokumentationsregel

- Jede fachliche Änderung (Einlesen, Parser, Aufbau der Datenbank, Rollen, Rechte, Abläufe, Synchronisation, Testaufbau) wird im selben Commit im README beschrieben oder dort nachgeführt.
- Änderungen am Weg eines Vorstosses zusätzlich in `parlwin/docs/vorstoss-modell.md` nachführen.


## Interna

### Datensynchronisation

- Ein Hintergrundauftrag lädt nach Zeitplan alle nötigen Daten von der Parlamentswebseite herunter und speichert sie in der Nextcloud-Datenbank.
- Es werden **keine Einträge gelöscht**. Elemente, die auf der Webseite verschwinden, werden als `gelöscht` markiert (Spalte `geloescht = true`), bleiben aber in der Datenbank erhalten.
- Die Daten stehen als JSON im HTML-Attribut `data-entities="…"` der jeweiligen Seiten und werden von dort ausgelesen.
- Für Geschäfte wird der Link aus dem Titel (`/_rte/information/{id}`) verfolgt, damit zusätzliche Detailfelder (`<dt>/<dd>`) strukturiert eingelesen werden.
- Wichtige Schreibvorgänge werden als Ereignis veröffentlicht; offene Fenster aktualisieren sich über die WebSocket-Verbindung von selbst.
- Der Arbeitsprozess hat keine Zeitgrenze (`set_time_limit(0)`).
- Lebt der Arbeitsprozess nicht mehr, steht der Lauf sofort auf `abgebrochen` und bleibt nicht auf dem letzten Prozentstand stehen.
- Fortsetzen über mehrere Läufe: Nach `abgebrochen` oder `fehler` macht jeder Bereich (`mitglieder`, `fraktionen`, `kommissionen`, `geschaefte`, `sitzungen`) dort weiter, wo er stehen geblieben ist.
- Bereits verarbeitete Datensätze bleiben gespeichert, denn geschrieben wird Element für Element.
- Geschäfte, die lokal als `erledigt` oder `abgeschlossen` markiert sind, überschreibt die Synchronisation nicht; abgeschlossene Geschäfte gelten als endgültig.
- Eine einzige Sperre: Systemweit läuft immer nur **eine** Synchronisation, gleich ob von Hand oder nach Zeitplan gestartet.
- Startet ein zweiter Benutzer während einer laufenden Synchronisation, hängt sich sein Aufruf an den bestehenden Lauf an.
- Auch nach einem Seitenwechsel erscheint eine laufende Synchronisation sofort wieder. Die Aktualisierung kommt als Ereignis über die WebSocket-Verbindung (`sync.progress`); fällt diese Verbindung aus, fragt die Seite regelmässig über HTTP nach.
- In der Verwaltung lässt sich eine laufende Synchronisation über «Synchronisierung abbrechen» anhalten. Der Arbeitsprozess beendet sich geordnet; reagiert er nicht, wird er nach kurzer Frist hart beendet (TERM/KILL), danach steht der Status auf `abgebrochen` und die Sperre ist wieder frei. Das gilt auch gleich nach dem Start: Zwischen dem Start des Arbeitsprozesses und dem Greifen seiner Sperre liegen ein bis zwei Sekunden. Das Abbruch-Signal (die Datei neben der Sperrdatei) trägt seinen Zeitpunkt, der startende Lauf nimmt an, was nach seinem Start kam, und räumt nur weg, was von einem früheren Lauf übrig blieb.
- Die Verwaltung zeigt einen Gesamtfortschritt von 0 bis 100% über alle laufenden Bereiche (`100% = alle Bereiche vollständig synchronisiert`).

Synchronisation über die Schnittstelle starten:
- `POST /apps/parlwin/sync` startet den Lauf im Hintergrund (`202 Accepted`).
- `POST /apps/parlwin/sync/cancel` fordert den Abbruch der laufenden Synchronisation an. Wenn der Lauf sofort beendet werden konnte, kommt die Antwort direkt mit `abgebrochen=true`; sonst bleibt `abbruch_angefragt=true` (`202 Accepted`).
- Wenn bereits ein Lauf aktiv ist, liefert die Schnittstelle `bereits_laufend=true`; der Aufrufer hängt sich an den bestehenden Lauf an.
- Die Arbeit läuft im selben PHP-FPM-Container ohne Shell-Aufruf (kein `bash`/`sh` erforderlich).
- Fortschritt und Abschluss werden über `GET /apps/parlwin/sync/status` geliefert.
- `sync/status` zeigt laufende Synchronisationen unabhängig davon, wer sie gestartet hat (`admin-ui`, `background-job`, `occ`).
- Die Hauptlisten (`Geschäfte`, `Sitzungen`, `Mitglieder`, `Kommissionen`, `Fraktionen`) werden vorab parallel geladen.
- Der Fortschritt für `Geschäfte` hat zwei Phasen:
  - Phase 1: die Detailseiten der Parlamentswebseite laden
  - Phase 2: in `pw_geschaefte` und `pw_geschaeft_ereignisse` schreiben
  - Der Zähler zeigt schon beim Herunterladen laufende Werte.
  - Werden die Seiten ohne `curl_multi` nacheinander geladen, zählt der Zähler je geladenem Geschäft weiter.

### Datenquellen

| Datenquelle          | URL                                                  |
|----------------------|------------------------------------------------------|
| Geschäfte            | https://parlament.winterthur.ch/politbusiness        |
| Sitzungen            | https://parlament.winterthur.ch/sitzung              |
| Mitglieder           | https://parlament.winterthur.ch/stadtparlament/27428 |
| Kommissionen         | https://parlament.winterthur.ch/kommissionen         |
| Fraktionen           | https://parlament.winterthur.ch/fraktionen           |
| Parteien             | https://parlament.winterthur.ch/fraktionen           |
| Stadtratsbeschlüsse  | https://stadt.winterthur.ch/stadtratsbeschluesse     |

### Datenstrukturen

#### Geschäfte

Eingelesene Felder aus der Parlamentswebseite, nur lesbar:
- `id` / `extern_id`: numerische ID aus `/_rte/information/{id}`, die technische Kennung
- `titel`: Bezeichnung des Geschäfts
- `nummer`: Geschäftsnummer
- `typ`: Art des Geschäfts
- `status`: aktueller Stand
- `datum`: Eingangsdatum
- `url`: Link auf die Webseite
- `quelle_hash`: Hash der zuletzt eingelesenen öffentlichen Fassung
- `quelle_aktualisiert_am`: Zeitpunkt der letzten inhaltlichen Änderung an der Quelle

Das rohe JSON der Quelle wird nicht als Ganzes gespeichert (`roh_daten`). Der Import übernimmt nur fachlich relevante, normalisierte Felder.

#### Entwürfe ohne Geschäftsnummer

Nicht oder noch nicht nummerierte Vorstösse stehen in `pw_vorstoss_entwuerfe`:
- `extern_id`: sofern vorhanden, etwa aus `/_rte/information/{id}`
- `titel`, `titel_normalisiert`, `typ`, `eingangsdatum`, `url`
- `status`: etwa `eingereicht_ohne_nummer`, `gematcht`
- `geschaeft_id`: Verknüpfung auf das offizielle Geschäft nach Einreichung oder Nummerierung
- `match_art`: `extern_id` oder `titel`

#### Verfahrensereignisse pro Geschäft

Detailseiten werden über den Link im Titel geladen (`/_rte/information/{id}`) und als Ereignisse gespeichert:
- Tabelle `pw_geschaeft_ereignisse`
- Beispiele: Datum und Art des Beschlusses, Abstimmungsresultat, Fristen, Vorberatung, Erheblicherklärung, Abschreibung

#### Fraktionsinterne Arbeitsdaten pro Geschäft

Die beschreibbaren Felder der Fraktion liegen in eigenen Tabellen, getrennt von den eingelesenen Feldern:

- `pw_geschaeft_zustaendigkeiten`
  - mehrere zuständige Mitglieder je Geschäft
  - Markierung des Hauptzuständigen
- `pw_geschaeft_aktionen`
  - Zeitleiste aller Aktionen der Fraktion mit Autor und Zeitstempel
  - Typen: `notiz`, `beschluss`, `votum`, `zuweisung`
  - Beschlüsse tragen `entscheid_gueltig`; Listen zeigen standardmässig den letzten gültigen Beschluss
- Pro Geschäft berechnet die App einen abgeleiteten `fraktionsstatus`:
  - `offen` (noch kein gültiger Fraktionsbeschluss)
  - `neu_zu_entscheiden` (die Quelle hat sich nach dem letzten Beschluss inhaltlich geändert)
  - `entschieden` (keine Änderung an der Quelle seit dem letzten Beschluss)
- Daraus folgt `entscheidungsbedarf` (bool) für die Listen der Fraktionssitzung

#### Votum mit Formatierung

Das «Votum im Rat» wird in einem Editor erfasst, der das Ergebnis gleich so zeigt, wie es aussieht (`PwWysiwyg.vue`, auf TipTap und ProseMirror aufgebaut). TipTap nutzt auch Nextcloud Text; es liefert sauberes, semantisches HTML, ohne den Umweg über ein Office-Format. Verfügbar sind: fett, kursiv, unterstrichen, durchgestrichen, Überschriften H2 und H3, Aufzählungen und nummerierte Listen, Blockzitat, Verweise mit automatischer Erkennung, Rückgängig und Wiederholen sowie «Formatierung entfernen». Die Werkzeugleiste zeichnet ihre Symbole direkt als SVG (Material Design Icons, Apache 2.0), sodass weder Schriftarten für Symbole noch zusätzliche Aufrufe an den Webserver nötig sind.

#### Votum als PDF herunterladen

Über den PDF-Knopf in der Werkzeugleiste (er erscheint, sobald Inhalt vorhanden ist) öffnet sich `/apps/parlwin/geschaefte/{id}/votum/pdf` in einem neuen Tab. Diese Route gibt das aktuelle Votum als HTML für den Druck auf A4 aus (Helvetica 11pt, Kopf- und Fusszeile mit Linien, eine Tabelle mit den Angaben zum Geschäft und dem letzten gültigen Beschluss) und ruft `window.print()` von selbst auf; im Druckdialog bieten die Browser «Als PDF speichern» an. Dieser Weg spart eine zusätzliche PHP-Bibliothek für PDF samt ihrer Abhängigkeit in Composer.

Das Skript der Druckseite muss ein `nonce="<?php p($votumNonce); ?>"` tragen (gleiche Herleitung wie in `main.php`/`admin.php`). Die Content-Security-Policy von Nextcloud blockiert Inline-Skripte ohne Nonce ersatzlos und ohne sichtbaren Fehler: Ohne Nonce öffnet sich weder der Druck-Dialog automatisch, noch reagiert der Knopf «Als PDF speichern / drucken». Aus demselben Grund bindet das Skript mit der Nonce den Klick an den Knopf; ein Inline-`onclick` würde blockiert. Der Dialog wird zusätzlich über ein Zeitlimit ausgelöst, damit er auch dann erscheint, wenn `document.fonts.ready` nicht auslöst (nicht ladbare Schrift).

Der Wortlaut wird als HTML ausgegeben, damit die Formatierung im Ausdruck erhalten bleibt. Dafür ist `Service\HtmlSanitizer` zuständig: Er parst den Text und behält ausschliesslich die dort aufgezählten Elemente und Attribute; Verweise dürfen nur `http`, `https`, `mailto` oder `tel` als Schema tragen. Eine Negativliste («entferne `script`, entferne `on…`-Attribute») lässt sich umgehen: `<img/onerror=…>` umgeht die Prüfung auf ein Leerzeichen vor dem Attribut, und `href="javascript:…"` kommt darin gar nicht vor. Der Text stammt aus dem Editor, wird aber über die Schnittstelle gespeichert und ist damit frei wählbar; nur die Positivliste begrenzt ihn verlässlich. Zwei Tests prüfen das: `parlwin/tests/Templates/VotumPdfSicherheitTest.php` rendert die ausgelieferte Vorlage, und der e2e-Lauf prüft die Ausgabe des realen Endpunkts.

#### Automatische Zuständigkeit über Kommissionsmitgliedschaft

Ist ein Geschäft in einer Kommission hängig (das letzte Verfahrensereignis nennt die Kommission als Organ) und ist ihm niemand der eigenen Fraktion zugewiesen, weist die Synchronisation, sobald Mitglieder und Geschäfte eingelesen sind, automatisch alle Mitglieder dieser Kommission zu, die zur eigenen Fraktion gehören. Das erste gefundene Mitglied wird Hauptzuständiger; jede Zuweisung steht als gewöhnliche Aktion (Typ `zuweisung`) in der Aktionszeitleiste des Geschäfts. Bestehende Zuständigkeiten überschreibt sie nie.

#### Fraktionssitzungsmodus

- Notizen schreiben immer alle.
- Beschlüsse schreiben im Modus `Fraktionssitzung` nur der Protokollführer oder seine aktive Stellvertretung.
- Der Fraktionspräsident und seine aktive Stellvertretung schalten den Modus um und setzen den Protokollführer.
- Eine befristete Stellvertretung des Protokollführers setzen der Protokollführer oder das Präsidium.
- Bei aktivem Modus filtert die Geschäftsübersicht standardmässig auf `Nur Entscheid nötig`.

Zusätzliche Rollenverwaltung:
- Tabelle `pw_fraktionsrollen` für zeitlich gültige Rollen und Stellvertretungen
- Rollen:
  - `kommissionsmitglied`
  - `fraktionspraesident`
  - `fraktionspraesident_stellvertretung`
  - `protokollfuehrer`
  - `protokollfuehrer_stellvertretung`

#### Sitzungen

Zwei Typen von Sitzungen werden in `pw_sitzungen` gespeichert:

Eine Parlamentssitzung kommt von der Webseite:

- `extern_id` ist gesetzt (ID der Parlamentswebseite)
- Felder: `extern_id`, `titel`, `datum`, `zeit_von`, `zeit_bis`, `ort`, `url`
- Für jede Sitzung wird automatisch ein **Kalendereintrag** im Fraktions-Kalender (`Fraktion <Name>`) erstellt

Eine interne Fraktionssitzung legt die Fraktion selbst an:

- `extern_id` ist NULL, `typ_id` verweist auf einen `pw_sitzungstypen`-Eintrag
- Felder: `titel`, `datum`, `zeit_von`, `zeit_bis`, `ort`, `bemerkungen` (Zweck)
- Traktanden in `pw_traktanden` (ohne Parlamentsgeschäfts-Verknüpfung)
- Optionaler Kalender-Eintrag: DESCRIPTION = Zweck + Traktanden-Liste
- Die Synchronisation mit dem Parlament löscht interne Sitzungen nie, denn sie berücksichtigt nur Zeilen mit `extern_id IS NOT NULL`

#### Traktanden

Felder aus der Parlamentswebseite:
- `sitzung_id`, `nummer`, `titel`, `beschreibung`, `url`
- `geschaeft_id` (Verknüpfung auf `pw_geschaefte`, falls vorhanden)

Fraktionsinterne Felder:
- `bemerkungen`: Kurznotiz zum Traktandum, etwa «Dringlichkeit klären»
- `notizen`: JSON-Array mit Zeitstempel, Autor und Freitext

#### Mitglieder

- Name, Vorname, Partei, Fraktion, E-Mail, Adresse des Fotos, Kennzeichen «aktiv»
- `nextcloud_uid`: Verknüpfung auf den lokalen Nextcloud-Benutzer

#### Kommissionen und Fraktionen

- Name, Beschreibung, Mitgliederliste (extern_id)

### Datenbankmodell

```text
pw_geschaefte               pw_geschaeft_ereignisse
────────────────────────    ───────────────────────
id (=_rte information id)   id
extern_id                   geschaeft_id -> pw_geschaefte
titel                       reihenfolge
nummer                      typ
typ                         organ
status                      label
datum                       wert
url                         datum
geloescht                   erstellt_am
quelle_hash                 aktualisiert_am
quelle_aktualisiert_am

pw_geschaeft_zustaendigkeiten    pw_geschaeft_aktionen
─────────────────────────────    ─────────────────────
id                               id
geschaeft_id -> pw_geschaefte    geschaeft_id -> pw_geschaefte
uid                              typ
hauptzustaendig                  titel
erstellt_am                      text
aktualisiert_am                  autor_uid
                                 autor_name
                                 entscheid_gueltig
                                 erstellt_am
                                 aktualisiert_am

pw_mitglieder               pw_fraktionen        pw_kommissionen
──────────────────────────  ──────────────────   ───────────────
id                          id                   id
extern_id                   extern_id            extern_id
name, vorname               name                 name
partei                      aktiv                beschreibung
fraktion                    erstellt_am          aktiv
email, foto_url             aktualisiert_am      erstellt_am
aktiv, geloescht                                 aktualisiert_am
nextcloud_uid

pw_sitzungen                pw_traktanden
──────────────────────────  ──────────────────────────
id                          id
extern_id                   sitzung_id -> pw_sitzungen
titel                       geschaeft_id -> pw_geschaefte
datum                       nummer
zeit_von, zeit_bis          titel
ort, url                    beschreibung, url
typ                         bemerkungen
kommission_id               notizen (JSON)
geloescht                   geloescht
erstellt_am                 erstellt_am
aktualisiert_am             aktualisiert_am

pw_vorstoss_entwuerfe               pw_fraktionsrollen
───────────────────────────────     ─────────────────────────
id                                  id
extern_id                           uid
titel, titel_normalisiert           name
typ                                 rolle_code
eingangsdatum, url                  gueltig_von, gueltig_bis
status                              gesetzt_von_uid
geschaeft_id -> pw_geschaefte       aktiv
match_art                           erstellt_am
erstellt_am                         aktualisiert_am
aktualisiert_am

pw_sitzungstypen                     pw_sitzungstyp_teilnehmer
────────────────────────────         ─────────────────────────
id                                   id
name                                 typ_id -> pw_sitzungstypen
zweck                                art (eigeneFraktion|mitglied|ncUser|…)
kalender_anlegen                     referenz_id
einladung_versenden                  referenz_name
standard_ort                         erstellt_am
standard_zeit_von
standard_zeit_bis
geloescht
erstellt_am
aktualisiert_am

pw_sitzungstyp_traktanden
─────────────────────────
id
typ_id -> pw_sitzungstypen
position
titel
beschreibung

pw_sitzung_geschaeft                 pw_vorstoesse
─────────────────────────────        ─────────────────────────
id                                   id
sitzung_id -> pw_sitzungen           titel, art
geschaeft_id -> pw_geschaefte        herkunft (eigene|fremde)
automatisch (0|1)                    status (neu|entwurf|bereit|
                                       eingereicht|erledigt|pausiert)
                                     beschluss, zustaendigkeit
                                     inhalt, dokument
                                     geloescht
                                     erstellt_am, aktualisiert_am

pw_fragestunden                      pw_fragestunde_fragen
─────────────────────────────        ──────────────────────────────────
id                                   id
datum                                fragestunde_id -> pw_fragestunden
titel                                urheber_key, urheber_name
frist (Donnerstag davor)             frage (höchstens 1'000 Zeichen)
geschaeft_id -> pw_geschaefte        kommentar
erstellt_am                          einreicher_key, einreicher_name
aktualisiert_am                      status (neu|besprochen|
                                       eingereicht|zurueckgezogen)
                                     geloescht
                                     erstellt_am, aktualisiert_am

pw_stadtratsbeschluesse
─────────────────────────────
id
nummer (2026/1279)
extern_id (20261279, eindeutig)
titel
datum (beschlossen)
departement
sitzung
publiziert_am
url
dokumente (JSON: titel, url)
erstellt_am, aktualisiert_am
```

Zusätzliche Spalten für die Sitzungs-Verknüpfung:

- `pw_sitzungen.verknuepfung_id`: Gruppen-ID verknüpfter Sitzungen.
- `pw_sitzungstypen.verknuepfen`: beim Anlegen die Verknüpfung anbieten.
- `pw_sitzungstypen.kommissionen`: JSON-Liste der IDs der «beratenen» Kommissionen, deren hängige Geschäfte die App automatisch über `pw_sitzung_geschaeft` verknüpft.

`pw_stadtratsbeschluesse` hält die Beschlüsse des Stadtrats, wie sie auf der Webseite der Stadt stehen. Die Synchronisation schreibt nur die Felder der Quelle; die Notizen der Fraktion liegen im geteilten Speicher der Notizen wie am Geschäft und am Vorstoss und bleiben bei jedem Lauf erhalten.

`pw_vorstoesse` hält die Vorstösse, die die Fraktion selbst pflegt (Tab «Vorstösse»). `pw_vorstoss_entwuerfe` hält die aus der Quelle des Parlaments eingelesenen, noch nicht nummerierten Vorstösse.

### Gemeinsam arbeiten in Echtzeit (WebSocket)

- Ereignisse, die der Server bei Änderungen sendet:
  - `geschaefte.updated`, `geschaefte.action`
  - `sitzungen.updated`, `traktanden.updated`
  - `fraktionssitzung.updated`, `fraktion.roles.updated`
  - `fragestunde.updated`
  - `stadtratsbeschluesse.updated`
  - `sync.progress`, `sync.cancel.requested`, `sync.cancelled`, `sync.failed`
  - `sync.completed`
- Die Oberfläche verbindet sich immer mit dem Dienst `parlwin-realtime`, über den Pfad `/ws/parlwin/` am Port von Nextcloud (`ws(s)://<host>[<WEBROOT>]/ws/parlwin/`).
- Jeder Verbindungsaufbau wird gegen die laufende Nextcloud-Anmeldung geprüft (Sitzungs-Cookie, App-Passwort oder HTTP-Basisauthentifizierung).
- Offene Fenster hören auf diese Ereignisse und laden nur die geänderten Einträge nach, etwa ein einzelnes Geschäft oder die Notizen eines Traktandums; `sync.completed`, `sync.cancelled` und `sync.failed` nennen in `geschaeftIds` die Geschäfte, die die Synchronisation bis zu ihrem Ende geändert hat; bei `null` lädt die Oberfläche die ganze Liste. Das Fenster, das gespeichert hat, lädt nichts.

### Datenstruktur und Geschäftsablauf

#### Struktur der eingelesenen Seiten

- Die Listen (`/politbusiness`, `/sitzung`, `/stadtparlament/27428`, `/kommissionen`, `/fraktionen`) liefern ihre Daten über `data-entities="..."`.
- Bei `/fraktionen` kommt der Status «aktiv» aus den Feldern `datumVon` und `datumBis`:
  - `aktiv = true`, wenn `datumBis` leer ist oder in der Zukunft liegt und `datumVon` nicht in der Zukunft liegt.
  - `aktiv = false`, wenn `datumBis` in der Vergangenheit liegt.
  - Die Statusauswahl der Quelle (`Aktiv`/`Inaktiv`) folgt derselben Regel.
- Bei `/stadtparlament/27428` (Mitglieder) folgt `aktiv` aus mehreren Angaben der Quelle:
  - zuerst aus `_funktionAktiv` und `_funktionInaktiv`, falls gesetzt
  - sonst aus `_mandatPersonDatumVon` und `_mandatPersonDatumBis`, mit derselben Regel wie bei den Fraktionen
  - Der Wert steht in `pw_mitglieder.aktiv`.
- Bei Geschäften enthält `title` in der Regel einen HTML-Link auf `/_rte/information/{id}`.
- Dieser Link ist die technische Kennung (`extern_id`, `id` in der Datenbank).
- `_nummer` oder `number` ist die fachliche Geschäftsnummer und bleibt ein eigenes Feld.
- Die Detailseiten der Geschäfte liefern weitere Angaben zum Verfahren als `<dt>/<dd>`, die in `pw_geschaeft_ereignisse` abgelegt werden.

#### Kennung und Nummer

Ausgewertet wurde die Liste der Geschäfte, wie die Webseite sie am 2026-05-12 lieferte, mit 1230 Einträgen. Jede Kennung `/_rte/information/{id}` gehörte zu genau einer Geschäftsnummer, und jede Geschäftsnummer zu genau einer Kennung.

Die beiden bleiben trotzdem getrennte Felder (`id`/`extern_id` und `nummer`), damit die App eine Abweichung von dieser Regel behandeln kann.

#### Modellierung der Vorstoss-Phasen

- Nummerierte Geschäfte laufen in `pw_geschaefte`.
- Nicht nummerierte oder noch nicht eingereichte Vorstösse laufen in `pw_vorstoss_entwuerfe`.
- Bekommt ein Vorstoss später seine Nummer, wird er zuerst über `extern_id` zugeordnet, sonst über `titel_normalisiert` und `typ`.

#### Modellierung der Fraktionsarbeit

- Die eingelesenen Felder sind nur lesbar und kommen ausschliesslich aus der Synchronisation.
- Die Fraktion arbeitet in `pw_geschaeft_zustaendigkeiten` (mehrere Zuständige und ein Hauptzuständiger) und `pw_geschaeft_aktionen` (die Zeitleiste: Notizen, Beschlüsse, Voten, Änderungen der Zuweisung).
- Jede Aktion trägt Autor und Zeitstempel.
- Die Standardansicht zeigt den letzten gültigen Beschluss; das Detail zeigt alle Aktionen.
- Der Fraktionsstatus folgt aus zwei Zeitpunkten:
  - letzter gültiger Fraktionsbeschluss (`pw_geschaeft_aktionen.erstellt_am`)
  - letzte inhaltliche Änderung an der Quelle (`pw_geschaefte.quelle_aktualisiert_am`)
- Ist die Quelle neuer als der letzte Beschluss, gilt das Geschäft als `neu_zu_entscheiden` und hat `entscheidungsbedarf = true`.
- Wer im Fraktionssitzungsmodus was darf, steht unter «Fraktionssitzungsmodus» im Abschnitt «Datenstrukturen».

#### Rollenmodell in der Fraktion

- `kommissionsmitglied`:
  - Wird als eigene Rolle geführt (optional befristet), damit Kommissionsarbeit unabhängig von Geschäfts-Zuständigkeiten auswertbar bleibt.
- `fraktionspraesident`:
  - Primäre Leitungsrolle.
  - Darf befristete Präsidiums-Stellvertretungen setzen.
- `fraktionspraesident_stellvertretung`:
  - Zeitlich befristete Delegation durch Präsidium.
  - Während Gültigkeit können Präsident und Stellvertretung parallel handeln.
- `protokollfuehrer`:
  - Primäre Rolle für Beschlussprotokollierung im Fraktionssitzungsmodus.
  - Darf befristete Protokoll-Stellvertretung setzen.
- `protokollfuehrer_stellvertretung`:
  - Zeitlich befristete Delegation durch Protokollführung oder Präsidium.
  - Während Gültigkeit können Protokollführer und Stellvertretung parallel handeln.

#### Rechtlicher Rahmen (Überblick)

- Kanton Zürich, Gemeindegesetz (GG), insbesondere §§ 34–35.
- Stadt Winterthur, Organisationsverordnung Stadtparlament (OV Parl), insbesondere Art. 77 ff.

#### Geschäftsgang nach Vorstossart

- **Motion:** Einreichung, Überweisung oder Ablehnung, Bericht und Antrag, Erheblicherklärung oder Abschreibung.
- **Postulat:** Einreichung, Überweisung oder Ablehnung, Bericht, Kenntnisnahme, wenn nötig Nachbericht.
- **Interpellation und schriftliche Anfrage:** Einreichung, Beantwortung, Kenntnisnahme.
- Die Webseite zeigt die Abläufe teils in mehreren Schritten innerhalb eines Geschäfts; deshalb führt die App den Ablauf als Folge von Ereignissen und nicht als ein einzelnes Statusfeld.

#### Geschäftsarten je Kategorie

Die Geschäftsarten der Quelle gehören zu diesen Kategorien; eine unbekannte Art gilt als `default`:

- `motion`: `Motion`, `Dringliche Motion`, `Budget-Motion`
- `postulat`: `Postulat`, `Dringliches Postulat`, `Budget-Postulat`
- `interpellation`: `Interpellation`, `Dringliche Interpellation`, `Fragestunde`
- `schriftliche_anfrage`: `Schriftliche Anfrage`
- `bericht`: `Bericht`, `Jahresrechnung`, `Kreditabrechnung`
- `initiative`: `Volksinitiative`, `Einzelinitiative`, `Parlamentarische Initiative`
- `vorlage`: `Kreditantrag`, `Budget`, `Beschlussantrag`, `Parlamentseigene Vorlage`, `Verordnung / Rechtserlass`, `Vertrag / Vereinbarung`, `Rechtsmittel`, `Referendum`, `übrige Geschäfte`
- `wahlen`: `Wahlen`

Die Zuordnung steht an einer Stelle, in `parlwin/lib/Service/GeschaeftWorkflow.php`, und `parlwin/tests/Service/GeschaeftWorkflowTest.php` prüft sie mit den Kategorien, die in der Quelle vorkommen.


## Lizenz

MIT
