import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import NotizenListe from '../components/NotizenListe.vue'
import PwWysiwyg from '../components/PwWysiwyg.vue'

vi.mock('@nextcloud/auth', () => ({
  getCurrentUser: () => ({ uid: 'testuser', displayName: 'Test User' }),
}))

function mount(notizen = []) {
  return shallowMount(NotizenListe, {
    props: { modelValue: notizen },
  })
}

// Das Eingabefeld für neue Notizen ist ein WYSIWYG-Editor (PwWysiwyg) am Ende
// der Liste. Beim Bearbeiten erscheint ein weiterer Editor in der Notiz-Zeile.
function neueNotizEditor(wrapper) {
  const editoren = wrapper.findAllComponents(PwWysiwyg)
  return editoren[editoren.length - 1]
}

// ── Eingabefeld ist WYSIWYG ───────────────────────────────────────────────────

describe('NotizenListe — WYSIWYG-Eingabefeld', () => {
  it('nutzt einen PwWysiwyg-Editor (kein einfaches input) für neue Notizen', () => {
    const wrapper = mount()
    expect(wrapper.findComponent(PwWysiwyg).exists()).toBe(true)
    expect(wrapper.find('input.pw-notiz-eingabe').exists()).toBe(false)
  })

  it('hat keinen + Button im Template', () => {
    const wrapper = mount()
    expect(wrapper.find('button[title="Notiz hinzufügen"]').exists()).toBe(false)
    const hasPlus = wrapper.findAll('button').some(b => b.text() === '+')
    expect(hasPlus).toBe(false)
  })
})

// ── Autosave: blur ───────────────────────────────────────────────────────────

describe('NotizenListe — Autosave bei Blur', () => {
  it('emittiert update:modelValue bei blur mit Text', async () => {
    const wrapper = mount()
    const editor = neueNotizEditor(wrapper)
    editor.vm.$emit('update:modelValue', 'Neue Notiz')
    await wrapper.vm.$nextTick()
    editor.vm.$emit('blur')
    await wrapper.vm.$nextTick()
    const emits = wrapper.emitted('update:modelValue')
    expect(emits).toBeTruthy()
    expect(emits.at(-1)[0].at(-1).text).toBe('Neue Notiz')
  })

  it('emittiert nichts bei blur mit leerem Feld', async () => {
    const wrapper = mount()
    neueNotizEditor(wrapper).vm.$emit('blur')
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('update:modelValue')).toBeFalsy()
  })

  it('leert das Eingabefeld nach Blur-Speicherung', async () => {
    const wrapper = mount()
    const editor = neueNotizEditor(wrapper)
    editor.vm.$emit('update:modelValue', 'Text')
    await wrapper.vm.$nextTick()
    editor.vm.$emit('blur')
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.neuerText).toBe('')
  })
})

// ── Autosave: Debounce ───────────────────────────────────────────────────────

describe('NotizenListe — Debounce (5s)', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('speichert nach 5 Sekunden ohne weiteren Input', async () => {
    const wrapper = mount()
    neueNotizEditor(wrapper).vm.$emit('update:modelValue', 'Debounce-Notiz')
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('update:modelValue')).toBeFalsy()
    vi.advanceTimersByTime(5000)
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
  })

  it('bricht den Timer ab wenn Blur früher kommt', async () => {
    const wrapper = mount()
    const editor = neueNotizEditor(wrapper)
    editor.vm.$emit('update:modelValue', 'Früh blur')
    await wrapper.vm.$nextTick()
    editor.vm.$emit('blur')
    await wrapper.vm.$nextTick()
    vi.advanceTimersByTime(5000)
    await wrapper.vm.$nextTick()
    // Nur ein einzelnes Emit — Blur hat schon gespeichert, Timer darf nicht nochmal auslösen
    expect(wrapper.emitted('update:modelValue')?.length ?? 0).toBe(1)
  })
})

// ── Bearbeiten und Löschen ───────────────────────────────────────────────────

describe('NotizenListe — Bearbeiten', () => {
  const eigeneNotiz = { uid: 'testuser', displayName: 'Test User', datum: '27.05.2026', text: 'Alt' }

  it('öffnet Bearbeitungsmodus bei Klick auf eigene Notiz', async () => {
    const wrapper = mount([eigeneNotiz])
    await wrapper.find('.pw-notiz-text-klickbar').trigger('click')
    expect(wrapper.vm.bearbeiteIdx).toBe(0)
  })

  it('emittiert geänderten Text bei ✓', async () => {
    const wrapper = mount([eigeneNotiz])
    await wrapper.find('.pw-notiz-text-klickbar').trigger('click')
    await wrapper.vm.$nextTick()
    // Editor in der Notiz-Zeile (erstes PwWysiwyg) speist v-model bearbeiteText
    wrapper.findAllComponents(PwWysiwyg)[0].vm.$emit('update:modelValue', 'Neu')
    await wrapper.vm.$nextTick()
    await wrapper.find('button[title="Speichern"]').trigger('click')
    expect(wrapper.emitted('update:modelValue')[0][0][0].text).toBe('Neu')
  })

  it('emittiert Liste ohne Eintrag bei Löschen', async () => {
    const wrapper = mount([eigeneNotiz])
    await wrapper.find('button[title="Notiz löschen"]').trigger('click')
    expect(wrapper.emitted('update:modelValue')[0][0]).toHaveLength(0)
  })
})
