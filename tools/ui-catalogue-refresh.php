<?php
/** Apply the reviewed 2026-09-29 catalogue copy changes without reimporting products. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'edit_products' ) ) {
	exit;
}

function chidemoon_ui_catalogue_canonical_json( $value ): string {
	if ( null === $value || is_bool( $value ) || is_int( $value ) || is_float( $value ) || is_string( $value ) ) {
		return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
	if ( is_array( $value ) ) {
		return '[' . implode( ',', array_map( 'chidemoon_ui_catalogue_canonical_json', $value ) ) . ']';
	}
	if ( is_object( $value ) ) {
		$properties = get_object_vars( $value );
		ksort( $properties, SORT_STRING );
		$parts = array();
		foreach ( $properties as $key => $property ) {
			$parts[] = wp_json_encode( (string) $key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ':' . chidemoon_ui_catalogue_canonical_json( $property );
		}
		return '{' . implode( ',', $parts ) . '}';
	}
	return 'null';
}

function chidemoon_ui_catalogue_read_export( string $path ): array {
	$raw = is_file( $path ) ? file_get_contents( $path ) : false;
	if ( false === $raw ) {
		WP_CLI::error( 'Catalogue export is missing or unreadable.' );
	}
	try {
		$artifact = json_decode( $raw, false, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $exception ) {
		WP_CLI::error( 'Catalogue export is invalid JSON.' );
	}
	if ( ! is_object( $artifact ) || ! isset( $artifact->checksum ) || ! is_string( $artifact->checksum ) || ! preg_match( '/^[a-f0-9]{64}$/i', $artifact->checksum ) ) {
		WP_CLI::error( 'Catalogue export checksum is missing or malformed.' );
	}
	$checksum = strtolower( $artifact->checksum );
	unset( $artifact->checksum );
	if ( ! hash_equals( $checksum, hash( 'sha256', chidemoon_ui_catalogue_canonical_json( $artifact ) ) ) ) {
		WP_CLI::error( 'Catalogue export checksum does not match its contents.' );
	}
	$required = array( 'digikala:563439', 'digikala:524436', 'basalam:25688211', 'basalam:33684609' );
	if ( 1 !== ( $artifact->schemaVersion ?? null ) || 'chidemoon' !== ( $artifact->organization->slug ?? null ) || ! isset( $artifact->items ) || ! is_array( $artifact->items ) || count( $artifact->items ) !== count( $required ) ) {
		WP_CLI::error( 'Catalogue export does not contain the expected four Chidemoon products.' );
	}
	$items = array();
	foreach ( $artifact->items as $item ) {
		$key = $item->sourceKey ?? null;
		if ( ! is_string( $key ) || ! in_array( $key, $required, true ) || isset( $items[ $key ] ) || ! is_string( $item->title ?? null ) || ! is_string( $item->description ?? null ) || ! is_string( $item->merchant->platform ?? null ) ) {
			WP_CLI::error( 'Catalogue export contains an unexpected or incomplete product.' );
		}
		$items[ $key ] = $item;
	}
	return $items;
}

function chidemoon_ui_catalogue_refresh( bool $apply, ?string $path = null ): array {
	$items = chidemoon_ui_catalogue_read_export( $path ?? __DIR__ . '/catalogue/verified-products.export.json' );
	$new_description = wp_kses_post( $items['digikala:563439']->description );
	$old_paragraph = 'این قیمت برای پیشنهاد هالی نور و رنگ صورتی در زمان بررسی ثبت شده است. موجودی و فروشندهٔ منتخب ممکن است تغییر کند؛ هزینهٔ ارسال در این مبلغ محاسبه نشده است.';
	$new_paragraph = 'این قیمت برای پیشنهاد هالی نور و رنگ صورتی در زمان بررسی ثبت شده است. تصویر اصلی چراغ قرمز را نشان می‌دهد؛ رنگ عکس با رنگ پیشنهاد قیمت‌گذاری‌شده یکی نیست. رنگ انتخابی، موجودی و فروشندهٔ منتخب ممکن است تغییر کنند؛ هزینهٔ ارسال در این مبلغ محاسبه نشده است.';
	if ( 1 !== substr_count( $new_description, $new_paragraph ) ) {
		WP_CLI::error( 'Expected product description was changed in the export; no products were updated.' );
	}
	$old_description = str_replace( $new_paragraph, $old_paragraph, $new_description );
	$report = array( 'planned' => 0, 'updated' => 0, 'unchanged' => 0, 'manual' => 0 );
	foreach ( $items as $key => $item ) {
		$ids = get_posts( array(
			'post_type' => 'product', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => 2,
			'meta_key' => '_chidemoon_source_key', 'meta_value' => $key,
			'no_found_rows' => true, 'suppress_filters' => true,
		) );
		if ( 1 !== count( $ids ) ) {
			++$report['manual'];
			WP_CLI::warning( 'MANUAL ' . $key . ': expected one product with this source key; found ' . count( $ids ) . '.' );
			continue;
		}
		$post = get_post( (int) $ids[0] );
		if ( ! $post || 'product' !== $post->post_type ) {
			++$report['manual'];
			WP_CLI::warning( 'MANUAL ' . $key . ': product post could not be read.' );
			continue;
		}

		$changes = array( 'ID' => (int) $ids[0] );
		$old_title = $item->title . ' | ' . $item->merchant->platform;
		if ( $post->post_title === $old_title ) {
			$changes['post_title'] = $item->title;
		} elseif ( $post->post_title !== $item->title ) {
			++$report['manual'];
			WP_CLI::warning( 'MANUAL ' . $key . ': title differs from the known previous and target values; editor title preserved.' );
		}

		if ( 'digikala:563439' === $key ) {
			if ( $post->post_content === $old_description ) {
				$changes['post_content'] = $new_description;
			} elseif ( $post->post_content !== $new_description ) {
				++$report['manual'];
				WP_CLI::warning( 'MANUAL ' . $key . ': description differs from the known previous and target values; editor content preserved.' );
			}
		}

		if ( 1 === count( $changes ) ) {
			++$report['unchanged'];
			WP_CLI::log( 'SKIP ' . $key . ': no eligible fields to change.' );
			continue;
		}
		$fields = implode( ', ', array_keys( array_diff_key( $changes, array( 'ID' => true ) ) ) );
		if ( ! $apply ) {
			++$report['planned'];
			WP_CLI::log( 'PLAN ' . $key . ': ' . $fields . '.' );
			continue;
		}
		$updated = wp_update_post( wp_slash( $changes ), true );
		if ( is_wp_error( $updated ) || ! $updated ) {
			WP_CLI::error( 'Could not update product ' . $key . ': ' . ( is_wp_error( $updated ) ? $updated->get_error_message() : 'unknown error' ) );
		}
		++$report['updated'];
		WP_CLI::log( 'UPDATED ' . $key . ': ' . $fields . '.' );
	}
	$summary = ( $apply ? 'Applied' : 'Dry run' ) . ': planned=' . $report['planned'] . ', updated=' . $report['updated'] . ', unchanged=' . $report['unchanged'] . ', manual conflicts=' . $report['manual'] . '.';
	if ( $report['manual'] ) {
		WP_CLI::warning( $summary );
	} else {
		WP_CLI::success( $summary );
	}
	return $report;
}

$arguments = array_values( (array) ( $args ?? array() ) );
if ( array_diff( $arguments, array( 'apply' ) ) || count( $arguments ) > 1 ) {
	WP_CLI::error( 'Usage: wp eval-file /tools/ui-catalogue-refresh.php [apply]' );
}
chidemoon_ui_catalogue_refresh( in_array( 'apply', $arguments, true ) );
