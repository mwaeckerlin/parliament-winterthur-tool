import { vi } from 'vitest'

// Suppress Vue warnings for missing components in unit tests
global.console.warn = vi.fn()
