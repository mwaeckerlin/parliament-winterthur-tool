<template>
  <Teleport v-if="filterReady" to="#pw-search-slot">
    <NcTextField
      v-model="suche"
      label="Suche"
      placeholder="Nummer, Titel oder Departement"
      trailing-button-icon="close"
      :show-trailing-button="!!suche"
      @trailing-button-click="suche = ''"
    />
  </Teleport>
  <Teleport v-if="filterReady" to="#pw-filter-slot">
    <FilterPanel @reset="filterZuruecksetzen">
      <!-- Die Auswahl zeigt nur, was in den gelesenen Beschlüssen vorkommt:
           ein Departement ohne Beschluss wäre eine Auswahl ohne Ergebnis. -->
      <NcSelect
        v-model="departementAuswahl"
        input-label="Departement"
        :options="departementOptionen"
        :clearable="false"
        :calculate-position="filterDropdownPosition"
      />
      <NcSelect
        v-model="jahrAuswahl"
        input-label="Jahr"
        :options="jahrOptionen"
        :clearable="false"
        :calculate-position="filterDropdownPosition"
      />
    </FilterPanel>
  </Teleport>

  <section class="pw-view-content pw-stadtratsbeschluesse">
    <header class="pw-view-header">
      <h2 class="pw-view-title">Stadtratsbeschlüsse</h2>
      <span class="pw-view-count">{{ gefilterte.length }}</span>
    </header>

    <div v-if="laden" class="pw-laden"><NcLoadingIcon :size="32" /></div>

    <NcEmptyContent
      v-else-if="!beschluesse.length"
      name="Noch keine Stadtratsbeschlüsse"
      description="Die Beschlüsse kommen mit der Synchronisation von der Webseite der Stadt."
    />

    <NcEmptyContent
      v-else-if="!gefilterte.length"
      name="Kein Beschluss passt zur Suche"
      description="Suche und Filter in der Seitenleiste zurücksetzen."
    />

    <div v-else class="pw-card-grid">
      <article
        v-for="b in gefilterte"
        :key="b.id"
        class="pw-data-card pw-srb-karte"
      >
        <div class="pw-data-card-header">
          <div>
            <p class="pw-data-card-kicker">
              <span class="pw-nr">{{ b.nummer }}</span>
              <a
                v-if="b.url"
                :href="b.url"
                target="_blank"
                rel="noopener"
                class="pw-extern-link"
                :title="'Beschluss ' + b.nummer + ' auf der Webseite der Stadt'"
              >↗</a>
            </p>
            <h3>{{ b.titel }}</h3>
          </div>
        </div>

        <div class="pw-data-card-grid">
          <div v-if="b.datum" class="pw-data-pair">
            <span>Beschlossen</span>
            <strong>{{ datumTagMonatJahr(b.datum) }}</strong>
          </div>
          <div v-if="b.departement" class="pw-data-pair">
            <span>Departement</span>
            <strong>{{ b.departement }}</strong>
          </div>
          <div v-if="b.sitzung" class="pw-data-pair">
            <span>Sitzung</span>
            <strong>{{ b.sitzung }}</strong>
          </div>
          <div v-if="b.publiziertAm" class="pw-data-pair">
            <span>Veröffentlicht</span>
            <strong>{{ datumTagMonatJahr(b.publiziertAm) }}</strong>
          </div>
        </div>

        <div v-if="b.dokumente && b.dokumente.length" class="pw-srb-dokumente">
          <strong>Dokumente ({{ b.dokumente.length }}):</strong>
          <ul>
            <li v-for="(d, i) in b.dokumente" :key="i">
              <a :href="d.url" target="_blank" rel="noopener" class="pw-extern-link">{{ d.titel }}</a>
            </li>
          </ul>
        </div>

        <div class="pw-srb-notizen">
          <button
            type="button"
            class="pw-srb-notizen-kopf"
            :aria-expanded="String(offeneNotizen.includes(b.id))"
            @click="notizenUmschalten(b.id)"
          >
            <strong>Notizen ({{ aktiveNotizen(b).length }}):</strong>
            <span class="pw-toggle">{{ offeneNotizen.includes(b.id) ? '▲' : '▼' }}</span>
          </button>
          <NotizenListe
            v-if="offeneNotizen.includes(b.id)"
            :basis-url="'stadtratsbeschluesse/' + b.id"
            :notizen="b.aktionen || []"
            :aktuelle-uid="aktuelleUid"
            @geaendert="notizen => b.aktionen = notizen"
          />
        </div>
      </article>
    </div>
  </section>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'
