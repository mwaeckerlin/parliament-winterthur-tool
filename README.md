# Parlament Winterthur Tool

Nextcloud-Plugin für die Fraktionsarbeit im Winterthurer Parlament. Das Plugin synchronisiert täglich die öffentlichen Daten des [Parlaments Winterthur](https://parlament.winterthur.ch/) und stellt sie als strukturierte Arbeitsoberfläche mit fraktionsinternen Notizen, Zuständigkeiten und Beschlüssen zur Verfügung.


## Zweck

Stell dir Nextcloud vor wie eine eigene, **private Version von Google Drive oder Microsoft 365** – aber sie läuft auf einem Server, den die Fraktion selbst kontrolliert. Niemand sonst hat Einsicht in eure Dokumente, Kalender oder Chats. Du erreichst Nextcloud über deinen Webbrowser (Chrome, Firefox, Safari) und optional über Apps für Handy und Computer.

Was sich vergleichen lässt:

| Was du brauchst                | Google / Microsoft        | Nextcloud (was wir nutzen)         |
|--------------------------------|---------------------------|------------------------------------|
| Dateien ablegen & teilen       | Google Drive / OneDrive   | **Dateien**                        |
| Dokumente gemeinsam schreiben  | Google Docs / Word Online | **Nextcloud Office (Collabora)**   |
| Termine                        | Google Calendar / Outlook | **Kalender**                       |
| Adressbuch                     | Google Contacts           | **Kontakte**                       |
| Aufgabenlisten                 | Google Tasks / To Do      | **Aufgaben / Deck**                |
| Kanban-Board (Projektplanung)  | Trello / Planner          | **Deck**                           |
| Chat                           | Google Chat / Teams       | **Talk**                           |
| Video-Konferenz                | Meet / Teams              | **Talk**                           |
| Umfragen / Doodle              | Forms                     | **Forms / Polls**                  |

Und zusätzlich: **dieses Plugin** – das Parlament-Winterthur-Tool. Es bringt alle laufenden Geschäfte, Sitzungen, Traktanden, Kommissionen und Fraktionsmitglieder automatisch in eure Nextcloud. Niemand muss mehr selbst auf der Parlamentswebseite suchen.


## Funktionen

Das Plugin lädt einmal täglich automatisch alle öffentlichen Daten vom Winterthurer Parlament herunter und zeigt sie übersichtlich an. Zusätzlich kann die Fraktion eigene Notizen, Zuständigkeiten und Argumente dazu erfassen – privat, nur innerhalb der Fraktion sichtbar.

Eine vollständige, laufend nachgeführte Funktionsliste steht in [FEATURES.md](FEATURES.md); die dort durchnummerierten Funktionen sind über [TESTS.md](TESTS.md) mit allen Tests verknüpft.

### Funktionen im Überblick

- **Geschäfte-Liste** – alle politischen Geschäfte (Anträge, Motionen, Interpellationen …) mit Suche, Filter und aktuellem Stand.
- **Sitzungskalender** – kommende Parlaments- und Kommissionssitzungen samt Traktandenliste; automatisch in den Nextcloud-Kalender integriert.
- **Mitgliederverzeichnis** – alle Parlamentarier mit Partei, Fraktion und Kommissionszugehörigkeit; sortierbar nach Funktion (Standard), Fraktion, Partei oder Name und filterbar nach Funktion (Fraktions- bzw. Kommissionspräsident) sowie nach einzelner Kommission.
- **Kommissionsübersicht** – welche Kommission behandelt welches Geschäft, wer sitzt drin.
- **Fraktionsarbeit**
  - Geschäften können **Zuständige** aus der eigenen Fraktion zugewiesen werden (Hauptzuständige + Mitarbeitende).
  - Die Zuweisung für Kommissionsgeschäfte erfolgt **automatisch**: Wer in der zuständigen Kommission sitzt, wird vorgeschlagen.
  - Pro Geschäft können **Notizen, Argumente, Hintergrund­dokumente** abgelegt werden. Notizen lassen sich **formatieren** (Fett, Kursiv, Listen, Überschriften, Zitate, Code, Links) und werden intern als Markdown gespeichert.
- **Gemeinsames Aufgaben-Board (Deck)** – ein mit der Fraktion geteiltes Kanban-Board «Fraktion» wird automatisch eingerichtet (analog zum gemeinsamen Ordner und Kalender); ist die Deck-App nicht installiert, bleibt die Funktion inaktiv.
- **Sitzungen verknüpfen und Dokumente ablegen** – Sitzungen lassen sich miteinander verknüpfen (z.B. eine Kommissions- mit der vorbereitenden Fraktionssitzung), Dokumente pro Sitzung ablegen und einzelne Geschäfte mit einer Sitzung verknüpfen.
- **Automatische Geschäfts-Verknüpfung** – Sitzungstypen können «beratene» Kommissionen festlegen; die in diesen Kommissionen hängigen Geschäfte werden vor jeder Sitzung dieses Typs automatisch verknüpft.
- **Aufgaben aus der Sitzung ins Deck-Board** – aus einer Sitzung lassen sich Aufgaben direkt als Karte im Fraktions-Board «Fraktion» anlegen.
- **Vorstösse** – eigene und fremde politische Vorstösse (Motion, Postulat, Interpellation …) erfassen und verwalten: Herkunft (eigene/fremde), Status, Priorität, Zuständigkeit (Personen-Liste), Notizen und Inhalt im Formatierungs-Editor; bei fremden Vorstössen zusätzlich die eigene Beschluss-Haltung, die Herkunftsfraktion und deren Ansprechpartner. Jede Eingabe speichert sofort. Dokumente lassen sich anlegen, hochladen oder aus «Fraktion/40_Vorstösse» wählen; von dort werden sie auch automatisch übernommen. Ein Vorstoss lässt sich durch Verknüpfung mit einem Geschäft abschliessen (Ähnlichkeitssuche über den Titel; die Priorität wird übernommen).
- **Budget** – ein eigener Bereich für den städtischen Budgetprozess mit den Tabs Globalbudgets, Personalbestand, Investitionsrechnung, Steuerfuss, Anträge und Grafik, Filtern (Jahr, Kommission/Departement, Kostensteigerung) und einer ständig mitlaufenden Summenzeile. Eigene und fremde Anträge je Produktegruppe; globale Kürzungen werden anteilig zum Aufwand verteilt (Defizit standardmässig zur schwarzen Null); Personal-, Investitions- und Steuerfuss-Anträge; ein Anträge-PDF je Kommission und das Mitführen der Beschlüsse während der Sitzung. Die Budgetdaten stammen aus den städtischen Budgetbüchern.
- **Grafische Übersicht** – der letzte Budget-Tab zeigt das ganze Budget als geschachtelte Kreise, deren Fläche dem Betrag entspricht: Einnahmen und Ausgaben, darin die Departemente, darin die Produktegruppen, darin die Produkte. Ein Klick öffnet einen Kreis, der Weg im Kopf und Escape führen zurück. Beim Öffnen kommen die Kreise von weit her zusammen, schieben einander beiseite und ordnen sich; wer mit dem Zeiger darüberfährt, sieht Name, Betrag und Anteil jedes einzelnen, auch der kleinsten.
- **Fragestunde** – ein eigener Bereich für die Fragen der Fraktion. Gesammelt wird jederzeit, auch ohne angesetzte Fragestunde: Wem etwas auffällt, trägt es ein, und die Fraktion nutzt es später in irgendeiner Fragestunde. Sie bespricht die Fragen in ihrer Sitzung, passt sie an, teilt sie einer Fragestunde zu und einander zum Einreichen — meist der Person, die sie eingebracht hat, sonst jemand anderem, weil jedes Mitglied nur eine Frage einreicht. Die Seite führt die Frist (der Donnerstag vor der Fragestunde) und die Grenze von 1'000 Zeichen mit und meldet, wenn einem Mitglied mehr als eine Frage zugeteilt ist.
- **Priorität pro Geschäft und Vorstoss** – hoch, mittel oder tief (nicht gesetzt wird als «—» angezeigt und wirkt wie mittel); einstellbar in der Übersicht wie in der Detailansicht. In den Übersichten werden hohe Einträge dezent hervorgehoben und tiefe abgeschwächt, mit eigenem Filter.
- **Änderungsverlauf** – ein eigener Bereich zeigt den Versionsverlauf der App als aufklappbare, formatierte Liste.
- **Aktualisierung in Echtzeit** – wenn jemand in der Fraktion etwas ändert, sehen alle anderen es sofort, ohne die Seite neu zu laden.
- **Suche und Filter** über alle Geschäfte, Sitzungen, Mitglieder und Vorstösse.

### Fraktionsarbeit – aktueller Stand

Aktuell umgesetzt:

1. **Geschäftsübersicht**: Tabellarische Darstellung aller Geschäfte mit Filtermöglichkeiten nach Status, Typ, Datum, Zuständigkeit, Priorität und letztem gültigen Beschluss (Status/Typ/Zuständige/Beschluss/Priorität als Mehrfachauswahl). Geschäfte mit hoher Priorität sind dezent hervorgehoben, solche mit tiefer abgeschwächt.
2. **Sitzungsvorbereitung**: Für jede Sitzung werden die Traktanden angezeigt. Pro Traktandum können Bemerkungen und Notizen erfasst werden.
3. **Zuständigkeiten**: Jedem Geschäft können mehrere Personen zugewiesen werden, inkl. Hauptzuständigkeit; die Auswahllisten führen aktive Mitglieder zuerst, inaktive getrennt darunter.
4. **Fraktionsentscheide**: Pro Geschäft können strukturierte Beschlüsse als Einträge der Aktionszeitleiste erfasst werden.
5. **Kalenderintegration**: Alle Sitzungen werden im gemeinsamen Fraktions-Kalender (`Fraktion <Name>`) als Nextcloud-Kalendereinträge gespeichert. Dieser Kalender ist bewusst als allgemeiner Kalender der Fraktion ausgelegt und nimmt künftig auch weitere Sitzungstypen auf.
6. **Mitgliederverwaltung**: Automatische Synchronisation der Fraktionsmitglieder als Nextcloud-Gruppe mit E-Mail-Einladung. Verwaiste Nextcloud-Benutzer (in der Gruppe, aber nicht mehr im Parlament) werden in der Verwaltung durchgestrichen angezeigt und lassen sich einzeln deaktivieren.
7. **Fraktionssitzungsmodus**: Beschlüsse sind im Sitzungsmodus auf Protokollführung (inkl. aktiver Stellvertretung) beschränkt; Notizen bleiben offen.
8. **Rollenmodell**: Fraktionspräsidium, Protokollführung und Kommissionsmitgliedschaften inkl. zeitlich befristeter Stellvertretungen.
9. **Gemeinsam arbeiten**: Änderungen der anderen erscheinen sofort, ohne die Seite neu zu laden.
10. **Entscheidungsbedarf**: Filter auf Geschäfte mit offenem/neuem Fraktionsentscheid basierend auf `quelle_aktualisiert_am` gegenüber letztem gültigen Beschluss.
11. **Erledigte standardmässig ausgeblendet**: In der Geschäftsliste werden `erledigt`/`abgeschlossen` standardmässig nicht angezeigt (der Schalter «Erledigte anzeigen» blendet sie ein).
12. **Notizen mit Formatierung**: Notizen zu Geschäften und Sitzungen werden in einem Editor erfasst, der das Ergebnis gleich so zeigt, wie es aussieht (Fett, Kursiv, Listen, Überschriften, Zitate, Code, Links); gespeichert wird intern als Markdown.
13. **Sitzungen verknüpfen und dokumentieren**: Sitzungen lassen sich untereinander sowie mit einzelnen Geschäften verknüpfen; pro Sitzung können Dokumente abgelegt werden. Sitzungstypen mit «beratenen» Kommissionen verknüpfen deren hängige Geschäfte automatisch vor der Sitzung.
14. **Vorstösse**: Eigene und fremde Vorstösse mit Herkunft, Status, Priorität, Zuständigkeit (Personen-Liste), Notizen und Inhalt (Formatierungs-Editor wie bei den Notizen); bei fremden Vorstössen zusätzlich Beschluss-Haltung, Herkunftsfraktion und Ansprechpartner der fremden Fraktion. Dokumente anlegen/hochladen/auswählen; Filter nach Herkunft/Status, automatische Übernahme aus «Fraktion/40_Vorstösse». Abschluss durch Verknüpfung mit einem Geschäft (Titel-Ähnlichkeitssuche, Priorität wird übernommen). Löschen über die Karte, mit Rückfrage.
15. **Anlegen und Bearbeiten überall gleich**: Der Neu-Knopf öffnet dieselbe vollständige Maske wie das Bearbeiten — mit allen Feldern, nie ein reduziertes Formular. Beim Anlegen wird nichts vorab gespeichert: unten stehen «Speichern» und «Abbrechen»; «Speichern» ist gesperrt, solange die Pflichtangabe fehlt, und führt danach direkt in die Bearbeitung. Ein Klick neben die Maske bewirkt dabei nichts, damit keine Eingabe verloren geht — verworfen wird ausschliesslich über «Abbrechen». Beim Bearbeiten speichert jede Eingabe sofort (keine Knöpfe, «✕» schliesst nur). Notizen, Dokumente und der Verlauf beziehen sich auf einen bestehenden Eintrag und erscheinen deshalb erst nach dem Speichern; ein Hinweis in der Maske sagt das. Stehen Vorlagen zur Auswahl (Sitzungstypen), öffnet der Neu-Knopf ein Aktionsmenü mit je einem Eintrag pro Vorlage — der Nextcloud-Standard aus der App «Dateien».
16. **Keine Änderung ohne Spur**: Jede Änderung wird nachvollziehbar festgehalten — Notizen über ihren Versionsverlauf, alles Übrige (auch geänderte Angaben und Prioritäten, mit Vorher- und Nachher-Wert) als Eintrag in der Aktionszeitleiste.
17. **Änderungsverlauf**: Eigener Bereich mit dem Versionsverlauf als aufklappbare, formatierte Liste.

### Geplante Funktionen

- **Abstimmung zu Vorlagen und Vorstössen**: Jedes Fraktionsmitglied kann zu jeder Vorlage (Geschäft) und jedem Vorstoss angeben, ob es **dafür, dagegen oder neutral** ist, und die Haltung begründen. Vorteil: Themen, bei denen sich die Fraktion ohnehin einig ist, müssen in der Fraktionssitzung nicht mehr besprochen werden, und kurzfristige Entscheide sind auch ohne Sitzung möglich. So wird die Fraktion effektiver und kann sich in der Sitzung auf die wirklich strittigen Fragen konzentrieren.

### Im Alltag

1. **Vor der Fraktionssitzung**: Der Hauptzuständige liest seine Geschäfte und schreibt eine Empfehlung in die Notiz.
2. **In der Fraktionssitzung**: Die Empfehlungen werden direkt am Bildschirm diskutiert; Beschlüsse werden im Geschäft notiert.
3. **Vor der Parlamentssitzung**: Jeder ruft die eigenen Geschäfte auf und hat sofort die Argumente zur Hand.
4. **Nach der Sitzung**: Der neue Stand erscheint automatisch beim nächsten Synchronisationslauf (oder manuell per Knopfdruck).


## Bedienung

### Nextcloud-Funktionen für die Fraktionsarbeit

Neben unserem Plugin bringt Nextcloud viele weitere Werkzeuge mit. Die folgenden sind für die Fraktionsarbeit besonders nützlich. Sie alle erscheinen als Symbole oben in der Menüleiste, sobald sie aktiviert sind.

#### Empfohlene Plugins (Apps)

| App                              | Original (englisch)         | Wofür?                                                                                              |
|----------------------------------|-----------------------------|------------------------------------------------------------------------------------------------------|
| **Dateien**                      | Files                       | Zentrale Ablage. Wie ein Ordner auf dem Computer, nur online.                                       |
| **Nextcloud Office** (Collabora) | Nextcloud Office (Collabora Online) | Word-, Excel- und PowerPoint-Dokumente direkt im Browser gemeinsam bearbeiten – wie Google Docs. |
| **Kalender**                     | Calendar                    | Persönliche und gemeinsame Termine. Sitzungen aus dem Plugin erscheinen automatisch.                |
| **Kontakte**                     | Contacts                    | Gemeinsames Adressbuch der Fraktion.                                                                |
| **Talk**                         | Talk                        | Chat und Videokonferenz innerhalb der Fraktion (Ersatz für WhatsApp-Gruppe + Zoom).                 |
| **Deck**                         | Deck                        | Kanban-Board mit den Spalten «To-do», «In Arbeit» und «Erledigt». Ideal pro Geschäft oder Kampagne. |
| **Aufgaben**                     | Tasks                       | Einfache Aufgabenlisten, synchronisiert mit dem Handy.                                              |
| **Notizen**                      | Notes                       | Schnelle Notizen, ähnlich wie ein Notizbuch. Markdown-fähig.                                        |
| **Forms**                        | Forms                       | Umfragen innerhalb der Fraktion (z.B. «Wer kommt am 15. Mai?»).                                     |
| **Polls**                        | Polls                       | Terminfindung (Doodle-Ersatz).                                                                      |
| **Mail**                         | Mail                        | E-Mail-Konto in Nextcloud einbinden (optional).                                                     |
| **Lesezeichen**                  | Bookmarks                   | Gemeinsame Linksammlung (z. B. wichtige Artikel, Gesetzestexte).                                    |

#### Empfehlung pro Zweck

- **Termine koordinieren** → Kalender + Polls
- **Dokumente gemeinsam erarbeiten** → Dateien + Nextcloud Office
- **Schnelle Abstimmung im Vorfeld** → Talk (Chat) oder Forms
- **Wahlkampf / Kampagnen planen** → Deck (Kanban) + Dateien
- **Fraktionsbeschlüsse dokumentieren** → Notizen pro Geschäft (direkt im Plugin)


### Organisation der Fraktionsarbeit

Damit alle die Sachen finden und niemand aus Versehen etwas Privates teilt oder etwas Wichtiges überschreibt, hat sich die folgende Struktur bewährt.

#### Bereiche der Ablage

1. **Mein persönlicher Bereich** – nur ich sehe es.
2. **Fraktions-Bereich** – alle Fraktionsmitglieder sehen es.
3. **Öffentlicher Bereich** – ein Link kann nach aussen weitergegeben werden (Medien, andere Parteien). Nur bewusst nutzen.

In Nextcloud erkennt man am Symbol neben dem Datei- oder Ordnernamen, in welchem Bereich man sich befindet (Personen-Symbol = geteilt, Welt-Symbol = öffentlicher Link).

#### Dokumente in der Fraktion teilen

Der Administrator legt diese Ordnerstruktur einmal an und gibt sie an alle Fraktionsmitglieder frei:

```text
Fraktion/
├── 00_Allgemein/           ← Statuten, Geschäftsordnung, Mitgliederliste
├── 10_Sitzungen/
│   ├── 2026/
│   │   ├── 2026-05-20 Fraktion/  ← Protokoll, Traktanden, Vorlagen
│   │   └── 2026-06-17 Fraktion/
├── 20_Geschäfte/           ← Hintergrund pro Geschäft (alternativ direkt im Plugin)
├── 30_Kommissionen/
│   ├── Aufsichtskommission/
│   ├── Sachkommission Bildung Sport Kultur/
│   └── …
├── 40_Vorstösse/
│   ├── 10_Eigene/          ← Vorstösse werden von hier automatisch übernommen
│   └── 20_Fremde/
├── 50_Wahlkampf/
├── 60_Medien/              ← Pressemitteilungen, Communiqués
└── 90_Archiv/
```

Bestehende Ablagen werden beim Start automatisch auf diese Struktur gebracht: `40_Wahlkampf` wird zu `50_Wahlkampf`, `50_Medien` zu `60_Medien` (Inhalte bleiben erhalten). Erst danach entsteht `40_Vorstösse`, damit die Nummern nicht kollidieren. Die Nummerierung läuft lückenlos in 10er-Schritten.

##### Berechtigungen

| Ordner                | Lesen          | Schreiben                              |
|-----------------------|----------------|----------------------------------------|
| `00_Allgemein/`       | Alle           | Fraktionspräsidium                     |
| `10_Sitzungen/`       | Alle           | Alle (Protokoll: Aktuar)               |
| `20_Geschäfte/`       | Alle           | Hauptzuständige und Mitarbeitende      |
| `30_Kommissionen/X/`  | Alle           | Mitglieder der Kommission X            |
| `40_Vorstösse/`       | Alle           | Zuständige des Vorstosses              |
| `50_Wahlkampf/`       | Alle           | Wahlkampfleitung                       |
| `60_Medien/`          | Alle           | Mediensprecher                         |
| `90_Archiv/`          | Alle (nur lesen) | Fraktionspräsidium                  |

> Tipp: Dokumente **nicht per E-Mail-Anhang** verschicken, sondern in Nextcloud
> ablegen und den Link teilen. So gibt es immer nur eine aktuelle Version.

#### Gemeinsamen Kalender teilen

Empfohlene Kalender:

- **Fraktion `<Name>`** (vom Plugin verwaltet, mit der Fraktion geteilt) – zentraler Fraktions-Kalender. Enthält automatisch alle vom Plugin synchronisierten Sitzungen (Parlament, Kommissionen, künftige weitere Sitzungstypen). **Vom Plugin erzeugte Einträge bitte nicht von Hand bearbeiten** – sie werden bei der nächsten Synchronisation überschrieben. Eigene Fraktionstermine (Fraktionssitzung, Fraktionsausflug, …) dürfen ergänzt werden.
- **Kommission X** (geteilt mit Kommissionsmitgliedern) – nur, falls die Kommissionsarbeit eng koordiniert wird.
- **Persönlich** (privat) – alles, was nur dich betrifft.

So fügst du den Fraktionskalender hinzu:

1. Symbol «Kalender» oben anklicken.
2. Links unten «+ Neuer Kalender» oder «Kalender abonnieren» wählen (Präsidium gibt den Link weiter).
3. Auf dem Handy: in der Nextcloud-Smartphone-App den Kalender auswählen – er erscheint dann in der Standard-Kalender-App.

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

Als Faustregel gilt: Sobald **eine zweite Person** das Dokument irgendwann braucht oder sehen sollte → in den Fraktionsordner. Sobald es **nur dich** betrifft oder es **rohes, unfertiges Material** ist → persönlicher Bereich. Bei Zweifel: zuerst persönlich, später in den geteilten Ordner verschieben.

#### Goldene Regeln

1. **Eine Datei, ein Ort.** Niemals Kopien per Mail; immer der Link aus Nextcloud.
2. **Sprechende Dateinamen** mit Datum vorne: `2026-05-20_Antrag_Velobruecke.odt`.
3. **Nichts löschen.** Veraltetes nach `90_Archiv/` verschieben. Nextcloud führt zwar eine Versionsgeschichte, aber Ordnung schadet nie.
4. **Persönliche Daten nicht über öffentliche Links teilen.** Lieber über eine Freigabe, die eine Anmeldung verlangt.
5. **Vor dem Schreiben kurz schauen, wer das Dokument gerade geöffnet hat.** Nextcloud Office zeigt das oben rechts an (mehrere Personen können gleichzeitig tippen – wie in Google Docs).
6. **Talk statt WhatsApp** für fraktionsinterne Themen – bleibt unter euch.

### Fragestunde

Das Parlament hält zweimal im Jahr eine Fragestunde. Der Bereich **«Fragestunde»** (in der Navigation direkt hinter «Budget») führt den Ablauf der Fraktion:

1. **Fragen eintragen, jederzeit** – jedes Mitglied trägt seine Fragen selbst ein («+ Neue Frage»), ohne dass eine Fragestunde angesetzt sein muss: wer sie eingebracht hat (vorbelegt), die Frage, ein Kommentar für die Fraktionssitzung und Notizen. Neben dem Feld steht die Zeichenzahl; über **1'000 Zeichen** nimmt der Parlamentsdienst die Frage nicht entgegen, deshalb lässt sie sich dann auch nicht speichern.
2. **Fragestunde anlegen, sobald sie feststeht** – «+ Neue Fragestunde» mit dem Datum. Titel und Frist ergeben sich daraus: Die Fragen müssen bis spätestens am **Donnerstag vor der Fragestunde** schriftlich beim Parlamentsdienst eingereicht werden (Art. 103 Abs. 2 der Organisationsverordnung des Stadtparlaments).
3. **In der Fraktionssitzung besprechen** – jede Frage lässt sich anpassen, ihr Status wandert von «Neu» über «Besprochen» zu «Eingereicht» (oder «Zurückgezogen»).
4. **Einer Fragestunde und einem Mitglied zuteilen** – im Feld «Fragestunde» entscheidet die Fraktion, in welcher Fragestunde die Frage gestellt wird, im Feld «Einzureichen von», wer sie einreicht. Meist ist das die Person, die sie eingebracht hat; weil **jedes Mitglied nur eine Frage einreicht**, manchmal jemand anderes. Ist einem Mitglied mehr als eine Frage zugeteilt, meldet es die Seite mit Namen.

Jede Änderung speichert sofort, und alle in der Fraktion sehen sie in Echtzeit. Sobald das Parlament die Fragestunde als Geschäft veröffentlicht hat, steht der Verweis darauf auf der Karte.

### Budget-Fassungen

Im Budgetprozess entstehen mehrere Fassungen desselben Budgets. Diese Begriffe werden durchgehend so verwendet:

- **Stadtratsbudget** – das Budget **1:1 aus der Weisung des Stadtrats**, inklusive Novemberbrief, sofern ein solcher vorliegt. Es ist der unveränderte Ausgangsstand.
- **Fraktionsbudget** – das Budget **mit allen erfassten Anträgen, welche die Fraktion einreicht oder unterstützt**. Es zeigt, wie das Budget nach dem Willen der eigenen Fraktion aussähe.
- **Kommissionsbudget** – das Budget **mit allen Mehrheitsbeschlüssen aus den Kommissionen**. (In diesem Tool bisher nicht eigens dargestellt — hier nur zur Einordnung genannt.)
- **Parlamentsbudget** – das vom **Parlament beschlossene, finale und gültige** Budget.

### Pauschalanträge und Steuerfuss

Im Budget-Bereich fasst der Kasten **«Pauschalanträge»** (Tab Globalbudgets) den automatischen Ausgleich und die frei angelegten Pauschalanträge zusammen:

- **Defizit automatisch verteilen:** Ist der Schalter «Defizit automatisch als Pauschalkürzung verteilen» eingeschaltet, wird ein Defizit anteilig zum Aufwand auf die Produktegruppen verteilt, bis das gewählte **Ziel** erreicht ist (Standard: schwarze Null; wahlweise ein Zielbetrag). Mit «Pauschalantrag einreichen» entscheidet man, ob die so erzeugten Einzelanträge tatsächlich eingereicht werden — nur dann stehen sie im Antrags-PDF.
- **Weitere Pauschalanträge:** Über «+ Pauschalantrag» lassen sich beliebig viele, voneinander unabhängige Pauschalanträge anlegen (Einsparung in Franken oder Prozent, Antragsteller-Fraktion, Ausnahmen, Begründung). Ihre Kürzungen summieren sich; der automatische Ausgleich rechnet zuletzt, auf dem bereits gekürzten Stand.

Über das Budget hinaus wird nichts gekürzt. In keinem Feld — Produktegruppe, Produkt, Kostenzeile, Investitionsprojekt, Personalbestand — lässt sich mehr sparen, als dort budgetiert ist; die Untergrenze ist null. Die Grenze gilt für die Summe aller Anträge auf dasselbe Feld: Wer sie überschreitet, bekommt eine Meldung mit dem Budget, dem bereits Beantragten und dem verbleibenden Rest. Eine Pauschalkürzung, die ein Feld unter null drücken würde, kürzt es auf null und verteilt den Rest auf die übrigen Felder.

Auf dem Tab Steuerfuss entscheidet sich, was mit einem Überschuss geschieht. Bleibt am Ende ein **Überschuss**, senkt die App den Steuerfuss automatisch in ganzen Prozent-Schritten, bis der Überschuss aufgebraucht ist — der Ertrag geht damit auf (nahe) null. Ein Steuerprozent ist so viel wert wie «Steuerertrag geteilt durch Steuerfuss». Die Senkung ist ein **echter Antrag**: Sie reduziert die Einnahmen in der Summenzeile und steht am Ende der Antragsliste und des Antrags-PDF. Wer den Steuerfuss selbst festlegen will, schaltet die Automatik auf dem Steuerfuss-Tab ab und stellt einen manuellen Steuerfuss-Antrag; dieser verdrängt die Automatik.

Gewünschter Ertrag und Steuerfuss-Senkung kommen zusammen vor, und beide Automatiken dürfen gleichzeitig laufen. Der automatische Defizit-Ausgleich verteilt nur **Defizite** als Kürzungen und rührt einen Überschuss nie an. Ein Überschuss — auch ein als Zielbetrag gewünschter Ertrag — wird stattdessen über die **Steuerfuss-Senkung** ausgeglichen: Man gibt den Zielbetrag ein und verteilt den Überschuss, dieser Betrag fliesst in die Steuerfuss-Senkung, und der Gesamtertrag ist wieder null. So ist eindeutig, was mit einem Überschuss geschieht, wenn beide Schalter aktiv sind.


## Administration

### Inbetriebnahme auf einer Ubuntu-VM (Beispiel)

Diese Anleitung zeigt, wie das Tool auf einer Ubuntu-VM mit automatischem HTTPS (Let's Encrypt über Traefik) produktiv betrieben wird.

#### Einmalige Einrichtung

##### Docker installieren

Auf der bereitgestellten Ubuntu-VM kommen Docker Engine und Compose aus der offiziellen Quelle:

```bash
# Offizielle Docker-Quelle einrichten (Ubuntu 22.04/24.04)
sudo apt-get update
sudo apt-get install -y ca-certificates curl pwgen git
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
     -o /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
     https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
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

`HOST` ist der öffentliche Domainname (z. B. `fraktion.example.com`). `LETSENCRYPT_EMAIL` erhält die Let's-Encrypt-Zertifikatsbenachrichtigungen. Die beiden Passwörter erzeugt `pwgen` als sichere Zufallszeichenketten.

#### Starten – und bei jeder Aktualisierung

```bash
cd parliament-winterthur-tool/example
git pull
docker compose up -d --build --remove-orphans --force-recreate --pull always
```

Das genügt für den ersten Start und für alle künftigen Aktualisierungen: `git pull` holt die aktuellste Version, `--pull always` lädt die neuen Docker-Images automatisch nach, und `--build` erzeugt den vorgelagerten Proxy neu.

Der vorgelagerte Proxy wird gebaut, weil seine Weiterleitungen (`example/traefik/dynamic.yml`) werden per `COPY` in sein Abbild aufgenommen, statt sie als Verzeichnis des Wirts in den Container einzublenden. Kein Container bekommt so ein Verzeichnis des Wirts zu sehen, und der Docker-Socket bleibt ebenfalls draussen — deshalb ist der Docker-Provider von Traefik abgeschaltet und die Weiterleitungen kommen aus dieser Datei. Wer sie ändert, baut den Proxy neu:

```bash
docker compose up -d --build traefik
```

#### Konfiguration in Nextcloud

1. Nextcloud unter `https://your-site.com` aufrufen und mit dem Administratorkonto anmelden (Passwort = `NEXTCLOUD_ADMIN_PASSWORD`).
2. Unter **Administration → Parlament Winterthur** die Fraktion, die Nextcloud-Gruppe und die E-Mail-Einstellungen konfigurieren.
3. **Jetzt synchronisieren** klicken, um die Parlamentsdaten erstmals zu laden.

#### Verwaiste Benutzer

In der Verwaltung unter **«Fraktionsmitglieder ↔ Nextcloud-Benutzer»** werden alle Benutzer der konfigurierten Nextcloud-Gruppe angezeigt. Wer in der Gruppe ist, aber keinen aktuellen Parlamentseintrag hat, erscheint **durchgestrichen** (Name und E-Mail). Benutzername und Gruppen werden normal angezeigt. Mit «Ausgewählte abgleichen» werden nur die angewählten verwaisten Benutzer aus der Gruppe entfernt und deaktiviert — die anderen bleiben unberührt.

Die automatische Synchronisation entfernt niemanden aus der Gruppe. Nur über «Ausgewählte abgleichen» mit ausdrücklicher Auswahl durch die Verwaltung werden Benutzer entfernt oder deaktiviert. Damit jemand als verwaist erscheint, muss er von Hand in die Nextcloud-Gruppe aufgenommen worden sein (Beispiel: ein ehemaliger Fraktionsmitarbeiter in der Gruppe — er erscheint als verwaister Eintrag, solange er kein aktives Parlamentsmandat hat).


### Voraussetzungen

- Nextcloud ≥ 25
- PHP ≥ 8.0
- Composer
- Node.js ≥ 16 und npm

### Installation im Nextcloud-Apps-Verzeichnis

```bash
cd /path/to/nextcloud/apps
cp -r parlwin/ .
cd parlwin
composer install --no-dev
npm ci
npm run build
```

In der Nextcloud-Administrationsoberfläche unter **Apps** das Plugin **Parlament Winterthur Tool** aktivieren.

### Konfiguration

Nach der Aktivierung unter **Einstellungen → Parlament Winterthur** die gewünschte Fraktion und Nextcloud-Gruppe konfigurieren.

In den Einstellungen des Plugins lässt sich Folgendes einstellen:

- **Fraktion**: Für welche Fraktion ist das Tool konfiguriert? (Pflichtauswahl aus den synchronisierten aktiven Fraktionen)
- **Nextcloud-Gruppe**: Automatisches Erstellen und Synchronisieren einer Nextcloud-Gruppe für die Fraktionsmitglieder (Einladung per E-Mail)
- **Automatische Synchronisation**: Zeitplan, wann die Daten geholt werden
- **Fraktionssitzung**: Modus aktiv/inaktiv
- **Fraktionspräsident**: primäre Präsidiumsrolle
- **Protokollführer**: primäre Rolle für Beschlussprotokollierung
- **Stellvertretungen**: befristete Delegation mit `gueltig_von` und `gueltig_bis`
- **Verbindung in Echtzeit (WebSocket)**: immer aktiv, ohne eigene Adresse in der Konfiguration (angemeldet wird über die laufende Nextcloud-Sitzung)
- **Fraktionsmitglieder ↔ Nextcloud-Benutzer**: Abgleich der Parlamentsmitglieder mit den lokalen Nextcloud-Benutzern. Alle Mitglieder der konfigurierten Nextcloud-Gruppe werden angezeigt – auch die, die in der Gruppe sind, aber keinen Parlamentseintrag haben (verwaiste Benutzer, durchgestrichen dargestellt). Abgeglichen wird gezielt: nur die markierten Benutzer werden deaktiviert oder entfernt.

Wichtige Regeln der Verwaltungsseite:
- **Nextcloud-Gruppe** kann als bestehende Gruppe gewählt oder als neuer Gruppenname eingetragen werden; die Seite zeigt sichtbar an, ob der Name bereits existiert.
- **Fraktionsraum** (gemeinsamer Ordner und Kalender) wird automatisch über das Administratorkonto bereitgestellt und mit der Fraktionsgruppe geteilt – beim Öffnen der App und bei jeder Änderung der Fraktionsgruppe. Es gibt dafür weder eine «Kalender-Benutzer»-Einstellung noch einen manuellen Knopf. Hat ein Mitglied bereits einen eigenen «Fraktion»-Ordner mit der Gruppe geteilt, wird dieser übernommen: der bisherige Ordner bleibt beim Eigentümer als «Fraktion.bak» erhalten, sein Inhalt wandert in den offiziellen Ordner.
- **Fraktionsmitglieder zuordnen:** Nach der Fraktionswahl erscheinen alle aktiven Mitglieder:
  - Vorgeschlagener Benutzername: `vorname-nachname` (klein, normalisiert)
  - Der Benutzername lässt sich ändern und wird in `pw_mitglieder.nextcloud_uid` gespeichert
  - Die Prüfung vor Ort zeigt, in welchen Gruppen der Benutzer bereits ist
  - Sammelaktion **Ausgewählte anlegen**: legt fehlende Benutzer an und nimmt sie in die gewählte Fraktionsgruppe auf
  - **Verwaiste Benutzer**: Wer in der Gruppe ist, aber keinen aktuellen Parlamentseintrag hat, wird durchgestrichen angezeigt. Mit «Ausgewählte abgleichen» werden nur die markierten verwaisten Benutzer aus der Gruppe entfernt und deaktiviert.
- Die Konfigurationsseite passt sich der Fensterbreite an und folgt dem Aufbau der Nextcloud-Einstellungen.
- Die Verwaltungsseite verwendet den Standardaufbau der Nextcloud-Einstellungen (`section` und die üblichen Formularfelder) ohne eigene Klassen für die Gestaltung der Eingabefelder.

### Lokale Docker-Umgebung (mwaeckerlin/nextcloud und Plugin)

Im Wurzelverzeichnis des Projekts liegt eine eigenständige `docker-compose.yml` ohne externe `extends`-Verweise.

Aufteilung der Netze im Compose (wie im Muster des übergeordneten Projekts, je Verbindung ein Netz):
- `nxinx-php`: `nextcloud-nginx` ↔ `nextcloud-php-fpm`
- `php-db`: `nextcloud-php-fpm` ↔ `nextcloud-db`
- `php-smtp`: `nextcloud-php-fpm` ↔ `smtp-relay`
- `nginx-collabora`: `nextcloud-nginx` ↔ `collabora`
- `php-collabora`: `nextcloud-php-fpm` ↔ `collabora`
- `php-realtime`: `nextcloud-php-fpm` ↔ `parlwin-realtime`
- `nginx-realtime`: `nextcloud-nginx` ↔ `parlwin-realtime`

Alle Netze sind wie in der Vorlage mit `driver_opts.encrypted=1` definiert.

Pflicht-Umgebungsvariablen (z.B. in `.env` im Wurzelverzeichnis des Projekts):
- `NEXTCLOUD_DB_PASSWORD`
- `NEXTCLOUD_ADMIN_PASSWORD`

Variablen des Dienstes `parlwin-realtime` (werden automatisch vorbelegt):
- `PARLWIN_REALTIME_WS_URL` (Standard: leer → automatisch aus der aktuellen Herkunft (Origin): `ws(s)://<host>[<WEBROOT>]/ws/parlwin/`; nur setzen, falls Nextcloud unter einer abweichenden öffentlichen Adresse erreichbar ist)
- `PARLWIN_REALTIME_PUBLISH_URL` (Standard: `http://parlwin-realtime:3001/publish`)
- `PARLWIN_REALTIME_SECRET` (optional, gemeinsames Geheimnis für `/publish`)
- `PARLWIN_REALTIME_AUTH_REQUIRED` (Standard: `1`, die Anmeldung an der WebSocket-Verbindung ist verlangt)
- `PARLWIN_NEXTCLOUD_BASE_URL` (Standard: `http://nextcloud-nginx:8080`, für die Prüfung dieser Anmeldung)
- `PARLWIN_NEXTCLOUD_AUTH_URL` (optional, ersetzt die Adresse für die Anmeldung vollständig)

Variablen für die Geschwindigkeit der Synchronisation:
- `PARLWIN_SYNC_SECTION_PARALLEL` (Standard: `6`) Wie viele Hauptlisten gleichzeitig vorgeladen werden (`geschäfte`, `sitzungen`, `mitglieder`, `kommissionen`, `fraktionen`).
- `PARLWIN_SYNC_GESCHAEFTE_PARALLEL` (Standard: `10`) Anzahl gleichzeitiger Abfragen auf die Detailseiten der Geschäfte (`/_rte/information/{id}`).

Variablen für den Speicher:
- `PARLWIN_PHP_MEMORY_LIMIT` (Standard: `1024M`) Die Speichergrenze von PHP im Container, gültig für die Oberfläche, die Kommandozeile (`occ`) und den Hintergrundauftrag. Das Basis-Abbild liefert 512 MB; ein Budgetbuch braucht beim Einlesen mehr (gemessen am Buch 2027: 574,7 MB), und in der laufenden Instanz brach der automatische Import genau daran ab. Der Bootstrap schreibt den Wert beim Start in ein eigenes ini-Verzeichnis, das `PHP_INI_SCAN_DIR` zusätzlich zum Standard nennt.
- `PARLWIN_BUDGET_MEMORY_LIMIT` (Standard: `1024M`) Die Speichergrenze, die während des Lesens eines Budgetbuchs gilt; danach gilt wieder die der Installation. Sie greift dort, wo die Installation weniger gibt als das Buch braucht, etwa ausserhalb des mitgelieferten Containers. Ein Buch belegt beim Lesen mehrere hundert Megabyte — mehr, als Nextcloud einer Anfrage standardmässig zugesteht. Wächst das Buch weiter, gehört der Wert hier herauf; eine bereits höhere oder aufgehobene Grenze der Installation bleibt unangetastet.

Schnellstart:

```bash
cd /home/marc/git/mwaeckerlin/parliament-winterthur-tool
cp .env.sample .env
```

Starten:

```bash
cd /home/marc/git/mwaeckerlin/parliament-winterthur-tool
npm start
```

Skript-Konvention:
- `npm run build`: `docker compose build`
- `npm start`: `docker compose up -d --build --force-recreate --remove-orphans`
- `npm run start:dev`: derselbe Compose-Start unter zweitem Namen
- `npm stop`: hält die Container an, ohne die Volumes zu löschen

`npm start` macht absichtlich nur den normalen Compose-Start und sonst nichts.

Hinweis:
- Der NGINX-`fastcgi_read_timeout` im Projekt-Image ist auf `36000s` gesetzt, damit manuelle Vollsynchronisationen nicht nach 60s mit HTTP 504 abbrechen.
- Nach Änderungen am `Dockerfile` immer mit Neubau starten (`npm start` oder `docker compose up -d --build`).
- Die App wird beim Compose-Start automatisch aktiviert (`parlwin-app-init`).
- Es gibt absichtlich **kein** dauerhaftes `custom_apps`-Volume; damit kommt bei jedem Neubau die aktuelle Version der App aus dem Abbild (keine veraltete Oberfläche aus alten Volumes).

Stoppen:

```bash
cd /home/marc/git/mwaeckerlin/parliament-winterthur-tool
npm stop
```

Standard-HTTP-Port lokal: `29824` (anpassbar über `NEXTCLOUD_HTTP_PORT`). Der WebSocket-Server (`parlwin-realtime`) wird **nicht** nach aussen freigegeben; der Browser erreicht ihn am gleichen Port wie Nextcloud über `ws://localhost:29824/ws/parlwin/`.

Plugin von Hand aktivieren (nur falls `parlwin-app-init` abgeschaltet wurde):

```bash
docker compose exec nextcloud-php-fpm \
  php occ app:enable parlwin
```

Das Compose nutzt getrennte Netze je Verbindung (analog zum `nextcloud`-Projekt): `nxinx-php`, `php-db`, `php-smtp`, `nginx-collabora`, `php-collabora`, `php-realtime`, `nginx-realtime`. Die Subnetzvergabe erfolgt vollständig durch Docker Compose (keine statischen `ipam`-Subnetze im Projekt).

### WebSocket-Dienst (`parlwin-realtime`)

Die App folgt der **Regel für WebSocket-Apps** von `mwaeckerlin/nextcloud:nginx` (siehe README dort, Abschnitt «WebSocket Apps»):

- Der Dienst im Compose heisst **`parlwin-realtime`** und hört intern auf Port **`3001`**.
- Kein Port muss nach aussen freigegeben werden — der Browser erreicht den WebSocket-Server **am gleichen Rechner und Port wie Nextcloud selbst** über den Pfad **`/ws/parlwin/`**. `nextcloud-nginx` schaltet die Verbindung auf WebSocket um und reicht sie unverändert an `http://parlwin-realtime:3001/` weiter.
- Damit entfallen die CSP-Probleme über zwei verschiedene Ports: die Verbindung des Browsers geht an dieselbe Herkunft, und die vorgegebene CSP von Nextcloud (`connect-src 'self'`) deckt sie ab, ohne dass etwas überschrieben werden muss.
- Aufrufe von Dienst zu Dienst aus PHP (etwa das Veröffentlichen eines Ereignisses) verwenden weiterhin den internen Hostnamen direkt (`http://parlwin-realtime:3001/publish`), nginx wird hier umgangen.

Wer Parlwin in einer bestehenden Nextcloud-Installation einsetzt, muss **nginx nicht anpassen** — es reicht, den Dienst `parlwin-realtime` in die eigene Compose-Datei aufzunehmen und ans gleiche Netz wie `nextcloud-nginx` zu hängen.

Die Dienste `nextcloud-nginx` und `nextcloud-php-fpm` werden aus `Dockerfile.nginx` bzw. `Dockerfile.php-fpm` gebaut und bauen auf:
- `mwaeckerlin/nodejs-build` (Bau-Stufe für die Oberfläche in `Dockerfile.php-fpm` und `Dockerfile.realtime`)
  - Die gebauten Dateien entstehen unter einem eigenen Benutzer (`BUILD_USER`).
- `mwaeckerlin/nodejs` (Grundlage für den Betrieb von `parlwin-realtime`, mit eigenem Benutzer `RUN_USER`)
- `mwaeckerlin/nextcloud:nginx`
- `mwaeckerlin/nextcloud:php-fpm`
- `mariadb` (`latest`)
- `collabora/code`
- `parlwin-realtime` (WebSocket-Verteiler auf Node, gebaut aus `Dockerfile.realtime`)

### Synchronisation

Von Hand startet der Administrator die Synchronisation auf der Kommandozeile:

```bash
php /path/to/nextcloud/occ parlwin:sync
```

Laufende Synchronisation abbrechen:

```bash
php /path/to/nextcloud/occ parlwin:sync:cancel
```

Ein Budgetjahr sauber neu aus dem Budgetbuch einlesen (etwa nach einer fehlerhaften früheren Fassung in der Datenbank) — ersetzt Produktegruppen, Investitionen und Kennzahlen, die eigenen Anträge bleiben erhalten:

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

Die Typen, die beim Anlegen eines eigenen Geschäfts zur Auswahl stehen, pflegt der Administrator im Bereich «Typen für eigene Geschäfte» (App-Einstellung `eigene_typen`, JSON-Liste von Bezeichnungen). Ohne Eintrag bleibt es beim Typ «Eigenes Geschäft». Der Status eines eigenen Geschäfts wird aus den Werten vorgeschlagen, die in der Datenbank bereits vorkommen (`GET /apps/parlwin/geschaefte/statuswerte`), bleibt aber frei überschreibbar.

#### Hintergrundauftrag

Das Plugin registriert einen Hintergrundauftrag, der die Daten automatisch synchronisiert. Den Zeitplan stellt die Verwaltung ein: beliebige Einträge mit Wochentagen (Mo–So) und Uhrzeit (App-Einstellung `sync_zeitplan`, JSON `[{"tage":[1,3],"zeit":"06:30"}]`, 1 = Montag … 7 = Sonntag, Europe/Zurich); verpasste Zeitpunkte werden nachgeholt. Ohne konfigurierten Zeitplan gilt der Standard: **zwei Läufe an allen Wochentagen, um 10:00 und um 18:00 Uhr**; dieser Standard erscheint in der Verwaltung vorbelegt und lässt sich dort bearbeiten.

Der mitgelieferte Container bringt den nötigen Takt selbst mit: `parlwin-watcher.php` setzt `backgroundjobs_mode=cron` und ruft den Cron von Nextcloud regelmässig auf (Abstand über `PARLWIN_CRON_INTERVAL`, Standard 300 s) – ein externer Cron-Daemon ist nicht nötig.

Der Hintergrundauftrag nutzt denselben Befehl wie die Verwaltung (`--source=background-job`). Damit erscheint ein automatisch gestarteter Lauf ebenfalls im Fortschritt der Verwaltung (`/sync/status`), mit Quelle, Stand und voraussichtlicher Restzeit.


## Entwicklung

```bash
cd parlwin
composer install
npm install
npm run dev   # baut die Oberfläche bei jeder Änderung neu
```

### Tests ausführen

Ein vollständiges Verzeichnis aller Tests, nach Testart gruppiert und je Test mit der abgedeckten Funktionsnummer aus [FEATURES.md](FEATURES.md), steht in [TESTS.md](TESTS.md).

Alle Tests (Klassen und Dienste, Abfragen gegen die echte Webseite, e2e) im Wurzelverzeichnis des Projekts:

```bash
cd /home/marc/git/mwaeckerlin/parliament-winterthur-tool
npm run test
```

Einzeln:

```bash
cd /home/marc/git/mwaeckerlin/parliament-winterthur-tool/parlwin
composer test
```

Test des Parsers gegen die echten Adressen (ohne Datenbank):

```bash
cd parlwin
phpunit --bootstrap tests/bootstrap.php --group live tests/Service/ScraperLiveEndpointTest.php
```

Hinweis: Dieser Test ruft `parlament.winterthur.ch` wirklich auf und prüft nur, wie das HTML gelesen wird (`data-entities`), nicht den Abgleich mit der Datenbank.

Strenger Testlauf:
- `npm run test` ist absichtlich **streng** eingestellt.
- `skip`, `warning`, `deprecation`, `notice`, `risky` und `incomplete` gelten als **Fehler**.

### Automatischer E2E-Test (Compose-basiert)

Der E2E-Test nutzt dieselbe `docker-compose.yml` wie der lokale Betrieb:

```bash
cd /home/marc/git/mwaeckerlin/parliament-winterthur-tool
./tests/e2e/run-compose-e2e.sh
```

Der Test führt bewusst einen vollständigen End-to-End-Ablauf aus:
- erzeugt bei jedem Lauf eine **neue leere Datenbank** (`docker compose down -v` und `up`)
- aktiviert die App und legt eigene Benutzer für den Test an
- synchronisiert echte Parlamentsdaten über dieselbe Schnittstelle wie der Knopf `Jetzt synchronisieren` in der Oberfläche (`POST /apps/parlwin/sync`)
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

Der Abgleich gegen die echte Webseite lässt sich für den Test begrenzen (nur für Tests, im Betrieb gilt das nicht):
- `PARLWIN_SYNC_LIMIT_GESCHAEFTE` (im Skript bewusst **ohne** Grenze: die Quelle liefert ihre Liste unsortiert, und jede Grenze schneidet irgendwann das Budget-Geschäft weg, aus dem der Budget-Import sein Buch holt)
- `PARLWIN_SYNC_LIMIT_SITZUNGEN` (Standard: `60`)
- `PARLWIN_SYNC_LIMIT_MITGLIEDER` (Standard: `80`)
- `PARLWIN_SYNC_LIMIT_KOMMISSIONEN` (Standard: `30`)
- `PARLWIN_SYNC_LIMIT_FRAKTIONEN` (Standard: `20`)
- oder für alles zusammen: `PARLWIN_SYNC_LIMIT_ALL`

Beispiel:

```bash
PARLWIN_SYNC_LIMIT_GESCHAEFTE=80 \
PARLWIN_SYNC_LIMIT_SITZUNGEN=30 \
./tests/e2e/run-compose-e2e.sh
```

Kompatibilitätshinweis (Nextcloud 33):
- `OCP\AppFramework\Db\Entity` enthält kein eingebautes `jsonSerialize()` mehr.
- Die App-Entities implementieren deshalb `jsonSerialize()` explizit, damit REST-Endpunkte (`/geschaefte`, `/sitzungen`, `/mitglieder`, `/kommissionen`, `/fraktionen`) stabil JSON liefern.

Wie ein Vorstoss seinen Weg nimmt, im Einzelnen:
- `parlwin/docs/vorstoss-modell.md`

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
- gerüstet für lange Läufe: keine Zeitgrenze für das Skript (`set_time_limit(0)`) im Arbeitsprozess
- Lebt der Arbeitsprozess nicht mehr, wird der Lauf sofort als `abgebrochen` markiert, statt lange im alten Prozentstand hängen zu bleiben
- Fortsetzen über mehrere Läufe: nach `abgebrochen` oder `fehler` macht jeder Bereich (`mitglieder`, `fraktionen`, `kommissionen`, `geschaefte`, `sitzungen`) dort weiter, wo er stehen geblieben ist
- bereits verarbeitete Datensätze bleiben gespeichert: geschrieben wird Element für Element, nicht alles oder nichts
- Bereits lokal als `erledigt`/`abgeschlossen` markierte Geschäfte werden beim Abgleich nicht erneut überschrieben; abgeschlossene Geschäfte gelten als endgültig.
- Eine einzige Sperre: systemweit läuft immer nur **eine** Synchronisation gleichzeitig (gleich ob von Hand oder nach Zeitplan).
- Startet ein zweiter Benutzer während eines laufenden Abgleichs, hängt sich sein Aufruf an den bestehenden Lauf an, statt einen zweiten zu starten.
- Auch nach einem Seitenwechsel erscheint ein bereits laufender Abgleich sofort wieder. In erster Linie kommt die Aktualisierung als Ereignis über die WebSocket-Verbindung (`sync.progress`); regelmässiges Nachfragen über HTTP springt nur ein, wenn diese Verbindung vorübergehend ausfällt.
- In der Verwaltung lässt sich ein laufender Abgleich über «Synchronisierung abbrechen» anhalten. Der Arbeitsprozess beendet sich geordnet; reagiert er nicht, wird er nach kurzer Frist hart beendet (TERM/KILL), danach steht der Status auf `abgebrochen` und die Sperre ist wieder frei. Das gilt auch gleich nach dem Start: Zwischen dem Start des Arbeitsprozesses und dem Greifen seiner Sperre liegen ein bis zwei Sekunden. Das Abbruch-Signal (die Datei neben der Sperrdatei) trägt seinen Zeitpunkt, der startende Lauf nimmt an, was nach seinem Start kam, und räumt nur weg, was von einem früheren Lauf übrig blieb.
- Die Verwaltung zeigt einen Gesamtfortschritt von 0 bis 100% über alle laufenden Bereiche (`100% = alle Bereiche vollständig synchronisiert`).

Synchronisation über die Schnittstelle starten:
- `POST /apps/parlwin/sync` startet den Lauf im Hintergrund (`202 Accepted`).
- `POST /apps/parlwin/sync/cancel` fordert den Abbruch des laufenden Abgleichs an. Wenn der Lauf sofort beendet werden konnte, kommt die Antwort direkt mit `abgebrochen=true`; sonst bleibt `abbruch_angefragt=true` (`202 Accepted`).
- Wenn bereits ein Lauf aktiv ist, liefert die Schnittstelle `bereits_laufend=true`; der Aufrufer hängt sich an den bestehenden Lauf an.
- Die Arbeit läuft im selben PHP-FPM-Container ohne Shell-Aufruf (kein `bash`/`sh` erforderlich).
- Fortschritt und Abschluss werden über `GET /apps/parlwin/sync/status` geliefert.
- `sync/status` zeigt laufende Abgleiche unabhängig davon, wer sie gestartet hat (`admin-ui`, `background-job`, `occ`).
- Die Hauptlisten (`Geschäfte`, `Sitzungen`, `Mitglieder`, `Kommissionen`, `Fraktionen`) werden vorab parallel geladen.
- Der Fortschritt für `Geschäfte` ist zweiphasig:
  - Phase 1: die Detailseiten der Parlamentswebseite laden
  - Phase 2: in `pw_geschaefte` und `pw_geschaeft_ereignisse` schreiben
  - Dadurch bleibt der Zähler nicht mehr lange auf `0/0`, sondern zeigt schon beim Herunterladen laufende Werte.
  - Auch ohne `curl_multi`, wenn die Seiten nacheinander geladen werden, wird der Zähler pro geladenem Geschäft erhöht und nicht erst am Ende.

### Datenquellen

| Datenquelle      | URL                                                  |
|------------------|------------------------------------------------------|
| Geschäfte        | https://parlament.winterthur.ch/politbusiness        |
| Sitzungen        | https://parlament.winterthur.ch/sitzung              |
| Mitglieder       | https://parlament.winterthur.ch/stadtparlament/27428 |
| Kommissionen     | https://parlament.winterthur.ch/kommissionen         |
| Fraktionen       | https://parlament.winterthur.ch/fraktionen           |
| Parteien         | https://parlament.winterthur.ch/fraktionen           |

### Datenstrukturen

#### Geschäfte (Politische Geschäfte)

Importierte Felder aus der Parlamentswebseite (nur lesbar):
- `id` / `extern_id` – numerische ID aus `/_rte/information/{id}` (kanonische technische ID)
- `titel` – Bezeichnung des Geschäfts
- `nummer` – Geschäftsnummer
- `typ` – Art des Geschäfts
- `status` – aktueller Stand
- `datum` – Eingangsdatum
- `url` – direkter Link auf der Webseite
- `quelle_hash` – Hash der zuletzt importierten öffentlichen Quellversion
- `quelle_aktualisiert_am` – Zeitpunkt der letzten inhaltlichen externen Änderung

Hinweis: Das rohe JSON der Quelle wird nicht als Ganzes gespeichert (`roh_daten`). Der Import übernimmt nur fachlich relevante, normalisierte Felder.

#### Entwürfe ohne Geschäftsnummer

Nicht oder noch nicht nummerierte Vorstösse werden in `pw_vorstoss_entwuerfe` geführt:
- `extern_id` – sofern bereits vorhanden (z. B. aus `/_rte/information/{id}`)
- `titel`, `titel_normalisiert`, `typ`, `eingangsdatum`, `url`
- `status` – z. B. `eingereicht_ohne_nummer`, `gematcht`
- `geschaeft_id` – Verknüpfung auf offizielles Geschäft nach Einreichung/Nummerierung
- `match_art` – `extern_id` oder `titel`

#### Verfahrensereignisse pro Geschäft

Detailseiten werden über den Link im Titel geladen (`/_rte/information/{id}`) und als Ereignisse gespeichert:
- Tabelle `pw_geschaeft_ereignisse`
- Beispiele: Beschlussdatum/-art, Abstimmungsresultat, Fristen, Vorberatung, Erheblicherklärung, Abschreibung

#### Fraktionsinterne Arbeitsdaten pro Geschäft

Interne, beschreibbare Felder liegen nicht in den Importfeldern, sondern in separaten Tabellen:

- `pw_geschaeft_zustaendigkeiten`
- Mehrfachzuweisung von Personen zu einem Geschäft
- Markierung einer Hauptzuständigkeit

- `pw_geschaeft_aktionen`
- Zeitachse aller Fraktionsaktionen mit Autor und Zeitstempel
- Typen: `notiz`, `beschluss`, `votum`, `zuweisung`
- Für Beschlüsse wird `entscheid_gueltig` gesetzt; in Listen wird standardmässig der letzte gültige Beschluss verwendet
- Pro Geschäft wird zusätzlich ein abgeleiteter `fraktionsstatus` berechnet:
  - `offen` (noch kein gültiger Fraktionsbeschluss)
  - `neu_zu_entscheiden` (Quelle wurde nach letztem Beschluss inhaltlich aktualisiert)
  - `entschieden` (kein neuer externer Änderungsstand seit letztem Beschluss)
- Daraus wird `entscheidungsbedarf` (bool) für Fraktionssitzungslisten abgeleitet

#### Votum mit Formatierung

Das «Votum im Rat» wird in einem Editor erfasst, der das Ergebnis gleich so zeigt, wie es aussieht (`PwWysiwyg.vue`, auf TipTap und ProseMirror aufgebaut). TipTap nutzt auch Nextcloud Text; es liefert sauberes, semantisches HTML, ohne den Umweg über ein Office-Format. Verfügbar sind: fett, kursiv, unterstrichen, durchgestrichen, Überschriften H2 und H3, Aufzählungen und nummerierte Listen, Blockzitat, Verweise mit automatischer Erkennung, Rückgängig und Wiederholen sowie «Formatierung entfernen». Die Werkzeugleiste zeichnet ihre Symbole direkt als SVG (Material Design Icons, Apache 2.0), sodass weder Schriftarten für Symbole noch zusätzliche Aufrufe an den Webserver nötig sind.

#### Votum als PDF herunterladen

Über den PDF-Knopf in der Werkzeugleiste (er erscheint, sobald Inhalt vorhanden ist) öffnet sich `/apps/parlwin/geschaefte/{id}/votum/pdf` in einem neuen Tab. Diese Route gibt das aktuelle Votum als HTML für den Druck auf A4 aus (Helvetica 11pt, Kopf- und Fusszeile mit Linien, eine Tabelle mit den Angaben zum Geschäft und dem letzten gültigen Beschluss) und ruft `window.print()` von selbst auf — dort bieten die Browser «Als PDF speichern» an. Dieser Weg spart eine zusätzliche PHP-Bibliothek für PDF samt ihrer Abhängigkeit in Composer.

Das Skript der Druckseite muss ein `nonce="<?php p($votumNonce); ?>"` tragen (gleiche Herleitung wie in `main.php`/`admin.php`). Die Content-Security-Policy von Nextcloud blockiert Inline-Skripte ohne Nonce ersatzlos und ohne sichtbaren Fehler: ohne ihn öffnet sich weder der Druck-Dialog automatisch, noch reagiert der Knopf «Als PDF speichern / drucken». Aus demselben Grund darf der Knopf kein Inline-`onclick` verwenden, sondern wird im Skript mit der Nonce auf den Klick gesetzt. Der Dialog wird zusätzlich über ein Zeitlimit ausgelöst, damit er auch dann erscheint, wenn `document.fonts.ready` nicht auslöst (nicht ladbare Schrift).

Der Wortlaut wird als HTML ausgegeben, damit die Formatierung im Ausdruck erhalten bleibt. Dafür ist `Service\HtmlSanitizer` zuständig: Er parst den Text und behält ausschliesslich die dort aufgezählten Elemente und Attribute; Verweise dürfen nur `http`, `https`, `mailto` oder `tel` als Schema tragen. Eine Negativliste («entferne `script`, entferne `on…`-Attribute») ist hier untauglich und war der zuvor verwendete Ansatz: `<img/onerror=…>` umgeht die Prüfung auf ein Leerzeichen vor dem Attribut, und `href="javascript:…"` kommt darin gar nicht vor. Der Text stammt zwar aus dem Editor, wird aber über die Schnittstelle gespeichert und ist damit frei wählbar — die Positivliste ist die einzige verlässliche Grenze. Festgenagelt ist das doppelt: `parlwin/tests/Templates/VotumPdfSicherheitTest.php` rendert die ausgelieferte Vorlage, und der e2e-Lauf prüft die Ausgabe des realen Endpunkts.

#### Automatische Zuständigkeit über Kommissionsmitgliedschaft

Wenn ein Geschäft aktuell in einer Kommission hängig ist (letztes Verfahrensereignis nennt das Kommissions-Organ) und niemand der eigenen Fraktion zugewiesen ist, weist die Synchronisation nach erfolgreichem Mitglieder- und Geschäftsabgleich automatisch alle Mitglieder dieser Kommission zu, die zur eigenen Fraktion gehören. Die erste gefundene Person wird als Hauptzuständige markiert; jede Zuweisung wird als reguläre Aktion (Typ `zuweisung`) in der Aktionszeitleiste des Geschäfts protokolliert. Bereits vorhandene Zuständigkeiten werden nie überschrieben.

#### Fraktionssitzungsmodus

- Notizen sind immer für alle möglich
- Beschlüsse sind im Modus `Fraktionssitzung` nur für Protokollführer oder aktive Protokoll-Stellvertretung schreibbar
- Fraktionspräsident und aktive Präsidiums-Stellvertretung können den Modus umstellen
- Protokoll-Stellvertretung kann durch Protokollführung oder Präsidium befristet gesetzt werden
- In der Geschäftsübersicht wird bei aktivem Modus standardmässig auf `Nur Entscheid nötig` gefiltert

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
- Der Abgleich mit dem Parlament löscht interne Sitzungen nie (er berücksichtigt nur Zeilen mit `extern_id IS NOT NULL`)

#### Traktanden

Felder aus der Parlamentswebseite:
- `sitzung_id`, `nummer`, `titel`, `beschreibung`, `url`
- `geschaeft_id` (Verknüpfung auf `pw_geschaefte`, falls vorhanden)

Fraktionsinterne Felder:
- `bemerkungen`: Kurznotiz zum Traktandum (z. B. «Dringlichkeit klären»)
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
```

Zusätzliche Spalten für die Sitzungs-Verknüpfung:

- `pw_sitzungen.verknuepfung_id` – Gruppen-ID verknüpfter Sitzungen.
- `pw_sitzungstypen.verknuepfen` – beim Anlegen Verknüpfung anbieten.
- `pw_sitzungstypen.kommissionen` – JSON-Liste der «beratenen» Kommissions-IDs, deren hängige Geschäfte automatisch über `pw_sitzung_geschaeft` verknüpft werden.

Hinweis: `pw_vorstoesse` ist die fraktionsintern gepflegte Vorstoss-Verwaltung (Tab «Vorstösse»); davon zu unterscheiden ist `pw_vorstoss_entwuerfe`, das die aus der Parlamentsquelle importierten, noch nicht nummerierten Vorstösse hält.

### Gemeinsam arbeiten in Echtzeit (WebSocket)

- Ereignisse, die der Server bei Änderungen sendet:
  - `geschaefte.updated`, `geschaefte.action`
  - `sitzungen.updated`, `traktanden.updated`
  - `fraktionssitzung.updated`, `fraktion.roles.updated`
  - `fragestunde.updated`
  - `sync.progress`, `sync.cancel.requested`, `sync.cancelled`
  - `sync.completed`
- Die Oberfläche verbindet sich immer mit dem Dienst `parlwin-realtime` (`ws://.../ws`).
- Jeder Verbindungsaufbau wird gegen die laufende Nextcloud-Anmeldung geprüft (Sitzungs-Cookie, App-Passwort oder HTTP-Basisauthentifizierung).
- Offene Fenster hören auf diese Ereignisse und laden die betroffenen Ansichten von selbst neu.

### Datenstruktur und Geschäftsablauf

Stand der Analyse: 2026-05-12

#### Struktur der importierten Webdaten

- Die Listen (`/politbusiness`, `/sitzung`, `/stadtparlament/27428`, `/kommissionen`, `/fraktionen`) liefern Daten primär über `data-entities="..."`.
- Bei `/fraktionen` kommt der Aktivstatus aus den Feldern `datumVon`/`datumBis`:
  - `aktiv = true`, wenn `datumBis` leer oder in der Zukunft liegt (und `datumVon` nicht in der Zukunft liegt).
  - `aktiv = false`, wenn `datumBis` in der Vergangenheit liegt.
  - Die Statusauswahl der Quelle (`Aktiv`/`Inaktiv`) spiegelt genau diese Logik.
- Bei `/stadtparlament/27428` (Mitglieder) wird `aktiv` aus mehreren Angaben der Quelle abgeleitet:
  - Primär über `_funktionAktiv` / `_funktionInaktiv` (falls gesetzt).
  - Ersatzweise über `_mandatPersonDatumVon` / `_mandatPersonDatumBis` mit derselben Datumslogik wie bei Fraktionen.
  - Der resultierende boolesche Wert wird in `pw_mitglieder.aktiv` geschrieben (kein hartes `true` mehr).
- Bei Geschäften enthält `title` typischerweise einen HTML-Link auf `/_rte/information/{id}`.
- Dieser Link wird als technische Primäridentität verwendet (`extern_id`, DB-`id`).
- `_nummer` bzw. `number` ist die fachliche Geschäftsnummer und bleibt ein eigenes Feld.
- Geschäftsdetailseiten liefern zusätzliche Verfahrensinformationen als `<dt>/<dd>`, die in `pw_geschaeft_ereignisse` strukturiert abgelegt werden.

#### Prüfung der ID- und Nummern-Stabilität

Auswertung der lokal gespeicherten Liste `parlwin/geschaefte.json`:

- Datensatzumfang: 1230 Einträge.
- Prüfung 1 (`/_rte/information/{id}` -> Geschäftsnummer): keine Kollisionen.
- Prüfung 2 (Geschäftsnummer -> `/_rte/information/{id}`): keine Kollisionen.

Fazit:
- Im geprüften Datenstand ist die Beziehung 1:1.
- Modelliert bleibt sie trotzdem als zwei getrennte Felder (`id`/`extern_id` und `nummer`), damit Sonderfälle kontrolliert behandelt werden können.

#### Modellierung der Vorstoss-Phasen

- Nummerierte Geschäfte laufen in `pw_geschaefte`.
- Nicht nummerierte oder noch nicht eingereichte Vorstösse laufen in `pw_vorstoss_entwuerfe`.
- Bekommt ein Vorstoss später seine Nummer, wird er zuerst über `extern_id` zugeordnet, sonst über `titel_normalisiert` und `typ`.

#### Modellierung der Fraktionsarbeit

- Die eingelesenen Felder sind nur lesbar und kommen ausschliesslich aus der Synchronisation.
- Fraktionsintern gearbeitet wird über `pw_geschaeft_zustaendigkeiten` (mehrere Zuständige und eine hauptzuständige Person) und `pw_geschaeft_aktionen` (die Zeitleiste: Notizen, Beschlüsse, Voten, Änderungen der Zuweisung).
- Jede Aktion trägt Autor und Zeitstempel.
- Die Standardansicht zeigt den letzten gültigen Beschluss; das Detail zeigt alle Aktionen.
- Fraktionsstatus wird implizit berechnet:
  - letzter gültiger Fraktionsbeschluss (`pw_geschaeft_aktionen.erstellt_am`)
  - letzte inhaltliche externe Änderung (`pw_geschaefte.quelle_aktualisiert_am`)
- Ist die Quelle neuer als der letzte Beschluss, gilt das Geschäft als `neu_zu_entscheiden` und hat `entscheidungsbedarf = true`.

#### Fraktionssitzungsmodus

- Notizen bleiben jederzeit für alle möglich.
- Beschlüsse sind im Modus `Fraktionssitzung` nur durch die aktive Protokollführung (Protokollführer oder befristete Protokoll-Stellvertretung) erfassbar.
- Fraktionspräsident oder aktive Präsidiums-Stellvertretung dürfen den Modus umstellen und den Protokollführer setzen.

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

- Kanton Zürich, Gemeindegesetz (GG), insbesondere §§ 34-35.
- Stadt Winterthur, Organisationsverordnung Stadtparlament (OV Parl), insbesondere Art. 77 ff.

#### Geschäftsgang nach Vorstossart

- **Motion:** Einreichung -> Überweisung/Ablehnung -> Bericht/Antrag -> Erheblicherklärung oder Abschreibung.
- **Postulat:** Einreichung -> Überweisung/Ablehnung -> Bericht -> Kenntnisnahme, ggf. Nachbericht.
- **Interpellation / schriftliche Anfrage:** Einreichung -> Beantwortung -> Kenntnisnahme.
- Die Webseite zeigt die tatsächlichen Abläufe teils in mehreren Schritten innerhalb eines Geschäfts; deshalb bleibt die Prozesssicht ereignisbasiert statt als einzelnes Statusfeld.

#### Geschäftsarten je Kategorie

Die Zuordnung hat den Stand vom 2026-05-12:

- `motion`: `Motion`, `Dringliche Motion`, `Budget-Motion`
- `postulat`: `Postulat`, `Dringliches Postulat`, `Budget-Postulat`
- `interpellation`: `Interpellation`, `Dringliche Interpellation`, `Fragestunde`
- `schriftliche_anfrage`: `Schriftliche Anfrage`
- `bericht`: `Bericht`, `Jahresrechnung`, `Kreditabrechnung`
- `initiative`: `Volksinitiative`, `Einzelinitiative`, `Parlamentarische Initiative`
- `vorlage`: `Kreditantrag`, `Budget`, `Beschlussantrag`, `Parlamentseigene Vorlage`, `Verordnung / Rechtserlass`, `Vertrag / Vereinbarung`, `Rechtsmittel`, `Referendum`, `übrige Geschäfte`
- `wahlen`: `Wahlen`

Technisch ist das in `lib/Service/GeschaeftWorkflow.php` zentralisiert und über `tests/Service/GeschaeftWorkflowTest.php` mit den aktuell beobachteten Kategorien abgesichert.


## Lizenz

MIT
