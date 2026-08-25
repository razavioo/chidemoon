<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_API_Products {

	private Kalahamoon_API_Client $client;

	public function __construct() {
		$this->client = new Kalahamoon_API_Client();
	}

	/**
	 * Fetch products from the Kalahamoon API.
	 *
	 * @param array $args { page, limit, category, marketplace, search, updatedSince, publicationState }
	 * @return array|WP_Error
	 */
	public function get_products( array $args = array() ) {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return Kalahamoon_Product_Cache::get_all( array_merge( $args, array( 'public_ready' => true ) ) );
		}

		$defaults = array(
			'page'         => 1,
			'limit'        => 20,
			'category'     => '',
			'marketplace'  => '',
			'search'       => '',
			'updatedSince' => '',
			'publicationState' => '',
		);

		$args   = wp_parse_args( $args, $defaults );
		$args   = apply_filters( 'kalahamoon_api_products_args', $args, 'get_products' );
		$params = array_filter( $args, fn( $v ) => '' !== $v && null !== $v );

		$cache_ttl = (int) get_option( 'kalahamoon_sync_interval', 6 ) * HOUR_IN_SECONDS;

		return $this->client->get( $this->catalog_endpoint(), $params, $cache_ttl );
	}

	/**
	 * Get a single product by Kalahamoon ID.
	 */
	public function get_product( string $product_id ): array|WP_Error {
		// Fetch from cache first
		$cached = Kalahamoon_Product_Cache::get_by_kalahamoon_id( $product_id );
		if ( $cached ) {
			return $cached;
		}
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return new WP_Error( 'kalahamoon_product_not_found', __( 'Product not found', 'kalahamoon' ) );
		}

		// If not cached, fetch from API (single product via paginated search)
		$result = $this->client->get( $this->catalog_endpoint(), array( 'limit' => '100' ), HOUR_IN_SECONDS );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$items = $result['items'] ?? array();
		foreach ( $items as $item ) {
			if ( $item['id'] === $product_id ) {
				return $item;
			}
		}

		return new WP_Error( 'kalahamoon_product_not_found', __( 'Product not found', 'kalahamoon' ) );
	}

	public function update_publication_state( string $listing_id, string $publication_state ) {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return new WP_Error( 'kalahamoon_catalog_read_only', __( 'Catalog publication is managed in Kalahamoon.', 'kalahamoon' ), array( 'status' => 403 ) );
		}

		$state = strtoupper( sanitize_key( $publication_state ) );
		if ( ! in_array( $state, array( 'DRAFT', 'VERIFIED', 'ARCHIVED' ), true ) ) {
			return new WP_Error( 'invalid_publication_state', __( 'Invalid publication state.', 'kalahamoon' ) );
		}

		return $this->client->patch(
			$this->publication_endpoint( $listing_id ),
			array( 'publicationState' => $state )
		);
	}

	/**
	 * Sync all products from Kalahamoon to local cache.
	 */
	public function sync_all(): array {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return ( new Kalahamoon_Catalog_Consumer( $this->client ) )->sync();
		}

		if ( ! $this->client->is_connected() ) {
			return array(
				'synced'  => 0,
				'errors'  => 1,
				'message' => __( 'Not connected to Kalahamoon. Connect in the plugin settings.', 'kalahamoon' ),
			);
		}

		$page    = 1;
		$synced  = 0;
		$errors  = 0;
		$expected_total = null;
		$expected_pages = null;
		$synced_product_ids = array();
		$sync_args = array();

		do {
			$params = apply_filters( 'kalahamoon_api_products_args', array(
				'page'            => (string) $page,
				'limit'           => '100',
				'includeUnlisted' => 'true',
			), 'sync_all' );
			$sync_args = $params;
			$result = $this->client->get( $this->catalog_endpoint(), $params );

			if ( is_wp_error( $result ) ) {
				return array( 'synced' => $synced, 'errors' => ++$errors, 'message' => $result->get_error_message() );
			}

			$items      = $result['items'] ?? null;
			$pagination = $result['pagination'] ?? null;

			if (
				! is_array( $items ) ||
				! is_array( $pagination ) ||
				! isset( $pagination['total'], $pagination['totalPages'] ) ||
				! is_numeric( $pagination['total'] ) ||
				! is_numeric( $pagination['totalPages'] )
			) {
				return array(
					'synced'   => $synced,
					'errors'   => ++$errors,
					'complete' => false,
					'message'  => __( 'The catalog response was incomplete and no cached products were removed.', 'kalahamoon' ),
				);
			}

			$response_total = (int) $pagination['total'];
			$response_pages = (int) $pagination['totalPages'];
			$page_limit     = max( 1, (int) ( $params['limit'] ?? 100 ) );
			$calculated_pages = (int) ceil( $response_total / $page_limit );

			if (
				$response_total < 0 ||
				$response_pages < 0 ||
				$response_pages > 10000 ||
				$response_pages !== $calculated_pages ||
				( null !== $expected_total && $response_total !== $expected_total ) ||
				( null !== $expected_pages && $response_pages !== $expected_pages )
			) {
				return array(
					'synced'   => $synced,
					'errors'   => ++$errors,
					'complete' => false,
					'message'  => __( 'The catalog changed during synchronization and no cached products were removed.', 'kalahamoon' ),
				);
			}

			$expected_total = $response_total;
			$expected_pages = $response_pages;

			foreach ( $items as $item ) {
				$saved = Kalahamoon_Product_Cache::upsert( $item );
				if ( $saved ) {
					$synced++;
					if ( ! empty( $item['id'] ) ) {
						$synced_product_ids[] = (string) $item['id'];
					}
				} else {
					$errors++;
				}
			}

			$page++;
			$total_pages = $expected_pages;
		} while ( $page <= $total_pages );

		$unique_product_ids = array_values( array_unique( $synced_product_ids ) );
		$sync_complete = 0 === $errors && null !== $expected_total && count( $unique_product_ids ) === $expected_total;
		$allow_empty_prune = 0 !== $expected_total || (bool) apply_filters( 'kalahamoon_allow_empty_catalog_prune', false );
		$pruned = false;
		$message = '';

		if ( ! $sync_complete ) {
			$errors++;
			$message = __( 'The catalog snapshot was incomplete and no cached products were removed.', 'kalahamoon' );
		}

		if ( $sync_complete ) {
			// Only an internally consistent, error-free snapshot is authoritative
			// enough to remove cached records that were absent upstream.
			if ( $allow_empty_prune ) {
				Kalahamoon_Product_Cache::delete_missing_ids( $unique_product_ids );
				$pruned = true;
			}

			update_option( 'kalahamoon_last_sync', current_time( 'mysql' ) );
			do_action( 'kalahamoon_products_sync_complete', $unique_product_ids, $sync_args );

			$this->provision_missing_affiliate_links();
			$this->reconcile_affiliate_metrics();
		}

		return array(
			'synced'   => $synced,
			'errors'   => $errors,
			'complete' => $sync_complete,
			'pruned'   => $pruned,
			'message'  => $message,
		);
	}

	private function catalog_endpoint(): string {
		return '/api/public/products';
	}

	private function publication_endpoint( string $listing_id ): string {
		return '/api/public/products/listings/' . rawurlencode( $listing_id ) . '/publication';
	}

	/**
	 * Reconcile local affiliate-link mirror rows with the panel's authoritative
	 * metrics (clicks, conversions, revenue). Idempotent — keyed on the panel
	 * AffiliateLink ID stored in `kalahamoon_link_id`.
	 *
	 * @return int Number of mirror rows updated.
	 */
	public function reconcile_affiliate_metrics(): int {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			// Projection links are already selected upstream. A local mirror must not
			// reopen the old affiliate-link workflow after connector activation.
			return 0;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_affiliate_links';

		$ids = $wpdb->get_col(
			"SELECT kalahamoon_link_id FROM {$table} WHERE kalahamoon_link_id IS NOT NULL AND kalahamoon_link_id <> ''"
		);
		if ( empty( $ids ) ) {
			return 0;
		}

		$updated = 0;
		foreach ( array_chunk( $ids, 100 ) as $chunk ) {
			$result = $this->client->get_affiliate_metrics( $chunk );
			if ( is_wp_error( $result ) ) {
				continue;
			}

			foreach ( ( $result['metrics'] ?? array() ) as $metric ) {
				$link_id = $metric['id'] ?? '';
				if ( '' === $link_id ) {
					continue;
				}

				$data = array(
					'clicks'      => (int) ( $metric['clicks'] ?? 0 ),
					'conversions' => (int) ( $metric['conversions'] ?? 0 ),
					'revenue'     => (float) ( $metric['revenue'] ?? 0 ),
				);
				if ( ! empty( $metric['status'] ) ) {
					$data['status'] = strtolower( (string) $metric['status'] ) === 'archived' ? 'archived' : 'active';
				}

				$updated += (int) $wpdb->update(
					$table,
					$data,
					array( 'kalahamoon_link_id' => $link_id )
				);
			}
		}

		return $updated;
	}

	/**
	 * Batch-create panel affiliate links for products without a cached link.
	 *
	 * One bounded cache page is processed per catalog sync. This allows large
	 * catalogs to converge without turning an ordinary WordPress request into an
	 * unbounded batch job; the page cursor advances only after the panel replied.
	 * Creation remains idempotent on the panel, so reordered cache pages are safe.
	 */
	public function provision_missing_affiliate_links(): void {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_affiliate_links';

		$page = max( 1, absint( get_option( 'kalahamoon_affiliate_provision_page', 1 ) ) );
		$catalog = Kalahamoon_Product_Cache::get_all( array( 'limit' => 100, 'page' => $page ) );
		$total_pages = max( 1, absint( $catalog['totalPages'] ?? 1 ) );
		if ( $page > $total_pages ) {
			$page    = 1;
			$catalog = Kalahamoon_Product_Cache::get_all( array( 'limit' => 100, 'page' => $page ) );
			$total_pages = max( 1, absint( $catalog['totalPages'] ?? 1 ) );
		}

		$rows    = array();

		foreach ( $catalog['items'] as $product ) {
			$pid      = $product['id'] ?? '';
			$url      = self::product_listing_url( $product );
			$provider = self::affiliate_provider_for_product( $product, $url );
			if ( '' === $pid || ! Kalahamoon_Link_Builder::is_clickable_url( $url ) || '' === $provider ) {
				continue;
			}

			$has_link = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE product_id = %s AND status = 'active'",
				$pid
			) );
			if ( $has_link > 0 ) {
				continue;
			}

			$rows[] = array(
				'kalahamoon_id' => $pid,
				'listing_url'   => $url,
				'title'         => $product['title'] ?? '',
				'provider'      => $provider,
			);
		}

		if ( empty( $rows ) ) {
			self::advance_affiliate_provision_page( $page, $total_pages );
			return;
		}

		$items = array_map( static function ( $row ) {
			return array(
				'productUrl'    => $row['listing_url'],
				'productId'     => $row['kalahamoon_id'],
				'provider'      => $row['provider'],
				'platform'      => $row['provider'],
				'campaignTitle' => $row['title'],
			);
		}, $rows );

		$result = $this->client->batch_create_affiliate_links( $items );
		if ( is_wp_error( $result ) ) {
			return;
		}

		$results = $result['results'] ?? array();
		foreach ( $results as $i => $item_result ) {
			if ( empty( $item_result['ok'] ) || empty( $item_result['link'] ) ) {
				continue;
			}
			$product_id  = $rows[ $i ]['kalahamoon_id'] ?? null;
			$cloaked_url = $item_result['cloakedUrl'] ?? ( $item_result['shortUrl'] ?? null );
			$kalahamoon_id = $item_result['link']['id'] ?? null;
			$provider    = $item_result['link']['provider'] ?? 'bakalahamoon';

			if ( $product_id && $cloaked_url && $kalahamoon_id ) {
				Kalahamoon_Link_Builder::persist_panel_link(
					$product_id,
					$cloaked_url,
					$kalahamoon_id,
					strtolower( $provider ),
					array(
						'slug'            => $item_result['slug'] ?? ( $item_result['link']['slug'] ?? '' ),
						'destination_url' => $rows[ $i ]['listing_url'] ?? '',
						'campaign_title'  => $rows[ $i ]['title'] ?? '',
					)
				);
			}
		}

		self::advance_affiliate_provision_page( $page, $total_pages );
	}

	/**
	 * Map a cached marketplace to a provider the panel can currently build.
	 * Unknown destinations remain ordinary product links and are never sent to
	 * the affiliate endpoint merely to produce an avoidable error.
	 */
	private static function affiliate_provider_for_product( array $product, string $url ): string {
		$platform = strtolower( sanitize_key( (string) ( $product['platform'] ?? '' ) ) );
		if ( 'bakalahamoon' === $platform ) {
			$platform = 'basalam';
		}

		if ( in_array( $platform, array( 'basalam', 'digikala' ), true ) ) {
			return $platform;
		}

		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( preg_match( '/(^|\\.)basalam\\.com$/', $host ) ) {
			return 'basalam';
		}
		if ( preg_match( '/(^|\\.)digikala\\.com$/', $host ) ) {
			return 'digikala';
		}

		return '';
	}

	/**
	 * Prefer the canonical URL, then a listing URL kept by older catalog payloads.
	 * This preserves automated provisioning while the panel rolls out catalog-field
	 * normalization across historical products.
	 */
	private static function product_listing_url( array $product ): string {
		$url = trim( (string) ( $product['listingUrl'] ?? '' ) );
		if ( '' !== $url ) {
			return $url;
		}

		$listings = $product['listings'] ?? array();
		if ( is_array( $listings ) && isset( $listings[0] ) && is_array( $listings[0] ) ) {
			return trim( (string) ( $listings[0]['listingUrl'] ?? $listings[0]['url'] ?? '' ) );
		}

		return '';
	}

	private static function advance_affiliate_provision_page( int $page, int $total_pages ): void {
		update_option( 'kalahamoon_affiliate_provision_page', $page >= $total_pages ? 1 : $page + 1 );
	}
}
