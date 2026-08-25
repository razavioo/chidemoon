<?php
/**
 * Chidemoon Theme functions and definitions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'chidemoon_public_locale' ) ) {
	function chidemoon_public_locale( string $locale ): string {
		if ( is_admin() || wp_doing_ajax() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
			return $locale;
		}

		return 'fa_IR';
	}
}
add_filter( 'determine_locale', 'chidemoon_public_locale', 20 );
add_filter( 'locale', 'chidemoon_public_locale', 20 );

/**
 * Keep the public shell legible in Persian even if a stale translation binary
 * or an inherited WordPress setting is unavailable during a deployment.
 */
function chidemoon_public_copy( string $key ): string {
	$copy = array(
		'brand'              => "\u{0686}\u{06CC}\u{062F}\u{0645}\u{0648}\u{0646}",
		'home'               => "\u{0635}\u{0641}\u{062D}\u{0647} \u{0627}\u{0635}\u{0644}\u{06CC}",
		'products'           => "\u{0645}\u{062D}\u{0635}\u{0648}\u{0644}\u{0627}\u{062A}",
		'magazine'           => "\u{0645}\u{062C}\u{0644}\u{0647}",
		'compare'            => "\u{0645}\u{0642}\u{0627}\u{06CC}\u{0633}\u{0647}",
		'primary_navigation' => "\u{0646}\u{0627}\u{0648}\u{0628}\u{0631}\u{06CC} \u{0627}\u{0635}\u{0644}\u{06CC}",
		'search_products'    => "\u{062C}\u{0633}\u{062A}\u{062C}\u{0648}\u{06CC} \u{0645}\u{062D}\u{0635}\u{0648}\u{0644}\u{0627}\u{062A}",
		'search_placeholder' => "\u{062C}\u{0633}\u{062A}\u{062C}\u{0648}\u{06CC} \u{0645}\u{062D}\u{0635}\u{0648}\u{0644}\u{0627}\u{062A}...",
		'search'             => "\u{062C}\u{0633}\u{062A}\u{062C}\u{0648}",
		'open_compare'       => "\u{0628}\u{0627}\u{0632} \u{06A9}\u{0631}\u{062F}\u{0646} \u{0645}\u{0642}\u{0627}\u{06CC}\u{0633}\u{0647} \u{0645}\u{062D}\u{0635}\u{0648}\u{0644}\u{0627}\u{062A}",
		'open_menu'          => "\u{0628}\u{0627}\u{0632} \u{06A9}\u{0631}\u{062F}\u{0646} \u{0645}\u{0646}\u{0648}",
		'close_menu'         => "\u{0628}\u{0633}\u{062A}\u{0646} \u{0645}\u{0646}\u{0648}",
		'mobile_navigation'  => "\u{0646}\u{0627}\u{0648}\u{0628}\u{0631}\u{06CC} \u{0645}\u{0648}\u{0628}\u{0627}\u{06CC}\u{0644}",
		'mobile_shortcuts'   => "\u{0645}\u{06CC}\u{0627}\u{0646}\u{0628}\u{0631}\u{0647}\u{0627}\u{06CC} \u{0645}\u{0648}\u{0628}\u{0627}\u{06CC}\u{0644}",
		'skip_to_content'    => "\u{067E}\u{0631}\u{0634} \u{0628}\u{0647} \u{0645}\u{062D}\u{062A}\u{0648}\u{0627}",
	);

	return $copy[ $key ] ?? '';
}

function chidemoon_public_brand_name(): string {
	return chidemoon_public_copy( 'brand' );
}

/**
 * Keep visible dates Persian-only even when a deployment has not yet loaded
 * WordPress core's translated month names. The ISO value remains in `datetime`
 * for machines, while readers receive an unambiguous numeric date.
 */
function chidemoon_public_numerals( string $value ): string {
	return strtr(
		$value,
		array(
			'0' => "\u{06F0}",
			'1' => "\u{06F1}",
			'2' => "\u{06F2}",
			'3' => "\u{06F3}",
			'4' => "\u{06F4}",
			'5' => "\u{06F5}",
			'6' => "\u{06F6}",
			'7' => "\u{06F7}",
			'8' => "\u{06F8}",
			'9' => "\u{06F9}",
		)
	);
}

