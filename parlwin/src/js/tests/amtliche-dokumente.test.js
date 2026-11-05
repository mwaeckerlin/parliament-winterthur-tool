import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from '@nextcloud/axios'
import PwAmtlicheDokumente from '../components/PwAmtlicheDokumente.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))

function dokumente() {
  return [
    { id: 7, titel: '2026.15V', kategorie: 'Vorstoss', datum: '2026-03-02', seiten: 2, url: 'https://parlament.winterthur.ch/_doc/6821653', hatInhalt: true },
    { id: 8, titel: 'Antwort Stadtrat', kategorie: 'Antwort', datum: '2026-05-04', seiten: 4, url: 'https://parlament.winterthur.ch/_doc/6900000', hatInhalt: false, fehler: 'Das Dokument ist 80.0 MB gross' },
  ]
}

// F121: Die amtlichen Dokumente stehen eingeklappt mit ihrem Titel; ein Klick
// zeigt den gelesenen Inhalt — in der Übersicht wie im geöffneten Geschäft.
describe('PwAmtlicheDokumente', () => {
  beforeEach(() => {
    axios.get.mockReset()
    const vorstoss = { id: 7, titel: '2026.15V', markdown: '# Schriftliche Anfrage\n\nWelche Immobilien sind Renditeobjekte?\n' }
    // Die Liste aller Dokumente eines Geschäfts und das einzelne Dokument.
    axios.get.mockImplementation((url) => Promise.resolve({
      data: String(url).endsWith('/amtliche-dokumente/7') ? vorstoss : [vorstoss],
    }))
  })

  // F149: Ein Budgetantrag misst mehrere hundert Kilobyte; geholt wird nur
  // das Dokument, das aufgeklappt wird.
  it('holt beim Aufklappen nur dieses Dokument über seine eigene Adresse', async () => {
    const w = mount(PwAmtlicheDokumente, { props: { geschaeftId: 42, dokumente: dokumente() } })
    await w.findAll('.pw-dokument-kopf')[0].trigger('click')
    await flushPromises()
    expect(axios.get).toHaveBeenCalledTimes(1)
    expect(String(axios.get.mock.calls[0][0])).toMatch(/\/apps\/parlwin\/geschaefte\/42\/amtliche-dokumente\/7$/)
    expect(w.html()).toContain('Renditeobjekte')
  })

  it('zeigt eingeklappt nur Titel, Kategorie und Datum', () => {
    const w = mount(PwAmtlicheDokumente, { props: { geschaeftId: 42, dokumente: dokumente() } })
    const html = w.html()
    expect(html).toContain('2026.15V')
    expect(html).toContain('Vorstoss')
    expect(html).toContain('2.3.2026')
    expect(html).not.toContain('Renditeobjekte')
    expect(w.findAll('.pw-dokument-inhalt').length).toBe(0)
  })

  it('zeigt nach dem Klick den Inhalt des Dokuments', async () => {
    const w = mount(PwAmtlicheDokumente, { props: { geschaeftId: 42, dokumente: dokumente() } })
    await w.findAll('.pw-dokument-kopf')[0].trigger('click')
    await flushPromises()
    expect(axios.get).toHaveBeenCalledTimes(1)
    expect(w.html()).toContain('Renditeobjekte')
    expect(w.findAll('.pw-dokument-inhalt').length).toBe(1)
  })

  it('lädt den Inhalt nur einmal und klappt beim zweiten Klick wieder zu', async () => {
    const w = mount(PwAmtlicheDokumente, { props: { geschaeftId: 42, dokumente: dokumente() } })
    const kopf = w.findAll('.pw-dokument-kopf')[0]
    await kopf.trigger('click')
    await flushPromises()
    await kopf.trigger('click')
    await flushPromises()
    expect(w.findAll('.pw-dokument-inhalt').length).toBe(0)
    await kopf.trigger('click')
    await flushPromises()
    expect(axios.get).toHaveBeenCalledTimes(1)
    expect(w.html()).toContain('Renditeobjekte')
  })

  it('holt bei «auf, zu, auf» vor der Antwort nur einmal und zeigt danach den Inhalt', async () => {
    let freigeben
    axios.get.mockImplementation(() => new Promise((weiter) => {
      freigeben = () => weiter({ data: { id: 7, markdown: 'Welche Immobilien sind Renditeobjekte?' } })
    }))
    const w = mount(PwAmtlicheDokumente, { props: { geschaeftId: 42, dokumente: dokumente() } })
    const kopf = w.findAll('.pw-dokument-kopf')[0]
    await kopf.trigger('click')
    await kopf.trigger('click')
    await kopf.trigger('click')
    expect(axios.get, 'das zweite Aufklappen holt das Dokument noch einmal').toHaveBeenCalledTimes(1)
    expect(w.find('.pw-dokument-inhalt .pw-laden').exists(), 'der Ladezustand fehlt beim zweiten Aufklappen').toBe(true)
    freigeben()
    await flushPromises()
    expect(w.html()).toContain('Renditeobjekte')
    expect(w.find('.pw-dokument-inhalt .pw-laden').exists()).toBe(false)
  })

  it('nennt den Fehler statt einer leeren Fläche, wenn das Dokument nicht gelesen wurde', async () => {
    const w = mount(PwAmtlicheDokumente, { props: { geschaeftId: 42, dokumente: dokumente() } })
    await w.findAll('.pw-dokument-kopf')[1].trigger('click')
    await flushPromises()
    expect(axios.get).not.toHaveBeenCalled()
    expect(w.html()).toContain('80.0 MB')
    expect(w.html()).toContain('_doc/6900000')
  })

  it('bleibt leer, wenn das Geschäft keine amtlichen Dokumente hat', () => {
    const w = mount(PwAmtlicheDokumente, { props: { geschaeftId: 42, dokumente: [] } })
    expect(w.find('.pw-amtliche-dokumente').exists()).toBe(false)
  })
})
