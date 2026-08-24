<?php
/**
 * Minimal no-secret administration surfaces for editorial operations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Admin {
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'woocommerce_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'Chidemoon AI', 'chidemoon-ai' ),
			__( 'Chidemoon AI', 'chidemoon-ai' ),
			Chidemoon_AI_Capabilities::GENERATE,
			'chidemoon-ai',
			array( __CLASS__, 'overview' ),
			'dashicons-admin-generic',
			56
		);
		add_submenu_page( 'chidemoon-ai', __( 'Overview', 'chidemoon-ai' ), __( 'Overview', 'chidemoon-ai' ), Chidemoon_AI_Capabilities::GENERATE, 'chidemoon-ai', array( __CLASS__, 'overview' ) );
		add_submenu_page( 'chidemoon-ai', __( 'Draft Studio', 'chidemoon-ai' ), __( 'Draft Studio', 'chidemoon-ai' ), Chidemoon_AI_Capabilities::GENERATE, 'chidemoon-ai-drafts', array( __CLASS__, 'draft_studio' ) );
		add_submenu_page( 'chidemoon-ai', __( 'Image Studio', 'chidemoon-ai' ), __( 'Image Studio', 'chidemoon-ai' ), Chidemoon_AI_Capabilities::GENERATE, 'chidemoon-ai-images', array( __CLASS__, 'image_studio' ) );
		add_submenu_page( 'chidemoon-ai', __( 'Comparison Studio', 'chidemoon-ai' ), __( 'Comparison Studio', 'chidemoon-ai' ), Chidemoon_AI_Capabilities::GENERATE, 'chidemoon-ai-comparisons', array( __CLASS__, 'comparison_studio' ) );
		add_submenu_page( 'chidemoon-ai', __( 'Review Queue', 'chidemoon-ai' ), __( 'Review Queue', 'chidemoon-ai' ), Chidemoon_AI_Capabilities::REVIEW, 'chidemoon-ai-review', array( __CLASS__, 'review_queue' ) );
		add_submenu_page( 'chidemoon-ai', __( 'Audit & Usage', 'chidemoon-ai' ), __( 'Audit & Usage', 'chidemoon-ai' ), Chidemoon_AI_Capabilities::VIEW_AUDIT, 'chidemoon-ai-audit', array( __CLASS__, 'audit_usage' ) );
		add_submenu_page( 'chidemoon-ai', __( 'Settings', 'chidemoon-ai' ), __( 'Settings', 'chidemoon-ai' ), Chidemoon_AI_Capabilities::MANAGE, 'chidemoon-ai-settings', array( __CLASS__, 'settings' ) );
	}

	public static function overview(): void {
		self::guard( Chidemoon_AI_Capabilities::GENERATE );
		$provider  = Chidemoon_AI_Provider_Factory::create();
		$scheduler = Chidemoon_AI_Runner::scheduler_health();
		$counts    = self::job_counts();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Chidemoon AI Overview', 'chidemoon-ai' ); ?></h1>
			<p><?php esc_html_e( 'Every output is a review-gated suggestion. This plugin cannot publish content, change affiliate URLs, or replace a live product image.', 'chidemoon-ai' ); ?></p>
			<table class="widefat striped" style="max-width: 900px">
				<tbody>
					<tr><th><?php esc_html_e( 'Provider', 'chidemoon-ai' ); ?></th><td><?php echo esc_html( is_wp_error( $provider ) ? __( 'Not configured', 'chidemoon-ai' ) : $provider->name() ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Background runner', 'chidemoon-ai' ); ?></th><td><?php echo esc_html( (string) $scheduler['driver'] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Queued', 'chidemoon-ai' ); ?></th><td><?php echo esc_html( (string) $counts[ Chidemoon_AI_State_Machine::QUEUED ] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Needs review', 'chidemoon-ai' ); ?></th><td><?php echo esc_html( (string) $counts[ Chidemoon_AI_State_Machine::REVIEW_REQUIRED ] ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Failed', 'chidemoon-ai' ); ?></th><td><?php echo esc_html( (string) $counts[ Chidemoon_AI_State_Machine::FAILED ] ); ?></td></tr>
				</tbody>
			</table>
			<h2><?php esc_html_e( 'Editorial safeguards', 'chidemoon-ai' ); ?></h2>
			<ul class="ul-disc">
				<li><?php esc_html_e( 'Text and comparison requests use only recorded local WordPress/WooCommerce evidence.', 'chidemoon-ai' ); ?></li>
				<li><?php esc_html_e( 'Generated media is copied into the Media Library and requires review before use.', 'chidemoon-ai' ); ?></li>
				<li><?php esc_html_e( 'The public assistant searches published content only and has no AI tools or write access.', 'chidemoon-ai' ); ?></li>
			</ul>
		</div>
		<?php
	}

	public static function draft_studio(): void {
		self::guard( Chidemoon_AI_Capabilities::GENERATE );
		self::studio_page( 'text', __( 'Draft Studio', 'chidemoon-ai' ), __( 'Select current local WordPress evidence. Generated text remains in the Review Queue until a reviewer approves and applies it to a draft.', 'chidemoon-ai' ) );
	}

	public static function image_studio(): void {
		self::guard( Chidemoon_AI_Capabilities::GENERATE );
		self::studio_page( 'image', __( 'Image Studio', 'chidemoon-ai' ), __( 'Use local Media Library attachment IDs only. Generated images are validated, copied locally, and never replace a featured image automatically.', 'chidemoon-ai' ) );
	}

	public static function comparison_studio(): void {
		self::guard( Chidemoon_AI_Capabilities::GENERATE );
		self::studio_page( 'comparison', __( 'Comparison Studio', 'chidemoon-ai' ), __( 'Compare two to four editable WooCommerce products. Unsupported claims are retained as review flags rather than silently becoming product facts.', 'chidemoon-ai' ) );
	}

	public static function review_queue(): void {
		self::guard( Chidemoon_AI_Capabilities::REVIEW );
		$jobs = array_merge(
			Chidemoon_AI_Repository::list( Chidemoon_AI_State_Machine::REVIEW_REQUIRED, 100 ),
			Chidemoon_AI_Repository::list( Chidemoon_AI_State_Machine::APPROVED, 100 )
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Review Queue', 'chidemoon-ai' ); ?></h1>
			<p><?php esc_html_e( 'Approval only makes an output eligible for draft application. It never publishes or changes an affiliate destination.', 'chidemoon-ai' ); ?></p>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Job', 'chidemoon-ai' ); ?></th><th><?php esc_html_e( 'Type', 'chidemoon-ai' ); ?></th><th><?php esc_html_e( 'Target', 'chidemoon-ai' ); ?></th><th><?php esc_html_e( 'Created', 'chidemoon-ai' ); ?></th><th><?php esc_html_e( 'Actions', 'chidemoon-ai' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $jobs ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'There are no generated outputs awaiting review.', 'chidemoon-ai' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $jobs as $job ) : ?>
						<tr>
							<td><?php echo esc_html( '#' . (string) $job['id'] ); ?></td>
							<td><?php echo esc_html( (string) $job['job_type'] ); ?></td>
							<td><?php echo esc_html( (string) ( $job['target_post_id'] ?: '—' ) ); ?></td>
							<td><?php echo esc_html( (string) $job['created_at'] ); ?></td>
							<td>
								<?php if ( Chidemoon_AI_State_Machine::APPROVED === $job['state'] ) : ?>
									<button type="button" class="button button-primary" data-chidemoon-ai-review="apply" data-job-id="<?php echo esc_attr( (string) $job['id'] ); ?>"><?php esc_html_e( 'Apply to draft', 'chidemoon-ai' ); ?></button>
								<?php else : ?>
									<button type="button" class="button" data-chidemoon-ai-review="approve" data-job-id="<?php echo esc_attr( (string) $job['id'] ); ?>"><?php esc_html_e( 'Approve', 'chidemoon-ai' ); ?></button>
								<?php endif; ?>
								<button type="button" class="button" data-chidemoon-ai-review="reject" data-job-id="<?php echo esc_attr( (string) $job['id'] ); ?>"><?php esc_html_e( 'Reject', 'chidemoon-ai' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function audit_usage(): void {
		self::guard( Chidemoon_AI_Capabilities::VIEW_AUDIT );
		$usage = Chidemoon_AI_Usage::summary();
		$jobs  = Chidemoon_AI_Repository::list( '', 50 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AI Audit & Usage', 'chidemoon-ai' ); ?></h1>
			<p><?php esc_html_e( 'This restricted view contains provenance and usage estimates, never provider credentials.', 'chidemoon-ai' ); ?></p>
			<ul class="ul-disc">
				<li><?php echo esc_html( sprintf( __( 'Today: %1$d requests / %2$d limit', 'chidemoon-ai' ), $usage['today_requests'], $usage['daily_limit'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'This month: %1$d requests / %2$d limit', 'chidemoon-ai' ), $usage['month_requests'], $usage['monthly_limit'] ) ); ?></li>
				<li><?php echo esc_html( sprintf( __( 'Reserved monthly cost: %1$.4f / %2$.4f', 'chidemoon-ai' ), $usage['month_cost'], $usage['monthly_budget'] ) ); ?></li>
			</ul>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Job', 'chidemoon-ai' ); ?></th><th><?php esc_html_e( 'State', 'chidemoon-ai' ); ?></th><th><?php esc_html_e( 'Provider / model', 'chidemoon-ai' ); ?></th><th><?php esc_html_e( 'Sources', 'chidemoon-ai' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $jobs as $job ) : ?>
					<?php $provenance = is_array( $job['provenance'] ?? null ) ? $job['provenance'] : array(); ?>
					<tr>
						<td><?php echo esc_html( '#' . (string) $job['id'] ); ?></td>
						<td><?php echo esc_html( (string) $job['state'] ); ?></td>
						<td><?php echo esc_html( trim( (string) ( $provenance['provider'] ?? '' ) . ' / ' . (string) ( $provenance['model'] ?? '' ), ' /' ) ); ?></td>
						<td><?php echo esc_html( implode( ', ', array_map( 'strval', is_array( $provenance['source_ids'] ?? null ) ? $provenance['source_ids'] : array() ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function settings(): void {
		self::guard( Chidemoon_AI_Capabilities::MANAGE );
		$provider = Chidemoon_AI_Provider_Factory::create();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Chidemoon AI Settings', 'chidemoon-ai' ); ?></h1>
			<p><?php esc_html_e( 'Provider secrets are intentionally host-managed. They are never saved in WordPress options or exposed in this panel.', 'chidemoon-ai' ); ?></p>
			<p><strong><?php esc_html_e( 'Provider status:', 'chidemoon-ai' ); ?></strong> <?php echo esc_html( is_wp_error( $provider ) ? __( 'Not configured', 'chidemoon-ai' ) : __( 'Configured', 'chidemoon-ai' ) ); ?></p>
			<p><?php esc_html_e( 'Required host environment values: CHIDEMOON_AI_PROVIDER_BASE_URL and CHIDEMOON_AI_API_KEY. Optional non-secret controls include text/image model names, quotas, budget, evidence freshness, and provider timeout.', 'chidemoon-ai' ); ?></p>
		</div>
		<?php
	}

	public static function woocommerce_notice(): void {
		if ( current_user_can( 'activate_plugins' ) && ! function_exists( 'wc_get_product' ) ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'Chidemoon AI can provide published-content retrieval, but WooCommerce must be active before comparison jobs are available.', 'chidemoon-ai' ) );
		}
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'chidemoon-ai' ) || ! current_user_can( Chidemoon_AI_Capabilities::GENERATE ) ) {
			return;
		}

		$handle = 'chidemoon-ai-admin';
		wp_enqueue_script( $handle, CHIDEMOON_AI_URL . 'assets/js/admin.js', array(), CHIDEMOON_AI_VERSION, true );
		wp_add_inline_script(
			$handle,
			'window.ChidemoonAiAdmin = ' . wp_json_encode(
				array(
					'root'       => esc_url_raw( rest_url( 'chidemoon-ai/v1/' ) ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'queued'     => __( 'AI job queued. The page will refresh shortly.', 'chidemoon-ai' ),
					'error'      => __( 'The AI request could not be completed.', 'chidemoon-ai' ),
					'reviewed'   => __( 'The AI review action completed.', 'chidemoon-ai' ),
				)
			) . ';',
			'before'
		);
	}

	private static function studio_page( string $type, string $title, string $description ): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $description ); ?></p>
			<form class="chidemoon-ai-job-form" data-chidemoon-ai-job="<?php echo esc_attr( $type ); ?>">
				<?php if ( 'text' === $type ) : ?>
					<p><label for="chidemoon-ai-kind"><?php esc_html_e( 'Editorial task', 'chidemoon-ai' ); ?></label><br>
					<select id="chidemoon-ai-kind" name="kind">
						<option value="article_draft"><?php esc_html_e( 'Article draft', 'chidemoon-ai' ); ?></option>
						<option value="product_description"><?php esc_html_e( 'Product description', 'chidemoon-ai' ); ?></option>
						<option value="short_description"><?php esc_html_e( 'Short description', 'chidemoon-ai' ); ?></option>
						<option value="pros_cons"><?php esc_html_e( 'Pros and cons', 'chidemoon-ai' ); ?></option>
						<option value="faq"><?php esc_html_e( 'FAQ', 'chidemoon-ai' ); ?></option>
						<option value="buying_guide"><?php esc_html_e( 'Buying guide', 'chidemoon-ai' ); ?></option>
						<option value="seo_draft"><?php esc_html_e( 'SEO draft', 'chidemoon-ai' ); ?></option>
					</select></p>
				<?php endif; ?>
				<p><label for="chidemoon-ai-target"><?php esc_html_e( 'Target draft ID (optional)', 'chidemoon-ai' ); ?></label><br><input id="chidemoon-ai-target" name="target_post_id" type="number" min="1" step="1"></p>
				<?php if ( 'comparison' === $type ) : ?>
					<p><label for="chidemoon-ai-products"><?php esc_html_e( 'WooCommerce product IDs', 'chidemoon-ai' ); ?></label><br><input id="chidemoon-ai-products" name="product_ids" type="text" class="regular-text" required aria-describedby="chidemoon-ai-products-help"><br><span id="chidemoon-ai-products-help" class="description"><?php esc_html_e( 'Two to four comma-separated product IDs.', 'chidemoon-ai' ); ?></span></p>
				<?php elseif ( 'image' === $type ) : ?>
					<p><label for="chidemoon-ai-mode"><?php esc_html_e( 'Image mode', 'chidemoon-ai' ); ?></label><br>
					<select id="chidemoon-ai-mode" name="mode"><option value="generate"><?php esc_html_e( 'Generate', 'chidemoon-ai' ); ?></option><option value="enhance"><?php esc_html_e( 'Enhance', 'chidemoon-ai' ); ?></option><option value="background"><?php esc_html_e( 'Background', 'chidemoon-ai' ); ?></option><option value="scene"><?php esc_html_e( 'Scene', 'chidemoon-ai' ); ?></option><option value="aggregate"><?php esc_html_e( 'Multi-image layout', 'chidemoon-ai' ); ?></option></select></p>
					<p><label for="chidemoon-ai-attachments"><?php esc_html_e( 'Source Media Library attachment IDs', 'chidemoon-ai' ); ?></label><br><input id="chidemoon-ai-attachments" name="source_attachment_ids" type="text" class="regular-text" aria-describedby="chidemoon-ai-attachments-help"><br><span id="chidemoon-ai-attachments-help" class="description"><?php esc_html_e( 'Comma-separated. Required for enhance, background, scene, and multi-image layout.', 'chidemoon-ai' ); ?></span></p>
					<p><label><input name="rights_attestation" type="checkbox" value="1" required> <?php esc_html_e( 'I confirm that I have the rights to use every source image and requested image concept.', 'chidemoon-ai' ); ?></label></p>
				<?php else : ?>
					<p><label for="chidemoon-ai-sources"><?php esc_html_e( 'WordPress evidence IDs', 'chidemoon-ai' ); ?></label><br><input id="chidemoon-ai-sources" name="source_post_ids" type="text" class="regular-text" required aria-describedby="chidemoon-ai-sources-help"><br><span id="chidemoon-ai-sources-help" class="description"><?php esc_html_e( 'Up to four editable post, page, or product IDs, separated by commas.', 'chidemoon-ai' ); ?></span></p>
				<?php endif; ?>
				<p><label for="chidemoon-ai-instructions"><?php esc_html_e( 'Editor request', 'chidemoon-ai' ); ?></label><br><textarea id="chidemoon-ai-instructions" name="instructions" rows="6" class="large-text" required></textarea></p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Queue AI job', 'chidemoon-ai' ); ?></button></p>
				<div class="chidemoon-ai-job-form__result" aria-live="polite"></div>
			</form>
		</div>
		<?php
	}

	/**
	 * @return array<string, int>
	 */
	private static function job_counts(): array {
		$states = array( Chidemoon_AI_State_Machine::QUEUED, Chidemoon_AI_State_Machine::REVIEW_REQUIRED, Chidemoon_AI_State_Machine::FAILED );
		$counts = array_fill_keys( $states, 0 );
		foreach ( $states as $state ) {
			$counts[ $state ] = count( Chidemoon_AI_Repository::list( $state, 100 ) );
		}

		return $counts;
	}

	private static function guard( string $capability ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have permission to access this Chidemoon AI page.', 'chidemoon-ai' ) );
		}
	}
}
