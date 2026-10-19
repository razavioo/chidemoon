<?php
/** A useful, deliberately designed destination when a catalogue has no matches. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Catalog_Empty {
	public static function register(): void {
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'widget_content' ), 120, 2 );
		add_filter( 'wc_get_template', array( __CLASS__, 'template' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', static function (): void {
			$path = CHIDEMOON_CORE_DIR . 'assets/css/catalog-empty.css';
			wp_enqueue_style( 'chidemoon-catalog-empty', CHIDEMOON_CORE_URL . 'assets/css/catalog-empty.css', array( 'chidemoon-public-design' ), (string) filemtime( $path ) );
		}, 31 );
	}

	public static function widget_content( string $content, $widget ): string {
		if ( is_admin() || ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) { return $content; }
		if ( ! in_array( $widget->get_name(), array( 'loop-grid', 'wc-archive-products' ), true ) ) { return $content; }
		// Inspect the rendered grid: never replace populated results or related widgets.
		if ( str_contains( $content, 'e-loop-item' ) || preg_match( '/class=["\'][^"\']*\bproduct\s/', $content ) ) { return $content; }
		global $wp_query;
		return $wp_query instanceof WP_Query && 0 === (int) $wp_query->found_posts ? self::render() : $content;
	}

	public static function template( string $located, string $name ): string {
		return 'loop/no-products-found.php' === $name ? CHIDEMOON_CORE_DIR . 'templates/catalog-empty.php' : $located;
	}

	public static function render(): string {
		$filtered = false;
		$keys = array( 's', 'ch_min_price', 'ch_max_price', 'ch_product_cat', 'ch_availability', 'filter_brand', 'min_price', 'max_price' );
		foreach ( array_keys( Chidemoon_Core_Enrichment::TAXONOMIES ) as $field ) {
			$keys[] = 'ch_' . $field;
			$keys[] = 'ch_' . $field . '_group';
		}
		foreach ( $keys as $key ) {
			if ( ! empty( $_GET[ $key ] ) || ( isset( $_GET[ $key ] ) && '0' === $_GET[ $key ] ) ) { $filtered = true; }
		}
		$title = $filtered ? 'این بار، چیزی پیدا نشد' : 'جای انتخاب بعدی تو اینجاست';
		$description = $filtered ? 'با کمی تغییر در فیلترها یا یک جستجوی تازه، به انتخاب دلخواهت نزدیک‌تر شو.' : 'هنوز محصولی در این بخش نداریم. تا آن زمان، برای خانه‌ات ایده بگیر و با خیال راحت انتخابت را بشناس.';
		ob_start();
		?>
		<section class="ch-catalog-empty" aria-label="محصولی پیدا نشد" dir="rtl">
			<div class="ch-catalog-empty__hero">
				<div class="ch-catalog-empty__art" aria-hidden="true">
					<svg viewBox="0 0 320 260" fill="none" xmlns="http://www.w3.org/2000/svg">
						<circle cx="160" cy="126" r="103" fill="#E8EEE4"/>
						<path d="M50 222h222M81 212V91l78-57 79 57v121" stroke="#76917B" stroke-width="2" stroke-linecap="round"/>
						<path d="M95 101h43v47H95z" fill="#FAFCF7" stroke="#A8B9A2" stroke-width="2"/><path d="M116 102v45M96 125h41" stroke="#A8B9A2"/>
						<path d="M104 180a12 12 0 0 1 12-12h57a12 12 0 0 1 12 12v23h-81z" fill="#DBB99A"/><rect x="96" y="182" width="97" height="27" rx="9" fill="#C49A78"/><path d="M106 210v11m77-11v11" stroke="#7B6958" stroke-width="3"/>
						<path d="M214 212v-77m-18 0h36l-9-28h-18z" stroke="#617D63" stroke-width="2" fill="#F9F5E8"/><path d="M202 213h24" stroke="#617D63" stroke-width="3" stroke-linecap="round"/>
						<path d="M62 203v-35m0 18c-20 0-21-21-21-21 19-1 21 21 21 21zm0-7c17-1 19-24 19-24-18 3-19 24-19 24z" stroke="#76917B" stroke-width="2" fill="#B2C4A7"/><path d="m51 201 3 20h17l3-20z" fill="#D7C4AE"/>
						<circle cx="235" cy="65" r="25" fill="#FFFEFA" stroke="#D3DDCC"/><path d="M225 65h20m-10-10v20" stroke="#76917B" stroke-width="2" stroke-linecap="round"/>
					</svg>
				</div>
				<div class="ch-catalog-empty__copy">
					<span class="ch-catalog-empty__eyebrow">برای خانه‌ای به سلیقهٔ تو</span>
					<h2><?php echo esc_html( $title ); ?></h2>
					<p><?php echo esc_html( $description ); ?></p>
					<a class="ch-catalog-empty__reset" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php echo $filtered ? 'پاک کردن فیلترها و دیدن همهٔ محصولات' : 'دیدن همهٔ محصولات'; ?><span aria-hidden="true">←</span></a>
				</div>
			</div>
			<div class="ch-catalog-empty__discover">
				<p>یک مسیر تازه برای کشف خانه‌ات</p>
				<div class="ch-catalog-empty__links">
					<a href="<?php echo esc_url( home_url( '/shop-the-look/' ) ); ?>"><span class="ch-catalog-empty__number">۰۱</span><span><strong>از یک چیدمان شروع کن</strong><small>ایده‌هایی برای کنار هم نشستن جزئیات</small></span><span aria-hidden="true">↖</span></a>
					<a href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><span class="ch-catalog-empty__number">۰۲</span><span><strong>انتخابت را بهتر بشناس</strong><small>راهنماهای خرید برای نیازهای خانه</small></span><span aria-hidden="true">↖</span></a>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
