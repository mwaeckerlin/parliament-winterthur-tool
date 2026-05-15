<?php

declare(strict_types=1);

function waitForInstalled(string $statusUrl, int $maxAttempts = 180, int $sleepSeconds = 2): bool
{
    for ($i = 0; $i < $maxAttempts; $i++) {
        $json = @file_get_contents($statusUrl);
        if (is_string($json) && $json !== '') {
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data['installed'])) {
                return true;
            }
        }
        sleep($sleepSeconds);
    }
    return false;
}

/**
 * @return array{code:int, output:string}
 */
function runOcc(string $args): array
{
    $command = '/usr/bin/php /app/occ ' . $args;
    $output = [];
    $code = 1;
    @exec($command . ' 2>&1', $output, $code);
    return [
        'code' => (int) $code,
        'output' => trim(implode(PHP_EOL, $output)),
    ];
}

if (!waitForInstalled('http://nextcloud-nginx:8080/status.php')) {
    fwrite(STDERR, "parlwin-app-init: Nextcloud wurde nicht rechtzeitig installiert.\n");
    exit(1);
}

// Legacy-App stilllegen (best effort)
runOcc('app:disable parliamentwinterthur --no-interaction');

// Federated Requests (z.B. Circles' GroupMemberAdded-Listener) machen interne
// HTTP-Calls auf die eigene Cloud-URL. Die Standard-`overwrite.cli.url` zeigt
// auf den externen Host:Port (z.B. http://localhost:29824), der aus den
// Containern aus nicht erreichbar ist → RequestNetworkException / Timeouts bei
// Gruppenoperationen. Wir biegen die CLI-URL auf den internen nginx-Service,
// damit Loopback-Calls funktionieren. Die externe URL (overwriteprotocol/
// overwritehost) bleibt unverändert für Browser/Mail-Links.
runOcc('config:system:set overwrite.cli.url --value=http://nextcloud-nginx:8080');

$enableResult = runOcc('app:enable parlwin --no-interaction');
if (
    $enableResult['code'] !== 0
    && stripos($enableResult['output'], 'already enabled') === false
) {
    fwrite(STDERR, "parlwin-app-init: Aktivieren von parlwin fehlgeschlagen (exit={$enableResult['code']}).\n");
    if ($enableResult['output'] !== '') {
        fwrite(STDERR, $enableResult['output'] . "\n");
    }
    exit($enableResult['code']);
}

exit(0);
