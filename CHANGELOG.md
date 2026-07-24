# Changelog

- 2026-07-24 **1.7.30**
    - Die Aktionszeitleiste gibt es jetzt auch bei den Vorstössen – dieselbe Darstellung wie beim Geschäft
    - Sitzungstypen lassen sich wieder anlegen; das Anlegen mit nur einem Namen schlug bisher fehl, und im Bearbeiten stehen alle Felder zur Verfügung
    - Im Sitzungstyp sind die beiden Schalter «Eigene Fraktion» und «Beim Anlegen Verknüpfung anbieten» wieder anklickbar
    - «+ Neue Sitzung» öffnet ein Menü mit je einem Eintrag pro Sitzungstyp; ein Klick darauf führt direkt ins vollständige Formular, ohne definierten Sitzungstyp steht ein Hinweis im Menü
    - Eigene Geschäfte lassen sich jetzt vollständig pflegen: Titel, Typ, Status und Datum sind direkt in der Geschäftsmaske änderbar, und ein selbst angelegtes Geschäft kann wieder gelöscht werden
        - Geschäfte von der Parlamentswebseite bleiben unverändert schreibgeschützt, damit die Angaben aus der Quelle erhalten bleiben
    - «+ Neuer Vorstoss», «+ Eigenes Geschäft» und «+ Neuer Typ» öffnen dieselbe vollständige Maske wie das Bearbeiten, mit allen Feldern
        - Beim Anlegen wird nichts vorab gespeichert: unten stehen «Speichern» und «Abbrechen»; erst «Speichern» legt den Eintrag an und die Maske geht gleich in die Bearbeitung über, sodass sich Dokumente und Notizen direkt anschliessen lassen
        - Ein Klick neben die Maske verwirft nichts mehr — so gehen angefangene Eingaben nicht mehr versehentlich verloren; verworfen wird nur über «Abbrechen»
        - Notizen, Dokumente und der Verlauf erscheinen erst nach dem Speichern, weil sie sich auf einen bereits bestehenden Eintrag beziehen; ein Hinweis in der Maske sagt das
    - Jede Änderung hinterlässt eine Spur: geänderte Angaben und Prioritäten erscheinen mit Vorher- und Nachher-Wert im Verlauf des Geschäfts
    - Das Votum im Rat lässt sich jetzt direkt im Geschäft erfassen: Wortlaut schreiben, laufend automatisch gespeichert, als PDF drucken und archivieren
        - Erfassen darf es die für das Geschäft zuständige Person; alle anderen sehen den Wortlaut, können ihn aber nicht ändern
        - Bisher war diese Funktion nur über die Druckansicht erreichbar, ohne Möglichkeit, den Text einzugeben
        - In der Druckansicht öffnet sich der Druckdialog wieder von selbst, und der Knopf «Als PDF speichern / drucken» reagiert wieder — beides blieb bisher wirkungslos
        - Der Dialog erscheint jetzt auch dann, wenn eine Schriftart nicht geladen werden kann
        - Die Druckansicht übernimmt weiterhin die volle Formatierung, gibt aber nur noch Gestaltungselemente aus: eingebettete Skripte, Ereignis-Handler und Verweise mit ausführbarem Ziel erscheinen nicht mehr im Ausdruck
    - Neue Sitzungsnotizen: Notizen, die man in einer Sitzung zu einem verknüpften Geschäft erfasst, haften nun am Geschäft (nicht mehr nur an dieser einen Sitzung)
        - Sie erscheinen automatisch bei jeder aktuellen und künftigen Sitzung, an der dasselbe Geschäft hängt – auch wenn das Geschäft auf eine spätere Sitzung verschoben wird
        - Im Geschäft selbst erscheinen die Sitzungsnotizen separat unter den normalen Notizen, aufklappbar und standardmässig eingeklappt
    - Der Hinweistext beim Synchronisations-Zeitplan nennt jetzt die tatsächlich vorbelegten Standardzeiten (alle Wochentage um 10:00 und 18:00 Uhr) statt der früheren Zeiten
    - Selbst angelegte Geschäfte bleiben bei der automatischen Aktualisierung erhalten — bisher konnten sie bei einer Synchronisation fälschlich verschwinden
    - Die Gesamt-Testauswertung zeigt nur noch Ergebnisse des aktuellen Laufs; ein liegengebliebener Bericht eines früheren Laufs wurde bisher mitgezählt
    - Beim Betrieb bekommt kein Dienst mehr ein Verzeichnis des Servers zu sehen: die Routing-Einstellungen des vorgelagerten Webservers stecken jetzt in seinem Abbild; beim Aktualisieren wird er mitgebaut (`--build`, siehe Betriebsanleitung)

