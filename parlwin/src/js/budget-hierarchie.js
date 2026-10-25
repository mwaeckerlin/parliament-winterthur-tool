/**
 * F111: Aus den Produktegruppen der Budgetansicht den Baum für die grafische
 * Übersicht bauen — Einnahmen/Ausgaben → Departement → Produktegruppe → Produkt.
 *
 * Jeder Knoten trägt zwei Zahlen:
 * - `betrag`: der Wert aus dem Budgetbuch, wie er angezeigt wird.
 * - `flaeche`: der Wert, aus dem die Kreisgrösse entsteht. Für Blätter ohne
 *   Kinder ist er gleich dem Betrag; für Produkte wird er auf das Budget ihrer
 *   Produktegruppe normiert. Ohne diese Normierung erschiene eine Gruppe, deren
 *   Produkte im Buch nur einen Teil des Budgets ausweisen, kleiner als eine
 *   gleich teure Gruppe ohne Produkte.
 */

const zahl = (wert) => {
  const n = Number(wert)
  return Number.isFinite(n) ? n : 0
}
/** Nur positive Beträge ergeben eine Fläche; negative und leere fallen weg. */
const positiv = (wert) => Math.max(0, zahl(wert))

// Die Kostentabelle eines Produkts führt «Kosten», «Erlös», «Nettokosten» und den
// Kostendeckungsgrad über drei Jahre; die dritte Spalte ist «Soll aktuell».
const SEITEN = [
  { name: 'Einnahmen', feld: 'ertrag', zeile: 'erlös' },
  { name: 'Ausgaben', feld: 'aufwand', zeile: 'kosten' },
]

/**
 * Das erste Wort einer Zeilenbeschriftung — «Kostendeckungsgrad in %» beginnt mit
 * «Kosten», ist aber eine Prozentzahl. Gemessen am Budget 2026: PG 712 hat keine
 * Kosten-Zeile, und eine Suche nach «beginnt mit kosten» nahm dort den
 * Deckungsgrad (30) als Kosten des Produkts.
 */
const ersteWort = (label) => String(label || '').trim().toLowerCase().split(/[^a-zäöüéèà]+/)[0]

const produktBetrag = (produkt, zeilenName) => {
  const zeilen = Array.isArray(produkt?.kostentabelle) ? produkt.kostentabelle : []
  const treffer = zeilen.find(z => ersteWort(z?.label) === zeilenName)
  const werte = Array.isArray(treffer?.werte) ? treffer.werte : []
  return positiv(werte[2])
}

/** Die Produkte einer Gruppe, flächennormiert auf deren Budget. */
const produktKreise = (gruppe, seite, gruppenBetrag) => {
  const produkte = (Array.isArray(gruppe?.produkte) ? gruppe.produkte : [])
    .map(p => ({ name: String(p?.name || ('Produkt ' + (p?.nummer || ''))).trim(), betrag: produktBetrag(p, seite.zeile) }))
    .filter(p => p.betrag > 0)
  if (produkte.length === 0) {
    return null
  }
  const summe = produkte.reduce((s, p) => s + p.betrag, 0)
  return {
    kinder: produkte.map(p => ({ ...p, flaeche: (p.betrag / summe) * gruppenBetrag })),
    // Welchen Anteil des Gruppenbudgets die Produkte im Buch überhaupt ausweisen.
    // Unter 100% bleibt ein Teil des Geldes ohne Produkt — das gehört sichtbar,
    // sonst versteckt die Flächennormierung genau diese Lücke.
    deckung: (summe / gruppenBetrag) * 100,
  }
}

const gruppenKreis = (gruppe, seite) => {
  const betrag = positiv(gruppe?.[seite.feld]?.soll)
  if (betrag <= 0) {
    return null
  }
  const produkte = produktKreise(gruppe, seite, betrag)
  const knoten = { name: String(gruppe?.name || gruppe?.code || '').trim(), code: String(gruppe?.code || ''), betrag, flaeche: betrag }
  return produkte ? { ...knoten, children: produkte.kinder, produktDeckung: produkte.deckung } : knoten
}

