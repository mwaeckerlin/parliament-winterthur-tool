# TODO — Ideen und offene Vorhaben

Ideen, die noch nicht umgesetzt sind. Umgesetzte Punkte wandern nach `FEATURES.md` und verschwinden hier.

## Aufträge von Marc

- 2026-10-23 · offen · Session mwaeckerlin-04: `.github/workflows/docker.yml` auf die stündliche Prüfung von mwaeckerlin/scratch 1.1.12 umstellen (`cron: "17 * * * *"`, die beiden Kommentarzeilen über dem Zeitplan weg, Kopfkommentar und Zeile 10 auf die stündliche Prüfung umschreiben, `permissions` bleiben), dann unter Marcs `/commit` nur diese Datei committen und pushen, auf grüne Runner warten und sha und Ergebnis zurückmelden

## Offen aus dem Testlauf

- 2026-10-15 · offen · **Der Volltext eines Dokuments mit Tabellen zieht Zellen zusammen.** Gemessen am 2026-10-15: Die Vorstösse, die Antwort des Stadtrats, die Medienmitteilung und das Drehbuch liefern saubere Wörter; im Budgetbuch stehen Zellen ohne Trennzeichen beieinander, und dann entstehen Folgen wie «nbetreffend». Die Dokumentsuche zerlegt den Text in Wörter und findet solche Stellen nicht. Betroffen ist die Suche über den Inhalt (F121) bei Dokumenten mit Tabellen.

## Befunde aus dem Design-Test

Diese Befunde sind noch nicht beauftragt.

- 2026-10-24 · Geschäfteliste bei 1280 Pixel Breite: Der Titel bekommt die wenigste Breite von allen Spalten und bricht mitten im Wort («Festset-zung»), während «Prio», «Status», «Zuständig» und «Beschluss» zusammen das Dreifache einnehmen und meist nur «—» zeigen. Die Mindestbreiten von «Status» (19 Zeichen für «Aufsichtskommission») und «Zuständig» (9em) stammen vom 20.10.2026; der Status steht in der Zelle ohnehin zweizeilig.

- 2026-09-14 · Übersichtstabellen (Geschäfte UND Vorstösse, dieselbe Tabelle): Die eingebetteten Auswahllisten machen die Zeilen etwa doppelt so hoch wie eine Textzeile. Betrifft beide Ansichten gleichermassen, weil sie sich die Tabelle teilen. Behoben sind der abgeschnittene Name in der Spalte «Zuständig» (`wortumbruch.test.js`), die auf zweieinhalb Zeichen geschrumpfte Spalte «Status» und der ungenutzte Streifen, der aus der unbegrenzt wachsenden Titel-Spalte kam (`spaltenbreite.test.js`).

## Ideen ohne Auftrag

Nichts davon hat Marc verlangt.

- **Zusammenfassung je Geschäft** aus dem gelesenen Inhalt der amtlichen Dokumente, mit einem lokalen Sprachmodell. Stand bis zum 2026-10-15 unter seinen Aufträgen, ohne Beleg: Belegt ist sein Prompt vom 2026-09-23 «Und zuallererst: F121 umsetzen!», und F121 ist das Lesen der Dokumente, umgesetzt und getestet. Zu «Zusammenfassung», «zusammenfassen», «Sprachmodell» und «Inhaltsangabe» findet `find-spec.py` im Projekt keinen Prompt.

## Entwicklung über die Jahre

Wenn alte Budgets vorhanden sind (importiert wurden), darstellen, wie sich die verschiedenen Budgetposten und die Mitarbeiterzahlen über die Jahre entwickelt haben — auch in Relation zum Wachstum an Einwohnern:

- Steigen die Kosten und die Mitarbeiterzahl proportional oder überproportional?
- Steigen die Kosten in einigen Departementen, Produktegruppen oder Produkten stärker als in anderen?
- Welche Leistungen werden teurer, welche billiger?

## Verschwendung aufspüren

Woran erkennt man im Budget Geld, das ohne Gegenleistung fliesst? Kandidaten für Kennzahlen, die sich aus den bereits eingelesenen Daten berechnen lassen:

- **Kosten je Leistungseinheit im Zeitverlauf.** Die Produkte tragen Zielvorgaben mit Messgrössen (Fälle, Bewilligungen, Betreuungsstunden). Nettokosten geteilt durch die Messgrösse ergibt einen Preis je Leistung; steigt er über Jahre schneller als die Teuerung, ist das der erste Hinweis.
- **Kosten je Leistung im Vergleich zwischen ähnlichen Produkten** derselben Stadt und — sobald Daten anderer Städte vorliegen — zwischen Städten.
- **Zielvorgaben, die nie verfehlt werden.** Ein Soll, das seit Jahren übertroffen wird, ist zu tief angesetzt; die Leistung kostet mehr, als sie müsste.
- **Kostendeckungsgrad, der sinkt, während die Menge steigt** — die Gebühr deckt einen immer kleineren Teil, ohne dass die Leistung wächst.
- **Sprünge ohne Begründung im Buch:** eine Produktegruppe wächst zweistellig, während ihre Zielvorgaben und Produkte unverändert bleiben.
- **Stellen ohne Leistungswachstum:** Stellenzahl steigt, die Messgrössen der zugehörigen Produkte nicht.
- **Reserven und Rückstellungen**, die Jahr für Jahr gebildet und nie gebraucht werden.
- **Auffällig runde Zahlen** über mehrere Jahre unverändert: ein Budgetposten, der nie gerechnet, sondern fortgeschrieben wird.

Jede dieser Kennzahlen ist ein Hinweis, kein Urteil — sie zeigt, wo sich das Nachfragen lohnt, und liefert dem Antrag seine Begründung.
