<?php

namespace Kalahamoon\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SettingsContractTest extends TestCase {

	private string $pluginDir;

	protected function setUp(): void {
		parent::setUp();
		$this->pluginDir = dirname( __DIR__, 2 );
	}

	public function test_all_settings_register_sanitize_callbacks(): void {
		$admin = (string) file_get_contents( $this->pluginDir . '/includes/admin/class-kalahamoon-admin.php' );
		preg_match_all( "/register_setting\\(\\s*'kalahamoon_settings'\\s*,\\s*'([^']+)'\\s*,\\s*array\\((.*?)\\)\\s*\\);/s", $admin, $matches, PREG_SET_ORDER );

		$settings = array();
		foreach ( $matches as $match ) {
			$settings[ $match[1] ] = $match[2];
		}

		$expected = array(
			'kalahamoon_api_key',
			'kalahamoon_api_url',
			'kalahamoon_organization_slug',
			'kalahamoon_webhook_secret',
			'kalahamoon_display_currency',
			'kalahamoon_clicks_retention',
			'kalahamoon_legacy_dark_mode',
			'kalahamoon_persian_numerals',
			'kalahamoon_display_unit',
			'kalahamoon_redirect_type',
			'kalahamoon_disclosure_text',
			'kalahamoon_sync_interval',
			'kalahamoon_catalog_authority',
		);

		foreach ( $expected as $name ) {
			$this->assertArrayHasKey( $name, $settings, "Missing register_setting for $name." );
			$this->assertStringContainsString( 'sanitize_callback', $settings[ $name ], "$name should declare sanitize_callback." );
		}
	}

	public function test_api_url_default_is_unified_across_connection_paths(): void {
		$files = array(
			'/kalahamoon.php',
			'/includes/class-kalahamoon-activator.php',
			'/includes/api/class-kalahamoon-api-client.php',
			'/includes/auth/class-kalahamoon-auth.php',
			'/includes/admin/class-kalahamoon-onboarding.php',
			'/includes/admin/class-kalahamoon-admin.php',
		);

		foreach ( $files as $file ) {
			$contents = (string) file_get_contents( $this->pluginDir . $file );
			$this->assertStringNotContainsString( 'http://localhost:3000', $contents, "$file should not hard-code localhost API defaults." );
		}

		$this->assertStringContainsString( "define( 'KALAHAMOON_DEFAULT_API_URL', 'https://app.kalahamoon.com' )", (string) file_get_contents( $this->pluginDir . '/kalahamoon.php' ) );
		$this->assertStringContainsString( 'kalahamoon_default_api_url', (string) file_get_contents( $this->pluginDir . '/includes/class-kalahamoon-activator.php' ) );
	}

	public function test_legacy_dark_mode_is_seeded_exposed_and_uninstalled(): void {
		$activator = (string) file_get_contents( $this->pluginDir . '/includes/class-kalahamoon-activator.php' );
		$admin     = (string) file_get_contents( $this->pluginDir . '/includes/admin/class-kalahamoon-admin.php' );
		$uninstall = (string) file_get_contents( $this->pluginDir . '/uninstall.php' );

		$this->assertStringContainsString( 'kalahamoon_legacy_dark_mode', $activator );
		$this->assertStringContainsString( 'kalahamoon_legacy_dark_mode', $admin );
		$this->assertStringContainsString( 'kalahamoon_legacy_dark_mode', $uninstall );
	}
}
