# Funktionen

Das Parlament-Winterthur-Tool bündelt die ganze Fraktionsarbeit an einem Ort: alle öffentlichen Parlamentsdaten immer aktuell, dazu die privaten Notizen, Zuständigkeiten und Entscheide der Fraktion — nur für die Fraktion sichtbar.

Jede Funktion trägt eine feste Nummer (`**F1**`, `**F2**`, …). Die Nummern werden einmal vergeben und nie wiederverwendet; jeder Test in [TESTS.md](TESTS.md) nennt die Funktionsnummer, die er abdeckt.

## Immer aktuelle Parlamentsdaten

- **F1** Alle Geschäfte, Sitzungen, Traktanden, Mitglieder, Kommissionen und Fraktionen des Winterthurer Parlaments werden täglich automatisch übernommen — samt den Einreichern mit ihren Rollen (Erst- und Mitunterzeichner) und dem Weg, den jedes Geschäft durch das Parlament nimmt.
- **F2** Nichts geht verloren: Was auf der Parlamentswebseite verschwindet, bleibt im Tool erhalten (als historisch markiert) und kehrt bei Wiedererscheinen automatisch zurück. Eigene Geschäfte, interne Sitzungen und alle Fraktionsdaten bleiben von der Aktualisierung unberührt; als erledigt markierte Geschäfte werden nicht wieder «aufgemacht».
- **F3** Die Aktualisierung lässt sich jederzeit auch von Hand starten und zeigt ihren Fortschritt live an: aktueller Bereich, Anzahl verarbeiteter von gesamten Einträgen, verstrichene Zeit und geschätzte Restzeit. Sie kann abgebrochen werden. Bricht ein Lauf ab oder scheitert er, erscheint eine Fehler- bzw. Abbruch-Meldung mit dem Grund; der nächste Start setzt dann dort fort, wo der Lauf stehen geblieben ist. Es läuft immer nur eine Aktualisierung — wer während eines Laufs startet, sieht den laufenden Fortschritt, statt einen zweiten Lauf zu beginnen. Der Fortschritt wird nur dem Administrator in den Einstellungen angezeigt.

## Aufbau und Bedienung

- **F4** Bereiche in der Seitenleiste: Geschäfte, Sitzungen, Mitglieder, Kommissionen, Vorstösse, Sitzungstypen, Änderungsverlauf — mit Suche und den Filtern der jeweiligen Ansicht direkt darunter; unten die App-Version.
- **F5** Aussehen und Bedienung wie die übrigen Nextcloud-Apps (z.B. «Dateien»).
- **F6** Wer die App in dieser Nextcloud öffnen kann, sieht darin dieselben Daten — sowohl die öffentlichen Parlamentsdaten als auch die fraktionsinternen Notizen, Zuständigkeiten, Beschlüsse und Voten. Die App ist für die private Nextcloud der Fraktion gedacht; der Zugang lässt sich in den Nextcloud-Einstellungen auf die Fraktionsgruppe beschränken. Der gemeinsame Ordner, der Kalender und das Aufgabenboard sind ausschliesslich mit der konfigurierten Fraktionsgruppe geteilt. Einzelne Aktionen sind zusätzlich eingeschränkt (Notiz nur durch Verfasser, Votum nur durch Zuständige, Beschluss im Sitzungsmodus nur durch Protokollführung, Rollen und Datenaktualisierung nur durch Präsidium bzw. Administrator).
- **F7** Jede Eingabe speichert sofort — es gibt keine Speichern- oder Abbrechen-Knöpfe. Auswahlen speichern beim Wählen, Texte beim Verlassen des Feldes; eine kurze Bestätigung erscheint als gewohnte Nextcloud-Benachrichtigung. Dialoge schliessen mit ✕ — ohne Datenverlust, denn alles ist bereits gespeichert. Eingaben gehen nie verloren, auch nicht beim Klick auf die Formatierungsleiste.
- **F8** Neues wird immer gleich angelegt: Der Neu-Knopf öffnet **dieselbe Maske wie das Bearbeiten** — mit allen Feldern. Es gibt kein reduziertes Formular und keine später nachgereichten Felder. Anders als beim Bearbeiten wird beim Anlegen **noch nichts gespeichert**: Die Maske sammelt die Eingaben und zeigt unten **«Speichern»** und **«Abbrechen»**; «Speichern» ist gesperrt, solange die Pflichtangabe fehlt. Erst «Speichern» legt den Eintrag an, danach geht dieselbe Maske unmittelbar in die Bearbeitung über, sodass sich Dokumente und Notizen direkt anschliessen lassen.
    - **Ein Klick neben die Maske bewirkt beim Anlegen nichts** — er schliesst nicht und verwirft nichts, damit keine Eingabe ungewollt verloren geht. Verworfen wird ausschliesslich über «Abbrechen».
    - **Begründete Ausnahme:** Notizen, Dokumente und der Verlauf hängen an einem bereits bestehenden Eintrag und erscheinen deshalb erst nach dem Speichern; ein Hinweis in der Maske sagt das. Alles Übrige ist von Anfang an vorhanden.
    - Gibt es Vorlagen zur Auswahl (Sitzungstypen), öffnet der Neu-Knopf ein Menü mit je einem Eintrag pro Vorlage — dasselbe Bedienmuster wie «+ Neu» in der Dateien-Ansicht von Nextcloud.
- **F68** Keine Änderung ohne Spur: Jede Änderung wird nachvollziehbar festgehalten — bei Notizen über den Versionsverlauf, bei allem Übrigen über die Aktionszeitleiste des Geschäfts bzw. des Vorstosses.
- **F9** Bearbeitet wird durch Klick auf den Eintrag; jede Eingabe speichert sofort. Selbst angelegte Einträge (eigene Geschäfte, Vorstösse, Sitzungstypen) lassen sich über einen Löschen-Knopf wieder entfernen, mit Rückfrage vor dem Löschen. Geschäfte von der Parlamentswebseite sind davon ausgenommen: ihr Lebenszyklus läuft über den Status, weil sie bei der nächsten Aktualisierung ohnehin zurückkämen.
- **F10** Auswahllisten zeigen nur Aktive: aktive Fraktionen mit ihrer Abkürzung, aktive Mitglieder; Ansprechpartner nur aus der gewählten Fraktion.
- **F11** Lange Bezeichnungen erscheinen überall gekürzt, sobald ein Kürzel definiert ist (siehe Administration) — Status, Partei-, Fraktions- und Kommissionsnamen, in Listen, Karten und Auswahlfeldern gleichermassen; gespeichert bleibt immer der volle Name.
- **F12** Auf schmalen Bildschirmen werden Tabellen zu übersichtlichen Karten.
- **F13** Geschäfte sind auch über die zentrale Nextcloud-Suche auffindbar.

## Geschäfte

- **F14** Übersicht aller Geschäfte mit Nummer, Datum, Typ, Titel (mit Link auf die Parlamentswebseite), Einreichern, Priorität, Status (mit konfigurierbaren Kurzformen), Zuständigkeit und letztem Fraktionsbeschluss. Standardmässig nach Datum absteigend geordnet (neueste zuerst); ein Klick auf die Spaltenüberschrift Nummer, Titel oder Status sortiert danach aufsteigend, ein erneuter Klick auf dieselbe Spalte kehrt die Richtung um. Die Status-Spalte ist ausgeblendet, wenn genau ein Status gefiltert ist.
- **F15** Priorität, Zuständigkeit und Beschluss lassen sich direkt in der Zeile ändern — ohne das Geschäft zu öffnen.
- **F16** Filter: Entscheidungsbedarf, Status, Typ, Zuständigkeit, Beschluss und Priorität (jeweils mehrfach wählbar); erledigte Geschäfte sind standardmässig ausgeblendet und per Schalter einblendbar; Suche über Nummer und Titel; «Filter zurücksetzen». **Grundprinzip für jeden Filter (überall in der App):** ein Filter bietet genau die Werte zur Auswahl an, die in den Daten tatsächlich vorkommen — keine leeren Kategorien, keine nie zugewiesenen Personen; alles andere wäre unnützer Ballast und führte zu leeren Trefferlisten.
- **F17** Geschäfte mit hoher Priorität sind dezent hervorgehoben, solche mit tiefer abgeschwächt; nicht gesetzte Priorität zählt wie mittel und zeigt «—». Die farbliche Hervorhebung gilt in **allen Ansichten**, in denen ein Geschäft erscheint — in der Geschäfteliste (Tabelle und Karten), bei den Vorstössen und bei den Traktanden einer Sitzung.
- **F18** Eigene Geschäfte (ausserhalb des Parlamentsregisters) lassen sich anlegen. «+ Eigenes Geschäft» öffnet sofort die vollständige Geschäftsmaske — dieselbe wie bei jedem anderen Geschäft, mit allen Feldern, Notizen und Dokumenten. Wird sie ohne Titel wieder geschlossen, bleibt kein leerer Eintrag zurück.
- **F65** Bei einem selbst angelegten Geschäft lassen sich Titel, Typ, Status und Datum direkt in der Geschäftsmaske ändern; jede Eingabe wird sofort gespeichert. Bei Geschäften von der Parlamentswebseite sind diese Angaben nur lesbar, weil sie bei der nächsten Aktualisierung aus der Quelle überschrieben würden. Ein unvollständiges Datum wird mit einem Hinweis abgelehnt statt still übernommen.
- **F66** Ein selbst angelegtes Geschäft lässt sich wieder löschen. Geschäfte von der Parlamentswebseite lassen sich nicht löschen — sie kämen bei der nächsten Aktualisierung ohnehin zurück.
- **F70** Ein selbst angelegtes Geschäft lässt sich mit dem offiziellen Parlamentsgeschäft verknüpfen, sobald dieses nach dem Abgleich erscheint — genau wie ein Vorstoss mit einem Geschäft verknüpft wird. In der Maske eines eigenen Geschäfts steht dafür in der fraktionsinternen Bearbeitung die Zeile «Offizielles Geschäft» mit dem Knopf «Mit offiziellem Geschäft verknüpfen»; er öffnet einen Auswahldialog mit Suchfeld (Platzhalter «Nr. oder Titel») und einer Liste, die die Geschäfte nach Titel-Ähnlichkeit zum eigenen Geschäft vorschlägt (ähnlichste zuerst, bei Gleichstand die neuesten). Angeboten werden nur offizielle Geschäfte&nbsp;— erledigte eingeschlossen, denn das gesuchte offizielle Geschäft kann bereits erledigt sein; eigene Geschäfte und das Geschäft selbst sind ausgeschlossen; gibt es keine, erscheint «Keine Geschäfte gefunden». Nach der Wahl:
    - Das eigene Geschäft wird als «erledigt» abgeschlossen und ist mit dem offiziellen Geschäft verknüpft. An Stelle des Knopfes steht der Hinweis «Mit dem offiziellen Geschäft ‹Nummer› «‹Titel›» verknüpft und abgeschlossen», wobei Nummer und Titel ein **anklickbarer Verweis** sind, der das offizielle Geschäft öffnet. (Ist das Ziel ausnahmsweise nicht mehr auffindbar, steht nur «Mit einem offiziellen Geschäft verknüpft und abgeschlossen».)
    - **Notizen und alle weiteren Angaben wandern ins offizielle Geschäft, sofern dort noch nicht gesetzt**: die Notizen (reguläre und Sitzungsnotizen, samt Versionsverlauf) werden ans offizielle Geschäft übertragen und erscheinen dort in dessen Notizliste; Priorität, Typ, Kommission, Datum, Inhalt und die Zuständigkeit werden nur dann übernommen, wenn das offizielle Geschäft im jeweiligen Feld noch leer ist — bereits gesetzte Angaben des offiziellen Geschäfts bleiben unverändert.
    - Die beiden Geschäfte sind danach **gegenseitig verlinkt und beidseitig anklickbar** («hin und her»): Am offiziellen Geschäft erscheint der Bereich «Verknüpfte eigene Geschäfte» (nur wenn mindestens ein eigenes Geschäft darauf verweist) mit je Eintrag dem anklickbaren Titel (öffnet das eigene Geschäft) und, falls gesetzt, dessen Priorität. Vom eigenen Geschäft führt der oben genannte Verweis zurück zum offiziellen. Bei einem Geschäft von der Parlamentswebseite gibt es den Knopf «Mit offiziellem Geschäft verknüpfen» nicht (nur eigene Geschäfte lassen sich verknüpfen). Fehler beim Verknüpfen: «Verknüpfung fehlgeschlagen: …».

- **F71** In der Geschäfteliste lässt sich zusätzlich nach **Einreicher** filtern — je ein Filter für die **Person** und für die **Partei**, beide als Mehrfachauswahl mit Platzhalter «Alle», im Filterbereich nach dem Prioritäts-Filter.
    - **Einreicher (Person):** Auswahl aller Personen, die bei irgendeinem Geschäft als Einreicher (Erst- oder Mitunterzeichner) vorkommen, alphabetisch sortiert. Ist mindestens eine Person gewählt, erscheinen nur Geschäfte, bei denen eine der gewählten Personen Einreicher ist.
    - **Schalter «Nur Ersteinreicher»** (direkt beim Person-Filter, standardmässig aus): eingeschaltet zählt nur der Erstunterzeichner (der erste Einreicher eines Geschäfts) — es erscheinen dann nur Geschäfte, deren Erstunterzeichner eine der gewählten Personen ist; blosse Mitunterzeichnerschaft genügt nicht mehr. Der Schalter wirkt ausschliesslich auf den Person-Filter.
    - **Partei:** Auswahl aller Parteien, die als Einreicher tatsächlich vorkommen, alphabetisch sortiert. Die Partei eines Einreichers wird über die Parlamentsmitglieder aufgelöst (Personen-ID der Webseite, sonst über den Namen). Ist mindestens eine Partei gewählt, erscheinen nur Geschäfte, bei denen ein Einreicher zu einer der gewählten Parteien gehört.
    - «Filter zurücksetzen» leert auch diese beiden Filter und schaltet «Nur Ersteinreicher» wieder aus.

- **F72** An jeder Stelle mit Dokumenten (eigenes/offizielles Geschäft, Vorstoss, Sitzung) steht dieselbe Dokument-Komponente. Neben «+ Neues Dokument» und «Hochladen» gibt es den Knopf **«Verknüpfen»**: er öffnet den Nextcloud-Standard-Dateiauswahldialog (startet im Jahres-Ordner des Objekts, frei navigierbar) und nimmt eine **bestehende** Datei — unabhängig von ihrem Namen und Ablageort — in die Dokumentliste auf. Verknüpfte Dokumente sind als solche gekennzeichnet und lassen sich mit «✕» wieder lösen (die Datei selbst bleibt bestehen). Die Dokumente werden jahr-basiert abgelegt (…/{Jahr}/…), nicht mehr nach interner Versionsnummer.

- **F73** Eine Sitzung lässt sich nicht nur mit Geschäften, sondern auch mit **Vorstössen** verknüpfen (eigene wie fremde) — so werden sie an der Sitzung traktandiert. Im aufgeklappten Sitzungsdetail steht dafür direkt unter «Verknüpfte Geschäfte» der Bereich «Verknüpfte Vorstösse» mit der Auswahlliste «Vorstoss verknüpfen…»; bereits verknüpfte Vorstösse werden ausgefiltert und lassen sich je mit «✕» wieder lösen. Jeder Vorstoss erscheint mit Titel und Art.
- **F19** Ein Klick öffnet das Geschäft mit allen öffentlichen Informationen und der fraktionsinternen Bearbeitung:
    - **F20** **Priorität** (hoch/mittel/tief, abwählbar).
    - **F21** **Zuständigkeit:** mehrere Personen, die erste ist die Hauptzuständige; zur Auswahl stehen aktive Mitglieder zuerst, ehemalige gekennzeichnet darunter. Wer in der zuständigen Kommission sitzt, wird nach der Synchronisation automatisch eingetragen (ohne bestehende Zuweisungen zu überschreiben); haben Fraktionsmitglieder das Geschäft selbst eingereicht, werden sie in Einreicher-Reihenfolge zuständig. **Die zuständige Kommission steht im Status des Parlaments oder im Feld «Kommission»** — dieselbe Regel wie in der Lasche «Kommissionen» (F46), damit hier und dort dasselbe Geschäft zur selben Kommission gehört.
    - **F22** **Fraktionsbeschluss:** Auswahl aus genau den zum Geschäftstyp passenden Beschlüssen. Die Auswahl je Typ:
        - **Motion:** Zustimmen, Ablehnen, Stimmfreigabe, Miteinreichen als Fraktion, Miteinreichen einzelne Personen, Überweisung befürworten, Überweisung ablehnen, Erheblich erklären, Abschreiben.
        - **Postulat:** Zustimmen, Ablehnen, Stimmfreigabe, Miteinreichen als Fraktion, Miteinreichen einzelne Personen, Überweisung befürworten, Überweisung ablehnen, Kenntnisnahme positiv, Kenntnisnahme negativ, Nachbericht verlangen, Abschreiben.
        - **Interpellation, Schriftliche Anfrage, Bericht:** Zustimmen, Ablehnen, Stimmfreigabe, Kenntnisnahme positiv, Kenntnisnahme negativ.
        - **Initiative:** Zustimmen, Ablehnen, Stimmfreigabe, Überweisung befürworten, Überweisung ablehnen.
        - **Wahlen, Vorlagen und jeder sonstige/unbekannte Typ:** Zustimmen, Ablehnen, Stimmfreigabe.
        - Ist das Geschäft als erledigt markiert, kommen bei jedem Typ zusätzlich Kenntnisnahme positiv und Kenntnisnahme negativ dazu. Alternativ jederzeit ein freier Beschlusstext ohne vorgegebene Auswahl. Ein Beschluss lässt sich zurücknehmen (er bleibt als «Beschluss zurückgenommen» in der Zeitleiste); einen Beschlusstext weiterbearbeiten kann nur, wer ihn erfasst hat.
    - **F23** **Entscheidungsbedarf:** Jedes Geschäft hat aus Fraktionssicht einen von drei Zuständen: «offen» (noch kein Fraktionsbeschluss — Entscheid nötig), «neu zu entscheiden» (das Parlament hat das Geschäft nach dem letzten Fraktionsbeschluss verändert — Entscheid nötig) oder «entschieden» (es liegt ein Beschluss vor und seither keine externe Änderung). «Offen» und «neu zu entscheiden» gelten als Entscheidungsbedarf und sind über den Filter «Entscheid nötig» gezielt anzeigbar.
    - **F24** **Notizen** mit Formatierung (fett, kursiv, unterstrichen, durchgestrichen, Überschriften, Listen, Zitat, Code, Links, Rückgängig und Wiederholen). Jede frühere Fassung bleibt erhalten: durch die Versionen blättern und eine ältere Fassung als neue aktuelle übernehmen — der Verlauf bleibt vollständig. Bearbeiten, Löschen und Wiederherstellen einer Notiz kann nur ihr Verfasser; für andere ist sie sichtbar, aber nicht änderbar. Gespeichert wird nur bewusst über das Häkchen «✓» (kein automatisches Zwischenspeichern, kein Speichern beim Verlassen des Feldes); bei ungespeicherten Änderungen wird beim Verlassen gewarnt. Gelöschte Notizen verschwinden nicht endgültig: in der Aktionszeitleiste erscheint «… hat seine Notiz gelöscht», und der Verfasser kann sie samt Verlauf wiederherstellen.
    - **F25** **Sitzungsnotizen:** Notizen, die man in einer Sitzung zu einem mit dieser Sitzung verknüpften Geschäft erfasst, haften am Geschäft (nicht an der einzelnen Sitzung). Sie erscheinen darum automatisch bei jeder aktuellen und künftigen Sitzung, an der dasselbe Geschäft hängt — auch wenn das Geschäft in der ursprünglich geplanten Sitzung nicht behandelt und auf eine spätere verschoben wird. Im Geschäft selbst stehen die Sitzungsnotizen separat unter den normalen Notizen, aufklappbar und standardmässig eingeklappt; sie verhalten sich sonst wie normale Notizen (Formatierung, Versionsverlauf, Löschen mit Wiederherstellung).
    - **F26** **Votum im Rat:** eigener formatierter Text pro Geschäft (nur die zuständige Person darf ihn erfassen und bearbeiten; für alle anderen erscheint das Feld nur, wenn ein Votum vorliegt), als druckbares PDF speicherbar. Das PDF öffnet in einem eigenen Tab, löst den Drucken-Dialog automatisch aus und trägt: Titel «Votum im Rat» und den Geschäftstitel; darunter Geschäftsnummer, Zuständige der Fraktion (die Hauptzuständige mit dem Zusatz «Hauptverantwortung»), den letzten Fraktionsbeschluss mit Datum, «Erfasst von» und den Stand (Datum/Zeit); dann den Abschnitt «Wortlaut» mit dem formatierten Votumstext (oder «— Noch kein Votum erfasst —», wenn leer) und eine Fusszeile mit Geschäftsnummer und Ausdruckdatum. **Das PDF zeigt die Formatierung so, wie sie im Editor erfasst wurde** — Fettschrift, Kursives, Absätze, Überschriften, Listen und Zitate; der Editor speichert Markdown, und das PDF wandelt es beim Drucken. Ein Votum lässt sich archivieren; es bleibt dann als historischer Eintrag in der Zeitleiste und ein neues Votum kann begonnen werden.
    - **F27** **Dokumente:** aus Vorlagen (Word, Excel, PowerPoint, OpenDocument, Markdown, Text) erstellen, hochladen oder aus dem Fraktionsordner wählen; der Dateiname ist mit dem Titel vorbelegt (Leerzeichen werden zu Unterstrichen, der Cursor steht am Ende für eine Ergänzung wie «-rede»).
    - **F28** **Aktionszeitleiste:** alle Notizen, Beschlüsse («Von: X → Nach: Y»), Voten und Zuweisungen mit Person, Datum und Uhrzeit; per Griff (⠿) umsortierbar.
    - **F29** **Verknüpfte Vorstösse** werden mit Haltung, Zuständigkeit und Notizen angezeigt.

## Vorstösse

- **F30** Eigene und fremde politische Vorstösse als klickbare Karten mit Herkunft, Art, Status, Zuständigkeit und — bei fremden — Herkunftsfraktion und eigener Haltung; Filter nach Herkunft und Status, beide standardmässig auf «Alle» und je nur mit den Werten, die bei den geladenen Vorstössen tatsächlich vorkommen (Grundprinzip; feste Reihenfolge Eigene/Fremde bzw. Neu/Entwurf/Bereit/ Eingereicht/Erledigt/Pausiert); Suche über Titel, Art und Zuständigkeit; Prioritäts-Hervorhebung wie bei den Geschäften. Ohne Treffer erscheint «Keine Vorstösse vorhanden».
- **F31** Beim Erfassen ist man selbst als zuständige Person vorausgewählt; Herkunft ist auf «Eigene», der Status auf «Neu» vorbelegt. «+ Neuer Vorstoss» öffnet sofort dieselbe vollständige Maske wie das Bearbeiten — mit allen Feldern, Notizen, Dokumenten und Verlauf. Wird sie ohne Titel wieder geschlossen, bleibt kein leerer Eintrag zurück.
- **F67** Ein Vorstoss lässt sich über seine Karte löschen; vorher wird zur Bestätigung gefragt.
- **F119** **Die Übersicht der Vorstösse ist gebaut wie die der Geschäfte.** Wer sie öffnet, will sehen, was ansteht, und das Richtige herausgreifen — dafür braucht er dieselbe Übersicht wie bei den Geschäften, nicht eine zweite Art von Liste. Auf breiten Fenstern steht darum eine Tabelle mit sechs Spalten: **Art** (darunter Herkunft und Datum), **Titel** (darunter die Herkunftsfraktion, wenn der Vorstoss von einer fremden stammt), **Prio**, **Status**, **Zuständig** und **Beschluss**. Priorität, Zuständigkeit und Beschluss lassen sich direkt in der Zeile ändern und speichern sofort; ein Klick in den freien Bereich der Zeile öffnet den Vorstoss mit allen Details. Wird das Fenster schmäler als die Spalten brauchen, wechselt die Ansicht auf die Karten — derselbe Umschaltpunkt wie bei den Geschäften.
- **F32** In der Bearbeitung: Art aus den bekannten Vorstoss-Arten (Motion, Postulat, Interpellation, Schriftliche Anfrage, Dringliche Motion, Dringliches Postulat, Budgetmotion, Fragestunde, Einzelinitiative, Parlamentarische Initiative) — als Vorschlag frei mit eigenem Text überschreibbar; Herkunft (Eigene/Fremde), Status (Neu, Entwurf, Bereit, Eingereicht, Erledigt, Pausiert), Priorität, Zuständigkeit (Personen-Liste), Inhalt im Formatierungs-Editor und Dokumente. Die Notizen verhalten sich exakt wie beim Geschäft — dieselbe Notizliste mit Formatierung, Versionsverlauf (durch die Fassungen blättern, eine ältere übernehmen), bewusstem Speichern über «✓» und Löschen mit Wiederherstellung durch den Verfasser. Bei fremden Vorstössen zusätzlich die eigene Haltung (Miteinreichen, Unterstützen, Stimmfreigabe, Ablehnen, Ablehnungsantrag stellen — ebenfalls frei überschreibbar), die Herkunftsfraktion und deren Ansprechpartner.
- **F33** Ein Vorstoss lässt sich durch Verknüpfung mit einem Geschäft abschliessen: Ein Dialog listet die Geschäfte, oben zuerst jene, die die meisten Wörter (ab drei Buchstaben) mit dem Vorstoss-Titel gemeinsam haben; bei gleich vielen gemeinsamen Wörtern steht das neuere Geschäft zuerst. Ein Suchfeld grenzt die Liste über Titel und Nummer ein; ohne Treffer erscheint «Keine Geschäfte gefunden.» Nach der Wahl übernimmt das Geschäft die Priorität des Vorstosses und der Vorstoss gilt als erledigt. Existiert das gewählte Geschäft nicht mehr, bleibt die Verknüpfung trotzdem bestehen; nur die Prioritäts-Übernahme entfällt dann stillschweigend.
- **F34** Dokumente im Fraktionsordner «40_Vorstösse» werden automatisch als Vorstösse übernommen — ohne Duplikate.
- **F35** In der Bearbeitung gibt es — wie beim Geschäft — eine Aktionszeitleiste, die die zeitlich protokollierten Aktionen mit Person, Datum und Uhrzeit anzeigt und per Griff (⠿) umsortierbar ist.

## Sitzungen

- **F36** Alle Sitzungen chronologisch mit Datum, Zeit, Titel, Ort und externem Link; kommende zuerst (aufsteigend), vergangene danach (absteigend) und abgeschwächt dargestellt, interne Fraktionssitzungen gekennzeichnet. Der Schalter «Nur zukünftige» ist standardmässig aktiv (es sind also zunächst nur kommende Sitzungen sichtbar); dazu eine Suche. Ohne Treffer erscheint «Keine Sitzungen gefunden».
- **F37** Neue interne Sitzungen entstehen aus einer Vorlage (Sitzungstyp): «+ Neue Sitzung» steht links im Kopf und öffnet ein Menü mit je einem Eintrag pro Sitzungstyp — dasselbe Bedienmuster wie «+ Neu» in der Dateien-Ansicht von Nextcloud. Ein Klick auf einen Eintrag öffnet unmittelbar das Formular für genau diesen Typ. Ist kein Sitzungstyp definiert, steht im Menü ein nicht anwählbarer Hinweis, zuerst unter «Sitzungstypen» einen Typ anzulegen. Im Formular: Titel, Datum (eine Woche voraus vorbelegt), Zeit und Ort aus der Vorlage, Zweck, Traktanden (umsortierbar) und Teilnehmer — von einzelnen Mitgliedern über ganze Fraktionen oder Kommissionen bis zu Nextcloud-Gruppen, -Benutzern und Fraktions-Rollen.
- **F38** In jeder Sitzung: Notizen, Dokumente (im Jahresordner, nach Datum benannt), verknüpfte Geschäfte (hinzufügen und lösen), Aufgaben direkt aufs Fraktions-Aufgabenboard, und die Traktanden mit Dokument-Links, Bemerkungen und eigenen Notizen; Traktanden mit Geschäftsbezug öffnen das Geschäft. Die Notizen zur Sitzung nutzen **dieselbe Notiz-Komponente und dasselbe Aussehen** wie überall (aufklappbare Liste, «+ Neue Notiz», Versionsverlauf, Löschen mit Wiederherstellen über die Aktionszeitleiste); es gibt kein davon abweichendes, eigenes Notizfeld.
- **F39** Bei einem Traktandum mit Geschäftsbezug wird die Notiz nicht an der Sitzung, sondern als Sitzungsnotiz am Geschäft gespeichert. Sie erscheint dadurch bei jeder aktuellen und künftigen Sitzung, an der dieses Geschäft hängt (und im Geschäft selbst, siehe Abschnitt Geschäfte). Traktanden ohne Geschäftsbezug haben ihre eigene Traktandum-Notiz — mit **derselben Notiz-Komponente und demselben Aussehen** wie überall (aufklappbare Liste, «+ Neue Notiz», Versionen, Löschen mit Wiederherstellen); die Notiz haftet dann am Traktandum. Es gibt kein davon abweichendes, eigenes Notizfeld.
- **F40** Sitzungen lassen sich verknüpfen (z.B. Kommissions- mit der vorbereitenden Fraktionssitzung): Die Notizen der verknüpften Sitzungen erscheinen gesammelt; Entkoppeln lässt alle Notizen an ihrem Platz.
- **F115** **Jedes Protokoll ist mit einem Klick erreichbar** — in einem neuen Fenster, an beiden Stellen, an denen man es sucht. Oben auf der Karte jeder Parlamentssitzung steht der Link «Protokoll» und führt zum Protokoll genau dieser Sitzung; sein Hinweistext ist der Titel, den die Parlamentswebseite dem Protokoll gibt. Der Klick klappt die Karte nicht auf oder zu. Solange das Parlament kein Protokoll dieser Sitzung veröffentlicht hat, fehlt der Link. In der Folgesitzung steht derselbe Link ein zweites Mal beim Traktandum, das das Protokoll abnimmt («Abnahme Parlaments-Protokolle»): hinter dem Titel des Traktandums, beschriftet mit «Protokoll» und dem Datum der protokollierten Sitzung («Protokoll 10.11.2025»), mit dem Titel des Protokolls als Hinweistext. Er führt auf das abgenommene Protokoll der protokollierten Sitzung; solange nur der Entwurf vorliegt, den das Parlament zur Abnahme stellt, führt er auf diesen. Trägt ein Traktandum kein Protokoll, steht dort kein solcher Link.
- **F41** Jede Sitzung erscheint automatisch im gemeinsamen Fraktionskalender, mit Teilnehmern und optionaler E-Mail-Einladung; interne Sitzungen mit Zweck und Traktandenliste im Kalendereintrag; Protokoll-Link pro Sitzung.

## Sitzungstypen (Vorlagen)

- **F42** Vorlagen für wiederkehrende Sitzungen: Name, Zweck, Standard-Ort und -Zeit, Vorlage-Traktanden und Teilnehmer; Bearbeiten per Klick auf die Karte, Löschen mit Rückfrage.
- **F43** Ein Sitzungstyp kann «beratene» Kommissionen festlegen: Deren hängige Geschäfte werden mit künftigen Sitzungen dieses Typs automatisch verknüpft, sodass vor der Sitzung alles bereitsteht.

## Mitglieder

- **F44** Alle Parlamentarier mit Partei, Fraktion, Fraktionsrolle, Kommissionen (mit Funktion) und E-Mail; ehemalige gekennzeichnet.
- **F45** Sortierbar nach Funktion (Standard), Fraktion, Partei oder Name; filterbar nach Fraktion, Partei, Kommission und Funktion (Grundzustand «Alle Funktionen»); Suche über Name, Partei und E-Mail; der Schalter «Nur Aktive» ist standardmässig aktiv. Bei Standard-Sortierung stehen die Mitglieder nach Fraktionsrolle geordnet (Präsidium zuerst, dann Stellvertretung, dann die übrigen), innerhalb gleicher Rolle nach Partei und Name. Ohne Treffer erscheint «Keine Mitglieder gefunden».

## Kommissionen

