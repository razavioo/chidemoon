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
		return in_array( $type, array( 'post', 'product' ), true ) ? $type : '';
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

	private static function persian_digits( string $text ): string {
		return strtr( $text, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}
}
