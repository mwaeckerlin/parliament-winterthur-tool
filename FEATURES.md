# Funktionen

Das Parlament-Winterthur-Tool führt die ganze Arbeit einer Fraktion an einem Ort: alle öffentlichen Daten des Parlaments, automatisch nachgeführt, und dazu die Notizen, Zuständigkeiten und Entscheide der Fraktion, die nur die Fraktion sieht.

Jede Funktion trägt eine feste Nummer (`**F1**`, `**F2**`, …). Die Nummern werden einmal vergeben und nie wiederverwendet; jeder Test in [TESTS.md](TESTS.md) nennt die Funktionsnummer, die er abdeckt.

## Immer aktuelle Parlamentsdaten

- **F1** Alle Geschäfte, Sitzungen, Traktanden, Mitglieder, Kommissionen und Fraktionen des Winterthurer Parlaments werden nach Zeitplan automatisch übernommen, standardmässig um 10:00 und 18:00 (F60), samt den Einreichern mit ihren Rollen (Erst- und Mitunterzeichner) und dem Verlauf jedes Geschäfts im Parlament.
- **F2** Nichts geht verloren: Was auf der Parlamentswebseite verschwindet, bleibt im Tool erhalten (als historisch markiert) und kehrt bei Wiedererscheinen automatisch zurück. Eigene Geschäfte, interne Sitzungen und alle Fraktionsdaten bleiben von der Synchronisation unberührt; als erledigt markierte Geschäfte werden nicht wieder «aufgemacht».
- **F3** Die Synchronisation lässt sich jederzeit auch von Hand starten und zeigt ihren Fortschritt laufend an: aktueller Bereich, Anzahl verarbeiteter von gesamten Einträgen, verstrichene Zeit und geschätzte Restzeit. Sie kann abgebrochen werden. Bricht ein Lauf ab oder scheitert er, erscheint eine Fehler- oder Abbruchmeldung mit dem Grund; der nächste Start setzt dort fort, wo der Lauf stehen geblieben ist. Es läuft immer nur eine Synchronisation. Wer während eines Laufs startet, sieht den Fortschritt dieses Laufs, und ein zweiter Lauf beginnt nicht. Den Fortschritt sieht nur der Administrator in den Einstellungen.

## Aufbau und Bedienung

- **F4** Ansichten in der Seitenleiste, in dieser Reihenfolge: Geschäfte, Sitzungen, Kommissionen, Stadtratsbeschlüsse, Vorstösse, Budget, Fragestunde, Mitglieder, Sitzungstypen, Protokoll, Bedienungsanleitung, Änderungsverlauf. Über den Ansichten steht die Suche der jeweiligen Ansicht, unter ihnen die Filter, ganz unten die Version der App.
- **F5** Aussehen und Bedienung wie die übrigen Nextcloud-Apps, zum Beispiel «Dateien». **Nichts geht über den Bildschirm hinaus:** Was nicht nebeneinander passt, bricht um; gescrollt wird nach unten, nie zur Seite. Auf dem Telefon wird dafür die Schrift einer Zahlentabelle kleiner, und der Kopf einer Karte stellt Titel und Betrag untereinander.
- **F6** Wer die App in dieser Nextcloud öffnen kann, sieht darin dieselben Daten: die öffentlichen Daten des Parlaments und die fraktionsinternen Notizen, Zuständigkeiten, Beschlüsse und Voten. Die App ist für die private Nextcloud der Fraktion gedacht; der Zugang lässt sich in den Nextcloud-Einstellungen auf die Fraktionsgruppe beschränken. Der gemeinsame Ordner, der Kalender und das Aufgabenboard sind ausschliesslich mit der konfigurierten Fraktionsgruppe geteilt. Einzelne Aktionen sind zusätzlich eingeschränkt: Eine Notiz ändert nur ihr Verfasser, ein Votum nur der Zuständige, einen Beschluss im Sitzungsmodus nur der Protokollführer oder seine aktive Stellvertretung, die Rollen vergeben die in F56 Genannten, und die Synchronisation startet nur der Administrator.
- **F7** Beim Bearbeiten speichert jede Eingabe sofort, und es gibt keine Knöpfe «Speichern» und «Abbrechen». Auswahlen speichern beim Wählen, Texte beim Verlassen des Feldes; eine kurze Bestätigung erscheint als gewohnte Nextcloud-Benachrichtigung. Dialoge schliessen mit ✕ ohne Datenverlust, weil alles bereits gespeichert ist. Ausgenommen sind die Notizen, die nur über «✓» speichern (F24), und das Anlegen (F8). Eingaben gehen nie verloren, auch nicht beim Klick auf die Werkzeugleiste eines formatierten Textbereichs.
- **F8** Neues wird immer gleich angelegt: Der Neu-Knopf öffnet **dieselbe Maske wie das Bearbeiten**, mit allen Feldern. Es gibt kein reduziertes Formular und keine später nachgereichten Felder. Anders als beim Bearbeiten wird beim Anlegen **noch nichts gespeichert**: Die Maske sammelt die Eingaben und zeigt unten **«Speichern»** und **«Abbrechen»**; «Speichern» ist gesperrt, solange die Pflichtangabe fehlt. Erst «Speichern» legt den Eintrag an, danach geht dieselbe Maske unmittelbar in die Bearbeitung über, sodass sich Dokumente und Notizen direkt anschliessen lassen.
    - **Ein Klick neben die Maske bewirkt beim Anlegen nichts.** Die Maske bleibt offen und behält alle Eingaben, damit keine ungewollt verloren geht. Verworfen wird ausschliesslich über «Abbrechen».
    - **Begründete Ausnahme:** Notizen, Dokumente und der Verlauf hängen an einem bereits bestehenden Eintrag und erscheinen deshalb erst nach dem Speichern; ein Hinweis in der Maske sagt das. Alles Übrige ist von Anfang an vorhanden.
    - Gibt es Vorlagen zur Auswahl (Sitzungstypen), öffnet der Neu-Knopf ein Menü mit je einem Eintrag pro Vorlage, wie «+ Neu» in der Ansicht «Dateien» von Nextcloud.
- **F68** Jede Änderung wird festgehalten: bei Notizen im Versionsverlauf, bei allem Übrigen in der Aktionszeitleiste des Geschäfts oder des Vorstosses.
- **F9** Bearbeitet wird durch Klick auf den Eintrag; jede Eingabe speichert sofort. Selbst angelegte Einträge (eigene Geschäfte, Vorstösse, Sitzungstypen) lassen sich über einen Löschen-Knopf wieder entfernen, mit Rückfrage vor dem Löschen. Geschäfte von der Parlamentswebseite lassen sich nicht löschen, weil die nächste Synchronisation sie wieder anlegen würde; sie werden über ihren Status abgeschlossen.
- **F10** Auswahllisten zeigen nur Aktive: aktive Fraktionen mit ihrer Abkürzung, aktive Mitglieder; Ansprechpartner nur aus der gewählten Fraktion.
- **F11** Lange Bezeichnungen erscheinen überall gekürzt, sobald ein Kürzel definiert ist (F63). Das gilt für Status, Partei-, Fraktions- und Kommissionsnamen, in Listen, Karten und Auswahlfeldern; gespeichert bleibt immer der volle Name.
- **F12** Auf schmalen Bildschirmen werden Tabellen zu übersichtlichen Karten.
- **F13** Geschäfte sind auch über die zentrale Nextcloud-Suche auffindbar.
- **F139** **Jeder Fehler im Server wird zu einer Meldung, die der Benutzer lesen kann.** Schlägt eine Aktion fehl, nennt die Meldung oben am Schirm, was nicht ging und warum, etwa «Auf eine künstliche Produktegruppe sind keine Anträge möglich». Was der Benutzer selbst richten kann, steht im Klartext da; ist der Eintrag inzwischen weg, sagt die Meldung, dass die Ansicht neu zu laden ist. Bei einem unerwarteten Fehler nennt sie eine achtstellige Kennung, unter der die Einzelheiten im Protokoll der Nextcloud-Installation stehen, und keine technischen Angaben aus dem Server. Eine Meldung wie «Request failed with status code 500» erscheint nie.

## Betrieb

- **F122** **Die Seite «Übersicht» der Nextcloud-Verwaltung zeigt nur die Warnungen, die der Container nicht beheben kann.** Die Seite liegt unter «Administration». Der Container richtet beim Start ein, was Nextcloud sonst bemängelt: Er trägt seinen internen Namen mit Port unter die vertrauten Domänen, damit der Server sich für die Selbstprüfungen erreicht (Datenverzeichnis, JavaScript-Module, Quellkarten, WebDAV, `.well-known`, Schriftarten); er legt die fehlenden Indizes der Datenbank an und führt die Migrationen der Dateitypen aus, die Nextcloud bei einer Aktualisierung auslässt; er sperrt die Dateien über den Zwischenspeicher statt über die Datenbank und trägt die Serverkennung ein. Der Webserver leitet zusätzlich `/ocm-provider/` auf `index.php` um. Offen bleiben die Punkte, die sich ohne HTTPS nicht lösen lassen, die Version der Datenbank und das Protokoll, das ein Container nach stderr schreibt. Der Start prüft ausserdem, ob jede Migration der App in dieser Installation gelaufen ist. Nextcloud führt eine Migration nur bei einer höheren Version aus, und ohne diese Prüfung arbeitete eine Installation unbemerkt mit dem alten Schema weiter. Fehlt eine Migration, hält der Container an und nennt sie.
- **F140** **Jeder Tag eines Images nennt seine Version mit führendem «v».** Eine Installation holt die drei Images wahlweise in der jeweils neuesten Fassung, auf eine Version festgelegt (etwa `nginx-v1.10.0`), auf eine Nextcloud-Linie festgelegt (`nginx-nc35`) oder auf beides (`nginx-nc35-v1.8.18`). Jede dieser Formen gibt es zusätzlich mit dem Datum des Baus am Ende, wenn genau ein Bau wiederholbar sein muss. Ein früheres Release bekommt die Korrekturen seiner Basis, etwa ein gepatchtes PHP, unter seinen eigenen Tags und auf seiner Nextcloud-Linie. Die Auslieferung bindet auf die Version, damit ein Neustart dieselbe Fassung holt, die beim Aufsetzen geprüft wurde. Einen Tag mit einer Version ohne «v» gibt es nicht. Wie ein Release neu gebaut wird und welche Datei den Tag nennt, steht im README unter «Images je Nextcloud-Linie».
- **F142** **Adresse der Ports wählbar.** Die Auslieferung zeigt die Ports 80 und 443 auf allen Adressen des Servers, IPv4 und IPv6, solange der Betreiber keine Adresse einstellt; eine bestehende Auslieferung bleibt so nach jedem Neustart von aussen erreichbar. Eine eingestellte Adresse beschränkt die Ports auf diese eine: `127.0.0.1` lässt nur den Server selbst zu, eine IPv6-Adresse steht in Klammern wie `[::1]`, `0.0.0.0` öffnet alle IPv4-Adressen und keine IPv6-Adresse. Die Anleitung zur Auslieferung und die Vorlage ihrer Einstellungen zeigen die Beschränkung als Möglichkeit und setzen sie nicht. Der Stack für die Entwicklung zeigt Nextcloud auf Port 29824 nach derselben Regel: auf allen Adressen des Rechners, solange keine Adresse eingestellt ist, sonst nur auf dieser. Die Einstellung steht im README unter «Konfigurationsdatei anlegen».
- **F143** **Die Images gibt es für amd64 und arm64, und jede Nextcloud-Linie trägt den neuesten Stand.** Jeder neue Stand des Hauptzweigs wird für beide Architekturen gebaut und veröffentlicht. Sobald das gelungen ist, entstehen die Linien für Nextcloud 33, 34 und 35 aus genau diesem Stand neu, und jede wird ebenso gebaut und veröffentlicht. Ändert sich ein Basis-Image oder eine heruntergeladene Datei oder ist ein Image eine Woche alt, baut der Bau innerhalb einer Stunde neu, auf dem Hauptzweig und auf jeder Linie; eine Sicherheitskorrektur in einem Basis-Image kommt so ohne neuen Stand des Projekts an. Der Ablauf steht im README unter «Images je Nextcloud-Linie».
- **F148** **Der Server läuft nach einem Absturz von selbst weiter, und zu viel Arbeit der App legt die Datenbank nicht still.** Jeder Dienst der Auslieferung startet nach einem Absturz und nach einem Neustart des Servers von selbst wieder; aus bleibt nur, was der Betreiber von Hand angehalten hat. Höchstens acht Anfragen arbeiten gleichzeitig, jede weitere wartet, bis eine fertig ist. Alles, was die App auf dem Server tut, die Anfragen, der Hintergrundauftrag und die Synchronisation, belegt zusammen höchstens 1280MiB. Eine einzelne Anfrage darf bis zu 1000MiB belegen, der Hintergrundauftrag und die Synchronisation nach F89 bis zu 1024MiB; die gemeinsame Grenze liegt 256MiB darüber, damit PHP an seiner eigenen Grenze eine Meldung schreibt, bevor das System den Prozess beendet. Wird es mehr als 1280MiB, beendet das System einen Prozess der App: Die Anfrage, die er gerade beantwortete, endet mit einem Fehler, die Datenbank und alle übrigen Dienste laufen weiter. Eine Anfrage darf bis zu einer Stunde rechnen, damit das Einlesen eines Budgetbuchs fertig wird. Die Werte passen auf einen Server mit 1,9GiB Speicher; der Betreiber kann jeden einstellen, und die Vorlage der Einstellungen nennt ihn mit seinem Standard und seiner Wirkung. Die Einstellungen stehen im README unter «Speicher und Neustart».

## Geschäfte

- **F14** Übersicht aller Geschäfte mit Nummer, Datum, Typ, Titel (mit Link auf die Parlamentswebseite), Einreichern, Priorität, Status (mit den Kürzeln aus F63), Zuständigkeit und letztem Fraktionsbeschluss. Standardmässig nach Datum absteigend geordnet (neueste zuerst); ein Klick auf die Spaltenüberschrift Nummer, Titel oder Status sortiert danach aufsteigend, ein erneuter Klick auf dieselbe Spalte kehrt die Richtung um. Die Status-Spalte ist ausgeblendet, wenn genau ein Status gefiltert ist.
- **F15** Priorität, Zuständigkeit und Beschluss lassen sich direkt in der Zeile ändern, ohne das Geschäft zu öffnen.
- **F16** Filter: Entscheidungsbedarf, Status, Typ, Zuständigkeit, Beschluss und Priorität (jeweils mehrfach wählbar); erledigte Geschäfte sind standardmässig ausgeblendet und per Schalter einblendbar; Suche über Nummer und Titel; «Filter zurücksetzen». **Grundprinzip für jeden Filter der App:** Ein Filter bietet genau die Werte an, die in den Daten tatsächlich vorkommen, also keine leeren Kategorien und keine Mitglieder, denen nie etwas zugewiesen wurde. Jeder andere Wert ergäbe eine leere Trefferliste.
- **F17** Geschäfte mit hoher Priorität sind dezent hervorgehoben, solche mit tiefer abgeschwächt; nicht gesetzte Priorität zählt wie mittel und zeigt «—». Die farbliche Hervorhebung gilt in **allen Ansichten**, in denen ein Geschäft erscheint: in der Ansicht «Geschäfte» (Tabelle und Karten), bei den Vorstössen und bei den Traktanden einer Sitzung.
- **F18** Eigene Geschäfte ausserhalb des Parlamentsregisters lassen sich anlegen. «+ Eigenes Geschäft» öffnet die vollständige Geschäftsmaske, dieselbe wie bei jedem anderen Geschäft (F8). Erst «Speichern» legt das Geschäft an, danach kommen Notizen und Dokumente dazu; «Abbrechen» verwirft die Eingaben, und es bleibt kein Eintrag zurück.
- **F65** Bei einem selbst angelegten Geschäft lassen sich Titel, Typ, Status und Datum direkt in der Geschäftsmaske ändern; jede Eingabe wird sofort gespeichert. Bei Geschäften von der Parlamentswebseite sind diese Angaben nur lesbar, weil die nächste Synchronisation sie aus der Quelle überschreiben würde. Ein unvollständiges Datum lehnt die Maske mit einem Hinweis ab.
- **F66** Ein selbst angelegtes Geschäft lässt sich wieder löschen. Geschäfte von der Parlamentswebseite lassen sich nicht löschen, weil die nächste Synchronisation sie wieder anlegen würde.
- **F70** Ein selbst angelegtes Geschäft lässt sich mit dem offiziellen Parlamentsgeschäft verknüpfen, sobald dieses nach der Synchronisation erscheint, so wie ein Vorstoss mit einem Geschäft verknüpft wird (F33). In der Maske eines eigenen Geschäfts steht dafür in der fraktionsinternen Bearbeitung die Zeile «Offizielles Geschäft» mit dem Knopf «Mit offiziellem Geschäft verknüpfen»; er öffnet einen Auswahldialog mit Suchfeld (Platzhalter «Nr. oder Titel») und einer Liste, die die Geschäfte nach Titel-Ähnlichkeit zum eigenen Geschäft vorschlägt (ähnlichste zuerst, bei Gleichstand die neuesten). Angeboten werden nur offizielle Geschäfte, auch erledigte, weil das gesuchte offizielle Geschäft bereits erledigt sein kann; eigene Geschäfte und das Geschäft selbst sind ausgeschlossen; gibt es keine, erscheint «Keine Geschäfte gefunden». Nach der Wahl:
    - Das eigene Geschäft wird als «erledigt» abgeschlossen und ist mit dem offiziellen Geschäft verknüpft. An Stelle des Knopfes steht der Hinweis «Mit dem offiziellen Geschäft ‹Nummer› «‹Titel›» verknüpft und abgeschlossen», wobei Nummer und Titel ein **anklickbarer Verweis** sind, der das offizielle Geschäft öffnet. (Ist das Ziel ausnahmsweise nicht mehr auffindbar, steht nur «Mit einem offiziellen Geschäft verknüpft und abgeschlossen».)
    - **Notizen und alle weiteren Angaben gehen ins offizielle Geschäft über, soweit sie dort noch fehlen:** Die Notizen (reguläre und Sitzungsnotizen, samt Versionsverlauf) werden ans offizielle Geschäft übertragen und erscheinen dort in dessen Notizliste; Priorität, Typ, Kommission, Datum, Inhalt und die Zuständigkeit werden nur dann übernommen, wenn das offizielle Geschäft im jeweiligen Feld noch leer ist; bereits gesetzte Angaben des offiziellen Geschäfts bleiben unverändert.
    - Die beiden Geschäfte sind danach **gegenseitig verlinkt und beidseitig anklickbar:** Am offiziellen Geschäft erscheint der Bereich «Verknüpfte eigene Geschäfte» (nur wenn mindestens ein eigenes Geschäft darauf verweist) mit je Eintrag dem anklickbaren Titel (öffnet das eigene Geschäft) und, falls gesetzt, dessen Priorität. Vom eigenen Geschäft führt der oben genannte Verweis zurück zum offiziellen. Bei einem Geschäft von der Parlamentswebseite gibt es den Knopf «Mit offiziellem Geschäft verknüpfen» nicht (nur eigene Geschäfte lassen sich verknüpfen). Fehler beim Verknüpfen: «Verknüpfung fehlgeschlagen: …».

- **F71** In der Ansicht «Geschäfte» lässt sich zusätzlich nach **Einreicher** filtern, mit je einem Filter für die Person («Einreicher») und für die Partei («Partei»), beide als Mehrfachauswahl mit Platzhalter «Alle», im Filterbereich nach dem Prioritäts-Filter.
    - **Einreicher:** Auswahl aller Personen, die bei irgendeinem Geschäft als Einreicher (Erst- oder Mitunterzeichner) vorkommen, alphabetisch sortiert. Ist mindestens eine Person gewählt, erscheinen nur Geschäfte, bei denen eine der gewählten Personen Einreicher ist.
    - **Schalter «Nur Ersteinreicher»** (direkt unter dem Filter «Einreicher», standardmässig aus): Eingeschaltet zählt nur der Erstunterzeichner, der erste Einreicher eines Geschäfts. Es erscheinen dann nur Geschäfte, deren Erstunterzeichner eine der gewählten Personen ist; wer nur mitunterzeichnet hat, genügt nicht. Der Schalter wirkt ausschliesslich auf den Filter «Einreicher».
    - **Partei:** Auswahl aller Parteien, die als Einreicher tatsächlich vorkommen, alphabetisch sortiert. Die Partei eines Einreichers wird über die Parlamentsmitglieder aufgelöst (Personen-ID der Webseite, sonst über den Namen). Ist mindestens eine Partei gewählt, erscheinen nur Geschäfte, bei denen ein Einreicher zu einer der gewählten Parteien gehört.
    - «Filter zurücksetzen» leert auch diese beiden Filter und schaltet «Nur Ersteinreicher» wieder aus.

- **F72** An jeder Stelle mit Dokumenten (eigenes und offizielles Geschäft, Vorstoss, Sitzung) steht dieselbe Dokument-Komponente. Neben «+ Neues Dokument» und «Hochladen» gibt es den Knopf **«Verknüpfen»**. Er öffnet den Dateiauswahldialog von Nextcloud, der im Jahresordner des Objekts startet und frei navigierbar ist, und nimmt eine **bestehende** Datei in die Dokumentliste auf, gleich wo sie liegt und wie sie heisst. Verknüpfte Dokumente sind als solche gekennzeichnet und lassen sich mit «✕» wieder lösen; die Datei selbst bleibt bestehen. Die Dokumente liegen in Ordnern nach Jahr (…/{Jahr}/…).

- **F73** Eine Sitzung lässt sich mit Geschäften und mit **Vorstössen** verknüpfen, eigenen wie fremden; so werden sie an der Sitzung traktandiert. Im aufgeklappten Sitzungsdetail steht dafür direkt unter «Verknüpfte Geschäfte» der Bereich «Verknüpfte Vorstösse» mit der Auswahlliste «Vorstoss verknüpfen…»; bereits verknüpfte Vorstösse werden ausgefiltert und lassen sich je mit «✕» wieder lösen. Jeder Vorstoss erscheint mit Titel und Art.
- **F19** Ein Klick öffnet das Geschäft mit allen öffentlichen Informationen und der fraktionsinternen Bearbeitung:
    - **F20** **Priorität** (hoch, mittel oder tief, abwählbar).
    - **F21** **Zuständigkeit:** mehrere Zuständige, der erste ist der Hauptzuständige; zur Auswahl stehen aktive Mitglieder zuerst, ehemalige gekennzeichnet darunter. Wer in der zuständigen Kommission sitzt, wird nach der Synchronisation automatisch eingetragen (ohne bestehende Zuweisungen zu überschreiben); haben Fraktionsmitglieder das Geschäft selbst eingereicht, werden sie in Einreicher-Reihenfolge zuständig. **Die zuständige Kommission steht im Status des Parlaments oder im Feld «Kommission».** Es gilt dieselbe Regel wie in der Ansicht «Kommissionen» (F46), damit ein Geschäft an beiden Stellen zur selben Kommission gehört.
    - **F22** **Fraktionsbeschluss:** Auswahl aus genau den zum Geschäftstyp passenden Beschlüssen. Die Auswahl je Typ:
        - **Motion:** Zustimmen, Ablehnen, Stimmfreigabe, Miteinreichen als Fraktion, Miteinreichen einzelne Personen, Überweisung befürworten, Überweisung ablehnen, Erheblich erklären, Abschreiben.
        - **Postulat:** Zustimmen, Ablehnen, Stimmfreigabe, Miteinreichen als Fraktion, Miteinreichen einzelne Personen, Überweisung befürworten, Überweisung ablehnen, Kenntnisnahme positiv, Kenntnisnahme negativ, Nachbericht verlangen, Abschreiben.
        - **Interpellation, Schriftliche Anfrage, Bericht:** Zustimmen, Ablehnen, Stimmfreigabe, Kenntnisnahme positiv, Kenntnisnahme negativ.
        - **Initiative:** Zustimmen, Ablehnen, Stimmfreigabe, Überweisung befürworten, Überweisung ablehnen.
        - **Wahlen, Vorlagen und jeder sonstige oder unbekannte Typ:** Zustimmen, Ablehnen, Stimmfreigabe.
        - Ist das Geschäft als erledigt markiert, kommen bei jedem Typ zusätzlich Kenntnisnahme positiv und Kenntnisnahme negativ dazu. Alternativ jederzeit ein freier Beschlusstext ohne vorgegebene Auswahl. Ein Beschluss lässt sich zurücknehmen (er bleibt als «Beschluss zurückgenommen» in der Zeitleiste); einen Beschlusstext weiterbearbeiten kann nur, wer ihn erfasst hat.
    - **F23** **Entscheidungsbedarf:** Jedes Geschäft hat aus Fraktionssicht einen von drei Zuständen: «offen» (noch kein Fraktionsbeschluss), «neu zu entscheiden» (das Parlament hat das Geschäft nach dem letzten Fraktionsbeschluss verändert) oder «entschieden» (es liegt ein Beschluss vor und seither keine Änderung des Parlaments). «Offen» und «neu zu entscheiden» brauchen einen Entscheid; der Filter «Entscheidungsbedarf» zeigt sie mit der Auswahl «Nur Entscheid nötig».
    - **F24** **Notizen** mit Formatierung (fett, kursiv, unterstrichen, durchgestrichen, Überschriften, Listen, Zitat, Code, Links, Rückgängig und Wiederholen). Jede frühere Fassung bleibt erhalten: durch die Versionen blättern und eine ältere Fassung als neue aktuelle übernehmen; der Verlauf bleibt vollständig. Bearbeiten, Löschen und Wiederherstellen einer Notiz kann nur ihr Verfasser; für andere ist sie sichtbar, aber nicht änderbar. Gespeichert wird nur über das Häkchen «✓», weder automatisch noch beim Verlassen des Feldes; bei ungespeicherten Änderungen wird beim Verlassen gewarnt. Gelöschte Notizen verschwinden nicht endgültig: In der Aktionszeitleiste erscheint «… hat seine Notiz gelöscht», und der Verfasser kann sie samt Verlauf wiederherstellen.
    - **F25** **Sitzungsnotizen:** Notizen, die man in einer Sitzung zu einem mit dieser Sitzung verknüpften Geschäft erfasst, gehören zum Geschäft und nicht zur einzelnen Sitzung. Sie erscheinen darum automatisch bei jeder aktuellen und künftigen Sitzung, an der dasselbe Geschäft hängt, auch wenn das Geschäft in der ursprünglich geplanten Sitzung nicht behandelt und auf eine spätere verschoben wird. Im Geschäft selbst stehen die Sitzungsnotizen separat unter den normalen Notizen, aufklappbar und standardmässig eingeklappt; sie verhalten sich sonst wie normale Notizen (Formatierung, Versionsverlauf, Löschen mit Wiederherstellung).
    - **F26** **Votum im Rat:** eigener formatierter Text pro Geschäft (nur der Zuständige darf ihn erfassen und bearbeiten; für alle anderen erscheint das Feld nur, wenn ein Votum vorliegt), als druckbares PDF speicherbar. Das PDF öffnet in einem eigenen Tab, löst den Drucken-Dialog automatisch aus und trägt: Titel «Votum im Rat» und den Geschäftstitel; darunter Geschäftsnummer, Zuständige der Fraktion (der Hauptzuständige mit dem Zusatz «Hauptverantwortung»), den letzten Fraktionsbeschluss mit Datum, «Erfasst von» und den Stand (Datum und Zeit); dann den Abschnitt «Wortlaut» mit dem formatierten Votumstext (oder «— Noch kein Votum erfasst —», wenn leer) und eine Fusszeile mit Geschäftsnummer und Ausdruckdatum. **Das PDF zeigt die Formatierung so, wie sie im formatierten Textbereich erfasst wurde:** Fettschrift, Kursives, Absätze, Überschriften, Listen und Zitate. Der Textbereich speichert Markdown, und das PDF wandelt es beim Drucken um. Ein Votum lässt sich archivieren; es bleibt dann als historischer Eintrag in der Zeitleiste und ein neues Votum kann begonnen werden.
    - **F27** **Dokumente:** aus Vorlagen (Word, Excel, PowerPoint, OpenDocument, Markdown, Text) erstellen, hochladen oder aus dem Fraktionsordner wählen; der Dateiname ist mit dem Titel vorbelegt (Leerzeichen werden zu Unterstrichen, der Cursor steht am Ende für eine Ergänzung wie «-rede»).
    - **F28** **Aktionszeitleiste:** alle Notizen, Beschlüsse («Von: X → Nach: Y»), Voten und Zuweisungen mit Person, Datum und Uhrzeit; per Griff (⠿) umsortierbar.
    - **F29** **Verknüpfte Vorstösse** werden mit Haltung, Zuständigkeit und Notizen angezeigt.
    - **F121** **Amtliche Dokumente mit ihrem Inhalt.** Die Synchronisation liest jedes Dokument, das an einem Geschäft hängt (Vorstoss, Antrag und Antwort des Stadtrats, Beilagen, Medienmitteilung), zerlegt es in Titel, Überschriften, Absätze und Listen und legt es als Struktur und als Markdown ab. Wer ein Geschäft liest, findet seinen Inhalt so im Tool und muss das PDF nicht beim Parlament herunterladen. In der Ansicht «Geschäfte» klappt das in zwei Stufen auf: Am Geschäft steht ein Dreieck, das die Liste seiner Dokumente mit Titel, Kategorie, Datum und Seitenzahl zeigt, und ein Klick auf einen Titel zeigt das ganze Dokument, so wie bei den Mitgliedern einer Kommission. Titel und Datum stehen links beieinander, der Titel fett. Im geöffneten Geschäft steht dieselbe Liste unter «Amtliche Dokumente». Die Suche greift in diesen Inhalt: Ein Wort, das nur im PDF steht, findet das Geschäft, in der Ansicht «Geschäfte» wie in der Suche von Nextcloud. Gelesen wird über dieselben Positionsdaten wie beim Budgetbuch und beim Drehbuch, darum bleiben auch **Tabellen** Tabellen: Spalten entstehen aus den x-Positionen der Textstücke, rechtsbündige Zahlen kommen in ihre Spalte, und eine Kopfzeile über mehreren Zeilen gehört zu ihrer Tabelle. Zwei Teile eines Dokuments stehen in der Struktur, aber nicht in der Anzeige: die **kursiv wiederholte Anfrage** in der Antwort des Stadtrats, die schon im Vorstoss selbst steht, und die **Rückseite des Vorstosses** mit der Liste der Unterzeichner. Ein Dokument, das sich nicht lesen liess, nennt den Grund und den Verweis auf das PDF und bleibt nicht leer; gelesen wird nur, was sich seit dem letzten Mal geändert hat. Ein neues Dokument steht sofort mit Titel, Kategorie und Datum am Geschäft, sein Inhalt kommt kurz darauf dazu: Die Synchronisation liest je Lauf nur eine begrenzte Zahl von Dokumenten, damit sie nicht stundenlang läuft, und ein stündlicher Auftrag holt den Rest nach. Jedes Dokument wird in einem eigenen Prozess gelesen, weil das Entpacken eines PDF beliebig viel Speicher braucht und eine überschrittene Speichergrenze den Prozess ohne abfangbaren Fehler beendet. Endet der Prozess eines Dokuments so, trägt dieses Dokument den Grund, und alle übrigen werden weiter gelesen.
    - **F149** **Die Liste der Geschäfte kommt ohne den Text der Dokumente.** Von jedem amtlichen Dokument kommen mit der Liste nur Titel, Kategorie, Datum, Seitenzahl, der Verweis auf das PDF und die Angabe, ob sein Inhalt gelesen ist. Der Text kommt erst, wenn jemand ein Dokument aufklappt, und dann nur der Text dieses einen Dokuments; bis er da ist, steht an seiner Stelle das Ladesymbol, und ein zweites Aufklappen holt nichts mehr, auch wenn es vor der ersten Antwort kommt. Kommt der Text nicht, steht «Der Inhalt dieses Dokuments liegt nicht gelesen vor.» mit dem Verweis auf das PDF da. Trägt das Dokument den Grund, warum es sich nicht lesen liess (F121), steht dieser Grund an Stelle des Satzes, und der Text wird gar nicht erst geholt. Ein Dokument kommt nur über das Geschäft, an dem es hängt: Wer die Nummer eines Dokuments zu einem anderen Geschäft angibt, bekommt «Dokument nicht gefunden».

## Vorstösse

- **F30** Eigene und fremde politische Vorstösse als Tabelle oder als klickbare Karten (F119) mit Herkunft, Art, Status, Zuständigkeit und bei fremden mit Herkunftsfraktion und eigener Haltung; Filter nach Herkunft und Status, beide standardmässig auf «Alle» und je nur mit den Werten, die bei den geladenen Vorstössen tatsächlich vorkommen (Grundprinzip aus F16; feste Reihenfolge «Eigene», «Fremde» und «Neu», «Entwurf», «Bereit», «Eingereicht», «Erledigt», «Pausiert»); Suche über Titel, Art und Zuständigkeit; Prioritäts-Hervorhebung wie bei den Geschäften. Ohne Treffer erscheint «Keine Vorstösse vorhanden».
- **F31** «+ Neuer Vorstoss» öffnet dieselbe vollständige Maske wie das Bearbeiten (F8). Vorausgewählt sind der Erfasser als Zuständiger, die Herkunft «Eigene» und der Status «Neu». Erst «Speichern» legt den Vorstoss an und ist gesperrt, solange der Titel fehlt; danach kommen Dokumente, Notizen und Verlauf dazu. «Abbrechen» verwirft die Eingaben, und es bleibt kein Eintrag zurück.
- **F67** Ein Vorstoss lässt sich über seine Karte löschen; vorher wird zur Bestätigung gefragt.
- **F119** **Die Ansicht «Vorstösse» ist gebaut wie die Ansicht «Geschäfte».** Auf breiten Fenstern steht eine Tabelle mit sechs Spalten: **Art** (darunter Herkunft und Datum), **Titel** (darunter die Herkunftsfraktion, wenn der Vorstoss von einer fremden stammt), **Prio**, **Status**, **Zuständig** und **Beschluss**. Priorität, Zuständigkeit und Beschluss lassen sich direkt in der Zeile ändern und speichern sofort; ein Klick in den freien Bereich der Zeile öffnet den Vorstoss mit allen Details. Wird das Fenster schmäler, als die Spalten brauchen, wechselt die Ansicht bei derselben Breite wie die Ansicht «Geschäfte» auf die Karten.
- **F32** In der Bearbeitung: Art aus den bekannten Vorstoss-Arten (Motion, Postulat, Interpellation, Schriftliche Anfrage, Beschlussantrag, Dringliche Motion, Dringliches Postulat, Budgetmotion, Fragestunde, Einzelinitiative, Parlamentarische Initiative), als Vorschlag, der sich mit eigenem Text überschreiben lässt; Herkunft (Eigene oder Fremde), Status (Neu, Entwurf, Bereit, Eingereicht, Erledigt, Pausiert), Priorität, Zuständigkeit (Liste der Mitglieder), Inhalt im formatierten Textbereich und Dokumente. Die Notizen verhalten sich wie beim Geschäft (F24): dieselbe Notizliste mit Formatierung, Versionsverlauf (durch die Fassungen blättern, eine ältere übernehmen), bewusstem Speichern über «✓» und Löschen mit Wiederherstellung durch den Verfasser. Bei fremden Vorstössen zusätzlich die eigene Haltung (Miteinreichen, Unterstützen, Stimmfreigabe, Ablehnen, Ablehnungsantrag stellen; ebenfalls frei überschreibbar), die Herkunftsfraktion und deren Ansprechpartner.
- **F33** Ein Vorstoss lässt sich durch Verknüpfung mit einem Geschäft abschliessen: Ein Dialog listet die Geschäfte, oben zuerst jene, die die meisten Wörter (ab drei Buchstaben) mit dem Vorstoss-Titel gemeinsam haben; bei gleich vielen gemeinsamen Wörtern steht das neuere Geschäft zuerst. Ein Suchfeld grenzt die Liste über Titel und Nummer ein; ohne Treffer erscheint «Keine Geschäfte gefunden.» Nach der Wahl übernimmt das Geschäft die Priorität des Vorstosses und der Vorstoss gilt als erledigt. Existiert das gewählte Geschäft nicht mehr, bleibt die Verknüpfung trotzdem bestehen; nur die Prioritäts-Übernahme entfällt dann ohne Meldung.
- **F34** Dokumente im Fraktionsordner «40_Vorstösse» werden automatisch als Vorstösse übernommen, ohne Duplikate.
- **F35** In der Bearbeitung gibt es wie beim Geschäft eine Aktionszeitleiste, die die zeitlich protokollierten Aktionen mit Person, Datum und Uhrzeit anzeigt und per Griff (⠿) umsortierbar ist.

## Sitzungen

