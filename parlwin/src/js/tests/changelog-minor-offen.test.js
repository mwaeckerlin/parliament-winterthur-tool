import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Changelog from '../components/Changelog.vue'

function minorVon(version) {
  const m = String(version).match(/^(\d+)\.(\d+)/)
  return m ? `${m[1]}.${m[2]}` : ''
}

// Alle Einträge der aktuellen Minor-Version (z.B. 1.7.x) sind aufgeklappt,
// ältere sind zugeklappt.
describe('Änderungsverlauf — aktuelle Minor-Version aufgeklappt', () => {
  it('klappt jeden Eintrag der aktuellen Minor-Version auf', () => {
    const wrapper = mount(Changelog)
    const versionen = wrapper.vm.versionen
    const aktuelleMinor = minorVon(versionen[0].version)

    const erwartet = versionen
      .filter(v => minorVon(v.version) === aktuelleMinor)
      .map(v => v.version)

    expect(erwartet.length).toBeGreaterThan(1)
    for (const v of erwartet) {
      expect(wrapper.vm.offen, `${v} muss aufgeklappt sein`).toContain(v)
    }
  })

  it('lässt ältere Minor-Versionen zugeklappt', () => {
    const wrapper = mount(Changelog)
    const versionen = wrapper.vm.versionen
    const aktuelleMinor = minorVon(versionen[0].version)

    const aeltere = versionen.filter(v => minorVon(v.version) !== aktuelleMinor)
    expect(aeltere.length).toBeGreaterThan(0)
    for (const v of aeltere) {
      expect(wrapper.vm.offen, `${v.version} darf nicht aufgeklappt sein`).not.toContain(v.version)
    }
  })
})