- 2026-07-20 **1.7.29**
    - Notizen bei Vorstössen funktionieren jetzt genau gleich wie bei Geschäften: derselbe Editor mit Formatierung, ein Versionsverlauf zum Zurückblättern und Übernehmen älterer Fassungen, Zwischenspeichern während des Tippens, sofortiges Speichern beim Verlassen sowie Löschen und Wiederherstellen durch den Verfasser
    - Eingereichte Geschäfte werden dem einreichenden Fraktionsmitglied wieder automatisch als zuständig zugeordnet – auch wenn die Parlamentswebseite den Namen in der Reihenfolge «Nachname Vorname» liefert
    - Läuft die automatische Aktualisierung ohne eigenen Zeitplan, gilt neu ein sinnvoller Standard: zwei Läufe an allen Wochentagen um 10:00 und um 18:00 Uhr; dieser Standard erscheint in den Admin-Einstellungen vorbelegt und lässt sich dort bearbeiten
    - Die Funktions- und Entwickler-Dokumentation wurde vollständig ausformuliert: alle Auswahlwerte, Standard-Sortierungen, Berechtigungen und Fehlerfälle sind nun vollständig beschrieben

- 2026-07-19 **1.7.28**
    - Jede Eingabe speichert überall sofort – Abbrechen/Speichern-Buttons entfallen in der ganzen App (Konsistenz)
        - Vorstösse: Neu-Anlegen über einen minimalen Titel-Dialog («Erstellen»), danach öffnet direkt die Bearbeitung; Notizen speichern beim Verlassen des Editors
        - Sitzungstypen: Bearbeiten per Klick auf die Karte, jede Eingabe speichert sofort; Neu-Anlegen über einen minimalen Namens-Dialog
        - Auch die Dialoge «Eigenes Geschäft», «Neue Sitzung» und «Neues Dokument» kommen ohne Abbrechen-Knopf aus (✕ schliesst)
    - Die Priorität ist neu auch in der Geschäfts-Detailansicht sichtbar und einstellbar
    - Verbindliche Design-Anforderungen und eine vollständige Funktions-Spezifikation sind neu dokumentiert (CONTRIBUTING, FEATURES)
    - Der Browser-Testlauf prüft immer den aktuellen Stand: das Test-Image wird vor jedem Lauf neu gebaut, der Testbericht liegt in einem frischen Verzeichnis pro Lauf
    - Kürzel gelten neu überall: die definierten Kurzformen kürzen nicht nur Status, sondern auch Partei-, Fraktions- und Kommissionsnamen — in allen Listen, Karten und Auswahlfeldern; das Suchtext-Feld der Verwaltung schlägt zusätzlich die aktuellen Fraktions- und Parteinamen vor
    - Der Zeitplan der automatischen Synchronisation ist in den Admin-Einstellungen konfigurierbar: beliebige Einträge mit Wochentagen (Mo–So) und Uhrzeit, mit Hinzufügen und Löschen; verpasste Zeitpunkte werden nachgeholt

- 2026-07-18 **1.7.27**
    - Vorstösse werden wieder angezeigt – die Liste blieb komplett leer («Keine Vorstösse vorhanden»), sobald ein Vorstoss ohne Notiz, Zuständigkeit oder Ansprechpartner gespeichert war
    - Der Inhalt eines Vorstosses wird beim Bearbeiten nicht mehr durchgehend fett dargestellt

- 2026-07-18 **1.7.26**
    - Eine nicht gesetzte Priorität wird in der Übersicht wieder als «—» (nicht gesetzt) angezeigt und lässt sich auch wieder auf «nicht gesetzt» zurückstellen – vorher erschien sie fälschlich als «Mittel»

- 2026-07-18 **1.7.25**
    - Vorstösse haben jetzt Notizen – wie die Geschäfte, im selben Formatierungs-Editor
    - Ein Vorstoss lässt sich mit einem Geschäft verknüpfen und so als Vorstufe abschliessen: die Auswahl schlägt die zum Titel ähnlichsten Geschäfte zuerst vor (sonst die neuesten); beim Verknüpfen wird die Priorität des Vorstosses ins Geschäft übernommen und der Vorstoss auf «erledigt» gesetzt
    - Im Geschäft werden die verknüpften Vorstösse mit ihren Angaben (Haltung, Zuständigkeit, Notizen) angezeigt

