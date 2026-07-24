import { describe, it, expect, beforeEach, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Geschaeftsliste from '../components/Geschaeftsliste.vue'
import axios from '@nextcloud/axios'

// Die Geschäftsliste filtert nach Status, Typ, Zuständigkeit, Beschluss und
// Suche; erledigte sind standardmässig ausgeblendet; sortiert wird natürlich
// (2026.9 vor 2026.10). «Eigenes Geschäft erstellen» öffnet direkt das Detail.
describe('Geschaeftsliste — Filter, Sortierung, Eigenes Geschäft', () => {
  beforeEach(() => {
    axios.post.mockReset()
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  const daten = [
    { id: 1, nummer: '2026.9', titel: 'Velowege', typ: 'Motion', status: 'Pendent', datum: '2026-01-01', hauptZustaendigePerson: 'Anna Müller', letzterBeschluss: { aktionCode: 'unterstuetzen' } },
    { id: 2, nummer: '2026.10', titel: 'Schulhaus', typ: 'Kreditantrag', status: 'Pendent', datum: '2026-01-02', hauptZustaendigePerson: 'Bob Meier' },
    { id: 3, nummer: '2025.5', titel: 'Altlast', typ: 'Motion', status: 'Erledigt', datum: '2025-01-01' },
  ]

  const mountMit = async (liste) => {
    const wrapper = shallowMount(Geschaeftsliste, { props: { mitglieder: [] } })
    axios.get.mockResolvedValue({ data: liste })
    await wrapper.vm.ladeGeschaefte()
    await wrapper.vm.$nextTick()
    return wrapper
  }

  it('blendet erledigte Geschäfte standardmässig aus (auch «abgeschlossen»/«aufgehoben»)', async () => {
    const wrapper = await mountMit([...daten, { id: 4, nummer: '2024.1', titel: 'Alt', status: 'Durch Rechtsmittelinstanz aufgehoben', datum: '2024-01-01' }])
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id).sort()).toEqual([1, 2])
    wrapper.vm.zeigeErledigte = true
    // zeigeErledigte lädt neu vom Server — die Filterlogik selbst lässt dann alle durch.
    expect(wrapper.vm.gefilterteGeschaefte).toHaveLength(4)
  })

  it('filtert nach Status, Typ, Zuständigkeit und Beschluss (Mehrfachauswahl)', async () => {
    const wrapper = await mountMit(daten)

    wrapper.vm.filterStatus = [{ label: 'Pendent', value: 'Pendent' }]
    wrapper.vm.filterTyp = ['Motion']
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id)).toEqual([1])

    wrapper.vm.filterStatus = []
    wrapper.vm.filterTyp = []
    wrapper.vm.filterZustaendige = ['Bob Meier']
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id)).toEqual([2])

    wrapper.vm.filterZustaendige = []
    wrapper.vm.filterBeschluss = ['unterstuetzen']
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id)).toEqual([1])
  })

  it('sucht über Nummer und Titel', async () => {
    const wrapper = await mountMit(daten)
    wrapper.vm.suche = 'schulh'
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id)).toEqual([2])
    wrapper.vm.suche = '2026.9'
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id)).toEqual([1])
  })

  it('sortiert Nummern natürlich: 2026.9 vor 2026.10', async () => {
    const wrapper = await mountMit([daten[1], daten[0]])
    wrapper.vm.sortFeld = 'nummer'
    wrapper.vm.sortRichtung = 'asc'
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.nummer)).toEqual(['2026.9', '2026.10'])
    wrapper.vm.sortiereNach('nummer') // gleiches Feld erneut → Richtung dreht
    expect(wrapper.vm.sortRichtung).toBe('desc')
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.nummer)).toEqual(['2026.10', '2026.9'])
  })

  it('«+ Eigenes Geschäft» öffnet dieselbe Maske und legt noch nichts an', async () => {
    const wrapper = await mountMit([])
    axios.post.mockClear()

    wrapper.vm.neuesGeschaeftOeffnen()

    // Die Maske ist offen (ID 0 = Anlege-Modus), angelegt wurde noch nichts.
    expect(wrapper.vm.neuesGeschaeft).toBe(true)
    expect(wrapper.vm.ausgewaehlteGeschaeftId).toBe(0)
    expect(wrapper.vm.detailOffen).toBe(true)
    expect(
      axios.post.mock.calls.filter(c => String(c[0]).endsWith('/apps/parlwin/geschaefte')),
      'Es wurde angelegt, bevor gespeichert wurde',
    ).toHaveLength(0)
  })

  it('nach dem Speichern geht dieselbe Maske in die Bearbeitung über', async () => {
    const wrapper = await mountMit([])
    wrapper.vm.neuesGeschaeftOeffnen()

    await wrapper.vm.nachErstellen(77)

    expect(wrapper.vm.neuesGeschaeft).toBe(false)
    expect(wrapper.vm.ausgewaehlteGeschaeftId).toBe(77)
  })

  it('«Abbrechen» schliesst die Neu-Maske, ein Klick daneben nicht', async () => {
    const wrapper = await mountMit([])
    wrapper.vm.neuesGeschaeftOeffnen()

    // Klick neben die Maske darf beim Erfassen nichts verwerfen.
    wrapper.vm.overlayKlick()
    expect(wrapper.vm.detailOffen, 'Ein Klick daneben hat die Neu-Maske geschlossen').toBe(true)

    wrapper.vm.neuAbbrechen()
    expect(wrapper.vm.detailOffen).toBe(false)
  })
})