function chidemoon_public_post_date( int $post_id = 0 ): string {
	$post_id = $post_id > 0 ? $post_id : (int) get_the_ID();
	if ( $post_id <= 0 ) {
		return '';
	}

	$timestamp = get_post_timestamp( $post_id, 'date' );
	if ( ! is_int( $timestamp ) || $timestamp <= 0 ) {
		return '';
	}

	return chidemoon_public_numerals( wp_date( 'Y/m/d', $timestamp ) );
}

function chidemoon_theme_setup() {
	load_theme_textdomain( 'chidemoon-theme', get_template_directory() . '/languages' );
	$catalog = get_template_directory() . '/languages/chidemoon-theme-fa_IR.mo';
	if ( is_readable( $catalog ) ) {
		load_textdomain( 'chidemoon-theme', $catalog );
	}

	// Register navigation menu locations for header and footer blocks.
	register_nav_menus(
		array(
			'primary' => __( 'Primary Header Menu', 'chidemoon-theme' ),
			'footer'  => __( 'Footer Menu', 'chidemoon-theme' ),
		)
	);

	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_editor_style( 'assets/css/editor.css' );

	// Core injects an English skip link from its own stale translation bundle.
	// The theme supplies the equivalent Persian link at wp_body_open instead.
	remove_action( 'wp_footer', 'the_block_template_skip_link' );

}
add_action( 'after_setup_theme', 'chidemoon_theme_setup' );

function chidemoon_render_skip_link(): void {
	if ( ! is_admin() ) {
		echo '<a class="chidemoon-skip-link" href="#chidemoon-main">' . esc_html( chidemoon_public_copy( 'skip_to_content' ) ) . '</a>';
	}
}
add_action( 'wp_body_open', 'chidemoon_render_skip_link', 1 );

function chidemoon_register_editorial_content_types(): void {
	register_taxonomy(
		'chidemoon_content_type',
		array( 'post' ),
		array(
			'labels'            => array(
				'name'          => __( 'Editorial Types', 'chidemoon-theme' ),
				'singular_name' => __( 'Editorial Type', 'chidemoon-theme' ),
			),
			'public'            => false,
			'publicly_queryable' => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => false,
			'rewrite'           => false,
			'hierarchical'      => false,
		)
	);

}
add_action( 'init', 'chidemoon_register_editorial_content_types' );

function chidemoon_ensure_editorial_types(): void {
	$types = array(
		'guide'       => __( 'Buying guide', 'chidemoon-theme' ),
		'shop-look'   => __( 'Room story', 'chidemoon-theme' ),
		'review'      => __( 'Review', 'chidemoon-theme' ),
		'inspiration' => __( 'Inspiration', 'chidemoon-theme' ),
		'video'       => __( 'Video', 'chidemoon-theme' ),
	);

	foreach ( $types as $slug => $label ) {
		if ( term_exists( $slug, 'chidemoon_content_type' ) ) {
			continue;
		}

		// The public query relies on stable slugs, so create only missing types
		// and never mutate terms an editor has already named or organized.
		wp_insert_term( $label, 'chidemoon_content_type', array( 'slug' => $slug ) );
	}
}
add_action( 'init', 'chidemoon_ensure_editorial_types', 20 );

function chidemoon_register_theme_blocks(): void {
	foreach ( array( 'site-header', 'site-footer', 'editorial-index', 'recovery', 'article-context' ) as $block ) {
		$directory = get_template_directory() . '/blocks/' . $block;
		if ( file_exists( $directory . '/block.json' ) ) {
			register_block_type( $directory );
		}
	}
}
add_action( 'init', 'chidemoon_register_theme_blocks', 25 );

function chidemoon_public_catalog_count(): int {
	static $count = null;
	if ( null !== $count ) {
		return $count;
	}

	// The shell renders more than once per page. One policy count keeps every
	// affordance consistent without multiplying catalog reads.
	$count = class_exists( 'Kalahamoon_Product_Cache' )
		? max( 0, Kalahamoon_Product_Cache::public_ready_count() )
		: 0;

	return $count;
}

function chidemoon_public_catalog_available(): bool {
	return chidemoon_public_catalog_count() > 0;
}

/**
 * The generic connector verifies this stable, anonymous catalogue page before
 * acknowledging a revision. Product eligibility remains entirely upstream.
 *
 * @param list<string>        $urls
 * @param array<string,mixed> $snapshot
 * @return list<string>
 */
