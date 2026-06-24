import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Changelog from '../components/Changelog.vue'
import App from '../App.vue'

describe('Changelog-Ansicht', () => {
  it('nutzt dieselbe View-Struktur wie alle anderen Seiten', () => {
    const wrapper = mount(Changelog)
    // Gemeinsame Hülle (identisch zu Kommissionen/Mitglieder/…)
    expect(wrapper.find('.pw-view-content').exists()).toBe(true)
    expect(wrapper.find('.pw-view-header').exists()).toBe(true)
    expect(wrapper.find('.pw-view-title').text()).toBe('Änderungsverlauf')
    expect(wrapper.find('.pw-card-grid').exists()).toBe(true)
    expect(wrapper.find('.pw-data-card').exists()).toBe(true)
    // Kein eigenes Changelog-Layout/Markdown-HTML
    expect(wrapper.find('.pw-changelog-inhalt').exists()).toBe(false)
  })

  it('parst Version und Einträge aus dem Changelog', () => {
    const wrapper = mount(Changelog)
    expect(wrapper.text()).toContain('1.8.0')
    expect(wrapper.text()).toContain('wichtig')
    // Markdown-Sternchen werden entfernt, nicht als Rohtext gezeigt
    expect(wrapper.text()).not.toContain('**wichtig**')
  })

  it('rendert die Einträge als formatiertes Markdown (Liste + fett)', () => {
    const wrapper = mount(Changelog)
    const inhalt = wrapper.find('.pw-changelog-eintraege')
    expect(inhalt.exists()).toBe(true)
    const html = inhalt.html()
    expect(html).toContain('<ul>')
    expect(html).toContain('<li>')
    expect(html).toContain('<strong>wichtig</strong>')
  })

  it('klappt Versionen wie eine Handorgel auf und zu', async () => {
    const wrapper = mount(Changelog)
    // Neueste Version ist standardmässig offen → Eintragsliste sichtbar
    expect(wrapper.find('.pw-data-card ul').exists()).toBe(true)
    await wrapper.find('.pw-data-card-header').trigger('click')
    // Zugeklappt → keine Eintragsliste mehr
    expect(wrapper.find('.pw-data-card ul').exists()).toBe(false)
  })

  it('App bietet ein Changelog-Tab als unterstes Navigations-Element', () => {
    const ansichten = App.data().ansichten
    expect(ansichten.at(-1).key).toBe('changelog')
  })
})
