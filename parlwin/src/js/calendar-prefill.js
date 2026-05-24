/**
 * Lädt auf jeder NC-Seite und befüllt den NC-Calendar-Editor mit
 * Vorschau-Daten, die parlwin via sessionStorage übergeben hat.
 *
 * Layer A (stabil):   title, location, description via DOM
 * Layer B (Calendar): Kalender-Dropdown via DOM-Klick (NcSelect / vue-select)
 * Layer C (Fallback): showWarning-Popup für Teilnehmer und Kalender
 */

import { showError, showWarning } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'

const STORAGE_KEY = 'parlwin_event_prefill'

if (window.location.pathname.includes('/apps/calendar')) {
  const raw = sessionStorage.getItem(STORAGE_KEY)
  if (raw) {
    sessionStorage.removeItem(STORAGE_KEY)
    let prefillData
    try {
      prefillData = JSON.parse(raw)
    } catch {
      console.error('[parlwin] Ungültige prefill-Daten in sessionStorage')
    }
    if (prefillData) {
      startPrefillObserver(prefillData)
    }
  }
}

// ─── Observer ─────────────────────────────────────────────────────────────

function startPrefillObserver(data) {
  let done = false
  const observer = new MutationObserver(() => {
    if (done) return
    if (tryPrefill(data)) {
      done = true
      observer.disconnect()
    }
  })
  observer.observe(document.body, { childList: true, subtree: true })
  setTimeout(() => {
    if (!done) {
      observer.disconnect()
      console.warn('[parlwin] Kalender-Editor nach 15s nicht gefunden')
    }
  }, 15000)
}

// ─── Haupt-Prefill ────────────────────────────────────────────────────────

function tryPrefill(data) {
  const titleEl = findTitleInput()
  if (!titleEl) return false

  console.debug('[parlwin] Editor bereit, befülle Felder …')

  const errors = []

  if (data.titel && !setInputValue(titleEl, data.titel)) errors.push('Titel')

  const locationEl = findLocationInput()
  if (locationEl && data.ort && !setInputValue(locationEl, data.ort)) errors.push('Ort')
  if (!locationEl && data.ort) errors.push('Ort')

  const descEl = findDescriptionElement()
  if (descEl && data.beschreibung && !setEditorValue(descEl, data.beschreibung)) errors.push('Beschreibung')
  if (!descEl && data.beschreibung) errors.push('Beschreibung')

  if (errors.length > 0) {
    showError(
      'Folgende Felder konnten nicht automatisch gesetzt werden: ' +
      errors.join(', ') + '. Bitte manuell eintragen.',
      { timeout: -1 }
    )
  }

  if (data.kalenderUri) {
    setTimeout(() => trySetCalendar(data), 300)
  }

  if (Array.isArray(data.teilnehmer) && data.teilnehmer.length > 0) {
    setTimeout(() => tryAddAttendees(data.teilnehmer), 400)
  }

  return true
}

// ─── Layer A: DOM-Injection ───────────────────────────────────────────────

function setInputValue(el, value) {
  try {
    const proto = el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype
    const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set
    if (!setter) return false
    setter.call(el, value)
    el.dispatchEvent(new Event('input', { bubbles: true }))
    el.dispatchEvent(new Event('change', { bubbles: true }))
    return true
  } catch (e) {
    console.warn('[parlwin] setInputValue fehlgeschlagen:', e)
    return false
  }
}

function setEditorValue(el, value) {
  try {
    if (el.tagName === 'TEXTAREA' || el.tagName === 'INPUT') {
      return setInputValue(el, value)
    }
    // contenteditable (ProseMirror / TipTap)
    el.focus()
    document.execCommand('selectAll', false, null)
    document.execCommand('insertText', false, value)
    return true
  } catch (e) {
    console.warn('[parlwin] setEditorValue fehlgeschlagen:', e)
    return false
  }
}

function findTitleInput() {
  for (const sel of [
    'input[placeholder="Titel"]',
    'input[placeholder="Title"]',
    'input[aria-label="Titel"]',
    'input[aria-label="Title"]',
    'input[placeholder*="Ereignis"]',
    'input[placeholder*="Event"]',
  ]) {
    const el = document.body.querySelector(sel)
    if (el) return el
  }
  return null
}

function findLocationInput() {
  // NC Calendar 4.x: location uses PropertyText.vue which renders a <textarea>
  // inside a .property-location container
  for (const sel of [
    '.property-location textarea',
    '.property-location input',
    'textarea[placeholder*="Ort"]',
    'textarea[placeholder*="Location"]',
    'input[placeholder="Ort hinzufügen"]',
    'input[placeholder*="Ort"]',
    'input[aria-label*="Ort"]',
    'input[aria-label*="location" i]',
  ]) {
    const el = document.body.querySelector(sel)
    if (el) return el
  }
  return null
}

