import { describe, it, expect, vi } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import GeschaeftDetail from '../components/GeschaeftDetail.vue'
import NotizenListe from '../components/NotizenListe.vue'
import Aktionszeitleiste from '../components/Aktionszeitleiste.vue'

vi.mock('../realtime', () => ({ subscribeRealtime: () => vi.fn() }))
vi.mock('@nextcloud/axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: { aktionen: [], zustaendigkeiten: [] } })),
    post: vi.fn(() => Promise.resolve({ data: {} })),
    put: vi.fn(() => Promise.resolve({ data: {} })),
    delete: vi.fn(() => Promise.resolve({ data: null })),
  },
}))
vi.mock('@nextcloud/dialogs', () => ({
  showSuccess: vi.fn(), showError: vi.fn(), showWarning: vi.fn(),
}))
vi.mock('@nextcloud/auth', () => ({
  getCurrentUser: () => ({ uid: 'testuser', displayName: 'Test User' }),
}))

const NOTIZ = {
  id: 7, aktionTyp: 'notiz', titel: '', text: 'Meine Notiz', aktionCode: '',
  autorUid: 'testuser', autorName: 'Test User', erstelltAm: '2026-07-14T10:00:00+00:00',
  entscheidGueltig: false, geloescht: false,
}
const NOTIZ_GELOESCHT = { ...NOTIZ, id: 8, text: 'Weg damit', geloescht: true }
const BESCHLUSS = {
  id: 9, aktionTyp: 'beschluss', titel: 'Zustimmen', text: '', aktionCode: 'unterstuetzen',
  autorUid: 'testuser', autorName: 'Test User', erstelltAm: '2026-07-14T11:00:00+00:00',
  entscheidGueltig: true, geloescht: false,
}

async function mountDetail(aktionen = []) {
  const wrapper = shallowMount(GeschaeftDetail, {
    props: { geschaeftId: 1, mitglieder: [], traktandumKontext: null },
    data() {
      return {
        laden: false,
        geschaeft: {
          id: 1, nummer: '2024/001', titel: 'Testgeschäft', typ: 'Motion', status: 'pendent',
          datum: '2024-01-01', aktionen, fraktionsstatus: null, fraktionssitzung: null, zustaendig: [],
        },
      }
    },
    global: {
      stubs: { NcSelect: true, PwMultiSelect: true, GeschaeftDokumente: true, NotizenListe: true, NcIconSvgWrapper: true },
    },
  })
  // mounted() lädt nach (axios-Mock liefert leere Aktionen) — danach den
  // geladenen Zustand herstellen, den die Prüfungen erwarten.
  await wrapper.vm.$nextTick()
  await Promise.resolve()
  wrapper.vm.laden = false
  wrapper.vm.geschaeft = {
    id: 1, nummer: '2024/001', titel: 'Testgeschäft', typ: 'Motion', status: 'pendent',
    datum: '2024-01-01', aktionen, fraktionsstatus: null, fraktionssitzung: null, zustaendig: [],
  }
  await wrapper.vm.$nextTick()
  return wrapper
}

// Die Notizen (aktiv wie gelöscht) leben in der geteilten NotizenListe —
// die Aktionszeitleiste des Geschäfts rendert sie nicht mehr.
function mountListe(notizen = [], aktuelleUid = 'testuser') {
  return shallowMount(NotizenListe, {
    props: { basisUrl: 'geschaefte/1', notizen, aktuelleUid },
  })
}

// Die Aktionszeitleiste zeigt aktive Notizen NICHT (die leben in der
// NotizenListe), GELÖSCHTE Notizen aber als Lösch-Vermerk mit Wiederherstellen.
function mountZeitleiste(aktionen = [], aktuelleUid = 'testuser') {
  return shallowMount(Aktionszeitleiste, {
    props: { aktionen, basisUrl: 'geschaefte/1', aktuelleUid },
  })
}

