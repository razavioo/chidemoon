<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Catalog_Sync {
	private const SETTINGS = 'chidemoon_catalog_settings';
	private const TOKENS = 'chidemoon_catalog_tokens';
	private const OUTBOX = 'chidemoon_catalog_outbox';
	private const STATUS = 'chidemoon_catalog_status';
	private const SCOPES = array( 'catalog:source:read', 'catalog:source:ack' );
	private const HOOK = 'chidemoon_catalog_poll';

	public static function register(): void {
		add_filter( 'cron_schedules', static function( $schedules ) {
			$schedules['chidemoon_catalog_quarter_hour'] = array( 'interval' => 900, 'display' => __( 'Every 15 minutes', 'chidemoon-catalog-sync' ) );
			return $schedules;
		} );
		add_action( self::HOOK, array( __CLASS__, 'poll' ) );
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', static fn( $vars ) => array_merge( $vars, array( 'chidemoon_catalog_proof' ) ) );
		add_action( 'template_redirect', array( __CLASS__, 'proof' ), 0 );
		add_action( 'admin_menu', static function() {
			add_submenu_page( 'woocommerce', __( 'Source synchronization', 'chidemoon-catalog-sync' ), __( 'Source synchronization', 'chidemoon-catalog-sync' ), 'manage_woocommerce', 'chidemoon-catalog-sync', array( __CLASS__, 'page' ) );
		} );
		add_action( 'admin_post_chidemoon_catalog_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_chidemoon_catalog_authorize', array( __CLASS__, 'authorize' ) );
		add_action( 'admin_post_chidemoon_catalog_callback', array( __CLASS__, 'callback' ) );
		add_action( 'admin_post_chidemoon_catalog_refresh', array( __CLASS__, 'refresh' ) );
	}

	public static function rewrite(): void {
		add_rewrite_rule( '^\.well-known/kalahamoon-publication-catalog-connector\.json$', 'index.php?chidemoon_catalog_proof=1', 'top' );
	}

	public static function activate(): void { self::rewrite(); flush_rewrite_rules(); }
	public static function deactivate(): void { wp_clear_scheduled_hook( self::HOOK ); flush_rewrite_rules(); }

	public static function proof(): void {
		if ( ! get_query_var( 'chidemoon_catalog_proof' ) ) { return; }
		$challenge = (string) ( get_option( self::SETTINGS, array() )['challenge'] ?? '' );
		if ( ! preg_match( '/^catalog_origin_[A-Za-z0-9_-]{32,256}$/D', $challenge ) ) { status_header( 404 ); exit; }
		nocache_headers();
		wp_send_json( array( 'challenge' => $challenge ) );
	}

	private static function allowed(): void {
		if ( ! current_user_can( 'chidemoon_manage_affiliate' ) || ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'Forbidden', 'chidemoon-catalog-sync' ), '', array( 'response' => 403 ) ); }
	}

	public static function callback_url(): string {
		return admin_url( 'admin-post.php?action=chidemoon_catalog_callback' );
	}

	private static function secret(): string {
		$value = defined( 'CHIDEMOON_CATALOG_CLIENT_SECRET' ) ? constant( 'CHIDEMOON_CATALOG_CLIENT_SECRET' ) : getenv( 'CHIDEMOON_CATALOG_CLIENT_SECRET' );
		if ( ! is_string( $value ) || '' === $value ) { throw new DomainException( 'client_secret_missing' ); }
		return $value;
	}

	private static function seal( array $value ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$key = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
		return base64_encode( $nonce . sodium_crypto_secretbox( wp_json_encode( $value ), $nonce, $key ) );
	}

	private static function unseal( string $value ): array {
		$bytes = base64_decode( $value, true );
		if ( false === $bytes || strlen( $bytes ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) { throw new DomainException( 'credentials_unavailable' ); }
		$key = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );
		$plain = sodium_crypto_secretbox_open( substr( $bytes, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $bytes, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
		if ( false === $plain ) { throw new DomainException( 'credentials_unavailable' ); }
		return json_decode( $plain, true, 32, JSON_THROW_ON_ERROR );
	}

  private static function configuration( ?array $candidate = null ): array {
		$config = $candidate ?? get_option( self::SETTINGS, array() );
		$url = wp_parse_url( (string) ( $config['origin'] ?? '' ) );
		if ( ! is_array( $url ) || 'https' !== ( $url['scheme'] ?? '' ) || empty( $url['host'] ) || isset( $url['user'] ) || isset( $url['pass'] ) || isset( $url['query'] ) || isset( $url['fragment'] ) || ! in_array( $url['path'] ?? '/', array( '', '/' ), true ) || ! wp_http_validate_url( $config['origin'] ) ) { throw new DomainException( 'configuration_invalid' ); }
		foreach ( array( 'clientId', 'organizationId', 'organizationSlug' ) as $key ) { if ( ! is_string( $config[ $key ] ?? null ) || '' === $config[ $key ] ) { throw new RuntimeException( 'configuration_invalid' ); } }
		return $config;
	}

	private static function request( string $path, array $args = array(), int $limit = 12000000 ): array {
		$config = self::configuration();
		$args = array_merge( array( 'method' => 'GET', 'timeout' => 30, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => $limit + 1 ), $args );
		$args['headers']['Accept'] = 'application/json';
		$response = wp_safe_remote_request( rtrim( $config['origin'], '/' ) . $path, $args );
		if ( is_wp_error( $response ) ) { throw new RuntimeException( 'upstream_unavailable' ); }
		$code = wp_remote_retrieve_response_code( $response );
		if ( 401 === $code || 403 === $code ) { throw new DomainException( 'authorization_required' ); }
		if ( 429 === $code || $code >= 500 ) { throw new RuntimeException( 'upstream_unavailable' ); }
		if ( $code < 200 || $code >= 300 ) { throw new DomainException( 'upstream_rejected' ); }
		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > $limit ) { throw new DomainException( 'response_too_large' ); }
		try { $decoded = json_decode( $body, true, 64, JSON_THROW_ON_ERROR ); }
		catch ( Throwable $exception ) { throw new DomainException( 'invalid_response' ); }
		if ( ! is_array( $decoded ) ) { throw new DomainException( 'invalid_response' ); }
		return $decoded;
	}

	private static function store_tokens( array $tokens ): void {
		if ( ! is_string( $tokens['access_token'] ?? null ) || ! is_string( $tokens['refresh_token'] ?? null ) || 'Bearer' !== ( $tokens['token_type'] ?? null ) || ! is_int( $tokens['expires_in'] ?? null ) || $tokens['expires_in'] < 60 || array_diff( self::SCOPES, explode( ' ', (string) ( $tokens['scope'] ?? '' ) ) ) ) { throw new DomainException( 'source_scopes_missing' ); }
		$tokens['expiresAt'] = time() + $tokens['expires_in'];
		update_option( self::TOKENS, self::seal( $tokens ), false );
	}

	private static function access_token(): string {
		$tokens = self::unseal( (string) get_option( self::TOKENS, '' ) );
		if ( (int) ( $tokens['expiresAt'] ?? 0 ) > time() + 60 ) { return $tokens['access_token']; }
		$config = self::configuration();
		$tokens = self::request( '/api/oauth2/token', array( 'method' => 'POST', 'body' => array( 'grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => $config['clientId'], 'client_secret' => self::secret() ) ), 16000 );
		self::store_tokens( $tokens );
		return $tokens['access_token'];
	}

	public static function save(): void {
		self::allowed(); check_admin_referer( 'chidemoon_catalog_save' );
		$config = array();
		foreach ( array( 'origin', 'clientId', 'organizationId', 'organizationSlug', 'challenge' ) as $key ) { $config[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ); }
		$config['origin'] = rtrim( $config['origin'], '/' );
		$config['enabled'] = ! empty( $_POST['enabled'] );
		try { self::configuration( $config ); }
		catch ( Throwable $exception ) { wp_die( esc_html__( 'Invalid connector configuration.', 'chidemoon-catalog-sync' ), '', array( 'response' => 422 ) ); }
		$old = get_option( self::SETTINGS, array() );
		$changed = array_intersect_key( $old, array_flip( array( 'origin', 'clientId', 'organizationId', 'organizationSlug' ) ) ) !== array_intersect_key( $config, array_flip( array( 'origin', 'clientId', 'organizationId', 'organizationSlug' ) ) );
		if ( $changed && get_option( self::OUTBOX, array() ) ) { wp_die( esc_html__( 'Deliver the pending receipt before changing the connector.', 'chidemoon-catalog-sync' ), '', array( 'response' => 409 ) ); }
		update_option( self::SETTINGS, $config, false );
		if ( $changed ) { delete_option( self::TOKENS ); delete_option( 'chidemoon_catalog_oauth_state' ); }
		wp_clear_scheduled_hook( self::HOOK );
		if ( $config['enabled'] ) { wp_schedule_event( time() + 5, 'chidemoon_catalog_quarter_hour', self::HOOK ); }
		self::back();
	}

	public static function authorize(): void {
		self::allowed(); check_admin_referer( 'chidemoon_catalog_authorize' );
		try {
			$config = self::configuration(); self::secret();
			$verifier = rtrim( strtr( base64_encode( random_bytes( 48 ) ), '+/', '-_' ), '=' );
			$state = bin2hex( random_bytes( 32 ) );
			update_option( 'chidemoon_catalog_oauth_state', self::seal( array( 'state' => $state, 'verifier' => $verifier, 'userId' => get_current_user_id(), 'session' => wp_get_session_token(), 'expiresAt' => time() + 600 ) ), false );
			$url = $config['origin'] . '/oauth/authorize?' . http_build_query( array( 'response_type' => 'code', 'client_id' => $config['clientId'], 'redirect_uri' => self::callback_url(), 'scope' => implode( ' ', self::SCOPES ), 'state' => $state, 'code_challenge_method' => 'S256', 'code_challenge' => rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ) ) );
			wp_redirect( $url ); exit;
		} catch ( Throwable $exception ) { self::record_error( $exception ); self::back(); }
	}

	public static function callback(): void {
		self::allowed();
		try {
			$state = self::unseal( (string) get_option( 'chidemoon_catalog_oauth_state', '' ) );
			$incoming = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
			if ( ! hash_equals( $state['state'], $incoming ) || $state['userId'] !== get_current_user_id() || ! hash_equals( $state['session'], wp_get_session_token() ) || $state['expiresAt'] < time() ) { throw new DomainException( 'oauth_state_invalid' ); }
			delete_option( 'chidemoon_catalog_oauth_state' );
			$code = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
			if ( '' === $code || strlen( $code ) > 512 ) { throw new DomainException( 'authorization_required' ); }
			$config = self::configuration();
			$tokens = self::request( '/api/oauth2/token', array( 'method' => 'POST', 'body' => array( 'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::callback_url(), 'client_id' => $config['clientId'], 'client_secret' => self::secret(), 'code_verifier' => $state['verifier'] ) ), 16000 );
			self::store_tokens( $tokens );
			$status = get_option( self::STATUS, array() ); unset( $status['error'], $status['blocked'] ); $status['attempts'] = 0; update_option( self::STATUS, $status, false );
		} catch ( Throwable $exception ) { self::record_error( $exception ); }
		self::back();
	}

	public static function refresh(): void {
		self::allowed(); check_admin_referer( 'chidemoon_catalog_refresh' );
		$status = get_option( self::STATUS, array() ); unset( $status['blocked'] ); $status['attempts'] = 0; update_option( self::STATUS, $status, false );
		if ( ! wp_next_scheduled( self::HOOK, array( 'manual' ) ) ) { wp_schedule_single_event( time() + 1, self::HOOK, array( 'manual' ) ); }
		self::back();
	}

	private static function back(): void { wp_safe_redirect( admin_url( 'admin.php?page=chidemoon-catalog-sync' ) ); exit; }

	private static function record_error( Throwable $exception ): void {
		$status = get_option( self::STATUS, array() );
		$status['error'] = preg_match( '/^[a-z_]{1,80}$/D', $exception->getMessage() ) ? $exception->getMessage() : 'import_rejected';
		$status['attempts'] = (int) ( $status['attempts'] ?? 0 ) + 1;
		$status['blocked'] = $exception instanceof DomainException || $exception instanceof InvalidArgumentException || $status['attempts'] >= 6;
		$status['lastFailureAt'] = gmdate( 'c' );
		if ( ! $status['blocked'] ) {
			$delay = min( 3600, 60 * ( 2 ** ( $status['attempts'] - 1 ) ) );
			$status['nextAttemptAt'] = time() + $delay;
			if ( ! wp_next_scheduled( self::HOOK, array( 'retry' ) ) ) { wp_schedule_single_event( time() + $delay, self::HOOK, array( 'retry' ) ); }
		}
		update_option( self::STATUS, $status, false );
	}

	private static function flush_receipts( string $token ): void {
		$outbox = get_option( self::OUTBOX, array() );
		while ( $outbox ) {
			$receipt = $outbox[0];
			$response = self::request( '/api/integrations/catalog/v2/source-receipts', array( 'method' => 'POST', 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $receipt ) ), 16000 );
			if ( true !== ( $response['imported'] ?? null ) || false !== ( $response['publicationConfirmed'] ?? null ) ) { throw new DomainException( 'receipt_not_confirmed' ); }
			array_shift( $outbox ); update_option( self::OUTBOX, $outbox, false );
		}
	}

	public static function poll( string $reason = '' ): void {
		global $wpdb;
		$lock = 'chidemoon_catalog_' . hash( 'sha256', $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $lock ) ) ) { return; }
		try {
			$config = self::configuration(); $status = get_option( self::STATUS, array() );
			if ( ( empty( $config['enabled'] ) && ! in_array( $reason, array( 'manual', 'retry' ), true ) ) || ! empty( $status['blocked'] ) ) { return; }
			if ( 'manual' !== $reason && (int) ( $status['nextAttemptAt'] ?? 0 ) > time() ) { return; }
			if ( ! class_exists( 'Chidemoon_Core_Source_Importer' ) ) { throw new DomainException( 'source_importer_unavailable' ); }
			$status['lastAttemptAt'] = gmdate( 'c' ); update_option( self::STATUS, $status, false );
			$token = self::access_token(); self::flush_receipts( $token );
			$snapshot = self::request( '/api/integrations/catalog/v2/source-snapshot', array( 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) );
			if ( ( $snapshot['organization']['id'] ?? null ) !== $config['organizationId'] || ( $snapshot['organization']['slug'] ?? null ) !== $config['organizationSlug'] ) { throw new DomainException( 'organization_mismatch' ); }
			$report = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, $config['organizationSlug'] );
			$outbox = array();
			foreach ( array_chunk( $report['records'], 1000 ) as $records ) { $outbox[] = array( 'snapshotId' => $snapshot['snapshot']['id'], 'snapshotRevision' => $snapshot['snapshot']['revision'], 'records' => $records ); }
			update_option( self::OUTBOX, $outbox, false );
			$status['lastImportAt'] = gmdate( 'c' ); $status['report'] = $report; $status['snapshotId'] = $snapshot['snapshot']['id']; update_option( self::STATUS, $status, false );
			// Media imports can outlast the access token; renew before acknowledging.
			self::flush_receipts( self::access_token() );
			if ( $report['failed'] ) {
				$issues = array_merge( ...array_map( static fn( $record ) => $record['issues'] ?? array(), $report['records'] ) );
				if ( array_diff( $issues, array( 'media_failed', 'persistence_failed' ) ) ) { throw new DomainException( 'records_require_review' ); }
				throw new RuntimeException( 'records_require_retry' );
			}
			$status['lastSuccessAt'] = gmdate( 'c' ); $status['attempts'] = 0; $status['blocked'] = false; unset( $status['error'], $status['nextAttemptAt'] ); update_option( self::STATUS, $status, false );
		} catch ( Throwable $exception ) { self::record_error( $exception ); }
		finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}

	public static function page(): void {
		self::allowed(); $config = get_option( self::SETTINGS, array() ); $status = get_option( self::STATUS, array() );
		echo '<div class="wrap"><h1>' . esc_html__( 'Source synchronization', 'chidemoon-catalog-sync' ) . '</h1><p>' . esc_html__( 'Import approved source offers every 15 minutes. Chidemoon retains editorial facts, Looks and publication approval.', 'chidemoon-catalog-sync' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="chidemoon_catalog_save">';
		wp_nonce_field( 'chidemoon_catalog_save' );
		foreach ( array( 'origin' => 'Kalahamoon HTTPS origin', 'clientId' => 'Client ID', 'organizationId' => 'Organization ID', 'organizationSlug' => 'Organization slug', 'challenge' => 'Origin proof challenge' ) as $key => $label ) { echo '<p><label>' . esc_html__( $label, 'chidemoon-catalog-sync' ) . '<br><input class="regular-text" required name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) ( $config[ $key ] ?? '' ) ) . '"></label></p>'; }
		echo '<p><label><input type="checkbox" name="enabled" value="1" ' . checked( ! empty( $config['enabled'] ), true, false ) . '> ' . esc_html__( 'Enable automatic synchronization', 'chidemoon-catalog-sync' ) . '</label></p>';
		submit_button(); echo '</form><p>' . esc_html__( 'Configure CHIDEMOON_CATALOG_CLIENT_SECRET in the host environment. Tokens are encrypted with WordPress salts.', 'chidemoon-catalog-sync' ) . '</p><p>' . esc_html__( 'OAuth callback:', 'chidemoon-catalog-sync' ) . ' <code>' . esc_html( self::callback_url() ) . '</code></p>';
		foreach ( array( 'authorize' => 'Authorize source access', 'refresh' => 'Refresh / retry in background' ) as $action => $label ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="chidemoon_catalog_' . esc_attr( $action ) . '">'; wp_nonce_field( 'chidemoon_catalog_' . $action ); submit_button( __( $label, 'chidemoon-catalog-sync' ), 'secondary' ); echo '</form>'; }
		echo '<h2>' . esc_html__( 'Import status', 'chidemoon-catalog-sync' ) . '</h2><pre>' . esc_html( wp_json_encode( $status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</pre><p>' . esc_html__( 'Pending receipt batches:', 'chidemoon-catalog-sync' ) . ' ' . count( get_option( self::OUTBOX, array() ) ) . '</p></div>';
	}
}