function findDescriptionElement() {
  // NC Calendar 4.x: description uses PropertyText.vue → <textarea> in .property-description
  for (const sel of [
    '.property-description textarea',
    'textarea.textarea--description',
    'div.ProseMirror[contenteditable="true"]',
    '.ProseMirror[contenteditable="true"]',
    '[contenteditable="true"][aria-label="Beschreibung"]',
    '[contenteditable="true"][aria-label="Description"]',
    '.property-description [contenteditable="true"]',
    'textarea[aria-label*="Beschreibung"]',
    'textarea[aria-label*="Description" i]',
    'textarea[placeholder*="Beschreibung"]',
  ]) {
    const el = document.body.querySelector(sel)
    if (el) return el
  }

  // Fallback: any textarea in the editor container that's not location
  const titleEl = findTitleInput()
  if (titleEl) {
    const container = findEditorContainer(titleEl)
    if (container) {
      const textareas = [...container.querySelectorAll('textarea')]
      const locationTa = container.querySelector('.property-location textarea')
      for (const ta of textareas) {
        if (ta !== locationTa) return ta
      }
    }
  }
  return null
}

/** Walks up from el to find the enclosing editor/popover container. */
function findEditorContainer(el) {
  let node = el.closest('.new-event-popover, [class*="popover"], [class*="editor"], [role="dialog"]')
  if (node) return node
  node = el.parentElement
  for (let i = 0; i < 8; i++) {
    if (!node?.parentElement) break
    node = node.parentElement
    if (node.querySelectorAll('input').length >= 3) break
  }
  return node
}

// ─── Layer B: Kalender via DOM-Klick ─────────────────────────────────────

async function trySetCalendar(data) {
  try {
    const btn = findCalendarPickerButton()
    if (!btn) throw new Error('Kalender-Picker-Button nicht gefunden')

    btn.click()

    // NC Calendar uses NcSelect (vue-select) — wait for .vs__dropdown-option
    const dropdownSelectors = [
      '.vs__dropdown-option',
      '.multiselect__option',
      'li.option',
      '[role="option"]',
      '[role="listbox"] li',
    ]
    let foundOptionEl = null
    for (const sel of dropdownSelectors) {
      try {
        foundOptionEl = await waitForElement(sel, 600)
        if (foundOptionEl) break
      } catch { /* try next */ }
    }
    if (!foundOptionEl) throw new Error('Kalender-Dropdown nicht geöffnet (keine Optionen gefunden)')

    const zielName = await findCalendarDisplayName(data.kalenderUri)
    if (!zielName) throw new Error(`Kalender '${data.kalenderUri}' nicht in Pinia gefunden`)

    const allOptionSel = '.vs__dropdown-option, .multiselect__option, li.option, [role="option"]'
    const options = document.body.querySelectorAll(allOptionSel)

    // Exact text match first
    for (const opt of options) {
      if (opt.textContent?.trim() === zielName) {
        opt.click()
        console.debug('[parlwin] Kalender gewählt (exact):', zielName)
        return
      }
    }
    // Partial match
    for (const opt of options) {
      if (opt.textContent?.trim().includes(zielName)) {
        opt.click()
        console.debug('[parlwin] Kalender gewählt (partial):', zielName)
        return
      }
    }

    throw new Error(
      `Option '${zielName}' nicht gefunden (${options.length} Optionen: ${
        [...options].map(o => o.textContent?.trim()).join(' | ')
      })`
    )

  } catch (err) {
    console.warn('[parlwin] Kalender-Klick fehlgeschlagen:', err.message)
    const zielName = await findCalendarDisplayName(data.kalenderUri).catch(() => null)
    showWarning(
      `Kalender bitte manuell wählen: «${zielName || data.kalenderUri}»`,
      { timeout: -1 }
    )
  }
}

function findCalendarPickerButton() {
  // NC Calendar 4.x: CalendarPicker uses NcSelect (vue-select) — trigger is .vs__dropdown-toggle
  for (const sel of [
    '.edit-calendar-picker .vs__dropdown-toggle',
    '.calendar-picker .vs__dropdown-toggle',
    '[class*="calendar-picker"] .vs__dropdown-toggle',
    '[class*="CalendarPicker"] .vs__dropdown-toggle',
    '.property-calendar .vs__dropdown-toggle',
    '.new-event-popover .vs__dropdown-toggle',
    '[class*="calendar-picker"]',
    '[class*="calendarPicker"]',
    '[class*="calendar-indicator"]',
  ]) {
    const el = document.body.querySelector(sel)
    if (el) return el
  }

  // Fallback: any .vs__dropdown-toggle in the editor container
  const titleEl = findTitleInput()
  if (titleEl) {
    const container = findEditorContainer(titleEl)
    if (container) {
      const vsToggle = container.querySelector('.vs__dropdown-toggle')
      if (vsToggle) return vsToggle
    }
  }

  // Original positional fallback: button above the title input in the popover
  const allBtns = document.body.querySelectorAll('button, [role="button"]')
  for (const btn of allBtns) {
    const txt = btn.textContent?.trim() || ''
    if (txt.length === 0 || txt.length > 60) continue
    if (['Speichern', 'Abbrechen', 'Schliessen', 'Schließen', 'Weitere Einzelheiten', 'More details'].includes(txt)) continue
    const titleEl2 = findTitleInput()
    if (titleEl2) {
      const rect1 = btn.getBoundingClientRect()
      const rect2 = titleEl2.getBoundingClientRect()
      if (Math.abs(rect1.left - rect2.left) < 300 && rect1.top < rect2.top) {
        return btn
      }
    }
  }
  return null
}

