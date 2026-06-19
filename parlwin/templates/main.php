<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
?>
<div id="parlwin-root"></div>
<?php
// CSP-Nonce für das Inline-Script. Nextcloud 34 hat den Getter
// \OC::$server->getContentSecurityPolicyNonceManager() entfernt; die Klasse
// selbst existiert weiter und ist über den DI-Container erreichbar (funktioniert
// in NC 33 und 34).
$parlwinNonce = \OCP\Server::get(\OC\Security\CSP\ContentSecurityPolicyNonceManager::class)->getNonce();
?>
<script nonce="<?php p($parlwinNonce); ?>">
    window.PARLWIN_CONFIG = <?php
        $kuerzelRaw = (string) ($_['status_kuerzel'] ?? '[]');
        $kuerzelArr = json_decode($kuerzelRaw, true);
        print_unescaped(json_encode([
            'realtimeWsUrl' => (string) ($_['realtime_ws_url'] ?? ''),
            'webroot' => rtrim((string) \OC::$WEBROOT, '/'),
            'nextcloudGruppe' => (string) ($_['nextcloud_gruppe'] ?? ''),
            'version' => (string) ($_['version'] ?? ''),
            'statusKuerzel' => is_array($kuerzelArr) ? $kuerzelArr : [],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>;
</script>