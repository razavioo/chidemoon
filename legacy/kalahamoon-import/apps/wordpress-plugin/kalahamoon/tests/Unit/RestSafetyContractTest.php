<?php

namespace Kalahamoon\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RestSafetyContractTest extends TestCase {
	private string $rest;

	protected function setUp(): void {
		parent::setUp();
		$this->rest = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/rest/class-kalahamoon-rest-controller.php' );
	}

	public function test_ai_routes_honor_a_valid_requested_language_and_mark_outputs_as_drafts(): void {
		$this->assertStringNotContainsString( '$language = \'en\';', $this->rest );
		$this->assertStringContainsString( 'resolve_language', $this->rest );
		$this->assertStringContainsString( "'draft'", $this->rest );
		$this->assertStringContainsString( "'provenance'", $this->rest );
	}

	public function test_generated_image_import_uses_bounded_safe_fetching_and_image_validation(): void {
		$this->assertStringContainsString( 'Kalahamoon_Image_Policy::download_remote', $this->rest );
		$this->assertStringContainsString( 'Kalahamoon_Image_Policy::decode_data_uri', $this->rest );
		$this->assertStringContainsString( 'is_supported_generated_image_reference', $this->rest );
		$this->assertStringContainsString( 'trusted_generated_image_download_url', $this->rest );
		$this->assertStringContainsString( 'download_trusted_internal', $this->rest );
		$this->assertStringNotContainsString( 'download_url( $image_url )', $this->rest );
		$this->assertStringContainsString( '_kalahamoon_ai_provenance', $this->rest );
	}

	public function test_webhooks_fail_closed_when_no_shared_secret_is_configured(): void {
		$this->assertStringContainsString( 'kalahamoon_webhook_not_configured', $this->rest );
		$this->assertStringContainsString( "'Webhook signing is not configured.'", $this->rest );
	}

	public function test_consumer_webhooks_reject_unrelated_signed_events_before_dispatch(): void {
		$this->assertStringContainsString( "if ( \$consumer && 'catalog.snapshot.available' !== \$event )", $this->rest );
		$this->assertStringContainsString( 'This catalog connector only accepts availability events.', $this->rest );
		$this->assertStringContainsString( "do_action( 'kalahamoon_webhook_' . sanitize_key( \$event ?? 'unknown' ), \$data );", $this->rest );
		$this->assertMatchesRegularExpression(
			'/if \( \$consumer && \'catalog\.snapshot\.available\' !== \$event \) \{.*?403 \);.*?\}\s+\$data = json_decode/s',
			$this->rest
		);
	}

	public function test_leads_require_consent_and_return_a_request_identifier(): void {
		$this->assertStringContainsString( "'consent'", $this->rest );
		$this->assertStringContainsString( "'intent'", $this->rest );
		$this->assertStringContainsString( "'sourceRef'", $this->rest );
		$this->assertStringContainsString( "'requestId'", $this->rest );
	}

	public function test_price_alert_confirmation_and_unsubscribe_routes_are_registered(): void {
		$this->assertStringContainsString( "'/price-alerts/confirm'", $this->rest );
		$this->assertStringContainsString( "'/price-alerts/unsubscribe'", $this->rest );
	}

	public function test_lead_context_is_rejected_when_it_cannot_be_normalized_safely(): void {
		$this->assertStringContainsString( 'normalize_lead_context', $this->rest );
		$this->assertStringContainsString( 'is_wp_error( $context )', $this->rest );
	}

	public function test_price_alert_confirmation_checks_the_conditional_update_result(): void {
		$this->assertStringContainsString( '$updated = $wpdb->update(', $this->rest );
		$this->assertStringContainsString( '1 !== $updated', $this->rest );
	}

	public function test_price_alert_delivery_claims_each_subscription_before_sending(): void {
		$mailer    = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/core/class-kalahamoon-price-alert-mailer.php' );
		$activator = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-kalahamoon-activator.php' );

		$this->assertStringContainsString( "'status' => 'processing'", $mailer );
		$this->assertStringContainsString( "array( 'id' => \$alert['id'], 'status' => 'active' )", $mailer );
		$this->assertStringContainsString( 'processing_at', $mailer );
		$this->assertStringContainsString( 'processing_at datetime', $activator );
	}

	public function test_new_price_alert_subscriptions_have_a_concurrency_key(): void {
		$activator = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-kalahamoon-activator.php' );

		$this->assertStringContainsString( 'subscription_key char(64)', $activator );
		$this->assertStringContainsString( 'UNIQUE KEY uniq_subscription', $activator );
		$this->assertStringContainsString( "'subscription_key'", $this->rest );
	}

	public function test_rate_limits_only_trust_forwarded_addresses_when_runtime_opts_in(): void {
		$this->assertStringContainsString( 'rate_limit_identity', $this->rest );
		$this->assertStringContainsString( 'KALAHAMOON_TRUSTED_PROXY_HEADERS', $this->rest );
		$this->assertSame( 3, substr_count( $this->rest, 'self::rate_limit_identity()' ) );
	}

	public function test_catalog_pruning_requires_a_complete_error_free_sync(): void {
		$products = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/api/class-kalahamoon-api-products.php' );
		$admin    = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/admin/class-kalahamoon-admin.php' );

		$this->assertStringContainsString( '$expected_total', $products );
		$this->assertStringContainsString( '$sync_complete', $products );
		$this->assertMatchesRegularExpression( '/if \( \$sync_complete \).*delete_missing_ids/s', $products );
		$this->assertStringContainsString( 'kalahamoon_allow_empty_catalog_prune', $products );
		$this->assertStringContainsString( "empty( \$result['complete'] )", $admin );
		$this->assertStringContainsString( "empty( \$result['complete'] ) ? 502 : 200", $this->rest );
	}
}