function chidemoon_catalog_public_render_urls( array $urls, array $snapshot ): array {
	$shop_url = esc_url_raw( home_url( '/shop/' ) );
	if ( '' !== $shop_url && ! in_array( $shop_url, $urls, true ) ) {
		$urls[] = $shop_url;
	}

	return $urls;
}
add_filter( 'kalahamoon_catalog_public_render_urls', 'chidemoon_catalog_public_render_urls', 10, 2 );

/**
 * Make the active projection visible in anonymous HTML so a consumer receipt
 * proves that public cache invalidation reached the requested revision.
 */
function chidemoon_render_catalog_revision_marker(): void {
	if ( ! class_exists( 'Kalahamoon_Catalog_Consumer' ) ) {
		return;
	}

	$revision = strtolower( trim( Kalahamoon_Catalog_Consumer::active_snapshot_revision() ) );
	if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $revision ) ) {
		return;
	}

	echo '<meta name="kalahamoon-catalog-revision" content="' . esc_attr( $revision ) . '">' . "\n";
}
add_action( 'wp_head', 'chidemoon_render_catalog_revision_marker', 1 );

/**
 * Keep the public route stable while the catalog provider owns the product
 * record. The theme only supplies a presentation URL; it does not decide
 * whether the product is eligible for publication.
 *
 * @param string               $url Existing URL supplied by another host theme.
 * @param array<string,mixed> $product Normalized catalog product.
 */
function chidemoon_product_detail_url( string $url, array $product ): string {
	$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $product['id'] ?? '' ) );
	if ( '' === $id ) {
		return $url;
	}

	return home_url( '/products/' . rawurlencode( $id ) . '/' );
}
add_filter( 'kalahamoon_product_public_url', 'chidemoon_product_detail_url', 10, 2 );

function chidemoon_register_catalog_routes(): void {
	// The catalog page is a stable presentation surface; do not let a retired
	// marketplace archive rule redirect it to the front page when the provider
	// has no live products yet.
	add_rewrite_rule( '^shop/?$', 'index.php?pagename=shop', 'top' );
	add_rewrite_rule( '^products/([A-Za-z0-9_-]+)/?$', 'index.php?chidemoon_product=$matches[1]', 'top' );
	add_rewrite_rule( '^collections/([A-Za-z0-9-]+)/?$', 'index.php?chidemoon_collection=$matches[1]', 'top' );
}
add_action( 'init', 'chidemoon_register_catalog_routes', 30 );

/** @param array<string,mixed> $query_vars @return array<string,mixed> */
function chidemoon_claim_shop_request( array $query_vars ): array {
	$request_path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
	$shop_path    = trim( (string) wp_parse_url( home_url( '/shop/' ), PHP_URL_PATH ), '/' );
	if ( '' === $shop_path || $request_path !== $shop_path ) {
		return $query_vars;
	}

	// A stale marketplace rule may already have classified this request as a
	// product archive. Replace that classification before canonical redirects
	// run so the stable WordPress page can render its intentional empty state.
	$query_vars['pagename'] = 'shop';
	unset( $query_vars['post_type'], $query_vars['name'], $query_vars['attachment'] );
	return $query_vars;
}
add_filter( 'request', 'chidemoon_claim_shop_request', 1 );

/** @param list<string> $vars @return list<string> */
function chidemoon_catalog_query_vars( array $vars ): array {
	$vars[] = 'chidemoon_product';
	$vars[] = 'chidemoon_collection';
	return $vars;
}
add_filter( 'query_vars', 'chidemoon_catalog_query_vars' );

function chidemoon_current_public_product(): ?array {
	static $resolved = false;
	static $product  = null;
	if ( $resolved ) {
		return $product;
	}
	$resolved = true;

	$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) get_query_var( 'chidemoon_product' ) );
	if ( '' === $id || ! class_exists( 'Kalahamoon_Product_Cache' ) ) {
		return null;
	}

	$candidate = Kalahamoon_Product_Cache::get_by_kalahamoon_id( $id );
	if ( ! is_array( $candidate ) ) {
		return null;
	}
	// The connector cache contains Kalahamoon's already-approved projection.
	// Re-evaluating offers here would let the consumer disagree with its source.
	$product = ! empty( $candidate['publicReady'] ) ? $candidate : null;
	return $product;
}