describe('Zeitleiste — aktive Notizen raus, gelöschte als Lösch-Vermerk', () => {
  it('zeigt eine aktive Notiz NICHT in der Aktionszeitleiste', () => {
    const wrapper = mountZeitleiste([NOTIZ])
    expect(wrapper.vm.zeitleisteEintraege.some(e => e.id === 7)).toBe(false)
  })

  it('zeigt eine gelöschte Notiz als Lösch-Vermerk in der Aktionszeitleiste', () => {
    const wrapper = mountZeitleiste([NOTIZ_GELOESCHT])
    const eintrag = wrapper.vm.zeitleisteEintraege.find(e => e.id === 8)
    expect(eintrag, 'Die gelöschte Notiz fehlt in der Aktionszeitleiste').toBeTruthy()
    expect(eintrag._type).toBe('notizGeloescht')
    // Der Vermerk erscheint, der Notiztext bleibt verborgen.
    expect(wrapper.text()).toContain('hat seine Notiz gelöscht')
    expect(wrapper.text()).not.toContain('Weg damit')
  })

  it('bietet dem Autor einen Undo-Knopf, anderen nicht', () => {
    const eigen = mountZeitleiste([NOTIZ_GELOESCHT], 'testuser')
    expect(eigen.find('button[title="Löschen rückgängig machen"]').exists()).toBe(true)
    const fremd = mountZeitleiste([NOTIZ_GELOESCHT], 'jemandanderes')
    expect(fremd.find('button[title="Löschen rückgängig machen"]').exists()).toBe(false)
  })

  it('stellt die Notiz per Undo wieder her und meldet sie an die Elternansicht', async () => {
    const axios = (await import('@nextcloud/axios')).default
    axios.post.mockResolvedValueOnce({ data: { ...NOTIZ_GELOESCHT, geloescht: false } })
    const wrapper = mountZeitleiste([NOTIZ_GELOESCHT])
    await wrapper.vm.notizWiederherstellen({ ...NOTIZ_GELOESCHT, _type: 'notizGeloescht' })
    expect(axios.post).toHaveBeenCalledWith(
      expect.stringContaining('/notizen/8/wiederherstellen?kategorie=notiz'),
    )
    expect(wrapper.emitted('notiz-wiederhergestellt')).toBeTruthy()
  })

  it('lässt andere Aktionen (Beschluss) in der Zeitleiste', () => {
    const wrapper = mountZeitleiste([BESCHLUSS])
    expect(wrapper.vm.zeitleisteEintraege.some(e => e.id === 9)).toBe(true)
  })

  it('reicht alle Notiz-Aktionen (aktiv + gelöscht) über notizAktionen an die Liste', async () => {
    const wrapper = await mountDetail([NOTIZ, NOTIZ_GELOESCHT])
    expect(wrapper.vm.notizAktionen.map(n => n.id)).toEqual(expect.arrayContaining([7, 8]))
  })
})

describe('NotizenListe — nur aktive Notizen', () => {
  it('führt eine aktive Notiz in der aktiven Liste', () => {
    const wrapper = mountListe([NOTIZ])
    expect(wrapper.vm.aktiveNotizen.map(n => n.id)).toContain(7)
  })

  it('blendet gelöschte Notizen aus (sie leben in der Aktionszeitleiste)', () => {
    const wrapper = mountListe([NOTIZ_GELOESCHT])
    expect(wrapper.vm.aktiveNotizen.map(n => n.id)).not.toContain(8)
    // Kein Lösch-Vermerk und kein Undo mehr in dieser Komponente.
    expect(wrapper.text()).not.toContain('hat seine Notiz gelöscht')
    expect(wrapper.find('button[title="Löschen rückgängig machen"]').exists()).toBe(false)
  })

  it('unterscheidet Löschen (Mülleimer-Icon) optisch vom Schliessen (✕)', () => {
    const wrapper = mountListe([NOTIZ])
    const loeschen = wrapper.find('button[title="Notiz löschen"]')
    expect(loeschen.exists()).toBe(true)
    expect(loeschen.text()).not.toContain('✕')
    expect(loeschen.element.children.length).toBeGreaterThan(0)
  })
})

// Der Editor öffnet erst auf Knopfdruck — kein grosses leeres Formular.
describe('NotizenListe — Editor öffnet erst auf Knopfdruck', () => {
  it('zeigt zunächst keinen Editor, sondern einen «Neue Notiz»-Knopf', () => {
    const wrapper = mountListe([])
    expect(wrapper.vm.editorOffen).toBe(false)
    expect(wrapper.find('button[title="Neue Notiz"]').exists()).toBe(true)
  })

  it('öffnet den Editor erst nach Klick', async () => {
    const wrapper = mountListe([])
    await wrapper.find('button[title="Neue Notiz"]').trigger('click')
    expect(wrapper.vm.editorOffen).toBe(true)
  })
})
