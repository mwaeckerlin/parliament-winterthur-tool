import { vi } from 'vitest'

// Suppress Vue warnings for missing components in unit tests
global.console.warn = vi.fn()

// Any console.error during tests is a bug — fail immediately
global.console.error = (...args) => {
  throw new Error(`console.error called in test: ${args.map(String).join(' ')}`)
}
