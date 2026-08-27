<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField v-model="suche" label="Suche" placeholder="Nr. oder Titel" trailing-button-icon="close" :show-trailing-button="!!suche" @trailing-button-click="suche = ''" />
  </Teleport>
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <FilterPanel @reset="resetFilter">
      <NcSelect v-model="entscheidungsbedarfOption" :options="entscheidungsbedarfOptions" :clearable="false" input-label="Entscheidungsbedarf" />
      <PwMultiSelect :model-value="filterStatus" :options="alleStatus" input-label="Status" placeholder="Alle" @update:model-value="filterStatus = $event || []" />
      <PwMultiSelect :model-value="filterTyp" :options="alleTypen" input-label="Typ" placeholder="Alle" @update:model-value="filterTyp = $event || []" />
      <PwMultiSelect :model-value="filterZustaendigeOptions" :options="zustaendigeOptionen" input-label="Zuständigkeit" placeholder="Alle" @update:model-value="filterZustaendige = ($event || []).map(o => o.value)" />
      <PwMultiSelect :model-value="filterBeschlussOptions" :options="beschlussOptionsList" input-label="Beschluss" placeholder="Alle" @update:model-value="filterBeschluss = ($event || []).map(o => o.value)" />
      <PwMultiSelect :model-value="filterPrioritaetOptions" :options="prioritaetOptionen" input-label="Priorität" placeholder="Alle" @update:model-value="filterPrioritaet = ($event || []).map(o => o.value)" />
      <PwMultiSelect :model-value="filterEinreicher" :options="einreicherOptionen" input-label="Einreicher" placeholder="Alle" @update:model-value="filterEinreicher = $event || []" />
      <NcCheckboxRadioSwitch v-model="nurErsteinreicher" type="switch">
        Nur Ersteinreicher
      </NcCheckboxRadioSwitch>
      <PwMultiSelect :model-value="filterPartei" :options="parteiOptionen" input-label="Partei" placeholder="Alle" @update:model-value="filterPartei = $event || []" />
      <NcCheckboxRadioSwitch v-model="zeigeErledigte" type="switch">
        Erledigte anzeigen
      </NcCheckboxRadioSwitch>
    </FilterPanel>
  </Teleport>

  <section class="pw-view-content pw-geschaefte">
      <header class="pw-view-header">
        <h2 class="pw-view-title">Geschäfte</h2>
        <span class="pw-view-count">{{ gefilterteGeschaefte.length }}</span>
        <NcButton type="primary" @click="neuesGeschaeftOeffnen">+ Eigenes Geschäft</NcButton>
      </header>

      <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>

      <template v-else>
        <div class="pw-table-wrap pw-table-desktop">
          <table class="pw-tabelle pw-tabelle-geschaefte" :class="{ 'pw-tabelle-mit-status': statusSpalteAnzeigen }" lang="de">
        <thead>
          <tr>
            <th @click="sortiereNach('nummer')" class="pw-sortierbar pw-col-nr">Nr.</th>
            <th @click="sortiereNach('titel')" class="pw-sortierbar pw-col-titel">Titel</th>
            <th class="pw-col-prio">Prio</th>
            <th v-if="statusSpalteAnzeigen" @click="sortiereNach('status')" class="pw-sortierbar pw-col-status">Status</th>
            <th class="pw-col-zustaendig">Zuständig</th>
            <th class="pw-col-beschluss">Beschluss</th>
          </tr>
        </thead>
          <tbody>
            <tr
              v-for="g in gefilterteGeschaefte"
              :key="g.id"
              :class="['pw-table-row-clickable', { 'pw-geloescht': g.geloescht, 'pw-prio-hoch': prioritaetEffektiv(g) === 'hoch', 'pw-prio-tief': prioritaetEffektiv(g) === 'tief' }]"
              tabindex="0"
              role="button"
              :aria-label="`Geschäft ${g.nummer || ''} öffnen`"
              @click="oeffneDetail(g.id)"
              @keydown="zeilenKeydown($event, g.id)"
            >
              <td data-label="Nr." class="pw-col-nr">
                <strong>{{ g.nummer }}</strong>
                <span class="pw-col-nr-datum">{{ formatieredatumKurz(g.datum) }}</span>
                <span class="pw-col-nr-typ">{{ g.typ }}</span>
              </td>
              <td class="pw-titel pw-col-titel" data-label="Titel">
                <a v-if="g.url" :href="g.url" target="_blank" @click.stop class="pw-inline-link" title="Extern öffnen">↗</a>
                {{ g.titel }}
                <span v-if="erstunterzeichner(g)" class="pw-col-einreicher">{{ erstunterzeichner(g) }}</span>
              </td>
              <td data-label="Prio" class="pw-col-inline-edit pw-col-prio" @click.stop>
                <PwPrioritaetSelect
                  class="pw-inline-select"
                  :model-value="g.prioritaet"
                  @update:model-value="aenderungPrioritaet(g, $event)"
                />
              </td>
              <td v-if="statusSpalteAnzeigen" data-label="Status" class="pw-col-status">
                <span :class="['pw-status-' + statusKlasse(g.status), 'pw-status-text']" :title="g.status">{{ statusKuerzen(g.status) }}</span>
              </td>
              <td data-label="Zuständig" class="pw-col-inline-edit pw-col-zustaendig" @click.stop>
                <PwMultiSelect
                  class="pw-inline-select"
                  :model-value="zustaendigOptionenFuer(g)"
                  :options="zustaendigeOptionenFuerSelect"
                  :clearable="true"
                  placeholder="—"
                  label="label"
                  @update:model-value="aenderungZustaendig(g, $event || [])"
                />
              </td>
              <td data-label="Beschluss" class="pw-col-inline-edit pw-col-beschluss" @click.stop>
                <BeschlussWidget
                  class="pw-inline-beschluss"
                  :model-value="beschlussOptionFuer(g)"
                  :options="beschlussOptionenFuer(g)"
                  placeholder="—"
                  @update:model-value="aenderungBeschluss(g, $event)"
                />
              </td>
            </tr>
          </tbody>
          </table>
        </div>

        <div class="pw-card-grid pw-card-mobile">
          <article
            v-for="g in gefilterteGeschaefte"
            :key="`card-${g.id}`"
            class="pw-data-card pw-geschaeft-card"
            :class="{ 'pw-geloescht': g.geloescht, 'pw-prio-hoch': prioritaetEffektiv(g) === 'hoch', 'pw-prio-tief': prioritaetEffektiv(g) === 'tief' }"
            tabindex="0"
            role="button"
            @click="oeffneDetail(g.id)"
            @keydown="zeilenKeydown($event, g.id)"
          >
            <div class="pw-data-card-header">
              <div>
                <p class="pw-data-card-kicker">
                  <a v-if="g.url" :href="g.url" target="_blank" @click.stop class="pw-inline-link" title="Extern öffnen">↗</a>
                  {{ g.nummer || 'Ohne Nummer' }}
                </p>
                <h3>{{ g.titel }}</h3>
              </div>
              <span :class="'pw-status-' + statusKlasse(g.status)">{{ statusKuerzen(g.status) || '—' }}</span>
            </div>

            <div class="pw-data-card-grid">
              <div class="pw-data-pair">
                <span>Typ</span>
                <strong>{{ g.typ || '—' }}</strong>
              </div>
              <div class="pw-data-pair">
                <span>Datum</span>
                <strong>{{ formatieredatum(g.datum) || '—' }}</strong>
              </div>
              <div class="pw-data-pair">
                <span>Zuständigkeit</span>
                <strong>{{ g.hauptZustaendigePerson || '—' }}</strong>
              </div>
            </div>

            <div class="pw-card-prio" @click.stop>
              <PwPrioritaetSelect
                class="pw-inline-select"
                :model-value="g.prioritaet"
                input-label="Priorität"
                @update:model-value="aenderungPrioritaet(g, $event)"
              />
            </div>

            <div class="pw-card-beschluss" @click.stop>
              <BeschlussWidget
                :model-value="beschlussOptionFuer(g)"
                :options="beschlussOptionenFuer(g)"
                placeholder="—"
                @update:model-value="aenderungBeschluss(g, $event)"
              />
            </div>
          </article>
        </div>

        <NcEmptyContent v-if="gefilterteGeschaefte.length === 0" name="Keine Geschäfte gefunden" />
      </template>
    </section>

    <Teleport to="body">
      <!-- Dieselbe Maske für Anlegen und Bearbeiten: beim Anlegen ist die ID 0,
           die Maske sammelt dann nur die Eingaben und bietet Speichern/Abbrechen.
           Ein Klick daneben verwirft dabei nichts. -->
      <div v-if="detailOffen" class="pw-modal-overlay" @click.self="overlayKlick">
        <div class="pw-modal">
          <div v-if="!neuesGeschaeft" class="pw-modal-kopf pw-modal-kopf-leer">
            <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click.stop="schliesseDetail">✕</button>
          </div>
          <GeschaeftDetail
            ref="detail"
            :geschaeft-id="ausgewaehlteGeschaeftId"
            :mitglieder="mitglieder"
            @gespeichert="nachSpeichern"
            @erstellt="nachErstellen"
            @abbrechen="neuAbbrechen"
            @oeffne-geschaeft="oeffneDetail"
          />
        </div>
      </div>
    </Teleport>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { subscribeRealtime } from '../realtime'
