<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
?>
<div id="parlwin-root"></div>
<script nonce="<?php p(\OC::$server->getContentSecurityPolicyNonceManager()->getNonce()); ?>">
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