/** Departemente in der Reihenfolge ihres ersten Auftretens in der Ansicht. */
const departementKreise = (gruppen, seite) => {
  const reihenfolge = []
  const jeDepartement = new Map()
  for (const gruppe of gruppen) {
    const kreis = gruppenKreis(gruppe, seite)
    if (!kreis) {
      continue
    }
    const dep = String(gruppe?.departement || 'Ohne Departement').trim()
    if (!jeDepartement.has(dep)) {
      jeDepartement.set(dep, [])
      reihenfolge.push(dep)
    }
    jeDepartement.get(dep).push(kreis)
  }
  return reihenfolge.map(dep => {
    const kinder = jeDepartement.get(dep)
    const betrag = kinder.reduce((s, k) => s + k.betrag, 0)
    return { name: dep, betrag, flaeche: betrag, children: kinder }
  })
}

/**
 * F123: Derselbe Baum für den Stellenplan — Stellen → Departement →
 * Produktegruppe. Bei der Produktegruppe hört er auf, weil das Budgetbuch die
 * Stellen nur dort ausweist und nicht je Produkt.
 *
 * Jeder Knoten trägt neben der Fläche den Vorjahreswert, die Veränderung und
 * daraus die Richtung: `aufbau` mehr Stellen als im Vorjahr, `abbau` weniger,
 * `gleich` unverändert. Die Grafik färbt danach.
 */
const richtungVon = (veraenderung) => {
  if (veraenderung > 0) { return 'aufbau' }
  if (veraenderung < 0) { return 'abbau' }
  return 'gleich'
}

const stellenKnoten = (name, betrag, vorjahr, rest = {}) => {
  const veraenderung = betrag - vorjahr
  return { name, betrag, flaeche: betrag, vorjahr, veraenderung, richtung: richtungVon(veraenderung), ...rest }
}

/**
 * @param {Array<object>} produktegruppen Produktegruppen der Budgetansicht
 * @return {object} Wurzel des Kreisbaums (ohne Kinder, wenn keine Stellen vorliegen)
 */
export function personalHierarchie(produktegruppen) {
  const reihenfolge = []
  const jeDepartement = new Map()
  for (const gruppe of (Array.isArray(produktegruppen) ? produktegruppen : [])) {
    const betrag = positiv(gruppe?.stellen?.soll)
    if (betrag <= 0) {
      continue
    }
    const dep = String(gruppe?.departement || 'Ohne Departement').trim()
    if (!jeDepartement.has(dep)) {
      jeDepartement.set(dep, [])
      reihenfolge.push(dep)
    }
    jeDepartement.get(dep).push(stellenKnoten(
      String(gruppe?.name || gruppe?.code || '').trim(),
      betrag,
      positiv(gruppe?.stellen?.sollVorjahr),
      { code: String(gruppe?.code || '') },
    ))
  }
  const departemente = reihenfolge.map(dep => {
    const kinder = jeDepartement.get(dep)
    return stellenKnoten(
      dep,
      kinder.reduce((s, k) => s + k.betrag, 0),
      kinder.reduce((s, k) => s + k.vorjahr, 0),
      { children: kinder },
    )
  })
  if (departemente.length === 0) {
    return { name: 'Stellenplan', betrag: 0, flaeche: 0 }
  }
  const stellen = stellenKnoten(
    'Stellen',
    departemente.reduce((s, k) => s + k.betrag, 0),
    departemente.reduce((s, k) => s + k.vorjahr, 0),
    { children: departemente },
  )
  return { ...stellenKnoten('Stellenplan', stellen.betrag, stellen.vorjahr), children: [stellen] }
}

/**
 * @param {Array<object>} produktegruppen Produktegruppen der Budgetansicht
 * @return {object} Wurzel des Kreisbaums (ohne Kinder, wenn nichts darstellbar ist)
 */
export function budgetHierarchie(produktegruppen) {
  const gruppen = Array.isArray(produktegruppen) ? produktegruppen : []
  const seiten = SEITEN
    .map(seite => {
      const kinder = departementKreise(gruppen, seite)
      if (kinder.length === 0) {
        return null
      }
      const betrag = kinder.reduce((s, k) => s + k.betrag, 0)
      return { name: seite.name, betrag, flaeche: betrag, children: kinder }
    })
    .filter(Boolean)
  const betrag = seiten.reduce((s, k) => s + k.betrag, 0)
  const wurzel = { name: 'Budget', betrag, flaeche: betrag }
  return seiten.length ? { ...wurzel, children: seiten } : wurzel
}
