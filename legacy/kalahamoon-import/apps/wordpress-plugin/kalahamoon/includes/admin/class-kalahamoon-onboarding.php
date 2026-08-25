<?php
/**
 * Kalahamoon Onboarding & connection-status pill.
 *
 * Additive features that sit alongside Kalahamoon_Admin without touching its 1.4k
 * lines of legacy code:
 *  - First-run welcome banner on Kalahamoon admin screens until the API key is set
 *  - Admin-bar pill showing live Kalahamoon connectivity (green/amber/red)
 *  - Dismissible cross-screen reminder when configuration is incomplete
 *
 * Connectivity is cached for 5 minutes to avoid hammering the Kalahamoon API
 * on every page load.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Onboarding {

	const STATUS_TRANSIENT = 'kalahamoon_connection_status';
	const STATUS_TTL       = 5 * MINUTE_IN_SECONDS;
	const DISMISS_META_KEY = 'kalahamoon_onboarding_dismissed';

	public static function init(): void {
		add_action( 'admin_notices',         array( __CLASS__, 'render_welcome_banner' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_bar_menu',        array( __CLASS__, 'add_admin_bar_pill' ), 100 );
		add_action( 'wp_ajax_kalahamoon_onboarding_dismiss', array( __CLASS__, 'ajax_dismiss' ) );
		add_action( 'wp_ajax_kalahamoon_onboarding_status',  array( __CLASS__, 'ajax_status' ) );
		add_action( 'kalahamoon_connection_state_changed', array( __CLASS__, 'clear_cached_status' ) );
	}

	public static function enqueue_assets( string $hook ): void {
		// Always load the small admin-bar styles (the pill is global).
		wp_register_style( 'kalahamoon-onboarding', false, array(), KALAHAMOON_VERSION );
		wp_enqueue_style( 'kalahamoon-onboarding' );
		wp_add_inline_style( 'kalahamoon-onboarding', self::admin_bar_css() );

		// Onboarding banner script only on Kalahamoon screens or dashboard.
		if ( ! self::is_relevant_screen( $hook ) ) {
			return;
		}
		wp_register_script( 'kalahamoon-onboarding', false, array( 'jquery' ), KALAHAMOON_VERSION, true );
		wp_enqueue_script( 'kalahamoon-onboarding' );
		wp_add_inline_script( 'kalahamoon-onboarding', self::banner_js() );
		wp_localize_script( 'kalahamoon-onboarding', 'kalahamoonOnboarding', array(
			'nonce' => wp_create_nonce( 'kalahamoon_onboarding' ),
			'ajax'  => admin_url( 'admin-ajax.php' ),
		) );
	}

	public static function render_welcome_banner(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( in_array( $page, array( 'kalahamoon-settings', 'kalahamoon-setting', 'salam-settings' ), true ) ) return;

		// Hide once the site is connected by EITHER auth method. OAuth is the
		// primary flow, so checking only the legacy API key kept nagging
		// correctly-connected sites.
		if ( self::is_connected() ) return;

		$dismissed = (bool) get_user_meta( get_current_user_id(), self::DISMISS_META_KEY, true );
		if ( $dismissed ) return;

		$settings_url = admin_url( 'admin.php?page=kalahamoon-setting' );
		?>
		<div class="notice notice-info kalahamoon-onboarding-banner" data-dismissible>
			<button type="button" class="notice-dismiss kalahamoon-onboarding-banner-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'بستن', 'kalahamoon' ); ?></span></button>
			<div class="kalahamoon-onboarding-banner-grid">
				<div class="kalahamoon-onboarding-banner-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="36" height="36"><path fill="currentColor" d="M12 2 4 6v6c0 5 4 8 8 9 4-1 8-4 8-9V6l-8-3Z"/></svg>
				</div>
				<div class="kalahamoon-onboarding-banner-text">
					<h2><?php esc_html_e( 'Welcome to Kalahamoon!', 'kalahamoon' ); ?></h2>
					<p><?php esc_html_e( 'Connect this WordPress site with your Kalahamoon account to activate blocks and product sync.', 'kalahamoon' ); ?></p>
					<ol class="kalahamoon-onboarding-steps">
						<li><?php esc_html_e( 'Open the Kalahamoon plugin settings page.', 'kalahamoon' ); ?></li>
						<li><?php esc_html_e( 'Click “Connect with Kalahamoon” and log in to your Kalahamoon account.', 'kalahamoon' ); ?></li>
						<li><?php esc_html_e( 'Approve the connection — after returning to WordPress, you are ready.', 'kalahamoon' ); ?></li>
					</ol>
					<p class="kalahamoon-onboarding-banner-actions">
						<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary"><?php esc_html_e( 'Open Settings', 'kalahamoon' ); ?></a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	public static function add_admin_bar_pill( $wp_admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) return;
		if ( ! is_object( $wp_admin_bar ) ) return;

		$status   = self::get_cached_status();
		$label    = self::label_for( $status['state'] );
		$class    = 'kalahamoon-bar-pill kalahamoon-bar-pill-' . $status['state'];
		$tooltip  = sprintf( __( 'آخرین بررسی: %s', 'kalahamoon' ), human_time_diff( $status['checked_at'] ) . ' ' . __( 'قبل', 'kalahamoon' ) );

		$wp_admin_bar->add_node( array(
			'id'    => 'kalahamoon-status',
			'title' => '<span class="' . esc_attr( $class ) . '" title="' . esc_attr( $tooltip ) . '">'
			           . '<span class="kalahamoon-bar-dot" aria-hidden="true"></span> '
			           . esc_html( $label )
			           . '</span>',
			'href'  => admin_url( 'admin.php?page=kalahamoon' ),
			'meta'  => array( 'title' => $tooltip ),
		) );
	}

	public static function ajax_dismiss(): void {
		check_ajax_referer( 'kalahamoon_onboarding', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		update_user_meta( get_current_user_id(), self::DISMISS_META_KEY, 1 );
		wp_send_json_success();
	}

	public static function ajax_status(): void {
		check_ajax_referer( 'kalahamoon_onboarding', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		self::clear_cached_status();
		wp_send_json_success( self::get_cached_status() );
	}

	public static function clear_cached_status(): void {
		delete_transient( self::STATUS_TRANSIENT );
	}

	/* ------------------------------------------------------------------ */

	/**
	 * @return array{state:string,checked_at:int,detail:string}
	 */
	private static function get_cached_status(): array {
		$cached = get_transient( self::STATUS_TRANSIENT );
		if ( is_array( $cached ) ) return $cached;

		$status = self::probe_status();
		set_transient( self::STATUS_TRANSIENT, $status, self::STATUS_TTL );
		return $status;
	}

	/**
	 * Probe through the API client so the badge follows the same credential and
	 * endpoint selection as the settings page, including filtered connections.
	 *
	 * @return array{state:string,checked_at:int,detail:string}
	 */
	private static function probe_status(): array {
		$now = time();

		if ( ! class_exists( 'Kalahamoon_API_Client' ) ) {
			return array( 'state' => 'unknown', 'checked_at' => $now, 'detail' => 'API client missing' );
		}

		$client = new Kalahamoon_API_Client();
		if ( ! $client->is_connected() ) {
			return array( 'state' => 'unconfigured', 'checked_at' => $now, 'detail' => 'Not connected' );
		}

		$result = $client->test_connection();
		if ( ! is_wp_error( $result ) ) {
			return array( 'state' => 'ok', 'checked_at' => $now, 'detail' => 'Live' );
		}

		$code = (int) ( $result->get_error_data()['status'] ?? 0 );
		if ( 401 === $code ) return array( 'state' => 'invalid', 'checked_at' => $now, 'detail' => 'Invalid credentials' );
		if ( 403 === $code ) return array( 'state' => 'invalid', 'checked_at' => $now, 'detail' => 'Insufficient scope' );
		if ( 0 === $code ) return array( 'state' => 'down', 'checked_at' => $now, 'detail' => $result->get_error_message() );

		return array( 'state' => 'degraded', 'checked_at' => $now, 'detail' => 'HTTP ' . $code );
	}

	/**
	 * Whether the site is connected to Kalahamoon by either OAuth (preferred)
	 * or a legacy API key.
	 */
	private static function is_connected(): bool {
		if ( class_exists( 'Kalahamoon_API_Client' ) && ( new Kalahamoon_API_Client() )->is_connected() ) {
			return true;
		}
		if ( class_exists( 'Kalahamoon_Auth' ) && Kalahamoon_Auth::is_connected() ) {
			return true;
		}
		return '' !== (string) get_option( 'kalahamoon_api_key', '' );
	}

	private static function label_for( string $state ): string {
		switch ( $state ) {
			case 'ok':           return __( 'Kalahamoon: connected', 'kalahamoon' );
			case 'unconfigured': return __( 'Kalahamoon: not configured', 'kalahamoon' );
			case 'invalid':      return __( 'Kalahamoon: invalid key', 'kalahamoon' );
			case 'degraded':     return __( 'Kalahamoon: slow', 'kalahamoon' );
			case 'down':         return __( 'Kalahamoon: disconnected', 'kalahamoon' );
			default:             return __( 'Kalahamoon: unknown', 'kalahamoon' );
		}
	}

	private static function is_relevant_screen( string $hook ): bool {
		// Kalahamoon admin pages all start with "toplevel_page_kalahamoon" or "kalahamoon_page_*"
		return ( false !== strpos( $hook, 'kalahamoon' ) ) || ( 'index.php' === $hook );
	}

	private static function admin_bar_css(): string {
		return <<<CSS
#wpadminbar .kalahamoon-bar-pill {
	display: inline-flex; align-items: center; gap: 6px; padding: 0 10px;
	border-radius: 999px; line-height: 1.6; font-weight: 600;
}
#wpadminbar .kalahamoon-bar-dot { width: 8px; height: 8px; border-radius: 50%; }
#wpadminbar .kalahamoon-bar-pill-ok          { background: rgba(34,197,94,0.18); color: #16a34a; }
#wpadminbar .kalahamoon-bar-pill-ok .kalahamoon-bar-dot          { background: #16a34a; box-shadow: 0 0 0 3px rgba(34,197,94,0.25); }
#wpadminbar .kalahamoon-bar-pill-unconfigured{ background: rgba(245,158,11,0.18); color: #d97706; }
#wpadminbar .kalahamoon-bar-pill-unconfigured .kalahamoon-bar-dot{ background: #d97706; box-shadow: 0 0 0 3px rgba(245,158,11,0.25); }
#wpadminbar .kalahamoon-bar-pill-invalid,
#wpadminbar .kalahamoon-bar-pill-down,
#wpadminbar .kalahamoon-bar-pill-degraded { background: rgba(220,38,38,0.18); color: #dc2626; }
#wpadminbar .kalahamoon-bar-pill-invalid .kalahamoon-bar-dot,
#wpadminbar .kalahamoon-bar-pill-down .kalahamoon-bar-dot,
#wpadminbar .kalahamoon-bar-pill-degraded .kalahamoon-bar-dot { background: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,0.25); }
#wpadminbar .kalahamoon-bar-pill-unknown { background: rgba(100,116,139,0.18); color: #475569; }
#wpadminbar .kalahamoon-bar-pill-unknown .kalahamoon-bar-dot { background: #475569; }

