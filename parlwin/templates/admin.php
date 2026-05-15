<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */

$fraktionOptionen = is_array($_['fraktion_optionen'] ?? null) ? $_['fraktion_optionen'] : [];
$gruppenOptionen = is_array($_['nextcloud_gruppen_optionen'] ?? null) ? $_['nextcloud_gruppen_optionen'] : [];
$kalenderAktiv = is_array($_['kalender_nutzer_optionen_aktiv'] ?? null) ? $_['kalender_nutzer_optionen_aktiv'] : [];
$kalenderInaktiv = is_array($_['kalender_nutzer_optionen_inaktiv'] ?? null) ? $_['kalender_nutzer_optionen_inaktiv'] : [];
$fraktionAktuell = (string) ($_['fraktion'] ?? '');
$fraktionAktuellInOptionen = in_array($fraktionAktuell, $fraktionOptionen, true);
?>
<div class="section" id="parlwin-admin-settings">
    <div class="pw-admin-shell">
        <aside class="pw-admin-sidebar">
            <section class="pw-admin-sidecard">
                <div class="pw-sync-actions">
                    <button type="button" id="pw-btn-sync" class="button">
                        <?php p($l->t('Jetzt synchronisieren')); ?>
                    </button>
                    <button type="button" id="pw-btn-sync-cancel" class="button" disabled>
                        <?php p($l->t('Synchronisierung abbrechen')); ?>
                    </button>
                    <span id="pw-sync-status" class="pw-sync-status"></span>
                </div>
                <div class="pw-sync-progress-row">
                    <progress id="pw-sync-progress" max="100" value="0"></progress>
                    <span id="pw-sync-percent" class="settings-hint">0%</span>
                </div>
                <p id="pw-sync-details" class="settings-hint"></p>
                <div class="pw-admin-statusline">
                    <span id="pw-admin-autosave"
                        class="pw-admin-autosave"><?php p($l->t('Bereit für automatische Speicherung')); ?></span>
                </div>
                <?php if (!empty($_['letzte_synchronisation'])): ?>
                    <p class="settings-hint">
                        <?php p($l->t('Letzte Synchronisation: %s', [$_['letzte_synchronisation']])); ?>
                    </p>
                <?php endif; ?>
            </section>
        </aside>

        <div class="pw-admin-main">
            <form id="pw-settings-form" class="pw-admin-form">
                <section class="pw-admin-card">
                    <h3><?php p($l->t('Fraktionskonfiguration')); ?></h3>
                    <p class="settings-hint">
                        <?php p($l->t('Wählen Sie Fraktion und Zielgruppe für die interne Fraktionsarbeit.')); ?>
                    </p>

                    <div class="pw-admin-grid">
                        <div class="pw-admin-field">
                            <label for="pw-fraktion">
                                <?php p($l->t('Fraktion (aus synchronisierten Fraktionen)')); ?>
                            </label>
                            <select id="pw-fraktion" name="fraktion" class="pw-select pw-admin-select" <?php if (count($fraktionOptionen) === 0): ?>disabled<?php endif; ?>>
                                <option value="">
                                    <?php p($l->t('Bitte Fraktion wählen')); ?>
                                </option>
                                <?php foreach ($fraktionOptionen as $option): ?>
                                    <option value="<?php p($option); ?>" <?php if ((string) ($_['fraktion'] ?? '') === (string) $option): ?>selected<?php endif; ?>>
                                        <?php p($option); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($fraktionAktuell !== '' && !$fraktionAktuellInOptionen): ?>
                                    <option value="<?php p($fraktionAktuell); ?>" selected>
                                        <?php p($fraktionAktuell . ' [nicht mehr synchronisiert]'); ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <?php if (count($fraktionOptionen) === 0): ?>
                                <small class="settings-hint">
                                    <?php p($l->t('Noch keine Fraktionen in der Datenbank. Bitte zuerst synchronisieren.')); ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="pw-admin-field">
                            <label for="pw-nextcloud-gruppe">
                                <?php p($l->t('Nextcloud-Gruppe (bestehend wählen oder neu erstellen)')); ?>
                            </label>
                            <input type="text" id="pw-nextcloud-gruppe" name="nextcloud_gruppe" class="pw-input"
                                list="pw-nextcloud-gruppen" value="<?php p($_['nextcloud_gruppe']); ?>"
                                placeholder="z.B. Fraktion-SP-Gruene" />
                            <datalist id="pw-nextcloud-gruppen">
                                <?php foreach ($gruppenOptionen as $gruppe): ?>
                                    <option value="<?php p($gruppe); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <div id="pw-nextcloud-gruppe-state" class="pw-selection-state"></div>
                        </div>
                    </div>
                </section>

                <section class="pw-admin-card">
                    <h3><?php p($l->t('Fraktionsmitglieder ↔ Nextcloud-User')); ?></h3>
                    <p class="settings-hint">
                        <?php p($l->t('Nach Fraktionswahl können Mitglieder auf lokale User gemappt und ausgewählt angelegt werden.')); ?>
                    </p>

                    <div class="pw-mitglied-toolbar">
                        <button type="button" id="pw-btn-members-provision" class="button">
                            <?php p($l->t('Ausgewählte anlegen')); ?>
                        </button>
                        <span id="pw-members-status" class="pw-sync-status"></span>
                    </div>

                    <div id="pw-members-empty" class="settings-hint">
                        <?php p($l->t('Bitte zuerst eine Fraktion wählen.')); ?>
                    </div>

                    <div class="pw-members-table-wrap">
                        <table class="pw-members-table" id="pw-members-table" aria-live="polite">
                            <thead>
                                <tr>
                                    <th class="pw-col-select">
                                        <label class="pw-members-select-all-label">
                                            <input type="checkbox" id="pw-members-select-all"
                                                aria-label="<?php p($l->t('Alle wählen')); ?>" />
                                        </label>
                                    </th>
                                    <th><?php p($l->t('Mitglied')); ?></th>
                                    <th><?php p($l->t('E-Mail')); ?></th>
                                    <th><?php p($l->t('Username')); ?></th>
                                    <th><?php p($l->t('Gruppen')); ?></th>
                                </tr>
                            </thead>
                            <tbody id="pw-members-body"></tbody>
                        </table>
                    </div>
                </section>

                <section class="pw-admin-card">
                    <h3><?php p($l->t('Kalenderintegration')); ?></h3>

                    <div class="pw-admin-grid">
                        <div class="pw-admin-field">
                            <label for="pw-kalender-nutzer">
                                <?php p($l->t('Nextcloud-Benutzer für Termineinträge')); ?>
                            </label>
                            <input type="text" id="pw-kalender-nutzer" name="kalender_nutzer" class="pw-input"
                                list="pw-kalender-nutzer-liste" value="<?php p($_['kalender_nutzer']); ?>"
                                placeholder="z.B. admin" />
                            <datalist id="pw-kalender-nutzer-liste">
                                <?php foreach ($kalenderAktiv as $user): ?>
                                    <option value="<?php p((string) ($user['uid'] ?? '')); ?>"
                                        label="<?php p((string) ($user['label'] ?? '')); ?>"></option>
                                <?php endforeach; ?>
                                <?php foreach ($kalenderInaktiv as $user): ?>
                                    <option value="<?php p((string) ($user['uid'] ?? '')); ?>"
                                        label="<?php p((string) ($user['label'] ?? '')); ?> [inaktiv]"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <div id="pw-kalender-nutzer-state" class="pw-selection-state"></div>
                        </div>
                    </div>
                </section>

                <section class="pw-admin-card">
                    <h3><?php p($l->t('E-Mail-Einladungen')); ?></h3>

                    <div class="pw-admin-grid">
                        <div class="pw-admin-field">
                            <label for="pw-absender-email">
                                <?php p($l->t('Absender-E-Mail')); ?>
                            </label>
                            <input type="email" id="pw-absender-email" name="absender_email" class="pw-input"
                                value="<?php p($_['absender_email']); ?>" placeholder="noreply@example.com" />
                        </div>
                        <div class="pw-admin-field">
                            <label for="pw-absender-name">
                                <?php p($l->t('Absendername')); ?>
                            </label>
                            <input type="text" id="pw-absender-name" name="absender_name" class="pw-input"
                                value="<?php p($_['absender_name']); ?>" />
                        </div>
                    </div>
                </section>
            </form>
        </div>
    </div>