- **F36** Alle Sitzungen chronologisch mit Datum, Zeit, Titel, Ort und externem Link; kommende zuerst (aufsteigend), vergangene danach (absteigend) und abgeschwächt dargestellt, interne Fraktionssitzungen gekennzeichnet. Der Schalter «Nur zukünftige» ist standardmässig aktiv (es sind also zunächst nur kommende Sitzungen sichtbar); dazu eine Suche. Ohne Treffer erscheint «Keine Sitzungen gefunden».
- **F37** Neue interne Sitzungen entstehen aus einer Vorlage (Sitzungstyp): «+ Neue Sitzung» steht links im Kopf und öffnet ein Menü mit je einem Eintrag pro Sitzungstyp, wie «+ Neu» in der Ansicht «Dateien» von Nextcloud. Ein Klick auf einen Eintrag öffnet unmittelbar das Formular für genau diesen Typ. Ist kein Sitzungstyp definiert, steht im Menü ein nicht anwählbarer Hinweis, zuerst unter «Sitzungstypen» einen Typ anzulegen. Im Formular: Titel, Datum (eine Woche voraus vorbelegt), Zeit und Ort aus der Vorlage, Zweck, Traktanden (umsortierbar) und Teilnehmer: einzelne Mitglieder, ganze Fraktionen oder Kommissionen, Gruppen und Benutzer von Nextcloud und die Rollen der Fraktion.
- **F38** In jeder Sitzung: Notizen, Dokumente (im Jahresordner, nach Datum benannt), verknüpfte Geschäfte (hinzufügen und lösen), Aufgaben direkt im Aufgabenboard der Fraktion (F50) und die Traktanden mit Dokument-Links, Bemerkungen und eigenen Notizen; Traktanden mit Geschäftsbezug öffnen das Geschäft. Die Notizen zur Sitzung nutzen **dieselbe Notiz-Komponente und dasselbe Aussehen** wie überall (aufklappbare Liste, «+ Neue Notiz», Versionsverlauf, Löschen).
- **F39** Bei einem Traktandum mit Geschäftsbezug wird die Notiz als Sitzungsnotiz am Geschäft gespeichert (F25). Sie erscheint dadurch bei jeder aktuellen und künftigen Sitzung, an der dieses Geschäft hängt, und im Geschäft selbst. Ein Traktandum ohne Geschäftsbezug hat eigene Notizen, die zum Traktandum gehören, mit **derselben Notiz-Komponente und demselben Aussehen** wie überall (aufklappbare Liste, «+ Neue Notiz», Versionen, Löschen). Eine gelöschte Notiz eines solchen Traktandums lässt sich nicht wiederherstellen, weil dort keine Aktionszeitleiste steht.
- **F40** Sitzungen lassen sich verknüpfen, zum Beispiel eine Kommissionssitzung mit der Fraktionssitzung, die sie vorbereitet: Die Notizen der verknüpften Sitzungen erscheinen gesammelt; Entkoppeln lässt alle Notizen an ihrem Platz. Die andere Sitzung zeigt die neue Verknüpfung und das Entkoppeln ohne Neuladen.
- **F115** **Jedes Protokoll ist mit einem Klick erreichbar.** Es öffnet in einem neuen Fenster, und der Link steht an beiden Stellen, an denen man es sucht. Oben auf der Karte jeder Parlamentssitzung steht der Link «Protokoll» und führt zum Protokoll genau dieser Sitzung; sein Hinweistext ist der Titel, den die Parlamentswebseite dem Protokoll gibt. Der Klick klappt die Karte nicht auf oder zu. Solange das Parlament kein Protokoll dieser Sitzung veröffentlicht hat, fehlt der Link. In der Folgesitzung steht derselbe Link ein zweites Mal beim Traktandum, das das Protokoll abnimmt («Abnahme Parlaments-Protokolle»): hinter dem Titel des Traktandums, beschriftet mit «Protokoll» und dem Datum der protokollierten Sitzung («Protokoll 10.11.2025»), mit dem Titel des Protokolls als Hinweistext. Er führt auf das abgenommene Protokoll der protokollierten Sitzung; solange nur der Entwurf vorliegt, den das Parlament zur Abnahme stellt, führt er auf diesen. Trägt ein Traktandum kein Protokoll, steht dort kein solcher Link.
- **F41** Jede Sitzung erscheint automatisch im gemeinsamen Fraktionskalender, mit Teilnehmern und optionaler E-Mail-Einladung; interne Sitzungen mit Zweck und Traktandenliste im Kalendereintrag; Protokoll-Link pro Sitzung.

## Sitzungstypen (Vorlagen)

- **F42** Vorlagen für wiederkehrende Sitzungen: Name, Zweck, Standard-Ort und -Zeit, Vorlage-Traktanden und Teilnehmer; Bearbeiten per Klick auf die Karte, Löschen mit Rückfrage.
- **F43** Ein Sitzungstyp kann «beratene» Kommissionen festlegen: Deren hängige Geschäfte werden mit künftigen Sitzungen dieses Typs automatisch verknüpft, sodass vor der Sitzung alles bereitsteht.

## Mitglieder

- **F44** Alle Parlamentarier mit Partei, Fraktion, Fraktionsrolle, Kommissionen (mit Funktion) und E-Mail; ehemalige gekennzeichnet.
- **F45** Sortierbar nach Funktion (Standard), Fraktion, Partei oder Name; filterbar nach Fraktion, Partei, Kommission und Funktion (Grundzustand «Alle Funktionen»); Suche über Name, Partei und E-Mail; der Schalter «Nur Aktive» ist standardmässig aktiv. Bei der Standard-Sortierung stehen zuerst der Fraktionspräsident, dann sein Stellvertreter, dann die Kommissionspräsidenten, dann die übrigen Kommissionsmitglieder und zuletzt alle anderen, innerhalb gleicher Funktion nach Partei und Name. Ohne Treffer erscheint «Keine Mitglieder gefunden».

## Kommissionen

- **F46** Alle Kommissionen mit Status, Beschreibung, ihren pendenten Geschäften (Klick öffnet das Geschäft) und ihren Mitgliedern (Funktion, Partei, Fraktion, E-Mail; die eigene Fraktion hervorgehoben). **Zu einer Kommission gehört ein Geschäft aus zwei Gründen:** Sein Status nennt sie («Bei Kommission … pendent», so schreibt es das Parlament), oder es ist ihr im Feld «Kommission» zugewiesen; so ordnet die Fraktion ihre eigenen Geschäfte zu, deren Status keine Kommission nennt.
- **F47** Zwei Schalter, beide standardmässig aktiv: «Nur aktive Kommissionen» und «Nur aktive Mitglieder» (ist letzterer aus, zeigt der Mitglieder-Titel zusätzlich die Zahl der aktiven Mitglieder an); dazu eine Suche. Ohne Treffer erscheint «Keine Kommissionen gefunden».
- **F118** **Die pendenten Geschäfte einer Kommission stehen offen da, die Mitglieder eingeklappt.** In der Ansicht «Kommissionen» brauchen Beschreibung, pendente Geschäfte und die Hinweise der Karte kein Aufklappen. Die Mitglieder stehen hinter einem Schalter, der ihre Zahl auch zugeklappt nennt («Mitglieder (10):») und sie auf Klick zeigt; ein zweiter Klick verbirgt sie wieder. Trifft die Suche ein Mitglied, klappt seine Kommission von selbst auf. Trifft sie ein Geschäft, bleiben die Mitglieder zugeklappt, weil das Geschäft ohnehin zu sehen ist.
- **F141** An jeder aktiven Kommission steht der Knopf **«Neues Geschäft»**, oben rechts auf ihrer Karte; eine aufgelöste oder inaktive Kommission, die bei ausgeschaltetem «Nur aktive Kommissionen» erscheint, trägt ihn nicht. Er öffnet dieselbe Maske wie «+ Eigenes Geschäft» in der Ansicht «Geschäfte», mit derselben Wirkung: Typ «Eigenes Geschäft», Status «Pendent», das heutige Datum, der Erfasser als Zuständiger, und dazu steht diese Kommission schon im Feld «Kommission». Nach «Speichern» bearbeitet dieselbe Maske das neue Geschäft, sodass sich Dokumente und Notizen gleich anschliessen lassen, und das Geschäft steht sofort in der Liste der pendenten Geschäfte dieser Kommission, ohne dass die Ansicht wechselt. Ein Klick neben die Maske verwirft beim Anlegen nichts (F8); vor dem Speichern schliesst nur «Abbrechen», danach das ✕. Name und Knopf stehen in einer Reihe, der Knopf oben rechts bei jeder Breite, auch am Telefon, und der Name beginnt oben im Kopf, am Schirm wie im Druck.

## Stadtratsbeschlüsse

Die Ansicht steht in der Navigation zwischen «Kommissionen» und «Vorstösse». Hier sieht ein Mitglied des Parlaments, was der Stadtrat entschieden hat. Der Stadtrat bereitet die Geschäfte des Parlaments vor und vollzieht dessen Beschlüsse, und seine veröffentlichten Beschlüsse liegen jedem Mitglied offen (`PARLAMENTSBETRIEB.md`).

- **F144** **Die Beschlüsse des Stadtrats stehen in einer eigenen Ansicht.** Je Beschluss zeigt die Karte:
    - die **Nummer** in der Form des Stadtrats («2026/1279») und daneben einen Pfeil «↗», der den Beschluss auf der Webseite der Stadt in einem neuen Tab öffnet,
    - den **Titel**,
    - **Beschlossen**: das Datum der Stadtratssitzung, als Tag.Monat.Jahr,
    - **Departement**: die zuständige Stelle mit dem Namen, den die Quelle nennt, etwa «Stadtkanzlei», «Präsidiales», «Finanzen», «Bau und Mobilität», «Schule und Sport» oder «Soziales»,
    - **Sitzung**: die Stadtratssitzung, in der der Beschluss gefasst wurde, mit dem Datum, das die Quelle nennt,
    - **Veröffentlicht**: das Datum, an dem die Stadt den Beschluss publiziert hat,
    - **Dokumente (n)**: je Dokument seine Beschriftung als Verweis, der das PDF der Stadt in einem neuen Tab öffnet, in der Farbe der übrigen Verweise wie die E-Mail in «Mitglieder»; ohne Dokument fehlt der Block,
    - **Notizen (n)**: eingeklappt, mit der Zahl der nicht gelöschten Notizen; ein Klick auf den Kopf zeigt sie, ein zweiter verbirgt sie wieder. Es ist dieselbe Notizliste wie am Geschäft und am Vorstoss, mit Versionen und Löschen (F24). Eine gelöschte Notiz lässt sich hier nicht wiederherstellen, weil die Karte keine Aktionszeitleiste trägt.
- **F145** **Suche und zwei Filter in der Seitenleiste.** Die Suche greift auf Nummer, Titel, Departement und Sitzung; ihr Platzhalter lautet «Nummer, Titel oder Departement». Die Auswahl «Alle Departemente» und die Auswahl «Alle Jahre» führen nur die Werte, die in den gelesenen Beschlüssen wirklich vorkommen, die Jahre mit dem jüngsten zuerst; «Filter zurücksetzen» leert die Suche und beide Auswahlen. Ohne Beschlüsse steht «Noch keine Stadtratsbeschlüsse» da, ohne Treffer «Kein Beschluss passt zur Suche». Die Zahl neben dem Titel nennt die Treffer.
- **F146** **Die Synchronisation holt die Beschlüsse von der Webseite der Stadt.** Sie liest sie in einem eigenen Abschnitt «Stadtratsbeschlüsse» und zeigt dessen Fortschritt wie bei den übrigen Abschnitten; auf der Kommandozeile kann der Administrator nur die Beschlüsse holen. Ein Beschluss wird an der Kennung in seiner Adresse auf der Webseite der Stadt wiedererkannt, etwa `20261279` für den Beschluss 2026/1279: Ein zweiter Lauf schreibt die Angaben der Quelle neu und legt keinen zweiten Eintrag an. Die Notizen der Fraktion bleiben bei jedem Lauf erhalten, weil die Synchronisation nur die Felder der Quelle schreibt. Ein Beschluss, den die Datenbank nicht annimmt, zählt als Fehler des Laufs und erscheint mit dieser Kennung in der Meldung der Verwaltung und im Protokoll; ein Titel über 500 Zeichen wird gekürzt und nicht verworfen. Antwortet die Quelle nicht oder liefert sie keinen einzigen Beschluss, endet nur dieser Abschnitt, und er meldet keinen leeren Lauf als Erfolg. Die übrigen Abschnitte laufen zu Ende, und die Meldung mit der Adresse der Quelle steht im Protokoll (F108) und im Fortschritt der Verwaltung; die Seite der Verwaltung zeigt sie unter der Statuszeile der Synchronisation als «Fehler im Bereich Stadtratsbeschlüsse: …». Kommt die Seite eines einzelnen Beschlusses nicht, behält ein gespeicherter Beschluss seine Dokumente und sein Datum der Veröffentlichung. Die Quelle führt die Beschlüsse auf Seiten mit je zwölf Einträgen, der jüngste zuerst; gelesen werden die ersten zehn Seiten, also die 120 jüngsten Beschlüsse. Im Stack für die Entwicklung lässt sich eine andere Zahl von Seiten einstellen, auch alle; die Auslieferung kennt diese Einstellung nicht. Der Befehl und die Einstellung stehen im README unter «Synchronisation» und «Automatischer E2E-Test». Die Ansicht zeigt höchstens die 500 jüngsten Beschlüsse, auch wenn die Synchronisation mehr gelesen hat. Nach dem Lauf steht die Liste ohne Neuladen da. Die Karten werden dabei an Ort und Stelle angeglichen, und offene Notizen bleiben offen. Mehrere Ereignisse kurz hintereinander laden einmal, das Fenster, das gespeichert hat, lädt nichts, und eine ältere Antwort ersetzt nie eine neuere.

## Gemeinsamer Fraktionsraum

- **F48** Ein geteilter Ordner «Fraktion» mit fester Nummern-Struktur erscheint automatisch bei allen Mitgliedern, auch bei später dazukommenden. Er enthält genau diese Ordner:
    - **00_Allgemein**
    - **10_Sitzungen** mit einem Unterordner je Jahr, etwa **2026**
    - **20_Geschäfte**
    - **30_Kommissionen** mit den Unterordnern **Aufsichtskommission** und **Sachkommission Bildung Sport Kultur**
    - **40_Vorstösse** mit den Unterordnern **10_Eigene** und **20_Fremde**
    - **50_Wahlkampf**
    - **60_Medien**
    - **90_Archiv**

    Jedes Mitglied darf in diesen Ordnern lesen, anlegen, ändern und löschen. Wird ein Ordner der Struktur umbenannt, kommen alle Inhalte in den umbenannten Ordner, und keine Datei geht verloren. Ein Fraktionsordner, den ein Mitglied selbst angelegt hat, bleibt als Sicherung «Fraktion.bak» erhalten, und sein Inhalt kommt in den offiziellen Ordner, ohne dass eine Datei verloren geht oder überschrieben wird; eine gleichnamige Datei liegt als «….migrated» daneben.
- **F49** Ein gemeinsamer Fraktionskalender, bei allen sichtbar und bearbeitbar.
- **F50** Ein gemeinsames Aufgabenboard «Fraktion» mit den Spalten «To-do», «In Arbeit» und «Erledigt».
- **F51** Neue Fraktionsmitglieder werden automatisch als Nextcloud-Benutzer geführt und per E-Mail eingeladen; niemand wird ohne Zustimmung des Administrators entfernt.

## Office-Integration

- **F52** Dokumente, Tabellen und Präsentationen lassen sich direkt im Browser öffnen und gemeinsam bearbeiten, aus jedem Bereich «Dokumente» des Tools heraus.

## Zusammenarbeit in Echtzeit

- **F53** Änderungen anderer Fraktionsmitglieder erscheinen sofort überall, ohne dass die Seite neu lädt und ohne die eigene Ansicht zu stören: Die Liste springt nicht, offene Fenster und angefangene Eingaben bleiben unangetastet.
- **F54** Das gilt für Geschäfte, Beschlüsse, Notizen, Sitzungen, Traktanden, Vorstösse, Stadtratsbeschlüsse samt ihren Notizen, Rollen und den Fortschritt der Synchronisation.
- **F55** Nur angemeldete Fraktionsmitglieder empfangen diese Aktualisierungen.
- **F105** **Robustes Nachladen bei schlechter Leitung:** Kann etwas nicht geladen werden (Netzwerkfehler, Zeitüberschreitung, überlasteter Server), versucht die App es automatisch wieder, bis es gelingt; nichts bleibt dauerhaft hängen. Die Wartezeit zwischen den Versuchen wächst schrittweise bis zu einer Obergrenze, damit der Server nicht überlastet wird. Ladevorgänge (Anzeigen) werden unbegrenzt wiederholt; Speicher- und Löschaktionen nur wenige Male und nur bei echten Netzwerkfehlern, damit keine Aktion doppelt ausgeführt wird. Fehler auf der eigenen Seite (etwa fehlende Berechtigung) werden nicht wiederholt. Ladeanzeigen erscheinen sofort, damit auch bei schlechter Leitung sichtbar ist, dass etwas geschieht.
- **F147** **Während der Sitzung bleibt die Anzeige stehen, wenn ein anderer etwas eingibt.** Wer in der Ansicht «Sitzungen» eine Sitzung offen hat, sieht die Eingaben der anderen erscheinen, ohne dass seine Traktandentabelle verschwindet; «Traktanden laden...» steht nur beim ersten Öffnen einer Sitzung da. Geladen wird im Hintergrund und nur das, was sich geändert hat:
    - eine neue oder geänderte Notiz an einem Traktandum ohne Geschäft: nur die Notizen dieses Traktandums,
    - eine Notiz an der Sitzung: nur die Notizen dieser Sitzung,
    - eine Sitzungsnotiz an einem Geschäft: nur dessen Sitzungsnotizen, und nur, wenn das Geschäft auf einer offenen Sitzung steht,
    - geänderte Angaben eines Traktandums oder eines Geschäfts: die Traktanden der offenen Sitzungen, auf denen es steht.

    Die neuen Angaben ersetzen die alten an Ort und Stelle: Die Liste springt nicht, und ein angefangener Text bleibt stehen. Kommen mehrere Änderungen kurz hintereinander, lädt die Ansicht einmal für alle. Das Fenster, in dem gespeichert wurde, lädt nichts nach, weil es die Änderung schon zeigt. In der Ansicht «Kommissionen» bleibt die Liste nach einer Synchronisation ebenso stehen, und die Ansicht «Geschäfte» lädt mehrere Änderungen ebenso in einer Runde.
- **F150** **«Kommissionen», «Geschäfte» und «Sitzungen» teilen eine Liste der Geschäfte.** Die Liste kommt beim ersten Öffnen einer dieser Ansichten einmal vom Server, die erledigten Geschäfte eingeschlossen; ein Wechsel zwischen den drei Ansichten und das Aufklappen einer Sitzung laden sie nicht neu. Die Ansicht «Geschäfte» blendet die erledigten aus, solange der Schalter «Erledigte anzeigen» aus ist (F16). Suche und Filter laufen über die ganze geladene Liste; ab drei Zeichen fragt die Suche zusätzlich den Server nach den Geschäften, deren Dokumente den Begriff enthalten (F121). Die Tabelle zeigt von den Treffern die neuesten 500; ein älteres erledigtes Geschäft ist so über die Suche zu finden.
    - Ändert jemand ein Geschäft, kommt in jedem anderen Fenster nur dieses eine Geschäft neu und ersetzt das alte an Ort und Stelle, in allen drei Ansichten zugleich; die Tabelle bleibt dabei stehen. Ein gelöschtes eigenes Geschäft verschwindet ebenso.
    - Nach einer Synchronisation kommen nur die Geschäfte neu, die sie angelegt hat, deren Angaben sich auf der Parlamentswebseite geändert haben, die als gelöscht markiert waren und wieder in der Quelle stehen, und denen sie Zuständige gegeben hat. Hat sie Geschäfte als gelöscht markiert oder wurde der Sitzungsmodus umgeschaltet, kommt die ganze Liste neu, ebenfalls ohne dass die Tabelle verschwindet. Bricht die Synchronisation ab oder scheitert sie, kommen ebenso die Geschäfte neu, die sie bis dahin angelegt oder geändert hat; wurde sie mit einem Signal hart beendet, kommt die ganze Liste neu.
    - Wer im eigenen Fenster Priorität, Zuständigkeit oder Beschluss ändert, ein Geschäft in der Maske bearbeitet oder ein eigenes Geschäft anlegt, sieht danach nur dieses Geschäft neu geladen; das Ereignis zu seiner eigenen Änderung lädt nichts mehr. Öffnet er die Maske aus einem Traktandum, kommen danach auch die Traktanden der offenen Sitzungen neu, auf denen das Geschäft steht, und die Traktandenzeile zeigt den neuen Stand. Das gilt auch, wenn ✕ die Maske schliesst, bevor der Server das Speichern bestätigt. Eine Sitzungsnotiz, die in der Maske erfasst wird, steht danach in der Zeile des Traktandums. Zuständigkeit und Beschluss in der Zeile eines Traktandums laden jede offene Sitzung mit demselben Geschäft neu.
    - Verknüpft jemand einen Vorstoss mit einem Geschäft oder ein eigenes Geschäft mit einem offiziellen, kommt das Geschäft, das die Priorität übernommen hat, in jedem Fenster neu, auch im eigenen (F33, F70).
    - Eine ältere Antwort ersetzt nie einen neueren Stand: Die Antwort zu einem Geschäft fällt weg, wenn eine spätere schon übernommen ist, und die ganze Liste lässt die Geschäfte stehen, die während ihres Ladens neu kamen. Verlangt ein Ereignis während einer Ladung die ganze Liste, folgt eine weitere Ladung, und ein Ereignis während der ersten Ladung lädt seine Geschäfte gleich danach.
    - Der Schalter «Erledigte anzeigen» und die Auswahl «Entscheidungsbedarf» wählen aus der geteilten Liste aus und fragen den Server nicht. Erledigt ist ein Geschäft, dessen Status «erledigt», «abgeschlossen» oder «aufgehoben» enthält, gross oder klein geschrieben.
    - Die Auswahl «Mit Geschäft verknüpfen» und die Auswahl der Geschäfte an einer Sitzung nehmen dieselbe Liste; an der Sitzung stehen die hängigen Geschäfte zur Wahl. Der Dialog «Mit Geschäft verknüpfen» zeigt wie die Tabelle von den Treffern die neuesten 500, die ähnlichsten zuoberst.

## Rollen und Sitzungsmodus

- **F56** Rollen in der Fraktion: Fraktionspräsident, Protokollführer und Kommissionsmitglieder, jede mit zeitlich befristeten Stellvertretungen, die neben dem Inhaber handeln können. Wer eine Rolle vergeben darf:
    - **Den Fraktionspräsidenten** setzt nur der Gruppen-Administrator der Fraktionsgruppe oder ein Nextcloud-Administrator.
    - **Den Protokollführer, die Stellvertretung des Fraktionspräsidenten und die Kommissionsmitglieder** setzt der Fraktionspräsident oder seine aktive Stellvertretung.
    - Die **Stellvertretung des Protokollführers** darf zusätzlich der Protokollführer selbst oder seine aktive Stellvertretung eintragen.
    - Zu jeder Stellvertretung gehört ein Zeitraum mit «von» und «bis»; «bis» muss nach «von» liegen, sonst weist die App die Eingabe ab.
- **F57** Während der Fraktionssitzung lässt sich der Sitzungsmodus einschalten, durch den Fraktionspräsidenten oder seine aktive Stellvertretung. Beschlüsse erfasst dann nur der Protokollführer oder seine aktive Stellvertretung; versucht es ein anderer, weist die App die Eingabe mit einem Hinweis ab. Notizen und Voten bleiben nach den üblichen Regeln offen, und die Ansicht «Geschäfte» zeigt automatisch nur die Geschäfte, die einen Entscheid brauchen. Ausserhalb des Sitzungsmodus darf jedes Mitglied Beschlüsse erfassen.

## Administration

- **F58** Fraktion und zugehörige Nextcloud-Gruppe wählen; wählbar sind nur synchronisierte, aktive Fraktionen.
- **F59** Synchronisation von Hand starten («Jetzt synchronisieren», mit laufendem Fortschritt) oder abbrechen. Nach dem Klick auf «Synchronisierung abbrechen» steht die Meldung «Abbruch angefragt...» da, bis der Lauf endet, auch solange der Server das Signal noch nicht aufgenommen hat; so lange bleibt der Knopf gesperrt. Kommt der Abbruch nicht beim Server an, meldet die Seite den Fehler und gibt den Knopf mit der nächsten Meldung des Laufs wieder frei.
- **F60** Zeitplan der automatischen Synchronisation: beliebige Einträge mit Wochentagen (Mo–So zum Abhaken) und Uhrzeit, mit Hinzufügen und Löschen; automatisch gespeichert. Verpasste Zeitpunkte, etwa nach einem Unterbruch, werden nachgeholt. Ist kein Eintrag gesetzt, gilt der Standard und wird vorbelegt angezeigt: zwei Einträge an allen Wochentagen (Mo–So), um 10:00 und um 18:00 Uhr; sie lassen sich wie eigene Einträge bearbeiten oder löschen.
- **F61** Fraktionsmitglieder den Nextcloud-Benutzern zuordnen, fehlende Benutzer anlegen (mit E-Mail-Einladung); verwaiste Benutzer (nicht mehr im Parlament) sind markiert und lassen sich gezielt deaktivieren.
- **F62** Rollen und befristete Stellvertretungen verwalten.
- **F63** Kürzel definieren, zum Beispiel «Pendent: Stadtrat» für «Beim Stadtrat pendent» oder «MPW» für «Musterpartei». Die Kürzel gelten überall für Status, Partei-, Fraktions- und Kommissionsnamen. Das Feld «Suchtext» schlägt die bestehenden Status-Werte sowie die aktuellen Fraktions- und Parteinamen vor; automatisch gespeichert.
- **F69** Typen für eigene Geschäfte pflegen: eine beliebig lange Liste von Bezeichnungen, mit Hinzufügen und Löschen, automatisch gespeichert. Diese Typen stehen beim Anlegen eines eigenen Geschäfts zur Auswahl. Ohne Eintrag bleibt es beim Typ «Eigenes Geschäft».

## Weitere Ansichten

- **F64** Die Ansicht «Änderungsverlauf» zeigt als aufklappbare Liste, was in welcher Version dazugekommen ist; die Neuerungen der aktuellen Version sind schon aufgeklappt.
- **F106** Die Ansicht **«Bedienungsanleitung»** (in der Navigation direkt vor «Änderungsverlauf») zeigt das README des Projekts formatiert, sodass die Anleitung im Tool selbst steht.
- **F107** **Direkter Verweis auf jede Ansicht:** Jede Ansicht ist über den **Hash der Adresse** direkt aufrufbar und teilbar, etwa `…/apps/parlwin/#budget` oder `…/#anleitung`. Der Hash folgt der Navigation, ein direkt aufgerufener Hash öffnet die passende Ansicht, ein unbekannter Hash bleibt bei der Standardansicht, und Vor und Zurück im Browser wechseln die Ansicht.
- **F108** Die Ansicht **«Protokoll»** (in der Navigation vor «Bedienungsanleitung») zeigt den **Verlauf der Synchronisationen und der eingelesenen Budgets**, das neueste Ereignis zuerst. Jeder Eintrag trägt **Zeitpunkt**, **Art** (Synchronisation, Budget-Import, Budget neu eingelesen, Novemberbrief, Sitzungsanträge, Fehler), eine **Markierung für Erfolg oder Fehler**, einen **Titel** und eine **Meldung** (bei Synchronisationen «X neu, Y geändert», dazu wie viele Einträge nicht gespeichert wurden und welcher Bereich nicht gelesen werden konnte; bei Budget-Importen die Zahl der Produktegruppen und Investitionen; bei Sitzungsanträgen «N neu von M gefundenen»), sowie den **Auslöser** (die Benutzer-ID beim Auslösen von Hand, «auto» beim automatischen Lauf im Hintergrund). Protokolliert werden die automatischen Läufe (Hintergrundauftrag) ebenso wie die von Hand ausgelösten. Eine Synchronisation, die Einträge nicht speichern oder einen Bereich nicht lesen konnte, trägt die Markierung für Fehler. **Fehler-Ereignisse sind hervorgehoben** und sind der Ort, an dem festgehalten wird, **was sich nicht lesen liess**, etwa ein Budgetbuch, aus dem keine Produktegruppen gelesen werden konnten, samt der geladenen Adresse. Das Protokoll behält die Ereignisse der letzten 180 Tage.

## Budget

Die Ansicht «Budget» begleitet die Fraktion durch den Budgetprozess der Stadt: Sie erfasst eigene und fremde Anträge nach Produktegruppen, verteilt Pauschalanträge automatisch auf die Produktegruppen und führt während der Budgetsitzung die Beschlüsse mit.

Grundlage sind die Budgetbücher der Stadt: Teil B (Produktegruppen-Globalbudgets) gliedert das Budget nach **Departement, Produktegruppe und Produkt**; die Produktegruppe trägt das beschlussfähige Globalbudget (Nettokosten), das Produkt ist reine Information. Teil A enthält die Investitionsrechnung, den Steuerfuss und die Gesamt-Erfolgsrechnung.

### Seite, Tabs und Filter

- **F74** Die Ansicht «Budget» in der Seitenleiste öffnet eine eigene Budget-Seite. Sie besteht aus den Tabs Globalbudgets, Personalbestand, Investitionsrechnung, Steuerfuss, Anträge und Grafik; die Filter und die Summenzeile gelten für alle Tabs gemeinsam.
    - Die **Tab-Leiste bleibt beim Scrollen oben stehen**, sodass man zum Wechseln nicht nach oben scrollen muss.
    - Die **Scrollposition wird je Tab gemerkt** und beim Zurückwechseln wiederhergestellt; ein noch nicht besuchter Tab startet oben. Das Merken ist flüchtig (nur solange die Seite offen ist, weder Cookie noch Speicher).
- **F75** Ein Filter wählt das **Budgetjahr** aus einem Auswahlmenü der tatsächlich vorhandenen Jahre (nur Jahre, für die Daten in der Datenbank liegen); vorbelegt ist das neueste Jahr. Ein Budgetjahr entsteht jeweils Ende des Vorjahres für das kommende Jahr (Ende 2026 entsteht das Budget 2027). Für vergangene Jahre, heute die Jahre vor 2027, erzeugt die App nichts mehr von selbst; von Hand lassen sie sich über «+ Neu» einlesen (F91). Nach dem 1. Dezember des Vorjahres werden nicht erzeugte Budgetjahre ignoriert, und es werden keine Daten mehr abgerufen, die nicht schon in der Datenbank sind. **Offen:** Diesen Stichtag prüft die App noch nicht; der automatische Lauf liest das neueste Budgetjahr ein, sobald es fehlt, gleich an welchem Tag (F89).
- **F76** Zwei weitere Filter auf der Budget-Seite: **zuständige Kommission** und **Departement**. Jedes Departement hat eine zuständige Sachkommission; die beiden Filter hängen entsprechend zusammen. Die Auswahl schränkt alle Tabs und die Summenzeile auf das gewählte Departement oder die gewählte Kommission ein.
- **F120** Nach dem Departement folgt ein Filter **Produktegruppe**, der nur die Produktegruppen des gewählten Departements anbietet; ohne gewähltes Departement stehen alle vorkommenden da. Angeboten wird, was in den Daten steht: die Produktegruppen der Globalbudgets und die Zuordnungen der Investitionsprojekte. Die Auswahl grenzt alle Tabs auf diese eine Produktegruppe ein, der Zähler im Kopf zählt mit, und wechselt das Departement, wird eine Produktegruppe, die nicht dazu gehört, geleert. Vor allem die Investitionsrechnung ist ohne diesen zweiten Schritt unübersichtlich, weil sie sehr viele Vorhaben führt.
- **F77** Filter nach **Kostensteigerung** gegenüber dem Vorjahr, in Prozent und in absoluten Franken, kombinierbar, etwa «alles über 5% gestiegen», «alles über 1 Mio. gestiegen» oder beides. Verglichen wird der **beschlussfähige Globalkredit** (Nettokosten) der Produktegruppe, das Soll des Budgetjahres mit dem Soll des Vorjahres; der Bruttoaufwand zählt nicht. Der Filter zeigt **nur echte Anstiege**: Eine Produktegruppe, deren Globalkredit gleich bleibt oder **sinkt** (negative Differenz), erscheint bei einer «Anstieg ab …»-Schwelle nie, ebenso wenig ein Anstieg **unter** der Schwelle. Der Prozentwert misst am **Betrag** des Vorjahreswertes (`abs`), damit ein negativer Vorjahreswert das Vorzeichen nicht verdreht; ist der Vorjahreswert 0, gilt der Prozentanstieg als 0.
- **F78** Sortiert wird überall in der Reihenfolge des Buches: **Departement, Produktegruppe, Produkt**, im Tab «Investitionsrechnung» Departement und Projekt.
- **F79** Auf **allen** Tabs steht zuoberst eine Summenzeile, die bei jeder Anpassung neu berechnet wird und sich immer nach den aktiven Filtern richtet (also für das gefilterte Departement oder die gefilterte Kommission). Enthalten sind: Total Stellen mit Zu- oder Abnahme, Einnahmen, Ausgaben, Ertrag oder Defizit und Steuerfuss, jeweils mit der Differenz zum Vorjahr. Auf breiten Bildschirmen mit genügend Platz bleibt die Summenzeile beim Scrollen oben stehen.

### Tab «Globalbudgets» (Teil B)

- **F80** Der erste Tab zeigt die **Globalbudgets** aus Teil B: je Produktegruppe das beschlussfähige Globalbudget (Nettokosten) mit den Werten des Budgetjahres, des Vorjahres und des Rechnungsjahres. Die zugehörigen **Produkte** stehen zur Information darunter, die **Erläuterungen und Begründungen** aus dem Buch werden an der richtigen Stelle mit ihren Zahlen angezeigt.
- **F81** **Anträge sind nur auf Produktegruppen möglich.** Sie sind im Buch als «Zum Beschluss» gekennzeichnet. Produkte gehören zum Informationsteil und lassen sich nicht beantragen.
- **F82** Eigene und fremde **Anträge** werden erfasst und nach Produktegruppen geordnet dargestellt. Zu jeder Produktegruppe sind die daran gestellten Anträge (Betrag, Antragsteller, Begründung) sichtbar.
- **F116** **Der Kopf jeder Karte zeigt, was unsere Anträge an dieser Position bewirken.** Er stellt die Werte gegenüber wie die Übersicht (F102), aber je Produktegruppe: Wirken unsere Anträge auf sie, steht oben der Wert des **Stadtrats** (wie vorgelegt) und darunter der Wert der **Fraktion** (nach Abzug unserer Anträge), jeder mit **seiner Differenz zum Vorjahr**. Es zählen dieselben Anträge wie in der Übersicht: in der Vorbereitung die von der Fraktion unterstützten, im Sitzungsmodus die vom Parlament angenommenen; ein Personalantrag senkt neben den Stellen auch den Betrag. Ändern unsere Anträge nichts an der Position (kein Antrag oder keiner, der zählt), steht nur der eine Wert da, ohne Beschriftung. Das gilt auf dem Tab «Globalbudgets» mit dem Betrag und auf dem Tab «Personalbestand» mit den Stellen. **Beide Werte samt Differenz stehen immer vollständig da**: Reicht die Breite der Karte nicht, rückt der Wert unter die Bezeichnung, statt am Kartenrand abgeschnitten zu werden.
- **F117** **Ein Antrag wird durch einen Klick in seine Zeile bearbeitbar.** Die Zeile öffnet dasselbe Formular wie «+ Antrag», gefüllt mit allem, was am Antrag steht (Richtung, Betrag in CHF und Prozent, Stellen, Herkunft, Antragsteller, unterstützende Fraktionen, Begründung, Zielvorgaben-Änderungen und Einsparungsverteilung). «Antrag» speichert die Änderung an genau diesem Antrag, «Abbrechen» verwirft sie; die Bedienelemente der Zeile (Haltung, Löschen, Verknüpfung, Notizen, Beschluss) behalten beim Klick ihre eigene Wirkung. **Automatisch erzeugte Anträge** (F85) gehören ihrem Pauschalantrag und lassen sich nicht so bearbeiten. Das gilt für die Anträge auf allen drei Tabs (Globalbudgets, Personalbestand, Investitionsrechnung).

### Globale Kürzungsanträge und automatische Verteilung

- **F83** Ein **globaler Kürzungsantrag** verteilt einen festgelegten Betrag anteilig auf alle verfügbaren (nach aktuellem Filter beschlussfähigen) Produktegruppen, **anteilig zum beeinflussbaren Aufwand** (F128): Grössere Budgets tragen absolut mehr. Die dafür nötigen Einzelanträge werden dabei automatisch erzeugt.
- **F84** Alle Pauschalanträge werden **gleich behandelt** und stehen im gemeinsamen Kasten «Pauschalanträge» (F100). Die Liste **startet leer**; «+ Pauschalantrag» legt einen neuen an. Jeder Pauschalantrag trägt einen **Ziel-Typ**:
    - **Einsparungen** (relativ): ein fester Betrag (CHF) oder Prozentsatz des ursprünglichen Aufwands wird anteilig gekürzt. Beliebig viele möglich, ihre Kürzungen kumulieren.
    - **Schwarze Null**, **fester Ertrag** oder **festes Defizit** (absolut): Das Gesamtergebnis wird auf diesen Wert ausgeglichen. Von diesen **absoluten Zielen ist nur eines aktiv**; wählt man ein zweites, wird das ältere automatisch zur **Einsparung 0**. Ein absolutes Ziel gleicht selbsttätig aus, ohne eigenen Schalter oder Knopf. Zuerst werden alle Einsparungen gerechnet, das absolute Ziel **zuletzt** auf dem bereits gekürzten Stand (F100).
- **F112** **Ein Antrag von Hand kürzt ohne Grenze, die automatische Verteilung höchstens um den beeinflussbaren Aufwand.** Ein Antrag von Hand darf eine Position stärker kürzen, als sie budgetiert ist, und mehrere Anträge auf dieselbe Position dürfen zusammen mehr als 100% ergeben; die App speichert sie ohne Meldung (F99). Die automatische Verteilung eines Pauschalantrags kürzt eine Position dagegen höchstens um ihren beeinflussbaren Aufwand (F128). Was eine Position darüber hinaus tragen müsste, verteilt sie auf die übrigen Positionen; reicht auch deren Spielraum nicht, bleibt der Rest liegen, und die Summe der Verteilung ist kleiner als der verlangte Betrag.
- **F113** **Jeder abgelehnte oder fehlgeschlagene Aufruf im Budget wird gemeldet**, mit dem Grund, den der Server nennt. Das gilt für Antrag stellen, ändern, löschen und entscheiden, Haltung umschalten, Pauschalantrag, Steuerfuss, Import und Neueinlesen.
- **F85** Die zur Erfüllung eines Pauschalantrags nötigen **Einzelanträge** werden automatisch auf den Produktegruppen erstellt, angepasst oder, wenn sie nicht mehr nötig sind, wieder gelöscht; sie verteilen **anteilig zum beeinflussbaren Aufwand** (F128) und greifen nur auf die echten, operativen Produktegruppen, nie auf die künstliche Produktegruppe (F89). Automatisch erzeugte Anträge sind als solche erkennbar und in der Oberfläche eigens filterbar.

