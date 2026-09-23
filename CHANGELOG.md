# Changelog

- 2026-09-23 **1.8.19**

- 2026-09-23 **1.8.18**
    - Betrieb: **der Server gibt jeder Anfrage 1024 MB Speicher** — bisher waren es 512 MB, und das reichte für ein Budgetbuch nicht mehr. Der Wert lässt sich im Betrieb setzen (`PARLWIN_PHP_MEMORY_LIMIT`) und gilt für die Oberfläche, die Kommandozeile und den Hintergrundauftrag gleichermassen
    - Budget: **ein Budgetbuch lässt sich auch dann lesen, wenn es gewachsen ist** — das Budget 2027 brach beim Einlesen mit «Allowed memory size exhausted» ab, weil das neue Buch mehr Speicher braucht, als eine Nextcloud-Installation einer Anfrage gibt. Für die Dauer des Lesens gilt jetzt eine höhere Grenze, danach wieder die der Installation; wer sie anders will, setzt sie im Betrieb (`PARLWIN_BUDGET_MEMORY_LIMIT`)
    - Budget: **das Budget 2027 wird gelesen** — die Stadt hat es am 22. September 2026 aufgeschaltet, und die Anwendung liest Teil A und Teil B von selbst ein, sobald die Synchronisation die Weisung kennt. Zwei Stellen las sie zuerst falsch: Die Berufsbildung stand mit einem Globalkredit von null statt 8'869'746 Franken da, weil das Buch dort den Tabellenkopf und seine Werte auf eine Zeile setzt; und die Sozial- und Erwachsenenhilfe stand ohne jede parlamentarische Zielvorgabe da, weil sie ihre Kennzahlen auf das Budgetjahr hin ersetzt und die neuen erst ab 2027 Werte tragen
    - Budget: **Kennzahlen ohne Ist-Wert erscheinen jetzt** — eine parlamentarische Zielvorgabe, die im Buch keine Ist-Spalte hat, fehlte bisher in jedem Jahrgang. Allein im Budget 2026 waren es 15 der 17 Kennzahlen der Produktegruppe «Schutz und Intervention», dazu vier beim Tiefbau und je zwei bei Immobilien und Stadtgrün. Ebenso gelesen werden jetzt Sollwerte, die das Buch als Vergleich («>22000»), als Spanne («1 bis 2»), mit Prozentzeichen oder mit mehr als drei Fussnotenzeichen schreibt
    - Budget: **jede Kennzahl trägt ihren Namen** — wo eine Messgrösse über zwei Zeilen läuft und die zweite mit einer Zahl beginnt, stand die Kennzahl bisher ohne Bezeichnung da; wo ein Ziel mehrere Kennzahlen führt, trug sie statt des Namens die ganze Zielbeschreibung
    - Budget: **Produktnamen stehen vollständig da** — bricht die Überschrift im Buch mitten im Satz um, endete der Name in der Anwendung ebenso: «Ausführung von Vermessungsaufträgen sowie Unterhalt und»
    - Vorstösse: **die Übersicht ist aufgebaut wie die der Geschäfte** — eine Tabelle mit Art, Titel, Priorität, Status, Zuständigkeit und Beschluss, statt grosser Karten, auf denen der Titel unterging. Priorität, Zuständigkeit und Beschluss lassen sich direkt in der Zeile ändern; ein Klick in die Zeile öffnet den Vorstoss mit allen Details. Auf schmalen Fenstern erscheinen weiterhin Karten
    - Budget: **ein begonnener Antrag geht nicht mehr verloren** — bisher verwarf jedes Neuladen der Ansicht die Eingaben in offenen Antragsformularen, ohne jede Meldung. Das geschah nach jedem gespeicherten Antrag, bei einer Änderung durch jemand anderen und beim Wechsel in den Sitzungsmodus: Wer gerade einen Betrag tippte, sah das Feld plötzlich wieder leer
    - Geschäfte: **das PDF des Votums zeigt die Formatierung, die im Editor erfasst wurde** — Fettschrift, Absätze, Überschriften und Listen. Bisher stand die Markdown-Schreibweise wörtlich im Ausdruck, und Absätze gingen verloren
    - Kommissionen: **die pendenten Geschäfte stehen offen da** — sie sind das, wofür die Lasche geöffnet wird, und brauchen kein Aufklappen mehr. Dafür stehen die Mitglieder eingeklappt hinter einem Schalter, der ihre Zahl auch zugeklappt nennt; wer einen Namen sucht, dem klappt seine Kommission von selbst auf
    - Budget: **jede Produktegruppe zeigt oben, was unsere Anträge an ihr bewirken** — wirken sie, steht oben der Betrag des Stadtrats und darunter der Betrag der Fraktion, jeder mit seiner Differenz zum Vorjahr. Bisher stand dort allein der Betrag des Stadtrats, und die beantragten Einsparungen waren im Kopf der Karte nicht abgezogen. Dasselbe gilt im Personalbestand mit den Stellen
    - Budget: **die Differenz zum Vorjahr wird nicht mehr abgeschnitten** — reicht die Breite der Karte nicht, rückt der Betrag unter die Bezeichnung der Produktegruppe und steht auch dort an der rechten Kante der Karte, statt am Kartenrand zu enden
    - Budget: **ein Antrag lässt sich durch einen Klick in seine Zeile bearbeiten** — dasselbe Formular wie beim Anlegen, gefüllt mit dem, was am Antrag steht; die Bedienelemente der Zeile behalten ihre eigene Wirkung, und automatisch erzeugte Anträge der Pauschalverteilung bleiben unberührt. Ein geänderter Betrag wird auch wirklich gespeichert: bisher machte der unverändert stehengebliebene Prozentsatz die Änderung stillschweigend rückgängig
    - Sitzungen: **der Protokoll-Link am Traktandum öffnet nur noch das Protokoll** — er steht in der anklickbaren Titelzeile, und ein Klick auf ihn öffnete bisher zusätzlich das Geschäft
    - Budget: **Zielvorgaben-Änderungen und Einsparungsverteilung gehen beim Speichern nicht mehr verloren** — die Schnittstelle nahm beide Felder gar nicht entgegen, sodass beim Anlegen eines Antrags verschwand, was unter «Zielvorgaben ändern» und «Einsparung verteilen» erfasst war

- 2026-09-05 **1.8.17**
    - Sitzungen: **jedes Protokoll ist mit einem Klick erreichbar** — oben auf der Karte der Sitzung, die es protokolliert, und noch einmal in der Folgesitzung beim Traktandum, das es abnimmt, dort mit dem Datum der protokollierten Sitzung. Beide Links öffnen das Protokoll in einem neuen Fenster; solange das Parlament nur den Entwurf zur Abnahme stellt, führt der Link am Traktandum auf diesen

