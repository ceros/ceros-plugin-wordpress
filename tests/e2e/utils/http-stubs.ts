/** Stub the local wp-env instance's outbound requests, through the test-only http-stub plugin. */

import { phpString, wpEval } from '@utils/wp-cli'

/** What a stubbed URL answers: a response, or a transport failure. */
export type HttpStub =
  { status?: number; headers?: Record<string, string>; body?: string } | { error: string }

/** Stubs by exact request URL. Any other request goes out as normal. */
export type HttpStubs = Record<string, HttpStub>

/** The option the plugin reads; it does nothing while the option is absent. */
const HTTP_STUBS_OPTION = 'ceros_e2e_http_stubs'

/** Replace every stub with `stubs`. */
export const setHttpStubs = (stubs: HttpStubs): Promise<void> =>
  wpEval(
    `update_option(${phpString(HTTP_STUBS_OPTION)}, json_decode(${phpString(JSON.stringify(stubs))}, true), false);`,
  )

export const clearHttpStubs = (): Promise<void> =>
  wpEval(`delete_option(${phpString(HTTP_STUBS_OPTION)});`)
