import { describe, it, expect, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import Vorstoesseliste from '../components/Vorstoesseliste.vue'
import axios from '@nextcloud/axios'

// Die Vorstoss-Übersicht folgt dem Muster der Geschäfte: Klick auf die Karte
// öffnet die Bearbeitung. Das Löschen bleibt als eigener Knopf auf der Karte
// erreichbar — wie bei den Sitzungstypen.
describe('Vorstoesseliste — Übersicht wie die Geschäfte', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  const mount = () => shallowMount(Vorstoesseliste, { props: { mitglieder: [], fraktionen: [] } })

  it('rendert Vorstösse als klickbare Datenkarten (pw-data-card)', async () => {
    const wrapper = mount()
    axios.get.mockResolvedValue({ data: [{ id: 5, titel: 'Mehr Velowege', herkunft: 'eigene', status: 'neu', art: 'Motion' }] })
    await wrapper.vm.lade()
    await wrapper.vm.$nextTick()

    const karte = wrapper.find('.pw-data-card')
    expect(karte.exists()).toBe(true)
    expect(karte.attributes('role')).toBe('button')
    expect(karte.text()).toContain('Mehr Velowege')
  })

  it('Klick auf die Karte öffnet die Bearbeitung', async () => {
    const wrapper = mount()
    axios.get.mockResolvedValue({ data: [{ id: 5, titel: 'Mehr Velowege', herkunft: 'eigene', status: 'neu' }] })
    await wrapper.vm.lade()
    await wrapper.vm.$nextTick()

    await wrapper.find('.pw-data-card').trigger('click')
    expect(wrapper.vm.bearbeitung).not.toBeNull()
    expect(wrapper.vm.bearbeitung.id).toBe(5)
  })

  it('bietet auf der Karte einen Löschen-Weg an', async () => {
    const wrapper = mount()
    axios.get.mockResolvedValue({ data: [{ id: 5, titel: 'Mehr Velowege', herkunft: 'eigene', status: 'neu' }] })
    await wrapper.vm.lade()
    await wrapper.vm.$nextTick()

    expect(typeof wrapper.vm.loeschen, 'Der Löschen-Weg fehlt').toBe('function')
    expect(
      wrapper.find('.pw-data-card .pw-data-card-aktionen').exists(),
      'Kein Löschen-Knopf auf der Vorstoss-Karte',
    ).toBe(true)
  })

  it('hebt hohe Priorität in der Karte hervor, tiefe schwächt sie ab', async () => {
    const wrapper = mount()
    axios.get.mockResolvedValue({ data: [
      { id: 1, titel: 'Hoch', herkunft: 'eigene', status: 'neu', prioritaet: 'hoch' },
      { id: 2, titel: 'Tief', herkunft: 'eigene', status: 'neu', prioritaet: 'tief' },
      { id: 3, titel: 'Ohne', herkunft: 'eigene', status: 'neu', prioritaet: '' },
    ] })
    await wrapper.vm.lade()
    await wrapper.vm.$nextTick()
    const karten = wrapper.findAll('.pw-data-card')
    expect(karten[0].classes()).toContain('pw-prio-hoch')
    expect(karten[1].classes()).toContain('pw-prio-tief')
    expect(karten[2].classes()).not.toContain('pw-prio-hoch')
    expect(karten[2].classes()).not.toContain('pw-prio-tief')
  })

  it('lädt die Priorität beim Bearbeiten (ohne Wert bleibt sie undefiniert)', async () => {
    const wrapper = mount()
    wrapper.vm.bearbeiten({ id: 5, titel: 'X', prioritaet: 'tief' })
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.bearbeitung.prioritaet).toBe('tief')
    wrapper.vm.bearbeiten({ id: 6, titel: 'Y' })
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.bearbeitung.prioritaet).toBe('')
  })
})

