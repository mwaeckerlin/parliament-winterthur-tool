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

  // Bug: Ein fremdes Realtime-Update darf keinen Lade-Zustand auslösen (sonst
  // verschwindet die Liste kurz und Scrollbalken/Fokus springen bei allen).
  it('lädt bei einem Realtime-Update ohne Lade-Flackern (kein Scroll-/Fokus-Springen)', async () => {
    const wrapper = await mountMit(daten)
    const spy = vi.spyOn(wrapper.vm, 'ladeGeschaefte')
    wrapper.vm.handleRealtimeEvent({ type: 'geschaefte.updated' })
    await new Promise(r => setTimeout(r, 300))
    expect(spy, 'Realtime hat keinen Reload ausgelöst').toHaveBeenCalled()
    expect(
      spy.mock.calls.every(c => c[0] === false),
      'Realtime-Reload lief mit Lade-Zustand (verursacht das Scroll-Springen)',
    ).toBe(true)
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

// F71: Filter nach Einreicher (Person, mit Toggle «Nur Ersteinreicher») und nach Partei.
describe('Geschaeftsliste — Filter nach Einreicher und Partei', () => {
  beforeEach(() => {
    axios.post.mockReset()
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  const mitglieder = [
    { name: 'Anna Müller', partei: 'SP', externId: '10' },
    { name: 'Bob Meier', partei: 'FDP', externId: '20' },
    { name: 'Clara Weiss', partei: 'SP', externId: '30' },
  ]
  const daten = [
    { id: 1, nummer: '2026.1', titel: 'A', status: 'Pendent', datum: '2026-01-01', einreicher: [{ name: 'Anna Müller', rolle: 'Erstunterzeichner', externId: '10' }, { name: 'Bob Meier', rolle: 'Mitunterzeichner', externId: '20' }] },
    { id: 2, nummer: '2026.2', titel: 'B', status: 'Pendent', datum: '2026-01-02', einreicher: [{ name: 'Bob Meier', rolle: 'Erstunterzeichner', externId: '20' }] },
    { id: 3, nummer: '2026.3', titel: 'C', status: 'Pendent', datum: '2026-01-03', einreicher: [{ name: 'Clara Weiss', rolle: 'Erstunterzeichner', externId: '30' }] },
  ]

  const mount = async () => {
    const wrapper = shallowMount(Geschaeftsliste, { props: { mitglieder } })
    axios.get.mockResolvedValue({ data: daten })
    await wrapper.vm.ladeGeschaefte()
    await wrapper.vm.$nextTick()
    return wrapper
  }

  it('bietet die Einreicher-Namen und die vorkommenden Parteien zur Auswahl', async () => {
    const wrapper = await mount()
    expect(wrapper.vm.einreicherOptionen).toEqual(['Anna Müller', 'Bob Meier', 'Clara Weiss'])
    expect(wrapper.vm.parteiOptionen).toEqual(['FDP', 'SP'])
  })

  it('filtert nach Einreicher-Person: standardmässig zählt jeder Einreicher', async () => {
    const wrapper = await mount()
    wrapper.vm.filterEinreicher = ['Bob Meier']
    // Bob ist Erst- (2) und Mitunterzeichner (1).
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id).sort()).toEqual([1, 2])
  })

  it('«Nur Ersteinreicher» beschränkt den Person-Filter auf den Erstunterzeichner', async () => {
    const wrapper = await mount()
    wrapper.vm.filterEinreicher = ['Bob Meier']
    wrapper.vm.nurErsteinreicher = true
    // Nur Geschäft 2 hat Bob als Erstunterzeichner (bei 1 ist Anna die erste).
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id)).toEqual([2])
  })

  it('filtert nach Partei über die Einreicher (via Mitglieder aufgelöst)', async () => {
    const wrapper = await mount()
    wrapper.vm.filterPartei = ['SP']
    // SP-Einreicher: Anna (1) und Clara (3); Geschäft 2 nur FDP.
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id).sort()).toEqual([1, 3])
  })

  it('«Filter zurücksetzen» leert Einreicher, Partei und den Ersteinreicher-Schalter', async () => {
    const wrapper = await mount()
    wrapper.vm.filterEinreicher = ['Bob Meier']
    wrapper.vm.filterPartei = ['SP']
    wrapper.vm.nurErsteinreicher = true
    wrapper.vm.resetFilter()
    expect(wrapper.vm.filterEinreicher).toEqual([])
    expect(wrapper.vm.filterPartei).toEqual([])
    expect(wrapper.vm.nurErsteinreicher).toBe(false)
  })
})