- 2026-09-01 **1.8.16**
    - Auslieferung: **das Beispiel-Compose fährt die Abbilder dieses Projekts** — für nginx stand dort das Basisabbild `mwaeckerlin/nextcloud:nginx`, während jeder Test gegen `parliament-winterthur-tool:nginx` lief; geprüft wurde damit ein anderes Abbild als ausgeliefert. Der vorgelagerte Proxy heisst jetzt `parlwin-traefik` ohne Registry-Namensraum, denn er entsteht nur lokal beim Bauen. Ein Test hält beides fest: Jedes Abbild im Namensraum des Projekts hat sein Dockerfile, und dieselbe Rolle trägt in Entwicklung und Auslieferung dasselbe Abbild
    - Kommissionen: **ein eigenes Geschäft, das einer Kommission zugewiesen ist, erscheint bei ihr** — die Lasche «Kommissionen» suchte die Geschäfte einer Kommission allein in deren Status («Bei Kommission … pendent»), wie ihn das Parlament schreibt. Ein selbst angelegtes Geschäft trägt den Status «Pendent» und nennt darin keine Kommission: Die Zuweisung im Feld «Kommission» blieb dort ohne Wirkung. Jetzt zählt beides, und der Hinweis auf der Karte sagt, dass weder ein Status noch eine Zuweisung auf sie zeigt
    - Geschäfte: **die automatische Zuständigkeit folgt derselben Regel** — wer in der zugewiesenen Kommission sitzt, wird nach der Synchronisation zuständig, auch wenn die Kommission nur im Feld «Kommission» steht. Bestehende Zuweisungen bleiben unberührt, und wer das Geschäft eingereicht hat, geht weiterhin vor
    - **Fragestunde: Fragen lassen sich jederzeit sammeln** — auch wenn keine Fragestunde angesetzt ist. Wem etwas auffällt, trägt es ein; die Fraktion teilt es später im Feld «Fragestunde» einer zu. Die Seite führt alle Fragen, die neueste zuoberst, jede mit ihrer Fragestunde oder dem Hinweis «noch keiner zugeteilt»; die angesetzten Fragestunden stehen darüber, mit Frist und der Zahl der ihnen zugeteilten Fragen
    - **Fragestunde** — ein neuer Bereich hinter dem Budget für die Fragestunde des Parlaments, die zweimal im Jahr stattfindet. Jedes Fraktionsmitglied trägt seine Fragen ein, die Fraktion bespricht sie in ihrer Sitzung, passt sie an und teilt sie einander zum Einreichen zu. Zu jeder Frage gehören: wer sie eingebracht hat, die Frage selbst, ein Kommentar und Notizen. Die Seite führt die Frist mit (der Donnerstag vor der Fragestunde) und die Grenze von 1'000 Zeichen, die der Parlamentsdienst setzt; darüber lässt sich nicht speichern. Weil jedes Mitglied nur eine Frage einreicht, meldet die Seite, wenn jemandem mehr als eine zugeteilt ist
    - Budget: **jedes Budgetbuch der Stadt lässt sich einlesen** — die Jahrgänge 2017 bis 2026. Die alten Bücher rechnen nach einem anderen Modell, trennen die Tausender mit Leerzeichen statt Apostroph (in einer Zelle auch beides), setzen Buchhaltungszeichen hinter die Beträge und die Jahreszahlen des Tabellenkopfs untereinander. Dadurch stand für 2020 ein Gesamtaufwand von 33 Millionen statt 1,4 Milliarden, für 2021 kein Steuerertrag, und der Investitionsanhang 2020 zeigte 33 der 108 Millionen
    - Budget: **jede Sitzungsunterlage mit den Anträgen lässt sich einlesen** — die Drehbücher 2022 bis 2026. Im Drehbuch 2022 steht der Betrag nicht im Antragssatz, sondern allein in der Antragsspalte, und die Kommission «BBK» gab es damals noch: Ohne sie fielen acht Anträge der falschen Produktegruppe zu. Ein Antrag zu einer parlamentarischen Zielvorgabe oder zu einem Verpflichtungskredit zählt nicht als Antrag auf den Globalkredit, ein Antrag auf einen anderen Steuerfuss wird als solcher geführt
    - Budget: **der Novemberbrief wird aus seiner eigenen Beilage gelesen** — bis zum Budget 2022 reicht der Stadtrat ihn als Beilage zum Budget-Geschäft nach, mit den Korrekturen von Aufwand, Ertrag und Nettokosten je Produktegruppe. Bisher wurde nur die Spalte «NB» des Drehbuchs gesucht, die es dort noch nicht gibt; und eingelesen wurde am Ende gar nichts, weil die Korrekturen als neue Absolutwerte gesucht wurden, die keine Quelle liefert
    - Verwaltung: **der Abbruch über die Kommandozeile wirkt** (`occ parlwin:sync:cancel`) — er schrieb das Signal nur in die App-Konfiguration, die der laufende Abgleich nie zu sehen bekommt, und lief deshalb ins Leere; jetzt geht er denselben Weg wie der Knopf in der Verwaltung
    - Verwaltung: **der Abbruch wirkt auch gleich nach dem Start** — zwischen dem Start des Synchronisationsprozesses und dem Greifen seiner Sperre vergehen ein bis zwei Sekunden. Wer in dieser Zeit abbrach, sah «abgebrochen», während die Synchronisation weiterlief und die Oberfläche danach minutenlang eine laufende Synchronisation zeigte. Das Abbruch-Signal trägt jetzt seinen Zeitpunkt: Der startende Lauf nimmt an, was nach seinem Start kam, und räumt nur weg, was von einem früheren Lauf übrig blieb. Ein abgebrochener Lauf lädt ausserdem gar nichts mehr von der Parlamentswebseite
    - Budget: **ein Jahr nimmt den Novemberbrief genau einmal an** — seine Zahlen sind Korrekturen am Entwurf, und ein zweiter Aufruf hätte sie ein zweites Mal aufaddiert. Wer das Jahr neu einliest, holt den Entwurf von der Webseite: Der Novemberbrief steht danach wieder zum Einlesen bereit

- 2026-08-31 **1.8.15**
    - Budget: **ein Budgetjahr lässt sich wieder einlesen** — der Import brach mit einem Serverfehler ab, weil beim Lesen zwei Budgetbücher gleichzeitig im Speicher lagen und die Grenze von PHP sprengten. Gehalten wird jetzt nur das Buch, das gerade gelesen wird; aus demselben Grund starb auch eine laufende Synchronisation still
    - Budget: **die Sitzungsanträge aus dem Drehbuch lassen sich wieder einlesen** — die Kürzungsgrenze zählte die Anträge aller Fraktionen zusammen und wies das Einlesen ab. In einer Sitzung stellen mehrere Fraktionen Anträge auf dieselbe Produktegruppe, von denen höchstens einer angenommen wird; die Grenze gilt für das, was zusammen wirkt: die eigenen Anträge und die unterstützten fremden
    - **Die Sprache ist überall Deutsch** — in der Verwaltung heisst die Karte jetzt «Fraktionsmitglieder ↔ Nextcloud-Benutzer», die Fortschrittszeile nennt die «Restzeit» statt «ETA», eine misslungene automatische Speicherung meldet «Automatisches Speichern fehlgeschlagen», ein misslungenes Hochladen «Hochladen fehlgeschlagen», und die Fusszeile sagt «gebaut am». Alle Dokumente des Projekts sind durchgesehen: erfundene Wörter und englische Begriffe, für die es das deutsche gibt, sind ersetzt

- 2026-08-31 **1.8.14**
    - Budget: in der **Grafik nennt jeder Kreis beim Überfahren sich selbst** — Name, Betrag und Anteil, auch der kleinste ohne Beschriftung. Zuvor zeigte das Popup mal den Kreis unter dem Zeiger, mal den grossen darüber
    - Budget: die **Summe jeder Seite der Grafik steht hervorgehoben im Kopf**

- 2026-08-31 **1.8.13**
    - Verwaltung: **eine Synchronisation, die hart beendet wurde, blockiert die Anzeige nicht mehr** — ihr Fortschritt blieb als Notiz stehen und behauptete weiter, sie laufe; der Abbruch räumte nur das Signal auf und liess ihn stehen, und die Oberfläche zeigte für immer eine laufende Synchronisation. Ob eine läuft, sagen jetzt die Sperre und der Arbeitsprozess
    - Budget: die **Kreise der Grafik kommen von weit her zusammen und sind dabei mehrere Sekunden unterwegs** — sie starten auf einem Ring weit ausserhalb, wandern aus allen Richtungen herein, schieben einander beiseite und schwingen sich ein. Der Zug zum Platz zieht dabei langsam fester an und die Kollision lässt nach, damit jeder Kreis wirklich ankommt. Zuvor sass jeder nach einem halben Dutzend Bildern auf seinem Platz, und vom Zusammenfinden war nichts zu sehen

- 2026-08-30 **1.8.12**
    - Budget: **gekürzt wird höchstens auf null** — was nicht ausgegeben wird, kann nicht gespart werden. Die Grenze gilt für die Summe aller Anträge auf dieselbe Position und in jedem Feld: Globalbudget, Personalbestand, Investitionen. Ein Antrag, der darüber hinausgeht, wird abgewiesen und die Meldung nennt das Budget, das bereits Beantragte und was noch möglich ist; die automatische Pauschalverteilung legt um, was eine Position nicht mehr tragen kann
    - Budget: die **Gesamtkosten eines Investitionsprojekts sind die Summe seiner Jahre** — bereits getätigt, Budgetjahr und die drei Planjahre. Der bewilligte Kredit steht daneben als eigener Wert; er sagt, was das Parlament freigegeben hat
    - Budget: der **Betrag des Budgetjahres ist der grösste und stärkste Wert jeder Karte** — über ihn wird entschieden
    - Budget: in der **Grafik stehen die vollen Namen** — an den Leerzeichen umgebrochen, nie gekürzt. Vier Produkte «Bewirtschaftung …» standen als viermal «Bewirtscha…» nebeneinander und waren nicht zu unterscheiden
    - Budget: die **Kreise der Grafik finden ihren Platz** — sie werden an die Stelle gezogen, die ihnen zusteht, schieben einander dabei beiseite und ordnen sich, bis alles passt. Jede Seite rechnet für sich: Einnahmen und Ausgaben teilen sich dasselbe Koordinatensystem, und gemeinsam gerechnet stiessen ihre Kreise einander quer über die Zeichenflächen weg. Beim Öffnen einer Ebene wandern sie von dort, wo sie stehen, an die neuen Plätze. Die Bewegung kommt aus der Kraftsimulation von d3 statt aus einer Schleife, die jeden Kreis für sich hat kreisen lassen
    - Budget: der Filter **«Nur mit Betrag im Budgetjahr»** steht in der Seitenleiste bei den anderen Filtern, sobald die Investitionsrechnung offen ist, und nennt, wie viele Projekte von wie vielen übrig bleiben
    - Budget: **auch mehrdeutige Tabellenzellen der alten Bücher werden aufgelöst** — der Anhang «Kontrolle der Investitionskredite» führt denselben Betrag in einer Tabelle mit nur zwei Wertspalten und entscheidet damit, wie eine Zelle mit fünf Spalten zu lesen ist. Im Buch 2024 fehlten dadurch 1,3 Millionen Franken
    - Vorstösse: **der Inhalt geht nicht mehr verloren, wenn man gleich nach dem Tippen schliesst** — der Editor speichert beim Verlassen, und die Maske schloss über die laufende Anfrage hinweg; beim nächsten Öffnen stand der Stand von davor da
    - Verwaltung: **der Abbruch einer laufenden Synchronisation wirkt** — die Synchronisation ist ein einziger Aufruf, der Abbruch kommt aus einem zweiten, und Nextcloud hält Konfigurationswerte pro Aufruf im Speicher: Der Lauf sah bis zu seinem Ende den Stand von seinem Beginn und lief weiter. Das Signal geht jetzt einen Weg, der keinen solchen Zwischenspeicher kennt
    - Budget: in der **Grafik finden die Kreise ihre Plätze, wenn man den Tab öffnet** — die Fläche steht als Tab unter mehreren von Anfang an im Dokument, und die Bewegung war längst gelaufen, bevor jemand hinsah
    - Budget: **jedes Departement der Investitionsrechnung trifft die Summe, die im Buch steht** — in allen fünf eingelesenen Jahrgängen. Bisher glich nur die Summe der ganzen Stadt, und die verdeckte, was sich gegenseitig aufhob: Im Buch 2024 zählte eine Planungszeile, die ihren Rahmenkredit beim Namen nennt statt bei der Nummer, als eigenes Projekt und legte 430'000 Franken auf das Departement Bau und Mobilität — während anderswo derselbe Betrag fehlte
    - Budget: **Departemente heissen wieder nach ihrem Namen** — wo das Buch die Zeile nicht an den Spalten trennt, stand auf jeder Karte «Behörden und Stadtkanzlei 2 670 000 1 287 000 900 000 900 000 748 000»; und wo es die Nummer zerreisst, hiess die Einheit «65 Stadtkanzlei»
    - Budget: **ein Betrag steht in der Spalte, in der er im Buch steht** — nennt die Kreditkontrolle den Betrag des Budgetjahres und findet er sich in der Investitionsplanung eine Spalte daneben, richten sich die Spalten danach aus
    - Budget: die **bewilligten Kredite werden richtig gelesen** — bei einem Projekt, dessen Name selbst Zahlen trägt («Masterplan Bahnhof: Rahmenkredit (11334) AP1 + AP2»), galten diese als Betrag und Kredit; eine im Buch zerrissene Zahl wurde als ihr Bruchstück gelesen. Beträge und Kredite stammen jetzt aus den beiden Wertspalten, und beide Tabellen des Anhangs nennen für über 1300 Projekte denselben Betrag
    - Budget: **ein abgewiesener Antrag sagt jetzt, warum** — bisher blieb das Formular offen und nichts geschah; der Grund stand nur in der Browser-Konsole. Das gilt für jede Aktion im Budget: Antrag stellen, ändern, löschen, entscheiden, Haltung umschalten, Pauschalantrag, Steuerfuss, Import und Neu-Einlesen
    - Budget: das **Einlesen eines Budgetbuchs ist deutlich schneller** — jedes Buch wurde bisher dreimal von Grund auf gelesen, obwohl es sich dabei nicht ändert

