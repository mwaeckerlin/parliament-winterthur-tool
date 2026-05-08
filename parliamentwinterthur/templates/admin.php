<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
?>
<div class="pw-admin-settings">
    <h2><?php p($l->t('Parliament Winterthur – Einstellungen')); ?></h2>

    <form id="pw-settings-form">
        <div class="pw-settings-section">
            <h3><?php p($l->t('Fraktionskonfiguration')); ?></h3>

            <p class="settings-hint">
                <?php p($l->t('Konfigurieren Sie hier, für welche Fraktion das Tool betrieben wird.')); ?>
            </p>

            <div class="pw-form-row">
                <label for="pw-fraktion">
                    <?php p($l->t('Fraktion (Name exakt wie auf der Parlamentswebseite)')); ?>
                </label>
                <input type="text" id="pw-fraktion" name="fraktion"
                       value="<?php p($_['fraktion']); ?>"
                       placeholder="z.B. SP/Grüne" class="pw-input" />
            </div>

            <div class="pw-form-row">
                <label for="pw-nextcloud-gruppe">
                    <?php p($l->t('Nextcloud-Gruppe (wird automatisch erstellt/aktualisiert)')); ?>
                </label>
                <input type="text" id="pw-nextcloud-gruppe" name="nextcloud_gruppe"
                       value="<?php p($_['nextcloud_gruppe']); ?>"
                       placeholder="z.B. Fraktion-SP-Grüne" class="pw-input" />
            </div>
        </div>

        <div class="pw-settings-section">
            <h3><?php p($l->t('Kalenderintegration')); ?></h3>

            <div class="pw-form-row">
                <label for="pw-kalender-nutzer">
                    <?php p($l->t('Nextcloud-Benutzername für Kalender')); ?>
                </label>
                <input type="text" id="pw-kalender-nutzer" name="kalender_nutzer"
                       value="<?php p($_['kalender_nutzer']); ?>"
                       placeholder="z.B. admin" class="pw-input" />
            </div>
        </div>

        <div class="pw-settings-section">
            <h3><?php p($l->t('E-Mail-Einladungen')); ?></h3>

            <div class="pw-form-row">
                <label for="pw-absender-email">
                    <?php p($l->t('Absender-E-Mail')); ?>
                </label>
                <input type="email" id="pw-absender-email" name="absender_email"
                       value="<?php p($_['absender_email']); ?>"
                       placeholder="noreply@example.com" class="pw-input" />
            </div>

            <div class="pw-form-row">
                <label for="pw-absender-name">
                    <?php p($l->t('Absendername')); ?>
                </label>
                <input type="text" id="pw-absender-name" name="absender_name"
                       value="<?php p($_['absender_name']); ?>"
                       class="pw-input" />
            </div>
        </div>

        <div class="pw-settings-actions">
            <button type="submit" class="button primary pw-btn-save">
                <?php p($l->t('Speichern')); ?>
            </button>

            <?php if (!empty($_['letzte_synchronisation'])): ?>
                <p class="pw-last-sync">
                    <?php p($l->t('Letzte Synchronisation: %s', [$_['letzte_synchronisation']])); ?>
                </p>
            <?php endif; ?>

            <button type="button" id="pw-btn-sync" class="button">
                <?php p($l->t('Jetzt synchronisieren')); ?>
            </button>
            <span id="pw-sync-status" class="pw-sync-status"></span>
        </div>
    </form>
</div>

<script nonce="<?php p(\OC::$server->getContentSecurityPolicyNonceManager()->getNonce()); ?>">
(function() {
    const form = document.getElementById('pw-settings-form');
    const btnSync = document.getElementById('pw-btn-sync');
    const syncStatus = document.getElementById('pw-sync-status');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(form);
        const data = {};
        formData.forEach((v, k) => data[k] = v);

        fetch(OC.generateUrl('/apps/parliamentwinterthur/settings'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': OC.requestToken,
            },
            body: JSON.stringify(data),
        })
        .then(r => r.json())
        .then(() => {
            OC.Notification.showTemporary(t('parliamentwinterthur', 'Einstellungen gespeichert'));
        })
        .catch(err => {
            OC.Notification.showTemporary(t('parliamentwinterthur', 'Fehler beim Speichern'));
            console.error(err);
        });
    });

    btnSync.addEventListener('click', function() {
        syncStatus.textContent = t('parliamentwinterthur', 'Synchronisiere...');
        btnSync.disabled = true;

        fetch(OC.generateUrl('/apps/parliamentwinterthur/sync'), {
            method: 'POST',
            headers: { 'requesttoken': OC.requestToken },
        })
        .then(r => r.json())
        .then(data => {
            if (data.erfolg) {
                syncStatus.textContent = t('parliamentwinterthur', 'Synchronisation abgeschlossen: {time}', {time: data.zeitpunkt});
            } else {
                syncStatus.textContent = t('parliamentwinterthur', 'Fehler: {msg}', {msg: data.fehler});
            }
        })
        .catch(err => {
            syncStatus.textContent = t('parliamentwinterthur', 'Verbindungsfehler');
            console.error(err);
        })
        .finally(() => {
            btnSync.disabled = false;
        });
    });
})();
</script>
