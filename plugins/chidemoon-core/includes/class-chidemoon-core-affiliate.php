<?php
/**
 * WooCommerce affiliate-product behavior and direct click redirects.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Affiliate {
	public const META_AFFILIATE_URL  = '_chidemoon_affiliate_url';
	public const META_MERCHANT_NAME  = '_chidemoon_merchant_name';
	public const META_SOURCE_URL     = '_chidemoon_source_url';
	public const META_SOURCE_CHECKED = '_chidemoon_source_checked_at';
	public const META_DISCLOSURE     = '_chidemoon_disclosure';
	public const META_REVIEW_STATE   = '_chidemoon_review_state';
	public const META_FACTS          = '_chidemoon_product_facts';
	public const META_SOURCE_KEY     = '_chidemoon_source_key';

	private const REDIRECT_QUERY_VAR = 'chidemoon_affiliate_product';

	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_redirect_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_frontend_redirects' ), 1 );

		add_filter( 'woocommerce_product_type_selector', array( __CLASS__, 'limit_product_types' ), 100 );
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_product_data_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_data' ) );
		add_action( 'save_post_product', array( __CLASS__, 'enforce_product_invariants' ), 100, 3 );

		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'only_external_products_are_purchasable' ), 100, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'prevent_cart_additions' ), 100, 2 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'use_tracking_url_for_product_cta' ), 100, 2 );
		add_filter( 'woocommerce_widget_cart_item_visible', array( __CLASS__, 'hide_cart_widget_items' ) );
		add_shortcode( 'chidemoon_affiliate_cta', array( __CLASS__, 'render_affiliate_cta' ) );
		add_shortcode( 'chidemoon_affiliate_disclosure', array( __CLASS__, 'render_disclosure' ) );
	}

	public static function register_redirect_rewrite(): void {
		add_rewrite_rule(
			'^go/([1-9][0-9]*)/?$',
			'index.php?' . self::REDIRECT_QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * @param string[] $query_vars Existing public query vars.
	 * @return string[]
	 */
	public static function register_query_var( array $query_vars ): array {
		$query_vars[] = self::REDIRECT_QUERY_VAR;
		return $query_vars;
	}

	/**
	 * @param array<string, string> $types WooCommerce product type labels.
	 * @return array<string, string>
	 */
	public static function limit_product_types( array $types ): array {
		return array(
			'external' => __( 'External/Affiliate product', 'chidemoon-core' ),
		);
	}

	/**
	 * @param array<string, array<string, mixed>> $tabs Existing Woo product tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_product_data_tab( array $tabs ): array {
		$tabs['chidemoon'] = array(
			'label'    => __( 'Chidemoon', 'chidemoon-core' ),
			'target'   => 'chidemoon_product_data',
			'class'    => array( 'show_if_external' ),
			'priority' => 85,
		);

		return $tabs;
	}

	public static function render_product_data_panel(): void {
		global $post;

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$product_id = (int) $post->ID;
		$facts      = get_post_meta( $product_id, self::META_FACTS, true );

		echo '<div id="chidemoon_product_data" class="panel woocommerce_options_panel hidden">';
		echo '<div class="options_group">';
		woocommerce_wp_text_input(
			array(
				'id'          => self::META_AFFILIATE_URL,
				'label'       => __( 'Affiliate destination URL', 'chidemoon-core' ),
				'desc_tip'    => true,
				'description' => __( 'The approved merchant URL. Public CTAs use a local redirect only after this value passes validation.', 'chidemoon-core' ),
				'type'        => 'url',
				'value'       => self::get_affiliate_url_by_id( $product_id ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => self::META_MERCHANT_NAME,
				'label'       => __( 'Merchant name', 'chidemoon-core' ),
				'desc_tip'    => true,
				'description' => __( 'Shown next to a reviewed affiliate recommendation when the theme requests it.', 'chidemoon-core' ),
				'value'       => get_post_meta( $product_id, self::META_MERCHANT_NAME, true ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => self::META_SOURCE_URL,
				'label'       => __( 'Evidence source URL', 'chidemoon-core' ),
				'desc_tip'    => true,
				'description' => __( 'Editorial evidence only. It is never rendered as the purchase destination.', 'chidemoon-core' ),
				'type'        => 'url',
				'value'       => get_post_meta( $product_id, self::META_SOURCE_URL, true ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => self::META_SOURCE_CHECKED,
				'label'       => __( 'Source checked at', 'chidemoon-core' ),
				'desc_tip'    => true,
				'description' => __( 'Use the time an editor verified price and product facts.', 'chidemoon-core' ),
				'type'        => 'datetime-local',
				'value'       => self::format_datetime_for_input( (string) get_post_meta( $product_id, self::META_SOURCE_CHECKED, true ) ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'          => self::META_REVIEW_STATE,
				'label'       => __( 'Editorial review state', 'chidemoon-core' ),
				'desc_tip'    => true,
				'description' => __( 'Only reviewed products should be published.', 'chidemoon-core' ),
				'options'     => array(
					'draft'      => __( 'Draft', 'chidemoon-core' ),
					'reviewed'   => __( 'Reviewed', 'chidemoon-core' ),
					'quarantine' => __( 'Quarantine', 'chidemoon-core' ),
				),
				'value'       => (string) get_post_meta( $product_id, self::META_REVIEW_STATE, true ),
			)
		);
		woocommerce_wp_textarea_input(
			array(
				'id'          => self::META_DISCLOSURE,
				'label'       => __( 'Product-specific disclosure', 'chidemoon-core' ),
				'desc_tip'    => true,
				'description' => __( 'Optional copy that overrides the site-wide affiliate disclosure for this product.', 'chidemoon-core' ),
				'value'       => get_post_meta( $product_id, self::META_DISCLOSURE, true ),
			)
		);
		woocommerce_wp_textarea_input(
			array(
				'id'          => self::META_FACTS,
				'label'       => __( 'Structured product facts (JSON)', 'chidemoon-core' ),
				'desc_tip'    => true,
				'description' => __( 'Store reviewed, factual attributes only. Invalid JSON is rejected.', 'chidemoon-core' ),
				'value'       => is_string( $facts ) ? $facts : '',
			)
		);
		echo '</div>';
		echo '</div>';
	}

	/**
	 * @param WC_Product $product Product currently saved by WooCommerce.
	 */
	public static function save_product_data( WC_Product $product ): void {
		if ( ! current_user_can( 'chidemoon_manage_affiliate' ) ) {
			return;
		}

		$product_id = $product->get_id();
		$url        = self::request_string( self::META_AFFILIATE_URL );
		$source_url = self::request_string( self::META_SOURCE_URL );

		if ( '' !== $url && ! self::is_allowed_affiliate_url( $url ) ) {
			self::add_admin_error( __( 'Affiliate destination URL must be a public HTTP or HTTPS URL.', 'chidemoon-core' ) );
			$url = '';
		}

		if ( '' !== $source_url && ! self::is_allowed_source_url( $source_url ) ) {
			self::add_admin_error( __( 'Evidence source URL must be an HTTP or HTTPS URL.', 'chidemoon-core' ) );
			$source_url = '';
		}

		$product->update_meta_data( self::META_AFFILIATE_URL, $url );
		$product->update_meta_data( self::META_MERCHANT_NAME, self::request_string( self::META_MERCHANT_NAME, 160 ) );
		$product->update_meta_data( self::META_SOURCE_URL, $source_url );
		$product->update_meta_data( self::META_SOURCE_CHECKED, self::normalize_datetime( self::request_string( self::META_SOURCE_CHECKED, 32 ) ) );
		$product->update_meta_data( self::META_REVIEW_STATE, self::review_state( self::request_string( self::META_REVIEW_STATE, 20 ) ) );
		$product->update_meta_data( self::META_DISCLOSURE, self::request_string( self::META_DISCLOSURE, 1000 ) );

		$facts = self::request_raw_string( self::META_FACTS, 20000 );
		if ( '' === $facts ) {
			$product->delete_meta_data( self::META_FACTS );
		} else {
			$decoded_facts = json_decode( $facts, true, 32 );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded_facts ) ) {
				self::add_admin_error( __( 'Structured product facts must be a JSON object or array.', 'chidemoon-core' ) );
			} else {
				$product->update_meta_data( self::META_FACTS, wp_json_encode( $decoded_facts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			}
		}

		// WooCommerce keeps this native field for interoperability; Core keeps the
		// equivalent reviewed source metadata so no theme depends on a vendor API.
		$product->update_meta_data( '_product_url', $url );
		$product->update_meta_data( '_button_text', __( 'View offer', 'chidemoon-core' ) );
		$product->set_manage_stock( false );
		$product->set_backorders( 'no' );
		$product->set_stock_status( 'instock' );

		if ( $product_id > 0 ) {
			self::enforce_product_invariants( $product_id, get_post( $product_id ), true );
		}
	}

	/**
	 * @param int     $post_id Product post ID.
	 * @param WP_Post $post    Product post object.
	 * @param bool    $update  Whether this is an update.
	 */
	public static function enforce_product_invariants( int $post_id, WP_Post $post, bool $update ): void {
		unset( $update );

		static $is_enforcing = false;
		if ( $is_enforcing || 'product' !== $post->post_type || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$is_enforcing = true;
		try {
			wp_set_object_terms( $post_id, 'external', 'product_type', false );
			$product = wc_get_product( $post_id );
			if ( ! $product instanceof WC_Product ) {
				return;
			}

			$changed = false;
			if ( $product->get_manage_stock() ) {
				$product->set_manage_stock( false );
				$changed = true;
			}
			if ( 'no' !== $product->get_backorders() ) {
				$product->set_backorders( 'no' );
				$changed = true;
			}
			if ( $changed ) {
				$product->save();
			}
		} finally {
			$is_enforcing = false;
		}
	}

	/**
	 * @param bool       $purchasable Current product purchase state.
	 * @param WC_Product $product     Current product.
	 */
	public static function only_external_products_are_purchasable( bool $purchasable, WC_Product $product ): bool {
		if ( ! $product->is_type( 'external' ) ) {
			return false;
		}

		return $purchasable && '' !== self::get_affiliate_url( $product );
	}

	/**
	 * @param bool $passed Existing validation state.
	 * @return bool
	 */
	public static function prevent_cart_additions( bool $passed ): bool {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Chidemoon links directly to approved merchants and does not use a shopping cart.', 'chidemoon-core' ), 'error' );
		}

		return false;
	}

	/**
	 * @param string     $url     Native WooCommerce external-product URL.
	 * @param WC_Product $product Current product.
	 */
	public static function use_tracking_url_for_product_cta( string $url, WC_Product $product ): string {
		if ( ! $product->is_type( 'external' ) || '' === self::get_affiliate_url( $product ) ) {
			return $url;
		}

		return self::tracking_url( $product->get_id() );
	}

	public static function handle_frontend_redirects(): void {
		if ( is_admin() || wp_doing_ajax() || ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) ) {
			return;
		}

		$product_id = absint( get_query_var( self::REDIRECT_QUERY_VAR ) );
		if ( $product_id > 0 ) {
			self::redirect_to_affiliate_offer( $product_id );
		}

		$is_commerce_page = ( function_exists( 'is_cart' ) && is_cart() )
			|| ( function_exists( 'is_checkout' ) && is_checkout() )
			|| ( function_exists( 'is_account_page' ) && is_account_page() );
		if ( $is_commerce_page ) {
			$destination = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
			wp_safe_redirect( $destination, 302, 'Chidemoon Core' );
			exit;
		}
	}

	/**
	 * The cart cannot represent an affiliate purchase, so it remains empty even
	 * when a third-party theme renders its mini-cart component.
	 */
	public static function hide_cart_widget_items( bool $visible ): bool {
		unset( $visible );
		return false;
	}

	/**
	 * @param array<string, string> $attributes Shortcode attributes.
	 */
	public static function render_affiliate_cta( array $attributes = array() ): string {
		$attributes = shortcode_atts(
			array(
				'product_id' => (string) get_the_ID(),
				'label'      => __( 'View offer', 'chidemoon-core' ),
			),
			$attributes,
			'chidemoon_affiliate_cta'
		);

		$product_id = absint( $attributes['product_id'] );
		$product    = $product_id > 0 ? wc_get_product( $product_id ) : false;
		if ( ! $product instanceof WC_Product || '' === self::get_affiliate_url( $product ) ) {
			return '';
		}

		return sprintf(
			'<a class="chidemoon-affiliate-cta" href="%1$s" target="_blank" rel="nofollow sponsored noopener">%2$s</a>',
			esc_url( self::tracking_url( $product_id ) ),
			esc_html( $attributes['label'] )
		);
	}

	/**
	 * @param array<string, string> $attributes Shortcode attributes.
	 */
	public static function render_disclosure( array $attributes = array() ): string {
		$attributes = shortcode_atts(
			array( 'product_id' => (string) get_the_ID() ),
			$attributes,
			'chidemoon_affiliate_disclosure'
		);
		$product_id = absint( $attributes['product_id'] );
		$disclosure = $product_id > 0 ? trim( (string) get_post_meta( $product_id, self::META_DISCLOSURE, true ) ) : '';
		if ( '' === $disclosure ) {
			$disclosure = trim( (string) get_option( 'chidemoon_core_disclosure_text', '' ) );
		}
		if ( '' === $disclosure ) {
			return '';
		}

		return '<p class="chidemoon-affiliate-disclosure">' . esc_html( $disclosure ) . '</p>';
	}

	public static function tracking_url( int $product_id ): string {
		return home_url( '/go/' . $product_id . '/' );
	}

	public static function get_affiliate_url_by_id( int $product_id ): string {
		$product = wc_get_product( $product_id );
		return $product instanceof WC_Product ? self::get_affiliate_url( $product ) : '';
	}

	public static function get_affiliate_url( WC_Product $product ): string {
		$url = (string) $product->get_meta( self::META_AFFILIATE_URL, true );
		if ( '' === $url ) {
			$url = (string) $product->get_meta( '_product_url', true );
		}

		return self::is_allowed_affiliate_url( $url ) ? $url : '';
	}

	public static function is_allowed_affiliate_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			return false;
		}
		if ( ! in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}

		$host = strtolower( (string) $parts['host'] );
		if ( 'localhost' === $host || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.internal' ) ) {
			return false;
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		return (bool) esc_url_raw( $url, array( 'http', 'https' ) );
	}

	private static function redirect_to_affiliate_offer( int $product_id ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product || 'publish' !== get_post_status( $product_id ) || ! $product->is_type( 'external' ) ) {
			self::render_not_found();
			return;
		}

		$url = self::get_affiliate_url( $product );
		if ( '' === $url ) {
			self::render_not_found();
			return;
		}

		self::log_click( $product_id, $url );
		nocache_headers();
		wp_redirect( $url, 302, 'Chidemoon Core' );
		exit;
	}

	private static function render_not_found(): void {
		global $wp_query;

		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();
		$template = get_404_template();
		if ( $template ) {
			include $template;
		}
		exit;
	}

	private static function log_click( int $product_id, string $url ): void {
		if ( '1' === (string) ( $_SERVER['HTTP_DNT'] ?? '' ) ) {
			return;
		}

		global $wpdb;
		$parts         = wp_parse_url( $url );
		$referrer      = wp_get_referer();
		$referrer_host = $referrer ? wp_parse_url( $referrer, PHP_URL_HOST ) : null;
		$remote_addr   = sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$visitor_hash  = '' !== $remote_addr
			? hash_hmac( 'sha256', $remote_addr . '|' . gmdate( 'Y-m-d' ), wp_salt( 'nonce' ) )
			: null;

		$wpdb->insert(
			$wpdb->prefix . 'chidemoon_clicks',
			array(
				'product_id'    => $product_id,
				'merchant_host' => sanitize_text_field( (string) ( $parts['host'] ?? '' ) ),
				'visitor_hash'  => $visitor_hash,
				'referrer_host' => is_string( $referrer_host ) ? sanitize_text_field( $referrer_host ) : null,
				'clicked_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	private static function request_string( string $key, int $max_length = 2000 ): string {
		$value = self::request_raw_string( $key, $max_length );
		return sanitize_text_field( $value );
	}

	private static function request_raw_string( string $key, int $max_length ): string {
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return '';
		}

		$value = trim( (string) wp_unslash( $_POST[ $key ] ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
	}

	private static function normalize_datetime( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		try {
			$datetime = new DateTimeImmutable( $value, wp_timezone() );
			return $datetime->setTimezone( new DateTimeZone( 'UTC' ) )->format( DATE_ATOM );
		} catch ( Exception $exception ) {
			unset( $exception );
			return '';
		}
	}

	private static function format_datetime_for_input( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		try {
			return ( new DateTimeImmutable( $value ) )->setTimezone( wp_timezone() )->format( 'Y-m-d\\TH:i' );
		} catch ( Exception $exception ) {
			unset( $exception );
			return '';
		}
	}

	private static function review_state( string $state ): string {
		return in_array( $state, array( 'draft', 'reviewed', 'quarantine' ), true ) ? $state : 'draft';
	}

	private static function is_allowed_source_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		return is_array( $parts )
			&& ! empty( $parts['host'] )
			&& isset( $parts['scheme'] )
			&& in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true )
			&& (bool) esc_url_raw( $url, array( 'http', 'https' ) );
	}

	private static function add_admin_error( string $message ): void {
		if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
			WC_Admin_Meta_Boxes::add_error( $message );
		}
	}
}
