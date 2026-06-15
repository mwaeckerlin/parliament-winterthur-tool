<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField
      v-model="suche"
      label="Suche"
      placeholder="Nr. oder Titel"
      trailing-button-icon="close"
      :show-trailing-button="!!suche"
      @trailing-button-click="suche = ''"
    />
  </Teleport>
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <div class="pw-filter-body">
      <NcCheckboxRadioSwitch v-model="nurKuenftige" type="switch">
        Nur zukünftige Sitzungen
      </NcCheckboxRadioSwitch>
    </div>
  </Teleport>

  <section class="pw-view-content pw-sitzungen">
    <header class="pw-view-header">
      <h2 class="pw-view-title">Sitzungen</h2>
      <span class="pw-view-count">{{ gefilterteSitzungen.length }}</span>
      <NcActions :aria-label="'Neue Sitzung aus Vorlage erstellen'" type="primary" class="pw-neue-sitzung-btn">
        <template #icon>
          <span style="font-size:1.2em;line-height:1">+</span>
        </template>
        <NcActionButton v-if="sitzungstypen.length === 0" :disabled="true">Kein Sitzungstyp definiert</NcActionButton>
        <NcActionButton
          v-for="typ in sitzungstypen"
          :key="typ.id"
          @click="waehleTypFuerNeueSitzung(typ)"
        >
          {{ typ.name }}
        </NcActionButton>
      </NcActions>
    </header>

  <!-- Neue Sitzung: Vollständiges Formular (1:1 NC-Kalender-Layout) -->
  <Teleport to="body">
    <div v-if="gewaehlterTyp" class="pw-neue-sitzung-overlay" @click.self="gewaehlterTyp = null">
      <div class="pw-neue-sitzung-form pw-neue-sitzung-form-gross">
        <!-- Titel – prominent oben -->
        <input
          v-model="neueSitzungTitel"
          type="text"
          class="pw-sitzung-titel-input"
          placeholder="Titel eingeben …"
          autofocus
        />
        <hr class="pw-form-divider" />

        <!-- Datum -->
        <div class="pw-form-zeile">
          <span class="pw-form-ikon" aria-hidden="true">📅</span>
          <input v-model="neueSitzungDatum" type="date" class="pw-form-feld pw-form-feld-flex" :min="heuteDatum" />
        </div>

        <!-- Von / Bis -->
        <div class="pw-form-zeile">
          <span class="pw-form-ikon" aria-hidden="true">🕐</span>
          <div class="pw-form-von-bis">
            <PwField label="Von">
              <input v-model="neueSitzungZeitVon" type="time" class="pw-form-feld pw-form-feld-zeit" />
            </PwField>
            <PwField label="Bis">
              <input v-model="neueSitzungZeitBis" type="time" class="pw-form-feld pw-form-feld-zeit" />
            </PwField>
          </div>
        </div>

        <!-- Ort -->
        <div class="pw-form-zeile">
          <span class="pw-form-ikon" aria-hidden="true">📍</span>
          <input v-model="neueSitzungOrt" type="text" class="pw-form-feld pw-form-feld-flex" placeholder="Ort" />
        </div>

        <!-- Zweck / Beschreibung -->
        <div class="pw-form-zeile pw-form-zeile-oben">
          <span class="pw-form-ikon" aria-hidden="true">≡</span>
          <textarea
            v-model="neueSitzungBemerkungen"
            class="pw-form-textarea"
            placeholder="Zweck / Beschreibung …"
            rows="3"
          />
        </div>

        <hr class="pw-form-divider" />

        <!-- Traktanden -->
        <div class="pw-form-zeile pw-form-zeile-oben">
          <span class="pw-form-ikon" aria-hidden="true">☰</span>
          <div class="pw-form-traktanden">
            <div class="pw-form-traktanden-kopf">Traktanden</div>
            <div
              v-for="(t, idx) in neueSitzungTraktanden"
              :key="idx"
              class="pw-form-traktandum"
              :class="{ 'pw-drag-over': dragOverIdx === idx }"
              @dragover.prevent="dragOverIdx = idx"
              @dragleave="dragOverIdx = null"
              @drop.prevent="dragDrop(idx, 'neueSitzungTraktanden')"
              @dragend="dragOverIdx = null"
            >
              <span
                class="pw-form-drag-handle"
                aria-hidden="true"
                draggable="true"
                @dragstart="dragStart(idx)"
              >⠿</span>
              <span class="pw-form-traktandum-nr">{{ idx + 1 }}.</span>
              <input v-model="t.titel" type="text" class="pw-form-feld pw-form-feld-flex" placeholder="Titel" />
              <input v-model="t.beschreibung" type="text" class="pw-form-feld pw-form-feld-flex" placeholder="Beschreibung (optional)" />
              <button
                type="button"
                class="pw-form-del-btn"
                :aria-label="'Traktandum ' + (idx + 1) + ' löschen'"
                @click="neueSitzungTraktanden.splice(idx, 1)"
              >✕</button>
            </div>
            <NcButton type="tertiary" @click="neueSitzungTraktanden.push({ titel: '', beschreibung: '' })">
              + Traktandum hinzufügen
            </NcButton>
          </div>
        </div>

        <!-- Teilnehmer (editierbar) -->
        <div class="pw-form-zeile pw-form-zeile-oben">
          <span class="pw-form-ikon" aria-hidden="true">👥</span>
          <div class="pw-form-teilnehmer">
            <div class="pw-form-traktanden-kopf">Teilnehmer</div>
            <div v-for="(p, idx) in neueSitzungTeilnehmer" :key="idx" class="pw-form-teilnehmer-zeile">
              <select v-model="p.art" class="pw-form-feld" @change="onArtChange(p)">
                <option value="mitglied">Einzelnes Mitglied</option>
                <option value="fraktion">Ganze Fraktion</option>
                <option value="eigeneFraktion">Eigene Fraktion</option>
                <option value="kommission">Ganze Kommission</option>
                <option value="rolle">Fraktions-Rolle</option>
                <option value="ncGruppe">Nextcloud-Gruppe</option>
                <option value="ncUser">Nextcloud-Benutzer</option>
              </select>
              <select v-if="p.art === 'mitglied'" v-model.number="p.referenzId" class="pw-form-feld pw-form-feld-flex">
                <option :value="0">— Mitglied wählen —</option>
                <option v-for="m in aktiveMitglieder" :key="m.id" :value="m.id">{{ (m.vorname || '') + ' ' + (m.name || '') }}</option>
              </select>
              <select v-else-if="p.art === 'fraktion'" v-model="p.referenzName" class="pw-form-feld pw-form-feld-flex">
                <option value="">— Fraktion wählen —</option>
                <option v-for="f in aktiveFraktionen" :key="f.kuerzel || f.name" :value="f.name">{{ f.name }}</option>
              </select>
              <span v-else-if="p.art === 'eigeneFraktion'" class="pw-form-teilnehmer-hinweis">→ {{ konfigurierteGruppe || '(keine Gruppe konfiguriert)' }}</span>
              <select v-else-if="p.art === 'kommission'" v-model.number="p.referenzId" class="pw-form-feld pw-form-feld-flex">
                <option :value="0">— Kommission wählen —</option>
                <option v-for="k in aktiveKommissionen" :key="k.id" :value="k.id">{{ k.name }}</option>
              </select>
              <select v-else-if="p.art === 'ncGruppe'" v-model="p.referenzName" class="pw-form-feld pw-form-feld-flex">
                <option value="">{{ ncGruppenLaden ? '— Lade … —' : '— Nextcloud-Gruppe —' }}</option>
                <option v-for="g in ncGruppen" :key="g.gid" :value="g.gid">{{ g.displayName || g.gid }}</option>
              </select>
              <select v-else-if="p.art === 'ncUser'" v-model="p.referenzName" class="pw-form-feld pw-form-feld-flex">
                <option value="">{{ ncUserLaden ? '— Lade … —' : '— Nextcloud-Benutzer —' }}</option>
                <option v-for="u in ncUser" :key="u.uid" :value="u.uid">{{ u.displayName || u.uid }} ({{ u.uid }})</option>
              </select>
              <select v-else-if="p.art === 'rolle'" v-model="p.referenzName" class="pw-form-feld pw-form-feld-flex">
                <option value="">— Fraktions-Rolle —</option>
                <option v-for="r in verfuegbareRollen" :key="r.code" :value="r.code">{{ r.bezeichnung }}</option>
              </select>
              <input v-else v-model="p.referenzName" placeholder="Bezeichnung" class="pw-form-feld pw-form-feld-flex" />
              <button type="button" class="pw-form-del-btn" @click="neueSitzungTeilnehmer.splice(idx, 1)">✕</button>
            </div>
            <NcButton type="tertiary" @click="neueSitzungTeilnehmer.push({ art: 'eigeneFraktion', referenzId: 0, referenzName: '' })">
              + Teilnehmer hinzufügen
            </NcButton>
          </div>
        </div>

        <div v-if="neuerSitzungFehler" class="pw-neue-sitzung-fehler">{{ neuerSitzungFehler }}</div>

        <div class="pw-neue-sitzung-aktionen">
          <NcButton
            type="primary"
            :disabled="!neueSitzungDatum || neuerSitzungLaden"
            @click="erstelleNeueSession"
          >
            {{ neuerSitzungLaden ? 'Erstellt …' : 'Erstellen' }}
          </NcButton>
          <NcButton @click="gewaehlterTyp = null">Abbrechen</NcButton>
        </div>
      </div>
    </div>
  </Teleport>
    <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>

    <div v-else>
      <div
        v-for="sitzung in gefilterteSitzungen"
        :key="sitzung.id"
        :id="'pw-sitzung-' + sitzung.id"
        class="pw-sitzung-karte"
        :class="{ 'pw-vergangen': istVergangen(sitzung.datum) }"
      >
        <div class="pw-sitzung-kopf" @click="toggleSitzung(sitzung.id)">
          <div class="pw-sitzung-datum">
            <strong>{{ formatieredatum(sitzung.datum) }}</strong>
            <span v-if="sitzung.zeitVon">{{ sitzung.zeitVon }}{{ sitzung.zeitBis ? ' – ' + sitzung.zeitBis : '' }}</span>
          </div>
          <div class="pw-sitzung-titel">
            <span v-if="sitzung.typId > 0" class="pw-badge-intern">intern</span>
            {{ sitzung.titel }}
          </div>
          <p v-if="sitzung.typId > 0 && sitzung.bemerkungen" class="pw-sitzung-zweck">{{ sitzung.bemerkungen }}</p>
          <div class="pw-sitzung-ort">{{ sitzung.ort }}</div>
          <a v-if="sitzung.url" :href="sitzung.url" target="_blank" @click.stop class="pw-extern-link">Extern</a>
          <span class="pw-toggle">{{ offeneSitzungen.includes(sitzung.id) ? '▲' : '▼' }}</span>
        </div>

        <!-- Aufklappbarer Bereich mit Traktanden -->
        <div v-if="offeneSitzungen.includes(sitzung.id)" class="pw-sitzung-details">
          <!-- Notizen zur Sitzung (ersetzt frühere „Bemerkungen zur Sitzung“). -->
          <div class="pw-sitzung-notizen">
            <h4>Notizen zur Sitzung</h4>
            <NotizenListe
              :model-value="sitzungNotizen[sitzung.id] || []"
              placeholder="Notiz zur Sitzung hinzufügen…"
              @update:model-value="speichereSitzungNotizen(sitzung, $event)"
            />
          </div>

          <!-- Traktanden -->
          <div class="pw-traktanden">
            <h4>Traktanden</h4>
            <div v-if="ladenTraktanden[sitzung.id]" class="pw-laden">Traktanden laden...</div>
            <template v-else>
              <!-- Interne Sitzung: vereinfachte Traktanden-Ansicht -->
              <template v-if="sitzung.typId > 0">
                <table class="pw-tabelle pw-tabelle-intern" lang="de">
                  <thead>
                    <tr>
                      <th class="pw-col-nr">Tr.</th>
                      <th class="pw-col-titel">Titel</th>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="t in gefilterteTraktanden(sitzung.id)" :key="t.id">
                      <tr>
                        <td class="pw-col-nr"><strong>{{ t.nummer }}</strong></td>
                        <td class="pw-col-titel">
                          <div class="pw-intern-traktandum-titel">{{ t.titel }}</div>
                          <div v-if="t.beschreibung" class="pw-intern-traktandum-beschreibung">{{ t.beschreibung }}</div>
                        </td>
                      </tr>
                      <tr class="pw-traktandum-notizen-zeile" @click.stop>
                        <td></td>
                        <td>
                          <NotizenListe
                            :model-value="parseTraktandumNotizen(t.id)"
                            placeholder="Notiz zum Traktandum hinzufügen…"
                            @update:model-value="speichereTraktandumNotizen(t, $event)"
                          />
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
                <p v-if="gefilterteTraktanden(sitzung.id).length === 0">
                  Keine Traktanden vorhanden.
                </p>
              </template>

              <!-- Parlamentssitzung: vollständige Traktanden-Ansicht mit Geschäften -->
              <template v-else>
                <div class="pw-table-wrap pw-table-desktop">
                  <table class="pw-tabelle pw-tabelle-geschaefte pw-tabelle-traktanden" lang="de">
                    <thead>
                      <tr>
                        <th class="pw-col-nr">Tr.</th>
                        <th class="pw-col-nr">Nr.</th>
                        <th class="pw-col-titel">Titel</th>
                        <th class="pw-col-zustaendig">Zuständig</th>
                        <th class="pw-col-beschluss">Beschluss</th>
                      </tr>
                    </thead>
                    <tbody>
                      <template v-for="t in gefilterteTraktanden(sitzung.id)" :key="t.id">
                        <tr
                          :class="['pw-table-row-clickable', { 'pw-geloescht': t.geschaeft?.geloescht }]"
                          tabindex="0"
                          role="button"
                          :aria-label="`Traktandum ${t.nummer} öffnen`"
                          @click="oeffneGeschaeft(t, sitzung)"
                          @keydown.enter.prevent="oeffneGeschaeft(t, sitzung)"
                          @keydown.space.prevent="oeffneGeschaeft(t, sitzung)"
                        >
                          <td data-label="Tr." class="pw-col-nr"><strong>{{ t.nummer }}</strong></td>
                          <td data-label="Nr." class="pw-col-nr">
                            <strong>{{ t.geschaeft?.nummer || '' }}</strong>
                            <span class="pw-col-nr-datum">{{ formatieredatumKurz(t.geschaeft?.datum) }}</span>
                            <span class="pw-col-nr-typ">{{ t.geschaeft?.typ || '' }}</span>
                          </td>
                          <td class="pw-titel pw-col-titel" data-label="Titel">
                            <a
                              v-if="t.geschaeft?.url"
                              :href="t.geschaeft.url"
                              target="_blank"
                              @click.stop
                              class="pw-inline-link"
                              title="Extern öffnen"
                            >↗</a>
                            <a
                              v-else-if="t.url"
                              :href="t.url"
                              target="_blank"
                              @click.stop
                              class="pw-inline-link"
                              title="Dokument öffnen"
                            >↗</a>
                            <a
                              v-else-if="sitzung.url"
                              :href="sitzung.url"
                              target="_blank"
                              @click.stop
                              class="pw-inline-link"
                              title="Originaltraktandum extern öffnen (kein verknüpftes Geschäft)"
                            >↗</a>
                            {{ t.geschaeft?.titel || t.titel }}
                          </td>
                          <td v-if="t.geschaeft" data-label="Zuständig" class="pw-col-inline-edit pw-col-zustaendig" @click.stop>
                            <PwMultiSelect
                              class="pw-inline-select"
                              :model-value="zustaendigOptionenFuer(t.geschaeft)"
                              :options="zustaendigeOptionenFuerSelect"
                              :clearable="true"
                              placeholder="—"
                              label="label"
                              @update:model-value="aenderungZustaendig(t.geschaeft, sitzung.id, $event || [])"
                            />
                          </td>
                          <td v-else data-label="Zuständig" class="pw-col-zustaendig">—</td>
                          <td v-if="t.geschaeft" data-label="Beschluss" class="pw-col-inline-edit pw-col-beschluss" @click.stop>
                            <NcSelect
                              class="pw-inline-select"
                              :model-value="beschlussOptionFuer(t.geschaeft)"
                              :options="beschlussOptionenFuer(t.geschaeft)"
                              :clearable="true"
                              placeholder="—"
                              label="label"
                              @update:model-value="aenderungBeschluss(t.geschaeft, sitzung.id, $event)"
                            />
                          </td>
                          <td v-else data-label="Beschluss" class="pw-col-beschluss">—</td>
                        </tr>
                        <tr class="pw-traktandum-notizen-zeile" @click.stop>
                          <td></td>
                          <td colspan="4">
                            <NotizenListe
                              :model-value="parseTraktandumNotizen(t.id)"
                              placeholder="Notiz zum Traktandum hinzufügen…"
                              @update:model-value="speichereTraktandumNotizen(t, $event)"
                            />
                          </td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                </div>
                <!-- Karten-Darstellung (bei schmaler Ansicht, via Container-Query) -->
                <div class="pw-card-mobile">
                  <div
                    v-for="t in gefilterteTraktanden(sitzung.id)"
                    :key="`karte-${t.id}`"
                    class="pw-traktandum-karte"
                    :class="{ 'pw-geloescht': t.geschaeft?.geloescht }"
                  >
                    <div
                      class="pw-traktandum-karte-kopf"
                      role="button"
                      tabindex="0"
                      :aria-label="`Traktandum ${t.nummer} öffnen`"
                      @click="oeffneGeschaeft(t, sitzung)"
                      @keydown.enter.prevent="oeffneGeschaeft(t, sitzung)"
                      @keydown.space.prevent="oeffneGeschaeft(t, sitzung)"
                    >
                      <div class="pw-traktandum-karte-kennung">
                        <strong>Tr.&nbsp;{{ t.nummer }}</strong>
                        <span v-if="t.geschaeft?.nummer">Nr.&nbsp;{{ t.geschaeft.nummer }}</span>
                      </div>
                      <span class="pw-traktandum-karte-titel">
                        <a
                          v-if="t.geschaeft?.url"
                          :href="t.geschaeft.url"
                          target="_blank"
                          @click.stop
                          class="pw-inline-link"
                          title="Extern öffnen"
                        >↗</a>
                        <a
                          v-else-if="t.url"
                          :href="t.url"
                          target="_blank"
                          @click.stop
                          class="pw-inline-link"
                          title="Dokument öffnen"
                        >↗</a>
                        <a
                          v-else-if="sitzung.url"
                          :href="sitzung.url"
                          target="_blank"
                          @click.stop
                          class="pw-inline-link"
                          title="Originaltraktandum extern öffnen"
                        >↗</a>
                        {{ t.geschaeft?.titel || t.titel }}
                      </span>
                      <div class="pw-traktandum-karte-meta">
                        <span
                          v-if="t.geschaeft?.status"
                          :class="['pw-status-' + statusKlasse(t.geschaeft.status), 'pw-status-text']"
                        >{{ t.geschaeft.status }}</span>
                        <span v-if="t.geschaeft?.typ">{{ t.geschaeft.typ }}</span>
                        <span v-if="t.geschaeft?.datum">{{ formatieredatum(t.geschaeft.datum) }}</span>
                      </div>
                    </div>
                    <div v-if="t.geschaeft" class="pw-traktandum-karte-selektoren" @click.stop>
                      <label>Zuständig</label>
                      <PwMultiSelect
                        class="pw-inline-select"
                        :model-value="zustaendigOptionenFuer(t.geschaeft)"
                        :options="zustaendigeOptionenFuerSelect"
                        :clearable="true"
                        placeholder="—"
                        label="label"
                        @update:model-value="aenderungZustaendig(t.geschaeft, sitzung.id, $event || [])"
                      />
                      <label>Beschluss</label>
                      <NcSelect
                        class="pw-inline-select"
                        :model-value="beschlussOptionFuer(t.geschaeft)"
                        :options="beschlussOptionenFuer(t.geschaeft)"
                        :clearable="true"
                        placeholder="—"
                        label="label"
                        @update:model-value="aenderungBeschluss(t.geschaeft, sitzung.id, $event)"
                      />
                    </div>
                    <div @click.stop>
                      <NotizenListe
                        :model-value="parseTraktandumNotizen(t.id)"
                        placeholder="Notiz zum Traktandum hinzufügen…"
                        @update:model-value="speichereTraktandumNotizen(t, $event)"
                      />
                    </div>
                  </div>
                </div>
                <p v-if="gefilterteTraktanden(sitzung.id).length === 0">
                  Keine Traktanden gefunden.
                </p>
              </template>
            </template>
          </div>
        </div>
      </div>
      <p v-if="gefilterteSitzungen.length === 0" class="pw-leer">Keine Sitzungen gefunden.</p>
    </div>
  </section>

  <Teleport to="body">
    <div v-if="ausgewaehlteGeschaeftId" class="pw-modal-overlay" @click.self="schliesseGeschaeft">
      <div class="pw-modal">
        <div class="pw-modal-kopf pw-modal-kopf-leer">
          <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click="schliesseGeschaeft">✕</button>
        </div>
        <GeschaeftDetail
          :geschaeft-id="ausgewaehlteGeschaeftId"
          :mitglieder="mitglieder"
          :traktandum-kontext="ausgewaehltesGeschaeftTraktandumKontext"
          @oeffne-traktandum="sprungZuSitzung"
        />
      </div>
    </div>
  </Teleport>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { vollerName, personKey, parseNotizen } from '../utils'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { subscribeRealtime } from '../realtime'
