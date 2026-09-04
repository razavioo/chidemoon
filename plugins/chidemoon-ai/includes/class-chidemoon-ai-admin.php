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
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_sidebar' ) );
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
		add_submenu_page( 'chidemoon-ai', __( 'Look Studio', 'chidemoon-ai' ), __( 'Look Studio', 'chidemoon-ai' ), Chidemoon_AI_Capabilities::GENERATE, 'chidemoon-ai-looks', array( __CLASS__, 'look_studio' ) );
		add_submenu_page( 'chidemoon-ai', __( 'Enrich Product', 'chidemoon-ai' ), __( 'Enrich Product', 'chidemoon-ai' ), Chidemoon_AI_Capabilities::GENERATE, 'chidemoon-ai-enrich', array( __CLASS__, 'enrich_studio' ) );
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

	public static function look_studio(): void {
		self::guard( Chidemoon_AI_Capabilities::GENERATE );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Look Studio', 'chidemoon-ai' ); ?></h1>
			<p><?php esc_html_e( 'Generate a full styled room scene from your products. The image is synthesized by AI, hotspots are proposed automatically, and everything waits in the Review Queue. Nothing is published automatically.', 'chidemoon-ai' ); ?></p>
			<form class="chidemoon-ai-job-form" data-chidemoon-ai-job="look">
				<p><label for="chidemoon-ai-look-products"><?php esc_html_e( 'Product IDs (1-6, comma-separated)', 'chidemoon-ai' ); ?></label><br>
				<input id="chidemoon-ai-look-products" name="product_ids" type="text" class="regular-text" required>
				<button type="button" class="button" data-chidemoon-ai-picker="product_ids"><?php esc_html_e( 'Search products', 'chidemoon-ai' ); ?></button></p>
				<p><label for="chidemoon-ai-look-room"><?php esc_html_e( 'Room', 'chidemoon-ai' ); ?></label><br>
				<select id="chidemoon-ai-look-room" name="room">
					<option value="living-room"><?php esc_html_e( 'Living room', 'chidemoon-ai' ); ?></option>
					<option value="bedroom"><?php esc_html_e( 'Bedroom', 'chidemoon-ai' ); ?></option>
					<option value="kitchen"><?php esc_html_e( 'Kitchen', 'chidemoon-ai' ); ?></option>
					<option value="kids-room"><?php esc_html_e( 'Kids room', 'chidemoon-ai' ); ?></option>
					<option value="terrace"><?php esc_html_e( 'Terrace', 'chidemoon-ai' ); ?></option>
					<option value="dining-room"><?php esc_html_e( 'Dining room', 'chidemoon-ai' ); ?></option>
					<option value="home-office"><?php esc_html_e( 'Home office', 'chidemoon-ai' ); ?></option>
					<option value="entryway"><?php esc_html_e( 'Entryway', 'chidemoon-ai' ); ?></option>
					<option value="reading-corner"><?php esc_html_e( 'Reading corner', 'chidemoon-ai' ); ?></option>
				</select></p>
				<p><label for="chidemoon-ai-look-style"><?php esc_html_e( 'Style', 'chidemoon-ai' ); ?></label><br>
				<select id="chidemoon-ai-look-style" name="style">
					<option value="minimal"><?php esc_html_e( 'Minimal', 'chidemoon-ai' ); ?></option>
					<option value="scandi"><?php esc_html_e( 'Scandinavian', 'chidemoon-ai' ); ?></option>
					<option value="warm"><?php esc_html_e( 'Warm', 'chidemoon-ai' ); ?></option>
					<option value="luxe"><?php esc_html_e( 'Quiet luxury', 'chidemoon-ai' ); ?></option>
				</select></p>
				<p><label for="chidemoon-ai-look-refs"><?php esc_html_e( 'Reference Media Library IDs (optional, max 2)', 'chidemoon-ai' ); ?></label><br>
				<input id="chidemoon-ai-look-refs" name="source_attachment_ids" type="text" class="regular-text"></p>
				<p><label for="chidemoon-ai-look-instructions"><?php esc_html_e( 'Style request', 'chidemoon-ai' ); ?></label><br>
				<textarea id="chidemoon-ai-look-instructions" name="instructions" rows="4" class="large-text" required placeholder="<?php esc_attr_e( 'e.g. Bright minimal living room, oak floor, morning light', 'chidemoon-ai' ); ?>"></textarea></p>
				<p><label><input name="rights_attestation" type="checkbox" value="1" required> <?php esc_html_e( 'I confirm that I have the rights to use every source image and requested image concept.', 'chidemoon-ai' ); ?></label></p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Generate look', 'chidemoon-ai' ); ?></button></p>
				<div class="chidemoon-ai-job-form__result" aria-live="polite"></div>
			</form>
		</div>
		<?php
	}

	public static function enrich_studio(): void {
		self::guard( Chidemoon_AI_Capabilities::GENERATE );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Enrich Product', 'chidemoon-ai' ); ?></h1>
			<p><?php esc_html_e( 'Enrich a WooCommerce product from its saved source URL plus free web search (DuckDuckGo, no key required). The proposal waits in the Review Queue and is applied to a draft only. The affiliate destination is never changed automatically.', 'chidemoon-ai' ); ?></p>
			<form class="chidemoon-ai-job-form" data-chidemoon-ai-job="enrich">
				<p><label for="chidemoon-ai-enrich-product"><?php esc_html_e( 'Product ID', 'chidemoon-ai' ); ?></label><br>
				<input id="chidemoon-ai-enrich-product" name="product_id" type="number" min="1" step="1" required>
				<button type="button" class="button" data-chidemoon-ai-picker="product_id"><?php esc_html_e( 'Search products', 'chidemoon-ai' ); ?></button></p>
				<p><label><input name="use_source_url" type="checkbox" value="1" checked> <?php esc_html_e( 'Fetch the saved evidence source URL', 'chidemoon-ai' ); ?></label><br>
				<label><input name="use_web" type="checkbox" value="1" checked> <?php esc_html_e( 'Free web search (DuckDuckGo, cached)', 'chidemoon-ai' ); ?></label></p>
				<p><label for="chidemoon-ai-enrich-instructions"><?php esc_html_e( 'Editor request', 'chidemoon-ai' ); ?></label><br>
				<textarea id="chidemoon-ai-enrich-instructions" name="instructions" rows="4" class="large-text">Enrich this product with accurate, concise Persian copy.</textarea></p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Queue enrichment', 'chidemoon-ai' ); ?></button></p>
				<div class="chidemoon-ai-job-form__result" aria-live="polite"></div>
			</form>
		</div>
		<?php
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
				<thead><tr><th><?php esc_html_e( 'Job', 'chidemoon-ai' ); ?></th><th><?php esc_html_e( 'Type', 'chidemoon-ai' ); ?></th><th><?php esc_html_e( 'Target', 'chidemoon-ai' ); ?></th><th><?php esc_html_e( 'Preview', 'chidemoon-ai' ); ?></th><th><?php esc_html_e( 'Created', 'chidemoon-ai' ); ?></th><th><?php esc_html_e( 'Actions', 'chidemoon-ai' ); ?></th></tr></thead>
				<tbody>
				<?php if ( empty( $jobs ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'There are no generated outputs awaiting review.', 'chidemoon-ai' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $jobs as $job ) : ?>
						<tr>
							<td><?php echo esc_html( '#' . (string) $job['id'] ); ?></td>
							<td><?php echo esc_html( (string) $job['job_type'] ); ?></td>
							<td><?php echo esc_html( (string) ( $job['target_post_id'] ?: '—' ) ); ?></td>
							<td style="max-width:420px"><?php echo wp_kses_post( self::job_preview( $job ) ); ?></td>
							<td><?php echo esc_html( (string) $job['created_at'] ); ?></td>
							<td>
								<?php if ( Chidemoon_AI_State_Machine::APPROVED === $job['state'] ) : ?>
									<button type="button" class="button button-primary" data-chidemoon-ai-review="apply" data-job-id="<?php echo esc_attr( (string) $job['id'] ); ?>"><?php echo esc_html( 'look' === $job['job_type'] ? __( 'Create look draft', 'chidemoon-ai' ) : __( 'Apply to draft', 'chidemoon-ai' ) ); ?></button>
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

	/**
	 * @param array<string, mixed> $job
	 */
	private static function job_preview( array $job ): string {
		$result = is_array( $job['result_payload'] ?? null ) ? $job['result_payload'] : array();
		if ( empty( $result ) ) {
			return '<em>' . esc_html__( 'Queued — no output yet.', 'chidemoon-ai' ) . '</em>';
		}
		$type = (string) ( $job['job_type'] ?? '' );
		if ( in_array( $type, array( 'image', 'look' ), true ) ) {
			$attachment_id = absint( $result['attachment_id'] ?? 0 );
			$html = '';
			if ( $attachment_id ) {
				$img = wp_get_attachment_image( $attachment_id, 'medium' );
				if ( $img ) {
					$html .= $img;
				}
			}
			if ( 'look' === $type ) {
				$products = is_array( $result['product_ids'] ?? null ) ? array_map( 'absint', $result['product_ids'] ) : array();
				$hotspots = is_array( $result['hotspots_proposal'] ?? null ) ? $result['hotspots_proposal'] : array();
				$html .= '<p><strong>' . esc_html__( 'Products:', 'chidemoon-ai' ) . '</strong> ' . esc_html( implode( ', ', array_map( 'strval', $products ) ) ) . '<br>';
				$html .= '<strong>' . esc_html__( 'Hotspots:', 'chidemoon-ai' ) . '</strong> ' . esc_html( (string) count( $hotspots ) . ' (' . (string) ( $result['hotspot_source'] ?? 'heuristic' ) . ')' ) . '<br>';
				$html .= '<strong>' . esc_html__( 'Room:', 'chidemoon-ai' ) . '</strong> ' . esc_html( (string) ( $result['room'] ?? '' ) ) . '</p>';
			} else {
				$html .= '<p>' . esc_html( (string) ( $result['revised_prompt'] ?? '' ) ) . '</p>';
			}

			return $html;
		}
		if ( 'enrich' === $type ) {
			$html = '<p><strong>' . esc_html( (string) ( $result['title'] ?? '' ) ) . '</strong></p>';
			$html .= '<p>' . esc_html( (string) ( $result['short_description'] ?? '' ) ) . '</p>';
			if ( ! empty( $result['needs_price_check'] ) ) {
				$html .= '<p><em>' . esc_html__( 'Price needs a manual check at the merchant.', 'chidemoon-ai' ) . '</em></p>';
			}
			$facts = is_array( $result['facts'] ?? null ) ? $result['facts'] : array();
			if ( ! empty( $facts ) ) {
				$html .= '<ul>';
				foreach ( array_slice( $facts, 0, 5 ) as $label => $value ) {
					$html .= '<li><strong>' . esc_html( (string) $label ) . ':</strong> ' . esc_html( (string) $value ) . '</li>';
				}
				$html .= '</ul>';
			}

			return $html;
		}

		$html = '<p><strong>' . esc_html( (string) ( $result['title'] ?? '' ) ) . '</strong></p>';
		$html .= '<p>' . esc_html( function_exists( 'mb_substr' ) ? mb_substr( (string) ( $result['excerpt'] ?? '' ), 0, 220 ) : substr( (string) ( $result['excerpt'] ?? '' ), 0, 220 ) ) . '</p>';

		return $html;
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
		$status   = class_exists( 'Chidemoon_AI_Settings' ) ? Chidemoon_AI_Settings::secret_status() : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Chidemoon AI Settings', 'chidemoon-ai' ); ?></h1>
			<p><?php esc_html_e( 'Provider secrets are intentionally host-managed. They are never saved in WordPress options or exposed in this panel.', 'chidemoon-ai' ); ?></p>
			<table class="widefat striped" style="max-width: 900px">
				<tbody>
					<tr><th><?php esc_html_e( 'Provider status', 'chidemoon-ai' ); ?></th><td><?php echo esc_html( is_wp_error( $provider ) ? __( 'Not configured', 'chidemoon-ai' ) : __( 'Configured', 'chidemoon-ai' ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Provider host', 'chidemoon-ai' ); ?></th><td><?php echo esc_html( (string) ( $status['base_host'] ?? '' ) ?: '—' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Moderation gate', 'chidemoon-ai' ); ?></th><td><?php echo esc_html( ! empty( $status['moderation_configured'] ) ? __( 'Configured', 'chidemoon-ai' ) : __( 'Missing CHIDEMOON_AI_MODERATION_MODEL', 'chidemoon-ai' ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Optional search key', 'chidemoon-ai' ); ?></th><td><?php echo esc_html( ! empty( $status['search_key_present'] ) ? __( 'Present (value hidden)', 'chidemoon-ai' ) : __( 'Not set — free search is used', 'chidemoon-ai' ) ); ?></td></tr>
					<?php if ( ! is_wp_error( $provider ) ) : ?>
					<tr><th><?php esc_html_e( 'Text model', 'chidemoon-ai' ); ?></th><td><?php echo esc_html( $provider->text_model() ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Vision model', 'chidemoon-ai' ); ?></th><td><?php echo esc_html( method_exists( $provider, 'vision_model' ) ? $provider->vision_model() : '—' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Image model', 'chidemoon-ai' ); ?></th><td><?php echo esc_html( $provider->image_model() ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="chidemoon-ai-test-connection"><?php esc_html_e( 'Test connection', 'chidemoon-ai' ); ?></button>
				<span id="chidemoon-ai-test-result" aria-live="polite"></span>
			</p>
			<p>
				<label for="chidemoon-ai-vision-attachment"><?php esc_html_e( 'Vision check attachment ID', 'chidemoon-ai' ); ?></label>
				<input id="chidemoon-ai-vision-attachment" type="number" min="1" step="1" style="width:120px">
				<button type="button" class="button" id="chidemoon-ai-test-vision"><?php esc_html_e( 'Check vision', 'chidemoon-ai' ); ?></button>
				<span id="chidemoon-ai-vision-result" aria-live="polite"></span>
			</p>
			<p><?php esc_html_e( 'Required host environment values: CHIDEMOON_AI_PROVIDER_BASE_URL and CHIDEMOON_AI_API_KEY. Optional non-secret controls below are stored locally; host environment values always win.', 'chidemoon-ai' ); ?></p>
			<?php if ( class_exists( 'Chidemoon_AI_Settings' ) ) : ?>
			<form action="options.php" method="post">
				<?php settings_fields( 'chidemoon_ai_settings' ); ?>
				<h2><?php esc_html_e( 'Models & media', 'chidemoon-ai' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<?php foreach ( array( 'text_model', 'vision_model', 'image_model', 'image_size', 'image_quality', 'provider_timeout' ) as $key ) : ?>
						<tr>
							<th scope="row"><label for="chidemoon-ai-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $key ); ?></label></th>
							<td><input id="chidemoon-ai-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( Chidemoon_AI_Settings::option_name( $key ) ); ?>" type="text" class="regular-text" value="<?php echo esc_attr( (string) get_option( Chidemoon_AI_Settings::option_name( $key ), '' ) ); ?>">
							<?php if ( 'vision_model' === $key ) : ?><p class="description"><?php esc_html_e( 'Empty means reuse the text model for vision.', 'chidemoon-ai' ); ?></p><?php endif; ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<h2><?php esc_html_e( 'Quotas, budget & freshness', 'chidemoon-ai' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<?php foreach ( array( 'daily_limit', 'monthly_limit', 'monthly_budget', 'text_cost', 'comparison_cost', 'image_cost', 'look_cost', 'enrich_cost', 'evidence_max_age', 'moderation_timeout' ) as $key ) : ?>
						<tr>
							<th scope="row"><label for="chidemoon-ai-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $key ); ?></label></th>
							<td><input id="chidemoon-ai-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( Chidemoon_AI_Settings::option_name( $key ) ); ?>" type="text" class="regular-text" value="<?php echo esc_attr( (string) get_option( Chidemoon_AI_Settings::option_name( $key ), '' ) ); ?>"></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<h2><?php esc_html_e( 'Web search (free first)', 'chidemoon-ai' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="chidemoon-ai-search_mode"><?php esc_html_e( 'search_mode', 'chidemoon-ai' ); ?></label></th>
							<td>
								<select id="chidemoon-ai-search_mode" name="<?php echo esc_attr( Chidemoon_AI_Settings::option_name( 'search_mode' ) ); ?>">
									<?php $current = (string) get_option( Chidemoon_AI_Settings::option_name( 'search_mode' ), 'free_only' ); ?>
									<?php foreach ( array( 'off', 'free_only', 'free_plus_key', 'model_native' ) as $mode ) : ?>
									<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $current, $mode ); ?>><?php echo esc_html( $mode ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'free_only (default): direct fetch + DuckDuckGo, no key. free_plus_key: also use a host search key when present. model_native: also let a capable model browse (fail-open).', 'chidemoon-ai' ); ?></p>
							</td>
						</tr>
						<?php foreach ( array( 'search_cache_hours', 'search_max_results' ) as $key ) : ?>
						<tr>
							<th scope="row"><label for="chidemoon-ai-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $key ); ?></label></th>
							<td><input id="chidemoon-ai-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( Chidemoon_AI_Settings::option_name( $key ) ); ?>" type="number" min="1" step="1" value="<?php echo esc_attr( (string) get_option( Chidemoon_AI_Settings::option_name( $key ), '' ) ); ?>"></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'Save AI settings', 'chidemoon-ai' ) ); ?>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function woocommerce_notice(): void {
		if ( current_user_can( 'activate_plugins' ) && ! function_exists( 'wc_get_product' ) ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'Chidemoon AI can provide published-content retrieval, but WooCommerce must be active before comparison jobs are available.', 'chidemoon-ai' ) );
		}
	}

	public static function enqueue_sidebar(): void {
		if ( ! current_user_can( Chidemoon_AI_Capabilities::GENERATE ) ) {
			return;
		}
		$handle = 'chidemoon-ai-sidebar';
		wp_enqueue_script( $handle, CHIDEMOON_AI_URL . 'assets/js/sidebar.js', array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ), CHIDEMOON_AI_VERSION, true );
		wp_add_inline_script(
			$handle,
			'window.ChidemoonAiAdmin = window.ChidemoonAiAdmin || ' . wp_json_encode(
				array(
					'root'  => esc_url_raw( rest_url( 'chidemoon-ai/v1/' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				)
			) . ';',
			'before'
		);
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
					'root'        => esc_url_raw( rest_url( 'chidemoon-ai/v1/' ) ),
					'nonce'       => wp_create_nonce( 'wp_rest' ),
					'queued'      => __( 'AI job queued. The page will refresh shortly.', 'chidemoon-ai' ),
					'error'       => __( 'The AI request could not be completed.', 'chidemoon-ai' ),
					'reviewed'    => __( 'The AI review action completed.', 'chidemoon-ai' ),
					'pickProduct' => __( 'Enter a product search term (min 2 chars):', 'chidemoon-ai' ),
					'pickNone'    => __( 'No products found.', 'chidemoon-ai' ),
					'testing'     => __( 'Testing…', 'chidemoon-ai' ),
					'testOk'      => __( 'Connection OK.', 'chidemoon-ai' ),
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
						<option value="shop_the_look_caption"><?php esc_html_e( 'Shop the look caption', 'chidemoon-ai' ); ?></option>
					</select></p>
					<p>
						<label for="chidemoon-ai-tone"><?php esc_html_e( 'Tone', 'chidemoon-ai' ); ?></label><br>
						<select id="chidemoon-ai-tone" name="tone">
							<option value="formal"><?php esc_html_e( 'Formal', 'chidemoon-ai' ); ?></option>
							<option value="friendly"><?php esc_html_e( 'Friendly', 'chidemoon-ai' ); ?></option>
							<option value="expert"><?php esc_html_e( 'Expert', 'chidemoon-ai' ); ?></option>
						</select>
						<label for="chidemoon-ai-length" style="margin-left:12px"><?php esc_html_e( 'Length', 'chidemoon-ai' ); ?></label>
						<select id="chidemoon-ai-length" name="length">
							<option value="short"><?php esc_html_e( 'Short', 'chidemoon-ai' ); ?></option>
							<option value="medium" selected><?php esc_html_e( 'Medium', 'chidemoon-ai' ); ?></option>
							<option value="long"><?php esc_html_e( 'Long', 'chidemoon-ai' ); ?></option>
						</select>
						<label for="chidemoon-ai-lang" style="margin-left:12px"><?php esc_html_e( 'Language', 'chidemoon-ai' ); ?></label>
						<select id="chidemoon-ai-lang" name="lang">
							<option value="fa" selected><?php esc_html_e( 'Persian', 'chidemoon-ai' ); ?></option>
							<option value="en"><?php esc_html_e( 'English', 'chidemoon-ai' ); ?></option>
						</select>
					</p>
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
