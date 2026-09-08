<?php
/**
 * Public affiliate product comparison controls and table data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Compare {
	public const MAX_PRODUCTS = 4;
	private const QUERY_VAR = 'products';
	private const SEARCH_LIMIT = 12;
	private const CATALOGUE_LIMIT = 24;

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( __CLASS__, 'append_loop_control' ), 100, 3 );
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'render_single_control' ), 25 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_single_control' ), 31 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'offer_label' ), 100, 2 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'offer_label' ), 100, 2 );
		add_shortcode( 'chidemoon_compare_action', array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( 'chidemoon_compare_status', array( __CLASS__, 'render_status_shortcode' ) );
		add_shortcode( 'chidemoon_compare_picker', array( __CLASS__, 'render_picker_shortcode' ) );
		add_shortcode( 'chidemoon_compare_table', array( __CLASS__, 'render_compare_table_shortcode' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 20 );
		add_action( 'init', array( __CLASS__, 'register_elementor_compare_support' ) );
	}

	public static function register_assets(): void {
		$script_path = CHIDEMOON_CORE_DIR . 'assets/js/compare.js';
		$style_path  = CHIDEMOON_CORE_DIR . 'assets/css/compare.css';
		wp_register_script( 'chidemoon-core-compare', CHIDEMOON_CORE_URL . 'assets/js/compare.js', array(), file_exists( $script_path ) ? (string) filemtime( $script_path ) : CHIDEMOON_CORE_VERSION, true );
		wp_register_style( 'chidemoon-core-compare', CHIDEMOON_CORE_URL . 'assets/css/compare.css', array(), file_exists( $style_path ) ? (string) filemtime( $style_path ) : CHIDEMOON_CORE_VERSION );
		if ( is_front_page() || is_shop() || is_product_taxonomy() || is_product() || is_page( array( 'comparisons', 'shop-the-look' ) ) || is_page_template( array( 'page-comparisons.php', 'page-shop-the-look.php' ) ) || has_block( 'chidemoon/shop-the-look' ) ) {
			self::enqueue_assets();
		}
	}

	public static function enqueue_assets(): void {
		static $configured = false;
		if ( ! wp_script_is( 'chidemoon-core-compare', 'registered' ) ) {
			return;
		}
		wp_enqueue_script( 'chidemoon-core-compare' );
		wp_enqueue_style( 'chidemoon-core-compare' );
		if ( $configured ) {
			return;
		}
		$configured = true;
		wp_add_inline_script(
			'chidemoon-core-compare',
			'window.ChidemoonCompare=' . wp_json_encode(
				array(
					'key'       => 'chidemoon.compare.products.v1',
					'maximum'   => self::MAX_PRODUCTS,
					'compareUrl'=> self::comparison_url(),
					'restUrl'   => esc_url_raw( rest_url( 'chidemoon-core/v1/compare-products' ) ),
					'labels'    => array(
						'added'          => __( 'مقایسه', 'chidemoon-core' ),
						'removed'        => __( 'انتخاب شده', 'chidemoon-core' ),
						'full'           => __( 'حداکثر چهار محصول را می‌توانید مقایسه کنید.', 'chidemoon-core' ),
						'compare'        => __( 'مقایسه محصولات', 'chidemoon-core' ),
						'clear'          => __( 'پاک کردن همه', 'chidemoon-core' ),
						'needMore'       => __( 'برای مقایسه حداقل دو محصول انتخاب کنید.', 'chidemoon-core' ),
						'oneMore'        => __( 'برای شروع مقایسه، یک محصول دیگر انتخاب کنید.', 'chidemoon-core' ),
						'count'          => __( 'محصول برای مقایسه', 'chidemoon-core' ),
						'removeItem'     => __( 'حذف از مقایسه', 'chidemoon-core' ),
						'loading'        => __( 'در حال جستجوی محصولات…', 'chidemoon-core' ),
						'noResults'      => __( 'محصولی پیدا نشد.', 'chidemoon-core' ),
						'searchError'    => __( 'جستجو در حال حاضر در دسترس نیست. دوباره تلاش کنید.', 'chidemoon-core' ),
						'sessionOnly'    => __( 'انتخاب‌ها فقط تا پایان این صفحه نگه داشته می‌شوند.', 'chidemoon-core' ),
						'staleSelection' => __( 'برخی انتخاب‌ها دیگر قابل مقایسه نیستند و حذف شدند.', 'chidemoon-core' ),
						'singleAdd'        => __( 'افزودن به مقایسه', 'chidemoon-core' ),
						'singleHint'       => __( 'با حداکثر چهار محصول بسنجید', 'chidemoon-core' ),
						'singleIn'         => __( 'در فهرست مقایسه', 'chidemoon-core' ),
						'singleRemoveHint' => __( 'برای حذف از فهرست کلیک کنید', 'chidemoon-core' ),
					),
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			) . ';',
			'before'
		);
	}

	public static function register_rest_routes(): void {
		register_rest_route(
			'chidemoon-core/v1',
			'/compare-products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search_products' ),
				// Public catalogue search over reviewed products only. Throttled
				// per-IP inside the callback to avoid unauthenticated scraping.
				'permission_callback' => '__return_true',
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'maxLength'         => 120,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'browse' => array(
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'ids' => array(
						'type'              => 'string',
						'maxLength'         => 64,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/** @return WP_REST_Response|WP_Error */
	public static function search_products( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$throttled = self::throttle_public_search();
		if ( is_wp_error( $throttled ) ) {
			return $throttled;
		}

		$term       = trim( (string) $request->get_param( 'search' ) );
		$browse     = (bool) $request->get_param( 'browse' );
		$requested  = self::product_ids( (string) $request->get_param( 'ids' ) );
		if ( ! $browse && empty( $requested ) && self::string_length( $term ) < 2 ) {
			return rest_ensure_response( array() );
		}

		$candidates = ! empty( $requested )
			? self::selected_products( $requested )
			: self::eligible_products( self::SEARCH_LIMIT, $browse ? '' : $term );
		$results = array();
		foreach ( $candidates as $product ) {
			$results[] = array(
				'id'    => $product->get_id(),
				'title' => wp_strip_all_tags( $product->get_name() ),
				'image' => (string) wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_gallery_thumbnail' ),
			);
		}

		return rest_ensure_response( $results );
	}

	/** @return WC_Product[] */
	public static function catalogue_products(): array {
		return self::eligible_products( self::CATALOGUE_LIMIT );
	}

	/** @return WC_Product[] */
	public static function eligible_products( int $limit, string $search = '' ): array {
		$args = array(
			'status'  => 'publish',
			'limit'   => max( $limit, 24 ),
			'orderby' => 'date',
			'order'   => 'DESC',
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$products = array();
		$offset   = 0;
		do {
			$args['offset'] = $offset;
			$page            = wc_get_products( $args );
			foreach ( $page as $product ) {
				if ( ! $product instanceof WC_Product || 'publish' !== get_post_status( $product ) ) {
					continue;
				}
				if ( ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
					continue;
				}
				$products[] = $product;
				if ( count( $products ) === $limit ) {
					break 2;
				}
			}
			$offset += count( $page );
		} while ( count( $page ) === $args['limit'] );

		return $products;
	}

	public static function offer_label( string $label, WC_Product $product ): string {
		return Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ? __( 'خرید از فروشگاه', 'chidemoon-core' ) : $label;
	}

	/** @param array<string, mixed> $args */
	public static function append_loop_control( string $html, WC_Product $product, array $args ): string {
		unset( $args );
		return $html . self::control( $product );
	}

	public static function render_single_control(): void {
		$product = self::current_product();
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		static $rendered = array();
		$product_id      = $product->get_id();
		if ( isset( $rendered[ $product_id ] ) ) {
			return;
		}
		$rendered[ $product_id ] = true;
		echo self::single_control( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** @param array<string, string> $attributes */
	public static function render_shortcode( array $attributes = array() ): string {
		$attributes = shortcode_atts( array( 'product_id' => (string) get_the_ID() ), $attributes, 'chidemoon_compare_action' );
		$product    = wc_get_product( absint( $attributes['product_id'] ) );
		return $product instanceof WC_Product ? self::control( $product ) : '';
	}

	public static function single_control( WC_Product $product ): string {
		if ( ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
			return '';
		}
		self::enqueue_assets();
		return sprintf(
			'<button type="button" class="chidemoon-compare-single" data-compare-product="%1$d" data-compare-name="%2$s" data-compare-image="%3$s" aria-pressed="false">' .
				'<span class="chidemoon-compare-single__icon" aria-hidden="true">' .
					'<svg class="chidemoon-compare-single__icon-add" viewBox="0 0 24 24"><path d="M4 7h11M12 4l3 3-3 3M20 17H9M12 14l-3 3 3 3"/></svg>' .
					'<svg class="chidemoon-compare-single__icon-check" viewBox="0 0 24 24"><path d="M4 12l5 5L20 7"/></svg>' .
				'</span>' .
				'<span class="chidemoon-compare-single__text">' .
					'<span class="chidemoon-compare-single__label">%4$s</span>' .
					'<span class="chidemoon-compare-single__hint">%5$s</span>' .
				'</span>' .
			'</button>',
			$product->get_id(),
			esc_attr( $product->get_name() ),
			esc_attr( (string) wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_gallery_thumbnail' ) ),
			esc_html__( 'افزودن به مقایسه', 'chidemoon-core' ),
			esc_html__( 'با حداکثر چهار محصول بسنجید', 'chidemoon-core' )
		);
	}

	public static function control( WC_Product $product ): string {
		if ( ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
			return '';
		}
		self::enqueue_assets();
		return sprintf(
			'<button type="button" class="chidemoon-compare-control" data-compare-product="%1$d" data-compare-name="%2$s" data-compare-image="%4$s" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h11M12 4l3 3-3 3M20 17H9M12 14l-3 3 3 3"/></svg><span>%3$s</span></button>',
			$product->get_id(),
			esc_attr( $product->get_name() ),
			esc_html__( 'مقایسه', 'chidemoon-core' ),
			esc_attr( (string) wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_gallery_thumbnail' ) )
		);
	}

	/** @param mixed $value @return int[] */
	public static function product_ids( $value ): array {
		$raw = is_array( $value ) ? $value : explode( ',', (string) $value );
		$ids = array();
		foreach ( $raw as $id ) {
			$id = absint( $id );
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
			if ( count( $ids ) === self::MAX_PRODUCTS ) {
				break;
			}
		}
		return $ids;
	}

	/** @return WC_Product[] */
	public static function selected_products( $value ): array {
		$products = array();
		foreach ( self::product_ids( $value ) as $id ) {
			$product = wc_get_product( $id );
			if ( $product instanceof WC_Product && Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
				$products[] = $product;
			}
		}
		return $products;
	}

	/** @return WC_Product[] */
	public static function products_from_request(): array {
		$requested = isset( $_GET[ self::QUERY_VAR ] ) ? wp_unslash( $_GET[ self::QUERY_VAR ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return self::selected_products( $requested );
	}

	/** @return array<string, string> */
	public static function facts( WC_Product $product ): array {
		$raw = $product->get_meta( Chidemoon_Core_Affiliate::META_FACTS, true );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : array();
		if ( ! is_array( $data ) ) {
			return array();
		}
		$facts = array();
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) && isset( $value['label'], $value['value'] ) ) {
				$label = sanitize_text_field( (string) $value['label'] );
				$fact  = sanitize_text_field( (string) $value['value'] );
			} elseif ( is_string( $key ) && is_scalar( $value ) ) {
				$label = sanitize_text_field( $key );
				$fact  = sanitize_text_field( (string) $value );
			} else {
				continue;
			}
			if ( '' !== $label && '' !== $fact ) {
				$facts[ $label ] = $fact;
			}
		}
		return $facts;
	}

	/** @return string[] */
	public static function fact_labels( array $products ): array {
		$labels = array();
		foreach ( $products as $product ) {
			foreach ( self::facts( $product ) as $label => $value ) {
				$labels[ $label ] = $label;
			}
		}
		return array_values( $labels );
	}

	public static function comparison_url( array $ids = array() ): string {
		$page = get_page_by_path( 'comparisons' );
		$url  = $page instanceof WP_Post ? get_permalink( $page ) : home_url( '/comparisons/' );
		if ( empty( $ids ) ) {
			return $url;
		}
		$eligible_ids = array_map( static fn( WC_Product $product ): int => $product->get_id(), self::selected_products( $ids ) );
		return empty( $eligible_ids ) ? $url : add_query_arg( self::QUERY_VAR, implode( ',', $eligible_ids ), $url );
	}

	private static function current_product(): ?WC_Product {
		global $product;
		if ( $product instanceof WC_Product ) {
			return $product;
		}
		$candidate = wc_get_product( get_the_ID() );
		return $candidate instanceof WC_Product ? $candidate : null;
	}

	private static function string_length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	/**
	 * @return true|WP_Error
	 */
	private static function throttle_public_search() {
		$ip = self::client_ip();
		$key = 'chidemoon_compare_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 36 );
		$count = (int) get_transient( $key );
		if ( $count >= 60 ) {
			return new WP_Error( 'rate_limited', __( 'Too many requests. Please try again later.', 'chidemoon-core' ), array( 'status' => 429 ) );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	private static function client_ip(): string {
		$forwarded = (string) ( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' );
		if ( '' !== $forwarded ) {
			$parts = explode( ',', $forwarded );
			$first = trim( (string) reset( $parts ) );
			if ( '' !== $first ) {
				return sanitize_text_field( $first );
			}
		}
		return sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	}

	public static function register_elementor_compare_support(): void {
		if ( did_action( 'elementor/loaded' ) ) {
			add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor_compare_widget' ) );
		} else {
			add_action( 'plugins_loaded', function (): void {
				if ( did_action( 'elementor/loaded' ) ) {
					add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor_compare_widget' ) );
				}
			} );
		}
	}

	/** @param \Elementor\Widgets_Manager $manager */
	public static function register_elementor_compare_widget( $manager ): void {
		$path = CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-elementor-compare-widget.php';
		if ( file_exists( $path ) ) {
			require_once $path;
			if ( class_exists( 'Chidemoon_Core_Elementor_Compare_Widget' ) ) {
				$manager->register( new Chidemoon_Core_Elementor_Compare_Widget() );
			}
		}
	}

	public static function register_admin_menu(): void {
		add_submenu_page(
			'chidemoon-readiness',
			__( 'مقایسه محصولات', 'chidemoon-core' ),
			__( 'مقایسه‌ها', 'chidemoon-core' ),
			'chidemoon_view_readiness',
			'chidemoon-compare',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function render_admin_page(): void {
		if ( ! current_user_can( 'chidemoon_view_readiness' ) ) {
			wp_die( esc_html__( 'You are not allowed to view Chidemoon comparisons.', 'chidemoon-core' ) );
		}
		$compare_url = self::comparison_url();
		$catalogue   = self::catalogue_products();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'مدیریت مقایسه محصولات', 'chidemoon-core' ); ?></h1>
			<p><?php esc_html_e( 'این بخش فقط محصولات منتشرشده، بررسی‌شده و قابل‌خرید (External/Affiliate) را برای مقایسه فهرست می‌کند. جدول مقایسه در فرانت‌اند از همین داده و ویژگی‌های ساختاریافته (Structured facts) استفاده می‌کند.', 'chidemoon-core' ); ?></p>
			<p>
				<strong><?php esc_html_e( 'آدرس صفحه مقایسه:', 'chidemoon-core' ); ?></strong>
				<a href="<?php echo esc_url( $compare_url ); ?>" target="_blank"><?php echo esc_html( $compare_url ); ?></a>
				— <code>[chidemoon_compare_table]</code> <?php esc_html_e( 'یا بلوک مقایسه را در آن صفحه قرار دهید.', 'chidemoon-core' ); ?>
			</p>
			<h2><?php esc_html_e( 'محصولات قابل مقایسه (حداکثر ۲۴ مورد اخیر)', 'chidemoon-core' ); ?></h2>
			<?php if ( empty( $catalogue ) ) : ?>
				<p><em><?php esc_html_e( 'هنوز محصول قابل‌مقایسه‌ای وجود ندارد. محصول را منتشر، بررسی‌شده و دارای آدرس مقصد معتبر کنید.', 'chidemoon-core' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'شناسه', 'chidemoon-core' ); ?></th><th><?php esc_html_e( 'عنوان', 'chidemoon-core' ); ?></th><th><?php esc_html_e( 'ویژگی‌ها', 'chidemoon-core' ); ?></th><th><?php esc_html_e( 'اقدام', 'chidemoon-core' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $catalogue as $product ) :
						$facts = self::facts( $product );
						?>
						<tr>
							<td><?php echo esc_html( (string) $product->get_id() ); ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $product->get_id() ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a><br><small><?php echo esc_html( wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: '— بدون تصویر' ); ?></small></td>
							<td><?php echo esc_html( empty( $facts ) ? '—' : implode( ' | ', array_map( static fn($k,$v) => $k . ': ' . $v, array_keys( $facts ), $facts ) ) ); ?></td>
							<td><a href="<?php echo esc_url( self::comparison_url( array( $product->get_id() ) ) ); ?>" target="_blank" class="button button-small"><?php esc_html_e( 'پیش‌نمایش مقایسه', 'chidemoon-core' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'پیش‌نمایش جدول مقایسه', 'chidemoon-core' ); ?></h2>
			<p class="description"><?php esc_html_e( 'شناسه محصولات را وارد کنید (۲ تا ۴ عدد با کاما) تا جدول همانند فرانت‌اند پیش‌نمایش شود.', 'chidemoon-core' ); ?></p>
			<div id="chidemoon-compare-admin-preview" style="margin:12px 0;padding:14px;border:1px solid #c3c4c7;border-radius:6px;background:#fff">
				<p>
					<label for="chidemoon-compare-admin-ids"><strong><?php esc_html_e( 'شناسه محصولات:', 'chidemoon-core' ); ?></strong></label>
					<input type="text" id="chidemoon-compare-admin-ids" placeholder="مثلاً 123,456" style="min-width:260px;margin:0 8px">
					<button type="button" class="button" id="chidemoon-compare-admin-load"><?php esc_html_e( 'نمایش جدول', 'chidemoon-core' ); ?></button>
					<a href="<?php echo esc_url( $compare_url ); ?>" target="_blank" class="button" style="margin-left:8px"><?php esc_html_e( 'رفتن به صفحه مقایسه', 'chidemoon-core' ); ?></a>
				</p>
				<div id="chidemoon-compare-admin-result"></div>
			</div>
			<script>
			(function(){
				var btn = document.getElementById('chidemoon-compare-admin-load');
				var input = document.getElementById('chidemoon-compare-admin-ids');
				var out = document.getElementById('chidemoon-compare-admin-result');
				if(!btn||!input||!out) return;
				btn.addEventListener('click', function(){
					var ids = (input.value||'').trim();
					if(!ids){ out.innerHTML = '<p style="color:#d63638">شناسه وارد کنید.</p>'; return; }
					out.innerHTML = '<p>در حال بارگذاری…</p>';
					fetch('<?php echo esc_url_raw( rest_url( 'chidemoon-core/v1/compare-products' ) ); ?>?ids=' + encodeURIComponent(ids))
						.then(function(r){ if(!r.ok) throw new Error('fetch'); return r.json(); })
						.then(function(products){
							if(!products.length){ out.innerHTML = '<p>محصولی یافت نشد یا قابل مقایسه نیست.</p>'; return; }
							// Fetch facts via second request? We already have simple preview, build minimal table.
							var html = '<p><strong>' + products.length + ' محصول:</strong> ' + products.map(function(p){ return p.title; }).join(' | ') + '</p>';
							html += '<p><a class="button button-primary" href="<?php echo esc_url( $compare_url ); ?>?products=' + encodeURIComponent(products.map(function(p){return p.id}).join(',')) + '#chidemoon-comparison-table" target="_blank">مشاهده در صفحه مقایسه</a></p>';
							out.innerHTML = html;
						})
						.catch(function(){ out.innerHTML = '<p style="color:#d63638">خطا در دریافت محصولات.</p>'; });
				});
			})();
			</script>

			<h2><?php esc_html_e( 'راهنمای ویرایش ویژگی‌های قابل مقایسه', 'chidemoon-core' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'وارد ویرایش محصول شوید → تب Chidemoon → بخش Structured product facts.', 'chidemoon-core' ); ?></li>
				<li><?php esc_html_e( 'به جای JSON خام، از ویرایشگر ردیفی جدید استفاده کنید: هر ردیف یک ویژگی (برچسب + مقدار) است.', 'chidemoon-core' ); ?></li>
				<li><?php esc_html_e( 'ذخیره کنید؛ جدول مقایسه به‌صورت خودکار این ویژگی‌ها را در ستون‌ها نمایش می‌دهد.', 'chidemoon-core' ); ?></li>
			</ol>
			<p><strong><?php esc_html_e( 'شورت‌کدها:', 'chidemoon-core' ); ?></strong> <code>[chidemoon_compare_action product_id="123"]</code> — <?php esc_html_e( 'دکمه افزودن به مقایسه', 'chidemoon-core' ); ?> · <code>[chidemoon_compare_table products="123,456"]</code> — <?php esc_html_e( 'جدول مقایسه داخل هر برگه (حتی المنتور HTML)', 'chidemoon-core' ); ?></p>
			<p class="description"><?php esc_html_e( 'ویجت مقایسه برای المنتور نیز به‌صورت خودکار ثبت می‌شود اگر المنتور فعال باشد؛ در غیر این صورت از شورت‌کد بالا در ابزارک HTML المنتور استفاده کنید.', 'chidemoon-core' ); ?></p>
		</div>
		<?php
	}

	/** @param array<string,string> $atts */
	public static function render_status_shortcode( $atts = array() ): string {
		unset( $atts );
		self::enqueue_assets();
		return '<section class="chidemoon-comparison-status" data-comparison-status hidden><p class="chidemoon-comparison-status__count" data-comparison-status-count aria-live="polite"></p><div class="chidemoon-comparison-status__chips" data-comparison-status-chips></div><a class="chidemoon-button chidemoon-comparison-status__cta" href="#chidemoon-comparison-table">' . esc_html__( 'دیدن جدول مقایسه', 'chidemoon-core' ) . '</a></section>';
	}

	/** @param array<string,string> $atts */
	public static function render_picker_shortcode( $atts = array() ): string {
		unset( $atts );
		self::enqueue_assets();
		$catalogue = self::catalogue_products();
		ob_start();
		?>
		<section class="chidemoon-comparison-picker" aria-label="<?php esc_attr_e( 'انتخاب محصولات برای مقایسه', 'chidemoon-core' ); ?>">
			<div class="chidemoon-comparison-search">
				<label for="chidemoon-comparison-search-input"><?php esc_html_e( 'جستجوی محصول بررسی‌شده', 'chidemoon-core' ); ?></label>
				<div class="chidemoon-comparison-search__field"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/></svg><input id="chidemoon-comparison-search-input" type="search" autocomplete="off" data-comparison-search-input placeholder="<?php esc_attr_e( 'حداقل دو حرف بنویسید', 'chidemoon-core' ); ?>"></div>
				<p class="chidemoon-comparison-search__hint"><?php esc_html_e( 'تا چهار محصول بررسی‌شده را انتخاب کنید.', 'chidemoon-core' ); ?></p>
				<div class="chidemoon-comparison-search__results" data-comparison-search-results hidden></div>
			</div>
			<?php if ( ! empty( $catalogue ) ) : ?>
				<div class="chidemoon-core-product-grid" data-comparison-catalogue>
					<?php foreach ( $catalogue as $product ) : ?>
						<?php echo self::product_card( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="chidemoon-compare-empty"><?php esc_html_e( 'هنوز محصول قابل مقایسه‌ای وجود ندارد.', 'chidemoon-core' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode: [chidemoon_compare_table products="1,2,3"]
	 *
	 * @param array<string,string> $atts
	 */
	public static function render_compare_table_shortcode( $atts = array() ): string {
		$atts     = shortcode_atts( array( 'products' => '', 'ids' => '' ), $atts, 'chidemoon_compare_table' );
		$raw      = $atts['products'] ?: $atts['ids'];
		$products = '' === $raw ? self::products_from_request() : self::selected_products( $raw );
		self::enqueue_assets();
		return self::render_comparison_table( $products );
	}

	/** @param WC_Product[] $products */
	public static function render_comparison_table( array $products ): string {
		if ( count( $products ) < 2 ) {
			return '<div id="chidemoon-comparison-table" class="chidemoon-comparison-table-section"><p class="chidemoon-compare-empty">' . esc_html__( 'برای نمایش جدول مقایسه حداقل دو محصول بررسی‌شده انتخاب کنید.', 'chidemoon-core' ) . '</p></div>';
		}
		self::enqueue_assets();
		$labels = self::fact_labels( $products );
		ob_start();
		?>
		<div id="chidemoon-comparison-table" class="chidemoon-comparison-table-section">
			<div class="chidemoon-comparison-table-wrap" tabindex="0" aria-label="<?php esc_attr_e( 'جدول مقایسه محصولات', 'chidemoon-core' ); ?>">
				<table class="chidemoon-comparison-table">
					<thead><tr><th scope="col"><?php esc_html_e( 'محصول', 'chidemoon-core' ); ?></th><?php foreach ( $products as $product ) : ?><th scope="col"><a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"><?php echo $product->get_image_id() ? wp_get_attachment_image( $product->get_image_id(), 'woocommerce_thumbnail', false, array( 'alt' => '' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $product->get_name() ); ?></span></a><button type="button" class="chidemoon-comparison-table__remove" data-compare-product="<?php echo esc_attr( $product->get_id() ); ?>" data-compare-name="<?php echo esc_attr( $product->get_name() ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'حذف %s از مقایسه', 'chidemoon-core' ), $product->get_name() ) ); ?>"><?php esc_html_e( 'حذف', 'chidemoon-core' ); ?></button></th><?php endforeach; ?></tr></thead>
					<tbody>
						<?php $cell_label = static function ( WC_Product $product ): void { echo ' data-label="' . esc_attr( $product->get_name() ) . '"'; }; ?>
						<tr><th scope="row"><?php esc_html_e( 'قیمت', 'chidemoon-core' ); ?></th><?php foreach ( $products as $product ) : ?><td<?php $cell_label( $product ); ?>><?php echo wp_kses_post( $product->get_price_html() ); ?></td><?php endforeach; ?></tr>
						<tr><th scope="row"><?php esc_html_e( 'فروشنده', 'chidemoon-core' ); ?></th><?php foreach ( $products as $product ) : ?><td<?php $cell_label( $product ); ?>><?php echo esc_html( (string) $product->get_meta( Chidemoon_Core_Affiliate::META_MERCHANT_NAME, true ) ?: '—' ); ?></td><?php endforeach; ?></tr>
						<tr><th scope="row"><?php esc_html_e( 'دسته‌بندی', 'chidemoon-core' ); ?></th><?php foreach ( $products as $product ) : ?><td<?php $cell_label( $product ); ?>><?php $categories = wc_get_product_category_list( $product->get_id(), '، ' ); echo esc_html( $categories ? wp_strip_all_tags( $categories ) : '—' ); ?></td><?php endforeach; ?></tr>
						<?php foreach ( $labels as $label ) : ?><tr><th scope="row"><?php echo esc_html( $label ); ?></th><?php foreach ( $products as $product ) : $facts = self::facts( $product ); ?><td<?php $cell_label( $product ); ?>><?php echo esc_html( $facts[ $label ] ?? '—' ); ?></td><?php endforeach; ?></tr><?php endforeach; ?>
						<tr><th scope="row"><?php esc_html_e( 'خرید', 'chidemoon-core' ); ?></th><?php foreach ( $products as $product ) : ?><td<?php $cell_label( $product ); ?>><a class="chidemoon-button" href="<?php echo esc_url( Chidemoon_Core_Affiliate::tracking_url( $product->get_id() ) ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php esc_html_e( 'خرید از فروشگاه', 'chidemoon-core' ); ?></a></td><?php endforeach; ?></tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function product_card( WC_Product $product ): string {
		if ( ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
			return '';
		}
		$product_id = $product->get_id();
		$title      = $product->get_name();
		$image_id   = $product->get_image_id();
		$terms      = get_the_terms( $product_id, 'product_cat' );
		$term       = is_array( $terms ) && ! empty( $terms ) && $terms[0] instanceof WP_Term ? $terms[0] : null;
		ob_start();
		?>
		<article class="chidemoon-core-product-card">
			<a class="chidemoon-core-product-card__media" href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" aria-label="<?php echo esc_attr( $title ); ?>"><?php echo $image_id ? wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'loading' => 'lazy' ) ) : '<span aria-hidden="true"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<div class="chidemoon-core-product-card__body"><?php if ( $term ) : ?><p><?php echo esc_html( $term->name ); ?></p><?php endif; ?><h3><a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>"><?php echo esc_html( $title ); ?></a></h3><div class="chidemoon-core-product-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div><?php echo self::control( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</article>
		<?php
		return (string) ob_get_clean();
	}
