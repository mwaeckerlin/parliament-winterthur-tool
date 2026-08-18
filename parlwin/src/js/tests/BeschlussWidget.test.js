import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import BeschlussWidget from '../components/BeschlussWidget.vue'

const OPTIONS = [
  { label: 'Annahme', value: 'annahme' },
  { label: 'Ablehnung', value: 'ablehnung' },
]

function mount(props = {}) {
  return shallowMount(BeschlussWidget, {
    props: { modelValue: null, options: OPTIONS, ...props },
  })
}

// ── Datalist vorhanden ────────────────────────────────────────────────────────

describe('BeschlussWidget — datalist', () => {
  it('rendert ein input[list] und eine datalist', () => {
    const wrapper = mount()
    const input = wrapper.find('input[type="text"]')
    expect(input.exists()).toBe(true)
    expect(input.attributes('list')).toBeTruthy()
    expect(wrapper.find('datalist').exists()).toBe(true)
  })

  it('rendert alle Optionen als datalist-Einträge', () => {
    const wrapper = mount()
    const opts = wrapper.findAll('datalist option')
    expect(opts).toHaveLength(OPTIONS.length)
    expect(opts[0].attributes('value')).toBe('Annahme')
    expect(opts[1].attributes('value')).toBe('Ablehnung')
  })
})

// ── Bekannte Option auswählen ─────────────────────────────────────────────────

describe('BeschlussWidget — Auswahl aus Liste', () => {
  it('emittiert passendes Objekt wenn Label aus Optionen gewählt wird', async () => {
    const wrapper = mount()
    const input = wrapper.find('input')
    await input.setValue('Annahme')
    await input.trigger('change')
    expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toEqual(OPTIONS[0])
  })

  it('emittiert freitext-Objekt bei unbekanntem Text', async () => {
    const wrapper = mount()
    const input = wrapper.find('input')
    await input.setValue('Eigener Text')
    await input.trigger('change')
    const emitted = wrapper.emitted('update:modelValue')?.[0]?.[0]
    expect(emitted?.freitext).toBe(true)
    expect(emitted?.label).toBe('Eigener Text')
  })

  it('emittiert null wenn Feld geleert wird', async () => {
    const wrapper = mount({ modelValue: OPTIONS[0] })
    const input = wrapper.find('input')
    await input.setValue('')
    await input.trigger('blur')
    expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBeNull()
  })
})

// ── Ein Wechsel = ein Update (kein zwischenzeitliches Leeren) ─────────────────
// Bug: Ein Statuswechsel erzeugte ZWEI Zeitleisten-Events («Pendent» → «—» und
// «—» → neuer Wert). Ursache: die Datalist-Neuauswahl leert das Feld kurz, und
// der Leerzustand emittierte SOFORT null — das speicherte einen Zwischenstand.
describe('BeschlussWidget — ein Wechsel erzeugt kein Zwischen-null', () => {
  it('ein kurzer Leerzustand vor der Neuauswahl emittiert kein zwischenzeitliches null', async () => {
    const wrapper = mount({ modelValue: OPTIONS[0] })
    const input = wrapper.find('input')
    input.element.value = ''
    await input.trigger('input')
    input.element.value = 'Ablehnung'
    await input.trigger('change')
    // Genau EIN Update — auf den neuen Wert, ohne zwischenzeitliches null.
    expect(wrapper.emitted('update:modelValue')).toEqual([[OPTIONS[1]]])
  })

  it('gilt auch für Freitext-Werte (Screenshot-Fall): ein Wechsel = genau ein Update', async () => {
    const wrapper = mount({ modelValue: { label: 'Pendent', value: '', freitext: true } })
    const input = wrapper.find('input')
    input.element.value = ''
    await input.trigger('input')
    input.element.value = 'Bei der Aufsichtskommission pendent'
    await input.trigger('blur')
    const updates = wrapper.emitted('update:modelValue') || []
    expect(updates.length).toBe(1)
    expect(updates[0][0]).toMatchObject({ label: 'Bei der Aufsichtskommission pendent', freitext: true })
  })
})

// ── Autosave bei Blur ─────────────────────────────────────────────────────────

describe('BeschlussWidget — Blur speichert', () => {
  it('emittiert bei blur mit bekanntem Wert passendes Objekt', async () => {
    const wrapper = mount()
    const input = wrapper.find('input')
    await input.setValue('Ablehnung')
    await input.trigger('blur')
    expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toEqual(OPTIONS[1])
  })

  it('emittiert bei blur mit Freitext freitext-Objekt', async () => {
    const wrapper = mount()
    const input = wrapper.find('input')
    await input.setValue('Freier Beschluss')
    await input.trigger('blur')
    const emitted = wrapper.emitted('update:modelValue')?.[0]?.[0]
    expect(emitted?.freitext).toBe(true)
    expect(emitted?.label).toBe('Freier Beschluss')
  })
})

// ── Debounce ──────────────────────────────────────────────────────────────────

describe('BeschlussWidget — Debounce (5s)', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('emittiert nach 5s ohne weiteren Input', async () => {
    const wrapper = mount()
    const input = wrapper.find('input')
    // Direkt Element-Value setzen + nur input (nicht change) triggern
    input.element.value = 'Verzögerter Text'
    await input.trigger('input')
    expect(wrapper.emitted('update:modelValue')).toBeFalsy()
    vi.advanceTimersByTime(5000)
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toMatchObject({ label: 'Verzögerter Text' })
  })

  it('blur bricht Timer ab und speichert sofort', async () => {
    const wrapper = mount()
    const input = wrapper.find('input')
    input.element.value = 'Sofort'
    await input.trigger('input')
    await input.trigger('blur')
    vi.advanceTimersByTime(5000)
    await wrapper.vm.$nextTick()
    // Nur ein Emit: vom blur — timer hätte ein zweites ausgelöst
    expect(wrapper.emitted('update:modelValue')?.length ?? 0).toBe(1)
  })
})

// ── Session-Signale (Eingabe-Session endet mit Fokus-Verlust) ─────────────────

describe('BeschlussWidget — Session-Signale', () => {
  it('emittiert blur-Ereignis beim Verlassen des Feldes', async () => {
    const wrapper = mount()
    const input = wrapper.find('input')
    await input.setValue('Abschreiben')
    await input.trigger('blur')
    expect(wrapper.emitted('blur')).toBeTruthy()
  })

  it('speichert ausstehende Eingabe beim Entfernen der Komponente (Seite verlassen)', async () => {
    vi.useFakeTimers()
    const wrapper = mount()
    const input = wrapper.find('input')
    input.element.value = 'Halbfertig'
    await input.trigger('input')
    wrapper.unmount()
    const emits = wrapper.emitted('update:modelValue') || []
    expect(emits.at(-1)?.[0]).toMatchObject({ label: 'Halbfertig' })
    vi.useRealTimers()
  })
})

// ── currentText zeigt Wert aus modelValue ─────────────────────────────────────

describe('BeschlussWidget — Anzeige', () => {
  it('zeigt Label aus modelValue im Input-Feld', () => {
    const wrapper = mount({ modelValue: OPTIONS[0] })
    expect(wrapper.find('input').element.value).toBe('Annahme')
  })

  it('zeigt leer wenn modelValue null', () => {
    const wrapper = mount({ modelValue: null })
    expect(wrapper.find('input').element.value).toBe('')
  })
})