- 2026-08-29 **1.8.8**
    - Budget: neuer letzter Tab **«Grafik»** — das ganze Budget als geschachtelte Kreise, deren Fläche dem Betrag entspricht: Einnahmen und Ausgaben nebeneinander über die volle Breite (auf schmalen Seiten untereinander), darin die Departemente, darin die Produktegruppen, darin die Produkte. Ein Klick auf ein Departement zoomt **beide Seiten** dorthin, so stehen Aufwand und Ertrag derselben Einheit nebeneinander — im selben Massstab, die kleinere Summe bleibt der kleinere Kreis. Der Weg im Kopf und die Taste Escape führen zurück, der Wechsel läuft als ruhige Bewegung. Jeder Kreis der aktuellen Ebene ist beschriftet und nennt beim Überfahren Betrag, Anteil am übergeordneten Kreis und — wo die Produkte das Budget ihrer Gruppe nicht erklären — wie viel Prozent sie ausweisen
    - Budget: die Auswahl **«Vergangenes Budgetjahr importieren»** zeigt wieder alle Jahre, für die eine Budgetweisung vorliegt. Bisher fehlten die Jahre ab 2020, weil die Stadt ihre Weisungen seit dem Budget 2020 als «Genehmigung des Budgets …» betitelt und die App nur die frühere Schreibweise «Budget …» las
    - Budget: in der **Grafik öffnet ein Klick jetzt zuverlässig eine Ebene** — bisher fing der kleine Kreis eines Produkts den Klick ab, der dem Departement galt, und es geschah gar nichts; ein Departement liess sich nur dort öffnen, wo zufällig kein Produkt lag
    - Kommissionen: die Ansicht zeigt wieder **alle pendenten Geschäfte** — sie lud höchstens tausend Geschäfte und filterte daraus; bei einem grösseren Bestand fehlten pendente Geschäfte, ohne dass es sichtbar wurde
    - Budget: die **Kopfzahlen eingelesener Jahrgänge stimmen wieder** — Gesamtergebnis, Steuerertrag sowie die Totale von Aufwand und Ertrag samt Vorjahr. Bis zum Budget 2024 stellt die Stadt die Spalten ihres Erfolgsausweises anders (das Budgetjahr steht hinten statt vorne); die App las deshalb für 2022 bis 2024 die Zahlen der Rechnung von vor zwei Jahren — plausible Werte aus dem falschen Jahr. Für 2022 und 2023 fehlte zudem das Gesamtergebnis ganz, für 2022 auch der Steuerertrag, weil beide an einer Formulierung hingen, die diese Bücher nicht verwenden
    - Budget: die **Investitionsrechnung vergangener Jahre wird eingelesen** — bisher blieb sie für jedes Jahr vor 2025 leer, weil der Anhang dort anders heisst, Tausender mit einem Leerzeichen trennt und kürzere Projektnummern führt. Neu erkannt werden auch «Behörden und Stadtkanzlei» als eigene Einheit neben den Departementen sowie Zeilen, die den Betrag darüber nur aufschlüsseln; damit stimmt die Summe der Projekte mit dem Total der Stadt überein. In den Büchern bis 2024 fehlen einzelne Projekte, deren Beträge in der Tabelle nicht eindeutig einer Spalte zuzuordnen sind — dort zeigt die App lieber weniger als eine erfundene Zahl
    - Tests: der Regressionslauf umfasst jetzt auch die Budget-Parser-Tests (Drehbuch und Budgetbücher). Sie liefen bisher nur auf Abruf, wodurch der ganze Weg vom Budget-Geschäft über das Drehbuch bis ins Buch in keinem vollständigen Testlauf geprüft wurde
    - Tests: die Browser-Tests prüfen wieder das, was die Oberfläche seit der Vereinheitlichung von Löschen und Navigation tatsächlich zeigt — der Löschknopf einer Notiz, die zehn Einträge der Navigation und der Kasten «Pauschalanträge» wurden unter ihren alten Namen gesucht und liefen deshalb ins Leere
    - Budget: die Ausnahmen eines Pauschalantrags gleichen sich wieder in **beide Richtungen** ab — wird eine Produktegruppe unten an ihrer Karte wieder aufgenommen oder ausgenommen, zeigt die Mehrfachauswahl oben im Pauschalantrag denselben Stand. Bisher übernahm der Pauschalantrag nur, was oben eingestellt wurde, und schrieb den alten Stand bei der nächsten Änderung zurück. Der Abgleich hielt danach nur bis zur ersten Eingabe im Pauschalantrag; jetzt kommt eine unten gesetzte Ausnahme auch nach dem Speichern wieder oben an
    - Budget: als **Antragsteller eines eigenen Antrags** stehen nur noch die eigene Fraktion und ihre Mitglieder zur Wahl — bisher waren dort alle Ratsmitglieder aufgeführt. Bei einem fremden Antrag bleiben wie bisher alle Fraktionen und alle Mitglieder wählbar
    - Budget: die **Werte eingelesener Bücher stimmen auch dort, wo der Buchtext sie verdeckt** — im Buch 2021 überschrieb der Satz «Die Nettokosten/Globalkredit steigen … um rund 1,31 Mio. Franken» die echten Beträge des Tiefbaus, und im Buch 2024 lief das Kapitel «Bibliotheken» weiter in die «Subventionsverträge», weil deren Überschrift im Satz umbricht: die Bibliotheken zeigten die Beträge der Subventionsverträge, die Subventionsverträge gar keine
    - Budget: bei **Steuern und Finanzausgleich** stimmt der Ertrag wieder — dort ist der Ertrag ein Vielfaches der Kosten, der Anteil in der Prozentspalte deshalb vierstellig, und die App las ihn als Betrag: angezeigt wurde der Ertrag des Vorjahres statt des Budgetjahres. Betrifft jeden eingelesenen Jahrgang
    - Budget: die **Stelleneinheiten** stammen wieder aus dem Stellenplan — bei den Informatikdiensten stand die Leistungsmenge «Geschätzter Zeitaufwand umgerechnet in Stelleneinheiten» im selben Kapitel und überschrieb die echten 85 Stellen mit 7; bei anderen Produktegruppen setzte ein Satz mit demselben Wort die Stellen auf null
    - Budget: jedes Investitionsprojekt nennt wieder seine **Produktegruppe** — auf den Karten stand «Projekt», weil das Buch «( PG )» mit Leerzeichen in den Klammern schreibt
    - Budget: ein Betrag, der im Buch als letzter einer Zeile steht, **fällt nicht mehr aus der Tabelle** — bei einem Projekt der Investitionsplanung 2026 verschwanden so 1,5 Millionen aus dem letzten Planjahr
    - Budget: bei jedem Investitionsprojekt stehen **Gesamtkosten und bereits getätigte Investitionen** wieder da — beide Felder zeigten für jedes Projekt eine Null. Das bereits Investierte steht in der Jahresspalte vor dem Budgetjahr, die Gesamtkosten im Anhang «Kontrolle der Investitionskredite», den die App bisher nicht las
    - Budget: ein Klick auf **«Details»** eines Investitionsprojekts öffnet die Angaben, die auf der Karte keinen Platz haben: die Jahresreihe vom bereits Investierten über das Budgetjahr bis in die drei Planjahre, den Planungsanteil und die **bewilligten Kredite je Konto samt Bewilligungsdatum**
    - Budget: die **Kostentabelle eines Produkts zeigt nur ihre eigenen Zeilen** — bei der Stadtentwicklung stand der Hinweis «Die Produkte wurden Anfang 2023 neu definiert …» als fünfte Zeile mit dem Wert 2023 in jeder Tabelle
    - Budget: die **Investitionsrechnung der Jahre bis 2024 ist vollständig**. Diese Bücher trennen die Tausender mit einem Leerzeichen und setzen mehrere Beträge in eine Tabellenzelle («100 000 751 000»); wo die Aufteilung nicht eindeutig war, liess die App die Zeile weg — 2024 fehlte damit die Hälfte der Projekte und zwei Drittel der Summe. Neu wird jede Zelle aufgelöst, sofern genau eine Lesart den Regeln der Tabelle entspricht; 2022, 2023, 2024 und 2026 stimmen jetzt mit dem Total der Stadt überein
    - Budget: ein **misslungener Import ersetzt den vorhandenen Stand nicht mehr**. Bisher schützte nur ein ganz leeres Ergebnis; liefert das Buch dagegen ein paar wenige Produktegruppen (geändertes Dokument, falscher Link), wurde der vollständige Stand dadurch ersetzt. Jetzt bricht der Import mit einer Meldung ab, die Quelle und Anzahl nennt, und lässt das eingelesene Budget unangetastet
    - Budget: bei einer **neuen Produktegruppe ohne Vorjahreszahlen** stehen die Werte wieder in der richtigen Spalte — wo die Ist-Spalte im Buch leer bleibt (Öffentliche Beleuchtung, Stadtgrün, Grosser Gemeinderat, Schulpflege), zeigte die App den Wert des Vorjahres als Ist-Wert und den ersten Planwert als Budgetjahr
    - Budget: die **Investitionsrechnung aller Jahrgänge ist vollständig** — die Summe der Projekte trifft in 2022, 2023, 2025 und 2026 das Total der Stadt auf den Franken, in 2024 bis auf eine Zeile, deren Beträge im Buch mehrdeutig stehen. Behoben wurden: Tranchen, die zusätzlich zu ihrem Rahmenkredit zählten (der Rahmenkredit trägt genau ihre Summe); die Aufschlüsselung «IR-Plan: Rahmenkredit …», die als eigenes Projekt zählte; eine Zeile, die das Buch einmal gekürzt und einmal vollständig setzt und die dadurch doppelt zählte; eine zerrissene Projektnummer («134 11» statt 13411), die als Bereichs-Subtotal galt; und zwei Fälle, in denen Beträge mit Leerzeichen als Tausendertrenner falsch zerlegt wurden — «550 000 1 390 000 …» ergab 1'390 statt 1'390'000, und ein Betrag mit führender Null machte die ganze Zeile mehrdeutig
    - Budget: die **Investitionsrechnung 2025 ist vollständig** — die Summe jedes Departements stimmt jetzt auf den Franken mit dem Buch überein, zuletzt fehlten 5,5 Prozent. Drei Ursachen: das Buch zerreisst Beträge mitten in der Zahl («960'00» und «0» ergaben 960 statt 960'000, betraf 24 Zeilen); eine Zelle mit zwei Beträgen schob die ganze Zeile um eine Spalte nach rechts, wodurch bei zwei Projekten 3,5 Millionen ins Planjahr statt ins Budgetjahr fielen; und die einzelnen Vorhaben unter einem Sammelposten oder Rahmenkredit wurden zusätzlich zu diesem gezählt. Welche Zeilen zu einem Sammelposten gehören, entscheidet neu die Summe, die das Buch für jede Produktegruppe nennt
    - Tests: die Bücher **2019, 2020 und 2021** liegen als Testfixture bei und werden bei jedem Lauf gegen die im Buch nachgeschlagenen Zahlen geprüft (Globalkredit über alle sechs Spalten, Kosten, Erlöse, Stelleneinheiten); damit ist der Import jedes Budgets seit 2019 abgedeckt

