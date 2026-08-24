<?php
/**
 * One-time, file-only WooCommerce product importer.
 *
 * The importer deliberately consumes a signed JSON export rather than reaching
 * into a previous application at runtime. That keeps migration credentials and
 * tenant access outside the public Chidemoon deployment.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Importer {
	private const EXPORT_SCHEMA       = 1;
	private const DEFAULT_ORGANIZATION_SLUG = 'chidemoon';
	private const MAX_FILE_SIZE       = 52428800;
	private const MAX_IMAGE_SIZE      = 10485760;
	private const LOCK_OPTION         = 'chidemoon_core_product_import_lock';
	private const LOCK_TIMEOUT        = 1800;

	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'chidemoon import-products', array( __CLASS__, 'command_import' ) );
		}
	}

	/**
	 * Imports an offline, checksum-verified product export.
	 *
	 * ## OPTIONS
	 *
	 * --file=<path>
	 * : JSON export with schemaVersion, organization, items, skipped, and checksum fields.
	 *
	 * [--dry-run]
	 * : Validate records and print reconciliation data without writing products or downloading media.
	 *
	 * [--organization-slug=<slug>]
	 * : Expected source organization slug. Defaults to the dedicated Chidemoon organization slug.
	 *
	 * ## EXAMPLES
	 *
	 *     wp chidemoon import-products --file=/imports/products.json --dry-run
	 *     wp chidemoon import-products --file=/imports/products.json --organization-slug=chidemoon
	 *
	 * @param string[]               $args       Positional arguments.
	 * @param array<string, string>  $assoc_args Associative command arguments.
	 */
	public static function command_import( array $args, array $assoc_args ): void {
		unset( $args );

		if ( get_current_user_id() > 0 && ! current_user_can( 'chidemoon_manage_affiliate' ) ) {
			WP_CLI::error( 'The current user cannot manage Chidemoon affiliate products.' );
		}

		$file              = isset( $assoc_args['file'] ) ? (string) $assoc_args['file'] : '';
		$dry_run           = isset( $assoc_args['dry-run'] );
		$organization_slug = self::normalize_organization_slug( $assoc_args['organization-slug'] ?? self::DEFAULT_ORGANIZATION_SLUG );
		if ( '' === $file ) {
			WP_CLI::error( 'Pass --file with a Chidemoon product export.' );
		}
		if ( '' === $organization_slug ) {
			WP_CLI::error( 'Pass a valid --organization-slug for the dedicated Chidemoon source organization.' );
		}

		$export = self::read_and_verify_export( $file, $organization_slug );
		$token  = self::acquire_lock();

		try {
			$report = self::import_export( $export, $dry_run, $organization_slug );
		} finally {
			self::release_lock( $token );
		}

		WP_CLI::log( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		if ( $report['failed'] > 0 ) {
			WP_CLI::warning( 'Import completed with records that require review.' );
		} else {
			WP_CLI::success( $dry_run ? 'Dry run completed.' : 'Import completed.' );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function read_and_verify_export( string $file, string $organization_slug ): array {
		if ( ! is_readable( $file ) || ! is_file( $file ) ) {
			WP_CLI::error( 'The export file is not readable.' );
		}

		$size = filesize( $file );
		if ( false === $size || $size > self::MAX_FILE_SIZE ) {
			WP_CLI::error( 'The export file is empty, unreadable, or exceeds the 50 MB safety limit.' );
		}

		$raw = file_get_contents( $file );
		if ( false === $raw || '' === trim( $raw ) ) {
			WP_CLI::error( 'The export file is empty or unreadable.' );
		}

		try {
			$export = json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
			$checksum_artifact = json_decode( $raw, false, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			unset( $exception );
			WP_CLI::error( 'The export file is not valid JSON.' );
		}
		if ( ! is_array( $export ) ) {
			WP_CLI::error( 'The export root must be a JSON object.' );
		}

		if ( self::EXPORT_SCHEMA !== (int) ( $export['schemaVersion'] ?? 0 ) ) {
			WP_CLI::error( 'The export schema is not supported.' );
		}
		if ( ! isset( $export['organization'] ) || ! is_array( $export['organization'] ) || $organization_slug !== (string) ( $export['organization']['slug'] ?? '' ) ) {
			WP_CLI::error( 'The export is not scoped to the selected Chidemoon source organization.' );
		}
		if ( ! isset( $export['items'] ) || ! is_array( $export['items'] ) || ! isset( $export['skipped'] ) || ! is_array( $export['skipped'] ) ) {
			WP_CLI::error( 'The export must contain items and skipped arrays.' );
		}

		$checksum = $export['checksum'] ?? null;
		if ( ! is_string( $checksum ) || ! preg_match( '/^[a-f0-9]{64}$/i', $checksum ) ) {
			WP_CLI::error( 'The export checksum is missing or malformed.' );
		}

		$artifact_body = $checksum_artifact;
		unset( $artifact_body->checksum );
		$actual_checksum = hash( 'sha256', self::canonical_json( $artifact_body ) );
		if ( ! hash_equals( strtolower( $checksum ), $actual_checksum ) ) {
			WP_CLI::error( 'The export checksum does not match its payload.' );
		}

		return $export;
	}

	/**
	 * @param array<string, mixed> $export
	 * @return array<string, mixed>
	 */
	private static function import_export( array $export, bool $dry_run, string $organization_slug ): array {
		$report = array(
			'schemaVersion'  => self::EXPORT_SCHEMA,
			'organizationSlug'=> $organization_slug,
			'dryRun'         => $dry_run,
			'seen'           => 0,
			'created'        => 0,
			'updated'        => 0,
			'published'      => 0,
			'drafted'        => 0,
			'quarantined'    => 0,
			'imagesImported' => 0,
			'failed'         => 0,
			'records'        => array(),
		);
		$seen_keys = array();

		foreach ( $export['items'] as $index => $record ) {
			$report['seen']++;
			if ( ! is_array( $record ) ) {
				$report['failed']++;
				$report['records'][] = self::report_record( (int) $index, '', 'rejected', array( 'record_not_object' ) );
				continue;
			}

			$normalized = self::normalize_record( $record );
			$key        = $normalized['sourceKey'];
			if ( '' === $key ) {
				$report['failed']++;
				$report['records'][] = self::report_record( (int) $index, '', 'rejected', $normalized['issues'] );
				continue;
			}
			if ( isset( $seen_keys[ $key ] ) ) {
				$report['failed']++;
				$report['records'][] = self::report_record( (int) $index, $key, 'rejected', array( 'duplicate_source_key_in_export' ) );
				continue;
			}
			$seen_keys[ $key ] = true;

			$result = self::import_record( $normalized, $dry_run );
			if ( 'created' === $result['action'] ) {
				$report['created']++;
			}
			if ( 'updated' === $result['action'] ) {
				$report['updated']++;
			}
			if ( 'published' === $result['state'] ) {
				$report['published']++;
			}
			if ( 'draft' === $result['state'] ) {
				$report['drafted']++;
			}
			if ( 'quarantine' === $result['state'] ) {
				$report['quarantined']++;
			}
			if ( ! empty( $result['imageImported'] ) ) {
				$report['imagesImported']++;
			}
			if ( 'rejected' === $result['state'] ) {
				$report['failed']++;
			}

			$report['records'][] = self::report_record( (int) $index, $key, $result['state'], $result['issues'], $result['productId'] );
		}

		return $report;
	}

	/**
	 * The operator chooses the one source tenant at import time instead of
	 * guessing from a display name or accepting an arbitrary artifact tenant.
	 *
	 * @param mixed $value Candidate source organization slug.
	 */
	private static function normalize_organization_slug( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$slug = strtolower( trim( (string) $value ) );
		return preg_match( '/^[a-z0-9][a-z0-9-]{0,62}$/', $slug ) ? $slug : '';
	}

	/**
	 * @param array<string, mixed> $record
	 * @return array<string, mixed>
	 */
	private static function normalize_record( array $record ): array {
		$source_key = trim( (string) ( $record['sourceKey'] ?? '' ) );
		$issues     = array();
		if ( ! preg_match( '/^[A-Za-z0-9._:-]{1,190}$/', $source_key ) ) {
			$issues[]   = 'invalid_source_key';
			$source_key = '';
		}

		$title = self::clean_text( $record['title'] ?? '', 255 );
		if ( '' === $title ) {
			$issues[] = 'missing_title';
		}

		$merchant      = isset( $record['merchant'] ) && is_array( $record['merchant'] ) ? $record['merchant'] : array();
		$affiliate_url = esc_url_raw( (string) ( $merchant['url'] ?? '' ), array( 'http', 'https' ) );
		if ( ! Chidemoon_Core_Affiliate::is_allowed_affiliate_url( $affiliate_url ) ) {
			$issues[] = 'invalid_affiliate_url';
		}

		$category_input = isset( $record['category'] ) && is_array( $record['category'] )
			? array(
				array(
					'name' => $record['category']['label'] ?? '',
					'slug' => $record['category']['slug'] ?? '',
				),
			)
			: array();
		$categories = self::normalize_categories( $category_input );
		if ( empty( $categories ) ) {
			$issues[] = 'missing_category';
		}

		$source_url = self::valid_source_url( (string) ( $merchant['url'] ?? '' ) );
		if ( '' === $source_url ) {
			$issues[] = 'missing_or_invalid_source_url';
		}
		$source_checked_at = self::normalize_datetime( (string) ( $merchant['sourceCheckedAt'] ?? '' ) );
		if ( '' === $source_checked_at ) {
			$issues[] = 'missing_or_invalid_source_checked_at';
		}

		$image = self::normalize_image( $record );
		if ( '' !== $image['url'] && ! self::is_safe_https_image_url( $image['url'] ) ) {
			$issues[] = 'unsafe_image_url';
		}

		$review_state = self::normalize_review_state( $record['status'] ?? 'draft' );
		if ( 'quarantine' === $review_state ) {
			$issues[] = 'source_marked_quarantine';
		}

		$facts = $record['specs'] ?? array();
		if ( ! is_array( $facts ) ) {
			$issues[] = 'invalid_facts';
			$facts    = array();
		}

		$price = self::normalize_price( $record['price'] ?? null );
		if ( null === $price && array_key_exists( 'price', $record ) && null !== $record['price'] && '' !== (string) $record['price'] ) {
			$issues[] = 'invalid_price';
		}

		return array(
			'sourceKey'       => $source_key,
			'title'           => $title,
			'description'     => wp_kses_post( (string) ( $record['description'] ?? '' ) ),
			'shortDescription'=> '',
			'affiliateUrl'    => $affiliate_url,
			'merchantName'    => self::clean_text( $merchant['sellerName'] ?? $merchant['platform'] ?? '', 160 ),
			'sourceUrl'       => $source_url,
			'sourceCheckedAt' => $source_checked_at,
			'categories'      => $categories,
			'image'           => $image,
			'reviewState'     => $review_state,
			'facts'           => $facts,
			'price'           => $price,
			'currency'        => self::clean_text( $record['currency'] ?? '', 12 ),
			'issues'          => array_values( array_unique( $issues ) ),
		);
	}

	/**
	 * @param array<string, mixed> $record
	 * @return array<string, mixed>
	 */
	private static function import_record( array $record, bool $dry_run ): array {
		$existing_ids = self::find_product_ids( $record['sourceKey'] );
		if ( count( $existing_ids ) > 1 ) {
			if ( ! $dry_run ) {
				foreach ( $existing_ids as $product_id ) {
					self::quarantine_product( $product_id );
				}
			}

			return array(
				'action'        => 'updated',
				'state'         => 'quarantine',
				'issues'        => array( 'ambiguous_existing_source_key' ),
				'productId'     => 0,
				'imageImported' => false,
			);
		}

		$issues = $record['issues'];
		if ( ! empty( $issues ) || '' === $record['sourceKey'] || '' === $record['title'] || '' === $record['affiliateUrl'] || empty( $record['categories'] ) ) {
			return self::persist_quarantined_record( $record, $existing_ids, $dry_run, $issues );
		}

		if ( $dry_run ) {
			$has_existing_image = ! empty( $existing_ids ) && has_post_thumbnail( (int) $existing_ids[0] );
			$state              = 'reviewed' === $record['reviewState'] && ( $has_existing_image || '' !== $record['image']['url'] ) ? 'published' : 'draft';
			return array(
				'action'        => empty( $existing_ids ) ? 'created' : 'updated',
				'state'         => $state,
				'issues'        => '' === $record['image']['url'] && ! $has_existing_image ? array( 'missing_image' ) : array(),
				'productId'     => empty( $existing_ids ) ? 0 : (int) $existing_ids[0],
				'imageImported' => false,
			);
		}

		$product_id = self::upsert_product( $record, $existing_ids );
		if ( is_wp_error( $product_id ) ) {
			return array(
				'action'        => empty( $existing_ids ) ? 'created' : 'updated',
				'state'         => 'rejected',
				'issues'        => array( 'product_save_failed' ),
				'productId'     => 0,
				'imageImported' => false,
			);
		}

		$image_result = self::ensure_product_image( (int) $product_id, $record['image'] );
		if ( is_wp_error( $image_result ) && ! has_post_thumbnail( (int) $product_id ) ) {
			self::quarantine_product( (int) $product_id );
			return array(
				'action'        => empty( $existing_ids ) ? 'created' : 'updated',
				'state'         => 'quarantine',
				'issues'        => array( $image_result->get_error_code() ),
				'productId'     => (int) $product_id,
				'imageImported' => false,
			);
		}

		$eligible = 'reviewed' === $record['reviewState'] && has_post_thumbnail( (int) $product_id );
		self::set_product_state( (int) $product_id, $eligible ? 'reviewed' : 'draft', $eligible ? 'publish' : 'draft' );

		return array(
			'action'        => empty( $existing_ids ) ? 'created' : 'updated',
			'state'         => $eligible ? 'published' : 'draft',
			'issues'        => is_wp_error( $image_result ) ? array( $image_result->get_error_code() ) : array(),
			'productId'     => (int) $product_id,
			'imageImported' => is_int( $image_result ) && $image_result > 0,
		);
	}

	/**
	 * @param array<string, mixed> $record
	 * @param int[]                $existing_ids
	 * @param string[]             $issues
	 * @return array<string, mixed>
	 */
	private static function persist_quarantined_record( array $record, array $existing_ids, bool $dry_run, array $issues ): array {
		if ( $dry_run ) {
			return array(
				'action'        => empty( $existing_ids ) ? 'created' : 'updated',
				'state'         => 'quarantine',
				'issues'        => array_values( array_unique( $issues ) ),
				'productId'     => empty( $existing_ids ) ? 0 : (int) $existing_ids[0],
				'imageImported' => false,
			);
		}

		if ( '' === $record['sourceKey'] ) {
			return array(
				'action'        => 'rejected',
				'state'         => 'rejected',
				'issues'        => array_values( array_unique( $issues ) ),
				'productId'     => 0,
				'imageImported' => false,
			);
		}

		$product_id = self::upsert_product( $record, $existing_ids );
		if ( is_wp_error( $product_id ) ) {
			return array(
				'action'        => empty( $existing_ids ) ? 'created' : 'updated',
				'state'         => 'rejected',
				'issues'        => array_merge( $issues, array( 'product_save_failed' ) ),
				'productId'     => 0,
				'imageImported' => false,
			);
		}

		self::quarantine_product( (int) $product_id );
		return array(
			'action'        => empty( $existing_ids ) ? 'created' : 'updated',
			'state'         => 'quarantine',
			'issues'        => array_values( array_unique( $issues ) ),
			'productId'     => (int) $product_id,
			'imageImported' => false,
		);
	}

	/**
	 * @param array<string, mixed> $record
	 * @param int[]                $existing_ids
	 * @return int|WP_Error
	 */
	private static function upsert_product( array $record, array $existing_ids ) {
		$is_new  = empty( $existing_ids );
		$product = $is_new ? new WC_Product_External() : wc_get_product( (int) $existing_ids[0] );
		if ( ! $product instanceof WC_Product ) {
			return new WP_Error( 'product_load_failed' );
		}

		$product->set_name( '' !== $record['title'] ? $record['title'] : sprintf( 'Quarantined source %s', $record['sourceKey'] ) );
		$product->set_status( 'draft' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_description( $record['description'] );
		$product->set_short_description( $record['shortDescription'] );
		$product->set_manage_stock( false );
		$product->set_backorders( 'no' );
		$product->set_stock_status( 'instock' );
		if ( null !== $record['price'] ) {
			$product->set_regular_price( $record['price'] );
			$product->set_price( $record['price'] );
		}
		if ( method_exists( $product, 'set_product_url' ) ) {
			$product->set_product_url( $record['affiliateUrl'] );
		}
		if ( method_exists( $product, 'set_button_text' ) ) {
			$product->set_button_text( __( 'View offer', 'chidemoon-core' ) );
		}
		$product->update_meta_data( Chidemoon_Core_Affiliate::META_SOURCE_KEY, $record['sourceKey'] );
		$product->update_meta_data( Chidemoon_Core_Affiliate::META_AFFILIATE_URL, $record['affiliateUrl'] );
		$product->update_meta_data( Chidemoon_Core_Affiliate::META_MERCHANT_NAME, $record['merchantName'] );
		$product->update_meta_data( Chidemoon_Core_Affiliate::META_SOURCE_URL, $record['sourceUrl'] );
		$product->update_meta_data( Chidemoon_Core_Affiliate::META_SOURCE_CHECKED, $record['sourceCheckedAt'] );
		$product->update_meta_data( Chidemoon_Core_Affiliate::META_REVIEW_STATE, 'draft' );
		$product->update_meta_data( Chidemoon_Core_Affiliate::META_FACTS, wp_json_encode( $record['facts'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$product->update_meta_data( '_product_url', $record['affiliateUrl'] );
		$product->update_meta_data( '_button_text', __( 'View offer', 'chidemoon-core' ) );
		if ( '' !== $record['currency'] ) {
			$product->update_meta_data( '_chidemoon_currency', $record['currency'] );
		}

		$product_id = $product->save();
		if ( $product_id <= 0 ) {
			return new WP_Error( 'product_save_failed' );
		}

		wp_set_object_terms( $product_id, 'external', 'product_type', false );
		$category_ids = self::ensure_categories( $record['categories'] );
		if ( is_wp_error( $category_ids ) ) {
			return $category_ids;
		}
		wp_set_object_terms( $product_id, $category_ids, 'product_cat', false );

		return $product_id;
	}

	/**
	 * @param array<string, string> $image
	 * @return int|WP_Error
	 */
	private static function ensure_product_image( int $product_id, array $image ) {
		$url = $image['url'];
		if ( '' === $url ) {
			return has_post_thumbnail( $product_id ) ? 0 : new WP_Error( 'missing_image' );
		}

		$current_attachment_id = get_post_thumbnail_id( $product_id );
		if ( $current_attachment_id > 0 && hash_equals( $url, (string) get_post_meta( $current_attachment_id, '_chidemoon_import_image_url', true ) ) ) {
			return 0;
		}

		return self::download_image( $url, $product_id, $image['alt'] );
	}

	private static function download_image( string $url, int $product_id, string $alt ) {
		if ( ! self::is_safe_https_image_url( $url ) ) {
			return new WP_Error( 'unsafe_image_url' );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_IMAGE_SIZE,
				'reject_unsafe_urls'  => true,
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'image_download_failed' );
		}

		$content_length = (int) wp_remote_retrieve_header( $response, 'content-length' );
		if ( $content_length > self::MAX_IMAGE_SIZE ) {
			return new WP_Error( 'image_too_large' );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body || strlen( $body ) > self::MAX_IMAGE_SIZE ) {
			return new WP_Error( 'image_too_large' );
		}

		$content_type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
		if ( ! in_array( $content_type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return new WP_Error( 'unsupported_image_type' );
		}

		$tmp_file = wp_tempnam( $url );
		if ( ! $tmp_file || false === file_put_contents( $tmp_file, $body, LOCK_EX ) ) {
			return new WP_Error( 'image_storage_failed' );
		}

		$image_info = @getimagesize( $tmp_file );
		if ( false === $image_info || ! in_array( $image_info[2], array( IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP ), true ) ) {
			@unlink( $tmp_file );
			return new WP_Error( 'invalid_image_content' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$path     = wp_parse_url( $url, PHP_URL_PATH );
		$filename = is_string( $path ) ? sanitize_file_name( basename( $path ) ) : '';
		if ( '' === $filename ) {
			$filename = 'product-' . $product_id . '.' . self::extension_for_mime_type( $content_type );
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => $filename,
				'tmp_name' => $tmp_file,
			),
			$product_id,
			''
		);
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp_file );
			return new WP_Error( 'image_storage_failed' );
		}

		update_post_meta( $attachment_id, '_chidemoon_import_image_url', $url );
		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}
		set_post_thumbnail( $product_id, $attachment_id );

		return (int) $attachment_id;
	}

	/**
	 * @param array<int, array<string, string>> $categories
	 * @return int[]|WP_Error
	 */
	private static function ensure_categories( array $categories ) {
		$term_ids = array();
		foreach ( $categories as $category ) {
			$existing = term_exists( $category['slug'], 'product_cat' );
			if ( ! $existing ) {
				$existing = wp_insert_term( $category['name'], 'product_cat', array( 'slug' => $category['slug'] ) );
			}
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}

			$term_ids[] = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
		}

		return array_values( array_unique( array_filter( $term_ids ) ) );
	}

	private static function quarantine_product( int $product_id ): void {
		self::set_product_state( $product_id, 'quarantine', 'draft' );
	}

	private static function set_product_state( int $product_id, string $review_state, string $post_status ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$product->set_status( $post_status );
		$product->set_catalog_visibility( 'publish' === $post_status ? 'visible' : 'hidden' );
		$product->update_meta_data( Chidemoon_Core_Affiliate::META_REVIEW_STATE, $review_state );
		$product->save();
	}

	/**
	 * @return int[]
	 */
	private static function find_product_ids( string $source_key ): array {
		$ids = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'posts_per_page'         => 3,
				'meta_key'               => Chidemoon_Core_Affiliate::META_SOURCE_KEY,
				'meta_value'             => $source_key,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => true,
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * @param mixed $categories
	 * @return array<int, array<string, string>>
	 */
	private static function normalize_categories( $categories ): array {
		if ( ! is_array( $categories ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $categories as $category ) {
			if ( is_string( $category ) ) {
				$name = self::clean_text( $category, 100 );
				$slug = sanitize_title( $name );
			} elseif ( is_array( $category ) ) {
				$name = self::clean_text( $category['name'] ?? '', 100 );
				$slug = sanitize_title( (string) ( $category['slug'] ?? $name ) );
			} else {
				continue;
			}

			if ( '' !== $name && '' !== $slug ) {
				$normalized[ $slug ] = array( 'name' => $name, 'slug' => $slug );
			}
		}

		return array_values( $normalized );
	}

	/**
	 * @param array<string, mixed> $record
	 * @return array<string, string>
	 */
	private static function normalize_image( array $record ): array {
		$image = $record['imageUrl'] ?? '';
		if ( ( ! is_string( $image ) || '' === trim( $image ) ) && isset( $record['gallery'] ) && is_array( $record['gallery'] ) ) {
			$image = $record['gallery'][0] ?? '';
		}
		if ( is_string( $image ) ) {
			return array( 'url' => esc_url_raw( $image, array( 'https' ) ), 'alt' => '' );
		}
		if ( ! is_array( $image ) ) {
			return array( 'url' => '', 'alt' => '' );
		}

		return array(
			'url' => esc_url_raw( (string) ( $image['url'] ?? '' ), array( 'https' ) ),
			'alt' => self::clean_text( $image['alt'] ?? '', 250 ),
		);
	}

	private static function normalize_review_state( $state ): string {
		$state = strtolower( sanitize_key( (string) $state ) );
		if ( in_array( $state, array( 'reviewed', 'verified', 'publish', 'published' ), true ) ) {
			return 'reviewed';
		}
		if ( 'quarantine' === $state ) {
			return 'quarantine';
		}
		return 'draft';
	}

	private static function normalize_price( $price ): ?string {
		if ( null === $price || '' === (string) $price || ! is_scalar( $price ) ) {
			return null;
		}

		$value = wc_format_decimal( (string) $price );
		if ( '' === $value || (float) $value < 0 ) {
			return null;
		}

		return $value;
	}

	private static function valid_source_url( string $url ): string {
		$url   = esc_url_raw( $url, array( 'http', 'https' ) );
		$parts = wp_parse_url( $url );
		return is_array( $parts ) && ! empty( $parts['host'] ) && isset( $parts['scheme'] ) ? $url : '';
	}

	private static function normalize_datetime( string $value ): string {
		if ( '' === trim( $value ) ) {
			return '';
		}

		try {
			return ( new DateTimeImmutable( $value ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( DATE_ATOM );
		} catch ( Exception $exception ) {
			unset( $exception );
			return '';
		}
	}

	private static function clean_text( $value, int $length ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	private static function is_safe_https_image_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}
		if ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) {
			return false;
		}

		$host = strtolower( (string) $parts['host'] );
		if ( 'localhost' === $host || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.internal' ) ) {
			return false;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		if ( ! function_exists( 'dns_get_record' ) ) {
			return false;
		}
		$records = dns_get_record( $host, DNS_A | DNS_AAAA );
		if ( ! is_array( $records ) || empty( $records ) ) {
			return false;
		}

		$has_public_address = false;
		foreach ( $records as $dns_record ) {
			$address = isset( $dns_record['ip'] ) ? $dns_record['ip'] : ( $dns_record['ipv6'] ?? '' );
			if ( ! is_string( $address ) || '' === $address ) {
				continue;
			}
			if ( false === filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return false;
			}
			$has_public_address = true;
		}

		return $has_public_address;
	}

	private static function extension_for_mime_type( string $mime_type ): string {
		return array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		)[ $mime_type ] ?? 'bin';
	}

	/**
	 * @param mixed $value
	 */
	private static function canonical_json( $value ): string {
		if ( null === $value || is_bool( $value ) || is_int( $value ) || is_float( $value ) || is_string( $value ) ) {
			return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		if ( is_array( $value ) ) {
			return '[' . implode( ',', array_map( array( __CLASS__, 'canonical_json' ), $value ) ) . ']';
		}
		if ( is_object( $value ) ) {
			$properties = get_object_vars( $value );
			ksort( $properties, SORT_STRING );
			$serialized = array();
			foreach ( $properties as $key => $property ) {
				$serialized[] = wp_json_encode( (string) $key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ':' . self::canonical_json( $property );
			}
			return '{' . implode( ',', $serialized ) . '}';
		}

		return 'null';
	}

	private static function acquire_lock(): string {
		$token    = wp_generate_uuid4();
		$existing = get_option( self::LOCK_OPTION, false );
		if ( is_array( $existing ) && isset( $existing['createdAt'] ) && (int) $existing['createdAt'] < time() - self::LOCK_TIMEOUT ) {
			delete_option( self::LOCK_OPTION );
		}

		if ( ! add_option( self::LOCK_OPTION, array( 'token' => $token, 'createdAt' => time() ), '', false ) ) {
			WP_CLI::error( 'Another import is already running. Wait for it to finish or resolve a stale lock.' );
		}

		return $token;
	}

	private static function release_lock( string $token ): void {
		$existing = get_option( self::LOCK_OPTION, false );
		if ( is_array( $existing ) && isset( $existing['token'] ) && hash_equals( (string) $existing['token'], $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}

	/**
	 * @param string[] $issues
	 * @return array<string, mixed>
	 */
	private static function report_record( int $index, string $source_key, string $state, array $issues, int $product_id = 0 ): array {
		return array(
			'index'     => $index,
			'sourceKey' => $source_key,
			'productId' => $product_id,
			'state'     => $state,
			'issues'    => array_values( array_unique( $issues ) ),
		);
	}
}
