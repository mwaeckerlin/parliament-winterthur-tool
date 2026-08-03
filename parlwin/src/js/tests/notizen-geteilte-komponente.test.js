import { describe, it, expect, vi } from 'vitest'
import { readFileSync } from 'fs'
import { shallowMount } from '@vue/test-utils'
import NotizenListe from '../components/NotizenListe.vue'
import Aktionszeitleiste from '../components/Aktionszeitleiste.vue'

vi.mock('@nextcloud/auth', () => ({
  getCurrentUser: () => ({ uid: 'testuser', displayName: 'Test User' }),
}))

// vitest läuft im Projekt-Root
const geschaeftSrc = readFileSync('parlwin/src/js/components/GeschaeftDetail.vue', 'utf8')
const vorstossSrc = readFileSync('parlwin/src/js/components/Vorstoesseliste.vue', 'utf8')

// Die Notizen bei Geschäften UND Vorstössen nutzen dieselbe Komponente
// (NotizenListe) — es darf keine zweite Notiz-Implementierung geben.
describe('Notizen — eine geteilte Komponente für Geschäft und Vorstoss', () => {
  it('GeschaeftDetail importiert und verwendet NotizenListe', () => {
    expect(geschaeftSrc).toMatch(/import\s+NotizenListe\s+from\s+['"]\.\/NotizenListe\.vue['"]/)
    expect(geschaeftSrc).toMatch(/<NotizenListe/)
  })

  it('Vorstoesseliste importiert und verwendet NotizenListe', () => {
    expect(vorstossSrc).toMatch(/import\s+NotizenListe\s+from\s+['"]\.\/NotizenListe\.vue['"]/)
    expect(vorstossSrc).toMatch(/<NotizenListe/)
  })

  it('GeschaeftDetail enthält keinen eigenen Notiz-Editor mehr', () => {
    expect(geschaeftSrc).not.toContain('notizPersistieren')
    expect(geschaeftSrc).not.toContain('notizBearbeitenStarten')
  })

  it('Vorstoesseliste enthält keinen eigenen Notiz-Editor mehr', () => {
    expect(vorstossSrc).not.toContain('notizHinzufuegen')
    expect(vorstossSrc).not.toContain('neueNotiz')
  })

  // Die gelöschten Notizen leben in der Aktionszeitleiste (nicht in der
  // NotizenListe): als Vermerk «… hat seine Notiz gelöscht», mit einem
  // Wiederherstellen-Knopf, den nur der Autor sieht.
  it('rendert eine gelöschte Notiz als Vermerk mit Wiederherstellen nur für den Autor', () => {
    const geloescht = {
      id: 3, aktionTyp: 'notiz', text: 'geheimer Inhalt', autorUid: 'testuser',
      autorName: 'Test User', erstelltAm: '2026-07-14T10:00:00+00:00', geloescht: true,
    }
    const eigen = shallowMount(Aktionszeitleiste, {
      props: { aktionen: [geloescht], basisUrl: 'geschaefte/1', aktuelleUid: 'testuser' },
    })
    expect(eigen.text()).toContain('hat seine Notiz gelöscht')
    // Der Notiztext selbst bleibt verborgen.
    expect(eigen.text()).not.toContain('geheimer Inhalt')
    expect(eigen.find('button[title="Löschen rückgängig machen"]').exists()).toBe(true)

    const fremd = shallowMount(Aktionszeitleiste, {
      props: { aktionen: [geloescht], basisUrl: 'geschaefte/1', aktuelleUid: 'jemandanderes' },
    })
    expect(fremd.find('button[title="Löschen rückgängig machen"]').exists()).toBe(false)

    // In der NotizenListe erscheint der Vermerk NICHT mehr.
    const liste = shallowMount(NotizenListe, {
      props: { basisUrl: 'geschaefte/1', notizen: [geloescht], aktuelleUid: 'testuser' },
    })
    expect(liste.text()).not.toContain('hat seine Notiz gelöscht')
  })
})