- 2026-08-27 **1.8.7**
    - Budget: jede Produktegruppe zeigt jetzt ihre **Parlamentarischen Zielvorgaben** (WoV) — die nummerierten Ziele mit ihren Messgrössen und den Jahreswerten (Ist, Soll, Planjahre). Der vom Parlament entscheidbare Wert («Soll» des Budgetjahres) ist hervorgehoben
    - Budget: **Antrag auf eine Zielvorgabe** — zu jeder Zahl lässt sich ein neuer Soll-Wert beantragen (z.B. 100 statt 90), einzeln oder mehrere in einem Antrag. Ein Zielvorgaben-Antrag hat keine automatische Budgetwirkung; er kann mit einer Budgetanpassung kombiniert werden oder allein stehen
    - Budget: **Einsparungsverteilung im Antrag** — weil das Parlament nur über die Produktegruppe bestimmt, lässt sich angeben, wo innerhalb der Produktegruppe der beantragte Betrag einzusparen ist (Kostenzeilen, Produkte), je mit Betrag oder Prozent. Ist oben kein Betrag gesetzt, ergibt sich der Betrag der Produktegruppe aus der Summe unten; ist er oben gesetzt, dient die Verteilung der Begründung. Die Verteilung erscheint im Antrags-PDF in der Begründung
    - Budget: eine Produktegruppe öffnet auf Klick ein **Vollbild-Detail** (wie bei den Geschäften) — oben die Zielvorgaben, darunter die Produkte als Karten mit ihrer Kostentabelle (Kosten, Erlös, Nettokosten, Kostendeckungsgrad über Ist, Soll Vorjahr und Soll aktuell) und ihren Leistungen, dann die Erläuterungen; die Karte bleibt dadurch geschlossen übersichtlich
    - Budget: die **Erläuterungen behalten ihre Formatierung** (Absätze und Aufzählungen) statt zu einem Textblock zu verschmelzen
    - Budget: neues Tab **«N Anträge»** hinter dem Steuerfuss zeigt alle Anträge zusammengefasst (nach Departement), damit man sie auf einen Blick sieht, ohne das PDF zu erzeugen; der Knopf «Anträge als PDF» steht jetzt in diesem Tab. Die Spalten (Position, Betrag, Stellen/Prozent, Antragsteller, Begründung, Beschluss) fluchten zeilen- und departementsübergreifend: Zahlen rechtsbündig, die Einheit auf einer gemeinsamen Kante
    - Budget: die Antrags-Bedienung ist vereinfacht — ein einziger Schalter je Antrag («Antrag stellen» bei eigenen, «Unterstützen» bei fremden) statt der Auswahlliste; gelöscht wird mit «✕»; der Beschluss (angenommen/abgelehnt) erscheint nur im Sitzungsmodus
    - Budget: im **Anträge-PDF** steht als Antragsteller immer die Fraktion (im Rat stellt die Fraktion die Anträge); die Person erscheint nur in der Übersicht. Eine Option nimmt die von uns unterstützten fremden Anträge zusätzlich ins PDF. Der Titel nennt die eigene Fraktion namentlich («Budgetanträge der …») statt allgemein «der Fraktion»
    - Budget: auf schmalen Bildschirmen (Handy) scrollt die Seite nicht mehr waagrecht — die Tab-Leiste bricht um und die Anträge-Übersicht stapelt sich statt in die Breite zu laufen
    - Budget: der Filter **«Anstieg ab CHF/%»** richtet sich nach dem angezeigten Anstieg des Globalkredits — negative und zu kleine Anstiege werden nicht mehr gezeigt
    - Budget: Übersicht und Tabs bleiben beim Scrollen zusammen am oberen Rand (ohne sich zu überlagern); der Link zur Weisung steht im Kopf ganz rechts
    - Budget: der **Antragsteller** eines neuen Antrags ist standardmässig die **eigene Fraktion** (im Rat stellt die Fraktion die Anträge); eine Person lässt sich weiterhin wählen. Gilt für Einzel- und Pauschalanträge
    - Budget: ein **Pauschalantrag speichert automatisch** — der «Übernehmen»-Knopf entfällt, Änderungen werden kurz nach der Eingabe übernommen
    - Budget: eine Produktegruppe lässt sich wieder mit einem **Schalter direkt an der Produktegruppe** vom Pauschalantrag ausnehmen (zusätzlich zur Mehrfachauswahl im Pauschalantrag selbst)
    - **Löschen sieht überall gleich aus:** ein einheitliches «✕» an derselben Stelle (Antrag, Pauschalantrag, Notiz, Vorstoss, Sitzungstyp) — kein Papierkorb-Symbol, kein «Löschen»-Text mehr
    - Alle Listen (Geschäfte, Vorstösse, Sitzungen, Mitglieder, Kommissionen, Budget) haben im Filter einen einheitlichen Knopf **«Filter zurücksetzen»**

