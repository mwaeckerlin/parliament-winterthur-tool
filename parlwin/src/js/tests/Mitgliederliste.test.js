import { describe, it, expect } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Mitgliederliste from '../components/Mitgliederliste.vue'

// Testdaten: Namen sind bewusst GEGENLÄUFIG zur Funktions-Reihenfolge gewählt
// (Fraktionspräsident heisst «Zürcher», das funktionslose Mitglied «Ammann»),
// damit die Funktions-Sortierung sich klar von der reinen Namens-Sortierung
// unterscheidet — sonst wäre der Test gegen die alte Namens-Sortierung falsch-grün.
const MITGLIEDER = [
  { id: 1, externId: '1', vorname: 'Zoe', name: 'Zürcher', partei: 'SVP', fraktion: 'SVP/EDU', aktiv: true },
  { id: 2, externId: '2', vorname: 'Max', name: 'Müller', partei: 'SVP', fraktion: 'SVP/EDU', aktiv: true },
  { id: 3, externId: '3', vorname: 'Hans', name: 'Huber', partei: 'FDP', fraktion: 'FDP', aktiv: true },
  { id: 4, externId: '4', vorname: 'Bea', name: 'Berger', partei: 'GLP', fraktion: 'GLP', aktiv: true },
  { id: 5, externId: '5', vorname: 'Amy', name: 'Ammann', partei: 'SP', fraktion: 'SP', aktiv: true },
]
const FRAKTIONEN = [
  {
    name: 'SVP/EDU',
    aktiv: true,
    mitglieder: JSON.stringify([
      { externId: '1', funktion: 'Präsident' },
      { externId: '2', funktion: 'Vizepräsident' },
    ]),
  },
]
const KOMMISSIONEN = [
  {
    name: 'RPK',
    aktiv: true,
    mitglieder: JSON.stringify([
      { externId: '3', funktion: 'Präsident' },
      { externId: '4', funktion: 'Mitglied' },
    ]),
  },
]

function mount(mitglieder = MITGLIEDER, fraktionen = FRAKTIONEN, kommissionen = KOMMISSIONEN) {
  return shallowMount(Mitgliederliste, { props: { mitglieder, fraktionen, kommissionen } })
}

const ids = (wrapper) => wrapper.vm.gefilterteMitglieder.map((m) => m.externId)

describe('Mitgliederliste — Sortierung nach Funktion (Standard)', () => {
  it('sortiert standardmässig: Fraktionspräsident, Stellvertreter, Kommissionspräsident, Kommissionsmitglied, Rest', () => {
    const wrapper = mount()
    expect(wrapper.vm.sortierModus).toBe('funktion')
    expect(ids(wrapper)).toEqual(['1', '2', '3', '4', '5'])
  })

  it('sortiert den Rest (gleiche Funktionsstufe) nach Partei, dann Name', () => {
    // Zwei funktionslose Mitglieder: unterschiedliche Partei → Partei entscheidet.
    const m = [
      { id: 10, externId: '10', vorname: 'A', name: 'A', partei: 'SP', fraktion: 'SP', aktiv: true },
      { id: 11, externId: '11', vorname: 'B', name: 'B', partei: 'FDP', fraktion: 'FDP', aktiv: true },
    ]
    const wrapper = mount(m, [], [])
    expect(ids(wrapper)).toEqual(['11', '10']) // FDP vor SP
  })
})

describe('Mitgliederliste — Sortier-Option', () => {
  it('sortiert nach Name, wenn sortierModus = name', async () => {
    const wrapper = mount()
    wrapper.vm.sortierModus = 'name'
    await wrapper.vm.$nextTick()
    expect(ids(wrapper)).toEqual(['5', '4', '3', '2', '1']) // Ammann..Zürcher
  })

  it('sortiert nach Familienname (name), nicht nach Vorname', async () => {
    // Familienname und Vorname laufen gegenläufig: nach Familienname Aebi(1)<Zwicky(2),
    // nach Vorname wäre es Anna(2)<Zoe(1).
    const m = [
      { id: 1, externId: '1', vorname: 'Zoe', name: 'Aebi', partei: 'X', fraktion: 'X', aktiv: true },
      { id: 2, externId: '2', vorname: 'Anna', name: 'Zwicky', partei: 'X', fraktion: 'X', aktiv: true },
    ]
    const wrapper = mount(m, [], [])
    wrapper.vm.sortierModus = 'name'
    await wrapper.vm.$nextTick()
    expect(ids(wrapper)).toEqual(['1', '2'])
  })

  it('sortiert nach Partei, wenn sortierModus = partei', async () => {
    const wrapper = mount()
    wrapper.vm.sortierModus = 'partei'
    await wrapper.vm.$nextTick()
    const parteien = wrapper.vm.gefilterteMitglieder.map((x) => x.partei)
    expect(parteien).toEqual([...parteien].sort((a, b) => a.localeCompare(b, 'de')))
  })

  it('bietet die Sortier-Optionen Funktion, Fraktion, Partei, Name', () => {
    const wrapper = mount()
    const werte = wrapper.vm.sortierOptions.map((o) => o.value)
    expect(werte).toEqual(['funktion', 'fraktion', 'partei', 'name'])
  })
})

describe('Mitgliederliste — Filter Funktion (Fraktions-/Kommissionspräsident)', () => {
  it('bietet die Optionen Alle, Fraktionspräsident, Kommissionspräsident', () => {
    const wrapper = mount()
    const werte = wrapper.vm.funktionFilterOptions.map((o) => o.value)
    expect(werte).toEqual(['', 'fraktionspraesident', 'kommissionspraesident'])
  })

  it('zeigt mit Funktion=Fraktionspräsident nur Fraktionspräsidenten (nicht Stellvertreter)', async () => {
    const wrapper = mount()
    wrapper.vm.filterFunktion = 'fraktionspraesident'
    await wrapper.vm.$nextTick()
    expect(ids(wrapper)).toEqual(['1'])
  })

  it('zeigt mit Funktion=Kommissionspräsident nur Kommissionspräsidenten', async () => {
    const wrapper = mount()
    wrapper.vm.filterFunktion = 'kommissionspraesident'
    await wrapper.vm.$nextTick()
    expect(ids(wrapper)).toEqual(['3'])
  })
})

describe('Mitgliederliste — Filter nach Kommission', () => {
  it('zeigt mit Kommissions-Filter nur Mitglieder dieser Kommission', async () => {
    const wrapper = mount()
    wrapper.vm.filterKommission = 'RPK'
    await wrapper.vm.$nextTick()
    expect(ids(wrapper).sort()).toEqual(['3', '4'])
  })
})