function chidemoon_current_public_collection(): ?WP_Term {
	$slug = sanitize_title( (string) get_query_var( 'chidemoon_collection' ) );
	if ( '' === $slug || ! taxonomy_exists( 'kalahamoon_collection' ) ) {
		return null;
	}
	$term = get_term_by( 'slug', $slug, 'kalahamoon_collection' );
	return $term instanceof WP_Term ? $term : null;
}

function chidemoon_set_catalog_route_404(): void {
	global $wp_query;
	if ( $wp_query instanceof WP_Query ) {
		$wp_query->set_404();
	}
	status_header( 404 );
	nocache_headers();
}

function chidemoon_guard_catalog_routes(): void {
	if ( '' !== (string) get_query_var( 'chidemoon_product' ) && ! chidemoon_current_public_product() ) {
		chidemoon_set_catalog_route_404();
		return;
	}

	if ( '' === (string) get_query_var( 'chidemoon_collection' ) ) {
		return;
	}
	$collection = chidemoon_current_public_collection();
	if ( ! $collection instanceof WP_Term || ! class_exists( 'Kalahamoon_Product_Cache' ) ) {
		chidemoon_set_catalog_route_404();
		return;
	}
	$products = Kalahamoon_Product_Cache::get_all(
		array(
			'collection'   => $collection->slug,
			'public_ready' => true,
			'limit'        => 1,
		)
	);
	if ( empty( $products['items'] ) ) {
		chidemoon_set_catalog_route_404();
	}
}
add_action( 'template_redirect', 'chidemoon_guard_catalog_routes', 0 );

/** @param string $template */
function chidemoon_catalog_route_template( string $template ): string {
	if ( '' !== (string) get_query_var( 'chidemoon_product' ) && ! is_404() ) {
		return get_theme_file_path( 'templates/product-detail.php' );
	}
	if ( '' !== (string) get_query_var( 'chidemoon_collection' ) && ! is_404() ) {
		return get_theme_file_path( 'templates/product-collection.php' );
	}
	return $template;
}
add_filter( 'template_include', 'chidemoon_catalog_route_template' );

function chidemoon_flush_catalog_routes(): void {
	chidemoon_register_catalog_routes();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'chidemoon_flush_catalog_routes' );

function chidemoon_public_default_document_title( array $parts ): array {
	if ( is_page( 'shop' ) ) {
		$parts['title'] = __( 'Products', 'chidemoon-theme' );
		return $parts;
	}
	$product = chidemoon_current_public_product();
	if ( is_array( $product ) ) {
		$parts['title'] = (string) $product['title'];
		return $parts;
	}
	$collection = chidemoon_current_public_collection();
	if ( $collection instanceof WP_Term ) {
		$parts['title'] = $collection->name;
		return $parts;
	}
	return $parts;
}
add_filter( 'document_title_parts', 'chidemoon_public_default_document_title' );

function chidemoon_public_shop_title( string $title, int $post_id ): string {
	if ( is_admin() || ! is_page( 'shop' ) || (int) get_queried_object_id() !== $post_id ) {
		return $title;
	}
	return __( 'Products', 'chidemoon-theme' );
}
add_filter( 'the_title', 'chidemoon_public_shop_title', 10, 2 );

function chidemoon_public_comparison_available(): bool {
	// A comparison can still reject an incompatible selection, but the shell
	// should not invite one before two verified candidates exist at all.
	return chidemoon_public_catalog_count() >= 2;
}

function chidemoon_public_editorial_available( string $content_type = '' ): bool {
	static $availability = array();
	$content_type = sanitize_key( $content_type );
	if ( array_key_exists( $content_type, $availability ) ) {
		return $availability[ $content_type ];
	}

	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 1,
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	);
	if ( '' !== $content_type ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'chidemoon_content_type',
				'field'    => 'slug',
				'terms'    => array( $content_type ),
			),
		);
	}

	$query = new WP_Query( $args );
	$availability[ $content_type ] = ! empty( $query->posts );

	return $availability[ $content_type ];
}

/**
 * Return one of the small, audited interface icons used by the theme shell.
 * Keeping the SVG paths local avoids an icon-font request and gives every
 * control the same stroke weight at desktop and mobile sizes.
 */
