<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Product_Cache {

	const POST_TYPE = 'kalahamoon_product';

	/**
	 * Register the custom post type for cached products.
	 */
	public static function register_post_type(): void {
		$consumer = class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled();
		register_post_type( self::POST_TYPE, array(
			'labels'       => array(
				'name'          => __( 'Kalahamoon Products', 'kalahamoon' ),
				'singular_name' => __( 'Kalahamoon Product', 'kalahamoon' ),
			),
			'public'       => false,
			'show_ui'      => false,
			// Cache rows are not an authoring model in connector installations.
			'show_in_rest' => ! $consumer,
			'supports'     => array( 'title', 'thumbnail', 'custom-fields' ),
			'has_archive'  => false,
			'rewrite'      => false,
		) );

		// Register taxonomies
		register_taxonomy( 'kalahamoon_category', self::POST_TYPE, array(
			'labels'            => array(
				'name'          => __( 'Product Categories', 'kalahamoon' ),
				'singular_name' => __( 'Product Category', 'kalahamoon' ),
			),
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => ! $consumer,
			'show_admin_column' => true,
			'show_in_rest'      => ! $consumer,
		) );

		register_taxonomy( 'kalahamoon_brand', self::POST_TYPE, array(
			'labels'            => array(
				'name'          => __( 'Brands', 'kalahamoon' ),
				'singular_name' => __( 'Brand', 'kalahamoon' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => ! $consumer,
			'show_admin_column' => true,
			'show_in_rest'      => ! $consumer,
		) );

		register_taxonomy( 'kalahamoon_collection', self::POST_TYPE, array(
			'labels'            => array(
				'name'          => __( 'Collections', 'kalahamoon' ),
				'singular_name' => __( 'Collection', 'kalahamoon' ),
			),
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => ! $consumer,
			'show_admin_column' => true,
			'show_in_rest'      => ! $consumer,
		) );
	}

	/**
	 * Get a cached product by its Kalahamoon ID.
	 */
	public static function get_by_kalahamoon_id( string $kalahamoon_id ): ?array {
		$args = array(
			'post_type'  => self::POST_TYPE,
			'numberposts' => 1,
			'post_status' => 'publish',
		);
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			$key = Kalahamoon_Catalog_Consumer::active_snapshot_key();
			if ( '' === $key ) {
				return null;
			}
			$args['meta_query'] = array(
				'relation' => 'AND',
				array( 'key' => '_kalahamoon_product_id', 'value' => $kalahamoon_id ),
				array( 'key' => '_kalahamoon_catalog_snapshot_key', 'value' => $key ),
			);
		} else {
			$args['meta_key']   = '_kalahamoon_product_id';
			$args['meta_value'] = $kalahamoon_id;
		}
		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return null;
		}

		return self::format_product( $posts[0] );
	}

	/**
	 * Resolve a product for a rendered block without leaking unreviewed cache rows.
	 * Editors retain an explicit preview path so they can repair broken content;
	 * every other visitor receives only policy-approved catalog data.
	 */
	public static function get_for_public_render( string $kalahamoon_id ): ?array {
		$product = self::get_by_kalahamoon_id( $kalahamoon_id );
		if ( null === $product ) {
			return null;
		}
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			// The active revision already contains Kalahamoon's publication decision.
			// Applying the legacy policy here would give this consumer a second chance
			// to alter eligibility or price visibility after activation.
			return ! empty( $product['publicReady'] ) ? $product : null;
		}
		if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
			return $product;
		}

		$product = Kalahamoon_Catalog_Policy::apply( $product );
		return ! empty( $product['publicReady'] ) ? $product : null;
	}

	/**
	 * Upsert a product from API data into the local cache.
	 */
	public static function upsert( array $item ): bool {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return false;
		}

		$kalahamoon_id = $item['id'] ?? '';
		if ( empty( $kalahamoon_id ) ) {
			return false;
		}

		$listings = is_array( $item['listings'] ?? null ) ? array_values( $item['listings'] ) : array();
		$verified_listings = array_values(
			array_filter(
				$listings,
				static fn( $candidate ) => is_array( $candidate )
					&& strtoupper( (string) ( $candidate['publicationState'] ?? '' ) ) === 'VERIFIED'
			)
		);
		$listing = $verified_listings[0] ?? $listings[0] ?? array();
		$title    = $listing['title'] ?? $item['title'] ?? '';
		$price    = $listing['price'] ?? $item['price'] ?? 0;
		$old_price = 0;
		$metadata = $listing['metadata'] ?? array();

		if ( is_array( $metadata ) ) {
			$old_price = $metadata['originalPrice'] ?? 0;
		}

		// Check if already exists
		$existing = get_posts( array(
			'post_type'   => self::POST_TYPE,
			'meta_key'    => '_kalahamoon_product_id',
			'meta_value'  => $kalahamoon_id,
			'numberposts' => 1,
			'post_status' => 'any',
		) );

		$post_data = array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => $title,
			'post_status' => 'publish',
			'post_content' => $listing['description'] ?? $item['description'] ?? '',
		);

		if ( ! empty( $existing ) ) {
			$post_data['ID'] = $existing[0]->ID;
			$post_id = wp_update_post( $post_data );
		} else {
			$post_id = wp_insert_post( $post_data );
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		// Store all metadata
		update_post_meta( $post_id, '_kalahamoon_product_id', $kalahamoon_id );
		update_post_meta( $post_id, '_kalahamoon_price', (float) $price );
		update_post_meta( $post_id, '_kalahamoon_old_price', (float) $old_price );
		update_post_meta( $post_id, '_kalahamoon_currency', $listing['currency'] ?? 'IRR' );
		update_post_meta( $post_id, '_kalahamoon_inventory', (int) ( $listing['inventory'] ?? $item['inventory'] ?? 0 ) );
		$source_image_url = self::resolve_best_image( $item, $listing );
		$image_url = self::mirror_verified_image( (int) $post_id, $source_image_url, $listing );
		$local_image_url = esc_url_raw( (string) get_post_meta( $post_id, '_kalahamoon_local_image_url', true ) );
		if ( '' !== $local_image_url ) {
			$image_url = $local_image_url;
		}
		update_post_meta( $post_id, '_kalahamoon_original_image_url', $source_image_url );
		update_post_meta( $post_id, '_kalahamoon_image_url', $image_url );
		update_post_meta( $post_id, '_kalahamoon_product_source', 'synced' );
		update_post_meta( $post_id, '_kalahamoon_platform', $listing['platform'] ?? '' );
		update_post_meta( $post_id, '_kalahamoon_listing_id', $listing['id'] ?? '' );
		update_post_meta( $post_id, '_kalahamoon_listing_url', $listing['listingUrl'] ?? '' );
		update_post_meta( $post_id, '_kalahamoon_external_id', $listing['externalProductId'] ?? '' );
		update_post_meta( $post_id, '_kalahamoon_seller_name', $listing['sellerName'] ?? '' );
		update_post_meta( $post_id, '_kalahamoon_specs', wp_json_encode( $listing['specs'] ?? array(), JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $post_id, '_kalahamoon_variant_data', wp_json_encode( $listing['variantData'] ?? array(), JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $post_id, '_kalahamoon_metadata', wp_json_encode( $metadata, JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $post_id, '_kalahamoon_status', $listing['status'] ?? $item['status'] ?? 'active' );
		update_post_meta( $post_id, '_kalahamoon_publication_state', $listing['publicationState'] ?? '' );
		update_post_meta( $post_id, '_kalahamoon_publication_reviewed_at', $listing['publicationReviewedAt'] ?? '' );
		update_post_meta( $post_id, '_kalahamoon_publication_reviewer_type', $listing['publicationReviewerType'] ?? '' );
		update_post_meta( $post_id, '_kalahamoon_publication_reviewer_id', $listing['publicationReviewerId'] ?? '' );
		update_post_meta( $post_id, '_kalahamoon_publication_review_issues', wp_json_encode( $listing['publicationReadinessIssues'] ?? $listing['publicationReviewIssues'] ?? array() ) );
		update_post_meta( $post_id, '_kalahamoon_last_synced', current_time( 'mysql' ) );
		update_post_meta( $post_id, '_kalahamoon_listings', wp_json_encode( $item['listings'] ?? array(), JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $post_id, '_kalahamoon_source_categories', wp_json_encode( $item['sourceCategories'] ?? array(), JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $post_id, '_kalahamoon_canonical_categories', wp_json_encode( $item['canonicalCategories'] ?? array(), JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $post_id, '_kalahamoon_comparison_type', wp_json_encode( $item['comparisonType'] ?? null, JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $post_id, '_kalahamoon_category_confidence', isset( $item['categoryConfidence'] ) ? (float) $item['categoryConfidence'] : '' );
		update_post_meta( $post_id, '_kalahamoon_category_review_status', $item['categoryReviewStatus'] ?? '' );
		$source_synced_at = self::normalize_source_timestamp( (string) ( $listing['lastSyncedAt'] ?? $listing['updatedAt'] ?? '' ) );
		update_post_meta( $post_id, '_kalahamoon_source_synced_at', gmdate( 'c', $source_synced_at ) );
		update_post_meta( $post_id, '_kalahamoon_source_sync_epoch', $source_synced_at );

		// Record price history
		self::record_price( $kalahamoon_id, (float) $price, $listing['currency'] ?? 'IRR' );

		// Auto-assign taxonomy terms from metadata. Prefer an explicit stable slug
		// when integrations provide one (for example, `appliances`) so public
		// shortcodes/REST filters can query by deterministic ASCII slugs instead of
		// WordPress-generated Persian percent-encoded slugs.
		$canonical_categories_for_terms = is_array( $item['canonicalCategories'] ?? null ) ? $item['canonicalCategories'] : array();
		$primary_canonical_category   = is_array( $canonical_categories_for_terms[0] ?? null ) ? $canonical_categories_for_terms[0] : null;
		$term_category_name           = $primary_canonical_category['label'] ?? ( $metadata['category'] ?? '' );
		$term_category_slug           = $primary_canonical_category['slug'] ?? ( $metadata['categorySlug'] ?? '' );
		if ( '' !== (string) $term_category_name ) {
			$category_name = sanitize_text_field( (string) $term_category_name );
			$category_slug = ! empty( $term_category_slug )
				? sanitize_title( (string) $term_category_slug )
				: sanitize_title( $category_name );

			$term = $category_slug ? get_term_by( 'slug', $category_slug, 'kalahamoon_category' ) : false;
			if ( ! $term ) {
				$inserted = wp_insert_term( $category_name, 'kalahamoon_category', array( 'slug' => $category_slug ) );
				$term_id  = ! is_wp_error( $inserted ) && ! empty( $inserted['term_id'] ) ? (int) $inserted['term_id'] : 0;
			} else {
				$term_id = (int) $term->term_id;
			}

			if ( $term_id ) {
				wp_set_object_terms( $post_id, array( $term_id ), 'kalahamoon_category', false );
			}
		}
		if ( is_array( $metadata ) && ! empty( $metadata['brand'] ) ) {
			wp_set_object_terms( $post_id, sanitize_text_field( $metadata['brand'] ), 'kalahamoon_brand', true );
		}
		self::refresh_static_readiness( (int) $post_id );

		return true;
	}

	/**
	 * Store a Kalahamoon-approved projection in a revision-scoped cache row.
	 * Rows from the active revision are never overwritten while another snapshot
	 * is staging, which is the cache half of the atomic delivery guarantee.
	 */
	public static function upsert_catalog_projection( array $item, array $snapshot ): bool {
		$id       = sanitize_text_field( (string) ( $item['id'] ?? '' ) );
		$key      = sanitize_text_field( (string) ( $snapshot['key'] ?? Kalahamoon_Catalog_Consumer::snapshot_key( (string) ( $snapshot['id'] ?? '' ), (string) ( $snapshot['revision'] ?? '' ) ) ) );
		if ( '' === $id || '' === $key || empty( $item['catalogProjection'] ) || 'PUBLISHED' !== strtoupper( (string) ( $item['catalogState'] ?? '' ) ) ) {
			return false;
		}

		$existing = get_posts( array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'any',
			'numberposts' => 1,
			'meta_query'  => array(
				'relation' => 'AND',
				array( 'key' => '_kalahamoon_product_id', 'value' => $id ),
				array( 'key' => '_kalahamoon_catalog_snapshot_key', 'value' => $key ),
			),
		) );
		$post_data = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
			'post_content' => wp_kses_post( (string) ( $item['description'] ?? '' ) ),
			'post_status'  => 'publish',
		);
		$post_id = empty( $existing )
			? wp_insert_post( $post_data, true )
			: wp_update_post( array_merge( $post_data, array( 'ID' => (int) $existing[0]->ID ) ), true );
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}
		$post_id = (int) $post_id;

		$price_visible = ! empty( $item['priceVisible'] );
		$price         = $price_visible && is_numeric( $item['price'] ?? null ) ? (float) $item['price'] : 0.0;
		$currency      = strtoupper( sanitize_key( (string) ( $item['currency'] ?? 'IRR' ) ) );
		$metadata = array(
			'primaryOffer' => is_array( $item['primaryOffer'] ?? null ) ? $item['primaryOffer'] : array(),
			'offers'       => is_array( $item['offers'] ?? null ) ? $item['offers'] : array(),
		);

		update_post_meta( $post_id, '_kalahamoon_product_id', $id );
		update_post_meta( $post_id, '_kalahamoon_product_source', 'catalog_projection' );
		update_post_meta( $post_id, '_kalahamoon_catalog_projection', 1 );
		update_post_meta( $post_id, '_kalahamoon_catalog_state', 'PUBLISHED' );
		update_post_meta( $post_id, '_kalahamoon_catalog_snapshot_id', sanitize_text_field( (string) ( $snapshot['id'] ?? '' ) ) );
		update_post_meta( $post_id, '_kalahamoon_catalog_snapshot_revision', sanitize_text_field( (string) ( $snapshot['revision'] ?? '' ) ) );
		update_post_meta( $post_id, '_kalahamoon_catalog_snapshot_key', $key );
		update_post_meta( $post_id, '_kalahamoon_catalog_public_handle', sanitize_text_field( (string) ( $item['publicHandle'] ?? '' ) ) );
		update_post_meta( $post_id, '_kalahamoon_catalog_sort_order', (int) ( $item['sortOrder'] ?? 0 ) );
		update_post_meta( $post_id, '_kalahamoon_public_ready_static', 1 );
		update_post_meta( $post_id, '_kalahamoon_price_visible', $price_visible ? 1 : 0 );
		update_post_meta( $post_id, '_kalahamoon_price_freshness', sanitize_key( (string) ( $item['priceFreshness'] ?? '' ) ) );
		update_post_meta( $post_id, '_kalahamoon_last_checked_at', sanitize_text_field( (string) ( $item['lastCheckedAt'] ?? '' ) ) );
		update_post_meta( $post_id, '_kalahamoon_price', $price );
		update_post_meta( $post_id, '_kalahamoon_old_price', 0 );
		update_post_meta( $post_id, '_kalahamoon_currency', '' !== $currency ? $currency : 'IRR' );
		update_post_meta( $post_id, '_kalahamoon_inventory', 1 );
		update_post_meta( $post_id, '_kalahamoon_image_url', esc_url_raw( (string) ( $item['imageUrl'] ?? '' ) ) );
		update_post_meta( $post_id, '_kalahamoon_original_image_url', esc_url_raw( (string) ( $item['imageUrl'] ?? '' ) ) );
		update_post_meta( $post_id, '_kalahamoon_listing_url', esc_url_raw( (string) ( $item['listingUrl'] ?? '' ) ) );
		update_post_meta( $post_id, '_kalahamoon_listing_id', sanitize_text_field( (string) ( $item['primaryOffer']['id'] ?? $id ) ) );
		update_post_meta( $post_id, '_kalahamoon_platform', sanitize_key( (string) ( $item['primaryOffer']['platform'] ?? '' ) ) );
		update_post_meta( $post_id, '_kalahamoon_seller_name', sanitize_text_field( (string) ( $item['primaryOffer']['sellerName'] ?? '' ) ) );
		update_post_meta( $post_id, '_kalahamoon_status', 'active' );
		update_post_meta( $post_id, '_kalahamoon_publication_state', 'PUBLISHED' );
		update_post_meta( $post_id, '_kalahamoon_listings', wp_json_encode( $item['listings'] ?? array(), JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $post_id, '_kalahamoon_metadata', wp_json_encode( $metadata, JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $post_id, '_kalahamoon_last_synced', current_time( 'mysql' ) );
		update_post_meta( $post_id, '_kalahamoon_source_synced_at', sanitize_text_field( (string) ( $item['priceUpdatedAt'] ?? '' ) ) );
		update_post_meta( $post_id, '_kalahamoon_source_sync_epoch', 0 );

		if ( $price_visible && $price > 0 ) {
			self::record_price( $id, $price, '' !== $currency ? $currency : 'IRR' );
		}
		self::set_projection_collections( $post_id, $item['collections'] ?? array() );

		return true;
	}

	/**
	 * Create or update a product authored in WordPress.
	 *
	 * Local product IDs are generated by the plugin so they cannot shadow remote
	 * catalog records. They retain the same normalized metadata shape as synced
	 * records, which lets existing blocks and shortcodes use them unchanged.
	 *
	 * @return int|WP_Error The cached product post ID on success.
	 */
	public static function save_manual( array $data, int $post_id = 0 ) {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return new WP_Error( 'kalahamoon_catalog_read_only', __( 'Catalog products are managed in Kalahamoon.', 'kalahamoon' ), array( 'status' => 403 ) );
		}

		$title = sanitize_text_field( (string) ( $data['title'] ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'kalahamoon_product_title', __( 'A product title is required.', 'kalahamoon' ) );
		}

		if ( $post_id > 0 && ! self::is_manual_post( $post_id ) ) {
			return new WP_Error( 'kalahamoon_product_source', __( 'Only local products can be edited here.', 'kalahamoon' ) );
		}

		$post_data = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => $title,
			'post_content' => wp_kses_post( (string) ( $data['description'] ?? '' ) ),
			'post_status'  => 'publish',
		);
		if ( $post_id > 0 ) {
			$post_data['ID'] = $post_id;
			$saved_post_id   = wp_update_post( $post_data, true );
		} else {
			$saved_post_id = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $saved_post_id ) || ! $saved_post_id ) {
			return is_wp_error( $saved_post_id ) ? $saved_post_id : new WP_Error( 'kalahamoon_product_save', __( 'Could not save the product.', 'kalahamoon' ) );
		}

		$saved_post_id = (int) $saved_post_id;
		$product_id    = (string) get_post_meta( $saved_post_id, '_kalahamoon_product_id', true );
		if ( '' === $product_id ) {
			$product_id = 'local-' . wp_generate_uuid4();
		}

		$price = is_numeric( $data['price'] ?? null ) ? max( 0, (float) $data['price'] ) : 0.0;
		$currency = strtoupper( sanitize_key( (string) ( $data['currency'] ?? 'IRR' ) ) );
		if ( ! in_array( $currency, array( 'IRR', 'USD', 'EUR' ), true ) ) {
			$currency = 'IRR';
		}
		$status = sanitize_key( (string) ( $data['status'] ?? 'active' ) );
		if ( ! in_array( $status, array( 'active', 'inactive' ), true ) ) {
			$status = 'active';
		}

		$image_url     = esc_url_raw( (string) ( $data['image_url'] ?? '' ) );
		$listing_url   = esc_url_raw( (string) ( $data['listing_url'] ?? '' ) );
		$platform      = sanitize_key( (string) ( $data['platform'] ?? 'wordpress' ) );
		$attachment_id = absint( $data['image_attachment_id'] ?? 0 );
		if ( $attachment_id > 0 ) {
			$attachment_url = (string) wp_get_attachment_url( $attachment_id );
			if ( '' !== $attachment_url ) {
				$image_url = $attachment_url;
				set_post_thumbnail( $saved_post_id, $attachment_id );
			}
		}

		$listing = array(
			'id'               => $product_id,
			'title'            => $title,
			'price'            => $price,
			'currency'         => $currency,
			'platform'         => $platform,
			'listingUrl'       => $listing_url,
			'inventory'        => 1,
			'status'           => $status,
			'publicationState' => 'VERIFIED',
		);

		update_post_meta( $saved_post_id, '_kalahamoon_product_id', $product_id );
		update_post_meta( $saved_post_id, '_kalahamoon_product_source', 'manual' );
		update_post_meta( $saved_post_id, '_kalahamoon_price', $price );
		update_post_meta( $saved_post_id, '_kalahamoon_old_price', 0 );
		update_post_meta( $saved_post_id, '_kalahamoon_currency', $currency );
		update_post_meta( $saved_post_id, '_kalahamoon_inventory', 1 );
		update_post_meta( $saved_post_id, '_kalahamoon_image_url', $image_url );
		update_post_meta( $saved_post_id, '_kalahamoon_local_image_url', $image_url );
		update_post_meta( $saved_post_id, '_kalahamoon_image_attachment_id', $attachment_id );
		update_post_meta( $saved_post_id, '_kalahamoon_platform', $platform );
		update_post_meta( $saved_post_id, '_kalahamoon_listing_id', $product_id );
		update_post_meta( $saved_post_id, '_kalahamoon_listing_url', $listing_url );
		update_post_meta( $saved_post_id, '_kalahamoon_status', $status );
		update_post_meta( $saved_post_id, '_kalahamoon_publication_state', 'VERIFIED' );
		update_post_meta( $saved_post_id, '_kalahamoon_listings', wp_json_encode( array( $listing ), JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $saved_post_id, '_kalahamoon_last_synced', current_time( 'mysql' ) );
		update_post_meta( $saved_post_id, '_kalahamoon_source_synced_at', gmdate( 'c' ) );
		update_post_meta( $saved_post_id, '_kalahamoon_source_sync_epoch', time() );
		self::refresh_static_readiness( $saved_post_id );

		return $saved_post_id;
	}

	public static function is_manual_post( int $post_id ): bool {
		return self::POST_TYPE === get_post_type( $post_id )
			&& 'manual' === get_post_meta( $post_id, '_kalahamoon_product_source', true );
	}

	/**
	 * Format a WP_Post into a standardized product array.
	 */
	public static function format_product( WP_Post $post ): array {
		$kalahamoon_id = get_post_meta( $post->ID, '_kalahamoon_product_id', true );
		$metadata             = json_decode( get_post_meta( $post->ID, '_kalahamoon_metadata', true ) ?: '{}', true );
		$specs                = json_decode( get_post_meta( $post->ID, '_kalahamoon_specs', true ) ?: '[]', true );
		$listings             = json_decode( get_post_meta( $post->ID, '_kalahamoon_listings', true ) ?: '[]', true );
		$source_categories    = json_decode( get_post_meta( $post->ID, '_kalahamoon_source_categories', true ) ?: '[]', true );
		$canonical_categories = json_decode( get_post_meta( $post->ID, '_kalahamoon_canonical_categories', true ) ?: '[]', true );
		$comparison_type      = json_decode( get_post_meta( $post->ID, '_kalahamoon_comparison_type', true ) ?: 'null', true );
		$publication_issues   = json_decode( get_post_meta( $post->ID, '_kalahamoon_publication_review_issues', true ) ?: '[]', true );
		$publication_issues   = is_array( $publication_issues )
			? array_values( array_filter( $publication_issues, 'is_string' ) )
			: array();
		if ( '1' === (string) get_post_meta( $post->ID, '_kalahamoon_catalog_projection', true ) ) {
			$metadata      = is_array( $metadata ) ? $metadata : array();
			$price_visible = '1' === (string) get_post_meta( $post->ID, '_kalahamoon_price_visible', true );
			$collections   = wp_get_object_terms( $post->ID, 'kalahamoon_collection', array( 'fields' => 'all' ) );
			$collections   = is_array( $collections ) ? array_values( array_filter( array_map( static function ( $term ): array {
				return $term instanceof WP_Term ? array( 'name' => $term->name, 'slug' => $term->slug ) : array();
			}, $collections ) ) ) : array();

			return array(
				'id'                => $kalahamoon_id,
				'wp_post_id'        => $post->ID,
				'title'             => $post->post_title,
				'description'       => $post->post_content,
				'price'             => $price_visible ? (float) get_post_meta( $post->ID, '_kalahamoon_price', true ) : null,
				'oldPrice'          => null,
				'currency'          => get_post_meta( $post->ID, '_kalahamoon_currency', true ) ?: 'IRR',
				'inventory'         => 1,
				'imageUrl'          => get_post_meta( $post->ID, '_kalahamoon_image_url', true ),
				'platform'          => get_post_meta( $post->ID, '_kalahamoon_platform', true ),
				'listingId'         => get_post_meta( $post->ID, '_kalahamoon_listing_id', true ),
				'listingUrl'        => get_post_meta( $post->ID, '_kalahamoon_listing_url', true ),
				'sellerName'        => get_post_meta( $post->ID, '_kalahamoon_seller_name', true ),
				'listings'          => is_array( $listings ) ? $listings : array(),
				'primaryOffer'      => is_array( $metadata['primaryOffer'] ?? null ) ? $metadata['primaryOffer'] : array(),
				'offers'            => is_array( $metadata['offers'] ?? null ) ? $metadata['offers'] : array(),
				'collections'       => $collections,
				'catalogProjection' => true,
				'catalogState'      => get_post_meta( $post->ID, '_kalahamoon_catalog_state', true ),
				'catalogSnapshotId' => get_post_meta( $post->ID, '_kalahamoon_catalog_snapshot_id', true ),
				'catalogRevision'   => get_post_meta( $post->ID, '_kalahamoon_catalog_snapshot_revision', true ),
				'publicHandle'      => get_post_meta( $post->ID, '_kalahamoon_catalog_public_handle', true ),
				'sortOrder'         => (int) get_post_meta( $post->ID, '_kalahamoon_catalog_sort_order', true ),
				'publicReady'       => '1' === (string) get_post_meta( $post->ID, '_kalahamoon_public_ready_static', true ),
				'priceVisible'      => $price_visible,
				'priceFreshness'    => get_post_meta( $post->ID, '_kalahamoon_price_freshness', true ),
				'lastCheckedAt'     => get_post_meta( $post->ID, '_kalahamoon_last_checked_at', true ),
				'priceUpdatedAt'    => get_post_meta( $post->ID, '_kalahamoon_source_synced_at', true ),
				'lastSynced'        => get_post_meta( $post->ID, '_kalahamoon_last_synced', true ),
				'source'            => 'catalog_projection',
			);
		}

		return array(
			'id'            => $kalahamoon_id,
			'wp_post_id'    => $post->ID,
			'title'         => $post->post_title,
			'description'   => $post->post_content,
			'price'         => (float) get_post_meta( $post->ID, '_kalahamoon_price', true ),
			'oldPrice'      => (float) get_post_meta( $post->ID, '_kalahamoon_old_price', true ),
			'currency'      => get_post_meta( $post->ID, '_kalahamoon_currency', true ) ?: 'IRR',
			'inventory'     => (int) get_post_meta( $post->ID, '_kalahamoon_inventory', true ),
			'imageUrl'      => get_post_meta( $post->ID, '_kalahamoon_image_url', true ),
			'platform'      => get_post_meta( $post->ID, '_kalahamoon_platform', true ),
			'listingId'     => get_post_meta( $post->ID, '_kalahamoon_listing_id', true ),
			'listingUrl'    => get_post_meta( $post->ID, '_kalahamoon_listing_url', true ),
			'externalId'    => get_post_meta( $post->ID, '_kalahamoon_external_id', true ),
			'sellerName'    => get_post_meta( $post->ID, '_kalahamoon_seller_name', true ),
			'specs'         => $specs,
			'metadata'      => $metadata,
			'listings'      => $listings,
			'status'        => get_post_meta( $post->ID, '_kalahamoon_status', true ),
			'publicationState' => get_post_meta( $post->ID, '_kalahamoon_publication_state', true ),
			'publicationReviewedAt' => get_post_meta( $post->ID, '_kalahamoon_publication_reviewed_at', true ),
			'publicationReviewerType' => get_post_meta( $post->ID, '_kalahamoon_publication_reviewer_type', true ),
			'publicationReviewerId' => get_post_meta( $post->ID, '_kalahamoon_publication_reviewer_id', true ),
			'publicationReadinessIssues' => $publication_issues,
			'lastSynced'          => get_post_meta( $post->ID, '_kalahamoon_last_synced', true ),
			'priceUpdatedAt'      => get_post_meta( $post->ID, '_kalahamoon_source_synced_at', true ) ?: get_post_meta( $post->ID, '_kalahamoon_last_synced', true ),
			'brand'               => $metadata['brand'] ?? '',
			'category'            => $canonical_categories[0]['label'] ?? $metadata['category'] ?? '',
			'sourceCategories'    => is_array( $source_categories ) ? $source_categories : array(),
			'canonicalCategories' => is_array( $canonical_categories ) ? $canonical_categories : array(),
			'comparisonType'      => is_array( $comparison_type ) ? $comparison_type : null,
			'categoryConfidence'  => get_post_meta( $post->ID, '_kalahamoon_category_confidence', true ),
			'categoryReviewStatus' => get_post_meta( $post->ID, '_kalahamoon_category_review_status', true ),
			'source'              => get_post_meta( $post->ID, '_kalahamoon_product_source', true ) ?: 'synced',
		);
	}

	/**
	 * Keep the local cache authoritative after a publication mutation.
	 * This avoids showing a stale action after Kalahamoon has accepted a review.
	 */
	public static function update_publication_cache( string $listing_id, array $publication ): ?array {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return null;
		}

		$posts = get_posts( array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'meta_key'    => '_kalahamoon_listing_id',
			'meta_value'  => $listing_id,
			'numberposts' => 1,
		) );
		if ( empty( $posts ) ) {
			return null;
		}

		$post_id = (int) $posts[0]->ID;
		update_post_meta( $post_id, '_kalahamoon_publication_state', $publication['publicationState'] ?? '' );
		update_post_meta( $post_id, '_kalahamoon_publication_reviewed_at', $publication['publicationReviewedAt'] ?? current_time( 'mysql', true ) );
		update_post_meta( $post_id, '_kalahamoon_publication_reviewer_type', $publication['publicationReviewerType'] ?? 'API_KEY' );
		update_post_meta( $post_id, '_kalahamoon_publication_reviewer_id', $publication['publicationReviewerId'] ?? '' );
		update_post_meta( $post_id, '_kalahamoon_publication_review_issues', wp_json_encode( $publication['publicationReadinessIssues'] ?? $publication['publicationReviewIssues'] ?? array() ) );
		self::refresh_static_readiness( $post_id );

		return self::format_product( $posts[0] );
	}

	/**
	 * Get all cached products with optional filters.
	 */
	public static function get_all( array $args = array() ): array {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return self::get_catalog_projection_all( $args );
		}

		$page  = max( 1, absint( $args['page'] ?? 1 ) );
		$limit = max( 1, min( 100, absint( $args['limit'] ?? 20 ) ) );
		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		$meta_query = array();
		$tax_query  = array();

		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = $args['search'];
		}

		if ( ! empty( $args['category'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'kalahamoon_category',
				'field'    => 'slug',
				'terms'    => sanitize_title( (string) $args['category'] ),
			);
		}
		if ( ! empty( $args['brand'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'kalahamoon_brand',
				'field'    => 'slug',
				'terms'    => sanitize_title( (string) $args['brand'] ),
			);
		}
		if ( ! empty( $args['collection'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'kalahamoon_collection',
				'field'    => 'slug',
				'terms'    => sanitize_title( (string) $args['collection'] ),
			);
		}
		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = count( $tax_query ) > 1
				? array_merge( array( 'relation' => 'AND' ), $tax_query )
				: $tax_query;
		}

		if ( ! empty( $args['publication_state'] ) ) {
			$meta_query[] = array(
				'key'   => '_kalahamoon_publication_state',
				'value' => strtoupper( sanitize_key( (string) $args['publication_state'] ) ),
			);
		}
		if ( ! empty( $args['platform'] ) ) {
			$meta_query[] = array(
				'key'   => '_kalahamoon_platform',
				'value' => sanitize_key( (string) $args['platform'] ),
			);
		}
		if ( isset( $args['min_price'] ) && is_numeric( $args['min_price'] ) ) {
			$meta_query[] = array(
				'key'     => '_kalahamoon_price',
				'value'   => max( 0, (float) $args['min_price'] ),
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}
		if ( isset( $args['max_price'] ) && is_numeric( $args['max_price'] ) ) {
			$meta_query[] = array(
				'key'     => '_kalahamoon_price',
				'value'   => max( 0, (float) $args['max_price'] ),
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}

		$public_ready = ! empty( $args['public_ready'] );
		$source       = sanitize_key( (string) ( $args['source'] ?? '' ) );
		if ( $public_ready ) {
			$authority = Kalahamoon_Catalog_Policy::normalize_authority(
				$args['authority'] ?? get_option( 'kalahamoon_catalog_authority', 'hybrid' )
			);
			if ( 'hybrid' !== $authority ) {
				$source = $authority;
			}
			$meta_query[] = array( 'key' => '_kalahamoon_public_ready_static', 'value' => '1' );
			$meta_query[] = array(
				'key'     => '_kalahamoon_source_sync_epoch',
				'value'   => time() - 72 * HOUR_IN_SECONDS,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
			$meta_query[] = array(
				'key'     => '_kalahamoon_source_sync_epoch',
				'value'   => time() + 300,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}

		if ( 'remote' === $source ) {
			// Older cache rows predate the source marker, so only explicit local
			// records are excluded from a remote catalog query after an upgrade.
			$source_clause = array(
				'relation' => 'OR',
				array(
					'key'     => '_kalahamoon_product_source',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_kalahamoon_product_source',
					'value'   => 'manual',
					'compare' => '!=',
				),
			);
			$meta_query[] = $source_clause;
		}

		if ( 'local' === $source ) {
			$source_clause = array(
				'key'   => '_kalahamoon_product_source',
				'value' => 'manual',
			);
			$meta_query[] = $source_clause;
		}

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = count( $meta_query ) > 1
				? array_merge( array( 'relation' => 'AND' ), $meta_query )
				: $meta_query;
		}

		switch ( sanitize_key( (string) ( $args['sort'] ?? 'newest' ) ) ) {
			case 'price_asc':
			case 'price_desc':
				$query_args['meta_key'] = '_kalahamoon_price';
				$query_args['orderby']  = 'meta_value_num';
				$query_args['order']    = 'price_asc' === $args['sort'] ? 'ASC' : 'DESC';
				break;
			case 'title_asc':
				$query_args['orderby'] = 'title';
				$query_args['order']   = 'ASC';
				break;
		}

		$query = new WP_Query( $query_args );
		$products = array();

		foreach ( $query->posts as $post ) {
			$product = self::format_product( $post );
			$products[] = $public_ready
				? Kalahamoon_Catalog_Policy::apply( $product, null, $args['authority'] ?? null )
				: $product;
		}

		return array(
			'items'      => $products,
			'total'      => $query->found_posts,
			'totalPages' => $query->max_num_pages,
		);
	}

	/**
	 * Active projection queries never look at source timestamps or legacy review
	 * fields. The snapshot pointer already identifies Kalahamoon's approved
	 * population, while the price visibility bit controls stale-price display.
	 */
	private static function get_catalog_projection_all( array $args ): array {
		$key = Kalahamoon_Catalog_Consumer::active_snapshot_key();
		if ( '' === $key ) {
			return array( 'items' => array(), 'total' => 0, 'totalPages' => 0 );
		}

		$page  = max( 1, absint( $args['page'] ?? 1 ) );
		$limit = max( 1, min( 100, absint( $args['limit'] ?? 20 ) ) );
		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'paged'          => $page,
			'meta_key'       => '_kalahamoon_catalog_sort_order',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_kalahamoon_catalog_snapshot_key', 'value' => $key ),
			),
		);
		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( (string) $args['search'] );
		}
		if ( ! empty( $args['collection'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'kalahamoon_collection',
					'field'    => 'slug',
					'terms'    => sanitize_title( (string) $args['collection'] ),
				),
			);
		}
		if ( ! empty( $args['public_ready'] ) ) {
			$query_args['meta_query'][] = array( 'key' => '_kalahamoon_public_ready_static', 'value' => '1' );
		}
		if ( isset( $args['min_price'] ) && is_numeric( $args['min_price'] ) ) {
			$query_args['meta_query'][] = array( 'key' => '_kalahamoon_price', 'value' => max( 0, (float) $args['min_price'] ), 'compare' => '>=', 'type' => 'NUMERIC' );
		}
		if ( isset( $args['max_price'] ) && is_numeric( $args['max_price'] ) ) {
			$query_args['meta_query'][] = array( 'key' => '_kalahamoon_price', 'value' => max( 0, (float) $args['max_price'] ), 'compare' => '<=', 'type' => 'NUMERIC' );
		}
		if ( 'price_asc' === ( $args['sort'] ?? '' ) || 'price_desc' === ( $args['sort'] ?? '' ) ) {
			$query_args['meta_key'] = '_kalahamoon_price';
			$query_args['orderby']  = 'meta_value_num';
			$query_args['order']    = 'price_asc' === $args['sort'] ? 'ASC' : 'DESC';
		} elseif ( 'title_asc' === ( $args['sort'] ?? '' ) ) {
			$query_args['meta_key'] = '';
			$query_args['orderby']  = 'title';
			$query_args['order']    = 'ASC';
		}

		$query    = new WP_Query( $query_args );
		$products = array();
		foreach ( $query->posts as $post ) {
			// This query is constrained to the active revision. Its public-readiness
			// and price fields are immutable projection values, not local policy inputs.
			$products[] = self::format_product( $post );
		}

		return array(
			'items'      => $products,
			'total'      => $query->found_posts,
			'totalPages' => $query->max_num_pages,
		);
	}

	/**
	 * Return a bounded, presentation-safe price timeline for a product that has
	 * already passed the public catalog policy. The caller is responsible for
	 * that policy check because this cache class also serves editor previews.
	 *
	 * @return list<array{price:float,currency:string,capturedAt:string}>
	 */
	public static function get_price_history( string $product_id, int $limit = 12 ): array {
		$product_id = trim( $product_id );
		if ( '' === $product_id || ! preg_match( '/^[A-Za-z0-9_-]+$/', $product_id ) ) {
			return array();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_price_history';
		$limit = max( 1, min( 24, $limit ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT price, currency, captured_at FROM {$table} WHERE product_id = %s ORDER BY captured_at DESC LIMIT %d",
				$product_id,
				$limit
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( array $row ): array {
						return array(
							'price'      => max( 0.0, (float) ( $row['price'] ?? 0 ) ),
							'currency'   => sanitize_key( (string) ( $row['currency'] ?? 'IRR' ) ),
							'capturedAt' => sanitize_text_field( (string) ( $row['captured_at'] ?? '' ) ),
						);
					},
					$rows
				),
				static fn( array $row ): bool => $row['price'] > 0 && '' !== $row['capturedAt']
			)
		);
	}

	/**
	 * Count records that satisfy the same public policy used by catalog cards.
	 *
	 * A successful sync can contain drafts or incomplete records, so using the
	 * raw cache total in public-readiness messaging would overstate what a
	 * visitor can actually browse.
	 */
	public static function public_ready_count(): int {
		$catalog = self::get_all(
			array(
				'limit'        => 1,
				'public_ready' => true,
			)
		);

		return max( 0, (int) ( $catalog['total'] ?? 0 ) );
	}

	/**
	 * Removes cache rows absent from a complete authoritative sync.
	 *
	 * @param array<int, string> $kalahamoon_ids
	 */
	public static function delete_missing_ids( array $kalahamoon_ids, string $publication_state = '' ): int {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return 0;
		}

		$args = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		);
		$args['meta_query'] = array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array(
					'key'     => '_kalahamoon_product_source',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_kalahamoon_product_source',
					'value'   => 'manual',
					'compare' => '!=',
				),
			),
		);
		if ( '' !== $publication_state ) {
			$args['meta_query'][] = array(
				'key'   => '_kalahamoon_publication_state',
				'value' => $publication_state,
			);
		}

		$known_ids = array_fill_keys( array_map( 'strval', $kalahamoon_ids ), true );
		$deleted   = 0;
		foreach ( get_posts( $args ) as $post_id ) {
			$product_id = (string) get_post_meta( (int) $post_id, '_kalahamoon_product_id', true );
			if ( '' !== $product_id && ! isset( $known_ids[ $product_id ] ) && wp_delete_post( (int) $post_id, true ) ) {
				$deleted++;
			}
		}

		return $deleted;
	}

	/**
	 * Remove a failed staging revision only. The active revision has a different
	 * key and remains untouched even if a fetch or one item write failed.
	 */
	public static function delete_catalog_snapshot( string $snapshot_key ): int {
		if ( '' === $snapshot_key ) {
			return 0;
		}
		$posts = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_kalahamoon_catalog_projection', 'value' => '1' ),
				array( 'key' => '_kalahamoon_catalog_snapshot_key', 'value' => $snapshot_key ),
			),
		) );
		$deleted = 0;
		foreach ( $posts as $post_id ) {
			if ( wp_delete_post( (int) $post_id, true ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	/**
	 * Optional successful-sync cleanup. The consumer calls this only after the
	 * active pointer moved and an operator explicitly enables pruning.
	 */
	public static function delete_catalog_snapshots_except( string $snapshot_key ): int {
		$posts = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_kalahamoon_catalog_projection',
			'meta_value'     => '1',
		) );
		$deleted = 0;
		foreach ( $posts as $post_id ) {
			if ( $snapshot_key !== (string) get_post_meta( (int) $post_id, '_kalahamoon_catalog_snapshot_key', true ) && wp_delete_post( (int) $post_id, true ) ) {
				$deleted++;
			}
		}
		return $deleted;
	}

	private static function normalize_source_timestamp( string $value ): int {
		$timestamp = '' !== trim( $value ) ? strtotime( $value ) : false;
		if ( false === $timestamp || $timestamp > time() + 300 ) {
			return 0;
		}
		return max( 1, $timestamp );
	}

	/**
	 * Collections arrive as catalog projection data. Creating matching local
	 * taxonomy terms is cache indexing only; WordPress never changes their
	 * membership upstream or offers an editor control in consumer mode.
	 */
	private static function set_projection_collections( int $post_id, $collections ): void {
		if ( ! is_array( $collections ) ) {
			wp_set_object_terms( $post_id, array(), 'kalahamoon_collection', false );
			return;
		}
		$term_ids = array();
		foreach ( $collections as $collection ) {
			if ( ! is_array( $collection ) ) {
				continue;
			}
			$name = sanitize_text_field( (string) ( $collection['name'] ?? '' ) );
			$slug = sanitize_title( (string) ( $collection['slug'] ?? $name ) );
			if ( '' === $name || '' === $slug ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, 'kalahamoon_collection' );
			if ( ! $term ) {
				$created = wp_insert_term( $name, 'kalahamoon_collection', array( 'slug' => $slug ) );
				$term_id = is_wp_error( $created ) ? 0 : (int) ( $created['term_id'] ?? 0 );
			} else {
				$term_id = (int) $term->term_id;
			}
			if ( $term_id > 0 ) {
				$term_ids[] = $term_id;
			}
		}
		wp_set_object_terms( $post_id, array_values( array_unique( $term_ids ) ), 'kalahamoon_collection', false );
	}

	/**
	 * Persist non-time-dependent readiness so WordPress can paginate accurately.
	 * Freshness remains a dynamic numeric query and is re-evaluated at render time.
	 */
	private static function refresh_static_readiness( int $post_id ): void {
		if ( '1' === (string) get_post_meta( $post_id, '_kalahamoon_catalog_projection', true ) ) {
			// The projection was already accepted upstream; re-evaluating legacy
			// source facts here would create a second publication authority.
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			update_post_meta( $post_id, '_kalahamoon_public_ready_static', 0 );
			return;
		}

		$product = self::format_product( $post );
		$required = array( 'id', 'title', 'imageUrl', 'listingUrl' );
		$ready    = true;
		foreach ( $required as $field ) {
			if ( '' === trim( (string) ( $product[ $field ] ?? '' ) ) ) {
				$ready = false;
			}
		}
		foreach ( array( 'imageUrl', 'listingUrl' ) as $field ) {
			$url    = (string) ( $product[ $field ] ?? '' );
			$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
			if ( 'https' !== $scheme ) {
				$ready = false;
			}
		}
		$ready = $ready
			&& 'VERIFIED' === strtoupper( (string) ( $product['publicationState'] ?? '' ) )
			&& 'active' === strtolower( (string) ( $product['status'] ?? '' ) )
			&& empty( $product['publicationReadinessIssues'] ?? array() )
			&& is_numeric( $product['price'] ?? null )
			&& (float) $product['price'] > 0;

		update_post_meta( $post_id, '_kalahamoon_public_ready_static', $ready ? 1 : 0 );
	}

	/**
	 * Pick the best product image URL from API data.
	 *
	 * Prefers the first gallery image whose URL is not a collage composite or a
	 * seller/store logo. Falls back to the raw imageUrl from the listing.
	 */
	private static function resolve_best_image( array $item, array $listing ): string {
		// Patterns that indicate a non-product image.
		$bad_patterns = array( 'collage', 'composite', 'gallery-thumb', 'store-logo', 'seller-logo', 'shop-logo', 'brand-logo', 'seller_logo', 'shop_logo' );

		// Collect candidate image arrays from both item and listing level.
		$candidates = array();
		foreach ( array( $item, $listing ) as $src ) {
			foreach ( array( 'images', 'galleryImages', 'gallery' ) as $key ) {
				if ( ! empty( $src[ $key ] ) && is_array( $src[ $key ] ) ) {
					$candidates = array_merge( $candidates, $src[ $key ] );
				}
			}
		}

		foreach ( $candidates as $img ) {
			$url = is_array( $img ) ? ( $img['url'] ?? $img['src'] ?? '' ) : (string) $img;
			if ( empty( $url ) ) continue;

			$bad = false;
			foreach ( $bad_patterns as $p ) {
				if ( stripos( $url, $p ) !== false ) {
					$bad = true;
					break;
				}
			}
			if ( ! $bad ) {
				return $url;
			}
		}

		// Fall back to explicit imageUrl field, filtering obvious logo URLs.
		$fallback = (string) ( $listing['imageUrl'] ?? $item['imageUrl'] ?? '' );
		foreach ( $bad_patterns as $p ) {
			if ( stripos( $fallback, $p ) !== false ) {
				return '';
			}
		}

		return $fallback;
	}

	/**
	 * Mirror public verified imagery once so merchant outages do not collapse
	 * published cards. A failed refresh keeps the last known good attachment and
	 * never deletes media authored locally.
	 */
	private static function mirror_verified_image( int $post_id, string $source_url, array $listing ): string {
		$current_attachment = absint( get_post_meta( $post_id, '_kalahamoon_image_attachment_id', true ) );
		$current_source     = (string) get_post_meta( $post_id, '_kalahamoon_image_source_url', true );
		$current_url        = $current_attachment ? (string) wp_get_attachment_image_url( $current_attachment, 'full' ) : '';

		if ( 'VERIFIED' !== strtoupper( (string) ( $listing['publicationState'] ?? '' ) ) || '' === $source_url ) {
			return '' !== $current_url ? $current_url : $source_url;
		}
		if ( $current_source === $source_url && '' !== $current_url ) {
			return $current_url;
		}
		if ( null !== Kalahamoon_Image_Policy::remote_url_issue( $source_url ) ) {
			return $current_url;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$download = Kalahamoon_Image_Policy::download_remote( $source_url );
		if ( is_wp_error( $download ) ) {
			return '' !== $current_url ? $current_url : $source_url;
		}

		$tmp  = $download['tmp_name'];
		$name = 'kalahamoon-product-' . $post_id . '.' . $download['extension'];
		$file = array( 'name' => $name, 'tmp_name' => $tmp );
		$attachment_id = media_handle_sideload( $file, $post_id, get_the_title( $post_id ) );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return '' !== $current_url ? $current_url : $source_url;
		}

		update_post_meta( $post_id, '_kalahamoon_image_attachment_id', (int) $attachment_id );
		update_post_meta( $post_id, '_kalahamoon_image_source_url', $source_url );
		set_post_thumbnail( $post_id, (int) $attachment_id );
		$mirrored_url = (string) wp_get_attachment_image_url( (int) $attachment_id, 'full' );
		return '' !== $mirrored_url ? $mirrored_url : ( '' !== $current_url ? $current_url : $source_url );
	}

	/**
	 * Record a price point for history tracking.
	 */
	private static function record_price( string $product_id, float $price, string $currency ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_price_history';

		// Only record if price changed since last entry
		$last = $wpdb->get_var( $wpdb->prepare(
			"SELECT price FROM {$table} WHERE product_id = %s ORDER BY captured_at DESC LIMIT 1",
			$product_id
		) );

		if ( null === $last || abs( (float) $last - $price ) > 0.01 ) {
			$wpdb->insert( $table, array(
				'product_id'  => $product_id,
				'price'       => $price,
				'currency'    => $currency,
				'captured_at' => current_time( 'mysql' ),
			), array( '%s', '%f', '%s', '%s' ) );
		}
	}
}