/** Holt den Display-Namen eines Kalenders aus dem Pinia-Store anhand des URI. */
async function findCalendarDisplayName(kalenderUri) {
  try {
    const pinia = document.querySelector('#content')?.__vue_app__?.config?.globalProperties?.$pinia
    if (!pinia?._s?.has('calendars')) return null
    const cals = pinia._s.get('calendars').calendars || []
    const kal = cals.find(c =>
      c.url?.includes(kalenderUri) ||
      c.uri?.includes(kalenderUri) ||
      c.id?.includes(kalenderUri)
    )
    return kal?.displayName || null
  } catch {
    return null
  }
}

function waitForElement(selector, timeout) {
  return new Promise((resolve, reject) => {
    const existing = document.body.querySelector(selector)
    if (existing) { resolve(existing); return }
    const obs = new MutationObserver(() => {
      const el = document.body.querySelector(selector)
      if (el) { obs.disconnect(); resolve(el) }
    })
    obs.observe(document.body, { childList: true, subtree: true })
    setTimeout(() => { obs.disconnect(); reject(new Error('Timeout: ' + selector)) }, timeout)
  })
}

// ─── Layer C: Teilnehmer ──────────────────────────────────────────────────

/**
 * Versucht, alle Teilnehmer automatisch über das NC Calendar Attendee-Suchfeld
 * einzufügen. Fallback: Popup mit copy-paste-fähiger Liste.
 */
async function tryAddAttendees(teilnehmer) {
  const failed = []
  for (const t of teilnehmer) {
    const searchTerm = t.gruppe ? (t.displayName || t.groupId) : (t.email || t.displayName || '')
    if (!searchTerm) continue
    const ok = await tryAddOneAttendee(searchTerm)
    if (!ok) failed.push(searchTerm)
  }
  if (failed.length > 0) {
    showWarning(
      'Teilnehmer bitte manuell hinzufügen:\n\n' + failed.join('\n'),
      { timeout: -1 }
    )
  }
}

async function tryAddOneAttendee(searchTerm) {
  try {
    const input = findAttendeeInput()
    if (!input) return false

    // Type search term — triggers NcSelect's @search="findAttendees" (debounced 500ms)
    setInputValue(input, searchTerm)

    // Wait for debounce + API response + dropdown render (>700ms total)
    let optEl = null
    for (const sel of ['.vs__dropdown-option', '.multiselect__option', '[role="option"]']) {
      try { optEl = await waitForElement(sel, 2000); if (optEl) break } catch { /* next */ }
    }
    if (!optEl) {
      setInputValue(input, '')
      return false
    }

    // Find and click the matching option (first result is usually the best match)
    const opts = [...document.body.querySelectorAll('.vs__dropdown-option, .multiselect__option, [role="option"]')]
    const match = opts.find(o => {
      const txt = (o.textContent || '').trim()
      return txt && (txt.includes(searchTerm) || searchTerm.includes(txt.slice(0, 8)))
    }) || opts[0]

    if (match) {
      match.click()
      console.debug('[parlwin] Teilnehmer hinzugefügt:', searchTerm)
      await sleep(500)
      return true
    }
    setInputValue(input, '')
    return false
  } catch (e) {
    console.warn('[parlwin] tryAddOneAttendee fehlgeschlagen:', e)
    return false
  }
}

function findAttendeeInput() {
  // NC Calendar 4.x: attendee search uses NcSelect with class "invitees-search__vselect"
  // and inputId="uid" on the inner <input>
  for (const sel of [
    '.invitees-search__vselect input#uid',
    '.invitees-search__vselect input.vs__search',
    '.invitees-search__vselect input',
    '.invitees-search input.vs__search',
    '[class*="invitees-search"] input',
    '[class*="attendees"] input.vs__search',
  ]) {
    const el = document.body.querySelector(sel)
    if (el) return el
  }
  return null
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms))
}
