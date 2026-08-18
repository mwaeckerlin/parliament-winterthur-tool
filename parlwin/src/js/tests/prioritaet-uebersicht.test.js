import { describe, it, expect, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'
import Geschaeftsliste from '../components/Geschaeftsliste.vue'
import PwPrioritaetSelect from '../components/PwPrioritaetSelect.vue'
import axios from '@nextcloud/axios'

const stylePath = resolve(dirname(fileURLToPath(import.meta.url)), '../../css/style.scss')

// Feature: Jedes Geschäft hat eine Priorität (hoch/mittel/tief). Standard nicht
// gesetzt → wie «mittel». In der Übersicht wird «hoch» hervorgehoben und «tief»
// abgeschwächt; zusätzlich filterbar nach Priorität.
describe('Geschaeftsliste — Priorität', () => {
  beforeEach(() => {
    axios.post.mockReset()
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  it('markiert hohe Priorität mit pw-prio-hoch und tiefe mit pw-prio-tief; mittel/nicht gesetzt ohne Prio-Klasse', async () => {
    const wrapper = shallowMount(Geschaeftsliste, { props: { mitglieder: [] } })
    axios.get.mockResolvedValue({ data: [
      { id: 1, titel: 'Hoch', nummer: '2026.1', status: 'Pendent', datum: '2026-01-04', prioritaet: 'hoch' },
      { id: 2, titel: 'Tief', nummer: '2026.2', status: 'Pendent', datum: '2026-01-03', prioritaet: 'tief' },
      { id: 3, titel: 'Mittel', nummer: '2026.3', status: 'Pendent', datum: '2026-01-02', prioritaet: 'mittel' },
      { id: 4, titel: 'Ohne', nummer: '2026.4', status: 'Pendent', datum: '2026-01-01', prioritaet: '' },
    ] })
    await wrapper.vm.ladeGeschaefte()
    await wrapper.vm.$nextTick()

    const rows = wrapper.findAll('tbody tr')
    expect(rows).toHaveLength(4)
    expect(rows[0].classes()).toContain('pw-prio-hoch')
    expect(rows[1].classes()).toContain('pw-prio-tief')
    expect(rows[2].classes()).not.toContain('pw-prio-hoch')
    expect(rows[2].classes()).not.toContain('pw-prio-tief')
    expect(rows[3].classes()).not.toContain('pw-prio-hoch')
    expect(rows[3].classes()).not.toContain('pw-prio-tief')
  })

  it('filtert nach Priorität; nicht gesetzt zählt als «mittel»', async () => {
    const wrapper = shallowMount(Geschaeftsliste, { props: { mitglieder: [] } })
    axios.get.mockResolvedValue({ data: [
      { id: 1, status: 'Pendent', prioritaet: 'hoch' },
      { id: 2, status: 'Pendent', prioritaet: 'mittel' },
      { id: 3, status: 'Pendent', prioritaet: '' },
      { id: 4, status: 'Pendent', prioritaet: 'tief' },
    ] })
    await wrapper.vm.ladeGeschaefte()

    wrapper.vm.filterPrioritaet = ['mittel']
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id).sort()).toEqual([2, 3])

    wrapper.vm.filterPrioritaet = ['hoch']
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id)).toEqual([1])
  })

  it('speichert eine geänderte Priorität über den Prioritäts-Endpunkt', async () => {
    const wrapper = shallowMount(Geschaeftsliste, { props: { mitglieder: [] } })
    axios.get.mockResolvedValue({ data: [] })
    axios.put = axios.put || (() => {})
    let gerufen = null
    axios.put = (url, body) => { gerufen = { url, body }; return Promise.resolve({ data: {} }) }

    await wrapper.vm.aenderungPrioritaet({ id: 7 }, 'hoch')
    expect(gerufen.url).toContain('/apps/parlwin/geschaefte/7/prioritaet')
    expect(gerufen.body).toEqual({ prioritaet: 'hoch' })
  })

  it('bindet die Prioritätsauswahl an den ROHWERT (nicht «Mittel»): nicht gesetzt bleibt leer', async () => {
    const wrapper = shallowMount(Geschaeftsliste, { props: { mitglieder: [] } })
    axios.get.mockResolvedValue({ data: [
      { id: 1, status: 'Pendent', prioritaet: '' },
      { id: 2, status: 'Pendent', prioritaet: 'hoch' },
    ] })
    await wrapper.vm.ladeGeschaefte()
    await wrapper.vm.$nextTick()
    // Das geteilte PwPrioritaetSelect bekommt den Rohwert; «nicht gesetzt» bleibt
    // leer (das Widget zeigt dann keine Auswahl), es wird kein «mittel» erfunden.
    const werte = wrapper.findAllComponents(PwPrioritaetSelect).map(s => s.props('modelValue'))
    expect(werte.length).toBeGreaterThan(0)
    expect(werte).toContain('')
    expect(werte).toContain('hoch')
    expect(werte).not.toContain('mittel')
  })

  it('definiert die Prioritäts-Stile (Hervorhebung hoch, Abschwächung tief)', () => {
    const css = readFileSync(stylePath, 'utf8')
    expect(css).toMatch(/--pw-prio-hoch-bg:/)
    expect(css).toMatch(/--pw-prio-tief-opacity:/)
    expect(css).toMatch(/\.pw-prio-hoch/)
    expect(css).toMatch(/\.pw-prio-tief\s*\{[^}]*opacity:\s*var\(--pw-prio-tief-opacity\)/)
  })
})