// F119: Die Übersicht der Vorstösse ist gebaut wie die der Geschäfte — auf
// breiten Fenstern eine Tabelle, auf schmalen Karten. Die wichtigsten Angaben
// stehen in der Zeile, alles Weitere erst in der geöffneten Maske.
describe('Vorstoesseliste — Tabelle wie bei den Geschäften (F119)', () => {
  beforeEach(() => {
    axios.get.mockReset().mockResolvedValue({ data: [] })
  })

  const vorstoss = {
    id: 5,
    titel: 'V19-2026 08 24 PO Areal Maienried v2',
    art: 'Postulat',
    herkunft: 'fremde',
    herkunftFraktion: 'Grüne/Alternative Linke-Fraktion',
    status: 'neu',
    prioritaet: 'hoch',
    beschluss: 'unterstützen',
    zustaendigkeit: [{ key: 'a', name: 'Anna Beispiel' }],
    erstelltAm: '2026-08-24 10:00:00',
  }

  async function geladen(daten = [vorstoss]) {
    const wrapper = shallowMount(Vorstoesseliste, { props: { mitglieder: [], fraktionen: [] } })
    axios.get.mockResolvedValue({ data: daten })
    await wrapper.vm.lade()
    await wrapper.vm.$nextTick()
    return wrapper
  }

  it('zeigt auf breiten Fenstern eine Tabelle, auf schmalen die Karten', async () => {
    const w = await geladen()
    expect(w.find('.pw-table-desktop .pw-tabelle-vorstoesse').exists(), 'die Tabelle fehlt (F119)').toBe(true)
    expect(w.find('.pw-card-mobile .pw-data-card').exists(), 'die Karten für schmale Fenster fehlen (F119)').toBe(true)
  })

  it('führt dieselben Spalten wie die Geschäfte', async () => {
    const w = await geladen()
    const koepfe = w.findAll('.pw-tabelle-vorstoesse thead th').map(th => th.text())
    // Die letzte Spalte trägt nur den Löschknopf und darum keine Beschriftung.
    expect(koepfe).toEqual(['Art', 'Titel', 'Prio', 'Status', 'Zuständig', 'Beschluss', ''])
  })

  it('zeigt den Titel und die tragenden Angaben in der Zeile', async () => {
    const w = await geladen()
    const zeile = w.find('.pw-tabelle-vorstoesse tbody tr')
    expect(zeile.find('.pw-col-titel').text(), 'der Titel fehlt in der Zeile (F119)')
      .toContain('V19-2026 08 24 PO Areal Maienried v2')
    // Die Art steht in der ersten Spalte, darunter Herkunft und Datum — wie bei
    // den Geschäften die Nummer mit Datum und Typ.
    expect(zeile.find('.pw-col-nr').text()).toContain('Postulat')
    expect(zeile.find('.pw-col-nr').text()).toContain('Fremde')
    // Die Herkunftsfraktion gehört zum Titel wie der Erstunterzeichner beim
    // Geschäft. Angezeigt wird sie gekürzt; ohne konfiguriertes Kürzel — wie
    // hier — steht der volle Name.
    expect(zeile.find('.pw-col-herkunftsfraktion').text()).toBe('Grüne/Alternative Linke-Fraktion')
  })

  it('macht die Zeile anklickbar und öffnet damit die Maske', async () => {
    const w = await geladen()
    const zeile = w.find('.pw-tabelle-vorstoesse tbody tr')
    expect(zeile.attributes('role')).toBe('button')
    await zeile.trigger('click')
    expect(w.vm.bearbeitung, 'der Klick auf die Zeile öffnet die Maske nicht (F119)').not.toBeNull()
    expect(w.vm.bearbeitung.id).toBe(5)
  })

  it('hebt die Priorität in der Zeile hervor wie bei den Geschäften', async () => {
    const w = await geladen([
      { ...vorstoss, id: 1, prioritaet: 'hoch' },
      { ...vorstoss, id: 2, prioritaet: 'tief' },
      { ...vorstoss, id: 3, prioritaet: '' },
    ])
    const zeilen = w.findAll('.pw-tabelle-vorstoesse tbody tr')
    expect(zeilen[0].classes()).toContain('pw-prio-hoch')
    expect(zeilen[1].classes()).toContain('pw-prio-tief')
    expect(zeilen[2].classes()).not.toContain('pw-prio-hoch')
  })

  it('bietet das Löschen auch in der Tabellenzeile an', async () => {
    // Auf breiten Fenstern sind die Karten verborgen; ohne einen Löschweg in
    // der Zeile wäre die Funktion dort unerreichbar (F67).
    const w = await geladen()
    const zeile = w.find('.pw-tabelle-vorstoesse tbody tr')
    // Gesucht wird die Zelle, nicht die Klasse des Knopfes: shallowMount ersetzt
    // PwLoeschen durch einen Stub, der die Klasse nicht trägt.
    expect(zeile.find('.pw-col-loeschen').exists(), 'kein Löschen-Weg in der Zeile (F119)').toBe(true)
    expect(zeile.find('.pw-col-loeschen').html()).toContain('pw-loeschen')
  })

  it('zeigt bei einem eigenen Vorstoss keine Herkunftsfraktion', async () => {
    const w = await geladen([{ ...vorstoss, herkunft: 'eigene', herkunftFraktion: '' }])
    const zeile = w.find('.pw-tabelle-vorstoesse tbody tr')
    expect(zeile.find('.pw-col-nr').text()).toContain('Eigene')
    expect(zeile.find('.pw-col-herkunftsfraktion').exists()).toBe(false)
  })
})
