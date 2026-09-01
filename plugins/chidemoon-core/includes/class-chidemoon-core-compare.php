<?php
/**
 * Public affiliate product comparison controls and table data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Compare {
	public const MAX_PRODUCTS = 4;
	private const QUERY_VAR = 'products';
	private const SEARCH_LIMIT = 12;
	private const CATALOGUE_LIMIT = 24;

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( __CLASS__, 'append_loop_control' ), 100, 3 );
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'render_single_control' ), 25 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'offer_label' ), 100, 2 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'offer_label' ), 100, 2 );
		add_shortcode( 'chidemoon_compare_action', array( __CLASS__, 'render_shortcode' ) );
	}

	public static function register_assets(): void {
		$script_path = CHIDEMOON_CORE_DIR . 'assets/js/compare.js';
		$style_path  = CHIDEMOON_CORE_DIR . 'assets/css/compare.css';
		wp_register_script( 'chidemoon-core-compare', CHIDEMOON_CORE_URL . 'assets/js/compare.js', array(), file_exists( $script_path ) ? (string) filemtime( $script_path ) : CHIDEMOON_CORE_VERSION, true );
		wp_register_style( 'chidemoon-core-compare', CHIDEMOON_CORE_URL . 'assets/css/compare.css', array(), file_exists( $style_path ) ? (string) filemtime( $style_path ) : CHIDEMOON_CORE_VERSION );
		if ( is_front_page() || is_shop() || is_product_taxonomy() || is_product() || is_page( array( 'comparisons', 'shop-the-look' ) ) || is_page_template( array( 'page-comparisons.php', 'page-shop-the-look.php' ) ) || has_block( 'chidemoon/shop-the-look' ) ) {
			self::enqueue_assets();
		}
	}

	public static function enqueue_assets(): void {
		static $configured = false;
		if ( ! wp_script_is( 'chidemoon-core-compare', 'registered' ) ) {
			return;
		}
		wp_enqueue_script( 'chidemoon-core-compare' );
		wp_enqueue_style( 'chidemoon-core-compare' );
		if ( $configured ) {
			return;
		}
		$configured = true;
		wp_add_inline_script(
			'chidemoon-core-compare',
			'window.ChidemoonCompare=' . wp_json_encode(
				array(
					'key'       => 'chidemoon.compare.products.v1',
					'maximum'   => self::MAX_PRODUCTS,
					'compareUrl'=> self::comparison_url(),
					'restUrl'   => esc_url_raw( rest_url( 'chidemoon-core/v1/compare-products' ) ),
					'labels'    => array(
						'added'          => __( 'افزودن به مقایسه', 'chidemoon-core' ),
						'removed'        => __( 'انتخاب شده', 'chidemoon-core' ),
						'full'           => __( 'حداکثر چهار محصول را می‌توانید مقایسه کنید.', 'chidemoon-core' ),
						'compare'        => __( 'مقایسه محصولات', 'chidemoon-core' ),
						'clear'          => __( 'پاک کردن همه', 'chidemoon-core' ),
						'needMore'       => __( 'برای مقایسه حداقل دو محصول انتخاب کنید.', 'chidemoon-core' ),
						'count'          => __( 'محصول برای مقایسه', 'chidemoon-core' ),
						'loading'        => __( 'در حال جست‌وجوی محصولات…', 'chidemoon-core' ),
						'noResults'      => __( 'محصولی پیدا نشد.', 'chidemoon-core' ),
						'searchError'    => __( 'جست‌وجو در حال حاضر در دسترس نیست. دوباره تلاش کنید.', 'chidemoon-core' ),
						'sessionOnly'    => __( 'انتخاب‌ها فقط تا پایان این صفحه نگه داشته می‌شوند.', 'chidemoon-core' ),
						'staleSelection' => __( 'برخی انتخاب‌ها دیگر قابل مقایسه نیستند و حذف شدند.', 'chidemoon-core' ),
					),
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			) . ';',
			'before'
		);
	}

	public static function register_rest_routes(): void {
		register_rest_route(
			'chidemoon-core/v1',
			'/compare-products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search_products' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'search' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'browse' => array(
						'sanitize_callback' => 'absint',
					),
					'ids' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/** @return WP_REST_Response */
	public static function search_products( WP_REST_Request $request ): WP_REST_Response {
		$term       = trim( (string) $request->get_param( 'search' ) );
		$browse     = (bool) $request->get_param( 'browse' );
		$requested  = self::product_ids( (string) $request->get_param( 'ids' ) );
		if ( ! $browse && empty( $requested ) && self::string_length( $term ) < 2 ) {
			return rest_ensure_response( array() );
		}

		$candidates = ! empty( $requested )
			? array_filter( array_map( 'wc_get_product', $requested ), static fn( $product ): bool => $product instanceof WC_Product && Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) )
			: self::eligible_products( self::SEARCH_LIMIT, $browse ? '' : $term );
		$results = array();
		foreach ( $candidates as $product ) {
			$results[] = array(
				'id'    => $product->get_id(),
				'title' => wp_strip_all_tags( $product->get_name() ),
			);
		}

		return rest_ensure_response( $results );
	}

	/** @return WC_Product[] */
	public static function catalogue_products(): array {
		return self::eligible_products( self::CATALOGUE_LIMIT );
	}

	/** @return WC_Product[] */
	public static function eligible_products( int $limit, string $search = '' ): array {
		$args = array(
			'status'  => 'publish',
			'limit'   => max( $limit, 24 ),
			'orderby' => 'date',
			'order'   => 'DESC',
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$products = array();
		$offset   = 0;
		do {
			$args['offset'] = $offset;
			$page            = wc_get_products( $args );
			foreach ( $page as $product ) {
				if ( ! $product instanceof WC_Product || ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
					continue;
				}
				$products[] = $product;
				if ( count( $products ) === $limit ) {
					break 2;
				}
			}
			$offset += count( $page );
		} while ( count( $page ) === $args['limit'] );

		return $products;
	}

	public static function offer_label( string $label, WC_Product $product ): string {
		return Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ? __( 'خرید از فروشگاه', 'chidemoon-core' ) : $label;
	}

	/** @param array<string, mixed> $args */
	public static function append_loop_control( string $html, WC_Product $product, array $args ): string {
		unset( $args );
		return $html . self::control( $product );
	}

	public static function render_single_control(): void {
		$product = self::current_product();
		if ( $product instanceof WC_Product ) {
			echo self::control( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/** @param array<string, string> $attributes */
	public static function render_shortcode( array $attributes = array() ): string {
		$attributes = shortcode_atts( array( 'product_id' => (string) get_the_ID() ), $attributes, 'chidemoon_compare_action' );
		$product    = wc_get_product( absint( $attributes['product_id'] ) );
		return $product instanceof WC_Product ? self::control( $product ) : '';
	}

	public static function control( WC_Product $product ): string {
		if ( ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
			return '';
		}
		self::enqueue_assets();
		return sprintf(
			'<button type="button" class="chidemoon-compare-control" data-compare-product="%1$d" data-compare-name="%2$s" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4v16M19 4v16M9 8h6M9 16h6"/></svg><span>%3$s</span></button>',
			$product->get_id(),
			esc_attr( $product->get_name() ),
			esc_html__( 'مقایسه', 'chidemoon-core' )
		);
	}

	/** @param mixed $value @return int[] */
	public static function product_ids( $value ): array {
		$raw = is_array( $value ) ? $value : explode( ',', (string) $value );
		$ids = array();
		foreach ( $raw as $id ) {
			$id = absint( $id );
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
			if ( count( $ids ) === self::MAX_PRODUCTS ) {
				break;
			}
		}
		return $ids;
	}

	/** @return WC_Product[] */
	public static function products_from_request(): array {
		$requested = isset( $_GET[ self::QUERY_VAR ] ) ? wp_unslash( $_GET[ self::QUERY_VAR ] ) : '';
		$products  = array();
		foreach ( self::product_ids( $requested ) as $id ) {
			$product = wc_get_product( $id );
			if ( $product instanceof WC_Product && Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
				$products[] = $product;
			}
		}
		return $products;
	}

	/** @return array<string, string> */
	public static function facts( WC_Product $product ): array {
		$raw = $product->get_meta( Chidemoon_Core_Affiliate::META_FACTS, true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : array();
		if ( ! is_array( $data ) ) {
			return array();
		}
		$facts = array();
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) && isset( $value['label'], $value['value'] ) ) {
				$label = sanitize_text_field( (string) $value['label'] );
				$fact  = sanitize_text_field( (string) $value['value'] );
			} elseif ( is_string( $key ) && is_scalar( $value ) ) {
				$label = sanitize_text_field( $key );
				$fact  = sanitize_text_field( (string) $value );
			} else {
				continue;
			}
			if ( '' !== $label && '' !== $fact ) {
				$facts[ $label ] = $fact;
			}
		}
		return $facts;
	}

	/** @return string[] */
	public static function fact_labels( array $products ): array {
		$labels = array();
		foreach ( $products as $product ) {
			foreach ( self::facts( $product ) as $label => $value ) {
				$labels[ $label ] = $label;
			}
		}
		return array_values( $labels );
	}

	public static function comparison_url( array $ids = array() ): string {
		$page = get_page_by_path( 'comparisons' );
		$url  = $page instanceof WP_Post ? get_permalink( $page ) : home_url( '/comparisons/' );
		return empty( $ids ) ? $url : add_query_arg( self::QUERY_VAR, implode( ',', self::product_ids( $ids ) ), $url );
	}

	private static function current_product(): ?WC_Product {
		global $product;
		if ( $product instanceof WC_Product ) {
			return $product;
		}
		$candidate = wc_get_product( get_the_ID() );
		return $candidate instanceof WC_Product ? $candidate : null;
	}

	private static function string_length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