- **F46** Alle Kommissionen mit Status, Beschreibung, ihren pendenten Geschäften (Klick öffnet das Geschäft) und ihren Mitgliedern (Funktion, Partei, Fraktion, E-Mail; die eigene Fraktion hervorgehoben). **Zu einer Kommission gehört ein Geschäft aus zwei Gründen:** Sein Status nennt sie («Bei Kommission&nbsp;… pendent», so schreibt es das Parlament), oder es ist ihr im Feld «Kommission» zugewiesen — so ordnet die Fraktion ihre eigenen Geschäfte zu, deren Status keine Kommission nennt.
- **F47** Zwei Schalter, beide standardmässig aktiv: «Nur aktive Kommissionen» und «Nur aktive Mitglieder» (ist letzterer aus, zeigt der Mitglieder-Titel zusätzlich die Zahl der aktiven Mitglieder an); dazu eine Suche. Ohne Treffer erscheint «Keine Kommissionen gefunden».
- **F118** **Die pendenten Geschäfte einer Kommission stehen offen da, die Mitglieder eingeklappt.** Wer die Lasche «Kommissionen» öffnet, will wissen, was bei welcher Kommission liegt: Beschreibung, pendente Geschäfte und die Hinweise der Karte brauchen kein Aufklappen. Die Mitglieder füllen die Karte und stehen darum hinter einem Schalter, der ihre Zahl auch zugeklappt nennt («Mitglieder (10):») und sie auf Klick zeigt; ein zweiter Klick verbirgt sie wieder. Trifft die Suche ein Mitglied, klappt seine Kommission von selbst auf — trifft sie ein Geschäft, bleibt es bei den Mitgliedern zugeklappt, weil das Geschäft ohnehin zu sehen ist.

## Gemeinsamer Fraktionsraum

- **F48** Ein geteilter Ordner «Fraktion» mit fester Nummern-Struktur erscheint automatisch bei allen Mitgliedern — auch bei später dazukommenden. Er enthält genau diese Ordner:
    - **00_Allgemein**
    - **10_Sitzungen** mit Jahres-Unterordner (aktuell **2026**)
    - **20_Geschäfte**
    - **30_Kommissionen** mit den Unterordnern **Aufsichtskommission** und **Sachkommission Bildung Sport Kultur**
    - **40_Vorstösse** mit den Unterordnern **10_Eigene** und **20_Fremde**
    - **50_Wahlkampf**
    - **60_Medien**
    - **90_Archiv** Jedes Mitglied darf in diesen Ordnern lesen, anlegen, ändern und löschen. Umbenennungen der Struktur (etwa als Wahlkampf von 40 auf 50 und Medien von 50 auf 60 rückte) übernehmen alle Inhalte verlustfrei; ein bereits selbst angelegter Fraktionsordner eines Mitglieds wird als Sicherung «Fraktion.bak» behalten und sein Inhalt in den offiziellen Ordner überführt, ohne dass eine Datei verloren geht oder überschrieben wird (gleichnamige Dateien landen als «…​.migrated» daneben).
- **F49** Ein gemeinsamer Fraktionskalender, bei allen sichtbar und bearbeitbar.
- **F50** Ein gemeinsames Aufgaben-Board «Fraktion» mit den Spalten «To-do», «In Arbeit» und «Erledigt».
- **F51** Neue Fraktionsmitglieder werden automatisch als Nextcloud-Benutzer geführt und per E-Mail eingeladen; niemand wird ohne Zustimmung des Administrators entfernt.

## Office-Integration

- **F52** Dokumente, Tabellen und Präsentationen lassen sich direkt im Browser öffnen und gemeinsam bearbeiten — aus jedem Dokumente-Bereich des Tools heraus.

## Zusammenarbeit in Echtzeit

- **F53** Änderungen anderer Fraktionsmitglieder erscheinen sofort überall — ohne die Seite neu zu laden und ohne die eigene Ansicht zu stören: kein Springen der Liste, offene Fenster und angefangene Eingaben bleiben unangetastet.
- **F54** Das gilt für Geschäfte, Beschlüsse, Notizen, Sitzungen, Traktanden, Vorstösse, Rollen und den Fortschritt der Datenaktualisierung.
- **F55** Nur angemeldete Fraktionsmitglieder empfangen diese Aktualisierungen.
- **F105** **Robustes Nachladen bei schlechter Leitung:** Kann etwas nicht geladen werden (Netzwerkfehler, Zeitüberschreitung, überlasteter Server), wird es automatisch immer wieder versucht, bis es klappt — nichts bleibt dauerhaft «hängen». Die Wartezeit zwischen den Versuchen wächst schrittweise (gedeckelt), damit der Server nicht überlastet wird. Ladevorgänge (Anzeigen) werden unbegrenzt wiederholt; Speicher- und Löschaktionen nur wenige Male und nur bei echten Netzwerkfehlern, damit keine Aktion doppelt ausgeführt wird. Fehler auf der eigenen Seite (etwa fehlende Berechtigung) werden nicht wiederholt. Ladeanzeigen erscheinen sofort, damit auch bei schlechter Leitung sichtbar ist, dass etwas geschieht.

## Rollen und Fraktionssitzungsmodus

- **F56** Rollen in der Fraktion: Präsidium, Protokollführung, Kommissionsmitglieder — jeweils mit zeitlich befristeten Stellvertretungen, die parallel zum Inhaber handeln können. Wer eine Rolle vergeben darf:
    - **Fraktionspräsidium** setzt nur der Gruppen-Administrator der Fraktionsgruppe (bzw. ein Nextcloud-Administrator).
    - **Protokollführung, Präsidiums-Stellvertretung und Kommissionsmitglieder** setzt das Präsidium (oder eine aktive Präsidiums-Stellvertretung).
    - Eine **Protokoll-Stellvertretung** darf zusätzlich die Protokollführung selbst ergänzen.
    - Zu jeder Stellvertretung gehört ein Gültig-von/-bis; «bis» muss nach «von» liegen, sonst wird die Eingabe abgewiesen.
- **F57** Während der Fraktionssitzung lässt sich der Sitzungsmodus einschalten (durch Präsidium oder dessen aktive Stellvertretung): Beschlüsse erfasst dann nur die Protokollführung (oder ihre aktive Stellvertretung) — versucht es jemand anderes, wird die Eingabe mit einem Hinweis abgewiesen; Notizen und Voten bleiben nach den üblichen Regeln offen, und die Geschäftsliste zeigt automatisch nur die Geschäfte, die einen Entscheid brauchen. Ausserhalb des Sitzungsmodus darf jedes Mitglied Beschlüsse erfassen.

## Administration

- **F58** Fraktion und zugehörige Nextcloud-Gruppe wählen; wählbar sind nur synchronisierte, aktive Fraktionen.
- **F59** Datenaktualisierung von Hand starten (mit laufendem Fortschritt) oder abbrechen.
- **F60** Zeitplan der automatischen Aktualisierung: beliebige Einträge mit Wochentagen (Mo–So zum Abhaken) und Uhrzeit, mit Hinzufügen und Löschen; automatisch gespeichert. Verpasste Zeitpunkte (z.B. nach einem Unterbruch) werden nachgeholt. Ist kein Eintrag gesetzt, gilt der Standard und wird vorbelegt angezeigt: zwei Einträge an allen Wochentagen (Mo–So), um 10:00 und um 18:00 Uhr; sie lassen sich wie eigene Einträge bearbeiten oder löschen.
- **F61** Fraktionsmitglieder den Nextcloud-Benutzern zuordnen, fehlende Benutzer anlegen (mit E-Mail-Einladung); verwaiste Benutzer (nicht mehr im Parlament) sind markiert und lassen sich gezielt deaktivieren.
- **F62** Rollen und befristete Stellvertretungen verwalten.
- **F63** Kürzel definieren (z.B. «Beim Stadtrat pendent» → «Pendent: Stadtrat» oder «Sozialdemokratische Partei» → «SP») — sie gelten überall für Status, Partei-, Fraktions- und Kommissionsnamen. Das Suchtext-Feld schlägt die bestehenden Status-Werte sowie die aktuellen Fraktions- und Parteinamen vor; automatisch gespeichert.
- **F69** Typen für eigene Geschäfte pflegen: eine beliebig lange Liste von Bezeichnungen, mit Hinzufügen und Löschen, automatisch gespeichert. Diese Typen stehen beim Anlegen eines eigenen Geschäfts zur Auswahl. Ohne Eintrag bleibt es beim Typ «Eigenes Geschäft».

## Änderungsverlauf

- **F64** Ein eigener Bereich zeigt, was in welcher Version dazugekommen ist — als aufklappbare Liste, die Neuerungen der aktuellen Version bereits geöffnet.
- **F106** Ein eigener Bereich **«Bedienungsanleitung»** (in der Navigation direkt vor «Änderungsverlauf») zeigt das README des Projekts als **formatiertes Markdown** — damit die Anleitung direkt im Tool auffindbar ist, ohne ins Repository zu wechseln.
- **F107** **Direkter Verweis auf jeden Bereich:** Jeder Bereich ist über den **Hash der Adresse** direkt aufrufbar und teilbar (z.B. `…/apps/parlwin/#budget`, `…/#anleitung`). Der Hash folgt der Navigation, ein direkt aufgerufener Hash öffnet den passenden Bereich, ein unbekannter Hash bleibt beim Standardbereich, und Vor und Zurück im Browser wechseln den Bereich. (Grundlage u.a. dafür, einzelne Seiten für die Prüfung der Gestaltung gezielt aufzurufen.)
- **F108** Ein eigener Bereich **«Protokoll»** (in der Navigation vor «Bedienungsanleitung») zeigt den **Verlauf der Synchronisationen und der eingelesenen Budgets** — neueste zuerst. Jeder Eintrag trägt **Zeitpunkt**, **Art** (Synchronisation, Budget-Import, Budget neu eingelesen, Novemberbrief, Sitzungsanträge, Fehler), eine **Erfolg/Fehler-Markierung**, einen **Titel** und eine **Meldung** (bei Synchronisationen «X neu, Y geändert»; bei Budget-Importen die Zahl der Produktegruppen und Investitionen; bei Sitzungsanträgen «N neu von M gefundenen»), sowie den **Auslöser** (die Benutzer-ID beim Auslösen von Hand, «auto» beim automatischen Lauf im Hintergrund). Protokolliert werden die automatischen Läufe (Hintergrundauftrag) ebenso wie die von Hand ausgelösten. **Fehler-Ereignisse sind hervorgehoben** und sind der Ort, an dem festgehalten wird, **was sich nicht lesen liess** — etwa ein Budgetbuch, aus dem keine Produktegruppen gelesen werden konnten, samt der geladenen Adresse. Das Protokoll behält die Ereignisse der letzten 180 Tage.

## Budget

Der Budget-Bereich unterstützt die Fraktion durch den städtischen Budgetprozess: er erleichtert die Vorarbeit in der Fraktion, hilft bei der Koordination mit anderen Parteien, erfasst eigene und fremde Anträge geordnet nach Produktegruppen, unterstützt globale Kürzungsanträge mit automatischer Verteilung und erlaubt es, die Beschlüsse während der Budgetsitzung laufend mitzuführen.

Grundlage sind die städtischen Budgetbücher: Teil B (Produktegruppen-Globalbudgets) gliedert das Budget nach **Departement → Produktegruppe → Produkt**; die Produktegruppe trägt das beschlussfähige Globalbudget (Nettokosten), das Produkt ist reine Information. Teil A enthält die Investitionsrechnung, den Steuerfuss und die Gesamt-Erfolgsrechnung.

### Seite, Tabs und Filter

- **F74** Ein neuer Bereich «Budget» in der Seitenleiste öffnet eine eigene Budget-Seite. Sie besteht aus den Tabs Globalbudgets, Personalbestand, Investitionsrechnung, Steuerfuss, Anträge und Grafik; die Filter und die Summenzeile gelten für alle Tabs gemeinsam.
    - Die **Tab-Leiste bleibt beim Scrollen oben stehen**, sodass man zum Wechseln nicht nach oben scrollen muss.
    - Die **Scrollposition wird je Tab gemerkt** und beim Zurückwechseln wiederhergestellt; ein noch nicht besuchter Tab startet oben. Das Merken ist flüchtig (nur solange die Seite offen ist, weder Cookie noch Speicher).
- **F75** Ein Filter wählt das **Budgetjahr** aus einem Auswahlmenü der tatsächlich vorhandenen Jahre (nur Jahre, für die Daten in der Datenbank liegen); vorbelegt ist das neueste Jahr. Für vergangene Jahre (aktuell alle vor 2027) wird nichts mehr neu erzeugt; nach dem 1. Dezember des Vorjahres werden nicht bereits erzeugte Budgetjahre ignoriert und keine Daten mehr nachgeladen, die nicht schon in der Datenbank sind. Ein Budgetjahr wird jeweils Ende des Vorjahres für das kommende Jahr erstellt (Ende 2026 entsteht das Budget 2027).
- **F76** Zwei weitere Filter auf der Budget-Seite: **zuständige Kommission** und **Departement**. Jedes Departement hat eine zuständige Sachkommission; die beiden Filter hängen entsprechend zusammen. Die Auswahl schränkt alle Tabs und die Summenzeile auf das gewählte Departement bzw. die gewählte Kommission ein.
- **F77** Filter nach **Kostensteigerung** gegenüber dem Vorjahr, in Prozent und in absoluten Franken, kombinierbar (z.B. «alles über 5% gestiegen» und/oder «alles über 1 Mio gestiegen»). Verglichen wird der **beschlussfähige Globalkredit** (Nettokosten) der Produktegruppe — Soll des Budgetjahres gegen Soll des Vorjahres —, nicht der Bruttoaufwand. Der Filter zeigt **nur echte Anstiege**: eine Produktegruppe, deren Globalkredit gleich bleibt oder **sinkt** (negative Differenz), erscheint bei einer «Anstieg ab …»-Schwelle nie, ebenso wenig ein Anstieg **unter** der Schwelle. Der Prozentwert misst am **Betrag** des Vorjahreswertes (`abs`), damit ein negativer Vorjahreswert das Vorzeichen nicht verdreht; ist der Vorjahreswert 0, gilt der Prozentanstieg als 0.
- **F78** Sortiert wird überall in der Reihenfolge des Buches: **Departement → Produktegruppe → Produkt** (im Investitions-Tab: Departement → Projekt).
- **F79** Auf **allen** Tabs steht zuoberst eine Summenzeile, die bei jeder Anpassung neu berechnet wird und sich immer nach den aktiven Filtern richtet (zeigt also nur das gefilterte Departement/die Kommission, nicht zwingend die ganze Stadt). Enthalten sind: Total Stellen mit Zu-/Abnahme, Einnahmen, Ausgaben, Ertrag bzw. Defizit und Steuerfuss — jeweils mit der Differenz zum Vorjahr. Auf breiten Bildschirmen mit genügend Platz bleibt die Summenzeile beim Scrollen oben stehen.

### Tab «Globalbudgets» (Teil B)

- **F80** Der erste Tab zeigt die **Globalbudgets** aus Teil B: je Produktegruppe das beschlussfähige Globalbudget (Nettokosten) mit den Werten des Budgetjahres, des Vorjahres und des Rechnungsjahres. Die zugehörigen **Produkte** stehen zur Information darunter, die **Erläuterungen und Begründungen** aus dem Buch werden an der richtigen Stelle mit ihren Zahlen angezeigt.
- **F81** **Anträge sind nur auf Produktegruppen möglich** — sie sind im Buch klar als «Zum Beschluss» gekennzeichnet. Produkte gehören zum Informationsteil und lassen sich nicht beantragen.
- **F82** Eigene und fremde **Anträge** werden erfasst und nach Produktegruppen geordnet dargestellt. Zu jeder Produktegruppe sind die daran gestellten Anträge (Betrag, Antragsteller, Begründung) sichtbar.
- **F116** **Der Kopf jeder Karte zeigt, was unsere Anträge an dieser Position bewirken** — dieselbe Gegenüberstellung wie die Übersicht (F102), aber je Produktegruppe: Wirken unsere Anträge auf sie, steht oben der Wert des **Stadtrats** (wie vorgelegt) und darunter der Wert der **Fraktion** (nach Abzug unserer Anträge), jeder mit **seiner Differenz zum Vorjahr**. Es zählen dieselben Anträge wie in der Übersicht: in der Vorbereitung die von der Fraktion unterstützten, im Sitzungsmodus die vom Parlament angenommenen; ein Personalantrag senkt neben den Stellen auch den Betrag. Ändern unsere Anträge nichts an der Position (kein Antrag, oder keiner, der zählt), steht wie bisher nur der eine Wert da, ohne Beschriftung. Das gilt auf dem Tab «Globalbudgets» mit dem Betrag und auf dem Tab «Personalbestand» mit den Stellen. **Beide Werte samt Differenz stehen immer vollständig da**: Reicht die Breite der Karte nicht, rückt der Wert unter die Bezeichnung, statt am Kartenrand abgeschnitten zu werden.
- **F117** **Ein Antrag wird durch einen Klick in seine Zeile bearbeitbar.** Die Zeile öffnet dasselbe Formular wie «+ Antrag», gefüllt mit allem, was am Antrag steht (Richtung, Betrag in CHF und Prozent, Stellen, Herkunft, Antragsteller, unterstützende Fraktionen, Begründung, Zielvorgaben-Änderungen und Einsparungsverteilung). «Antrag» speichert die Änderung an genau diesem Antrag, «Abbrechen» verwirft sie; die Bedienelemente der Zeile (Haltung, Löschen, Verknüpfung, Notizen, Beschluss) behalten beim Klick ihre eigene Wirkung. **Automatisch erzeugte Anträge** (F85) gehören ihrem Pauschalantrag und lassen sich nicht so bearbeiten. Das gilt für die Anträge auf allen drei Tabs (Globalbudgets, Personalbestand, Investitionsrechnung).

### Globale Kürzungsanträge und automatische Verteilung

- **F83** Ein **globaler Kürzungsantrag** verteilt einen festgelegten Betrag anteilig auf alle verfügbaren (nach aktuellem Filter beschlussfähigen) Produktegruppen — **anteilig zum Aufwand** (grössere Budgets tragen absolut mehr). Die dafür nötigen Einzelanträge werden dabei automatisch erzeugt.
- **F84** Alle Pauschalanträge werden **gleich behandelt** und stehen im gemeinsamen Kasten «Pauschalanträge» (F100). Die Liste **startet leer**; «+ Pauschalantrag» legt einen neuen an. Jeder Pauschalantrag trägt einen **Ziel-Typ**:
    - **Einsparungen** (relativ): ein fester Betrag (CHF) oder Prozentsatz des ursprünglichen Aufwands wird anteilig gekürzt. Beliebig viele möglich, ihre Kürzungen kumulieren.
    - **Schwarze Null**, **fester Ertrag** oder **festes Defizit** (absolut): das Gesamtergebnis wird auf diesen Wert ausgeglichen. Von diesen **absoluten Zielen darf nur EINES aktiv sein**; wählt man ein zweites, wird das ältere automatisch zur **Einsparung 0** herabgestuft (der ältere weicht). Es gibt **keinen Automatik-Schalter** und **keinen separaten «Defizit verteilen»-Knopf** mehr: ein absolutes Ziel gleicht selbsttätig aus. Zuerst werden alle Einsparungen gerechnet, das absolute Ziel **zuletzt** auf dem bereits gekürzten Stand (F100).
- **F112** **Über das Budget hinaus wird nichts gekürzt.** In keinem Feld — Produktegruppe, Produkt, Kostenzeile, Investitionsprojekt, Personalbestand — darf mehr gespart werden, als dort budgetiert ist; die Untergrenze ist Null. Die Grenze gilt für die **Summe aller Anträge** auf dasselbe Feld, nicht je Antrag: Ein Antrag, mit dem die Summe 100% des Budgets überschreiten würde, wird abgelehnt, und die Meldung nennt das Budget, das bereits Beantragte und den verbleibenden Rest. Ein Pauschalantrag, dessen anteilige Verteilung ein Feld unter Null drücken würde, kürzt es **auf Null** und verteilt den Überhang auf die übrigen Felder.
- **F113** **Jeder abgelehnte oder fehlgeschlagene Aufruf im Budget wird gemeldet**, mit dem Grund, den der Server nennt — Antrag stellen, ändern, löschen, entscheiden, Haltung umschalten, Pauschalantrag, Steuerfuss, Import und Neu-Einlesen. Ohne diese Meldung blieb das Formular offen und es geschah nichts, ohne jede Erklärung.
- **F85** Die zur Erfüllung eines Pauschalantrags nötigen **Einzelanträge** werden automatisch auf den Produktegruppen erstellt, angepasst oder — wenn nicht mehr nötig — wieder gelöscht; sie verteilen **anteilig zum Aufwand** und greifen nur auf die echten, operativen Produktegruppen (nie auf die künstliche, F89). Automatisch erzeugte Anträge sind als solche erkennbar und in der Oberfläche eigens filterbar.

### Tab «Personalbestand»

