<?php
/**
 * Native editor patterns for the portable Chidemoon editorial toolkit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
final class Chidemoon_Core_Blocks {
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_patterns' ) );
	}

	public static function register_patterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) || ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		register_block_pattern_category(
			'chidemoon-commerce',
			array( 'label' => __( 'Chidemoon commerce', 'chidemoon-core' ) )
		);
		register_block_pattern_category(
			'chidemoon-editorial',
			array( 'label' => __( 'Chidemoon editorial', 'chidemoon-core' ) )
		);

		foreach ( self::patterns() as $name => $pattern ) {
			register_block_pattern( $name, $pattern );
		}
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private static function patterns(): array {
		return array(
			'chidemoon-core/affiliate-disclosure' => array(
				'title'      => __( 'Affiliate disclosure', 'chidemoon-core' ),
				'categories' => array( 'chidemoon-commerce' ),
				'content'    => '<!-- wp:shortcode -->[chidemoon_affiliate_disclosure]<!-- /wp:shortcode -->',
			),
			'chidemoon-core/affiliate-cta' => array(
				'title'      => __( 'Affiliate CTA', 'chidemoon-core' ),
				'categories' => array( 'chidemoon-commerce' ),
				'content'    => '<!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
			),
			'chidemoon-core/product-grid' => array(
				'title'      => __( 'Product grid', 'chidemoon-core' ),
				'categories' => array( 'chidemoon-commerce' ),
				'content'    => '<!-- wp:heading {"level":2} --><h2>' . esc_html__( 'Featured products', 'chidemoon-core' ) . '</h2><!-- /wp:heading --><!-- wp:shortcode -->[products limit="8" columns="4" visibility="featured"]<!-- /wp:shortcode -->',
			),
			'chidemoon-core/product-card' => array(
				'title'      => __( 'Product recommendation card', 'chidemoon-core' ),
				'categories' => array( 'chidemoon-commerce' ),
				'content'    => '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:post-featured-image /--><!-- wp:post-title {"level":3,"isLink":true} /--><!-- wp:post-excerpt /--><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode --></div><!-- /wp:group -->',
			),
			'chidemoon-core/faq' => array(
				'title'      => __( 'FAQ', 'chidemoon-core' ),
				'categories' => array( 'chidemoon-editorial' ),
				'content'    => '<!-- wp:heading {"level":2} --><h2>' . esc_html__( 'Frequently asked questions', 'chidemoon-core' ) . '</h2><!-- /wp:heading --><!-- wp:details --><details class="wp-block-details"><summary>' . esc_html__( 'Question', 'chidemoon-core' ) . '</summary><!-- wp:paragraph --><p>' . esc_html__( 'Write a reviewed answer with a source where appropriate.', 'chidemoon-core' ) . '</p><!-- /wp:paragraph --></details><!-- /wp:details -->',
			),
			'chidemoon-core/pros-cons' => array(
				'title'      => __( 'Pros and cons', 'chidemoon-core' ),
				'categories' => array( 'chidemoon-editorial' ),
				'content'    => '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3>' . esc_html__( 'Pros', 'chidemoon-core' ) . '</h3><!-- /wp:heading --><!-- wp:list --><ul><li>' . esc_html__( 'Reviewed advantage', 'chidemoon-core' ) . '</li></ul><!-- /wp:list --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:heading {"level":3} --><h3>' . esc_html__( 'Considerations', 'chidemoon-core' ) . '</h3><!-- /wp:heading --><!-- wp:list --><ul><li>' . esc_html__( 'Reviewed limitation', 'chidemoon-core' ) . '</li></ul><!-- /wp:list --></div><!-- /wp:column --></div><!-- /wp:columns -->',
			),
			'chidemoon-core/rating' => array(
				'title'      => __( 'Rating summary', 'chidemoon-core' ),
				'categories' => array( 'chidemoon-editorial' ),
				'content'    => '<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"}} --><div class="wp-block-group"><!-- wp:heading {"level":3} --><h3>' . esc_html__( 'Chidemoon rating', 'chidemoon-core' ) . '</h3><!-- /wp:heading --><!-- wp:paragraph --><p>' . esc_html__( 'Explain the rating criteria and evidence.', 'chidemoon-core' ) . '</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
			),
			'chidemoon-core/comparison' => array(
				'title'      => __( 'Product comparison', 'chidemoon-core' ),
				'categories' => array( 'chidemoon-commerce', 'chidemoon-editorial' ),
				'content'    => '<!-- wp:heading {"level":2} --><h2>' . esc_html__( 'Compare products', 'chidemoon-core' ) . '</h2><!-- /wp:heading --><!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>' . esc_html__( 'Criterion', 'chidemoon-core' ) . '</th><th>' . esc_html__( 'Product A', 'chidemoon-core' ) . '</th><th>' . esc_html__( 'Product B', 'chidemoon-core' ) . '</th></tr></thead><tbody><tr><td>' . esc_html__( 'Reviewed fact', 'chidemoon-core' ) . '</td><td></td><td></td></tr></tbody></table></figure><!-- /wp:table -->',
			),
			'chidemoon-core/shop-the-look' => array(
				'title'      => __( 'Shop the look', 'chidemoon-core' ),
				'categories' => array( 'chidemoon-commerce', 'chidemoon-editorial' ),
				'content'    => '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:heading {"level":2} --><h2>' . esc_html__( 'Shop the look', 'chidemoon-core' ) . '</h2><!-- /wp:heading --><!-- wp:paragraph --><p>' . esc_html__( 'Introduce the space and explain the editorial choice.', 'chidemoon-core' ) . '</p><!-- /wp:paragraph --><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode --></div><!-- /wp:group -->',
			),
			'chidemoon-core/testimonials' => array(
				'title'      => __( 'Testimonial', 'chidemoon-core' ),
				'categories' => array( 'chidemoon-editorial' ),
				'content'    => '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>' . esc_html__( 'Use a genuine, permissioned testimonial.', 'chidemoon-core' ) . '</p><!-- /wp:paragraph --><cite>' . esc_html__( 'Attribution', 'chidemoon-core' ) . '</cite></blockquote><!-- /wp:quote -->',
			),
			'chidemoon-core/editorial-layout' => array(
				'title'      => __( 'Editorial article layout', 'chidemoon-core' ),
				'categories' => array( 'chidemoon-editorial' ),
				'content'    => '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:post-title {"level":1} /--><!-- wp:post-excerpt /--><!-- wp:post-featured-image /--><!-- wp:post-content /--><!-- wp:shortcode -->[chidemoon_affiliate_disclosure]<!-- /wp:shortcode --></div><!-- /wp:group -->',
			),
		);
	}
}
