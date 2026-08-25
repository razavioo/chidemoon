<?php
/**
 * Shortcode registrations for non-Gutenberg usage.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Shortcodes {

	public static function init(): void {
		$consumer = class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled();

		add_shortcode( 'kalahamoon_product', array( __CLASS__, 'product_box' ) );
		add_shortcode( 'kalahamoon_products', array( __CLASS__, 'product_grid' ) );
		add_shortcode( 'kalahamoon_grid', array( __CLASS__, 'product_grid' ) );
		add_shortcode( 'kalahamoon_carousel', array( __CLASS__, 'product_grid' ) );
		add_shortcode( 'kalahamoon_cta', array( __CLASS__, 'product_box' ) );
		add_shortcode( 'kalahamoon_price', array( __CLASS__, 'inline_price' ) );
		add_shortcode( 'kalahamoon_compare', array( __CLASS__, 'comparison_table' ) );
		add_shortcode( 'kalahamoon_pros_cons', array( __CLASS__, 'pros_cons' ) );
		add_shortcode( 'kalahamoon_look', array( __CLASS__, 'shop_the_look' ) );

		if ( ! $consumer ) {
			add_shortcode( 'kalahamoon_favorites', array( __CLASS__, 'favorites_page' ) );
			add_shortcode( 'kalahamoon_lead_form', array( __CLASS__, 'lead_form' ) );
		}
	}

	/**
	 * Provides a portable lead form for classic-editor and imported content.
	 * The block remains the preferred authoring surface, while the shortcode
	 * keeps the same CRM submission contract for legacy WordPress pages.
	 */
	public static function lead_form( array $atts ): string {
		$atts = shortcode_atts( array(
			'heading'     => '',
			'description' => '',
			'intent'      => 'contact',
			'show_subject'=> '1',
			'show_name'   => '1',
			'show_email'  => '1',
			'show_phone'  => '1',
			'show_message'=> '1',
			'button_text' => '',
			'success_text'=> '',
			'consent_text'=> '',
			'consent_version' => '1',
		), $atts, 'kalahamoon_lead_form' );

		$intent = sanitize_key( (string) $atts['intent'] );
		$intent = in_array( $intent, array( 'contact', 'consultation', 'issue' ), true ) ? $intent : 'contact';
		$attributes = array(
			'heading'     => sanitize_text_field( $atts['heading'] ),
			'description' => sanitize_textarea_field( $atts['description'] ),
			'intent'      => $intent,
			'showSubject' => '1' === $atts['show_subject'],
			'showName'    => '1' === $atts['show_name'],
			'showEmail'   => '1' === $atts['show_email'],
			'showPhone'   => '1' === $atts['show_phone'],
			'showMessage' => '1' === $atts['show_message'],
			'buttonText'  => sanitize_text_field( $atts['button_text'] ),
			'successText' => sanitize_textarea_field( $atts['success_text'] ),
			'consentText' => sanitize_textarea_field( $atts['consent_text'] ),
			'consentVersion' => sanitize_text_field( $atts['consent_version'] ),
		);

		ob_start();
		$render = KALAHAMOON_PLUGIN_DIR . 'blocks/lead-form/render.php';
		if ( file_exists( $render ) ) {
			include $render;
		}
		return (string) ob_get_clean();
	}

	/**
	 * [kalahamoon_pros_cons id="xxx" pros="مزیت ۱|مزیت ۲" cons="عیب ۱|عیب ۲" cta="1"]
	 */
	public static function pros_cons( array $atts ): string {
		$atts = shortcode_atts( array(
			'id'      => '',
			'heading' => '',
			'pros'    => '',
			'cons'    => '',
			'cta'     => '1',
			'cta_text' => __( 'View product', 'kalahamoon' ),
		), $atts, 'kalahamoon_pros_cons' );

		$pros = array_filter( array_map( 'trim', explode( '|', $atts['pros'] ) ) );
		$cons = array_filter( array_map( 'trim', explode( '|', $atts['cons'] ) ) );

		if ( empty( $pros ) && empty( $cons ) ) {
			return '';
		}

		$product  = ! empty( $atts['id'] ) ? Kalahamoon_Product_Cache::get_for_public_render( $atts['id'] ) : null;
		$show_cta = '1' === $atts['cta'];
		$heading  = $atts['heading'];

		ob_start();
		$attributes = array(
			'productId' => $atts['id'],
			'heading'   => $heading,
			'pros'      => $atts['pros'],
			'cons'      => $atts['cons'],
			'showCta'   => $show_cta,
			'ctaText'   => $atts['cta_text'],
		);
		// Reuse block render file
		$render = KALAHAMOON_PLUGIN_DIR . 'blocks/pros-cons/render.php';
		if ( file_exists( $render ) ) {
			include $render;
		}
		return ob_get_clean();
	}

	/**
	 * [kalahamoon_look image="url" hotspots="id1:30,40;id2:60,55" caption="..."]
	 * Hotspot format: productId:x%,y%  separated by semicolons.
	 */
	public static function shop_the_look( array $atts ): string {
		$atts = shortcode_atts( array(
			'image'     => '',
			'alt'       => '',
			'hotspots'  => '',
			'caption'   => '',
			'style'     => 'hotspots',
		), $atts, 'kalahamoon_look' );

		if ( empty( $atts['image'] ) ) {
			return '';
		}

		// Parse hotspots string "id1:30,40;id2:60,55" into JSON array
		$hs_array = array();
		if ( ! empty( $atts['hotspots'] ) ) {
			foreach ( explode( ';', $atts['hotspots'] ) as $entry ) {
				$entry = trim( $entry );
				if ( empty( $entry ) ) continue;
				$parts = explode( ':', $entry, 2 );
				if ( count( $parts ) !== 2 ) continue;
				$pid   = trim( $parts[0] );
				$coords = array_map( 'trim', explode( ',', $parts[1] ) );
				if ( count( $coords ) < 2 ) continue;
				$hs_array[] = array(
					'productId' => $pid,
					'x'         => (float) $coords[0],
					'y'         => (float) $coords[1],
					'style'     => 'dot',
				);
			}
		}

		return self::render_shop_the_look( array(
			'imageUrl' => $atts['image'],
			'imageAlt' => $atts['alt'],
			'hotspots' => wp_json_encode( $hs_array ),
			'caption'  => $atts['caption'],
			'displayStyle' => $atts['style'],
		) );
	}

	public static function render_shop_the_look( array $attributes ): string {
		if ( empty( $attributes['imageUrl'] ) ) {
			return '';
		}

		wp_enqueue_style(
			'kalahamoon-shop-the-look-style',
			KALAHAMOON_PLUGIN_URL . 'blocks/shop-the-look/style.css',
			array(),
			KALAHAMOON_VERSION
		);
		wp_enqueue_script(
			'kalahamoon-shop-the-look-view',
			KALAHAMOON_PLUGIN_URL . 'blocks/shop-the-look/view.js',
			array(),
			KALAHAMOON_VERSION,
			array( 'strategy' => 'defer', 'in_footer' => true )
		);

		ob_start();
		$render = KALAHAMOON_PLUGIN_DIR . 'blocks/shop-the-look/render.php';
		if ( file_exists( $render ) ) {
			include $render;
		}
		return ob_get_clean();
	}

	/**
	 * [kalahamoon_product id="xxx" layout="vertical" show_price="1" cta_text="View product"]
	 */
	public static function product_box( array $atts ): string {
		$atts = shortcode_atts( array(
			'id'         => '',
			'layout'     => 'vertical',
			'show_price' => '1',
			'show_badge' => '1',
			'cta_text'   => __( 'View product', 'kalahamoon' ),
		), $atts, 'kalahamoon_product' );

		if ( empty( $atts['id'] ) ) {
			return '';
		}

		$product = Kalahamoon_Product_Cache::get_for_public_render( $atts['id'] );
		if ( ! $product ) {
			return Kalahamoon_Placeholder::product_not_found( (string) $atts['id'] );
		}

		return self::render_product_card( $product, $atts );
	}

	/**
	 * [kalahamoon_products category="قهوه‌ساز" limit="6" columns="3" ranked="1"]
	 */
	public static function product_grid( array $atts ): string {
		$atts = shortcode_atts( array(
			'category' => '',
			'limit'    => '6',
			'columns'  => '3',
			'ranked'   => '0',
			'ids'      => '',
			'orderby'  => 'newest',
		), $atts, 'kalahamoon_products' );

		$products = array();

		if ( ! empty( $atts['ids'] ) ) {
			$ids = array_map( 'trim', explode( ',', $atts['ids'] ) );
			foreach ( $ids as $id ) {
				$p = Kalahamoon_Product_Cache::get_for_public_render( $id );
				if ( $p ) {
					$products[] = $p;
				}
			}
		} else {
			$result   = Kalahamoon_Product_Cache::get_all( array(
				'category'     => $atts['category'],
				'limit'        => (int) $atts['limit'],
				'public_ready' => true,
			) );
			$products = $result['items'];
		}

		if ( empty( $products ) ) {
			return Kalahamoon_Placeholder::empty_state(
				__( 'محصولی برای نمایش یافت نشد.', 'kalahamoon' ),
				__( 'دسته‌بندی را بررسی کنید یا مستقیماً شناسه محصولات را وارد کنید.', 'kalahamoon' ),
				'grid'
			);
		}

		// Ranked lists are already editorially ordered — sort only when the
		// author hasn't asked for a ranking treatment.
		$ranked = '1' === $atts['ranked'];
		if ( ! $ranked ) {
			$products = self::sort_products( $products, (string) $atts['orderby'] );
		}

		$columns = max( 2, min( 4, (int) $atts['columns'] ) );
		$medals  = array( '🥇', '🥈', '🥉' );

		$html = '<div class="kalahamoon-product-grid kalahamoon-grid-cols-' . $columns . '">';

		$rank_labels = array(
			__( 'رتبه اول', 'kalahamoon' ),
			__( 'رتبه دوم', 'kalahamoon' ),
			__( 'رتبه سوم', 'kalahamoon' ),
		);

		foreach ( $products as $index => $product ) {
			$rank_badge = '';
			if ( $ranked && $index < 3 ) {
				$rank_badge = '<span class="kalahamoon-rank-badge" role="img" aria-label="'
					. esc_attr( $rank_labels[ $index ] ) . '">'
					. $medals[ $index ] . '</span>';
			}

			$html .= '<div class="kalahamoon-grid-item">';
			$html .= $rank_badge;
			$html .= self::render_product_card( $product, array(
				'layout'     => 'vertical',
				'show_price' => '1',
				'show_badge' => '1',
				'cta_text'   => __( 'View product', 'kalahamoon' ),
			) );
			$html .= '</div>';
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * [kalahamoon_price id="xxx"] — Inline price display.
	 */
	public static function inline_price( array $atts ): string {
		$atts = shortcode_atts( array( 'id' => '' ), $atts, 'kalahamoon_price' );

		if ( empty( $atts['id'] ) ) {
			return '';
		}

		$product = Kalahamoon_Product_Cache::get_for_public_render( $atts['id'] );
		if ( ! $product || $product['price'] <= 0 ) {
			return '';
		}

		return '<span class="kalahamoon-inline-price">' . esc_html( Kalahamoon_RTL::format_price( $product['price'], $product['currency'] ) ) . '</span>';
	}

	/**
	 * [kalahamoon_compare ids="id1,id2,id3" specs="جنس,وزن,قیمت"]
	 */
	public static function comparison_table( array $atts ): string {
		$atts = shortcode_atts( array(
			'ids'   => '',
			'specs' => '',
		), $atts, 'kalahamoon_compare' );

		if ( empty( $atts['ids'] ) ) {
			return '';
		}

		$ids      = array_map( 'trim', explode( ',', $atts['ids'] ) );
		$products = array();

		foreach ( $ids as $id ) {
			$p = Kalahamoon_Product_Cache::get_for_public_render( $id );
			if ( $p ) {
				$products[] = $p;
			}
		}

		if ( count( $products ) < 2 ) {
			return Kalahamoon_Placeholder::editor_hint(
				__( 'جدول مقایسه نیاز به حداقل ۲ محصول دارد', 'kalahamoon' ),
				__( 'با نگه‌داشتن Ctrl/⌘ چند محصول را در انتخابگر برگزینید.', 'kalahamoon' )
			);
		}

		$comparison_types = array_values( array_unique( array_filter( array_map( static function ( $product ) {
			$type = $product['comparisonType']['key'] ?? '';
			return is_string( $type ) ? $type : '';
		}, $products ) ) ) );
		if ( count( $comparison_types ) > 1 ) {
			return Kalahamoon_Placeholder::editor_hint(
				__( 'این محصولات از یک نوع مقایسه نیستند', 'kalahamoon' ),
				__( 'برای جدول مقایسه، محصولاتی با نوع مقایسه یکسان انتخاب کنید.', 'kalahamoon' )
			);
		}

		$spec_keys = ! empty( $atts['specs'] ) ? array_map( 'trim', explode( ',', $atts['specs'] ) ) : array();

		return self::render_comparison_table( $products, $spec_keys );
	}

	/**
	 * [kalahamoon_favorites] — Renders saved products from localStorage.
	 */
	public static function favorites_page( array $atts ): string {
		wp_enqueue_script( 'kalahamoon-favorites', KALAHAMOON_PLUGIN_URL . 'public/js/kalahamoon-favorites.js', array(), KALAHAMOON_VERSION, true );

		return '<div id="kalahamoon-favorites-container" class="kalahamoon-product-grid kalahamoon-grid-cols-3" data-kalahamoon-storage="favorites">'
			. '<p class="kalahamoon-favorites-empty">' . esc_html__( 'هنوز محصولی به لیست علاقه‌مندی‌ها اضافه نکردید.', 'kalahamoon' ) . '</p>'
			. '</div>';
	}

	// ──────────────────────────────────────────────
	// Rendering helpers
	// ──────────────────────────────────────────────

	private static function render_product_card( array $product, array $opts ): string {
		$layout      = $opts['layout'] ?? 'vertical';
		$destination = Kalahamoon_Link_Builder::resolve_product_destination( $product );
		$affiliate   = $destination['url'];
		$link_attrs  = Kalahamoon_Link_Builder::public_link_attributes( $destination );
		$platform    = strtolower( $product['platform'] ?? 'bakalahamoon' );
		$title       = (string) ( $product['title'] ?? '' );

		$html  = '<div class="kalahamoon-product-card kalahamoon-layout-' . esc_attr( $layout )
			. '" data-product-id="' . esc_attr( $product['id'] ) . '" data-track-recent="1">';

		// Image (always emitted; Kalahamoon_Placeholder handles the missing-image case)
		$html .= Kalahamoon_Placeholder::image( $product );

		$html .= '<div class="kalahamoon-product-info">';

		// Title — shown even in horizontal layout where empty cards would look broken
		if ( '' !== $title ) {
			$html .= '<h3 class="kalahamoon-product-title" title="' . esc_attr( $title ) . '">'
				. esc_html( $title ) . '</h3>';
		}

		// Price — helper emits an "unavailable" chip when price <= 0
		if ( '1' === ( $opts['show_price'] ?? '1' ) ) {
			$html .= Kalahamoon_Placeholder::price( $product, true );
		}

		// Marketplace badge
		if ( '1' === ( $opts['show_badge'] ?? '1' ) && '' !== $platform ) {
			$html .= '<span class="kalahamoon-marketplace-badge kalahamoon-badge-' . esc_attr( $platform ) . '">'
				. esc_html( self::platform_label( $platform ) )
				. '</span>';
		}

		// Skip the CTA when no public destination can safely be opened.
		if ( '' !== $affiliate && '#' !== $affiliate ) {
			$cta_text = Kalahamoon_Link_Builder::public_cta_label( $opts['cta_text'] ?? '' );
			$html .= '<a href="' . esc_url( $affiliate )
				. '" class="kalahamoon-cta-button ' . esc_attr( $link_attrs['class'] ) . '" '
				. 'target="_blank" rel="' . esc_attr( $link_attrs['rel'] ) . '" '
				. 'data-product-id="' . esc_attr( $product['id'] ) . '" '
				. 'data-link-id="' . esc_attr( $link_attrs['linkId'] ) . '" '
				. 'data-link-kind="' . esc_attr( $link_attrs['kind'] ) . '" '
				. 'data-block-type="product-card">'
				. esc_html( $cta_text )
				. '</a>';
		}

		$html .= '</div></div>';
		return $html;
	}

	private static function render_comparison_table( array $products, array $spec_keys ): string {
		$html = '<div class="kalahamoon-comparison-table-wrapper" dir="auto"><table class="kalahamoon-comparison-table">';

		// Header row with product images/titles
		$html .= '<thead><tr><th scope="col"><span class="kalahamoon-screen-reader-text">'
			. esc_html__( 'مشخصه', 'kalahamoon' ) . '</span></th>';
		foreach ( $products as $p ) {
			$html .= '<th scope="col">';
			if ( ! empty( $p['imageUrl'] ) ) {
				$html .= '<img src="' . esc_url( $p['imageUrl'] ) . '" alt="' . esc_attr( $p['title'] ) . '" loading="lazy" class="kalahamoon-compare-img" onerror="this.hidden=true;this.nextElementSibling.hidden=false" />';
				$html .= '<div class="kalahamoon-compare-img kalahamoon-compare-img--placeholder" aria-hidden="true" hidden>'
					. Kalahamoon_Placeholder::image_fallback_svg(
						strtolower( (string) ( $p['platform'] ?? '' ) ),
						(string) ( $p['title'] ?? '' )
					)
					. '</div>';
			} else {
				$html .= '<div class="kalahamoon-compare-img kalahamoon-compare-img--placeholder">'
					. Kalahamoon_Placeholder::image_fallback_svg(
						strtolower( (string) ( $p['platform'] ?? '' ) ),
						(string) ( $p['title'] ?? '' )
					)
					. '</div>';
			}
			$html .= '<span class="kalahamoon-compare-title">' . esc_html( $p['title'] ) . '</span>';
			$html .= '</th>';
		}
		$html .= '</tr></thead><tbody>';

		// Price row — missing prices render as an em-dash so columns stay aligned
		$html .= '<tr><th scope="row" class="kalahamoon-compare-label">' . esc_html__( 'قیمت', 'kalahamoon' ) . '</th>';
		$prices = array_column( $products, 'price' );
		$positive = array_filter( $prices, static fn( $p ) => (float) $p > 0 );
		$min_price = $positive ? min( $positive ) : 0;
		foreach ( $products as $p ) {
			$is_best = ( (float) $p['price'] > 0 )
				&& $min_price > 0
				&& abs( (float) $p['price'] - $min_price ) < 0.01;
			$html .= '<td' . ( $is_best ? ' class="kalahamoon-compare-winner"' : '' ) . '>';
			$html .= $p['price'] > 0
				? esc_html( Kalahamoon_RTL::format_price( $p['price'], $p['currency'] ) )
				: '<span class="kalahamoon-compare-unavailable">—</span>';
			$html .= '</td>';
		}
		$html .= '</tr>';

		// Spec rows
		$all_specs = self::collect_specs( $products, $spec_keys );
		foreach ( $all_specs as $key => $label ) {
			$html .= '<tr><th scope="row" class="kalahamoon-compare-label">' . esc_html( $label ) . '</th>';
			foreach ( $products as $p ) {
				$val = self::get_spec_value( $p, $key );
				$html .= '<td>' . ( $val !== '' ? esc_html( $val ) : '<span class="kalahamoon-compare-unavailable">—</span>' ) . '</td>';
			}
			$html .= '</tr>';
		}

		// CTA row — skip the link entirely when no clickable destination exists
		// (e.g. a marketplace product synced without a public URL).
		$html .= '<tr class="kalahamoon-compare-cta-row"><td></td>';
		foreach ( $products as $p ) {
			$destination = Kalahamoon_Link_Builder::resolve_product_destination( $p );
			$url         = $destination['url'];
			$link_attrs  = Kalahamoon_Link_Builder::public_link_attributes( $destination );
			if ( Kalahamoon_Link_Builder::is_clickable_url( $url ) ) {
				$html .= '<td><a href="' . esc_url( $url ) . '" class="kalahamoon-cta-button ' . esc_attr( $link_attrs['class'] ) . '" target="_blank" rel="' . esc_attr( $link_attrs['rel'] ) . '" data-product-id="' . esc_attr( $p['id'] ) . '" data-link-id="' . esc_attr( $link_attrs['linkId'] ) . '" data-link-kind="' . esc_attr( $link_attrs['kind'] ) . '">'
					. esc_html__( 'View product', 'kalahamoon' ) . '</a></td>';
			} else {
				$html .= '<td></td>';
			}
		}
		$html .= '</tr>';

		$html .= '</tbody></table></div>';
		return $html;
	}

	private static function collect_specs( array $products, array $filter_keys ): array {
		$all = array();
		foreach ( $products as $p ) {
			$specs = $p['specs'] ?? array();
			if ( ! is_array( $specs ) ) {
				continue;
			}
			foreach ( $specs as $group ) {
				$items = $group['items'] ?? array();
				foreach ( $items as $item ) {
					$key = self::decode_unicode_escapes( (string) ( $item['key'] ?? '' ) );
					if ( empty( $key ) ) {
						continue;
					}
					if ( ! empty( $filter_keys ) && ! in_array( $key, $filter_keys, true ) ) {
						continue;
					}
					$all[ $key ] = $key;
				}
			}
		}
		return $all;
	}

	private static function get_spec_value( array $product, string $key ): string {
		$specs = $product['specs'] ?? array();
		if ( ! is_array( $specs ) ) {
			return '';
		}
		foreach ( $specs as $group ) {
			foreach ( $group['items'] ?? array() as $item ) {
				if ( ( $item['key'] ?? '' ) === $key ) {
					return self::decode_unicode_escapes( (string) ( $item['value'] ?? '' ) );
				}
			}
		}
		return '';
	}

	/**
	 * Decode any raw \uXXXX escape sequences left in strings from old cached data
	 * (stored before JSON_UNESCAPED_UNICODE was added to wp_json_encode calls).
	 */
	private static function decode_unicode_escapes( string $s ): string {
		if ( strpos( $s, '\u' ) === false && strpos( $s, '\\u' ) === false ) {
			return $s;
		}
		$decoded = json_decode( '"' . str_replace( '"', '\\"', $s ) . '"' );
		return ( $decoded !== null && is_string( $decoded ) ) ? $decoded : $s;
	}

	/**
	 * Reorder a products array client-side (PHP). Keeps the grid API simple
	 * since Kalahamoon_Product_Cache::get_all() always returns newest-first.
	 *
	 * @param array<int,array> $products
	 * @return array<int,array>
	 */
	private static function sort_products( array $products, string $order_by ): array {
		switch ( $order_by ) {
			case 'oldest':
				return array_reverse( $products );
			case 'price_asc':
				usort( $products, static function ( $a, $b ) {
					$pa = (float) ( $a['price'] ?? 0 );
					$pb = (float) ( $b['price'] ?? 0 );
					// Push price=0 to the bottom so "contact for price" items
					// don't pollute the start of a price-sorted list.
					if ( $pa <= 0 && $pb <= 0 ) return 0;
					if ( $pa <= 0 ) return 1;
					if ( $pb <= 0 ) return -1;
					return $pa <=> $pb;
				} );
				return $products;
			case 'price_desc':
				usort( $products, static function ( $a, $b ) {
					return (float) ( $b['price'] ?? 0 ) <=> (float) ( $a['price'] ?? 0 );
				} );
				return $products;
			case 'title':
				usort( $products, static function ( $a, $b ) {
					return strcmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
				} );
				return $products;
			case 'random':
				shuffle( $products );
				return $products;
			case 'newest':
			default:
				return $products; // Cache already returns newest-first.
		}
	}

	private static function platform_label( string $platform ): string {
		// Delegates to the centralized label map (Kalahamoon_RTL).
		return Kalahamoon_RTL::platform_label( $platform );
	}
}
