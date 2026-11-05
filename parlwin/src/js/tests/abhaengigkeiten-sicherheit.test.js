import { describe, it, expect } from 'vitest'
import { existsSync, readFileSync } from 'node:fs'

// vitest läuft im Wurzelverzeichnis des Projekts.
const paket = JSON.parse(readFileSync('package.json', 'utf8'))
const composer = JSON.parse(readFileSync('parlwin/composer.json', 'utf8'))

function pruefdatenIn(text) {
  return [...String(text ?? '').matchAll(/Nachprüfen am (\d{4}-\d{2}-\d{2})/g)].map((m) => m[1])
}

function tagVon(datum) {
  const zweistellig = (zahl) => String(zahl).padStart(2, '0')
  return `${datum.getFullYear()}-${zweistellig(datum.getMonth() + 1)}-${zweistellig(datum.getDate())}`
}

function erzwungeneVersionen(eintraege, vorne = '') {
  return Object.entries(eintraege ?? {}).flatMap(([name, wert]) =>
    typeof wert === 'object' ? erzwungeneVersionen(wert, `${vorne}${name} › `) : [`${vorne}${name}`])
}

function obergrenzen() {
  const begrenzt = (eintraege) => Object.entries(eintraege ?? {})
    .filter(([, fassung]) => fassung.includes('<'))
    .map(([name, fassung]) => `${name} ${fassung}`)
  const konflikte = Object.entries(composer.conflict ?? {}).map(([name, fassung]) => `${name} ${fassung}`)
  return [...begrenzt(composer.require), ...begrenzt(composer['require-dev']), ...konflikte]
}

function expectKalendertage(daten, quelle) {
  for (const datum of daten) {
    expect(tagVon(new Date(`${datum}T12:00:00`)), `«Nachprüfen am ${datum}» in ${quelle} ist kein Kalendertag`).toBe(datum)
  }
}

// Zerlegt Dockerfile.php-fpm in Stufen; `zeilen` enthält die Anweisungen der
// Stufe samt denen der Stufen, auf denen sie aufbaut, je auf einer Zeile.
function dockerStufen() {
  const stufen = []
  const anweisungen = readFileSync('Dockerfile.php-fpm', 'utf8')
    .split('\n').filter((zeile) => !zeile.trimStart().startsWith('#'))
    .join('\n').replace(/\\\n\s*/g, ' ').split('\n')
  for (const zeile of anweisungen) {
    const kopf = zeile.match(/^FROM\s+(\S+)(?:\s+AS\s+(\S+))?/i)
    if (kopf) stufen.push({ basis: kopf[1], name: kopf[2] ?? '', eigene: [] })
    else if (stufen.length > 0 && zeile.trim() !== '') stufen.at(-1).eigene.push(zeile.trim())
  }
  const zeilen = (stufe) => {
    const basis = stufen.find((s) => s.name !== '' && s.name === stufe.basis)
    return [...(basis ? zeilen(basis) : []), ...stufe.eigene]
  }
  return stufen.map((s) => ({ ...s, zeilen: zeilen(s) }))
}

const pakete = (lock, teil) => (lock[teil] ?? []).map((p) => p.name)
const bibliotheken = (eintraege) => Object.keys(eintraege ?? {}).filter((name) => name.includes('/'))

const heute = tagVon(new Date())

