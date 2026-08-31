<?php
/**
 * Tests for ceros_get_api_headers(), the headers on every outgoing Ceros API
 * request.
 *
 * Two of these are how an install is identified after the fact: the API version
 * it is pinned to, and its own version. An install whose behaviour differs has
 * to report differently, so a dropped header here is the difference between a
 * traceable report and a guess.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class ApiHeadersTest extends TestCase {

	public function test_sends_the_api_key_as_a_bearer_token() {
		$headers = ceros_get_api_headers( 'test-key' );
		$this->assertSame( 'Bearer test-key', $headers['Authorization'] );
	}

	public function test_sends_the_pinned_api_version() {
		$headers = ceros_get_api_headers( 'test-key' );
		$this->assertSame( CEROS_API_VERSION, $headers['X-Ceros-Api-Version'] );
	}

	public function test_sends_the_plugin_version() {
		// Without this nobody can tell which build made a call.
		$headers = ceros_get_api_headers( 'test-key' );
		$this->assertSame( CEROS_PLUGIN_VERSION, $headers['X-Ceros-Plugin-Version'] );
	}

	public function test_plugin_version_is_not_empty() {
		// A silently empty value is worse than a missing header: it looks reported.
		$headers = ceros_get_api_headers( 'test-key' );
		$this->assertNotSame( '', $headers['X-Ceros-Plugin-Version'] );
	}

	public function test_get_version_returns_the_constant() {
		$this->assertSame( CEROS_PLUGIN_VERSION, ceros_get_version() );
	}
}
