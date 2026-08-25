<?php
/**
 * Main plugin orchestrator.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Kalahamoon_Plugin {

	private static ?Kalahamoon_Plugin $instance = null;

	public static function instance(): Kalahamoon_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	private function load_dependencies(): void {
		$dir = KALAHAMOON_PLUGIN_DIR . 'includes/';

		// Core
		require_once $dir . 'class-kalahamoon-activator.php';
		require_once $dir . 'class-kalahamoon-deactivator.php';
		require_once $dir . 'i18n/class-kalahamoon-i18n.php';
		require_once $dir . 'i18n/class-kalahamoon-rtl.php';

		// API client
		require_once $dir . 'api/class-kalahamoon-api-client.php';

		// Auth (KalahamoonAuth OAuth)
		require_once $dir . 'auth/class-kalahamoon-token-store.php';
		require_once $dir . 'auth/class-kalahamoon-auth.php';
		require_once $dir . 'api/class-kalahamoon-catalog-consumer.php';
		require_once $dir . 'api/class-kalahamoon-api-products.php';

		// Core features
		require_once $dir . 'core/class-kalahamoon-catalog-policy.php';
		require_once $dir . 'core/class-kalahamoon-image-policy.php';
		require_once $dir . 'core/class-kalahamoon-product-cache.php';
		require_once $dir . 'core/class-kalahamoon-link-builder.php';
		require_once $dir . 'core/class-kalahamoon-link-cloaker.php';
		require_once $dir . 'core/class-kalahamoon-click-tracker.php';
		require_once $dir . 'core/class-kalahamoon-schema-output.php';
		require_once $dir . 'core/class-kalahamoon-disclosure.php';
		require_once $dir . 'core/class-kalahamoon-auto-linker.php';
		require_once $dir . 'core/class-kalahamoon-price-alert-mailer.php';

		// Display
		require_once $dir . 'display/class-kalahamoon-shortcodes.php';
		require_once $dir . 'display/class-kalahamoon-patterns.php';
		require_once $dir . 'display/class-kalahamoon-block-styles.php';
		require_once $dir . 'display/class-kalahamoon-placeholder.php';
		require_once $dir . 'display/class-kalahamoon-listings.php';

		// Admin
		require_once $dir . 'admin/class-kalahamoon-admin.php';
		require_once $dir . 'admin/class-kalahamoon-help.php';
		require_once $dir . 'admin/class-kalahamoon-onboarding.php';
		require_once $dir . 'admin/class-kalahamoon-changelog.php';

		// REST API
		require_once $dir . 'rest/class-kalahamoon-rest-controller.php';

		// Classic widgets (work alongside the blocks in widget-block editors too)
		require_once KALAHAMOON_PLUGIN_DIR . 'widgets/class-kalahamoon-widget-trending.php';
		require_once KALAHAMOON_PLUGIN_DIR . 'widgets/class-kalahamoon-widget-recently-viewed.php';
		require_once KALAHAMOON_PLUGIN_DIR . 'widgets/class-kalahamoon-widget-favorites.php';
	}

	private function init_hooks(): void {
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_filter( 'gettext', array( 'Kalahamoon_I18n', 'admin_gettext' ), 10, 3 );
		Kalahamoon_Catalog_Consumer::init_origin_proof_endpoint();

		// Register classic widgets (also usable via the legacy Widget block in
		// block themes).
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );

		// Legacy standalone sites may still opt into plugin tokens. Connector sites
		// leave visual ownership with their theme, including typography and tokens.
		if ( ! Kalahamoon_Catalog_Consumer::is_enabled() ) {
			add_filter( 'wp_theme_json_data_default', array( $this, 'merge_theme_json' ) );
		}

		// Register block category
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ), 10, 2 );

		// Settings link on plugins page
		add_filter( 'plugin_action_links_' . KALAHAMOON_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

		// Existing product shortcodes remain a renderer-only compatibility layer
		// while published content is migrated. Everything that rewrites content,
		// tracks visitors, or exposes legacy authoring stays out of connector mode.
		Kalahamoon_Shortcodes::init();
		Kalahamoon_Admin::init();
		if ( ! Kalahamoon_Catalog_Consumer::is_enabled() ) {
			Kalahamoon_Link_Cloaker::init();
			Kalahamoon_Click_Tracker::init();
			Kalahamoon_Patterns::init();
			Kalahamoon_Block_Styles::init();
			Kalahamoon_Schema_Output::init();
			Kalahamoon_Disclosure::init();
			Kalahamoon_Auto_Linker::init();
			Kalahamoon_Price_Alert_Mailer::init();
			Kalahamoon_Help::init();
			Kalahamoon_Onboarding::init();
			Kalahamoon_Changelog::init();
		}
	}

	public function on_init(): void {
		Kalahamoon_Activator::maybe_upgrade();
		Kalahamoon_Product_Cache::register_post_type();
		$this->register_blocks();
		$this->register_script_translations();
	}

	/**
	 * Wire JS translations for every block's editor script so __() strings
	 * inside index.js pick up the shipped .mo files.
	 */
	private function register_script_translations(): void {
		if ( ! function_exists( 'wp_set_script_translations' ) ) {
			return;
		}
		$blocks = glob( KALAHAMOON_PLUGIN_DIR . 'blocks/*/block.json' ) ?: array();
		foreach ( $blocks as $file ) {
			$handle = 'kalahamoon-' . basename( dirname( $file ) ) . '-editor-script';
			wp_set_script_translations( $handle, 'kalahamoon', KALAHAMOON_PLUGIN_DIR . 'languages' );
		}
	}

	private function register_blocks(): void {
		$blocks_dir = KALAHAMOON_PLUGIN_DIR . 'blocks/';
		$block_dirs = glob( $blocks_dir . '*/block.json' );
		foreach ( $block_dirs as $block_json ) {
			$block = register_block_type( dirname( $block_json ) );
			if ( Kalahamoon_Catalog_Consumer::is_enabled() && $block instanceof WP_Block_Type ) {
				// Existing markup keeps rendering for a measured content migration, but
				// WordPress should not offer this legacy plugin as a new authoring path.
				$block->supports['inserter'] = false;
			}
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'kalahamoon', false, dirname( KALAHAMOON_PLUGIN_BASENAME ) . '/languages' );
	}

	public function register_widgets(): void {
		if ( Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return;
		}
		register_widget( 'Kalahamoon_Widget_Trending' );
		register_widget( 'Kalahamoon_Widget_Recently_Viewed' );
		register_widget( 'Kalahamoon_Widget_Favorites' );
	}

	public function enqueue_public_assets(): void {
		if ( Kalahamoon_Catalog_Consumer::is_enabled() ) {
			// Registered blocks retain their own scoped style handles. Do not enqueue
			// the historic global stylesheet, font, tracker, or legacy form workflow
			// over the publication.
			return;
		}

		/**
		 * Allow themes to opt out of the plugin's shared stylesheet.
		 *
		 * When a theme ships its own Kalahamoon design, it can disable the default
		 * stylesheet entirely while still using the blocks and tokens.
		 *
		 * @param bool $enqueue Whether to enqueue kalahamoon-public.css. Default true.
		 */
		if ( apply_filters( 'kalahamoon_enqueue_public_styles', true ) ) {
			wp_enqueue_style(
				'kalahamoon-public',
				KALAHAMOON_PLUGIN_URL . 'public/css/kalahamoon-public.css',
				array(),
				KALAHAMOON_VERSION
			);

			$this->emit_token_bridge();

			// Opt-in: restore the pre-2026 dark mode toggle for sites that still
			// rely on `.kalahamoon-dark` or the plugin's prefers-color-scheme override.
			// Theme-native dark mode works out of the box without this stylesheet.
			if ( get_option( 'kalahamoon_legacy_dark_mode', false ) ) {
				wp_enqueue_style(
					'kalahamoon-public-legacy-dark',
					KALAHAMOON_PLUGIN_URL . 'public/css/kalahamoon-public-legacy-dark.css',
					array( 'kalahamoon-public' ),
					KALAHAMOON_VERSION
				);
			}
		}

		// Public blocks may be rendered on Persian/Arabic sites and need their
		// matching font; the English-only rule applies to the admin UI only.
		if ( Kalahamoon_RTL::needs_rtl_font() && ! $this->theme_provides_persian_font() ) {
			wp_enqueue_style( 'kalahamoon-yekanbakh', KALAHAMOON_PLUGIN_URL . 'assets/fonts/yekanbakh.css', array(), KALAHAMOON_VERSION );
		}

		/**
		 * Allow themes/performance plugins to disable the global click/favorite
		 * script and provide their own tracker. Enabled by default because it also
		 * hydrates favorite buttons and the privacy-friendly recently-viewed list.
		 *
		 * @param bool $enqueue Whether to enqueue kalahamoon-click-tracker.js.
		 */
		if ( apply_filters( 'kalahamoon_enqueue_click_tracker', true ) ) {
			wp_enqueue_script(
				'kalahamoon-click-tracker',
				KALAHAMOON_PLUGIN_URL . 'public/js/kalahamoon-click-tracker.js',
				array(),
				KALAHAMOON_VERSION,
				array( 'strategy' => 'defer', 'in_footer' => true )
			);

			wp_localize_script(
				'kalahamoon-click-tracker',
				'kalahamoonConfig',
				array_merge(
					Kalahamoon_RTL::script_locale_config(),
					array(
						'restUrl'     => esc_url_raw( rest_url( 'kalahamoon/v1/' ) ),
						'nonce'       => wp_create_nonce( 'wp_rest' ),
						'currency'    => Kalahamoon_RTL::get_display_currency(),
						'displayUnit' => Kalahamoon_RTL::get_display_unit(),
						'storageMode' => (string) apply_filters( 'kalahamoon_public_storage_mode', 'local' ),
					)
				)
			);
		}

		// Front-end forms (lead-capture + price-alert blocks). Self-contained
		// submit handler that posts JSON to the plugin REST namespace.
		if ( apply_filters( 'kalahamoon_enqueue_forms', $this->page_has_public_form_surface() ) ) {
			$this->enqueue_public_forms();
		}
	}

	private function enqueue_public_forms(): void {
		wp_enqueue_script(
			'kalahamoon-forms',
			KALAHAMOON_PLUGIN_URL . 'public/js/kalahamoon-forms.js',
			array(),
			KALAHAMOON_VERSION,
			array( 'strategy' => 'defer', 'in_footer' => true )
		);

		wp_localize_script(
			'kalahamoon-forms',
			'kalahamoonForms',
			array(
				'restUrl' => esc_url_raw( rest_url( 'kalahamoon/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	private function page_has_public_form_surface(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		foreach ( array( 'kalahamoon/lead-form', 'kalahamoon/price-alert' ) as $block_name ) {
			if ( has_block( $block_name, $post ) ) {
				return true;
			}
		}

		$content = (string) $post->post_content;
		foreach ( array( 'kalahamoon_lead_form', 'kalahamoon_price_alert' ) as $shortcode ) {
			if ( has_shortcode( $content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Inject the CSS token bridge as inline CSS after kalahamoon-public.css.
	 *
	 * Each `--kalahamoon-*` value is resolved through the `kalahamoon_css_tokens` filter
	 * so themes/plugins can swap tokens without patching the plugin.
	 */
	private function emit_token_bridge( string $handle = 'kalahamoon-public' ): void {
		$defaults = array(
			'primary'        => 'var(--wp--preset--color--primary, var(--wp--custom--kalahamoon--accent, #2563eb))',
			'primary-hover'  => 'color-mix(in srgb, var(--kalahamoon-primary) 85%, var(--kalahamoon-text))',
			'on-primary'     => 'var(--wp--preset--color--base, #ffffff)',
			'success'        => 'var(--wp--preset--color--success, var(--wp--preset--color--vivid-green-cyan, #16a34a))',
			'danger'         => 'var(--wp--preset--color--vivid-red, #dc2626)',
			'warning'        => 'var(--wp--preset--color--luminous-vivid-amber, #f59e0b)',
			'text'           => 'var(--wp--preset--color--contrast, var(--wp--custom--kalahamoon--text, #1f2937))',
			'muted'          => 'color-mix(in srgb, var(--kalahamoon-text) 65%, transparent)',
			'text-muted'     => 'var(--kalahamoon-muted)',
			'surface'        => 'var(--wp--preset--color--base, var(--wp--custom--kalahamoon--surface, #ffffff))',
			'surface-alt'    => 'color-mix(in srgb, var(--kalahamoon-surface) 96%, var(--kalahamoon-text))',
			'bg'             => 'var(--kalahamoon-surface)',
			'bg-muted'       => 'var(--kalahamoon-surface-alt)',
			'border'         => 'var(--wp--custom--kalahamoon--border, color-mix(in srgb, var(--kalahamoon-text) 12%, transparent))',
			'radius'         => 'var(--wp--custom--radius--medium, var(--wp--custom--kalahamoon--radius-card, 12px))',
			'radius-sm'      => 'var(--wp--custom--radius--small, 8px)',
			'radius-lg'      => 'var(--wp--custom--radius--large, 20px)',
			'shadow'         => 'var(--wp--custom--shadow--natural, 0 1px 3px rgba(0,0,0,.08))',
			'shadow-lg'      => 'var(--wp--custom--shadow--deep, var(--wp--custom--kalahamoon--shadow-card-hover, 0 4px 12px rgba(0,0,0,.1)))',
			'focus'          => 'var(--wp--custom--shadow--focus, 0 0 0 3px color-mix(in srgb, var(--kalahamoon-primary) 25%, transparent))',
			'gap'            => 'var(--wp--preset--spacing--40, 1rem)',
			'gap-lg'         => 'var(--wp--preset--spacing--60, 1.5rem)',
			'gap-sm'         => 'var(--wp--preset--spacing--30, 0.75rem)',
			// Marketplace brand colors — intentionally literal, not themable.
			'bakalahamoon'        => '#ff6b35',
			'digikala'       => '#ef394e',
			'torob'          => '#00b4d8',
		);

		/**
		 * Filter the Kalahamoon CSS token map.
		 *
		 * Each key becomes `--kalahamoon-{key}` in the injected :root block. Values
		 * are CSS expressions (var(), color-mix(), literals) written verbatim.
		 *
		 * @param array<string,string> $tokens
		 */
		$tokens = apply_filters( 'kalahamoon_css_tokens', $defaults );
		if ( ! is_array( $tokens ) || empty( $tokens ) ) {
			return;
		}

		$vars = '';
		foreach ( $tokens as $key => $value ) {
			$name  = sanitize_key( $key );
			$value = is_string( $value ) ? $value : '';
			if ( '' === $name || '' === $value ) {
				continue;
			}
			$vars .= '--kalahamoon-' . $name . ':' . $value . ';';
		}

		if ( '' !== $vars ) {
			wp_add_inline_style( $handle, ':root{' . $vars . '}' );
		}
	}

	/**
	 * Detect whether the active theme already registers a Persian/Arabic
	 * font family through theme.json. When it does we skip Vazirmatn to avoid
	 * fighting the theme's typography.
	 */
	private function theme_provides_persian_font(): bool {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return false;
		}
		$families = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
		if ( empty( $families ) || ! is_array( $families ) ) {
			return false;
		}

		$needles = array( 'vazir', 'iran', 'tahoma', 'shabnam', 'yekan', 'noto naskh', 'noto sans arabic' );
		foreach ( $families as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			foreach ( $group as $family ) {
				if ( empty( $family['fontFamily'] ) ) {
					continue;
				}
				$haystack = strtolower( (string) $family['fontFamily'] );
				foreach ( $needles as $n ) {
					if ( strpos( $haystack, $n ) !== false ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( strpos( $hook, 'kalahamoon' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'kalahamoon-admin',
			KALAHAMOON_PLUGIN_URL . 'admin/css/kalahamoon-admin.css',
			array(),
			file_exists( KALAHAMOON_PLUGIN_DIR . 'admin/css/kalahamoon-admin.css' )
				? (string) filemtime( KALAHAMOON_PLUGIN_DIR . 'admin/css/kalahamoon-admin.css' )
				: KALAHAMOON_VERSION
		);

		wp_enqueue_script(
			'kalahamoon-admin',
			KALAHAMOON_PLUGIN_URL . 'admin/js/kalahamoon-admin.js',
			array( 'wp-i18n' ),
			KALAHAMOON_VERSION,
			true
		);

		wp_localize_script(
			'kalahamoon-admin',
			'kalahamoonAdminConfig',
			Kalahamoon_RTL::admin_script_locale_config()
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'kalahamoon-admin', 'kalahamoon', KALAHAMOON_PLUGIN_DIR . 'languages' );
		}

		if ( Kalahamoon_Catalog_Consumer::is_enabled() ) {
			return;
		}

		$needs_product_picker = false !== strpos( $hook, 'kalahamoon-ai-studio' )
			|| false !== strpos( $hook, 'kalahamoon-auto-links' );
		if ( $needs_product_picker ) {
			wp_enqueue_style(
				'kalahamoon-product-picker',
				KALAHAMOON_PLUGIN_URL . 'admin/css/kalahamoon-product-picker.css',
				array( 'wp-components' ),
				KALAHAMOON_VERSION
			);
			wp_enqueue_script(
				'kalahamoon-product-picker',
				KALAHAMOON_PLUGIN_URL . 'admin/js/kalahamoon-product-picker.js',
				array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
				KALAHAMOON_VERSION,
				true
			);

			wp_localize_script(
				'kalahamoon-product-picker',
				'kalahamoonPickerConfig',
				array_merge(
					Kalahamoon_RTL::admin_script_locale_config(),
					array(
						'restUrl'     => esc_url_raw( rest_url( 'kalahamoon/v1/' ) ),
						'nonce'       => wp_create_nonce( 'wp_rest' ),
						'currency'    => Kalahamoon_RTL::get_display_currency(),
						'displayUnit' => Kalahamoon_RTL::get_display_unit(),
					)
				)
			);

		}

		if ( false !== strpos( $hook, 'kalahamoon-product-editor' ) ) {
			wp_enqueue_media();
		}

		if ( false !== strpos( $hook, 'kalahamoon-ai-studio' ) ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'kalahamoon-ai-studio',
				KALAHAMOON_PLUGIN_URL . 'admin/js/kalahamoon-ai-studio.js',
				array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'kalahamoon-product-picker' ),
				KALAHAMOON_VERSION,
				true
			);
			wp_localize_script(
				'kalahamoon-ai-studio',
				'kalahamoonStudioConfig',
				array_merge(
					Kalahamoon_RTL::admin_script_locale_config(),
					array(
						'restUrl' => esc_url_raw( rest_url( 'kalahamoon/v1/' ) ),
						'nonce'   => wp_create_nonce( 'wp_rest' ),
					)
				)
			);
			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'kalahamoon-ai-studio', 'kalahamoon', KALAHAMOON_PLUGIN_DIR . 'languages' );
			}
		}
	}

	/**
	 * Enqueue scripts only for the Gutenberg block editor screen.
	 * Loads the shared Product Picker modal used by every Kalahamoon block.
	 */
	public function enqueue_block_editor_assets(): void {
		if ( Kalahamoon_Catalog_Consumer::is_enabled() ) {
			// Existing blocks remain parseable for historic content, but no product
			// picker is loaded into new editor sessions.
			return;
		}

		// Load the shared frontend stylesheet inside the editor canvas so the
		// ServerSideRender previews (product box, comparison table, shop-the-look,
		// etc.) inherit the same card/image/aspect-ratio rules they get on the
		// front end. Without this the editor renders raw, unconstrained markup —
		// e.g. product images blowing up to full width.
		if ( apply_filters( 'kalahamoon_enqueue_public_styles', true ) ) {
			wp_enqueue_style(
				'kalahamoon-public',
				KALAHAMOON_PLUGIN_URL . 'public/css/kalahamoon-public.css',
				array(),
				KALAHAMOON_VERSION
			);
			$this->emit_token_bridge( 'kalahamoon-public' );
		}

		wp_enqueue_style(
			'kalahamoon-product-picker',
			KALAHAMOON_PLUGIN_URL . 'admin/css/kalahamoon-product-picker.css',
			array( 'wp-components' ),
			KALAHAMOON_VERSION
		);

		wp_enqueue_script(
			'kalahamoon-product-picker',
			KALAHAMOON_PLUGIN_URL . 'admin/js/kalahamoon-product-picker.js',
			array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
			KALAHAMOON_VERSION,
			true
		);

		wp_localize_script(
			'kalahamoon-product-picker',
			'kalahamoonPickerConfig',
			array_merge(
				Kalahamoon_RTL::script_locale_config(),
				array(
					'restUrl'     => esc_url_raw( rest_url( 'kalahamoon/v1/' ) ),
					'nonce'       => wp_create_nonce( 'wp_rest' ),
					'currency'    => Kalahamoon_RTL::get_display_currency(),
					'displayUnit' => Kalahamoon_RTL::get_display_unit(),
				)
			)
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'kalahamoon-product-picker', 'kalahamoon', KALAHAMOON_PLUGIN_DIR . 'languages' );
		}

		// Each block's editor script depends on the picker so inspector buttons
		// can call window.kalahamoonPicker.open() without a race. wp_script_add_data()
		// cannot add dependencies — we have to push onto the registered script's
		// deps array directly.
		$scripts = wp_scripts();
		foreach ( array( 'product-box', 'product-grid', 'comparison-table', 'cta-button', 'pros-cons', 'shop-the-look', 'ai-compare', 'price-comparison', 'price-alert' ) as $slug ) {
			$handle = 'kalahamoon-' . $slug . '-editor-script';
			if ( isset( $scripts->registered[ $handle ] ) && ! in_array( 'kalahamoon-product-picker', $scripts->registered[ $handle ]->deps, true ) ) {
				$scripts->registered[ $handle ]->deps[] = 'kalahamoon-product-picker';
			}
		}
	}

	public function register_rest_routes(): void {
		Kalahamoon_REST_Controller::register();
	}

	/**
	 * Merge plugin-scoped theme.json settings into the global theme.json chain.
	 *
	 * We ship settings only (palette, custom radius/shadow). The theme's own
	 * theme.json wins whenever both define the same key — so this adds without
	 * overriding the host theme.
	 *
	 * @param WP_Theme_JSON_Data $theme_json
	 * @return WP_Theme_JSON_Data
	 */
	public function merge_theme_json( $theme_json ) {
		if ( ! is_object( $theme_json ) || ! method_exists( $theme_json, 'update_with' ) ) {
			return $theme_json;
		}

		$file = KALAHAMOON_PLUGIN_DIR . 'theme.json';
		if ( ! file_exists( $file ) ) {
			return $theme_json;
		}

		$raw = file_get_contents( $file );
		if ( false === $raw ) {
			return $theme_json;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return $theme_json;
		}

		$theme_json->update_with( $data );
		return $theme_json;
	}

	public function register_block_category( array $categories, $context ): array {
		return array_merge(
			array(
				array(
					'slug'  => 'kalahamoon',
					'title' => __( 'Kalahamoon', 'kalahamoon' ),
					'icon'  => 'cart',
				),
			),
			$categories
		);
	}

	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=kalahamoon-setting' ),
			__( 'Settings', 'kalahamoon' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}