describe('PHP-Abhängigkeiten: der Bau installiert die Fassungen aus parlwin/composer.lock', () => {
  it('parlwin/composer.lock liegt im Repository und enthält jede Bibliothek aus parlwin/composer.json', () => {
    expect(existsSync('parlwin/composer.lock'), 'parlwin/composer.lock fehlt; `npm run composer:update -- --lock` erzeugt sie').toBe(true)
    const lock = JSON.parse(readFileSync('parlwin/composer.lock', 'utf8'))
    for (const name of bibliotheken(composer.require)) {
      expect(pakete(lock, 'packages'), `${name} aus «require» fehlt in parlwin/composer.lock unter «packages»`).toContain(name)
    }
    for (const name of bibliotheken(composer['require-dev'])) {
      expect([...pakete(lock, 'packages'), ...pakete(lock, 'packages-dev')],
        `${name} aus «require-dev» fehlt in parlwin/composer.lock`).toContain(name)
    }
  })

  it('die Bibliotheken des ausgelieferten Images kommen aus «composer install --no-dev» über composer.lock', () => {
    const stufen = dockerStufen()
    const letzte = stufen.at(-1)
    const herkunft = letzte.eigene.map((z) => z.match(/^COPY\s+--from=(\S+)\s+\S*\/vendor\s/)).find(Boolean)
    expect(herkunft, 'die letzte Stufe von Dockerfile.php-fpm kopiert kein vendor-Verzeichnis').toBeTruthy()
    const quelle = stufen.find((s) => s.name === herkunft[1])
    expect(quelle.zeilen.some((z) => /^COPY\s+parlwin\/composer\.lock\s/.test(z)),
      `die Stufe ${quelle.name} kopiert parlwin/composer.lock nicht`).toBe(true)
    const aufrufe = quelle.zeilen.filter((z) => /^RUN\s+composer\s/.test(z))
    expect(aufrufe.length, `die Stufe ${quelle.name} ruft composer nicht auf`).toBeGreaterThan(0)
    for (const aufruf of aufrufe) {
      expect(aufruf, `die Stufe ${quelle.name} löst die Abhängigkeiten neu auf statt composer.lock zu installieren`)
        .toMatch(/^RUN\s+composer\s+install\s+--no-dev\s/)
    }
  })

  it('das ausgelieferte Image übernimmt kein parlwin/vendor aus dem Arbeitsverzeichnis', () => {
    const stufen = dockerStufen()
    const app = stufen.at(-1).eigene.map((z) => z.match(/^COPY\s+--from=(\S+)\s+\S*\/parlwin\s/)).find(Boolean)
    expect(app, 'die letzte Stufe von Dockerfile.php-fpm kopiert die App nicht').toBeTruthy()
    const quelle = stufen.find((s) => s.name === app[1])
    const kopie = quelle.zeilen.findIndex((z) => /^COPY\s.*\bparlwin\/\s/.test(z))
    expect(kopie, `die Stufe ${quelle.name} kopiert parlwin/ nicht`).toBeGreaterThanOrEqual(0)
    const entfernt = quelle.zeilen.slice(kopie + 1)
      .some((z) => /^RUN\s.*\brm\s+-rf\s.*\sparlwin\/vendor(\s|$)/.test(z))
    expect(entfernt, `die Stufe ${quelle.name} kopiert parlwin/ samt einem vendor aus dem Arbeitsverzeichnis `
      + 'mit den Werkzeugen der Entwicklung und entfernt es nicht').toBe(true)
  })

  it('nur die Stufe, deren Ergebnis composer.lock ist, ruft «composer update» auf', () => {
    const stufen = dockerStufen()
    const lockStufe = stufen.flatMap((s) => s.eigene)
      .map((z) => z.match(/^COPY\s+--from=(\S+)\s+\S*composer\.lock\s+\/composer\.lock$/)).find(Boolean)
    expect(lockStufe, 'keine Stufe von Dockerfile.php-fpm gibt composer.lock heraus').toBeTruthy()
    for (const stufe of stufen) {
      const auffloesen = stufe.eigene.filter((z) => /^RUN\s+composer\s+update\b/.test(z))
      if (stufe.name !== lockStufe[1]) {
        expect(auffloesen, `die Stufe ${stufe.name || '(letzte)'} löst mit composer update neu auf`).toEqual([])
      }
    }
  })
})

describe('SECURITY-WORKAROUND in package.json: Prüfdatum', () => {
  const kommentar = paket['comment-overrides'] ?? ''
  const pruefdaten = pruefdatenIn(kommentar)

  it('die erzwungenen Versionen in «overrides» tragen den Hinweis SECURITY-WORKAROUND mit einem gültigen Prüfdatum', () => {
    expect(erzwungeneVersionen(paket.overrides).length, 'package.json hat keine «overrides»').toBeGreaterThan(0)
    expect(kommentar, '«comment-overrides» in package.json nennt kein SECURITY-WORKAROUND').toContain('SECURITY-WORKAROUND')
    expect(pruefdaten.length, '«comment-overrides» in package.json nennt kein «Nachprüfen am JJJJ-MM-TT»').toBeGreaterThan(0)
    expectKalendertage(pruefdaten, '«comment-overrides» in package.json')
  })

  it('kein Prüfdatum in «comment-overrides» ist vorbei', () => {
    const versionen = erzwungeneVersionen(paket.overrides).join(', ')
    for (const datum of pruefdaten) {
      expect(datum >= heute,
        `SECURITY-WORKAROUND «comment-overrides» in package.json (overrides: ${versionen}): `
        + `Nachprüfen am ${datum} ist vorbei, heute ist ${heute}. Mit npm audit prüfen, welche erzwungene Version `
        + 'noch nötig ist, und das Ergebnis mit einem neuen Prüfdatum in «comment-overrides» und in CONTRIBUTING.md '
        + 'unter «Sicherheit» eintragen.').toBe(true)
    }
  })
})

describe('Obergrenzen in parlwin/composer.json: Prüfdatum', () => {
  const kommentar = composer.extra?.['comment-obergrenzen'] ?? ''
  const pruefdaten = pruefdatenIn(kommentar)

  it('jede Obergrenze steht mit einem gültigen Prüfdatum in «extra.comment-obergrenzen»', () => {
    if (obergrenzen().length === 0) return
    expect(pruefdaten.length,
      `parlwin/composer.json begrenzt ${obergrenzen().join(', ')}, aber «extra.comment-obergrenzen» nennt kein `
      + '«Nachprüfen am JJJJ-MM-TT»').toBeGreaterThan(0)
    expectKalendertage(pruefdaten, '«extra.comment-obergrenzen» in parlwin/composer.json')
  })

  it('kein Prüfdatum in «extra.comment-obergrenzen» ist vorbei', () => {
    for (const datum of pruefdaten) {
      expect(datum >= heute,
        `Obergrenzen in parlwin/composer.json (${obergrenzen().join(', ')}): Nachprüfen am ${datum} ist vorbei, `
        + `heute ist ${heute}. Jede Obergrenze entfernen, deren ausgeschlossene Fassungen mindestens zwei Wochen alt `
        + 'sind, danach `npm run composer:update -- --lock` laufen lassen und Kommentar und Prüfdatum nachführen.').toBe(true)
    }
  })
})