// Grundprinzip: ein Filter bietet GENAU die Werte an, die in den Daten tatsächlich
// vorkommen — nicht die ganze Domäne. Alles andere ist unnützer Ballast und führt
// zu leeren Filtertreffern.
describe('Geschaeftsliste — Filter bieten nur tatsächlich vorkommende Werte', () => {
  beforeEach(() => {
    axios.post.mockReset()
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  const mitglieder = [
    { name: 'Anna Müller', aktiv: true },
    { name: 'Bob Meier', aktiv: true },
    { name: 'Nie Zugewiesen', aktiv: true },
  ]

  const mountMit = async (liste, mgl = mitglieder) => {
    const wrapper = shallowMount(Geschaeftsliste, { props: { mitglieder: mgl } })
    axios.get.mockResolvedValue({ data: liste })
    await wrapper.vm.ladeGeschaefte()
    await wrapper.vm.$nextTick()
    return wrapper
  }

  it('Zuständigkeitsfilter: zugewiesene Personen plus «Nicht zugewiesen», kein unzugewiesenes Mitglied', async () => {
    const wrapper = await mountMit([
      { id: 1, nummer: '2026.1', titel: 'A', status: 'Pendent', datum: '2026-01-01', hauptZustaendigePerson: 'Anna Müller' },
      { id: 2, nummer: '2026.2', titel: 'B', status: 'Pendent', datum: '2026-01-02', hauptZustaendigePerson: 'Bob Meier' },
      { id: 3, nummer: '2026.3', titel: 'C', status: 'Pendent', datum: '2026-01-03', hauptZustaendigePerson: '' },
    ])
    const werte = wrapper.vm.zustaendigeOptionen.map(o => o.value)
    const labels = wrapper.vm.zustaendigeOptionen.map(o => o.label)
    // Zugewiesene Personen UND «Nicht zugewiesen» (leerer Wert = value '') — der
    // leere Wert kommt in den Daten vor (id 3) und ist ein echter Filterwert.
    expect(werte).toEqual(['', 'Anna Müller', 'Bob Meier'])
    expect(labels).toContain('Nicht zugewiesen')
    // «Nie Zugewiesen» ist Mitglied, aber an keinem Geschäft zuständig → NICHT im Filter.
    expect(labels).not.toContain('Nie Zugewiesen')
  })

  it('Zuständigkeitsfilter «Nicht zugewiesen» trifft genau die Geschäfte ohne Zuständige', async () => {
    const wrapper = await mountMit([
      { id: 1, nummer: '2026.1', titel: 'A', status: 'Pendent', datum: '2026-01-01', hauptZustaendigePerson: 'Anna Müller' },
      { id: 2, nummer: '2026.2', titel: 'B', status: 'Pendent', datum: '2026-01-02', hauptZustaendigePerson: '' },
    ])
    wrapper.vm.filterZustaendige = ['']
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id)).toEqual([2])
  })

  it('Zuständigkeitsfilter bietet KEIN «Nicht zugewiesen», wenn alle Geschäfte zugewiesen sind', async () => {
    const wrapper = await mountMit([
      { id: 1, nummer: '2026.1', titel: 'A', status: 'Pendent', datum: '2026-01-01', hauptZustaendigePerson: 'Anna Müller' },
    ])
    expect(wrapper.vm.zustaendigeOptionen.map(o => o.value)).toEqual(['Anna Müller'])
  })

  it('Prioritätsfilter: vorkommende Stufen plus «Undefiniert» für Geschäfte ohne Priorität', async () => {
    const wrapper = await mountMit([
      { id: 1, nummer: '2026.1', titel: 'A', status: 'Pendent', datum: '2026-01-01' }, // keine Priorität
      { id: 2, nummer: '2026.2', titel: 'B', status: 'Pendent', datum: '2026-01-02', prioritaet: 'hoch' },
    ])
    // «Undefiniert» (value '') + «hoch»; «mittel»/«tief» kommen nicht vor.
    expect(wrapper.vm.prioritaetOptionen.map(o => o.value)).toEqual(['', 'hoch'])
    expect(wrapper.vm.prioritaetOptionen.map(o => o.label)).toContain('Undefiniert')
    expect(wrapper.vm.prioritaetOptionen.map(o => o.value)).not.toContain('tief')
    // «Undefiniert» trifft genau das Geschäft ohne gesetzte Priorität.
    wrapper.vm.filterPrioritaet = ['']
    expect(wrapper.vm.gefilterteGeschaefte.map(g => g.id)).toEqual([1])
  })

  it('Prioritätsfilter bietet KEIN «Undefiniert», wenn alle Geschäfte eine Priorität haben', async () => {
    const wrapper = await mountMit([
      { id: 1, nummer: '2026.1', titel: 'A', status: 'Pendent', datum: '2026-01-01', prioritaet: 'hoch' },
      { id: 2, nummer: '2026.2', titel: 'B', status: 'Pendent', datum: '2026-01-02', prioritaet: 'tief' },
    ])
    expect(wrapper.vm.prioritaetOptionen.map(o => o.value)).toEqual(['hoch', 'tief'])
  })
})