import { vollerName, personKey, PRIORITAETEN, kuerze, mitLeerOption } from '../utils'
import GeschaeftDetail from './GeschaeftDetail.vue'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import PwMultiSelect from './PwMultiSelect.vue'
import PwPrioritaetSelect from './PwPrioritaetSelect.vue'
import BeschlussWidget from './BeschlussWidget.vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import FilterPanel from './FilterPanel.vue'

export default {
  name: 'Geschaeftsliste',
  components: { GeschaeftDetail, NcTextField, NcSelect, PwMultiSelect, PwPrioritaetSelect, NcCheckboxRadioSwitch, NcButton, NcLoadingIcon, NcEmptyContent, BeschlussWidget, FilterPanel },
  props: {
    mitglieder: { type: Array, default: () => [] },
  },
  emits: ['aktualisiert'],
  data() {
    return {
      filterReady: false,
      geschaefte: [],
      laden: true,
      statusKuerzelListe: window.PARLWIN_CONFIG?.statusKuerzel || [],
      suche: '',
      filterStatus: [],
      filterPrioritaet: [],
      // Die Maske ist im Anlege-Modus: es existiert noch kein Geschäft.
      neuesGeschaeft: false,
      filterTyp: [],
      filterZustaendige: [],
      filterBeschluss: [],
      filterEinreicher: [],
      filterPartei: [],
      nurErsteinreicher: false,
      filterEntscheidungsbedarf: '',
      zeigeErledigte: false,
      sortFeld: 'datum',
      sortRichtung: 'desc',
      ausgewaehlteGeschaeftId: null,
      unsubRealtime: null,
      reloadTimer: null,
    }
  },
  computed: {
    entscheidungsbedarfOptions() {
      return [
        { label: 'Alle', value: '' },
        { label: 'Nur Entscheid nötig', value: '1' },
        { label: 'Nur ohne offenen Entscheid', value: '0' },
      ]
    },
    entscheidungsbedarfOption: {
      get() { return this.entscheidungsbedarfOptions.find(o => o.value === this.filterEntscheidungsbedarf) || this.entscheidungsbedarfOptions[0] },
      set(v) { this.filterEntscheidungsbedarf = v ? v.value : '' },
    },
    alleStatus() {
      return [...new Set(this.geschaefte.map(g => g.status).filter(Boolean))].sort()
        .map(s => ({ label: this.statusKuerzen(s), value: s }))
    },
    statusSpalteAnzeigen() {
      // Wenn genau ein Status gefiltert ist, wäre die Spalte redundant.
      return !(Array.isArray(this.filterStatus) && this.filterStatus.length === 1)
    },
    alleTypen() {
      return [...new Set(this.geschaefte.map(g => g.typ).filter(Boolean))].sort()
    },
    // Auswahl der Einreicher-Personen: die Namen aller Einreicher über alle Geschäfte.
    einreicherOptionen() {
      const namen = new Set()
      this.geschaefte.forEach(g => this.einreicherNamen(g).forEach(n => namen.add(n)))
      return [...namen].sort((a, b) => a.localeCompare(b))
    },
    // Auswahl der Parteien: die Parteien, die als Einreicher tatsächlich vorkommen.
    parteiOptionen() {
      const parteien = new Set()
      this.geschaefte.forEach(g => this.einreicherParteien(g).forEach(p => parteien.add(p)))
      return [...parteien].sort((a, b) => a.localeCompare(b))
    },
    // Nachschlage-Index Einreicher→Partei aus den Mitgliedern (Personen-ID der
    // Webseite zuerst, sonst normalisierter Name — wie im Backend beim Zuordnen
    // der einreichenden Mitglieder).
    parteiIndex() {
      const byExtern = {}
      const byName = {}
      this.mitglieder.forEach(m => {
        const partei = m.partei || m.Partei || ''
        if (!partei) return
        const extId = String(m.externId || m.extern_id || '')
        if (extId) byExtern[extId] = partei
        const name = this.normEinreicherName(m.name || '')
        if (name) byName[name] = partei
      })
      return { byExtern, byName }
    },
    prioritaetOptionen() {
      // Grundprinzip (wie Beschluss): nur die tatsächlich gesetzten Stufen (feste
      // Reihenfolge) — plus «Undefiniert», wenn Geschäfte ohne gesetzte Priorität
      // vorkommen. Auch die undefinierte Priorität ist ein realer Wert der Daten.
      const vorhanden = new Set()
      let hatUndefiniert = false
      this.geschaefte.forEach((g) => {
        const p = (g.prioritaet || '').trim()
        if (p === '') { hatUndefiniert = true } else { vorhanden.add(p) }
      })
      const stufen = PRIORITAETEN.filter((o) => vorhanden.has(o.value))
      return mitLeerOption(stufen, hatUndefiniert, 'Undefiniert')
    },
    filterPrioritaetOptions() {
      return this.prioritaetOptionen.filter(o => this.filterPrioritaet.includes(o.value))
    },
    alleBeschluesse() {
      // Gleiches Grundprinzip wie überall, derselbe Helfer: die vorkommenden
      // Beschlüsse plus «—» für Geschäfte ohne erfassten Beschluss (leerer Wert).
      const seen = new Map()
      let hatOhneBeschluss = false
      this.geschaefte.forEach(g => {
        const b = g.letzterBeschluss
        const code = b?.aktionCode || ''
        if (!code) {
          hatOhneBeschluss = true
          return
        }
        if (!seen.has(code)) {
          seen.set(code, { value: code, label: b.titel || code })
        }
      })
      const liste = [...seen.values()].sort((a, b) => a.label.localeCompare(b.label))
      return mitLeerOption(liste, hatOhneBeschluss, '—')
    },
    zustaendigeOptionen() {
      // Grundprinzip (wie Beschluss): GENAU die Hauptzuständigen anbieten, die an
      // mindestens einem Geschäft gesetzt sind (nicht jedes Mitglied) — plus
      // «Nicht zugewiesen», wenn es Geschäfte ohne Zuständige gibt. Auch der leere
      // Wert ist ein realer Wert der Daten.
      const map = new Map()
      let hatUnzugewiesen = false
      this.geschaefte.forEach((geschaeft) => {
        const label = (geschaeft.hauptZustaendigePerson || '').trim()
        if (label === '') {
          hatUnzugewiesen = true
          return
        }
        if (map.has(label)) {
          return
        }
        const mitglied = this.mitglieder.find((m) => this.vollerName(m) === label)
        map.set(label, {
          value: label,
          label,
          // Aktiv-Kennzeichen (falls das Mitglied auffindbar ist) nur für die Sortierung.
          aktiv: mitglied ? mitglied.aktiv !== false : false,
        })
      })
      const personen = [...map.values()].sort((a, b) => {
        if (a.aktiv !== b.aktiv) {
          return a.aktiv ? -1 : 1
        }
        return a.label.localeCompare(b.label)
      })
      return mitLeerOption(personen, hatUnzugewiesen, 'Nicht zugewiesen')
    },
    filterZustaendigeOptions() {
      return this.zustaendigeOptionen.filter((o) => this.filterZustaendige.includes(o.value))
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
    beschlussOptionsList() {
      // alleBeschluesse liefert bereits {value,label} (wie die anderen Filter).
      return this.alleBeschluesse
    },
    filterBeschlussOptions() {
      return this.beschlussOptionsList.filter((o) => this.filterBeschluss.includes(o.value))
    },
    ausgewaehltesGeschaeft() {
      return this.geschaefte.find((geschaeft) => geschaeft.id === this.ausgewaehlteGeschaeftId) || null
    },
    // Die Maske ist offen, wenn ein Geschäft gewählt ist ODER ein neues erfasst
    // wird (dort ist die ID noch 0).
    detailOffen() {
      return this.neuesGeschaeft || !!this.ausgewaehlteGeschaeftId
    },
    gefilterteGeschaefte() {
      let liste = [...this.geschaefte]

      if (!this.zeigeErledigte) {
        liste = liste.filter((g) => !this.istErledigtStatus(g.status || ''))
      }

      if (this.suche) {
        const s = this.suche.toLowerCase()
        liste = liste.filter(g =>
          (g.titel || '').toLowerCase().includes(s) ||
          (g.nummer || '').toLowerCase().includes(s)
        )
      }
      if (this.filterStatus.length > 0) {
        const statusWerte = this.filterStatus.map(o => (o && typeof o === 'object' ? o.value : o))
        liste = liste.filter(g => statusWerte.includes(g.status))
      }
      if (this.filterTyp.length > 0) {
        liste = liste.filter(g => this.filterTyp.includes(g.typ))
      }
      if (this.filterZustaendige.length > 0) {
        liste = liste.filter(g => this.filterZustaendige.includes(g.hauptZustaendigePerson || ''))
      }
      if (this.filterBeschluss.length > 0) {
        liste = liste.filter(g => this.filterBeschluss.includes(g.letzterBeschluss?.aktionCode || ''))
      }
      if (this.filterPrioritaet.length > 0) {
        // Gegen den ROHwert filtern, damit «Undefiniert» (leerer Wert) genau die
        // Geschäfte ohne gesetzte Priorität trifft — getrennt von «Mittel».
        liste = liste.filter(g => this.filterPrioritaet.includes((g.prioritaet || '').trim()))
      }
      // Einreicher-Person: standardmässig trifft jeder Einreicher; mit «Nur
      // Ersteinreicher» nur der erste (Erstunterzeichner) eines Geschäfts.
      if (this.filterEinreicher.length > 0) {
        liste = liste.filter(g => this.nurErsteinreicher
          ? this.filterEinreicher.includes(this.ersteinreicherName(g))
          : this.einreicherNamen(g).some(n => this.filterEinreicher.includes(n)))
      }
      // Partei: das Geschäft trifft, wenn eine Partei eines Einreichers gewählt ist.
      if (this.filterPartei.length > 0) {
        liste = liste.filter(g => this.einreicherParteien(g).some(p => this.filterPartei.includes(p)))
      }

      liste.sort((a, b) => {
        const av = this.sortWert(a, this.sortFeld)
        const bv = this.sortWert(b, this.sortFeld)
        return this.sortRichtung === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av)
      })

      return liste
    },
  },
  watch: {
    filterEntscheidungsbedarf() {
      this.ladeGeschaefte()
    },
    zeigeErledigte() {
      this.ladeGeschaefte()
    },
  },
  mounted() {
    this.$nextTick(() => { this.filterReady = true })
    this.initialisiereAnsicht()
    this.unsubRealtime = subscribeRealtime(this.handleRealtimeEvent)
  },
  beforeUnmount() {
    if (this.unsubRealtime) {
      this.unsubRealtime()
      this.unsubRealtime = null
    }
    if (this.reloadTimer) {
      window.clearTimeout(this.reloadTimer)
      this.reloadTimer = null
    }
  },
  methods: {
    toggleMehrfachFilter(feld, wert, checked) {
      const liste = Array.isArray(this[feld]) ? [...this[feld]] : []
      const index = liste.indexOf(wert)
      const soll = checked === undefined ? index < 0 : !!checked
      if (soll && index < 0) liste.push(wert)
      else if (!soll && index >= 0) liste.splice(index, 1)
      this[feld] = liste
    },
    vollerName,
    personKey,
    zustaendigOptionenFuer(geschaeft) {
      const zust = Array.isArray(geschaeft.zustaendigkeiten) ? geschaeft.zustaendigkeiten : []
      return zust.map((z) => {
        const treffer = this.zustaendigeOptionenFuerSelect.find((o) => o.value === z.personKey)
        return treffer || { label: z.personName || z.personKey, value: z.personKey, mitglied: null }
      })
    },
    zeilenKeydown(event, id) {
      if (event.target.closest('input, select, textarea, [contenteditable], [role="combobox"], [role="listbox"], [role="option"]')) return
      if (event.key === ' ') { event.preventDefault(); this.oeffneDetail(id) }
      else if (event.key === 'Enter') { event.preventDefault(); this.oeffneDetail(id) }
    },
    beschlussOptionenFuer(geschaeft) {
      const erlaubt = Array.isArray(geschaeft.erlaubteBeschluesse) ? geschaeft.erlaubteBeschluesse : []
      return erlaubt.map((b) => ({ label: b.label || b.code, value: b.code }))
    },
    beschlussOptionFuer(geschaeft) {
      const lb = geschaeft.letzterBeschluss
      if (!lb) return null
      const code = lb.aktionCode || ''
      if (!code && lb.text) return { label: lb.text, value: '', freitext: true }
      if (!code) return null
      const optionen = this.beschlussOptionenFuer(geschaeft)
      return optionen.find((o) => o.value === code) || { label: lb.titel || code, value: code }
    },
    async aenderungZustaendig(geschaeft, optionen) {
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
        await this.ladeGeschaefte()
        this.$emit('aktualisiert')
      } catch (fehler) {
        console.error('Fehler beim Speichern der Zuständigkeit:', fehler)
      }
    },
    prioritaetEffektiv(geschaeft) {
      return geschaeft.prioritaet || 'mittel'
    },
    async aenderungPrioritaet(geschaeft, wert) {
      const prioritaet = ['hoch', 'mittel', 'tief'].includes(wert) ? wert : ''
      try {
        await axios.put(generateUrl(`/apps/parlwin/geschaefte/${geschaeft.id}/prioritaet`), { prioritaet })
        await this.ladeGeschaefte()
        this.$emit('aktualisiert')
      } catch (fehler) {
        console.error('Fehler beim Speichern der Priorität:', fehler)
      }
    },
    async aenderungBeschluss(geschaeft, option) {
      const code = option?.value || ''
      const text = option?.freitext ? (option.label || '') : ''
      try {
        if (code || text) {
          await axios.post(generateUrl(`/apps/parlwin/geschaefte/${geschaeft.id}/beschluesse`), { code, text })
        } else {
          await axios.delete(generateUrl(`/apps/parlwin/geschaefte/${geschaeft.id}/beschluesse`))
        }
        await this.ladeGeschaefte()
        this.$emit('aktualisiert')
      } catch (fehler) {
        console.error('Fehler beim Speichern des Beschlusses:', fehler)
      }
    },
    async initialisiereAnsicht() {
      try {
        const { data } = await axios.get(generateUrl('/apps/parlwin/settings/fraktionssitzung'))
        if (data?.modusAktiv && this.filterEntscheidungsbedarf === '') {
          this.filterEntscheidungsbedarf = '1'
          return
        }
      } catch (fehler) {
        // Fallback: ohne Kontext lädt die Liste normal.
      }
      this.ladeGeschaefte()
    },
    async ladeGeschaefte(zeigeLaden = true) {
      // Bei Realtime-Reloads KEIN Lade-Flackern (sonst verschwindet die Liste kurz
      // und der Scrollbalken/Fokus springt); mergeGeschaefte() patcht in-place.
      if (zeigeLaden) this.laden = true
      try {
        const params = { limit: 500 }
        params.show_erledigt = this.zeigeErledigte ? '1' : '0'
        if (this.filterEntscheidungsbedarf !== '') {
          params.entscheidungsbedarf = this.filterEntscheidungsbedarf
        }
        const { data } = await axios.get(generateUrl('/apps/parlwin/geschaefte'), { params })
        this.mergeGeschaefte(Array.isArray(data) ? data : [])
      } catch (fehler) {
        console.error('Fehler beim Laden der Geschäfte:', fehler)
      } finally {
        if (zeigeLaden) this.laden = false
      }
    },
    // In-place-Merge (wie bei den Sitzungen): ein Realtime-Sync baut so nicht das
    // ganze DOM neu auf, der Scrollbalken bleibt stehen.
    mergeGeschaefte(neu) {
      const neuMap = new Map(neu.map(g => [g.id, g]))
      for (const g of this.geschaefte) {
        const n = neuMap.get(g.id)
        if (n) Object.assign(g, n)
      }
      const vorhanden = new Set(this.geschaefte.map(g => g.id))
      for (const n of neu) {
        if (!vorhanden.has(n.id)) this.geschaefte.push(n)
      }
      for (let i = this.geschaefte.length - 1; i >= 0; i--) {
        if (!neuMap.has(this.geschaefte[i].id)) this.geschaefte.splice(i, 1)
      }
      const reihenfolge = new Map(neu.map((g, i) => [g.id, i]))
      this.geschaefte.sort((a, b) => (reihenfolge.get(a.id) ?? 0) - (reihenfolge.get(b.id) ?? 0))
    },
    handleRealtimeEvent(event) {
      const type = event?.type || ''
      if (type === 'sync.completed' || type.startsWith('geschaefte.') || type === 'fraktionssitzung.updated') {
        this.scheduleReload()
      }
    },
    scheduleReload() {
      if (this.reloadTimer) return
      this.reloadTimer = window.setTimeout(async () => {
        this.reloadTimer = null
        await this.ladeGeschaefte(false)
      }, 250)
    },
    sortiereNach(feld) {
      if (this.sortFeld === feld) {
        this.sortRichtung = this.sortRichtung === 'asc' ? 'desc' : 'asc'
      } else {
        this.sortFeld = feld
        this.sortRichtung = 'asc'
      }
    },
    sortWert(g, feld) {
      const v = g[feld] || ''
      if (feld === 'nummer') {
        // Zweite Komponente nach dem Punkt auf 4 Stellen mit führenden Nullen
        // auffüllen, damit "2026.9" vor "2026.10" landet.
        return String(v).replace(/^(\d+)\.(\d+)/, (_, jahr, nr) => `${jahr}.${nr.padStart(4, '0')}`)
      }
      return String(v)
    },
    oeffneDetail(geschaeftId) {
      this.ausgewaehlteGeschaeftId = geschaeftId
    },
    statusKuerzen(text) {
      return kuerze(text, this.statusKuerzelListe)
    },
    // «+ Eigenes Geschäft» öffnet dieselbe Maske wie das Bearbeiten (geteilter
    // Code). Angelegt wird noch nichts — erst «Speichern» erzeugt das Geschäft.
    neuesGeschaeftOeffnen() {
      this.neuesGeschaeft = true
      this.ausgewaehlteGeschaeftId = 0
    },
    // Nach dem Anlegen geht dieselbe Maske in die Bearbeitung über, sodass sich
    // Dokumente und Notizen unmittelbar anschliessen lassen.
    async nachErstellen(id) {
      this.neuesGeschaeft = false
      await this.ladeGeschaefte()
      this.ausgewaehlteGeschaeftId = id || null
      this.$emit('aktualisiert')
    },
    // Nur der ausdrückliche Abbruch verwirft die Eingaben.
    neuAbbrechen() {
      this.neuesGeschaeft = false
      this.ausgewaehlteGeschaeftId = null
    },
    // Ein Klick neben die Maske darf beim Erfassen nichts verwerfen.
    overlayKlick() {
      if (!this.neuesGeschaeft) this.schliesseDetail()
    },
    resetFilter() {
      this.suche = ''
      this.filterStatus = []
      this.filterPrioritaet = []
      this.filterTyp = []
      this.filterZustaendige = []
      this.filterBeschluss = []
      this.filterEinreicher = []
      this.filterPartei = []
      this.nurErsteinreicher = false
      this.filterEntscheidungsbedarf = ''
      this.zeigeErledigte = false
      this.ladeGeschaefte()
    },
    schliesseDetail() {
      // Warnung, wenn im Notiz-Editor ungespeicherte Änderungen offen sind.
      if (this.$refs.detail?.hatUngespeicherteNotizen?.()) {
        // eslint-disable-next-line no-alert
        if (!window.confirm('Die Notiz ist noch nicht gespeichert. Trotzdem schliessen?')) return
      }
      this.ausgewaehlteGeschaeftId = null
      this.neuesGeschaeft = false
    },
    async nachSpeichern() {
      await this.ladeGeschaefte()
      this.$emit('aktualisiert')
    },
    formatieredatum(datum) {
      if (!datum) return ''
      try {
        return new Date(datum).toLocaleDateString('de-CH')
      } catch {
        return datum
      }
    },
    erstunterzeichner(g) {
      const liste = g.einreicher
      if (!Array.isArray(liste) || liste.length === 0) return ''
      return liste.map(p => p.name).join(', ')
    },
    // Normalisierung wie im Backend (trim, Mehrfach-Leerzeichen zu einem, klein).
    normEinreicherName(name) {
      return String(name || '').trim().replace(/\s+/g, ' ').toLowerCase()
    },
    einreicherNamen(g) {
      const liste = Array.isArray(g.einreicher) ? g.einreicher : []
      return liste.map(e => e && e.name).filter(Boolean)
    },
    ersteinreicherName(g) {
      const liste = Array.isArray(g.einreicher) ? g.einreicher : []
      return (liste[0] && liste[0].name) || ''
    },
    // Distinkte Parteien der Einreicher eines Geschäfts (über die Mitglieder aufgelöst).
    einreicherParteien(g) {
      const liste = Array.isArray(g.einreicher) ? g.einreicher : []
      const idx = this.parteiIndex
      const out = new Set()
      liste.forEach(e => {
        if (!e) return
        const extId = String(e.externId || e.extern_id || '')
        let partei = extId && idx.byExtern[extId] ? idx.byExtern[extId] : ''
        if (!partei) partei = idx.byName[this.normEinreicherName(e.name || '')] || ''
        if (partei) out.add(partei)
      })
      return [...out]
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
      if (this.istErledigtStatus(s)) return 'erledigt'
      if (s.includes('abgelehnt') || s.includes('zurückgezogen')) return 'abgelehnt'
      return 'neutral'
    },
    istErledigtStatus(status) {
      const s = (status || '').toLowerCase()
      // Regel: Status gilt als "erledigt", wenn er "erledigt", "abgeschlossen"
      // oder "aufgehoben" enthält (z.B. "Durch Rechtsmittelinstanz aufgehoben").
      return s.includes('erledigt') || s.includes('abgeschlossen') || s.includes('aufgehoben')
    },
    fraktionsstatusLabel(status) {
      if (status === 'neu_zu_entscheiden') return 'Neu zu entscheiden'
      if (status === 'entschieden') return 'Entschieden'
      return 'Offen'
    },
    fraktionsstatusKlasse(status) {
      if (status === 'neu_zu_entscheiden') return 'offen'
      if (status === 'entschieden') return 'erledigt'
      return 'neutral'
    },
  },
}
</script>