function chidemoon_icon( string $name, int $size = 20 ): string {
	$paths = array(
		'home'    => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10.5V20h13v-9.5"/><path d="M9.5 20v-6h5v6"/>',
		'shop'    => '<path d="M4 8h16l-1 12H5L4 8Z"/><path d="M8 8a4 4 0 0 1 8 0"/>',
		'layers'  => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/>',
		'compare' => '<path d="M7 4v13"/><path d="m3 8 4-4 4 4"/><path d="M17 20V7"/><path d="m13 16 4 4 4-4"/>',
		'book'    => '<path d="M4 5.5A3.5 3.5 0 0 1 7.5 2H11v17H7.5A3.5 3.5 0 0 0 4 22V5.5Z"/><path d="M20 5.5A3.5 3.5 0 0 0 16.5 2H13v17h3.5A3.5 3.5 0 0 1 20 22V5.5Z"/>',
		'search'  => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
		'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close'   => '<path d="m6 6 12 12M18 6 6 18"/>',
		'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>',
	);
	$path = $paths[ $name ] ?? $paths['info'];
	$size = max( 12, min( 32, $size ) );
	return '<svg class="chidemoon-icon chidemoon-icon-' . esc_attr( $name ) . '" width="' . esc_attr( (string) $size ) . '" height="' . esc_attr( (string) $size ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

/**
 * Resolve navigation through WordPress menu locations first, then fall back to
 * a bounded set of published route pages. The fallback never emits every page.
 *
 * @return list<array{slug:string,label:string,url:string,current:bool}>
 */
function chidemoon_navigation_items( string $location, bool $include_fallback = true ): array {
	$definitions = array(
		'primary' => array(
			'shop'          => chidemoon_public_copy( 'products' ),
			'magazine'      => chidemoon_public_copy( 'magazine' ),
			'compare'       => chidemoon_public_copy( 'compare' ),
		),
		'footer' => array(
			'home'          => chidemoon_public_copy( 'home' ),
			'shop'          => chidemoon_public_copy( 'products' ),
			'magazine'      => chidemoon_public_copy( 'magazine' ),
		),
		'mobile' => array(
			'home'          => chidemoon_public_copy( 'home' ),
			'shop'          => chidemoon_public_copy( 'products' ),
			'magazine'      => chidemoon_public_copy( 'magazine' ),
			'compare'       => chidemoon_public_copy( 'compare' ),
		),
	);
	$allowed = $definitions[ $location ] ?? array();
	if ( empty( $allowed ) ) {
		return array();
	}
	$items     = array();
	$locations = get_nav_menu_locations();
	$menu_id   = absint( $locations[ 'mobile' === $location ? 'primary' : $location ] ?? 0 );
	$menu      = $menu_id > 0 ? wp_get_nav_menu_items( $menu_id ) : array();
	if ( is_array( $menu ) && 'mobile' !== $location ) {
		foreach ( $menu as $item ) {
			if ( ! $item instanceof WP_Post || (int) $item->menu_item_parent > 0 ) {
				continue;
			}
			$slug = '';
			if ( 'post_type' === $item->type && 'page' === $item->object ) {
				$slug = (string) get_post_field( 'post_name', (int) $item->object_id );
			} else {
				$slug = trim( (string) wp_parse_url( (string) $item->url, PHP_URL_PATH ), '/' );
				$slug = '' === $slug ? 'home' : basename( $slug );
			}
			if ( ! isset( $allowed[ $slug ] ) ) {
				continue;
			}
			$url = 'home' === $slug
				? home_url( '/' )
				: ( 'post_type' === $item->type && 'page' === $item->object ? get_permalink( (int) $item->object_id ) : (string) $item->url );
			$items[ $slug ] = array(
				'slug'    => $slug,
				// Navigation text is theme-owned so a copied English menu label
				// cannot leak into the Persian public shell.
				'label'   => $allowed[ $slug ],
				'url'     => esc_url_raw( $url ),
				'current' => 'home' === $slug ? is_front_page() : is_page( (int) $item->object_id ),
			);
		}
	}

	if ( $include_fallback ) {
		foreach ( $allowed as $slug => $label ) {
			if ( isset( $items[ $slug ] ) ) {
				continue;
			}
			if ( 'home' === $slug ) {
				$items[ $slug ] = array( 'slug' => $slug, 'label' => $label, 'url' => home_url( '/' ), 'current' => is_front_page() );
				continue;
			}
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
				$items[ $slug ] = array( 'slug' => $slug, 'label' => $label, 'url' => get_permalink( $page ), 'current' => is_page( $page->ID ) );
			}
		}
	}

	$ordered = array();
	foreach ( array_keys( $allowed ) as $slug ) {
		if ( isset( $items[ $slug ] ) ) {
			$ordered[] = $items[ $slug ];
		}
	}
	// A stable discovery route is more useful than a disappearing navigation
	// item. Empty catalog states are rendered by their destination block.
	return array_values( $ordered );
}

function chidemoon_disable_public_discussion(): void {
	remove_post_type_support( 'post', 'comments' );
	remove_post_type_support( 'post', 'trackbacks' );
	remove_post_type_support( 'page', 'comments' );
	remove_post_type_support( 'page', 'trackbacks' );
}
add_action( 'init', 'chidemoon_disable_public_discussion', 20 );
add_filter( 'comments_open', '__return_false', 20, 2 );
add_filter( 'pings_open', '__return_false', 20, 2 );
add_filter( 'comments_array', static fn( array $comments ): array => array(), 20, 2 );
add_action( 'admin_menu', static fn() => remove_menu_page( 'edit-comments.php' ), 20 );

function chidemoon_editorial_reading_minutes( int $post_id ): int {
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return 1;
	}

	$words = preg_split( '/\s+/u', trim( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) ), -1, PREG_SPLIT_NO_EMPTY );
	return max( 1, (int) ceil( count( is_array( $words ) ? $words : array() ) / 180 ) );
}