import { getCurrentUser } from '@nextcloud/auth'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import FilterPanel from './FilterPanel.vue'
import NotizenListe from './NotizenListe.vue'
import { filterDropdownPosition } from '../filter-dropdown'
import { subscribeRealtime } from '../realtime'
import { ereignisseBuendeln, listeAbgleichen } from '../nachladen'

/**
 * Die Beschlüsse des Stadtrats (F144, F145).
 *
 * Die Ansicht steht in der Navigation zwischen «Kommissionen» und
 * «Vorstösse». Der Stadtrat bereitet die Geschäfte des Parlaments vor und
 * vollzieht dessen Beschlüsse, und seine veröffentlichten Beschlüsse liegen
 * jedem Mitglied offen (PARLAMENTSBETRIEB.md); darum stehen sie in diesem
 * Werkzeug neben den Geschäften. Je Beschluss zeigt die Karte Nummer, Titel,
 * Datum, Departement, Sitzung, das Datum der Veröffentlichung und die
 * amtlichen Dokumente, und die Fraktion hängt ihre Notizen daran — mit
 * derselben Komponente wie am Geschäft und am Vorstoss.
 */
export default {
  name: 'Stadtratsbeschluesseliste',
  components: {
    NcTextField,
    NcSelect,
    NcLoadingIcon,
    NcEmptyContent,
    FilterPanel,
    NotizenListe,
  },
  data() {
    return {
      beschluesse: [],
      laden: true,
      suche: '',
      departement: '',
      jahr: '',
      offeneNotizen: [],
      filterReady: false,
      unsubRealtime: null,
      filterDropdownPosition,
    }
  },
  computed: {
    aktuelleUid() {
      return getCurrentUser()?.uid || ''
    },
    /** Nur die Departemente, die in den gelesenen Beschlüssen vorkommen. */
    departemente() {
      const werte = new Set()
      for (const b of this.beschluesse) {
        if (b.departement) {
          werte.add(b.departement)
        }
      }
      return [...werte].sort((a, b) => a.localeCompare(b, 'de-CH'))
    },
    /** Nur die Jahre, aus denen Beschlüsse vorliegen, das jüngste zuerst. */
    jahre() {
      const werte = new Set()
      for (const b of this.beschluesse) {
        const jahr = String(b.datum || '').slice(0, 4)
        if (/^\d{4}$/.test(jahr)) {
          werte.add(jahr)
        }
      }
      return [...werte].sort().reverse()
    },
    departementOptionen() {
      return [{ label: 'Alle Departemente', value: '' },
        ...this.departemente.map(d => ({ label: d, value: d }))]
    },
    jahrOptionen() {
      return [{ label: 'Alle Jahre', value: '' },
        ...this.jahre.map(j => ({ label: j, value: j }))]
    },
    // Die Auswahl zeigt die Option zum gespeicherten Wert, ohne Wahl also
    // «Alle Departemente» und «Alle Jahre», wie die Filter der Mitglieder.
    departementAuswahl: {
      get() { return this.departementOptionen.find(o => o.value === this.wert(this.departement)) || this.departementOptionen[0] },
      set(option) { this.departement = this.wert(option) },
    },
    jahrAuswahl: {
      get() { return this.jahrOptionen.find(o => o.value === this.wert(this.jahr)) || this.jahrOptionen[0] },
      set(option) { this.jahr = this.wert(option) },
    },
    gefilterte() {
      const suche = this.suche.trim().toLowerCase()
      const departement = this.wert(this.departement)
      const jahr = this.wert(this.jahr)
      return this.beschluesse.filter(b => {
        if (departement && b.departement !== departement) {
          return false
        }
        if (jahr && String(b.datum || '').slice(0, 4) !== jahr) {
          return false
        }
        if (!suche) {
          return true
        }
        return [b.nummer, b.titel, b.departement, b.sitzung]
          .some(wert => String(wert || '').toLowerCase().includes(suche))
      })
    },
  },
  mounted() {
    this.filterReady = true
    // Jede Ladung zieht eine Nummer; nur die Antwort der jüngsten gilt.
    this.ladeNummer = 0
    this.ladeBeschluesse()
    // Ereignisse kommen gebündelt an, eigene fallen weg (nachladen.js).
    this.ereignisAnnehmen = ereignisseBuendeln(() => this.ladeBeschluesse(true))
    this.unsubRealtime = subscribeRealtime(ereignis => {
      const typ = String(ereignis?.type || '')
      if (typ === 'sync.completed' || typ === 'stadtratsbeschluesse.updated') {
        this.ereignisAnnehmen(ereignis)
      }
    })
  },
  beforeUnmount() {
    if (this.unsubRealtime) {
      this.unsubRealtime()
    }
    if (this.ereignisAnnehmen) { this.ereignisAnnehmen.abbrechen() }
  },
  methods: {
    /**
     * Lädt die Beschlüsse. Nach einem Ereignis (`hintergrund`) werden die
     * Karten an Ort und Stelle angeglichen, und offene Notizen bleiben offen.
     */
    async ladeBeschluesse(hintergrund = false) {
      const nummer = ++this.ladeNummer
      try {
        const antwort = await axios.get(generateUrl('/apps/parlwin/stadtratsbeschluesse'))
        if (nummer !== this.ladeNummer) return
        const neu = Array.isArray(antwort.data) ? antwort.data : []
        if (hintergrund) {
          listeAbgleichen(this.beschluesse, neu)
        } else {
          this.beschluesse = neu
        }
      } catch (fehler) {
        showError('Die Stadtratsbeschlüsse liessen sich nicht laden: '
          + (fehler.response?.data?.fehler || fehler.message))
      } finally {
        this.laden = false
      }
    },
    /** NcSelect gibt die ganze Option zurück, sobald eine gewählt ist. */
    wert(auswahl) {
      return typeof auswahl === 'object' && auswahl !== null ? auswahl.value : (auswahl || '')
    },
    /** Die Notizen ohne die gelöschten, für die Zahl am Kopf. */
    aktiveNotizen(beschluss) {
      return (beschluss.aktionen || []).filter(a => !a.geloescht)
    },
    notizenUmschalten(id) {
      const stelle = this.offeneNotizen.indexOf(id)
      if (stelle === -1) {
        this.offeneNotizen.push(id)
      } else {
        this.offeneNotizen.splice(stelle, 1)
      }
    },
    /**
     * «2026-10-21» wird zu «21.10.2026».
     *
     * Die Fragestunde schreibt den Monat aus («21. Oktober 2026») und die
     * Übersichtstabellen kürzen das Jahr («21.10.26»); eine Karte mit mehreren
     * Daten nebeneinander liest sich mit dem vollen Jahr am besten.
     */
    datumTagMonatJahr(iso) {
      const teile = String(iso || '').split('-')
      return teile.length === 3 ? `${teile[2]}.${teile[1]}.${teile[0]}` : String(iso || '')
    },
    filterZuruecksetzen() {
      this.suche = ''
      this.departement = ''
      this.jahr = ''
    },
  },
}
</script>
