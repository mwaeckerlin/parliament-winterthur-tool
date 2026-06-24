import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Changelog from '../components/Changelog.vue'
import App from '../App.vue'

describe('Changelog-Ansicht', () => {
  it('rendert den Changelog als formatiertes HTML (nicht als Rohtext)', () => {
    const wrapper = mount(Changelog)
    const html = wrapper.find('.pw-changelog-inhalt').html()
    expect(html).toContain('<strong>wichtig</strong>')
    expect(html).toContain('1.8.0')
    // Rohes Markdown darf nicht sichtbar sein
    expect(html).not.toContain('**wichtig**')
  })

  it('App bietet ein Changelog-Tab als unterstes Navigations-Element', () => {
    const ansichten = App.data().ansichten
    expect(ansichten.at(-1).key).toBe('changelog')
  })
})
