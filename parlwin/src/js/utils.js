export function vollerName(m) {
  return `${m.vorname || ''} ${m.name || ''}`.trim()
}

export function personKey(m) {
  const externId = m.externId || m.extern_id || ''
  if (externId) return `mitglied:${externId}`
  return `name:${vollerName(m)}`
}

export function parseNotizen(raw) {
  if (!raw) return []
  try {
    const arr = typeof raw === 'string' ? JSON.parse(raw) : raw
    return Array.isArray(arr) ? arr : []
  } catch {
    return []
  }
}

import MarkdownIt from 'markdown-it'
import DOMPurify from 'dompurify'

// Notizen werden intern als Markdown gespeichert (siehe PwWysiwyg). Für die
// Anzeige wird mit demselben Parser (markdown-it) nach HTML gerendert und
// anschliessend gesäubert (XSS-Schutz vor v-html).
const markdownRenderer = new MarkdownIt({ html: false, linkify: true, breaks: true })

export function markdownZuHtml(text) {
  if (!text) return ''
  return DOMPurify.sanitize(markdownRenderer.render(String(text)))
}

// Prioritätsstufen für Geschäfte UND Vorstösse; Default ist nicht gesetzt (leer).
export const PRIORITAETEN = [
  { value: 'hoch', label: 'Hoch' },
  { value: 'mittel', label: 'Mittel' },
  { value: 'tief', label: 'Tief' },
]

/**
 * Grundprinzip für ALLE Wertefilter: ein leerer/undefinierter Wert ist ebenfalls
 * ein realer Wert in den Daten. Kommt er vor, wird er als eigene Filteroption
 * `{ value: '', label }` vorangestellt — genau wie der «—»-Eintrag beim
 * Beschluss-Filter. EIN gemeinsamer Mechanismus, damit das nicht pro Filter neu
 * erfunden wird; `optionen` sind die aus den Daten gegründeten Nicht-Leer-Werte.
 */
export function mitLeerOption(optionen, hatLeer, leerLabel) {
  return hatLeer ? [{ value: '', label: leerLabel }, ...optionen] : optionen
}

// Anzeigetext einer Prioritätsstufe (der EINE Ort für die Wert→Text-Umrechnung).
export function prioritaetLabel(value) {
  return (PRIORITAETEN.find(p => p.value === value) || {}).label || ''
}

// Wendet die konfigurierten Kürzel (Suchtext → Kürzel) auf einen Anzeigetext an.
// Gilt gleichermassen für Status-, Partei-, Fraktions- und Kommissionsnamen.
// Längere Suchtexte werden zuerst ersetzt, damit ein kürzerer Eintrag einen
// längeren nicht zerstückelt. Gespeichert wird immer der Originalwert.
export function kuerze(text, liste) {
  if (!text) return text
  const regeln = liste || (typeof window !== 'undefined' && window.PARLWIN_CONFIG?.statusKuerzel) || []
  let result = String(text)
  for (const { suche, kuerzel } of [...regeln].sort((a, b) => (b.suche || '').length - (a.suche || '').length)) {
    if (suche && kuerzel) result = result.split(suche).join(kuerzel)
  }
  return result
}

// Einfaches Titel-Ähnlichkeitsmass: Zahl gemeinsamer Wörter (länger als zwei
// Zeichen). Wird beim Verknüpfen (Vorstoss→Geschäft, eigenes→offizielles
// Geschäft) genutzt, um ähnliche Ziele zuoberst vorzuschlagen.
export function titelAehnlichkeit(a, b) {
  const worte = t => new Set((t || '').toLowerCase().split(/\W+/).filter(w => w.length > 2))
  const wa = worte(a)
  const wb = worte(b)
  let gemeinsam = 0
  wa.forEach(w => { if (wb.has(w)) gemeinsam++ })
  return gemeinsam
}
