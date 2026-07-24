import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

let kurzelSaveTimer = null
const KURZEL_SAVE_DELAY = 5000

function showStatusMessage(elementId, message, isError = false) {
  const el = document.getElementById(elementId)
  if (!el) return
  el.textContent = message
  el.className = `pw-sync-status ${isError ? 'pw-error' : 'pw-success'}`
  if (!isError) {
    setTimeout(() => {
      if (el.textContent === message) {
        el.textContent = ''
        el.className = 'pw-sync-status'
      }
    }, 2500)
  }
}

// Erstellt eine Eingabezeile (Suchtext + Kürzel + Löschen) mit Auto-Save.
function kuerzelZeileErstellen(suche = '', kuerzel = '') {
  const liste = document.getElementById('pw-kuerzel-liste')
  if (!liste) return null

  const row = document.createElement('div')
  row.className = 'pw-kuerzel-row'
  row.innerHTML = `
    <input type="text" class="pw-kuerzel-suchtext" value="${escapeHtml(suche)}" placeholder="Suchtext" list="pw-status-kuerzel-liste" />
    <input type="text" class="pw-kuerzel-wert" value="${escapeHtml(kuerzel)}" placeholder="Kürzel" />
    <button type="button" class="button pw-kuerzel-delete" title="Löschen">×</button>
  `
  liste.appendChild(row)

  row.querySelector('.pw-kuerzel-delete').addEventListener('click', (e) => {
    e.preventDefault()
    row.remove()
    kurzelAutoSpeichern()
  })
  row.querySelectorAll('input').forEach((input) => {
    input.addEventListener('change', kurzelAutoSpeichern)
    input.addEventListener('blur', kurzelAutoSpeichern)
    input.addEventListener('input', kurzelAutoSpeichern)
  })
  return row
}

// Lädt die gespeicherten Kürzel vom Server (Liste von {suche, kuerzel}) und rendert sie.
function kuerzeleRendern() {
  const liste = document.getElementById('pw-kuerzel-liste')
  if (!liste) return

  axios
    .get(generateUrl('/apps/parlwin/settings/status-kuerzel'))
    .then((response) => {
      const eintraege = Array.isArray(response.data) ? response.data : []
      liste.innerHTML = ''
      eintraege.forEach((e) => kuerzelZeileErstellen(e.suche || '', e.kuerzel || ''))
    })
    .catch((err) => {
      console.error('Fehler beim Laden der Status-Kürzel:', err)
    })
}

function kurzelAutoSpeichern() {
  clearTimeout(kurzelSaveTimer)
  showStatusMessage('pw-kuerzel-status', 'Speichern...', false)

  kurzelSaveTimer = setTimeout(() => {
    const liste = document.getElementById('pw-kuerzel-liste')
    if (!liste) return

    const eintraege = []
    document.querySelectorAll('#pw-kuerzel-liste .pw-kuerzel-row').forEach((row) => {
      const suche = row.querySelector('.pw-kuerzel-suchtext').value.trim()
      const kuerzel = row.querySelector('.pw-kuerzel-wert').value.trim()
      if (suche && kuerzel) {
        eintraege.push({ suche, kuerzel })
      }
    })

    axios
      .post(generateUrl('/apps/parlwin/settings/status-kuerzel'), { status_kuerzel: eintraege })
      .then(() => {
        showStatusMessage('pw-kuerzel-status', 'Gespeichert', false)
      })
      .catch((err) => {
        console.error('Fehler beim Speichern der Kürzel:', err)
        showStatusMessage('pw-kuerzel-status', 'Fehler beim Speichern', true)
      })
  }, KURZEL_SAVE_DELAY)
}

function kuerzleHinzufuegen() {
  const row = kuerzelZeileErstellen('', '')
  if (row) row.querySelector('.pw-kuerzel-suchtext').focus()
}

// Füllt die Vorschlagsliste für das Suchtext-Feld: bestehende Status-Werte
// sowie die aktuellen (aktiven) Fraktions- und Parteinamen — die Kürzel gelten
// überall, wo diese Namen angezeigt werden.
export function ladeVorschlagswerte() {
  const datalist = document.getElementById('pw-status-kuerzel-liste')
  if (!datalist) return Promise.resolve()
  datalist.innerHTML = ''
  const seen = new Set()
  const hinzufuegen = (wert) => {
    if (!wert || seen.has(wert)) return
    seen.add(wert)
    const option = document.createElement('option')
    option.value = wert
    datalist.appendChild(option)
  }
  return Promise.all([
    axios
      .get(generateUrl('/apps/parlwin/geschaefte?show_erledigt=1&limit=2000'))
      .then((r) => Array.isArray(r.data) && r.data.forEach((g) => hinzufuegen(g.status)))
      .catch((err) => console.error('Fehler beim Laden der Status-Werte:', err)),
    axios
      .get(generateUrl('/apps/parlwin/fraktionen'))
      .then((r) => Array.isArray(r.data) && r.data.forEach((f) => { if (f.aktiv !== false) hinzufuegen(f.name) }))
      .catch((err) => console.error('Fehler beim Laden der Fraktionen:', err)),
    axios
      .get(generateUrl('/apps/parlwin/mitglieder?aktiv=1'))
      .then((r) => Array.isArray(r.data) && r.data.forEach((m) => hinzufuegen(m.partei)))
      .catch((err) => console.error('Fehler beim Laden der Parteien:', err)),
  ])
}

