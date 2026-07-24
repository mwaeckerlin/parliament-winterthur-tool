import { describe, it, expect, beforeEach } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'
import axios from '@nextcloud/axios'

const adminTemplate = resolve(dirname(fileURLToPath(import.meta.url)), '../../../templates/admin.php')
const stylePath = resolve(dirname(fileURLToPath(import.meta.url)), '../../css/style.scss')

// Feature: Der Zeitplan der automatischen Synchronisation ist in der Admin-UI
// konfigurierbar — beliebige Einträge mit Wochentagen (Mo–So zum Abhaken) und
// Uhrzeit, mit Hinzufügen und Löschen.
describe('Admin: Sync-Zeitplan (Wochentage + Uhrzeit)', () => {
  beforeEach(() => {
    document.body.innerHTML = '<div id="pw-zeitplan-liste"></div><span id="pw-zeitplan-status"></span>'
    axios.get.mockReset()
    axios.post.mockReset()
  })

  it('die Admin-Seite enthält den Zeitplan-Bereich (Liste + Hinzufügen)', () => {
    const src = readFileSync(adminTemplate, 'utf8')
    expect(src).toContain('pw-zeitplan-liste')
    expect(src).toContain('pw-zeitplan-hinzufuegen')
  })

  it('lädt den gespeicherten Zeitplan und rendert Wochentage als Checkboxen und die Uhrzeit', async () => {
    axios.get.mockResolvedValue({ data: [{ tage: [1, 3], zeit: '06:30' }] })
    const { ladeZeitplan } = await import('../admin')
    await ladeZeitplan()

    const zeilen = document.querySelectorAll('#pw-zeitplan-liste .pw-zeitplan-row')
    expect(zeilen).toHaveLength(1)
    const boxen = zeilen[0].querySelectorAll('input[type="checkbox"]')
    expect(boxen).toHaveLength(7)
    expect(boxen[0].checked).toBe(true)  // Mo
    expect(boxen[1].checked).toBe(false) // Di
    expect(boxen[2].checked).toBe(true)  // Mi
    expect(zeilen[0].querySelector('input[type="time"]').value).toBe('06:30')
    expect(zeilen[0].querySelector('.pw-zeitplan-delete')).not.toBeNull()
  })

  it('der Wochentag steht senkrecht zur Checkbox (Spalten-Layout, nicht daneben)', () => {
    const css = readFileSync(stylePath, 'utf8')
    const block = css.match(/\.pw-zeitplan-tag\s*\{[^}]*\}/)
    expect(block, 'Stil-Regel .pw-zeitplan-tag fehlt').toBeTruthy()
    expect(block[0]).toMatch(/flex-direction:\s*column/)
    expect(block[0]).toMatch(/align-items:\s*center/)
  })

  it('liest den Zeitplan aus den Eingabezeilen (nur vollständige Einträge)', async () => {
    axios.get.mockResolvedValue({ data: [{ tage: [2, 6], zeit: '18:15' }, { tage: [4], zeit: '' }] })
    const { ladeZeitplan, sammleZeitplan } = await import('../admin')
    await ladeZeitplan()
    expect(sammleZeitplan()).toEqual([{ tage: [2, 6], zeit: '18:15' }])
  })
})
