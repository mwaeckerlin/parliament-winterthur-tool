import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount, shallowMount } from '@vue/test-utils'
import PwSelect from '../components/PwSelect.vue'
import PwKommissionSelect from '../components/PwKommissionSelect.vue'
import PwPrioritaetSelect from '../components/PwPrioritaetSelect.vue'
import PwTypSelect from '../components/PwTypSelect.vue'
import PwDatumInput from '../components/PwDatumInput.vue'

// NcSelect ist die Blatt-Komponente; im Test durch einen Platzhalter ersetzt,
// damit wir die Aufbereitung (Label/Wert) der Basis unabhängig prüfen können.
const NcSelectStub = { name: 'NcSelect', props: ['modelValue', 'options'], template: '<div class="nc-select-stub" />' }
const stubs = { NcSelect: NcSelectStub }

describe('PwSelect – gemeinsame Basis aller Datentyp-Selects', () => {
  it('bereitet String-Optionen zu {label,value} auf; der Formatierer wirkt NUR aufs Label', () => {
    const w = shallowMount(PwSelect, {
      props: {
        modelValue: 'Sachkommission Soziales und Sicherheit',
        options: ['Aufsichtskommission', 'Sachkommission Soziales und Sicherheit'],
        format: (t) => t.replace('Sachkommission ', 'SK '),
      },
      global: { stubs },
    })
    expect(w.vm.aufbereitet).toEqual([
      { value: 'Aufsichtskommission', label: 'Aufsichtskommission' },
      { value: 'Sachkommission Soziales und Sicherheit', label: 'SK Soziales und Sicherheit' },
    ])
    // Die Auswahl folgt dem ROHWERT, die Anzeige ist gekürzt.
    expect(w.vm.gewaehlt).toEqual({ value: 'Sachkommission Soziales und Sicherheit', label: 'SK Soziales und Sicherheit' })
  })

  it('emittiert beim Update den Rohwert (nicht das Option-Objekt); Leeren ergibt null', () => {
    const w = shallowMount(PwSelect, { props: { modelValue: null, options: ['A', 'B'] }, global: { stubs } })
    w.vm.onUpdate({ value: 'B', label: 'B' })
    w.vm.onUpdate(null)
    expect(w.emitted('update:model-value')).toEqual([['B'], [null]])
  })

  it('kommt auch mit {label,value}-Optionen klar', () => {
    const w = shallowMount(PwSelect, { props: { modelValue: 'hoch', options: [{ value: 'hoch', label: 'Hoch' }, { value: 'tief', label: 'Tief' }] }, global: { stubs } })
    expect(w.vm.gewaehlt).toEqual({ value: 'hoch', label: 'Hoch' })
  })
})

describe('PwKommissionSelect – Kürzel im Dropdown (Screenshot-Bug: volle Namen)', () => {
  beforeEach(() => { window.PARLWIN_CONFIG = { statusKuerzel: [{ suche: 'Sachkommission Soziales und Sicherheit', kuerzel: 'SK Soz. u. Sicherheit' }] } })
  afterEach(() => { delete window.PARLWIN_CONFIG })

  it('zeigt die Kommissionen gekürzt an, gibt aber den vollen Namen zurück', () => {
    const w = mount(PwKommissionSelect, {
      props: { modelValue: 'Sachkommission Soziales und Sicherheit', options: ['Aufsichtskommission', 'Sachkommission Soziales und Sicherheit'] },
      global: { stubs },
    })
    const sel = w.findComponent(PwSelect)
    expect(sel.vm.aufbereitet).toContainEqual({ value: 'Sachkommission Soziales und Sicherheit', label: 'SK Soz. u. Sicherheit' })
    // Auswahl liefert den vollen Namen (nicht das Kürzel) zurück.
    sel.vm.onUpdate({ value: 'Aufsichtskommission', label: 'Aufsichtskommission' })
    expect(w.emitted('update:model-value')[0]).toEqual(['Aufsichtskommission'])
  })
})

describe('PwPrioritaetSelect – kapselt die Prioritätsstufen an einem Ort', () => {
  it('bietet Hoch/Mittel/Tief und emittiert den Rohwert', () => {
    const w = mount(PwPrioritaetSelect, { props: { modelValue: 'hoch' }, global: { stubs } })
    const sel = w.findComponent(PwSelect)
    expect(sel.vm.aufbereitet).toEqual([
      { value: 'hoch', label: 'Hoch' },
      { value: 'mittel', label: 'Mittel' },
      { value: 'tief', label: 'Tief' },
    ])
    sel.vm.onUpdate({ value: 'tief', label: 'Tief' })
    sel.vm.onUpdate(null)
    expect(w.emitted('update:model-value')).toEqual([['tief'], ['']])
  })
})

describe('PwTypSelect – Admin-Typen', () => {
  it('reicht die Typen durch und emittiert den Rohwert', () => {
    const w = mount(PwTypSelect, { props: { modelValue: 'Motion', options: ['Motion', 'Postulat'] }, global: { stubs } })
    const sel = w.findComponent(PwSelect)
    expect(sel.vm.aufbereitet).toEqual([{ value: 'Motion', label: 'Motion' }, { value: 'Postulat', label: 'Postulat' }])
    sel.vm.onUpdate({ value: 'Postulat', label: 'Postulat' })
    expect(w.emitted('update:model-value')[0]).toEqual(['Postulat'])
  })
})

describe('PwDatumInput – einheitliches Datumsfeld', () => {
  it('rendert ein type=date-Feld und emittiert den ISO-Wert bei Änderung', async () => {
    const w = mount(PwDatumInput, { props: { modelValue: '2026-08-07' } })
    const input = w.find('input[type="date"]')
    expect(input.exists()).toBe(true)
    expect(input.element.value).toBe('2026-08-07')
    input.element.value = '2026-09-01'
    await input.trigger('change')
    expect(w.emitted('update:model-value')[0]).toEqual(['2026-09-01'])
  })
})
