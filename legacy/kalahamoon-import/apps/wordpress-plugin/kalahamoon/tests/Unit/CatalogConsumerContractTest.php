<?php

namespace Kalahamoon\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/api/class-kalahamoon-catalog-consumer.php';
require_once dirname( __DIR__, 2 ) . '/includes/core/class-kalahamoon-price-alert-mailer.php';

/**
 * The plugin is a generic read-only catalog consumer. These focused source
 * contracts keep its connector boundary explicit without importing any
 * publication-specific runtime assumptions into the product integration.
 */
final class CatalogConsumerContractTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( '__' )->alias( static fn( string $text ): string => $text );
		Functions\when( 'is_wp_error' )->alias( static fn( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $value ): string => trim( strip_tags( $value ) ) );
		Functions\when( 'esc_url_raw' )->alias( static fn( string $value ): string => $value );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_unslash' )->alias( static fn( string $value ): string => $value );
		Functions\when( 'wp_kses_post' )->alias( static fn( string $value ): string => $value );
		Functions\when( 'sanitize_title' )->alias( static fn( string $value ): string => strtolower( trim( $value ) ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function plugin( string $path ): string {
		$content = file_get_contents( dirname( __DIR__, 2 ) . '/' . $path );
		$this->assertNotFalse( $content, 'Unable to read ' . $path );
		return (string) $content;
	}

	public function test_connector_uses_the_generic_catalog_integration_contract(): void {
		$client   = $this->plugin( 'includes/api/class-kalahamoon-api-client.php' );
		$consumer = $this->plugin( 'includes/api/class-kalahamoon-catalog-consumer.php' );

		foreach ( array( '/api/integrations/catalog/v1/capabilities', '/api/integrations/catalog/v1/snapshot', '/api/integrations/catalog/v1/delivery-receipts' ) as $endpoint ) {
			$this->assertStringContainsString( $endpoint, $client );
		}
		$this->assertStringContainsString( 'catalog:read catalog:delivery:ack', $this->plugin( 'includes/auth/class-kalahamoon-auth.php' ) );
		$this->assertStringContainsString( "apply_filters( 'kalahamoon_catalog_consumer_mode', false )", $consumer );
	}

	public function test_consumer_origin_proof_is_fixed_generic_and_minimal(): void {
		$consumer = $this->plugin( 'includes/api/class-kalahamoon-catalog-consumer.php' );
		$plugin   = $this->plugin( 'includes/class-kalahamoon-plugin.php' );

		$this->assertStringContainsString( "'/.well-known/kalahamoon-publication-catalog-connector.json'", $consumer );
		$this->assertStringContainsString( "'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE'", $consumer );
		$this->assertStringContainsString( "array( 'challenge' => \$challenge )", $consumer );
		$this->assertStringContainsString( "Kalahamoon_Catalog_Consumer::init_origin_proof_endpoint();", $plugin );
		$this->assertStringContainsString( "add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_origin_proof' ), 0 )", $consumer );
	}

	public function test_consumer_origin_proof_requires_consumer_mode_and_a_valid_configured_challenge(): void {
		$consumer_mode = true;
		$challenge     = 'catalog_origin_' . str_repeat( 'a', 32 );
		$previous_challenge = getenv( 'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE' );
		Functions\when( 'get_option' )->alias( static function ( string $key, mixed $default = false ) use ( &$consumer_mode ) {
			return 'kalahamoon_catalog_consumer_mode' === $key ? $consumer_mode : $default;
		} );
		putenv( 'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE=' . $challenge );
		try {
			$this->assertSame( array( 'challenge' => $challenge ), \Kalahamoon_Catalog_Consumer::origin_proof_payload() );
			$this->assertTrue( \Kalahamoon_Catalog_Consumer::has_origin_proof_configuration() );

			putenv( 'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE=not-a-catalog-origin-challenge' );
			$this->assertNull( \Kalahamoon_Catalog_Consumer::origin_proof_payload() );

			putenv( 'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE=' . $challenge );
			$consumer_mode = false;
			$this->assertNull( \Kalahamoon_Catalog_Consumer::origin_proof_payload() );
		} finally {
			putenv( false === $previous_challenge ? 'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE' : 'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE=' . $previous_challenge );
		}
	}

	public function test_consumer_origin_proof_matches_only_its_fixed_contract_route(): void {
		$previous_request_uri = $_SERVER['REQUEST_URI'] ?? null;
		$method               = new \ReflectionMethod( \Kalahamoon_Catalog_Consumer::class, 'is_origin_proof_request' );

		try {
			$_SERVER['REQUEST_URI'] = '/.well-known/kalahamoon-publication-catalog-connector.json';
			$this->assertTrue( $method->invoke( null ) );

			$_SERVER['REQUEST_URI'] = '/.well-known/kalahamoon-publication-catalog-connector.json?unexpected=1';
			$this->assertFalse( $method->invoke( null ) );

			$_SERVER['REQUEST_URI'] = '/.well-known/kalahamoon-publication-catalog-connector.json/';
			$this->assertFalse( $method->invoke( null ) );

			$_SERVER['REQUEST_URI'] = 'https://untrusted.example/.well-known/kalahamoon-publication-catalog-connector.json';
			$this->assertFalse( $method->invoke( null ) );
		} finally {
			if ( null === $previous_request_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $previous_request_uri;
			}
		}
	}

	public function test_connector_requires_its_dedicated_catalog_grant(): void {
		$auth = $this->plugin( 'includes/auth/class-kalahamoon-auth.php' );

		$this->assertStringContainsString( "'client_id'     => self::client_id()", $auth );
		$this->assertStringContainsString( "in_array( 'catalog:read', \$scopes, true )", $auth );
		$this->assertStringContainsString( "in_array( 'catalog:delivery:ack', \$scopes, true )", $auth );
		$this->assertStringContainsString( '2 === count( $scopes )', $auth );
		$this->assertStringNotContainsString( 'kalahamoon_catalog_connector_client_secret', $auth );
		$this->assertStringContainsString( 'has_catalog_connector_grant', $auth );
		$this->assertStringContainsString( 'Kalahamoon_Token_Store::get_access_token()', $auth );
	}

	public function test_wordpress_only_activates_a_complete_staged_snapshot(): void {
		$consumer = $this->plugin( 'includes/api/class-kalahamoon-catalog-consumer.php' );
		$cache    = $this->plugin( 'includes/core/class-kalahamoon-product-cache.php' );

		$this->assertStringContainsString( 'validate_snapshot', $consumer );
		$this->assertStringContainsString( 'upsert_catalog_projection', $consumer );
		$this->assertStringContainsString( 'delete_catalog_snapshot( $key )', $consumer );
		$this->assertStringContainsString( 'update_option( self::SNAPSHOT_OPTION, $active )', $consumer );
		$this->assertStringContainsString( '_kalahamoon_catalog_snapshot_key', $cache );
		$this->assertStringContainsString( 'delete_catalog_snapshots_except', $cache );
	}

	public function test_connector_mode_does_not_expose_local_catalog_authoring(): void {
		$cache  = $this->plugin( 'includes/core/class-kalahamoon-product-cache.php' );
		$rest   = $this->plugin( 'includes/rest/class-kalahamoon-rest-controller.php' );
		$admin  = $this->plugin( 'includes/admin/class-kalahamoon-admin.php' );
		$plugin = $this->plugin( 'includes/class-kalahamoon-plugin.php' );

		$this->assertStringContainsString( "'kalahamoon_catalog_read_only'", $cache );
		$this->assertStringContainsString( 'Catalog publication is managed in Kalahamoon.', $rest );
		$this->assertStringContainsString( 'Catalog products are managed in Kalahamoon.', $admin );
		$this->assertStringContainsString( "\$block->supports['inserter'] = false", $plugin );
		$this->assertStringContainsString( 'if ( Kalahamoon_Catalog_Consumer::is_enabled() ) {', $plugin );
	}

	public function test_connector_render_paths_preserve_the_approved_projection_without_legacy_policy_recalculation(): void {
		$cache = $this->plugin( 'includes/core/class-kalahamoon-product-cache.php' );
		$rest  = $this->plugin( 'includes/rest/class-kalahamoon-rest-controller.php' );

		$this->assertMatchesRegularExpression(
			'/if \( class_exists\( \'Kalahamoon_Catalog_Consumer\' \) && Kalahamoon_Catalog_Consumer::is_enabled\(\) \) \{\s+\/\/ The active revision already contains Kalahamoon\'s publication decision\.\s+\/\/ Applying the legacy policy here would give this consumer a second chance\s+\/\/ to alter eligibility or price visibility after activation\.\s+return ! empty\( \$product\[\'publicReady\'\] \) \? \$product : null;/s',
			$cache
		);
		$this->assertStringContainsString( '$products[] = self::format_product( $post );', $cache );
		$this->assertStringContainsString( "if ( \$product && ! \$consumer && ! current_user_can( 'edit_posts' ) )", $rest );
	}

	public function test_connector_webhook_accepts_only_the_catalog_availability_signal(): void {
		$rest = $this->plugin( 'includes/rest/class-kalahamoon-rest-controller.php' );

		$this->assertStringContainsString( "if ( \$consumer && 'catalog.snapshot.available' !== \$event )", $rest );
		$this->assertStringContainsString( 'This catalog connector only accepts availability events.', $rest );
		$this->assertStringContainsString( 'Kalahamoon_Catalog_Consumer::record_available_snapshot( $data );', $rest );
		$this->assertStringContainsString( "do_action( 'kalahamoon_catalog_snapshot_available', \$data )", $rest );
		$this->assertMatchesRegularExpression(
			'/if \( \$consumer && \'catalog\.snapshot\.available\' !== \$event \) \{.*?return new WP_REST_Response\(.*?403 \);.*?\}\s+\$data = json_decode/s',
			$rest
		);
	}

	public function test_connector_mode_never_registers_or_runs_price_alert_processing(): void {
		Functions\when( 'get_option' )->alias( static function ( string $key, $default = false ) {
			return 'kalahamoon_catalog_consumer_mode' === $key ? true : $default;
		} );
		Functions\expect( 'add_action' )->never();

		\Kalahamoon_Price_Alert_Mailer::init();
		\Kalahamoon_Price_Alert_Mailer::run();

		$this->assertTrue( true );
	}

	public function test_connector_mode_leaves_public_visual_ownership_with_the_theme(): void {
		$plugin = $this->plugin( 'includes/class-kalahamoon-plugin.php' );

		$this->assertStringContainsString( 'historic global stylesheet, font, tracker', $plugin );
		$this->assertStringContainsString( 'wp_theme_json_data_default', $plugin );
		$this->assertStringContainsString( 'if ( ! Kalahamoon_Catalog_Consumer::is_enabled() )', $plugin );
	}

	public function test_connector_mode_has_no_runtime_content_or_duplicate_schema_pass(): void {
		$disclosure = $this->plugin( 'includes/core/class-kalahamoon-disclosure.php' );
		$auto_link  = $this->plugin( 'includes/core/class-kalahamoon-auto-linker.php' );
		$schema     = $this->plugin( 'includes/core/class-kalahamoon-schema-output.php' );

		$this->assertStringContainsString( 'must not rewrite published page content at render time', $disclosure );
		$this->assertStringContainsString( 'never a request-time content transformation', $auto_link );
		$this->assertStringContainsString( 'duplicate Product markup', $schema );
	}

	public function test_delivery_receipts_are_signed_with_the_exact_bearer_authenticated_body(): void {
		$client   = $this->plugin( 'includes/api/class-kalahamoon-api-client.php' );
		$consumer = $this->plugin( 'includes/api/class-kalahamoon-catalog-consumer.php' );

		$this->assertStringContainsString( "wp_json_encode( \$receipt )", $client );
		$this->assertStringContainsString( "hash_hmac( 'sha256', \$body, \$token )", $client );
		$this->assertStringContainsString( "'X-Kalahamoon-Catalog-Signature'", $client );
		$this->assertStringContainsString( "'' === trim( \$token )", $client );
		$this->assertStringContainsString( "if ( ! ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) )", $client );
		$this->assertStringNotContainsString( 'KALAHAMOON_CATALOG_DELIVERY_SECRET', $consumer );
	}

	public function test_known_delivery_failures_use_only_signed_fixed_codes(): void {
		$client   = $this->plugin( 'includes/api/class-kalahamoon-api-client.php' );
		$consumer = $this->plugin( 'includes/api/class-kalahamoon-catalog-consumer.php' );

		$this->assertStringContainsString( 'report_catalog_delivery_failure', $client );
		$this->assertStringContainsString( "'X-Kalahamoon-Catalog-Signature'", $client );
		$this->assertStringContainsString( "'outcome'     => 'FAILED'", $consumer );
		$this->assertStringContainsString( "'SNAPSHOT_VALIDATION_FAILED'", $consumer );
		$this->assertStringContainsString( "'SNAPSHOT_STAGING_FAILED'", $consumer );
		$this->assertStringContainsString( "'PUBLIC_RENDER_VERIFICATION_FAILED'", $consumer );
		$this->assertStringContainsString( 'known_snapshot_from_payload', $consumer );
		$this->assertStringContainsString( 'report_delivery_failure( $snapshot', $consumer );
	}

	public function test_projection_requires_the_explicit_primary_offer_and_hides_stale_offer_prices(): void {
		$consumer = $this->plugin( 'includes/api/class-kalahamoon-catalog-consumer.php' );

		$this->assertStringContainsString( 'normalize_primary_offer', $consumer );
		$this->assertStringContainsString( "'kalahamoon_catalog_primary_offer_missing'", $consumer );
		$this->assertStringContainsString( "'VISIBLE' === \$visibility ? self::normalize_offers", $consumer );
		$this->assertStringContainsString( "'HIDDEN_STALE' === \$visibility", $consumer );
	}

	public function test_v1_projection_keeps_approved_scalar_comparison_offers_in_order(): void {
		$projection = \Kalahamoon_Catalog_Consumer::normalize_projection_item( array(
			'id'              => 'catalog-item-1',
			'state'           => 'PUBLISHED',
			'title'           => 'Chair',
			'imageUrl'        => 'https://images.example.test/chair.jpg',
			'destinationUrl'  => 'https://merchant.example.test/chair',
			'price'           => array( 'amount' => 100, 'currency' => 'IRR', 'observedAt' => '2026-08-23T00:00:00Z' ),
			'priceVisibility' => 'VISIBLE',
			'primaryOffer'    => array( 'id' => 'offer-primary', 'platform' => 'merchant', 'listingUrl' => 'https://merchant.example.test/chair' ),
			'offers'          => array(
				array( 'price' => 105, 'currency' => 'IRR', 'listingUrl' => 'https://comparison.example.test/chair-a', 'observedAt' => '2026-08-23T00:00:00Z' ),
				array( 'price' => 110, 'currency' => 'IRR', 'listingUrl' => 'https://comparison.example.test/chair-b', 'observedAt' => '2026-08-23T00:00:00Z' ),
			),
		) );

		$this->assertIsArray( $projection );
		$this->assertSame( 'offer-primary', $projection['primaryOffer']['id'] );
		$this->assertSame( 'https://merchant.example.test/chair', $projection['primaryOffer']['listingUrl'] );
		$this->assertSame( array( 100.0, 105.0, 110.0 ), array_column( $projection['offers'], 'price' ) );
		$this->assertSame(
			array(
				'https://merchant.example.test/chair',
				'https://comparison.example.test/chair-a',
				'https://comparison.example.test/chair-b',
			),
			array_column( $projection['offers'], 'listingUrl' )
		);
	}

	public function test_v1_projection_rejects_missing_primary_offer_and_removes_stale_comparison_prices(): void {
		$base = array(
			'id'              => 'catalog-item-2',
			'state'           => 'PUBLISHED',
			'title'           => 'Table',
			'imageUrl'        => 'https://images.example.test/table.jpg',
			'destinationUrl'  => 'https://merchant.example.test/table',
			'price'           => array( 'amount' => 200, 'currency' => 'IRR', 'observedAt' => '2026-08-23T00:00:00Z' ),
			'priceVisibility' => 'VISIBLE',
			'offers'          => array( array( 'price' => 220, 'currency' => 'IRR', 'listingUrl' => 'https://comparison.example.test/table' ) ),
		);

		$missing_primary = \Kalahamoon_Catalog_Consumer::normalize_projection_item( $base );
		$this->assertInstanceOf( \WP_Error::class, $missing_primary );
		$this->assertSame( 'kalahamoon_catalog_primary_offer_missing', $missing_primary->get_error_code() );

		$stale = \Kalahamoon_Catalog_Consumer::normalize_projection_item( array_merge( $base, array(
			'priceVisibility' => 'HIDDEN_STALE',
			'primaryOffer'    => array( 'id' => 'offer-primary', 'platform' => 'merchant', 'listingUrl' => 'https://merchant.example.test/table' ),
		) ) );
		$this->assertIsArray( $stale );
		$this->assertNull( $stale['price'] );
		$this->assertSame( array(), $stale['offers'] );
		$this->assertSame( 'https://merchant.example.test/table', $stale['listingUrl'] );
	}

	public function test_empty_snapshot_requires_an_explicit_validated_withdrawal_directive(): void {
		$base = array(
			'version'  => 'v1',
			'snapshot' => array( 'id' => 'snapshot-2', 'revision' => 'revision-2', 'generatedAt' => '2026-08-23T00:00:00Z' ),
			'items'    => array(),
		);
		$active = array(
			'key'     => 'previous-active-snapshot',
			'count'   => 2,
			'itemIds' => array( 'catalog-item-1', 'catalog-item-2' ),
		);

		$ordinary_empty = \Kalahamoon_Catalog_Consumer::validate_snapshot( $base, $active );
		$this->assertInstanceOf( \WP_Error::class, $ordinary_empty );
		$this->assertSame( 'kalahamoon_catalog_withdrawal_invalid', $ordinary_empty->get_error_code() );

		$withdrawal = \Kalahamoon_Catalog_Consumer::validate_snapshot( array_merge( $base, array(
			'withdrawnItemIds' => array( 'catalog-item-1', 'catalog-item-2' ),
		) ), $active );
		$this->assertIsArray( $withdrawal );
		$this->assertSame( array(), $withdrawal['items'] );
		$this->assertSame( array( 'catalog-item-1', 'catalog-item-2' ), $withdrawal['snapshot']['withdrawnItemIds'] );
	}

	public function test_empty_snapshot_rejects_invalid_or_duplicate_withdrawal_directives(): void {
		$base = array(
			'version'  => 'v1',
			'snapshot' => array( 'id' => 'snapshot-3', 'revision' => 'revision-3', 'generatedAt' => '2026-08-23T00:00:00Z' ),
			'items'    => array(),
		);

		$active = array(
			'key'     => 'previous-active-snapshot',
			'count'   => 1,
			'itemIds' => array( 'catalog-item-1' ),
		);

		$empty_directive = \Kalahamoon_Catalog_Consumer::validate_snapshot( array_merge( $base, array( 'withdrawnItemIds' => array() ) ), $active );
		$this->assertInstanceOf( \WP_Error::class, $empty_directive );
		$this->assertSame( 'kalahamoon_catalog_snapshot_withdrawal_mismatch', $empty_directive->get_error_code() );

		$duplicate_directive = \Kalahamoon_Catalog_Consumer::validate_snapshot( array_merge( $base, array( 'withdrawnItemIds' => array( 'catalog-item-1', 'catalog-item-1' ) ) ), $active );
		$this->assertInstanceOf( \WP_Error::class, $duplicate_directive );
		$this->assertSame( 'kalahamoon_catalog_withdrawal_duplicate', $duplicate_directive->get_error_code() );
	}

	public function test_delivery_receipts_require_anonymous_public_render_evidence(): void {
		$consumer = $this->plugin( 'includes/api/class-kalahamoon-catalog-consumer.php' );

		$this->assertStringContainsString( "apply_filters( 'kalahamoon_catalog_public_render_urls', array(), \$snapshot )", $consumer );
		$this->assertStringContainsString( 'wp_remote_get( $url', $consumer );
		$this->assertStringContainsString( "'cookies'            => array()", $consumer );
		$this->assertStringContainsString( "'redirection'        => 0", $consumer );
		$this->assertStringContainsString( "'reject_unsafe_urls' => true", $consumer );
		$this->assertStringContainsString( "self::safe_public_https_url( home_url( '/' ) )", $consumer );
		$this->assertStringContainsString( 'is_exact_self_origin_public_url', $consumer );
		$this->assertStringContainsString( "'kalahamoon_catalog_render_evidence_redirected'", $consumer );
		$this->assertStringContainsString( '$code >= 300 && $code < 400', $consumer );
		$this->assertStringContainsString( "'<meta name=\"kalahamoon-catalog-revision\" content=\"'", $consumer );
		$this->assertStringContainsString( 'has not rendered the active catalog revision', $consumer );
		$this->assertStringContainsString( "'renderedUrls' => \$rendered_urls", $consumer );
		$this->assertStringContainsString( 'kalahamoon_catalog_render_evidence_missing', $consumer );
		$this->assertStringNotContainsString( "409 === (int) ( \$data['status'] ?? 0 )", $consumer );
	}

	public function test_delivery_evidence_rejects_non_self_origins_without_following_redirects(): void {
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://consumer.example' . $path );

		$method = new \ReflectionMethod( \Kalahamoon_Catalog_Consumer::class, 'is_exact_self_origin_public_url' );
		$this->assertTrue( $method->invoke( null, 'https://consumer.example/shop/' ) );
		$this->assertTrue( $method->invoke( null, 'https://consumer.example:443/shop/' ) );
		$this->assertFalse( $method->invoke( null, 'https://catalog.example/shop/' ) );
		$this->assertFalse( $method->invoke( null, 'https://consumer.example:8443/shop/' ) );
		$this->assertFalse( $method->invoke( null, 'https://consumer.example/shop/#not-requested' ) );
		$this->assertFalse( $method->invoke( null, 'http://consumer.example/shop/' ) );
	}

	public function test_active_snapshot_readiness_requires_a_coherent_pointer_and_matching_delivery_receipt(): void {
		$revision = str_repeat( 'a', 64 );
		$snapshot = array(
			'id'          => 'snapshot-1',
			'revision'    => $revision,
			'key'         => \Kalahamoon_Catalog_Consumer::snapshot_key( 'snapshot-1', $revision ),
			'generatedAt' => '2026-08-23T00:00:00Z',
			'activatedAt' => '2026-08-23T00:01:00Z',
			'count'       => 1,
			'itemIds'     => array( 'catalog-item-1' ),
		);
		$options = array(
			'kalahamoon_catalog_active_snapshot'            => $snapshot,
			'kalahamoon_catalog_last_confirmed_delivery' => array(
				'status'   => 'ACTIVE',
				'snapshot' => 'snapshot-1',
				'revision' => $revision,
				'at'       => '2026-08-23T00:01:30Z',
			),
		);
		Functions\when( 'get_option' )->alias( static function ( string $key, $default = false ) use ( &$options ) {
			return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
		} );

		$this->assertTrue( \Kalahamoon_Catalog_Consumer::has_valid_active_snapshot() );
		$this->assertTrue( \Kalahamoon_Catalog_Consumer::has_confirmed_active_delivery() );

		$options['kalahamoon_catalog_last_confirmed_delivery']['revision'] = str_repeat( 'b', 64 );
		$this->assertFalse( \Kalahamoon_Catalog_Consumer::has_confirmed_active_delivery() );

		$options['kalahamoon_catalog_active_snapshot']['itemIds'] = array( 'catalog-item-1', 'catalog-item-1' );
		$options['kalahamoon_catalog_active_snapshot']['count']   = 2;
		$this->assertFalse( \Kalahamoon_Catalog_Consumer::has_valid_active_snapshot() );
	}

	public function test_connector_exposes_a_server_scheduler_hook_without_reenabling_wp_cron(): void {
		$admin     = $this->plugin( 'includes/admin/class-kalahamoon-admin.php' );
		$activator = $this->plugin( 'includes/class-kalahamoon-activator.php' );

		$this->assertStringContainsString( "add_action( 'kalahamoon_catalog_consumer_sync'", $admin );
		$this->assertStringContainsString( 'run_catalog_consumer_sync', $admin );
		$this->assertStringContainsString( 'Next expected refresh:', $admin );
		$this->assertStringContainsString( 'kalahamoon_catalog_refresh_interval_minutes', $this->plugin( 'includes/api/class-kalahamoon-catalog-consumer.php' ) );
		$this->assertStringContainsString( "wp_clear_scheduled_hook( 'kalahamoon_sync_products' )", $activator );
		$this->assertMatchesRegularExpression( '/if \( ! \$consumer \) \{\s+if \( ! wp_next_scheduled\( \'kalahamoon_purge_clicks\'/s', $activator );
	}
}