- 2026-07-18 **1.7.24**
    - Behoben: Ein gespeicherter Vorstoss zeigte beim Öffnen keine Daten mehr – Titel, Zuständigkeit, Haltung und Inhalt werden wieder korrekt geladen und angezeigt
    - Die Vorstoss-Übersicht ist jetzt wie die Geschäfte aufgebaut: ein Klick auf eine Karte öffnet die Bearbeitung; ein separater Löschen-Knopf entfällt – der Lebenszyklus wird über den Status gesteuert
    - Vorstösse haben neu eine Priorität (hoch, mittel, tief; Standard nicht gesetzt); hohe Vorstösse werden in der Übersicht hervorgehoben, tiefe abgeschwächt – wie bei den Geschäften

- 2026-07-18 **1.7.23**
    - Beim Erfassen eines neuen Vorstosses ist man selbst als zuständige Person vorausgewählt
    - Beim Erstellen eines Dokuments (Geschäfte und Vorstösse) ist der Dateiname bereits mit dem Titel vorbelegt – Leerzeichen werden zu Unterstrichen, der Cursor steht am Ende, sodass nur noch eine Ergänzung (z.B. «-rede») angehängt werden muss

- 2026-07-18 **1.7.22**
    - Die App-Aktualisierung bleibt nicht mehr im Wartungsmodus stecken: Lässt sich eine Datenbank-Anpassung beim Update nicht anwenden, stoppt der Dienst jetzt zuverlässig und macht den Fehler sichtbar, statt unerreichbar hängen zu bleiben — nach einem Neustart läuft die Aktualisierung erneut

- 2026-07-17 **1.7.21**
    - Jedes Geschäft hat neu eine Priorität (hoch, mittel, tief)
        - Eine nicht gesetzte Priorität wird wie «mittel» behandelt
        - In den Übersichten werden Geschäfte mit hoher Priorität dezent hervorgehoben und solche mit tiefer Priorität abgeschwächt (sichtbar, aber zurückhaltend)
        - Neuer Filter nach Priorität
    - Der «Neuer Vorstoss»-Dialog nutzt jetzt dieselben Eingabe-Elemente wie die übrigen Ansichten
        - Die Art wird aus den bekannten Vorstoss-Arten gewählt und lässt sich frei überschreiben
        - Die Zuständigkeit ist eine Personen-Liste statt eines Freitextfelds
        - Der Inhalt wird im selben Formatierungs-Editor wie die Notizen erfasst
        - Dokumente lassen sich anlegen, hochladen oder aus dem Vorstoss-Ordner auswählen
        - Bei einem fremden Vorstoss lassen sich zusätzlich die Haltung (miteinreichen, unterstützen, Stimmfreigabe, ablehnen, Ablehnungsantrag stellen), die Herkunftsfraktion und die Ansprechpartner dieser Fraktion erfassen

- 2026-07-16 **1.7.20**
    - Der Löschen-Knopf einer Notiz zeigt neu ein Mülleimer-Symbol – so ist er klar vom Schliessen-Kreuz (✕) des Fensters unterscheidbar und wird nicht mehr verwechselt
    - Ein Klick auf das Schliessen-Kreuz eines Dialogs schliesst nur noch den Dialog und wird nicht mehr an dahinterliegende Elemente weitergegeben

- 2026-07-16 **1.7.19**
    - Beim Blättern durch die Versionen einer Notiz wird nie die angezeigte alte Fassung gespeichert, sondern immer der tatsächliche Arbeitsstand
        - «Zur neuesten Version» (»») bringt wieder den aktuellen Bearbeitungsstand zurück, nicht die zuletzt gespeicherte Fassung
        - Automatisches Speichern und der Fokus-Verlust sichern immer den Arbeitsstand
    - Eine ältere Fassung lässt sich wiederherstellen: zur gewünschten Version zurückblättern und mit dem Haken bestätigen – die alte Fassung wird als neue aktuelle Version übernommen, der bisherige Verlauf bleibt vollständig erhalten
    - Abbrechen während des Blätterns verwirft die laufende Bearbeitung, ohne etwas zu speichern

- 2026-07-16 **1.7.18**
    - Der Löschen-Knopf einer Notiz sitzt jetzt ganz rechts – bündig mit dem Wiederherstellen-Knopf in der Aktionszeitleiste

