<template>
  <Teleport to="body">
    <!-- Dieselbe Maske für Anlegen und Bearbeiten (F8): Beim Anlegen ist die ID
         0, die Maske sammelt dann nur die Eingaben und schliesst nur über
         «Abbrechen»; ein Klick daneben verwirft nichts. -->
    <div v-if="geschaeftId !== null" class="pw-modal-overlay" @click.self="daneben">
      <div class="pw-modal">
        <div v-if="!neu" class="pw-modal-kopf pw-modal-kopf-leer">
          <button type="button" class="button pw-btn-schliessen" aria-label="Dialog schliessen" @click.stop="schliessen">✕</button>
        </div>
        <GeschaeftDetail
          ref="detail"
          :geschaeft-id="geschaeftId"
          :mitglieder="mitglieder"
          :traktandum-kontext="traktandumKontext"
          :vorauswahl-kommission="vorauswahlKommission"
          @erstellt="erstellt"
          @abbrechen="$emit('schliessen')"
          @oeffne-geschaeft="(id) => $emit('oeffneGeschaeft', id)"
          @oeffne-traktandum="(sitzungId) => $emit('oeffneTraktandum', sitzungId)"
        />
      </div>
    </div>
  </Teleport>
</template>

<script>
import GeschaeftDetail, { MASKE_SCHLUESSEL } from './GeschaeftDetail.vue'
import { BESTAND_SCHLUESSEL } from '../geschaefte-bestand.js'

/**
 * Die Maske eines Geschäfts in «Geschäfte», «Kommissionen» und «Sitzungen».
 *
 * Sie bleibt in der Ansicht eingehängt, auch wenn sie zu ist. Darum kommt bei
 * ihr an, was `GeschaeftDetail` gespeichert hat, auch wenn ✕ die Maske vor der
 * Antwort des Servers geschlossen hat: Sie lädt das Geschäft in der Liste der
 * Ansicht nach und meldet es der Ansicht (F150).
 */
export default {
  name: 'GeschaeftMaske',
  components: { GeschaeftDetail },
  provide() {
    return {
      [BESTAND_SCHLUESSEL]: this.bestand,
      [MASKE_SCHLUESSEL]: { melden: (ereignis, ...daten) => this.melden(ereignis, ...daten) },
    }
  },
  props: {
    // null: zu; 0: ein neues Geschäft; sonst das Geschäft mit dieser ID.
    geschaeftId: { type: Number, default: null },
    // Die Liste der Geschäfte, die die Ansicht zeigt (geschaefte-bestand.js).
    bestand: { type: Object, required: true },
    mitglieder: { type: Array, default: () => [] },
    traktandumKontext: { type: Object, default: null },
    vorauswahlKommission: { type: String, default: '' },
  },
  emits: ['schliessen', 'erstellt', 'gespeichert', 'sitzungsnotizenGeaendert', 'oeffneGeschaeft', 'oeffneTraktandum'],
  computed: {
    neu() {
      return this.geschaeftId === 0
    },
  },
  methods: {
    // Ein offener Notizen-Editor mit ungespeichertem Text schliesst erst nach Rückfrage.
    schliessen() {
      // eslint-disable-next-line no-alert
      if (this.$refs.detail?.hatUngespeicherteNotizen?.() && !window.confirm('Die Notiz ist noch nicht gespeichert. Trotzdem schliessen?')) return
      this.$emit('schliessen')
    },
    daneben() {
      if (!this.neu) this.schliessen()
    },
    erstellt(id) {
      this.$emit('erstellt', id)
      this.bestand.nachladen([id])
    },
    melden(ereignis, ...daten) {
      if (ereignis === 'gespeichert') { this.bestand.nachladen([daten[0]]) }
      this.$emit(ereignis, ...daten)
    },
  },
}
</script>
