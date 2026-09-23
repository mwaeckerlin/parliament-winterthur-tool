# NULL in der Datenbank bricht das Laden, wenn die Eigenschaft kein NULL erlaubt

Wird eine Spalte nachträglich mit einer Migration angelegt oder geändert und erlaubt
sie NULL (`ADD COLUMN x LONGTEXT NULL`, `MODIFY x TEXT NULL`), dann steht in den
bestehenden Zeilen **NULL**, und ebenso in jeder neuen Zeile, bei der das Feld nicht
gesetzt wurde. Ist die zugehörige Eigenschaft der Entity ohne NULL typisiert
(`protected string $x`), schreibt der `QBMapper` von Nextcloud dieses NULL über
`setX()`, und PHP wirft `TypeError: Cannot assign null to property … of type string`.
Der `TypeError` bricht `findEntities()` und `findAll()` ab, also kommt die **ganze
Liste** leer zurück; sichtbar wird das als «Keine … vorhanden», obwohl die Daten in der
Datenbank stehen. Betroffen waren `Vorstoss::$notizen` (V27) sowie
`$ansprechpartner` und `$zustaendigkeit` (V25) — die Vorstossliste blieb vollständig
leer.

**Regel für jede nachträglich hinzugefügte Spalte:**

- Entweder `NOT NULL DEFAULT <wert>` in der Migration,
- oder die Eigenschaft der Entity erlaubt NULL (`?string`), die lesenden Methoden
  kommen mit NULL zurecht (`json_decode($this->x ?? '[]', …)`,
  `alsListe(?string $roh)` mit `trim($roh ?? '')`), und eine **Migration der Daten**
  setzt die bestehenden NULL auf den leeren Wert.
- IMMER ein Guard-Test, der die Entity mit **NULL in der Spalte lädt** (`setX(null)`),
  nicht nur `new Entity()`. Eine frisch erzeugte Entity trägt den Standardwert ihrer
  Eigenschaft und trifft den Fall mit NULL NIE.

Zur selben Fehlerklasse gehört ein fehlendes `implements \JsonSerializable`: dann
liefert `DataResponse` nur `{id}`. Beide Fehler liegen auf dem ganzen Weg von der
Entity über die Datenbank und die Schnittstelle zurück in die Anzeige, und diesen Weg
sichert nur ein e2e-Test mit echten Daten (speichern, laden, anzeigen) — nie ein Mock
und nie eine frisch erzeugte Entity. Post-Mortem:
`~/.claude/post-mortems/2026-07-18-vorstoss-liste-leer-null-property-e2e-nie-gelaufen.md`.
