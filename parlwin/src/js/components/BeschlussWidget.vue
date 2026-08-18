<template>
  <div class="pw-beschluss-widget" @keydown.stop>
    <input
      :id="listId"
      type="text"
      :list="listId + '-dl'"
      :value="currentText"
      :disabled="disabled"
      :placeholder="placeholder"
      :aria-label="ariaLabel || null"
      :class="['pw-input', inputClass]"
      @input="handleInput"
      @blur="handleBlur"
      @change="handleChange"
    />
    <datalist :id="listId + '-dl'">
      <option v-for="o in options" :key="o.value" :value="o.label" />
    </datalist>
  </div>
</template>

<script>
export default {
  name: 'BeschlussWidget',
  props: {
    modelValue: { type: Object, default: null },
    options: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Beschluss eingeben oder aus Liste wählen…' },
    // Klasse des Eingabefeldes. Default ist der Beschluss; bei einem zweiten
    // Einsatz desselben Widgets (z.B. Status am eigenen Geschäft) eine andere
    // setzen, damit die beiden Felder unterscheidbar bleiben.
    inputClass: { type: String, default: 'pw-beschluss-input' },
    // Beschriftung für die Sprachausgabe; leer lässt das Attribut weg.
    ariaLabel: { type: String, default: '' },
  },
  emits: ['update:modelValue', 'blur'],
  data() {
    return {
      listId: 'pw-beschluss-' + Math.random().toString(36).slice(2),
      timer: null,
      localText: this.modelValue?.label || '',
    }
  },
  computed: {
    currentText() {
      return this.modelValue?.label || ''
    },
  },
  watch: {
    modelValue(val) {
      this.localText = val?.label || ''
    },
  },
  beforeUnmount() {
    if (this.timer) {
      clearTimeout(this.timer)
      this.timer = null
      this.emitValue(this.localText)
    }
  },
  methods: {
    handleInput(event) {
      const text = event.target.value
      this.localText = text
      if (this.timer) clearTimeout(this.timer)
      // Auch der leere Zwischenzustand wird verzögert (nicht sofort als null
      // gespeichert): Beim Umwählen über die Datalist ist das Feld kurz leer,
      // bevor der neue Wert einrastet — ein sofortiges null-Emit würde einen
      // Zwischenstand «→ —» speichern und einen zweiten Zeitleisten-Eintrag
      // erzeugen. Blur/Change (echter Abschluss) speichern weiterhin sofort.
      this.timer = setTimeout(() => {
        this.timer = null
        this.emitValue(text)
      }, 5000)
    },
    handleBlur(event) {
      if (this.timer) { clearTimeout(this.timer); this.timer = null }
      this.emitValue(event.target.value)
      this.$emit('blur')
    },
    handleChange(event) {
      // Change fires when user picks from datalist — emit immediately.
      if (this.timer) { clearTimeout(this.timer); this.timer = null }
      this.emitValue(event.target.value)
    },
    emitValue(text) {
      const trimmed = (text || '').trim()
      if (!trimmed) {
        this.$emit('update:modelValue', null)
        return
      }
      const match = this.options.find(o => o.label === trimmed)
      if (match) {
        this.$emit('update:modelValue', match)
      } else {
        this.$emit('update:modelValue', { label: trimmed, value: '', freitext: true })
      }
    },
  },
}
</script>

<style scoped>
.pw-beschluss-widget {
  width: 100%;
}
.pw-beschluss-input {
  width: 100%;
  padding: 6px 8px;
  border: 1px solid var(--color-border, #ccc);
  border-radius: var(--border-radius, 4px);
  background: var(--color-main-background, #fff);
  color: var(--color-main-text, #000);
}
</style>