import GeschaeftDetail from './GeschaeftDetail.vue'
import NotizenListe from './NotizenListe.vue'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCaption from '@nextcloud/vue/components/NcActionCaption'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PwMultiSelect from './PwMultiSelect.vue'
import PwField from './PwField.vue'

export default {
  name: 'Sitzungsliste',
  components: { GeschaeftDetail, NotizenListe, NcActions, NcActionButton, NcActionCaption, NcButton, NcCheckboxRadioSwitch, NcLoadingIcon, NcSelect, NcTextField, PwMultiSelect, PwField },
  props: {
    mitglieder:   { type: Array, default: () => [] },
    fraktionen:   { type: Array, default: () => [] },
    kommissionen: { type: Array, default: () => [] },
  },
  data() {
    return {
      filterReady: false,
      sitzungen: [],
      laden: true,
      suche: '',
      nurKuenftige: true,
      offeneSitzungen: [],
      traktanden: {},
      ladenTraktanden: {},
      sitzungNotizen: {},
      traktandumNotizen: {},
      ausgewaehlteGeschaeftId: null,
      ausgewaehltesGeschaeftTraktandumKontext: null,
      unsubRealtime: null,
      // Neue Sitzung aus Vorlage
      sitzungstypen: [],
      gewaehlterTyp: null,
      neueSitzungDatum: '',
      neueSitzungTitel: '',
      neueSitzungOrt: '',
      neueSitzungZeitVon: '',
      neueSitzungZeitBis: '',
      neueSitzungBemerkungen: '',
      neueSitzungTraktanden: [],
      neueSitzungTeilnehmer: [],
      neuerSitzungLaden: false,
      neuerSitzungFehler: '',
      // Drag & Drop
      dragSrcIdx: null,
      dragOverIdx: null,
      // Teilnehmer-Dropdowns
      ncGruppen: [],
      ncUser: [],
      ncGruppenLaden: false,
      ncUserLaden: false,
      verfuegbareRollen: [
        { code: 'kommissionsmitglied', bezeichnung: 'Kommissionsmitglied' },
        { code: 'fraktionspraesident', bezeichnung: 'Fraktionspräsident' },
        { code: 'fraktionspraesident_stellvertretung', bezeichnung: 'Fraktionspräsident Stellvertretung' },
        { code: 'protokollfuehrer', bezeichnung: 'Protokollführer' },
        { code: 'protokollfuehrer_stellvertretung', bezeichnung: 'Protokollführer Stellvertretung' },
      ],
    }
  },
  computed: {
    heuteDatum() {
      return new Date().toISOString().slice(0, 10)
    },
    aktiveMitglieder() {
      return (this.mitglieder || []).filter(m => m.aktiv !== false)
    },
    aktiveFraktionen() {
      return (this.fraktionen || []).filter(f => f.aktiv !== false)
    },
    aktiveKommissionen() {
      return (this.kommissionen || []).filter(k => k.aktiv !== false && !k.geloescht)
    },
    konfigurierteGruppe() {
      return (typeof window !== 'undefined' && window.PARLWIN_CONFIG && window.PARLWIN_CONFIG.nextcloudGruppe) || ''
    },
    gefilterteSitzungen() {
      let liste = this.sitzungen
      if (this.nurKuenftige) {
        const heute = new Date().toISOString().slice(0, 10)
        liste = liste.filter(s => (s.datum || '') >= heute)
      }
      const s = (this.suche || '').trim().toLowerCase()
      if (s) {
        liste = liste.filter((sit) => {
          // Sitzung selbst (Titel/Ort) auch durchsuchen.
          const titel = (sit.titel || '').toLowerCase()
          const ort = (sit.ort || '').toLowerCase()
          if (titel.includes(s) || ort.includes(s)) return true
          // Wenn Traktanden noch nicht geladen sind: optimistisch anzeigen –
          // der Watcher unten lädt sie nach und reduziert die Liste dann weiter.
          const traks = this.traktanden[sit.id]
          if (!traks) return true
          return traks.some((t) => {
            const tt = (t.geschaeft?.titel || t.titel || '').toLowerCase()
            const tn = (t.geschaeft?.nummer || '').toLowerCase()
            return tt.includes(s) || tn.includes(s)
          })
        })
      }
      return liste
    },
    zustaendigeOptionenFuerSelect() {
      return this.mitglieder
        .filter((m) => m.aktiv !== false && !!(m.nextcloudUid || m.nextcloud_uid))
        .map((member) => ({
          label: this.vollerName(member),
          value: this.personKey(member),
          mitglied: member,
        }))
        .filter((o) => !!o.label)
        .sort((a, b) => a.label.localeCompare(b.label))
    },
  },
  watch: {
    suche(neu) {
      const term = (neu || '').trim()
      if (!term) return
      // Beim Tippen: Traktanden aller (gefilterten) Sitzungen nachladen, damit
      // die Suche tatsächlich greift. Trefferliste klappt sich automatisch auf.
      this.sitzungen.forEach((sit) => {
        if (this.nurKuenftige) {
          const heute = new Date().toISOString().slice(0, 10)
          if ((sit.datum || '') < heute) return
        }
        if (!this.traktanden[sit.id] && !this.ladenTraktanden[sit.id]) {
          this.ladeTraktandenFuerSitzung(sit.id)
        }
      })
    },
  },
  mounted() {
    this.$nextTick(() => { this.filterReady = true })
    this.ladeSitzungen()
    this.ladeSitzungstypen()
    this.unsubRealtime = subscribeRealtime(this.handleRealtimeEvent)
  },
  beforeUnmount() {
    if (this.unsubRealtime) {
      this.unsubRealtime()
      this.unsubRealtime = null
    }
  },
  methods: {
    async ladeSitzungstypen() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/sitzungstypen'))
        this.sitzungstypen = (data || []).filter(t => !t.geloescht)
      } catch (e) {
        console.error('Fehler beim Laden der Sitzungstypen:', e)
      }
    },
    waehleTypFuerNeueSitzung(typ) {
      this.gewaehlterTyp = typ
      this.neueSitzungDatum = this.heuteDatum
      this.neueSitzungTitel = typ.name || ''
      this.neueSitzungOrt = typ.standardOrt || ''
      this.neueSitzungZeitVon = typ.standardZeitVon || ''
      this.neueSitzungZeitBis = typ.standardZeitBis || ''
      this.neueSitzungBemerkungen = typ.zweck || ''
      this.neueSitzungTraktanden = (typ.traktanden || []).map(t => ({
        titel: t.titel || '',
        beschreibung: t.beschreibung || '',
      }))
      this.neueSitzungTeilnehmer = (typ.teilnehmer || []).map(p => ({
        art: p.art || 'eigeneFraktion',
        referenzId: p.referenzId || 0,
        referenzName: p.referenzName || '',
      }))
      this.neuerSitzungFehler = ''
    },
    async erstelleNeueSession() {
      if (!this.gewaehlterTyp || !this.neueSitzungDatum) return
      this.neuerSitzungLaden = true
      this.neuerSitzungFehler = ''
      try {
        const { data } = await axios.post(generateUrl('/apps/parlwin/sitzungen'), {
          typId:       this.gewaehlterTyp.id,
          datum:       this.neueSitzungDatum,
          titel:       this.neueSitzungTitel,
          ort:         this.neueSitzungOrt,
          zeitVon:     this.neueSitzungZeitVon,
          zeitBis:     this.neueSitzungZeitBis,
          bemerkungen: this.neueSitzungBemerkungen,
          traktanden:  this.neueSitzungTraktanden,
          teilnehmer:  this.neueSitzungTeilnehmer,
        })
        this.sitzungen.push(data)
        this.sitzungen.sort((a, b) => (a.datum || '').localeCompare(b.datum || ''))
        this.sitzungNotizen[data.id] = []
        this.offeneSitzungen.push(data.id)
        await this.ladeTraktandenFuerSitzung(data.id)
        this.gewaehlterTyp = null
      } catch (e) {
        const meldung = e?.response?.data?.fehler || e?.message || 'Unbekannter Fehler'
        this.neuerSitzungFehler = 'Fehler: ' + meldung
        showError('Sitzung konnte nicht erstellt werden: ' + meldung)
        console.error('Fehler beim Erstellen der Sitzung:', e)
      } finally {
        this.neuerSitzungLaden = false
      }
    },
    onArtChange(p) {
      p.referenzId = 0
      p.referenzName = ''
      if (p.art === 'ncGruppe' && !this.ncGruppen.length) this.ladeNcGruppen()
      if (p.art === 'ncUser' && !this.ncUser.length) this.ladeNcUser()
    },
    async ladeNcGruppen() {
      this.ncGruppenLaden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/sitzungstypen/nc/groups'))
        this.ncGruppen = Array.isArray(data) ? data : []
      } catch (e) {
        this.ncGruppen = []
      } finally {
        this.ncGruppenLaden = false
      }
    },
    async ladeNcUser() {
      this.ncUserLaden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/sitzungstypen/nc/users'), { params: { limit: 100 } })
        this.ncUser = Array.isArray(data) ? data : []
      } catch (e) {
        this.ncUser = []
      } finally {
        this.ncUserLaden = false
      }
    },
    teilnehmerLabel(p) {
      const artLabel = {
        eigeneFraktion: 'Eigene Fraktion',
        fraktion: 'Fraktion',
        kommission: 'Kommission',
        rolle: 'Rolle',
        mitglied: 'Mitglied',
        ncGruppe: 'NC-Gruppe',
        ncUser: 'NC-Benutzer',
      }
      const prefix = artLabel[p.art] || p.art
      return p.referenzName ? `${prefix}: ${p.referenzName}` : prefix
    },
    dragStart(idx) {
      this.dragSrcIdx = idx
    },
    dragDrop(targetIdx, liste) {
      if (this.dragSrcIdx === null || this.dragSrcIdx === targetIdx) return
      const arr = this[liste]
      const moved = arr.splice(this.dragSrcIdx, 1)[0]
      arr.splice(targetIdx, 0, moved)
      this.dragSrcIdx = null
      this.dragOverIdx = null
    },
    vollerName,
    personKey,
    async ladeSitzungen() {
      this.laden = true
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/sitzungen'), {
          params: { limit: 100 },
        })
        this.sitzungen = data
        data.forEach(s => {
          this.sitzungNotizen[s.id] = this.parseNotizen(s.notizen)
        })
      } catch (e) {
        console.error('Fehler beim Laden der Sitzungen:', e)
      } finally {
        this.laden = false
      }
    },
    async toggleSitzung(id) {
      if (this.offeneSitzungen.includes(id)) {
        this.offeneSitzungen = this.offeneSitzungen.filter(i => i !== id)
      } else {
        this.offeneSitzungen.push(id)
        await this.ladeTraktandenFuerSitzung(id)
      }
    },
    async ladeTraktandenFuerSitzung(sitzungId, force = false, silent = false) {
      if (this.traktanden[sitzungId] && !force) return
      if (!silent) this.ladenTraktanden = { ...this.ladenTraktanden, [sitzungId]: true }
      try {
        const { data } = await axios.get(
          generateUrl(`/apps/parlwin/sitzungen/${sitzungId}/traktanden`)
        )
        this.traktanden = { ...this.traktanden, [sitzungId]: data }
        const notizen = { ...this.traktandumNotizen }
        data.forEach(t => {
          notizen[t.id] = this.parseNotizen(t.notizen)
        })
        this.traktandumNotizen = notizen
        // Wenn aktive Suche Treffer ergibt, Sitzung automatisch aufklappen.
        const term = (this.suche || '').trim().toLowerCase()
        if (term && !this.offeneSitzungen.includes(sitzungId)) {
          const hit = data.some((t) => {
            const tt = (t.geschaeft?.titel || t.titel || '').toLowerCase()
            const tn = (t.geschaeft?.nummer || '').toLowerCase()
            return tt.includes(term) || tn.includes(term)
          })
          if (hit) this.offeneSitzungen.push(sitzungId)
        }
      } catch (e) {
        console.error('Fehler beim Laden der Traktanden:', e)
      } finally {
        this.ladenTraktanden = { ...this.ladenTraktanden, [sitzungId]: false }
      }
    },
    handleRealtimeEvent(event) {
      const type = event?.type || ''
      if (type === 'sync.completed' || type === 'sitzungen.updated') {
        this.ladeSitzungen()
      }
      if (type === 'traktanden.updated') {
        const sitzungId = Number(event?.payload?.sitzungId || 0)
        if (sitzungId > 0 && this.offeneSitzungen.includes(sitzungId)) {
          this.ladeTraktandenFuerSitzung(sitzungId, true)
        }
      }
      if (type === 'geschaefte.updated' || type === 'geschaefte.action') {
        // Geschäftsdaten geändert – betroffene offene Sitzungen neu laden.
        this.offeneSitzungen.forEach((sid) => this.ladeTraktandenFuerSitzung(sid, true))
      }
    },
    parseNotizen,
    parseTraktandumNotizen(tId) {
      return this.traktandumNotizen[tId] || []
    },
    gefilterteTraktanden(sitzungId) {
      const liste = this.traktanden[sitzungId] || []
      const s = (this.suche || '').trim().toLowerCase()
      if (!s) return liste
      return liste.filter((t) => {
        const titel = (t.geschaeft?.titel || t.titel || '').toLowerCase()
        const nummer = (t.geschaeft?.nummer || '').toLowerCase()
        return titel.includes(s) || nummer.includes(s)
      })
    },
    async speichereTraktandumNotizen(traktandum, notizen) {
      const tId = traktandum.id
      const liste = Array.isArray(notizen) ? notizen : []
      this.traktandumNotizen = { ...this.traktandumNotizen, [tId]: liste }
      const sitzungId = traktandum.sitzungId
      try {
        await axios.put(
          generateUrl(`/apps/parlwin/sitzungen/${sitzungId}/traktanden/${traktandum.id}`),
          { notizen: JSON.stringify(liste) }
        )
      } catch (e) {
        console.error('Fehler beim Speichern der Traktandum-Notizen:', e)
      }
    },
    async speichereSitzungNotizen(sitzung, notizen) {
      const liste = Array.isArray(notizen) ? notizen : []
      this.sitzungNotizen = { ...this.sitzungNotizen, [sitzung.id]: liste }
      try {
        await axios.put(
          generateUrl(`/apps/parlwin/sitzungen/${sitzung.id}`),
          { notizen: JSON.stringify(liste) }
        )
      } catch (e) {
        console.error('Fehler beim Speichern der Sitzungs-Notizen:', e)
      }
    },
    zustaendigOptionenFuer(geschaeft) {
      const zust = Array.isArray(geschaeft.zustaendigkeiten) ? geschaeft.zustaendigkeiten : []
      return zust.map((z) => {
        const treffer = this.zustaendigeOptionenFuerSelect.find((o) => o.value === z.personKey)
        return treffer || { label: z.personName || z.personKey, value: z.personKey, mitglied: null }
      })
    },
    beschlussOptionenFuer(geschaeft) {
      const erlaubt = Array.isArray(geschaeft.erlaubteBeschluesse) ? geschaeft.erlaubteBeschluesse : []
      return erlaubt.map((b) => ({ label: b.label || b.code, value: b.code }))
    },
    beschlussOptionFuer(geschaeft) {
      const code = geschaeft.letzterBeschluss?.aktionCode || ''
      if (!code) return null
      const optionen = this.beschlussOptionenFuer(geschaeft)
      return optionen.find((o) => o.value === code) || { label: geschaeft.letzterBeschluss?.titel || code, value: code }
    },
    async aenderungZustaendig(geschaeft, sitzungId, optionen) {
      const optList = Array.isArray(optionen) ? optionen : (optionen ? [optionen] : [])
      const keys = optList.map((o) => o.value).filter(Boolean)
      const vorhandeneHaupt = (geschaeft.zustaendigkeiten || []).find((z) => z.istHaupt)?.personKey || ''
      const haupt = keys.includes(vorhandeneHaupt) ? vorhandeneHaupt : (keys[0] || '')
      const payload = keys.map((key) => {
        const member = this.mitglieder.find((m) => this.personKey(m) === key)
        const fallback = optList.find((o) => o.value === key)
        return {
          mitgliedExternId: member?.externId || member?.extern_id || '',
          personName: member ? this.vollerName(member) : (fallback?.label || ''),
        }
      })
      try {
        await axios.put(generateUrl(`/apps/parlwin/geschaefte/${geschaeft.id}`), {
          zustaendigkeiten: payload,
          haupt_person_key: haupt,
        })
        await this.ladeTraktandenFuerSitzung(sitzungId, true, true)
      } catch (fehler) {
        console.error('Fehler beim Speichern der Zuständigkeit:', fehler)
      }
    },
    async aenderungBeschluss(geschaeft, sitzungId, option) {
      const code = option?.value || ''
      try {
        if (code) {
          await axios.post(generateUrl(`/apps/parlwin/geschaefte/${geschaeft.id}/beschluesse`), { code, text: '' })
        } else {
          await axios.delete(generateUrl(`/apps/parlwin/geschaefte/${geschaeft.id}/beschluesse`))
        }
        await this.ladeTraktandenFuerSitzung(sitzungId, true, true)
      } catch (fehler) {
        console.error('Fehler beim Speichern des Beschlusses:', fehler)
      }
    },
    oeffneGeschaeft(traktandum, sitzung) {
      const id = Number(traktandum?.geschaeftId || traktandum?.geschaeft?.id || 0)
      if (id > 0) {
        this.ausgewaehlteGeschaeftId = id
        this.ausgewaehltesGeschaeftTraktandumKontext = sitzung ? {
          sitzungId: sitzung.id,
          sitzungDatum: sitzung.datum,
          sitzungTitel: sitzung.titel,
          traktandumNummer: traktandum.nummer,
          notizen: this.traktandumNotizen[traktandum.id] || [],
        } : null
      }
    },
    schliesseGeschaeft() {
      this.ausgewaehlteGeschaeftId = null
      this.ausgewaehltesGeschaeftTraktandumKontext = null
    },
    async sprungZuSitzung(sitzungId) {
      this.schliesseGeschaeft()
      if (!sitzungId) return
      if (!this.offeneSitzungen.includes(sitzungId)) {
        this.offeneSitzungen.push(sitzungId)
        await this.ladeTraktandenFuerSitzung(sitzungId)
      }
      await this.$nextTick()
      document.getElementById('pw-sitzung-' + sitzungId)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    },
    istVergangen(datum) {
      if (!datum) return false
      return datum < new Date().toISOString().slice(0, 10)
    },
    formatieredatum(datum) {
      if (!datum) return ''
      try {
        return new Date(datum).toLocaleDateString('de-CH', {
          weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
        })
      } catch {
        return datum
      }
    },
    formatieredatumKurz(datum) {
      if (!datum) return ''
      try {
        const d = new Date(datum)
        const tag = String(d.getDate()).padStart(2, '0')
        const monat = String(d.getMonth() + 1).padStart(2, '0')
        const jahr = String(d.getFullYear()).slice(-2)
        return `${tag}.${monat}.${jahr}`
      } catch {
        return ''
      }
    },
    statusKlasse(status) {
      if (!status) return ''
      const s = status.toLowerCase()
      if (s.includes('pendent') || s.includes('offen') || s.includes('laufend')) return 'offen'
      if (s.includes('erledigt') || s.includes('abgeschlossen') || s.includes('aufgehoben')) return 'erledigt'
      if (s.includes('abgelehnt') || s.includes('zurückgezogen')) return 'abgelehnt'
      return 'neutral'
    },
  },
}
</script>

