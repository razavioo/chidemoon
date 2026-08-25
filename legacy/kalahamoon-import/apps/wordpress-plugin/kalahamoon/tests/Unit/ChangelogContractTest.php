<?php

namespace Kalahamoon\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ChangelogContractTest extends TestCase {

	private string $pluginDir;

	protected function setUp(): void {
		parent::setUp();
		$this->pluginDir = dirname( __DIR__, 2 );
	}

	private function plugin( string $rel ): string {
		return (string) file_get_contents( $this->pluginDir . '/' . $rel );
	}

	public function test_plugin_version_readme_stable_tag_and_constant_match(): void {
		$main   = $this->plugin( 'kalahamoon.php' );
		$readme = $this->plugin( 'readme.txt' );

		preg_match( '/Version:\s*([0-9.]+)/', $main, $header );
		preg_match( "/KALAHAMOON_VERSION',\s*'([^']+)'/", $main, $constant );
		preg_match( '/Stable tag:\s*([0-9.]+)/', $readme, $stable );

		$this->assertNotEmpty( $header[1] ?? '' );
		$this->assertSame( $header[1], $constant[1] ?? null );
		$this->assertSame( $header[1], $stable[1] ?? null );
		$this->assertStringContainsString( '= ' . $header[1] . ' =', $readme );
	}

	public function test_changelog_page_registers_menu_notice_and_nonce_dismissal(): void {
		$changelog = $this->plugin( 'includes/admin/class-kalahamoon-changelog.php' );

		$this->assertStringContainsString( 'class Kalahamoon_Changelog', $changelog );
		$this->assertStringContainsString( 'add_submenu_page', $changelog );
		$this->assertStringContainsString( 'kalahamoon-changelog', $changelog );
		$this->assertStringContainsString( "'edit_posts'", $changelog );
		$this->assertStringContainsString( 'KALAHAMOON_VERSION', $changelog );
		$this->assertStringContainsString( 'kalahamoon_changelog_seen_version', $changelog );
		$this->assertStringContainsString( 'check_ajax_referer', $changelog );
		$this->assertStringContainsString( 'current_user_can', $changelog );
	}

	public function test_changelog_is_loaded_upgraded_and_uninstalled(): void {
		$plugin    = $this->plugin( 'includes/class-kalahamoon-plugin.php' );
		$activator = $this->plugin( 'includes/class-kalahamoon-activator.php' );
		$uninstall = $this->plugin( 'uninstall.php' );

		$this->assertStringContainsString( 'class-kalahamoon-changelog.php', $plugin );
		$this->assertStringContainsString( 'Kalahamoon_Changelog::init', $plugin );
		$this->assertStringContainsString( 'kalahamoon_changelog_notice_version', $activator );
		$this->assertStringContainsString( 'kalahamoon_changelog_notice_version', $uninstall );
		$this->assertStringContainsString( 'kalahamoon_changelog_seen_version', $uninstall );
		$this->assertStringContainsString( 'kalahamoon_changelog_dismissed_version', $uninstall );
	}
}
