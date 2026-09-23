# Project rules for parliament-winterthur-tool

## Language

The domain of this tool is the Winterthur city parliament, and that domain speaks German: its terms stay German as identifiers in code and data (`Traktandum`, `Vorstoss`, `Geschäft`, `Fraktion`), because their link to the parliament's law, forms, and everyday usage carries meaning; structure, verbs, and generic names are English (CLAUDE.md C9). README, CHANGELOG, FEATURES, TESTS, CONTRIBUTING and every other document are German for the Swiss users, and so are the comments in the code and the names of the tests; commit messages are English.

**Every word in those documents has a source** (CLAUDE.md A3): a name from the code, a label from the interface, a term of the parliament, or ordinary German. An English word stays only where the trade speaks it and no German word says the same: «Guard», «Fixture», «Viewport», «Endpoint», and the names of the Nextcloud apps «Deck», «Talk», «Forms». Everything else is said in German: «Benutzername» not «Username», «Unsinn» not «Gibberish», «Ruhezustand» not «Idle», «Standard» not «Default», «Kennzeichen» not «Flag». A word invented for this project («Vorlauf», «Doppellauf», «Auslieferungspfad») is wrong wherever it stands, in a document as much as in the name of a test.