- **F86** Der zweite Tab zeigt ausschliesslich den **Personalbestand**: vom Gesamttotal heruntergebrochen nach Departement und Produktegruppe (bis auf die Produkte nur, soweit das Buch die Stellen dort ausweist — im Regelfall endet die Aufschlüsselung bei der Produktegruppe). Es sind **Anträge auf Personalkürzung oder -aufstockung** möglich: angegeben werden die Zahl der Stellen (Kürzung oder Erweiterung) und der entsprechende Betrag. Wird kein Betrag angegeben, gilt ein im Verwaltungsbereich konfigurierbarer Standardbetrag pro Stelle (anfänglich 200'000 CHF).

### Tab «Investitionsrechnung»

- **F87** Der dritte Tab zeigt die **Investitionsrechnung**. Auch hier lassen sich Anträge (Änderungsanträge) stellen. Sortiert wird nach **Departement und Projekt** (Investitionen sind nicht nach Produktegruppe/Produkt gegliedert, sondern nach Departement/Investitionscluster und Einzelprojekt). Zuoberst stehen die Summen. Je Projekt werden zusätzlich angezeigt: bereits getätigte und in den künftigen Jahren noch folgende Investitionen zum selben Projekt, die Gesamtkosten sowie — soweit verfügbar — laufende oder bereits getätigte Planungskosten. Hier gibt es **keinen** automatischen Verteilmechanismus.
    - Woher die Zahlen kommen: Der Anhang «Investitionsplanung Verwaltungsvermögen» führt je Projekt fünf Jahresspalten. Die Spalte **vor** dem Budgetjahr ist das bereits Investierte, die drei danach sind die Planjahre. Der zweite Anhang «Kontrolle der Investitionskredite» führt je Projekt den **Gesamtkredit** und darunter die einzelnen **Konten** mit Teilbetrag, bewilligtem Kredit und dem **Datum der Bewilligung**. Eine Zeile «… - Planung» schlüsselt den Planungsanteil des Projekts darüber auf und zählt nicht als eigenes Projekt.
    - **Details je Projekt:** Ein Klick auf «Details» öffnet denselben Vollbild-Dialog wie bei einer Produktegruppe. Es zeigt die Jahresreihe vom bereits Investierten über das Budgetjahr bis in die drei Planjahre samt Gesamtkosten, den Planungsanteil und die Tabelle der bewilligten Kredite je Konto. Führt das Buch für ein Projekt keine einzelnen Kredite, sagt das Detail das.
    - **Gesamtkosten sind die Summe aller Jahre** — bereits getätigt, Budgetjahr und die drei Planjahre. Der im Anhang «Kontrolle der Investitionskredite» geführte **bewilligte Kredit** steht daneben als eigener Wert; er sagt, was das Parlament freigegeben hat, und ist deshalb eine andere Aussage als die geplanten Kosten.
    - **Der Betrag des Budgetjahres ist der grösste und stärkste Wert der Karte** — über ihn wird entschieden. Das gilt auf jeder Karte des Budgets, auch bei den Globalbudgets und beim Personalbestand.
    - **Filter «nur mit Betrag im Budgetjahr»:** Projekte, die im Budgetjahr null führen (sie beginnen erst in einem Planjahr), lassen sich ausblenden.

### Tab «Steuerfuss»

- **F88** Der vierte Tab zeigt den **Steuerfuss**. Der Tab zeigt den **geltenden** Steuerfuss, den **beantragten** Steuerfuss (aus dem Stadtratsantrag) und die Differenz zum Vorjahr (z.B. ±0%). Standardmässig wird bei einem **Ertragsüberschuss** der Steuerfuss in abgerundeten Prozent-Schritten automatisch gesenkt und der Überschuss dadurch abgebaut (1 Steuerprozent = Steuerertrag geteilt durch den geltenden Steuerfuss). Der Schalter «Steuerfuss bei Überschuss automatisch senken» wird **pro Budgetjahr gespeichert** und überlebt das Neuladen. Bei aktiver Automatik ist «Antrag stellen» implizit ein; das manuelle Feld und der «Antrag stellen»-Schalter sind ausgeblendet. **Schaltet man die Automatik aus, fällt der Steuerfuss auf den Stadtratsantrag zurück** (der automatische Antrag verschwindet), und es erscheint ein Eingabefeld (vorbelegt mit dem Stadtratsantrag) samt dem **Schalter** «Antrag stellen». «Antrag stellen» ist ein **Schalter, kein Knopf**: einschalten stellt den Steuerfuss-Antrag auf den Feldwert (ändert man das Feld, wird der Antrag entprellt nachgeführt), ausschalten löscht ihn wieder (zurück zum Stadtratsantrag). Mehrfaches Auslösen während des Neurechnens erzeugt **keine doppelten Anträge** (Laufsperre). Eine **beliebige manuelle Steuerfuss-Festlegung ist nie ein Konflikt** — sie steht für sich. Jede Steuerfuss-Anpassung (automatisch wie manuell) erzeugt einen **echten Antrag** und erzeugt/anpasst/löscht dazu automatisch einen **Antrag auf die Steuererträge** (Einnahmen) in der entsprechenden Höhe; beide werden standardmässig von der **eigenen Fraktion** beantragt und stehen am Ende der Antragsliste und des Antrags-PDF (F92).
    - **Steuerfuss-Senkung und fixer Ertrag zusammen:** Ist die automatische Senkung aktiv und es gibt **keinen** fixen Pauschalantrag, wird der natürliche Ertragsüberschuss über die Senkung abgebaut → Gesamtertrag null. Gibt es zusätzlich einen **fix definierten Ertrag** (fixer Pauschalantrag, F100), wird **exakt dieser fixe Ertragsbetrag zur Steuerfuss-Senkung verwendet**, und der tatsächliche Fraktions-Ertrag ist dann **nicht** mehr der fixe Betrag, sondern **null**. Ohne aktive Senkung bleibt der fixe Ertrag auf seinem festgelegten Wert (F100).

### Parlamentarische Zielvorgaben und Antrags-Aufteilung (WoV)

WoV (Wirkungsorientierte Verwaltung): das Parlament steuert die Verwaltung über Wirkungs- und Leistungsziele, nicht über Ausgabenposten. Entschieden wird **nur auf Ebene Produktegruppe** — über das Globalbudget (Geld) und die Zielvorgaben. Alles andere (Produkte, Kostentabellen, Erläuterungen) ist Information.

- **F109** Jede Produktegruppe trägt ihre **Parlamentarischen Zielvorgaben**: nummerierte Ziele (z.B. «2 Kundenorientierung zentrales Personalmanagement») mit je einer oder mehreren **Messgrössen**, jede mit sechs Jahresspalten (Ist Vorjahr, Soll Vorjahr, **Soll aktuell**, drei Planjahre). Das Parlament entscheidet über «Soll aktuell».
    - **Gelesen wird auch, was das Buch offen lässt:** Eine Messgrösse, die erst mit dem Budgetjahr eingeführt wird, trägt nur die letzten Spalten — die leeren fehlen links. Ein Sollwert steht als Zahl, als Vergleich («>22000»), als Spanne («1 bis 2»), mit Prozentzeichen oder mit Fussnotenzeichen. Beantragbar ist eine Vorgabe nur, wo im Soll des Budgetjahres eine Zahl steht: «N/A» und «wird gelöscht» sind nichts, worüber das Parlament beschliessen könnte.
    - **Antrag auf eine Zielvorgabe:** Zu jeder Zahl kann ein Antrag gestellt werden, der den Soll-Wert ändert (z.B. 100 statt 90). Ein Zielvorgaben-Antrag hat **keine automatische Budgetwirkung**. Ein Antrag kann **eine oder mehrere Zielvorgaben** ändern.
    - **Zielvorgabe und Budget getrennt oder zusammen:** In EINEM Antrag lassen sich Zielvorgaben-Änderungen **und** eine Budgetanpassung kombinieren. Nur Zielvorgabe ohne Budget: der Budgetwert bleibt leer. Nur Budget ohne Zielvorgabe: keine Zielvorgabe wählen. Beispiel: «nur noch 80% zufrieden, dafür 20% billiger» sind zwei Wirkungen in einem oder zwei Anträgen — Zielvorgabe 80 statt 90 und ein Budgetantrag «20% einsparen».
    - **Hierarchische Aufteilung (Einsparungsverteilung):** Weil das Parlament nur über die Produktegruppe bestimmt, dient die Aufteilung vor allem der **Begründung** — sie sagt, **wo** innerhalb der Produktegruppe der beantragte Betrag einzusparen ist. Beim Antrag lassen sich mit je optionalem Betrag **oder** Prozentanteil auswählen: (a) eine oder mehrere **Zeilen der Kostentabelle** im Informationsteil der Produktegruppe (Personalkosten, Sachkosten, Informatikkosten …), (b) ein oder mehrere **Produkte**, (c) eine oder mehrere **Kostenzeilen innerhalb eines Produkts**.
        - Ist auf Ebene Produktegruppe **kein** Betrag gesetzt, weiter unten aber schon, wird oben die **Summe** der unteren Beträge eingesetzt.
        - Ist oben **und** unten ein Betrag gesetzt, wird nichts gerechnet — die Aufteilung geht dann **nur in die Begründung**.
        - Ein unten gesetzter Betrag oder Prozentwert bedeutet: vom ganzen beantragten Betrag ist der angegebene Anteil an dieser Stelle einzusparen.
        - Die vollständige Aufteilung erscheint **im Antrags-PDF in der Begründung**, zusätzlich zur manuell erfassten Begründung.
    - **Produktegruppen-Budget = Summe der Produkt-Budgets.** Produkte sind Information; manche sind in ihren Kosten weiter aufgeschlüsselt.

- **F110** **Grafischer Aufbau der Budgetansicht (Baumstruktur** Globalbudget → Departement → Produktegruppe → Produkt**):**
    - **Departement:** dient nur als Gruppierungstitel, unter dem direkt die Karten der Produktegruppen stehen — kein Informationstext darüber. Das Budgetbuch führt allgemeine Informationen nur auf Ebene Produktegruppe, nicht je Departement.
    - **Produktegruppe:** bleibt als Karte; ein Klick öffnet einen **Vollbild-Dialog** (wie bei den Geschäften), der den ganzen Bildschirm für eine Produktegruppe nutzt. Im Kopf der Globalkredit, oben die Zielvorgaben, darunter die Produkte als Karten. Jede Produkt-Karte zeigt oben die prominente Nettokosten-Soll-Zahl, darunter die **Kostentabelle** (Kosten, Erlös, Nettokosten, Kostendeckungsgrad über Ist, Soll Vorjahr und Soll aktuell — «Soll aktuell» hervorgehoben), und die **Leistungen** des Produkts als Aufzählung.
    - **Erläuterungen** (Auftrag, Begründungen, Massnahmen, Erläuterungen zum Stellenplan) gehören zur Produktegruppe und behalten ihre **Formatierung** (Absätze, Aufzählungen); dazu die Leistungen je Produkt — alles wird im Dialog angezeigt.

- **F111** **Grafische Übersicht als geschachtelte Kreise** — der letzte Tab des Budgets («Grafik») zeigt das ganze Budget als geschachtelte Kreise, deren **Fläche dem Betrag entspricht**:
    - **Zwei Zeichenflächen nebeneinander: «Einnahmen» und «Ausgaben»**, jede über die halbe Seitenbreite; auf schmalen Seiten stehen sie untereinander. Der Kopf jeder Seite nennt ihre Summe.
    - Darin je ein Kreis **pro Departement**, dessen Fläche seinem Anteil an Einnahmen bzw. Ausgaben entspricht; darin die **Produktegruppen**, darin die **Produkte**. Ein Produkt trägt auf der Ausgabenseite seine Kosten, auf der Einnahmenseite seinen Erlös aus der Kostentabelle des Budgetbuchs; wo ein Produkt keine Kostentabelle hat, bleibt die Produktegruppe die unterste Ebene.
    - **Beide Seiten zeigen immer denselben Ausschnitt:** ein Klick auf ein Departement zoomt Einnahmen UND Ausgaben dorthin, so stehen Aufwand und Ertrag derselben Einheit nebeneinander. Wo eine Seite den gewählten Ausschnitt nicht kennt (kein Ertrag), sagt sie das. Ein Klick auf den Hintergrund oder `Escape` geht eine Ebene zurück; der Weg im Kopf («Budget › Präsidiales › Bibliotheken») führt per Klick direkt auf jede Ebene zurück.
    - **Die Grössenrelation zwischen den Seiten bleibt auf jeder Ebene erhalten** — der grössere Betrag füllt seine Fläche, der kleinere wird im selben Massstab kleiner gezeichnet.
    - **Jeder Kreis der aktuellen Ebene ist beschriftet** — mit dem **vollen Namen**, an den Leerzeichen auf mehrere Zeilen umgebrochen. Gekürzt wird nie: vier Produkte «Bewirtschaftung …» wären als «Bewirtscha…» nicht zu unterscheiden. Tiefere Ebenen tragen ihre Beschriftung, sobald sie gross genug sind. Jeder Kreis nennt beim Überfahren Name, Betrag, Anteil am übergeordneten Kreis — und, wo die Produkte das Budget ihrer Gruppe nicht erklären, wie viel Prozent sie ausweisen.
    - Farbe je Departement, die Kinder in Abstufungen derselben Farbe — dieselbe Einheit hat auf beiden Seiten dieselbe Farbe.
    - **Die Kreise finden ihren Platz** — mit d3s Kraftsimulation (`d3-force`): Jede Einheit wird an die Stelle gezogen, die ihr die Packung zuweist, und dabei von ihren Nachbarn auf Abstand gehalten. Das sieht aus wie ein Zusammenfinden: Die Kugeln schieben einander beiseite, rücken zusammen und ordnen sich, bis alles passt. Beim Öffnen einer Ebene starten sie dort, wo sie stehen, und wandern an die neuen Plätze; ihr Inhalt wandert mit seiner Einheit. Die Simulation kommt nie ganz zur Ruhe, damit das Bild lebt.
    - **Sie kommen von weit her und sind mehrere Sekunden unterwegs:** Beim Aufbau der Fläche starten sie auf einem Ring weit ausserhalb, aus allen Richtungen verteilt, und die Reise dauert **zwei bis fünf Sekunden**. Der Zug zum Platz zieht dabei langsam fester an, die Kollision lässt nach: So bleibt der Anfang sanft und am Ende steht jeder Kreis auf seinem Platz. Wird der Tab geöffnet, beginnt die Reise von vorn — die Fläche liegt als Tab unter mehreren von Anfang an im Dokument, und ohne das wäre die Bewegung längst gelaufen, bevor jemand hinsieht.
    - **Jeder Kreis nennt beim Überfahren sich selbst:** Name, Betrag, Anteil am übergeordneten Kreis — auch der kleinste, der keine Beschriftung mehr trägt. Der Klick trifft dabei weiterhin die Ebene, der er gilt.
    - **Die Summe jeder Seite steht hervorgehoben im Kopf** — grösser und kräftiger als der Rest, denn sie ist die Zahl, um die es geht.
    - **Beim Überfahren tritt der Kreis unter dem Zeiger hervor:** er wächst ein Stück, legt sich mit kräftigerer Kante über seine Nachbarn — und zeigt die **Namen seiner Kinder**, solange der Zeiger auf ihm steht. So ist vor dem Klick zu sehen, was einen dort erwartet.
    - Die Grafik folgt den **Filtern** der Budgetansicht (Departement, Kommission): was gefiltert ist, fehlt auch hier.

### Import der Budgetbücher

- **F89** Die Informationen aus dem Budgetbuch werden besser lesbar und systematisch ausgelesen und in die Datenbank geschrieben — mindestens alle vom Parlament festlegbaren Budgets (Produktegruppen) einzeln. Hinweise und Erklärungen werden an die richtige Stelle kopiert und mit den dazugehörigen Zahlen angezeigt. Alles wird automatisch angelegt, sobald eine neue Budgetweisung veröffentlicht und abgerufen wurde. Das Einlesen ist gegenüber Formatanpassungen der Bücher so tolerant wie möglich.
    - **Departementszuordnung robust gegen umgebrochene Inhaltsverzeichnis-Zeilen:** Ein Departementsname enthält immer Buchstaben. Eine im Buch umgebrochene Zeile aus nur Füllpunkten und einer Seitenzahl («……… 175») wird nicht als Departementskopf gelesen und landet nicht als Departement der folgenden Produktegruppe (die dann unter ihrem richtigen Departement erscheint).
    - **Das Budgetbuch wird LIVE von der Parlamentswebseite geladen, nie aus einer gebündelten Datei.** Beim Abrufen der Webseite wird das **Budget-Geschäft** erkannt (die Weisung «Budget&nbsp;<Jahr> … Festsetzung des Steuerfusses»); auf dessen Seite hängen die Bücher als Beilagen «… Teil A …» (Budget/Finanzplan/Investitionen/Steuerfuss) und «… Teil B …» (Produktegruppen-Globalbudgets). Beim Import werden genau diese beiden PDF **im Betrieb heruntergeladen und gelesen**; das Ergebnis geht direkt als SQL in die Datenbank. **Im Abbild liegt kein Budgetbuch und kein JSON** — neue Bücher kommen in die Datenbank, ohne dass ein neues Abbild gebaut wird. Ein aus dem Buch erzeugtes JSON gibt es im laufenden Betrieb **zu keinem Zeitpunkt**. Die Herkunft (URL des Budget-Geschäfts) wird als **Weisungsquelle** gespeichert und als Link im Kopf der Budgetseite gezeigt. Wo ein Budget existiert, existiert damit immer auch der Link.
    - **Die Bezeichnungen stammen aus dem Dokument:** Sie folgen dem Budgetbuch (etwa «Ertragsüberschuss», «Aufwandüberschuss», «Gesamtergebnis»), nicht selbst erfundenen Wörtern.
    - **Gelesen wird JEDER Jahrgang, den die Stadt veröffentlicht hat** — die Bücher 2017 bis 2027. Die Stadt hat ihr Rechnungsmodell und ihren Buchsatz mehrfach gewechselt, und das Einlesen kennt beide Welten: die Bücher bis 2019 rechnen nach HRM1 (das Ergebnis ist, was der Steuerertrag des Rechnungsjahres vom zu deckenden Aufwandüberschuss übrig lässt), ab 2020 nach HRM2 (Ergebnis = Ertrag − Aufwand). Die Tausender trennt bis 2020 ein Leerzeichen, ab 2021 ein Apostroph — in einer einzigen Tabellenzelle auch beides gemischt; die Beträge des gestuften Erfolgsausweises tragen in den älteren Büchern ein Buchhaltungszeichen («1'692.7 S»); und die Jahreszahlen des Tabellenkopfs stehen mal auf einer Zeile, mal untereinander. Wo eine dieser Formen nicht erkannt wurde, standen plausible Zahlen aus dem falschen Jahr oder ein Gesamtaufwand von 33 Millionen statt 1,4 Milliarden.
    - **Geprüft wird das Lesen selbst** — mit **echten Budgetbüchern (PDF) aus mindestens vier vergangenen Jahrgängen**. Diese PDF sind **reine Testdaten** (unter `parlwin/tests/Fixtures/`, über `.dockerignore` **nie im Abbild**) und dienen im Test **ausschliesslich als Eingabe für das Lesen und zum Vergleich des Ergebnisses** — sie werden **jedes Mal neu gelesen**, nie durch ein einmal gelesenes Ergebnis ersetzt. Der Import- Test geht sogar denselben Weg wie im Betrieb (Seite des Geschäfts → Links auf Teil A und B → die PDF selbst), damit derselbe Pfad wie produktiv geprüft wird. Zusätzlich werden die im Dokument ausgewiesenen **Gesamtsummen** (Gesamtergebnis, Total Aufwand/Ertrag) gelesen und mit den aus den Produktegruppen **errechneten** Werten verglichen — weicht die App-Summe vom Buch ab, ist entweder das Einlesen oder die Rechnung falsch (auch der Fehlerfall wird geprüft).
    - **Das Einlesen ist tolerant gegenüber Formatanpassungen** der Bücher (F89), projektweise gegen das Buch validiert (auch die Investitionsprojekte, Anhang Investitionsplan).
    - **Der Container gibt PHP 1024 MB Speicher**, für die Oberfläche, die Kommandozeile und den Hintergrundauftrag gleichermassen. Das Basis-Abbild liefert 512 MB, und ein Budgetbuch braucht beim Einlesen mehr (Buch 2027: 574,7 MB gemessen); der Wert ist im Betrieb einstellbar (`PARLWIN_PHP_MEMORY_LIMIT`).
    - **Gesamtergebnis über eine künstliche Produktegruppe.** Das offizielle Gesamtergebnis der Stadt (Erfolgsrechnung, Teil A) ist **nicht** Σ der operativen Produktegruppen: die Teil-B- Produktegruppen führen ihre effektiven Kosten/Erlöse **brutto** (inklusive der internen Verrechnung, die zwischen den Produktegruppen fliesst), während die Erfolgsrechnung **netto** abschliesst. **Steuererträge und Finanzausgleich stehen bereits in einer echten Produktegruppe** («Steuern und Finanzausgleich», Departement Finanzen) — sie werden nicht doppelt erfasst. Was ausserhalb der operativen Produktegruppen bleibt, ist die **interne Verrechnung samt Abgrenzung**; sie wird als **eine künstliche Produktegruppe «Interne Verrechnung / Abgrenzung»** im Departement Finanzen abgebildet, sodass **Σ(alle Produktegruppen inkl. der künstlichen) exakt das deklarierte Gesamtergebnis** ergibt. Das **Ergebnis** der künstlichen Produktegruppe wird an die **Schlagzeile der Erfolgsrechnung** gebunden (das vom Stadtrat deklarierte Gesamtergebnis, «Ertragsüberschuss» / «Aufwandüberschuss»); die Aufwand-Seite folgt dem Total aus dem Buch. Sie wird einmalig beim Import gebildet und ist danach fix; Anträge und Novemberbrief verschieben die Summe wie bei jeder echten Produktegruppe. Die künstliche Produktegruppe ist **nicht antragbar**: kein «+ Antrag», keine Pauschalkürzung greift auf sie (Pauschalkürzungen verteilen nur auf die echten, operativen Produktegruppen). Die **Summenprobe** prüft an den echten Büchern, dass Σ(alle Produktegruppen) exakt dem deklarierten Gesamtergebnis entspricht.
    - **Anzeige der künstlichen Produktegruppe:** im **Globalbudget-Tab** beim Departement Finanzen, **nach** den echten Produktegruppen, optisch durch eine **leicht andere Hintergrundfarbe** abgesetzt (aus einem CSS-Token, nie ein harter Farbcode) und **ohne** Antrags-Bedienelemente; im Personalbestand-Tab erscheint sie nicht (sie trägt keine Stellen).
- **F90** Das **Drehbuch zur Budgetbehandlung** (die Beilage der Budgetsitzung, die den Ablauf und alle Anträge der Sitzung führt) ist die Quelle für zwei Dinge — die Sitzungsanträge und den Novemberbrief. Es wird **live von der Parlamentswebseite geladen**, nie gebündelt: vom Budget-Geschäft über dessen Traktandum zur Budgetsitzung und von deren Seite die Beilage, deren Bezeichnung «Drehbuch» enthält.
    - **Sitzungsanträge einlesen (Sitzungsmodus):** Im Sitzungsmodus erscheint oben rechts «Sitzungsanträge einlesen». Der Knopf lädt das Drehbuch und übernimmt die dort je Produktegruppe behandelten **Kommissions- und Fraktionsanträge** als offizielle Sitzungsanträge. Zu jedem Antrag werden **Quelle** (z.B. «AK», «Fraktion SP»), **Richtung und Betrag** (Erhöhung/Reduktion des Globalkredits in CHF) und die **Begründung** übernommen; das **Abstimmungsergebnis der Kommission** (z.B. «11:0 angenommen», oder keines bei einem in der Kommission nicht abgestimmten Fraktionsantrag) wird in der Begründung vermerkt. Die eingelesenen Anträge sind **fremde** Anträge und stehen zunächst **offen** (unsere Haltung setzen wir selbst). Das Einlesen ist **wiederholbar, ohne zu duplizieren**: bereits vorhandene, gleich lautende Sitzungsanträge (gleiche Produktegruppe, Antragsteller und Betrag) werden nicht erneut angelegt, und von Hand gesetzte Haltungen und Entscheide bleiben unberührt. Während des Ladens läuft ein Fortschrittsbalken; am Ende meldet eine Benachrichtigung, wie viele neue Anträge dazugekommen sind (oder dass keine neuen vorlagen bzw. kein Drehbuch gefunden wurde).
    - **Gelesen wird JEDE Sitzungsunterlage, die das Parlament veröffentlicht hat** — die Drehbücher 2022 bis 2026. Ihr Aufbau hat sich geändert: Von 2023 an nennt der Antragssatz Richtung und Betrag («Antrag AK: Reduktion des Globalkredits um CHF 100'000»), mit Abweichungen im Wortlaut («Globalbudget» statt «Globalkredit», ein Einschub zwischen Gegenstand und Betrag). Das Drehbuch 2022 nennt im Satz nur die Begründung; **Betrag und Gremium stehen dort allein in den Spalten «Antrag Kom.» und «Antrag Fraktion»** der Nettokosten-Zeile, und die Kommissionen hiessen anders (die «BBK» gibt es später nicht mehr). Ein Antrag zu einer **parlamentarischen Zielvorgabe** oder zu einem **Verpflichtungskredit** steht in einer eigenen Tabelle derselben Produktegruppe und ist **kein** Antrag auf den Globalkredit; ein Antrag auf einen anderen **Steuerfuss** wird als solcher geführt.
    - **Novemberbrief:** Die nachträglichen Korrekturen des Stadtrats zum Budgetentwurf stehen bis zum Budget 2022 in einer **eigenen Beilage «Novemberbrief»** am Budget-Geschäft, danach in der Spalte «NB» der Nettokosten-Tabelle des Drehbuchs. Beide Quellen werden gelesen, die Beilage zuerst. Sie führt je Produktegruppe die Korrektur von **Aufwand, Ertrag und Nettokosten** und rechnet in der Zeile «Stadt Winterthur» ihre Summe vor; die Spalte des Drehbuchs nennt nur die Nettokosten. Führt keine der beiden Quellen Korrekturen (wie im Budget 2026), gibt es für das Jahr keinen Novemberbrief und der Knopf erscheint nicht.
- **F91** Für das Einlesen von Hand und für Tests: Über «+ Neu» lässt sich ein vergangenes Budgetjahr **importieren**. **«Vergangen» heisst:** das zugehörige **Budget-Geschäft ist «Erledigt»** — das Parlament hat das Budget beschlossen bzw. genehmigt. Das **aktuelle** (nicht vergangene) Budget ist das **noch nicht erledigte** Budget-Geschäft, das gerade in Beratung ist; **nur dieses** wird automatisch eingelesen (F89). Jedes erledigte Budget ist vergangen und wird **ausschliesslich manuell** über «+ Neu» importiert (nie automatisch) — auch das Budget des laufenden Kalenderjahres, sobald es beschlossen ist. Zeitlogik dazu: das aktuelle Budget ist immer das des **nächsten** Jahres, das gegen Ende des laufenden Jahres erstellt wird (Ende 2026 → Budget 2027); **nach dem 1. Dezember des Vorjahres** werden nicht generierte Budgetjahre ignoriert — es werden keine Daten mehr abgerufen, die nicht schon in der Datenbank sind. Die Auswahl bietet die Jahre an, für die Budgetunterlagen vorliegen und die noch nicht importiert sind (kein Freitext), wahlweise nur das Budget oder zusammen mit dem Novemberbrief. Ist für ein Jahr nur das Budget in der Datenbank, führt aber das Drehbuch der Budgetsitzung Novemberbrief-Korrekturen (Spalte «NB»), die noch nicht eingelesen wurden, lässt er sich von Hand einlesen. Der dafür nötige Knopf «Novemberbrief einlesen» erscheint nur dann — oben rechts neben «+ Neu» — wenn für das betreffende Jahr ein Budget in der Datenbank liegt, es noch nicht als Novemberbrief-eingelesen markiert ist und eine Quelle tatsächlich Korrekturen führt (die Beilage «Novemberbrief» oder die NB-Spalte des Drehbuchs). Andernfalls ist der Knopf nicht vorhanden. **Eingelesen wird als Korrektur:** Der Novemberbrief nennt Änderungen, keine neuen Beträge; sie werden auf den Stand des Budgetentwurfs addiert — Aufwand, Ertrag und Globalkredit je Produktegruppe. **Jedes Jahr nimmt sie genau einmal an:** Ein zweiter Aufruf ändert nichts mehr. Wird das Jahr neu eingelesen, stehen wieder die Zahlen des Budgetentwurfs da, und der Novemberbrief wird erneut angeboten.
    - **Budget neu einlesen (destruktiv, doppelt abgesichert):** Ein bereits importiertes Budgetjahr lässt sich über «Budget neu einlesen» (im Kopf der Budgetseite) vollständig neu aus dem Budgetbuch einlesen. Dabei werden **alle bestehenden Anträge, Notizen, Pauschalanträge und Entscheide zu diesem Budget unwiederbringlich gelöscht**. Wegen dieser Gefahr geschieht das **nur nach doppelter Bestätigung**: ein Dialog «Bist du ganz sicher?» mit einem Kontrollkästchen «Ich verstehe, dass alle bestehenden Anträge, Notizen, usw. zu diesem Budget dabei unwiederbringlich gelöscht werden»; der Knopf «Neu einlesen» bleibt gesperrt, bis das Kontrollkästchen gesetzt ist. Für die Administration gibt es denselben Vorgang als occ-Befehl `parlwin:budget-reimport <Jahr>` (mit `--purge` für den vollständig leeren Neuanfang).

### Anträge einreichen

- **F92** Ein **PDF mit den Anträgen der Fraktion** lässt sich erzeugen — insgesamt oder getrennt nach Kommission —, damit die Fraktion in der Kommission ein fertiges Antrags-PDF einreichen kann. Das PDF enthält die **eigenen einzureichenden Anträge** (Schalter «Antrag stellen» ein, F97); eine **Option (Schalter)** nimmt zusätzlich die **von uns unterstützten fremden Anträge** auf (Schalter «Unterstützen» ein). Ein Antrag, den wir nicht einreichen, und ein Pauschalantrag mit abgeschaltetem Einreichen-Schalter (F100) erscheinen nicht. Als **Antragsteller steht im PDF immer die Fraktion** (im Rat stellt die Fraktion die Anträge) — die eingetragene Person erscheint nur in der Bildschirm-Übersicht, nicht im PDF. Die automatische Steuerfuss-Senkung (F88) steht als eigener Antrag am Ende der Liste. Der PDF-Knopf steht im Anträge-Tab (F102).
- **F93** In den Sitzungen, in denen das Budget traktandiert ist, lassen sich die **Beschlüsse laufend mitführen**: Jeder gestellte Antrag (eigene wie fremde) wird aus der Einladung bzw. den Dokumenten übernommen und angezeigt. Zu Beginn sind alle Anträge «noch nicht entschieden». Während der Sitzung trägt ein Fraktionsmitglied ein, ob ein Antrag angenommen oder abgelehnt wurde; ein Antrag lässt sich auch wieder auf «noch nicht entschieden» zurücksetzen. Spontane Anträge lassen sich in der Sitzung erfassen. Anträge, die in einer Sitzung erfasst wurden, erscheinen auch in vorhergehenden und folgenden Sitzungen, in denen dasselbe Budget behandelt wird — analog zu den Sitzungsnotizen an einem Geschäft, die bei Verschiebungen oder mehreren Sitzungen zum selben Thema überall verfügbar sind (und über denselben Mechanismus).

### Angaben am Antrag

- **F94** Jeder Antrag hat eine **Herkunft**: «eigen» (Standard) oder «fremd» — mit demselben Bedienmuster wie beim Vorstoss (F19/F20). Der **Antragsteller** wird immer aus einer Liste gewählt, nie als Freitext, und ist **standardmässig die eigene Fraktion** (im Rat stellt die Fraktion die Anträge): bei eigenen Anträgen steht die **eigene Fraktion zuoberst** und ist vorbelegt, darunter die Personen der eigenen Fraktion (eine Person lässt sich statt der Fraktion wählen); bei fremden Anträgen eine **Fraktion** (zuoberst) oder eine Person. Die eigene Fraktion stammt aus der Konfiguration (dieselbe Quelle wie das PDF), die übrigen Personen und Fraktionen aus denselben Quellen wie beim Vorstoss (aktive Mitglieder, aktive Fraktionen).
- **F95** Ein Antrag trägt seinen Betrag **in CHF und in Prozent**, mit einem **Umschalter Reduktion/Mehrausgabe**: Standard ist eine Reduktion (Kürzung), umgeschaltet eine Mehrausgabe. Die Richtung ist allein das **Vorzeichen** (kein eigenes Richtungsfeld). Prozent bezieht sich auf den **Budgetwert der Position** (Beispiel: Kürzung um 20'000 bei einem Budget von 100'000 = −20%). Wird der Betrag in **Prozent** eingegeben, wird der CHF-Betrag berechnet; eine Eingabe im **CHF**-Feld rechnet sofort den Prozentwert nach. Führend ist der zuletzt eingegebene Wert; ändert sich später die Basis (z.B. durch den Novemberbrief), bleibt der CHF-Betrag fix.
- **F96** Der **Steuerfuss-Antrag** wird in **Prozentpunkten** gestellt (Senkung oder Steigerung), bezogen auf 100% = kantonaler Steuersatz. Der Steuerfuss wirkt direkt auf die erwarteten Steuereinnahmen, die sich im selben Verhältnis ändern: bei einer Änderung von X auf Y Prozentpunkte gilt «neuer Ertrag = bisheriger Ertrag × Y/X». −2 Prozentpunkte bei einem Steuerfuss von 125% bedeuten also 123% und einen Ertrag von bisher × 123/125. In CHF ist das nur eine Schätzung; massgeblich ist der Prozentpunkt-Wert.
- **F97** Jeder Antrag trägt **unsere Haltung** als einfacher **Schalter** an der Antragszeile, getrennt vom Sitzungs-Beschluss (F93): bei eigenen Anträgen **«Antrag stellen»** (ein = wir reichen ihn ein, Standard bei einem neu angelegten eigenen Antrag), bei fremden Anträgen **«Unterstützen»** (ein = wir unterstützen ihn; ein neu erfasster fremder Antrag startet ausgeschaltet). Nur eingeschaltete Anträge fliessen ins Fraktionsbudget, erscheinen in der Übersicht-Antragsliste und — beim eigenen Schalter — im Antrags-PDF (fremde nur mit der PDF-Option, F92); ein ausgeschalteter fremder Antrag wird ignoriert. Gelöscht wird ein Antrag mit **«✕»** (kein Papierkorb-Symbol). Der Beschluss (angenommen/abgelehnt, ✓/✕) erscheint nur im **Sitzungsmodus**, nicht in der Vorbereitung.
- **F98** Zu jeder Antragsposition lässt sich mit einer **Mehrfachauswahl** festhalten, **welche Fraktionen den Antrag unterstützen**. Haben wir für den Antrag Zustimmung beschlossen (F97), ist die eigene Fraktion automatisch ausgewählt.
- **F99** Auf jede Position (Produktegruppe, Projekt) lassen sich **beliebig viele Anträge** stellen; ihre Beträge **summieren sich**. Spezifische Anträge und Pauschalanträge mischen sich in beliebiger Zahl und Kombination.
    - **Gekürzt wird höchstens auf null** — was nicht ausgegeben wird, kann nicht gespart werden. Die Grenze gilt für die **Summe aller Anträge** auf dieselbe Position, nicht für den einzelnen: drei Anträge zu je 40% kürzen zusammen um 120% und sind damit unmöglich. Sie gilt in jedem Feld — Globalbudget, Personal, Investitionen — und ebenso für die automatische Pauschalverteilung: was eine Position nicht mehr tragen kann, geht auf die übrigen, und reicht auch das nicht, bleibt der Rest liegen (sichtbar an der Summe). Nach oben gibt es keine Grenze. Ein Antrag, der die Grenze überschreitet, wird abgewiesen; die Meldung nennt das Budget, das bereits Beantragte und was noch möglich ist.
- **F103** Jeder Antrag kann **Notizen** tragen — mit demselben Editor, derselben Bedienung und derselben Speicherung wie Notizen an allen anderen Objekten (F27).

### Pauschalanträge, Übersicht und Sitzungsverknüpfung

- **F100** Ein **Pauschalantrag** verteilt anteilig zum Aufwand auf die Positionen und erzeugt je Position einen Einzelantrag. Sein **Ziel-Typ** (F84) bestimmt, was verteilt wird: eine **Einsparung** in **CHF** oder in **Prozent** des **ursprünglichen** Aufwands (nie eines bereits gekürzten), oder ein **absolutes Ziel** (schwarze Null / fester Ertrag / festes Defizit), das das Gesamtergebnis auf diesen Wert ausgleicht. Jeder Pauschalantrag hat einen **Einreichen-Entscheid** (F97): wird er eingereicht, erscheint je nicht ausgenommene Position ein Einzelantrag im Antrags-PDF; wird er nicht eingereicht, keiner. Jeder trägt einen **Antragsteller** — eine **Fraktion** (F94), vorbelegt mit der **eigenen Fraktion** —, der sich auf die erzeugten Einzelanträge überträgt. **Beliebig viele** Pauschalanträge lassen sich anlegen; ihre Kürzungen kumulieren. Alle stehen im gemeinsamen Kasten **«Pauschalanträge»** und werden gleich behandelt; das eine absolute Ziel (F84) wird **zuletzt** gerechnet — auf dem bereits durch die Einsparungen gekürzten Stand. Änderungen an einem Pauschalantrag werden **automatisch übernommen** (kein «Übernehmen»-Knopf), kurz nach der letzten Eingabe.
- **F101** Eine einzelne Produktegruppe lässt sich vom Pauschalantrag **ausnehmen** — auf **zwei** Wegen, die sich entsprechen: über einen **Schalter direkt an der Produktegruppe** (je Pauschalantrag einer; ausgeschaltet = ausgenommen, angezeigt als «— ausgenommen») **oder** über die **Mehrfachauswahl «Ausnahmen (Produktegruppen)»** im Pauschalantrag selbst. Beide Wege zeigen **jederzeit denselben Stand**: was an der Produktegruppe ausgenommen oder wieder aufgenommen wird, steht sofort auch in der Mehrfachauswahl — und umgekehrt. Für eine ausgenommene Position entsteht kein Einzelantrag ins PDF. Der einzusparende **Gesamtbetrag bleibt gleich**: er verteilt sich neu auf die verbleibenden Positionen.
- **F102** Zuoberst steht die **Übersicht** als **Vergleich** zwischen dem **Stadtratsbudget** (die Zahlen, wie der Stadtrat sie vorgelegt hat — ohne unsere Anträge) und dem **Fraktionsbudget** (mit unseren Anträgen); eine **Differenz**-Zeile zeigt, was unsere Anträge bewirken. Verglichen werden Ausgaben, Einnahmen, Ergebnis (Ertrag/Defizit), Steuerfuss und Stellen; die Übersicht richtet sich nach den aktiven Filtern. In das Fraktionsbudget zählt nur, **was die Fraktion unterstützt**; unentschiedene oder abgelehnte Anträge zählen nicht. Im **Sitzungsmodus** zählen stattdessen die vom Parlament **angenommenen** (beschlossenen) Anträge — so ist laufend sichtbar, wieviel das eigene korrigierte Budget bringt bzw. was in der Sitzung tatsächlich beschlossen wurde.
- **F102a** Unter der Übersicht stehen die Bereiche als **Tabs** (Globalbudgets, Personalbestand, Investitionsrechnung, Steuerfuss) plus ein Tab **«N Anträge»** (N = Anzahl der Anträge), das **alle Anträge nach Departement zusammengefasst** zeigt (Position, Betrag/Stellen, Antragsteller, Begründung, Beschluss) — so sieht man alle Anträge auf einen Blick, ohne das PDF zu erzeugen; dort steht auch der Knopf **«Anträge als PDF»** samt der Option, die unterstützten fremden Anträge mitzunehmen (F92). **Übersicht und Tabs bleiben beim Scrollen als ein Block am oberen Rand** (die Tabs hängen unten an den Übersichtszahlen). Der Link zur **Weisung** steht im Kopf ganz rechts. Der Filter aller Listen (auch Budget) trägt unten einen einheitlichen Knopf **«Filter zurücksetzen»**.
- **F104** Ein Vorbereitungs-Antrag wird mit dem tatsächlich eingereichten **Sitzungsantrag verknüpft**: **automatisch, wenn eindeutig** (gleiche Position und gleicher Betrag), sonst nicht — und immer **manuell korrigierbar**. Über die Verknüpfung wird die Haltung bzw. der Entscheid in die Sitzung übernommen; für alle nicht verknüpften Sitzungsanträge bleibt offen, ob wir sie unterstützen, bis wir entscheiden.

## Fragestunde

- **F114** Ein eigener Bereich **«Fragestunde»** (in der Navigation direkt hinter «Budget») sammelt die Fragen der Fraktion für die **Fragestunde des Parlaments**. Das Parlament hält sie zweimal im Jahr; die Fragen sind **bis spätestens am Donnerstag vor der Fragestunde schriftlich beim Parlamentsdienst einzureichen** und dürfen **nicht mehr als 1'000 Zeichen** umfassen (Art. 103 Abs. 2 der Organisationsverordnung des Stadtparlaments). Jedes Mitglied reicht **eine** Frage ein.
    - **Fragen werden jederzeit gesammelt, ohne angesetzte Fragestunde.** Wem etwas auffällt, trägt es ein («+ Neue Frage»); die Fraktion nutzt es später in irgendeiner Fragestunde. Die Seite führt **alle Fragen**, die neueste zuoberst, und jede sagt, welcher Fragestunde sie zugeteilt ist («noch keiner zugeteilt», solange keine gewählt ist).
    - **Eine Fragestunde ist freiwillig und wird mit ihrem Datum angelegt** («+ Neue Fragestunde»). Titel («Fragestunde vom 2. März 2026») und **Frist** (der Donnerstag davor) ergeben sich daraus; beide lassen sich überschreiben. Die angesetzten Fragestunden stehen über den Fragen, jede mit Datum, Titel, Frist und der Zahl der ihr zugeteilten Fragen.
    - **Die Zuteilung geschieht im Feld «Fragestunde» der Frage** und lässt sich jederzeit ändern oder lösen.
    - **Jedes Fraktionsmitglied trägt seine Fragen selbst ein.** Zu einer Frage gehören: **wer sie eingebracht hat** (vorbelegt mit dem angemeldeten Mitglied), die **Frage** selbst, ein **Kommentar** für die Fraktionssitzung und **Notizen** — mit demselben Editor, denselben Versionen und demselben Wiederherstellen wie am Geschäft und am Vorstoss.
    - **Die Zeichenzahl steht neben dem Feld** und ist im Verhältnis zur Grenze angegeben («842 / 1000 Zeichen»). Über der Grenze meldet die Maske «bitte kürzen», und **Speichern bleibt gesperrt**; auch der Server nimmt eine zu lange Frage nicht an und nennt in seiner Meldung die Länge, die Grenze und den Artikel der Organisationsverordnung.
    - **In der Fraktionssitzung wird jede Frage besprochen, angepasst und zugeteilt.** Der **Status** einer Frage ist «Neu», «Besprochen», «Eingereicht» oder «Zurückgezogen». Wer sie **einreicht**, wird zugeteilt, sobald die Fraktion die Frage stellt — meist die Person, die sie eingebracht hat, sonst jemand anderes, weil jedes Mitglied nur eine Frage einreicht. Solange niemand zugeteilt ist, steht «noch nicht zugeteilt».
    - **Ist einem Mitglied mehr als eine Frage zugeteilt, meldet es die Seite** mit Namen — die Fraktion muss umverteilen.
    - **Der Verweis auf das Geschäft des Parlaments** steht auf der Karte, sobald es abgerufen ist: Das Parlament führt jede Fragestunde als eigenes Geschäft («Fragestunde vom 2. März 2026»), veröffentlicht es aber erst kurz vorher — die Fraktion sammelt ihre Fragen längst davor. Die Verknüpfung wird deshalb nachgeholt, sobald das Geschäft da ist.
    - **Jede Änderung speichert sofort** (wie bei Vorstössen und Geschäften), und alle sehen sie **in Echtzeit**; nur beim Anlegen sammelt die Maske die Eingaben bis zum «Speichern».

## Bedienelemente im Detail

Dieser Abschnitt beschreibt jedes einzelne Bedienelement jeder Ansicht und jedes Dialogs: seine Position, seine Beschriftung, seine Art, ob es ein Pflichtfeld ist, womit es vorbelegt wird, welche Auswahlwerte in welcher Reihenfolge zur Verfügung stehen und woher diese stammen. Beschrieben werden ausserdem das dynamische Verhalten — was ein Element ein- oder ausblendet, sperrt oder verändert — sowie der genaue Zeitpunkt, zu dem eine Eingabe gespeichert wird.

### Ansicht «Geschäfte»

#### Suchfeld (in der Navigationsspalte, zuoberst)

1. **Suchfeld** — Beschriftung «Suche», Platzhalter «Nr. oder Titel».
    - Art: Textfeld mit Löschknopf am rechten Rand (Kreuz-Symbol).
    - Pflichtfeld: nein.
    - Vorbelegung: leer (kein gespeicherter Wert, bei jedem Öffnen der Ansicht leer).
    - Dynamisches Verhalten: filtert die Liste bei jedem Tastendruck sofort; verglichen wird gross-/kleinschreibungsunabhängig gegen Titel **und** Nummer des Geschäfts. Der Löschknopf erscheint nur, solange etwas eingegeben ist; ein Klick darauf leert das Feld und hebt die Einschränkung sofort auf.
    - Speicherung: keine — reine Anzeigeeinschränkung, kein Serverzugriff.
    - Gesperrt: nie.

#### Filterbereich

Er steht in der Navigationsspalte, unterhalb der Ansichtenliste.

1. **Entscheidungsbedarf** — Beschriftung «Entscheidungsbedarf».
    - Art: Auswahlliste, nicht leerbar (es ist immer genau ein Wert gewählt).
    - Pflichtfeld: ja, im Sinne von «immer gesetzt».
    - Auswahlwerte in dieser Reihenfolge: «Alle», «Nur Entscheid nötig», «Nur ohne offenen Entscheid». Die Liste ist fest vorgegeben und hängt nicht von den Daten ab.
    - Vorbelegung: «Alle». Ausnahme: Ist der Fraktionssitzungsmodus eingeschaltet, wird beim Öffnen der Ansicht automatisch auf «Nur Entscheid nötig» umgestellt.
    - Dynamisches Verhalten: Jede Änderung lädt die Geschäftsliste **neu vom Server** (die Einschränkung wird nicht lokal, sondern beim Laden angewendet). «Entscheid nötig» bedeutet: Es gibt noch keinen Fraktionsbeschluss, oder die Quelle wurde nach dem letzten Fraktionsbeschluss aktualisiert.
    - Speicherung: keine Datenänderung; wirkt sofort bei Auswahl.
    - Gesperrt: nie.
2. **Status** — Beschriftung «Status», Platzhalter «Alle».
    - Art: Mehrfachauswahl (gewählte Einträge erscheinen als entfernbare Marken; die Liste bleibt nach einer Auswahl offen, bereits gewählte Einträge verschwinden aus der Auswahlliste).
    - Pflichtfeld: nein.
    - Vorbelegung: nichts gewählt (Platzhalter «Alle»).
    - Auswahlwerte: alle Status, die in den aktuell geladenen Geschäften tatsächlich vorkommen — leere Status werden weggelassen, jeder Status erscheint genau einmal, sortiert nach dem Originalwert (alphabetisch). Angezeigt wird die gekürzte Schreibweise gemäss den in der Verwaltung hinterlegten Kürzelregeln, gespeichert und verglichen wird der volle Originalwert.
    - Dynamisches Verhalten: Ist **genau ein** Status gewählt, verschwindet die Tabellenspalte «Status» (sie wäre redundant); bei keinem oder mehreren gewählten Status ist sie wieder da.
    - Speicherung: keine; wirkt sofort, ohne Neuladen vom Server.
    - Gesperrt: nie.
3. **Typ** — Beschriftung «Typ», Platzhalter «Alle».
    - Art: Mehrfachauswahl.
    - Pflichtfeld: nein.
    - Vorbelegung: nichts gewählt.
    - Auswahlwerte: alle Typen, die in den geladenen Geschäften vorkommen (ohne Leerwerte, ohne Doppelte), alphabetisch sortiert.
    - Dynamisches Verhalten: schränkt die Liste sofort ein; mehrere Typen wirken als «oder».
    - Speicherung: keine.
    - Gesperrt: nie.
4. **Zuständigkeit** — Beschriftung «Zuständigkeit», Platzhalter «Alle».
    - Art: Mehrfachauswahl.
    - Pflichtfeld: nein.
    - Vorbelegung: nichts gewählt.
    - Auswahlwerte: **nur die Personen, die an mindestens einem geladenen Geschäft tatsächlich als hauptzuständig eingetragen sind** — nicht jedes Mitglied (Grundprinzip: der Filter bietet genau die vorkommenden Werte, sonst führte eine Wahl ins Leere). Reihenfolge: zuerst alle **aktiven** Mitglieder alphabetisch, danach alle inaktiven bzw. nicht mehr vorhandenen Personen alphabetisch. Gibt es Geschäfte **ohne** Hauptzuständige, steht zusätzlich **«Nicht zugewiesen»** an erster Stelle und wählt genau diese Geschäfte aus (auch der leere Wert ist ein realer Wert der Daten).
    - Dynamisches Verhalten: Es wird gegen die hauptzuständige Person des Geschäfts verglichen; mehrere gewählte Personen wirken als «oder».
    - Speicherung: keine.
    - Gesperrt: nie.
5. **Beschluss** — Beschriftung «Beschluss», Platzhalter «Alle».
    - Art: Mehrfachauswahl.
    - Pflichtfeld: nein.
    - Vorbelegung: nichts gewählt.
    - Auswahlwerte: alle Beschlüsse, die bei den geladenen Geschäften als letzter gültiger Beschluss vorkommen, alphabetisch nach Beschlusstext sortiert. Gibt es mindestens ein Geschäft **ohne** erfassten Beschluss, steht zusätzlich «—» an **erster** Stelle und wählt genau diese Geschäfte aus.
    - Dynamisches Verhalten: schränkt die Liste sofort ein.
    - Speicherung: keine.
    - Gesperrt: nie.
6. **Priorität** — Beschriftung «Priorität», Platzhalter «Alle».
    - Art: Mehrfachauswahl.
    - Pflichtfeld: nein.
    - Vorbelegung: nichts gewählt.
    - Auswahlwerte: **nur die Prioritätsstufen, die bei den geladenen Geschäften tatsächlich gesetzt sind**, in der festen Reihenfolge «Hoch», «Mittel», «Tief» — Stufen ohne ein einziges Geschäft entfallen. Gibt es Geschäfte **ohne** gesetzte Priorität, steht zusätzlich **«Undefiniert»** an erster Stelle und wählt genau diese aus (auch die fehlende Priorität ist ein realer Wert der Daten).
    - Dynamisches Verhalten: Der Filter unterscheidet «Undefiniert» (keine Priorität gesetzt) von «Mittel» (ausdrücklich gesetzt) — gefiltert wird nach dem gespeicherten Rohwert. (Für die farbliche Hervorhebung der Zeile gilt eine fehlende Priorität weiterhin als «Mittel».)
    - Speicherung: keine.
    - Gesperrt: nie.
7. **Erledigte anzeigen** — Beschriftung «Erledigte anzeigen».
    - Art: Schalter (Ein/Aus).
    - Pflichtfeld: nein.
    - Vorbelegung: ausgeschaltet.
    - Dynamisches Verhalten: Jedes Umschalten lädt die Liste **neu vom Server**. Ausgeschaltet werden Geschäfte ausgeblendet, deren Status «erledigt», «abgeschlossen» oder «aufgehoben» enthält (z.B. auch «Durch Rechtsmittelinstanz aufgehoben»).
    - Speicherung: keine Datenänderung.
    - Gesperrt: nie.
8. **Filter zurücksetzen** — Beschriftung «Filter zurücksetzen».
    - Art: Knopf über die ganze Breite, zurückhaltend dargestellt.
    - Wirkung: leert Suchfeld, Status, Priorität, Typ, Zuständigkeit und Beschluss, stellt «Entscheidungsbedarf» auf «Alle», schaltet «Erledigte anzeigen» aus und lädt die Liste neu vom Server.
    - Gesperrt: nie.

#### Kopfzeile der Ansicht

1. **Titel** — «Geschäfte» (feste Beschriftung).
2. **Anzahl-Marke** — Zahl ohne Beschriftung; zeigt, wie viele Geschäfte nach allen Filtern und der Suche übrig bleiben; aktualisiert sich bei jeder Filter- oder Sucheingabe sofort.
3. **«+ Eigenes Geschäft»** — Knopf, hervorgehobene Darstellung.
    - Wirkung: legt sofort ein neues, leeres Geschäft mit dem Typ «Eigenes Geschäft» an, lädt die Liste neu und öffnet unmittelbar die vollständige Geschäfts-Detailmaske dieses neuen Geschäfts.
    - Besonderheit: Wird die Maske geschlossen, ohne dass ein Titel eingegeben wurde, wird das leere Geschäft wieder entfernt — «anlegen und abbrechen» hinterlässt also keine Leereinträge.
    - Gesperrt: solange das Anlegen läuft (Doppelklick erzeugt kein zweites Geschäft). Schlägt das Anlegen fehl, erscheint die Meldung «Eigenes Geschäft konnte nicht erstellt werden: …».

#### Ladeanzeige und Leerzustand

1. **Ladeanzeige** — während die Liste geladen wird, erscheint anstelle von Tabelle und Karten ein Ladekreis. Sie erscheint bei jedem Neuladen (Filterwechsel, «Erledigte anzeigen», Zurücksetzen, Aktualisierung nach einer Änderung).
2. **Leerzustand** — Text «Keine Geschäfte gefunden»; erscheint, wenn nach Suche und Filtern kein Geschäft übrig bleibt (nicht während des Ladens).

#### Tabellendarstellung (breite Ansicht)

Die Tabelle erscheint, solange die Inhaltsspalte breit genug ist; wird sie schmäler, wechselt die Darstellung automatisch auf Karten (siehe nächster Abschnitt).

Spalten von links nach rechts:

1. **«Nr.»** — sortierbar (Klick auf die Spaltenüberschrift).
    - Inhalt je Zeile: Geschäftsnummer fett, darunter das Datum in Kurzform (TT.MM.JJ) und der Typ.
    - Sortierung: Klick sortiert aufsteigend, erneuter Klick auf dieselbe Spalte kehrt die Richtung um. Die Nummern werden dabei so verglichen, dass «2026.9» vor «2026.10» steht.
2. **«Titel»** — sortierbar.
    - Inhalt je Zeile: ein Pfeilsymbol «↗» mit dem Hinweis «Extern öffnen» (nur wenn zum Geschäft ein Link auf die Parlamentswebseite hinterlegt ist; öffnet ihn in einem neuen Fenster, ohne die Detailmaske zu öffnen), danach der Titel, danach — falls vorhanden — die Einreicher, mit Komma getrennt.
3. **«Prio»** — nicht sortierbar; enthält ein direkt bedienbares Auswahlfeld.
    - Art: Auswahlliste, leerbar, Platzhalter «—».
    - Vorbelegung: die am Geschäft gespeicherte Priorität; ist keine gesetzt, bleibt das Feld leer («—») — es wird **nicht** «Mittel» angezeigt, obwohl für Filterung und Einfärbung «Mittel» als Standard gilt.
    - Auswahlwerte, feste Reihenfolge: «Hoch», «Mittel», «Tief»; zusätzlich Leeren möglich.
    - Speicherung: sofort bei der Auswahl. Danach wird die ganze Liste neu geladen, damit Sortierung und Einfärbung stimmen.
    - Dynamisches Verhalten: «Hoch» hinterlegt die Zeile dezent farbig, «Tief» blendet sie abgeschwächt dar.
    - Gesperrt: nie. Ein Klick in dieses Feld öffnet **nicht** die Detailmaske.
4. **«Status»** — sortierbar; **bedingte Sichtbarkeit**: diese Spalte erscheint nur, wenn im Filter «Status» **nicht genau ein** Status gewählt ist.
    - Inhalt: der Status in gekürzter Schreibweise als farbige Marke; der vollständige Status erscheint als Hinweis beim Darüberfahren. Farbgebung: «pendent»/«offen»/«laufend» als offen, «erledigt»/«abgeschlossen»/«aufgehoben» als erledigt, «abgelehnt»/«zurückgezogen» als abgelehnt, alles Übrige neutral.
5. **«Zuständig»** — nicht sortierbar; enthält ein direkt bedienbares Mehrfachauswahlfeld.
    - Art: Mehrfachauswahl, leerbar, Platzhalter «—».
    - Vorbelegung: die am Geschäft eingetragenen zuständigen Personen. Personen, die nicht mehr in der Auswahlliste stehen, bleiben trotzdem als Marke sichtbar.
    - Auswahlwerte: **nur aktive** Fraktionsmitglieder, die zusätzlich ein Benutzerkonto haben; alphabetisch nach vollem Namen sortiert. Bereits gewählte Personen verschwinden aus der Auswahlliste.
    - Speicherung: sofort bei jeder Änderung. Die erste gewählte Person gilt als Hauptzuständigkeit; ist die bisherige Hauptperson weiterhin ausgewählt, bleibt sie es. Danach wird die Liste neu geladen.
    - Gesperrt: nie. Ein Klick in dieses Feld öffnet **nicht** die Detailmaske.
6. **«Beschluss»** — nicht sortierbar; enthält das Beschlussfeld (Beschreibung des Bedienverhaltens siehe «Geteilte Bausteine → Beschlussfeld»).
    - Vorbelegung: der letzte gültige Beschluss des Geschäfts. Ist er als freier Text erfasst, erscheint dieser Text.
    - Auswahlwerte: die für dieses Geschäft je nach Typ und Status zulässigen Beschlüsse (z.B. «Zustimmen», «Ablehnen», «Stimmfreigabe», «Miteinreichen als Fraktion», «Miteinreichen einzelne Personen», «Überweisung befürworten», «Überweisung ablehnen», «Kenntnisnahme positiv», «Kenntnisnahme negativ», «Nachbericht verlangen», «Erheblich erklären», «Abschreiben»). Die Liste unterscheidet sich also von Geschäft zu Geschäft.
    - Speicherung: sofort, sobald die Eingabe abgeschlossen ist. Leeren des Feldes nimmt den Beschluss zurück. Danach wird die Liste neu geladen.
    - Gesperrt: nie in der Übersicht. Ein Klick in dieses Feld öffnet **nicht** die Detailmaske.

Verhalten der Tabellenzeile insgesamt:

7. **Zeile anklicken** öffnet die Geschäfts-Detailmaske. Dasselbe leisten die Tasten «Eingabe» und «Leertaste», wenn die Zeile den Fokus hat. Klicks und Tastendrücke innerhalb der drei Bedienfelder (Prio, Zuständig, Beschluss) öffnen die Maske nicht.
8. **Einfärbung der Zeile**: Priorität «Hoch» dezent hervorgehoben, «Tief» abgeschwächt, als gelöscht markierte Geschäfte insgesamt abgeschwächt.
9. **Standardsortierung**: nach Datum, neueste zuerst — bis eine Spaltenüberschrift angeklickt wird.

#### Kartendarstellung (schmale Ansicht)

Erscheint automatisch, wenn die Inhaltsspalte zu schmal für die Tabelle wird. Sie zeigt dieselben Geschäfte in derselben Reihenfolge. Aufbau einer Karte von oben nach unten:

1. **Kopfzeile links**: Pfeilsymbol «↗» mit Hinweis «Extern öffnen» (nur bei hinterlegtem Link), danach die Geschäftsnummer; steht keine Nummer zur Verfügung, erscheint «Ohne Nummer».
2. **Titel** des Geschäfts.
3. **Statusmarke** rechts in der Kopfzeile: gekürzter Status, oder «—», wenn kein Status vorhanden ist. Sie ist hier **immer** sichtbar, unabhängig vom Statusfilter.
4. **Wertepaar «Typ»** — Anzeige, nicht bearbeitbar; «—», wenn leer.
5. **Wertepaar «Datum»** — Anzeige in Schweizer Schreibweise; «—», wenn leer.
6. **Wertepaar «Zuständigkeit»** — Anzeige der hauptzuständigen Person; «—», wenn leer.
7. **Priorität** — dasselbe Auswahlfeld wie in der Tabelle, hier zusätzlich mit sichtbarer Beschriftung «Priorität» und Platzhalter «—»; speichert sofort.
8. **Beschluss** — dasselbe Beschlussfeld wie in der Tabelle, Platzhalter «—»; speichert sofort.
9. **Karte anklicken** (oder Eingabe/Leertaste bei Fokus) öffnet die Detailmaske; Klicks auf Priorität und Beschluss nicht.
10. Einfärbung nach Priorität und «gelöscht» wie bei der Tabellenzeile.

#### Detailfenster aus der Liste heraus

1. **Kopfzeile des Fensters** — enthält ausser dem Schliessknopf nichts.
2. **Schliessknopf «✕»** — Hilfetext «Dialog schliessen». Wirkung: schliesst das Fenster. Er erscheint **nur beim Bearbeiten**; beim Anlegen stehen stattdessen unten «Speichern» und «Abbrechen».
3. **Klick neben das Fenster** (auf die abgedunkelte Fläche) schliesst es mit derselben Wirkung.
4. Nach jedem Speichern in der Maske wird die Liste im Hintergrund aktualisiert.

---

### Geschäfts-Detailmaske

Die Maske ist für alle Geschäfte gleich aufgebaut. Bei **selbst angelegten** Geschäften («+ Eigenes Geschäft») sind Titel, Beschreibungstext, Typ, Status, Datum und Kommission zusätzlich bearbeitbar; bei Geschäften von der Parlamentswebseite sind sie reine Anzeige, weil sie beim nächsten Abgleich von der Quelle überschrieben würden.

#### Zustände beim Öffnen

1. Solange geladen wird: Text «Lade Geschäft...».
2. Schlägt das Laden fehl: Text «Geschäft konnte nicht geladen werden.» — es erscheint kein Formular.

#### Kopfbereich

1. **Kicker** — die Geschäftsnummer; bei einem selbst angelegten Geschäft ohne Nummer steht dort «Eigenes Geschäft».
2. **Titel** —
    - Bei selbst angelegten Geschäften: Textfeld, Platzhalter «Titel des Geschäfts», Beschriftung für Sprachausgabe «Titel». Pflichtfeld faktisch ja: bleibt es leer, wird das Geschäft beim Schliessen verworfen. Vorbelegung: der gespeicherte Titel (bei einem frisch angelegten Geschäft leer). Speicherung: beim Verlassen des Feldes bzw. sobald die Änderung abgeschlossen ist — mit Rückmeldung «Gespeichert» bzw. «Geschäft konnte nicht gespeichert werden: …».
    - Sonst: reine Anzeige des Titels.
3. **Fraktionsstatus-Marke** rechts — Text «Offen», «Neu zu entscheiden» oder «Entschieden»; farblich: «Neu zu entscheiden» wie offen, «Entschieden» wie erledigt, «Offen» neutral. Sie wird abgeleitet: kein Beschluss ⇒ «Offen»; Quelle nach dem letzten Beschluss aktualisiert ⇒ «Neu zu entscheiden»; sonst «Entschieden».
    - Der Titel nutzt die ganze Zeilenbreite; die Fraktionsstatus-Marke steht rechts daneben, nie darunter.
4. **Beschreibungstext** — **bedingte Sichtbarkeit**: nur bei selbst angelegten Geschäften, direkt unter dem Titel (vor den öffentlichen Informationen). Formatierter Textbereich mit Werkzeugleiste (siehe «Geteilte Bausteine → Formatierter Textbereich»), Platzhalter «Worum geht es?». Kein Pflichtfeld. Vorbelegung: der gespeicherte Text, bei einem frisch angelegten Geschäft leer. Speicherung beim Verlassen des Editors. Bei Geschäften von der Parlamentswebseite entfällt er — der Text steht dort auf der Quellseite.

#### Bereich «Öffentliche Informationen»

Tabelle mit Beschriftung links und Wert rechts, von oben nach unten:

1. **«Nummer»** — Anzeige, nie bearbeitbar.
2. **«Typ»** — bei selbst angelegten Geschäften Auswahlliste, deren Werte der Administrator pflegt (Verwaltungsbereich «Typen für eigene Geschäfte»). Sonst Anzeige. Kein Pflichtfeld. Vorbelegung: gespeicherter Wert, bei neu angelegten Geschäften «Eigenes Geschäft». Speicherung sofort bei der Auswahl, Rückmeldung «Gespeichert». Ist keine Typenliste konfiguriert, steht dort nur «Eigenes Geschäft».
3. **«Status»** — bei selbst angelegten Geschäften dasselbe Widget wie beim Beschluss: eine Auswahl aus den Werten, die in der Datenbank bereits vorkommen, jederzeit mit einem frei eingetippten Wert überschreibbar. Sonst Anzeige des Status in gekürzter Schreibweise. Kein Pflichtfeld. Speicherung sofort.
4. **«Fraktionsstatus»** — Anzeige «Offen» / «Neu zu entscheiden» / «Entschieden», nie bearbeitbar (wird abgeleitet).
5. **«Datum»** — bei selbst angelegten Geschäften Datumsfeld (Sprachausgabe-Beschriftung «Datum»), sonst Anzeige in Schweizer Schreibweise. Kein Pflichtfeld. **Vorbelegung beim Anlegen: das heutige Datum.** Speicherung sofort. Ein unsinniges Datum (nicht JJJJ-MM-TT) wird mit einer Meldung abgewiesen.
6. **«Kommission»** — **bedingte Sichtbarkeit**: bei selbst angelegten Geschäften eine leerbare Auswahlliste (Platzhalter «—»), die **nur aktive** Kommissionen in gekürzter Schreibweise anbietet (gespeichert wird der volle Name); höchstens eine ist wählbar, «keine» (leer) ist gültig. Bei Geschäften von der Parlamentswebseite nur angezeigt, wenn eine Kommission hinterlegt ist (dann in gekürzter Schreibweise). Speicherung sofort bei der Auswahl, auch beim Leeren.
7. **«Einreicher»** — **bedingte Sichtbarkeit**: nur, wenn zum Geschäft mindestens eine einreichende Person hinterlegt ist. Anzeige: Name, dahinter in Klammern die Rolle, mehrere durch Komma getrennt. Nie bearbeitbar.
8. **«Letzte externe Änderung»** — Zeitpunkt der letzten Änderung an der Quelle in Schweizer Schreibweise; «—», wenn unbekannt. Nie bearbeitbar.
9. **«Letzte Fraktionsentscheidung»** — Zeitpunkt des letzten Fraktionsbeschlusses; «—», wenn noch keiner gefasst wurde. Nie bearbeitbar.
10. **«Link»** — **bedingte Sichtbarkeit**: nur wenn ein Link hinterlegt ist. Verweis mit dem Text «Auf Parlamentswebseite öffnen ↗», öffnet ein neues Fenster.

#### Bereich «Fraktionsinterne Bearbeitung»

1. **«Priorität»**
    - Art: Auswahlliste, leerbar, Platzhalter «—».
    - Pflichtfeld: nein.
    - Vorbelegung: die gespeicherte Priorität des Geschäfts; ist keine gesetzt, bleibt das Feld leer.
    - Auswahlwerte, feste Reihenfolge: «Hoch», «Mittel», «Tief».
    - Speicherung: sofort bei der Auswahl, auch beim Leeren. Rückmeldung «Priorität gespeichert» bzw. «Priorität konnte nicht gespeichert werden: …».
    - Gesperrt: nie.
2. **«Zuständigkeit»**
    - Art: Mehrfachauswahl, leerbar, Platzhalter «—»; gewählte Personen erscheinen als entfernbare Marken und verschwinden aus der Auswahlliste.
    - Pflichtfeld: nein.
    - Vorbelegung: die am Geschäft eingetragenen zuständigen Personen; beim Anlegen eines neuen eigenen Geschäfts ist die anlegende Person vorausgewählt, sofern sie Fraktionsmitglied mit Benutzerkonto ist (vor dem Speichern noch änderbar).
    - Auswahlwerte und Reihenfolge: zuerst alle **aktiven** Fraktionsmitglieder **mit Benutzerkonto**, alphabetisch nach vollem Namen; danach alle **inaktiven** Mitglieder mit Benutzerkonto, ebenfalls alphabetisch, jeweils mit dem Zusatz «(inaktiv)» hinter dem Namen. Mitglieder ohne Benutzerkonto stehen nicht zur Wahl.
    - Hinweistext direkt unter dem Feld: «Falls mehrere Personen ausgewählt sind, wird die erste Auswahl intern als Hauptzuständigkeit geführt.»
    - Dynamisches Verhalten: Die Hauptzuständigkeit wird automatisch auf die erste ausgewählte Person gesetzt, sobald die bisherige Hauptperson nicht mehr ausgewählt ist.
    - Speicherung: sofort bei jeder Änderung; unveränderte Auswahl löst kein Speichern aus. Rückmeldung «Zuständigkeiten gespeichert» bzw. «Fehler beim Speichern der Zuständigkeiten». Der Vorgang wird zusätzlich in der Aktionszeitleiste festgehalten.
    - Gesperrt: nie.
3. **«Beschluss erfassen»**
    - Art: Beschlussfeld — Texteingabe mit Vorschlagsliste (Bedienverhalten siehe «Geteilte Bausteine → Beschlussfeld»).
    - Pflichtfeld: nein.
    - Vorbelegung: der letzte gültige Beschluss; als freier Text erfasste Beschlüsse erscheinen als Text.
    - Auswahlwerte: die für Typ und Status dieses Geschäfts zulässigen Beschlüsse, in der vom System vorgegebenen Reihenfolge. Je nach Geschäftsart sind das z.B. für eine Motion «Zustimmen», «Ablehnen», «Stimmfreigabe», «Miteinreichen als Fraktion», «Miteinreichen einzelne Personen», «Überweisung befürworten», «Überweisung ablehnen», «Erheblich erklären», «Abschreiben»; für ein Postulat zusätzlich «Kenntnisnahme positiv», «Kenntnisnahme negativ», «Nachbericht verlangen»; für Interpellationen, schriftliche Anfragen und Berichte «Zustimmen», «Ablehnen», «Stimmfreigabe», «Kenntnisnahme positiv», «Kenntnisnahme negativ»; für Vorlagen, Wahlen und alles Übrige «Zustimmen», «Ablehnen», «Stimmfreigabe»; für Initiativen zusätzlich «Überweisung befürworten» und «Überweisung ablehnen». Bei einem erledigten Geschäft kommen «Kenntnisnahme positiv» und «Kenntnisnahme negativ» in jedem Fall dazu. Ein frei eingegebener Text ausserhalb der Liste ist erlaubt.
    - Speicherung: sofort, sobald die Eingabe abgeschlossen ist (Auswahl aus der Liste, Verlassen des Feldes oder fünf Sekunden ohne weitere Eingabe). Rückmeldung «Beschluss gespeichert». Der Fraktionsstatus wechselt damit auf «Entschieden». Änderungen innerhalb derselben Eingabe aktualisieren denselben Eintrag; nach dem Verlassen des Feldes erzeugt die nächste Änderung einen **neuen** Eintrag in der Zeitleiste, sodass die Beschlussgeschichte erhalten bleibt.
    - Leeren des Feldes nimmt einen bestehenden Beschluss zurück: Rückmeldung «Beschluss zurückgenommen», der Fraktionsstatus fällt auf «Offen» zurück, und die Rücknahme erscheint in der Aktionszeitleiste.
    - **Gesperrt**, wenn der Fraktionssitzungsmodus eingeschaltet ist und die angemeldete Person nicht die Protokollführung (oder deren aktive Stellvertretung) innehat. Dann erscheint unter dem Feld der Hinweis: «Im Fraktionssitzungsmodus darf nur der Protokollführer Beschlüsse erfassen.»
4. **«Votum im Rat»**
    - Art: formatierter Textbereich mit Werkzeugleiste (siehe «Geteilte Bausteine → Formatierter Textbereich»), Platzhalter «Wortlaut des Votums».
    - Pflichtfeld: nein.
    - Vorbelegung: der zuletzt erfasste, noch nicht archivierte Wortlaut; sonst leer.
    - **Gesperrt für alle ausser der für dieses Geschäft zuständigen Person.** Ist man nicht zuständig, bleibt der Wortlaut lesbar, und darunter steht der Hinweis: «Das Votum erfasst die für dieses Geschäft zuständige Person.»
    - Speicherung: kurz nach der letzten Eingabe automatisch sowie sofort beim Verlassen des Feldes. In der Werkzeugleiste erscheint der Stand («Gespeichert» bzw. «Fehler beim Speichern»).
    - **Verweis auf die Druckfassung** in der Werkzeugleiste (Hinweistext «Votum als PDF drucken»): erscheint, sobald ein Wortlaut erfasst ist, öffnet die Druckansicht in einem eigenen Fenster und löst dort den Drucken-Dialog aus.
        - Die Druckansicht übernimmt die Formatierung des Wortlauts (Absätze, Überschriften, Fett-/Kursivschrift, Aufzählungen, Zitate, Verweise), gibt aber ausschliesslich diese Gestaltungselemente aus. Alles Ausführbare — eingebettete Skripte, an Elementen hängender Code für Ereignisse, Verweise mit ausführbarem Ziel — erscheint nicht im Ausdruck; der zugehörige Text bleibt erhalten.
    - **Knopf «Votum archivieren»** — erscheint nur für die zuständige Person und nur, wenn ein Wortlaut erfasst ist. Er sichert zuerst offene Eingaben, legt das Votum als historischen Eintrag in der Aktionszeitleiste ab und leert das Feld für ein neues Votum. Rückmeldung: «Votum archiviert».
5. **«Notizen»**
    - Enthält die Notizenliste des Geschäfts (Bedienelemente siehe «Geteilte Bausteine → Notizenliste»).
    - Darunter der aufklappbare Bereich **«Sitzungsnotizen»**: standardmässig **zugeklappt**; hinter dem Wort steht die Anzahl der aktiven Sitzungsnotizen (die Zahl erscheint nur, wenn es mindestens eine gibt). Aufgeklappt enthält er eine zweite, gleich bedienbare Notizenliste, die ausschliesslich die in Sitzungen erfassten und am Geschäft haftenden Notizen verwaltet.
6. **«Dokumente zum Geschäft»**
    - Enthält den Dokumentenbereich (siehe «Geteilte Bausteine → Dokumente»). Der angezeigte Ablagepfad lautet hier «Fraktion/20_Geschäfte/‹Jahr›/‹Geschäftsnummer›-*», wobei das Jahr aus der Geschäftsnummer stammt. Der Dateiname wird mit der Geschäftsnummer vorangestellt.
    - Dynamisches Verhalten: Die Bedienelemente zum Anlegen und Hochladen erscheinen erst, wenn das Geschäft eine Nummer hat — bei einem neu angelegten eigenen Geschäft ohne Nummer bleiben sie aus.

#### Bereich «Aktionszeitleiste»

Siehe «Geteilte Bausteine → Aktionszeitleiste». Sie ist in der Detailmaske immer vorhanden. Ist die Maske aus einem Sitzungstraktandum heraus geöffnet, erscheinen zusätzlich die dort erfassten Traktandennotizen; ein Klick darauf springt zur Sitzung.

#### Bereich «Verknüpfte Vorstösse»

Der ganze Bereich erscheint nur, wenn mindestens ein Vorstoss mit diesem Geschäft verknüpft ist.

1. **Überschrift** «Verknüpfte Vorstösse».
2. Je Vorstoss eine **Zwischenüberschrift** mit dem Titel des Vorstosses; ist eine Art erfasst, folgt « · » und die Art.
3. **Wertepaar «Haltung»** — **bedingte Sichtbarkeit**: nur wenn beim Vorstoss ein Beschluss (Haltung) erfasst ist. Reine Anzeige.
4. **Wertepaar «Zuständigkeit»** — **bedingte Sichtbarkeit**: nur wenn dem Vorstoss mindestens eine Person zugeordnet ist; mehrere Namen mit Komma getrennt. Reine Anzeige.
5. **Notizen des Vorstosses** — je Notiz Name der verfassenden Person und der formatierte Notiztext. Reine Anzeige; gelöschte Notizen erscheinen hier nicht. In diesem Bereich lässt sich nichts bearbeiten.

---

### Ansicht «Vorstösse»

#### Suchfeld (in der Navigationsspalte, zuoberst)

1. **Suchfeld** — Beschriftung «Suche», Platzhalter «Titel, Art oder Zuständigkeit».
    - Art: Textfeld mit Löschknopf (Kreuz-Symbol), der nur bei nicht leerem Feld erscheint.
    - Pflichtfeld: nein. Vorbelegung: leer.
    - Dynamisches Verhalten: schränkt die Karten sofort ein; gesucht wird gross-/kleinschreibungsunabhängig in Titel, Art und den Namen der zuständigen Personen.
    - Speicherung: keine.

#### Filterbereich

Er steht in der Navigationsspalte.

1. **Herkunft** — Beschriftung «Herkunft».
    - Art: Auswahlliste, nicht leerbar.
    - Vorbelegung: «Alle».
    - Auswahlwerte: «Alle» und danach — in der festen Reihenfolge «Eigene», «Fremde» — **nur die Herkünfte, die bei den geladenen Vorstössen tatsächlich vorkommen** (Grundprinzip: keine leere Kategorie als Ballast).
    - Wirkung: sofort, ohne Neuladen.
2. **Status** — Beschriftung «Status».
    - Art: Auswahlliste, nicht leerbar.
    - Vorbelegung: «Alle».
    - Auswahlwerte: «Alle» und danach — in der festen Reihenfolge «Neu», «Entwurf», «Bereit», «Eingereicht», «Erledigt», «Pausiert» — **nur die Status, die bei den geladenen Vorstössen tatsächlich vorkommen**.
    - Wirkung: sofort, ohne Neuladen.

Ein Knopf «Filter zurücksetzen» existiert in dieser Ansicht nicht; die Filter werden über den Wert «Alle» aufgehoben.

#### Kopfzeile der Ansicht

1. **Titel** — «Vorstösse».
2. **Anzahl-Marke** — Zahl der nach Suche und Filtern sichtbaren Vorstösse.
3. **«+ Neuer Vorstoss»** — Knopf, hervorgehobene Darstellung.
    - Wirkung: legt sofort einen leeren Vorstoss an und öffnet damit dasselbe vollständige Formular wie das Bearbeiten (Fenstertitel dann «Neuer Vorstoss»). Als Zuständigkeit ist die angemeldete Person vorbelegt, sofern sie Fraktionsmitglied ist; sonst bleibt die Zuständigkeit leer.
    - Gesperrt: solange das Anlegen läuft. Fehlermeldung: «Vorstoss konnte nicht erstellt werden: …».

#### Ladeanzeige und Leerzustand

1. **Ladeanzeige** — Ladekreis, solange die Vorstösse geladen werden.
2. **Leerzustand** — Text «Keine Vorstösse vorhanden»; erscheint, wenn nach Suche und Filtern kein Vorstoss übrig bleibt.

#### Kartendarstellung

Die Vorstösse erscheinen ausschliesslich als Karten (keine Tabelle). Aufbau von oben nach unten:

1. **Kicker** — Herkunft im Klartext («Eigene» oder «Fremde»); ist eine Art erfasst, folgt « · » und die Art.
2. **Titel** — der Titel des Vorstosses; ohne Titel steht «Ohne Titel».
3. **Statusmarke** rechts — «Neu», «Entwurf», «Bereit», «Eingereicht», «Erledigt» oder «Pausiert». Farbgebung: «Erledigt» wie erledigt, «Neu» und «Entwurf» wie offen, alles Übrige neutral.
4. **Wertepaar «Zuständigkeit»** — die Namen der zuständigen Personen mit Komma getrennt; «—», wenn keine.
5. **Wertepaar «Herkunftsfraktion»** — **bedingte Sichtbarkeit**: nur bei Herkunft «Fremde». Anzeige des gekürzten Fraktionsnamens; «—», wenn keine gewählt.
6. **Wertepaar «Beschluss»** — **bedingte Sichtbarkeit**: nur bei Herkunft «Fremde» **und** wenn ein Beschluss erfasst ist.
7. **«Löschen»** — Knopf in Warnfarbe, unten rechts auf der Karte.
    - Wirkung: fragt zuerst zurück «Vorstoss ‹Titel› wirklich löschen?» (ohne Titel: «Vorstoss ‹Ohne Titel› wirklich löschen?»). Erst nach Bestätigung wird gelöscht und die Liste neu geladen. Fehlermeldung: «Vorstoss konnte nicht gelöscht werden: …».
    - Ein Klick auf «Löschen» öffnet die Bearbeitungsmaske **nicht**.
8. **Karte anklicken** (oder Eingabe/Leertaste bei Fokus) öffnet die Bearbeitungsmaske.
9. Einfärbung: Priorität «Hoch» hebt die Karte dezent hervor, «Tief» schwächt sie ab; Vorstösse ohne gesetzte Priorität gelten dabei als «Mittel».

#### Dialog «Neuer Vorstoss» / «Vorstoss bearbeiten»

Beide Fälle zeigen **exakt dieselben** Felder; unterschiedlich ist nur der Fenstertitel: «Neuer Vorstoss», solange der Vorstoss noch keinen Titel bekommen hat, sonst «Vorstoss bearbeiten». Jede Eingabe speichert sofort — es gibt weder «Speichern» noch «Abbrechen».

Kopfzeile:

1. **Fenstertitel** — «Neuer Vorstoss» oder «Vorstoss bearbeiten» (siehe oben).
2. **Schliessknopf «✕»** — Hilfetext «Dialog schliessen». Wirkung: schliesst das Fenster. Er erscheint **nur beim Bearbeiten**; dort wirkt auch ein Klick auf die Fläche neben dem Fenster gleich. Beim Anlegen stehen stattdessen unten «Speichern» und «Abbrechen», und ein Klick neben das Fenster bewirkt nichts.

Felder von oben nach unten:

1. **«Titel *»** — der Stern zeigt an, dass es sich um ein Pflichtfeld handelt.
    - Art: Textfeld, Platzhalter «Titel des Vorstosses».
    - Pflichtfeld: **ja**. Solange der Titel leer ist, wird **überhaupt nichts** gespeichert — auch keine andere Eingabe im Formular. Ein nie benannter Vorstoss wird beim Schliessen entfernt.
    - Vorbelegung: der gespeicherte Titel; bei einem neuen Vorstoss leer.
    - Speicherung: beim Abschluss der Eingabe (Verlassen des Feldes) und zusätzlich sofort bei «Eingabe»-Taste. Rückmeldung «Gespeichert» bzw. «Vorstoss konnte nicht gespeichert werden: …».
    - Dynamisches Verhalten: Sobald ein Titel gespeichert ist, wechselt der Fenstertitel von «Neuer Vorstoss» auf «Vorstoss bearbeiten», und der Vorstoss wird beim Schliessen nicht mehr verworfen.
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
    - **Dynamisches Verhalten**: Die Wahl «Fremde» blendet drei zusätzliche Felder ein — «Beschluss (Haltung zum fremden Vorstoss)», «Herkunft (fremde Fraktion)» und «Ansprechpartner». Ein Wechsel zurück auf «Eigene» blendet sie wieder aus; die bereits erfassten Werte bleiben gespeichert, sind aber nicht mehr sichtbar. Zusätzlich ändert sich der angezeigte Ablagepfad für Dokumente (siehe Feld «Dokument»).
    - Speicherung: sofort bei der Auswahl.
4. **«Status»** (zweites Feld der Dreiergruppe)
    - Art: Auswahlliste, nicht leerbar.
    - Vorbelegung: «Neu» bei einem neuen Vorstoss, sonst der gespeicherte Wert.
    - Auswahlwerte in dieser Reihenfolge: «Neu», «Entwurf», «Bereit», «Eingereicht», «Erledigt», «Pausiert».
    - Speicherung: sofort bei der Auswahl.
    - Dynamisches Verhalten: Wird der Vorstoss mit einem Geschäft verknüpft, springt der Status automatisch auf «Erledigt».
5. **«Priorität»** (drittes Feld der Dreiergruppe)
    - Art: Auswahlliste, leerbar, Platzhalter «—» (dasselbe Prioritäts-Widget wie beim Geschäft).
    - Pflichtfeld: nein.
    - Vorbelegung: die gespeicherte Priorität; bei neuem Vorstoss leer.
    - Auswahlwerte in dieser Reihenfolge: «Hoch», «Mittel», «Tief».
    - Speicherung: sofort, auch beim Leeren.
    - Dynamisches Verhalten: bestimmt die Hervorhebung der Karte in der Übersicht; wird der Vorstoss später mit einem Geschäft verknüpft, übernimmt das Geschäft diese Priorität.
6. **«Zuständigkeit»**
    - Art: Mehrfachauswahl, leerbar, Platzhalter «Personen wählen»; gewählte Personen erscheinen als entfernbare Marken und verschwinden aus der Auswahlliste.
    - Pflichtfeld: nein.
    - Vorbelegung: bei einem neuen Vorstoss die angemeldete Person, sofern sie Fraktionsmitglied ist; sonst die gespeicherten Personen. Personen, die nicht mehr zur Auswahl stehen, bleiben als Marke sichtbar.
    - Auswahlwerte: **nur aktive** Fraktionsmitglieder **mit Benutzerkonto**, alphabetisch nach vollem Namen.
    - Speicherung: sofort bei jeder Änderung.
7. **«Beschluss (Haltung zum fremden Vorstoss)»** — **bedingte Sichtbarkeit: nur bei Herkunft «Fremde»**.
    - Art: Texteingabe mit Vorschlagsliste (freier Text erlaubt), Platzhalter «Miteinreichen, Unterstützen, Ablehnen …».
    - Pflichtfeld: nein.
    - Vorbelegung: der gespeicherte Beschluss.
    - Vorschlagswerte in dieser Reihenfolge: «Miteinreichen», «Unterstützen», «Stimmfreigabe», «Ablehnen», «Ablehnungsantrag stellen». Jeder andere Text ist ebenfalls zulässig.
    - Speicherung: sofort bei Auswahl, Verlassen des Feldes oder nach fünf Sekunden ohne weitere Eingabe.
    - Dynamisches Verhalten: Der Wert erscheint auf der Karte in der Übersicht als Wertepaar «Beschluss».
8. **«Herkunft (fremde Fraktion)»** — **bedingte Sichtbarkeit: nur bei Herkunft «Fremde»**.
    - Art: Auswahlliste, leerbar, Platzhalter «Fraktion wählen».
    - Pflichtfeld: nein.
    - Vorbelegung: die gespeicherte Fraktion.
    - Auswahlwerte: **nur aktive** Fraktionen **ohne die eigene**, alphabetisch nach der angezeigten Bezeichnung. Angezeigt wird die gekürzte Bezeichnung (Abkürzung) gemäss den hinterlegten Kürzelregeln; gespeichert wird der volle Name.
    - **Dynamisches Verhalten**: Die Wahl bestimmt, welche Personen im Feld «Ansprechpartner» zur Auswahl stehen. Wird eine **andere** Fraktion gewählt, wird eine bereits getroffene Ansprechpartner-Auswahl **geleert** — die alten Personen gehören zu einer anderen Fraktion.
    - Speicherung: sofort bei der Auswahl (zusammen mit der geleerten Ansprechpartner-Liste).
9. **«Ansprechpartner»** — **bedingte Sichtbarkeit: nur bei Herkunft «Fremde»**.
    - Art: Mehrfachauswahl, leerbar, Platzhalter «Mitglieder der Fraktion».
    - Pflichtfeld: nein.
    - Vorbelegung: die gespeicherten Personen; Personen, die nicht mehr in der Auswahlliste stehen, bleiben als Marke sichtbar.
    - Auswahlwerte: **nur aktive** Mitglieder **der oben gewählten fremden Fraktion**, alphabetisch nach vollem Namen. Ist keine Fraktion gewählt, ist die Liste leer.
    - **Gesperrt**, solange keine Herkunftsfraktion gewählt ist.
    - Speicherung: sofort bei jeder Änderung.
10. **«Inhalt»**
    - Art: formatierter Textbereich mit Werkzeugleiste (siehe «Geteilte Bausteine → Formatierter Textbereich»), Platzhalter «Inhalt des Vorstosses».
    - Pflichtfeld: nein.
    - Vorbelegung: der gespeicherte Inhalt.
    - Speicherung: beim Verlassen des Feldes. Ein Klick auf die eigene Werkzeugleiste gilt **nicht** als Verlassen und löst weder ein Speichern noch einen Verlust der Eingabe aus.
    - Eine Versionsgeschichte gibt es für dieses Feld nicht (die Blätter-Knöpfe erscheinen nur bei Notizen).
11. **«Dokument»**
    - Art: Dokumentenbereich (siehe «Geteilte Bausteine → Dokumente»).
    - Angezeigter Ablagepfad: «Fraktion/40_Vorstösse/10_Eigene/V‹Nummer›-*» bei Herkunft «Eigene» und «Fraktion/40_Vorstösse/20_Fremde/V‹Nummer›-*» bei Herkunft «Fremde» — der Pfad **wechselt also sofort mit dem Feld «Herkunft»**.
    - Dateinamen werden mit «V‹Nummer›-» vorangestellt; als Namensvorschlag dient der Vorstoss-Titel.
12. **«Notizen»**
    - Art: Notizenliste (siehe «Geteilte Bausteine → Notizenliste») — identisch zu den Notizen beim Geschäft.
13. **«Aktionszeitleiste»**
    - Art: Zeitleiste (siehe «Geteilte Bausteine → Aktionszeitleiste»), nur Anzeige. Traktandennotizen erscheinen hier nicht.
14. **«Geschäft»**
    - **Bedingte Anzeige**: Ist der Vorstoss bereits mit einem Geschäft verknüpft, steht hier nur der Hinweis «Mit einem Geschäft verknüpft und abgeschlossen.» — es gibt dann keinen Knopf mehr, die Verknüpfung lässt sich hier nicht mehr ändern oder lösen.
    - Ist er noch nicht verknüpft, erscheint stattdessen der Knopf **«Mit Geschäft verknüpfen»** (zurückhaltende Darstellung). Er lädt die Geschäfte und öffnet den Verknüpfungsdialog.

#### Dialog «Mit Geschäft verknüpfen»

1. **Fenstertitel** — «Mit Geschäft verknüpfen».
2. **Schliessknopf «✕»** — Hilfetext «Dialog schliessen»; schliesst den Dialog ohne Verknüpfung. Ein Klick neben das Fenster wirkt gleich.
3. **Suchfeld** — Beschriftung «Suche», Platzhalter «Nr. oder Titel».
    - Art: Textfeld (ohne Löschknopf).
    - Pflichtfeld: nein. Vorbelegung: leer — es wird bei jedem Öffnen des Dialogs geleert.
    - Dynamisches Verhalten: schränkt die Liste sofort ein; gesucht wird gross-/kleinschreibungsunabhängig in Titel und Nummer.
4. **Hinweistext** — «Ähnlichste zum Vorstoss-Titel zuerst, sonst neueste.»
5. **Trefferliste** — je Geschäft eine anklickbare Zeile mit der Geschäftsnummer (fett; «—», wenn keine vorhanden) und dem Titel.
    - Inhalt und Reihenfolge: alle nicht gelöschten Geschäfte, sortiert nach der Zahl gemeinsamer, längerer Wörter mit dem Vorstoss-Titel (grösste Übereinstimmung zuerst); bei gleicher Übereinstimmung nach Datum, neuestes zuerst.
    - Wirkung eines Klicks: Der Vorstoss wird sofort mit dem Geschäft verknüpft, sein Status wechselt auf «Erledigt», eine gesetzte Priorität wird auf das Geschäft übertragen, der Dialog schliesst sich und die Vorstossliste wird neu geladen. Fehlermeldung: «Verknüpfung fehlgeschlagen: …».
6. **Leerzustand** — Text «Keine Geschäfte gefunden.», wenn die Suche nichts liefert.

---

### Geteilte Bausteine

#### Beschlussfeld

Wird beim Geschäft in der Tabelle, auf der Karte und in der Detailmaske eingesetzt sowie beim Vorstoss für «Art» und «Beschluss (Haltung zum fremden Vorstoss)».

1. **Eingabefeld mit Vorschlagsliste** — ein einzelnes Textfeld; beim Tippen und beim Klick in das Feld erscheinen die hinterlegten Vorschläge zur Auswahl.
    - Platzhalter: je nach Einsatzort «—», «Motion, Postulat, Interpellation …», «Miteinreichen, Unterstützen, Ablehnen …»; ohne besondere Vorgabe «Beschluss eingeben oder aus Liste wählen…».
    - Pflichtfeld: nein.
    - Vorbelegung: der aktuell gespeicherte Wert im Klartext.
    - Freier Text ist immer erlaubt: Stimmt der eingetippte Text (nach Entfernen führender und folgender Leerzeichen) genau mit einem Vorschlag überein, gilt der Vorschlag als gewählt; sonst wird der Text als freier Eintrag übernommen.
2. **Speicherzeitpunkte**, in dieser Rangfolge:
    - Auswahl aus der Vorschlagsliste ⇒ sofort.
    - Verlassen des Feldes ⇒ sofort; zusätzlich gilt die Eingabe damit als abgeschlossen (beim Geschäft beginnt danach ein neuer Eintrag in der Beschlussgeschichte).
    - Reines Tippen ohne Verlassen ⇒ automatisch fünf Sekunden nach dem letzten Tastendruck.
    - Wird das Fenster geschlossen, während noch eine solche Wartezeit läuft, wird der letzte Stand trotzdem noch gespeichert.
    - Leert man das Feld vollständig, wird der Wert **sofort** entfernt (beim Geschäft: der Beschluss wird zurückgenommen) — ohne Wartezeit.
3. **Gesperrt**, wenn der Einsatzort es vorgibt: beim Geschäft in der Detailmaske im Fraktionssitzungsmodus für alle ausser der Protokollführung. Gesperrt ist das Feld grau und nicht beschreibbar.

#### Mehrfachauswahl

Wird für Zuständigkeit, Ansprechpartner und alle Mehrfachfilter verwendet.

1. **Auswahlfeld** — zeigt die gewählten Einträge als Marken; jede Marke lässt sich einzeln entfernen.
2. Bereits gewählte Einträge werden **aus der Auswahlliste entfernt** — sie erscheinen nur noch als Marke.
3. Die Auswahlliste **bleibt nach einer Auswahl offen**, sodass mehrere Einträge nacheinander gewählt werden können.
4. Ist das Feld leerbar, entfernt ein Klick auf das Kreuz die gesamte Auswahl.
5. Der Platzhalter erscheint nur, solange nichts gewählt ist.

#### Notizenliste

Identisch bei Geschäften (reguläre Notizen und Sitzungsnotizen) und bei Vorstössen.

Notizen speichern **ausschliesslich** über das Häkchen «✓»; abgebrochen wird mit «✕». Es gibt **kein** automatisches Zwischenspeichern und **kein** Speichern beim Verlassen des Feldes. Nur die aktiven Notizen erscheinen hier — gelöschte stehen in der Aktionszeitleiste.

1. **Hinweis «Noch keine Notizen vorhanden.»** — erscheint nur, wenn keine aktiven Notizen vorliegen und gerade kein Notizfeld offen ist.
2. **Je aktive Notiz** (von oben nach unten in der gespeicherten Reihenfolge):
    1. **Name der verfassenden Person** (ersatzweise deren Benutzername).
    2. **Datum und Uhrzeit** der Erfassung in Schweizer Schreibweise (Uhrzeit auf Stunden und Minuten).
    3. **Löschknopf «✕»** (Hilfetext «Notiz löschen», derselbe einheitliche Löschknopf wie überall — kein Papierkorb-Symbol) — **bedingte Sichtbarkeit**: nur bei **eigenen** Notizen und nur, solange diese Notiz nicht gerade bearbeitet wird. Wirkung: die Notiz wird sofort ohne Rückfrage entfernt und erscheint fortan als Löschvermerk in der Aktionszeitleiste. Fehlermeldung: «Fehler beim Löschen der Notiz».
    4. **Notiztext**, formatiert dargestellt. Bei **eigenen** Notizen ist er anklickbar (Hilfetext «Klicken zum Bearbeiten»); ein Klick oder die «Eingabe»-Taste öffnet an dieser Stelle das Bearbeitungsfeld. Fremde Notizen sind nicht anklickbar und nicht bearbeitbar.
3. **Bearbeitungsfeld** (dasselbe Feld für neue und bestehende Notizen):
    - Formatierter Textbereich mit Werkzeugleiste; Platzhalter «Notiz bearbeiten…» beim Bearbeiten, «Kommentar, Beobachtung, Hinweis» bei einer neuen Notiz.
    - Beim Bearbeiten einer bestehenden Notiz erscheinen in der Werkzeugleiste zusätzlich die Knöpfe zum Blättern in älteren Fassungen (siehe «Formatierter Textbereich»).
    - **«✓»** — Hilfetext «Speichern». Der **einzige** Speicherweg. Schliesst die Bearbeitung ab und legt bei einer Änderung eine Fassung in der Versionsgeschichte ab. Rückmeldung «Notiz gespeichert» bzw. «Fehler beim Speichern der Notiz» / «Fehler beim Bearbeiten der Notiz». Blättert man gerade in einer älteren Fassung, hat der Knopf die Sonderfunktion «Wiederherstellen»: Der aktuelle Arbeitsstand wird zuerst regulär gesichert (und damit als Fassung erhalten), danach wird die angezeigte ältere Fassung als neue aktuelle Fassung übernommen.
    - **«✕»** — Hilfetext «Abbrechen». Schliesst das Feld, ohne zu speichern; die Eingabe wird verworfen.
    - **Kein Speichern bei Fokus-Verlust**: verlässt man das Feld (Klick daneben, anderes Feld), bleibt der Editor offen und der Text erhalten. Klicks auf «✓», «✕» und auf die Werkzeugleiste verwerfen nie eine Eingabe.
    - Eine **neue** Notiz wird erst beim «✓» angelegt, und nur wenn sie nicht leer ist — ein geöffnetes, leer gebliebenes Feld erzeugt nichts.
    - Wird der Text einer bestehenden Notiz vollständig geleert, wird die Notiz **nicht** gelöscht und der Leerstand nicht gespeichert; zum Löschen dient der Löschknopf.
4. **Warnung bei ungespeicherten Änderungen**: Ist ein Notizfeld mit geändertem, noch nicht gespeichertem Text offen, erscheint beim Verlassen der Seite (Zurück, anderer Link, Tab/Fenster schliessen) sowie beim Schliessen der Detailmaske (✕ oder Klick daneben) und beim Öffnen einer anderen Notiz eine Rückfrage, ob die Änderungen verworfen werden sollen.
5. **«+ Neue Notiz»** — Knopf am Ende der Liste, Hilfetext «Neue Notiz».
    - Wirkung: öffnet ein leeres Bearbeitungsfeld am Ende der Liste. Ist bereits ein Feld mit ungespeicherten Änderungen offen, wird zuerst nachgefragt.
    - Solange das Feld für eine neue Notiz offen ist, erscheint diese Notiz nicht zusätzlich als fertiger Eintrag in der Liste.
    - Gesperrt: nie.

#### Aktionszeitleiste

1. **Überschrift** — «Aktionszeitleiste».
2. **Hinweis «Noch keine Aktionen vorhanden.»** — erscheint, solange keine Einträge vorliegen.
3. **Je Eintrag** von links nach rechts:
    1. **Griff «⠿»** — Hilfetext «Verschieben». Damit lässt sich der Eintrag per Ziehen an eine andere Stelle der Zeitleiste bringen; das Ziel wird beim Darüberziehen hervorgehoben. Die geänderte Reihenfolge gilt nur für die aktuelle Ansicht und wird nicht gespeichert.
    2. **Datum** und darunter **Uhrzeit** der Aktion in Schweizer Schreibweise; bei einer Traktandennotiz zusätzlich eine kleine Zeile mit Traktandennummer, Sitzungsdatum und Sitzungstitel.
    3. **Name der handelnden Person** (ersatzweise Benutzername, sonst «unbekannt»).
    4. **Inhalt** — je nach Art der Aktion: die Bezeichnung der Aktion und darunter der ergänzende Text (z.B. bei einem Beschluss oder einer Rücknahme), ein formatierter Notiztext bei Traktandennotizen, oder nur ein Text.
    5. **Gelöschte Notiz** — für eine gelöschte Notiz (regulär oder Sitzungsnotiz) erscheint statt des Textes der Vermerk «‹Name› hat seine Notiz gelöscht» (der ursprüngliche Notiztext bleibt verborgen; ohne Namen «Jemand»). Daneben für die verfassende Person der Knopf **«↺»** (Hilfetext «Löschen rückgängig machen»): Notiz und Versionsgeschichte kommen zurück, die Notiz wandert zurück in ihre Notizenliste; Rückmeldung «Notiz wiederhergestellt» bzw. «Notiz konnte nicht wiederhergestellt werden». Aktive Notizen erscheinen hier NICHT (sie leben in der Notizenliste).
4. **Was in der Zeitleiste nicht erscheint**: reguläre Notizen und Sitzungsnotizen (sie leben in ihren eigenen Listen) sowie ein aktuell gültiges Votum.
5. **Traktandennotizen anklickbar** — **bedingte Sichtbarkeit**: nur, wenn die Maske aus einem Sitzungstraktandum heraus geöffnet wurde und die Sitzung bekannt ist. Hilfetext «Zur Sitzung springen»; ein Klick oder die «Eingabe»-Taste springt zur Sitzung.
6. Es gibt in der Zeitleiste keine Bearbeitungs-, Lösch- oder Speicherfunktion.

#### Dokumente

1. **Überschrift** — «Dokumente».
2. **Pfadangabe** — «Pfad:» gefolgt vom Ablageort in fester Schrift. Beim Geschäft «Fraktion/20_Geschäfte/‹Jahr›/‹Geschäftsnummer›-*», beim Vorstoss «Fraktion/40_Vorstösse/10_Eigene/V‹Nummer›-*» bzw. «…/20_Fremde/V‹Nummer›-*» je nach Herkunft.
3. **Ladehinweis «Lädt…»** — solange die Dateiliste geladen wird.
4. **Hinweis «Noch keine Dokumente vorhanden.»** — wenn keine Datei vorliegt.
5. **Dateiliste** — je Datei:
    1. **Dateiname** als Verweis; öffnet die Datei in einem neuen Fenster im passenden Betrachter oder Editor.
    2. **«⤓»** — Hilfetext «Herunterladen»; lädt die Datei herunter.
6. **«+ Neues Dokument»** — Menüknopf, hervorgehobene Darstellung. **Bedingte Sichtbarkeit**: erscheint erst, wenn der Ablageort feststeht (beim Geschäft: Geschäft und Nummer vorhanden; beim Vorstoss: Nummer vorhanden). Das Menü enthält in dieser Reihenfolge:
    1. «Word-Dokument (docx)»
    2. «Excel-Tabelle (xlsx)»
    3. «PowerPoint (pptx)»
    4. «OpenDocument-Text (odt)»
    5. «OpenDocument-Tabelle (ods)»
    6. «OpenDocument-Präsentation (odp)»
    7. «Markdown (md)»
    8. «Textdatei (txt)»
    - Eine Auswahl schliesst das Menü und öffnet den Dialog «Neues Dokument».
7. **«⤒ Hochladen»** — Knopf; öffnet die Dateiauswahl des Rechners. Nach der Auswahl wird die Datei sofort hochgeladen und die Liste aktualisiert. Rückmeldung «Datei hochgeladen» (verschwindet nach kurzer Zeit von selbst) bzw. «Hochladen fehlgeschlagen: …».
8. **Dialog «Neues Dokument: ‹gewählte Vorlage›»**:
    1. **Beschriftung** «Dateiname (ohne Präfix und Endung)».
    2. **Vorangestelltes Präfix** — unveränderliche Anzeige, z.B. die Geschäftsnummer oder «V‹Nummer›», gefolgt von einem Bindestrich.
    3. **Namensfeld** — Textfeld, Platzhalter «z. B. Überweisung Rede». Pflichtfeld: ja (ohne Eingabe lässt sich nicht erstellen). Vorbelegung: der Titel des Geschäfts bzw. des Vorstosses, wobei Leerzeichen und Schrägstriche durch Unterstriche ersetzt sind; der Schreibcursor steht am Ende, sodass sich der Vorschlag direkt ergänzen lässt. Die «Eingabe»-Taste erstellt das Dokument.
    4. **Angehängte Endung** — unveränderliche Anzeige der Dateiendung der gewählten Vorlage.
    5. **Hinweis** «Leerzeichen werden zu Unterstrichen.»
    6. **«Erstellen»** — Knopf, hervorgehobene Darstellung. **Gesperrt**, solange das Namensfeld leer ist (auch bei reinen Leerzeichen) oder das Erstellen bereits läuft. Wirkung: legt die Datei an, öffnet sie sofort in einem neuen Fenster, schliesst den Dialog, meldet «Dokument erstellt» und aktualisiert die Dateiliste. Fehlermeldung: «Fehler: …».
    7. **«Abbrechen»** — schliesst den Dialog ohne Wirkung.
    8. **«✕»** in der Kopfzeile — schliesst den Dialog ebenfalls und setzt den eingegebenen Namen zurück; ein Klick neben das Fenster wirkt gleich.
9. **Meldungszeile** — zeigt kurzzeitig Rückmeldungen zu Hochladen und Erstellen und blendet sich selbst wieder aus.

#### Formatierter Textbereich (Werkzeugleiste)

Wird für Notizen und für den Inhalt eines Vorstosses verwendet. Die Werkzeugleiste erscheint nur, solange der Bereich bearbeitbar ist; ist er es nicht, bleibt nur der reine Text sichtbar. Ein aktiver Formatierungszustand (z.B. Cursor steht in fettem Text) wird am Knopf hervorgehoben.

Knöpfe von links nach rechts, gruppenweise:

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
    1. «Link einfügen / bearbeiten» — fragt in einem kleinen Eingabefenster nach der Adresse; vorbelegt ist die Adresse eines bereits vorhandenen Verweises. Der Text im Fenster lautet «Link-URL (leer = entfernen)»: eine leere Eingabe entfernt den Verweis, ein Abbruch lässt alles unverändert.
    2. «Link entfernen» — **gesperrt**, solange der Cursor nicht in einem Verweis steht.
5. **Gruppe Verlauf**
    1. «Rückgängig (Ctrl+Z)» — **gesperrt**, wenn nichts rückgängig zu machen ist.
    2. «Wiederholen (Ctrl+Shift+Z)» — **gesperrt**, wenn nichts zu wiederholen ist.
    3. «Formatierung entfernen» — entfernt alle Auszeichnungen der Auswahl.
6. **Gruppe Versionen** — **bedingte Sichtbarkeit**: nur, wenn zu diesem Text ältere Fassungen vorliegen (bei Notizen, sobald sie mindestens einmal abgeschlossen bearbeitet wurden).
    1. **«←»** «Eine Version zurück» — erscheint nur, solange es eine ältere Fassung gibt; vom aktuellen Stand aus führt der erste Klick zur jüngsten archivierten Fassung. Beim ersten Klick wird der aktuelle Arbeitsstand festgehalten.
    2. **«→»** «Eine Version vorwärts» — erscheint nur, während eine ältere Fassung angezeigt wird.
    3. **«»»** «Zur neuesten Version» — erscheint nur, während eine ältere Fassung angezeigt wird; holt den festgehaltenen Arbeitsstand zurück (nicht die zuletzt gespeicherte Fassung).
7. **Gruppe rechts** — **bedingte Sichtbarkeit**: nur, wenn der Einsatzort zusätzliche Knöpfe mitgibt oder ein PDF angeboten wird. Enthält gegebenenfalls den Verweis mit Hilfetext «Als PDF herunterladen», der in einem neuen Fenster öffnet.
8. **Statusanzeige** ganz rechts:
    - Während des Blätterns: «Ältere Fassung – nur Ansicht»; der Text ist dann **gesperrt** und lässt sich nicht bearbeiten.
    - Sonst: der vom Einsatzort gelieferte Statustext (z.B. eine Speicherrückmeldung), falls vorhanden.

Weiteres Verhalten:

9. Alle Knöpfe der Werkzeugleiste sind so gebaut, dass ein Klick den Schreibfokus **nicht** aus dem Text nimmt: die Eingabe geht nie verloren, und ein solcher Klick löst kein «Feld verlassen» aus.
10. Während eine ältere Fassung angezeigt wird, wird beim Verlassen **nichts** gespeichert — Blättern verändert den gespeicherten Stand nie.
11. Der Platzhaltertext wird vom jeweiligen Einsatzort vorgegeben und erscheint nur, solange der Bereich leer ist.

---

### Ansicht «Sitzungen»

#### Suche und Filter

Beide Elemente erscheinen nicht im Inhaltsbereich, sondern in der linken Navigationsspalte: das Suchfeld zuoberst über den Ansichts-Einträgen, der Filter zuunterst darunter. Sie werden erst eingeblendet, wenn die Ansicht «Sitzungen» aktiv ist, und verschwinden beim Wechsel in eine andere Ansicht.

1. **Suchfeld** — Beschriftung «Suche», Platzhalter «Nr. oder Titel».
    - Art: Textfeld mit nachgestelltem Löschknopf.
    - Pflichtfeld: nein.
    - Vorbelegung: leer (kein gespeicherter Wert; nach einem Ansichtswechsel wieder leer).
    - Dynamisches Verhalten: filtert die Sitzungsliste sofort bei jedem Tastendruck. Gesucht wird gross-/kleinschreibungsunabhängig in Titel und Ort der Sitzung sowie in Titel und Nummer aller Traktanden. Sitzungen, deren Traktanden noch nicht geladen sind, bleiben zunächst sichtbar; sobald sie im Hintergrund nachgeladen sind, verschwinden sie, falls sie nicht passen. Beim Tippen werden die Traktanden aller sichtbaren Sitzungen automatisch nachgeladen; findet sich darin ein Treffer, klappt die betreffende Sitzung von selbst auf. Innerhalb einer aufgeklappten Sitzung werden zusätzlich die Traktandenzeilen auf die Treffer reduziert. Der Zähler in der Kopfzeile zeigt die verbleibende Anzahl.
    - Der Löschknopf (Kreuz-Symbol am rechten Feldrand) erscheint nur, solange Text im Feld steht; ein Klick leert die Suche und stellt die volle Liste wieder her.
    - Speichern: nichts wird gespeichert, reine Anzeigefilterung.
    - Gesperrt: nie.
2. **Schalter «Nur zukünftige Sitzungen»** — Beschriftung «Nur zukünftige Sitzungen».
    - Art: Schalter (ein/aus).
    - Pflichtfeld: entfällt.
    - Vorbelegung: eingeschaltet (fest im Programm vorgegeben, nicht gespeichert — nach jedem Neuladen wieder eingeschaltet).
    - Dynamisches Verhalten: eingeschaltet blendet alle Sitzungen aus, deren Datum vor dem heutigen Tag liegt; ausgeschaltet erscheinen auch vergangene Sitzungen (blass dargestellt). Der Schalter wirkt vor der Suche: bei eingeschaltetem Schalter werden Traktanden vergangener Sitzungen für die Suche gar nicht erst nachgeladen.
    - Speichern: nichts; reine Anzeigefilterung.
    - Gesperrt: nie.

#### Kopfzeile der Ansicht

1. **Überschrift «Sitzungen»** — reine Beschriftung.
2. **Zähler** — Marke mit der Anzahl der aktuell angezeigten (gefilterten) Sitzungen; ändert sich bei jeder Eingabe in Suche oder Filter.
3. **Menüknopf «+ Neue Sitzung»** — sichtbare Beschriftung «+ Neue Sitzung», Vorlese-Bezeichnung «Neue Sitzung aus Vorlage erstellen».
    - Art: Knopf in Hauptfarbe, der immer ein Auswahlmenü öffnet (auch wenn nur ein einziger Eintrag darin steht).
    - Menüeinträge: je ein Eintrag pro Sitzungstyp, beschriftet mit dem Namen des Sitzungstyps. Quelle: die gespeicherten Sitzungstypen; gelöschte Typen werden ausgefiltert. Reihenfolge: wie vom Server geliefert (Reihenfolge der Sitzungstypen-Verwaltung).
    - Sonderfall: existiert kein einziger Sitzungstyp, enthält das Menü genau einen, dauerhaft gesperrten Eintrag «Kein Sitzungstyp definiert».
    - Dynamisches Verhalten: die Wahl eines Eintrags öffnet das Formular «Neue Sitzung» und füllt es vollständig aus den Angaben des gewählten Sitzungstyps vor (siehe nächster Abschnitt).

#### Formular «Neue Sitzung» — Kopfbereich

Das Formular erscheint als überlagerndes Fenster über der ganzen Seite. Ein Klick auf die abgedunkelte Fläche neben dem Formular schliesst es ohne zu speichern.

1. **Schliessen-Knopf «✕»** — Vorlese-Bezeichnung «Dialog schliessen»; oben rechts; schliesst das Formular ohne zu speichern; alle Eingaben gehen verloren.
2. **Titel** — Platzhalter «Titel eingeben …», keine sichtbare Beschriftung (das Feld steht prominent zuoberst).
    - Art: Textfeld; erhält beim Öffnen automatisch den Eingabefokus.
    - Pflichtfeld: nein (die Sitzung lässt sich auch mit leerem Titel erstellen).
    - Vorbelegung: der Name des gewählten Sitzungstyps.
3. **Datum** — kein Beschriftungstext, mit Kalender-Symbol gekennzeichnet.
    - Art: Datumsfeld.
    - Pflichtfeld: **ja** — ohne Datum bleibt der Knopf «Erstellen» gesperrt.
    - Vorbelegung: das Datum in einer Woche (heute plus sieben Tage).
    - Einschränkung: als frühestes wählbares Datum ist der heutige Tag gesetzt; frühere Tage bietet die Datumsauswahl nicht an.
4. **Von** — Beschriftung «Von», mit Uhr-Symbol gekennzeichnet.
    - Art: Zeitfeld.
    - Pflichtfeld: nein.
    - Vorbelegung: die Standard-Anfangszeit des gewählten Sitzungstyps (leer, wenn dort keine hinterlegt ist).
5. **Bis** — Beschriftung «Bis», rechts neben «Von».
    - Art: Zeitfeld.
    - Pflichtfeld: nein.
    - Vorbelegung: die Standard-Endzeit des gewählten Sitzungstyps.
6. **Ort** — Platzhalter «Ort», mit Standort-Symbol gekennzeichnet.
    - Art: Textfeld.
    - Pflichtfeld: nein.
    - Vorbelegung: der Standard-Ort des gewählten Sitzungstyps.
7. **Zweck / Beschreibung** — Platzhalter «Zweck / Beschreibung …», mit Listen-Symbol gekennzeichnet.
    - Art: Textbereich, drei Zeilen hoch.
    - Pflichtfeld: nein.
    - Vorbelegung: der Zweck des gewählten Sitzungstyps.
8. **Verknüpfen mit** — Beschriftung «Verknüpfen mit», Platzhalter «Andere Sitzung wählen (optional) …», mit Ketten-Symbol gekennzeichnet.
    - Art: Auswahlliste mit Suchfunktion (Einfachauswahl).
    - **Bedingte Sichtbarkeit: dieser Bereich erscheint ausschliesslich dann, wenn beim gewählten Sitzungstyp die Option «Beim Anlegen Verknüpfung mit anderen Sitzungen anbieten» eingeschaltet ist.** Bei allen anderen Sitzungstypen fehlt die Zeile vollständig.
    - Pflichtfeld: nein.
    - Vorbelegung: leer (keine Verknüpfung).
    - Auswahlwerte: alle nicht gelöschten Sitzungen, beschriftet als «ausgeschriebenes Datum – Titel». Reihenfolge: zuerst alle künftigen Sitzungen (Datum ab heute) aufsteigend nach Datum, danach alle vergangenen absteigend nach Datum. Ausgefiltert werden gelöschte Sitzungen; die gerade entstehende Sitzung ist naturgemäss noch nicht in der Liste.
    - Speichern: die Verknüpfung wird erst beim Klick auf «Erstellen» hergestellt, und zwar unmittelbar nach dem Anlegen der Sitzung.

#### Formular «Neue Sitzung» — Traktandenliste

Der Bereich ist mit einem Listen-Symbol gekennzeichnet und trägt die Bereichsüberschrift «Traktanden». Die Zeilen sind beim Öffnen mit den Vorlage-Traktanden des gewählten Sitzungstyps vorbelegt, in der dort festgelegten Reihenfolge. Hat der Sitzungstyp keine Vorlage-Traktanden, ist die Liste leer.

Je Traktandenzeile, von links nach rechts:

1. **Ziehgriff «⠿»** — Art: Ziehgriff.
    - Dynamisches Verhalten: die Zeile lässt sich am Griff aufnehmen und über einer anderen Zeile fallen lassen; die aufgenommene Zeile wird an der Zielposition eingefügt, alle übrigen rücken nach. Die Zielzeile wird während des Ziehens hervorgehoben. Die Nummerierung wird sofort neu berechnet.
2. **Positionsnummer** — Anzeige «1.», «2.», … Art: reine Anzeige, fortlaufend nach der aktuellen Reihenfolge, nicht editierbar.
3. **Titel** — Platzhalter «Titel». Art: Textfeld. Pflichtfeld: nein. Vorbelegung: Titel des Vorlage-Traktandums.
4. **Beschreibung** — Platzhalter «Beschreibung (optional)». Art: Textfeld. Pflichtfeld: nein. Vorbelegung: Beschreibung des Vorlage-Traktandums.
5. **Löschknopf «✕»** — Vorlese-Bezeichnung «Traktandum ‹Nummer› löschen». Art: Knopf. Entfernt die Zeile sofort aus der Liste; die folgenden Zeilen werden neu nummeriert.

Am Ende der Liste:

6. **Knopf «+ Traktandum hinzufügen»** — Art: Knopf (zurückhaltende Gestaltung). Hängt eine leere Zeile mit leerem Titel und leerer Beschreibung an.

Speichern: nichts in diesem Bereich wird sofort gespeichert; die gesamte Traktandenliste wird erst mit «Erstellen» übernommen.

#### Formular «Neue Sitzung» — Teilnehmerliste

Der Bereich ist mit einem Personen-Symbol gekennzeichnet und trägt die Bereichsüberschrift «Teilnehmer». Die Zeilen sind beim Öffnen mit den Teilnehmerregeln des gewählten Sitzungstyps vorbelegt, in der dort gespeicherten Reihenfolge.

Je Teilnehmerzeile:

1. **Teilnehmerart** — keine sichtbare Beschriftung.
    - Art: Auswahlliste (Einfachauswahl).
    - Pflichtfeld: ja, immer belegt.
    - Vorbelegung: die Art aus der Vorlage; bei einer neu hinzugefügten Zeile «Eigene Fraktion».
    - Auswahlwerte in genau dieser Reihenfolge, fest im Programm vorgegeben: «Einzelnes Mitglied», «Ganze Fraktion», «Eigene Fraktion», «Ganze Kommission», «Fraktions-Rolle», «Nextcloud-Gruppe», «Nextcloud-Benutzer».
    - **Dynamisches Verhalten: die gewählte Art bestimmt, welches Element rechts daneben erscheint (siehe Punkt 2). Bei jedem Wechsel der Art wird die bisherige Auswahl der Zeile zurückgesetzt (die Zeile ist danach wieder unbestimmt). Beim erstmaligen Wechsel auf «Nextcloud-Gruppe» bzw. «Nextcloud-Benutzer» werden die entsprechenden Listen im Hintergrund nachgeladen.**
2. **Bezugselement rechts daneben** — welches erscheint, hängt allein von der gewählten Art ab:
    - Bei **«Einzelnes Mitglied»**: Auswahlliste mit dem ersten Eintrag «— Mitglied wählen —» (Leerwert) und darunter allen aktiven Mitgliedern, beschriftet «Vorname Name». Quelle: die Mitgliederverwaltung; ausgefiltert werden inaktive Mitglieder. Reihenfolge: alphabetisch nach «Vorname Name» (die Gesamtliste ist so sortiert, dass aktive Mitglieder zuerst kommen; da inaktive ausgefiltert sind, bleibt die reine alphabetische Reihenfolge).
    - Bei **«Ganze Fraktion»**: Auswahlliste mit dem ersten Eintrag «— Fraktion wählen —» und darunter allen aktiven Fraktionen. Angezeigt wird die Kurzform des Fraktionsnamens gemäss den konfigurierten Kürzeln, gespeichert wird der volle Name. Reihenfolge: wie in der Fraktionsverwaltung geliefert. Ausgefiltert: inaktive Fraktionen.
    - Bei **«Eigene Fraktion»**: keine Auswahl, sondern ein reiner Hinweistext «→ ‹Name der konfigurierten Gruppe›». Ist in der Konfiguration keine Gruppe hinterlegt, steht dort «→ (keine Gruppe konfiguriert)».
    - Bei **«Ganze Kommission»**: Auswahlliste mit dem ersten Eintrag «— Kommission wählen —» und darunter allen aktiven, nicht gelöschten Kommissionen, angezeigt in der Kurzform gemäss den konfigurierten Kürzeln. Reihenfolge: wie in der Kommissionsverwaltung geliefert.
    - Bei **«Fraktions-Rolle»**: Auswahlliste mit dem ersten Eintrag «— Fraktions-Rolle —» und darunter genau fünf fest vorgegebenen Rollen in dieser Reihenfolge: «Kommissionsmitglied», «Fraktionspräsident», «Fraktionspräsident Stellvertretung», «Protokollführer», «Protokollführer Stellvertretung».
    - Bei **«Nextcloud-Gruppe»**: Auswahlliste mit dem ersten Eintrag «— Nextcloud-Gruppe —»; solange die Liste noch geladen wird, lautet dieser Eintrag «— Lade … —». Darunter alle Gruppen der Nextcloud-Installation, beschriftet mit dem Anzeigenamen (ersatzweise der technischen Kennung). Reihenfolge: wie vom Server geliefert.
    - Bei **«Nextcloud-Benutzer»**: Auswahlliste mit dem ersten Eintrag «— Nextcloud-Benutzer —»; während des Ladens «— Lade … —». Darunter Benutzer, beschriftet «Anzeigename (Benutzerkennung)»; es werden höchstens 100 Benutzer geladen. Reihenfolge: wie vom Server geliefert.
    - Ersatzfall: stammt eine Zeile aus älteren Daten mit einer unbekannten Art, erscheint stattdessen ein freies Textfeld mit dem Platzhalter «Bezeichnung».
3. **Löschknopf «✕»** — entfernt die Teilnehmerzeile sofort aus der Liste.

Am Ende der Liste:

4. **Knopf «+ Teilnehmer hinzufügen»** — Art: Knopf (zurückhaltende Gestaltung). Hängt eine neue Zeile an, vorbelegt mit der Art «Eigene Fraktion» und ohne Bezug.

Speichern: nichts wird sofort gespeichert; die Teilnehmerliste wird erst mit «Erstellen» übernommen.

#### Formular «Neue Sitzung» — Abschluss

1. **Fehlermeldung** — Text «Fehler: ‹Meldung des Servers›».
    - Art: Meldungszeile im Formular.
    - Bedingte Sichtbarkeit: erscheint erst, nachdem ein Erstellungsversuch fehlgeschlagen ist; bei jedem neuen Versuch und bei jeder neuen Typwahl wird sie zurückgesetzt. Zusätzlich erscheint eine Fehlermeldung als eingeblendete Systemmeldung «Sitzung konnte nicht erstellt werden: …».
2. **Knopf «Erstellen»** — Art: Knopf in Hauptfarbe.
    - **Gesperrt, solange kein Datum gesetzt ist oder das Erstellen bereits läuft.**
    - Während des Erstellens wechselt die Beschriftung zu «Erstellt …».
    - Beim Klick wird die Sitzung mit Sitzungstyp, Datum, Titel, Ort, Von, Bis, Zweck, der vollständigen Traktandenliste und der vollständigen Teilnehmerliste angelegt. Ist unter «Verknüpfen mit» eine Sitzung gewählt, wird unmittelbar danach die Verknüpfung hergestellt; scheitert nur diese, erscheint die Meldung «Sitzung erstellt, Verknüpfung fehlgeschlagen: …», die Sitzung bleibt aber bestehen. Nach Erfolg wird die neue Sitzung in die Liste einsortiert (nach Datum), automatisch aufgeklappt, ihre Traktanden werden geladen und das Formular schliesst sich.
3. **Knopf «Abbrechen»** — Art: Knopf. Schliesst das Formular ohne zu speichern; alle Eingaben gehen verloren.

#### Ladeanzeige und Leermeldung der Liste

1. **Ladeanzeige** — drehendes Symbol, solange die Sitzungen geladen werden; die Liste selbst ist währenddessen nicht sichtbar.
2. **Leermeldung «Keine Sitzungen gefunden.»** — erscheint, wenn Filter und Suche kein Ergebnis liefern.

#### Sitzungskarte (zugeklappt)

Jede Sitzung erscheint als Karte. Der ganze Kartenkopf ist anklickbar und klappt den Detailbereich auf oder zu. Vergangene Sitzungen werden blass dargestellt.

1. **Datum** — fett, ausgeschrieben im Schweizer Format (Wochentag, Tag, Monat, Jahr).
2. **Zeitangabe** — «Von» bzw. «Von – Bis»; erscheint nur, wenn eine Anfangszeit hinterlegt ist; die Endzeit nur, wenn auch sie hinterlegt ist.
3. **Marke «intern»** — erscheint nur bei Sitzungen, die aus einem eigenen Sitzungstyp entstanden sind (nicht bei importierten Parlamentssitzungen).
4. **Titel der Sitzung** — reine Anzeige.
5. **Zweck** — erscheint nur bei internen Sitzungen und nur, wenn ein Zweck erfasst ist.
6. **Ort** — reine Anzeige.
7. **Link «Protokoll»** — erscheint nur, wenn das Protokoll dieser Sitzung auf der Parlamentswebseite veröffentlicht ist; Hinweistext ist der Titel des Protokolls. Öffnet das Protokoll in einem neuen Fenster und klappt die Karte dabei nicht auf oder zu (F115). Er steht links von «Extern», damit «Extern» auf jeder Karte an derselben Stelle steht.
8. **Link «Extern»** — erscheint nur, wenn zur Sitzung eine externe Adresse hinterlegt ist; öffnet sie in einem neuen Fenster und klappt die Karte dabei nicht auf oder zu.
9. **Auf-/Zuklapp-Zeichen «▼» / «▲»** — zeigt den Zustand an: «▼» zugeklappt, «▲» aufgeklappt.

Dynamisches Verhalten beim Aufklappen: es werden die Traktanden der Sitzung, die verknüpften Sitzungen, die Liste aller Geschäfte (einmalig, für die Verknüpfungsauswahl) und die bereits verknüpften Geschäfte nachgeladen. Änderungen anderer Benutzer werden laufend übernommen; offene Sitzungen aktualisieren ihre Traktanden dabei automatisch, ohne dass die Ansicht springt.

#### Aufgeklappter Detailbereich — Notizen zur Sitzung

1. **Bereichsüberschrift «Notizen zur Sitzung»**.
2. **Notizenliste der Sitzung** — dieselbe geteilte Notizenliste wie überall (Aufbau und Bedienung siehe «Geteilte Bausteine → Notizenliste»): «+ Neue Notiz», Speichern ausschliesslich über «✓», Versionsverlauf, Löschen mit Wiederherstellen über die Aktionszeitleiste. Die Notizen haften an der Sitzung; es gibt kein davon abweichendes, eigenes Notizfeld.

#### Aufgeklappter Detailbereich — Dokumente

1. **Bereichsüberschrift «Dokumente»**.
2. **Pfadhinweis** — Text «Pfad: Fraktion/10_Sitzungen/‹Jahr der Sitzung›/‹Datum der Sitzung›-*»; reine Anzeige, zeigt, wo die Dateien abgelegt werden.
3. **Ladeanzeige «Lädt…»** — solange die Dateiliste geladen wird.
4. **Hinweis «Noch keine Dokumente vorhanden.»** — wenn keine Datei existiert.
5. **Dokumentenliste** — je Eintrag:
    - **Dateiname** als Link; öffnet die Datei in einem neuen Fenster im passenden Betrachter bzw. Editor.
    - **Herunterladen-Knopf «⤓»** — Hinweistext «Herunterladen»; lädt die Datei direkt herunter.
6. **Menüknopf «+ Neues Dokument»** — Art: Knopf in Hauptfarbe mit Auswahlmenü. Menüeinträge in dieser festen Reihenfolge: «Word-Dokument (docx)», «Excel-Tabelle (xlsx)», «PowerPoint (pptx)», «OpenDocument-Text (odt)», «OpenDocument-Tabelle (ods)», «OpenDocument-Präsentation (odp)», «Markdown (md)», «Textdatei (txt)». Die Wahl öffnet den Dialog «Neues Dokument».
7. **Knopf «⤒ Hochladen»** — öffnet die Dateiauswahl des Betriebssystems; die gewählte Datei wird sofort hochgeladen. Danach erscheint kurz die Meldung «Datei hochgeladen» (bei Fehlschlag «Hochladen fehlgeschlagen: …»), und die Liste wird neu geladen.
8. **Dialog «Neues Dokument: ‹gewählte Vorlage›»** — Elemente von oben nach unten:
    1. Titelzeile mit dem Namen der gewählten Vorlage und Schliessknopf «✕».
    2. Beschriftung «Dateiname (ohne Präfix und Endung)».
    3. Fester Präfix-Text «Sitzungsdatum-» links vom Eingabefeld (reine Anzeige).
    4. **Namensfeld** — Platzhalter «z. B. Überweisung Rede»; Art: Textfeld; Pflichtfeld: ja (ohne Eingabe bleibt «Erstellen» gesperrt); Vorbelegung: bei Sitzungen leer; erhält beim Öffnen den Fokus mit dem Cursor am Textende; die Eingabetaste erstellt das Dokument.
    5. Feste Endungs-Anzeige «.‹Dateiendung der Vorlage›» rechts vom Eingabefeld.
    6. Hinweis «Leerzeichen werden zu Unterstrichen.».
    7. **Knopf «Erstellen»** — gesperrt bei leerem Namen oder während des Anlegens. Legt die Datei an, öffnet sie sofort in einem neuen Fenster, schliesst den Dialog, zeigt kurz «Dokument erstellt» und lädt die Liste neu. Bei Fehler: «Fehler: …».
    8. **Knopf «Abbrechen»** — schliesst den Dialog ohne anzulegen.
    - Ein Klick neben den Dialog schliesst ihn ebenfalls.
9. **Meldungszeile** — zeigt kurzzeitig (rund 2,5 Sekunden) die letzte Rückmeldung wie «Dokument erstellt» oder «Datei hochgeladen»; Fehlermeldungen bleiben stehen.

#### Aufgeklappter Detailbereich — Verknüpfte Geschäfte

1. **Bereichsüberschrift «Verknüpfte Geschäfte»**.
2. **Liste der verknüpften Geschäfte** — erscheint nur, wenn mindestens eines verknüpft ist. Je Eintrag:
    - Anzeige «Geschäftsnummer Titel» (ist das Geschäft nicht auffindbar, erscheint ersatzweise «#Nummer»).
    - **Knopf «✕»** — Hinweistext «Verknüpfung lösen»; löst die Verknüpfung sofort; die Liste wird unmittelbar aktualisiert. Bei Fehler erscheint «Verknüpfung konnte nicht gelöst werden: …».
3. **Auswahlliste «Geschäft verknüpfen…»** — Platzhalter «Geschäft verknüpfen…».
    - Art: Auswahlliste mit Suchfunktion (Einfachauswahl).
    - Pflichtfeld: nein.
    - Vorbelegung: keine (das Feld bleibt nach jeder Wahl leer).
    - Auswahlwerte: alle geladenen Geschäfte (bis zu 500), beschriftet «Nummer Titel», in der vom Server gelieferten Reihenfolge; **bereits verknüpfte Geschäfte werden ausgefiltert**.
    - Speichern: die Auswahl verknüpft das Geschäft sofort; die Liste darüber wächst unmittelbar. Bei Fehler: «Geschäft konnte nicht verknüpft werden: …».

#### Aufgeklappter Detailbereich — To-do

1. **Bereichsüberschrift «To-do zu Deck»**.
2. **Aufgabenfeld** — Platzhalter «Aufgabe für das Fraktions-Board…».
    - Art: Textfeld.
    - Pflichtfeld: nein, aber ohne Text passiert nichts.
    - Vorbelegung: leer; je Sitzung wird ein eigener Zwischenstand gehalten.
    - Dynamisches Verhalten: die Eingabetaste löst dieselbe Aktion aus wie der Knopf daneben.
3. **Knopf «Hinzufügen»** — Art: Knopf (zweite Ebene). Legt eine Aufgabenkarte auf dem Fraktions-Board an; die Beschreibung der Karte wird automatisch gesetzt auf «Aus Sitzung: ‹Titel der Sitzung› (‹Datum›)». Nach Erfolg wird das Eingabefeld geleert. Bei Fehler: «To-do konnte nicht zu Deck hinzugefügt werden: …».

#### Aufgeklappter Detailbereich — Verknüpfte Sitzungen

Der ganze Bereich erscheint nur, wenn die Sitzung Teil einer Verknüpfungsgruppe ist.

1. **Bereichsüberschrift «🔗 Verknüpfte Sitzungen»**.
2. **Knopf «Entkoppeln»** — in der Überschrift rechts; löst die Sitzung sofort aus der Verknüpfungsgruppe; der ganze Bereich verschwindet danach. Bei Fehler: «Entkoppeln fehlgeschlagen: …».
3. **Hinweis «Keine weiteren Sitzungen in dieser Verknüpfung.»** — erscheint, wenn ausser dieser Sitzung keine weitere in der Gruppe ist.
4. **Je verknüpfte Sitzung:**
    1. Unterüberschrift «ausgeschriebenes Datum – Titel».
    2. **Notizenliste der anderen Sitzung — reine Ansicht:** dieselbe geteilte Notizenliste (siehe «Geteilte Bausteine → Notizenliste»), jedoch im Nur-Lese-Modus: kein «+ Neue Notiz», kein Bearbeiten, kein Löschen.

#### Aufgeklappter Detailbereich — interne Traktanden

Diese vereinfachte Darstellung erscheint bei Sitzungen, die aus einem eigenen Sitzungstyp entstanden sind.

1. **Bereichsüberschrift «Traktanden»**.
2. **Ladeanzeige «Traktanden laden...»** — solange die Traktanden geladen werden.
3. **Tabelle** mit zwei Spaltenüberschriften:
    1. Spalte «Tr.» — die Traktandennummer, fett.
    2. Spalte «Titel» — Titel des Traktandums und darunter, sofern vorhanden, die Beschreibung.
4. **Notizzeile unter jedem Traktandum** — zwei Varianten:
    - **Ist dem Traktandum ein Geschäft zugeordnet:** Hinweistext «Sitzungsnotiz zum Geschäft», darunter die Notizenliste des Geschäfts in der Kategorie Sitzungsnotiz (Aufbau siehe Abschnitt «Notizen am Geschäft»). Diese Notizen haften am Geschäft und erscheinen daher an jeder Sitzung, in der dasselbe Geschäft traktandiert ist.
    - **Sonst:** Hinweistext «Notiz zum Traktandum», darunter dieselbe geteilte Notizenliste wie überall (siehe «Geteilte Bausteine → Notizenliste»). Die Notiz haftet am Traktandum.
5. **Leermeldung «Keine Traktanden vorhanden.»** — wenn die Sitzung keine Traktanden hat (bzw. die Suche keine übrig lässt).

#### Aufgeklappter Detailbereich — Traktanden als Tabelle

Diese vollständige Darstellung erscheint bei den Traktanden einer Parlamentssitzung, also bei Sitzungen ohne eigenen Sitzungstyp. Bei schmaler Ansicht wird stattdessen die Kartendarstellung des nächsten Abschnitts gezeigt.

1. **Bereichsüberschrift «Traktanden»**.
2. **Spaltenüberschriften** in dieser Reihenfolge: «Tr.», «Nr.», «Titel», «Zuständig», «Beschluss».
3. **Je Traktandenzeile:**
    1. **Spalte «Tr.»** — Traktandennummer, fett; reine Anzeige.
    2. **Spalte «Nr.»** — Geschäftsnummer (fett), darunter das Geschäftsdatum in Kurzform «TT.MM.JJ» und die Geschäftsart; reine Anzeige, leer wenn kein Geschäft zugeordnet ist.
    3. **Spalte «Titel»** — vorangestelltes Pfeilsymbol «↗» als Link, danach der Titel (Titel des Geschäfts, ersatzweise Titel des Traktandums). Das Pfeilsymbol erscheint in genau einer von drei Ausprägungen, in dieser Rangfolge: Hinweistext «Extern öffnen» (Adresse des Geschäfts), sonst «Dokument öffnen» (Adresse des Traktandums), sonst «Originaltraktandum extern öffnen (kein verknüpftes Geschäft)» (Adresse der Sitzung). Ist keine dieser Adressen vorhanden, fehlt das Symbol. Ein Klick auf den Pfeil öffnet ein neues Fenster und öffnet **nicht** das Geschäft. Hinter dem Titel steht bei einem Traktandum, das ein Protokoll abnimmt, der Link «Protokoll ‹TT.MM.JJJJ der protokollierten Sitzung›» mit dem Titel des Protokolls als Hinweistext; er öffnet das Protokoll in einem neuen Fenster und öffnet **nicht** das Geschäft (F115).
    4. **Spalte «Zuständig»** — Mehrfachauswahl, Platzhalter «—», mit Löschmöglichkeit der ganzen Auswahl.
        - Bedingte Sichtbarkeit: nur wenn dem Traktandum ein Geschäft zugeordnet ist; sonst zeigt die Zelle nur «—».
        - Vorbelegung: die aktuell am Geschäft hinterlegten zuständigen Personen; für Personen, die nicht mehr in der Mitgliederliste sind, wird der gespeicherte Name angezeigt.
        - Auswahlwerte: alle aktiven Mitglieder, **die ein Nextcloud-Konto hinterlegt haben**; beschriftet mit «Vorname Name»; alphabetisch sortiert. Bereits gewählte Personen verschwinden aus der Auswahlliste und erscheinen nur noch als Merkzeichen im Feld.
        - Speichern: **sofort bei jeder Änderung**. Die erste gewählte Person wird zur Hauptzuständigen, sofern die bisherige Hauptperson nicht mehr in der Auswahl ist; andernfalls bleibt die bisherige Hauptperson bestehen. Danach werden die Traktanden im Hintergrund neu geladen, ohne Ladeanzeige.
        - Ein Klick in diese Zelle öffnet **nicht** das Geschäft.
    5. **Spalte «Beschluss»** — Auswahlliste (Einfachauswahl), Platzhalter «—», mit Löschmöglichkeit.
        - Bedingte Sichtbarkeit: nur wenn dem Traktandum ein Geschäft zugeordnet ist; sonst zeigt die Zelle nur «—».
        - Vorbelegung: der zuletzt am Geschäft erfasste Beschluss; ist er in den erlaubten Werten nicht enthalten, wird sein gespeicherter Titel angezeigt.
        - Auswahlwerte: **ausschliesslich die für dieses Geschäft im aktuellen Zustand erlaubten Beschlüsse** — sie kommen mit dem Geschäft vom Server und unterscheiden sich daher von Zeile zu Zeile; Reihenfolge wie geliefert.
        - Speichern: **sofort**. Die Wahl eines Wertes erfasst den Beschluss am Geschäft; das Leeren des Feldes entfernt den zuletzt erfassten Beschluss. Danach werden die Traktanden im Hintergrund neu geladen.
        - Ein Klick in diese Zelle öffnet **nicht** das Geschäft.
    6. **Zeilenaktion** — die ganze Zeile (ausser den beiden Auswahlspalten und den Pfeil-Links) ist anklickbar und auch per Tastatur mit Eingabe- oder Leertaste auslösbar; Vorlese-Bezeichnung «Traktandum ‹Nummer› öffnen». Sie öffnet das zugeordnete Geschäft im Detailfenster. Ohne zugeordnetes Geschäft passiert nichts. Zeilen gelöschter Geschäfte werden durchgestrichen bzw. abgesetzt dargestellt.
    7. **Notizzeile unter dem Traktandum** — identisch zur internen Sitzung: bei zugeordnetem Geschäft der Hinweis «Sitzungsnotiz zum Geschäft» mit der Notizenliste des Geschäfts, sonst der Hinweis «Notiz zum Traktandum» mit derselben geteilten Notizenliste.
4. **Leermeldung «Keine Traktanden gefunden.»**.

#### Aufgeklappter Detailbereich — Traktanden als Karten

Bei schmaler Ansicht treten die Karten an die Stelle der Tabelle: inhaltlich dieselben Bedienelemente, anders angeordnet. Je Traktandum:

1. **Kennung** — «Tr. ‹Nummer›» (fett) und, sofern vorhanden, «Nr. ‹Geschäftsnummer›».
2. **Titel mit vorangestelltem Pfeilsymbol «↗»** — dieselben drei Ausprägungen wie in der Tabelle («Extern öffnen», «Dokument öffnen», «Originaltraktandum extern öffnen»), und hinter dem Titel derselbe Protokoll-Link wie in der Tabelle, sofern das Traktandum ein Protokoll abnimmt (F115).
3. **Merkmalszeile** — Statustext des Geschäfts (farblich abgesetzt nach offen / erledigt / abgelehnt / neutral), Geschäftsart, ausgeschriebenes Geschäftsdatum; jedes nur, wenn vorhanden.
4. **Kartenkopf als Aktion** — Klick, Eingabe- oder Leertaste öffnet das Geschäft; Vorlese-Bezeichnung «Traktandum ‹Nummer› öffnen».
5. **Beschriftung «Zuständig»** mit derselben Mehrfachauswahl wie in der Tabelle (nur bei zugeordnetem Geschäft).
6. **Beschriftung «Beschluss»** mit derselben Auswahlliste wie in der Tabelle (nur bei zugeordnetem Geschäft).
7. **Notizbereich** — bei zugeordnetem Geschäft «Sitzungsnotiz zum Geschäft» mit der Notizenliste des Geschäfts, sonst der Hinweis «Notiz zum Traktandum» mit derselben geteilten Notizenliste.

#### Detailfenster eines Geschäfts

1. **Schliessknopf «✕»** — Vorlese-Bezeichnung «Dialog schliessen»; ein Klick auf die abgedunkelte Fläche schliesst ebenfalls.
2. **Geschäftsdetail** — zeigt zusätzlich den Zusammenhang, aus dem es geöffnet wurde (Sitzung, Sitzungsdatum, Sitzungstitel, Traktandennummer und die Notizen zu diesem Traktandum).
3. **Sprung zurück zu einer Sitzung** — wird im Detail eine Sitzung angesteuert, schliesst sich das Fenster, die Zielsitzung klappt auf, ihre Traktanden werden geladen und die Ansicht scrollt sanft zu ihr.

---

### Ansicht «Sitzungstypen»

#### Suche in der Seitenspalte

1. **Suchfeld** — Beschriftung «Suche», Platzhalter «Name oder Zweck».
    - Art: Textfeld mit nachgestelltem Löschknopf (erscheint nur bei Inhalt).
    - Pflichtfeld: nein. Vorbelegung: leer.
    - Dynamisches Verhalten: filtert die Kartenliste sofort; durchsucht Name und Zweck gross-/kleinschreibungsunabhängig. Der Zähler in der Kopfzeile passt sich an.
    - In dieser Ansicht gibt es keinen zusätzlichen Filterschalter.

#### Kopfzeile der Ansicht

1. **Überschrift «Sitzungstypen»**.
2. **Zähler** — Anzahl der aktuell angezeigten Sitzungstypen.
3. **Knopf «+ Neuer Typ»** — Art: Knopf in Hauptfarbe; öffnet die vollständige Sitzungstyp-Maske mit leerem Namensfeld. Angelegt wird erst beim Speichern.

#### Liste und Karten

1. **Ladeanzeige** — drehendes Symbol, solange geladen wird.
2. **Hinweis «Keine Sitzungstypen vorhanden. Lege einen neuen Typ an.»** — wenn die (gefilterte) Liste leer ist.
3. **Karte je Sitzungstyp** — die ganze Karte ist anklickbar und auch per Eingabe- oder Leertaste auslösbar; Vorlese-Bezeichnung «Sitzungstyp ‹Name› bearbeiten»; sie öffnet den Bearbeitungsdialog. Aufbau:
    1. **Name** — Überschrift der Karte.
    2. **Zweck** — abgesetzter Text darunter; erscheint nur, wenn ein Zweck erfasst ist.
    3. **Knopf «Löschen»** — Art: Knopf in Warnfarbe, oben rechts. Öffnet zuerst eine Sicherheitsabfrage «Sitzungstyp ‹Name› wirklich löschen?»; erst nach Bestätigung wird gelöscht und die Liste neu geladen. Der Klick öffnet den Bearbeitungsdialog **nicht**.
    4. **Merkmalszeile** — vier Angaben, jede als reine Anzeige: «📍 ‹Standard-Ort›» (nur wenn erfasst), «🕒 ‹Von›» bzw. «🕒 ‹Von› – ‹Bis›» (nur wenn eine Anfangszeit erfasst ist; die Endzeit nur zusätzlich, wenn auch sie erfasst ist), «‹Anzahl› Vorlage-Traktanden», «‹Anzahl› Teilnehmer».

#### Neuen Sitzungstyp anlegen

«+ Neuer Typ» öffnet **sofort dieselbe vollständige Maske wie das Bearbeiten** — mit allen Feldern, den Vorlage-Traktanden, den Teilnehmern und den Optionen. Es gibt kein reduziertes Formular davor.

1. **Titelzeile** — solange kein Name erfasst ist, heisst sie «Neuer Sitzungstyp»; sobald ein Name gespeichert wurde, «Sitzungstyp bearbeiten». Rechts der **Schliessknopf «✕»**.
2. **Knöpfe unten: «Speichern» (links, hervorgehoben) und «Abbrechen»** — im Anlege-Modus gibt es kein «✕». «Speichern» ist gesperrt, solange das Namensfeld leer ist. Erst «Speichern» legt den Sitzungstyp an; dabei sind «Kalender anlegen» und «Einladung versenden» eingeschaltet und «Verknüpfung anbieten» ausgeschaltet, sofern nicht anders erfasst. Bei Fehler: «Sitzungstyp konnte nicht erstellt werden: …». Danach geht dieselbe Maske in die Bearbeitung über.
3. **Ein Klick neben die Maske bewirkt nichts** — angefangene Eingaben bleiben erhalten. Verworfen wird ausschliesslich über «Abbrechen».
4. Alle weiteren Felder, ihre Vorbelegungen, Auswahlwerte und ihr Speicherverhalten sind unter «Dialog ‹Sitzungstyp bearbeiten›» beschrieben — sie gelten unverändert auch beim Anlegen.

#### Dialog «Sitzungstyp bearbeiten» — Grunddaten

Jede Eingabe wird sofort gespeichert, sobald das Feld verlassen oder der Wert geändert wird. Es gibt keinen Speichern-Knopf; «✕» und der Klick neben den Dialog schliessen nur. Nach jedem erfolgreichen Speichern erscheint kurz die Meldung «Gespeichert», bei Fehlschlag «Sitzungstyp konnte nicht gespeichert werden: …». **Ein leerer Name wird nie gespeichert** — solange das Namensfeld leer ist, bleiben auch alle übrigen Änderungen ungespeichert. Unvollständige Teilnehmerangaben werden beim Speichern stillschweigend verworfen.

1. **Titelzeile «Sitzungstyp bearbeiten»** mit **Schliessknopf «✕»** (Vorlese-Bezeichnung «Dialog schliessen»).
2. **Feld «Name *»** — Art: Textfeld. **Pflichtfeld: ja.** Vorbelegung: der gespeicherte Name. Speichern beim Verlassen des Feldes.
3. **Feld «Zweck»** — Art: Textbereich, zwei Zeilen hoch. Pflichtfeld: nein. Vorbelegung: der gespeicherte Zweck. Speichern beim Verlassen des Feldes. Dieser Text belegt später den Zweck einer neu erstellten Sitzung vor.
4. **Feld «Standard-Ort»** — Art: Textfeld. Pflichtfeld: nein. Vorbelegung: der gespeicherte Ort. Belegt später das Feld «Ort» einer neuen Sitzung vor.
5. **Feld «Von»** — Art: Zeitfeld. Pflichtfeld: nein. Vorbelegung: die gespeicherte Anfangszeit. Belegt später «Von» einer neuen Sitzung vor.
6. **Feld «Bis»** — Art: Zeitfeld, rechts neben «Von». Pflichtfeld: nein. Vorbelegung: die gespeicherte Endzeit. Belegt später «Bis» einer neuen Sitzung vor.

#### Dialog «Sitzungstyp bearbeiten» — Bereich «Vorlage-Traktanden»

Umrandeter Bereich mit der Bereichsbeschriftung «Vorlage-Traktanden». Die hier erfassten Zeilen erscheinen später als vorbelegte Traktandenliste jeder neuen Sitzung dieses Typs.

Je Zeile, von links nach rechts:

1. **Ziehgriff «⠿»** — Art: Ziehgriff. Die Zeile lässt sich am Griff aufnehmen und über einer anderen Zeile fallen lassen; die Zielzeile wird während des Ziehens hervorgehoben. **Das Umsortieren wird sofort gespeichert.**
2. **Titel** — Platzhalter «Titel». Art: Textfeld. Pflichtfeld: nein. Vorbelegung: gespeicherter Titel. Speichern beim Verlassen des Feldes.
3. **Beschreibung** — Platzhalter «Beschreibung». Art: Textfeld. Pflichtfeld: nein. Vorbelegung: gespeicherte Beschreibung. Speichern beim Verlassen des Feldes.
4. **Knopf «✕»** — entfernt die Zeile und **speichert sofort**.

Am Ende des Bereichs:

5. **Knopf «+ Traktandum»** — Art: Knopf (zweite Ebene). Hängt eine leere Zeile an; gespeichert wird erst, wenn eines der beiden Felder ausgefüllt und verlassen wird.

Es gibt hier — anders als in der Sitzungsliste — keine sichtbare Positionsnummer; die Reihenfolge ergibt sich aus der Anordnung der Zeilen.

#### Dialog «Sitzungstyp bearbeiten» — Bereich «Teilnehmer»

Umrandeter Bereich mit der Bereichsbeschriftung «Teilnehmer». Die hier erfassten Angaben erscheinen später als vorbelegte Teilnehmerliste jeder neuen Sitzung dieses Typs.

1. **Schalter «Eigene Fraktion»** — Beschriftung «Eigene Fraktion», dahinter der Hinweis «→ ‹Name der konfigurierten Gruppe›», sofern eine Gruppe konfiguriert ist (ohne Konfiguration fehlt der Hinweis ganz).
    - Art: Schalter (ein/aus).
    - Vorbelegung: eingeschaltet, wenn beim Typ bereits eine Teilnehmerregel «Eigene Fraktion» gespeichert ist.
    - Dynamisches Verhalten: Einschalten fügt genau eine Regel «Eigene Fraktion» hinzu (mehrfach ist nicht möglich), Ausschalten entfernt alle solchen Regeln.
    - Speichern: **sofort** bei jeder Umschaltung.
2. **Mehrfachauswahl «Einzelne Mitglieder»** — Beschriftung «Einzelne Mitglieder», Platzhalter «Mitglieder hinzufügen…».
    - Art: Mehrfachauswahl mit Suchfunktion; die Liste bleibt nach einer Wahl offen.
    - Pflichtfeld: nein.
    - Vorbelegung: die beim Typ gespeicherten Einzelmitglieder; ist ein gespeichertes Mitglied nicht mehr auffindbar, wird der gespeicherte Name angezeigt.
    - Auswahlwerte: alle aktiven Mitglieder, beschriftet «Vorname Name (Fraktionskürzel)» — das Kürzel entfällt, wenn beim Mitglied keine Fraktion hinterlegt ist. Reihenfolge: alphabetisch nach «Vorname Name» (aktive zuerst; inaktive sind ausgefiltert). **Bereits gewählte Mitglieder verschwinden aus der Auswahlliste und erscheinen nur noch als Merkzeichen im Feld.**
    - Speichern: **sofort** bei jeder Änderung. Dabei bleiben alle nicht mitgliedsbezogenen Regeln (z.B. «Eigene Fraktion») unverändert erhalten.

Hinweis: Teilnehmerregeln der Arten «Ganze Fraktion», «Ganze Kommission», «Fraktions-Rolle», «Nextcloud-Gruppe» und «Nextcloud-Benutzer» lassen sich in diesem Dialog nicht erfassen; sie stehen erst beim Anlegen einer einzelnen Sitzung zur Verfügung. Bereits gespeicherte Regeln dieser Arten bleiben erhalten, sofern sie vollständig sind.

#### Dialog «Sitzungstyp bearbeiten» — Bereich «Optionen»

Umrandeter Bereich mit der Bereichsbeschriftung «Optionen».

1. **Schalter «Beim Anlegen Verknüpfung mit anderen Sitzungen anbieten»**.
    - Art: Schalter (ein/aus).
    - Vorbelegung: der gespeicherte Wert; bei einem neu erstellten Typ ausgeschaltet.
    - **Dynamisches Verhalten: eingeschaltet erscheint im Formular «Neue Sitzung» dieses Typs zusätzlich das Feld «Verknüpfen mit»; ausgeschaltet fehlt es dort vollständig.**
    - Speichern: **sofort** bei jeder Umschaltung.
2. **Mehrfachauswahl «Kommissionen beraten (hängige Geschäfte automatisch verknüpfen)»** — Beschriftung wie angegeben, Platzhalter «Kommissionen wählen…».
    - Art: Mehrfachauswahl mit Suchfunktion.
    - Pflichtfeld: nein.
    - Vorbelegung: die beim Typ gespeicherten Kommissionen.
    - Auswahlwerte: **alle** Kommissionen der Kommissionsverwaltung (auch inaktive), angezeigt in der Kurzform gemäss den konfigurierten Kürzeln, in der vom Server gelieferten Reihenfolge. Bereits gewählte Kommissionen verschwinden aus der Auswahlliste und erscheinen nur noch als Merkzeichen im Feld.
    - Wirkung: für die gewählten Kommissionen werden bei Sitzungen dieses Typs hängige Geschäfte automatisch verknüpft.
    - Speichern: **sofort** bei jeder Änderung.

Die beim Anlegen gesetzten Einstellungen «Kalender anlegen» und «Einladung versenden» haben in diesem Dialog kein Bedienelement; sie bleiben auf ihrem gespeicherten Wert.

---

### Notizen an der Sitzung

Für Notizen an einer Sitzung, an einem geschäftslosen Traktandum und — in reiner Ansicht — an verknüpften Sitzungen wird **dieselbe geteilte Notizenliste** verwendet wie bei Geschäften und Vorstössen (Aufbau, Bedienung, Formatierungsleiste und Versionsverlauf siehe «Geteilte Bausteine → Notizenliste» und «Geteilte Bausteine → Formatierter Textbereich»). Es gibt keine davon abweichende, eigene Notiz-Komponente und kein abweichendes Aussehen.

Unterschiede je Ort:

- **Notizen zur Sitzung** — Bereichsüberschrift «Notizen zur Sitzung»; die Notizen haften an der Sitzung.
- **Notiz zum Traktandum (ohne Geschäft)** — Hinweistext «Notiz zum Traktandum»; die Notiz haftet am Traktandum.
- **Verknüpfte Sitzung — reine Ansicht** — die Notizenliste erscheint im Nur-Lese-Modus: kein «+ Neue Notiz», kein Bearbeiten, kein Löschen. Auch eigene Notizen sind hier nicht anklickbar.

Wie überall gilt: Speichern ausschliesslich über «✓» (kein automatisches Zwischenspeichern, kein Speichern beim Verlassen des Feldes), Löschen über den einheitlichen «✕»-Knopf (die Notiz bleibt erhalten und lässt sich über die Aktionszeitleiste wiederherstellen), und der Versionsverlauf steht auch hier zur Verfügung.

---

### Sitzungsnotiz zum Geschäft

Diese Liste tritt in beiden Traktandendarstellungen an die Stelle der einfachen Notizenliste, sobald dem Traktandum ein Geschäft zugeordnet ist. Die Notizen haften am Geschäft und erscheinen an jeder Sitzung, in der es traktandiert ist.

1. **Hinweistext «Sitzungsnotiz zum Geschäft»** — reine Anzeige über der Liste.
2. **Hinweis «Noch keine Notizen vorhanden.»** — erscheint, solange keine Notiz existiert und kein Editor offen ist.
3. **Je Notiz:**
    1. **Autor** — Anzeigename, ersatzweise Benutzerkennung.
    2. **Datum und Uhrzeit** — Erstellungszeitpunkt im Schweizer Format.
    3. **Löschknopf «✕»** (einheitlicher Löschknopf, kein Papierkorb-Symbol) — Hinweistext «Notiz löschen»; **nur bei eigenen Notizen** und nur, solange die Notiz nicht gerade bearbeitet wird. Die Notiz verschwindet aus dieser Liste und erscheint danach als Lösch-Vermerk in der Aktionszeitleiste der Traktandenzeile (siehe Punkt 5).
    4. **Notiztext** — formatierter Text; eigene Notizen sind anklickbar bzw. per Eingabetaste auslösbar (Hinweistext «Klicken zum Bearbeiten») und öffnen den Editor an Ort und Stelle.
4. **Bearbeitungszustand einer Notiz:**
    1. **Texteditor** — Platzhalter «Notiz bearbeiten…», vorbelegt mit dem bisherigen Text, mit vollständiger Formatierungsleiste **und zusätzlich den Knöpfen zum Blättern in früheren Fassungen**: «←» (Eine Version zurück), «→» (Eine Version vorwärts) und «»» (Zur neuesten Version). Sie erscheinen nur, wenn frühere Fassungen vorliegen; «→» und «»» nur, während eine ältere Fassung angezeigt wird. Dann steht rechts der Statustext «Ältere Fassung – nur Ansicht», und **der Editor ist gesperrt**.
    2. **Knopf «✓»** — Hinweistext «Speichern». Ausserhalb des Blätterns schliesst er die Bearbeitung ab. **Wird gerade eine ältere Fassung angezeigt, stellt er diese wieder her**: der Arbeitsstand wird zuerst gesichert, danach die angezeigte alte Fassung als neue aktuelle Fassung übernommen.
    3. **Knopf «✕»** — Hinweistext «Abbrechen». Schliesst den Editor ohne weiteres Speichern.
    - **Speichern nur bewusst über «✓» (dabei wird die bisherige Fassung im Verlauf archiviert); es gibt kein automatisches Zwischenspeichern und kein Speichern beim Verlassen des Feldes. Nach dem Speichern erscheint die Meldung «Notiz gespeichert».** Ein leerer Text löscht die Notiz **nicht** — dafür ist der Löschknopf da. Bei ungespeicherten Änderungen wird beim Verlassen der Seite bzw. beim Schliessen gewarnt.
5. **Gelöschte Notiz** — sie verschwindet aus dieser Liste und erscheint als Vermerk «‹Autor› hat seine Notiz gelöscht» in der **Aktionszeitleiste der Traktandenzeile** (dieselbe geteilte Aktionszeitleiste wie beim Geschäft/Vorstoss; sie erscheint hier nur, sobald eine Sitzungsnotiz gelöscht ist). Der ursprüngliche Text bleibt verborgen. Dort steht daneben — **nur für den Verfasser** — der Knopf **«↺»** (Hinweistext «Löschen rückgängig machen»): er holt die Notiz mitsamt Verlauf zurück in die Liste; danach erscheint «Notiz wiederhergestellt».
6. **Knopf «+ Neue Notiz»** — Hinweistext «Neue Notiz»; am Ende der Liste. Öffnet einen leeren Editor mit dem Platzhalter «Kommentar, Beobachtung, Hinweis» und den Knöpfen «✓» (Speichern) und «✕» (Abbrechen). Ist bereits ein Editor mit ungespeicherten Änderungen offen, wird vorher nachgefragt, ob verworfen werden darf. Eine leer gelassene neue Notiz wird nicht angelegt.

---

### App-Rahmen (Grundgerüst der App-Seite)

#### Aufbau der Seitenleiste

1. **Suchbereich** — oberster Eintrag der Seitenleiste, noch vor allen Navigationseinträgen. Art: Behälter, den die jeweils geöffnete Ansicht mit ihrem eigenen Suchfeld füllt. Vorbelegung: leer. Er bleibt komplett leer, solange die geöffnete Ansicht kein Suchfeld mitbringt.
    - Gefüllt wird er von den Ansichten «Geschäfte» (Platzhalter «Nr. oder Titel»), «Sitzungen», «Mitglieder» (Platzhalter «Name, Partei oder E-Mail»), «Kommissionen» (Platzhalter «Name oder Beschreibung»), «Vorstösse» und «Sitzungstypen».
    - Leer bleibt er ausschliesslich in der Ansicht «Änderungsverlauf».
    - Das Suchfeld erscheint erst, sobald die Ansicht fertig aufgebaut ist (unmittelbar nach dem Umschalten, für den Anwender praktisch sofort).
2. **Navigationseinträge** — feste Reihenfolge, jeder Eintrag mit Symbol und Beschriftung; der Eintrag der geöffneten Ansicht ist hervorgehoben:
    1. «Geschäfte» (Vorbelegung: diese Ansicht ist beim Öffnen der App aktiv)
    2. «Sitzungen»
    3. «Mitglieder»
    4. «Kommissionen»
    5. «Vorstösse»
    6. «Sitzungstypen»
    7. «Änderungsverlauf»
    - Art: Knopf-artige Navigationseinträge. Pflichtfeld: entfällt. Ein Klick wechselt sofort die Ansicht im Hauptbereich; Inhalte von Such- und Filterbereich werden dabei durch die Elemente der neuen Ansicht ersetzt. Gespeichert wird nichts, die Auswahl gilt nur für die laufende Arbeit am Bildschirm und fällt beim Neuladen der Seite auf «Geschäfte» zurück. Nie gesperrt.
3. **Filterbereich** — unterster Eintrag der Seitenleiste, unterhalb aller Navigationseinträge. Art: Behälter, den die geöffnete Ansicht mit ihren Sortier- und Filterelementen füllt. Vorbelegung: leer.
    - Gefüllt wird er von «Geschäfte», «Sitzungen», «Mitglieder», «Kommissionen» und «Vorstösse».
    - Leer bleibt er in «Sitzungstypen» (diese Ansicht bringt nur ein Suchfeld mit) und in «Änderungsverlauf» (weder Suche noch Filter).
4. **Versionsanzeige** — Fusszeile der Seitenleiste, ganz unten. Art: Statustext in der Form «v» gefolgt von der Versionsnummer (z. B. «v1.7.12»). Quelle: die von der Serverseite mitgelieferte App-Version. Bedingte Sichtbarkeit: erscheint nur, wenn eine Version geliefert wurde; sonst bleibt die Fusszeile ganz weg. Nicht bearbeitbar, keine Speicherung.

#### Meldungs- und Statusanzeigen im Hauptbereich

1. **Meldungsstreifen zur Synchronisation** — oberhalb des Ansichtsinhalts. Art: farbiger Statustext (grün bei Erfolg, rot bei Fehler). Bedingte Sichtbarkeit: nur wenn eine Meldung vorliegt; im ausgelieferten Stand wird an dieser Stelle keine Meldung gesetzt, der Streifen bleibt daher unsichtbar.
2. **Verbindungsanzeige** — kleines Anzeigeelement, das direkt neben dem App-Inhalt eingehängt wird. Art: Statusanzeige mit drei Zuständen:
    - «Verbindung wird hergestellt» beim Laden der Seite; Hinweistext wörtlich «WebSocket-Status: Verbindung wird hergestellt...»
    - «verbunden», sobald die WebSocket-Verbindung steht; Beschriftung für Hilfstechnologien wörtlich «WebSocket verbunden»
    - «getrennt» bei Verbindungsabbruch; Beschriftung wörtlich «WebSocket getrennt»
    - Verhalten: Nach einem Abbruch wird automatisch neu verbunden — zunächst nach 1 Sekunde, danach mit jeweils verdoppelter Wartezeit bis höchstens 15 Sekunden. Keine Bedienung möglich, keine Speicherung.
3. **Automatische Aktualisierung** — ohne Bedienelement: Meldet die WebSocket-Verbindung eine abgeschlossene Synchronisation, geänderte Mitglieder oder geänderte Fraktionsrollen, werden Mitglieder-, Fraktions- und Kommissionsdaten im Hintergrund neu geladen; alle Ansichten zeigen danach die neuen Werte.

#### Reihenfolge der Mitgliederdaten im Hintergrund

1. Die App lädt beim Start die Mitgliederliste und sortiert sie einmal grundsätzlich: zuerst alle aktiven, danach die ehemaligen Mitglieder; innerhalb der Gruppen alphabetisch nach «Vorname Name». Diese Grundreihenfolge gilt überall dort, wo eine Ansicht nicht selbst anders sortiert.

---

### Ansicht «Mitglieder»

#### Suchbereich der Seitenleiste

1. **Suchfeld** — Beschriftung «Suche», Platzhalter «Name, Partei oder E-Mail». Art: Textfeld mit Löschknopf. Pflichtfeld: nein. Vorbelegung: leer (wird bei jedem Wechsel in die Ansicht neu leer aufgebaut).
    - Der Löschknopf (Kreuz-Symbol am rechten Feldrand) erscheint nur, solange Text im Feld steht; ein Klick leert das Feld sofort.
    - Dynamisches Verhalten: Jede Eingabe filtert die Kartenliste unmittelbar (ohne Bestätigen). Gesucht wird ohne Berücksichtigung von Gross-/Kleinschreibung als Textbestandteil in: «Name Vorname», Partei, Fraktion, E-Mail-Adresse.
    - Der Treffer-Zähler in der Kopfzeile der Ansicht ändert sich mit.
    - Keine Speicherung; nie gesperrt.

#### Filterbereich der Seitenleiste — Gruppe «Sortieren»

1. **Überschrift «Sortieren»** — reiner Titeltext, keine Bedienung.
2. **Auswahlliste «Sortieren nach»** — Art: einfache Auswahlliste, nicht leerbar (es ist immer genau ein Wert gewählt). Pflichtfeld: ja, immer belegt. Vorbelegung: «Funktion».
    - Auswahlwerte, fest in dieser Reihenfolge: «Funktion», «Fraktion», «Partei», «Name».
    - Wirkung «Funktion»: Reihenfolge nach Rang — 1. Fraktionspräsident, 2. Vize Fraktionspräsident, 3. Kommissionspräsident, 4. übrige Mitglieder mit Kommissionssitz, 5. alle übrigen; bei gleichem Rang nach Partei, danach nach «Name Vorname».
    - Wirkung «Fraktion»: nach Fraktionsname, bei Gleichstand nach «Name Vorname».
    - Wirkung «Partei»: nach Parteiname, bei Gleichstand nach «Name Vorname».
    - Wirkung «Name»: nach «Name Vorname».
    - Sortiert wird sofort bei Auswahl; keine Speicherung, die Einstellung fällt beim nächsten Öffnen der Ansicht wieder auf «Funktion» zurück. Nie gesperrt.

#### Filterbereich der Seitenleiste — Gruppe «Filter»

1. **Überschrift «Filter»** — reiner Titeltext, keine Bedienung.
2. **Auswahlliste «Fraktion»** — Art: einfache Auswahlliste, nicht leerbar. Pflichtfeld: ja, immer belegt. Vorbelegung: «Alle Fraktionen».
    - Auswahlwerte: erster Eintrag «Alle Fraktionen» (kein Filter), danach alle Fraktionsnamen, die in der aktuell zugrunde liegenden Mitgliedermenge vorkommen — ohne Doppelte, alphabetisch aufsteigend. Leere Fraktionsangaben werden weggelassen.
    - Angezeigt werden die Namen in der gekürzten Form gemäss den in der Verwaltung definierten Kürzeln; gefiltert wird immer nach dem vollständigen Originalnamen.
    - Dynamisches Verhalten: Die Liste hängt vom Schalter «Nur aktive Mitglieder» ab — ist er eingeschaltet, erscheinen nur Fraktionen, in denen aktive Mitglieder sitzen; beim Ausschalten kommen Fraktionen ehemaliger Mitglieder dazu.
    - Wirkt sofort, keine Speicherung, nie gesperrt.
3. **Auswahlliste «Partei»** — Art: einfache Auswahlliste, nicht leerbar. Vorbelegung: «Alle Parteien».
    - Auswahlwerte: «Alle Parteien», danach alle Parteinamen aus derselben zugrunde liegenden Mitgliedermenge, ohne Doppelte, alphabetisch; leere Angaben entfallen; Anzeige gekürzt, Filterung nach Originalnamen.
    - Hängt ebenfalls vom Schalter «Nur aktive Mitglieder» ab. Wirkt sofort, keine Speicherung.
4. **Auswahlliste «Kommission»** — Art: einfache Auswahlliste, nicht leerbar. Vorbelegung: «Alle Kommissionen».
    - Auswahlwerte: «Alle Kommissionen», danach **nur die Kommissionen, denen mindestens ein sichtbares Mitglied tatsächlich angehört** — eine Kommission ohne Mitglied in der zugrunde liegenden Menge entfällt (Grundprinzip: keine Kommission als Ballast, die keinen Treffer ergäbe) —, ohne Doppelte, alphabetisch nach deutschen Sortierregeln; Anzeige gekürzt.
    - Wirkung: zeigt nur Mitglieder, die in der gewählten Kommission sitzen.
    - Hängt vom Schalter «Nur aktive Mitglieder» ab (er bestimmt die zugrunde liegende Mitgliedermenge). Wirkt sofort, keine Speicherung.
5. **Auswahlliste «Funktion»** — Art: einfache Auswahlliste, nicht leerbar. Vorbelegung: «Alle Funktionen».
    - Auswahlwerte: «Alle Funktionen» und danach — in der festen Reihenfolge «Fraktionspräsident», «Kommissionspräsident» — **nur die Funktionen, die unter den sichtbaren Mitgliedern tatsächlich vorkommen** (gibt es keinen Kommissionspräsidenten, entfällt der Eintrag).
    - Wirkung «Fraktionspräsident»: nur Mitglieder, deren Fraktionsfunktion als Präsidium erkannt wurde (Stellvertretungen zählen hier nicht).
    - Wirkung «Kommissionspräsident»: nur Mitglieder, die in mindestens einer Kommission das Präsidium innehaben (Vizepräsidien zählen nicht).
    - Wirkt sofort, keine Speicherung.
6. **Schalter «Nur aktive Mitglieder»** — Art: Umschalter (ein/aus). Vorbelegung: eingeschaltet.
    - Wirkung eingeschaltet: Es werden ausschliesslich aktive Mitglieder angezeigt.
    - Wirkung ausgeschaltet: Ehemalige Mitglieder erscheinen zusätzlich, ihre Karten sind abgeschwächt dargestellt und mit einem Kennzeichen versehen.
    - Dynamisches Verhalten: Der Schalter verändert nicht nur die Kartenliste, sondern auch den Inhalt der Auswahllisten «Fraktion» und «Partei» (siehe dort). Auf «Kommission» und «Funktion» hat er keinen Einfluss.
    - Wirkt sofort, keine Speicherung, nie gesperrt.

#### Kopfzeile und Kartenliste

1. **Titel «Mitglieder»** — Statustext.
2. **Trefferzähler** — Statustext (Zahl) direkt rechts vom Titel; zeigt die Anzahl der aktuell angezeigten Karten und ändert sich mit jeder Sucheingabe und jedem Filter.
3. **Mitgliederkarte** — je Mitglied eine Karte im Raster; Reihenfolge gemäss gewählter Sortierung. Inhalt in dieser Reihenfolge:
    1. **Name** — «Vorname Name», hervorgehoben.
    2. **Partei** — gekürzter Parteiname; ist keine Partei hinterlegt, steht dort «Ohne Partei».
    3. **Fraktionsfunktion** — Marke, nur sichtbar wenn das Mitglied in einer aktiven Fraktion eine Präsidialfunktion hat; wörtlich entweder «Fraktionspräsident» oder «Vize Fraktionspräsident».
    4. **Angabe «Fraktion»** — Beschriftung «Fraktion» und daneben der gekürzte Fraktionsname; ohne Fraktion steht dort ein Gedankenstrich «—».
    5. **Angabe «Kommission»** — Beschriftung «Kommission» und daneben alle Kommissionen des Mitglieds; nur sichtbar, wenn das Mitglied mindestens einer aktiven Kommission angehört. Je Eintrag der gekürzte Kommissionsname und — sofern vorhanden — die Rolle «Präsident» oder «Vizepräsident». Reihenfolge: zuerst Präsidien, dann Vizepräsidien, danach alphabetisch nach Kommissionsname.
    6. **E-Mail-Adresse** — anklickbarer Verweis, der das Mailprogramm öffnet; nur sichtbar, wenn eine Adresse hinterlegt ist.
    7. **Kennzeichen «Ehemaliges Mitglied»** — Marke, nur sichtbar bei nicht mehr aktiven Mitgliedern (und damit nur, wenn der Schalter «Nur aktive Mitglieder» ausgeschaltet ist).
    - Die Karten sind reine Anzeige: nichts ist bearbeitbar, nichts wird gespeichert.
4. **Hinweis «Keine Mitglieder gefunden»** — Leermeldung, erscheint anstelle von Karten, sobald Suche und Filter keine Treffer ergeben.

---

### Ansicht «Kommissionen»

#### Suchbereich der Seitenleiste

1. **Suchfeld** — Beschriftung «Suche», Platzhalter «Name oder Beschreibung». Art: Textfeld mit Löschknopf (Kreuz), der nur bei gefülltem Feld erscheint. Pflichtfeld: nein. Vorbelegung: leer.
    - Gesucht wird ohne Rücksicht auf Gross-/Kleinschreibung in: Kommissionsname, Beschreibung, allen Mitgliedern der Kommission (Name, Partei, Fraktion, E-Mail, Funktion) sowie in den pendenten Geschäften (Geschäftsnummer und Titel).
    - Dynamisches Verhalten: Sobald Text eingegeben wird, klappen die Mitglieder jeder Karte auf, in der **ein Mitglied** der Treffer ist — die Geschäfte stehen ohnehin offen, und ein Treffer im Kommissionsnamen, in der Beschreibung oder in einem Geschäft klappt darum nichts auf. Von Hand aufgeklappte Mitglieder bleiben offen; beim Leeren des Feldes wird nichts wieder zugeklappt.
    - Wirkt sofort, keine Speicherung, nie gesperrt.

#### Filterbereich der Seitenleiste

1. **Schalter «Nur aktive Kommissionen»** — Art: Umschalter. Vorbelegung: eingeschaltet.
    - Wirkung eingeschaltet: aufgelöste bzw. inaktive Kommissionen werden ausgeblendet. Als inaktiv gilt eine Kommission, die als gelöscht gemeldet ist, deren Aktiv-Kennzeichen aus der Synchronisation «nein» lautet, oder deren Enddatum in der Vergangenheit liegt.
    - Wirkung ausgeschaltet: Auch aufgelöste Kommissionen erscheinen, abgeschwächt dargestellt und mit entsprechendem Statustext und Hinweis.
    - Wirkt sofort, keine Speicherung.
2. **Schalter «Nur aktive Mitglieder»** — Art: Umschalter. Vorbelegung: eingeschaltet.
    - Wirkung: Steuert, ob in der Mitgliederliste einer Karte auch ausgeschiedene Kommissionsmitglieder aufgeführt werden. Ausgeschaltet erscheinen sie zusätzlich, abgeschwächt dargestellt und immer am Ende der Mitgliederliste.
    - Dynamisches Verhalten: Ist der Schalter ausgeschaltet und weicht die Zahl der angezeigten von der Zahl der aktiven Mitglieder ab, wird im Schalter «Mitglieder» zusätzlich die Zahl der aktiven Mitglieder ausgewiesen.
    - Wirkt sofort, keine Speicherung.

#### Kopfzeile und Kartenliste

1. **Titel «Kommissionen»** — Statustext.
2. **Trefferzähler** — Statustext (Zahl) rechts vom Titel; Anzahl der aktuell angezeigten Kommissionen.
3. **Ladeanzeige** — drehendes Symbol, sichtbar solange die Kommissionen geladen werden; danach erscheint die Kartenliste.
4. **Kommissionskarte, Kopfbereich** — je Kommission eine Karte; der Kopfbereich ist nicht anklickbar, die Karte steht immer offen:
    1. **Kommissionsname** — gekürzt gemäss den in der Verwaltung definierten Kürzeln.
    2. **Statustext** — wörtlich «Aktiv» oder «Aufgelöst oder inaktiv».
5. **Leermeldung «Keine Kommissionen gefunden»** — erscheint, wenn Suche und Schalter keine Kommission übrig lassen.

#### Inhalt einer Kommissionskarte

1. **Beschreibung** — Fliesstext; nur sichtbar, wenn zur Kommission eine Beschreibung vorliegt.
2. **Zwischenüberschrift «Pendente Geschäfte (n):»** — mit der Anzahl in Klammern; nur sichtbar, wenn mindestens ein pendentes Geschäft zugeordnet ist.
3. **Liste der pendenten Geschäfte** — je Geschäft eine Zeile, bestehend aus:
    1. **Geschäftsnummer** — Statustext.
    2. **Verweis «↗»** — öffnet das Geschäft auf der Parlamentswebseite in einem neuen Fenster; Hinweistext wörtlich «Extern öffnen»; nur sichtbar, wenn eine Webadresse hinterlegt ist. Der Klick auf diesen Verweis öffnet nicht den Detaildialog.
    3. **Geschäftstitel** — Statustext.
    - Die ganze Zeile ist anklickbar (auch per Eingabe- oder Leertaste bedienbar) und öffnet den Detaildialog des Geschäfts.
    - Zuordnung: Es erscheinen Geschäfte, deren Status auf diese Kommission verweist (Statustexte der Form «Bei der Kommission … pendent»).
4. **Hinweis «Keine Geschäfte mit Status ‹Bei Kommission Name pendent›.»** — erscheint anstelle der Liste, wenn der Kommission kein Geschäft zugeordnet ist; der Kommissionsname erscheint darin gekürzt.
5. **Schalter «Mitglieder (n):»** — mit der Anzahl der angezeigten Mitglieder; ist der Schalter «Nur aktive Mitglieder» ausgeschaltet und unterscheiden sich die Zahlen, lautet er «Mitglieder (n / m aktiv):». Rechts davon das Aufklapp-Zeichen «▼» wenn zugeklappt, «▲» wenn aufgeklappt. Die ganze Zeile ist anklickbar (auch per Eingabe- oder Leertaste bedienbar) und zeigt bzw. verbirgt die Mitgliederkarten.
    - Vorbelegung: zugeklappt. Trifft eine Sucheingabe ein Mitglied dieser Kommission, klappt sie von selbst auf; ein Treffer allein im Namen, in der Beschreibung oder in einem Geschäft tut das nicht. Die Auf-/Zu-Zustände werden nicht gespeichert.
6. **Mitgliederkarten der Kommission** — nur sichtbar, solange der Schalter aufgeklappt ist; je Mitglied eine kleine Karte; Inhalt in dieser Reihenfolge:
    1. **Name** — hervorgehoben; stammt aus der Synchronisation bzw. aus dem zugeordneten Mitglied.
    2. **Funktion** — Marke, nur sichtbar wenn eine Funktion hinterlegt ist (z. B. Präsidium, Vizepräsidium, Protokollführung).
    3. **Partei** — gekürzt; nur sichtbar wenn bekannt.
    4. **Fraktion** — gekürzt; nur sichtbar wenn bekannt.
    5. **E-Mail-Adresse** — anklickbarer Verweis zum Mailprogramm; nur sichtbar wenn bekannt.
    - Darstellung: Mitglieder der eigenen Fraktion sind hervorgehoben. Die eigene Fraktion ergibt sich aus dem angemeldeten Benutzerkonto, sofern dieses einem Mitglied zugeordnet ist; ohne Zuordnung entfällt die Hervorhebung.
    - Ausgeschiedene Mitglieder sind abgeschwächt dargestellt.
    - Reihenfolge: zuerst alle aktiven, dann die ausgeschiedenen; innerhalb dessen 1. Präsidium, 2. Vizepräsidium, 3. einfache Mitglieder der eigenen Fraktion, 4. übrige einfache Mitglieder, 5. weitere Rollen (z. B. Protokollführung); bei Gleichstand alphabetisch nach Name.
7. **Hinweis «Keine Mitglieder synchronisiert.»** — erscheint anstelle der Mitgliederliste, wenn zur Kommission keine (sichtbaren) Mitglieder vorliegen.
8. **Hinweis «Diese Kommission erscheint nur, weil historische Daten vorhanden sind.»** — erscheint nur bei aufgelösten bzw. inaktiven Kommissionen (also nur bei ausgeschaltetem Schalter «Nur aktive Kommissionen»).

#### Detaildialog eines Geschäfts

1. **Dialogfenster** — öffnet sich über der Ansicht, sobald ein pendentes Geschäft angeklickt wird. Ein Klick auf die abgedunkelte Fläche neben dem Dialog schliesst ihn.
2. **Knopf «✕»** — oben rechts im Dialog, Beschriftung für Hilfstechnologien wörtlich «Dialog schliessen»; schliesst den Dialog.
3. **Geschäftsdetails** — im Dialog wird die vollständige Geschäftsansicht mit allen ihren Bedienelementen eingebettet. Nach dem Speichern im Dialog werden die Geschäfte der Kommissionsansicht neu geladen, so dass sich die Listen der pendenten Geschäfte sofort aktualisieren.

---

### Ansicht «Änderungsverlauf»

1. **Suchbereich und Filterbereich der Seitenleiste** — bleiben in dieser Ansicht vollständig leer; die Ansicht bringt weder Suchfeld noch Filter mit.
2. **Titel «Änderungsverlauf»** — Statustext in der Kopfzeile.
3. **Versionszähler** — Statustext (Zahl) rechts vom Titel; Anzahl der aufgeführten Versionen.
4. **Versionskarte** — je Version eine Karte, in der Reihenfolge der Änderungsliste (neueste Version zuoberst). Der Kopfbereich ist anklickbar und zusätzlich mit der Eingabetaste bedienbar; er enthält:
    1. **Versionsnummer** — Statustext.
    2. **Datum** — Statustext im Format «JJJJ-MM-TT».
    3. **Aufklapp-Zeichen** — «▼» wenn zugeklappt, «▲» wenn aufgeklappt.
5. **Änderungsliste einer Version** — nur im aufgeklappten Zustand sichtbar; zeigt die Änderungen als formatierte, gegebenenfalls verschachtelte Aufzählung mit Hervorhebungen und Verweisen.
    - Vorbelegung: Alle Versionen der neuesten Haupt-/Nebenversion (z. B. alle Einträge «1.7.x») sind aufgeklappt, alle älteren zugeklappt.
    - Ein Klick klappt die jeweilige Karte um; die Zustände werden nicht gespeichert und fallen beim nächsten Öffnen der Ansicht auf die Vorbelegung zurück.
6. Die Ansicht ist reine Anzeige — keine Eingabe, keine Speicherung, nichts gesperrt.

---

### Verwaltungsbereich

#### Zugang und Aufbau

1. **Sichtbarkeit** — Der gesamte Verwaltungsbereich liegt in den Nextcloud-Verwaltungseinstellungen im eigenen Abschnitt «Parlament Winterthur» und ist ausschliesslich für Administratoren sichtbar und bedienbar. Alle Aktionen darin (Synchronisation, Zuordnungen, Zeitplan, Kürzel, Einstellungen) werden zusätzlich serverseitig auf Administratorrechte geprüft; ohne Rechte erscheint die Meldung «Zugriff verweigert. Bitte als Admin anmelden und erneut versuchen.», ohne Anmeldung «Nicht angemeldet. Bitte neu anmelden und erneut versuchen.».
2. **Aufbau** — links eine schmale Spalte mit Synchronisation und Speicherstatus, rechts die Konfigurationskarten in dieser Reihenfolge: «Fraktionskonfiguration», «Fraktionsmitglieder ↔ Nextcloud-Benutzer», «Typen für eigene Geschäfte», «Automatische Synchronisation», «Kürzel», «E-Mail-Einladungen».

#### Synchronisation (linke Spalte)

1. **Knopf «Jetzt synchronisieren»** — Art: Knopf. Startet die Synchronisation mit der Parlamentswebseite.
    - Gesperrt: während eine Synchronisation läuft; ebenso unmittelbar nach dem Anklicken, bis die Antwort des Servers eintrifft.
    - Beim Klick werden Fortschrittsbalken auf 0, Prozentanzeige auf «0%» und die Detailzeile geleert, der Abbruchknopf freigegeben und der Statustext auf «Synchronisation wird gestartet...» gesetzt.
2. **Knopf «Synchronisierung abbrechen»** — Art: Knopf. Vorbelegung: gesperrt.
    - Freigegeben: sobald eine Synchronisation läuft.
    - Gesperrt: solange keine Synchronisation läuft und ebenso, sobald ein Abbruch bereits angefragt wurde.
    - Beim Klick erscheint zunächst «Abbruch wird angefordert...», nach Bestätigung durch den Server «Abbruch angefragt...».
3. **Statustext der Synchronisation** — Art: Statustext neben den Knöpfen. Mögliche Texte wörtlich:
    - «Synchronisation wird gestartet...», «Synchronisation gestartet», «Synchronisiere...», «Synchronisation läuft bereits»
    - «Abbruch wird angefordert...», «Abbruch angefragt...», «Synchronisation abgebrochen»
    - «Synchronisation abgeschlossen: ‹Zeitpunkt›»
    - «Fehler: ‹Meldung›» — als Meldung erscheinen unter anderem «Sicherheitsprüfung fehlgeschlagen (Token ungültig/abgelaufen). Seite neu laden und erneut versuchen.», «Sicherheitsprüfung fehlgeschlagen (HTTP 412). Seite neu laden und erneut versuchen.», «Zugriff verweigert. Bitte als Admin anmelden und erneut versuchen.», «Nicht angemeldet. Bitte neu anmelden und erneut versuchen.», «Synchronisations-Endpunkt nicht gefunden. Bitte App-Installation prüfen.», «Serverfehler beim Synchronisieren. Details im Nextcloud-Log prüfen.», «Verbindungsfehler», «Ungültige Serverantwort», «Unbekannter Fehler».
4. **Fortschrittsbalken** — Art: Fortschrittsbalken von 0 bis 100. Vorbelegung: 0.
    - Quelle: Gesamtfortschritt über alle aktiven Teilbereiche (Mitglieder, Fraktionen, Kommissionen, Geschäfte, Sitzungen) — Summe der verarbeiteten Datensätze geteilt durch die Summe aller erwarteten Datensätze.
    - Bei abgeschlossener Synchronisation wird er auf 100 gesetzt, im Ruhezustand auf 0.
5. **Prozentanzeige** — Art: Statustext rechts vom Balken, Vorbelegung «0%»; zeigt denselben Wert wie der Balken, gerundet auf ganze Prozent.
6. **Detailzeile zum Fortschritt** — Art: Statustext unterhalb des Balkens. Vorbelegung: leer.
    - Während des Laufs in der Form: «‹Teilbereich› (‹Datenbereich›) - ‹verarbeitet›/‹gesamt› | Gesamt ‹verarbeitet›/‹gesamt› (‹Prozent›%) | Quelle ‹Auslöser› | Restzeit ‹Restzeit› | Laufzeit ‹Dauer›».
    - Nach Abbruch oder Abschluss: «Laufzeit: ‹Dauer›».
    - Im Fehlerfall: der Fehlertext.
    - Im Ruhezustand: leer.
7. **Aktualisierung der Anzeige** — Der Fortschritt wird über die WebSocket-Verbindung sofort nachgeführt; zusätzlich fragt die Seite den Stand regelmässig ab (etwa alle 1,2 Sekunden, bei stehender Verbindung etwa alle 5 Sekunden) sowie immer dann, wenn das Fenster wieder in den Vordergrund kommt. Dadurch bleibt die Anzeige auch dann aktuell, wenn die Verbindung unterbrochen ist.
8. **Speicherstatus** — Art: Statustext unterhalb des Fortschritts. Vorbelegung wörtlich «Bereit für automatische Speicherung». Weitere Texte: «Änderungen erkannt...» (unmittelbar nach einer Eingabe), «Speichere automatisch...» (während des Speicherns), «Alle Änderungen gespeichert» (nach Erfolg oder wenn nichts zu speichern war), «Automatisches Speichern fehlgeschlagen» (bei Fehler; zusätzlich erscheint die kurzzeitige Systemmeldung «Fehler beim Speichern: ‹Meldung›»).
9. **Angabe «Letzte Synchronisation: ‹Zeitpunkt›»** — Art: Statustext. Bedingte Sichtbarkeit: nur wenn bereits einmal synchronisiert wurde; Quelle ist der gespeicherte Zeitpunkt der letzten erfolgreichen Synchronisation, gelesen beim Aufbau der Seite.

#### Karte «Fraktionskonfiguration»

1. **Erklärungstext** — wörtlich «Wähle Fraktion und Zielgruppe für die interne Fraktionsarbeit.»
2. **Auswahlliste «Fraktion (aus synchronisierten Fraktionen)»** — Art: Auswahlliste. Pflichtfeld: faktisch ja — ohne Fraktion bleibt die Mitgliederzuordnung leer und der Abgleich verweigert die Ausführung.
    - Vorbelegung: die gespeicherte Fraktion; ist keine gespeichert, der Eintrag «Bitte Fraktion wählen».
    - Auswahlwerte in dieser Reihenfolge: 1. «Bitte Fraktion wählen» (leerer Wert), 2. alle **aktiven** Fraktionen aus der synchronisierten Datenbank, ohne Doppelte, alphabetisch ohne Beachtung der Gross-/Kleinschreibung sortiert. Inaktive Fraktionen und namenlose Einträge werden ausgefiltert.
    - Zusatzeintrag: Ist die gespeicherte Fraktion nicht mehr unter den synchronisierten, erscheint sie als letzter Eintrag mit dem Zusatz «[nicht mehr synchronisiert]» und ist vorausgewählt.
    - Gesperrt: solange überhaupt keine Fraktionen in der Datenbank vorliegen; darunter erscheint dann der Hinweis «Noch keine Fraktionen in der Datenbank. Bitte zuerst synchronisieren.»
    - Dynamisches Verhalten: Eine Änderung speichert die Einstellungen sofort **und** lädt die Tabelle der Fraktionsmitglieder neu (Statustext «Lade Mitglieder...»). Wird eine Fraktion gewählt, die der Server nicht kennt, lehnt er das Speichern mit «Bitte eine vorhandene Fraktion aus der Liste wählen.» ab.
3. **Textfeld «Nextcloud-Gruppe (bestehend wählen oder neu erstellen)»** — Art: Textfeld mit Vorschlagsliste. Platzhalter wörtlich «z.B. Fraktion-SP-Gruene». Pflichtfeld: für den Abgleich der Mitglieder ja, sonst nein.
    - Vorbelegung: die gespeicherte Gruppe (Standard: leer).
    - Vorschlagswerte: alle bestehenden Nextcloud-Gruppen (bis zu 500), ohne Doppelte, alphabetisch ohne Beachtung der Gross-/Kleinschreibung; leere Namen entfallen. Eigene, noch nicht existierende Namen dürfen frei eingetippt werden.
    - **Zustandstext darunter**: leer solange das Feld leer ist; «Bestehende Gruppe» wenn der eingetippte Name (ohne Beachtung der Gross-/Kleinschreibung) einer beim Seitenaufbau bekannten Gruppe entspricht; sonst «Neue Gruppe wird angelegt».
    - Dynamisches Verhalten: Jede Änderung aktualisiert zusätzlich sofort die Spalte «Gruppen» aller Zeilen der Mitgliedertabelle.
    - Speicherung: 0,5 Sekunden nach der letzten Tasteneingabe automatisch; beim Verlassen bzw. bei einer Auswahl aus der Vorschlagsliste sofort.
4. **Speicherverhalten der ganzen Karte** — Alle Felder ausserhalb der Mitgliedertabelle speichern automatisch: verzögert 0,5 Sekunden nach der letzten Tasteneingabe, sofort bei abgeschlossener Änderung (Verlassen des Feldes, Auswahl in einer Liste). Ist der Inhalt unverändert, wird nicht erneut gespeichert und es erscheint direkt «Alle Änderungen gespeichert». Ein Absenden per Eingabetaste löst ebenfalls sofortiges Speichern aus und lädt die Seite nicht neu.

#### Karte «Fraktionsmitglieder ↔ Nextcloud-Benutzer»

1. **Erklärungstext** — wörtlich «Nach der Wahl der Fraktion lassen sich die Mitglieder lokalen Benutzern zuordnen und die ausgewählten anlegen.»
2. **Knopf «Ausgewählte abgleichen»** — Art: Knopf. Legt fehlende Benutzerkonten an, gleicht bestehende ab und deaktiviert angekreuzte verwaiste Konten.
    - Vor der Ausführung werden die Einstellungen der Seite sofort gespeichert.
    - Abbruchbedingungen mit Statustext statt Ausführung: ohne gewählte Fraktion «Bitte zuerst eine Fraktion wählen.»; ohne Gruppenname «Bitte eine Nextcloud-Gruppe setzen.»; ohne angekreuzte Zeile «Bitte mindestens einen Eintrag auswählen.»
    - Während der Ausführung: «Gleiche Benutzer ab...»; danach «Angelegt: ‹Anzahl›, abgeglichen: ‹Anzahl›, deaktiviert: ‹Anzahl›», bei Problemen ergänzt um « — » und die Warnungstexte (z. B. dass ein Konto nicht angelegt oder nicht deaktiviert werden konnte).
    - Was passiert: Für Mitglieder ohne bestehendes Konto wird ein neues Benutzerkonto mit dem angezeigten Benutzernamen angelegt und eine Willkommens-/Einladungsmail verschickt; für Mitglieder mit bestehendem Konto werden Profilfelder abgeglichen, das Konto bei Bedarf wieder aktiviert und der Gruppe zugewiesen — ohne Mail; angekreuzte verwaiste Konten werden aus der Gruppe entfernt und deaktiviert — ohne Mail. Die Tabelle wird anschliessend neu aufgebaut.
    - Nie dauerhaft gesperrt.
3. **Statustext der Mitgliederkarte** — Art: Statustext neben dem Knopf. Texte: «Lade Mitglieder...», «‹Anzahl› Mitglieder geladen», «‹Anzahl› Mitglieder geladen, ‹Anzahl› verwaiste lokale User», «Speichere Zuordnungen...», «‹Anzahl› Zuordnungen gespeichert», «Gleiche Benutzer ab...», das Abgleichsergebnis (siehe oben) sowie «Fehler: ‹Meldung›».
4. **Hinweiszeile über der Tabelle** — Art: Statustext. Vorbelegung wörtlich «Bitte zuerst eine Fraktion wählen.»
    - Gleicher Text, solange keine Fraktion gewählt ist.
    - «Keine aktiven Mitglieder für diese Fraktion gefunden.» wenn die gewählte Fraktion weder Mitglieder noch verwaiste Konten liefert.
    - Leer, sobald Zeilen angezeigt werden.
5. **Tabelle der Fraktionsmitglieder** — Spalten in dieser Reihenfolge:
    1. **Spalte Auswahl** — Kopf: Ankreuzfeld «Alle wählen» (Beschriftung für Hilfstechnologien). Vorbelegung: angekreuzt, sobald Zeilen vorhanden sind; nicht angekreuzt, wenn die Tabelle leer ist. Ein Klick setzt oder entfernt das Häkchen in **allen** Zeilen, auch bei den verwaisten.
    2. **Spalte «Mitglied»** — Tabellenspalte, Anzeige des vollständigen Namens aus der Synchronisation.
    3. **Spalte «E-Mail»** — Tabellenspalte, Anzeige der bei der Synchronisation gefundenen Adresse; leer wenn keine bekannt ist.
    4. **Spalte «Benutzername»** — Tabellenspalte mit Textfeld je Zeile (siehe unten).
    5. **Spalte «Gruppen»** — Tabellenspalte mit Statustext je Zeile (siehe unten).
    - Die Zeilen erscheinen nur, wenn eine Fraktion gewählt ist; aufgeführt werden ausschliesslich die **aktiven** Mitglieder der gewählten Fraktion, danach die verwaisten Konten.
6. **Zeile eines Fraktionsmitglieds**:
    1. **Ankreuzfeld** — Vorbelegung: angekreuzt. Bestimmt, ob das Mitglied beim Abgleich berücksichtigt wird.
    2. **Name** — Statustext, nicht bearbeitbar.
    3. **E-Mail** — Statustext, nicht bearbeitbar.
    4. **Textfeld Benutzername** — Pflichtfeld: nein; bleibt es leer, wird beim Speichern automatisch der Vorschlag verwendet. Vorbelegung: der gespeicherte Benutzername bzw. — wenn bereits ein passendes Konto gefunden wurde — dessen Benutzername; ist nichts gespeichert, der Vorschlag. Platzhalter: der automatische Vorschlag, gebildet aus «Vorname-Name» in Kleinbuchstaben mit umgeschriebenen Umlauten und Sonderzeichen.
        - Gesperrt: wenn zu diesem Mitglied bereits ein lokales Benutzerkonto gefunden wurde; der Hinweistext nennt dann wörtlich «Lokales Benutzerkonto existiert bereits (‹Fundart›)» mit der Fundart (über Benutzername, über E-Mail-Adresse oder über Anzeigename).
        - Dynamisches Verhalten: Jede Tasteneingabe aktualisiert sofort die Spalte «Gruppen» derselben Zeile und setzt die Zeile auf «noch kein Konto vorhanden» zurück.
        - Speicherung: automatisch 0,5 Sekunden nach der letzten Tasteneingabe; dabei erscheint «Speichere Zuordnungen...» und danach «‹Anzahl› Zuordnungen gespeichert». Die allgemeine Einstellungsspeicherung der Seite wird durch Eingaben in der Tabelle ausdrücklich **nicht** ausgelöst.
    5. **Statustext Gruppen** — nicht bearbeitbar; Inhalt abhängig vom Zustand: bei bestehendem Konto die konfigurierte Fraktionsgruppe zusammen mit allen bereits vorhandenen Gruppen des Kontos, kommagetrennt und ohne Doppelte; bei leerem Benutzernamen wörtlich «Bitte Benutzername setzen»; sonst nur die konfigurierte Fraktionsgruppe (die beim Abgleich zugewiesen würde).
7. **Zeile eines verwaisten Kontos** — erscheint für jedes Konto, das in der konfigurierten Nextcloud-Gruppe ist, aber keinem aktiven Mitglied der gewählten Fraktion entspricht. Sichtbar nur, wenn eine Gruppe konfiguriert ist.
    1. **Ankreuzfeld** — Vorbelegung: **nicht** angekreuzt. Angekreuzt bedeutet: beim Abgleich aus der Gruppe entfernen und Konto deaktivieren.
    2. **Name** — durchgestrichen dargestellt; Hinweistext wörtlich «In Nextcloud-Gruppe, aber laut Webseite nicht mehr in der Fraktion. Anwählen zum Deaktivieren.»
    3. **E-Mail** — durchgestrichen dargestellt, aus dem Benutzerkonto.
    4. **Benutzername** — Statustext (Benutzername des Kontos), nicht bearbeitbar.
    5. **Gruppen** — Statustext mit der Fraktionsgruppe und den weiteren Gruppen des Kontos.

#### Karte «Typen für eigene Geschäfte»

1. **Erklärungstext** — wörtlich «Diese Typen stehen beim Anlegen eines eigenen Geschäfts zur Auswahl. Ohne Eintrag bleibt es beim Typ ‹Eigenes Geschäft›.»
2. **Typenzeilen** — beliebig viele; Vorbelegung beim Öffnen: die gespeicherten Typen in gespeicherter Reihenfolge. Je Zeile in dieser Reihenfolge:
    1. **Textfeld Bezeichnung** — Platzhalter «z.B. Kommissionsgeschäft». Pflichtfeld: für das Speichern der Zeile ja (leere Zeilen zählen nicht).
    2. **Löschknopf «×»** — Hinweistext «Löschen»; entfernt die Zeile sofort und stösst das Speichern an.
3. **Knopf «+ Typ hinzufügen»** — fügt am Ende eine leere Zeile an und setzt den Schreibcursor hinein. Speichert selbst noch nicht.
4. **Statustext** — «Speichern...» unmittelbar nach jeder Änderung, danach «Gespeichert» oder «Fehler beim Speichern».
5. **Speicherung** — Jede Änderung an einem Textfeld und jedes Löschen einer Zeile startet die Speicherung; ausgeführt wird sie kurz nach der letzten Änderung, wobei stets die komplette Liste ersetzt wird. Dabei werden leere Einträge und Doppelte entfernt, die Reihenfolge bleibt erhalten. Kein Speichern-Knopf, keine Sperre.

#### Karte «Automatische Synchronisation»

1. **Erklärungstext** — wörtlich «Zeitplan der automatischen Synchronisation: beliebige Einträge mit Wochentagen und Uhrzeit. Ohne Eintrag wird an allen Wochentagen um 10:00 und 18:00 Uhr synchronisiert.»
2. **Zeitplanzeilen** — beliebig viele; Vorbelegung beim Öffnen: der gespeicherte Zeitplan. Ist keiner gespeichert, erscheinen zwei Zeilen mit allen sieben Wochentagen und den Uhrzeiten 10:00 und 18:00, die direkt bearbeitet werden können. Je Zeile in dieser Reihenfolge:
    1. **Sieben Ankreuzfelder** mit den Beschriftungen «Mo», «Di», «Mi», «Do», «Fr», «Sa», «So» — in genau dieser Reihenfolge. Vorbelegung: gemäss gespeichertem Eintrag; bei einer neu hinzugefügten Zeile keiner angekreuzt.
    2. **Uhrzeitfeld** — Art: Zeiteingabefeld (Stunden/Minuten). Vorbelegung: gespeicherte Uhrzeit; bei neuer Zeile leer.
    3. **Löschknopf «×»** — Hinweistext wörtlich «Löschen»; entfernt die Zeile sofort und stösst das Speichern an.
    - Pflichtfeld: Eine Zeile zählt nur, wenn mindestens ein Wochentag angekreuzt **und** eine Uhrzeit gesetzt ist; unvollständige Zeilen werden beim Speichern stillschweigend verworfen (sie bleiben aber sichtbar, bis die Seite neu geladen wird).
3. **Knopf «+ Zeit hinzufügen»** — Art: Knopf. Fügt am Ende eine leere Zeile an und setzt den Schreibcursor in deren Uhrzeitfeld. Speichert selbst noch nicht.
4. **Statustext des Zeitplans** — Art: Statustext neben dem Knopf. «Speichern...» unmittelbar nach jeder Änderung, danach «Gespeichert» (verschwindet nach rund 2,5 Sekunden von selbst) oder «Fehler beim Speichern» (bleibt stehen).
5. **Speicherung** — Jede Änderung an einem Ankreuzfeld, an der Uhrzeit oder das Löschen einer Zeile startet die Speicherung; ausgeführt wird sie 5 Sekunden nach der letzten Änderung, wobei stets der komplette Zeitplan ersetzt wird. Kein Speichern-Knopf, keine Sperre.

#### Karte «Kürzel»

1. **Erklärungstext** — wörtlich «Lange Bezeichnungen überall in der App kürzen — gilt für Status, Parteien, Fraktionen und Kommissionen: Suchtext eingeben (Vorschläge aus bestehenden Status-Werten sowie den aktuellen Fraktions- und Parteinamen) und gewünschtes Kürzel definieren. Beispiele:»
2. **Beispielliste** — drei Einträge, wörtlich:
    1. «Kommissionsname → Kürzel: ‹Kommission Bildung, Sport und Kultur› → ‹BSKK›»
    2. «Status-Text → Kurzform: ‹Bei der Kommission Bildung, Sport und Kultur pendent› → ‹Pendent: BSKK›»
    3. «Fraktions- oder Parteiname → Abkürzung: ‹Sozialdemokratische Partei› → ‹SP›»
3. **Kürzelzeilen** — beliebig viele; Vorbelegung beim Öffnen: die gespeicherten Einträge in gespeicherter Reihenfolge. Je Zeile in dieser Reihenfolge:
    1. **Textfeld Suchtext** — Platzhalter wörtlich «Suchtext», mit Vorschlagsliste. Pflichtfeld: für das Speichern der Zeile ja.
        - Vorschlagswerte, in dieser Ladereihenfolge und ohne Doppelte: alle bei den Geschäften vorkommenden Statustexte (auch von erledigten Geschäften, bis 2000 Geschäfte), danach die Namen aller aktiven Fraktionen, danach die Parteinamen aller aktiven Mitglieder.
    2. **Textfeld Kürzel** — Platzhalter wörtlich «Kürzel». Pflichtfeld: für das Speichern der Zeile ja.
    3. **Löschknopf «×»** — Hinweistext wörtlich «Löschen»; entfernt die Zeile sofort und stösst das Speichern an.
4. **Knopf «+ Eintrag hinzufügen»** — Art: Knopf. Fügt eine leere Zeile an und setzt den Schreibcursor in deren Suchtextfeld.
5. **Statustext der Kürzel** — «Speichern...» ab der ersten Eingabe, danach «Gespeichert» (verschwindet nach rund 2,5 Sekunden) oder «Fehler beim Speichern» (bleibt stehen).
6. **Speicherung** — Jede Tasteneingabe, jedes Verlassen eines Feldes und jedes Löschen startet die Speicherung; ausgeführt wird sie 5 Sekunden nach der letzten Änderung. Gespeichert wird stets die vollständige Liste; Zeilen, bei denen Suchtext oder Kürzel fehlt, werden dabei verworfen.
7. **Wirkung** — Die Kürzel werden überall in der App auf Status-, Partei-, Fraktions- und Kommissionsnamen angewendet; längere Suchtexte werden zuerst ersetzt, damit kürzere Einträge längere nicht zerschneiden. Gespeichert und gefiltert wird immer mit dem Originalwert, gekürzt wird nur die Anzeige. Wirksam wird eine Änderung, sobald die App-Seite neu geladen wird.

#### Karte «E-Mail-Einladungen»

1. **Textfeld «Absender-E-Mail»** — Art: Textfeld für E-Mail-Adressen (der Browser prüft das Format). Platzhalter wörtlich «noreply@example.com». Pflichtfeld: nein. Vorbelegung: der gespeicherte Wert, Standard leer.
2. **Textfeld «Absendername»** — Art: Textfeld, ohne Platzhalter. Pflichtfeld: nein. Vorbelegung: der gespeicherte Wert; ist nichts gespeichert, der Standardwert «Parlament Winterthur Tool».
3. **Speicherung beider Felder** — automatisch: 0,5 Sekunden nach der letzten Tasteneingabe, sofort beim Verlassen des Feldes. Statusmeldungen erscheinen in der linken Spalte («Änderungen erkannt...», «Speichere automatisch...», «Alle Änderungen gespeichert», «Automatisches Speichern fehlgeschlagen»). Nie gesperrt.
4. **Verwendung** — Absenderadresse und Absendername werden für die Einladungsmails verwendet, die beim Anlegen neuer Benutzerkonten über «Ausgewählte abgleichen» verschickt werden.

#### Fusszeile des Verwaltungsbereichs

1. **Angabe «Parlament Winterthur Tool — gebaut am: ‹Zeitpunkt›»** — Art: Statustext, rechtsbündig unterhalb aller Karten. Quelle: der beim Erstellen der App festgehaltene Zeitpunkt. Bedingte Sichtbarkeit: nur wenn dieser Zeitpunkt vorliegt.
