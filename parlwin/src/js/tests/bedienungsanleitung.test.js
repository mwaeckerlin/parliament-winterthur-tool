import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Bedienungsanleitung from '../components/Bedienungsanleitung.vue'
import App from '../App.vue'

// Neue Seite «Bedienungsanleitung»: rendert das README des Projekts als
// formatiertes Markdown und steht in der Navigation direkt vor «Änderungsverlauf».
describe('Bedienungsanleitung', () => {
  it('steht als Navigationspunkt direkt vor «Änderungsverlauf»', () => {
    const keys = App.data().ansichten.map(a => a.key)
    expect(keys.indexOf('anleitung')).toBe(keys.indexOf('changelog') - 1)
    const item = App.data().ansichten.find(a => a.key === 'anleitung')
    expect(item.bezeichnung).toBe('Bedienungsanleitung')
  })

  it('rendert das README als formatiertes Markdown (Überschriften, Fett, Listen)', () => {
    const w = mount(Bedienungsanleitung)
    const html = w.find('.pw-anleitung-inhalt').html()
    expect(html).toContain('<h1')
    expect(html).toContain('<h2')
    expect(html).toContain('<strong>Einleitungssatz</strong>')
    expect(html).toContain('<li>')
  })
})
