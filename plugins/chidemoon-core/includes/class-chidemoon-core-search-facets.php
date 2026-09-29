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

	public static function render(): string {
		if ( ! is_search() ) {
			return '';
		}
		$term = get_search_query( false );
		$counts = array();
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
		$counts[''] = $counts['product'] + $counts['post'];
		$selected = self::selected_type();
		$url = get_search_link( $term );
		$html = '<nav class="ch-search-facets" aria-label="نوع نتایج جست‌وجو">';
		foreach ( array( '' => 'همه', 'product' => 'محصولات', 'post' => 'مطالب' ) as $type => $label ) {
			$link = $type ? add_query_arg( 'content_type', $type, $url ) : $url;
			$current = $selected === $type ? ' aria-current="page"' : '';
			$html .= '<a href="' . esc_url( $link ) . '"' . $current . '><span>' . esc_html( $label ) . '</span><span class="ch-search-facets__count">' . esc_html( self::persian_digits( (string) $counts[ $type ] ) ) . '</span></a>';
		}
		return $html . '</nav>';
	}

	private static function persian_digits( string $text ): string {
		return strtr( $text, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}
}
