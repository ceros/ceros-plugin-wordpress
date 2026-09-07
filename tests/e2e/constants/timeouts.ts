/** Named timeout tiers. Specs use these, never numeric literals. */

const seconds = (n: number) => n * 1000

const fromEnv = (envVar: string, defaultMs: number): number => {
  const raw = process.env[envVar]
  return raw && !isNaN(Number(raw)) ? Number(raw) : defaultMs
}

export const TIMEOUTS = {
  MICRO: fromEnv('E2E_TIMEOUT_MICRO', seconds(0.4)),
  SHORT: fromEnv('E2E_TIMEOUT_SHORT', seconds(8)),
  SHORT_MEDIUM: fromEnv('E2E_TIMEOUT_SHORT_MEDIUM', seconds(16)),
  MEDIUM: fromEnv('E2E_TIMEOUT_MEDIUM', seconds(28)),
  MEDIUM_LONG: fromEnv('E2E_TIMEOUT_MEDIUM_LONG', seconds(48)),
  LONG: fromEnv('E2E_TIMEOUT_LONG', seconds(80)),
  EXTRA_LONG: fromEnv('E2E_TIMEOUT_EXTRA_LONG', seconds(240)),
} as const

export const CONFIG_TIMEOUTS = {
  TEST: fromEnv('E2E_TIMEOUT_TEST', seconds(150)),
  ACTION: fromEnv('E2E_TIMEOUT_ACTION', seconds(30)),
  NAVIGATION: fromEnv('E2E_TIMEOUT_NAVIGATION', seconds(30)),
  GLOBAL: fromEnv('E2E_TIMEOUT_GLOBAL', seconds(1800)),
} as const
