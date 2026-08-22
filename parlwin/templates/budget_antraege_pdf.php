<?php
declare(strict_types=1);

/**
 * Druckansicht «Budgetanträge der Fraktion» — gleiche Technik wie votum_pdf.php
 * (eigenständige A4-Seite, öffnet den Druck-Dialog automatisch, «Als PDF
 * speichern»). Kein serverseitiges PDF.
 *
 * @var array{
 *     jahr: int,
 *     kommission?: string|null,
 *     eintraege?: array<int, array{departement?: string, produktegruppe?: string, antrag?: array<string, mixed>}>
 * } $_
 */

$jahr = (int) ($_['jahr'] ?? 0);
$kommission = (string) ($_['kommission'] ?? '');
$eintraege = is_array($_['eintraege'] ?? null) ? $_['eintraege'] : [];

// Gruppierung nach Departement, Buchreihenfolge bleibt erhalten.
$nachDepartement = [];
foreach ($eintraege as $e) {
    $dep = (string) ($e['departement'] ?? '');
    $nachDepartement[$dep][] = $e;
}

$fr = static function ($betrag): string {
    $betrag = (int) $betrag;
    $vorzeichen = $betrag < 0 ? '−' : ($betrag > 0 ? '+' : '');
    return $vorzeichen . number_format(abs($betrag), 0, '.', "'");
};
$bereichLabel = [
    'globalbudget' => 'Globalbudget',
    'personal' => 'Personal',
    'investition' => 'Investition',
    'steuerfuss' => 'Steuerfuss',
];
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgetanträge <?php p((string) $jahr); ?></title>
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
            line-height: 1.5;
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
        header.kopf .unter {
            font-size: 15pt;
            font-weight: 600;
            margin: 0.2em 0 0 0;
            color: #111;
        }
        h2.departement {
            font-size: 12pt;
            color: #002b5c;
            margin: 1.2em 0 0.3em 0;
            border-bottom: 1px solid #ccc;
            padding-bottom: 0.2em;
        }
        table.antraege {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-bottom: 0.6em;
        }
        table.antraege th, table.antraege td {
            text-align: left;
            vertical-align: top;
            padding: 3px 6px;
            border-bottom: 1px solid #e0e0e0;
        }
        table.antraege th {
            color: #555;
            font-weight: 600;
            border-bottom: 1px solid #999;
        }
        td.betrag { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .auto { color: #777; font-style: italic; }
        .leer { color: #888; font-style: italic; }
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
    <button type="button" class="druck-knopf" id="druck-knopf">Als PDF speichern / drucken</button>

    <header class="kopf">
        <h1>Budgetanträge der Fraktion</h1>
        <p class="unter">Budget <?php p((string) $jahr); ?><?php if ($kommission !== ''): ?> · <?php p($kommission); ?><?php endif; ?></p>
    </header>

    <?php if (empty($eintraege)): ?>
        <p class="leer">— Keine Anträge erfasst —</p>
    <?php else: ?>
        <?php foreach ($nachDepartement as $dep => $liste): ?>
            <h2 class="departement"><?php p($dep !== '' ? $dep : 'Ohne Zuordnung'); ?></h2>
            <table class="antraege">
                <thead>
                    <tr>
                        <th style="width: 26%;">Produktegruppe</th>
                        <th style="width: 12%;">Bereich</th>
                        <th style="width: 14%; text-align: right;">Betrag</th>
                        <th style="width: 10%; text-align: right;">Stellen</th>
                        <th style="width: 18%;">Antragsteller</th>
                        <th>Begründung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($liste as $e):
                        $a = is_array($e['antrag'] ?? null) ? $e['antrag'] : [];
                        $auto = !empty($a['automatisch']);
                    ?>
                    <tr>
                        <td><?php p((string) ($e['produktegruppe'] ?? '')); ?></td>
                        <td><?php p($bereichLabel[(string) ($a['bereich'] ?? '')] ?? (string) ($a['bereich'] ?? '')); ?></td>
                        <td class="betrag"><?php p($fr($a['betragDelta'] ?? 0)); ?></td>
                        <td class="betrag"><?php p((string) ((float) ($a['stellenDelta'] ?? 0) !== 0.0 ? ($a['stellenDelta'] ?? '') : '')); ?></td>
                        <td><?php p((string) ($a['antragsteller'] ?? '')); ?><?php if ($auto): ?> <span class="auto">(automatisch)</span><?php endif; ?></td>
                        <td><?php p((string) ($a['begruendung'] ?? '')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <footer class="fuss">
        Parliament Winterthur · Budgetanträge <?php p((string) $jahr); ?> · ausgedruckt am <?php p((new \DateTimeImmutable())->format('d.m.Y H:i')); ?>
    </footer>

<?php
// CSP-Nonce für das Inline-Script (gleiches Muster wie votum_pdf.php/main.php).
$nonce = \OCP\Server::get(\OC\Security\CSP\ContentSecurityPolicyNonceManager::class)->getNonce();
?>
    <script nonce="<?php p($nonce); ?>">
        window.addEventListener('load', function () {
            var gedruckt = false;
            function drucken() {
                if (gedruckt) { return; }
                gedruckt = true;
                window.print();
            }
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(drucken);
            }
            setTimeout(drucken, 500);
        });
        document.getElementById('druck-knopf').addEventListener('click', function () {
            window.print();
        });
    </script>
</body>
</html>