// Chidemoon is a Persian-first public catalogue. WordPress may still be
// configured with an English site locale, so declare the public document
// direction here instead of allowing inherited LTR styles to leak through.
function chidemoon_public_language_attributes( string $output ): string {
	if ( is_admin() ) {
		return $output;
	}

	return 'lang="fa-IR" dir="rtl"';
}
add_filter( 'language_attributes', 'chidemoon_public_language_attributes', 20 );

function chidemoon_has_external_seo_provider(): bool {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

function chidemoon_meta_description(): string {
	$product = chidemoon_current_public_product();
	if ( is_array( $product ) ) {
		$description = wp_trim_words( wp_strip_all_tags( (string) ( $product['description'] ?? '' ) ), 34, '' );
		return '' !== $description ? $description : (string) ( $product['title'] ?? '' );
	}

	if ( is_singular() ) {
		$description = trim( (string) get_the_excerpt() );
		if ( '' === $description ) {
			$content     = strip_shortcodes( (string) get_post_field( 'post_content', get_queried_object_id() ) );
			$description = wp_trim_words( wp_strip_all_tags( $content ), 34, '' );
		}
		return $description;
	}

	return trim( (string) get_bloginfo( 'description' ) );
}

function chidemoon_current_canonical_url(): string {
	$product = chidemoon_current_public_product();
	if ( is_array( $product ) ) {
		return chidemoon_product_detail_url( '', $product );
	}
	$collection = chidemoon_current_public_collection();
	if ( $collection instanceof WP_Term ) {
		return home_url( '/collections/' . rawurlencode( $collection->slug ) . '/' );
	}
	if ( is_singular() ) {
		return (string) get_permalink( get_queried_object_id() );
	}
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( $paged > 1 ) {
		return (string) get_pagenum_link( $paged );
	}

	if ( is_front_page() ) {
		return home_url( '/' );
	}

	if ( is_home() ) {
		$posts_page = (int) get_option( 'page_for_posts' );
		return $posts_page > 0 ? (string) get_permalink( $posts_page ) : home_url( '/' );
	}

	if ( is_search() ) {
		return (string) get_search_link( get_search_query() );
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$term_url = get_term_link( get_queried_object() );
		if ( ! is_wp_error( $term_url ) ) {
			return (string) $term_url;
		}
	}

	if ( is_author() ) {
		$author = get_queried_object();
		if ( isset( $author->ID ) ) {
			return (string) get_author_posts_url( (int) $author->ID );
		}
	}

	if ( is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
		$archive_url = is_string( $post_type ) ? get_post_type_archive_link( $post_type ) : false;
		if ( false !== $archive_url ) {
			return (string) $archive_url;
		}
	}

	return (string) get_pagenum_link( $paged );
}

function chidemoon_document_metadata(): void {
	if ( chidemoon_has_external_seo_provider() || is_admin() ) {
		return;
	}

	$title       = wp_get_document_title();
	$description = chidemoon_meta_description();
	$product     = chidemoon_current_public_product();
	$type        = is_singular( 'post' ) ? 'article' : ( is_array( $product ) ? 'product' : 'website' );
	$url         = chidemoon_current_canonical_url();
	$image       = '';
	if ( is_array( $product ) && ! empty( $product['imageUrl'] ) ) {
		$image = (string) $product['imageUrl'];
	} elseif ( is_singular() && has_post_thumbnail() ) {
		$image_data = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
		$image      = is_array( $image_data ) ? (string) $image_data[0] : '';
	}

	if ( '' !== $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	// WordPress core owns singular canonicals. Archives and search views need an
	// explicit route-aware canonical instead of incorrectly pointing to home.
	if ( ! is_singular() ) {
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}
	echo '<meta property="og:locale" content="fa_IR">' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	if ( '' !== $description ) {
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	if ( '' !== $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="' . ( '' !== $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";

	$graph = array(
		array(
			'@type'       => 'WebSite',
			'@id'         => home_url( '/#website' ),
			'url'         => home_url( '/' ),
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'inLanguage'  => 'fa-IR',
		),
	);
	if ( is_singular( 'post' ) ) {
		$post_id   = get_queried_object_id();
		$author_id = (int) get_post_field( 'post_author', $post_id );
		$article   = array(
			'@type'            => 'Article',
			'headline'         => get_the_title( $post_id ),
			'description'      => $description,
			'datePublished'    => get_the_date( DATE_W3C, $post_id ),
			'dateModified'     => get_the_modified_date( DATE_W3C, $post_id ),
			'mainEntityOfPage' => get_permalink( $post_id ),
			'inLanguage'       => 'fa-IR',
			'author'           => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $author_id ),
			),
			'publisher'        => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
		);
		if ( '' !== $image ) {
			$article['image'] = array( $image );
		}
		$graph[] = $article;
		if ( has_term( 'video', 'chidemoon_content_type', $post_id ) ) {
			$content = (string) get_post_field( 'post_content', $post_id );
			if ( preg_match( '#https?://[^\\s"\']*(?:aparat\\.com|youtube\\.com|youtu\\.be)[^\\s"\']*#i', $content, $video_match ) ) {
				$video = array(
					'@type'        => 'VideoObject',
					'name'         => get_the_title( $post_id ),
					'description'  => $description,
					'uploadDate'   => get_the_date( DATE_W3C, $post_id ),
					'embedUrl'     => esc_url_raw( (string) $video_match[0] ),
					'inLanguage'   => 'fa-IR',
				);
				if ( '' !== $image ) {
					$video['thumbnailUrl'] = $image;
				}
				$graph[] = $video;
			}
		}
	}
	if ( is_array( $product ) ) {
		$affiliate_url = class_exists( 'Kalahamoon_Link_Builder' ) ? Kalahamoon_Link_Builder::get_product_affiliate_url( $product ) : '';
		$product_schema = array(
			'@type'       => 'Product',
			'name'        => (string) $product['title'],
			'description' => wp_trim_words( wp_strip_all_tags( (string) ( $product['description'] ?? '' ) ), 34, '' ),
			'url'         => $url,
			'inLanguage'  => 'fa-IR',
		);
		if ( '' !== $image ) {
			$product_schema['image'] = array( $image );
		}
		if ( '' !== (string) ( $product['brand'] ?? '' ) ) {
			$product_schema['brand'] = array( '@type' => 'Brand', 'name' => (string) $product['brand'] );
		}
		if ( ! empty( $product['priceVisible'] ) && (float) ( $product['price'] ?? 0 ) > 0 ) {
			$product_schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => (float) $product['price'],
				'priceCurrency' => (string) ( $product['currency'] ?? 'IRR' ),
				'availability'  => (int) ( $product['inventory'] ?? 0 ) > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'url'           => '' !== $affiliate_url ? $affiliate_url : $url,
			);
		}
		$graph[] = $product_schema;
		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array( '@type' => 'ListItem', 'position' => 1, 'name' => __( 'Home', 'chidemoon-theme' ), 'item' => home_url( '/' ) ),
				array( '@type' => 'ListItem', 'position' => 2, 'name' => __( 'Products', 'chidemoon-theme' ), 'item' => home_url( '/shop/' ) ),
				array( '@type' => 'ListItem', 'position' => 3, 'name' => (string) $product['title'], 'item' => $url ),
			),
		);
	}

	echo '<script type="application/ld+json">' . wp_json_encode(
		array( '@context' => 'https://schema.org', '@graph' => $graph ),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	) . '</script>' . "\n";
}
add_action( 'wp_head', 'chidemoon_document_metadata', 4 );

