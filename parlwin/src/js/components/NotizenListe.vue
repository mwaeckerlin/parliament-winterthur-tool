<template>
  <div class="pw-notizen">
    <div
      v-for="(n, idx) in notizen"
      :key="idx"
      class="pw-notiz-zeile"
      :class="{ 'pw-notiz-eigen': istEigene(n), 'pw-notiz-bearbeiten': bearbeiteIdx === idx, 'pw-notiz-drag-over': dragUeberIdx === idx }"
      draggable="true"
      @dragstart="dragStart($event, idx)"
      @dragover.prevent="dragOver($event, idx)"
      @dragleave="dragLeave"
      @drop.prevent="drop($event, idx)"
      @dragend="dragEnd"
    >
      <span class="pw-notiz-griff" title="Verschieben" aria-hidden="true">⠿</span>
      <span class="pw-notiz-datum">{{ n.datum }}</span>
      <span class="pw-notiz-autor">{{ n.displayName || n.uid }}</span>
      <template v-if="bearbeiteIdx === idx">
        <input
          ref="bearbeitenInput"
          v-model="bearbeiteText"
          type="text"
          class="pw-input pw-notiz-eingabe"
          @keyup.enter="bearbeitenSpeichern"
          @keyup.escape="bearbeitenAbbrechen"
        />
        <button
          type="button"
          class="button pw-btn-mini"
          title="Speichern"
          @click="bearbeitenSpeichern"
        >✓</button>
        <button
          type="button"
          class="button pw-btn-mini"
          title="Abbrechen"
          @click="bearbeitenAbbrechen"
        >✕</button>
      </template>
      <template v-else>
        <span
          class="pw-notiz-text"
          :class="{ 'pw-notiz-text-klickbar': istEigene(n) }"
          :role="istEigene(n) ? 'button' : null"
          :tabindex="istEigene(n) ? 0 : null"
          :title="istEigene(n) ? 'Klicken zum Bearbeiten' : ''"
          @click="istEigene(n) && starteBearbeiten(idx, n)"
          @keydown.enter.prevent="istEigene(n) && starteBearbeiten(idx, n)"
        >{{ n.text }}</span>
        <button
          v-if="istEigene(n)"
          type="button"
          class="button pw-btn-mini"
          title="Notiz löschen"
          @click="loeschen(idx)"
        >✕</button>
      </template>
    </div>
    <div class="pw-neue-notiz">
      <input
        v-model="neuerText"
        type="text"
        :placeholder="placeholder"
        class="pw-input pw-notiz-eingabe"
        @keyup.enter="hinzufuegen"
      />
      <button
        type="button"
        class="button pw-btn-mini"
        :disabled="!neuerText.trim()"
        title="Notiz hinzufügen"
        @click="hinzufuegen"
      >+</button>
    </div>
  </div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'

/**
 * Anzeige + Bearbeitung einer Notizen-Liste.
 *
 * Notizen sind Objekte `{ datum, uid, displayName, text }`. Nur der
 * Urheber kann eigene Notizen löschen oder durch Klick auf den Text
 * bearbeiten. Andere Notizen sind reine Anzeige.
 */
export default {
  name: 'NotizenListe',
  props: {
    /** Aktuelle Notizen-Liste. */
    modelValue: { type: Array, default: () => [] },
    /** Placeholder für das Eingabefeld einer neuen Notiz. */
    placeholder: { type: String, default: 'Notiz hinzufügen…' },
  },
  emits: ['update:modelValue'],
  data() {
    return {
      neuerText: '',
      bearbeiteIdx: -1,
      bearbeiteText: '',
      dragVonIdx: -1,
      dragUeberIdx: -1,
    }
  },
  computed: {
    notizen() {
      return Array.isArray(this.modelValue) ? this.modelValue : []
    },
    eigenerUid() {
      const u = getCurrentUser()
      return (u?.uid || '').toLowerCase()
    },
  },
  methods: {
    istEigene(n) {
      const uid = (n?.uid || '').toLowerCase()
      return !!uid && uid === this.eigenerUid
    },
    hinzufuegen() {
      const text = (this.neuerText || '').trim()
      if (!text) return
      const u = getCurrentUser()
      const eintrag = {
        text,
        datum: new Date().toLocaleString('de-CH'),
        uid: u?.uid || '',
        displayName: u?.displayName || u?.uid || '',
      }
      this.$emit('update:modelValue', [...this.notizen, eintrag])
      this.neuerText = ''
    },
    loeschen(idx) {
      const n = this.notizen[idx]
      if (!n || !this.istEigene(n)) return
      const next = [...this.notizen]
      next.splice(idx, 1)
      this.$emit('update:modelValue', next)
    },
    starteBearbeiten(idx, n) {
      if (!this.istEigene(n)) return
      this.bearbeiteIdx = idx
      this.bearbeiteText = String(n.text || '')
      this.$nextTick(() => {
        const ref = this.$refs.bearbeitenInput
        const el = Array.isArray(ref) ? ref[0] : ref
        el?.focus?.()
      })
    },
    bearbeitenAbbrechen() {
      this.bearbeiteIdx = -1
      this.bearbeiteText = ''
    },
    bearbeitenSpeichern() {
      const idx = this.bearbeiteIdx
      const text = (this.bearbeiteText || '').trim()
      if (idx < 0) return
      const aktuell = this.notizen[idx]
      if (!aktuell || !this.istEigene(aktuell)) {
        this.bearbeitenAbbrechen()
        return
      }
      if (!text) {
        // Leerer Text → wie Löschen behandeln.
        this.loeschen(idx)
        this.bearbeitenAbbrechen()
        return
      }
      const next = [...this.notizen]
      next[idx] = { ...aktuell, text }
      this.$emit('update:modelValue', next)
      this.bearbeitenAbbrechen()
    },
    dragStart(event, idx) {
      this.dragVonIdx = idx
      event.dataTransfer.effectAllowed = 'move'
    },
    dragOver(event, idx) {
      event.dataTransfer.dropEffect = 'move'
      this.dragUeberIdx = idx
    },
    dragLeave() {
      this.dragUeberIdx = -1
    },
    drop(event, zuIdx) {
      const vonIdx = this.dragVonIdx
      this.dragUeberIdx = -1
      this.dragVonIdx = -1
      if (vonIdx < 0 || vonIdx === zuIdx) return
      const next = [...this.notizen]
      const [verschoben] = next.splice(vonIdx, 1)
      next.splice(zuIdx, 0, verschoben)
      this.$emit('update:modelValue', next)
    },
    dragEnd() {
      this.dragVonIdx = -1
      this.dragUeberIdx = -1
    },
  },
}
</script>
