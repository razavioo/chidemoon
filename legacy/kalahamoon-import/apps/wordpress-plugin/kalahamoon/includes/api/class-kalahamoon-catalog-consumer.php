<?php
/**
 * Read-only catalog projection consumer.
 *
 * Kalahamoon decides which products are publishable. WordPress only validates
 * that a complete, safe projection arrived and then atomically switches the
 * active cache pointer. Keeping this boundary here prevents a second product
 * publication workflow from slowly reappearing in the site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Kalahamoon_Catalog_Consumer {
	private const SNAPSHOT_OPTION      = 'kalahamoon_catalog_active_snapshot';
	private const LAST_SYNC_OPTION     = 'kalahamoon_catalog_last_sync';
	private const LAST_DELIVERY_OPTION = 'kalahamoon_catalog_last_delivery';
	private const LAST_CONFIRMED_DELIVERY_OPTION = 'kalahamoon_catalog_last_confirmed_delivery';
	private const AVAILABLE_OPTION     = 'kalahamoon_catalog_available_snapshot';
	private const LOCK_OPTION          = 'kalahamoon_catalog_sync_lock';
	private const LOCK_TTL             = 300;
	private const ORIGIN_PROOF_PATH    = '/.well-known/kalahamoon-publication-catalog-connector.json';
	private const ORIGIN_PROOF_ENV     = 'KALAHAMOON_CATALOG_CONNECTOR_ORIGIN_CHALLENGE';
	private const DELIVERY_FAILURE_CODES = array(
		'SNAPSHOT_VALIDATION_FAILED',
		'SNAPSHOT_STAGING_FAILED',
		'PUBLIC_RENDER_VERIFICATION_FAILED',
	);

	private Kalahamoon_API_Client $client;

	public function __construct( ?Kalahamoon_API_Client $client = null ) {
		$this->client = $client ?: new Kalahamoon_API_Client();
	}

	/**
	 * The consumer mode is deliberately local configuration. The integration API
	 * remains brand-neutral: Kalahamoon does not need to know which WordPress
	 * publication consumes its catalog.
	 */
	public static function is_enabled(): bool {
		if ( defined( 'KALAHAMOON_CATALOG_CONSUMER_MODE' ) ) {
			return (bool) KALAHAMOON_CATALOG_CONSUMER_MODE;
		}

		$absent     = '__kalahamoon_catalog_consumer_mode_absent__';
		$configured = function_exists( 'get_option' ) ? get_option( 'kalahamoon_catalog_consumer_mode', $absent ) : $absent;
		// An explicit false is an operator choice. A reusable plugin must not
		// infer connector ownership from the hostname of the publication.
		if ( $absent !== $configured ) {
			return (bool) $configured;
		}

		return (bool) apply_filters( 'kalahamoon_catalog_consumer_mode', false );
	}

	/**
	 * The ownership proof is a fixed public document rather than a callback URL.
	 * Register it independently of rewrite rules so a connector can prove a new
	 * deployment before an operator saves permalinks or activates the plugin again.
	 */
	public static function init_origin_proof_endpoint(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_origin_proof' ), 0 );
	}

	/**
	 * @return array{challenge:string}|null
	 */
	public static function origin_proof_payload(): ?array {
		if ( ! self::is_enabled() ) {
			return null;
		}

		$challenge = self::configured_origin_proof_challenge();
		return '' === $challenge ? null : array( 'challenge' => $challenge );
	}

	public static function has_origin_proof_configuration(): bool {
		return null !== self::origin_proof_payload();
	}

	/**
	 * Serve only the configured public challenge. The connector credential and
	 * all catalog state stay private even though the challenge is intentionally
	 * fetchable by Kalahamoon's ownership verifier.
	 */
	public static function maybe_serve_origin_proof(): void {
		if ( ! self::is_origin_proof_request() ) {
			return;
		}

		$payload = self::origin_proof_payload();
		if ( null === $payload ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8', true );
		header( 'Cache-Control: no-store, max-age=0', true );
		header( 'X-Content-Type-Options: nosniff', true );
		echo wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
		exit;
	}

	public static function active_snapshot(): array {
		$snapshot = get_option( self::SNAPSHOT_OPTION, array() );
		return is_array( $snapshot ) ? $snapshot : array();
	}

	public static function active_snapshot_key(): string {
		return (string) ( self::active_snapshot()['key'] ?? '' );
	}

	public static function active_snapshot_revision(): string {
		return (string) ( self::active_snapshot()['revision'] ?? '' );
	}

	/**
	 * A readiness check must distinguish a persisted option from the complete
	 * pointer that the consumer can safely render. This is structural validation
	 * only: it never re-evaluates catalog eligibility or source facts.
	 */
	public static function has_valid_active_snapshot(): bool {
		return self::is_valid_active_snapshot( self::active_snapshot() );
	}

	/**
	 * @param array<string,mixed> $snapshot
	 */
	private static function is_valid_active_snapshot( array $snapshot ): bool {
		$id       = self::safe_identifier( $snapshot['id'] ?? '' );
		$revision = strtolower( self::safe_identifier( $snapshot['revision'] ?? '' ) );
		$key      = self::safe_identifier( $snapshot['key'] ?? '' );

		if (
			'' === $id
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $revision )
			|| '' === $key
			|| ! hash_equals( self::snapshot_key( $id, $revision ), $key )
			|| ! self::is_safe_timestamp( $snapshot['generatedAt'] ?? '' )
			|| ! self::is_safe_timestamp( $snapshot['activatedAt'] ?? '' )
			|| ! self::is_snapshot_count( $snapshot['count'] ?? null )
			|| ! array_key_exists( 'itemIds', $snapshot )
			|| ! is_array( $snapshot['itemIds'] )
		) {
			return false;
		}

		$item_ids = self::active_snapshot_item_ids( $snapshot );
		return ! is_wp_error( $item_ids ) && count( $item_ids ) === (int) $snapshot['count'];
	}

	/**
	 * An active cache pointer becomes live delivery evidence only after the
	 * anonymous-render receipt for that same immutable revision is confirmed.
	 */
	public static function has_confirmed_active_delivery(): bool {
		$snapshot = self::active_snapshot();
		if ( ! self::is_valid_active_snapshot( $snapshot ) ) {
			return false;
		}

		$delivery = self::safe_status_option( self::LAST_CONFIRMED_DELIVERY_OPTION );
		return 'ACTIVE' === strtoupper( (string) ( $delivery['status'] ?? '' ) )
			&& hash_equals( (string) $snapshot['id'], (string) ( $delivery['snapshot'] ?? '' ) )
			&& hash_equals( strtolower( (string) $snapshot['revision'] ), strtolower( (string) ( $delivery['revision'] ?? '' ) ) )
			&& self::is_safe_timestamp( $delivery['at'] ?? '' );
	}

	public static function snapshot_key( string $id, string $revision ): string {
		return hash( 'sha256', $id . '|' . $revision );
	}

	public static function status(): array {
		$last_sync = self::safe_status_option( self::LAST_SYNC_OPTION );
		return array(
			'activeSnapshot' => self::active_snapshot(),
			'lastSync'       => $last_sync,
			'lastDelivery'   => self::safe_status_option( self::LAST_DELIVERY_OPTION ),
			'lastConfirmedDelivery' => self::safe_status_option( self::LAST_CONFIRMED_DELIVERY_OPTION ),
			'nextExpectedRefresh' => (string) ( $last_sync['nextExpectedAt'] ?? '' ),
			'available'      => self::safe_status_option( self::AVAILABLE_OPTION ),
		);
	}

	/**
	 * Webhooks only advertise a revision. Pulling happens in the scheduled or
	 * explicitly requested sync so a signed notification cannot create a partial
	 * catalog while a visitor is loading a page.
	 */
	public static function record_available_snapshot( array $payload ): void {
		$snapshot = is_array( $payload['snapshot'] ?? null ) ? $payload['snapshot'] : $payload;
		$id       = self::safe_identifier( $snapshot['id'] ?? $snapshot['snapshotId'] ?? '' );
		$revision = self::safe_identifier( $snapshot['revision'] ?? '' );
		if ( '' === $id || '' === $revision ) {
			return;
		}

		update_option( self::AVAILABLE_OPTION, array(
			'id'          => $id,
			'revision'    => $revision,
			'receivedAt'  => gmdate( 'c' ),
		) );
	}

	/**
	 * Pull, stage, validate, and activate a complete snapshot. The active pointer
	 * is changed only after every item has been stored successfully.
	 *
	 * @return array{synced:int,errors:int,complete:bool,activated:bool,deliveryAcknowledged:bool,message:string}
	 */
	public function sync(): array {
		if ( ! $this->client->is_connected() ) {
			return $this->failure( __( 'Not connected to Kalahamoon. Connect the catalog connector first.', 'kalahamoon' ) );
		}

		$lock = $this->acquire_lock();
		if ( is_wp_error( $lock ) ) {
			return $this->failure( $lock->get_error_message() );
		}

		try {
			$active = self::active_snapshot();
			$cursor = (string) ( $active['revision'] ?? '' );
			$result = $this->client->get_catalog_snapshot( $cursor, $active );
			if ( is_wp_error( $result ) ) {
				return $this->failure( $result->get_error_message() );
			}

			$known_snapshot = self::known_snapshot_from_payload( $result );
			$validated = self::validate_snapshot( $result, $active );
			if ( is_wp_error( $validated ) ) {
				return $this->failure( $validated->get_error_message(), 0, $known_snapshot, 'SNAPSHOT_VALIDATION_FAILED' );
			}

			$snapshot = $validated['snapshot'];
			$items    = $validated['items'];
			$key      = self::snapshot_key( $snapshot['id'], $snapshot['revision'] );
			if ( $key === (string) ( $active['key'] ?? '' ) ) {
				$acknowledged = $this->acknowledge_delivery( $snapshot );
				$this->record_sync_status( 'ACTIVE', (int) ( $active['count'] ?? 0 ) );
				return array(
					'synced'               => 0,
					'errors'               => $acknowledged ? 0 : 1,
					'complete'             => true,
					'activated'            => false,
					'deliveryAcknowledged' => $acknowledged,
					'message'              => $acknowledged ? __( 'The catalog snapshot is already active.', 'kalahamoon' ) : __( 'The catalog snapshot is active, but its delivery receipt could not be confirmed.', 'kalahamoon' ),
				);
			}

			$staged = 0;
			foreach ( $items as $item ) {
				if ( ! Kalahamoon_Product_Cache::upsert_catalog_projection( $item, $snapshot ) ) {
					Kalahamoon_Product_Cache::delete_catalog_snapshot( $key );
					return $this->failure( __( 'The catalog snapshot could not be staged. The previous public catalog remains active.', 'kalahamoon' ), $staged, $snapshot, 'SNAPSHOT_STAGING_FAILED' );
				}
				$staged++;
			}

			// This option is the only visibility switch. A request either sees every
			// row of the old revision or every row of the fully staged new revision.
			$activated_at = gmdate( 'c' );
			$active       = array(
				'id'          => $snapshot['id'],
				'revision'    => $snapshot['revision'],
				'key'         => $key,
				'generatedAt' => $snapshot['generatedAt'],
				'activatedAt' => $activated_at,
				'count'       => $staged,
				'itemIds'     => array_values( array_map( static fn( array $item ): string => (string) $item['id'], $items ) ),
				'withdrawnItemIds' => $snapshot['withdrawnItemIds'] ?? array(),
			);
			update_option( self::SNAPSHOT_OPTION, $active );
			update_option( 'kalahamoon_last_sync', current_time( 'mysql' ) );
			$this->record_sync_status( 'ACTIVE', $staged, '', $activated_at );
			delete_option( self::AVAILABLE_OPTION );

			do_action( 'kalahamoon_catalog_snapshot_activated', $active );
			// Keep prior revisions by default so a rollout can be inspected or rolled
			// back without refetching. Operators may opt into bounded cleanup later.
			if ( apply_filters( 'kalahamoon_catalog_prune_snapshots', false, $active ) ) {
				Kalahamoon_Product_Cache::delete_catalog_snapshots_except( $key );
			}

			$acknowledged = $this->acknowledge_delivery( $snapshot, $activated_at );
			return array(
				'synced'               => $staged,
				'errors'               => $acknowledged ? 0 : 1,
				'complete'             => true,
				'activated'            => true,
				'deliveryAcknowledged' => $acknowledged,
				'message'              => $acknowledged ? __( 'Catalog snapshot activated.', 'kalahamoon' ) : __( 'Catalog snapshot activated, but its delivery receipt could not be confirmed.', 'kalahamoon' ),
			);
		} finally {
			$this->release_lock( (string) $lock );
		}
	}

	/**
	 * Projection validation protects WordPress from malformed or unsafe transport
	 * data. It deliberately does not infer source status, approval, freshness,
	 * or offer eligibility: those decisions belong to Kalahamoon.
	 *
	 * @return array{snapshot:array<string,mixed>,items:array<int,array<string,mixed>>}|WP_Error
	 */
	public static function validate_snapshot( $payload, array $active_snapshot = array() ) {
		if ( ! is_array( $payload ) || 'v1' !== (string) ( $payload['version'] ?? '' ) ) {
			return new WP_Error( 'kalahamoon_catalog_snapshot_invalid', __( 'The catalog response has an unsupported version.', 'kalahamoon' ) );
		}
		if ( isset( $payload['hasMore'] ) && false !== $payload['hasMore'] ) {
			return new WP_Error( 'kalahamoon_catalog_snapshot_partial', __( 'The catalog response was partial. The previous public catalog remains active.', 'kalahamoon' ) );
		}

		$raw_snapshot = $payload['snapshot'] ?? null;
		if ( ! is_array( $raw_snapshot ) || ! is_array( $payload['items'] ?? null ) ) {
			return new WP_Error( 'kalahamoon_catalog_snapshot_invalid', __( 'The catalog response is incomplete. The previous public catalog remains active.', 'kalahamoon' ) );
		}
		$id       = self::safe_identifier( $raw_snapshot['id'] ?? '' );
		$revision = self::safe_identifier( $raw_snapshot['revision'] ?? '' );
		if ( '' === $id || '' === $revision ) {
			return new WP_Error( 'kalahamoon_catalog_snapshot_invalid', __( 'The catalog snapshot is missing its identity.', 'kalahamoon' ) );
		}

		$normalized = array();
		$seen       = array();
		foreach ( $payload['items'] as $item ) {
			$projection = self::normalize_projection_item( $item );
			if ( is_wp_error( $projection ) ) {
				return $projection;
			}
			if ( isset( $seen[ $projection['id'] ] ) ) {
				return new WP_Error( 'kalahamoon_catalog_snapshot_duplicate', __( 'The catalog snapshot contains duplicate products.', 'kalahamoon' ) );
			}
			$seen[ $projection['id'] ] = true;
			$normalized[]               = $projection;
		}

		if ( ! array_key_exists( 'withdrawnItemIds', $payload ) ) {
			return new WP_Error( 'kalahamoon_catalog_withdrawal_invalid', __( 'The catalog withdrawal directive is missing.', 'kalahamoon' ) );
		}
		$withdrawn_item_ids = self::normalize_withdrawn_item_ids( $payload['withdrawnItemIds'] );
		if ( is_wp_error( $withdrawn_item_ids ) ) {
			return $withdrawn_item_ids;
		}

		$active_item_ids = self::active_snapshot_item_ids( $active_snapshot );
		if ( is_wp_error( $active_item_ids ) ) {
			return $active_item_ids;
		}
		$current_item_ids = array_keys( $seen );
		sort( $current_item_ids, SORT_STRING );
		$expected_withdrawals = array_values( array_diff( $active_item_ids, $current_item_ids ) );
		sort( $expected_withdrawals, SORT_STRING );
		$declared_withdrawals = $withdrawn_item_ids;
		sort( $declared_withdrawals, SORT_STRING );

		if ( $expected_withdrawals !== $declared_withdrawals ) {
			// The active pointer is the consumer's own evidence of what visitors can
			// currently see. Never let an incomplete or unrelated response hide a
			// subset of it merely because the transport returned valid JSON.
			return new WP_Error( 'kalahamoon_catalog_snapshot_withdrawal_mismatch', __( 'The catalog withdrawal directive does not account for the active public catalog.', 'kalahamoon' ) );
		}

		if ( ! empty( $active_snapshot['key'] ) && empty( $normalized ) && empty( $withdrawn_item_ids ) ) {
			return new WP_Error( 'kalahamoon_catalog_snapshot_empty', __( 'An empty catalog snapshot cannot replace the active public catalog without an authoritative withdrawal directive.', 'kalahamoon' ) );
		}

		return array(
			'snapshot' => array(
				'id'          => $id,
				'revision'    => $revision,
				'generatedAt' => self::safe_timestamp( $raw_snapshot['generatedAt'] ?? '' ),
				'withdrawnItemIds' => $withdrawn_item_ids,
			),
			'items' => $normalized,
		);
	}

	/**
	 * Normalize the generic v1 projection into the historic cache shape used by
	 * existing blocks. This preserves public content compatibility without
	 * allowing old WordPress publication rules to re-evaluate the product.
	 */
	public static function normalize_projection_item( $item ) {
		if ( ! is_array( $item ) ) {
			return new WP_Error( 'kalahamoon_catalog_item_invalid', __( 'The catalog contains an invalid product.', 'kalahamoon' ) );
		}

		$id          = self::safe_identifier( $item['id'] ?? '' );
		$title       = self::safe_text( $item['title'] ?? '' );
		$image_url   = self::safe_public_https_url( $item['imageUrl'] ?? '' );
		$destination = self::safe_public_https_url( $item['destinationUrl'] ?? '' );
		$state       = strtoupper( self::safe_identifier( $item['state'] ?? '' ) );
		$visibility  = strtoupper( self::safe_identifier( $item['priceVisibility'] ?? '' ) );

		if ( '' === $id || '' === $title || '' === $image_url || '' === $destination || 'PUBLISHED' !== $state ) {
			return new WP_Error( 'kalahamoon_catalog_item_invalid', __( 'The catalog contains a product that cannot be safely rendered.', 'kalahamoon' ) );
		}
		if ( ! in_array( $visibility, array( 'VISIBLE', 'HIDDEN_STALE' ), true ) ) {
			return new WP_Error( 'kalahamoon_catalog_item_invalid', __( 'The catalog contains an unknown price visibility state.', 'kalahamoon' ) );
		}

		$source_price = self::normalize_price( $item['price'] ?? null );
		$observed_at  = is_array( $source_price ) ? (string) ( $source_price['observedAt'] ?? '' ) : '';
		if ( 'VISIBLE' === $visibility && null === $source_price ) {
			return new WP_Error( 'kalahamoon_catalog_item_invalid', __( 'A visible catalog price is invalid.', 'kalahamoon' ) );
		}
		$price = $source_price;
		if ( 'HIDDEN_STALE' === $visibility ) {
			// A stale source value is retained nowhere in presentation fields, so it
			// cannot leak through an older block template or schema renderer.
			$price = null;
		}

		$primary_offer = self::normalize_primary_offer( $item['primaryOffer'] ?? null );
		if ( is_wp_error( $primary_offer ) ) {
			return $primary_offer;
		}

		// Price-hidden products keep their valid destination card, but no offer
		// price can survive into comparison renderers through a legacy template.
		$listings = 'VISIBLE' === $visibility ? self::normalize_offers( $item['offers'] ?? array() ) : array();
		if ( 'VISIBLE' === $visibility ) {
			$has_primary = false;
			foreach ( $listings as $listing ) {
				if (
					( '' !== (string) ( $listing['id'] ?? '' ) && (string) ( $listing['id'] ?? '' ) === $primary_offer['id'] )
					|| (string) ( $listing['listingUrl'] ?? '' ) === $primary_offer['listingUrl']
				) {
					$has_primary = true;
					break;
				}
			}
			if ( ! $has_primary ) {
				// This is the explicit upstream selection, not a local first-offer
				// fallback. Additional offers retain Kalahamoon's supplied order.
				array_unshift( $listings, array(
					'id'         => $primary_offer['id'],
					'platform'   => $primary_offer['platform'],
					'sellerName' => $primary_offer['sellerName'] ?? '',
					'listingUrl' => $primary_offer['listingUrl'],
					'price'      => $price['amount'],
					'currency'   => $price['currency'],
					'inventory'  => 1,
				) );
			}
		}

		return array(
			'id'                  => $id,
			'title'               => $title,
			'description'         => self::safe_html( $item['description'] ?? '' ),
			'imageUrl'            => $image_url,
			'listingUrl'          => $destination,
			'price'               => null === $price ? null : $price['amount'],
			'currency'            => null === $price ? self::safe_identifier( $item['currency'] ?? 'IRR' ) : $price['currency'],
			'priceVisible'        => 'VISIBLE' === $visibility,
			'priceFreshness'      => strtolower( $visibility ),
			'lastCheckedAt'       => self::safe_timestamp( $item['lastCheckedAt'] ?? $observed_at ),
			'catalogState'        => 'PUBLISHED',
			'catalogProjection'   => true,
			'publicReady'         => true,
			'publicHandle'        => self::safe_identifier( $item['publicHandle'] ?? '' ),
			'sortOrder'           => is_numeric( $item['sortOrder'] ?? null ) ? (int) $item['sortOrder'] : 0,
			'primaryOffer'        => $primary_offer,
			// Store only the already-sanitized subset which renderers may consume.
			// This prevents a historic block from bypassing projection validation.
			'offers'              => $listings,
			'listings'            => $listings,
			'collections'         => self::normalize_collections( $item['collections'] ?? array() ),
			'updatedAt'           => self::safe_timestamp( $item['updatedAt'] ?? '' ),
			'priceUpdatedAt'      => $observed_at,
			'source'              => 'catalog_projection',
		);
	}

	private function acknowledge_delivery( array $snapshot, string $activated_at = '' ): bool {
		$rendered_urls = $this->verified_public_render_urls( $snapshot );
		if ( is_wp_error( $rendered_urls ) ) {
			$this->record_delivery_status( $snapshot, false, $rendered_urls->get_error_message() );
			$this->report_delivery_failure( $snapshot, 'PUBLIC_RENDER_VERIFICATION_FAILED' );
			return false;
		}

		$receipt = array(
			'outcome'      => 'ACTIVE',
			'snapshotId'   => $snapshot['id'],
			'revision'     => $snapshot['revision'],
			'receivedAt'   => gmdate( 'c' ),
			'activeAt'     => '' !== $activated_at ? $activated_at : (string) ( self::active_snapshot()['activatedAt'] ?? gmdate( 'c' ) ),
			'renderedUrls' => $rendered_urls,
		);
		$result  = $this->client->acknowledge_catalog_delivery( $receipt );
		$ok      = ! is_wp_error( $result );

		$this->record_delivery_status( $snapshot, $ok, $ok || ! is_wp_error( $result ) ? '' : $result->get_error_message() );

		return $ok;
	}

	/**
	 * The upstream service receives a small fixed failure code, never a local
	 * PHP, HTTP, or rendering message. That makes delivery health actionable
	 * without exposing the consumer's internals or granting product authority.
	 */
	private function report_delivery_failure( array $snapshot, string $failure_code ): bool {
		if ( ! in_array( $failure_code, self::DELIVERY_FAILURE_CODES, true ) ) {
			return false;
		}
		$id       = self::safe_identifier( $snapshot['id'] ?? '' );
		$revision = self::safe_identifier( $snapshot['revision'] ?? '' );
		if ( '' === $id || 1 !== preg_match( '/^[a-f0-9]{64}$/i', $revision ) ) {
			return false;
		}

		$result = $this->client->report_catalog_delivery_failure( array(
			'outcome'     => 'FAILED',
			'snapshotId'  => $id,
			'revision'    => strtolower( $revision ),
			'failedAt'    => gmdate( 'c' ),
			'failureCode' => $failure_code,
		) );

		return ! is_wp_error( $result );
	}

	/**
	 * A receipt is proof that an anonymous visitor can see the active revision,
	 * not merely that a database pointer was updated. Each publication supplies
	 * its stable catalog routes through a local filter so the connector remains
	 * reusable and does not assume a theme, hostname, or product URL shape.
	 *
	 * @return array<int,string>|WP_Error
	 */
	private function verified_public_render_urls( array $snapshot ) {
		$urls = apply_filters( 'kalahamoon_catalog_public_render_urls', array(), $snapshot );
		if ( ! is_array( $urls ) ) {
			return new WP_Error( 'kalahamoon_catalog_render_evidence_invalid', __( 'The public catalog route configuration is invalid.', 'kalahamoon' ) );
		}

		$urls = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $url ): string {
							$url = self::safe_public_https_url( $url );
							// Delivery evidence must describe this WordPress site's public
							// render, never an arbitrary HTTPS endpoint supplied by a filter.
							return self::is_exact_self_origin_public_url( $url ) ? $url : '';
						},
						$urls
					)
				)
			)
		);
		if ( empty( $urls ) ) {
			return new WP_Error( 'kalahamoon_catalog_render_evidence_missing', __( 'No public HTTPS catalog route is configured for delivery verification.', 'kalahamoon' ) );
		}

		$verified = array();
		$revision = self::safe_identifier( $snapshot['revision'] ?? '' );
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/i', $revision ) ) {
			return new WP_Error( 'kalahamoon_catalog_render_evidence_invalid', __( 'The catalog revision cannot be verified for public rendering.', 'kalahamoon' ) );
		}
		$revision_marker = '<meta name="kalahamoon-catalog-revision" content="' . strtolower( $revision ) . '">';
		foreach ( $urls as $url ) {
			// Do not reuse an authenticated connector request here: a receipt must
			// represent what an ordinary visitor can render without credentials.
			$response = wp_remote_get( $url, array(
				'headers'            => array(
					'Accept'        => 'text/html',
					'Cache-Control' => 'no-cache',
				),
				'cookies'            => array(),
				'timeout'            => 20,
				// A receipt cannot be earned through a login, canonical-host, or
				// external redirect. The configured route must render directly.
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
				'sslverify'          => true,
				'limit_response_size' => 1024 * 1024,
			) );
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'kalahamoon_catalog_render_evidence_failed', sprintf( __( 'The public catalog route could not be verified: %s', 'kalahamoon' ), $response->get_error_message() ) );
			}

			$code         = (int) wp_remote_retrieve_response_code( $response );
			$content_type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
			$body         = trim( (string) wp_remote_retrieve_body( $response ) );
			if ( $code >= 300 && $code < 400 ) {
				return new WP_Error( 'kalahamoon_catalog_render_evidence_redirected', __( 'The public catalog route redirected instead of rendering directly.', 'kalahamoon' ) );
			}
			if ( $code < 200 || $code >= 300 || ! str_contains( $content_type, 'text/html' ) || '' === $body ) {
				return new WP_Error( 'kalahamoon_catalog_render_evidence_failed', __( 'The public catalog route did not return an anonymous HTML page.', 'kalahamoon' ) );
			}
			if ( ! str_contains( strtolower( $body ), $revision_marker ) ) {
				return new WP_Error( 'kalahamoon_catalog_render_evidence_failed', __( 'The public catalog route has not rendered the active catalog revision.', 'kalahamoon' ) );
			}

			$verified[] = $url;
		}

		return $verified;
	}

	private function record_delivery_status( array $snapshot, bool $ok, string $error = '' ): void {
		$status = array(
			'status'   => $ok ? 'ACTIVE' : 'FAILED',
			'snapshot' => $snapshot['id'],
			'revision' => $snapshot['revision'],
			'at'       => gmdate( 'c' ),
			'error'    => $ok ? '' : sanitize_text_field( $error ),
		);
		update_option( self::LAST_DELIVERY_OPTION, $status );
		if ( $ok ) {
			update_option( self::LAST_CONFIRMED_DELIVERY_OPTION, $status );
		}
	}

	private function failure( string $message, int $synced = 0, ?array $snapshot = null, string $failure_code = '' ): array {
		if ( is_array( $snapshot ) && '' !== $failure_code ) {
			$this->record_delivery_status( $snapshot, false, $message );
			$this->report_delivery_failure( $snapshot, $failure_code );
		}
		$this->record_sync_status( 'FAILED', $synced, $message );
		return array(
			'synced'               => $synced,
			'errors'               => 1,
			'complete'             => false,
			'activated'            => false,
			'deliveryAcknowledged' => false,
			'message'              => $message,
		);
	}

	/**
	 * A malformed response may still name a genuine snapshot. Extract only the
	 * identity required for a fixed-code failure report; every other payload
	 * field remains subject to the normal complete-snapshot validation.
	 */
	private static function known_snapshot_from_payload( $payload ): ?array {
		if ( ! is_array( $payload ) || ! is_array( $payload['snapshot'] ?? null ) ) {
			return null;
		}
		$id       = self::safe_identifier( $payload['snapshot']['id'] ?? '' );
		$revision = self::safe_identifier( $payload['snapshot']['revision'] ?? '' );
		if ( '' === $id || 1 !== preg_match( '/^[a-f0-9]{64}$/i', $revision ) ) {
			return null;
		}

		return array(
			'id'       => $id,
			'revision' => strtolower( $revision ),
		);
	}

	private function record_sync_status( string $status, int $count = 0, string $error = '', string $at = '' ): void {
		$at = '' === $at ? gmdate( 'c' ) : $at;
		update_option( self::LAST_SYNC_OPTION, array(
			'status'         => $status,
			'count'          => $count,
			'at'             => $at,
			'nextExpectedAt' => self::next_expected_refresh_at(),
			'error'          => '' === $error ? '' : sanitize_text_field( $error ),
		) );
	}

	/**
	 * The host owns scheduling, so this is operational metadata rather than a
	 * WP-Cron instruction. Hosts may set the constant, option, or filter to
	 * reflect their real server cadence without granting catalog authority here.
	 */
	private static function next_expected_refresh_at(): string {
		$default = defined( 'KALAHAMOON_CATALOG_REFRESH_INTERVAL_MINUTES' )
			? (int) KALAHAMOON_CATALOG_REFRESH_INTERVAL_MINUTES
			: (int) get_option( 'kalahamoon_catalog_refresh_interval_minutes', 15 );
		$minutes = (int) apply_filters( 'kalahamoon_catalog_refresh_interval_minutes', $default );
		$minutes = max( 1, min( 24 * 60, $minutes ) );

		return gmdate( 'c', time() + $minutes * 60 );
	}

	private function acquire_lock() {
		$token = wp_generate_uuid4();
		$lock  = array( 'token' => $token, 'startedAt' => gmdate( 'c' ), 'expiresAt' => gmdate( 'c', time() + self::LOCK_TTL ) );

		// add_option is an atomic insert on the option name. Reading then updating
		// would let two scheduled workers activate competing snapshots.
		if ( add_option( self::LOCK_OPTION, $lock, '', 'no' ) ) {
			return $token;
		}

		$existing = get_option( self::LOCK_OPTION, array() );
		$started  = is_array( $existing ) ? strtotime( (string) ( $existing['startedAt'] ?? '' ) ) : false;
		if ( is_array( $existing ) && ! empty( $existing['token'] ) && false !== $started && $started > time() - self::LOCK_TTL ) {
			return new WP_Error( 'kalahamoon_catalog_sync_locked', __( 'A catalog synchronization is already running.', 'kalahamoon' ) );
		}

		// A crashed worker may leave an expired row. Deleting it before another
		// atomic insert preserves recovery without treating stale state as success.
		delete_option( self::LOCK_OPTION );
		if ( add_option( self::LOCK_OPTION, $lock, '', 'no' ) ) {
			return $token;
		}

		return new WP_Error( 'kalahamoon_catalog_sync_locked', __( 'A catalog synchronization is already running.', 'kalahamoon' ) );
	}

	private function release_lock( string $token ): void {
		$current = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $current ) && hash_equals( (string) ( $current['token'] ?? '' ), $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}

	private static function normalize_offers( $offers ): array {
		if ( ! is_array( $offers ) ) {
			return array();
		}
		$normalized = array();
		foreach ( $offers as $offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}
			$visibility = strtoupper( self::safe_identifier( $offer['priceVisibility'] ?? 'VISIBLE' ) );
			$price      = self::normalize_offer_price( $offer );
			$url        = self::safe_public_https_url( $offer['listingUrl'] ?? $offer['destinationUrl'] ?? $offer['url'] ?? '' );
			if ( 'VISIBLE' !== $visibility || null === $price || '' === $url ) {
				continue;
			}
			$identity = self::normalize_offer_identity( $offer );
			$normalized[] = array(
				'id'         => $identity['id'] ?? '',
				'platform'   => $identity['platform'] ?? '',
				'sellerName' => $identity['sellerName'] ?? '',
				'listingUrl' => $url,
				'price'      => $price['amount'],
				'currency'   => $price['currency'],
				'inventory'  => 1,
			);
		}
		return $normalized;
	}

	/**
	 * Withdrawals are a destructive visibility change, so the consumer accepts
	 * only a structurally valid list of safe, unique catalog IDs. An empty list
	 * is valid for an ordinary non-destructive replacement and is compared with
	 * the active pointer before activation.
	 * WordPress does not decide whether those IDs should be withdrawn; it merely
	 * verifies that the upstream directive is structurally unambiguous.
	 *
	 * @return array<int,string>|WP_Error
	 */
	private static function normalize_withdrawn_item_ids( $value ) {
		if ( ! is_array( $value ) ) {
			return new WP_Error( 'kalahamoon_catalog_withdrawal_invalid', __( 'The catalog withdrawal directive is invalid.', 'kalahamoon' ) );
		}

		$ids  = array();
		$seen = array();
		foreach ( $value as $raw_id ) {
			$id = self::safe_identifier( $raw_id );
			if ( '' === $id ) {
				return new WP_Error( 'kalahamoon_catalog_withdrawal_invalid', __( 'The catalog withdrawal directive contains an invalid product identity.', 'kalahamoon' ) );
			}
			if ( isset( $seen[ $id ] ) ) {
				return new WP_Error( 'kalahamoon_catalog_withdrawal_duplicate', __( 'The catalog withdrawal directive contains duplicate product identities.', 'kalahamoon' ) );
			}
			$seen[ $id ] = true;
			$ids[]       = $id;
		}

		return $ids;
	}

	/**
	 * The active pointer records exactly which remote IDs its revision made
	 * public. It is required for every replacement so loss of a response cannot
	 * be mistaken for an intentional removal.
	 *
	 * @return array<int,string>|WP_Error
	 */
	private static function active_snapshot_item_ids( array $active_snapshot ) {
		if ( empty( $active_snapshot['key'] ) ) {
			return array();
		}
		if ( ! array_key_exists( 'itemIds', $active_snapshot ) || ! is_array( $active_snapshot['itemIds'] ) ) {
			return new WP_Error( 'kalahamoon_catalog_snapshot_baseline_missing', __( 'The active catalog baseline cannot be verified. The previous public catalog remains active.', 'kalahamoon' ) );
		}

		$ids  = array();
		$seen = array();
		foreach ( $active_snapshot['itemIds'] as $raw_id ) {
			$id = self::safe_identifier( $raw_id );
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				return new WP_Error( 'kalahamoon_catalog_snapshot_baseline_invalid', __( 'The active catalog baseline is invalid. The previous public catalog remains active.', 'kalahamoon' ) );
			}
			$seen[ $id ] = true;
			$ids[]       = $id;
		}
		if ( isset( $active_snapshot['count'] ) && (int) $active_snapshot['count'] !== count( $ids ) ) {
			return new WP_Error( 'kalahamoon_catalog_snapshot_baseline_invalid', __( 'The active catalog baseline is invalid. The previous public catalog remains active.', 'kalahamoon' ) );
		}
		sort( $ids, SORT_STRING );
		return $ids;
	}

	/**
	 * v1 comparison offers intentionally use a compact scalar price shape while
	 * the selected product price carries its own object. Supporting both shapes
	 * preserves Kalahamoon's approved offer order without asking WordPress to
	 * derive or refresh a price itself.
	 */
	private static function normalize_offer_price( array $offer ): ?array {
		$raw_price = $offer['price'] ?? null;
		if ( is_array( $raw_price ) ) {
			return self::normalize_price( $raw_price );
		}
		if ( ! is_numeric( $raw_price ) || (float) $raw_price <= 0 ) {
			return null;
		}

		$currency = strtoupper( self::safe_identifier( $offer['currency'] ?? 'IRR' ) );
		if ( '' === $currency ) {
			return null;
		}
		return array(
			'amount'     => (float) $raw_price,
			'currency'   => $currency,
			'observedAt' => self::safe_timestamp( $offer['observedAt'] ?? '' ),
		);
	}

	private static function normalize_offer_identity( $offer ): array {
		if ( ! is_array( $offer ) ) {
			return array();
		}
		return array_filter( array(
			'id'         => self::safe_identifier( $offer['id'] ?? $offer['offerId'] ?? '' ),
			'platform'   => self::safe_identifier( $offer['platform'] ?? $offer['provider'] ?? '' ),
			'sellerName' => self::safe_text( $offer['sellerName'] ?? $offer['seller'] ?? '' ),
	) );
	}

	/**
	 * A published projection is not allowed to rely on the first comparison
	 * offer. Kalahamoon freezes one deliberate primary offer with the revision.
	 *
	 * @return array<string,string>|WP_Error
	 */
	private static function normalize_primary_offer( $offer ) {
		if ( ! is_array( $offer ) ) {
			return new WP_Error( 'kalahamoon_catalog_primary_offer_missing', __( 'The catalog item is missing its selected offer.', 'kalahamoon' ) );
		}

		$identity   = self::normalize_offer_identity( $offer );
		$listing_url = self::safe_public_https_url( $offer['listingUrl'] ?? '' );
		if ( '' === (string) ( $identity['id'] ?? '' ) || '' === (string) ( $identity['platform'] ?? '' ) || '' === $listing_url ) {
			return new WP_Error( 'kalahamoon_catalog_primary_offer_invalid', __( 'The catalog item has an invalid selected offer.', 'kalahamoon' ) );
		}

		$identity['listingUrl'] = $listing_url;
		return $identity;
	}

	private static function normalize_price( $value ): ?array {
		if ( ! is_array( $value ) || ! is_numeric( $value['amount'] ?? null ) || (float) $value['amount'] <= 0 ) {
			return null;
		}
		$currency = strtoupper( self::safe_identifier( $value['currency'] ?? 'IRR' ) );
		if ( '' === $currency ) {
			return null;
		}
		return array(
			'amount'     => (float) $value['amount'],
			'currency'   => $currency,
			'observedAt' => self::safe_timestamp( $value['observedAt'] ?? '' ),
		);
	}

	private static function normalize_collections( $collections ): array {
		if ( ! is_array( $collections ) ) {
			return array();
		}
		$normalized = array();
		foreach ( array_slice( $collections, 0, 50 ) as $collection ) {
			$name = is_array( $collection ) ? self::safe_text( $collection['name'] ?? $collection['label'] ?? '' ) : self::safe_text( $collection );
			$slug = is_array( $collection ) ? sanitize_title( (string) ( $collection['slug'] ?? $name ) ) : sanitize_title( $name );
			if ( '' !== $name && '' !== $slug ) {
				$normalized[ $slug ] = array( 'name' => $name, 'slug' => $slug );
			}
		}
		return array_values( $normalized );
	}

	private static function safe_identifier( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		return substr( sanitize_text_field( (string) $value ), 0, 191 );
	}

	private static function safe_text( $value ): string {
		return is_scalar( $value ) ? substr( sanitize_text_field( (string) $value ), 0, 1000 ) : '';
	}

	private static function safe_html( $value ): string {
		return is_scalar( $value ) ? wp_kses_post( (string) $value ) : '';
	}

	private static function safe_timestamp( $value ): string {
		if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
			return '';
		}
		$timestamp = strtotime( (string) $value );
		return false === $timestamp ? '' : gmdate( 'c', $timestamp );
	}

	private static function is_safe_timestamp( $value ): bool {
		return '' !== self::safe_timestamp( $value );
	}

	private static function is_snapshot_count( $value ): bool {
		if ( is_int( $value ) ) {
			return $value >= 0;
		}
		if ( ! is_string( $value ) || ! preg_match( '/^(0|[1-9][0-9]*)$/', $value ) ) {
			return false;
		}
		return (int) $value >= 0;
	}

	private static function safe_public_https_url( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$url = esc_url_raw( (string) $value );
		if ( '' === $url || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return '';
		}
		$parts  = wp_parse_url( $url );
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( trim( (string) ( $parts['host'] ?? '' ), '[]' ) );
		if ( 'https' !== $scheme || '' === $host || isset( $parts['user'] ) || isset( $parts['pass'] ) || 'localhost' === $host || str_ends_with( $host, '.localhost' ) || str_ends_with( $host, '.local' ) ) {
			return '';
		}
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) && false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Keep delivery probes on the configured public WordPress origin. A route may
	 * vary by path, but not by scheme, host, or effective HTTPS port.
	 */
	private static function is_exact_self_origin_public_url( string $url ): bool {
		$home_url = self::safe_public_https_url( home_url( '/' ) );
		if ( '' === $url || '' === $home_url ) {
			return false;
		}

		$requested = wp_parse_url( $url );
		$origin    = wp_parse_url( $home_url );
		if ( ! is_array( $requested ) || ! is_array( $origin ) ) {
			return false;
		}
		// Fragments are never part of an HTTP request. Reject them instead of
		// allowing a receipt to attest a subtly different URL than the one fetched.
		if ( isset( $requested['fragment'] ) ) {
			return false;
		}

		return strtolower( (string) ( $requested['scheme'] ?? '' ) ) === strtolower( (string) ( $origin['scheme'] ?? '' ) )
			&& strtolower( trim( (string) ( $requested['host'] ?? '' ), '[]' ) ) === strtolower( trim( (string) ( $origin['host'] ?? '' ), '[]' ) )
			&& self::effective_https_port( $requested ) === self::effective_https_port( $origin );
	}

	/** @param array<string,mixed> $parts */
	private static function effective_https_port( array $parts ): int {
		$port = $parts['port'] ?? 443;
		return is_int( $port ) || ( is_string( $port ) && ctype_digit( $port ) ) ? (int) $port : -1;
	}

	private static function is_origin_proof_request(): bool {
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( ! is_string( $request_uri ) || '' === $request_uri ) {
			return false;
		}
		$parts = wp_parse_url( wp_unslash( $request_uri ) );
		if (
			! is_array( $parts )
			|| isset( $parts['query'] )
			|| isset( $parts['fragment'] )
			|| isset( $parts['scheme'] )
			|| isset( $parts['host'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
			|| isset( $parts['port'] )
		) {
			return false;
		}

		$path = $parts['path'] ?? '';
		return is_string( $path ) && hash_equals( self::ORIGIN_PROOF_PATH, $path );
	}

	private static function configured_origin_proof_challenge(): string {
		$value = defined( self::ORIGIN_PROOF_ENV )
			? constant( self::ORIGIN_PROOF_ENV )
			: getenv( self::ORIGIN_PROOF_ENV );
		if ( ! is_string( $value ) || 1 !== preg_match( '/^catalog_origin_[A-Za-z0-9_-]{32,256}$/', $value ) ) {
			return '';
		}

		return $value;
	}

	private static function safe_status_option( string $option ): array {
		$value = get_option( $option, array() );
		return is_array( $value ) ? $value : array();
	}
}