### Tab «Personalbestand»

- **F86** Der zweite Tab zeigt ausschliesslich den **Personalbestand**: vom Gesamttotal heruntergebrochen nach Departement und Produktegruppe (bis auf die Produkte nur, soweit das Buch die Stellen dort ausweist; meist endet die Aufschlüsselung bei der Produktegruppe). Es sind **Anträge auf Personalkürzung oder -aufstockung** möglich: Angegeben werden die Zahl der Stellen (Kürzung oder Erweiterung) und der entsprechende Betrag. Wird kein Betrag angegeben, gilt ein Standardbetrag pro Stelle, den die Verwaltung einstellt; ohne Einstellung sind es 200'000 CHF.

### Tab «Investitionsrechnung»

- **F87** Der dritte Tab zeigt die **Investitionsrechnung**. Auch hier lassen sich Anträge (Änderungsanträge) stellen. Sortiert wird nach **Departement und Projekt** (Investitionen sind nach Departement, Investitionscluster und Einzelprojekt gegliedert). Zuoberst stehen die Summen. Je Projekt werden zusätzlich angezeigt: bereits getätigte und in den künftigen Jahren noch folgende Investitionen zum selben Projekt, die **Summe der Jahre** (was das Vorhaben über alle ausgewiesenen Jahre kostet) und der **bewilligte Kredit** aus dem Anhang «Kontrolle der Investitionskredite» (was das Parlament freigegeben hat) und, soweit verfügbar, laufende oder bereits getätigte Planungskosten. Beide Zahlen heissen auf der Karte und im Detail gleich. Das Detail eines Projekts führt die Konten des Anhangs «Kontrolle der Investitionskredite» einzeln auf und schliesst mit deren Total, das den bewilligten Kredit nachrechnet. Hier gibt es **keinen** automatischen Verteilmechanismus.
    - Woher die Zahlen kommen: Der Anhang «Investitionsplanung Verwaltungsvermögen» führt je Projekt fünf Jahresspalten. Die Spalte **vor** dem Budgetjahr ist das bereits Investierte, die drei danach sind die Planjahre. Der zweite Anhang «Kontrolle der Investitionskredite» führt je Projekt den **Gesamtkredit** und darunter die einzelnen **Konten** mit Teilbetrag, bewilligtem Kredit und dem **Datum der Bewilligung**. Eine Zeile «… - Planung» schlüsselt den Planungsanteil des Projekts darüber auf und zählt nicht als eigenes Projekt.
    - **Details je Projekt:** Ein Klick auf «Details» öffnet denselben Vollbild-Dialog wie bei einer Produktegruppe. Er zeigt die Jahre vom bereits Investierten über das Budgetjahr bis zu den drei Planjahren samt der Summe der Jahre, den Planungsanteil und die Tabelle der bewilligten Kredite je Konto. Führt das Buch für ein Projekt keine einzelnen Kredite, sagt das Detail das.
    - **Die Summe der Jahre umfasst alle Jahre:** das bereits Investierte, das Budgetjahr und die drei Planjahre. Der im Anhang «Kontrolle der Investitionskredite» geführte **bewilligte Kredit** steht daneben als eigener Wert; er sagt, was das Parlament freigegeben hat, und ist deshalb eine andere Aussage als die geplanten Kosten.
    - **Der Betrag des Budgetjahres ist der grösste und kräftigste Wert der Karte.** Über ihn wird entschieden. Das gilt auf jeder Karte des Budgets, auch bei den Globalbudgets und beim Personalbestand.
    - **Filter «nur mit Betrag im Budgetjahr»:** Projekte, die im Budgetjahr null führen (sie beginnen erst in einem Planjahr), lassen sich ausblenden.

### Tab «Steuerfuss»

- **F88** Der vierte Tab zeigt den **Steuerfuss**. Der Tab zeigt den **geltenden** Steuerfuss, den **beantragten** Steuerfuss (aus dem Stadtratsantrag) und die Differenz zum Vorjahr, etwa ±0%. Standardmässig wird bei einem **Ertragsüberschuss** der Steuerfuss in abgerundeten Prozent-Schritten automatisch gesenkt und der Überschuss dadurch abgebaut (1 Steuerprozent = Steuerertrag geteilt durch den geltenden Steuerfuss). Der Schalter «Steuerfuss bei Überschuss automatisch senken» wird **pro Budgetjahr gespeichert** und bleibt nach dem Neuladen erhalten. Bei aktiver Automatik ist «Antrag stellen» implizit ein; das manuelle Feld und der «Antrag stellen»-Schalter sind ausgeblendet. **Schaltet man die Automatik aus, fällt der Steuerfuss auf den Stadtratsantrag zurück** (der automatische Antrag verschwindet), und es erscheint ein Eingabefeld (vorbelegt mit dem Stadtratsantrag) samt dem **Schalter** «Antrag stellen». «Antrag stellen» ist ein **Schalter**: Einschalten stellt den Steuerfuss-Antrag auf den Feldwert (ändert man das Feld, folgt der Antrag kurz nach der letzten Eingabe), Ausschalten löscht ihn wieder, und es gilt der Stadtratsantrag. Mehrfaches Auslösen während des Neurechnens erzeugt **keine doppelten Anträge** (Laufsperre). Ein **von Hand gesetzter Steuerfuss ist nie ein Konflikt** und steht für sich. Jede Anpassung des Steuerfusses, automatisch wie von Hand, erzeugt einen **echten Antrag** und legt dazu automatisch einen **Antrag auf die Steuererträge** (Einnahmen) in der entsprechenden Höhe an, passt ihn an oder löscht ihn; beide werden standardmässig von der **eigenen Fraktion** beantragt und stehen am Ende der Antragsliste und des Antrags-PDF (F92).
    - **Steuerfuss-Senkung und fixer Ertrag zusammen:** Ist die automatische Senkung aktiv und es gibt **keinen** fixen Pauschalantrag, wird der natürliche Ertragsüberschuss über die Senkung abgebaut, und der Gesamtertrag ist null. Gibt es zusätzlich einen **fix definierten Ertrag** (fixer Pauschalantrag, F100), wird **genau dieser fixe Ertragsbetrag für die Senkung des Steuerfusses verwendet**, und der tatsächliche Ertrag der Fraktion ist dann **null**. Ohne aktive Senkung bleibt der fixe Ertrag auf seinem festgelegten Wert (F100).

### Parlamentarische Zielvorgaben und Antrags-Aufteilung (WoV)

In der wirkungsorientierten Verwaltung (WoV) steuert das Parlament die Verwaltung über Wirkungs- und Leistungsziele. Entschieden wird **nur auf der Ebene der Produktegruppe**, über das Globalbudget und die Zielvorgaben. Alles andere (Produkte, Kostentabellen, Erläuterungen) ist Information.

- **F109** Jede Produktegruppe trägt ihre **Parlamentarischen Zielvorgaben**: nummerierte Ziele wie «2 Kundenorientierung zentrales Personalmanagement» mit je einer oder mehreren **Messgrössen**, jede mit sechs Jahresspalten (Ist Vorjahr, Soll Vorjahr, **Soll aktuell**, drei Planjahre). Das Parlament entscheidet über «Soll aktuell».
    - **Gelesen wird auch, was das Buch offen lässt:** Eine Messgrösse, die erst mit dem Budgetjahr eingeführt wird, trägt nur die letzten Spalten; links fehlen die leeren. Ein Sollwert steht als Zahl, als Vergleich («>22000»), als Spanne («1 bis 2»), mit Prozentzeichen oder mit Fussnotenzeichen. Beantragbar ist eine Vorgabe nur, wo im Soll des Budgetjahres eine Zahl steht: «N/A» und «wird gelöscht» sind nichts, worüber das Parlament beschliessen könnte.
    - **Der Unterabschnitt gehört zum Namen:** Das Buch gliedert ein Ziel in nummerierte Unterabschnitte («1.1 Minimale Nettorendite», «1.2 Durchschnittliche Nettorendite», «4.1 Wohnliegenschaften»), und darunter stehen dieselben Messgrössen mehrfach. Eine Zielvorgabe heisst darum Ziel, Unterabschnitt und Messgrösse; ohne den Unterabschnitt heissen zwei Vorgaben derselben Produktegruppe gleich, und ein Antrag auf die eine trifft die andere.
    - **Antrag auf eine Zielvorgabe:** Zu jeder Zahl kann ein Antrag gestellt werden, der den Soll-Wert ändert, etwa 100 statt 90. Ein Zielvorgaben-Antrag hat **keine automatische Budgetwirkung**. Ein Antrag kann **eine oder mehrere Zielvorgaben** ändern.
    - **Zielvorgabe und Budget getrennt oder zusammen:** In einem Antrag lassen sich Zielvorgaben-Änderungen **und** eine Budgetanpassung kombinieren. Nur Zielvorgabe ohne Budget: der Budgetwert bleibt leer. Nur Budget ohne Zielvorgabe: keine Zielvorgabe wählen. Beispiel: «nur noch 80% zufrieden, dafür 20% billiger» sind zwei Wirkungen, in einem oder in zwei Anträgen, nämlich die Zielvorgabe 80 statt 90 und ein Budgetantrag «20% einsparen».
    - **Hierarchische Aufteilung (Einsparungsverteilung):** Weil das Parlament nur über die Produktegruppe bestimmt, dient die Aufteilung vor allem der **Begründung**. Sie sagt, **wo** innerhalb der Produktegruppe der beantragte Betrag einzusparen ist. Beim Antrag lassen sich mit je optionalem Betrag **oder** Prozentanteil auswählen: (a) eine oder mehrere **Zeilen der Kostentabelle** im Informationsteil der Produktegruppe (Personalkosten, Sachkosten, Informatikkosten …), (b) ein oder mehrere **Produkte**, (c) eine oder mehrere **Kostenzeilen innerhalb eines Produkts**.
        - Ist auf Ebene Produktegruppe **kein** Betrag gesetzt, weiter unten aber schon, wird oben die **Summe** der unteren Beträge eingesetzt.
        - Ist oben **und** unten ein Betrag gesetzt, wird nichts gerechnet, und die Aufteilung geht **nur in die Begründung**.
        - Ein unten gesetzter Betrag oder Prozentwert bedeutet: vom ganzen beantragten Betrag ist der angegebene Anteil an dieser Stelle einzusparen.
        - Die vollständige Aufteilung erscheint **im Antrags-PDF in der Begründung**, zusätzlich zur manuell erfassten Begründung.
    - **Was ein Antrag an den Zielwerten ändert, steht am Antrag:** auf der Karte der Produktegruppe, in der Antragsliste und im PDF, je Änderung der Name der Vorgabe, der heutige Soll und der beantragte. Im Tab «Anträge» und im Antrags-PDF steht der geänderte Zielwert bei einem Antrag ohne Betrag in der Spalte des Betrags, dort, wo man liest, worüber abgestimmt wird; eine Null steht dort nicht. Trägt der Antrag auch einen Betrag, steht der Betrag in dieser Spalte, und die geänderten Zielwerte folgen der Begründung.
    - **Produktegruppen-Budget = Summe der Produkt-Budgets.** Produkte sind Information; manche sind in ihren Kosten weiter aufgeschlüsselt.

- **F110** **Die Ansicht «Budget» ist ein Baum aus Globalbudget, Departement, Produktegruppe und Produkt:**
    - **Departement:** dient nur als Titel einer Gruppe, unter dem direkt die Karten der Produktegruppen stehen, ohne Informationstext. Das Budgetbuch führt allgemeine Informationen nur auf der Ebene der Produktegruppe.
    - **Produktegruppe:** bleibt als Karte; ein Klick öffnet einen **Vollbild-Dialog** (wie bei den Geschäften), der den ganzen Bildschirm für eine Produktegruppe nutzt. Im Kopf der Globalkredit, oben die Zielvorgaben, darunter die Produkte als Karten. Jede Produkt-Karte zeigt oben die prominente Nettokosten-Soll-Zahl, darunter die **Kostentabelle** (Kosten, Erlös, Nettokosten, Kostendeckungsgrad über Ist, Soll Vorjahr und Soll aktuell, wobei «Soll aktuell» hervorgehoben ist), und die **Leistungen** des Produkts als Aufzählung.
    - **Erläuterungen** (Auftrag, Begründungen, Massnahmen, Erläuterungen zum Stellenplan) gehören zur Produktegruppe und behalten ihre **Formatierung** (Absätze, Aufzählungen); dazu kommen die Leistungen je Produkt. Der Dialog zeigt all das.

- **F111** **Grafische Übersicht als geschachtelte Kreise.** Der letzte Tab des Budgets, «Grafik», zeigt das ganze Budget als geschachtelte Kreise, deren **Fläche dem Betrag entspricht**:
    - **Zwei Zeichenflächen nebeneinander: «Einnahmen» und «Ausgaben»**, jede über die halbe Seitenbreite; auf schmalen Seiten stehen sie untereinander. Der Kopf jeder Seite nennt ihre Summe.
    - Darin je ein Kreis **pro Departement**, dessen Fläche seinem Anteil an den Einnahmen oder Ausgaben entspricht; darin die **Produktegruppen**, darin die **Produkte**. Ein Produkt trägt auf der Ausgabenseite seine Kosten, auf der Einnahmenseite seinen Erlös aus der Kostentabelle des Budgetbuchs; wo ein Produkt keine Kostentabelle hat, bleibt die Produktegruppe die unterste Ebene.
    - **Beide Seiten zeigen immer denselben Ausschnitt:** Ein Klick auf ein Departement zoomt Einnahmen und Ausgaben dorthin, so stehen Aufwand und Ertrag derselben Einheit nebeneinander. Wo eine Seite den gewählten Ausschnitt nicht kennt (kein Ertrag), sagt sie das. Ein Klick auf den Hintergrund oder `Escape` geht eine Ebene zurück; der Pfad im Kopf («Budget › Präsidiales › Bibliotheken») führt per Klick direkt auf jede Ebene zurück.
    - **Das Grössenverhältnis zwischen den Seiten bleibt auf jeder Ebene erhalten.** Der grössere Betrag füllt seine Fläche, der kleinere wird im selben Massstab kleiner gezeichnet.
    - **Jeder Kreis der aktuellen Ebene trägt seinen vollen Namen**, an den Leerzeichen auf mehrere Zeilen umgebrochen. Gekürzt wird nie: Vier Produkte «Bewirtschaftung …» wären als «Bewirtscha…» nicht zu unterscheiden. Tiefere Ebenen tragen ihre Beschriftung, sobald sie gross genug sind.
    - Jedes Departement hat eine Farbe, seine Kinder Abstufungen davon, und dieselbe Einheit hat auf beiden Seiten dieselbe Farbe.
    - **Die Kreise bewegen sich an ihren Platz.** Die Kraftsimulation von d3 (`d3-force`) zieht jede Einheit an die Stelle, die ihr die Kreispackung zuweist, und hält sie dabei auf Abstand zu ihren Nachbarn: Die Kreise schieben einander beiseite, rücken zusammen und ordnen sich, bis alle Platz haben. Beim Öffnen einer Ebene starten sie an ihrer bisherigen Stelle und bewegen sich zu den neuen Plätzen; ihr Inhalt bewegt sich mit. Die Simulation kommt nie ganz zur Ruhe, sodass sich die Kreise leicht weiter bewegen.
    - **Beim Aufbau der Fläche starten die Kreise auf einem Ring weit ausserhalb**, aus allen Richtungen verteilt, und brauchen **zwei bis fünf Sekunden** bis an ihren Platz. Die Kraft zum Platz nimmt dabei langsam zu, die Abstossung zwischen den Kreisen ab: Der Anfang bleibt sanft, und am Ende steht jeder Kreis auf seinem Platz. Wird der Tab geöffnet, beginnt die Bewegung von vorn, weil die Fläche von Anfang an im Dokument liegt und die Bewegung sonst schon vorbei wäre, bevor der Tab sichtbar ist.
    - **Beim Überfahren nennt jeder Kreis seine Zahlen**, auch der kleinste ohne Beschriftung: Name, Betrag, Anteil am übergeordneten Kreis und, wo die Produkte das Budget ihrer Gruppe nicht ganz ausweisen, wie viel Prozent sie ausweisen. Der Klick trifft dabei die Ebene, der er gilt.
    - **Die Summe jeder Seite steht hervorgehoben im Kopf**, grösser und kräftiger als der übrige Text.
    - **Beim Überfahren hebt sich der Kreis unter dem Zeiger hervor:** Er wächst ein Stück, legt sich mit kräftigerem Rand über seine Nachbarn und zeigt die **Namen seiner Kinder**, solange der Zeiger auf ihm steht. So ist vor dem Klick zu sehen, was darin liegt.
    - Die Grafik folgt den **Filtern** der Ansicht «Budget» (Departement, Kommission): Was ein Filter ausschliesst, fehlt auch hier.
- **F123** **Dieselben Kreise zeigen den Stellenplan.** Über der Grafik steht ein Umschalter mit den beiden Massen «Franken» und «Stellen»; «Franken» gilt beim Öffnen. Auf «Stellen» misst die Kreisfläche die Stellen statt der Franken:
    - **Eine einzige Zeichenfläche «Stellen»** statt der beiden Seiten Einnahmen und Ausgaben, mit der Summe aller Stellen im Kopf, auf zwei Nachkommastellen genau.
    - Darin steht ein Kreis **je Departement** und darin **je Produktegruppe**; tiefer geht es nicht, weil das Budgetbuch die Stellen je Produktegruppe ausweist. Eine Produktegruppe ohne Stellen fehlt.
    - **Die Farbe sagt die Richtung gegenüber dem Vorjahr:** **rot, wo Stellen dazukommen**, **grün, wo welche wegfallen**, und **grau, wo sich nichts bewegt hat**. Das gilt auf jeder Ebene: Ein Departement ist rot, wenn es unter dem Strich wächst, auch wenn einzelne Gruppen darin abbauen.
    - **Beim Überfahren nennt der Kreis die Zahlen hinter der Farbe:** Name, Stellen des Jahres, Stellen des Vorjahres, die Veränderung mit Vorzeichen und den Anteil am übergeordneten Kreis.
    - Zoomen, Pfad im Kopf, Beschriftung, Bewegung und die Filter der Ansicht «Budget» verhalten sich wie bei den Franken (F111). Der Wechsel des Masses beginnt den Weg wieder zuoberst, weil ein anderer Baum darunter liegt.
    - **Der Umschalter im Einzelnen:** Er steht als erstes Bedienelement im Tab «Grafik», über der Zeichenfläche und linksbündig. Es sind zwei Knöpfe in einer Gruppe, beschriftet «Franken» und «Stellen», in dieser Reihenfolge; der gewählte ist farbig hinterlegt und sagt es auch der Vorlesehilfe («aria-pressed»). Vorbelegt ist «Franken». Ein Klick wirkt sofort und nur auf die Darstellung: Er ändert keine Daten und wird nirgends gespeichert; beim nächsten Öffnen der Seite steht wieder «Franken».

- **F124** **Notizen stehen auf jeder Ebene des Budgets.** Wer am Budget arbeitet, hält Beobachtungen fest, die keinem einzelnen Antrag gehören: eine Haltung zum ganzen Budget, eine Absprache zu einem Departement, eine Bemerkung zum Steuerfuss. Drei Ebenen tragen darum eine eigene Notizenliste, und es ist dieselbe wie überall in der Anwendung (geteilte Notizenliste, Formatierung, Versionsverlauf, Speichern ausschliesslich über «✓», Löschen durch den Verfasser, ohne Wiederherstellen, weil das Budget keine Aktionszeitleiste trägt):
    - **Zum ganzen Budget:** ein aufklappbarer Bereich «Notizen zum Budget» unter der Tab-Leiste, sichtbar auf jedem Tab. Die Notizen hängen am Budgetjahr; ein Wechsel des Budgetjahres zeigt die Notizen des anderen Jahres.
    - **Zum offenen Tab:** daneben ein zweiter Bereich «Notizen zu <Tab>», je einer für Globalbudgets, Personalbestand, Investitionsrechnung, Steuerfuss, die Anträge und die Grafik. Er zeigt immer die Notizen des gerade offenen Tabs.
    - **Zum Departement:** unter jedem Departementstitel ein Bereich «Notizen zu <Departement>», über alle Produktegruppen dieses Departements hinweg.
    - Jeder Bereich nennt hinter seinem Namen die Zahl seiner aktiven Notizen und lädt sie erst beim Aufklappen. Alle drei Ebenen sind beim Öffnen der Seite zugeklappt.
- **F125** **Ein Budgetantrag trägt Dokumente.** Unter jedem Antrag steht neben «Notizen» ein aufklappbarer Bereich «Dokumente» mit derselben Dokumentenverwaltung wie am Geschäft, am Vorstoss und an der Sitzung: bestehende Dateien ansehen und öffnen, eine neue Datei anlegen, eine Datei hochladen. Die Dateien eines Budgetjahres liegen im Fraktionsordner unter «Fraktion/50_Budget/<Budgetjahr>»; der Name jeder Datei beginnt mit «Antrag-<Nummer>-», sodass die Dateien eines Antrags im Jahresordner beieinander stehen. Eine hochgeladene Datei mit einem bereits vorhandenen Namen ersetzt die bestehende.
- **F126** **Viele Anträge lassen sich in einer Tabelle erfassen.** Im Tab «Anträge» steht der aufklappbare Bereich «Anträge erfassen». Darin:
    - **Oben die gemeinsamen Angaben**, die für jede neue Zeile gelten: Herkunft (Eigen/Fremd, vorbelegt «Eigen»), Antragsteller (vorbelegt mit dem zuletzt verwendeten) und unterstützende Fraktionen.
    - **Darunter eine Zeile je Antrag** mit den Spalten Position (Auswahl über Nummer oder Name, alle Produktegruppen und alle Investitionsvorhaben), Richtung (Umschalter Reduktion/Mehrausgabe, vorbelegt Reduktion), Betrag CHF, Betrag % und Begründung. CHF und Prozent rechnen sich wechselseitig aus dem Budgetwert der gewählten Position um, wie im Einzelformular.
    - **Die Eingabetaste schliesst die Zeile ab und öffnet die nächste**, solange Position und Betrag gesetzt sind; eine leere oder unvollständige Zeile öffnet keine neue.
    - **«+ Zeile»** hängt eine weitere Zeile an, «✕» entfernt eine; die letzte Zeile bleibt immer stehen.
    - **«<n> Anträge anlegen»** legt alle vollständigen Zeilen an und leert die Tabelle; unvollständige Zeilen bleiben unberücksichtigt. Danach steht die Zahl der angelegten Anträge als Meldung da.
    - Im Sitzungsmodus erscheint der Bereich nicht.
- **F127** **Anträge lassen sich aus einer Tabellendatei übernehmen.** Im selben Bereich steht «Aus einer Tabelle übernehmen»:
    - **«Vorlage herunterladen»** gibt eine CSV-Datei mit den vier Spalten «Position», «Richtung», «Betrag» und «Begründung».
    - **Hochgeladen werden `.csv` und `.xlsx`.** Die Position ist die Nummer der Produktegruppe oder ihr Name; ein Betrag ohne Vorzeichen gilt als Kürzung, ein Pluszeichen oder das Wort «Mehrausgabe», «Erhöhung» oder «Aufstockung» in der Spalte «Richtung» macht eine Mehrausgabe daraus. Beträge in Schweizer Schreibweise (1'200'000) werden gelesen.
    - **Vor der Übernahme steht eine Vorschau** mit einer Zeile je Dateizeile: ihre Nummer in der Datei, Position, Betrag, Begründung und der Befund: «übernehmbar», «Unbekannte Position» oder «Betrag ist keine Zahl». Fehlerhafte Zeilen sind hervorgehoben.
    - **Erst «<n> Anträge übernehmen»** legt die übernehmbaren Zeilen an; fehlerhafte Zeilen werden übergangen.

- **F128** **Das Budget zeigt, wie viel davon überhaupt beeinflussbar ist.** Ein grosser Teil des Aufwands ist durch Gesetz, Vertrag oder Rechnungsmodell gebunden, ein weiterer wird innerhalb der Stadt verrechnet und hebt sich im Gesamtergebnis auf. Wer kürzen will, braucht zuerst die Antwort, wo überhaupt Spielraum ist.
    - **Jede Kostenzeile trägt eine Stufe:** *beeinflussbar* (Personalkosten, Informatikkosten, Sachkosten, Übrige Kosten, Verkäufe, Übrige externe Erlöse), *neutral* (Residualkosten, Mietkosten, Interne Erlöse, Verrechnung innerhalb der Produktegruppe) oder *gebunden* (Kalkulatorische Abschreibungen und Zinsen, Finanzaufwand, Beiträge an und von Dritten, Gebühren). Eine Zeile, die zu keiner Stufe passt, gilt als beeinflussbar, damit die Anzeige den Spielraum nie kleiner zeigt, als er ist.
    - **Unter dem Betrag jeder Produktegruppe** steht «Beeinflussbar X von Y (Z%)», dazu der intern verrechnete Betrag, wenn es einen gibt. Wo das Buch die Kosten nicht gliedert, steht nichts.
    - **Über der Tab-Leiste** steht dieselbe Angabe für alle gerade gezeigten Produktegruppen, also für das Budget unter den gesetzten Filtern.
    - **Im Antragsformular** trägt jede Zeile der Einsparungsverteilung ihren Hinweis «gebunden» oder «intern verrechnet»; die beeinflussbare Zeile trägt keinen, sie ist der Normalfall.
    - **Die Einstufung sperrt nichts.** Ein Antrag auf eine gebundene Position bleibt möglich; er fordert damit der Sache nach eine Rechtsänderung.
    - **Bindend ist sie an einer Stelle:** Die automatische Pauschalverteilung verteilt ausschliesslich auf den beeinflussbaren Aufwand, im Verhältnis dieses Anteils, und kürzt eine Position höchstens um ihn. Was darüber hinaus nötig wäre, bleibt liegen und ist im Budget sichtbar. Wo eine Produktegruppe keine Gliederung hat, bleibt ihr ganzer Aufwand die Bezugsgrösse, und sie bleibt in der Verteilung.
- **F129** **Jede Produktegruppe nennt ihre Seite im Budgetbuch.** «Buch S. <Nummer>», gelesen aus dem Inhaltsverzeichnis des Buchs, steht überall, wo die Produktegruppe erscheint: auf ihrer Karte im Tab «Globalbudgets» und im Tab «Personalbestand», im Vollbild, das «Details» öffnet, an ihren Anträgen im Tab «Anträge», im PDF von «Anträge als PDF», in der Spalte «Buch S.» der Excel-Tabelle von «Excel in die Dateien» und «Excel herunterladen» und im Hinweistext ihres Kreises in der Grafik. Wer in der Fraktion, in einer Kommission oder in der Debatte mit dem Buch oder dem PDF arbeitet, findet die Position damit sofort. Liefert das Buch keine lesbare Seitenangabe, steht keine Nummer da.

- **F130** **Nach einer Verbesserung des Einlesens werden bereits gelesene Bücher von selbst neu gelesen, und die Einträge der Fraktion bleiben erhalten.** Das Einlesen trägt eine Versionsnummer, die bei jeder Verbesserung steigt, und jedes Budgetjahr trägt die Nummer, mit der es gelesen wurde. Nach einer Aktualisierung der App liest ein Hintergrundauftrag stündlich ein Jahr mit einer älteren Nummer neu ein, bis alle Jahre neu gelesen sind. Ein Jahr zu lesen dauert knapp eine Minute; alle Jahre auf einmal hielten den Hintergrundauftrag so lange an, auch die Synchronisation.
    - **Ersetzt werden allein die Zahlen des Buchs:** Produktegruppen, Investitionen, Zielvorgaben, Kostenzeilen.
    - **Unberührt bleiben alle Einträge der Fraktion:** Anträge mit ihren Beträgen, Zielwert-Änderungen und Einsparungsverteilungen, Notizen samt Versionsverlauf, Dokumente, Entscheide, Haltungen, Pauschalverteilungen und ihre Ausnahmen. Sie hängen an der Nummer der Produktegruppe und am Investitionsvorhaben, und beide stehen im Buch unverändert.
    - **Ein Antrag aus der Zeit vor einer Verbesserung findet seine Zielvorgabe weiter.** Trägt er noch keinen Unterabschnitt, wird er über Zielnummer und Messgrösse zugeordnet.
    - **Ein Jahr, dessen Buch gerade nicht erreichbar ist**, bleibt auf seinem Stand; der nächste Lauf versucht es erneut.
    - Das unterscheidet sich vom Knopf «Budget neu einlesen» (F91), der bewusst alles löscht und darum doppelt bestätigt werden muss.

- **F131** **Vollbild.** Am Kopf der Seitenleiste stehen zwei Knöpfe als Paar: der Griff, der die Leiste ein- und ausklappt, und daneben der Knopf fürs Vollbild, in derselben Form und Grösse. Ist die Leiste offen, steht das Paar rechtsbündig an ihrem Rand; ist sie zugeklappt, rückt das Paar so weit nach links, dass der linke Knopf am linken Bildrand steht, und der Titel der Ansicht beginnt rechts der beiden Knöpfe. Der Vollbild-Knopf erscheint bei jeder Schirmhöhe, sobald der Browser ein Vollbild anbietet; er nimmt die Adressleiste des Browsers und seine Schaltflächen weg und zeigt dann das Symbol zum Verlassen. Verlässt der Browser das Vollbild von sich aus, etwa mit der Zurück-Taste, folgt der Knopf diesem Zustand.
    - Ein Browser, der kein Vollbild anbietet, zeigt den Knopf nicht; dort steht der Griff allein.
    - Lehnt der Browser den Wechsel ab, bleibt alles, wie es war.

- **F132** **Die Begründung eines Antrags ist formatierter Text.** Im Antragsformular, im Pauschalantrag und in der Erfassungstabelle steht dafür dieselbe mehrzeilige Komponente wie bei den Notizen und beim Inhalt eines Vorstosses, mit derselben Werkzeugleiste: fett, kursiv, unterstrichen, durchgestrichen, Überschriften, Listen, Zitat, Code, Links, Rückgängig und Wiederholen. Die Begründung erscheint auf der Karte, in der Antragsliste und im PDF mit ihrer Formatierung.

- **F133** **Jede Karte trägt auf jedem Tab dieselbe Aktionszeile.** Unten an der Karte stehen «+ Antrag» als Hauptaktion und «Details», das dasselbe Vollbild der Produktegruppe öffnet, bei den Globalbudgets wie beim Personalbestand. Hinter «Details» steht die Zahl der Zielvorgaben, wo es welche gibt. Die künstliche Produktegruppe (F89) trägt die Zeile nicht, weil auf sie weder Anträge noch Details möglich sind.

- **F135** **Ein Antrag kann auf einen einzelnen Verpflichtungskredit zielen.** In der Investitionsrechnung führt jedes Vorhaben einen aufklappbaren Bereich «Verpflichtungskredite» mit einer Zeile je Kredit: Kontonummer, Bezeichnung, Betrag des Budgetjahres und der bewilligte Gesamtkredit. Je Zeile steht «+ Antrag»; der Betrag ändert das **Jahresprogramm** dieses Kredits. Ein solcher Antrag zählt zur Investitionsrechnung und bewegt die Erfolgsrechnung nicht. Im Drehbuch der Budgetberatung steht er so: «Das Programm 2026 ist infolge Projektabbruch um CHF 263'000 auf 0 zu kürzen.»
- **F136** **Ein Antrag kann auf ein Departementssekretariat zielen.** Im Tab «Globalbudgets» steht über den Produktegruppen jedes Departements dessen Sekretariat als eigene Karte mit dem Aufwand, den Teil A des Budgetbuchs «vor Umlagen auf die Produktegruppen» ausweist, dazu der Hinweis, dass ein Antrag darauf sich über die Umlage auf die Globalkredite des Departements verteilt. «+ Antrag» öffnet dasselbe Formular wie bei einer Produktegruppe. Der Antrag zählt in die Erfolgsrechnung, weil die Kosten des Sekretariats über die Umlage in den Globalkrediten stecken. Wo ein Buch dieses Kapitel nicht führt, erscheint keine Karte.
- **F137** **Ein Antrag kann das Bewilligungsorgan eines Verpflichtungskredits ändern.** Im selben Formular steht ein Feld «Neues Bewilligungsorgan (statt Betrag)». Ist es gefüllt, trägt der Antrag diesen Wert und keinen Betrag; er erscheint an der Kreditzeile, in der Antragsliste und im PDF als «Bewilligungsorgan → <Wert>» und zählt in keiner Summe mit. Im Drehbuch: «Beim Ausführungskredit ist das Bewilligungsorgan von S auf # anzupassen, da der Kredit aufgrund der Höhe in die Kompetenz des Parlaments fällt.»
    - **Im PDF und im Excel-Export** steht ein Antrag auf einen Verpflichtungskredit unter dem Departement seines Vorhabens, mit «<Vorhaben> · Kredit <Konto> <Bezeichnung>» als Position und «Verpflichtungskredit» als Bereich; ein Antrag auf das Departementssekretariat steht unter seinem Departement mit der Position «Departementssekretariat» und dem Bereich «Sekretariat».

- **F138** **Die Anträge gibt es auch als Tabelle, in den Dateien oder zum Herunterladen.** Im Tab «Anträge» stehen neben «Anträge als PDF» zwei weitere Knöpfe: «Excel in die Dateien» und «Excel herunterladen». Beide erzeugen dieselbe `.xlsx`-Datei aus denselben Anträgen, in derselben Reihenfolge und mit derselben Auswahl wie das PDF: die gewählte Kommission grenzt ein, und die unterstützten fremden Anträge kommen nur mit dem Schalter «Mit unterstützten fremden Anträgen» dazu.
    - **Die Datei trägt eine Zeile je Antrag** und die Spalten Departement, Produktegruppe, Buch S., Bereich, Betrag, Stellen, Prozent, Neuer Wert, Antragsteller, Haltung, Begründung, Zielvorgaben und Einsparung verteilt. Betrag, Stellen, Prozent und die Seite stehen als Zahlen da, also summierbar und sortierbar; alles andere als Text. Wo es keine Stellen, keinen Prozentwert und keine Seitenangabe gibt, bleibt die Zelle leer; die Spalte Betrag trägt auch die Null, damit sich die Spalte als Ganzes summieren lässt.
    - **Die Kopfzeile ist fett, bleibt beim Rollen stehen und trägt den Spaltenfilter.** Die Spalten haben feste Breiten, die Textspalten brechen um.
    - **Die Begründung steht ohne ihre Formatierung** in der Zelle: Absätze und Listenpunkte bleiben als Zeilen erhalten, Fettschrift und Links fallen weg. Zielvorgaben-Änderungen und die Einsparungsverteilung stehen je in einer Zelle, eine Angabe pro Zeile darin.
    - **«Excel in die Dateien»** legt sie unter «Fraktion/50_Budget/<Budgetjahr>» ab, dort wo auch die Dokumente der Anträge liegen (F125), und nennt danach den Pfad. Der Dateiname ist «Budgetantraege-<Jahr>.xlsx», mit gewählter Kommission «Budgetantraege-<Jahr>-<Kommission>.xlsx». Ein zweiter Export überschreibt die Datei, sodass im Ordner immer der letzte Stand liegt und keine zweite Fassung daneben. Fehlt der Ordner, wird er angelegt.
    - **«Excel herunterladen»** gibt dieselbe Datei zum Herunterladen, ohne etwas abzulegen.
    - **Ohne Anträge** entsteht eine Datei mit der Kopfzeile allein.

### Import der Budgetbücher

