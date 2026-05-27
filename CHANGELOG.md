# Changelog

2026-05-27  Marc Wäckerlin

	Ungültige HTML-Struktur in der Geschäfts-Detailansicht behoben: Tabellenzeilen
	waren nicht korrekt in ein tbody-Element eingeschlossen, was zu Hydration-
	Warnungen im Browser führte.

	Fehler behoben, bei dem das Laden der Kommissionsliste abstürzte, wenn der
	Server eine leere Antwort zurückgab. Vorher entstand eine TypeError-Meldung
	in der Konsole, obwohl der Test trotzdem als erfolgreich galt.

	Teststabilität verbessert: Konsolenausgaben von console.error() führen jetzt
	sofort zu einem Testfehler statt stillschweigend ignoriert zu werden.

	PHPUnit-Notices in den Live-Scraper-Tests behoben (createStub statt
	createMock für Logger und HTTP-Client).

2026-05-27  Marc Wäckerlin

	WebSocket-Verbindung zum Echtzeit-Server funktioniert wieder. Der Nginx-Pfad
	war falsch konfiguriert (/parlwin/ws statt /ws/parlwin/) und der Container-
	Servicename stimmte nicht mit der nginx-Konvention überein.

	Beim Container-Start blieb Nextcloud im Wartungsmodus hängen, falls ein
	vorheriger Start abgebrochen wurde. Das wird jetzt automatisch behoben.

	Der freie Beschlusstext wird erst beim Verlassen des Feldes gespeichert,
	nicht sofort bei der Eingabe. Wenn der Text geleert wird, erscheint wieder
	die Auswahlliste.

2026-05-26  Marc Wäckerlin

	Synchronisations-Zuverlässigkeit verbessert: Wenn während der Synchronisation
	ein Fehler auftritt, werden keine bestehenden Einträge als gelöscht markiert —
	alle Daten bleiben vollständig erhalten.

	Traktanden werden beim Sync nicht mehr vollständig gelöscht und neu angelegt,
	sondern einzeln abgeglichen. Bestehende Notizen bleiben erhalten, auch wenn
	ein Traktandum zwischenzeitlich vom Parlament entfernt und wieder hinzugefügt
	wurde.

	Objekte (Sitzungen, Geschäfte, Mitglieder), die bei einem früheren Sync
	fälschlicherweise als gelöscht markiert wurden, werden bei der nächsten
	Synchronisation automatisch wiederhergestellt — inklusive aller bisherigen
	Notizen und fraktionsinternen Daten.

	Migrationen werden jetzt beim Container-Start zuverlässig ausgeführt. Schlägt
	eine Migration fehl, bricht der Container ab und zeigt eine Fehlermeldung im
	Docker-Log.

	Notizen und Beschlüsse können wieder mit der Maus verschoben werden — nur
	noch über das ⠿-Symbol, nicht mehr über die ganze Zeile. Text in der Zeile
	kann wieder normal markiert werden.

	Beschluss-Widget ist jetzt überall gleich: in der Übersicht und in der
	Detailansicht dasselbe Eingabefeld. Der Knopf «Aus Liste wählen» ist
	verschwunden — nur wenn der Freitext manuell geleert wird, erscheint die
	Listenauswahl wieder. Freier Text wird automatisch gespeichert.

	Alle Beschriftungen im Formular sind jetzt einheitlich: gleiche Schrift,
	gleiche Farbe — egal ob Label über Eingabefeld oder Abschnittstitel.

	Sitzungstypen: Teilnehmerliste direkt wählen statt Regelwerk definieren.
	Eigene Fraktion per Checkbox, einzelne Mitglieder per Mehrfachauswahl.

	Zweite (und weitere) interne Sitzungen konnten nicht gespeichert werden.
	Fehler behoben.

2026-05-24  Marc Wäckerlin

	Interne Fraktionssitzungen erscheinen jetzt neben den Parlamentssitzungen in
	der Sitzungsliste — mit Titel, Zweck, aufklappbaren Traktanden und Notizen für
	das Protokoll.

	Neues Erstellungsformular im Stil des Nextcloud-Kalenders: Sitzungstyp wählen,
	alle Felder werden aus der Vorlage vorausgefüllt (Titel, Ort, Von/Bis, Zweck,
	Traktanden) und können vor dem Speichern angepasst werden. Optional wird
	gleichzeitig ein Kalender-Eintrag erstellt.

	Interne Sitzungen sind in der Liste mit «intern» gekennzeichnet. Der Zwecktext
	ist direkt sichtbar ohne Aufklappen.

	Der Parlamentssync löscht interne Sitzungen nicht mehr.

	In «Fraktionsmitglieder ↔ Nextcloud-User» erscheinen jetzt auch Personen, die
	in der NC-Gruppe sind, aber keinen aktiven Parlamentseintrag haben — mit
	durchgestrichenem Namen. Nur wenn diese Person explizit ausgewählt wird, wird
	sie beim «Ausgewählte abgleichen» deaktiviert.

	Traktandum-Bemerkungen werden wieder korrekt gespeichert.

2026-05-24  Marc Wäckerlin

	Notizen und Beschlüsse speichern automatisch — kein «Speichern»-Knopf mehr.
	Notizen speichern bei Fokusverlust oder nach 5 Sekunden ohne Eingabe.
	Beschlüsse speichern sofort nach Auswahl aus der Liste.

	Beim Speichern flimmert nichts mehr und offene Popups bleiben offen —
	auch wenn mehrere Personen gleichzeitig arbeiten.

	In der Zeitleiste wird bei Zuständigkeitsänderungen «Von: X → Nach: Y»
	angezeigt.

	Die Traktanden-Tabelle wechselt auf schmalen Bildschirmen in ein Karten-Layout.

	Dokument-Links (PDF) werden direkt beim Traktandum angezeigt (↗).

2026-05-22  Marc Wäckerlin

	Fehler beim Erstellen einer Sitzung aus einer Vorlage behoben.
	Standard-Teilnehmerkreis ist nun die eigene Fraktion.

2026-05-21  Marc Wäckerlin

	Sitzungstyp-Formular vollständig funktionsfähig — Vorlagen können angelegt,
	bearbeitet und für neue Sitzungen verwendet werden.

	Notizen können pro Sitzung erfasst werden (Protokollführung).

	Kommissionen können per Suchfeld gefunden werden.

	Dokumente können direkt aus dem Tool erstellt werden.