function escapeHtml(text) {
  const div = document.createElement('div')
  div.textContent = text
  return div.innerHTML
}

// --- Zeitplan der automatischen Synchronisation (Wochentage + Uhrzeit) -------

const WOCHENTAGE = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']
let zeitplanSaveTimer = null

// Erstellt eine Zeitplan-Zeile (7 Wochentag-Checkboxen + Uhrzeit + Löschen).
function zeitplanZeileErstellen(tage = [], zeit = '') {
  const liste = document.getElementById('pw-zeitplan-liste')
  if (!liste) return null

  const row = document.createElement('div')
  row.className = 'pw-zeitplan-row'
  row.innerHTML = `
    ${WOCHENTAGE.map((name, i) => `
      <label class="pw-zeitplan-tag"><input type="checkbox" data-tag="${i + 1}" ${tage.includes(i + 1) ? 'checked' : ''} /> ${name}</label>
    `).join('')}
    <input type="time" class="pw-zeitplan-zeit" value="${escapeHtml(zeit)}" />
    <button type="button" class="button pw-zeitplan-delete" title="Löschen">×</button>
  `
  liste.appendChild(row)

  row.querySelector('.pw-zeitplan-delete').addEventListener('click', (e) => {
    e.preventDefault()
    row.remove()
    zeitplanAutoSpeichern()
  })
  row.querySelectorAll('input').forEach((input) => {
    input.addEventListener('change', zeitplanAutoSpeichern)
  })
  return row
}

// Liest den Zeitplan aus den Eingabezeilen; unvollständige Einträge
// (keine Tage oder keine Uhrzeit) werden ausgelassen.
export function sammleZeitplan() {
  const eintraege = []
  document.querySelectorAll('#pw-zeitplan-liste .pw-zeitplan-row').forEach((row) => {
    const tage = [...row.querySelectorAll('input[type="checkbox"]')]
      .filter((box) => box.checked)
      .map((box) => Number(box.dataset.tag))
    const zeit = row.querySelector('.pw-zeitplan-zeit').value
    if (tage.length && zeit) {
      eintraege.push({ tage, zeit })
    }
  })
  return eintraege
}

function zeitplanAutoSpeichern() {
  clearTimeout(zeitplanSaveTimer)
  showStatusMessage('pw-zeitplan-status', 'Speichern...', false)
  zeitplanSaveTimer = setTimeout(() => {
    axios
      .post(generateUrl('/apps/parlwin/settings/sync-zeitplan'), { sync_zeitplan: sammleZeitplan() })
      .then(() => {
        showStatusMessage('pw-zeitplan-status', 'Gespeichert', false)
      })
      .catch((err) => {
        console.error('Fehler beim Speichern des Zeitplans:', err)
        showStatusMessage('pw-zeitplan-status', 'Fehler beim Speichern', true)
      })
  }, KURZEL_SAVE_DELAY)
}

// Lädt den gespeicherten Zeitplan und rendert die Eingabezeilen.
export function ladeZeitplan() {
  const liste = document.getElementById('pw-zeitplan-liste')
  if (!liste) return Promise.resolve()
  return axios
    .get(generateUrl('/apps/parlwin/settings/sync-zeitplan'))
    .then((response) => {
      const eintraege = Array.isArray(response.data) ? response.data : []
      liste.innerHTML = ''
      eintraege.forEach((e) => zeitplanZeileErstellen(Array.isArray(e.tage) ? e.tage : [], e.zeit || ''))
    })
    .catch((err) => {
      console.error('Fehler beim Laden des Zeitplans:', err)
    })
}

document.addEventListener('DOMContentLoaded', () => {
  // Kürzel: Initial-Laden und Auto-Save
  kuerzeleRendern()
  ladeVorschlagswerte()

  const hinzufuegenBtn = document.getElementById('pw-kuerzel-hinzufuegen')
  if (hinzufuegenBtn) {
    hinzufuegenBtn.addEventListener('click', (e) => {
      e.preventDefault()
      kuerzleHinzufuegen()
    })
  }

  // Sync-Zeitplan: Initial-Laden und Auto-Save
  ladeZeitplan()
  const zeitplanBtn = document.getElementById('pw-zeitplan-hinzufuegen')
  if (zeitplanBtn) {
    zeitplanBtn.addEventListener('click', (e) => {
      e.preventDefault()
      const row = zeitplanZeileErstellen([], '')
      if (row) row.querySelector('.pw-zeitplan-zeit').focus()
    })
  }
})