- **F89** Die Informationen aus dem Budgetbuch werden lesbar aufbereitet, systematisch ausgelesen und in die Datenbank geschrieben, mindestens alle vom Parlament festlegbaren Budgets (Produktegruppen) einzeln. Hinweise und Erklärungen werden an die richtige Stelle kopiert und mit den dazugehörigen Zahlen angezeigt. Alles wird automatisch angelegt, sobald eine neue Budgetweisung veröffentlicht und abgerufen wurde. Das Einlesen ist gegenüber Formatanpassungen der Bücher so tolerant wie möglich.
    - **Departementszuordnung robust gegen umgebrochene Inhaltsverzeichnis-Zeilen:** Ein Departementsname enthält immer Buchstaben. Eine im Buch umgebrochene Zeile aus nur Füllpunkten und einer Seitenzahl («……… 175») wird nicht als Departementskopf gelesen und landet nicht als Departement der folgenden Produktegruppe (die dann unter ihrem richtigen Departement erscheint).
    - **Das Budgetbuch kommt im Betrieb von der Parlamentswebseite.** Beim Abrufen der Webseite wird das **Budget-Geschäft** erkannt (die Weisung «Budget <Jahr> … Festsetzung des Steuerfusses»); auf dessen Seite hängen die Bücher als Beilagen «… Teil A …» (Budget, Finanzplan, Investitionen, Steuerfuss) und «… Teil B …» (Produktegruppen-Globalbudgets). Beim Import werden genau diese beiden PDF **im Betrieb heruntergeladen und gelesen**, und das Ergebnis geht direkt in die Datenbank. **Im Image liegt kein Budgetbuch und kein daraus erzeugtes JSON**, und neue Bücher kommen in die Datenbank, ohne dass ein neues Image gebaut wird. Die Herkunft, die Adresse des Budget-Geschäfts, wird als **Weisungsquelle** gespeichert und als Link im Kopf der Budgetseite gezeigt; wo ein Budget existiert, existiert damit auch der Link.
    - **Die Bezeichnungen stammen aus dem Dokument:** Sie folgen dem Budgetbuch, etwa «Ertragsüberschuss», «Aufwandüberschuss», «Gesamtergebnis».
    - **Gelesen wird jeder Jahrgang, den die Stadt veröffentlicht hat:** die Bücher 2017 bis 2027. Die Stadt hat ihr Rechnungsmodell und ihren Buchsatz mehrfach gewechselt, und das Einlesen kennt beide Rechnungsmodelle: Die Bücher bis 2019 rechnen nach HRM1 (das Ergebnis ist, was der Steuerertrag des Rechnungsjahres vom zu deckenden Aufwandüberschuss übrig lässt), ab 2020 nach HRM2 (das Ergebnis ist Ertrag minus Aufwand). Die Tausender trennt bis 2020 ein Leerzeichen, ab 2021 ein Apostroph, in einer einzigen Tabellenzelle auch beides gemischt; die Beträge des gestuften Erfolgsausweises tragen in den älteren Büchern ein Buchhaltungszeichen («1'692.7 S»); und die Jahreszahlen des Tabellenkopfs stehen mal auf einer Zeile, mal untereinander. Wird eine dieser Formen nicht erkannt, entstehen plausible Zahlen aus dem falschen Jahr oder ein Gesamtaufwand von 33 Millionen statt 1,4 Milliarden.
    - **Geprüft wird das Lesen selbst:** mit **echten Budgetbüchern (PDF) aus mindestens vier vergangenen Jahrgängen**. Diese PDF sind **reine Testdaten** (im Verzeichnis der Tests, **nie im Image**) und dienen im Test **ausschliesslich als Eingabe für das Lesen und zum Vergleich des Ergebnisses**. Sie werden **jedes Mal neu gelesen** und nie durch ein einmal gelesenes Ergebnis ersetzt. Der Test des Imports geht denselben Weg wie der Betrieb: Seite des Geschäfts, Links auf Teil A und B, die PDF selbst. Zusätzlich werden die im Dokument ausgewiesenen **Gesamtsummen** (Gesamtergebnis, Total Aufwand und Ertrag) gelesen und mit den aus den Produktegruppen **errechneten** Werten verglichen. Weicht die Summe der App vom Buch ab, ist das Einlesen oder die Rechnung falsch; auch diesen Fehlerfall prüft ein Test.
    - **Das Einlesen ist tolerant gegenüber Formatanpassungen** der Bücher (F89), projektweise gegen das Buch validiert (auch die Investitionsprojekte, Anhang Investitionsplan).
    - **Der Hintergrundauftrag, die Synchronisation und die Kommandozeile bekommen 1024MiB Speicher**, eine einzelne Anfrage der Oberfläche bis zu 1000MiB (F148). Das Einlesen eines Budgetbuchs braucht mehr als 512MiB, beim Buch 2027 574,7MB. Der Wert ist im Betrieb einstellbar (README, «Variablen für den Speicher»); die gemeinsame Grenze nennt F148.
    - **Gesamtergebnis über eine künstliche Produktegruppe.** Das offizielle Gesamtergebnis der Stadt (Erfolgsrechnung, Teil A) ist **nicht** die Summe der operativen Produktegruppen: Die Produktegruppen von Teil B führen ihre effektiven Kosten und Erlöse **brutto**, samt der internen Verrechnung zwischen den Produktegruppen, und die Erfolgsrechnung schliesst **netto** ab. **Steuererträge und Finanzausgleich stehen bereits in einer echten Produktegruppe** («Steuern und Finanzausgleich», Departement Finanzen) und werden nicht doppelt erfasst. Was ausserhalb der operativen Produktegruppen bleibt, ist die **interne Verrechnung samt Abgrenzung**; sie wird als **eine künstliche Produktegruppe «Interne Verrechnung / Abgrenzung»** im Departement Finanzen abgebildet, sodass **die Summe aller Produktegruppen samt der künstlichen genau das deklarierte Gesamtergebnis** ergibt. Das **Ergebnis** der künstlichen Produktegruppe folgt dem Gesamtergebnis, das der Stadtrat in der Erfolgsrechnung ausweist («Ertragsüberschuss» oder «Aufwandüberschuss»); die Aufwand-Seite folgt dem Total aus dem Buch. Sie wird einmalig beim Import gebildet und ist danach fix; Anträge und Novemberbrief verschieben die Summe wie bei jeder echten Produktegruppe. Die künstliche Produktegruppe ist **nicht antragbar**: kein «+ Antrag», keine Pauschalkürzung greift auf sie (Pauschalkürzungen verteilen nur auf die echten, operativen Produktegruppen). Die **Summenprobe** prüft an den echten Büchern, dass die Summe aller Produktegruppen genau dem deklarierten Gesamtergebnis entspricht.
    - **Anzeige der künstlichen Produktegruppe:** im **Globalbudget-Tab** beim Departement Finanzen, **nach** den echten Produktegruppen, optisch durch eine **leicht andere Hintergrundfarbe** abgesetzt (aus einem CSS-Token, nie ein harter Farbcode) und **ohne** Antrags-Bedienelemente; im Personalbestand-Tab erscheint sie nicht (sie trägt keine Stellen).
- **F90** Das **Drehbuch zur Budgetbehandlung** (die Beilage der Budgetsitzung, die den Ablauf und alle Anträge der Sitzung führt) ist die Quelle für die Sitzungsanträge und den Novemberbrief. Es wird **im Betrieb von der Parlamentswebseite geladen**: vom Budget-Geschäft über dessen Traktandum zur Budgetsitzung und von deren Seite die Beilage, deren Bezeichnung «Drehbuch» enthält.
    - **Sitzungsanträge einlesen (Sitzungsmodus):** Im Sitzungsmodus erscheint oben rechts «Sitzungsanträge einlesen». Der Knopf lädt das Drehbuch und übernimmt die dort je Produktegruppe behandelten **Kommissions- und Fraktionsanträge** als offizielle Sitzungsanträge. Zu jedem Antrag werden **Quelle** (etwa «AK» oder «Fraktion Muster»), **Richtung und Betrag** (Erhöhung oder Reduktion des Globalkredits in CHF) und die **Begründung** übernommen; das **Abstimmungsergebnis der Kommission** (etwa «11:0 angenommen»; ein Fraktionsantrag, über den die Kommission nicht abgestimmt hat, trägt keines) wird in der Begründung vermerkt. Die eingelesenen Anträge sind **fremde** Anträge und stehen zunächst **offen** (unsere Haltung setzen wir selbst). Das Einlesen ist **wiederholbar, ohne zu duplizieren**: bereits vorhandene, gleich lautende Sitzungsanträge (gleiche Produktegruppe, Antragsteller und Betrag) werden nicht erneut angelegt, und von Hand gesetzte Haltungen und Entscheide bleiben unberührt. Während des Ladens läuft ein Fortschrittsbalken; am Ende meldet eine Benachrichtigung, wie viele neue Anträge dazugekommen sind (oder dass keine neuen vorlagen oder kein Drehbuch gefunden wurde).
    - **Gelesen wird jede Sitzungsunterlage, die das Parlament veröffentlicht hat:** die Drehbücher 2022 bis 2026. Ihr Aufbau hat sich geändert: Von 2023 an nennt der Antragssatz Richtung und Betrag («Antrag AK: Reduktion des Globalkredits um CHF 100'000»), mit Abweichungen im Wortlaut («Globalbudget» statt «Globalkredit», ein Einschub zwischen Gegenstand und Betrag). Das Drehbuch 2022 nennt im Satz nur die Begründung; **Betrag und Gremium stehen dort allein in den Spalten «Antrag Kom.» und «Antrag Fraktion»** der Nettokosten-Zeile, und die Kommissionen hiessen anders (die «BBK» gibt es später nicht mehr). Ein Antrag zu einer **parlamentarischen Zielvorgabe** oder zu einem **Verpflichtungskredit** steht in einer eigenen Tabelle derselben Produktegruppe und ist **kein** Antrag auf den Globalkredit; ein Antrag auf einen anderen **Steuerfuss** wird als solcher geführt.
    - **Novemberbrief:** Die nachträglichen Korrekturen des Stadtrats zum Budgetentwurf stehen bis zum Budget 2022 in einer **eigenen Beilage «Novemberbrief»** am Budget-Geschäft, danach in der Spalte «NB» der Nettokosten-Tabelle des Drehbuchs. Beide Quellen werden gelesen, die Beilage zuerst. Sie führt je Produktegruppe die Korrektur von **Aufwand, Ertrag und Nettokosten** und rechnet in der Zeile «Stadt Winterthur» ihre Summe vor; die Spalte des Drehbuchs nennt nur die Nettokosten. Führt keine der beiden Quellen Korrekturen (wie im Budget 2026), gibt es für das Jahr keinen Novemberbrief und der Knopf erscheint nicht.
- **F91** Für das Einlesen von Hand und für Tests: Über «+ Neu» lässt sich gezielt ein Budgetjahr **importieren**, auch ein vergangenes, wahlweise nur das Budget oder zusammen mit dem Novemberbrief. Die Auswahl bietet die Jahre an, für die auf der Parlamentswebseite ein Budget-Geschäft steht und die noch nicht in der Datenbank sind (kein Freitext); der Status des Geschäfts zählt dabei nicht. Von selbst liest die App nur das neueste dieser Jahre ein, sobald es in der Datenbank fehlt (F89); den Stichtag des 1. Dezember nennt F75. **Wenn der Novemberbrief erscheint, werden seine Anpassungen automatisch abgerufen und an den richtigen Stellen übernommen.** **Offen:** Das tut die App noch nicht; der automatische Lauf liest keinen Novemberbrief ein. Ist für ein Jahr nur das Budget in der Datenbank und ist der Novemberbrief da, aber noch nicht eingelesen, lässt er sich von Hand einlesen. Der Knopf «Novemberbrief einlesen» steht oben rechts neben «+ Neu» und erscheint nur, wenn für das betreffende Jahr ein Budget in der Datenbank liegt und zusätzlich ein mindestens zwei Tage alter Novemberbrief vorliegt, der noch nicht in der Datenbank ist. Andernfalls ist der Knopf nicht vorhanden. Als vorliegend gilt der Novemberbrief, wenn eine Quelle tatsächlich Korrekturen führt, die Beilage «Novemberbrief» oder die Spalte «NB» des Drehbuchs (F90), und als eingelesen, sobald das Jahr so markiert ist. **Offen:** Das Alter von zwei Tagen prüft die App noch nicht; der Knopf erscheint, sobald eine Quelle Korrekturen führt. **Eingelesen wird als Korrektur:** Der Novemberbrief nennt Änderungen, keine neuen Beträge; sie werden auf den Stand des Budgetentwurfs addiert, je Produktegruppe auf Aufwand, Ertrag und Globalkredit. **Jedes Jahr nimmt sie genau einmal an:** Ein zweiter Aufruf ändert nichts mehr. Wird das Jahr neu eingelesen, stehen wieder die Zahlen des Budgetentwurfs da, und der Novemberbrief wird erneut angeboten.
    - **Budget neu einlesen (destruktiv, doppelt abgesichert):** Ein bereits importiertes Budgetjahr lässt sich über «Budget neu einlesen» (im Kopf der Budgetseite) vollständig neu aus dem Budgetbuch einlesen. Dabei werden **alle bestehenden Anträge, Notizen, Pauschalanträge und Entscheide zu diesem Budget unwiederbringlich gelöscht**. Wegen dieser Gefahr geschieht das **nur nach doppelter Bestätigung**: ein Dialog «Bist du ganz sicher?» mit einem Kontrollkästchen «Ich verstehe, dass alle bestehenden Anträge, Notizen, usw. zu diesem Budget dabei unwiederbringlich gelöscht werden»; der Knopf «Neu einlesen» bleibt gesperrt, bis das Kontrollkästchen gesetzt ist. Für die Administration gibt es denselben Vorgang auf der Kommandozeile, auch als vollständig leeren Neuanfang (README, «Synchronisation»).

### Anträge einreichen

- **F92** Ein **PDF mit den Anträgen der Fraktion** lässt sich erzeugen, insgesamt oder getrennt nach Kommission, damit die Fraktion in der Kommission ein fertiges Antrags-PDF einreichen kann. Das PDF enthält die **eigenen einzureichenden Anträge** (Schalter «Antrag stellen» ein, F97); eine **Option (Schalter)** nimmt zusätzlich die **von uns unterstützten fremden Anträge** auf (Schalter «Unterstützen» ein). Ein Antrag, den wir nicht einreichen, und ein Pauschalantrag mit abgeschaltetem Einreichen-Schalter (F100) erscheinen nicht. Als **Antragsteller steht im PDF immer die Fraktion** (im Rat stellt die Fraktion die Anträge); die eingetragene Person erscheint nur im Tab «Anträge» am Bildschirm. Die automatische Steuerfuss-Senkung (F88) steht als eigener Antrag am Ende der Liste. Der PDF-Knopf steht im Anträge-Tab (F102).
- **F93** In den Sitzungen, in denen das Budget traktandiert ist, lassen sich die **Beschlüsse laufend mitführen**: Jeder gestellte Antrag (eigene wie fremde) wird aus der Einladung oder den Dokumenten übernommen und angezeigt. Zu Beginn sind alle Anträge «noch nicht entschieden». Während der Sitzung trägt ein Fraktionsmitglied ein, ob ein Antrag angenommen oder abgelehnt wurde; ein Antrag lässt sich auch wieder auf «noch nicht entschieden» zurücksetzen. Spontane Anträge lassen sich in der Sitzung erfassen. Anträge, die in einer Sitzung erfasst wurden, erscheinen auch in vorhergehenden und folgenden Sitzungen, in denen dasselbe Budget behandelt wird, so wie die Sitzungsnotizen an einem Geschäft (F25) bei Verschiebungen und bei mehreren Sitzungen zum selben Thema überall stehen.

### Angaben am Antrag

- **F94** Jeder Antrag hat eine **Herkunft**: «eigen» (Standard) oder «fremd», mit derselben Bedienung wie beim Vorstoss (F30, F32). Der **Antragsteller** wird immer aus einer Liste gewählt, nie als Freitext, und ist **standardmässig die eigene Fraktion** (im Rat stellt die Fraktion die Anträge): bei eigenen Anträgen steht die **eigene Fraktion zuoberst** und ist vorbelegt, darunter die Personen der eigenen Fraktion (eine Person lässt sich statt der Fraktion wählen); bei fremden Anträgen eine **Fraktion** (zuoberst) oder eine Person. Die eigene Fraktion stammt aus der Konfiguration (dieselbe Quelle wie das PDF), die übrigen Personen und Fraktionen aus denselben Quellen wie beim Vorstoss (aktive Mitglieder, aktive Fraktionen).
- **F95** Ein Antrag trägt seinen Betrag **in CHF und in Prozent**, mit einem **Umschalter Reduktion/Mehrausgabe**: Standard ist eine Reduktion (Kürzung), umgeschaltet eine Mehrausgabe. Die Richtung ist allein das **Vorzeichen** (kein eigenes Richtungsfeld). Prozent bezieht sich auf den **Budgetwert der Position** (Beispiel: Kürzung um 20'000 bei einem Budget von 100'000 = −20%). Wird der Betrag in **Prozent** eingegeben, wird der CHF-Betrag berechnet; eine Eingabe im **CHF**-Feld rechnet sofort den Prozentwert nach. Führend ist der zuletzt eingegebene Wert; ändert sich später die Basis, etwa durch den Novemberbrief, bleibt der CHF-Betrag fix.
- **F96** Der **Steuerfuss-Antrag** wird in **Prozentpunkten** gestellt (Senkung oder Steigerung), bezogen auf 100% = kantonaler Steuersatz. Der Steuerfuss wirkt direkt auf die erwarteten Steuereinnahmen, die sich im selben Verhältnis ändern: bei einer Änderung von X auf Y Prozentpunkte gilt «neuer Ertrag = bisheriger Ertrag × Y/X». −2 Prozentpunkte bei einem Steuerfuss von 125% bedeuten also 123% und einen Ertrag von bisher × 123/125. In CHF ist das nur eine Schätzung; massgeblich ist der Prozentpunkt-Wert.
- **F97** Jeder Antrag trägt **unsere Haltung** als einfacher **Schalter** an der Antragszeile, getrennt vom Sitzungs-Beschluss (F93): bei eigenen Anträgen **«Antrag stellen»** (ein = wir reichen ihn ein, Standard bei einem neu angelegten eigenen Antrag), bei fremden Anträgen **«Unterstützen»** (ein = wir unterstützen ihn; ein neu erfasster fremder Antrag startet ausgeschaltet). Nur eingeschaltete Anträge fliessen ins Fraktionsbudget und erscheinen in der Antragsliste; eingeschaltete eigene Anträge erscheinen auch im Antrags-PDF, fremde nur mit der Option des PDF (F92). Ein ausgeschalteter fremder Antrag zählt nirgends. Gelöscht wird ein Antrag mit **«✕»** (kein Papierkorb-Symbol). Der Beschluss (angenommen oder abgelehnt, ✓ oder ✕) erscheint nur im **Sitzungsmodus**, nicht in der Vorbereitung.
- **F98** Zu jeder Antragsposition lässt sich mit einer **Mehrfachauswahl** festhalten, **welche Fraktionen den Antrag unterstützen**. Haben wir für den Antrag Zustimmung beschlossen (F97), ist die eigene Fraktion automatisch ausgewählt.
- **F99** Auf jede Position (Produktegruppe, Projekt) lassen sich **beliebig viele Anträge** stellen; ihre Beträge **summieren sich**. Spezifische Anträge und Pauschalanträge mischen sich in beliebiger Zahl und Kombination.
    - **Gekürzt wird ohne feste Grenze.** Eine Position lässt sich über ihr Budget hinaus kürzen: Wer mehr streicht, als sie kostet, will aus ihr Ertrag statt Aufwand machen, und das ist ein zulässiges Ziel. Ebenso dürfen sich mehrere Anträge auf dieselbe Position zu mehr als 100% summieren, und nach oben gibt es keine Grenze. Der beeinflussbare Anteil (F128) ist die Orientierung und keine Sperre: Er steht an der Position; die Prozentangabe eines Antrags bezieht sich auf den Budgetwert der Position (F95). Die einzige harte Grenze trägt die automatische Pauschalverteilung: Sie verteilt nur auf den beeinflussbaren Aufwand und kürzt eine Position höchstens um ihn (F112); was eine Position nicht mehr trägt, geht auf die übrigen, und reicht auch das nicht, bleibt der Rest liegen (sichtbar an der Summe).
- **F103** Jeder Antrag kann **Notizen** tragen, mit demselben formatierten Textbereich, derselben Bedienung und derselben Speicherung wie Notizen an allen anderen Objekten (F24).

### Pauschalanträge, Übersicht und Sitzungsverknüpfung

- **F100** Ein **Pauschalantrag** verteilt anteilig zum beeinflussbaren Aufwand (F128) auf die Positionen und erzeugt je Position einen Einzelantrag. Sein **Ziel-Typ** (F84) bestimmt, was verteilt wird: eine **Einsparung** in **CHF** oder in **Prozent** des **ursprünglichen** Aufwands (nie eines bereits gekürzten), oder ein **absolutes Ziel** (schwarze Null, fester Ertrag oder festes Defizit), das das Gesamtergebnis auf diesen Wert ausgleicht. Jeder Pauschalantrag hat einen **Einreichen-Entscheid** (F97): wird er eingereicht, erscheint je nicht ausgenommene Position ein Einzelantrag im Antrags-PDF; wird er nicht eingereicht, keiner. Jeder trägt als **Antragsteller** eine **Fraktion** (F94), vorbelegt mit der **eigenen Fraktion**, und dieser Antragsteller überträgt sich auf die erzeugten Einzelanträge. **Beliebig viele** Pauschalanträge lassen sich anlegen; ihre Kürzungen kumulieren. Alle stehen im gemeinsamen Kasten **«Pauschalanträge»** und werden gleich behandelt; das eine absolute Ziel (F84) wird **zuletzt** gerechnet, auf dem bereits durch die Einsparungen gekürzten Stand. Änderungen an einem Pauschalantrag werden **automatisch übernommen** (kein «Übernehmen»-Knopf), kurz nach der letzten Eingabe.
- **F101** Eine einzelne Produktegruppe lässt sich vom Pauschalantrag **ausnehmen**, auf **zwei** Wegen, die sich entsprechen: über einen **Schalter direkt an der Produktegruppe** (je Pauschalantrag einer; ausgeschaltet heisst ausgenommen, angezeigt als «— ausgenommen») **oder** über die **Mehrfachauswahl «Ausnahmen (Produktegruppen)»** im Pauschalantrag selbst. Beide Wege zeigen **jederzeit denselben Stand**: was an der Produktegruppe ausgenommen oder wieder aufgenommen wird, steht sofort auch in der Mehrfachauswahl, und umgekehrt. Für eine ausgenommene Position entsteht kein Einzelantrag ins PDF. Der einzusparende **Gesamtbetrag bleibt gleich**: Er verteilt sich neu auf die verbleibenden Positionen.
- **F102** Zuoberst steht die **Übersicht** als **Vergleich** zwischen dem **Stadtratsbudget** (die Zahlen, wie der Stadtrat sie vorgelegt hat, ohne unsere Anträge) und dem **Fraktionsbudget** (mit unseren Anträgen); eine **Differenz**-Zeile zeigt, was unsere Anträge bewirken. Verglichen werden Ausgaben, Einnahmen, Ergebnis (Ertrag oder Defizit), Steuerfuss und Stellen; die Übersicht richtet sich nach den aktiven Filtern. In das Fraktionsbudget zählt nur, **was die Fraktion unterstützt**; unentschiedene oder abgelehnte Anträge zählen nicht. Im **Sitzungsmodus** zählen stattdessen die vom Parlament **angenommenen** (beschlossenen) Anträge. So ist laufend sichtbar, wie viel das eigene korrigierte Budget bringt und was in der Sitzung tatsächlich beschlossen wurde.
- **F102a** Unter der Übersicht stehen die Bereiche als **Tabs** (Globalbudgets, Personalbestand, Investitionsrechnung, Steuerfuss) plus ein Tab **«N Anträge»** (N = Anzahl der Anträge), das **alle Anträge nach Departement zusammengefasst** zeigt (Position, Betrag oder Stellen, Antragsteller, Begründung, Beschluss). So stehen alle Anträge auf einen Blick da, ohne das PDF zu erzeugen; dort steht auch der Knopf **«Anträge als PDF»** samt der Option, die unterstützten fremden Anträge mitzunehmen (F92). **Übersicht und Tabs bleiben beim Scrollen als ein Block am oberen Rand** (die Tabs hängen unten an den Übersichtszahlen). Der Link zur **Weisung** steht im Kopf ganz rechts. Der Filter aller Listen (auch Budget) trägt unten einen einheitlichen Knopf **«Filter zurücksetzen»**.
- **F104** Ein Vorbereitungs-Antrag wird mit dem tatsächlich eingereichten **Sitzungsantrag verknüpft**: **automatisch, wenn eindeutig** (gleiche Position und gleicher Betrag), und immer **von Hand korrigierbar**. Über die Verknüpfung kommen die Haltung und der Entscheid in die Sitzung; für alle nicht verknüpften Sitzungsanträge bleibt offen, ob wir sie unterstützen, bis wir entscheiden.

## Fragestunde

- **F114** Die Ansicht **«Fragestunde»** (in der Navigation direkt hinter «Budget») sammelt die Fragen der Fraktion für die **Fragestunde des Parlaments**. Das Parlament hält sie zweimal im Jahr; die Fragen sind **bis spätestens am Donnerstag vor der Fragestunde schriftlich beim Parlamentsdienst einzureichen** und dürfen **nicht mehr als 1'000 Zeichen** umfassen (Art. 103 Abs. 2 der Organisationsverordnung des Stadtparlaments). Jedes Mitglied reicht **eine** Frage ein.
    - **Fragen werden jederzeit gesammelt, ohne angesetzte Fragestunde.** Wem etwas auffällt, trägt es ein («+ Neue Frage»); die Fraktion nutzt es später in irgendeiner Fragestunde. Die Seite führt **alle Fragen**, die neueste zuoberst, und jede sagt, welcher Fragestunde sie zugeteilt ist («noch keiner zugeteilt», solange keine gewählt ist).
    - **Eine Fragestunde ist freiwillig und wird mit ihrem Datum angelegt** («+ Neue Fragestunde»). Titel («Fragestunde vom 2. März 2026») und **Frist** (der Donnerstag davor) ergeben sich daraus; beide lassen sich überschreiben. Die angesetzten Fragestunden stehen über den Fragen, jede mit Datum, Titel, Frist und der Zahl der ihr zugeteilten Fragen.
    - **Die Zuteilung geschieht im Feld «Fragestunde» der Frage** und lässt sich jederzeit ändern oder lösen.
    - **Jedes Fraktionsmitglied trägt seine Fragen selbst ein.** Zu einer Frage gehören: **wer sie eingebracht hat** (vorbelegt mit dem angemeldeten Mitglied), die **Frage** selbst, ein **Kommentar** für die Fraktionssitzung und **Notizen**, mit demselben formatierten Textbereich und denselben Versionen wie am Geschäft und am Vorstoss. Eine gelöschte Notiz einer Frage lässt sich nicht wiederherstellen, weil die Fragestunde keine Aktionszeitleiste trägt.
    - **Die Zeichenzahl steht neben dem Feld** und ist im Verhältnis zur Grenze angegeben («842 / 1000 Zeichen»). Über der Grenze meldet die Maske «bitte kürzen», und **Speichern bleibt gesperrt**; auch der Server nimmt eine zu lange Frage nicht an und nennt in seiner Meldung die Länge, die Grenze und den Artikel der Organisationsverordnung.
    - **In der Fraktionssitzung wird jede Frage besprochen, angepasst und zugeteilt.** Der **Status** einer Frage ist «Neu», «Besprochen», «Eingereicht» oder «Zurückgezogen». Wer sie **einreicht**, wird zugeteilt, sobald die Fraktion die Frage stellt, meist das Mitglied, das sie eingebracht hat, sonst ein anderes, weil jedes Mitglied nur eine Frage einreicht. Solange niemand zugeteilt ist, steht «noch nicht zugeteilt».
    - **Ist einem Mitglied mehr als eine Frage zugeteilt, meldet es die Seite** mit Namen, damit die Fraktion umverteilt.
    - **Der Verweis auf das Geschäft des Parlaments** steht auf der Karte, sobald es abgerufen ist: Das Parlament führt jede Fragestunde als eigenes Geschäft («Fragestunde vom 2. März 2026»), veröffentlicht es aber erst kurz vorher, während die Fraktion ihre Fragen schon lange davor sammelt. Die Verknüpfung wird deshalb nachgeholt, sobald das Geschäft da ist.
    - **Jede Änderung speichert sofort** (wie bei Vorstössen und Geschäften), und alle sehen sie **in Echtzeit**; nur beim Anlegen sammelt die Maske die Eingaben bis zum «Speichern».

## Bedienelemente im Detail

Dieser Abschnitt beschreibt jedes einzelne Bedienelement jeder Ansicht und jedes Dialogs: seine Position, seine Beschriftung, seine Art, ob es ein Pflichtfeld ist, womit es vorbelegt wird, welche Auswahlwerte in welcher Reihenfolge zur Verfügung stehen und woher diese stammen. Beschrieben werden ausserdem das dynamische Verhalten, also was ein Element ein- oder ausblendet, sperrt oder verändert, und der genaue Zeitpunkt, zu dem eine Eingabe gespeichert wird.

### Ansicht «Geschäfte»

#### Suchfeld (in der Navigationsspalte, zuoberst)

1. **Suchfeld:** Beschriftung «Suche», Platzhalter «Nr. oder Titel».
    - Art: Textfeld mit Löschknopf am rechten Rand (Kreuz-Symbol).
    - Pflichtfeld: nein.
    - Vorbelegung: leer (kein gespeicherter Wert, bei jedem Öffnen der Ansicht leer).
    - Dynamisches Verhalten: filtert die Liste bei jedem Tastendruck sofort; verglichen wird gross-/kleinschreibungsunabhängig mit Titel **und** Nummer des Geschäfts und mit den Titeln seiner amtlichen Dokumente. Ab drei Zeichen fragt die Ansicht zusätzlich den Server nach den Geschäften, deren Dokumente den Begriff enthalten (F121); ohne Antwort bleibt es bei Nummer und Titeln. Der Löschknopf erscheint nur, solange etwas eingegeben ist; ein Klick darauf leert das Feld und hebt die Einschränkung sofort auf.
    - Speicherung: keine; das Feld schränkt nur die Anzeige ein.
    - Gesperrt: nie.

#### Filterbereich

Er steht in der Navigationsspalte, unterhalb der Ansichtenliste.

1. **Entscheidungsbedarf:** Beschriftung «Entscheidungsbedarf».
    - Art: Auswahlliste, nicht leerbar (es ist immer genau ein Wert gewählt).
    - Pflichtfeld: ja, im Sinne von «immer gesetzt».
    - Auswahlwerte in dieser Reihenfolge: «Alle», «Nur Entscheid nötig», «Nur ohne offenen Entscheid». Die Liste ist fest vorgegeben und hängt nicht von den Daten ab.
    - Vorbelegung: «Alle». Ausnahme: Ist der Sitzungsmodus eingeschaltet, stellt die Ansicht beim Öffnen automatisch auf «Nur Entscheid nötig» um.
    - Dynamisches Verhalten: Jede Änderung wählt aus der geteilten Liste der Geschäfte aus und fragt den Server nicht (F150). «Nur Entscheid nötig» heisst: Es gibt noch keinen Fraktionsbeschluss, oder das Parlament hat das Geschäft nach dem letzten Fraktionsbeschluss verändert (F23).
    - Speicherung: keine Datenänderung; wirkt sofort bei Auswahl.
    - Gesperrt: nie.
2. **Status:** Beschriftung «Status», Platzhalter «Alle».
    - Art: Mehrfachauswahl (gewählte Einträge stehen im Feld und lassen sich einzeln entfernen; die Liste bleibt nach einer Auswahl offen, bereits gewählte Einträge verschwinden aus der Auswahlliste).
    - Pflichtfeld: nein.
    - Vorbelegung: nichts gewählt (Platzhalter «Alle»).
    - Auswahlwerte: alle Status, die in den aktuell geladenen Geschäften tatsächlich vorkommen, ohne leere Status, jeder genau einmal, alphabetisch nach dem Originalwert sortiert. Angezeigt wird die gekürzte Schreibweise gemäss den in der Verwaltung hinterlegten Kürzelregeln, gespeichert und verglichen wird der volle Originalwert.
    - Dynamisches Verhalten: Ist **genau ein** Status gewählt, verschwindet die Tabellenspalte «Status» (sie wäre redundant); bei keinem oder mehreren gewählten Status ist sie wieder da.
    - Speicherung: keine; wirkt sofort, ohne Neuladen vom Server.
    - Gesperrt: nie.
3. **Typ:** Beschriftung «Typ», Platzhalter «Alle».
    - Art: Mehrfachauswahl.
    - Pflichtfeld: nein.
    - Vorbelegung: nichts gewählt.
    - Auswahlwerte: alle Typen, die in den geladenen Geschäften vorkommen (ohne Leerwerte, ohne Doppelte), alphabetisch sortiert.
    - Dynamisches Verhalten: schränkt die Liste sofort ein; mehrere Typen wirken als «oder».
    - Speicherung: keine.
    - Gesperrt: nie.
4. **Zuständigkeit:** Beschriftung «Zuständigkeit», Platzhalter «Alle».
    - Art: Mehrfachauswahl.
    - Pflichtfeld: nein.
    - Vorbelegung: nichts gewählt.
    - Auswahlwerte: **nur die Mitglieder, die an mindestens einem geladenen Geschäft als Hauptzuständiger eingetragen sind** (Grundprinzip aus F16). Reihenfolge: zuerst alle **aktiven** Mitglieder alphabetisch, danach alle inaktiven oder nicht mehr vorhandenen alphabetisch. Gibt es Geschäfte **ohne** Hauptzuständigen, steht zusätzlich **«Nicht zugewiesen»** an erster Stelle und wählt genau diese Geschäfte aus, weil auch der leere Wert in den Daten vorkommt.
    - Dynamisches Verhalten: Verglichen wird mit dem Hauptzuständigen des Geschäfts; mehrere gewählte Mitglieder wirken als «oder».
    - Speicherung: keine.
    - Gesperrt: nie.
5. **Beschluss:** Beschriftung «Beschluss», Platzhalter «Alle».
    - Art: Mehrfachauswahl.
    - Pflichtfeld: nein.
    - Vorbelegung: nichts gewählt.
    - Auswahlwerte: alle Beschlüsse, die bei den geladenen Geschäften als letzter gültiger Beschluss vorkommen, alphabetisch nach Beschlusstext sortiert. Gibt es mindestens ein Geschäft **ohne** erfassten Beschluss, steht zusätzlich «—» an **erster** Stelle und wählt genau diese Geschäfte aus.
    - Dynamisches Verhalten: schränkt die Liste sofort ein.
    - Speicherung: keine.
    - Gesperrt: nie.
6. **Priorität:** Beschriftung «Priorität», Platzhalter «Alle».
    - Art: Mehrfachauswahl.
    - Pflichtfeld: nein.
    - Vorbelegung: nichts gewählt.
    - Auswahlwerte: **nur die Prioritätsstufen, die bei den geladenen Geschäften tatsächlich gesetzt sind**, in der festen Reihenfolge «Hoch», «Mittel», «Tief»; Stufen ohne ein einziges Geschäft entfallen. Gibt es Geschäfte **ohne** gesetzte Priorität, steht zusätzlich **«Undefiniert»** an erster Stelle und wählt genau diese aus, weil auch die fehlende Priorität in den Daten vorkommt.
    - Dynamisches Verhalten: Der Filter unterscheidet «Undefiniert» (keine Priorität gesetzt) von «Mittel» (ausdrücklich gesetzt) und filtert nach dem gespeicherten Wert. Für die farbliche Hervorhebung der Zeile gilt eine fehlende Priorität als «Mittel».
    - Speicherung: keine.
    - Gesperrt: nie.
7. **Erledigte anzeigen:** Beschriftung «Erledigte anzeigen».
    - Art: Schalter (Ein/Aus).
    - Pflichtfeld: nein.
    - Vorbelegung: ausgeschaltet.
    - Dynamisches Verhalten: Jedes Umschalten wählt aus der geteilten Liste aus und fragt den Server nicht (F150). Ausgeschaltet sind die Geschäfte ausgeblendet, deren Status «erledigt», «abgeschlossen» oder «aufgehoben» enthält, etwa auch «Durch Rechtsmittelinstanz aufgehoben».
    - Speicherung: keine Datenänderung.
    - Gesperrt: nie.
8. **Filter zurücksetzen:** Beschriftung «Filter zurücksetzen».
    - Art: Knopf über die ganze Breite, zurückhaltend dargestellt.
    - Wirkung: leert Suchfeld, Status, Priorität, Typ, Zuständigkeit, Beschluss, Einreicher und Partei, schaltet «Nur Ersteinreicher» und «Erledigte anzeigen» aus, stellt «Entscheidungsbedarf» auf «Alle» und wählt danach wieder aus der geteilten Liste aus (F150).
    - Gesperrt: nie.

#### Kopfzeile der Ansicht

1. **Titel:** «Geschäfte» (feste Beschriftung).
2. **Anzahl:** Zahl ohne Beschriftung; zeigt, wie viele Geschäfte nach allen Filtern und der Suche übrig bleiben, und ändert sich bei jeder Filter- oder Sucheingabe sofort.
3. **«+ Eigenes Geschäft»:** Knopf, hervorgehobene Darstellung.
    - Wirkung: öffnet die vollständige Geschäftsmaske für ein neues Geschäft mit dem Typ «Eigenes Geschäft» (F8, F18). Angelegt wird noch nichts; erst «Speichern» in der Maske legt das Geschäft an, danach geht dieselbe Maske in die Bearbeitung über.
    - Besonderheit: «Abbrechen» verwirft die Eingaben, und es bleibt kein Eintrag zurück; ein Klick neben die Maske verwirft nichts.
    - Gesperrt: nie. «Speichern» ist gesperrt, solange der Titel fehlt oder das Anlegen läuft. Schlägt das Anlegen fehl, erscheint die Meldung «Geschäft konnte nicht erstellt werden: …».

#### Ladeanzeige und Leerzustand

1. **Ladeanzeige:** Während die geteilte Liste der Geschäfte zum ersten Mal geladen wird (F150), steht anstelle von Tabelle und Karten ein Ladekreis. Danach ersetzen Änderungen einzelne Geschäfte an Ort und Stelle, und die Tabelle bleibt stehen.
2. **Leerzustand:** Text «Keine Geschäfte gefunden»; erscheint, wenn nach Suche und Filtern kein Geschäft übrig bleibt, aber nicht während des Ladens.

#### Tabellendarstellung (breite Ansicht)

Die Tabelle erscheint, solange die Inhaltsspalte breit genug ist; wird sie schmäler, wechselt die Darstellung automatisch auf Karten (siehe nächster Abschnitt).

Die Spalten stehen in dieser Reihenfolge von links nach rechts:

1. **«Nr.»:** sortierbar (Klick auf die Spaltenüberschrift).
    - Inhalt je Zeile: Geschäftsnummer fett, darunter das Datum in Kurzform (TT.MM.JJ) und der Typ.
    - Sortierung: Klick sortiert aufsteigend, erneuter Klick auf dieselbe Spalte kehrt die Richtung um. Die Nummern werden dabei so verglichen, dass «2026.9» vor «2026.10» steht.
2. **«Titel»:** sortierbar.
    - Inhalt je Zeile: ein Pfeilsymbol «↗» mit dem Hinweis «Extern öffnen» (nur wenn zum Geschäft ein Link auf die Parlamentswebseite hinterlegt ist; öffnet ihn in einem neuen Fenster, ohne die Detailmaske zu öffnen), danach der Titel und, falls vorhanden, die Einreicher, mit Komma getrennt.
3. **«Prio»:** nicht sortierbar; enthält ein direkt bedienbares Auswahlfeld.
    - Art: Auswahlliste, leerbar, Platzhalter «—».
    - Vorbelegung: die am Geschäft gespeicherte Priorität; ist keine gesetzt, bleibt das Feld leer («—»), auch wenn für die Einfärbung «Mittel» als Standard gilt.
    - Auswahlwerte, feste Reihenfolge: «Hoch», «Mittel», «Tief»; zusätzlich Leeren möglich.
    - Speicherung: sofort bei der Auswahl. Danach wird nur dieses Geschäft neu geladen (F150), und Sortierung und Einfärbung folgen.
    - Dynamisches Verhalten: «Hoch» hinterlegt die Zeile dezent farbig, «Tief» stellt sie abgeschwächt dar.
    - Gesperrt: nie. Ein Klick in dieses Feld öffnet **nicht** die Detailmaske.
4. **«Status»:** sortierbar; **bedingte Sichtbarkeit**: diese Spalte erscheint nur, wenn im Filter «Status» **nicht genau ein** Status gewählt ist.
    - Inhalt: der Status in gekürzter Schreibweise, farbig hinterlegt; der vollständige Status erscheint als Hinweistext beim Darüberfahren. Farbgebung: «pendent», «offen» und «laufend» als offen, «erledigt», «abgeschlossen» und «aufgehoben» als erledigt, «abgelehnt» und «zurückgezogen» als abgelehnt, alles Übrige neutral.
5. **«Zuständig»:** nicht sortierbar; enthält ein direkt bedienbares Mehrfachauswahlfeld.
    - Art: Mehrfachauswahl, leerbar, Platzhalter «—».
    - Vorbelegung: die am Geschäft eingetragenen Zuständigen. Wer nicht mehr in der Auswahlliste steht, bleibt trotzdem als Eintrag im Feld sichtbar.
    - Auswahlwerte: **nur aktive** Fraktionsmitglieder, die zusätzlich ein Benutzerkonto haben, alphabetisch nach vollem Namen sortiert. Bereits gewählte Mitglieder verschwinden aus der Auswahlliste.
    - Speicherung: sofort bei jeder Änderung. Der zuerst gewählte gilt als Hauptzuständiger; ist der bisherige Hauptzuständige weiterhin ausgewählt, bleibt er es. Danach wird nur dieses Geschäft neu geladen (F150).
    - Gesperrt: nie. Ein Klick in dieses Feld öffnet **nicht** die Detailmaske.
6. **«Beschluss»:** nicht sortierbar; enthält das Beschlussfeld (Beschreibung des Bedienverhaltens siehe «Beschlussfeld» unter «Geteilte Bedienelemente»).
    - Vorbelegung: der letzte gültige Beschluss des Geschäfts. Ist er als freier Text erfasst, erscheint dieser Text.
    - Auswahlwerte: die für dieses Geschäft je nach Typ und Status zulässigen Beschlüsse (F22), etwa «Zustimmen», «Ablehnen», «Stimmfreigabe», «Miteinreichen als Fraktion», «Miteinreichen einzelne Personen», «Überweisung befürworten», «Überweisung ablehnen», «Kenntnisnahme positiv», «Kenntnisnahme negativ», «Nachbericht verlangen», «Erheblich erklären», «Abschreiben». Die Liste unterscheidet sich also von Geschäft zu Geschäft.
    - Speicherung: sofort, sobald die Eingabe abgeschlossen ist. Leeren des Feldes nimmt den Beschluss zurück. Danach wird nur dieses Geschäft neu geladen (F150).
    - Gesperrt: nie in der Liste. Ein Klick in dieses Feld öffnet **nicht** die Detailmaske.

Für die Tabellenzeile als Ganzes gilt:

7. **Zeile anklicken** öffnet die Geschäfts-Detailmaske. Dasselbe leisten die Tasten «Eingabe» und «Leertaste», wenn die Zeile den Fokus hat. Klicks und Tastendrücke innerhalb der drei Bedienfelder (Prio, Zuständig, Beschluss) öffnen die Maske nicht.
8. **Einfärbung der Zeile**: Priorität «Hoch» dezent hervorgehoben, «Tief» abgeschwächt, als gelöscht markierte Geschäfte insgesamt abgeschwächt.
9. **Standardsortierung**: nach Datum, neueste zuerst, bis eine Spaltenüberschrift angeklickt wird.

#### Kartendarstellung (schmale Ansicht)

Erscheint automatisch, wenn die Inhaltsspalte zu schmal für die Tabelle wird. Sie zeigt dieselben Geschäfte in derselben Reihenfolge. Aufbau einer Karte von oben nach unten:

1. **Kopfzeile links**: Pfeilsymbol «↗» mit Hinweis «Extern öffnen» (nur bei hinterlegtem Link), danach die Geschäftsnummer; steht keine Nummer zur Verfügung, erscheint «Ohne Nummer».
2. **Titel** des Geschäfts.
3. **Status** rechts in der Kopfzeile: gekürzter Status oder «—», wenn kein Status vorhanden ist. Er ist hier **immer** sichtbar, unabhängig vom Statusfilter.
4. **Wertepaar «Typ»:** Anzeige, nicht bearbeitbar; «—», wenn leer.
5. **Wertepaar «Datum»:** Anzeige in Schweizer Schreibweise; «—», wenn leer.
6. **Wertepaar «Zuständigkeit»:** Anzeige des Hauptzuständigen; «—», wenn leer.
7. **Priorität:** dasselbe Auswahlfeld wie in der Tabelle, hier zusätzlich mit sichtbarer Beschriftung «Priorität» und Platzhalter «—»; speichert sofort.
8. **Beschluss:** dasselbe Beschlussfeld wie in der Tabelle, Platzhalter «—»; speichert sofort.
9. **Karte anklicken** (oder Eingabe/Leertaste bei Fokus) öffnet die Detailmaske; Klicks auf Priorität und Beschluss nicht.
10. Einfärbung nach Priorität und «gelöscht» wie bei der Tabellenzeile.

#### Detailmaske aus der Liste heraus

1. **Kopfzeile der Maske:** enthält ausser dem Schliessknopf nichts.
2. **Schliessknopf «✕»:** Hinweistext «Dialog schliessen». Wirkung: schliesst die Maske. Er erscheint **nur beim Bearbeiten**; beim Anlegen stehen stattdessen unten «Speichern» und «Abbrechen».
3. **Klick neben die Maske** (auf die abgedunkelte Fläche) schliesst sie beim Bearbeiten mit derselben Wirkung; beim Anlegen bewirkt er nichts (F8).
4. Nach jedem Speichern in der Maske lädt die Liste nur dieses Geschäft neu (F150).

---

### Geschäfts-Detailmaske

Die Maske ist für alle Geschäfte gleich aufgebaut. Bei **selbst angelegten** Geschäften («+ Eigenes Geschäft») sind Titel, Beschreibungstext, Typ, Status, Datum und Kommission zusätzlich bearbeitbar; bei Geschäften von der Parlamentswebseite sind sie reine Anzeige, weil die nächste Synchronisation sie aus der Quelle überschreiben würde.

#### Zustände beim Öffnen

1. Solange geladen wird: Text «Lade Geschäft...».
2. Schlägt das Laden fehl: Text «Geschäft konnte nicht geladen werden.»; es erscheint kein Formular.

#### Kopfbereich

1. **Überzeile:** die Geschäftsnummer; bei einem selbst angelegten Geschäft ohne Nummer steht dort «Eigenes Geschäft».
2. **Titel:**
    - Bei selbst angelegten Geschäften: Textfeld, Platzhalter «Titel des Geschäfts», Beschriftung für Sprachausgabe «Titel». Pflichtfeld: ja; beim Anlegen bleibt «Speichern» gesperrt, solange der Titel fehlt. Vorbelegung: der gespeicherte Titel, bei einem neuen Geschäft leer. Speicherung beim Bearbeiten: beim Verlassen des Feldes oder sobald die Änderung abgeschlossen ist, mit der Rückmeldung «Gespeichert» oder «Geschäft konnte nicht gespeichert werden: …».
    - Sonst: reine Anzeige des Titels.
3. **Fraktionsstatus** rechts: Text «Offen», «Neu zu entscheiden» oder «Entschieden», farbig hinterlegt: «Neu zu entscheiden» wie offen, «Entschieden» wie erledigt, «Offen» neutral. Der Wert folgt aus F23: ohne Beschluss «Offen», nach einer Änderung des Parlaments seit dem letzten Beschluss «Neu zu entscheiden», sonst «Entschieden».
    - Der Titel nutzt die ganze Zeilenbreite; der Fraktionsstatus steht rechts daneben, nie darunter.
4. **Beschreibungstext:** **bedingte Sichtbarkeit**: nur bei selbst angelegten Geschäften, direkt unter dem Titel (vor den öffentlichen Informationen). Formatierter Textbereich mit Werkzeugleiste (siehe «Formatierter Textbereich» unter «Geteilte Bedienelemente»), Platzhalter «Worum geht es?». Kein Pflichtfeld. Vorbelegung: der gespeicherte Text, bei einem neuen Geschäft leer. Speicherung beim Verlassen des Textbereichs. Bei Geschäften von der Parlamentswebseite entfällt er, weil der Text dort auf der Parlamentswebseite steht.

#### Bereich «Öffentliche Informationen»

Tabelle mit Beschriftung links und Wert rechts, von oben nach unten:

1. **«Nummer»:** Anzeige, nie bearbeitbar.
2. **«Typ»:** bei selbst angelegten Geschäften Auswahlliste, deren Werte der Administrator pflegt (Verwaltungsbereich «Typen für eigene Geschäfte»). Sonst Anzeige. Kein Pflichtfeld. Vorbelegung: gespeicherter Wert, bei neu angelegten Geschäften «Eigenes Geschäft». Speicherung sofort bei der Auswahl, Rückmeldung «Gespeichert». Ist keine Typenliste konfiguriert, steht dort nur «Eigenes Geschäft».
3. **«Status»:** bei selbst angelegten Geschäften dasselbe Bedienelement wie das Beschlussfeld: eine Auswahl aus den Werten, die in der Datenbank bereits vorkommen, jederzeit mit einem frei eingetippten Wert überschreibbar. Sonst Anzeige des Status in gekürzter Schreibweise. Kein Pflichtfeld. Speicherung sofort.
4. **«Fraktionsstatus»:** Anzeige «Offen», «Neu zu entscheiden» oder «Entschieden» nach F23, nie bearbeitbar.
5. **«Datum»:** bei selbst angelegten Geschäften Datumsfeld (Sprachausgabe-Beschriftung «Datum»), sonst Anzeige in Schweizer Schreibweise. Kein Pflichtfeld. **Vorbelegung beim Anlegen: das heutige Datum.** Speicherung sofort. Ein unsinniges Datum (nicht JJJJ-MM-TT) wird mit einer Meldung abgewiesen.
6. **«Kommission»:** **bedingte Sichtbarkeit**: bei selbst angelegten Geschäften eine leerbare Auswahlliste (Platzhalter «—»), die **nur aktive** Kommissionen in gekürzter Schreibweise anbietet (gespeichert wird der volle Name); höchstens eine ist wählbar, «keine» (leer) ist gültig. Bei Geschäften von der Parlamentswebseite nur angezeigt, wenn eine Kommission hinterlegt ist (dann in gekürzter Schreibweise). Speicherung sofort bei der Auswahl, auch beim Leeren.
7. **«Einreicher»:** **bedingte Sichtbarkeit**: nur, wenn zum Geschäft mindestens ein Einreicher hinterlegt ist. Anzeige: Name, dahinter in Klammern die Rolle, mehrere durch Komma getrennt. Nie bearbeitbar.
8. **«Letzte externe Änderung»:** Zeitpunkt der letzten Änderung an der Quelle in Schweizer Schreibweise; «—», wenn unbekannt. Nie bearbeitbar.
9. **«Letzte Fraktionsentscheidung»:** Zeitpunkt des letzten Fraktionsbeschlusses; «—», wenn noch keiner gefasst wurde. Nie bearbeitbar.
10. **«Link»:** **bedingte Sichtbarkeit**: nur wenn ein Link hinterlegt ist. Verweis mit dem Text «Auf Parlamentswebseite öffnen ↗», öffnet ein neues Fenster.

#### Bereich «Fraktionsinterne Bearbeitung»

1. **«Priorität»**
    - Art: Auswahlliste, leerbar, Platzhalter «—».
    - Pflichtfeld: nein.
    - Vorbelegung: die gespeicherte Priorität des Geschäfts; ist keine gesetzt, bleibt das Feld leer.
    - Auswahlwerte, feste Reihenfolge: «Hoch», «Mittel», «Tief».
    - Speicherung: sofort bei der Auswahl, auch beim Leeren. Rückmeldung «Priorität gespeichert» oder «Priorität konnte nicht gespeichert werden: …».
    - Gesperrt: nie.
2. **«Zuständigkeit»**
    - Art: Mehrfachauswahl, leerbar, Platzhalter «—»; gewählte Mitglieder stehen als einzeln entfernbare Einträge im Feld und verschwinden aus der Auswahlliste.
    - Pflichtfeld: nein.
    - Vorbelegung: die am Geschäft eingetragenen Zuständigen; beim Anlegen eines neuen eigenen Geschäfts ist der Erfasser vorausgewählt, sofern er Fraktionsmitglied mit Benutzerkonto ist (vor dem Speichern noch änderbar).
    - Auswahlwerte und Reihenfolge: zuerst alle **aktiven** Fraktionsmitglieder **mit Benutzerkonto**, alphabetisch nach vollem Namen; danach alle **inaktiven** Mitglieder mit Benutzerkonto, ebenfalls alphabetisch, jeweils mit dem Zusatz «(inaktiv)» hinter dem Namen. Mitglieder ohne Benutzerkonto stehen nicht zur Wahl.
    - Hinweistext direkt unter dem Feld: «Falls mehrere Personen ausgewählt sind, wird die erste Auswahl intern als Hauptzuständigkeit geführt.»
    - Dynamisches Verhalten: Hauptzuständiger wird automatisch das erste ausgewählte Mitglied, sobald der bisherige Hauptzuständige nicht mehr ausgewählt ist.
    - Speicherung: sofort bei jeder Änderung; unveränderte Auswahl löst kein Speichern aus. Rückmeldung «Zuständigkeiten gespeichert» oder «Fehler beim Speichern der Zuständigkeiten». Der Vorgang wird zusätzlich in der Aktionszeitleiste festgehalten.
    - Gesperrt: nie.
3. **«Beschluss erfassen»**
    - Art: Beschlussfeld, eine Texteingabe mit Vorschlagsliste (Bedienverhalten siehe «Beschlussfeld» unter «Geteilte Bedienelemente»).
    - Pflichtfeld: nein.
    - Vorbelegung: der letzte gültige Beschluss; als freier Text erfasste Beschlüsse erscheinen als Text.
    - Auswahlwerte: die für Typ und Status dieses Geschäfts zulässigen Beschlüsse nach F22, in der dort genannten Reihenfolge. Ein frei eingegebener Text ausserhalb der Liste ist erlaubt.
    - Speicherung: sofort, sobald die Eingabe abgeschlossen ist (Auswahl aus der Liste, Verlassen des Feldes oder fünf Sekunden ohne weitere Eingabe). Rückmeldung «Beschluss gespeichert». Der Fraktionsstatus wechselt damit auf «Entschieden». Änderungen innerhalb derselben Eingabe aktualisieren denselben Eintrag; nach dem Verlassen des Feldes erzeugt die nächste Änderung einen **neuen** Eintrag in der Zeitleiste, sodass die Beschlussgeschichte erhalten bleibt.
    - Leeren des Feldes nimmt einen bestehenden Beschluss zurück: Rückmeldung «Beschluss zurückgenommen», der Fraktionsstatus fällt auf «Offen» zurück, und die Rücknahme erscheint in der Aktionszeitleiste.
    - **Gesperrt**, wenn der Sitzungsmodus eingeschaltet ist und der angemeldete Benutzer weder Protokollführer noch dessen aktive Stellvertretung ist. Dann erscheint unter dem Feld der Hinweis: «Im Fraktionssitzungsmodus darf nur der Protokollführer Beschlüsse erfassen.»
4. **«Votum im Rat»**
    - Art: formatierter Textbereich mit Werkzeugleiste (siehe «Formatierter Textbereich» unter «Geteilte Bedienelemente»), Platzhalter «Wortlaut des Votums».
    - Pflichtfeld: nein.
    - Vorbelegung: der zuletzt erfasste, noch nicht archivierte Wortlaut; sonst leer.
    - **Gesperrt für alle, die für dieses Geschäft nicht zuständig sind.** Ist man nicht zuständig, bleibt der Wortlaut lesbar, und darunter steht der Hinweis: «Das Votum erfasst die für dieses Geschäft zuständige Person.»
    - Speicherung: kurz nach der letzten Eingabe automatisch sowie sofort beim Verlassen des Feldes. In der Werkzeugleiste erscheint der Stand («Gespeichert» oder «Fehler beim Speichern»).
    - **Verweis auf die Druckfassung** in der Werkzeugleiste (Hinweistext «Votum als PDF drucken»): erscheint, sobald ein Wortlaut erfasst ist, öffnet die Druckansicht in einem eigenen Fenster und löst dort den Drucken-Dialog aus.
        - Die Druckansicht übernimmt die Formatierung des Wortlauts (Absätze, Überschriften, Fett- und Kursivschrift, Aufzählungen, Zitate, Verweise) und gibt ausschliesslich diese Gestaltungselemente aus. Ausführbares wie eingebettete Skripte, Code für Ereignisse an Elementen und Verweise mit ausführbarem Ziel erscheint nicht im Ausdruck; der zugehörige Text bleibt erhalten.
    - **Knopf «Votum archivieren»:** erscheint nur für den Zuständigen und nur, wenn ein Wortlaut erfasst ist. Er sichert zuerst offene Eingaben, legt das Votum als historischen Eintrag in der Aktionszeitleiste ab und leert das Feld für ein neues Votum. Rückmeldung: «Votum archiviert».
5. **«Notizen»**
    - Enthält die Notizenliste des Geschäfts (Bedienelemente siehe «Notizenliste» unter «Geteilte Bedienelemente»).
    - Darunter der aufklappbare Bereich **«Sitzungsnotizen»**: standardmässig **zugeklappt**; hinter dem Wort steht die Anzahl der aktiven Sitzungsnotizen (die Zahl erscheint nur, wenn es mindestens eine gibt). Aufgeklappt enthält er eine zweite, gleich bedienbare Notizenliste, die ausschliesslich die in Sitzungen erfassten Notizen des Geschäfts führt.
6. **«Dokumente zum Geschäft»**
    - Enthält den Dokumentenbereich (siehe «Dokumente» unter «Geteilte Bedienelemente»). Der angezeigte Ablagepfad lautet hier «Fraktion/20_Geschäfte/‹Jahr›/‹Geschäftsnummer›-*», wobei das Jahr aus der Geschäftsnummer stammt. Der Dateiname wird mit der Geschäftsnummer vorangestellt.
    - Dynamisches Verhalten: Die Bedienelemente zum Anlegen und Hochladen erscheinen erst, wenn das Geschäft eine Nummer hat; bei einem eigenen Geschäft ohne Nummer bleiben sie aus.

#### Bereich «Aktionszeitleiste»

Siehe «Aktionszeitleiste» unter «Geteilte Bedienelemente». Sie ist in der Detailmaske immer vorhanden. Ist die Maske aus einem Sitzungstraktandum heraus geöffnet, erscheinen zusätzlich die dort erfassten Traktandennotizen; ein Klick darauf springt zur Sitzung.

#### Bereich «Verknüpfte Vorstösse»

Der ganze Bereich erscheint nur, wenn mindestens ein Vorstoss mit diesem Geschäft verknüpft ist.

1. **Überschrift** «Verknüpfte Vorstösse».
2. Je Vorstoss eine **Zwischenüberschrift** mit dem Titel des Vorstosses; ist eine Art erfasst, folgt « · » und die Art.
3. **Wertepaar «Haltung»:** **bedingte Sichtbarkeit**: nur wenn beim Vorstoss ein Beschluss (Haltung) erfasst ist. Reine Anzeige.
4. **Wertepaar «Zuständigkeit»:** **bedingte Sichtbarkeit**: nur wenn dem Vorstoss mindestens eine Person zugeordnet ist; mehrere Namen mit Komma getrennt. Reine Anzeige.
5. **Notizen des Vorstosses:** je Notiz der Name des Verfassers und der formatierte Notiztext. Reine Anzeige; gelöschte Notizen erscheinen hier nicht. In diesem Bereich lässt sich nichts bearbeiten.

---

### Ansicht «Vorstösse»

#### Suchfeld (in der Navigationsspalte, zuoberst)

1. **Suchfeld:** Beschriftung «Suche», Platzhalter «Titel, Art oder Zuständigkeit».
    - Art: Textfeld mit Löschknopf (Kreuz-Symbol), der nur bei nicht leerem Feld erscheint.
    - Pflichtfeld: nein. Vorbelegung: leer.
    - Dynamisches Verhalten: schränkt die Liste sofort ein; gesucht wird gross-/kleinschreibungsunabhängig in Titel, Art und den Namen der Zuständigen.
    - Speicherung: keine.

#### Filterbereich

Er steht in der Navigationsspalte.

1. **Herkunft:** Beschriftung «Herkunft».
    - Art: Auswahlliste, nicht leerbar.
    - Vorbelegung: «Alle».
    - Auswahlwerte: «Alle» und danach in der festen Reihenfolge «Eigene», «Fremde» **nur die Herkünfte, die bei den geladenen Vorstössen tatsächlich vorkommen** (Grundprinzip aus F16).
    - Wirkung: sofort, ohne Neuladen.
2. **Status:** Beschriftung «Status».
    - Art: Auswahlliste, nicht leerbar.
    - Vorbelegung: «Alle».
    - Auswahlwerte: «Alle» und danach in der festen Reihenfolge «Neu», «Entwurf», «Bereit», «Eingereicht», «Erledigt», «Pausiert» **nur die Status, die bei den geladenen Vorstössen tatsächlich vorkommen**.
    - Wirkung: sofort, ohne Neuladen.

Ein Knopf «Filter zurücksetzen» existiert in dieser Ansicht nicht; die Filter werden über den Wert «Alle» aufgehoben.

#### Kopfzeile der Ansicht

1. **Titel:** «Vorstösse».
2. **Anzahl:** Zahl der nach Suche und Filtern sichtbaren Vorstösse.
3. **«+ Neuer Vorstoss»:** Knopf, hervorgehobene Darstellung.
    - Wirkung: öffnet dasselbe vollständige Formular wie das Bearbeiten, mit dem Fenstertitel «Neuer Vorstoss» (F31). Angelegt wird noch nichts. Als Zuständigkeit ist der angemeldete Benutzer vorbelegt, sofern er Fraktionsmitglied ist; sonst bleibt die Zuständigkeit leer.
    - Gesperrt: nie. Schlägt das Anlegen beim «Speichern» fehl, erscheint «Vorstoss konnte nicht erstellt werden: …».

#### Ladeanzeige und Leerzustand

1. **Ladeanzeige:** Ladekreis, solange die Vorstösse geladen werden.
2. **Leerzustand:** Text «Keine Vorstösse vorhanden»; erscheint, wenn nach Suche und Filtern kein Vorstoss übrig bleibt.

#### Kartendarstellung

Auf breiten Fenstern stehen die Vorstösse in einer Tabelle (F119), auf schmalen als Karten. Eine Karte ist von oben nach unten so aufgebaut:

1. **Überzeile:** Herkunft im Klartext («Eigene» oder «Fremde»); ist eine Art erfasst, folgt « · » und die Art.
2. **Titel:** der Titel des Vorstosses; ohne Titel steht «Ohne Titel».
3. **Status** rechts: «Neu», «Entwurf», «Bereit», «Eingereicht», «Erledigt» oder «Pausiert». Farbgebung: «Erledigt» wie erledigt, «Neu» und «Entwurf» wie offen, alles Übrige neutral.
4. **Wertepaar «Zuständigkeit»:** die Namen der Zuständigen mit Komma getrennt; «—», wenn keine.
5. **Wertepaar «Herkunftsfraktion»:** **bedingte Sichtbarkeit**: nur bei Herkunft «Fremde». Anzeige des gekürzten Fraktionsnamens; «—», wenn keine gewählt.
6. **Wertepaar «Beschluss»:** **bedingte Sichtbarkeit**: nur bei Herkunft «Fremde» **und** wenn ein Beschluss erfasst ist.
7. **«Löschen»:** Knopf in Warnfarbe, unten rechts auf der Karte.
    - Wirkung: fragt zuerst zurück «Vorstoss ‹Titel› wirklich löschen?» (ohne Titel: «Vorstoss ‹Ohne Titel› wirklich löschen?»). Erst nach Bestätigung wird gelöscht und die Liste neu geladen. Fehlermeldung: «Vorstoss konnte nicht gelöscht werden: …».
    - Ein Klick auf «Löschen» öffnet die Bearbeitungsmaske **nicht**.
8. **Karte anklicken** (oder Eingabe/Leertaste bei Fokus) öffnet die Bearbeitungsmaske.
9. Einfärbung: Priorität «Hoch» hebt die Karte dezent hervor, «Tief» schwächt sie ab; Vorstösse ohne gesetzte Priorität gelten dabei als «Mittel».

#### Vorstossmaske

Anlegen und Bearbeiten zeigen **dieselben** Felder. Beim Anlegen lautet der Fenstertitel «Neuer Vorstoss», die Maske sammelt die Eingaben, und erst «Speichern» legt den Vorstoss an (F8, F31). Beim Bearbeiten lautet er «Vorstoss bearbeiten», jede Eingabe speichert sofort, und es gibt weder «Speichern» noch «Abbrechen».

Die Kopfzeile enthält:

1. **Fenstertitel:** «Neuer Vorstoss» oder «Vorstoss bearbeiten».
2. **Schliessknopf «✕»:** Hinweistext «Dialog schliessen». Wirkung: schliesst das Fenster. Er erscheint **nur beim Bearbeiten**; dort wirkt auch ein Klick auf die Fläche neben dem Fenster gleich. Beim Anlegen stehen stattdessen unten «Speichern» und «Abbrechen», und ein Klick neben das Fenster bewirkt nichts.

Die Felder stehen in dieser Reihenfolge von oben nach unten:

1. **«Titel *»:** Der Stern zeigt an, dass das Feld ein Pflichtfeld ist.
    - Art: Textfeld, Platzhalter «Titel des Vorstosses».
    - Pflichtfeld: **ja**. Beim Anlegen bleibt «Speichern» gesperrt, solange der Titel leer ist. Beim Bearbeiten speichert die Maske nichts, solange der Titel leer ist, auch keine andere Eingabe.
    - Vorbelegung: der gespeicherte Titel; bei einem neuen Vorstoss leer.
    - Speicherung beim Bearbeiten: beim Verlassen des Feldes und zusätzlich sofort bei der Eingabetaste. Rückmeldung «Gespeichert» oder «Vorstoss konnte nicht gespeichert werden: …».
    - Dynamisches Verhalten: Nach «Speichern» wechselt der Fenstertitel von «Neuer Vorstoss» auf «Vorstoss bearbeiten», und Dokument, Notizen, Aktionszeitleiste und Geschäft erscheinen.
2. **«Art»**
    - Art: Texteingabe mit Vorschlagsliste (freier Text erlaubt), Platzhalter «Motion, Postulat, Interpellation …».
    - Pflichtfeld: nein.
    - Vorbelegung: die gespeicherte Art; bei neuem Vorstoss leer.
    - Vorschlagswerte in dieser Reihenfolge: «Motion», «Postulat», «Interpellation», «Schriftliche Anfrage», «Beschlussantrag», «Dringliche Motion», «Dringliches Postulat», «Budgetmotion», «Fragestunde», «Einzelinitiative», «Parlamentarische Initiative». Die Liste ist fest hinterlegt; jeder andere Text ist ebenfalls zulässig.
    - Speicherung: sofort bei Auswahl aus der Liste, beim Verlassen des Feldes oder nach fünf Sekunden ohne weitere Eingabe.
3. **«Herkunft»** (erstes von drei nebeneinanderstehenden Feldern)
    - Art: Auswahlliste, nicht leerbar.
    - Pflichtfeld: ja im Sinne von «immer gesetzt».
    - Vorbelegung: «Eigene» bei einem neuen Vorstoss, sonst der gespeicherte Wert.
    - Auswahlwerte in dieser Reihenfolge: «Eigene», «Fremde».
    - **Dynamisches Verhalten**: Die Wahl «Fremde» blendet drei zusätzliche Felder ein: «Beschluss (Haltung zum fremden Vorstoss)», «Herkunft (fremde Fraktion)» und «Ansprechpartner». Ein Wechsel zurück auf «Eigene» blendet sie wieder aus; die bereits erfassten Werte bleiben gespeichert, sind aber nicht mehr sichtbar. Zusätzlich ändert sich der angezeigte Ablagepfad für Dokumente (siehe Feld «Dokument»).
    - Speicherung: sofort bei der Auswahl.
4. **«Status»** (zweites Feld der Dreiergruppe)
    - Art: Auswahlliste, nicht leerbar.
    - Vorbelegung: «Neu» bei einem neuen Vorstoss, sonst der gespeicherte Wert.
    - Auswahlwerte in dieser Reihenfolge: «Neu», «Entwurf», «Bereit», «Eingereicht», «Erledigt», «Pausiert».
    - Speicherung: sofort bei der Auswahl.
    - Dynamisches Verhalten: Wird der Vorstoss mit einem Geschäft verknüpft, springt der Status automatisch auf «Erledigt».
5. **«Priorität»** (drittes Feld der Dreiergruppe)
    - Art: Auswahlliste, leerbar, Platzhalter «—» (dieselbe Auswahl der Priorität wie beim Geschäft).
    - Pflichtfeld: nein.
    - Vorbelegung: die gespeicherte Priorität; bei neuem Vorstoss leer.
    - Auswahlwerte in dieser Reihenfolge: «Hoch», «Mittel», «Tief».
    - Speicherung: sofort, auch beim Leeren.
    - Dynamisches Verhalten: bestimmt die Hervorhebung von Zeile und Karte in der Liste; wird der Vorstoss später mit einem Geschäft verknüpft, übernimmt das Geschäft diese Priorität.
6. **«Zuständigkeit»**
    - Art: Mehrfachauswahl, leerbar, Platzhalter «Personen wählen»; gewählte Mitglieder stehen als einzeln entfernbare Einträge im Feld und verschwinden aus der Auswahlliste.
    - Pflichtfeld: nein.
    - Vorbelegung: bei einem neuen Vorstoss der angemeldete Benutzer, sofern er Fraktionsmitglied ist; sonst die gespeicherten Zuständigen. Wer nicht mehr zur Auswahl steht, bleibt als Eintrag im Feld sichtbar.
    - Auswahlwerte: **nur aktive** Fraktionsmitglieder **mit Benutzerkonto**, alphabetisch nach vollem Namen.
    - Speicherung: sofort bei jeder Änderung.
7. **«Beschluss (Haltung zum fremden Vorstoss)»:** **bedingte Sichtbarkeit: nur bei Herkunft «Fremde»**.
    - Art: Texteingabe mit Vorschlagsliste (freier Text erlaubt), Platzhalter «Miteinreichen, Unterstützen, Ablehnen …».
    - Pflichtfeld: nein.
    - Vorbelegung: der gespeicherte Beschluss.
    - Vorschlagswerte in dieser Reihenfolge: «Miteinreichen», «Unterstützen», «Stimmfreigabe», «Ablehnen», «Ablehnungsantrag stellen». Jeder andere Text ist ebenfalls zulässig.
    - Speicherung: sofort bei Auswahl, Verlassen des Feldes oder nach fünf Sekunden ohne weitere Eingabe.
    - Dynamisches Verhalten: Der Wert erscheint auf der Karte in der Liste als Wertepaar «Beschluss».
8. **«Herkunft (fremde Fraktion)»:** **bedingte Sichtbarkeit: nur bei Herkunft «Fremde»**.
    - Art: Auswahlliste, leerbar, Platzhalter «Fraktion wählen».
    - Pflichtfeld: nein.
    - Vorbelegung: die gespeicherte Fraktion.
    - Auswahlwerte: **nur aktive** Fraktionen **ohne die eigene**, alphabetisch nach der angezeigten Bezeichnung. Angezeigt wird die gekürzte Bezeichnung (Abkürzung) gemäss den hinterlegten Kürzelregeln; gespeichert wird der volle Name.
    - **Dynamisches Verhalten**: Die Wahl bestimmt, welche Personen im Feld «Ansprechpartner» zur Auswahl stehen. Wird eine **andere** Fraktion gewählt, wird eine bereits getroffene Auswahl der Ansprechpartner **geleert**, weil diese zu einer anderen Fraktion gehören.
    - Speicherung: sofort bei der Auswahl (zusammen mit der geleerten Ansprechpartner-Liste).
9. **«Ansprechpartner»:** **bedingte Sichtbarkeit: nur bei Herkunft «Fremde»**.
    - Art: Mehrfachauswahl, leerbar, Platzhalter «Mitglieder der Fraktion».
    - Pflichtfeld: nein.
    - Vorbelegung: die gespeicherten Ansprechpartner; wer nicht mehr in der Auswahlliste steht, bleibt als Eintrag im Feld sichtbar.
    - Auswahlwerte: **nur aktive** Mitglieder **der oben gewählten fremden Fraktion**, alphabetisch nach vollem Namen. Ist keine Fraktion gewählt, ist die Liste leer.
    - **Gesperrt**, solange keine Herkunftsfraktion gewählt ist.
    - Speicherung: sofort bei jeder Änderung.
10. **«Inhalt»**
    - Art: formatierter Textbereich mit Werkzeugleiste (siehe «Formatierter Textbereich» unter «Geteilte Bedienelemente»), Platzhalter «Inhalt des Vorstosses».
    - Pflichtfeld: nein.
    - Vorbelegung: der gespeicherte Inhalt.
    - Speicherung: beim Verlassen des Feldes. Ein Klick auf die eigene Werkzeugleiste gilt **nicht** als Verlassen und löst weder ein Speichern noch einen Verlust der Eingabe aus.
    - Eine Versionsgeschichte gibt es für dieses Feld nicht (die Blätter-Knöpfe erscheinen nur bei Notizen).
11. **«Dokument»**
    - Art: Dokumentenbereich (siehe «Dokumente» unter «Geteilte Bedienelemente»).
    - Angezeigter Ablagepfad: «Fraktion/40_Vorstösse/10_Eigene/‹Jahr›/*» bei Herkunft «Eigene» und «Fraktion/40_Vorstösse/20_Fremde/‹Jahr›/*» bei Herkunft «Fremde»; das Jahr ist das Jahr, in dem der Vorstoss angelegt wurde. Der Pfad **wechselt also sofort mit dem Feld «Herkunft»**.
    - Der Dateiname trägt kein Präfix; als Namensvorschlag dient der Vorstoss-Titel. Der Bereich erscheint erst, wenn der Vorstoss gespeichert ist.
12. **«Notizen»**
    - Art: Notizenliste (siehe «Notizenliste» unter «Geteilte Bedienelemente»), dieselbe wie beim Geschäft.
13. **«Aktionszeitleiste»**
    - Art: Zeitleiste (siehe «Aktionszeitleiste» unter «Geteilte Bedienelemente»), nur Anzeige. Traktandennotizen erscheinen hier nicht.
14. **«Geschäft»**
    - **Bedingte Anzeige**: Ist der Vorstoss bereits mit einem Geschäft verknüpft, steht hier nur der Hinweis «Mit einem Geschäft verknüpft und abgeschlossen.»; einen Knopf gibt es dann nicht, und die Verknüpfung lässt sich hier weder ändern noch lösen.
    - Ist er noch nicht verknüpft, erscheint stattdessen der Knopf **«Mit Geschäft verknüpfen»** (zurückhaltende Darstellung). Er öffnet den Dialog «Mit Geschäft verknüpfen», der die geteilte Liste der Geschäfte nimmt (F150).

#### Dialog «Mit Geschäft verknüpfen»

1. **Fenstertitel:** «Mit Geschäft verknüpfen».
2. **Schliessknopf «✕»:** Hinweistext «Dialog schliessen»; schliesst den Dialog ohne Verknüpfung. Ein Klick neben das Fenster wirkt gleich.
3. **Suchfeld:** Beschriftung «Suche», Platzhalter «Nr. oder Titel».
    - Art: Textfeld (ohne Löschknopf).
    - Pflichtfeld: nein. Vorbelegung: leer; das Feld wird bei jedem Öffnen des Dialogs geleert.
    - Dynamisches Verhalten: schränkt die Liste sofort ein; gesucht wird gross-/kleinschreibungsunabhängig in Titel und Nummer.
4. **Hinweistext:** «Ähnlichste zum Vorstoss-Titel zuerst, sonst neueste.»
5. **Trefferliste:** je Geschäft eine anklickbare Zeile mit der Geschäftsnummer (fett; «—», wenn keine vorhanden) und dem Titel.
    - Inhalt und Reihenfolge: alle nicht gelöschten und nicht erledigten Geschäfte der geteilten Liste (F150), sortiert nach der Zahl gemeinsamer, längerer Wörter mit dem Vorstoss-Titel (grösste Übereinstimmung zuerst); bei gleicher Übereinstimmung nach Datum, neuestes zuerst.
    - Wirkung eines Klicks: Der Vorstoss wird sofort mit dem Geschäft verknüpft, sein Status wechselt auf «Erledigt», eine gesetzte Priorität wird auf das Geschäft übertragen, der Dialog schliesst sich und die Vorstossliste wird neu geladen. Fehlermeldung: «Verknüpfung fehlgeschlagen: …».
6. **Leerzustand:** Text «Keine Geschäfte gefunden.», wenn die Suche nichts liefert.

---

### Geteilte Bedienelemente

#### Beschlussfeld

Wird beim Geschäft in der Tabelle, auf der Karte und in der Detailmaske eingesetzt sowie beim Vorstoss für «Art» und «Beschluss (Haltung zum fremden Vorstoss)».

1. **Eingabefeld mit Vorschlagsliste:** ein einzelnes Textfeld; beim Tippen und beim Klick in das Feld erscheinen die hinterlegten Vorschläge zur Auswahl.
    - Platzhalter: je nach Einsatzort «—», «Motion, Postulat, Interpellation …», «Miteinreichen, Unterstützen, Ablehnen …»; ohne besondere Vorgabe «Beschluss eingeben oder aus Liste wählen…».
    - Pflichtfeld: nein.
    - Vorbelegung: der aktuell gespeicherte Wert im Klartext.
    - Freier Text ist immer erlaubt: Stimmt der eingetippte Text (nach Entfernen führender und folgender Leerzeichen) genau mit einem Vorschlag überein, gilt der Vorschlag als gewählt; sonst wird der Text als freier Eintrag übernommen.
2. **Speicherzeitpunkte**, in dieser Rangfolge:
    - Auswahl aus der Vorschlagsliste: sofort.
    - Verlassen des Feldes: sofort; zusätzlich gilt die Eingabe damit als abgeschlossen, und beim Geschäft beginnt danach ein neuer Eintrag in der Beschlussgeschichte.
    - Tippen ohne Verlassen: automatisch fünf Sekunden nach dem letzten Tastendruck.
    - Wird die Maske geschlossen, während noch eine solche Wartezeit läuft, wird der letzte Stand trotzdem gespeichert.
    - Leert man das Feld vollständig, wird der Wert wie jede andere Eingabe beim Verlassen des Feldes oder nach fünf Sekunden entfernt; beim Geschäft wird damit der Beschluss zurückgenommen.
3. **Gesperrt**, wenn der Einsatzort es vorgibt: beim Geschäft in der Detailmaske im Sitzungsmodus für alle ausser dem Protokollführer und seiner aktiven Stellvertretung. Gesperrt ist das Feld grau und nicht beschreibbar.

#### Mehrfachauswahl

Wird für Zuständigkeit, Ansprechpartner und alle Mehrfachfilter verwendet.

1. **Auswahlfeld:** zeigt die gewählten Einträge im Feld; jeder lässt sich einzeln entfernen.
2. Bereits gewählte Einträge werden **aus der Auswahlliste entfernt** und stehen nur noch im Feld.
3. Die Auswahlliste **bleibt nach einer Auswahl offen**, sodass mehrere Einträge nacheinander gewählt werden können.
4. Ist das Feld leerbar, entfernt ein Klick auf das Kreuz die gesamte Auswahl.
5. Der Platzhalter erscheint nur, solange nichts gewählt ist.

#### Notizenliste

Identisch bei Geschäften (reguläre Notizen und Sitzungsnotizen), bei Vorstössen, bei Stadtratsbeschlüssen, an Sitzungen und Traktanden, bei den Fragen der Fragestunde, an den Budgetanträgen und an den Ebenen des Budgets.

Notizen speichern **ausschliesslich** über das Häkchen «✓»; abgebrochen wird mit «✕». Es gibt **kein** automatisches Zwischenspeichern und **kein** Speichern beim Verlassen des Feldes. Nur die aktiven Notizen erscheinen hier; gelöschte stehen in der Aktionszeitleiste.

1. **Hinweis «Noch keine Notizen vorhanden.»:** erscheint nur, wenn keine aktiven Notizen vorliegen und gerade kein Bearbeitungsfeld offen ist.
2. **Je aktive Notiz** (von oben nach unten in der gespeicherten Reihenfolge):
    1. **Name des Verfassers** (ersatzweise sein Benutzername).
    2. **Datum und Uhrzeit** der Erfassung in Schweizer Schreibweise (Uhrzeit auf Stunden und Minuten).
    3. **Löschknopf «✕»** (Hinweistext «Notiz löschen»; derselbe Löschknopf wie überall, ohne Papierkorb-Symbol). **Bedingte Sichtbarkeit**: nur bei **eigenen** Notizen und nur, solange diese Notiz nicht gerade bearbeitet wird. Wirkung: Die Notiz wird sofort ohne Rückfrage entfernt und erscheint fortan als Löschvermerk in der Aktionszeitleiste. Fehlermeldung: «Fehler beim Löschen der Notiz».
    4. **Notiztext**, formatiert dargestellt. Bei **eigenen** Notizen ist er anklickbar (Hinweistext «Klicken zum Bearbeiten»); ein Klick oder die «Eingabe»-Taste öffnet an dieser Stelle das Bearbeitungsfeld. Fremde Notizen sind nicht anklickbar und nicht bearbeitbar.
3. **Bearbeitungsfeld** (dasselbe Feld für neue und bestehende Notizen):
    - Formatierter Textbereich mit Werkzeugleiste; Platzhalter «Notiz bearbeiten…» beim Bearbeiten, «Kommentar, Beobachtung, Hinweis» bei einer neuen Notiz.
    - Beim Bearbeiten einer bestehenden Notiz erscheinen in der Werkzeugleiste zusätzlich die Knöpfe zum Blättern in älteren Fassungen (siehe «Formatierter Textbereich»).
    - **«✓»:** Hinweistext «Speichern». Der **einzige** Speicherweg. Schliesst die Bearbeitung ab und legt bei einer Änderung eine Fassung in der Versionsgeschichte ab. Rückmeldung «Notiz gespeichert», bei einem Fehler «Fehler beim Speichern der Notiz» oder «Fehler beim Bearbeiten der Notiz». Blättert man gerade in einer älteren Fassung, hat der Knopf die Sonderfunktion «Wiederherstellen»: Der aktuelle Arbeitsstand wird zuerst regulär gesichert (und damit als Fassung erhalten), danach wird die angezeigte ältere Fassung als neue aktuelle Fassung übernommen.
    - **«✕»:** Hinweistext «Abbrechen». Schliesst das Feld, ohne zu speichern; die Eingabe wird verworfen.
    - **Kein Speichern bei Fokus-Verlust**: Verlässt man das Feld (Klick daneben, anderes Feld), bleibt das Bearbeitungsfeld offen und der Text erhalten. Klicks auf «✓», «✕» und auf die Werkzeugleiste verwerfen nie eine Eingabe.
    - Eine **neue** Notiz wird erst beim «✓» angelegt, und nur, wenn sie nicht leer ist; ein geöffnetes, leer gebliebenes Feld erzeugt nichts.
    - Wird der Text einer bestehenden Notiz vollständig geleert, wird die Notiz **nicht** gelöscht und der Leerstand nicht gespeichert; zum Löschen dient der Löschknopf.
4. **Warnung bei ungespeicherten Änderungen**: Ist ein Bearbeitungsfeld mit geändertem, noch nicht gespeichertem Text offen, erscheint beim Verlassen der Seite (Zurück, anderer Link, Tab oder Fenster schliessen) sowie beim Schliessen der Detailmaske (✕ oder Klick daneben) und beim Öffnen einer anderen Notiz eine Rückfrage, ob die Änderungen verworfen werden sollen.
5. **«+ Neue Notiz»:** Knopf am Ende der Liste, Hinweistext «Neue Notiz».
    - Wirkung: öffnet ein leeres Bearbeitungsfeld am Ende der Liste. Ist bereits ein Feld mit ungespeicherten Änderungen offen, wird zuerst nachgefragt.
    - Solange das Feld für eine neue Notiz offen ist, erscheint diese Notiz nicht zusätzlich als fertiger Eintrag in der Liste.
    - Gesperrt: nie.

#### Aktionszeitleiste

1. **Überschrift:** «Aktionszeitleiste».
2. **Hinweis «Noch keine Aktionen vorhanden.»:** erscheint, solange keine Einträge vorliegen.
3. **Je Eintrag** von links nach rechts:
    1. **Griff «⠿»:** Hinweistext «Verschieben». Damit lässt sich der Eintrag per Ziehen an eine andere Stelle der Zeitleiste bringen; das Ziel wird beim Darüberziehen hervorgehoben. Die geänderte Reihenfolge gilt nur für die aktuelle Ansicht und wird nicht gespeichert.
    2. **Datum** und darunter **Uhrzeit** der Aktion in Schweizer Schreibweise; bei einer Traktandennotiz zusätzlich eine kleine Zeile mit Traktandennummer, Sitzungsdatum und Sitzungstitel.
    3. **Name des Benutzers**, der die Aktion ausgeführt hat (ersatzweise sein Benutzername, sonst «unbekannt»).
    4. **Inhalt:** je nach Art der Aktion die Bezeichnung der Aktion und darunter der ergänzende Text (etwa bei einem Beschluss oder einer Rücknahme), ein formatierter Notiztext bei Traktandennotizen oder nur ein Text.
    5. **Gelöschte Notiz:** Für eine gelöschte Notiz (regulär oder Sitzungsnotiz) erscheint an Stelle des Textes der Vermerk «‹Name› hat seine Notiz gelöscht»; der ursprüngliche Notiztext bleibt verborgen, und ohne Namen steht «Jemand». Daneben steht für den Verfasser der Knopf **«↺»** (Hinweistext «Löschen rückgängig machen»): Notiz und Versionsgeschichte kommen zurück, und die Notiz steht wieder in ihrer Notizenliste; Rückmeldung «Notiz wiederhergestellt» oder «Notiz konnte nicht wiederhergestellt werden». Aktive Notizen erscheinen hier nicht, sie stehen in der Notizenliste.
4. **Was in der Zeitleiste nicht erscheint**: reguläre Notizen und Sitzungsnotizen, die in ihren eigenen Listen stehen, und ein aktuell gültiges Votum.
5. **Traktandennotizen anklickbar:** **bedingte Sichtbarkeit**: nur, wenn die Maske aus einem Sitzungstraktandum heraus geöffnet wurde und die Sitzung bekannt ist. Hinweistext «Zur Sitzung springen»; ein Klick oder die «Eingabe»-Taste springt zur Sitzung.
6. Es gibt in der Zeitleiste keine Bearbeitungs-, Lösch- oder Speicherfunktion.

#### Dokumente

1. **Überschrift:** «Dokumente».
2. **Pfadangabe:** «Pfad:» gefolgt vom Ablageort in fester Schrift. Beim Geschäft «Fraktion/20_Geschäfte/‹Jahr›/‹Geschäftsnummer›-*», beim Vorstoss «Fraktion/40_Vorstösse/10_Eigene/‹Jahr›/*» oder «…/20_Fremde/‹Jahr›/*» je nach Herkunft.
3. **Ladehinweis «Lädt…»:** solange die Dateiliste geladen wird.
4. **Hinweis «Noch keine Dokumente vorhanden.»:** wenn keine Datei vorliegt.
5. **Dateiliste:** je Datei:
    1. **Dateiname** als Verweis; öffnet die Datei in einem neuen Fenster im passenden Betrachter oder Editor.
    2. **«⤓»:** Hinweistext «Herunterladen»; lädt die Datei herunter.
6. **«+ Neues Dokument»:** Menüknopf, hervorgehobene Darstellung. **Bedingte Sichtbarkeit**: erscheint erst, wenn der Ablageort feststeht (beim Geschäft: Geschäft und Nummer vorhanden; beim Vorstoss und an der Sitzung: gespeichert). Das Menü enthält in dieser Reihenfolge:
    1. «Word-Dokument (docx)»
    2. «Excel-Tabelle (xlsx)»
    3. «PowerPoint (pptx)»
    4. «OpenDocument-Text (odt)»
    5. «OpenDocument-Tabelle (ods)»
    6. «OpenDocument-Präsentation (odp)»
    7. «Markdown (md)»
    8. «Textdatei (txt)»
    - Eine Auswahl schliesst das Menü und öffnet den Dialog «Neues Dokument».
7. **«⤒ Hochladen»:** Knopf; öffnet die Dateiauswahl des Rechners. Nach der Auswahl wird die Datei sofort hochgeladen und die Liste aktualisiert. Rückmeldung «Datei hochgeladen» (verschwindet nach kurzer Zeit von selbst) oder «Hochladen fehlgeschlagen: …».
8. **Dialog «Neues Dokument: ‹gewählte Vorlage›»**:
    1. **Beschriftung** «Dateiname (ohne Präfix und Endung)».
    2. **Vorangestelltes Präfix:** unveränderliche Anzeige, etwa die Geschäftsnummer oder das Datum der Sitzung, gefolgt von einem Bindestrich; beim Vorstoss gibt es kein Präfix, und die Beschriftung lautet «Dateiname (ohne Endung)».
    3. **Namensfeld:** Textfeld, Platzhalter «z. B. Überweisung Rede». Pflichtfeld: ja (ohne Eingabe lässt sich nicht erstellen). Vorbelegung: der Titel des Geschäfts oder des Vorstosses, wobei Leerzeichen und Schrägstriche durch Unterstriche ersetzt sind; der Schreibcursor steht am Ende, sodass sich der Vorschlag direkt ergänzen lässt. Die «Eingabe»-Taste erstellt das Dokument.
    4. **Angehängte Endung:** unveränderliche Anzeige der Dateiendung der gewählten Vorlage.
    5. **Hinweis** «Leerzeichen werden zu Unterstrichen.»
    6. **«Erstellen»:** Knopf, hervorgehobene Darstellung. **Gesperrt**, solange das Namensfeld leer ist (auch bei reinen Leerzeichen) oder das Erstellen bereits läuft. Wirkung: legt die Datei an, öffnet sie sofort in einem neuen Fenster, schliesst den Dialog, meldet «Dokument erstellt» und aktualisiert die Dateiliste. Fehlermeldung: «Fehler: …».
    7. **«Abbrechen»:** schliesst den Dialog ohne Wirkung.
    8. **«✕»** in der Kopfzeile: schliesst den Dialog ebenfalls und setzt den eingegebenen Namen zurück; ein Klick neben das Fenster wirkt gleich.
9. **Meldungszeile:** zeigt Rückmeldungen zu Hochladen, Erstellen und Verknüpfen; eine Erfolgsmeldung verschwindet nach rund 2,5 Sekunden, eine Fehlermeldung bleibt stehen.

#### Formatierter Textbereich (Werkzeugleiste)

Der Bereich steht bei Notizen, beim Inhalt eines Vorstosses, beim Beschreibungstext eines eigenen Geschäfts, beim Votum und bei der Begründung eines Antrags (F132). Die Werkzeugleiste erscheint nur, solange der Bereich bearbeitbar ist; ist er es nicht, bleibt nur der reine Text sichtbar. Eine aktive Formatierung, etwa wenn der Cursor in fettem Text steht, ist am Knopf hervorgehoben.

Die Knöpfe stehen in Gruppen von links nach rechts:

1. **Gruppe Zeichenformat**
    1. «Fett (Ctrl+B)»
    2. «Kursiv (Ctrl+I)»
    3. «Unterstrichen (Ctrl+U)»
    4. «Durchgestrichen»
2. **Gruppe Absatzformat**
    1. «Absatz»
    2. «Überschrift 2»
    3. «Überschrift 3»
3. **Gruppe Blöcke**
    1. «Aufzählung»
    2. «Nummerierte Liste»
    3. «Zitat»
    4. «Code (verbatim)»
    5. «Code-Block (verbatim)»
4. **Gruppe Verweise**
    1. «Link einfügen / bearbeiten»: fragt in einem kleinen Eingabefenster nach der Adresse; vorbelegt ist die Adresse eines bereits vorhandenen Verweises. Der Text im Fenster lautet «Link-URL (leer = entfernen)»: Eine leere Eingabe entfernt den Verweis, ein Abbruch lässt alles unverändert.
    2. «Link entfernen»: **gesperrt**, solange der Cursor nicht in einem Verweis steht.
5. **Gruppe Verlauf**
    1. «Rückgängig (Ctrl+Z)»: **gesperrt**, wenn nichts rückgängig zu machen ist.
    2. «Wiederholen (Ctrl+Shift+Z)»: **gesperrt**, wenn nichts zu wiederholen ist.
    3. «Formatierung entfernen»: entfernt alle Auszeichnungen der Auswahl.
6. **Gruppe Versionen:** **bedingte Sichtbarkeit**: nur, wenn zu diesem Text ältere Fassungen vorliegen (bei Notizen, sobald sie mindestens einmal abgeschlossen bearbeitet wurden).
    1. **«←»** «Eine Version zurück»: erscheint nur, solange es eine ältere Fassung gibt; vom aktuellen Stand aus führt der erste Klick zur jüngsten archivierten Fassung. Beim ersten Klick wird der aktuelle Arbeitsstand festgehalten.
    2. **«→»** «Eine Version vorwärts»: erscheint nur, während eine ältere Fassung angezeigt wird.
    3. **«»»** «Zur neuesten Version»: erscheint nur, während eine ältere Fassung angezeigt wird; holt den festgehaltenen Arbeitsstand zurück (nicht die zuletzt gespeicherte Fassung).
7. **Gruppe rechts:** **bedingte Sichtbarkeit**: nur, wenn der Einsatzort zusätzliche Knöpfe mitgibt oder ein PDF angeboten wird. Enthält gegebenenfalls den Verweis, der in einem neuen Fenster öffnet; sein Hinweistext ist «Als PDF herunterladen», sofern der Einsatzort keinen eigenen mitgibt, beim Votum «Votum als PDF drucken».
8. **Statusanzeige** ganz rechts:
    - Während des Blätterns: «Ältere Fassung – nur Ansicht»; der Text ist dann **gesperrt** und lässt sich nicht bearbeiten.
    - Sonst: der vom Einsatzort gelieferte Statustext, etwa eine Rückmeldung zum Speichern, falls vorhanden.

Ausserdem gilt:

9. Ein Klick auf einen Knopf der Werkzeugleiste nimmt den Schreibfokus **nicht** aus dem Text: Die Eingabe geht nie verloren, und ein solcher Klick gilt nicht als Verlassen des Feldes.
10. Während eine ältere Fassung angezeigt wird, wird beim Verlassen **nichts** gespeichert; Blättern verändert den gespeicherten Stand nie.
11. Der Platzhaltertext wird vom jeweiligen Einsatzort vorgegeben und erscheint nur, solange der Bereich leer ist.

---

### Ansicht «Sitzungen»

#### Suche und Filter

Beide Elemente stehen in der linken Navigationsspalte: das Suchfeld zuoberst über den Ansichts-Einträgen, der Filter zuunterst darunter. Sie werden erst eingeblendet, wenn die Ansicht «Sitzungen» aktiv ist, und verschwinden beim Wechsel in eine andere Ansicht.

1. **Suchfeld:** Beschriftung «Suche», Platzhalter «Nr. oder Titel».
    - Art: Textfeld mit nachgestelltem Löschknopf.
    - Pflichtfeld: nein.
    - Vorbelegung: leer (kein gespeicherter Wert; nach einem Ansichtswechsel wieder leer).
    - Dynamisches Verhalten: filtert die Sitzungsliste sofort bei jedem Tastendruck. Gesucht wird gross-/kleinschreibungsunabhängig in Titel und Ort der Sitzung sowie in Titel und Nummer aller Traktanden. Sitzungen, deren Traktanden noch nicht geladen sind, bleiben zunächst sichtbar; sobald sie im Hintergrund nachgeladen sind, verschwinden sie, falls sie nicht passen. Beim Tippen werden die Traktanden aller sichtbaren Sitzungen automatisch nachgeladen; findet sich darin ein Treffer, klappt die betreffende Sitzung von selbst auf. Innerhalb einer aufgeklappten Sitzung werden zusätzlich die Traktandenzeilen auf die Treffer reduziert. Der Zähler in der Kopfzeile zeigt die verbleibende Anzahl.
    - Der Löschknopf (Kreuz-Symbol am rechten Feldrand) erscheint nur, solange Text im Feld steht; ein Klick leert die Suche und stellt die volle Liste wieder her.
    - Speicherung: keine; das Feld filtert nur die Anzeige.
    - Gesperrt: nie.
2. **Schalter «Nur zukünftige Sitzungen»:** Beschriftung «Nur zukünftige Sitzungen».
    - Art: Schalter (ein/aus).
    - Pflichtfeld: entfällt.
    - Vorbelegung: eingeschaltet (fest vorgegeben und nicht gespeichert, also nach jedem Neuladen wieder eingeschaltet).
    - Dynamisches Verhalten: Eingeschaltet blendet der Schalter alle Sitzungen aus, deren Datum vor dem heutigen Tag liegt; ausgeschaltet erscheinen auch vergangene Sitzungen, blass dargestellt. Der Schalter wirkt vor der Suche: Bei eingeschaltetem Schalter werden Traktanden vergangener Sitzungen für die Suche gar nicht erst nachgeladen.
    - Speicherung: keine; der Schalter filtert nur die Anzeige.
    - Gesperrt: nie.

#### Kopfzeile der Ansicht

1. **Überschrift «Sitzungen»:** reine Beschriftung.
2. **Zähler:** Zahl der aktuell angezeigten (gefilterten) Sitzungen; ändert sich bei jeder Eingabe in Suche oder Filter.
3. **Menüknopf «+ Neue Sitzung»:** sichtbare Beschriftung «+ Neue Sitzung», Vorlese-Bezeichnung «Neue Sitzung aus Vorlage erstellen».
    - Art: Knopf in Hauptfarbe, der immer ein Auswahlmenü öffnet (auch wenn nur ein einziger Eintrag darin steht).
    - Menüeinträge: je ein Eintrag pro Sitzungstyp, beschriftet mit dem Namen des Sitzungstyps. Quelle: die gespeicherten Sitzungstypen; gelöschte Typen werden ausgefiltert. Reihenfolge: wie vom Server geliefert (Reihenfolge der Sitzungstypen-Verwaltung).
    - Sonderfall: existiert kein einziger Sitzungstyp, enthält das Menü genau einen, dauerhaft gesperrten Eintrag «Kein Sitzungstyp definiert».
    - Dynamisches Verhalten: Die Wahl eines Eintrags öffnet das Formular «Neue Sitzung» und füllt es vollständig aus den Angaben des gewählten Sitzungstyps vor (siehe nächster Abschnitt).

#### Kopfbereich des Formulars «Neue Sitzung»

Das Formular erscheint als überlagerndes Fenster über der ganzen Seite. Ein Klick auf die abgedunkelte Fläche neben dem Formular schliesst es, ohne zu speichern.

1. **Schliessen-Knopf «✕»:** Vorlese-Bezeichnung «Dialog schliessen»; oben rechts; schliesst das Formular, ohne zu speichern, und alle Eingaben gehen verloren.
2. **Titel:** Platzhalter «Titel eingeben …», keine sichtbare Beschriftung (das Feld steht prominent zuoberst).
    - Art: Textfeld; erhält beim Öffnen automatisch den Eingabefokus.
    - Pflichtfeld: nein (die Sitzung lässt sich auch mit leerem Titel erstellen).
    - Vorbelegung: der Name des gewählten Sitzungstyps.
3. **Datum:** kein Beschriftungstext, mit Kalender-Symbol gekennzeichnet.
    - Art: Datumsfeld.
    - Pflichtfeld: **ja**; ohne Datum bleibt der Knopf «Erstellen» gesperrt.
    - Vorbelegung: das Datum in einer Woche (heute plus sieben Tage).
    - Einschränkung: Als frühestes wählbares Datum ist der heutige Tag gesetzt; frühere Tage bietet die Datumsauswahl nicht an.
4. **Von:** Beschriftung «Von», mit Uhr-Symbol gekennzeichnet.
    - Art: Zeitfeld.
    - Pflichtfeld: nein.
    - Vorbelegung: die Standard-Anfangszeit des gewählten Sitzungstyps (leer, wenn dort keine hinterlegt ist).
5. **Bis:** Beschriftung «Bis», rechts neben «Von».
    - Art: Zeitfeld.
    - Pflichtfeld: nein.
    - Vorbelegung: die Standard-Endzeit des gewählten Sitzungstyps.
6. **Ort:** Platzhalter «Ort», mit Standort-Symbol gekennzeichnet.
    - Art: Textfeld.
    - Pflichtfeld: nein.
    - Vorbelegung: der Standard-Ort des gewählten Sitzungstyps.
7. **Zweck / Beschreibung:** Platzhalter «Zweck / Beschreibung …», mit Listen-Symbol gekennzeichnet.
    - Art: Textbereich, drei Zeilen hoch.
    - Pflichtfeld: nein.
    - Vorbelegung: der Zweck des gewählten Sitzungstyps.
8. **Verknüpfen mit:** Beschriftung «Verknüpfen mit», Platzhalter «Andere Sitzung wählen (optional) …», mit Ketten-Symbol gekennzeichnet.
    - Art: Auswahlliste mit Suchfunktion (Einfachauswahl).
    - **Bedingte Sichtbarkeit: Dieser Bereich erscheint ausschliesslich dann, wenn beim gewählten Sitzungstyp die Option «Beim Anlegen Verknüpfung mit anderen Sitzungen anbieten» eingeschaltet ist.** Bei allen anderen Sitzungstypen fehlt die Zeile vollständig.
    - Pflichtfeld: nein.
    - Vorbelegung: leer (keine Verknüpfung).
    - Auswahlwerte: alle nicht gelöschten Sitzungen, beschriftet als «ausgeschriebenes Datum – Titel». Reihenfolge: zuerst alle künftigen Sitzungen (Datum ab heute) aufsteigend nach Datum, danach alle vergangenen absteigend nach Datum. Ausgefiltert werden gelöschte Sitzungen; die gerade entstehende Sitzung ist noch nicht in der Liste.
    - Speicherung: Die Verknüpfung entsteht erst beim Klick auf «Erstellen», unmittelbar nach dem Anlegen der Sitzung.

#### Traktanden im Formular «Neue Sitzung»

Der Bereich ist mit einem Listen-Symbol gekennzeichnet und trägt die Bereichsüberschrift «Traktanden». Die Zeilen sind beim Öffnen mit den Vorlage-Traktanden des gewählten Sitzungstyps vorbelegt, in der dort festgelegten Reihenfolge. Hat der Sitzungstyp keine Vorlage-Traktanden, ist die Liste leer.

Jede Traktandenzeile enthält von links nach rechts:

1. **Ziehgriff «⠿»:** Art: Ziehgriff.
    - Dynamisches Verhalten: Die Zeile lässt sich am Griff aufnehmen und über einer anderen Zeile fallen lassen; die aufgenommene Zeile wird an der Zielposition eingefügt, alle übrigen rücken nach. Die Zielzeile wird während des Ziehens hervorgehoben. Die Nummerierung wird sofort neu berechnet.
2. **Positionsnummer:** Anzeige «1.», «2.», … Art: reine Anzeige, fortlaufend nach der aktuellen Reihenfolge, nicht bearbeitbar.
3. **Titel:** Platzhalter «Titel». Art: Textfeld. Pflichtfeld: nein. Vorbelegung: Titel des Vorlage-Traktandums.
4. **Beschreibung:** Platzhalter «Beschreibung (optional)». Art: Textfeld. Pflichtfeld: nein. Vorbelegung: Beschreibung des Vorlage-Traktandums.
5. **Löschknopf «✕»:** Vorlese-Bezeichnung «Traktandum ‹Nummer› löschen». Art: Knopf. Entfernt die Zeile sofort aus der Liste; die folgenden Zeilen werden neu nummeriert.

Am Ende der Liste steht:

6. **Knopf «+ Traktandum hinzufügen»:** Art: Knopf (zurückhaltende Gestaltung). Hängt eine leere Zeile mit leerem Titel und leerer Beschreibung an.

Nichts in diesem Bereich wird sofort gespeichert; die ganze Traktandenliste wird erst mit «Erstellen» übernommen.

#### Teilnehmer im Formular «Neue Sitzung»

Der Bereich ist mit einem Personen-Symbol gekennzeichnet und trägt die Bereichsüberschrift «Teilnehmer». Die Zeilen sind beim Öffnen mit den Teilnehmerregeln des gewählten Sitzungstyps vorbelegt, in der dort gespeicherten Reihenfolge.

Jede Teilnehmerzeile enthält:

1. **Teilnehmerart:** keine sichtbare Beschriftung.
    - Art: Auswahlliste (Einfachauswahl).
    - Pflichtfeld: ja, immer belegt.
    - Vorbelegung: die Art aus der Vorlage; bei einer neu hinzugefügten Zeile «Eigene Fraktion».
    - Auswahlwerte in genau dieser Reihenfolge, fest im Programm vorgegeben: «Einzelnes Mitglied», «Ganze Fraktion», «Eigene Fraktion», «Ganze Kommission», «Fraktions-Rolle», «Nextcloud-Gruppe», «Nextcloud-Benutzer».
    - **Dynamisches Verhalten: Die gewählte Art bestimmt, welches Element rechts daneben erscheint (siehe Punkt 2). Bei jedem Wechsel der Art wird die bisherige Auswahl der Zeile zurückgesetzt, und die Zeile ist wieder unbestimmt. Beim ersten Wechsel auf «Nextcloud-Gruppe» oder «Nextcloud-Benutzer» werden die entsprechenden Listen im Hintergrund nachgeladen.**
2. **Bezugselement rechts daneben:** Welches erscheint, hängt allein von der gewählten Art ab:
    - Bei **«Einzelnes Mitglied»**: Auswahlliste mit dem ersten Eintrag «— Mitglied wählen —» (Leerwert) und darunter allen aktiven Mitgliedern, beschriftet «Vorname Name». Quelle: die Mitgliederverwaltung; ausgefiltert werden inaktive Mitglieder. Reihenfolge: alphabetisch nach «Vorname Name».
    - Bei **«Ganze Fraktion»**: Auswahlliste mit dem ersten Eintrag «— Fraktion wählen —» und darunter allen aktiven Fraktionen. Angezeigt wird die Kurzform des Fraktionsnamens gemäss den konfigurierten Kürzeln, gespeichert wird der volle Name. Reihenfolge: wie in der Fraktionsverwaltung geliefert. Ausgefiltert: inaktive Fraktionen.
    - Bei **«Eigene Fraktion»**: ein Hinweistext ohne Auswahl, «→ ‹Name der konfigurierten Gruppe›». Ist in der Konfiguration keine Gruppe hinterlegt, steht dort «→ (keine Gruppe konfiguriert)».
    - Bei **«Ganze Kommission»**: Auswahlliste mit dem ersten Eintrag «— Kommission wählen —» und darunter allen aktiven, nicht gelöschten Kommissionen, angezeigt in der Kurzform gemäss den konfigurierten Kürzeln. Reihenfolge: wie in der Kommissionsverwaltung geliefert.
    - Bei **«Fraktions-Rolle»**: Auswahlliste mit dem ersten Eintrag «— Fraktions-Rolle —» und darunter genau fünf fest vorgegebenen Rollen in dieser Reihenfolge: «Kommissionsmitglied», «Fraktionspräsident», «Fraktionspräsident Stellvertretung», «Protokollführer», «Protokollführer Stellvertretung».
    - Bei **«Nextcloud-Gruppe»**: Auswahlliste mit dem ersten Eintrag «— Nextcloud-Gruppe —»; solange die Liste noch geladen wird, lautet dieser Eintrag «— Lade … —». Darunter alle Gruppen der Nextcloud-Installation, beschriftet mit dem Anzeigenamen (ersatzweise der technischen Kennung). Reihenfolge: wie vom Server geliefert.
    - Bei **«Nextcloud-Benutzer»**: Auswahlliste mit dem ersten Eintrag «— Nextcloud-Benutzer —»; während des Ladens «— Lade … —». Darunter Benutzer, beschriftet «Anzeigename (Benutzerkennung)»; es werden höchstens 100 Benutzer geladen. Reihenfolge: wie vom Server geliefert.
    - Ersatzfall: Stammt eine Zeile aus älteren Daten mit einer unbekannten Art, erscheint an Stelle der Auswahl ein freies Textfeld mit dem Platzhalter «Bezeichnung».
3. **Löschknopf «✕»:** entfernt die Teilnehmerzeile sofort aus der Liste.

Am Ende der Liste steht:

4. **Knopf «+ Teilnehmer hinzufügen»:** Art: Knopf (zurückhaltende Gestaltung). Hängt eine neue Zeile an, vorbelegt mit der Art «Eigene Fraktion» und ohne Bezug.

Nichts wird sofort gespeichert; die Teilnehmerliste wird erst mit «Erstellen» übernommen.

#### Abschluss des Formulars «Neue Sitzung»

1. **Fehlermeldung:** Text «Fehler: ‹Meldung des Servers›».
    - Art: Meldungszeile im Formular.
    - Bedingte Sichtbarkeit: erscheint erst, nachdem ein Erstellungsversuch fehlgeschlagen ist; bei jedem neuen Versuch und bei jeder neuen Typwahl wird sie zurückgesetzt. Zusätzlich erscheint eine Fehlermeldung als eingeblendete Systemmeldung «Sitzung konnte nicht erstellt werden: …».
2. **Knopf «Erstellen»:** Art: Knopf in Hauptfarbe.
    - **Gesperrt, solange kein Datum gesetzt ist oder das Erstellen bereits läuft.**
    - Während des Erstellens wechselt die Beschriftung zu «Erstellt …».
    - Beim Klick wird die Sitzung mit Sitzungstyp, Datum, Titel, Ort, Von, Bis, Zweck, der vollständigen Traktandenliste und der vollständigen Teilnehmerliste angelegt. Ist unter «Verknüpfen mit» eine Sitzung gewählt, wird unmittelbar danach die Verknüpfung hergestellt; scheitert nur diese, erscheint die Meldung «Sitzung erstellt, Verknüpfung fehlgeschlagen: …», die Sitzung bleibt aber bestehen. Nach Erfolg wird die neue Sitzung in die Liste einsortiert (nach Datum), automatisch aufgeklappt, ihre Traktanden werden geladen und das Formular schliesst sich.
3. **Knopf «Abbrechen»:** Art: Knopf. Schliesst das Formular, ohne zu speichern, und alle Eingaben gehen verloren.

#### Ladeanzeige und Leermeldung der Liste

1. **Ladeanzeige:** drehendes Symbol, solange die Sitzungen geladen werden; die Liste selbst ist währenddessen nicht sichtbar.
2. **Leermeldung «Keine Sitzungen gefunden.»:** erscheint, wenn Filter und Suche kein Ergebnis liefern.

#### Sitzungskarte (zugeklappt)

Jede Sitzung erscheint als Karte. Der ganze Kartenkopf ist anklickbar und klappt den Detailbereich auf oder zu. Vergangene Sitzungen werden blass dargestellt.

1. **Datum:** fett, ausgeschrieben im Schweizer Format (Wochentag, Tag, Monat, Jahr).
2. **Zeitangabe:** «Von» oder «Von – Bis»; erscheint nur, wenn eine Anfangszeit hinterlegt ist; die Endzeit nur, wenn auch sie hinterlegt ist.
3. **Kennzeichen «intern»:** erscheint nur bei Sitzungen, die aus einem eigenen Sitzungstyp entstanden sind (nicht bei importierten Parlamentssitzungen).
4. **Titel der Sitzung:** reine Anzeige.
5. **Zweck:** erscheint nur bei internen Sitzungen und nur, wenn ein Zweck erfasst ist.
6. **Ort:** reine Anzeige.
7. **Link «Protokoll»:** erscheint nur, wenn das Protokoll dieser Sitzung auf der Parlamentswebseite veröffentlicht ist; Hinweistext ist der Titel des Protokolls. Öffnet das Protokoll in einem neuen Fenster und klappt die Karte dabei nicht auf oder zu (F115). Er steht links von «Extern», damit «Extern» auf jeder Karte an derselben Stelle steht.
8. **Link «Extern»:** erscheint nur, wenn zur Sitzung eine externe Adresse hinterlegt ist; öffnet sie in einem neuen Fenster und klappt die Karte dabei nicht auf oder zu.
9. **Zeichen «▼» und «▲»:** zeigen den Zustand an: «▼» zugeklappt, «▲» aufgeklappt.

Beim Aufklappen werden die Traktanden der Sitzung, die verknüpften Sitzungen und die bereits verknüpften Geschäfte nachgeladen; die Auswahl der Geschäfte nimmt die geteilte Liste (F150). Änderungen anderer Benutzer kommen laufend dazu, und offene Sitzungen ändern ihre Traktanden dabei an Ort und Stelle, ohne dass die Ansicht springt (F147). Ein Traktandum, das dabei neu dazukommt, bringt seine Notizen gleich mit. Eine zugeklappte Sitzung lädt bei einem Ereignis nichts. Betrifft es ihre Traktanden oder ein Geschäft auf einem ihrer Traktanden, lädt das nächste Aufklappen ihre Traktanden neu. Ohne ein solches Ereignis zeigt das Aufklappen die schon geladenen Traktanden ohne neue Anfrage. Ein Abgleich, der Geschäfte nennt, gilt dabei für jedes dieser Geschäfte wie eine Änderung durch einen anderen Benutzer: Offene Sitzungen laden ihre Traktanden im Hintergrund, zugeklappte laden sie beim nächsten Aufklappen.

#### Notizen der aufgeklappten Sitzung

1. **Bereichsüberschrift «Notizen zur Sitzung»**.
2. **Notizenliste der Sitzung:** dieselbe geteilte Notizenliste wie überall (Aufbau und Bedienung siehe «Notizenliste» unter «Geteilte Bedienelemente»): «+ Neue Notiz», Speichern ausschliesslich über «✓», Versionsverlauf, Löschen. Die Notizen gehören zur Sitzung.

#### Dokumente der aufgeklappten Sitzung

Der Bereich ist der Dokumentenbereich aus «Dokumente» unter «Geteilte Bedienelemente». An der Sitzung gilt dazu:

1. **Pfadhinweis:** Text «Pfad: Fraktion/10_Sitzungen/‹Jahr der Sitzung›/‹Datum der Sitzung›-*»; reine Anzeige, zeigt, wo die Dateien abgelegt werden.
2. **Präfix im Dialog «Neues Dokument»:** das Datum der Sitzung in der Form JJJJ-MM-TT, gefolgt von einem Bindestrich, links vom Namensfeld. Das Namensfeld ist bei Sitzungen leer vorbelegt und erhält beim Öffnen den Fokus.
3. **Meldungszeile:** zeigt rund 2,5 Sekunden lang die letzte Rückmeldung wie «Dokument erstellt» oder «Datei hochgeladen»; Fehlermeldungen bleiben stehen.

#### Verknüpfte Geschäfte der aufgeklappten Sitzung

1. **Bereichsüberschrift «Verknüpfte Geschäfte»**.
2. **Liste der verknüpften Geschäfte:** erscheint nur, wenn mindestens eines verknüpft ist. Je Eintrag:
    - Anzeige «Geschäftsnummer Titel»; ist das Geschäft nicht auffindbar, erscheint «Unbekanntes Geschäft».
    - **Knopf «✕»:** Hinweistext «Verknüpfung lösen»; löst die Verknüpfung sofort; die Liste wird unmittelbar aktualisiert. Bei Fehler erscheint «Verknüpfung konnte nicht gelöst werden: …».
3. **Auswahlliste «Geschäft verknüpfen…»:** Platzhalter «Geschäft verknüpfen…».
    - Art: Auswahlliste mit Suchfunktion (Einfachauswahl).
    - Pflichtfeld: nein.
    - Vorbelegung: keine (das Feld bleibt nach jeder Wahl leer).
    - Auswahlwerte: die hängigen Geschäfte der geteilten Liste (F150), beschriftet «Nummer Titel», in der Reihenfolge dieser Liste; **bereits verknüpfte Geschäfte werden ausgefiltert**.
    - Speicherung: Die Auswahl verknüpft das Geschäft sofort, und die Liste darüber zeigt es unmittelbar. Bei Fehler: «Geschäft konnte nicht verknüpft werden: …».

#### To-do der aufgeklappten Sitzung

1. **Bereichsüberschrift «To-do zu Deck»**.
2. **Aufgabenfeld:** Platzhalter «Aufgabe für das Fraktions-Board…».
    - Art: Textfeld.
    - Pflichtfeld: nein, aber ohne Text passiert nichts.
    - Vorbelegung: leer; je Sitzung wird ein eigener Zwischenstand gehalten.
    - Dynamisches Verhalten: Die Eingabetaste löst dieselbe Aktion aus wie der Knopf daneben.
3. **Knopf «Hinzufügen»:** Art: Knopf (zweite Ebene). Legt eine Aufgabenkarte im Aufgabenboard der Fraktion an (F50); die Beschreibung der Karte lautet automatisch «Aus Sitzung: ‹Titel der Sitzung› (‹Datum›)». Nach Erfolg wird das Eingabefeld geleert. Bei Fehler: «To-do konnte nicht zu Deck hinzugefügt werden: …».

#### Verknüpfte Sitzungen der aufgeklappten Sitzung

Der ganze Bereich erscheint nur, wenn die Sitzung Teil einer Verknüpfungsgruppe ist.

1. **Bereichsüberschrift «🔗 Verknüpfte Sitzungen»**.
2. **Knopf «Entkoppeln»:** in der Überschrift rechts; löst die Sitzung sofort aus der Verknüpfungsgruppe; der ganze Bereich verschwindet danach. Bei Fehler: «Entkoppeln fehlgeschlagen: …».
3. **Hinweis «Keine weiteren Sitzungen in dieser Verknüpfung.»:** erscheint, wenn ausser dieser Sitzung keine weitere in der Gruppe ist.
4. **Je verknüpfte Sitzung:**
    1. Unterüberschrift «ausgeschriebenes Datum – Titel».
    2. **Notizenliste der anderen Sitzung, nur zum Lesen:** dieselbe geteilte Notizenliste (siehe «Notizenliste» unter «Geteilte Bedienelemente»), aber ohne «+ Neue Notiz», ohne Bearbeiten und ohne Löschen.

#### Traktanden einer internen Sitzung

Diese vereinfachte Darstellung erscheint bei Sitzungen, die aus einem eigenen Sitzungstyp entstanden sind.

1. **Bereichsüberschrift «Traktanden»**.
2. **Ladeanzeige «Traktanden laden...»:** solange die Traktanden geladen werden.
3. **Tabelle** mit zwei Spaltenüberschriften:
    1. Spalte «Tr.»: die Traktandennummer, fett.
    2. Spalte «Titel»: Titel des Traktandums und darunter, sofern vorhanden, die Beschreibung.
4. **Notizzeile unter jedem Traktandum:** zwei Varianten:
    - **Ist dem Traktandum ein Geschäft zugeordnet:** Hinweistext «Sitzungsnotiz zum Geschäft», darunter die Sitzungsnotizen des Geschäfts (siehe «Sitzungsnotiz zum Geschäft»). Diese Notizen gehören zum Geschäft und erscheinen daher an jeder Sitzung, in der dasselbe Geschäft traktandiert ist.
    - **Sonst:** Hinweistext «Notiz zum Traktandum», darunter dieselbe geteilte Notizenliste wie überall (siehe «Notizenliste» unter «Geteilte Bedienelemente»). Die Notizen gehören zum Traktandum.
5. **Leermeldung «Keine Traktanden vorhanden.»:** wenn die Sitzung keine Traktanden hat oder die Suche keine übrig lässt.

#### Traktanden einer Parlamentssitzung als Tabelle

Diese vollständige Darstellung erscheint bei den Traktanden einer Parlamentssitzung, also bei Sitzungen ohne eigenen Sitzungstyp. Bei schmaler Ansicht wird stattdessen die Kartendarstellung des nächsten Abschnitts gezeigt.

1. **Bereichsüberschrift «Traktanden»**.
2. **Spaltenüberschriften** in dieser Reihenfolge: «Tr.», «Nr.», «Titel», «Zuständig», «Beschluss».
3. **Je Traktandenzeile:**
    1. **Spalte «Tr.»:** Traktandennummer, fett; reine Anzeige.
    2. **Spalte «Nr.»:** Geschäftsnummer (fett), darunter das Geschäftsdatum in Kurzform «TT.MM.JJ» und die Geschäftsart; reine Anzeige, leer, wenn kein Geschäft zugeordnet ist.
    3. **Spalte «Titel»:** vorangestelltes Pfeilsymbol «↗» als Link, danach der Titel (Titel des Geschäfts, ersatzweise Titel des Traktandums). Das Pfeilsymbol erscheint in genau einer von drei Ausprägungen, in dieser Rangfolge: Hinweistext «Extern öffnen» (Adresse des Geschäfts), sonst «Dokument öffnen» (Adresse des Traktandums), sonst «Originaltraktandum extern öffnen (kein verknüpftes Geschäft)» (Adresse der Sitzung). Ist keine dieser Adressen vorhanden, fehlt das Symbol. Ein Klick auf den Pfeil öffnet ein neues Fenster und öffnet **nicht** das Geschäft. Hinter dem Titel steht bei einem Traktandum, das ein Protokoll abnimmt, der Link «Protokoll ‹TT.MM.JJJJ der protokollierten Sitzung›» mit dem Titel des Protokolls als Hinweistext; er öffnet das Protokoll in einem neuen Fenster und öffnet **nicht** das Geschäft (F115).
    4. **Spalte «Zuständig»:** Mehrfachauswahl, Platzhalter «—», mit Löschmöglichkeit der ganzen Auswahl.
        - Bedingte Sichtbarkeit: nur wenn dem Traktandum ein Geschäft zugeordnet ist; sonst zeigt die Zelle nur «—».
        - Vorbelegung: die aktuell am Geschäft hinterlegten Zuständigen; wer nicht mehr in der Mitgliederliste steht, erscheint mit dem gespeicherten Namen.
        - Auswahlwerte: alle aktiven Mitglieder, **die ein Nextcloud-Konto hinterlegt haben**, beschriftet mit «Vorname Name», alphabetisch sortiert. Bereits gewählte Mitglieder verschwinden aus der Auswahlliste und stehen nur noch im Feld.
        - Speicherung: **sofort bei jeder Änderung**. Das erste gewählte Mitglied wird Hauptzuständiger, sofern der bisherige Hauptzuständige nicht mehr in der Auswahl ist; sonst bleibt er es. Danach werden die Traktanden im Hintergrund neu geladen, ohne Ladeanzeige.
        - Ein Klick in diese Zelle öffnet **nicht** das Geschäft.
    5. **Spalte «Beschluss»:** Auswahlliste (Einfachauswahl), Platzhalter «—», mit Löschmöglichkeit.
        - Bedingte Sichtbarkeit: nur wenn dem Traktandum ein Geschäft zugeordnet ist; sonst zeigt die Zelle nur «—».
        - Vorbelegung: der zuletzt am Geschäft erfasste Beschluss; ist er in den erlaubten Werten nicht enthalten, wird sein gespeicherter Titel angezeigt.
        - Auswahlwerte: **ausschliesslich die für dieses Geschäft im aktuellen Zustand erlaubten Beschlüsse** (F22); sie kommen mit dem Geschäft vom Server und unterscheiden sich daher von Zeile zu Zeile, in der gelieferten Reihenfolge.
        - Speicherung: **sofort**. Die Wahl eines Wertes erfasst den Beschluss am Geschäft; das Leeren des Feldes entfernt den zuletzt erfassten Beschluss. Danach werden die Traktanden im Hintergrund neu geladen.
        - Ein Klick in diese Zelle öffnet **nicht** das Geschäft.
    6. **Zeilenaktion:** Die ganze Zeile ausser den beiden Auswahlspalten und den Pfeil-Links ist anklickbar und auch per Tastatur mit Eingabe- oder Leertaste auslösbar; Vorlese-Bezeichnung «Traktandum ‹Nummer› öffnen». Sie öffnet das zugeordnete Geschäft in der Detailmaske. Ohne zugeordnetes Geschäft geschieht nichts. Zeilen gelöschter Geschäfte sind durchgestrichen oder abgesetzt dargestellt. Wird das Geschäft in der Maske geändert, zeigt die Zeile danach den neuen Stand: Die Traktanden der offenen Sitzungen, auf denen es steht, laden im Hintergrund neu.
    7. **Notizzeile unter dem Traktandum:** wie bei der internen Sitzung: bei zugeordnetem Geschäft der Hinweis «Sitzungsnotiz zum Geschäft» mit der Notizenliste des Geschäfts, sonst der Hinweis «Notiz zum Traktandum» mit derselben geteilten Notizenliste.
4. **Leermeldung «Keine Traktanden gefunden.»**.

#### Traktanden einer Parlamentssitzung als Karten

Bei schmaler Ansicht stehen Karten an Stelle der Tabelle, mit denselben Bedienelementen in anderer Anordnung. Jede Karte eines Traktandums enthält:

1. **Kennung:** «Tr. ‹Nummer›» (fett) und, sofern vorhanden, «Nr. ‹Geschäftsnummer›».
2. **Titel mit vorangestelltem Pfeilsymbol «↗»:** dieselben drei Ausprägungen wie in der Tabelle («Extern öffnen», «Dokument öffnen», «Originaltraktandum extern öffnen»), und hinter dem Titel derselbe Protokoll-Link wie in der Tabelle, sofern das Traktandum ein Protokoll abnimmt (F115).
3. **Merkmalszeile:** Statustext des Geschäfts (farblich abgesetzt nach offen, erledigt, abgelehnt oder neutral), Geschäftsart, ausgeschriebenes Geschäftsdatum; jedes nur, wenn vorhanden.
4. **Kartenkopf als Aktion:** Klick, Eingabe- oder Leertaste öffnet das Geschäft; Vorlese-Bezeichnung «Traktandum ‹Nummer› öffnen».
5. **Beschriftung «Zuständig»** mit derselben Mehrfachauswahl wie in der Tabelle (nur bei zugeordnetem Geschäft).
6. **Beschriftung «Beschluss»** mit derselben Auswahlliste wie in der Tabelle (nur bei zugeordnetem Geschäft).
7. **Notizbereich:** bei zugeordnetem Geschäft «Sitzungsnotiz zum Geschäft» mit der Notizenliste des Geschäfts, sonst der Hinweis «Notiz zum Traktandum» mit derselben geteilten Notizenliste.

#### Detailmaske aus der Sitzung

1. **Schliessknopf «✕»:** Vorlese-Bezeichnung «Dialog schliessen»; ein Klick auf die abgedunkelte Fläche schliesst ebenfalls; steht in einer Notiz noch ungespeicherter Text, fragt die Maske beim Schliessen mit ✕ und beim Klick daneben zuerst «Die Notiz ist noch nicht gespeichert. Trotzdem schliessen?».
2. **Geschäftsdetail:** zeigt zusätzlich den Zusammenhang, aus dem es geöffnet wurde (Sitzung, Sitzungsdatum, Sitzungstitel, Traktandennummer und die Notizen zu diesem Traktandum).
3. **Sprung zurück zu einer Sitzung:** Wird im Detail eine Sitzung angesteuert, schliesst sich die Maske, die Zielsitzung klappt auf, ihre Traktanden werden geladen und die Ansicht scrollt sanft zu ihr.
4. **Verweis auf ein anderes Geschäft** (etwa das offizielle eines eigenen Geschäfts): öffnet dieses in derselben Maske.

---

### Ansicht «Sitzungstypen»

#### Suche in der Seitenspalte

1. **Suchfeld:** Beschriftung «Suche», Platzhalter «Name oder Zweck».
    - Art: Textfeld mit nachgestelltem Löschknopf (erscheint nur bei Inhalt).
    - Pflichtfeld: nein. Vorbelegung: leer.
    - Dynamisches Verhalten: filtert die Kartenliste sofort; durchsucht Name und Zweck gross-/kleinschreibungsunabhängig. Der Zähler in der Kopfzeile passt sich an.
    - In dieser Ansicht gibt es keinen zusätzlichen Filterschalter.

#### Kopfzeile der Ansicht

1. **Überschrift «Sitzungstypen»**.
2. **Zähler:** Anzahl der aktuell angezeigten Sitzungstypen.
3. **Knopf «+ Neuer Typ»:** Art: Knopf in Hauptfarbe; öffnet die vollständige Sitzungstyp-Maske mit leerem Namensfeld. Angelegt wird erst beim Speichern.

#### Liste und Karten

1. **Ladeanzeige:** drehendes Symbol, solange geladen wird.
2. **Hinweis «Keine Sitzungstypen vorhanden. Lege einen neuen Typ an.»:** wenn die (gefilterte) Liste leer ist.
3. **Karte je Sitzungstyp:** Die ganze Karte ist anklickbar und auch per Eingabe- oder Leertaste auslösbar; Vorlese-Bezeichnung «Sitzungstyp ‹Name› bearbeiten»; sie öffnet die Maske zum Bearbeiten. Die Karte enthält:
    1. **Name:** Überschrift der Karte.
    2. **Zweck:** abgesetzter Text darunter; erscheint nur, wenn ein Zweck erfasst ist.
    3. **Knopf «Löschen»:** Art: Knopf in Warnfarbe, oben rechts. Öffnet zuerst eine Sicherheitsabfrage «Sitzungstyp ‹Name› wirklich löschen?»; erst nach Bestätigung wird gelöscht und die Liste neu geladen. Der Klick öffnet die Maske **nicht**.
    4. **Merkmalszeile:** vier Angaben, jede als reine Anzeige: «📍 ‹Standard-Ort›» (nur wenn erfasst), «🕒 ‹Von›» oder «🕒 ‹Von› – ‹Bis›» (nur wenn eine Anfangszeit erfasst ist; die Endzeit nur zusätzlich, wenn auch sie erfasst ist), «‹Anzahl› Vorlage-Traktanden», «‹Anzahl› Teilnehmer».

#### Neuen Sitzungstyp anlegen

«+ Neuer Typ» öffnet **sofort dieselbe vollständige Maske wie das Bearbeiten**, mit allen Feldern, den Vorlage-Traktanden, den Teilnehmern und den Optionen (F8).

1. **Titelzeile:** beim Anlegen «Neuer Sitzungstyp», nach dem Speichern «Sitzungstyp bearbeiten». Der **Schliessknopf «✕»** rechts erscheint erst, wenn der Sitzungstyp gespeichert ist.
2. **Knöpfe unten, «Speichern» (links, hervorgehoben) und «Abbrechen»:** Beim Anlegen gibt es kein «✕». «Speichern» ist gesperrt, solange das Namensfeld leer ist. Erst «Speichern» legt den Sitzungstyp an; dabei sind «Kalender anlegen» und «Einladung versenden» eingeschaltet und «Verknüpfung anbieten» ausgeschaltet, sofern nicht anders erfasst. Bei Fehler: «Sitzungstyp konnte nicht erstellt werden: …». Danach geht dieselbe Maske in die Bearbeitung über.
3. **Ein Klick neben die Maske bewirkt nichts:** Angefangene Eingaben bleiben erhalten. Verworfen wird ausschliesslich über «Abbrechen».
4. Alle weiteren Felder, ihre Vorbelegungen, Auswahlwerte und ihr Speicherverhalten beschreiben die folgenden Abschnitte zur Maske «Sitzungstyp bearbeiten»; sie gelten unverändert auch beim Anlegen.

#### Grunddaten des Sitzungstyps

Beim Bearbeiten wird jede Eingabe sofort gespeichert, sobald das Feld verlassen oder der Wert geändert wird. Es gibt keinen Knopf «Speichern»; «✕» und der Klick neben die Maske schliessen nur. Nach jedem erfolgreichen Speichern erscheint kurz die Meldung «Gespeichert», bei Fehlschlag «Sitzungstyp konnte nicht gespeichert werden: …». **Ein leerer Name wird nie gespeichert**; solange das Namensfeld leer ist, bleiben auch alle übrigen Änderungen ungespeichert. Unvollständige Teilnehmerangaben werden beim Speichern ohne Meldung verworfen.

1. **Titelzeile «Sitzungstyp bearbeiten»** mit **Schliessknopf «✕»** (Vorlese-Bezeichnung «Dialog schliessen»).
2. **Feld «Name *»:** Art: Textfeld. **Pflichtfeld: ja.** Vorbelegung: der gespeicherte Name. Speichern beim Verlassen des Feldes.
3. **Feld «Zweck»:** Art: Textbereich, zwei Zeilen hoch. Pflichtfeld: nein. Vorbelegung: der gespeicherte Zweck. Speichern beim Verlassen des Feldes. Dieser Text belegt später den Zweck einer neu erstellten Sitzung vor.
4. **Feld «Standard-Ort»:** Art: Textfeld. Pflichtfeld: nein. Vorbelegung: der gespeicherte Ort. Belegt später das Feld «Ort» einer neuen Sitzung vor.
5. **Feld «Von»:** Art: Zeitfeld. Pflichtfeld: nein. Vorbelegung: die gespeicherte Anfangszeit. Belegt später «Von» einer neuen Sitzung vor.
6. **Feld «Bis»:** Art: Zeitfeld, rechts neben «Von». Pflichtfeld: nein. Vorbelegung: die gespeicherte Endzeit. Belegt später «Bis» einer neuen Sitzung vor.

#### Vorlage-Traktanden des Sitzungstyps

Umrandeter Bereich mit der Bereichsbeschriftung «Vorlage-Traktanden». Die hier erfassten Zeilen erscheinen später als vorbelegte Traktandenliste jeder neuen Sitzung dieses Typs.

Jede Zeile enthält von links nach rechts:

1. **Ziehgriff «⠿»:** Art: Ziehgriff. Die Zeile lässt sich am Griff aufnehmen und über einer anderen Zeile fallen lassen; die Zielzeile wird während des Ziehens hervorgehoben. **Das Umsortieren wird sofort gespeichert.**
2. **Titel:** Platzhalter «Titel». Art: Textfeld. Pflichtfeld: nein. Vorbelegung: gespeicherter Titel. Speichern beim Verlassen des Feldes.
3. **Beschreibung:** Platzhalter «Beschreibung». Art: Textfeld. Pflichtfeld: nein. Vorbelegung: gespeicherte Beschreibung. Speichern beim Verlassen des Feldes.
4. **Knopf «✕»:** entfernt die Zeile und **speichert sofort**.

Am Ende des Bereichs steht:

5. **Knopf «+ Traktandum»:** Art: Knopf (zweite Ebene). Hängt eine leere Zeile an; gespeichert wird erst, wenn eines der beiden Felder ausgefüllt und verlassen wird.

Eine sichtbare Positionsnummer wie im Formular «Neue Sitzung» gibt es hier nicht; die Reihenfolge ergibt sich aus der Anordnung der Zeilen.

#### Teilnehmer des Sitzungstyps

Umrandeter Bereich mit der Bereichsbeschriftung «Teilnehmer». Die hier erfassten Angaben erscheinen später als vorbelegte Teilnehmerliste jeder neuen Sitzung dieses Typs.

1. **Schalter «Eigene Fraktion»:** Beschriftung «Eigene Fraktion», dahinter der Hinweis «→ ‹Name der konfigurierten Gruppe›», sofern eine Gruppe konfiguriert ist (ohne Konfiguration fehlt der Hinweis ganz).
    - Art: Schalter (ein/aus).
    - Vorbelegung: eingeschaltet, wenn beim Typ bereits eine Teilnehmerregel «Eigene Fraktion» gespeichert ist.
    - Dynamisches Verhalten: Einschalten fügt genau eine Regel «Eigene Fraktion» hinzu (mehrfach ist nicht möglich), Ausschalten entfernt alle solchen Regeln.
    - Speichern: **sofort** bei jeder Umschaltung.
2. **Mehrfachauswahl «Einzelne Mitglieder»:** Beschriftung «Einzelne Mitglieder», Platzhalter «Mitglieder hinzufügen…».
    - Art: Mehrfachauswahl mit Suchfunktion; die Liste bleibt nach einer Wahl offen.
    - Pflichtfeld: nein.
    - Vorbelegung: die beim Typ gespeicherten Einzelmitglieder; ist ein gespeichertes Mitglied nicht mehr auffindbar, wird der gespeicherte Name angezeigt.
    - Auswahlwerte: alle aktiven Mitglieder, beschriftet «Vorname Name (Fraktionskürzel)»; das Kürzel entfällt, wenn beim Mitglied keine Fraktion hinterlegt ist. Reihenfolge: alphabetisch nach «Vorname Name»; inaktive Mitglieder sind ausgefiltert. **Bereits gewählte Mitglieder verschwinden aus der Auswahlliste und stehen nur noch im Feld.**
    - Speicherung: **sofort** bei jeder Änderung. Dabei bleiben alle Regeln, die kein einzelnes Mitglied betreffen, etwa «Eigene Fraktion», unverändert erhalten.

Teilnehmerregeln der Arten «Ganze Fraktion», «Ganze Kommission», «Fraktions-Rolle», «Nextcloud-Gruppe» und «Nextcloud-Benutzer» lassen sich in dieser Maske nicht erfassen; sie stehen erst beim Anlegen einer einzelnen Sitzung zur Verfügung. Bereits gespeicherte Regeln dieser Arten bleiben erhalten, sofern sie vollständig sind.

#### Optionen des Sitzungstyps

Umrandeter Bereich mit der Bereichsbeschriftung «Optionen».

1. **Schalter «Beim Anlegen Verknüpfung mit anderen Sitzungen anbieten»**.
    - Art: Schalter (ein/aus).
    - Vorbelegung: der gespeicherte Wert; bei einem neu erstellten Typ ausgeschaltet.
    - **Dynamisches Verhalten: Eingeschaltet erscheint im Formular «Neue Sitzung» dieses Typs zusätzlich das Feld «Verknüpfen mit»; ausgeschaltet fehlt es dort vollständig.**
    - Speichern: **sofort** bei jeder Umschaltung.
2. **Mehrfachauswahl «Kommissionen beraten (hängige Geschäfte automatisch verknüpfen)»:** Beschriftung wie angegeben, Platzhalter «Kommissionen wählen…».
    - Art: Mehrfachauswahl mit Suchfunktion.
    - Pflichtfeld: nein.
    - Vorbelegung: die beim Typ gespeicherten Kommissionen.
    - Auswahlwerte: **alle** Kommissionen der Kommissionsverwaltung (auch inaktive), angezeigt in der Kurzform gemäss den konfigurierten Kürzeln, in der vom Server gelieferten Reihenfolge. Bereits gewählte Kommissionen verschwinden aus der Auswahlliste und stehen nur noch im Feld.
    - Wirkung: Für die gewählten Kommissionen werden bei Sitzungen dieses Typs hängige Geschäfte automatisch verknüpft.
    - Speichern: **sofort** bei jeder Änderung.

Die beim Anlegen gesetzten Einstellungen «Kalender anlegen» und «Einladung versenden» haben in dieser Maske kein Bedienelement; sie bleiben auf ihrem gespeicherten Wert.

---

### Notizen an der Sitzung

Für Notizen an einer Sitzung, an einem Traktandum ohne Geschäft und, nur zum Lesen, an verknüpften Sitzungen steht **dieselbe geteilte Notizenliste** wie bei Geschäften und Vorstössen (Aufbau, Bedienung, Werkzeugleiste und Versionsverlauf siehe «Notizenliste» und «Formatierter Textbereich» unter «Geteilte Bedienelemente»). Je nach Ort unterscheidet sie sich so:

- **Notizen zur Sitzung:** Bereichsüberschrift «Notizen zur Sitzung»; die Notizen gehören zur Sitzung.
- **Notiz zum Traktandum ohne Geschäft:** Hinweistext «Notiz zum Traktandum»; die Notizen gehören zum Traktandum.
- **Verknüpfte Sitzung, nur zum Lesen:** Die Notizenliste erscheint ohne «+ Neue Notiz», ohne Bearbeiten und ohne Löschen. Auch eigene Notizen sind hier nicht anklickbar.

---

### Sitzungsnotiz zum Geschäft

Diese Liste steht in beiden Darstellungen der Traktanden an Stelle der einfachen Notizenliste, sobald dem Traktandum ein Geschäft zugeordnet ist. Die Notizen gehören zum Geschäft und erscheinen an jeder Sitzung, in der es traktandiert ist (F25). Aufbau und Bedienung sind die der geteilten Notizenliste (siehe «Notizenliste» unter «Geteilte Bedienelemente»). Dazu gilt:

1. **Hinweistext «Sitzungsnotiz zum Geschäft»:** reine Anzeige über der Liste.
2. **Gelöschte Notiz:** Sie verschwindet aus dieser Liste und erscheint als Vermerk «‹Name› hat seine Notiz gelöscht» in der **Aktionszeitleiste der Traktandenzeile**. Diese Aktionszeitleiste ist dieselbe wie beim Geschäft und beim Vorstoss und erscheint hier erst, sobald eine Sitzungsnotiz gelöscht ist. Der ursprüngliche Text bleibt verborgen. Daneben steht nur für den Verfasser der Knopf **«↺»** (Hinweistext «Löschen rückgängig machen»): Er holt die Notiz mitsamt Verlauf zurück in die Liste, danach erscheint «Notiz wiederhergestellt».

---

### Seite der App

#### Aufbau der Seitenleiste

1. **Suchbereich:** oberster Eintrag der Seitenleiste, noch vor allen Navigationseinträgen. Art: Bereich, den die jeweils geöffnete Ansicht mit ihrem eigenen Suchfeld füllt. Vorbelegung: leer. Er bleibt ganz leer, solange die geöffnete Ansicht kein Suchfeld mitbringt.
    - Gefüllt wird er von den Ansichten «Geschäfte» (Platzhalter «Nr. oder Titel»), «Sitzungen», «Kommissionen» (Platzhalter «Name oder Beschreibung»), «Stadtratsbeschlüsse» (Platzhalter «Nummer, Titel oder Departement»), «Vorstösse», «Mitglieder» (Platzhalter «Name, Partei oder E-Mail») und «Sitzungstypen».
    - Leer bleibt er in den Ansichten «Budget», «Fragestunde», «Protokoll», «Bedienungsanleitung» und «Änderungsverlauf».
    - Das Suchfeld erscheint, sobald die Ansicht aufgebaut ist, unmittelbar nach dem Umschalten.
2. **Navigationseinträge:** die Ansichten aus F4 in der dort genannten Reihenfolge, jeder Eintrag mit Symbol und Beschriftung; der Eintrag der geöffneten Ansicht ist hervorgehoben.
    - Art: Navigationseinträge in der Art von Knöpfen. Pflichtfeld: entfällt. Ein Klick wechselt sofort die Ansicht im Hauptbereich; Such- und Filterbereich zeigen danach die Elemente der neuen Ansicht. Der Klick schreibt die Ansicht in den Hash der Adresse (F107), sodass das Neuladen der Seite dieselbe Ansicht wieder öffnet; ohne Hash öffnet die App die Ansicht «Geschäfte». Nie gesperrt.
3. **Filterbereich:** unterster Eintrag der Seitenleiste, unterhalb aller Navigationseinträge. Art: Bereich, den die geöffnete Ansicht mit ihren Sortier- und Filterelementen füllt. Vorbelegung: leer.
    - Gefüllt wird er von «Geschäfte», «Sitzungen», «Kommissionen», «Stadtratsbeschlüsse», «Vorstösse», «Budget» und «Mitglieder».
    - Leer bleibt er in «Sitzungstypen», die nur ein Suchfeld mitbringt, und in «Fragestunde», «Protokoll», «Bedienungsanleitung» und «Änderungsverlauf».
4. **Versionsanzeige:** Fusszeile der Seitenleiste, ganz unten. Art: Statustext in der Form «v» gefolgt von der Versionsnummer, etwa «v1.10.0». Quelle: die von der Serverseite mitgelieferte App-Version. Bedingte Sichtbarkeit: erscheint nur, wenn eine Version geliefert wurde; sonst bleibt die Fusszeile ganz weg. Nicht bearbeitbar, keine Speicherung.

#### Meldungs- und Statusanzeigen im Hauptbereich

1. **Meldungsstreifen zur Synchronisation:** oberhalb des Ansichtsinhalts. Art: farbiger Statustext (grün bei Erfolg, rot bei Fehler). Bedingte Sichtbarkeit: nur, wenn eine Meldung vorliegt; die App setzt an dieser Stelle keine Meldung, der Streifen bleibt daher unsichtbar.
2. **Verbindungsanzeige:** kleines Anzeigeelement, das direkt neben dem App-Inhalt eingehängt wird. Art: Statusanzeige mit drei Zuständen:
    - «Verbindung wird hergestellt» beim Laden der Seite; Hinweistext wörtlich «WebSocket-Status: Verbindung wird hergestellt...»
    - «verbunden», sobald die WebSocket-Verbindung steht; Beschriftung für Hilfstechnologien wörtlich «WebSocket verbunden»
    - «getrennt» bei Verbindungsabbruch; Beschriftung wörtlich «WebSocket getrennt»
    - Verhalten: Nach einem Abbruch verbindet die App automatisch neu, zunächst nach einer Sekunde, danach mit jeweils verdoppelter Wartezeit bis höchstens 15 Sekunden. Keine Bedienung möglich, keine Speicherung.
3. **Laden im Hintergrund:** ohne Bedienelement. Meldet die WebSocket-Verbindung eine abgeschlossene Synchronisation, geänderte Mitglieder oder geänderte Fraktionsrollen, werden Mitglieder-, Fraktions- und Kommissionsdaten im Hintergrund neu geladen; alle Ansichten zeigen danach die neuen Werte.

#### Reihenfolge der Mitgliederdaten im Hintergrund

1. Die App lädt beim Start die Mitgliederliste und sortiert sie einmal grundsätzlich: zuerst alle aktiven, danach die ehemaligen Mitglieder; innerhalb der Gruppen alphabetisch nach «Vorname Name». Diese Grundreihenfolge gilt überall dort, wo eine Ansicht nicht selbst anders sortiert.

---

### Ansicht «Mitglieder»

#### Suchbereich der Seitenleiste

1. **Suchfeld:** Beschriftung «Suche», Platzhalter «Name, Partei oder E-Mail». Art: Textfeld mit Löschknopf. Pflichtfeld: nein. Vorbelegung: leer (wird bei jedem Wechsel in die Ansicht neu leer aufgebaut).
    - Der Löschknopf (Kreuz-Symbol am rechten Feldrand) erscheint nur, solange Text im Feld steht; ein Klick leert das Feld sofort.
    - Dynamisches Verhalten: Jede Eingabe filtert die Kartenliste unmittelbar (ohne Bestätigen). Gesucht wird ohne Berücksichtigung von Gross-/Kleinschreibung als Textbestandteil in: «Name Vorname», Partei, Fraktion, E-Mail-Adresse.
    - Der Treffer-Zähler in der Kopfzeile der Ansicht ändert sich mit.
    - Keine Speicherung; nie gesperrt.

#### Gruppe «Sortieren»

1. **Überschrift «Sortieren»:** reiner Titeltext, keine Bedienung.
2. **Auswahlliste «Sortieren nach»:** Art: einfache Auswahlliste, nicht leerbar (es ist immer genau ein Wert gewählt). Pflichtfeld: ja, immer belegt. Vorbelegung: «Funktion».
    - Auswahlwerte, fest in dieser Reihenfolge: «Funktion», «Fraktion», «Partei», «Name».
    - Wirkung «Funktion»: Reihenfolge nach Rang: 1. Fraktionspräsident, 2. Vize Fraktionspräsident, 3. Kommissionspräsident, 4. übrige Mitglieder mit Kommissionssitz, 5. alle übrigen; bei gleichem Rang nach Partei, danach nach «Name Vorname».
    - Wirkung «Fraktion»: nach Fraktionsname, bei Gleichstand nach «Name Vorname».
    - Wirkung «Partei»: nach Parteiname, bei Gleichstand nach «Name Vorname».
    - Wirkung «Name»: nach «Name Vorname».
    - Sortiert wird sofort bei Auswahl; keine Speicherung, die Einstellung fällt beim nächsten Öffnen der Ansicht wieder auf «Funktion» zurück. Nie gesperrt.

#### Gruppe «Filter»

1. **Überschrift «Filter»:** reiner Titeltext, keine Bedienung.
2. **Auswahlliste «Fraktion»:** Art: einfache Auswahlliste, nicht leerbar. Pflichtfeld: ja, immer belegt. Vorbelegung: «Alle Fraktionen».
    - Auswahlwerte: erster Eintrag «Alle Fraktionen» (kein Filter), danach alle Fraktionsnamen, die in der aktuell zugrunde liegenden Mitgliedermenge vorkommen, ohne Doppelte, alphabetisch aufsteigend. Leere Fraktionsangaben werden weggelassen.
    - Angezeigt werden die Namen in der gekürzten Form gemäss den in der Verwaltung definierten Kürzeln; gefiltert wird immer nach dem vollständigen Originalnamen.
    - Dynamisches Verhalten: Die Liste hängt vom Schalter «Nur aktive Mitglieder» ab: Ist er eingeschaltet, erscheinen nur Fraktionen, in denen aktive Mitglieder sitzen; beim Ausschalten kommen Fraktionen ehemaliger Mitglieder dazu.
    - Wirkt sofort, keine Speicherung, nie gesperrt.
3. **Auswahlliste «Partei»:** Art: einfache Auswahlliste, nicht leerbar. Vorbelegung: «Alle Parteien».
    - Auswahlwerte: «Alle Parteien», danach alle Parteinamen aus derselben zugrunde liegenden Mitgliedermenge, ohne Doppelte, alphabetisch; leere Angaben entfallen; Anzeige gekürzt, Filterung nach Originalnamen.
    - Hängt ebenfalls vom Schalter «Nur aktive Mitglieder» ab. Wirkt sofort, keine Speicherung.
4. **Auswahlliste «Kommission»:** Art: einfache Auswahlliste, nicht leerbar. Vorbelegung: «Alle Kommissionen».
    - Auswahlwerte: «Alle Kommissionen», danach **nur die Kommissionen, denen mindestens ein sichtbares Mitglied tatsächlich angehört** (Grundprinzip aus F16), ohne Doppelte, alphabetisch nach deutschen Sortierregeln; Anzeige gekürzt.
    - Wirkung: zeigt nur Mitglieder, die in der gewählten Kommission sitzen.
    - Hängt vom Schalter «Nur aktive Mitglieder» ab (er bestimmt die zugrunde liegende Mitgliedermenge). Wirkt sofort, keine Speicherung.
5. **Auswahlliste «Funktion»:** Art: einfache Auswahlliste, nicht leerbar. Vorbelegung: «Alle Funktionen».
    - Auswahlwerte: «Alle Funktionen» und danach in der festen Reihenfolge «Fraktionspräsident», «Kommissionspräsident» **nur die Funktionen, die unter den sichtbaren Mitgliedern tatsächlich vorkommen**; gibt es keinen Kommissionspräsidenten, entfällt der Eintrag.
    - Wirkung «Fraktionspräsident»: nur Mitglieder, deren Fraktionsfunktion als Fraktionspräsident erkannt wurde; Stellvertreter zählen hier nicht.
    - Wirkung «Kommissionspräsident»: nur Mitglieder, die mindestens eine Kommission präsidieren; Vizepräsidenten zählen nicht.
    - Hängt vom Schalter «Nur aktive Mitglieder» ab (er bestimmt die zugrunde liegende Mitgliedermenge). Wirkt sofort, keine Speicherung.
6. **Schalter «Nur aktive Mitglieder»:** Art: Umschalter (ein/aus). Vorbelegung: eingeschaltet.
    - Wirkung eingeschaltet: Es werden ausschliesslich aktive Mitglieder angezeigt.
    - Wirkung ausgeschaltet: Ehemalige Mitglieder erscheinen zusätzlich, ihre Karten sind abgeschwächt dargestellt und mit einem Kennzeichen versehen.
    - Dynamisches Verhalten: Der Schalter verändert die Kartenliste und den Inhalt der Auswahllisten «Fraktion», «Partei», «Kommission» und «Funktion» (siehe dort).
    - Wirkt sofort, keine Speicherung, nie gesperrt.

#### Kopfzeile und Kartenliste

1. **Titel «Mitglieder»:** Statustext.
2. **Trefferzähler:** Statustext (Zahl) direkt rechts vom Titel; zeigt die Anzahl der aktuell angezeigten Karten und ändert sich mit jeder Sucheingabe und jedem Filter.
3. **Mitgliederkarte:** je Mitglied eine Karte im Raster; Reihenfolge gemäss gewählter Sortierung. Inhalt in dieser Reihenfolge:
    1. **Name:** «Vorname Name», hervorgehoben.
    2. **Partei:** gekürzter Parteiname; ist keine Partei hinterlegt, steht dort «Ohne Partei».
    3. **Fraktionsfunktion:** Kennzeichen, nur sichtbar, wenn das Mitglied in einer aktiven Fraktion eine Präsidialfunktion hat; wörtlich entweder «Fraktionspräsident» oder «Vize Fraktionspräsident».
    4. **Angabe «Fraktion»:** Beschriftung «Fraktion» und daneben der gekürzte Fraktionsname; ohne Fraktion steht dort «—».
    5. **Angabe «Kommission»:** Beschriftung «Kommission» und daneben alle Kommissionen des Mitglieds; nur sichtbar, wenn das Mitglied mindestens einer aktiven Kommission angehört. Je Eintrag der gekürzte Kommissionsname und, sofern vorhanden, die Rolle «Präsident» oder «Vizepräsident». Reihenfolge: zuerst Präsidien, dann Vizepräsidien, danach alphabetisch nach Kommissionsname.
    6. **E-Mail-Adresse:** anklickbarer Verweis, der das Mailprogramm öffnet; nur sichtbar, wenn eine Adresse hinterlegt ist.
    7. **Kennzeichen «Ehemaliges Mitglied»:** nur sichtbar bei nicht mehr aktiven Mitgliedern (und damit nur, wenn der Schalter «Nur aktive Mitglieder» ausgeschaltet ist).
    - Die Karten sind reine Anzeige: nichts ist bearbeitbar, nichts wird gespeichert.
4. **Hinweis «Keine Mitglieder gefunden»:** Leermeldung, erscheint anstelle von Karten, sobald Suche und Filter keine Treffer ergeben.

---

### Ansicht «Kommissionen»

#### Suchbereich der Seitenleiste

1. **Suchfeld:** Beschriftung «Suche», Platzhalter «Name oder Beschreibung». Art: Textfeld mit Löschknopf (Kreuz), der nur bei gefülltem Feld erscheint. Pflichtfeld: nein. Vorbelegung: leer.
    - Gesucht wird ohne Rücksicht auf Gross-/Kleinschreibung in: Kommissionsname, Beschreibung, allen Mitgliedern der Kommission (Name, Partei, Fraktion, E-Mail, Funktion) sowie in den pendenten Geschäften (Geschäftsnummer und Titel).
    - Dynamisches Verhalten: Sobald Text eingegeben wird, klappen die Mitglieder jeder Karte auf, in der **ein Mitglied** der Treffer ist. Die Geschäfte stehen ohnehin offen, darum klappt ein Treffer im Kommissionsnamen, in der Beschreibung oder in einem Geschäft nichts auf. Von Hand aufgeklappte Mitglieder bleiben offen; beim Leeren des Feldes wird nichts wieder zugeklappt.
    - Wirkt sofort, keine Speicherung, nie gesperrt.

#### Filterbereich der Seitenleiste

1. **Schalter «Nur aktive Kommissionen»:** Art: Umschalter. Vorbelegung: eingeschaltet.
    - Wirkung eingeschaltet: aufgelöste oder inaktive Kommissionen werden ausgeblendet. Als inaktiv gilt eine Kommission, die als gelöscht gemeldet ist, deren Aktiv-Kennzeichen aus der Synchronisation «nein» lautet, oder deren Enddatum in der Vergangenheit liegt.
    - Wirkung ausgeschaltet: Auch aufgelöste Kommissionen erscheinen, abgeschwächt dargestellt und mit entsprechendem Statustext und Hinweis.
    - Wirkt sofort, keine Speicherung.
2. **Schalter «Nur aktive Mitglieder»:** Art: Umschalter. Vorbelegung: eingeschaltet.
    - Wirkung: Steuert, ob in der Mitgliederliste einer Karte auch ausgeschiedene Kommissionsmitglieder aufgeführt werden. Ausgeschaltet erscheinen sie zusätzlich, abgeschwächt dargestellt und immer am Ende der Mitgliederliste.
    - Dynamisches Verhalten: Ist der Schalter ausgeschaltet und weicht die Zahl der angezeigten von der Zahl der aktiven Mitglieder ab, wird im Schalter «Mitglieder» zusätzlich die Zahl der aktiven Mitglieder ausgewiesen.
    - Wirkt sofort, keine Speicherung.

#### Kopfzeile und Kartenliste

1. **Titel «Kommissionen»:** Statustext.
2. **Trefferzähler:** Statustext (Zahl) rechts vom Titel; Anzahl der aktuell angezeigten Kommissionen.
3. **Ladeanzeige:** drehendes Symbol, sichtbar solange die Kommissionen geladen werden; danach erscheint die Kartenliste.
4. **Kommissionskarte, Kopfbereich:** je Kommission eine Karte; der Kopfbereich ist nicht anklickbar, die Karte steht immer offen:
    1. **Kommissionsname:** gekürzt gemäss den in der Verwaltung definierten Kürzeln.
    2. **Statustext:** wörtlich «Aktiv» oder «Aufgelöst oder inaktiv».
    3. **Knopf «Neues Geschäft»:** nur an aktiven Kommissionen; öffnet die Maske für ein neues eigenes Geschäft mit dieser Kommission im Feld «Kommission» (F141). Nach «Speichern» bearbeitet dieselbe Maske das neue Geschäft.
5. **Leermeldung «Keine Kommissionen gefunden»:** erscheint, wenn Suche und Schalter keine Kommission übrig lassen.

#### Inhalt einer Kommissionskarte

1. **Beschreibung:** Fliesstext; nur sichtbar, wenn zur Kommission eine Beschreibung vorliegt.
2. **Zwischenüberschrift «Pendente Geschäfte (n):»:** mit der Anzahl in Klammern; nur sichtbar, wenn mindestens ein pendentes Geschäft zugeordnet ist.
3. **Liste der pendenten Geschäfte:** je Geschäft eine Zeile, bestehend aus:
    1. **Geschäftsnummer:** Statustext.
    2. **Verweis «↗»:** öffnet das Geschäft auf der Parlamentswebseite in einem neuen Fenster; Hinweistext wörtlich «Extern öffnen»; nur sichtbar, wenn eine Webadresse hinterlegt ist. Der Klick auf diesen Verweis öffnet nicht die Detailmaske.
    3. **Geschäftstitel:** Statustext.
    - Die ganze Zeile ist anklickbar (auch per Eingabe- oder Leertaste bedienbar) und öffnet die Detailmaske des Geschäfts.
    - Zuordnung: Es erscheinen Geschäfte, deren Status auf diese Kommission verweist (Statustexte der Form «Bei der Kommission … pendent») oder denen sie im Feld «Kommission» zugewiesen ist (F46).
4. **Hinweis «Keine Geschäfte mit Status ‹Bei Kommission Name pendent› und keines, das dieser Kommission zugewiesen ist.»:** erscheint anstelle der Liste, wenn der Kommission kein Geschäft zugeordnet ist; der Kommissionsname erscheint darin gekürzt.
5. **Schalter «Mitglieder (n):»:** mit der Anzahl der angezeigten Mitglieder; ist der Schalter «Nur aktive Mitglieder» ausgeschaltet und unterscheiden sich die Zahlen, lautet er «Mitglieder (n / m aktiv):». Rechts davon steht «▼», wenn zugeklappt, und «▲», wenn aufgeklappt. Die ganze Zeile ist anklickbar (auch per Eingabe- oder Leertaste bedienbar) und zeigt oder verbirgt die Mitgliederkarten.
    - Vorbelegung: zugeklappt. Trifft eine Sucheingabe ein Mitglied dieser Kommission, klappt sie von selbst auf; ein Treffer allein im Namen, in der Beschreibung oder in einem Geschäft tut das nicht. Ob die Mitglieder auf- oder zugeklappt sind, wird nicht gespeichert.
6. **Mitgliederkarten der Kommission:** nur sichtbar, solange der Schalter aufgeklappt ist; je Mitglied eine kleine Karte; Inhalt in dieser Reihenfolge:
    1. **Name:** hervorgehoben; stammt aus der Synchronisation oder aus dem zugeordneten Mitglied.
    2. **Funktion:** Kennzeichen, nur sichtbar, wenn eine Funktion hinterlegt ist, etwa Präsident, Vizepräsident oder Protokollführer.
    3. **Partei:** gekürzt; nur sichtbar, wenn bekannt.
    4. **Fraktion:** gekürzt; nur sichtbar, wenn bekannt.
    5. **E-Mail-Adresse:** anklickbarer Verweis zum Mailprogramm; nur sichtbar, wenn bekannt.
    - Darstellung: Mitglieder der eigenen Fraktion sind hervorgehoben. Die eigene Fraktion ergibt sich aus dem angemeldeten Benutzerkonto, sofern dieses einem Mitglied zugeordnet ist; ohne Zuordnung entfällt die Hervorhebung.
    - Ausgeschiedene Mitglieder sind abgeschwächt dargestellt.
    - Reihenfolge: zuerst alle aktiven, dann die ausgeschiedenen; innerhalb davon 1. Präsident, 2. Vizepräsident, 3. einfache Mitglieder der eigenen Fraktion, 4. übrige einfache Mitglieder, 5. weitere Rollen wie Protokollführer; bei Gleichstand alphabetisch nach Name.
7. **Hinweis «Keine Mitglieder synchronisiert.»:** erscheint anstelle der Mitgliederliste, wenn zur Kommission keine (sichtbaren) Mitglieder vorliegen.
8. **Hinweis «Diese Kommission erscheint nur, weil historische Daten vorhanden sind.»:** erscheint nur bei aufgelösten oder inaktiven Kommissionen, also nur bei ausgeschaltetem Schalter «Nur aktive Kommissionen».

#### Detailmaske aus der Kommission

1. **Maske:** öffnet sich über der Ansicht, sobald ein pendentes Geschäft angeklickt wird. Ein Klick auf die abgedunkelte Fläche neben der Maske schliesst sie.
2. **Knopf «✕»:** oben rechts in der Maske, Beschriftung für Hilfstechnologien wörtlich «Dialog schliessen»; schliesst die Maske; steht in einer Notiz noch ungespeicherter Text, fragt die Maske beim Schliessen mit ✕ und beim Klick daneben zuerst «Die Notiz ist noch nicht gespeichert. Trotzdem schliessen?».
3. **Geschäftsdetails:** In der Maske steht die vollständige Geschäfts-Detailmaske mit allen ihren Bedienelementen. Nach dem Speichern kommt nur dieses Geschäft neu in die geteilte Liste (F150), und die Liste der pendenten Geschäfte zeigt es sofort.
4. **Verweis auf ein anderes Geschäft** (etwa das offizielle eines eigenen Geschäfts): öffnet dieses in derselben Maske.

---

### Ansicht «Änderungsverlauf»

1. **Suchbereich und Filterbereich der Seitenleiste:** bleiben in dieser Ansicht vollständig leer; die Ansicht bringt weder Suchfeld noch Filter mit.
2. **Titel «Änderungsverlauf»:** Statustext in der Kopfzeile.
3. **Versionszähler:** Statustext (Zahl) rechts vom Titel; Anzahl der aufgeführten Versionen.
4. **Versionskarte:** je Version eine Karte, in der Reihenfolge der Änderungsliste (neueste Version zuoberst). Der Kopfbereich ist anklickbar und zusätzlich mit der Eingabetaste bedienbar; er enthält:
    1. **Versionsnummer:** Statustext.
    2. **Datum:** Statustext im Format «JJJJ-MM-TT».
    3. **Zeichen zum Aufklappen:** «▼», wenn zugeklappt, «▲», wenn aufgeklappt.
5. **Änderungsliste einer Version:** nur im aufgeklappten Zustand sichtbar; zeigt die Änderungen als formatierte, gegebenenfalls verschachtelte Aufzählung mit Hervorhebungen und Verweisen.
    - Vorbelegung: Alle Versionen der neuesten Haupt- und Nebenversion, etwa alle Einträge «1.10.x», sind aufgeklappt, alle älteren zugeklappt.
    - Ein Klick klappt die jeweilige Karte auf oder zu; die Zustände werden nicht gespeichert und fallen beim nächsten Öffnen der Ansicht auf die Vorbelegung zurück.
6. Die Ansicht ist reine Anzeige: keine Eingabe, keine Speicherung, nichts gesperrt.

---

### Verwaltungsbereich

#### Zugang und Aufbau

1. **Sichtbarkeit:** Der gesamte Verwaltungsbereich liegt in den Nextcloud-Verwaltungseinstellungen im eigenen Abschnitt «Parlament Winterthur» und ist ausschliesslich für Administratoren sichtbar und bedienbar. Alle Aktionen darin (Synchronisation, Zuordnungen, Zeitplan, Kürzel, Einstellungen) werden zusätzlich serverseitig auf Administratorrechte geprüft; ohne Rechte erscheint die Meldung «Zugriff verweigert. Bitte als Admin anmelden und erneut versuchen.», ohne Anmeldung «Nicht angemeldet. Bitte neu anmelden und erneut versuchen.».
2. **Aufbau:** links eine schmale Spalte mit Synchronisation und Speicherstatus, rechts die Konfigurationskarten in dieser Reihenfolge: «Fraktionskonfiguration», «Fraktionsmitglieder ↔ Nextcloud-Benutzer», «Typen für eigene Geschäfte», «Automatische Synchronisation», «Kürzel», «Budget: Departement → zuständige Kommission», «E-Mail-Einladungen».

#### Synchronisation (linke Spalte)

1. **Knopf «Jetzt synchronisieren»:** Art: Knopf. Startet die Synchronisation mit der Parlamentswebseite und der Webseite der Stadt.
    - Gesperrt: während eine Synchronisation läuft; ebenso unmittelbar nach dem Anklicken, bis die Antwort des Servers eintrifft.
    - Beim Klick werden Fortschrittsbalken auf 0, Prozentanzeige auf «0%» und die Detailzeile geleert, der Abbruchknopf freigegeben und der Statustext auf «Synchronisation wird gestartet...» gesetzt.
2. **Knopf «Synchronisierung abbrechen»:** Art: Knopf. Vorbelegung: gesperrt.
    - Freigegeben: sobald eine Synchronisation läuft.
    - Gesperrt: solange keine Synchronisation läuft und ebenso, sobald ein Abbruch bereits angefragt wurde.
    - Beim Klick erscheint zunächst «Abbruch wird angefordert...», nach Bestätigung durch den Server «Abbruch angefragt...».
3. **Statustext der Synchronisation:** Art: Statustext neben den Knöpfen. Mögliche Texte wörtlich:
    - «Synchronisation wird gestartet...», «Synchronisation gestartet», «Synchronisiere...», «Synchronisation läuft bereits»
    - «Abbruch wird angefordert...», «Abbruch angefragt...», «Synchronisation abgebrochen»
    - «Synchronisation abgeschlossen: ‹Zeitpunkt›»
    - «Fehler: ‹Meldung›»; als Meldung erscheinen unter anderem «Sicherheitsprüfung fehlgeschlagen (Token ungültig/abgelaufen). Seite neu laden und erneut versuchen.», «Sicherheitsprüfung fehlgeschlagen (HTTP 412). Seite neu laden und erneut versuchen.», «Zugriff verweigert. Bitte als Admin anmelden und erneut versuchen.», «Nicht angemeldet. Bitte neu anmelden und erneut versuchen.», «Synchronisations-Endpunkt nicht gefunden. Bitte App-Installation prüfen.», «Serverfehler beim Synchronisieren. Details im Nextcloud-Log prüfen.», «Verbindungsfehler», «Ungültige Serverantwort», «Unbekannter Fehler».
4. **Fortschrittsbalken:** Art: Fortschrittsbalken von 0 bis 100. Vorbelegung: 0.
    - Quelle: Gesamtfortschritt über alle aktiven Teilbereiche (Mitglieder, Fraktionen, Kommissionen, Geschäfte, Sitzungen, Stadtratsbeschlüsse), also die Summe der verarbeiteten Datensätze geteilt durch die Summe aller erwarteten Datensätze.
    - Bei abgeschlossener Synchronisation wird er auf 100 gesetzt, im Ruhezustand auf 0.
5. **Prozentanzeige:** Art: Statustext rechts vom Balken, Vorbelegung «0%»; zeigt denselben Wert wie der Balken, gerundet auf ganze Prozent.
6. **Detailzeile zum Fortschritt:** Art: Statustext unterhalb des Balkens. Vorbelegung: leer.
    - Während des Laufs in der Form: «‹Teilbereich› (‹Datenbereich›) - ‹verarbeitet›/‹gesamt› | Gesamt ‹verarbeitet›/‹gesamt› (‹Prozent›%) | Quelle ‹Auslöser› | Restzeit ‹Restzeit› | Laufzeit ‹Dauer›».
    - Nach Abbruch oder Abschluss: «Laufzeit: ‹Dauer›».
    - Im Fehlerfall: der Fehlertext.
    - Im Ruhezustand: leer.
7. **Aktualisierung der Anzeige:** Der Fortschritt wird über die WebSocket-Verbindung sofort nachgeführt; zusätzlich fragt die Seite den Stand regelmässig ab (etwa alle 1,2 Sekunden, bei stehender Verbindung etwa alle 5 Sekunden) sowie immer dann, wenn das Fenster wieder in den Vordergrund kommt. Dadurch bleibt die Anzeige auch dann aktuell, wenn die Verbindung unterbrochen ist.
8. **Speicherstatus:** Art: Statustext unterhalb des Fortschritts. Vorbelegung wörtlich «Bereit für automatische Speicherung». Weitere Texte: «Änderungen erkannt...» (unmittelbar nach einer Eingabe), «Speichere automatisch...» (während des Speicherns), «Alle Änderungen gespeichert» (nach Erfolg oder wenn nichts zu speichern war), «Automatisches Speichern fehlgeschlagen» (bei Fehler; zusätzlich erscheint die kurzzeitige Systemmeldung «Fehler beim Speichern: ‹Meldung›»).
9. **Angabe «Letzte Synchronisation: ‹Zeitpunkt›»:** Art: Statustext. Bedingte Sichtbarkeit: nur wenn bereits einmal synchronisiert wurde; Quelle ist der gespeicherte Zeitpunkt der letzten erfolgreichen Synchronisation, gelesen beim Aufbau der Seite.

#### Karte «Fraktionskonfiguration»

1. **Erklärungstext:** wörtlich «Wähle Fraktion und Zielgruppe für die interne Fraktionsarbeit.»
2. **Auswahlliste «Fraktion (aus synchronisierten Fraktionen)»:** Art: Auswahlliste. Pflichtfeld: ja; ohne Fraktion bleibt die Mitgliederzuordnung leer, und «Ausgewählte abgleichen» verweigert die Ausführung.
    - Vorbelegung: die gespeicherte Fraktion; ist keine gespeichert, der Eintrag «Bitte Fraktion wählen».
    - Auswahlwerte in dieser Reihenfolge: 1. «Bitte Fraktion wählen» (leerer Wert), 2. alle **aktiven** Fraktionen aus der synchronisierten Datenbank, ohne Doppelte, alphabetisch ohne Beachtung der Gross-/Kleinschreibung sortiert. Inaktive Fraktionen und namenlose Einträge werden ausgefiltert.
    - Zusatzeintrag: Ist die gespeicherte Fraktion nicht mehr unter den synchronisierten, erscheint sie als letzter Eintrag mit dem Zusatz «[nicht mehr synchronisiert]» und ist vorausgewählt.
    - Gesperrt: solange überhaupt keine Fraktionen in der Datenbank vorliegen; darunter erscheint dann der Hinweis «Noch keine Fraktionen in der Datenbank. Bitte zuerst synchronisieren.»
    - Dynamisches Verhalten: Eine Änderung speichert die Einstellungen sofort **und** lädt die Tabelle der Fraktionsmitglieder neu (Statustext «Lade Mitglieder...»). Wird eine Fraktion gewählt, die der Server nicht kennt, lehnt er das Speichern mit «Bitte eine vorhandene Fraktion aus der Liste wählen.» ab.
3. **Textfeld «Nextcloud-Gruppe (bestehend wählen oder neu erstellen)»:** Art: Textfeld mit Vorschlagsliste. Platzhalter «z.B. Fraktion-Muster». Pflichtfeld: für «Ausgewählte abgleichen» ja, sonst nein.
    - Vorbelegung: die gespeicherte Gruppe (Standard: leer).
    - Vorschlagswerte: alle bestehenden Nextcloud-Gruppen (bis zu 500), ohne Doppelte, alphabetisch ohne Beachtung der Gross-/Kleinschreibung; leere Namen entfallen. Eigene, noch nicht existierende Namen dürfen frei eingetippt werden.
    - **Zustandstext darunter**: leer, solange das Feld leer ist; «Bestehende Gruppe», wenn der eingetippte Name (ohne Beachtung der Gross-/Kleinschreibung) einer beim Seitenaufbau bekannten Gruppe entspricht; sonst «Neue Gruppe wird angelegt».
    - Dynamisches Verhalten: Jede Änderung aktualisiert zusätzlich sofort die Spalte «Gruppen» aller Zeilen der Mitgliedertabelle.
    - Speicherung: 0,5 Sekunden nach der letzten Tasteneingabe automatisch; beim Verlassen und bei einer Auswahl aus der Vorschlagsliste sofort.
4. **Speicherverhalten der ganzen Karte:** Alle Felder ausserhalb der Mitgliedertabelle speichern automatisch: verzögert 0,5 Sekunden nach der letzten Tasteneingabe, sofort bei abgeschlossener Änderung (Verlassen des Feldes, Auswahl in einer Liste). Ist der Inhalt unverändert, wird nicht erneut gespeichert und es erscheint direkt «Alle Änderungen gespeichert». Ein Absenden per Eingabetaste löst ebenfalls sofortiges Speichern aus und lädt die Seite nicht neu.

#### Karte «Fraktionsmitglieder ↔ Nextcloud-Benutzer»

1. **Erklärungstext:** wörtlich «Nach der Wahl der Fraktion lassen sich die Mitglieder lokalen Benutzern zuordnen und die ausgewählten anlegen.»
2. **Knopf «Ausgewählte abgleichen»:** Art: Knopf. Legt fehlende Benutzerkonten an, gleicht bestehende ab und deaktiviert angekreuzte verwaiste Konten.
    - Vor der Ausführung werden die Einstellungen der Seite sofort gespeichert.
    - Abbruchbedingungen mit Statustext statt Ausführung: ohne gewählte Fraktion «Bitte zuerst eine Fraktion wählen.»; ohne Gruppenname «Bitte eine Nextcloud-Gruppe setzen.»; ohne angekreuzte Zeile «Bitte mindestens einen Eintrag auswählen.»
    - Während der Ausführung: «Gleiche Benutzer ab...»; danach «Angelegt: ‹Anzahl›, abgeglichen: ‹Anzahl›, deaktiviert: ‹Anzahl›», bei Problemen ergänzt um « — » und die Warnungstexte (etwa dass ein Konto nicht angelegt oder nicht deaktiviert werden konnte).
    - Was geschieht: Für Mitglieder ohne bestehendes Konto wird ein neues Benutzerkonto mit dem angezeigten Benutzernamen angelegt und eine Einladungsmail verschickt. Für Mitglieder mit bestehendem Konto werden Profilfelder abgeglichen und das Konto bei Bedarf wieder aktiviert und der Gruppe zugewiesen, ohne Mail. Angekreuzte verwaiste Konten werden aus der Gruppe entfernt und deaktiviert, ebenfalls ohne Mail. Die Tabelle wird anschliessend neu aufgebaut.
    - Nie dauerhaft gesperrt.
3. **Statustext der Mitgliederkarte:** Art: Statustext neben dem Knopf. Texte: «Lade Mitglieder...», «‹Anzahl› Mitglieder geladen», «‹Anzahl› Mitglieder geladen, ‹Anzahl› verwaiste lokale Benutzer», «Speichere Zuordnungen...», «‹Anzahl› Zuordnungen gespeichert», «Gleiche Benutzer ab...», das Abgleichsergebnis (siehe oben) sowie «Fehler: ‹Meldung›».
4. **Hinweiszeile über der Tabelle:** Art: Statustext. Vorbelegung wörtlich «Bitte zuerst eine Fraktion wählen.»
    - Gleicher Text, solange keine Fraktion gewählt ist.
    - «Keine aktiven Mitglieder für diese Fraktion gefunden.», wenn die gewählte Fraktion weder Mitglieder noch verwaiste Konten liefert.
    - Leer, sobald Zeilen angezeigt werden.
5. **Tabelle der Fraktionsmitglieder:** Spalten in dieser Reihenfolge:
    1. **Spalte Auswahl:** Kopf: Ankreuzfeld «Alle wählen» (Beschriftung für Hilfstechnologien). Vorbelegung: angekreuzt, sobald Zeilen vorhanden sind; nicht angekreuzt, wenn die Tabelle leer ist. Ein Klick setzt oder entfernt das Häkchen in **allen** Zeilen, auch bei den verwaisten.
    2. **Spalte «Mitglied»:** Tabellenspalte, Anzeige des vollständigen Namens aus der Synchronisation.
    3. **Spalte «E-Mail»:** Tabellenspalte, Anzeige der bei der Synchronisation gefundenen Adresse; leer, wenn keine bekannt ist.
    4. **Spalte «Benutzername»:** Tabellenspalte mit Textfeld je Zeile (siehe unten).
    5. **Spalte «Gruppen»:** Tabellenspalte mit Statustext je Zeile (siehe unten).
    - Die Zeilen erscheinen nur, wenn eine Fraktion gewählt ist; aufgeführt werden ausschliesslich die **aktiven** Mitglieder der gewählten Fraktion, danach die verwaisten Konten.
6. **Zeile eines Fraktionsmitglieds**:
    1. **Ankreuzfeld:** Vorbelegung: angekreuzt. Bestimmt, ob das Mitglied beim Abgleich berücksichtigt wird.
    2. **Name:** Statustext, nicht bearbeitbar.
    3. **E-Mail:** Statustext, nicht bearbeitbar.
    4. **Textfeld Benutzername:** Pflichtfeld: nein; bleibt es leer, wird beim Speichern automatisch der Vorschlag verwendet. Vorbelegung: der gespeicherte Benutzername oder, wenn bereits ein passendes Konto gefunden wurde, dessen Benutzername; ist nichts gespeichert, der Vorschlag. Platzhalter: der automatische Vorschlag, gebildet aus «Vorname-Name» in Kleinbuchstaben mit umgeschriebenen Umlauten und Sonderzeichen.
        - Gesperrt: wenn zu diesem Mitglied bereits ein lokales Benutzerkonto gefunden wurde; der Hinweistext nennt dann wörtlich «Lokales Benutzerkonto existiert bereits (‹Fundart›)» mit der Fundart (über Benutzername, über E-Mail-Adresse oder über Anzeigename).
        - Dynamisches Verhalten: Jede Tasteneingabe aktualisiert sofort die Spalte «Gruppen» derselben Zeile und setzt die Zeile auf «noch kein Konto vorhanden» zurück.
        - Speicherung: automatisch 0,5 Sekunden nach der letzten Tasteneingabe; dabei erscheint «Speichere Zuordnungen...» und danach «‹Anzahl› Zuordnungen gespeichert». Die allgemeine Einstellungsspeicherung der Seite wird durch Eingaben in der Tabelle ausdrücklich **nicht** ausgelöst.
    5. **Statustext Gruppen:** nicht bearbeitbar; Inhalt abhängig vom Zustand: bei bestehendem Konto die konfigurierte Fraktionsgruppe zusammen mit allen bereits vorhandenen Gruppen des Kontos, kommagetrennt und ohne Doppelte; bei leerem Benutzernamen wörtlich «Bitte Benutzername setzen»; sonst nur die konfigurierte Fraktionsgruppe (die beim Abgleich zugewiesen würde).
7. **Zeile eines verwaisten Kontos:** erscheint für jedes Konto, das in der konfigurierten Nextcloud-Gruppe ist, aber keinem aktiven Mitglied der gewählten Fraktion entspricht. Sichtbar nur, wenn eine Gruppe konfiguriert ist.
    1. **Ankreuzfeld:** Vorbelegung: **nicht** angekreuzt. Angekreuzt bedeutet: beim Abgleich aus der Gruppe entfernen und Konto deaktivieren.
    2. **Name:** durchgestrichen dargestellt; Hinweistext wörtlich «In Nextcloud-Gruppe, aber laut Webseite nicht mehr in der Fraktion. Anwählen zum Deaktivieren.»
    3. **E-Mail:** durchgestrichen dargestellt, aus dem Benutzerkonto.
    4. **Benutzername:** Statustext (Benutzername des Kontos), nicht bearbeitbar.
    5. **Gruppen:** Statustext mit der Fraktionsgruppe und den weiteren Gruppen des Kontos.

#### Karte «Typen für eigene Geschäfte»

1. **Erklärungstext:** wörtlich «Diese Typen stehen beim Anlegen eines eigenen Geschäfts zur Auswahl. Ohne Eintrag bleibt es beim Typ ‹Eigenes Geschäft›.»
2. **Typenzeilen:** beliebig viele; Vorbelegung beim Öffnen: die gespeicherten Typen in gespeicherter Reihenfolge. Je Zeile in dieser Reihenfolge:
    1. **Textfeld Bezeichnung:** Platzhalter «z.B. Kommissionsgeschäft». Pflichtfeld: für das Speichern der Zeile ja (leere Zeilen zählen nicht).
    2. **Löschknopf «×»:** Hinweistext «Löschen»; entfernt die Zeile sofort und stösst das Speichern an.
3. **Knopf «+ Typ hinzufügen»:** fügt am Ende eine leere Zeile an und setzt den Schreibcursor hinein. Speichert selbst noch nicht.
4. **Statustext:** «Speichern...» unmittelbar nach jeder Änderung, danach «Gespeichert» oder «Fehler beim Speichern».
5. **Speicherung:** Jede Änderung an einem Textfeld und jedes Löschen einer Zeile startet die Speicherung; ausgeführt wird sie kurz nach der letzten Änderung, wobei stets die komplette Liste ersetzt wird. Dabei werden leere Einträge und Doppelte entfernt, die Reihenfolge bleibt erhalten. Es gibt keinen Knopf «Speichern» und keine Sperre.

#### Karte «Automatische Synchronisation»

1. **Erklärungstext:** wörtlich «Zeitplan der automatischen Synchronisation: beliebige Einträge mit Wochentagen und Uhrzeit. Ohne Eintrag wird an allen Wochentagen um 10:00 und 18:00 Uhr synchronisiert.»
2. **Zeitplanzeilen:** beliebig viele; Vorbelegung beim Öffnen: der gespeicherte Zeitplan. Ist keiner gespeichert, erscheinen zwei Zeilen mit allen sieben Wochentagen und den Uhrzeiten 10:00 und 18:00, die direkt bearbeitet werden können. Je Zeile in dieser Reihenfolge:
    1. **Sieben Ankreuzfelder** mit den Beschriftungen «Mo», «Di», «Mi», «Do», «Fr», «Sa», «So», in genau dieser Reihenfolge. Vorbelegung: gemäss gespeichertem Eintrag; bei einer neu hinzugefügten Zeile keiner angekreuzt.
    2. **Uhrzeitfeld:** Art: Zeiteingabefeld (Stunden/Minuten). Vorbelegung: gespeicherte Uhrzeit; bei neuer Zeile leer.
    3. **Löschknopf «×»:** Hinweistext wörtlich «Löschen»; entfernt die Zeile sofort und stösst das Speichern an.
    - Pflichtfeld: Eine Zeile zählt nur, wenn mindestens ein Wochentag angekreuzt **und** eine Uhrzeit gesetzt ist; unvollständige Zeilen werden beim Speichern ohne Meldung verworfen, bleiben aber sichtbar, bis die Seite neu geladen wird.
3. **Knopf «+ Zeit hinzufügen»:** Art: Knopf. Fügt am Ende eine leere Zeile an und setzt den Schreibcursor in deren Uhrzeitfeld. Speichert selbst noch nicht.
4. **Statustext des Zeitplans:** Art: Statustext neben dem Knopf. «Speichern...» unmittelbar nach jeder Änderung, danach «Gespeichert» (verschwindet nach rund 2,5 Sekunden von selbst) oder «Fehler beim Speichern» (bleibt stehen).
5. **Speicherung:** Jede Änderung an einem Ankreuzfeld, an der Uhrzeit oder das Löschen einer Zeile startet die Speicherung; ausgeführt wird sie 5 Sekunden nach der letzten Änderung, wobei stets der ganze Zeitplan ersetzt wird. Es gibt keinen Knopf «Speichern» und keine Sperre.

#### Karte «Kürzel»

1. **Erklärungstext:** wörtlich «Lange Bezeichnungen überall in der App kürzen — gilt für Status, Parteien, Fraktionen und Kommissionen: Suchtext eingeben (Vorschläge aus bestehenden Status-Werten sowie den aktuellen Fraktions- und Parteinamen) und gewünschtes Kürzel definieren. Beispiele:»
2. **Beispielliste:** drei Einträge, wörtlich:
    1. «Kommissionsname → Kürzel: ‹Kommission Bildung, Sport und Kultur› → ‹BSKK›»
    2. «Status-Text → Kurzform: ‹Bei der Kommission Bildung, Sport und Kultur pendent› → ‹Pendent: BSKK›»
    3. «Fraktions- oder Parteiname → Abkürzung: ‹Musterpartei› → ‹MPW›»
3. **Kürzelzeilen:** beliebig viele; Vorbelegung beim Öffnen: die gespeicherten Einträge in gespeicherter Reihenfolge. Je Zeile in dieser Reihenfolge:
    1. **Textfeld Suchtext:** Platzhalter wörtlich «Suchtext», mit Vorschlagsliste. Pflichtfeld: für das Speichern der Zeile ja.
        - Vorschlagswerte, ohne Doppelte; die drei Quellen laden gleichzeitig, und ein Wert steht dort, wo er zuerst ankommt: alle Statustexte, die in der Datenbank bei den Geschäften vorkommen, auch die von erledigten Geschäften, danach die Namen aller aktiven Fraktionen, danach die Parteinamen aller aktiven Mitglieder.
    2. **Textfeld Kürzel:** Platzhalter wörtlich «Kürzel». Pflichtfeld: für das Speichern der Zeile ja.
    3. **Löschknopf «×»:** Hinweistext wörtlich «Löschen»; entfernt die Zeile sofort und stösst das Speichern an.
4. **Knopf «+ Eintrag hinzufügen»:** Art: Knopf. Fügt eine leere Zeile an und setzt den Schreibcursor in deren Suchtextfeld.
5. **Statustext der Kürzel:** «Speichern...» ab der ersten Eingabe, danach «Gespeichert» (verschwindet nach rund 2,5 Sekunden) oder «Fehler beim Speichern» (bleibt stehen).
6. **Speicherung:** Jede Tasteneingabe, jedes Verlassen eines Feldes und jedes Löschen startet die Speicherung; ausgeführt wird sie 5 Sekunden nach der letzten Änderung. Gespeichert wird stets die vollständige Liste; Zeilen, bei denen Suchtext oder Kürzel fehlt, werden dabei verworfen.
7. **Wirkung:** Die Kürzel werden überall in der App auf Status-, Partei-, Fraktions- und Kommissionsnamen angewendet; längere Suchtexte werden zuerst ersetzt, damit kürzere Einträge längere nicht zerschneiden. Gespeichert und gefiltert wird immer mit dem Originalwert, gekürzt wird nur die Anzeige. Wirksam wird eine Änderung, sobald die Seite der App neu geladen wird.

#### Zuständige Kommission im Budget

Die Karte heisst «Budget: Departement → zuständige Kommission» und enthält:

1. **Erklärungstext:** wörtlich «Ordne jedem Departement seine zuständige Sachkommission zu. Der Budget-Filter «Zuständige Kommission» nutzt diese Zuordnung; ohne Zuordnung erscheint nur der Departement-Filter.»
2. **Zuordnungszeilen:** beliebig viele; Vorbelegung beim Öffnen: die gespeicherten Zuordnungen. Je Zeile in dieser Reihenfolge:
    1. **Textfeld Departement:** Platzhalter wörtlich «Departement», mit Vorschlägen aus den Departementen des neuesten Budgetjahres.
    2. **Textfeld Kommission:** Platzhalter wörtlich «Zuständige Kommission», mit Vorschlägen aus den Namen der aktiven Kommissionen.
    3. **Löschknopf «×»:** Hinweistext wörtlich «Löschen»; entfernt die Zeile sofort und stösst das Speichern an.
3. **Knopf «+ Zuordnung hinzufügen»:** fügt eine leere Zeile an und setzt den Schreibcursor in deren Feld «Departement».
4. **Statustext:** «Speichern...» ab der ersten Eingabe, danach «Gespeichert» oder «Fehler beim Speichern».
5. **Speicherung:** Jede Tasteneingabe, jedes Verlassen eines Feldes und jedes Löschen startet die Speicherung; ausgeführt wird sie 5 Sekunden nach der letzten Änderung. Gespeichert wird stets die vollständige Liste; Zeilen, bei denen Departement oder Kommission fehlt, werden dabei verworfen.
6. **Wirkung:** Der Filter «Zuständige Kommission» der Ansicht «Budget» (F76) nimmt diese Zuordnung.

#### Karte «E-Mail-Einladungen»

1. **Textfeld «Absender-E-Mail»:** Art: Textfeld für E-Mail-Adressen (der Browser prüft das Format). Platzhalter wörtlich «noreply@example.com». Pflichtfeld: nein. Vorbelegung: der gespeicherte Wert, Standard leer.
2. **Textfeld «Absendername»:** Art: Textfeld, ohne Platzhalter. Pflichtfeld: nein. Vorbelegung: der gespeicherte Wert; ist nichts gespeichert, der Standardwert «Parlament Winterthur Tool».
3. **Speicherung beider Felder:** automatisch: 0,5 Sekunden nach der letzten Tasteneingabe, sofort beim Verlassen des Feldes. Statusmeldungen erscheinen in der linken Spalte («Änderungen erkannt...», «Speichere automatisch...», «Alle Änderungen gespeichert», «Automatisches Speichern fehlgeschlagen»). Nie gesperrt.
4. **Verwendung:** Absenderadresse und Absendername werden für die Einladungsmails verwendet, die beim Anlegen neuer Benutzerkonten über «Ausgewählte abgleichen» verschickt werden.

#### Fusszeile des Verwaltungsbereichs

1. **Angabe «Parlament Winterthur Tool — gebaut am: ‹Zeitpunkt›»:** Art: Statustext, rechtsbündig unterhalb aller Karten. Quelle: der beim Erstellen der App festgehaltene Zeitpunkt. Bedingte Sichtbarkeit: nur wenn dieser Zeitpunkt vorliegt.
