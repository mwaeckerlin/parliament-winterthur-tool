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
    axios.get.mockResolvedValue({ data: [{ id: 7, titel: '2026.15V', markdown: '# Schriftliche Anfrage\n\nWelche Immobilien sind Renditeobjekte?\n' }] })
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
