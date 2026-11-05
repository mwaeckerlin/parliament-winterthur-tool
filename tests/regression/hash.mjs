// Hashes der Eingaben: für die Identität des Quellstands, die Marken der
// Images und die Gruppen, deren letzter grüner Lauf ihren Stand schon misst.
import { createHash } from 'node:crypto'
import fs from 'node:fs'
import path from 'node:path'
import { WURZEL, lesen } from './hilfen.mjs'

/** Jede von git verfolgte und jede neue, nicht ignorierte Datei (wie regression-state.py). */
export function gitDateien(unter = []) {
  const r = lesen('git', ['ls-files', '-z', '--cached', '--others', '--exclude-standard', '--', ...unter])
  if (r.code !== 0) return null
  return [...new Set(r.stdout.split('\0').filter(Boolean))].sort()
}

export function hashDateien(dateien, zusatz = []) {
  const h = createHash('sha256')
  for (const z of zusatz) h.update(`${z}\0`)
  for (const datei of dateien) {
    h.update(`${datei}\0`)
    const pfad = path.join(WURZEL, datei)
    h.update(fs.existsSync(pfad) ? fs.readFileSync(pfad) : 'gelöscht')
    h.update('\0')
  }
  return h.digest('hex')
}

/** Der Quellstand des ganzen Projekts. */
export function quellIdentitaet() {
  const dateien = gitDateien()
  return dateien ? hashDateien(dateien) : `ohne-git-${Date.now()}`
}

/** Die ID eines lokalen Image, leer wenn es fehlt. Fragt keine Registry. */
export function bildId(name) {
  const r = lesen('docker', ['image', 'inspect', '--format', '{{.Id}}', name])
  return r.code === 0 ? r.stdout.trim() : ''
}

/**
 * Hash der Eingaben eines Image: die Dateien, die sein Dockerfile kopiert, und
 * die IDs seiner lokal vorhandenen Basis-Images.
 */
export function bildHash({ dateien, ohne, basen }) {
  const liste = (gitDateien(dateien) || []).filter((d) => !(ohne && ohne.test(d)))
  return hashDateien(liste, basen.map((b) => `${b}=${bildId(b) || 'fehlt'}`)).slice(0, 12)
}

// Welche Eingaben eine Gruppe liest. vitest liest Dateien im ganzen Projekt
// (README.md, FEATURES.md, TESTS.md, Dockerfiles, PHP-Quellen), also den
// ganzen Quellstand. PHPUnit läuft im Image parlwin-php-test:lokal, dessen ID
// genau seine Eingaben misst (COPY . /test). Die beiden Verträge lesen ihre
// Dateien selbst (COMPOSE und MUSTER in tests/compose-contract.sh). Ob eine Gruppe mit
// unverändertem Hash ausfallen darf, entscheidet VON_AUSSEN in
// tests/regression.mjs.
export function gruppenHash(gruppe, { phpTestBild = '' } = {}) {
  switch (gruppe) {
    case 'js':
    case 'lint':
      return quellIdentitaet()
    case 'php-unit':
    case 'php-pdf':
    case 'php-live':
      return phpTestBild ? `${bildId(phpTestBild)}:${hashDateien(['tests/php-suite.sh', 'tests/run-all.sh'])}` : ''
    case 'compose':
      return hashDateien(['tests/compose-contract.sh', 'example/docker-compose.yml', 'example/.env.sample'])
    case 'audit':
      return hashDateien(['package.json', 'package-lock.json'])
    default:
      return ''
  }
}
