<?php
/**
 * The small public baseline shared by Elementor templates and Core widgets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Public_Design {
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 30 );
		add_action( 'elementor/query/chidemoon_looks', array( __CLASS__, 'looks_query' ) );
		add_shortcode( 'chidemoon_room_filters', array( __CLASS__, 'room_filters' ) );
		add_filter( 'hello_elementor_skip_link_url', static fn(): string => '#chidemoon-content' );
		add_filter( 'hello_elementor_skip_link_text', static fn(): string => 'رفتن به محتوا' );
		add_filter( 'elementor/fonts/additional_fonts', array( __CLASS__, 'fonts' ) );
		add_filter( 'elementor/utils/get_the_archive_title', static function ( string $title ): string {
			return is_home() ? get_the_title( (int) get_option( 'page_for_posts' ) ) : $title;
		} );
		add_filter( 'elementor/widget/render_content', array( __CLASS__, 'featured_image' ), 10, 2 );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ) );
		add_filter( 'gettext', static function ( string $translation, string $text, string $domain ): string {
			return 'hello-elementor' === $domain && 'Skip to content' === $text && is_rtl() ? 'رفتن به محتوا' : $translation;
		}, 10, 3 );
	}

	public static function widgets( $manager ): void {
		require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-elementor-product-offer-widget.php';
		require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-elementor-room-filters-widget.php';
		$manager->register( new Chidemoon_Core_Elementor_Product_Offer_Widget() );
		$manager->register( new Chidemoon_Core_Elementor_Room_Filters_Widget() );
	}

	public static function featured_image( string $content, $widget ): string {
		if ( 'theme-post-featured-image' === $widget->get_name() && get_post_meta( get_the_ID(), '_chidemoon_native_look', true ) ) {
			return '';
		}
		return $content;
	}

	public static function fonts( array $fonts ): array {
		$fonts['Vazirmatn'] = \Elementor\Fonts::LOCAL;
		$fonts['Estedad'] = \Elementor\Fonts::LOCAL;
		return $fonts;
	}

	public static function looks_query( WP_Query $query ): void {
		$query->set( 'post_type', 'post' );
		$query->set( 'post_status', 'publish' );
		$query->set( 'tag', 'shop-the-look' );
		$room = isset( $_GET['room'] ) ? sanitize_title( wp_unslash( (string) $_GET['room'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $room && term_exists( $room, Chidemoon_Core_Shop_The_Look::TAXONOMY ) ) {
			$query->set( 'tax_query', array( array( 'taxonomy' => Chidemoon_Core_Shop_The_Look::TAXONOMY, 'field' => 'slug', 'terms' => $room ) ) );
		}
	}

	public static function room_filters( array $attributes = array() ): string {
		$terms = get_terms( array( 'taxonomy' => Chidemoon_Core_Shop_The_Look::TAXONOMY, 'hide_empty' => true ) );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return '';
		}
		$page = get_page_by_path( 'shop-the-look' );
		$url = $page ? get_permalink( $page ) : home_url( '/shop-the-look/' );
		$selected = isset( $_GET['room'] ) ? sanitize_title( wp_unslash( (string) $_GET['room'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$html = '<nav class="ch-room-filters" aria-label="فضای خانه"><a href="' . esc_url( $url ) . '"' . ( '' === $selected ? ' aria-current="page"' : '' ) . '>' . esc_html( $attributes['all_label'] ?? 'همهٔ فضاها' ) . '</a>';
		foreach ( $terms as $term ) {
			$html .= '<a href="' . esc_url( add_query_arg( 'room', $term->slug, $url ) ) . '"' . ( $selected === $term->slug ? ' aria-current="page"' : '' ) . '>' . esc_html( $term->name ) . '</a>';
		}
		return $html . '</nav>';
	}

	/** Searchable Elementor options include drafts so editors can prepare a page before review. */
	public static function product_options(): array {
		$options = array();
		foreach ( get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $post ) {
			$options[ $post->ID ] = $post->post_title . ' (#' . $post->ID . ')';
		}
		return $options;
	}

	public static function enqueue(): void {
		$path = CHIDEMOON_CORE_DIR . 'assets/css/public-design.css';
		wp_enqueue_style(
			'chidemoon-public-design',
			CHIDEMOON_CORE_URL . 'assets/css/public-design.css',
			array(),
			file_exists( $path ) ? (string) filemtime( $path ) : CHIDEMOON_CORE_VERSION
		);
	}
}
