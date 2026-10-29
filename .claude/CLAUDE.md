# Project rules for parliament-winterthur-tool

## Language

The domain of this tool is the Winterthur city parliament, and that domain speaks German: its terms stay German as identifiers in code and data (`Traktandum`, `Vorstoss`, `Geschäft`, `Fraktion`), because their link to the parliament's law, forms, and everyday usage carries meaning; structure, verbs, and generic names are English (CLAUDE.md C9). README, CHANGELOG, FEATURES, TESTS, CONTRIBUTING and every other document are German for the Swiss users, and so are the comments in the code and the names of the tests; commit messages are English.

**An English word stays in those documents only where the trade speaks it and no German word says the same:** «Guard», «Fixture», «Viewport», «Endpoint», and the names of the Nextcloud apps «Deck», «Talk», «Forms». Everything else is said in German: «Benutzername» not «Username», «Unsinn» not «Gibberish», «Ruhezustand» not «Idle», «Standard» not «Default», «Kennzeichen» not «Flag». «Vorlauf», «Doppellauf» and «Auslieferungspfad» were invented for this project and are written nowhere, in a document as little as in the name of a test. This is a deliberate exception to the `german` skill, which keeps the English terms of the trade: the readers of these documents are members of the city parliament, and they know «Standard», not «Default».

## PARLAMENTSBETRIEB.md — das Fachwissen des Rates

`PARLAMENTSBETRIEB.md` im Wurzelverzeichnis sammelt, was ein Mitglied des
Stadtparlaments für seine Arbeit wissen muss: die Instrumente und ihre Wirkung,
den Ablauf des Budgetjahres, die Regeln der Sitzung, die Wörter, die im Rat
anders gebraucht werden als im Alltag. Sie ist für die Parlamentarier
geschrieben, nicht für die Entwicklung, und sie steht im Projekt, weil das
Werkzeug dieses Wissen abbildet.

**Darin stehen die Regeln, sonst nichts.** Keine Vorbehalte, keine
Quellenangaben, keine Vermerke «zu prüfen», kein Abschnitt über das Dokument
selbst, keine Kapitel über offene Punkte. Marc, am 24.10.2026: «Kein Bullshit,
kein Formalismus! Alle Regeln, so wie sie dir bekannt sind, ohne Erklärung, ohne
Referenzen, nur die Regeln! […] Kein dummes Metageschwafel!» Wer das Dokument
aufschlägt, will wissen, was gilt — jede Zeile, die stattdessen über das Wissen
redet, nimmt ihm Zeit.

**Was bekannt ist, steht als Regel da.** Eine Regel wird als Aussage
geschrieben, nicht als Vermutung mit Rückversicherung. Was nicht bekannt ist,
steht gar nicht da; eine erfundene Zahl, Frist oder Zuständigkeit wird nie
geschrieben. Eine falsche Angabe korrigiert Marc.

**Sie wird laufend ergänzt**, bei jeder Arbeit, die neues Wissen über den
Parlamentsbetrieb hervorbringt: beim Einlesen eines Buchs, beim Bauen eines
Features, aus einer Rückmeldung der Fraktion, aus einer Aussage von Marc. Das
Neue kommt an die Stelle, wo sein Thema schon steht — nie als Anhang, nie als
Abschnitt mit einem Datum (globale Regel B4).

**Der Ton ist knapp.** Kurze Sätze, keine Wiederholung. Was in einer Tabelle
steht, steht nicht noch einmal im Fliesstext. Ein Begriff wird einmal erklärt,
dort wo er zuerst gebraucht wird.
