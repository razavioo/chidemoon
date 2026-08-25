<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Local WordPress changelog and post-upgrade notice.
 */
class Kalahamoon_Changelog {

	const SEEN_META_KEY       = 'kalahamoon_changelog_seen_version';
	const NOTICE_OPTION       = 'kalahamoon_changelog_notice_version';
	const DISMISSED_META_KEY  = 'kalahamoon_changelog_dismissed_version';
	const DISMISS_NONCE       = 'kalahamoon_changelog_dismiss';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_upgrade_notice' ) );
		add_action( 'wp_ajax_kalahamoon_changelog_dismiss', array( __CLASS__, 'ajax_dismiss' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'kalahamoon',
			__( 'What\'s New', 'kalahamoon' ),
			__( 'What\'s New', 'kalahamoon' ),
			'edit_posts',
			'kalahamoon-changelog',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function releases(): array {
		return apply_filters( 'kalahamoon_changelog_entries', array(
			array(
				'version' => KALAHAMOON_VERSION,
				'date'    => '2026-07-08',
				'title'   => __( 'Professional release notes for WordPress', 'kalahamoon' ),
				'summary' => __( 'Kalahamoon now includes a local What\'s New page, update notices, and version consistency checks for professional releases.', 'kalahamoon' ),
				'items'   => array(
					'added' => array(
						__( 'A dedicated WordPress admin changelog page under the Kalahamoon menu.', 'kalahamoon' ),
						__( 'Per-user update notice dismissal so each editor can review new plugin versions once.', 'kalahamoon' ),
						__( 'A WordPress.org-compatible readme changelog with a stable tag matching the plugin version.', 'kalahamoon' ),
					),
					'changed' => array(
						__( 'Release links point back to the Kalahamoon panel changelog when the site is connected.', 'kalahamoon' ),
					),
				),
			),
		) );
	}

	public static function render_upgrade_notice(): void {
		if ( ! current_user_can( 'edit_posts' ) ) return;
		$notice_version = (string) get_option( self::NOTICE_OPTION, '' );
		if ( '' === $notice_version || KALAHAMOON_VERSION !== $notice_version ) return;

		$dismissed = (string) get_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, true );
		if ( KALAHAMOON_VERSION === $dismissed ) return;

		$url = admin_url( 'admin.php?page=kalahamoon-changelog' );
		?>
		<div class="notice notice-info kalahamoon-changelog-notice" data-version="<?php echo esc_attr( KALAHAMOON_VERSION ); ?>">
			<button type="button" class="notice-dismiss kalahamoon-changelog-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'kalahamoon' ); ?></span></button>
			<p>
				<strong><?php printf( esc_html__( 'Kalahamoon updated to %s.', 'kalahamoon' ), esc_html( KALAHAMOON_VERSION ) ); ?></strong>
				<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'View what\'s new', 'kalahamoon' ); ?></a>
			</p>
			<script>
			(function(){
				var button = document.querySelector('.kalahamoon-changelog-dismiss');
				if (!button) return;
				button.addEventListener('click', function(){
					var notice = button.closest('.kalahamoon-changelog-notice');
					if (notice) notice.style.display = 'none';
					var body = new FormData();
					body.append('action', 'kalahamoon_changelog_dismiss');
					body.append('nonce', '<?php echo esc_js( wp_create_nonce( self::DISMISS_NONCE ) ); ?>');
					fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body });
				});
			})();
			</script>
		</div>
		<?php
	}

	public static function ajax_dismiss(): void {
		check_ajax_referer( self::DISMISS_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		update_user_meta( get_current_user_id(), self::DISMISSED_META_KEY, KALAHAMOON_VERSION );
		update_user_meta( get_current_user_id(), self::SEEN_META_KEY, KALAHAMOON_VERSION );
		wp_send_json_success();
	}

	public static function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) return;
		update_user_meta( get_current_user_id(), self::SEEN_META_KEY, KALAHAMOON_VERSION );

		$direction = Kalahamoon_RTL::admin_direction();
		$language  = Kalahamoon_RTL::admin_language();
		$panel_url = rtrim( (string) get_option( 'kalahamoon_api_url', kalahamoon_default_api_url() ), '/' );
		$panel_changelog = $panel_url . '/fa/changelog?product=WORDPRESS_PLUGIN';
		?>
		<div class="wrap kalahamoon-changelog" dir="<?php echo esc_attr( $direction ); ?>" lang="<?php echo esc_attr( $language ); ?>">
			<h1><?php esc_html_e( 'Kalahamoon — What\'s New', 'kalahamoon' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Versioned release notes for this WordPress plugin. These notes work offline and link to the panel changelog when your site is connected.', 'kalahamoon' ); ?></p>
			<p><a class="button button-secondary" href="<?php echo esc_url( $panel_changelog ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open panel changelog', 'kalahamoon' ); ?></a></p>

			<div class="kalahamoon-changelog-timeline">
				<?php foreach ( self::releases() as $release ) : ?>
					<section class="kalahamoon-changelog-card">
						<div class="kalahamoon-changelog-card-header">
							<span class="kalahamoon-changelog-version">v<?php echo esc_html( (string) $release['version'] ); ?></span>
							<span class="kalahamoon-changelog-date"><?php echo esc_html( (string) $release['date'] ); ?></span>
						</div>
						<h2><?php echo esc_html( (string) $release['title'] ); ?></h2>
						<p><?php echo esc_html( (string) $release['summary'] ); ?></p>
						<?php foreach ( (array) $release['items'] as $category => $items ) : ?>
							<h3><?php echo esc_html( ucfirst( (string) $category ) ); ?></h3>
							<ul>
								<?php foreach ( (array) $items as $item ) : ?>
									<li><?php echo esc_html( (string) $item ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endforeach; ?>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