function chidemoon_parameterized_pages_are_noindex( array $robots ): array {
	$catalog_parameters = array( 'kc_search', 'kc_category', 'kc_brand', 'kc_platform', 'kc_min_price', 'kc_max_price', 'kc_sort', 'kc_page', 'products', 'topic' );
	$has_catalog_query  = (bool) array_intersect( $catalog_parameters, array_keys( $_GET ) );
	if ( is_search() || $has_catalog_query ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'chidemoon_parameterized_pages_are_noindex' );

function chidemoon_disable_plugin_public_styles( bool $enqueue ): bool {
	unset( $enqueue );
	// Chidemoon owns the public shell so the generic plugin cannot introduce a
	// second token cascade or override the RTL layout around its semantic blocks.
	return false;
}
add_filter( 'kalahamoon_enqueue_public_styles', 'chidemoon_disable_plugin_public_styles' );

function chidemoon_disable_plugin_click_tracker( bool $enqueue ): bool {
	unset( $enqueue );
	// Catalog interactions are rendered by the read-only consumer. A global
	// plugin script is unnecessary unless a block explicitly requests behavior.
	return false;
}
add_filter( 'kalahamoon_enqueue_click_tracker', 'chidemoon_disable_plugin_click_tracker' );

function chidemoon_theme_scripts(): void {
	$theme_version = (string) wp_get_theme()->get( 'Version' );

	// The theme owns type and layout so the generic catalog plugin can remain
	// visual-neutral on every consuming site.
	wp_enqueue_style( 'chidemoon-style', get_stylesheet_uri(), array(), $theme_version );

	// This script is limited to local navigation and the comparison badge.
	wp_enqueue_script(
		'chidemoon-theme-main',
		get_template_directory_uri() . '/assets/js/theme-main.js',
		array(),
		$theme_version,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'chidemoon_theme_scripts' );

function chidemoon_disable_emoji_assets() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}
add_action( 'init', 'chidemoon_disable_emoji_assets' );

function chidemoon_send_security_headers(): void {
	if ( headers_sent() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
	header( 'Cross-Origin-Opener-Policy: same-origin-allow-popups' );
}
add_action( 'send_headers', 'chidemoon_send_security_headers' );

// Chidemoon has no public account or remote-publishing surface.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'rest_endpoints', static function ( array $endpoints ): array {
	foreach ( array_keys( $endpoints ) as $route ) {
		if ( preg_match( '#^/wp/v2/users(?:/|$)#', $route ) ) {
			unset( $endpoints[ $route ] );
		}
	}
	return $endpoints;
} );

