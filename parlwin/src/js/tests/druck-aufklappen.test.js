import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Kommissionsliste from '../components/Kommissionsliste.vue'

/**
 * Was am Schirm eingeklappt ist, steht auf dem Papier offen.
 *
 * Befund aus dem Design-Test vom 16.10.2026 am gedruckten A4 quer: Auf der
 * Karte jeder Kommission stand «Mitglieder (12):» und darunter nichts. Die
 * Liste ist am Schirm eingeklappt, und eingeklappt heisst hier: Sie steht gar
 * nicht im Dokument, also kann kein Stylesheet sie auf das Papier holen. Der
 * Druck hat vorher aufzuklappen und danach den Zustand des Anwenders
 * zurückzugeben.
 *
 * vitest läuft im Projekt-Root.
 */
vi.mock('../api.js', () => ({
  getKommissionen: () => Promise.resolve([
    { id: 1, name: 'Aufsichtskommission', aktiv: true, mitglieder: [] },
    { id: 2, name: 'Sachkommission Stadtbau', aktiv: true, mitglieder: [] },
  ]),
  getGeschaefte: () => Promise.resolve([]),
}))
vi.mock('../realtime.js', () => ({ subscribeRealtime: () => () => {} }))

describe('Drucken klappt auf', () => {
  let komponente

  beforeEach(async () => {
    komponente = mount(Kommissionsliste, {
      global: { stubs: { NcTextField: true, NcSelect: true, NcCheckboxRadioSwitch: true, NcEmptyContent: true, NcLoadingIcon: true, FilterPanel: true, Teleport: true } },
    })
    await komponente.vm.$nextTick()
  })

  it('vor dem Druck stehen alle Mitgliederlisten offen', () => {
    komponente.vm.kommissionen = [{ id: 1 }, { id: 2 }]
    komponente.vm.offeneMitglieder = []
    window.dispatchEvent(new Event('beforeprint'))
    expect(komponente.vm.offeneMitglieder, 'der Druck klappt nicht auf').toEqual([1, 2])
  })

  it('nach dem Druck steht wieder der Zustand des Anwenders da', () => {
    komponente.vm.kommissionen = [{ id: 1 }, { id: 2 }]
    komponente.vm.offeneMitglieder = [2]
    window.dispatchEvent(new Event('beforeprint'))
    window.dispatchEvent(new Event('afterprint'))
    expect(komponente.vm.offeneMitglieder, 'der Druck lässt die Karten offen stehen').toEqual([2])
  })
})
