import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
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

describe('NotizenListe zeigt Notizen als gerendertes Markdown', () => {
  it('rendert eine Markdown-Notiz formatiert (nicht als Rohtext)', () => {
    const wrapper = mount(NotizenListe, {
      props: { modelValue: [{ text: '**wichtig**', uid: 'other', displayName: 'Andere' }] },
    })
    const html = wrapper.find('.pw-notiz-text').html()
    expect(html).toContain('<strong>wichtig</strong>')
  })
})
