<?php
/**
 * Local public forms with deliberately narrow REST surfaces.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Chidemoon_Core_Forms {
	private const REST_NAMESPACE = 'chidemoon-core/v1';
	private const RATE_WINDOW    = 900;

	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'chidemoon_lead_form', array( __CLASS__, 'render_lead_form' ) );
		add_shortcode( 'chidemoon_price_alert_form', array( __CLASS__, 'render_price_alert_form' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/leads',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'submit_lead' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/price-alerts',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'submit_price_alert' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function register_assets(): void {
		wp_register_script(
			'chidemoon-core-forms',
			CHIDEMOON_CORE_URL . 'assets/js/forms.js',
			array(),
			CHIDEMOON_CORE_VERSION,
			true
		);
	}

	/**
	 * @param WP_REST_Request $request Incoming public form request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function submit_lead( WP_REST_Request $request ) {
		$data = self::request_data( $request );
		$rate = self::enforce_rate_limit( 'lead' );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}
		if ( ! self::is_human_submission( $data ) ) {
			return new WP_Error( 'invalid_submission', __( 'Unable to accept this submission.', 'chidemoon-core' ), array( 'status' => 400 ) );
		}

		$email = sanitize_email( (string) ( $data['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Enter a valid email address.', 'chidemoon-core' ), array( 'status' => 422 ) );
		}
		if ( ! self::has_consent( $data ) ) {
			return new WP_Error( 'consent_required', __( 'Consent is required before submitting this form.', 'chidemoon-core' ), array( 'status' => 422 ) );
		}

		$name    = self::clean_text( $data['name'] ?? '', 160 );
		$message = self::clean_textarea( $data['message'] ?? '', 4000 );
		$intent  = self::lead_intent( $data['intent'] ?? 'contact' );
		if ( '' === $message ) {
			return new WP_Error( 'message_required', __( 'Add a message before submitting this form.', 'chidemoon-core' ), array( 'status' => 422 ) );
		}

		global $wpdb;
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'chidemoon_leads',
			array(
				'email'           => $email,
				'name'            => $name,
				'message'         => $message,
				'intent'          => $intent,
				'consent_version' => self::consent_version(),
				'request_hash'    => self::request_fingerprint( 'lead' ),
				'status'          => 'new',
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			return new WP_Error( 'lead_storage_failed', __( 'The form could not be saved. Please try again later.', 'chidemoon-core' ), array( 'status' => 503 ) );
		}

		return new WP_REST_Response( array( 'status' => 'received' ), 201 );
	}

	/**
	 * @param WP_REST_Request $request Incoming public price-alert request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function submit_price_alert( WP_REST_Request $request ) {
		$data = self::request_data( $request );
		$rate = self::enforce_rate_limit( 'price-alert' );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}
		if ( ! self::is_human_submission( $data ) ) {
			return new WP_Error( 'invalid_submission', __( 'Unable to accept this submission.', 'chidemoon-core' ), array( 'status' => 400 ) );
		}

		$email      = sanitize_email( (string) ( $data['email'] ?? '' ) );
		$product_id = absint( $data['productId'] ?? 0 );
		$price      = self::normalize_target_price( $data['targetPrice'] ?? null );
		$product    = $product_id > 0 ? wc_get_product( $product_id ) : false;

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Enter a valid email address.', 'chidemoon-core' ), array( 'status' => 422 ) );
		}
		if ( ! $product instanceof WC_Product || 'publish' !== get_post_status( $product_id ) || '' === Chidemoon_Core_Affiliate::get_affiliate_url( $product ) ) {
			return new WP_Error( 'invalid_product', __( 'This product is not available for alerts.', 'chidemoon-core' ), array( 'status' => 422 ) );
		}
		if ( null === $price ) {
			return new WP_Error( 'invalid_target_price', __( 'Enter a valid target price.', 'chidemoon-core' ), array( 'status' => 422 ) );
		}
		if ( ! self::has_consent( $data ) ) {
			return new WP_Error( 'consent_required', __( 'Consent is required before submitting this form.', 'chidemoon-core' ), array( 'status' => 422 ) );
		}

		$subscription_key = hash_hmac( 'sha256', strtolower( $email ) . '|' . $product_id, wp_salt( 'auth' ) );
		$now              = current_time( 'mysql', true );
		global $wpdb;
		$table = $wpdb->prefix . 'chidemoon_price_alerts';
		$query = $wpdb->prepare(
			"INSERT INTO {$table} (email, product_id, target_price, subscription_key, consent_version, status, created_at, updated_at)
			VALUES (%s, %d, %s, %s, %s, 'pending', %s, %s)
			ON DUPLICATE KEY UPDATE target_price = VALUES(target_price), consent_version = VALUES(consent_version), status = 'pending', updated_at = VALUES(updated_at)",
			$email,
			$product_id,
			$price,
			$subscription_key,
			self::consent_version(),
			$now,
			$now
		);
		$result = $wpdb->query( $query );
		if ( false === $result ) {
			return new WP_Error( 'alert_storage_failed', __( 'The alert could not be saved. Please try again later.', 'chidemoon-core' ), array( 'status' => 503 ) );
		}

		return new WP_REST_Response( array( 'status' => 'pending' ), 201 );
	}

	/**
	 * @param array<string, string> $attributes Shortcode attributes.
	 */
	public static function render_lead_form( array $attributes = array() ): string {
		$attributes = shortcode_atts(
			array(
				'intent' => 'contact',
				'title'  => __( 'Contact Chidemoon', 'chidemoon-core' ),
			),
			$attributes,
			'chidemoon_lead_form'
		);
		self::enqueue_form_script();

		return sprintf(
			'<form class="chidemoon-public-form" data-chidemoon-form="lead" action="%1$s" method="post" novalidate>
				<h2>%2$s</h2>
				<label>Name <input name="name" type="text" maxlength="160" autocomplete="name"></label>
				<label>Email <input name="email" type="email" maxlength="320" autocomplete="email" required></label>
				<label>Message <textarea name="message" maxlength="4000" required></textarea></label>
				<input name="intent" type="hidden" value="%3$s">
				<label class="chidemoon-honeypot" aria-hidden="true">Website <input name="website" type="text" tabindex="-1" autocomplete="off"></label>
				<label><input name="consent" type="checkbox" value="1" required> I consent to Chidemoon storing this request.</label>
				<button type="submit">Send request</button><p class="chidemoon-form-status" aria-live="polite"></p>
			</form>',
			esc_url( rest_url( self::REST_NAMESPACE . '/leads' ) ),
			esc_html( $attributes['title'] ),
			esc_attr( self::lead_intent( $attributes['intent'] ) )
		);
	}

	/**
	 * @param array<string, string> $attributes Shortcode attributes.
	 */
	public static function render_price_alert_form( array $attributes = array() ): string {
		$attributes = shortcode_atts(
			array(
				'product_id' => (string) get_the_ID(),
				'title'      => __( 'Price alert', 'chidemoon-core' ),
			),
			$attributes,
			'chidemoon_price_alert_form'
		);
		$product_id = absint( $attributes['product_id'] );
		$product    = $product_id > 0 ? wc_get_product( $product_id ) : false;
		if ( ! $product instanceof WC_Product || '' === Chidemoon_Core_Affiliate::get_affiliate_url( $product ) ) {
			return '';
		}

		self::enqueue_form_script();
		return sprintf(
			'<form class="chidemoon-public-form" data-chidemoon-form="price-alert" action="%1$s" method="post" novalidate>
				<h2>%2$s</h2>
				<label>Email <input name="email" type="email" maxlength="320" autocomplete="email" required></label>
				<label>Target price <input name="targetPrice" type="number" min="0" step="0.01" required></label>
				<input name="productId" type="hidden" value="%3$d">
				<label class="chidemoon-honeypot" aria-hidden="true">Website <input name="website" type="text" tabindex="-1" autocomplete="off"></label>
				<label><input name="consent" type="checkbox" value="1" required> I consent to Chidemoon storing this alert request.</label>
				<button type="submit">Create alert</button><p class="chidemoon-form-status" aria-live="polite"></p>
			</form>',
			esc_url( rest_url( self::REST_NAMESPACE . '/price-alerts' ) ),
			esc_html( $attributes['title'] ),
			$product_id
		);
	}

	private static function enqueue_form_script(): void {
		wp_enqueue_script( 'chidemoon-core-forms' );
		wp_add_inline_script(
			'chidemoon-core-forms',
			'window.ChidemoonCoreForms = ' . wp_json_encode(
				array(
					'leadEndpoint'       => rest_url( self::REST_NAMESPACE . '/leads' ),
					'priceAlertEndpoint' => rest_url( self::REST_NAMESPACE . '/price-alerts' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function request_data( WP_REST_Request $request ): array {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) || empty( $data ) ) {
			$data = $request->get_params();
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function is_human_submission( array $data ): bool {
		return '' === trim( (string) ( $data['website'] ?? '' ) );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function has_consent( array $data ): bool {
		return in_array( $data['consent'] ?? null, array( true, 1, '1', 'true', 'on' ), true );
	}

	/**
	 * @return true|WP_Error
	 */
	private static function enforce_rate_limit( string $scope ) {
		$limit = min( 20, max( 1, absint( get_option( 'chidemoon_core_form_rate_limit', 5 ) ) ) );
		$key   = 'chidemoon_core_form_' . hash_hmac( 'sha256', $scope . '|' . self::request_fingerprint( $scope ), wp_salt( 'nonce' ) );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return new WP_Error( 'rate_limited', __( 'Too many requests. Please try again later.', 'chidemoon-core' ), array( 'status' => 429 ) );
		}

		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return true;
	}

	private static function request_fingerprint( string $scope ): string {
		$remote_addr = sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$user_agent  = sanitize_text_field( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
		return hash_hmac( 'sha256', $scope . '|' . $remote_addr . '|' . $user_agent, wp_salt( 'auth' ) );
	}

	private static function consent_version(): string {
		return substr( sanitize_text_field( (string) get_option( 'chidemoon_core_form_consent_version', '1' ) ), 0, 40 );
	}

	private static function lead_intent( $value ): string {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'contact', 'consultation', 'issue' ), true ) ? $value : 'contact';
	}

	private static function normalize_target_price( $value ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		$price = wc_format_decimal( (string) $value );
		return '' !== $price && (float) $price >= 0 ? $price : null;
	}

	private static function clean_text( $value, int $length ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	private static function clean_textarea( $value, int $length ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = sanitize_textarea_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