<style scoped>
.pw-neue-sitzung-btn {
  margin-left: auto;
}

.pw-neue-sitzung-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pw-neue-sitzung-form {
  background: var(--color-main-background);
  border-radius: var(--border-radius-large);
  padding: 1.5rem;
  min-width: 320px;
  max-width: 480px;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.pw-neue-sitzung-form-gross {
  max-width: 660px;
  max-height: 90vh;
  overflow-y: auto;
}

/* Titelfeld – prominent wie NC Calendar */
.pw-sitzung-titel-input {
  font-size: 1.25em;
  font-weight: 600;
  border: none;
  border-bottom: 2px solid var(--color-border);
  border-radius: 0;
  background: transparent;
  color: var(--color-main-text);
  padding: 0.25em 0;
  width: 100%;
  outline: none;
}

.pw-sitzung-titel-input:focus {
  border-bottom-color: var(--color-primary);
}

.pw-form-divider {
  border: none;
  border-top: 1px solid var(--color-border-dark);
  margin: 0.25rem 0;
}

.pw-form-zeile {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.pw-form-zeile-oben {
  align-items: flex-start;
}

.pw-form-ikon {
  font-size: 1.1em;
  min-width: 1.5em;
  text-align: center;
  flex-shrink: 0;
}

.pw-form-von-bis {
  display: flex;
  gap: 12px;
  flex: 1;
}

.pw-form-label-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-weight: 500;
  flex: 1;
}

.pw-form-feld {
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  padding: 0.35em 0.6em;
  background: var(--color-main-background);
  color: var(--color-main-text);
  font-size: 0.95em;
}

.pw-form-feld:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

.pw-form-feld-flex {
  flex: 1;
  min-width: 0;
}

.pw-form-feld-zeit {
  width: 6.5em;
}

.pw-form-textarea {
  flex: 1;
  border: 1px solid var(--color-border);
  border-radius: var(--border-radius);
  padding: 0.4em 0.6em;
  background: var(--color-main-background);
  color: var(--color-main-text);
  font-size: 0.95em;
  resize: vertical;
  min-height: 4em;
  font-family: inherit;
}

.pw-form-textarea:focus {
  outline: 2px solid var(--color-primary);
  outline-offset: 1px;
}

.pw-form-traktanden {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.pw-form-traktanden-kopf {
  font-weight: 600;
  font-size: 0.9em;
  color: var(--color-text-lighter);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin-bottom: 0.2rem;
}

.pw-form-traktandum {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.pw-form-traktandum.pw-drag-over {
  outline: 2px solid var(--color-primary);
  border-radius: var(--border-radius);
}

.pw-form-drag-handle {
  cursor: grab;
  color: var(--color-text-lighter);
  flex-shrink: 0;
  user-select: none;
}

.pw-form-teilnehmer {
  flex: 1;
}

.pw-form-teilnehmer-zeile {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-bottom: 0.3rem;
}

.pw-form-teilnehmer-hinweis {
  color: var(--color-text-lighter);
  font-size: 0.9em;
  flex: 1;
}

.pw-form-traktandum-nr {
  min-width: 1.5em;
  font-weight: 600;
  text-align: right;
  flex-shrink: 0;
  color: var(--color-text-lighter);
}

.pw-form-del-btn {
  border: none;
  background: transparent;
  color: var(--color-text-lighter);
  cursor: pointer;
  padding: 0.2em 0.4em;
  border-radius: var(--border-radius);
  flex-shrink: 0;
}

.pw-form-del-btn:hover {
  color: var(--color-error);
  background: var(--color-background-hover);
}

.pw-neue-sitzung-fehler {
  color: var(--color-error);
  font-size: 0.9em;
}

.pw-neue-sitzung-aktionen {
  display: flex;
  gap: 0.5rem;
  justify-content: flex-end;
}

/* Interne Sitzung: Badge + Zweck-Text */
.pw-badge-intern {
  display: inline-block;
  font-size: 0.7em;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: var(--color-primary-light, #e8f0fe);
  color: var(--color-primary, #0082c9);
  border-radius: 3px;
  padding: 0.1em 0.45em;
  vertical-align: middle;
  margin-right: 0.4em;
}

.pw-sitzung-zweck {
  margin: 0.2em 0 0;
  font-size: 0.85em;
  color: var(--color-text-lighter);
  white-space: pre-wrap;
  max-height: 3.5em;
  overflow: hidden;
}

/* Vereinfachte Traktanden-Tabelle für interne Sitzungen */
.pw-tabelle-intern {
  width: 100%;
  border-collapse: collapse;
}

.pw-tabelle-intern th,
.pw-tabelle-intern td {
  padding: 0.4em 0.6em;
  text-align: left;
  border-bottom: 1px solid var(--color-border);
}

.pw-intern-traktandum-titel {
  font-weight: 500;
}

.pw-intern-traktandum-beschreibung {
  font-size: 0.85em;
  color: var(--color-text-lighter);
  margin-top: 0.15em;
}
</style>
