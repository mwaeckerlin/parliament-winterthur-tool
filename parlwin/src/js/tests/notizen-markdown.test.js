import { describe, it, expect } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import { markdownZuHtml } from '../utils'
import NotizenListe from '../components/NotizenListe.vue'

describe('markdownZuHtml', () => {
  it('rendert Markdown nach HTML', () => {
    const html = markdownZuHtml('**fett** und *kursiv*')
    expect(html).toContain('<strong>fett</strong>')
    expect(html).toContain('<em>kursiv</em>')
  })

  it('säubert gefährliches HTML (XSS-Schutz)', () => {
    const html = markdownZuHtml('Hallo <script>alert(1)<\/script>')
    expect(html).not.toContain('<script>')
  })

  it('liefert leeren String für leere Eingabe', () => {
    expect(markdownZuHtml('')).toBe('')
    expect(markdownZuHtml(null)).toBe('')
  })
})

// Die EINE geteilte Notiz-Komponente (NotizenListe) zeigt Notizen überall gleich
// — inkl. Sitzungs-/Traktandum-Notizen seit der Vereinheitlichung. Der Text wird
// als gerendertes Markdown angezeigt, nie als Rohtext.
describe('NotizenListe zeigt Notizen als gerendertes Markdown', () => {
  it('rendert eine Markdown-Notiz formatiert (nicht als Rohtext)', () => {
    const wrapper = shallowMount(NotizenListe, {
      props: {
        basisUrl: 'sitzungen/1',
        notizen: [{ id: 1, text: '**wichtig**', autorUid: 'other', autorName: 'Andere' }],
        aktuelleUid: 'me',
      },
    })
    const html = wrapper.find('.pw-notiz-inhalt').html()
    expect(html).toContain('<strong>wichtig</strong>')
  })
})
