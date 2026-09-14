<?php
/**
 * Tests for the settings-page dedup of the stale-plugin message.
 *
 * A rejected save reports the retired pin through core's settings errors, but
 * only on production does that error carry the update-the-plugin text. On
 * staging it carries the raw HTTP body, so the notice still has to render.
 *
 * @package ceros
 */

use PHPUnit\Framework\TestCase;

final class VersionRejectionReportedTest extends TestCase {

	public function test_reports_a_production_settings_error() {
		$errors = [
			[
				'setting' => 'ceros_api_key',
				'code'    => 'ceros_api_version_unsupported',
				'message' => '[Production] ' . ceros_api_version_rejection_message(),
				'type'    => 'error',
			],
		];

		$this->assertTrue( ceros_version_rejection_reported( $errors ) );
	}

	public function test_leaves_a_staging_settings_error_to_the_notice() {
		$errors = [
			[
				'setting' => 'ceros_api_key',
				'code'    => 'ceros_api_version_unsupported',
				'message' => '[Staging] HTTP 400 — {"message":"Invalid API version","statusCode":400}',
				'type'    => 'error',
			],
		];

		$this->assertFalse( ceros_version_rejection_reported( $errors ) );
	}

	public function unreported() {
		return [
			'no errors'       => [ [] ],
			'bad key'         => [
				[
					[
						'setting' => 'ceros_api_key',
						'code'    => 'ceros_api_key_invalid',
						'message' => '[Production] The API key could not be verified.',
						'type'    => 'error',
					],
				],
			],
			'message missing' => [
				[
					[
						'setting' => 'ceros_api_key',
						'code'    => 'ceros_api_version_unsupported',
					],
				],
			],
		];
	}

	/**
	 * @dataProvider unreported
	 */
	public function test_leaves_other_states_to_the_notice( $errors ) {
		$this->assertFalse( ceros_version_rejection_reported( $errors ) );
	}
}