- 2026-07-15 **1.7.17**
    - Notizen erfassen und bearbeiten laufen jetzt vollständig gleich ab
        - Eine neue Notiz öffnet sofort denselben Editor wie das Bearbeiten – die Notiz wird erst gespeichert, sobald sie Text enthält
        - Eine neu getippte Notiz erscheint nicht mehr doppelt (einmal im Editor, einmal als fertiger Eintrag)
        - Wird der Editor leer geschlossen (Haken oder Verlassen des Feldes), entsteht keine leere Notiz mehr
        - Der Knopf «Neue Notiz» bleibt immer sichtbar

- 2026-07-15 **1.7.16**
    - Der Browser-Testlauf arbeitet vollständig mit einer eigenen, beim Bauen erstellten Kopie der Tests
        - Er greift nicht mehr auf das Arbeitsverzeichnis zu: Änderungen während eines laufenden Tests verfälschen das Ergebnis nicht mehr, und ein Testlauf kann am Arbeitsstand nichts mehr verändern
        - Der Testbericht wird nach dem Lauf aus dem Container geholt und gehört dem angemeldeten Benutzer, nicht mehr dem Systemverwalter
        - Der Testlauf läuft ohne besondere Rechte

- 2026-07-14 **1.7.15**
    - Die ausgelieferten Container werden neu automatisch darauf geprüft, dass sie keine Shell und keine Skriptsprache enthalten — wer Codeausführung im Container erreicht, findet dort kein Werkzeug vor, mit dem er weiterkommt
    - Der Browser-Testlauf lädt seine Werkzeuge nicht mehr bei jedem Durchgang aus dem Internet nach; er läuft dadurch verlässlich durch, auch wenn die Paketquelle gerade nicht erreichbar ist

- 2026-07-14 **1.7.14**
    - Die Ordnerstruktur im Fraktions-Ordner wird zuverlässig auf den aktuellen Stand gebracht
        - «40_Wahlkampf» heisst neu «50_Wahlkampf», «50_Medien» neu «60_Medien» – die Inhalte wandern mit
        - Erst danach entsteht «40_Vorstösse», damit die Nummern nicht doppelt vergeben werden; die Nummerierung läuft lückenlos in 10er-Schritten
        - Blieb ein Ordner aus einem früheren, abgebrochenen Lauf unter dem alten Namen zurück, wird sein Inhalt jetzt übernommen und der alte Ordner entfernt; gleichnamige Dateien werden dabei nie überschrieben

- 2026-07-14 **1.7.13**
    - Notizen haben einen eigenen Bereich (direkt nach dem Beschluss) und stehen nicht mehr in der Aktionszeitleiste
        - Statt eines grossen leeren Formulars erscheint nur ein Knopf «Neue Notiz» – der Editor öffnet sich erst auf Klick
    - Eine gelöschte Notiz wird nicht mehr entfernt, sondern nur ausgeblendet
        - In der Aktionszeitleiste erscheint «… hat seine Notiz gelöscht»; der Verfasser kann das Löschen rückgängig machen, worauf die Notiz samt Verlauf zurückkehrt
    - Beim Bearbeiten einer Notiz bleibt die bisherige Fassung als Version erhalten
        - Im Editor lässt sich mit «←» und «→» durch die Versionen blättern; «»» führt zurück zur neuesten Fassung
        - Ältere Fassungen werden nur angezeigt, bearbeitet wird immer die neueste
    - Der Absatz-Knopf ist aus der Formatierungsleiste verschwunden (Zeilenumbrüche genügen)
    - Notizen sehen in der Vorschau genauso aus wie fertig dargestellt – mit sichtbaren Aufzählungspunkten und Absatzabständen
    - Zuständigkeit wird automatisch gesetzt, wenn Fraktionsmitglieder ein Geschäft eingereicht haben – in der Reihenfolge der Einreicher und mit Vorrang vor der Zuweisung nach Kommission
    - Im Änderungsverlauf sind alle Einträge der aktuellen Version aufgeklappt, ältere bleiben zugeklappt
    - Die Synchronisation bricht nicht mehr mitten im Lauf ab

