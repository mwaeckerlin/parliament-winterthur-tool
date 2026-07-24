import { describe, it, expect, vi, beforeEach } from 'vitest'
import { readFileSync } from 'fs'
import { shallowMount } from '@vue/test-utils'
import Sitzungsliste from '../components/Sitzungsliste.vue'
import axios from '@nextcloud/axios'

// vitest läuft im Projekt-Root
const sitzungslisteSrc = readFileSync('parlwin/src/js/components/Sitzungsliste.vue', 'utf8')

// Notizen zu einem Traktandum mit verknüpftem Geschäft haften am GESCHÄFT (als
// eigener Typ «sitzungsnotiz») — nicht an der Sitzung. So erscheinen sie an jeder
// Sitzung, an der das Geschäft als Traktandum hängt. Verwaltet wird das über die
// geteilte NotizenListe-Komponente mit kategorie="sitzungsnotiz".
describe('Sitzungsnotiz am Geschäft — Guard (Aufbau)', () => {
  it('importiert und verwendet die geteilte NotizenListe', () => {
    expect(sitzungslisteSrc).toMatch(/import\s+NotizenListe\s+from\s+['"]\.\/NotizenListe\.vue['"]/)
    expect(sitzungslisteSrc).toMatch(/<NotizenListe/)
  })

  it('verwendet NotizenListe mit kategorie="sitzungsnotiz" und einer geschaefte/-basisUrl', () => {
    expect(sitzungslisteSrc).toMatch(/kategorie="sitzungsnotiz"/)
    expect(sitzungslisteSrc).toMatch(/basis-url="'geschaefte\//)
  })
})

describe('Sitzungsnotiz am Geschäft — Laden', () => {
  beforeEach(() => {
    axios.get.mockReset()
    axios.post.mockReset()
    axios.put.mockReset()
    axios.delete.mockReset()
  })

  it('lädt die Sitzungsnotizen eines Geschäfts mit ?kategorie=sitzungsnotiz und legt sie in sitzungsnotizen[gid] ab', async () => {
    const notizDaten = [
      { id: 11, aktionTyp: 'sitzungsnotiz', text: 'am Geschäft', autorUid: 'testuser', geloescht: false },
    ]
    axios.get.mockImplementation((url) => {
      if (String(url).includes('/notizen')) return Promise.resolve({ data: notizDaten })
      return Promise.resolve({ data: [] })
    })

    const wrapper = shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
    await wrapper.vm.$nextTick()

    await wrapper.vm.ladeSitzungsnotizen(5)

    const treffer = axios.get.mock.calls.find(c => String(c[0]).includes('/geschaefte/5/notizen'))
    expect(treffer).toBeTruthy()
    expect(treffer[1]).toMatchObject({ params: { kategorie: 'sitzungsnotiz' } })
    expect(wrapper.vm.sitzungsnotizen[5]).toEqual(notizDaten)
  })

  it('ignoriert Traktanden ohne Geschäft (gid <= 0)', async () => {
    axios.get.mockResolvedValue({ data: [] })
    const wrapper = shallowMount(Sitzungsliste, { props: { mitglieder: [], fraktionen: [], kommissionen: [] } })
    await wrapper.vm.$nextTick()
    axios.get.mockClear()

    await wrapper.vm.ladeSitzungsnotizen(0)

    expect(axios.get.mock.calls.some(c => String(c[0]).includes('/notizen'))).toBe(false)
    expect(wrapper.vm.sitzungsnotizen[0]).toBeUndefined()
  })
})
