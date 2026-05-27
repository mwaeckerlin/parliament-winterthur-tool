import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import NotizenListe from '../components/NotizenListe.vue'

vi.mock('@nextcloud/auth', () => ({
  getCurrentUser: () => ({ uid: 'testuser', displayName: 'Test User' }),
}))

function mount(notizen = []) {
  return shallowMount(NotizenListe, {
    props: { modelValue: notizen },
  })
}

// ── Autosave: no '+' button ──────────────────────────────────────────────────

describe('NotizenListe — kein Speichern-Button', () => {
  it('hat keinen + Button im Template', () => {
    const wrapper = mount()
    expect(wrapper.find('button[title="Notiz hinzufügen"]').exists()).toBe(false)
    const buttons = wrapper.findAll('button')
    const hasPlus = buttons.some(b => b.text() === '+')
    expect(hasPlus).toBe(false)
  })
})

// ── Autosave: blur ───────────────────────────────────────────────────────────

describe('NotizenListe — Autosave bei Blur', () => {
  it('emittiert update:modelValue bei blur mit Text', async () => {
    const wrapper = mount()
    const input = wrapper.find('input.pw-notiz-eingabe')
    await input.setValue('Neue Notiz')
    await input.trigger('blur')
    const emits = wrapper.emitted('update:modelValue')
    expect(emits).toBeTruthy()
    expect(emits[0][0].at(-1).text).toBe('Neue Notiz')
  })

  it('emittiert nichts bei blur mit leerem Feld', async () => {
    const wrapper = mount()
    const input = wrapper.find('input.pw-notiz-eingabe')
    await input.setValue('   ')
    await input.trigger('blur')
    expect(wrapper.emitted('update:modelValue')).toBeFalsy()
  })

  it('leert das Eingabefeld nach Blur-Speicherung', async () => {
    const wrapper = mount()
    const input = wrapper.find('input.pw-notiz-eingabe')
    await input.setValue('Text')
    await input.trigger('blur')
    expect(wrapper.vm.neuerText).toBe('')
  })
})

// ── Autosave: Enter ──────────────────────────────────────────────────────────

describe('NotizenListe — Autosave bei Enter', () => {
  it('emittiert update:modelValue bei Enter', async () => {
    const wrapper = mount()
    const input = wrapper.find('input.pw-notiz-eingabe')
    await input.setValue('Enter-Notiz')
    await input.trigger('keyup', { key: 'Enter' })
    const emits = wrapper.emitted('update:modelValue')
    expect(emits).toBeTruthy()
    expect(emits[0][0].at(-1).text).toBe('Enter-Notiz')
  })
})

// ── Autosave: Debounce ───────────────────────────────────────────────────────

describe('NotizenListe — Debounce (5s)', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('speichert nach 5 Sekunden ohne weiteren Input', async () => {
    const wrapper = mount()
    const input = wrapper.find('input.pw-notiz-eingabe')
    await input.setValue('Debounce-Notiz')
    await input.trigger('input')
    expect(wrapper.emitted('update:modelValue')).toBeFalsy()
    vi.advanceTimersByTime(5000)
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
  })

  it('bricht den Timer ab wenn Blur früher kommt', async () => {
    const wrapper = mount()
    const input = wrapper.find('input.pw-notiz-eingabe')
    await input.setValue('Früh blur')
    await input.trigger('input')
    await input.trigger('blur')
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
    const text = wrapper.find('.pw-notiz-text-klickbar')
    await text.trigger('click')
    expect(wrapper.vm.bearbeiteIdx).toBe(0)
  })

  it('emittiert geänderten Text bei ✓', async () => {
    const wrapper = mount([eigeneNotiz])
    await wrapper.find('.pw-notiz-text-klickbar').trigger('click')
    await wrapper.vm.$nextTick()
    const input = wrapper.find('input.pw-notiz-eingabe')
    await input.setValue('Neu')
    await wrapper.find('button[title="Speichern"]').trigger('click')
    expect(wrapper.emitted('update:modelValue')[0][0][0].text).toBe('Neu')
  })

  it('emittiert Liste ohne Eintrag bei Löschen', async () => {
    const wrapper = mount([eigeneNotiz])
    await wrapper.find('button[title="Notiz löschen"]').trigger('click')
    expect(wrapper.emitted('update:modelValue')[0][0]).toHaveLength(0)
  })
})