function chidemoon_register_editorial_publish_checklist(): void {
	add_meta_box(
		'chidemoon-editorial-publish-checklist',
		__( 'Before publishing', 'chidemoon-theme' ),
		'chidemoon_render_editorial_publish_checklist',
		'post',
		'side',
		'high',
		array( '__block_editor_compatible_meta_box' => true )
	);
}
add_action( 'add_meta_boxes_post', 'chidemoon_register_editorial_publish_checklist' );

function chidemoon_render_editorial_publish_checklist( WP_Post $post ): void {
	$checks = array(
		__( 'Add a featured image.', 'chidemoon-theme' ) => has_post_thumbnail( $post ),
		__( 'Add a useful excerpt.', 'chidemoon-theme' ) => '' !== trim( (string) $post->post_excerpt ),
		__( 'Choose at least one category.', 'chidemoon-theme' ) => count( wp_get_post_categories( $post->ID ) ) > 0,
	);

	echo '<p>' . esc_html__( 'Use this short checklist before you publish. It does not block WordPress publishing.', 'chidemoon-theme' ) . '</p>';
	echo '<ul class="chidemoon-editorial-publish-checklist">';
	foreach ( $checks as $label => $complete ) {
		echo '<li>' . ( $complete ? '&#10003; ' : '&#8211; ' ) . esc_html( $label ) . '</li>';
	}
	echo '</ul>';
	echo '<p><strong>' . esc_html__( 'Writing reminder', 'chidemoon-theme' ) . '</strong><br>' . esc_html__( 'Replace pattern guidance with your article copy and source details.', 'chidemoon-theme' ) . '</p>';
}
