# Vorstoss-Lifecycle: Modellierung

Stand: 2026-05-12

## Rechtsgrundlagen (Kurzbezug)

- Kanton Zürich, Gemeindegesetz (GG):
  - § 34: mögliche Vorstösse im Parlament (Motion, Postulat, PI, Interpellation, Anfrage).
  - § 35: Wirkung überwiesener Motion/Postulat und unterstützter Interpellation.
- Stadt Winterthur, Organisationsverordnung Stadtparlament (OV Parl):
  - Art. 77-79: Einreichung/Form/Verfahren inkl. Rückzug.
  - Art. 82-86: Motion (Überweisung, Erheblicherklärung, Umsetzungsvorlage, Abschreibung).
  - Art. 89-90: Postulat (Bericht, Kenntnisnahme, Ergänzungsbericht/Nachbericht).
  - Art. 96: Interpellation.
  - Art. 98: Schriftliche Anfrage.

## Beobachtete Daten auf parlament.winterthur.ch

Die Detailseiten eines Geschäfts (`/politbusiness/{id}`) liefern als `<dt>/<dd>` u.a.:

- `Nummer`, `Geschäftsart`, `Status`, `Eingangsdatum`
- `Beschlussdatum Stadtparlament`, `Beschlussart Stadtparlament` oder `Beschluss Stadtparlament`
- `Abstimmungsresultat Stadtparlament`
- `Frist für Antrag / Beantwortung bis`
- `Antrag und Bericht vom`
- `Frist Umsetzungsvorlage bis`
- `Geschäft in Vorberatung bei`
- `Bemerkungen`

Wichtig: gleiche Label können mehrfach vorkommen (z. B. mehrere Beschlussphasen).

## Datenmodell

### 1) Offizielle Geschäfte

- Tabelle: `pw_geschaefte`
- Primäre technische Identität: `id = /_rte/information/{id}`
- Fachliche Nummer: `nummer` (z. B. `2025.15`)

### 2) Entwurfs-/Vorphase ohne Geschäftsnummer

- Tabelle: `pw_vorstoss_entwuerfe`
- Zweck:
  - Vorstösse ohne Nummer separat führen
  - später auf offizielles Geschäft matchen
- Match-Reihenfolge:
  - `extern_id` (falls vorhanden)
  - sonst `titel_normalisiert` + `typ`

### 3) Verfahrensereignisse

- Tabelle: `pw_geschaeft_ereignisse`
- 1:n zu `pw_geschaefte`
- speichert die zeitliche Reihenfolge (`reihenfolge`) jedes extrahierten Detailpunkts
- Typisierung z. B.:
  - `beschlussdatum`, `beschlussart`, `beschluss`, `abstimmungsresultat`, `frist`, `antrag_bericht`, `beantwortung`, `vorberatung`, `bemerkung`, `status`

## Warum so?

- Der Ablauf eines Vorstosses ist mehrstufig und im selben Geschäft sichtbar.
- Einzelne Statusfelder genügen nicht, um den rechtlichen Ablauf sauber abzubilden.
- Durch Ereignisse bleibt die Historie vollständig und auswertbar.
- Entwürfe ohne Nummer bleiben sichtbar und können später automatisch zugeordnet werden.
