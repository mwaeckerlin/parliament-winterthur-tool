import { describe, it, expect, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import App from '../App.vue'

// F107: Deep-Linking — eine Ansicht ist über den URL-Hash direkt aufrufbar
// (…/#budget), der Hash folgt der Navigation, und Vor/Zurück wird beachtet.
describe('Deep-Linking', () => {
  beforeEach(() => { window.location.hash = '' })

  it('öffnet die im URL-Hash genannte Ansicht direkt', () => {
    window.location.hash = '#budget'
    const w = shallowMount(App)
    expect(w.vm.aktiveAnsicht).toBe('budget')
  })

  it('ignoriert einen unbekannten Hash und bleibt beim Standardbereich', () => {
    window.location.hash = '#gibtsnicht'
    const w = shallowMount(App)
    expect(w.vm.aktiveAnsicht).toBe('geschaefte')
  })

  it('schreibt die gewählte Ansicht in den URL-Hash', () => {
    const w = shallowMount(App)
    w.vm.ansichtWechseln('vorstoesse')
    expect(w.vm.aktiveAnsicht).toBe('vorstoesse')
    expect(window.location.hash).toBe('#vorstoesse')
  })

  it('reagiert auf hashchange (Vor/Zurück im Browser)', async () => {
    const w = shallowMount(App)
    window.location.hash = '#anleitung'
    window.dispatchEvent(new Event('hashchange'))
    await w.vm.$nextTick()
    expect(w.vm.aktiveAnsicht).toBe('anleitung')
  })
})