- 2026-07-14 **1.7.12**
    - Notiz erfassen: Ein Klick auf die Formatierungsleiste (Fett, Kursiv, Aufzählung …) verwarf bisher den bereits getippten Text und speicherte die Notiz vorzeitig
        - Der getippte Text bleibt beim Formatieren nun erhalten; gespeichert wird erst, wenn das Eingabefeld wirklich verlassen wird
    - Das Notizfeld sitzt nicht mehr in einem überflüssigen Rahmen: es beginnt kompakt, wächst mit dem Inhalt und rollt erst, wenn es seine maximale Höhe erreicht
    - Rückmeldungen wie «Notiz gespeichert» erscheinen neu als gewohnte Nextcloud-Benachrichtigung statt als übersehbare Zeile am Seitenende

- 2026-07-10 **1.7.11**
    - Bugfix (seit 1.6.2): Beim langsamen Tippen einer Notiz oder eines Fraktionsbeschlusses erschien dieselbe Änderung mehrfach im Verlauf
        - Zwischenspeichern während des Tippens führt die Stände nun in einem einzigen Eintrag zusammen
        - Erst beim Verlassen des Feldes wird definitiv gespeichert und das Eingabefeld geleert; nur ein späteres, erneutes Bearbeiten erzeugt einen neuen Verlaufseintrag
    - Testlauf bricht bei fehlender Office-Integration nicht mehr stillschweigend ab, sondern meldet die Ursache

- 2026-06-25 **1.7.10**
    - Einheitliche Bedienung: «Neue Sitzung» erscheint nun – wie «Neuer Vorstoss», «Neuer Typ» und «Eigenes Geschäft» – als beschrifteter Knopf statt als rundes Symbol; Filter erscheinen überall in der Seitenleiste

- 2026-06-24 **1.7.9**
    - Neuer Bereich «Vorstösse» (zwischen Kommissionen und Sitzungstypen): politische Vorstösse erfassen und verwalten
        - Herkunft «eigene» oder «fremde»; bei fremden Vorstössen lässt sich die eigene Haltung als Beschluss festhalten
        - Status neu, Entwurf, bereit, eingereicht, erledigt oder pausiert; mit Zuständigkeit, Art und Inhalt
        - Filter nach Herkunft und Status sowie Suche nach Titel, Art und Zuständigkeit
        - Dokumente im Ordner «Fraktion/40_Vorstösse» (10_Eigene/20_Fremde) werden automatisch als Vorstösse übernommen

- 2026-06-24 **1.7.8**
    - In einer aufgeklappten Sitzung lassen sich To-dos erfassen, die als Karte im Deck-Board «Fraktion» (Spalte «To-do») landen – mit Bezug auf die Sitzung
    - Änderungsverlauf: die Einträge werden nun sauber als Liste mit Aufzählung, Verschachtelung und Fettschrift dargestellt statt als zusammenhängender Textblock

- 2026-06-24 **1.7.7**
    - Sitzungstypen können «beratene» Kommissionen festlegen: die in diesen Kommissionen hängigen Geschäfte werden für künftige Sitzungen dieses Typs automatisch verknüpft (regelmässig im Hintergrund, sodass kurz vor der Sitzung alles bereitsteht)

- 2026-06-24 **1.7.6**
    - In einer aufgeklappten Sitzung lassen sich Geschäfte verknüpfen: bereits verknüpfte Geschäfte werden aufgelistet, weitere können über eine Auswahl hinzugefügt und einzeln wieder entfernt werden

- 2026-06-24 **1.7.5**
    - Zu jeder Sitzung lassen sich Dokumente ablegen und öffnen (im Ordner «Fraktion/10_Sitzungen/{Jahr}», nach Sitzungsdatum benannt) – gleiche Bedienung wie die Dokumente bei den Geschäften

- 2026-06-24 **1.7.4**
    - Bugfix: Beim automatischen Aktualisieren (wenn jemand anderes etwas speichert) sprangen die Sitzungs- und die Geschäftsliste an den Anfang; sie werden nun an Ort und Stelle aktualisiert, ohne dass die Ansicht springt

- 2026-06-24 **1.7.3**
    - Verknüpfte Sitzungen zeigen nun die Notizen der jeweils anderen Sitzungen der Verknüpfung gesammelt an (nur Anzeige); eine Sitzung lässt sich direkt wieder entkoppeln, wobei alle Notizen an ihrem Platz bleiben

