import { execFile } from 'node:child_process'
import path from 'node:path'
import { promisify } from 'node:util'

const run = promisify(execFile)

/** The repository root, where wp-env finds its config. */
const REPO_ROOT = path.resolve(__dirname, '../../..')

// Built by concatenation in PHP so the echoed command never contains the joined marker.
const MARKER = '__E2E_JSON__'
const PHP_MARKER = '"__E2E" . "_JSON__"'

const redact = (text: string, secrets: readonly string[]): string =>
  secrets.filter(Boolean).reduce((out, secret) => out.split(secret).join('[redacted]'), text)

/**
 * Run WP-CLI in the local wp-env instance and return its stdout.
 *
 * The one place the suite reaches past WordPress's HTTP surface, so it only ever
 * talks to a local wp-env instance (see the README). Any secret passed in `args`
 * is scrubbed from the error, because the failure message repeats the command.
 */
export async function wpCli(
  args: readonly string[],
  secrets: readonly string[] = [],
): Promise<string> {
  try {
    const { stdout } = await run('npx', ['wp-env', 'run', 'cli', 'wp', ...args], { cwd: REPO_ROOT })
    return stdout
  } catch (error) {
    const detail = error instanceof Error ? error.message : String(error)
    throw new Error(`WP-CLI failed in wp-env: ${redact(detail, secrets)}`)
  }
}

/** Run PHP in WordPress for its side effects. */
export async function wpEval(php: string, secrets: readonly string[] = []): Promise<void> {
  await wpCli(['eval', php], secrets)
}

/**
 * Evaluate a PHP expression in WordPress and return its value, JSON round-tripped.
 * The value may hold a secret, so a parse failure never quotes it.
 */
export async function wpEvalJson<T>(expression: string): Promise<T> {
  const stdout = await wpCli([
    'eval',
    `echo ${PHP_MARKER} . wp_json_encode(${expression}) . ${PHP_MARKER};`,
  ])
  const payload = stdout.split(MARKER)[1]
  try {
    return JSON.parse(payload ?? '') as T
  } catch {
    throw new Error('WP-CLI eval did not return a JSON value')
  }
}

/** Quote a string as a PHP single-quoted literal. */
export const phpString = (value: string): string =>
  `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`