- 2026-08-26 **1.8.5**
    - Protokoll: neue Ansicht «Protokoll» — ein Verlauf der Synchronisationen und der eingelesenen Budgets (wann abgeglichen wurde, was neu und was geändert kam) und der Ort, an dem Probleme beim Einlesen festgehalten werden (z.B. ein Budgetbuch, aus dem keine Produktegruppen gelesen werden konnten). Fehler-Ereignisse sind hervorgehoben
    - Budget: im Sitzungsmodus lassen sich über «Sitzungsanträge einlesen» die tatsächlichen Anträge der Budgetsitzung einlesen — sie werden direkt aus dem Drehbuch zur Budgetbehandlung geladen (der Beilage der Budgetsitzung). Zu jedem Antrag kommen Quelle (Kommission oder Fraktion), Richtung und Betrag, Begründung und das Abstimmungsergebnis der Kommission dazu; die Anträge sind fremde Anträge und stehen zunächst offen. Erneutes Einlesen dupliziert nichts und lässt eigene Haltungen und Entscheide unberührt
    - Budget: der Novemberbrief wird jetzt ebenfalls aus dem Drehbuch der Budgetsitzung gelesen (die Spalte «NB»); führt das Drehbuch eines Jahres keine solchen Korrekturen, gibt es für dieses Jahr keinen Novemberbrief einzulesen
    - Budget: das Budgetbuch wird jetzt auch aus PDFs mit Macintosh-Schriften korrekt gelesen. Bisher las der Server aus solchen PDFs leeren Text, sodass beim Einlesen gar keine Produktegruppen erkannt wurden; das ist behoben
    - Budget: «Budget neu einlesen» zerstört das Budget nicht mehr, wenn das geladene Budgetbuch keine Produktegruppen liefert (falscher Link, Fehlerseite, unerwartete Struktur): der Vorgang bricht ab und lässt den bestehenden Stand unverändert, statt alle Produktegruppen zu löschen und das ganze Ergebnis in die Position «Interne Verrechnung / Abgrenzung» zu schieben. Die Fehlermeldung nennt die geladene Adresse. Ebenso wird eine Antwort, die kein PDF ist, sofort mit klarer Meldung abgewiesen
    - Protokoll: der Verlauf ist eine einspaltige Liste (neueste oben) statt einer mehrspaltigen Kachelwand — so liest sich die Zeitfolge von oben nach unten; jeder Eintrag zeigt Erfolg oder Fehler als farbigen Randstreifen und Zeichen
    - Budget: der Steuerfuss-Tab ist überarbeitet. Der Schalter «Steuerfuss bei Überschuss automatisch senken» wird pro Budgetjahr gespeichert und überlebt das Neuladen. Schaltet man ihn aus, fällt der Steuerfuss auf den Stadtratsantrag zurück; es erscheint ein Eingabefeld (vorbelegt mit dem Stadtratsantrag) und der Schalter «Antrag stellen». «Antrag stellen» ist jetzt ein Schalter statt eines Knopfes — einschalten stellt den Antrag, ausschalten löscht ihn wieder. Mehrfaches Klicken erzeugt keine doppelten Anträge mehr, und ein gestellter Antrag lässt sich wieder entfernen
    - Budget: der automatisch erzeugte Steuerfuss-Antrag trägt jetzt die eigene Fraktion als Antragsteller (er stand bisher im Antrags-PDF ohne Antragsteller)
    - Budget: die Tabs (Globalbudgets, Personalbestand, Investitionsrechnung, Steuerfuss) bleiben beim Scrollen oben stehen, sodass man zum Wechseln nicht hochscrollen muss; die Scrollposition wird je Tab gemerkt und beim Zurückwechseln wiederhergestellt
    - Budget: ein Departement wird nicht mehr als «……… 175» angezeigt — eine umgebrochene Zeile aus Füllpunkten und Seitenzahl im Inhaltsverzeichnis des Budgetbuchs wird nicht mehr als Departementsname gelesen; die betroffene Produktegruppe erscheint wieder unter ihrem richtigen Departement (wirkt beim nächsten Neu-Einlesen des Budgets)

