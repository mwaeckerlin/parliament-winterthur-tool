# DB-NULL in non-nullable Entity-Property bricht das Laden (QBMapper-Fehlklasse)

Wird eine Spalte nachträglich per Migration als NULLABLE hinzugefügt oder geändert
(`ADD COLUMN x LONGTEXT NULL`, `MODIFY x TEXT NULL`), haben bestehende Zeilen und
nicht-dirty Inserts **NULL** in dieser Spalte. Ist die zugehörige Entity-Property
non-nullable typisiert (`protected string $x`), setzt der Nextcloud-QBMapper den
DB-NULL über den Setter und PHP wirft `TypeError: Cannot assign null to property …
of type string`. Der TypeError bricht `findEntities()`/`findAll()` ab → die
**gesamte Liste** kommt leer zurück (Symptom: «Keine … vorhanden» trotz Daten in
der DB). Betraf `Vorstoss::$notizen` (V27), `$ansprechpartner` + `$zustaendigkeit`
(V25) → Vorstoss-Liste komplett leer.

**Regel bei jeder nachträglich hinzugefügten Spalte:**
- Entweder `NOT NULL DEFAULT <wert>` in der Migration,
- ODER Entity-Property nullable (`?string`) + Getter/Helfer NULL-tolerant
  (`json_decode($this->x ?? '[]', …)`, `alsListe(?string $roh)` mit `trim($roh ?? '')`),
  und eine **Daten-Migration**, die bestehende NULLs auf den Leerwert normalisiert.
- IMMER ein Guard-Test, der die Entity mit **NULL-Spaltenwert lädt** (`setX(null)`),
  nicht nur `new Entity()` mit Property-Default. Ein frisch konstruiertes Entity
  hat den Property-Default und trifft den DB-NULL-Fall NIE.

Verwandte Fehlklasse: fehlendes `implements \JsonSerializable` (DataResponse liefert
nur `{id}`). Beide gehören zum realen Entity↔DB↔API-Round-trip, den nur ein e2e-Test
(Speichern → Laden → Anzeige) mit echten Daten absichert — nie ein Mock, nie ein
frisch konstruiertes Entity. Post-Mortem:
`~/.claude/post-mortems/2026-07-18-vorstoss-liste-leer-null-property-e2e-nie-gelaufen.md`.