- 2026-06-24 **1.7.1**
    - Notizen lassen sich jetzt formatieren: Eingabe über eine Werkzeugleiste mit Fett, Kursiv, Unterstrichen, Durchgestrichen, Überschriften, Aufzählungen, nummerierten Listen, Zitat, Code und Links
        - Alle Notizfelder (Geschäfte, Sitzungen, Traktanden) verwenden denselben Editor; neue und bestehende Notizen werden formatiert angezeigt
        - Intern werden Notizen als Markdown gespeichert
    - Gemeinsames Aufgaben-Board: Ein Deck-Board «Fraktion» wird automatisch angelegt und mit der Fraktionsgruppe geteilt (analog zum gemeinsamen Ordner und Kalender), mit den Spalten «To-do», «In Arbeit» und «Erledigt». Ist Deck nicht installiert, bleibt die Funktion einfach inaktiv
    - Erweiterte Ordnerstruktur im Fraktionsordner: neuer Ordner «40_Vorstösse» mit den Unterordnern «10_Eigene» und «20_Fremde»; die bisherigen Ordner «Wahlkampf» und «Medien» heissen neu «50_Wahlkampf» und «60_Medien» – die Inhalte werden dabei automatisch und verlustfrei übernommen
    - Sitzungen lassen sich verknüpfen: Sitzungstypen können «Verknüpfen» aktivieren; beim Anlegen einer solchen Sitzung kann sie mit einer anderen Sitzung verknüpft werden (zukünftige Sitzungen zuerst, danach vergangene)
    - Neuer Tab «Änderungsverlauf» zeigt diese Änderungsliste direkt in der App

- 2026-06-24 **1.7.0**
    - Tab «Mitglieder»: neue Sortier- und Filtermöglichkeiten
        - Sortierung wählbar über «Sortieren»: Funktion (Standard), Fraktion, Partei, Name
        - Standard-Sortierung nach Funktion: Fraktionspräsident, Stellvertreter, Kommissionspräsident, Kommissionsmitglied, danach Partei und Name
        - Neuer Filter «Funktion»: nur Fraktionspräsidenten oder nur Kommissionspräsidenten anzeigen
        - Neuer Filter «Kommission»: nur Mitglieder einer bestimmten Kommission anzeigen
        - Sortieren und Filtern sind nun deutlich voneinander abgegrenzt (eigene Abschnitte)
    - App-Symbol einfarbig wie die übrigen Nextcloud-Apps (vorher als einziges zweifarbig)
    - Neue Sitzungen sind als Datum standardmässig auf eine Woche im Voraus vorbelegt
    - Bugfix: In der Geschäftsliste war der Beschluss nicht lesbar – die Spalte war zu schmal und schnitt den Text ab (z.B. nur «Zu» statt «Zustimmung»); sie ist jetzt breit genug
    - Bugfix: Bei nur einem Sitzungstyp liess sich keine neue Sitzung anlegen – der «+»-Knopf führte die Aktion direkt aus, statt das Auswahlmenü zu öffnen; jetzt erscheint immer das Menü
    - Bugfix: Geschäfts-Detailansicht – lange Verfasser-Listen sprengten die Breite; die Tabelle bricht jetzt um. «Einreichende» heisst neu «Einreicher», und die Rollen erscheinen ohne Schrägstrich-Gendern (z.B. «Erstunterzeichner» statt «Erstunterzeichner/-in»)

- 2026-06-19 **1.6.2**
    - Behebt, dass sich die App nach einem Nextcloud-Upgrade nicht mehr aktivieren liess («could not enable app»): Eine Datenbank-Anpassung beim Aktivieren verwendete eine in Nextcloud 34 entfernte interne Funktion und brach die Aktivierung ab. Sie ermittelt den Tabellennamen jetzt über die System-Konfiguration

- 2026-06-19 **1.6.1**
    - Weitere Anpassungen an Nextcloud 34 (Fortsetzung von 1.6.0):
        - Geschäfts- und Sitzungslisten laden wieder vollständig (Nextcloud 34 wies grosse Listenabfragen mit «Interner Serverfehler» ab; betraf auch die Admin-Seite und die Status-Kürzel)
        - Auch die Admin-Seite öffnet wieder fehlerfrei (gleiche in Nextcloud 34 entfernte interne Schnittstelle wie bei der Startseite)
        - Seitenleiste sieht wieder genau wie die übrigen Nextcloud-Apps (z.B. Dateien) aus: durchgängig Nextclouds Standard-Aufbau und -Symbole übernommen (statt eigener Darstellung mit Emoji), inklusive korrektem Hintergrund und Layout nach dem Update