- 2026-08-26 **1.8.4**
    - Budget: die Budgetbücher werden jetzt zur Laufzeit von der Parlamentswebseite geladen (die Beilagen Teil A und Teil B des Budget-Geschäfts) und eingelesen — neue Bücher landen in der Datenbank, ohne dass ein neues Abbild gebaut werden muss; im Abbild liegt kein Budgetbuch
    - Budget: «Budget neu einlesen» direkt im Tool — liest ein Budgetjahr vollständig neu aus dem Budgetbuch ein. Weil dabei alle bestehenden Anträge, Notizen, Pauschalanträge und Entscheide zu diesem Budget unwiederbringlich gelöscht werden, geschieht das nur nach doppelter Bestätigung (Dialog «Bist du ganz sicher?» und ein Kontrollkästchen, mit dem man das bestätigt). Nach dem Bestätigen schliesst der Dialog sofort, ein Fortschrittsbalken zeigt das laufende Einlesen, am Ende erscheint eine Erfolgs- oder (bei einem Fehler) eine Fehlermeldung
    - Budget: der aktive Tab sieht jetzt beim Öffnen der Seite gleich aus wie nach einem Mausklick (kein hängenbleibender Fokus-Hintergrund)
    - Budget: neuer Wartungsbefehl `occ parlwin:budget-reimport <Jahr>`, um ein Budgetjahr sauber neu aus dem Budgetbuch einzulesen (ersetzt Produktegruppen, Investitionen und Kennzahlen); mit `--purge` werden zusätzlich die Anträge und Pauschalanträge des Jahres gelöscht
    - Budget: die Übersicht oben vergleicht jetzt das Stadtratsbudget (wie vorgelegt) mit dem Fraktionsbudget (mit unseren Anträgen) und zeigt die Differenz je Kennzahl (Ausgaben, Einnahmen, Ergebnis, Steuerfuss, Stellen)
    - Budget: das Anträge-PDF ist nach Departement gruppiert — innerhalb eines Departements zuerst das Budget (Globalbudget, Personal), dann die Investitionen; der Steuerfuss steht in einem eigenen Abschnitt am Ende. Ohne Anträge erscheint «Keine Anträge»
    - Budget: der Steuerfuss-Tab zeigt jetzt den beantragten Steuerfuss und die Differenz zum Vorjahr
    - Budget: die Pauschalanträge sind jetzt einheitlich — jeder trägt einen Ziel-Typ (Einsparungen, schwarze Null, fester Ertrag, festes Defizit). Die Liste startet leer und wird über «+ Pauschalantrag» befüllt; der frühere Schalter «Defizit automatisch verteilen» und der Knopf «Defizit verteilen» entfallen. Von den absoluten Zielen ist immer nur eines aktiv — wählt man ein zweites, wird das ältere zur Einsparung 0
    - Budget: die Gesamtsumme geht jetzt exakt auf das vom Stadtrat deklarierte Ergebnis auf. Posten, die in keiner Produktegruppe stehen (interne Verrechnung und Abgrenzung), erscheinen als eigene, abgesetzte Position «Interne Verrechnung / Abgrenzung» beim Departement Finanzen; auf sie sind keine Anträge und keine Pauschalkürzung möglich
    - Budget: die Budgetdaten stammen ausschliesslich aus dem echten Budgetbuch (dem Weisungs-PDF des Budget-Geschäfts) — es gibt keine statische Zwischendatei mehr, und das Einlesen selbst wird an mehreren echten Jahrgängen geprüft
    - Budget: die grünen und roten Differenz-Zahlen sind dunkler und dadurch besser lesbar
    - Budget: ein neu geöffnetes Antragsformular lässt sich mit «Abbrechen» wieder schliessen, ohne einen Antrag anzulegen
    - Budget: das Zielbetrag-Feld der Defizit-Verteilung lässt sich ungestört eintippen — es wird beim Nachladen (auch durch Änderungen anderer) nicht mehr überschrieben, solange man darin schreibt
    - Budget: der Steuerfuss in der Summenzeile weist aus, wenn er durch die automatische Senkung vom geltenden Wert abweicht («gesenkt von 125%»)
    - Budget: die automatische Steuerfusssenkung bei Überschuss ist jetzt ein echter Antrag — sie reduziert die Einnahmen in der Summenzeile (der Ertrag geht dadurch auf null) und erscheint am Ende der Antragsliste und des Antrags-PDF
    - Budget: klar geregeltes Zusammenspiel von gewünschtem Ertrag und Steuerfusssenkung — sind beide Automatiken eingeschaltet, wird ein Überschuss (auch ein als Zielbetrag gewünschter Ertrag) über die Steuerfusssenkung ausgeglichen, sodass der Gesamtertrag null ist
    - Budget: das Anträge-PDF enthält nur noch die tatsächlich einzureichenden Anträge — ein Pauschalantrag, dessen Einreichen-Entscheid abgeschaltet ist, erscheint nicht mehr darin
    - Budget: der Knopf «Novemberbrief einlesen» erscheint nur noch, wenn wirklich ein passender, mindestens zwei Tage alter Novemberbrief vorliegt (nicht mehr ganzjährig)
    - Budget: ein Pauschalantrag trägt einen Antragsteller (Fraktion, vorbelegt mit der eigenen Fraktion)
    - Budget: der automatische Defizit-Ausgleich und die weiteren Pauschalanträge stehen jetzt in einem gemeinsamen Kasten «Pauschalanträge»
    - Grün und Rot für positive/negative Werte sind jetzt überall in der App identisch und stammen aus einer zentralen Farbdefinition (vorher stellenweise abweichend eingefärbt)
    - Budget: im Kopf der Budgetseite steht jetzt ein Link zur Weisung (dem Budget-Geschäft mit seiner Nummer) auf der Parlamentswebseite
    - Neuer Bereich «Bedienungsanleitung» (vor «Änderungsverlauf»): zeigt die Anleitung direkt im Tool, formatiert aus dem README
    - Jeder Bereich ist jetzt über einen Link direkt aufrufbar und teilbar (z.B. …/#budget); Vor/Zurück im Browser wechselt den Bereich

- 2026-08-24 **1.8.2**
    - Budget-Anträge erhalten Herkunft, Haltung, Prozentwert, unterstützende Fraktionen und Notizen
        - Jeder Antrag hat eine Herkunft «eigen» oder «fremd» — wie beim Vorstoss; der Antragsteller wird aus einer Liste gewählt (eigene Anträge: eine Person, vorbelegt mit der eintragenden Person; fremde: eine Fraktion oder Person)
        - Der Betrag lässt sich in CHF oder in Prozent des Budgetwerts der Position eingeben; das jeweils andere Feld wird sofort nachgerechnet, ein Umschalter wechselt zwischen Reduktion (Standard) und Mehrausgabe
        - Steuerfuss-Anträge werden in Prozentpunkten gestellt; der Einfluss auf die erwarteten Steuereinnahmen wird anteilig berechnet
        - Zu jedem Antrag lässt sich unsere Haltung festhalten (eigene: einreichen/nicht einreichen; fremde: unterstützen/nicht/offen) und mit einer Mehrfachauswahl, welche Fraktionen ihn unterstützen — die eigene Fraktion ist bei Zustimmung automatisch dabei
        - Anträge können Notizen tragen, mit demselben Editor wie überall (Versionen, Löschen mit Wiederherstellen)
        - In der Übersicht zählt nur, was die Fraktion unterstützt; im Sitzungsmodus nur die vom Parlament angenommenen Anträge
        - Ein Pauschalantrag hat einen Einreichen-Entscheid; einzelne Positionen lassen sich davon ausnehmen, der eingesparte Betrag verteilt sich dann neu auf die übrigen
        - Beliebig viele, voneinander unabhängige Pauschalanträge lassen sich anlegen (Einsparung in CHF oder in Prozent des ursprünglichen Aufwands); ihre Kürzungen kumulieren, der automatische Ausgleich auf ein Ziel wird zuletzt gerechnet
        - Vorbereitungs- und Sitzungsanträge werden bei Eindeutigkeit (gleiche Position, gleicher Betrag) automatisch verknüpft und übernehmen die Haltung; im Sitzungsmodus ist der Verknüpfungsstand sichtbar
    - Alle Notizen nutzen jetzt dieselbe Notiz-Komponente und dasselbe Aussehen wie überall (aufklappbare Liste, «+ Neue Notiz», Versionen, Löschen mit Wiederherstellen) — auch die Notizen zur Sitzung, die Notizen an einem Traktandum ohne Geschäftsbezug und die nur lesend gezeigten Notizen verknüpfter Sitzungen; der frühere abweichende Editor entfällt vollständig, bestehende Notizen werden automatisch übernommen
    - Filter bieten überall nur noch die Werte an, die tatsächlich vorkommen: der Zuständigkeitsfilter der Geschäfte zeigt nur Personen, die wirklich zuständig sind (nicht jedes Mitglied), der Prioritätsfilter nur vorkommende Stufen, die Vorstoss-Filter nur vorkommende Herkünfte und Status, und bei den Mitgliedern bieten die Kommissions- und Funktionsfilter nur an, was es auch gibt — keine leeren Auswahlmöglichkeiten mehr
        - Ein leerer/undefinierter Wert ist ebenfalls ein echter Wert: gibt es Geschäfte ohne Zuständige, lässt sich nach «Nicht zugewiesen» filtern; gibt es Geschäfte ohne gesetzte Priorität, nach «Undefiniert» (getrennt von «Mittel»)
    - Robustes Nachladen bei schlechter Leitung: kann etwas nicht geladen werden, wird es automatisch immer wieder versucht, bis es klappt (mit wachsender Wartezeit); nichts bleibt mehr dauerhaft «hängen»

- 2026-08-22 **1.8.1**
    - Neuer Bereich «Budget» zur Unterstützung des städtischen Budgetprozesses
        - Budget-Seite mit vier Tabs: Globalbudgets (Teil B), Personalbestand, Investitionsrechnung und Steuerfuss
        - Filter nach Budgetjahr (nur vorhandene Jahre, neuestes zuvorderst), zuständiger Kommission bzw. Departement sowie nach Kostensteigerung in Prozent und in Franken
        - Ständig sichtbare Summenzeile (Total Stellen, Ausgaben, Einnahmen, Ertrag bzw. Defizit, Steuerfuss) mit Differenz zum Vorjahr; sie richtet sich immer nach den gesetzten Filtern
        - Eigene und fremde Anträge je Produktegruppe erfassen; globale Kürzungen werden anteilig zum Aufwand automatisch auf die Produktegruppen verteilt — ein Defizit wird standardmässig zur schwarzen Null ausgeglichen, ein Überschuss bleibt erhalten, das Ziel (Defizit/Ertrag) ist einstellbar
        - Personalanträge (Stellen mit Betrag, Standardbetrag pro Stelle im Verwaltungsbereich konfigurierbar), Investitionsanträge je Projekt und automatische Steuerfuss-Senkung bei Überschuss
        - Anträge der Fraktion als PDF erzeugen (gesamt oder je Kommission) und Beschlüsse in der Sitzung live mitverfolgen
        - Budgetdaten werden aus den städtischen Budgetbüchern (Teil A und Teil B als PDF) eingelesen; über «+ Neu» lässt sich ein vergangenes Budgetjahr aus der Liste der Jahre mit vorhandenen Unterlagen importieren und der Novemberbrief nachträglich einlesen
        - Beim Import werden je Produktegruppe Globalkredit, Aufwand, Ertrag und Stellen, der vollständige Produktegruppenname, der Auftragstext, die einzelnen Produkte (mit Nettokosten) sowie die Erläuterungen und Begründungen (Stellenplan, Abweichung, Finanzplan, Massnahmen), dazu der Steuerfuss, der Gesamt-Steuerertrag und die Investitionen je einzelnem Projekt (mit Departement, Produktegruppe, Budget- und Planwerten) aus den Büchern übernommen; das Einlesen ist gegenüber dem genauen Seitenaufbau tolerant und wurde gegen die Budgetbücher 2022 bis 2026 geprüft
        - Automatisch erzeugte Kürzungsanträge lassen sich in der Antragsliste eigens filtern (alle, nur manuelle, nur automatische)
        - Filter «Zuständige Kommission»: im Verwaltungsbereich lässt sich jedem Departement seine Sachkommission zuordnen; der Filter engt die Ansicht auf die Departemente der gewählten Kommission ein und ist mit dem Departement-Filter verknüpft
        - Sitzungsmodus (Schalter unten im Budget-Filter): trennt die internen Fraktionsanträge der Vorbereitung von den offiziellen Sitzungsanträgen der Budgetdebatte; im Sitzungsmodus zählen für die Summen nur die angenommenen Sitzungsanträge, und die automatische Verteilung entfällt
        - Die offiziellen Sitzungsanträge werden in die Sitzung mit Budgetdebatte gespiegelt: dort erscheinen dieselben Anträge samt dem Entscheid im Saal (angenommen/abgelehnt/offen), sobald das Budget traktandiert ist
        - Ein neues Budgetjahr wird automatisch eingelesen, sobald dessen Weisung vorliegt; ein vorhandener Novemberbrief wird dabei nachgezogen
        - Beschlüsse zu Budgetanträgen (angenommen/abgelehnt/offen) werden während der Sitzung laufend und für alle gleichzeitig sichtbar mitverfolgt

- 2026-08-22 **1.8.0**
    - In der Geschäfteliste lässt sich neu nach Einreicher filtern — je ein Filter für die Person und für die Partei
        - Beim Person-Filter schaltet «Nur Ersteinreicher» (standardmässig aus) die Auswahl auf den Erstunterzeichner um; sonst zählt jeder Einreicher (Erst- und Mitunterzeichner)
        - Der Partei-Filter zeigt Geschäfte, bei denen ein Einreicher zur gewählten Partei gehört
    - Dokumente lassen sich überall gleich verwalten und zusätzlich mit bestehenden Dateien verknüpfen
        - Neuer Knopf «Verknüpfen» (neben «+ Neues Dokument» und «Hochladen»): eine bestehende Datei über den Dateiauswahldialog einbinden, unabhängig vom Dateinamen; der Dialog startet im Jahres-Ordner, ist aber frei navigierbar
        - Verknüpfte Dokumente sind gekennzeichnet und lassen sich wieder lösen (die Datei selbst bleibt bestehen)
        - Dokument-Ordner sind jahr-basiert statt nach interner Versionsnummer (kein «V6-» mehr im Pfad)
    - Behoben: Änderungen anderer Benutzer liessen die Liste kurz verschwinden und den Scrollbalken/Fokus springen — Sitzungen und Geschäfte aktualisieren sich jetzt an Ort und Stelle, ohne Lade-Flackern
    - «Beschlussantrag» ist neu als Vorstossart wählbar
    - Wichtigkeit (Priorität) ist jetzt in allen Ansichten farblich hinterlegt — auch bei den Traktanden einer Sitzung
    - Eine Sitzung lässt sich jetzt auch mit Vorstössen verknüpfen (eigene wie fremde) — im Sitzungsdetail unter «Verknüpfte Vorstösse», damit sie an der Sitzung traktandiert sind

- 2026-08-10 **1.7.34**
    - Ein selbst angelegtes Geschäft lässt sich jetzt mit dem offiziellen Parlamentsgeschäft verknüpfen — genau wie ein Vorstoss mit einem Geschäft
        - In der Maske eines eigenen Geschäfts öffnet «Mit offiziellem Geschäft verknüpfen» einen Auswahldialog, der die ähnlichsten Geschäfte zuoberst vorschlägt (Suche nach Nummer oder Titel möglich); angeboten werden nur offizielle Geschäfte, erledigte eingeschlossen
        - Nach der Wahl ist das eigene Geschäft erledigt und mit dem offiziellen verknüpft
        - Notizen und alle weiteren Angaben (Priorität, Typ, Kommission, Datum, Inhalt, Zuständigkeit) wandern dabei ins offizielle Geschäft, sofern sie dort noch nicht gesetzt sind — bereits vorhandene Angaben des offiziellen Geschäfts bleiben unverändert
        - Die beiden Geschäfte sind gegenseitig verlinkt und lassen sich hin und her anklicken: das offizielle zeigt die verknüpften eigenen Geschäfte, das eigene verweist zurück aufs offizielle

- 2026-08-07 **1.7.33**
    - Die Auswahlfelder für Kommission, Priorität, Typ und Datum funktionieren überall gleich — dasselbe Feld sieht überall gleich aus und verhält sich gleich
        - Das Kommissions-Auswahlfeld zeigt die Namen jetzt gekürzt an (wie überall sonst); gespeichert wird weiterhin der volle Name
    - Beim Anlegen eines eigenen Geschäfts ist die anlegende Person automatisch als zuständig vorausgewählt (vor dem Speichern noch änderbar)
    - Ein Statuswechsel erzeugt jetzt genau einen Eintrag in der Aktionszeitleiste (bisher entstand zusätzlich ein irreführender Zwischeneintrag «→ —»)

- 2026-07-27 **1.7.32**
    - Notizen speichern jetzt nur noch bewusst über das Häkchen; Abbrechen (✕) verwirft
        - Kein automatisches Zwischenspeichern mehr nach fünf Sekunden und kein Speichern beim Verlassen des Feldes — das führte zu unvollständig gespeicherten Notizen und einer unzuverlässigen Versionsgeschichte
        - Wer eine noch nicht gespeicherte Notiz offen hat und die Seite verlässt (Zurück, anderer Link, Tab schliessen) oder den Dialog schliesst, wird gewarnt
        - Der Versionsverlauf ist wieder verlässlich: jedes bewusste Speichern legt eine Version an, mehrzeilige Notizen gehen nicht mehr verloren
    - Gelöschte Notizen erscheinen wieder in der Aktionszeitleiste als «… hat seine Notiz gelöscht» (mit Wiederherstellen für den Verfasser), nicht mehr zwischen den aktiven Notizen
        - Das gilt auch für Sitzungsnotizen direkt in der Traktandenliste einer Sitzung: gelöscht und wiederhergestellt wird dort über dieselbe Aktionszeitleiste
    - Das «Votum im Rat» erfasst weiterhin die zuständige Person; für alle anderen erscheint das Feld nur, wenn ein Votum vorliegt (kein leerer, unbrauchbarer Kasten mehr)

- 2026-07-26 **1.7.31**
    - Eigenes Geschäft anlegen ist vollständiger: das Datum ist auf heute vorbelegt, direkt unter dem Titel steht ein Beschreibungstext (mit Formatierung), der Titel nutzt die ganze Breite
        - Neu wählbar: eine Kommission (nur aktive, höchstens eine, «keine» ist erlaubt)
        - Der Typ kommt aus einer Liste, die der Administrator pflegt; der Status wird aus den bereits vorkommenden Werten vorgeschlagen und lässt sich frei überschreiben
    - Neuer Verwaltungsbereich «Typen für eigene Geschäfte»: Bezeichnungen hinzufügen und löschen, automatisch gespeichert
    - Bei einer neuen Sitzung zeigt «Verknüpfen mit» die Auswahl wieder an – die aufgeklappte Liste war bisher hinter dem Dialog verborgen

- 2026-07-24 **1.7.30**
    - Die Aktionszeitleiste gibt es jetzt auch bei den Vorstössen – dieselbe Darstellung wie beim Geschäft
    - Sitzungstypen lassen sich wieder anlegen; das Anlegen mit nur einem Namen schlug bisher fehl, und im Bearbeiten stehen alle Felder zur Verfügung
    - Im Sitzungstyp sind die beiden Schalter «Eigene Fraktion» und «Beim Anlegen Verknüpfung anbieten» wieder anklickbar
    - «+ Neue Sitzung» öffnet ein Menü mit je einem Eintrag pro Sitzungstyp; ein Klick darauf führt direkt ins vollständige Formular, ohne definierten Sitzungstyp steht ein Hinweis im Menü
    - Eigene Geschäfte lassen sich jetzt vollständig pflegen: Titel, Typ, Status und Datum sind direkt in der Geschäftsmaske änderbar, und ein selbst angelegtes Geschäft kann wieder gelöscht werden
        - Geschäfte von der Parlamentswebseite bleiben unverändert schreibgeschützt, damit die Angaben aus der Quelle erhalten bleiben
    - «+ Neuer Vorstoss», «+ Eigenes Geschäft» und «+ Neuer Typ» öffnen dieselbe vollständige Maske wie das Bearbeiten, mit allen Feldern
        - Beim Anlegen wird nichts vorab gespeichert: unten stehen «Speichern» und «Abbrechen»; erst «Speichern» legt den Eintrag an und die Maske geht gleich in die Bearbeitung über, sodass sich Dokumente und Notizen direkt anschliessen lassen
        - Ein Klick neben die Maske verwirft nichts mehr — so gehen angefangene Eingaben nicht mehr versehentlich verloren; verworfen wird nur über «Abbrechen»
        - Notizen, Dokumente und der Verlauf erscheinen erst nach dem Speichern, weil sie sich auf einen bereits bestehenden Eintrag beziehen; ein Hinweis in der Maske sagt das
    - Jede Änderung hinterlässt eine Spur: geänderte Angaben und Prioritäten erscheinen mit Vorher- und Nachher-Wert im Verlauf des Geschäfts
    - Das Votum im Rat lässt sich jetzt direkt im Geschäft erfassen: Wortlaut schreiben, laufend automatisch gespeichert, als PDF drucken und archivieren
        - Erfassen darf es die für das Geschäft zuständige Person; alle anderen sehen den Wortlaut, können ihn aber nicht ändern
        - Bisher war diese Funktion nur über die Druckansicht erreichbar, ohne Möglichkeit, den Text einzugeben
        - In der Druckansicht öffnet sich der Druckdialog wieder von selbst, und der Knopf «Als PDF speichern / drucken» reagiert wieder — beides blieb bisher wirkungslos
        - Der Dialog erscheint jetzt auch dann, wenn eine Schriftart nicht geladen werden kann
        - Die Druckansicht übernimmt weiterhin die volle Formatierung, gibt aber nur noch Gestaltungselemente aus: eingebettete Skripte, an Elementen hängender Code für Ereignisse und Verweise mit ausführbarem Ziel erscheinen nicht mehr im Ausdruck
    - Neue Sitzungsnotizen: Notizen, die man in einer Sitzung zu einem verknüpften Geschäft erfasst, haften nun am Geschäft (nicht mehr nur an dieser einen Sitzung)
        - Sie erscheinen automatisch bei jeder aktuellen und künftigen Sitzung, an der dasselbe Geschäft hängt – auch wenn das Geschäft auf eine spätere Sitzung verschoben wird
        - Im Geschäft selbst erscheinen die Sitzungsnotizen separat unter den normalen Notizen, aufklappbar und standardmässig eingeklappt
    - Der Hinweistext beim Synchronisations-Zeitplan nennt jetzt die tatsächlich vorbelegten Standardzeiten (alle Wochentage um 10:00 und 18:00 Uhr) statt der früheren Zeiten
    - Selbst angelegte Geschäfte bleiben bei der automatischen Aktualisierung erhalten — bisher konnten sie bei einer Synchronisation fälschlich verschwinden
    - Die Gesamt-Testauswertung zeigt nur noch Ergebnisse des aktuellen Laufs; ein liegengebliebener Bericht eines früheren Laufs wurde bisher mitgezählt
    - Beim Betrieb bekommt kein Dienst mehr ein Verzeichnis des Servers zu sehen: die Routing-Einstellungen des vorgelagerten Webservers stecken jetzt in seinem Abbild; beim Aktualisieren wird er mitgebaut (`--build`, siehe Betriebsanleitung)

- 2026-07-20 **1.7.29**
    - Notizen bei Vorstössen funktionieren jetzt genau gleich wie bei Geschäften: derselbe Editor mit Formatierung, ein Versionsverlauf zum Zurückblättern und Übernehmen älterer Fassungen, Zwischenspeichern während des Tippens, sofortiges Speichern beim Verlassen sowie Löschen und Wiederherstellen durch den Verfasser
    - Eingereichte Geschäfte werden dem einreichenden Fraktionsmitglied wieder automatisch als zuständig zugeordnet – auch wenn die Parlamentswebseite den Namen in der Reihenfolge «Nachname Vorname» liefert
    - Läuft die automatische Aktualisierung ohne eigenen Zeitplan, gilt neu ein sinnvoller Standard: zwei Läufe an allen Wochentagen um 10:00 und um 18:00 Uhr; dieser Standard erscheint in der Verwaltung vorbelegt und lässt sich dort bearbeiten
    - Die Funktions- und Entwickler-Dokumentation wurde vollständig ausformuliert: alle Auswahlwerte, Standard-Sortierungen, Berechtigungen und Fehlerfälle sind nun vollständig beschrieben

- 2026-07-19 **1.7.28**
    - Jede Eingabe speichert überall sofort – die Knöpfe zum Abbrechen und Speichern entfallen in der ganzen App (Konsistenz)
        - Vorstösse: Neu-Anlegen über einen minimalen Titel-Dialog («Erstellen»), danach öffnet direkt die Bearbeitung; Notizen speichern beim Verlassen des Editors
        - Sitzungstypen: Bearbeiten per Klick auf die Karte, jede Eingabe speichert sofort; Neu-Anlegen über einen minimalen Namens-Dialog
        - Auch die Dialoge «Eigenes Geschäft», «Neue Sitzung» und «Neues Dokument» kommen ohne Abbrechen-Knopf aus (✕ schliesst)
    - Die Priorität ist neu auch in der Geschäfts-Detailansicht sichtbar und einstellbar
    - Verbindliche Design-Anforderungen und eine vollständige Funktions-Spezifikation sind neu dokumentiert (CONTRIBUTING, FEATURES)
    - Der Browser-Testlauf prüft immer den aktuellen Stand: das Abbild für den Test wird vor jedem Lauf neu gebaut, der Testbericht liegt in einem frischen Verzeichnis pro Lauf
    - Kürzel gelten neu überall: die definierten Kurzformen kürzen nicht nur Status, sondern auch Partei-, Fraktions- und Kommissionsnamen — in allen Listen, Karten und Auswahlfeldern; das Suchtext-Feld der Verwaltung schlägt zusätzlich die aktuellen Fraktions- und Parteinamen vor
    - Der Zeitplan der automatischen Synchronisation ist in der Verwaltung einstellbar: beliebige Einträge mit Wochentagen (Mo–So) und Uhrzeit, mit Hinzufügen und Löschen; verpasste Zeitpunkte werden nachgeholt

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
    - Die App-Aktualisierung bleibt nicht mehr im Wartungsmodus stecken: Lässt sich eine Datenbank-Anpassung beim Update nicht anwenden, stoppt der Dienst jetzt zuverlässig und macht den Fehler sichtbar, statt unerreichbar hängen zu bleiben — nach einem Neustart läuft die Aktualisierung erneut

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
    - Die ausgelieferten Container werden neu automatisch darauf geprüft, dass sie keine Shell und keine Skriptsprache enthalten — wer Codeausführung im Container erreicht, findet dort kein Werkzeug vor, mit dem er weiterkommt
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
    - Notiz erfassen: Ein Klick auf die Formatierungsleiste (Fett, Kursiv, Aufzählung …) verwarf bisher den bereits getippten Text und speicherte die Notiz vorzeitig
        - Der getippte Text bleibt beim Formatieren nun erhalten; gespeichert wird erst, wenn das Eingabefeld wirklich verlassen wird
    - Das Notizfeld sitzt nicht mehr in einem überflüssigen Rahmen: es beginnt kompakt, wächst mit dem Inhalt und rollt erst, wenn es seine maximale Höhe erreicht
    - Rückmeldungen wie «Notiz gespeichert» erscheinen neu als gewohnte Nextcloud-Benachrichtigung statt als übersehbare Zeile am Seitenende

- 2026-07-10 **1.7.11**
    - Behoben (seit 1.6.2): Beim langsamen Tippen einer Notiz oder eines Fraktionsbeschlusses erschien dieselbe Änderung mehrfach im Verlauf
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
    - Behoben: Beim automatischen Aktualisieren (wenn jemand anderes etwas speichert) sprangen die Sitzungs- und die Geschäftsliste an den Anfang; sie werden nun an Ort und Stelle aktualisiert, ohne dass die Ansicht springt

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
    - Behoben: In der Geschäftsliste war der Beschluss nicht lesbar – die Spalte war zu schmal und schnitt den Text ab (z.B. nur «Zu» statt «Zustimmung»); sie ist jetzt breit genug
    - Behoben: Bei nur einem Sitzungstyp liess sich keine neue Sitzung anlegen – der «+»-Knopf führte die Aktion direkt aus, statt das Auswahlmenü zu öffnen; jetzt erscheint immer das Menü
    - Behoben: Geschäfts-Detailansicht – lange Verfasser-Listen sprengten die Breite; die Tabelle bricht jetzt um. «Einreichende» heisst neu «Einreicher», und die Rollen erscheinen ohne Schrägstrich-Gendern (z.B. «Erstunterzeichner» statt «Erstunterzeichner/-in»)

- 2026-06-19 **1.6.2**
    - Behebt, dass sich die App nach einem Nextcloud-Upgrade nicht mehr aktivieren liess («could not enable app»): Eine Datenbank-Anpassung beim Aktivieren verwendete eine in Nextcloud 34 entfernte interne Funktion und brach die Aktivierung ab. Sie ermittelt den Tabellennamen jetzt über die System-Konfiguration

- 2026-06-19 **1.6.1**
    - Weitere Anpassungen an Nextcloud 34 (Fortsetzung von 1.6.0):
        - Geschäfts- und Sitzungslisten laden wieder vollständig (Nextcloud 34 wies grosse Listenabfragen mit «Interner Serverfehler» ab; betraf auch die Verwaltungsseite und die Status-Kürzel)
        - Auch die Verwaltungsseite öffnet wieder fehlerfrei (gleiche in Nextcloud 34 entfernte interne Schnittstelle wie bei der Startseite)
        - Seitenleiste sieht wieder genau wie die übrigen Nextcloud-Apps (z.B. Dateien) aus: durchgängig Nextclouds Standard-Aufbau und -Symbole übernommen (statt eigener Darstellung mit Emoji), inklusive korrektem Hintergrund und Layout nach der Aktualisierung

- 2026-06-19 **1.6.0**
    - Kompatibilität mit Nextcloud 34 wiederhergestellt: Nextcloud wurde kurz nach der letzten Veröffentlichung von Version 33 auf 34 angehoben. Dieser Versionssprung brachte mehrere Änderungen, mit denen das bisherige Tool nicht mehr zusammenpasste – es wurde unter Nextcloud 34 sogar automatisch abgeschaltet. Die folgenden Anpassungen stellen die Kompatibilität wieder her (fortgesetzt in 1.6.1):
        - Die App bleibt nach Aktualisierungen von Nextcloud aktiv: die obere Nextcloud-Versionsgrenze wurde aufgehoben, sodass ein Nextcloud-Upgrade die App nicht mehr automatisch deaktiviert
        - Lauffähig unter Nextcloud 34: die Startseite lädt wieder (eine in Nextcloud 34 entfernte interne Schnittstelle wird nicht mehr verwendet; vorher «Interner Serverfehler»)
    - Die Uhrzeiten der automatischen Synchronisation sind konfigurierbar (Standard 03:00 und 15:00 Uhr)
    - Behoben: Automatische Synchronisation lief nicht

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
    - Fraktionsordner und Kalender werden automatisch beim Öffnen und bei Wechsel der Fraktionsgruppe geprüft und ergänzt – der Knopf von Hand und die Einstellung «Kalender-Benutzer» entfallen (es wird immer das Administratorkonto verwendet)
    - Eigene Geschäfte lassen sich wieder anlegen (Speicherfehler behoben)
    - Status-Kürzel bleiben gespeichert, werden automatisch gespeichert und in der Verwaltung korrekt angezeigt (vorher «[object Object]» bzw. in Firefox eine leere Liste durch zwei sich überschreibende Implementierungen)
    - Beschluss- und Notizänderungen erscheinen bei allen Mitgliedern sofort ohne Neuladen
    - Zusammenarbeit mehrerer Mitglieder durchgehend mit einem Test über mehrere Benutzer abgesichert (Ordner, Kalender, Dokumente, Echtzeit)

- 2026-06-15 **1.4.1**
    - Fraktionsordner und Kalender funktionieren jetzt korrekt mit Gruppenmitgliedern
        - Das Administratorkonto legt Ordner und Kalender an und teilt sie mit der Gruppe
        - Alle Mitglieder sehen Ordner und Kalender
        - Dateien und Termine für alle Mitglieder lesbar und bearbeitbar

- 2026-06-14 **1.3.4**
    - Status-Kürzel in der Verwaltung
        - Textersetzungen für lange Statusbeschriftungen (z.B. «BSKK»)
        - Automatisches Speichern nach 5s oder beim Verlassen des Feldes
    - Dokumente hochladen: bestehende Dateien direkt hochladen
    - Eigene Geschäfte erstellen: Geschäfte ausserhalb Parlamentsregister
    - Fraktions-Infrastruktur automatisch beim Start prüfen

- 2026-05-29 **1.3.3**
    - Miteinreicher aus Parlamentswebseite einlesen
        - Alle Einreichenden mit Rolle in Detailansicht
        - Namen als Zusatzzeile in Geschäftsliste
    - Behoben: Freier Text im Fraktionsentscheid korrekt anzeigen
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
    - Notizen speichern in der Sitzungsliste automatisch
        - Beim Verlassen des Feldes oder nach 5s Pause
        - «+»-Knopf entfernt
    - Gemeinsame Hilfsfunktionen (vollerName, personKey, parseNotizen)
        - Zentrale utils.js
        - Doppelter Code beseitigt

- 2026-05-27 **1.3.0**
    - Ungültige HTML-Struktur in Geschäfts-Detail behoben
    - Fehler beim Laden der Kommissionsliste behoben
    - Teststabilität verbessert (console.error → Testfehler)
    - Hinweise von PHPUnit in den Tests gegen die echte Webseite behoben

- 2026-05-27 **1.2.9**
    - WebSocket-Verbindung funktioniert wieder
        - Nginx-Pfad korrekt konfiguriert
        - Name des Container-Dienstes angepasst
    - Nextcloud bleibt nicht im Wartungsmodus hängen
    - Beschlusstext speichert beim Feldverlust, nicht sofort

- 2026-05-26 **1.2.8**
    - Synchronisations-Zuverlässigkeit verbessert
        - Fehler während der Synchronisation führen keine falschen Löschungen
    - Traktanden einzeln abgeglichen statt neu angelegt
        - Bestehende Notizen bleiben
    - Gelöschte Objekte bei der nächsten Synchronisation automatisch wiederhergestellt
    - Migrationen beim Start zuverlässig ausgeführt
    - Notizen/Beschlüsse mit Maus verschiebbar (nur über ⠿-Symbol)
    - Beschluss-Widget überall gleich, Freitext speichert automatisch
    - Formular-Beschriftungen einheitlich

- 2026-05-24 **1.2.7**
    - Interne Fraktionssitzungen in Liste
        - Mit Titel, Zweck, Traktanden, Notizen
    - Erstellungsformular im Stil des Nextcloud-Kalenders
    - Interne Sitzungen gekennzeichnet
    - Der Abgleich mit dem Parlament löscht interne Sitzungen nicht
    - «Fraktionsmitglieder ↔ Nextcloud-Benutzer»: auch inaktive Personen anzeigen

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

## Ältere Versionen

Einzelheiten zu den Versionen vor 1.2.4 stehen im Verlauf von Git.
