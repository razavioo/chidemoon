<?php
/**
 * Counts and filters the two content types shown in public search.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Search_Facets {
	public static function register(): void {
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_query' ), 20 );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor_widget' ), 10 );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'global_search_form' ), 30, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'canonical_search' ), 0 );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'product_grid' ), 200, 2 );
	}

	public static function global_search_form( string $html, $widget ): string {
		if ( 'search' !== $widget->get_name() ) { return $html; }
		// The public search uses Core's post/product query, not a widget-bound
		// query whose template ID can become stale after native migrations.
		$tags = new WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( 'INPUT' ) ) {
			if ( 'e_search_props' === $tags->get_attribute( 'name' ) ) {
				$tags->set_attribute( 'disabled', true );
			}
		}
		return $tags->get_updated_html();
	}

	public static function canonical_search(): void {
		if ( is_admin() || wp_doing_ajax() || ! isset( $_GET['e_search_props'], $_GET['s'] ) || ! is_string( $_GET['s'] ) || '' === trim( $_GET['s'] ) ) { return; }
		// Also recover old bookmarks that Elementor has already marked 404.
		wp_safe_redirect( remove_query_arg( 'e_search_props' ), 302, 'Chidemoon Search' );
		exit;
	}

	public static function register_elementor_widget( $manager ): void {
		require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-elementor-search-facets-widget.php';
		$manager->register( new Chidemoon_Core_Elementor_Search_Facets_Widget() );
	}

	public static function selected_type(): string {
		$input = $_GET['content_type'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = is_string( $input ) ? sanitize_key( wp_unslash( $input ) ) : '';
		return 'product';
	}

	public static function filter_query( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}
		$type = self::selected_type();
		if ( $type ) {
			$query->set( 'post_type', $type );
		}
	}

	public static function render( array $settings = array(), bool $preview = false ): string {
		if(is_search()){global $wp_query;return '<div class="ch-search-summary"><span class="ch-search-summary-dot" aria-hidden="true"></span><span>'.esc_html(self::persian_digits((string)$wp_query->found_posts)).' محصول برای «'.esc_html(get_search_query(false)).'»</span></div>';}
		if ( ! is_search() && ! $preview ) {
			return '';
		}
		$term = is_search() ? get_search_query( false ) : '';
		$counts = array( '' => 0, 'product' => 0, 'post' => 0 );
		if ( is_search() && 'yes' !== ( $settings['hide_counts'] ?? '' ) ) {
			foreach ( array( 'product', 'post' ) as $type ) {
				$results = new WP_Query( array(
					'post_type' => $type,
					'post_status' => 'publish',
					's' => $term,
					'posts_per_page' => 1,
					'fields' => 'ids',
					'ignore_sticky_posts' => true,
				) );
				$counts[ $type ] = (int) $results->found_posts;
			}
		}
		$counts[''] = $counts['product'] + $counts['post'];
		$selected = is_search() ? self::selected_type() : '';
		$url = is_search() ? get_search_link( $term ) : '#';
		$html = '<nav class="ch-search-facets" aria-label="نوع نتایج جست‌وجو">';
		$defaults = array( 'all_label' => 'همه', 'product_label' => 'محصولات', 'post_label' => 'مطالب' );
		foreach ( array( '' => 'all_label', 'product' => 'product_label', 'post' => 'post_label' ) as $type => $key ) {
			$label = trim( (string) ( $settings[ $key ] ?? '' ) ) ?: $defaults[ $key ];
			$link = $type && ! $preview ? add_query_arg( 'content_type', $type, $url ) : $url;
			$current = $selected === $type ? ' aria-current="page"' : '';
			$html .= '<a href="' . esc_url( $link ) . '"' . $current . '><span>' . esc_html( $label ) . '</span>';
			if ( 'yes' !== ( $settings['hide_counts'] ?? '' ) && ! $preview ) {
				$html .= '<span class="ch-search-facets__count">' . esc_html( self::persian_digits( (string) $counts[ $type ] ) ) . '</span>';
			}
			$html .= '</a>';
		}
		return $html . '</nav>';
	}

	public static function product_grid(string $html,$widget):string {
		if(!is_search())return $html;
		$html=str_replace('جستجو در محصولات و مطالب','محصول مورد علاقه‌ات را پیدا کن',$html);
		if(!in_array($widget->get_name(),['archive-posts','posts','loop-grid'],true)||!str_contains((string)$widget->get_settings('_css_classes'),'ch-search-results-feed'))return $html;
		global $wp_query;$cards='';
		foreach($wp_query->posts as $post){$p=wc_get_product($post->ID);if(!$p||!Chidemoon_Core_Affiliate::is_publicly_eligible($p))continue;
			$url=get_permalink($p->get_id());$terms=get_the_terms($p->get_id(),'product_cat');$category=is_array($terms)?end($terms)->name:'';
			$cards.='<article class="ch-result-card"><a class="ch-result-image" href="'.esc_url($url).'" aria-label="'.esc_attr($p->get_name()).'">'.wp_get_attachment_image($p->get_image_id(),'woocommerce_thumbnail',false,['sizes'=>'(max-width: 600px) calc(50vw - 26px), (max-width: 1024px) 30vw, 300px']).'<span class="ch-result-arrow" aria-hidden="true">↖</span></a><div class="ch-result-info"><span class="ch-result-category">'.esc_html($category).'</span><h2><a href="'.esc_url($url).'">'.esc_html($p->get_name()).'</a></h2><div class="ch-result-price">'.$p->get_price_html().'</div><a class="ch-result-action" href="'.esc_url($url).'">مشاهده محصول <span aria-hidden="true">←</span></a></div></article>';
		}
		if(!$cards)return '<div class="ch-result-empty"><h2>محصولی پیدا نشد</h2><p>نام کوتاه‌تر یا عبارت دیگری را جستجو کن.</p><a href="'.esc_url(home_url('/shop/')).'">دیدن همه محصولات ←</a></div>';
		return '<div class="ch-result-grid">'.$cards.'</div><nav class="ch-result-pagination" aria-label="صفحه‌های نتایج">'.paginate_links(['total'=>$wp_query->max_num_pages,'current'=>max(1,get_query_var('paged')),'prev_text'=>'→','next_text'=>'←']).'</nav>';
	}
	private static function persian_digits( string $text ): string {
		return strtr( $text, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}
}
