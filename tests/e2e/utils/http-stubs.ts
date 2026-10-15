/** Stub the local wp-env instance's outbound requests, through the test-only http-stub plugin. */

import { phpString, wpEval } from '@utils/wp-cli'

/** What a stubbed URL answers: a response, or a transport failure. */
export type HttpStub =
  { status?: number; headers?: Record<string, string>; body?: string } | { error: string }

/** Stubs by exact request URL. Any other request goes out as normal. */
export type HttpStubs = Record<string, HttpStub>

/** The plugin reads one option per URL, named this prefix plus the URL's md5. */
const HTTP_STUB_OPTION = phpString('ceros_e2e_http_stub_')

const phpJson = (value: unknown): string => `json_decode(${phpString(JSON.stringify(value))}, true)`

export const setHttpStubs = (stubs: HttpStubs): Promise<void> =>
  wpEval(
    `foreach (${phpJson(stubs)} as $url => $stub) { update_option(${HTTP_STUB_OPTION} . md5($url), $stub, false); }`,
  )

export const clearHttpStubs = (urls: readonly string[]): Promise<void> =>
  wpEval(`foreach (${phpJson(urls)} as $url) { delete_option(${HTTP_STUB_OPTION} . md5($url)); }`)
