<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Kalahamoon Help & Guide admin page.
 * Tabbed, self-contained documentation rendered inside wp-admin.
 */
class Kalahamoon_Help {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'kalahamoon',
			__( 'Help & Guide', 'kalahamoon' ),
			__( 'Help & Guide', 'kalahamoon' ),
			'edit_posts',
			'kalahamoon-help',
			array( __CLASS__, 'render' )
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Render
	// ─────────────────────────────────────────────────────────────────────────

	public static function render(): void {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'start';
		$tabs = array(
			'start'        => __( 'Getting Started', 'kalahamoon' ),
			'blocks'       => __( 'Blocks', 'kalahamoon' ),
			'patterns'     => __( 'Block Patterns', 'kalahamoon' ),
			'shortcodes'   => __( 'Shortcodes', 'kalahamoon' ),
			'settings'     => __( 'Settings', 'kalahamoon' ),
			'troubleshoot' => __( 'Troubleshooting', 'kalahamoon' ),
		);
		$direction = Kalahamoon_RTL::admin_direction();
		$language  = Kalahamoon_RTL::admin_language();
		?>
		<div class="wrap slm-help" dir="<?php echo esc_attr( $direction ); ?>" lang="<?php echo esc_attr( $language ); ?>">
			<?php self::print_styles(); ?>

			<h1 class="slm-help-title">
				<span class="slm-help-logo" aria-hidden="true">📖</span>
				<?php esc_html_e( 'Kalahamoon Plugin — Help & Guide', 'kalahamoon' ); ?>
			</h1>
			<p class="slm-help-subtitle">
				<?php esc_html_e( 'Everything you need to build a professional affiliate content site.', 'kalahamoon' ); ?>
			</p>

			<nav class="slm-help-nav" aria-label="<?php esc_attr_e( 'Help sections', 'kalahamoon' ); ?>">
				<?php foreach ( $tabs as $key => $label ) :
					$url    = add_query_arg( array( 'page' => 'kalahamoon-help', 'tab' => $key ), admin_url( 'admin.php' ) );
					$active = ( $tab === $key );
				?>
					<a href="<?php echo esc_url( $url ); ?>"
					   class="slm-help-nav-item <?php echo $active ? 'is-active' : ''; ?>"
					   <?php echo $active ? 'aria-current="page"' : ''; ?>>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="slm-help-body">
				<?php
				switch ( $tab ) {
					case 'blocks':       self::tab_blocks();       break;
					case 'patterns':     self::tab_patterns();     break;
					case 'shortcodes':   self::tab_shortcodes();   break;
					case 'settings':     self::tab_settings();     break;
					case 'troubleshoot': self::tab_troubleshoot(); break;
					default:             self::tab_start();        break;
				}
				?>
			</div>

			<?php self::print_scripts(); ?>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Tab: Getting Started
	// ─────────────────────────────────────────────────────────────────────────

	private static function tab_start(): void {
		$settings_url  = admin_url( 'admin.php?page=kalahamoon-setting' );
		$products_url  = admin_url( 'admin.php?page=kalahamoon-products' );
		$dashboard_url = admin_url( 'admin.php?page=kalahamoon' );
		?>
		<div class="slm-help-section">
			<h2><?php esc_html_e( 'Welcome to Kalahamoon', 'kalahamoon' ); ?></h2>
			<p><?php esc_html_e( 'Kalahamoon is an affiliate publishing toolkit for WordPress. Sync products from supported Iranian marketplaces, build product reviews and comparison articles, show live prices through cached product data, cloak affiliate links, track clicks, and add disclosure/schema markup without leaving the block editor.', 'kalahamoon' ); ?></p>

			<div class="slm-help-steps">

				<?php self::step( 1,
					__( 'Connect your Kalahamoon account', 'kalahamoon' ),
					sprintf(
						/* translators: %s settings page link */
						__( 'Go to <a href="%s">Settings</a> and click <strong>Connect with Kalahamoon</strong>. The OAuth flow links WordPress to your catalog and analytics without sharing your password.', 'kalahamoon' ),
						esc_url( $settings_url )
					),
					array(
						__( 'Use OAuth when possible; API keys are kept only for legacy installs.', 'kalahamoon' ),
					)
				); ?>

				<?php self::step( 2,
					__( 'Sync your product catalog', 'kalahamoon' ),
					sprintf(
						/* translators: %s products page link */
						__( 'Open <a href="%s">Products</a> and click <strong>Sync Now</strong>. Product titles, prices, images, marketplace badges, and affiliate URLs are cached locally for fast block previews and frontend rendering.', 'kalahamoon' ),
						esc_url( $products_url )
					),
					array(
						__( 'Scheduled sync keeps product data fresh while pages continue to render from the local cache.', 'kalahamoon' ),
					)
				); ?>

				<?php self::step( 3,
					__( 'Build a product recommendation', 'kalahamoon' ),
					__( 'In the block editor, insert Product Box for one recommendation or Product Grid for a ranked list. Use the visual picker to choose products and let Kalahamoon handle prices, images, cloaked links, and click tracking.', 'kalahamoon' ),
					array(
						__( 'Start simple: one product box, one rating/verdict, a pros & cons block, and one CTA are enough for a practical review.', 'kalahamoon' ),
					)
				); ?>

				<?php self::step( 4,
					__( 'Publish reviews, comparisons, and roundups', 'kalahamoon' ),
					__( 'Use the curated patterns for product reviews, single-product buying pages, head-to-head comparisons, best-of lists, buying guides, and deal posts. Each pattern is only a starter: replace the products and write your own verdict.', 'kalahamoon' ),
					array()
				); ?>

				<?php self::step( 5,
					__( 'Review affiliate performance', 'kalahamoon' ),
					sprintf(
						/* translators: %s analytics page link */
						__( 'Open the <a href="%s">Dashboard</a> to see clicks, top products, and revenue estimates. Every affiliate CTA uses the tracker automatically.', 'kalahamoon' ),
						esc_url( $dashboard_url )
					),
					array()
				); ?>

			</div>
		</div>

		<div class="slm-help-section">
			<h2><?php esc_html_e( 'What is inside the plugin', 'kalahamoon' ); ?></h2>
			<div class="slm-help-card-grid">

				<?php self::feature_card( '🧩', __( 'Core publishing blocks', 'kalahamoon' ),
					__( 'Product Box, Product Grid, Comparison Table, AI Compare, Rating Box, Pros & Cons, CTA Button, FAQ, and Affiliate Disclosure.', 'kalahamoon' ),
					admin_url( 'admin.php?page=kalahamoon-help&tab=blocks' )
				); ?>

				<?php self::feature_card( '📝', __( 'Affiliate article patterns', 'kalahamoon' ),
					__( 'Focused starters for product reviews, buying pages, head-to-head comparisons, best-of roundups, category guides, and deal posts.', 'kalahamoon' ),
					admin_url( 'admin.php?page=kalahamoon-help&tab=patterns' )
				); ?>

				<?php self::feature_card( '[ ]', __( 'Classic Editor shortcodes', 'kalahamoon' ),
					__( 'Product cards, product grids, comparison tables, CTA buttons, prices, pros/cons cards, and Shop the Look are also available as shortcodes.', 'kalahamoon' ),
					admin_url( 'admin.php?page=kalahamoon-help&tab=shortcodes' )
				); ?>

				<?php self::feature_card( '📊', __( 'Click Tracking', 'kalahamoon' ),
					__( 'Affiliate clicks are tracked through a lightweight beacon and shown in your WordPress analytics dashboard.', 'kalahamoon' ),
					''
				); ?>

				<?php self::feature_card( '🔗', __( 'Link Cloaking', 'kalahamoon' ),
					__( 'Affiliate URLs are served from /go/{slug} on your domain for cleaner links, attribution, and easier sharing.', 'kalahamoon' ),
					''
				); ?>

				<?php self::feature_card( '🤖', __( 'AI Comparison', 'kalahamoon' ),
					__( 'AI Compare generates a scored criteria table, pros & cons, and a verdict for two selected products, then stores the result in the post.', 'kalahamoon' ),
					admin_url( 'admin.php?page=kalahamoon-help&tab=blocks#ai-compare' )
				); ?>

			</div>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Tab: Blocks
	// ─────────────────────────────────────────────────────────────────────────

	private static function tab_blocks(): void {
		$core_blocks = array(
			array(
				'id'      => 'product-box',
				'icon'    => '📦',
				'name'    => __( 'Product Box', 'kalahamoon' ),
				'tagline' => __( 'A single product card with image, live price, marketplace badge, CTA, schema, and tracked affiliate link.', 'kalahamoon' ),
				'when'    => __( 'Use for the main recommendation in a review, buying guide, deal post, or product landing page.', 'kalahamoon' ),
				'attrs'   => array(
					'productId'  => __( 'Kalahamoon product ID selected with the visual picker.', 'kalahamoon' ),
					'ctaText'    => __( 'Affiliate button label.', 'kalahamoon' ),
					'showPrice'  => __( 'Show or hide the cached live price.', 'kalahamoon' ),
					'showRating' => __( 'Show or hide the rating when available.', 'kalahamoon' ),
				),
				'steps'   => array(
					__( 'Insert Product Box from the Kalahamoon block category.', 'kalahamoon' ),
					__( 'Click Choose Product and select a synced product.', 'kalahamoon' ),
					__( 'Adjust the CTA text and visibility toggles in the sidebar.', 'kalahamoon' ),
				),
				'note'    => __( 'Best paired with Affiliate Disclosure, Rating Box, and Pros & Cons in product reviews.', 'kalahamoon' ),
			),
			array(
				'id'      => 'product-grid',
				'icon'    => '🔲',
				'name'    => __( 'Product Grid', 'kalahamoon' ),
				'tagline' => __( 'A responsive grid, list, or carousel layout for hand-picked or category-filtered products.', 'kalahamoon' ),
				'when'    => __( 'Use in best-of roundups, related product sections, and category buying guides.', 'kalahamoon' ),
				'attrs'   => array(
					'productIds' => __( 'Comma-separated product IDs selected with the multi-select picker.', 'kalahamoon' ),
					'category'   => __( 'Optional category slug when you want a dynamic product list.', 'kalahamoon' ),
					'columns'    => __( 'Grid column count.', 'kalahamoon' ),
					'limit'      => __( 'Maximum number of products to display.', 'kalahamoon' ),
					'ranked'     => __( 'Show numbered badges for ranked recommendations.', 'kalahamoon' ),
				),
				'steps'   => array(
					__( 'Insert Product Grid.', 'kalahamoon' ),
					__( 'Choose products manually or set a category slug.', 'kalahamoon' ),
					__( 'Use ranked mode for best-of articles and buying guides.', 'kalahamoon' ),
				),
				'note'    => __( 'When both product IDs and category are set, the hand-picked products take priority.', 'kalahamoon' ),
			),
			array(
				'id'      => 'comparison-table',
				'icon'    => '📊',
				'name'    => __( 'Comparison Table', 'kalahamoon' ),
				'tagline' => __( 'A side-by-side product comparison table for specs, prices, and buying criteria.', 'kalahamoon' ),
				'when'    => __( 'Use when readers need structured specs before choosing between products.', 'kalahamoon' ),
				'attrs'   => array(
					'productIds' => __( 'IDs of the products to compare.', 'kalahamoon' ),
					'specs'      => __( 'Spec rows for the comparison matrix.', 'kalahamoon' ),
				),
				'steps'   => array(
					__( 'Insert Comparison Table.', 'kalahamoon' ),
					__( 'Pick the products to compare.', 'kalahamoon' ),
					__( 'Fill in the criteria that matter to buyers.', 'kalahamoon' ),
				),
				'note'    => __( 'Use AI Compare when you want a narrative verdict in addition to structured criteria.', 'kalahamoon' ),
			),
			array(
				'id'      => 'ai-compare',
				'icon'    => '🤖',
				'name'    => __( 'AI Compare', 'kalahamoon' ),
				'tagline' => __( 'AI-assisted head-to-head product comparison with criteria, pros, cons, and a final verdict.', 'kalahamoon' ),
				'when'    => __( 'Use for buyer-intent comparison posts where two products are close alternatives.', 'kalahamoon' ),
				'attrs'   => array(
					'productIds' => __( 'Exactly two product IDs.', 'kalahamoon' ),
					'criteria'   => __( 'Optional comma-separated buying criteria.', 'kalahamoon' ),
					'language'   => __( 'Persian, Arabic, or English output.', 'kalahamoon' ),
				),
				'steps'   => array(
					__( 'Insert AI Compare.', 'kalahamoon' ),
					__( 'Choose exactly two products.', 'kalahamoon' ),
					__( 'Generate the comparison, review the result, and save the post.', 'kalahamoon' ),
				),
				'note'    => __( 'The generated comparison is stored in the post; visitors do not trigger AI calls.', 'kalahamoon' ),
			),
			array(
				'id'      => 'rating-box',
				'icon'    => '⭐',
				'name'    => __( 'Rating Box', 'kalahamoon' ),
				'tagline' => __( 'A review scorecard with criteria, verdict text, optional CTA, and review schema.', 'kalahamoon' ),
				'when'    => __( 'Use near the top of product reviews to summarize your verdict quickly.', 'kalahamoon' ),
				'attrs'   => array(
					'score'    => __( 'Final score.', 'kalahamoon' ),
					'criteria' => __( 'Per-criterion scores, one per line.', 'kalahamoon' ),
					'verdict'  => __( 'Short editorial verdict.', 'kalahamoon' ),
				),
				'steps'   => array(
					__( 'Insert Rating Box after the product card or intro.', 'kalahamoon' ),
					__( 'Set the score, criteria, and verdict.', 'kalahamoon' ),
				),
				'note'    => '',
			),
			array(
				'id'      => 'pros-cons',
				'icon'    => '✅',
				'name'    => __( 'Pros & Cons', 'kalahamoon' ),
				'tagline' => __( 'A scan-friendly list of product advantages and disadvantages.', 'kalahamoon' ),
				'when'    => __( 'Use in every review where readers need a quick buying summary.', 'kalahamoon' ),
				'attrs'   => array(
					'pros'    => __( 'Advantages, one per line.', 'kalahamoon' ),
					'cons'    => __( 'Disadvantages, one per line.', 'kalahamoon' ),
					'showCta' => __( 'Optional product CTA footer.', 'kalahamoon' ),
				),
				'steps'   => array(
					__( 'Insert Pros & Cons.', 'kalahamoon' ),
					__( 'Write concise buyer-facing points.', 'kalahamoon' ),
				),
				'note'    => '',
			),
			array(
				'id'      => 'cta-button',
				'icon'    => '🔘',
				'name'    => __( 'CTA Button', 'kalahamoon' ),
				'tagline' => __( 'A lightweight tracked affiliate button for inline recommendations.', 'kalahamoon' ),
				'when'    => __( 'Use when a full Product Box would interrupt the article flow.', 'kalahamoon' ),
				'attrs'   => array(
					'productId' => __( 'Target product.', 'kalahamoon' ),
					'text'      => __( 'Button text.', 'kalahamoon' ),
					'showPrice' => __( 'Optionally show the current price.', 'kalahamoon' ),
				),
				'steps'   => array(
					__( 'Insert CTA Button.', 'kalahamoon' ),
					__( 'Pick the product and write the button label.', 'kalahamoon' ),
				),
				'note'    => '',
			),
			array(
				'id'      => 'faq',
				'icon'    => '❓',
				'name'    => __( 'FAQ', 'kalahamoon' ),
				'tagline' => __( 'Accessible buyer questions with optional FAQPage schema.', 'kalahamoon' ),
				'when'    => __( 'Use at the end of reviews, comparisons, and buying guides.', 'kalahamoon' ),
				'attrs'   => array(
					'items'      => __( 'Questions and answers.', 'kalahamoon' ),
					'openFirst'  => __( 'Open the first question by default.', 'kalahamoon' ),
					'emitSchema' => __( 'Emit FAQPage structured data.', 'kalahamoon' ),
				),
				'steps'   => array(
					__( 'Insert FAQ.', 'kalahamoon' ),
					__( 'Add real buyer objections and concise answers.', 'kalahamoon' ),
				),
				'note'    => '',
			),
		);

		$supporting_blocks = array(
			array(
				'id'      => 'testimonials',
				'icon'    => '💬',
				'name'    => __( 'Testimonials', 'kalahamoon' ),
				'tagline' => __( 'Real merchant or customer quotes for trust sections.', 'kalahamoon' ),
				'when'    => __( 'Use only with real testimonials; do not publish placeholder quotes.', 'kalahamoon' ),
				'attrs'   => array(),
				'steps'   => array( __( 'Add verified quotes, authors, roles, and ratings where appropriate.', 'kalahamoon' ) ),
				'note'    => '',
			),
			array(
				'id'      => 'shop-the-look',
				'icon'    => '🛍️',
				'name'    => __( 'Shop the Look', 'kalahamoon' ),
				'tagline' => __( 'Interactive hotspots on lifestyle images for shoppable editorial content.', 'kalahamoon' ),
				'when'    => __( 'Use for home, fashion, kitchen, and setup articles where an image explains multiple products.', 'kalahamoon' ),
				'attrs'   => array(),
				'steps'   => array( __( 'Upload a real lifestyle image, then assign products to hotspots.', 'kalahamoon' ) ),
				'note'    => __( 'This is a niche supporting block, not required for standard review workflows.', 'kalahamoon' ),
			),
		);

		echo '<div class="slm-help-section">';
		echo '<h2>' . esc_html__( 'Core Publishing Blocks', 'kalahamoon' ) . '</h2>';
		echo '<p>' . esc_html__( 'These are the main Kalahamoon blocks for merchant, product, review, comparison, and affiliate publishing.', 'kalahamoon' ) . '</p>';
		foreach ( $core_blocks as $b ) {
			self::block_card( $b );
		}
		echo '</div>';

		echo '<div class="slm-help-section">';
		echo '<h2>' . esc_html__( 'Supporting Merchant Blocks', 'kalahamoon' ) . '</h2>';
		echo '<p>' . esc_html__( 'These blocks remain available for merchant context and niche editorial use, but they are secondary to the product/review workflow.', 'kalahamoon' ) . '</p>';
		foreach ( $supporting_blocks as $b ) {
			self::block_card( $b );
		}
		echo '</div>';
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Tab: Block Patterns
	// ─────────────────────────────────────────────────────────────────────────

	private static function tab_patterns(): void {
		$patterns = array(
			array(
				'icon'  => '📝',
				'name'  => __( 'Product Review Article', 'kalahamoon' ),
				'desc'  => __( 'The full single-product review: intro, Product Box, Rating Box, Pros & Cons, section-by-section analysis, buyer fit, comparison table, FAQ, and verdict CTA.', 'kalahamoon' ),
				'how'   => __( 'Select the reviewed product, write your verdict, fill the analysis sections and buyer criteria, then replace the placeholder FAQ content.', 'kalahamoon' ),
			),
			array(
				'icon'  => '⚖️',
				'name'  => __( 'Product Comparison Article', 'kalahamoon' ),
				'desc'  => __( 'The full comparison post: intro, ranked product grid, AI head-to-head verdict, complete spec table, per-pick fit, FAQ, and CTA.', 'kalahamoon' ),
				'how'   => __( 'Pick the products to compare, edit the AI criteria to match the purchase decision, and write who each option is best for.', 'kalahamoon' ),
			),
			array(
				'icon'  => '🏆',
				'name'  => __( 'Best-of Roundup & Buying Guide', 'kalahamoon' ),
				'desc'  => __( 'The full "best X" / category guide: intro, ranked product grid, buying criteria, per-pick notes, FAQ, and CTA.', 'kalahamoon' ),
				'how'   => __( 'Choose the products in ranked order, write practical buying criteria, and explain why each pick earns its place.', 'kalahamoon' ),
			),
		);
		?>
		<div class="slm-help-section">
			<h2><?php esc_html_e( 'Affiliate Publishing Patterns', 'kalahamoon' ); ?></h2>
			<p>
				<?php esc_html_e( 'Patterns are focused article starters for product reviews, buying pages, comparisons, roundups, category guides, and deal posts. Insert one from the editor Patterns tab, then replace the products and write your own editorial verdict.', 'kalahamoon' ); ?>
			</p>

			<div class="slm-help-callout slm-help-callout--info">
				<strong><?php esc_html_e( 'Tip:', 'kalahamoon' ); ?></strong>
				<?php esc_html_e( 'Patterns provide structure only. Real rankings, pros/cons, testimonials, and offer claims should come from your own review process.', 'kalahamoon' ); ?>
			</div>

			<div class="slm-help-pattern-list">
				<?php foreach ( $patterns as $p ) : ?>
					<div class="slm-help-pattern-row">
						<div class="slm-help-pattern-icon" aria-hidden="true"><?php echo esc_html( $p['icon'] ); ?></div>
						<div class="slm-help-pattern-body">
							<strong><?php echo esc_html( $p['name'] ); ?></strong>
							<p><?php echo esc_html( $p['desc'] ); ?></p>
							<p class="slm-help-pattern-how">
								<span class="slm-help-label"><?php esc_html_e( 'After inserting:', 'kalahamoon' ); ?></span>
								<?php echo esc_html( $p['how'] ); ?>
							</p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Tab: Shortcodes
	// ─────────────────────────────────────────────────────────────────────────

	private static function tab_shortcodes(): void {
		$codes = array(
			array(
				'tag'     => 'kalahamoon_product',
				'desc'    => __( 'Full Product Box card — image, title, price, CTA.', 'kalahamoon' ),
				'attrs'   => array(
					'id'       => __( 'Kalahamoon product ID (required).', 'kalahamoon' ),
					'cta_text' => __( 'Button label. Default: "View product".', 'kalahamoon' ),
				),
				'example' => '[kalahamoon_product id="bas-123" cta_text="View product"]',
			),
			array(
				'tag'     => 'kalahamoon_grid',
				'desc'    => __( 'Product Grid — multiple products in a responsive grid.', 'kalahamoon' ),
				'attrs'   => array(
					'ids'      => __( 'Comma-separated product IDs.', 'kalahamoon' ),
					'category' => __( 'Category slug (alternative to ids).', 'kalahamoon' ),
					'columns'  => __( '2, 3, or 4. Default: 3.', 'kalahamoon' ),
					'limit'    => __( 'Max items. Default: 12.', 'kalahamoon' ),
				),
				'example' => '[kalahamoon_grid ids="bas-1,bas-2,bas-3" columns="3"]',
			),
			array(
				'tag'     => 'kalahamoon_carousel',
				'desc'    => __( 'Horizontally scrollable product row.', 'kalahamoon' ),
				'attrs'   => array(
					'ids'      => __( 'Comma-separated IDs.', 'kalahamoon' ),
					'category' => __( 'Category slug.', 'kalahamoon' ),
					'limit'    => __( 'Max items. Default: 10.', 'kalahamoon' ),
				),
				'example' => '[kalahamoon_carousel category="vacuum-cleaners" limit="6"]',
			),
			array(
				'tag'     => 'kalahamoon_compare',
				'desc'    => __( 'Static comparison table for 2–4 products.', 'kalahamoon' ),
				'attrs'   => array(
					'ids'  => __( '2–4 comma-separated IDs.', 'kalahamoon' ),
				),
				'example' => '[kalahamoon_compare ids="bas-1,bas-2"]',
			),
			array(
				'tag'     => 'kalahamoon_cta',
				'desc'    => __( 'A single affiliate CTA button.', 'kalahamoon' ),
				'attrs'   => array(
					'id'       => __( 'Product ID.', 'kalahamoon' ),
					'label'    => __( 'Button text.', 'kalahamoon' ),
					'variant'  => __( 'primary / secondary / ghost.', 'kalahamoon' ),
					'size'     => __( 'sm / md / lg.', 'kalahamoon' ),
				),
				'example' => '[kalahamoon_cta id="dig-456" label="خرید از دیجی‌کالا" variant="primary" size="lg"]',
			),
			array(
				'tag'     => 'kalahamoon_price',
				'desc'    => __( 'Inline price for a product — renders the cached price inline with the correct currency.', 'kalahamoon' ),
				'attrs'   => array(
					'id' => __( 'Product ID.', 'kalahamoon' ),
				),
				'example' => 'قیمت فعلی: [kalahamoon_price id="bas-123"]',
			),
			array(
				'tag'     => 'kalahamoon_pros_cons',
				'desc'    => __( 'Pros & Cons card — same as the block, usable in Classic Editor.', 'kalahamoon' ),
				'attrs'   => array(
					'id'      => __( 'Optional product ID for thumbnail + CTA.', 'kalahamoon' ),
					'heading' => __( 'Card title.', 'kalahamoon' ),
					'pros'    => __( 'Pipe-separated advantages, e.g. "سبک|ارزان|بادوام".', 'kalahamoon' ),
					'cons'    => __( 'Pipe-separated disadvantages.', 'kalahamoon' ),
					'cta'     => __( 'CTA button label.', 'kalahamoon' ),
				),
				'example' => '[kalahamoon_pros_cons id="bas-123" heading="نقاط قوت و ضعف" pros="سبک|ارزان" cons="باتری کوتاه"]',
			),
			array(
				'tag'     => 'kalahamoon_look',
				'desc'    => __( 'Shop the Look for Classic Editor — provide the image URL and hotspot coordinates manually.', 'kalahamoon' ),
				'attrs'   => array(
					'image'    => __( 'Full URL to the lifestyle image.', 'kalahamoon' ),
					'hotspots' => __( '"productId:x,y" pairs separated by semicolons, e.g. "bas-1:30,40;bas-2:60,55".', 'kalahamoon' ),
					'caption'  => __( 'Optional caption.', 'kalahamoon' ),
				),
				'example' => '[kalahamoon_look image="https://example.com/kitchen.jpg" hotspots="bas-1:30,40;bas-2:65,55" caption="آشپزخانه من"]',
			),
		);
		?>
		<div class="slm-help-section">
			<h2><?php esc_html_e( 'Shortcodes', 'kalahamoon' ); ?></h2>
			<p>
				<?php esc_html_e( 'Use shortcodes in the Classic Editor, in text widgets, or anywhere that accepts WordPress shortcodes. Gutenberg users should use blocks instead — the visual experience is better.', 'kalahamoon' ); ?>
			</p>

			<?php foreach ( $codes as $c ) : ?>
				<div class="slm-help-shortcode-card">
					<div class="slm-help-shortcode-header">
						<code class="slm-help-shortcode-tag">[<?php echo esc_html( $c['tag'] ); ?>]</code>
						<span class="slm-help-shortcode-desc"><?php echo esc_html( $c['desc'] ); ?></span>
					</div>

					<?php if ( ! empty( $c['attrs'] ) ) : ?>
						<table class="slm-help-attr-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Attribute', 'kalahamoon' ); ?></th>
									<th><?php esc_html_e( 'Description', 'kalahamoon' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $c['attrs'] as $attr => $attrDesc ) : ?>
									<tr>
										<td><code dir="ltr"><?php echo esc_html( $attr ); ?></code></td>
										<td><?php echo esc_html( $attrDesc ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<div class="slm-help-shortcode-example">
						<span class="slm-help-label"><?php esc_html_e( 'Example', 'kalahamoon' ); ?></span>
						<div class="slm-help-code-row">
							<code class="slm-help-code-block" dir="ltr"><?php echo esc_html( $c['example'] ); ?></code>
							<button class="slm-help-copy-btn" data-copy="<?php echo esc_attr( $c['example'] ); ?>" title="<?php esc_attr_e( 'Copy', 'kalahamoon' ); ?>">
								<?php esc_html_e( 'Copy', 'kalahamoon' ); ?>
							</button>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Tab: Settings
	// ─────────────────────────────────────────────────────────────────────────

	private static function tab_settings(): void {
		$settings_url = admin_url( 'admin.php?page=kalahamoon-setting' );
		?>
		<div class="slm-help-section">
			<h2><?php esc_html_e( 'Settings Reference', 'kalahamoon' ); ?></h2>

			<?php self::settings_group(
				__( 'Connection', 'kalahamoon' ),
				__( 'How the plugin authenticates with the Kalahamoon platform.', 'kalahamoon' ),
				array(
					array(
						'label' => __( 'OAuth (recommended)', 'kalahamoon' ),
						'desc'  => __( 'Click "Connect with Kalahamoon" to go through the OAuth flow. Your account is linked without ever sharing a password. Tokens are refreshed automatically.', 'kalahamoon' ),
					),
					array(
						'label' => __( 'API Key (legacy)', 'kalahamoon' ),
						'desc'  => __( 'Paste a key from your Kalahamoon dashboard → Developer → API Keys. Use this only if OAuth is not available.', 'kalahamoon' ),
					),
					array(
						'label' => __( 'Kalahamoon API URL', 'kalahamoon' ),
						'desc'  => __( 'Default: https://app.kalahamoon.com. Change only if you are running a self-hosted Kalahamoon instance.', 'kalahamoon' ),
					),
					array(
						'label' => __( 'Organization Slug', 'kalahamoon' ),
						'desc'  => __( 'Your organization\'s unique slug on Kalahamoon. Found in your Kalahamoon dashboard URL.', 'kalahamoon' ),
					),
					array(
						'label' => __( 'Webhook Secret', 'kalahamoon' ),
						'desc'  => __( 'Optional. When set, Kalahamoon verifies incoming product-update webhooks with HMAC-SHA256. Prevents unauthorized cache invalidation.', 'kalahamoon' ),
					),
				)
			); ?>

			<?php self::settings_group(
				__( 'Display', 'kalahamoon' ),
				__( 'Controls how prices and numbers are formatted across all blocks and shortcodes.', 'kalahamoon' ),
				array(
					array(
						'label' => __( 'Persian Numerals', 'kalahamoon' ),
						'desc'  => __( 'When enabled, all numbers are displayed using Eastern Arabic digits (۱۲۳ instead of 123). Recommended for Persian-language sites.', 'kalahamoon' ),
					),
					array(
						'label' => __( 'Currency Unit', 'kalahamoon' ),
						'desc'  => __( 'TOMAN: divides the IRR price by 10 and shows "تومان". RIAL: shows the raw IRR amount with "ریال".', 'kalahamoon' ),
					),
				)
			); ?>

			<?php self::settings_group(
				__( 'Affiliate', 'kalahamoon' ),
				__( 'Controls link cloaking and legal disclosure.', 'kalahamoon' ),
				array(
					array(
						'label' => __( 'Redirect Type', 'kalahamoon' ),
						'desc'  => __( '301 (permanent) is best for SEO link equity. 302 (temporary) is useful while testing. 307 preserves the request method.', 'kalahamoon' ),
					),
					array(
						'label' => __( 'Disclosure Text', 'kalahamoon' ),
						'desc'  => __( 'The text shown at the bottom of posts containing Kalahamoon blocks. Required by FTC guidelines and similar regulations. Kalahamoon adds it automatically — you just provide the text.', 'kalahamoon' ),
					),
				)
			); ?>

			<?php self::settings_group(
				__( 'Sync', 'kalahamoon' ),
				__( 'Controls automatic product catalog synchronization.', 'kalahamoon' ),
				array(
					array(
						'label' => __( 'Sync Interval', 'kalahamoon' ),
						'desc'  => __( 'How often WordPress cron re-fetches products from Kalahamoon. Options: 1 hour, 6 hours, 12 hours, 24 hours. 6 hours is the default and recommended for most sites.', 'kalahamoon' ),
					),
					array(
						'label' => __( 'Manual Sync', 'kalahamoon' ),
						'desc'  => __( 'The "Sync Now" button on the Dashboard page triggers an immediate sync outside of the cron schedule. Use it after adding new products to Kalahamoon.', 'kalahamoon' ),
					),
				)
			); ?>

			<div class="slm-help-callout slm-help-callout--action">
				<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Open Settings →', 'kalahamoon' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Tab: Troubleshooting
	// ─────────────────────────────────────────────────────────────────────────

	private static function tab_troubleshoot(): void {
		$issues = array(
			array(
				'symptom' => __( 'Block shows "No product found" after selecting a product', 'kalahamoon' ),
				'cause'   => __( 'The product exists in Kalahamoon but has not been synced to the local cache yet.', 'kalahamoon' ),
				'fix'     => __( 'Go to Dashboard → click "Sync Now". Then refresh the editor.', 'kalahamoon' ),
			),
			array(
				'symptom' => __( '"401 Unauthorized" in the AI Compare block', 'kalahamoon' ),
				'cause'   => __( 'Your OAuth token is missing the ai:read scope — this happens when you connected before the AI Compare feature was added.', 'kalahamoon' ),
				'fix'     => __( 'Go to Settings → click "Disconnect" → click "Connect with Kalahamoon" to re-authorize with the new scope.', 'kalahamoon' ),
			),
			array(
				'symptom' => __( 'Prices are stale or wrong', 'kalahamoon' ),
				'cause'   => __( 'The cached price is from the last sync.', 'kalahamoon' ),
				'fix'     => __( 'Click "Sync Now" on the Dashboard. Prices update within seconds of the sync completing.', 'kalahamoon' ),
			),
			array(
				'symptom' => __( 'Shop the Look hotspots are misaligned on mobile', 'kalahamoon' ),
				'cause'   => __( 'Hotspot coordinates are percentage-based and should scale correctly. This usually means the image has a fixed pixel width set in the block.', 'kalahamoon' ),
				'fix'     => __( 'In the block toolbar, set the image width to 100% (not a fixed pixel value).', 'kalahamoon' ),
			),
			array(
				'symptom' => __( 'Block preview in editor shows a spinner forever', 'kalahamoon' ),
				'cause'   => __( 'ServerSideRender timed out — usually because the REST API is blocked.', 'kalahamoon' ),
				'fix'     => __( 'Check that your WordPress REST API is accessible: open /wp-json/ in a new tab. If it 403s, a security plugin (Wordfence, iThemes) may be blocking the editor from calling it.', 'kalahamoon' ),
			),
			array(
				'symptom' => __( 'Clicks are not appearing in Analytics', 'kalahamoon' ),
				'cause'   => __( 'The click tracker uses navigator.sendBeacon to /wp-json/kalahamoon/v1/clicks. A Content Security Policy or ad-blocker may be blocking it.', 'kalahamoon' ),
				'fix'     => __( 'Test without ad-blockers. Add the REST API path to your CSP connect-src directive. Clicks are stored server-side so even one unblocked test will confirm the system works.', 'kalahamoon' ),
			),
			array(
				'symptom' => __( 'Cloaked /go/ links return 404', 'kalahamoon' ),
				'cause'   => __( 'WordPress permalink rewrite rules have not been flushed after plugin activation.', 'kalahamoon' ),
				'fix'     => __( 'Go to Settings → Permalinks and click "Save Changes" without changing anything. This regenerates the .htaccess rewrite rules.', 'kalahamoon' ),
			),
			array(
				'symptom' => __( 'Patterns tab is empty in the block inserter', 'kalahamoon' ),
				'cause'   => __( 'Some themes or full-site editing setups disable pattern registration for third-party plugins.', 'kalahamoon' ),
				'fix'     => __( 'Check if your theme sets "block_patterns" to false in add_theme_support(). If so, ask your theme author to enable it, or switch to a block-editor-compatible theme.', 'kalahamoon' ),
			),
		);
		?>
		<div class="slm-help-section">
			<h2><?php esc_html_e( 'Troubleshooting', 'kalahamoon' ); ?></h2>
			<p>
				<?php esc_html_e( 'Common issues and their solutions. If your problem is not listed here, check the PHP error log at wp-content/debug.log (enable WP_DEBUG_LOG in wp-config.php) or contact support.', 'kalahamoon' ); ?>
			</p>

			<div class="slm-help-issue-list">
				<?php foreach ( $issues as $i => $issue ) : ?>
					<details class="slm-help-issue" <?php echo 0 === $i ? 'open' : ''; ?>>
						<summary class="slm-help-issue-summary">
							<span class="slm-help-issue-icon" aria-hidden="true">⚠️</span>
							<?php echo esc_html( $issue['symptom'] ); ?>
						</summary>
						<div class="slm-help-issue-body">
							<p>
								<strong><?php esc_html_e( 'Likely cause:', 'kalahamoon' ); ?></strong>
								<?php echo esc_html( $issue['cause'] ); ?>
							</p>
							<p>
								<strong><?php esc_html_e( 'Fix:', 'kalahamoon' ); ?></strong>
								<?php echo esc_html( $issue['fix'] ); ?>
							</p>
						</div>
					</details>
				<?php endforeach; ?>
			</div>

			<div class="slm-help-section" style="margin-top:32px;">
				<h3><?php esc_html_e( 'Diagnostic tools', 'kalahamoon' ); ?></h3>
				<div class="slm-help-diag-grid">

					<div class="slm-help-diag-card">
						<h4><?php esc_html_e( 'Check REST API', 'kalahamoon' ); ?></h4>
						<p><?php esc_html_e( 'Open this URL in your browser to verify the Kalahamoon endpoints are registered:', 'kalahamoon' ); ?></p>
						<?php
						$rest_url = rest_url( 'kalahamoon/v1/' );
						echo '<div class="slm-help-code-row">';
						echo '<code class="slm-help-code-block" dir="ltr">' . esc_html( $rest_url ) . '</code>';
						echo '<button class="slm-help-copy-btn" data-copy="' . esc_attr( $rest_url ) . '">' . esc_html__( 'Copy', 'kalahamoon' ) . '</button>';
						echo '</div>';
						?>
					</div>

					<div class="slm-help-diag-card">
						<h4><?php esc_html_e( 'Test product search', 'kalahamoon' ); ?></h4>
						<p><?php esc_html_e( 'This URL should return a JSON array of your synced products:', 'kalahamoon' ); ?></p>
						<?php
						$search_url = rest_url( 'kalahamoon/v1/products?limit=5' );
						echo '<div class="slm-help-code-row">';
						echo '<code class="slm-help-code-block" dir="ltr">' . esc_html( $search_url ) . '</code>';
						echo '<button class="slm-help-copy-btn" data-copy="' . esc_attr( $search_url ) . '">' . esc_html__( 'Copy', 'kalahamoon' ) . '</button>';
						echo '</div>';
						?>
					</div>

					<div class="slm-help-diag-card">
						<h4><?php esc_html_e( 'PHP version & memory', 'kalahamoon' ); ?></h4>
						<p><?php esc_html_e( 'Kalahamoon requires PHP 8.0 or higher and at least 128 MB of memory.', 'kalahamoon' ); ?></p>
						<p>
							<strong><?php esc_html_e( 'Your PHP:', 'kalahamoon' ); ?></strong>
							<code><?php echo esc_html( PHP_VERSION ); ?></code>
							&nbsp;
							<strong><?php esc_html_e( 'Memory limit:', 'kalahamoon' ); ?></strong>
							<code><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></code>
						</p>
					</div>

					<div class="slm-help-diag-card">
						<h4><?php esc_html_e( 'Last product sync', 'kalahamoon' ); ?></h4>
						<?php
						$last = get_option( 'kalahamoon_last_sync', '' );
						if ( $last ) {
							echo '<p>' . esc_html__( 'Last sync completed:', 'kalahamoon' ) . ' <strong>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last ) ) ) . '</strong></p>';
						} else {
							echo '<p>' . esc_html__( 'No sync recorded yet. Go to Dashboard → Sync Now.', 'kalahamoon' ) . '</p>';
						}
						?>
					</div>

				</div>
			</div>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Reusable components
	// ─────────────────────────────────────────────────────────────────────────

	private static function step( int $n, string $title, string $body, array $notes ): void {
		?>
		<div class="slm-help-step">
			<div class="slm-help-step-num" aria-hidden="true"><?php echo (int) $n; ?></div>
			<div class="slm-help-step-content">
				<h3><?php echo esc_html( $title ); ?></h3>
				<p><?php echo wp_kses( $body, array( 'a' => array( 'href' => array() ), 'strong' => array() ) ); ?></p>
				<?php if ( $notes ) : ?>
					<ul class="slm-help-step-notes">
						<?php foreach ( $notes as $note ) : ?>
							<li><?php echo esc_html( $note ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function feature_card( string $icon, string $title, string $desc, string $link ): void {
		?>
		<div class="slm-help-feat-card">
			<div class="slm-help-feat-icon" aria-hidden="true"><?php echo esc_html( $icon ); ?></div>
			<div class="slm-help-feat-title"><?php echo esc_html( $title ); ?></div>
			<p class="slm-help-feat-desc"><?php echo esc_html( $desc ); ?></p>
			<?php if ( $link ) : ?>
				<a class="slm-help-feat-link" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Learn more →', 'kalahamoon' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function block_card( array $b ): void {
		?>
		<div class="slm-help-block-card" id="<?php echo esc_attr( $b['id'] ); ?>">
			<div class="slm-help-block-header">
				<span class="slm-help-block-icon" aria-hidden="true"><?php echo esc_html( $b['icon'] ); ?></span>
				<div>
					<h3 class="slm-help-block-name"><?php echo esc_html( $b['name'] ); ?></h3>
					<p class="slm-help-block-tagline"><?php echo esc_html( $b['tagline'] ); ?></p>
				</div>
			</div>

			<div class="slm-help-block-body">
				<div class="slm-help-block-col">
					<h4><?php esc_html_e( 'When to use', 'kalahamoon' ); ?></h4>
					<p><?php echo esc_html( $b['when'] ); ?></p>

					<?php if ( $b['note'] ) : ?>
						<div class="slm-help-callout slm-help-callout--tip">
							<?php echo esc_html( $b['note'] ); ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="slm-help-block-col">
					<h4><?php esc_html_e( 'How to use', 'kalahamoon' ); ?></h4>
					<ol class="slm-help-block-steps">
						<?php foreach ( $b['steps'] as $step ) : ?>
							<li><?php echo esc_html( $step ); ?></li>
						<?php endforeach; ?>
					</ol>
				</div>

				<?php if ( ! empty( $b['attrs'] ) ) : ?>
					<div class="slm-help-block-col slm-help-block-col--wide">
						<h4><?php esc_html_e( 'Block settings', 'kalahamoon' ); ?></h4>
						<table class="slm-help-attr-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Setting', 'kalahamoon' ); ?></th>
									<th><?php esc_html_e( 'What it does', 'kalahamoon' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $b['attrs'] as $attr => $desc ) : ?>
									<tr>
										<td><code dir="ltr"><?php echo esc_html( $attr ); ?></code></td>
										<td><?php echo esc_html( $desc ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function settings_group( string $title, string $intro, array $items ): void {
		?>
		<div class="slm-help-settings-group">
			<h3><?php echo esc_html( $title ); ?></h3>
			<p class="slm-help-settings-intro"><?php echo esc_html( $intro ); ?></p>
			<dl class="slm-help-settings-list">
				<?php foreach ( $items as $item ) : ?>
					<div class="slm-help-settings-row">
						<dt><?php echo esc_html( $item['label'] ); ?></dt>
						<dd><?php echo esc_html( $item['desc'] ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		</div>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Styles
	// ─────────────────────────────────────────────────────────────────────────

	private static function print_styles(): void {
		?>
		<style>
		/* ── Layout ── */
		.slm-help { max-width: 960px; }
		.slm-help-title {
			display: flex; align-items: center; gap: 10px;
			font-size: 1.5rem; font-weight: 700; margin-top: 20px; margin-bottom: 4px;
			color: #1d2327;
		}
		.slm-help-logo { font-size: 1.4rem; }
		.slm-help-subtitle { color: #646970; margin-top: 0; margin-bottom: 24px; font-size: 14px; }

		/* ── Nav tabs ── */
		.slm-help-nav {
			display: flex; flex-wrap: wrap; gap: 2px;
			border-bottom: 2px solid #dcdcde;
			margin-bottom: 28px;
		}
		.slm-help-nav-item {
			padding: 10px 18px; font-size: 13px; font-weight: 500;
			color: #50575e; text-decoration: none;
			border-radius: 4px 4px 0 0;
			border: 1px solid transparent;
			border-bottom: none;
			margin-bottom: -2px;
			transition: background .15s, color .15s;
		}
		.slm-help-nav-item:hover { background: #f6f7f7; color: #1d2327; }
		.slm-help-nav-item.is-active {
			background: #fff; color: #2271b1;
			border-color: #dcdcde; border-bottom-color: #fff;
			font-weight: 600;
		}

		/* ── Body ── */
		.slm-help-body { padding: 0; }
		.slm-help-section { margin-bottom: 40px; }
		.slm-help-section h2 {
			font-size: 1.125rem; font-weight: 700; color: #1d2327;
			margin: 0 0 8px; padding-bottom: 10px;
			border-bottom: 1px solid #dcdcde;
		}
		.slm-help-section h3 { font-size: 1rem; font-weight: 600; color: #1d2327; margin: 24px 0 6px; }
		.slm-help-section p { font-size: 13.5px; color: #3c434a; line-height: 1.6; margin-top: 0; }

		/* ── Getting Started steps ── */
		.slm-help-steps { display: flex; flex-direction: column; gap: 0; margin-top: 20px; }
		.slm-help-step {
			display: flex; gap: 20px; align-items: flex-start;
			padding: 20px 0; border-bottom: 1px solid #f0f0f1;
		}
		.slm-help-step:last-child { border-bottom: none; }
		.slm-help-step-num {
			flex-shrink: 0;
			width: 34px; height: 34px;
			background: #2271b1; color: #fff;
			border-radius: 50%; display: flex; align-items: center; justify-content: center;
			font-size: 14px; font-weight: 700;
		}
		.slm-help-step-content h3 { margin: 0 0 6px; font-size: 14px; font-weight: 600; color: #1d2327; }
		.slm-help-step-content p { margin: 0 0 8px; }
		.slm-help-step-notes { margin: 8px 0 0; padding-inline-start: 18px; }
		.slm-help-step-notes li { font-size: 12.5px; color: #646970; line-height: 1.5; margin-bottom: 4px; }

		/* ── Feature cards grid ── */
		.slm-help-card-grid {
			display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
			gap: 16px; margin-top: 20px;
		}
		.slm-help-feat-card {
			background: #fff; border: 1px solid #dcdcde; border-radius: 8px;
			padding: 18px 20px;
		}
		.slm-help-feat-icon { font-size: 1.6rem; margin-bottom: 8px; }
		.slm-help-feat-title { font-size: 13.5px; font-weight: 700; color: #1d2327; margin-bottom: 6px; }
		.slm-help-feat-desc { font-size: 12.5px; color: #646970; line-height: 1.55; margin: 0 0 10px; }
		.slm-help-feat-link { font-size: 12.5px; color: #2271b1; text-decoration: none; font-weight: 500; }
		.slm-help-feat-link:hover { text-decoration: underline; }

		/* ── Block cards ── */
		.slm-help-block-card {
			background: #fff; border: 1px solid #dcdcde; border-radius: 8px;
			margin-bottom: 20px; overflow: hidden;
		}
		.slm-help-block-header {
			display: flex; align-items: flex-start; gap: 14px;
			padding: 18px 20px; background: #f6f7f7;
			border-bottom: 1px solid #dcdcde;
		}
		.slm-help-block-icon { font-size: 1.8rem; flex-shrink: 0; margin-top: 2px; }
		.slm-help-block-name { margin: 0 0 4px; font-size: 15px; font-weight: 700; color: #1d2327; }
		.slm-help-block-tagline { margin: 0; font-size: 13px; color: #646970; line-height: 1.45; }
		.slm-help-block-body {
			display: grid; grid-template-columns: 1fr 1fr;
			gap: 0;
		}
		.slm-help-block-col {
			padding: 16px 20px; border-inline-end: 1px solid #f0f0f1;
		}
		.slm-help-block-col:last-child { border-inline-end: none; }
		.slm-help-block-col--wide { grid-column: span 2; border-top: 1px solid #f0f0f1; border-inline-end: none; }
		.slm-help-block-col h4 { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #646970; margin: 0 0 10px; }
		.slm-help-block-col p { font-size: 13px; color: #3c434a; margin: 0; }
		.slm-help-block-steps { margin: 0; padding-inline-start: 18px; }
		.slm-help-block-steps li { font-size: 13px; color: #3c434a; line-height: 1.55; margin-bottom: 6px; }

		/* ── Attribute tables ── */
		.slm-help-attr-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
		.slm-help-attr-table th {
			background: #f6f7f7; text-align: start; padding: 7px 10px;
			font-weight: 700; color: #646970; font-size: 11px; text-transform: uppercase; letter-spacing: .04em;
			border-bottom: 1px solid #dcdcde;
		}
		.slm-help-attr-table td { padding: 8px 10px; border-bottom: 1px solid #f0f0f1; color: #3c434a; vertical-align: top; }
		.slm-help-attr-table tr:last-child td { border-bottom: none; }
		.slm-help-attr-table code { background: #f0f0f1; padding: 1px 5px; border-radius: 3px; font-size: 11.5px; }

		/* ── Shortcode cards ── */
		.slm-help-shortcode-card {
			background: #fff; border: 1px solid #dcdcde; border-radius: 8px;
			margin-bottom: 16px; overflow: hidden;
		}
		.slm-help-shortcode-header {
			display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap;
			padding: 14px 18px; background: #f6f7f7; border-bottom: 1px solid #dcdcde;
		}
		.slm-help-shortcode-tag {
			background: #2271b1; color: #fff; padding: 3px 10px;
			border-radius: 4px; font-size: 13px; font-weight: 600;
		}
		.slm-help-shortcode-desc { font-size: 13px; color: #3c434a; }
		.slm-help-shortcode-example { padding: 12px 18px; background: #fafafa; border-top: 1px solid #f0f0f1; }
		.slm-help-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #646970; display: block; margin-bottom: 6px; }
		.slm-help-code-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
		.slm-help-code-block {
			background: #1d2327; color: #7dc4e0; padding: 8px 12px;
			border-radius: 5px; font-size: 12px; flex: 1; word-break: break-all;
			font-family: Consolas, Monaco, monospace; direction: ltr; text-align: start;
		}
		.slm-help-copy-btn {
			flex-shrink: 0; padding: 6px 14px; font-size: 12px; font-weight: 600;
			background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;
			cursor: pointer; color: #50575e; transition: background .15s, color .15s;
		}
		.slm-help-copy-btn:hover { background: #2271b1; color: #fff; border-color: #2271b1; }

		/* ── Patterns ── */
		.slm-help-pattern-list { margin-top: 20px; display: flex; flex-direction: column; gap: 0; }
		.slm-help-pattern-row {
			display: flex; gap: 16px; align-items: flex-start;
			padding: 18px 0; border-bottom: 1px solid #f0f0f1;
		}
		.slm-help-pattern-row:last-child { border-bottom: none; }
		.slm-help-pattern-icon { font-size: 1.8rem; flex-shrink: 0; margin-top: 2px; width: 36px; text-align: center; }
		.slm-help-pattern-body strong { font-size: 14px; font-weight: 700; color: #1d2327; }
		.slm-help-pattern-body p { font-size: 13px; color: #3c434a; margin: 6px 0; }
		.slm-help-pattern-how { color: #646970 !important; font-size: 12.5px !important; }

		/* ── Settings groups ── */
		.slm-help-settings-group { margin-bottom: 28px; }
		.slm-help-settings-intro { font-size: 13px; color: #646970; margin: 4px 0 12px; }
		.slm-help-settings-list { margin: 0; }
		.slm-help-settings-row { display: grid; grid-template-columns: 220px 1fr; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f0f0f1; }
		.slm-help-settings-row:last-child { border-bottom: none; }
		.slm-help-settings-row dt { font-size: 13px; font-weight: 600; color: #1d2327; padding-top: 1px; }
		.slm-help-settings-row dd { margin: 0; font-size: 13px; color: #3c434a; line-height: 1.55; }

		/* ── Troubleshooting ── */
		.slm-help-issue-list { margin-top: 16px; }
		.slm-help-issue {
			border: 1px solid #dcdcde; border-radius: 7px;
			margin-bottom: 8px; background: #fff; overflow: hidden;
		}
		.slm-help-issue-summary {
			display: flex; align-items: center; gap: 10px;
			padding: 14px 16px; cursor: pointer;
			font-size: 13.5px; font-weight: 600; color: #1d2327;
			list-style: none; user-select: none;
		}
		.slm-help-issue-summary::-webkit-details-marker { display: none; }
		.slm-help-issue-summary::after {
			content: '›'; margin-inline-start: auto; font-size: 18px; color: #646970;
			transition: transform .2s;
		}
		[dir="rtl"] .slm-help-issue-summary::after { content: '‹'; }
		.slm-help-issue[open] .slm-help-issue-summary::after { transform: rotate(90deg); }
		.slm-help-issue-icon { font-size: 1rem; flex-shrink: 0; }
		.slm-help-issue-body { padding: 0 16px 16px; padding-inline-start: 44px; }
		.slm-help-issue-body p { font-size: 13px; color: #3c434a; margin: 8px 0 0; line-height: 1.55; }

		/* ── Diagnostic grid ── */
		.slm-help-diag-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; }
		.slm-help-diag-card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 16px 18px; }
		.slm-help-diag-card h4 { margin: 0 0 8px; font-size: 13px; font-weight: 700; color: #1d2327; }
		.slm-help-diag-card p { font-size: 12.5px; color: #646970; margin: 0 0 8px; }
		.slm-help-diag-card code { background: #f0f0f1; padding: 1px 5px; border-radius: 3px; font-size: 12px; }

		/* ── Callouts ── */
		.slm-help-callout {
			border-radius: 6px; padding: 12px 16px;
			font-size: 13px; line-height: 1.55; margin: 14px 0;
		}
		.slm-help-callout--info { background: #e8f0fb; border-inline-start: 3px solid #2271b1; color: #1d4587; }
		.slm-help-callout--tip { background: #edfce7; border-inline-start: 3px solid #16a34a; color: #14532d; }
		.slm-help-callout--action { background: transparent; border: none; padding: 8px 0 0; }

		/* ── Responsive ── */
		@media (max-width: 782px) {
			.slm-help-block-body { grid-template-columns: 1fr; }
			.slm-help-block-col--wide { grid-column: span 1; }
			.slm-help-block-col { border-inline-end: none; border-bottom: 1px solid #f0f0f1; }
			.slm-help-card-grid { grid-template-columns: 1fr; }
			.slm-help-diag-grid { grid-template-columns: 1fr; }
			.slm-help-settings-row { grid-template-columns: 1fr; gap: 4px; }
		}
		</style>
		<?php
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Scripts
	// ─────────────────────────────────────────────────────────────────────────

	private static function print_scripts(): void {
		?>
		<script>
		document.querySelectorAll('.slm-help-copy-btn').forEach(function(btn) {
			btn.addEventListener('click', function() {
				const text = this.dataset.copy;
				const orig = this.textContent;
				if (navigator.clipboard) {
					navigator.clipboard.writeText(text);
				} else {
					const ta = document.createElement('textarea');
					ta.value = text; ta.dir = 'ltr'; document.body.appendChild(ta); ta.select();
					document.execCommand('copy'); document.body.removeChild(ta);
				}
				this.textContent = '✓ <?php echo esc_js( __( 'Copied', 'kalahamoon' ) ); ?>';
				setTimeout(() => { this.textContent = orig; }, 1400);
			});
		});
		</script>
		<?php
	}
}