</div>

<script nonce="<?php p(\OC::$server->getContentSecurityPolicyNonceManager()->getNonce()); ?>">
    window.PARLWIN_ADMIN_CONFIG = <?php print_unescaped(json_encode([
        'realtimeWsUrl' => (string) ($_['realtime_ws_url'] ?? ''),
        'webroot' => rtrim((string) \OC::$WEBROOT, '/'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>;
    window.PARLWIN_ADMIN_BOOTSTRAP = <?php print_unescaped(json_encode([
        'nextcloudGruppen' => $gruppenOptionen,
        'kalenderNutzerAktiv' => $kalenderAktiv,
        'kalenderNutzerInaktiv' => $kalenderInaktiv,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>;

    (function () {
        const form = document.getElementById('pw-settings-form');
        const btnSync = document.getElementById('pw-btn-sync');
        const btnSyncCancel = document.getElementById('pw-btn-sync-cancel');
        const syncStatus = document.getElementById('pw-sync-status');
        const syncDetails = document.getElementById('pw-sync-details');
        const syncProgress = document.getElementById('pw-sync-progress');
        const syncPercent = document.getElementById('pw-sync-percent');
        const inputGruppe = document.getElementById('pw-nextcloud-gruppe');
        const inputKalenderNutzer = document.getElementById('pw-kalender-nutzer');
        const selectFraktion = document.getElementById('pw-fraktion');
        const autosaveStatus = document.getElementById('pw-admin-autosave');
        const gruppeState = document.getElementById('pw-nextcloud-gruppe-state');
        const kalenderState = document.getElementById('pw-kalender-nutzer-state');
        const membersBody = document.getElementById('pw-members-body');
        const membersEmpty = document.getElementById('pw-members-empty');
        const membersStatus = document.getElementById('pw-members-status');
        const membersSelectAll = document.getElementById('pw-members-select-all');
        const btnMembersProvision = document.getElementById('pw-btn-members-provision');
        let pollTimer = null;
        let syncRequestInFlight = false;
        let realtimeSocket = null;
        let realtimeReconnectTimer = null;
        let realtimeReconnectDelayMs = 1000;
        let realtimeConnected = false;
        let fraktionsMitglieder = [];
        let settingsSaveTimer = null;
        let memberSaveTimer = null;
        let lastSavedSettingsPayload = '';

        // Fallback fuer die globale Uebersetzungsfunktion `t`. In Nextcloud 33 ist
        // sie in manchen Page-Kontexten nicht mehr als globale Variable im IIFE-Scope
        // verfuegbar (sondern nur als ES-Modul-Export `@nextcloud/l10n`). Ohne diesen
        // Fallback wuerde der erste t()-Aufruf einen ReferenceError werfen und den
        // gesamten Init-Block (inkl. pollSyncStatus / connectRealtime) abbrechen.
        const t = (typeof window !== 'undefined' && typeof window.t === 'function')
            ? window.t
            : ((app, text, vars) => {
                let result = String(text);
                if (typeof window !== 'undefined' && window.OC?.L10N && typeof window.OC.L10N.translate === 'function') {
                    try {
                        return window.OC.L10N.translate(app, text, vars);
                    } catch (_e) {
                        // Fallback unten benutzen.
                    }
                }
                if (vars && typeof vars === 'object') {
                    Object.keys(vars).forEach((key) => {
                        result = result.split('{' + key + '}').join(String(vars[key]));
                    });
                }
                return result;
            });
        const n = (typeof window !== 'undefined' && typeof window.n === 'function')
            ? window.n
            : ((app, singular, plural, count, vars) => {
                const text = count === 1 ? singular : plural;
                return t(app, text, Object.assign({ count: count }, vars || {}));
            });

        // Fallback fuer das globale `OC`-Objekt. In NC33 ist es in vielen
        // Page-Kontexten nicht mehr global verfuegbar.
        const ncOC = (typeof window !== 'undefined' && window.OC) ? window.OC : null;
        const generateUrl = (path) => {
            if (ncOC && typeof ncOC.generateUrl === 'function') {
                try { return ncOC.generateUrl(path); } catch (_e) { /* fall through */ }
            }
            const cleaned = String(path || '').replace(/^\/+/, '');
            return '/index.php/' + cleaned;
        };
        const notifyTemporary = (message) => {
            if (ncOC && ncOC.Notification && typeof ncOC.Notification.showTemporary === 'function') {
                try { ncOC.Notification.showTemporary(message); return; } catch (_e) { /* fall through */ }
            }
            console.warn('[parlwin]', message);
        };

        const bootstrap = window.PARLWIN_ADMIN_BOOTSTRAP || {};
        const knownGroups = new Set((bootstrap.nextcloudGruppen || []).map((name) => String(name).toLowerCase().trim()).filter(Boolean));
        const activeCalendarUsers = new Map((bootstrap.kalenderNutzerAktiv || []).map((user) => [String(user.uid || '').toLowerCase(), user]));
        const inactiveCalendarUsers = new Map((bootstrap.kalenderNutzerInaktiv || []).map((user) => [String(user.uid || '').toLowerCase(), user]));

        const resolveRequestToken = () => {
            if (ncOC?.Util && typeof ncOC.Util.getRequestToken === 'function') {
                const utilToken = String(ncOC.Util.getRequestToken() || '').trim();
                if (utilToken !== '') {
                    return utilToken;
                }
            }
            const direct = typeof ncOC?.requestToken === 'string' ? ncOC.requestToken.trim() : '';
            if (direct !== '') {
                return direct;
            }
            const legacy = typeof window.oc_requesttoken === 'string' ? window.oc_requesttoken.trim() : '';
            if (legacy !== '') {
                return legacy;
            }
            const headToken = document.head ? String(document.head.getAttribute('data-requesttoken') || '').trim() : '';
            if (headToken !== '') {
                return headToken;
            }
            const meta = document.querySelector('meta[name="requesttoken"]');
            const metaToken = meta ? String(meta.getAttribute('content') || '').trim() : '';
            if (metaToken !== '') {
                return metaToken;
            }
            const input = document.querySelector('input[name="requesttoken"]');
            const inputToken = input ? String(input.value || '').trim() : '';
            if (inputToken !== '') {
                return inputToken;
            }
            return '';
        };

        const authHeaders = (baseHeaders = {}, includeToken = true) => {
            const headers = { ...baseHeaders };
            headers['Accept'] = headers['Accept'] || 'application/json';
            headers['OCS-APIRequest'] = 'true';
            if (includeToken) {
                const token = resolveRequestToken();
                if (token !== '') {
                    headers.requesttoken = token;
                    headers.Requesttoken = token;
                    headers['X-Requesttoken'] = token;
                }
            }
            return headers;
        };

        const parseJsonSafe = (body) => {
            try {
                return JSON.parse(body);
            } catch (_error) {
                return null;
            }
        };

        const extractHttpErrorMessage = (response, body, parsed) => {
            const status = Number(response?.status || 0);
            const statusText = String(response?.statusText || '').trim();

            const direct =
                (parsed && typeof parsed === 'object' && (parsed.fehler || parsed.error || parsed.message))
                    ? String(parsed.fehler || parsed.error || parsed.message)
                    : '';
            if (direct.trim() !== '') {
                return direct.trim();
            }
            if (parsed?.ocs?.meta?.message) {
                return String(parsed.ocs.meta.message);
            }

            const lowerBody = String(body || '').toLowerCase();
            if (status === 412) {
                if (lowerBody.includes('csrf') || lowerBody.includes('requesttoken')) {
                    return t('parlwin', 'Sicherheitsprüfung fehlgeschlagen (Token ungültig/abgelaufen). Seite neu laden und erneut versuchen.');
                }
                return t('parlwin', 'Sicherheitsprüfung fehlgeschlagen (HTTP 412). Seite neu laden und erneut versuchen.');
            }
            if (status === 403) {
                return t('parlwin', 'Zugriff verweigert. Bitte als Admin anmelden und erneut versuchen.');
            }
            if (status === 401) {
                return t('parlwin', 'Nicht angemeldet. Bitte neu anmelden und erneut versuchen.');
            }
            if (status === 404) {
                return t('parlwin', 'Synchronisations-Endpunkt nicht gefunden. Bitte App-Installation prüfen.');
            }
            if (status >= 500) {
                return t('parlwin', 'Serverfehler beim Synchronisieren. Details im Nextcloud-Log prüfen.');
            }

            return `${status} ${statusText}`.trim() || t('parlwin', 'Unbekannter Fehler');
        };

        const computeGlobalProgress = (status) => {
            const sections = status && typeof status === 'object' ? (status.sections || {}) : {};
            let processed = 0;
            let total = 0;
            Object.values(sections).forEach((section) => {
                if (!section || typeof section !== 'object') {
                    return;
                }
                if (Object.prototype.hasOwnProperty.call(section, 'aktiv') && section.aktiv === false) {
                    return;
                }
                const sProcessed = Number(section.processed || 0);
                const sTotal = Number(section.total || 0);
                if (sTotal <= 0) {
                    return;
                }
                processed += Math.min(sProcessed, sTotal);
                total += sTotal;
            });
            const percent = total > 0 ? Math.max(0, Math.min(100, Math.round((processed / total) * 100))) : 0;
            return { processed, total, percent };
        };

        const setSelectionState = (target, type, text) => {
            if (!target) {
                return;
            }
            target.className = `pw-selection-state ${type}`.trim();
            target.textContent = text;
        };

        const setAutosaveStatus = (text) => {
            if (autosaveStatus) {
                autosaveStatus.textContent = text;
            }
        };

        const updateGroupState = () => {
            const value = String(inputGruppe?.value || '').trim();
            if (!value) {
                setSelectionState(gruppeState, '', '');
                return;
            }
            const isExisting = knownGroups.has(value.toLowerCase());
            if (isExisting) {
                setSelectionState(gruppeState, 'existing', t('parlwin', 'Bestehende Gruppe'));
                return;
            }
            setSelectionState(gruppeState, 'new', t('parlwin', 'Neue Gruppe wird angelegt'));
        };

        const updateKalenderState = () => {
            const value = String(inputKalenderNutzer?.value || '').trim().toLowerCase();
            if (!value) {
                setSelectionState(kalenderState, '', '');
                return;
            }
            if (activeCalendarUsers.has(value)) {
                setSelectionState(kalenderState, 'existing', t('parlwin', 'Aktiver Benutzer'));
                return;
            }
            if (inactiveCalendarUsers.has(value)) {
                setSelectionState(kalenderState, 'inactive', t('parlwin', 'Inaktiver Benutzer'));
                return;
            }
            setSelectionState(kalenderState, 'invalid', t('parlwin', 'Benutzer nicht gefunden'));
        };

        const aktuelleFraktionsGruppe = () => {
            return inputGruppe ? String(inputGruppe.value || '').trim() : '';
        };

        const vereinigeGruppen = (...listen) => {
            const seen = new Set();
            const ergebnis = [];
            listen.forEach((liste) => {
                if (!Array.isArray(liste)) {
                    return;
                }
                liste.forEach((eintrag) => {
                    const name = String(eintrag || '').trim();
                    if (name === '' || seen.has(name)) {
                        return;
                    }
                    seen.add(name);
                    ergebnis.push(name);
                });
            });
            return ergebnis;
        };

        const aktualisiereGruppenZelle = (tdLocal, mitglied) => {
            if (!tdLocal) {
                return;
            }
            const exists = mitglied && mitglied.lokalerUserExistiert === true;
            const groups = mitglied && Array.isArray(mitglied.lokaleGruppen)
                ? mitglied.lokaleGruppen.filter(Boolean)
                : [];
            const username = String((mitglied && mitglied.username) || '').trim();
            tdLocal.classList.add('pw-member-groups');
            if (exists) {
                tdLocal.textContent = vereinigeGruppen([aktuelleFraktionsGruppe()], groups).join(', ');
                tdLocal.className = 'pw-member-groups pw-member-local-exists';
            } else if (username === '') {
                tdLocal.textContent = t('parlwin', 'Bitte Username setzen');
                tdLocal.className = 'pw-member-groups pw-member-local-missing';
            } else {
                tdLocal.textContent = aktuelleFraktionsGruppe();
                tdLocal.className = 'pw-member-groups pw-member-local-pending';
            }
        };

        const aktualisiereAlleGruppenZellen = () => {
            if (!membersBody) {
                return;
            }
            membersBody.querySelectorAll('tr').forEach((row) => {
                const memberId = Number(row.dataset.memberId || 0);
                if (!(memberId > 0)) {
                    return;
                }
                const eintrag = fraktionsMitglieder.find((m) => Number(m.id) === memberId);
                if (!eintrag) {
                    return;
                }
                const tdLocal = row.querySelector('.pw-member-groups');
                aktualisiereGruppenZelle(tdLocal, eintrag);
            });
        };

        const renderMemberRows = () => {
            if (!membersBody || !membersEmpty) {
                return;
            }
            membersBody.innerHTML = '';

            if (!selectFraktion || !String(selectFraktion.value || '').trim()) {
                membersEmpty.textContent = t('parlwin', 'Bitte zuerst eine Fraktion wählen.');
                return;
            }

            if (!Array.isArray(fraktionsMitglieder) || fraktionsMitglieder.length === 0) {
                membersEmpty.textContent = t('parlwin', 'Keine aktiven Mitglieder für diese Fraktion gefunden.');
                if (membersSelectAll) {
                    membersSelectAll.checked = false;
                }
                return;
            }

            membersEmpty.textContent = '';
            if (membersSelectAll) {
                membersSelectAll.checked = true;
            }
            fraktionsMitglieder.forEach((mitglied) => {
                const tr = document.createElement('tr');
                tr.dataset.memberId = String(mitglied.id);

                const tdSelect = document.createElement('td');
                tdSelect.dataset.label = t('parlwin', 'Auswahl');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'pw-member-select';
                checkbox.checked = true;
                tdSelect.appendChild(checkbox);

                const tdName = document.createElement('td');
                tdName.dataset.label = t('parlwin', 'Mitglied');
                tdName.textContent = String(mitglied.displayName || '').trim();

                const tdEmail = document.createElement('td');
                tdEmail.dataset.label = t('parlwin', 'E-Mail');
                tdEmail.textContent = String(mitglied.email || '').trim();

                const tdUser = document.createElement('td');
                tdUser.dataset.label = t('parlwin', 'Username');
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'pw-input pw-member-username';
                input.value = String(mitglied.username || '');
                input.placeholder = String(mitglied.vorschlagUsername || '');
                input.dataset.memberId = String(mitglied.id);
                input.addEventListener('input', () => {
                    const eintrag = fraktionsMitglieder.find((m) => Number(m.id) === Number(mitglied.id));
                    if (eintrag) {
                        eintrag.username = String(input.value || '').trim();
                        eintrag.lokalerUserExistiert = false;
                        eintrag.lokaleGruppen = [];
                    }
                    aktualisiereGruppenZelle(tdLocal, eintrag || mitglied);
                    queueMemberMappingSave();
                });
                tdUser.appendChild(input);

                const tdLocal = document.createElement('td');
                tdLocal.classList.add('pw-member-groups');
                tdLocal.dataset.label = t('parlwin', 'Gruppen');
                aktualisiereGruppenZelle(tdLocal, mitglied);

                tr.appendChild(tdSelect);
                tr.appendChild(tdName);
                tr.appendChild(tdEmail);
                tr.appendChild(tdUser);
                tr.appendChild(tdLocal);
                membersBody.appendChild(tr);
            });
        };

        const collectMemberMappings = () => {
            if (!membersBody) {
                return [];
            }
            return Array.from(membersBody.querySelectorAll('tr'))
                .map((row) => {
                    const memberId = Number(row.dataset.memberId || 0);
                    const input = row.querySelector('.pw-member-username');
                    const username = input ? String(input.value || '').trim() : '';
                    return memberId > 0 ? { mitgliedId: memberId, username } : null;
                })
                .filter(Boolean);
        };

        const collectSelectedMemberIds = () => {
            if (!membersBody) {
                return [];
            }
            return Array.from(membersBody.querySelectorAll('tr'))
                .filter((row) => {
                    const box = row.querySelector('.pw-member-select');
                    return box && box.checked;
                })
                .map((row) => Number(row.dataset.memberId || 0))
                .filter((id) => id > 0);
        };

        const loadFraktionMembers = (explicitFraktion) => {
            const fraktion = String(
                explicitFraktion !== undefined && explicitFraktion !== null
                    ? explicitFraktion
                    : (selectFraktion?.value || '')
            ).trim();
            if (selectFraktion && fraktion && selectFraktion.value !== fraktion) {
                selectFraktion.value = fraktion;
            }
            if (!fraktion) {
                fraktionsMitglieder = [];
                renderMemberRows();
                return Promise.resolve();
            }

            if (membersStatus) {
                membersStatus.textContent = t('parlwin', 'Lade Mitglieder...');
            }
            return fetch(`${generateUrl('/apps/parlwin/settings/fraktion-mitglieder')}?fraktion=${encodeURIComponent(fraktion)}`, {
                method: 'GET',
                headers: authHeaders(),
            })
                .then(async (r) => {
                    const json = await r.json();
                    if (!r.ok) {
                        throw new Error(json?.fehler || `${r.status} ${r.statusText}`);
                    }
                    return json;
                })
                .then((payload) => {
                    fraktionsMitglieder = Array.isArray(payload?.mitglieder) ? payload.mitglieder : [];
                    renderMemberRows();
                    if (membersStatus) {
                        const count = Number(payload?.summary?.anzahl || 0);
                        membersStatus.textContent = t('parlwin', '{count} Mitglieder geladen', { count });
                    }
                })
                .catch((err) => {
                    fraktionsMitglieder = [];
                    renderMemberRows();
                    if (membersStatus) {
                        membersStatus.textContent = t('parlwin', 'Fehler: {msg}', { msg: err?.message || 'unbekannt' });
                    }
                });
        };

        const saveMemberMappings = () => {
            const fraktion = String(selectFraktion?.value || '').trim();
            if (!fraktion) {
                if (membersStatus) {
                    membersStatus.textContent = t('parlwin', 'Bitte zuerst eine Fraktion wählen.');
                }
                return;
            }
            const mappings = collectMemberMappings();
            if (membersStatus) {
                membersStatus.textContent = t('parlwin', 'Speichere Zuordnungen...');
            }
            fetch(generateUrl('/apps/parlwin/settings/fraktion-mitglieder/mappings'), {
                method: 'POST',
                headers: authHeaders({
                    'Content-Type': 'application/json',
                }),
                body: JSON.stringify({
                    fraktion,
                    mappings,
                }),
            })
                .then(async (r) => {
                    const json = await r.json();
                    if (!r.ok) {
                        throw new Error(json?.fehler || `${r.status} ${r.statusText}`);
                    }
                    return json;
                })
                .then((payload) => {
                    fraktionsMitglieder = Array.isArray(payload?.mitglieder) ? payload.mitglieder : [];
                    renderMemberRows();
                    if (membersStatus) {
                        membersStatus.textContent = t('parlwin', '{count} Zuordnungen gespeichert', {
                            count: Number(payload?.aktualisiert || 0),
                        });
                    }
                })
                .catch((err) => {
                    if (membersStatus) {
                        membersStatus.textContent = t('parlwin', 'Fehler: {msg}', { msg: err?.message || 'unbekannt' });
                    }
                });
        };

        const queueMemberMappingSave = () => {
            if (memberSaveTimer !== null) {
                window.clearTimeout(memberSaveTimer);
            }
            memberSaveTimer = window.setTimeout(() => {
                memberSaveTimer = null;
                saveMemberMappings();
            }, 500);
        };

        const collectSettingsFormData = () => {
            const formData = new FormData(form);
            const data = {};
            formData.forEach((value, key) => {
                data[key] = String(value || '').trim();
            });
            return data;
        };

        const persistSettings = () => {
            const data = collectSettingsFormData();
            const serialized = JSON.stringify(data);
            if (serialized === lastSavedSettingsPayload) {
                setAutosaveStatus(t('parlwin', 'Alle Änderungen gespeichert'));
                return Promise.resolve();
            }

            setAutosaveStatus(t('parlwin', 'Speichere automatisch...'));
            return fetch(generateUrl('/apps/parlwin/settings'), {
                method: 'POST',
                headers: authHeaders({
                    'Content-Type': 'application/json',
                }),
                body: serialized,
            })
                .then(async (r) => {
                    const body = await r.text();
                    const parsed = parseJsonSafe(body);
                    if (!r.ok) {
                        throw new Error(extractHttpErrorMessage(r, body, parsed));
                    }
                    return parsed;
                })
                .then(() => {
                    lastSavedSettingsPayload = serialized;
                    updateGroupState();
                    updateKalenderState();
                    setAutosaveStatus(t('parlwin', 'Alle Änderungen gespeichert'));
                })
                .catch((err) => {
                    setAutosaveStatus(t('parlwin', 'Auto-Save fehlgeschlagen'));
                    notifyTemporary(t('parlwin', 'Fehler beim Speichern: {msg}', { msg: err?.message || 'unbekannt' }));
                    console.error(err);
                });
        };

        const queueSettingsSave = (immediate = false) => {
            if (settingsSaveTimer !== null) {
                window.clearTimeout(settingsSaveTimer);
                settingsSaveTimer = null;
            }
            if (immediate) {
                return persistSettings();
            }
            setAutosaveStatus(t('parlwin', 'Änderungen erkannt...'));
            settingsSaveTimer = window.setTimeout(() => {
                settingsSaveTimer = null;
                persistSettings();
            }, 500);
            return Promise.resolve();
        };

        const provisionSelectedMembers = () => {
            const fraktion = String(selectFraktion?.value || '').trim();
            if (!fraktion) {
                if (membersStatus) {
                    membersStatus.textContent = t('parlwin', 'Bitte zuerst eine Fraktion wählen.');
                }
                return;
            }
            const nextcloudGruppe = String(inputGruppe?.value || '').trim();
            if (!nextcloudGruppe) {
                if (membersStatus) {
                    membersStatus.textContent = t('parlwin', 'Bitte eine Nextcloud-Gruppe setzen.');
                }
                return;
            }
            const mitglied_ids = collectSelectedMemberIds();
            if (mitglied_ids.length === 0) {
                if (membersStatus) {
                    membersStatus.textContent = t('parlwin', 'Bitte mindestens ein Mitglied auswählen.');
                }
                return;
            }
            const mappings = collectMemberMappings();

            if (membersStatus) {
                membersStatus.textContent = t('parlwin', 'Lege Benutzer an...');
            }
            queueSettingsSave(true).catch(() => { });
            fetch(generateUrl('/apps/parlwin/settings/fraktion-mitglieder/anlegen'), {
                method: 'POST',
                headers: authHeaders({
                    'Content-Type': 'application/json',
                }),
                body: JSON.stringify({
                    fraktion,
                    nextcloud_gruppe: nextcloudGruppe,
                    mitglied_ids,
                    mappings,
                }),
            })
                .then(async (r) => {
                    const json = await r.json();
                    if (!r.ok) {
                        throw new Error(json?.fehler || `${r.status} ${r.statusText}`);
                    }
                    return json;
                })
                .then((payload) => {
                    fraktionsMitglieder = Array.isArray(payload?.mitglieder) ? payload.mitglieder : [];
                    renderMemberRows();
                    const provision = payload?.provision || {};
                    const warnungen = Array.isArray(provision?.warnungen) ? provision.warnungen : [];
                    if (membersStatus) {
                        let msg = t('parlwin', 'Angelegt: {newCount}, Gruppe ergänzt: {groupCount}', {
                            newCount: Number(provision?.angelegt || 0),
                            groupCount: Number(provision?.zurGruppeHinzugefuegt || 0),
                        });
                        if (warnungen.length > 0) {
                            msg += ' — ' + warnungen.join('; ');
                        } else if (
                            Number(provision?.ausgewaehlt || 0) > 0
                            && Number(provision?.angelegt || 0) === 0
                            && Number(provision?.zurGruppeHinzugefuegt || 0) === 0
                            && Number(provision?.bereitsVorhanden || 0) === 0
                        ) {
                            msg += ' — ' + t('parlwin', 'Keines der ausgewählten Mitglieder gehört zur gewählten Fraktion.');
                        }
                        membersStatus.textContent = msg;
                    }
                })
                .catch((err) => {
                    if (membersStatus) {
                        membersStatus.textContent = t('parlwin', 'Fehler: {msg}', { msg: err?.message || 'unbekannt' });
                    }
                });
        };

        const defaultWsUrl = () => {
            // Same-origin WebSocket via the /ws/<appid>/ reverse-proxy
            // convention shipped by mwaeckerlin/nextcloud:nginx.
            const scheme = window.location.protocol === 'https:' ? 'wss' : 'ws';
            const host = window.location.host || 'localhost';
            const webroot = String(window.PARLWIN_ADMIN_CONFIG?.webroot || '').replace(/\/$/, '');
            return `${scheme}://${host}${webroot}/ws/parlwin/`;
        };

        const resolveWsUrl = () => {
            const configured = window.PARLWIN_ADMIN_CONFIG?.realtimeWsUrl;
            if (typeof configured === 'string' && configured.trim() !== '') {
                return configured.trim();
            }
            return defaultWsUrl();
        };

        const stopPolling = () => {
            if (pollTimer !== null) {
                window.clearInterval(pollTimer);
                pollTimer = null;
            }
        };

        const startPolling = () => {
            if (pollTimer !== null) {
                return;
            }
            pollTimer = window.setInterval(() => {
                if (realtimeConnected) {
                    return;
                }
                pollSyncStatus().catch(() => {
                    // Fallback bleibt aktiv, bis WS wieder verbunden ist.
                });
            }, 1200);
        };

        const clearReconnectTimer = () => {
            if (realtimeReconnectTimer !== null) {
                window.clearTimeout(realtimeReconnectTimer);
                realtimeReconnectTimer = null;
            }
        };

        const scheduleRealtimeReconnect = () => {
            if (realtimeReconnectTimer !== null) {
                return;
            }
            realtimeReconnectTimer = window.setTimeout(() => {
                realtimeReconnectTimer = null;
                connectRealtime();
            }, realtimeReconnectDelayMs);
            realtimeReconnectDelayMs = Math.min(realtimeReconnectDelayMs * 2, 15000);
        };

        const renderProgress = (status) => {
            if (!status || typeof status !== 'object') {
                return;
            }
            const current = status.current || {};
            const label = current.label || '';
            const db = current.db || '-';
            const processed = Number(current.processed || 0);
            const total = Number(current.total || 0);
            const elapsed = status.elapsed || '00:00:00';
            const eta = status.eta || '--:--:--';
            const running = status.running === true;
            const cancelRequested = status.phase === 'abbruch_angefragt';
            const source = status.source || 'unbekannt';
            const global = computeGlobalProgress(status);
            syncProgress.value = global.percent;
            syncPercent.textContent = `${global.percent}%`;

            if (running) {
                syncStatus.textContent = cancelRequested
                    ? t('parlwin', 'Abbruch angefragt...')
                    : t('parlwin', 'Synchronisiere...');
                syncDetails.textContent = `${label} (${db}) - ${processed}/${total} | Gesamt ${global.processed}/${global.total} (${global.percent}%) | Quelle ${source} | ETA ${eta} | Laufzeit ${elapsed}`;
                btnSync.disabled = true;
                btnSyncCancel.disabled = cancelRequested;
                return;
            }

            btnSync.disabled = false;
            btnSyncCancel.disabled = true;
            if (status.phase === 'abgebrochen') {
                syncStatus.textContent = t('parlwin', 'Synchronisation abgebrochen');
                syncDetails.textContent = `Laufzeit: ${elapsed}`;
                return;
            }
            if (status.error) {
                syncStatus.textContent = t('parlwin', 'Fehler: {msg}', { msg: status.error });
                syncDetails.textContent = status.error;
                return;
            }

            if (status.phase === 'abgeschlossen') {
                const zeit = status.finishedAt || status.updatedAt || '';
                syncStatus.textContent = t('parlwin', 'Synchronisation abgeschlossen: {time}', { time: zeit });
                syncProgress.value = 100;
                syncPercent.textContent = '100%';
                syncDetails.textContent = `Laufzeit: ${elapsed}`;
                return;
            }

            if (status.phase === 'idle') {
                syncProgress.value = 0;
                syncPercent.textContent = '0%';
                syncDetails.textContent = '';
            }
        };

        const pollSyncStatus = () => {
            return fetch(generateUrl('/apps/parlwin/sync/status'), {
                method: 'GET',
                headers: authHeaders(),
            })
                .then(async (r) => {
                    const body = await r.text();
                    if (!r.ok) {
                        throw new Error(extractHttpErrorMessage(r, body, parseJsonSafe(body)));
                    }
                    return parseJsonSafe(body) || {};
                })
                .then((status) => {
                    renderProgress(status);
                    return status;
                });
        };

        const handleRealtimeMessage = (rawEvent) => {
            let message = null;
            try {
                message = JSON.parse(rawEvent.data);
            } catch (error) {
                return;
            }
            const type = message?.type || '';
            const payload = message?.payload || {};

            if (type === 'sync.progress' && payload && typeof payload === 'object') {
                renderProgress(payload);
                return;
            }
            if (type === 'sync.completed' || type === 'sync.failed' || type === 'sync.cancelled' || type === 'sync.cancel.requested') {
                pollSyncStatus().catch(() => { });
            }
        };

        const connectRealtime = () => {
            clearReconnectTimer();
            const wsUrl = resolveWsUrl();
            if (!wsUrl) {
                startPolling();
                return;
            }

            try {
                realtimeSocket = new WebSocket(wsUrl);
            } catch (_error) {
                realtimeConnected = false;
                startPolling();
                scheduleRealtimeReconnect();
                return;
            }

            realtimeSocket.addEventListener('open', () => {
                realtimeConnected = true;
                realtimeReconnectDelayMs = 1000;
                stopPolling();
                pollSyncStatus().catch(() => { });
            });
            realtimeSocket.addEventListener('message', handleRealtimeMessage);
            realtimeSocket.addEventListener('close', () => {
                realtimeConnected = false;
                startPolling();
                scheduleRealtimeReconnect();
            });
            realtimeSocket.addEventListener('error', () => {
                realtimeConnected = false;
                startPolling();
                scheduleRealtimeReconnect();
            });
        };

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            queueSettingsSave(true);
        });

        form.addEventListener('input', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            if (target.closest('.pw-members-table-wrap')) {
                return;
            }
            queueSettingsSave(false);
        });

        form.addEventListener('change', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            if (target.closest('.pw-members-table-wrap')) {
                return;
            }
            queueSettingsSave(true);
        });

        btnSync.addEventListener('click', function () {
            if (syncRequestInFlight) {
                return;
            }
            syncRequestInFlight = true;
            btnSync.disabled = true;
            syncStatus.textContent = t('parlwin', 'Synchronisation wird gestartet...');
            syncDetails.textContent = '';
            syncProgress.value = 0;
            syncPercent.textContent = '0%';
            btnSyncCancel.disabled = false;
            startPolling();
            fetch(generateUrl('/apps/parlwin/sync'), {
                method: 'POST',
                headers: authHeaders(),
            })
                .then(async (r) => {
                    const body = await r.text();
                    const data = parseJsonSafe(body);

                    if (!r.ok) {
                        throw new Error(extractHttpErrorMessage(r, body, data));
                    }
                    if (!data) {
                        throw new Error('Ungültige Serverantwort');
                    }
                    return data;
                })
                .then(data => {
                    if (!data.erfolg) {
                        syncStatus.textContent = t('parlwin', 'Fehler: {msg}', { msg: data.fehler });
                        btnSync.disabled = false;
                        btnSyncCancel.disabled = true;
                        return;
                    }

                    if (data.bereits_laufend) {
                        syncStatus.textContent = t('parlwin', 'Synchronisation läuft bereits');
                        pollSyncStatus().catch(() => { });
                        return;
                    }

                    if (data.asynchron) {
                        syncStatus.textContent = t('parlwin', 'Synchronisation gestartet');
                        pollSyncStatus().catch(() => { });
                        return;
                    }

                    syncStatus.textContent = t('parlwin', 'Synchronisation abgeschlossen: {time}', { time: data.zeitpunkt });
                    btnSync.disabled = false;
                    btnSyncCancel.disabled = true;
                })
                .catch(err => {
                    pollSyncStatus()
                        .then((status) => {
                            if (status && status.running === true) {
                                syncStatus.textContent = t('parlwin', 'Synchronisation läuft bereits');
                                btnSync.disabled = true;
                                btnSyncCancel.disabled = status.phase === 'abbruch_angefragt';
                                return;
                            }
                            const msg = (err && err.message) ? err.message : t('parlwin', 'Verbindungsfehler');
                            syncStatus.textContent = t('parlwin', 'Fehler: {msg}', { msg });
                            syncDetails.textContent = '';
                            btnSync.disabled = false;
                            btnSyncCancel.disabled = true;
                            console.error(err);
                        })
                        .catch(() => {
                            const msg = (err && err.message) ? err.message : t('parlwin', 'Verbindungsfehler');
                            syncStatus.textContent = t('parlwin', 'Fehler: {msg}', { msg });
                            syncDetails.textContent = '';
                            btnSync.disabled = false;
                            btnSyncCancel.disabled = true;
                            console.error(err);
                        });
                })
                .finally(() => {
                    syncRequestInFlight = false;
                });
        });

        btnSyncCancel.addEventListener('click', function () {
            if (btnSyncCancel.disabled) {
                return;
            }
            btnSyncCancel.disabled = true;
            syncStatus.textContent = t('parlwin', 'Abbruch wird angefordert...');

            fetch(generateUrl('/apps/parlwin/sync/cancel'), {
                method: 'POST',
                headers: authHeaders(),
            })
                .then(async (r) => {
                    const body = await r.text();
                    const data = parseJsonSafe(body);
                    if (!r.ok) {
                        throw new Error(extractHttpErrorMessage(r, body, data));
                    }
                    return data || {};
                })
                .then(() => {
                    syncStatus.textContent = t('parlwin', 'Abbruch angefragt...');
                    pollSyncStatus().catch(() => { });
                })
                .catch((err) => {
                    syncStatus.textContent = t('parlwin', 'Fehler: {msg}', {
                        msg: (err && err.message) ? err.message : t('parlwin', 'Verbindungsfehler'),
                    });
                    console.error(err);
                });
        });

        inputGruppe?.addEventListener('input', updateGroupState);
        inputGruppe?.addEventListener('input', aktualisiereAlleGruppenZellen);
        inputGruppe?.addEventListener('change', aktualisiereAlleGruppenZellen);
        inputKalenderNutzer?.addEventListener('input', updateKalenderState);
        selectFraktion?.addEventListener('change', () => {
            queueSettingsSave(true).catch(() => { });
            loadFraktionMembers().catch(() => { });
        });
        membersSelectAll?.addEventListener('change', () => {
            const checked = membersSelectAll.checked;
            document.querySelectorAll('.pw-member-select').forEach((checkbox) => {
                checkbox.checked = checked;
            });
        });
        btnMembersProvision?.addEventListener('click', provisionSelectedMembers);

        updateGroupState();
        updateKalenderState();
        const initialFraktion = <?php print_unescaped(json_encode((string) ($_['fraktion'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)); ?>;
        if (selectFraktion && initialFraktion && selectFraktion.value !== initialFraktion) {
            selectFraktion.value = initialFraktion;
        }
        renderMemberRows();
        lastSavedSettingsPayload = JSON.stringify(collectSettingsFormData());
        loadFraktionMembers(initialFraktion).catch(() => { });
        connectRealtime();
        startPolling();
        pollSyncStatus().catch(() => { });
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                pollSyncStatus().catch(() => { });
            }
        });
        window.addEventListener('focus', () => {
            pollSyncStatus().catch(() => { });
        });

        window.addEventListener('beforeunload', () => {
            clearReconnectTimer();
            stopPolling();
            if (realtimeSocket) {
                realtimeSocket.close();
                realtimeSocket = null;
            }
        });
    })();
</script>