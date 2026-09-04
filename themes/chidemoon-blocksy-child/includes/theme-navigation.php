<?php
/**
 * Chidemoon Blocksy child theme module.
 *
 * Loaded by functions.php; do not load directly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Curated header fallback. WordPress and Blocksy fall back to listing every
 * published page when no nav menu is assigned, which would surface cart,
 * checkout, account, and showcase pages in the public header. Only the
 * editorial entry points belong there.
 *
 * @return int[]
 */
function chidemoon_header_page_ids(): array {
	$ids = array();
	foreach ( array( 'shop', 'magazine', 'guides', 'comparisons', 'shop-the-look' ) as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
			$ids[] = (int) $page->ID;
		}
	}
	return $ids;
}

/**
 * Wrap the theme's page-list fallback so unassigned menu locations only
 * surface the curated Chidemoon entry points.
 *
 * @param array $args wp_nav_menu arguments.
 */
function chidemoon_nav_menu_fallback( array $args = array() ): void {
	$keep = chidemoon_header_page_ids();
	if ( empty( $keep ) ) {
		return;
	}

	$all_pages = get_posts(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	$hide      = array_values( array_diff( $all_pages, $keep ) );

	add_filter(
		'wp_list_pages_excludes',
		static function ( array $excludes ) use ( $hide ): array {
			return array_values( array_unique( array_merge( $excludes, $hide ) ) );
		}
	);

	$original = $args['chidemoon_original_fallback'] ?? '';
	if ( is_callable( $original ) ) {
		call_user_func( $original, $args );
		return;
	}

	wp_page_menu( array( 'echo' => true ) );
}

add_filter(
	'wp_nav_menu_args',
	static function ( array $args ): array {
		$has_menu = ! empty( $args['menu'] ) && (bool) wp_get_nav_menu_object( $args['menu'] );

		if ( ! $has_menu && ! empty( $args['theme_location'] ) && has_nav_menu( $args['theme_location'] ) ) {
			$has_menu = true;
		}

		if ( $has_menu ) {
			return $args;
		}

		$args['chidemoon_original_fallback'] = $args['fallback_cb'] ?? '';
		$args['fallback_cb']                 = 'chidemoon_nav_menu_fallback';

		return $args;
	}
);
/**
 * Section shortcuts shown under the search field in the header modal and on
 * empty search results, so a stalled query always has a next step.
 */
function chidemoon_search_quick_links(): void {
	$links = array(
		'فروشگاه'       => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : chidemoon_blocksy_page_url( 'shop' ),
		'مجله'          => chidemoon_blocksy_page_url( 'magazine' ),
		'راهنمای خرید'  => chidemoon_blocksy_page_url( 'guides' ),
		'مقایسه‌ها'     => chidemoon_blocksy_page_url( 'comparisons' ),
	);

	echo '<nav class="chidemoon-search-form__links" aria-label="' . esc_attr__( 'بخش‌های پیشنهادی', 'chidemoon-blocksy-child' ) . '">';
	echo '<span class="chidemoon-search-form__links-label">' . esc_html__( 'می‌توانید از اینجا شروع کنید:', 'chidemoon-blocksy-child' ) . '</span>';
	foreach ( $links as $label => $url ) {
		if ( is_string( $url ) && '' !== $url ) {
			printf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
		}
	}
	echo '</nav>';
}

/**
 * The header builder keeps the search element on the desktop row, so mobile
 * visitors get their search entry at the top of the off-canvas menu.
 */
add_action(
	'blocksy:header:offcanvas:mobile:top',
	static function (): void {
		echo '<div class="chidemoon-offcanvas-search">';
		get_search_form();
		echo '</div>';
	}
);