.kalahamoon-onboarding-banner { border-inline-start-width: 4px; border-inline-start-color: #b94a24; padding: 16px 20px !important; }
.rtl .kalahamoon-onboarding-banner, [dir="rtl"] .kalahamoon-onboarding-banner { direction: ltr; text-align: left; }
.kalahamoon-onboarding-banner-grid { display: grid; grid-template-columns: 56px 1fr; gap: 16px; align-items: start; }
.rtl .kalahamoon-onboarding-banner-grid, [dir="rtl"] .kalahamoon-onboarding-banner-grid { grid-template-columns: 56px 1fr; }
.rtl .kalahamoon-onboarding-banner-icon, [dir="rtl"] .kalahamoon-onboarding-banner-icon { grid-column: 1; grid-row: 1; }
.rtl .kalahamoon-onboarding-banner-text, [dir="rtl"] .kalahamoon-onboarding-banner-text { grid-column: 2; grid-row: 1; }
.kalahamoon-onboarding-banner-icon { color: #b94a24; }
.kalahamoon-onboarding-banner h2 { margin: 0 0 6px; font-size: 1.15rem; }
.kalahamoon-onboarding-steps { margin: 6px 0 12px; padding-inline-start: 20px; }
.kalahamoon-onboarding-banner-actions { margin: 0; }
.kalahamoon-onboarding-banner-actions .button { margin-inline-end: 6px; }
CSS;
	}

	private static function banner_js(): string {
		return <<<JS
(function () {
	var dismiss = document.querySelector('.kalahamoon-onboarding-banner-dismiss');
	if (!dismiss) return;
	dismiss.addEventListener('click', function () {
		var banner = dismiss.closest('.kalahamoon-onboarding-banner');
		if (banner) banner.style.display = 'none';
		var body = new FormData();
		body.append('action', 'kalahamoon_onboarding_dismiss');
		body.append('nonce', kalahamoonOnboarding.nonce);
		fetch(kalahamoonOnboarding.ajax, { method: 'POST', credentials: 'same-origin', body: body });
	});
})();
JS;
	}
}
