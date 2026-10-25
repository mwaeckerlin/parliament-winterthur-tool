/**
 * Eine Tabellendatei in Zeilen und Spalten (F127).
 *
 * CSV wird hier gelesen, `.xlsx` ebenso: eine solche Datei ist ein ZIP mit
 * XML darin, und für die Übernahme von Anträgen genügen die Zeichenketten und
 * die Zahlen des ersten Blattes. Ohne Bibliothek, damit die Anwendung keine
 * weitere Abhängigkeit trägt.
 */

/** Trennt eine CSV-Zeile; Anführungszeichen schützen Trennzeichen im Feld. */
export function csvZeile(zeile, trenner) {
  const felder = []
  let feld = ''
  let imZitat = false
  for (let i = 0; i < zeile.length; i++) {
    const z = zeile[i]
    if (imZitat) {
      if (z === '"' && zeile[i + 1] === '"') { feld += '"'; i++ } else if (z === '"') { imZitat = false } else { feld += z }
      continue
    }
    if (z === '"') { imZitat = true } else if (z === trenner) { felder.push(feld); feld = '' } else { feld += z }
  }
  felder.push(feld)
  return felder
}

/** Das Trennzeichen der Datei: Semikolon oder Komma, je nachdem was häufiger vorkommt. */
export function trennzeichen(text) {
  const kopf = text.split(/\r?\n/)[0] || ''
  const semikolon = (kopf.match(/;/g) || []).length
  const komma = (kopf.match(/,/g) || []).length
  return semikolon >= komma ? ';' : ','
}

/** CSV als Liste von Zeilen, jede Zeile eine Liste von Feldern. */
export function csvLesen(text) {
  const trenner = trennzeichen(text)
  return text
    .split(/\r?\n/)
    .filter(z => z.trim() !== '')
    .map(z => csvZeile(z, trenner).map(f => f.trim()))
}

/**
 * Die Zeilen des ersten Blattes einer `.xlsx`-Datei. `zip` ist eine Abbildung
 * von Dateinamen auf Text, wie sie der Aufrufer aus dem Archiv gewinnt.
 */
export function xlsxLesen(zip) {
  const geteilt = zeichenketten(zip['xl/sharedStrings.xml'] || '')
  const blatt = zip['xl/worksheets/sheet1.xml'] || ''
  const zeilen = []
  for (const zeileXml of blatt.match(/<row[\s\S]*?<\/row>/g) || []) {
    const felder = []
    for (const zelle of zeileXml.match(/<c[\s>][\s\S]*?(?:<\/c>|\/>)/g) || []) {
      const spalte = spaltenNummer((zelle.match(/r="([A-Z]+)\d+"/) || [])[1] || '')
      const wert = (zelle.match(/<v>([\s\S]*?)<\/v>/) || [])[1] || ''
      const inline = (zelle.match(/<t[^>]*>([\s\S]*?)<\/t>/) || [])[1]
      const istText = /t="s"/.test(zelle)
      let text = inline !== undefined ? inline : wert
      if (istText) { text = geteilt[Number(wert)] || '' }
      while (felder.length < spalte) { felder.push('') }
      felder[spalte] = entschluesselt(text).trim()
    }
    if (felder.some(f => f !== '')) { zeilen.push(felder) }
  }
  return zeilen
}

/** Die gemeinsame Zeichenkettentabelle einer xlsx-Datei. */
function zeichenketten(xml) {
  return (xml.match(/<si>[\s\S]*?<\/si>/g) || []).map(si => {
    const teile = si.match(/<t[^>]*>([\s\S]*?)<\/t>/g) || []
    return entschluesselt(teile.map(t => t.replace(/<[^>]+>/g, '')).join(''))
  })
}

/** «A» → 0, «B» → 1, «AA» → 26. */
function spaltenNummer(buchstaben) {
  let n = 0
  for (const b of buchstaben) { n = n * 26 + (b.charCodeAt(0) - 64) }
  return Math.max(0, n - 1)
}

function entschluesselt(text) {
  return String(text)
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&apos;/g, "'")
    .replace(/&amp;/g, '&')
}

/**
 * Ordnet die Spalten der Datei den Feldern eines Antrags zu und prüft jede
 * Zeile. Das Ergebnis trägt je Zeile entweder den Antrag oder den Grund,
 * warum sie nicht übernommen werden kann.
 */
export function zeilenPruefen(zeilen, positionOptionen) {
  if (zeilen.length === 0) { return [] }
  const kopf = zeilen[0].map(z => z.toLowerCase())
  const spalte = name => kopf.findIndex(k => k.includes(name))
  const iPosition = spalte('position')
  const iBetrag = spalte('betrag')
  const iRichtung = spalte('richtung')
  const iBegruendung = spalte('begründung') >= 0 ? spalte('begründung') : spalte('begruendung')
  return zeilen.slice(1).map((zeile, nummer) => {
    const roh = {
      zeile: nummer + 2,
      position: iPosition >= 0 ? (zeile[iPosition] || '') : '',
      betrag: iBetrag >= 0 ? (zeile[iBetrag] || '') : '',
      richtung: iRichtung >= 0 ? (zeile[iRichtung] || '') : '',
      begruendung: iBegruendung >= 0 ? (zeile[iBegruendung] || '') : '',
    }
    const treffer = positionOptionen.find(o =>
      o.value === roh.position
      || String(o.label).toLowerCase() === roh.position.toLowerCase()
      || String(o.nummer || '') === roh.position
    )
    if (!treffer) { return { ...roh, fehler: 'Unbekannte Position' } }
    const zahl = Number(String(roh.betrag).replace(/[’'\s]/g, '').replace(',', '.'))
    if (roh.betrag === '' || isNaN(zahl)) { return { ...roh, fehler: 'Betrag ist keine Zahl' } }
    // Ohne Angabe ist ein Antrag eine Kürzung: die Spalte «Richtung» oder ein
    // ausdrückliches Pluszeichen am Betrag macht eine Mehrausgabe daraus.
    const mehrausgabe = /mehr|erhöh|aufstock|\+/i.test(roh.richtung)
      || String(roh.betrag).trim().startsWith('+')
    return {
      ...roh,
      antrag: {
        position: treffer.value,
        mehrausgabe,
        betragDelta: (mehrausgabe ? 1 : -1) * Math.abs(Math.round(zahl)),
        begruendung: roh.begruendung,
      },
    }
  })
}

/** Die Vorlage, die der Übernahme als Muster dient. */
export const VORLAGE_SPALTEN = ['Position', 'Richtung', 'Betrag', 'Begründung']

export function vorlageCsv() {
  return VORLAGE_SPALTEN.join(';') + '\n'
}
