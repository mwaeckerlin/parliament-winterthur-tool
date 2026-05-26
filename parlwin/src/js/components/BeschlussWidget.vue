<template>
  <div class="pw-beschluss-widget" @keydown.stop>
    <textarea
      v-if="freitextModus"
      v-model="freitextText"
      class="pw-textarea"
      rows="2"
      :disabled="disabled"
      placeholder="Freitext Beschluss"
      @input="freitextInput"
      @blur="freitextBlur"
    />
    <NcSelect
      v-else
      :model-value="selektierterWert"
      :options="options"
      :taggable="true"
      :create-option="(text) => ({ label: text, value: '', freitext: true })"
      :clearable="true"
      :disabled="disabled"
      :placeholder="placeholder"
      label="label"
      @update:model-value="nachWahl"
    />
  </div>
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect'

export default {
  name: 'BeschlussWidget',
  components: { NcSelect },
  props: {
    modelValue: { type: Object, default: null },
    options: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Beschluss eingeben oder aus Liste wählen…' },
  },
  emits: ['update:modelValue'],
  data() {
    return {
      freitextModus: !!(this.modelValue?.freitext),
      freitextText: this.modelValue?.freitext ? (this.modelValue.label || '') : '',
      timer: null,
    }
  },
  computed: {
    selektierterWert() {
      if (!this.modelValue || this.modelValue.freitext) return null
      return this.modelValue
    },
  },
  watch: {
    modelValue(val) {
      if (!val) {
        this.freitextModus = false
        this.freitextText = ''
      } else if (val.freitext) {
        if (!this.freitextModus) {
          this.freitextModus = true
          this.freitextText = val.label || ''
        }
      } else {
        this.freitextModus = false
      }
    },
  },
  beforeUnmount() {
    if (this.timer) clearTimeout(this.timer)
  },
  methods: {
    nachWahl(val) {
      if (!val) {
        this.freitextModus = false
        this.freitextText = ''
        this.$emit('update:modelValue', null)
        return
      }
      if (val.freitext) {
        this.freitextModus = true
        this.freitextText = val.label || ''
        return
      }
      this.$emit('update:modelValue', val)
    },
    freitextInput() {
      if (!this.freitextText) {
        if (this.timer) { clearTimeout(this.timer); this.timer = null }
        this.freitextModus = false
        this.$emit('update:modelValue', null)
        return
      }
      if (this.timer) clearTimeout(this.timer)
      this.timer = setTimeout(() => {
        this.timer = null
        this.$emit('update:modelValue', { label: this.freitextText, value: '', freitext: true })
      }, 5000)
    },
    freitextBlur() {
      if (this.timer) { clearTimeout(this.timer); this.timer = null }
      if ((this.freitextText || '').trim()) {
        this.$emit('update:modelValue', { label: this.freitextText, value: '', freitext: true })
      }
    },
  },
}
</script>

<style scoped>
.pw-beschluss-widget {
  width: 100%;
}
.pw-textarea {
  width: 100%;
  padding: 6px 8px;
  border: 1px solid var(--color-border, #ccc);
  border-radius: var(--border-radius, 4px);
  background: var(--color-main-background, #fff);
  color: var(--color-main-text, #000);
  resize: vertical;
}
</style>
