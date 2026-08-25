<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Admin {

	public static function init(): void {
		$consumer = self::is_catalog_consumer();

		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_pages' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_dashboard_widget' ) );
		if ( ! $consumer ) {
			add_action( 'admin_init', array( __CLASS__, 'handle_csv_export' ) );
			add_action( 'admin_post_kalahamoon_save_product', array( __CLASS__, 'handle_product_save' ) );
			add_action( 'admin_post_kalahamoon_delete_product', array( __CLASS__, 'handle_product_delete' ) );
			add_action( 'admin_post_kalahamoon_assign_product_collection', array( __CLASS__, 'handle_product_collection_assignment' ) );
		}

		// The host scheduler explicitly invokes one of these hooks. Connector mode
		// never schedules either hook through visitor-driven WP-Cron.
		add_action( 'kalahamoon_sync_products', array( __CLASS__, 'cron_sync' ) );
		add_action( 'kalahamoon_catalog_consumer_sync', array( __CLASS__, 'run_catalog_consumer_sync' ) );

		// Custom cron interval
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );

		// AJAX handlers
		add_action( 'wp_ajax_kalahamoon_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_kalahamoon_sync_now', array( __CLASS__, 'ajax_sync_now' ) );
		add_action( 'wp_ajax_kalahamoon_oauth_disconnect', array( __CLASS__, 'ajax_oauth_disconnect' ) );
		if ( ! $consumer ) {
			add_action( 'wp_ajax_kalahamoon_add_auto_link', array( __CLASS__, 'ajax_add_auto_link' ) );
			add_action( 'wp_ajax_kalahamoon_delete_auto_link', array( __CLASS__, 'ajax_delete_auto_link' ) );
			add_action( 'wp_ajax_kalahamoon_toggle_auto_link', array( __CLASS__, 'ajax_toggle_auto_link' ) );
			add_action( 'wp_ajax_kalahamoon_check_link_health', array( __CLASS__, 'ajax_check_link_health' ) );
		}
	}

	public static function register_menus(): void {
		$consumer = self::is_catalog_consumer();
		add_menu_page(
			__( 'Kalahamoon', 'kalahamoon' ),
			__( 'Kalahamoon', 'kalahamoon' ),
			'manage_options',
			'kalahamoon',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-store',
			30
		);

		add_submenu_page( 'kalahamoon', __( 'Dashboard', 'kalahamoon' ), __( 'Dashboard', 'kalahamoon' ), 'manage_options', 'kalahamoon', array( __CLASS__, 'render_dashboard_page' ) );
		add_submenu_page( 'kalahamoon', __( 'Products', 'kalahamoon' ), __( 'Products', 'kalahamoon' ), 'manage_options', 'kalahamoon-products', array( __CLASS__, 'render_products_page' ) );
		if ( ! $consumer ) {
			add_submenu_page( null, __( 'Edit Product', 'kalahamoon' ), __( 'Edit Product', 'kalahamoon' ), 'manage_options', 'kalahamoon-product-editor', array( __CLASS__, 'render_product_editor_page' ) );
			add_submenu_page( 'kalahamoon', __( 'AI Image Studio', 'kalahamoon' ), __( 'AI Image Studio', 'kalahamoon' ), 'upload_files', 'kalahamoon-ai-studio', array( __CLASS__, 'render_ai_studio_page' ) );
		}
		if ( ! $consumer ) {
			add_submenu_page( 'kalahamoon', __( 'Analytics', 'kalahamoon' ), __( 'Analytics', 'kalahamoon' ), 'manage_options', 'kalahamoon-analytics', array( __CLASS__, 'render_analytics_page' ) );
			add_submenu_page( 'kalahamoon', __( 'Affiliate Links', 'kalahamoon' ), __( 'Affiliate Links', 'kalahamoon' ), 'manage_options', 'kalahamoon-links', array( __CLASS__, 'render_links_page' ) );
			add_submenu_page( 'kalahamoon', __( 'Auto Links', 'kalahamoon' ), __( 'Auto Links', 'kalahamoon' ), 'manage_options', 'kalahamoon-auto-links', array( __CLASS__, 'render_auto_links_page' ) );
		}
		add_submenu_page( 'kalahamoon', __( 'Settings', 'kalahamoon' ), __( 'Settings', 'kalahamoon' ), 'manage_options', 'kalahamoon-setting', array( __CLASS__, 'render_settings_page' ) );
	}

	public static function redirect_legacy_pages(): void {
		if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
			return;
		}

		$legacy_pages = array(
			'salam'            => 'kalahamoon',
			'salam-products'   => 'kalahamoon-products',
			'salam-analytics'  => 'kalahamoon-analytics',
			'salam-links'      => 'kalahamoon-links',
			'salam-auto-links' => 'kalahamoon-auto-links',
			'salam-settings'      => 'kalahamoon-setting',
			'kalahamoon-settings' => 'kalahamoon-setting',
			'salam-help'          => 'kalahamoon-help',
		);

		$page = sanitize_key( wp_unslash( $_GET['page'] ) );
		if ( ! isset( $legacy_pages[ $page ] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$args = wp_unslash( $_GET );
		$args['page'] = $legacy_pages[ $page ];

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ), 301 );
		exit;
	}

	// -------------------------------------------------------------------------
	// Dashboard
	// -------------------------------------------------------------------------

	public static function render_dashboard_page(): void {
		if ( self::is_catalog_consumer() ) {
			self::render_catalog_consumer_page();
			return;
		}

		global $wpdb;

		$products_total  = Kalahamoon_Product_Cache::get_all( array( 'limit' => 1 ) )['total'];
		$public_products = Kalahamoon_Product_Cache::public_ready_count();
		$clicks_7d       = Kalahamoon_Click_Tracker::get_stats( 7 )['total'];
		$links_total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}kalahamoon_affiliate_links" );
		$auto_links      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}kalahamoon_auto_links WHERE is_active = 1" );
		$last_sync       = get_option( 'kalahamoon_last_sync', '' );

		$oauth_connected = class_exists( 'Kalahamoon_Token_Store' ) && Kalahamoon_Token_Store::is_connected();
		$user_info       = $oauth_connected ? Kalahamoon_Token_Store::get_user_info() : null;

		$settings_url    = admin_url( 'admin.php?page=kalahamoon-setting' );
		$products_url    = admin_url( 'admin.php?page=kalahamoon-products' );
		$auto_links_url  = admin_url( 'admin.php?page=kalahamoon-auto-links' );
		$digikala_capabilities = null;
		if ( $oauth_connected ) {
			$digikala_result = ( new Kalahamoon_API_Client() )->get_digikala_capabilities();
			if ( ! is_wp_error( $digikala_result ) && ! empty( $digikala_result['success'] ) ) {
				$digikala_capabilities = $digikala_result;
			}
		}

		$direction = Kalahamoon_RTL::admin_direction();
		$language  = Kalahamoon_RTL::admin_language();
		?>
		<div class="wrap kalahamoon-dash" dir="<?php echo esc_attr( $direction ); ?>" lang="<?php echo esc_attr( $language ); ?>">

			<h1><?php esc_html_e( 'Kalahamoon Dashboard', 'kalahamoon' ); ?></h1>

			<?php if ( ! $oauth_connected ) : ?>
				<div class="kalahamoon-dash-connect-hero">
					<div>
						<h2><?php esc_html_e( 'Connect to Kalahamoon', 'kalahamoon' ); ?></h2>
						<p><?php esc_html_e( 'Link this WordPress site to your Kalahamoon account to sync products, track affiliate clicks, and capture leads — all from one dashboard.', 'kalahamoon' ); ?></p>
					</div>
					<a href="<?php echo esc_url( Kalahamoon_Auth::get_authorization_url() ); ?>" class="kalahamoon-dash-connect-btn">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
						<?php esc_html_e( 'Connect with Kalahamoon', 'kalahamoon' ); ?>
					</a>
				</div>
			<?php else : ?>
				<div class="kalahamoon-dash-status">
					<span class="kalahamoon-dash-status-dot"></span>
					<div class="kalahamoon-dash-status-text">
						<strong><?php esc_html_e( 'Connected to Kalahamoon', 'kalahamoon' ); ?></strong>
						<?php if ( $user_info && ! empty( $user_info['org_name'] ) ) : ?>
							· <?php echo esc_html( $user_info['email'] ); ?>
							· <?php echo esc_html( $user_info['org_name'] ); ?>
						<?php endif; ?>
					</div>
					<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Manage', 'kalahamoon' ); ?></a>
				</div>
			<?php endif; ?>

			<section class="kalahamoon-dash-readiness <?php echo $public_products > 0 ? 'is-ready' : 'is-blocked'; ?>" aria-labelledby="kalahamoon-public-readiness">
				<div>
					<p class="kalahamoon-dash-readiness__eyebrow"><?php esc_html_e( 'Public site readiness', 'kalahamoon' ); ?></p>
					<h2 id="kalahamoon-public-readiness"><?php echo esc_html( $public_products > 0 ? __( 'Verified products can appear on the public catalog.', 'kalahamoon' ) : __( 'The public catalog is not ready to show products.', 'kalahamoon' ) ); ?></h2>
					<p><?php echo esc_html( $public_products > 0 ? sprintf( _n( '%s verified product currently satisfies the public checks.', '%s verified products currently satisfy the public checks.', $public_products, 'kalahamoon' ), number_format_i18n( $public_products ) ) : __( 'A product can be synced without being public. Complete its publishing, listing, image, price, and freshness checks before sending visitors to the catalog.', 'kalahamoon' ) ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( $products_url ); ?>"><?php esc_html_e( 'Review publishing readiness', 'kalahamoon' ); ?></a>
			</section>

			<div class="kalahamoon-dash-stats">
				<?php
				$stat_cards = array(
					array(
						'label' => __( 'Synced products', 'kalahamoon' ),
						'value' => $products_total,
						'url'   => $products_url,
						'icon'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
					),
					array(
						'label' => __( 'Clicks (7d)', 'kalahamoon' ),
						'value' => $clicks_7d,
						'url'   => admin_url( 'admin.php?page=kalahamoon-analytics' ),
						'icon'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l4-4 4 4 5-5"/></svg>',
					),
					array(
						'label' => __( 'Affiliate Links', 'kalahamoon' ),
						'value' => $links_total,
						'url'   => admin_url( 'admin.php?page=kalahamoon-links' ),
						'icon'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
					),
					array(
						'label' => __( 'Auto Links', 'kalahamoon' ),
						'value' => $auto_links,
						'url'   => $auto_links_url,
						'icon'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
					),
				);
				foreach ( $stat_cards as $card ) :
				?>
				<a href="<?php echo esc_url( $card['url'] ); ?>" class="kalahamoon-stat-card">
					<div class="kalahamoon-stat-icon"><?php echo $card['icon']; ?></div>
					<div class="kalahamoon-stat-value"><?php echo esc_html( Kalahamoon_RTL::format_number( $card['value'] ) ); ?></div>
					<div class="kalahamoon-stat-label"><?php echo esc_html( $card['label'] ); ?></div>
				</a>
				<?php endforeach; ?>
			</div>

			<div class="kalahamoon-dash-grid">

				<!-- Getting started checklist -->
				<div class="kalahamoon-dash-panel">
					<h2><?php esc_html_e( 'Getting Started', 'kalahamoon' ); ?></h2>
					<?php
					$steps = array(
						array(
							'done'  => $oauth_connected,
							'label' => __( 'Connect your Kalahamoon account', 'kalahamoon' ),
							'url'   => $settings_url,
						),
						array(
							'done'  => $products_total > 0,
							'label' => __( 'Sync source products', 'kalahamoon' ),
							'url'   => $settings_url,
						),
						array(
							'done'  => $public_products > 0,
							'label' => __( 'Approve products for the public catalog', 'kalahamoon' ),
							'url'   => $products_url,
						),
						array(
							'done'  => $links_total > 0,
							'label' => __( 'Create affiliate links', 'kalahamoon' ),
							'url'   => admin_url( 'admin.php?page=kalahamoon-links' ),
						),
						array(
							'done'  => $auto_links > 0,
							'label' => __( 'Set up auto-linking', 'kalahamoon' ),
							'url'   => $auto_links_url,
						),
					);
					$done_count = count( array_filter( $steps, fn( $s ) => $s['done'] ) );
					$total_steps = count( $steps );
					$pct = $total_steps ? round( $done_count / $total_steps * 100 ) : 0;
					?>
					<div class="kalahamoon-progress-bar">
						<div class="kalahamoon-progress-fill" style="width:<?php echo $pct; ?>%"></div>
					</div>
					<ul style="margin:0;padding:0;list-style:none">
					<?php foreach ( $steps as $step ) : ?>
						<li class="kalahamoon-step">
							<?php if ( $step['done'] ) : ?>
								<span class="kalahamoon-step-icon done">
									<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
								</span>
							<?php else : ?>
								<span class="kalahamoon-step-icon pending"></span>
							<?php endif; ?>
							<a href="<?php echo esc_url( $step['url'] ); ?>" class="<?php echo $step['done'] ? 'done' : ''; ?>">
								<?php echo esc_html( $step['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
					</ul>
					<?php if ( $last_sync ) : ?>
						<p style="color:#6b7280;font-size:11.5px;margin:14px 0 0"><?php echo esc_html( sprintf( __( 'Last sync: %s', 'kalahamoon' ), $last_sync ) ); ?></p>
						<?php if ( 0 === (int) $products_total ) : ?>
							<p style="color:#6b7280;font-size:11.5px;margin:6px 0 0"><?php esc_html_e( 'Sync completed, but no products were returned by your Kalahamoon catalog yet.', 'kalahamoon' ); ?></p>
						<?php elseif ( 0 === (int) $public_products ) : ?>
							<p style="color:#6b7280;font-size:11.5px;margin:6px 0 0"><?php esc_html_e( 'The sync is complete, but no product currently passes the public publishing checks.', 'kalahamoon' ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<?php if ( $digikala_capabilities ) : ?>
				<div class="kalahamoon-dash-panel">
					<h2><?php esc_html_e( 'Digikala Open API', 'kalahamoon' ); ?></h2>
					<?php
					$dk_snapshot = $digikala_capabilities['snapshot'] ?? array();
					$dk_seller   = $digikala_capabilities['seller'] ?? array();
					$dk_metrics  = array(
						array(
							'label' => __( 'Active order sample', 'kalahamoon' ),
							'value' => $dk_snapshot['orders']['activeSampleCount'] ?? 0,
						),
						array(
							'label' => __( 'Low-stock variants', 'kalahamoon' ),
							'value' => $dk_snapshot['variants']['lowStockSampleCount'] ?? 0,
						),
						array(
							'label' => __( 'Unanswered questions', 'kalahamoon' ),
							'value' => $dk_snapshot['customerCare']['unansweredQuestionSampleCount'] ?? 0,
						),
						array(
							'label' => __( 'Webhook events', 'kalahamoon' ),
							'value' => $dk_snapshot['automation']['webhookEventTypeSampleCount'] ?? 0,
						),
					);
					?>
					<p style="color:#6b7280;font-size:12px;line-height:1.6;margin:0 0 12px">
						<?php
						echo esc_html(
							sprintf(
								__( 'Seller: %s', 'kalahamoon' ),
								$dk_seller['shopTitle'] ?? $dk_seller['sellerName'] ?? $dk_seller['sellerId'] ?? __( 'Connected', 'kalahamoon' )
							)
						);
						?>
					</p>
					<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
						<?php foreach ( $dk_metrics as $metric ) : ?>
						<div class="kalahamoon-feature-card">
							<div class="kalahamoon-stat-value" style="font-size:20px"><?php echo esc_html( Kalahamoon_RTL::format_number( (int) $metric['value'] ) ); ?></div>
							<div class="kalahamoon-stat-label"><?php echo esc_html( $metric['label'] ); ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<p style="margin:14px 0 0;font-size:12px">
						<a href="<?php echo esc_url( $digikala_capabilities['docsUrl'] ?? 'https://seller.digikala.com/open-api/v1/doc/' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open API docs', 'kalahamoon' ); ?></a>
						·
						<a href="<?php echo esc_url( $digikala_capabilities['serviceHubUrl'] ?? 'https://servicehub.digikala.services/' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Service Hub', 'kalahamoon' ); ?></a>
					</p>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php self::print_copy_script(); ?>
		<?php
	}

	/**
	 * A connector installation has no local product editor. This page exposes
	 * delivery evidence only, so a failed cache pull is visible without offering
	 * a misleading WordPress repair action for Kalahamoon-owned catalog data.
	 */
	private static function render_catalog_consumer_page(): void {
		$status     = Kalahamoon_Catalog_Consumer::status();
		$catalog    = Kalahamoon_Product_Cache::get_all( array( 'limit' => 20, 'public_ready' => true ) );
		$connected  = Kalahamoon_Auth::is_connected();
		$connector_configured = Kalahamoon_Auth::has_catalog_connector_configuration();
		$authorization_url = Kalahamoon_Auth::get_authorization_url();
		$active     = is_array( $status['activeSnapshot'] ?? null ) ? $status['activeSnapshot'] : array();
		$last_sync  = is_array( $status['lastSync'] ?? null ) ? $status['lastSync'] : array();
		$delivery   = is_array( $status['lastDelivery'] ?? null ) ? $status['lastDelivery'] : array();
		$confirmed_delivery = is_array( $status['lastConfirmedDelivery'] ?? null ) ? $status['lastConfirmedDelivery'] : array();
		$next_refresh = (string) ( $status['nextExpectedRefresh'] ?? '' );
		$available  = is_array( $status['available'] ?? null ) ? $status['available'] : array();
		$direction  = Kalahamoon_RTL::admin_direction();
		$language   = Kalahamoon_RTL::admin_language();
		$sync_nonce = wp_create_nonce( 'kalahamoon_admin' );
		?>
		<div class="wrap kalahamoon-products" dir="<?php echo esc_attr( $direction ); ?>" lang="<?php echo esc_attr( $language ); ?>">
			<div class="kalahamoon-page-heading kalahamoon-products-heading">
				<div>
					<h1><?php esc_html_e( 'Catalog delivery', 'kalahamoon' ); ?></h1>
					<p><?php esc_html_e( 'Products, offers, collections, and publication decisions are managed in Kalahamoon. This site only renders the active catalog projection.', 'kalahamoon' ); ?></p>
				</div>
				<div class="kalahamoon-products-heading-actions">
					<button type="button" class="button button-primary" data-kalahamoon-catalog-sync data-nonce="<?php echo esc_attr( $sync_nonce ); ?>"><?php esc_html_e( 'Pull catalog now', 'kalahamoon' ); ?></button>
				</div>
			</div>
			<?php if ( ! $connected ) : ?>
				<?php if ( ! $connector_configured ) : ?>
					<div class="notice notice-error"><p><?php esc_html_e( 'This site has no dedicated catalog connector configured. Create a connector in Kalahamoon and install its client ID and secret before connecting.', 'kalahamoon' ); ?></p></div>
				<?php else : ?>
					<div class="notice notice-warning"><p><?php esc_html_e( 'Connect the dedicated catalog connector before this site can pull a projection.', 'kalahamoon' ); ?> <a class="button button-secondary" href="<?php echo esc_url( $authorization_url ); ?>"><?php esc_html_e( 'Connect catalog', 'kalahamoon' ); ?></a></p></div>
				<?php endif; ?>
			<?php endif; ?>

			<div class="kalahamoon-catalog-summary" role="status">
				<span><strong><?php esc_html_e( 'Active revision:', 'kalahamoon' ); ?></strong> <?php echo esc_html( (string) ( $active['revision'] ?? __( 'None', 'kalahamoon' ) ) ); ?></span>
				<span><strong><?php esc_html_e( 'Published products:', 'kalahamoon' ); ?></strong> <?php echo esc_html( Kalahamoon_RTL::format_number( (int) ( $catalog['total'] ?? 0 ) ) ); ?></span>
				<span><strong><?php esc_html_e( 'Last catalog result:', 'kalahamoon' ); ?></strong> <?php echo esc_html( (string) ( $last_sync['status'] ?? __( 'Never', 'kalahamoon' ) ) ); ?></span>
				<span><strong><?php esc_html_e( 'Delivery receipt:', 'kalahamoon' ); ?></strong> <?php echo esc_html( (string) ( $delivery['status'] ?? __( 'Pending', 'kalahamoon' ) ) ); ?></span>
				<span><strong><?php esc_html_e( 'Last confirmed delivery:', 'kalahamoon' ); ?></strong> <?php echo esc_html( (string) ( $confirmed_delivery['at'] ?? __( 'Never', 'kalahamoon' ) ) ); ?></span>
				<span><strong><?php esc_html_e( 'Next expected refresh:', 'kalahamoon' ); ?></strong> <?php echo esc_html( '' !== $next_refresh ? $next_refresh : __( 'Not reported yet', 'kalahamoon' ) ); ?></span>
			</div>

			<?php if ( ! empty( $last_sync['error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $last_sync['error'] ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $delivery['error'] ) ) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html( $delivery['error'] ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $available['revision'] ) ) : ?>
				<div class="notice notice-info"><p><?php printf( esc_html__( 'Kalahamoon advertised revision %s. It will be pulled by the configured server scheduler or the button above.', 'kalahamoon' ), esc_html( (string) $available['revision'] ) ); ?></p></div>
			<?php endif; ?>

			<div class="kalahamoon-table-scroll">
				<table class="wp-list-table widefat fixed striped kalahamoon-product-table">
					<thead><tr>
						<th><?php esc_html_e( 'Product', 'kalahamoon' ); ?></th>
						<th><?php esc_html_e( 'Collection', 'kalahamoon' ); ?></th>
						<th><?php esc_html_e( 'Price', 'kalahamoon' ); ?></th>
						<th><?php esc_html_e( 'Last checked', 'kalahamoon' ); ?></th>
					</tr></thead>
					<tbody>
					<?php if ( empty( $catalog['items'] ) ) : ?>
						<tr><td colspan="4" class="kalahamoon-empty-cell"><?php esc_html_e( 'No published catalog item is active on this site yet.', 'kalahamoon' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $catalog['items'] as $product ) : ?>
						<tr>
							<td><strong><?php echo esc_html( (string) ( $product['title'] ?? '' ) ); ?></strong><br /><code><?php echo esc_html( (string) ( $product['publicHandle'] ?? $product['id'] ?? '' ) ); ?></code></td>
							<td><?php echo esc_html( implode( ', ', array_map( static fn( $collection ): string => is_array( $collection ) ? (string) ( $collection['name'] ?? '' ) : '', is_array( $product['collections'] ?? null ) ? $product['collections'] : array() ) ) ); ?></td>
							<td><?php echo ! empty( $product['priceVisible'] ) ? esc_html( Kalahamoon_RTL::format_price( $product['price'], $product['currency'] ) ) : esc_html__( 'Hidden until refreshed', 'kalahamoon' ); ?></td>
							<td><?php echo esc_html( (string) ( $product['lastCheckedAt'] ?? __( 'Not supplied', 'kalahamoon' ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private static function is_catalog_consumer(): bool {
		return class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled();
	}

	// -------------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------------

	public static function register_settings(): void {
		// Connection section
		add_settings_section( 'kalahamoon_connection', __( 'Connection', 'kalahamoon' ), null, 'kalahamoon-settings' );

		register_setting( 'kalahamoon_settings', 'kalahamoon_api_key', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'kalahamoon_settings', 'kalahamoon_api_url', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_api_url' ),
			'default'           => function_exists( 'kalahamoon_default_api_url' ) ? kalahamoon_default_api_url() : 'https://app.kalahamoon.com',
		) );
		register_setting( 'kalahamoon_settings', 'kalahamoon_organization_slug', array(
			'sanitize_callback' => 'sanitize_title',
			'default'           => '',
		) );
		register_setting( 'kalahamoon_settings', 'kalahamoon_webhook_secret', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'kalahamoon_settings', 'kalahamoon_display_currency', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_display_currency' ),
			'default'           => 'IRR',
		) );

		register_setting( 'kalahamoon_settings', 'kalahamoon_clicks_retention', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_clicks_retention' ),
			'default'           => 90,
		) );

		register_setting( 'kalahamoon_settings', 'kalahamoon_legacy_dark_mode', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			'default'           => false,
		) );
		register_setting( 'kalahamoon_settings', 'kalahamoon_persian_numerals', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			'default'           => true,
		) );

		// Display section
		add_settings_section( 'kalahamoon_display', __( 'Display', 'kalahamoon' ), null, 'kalahamoon-settings' );

		register_setting( 'kalahamoon_settings', 'kalahamoon_display_unit', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_display_unit' ),
			'default'           => 'TOMAN',
		) );
		add_settings_field( 'kalahamoon_display_unit', __( 'Currency Unit', 'kalahamoon' ), function () {
			$value = get_option( 'kalahamoon_display_unit', 'TOMAN' );
			echo '<select name="kalahamoon_display_unit">';
			echo '<option value="TOMAN"' . selected( $value, 'TOMAN', false ) . '>' . esc_html__( 'Toman', 'kalahamoon' ) . '</option>';
			echo '<option value="RIAL"' . selected( $value, 'RIAL', false ) . '>' . esc_html__( 'Rial', 'kalahamoon' ) . '</option>';
			echo '</select>';
		}, 'kalahamoon-settings', 'kalahamoon_display' );

		add_settings_field( 'kalahamoon_legacy_dark_mode', __( 'Legacy Dark Mode Styles', 'kalahamoon' ), function () {
			$checked = get_option( 'kalahamoon_legacy_dark_mode', false );
			echo '<input type="hidden" name="kalahamoon_legacy_dark_mode" value="0" />';
			echo '<label><input type="checkbox" name="kalahamoon_legacy_dark_mode" value="1" ' . checked( $checked, true, false ) . ' /> '
				. esc_html__( 'Load the old Kalahamoon dark-mode compatibility stylesheet. Keep disabled when your theme controls dark mode.', 'kalahamoon' ) . '</label>';
		}, 'kalahamoon-settings', 'kalahamoon_display' );

		// Affiliate section
		add_settings_section( 'kalahamoon_affiliate', __( 'Affiliate', 'kalahamoon' ), function () {
			$panel_url = rtrim( get_option( 'kalahamoon_api_url', '' ), '/' );
			if ( $panel_url ) {
				echo '<p class="description">' .
					sprintf(
						wp_kses( __( 'Affiliate credentials (Bakalahamoon tracking URL, etc.) are managed in the <a href="%s" target="_blank">panel</a>.', 'kalahamoon' ), array( 'a' => array( 'href' => array(), 'target' => array() ) ) ),
						esc_url( $panel_url . '/settings/affiliate' )
					) . '</p>';
			}
		}, 'kalahamoon-settings' );

		register_setting( 'kalahamoon_settings', 'kalahamoon_redirect_type', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_redirect_type' ),
			'default'           => '301',
		) );
		add_settings_field( 'kalahamoon_redirect_type', __( 'Redirect Type', 'kalahamoon' ), function () {
			$value = get_option( 'kalahamoon_redirect_type', '301' );
			echo '<select name="kalahamoon_redirect_type">';
			echo '<option value="301"' . selected( $value, '301', false ) . '>' . esc_html__( '301 Permanent', 'kalahamoon' ) . '</option>';
			echo '<option value="302"' . selected( $value, '302', false ) . '>' . esc_html__( '302 Temporary', 'kalahamoon' ) . '</option>';
			echo '<option value="307"' . selected( $value, '307', false ) . '>' . esc_html__( '307 Temporary (preserve method)', 'kalahamoon' ) . '</option>';
			echo '</select>';
		}, 'kalahamoon-settings', 'kalahamoon_affiliate' );

		register_setting( 'kalahamoon_settings', 'kalahamoon_auto_disclosure', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
			'default'           => false,
		) );
		add_settings_field( 'kalahamoon_auto_disclosure', __( 'Auto Disclosure', 'kalahamoon' ), function () {
			$checked = get_option( 'kalahamoon_auto_disclosure', false );
			echo '<input type="hidden" name="kalahamoon_auto_disclosure" value="0" />';
			echo '<label><input type="checkbox" name="kalahamoon_auto_disclosure" value="1" ' . checked( $checked, true, false ) . ' /> '
				. esc_html__( 'Automatically prepend the disclosure note to posts that contain affiliate links.', 'kalahamoon' ) . '</label>';
		}, 'kalahamoon-settings', 'kalahamoon_affiliate' );

		register_setting( 'kalahamoon_settings', 'kalahamoon_disclosure_text', array(
			'sanitize_callback' => 'wp_kses_post',
			'default'           => '',
		) );
		add_settings_field( 'kalahamoon_disclosure_text', __( 'Disclosure Text', 'kalahamoon' ), function () {
			$value = get_option( 'kalahamoon_disclosure_text', '' );
			echo '<textarea name="kalahamoon_disclosure_text" class="large-text" rows="3">' . esc_textarea( $value ) . '</textarea>';
			echo '<p class="description">' . esc_html__( 'Auto-inserted at the top of posts containing affiliate links. Leave empty for default.', 'kalahamoon' ) . '</p>';
		}, 'kalahamoon-settings', 'kalahamoon_affiliate' );

		// Sync section
		add_settings_section( 'kalahamoon_sync', __( 'Sync', 'kalahamoon' ), null, 'kalahamoon-settings' );

		register_setting( 'kalahamoon_settings', 'kalahamoon_sync_interval', array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_sync_interval' ),
			'default'           => 6,
		) );
		if ( ! self::is_catalog_consumer() ) {
			register_setting( 'kalahamoon_settings', 'kalahamoon_catalog_authority', array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_catalog_authority' ),
				'default'           => 'hybrid',
			) );
			add_settings_field( 'kalahamoon_catalog_authority', __( 'Catalog Authority', 'kalahamoon' ), function () {
				$value = get_option( 'kalahamoon_catalog_authority', 'hybrid' );
				echo '<select name="kalahamoon_catalog_authority">';
				foreach ( array(
					'remote' => __( 'Kalahamoon only', 'kalahamoon' ),
					'hybrid' => __( 'Kalahamoon and local products', 'kalahamoon' ),
					'local'  => __( 'Local WordPress products only', 'kalahamoon' ),
				) as $authority => $label ) {
					echo '<option value="' . esc_attr( $authority ) . '"' . selected( $value, $authority, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
				echo '<p class="description">' . esc_html__( 'Controls which product source may appear on public catalog surfaces. Admin product management remains unchanged.', 'kalahamoon' ) . '</p>';
			}, 'kalahamoon-settings', 'kalahamoon_sync' );
			add_settings_field( 'kalahamoon_sync_interval', __( 'Sync Interval', 'kalahamoon' ), function () {
				$value = get_option( 'kalahamoon_sync_interval', 6 );
				echo '<select name="kalahamoon_sync_interval">';
				foreach ( array( 1, 3, 6, 12, 24 ) as $h ) {
					echo '<option value="' . $h . '"' . selected( $value, $h, false ) . '>' . $h . ' ' . esc_html__( 'hours', 'kalahamoon' ) . '</option>';
				}
				echo '</select>';
				$last = get_option( 'kalahamoon_last_sync', '' );
				if ( $last ) {
					echo '<p class="description">' . esc_html( sprintf( __( 'Last sync: %s', 'kalahamoon' ), $last ) ) . '</p>';
				}
			}, 'kalahamoon-settings', 'kalahamoon_sync' );
		} else {
			add_settings_field( 'kalahamoon_catalog_scheduler', __( 'Catalog scheduler', 'kalahamoon' ), function () {
				echo '<p class="description">' . esc_html__( 'Catalog pulls are run by the server scheduler, not visitor-driven WP-Cron. Use Catalog delivery to inspect the active revision and last receipt.', 'kalahamoon' ) . '</p>';
			}, 'kalahamoon-settings', 'kalahamoon_sync' );
		}
	}

	public static function sanitize_api_url( $value ): string {
		$default = function_exists( 'kalahamoon_default_api_url' ) ? kalahamoon_default_api_url() : 'https://app.kalahamoon.com';
		$url     = esc_url_raw( trim( (string) $value ) );
		if ( '' === $url ) {
			return $default;
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return $default;
		}

		return rtrim( $url, '/' );
	}

	public static function sanitize_bool( $value ): bool {
		return ! empty( $value ) && '0' !== (string) $value;
	}

	public static function sanitize_display_unit( $value ): string {
		$value = strtoupper( sanitize_key( (string) $value ) );
		return in_array( $value, array( 'TOMAN', 'RIAL' ), true ) ? $value : 'TOMAN';
	}

	public static function sanitize_direction( $value ): string {
		$value = strtolower( sanitize_key( (string) $value ) );
		return in_array( $value, array( 'auto', 'rtl', 'ltr' ), true ) ? $value : 'auto';
	}

	public static function sanitize_display_currency( $value ): string {
		$value = strtoupper( sanitize_key( (string) $value ) );
		return in_array( $value, array( 'IRR', 'USD', 'EUR' ), true ) ? $value : 'IRR';
	}

	public static function sanitize_redirect_type( $value ): string {
		$value = (string) absint( $value );
		return in_array( $value, array( '301', '302', '307' ), true ) ? $value : '301';
	}

	public static function sanitize_sync_interval( $value ): int {
		$value = absint( $value );
		return in_array( $value, array( 1, 3, 6, 12, 24 ), true ) ? $value : 6;
	}

	public static function sanitize_catalog_authority( $value ): string {
		return Kalahamoon_Catalog_Policy::normalize_authority( $value );
	}

	public static function sanitize_clicks_retention( $value ): int {
		$value = absint( $value );
		return max( 7, min( 365, $value ) );
	}

	public static function render_settings_page(): void {
		if ( self::is_catalog_consumer() ) {
			self::render_catalog_consumer_page();
			return;
		}

		$oauth_connected = Kalahamoon_Auth::is_connected();
		$user_info       = $oauth_connected ? Kalahamoon_Token_Store::get_user_info() : null;
		$just_connected  = isset( $_GET['kalahamoon_connected'] );
		$oauth_error     = sanitize_text_field( $_GET['kalahamoon_oauth_error'] ?? '' );
		$last_sync       = get_option( 'kalahamoon_last_sync', '' );
		$public_products = Kalahamoon_Product_Cache::public_ready_count();
		$products_url    = admin_url( 'admin.php?page=kalahamoon-products' );
		$nonce           = wp_create_nonce( 'kalahamoon_admin' );
		$direction       = Kalahamoon_RTL::admin_direction();
		$language        = Kalahamoon_RTL::admin_language();
		?>
		<div class="wrap kalahamoon-settings" dir="<?php echo esc_attr( $direction ); ?>" lang="<?php echo esc_attr( $language ); ?>">

			<h1><?php esc_html_e( 'Kalahamoon Settings', 'kalahamoon' ); ?></h1>

			<?php if ( $just_connected ) : ?>
				<div class="kalahamoon-notice kalahamoon-notice-success">
					<strong><?php esc_html_e( 'Successfully connected to Kalahamoon!', 'kalahamoon' ); ?></strong>
					<?php esc_html_e( 'You can now sync products and track affiliate clicks.', 'kalahamoon' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $oauth_error ) : ?>
				<div class="kalahamoon-notice kalahamoon-notice-error">
					<strong><?php esc_html_e( 'Connection failed:', 'kalahamoon' ); ?></strong> <?php echo esc_html( $oauth_error ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $oauth_connected && $user_info ) : ?>
				<!-- Connected state -->
				<div class="kalahamoon-card kalahamoon-connected-card">
					<div class="kalahamoon-connected-header">
						<span class="kalahamoon-connected-dot"></span>
						<span class="kalahamoon-connected-title"><?php esc_html_e( 'Connected to Kalahamoon', 'kalahamoon' ); ?></span>
					</div>
					<table class="kalahamoon-meta-table">
						<tr><td><?php esc_html_e( 'Account', 'kalahamoon' ); ?></td><td dir="ltr"><?php echo esc_html( $user_info['email'] ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Organization', 'kalahamoon' ); ?></td><td dir="auto"><?php echo esc_html( $user_info['org_name'] ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Connected', 'kalahamoon' ); ?></td><td><?php echo esc_html( $user_info['connected_at'] ); ?></td></tr>
						<?php if ( $last_sync ) : ?>
							<tr><td><?php esc_html_e( 'Last sync', 'kalahamoon' ); ?></td><td><?php echo esc_html( $last_sync ); ?></td></tr>
						<?php endif; ?>
					</table>
					<div class="kalahamoon-btn-row">
						<button type="button" class="kalahamoon-btn-primary" id="kalahamoon-sync-now"><?php esc_html_e( 'Sync products now', 'kalahamoon' ); ?></button>
						<button type="button" class="kalahamoon-btn-secondary" id="kalahamoon-test-connection"><?php esc_html_e( 'Test connection', 'kalahamoon' ); ?></button>
						<button type="button" class="kalahamoon-btn-secondary kalahamoon-btn-danger" id="kalahamoon-oauth-disconnect" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Disconnect', 'kalahamoon' ); ?></button>
						<span id="kalahamoon-action-status" class="kalahamoon-action-status"></span>
					</div>
				</div>
			<?php else : ?>
				<!-- Disconnected state — prominent connect card -->
				<div class="kalahamoon-card kalahamoon-connect-card">
					<div class="kalahamoon-card-header">
						<h2><?php esc_html_e( 'Connect to Kalahamoon', 'kalahamoon' ); ?></h2>
					</div>
					<p class="kalahamoon-card-subtitle"><?php esc_html_e( 'Link this WordPress site to your Kalahamoon account to sync products, track affiliate clicks, and capture leads.', 'kalahamoon' ); ?></p>
					<a href="<?php echo esc_url( Kalahamoon_Auth::get_authorization_url() ); ?>" class="kalahamoon-btn-connect">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
						<?php esc_html_e( 'Connect with Kalahamoon', 'kalahamoon' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<section class="kalahamoon-dash-readiness kalahamoon-settings-readiness <?php echo $public_products > 0 ? 'is-ready' : 'is-blocked'; ?>" aria-labelledby="kalahamoon-settings-public-readiness">
				<div>
					<p class="kalahamoon-dash-readiness__eyebrow"><?php esc_html_e( 'Catalog publication status', 'kalahamoon' ); ?></p>
					<h2 id="kalahamoon-settings-public-readiness"><?php echo esc_html( $public_products > 0 ? __( 'Products are ready for the public catalog.', 'kalahamoon' ) : __( 'No products meet the public catalog checks yet.', 'kalahamoon' ) ); ?></h2>
					<p><?php esc_html_e( 'Catalog settings control which ready products are allowed to appear. Review product readiness before promoting the catalog.', 'kalahamoon' ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( $products_url ); ?>"><?php esc_html_e( 'Review product readiness', 'kalahamoon' ); ?></a>
			</section>

			<!-- Display / Localization Preferences -->
			<div class="kalahamoon-card">
				<div class="kalahamoon-card-header">
					<h2><?php esc_html_e( 'Display & Affiliate', 'kalahamoon' ); ?></h2>
				</div>
				<p class="kalahamoon-card-subtitle"><?php esc_html_e( 'Currency display, number formatting, affiliate disclosure, and sync frequency.', 'kalahamoon' ); ?></p>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'kalahamoon_settings' );
					do_settings_sections( 'kalahamoon-settings' );
					submit_button( __( 'Save changes', 'kalahamoon' ), 'primary', 'submit', false );
					?>
				</form>
			</div>

			<script>
			(function() {
				const ajaxUrl = ajaxurl + '?_wpnonce=<?php echo esc_attr( $nonce ); ?>';
				const statusEl = document.getElementById('kalahamoon-action-status');
				const setStatus = (text, kind) => {
					if (!statusEl) return;
					statusEl.textContent = text;
					statusEl.className = 'kalahamoon-action-status' + (kind ? ' ' + kind : '');
				};

				document.getElementById('kalahamoon-test-connection')?.addEventListener('click', async function() {
					if (this.disabled) return;
					this.disabled = true;
					this.setAttribute('aria-busy', 'true');
					setStatus('<?php echo esc_js( __( 'Testing…', 'kalahamoon' ) ); ?>');
					try {
						const res = await fetch(ajaxUrl + '&action=kalahamoon_test_connection');
						const data = await res.json();
						if (data.success) setStatus('<?php echo esc_js( __( '✓ Connection works', 'kalahamoon' ) ); ?>', 'success');
						else setStatus('✗ ' + (data.data?.message || '<?php echo esc_js( __( 'Failed', 'kalahamoon' ) ); ?>'), 'error');
					} catch(e) {
						setStatus('✗ ' + e.message, 'error');
					} finally {
						this.disabled = false;
						this.removeAttribute('aria-busy');
					}
				});

				document.getElementById('kalahamoon-sync-now')?.addEventListener('click', async function() {
					if (this.disabled) return;
					this.disabled = true;
					this.setAttribute('aria-busy', 'true');
					setStatus('<?php echo esc_js( __( 'Syncing products…', 'kalahamoon' ) ); ?>');
					try {
						const res = await fetch(ajaxUrl + '&action=kalahamoon_sync_now');
						const data = await res.json();
						if (data.success) {
							const synced = data.data.synced || 0;
							const errors = data.data.errors || 0;
							const msg = data.data.message ? ' — ' + data.data.message : '';
							if (synced > 0) {
								setStatus('✓ <?php echo esc_js( __( 'Synced', 'kalahamoon' ) ); ?> ' + synced + ' <?php echo esc_js( __( 'products', 'kalahamoon' ) ); ?>' + (errors ? ' (' + errors + ' <?php echo esc_js( __( 'errors', 'kalahamoon' ) ); ?>)' : ''), 'success');
								setTimeout(() => location.reload(), 1500);
							} else if (errors > 0) {
								setStatus('✗ <?php echo esc_js( __( 'Sync failed', 'kalahamoon' ) ); ?>' + msg, 'error');
							} else {
								setStatus('<?php echo esc_js( __( 'No products available to sync.', 'kalahamoon' ) ); ?>' + msg);
							}
						} else {
							setStatus('✗ ' + (data.data?.message || '<?php echo esc_js( __( 'Failed', 'kalahamoon' ) ); ?>'), 'error');
						}
					} catch(e) {
						setStatus('✗ ' + e.message, 'error');
					} finally {
						this.disabled = false;
						this.removeAttribute('aria-busy');
					}
				});

				document.getElementById('kalahamoon-oauth-disconnect')?.addEventListener('click', async function() {
					if (this.disabled) return;
					if (!confirm('<?php echo esc_js( __( 'Disconnect from Kalahamoon? You can reconnect later.', 'kalahamoon' ) ); ?>')) return;
					this.disabled = true;
					this.setAttribute('aria-busy', 'true');
					try {
						const res = await fetch(ajaxUrl + '&action=kalahamoon_oauth_disconnect');
						const data = await res.json();
						if (data.success) location.reload();
						else setStatus('✗ ' + (data.data?.message || '<?php echo esc_js( __( 'Failed', 'kalahamoon' ) ); ?>'), 'error');
					} catch(e) {
						setStatus('✗ ' + e.message, 'error');
					} finally {
						this.disabled = false;
						this.removeAttribute('aria-busy');
					}
				});
			})();
			</script>
		</div>
		<?php
	}

	/**
	 * Convert the catalog's stable validation identifiers into next steps people
	 * can act on. Provider identifiers stay internal because they are neither
	 * useful nor stable instructions for store staff.
	 *
	 * @param array<int, mixed> $policy_issues
	 * @param array<int, mixed> $source_issues
	 * @return array<int, string>
	 */
	private static function public_catalog_readiness_messages( array $policy_issues, array $source_issues ): array {
		$labels = array(
			'CANONICAL_CATEGORY_REQUIRED' => __( 'Confirm the canonical product category.', 'kalahamoon' ),
			'LISTING_NOT_ACTIVE'          => __( 'Make the source listing active.', 'kalahamoon' ),
			'TITLE_REQUIRED'              => __( 'Add a product title.', 'kalahamoon' ),
			'DESTINATION_REQUIRED'        => __( 'Add a secure (HTTPS) product destination.', 'kalahamoon' ),
			'IMAGE_REQUIRED'              => __( 'Add a secure (HTTPS) product image.', 'kalahamoon' ),
			'PRICE_REQUIRED'              => __( 'Add a current product price.', 'kalahamoon' ),
			'missing_id'                  => __( 'Restore the product identity before publishing.', 'kalahamoon' ),
			'missing_title'               => __( 'Add a product title.', 'kalahamoon' ),
			'missing_imageUrl'            => __( 'Add a secure (HTTPS) product image.', 'kalahamoon' ),
			'missing_listingUrl'          => __( 'Add a secure (HTTPS) product destination.', 'kalahamoon' ),
			'invalid_image_url'           => __( 'Add a secure (HTTPS) product image.', 'kalahamoon' ),
			'invalid_listing_url'         => __( 'Add a secure (HTTPS) product destination.', 'kalahamoon' ),
			'not_verified'                => __( 'Verify the listing before it can appear publicly.', 'kalahamoon' ),
			'not_active'                  => __( 'Make the product active before it can appear publicly.', 'kalahamoon' ),
			'source_not_allowed'          => __( 'Allow this product source in the catalog settings.', 'kalahamoon' ),
			'missing_price'               => __( 'Add a current product price.', 'kalahamoon' ),
			'missing_freshness'           => __( 'Sync the listing to record a current price check.', 'kalahamoon' ),
			'future_freshness'            => __( 'Sync the listing again because its update time is invalid.', 'kalahamoon' ),
			'expired'                     => __( 'Sync the listing again because it is more than 72 hours old.', 'kalahamoon' ),
			'listing_needs_review'        => __( 'Complete the remaining Kalahamoon listing review.', 'kalahamoon' ),
		);

		$messages = array();
		foreach ( array_merge( $source_issues, $policy_issues ) as $issue ) {
			if ( ! is_string( $issue ) || '' === trim( $issue ) ) {
				continue;
			}

			$message = $labels[ $issue ] ?? __( 'Complete the remaining Kalahamoon listing review.', 'kalahamoon' );
			if ( ! in_array( $message, $messages, true ) ) {
				$messages[] = $message;
			}
		}

		return $messages;
	}

	/**
	 * @param array<int, string> $messages
	 */
	private static function render_public_catalog_readiness_messages( array $messages ): void {
		if ( empty( $messages ) ) {
			return;
		}
		?>
		<ul class="kalahamoon-readiness-issues">
			<?php foreach ( $messages as $message ) : ?>
				<li><?php echo esc_html( $message ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	// -------------------------------------------------------------------------
	// Products
	// -------------------------------------------------------------------------

	public static function render_products_page(): void {
		if ( self::is_catalog_consumer() ) {
			self::render_catalog_consumer_page();
			return;
		}

		$search   = sanitize_text_field( $_GET['s'] ?? '' );
		$platform = sanitize_text_field( $_GET['platform'] ?? '' );
		$source   = sanitize_key( $_GET['source'] ?? '' );
		$page     = max( 1, absint( $_GET['paged'] ?? 1 ) );
		if ( ! in_array( $source, array( '', 'local', 'remote' ), true ) ) {
			$source = '';
		}

		$args = array(
			'page'  => $page,
			'limit' => 20,
		);
		if ( $search )   $args['search']   = $search;
		if ( $platform ) $args['platform'] = $platform;
		if ( $source )   $args['source']   = $source;

		$result   = Kalahamoon_Product_Cache::get_all( $args );
		$products = $result['items'];
		$total    = $result['total'];

		// Distinct platforms for filter
		$all_products = Kalahamoon_Product_Cache::get_all( array( 'limit' => 500 ) )['items'];
		$platforms    = array_unique( array_filter( array_column( $all_products, 'platform' ) ) );
		sort( $platforms );
		$local_total  = count( array_filter( $all_products, static fn( array $product ): bool => 'manual' === ( $product['source'] ?? '' ) ) );
		$synced_total = count( $all_products ) - $local_total;
		$public_ready_total = Kalahamoon_Product_Cache::public_ready_count();
		$collections = get_terms( array( 'taxonomy' => 'kalahamoon_collection', 'hide_empty' => false, 'orderby' => 'name' ) );
		$collections = is_wp_error( $collections ) ? array() : $collections;

		$base_url  = admin_url( 'admin.php?page=kalahamoon-products' );
		$clear_filters_url = '' !== $source ? add_query_arg( 'source', $source, $base_url ) : $base_url;
		$direction = Kalahamoon_RTL::admin_direction();
		$language  = Kalahamoon_RTL::admin_language();
		$locale    = 'rtl' === $direction ? 'fa' : 'en';
		$panel_url = rtrim( (string) get_option( 'kalahamoon_api_url', kalahamoon_default_api_url() ), '/' );
		$taxonomy_url = $panel_url . '/' . $locale . '/products/taxonomy-intelligence';
		$settings_url = admin_url( 'admin.php?page=kalahamoon-setting' );
		$collections_url = admin_url( 'edit-tags.php?taxonomy=kalahamoon_collection&post_type=kalahamoon_product' );
		$sync_nonce = wp_create_nonce( 'kalahamoon_admin' );
		?>
		<div class="wrap kalahamoon-products" dir="<?php echo esc_attr( $direction ); ?>" lang="<?php echo esc_attr( $language ); ?>">
			<div class="kalahamoon-page-heading kalahamoon-products-heading">
				<div>
					<h1>
						<?php esc_html_e( 'Kalahamoon Products', 'kalahamoon' ); ?>
						<span class="kalahamoon-count-badge"><?php echo esc_html( Kalahamoon_RTL::format_number( $total ) ); ?></span>
					</h1>
					<p><?php esc_html_e( 'Use local products for this WordPress site. Synced products remain managed in Kalahamoon.', 'kalahamoon' ); ?></p>
				</div>
				<div class="kalahamoon-products-heading-actions">
					<button type="button" class="button" data-kalahamoon-catalog-sync data-nonce="<?php echo esc_attr( $sync_nonce ); ?>"><?php esc_html_e( 'Sync catalog', 'kalahamoon' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=kalahamoon-product-editor' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create local product', 'kalahamoon' ); ?></a>
				</div>
			</div>
			<?php if ( isset( $_GET['deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Local product moved to the Trash.', 'kalahamoon' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['collection_updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Primary collection updated. Product facts and seller data remain managed in Kalahamoon.', 'kalahamoon' ); ?></p></div>
			<?php endif; ?>
			<div class="kalahamoon-catalog-summary" role="status">
				<span><strong><?php echo esc_html( Kalahamoon_RTL::format_number( $synced_total ) ); ?></strong> <?php esc_html_e( 'synced from Kalahamoon', 'kalahamoon' ); ?></span>
				<span><strong><?php echo esc_html( Kalahamoon_RTL::format_number( $local_total ) ); ?></strong> <?php esc_html_e( 'local to WordPress', 'kalahamoon' ); ?></span>
				<span class="kalahamoon-public-ready-summary <?php echo $public_ready_total > 0 ? 'is-ready' : 'is-blocked'; ?>"><strong><?php echo esc_html( Kalahamoon_RTL::format_number( $public_ready_total ) ); ?></strong> <?php esc_html_e( 'ready for the public catalog', 'kalahamoon' ); ?></span>
				<a href="<?php echo esc_url( $taxonomy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Review product categories in Kalahamoon', 'kalahamoon' ); ?></a>
				<a href="<?php echo esc_url( $collections_url ); ?>"><?php esc_html_e( 'Manage curated collections', 'kalahamoon' ); ?></a>
			</div>

			<nav class="kalahamoon-catalog-source-tabs" aria-label="<?php esc_attr_e( 'Product source', 'kalahamoon' ); ?>">
				<?php foreach ( array( '' => __( 'All products', 'kalahamoon' ), 'remote' => __( 'Synced catalog', 'kalahamoon' ), 'local' => __( 'Local WordPress products', 'kalahamoon' ) ) as $source_value => $source_label ) : ?>
					<a class="<?php echo $source === $source_value ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array_filter( array( 'source' => $source_value ?: null, 's' => $search ?: null, 'platform' => $platform ?: null ) ), $base_url ) ); ?>"><?php echo esc_html( $source_label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="get" action="<?php echo esc_url( $base_url ); ?>" class="kalahamoon-admin-filters">
				<input type="hidden" name="page" value="kalahamoon-products" />
				<?php if ( $source ) : ?><input type="hidden" name="source" value="<?php echo esc_attr( $source ); ?>" /><?php endif; ?>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search products…', 'kalahamoon' ); ?>" class="regular-text" style="width:220px" />
				<select name="platform">
					<option value=""><?php esc_html_e( 'All Platforms', 'kalahamoon' ); ?></option>
					<?php foreach ( $platforms as $p ) : ?>
						<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $platform, $p ); ?>><?php echo esc_html( $p ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'kalahamoon' ); ?>" />
				<?php if ( $search || $platform ) : ?>
					<a href="<?php echo esc_url( $clear_filters_url ); ?>" class="button"><?php esc_html_e( 'Clear', 'kalahamoon' ); ?></a>
				<?php endif; ?>
			</form>

			<div class="kalahamoon-table-scroll">
			<table class="wp-list-table widefat fixed striped kalahamoon-product-table">
				<thead>
					<tr>
						<th style="width:56px"><?php esc_html_e( 'Image', 'kalahamoon' ); ?></th>
						<th><?php esc_html_e( 'Title', 'kalahamoon' ); ?></th>
						<th style="width:120px"><?php esc_html_e( 'Price', 'kalahamoon' ); ?></th>
						<th style="width:220px"><?php esc_html_e( 'Connection', 'kalahamoon' ); ?></th>
						<th style="width:190px"><?php esc_html_e( 'Readiness', 'kalahamoon' ); ?></th>
						<th style="width:150px"><?php esc_html_e( 'Actions', 'kalahamoon' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $products ) ) : ?>
					<tr><td colspan="6" class="kalahamoon-empty-cell">
						<?php esc_html_e( 'No products found. Create a local product or sync your catalog from Settings.', 'kalahamoon' ); ?>
					</td></tr>
				<?php endif; ?>
				<?php foreach ( $products as $p ) :
					$is_local = 'manual' === ( $p['source'] ?? '' );
					$issues = is_array( $p['publicationReadinessIssues'] ?? null ) ? $p['publicationReadinessIssues'] : array();
					$catalog_policy = Kalahamoon_Catalog_Policy::evaluate( $p );
					$policy_issues = is_array( $catalog_policy['readinessIssues'] ?? null ) ? $catalog_policy['readinessIssues'] : array();
					$readiness_messages = self::public_catalog_readiness_messages( $policy_issues, $issues );
					$ready_for_catalog = ! empty( $catalog_policy['publicReady'] );
					$price_visible = ! empty( $catalog_policy['priceVisible'] );
					$status = strtolower( (string) ( $p['status'] ?? '' ) );
					$status_label = array(
						'active' => __( 'Active', 'kalahamoon' ),
						'captured' => __( 'Needs review', 'kalahamoon' ),
						'inactive' => __( 'Inactive', 'kalahamoon' ),
					)[ $status ] ?? __( 'Needs review', 'kalahamoon' );
					$category_blocked = in_array( 'CANONICAL_CATEGORY_REQUIRED', $issues, true );
				?>
					<tr>
						<td data-label="<?php esc_attr_e( 'Image', 'kalahamoon' ); ?>">
							<?php if ( ! empty( $p['imageUrl'] ) ) : ?>
								<img src="<?php echo esc_url( $p['imageUrl'] ); ?>" width="44" height="44" style="object-fit:cover;border-radius:4px;vertical-align:middle" />
							<?php else : ?>
								<div style="width:44px;height:44px;background:#f0f0f1;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:18px">📦</div>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Title', 'kalahamoon' ); ?>">
							<strong dir="auto"><?php echo esc_html( $p['title'] ); ?></strong>
							<div class="kalahamoon-product-subtitle" dir="auto"><?php echo esc_html( $p['sellerName'] ?: ( $p['platform'] ?: __( 'No marketplace details yet', 'kalahamoon' ) ) ); ?></div>
							<details class="kalahamoon-product-reference"><summary><?php esc_html_e( 'Product ID', 'kalahamoon' ); ?></summary><code dir="ltr"><?php echo esc_html( $p['id'] ); ?></code><button type="button" class="button-link kalahamoon-copy-btn" data-copy="<?php echo esc_attr( $p['id'] ); ?>"><?php esc_html_e( 'Copy', 'kalahamoon' ); ?></button></details>
						</td>
						<td data-label="<?php esc_attr_e( 'Price', 'kalahamoon' ); ?>"><?php echo esc_html( Kalahamoon_RTL::format_price( $p['price'], $p['currency'] ) ); ?></td>
						<td data-label="<?php esc_attr_e( 'Connection', 'kalahamoon' ); ?>">
							<span class="kalahamoon-source-badge <?php echo $is_local ? 'is-local' : ''; ?>"><?php echo esc_html( $is_local ? __( 'Local WordPress product', 'kalahamoon' ) : __( 'Synced Kalahamoon listing', 'kalahamoon' ) ); ?></span>
							<p class="kalahamoon-connection-note"><?php echo esc_html( $is_local ? __( 'Managed only on this WordPress site.', 'kalahamoon' ) : sprintf( __( '%s status', 'kalahamoon' ), $status_label ) ); ?></p>
						</td>
						<td data-label="<?php esc_attr_e( 'Readiness', 'kalahamoon' ); ?>">
							<?php if ( $ready_for_catalog ) : ?>
								<span class="kalahamoon-readiness-badge is-ready"><?php esc_html_e( 'Ready for public catalog', 'kalahamoon' ); ?></span>
								<?php if ( ! $price_visible ) : ?>
									<p class="kalahamoon-readiness-note"><?php esc_html_e( 'The product stays visible, but its price is hidden until the next sync.', 'kalahamoon' ); ?></p>
								<?php endif; ?>
							<?php elseif ( $category_blocked ) : ?>
								<span class="kalahamoon-readiness-badge is-warning"><?php esc_html_e( 'Category review needed', 'kalahamoon' ); ?></span>
								<?php self::render_public_catalog_readiness_messages( $readiness_messages ); ?>
								<a class="kalahamoon-readiness-link" href="<?php echo esc_url( $taxonomy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Review category in Kalahamoon', 'kalahamoon' ); ?></a>
							<?php elseif ( $is_local ) : ?>
								<span class="kalahamoon-readiness-badge is-warning"><?php esc_html_e( 'Needs WordPress details', 'kalahamoon' ); ?></span>
								<?php self::render_public_catalog_readiness_messages( $readiness_messages ); ?>
								<a class="kalahamoon-readiness-link" href="<?php echo esc_url( add_query_arg( array( 'page' => 'kalahamoon-product-editor', 'product' => (int) $p['wp_post_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit this product', 'kalahamoon' ); ?></a>
							<?php elseif ( ! empty( $readiness_messages ) ) : ?>
								<span class="kalahamoon-readiness-badge is-warning"><?php esc_html_e( 'Needs Kalahamoon review', 'kalahamoon' ); ?></span>
								<?php self::render_public_catalog_readiness_messages( $readiness_messages ); ?>
								<a class="kalahamoon-readiness-link" href="<?php echo esc_url( in_array( 'source_not_allowed', $policy_issues, true ) ? $settings_url : $panel_url ); ?>"<?php echo in_array( 'source_not_allowed', $policy_issues, true ) ? '' : ' target="_blank" rel="noopener noreferrer"'; ?>><?php echo esc_html( in_array( 'source_not_allowed', $policy_issues, true ) ? __( 'Review catalog source setting', 'kalahamoon' ) : __( 'Open in Kalahamoon', 'kalahamoon' ) ); ?></a>
							<?php else : ?>
								<span class="kalahamoon-readiness-badge is-warning"><?php esc_html_e( 'Needs Kalahamoon review', 'kalahamoon' ); ?></span>
								<p class="kalahamoon-readiness-note"><?php esc_html_e( 'Review this listing in Kalahamoon, then sync the catalog again.', 'kalahamoon' ); ?></p>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Actions', 'kalahamoon' ); ?>">
							<?php $sc = '[kalahamoon_product id="' . esc_attr( $p['id'] ) . '"]'; ?>
							<button type="button" class="button button-small kalahamoon-copy-btn" data-copy="<?php echo esc_attr( $sc ); ?>"><?php esc_html_e( 'Copy shortcode', 'kalahamoon' ); ?></button>
							<?php if ( $is_local ) : ?>
								<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'kalahamoon-product-editor', 'product' => (int) $p['wp_post_id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'kalahamoon' ); ?></a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kalahamoon-inline-form">
									<input type="hidden" name="action" value="kalahamoon_delete_product" />
									<input type="hidden" name="product" value="<?php echo esc_attr( (string) $p['wp_post_id'] ); ?>" />
									<?php wp_nonce_field( 'kalahamoon_delete_product_' . (int) $p['wp_post_id'], 'kalahamoon_delete_product_nonce' ); ?>
									<button type="submit" class="button button-small button-link-delete kalahamoon-delete-local-product"><?php esc_html_e( 'Delete', 'kalahamoon' ); ?></button>
								</form>
							<?php else : ?>
								<a class="button button-small" href="<?php echo esc_url( $panel_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open in Kalahamoon', 'kalahamoon' ); ?></a>
							<?php endif; ?>
							<?php $assigned_collections = wp_get_object_terms( (int) $p['wp_post_id'], 'kalahamoon_collection', array( 'fields' => 'ids' ) ); $assigned_collection = is_array( $assigned_collections ) ? absint( $assigned_collections[0] ?? 0 ) : 0; ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kalahamoon-inline-form kalahamoon-collection-assignment">
								<input type="hidden" name="action" value="kalahamoon_assign_product_collection" />
								<input type="hidden" name="product" value="<?php echo esc_attr( (string) $p['wp_post_id'] ); ?>" />
								<?php wp_nonce_field( 'kalahamoon_assign_product_collection_' . (int) $p['wp_post_id'], 'kalahamoon_product_collection_nonce' ); ?>
								<label class="screen-reader-text" for="kalahamoon-collection-<?php echo esc_attr( (string) $p['wp_post_id'] ); ?>"><?php esc_html_e( 'Primary collection', 'kalahamoon' ); ?></label>
								<select id="kalahamoon-collection-<?php echo esc_attr( (string) $p['wp_post_id'] ); ?>" name="collection">
									<option value="0"><?php esc_html_e( 'No collection', 'kalahamoon' ); ?></option>
									<?php foreach ( $collections as $collection ) : ?><?php if ( $collection instanceof WP_Term ) : ?><option value="<?php echo esc_attr( (string) $collection->term_id ); ?>" <?php selected( $assigned_collection, $collection->term_id ); ?>><?php echo esc_html( $collection->name ); ?></option><?php endif; ?><?php endforeach; ?>
								</select>
								<button type="submit" class="button button-small"><?php esc_html_e( 'Set primary collection', 'kalahamoon' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>

			<?php if ( $total > 20 ) :
				$pages = (int) ceil( $total / 20 );
				$links = paginate_links( array(
					'base'      => add_query_arg( 'paged', '%#%', $base_url ),
					'format'    => '',
					'current'   => $page,
					'total'     => $pages,
					'mid_size'  => 2,
					'end_size'  => 1,
					'prev_text' => __( 'Previous', 'kalahamoon' ),
					'next_text' => __( 'Next', 'kalahamoon' ),
					'type'      => 'list',
					'add_args'  => array_filter( array( 's' => $search, 'platform' => $platform, 'source' => $source ) ),
				) );
				if ( $links ) : ?>
					<nav class="kalahamoon-pagination" aria-label="<?php esc_attr_e( 'Product pagination', 'kalahamoon' ); ?>"><?php echo wp_kses_post( $links ); ?></nav>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php self::print_copy_script(); ?>
		<?php
	}

	/**
	 * Render the WordPress-owned product editor. Imported catalog records remain
	 * read-only so a catalog sync cannot silently overwrite an editor's changes.
	 */
	public static function render_product_editor_page(): void {
		if ( self::is_catalog_consumer() ) {
			wp_die( esc_html__( 'Catalog products are managed in Kalahamoon.', 'kalahamoon' ), '', array( 'response' => 403 ) );
		}

		$post_id  = absint( $_GET['product'] ?? 0 );
		$is_edit  = $post_id > 0;
		$product  = $is_edit ? get_post( $post_id ) : null;
		$back_url = admin_url( 'admin.php?page=kalahamoon-products' );

		if ( $is_edit && ( ! $product || ! Kalahamoon_Product_Cache::is_manual_post( $post_id ) ) ) {
			wp_die( esc_html__( 'This product cannot be edited here.', 'kalahamoon' ), '', array( 'response' => 403 ) );
		}

		$values = array(
			'title'               => $product ? $product->post_title : '',
			'description'         => $product ? $product->post_content : '',
			'price'               => $product ? get_post_meta( $post_id, '_kalahamoon_price', true ) : '',
			'currency'            => $product ? get_post_meta( $post_id, '_kalahamoon_currency', true ) : 'IRR',
			'platform'            => $product ? get_post_meta( $post_id, '_kalahamoon_platform', true ) : 'wordpress',
			'listing_url'         => $product ? get_post_meta( $post_id, '_kalahamoon_listing_url', true ) : '',
			'image_url'           => $product ? get_post_meta( $post_id, '_kalahamoon_image_url', true ) : '',
			'image_attachment_id' => $product ? absint( get_post_meta( $post_id, '_kalahamoon_image_attachment_id', true ) ) : 0,
			'status'              => $product ? get_post_meta( $post_id, '_kalahamoon_status', true ) : 'active',
		);
		$direction = Kalahamoon_RTL::admin_direction();
		$language  = Kalahamoon_RTL::admin_language();
		?>
		<div class="wrap kalahamoon-product-editor" dir="<?php echo esc_attr( $direction ); ?>" lang="<?php echo esc_attr( $language ); ?>">
			<div class="kalahamoon-page-heading">
				<h1><?php echo esc_html( $is_edit ? __( 'Edit local product', 'kalahamoon' ) : __( 'Create local product', 'kalahamoon' ) ); ?></h1>
				<a href="<?php echo esc_url( $back_url ); ?>" class="button"><?php esc_html_e( 'Back to products', 'kalahamoon' ); ?></a>
			</div>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Local product saved.', 'kalahamoon' ); ?></p></div>
			<?php endif; ?>
			<p class="description kalahamoon-products-intro"><?php esc_html_e( 'Local products are available immediately in product blocks, shortcodes, and auto-link rules. They are not changed by catalog syncs.', 'kalahamoon' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kalahamoon-product-form">
				<input type="hidden" name="action" value="kalahamoon_save_product" />
				<input type="hidden" name="product" value="<?php echo esc_attr( (string) $post_id ); ?>" />
				<?php wp_nonce_field( 'kalahamoon_save_product', 'kalahamoon_product_nonce' ); ?>

				<div class="kalahamoon-card">
					<div class="kalahamoon-card-header"><h2><?php esc_html_e( 'Product details', 'kalahamoon' ); ?></h2></div>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="kalahamoon-product-title"><?php esc_html_e( 'Title', 'kalahamoon' ); ?></label></th>
							<td><input required id="kalahamoon-product-title" name="title" type="text" class="regular-text" value="<?php echo esc_attr( $values['title'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="kalahamoon-product-description"><?php esc_html_e( 'Description', 'kalahamoon' ); ?></label></th>
							<td><textarea id="kalahamoon-product-description" name="description" class="large-text" rows="6"><?php echo esc_textarea( $values['description'] ); ?></textarea></td>
						</tr>
						<tr>
							<th scope="row"><label for="kalahamoon-product-price"><?php esc_html_e( 'Price', 'kalahamoon' ); ?></label></th>
							<td><input id="kalahamoon-product-price" name="price" type="number" min="0" step="0.01" value="<?php echo esc_attr( (string) $values['price'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="kalahamoon-product-currency"><?php esc_html_e( 'Currency', 'kalahamoon' ); ?></label></th>
							<td><select id="kalahamoon-product-currency" name="currency">
								<?php foreach ( array( 'IRR', 'USD', 'EUR' ) as $currency ) : ?>
									<option value="<?php echo esc_attr( $currency ); ?>" <?php selected( $values['currency'], $currency ); ?>><?php echo esc_html( $currency ); ?></option>
								<?php endforeach; ?>
							</select></td>
						</tr>
						<tr>
							<th scope="row"><label for="kalahamoon-product-platform"><?php esc_html_e( 'Platform', 'kalahamoon' ); ?></label></th>
							<td><input id="kalahamoon-product-platform" name="platform" type="text" class="regular-text" value="<?php echo esc_attr( $values['platform'] ); ?>" /><p class="description"><?php esc_html_e( 'Use a short source label, such as WordPress or WooCommerce.', 'kalahamoon' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="kalahamoon-product-listing-url"><?php esc_html_e( 'Destination URL', 'kalahamoon' ); ?></label></th>
							<td><input id="kalahamoon-product-listing-url" name="listing_url" type="url" class="large-text" value="<?php echo esc_attr( $values['listing_url'] ); ?>" /><p class="description"><?php esc_html_e( 'Optional URL opened by product calls to action.', 'kalahamoon' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Product image', 'kalahamoon' ); ?></th>
							<td>
								<input type="hidden" id="kalahamoon-product-image-id" name="image_attachment_id" value="<?php echo esc_attr( (string) $values['image_attachment_id'] ); ?>" />
								<input id="kalahamoon-product-image-url" name="image_url" type="url" class="large-text" value="<?php echo esc_attr( $values['image_url'] ); ?>" />
								<p><button type="button" class="button" id="kalahamoon-select-product-image"><?php esc_html_e( 'Choose from Media Library', 'kalahamoon' ); ?></button> <button type="button" class="button-link-delete" id="kalahamoon-clear-product-image"><?php esc_html_e( 'Clear image', 'kalahamoon' ); ?></button></p>
								<div id="kalahamoon-product-image-preview" class="kalahamoon-product-image-preview" <?php echo empty( $values['image_url'] ) ? 'hidden' : ''; ?>><?php if ( ! empty( $values['image_url'] ) ) : ?><img src="<?php echo esc_url( $values['image_url'] ); ?>" alt="" /><?php endif; ?></div>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="kalahamoon-product-status"><?php esc_html_e( 'Status', 'kalahamoon' ); ?></label></th>
							<td><select id="kalahamoon-product-status" name="status"><option value="active" <?php selected( $values['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'kalahamoon' ); ?></option><option value="inactive" <?php selected( $values['status'], 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'kalahamoon' ); ?></option></select></td>
						</tr>
					</table>
				</div>
				<?php submit_button( $is_edit ? __( 'Save local product', 'kalahamoon' ) : __( 'Create product', 'kalahamoon' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_product_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'kalahamoon' ), '', array( 'response' => 403 ) );
		}
		if ( self::is_catalog_consumer() ) {
			wp_die( esc_html__( 'Catalog products are managed in Kalahamoon.', 'kalahamoon' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'kalahamoon_save_product', 'kalahamoon_product_nonce' );

		$post_id = absint( $_POST['product'] ?? 0 );
		$data    = wp_unslash( $_POST );
		$result  = Kalahamoon_Product_Cache::save_manual( is_array( $data ) ? $data : array(), $post_id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'kalahamoon-product-editor', 'product' => (int) $result, 'updated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_product_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'kalahamoon' ), '', array( 'response' => 403 ) );
		}
		if ( self::is_catalog_consumer() ) {
			wp_die( esc_html__( 'Catalog products are managed in Kalahamoon.', 'kalahamoon' ), '', array( 'response' => 403 ) );
		}
		$post_id = absint( $_POST['product'] ?? 0 );
		check_admin_referer( 'kalahamoon_delete_product_' . $post_id, 'kalahamoon_delete_product_nonce' );
		if ( ! Kalahamoon_Product_Cache::is_manual_post( $post_id ) || ! wp_trash_post( $post_id ) ) {
			wp_die( esc_html__( 'This local product could not be moved to the Trash.', 'kalahamoon' ), '', array( 'response' => 400 ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'kalahamoon-products', 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Collections are WordPress editorial curation, not product-source edits.
	 * Keeping this control beside the existing product action avoids a second
	 * manager surface and cannot be overwritten by the next catalog sync.
	 */
	public static function handle_product_collection_assignment(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'kalahamoon' ), '', array( 'response' => 403 ) );
		}
		if ( self::is_catalog_consumer() ) {
			wp_die( esc_html__( 'Catalog collections are managed in Kalahamoon.', 'kalahamoon' ), '', array( 'response' => 403 ) );
		}

		$post_id = absint( $_POST['product'] ?? 0 );
		check_admin_referer( 'kalahamoon_assign_product_collection_' . $post_id, 'kalahamoon_product_collection_nonce' );
		if ( ! $post_id || Kalahamoon_Product_Cache::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'Product not found.', 'kalahamoon' ), '', array( 'response' => 404 ) );
		}

		$term_id = absint( $_POST['collection'] ?? 0 );
		if ( $term_id > 0 ) {
			$term = get_term( $term_id, 'kalahamoon_collection' );
			if ( ! $term instanceof WP_Term ) {
				wp_die( esc_html__( 'Collection not found.', 'kalahamoon' ), '', array( 'response' => 400 ) );
			}
			$result = wp_set_object_terms( $post_id, array( $term_id ), 'kalahamoon_collection', false );
		} else {
			$result = wp_set_object_terms( $post_id, array(), 'kalahamoon_collection', false );
		}
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'kalahamoon-products', 'collection_updated' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * AI Image Studio screen — mount point for the React app
	 * (admin/js/kalahamoon-ai-studio.js).
	 */
	public static function render_ai_studio_page(): void {
		$direction = Kalahamoon_RTL::admin_direction();
		$language  = Kalahamoon_RTL::admin_language();
		?>
		<div class="wrap kalahamoon-ai-studio-wrap" dir="<?php echo esc_attr( $direction ); ?>" lang="<?php echo esc_attr( $language ); ?>">
			<h1><?php esc_html_e( 'AI Image Studio', 'kalahamoon' ); ?></h1>
			<p class="description" style="max-width:720px">
				<?php esc_html_e( 'Enhance a product photo, place it in a new scene, combine several product images into one, or generate a fresh image from a prompt. Results can be saved to your Media Library and applied to the product.', 'kalahamoon' ); ?>
			</p>
			<div id="kalahamoon-ai-studio-root"></div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Analytics
	// -------------------------------------------------------------------------

	public static function handle_csv_export(): void {
		if ( ! isset( $_GET['kalahamoon_export'] ) || $_GET['kalahamoon_export'] !== 'clicks_csv' ) return;
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Unauthorized', 'kalahamoon' ) );
		check_admin_referer( 'kalahamoon_export_csv' );

		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_clicks';
		$rows  = $wpdb->get_results(
			"SELECT c.product_id, p.post_title as product_name, COUNT(*) as clicks
			 FROM {$table} c
			 LEFT JOIN {$wpdb->posts} p ON p.ID = (
				 SELECT ID FROM {$wpdb->posts} wp2
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = wp2.ID
				 WHERE m.meta_key = '_kalahamoon_product_id' AND m.meta_value = c.product_id LIMIT 1
			 )
			 WHERE c.clicked_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
			 GROUP BY c.product_id
			 ORDER BY clicks DESC",
			ARRAY_A
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="kalahamoon-clicks-' . date( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Product ID', 'Product Name', 'Clicks (30d)' ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, array( $row['product_id'], $row['product_name'] ?: $row['product_id'], $row['clicks'] ) );
		}
		fclose( $out );
		exit;
	}

	public static function render_analytics_page(): void {
		if ( self::is_catalog_consumer() ) {
			self::render_catalog_consumer_page();
			return;
		}

		$stats = Kalahamoon_Click_Tracker::get_stats( 30 );
		$total = $stats['total'];
		$by_day     = $stats['byDay'];
		$by_product = $stats['byProduct'];

		// Build SVG bar chart from byDay data
		$chart_html = self::render_svg_bar_chart( $by_day, 30 );

		$export_url = wp_nonce_url(
			admin_url( 'admin.php?page=kalahamoon-analytics&kalahamoon_export=clicks_csv' ),
			'kalahamoon_export_csv'
		);
		$direction  = Kalahamoon_RTL::admin_direction();
		$language   = Kalahamoon_RTL::admin_language();
		?>
		<div class="wrap kalahamoon-analytics" dir="<?php echo esc_attr( $direction ); ?>" lang="<?php echo esc_attr( $language ); ?>">
			<h1 style="display:flex;align-items:center;justify-content:space-between">
				<span><?php esc_html_e( 'Kalahamoon Analytics', 'kalahamoon' ); ?></span>
				<?php if ( $total > 0 ) : ?>
					<a href="<?php echo esc_url( $export_url ); ?>" class="button"><?php esc_html_e( 'Export CSV', 'kalahamoon' ); ?></a>
				<?php endif; ?>
			</h1>

			<!-- Total clicks card -->
			<div style="background:#fff;padding:20px 28px;border:1px solid #ddd;border-radius:8px;display:inline-block;margin:16px 0 24px;min-width:180px;text-align:center">
				<div style="font-size:40px;font-weight:700;color:#1d2327"><?php echo esc_html( Kalahamoon_RTL::format_number( $total ) ); ?></div>
				<div style="color:#646970;margin-top:4px"><?php esc_html_e( 'Total Clicks (last 30 days)', 'kalahamoon' ); ?></div>
			</div>

			<!-- SVG bar chart -->
			<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:24px">
				<h3 style="margin-top:0"><?php esc_html_e( 'Daily Clicks', 'kalahamoon' ); ?></h3>
				<?php echo $chart_html; ?>
			</div>

			<!-- Top products table -->
			<h2><?php esc_html_e( 'Top Products by Clicks', 'kalahamoon' ); ?></h2>
			<?php if ( empty( $by_product ) ) : ?>
				<p style="color:#646970"><?php esc_html_e( 'No click data yet.', 'kalahamoon' ); ?></p>
			<?php else : ?>
			<div class="kalahamoon-table-scroll kalahamoon-table-scroll--compact">
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th>#</th>
					<th><?php esc_html_e( 'Product', 'kalahamoon' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Clicks', 'kalahamoon' ); ?></th>
					<th style="width:100px"><?php esc_html_e( 'Share', 'kalahamoon' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $by_product as $i => $row ) :
					$product = Kalahamoon_Product_Cache::get_by_kalahamoon_id( $row['product_id'] );
					$pct     = $total > 0 ? round( $row['clicks'] / $total * 100, 1 ) : 0;
				?>
					<tr>
						<td style="color:#646970"><?php echo $i + 1; ?></td>
						<td dir="auto"><?php echo esc_html( $product ? $product['title'] : $row['product_id'] ); ?></td>
						<td><?php echo esc_html( Kalahamoon_RTL::format_number( $row['clicks'] ) ); ?></td>
						<td>
							<div style="display:flex;align-items:center;gap:6px">
								<div style="background:#e0e0e0;border-radius:3px;height:6px;flex:1">
									<div style="background:#2271b1;height:6px;border-radius:3px;width:<?php echo $pct; ?>%"></div>
								</div>
								<span style="font-size:11px;color:#646970"><?php echo $pct; ?>%</span>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_svg_bar_chart( array $by_day, int $days ): string {
		if ( empty( $by_day ) ) {
			return '<p style="color:#646970;text-align:center;padding:40px 0">' . esc_html__( 'No click data in the last 30 days.', 'kalahamoon' ) . '</p>';
		}

		// Fill missing days with zeros
		$date_map = array();
		foreach ( $by_day as $row ) {
			$date_map[ $row['date'] ] = (int) $row['clicks'];
		}
		$filled = array();
		for ( $d = $days - 1; $d >= 0; $d-- ) {
			$date           = date( 'Y-m-d', strtotime( "-{$d} days" ) );
			$filled[ $date ] = $date_map[ $date ] ?? 0;
		}

		$max_val  = max( array_values( $filled ) ) ?: 1;
		$count    = count( $filled );
		$width    = 800;
		$height   = 160;
		$pad_l    = 40;
		$pad_r    = 10;
		$pad_t    = 10;
		$pad_b    = 30;
		$chart_w  = $width - $pad_l - $pad_r;
		$chart_h  = $height - $pad_t - $pad_b;
		$bar_w    = max( 2, floor( $chart_w / $count ) - 2 );

		$svg  = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" style="width:100%;height:' . $height . 'px">';
		$svg .= '<style>.kalahamoon-bar{fill:#2271b1;opacity:.85}.kalahamoon-bar:hover{opacity:1}</style>';

		// Y axis
		$svg .= '<line x1="' . $pad_l . '" y1="' . $pad_t . '" x2="' . $pad_l . '" y2="' . ( $pad_t + $chart_h ) . '" stroke="#ddd" stroke-width="1"/>';

		// Horizontal grid lines + labels
		for ( $i = 0; $i <= 4; $i++ ) {
			$y_val = round( $max_val * $i / 4 );
			$y_px  = $pad_t + $chart_h - round( $chart_h * $i / 4 );
			$svg  .= '<line x1="' . $pad_l . '" y1="' . $y_px . '" x2="' . ( $pad_l + $chart_w ) . '" y2="' . $y_px . '" stroke="#f0f0f0" stroke-width="1"/>';
			$svg  .= '<text x="' . ( $pad_l - 4 ) . '" y="' . ( $y_px + 4 ) . '" text-anchor="end" font-size="10" fill="#999">' . $y_val . '</text>';
		}

		$idx = 0;
		foreach ( $filled as $date => $clicks ) {
			$bar_h  = $chart_h > 0 ? round( $chart_h * $clicks / $max_val ) : 0;
			$x      = $pad_l + round( $idx * $chart_w / $count );
			$y      = $pad_t + $chart_h - $bar_h;
			$svg   .= '<rect class="kalahamoon-bar" x="' . $x . '" y="' . $y . '" width="' . $bar_w . '" height="' . $bar_h . '" rx="2"><title>' . esc_attr( $date . ': ' . $clicks ) . '</title></rect>';

			// Show date label every 7 days
			if ( $idx % 7 === 0 ) {
				$label = date( 'M d', strtotime( $date ) );
				$svg  .= '<text x="' . ( $x + $bar_w / 2 ) . '" y="' . ( $pad_t + $chart_h + 18 ) . '" text-anchor="middle" font-size="10" fill="#999">' . esc_html( $label ) . '</text>';
			}
			$idx++;
		}

		$svg .= '</svg>';
		return $svg;
	}

	// -------------------------------------------------------------------------
	// Affiliate Links
	// -------------------------------------------------------------------------

	public static function render_links_page(): void {
		if ( self::is_catalog_consumer() ) {
			self::render_catalog_consumer_page();
			return;
		}

		global $wpdb;
		$table    = $wpdb->prefix . 'kalahamoon_affiliate_links';
		$provider = sanitize_text_field( $_GET['provider'] ?? '' );

		$where  = $provider ? $wpdb->prepare( 'WHERE provider = %s', $provider ) : '';
		$links  = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT 100", ARRAY_A );

		// Distinct providers for filter
		$providers = $wpdb->get_col( "SELECT DISTINCT provider FROM {$table} ORDER BY provider" );

		$base_url  = admin_url( 'admin.php?page=kalahamoon-links' );
		$direction = Kalahamoon_RTL::admin_direction();
		$language  = Kalahamoon_RTL::admin_language();
		?>
		<div class="wrap kalahamoon-links" dir="<?php echo esc_attr( $direction ); ?>" lang="<?php echo esc_attr( $language ); ?>">
			<h1><?php esc_html_e( 'Affiliate Links', 'kalahamoon' ); ?></h1>

			<form method="get" action="<?php echo esc_url( $base_url ); ?>" class="kalahamoon-admin-filters">
				<input type="hidden" name="page" value="kalahamoon-links" />
				<select name="provider">
					<option value=""><?php esc_html_e( 'All Providers', 'kalahamoon' ); ?></option>
					<?php foreach ( $providers as $p ) : ?>
						<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $provider, $p ); ?>><?php echo esc_html( $p ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'kalahamoon' ); ?>" />
				<?php if ( $provider ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Clear', 'kalahamoon' ); ?></a>
				<?php endif; ?>
			</form>

			<div class="kalahamoon-table-scroll">
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th><?php esc_html_e( 'Campaign', 'kalahamoon' ); ?></th>
					<th><?php esc_html_e( 'Cloaked URL', 'kalahamoon' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Provider', 'kalahamoon' ); ?></th>
					<th style="width:70px"><?php esc_html_e( 'Clicks', 'kalahamoon' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Status', 'kalahamoon' ); ?></th>
					<th style="width:130px"><?php esc_html_e( 'Health', 'kalahamoon' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $links ) ) : ?>
					<tr><td colspan="6" style="text-align:center;padding:30px;color:#646970"><?php esc_html_e( 'No affiliate links found.', 'kalahamoon' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $links ?: array() as $link ) :
					$cloaked_url = home_url( '/go/' . $link['slug'] );
					$health      = get_transient( 'kalahamoon_link_health_' . $link['id'] );
				?>
					<tr>
						<td dir="auto"><?php echo esc_html( $link['campaign_title'] ?: '—' ); ?></td>
						<td>
							<code style="font-size:11px" dir="ltr"><?php echo esc_html( $cloaked_url ); ?></code>
							<button type="button" class="button button-small kalahamoon-copy-btn" data-copy="<?php echo esc_attr( $cloaked_url ); ?>" style="margin-inline-start:6px"><?php esc_html_e( 'Copy', 'kalahamoon' ); ?></button>
						</td>
						<td dir="auto"><?php echo esc_html( $link['provider'] ); ?></td>
						<td><?php echo esc_html( Kalahamoon_RTL::format_number( $link['clicks'] ) ); ?></td>
						<td>
							<span style="background:<?php echo $link['status'] === 'active' ? '#d1fae5' : '#fee2e2'; ?>;color:<?php echo $link['status'] === 'active' ? '#065f46' : '#991b1b'; ?>;padding:2px 8px;border-radius:10px;font-size:11px">
								<?php echo esc_html( $link['status'] ); ?>
							</span>
						</td>
						<td>
							<?php if ( $health === false ) : ?>
								<button type="button" class="button button-small kalahamoon-check-health" data-id="<?php echo (int) $link['id']; ?>" data-nonce="<?php echo wp_create_nonce( 'kalahamoon_check_health_' . $link['id'] ); ?>"><?php esc_html_e( 'Check', 'kalahamoon' ); ?></button>
							<?php elseif ( $health >= 200 && $health < 400 ) : ?>
								<span style="color:#00a32a">✓ <?php echo esc_html( $health ); ?></span>
							<?php else : ?>
								<span style="color:#d63638">✗ <?php echo esc_html( $health ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</div>
		<?php self::print_copy_script(); ?>
		<script>
		document.querySelectorAll('.kalahamoon-check-health').forEach(function(btn) {
			btn.addEventListener('click', async function() {
				const id    = this.dataset.id;
				const nonce = this.dataset.nonce;
				const cell  = this.parentElement;
				cell.textContent = '...';
				try {
					const res  = await fetch(ajaxurl + '?action=kalahamoon_check_link_health&id=' + id + '&_wpnonce=' + nonce);
					const data = await res.json();
					if ( data.success ) {
						const code = data.data.code;
						cell.innerHTML = code >= 200 && code < 400
							? '<span style="color:#00a32a">✓ ' + code + '</span>'
							: '<span style="color:#d63638">✗ ' + code + '</span>';
					} else {
						cell.innerHTML = '<span style="color:#d63638"><?php echo esc_js( __( 'Error', 'kalahamoon' ) ); ?></span>';
					}
				} catch(e) { cell.innerHTML = '<span style="color:#d63638"><?php echo esc_js( __( 'Error', 'kalahamoon' ) ); ?></span>'; }
			});
		});
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Auto Links
	// -------------------------------------------------------------------------

	public static function render_auto_links_page(): void {
		if ( self::is_catalog_consumer() ) {
			self::render_catalog_consumer_page();
			return;
		}

		global $wpdb;
		$table     = $wpdb->prefix . 'kalahamoon_auto_links';
		$links     = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY priority ASC, id DESC", ARRAY_A );
		$nonce     = wp_create_nonce( 'kalahamoon_admin' );
		$direction = Kalahamoon_RTL::admin_direction();
		$language  = Kalahamoon_RTL::admin_language();
		?>
		<div class="wrap kalahamoon-auto-links" dir="<?php echo esc_attr( $direction ); ?>" lang="<?php echo esc_attr( $language ); ?>">
			<h1><?php esc_html_e( 'Auto Links', 'kalahamoon' ); ?></h1>
			<p style="color:#646970"><?php esc_html_e( 'Automatically convert keywords in your post content into affiliate links. Keywords are matched case-insensitively and only text outside of existing links is affected.', 'kalahamoon' ); ?></p>

			<!-- Add new keyword form -->
			<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;max-width:600px;margin-bottom:24px">
				<h3 style="margin-top:0"><?php esc_html_e( 'Add Keyword Mapping', 'kalahamoon' ); ?></h3>
				<table class="form-table" style="margin:0">
					<tr>
						<th style="padding:8px 0;width:140px"><label for="al-keyword"><?php esc_html_e( 'Keyword', 'kalahamoon' ); ?></label></th>
						<td><input id="al-keyword" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. vacuum cleaner', 'kalahamoon' ); ?>" /></td>
					</tr>
					<tr>
						<th style="padding:8px 0"><label for="al-product-id"><?php esc_html_e( 'Product ID', 'kalahamoon' ); ?></label></th>
						<td>
							<input id="al-product-id" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'Kalahamoon product ID', 'kalahamoon' ); ?>" />
							<button type="button" id="kalahamoon-select-auto-link-product" class="button"><?php esc_html_e( 'Select product', 'kalahamoon' ); ?></button>
							<p class="description"><?php printf( __( 'Choose a cached product, or copy its ID from the <a href="%s">Products page</a>.', 'kalahamoon' ), esc_url( admin_url( 'admin.php?page=kalahamoon-products' ) ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th style="padding:8px 0"><label for="al-max"><?php esc_html_e( 'Max per post', 'kalahamoon' ); ?></label></th>
						<td><input id="al-max" type="number" value="1" min="1" max="10" style="width:70px" /></td>
					</tr>
					<tr>
						<th style="padding:8px 0"><label for="al-priority"><?php esc_html_e( 'Priority', 'kalahamoon' ); ?></label></th>
						<td>
							<input id="al-priority" type="number" value="10" min="1" max="100" style="width:70px" />
							<p class="description"><?php esc_html_e( 'Lower number = matched first.', 'kalahamoon' ); ?></p>
						</td>
					</tr>
				</table>
				<p>
					<button type="button" id="al-add-btn" class="button button-primary" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Add Mapping', 'kalahamoon' ); ?></button>
					<span id="al-add-status" style="margin-inline-start:12px"></span>
				</p>
			</div>

			<!-- Existing mappings -->
			<div class="kalahamoon-table-scroll kalahamoon-table-scroll--compact">
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th><?php esc_html_e( 'Keyword', 'kalahamoon' ); ?></th>
					<th><?php esc_html_e( 'Product ID', 'kalahamoon' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Max/Post', 'kalahamoon' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Priority', 'kalahamoon' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Active', 'kalahamoon' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Actions', 'kalahamoon' ); ?></th>
				</tr></thead>
				<tbody id="al-table-body">
				<?php if ( empty( $links ) ) : ?>
					<tr id="al-empty-row"><td colspan="6" style="text-align:center;padding:30px;color:#646970"><?php esc_html_e( 'No keyword mappings yet.', 'kalahamoon' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $links as $link ) : ?>
					<tr id="al-row-<?php echo (int) $link['id']; ?>">
						<td><strong dir="auto"><?php echo esc_html( $link['keyword'] ); ?></strong></td>
						<td><code style="font-size:11px" dir="ltr"><?php echo esc_html( $link['product_id'] ?: '—' ); ?></code></td>
						<td><?php echo (int) $link['max_per_post']; ?></td>
						<td><?php echo (int) $link['priority']; ?></td>
						<td>
							<button type="button" class="button button-small al-toggle" data-id="<?php echo (int) $link['id']; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" style="background:<?php echo $link['is_active'] ? '#d1fae5' : '#f0f0f1'; ?>">
								<?php echo $link['is_active'] ? esc_html__( 'On', 'kalahamoon' ) : esc_html__( 'Off', 'kalahamoon' ); ?>
							</button>
						</td>
						<td>
							<button type="button" class="button button-small al-delete" data-id="<?php echo (int) $link['id']; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" style="color:#d63638"><?php esc_html_e( 'Delete', 'kalahamoon' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</div>
		<script>
			(function(){
				const nonce = '<?php echo esc_js( $nonce ); ?>';
				const productInput = document.getElementById('al-product-id');
				const productPickerButton = document.getElementById('kalahamoon-select-auto-link-product');
				productPickerButton?.addEventListener('click', function() {
					if (!window.kalahamoonPicker || !productInput) return;
					window.kalahamoonPicker.open({
						multiple: false,
						initialIds: productInput.value ? [productInput.value] : [],
						title: '<?php echo esc_js( __( 'Select a product for auto-linking', 'kalahamoon' ) ); ?>',
						onSelect: function(id) { productInput.value = Array.isArray(id) ? (id[0] || '') : id; },
					});
				});

			async function post(action, data) {
				const params = new URLSearchParams({ action, _wpnonce: nonce, ...data });
				const res = await fetch(ajaxurl, { method: 'POST', body: params });
				return res.json();
			}

			function escapeHtml(value) {
				return String(value).replace(/[&<>"']/g, function(ch) {
					switch (ch) {
						case '&': return '&amp;';
						case '<': return '&lt;';
						case '>': return '&gt;';
						case '"': return '&quot;';
						case "'": return '&#039;';
						default: return ch;
					}
				});
			}

			document.getElementById('al-add-btn')?.addEventListener('click', async function() {
				if (this.disabled) return;
				const keyword   = document.getElementById('al-keyword').value.trim();
				const productId = document.getElementById('al-product-id').value.trim();
				const max       = document.getElementById('al-max').value;
				const priority  = document.getElementById('al-priority').value;
				const status    = document.getElementById('al-add-status');

				if ( ! keyword || ! productId ) {
					status.textContent = '<?php echo esc_js( __( 'Please fill in keyword and product ID.', 'kalahamoon' ) ); ?>';
					return;
				}

				this.disabled = true;
				this.setAttribute('aria-busy', 'true');
				status.textContent = '<?php echo esc_js( __( 'Saving...', 'kalahamoon' ) ); ?>';
				try {
					const data = await post('kalahamoon_add_auto_link', { keyword, product_id: productId, max_per_post: max, priority });

					if ( data.success ) {
						status.textContent = '';
						document.getElementById('al-keyword').value = '';
						document.getElementById('al-product-id').value = '';
						const empty = document.getElementById('al-empty-row');
						if ( empty ) empty.remove();
						const tbody = document.getElementById('al-table-body');
						const tr = document.createElement('tr');
						tr.id = 'al-row-' + data.data.id;
						tr.innerHTML = `<td><strong dir="auto">${escapeHtml(keyword)}</strong></td><td><code style="font-size:11px" dir="ltr">${escapeHtml(productId)}</code></td><td>${escapeHtml(max)}</td><td>${escapeHtml(priority)}</td><td><button type="button" class="button button-small al-toggle" data-id="${escapeHtml(data.data.id)}" data-nonce="${escapeHtml(nonce)}" style="background:#d1fae5"><?php echo esc_js( __( 'On', 'kalahamoon' ) ); ?></button></td><td><button type="button" class="button button-small al-delete" data-id="${escapeHtml(data.data.id)}" data-nonce="${escapeHtml(nonce)}" style="color:#d63638"><?php echo esc_js( __( 'Delete', 'kalahamoon' ) ); ?></button></td>`;
						tbody.prepend( tr );
						bindRowButtons( tr );
					} else {
						status.textContent = '❌ ' + (data.data?.message || '<?php echo esc_js( __( 'Error', 'kalahamoon' ) ); ?>');
					}
				} catch (error) {
					status.textContent = '❌ ' + (error.message || '<?php echo esc_js( __( 'Error', 'kalahamoon' ) ); ?>');
				} finally {
					this.disabled = false;
					this.removeAttribute('aria-busy');
				}
			});

			function bindRowButtons(scope) {
				scope.querySelectorAll('.al-delete').forEach(btn => {
					btn.addEventListener('click', async function() {
						if (this.disabled) return;
						if ( ! confirm('<?php echo esc_js( __( 'Delete this mapping?', 'kalahamoon' ) ); ?>') ) return;
						const id = this.dataset.id;
						this.disabled = true;
						this.setAttribute('aria-busy', 'true');
						try {
							const d = await post('kalahamoon_delete_auto_link', { id });
							if ( d.success ) {
								document.getElementById('al-row-' + id)?.remove();
							}
						} catch (error) {
							const rowStatus = document.getElementById('al-add-status');
							if (rowStatus) rowStatus.textContent = '❌ ' + (error.message || '<?php echo esc_js( __( 'Error', 'kalahamoon' ) ); ?>');
						} finally {
							this.disabled = false;
							this.removeAttribute('aria-busy');
						}
					});
				});
				scope.querySelectorAll('.al-toggle').forEach(btn => {
					btn.addEventListener('click', async function() {
						if (this.disabled) return;
						const id = this.dataset.id;
						this.disabled = true;
						this.setAttribute('aria-busy', 'true');
						try {
							const d = await post('kalahamoon_toggle_auto_link', { id });
							if ( d.success ) {
								const on = d.data.is_active;
								this.textContent = on ? '<?php echo esc_js( __( 'On', 'kalahamoon' ) ); ?>' : '<?php echo esc_js( __( 'Off', 'kalahamoon' ) ); ?>';
								this.style.background = on ? '#d1fae5' : '#f0f0f1';
							}
						} catch (error) {
							const rowStatus = document.getElementById('al-add-status');
							if (rowStatus) rowStatus.textContent = '❌ ' + (error.message || '<?php echo esc_js( __( 'Error', 'kalahamoon' ) ); ?>');
						} finally {
							this.disabled = false;
							this.removeAttribute('aria-busy');
						}
					});
				});
			}

			bindRowButtons(document);
		})();
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// WP Dashboard widget
	// -------------------------------------------------------------------------

	public static function add_dashboard_widget(): void {
		wp_add_dashboard_widget(
			'kalahamoon_dashboard',
			__( 'Kalahamoon — Overview', 'kalahamoon' ),
			array( __CLASS__, 'render_dashboard_widget' )
		);
	}

	public static function render_dashboard_widget(): void {
		$stats           = Kalahamoon_Click_Tracker::get_stats( 7 );
		$products        = Kalahamoon_Product_Cache::get_all( array( 'limit' => 1 ) );
		$public_products = Kalahamoon_Product_Cache::public_ready_count();
		$last            = get_option( 'kalahamoon_last_sync', __( 'Never', 'kalahamoon' ) );

		echo '<ul>';
		echo '<li><strong>' . esc_html__( 'Products:', 'kalahamoon' ) . '</strong> ' . esc_html( Kalahamoon_RTL::format_number( $products['total'] ) ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Ready for public catalog', 'kalahamoon' ) . ':</strong> ' . esc_html( Kalahamoon_RTL::format_number( $public_products ) ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Clicks (7d):', 'kalahamoon' ) . '</strong> ' . esc_html( Kalahamoon_RTL::format_number( $stats['total'] ) ) . '</li>';
		echo '<li><strong>' . esc_html__( 'Last Sync:', 'kalahamoon' ) . '</strong> ' . esc_html( $last ) . '</li>';
		echo '</ul>';
		if ( current_user_can( 'manage_options' ) ) {
			// Dashboard viewers without plugin access must not be sent to a page they cannot use.
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=kalahamoon' ) ) . '" class="button button-small">' . esc_html__( 'View Dashboard', 'kalahamoon' ) . '</a> ';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=kalahamoon-products' ) ) . '" class="button button-secondary button-small">' . esc_html__( 'Review product readiness', 'kalahamoon' ) . '</a></p>';
		}
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public static function ajax_test_connection(): void {
		check_ajax_referer( 'kalahamoon_admin' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Unauthorized', 'kalahamoon' ) ) );

		$client = new Kalahamoon_API_Client();
		$result = $client->test_connection();

		if ( is_wp_error( $result ) ) {
			update_option( 'kalahamoon_connected', false );
			do_action( 'kalahamoon_connection_state_changed' );
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		update_option( 'kalahamoon_connected', true );
		do_action( 'kalahamoon_connection_state_changed' );
		wp_send_json_success( array( 'message' => __( 'Connected', 'kalahamoon' ) ) );
	}

	public static function ajax_sync_now(): void {
		check_ajax_referer( 'kalahamoon_admin' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Unauthorized', 'kalahamoon' ) ) );

		$api    = new Kalahamoon_API_Products();
		$result = $api->sync_all();

		// A partial cache write is still a failed synchronization because it did
		// not establish an authoritative snapshot or run safe reconciliation.
		if ( empty( $result['complete'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'], 'errors' => $result['errors'] ?? 1 ) );
		}

		wp_send_json_success( $result );
	}

	public static function ajax_add_auto_link(): void {
		check_ajax_referer( 'kalahamoon_admin' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Unauthorized', 'kalahamoon' ) ) );

		global $wpdb;
		$keyword    = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
		$product_id = sanitize_text_field( wp_unslash( $_POST['product_id'] ?? '' ) );
		$max        = max( 1, min( 10, absint( $_POST['max_per_post'] ?? 1 ) ) );
		$priority   = max( 1, min( 100, absint( $_POST['priority'] ?? 10 ) ) );

		if ( ! $keyword || ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing fields', 'kalahamoon' ) ) );
		}
		if ( ! Kalahamoon_Product_Cache::get_by_kalahamoon_id( $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Selected product was not found in the local catalog.', 'kalahamoon' ) ) );
		}

		$wpdb->insert( $wpdb->prefix . 'kalahamoon_auto_links', array(
			'keyword'      => $keyword,
			'product_id'   => $product_id,
			'max_per_post' => $max,
			'priority'     => $priority,
			'is_active'    => 1,
		), array( '%s', '%s', '%d', '%d', '%d' ) );

		wp_send_json_success( array( 'id' => $wpdb->insert_id ) );
	}

	public static function ajax_delete_auto_link(): void {
		check_ajax_referer( 'kalahamoon_admin' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Unauthorized', 'kalahamoon' ) ) );

		global $wpdb;
		$id = absint( $_POST['id'] ?? 0 );
		$wpdb->delete( $wpdb->prefix . 'kalahamoon_auto_links', array( 'id' => $id ), array( '%d' ) );
		wp_send_json_success();
	}

	public static function ajax_toggle_auto_link(): void {
		check_ajax_referer( 'kalahamoon_admin' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Unauthorized', 'kalahamoon' ) ) );

		global $wpdb;
		$id      = absint( $_POST['id'] ?? 0 );
		$current = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$wpdb->prefix}kalahamoon_auto_links WHERE id = %d", $id ) );
		$new_val = $current ? 0 : 1;
		$wpdb->update( $wpdb->prefix . 'kalahamoon_auto_links', array( 'is_active' => $new_val ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
		wp_send_json_success( array( 'is_active' => (bool) $new_val ) );
	}

	public static function ajax_check_link_health(): void {
		$id    = absint( $_GET['id'] ?? 0 );
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'kalahamoon_check_health_' . $id ) ) wp_send_json_error( array( 'message' => __( 'Bad nonce', 'kalahamoon' ) ) );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Unauthorized', 'kalahamoon' ) ) );

		global $wpdb;
		$url = $wpdb->get_var( $wpdb->prepare( "SELECT destination_url FROM {$wpdb->prefix}kalahamoon_affiliate_links WHERE id = %d", $id ) );

		if ( ! $url ) {
			wp_send_json_error( array( 'message' => __( 'Link not found', 'kalahamoon' ) ) );
		}

		$response = wp_remote_head( $url, array( 'timeout' => 10, 'redirection' => 5 ) );
		$code     = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );

		set_transient( 'kalahamoon_link_health_' . $id, $code, WEEK_IN_SECONDS );
		wp_send_json_success( array( 'code' => $code ) );
	}

	public static function ajax_oauth_disconnect(): void {
		check_ajax_referer( 'kalahamoon_admin' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'Unauthorized', 'kalahamoon' ) ) );

		Kalahamoon_Auth::revoke_tokens();
		wp_send_json_success();
	}

	// -------------------------------------------------------------------------
	// Cron & intervals
	// -------------------------------------------------------------------------

	public static function add_cron_interval( array $schedules ): array {
		$hours = (int) get_option( 'kalahamoon_sync_interval', 6 );
		$schedules['kalahamoon_sync_interval'] = array(
			'interval' => $hours * HOUR_IN_SECONDS,
			'display'  => sprintf( __( 'Every %d hours', 'kalahamoon' ), $hours ),
		);
		return $schedules;
	}

	public static function cron_sync(): void {
		if ( self::is_catalog_consumer() ) {
			self::run_catalog_consumer_sync();
			return;
		}

		$client = new Kalahamoon_API_Client();
		if ( ! $client->is_connected() ) {
			return;
		}

		$api = new Kalahamoon_API_Products();
		$api->sync_all();
	}

	/**
	 * Deliberately hook-only entry point for a server scheduler or WP-CLI. The
	 * consumer lock protects it when an operator and scheduler run concurrently.
	 */
	public static function run_catalog_consumer_sync(): void {
		if ( ! self::is_catalog_consumer() ) {
			return;
		}

		$client = new Kalahamoon_API_Client();
		if ( ! $client->is_connected() ) {
			return;
		}

		( new Kalahamoon_Catalog_Consumer( $client ) )->sync();
	}

	// -------------------------------------------------------------------------
	// Shared helpers
	// -------------------------------------------------------------------------

	private static function print_copy_script(): void {
		?>
		<script>
		document.querySelectorAll('.kalahamoon-copy-btn').forEach(function(btn) {
			btn.addEventListener('click', function() {
				const text = this.dataset.copy;
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText(text);
				} else {
					const ta = document.createElement('textarea');
					ta.value = text; ta.dir = 'ltr'; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
				}
				const orig = this.textContent;
				this.textContent = '✓';
				setTimeout(() => { this.textContent = orig; }, 1200);
			});
		});
		</script>
		<?php
	}
}
