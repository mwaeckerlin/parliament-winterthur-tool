<?php
declare(strict_types=1);

/**
 * @var array{
 *     id: int,
 *     externId?: string,
 *     titel?: string,
 *     status?: string,
 *     aktuellesVotum?: array{text?: string, erstelltAm?: string, autorName?: string}|null,
 *     zustaendigkeiten?: array<int, array{personName?: string, istHaupt?: bool}>,
 *     letzterBeschluss?: array{aktionCode?: string, erstelltAm?: string}|null
 * } $_
 */

// p() ist eine globale Nextcloud-Template-Funktion (escaping); muss nicht importiert werden.

// Hilfsfunktion: HTML aus Datenbank ist von TipTap erzeugt und enthält
// nur Tags aus dem von uns kontrollierten Whitelist (p, h2, h3, ul, ol, li,
// blockquote, strong, em, u, s, a). Wir geben es 1:1 aus, damit die
// Formatierung erhalten bleibt.
$votum = is_array($_['aktuellesVotum'] ?? null) ? $_['aktuellesVotum'] : null;
$votumText = (string) ($votum['text'] ?? '');
$titel = (string) ($_['titel'] ?? '');
$externId = (string) ($_['externId'] ?? '');
$zustaendige = is_array($_['zustaendigkeiten'] ?? null) ? $_['zustaendigkeiten'] : [];
$beschluss = is_array($_['letzterBeschluss'] ?? null) ? $_['letzterBeschluss'] : null;
$datum = $votum['erstelltAm'] ?? (new \DateTimeImmutable())->format('Y-m-d H:i');
$autor = (string) ($votum['autorName'] ?? '');
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votum – <?php p($externId !== '' ? $externId : (string) $_['id']); ?></title>
    <style>
        @page { size: A4; margin: 2.2cm 2cm 2.4cm 2cm; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #111;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.55;
        }
        body { padding: 1.5cm; }
        header.kopf {
            border-bottom: 2px solid #002b5c;
            padding-bottom: 0.6em;
            margin-bottom: 1.2em;
        }
        header.kopf h1 {
            font-size: 13pt;
            margin: 0 0 0.2em 0;
            color: #002b5c;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        header.kopf .geschaeft-titel {
            font-size: 15pt;
            font-weight: 600;
            margin: 0.2em 0 0 0;
            color: #111;
        }
        .meta {
            display: table;
            width: 100%;
            margin: 0.8em 0 1.2em 0;
            font-size: 10pt;
            color: #333;
        }
        .meta-zeile { display: table-row; }
        .meta-label, .meta-wert { display: table-cell; padding: 2px 0; }
        .meta-label {
            width: 38%;
            font-weight: 600;
            color: #555;
            padding-right: 1em;
        }
        h2.abschnitt {
            font-size: 12pt;
            color: #002b5c;
            margin: 1.2em 0 0.4em 0;
            border-bottom: 1px solid #ccc;
            padding-bottom: 0.2em;
        }
        .votum {
            font-size: 11pt;
            line-height: 1.6;
        }
        .votum h2 { font-size: 12pt; font-weight: 600; margin: 0.8em 0 0.3em; }
        .votum h3 { font-size: 11pt; font-weight: 600; margin: 0.7em 0 0.3em; }
        .votum p { margin: 0.4em 0; }
        .votum ul, .votum ol { padding-left: 1.6em; margin: 0.4em 0; }
        .votum blockquote {
            border-left: 3px solid #002b5c;
            margin: 0.5em 0;
            padding: 0.1em 0.8em;
            color: #333;
            background: #f4f6fa;
        }
        .votum a { color: #002b5c; }
        .leer {
            color: #888;
            font-style: italic;
        }
        footer.fuss {
            margin-top: 2em;
            padding-top: 0.6em;
            border-top: 1px solid #ccc;
            font-size: 9pt;
            color: #666;
        }
        .druck-knopf {
            position: fixed;
            top: 1em;
            right: 1em;
            padding: 0.6em 1em;
            background: #002b5c;
            color: #fff;
            border: 0;
            border-radius: 4px;
            font-size: 11pt;
            cursor: pointer;
            z-index: 1000;
        }
        @media print {
            .druck-knopf { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <button type="button" class="druck-knopf" onclick="window.print()">Als PDF speichern / drucken</button>

    <header class="kopf">
        <h1>Votum im Rat</h1>
        <p class="geschaeft-titel"><?php p($titel !== '' ? $titel : 'Geschäft #' . (string) $_['id']); ?></p>
    </header>

    <div class="meta">
        <?php if ($externId !== ''): ?>
        <div class="meta-zeile">
            <div class="meta-label">Geschäftsnummer</div>
            <div class="meta-wert"><?php p($externId); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($zustaendige)): ?>
        <div class="meta-zeile">
            <div class="meta-label">Zuständig (Fraktion)</div>
            <div class="meta-wert"><?php
                $teile = [];
                foreach ($zustaendige as $z) {
                    $name = trim((string) ($z['personName'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    if (!empty($z['istHaupt'])) {
                        $name .= ' (Hauptverantwortung)';
                    }
                    $teile[] = $name;
                }
                p(implode(', ', $teile));
            ?></div>
        </div>
        <?php endif; ?>
        <?php if ($beschluss !== null): ?>
        <div class="meta-zeile">
            <div class="meta-label">Letzter Beschluss der Fraktion</div>
            <div class="meta-wert">
                <?php p((string) ($beschluss['aktionCode'] ?? '')); ?>
                <?php if (!empty($beschluss['erstelltAm'])): ?>
                    (<?php p((string) $beschluss['erstelltAm']); ?>)
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($autor !== ''): ?>
        <div class="meta-zeile">
            <div class="meta-label">Erfasst von</div>
            <div class="meta-wert"><?php p($autor); ?></div>
        </div>
        <?php endif; ?>
        <div class="meta-zeile">
            <div class="meta-label">Stand</div>
            <div class="meta-wert"><?php p((string) $datum); ?></div>
        </div>
    </div>

    <h2 class="abschnitt">Wortlaut</h2>
    <div class="votum">
        <?php if (trim(strip_tags($votumText)) === ''): ?>
            <p class="leer">— Noch kein Votum erfasst —</p>
        <?php else: ?>
            <?php
            // TipTap-HTML ist auf ein sicheres Tag-Whitelist beschraenkt.
            // Wir reichen es durch, entfernen aber sicherheitshalber
            // alle script/style/iframe/object/embed Elemente.
            $sicher = preg_replace(
                '#<(script|style|iframe|object|embed|link|meta)\b[^>]*>.*?</\1>#is',
                '',
                $votumText
            );
            $sicher = preg_replace(
                '#<(script|style|iframe|object|embed|link|meta)\b[^>]*/?>#is',
                '',
                (string) $sicher
            );
            // on*-Attribute entfernen
            $sicher = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)#i', '', (string) $sicher);
            echo $sicher;
            ?>
        <?php endif; ?>
    </div>

    <footer class="fuss">
        Parliament Winterthur · Geschäft <?php p($externId !== '' ? $externId : (string) $_['id']); ?> · ausgedruckt am <?php p((new \DateTimeImmutable())->format('d.m.Y H:i')); ?>
    </footer>

    <script>
        // Druck-Dialog automatisch oeffnen, sobald die Schriftarten geladen sind.
        // Der Benutzer kann dann im Dialog "Als PDF speichern" waehlen.
        window.addEventListener('load', function () {
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(function () { window.print(); });
            } else {
                setTimeout(function () { window.print(); }, 250);
            }
        });
    </script>
</body>
</html>
