<template>
  <label class="pw-field">
    <span class="pw-field-label">{{ label }}<slot name="label" /></span>
    <slot />
  </label>
</template>

<script>
export default {
  name: 'PwField',
  props: {
    label: { type: String, default: '' },
  },
}
</script>

<style scoped>
.pw-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.pw-field-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--color-text-maxcontrast);
}
</style>