- 2026-06-19 **1.6.0**
    - Kompatibilität mit Nextcloud 34 wiederhergestellt: Nextcloud wurde kurz nach dem letzten Release von Version 33 auf 34 angehoben. Dieser Versionssprung brachte mehrere Änderungen, mit denen das bisherige Tool nicht mehr zusammenpasste – es wurde unter Nextcloud 34 sogar automatisch abgeschaltet. Die folgenden Anpassungen stellen die Kompatibilität wieder her (fortgesetzt in 1.6.1):
        - Die App bleibt nach Nextcloud-Updates aktiv: die obere Nextcloud-Versionsgrenze wurde aufgehoben, sodass ein Nextcloud-Upgrade die App nicht mehr automatisch deaktiviert
        - Lauffähig unter Nextcloud 34: die Startseite lädt wieder (eine in Nextcloud 34 entfernte interne Schnittstelle wird nicht mehr verwendet; vorher «Interner Serverfehler»)
    - Die Uhrzeiten der automatischen Synchronisation sind konfigurierbar (Standard 03:00 und 15:00 Uhr)
    - Bugfix: Automatische Synchronisation lief nicht

- 2026-06-18 **1.5.3**
    - Hatte ein Mitglied selbst schon einen «Fraktion»-Ordner mit der Gruppe geteilt, wird dieser jetzt sauber in den offiziellen Ordner überführt: der bisherige Ordner bleibt beim Eigentümer als «Fraktion.bak» erhalten, sein Inhalt (auch eigene Unterordner und Dateien) wird in den offiziellen Ordner übernommen, und alle anderen Mitglieder sehen nur noch den offiziellen Ordner
    - Beim Zusammenführen gehen keine Dateien verloren: bei gleichem Namen wird die übernommene Datei als «name.migrated» abgelegt
    - Der offizielle Fraktionsordner erscheint bei allen Mitgliedern zuverlässig unter «Fraktion» (nicht mehr versehentlich als «Fraktion (2)»)

- 2026-06-18 **1.5.2**
    - Der geteilte Fraktionsordner erscheint jetzt zuverlässig bei allen Mitgliedern – auch wenn die Freigabe zuvor nicht automatisch angenommen wurde oder ein Mitglied erst später dazukam (die Freigabe wird beim Öffnen und bei jeder Gruppenänderung für alle Mitglieder bestätigt)

- 2026-06-17 **1.5.1**
    - Status-Kürzel-Verwaltung: Suchtext-Feld nutzt jetzt die volle verfügbare Breite, das Kürzel-Feld behält eine passende Breite und der Löschen-Knopf beansprucht nur den nötigen Platz

