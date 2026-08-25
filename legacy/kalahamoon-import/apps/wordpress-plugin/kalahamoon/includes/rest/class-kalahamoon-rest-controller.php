<?php
/**
 * Plugin REST API endpoints.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_REST_Controller {

	const NAMESPACE = 'kalahamoon/v1';

	public static function register(): void {
		$consumer = class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled();

		// OAuth callback (public, handles code exchange)
		register_rest_route( self::NAMESPACE, '/oauth/callback', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'oauth_callback' ),
			'permission_callback' => '__return_true',
		) );

		if ( ! $consumer ) {
			// Public: click tracking (sendBeacon target)
			register_rest_route( self::NAMESPACE, '/clicks', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'log_click' ),
				'permission_callback' => '__return_true',
			) );
		}

		// Public: product list (for AJAX / headless)
		register_rest_route( self::NAMESPACE, '/products', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_products' ),
			'permission_callback' => '__return_true',
		) );

		// Public: single product
		register_rest_route( self::NAMESPACE, '/products/(?P<id>[a-zA-Z0-9-]+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_product' ),
			'permission_callback' => '__return_true',
		) );

		if ( ! $consumer ) {
			register_rest_route( self::NAMESPACE, '/products/(?P<id>[a-zA-Z0-9-]+)/publication', array(
				'methods'             => 'PATCH',
				'callback'            => array( __CLASS__, 'update_product_publication' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			) );
		}

		if ( ! $consumer ) {
			// Price-alert workflow is a local publication feature rather than a
			// catalog projection concern, so connector installations do not accept
			// subscriptions that they will never process.
			register_rest_route( self::NAMESPACE, '/price-alerts', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'subscribe_price_alert' ),
				'permission_callback' => '__return_true',
			) );
			register_rest_route( self::NAMESPACE, '/price-alerts/confirm', array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'confirm_price_alert' ),
				'permission_callback' => '__return_true',
			) );
			register_rest_route( self::NAMESPACE, '/price-alerts/unsubscribe', array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'unsubscribe_price_alert' ),
				'permission_callback' => '__return_true',
			) );
		}

		if ( ! $consumer ) {
			// These routes call unrelated panel workflows. A catalog connector is
			// deliberately limited to projections and delivery acknowledgements.
			register_rest_route( self::NAMESPACE, '/leads', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit_lead' ),
				'permission_callback' => '__return_true',
			) );
		}

		// Admin: stats
		register_rest_route( self::NAMESPACE, '/stats', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_stats' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		// Admin: sync products
		register_rest_route( self::NAMESPACE, '/sync', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'sync_products' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		) );

		// Webhook receiver (from Kalahamoon SaaS)
		register_rest_route( self::NAMESPACE, '/webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_webhook' ),
			'permission_callback' => '__return_true',
		) );

		if ( ! $consumer ) {
			// Editor: AI product comparison (proxies to Kalahamoon's /api/public/ai/compare-products).
			// Restricted to users who can edit posts — this is a block-editor action.
			register_rest_route( self::NAMESPACE, '/ai/compare', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ai_compare' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			) );

			// Editor: AI content generation (proxies to Kalahamoon's /api/public/ai/generate-description).
			// type ∈ description | pros_cons | buying_guide | comparison_intro
			register_rest_route( self::NAMESPACE, '/ai/generate-content', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ai_generate_content' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			) );

			// Public: retrieval-only visitor search widget adapter.
			register_rest_route( self::NAMESPACE, '/ai/chat', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ai_chat' ),
				'permission_callback' => '__return_true',
			) );

			// AI Image Studio (proxies to Kalahamoon's /api/public/ai/generate-image).
			register_rest_route( self::NAMESPACE, '/ai/generate-image', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ai_generate_image' ),
				'permission_callback' => function () {
					return current_user_can( 'upload_files' );
				},
			) );

			// AI Image Studio: save a generated image into the Media Library and,
			// optionally, set it as the product's image in the local cache.
			register_rest_route( self::NAMESPACE, '/ai/save-image', array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'ai_save_image' ),
				'permission_callback' => function () {
					return current_user_can( 'upload_files' );
				},
			) );
		}
	}

	public static function log_click( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();

		Kalahamoon_Click_Tracker::log_click( array(
			'product_id' => sanitize_text_field( $data['productId'] ?? '' ),
			'link_id'    => absint( $data['linkId'] ?? 0 ) ?: null,
			'post_id'    => absint( $data['postId'] ?? 0 ) ?: null,
			'block_type' => sanitize_text_field( $data['blockType'] ?? '' ),
		) );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public static function get_products( WP_REST_Request $request ): WP_REST_Response {
		$consumer  = class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled();
		$is_editor = ! $consumer && current_user_can( 'edit_posts' );
		$result = Kalahamoon_Product_Cache::get_all( array(
			'page'              => max( 1, absint( $request->get_param( 'page' ) ?: 1 ) ),
			'limit'             => max( 1, min( 100, absint( $request->get_param( 'limit' ) ?: 20 ) ) ),
			'category'          => sanitize_title( (string) ( $request->get_param( 'category' ) ?: '' ) ),
			'brand'             => sanitize_title( (string) ( $request->get_param( 'brand' ) ?: '' ) ),
			'platform'          => sanitize_key( (string) ( $request->get_param( 'platform' ) ?: '' ) ),
			'search'            => sanitize_text_field( (string) ( $request->get_param( 'search' ) ?: '' ) ),
			'min_price'         => $request->get_param( 'minPrice' ),
			'max_price'         => $request->get_param( 'maxPrice' ),
			'sort'              => sanitize_key( (string) ( $request->get_param( 'sort' ) ?: 'newest' ) ),
			'publication_state' => $is_editor ? '' : 'VERIFIED',
			'public_ready'      => $consumer || ! $is_editor,
		) );

		return new WP_REST_Response( $result, 200 );
	}

	public static function get_product( WP_REST_Request $request ): WP_REST_Response {
		$product = Kalahamoon_Product_Cache::get_by_kalahamoon_id( $request->get_param( 'id' ) );
		$consumer = class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled();

		if ( $product && ! $consumer && ! current_user_can( 'edit_posts' ) ) {
			$product = Kalahamoon_Catalog_Policy::apply( $product );
		}
		if ( ! $product || ( ( $consumer || ! current_user_can( 'edit_posts' ) ) && empty( $product['publicReady'] ) ) ) {
			return new WP_REST_Response( array( 'message' => 'Not found' ), 404 );
		}

		return new WP_REST_Response( $product, 200 );
	}

	public static function update_product_publication( WP_REST_Request $request ): WP_REST_Response {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return new WP_REST_Response( array( 'message' => __( 'Catalog publication is managed in Kalahamoon.', 'kalahamoon' ) ), 403 );
		}

		$state = strtoupper( sanitize_key( (string) $request->get_param( 'publicationState' ) ) );
		$api   = new Kalahamoon_API_Products();
		$result = $api->update_publication_state( sanitize_text_field( (string) $request['id'] ), $state );
		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status = is_array( $error_data ) ? (int) ( $error_data['status'] ?? 400 ) : 400;
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), $status );
		}
		$publication = is_array( $result['listing'] ?? null ) ? $result['listing'] : $result;
		$cached      = is_array( $publication )
			? Kalahamoon_Product_Cache::update_publication_cache( sanitize_text_field( (string) $request['id'] ), $publication )
			: null;
		return new WP_REST_Response( array( 'publication' => $publication, 'product' => $cached ), 200 );
	}

	public static function subscribe_price_alert( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();
		$data = is_array( $data ) ? $data : array();

		// Honeypot: silently accept bot submissions without persisting.
		if ( ! empty( $data['website'] ) ) {
			return new WP_REST_Response( array( 'message' => 'Confirmation requested', 'ok' => true ), 202 );
		}

		// Rate limit: max 5 subscriptions per IP per 10 minutes.
		$ip       = self::rate_limit_identity();
		$rate_key = 'kalahamoon_palert_rate_' . md5( $ip );
		$count    = (int) get_transient( $rate_key );
		if ( $count >= 5 ) {
			return new WP_REST_Response( array( 'message' => __( 'درخواست‌های زیادی دریافت شد. لطفاً کمی صبر کنید.', 'kalahamoon' ) ), 429 );
		}
		set_transient( $rate_key, $count + 1, 10 * MINUTE_IN_SECONDS );

		$email   = sanitize_email( $data['email'] ?? '' );
		$pid     = sanitize_text_field( $data['productId'] ?? '' );
		$consent = true === ( $data['consent'] ?? false );

		if ( ! is_email( $email ) || empty( $pid ) || ! $consent ) {
			return new WP_REST_Response( array( 'message' => __( 'A valid email, product, and explicit consent are required.', 'kalahamoon' ) ), 400 );
		}

		$product = Kalahamoon_Product_Cache::get_by_kalahamoon_id( $pid );
		$product = $product ? Kalahamoon_Catalog_Policy::apply( $product ) : null;
		if ( ! $product || empty( $product['publicReady'] ) ) {
			return new WP_REST_Response( array( 'message' => 'Unknown product' ), 404 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_price_alerts';
		$subscription_key = hash( 'sha256', strtolower( $email ) . '|' . $pid );

		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, status, confirmation_expires_at FROM {$table} WHERE email = %s AND product_id = %s AND status IN ('pending', 'active') ORDER BY id DESC LIMIT 1",
			$email,
			$pid
		), ARRAY_A );
		if ( is_array( $existing ) && 'active' === ( $existing['status'] ?? '' ) ) {
			return new WP_REST_Response( array( 'message' => 'Confirmation requested', 'ok' => true ), 202 );
		}
		if ( is_array( $existing ) && false !== strtotime( (string) ( $existing['confirmation_expires_at'] ?? '' ) ) && strtotime( (string) $existing['confirmation_expires_at'] ) >= time() ) {
			return new WP_REST_Response( array( 'message' => 'Confirmation requested', 'ok' => true ), 202 );
		}

		$target_price = isset( $data['targetPrice'] ) && is_numeric( $data['targetPrice'] )
			? max( 0, (float) $data['targetPrice'] )
			: null;
		if ( 0.0 === $target_price ) {
			$target_price = null;
		}
		$raw_token       = bin2hex( random_bytes( 32 ) );
		$token_hash      = self::price_alert_token_hash( $raw_token );
		$expires_at      = gmdate( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS );
		$consent_version = sanitize_text_field( (string) ( $data['consentVersion'] ?? '1' ) );

		if ( is_array( $existing ) ) {
			$alert_id = (int) $existing['id'];
			$saved = $wpdb->update(
				$table,
				array(
					'target_price'            => $target_price,
					'confirm_token_hash'      => $token_hash,
					'confirmation_expires_at' => $expires_at,
					'consent_version'         => $consent_version,
					'consented_at'            => current_time( 'mysql', true ),
				),
				array( 'id' => $alert_id )
			);
		} else {
			$saved = $wpdb->insert( $table, array(
				'email'                   => $email,
				'product_id'              => $pid,
				'subscription_key'         => $subscription_key,
				'target_price'            => $target_price,
				'status'                  => 'pending',
				'confirm_token_hash'      => $token_hash,
				'confirmation_expires_at' => $expires_at,
				'consent_version'         => $consent_version,
				'consented_at'            => current_time( 'mysql', true ),
				'created_at'              => current_time( 'mysql', true ),
			) );
			$alert_id = (int) $wpdb->insert_id;
		}
		if ( false === $saved || $alert_id < 1 ) {
			$concurrent_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE subscription_key = %s LIMIT 1", $subscription_key ) ) );
			if ( $concurrent_id > 0 ) {
				return new WP_REST_Response( array( 'message' => 'Confirmation requested', 'ok' => true ), 202 );
			}
			return new WP_REST_Response( array( 'message' => __( 'The price alert could not be saved.', 'kalahamoon' ) ), 500 );
		}

		$confirm_url = add_query_arg(
			array( 'token' => $raw_token ),
			rest_url( self::NAMESPACE . '/price-alerts/confirm' )
		);
		$subject = sprintf( __( 'Confirm your price alert for %s', 'kalahamoon' ), (string) $product['title'] );
		$message = sprintf(
			/* translators: 1: product title, 2: confirmation URL. */
			__( "Confirm the price alert for %1\$s by opening this link within 48 hours:\n\n%2\$s", 'kalahamoon' ),
			(string) $product['title'],
			$confirm_url
		);
		if ( ! wp_mail( $email, $subject, $message ) ) {
			$wpdb->delete( $table, array( 'id' => $alert_id, 'status' => 'pending' ) );
			return new WP_REST_Response( array( 'message' => __( 'The confirmation email could not be sent.', 'kalahamoon' ) ), 503 );
		}

		return new WP_REST_Response( array( 'message' => 'Confirmation requested', 'ok' => true ), 202 );
	}

	public static function confirm_price_alert( WP_REST_Request $request ): WP_REST_Response {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		if ( 64 !== strlen( $token ) || ! ctype_xdigit( $token ) ) {
			return new WP_REST_Response( array( 'message' => __( 'This confirmation link is invalid or expired.', 'kalahamoon' ) ), 400 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_price_alerts';
		$alert = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE confirm_token_hash = %s AND status = 'pending' AND confirmation_expires_at >= UTC_TIMESTAMP() LIMIT 1",
			self::price_alert_token_hash( $token )
		), ARRAY_A );
		if ( ! is_array( $alert ) ) {
			return new WP_REST_Response( array( 'message' => __( 'This confirmation link is invalid or expired.', 'kalahamoon' ) ), 400 );
		}

		$updated = $wpdb->update(
			$table,
			array(
				'status'                  => 'active',
				'confirmed_at'            => current_time( 'mysql', true ),
				'confirm_token_hash'      => null,
				'confirmation_expires_at' => null,
			),
			array( 'id' => (int) $alert['id'], 'status' => 'pending' )
		);
		if ( 1 !== $updated ) {
			return new WP_REST_Response( array( 'message' => __( 'This confirmation link is invalid or expired.', 'kalahamoon' ) ), 400 );
		}

		return new WP_REST_Response( array( 'message' => __( 'Your price alert is active.', 'kalahamoon' ), 'ok' => true ), 200 );
	}

	public static function unsubscribe_price_alert( WP_REST_Request $request ): WP_REST_Response {
		$id    = absint( $request->get_param( 'id' ) );
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );

		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_price_alerts';
		$alert = $id > 0 ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ), ARRAY_A ) : null;
		if ( is_array( $alert ) && hash_equals( Kalahamoon_Price_Alert_Mailer::unsubscribe_token( $alert ), $token ) ) {
			$wpdb->update(
				$table,
				array( 'status' => 'unsubscribed', 'confirm_token_hash' => null, 'subscription_key' => null ),
				array( 'id' => $id )
			);
		}

		// Always return the same response so the endpoint cannot enumerate alerts.
		return new WP_REST_Response( array( 'message' => __( 'This price alert is no longer active.', 'kalahamoon' ), 'ok' => true ), 200 );
	}

	private static function price_alert_token_hash( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	public static function submit_lead( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();
		$data = is_array( $data ) ? $data : array();
		if ( ! empty( $data['website'] ) ) {
			return new WP_REST_Response( array( 'message' => 'Lead submitted', 'ok' => true, 'requestId' => null ), 201 );
		}

		// Rate limit: max 3 submissions per IP per 10 minutes.
		$ip       = self::rate_limit_identity();
		$rate_key = 'kalahamoon_lead_rate_' . md5( $ip );
		$count    = (int) get_transient( $rate_key );
		if ( $count >= 3 ) {
			return new WP_REST_Response( array( 'message' => __( 'درخواست‌های زیادی دریافت شد. لطفاً کمی صبر کنید.', 'kalahamoon' ) ), 429 );
		}
		set_transient( $rate_key, $count + 1, 10 * MINUTE_IN_SECONDS );

		$client = new Kalahamoon_API_Client();
		if ( ! $client->is_connected() ) {
			return new WP_REST_Response( array( 'message' => __( 'Not connected to Kalahamoon. Connect in the plugin settings.', 'kalahamoon' ) ), 400 );
		}

		$name    = sanitize_text_field( $data['name'] ?? '' );
		$email   = sanitize_email( $data['email'] ?? '' );
		$phone   = sanitize_text_field( $data['phoneNumber'] ?? '' );
		$message = sanitize_textarea_field( $data['message'] ?? '' );
		$intent  = sanitize_key( (string) ( $data['intent'] ?? 'contact' ) );
		$subject = sanitize_text_field( (string) ( $data['subject'] ?? '' ) );
		$consent = true === ( $data['consent'] ?? false );
		if ( ! in_array( $intent, array( 'contact', 'consultation', 'issue' ), true ) ) {
			return new WP_REST_Response( array( 'message' => __( 'Select a valid request type.', 'kalahamoon' ) ), 400 );
		}
		if ( ! $consent ) {
			return new WP_REST_Response( array( 'message' => __( 'Consent is required before submitting this request.', 'kalahamoon' ) ), 400 );
		}
		if ( '' !== (string) ( $data['email'] ?? '' ) && ! is_email( $email ) ) {
			return new WP_REST_Response( array( 'message' => __( 'Enter a valid email address.', 'kalahamoon' ) ), 400 );
		}

		// At least one identifying field is required (matches panel schema).
		if ( '' === $name && '' === $email && '' === $phone ) {
			return new WP_REST_Response( array( 'message' => __( 'Please provide your name, email, or phone number.', 'kalahamoon' ) ), 400 );
		}

		$context = self::normalize_lead_context( $data['context'] ?? null );
		if ( is_wp_error( $context ) ) {
			return new WP_REST_Response( array( 'message' => $context->get_error_message() ), 400 );
		}
		$source_ref = esc_url_raw( (string) ( $data['sourceRef'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' ) ) );
		$payload = array(
			'name'        => $name,
			'email'       => $email,
			'phoneNumber' => $phone,
			'message'     => $message,
			'source'      => 'WEBSITE',
			'sourceRef'   => $source_ref,
			'tags'        => array( 'wordpress', 'intent:' . $intent ),
			'intent'      => $intent,
			'subject'     => $subject,
			'context'     => $context,
			'consent'     => true,
			'consentVersion' => sanitize_text_field( (string) ( $data['consentVersion'] ?? '1' ) ),
			// Honeypot: forward the value verbatim so the panel can silently drop bots.
			'website'     => sanitize_text_field( $data['website'] ?? '' ),
		);

		// The panel's bearer-authenticated lead endpoint (honeypot-protected),
		// purpose-built for the WordPress plugin. Replaces the CAPTCHA-gated
		// public store route which rejected token-less plugin submissions.
		$result = $client->post( '/api/public/leads', array_filter(
			$payload,
			static fn( $v ) => '' !== $v && array() !== $v
		) );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}
		$request_id = sanitize_text_field( (string) ( $result['id'] ?? '' ) );
		if ( '' === $request_id ) {
			return new WP_REST_Response( array( 'message' => __( 'The request was received without a tracking identifier.', 'kalahamoon' ) ), 502 );
		}

		return new WP_REST_Response( array( 'message' => 'Lead submitted', 'ok' => true, 'requestId' => $request_id ), 201 );
	}

	/**
	 * Keep the WordPress boundary aligned with the panel's scalar context schema.
	 * Rejecting the whole context prevents silent data loss and ambiguity between
	 * what the visitor submitted and what the CRM ultimately persisted.
	 *
	 * @return array<string, string|int|float|bool|null>|WP_Error
	 */
	public static function normalize_lead_context( $raw ) {
		if ( null === $raw ) {
			return array();
		}
		if ( ! is_array( $raw ) || ( ! empty( $raw ) && array_is_list( $raw ) ) || count( $raw ) > 12 ) {
			return new WP_Error( 'kalahamoon_invalid_lead_context', __( 'Request context must contain at most 12 named scalar fields.', 'kalahamoon' ) );
		}

		$context = array();
		foreach ( $raw as $key => $value ) {
			$raw_key = trim( (string) $key );
			$key_len = function_exists( 'mb_strlen' ) ? mb_strlen( $raw_key ) : strlen( $raw_key );
			$safe_key = sanitize_key( $raw_key );
			if ( '' === $raw_key || $key_len > 60 || '' === $safe_key || array_key_exists( $safe_key, $context ) ) {
				return new WP_Error( 'kalahamoon_invalid_lead_context', __( 'Request context contains an invalid field name.', 'kalahamoon' ) );
			}
			if ( ! is_scalar( $value ) && null !== $value ) {
				return new WP_Error( 'kalahamoon_invalid_lead_context', __( 'Request context values must be scalar.', 'kalahamoon' ) );
			}
			if ( is_float( $value ) && ! is_finite( $value ) ) {
				return new WP_Error( 'kalahamoon_invalid_lead_context', __( 'Request context contains an invalid number.', 'kalahamoon' ) );
			}
			if ( is_string( $value ) ) {
				$value_len = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
				if ( $value_len > 500 ) {
					return new WP_Error( 'kalahamoon_invalid_lead_context', __( 'Request context values cannot exceed 500 characters.', 'kalahamoon' ) );
				}
				$value = sanitize_text_field( trim( $value ) );
			}
			$context[ $safe_key ] = $value;
		}

		return $context;
	}

	public static function get_stats( WP_REST_Request $request ): WP_REST_Response {
		$days  = $request->get_param( 'days' ) ?: 30;
		$stats = Kalahamoon_Click_Tracker::get_stats( (int) $days );

		$products = Kalahamoon_Product_Cache::get_all( array( 'limit' => 1 ) );
		$stats['totalProducts'] = $products['total'] ?? 0;
		$stats['lastSync']      = get_option( 'kalahamoon_last_sync', '' );

		return new WP_REST_Response( $stats, 200 );
	}

	public static function sync_products( WP_REST_Request $request ): WP_REST_Response {
		$api    = new Kalahamoon_API_Products();
		$result = $api->sync_all();

		return new WP_REST_Response( $result, empty( $result['complete'] ) ? 502 : 200 );
	}

	public static function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$signature = $request->get_header( 'X-Kalahamoon-Signature' );
		$event     = $request->get_header( 'X-Kalahamoon-Event' );
		$body      = $request->get_body();

		// Validate HMAC-SHA256 signature
		$secret = get_option( 'kalahamoon_webhook_secret', '' );
		if ( empty( $secret ) ) {
			return new WP_REST_Response(
				array( 'message' => 'Webhook signing is not configured.', 'code' => 'kalahamoon_webhook_not_configured' ),
				503
			);
		}
		if ( empty( $signature ) ) {
			return new WP_REST_Response( array( 'message' => 'Missing signature' ), 401 );
		}

		$expected = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
		if ( ! hash_equals( $expected, $signature ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid signature' ), 401 );
		}

		$consumer = class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled();
		if ( $consumer && 'catalog.snapshot.available' !== $event ) {
			// A connector site has no general inbound integration surface. Keeping
			// this rejection after HMAC verification lets the same route remain
			// compatible for standalone plugins without dispatching unrelated hooks.
			return new WP_REST_Response( array( 'message' => __( 'This catalog connector only accepts availability events.', 'kalahamoon' ) ), 403 );
		}

		$data = json_decode( $body, true );
		if ( $consumer ) {
			if ( ! is_array( $data ) ) {
				return new WP_REST_Response( array( 'message' => __( 'The catalog availability event is invalid.', 'kalahamoon' ) ), 400 );
			}

			Kalahamoon_Catalog_Consumer::record_available_snapshot( $data );
			do_action( 'kalahamoon_catalog_snapshot_available', $data );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		switch ( $event ) {
			case 'catalog.snapshot.available':
				// Connector mode handles this signal above. Standalone installations
				// historically acknowledge it without changing their local catalog.
				break;
			case 'order.synced':
				// Could trigger product inventory update
				do_action( 'kalahamoon_webhook_order_synced', $data );
				break;
			case 'lead.created':
				do_action( 'kalahamoon_webhook_lead_created', $data );
				break;
			default:
				do_action( 'kalahamoon_webhook_' . sanitize_key( $event ?? 'unknown' ), $data );
				break;
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * OAuth callback — handles the authorization code redirect from Kalahamoon.
	 */
	public static function oauth_callback( WP_REST_Request $request ): WP_REST_Response {
		$code  = sanitize_text_field( $request->get_param( 'code' ) ?? '' );
		$state = sanitize_text_field( $request->get_param( 'state' ) ?? '' );
		$error = sanitize_text_field( $request->get_param( 'error' ) ?? '' );

		// Handle user denial
		if ( $error ) {
			$redirect = add_query_arg(
				array( 'page' => 'kalahamoon-setting', 'kalahamoon_oauth_error' => $error ),
				admin_url( 'admin.php' )
			);
			return new WP_REST_Response( null, 302, array( 'Location' => $redirect ) );
		}

		if ( empty( $code ) || empty( $state ) ) {
			$redirect = add_query_arg(
				array( 'page' => 'kalahamoon-setting', 'kalahamoon_oauth_error' => 'missing_params' ),
				admin_url( 'admin.php' )
			);
			return new WP_REST_Response( null, 302, array( 'Location' => $redirect ) );
		}

		$success = Kalahamoon_Auth::handle_callback( $code, $state );

		if ( $success ) {
			$redirect = add_query_arg(
				array( 'page' => 'kalahamoon-setting', 'kalahamoon_connected' => '1' ),
				admin_url( 'admin.php' )
			);
		} else {
			$redirect = add_query_arg(
				array( 'page' => 'kalahamoon-setting', 'kalahamoon_oauth_error' => 'exchange_failed' ),
				admin_url( 'admin.php' )
			);
		}

		return new WP_REST_Response( null, 302, array( 'Location' => $redirect ) );
	}

	/**
	 * Proxy AI product comparison request to Kalahamoon.
	 *
	 * Expects JSON body: { productIds: [a, b], criteria?: string[], language?: 'en'|'fa' }
	 * Returns Kalahamoon's response verbatim on success, or a WP_Error-shaped JSON on failure.
	 *
	 * Results are cached for 24h keyed on productIds+criteria+language so editors
	 * don't burn tokens opening the same draft repeatedly.
	 */
	public static function ai_compare( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();

		$product_ids = $data['productIds'] ?? array();
		$criteria    = $data['criteria'] ?? array();
		$language    = self::resolve_language( $data['language'] ?? null, array( 'fa', 'ar', 'en' ) );

		if ( ! is_array( $product_ids ) || count( $product_ids ) < 2 || count( $product_ids ) > 4 ) {
			return new WP_REST_Response( array( 'message' => 'productIds must be an array of 2 to 4 product IDs' ), 400 );
		}

		$product_ids = array_values( array_map( 'sanitize_text_field', $product_ids ) );
		if ( in_array( '', $product_ids, true ) ) {
			return new WP_REST_Response( array( 'message' => 'Product IDs must be non-empty' ), 400 );
		}

		$criteria = is_array( $criteria )
			? array_values( array_filter( array_map( 'sanitize_text_field', $criteria ) ) )
			: array();

		// Cache key keeps the editor's product order because the AI response labels columns by order.
		$cache_key = 'kalahamoon_ai_cmp_' . md5( implode( ',', $product_ids ) . '|' . implode( ',', $criteria ) . '|' . $language );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && ! $request->get_param( 'refresh' ) ) {
			$cached['draft'] = true;
			if ( empty( $cached['provenance'] ) ) {
				$cached['provenance'] = array(
					'source'      => 'kalahamoon-ai',
					'productIds'  => $product_ids,
					'language'    => $language,
					'generatedAt' => '',
				);
			}
			return new WP_REST_Response( array_merge( $cached, array( 'cached' => true ) ), 200 );
		}

		$client = new Kalahamoon_API_Client();
		if ( ! $client->is_connected() ) {
			return new WP_REST_Response( array( 'message' => __( 'Not connected to Kalahamoon. Connect in the plugin settings.', 'kalahamoon' ) ), 401 );
		}

		$payload = array(
			'productIds' => $product_ids,
			'language'   => $language,
		);
		// The panel schema requires `criteria` to have >=1 item when present, so
		// only forward it when the editor actually supplied criteria. Otherwise the
		// AI auto-selects criteria and an empty array would fail validation.
		if ( ! empty( $criteria ) ) {
			$payload['criteria'] = $criteria;
		}

		$result = $client->post( '/api/public/ai/compare-products', $payload );

		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 500 );
			return new WP_REST_Response( array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			), $status );
		}

		if (
			empty( $result['comparison'] )
			|| ! is_array( $result['comparison'] )
			|| empty( $result['comparison']['criteria'] )
			|| ! is_array( $result['comparison']['criteria'] )
			|| empty( $result['comparison']['verdict'] )
		) {
			return new WP_REST_Response(
				array( 'message' => __( 'AI comparison returned an invalid response.', 'kalahamoon' ) ),
				502
			);
		}
		$result['draft'] = true;
		$result['provenance'] = array(
			'source'      => 'kalahamoon-ai',
			'productIds'  => $product_ids,
			'language'    => $language,
			'generatedAt' => gmdate( 'c' ),
		);

		set_transient( $cache_key, $result, DAY_IN_SECONDS );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Proxy an AI content-generation request to Kalahamoon's
	 * /api/public/ai/generate-description. Expects JSON:
	 *   { productId, type?, language?, tone?, maxLength? }
	 *
	 * Results are cached 24h keyed on the request shape so editors don't burn
	 * tokens regenerating the same draft.
	 */
	public static function ai_generate_content( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();

		$product_id = sanitize_text_field( $data['productId'] ?? '' );
		if ( '' === $product_id ) {
			return new WP_REST_Response( array( 'message' => 'productId is required' ), 400 );
		}

		$type = sanitize_text_field( $data['type'] ?? 'description' );
		if ( ! in_array( $type, array( 'description', 'pros_cons', 'buying_guide', 'comparison_intro' ), true ) ) {
			$type = 'description';
		}

		$language = self::resolve_language( $data['language'] ?? null, array( 'fa', 'en' ) );
		$tone     = in_array( $data['tone'] ?? 'professional', array( 'professional', 'casual', 'expert' ), true ) ? $data['tone'] : 'professional';
		$max_len  = isset( $data['maxLength'] ) ? max( 50, min( 2000, (int) $data['maxLength'] ) ) : 500;

		$payload = array(
			'productId' => $product_id,
			'type'      => $type,
			'language'  => $language,
			'tone'      => $tone,
			'maxLength' => $max_len,
		);

		$cache_key = 'kalahamoon_ai_content_' . md5( wp_json_encode( $payload ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && ! $request->get_param( 'refresh' ) ) {
			return new WP_REST_Response( array_merge( $cached, array( 'cached' => true ) ), 200 );
		}

		$client = new Kalahamoon_API_Client();
		if ( ! $client->is_connected() ) {
			return new WP_REST_Response( array( 'message' => __( 'Not connected to Kalahamoon. Connect in the plugin settings.', 'kalahamoon' ) ), 401 );
		}

		$result = $client->post( '/api/public/ai/generate-description', $payload );

		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 500 );
			return new WP_REST_Response( array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			), $status );
		}

		$generated = $result['generated'] ?? null;
		$valid_generated = false;
		if ( is_array( $generated ) ) {
			switch ( $type ) {
				case 'pros_cons':
					$valid_generated = ! empty( $generated['pros'] )
						&& is_array( $generated['pros'] )
						&& ! empty( $generated['cons'] )
						&& is_array( $generated['cons'] )
						&& ! empty( $generated['verdict'] );
					break;
				case 'buying_guide':
					$valid_generated = ! empty( $generated['guide'] )
						&& ! empty( $generated['keyFactors'] )
						&& is_array( $generated['keyFactors'] );
					break;
				case 'comparison_intro':
					$valid_generated = ! empty( $generated['intro'] );
					break;
				case 'description':
				default:
					$valid_generated = ! empty( $generated['description'] )
						&& ! empty( $generated['headline'] );
					break;
			}
		}

		if ( ! $valid_generated ) {
			return new WP_REST_Response(
				array( 'message' => __( 'AI content generation returned an invalid response.', 'kalahamoon' ) ),
				502
			);
		}
		$result['draft'] = true;
		$result['provenance'] = array(
			'source'      => 'kalahamoon-ai',
			'productId'   => $product_id,
			'contentType' => $type,
			'language'    => $language,
			'generatedAt' => gmdate( 'c' ),
		);

		set_transient( $cache_key, $result, DAY_IN_SECONDS );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Public visitor-search compatibility route.
	 *
	 * Public storefront assistants are retrieval-only. They search cached products
	 * and public WordPress content, then return openable cards instead of proxying
	 * to generative AI.
	 */
	public static function ai_chat( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( ! empty( $data['website'] ) ) {
			return new WP_REST_Response( array( 'type' => 'retrieval_results', 'text' => '', 'results' => array() ), 200 );
		}

		$ip       = self::rate_limit_identity();
		$rate_key = 'kalahamoon_public_search_rate_' . md5( $ip );
		$count    = (int) get_transient( $rate_key );
		if ( $count >= 10 ) {
			return new WP_REST_Response( array( 'message' => __( 'درخواست‌های زیادی دریافت شد. لطفاً کمی صبر کنید.', 'kalahamoon' ) ), 429 );
		}
		set_transient( $rate_key, $count + 1, 10 * MINUTE_IN_SECONDS );

		$message = sanitize_textarea_field( $data['message'] ?? $data['query'] ?? '' );
		if ( '' === trim( $message ) ) {
			return new WP_REST_Response( array( 'message' => __( 'Search query is required.', 'kalahamoon' ) ), 400 );
		}

		$limit   = isset( $data['limit'] ) ? max( 1, min( 10, absint( $data['limit'] ) ) ) : 6;
		$results = self::search_openable_public_content( $message, $limit );
		$text    = empty( $results )
			? __( 'No openable products, posts, or pages matched this search.', 'kalahamoon' )
			: sprintf(
				/* translators: 1: search query, 2: result count. */
				__( 'Found %2$s openable result(s) for “%1$s”.', 'kalahamoon' ),
				sanitize_text_field( wp_trim_words( $message, 8, '…' ) ),
				number_format_i18n( count( $results ) )
			);

		return new WP_REST_Response( array(
			'type'    => 'retrieval_results',
			'query'   => $message,
			'text'    => $text,
			'results' => $results,
		), 200 );
	}

	private static function search_openable_public_content( string $query, int $limit ): array {
		$products        = self::search_cached_products_for_public_chat( $query, $limit );
		$remaining_slots = $limit - count( $products );
		$posts           = $remaining_slots > 0 ? self::search_public_posts_for_public_chat( $query, $remaining_slots ) : array();
		$remaining_slots -= count( $posts );
		$taxonomy_posts  = $remaining_slots > 0
			? self::search_public_taxonomy_posts_for_public_chat( $query, $remaining_slots, array_column( $posts, 'id' ) )
			: array();

		return array_slice( array_merge( $products, $posts, $taxonomy_posts ), 0, $limit );
	}

	private static function search_cached_products_for_public_chat( string $query, int $limit ): array {
		if ( $limit < 1 || ! class_exists( 'Kalahamoon_Product_Cache' ) ) {
			return array();
		}

		$cached = Kalahamoon_Product_Cache::get_all( array(
			'limit'        => $limit,
			'search'       => $query,
			'public_ready' => true,
		) );

		$results = array();
		foreach ( $cached['items'] ?? array() as $product ) {
			if ( ! is_array( $product ) || empty( $product['listingUrl'] ) ) {
				continue;
			}
			$price = isset( $product['price'] ) && (float) $product['price'] > 0
				? number_format_i18n( (float) $product['price'] ) . ' ' . sanitize_text_field( $product['currency'] ?? 'IRR' )
				: '';
			$results[] = array(
				'type'     => 'product',
				'id'       => sanitize_text_field( $product['id'] ?? '' ),
				'title'    => sanitize_text_field( $product['title'] ?? '' ),
				'excerpt'  => wp_trim_words( wp_strip_all_tags( (string) ( $product['description'] ?? '' ) ), 24 ),
				'url'      => esc_url_raw( (string) $product['listingUrl'] ),
				'imageUrl' => esc_url_raw( (string) ( $product['imageUrl'] ?? '' ) ),
				'kicker'   => sanitize_text_field( $product['sellerName'] ?? $product['platform'] ?? $product['category'] ?? __( 'Product', 'kalahamoon' ) ),
				'price'    => $price,
				'cta'      => __( 'Open product', 'kalahamoon' ),
			);
		}

		return $results;
	}

	private static function search_public_posts_for_public_chat( string $query, int $limit ): array {
		$query_args = array(
			'post_type'              => array( 'post', 'page' ),
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			's'                      => $query,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
		);
		/**
		 * Allow an editorial site to apply its own reviewed-content boundary while
		 * keeping the reusable plugin compatible with ordinary WordPress sites.
		 */
		$query_args    = apply_filters( 'kalahamoon_public_content_query_args', $query_args, $query );
		$content_query = new WP_Query( $query_args );

		$results = self::public_content_results( $content_query->posts );
		wp_reset_postdata();

		return $results;
	}

	/**
	 * A visitor can search for a visible topic even when its label is not repeated
	 * in every article body. Category and tag matches keep that discovery promise
	 * without widening the result set beyond published, openable content.
	 *
	 * @param array<int, string> $excluded_ids
	 * @return array<int, array<string, string>>
	 */
	private static function search_public_taxonomy_posts_for_public_chat( string $query, int $limit, array $excluded_ids ): array {
		if ( $limit < 1 ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => array( 'category', 'post_tag' ),
				'hide_empty' => true,
				'search'      => $query,
				'number'      => 12,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$terms_by_taxonomy = array( 'category' => array(), 'post_tag' => array() );
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term && isset( $terms_by_taxonomy[ $term->taxonomy ] ) ) {
				$terms_by_taxonomy[ $term->taxonomy ][] = (int) $term->term_id;
			}
		}
		$tax_query = array( 'relation' => 'OR' );
		foreach ( $terms_by_taxonomy as $taxonomy => $term_ids ) {
			if ( empty( $term_ids ) ) {
				continue;
			}
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $term_ids,
			);
		}
		if ( 1 === count( $tax_query ) ) {
			return array();
		}

		$query_args = array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'post__not_in'           => array_map( 'absint', $excluded_ids ),
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
			'tax_query'              => $tax_query,
		);
		$query_args    = apply_filters( 'kalahamoon_public_content_query_args', $query_args, $query );
		$content_query = new WP_Query( $query_args );
		$results       = self::public_content_results( $content_query->posts );
		wp_reset_postdata();

		return $results;
	}

	/**
	 * @param array<int, WP_Post> $posts
	 * @return array<int, array<string, string>>
	 */
	private static function public_content_results( array $posts ): array {
		$results = array();
		foreach ( $posts as $post ) {
			$url = get_permalink( $post );
			if ( ! $url ) {
				continue;
			}
			$results[] = array(
				'type'     => 'post' === $post->post_type ? 'post' : 'page',
				'id'       => (string) $post->ID,
				'title'    => get_the_title( $post ),
				'excerpt'  => wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ?: $post->post_content ), 24 ),
				'url'      => esc_url_raw( $url ),
				'imageUrl' => get_the_post_thumbnail_url( $post, 'medium' ) ?: '',
				'kicker'   => 'post' === $post->post_type ? __( 'Post', 'kalahamoon' ) : __( 'Page', 'kalahamoon' ),
				'price'    => '',
				'cta'      => __( 'Open', 'kalahamoon' ),
			);
		}
		return $results;
	}

	/**
	 * Proxy an AI Image Studio request to Kalahamoon's
	 * /api/public/ai/generate-image. Expects JSON:
	 *   { productId?, mode, prompt?, sourceImageUrls?, size?, language?, style?, palette?, roomType? }
	 */
	public static function ai_generate_image( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();

		$mode = sanitize_text_field( $data['mode'] ?? 'enhance' );
		if ( ! in_array( $mode, array( 'enhance', 'background', 'aggregate', 'generate', 'scene' ), true ) ) {
			$mode = 'enhance';
		}

		$payload = array( 'mode' => $mode );

		if ( ! empty( $data['productId'] ) ) {
			$payload['productId'] = sanitize_text_field( $data['productId'] );
		}
		if ( isset( $data['prompt'] ) && '' !== trim( (string) $data['prompt'] ) ) {
			$payload['prompt'] = sanitize_textarea_field( $data['prompt'] );
		}
		if ( ! empty( $data['sourceImageUrls'] ) && is_array( $data['sourceImageUrls'] ) ) {
			$urls = array_values( array_filter( array_map( 'esc_url_raw', array_slice( $data['sourceImageUrls'], 0, 6 ) ) ) );
			foreach ( $urls as $url ) {
				if ( null !== Kalahamoon_Image_Policy::remote_url_issue( $url ) ) {
					return new WP_REST_Response( array( 'message' => __( 'One or more source image URLs are not allowed.', 'kalahamoon' ) ), 400 );
				}
			}
			if ( ! empty( $urls ) ) {
				$payload['sourceImageUrls'] = $urls;
			}
		}
		$size = sanitize_text_field( $data['size'] ?? '1024x1024' );
		if ( in_array( $size, array( '1024x1024', '1024x1536', '1536x1024' ), true ) ) {
			$payload['size'] = $size;
		}
		$payload['language'] = self::resolve_language( $data['language'] ?? null, array( 'fa', 'ar', 'en' ) );
		foreach ( array( 'style', 'palette', 'roomType' ) as $field ) {
			if ( isset( $data[ $field ] ) && '' !== trim( (string) $data[ $field ] ) ) {
				$payload[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		$client = new Kalahamoon_API_Client();
		if ( ! $client->is_connected() ) {
			return new WP_REST_Response( array( 'message' => __( 'Not connected to Kalahamoon. Connect in the plugin settings.', 'kalahamoon' ) ), 401 );
		}

		$result = $client->post( '/api/public/ai/generate-image', $payload );

		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 500 );
			return new WP_REST_Response( array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			), $status );
		}

		if ( empty( $result['images'] ) || ! is_array( $result['images'] ) || ! isset( $result['images'][0] ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'AI image studio returned an invalid response.', 'kalahamoon' ) ),
				502
			);
		}
		foreach ( $result['images'] as $image_reference ) {
			if ( ! is_string( $image_reference ) || ! self::is_supported_generated_image_reference( $image_reference ) ) {
				return new WP_REST_Response(
					array( 'message' => __( 'AI image studio returned an unsupported image.', 'kalahamoon' ) ),
					502
				);
			}
		}
		$result['draft'] = true;
		$result['provenance'] = array(
			'source'      => 'kalahamoon-ai',
			'mode'        => $mode,
			'language'    => $payload['language'],
			'productId'   => $payload['productId'] ?? '',
			'generatedAt' => gmdate( 'c' ),
		);

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Sideload a generated image URL into the Media Library and, when a product
	 * is provided, set it as that product's cached image. Returns the attachment
	 * URL + ID.
	 */
	public static function ai_save_image( WP_REST_Request $request ): WP_REST_Response {
		$data = $request->get_json_params();

		$image_url  = trim( (string) ( $data['imageUrl'] ?? '' ) );
		$product_id = sanitize_text_field( $data['productId'] ?? '' );
		$apply      = ! empty( $data['applyToProduct'] );
		if ( $apply && class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return new WP_REST_Response( array( 'message' => __( 'Catalog images are managed in Kalahamoon.', 'kalahamoon' ) ), 403 );
		}

		if ( '' === $image_url ) {
			return new WP_REST_Response( array( 'message' => __( 'Missing image URL.', 'kalahamoon' ) ), 400 );
		}
		$product = null;
		if ( $apply ) {
			if ( '' === $product_id ) {
				return new WP_REST_Response( array( 'message' => __( 'Select a product before applying an image.', 'kalahamoon' ) ), 400 );
			}
			$product = Kalahamoon_Product_Cache::get_by_kalahamoon_id( $product_id );
			if ( ! $product || empty( $product['wp_post_id'] ) ) {
				return new WP_REST_Response( array( 'message' => __( 'Local product image was not applied.', 'kalahamoon' ) ), 404 );
			}
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Support validated raster data URIs; all remote imports use WordPress's
		// safe HTTP transport with strict size, redirect, MIME, and dimension caps.
		if ( 0 === strpos( $image_url, 'data:image' ) ) {
			$attachment_id = self::sideload_data_uri( $image_url );
		} else {
			$client   = new Kalahamoon_API_Client();
			$internal = self::trusted_generated_image_download_url( $image_url, $client );
			$download = $internal
				? Kalahamoon_Image_Policy::download_trusted_internal( $internal, $client->get_service_base_url() )
				: Kalahamoon_Image_Policy::download_remote( $image_url );
			if ( is_wp_error( $download ) ) {
				return new WP_REST_Response( array( 'message' => $download->get_error_message() ), 502 );
			}
			$file_array    = array(
				'name'     => 'kalahamoon-ai-' . gmdate( 'YmdHis' ) . '.' . $download['extension'],
				'tmp_name' => $download['tmp_name'],
			);
			$attachment_id = media_handle_sideload( $file_array, 0, __( 'Kalahamoon AI image', 'kalahamoon' ) );
			if ( is_wp_error( $attachment_id ) ) {
				@unlink( $download['tmp_name'] );
				return new WP_REST_Response( array( 'message' => $attachment_id->get_error_message() ), 500 );
			}
		}

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new WP_REST_Response( array( 'message' => __( 'Could not save image to Media Library.', 'kalahamoon' ) ), 500 );
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );
		$provenance = array(
			'source'      => 'kalahamoon-ai',
			'productId'   => $product_id,
			'generatedAt' => gmdate( 'c' ),
		);
		if ( is_array( $data['provenance'] ?? null ) ) {
			foreach ( array( 'mode', 'language', 'model', 'requestId' ) as $field ) {
				if ( isset( $data['provenance'][ $field ] ) && is_scalar( $data['provenance'][ $field ] ) ) {
					$provenance[ $field ] = sanitize_text_field( (string) $data['provenance'][ $field ] );
				}
			}
		}
		update_post_meta( (int) $attachment_id, '_kalahamoon_ai_generated', 1 );
		update_post_meta( (int) $attachment_id, '_kalahamoon_ai_provenance', wp_json_encode( $provenance ) );

		// A cache entry keeps its local image through later catalog syncs, so only
		// mark it as local after WordPress confirms the attachment is usable.
		if ( $apply && $product ) {
			set_post_thumbnail( (int) $product['wp_post_id'], (int) $attachment_id );
			if ( (int) get_post_thumbnail_id( (int) $product['wp_post_id'] ) !== (int) $attachment_id ) {
				wp_delete_attachment( (int) $attachment_id, true );
				return new WP_REST_Response( array( 'message' => __( 'The image was saved but could not be applied to the local product.', 'kalahamoon' ) ), 500 );
			}
			update_post_meta( (int) $product['wp_post_id'], '_kalahamoon_image_url', $attachment_url );
			update_post_meta( (int) $product['wp_post_id'], '_kalahamoon_local_image_url', $attachment_url );
			update_post_meta( (int) $product['wp_post_id'], '_kalahamoon_image_attachment_id', (int) $attachment_id );
		}

		return new WP_REST_Response( array(
			'ok'            => true,
			'attachmentId'  => (int) $attachment_id,
			'attachmentUrl' => $attachment_url,
			'applied'       => (bool) $apply,
		), 200 );
	}

	/**
	 * Decode a data: image URI and insert it as a Media Library attachment.
	 *
	 * @return int|WP_Error Attachment ID or error.
	 */
	private static function sideload_data_uri( string $data_uri ) {
		$decoded = Kalahamoon_Image_Policy::decode_data_uri( $data_uri );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$filename = 'kalahamoon-ai-' . gmdate( 'YmdHis' ) . '.' . $decoded['extension'];
		$upload   = wp_upload_bits( $filename, null, $decoded['binary'] );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'kalahamoon_upload_failed', $upload['error'] );
		}

		$filetype   = wp_check_filetype( $upload['file'], null );
		$attachment = array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	/**
	 * The image endpoint may return a durable HTTPS URL or OpenAI-compatible
	 * base64 output. esc_url_raw() removes data URIs, which previously made a
	 * valid generated image look like a malformed API response before saving.
	 */
	private static function is_supported_generated_image_reference( string $image_reference ): bool {
		$image_reference = trim( $image_reference );
		if ( 0 === strpos( $image_reference, 'data:image' ) ) {
			return ! is_wp_error( Kalahamoon_Image_Policy::decode_data_uri( $image_reference ) );
		}

		return null === Kalahamoon_Image_Policy::remote_url_issue( $image_reference );
	}

	/**
	 * Generated assets use a predictable static path. Only that exact path on
	 * the configured public app host may be translated to the internal service;
	 * every other editor-supplied URL remains subject to the normal safe fetch.
	 */
	private static function trusted_generated_image_download_url( string $image_url, Kalahamoon_API_Client $client ): ?string {
		$public_parts = wp_parse_url( $client->get_public_base_url() );
		$image_parts  = wp_parse_url( $image_url );
		$service_url  = $client->get_service_base_url();
		if ( ! is_array( $public_parts ) || ! is_array( $image_parts ) || '' === $service_url ) {
			return null;
		}
		if (
			strtolower( (string) ( $image_parts['scheme'] ?? '' ) ) !== strtolower( (string) ( $public_parts['scheme'] ?? '' ) )
			|| strtolower( (string) ( $image_parts['host'] ?? '' ) ) !== strtolower( (string) ( $public_parts['host'] ?? '' ) )
			|| (int) ( $image_parts['port'] ?? 0 ) !== (int) ( $public_parts['port'] ?? 0 )
			|| ! empty( $image_parts['query'] )
			|| ! empty( $image_parts['fragment'] )
		) {
			return null;
		}

		$path = (string) ( $image_parts['path'] ?? '' );
		if ( ! preg_match( '#^/uploads/ai-images/[A-Za-z0-9_-]+/[0-9a-f-]{36}\.(?:jpg|png|webp)$#i', $path ) ) {
			return null;
		}

		return rtrim( $service_url, '/' ) . $path;
	}

	private static function resolve_language( $requested, array $allowed ): string {
		$requested = strtolower( sanitize_key( (string) $requested ) );
		if ( in_array( $requested, $allowed, true ) ) {
			return $requested;
		}

		$locale = strtolower( function_exists( 'determine_locale' ) ? determine_locale() : get_locale() );
		foreach ( $allowed as $language ) {
			if ( str_starts_with( $locale, $language ) ) {
				return $language;
			}
		}

		return in_array( 'fa', $allowed, true ) ? 'fa' : $allowed[0];
	}

	private static function rate_limit_identity(): string {
		$remote = trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$remote = false !== filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
		if ( ! defined( 'KALAHAMOON_TRUSTED_PROXY_HEADERS' ) || true !== KALAHAMOON_TRUSTED_PROXY_HEADERS ) {
			return $remote;
		}

		$forwarded = explode( ',', (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' ) );
		$candidate = trim( (string) ( $forwarded[0] ?? '' ) );
		$is_public = false !== filter_var(
			$candidate,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
		return $is_public ? $candidate : $remote;
	}
}
