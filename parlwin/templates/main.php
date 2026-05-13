<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
?>
<div id="parlwin-root"></div>
<script nonce="<?php p(\OC::$server->getContentSecurityPolicyNonceManager()->getNonce()); ?>">
window.PARLWIN_CONFIG = <?php print_unescaped(json_encode([
    'realtimeWsUrl' => (string) ($_['realtime_ws_url'] ?? ''),
    'realtimePort' => (int) ($_['realtime_ws_port'] ?? 29825),
    'realtimePath' => (string) ($_['realtime_ws_path'] ?? '/ws'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>;
</script>