- 2026-06-17 **1.5.0**
    - Geschäfteliste zeigt wieder zuverlässig alle Geschäfte: eine einzelne unvollständige Datenzeile (fehlendes Quell-Datum) blendete bisher die gesamte Liste aus («Keine Geschäfte gefunden»)
    - Synchronisation bricht nicht mehr ab, wenn Miteinreicher importiert werden
    - Namen von Einreichenden werden korrekt dargestellt (keine «&#39;»-Zeichen mehr bei Apostrophen)
    - Geschäfte lassen sich serverseitig nach Status filtern
    - Geteilter Fraktionsordner erscheint jetzt zuverlässig bei allen Mitgliedern und bleibt erhalten (wurde vorher teils wieder entfernt)
    - Geteilter Fraktionskalender erscheint und ist bearbeitbar bei allen Mitgliedern
    - Fraktionsordner und Kalender werden automatisch beim Öffnen und bei Wechsel der Fraktionsgruppe geprüft und ergänzt – der manuelle Knopf und die Einstellung «Kalender-Benutzer» entfallen (es wird immer das Admin-Konto verwendet)
    - Eigene Geschäfte lassen sich wieder anlegen (Speicherfehler behoben)
    - Status-Kürzel bleiben gespeichert, werden automatisch gespeichert und in der Verwaltung korrekt angezeigt (vorher «[object Object]» bzw. in Firefox eine leere Liste durch zwei sich überschreibende Implementierungen)
    - Beschluss- und Notizänderungen erscheinen bei allen Mitgliedern sofort ohne Neuladen
    - Zusammenarbeit mehrerer Mitglieder durchgehend per Mehrnutzer-Test abgesichert (Ordner, Kalender, Dokumente, Echtzeit)

- 2026-06-15 **1.4.1**
    - Fraktionsordner und Kalender funktionieren jetzt korrekt mit Gruppenmitgliedern
        - Admin-Account erstellt Infrastruktur, Gruppe teilt korrekt
        - Alle Member sehen Ordner und Kalender
        - Dateien und Termine für alle Mitglieder lesbar und bearbeitbar

- 2026-06-14 **1.3.4**
    - Status-Kürzel in Admin-Einstellungen
        - Textersetzungen für lange Statusbeschriftungen (z.B. «BSKK»)
        - Auto-Save nach 5s oder beim Feldverlust
    - Dokumente hochladen: bestehende Dateien direkt hochladen
    - Eigene Geschäfte erstellen: Geschäfte ausserhalb Parlamentsregister
    - Fraktions-Infrastruktur automatisch beim Start prüfen

- 2026-05-29 **1.3.3**
    - Miteinreicher aus Parlamentswebseite einlesen
        - Alle Einreichenden mit Rolle in Detailansicht
        - Namen als Zusatzzeile in Geschäftsliste
    - Bugfix: Freier Text im Fraktionsentscheid korrekt anzeigen
    - Geschäftslisten: Nr., Datum, Typ untereinander in Nr.-Spalte
    - Sitzungsliste: Status-Spalte entfernt
    - Aktionszeitleiste: Uhrzeit unter Datum

- 2026-05-27 **1.3.2**
    - Versionsnummer in Navigationsleiste (aus info.xml)
    - Synchronisation: 2x täglich (03:00 + 15:00 Uhr)
    - Beschluss-Widget überall identisch
        - Kartensicht, Tabellenansicht, Detailansicht gleich
        - Freitext-Beschlüsse korrekt gespeichert

- 2026-05-27 **1.3.1**
    - Notizen Auto-Save in Sitzungsliste
        - Bei Fokusverlust oder nach 5s Pause
        - «+»-Knopf entfernt
    - Gemeinsame Hilfsfunktionen (vollerName, personKey, parseNotizen)
        - Zentrale utils.js
        - Copy-Paste beseitigt

- 2026-05-27 **1.3.0**
    - Ungültige HTML-Struktur in Geschäfts-Detail behoben
    - Fehler beim Laden der Kommissionsliste behoben
    - Teststabilität verbessert (console.error → Testfehler)
    - PHPUnit-Notices in Live-Scraper-Tests behoben

- 2026-05-27 **1.2.9**
    - WebSocket-Verbindung funktioniert wieder
        - Nginx-Pfad korrekt konfiguriert
        - Container-Servicename angepasst
    - Nextcloud bleibt nicht im Wartungsmodus hängen
    - Beschlusstext speichert beim Feldverlust, nicht sofort

- 2026-05-26 **1.2.8**
    - Synchronisations-Zuverlässigkeit verbessert
        - Fehler während Sync führen keine falschen Löschungen
    - Traktanden einzeln abgeglichen statt neu angelegt
        - Bestehende Notizen bleiben
    - Gelöschte Objekte automatisch bei nächster Sync wiederhergestellt
    - Migrationen beim Start zuverlässig ausgeführt
    - Notizen/Beschlüsse mit Maus verschiebbar (nur über ⠿-Symbol)
    - Beschluss-Widget überall gleich, Freitext speichert automatisch
    - Formular-Beschriftungen einheitlich

- 2026-05-24 **1.2.7**
    - Interne Fraktionssitzungen in Liste
        - Mit Titel, Zweck, Traktanden, Notizen
    - Erstellungsformular im NC-Kalender-Stil
    - Interne Sitzungen gekennzeichnet
    - Parlamentssync löscht interne Sitzungen nicht
    - «Fraktionsmitglieder ↔ NC-User»: auch inaktive Personen anzeigen

- 2026-05-22 **1.2.6**
    - Fehler beim Erstellen Sitzung aus Vorlage behoben

- 2026-05-21 **1.2.5**
    - Sitzungstyp-Formular vollständig funktionsfähig
    - Notizen pro Sitzung (Protokollführung)
    - Kommissionen per Suchfeld findbar
    - Dokumente direkt aus Tool erstellen

- 2026-05-24 **1.2.4**
    - Notizen und Beschlüsse speichern automatisch
        - Kein «Speichern»-Knopf mehr
        - Bei Fokusverlust oder nach 5s Pause
    - Beim Speichern flimmert nichts
    - Offene Popups bleiben offen bei gleichzeitiger Arbeit
    - Zeitleiste zeigt «Von: X → Nach: Y»
    - Traktanden-Tabelle: Karten-Layout auf schmalen Bildschirmen
    - Dokument-Links (PDF) direkt beim Traktandum

---

### Ältere Versionen

Siehe Git-History für Details zu Versionen vor 1.2.4.
