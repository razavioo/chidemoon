<?php
/**
 * Single product tabs.
 *
 * Chidemoon hides the reviews tab upstream (functions.php), and when only one
 * tab remains its panel renders as plain content: the tab list adds nothing,
 * and the wrapper class WooCommerce uses to bootstrap its tab JS is avoided
 * so the lone panel is never hidden waiting for a tab click.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @version 9.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filter tabs and allow third parties to add their own.
 *
 * Each tab is an array containing title, callback and priority.
 *
 * @see woocommerce_default_product_tabs()
 */
$product_tabs = apply_filters( 'woocommerce_product_tabs', array() );

if ( empty( $product_tabs ) ) {
	return;
}

if ( 1 === count( $product_tabs ) ) :
	$lone_key   = (string) array_key_first( $product_tabs );
	$lone_tab   = $product_tabs[ $lone_key ];
	?>
	<div class="chidemoon-product-info">
		<div class="woocommerce-Tabs-panel woocommerce-Tabs-panel--<?php echo esc_attr( $lone_key ); ?> entry-content is-layout-constrained" id="tab-<?php echo esc_attr( $lone_key ); ?>">
			<?php
			if ( isset( $lone_tab['callback'] ) ) {
				call_user_func( $lone_tab['callback'], $lone_key, $lone_tab );
			}
			?>
		</div>

		<?php do_action( 'woocommerce_product_after_tabs' ); ?>
	</div>
	<?php
	return;
endif;
?>

<div class="woocommerce-tabs wc-tabs-wrapper">
	<ul class="tabs wc-tabs" role="tablist">
		<?php foreach ( $product_tabs as $key => $product_tab ) : ?>
			<li role="presentation" class="<?php echo esc_attr( $key ); ?>_tab" id="tab-title-<?php echo esc_attr( $key ); ?>">
				<a href="#tab-<?php echo esc_attr( $key ); ?>" role="tab" aria-controls="tab-<?php echo esc_attr( $key ); ?>">
					<?php echo wp_kses_post( apply_filters( 'woocommerce_product_' . $key . '_tab_title', $product_tab['title'], $key ) ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<article>
		<?php foreach ( $product_tabs as $key => $product_tab ) : ?>
			<?php
			$classes = array(
				'woocommerce-Tabs-panel',
				'woocommerce-Tabs-panel--' . esc_attr( $key ),
				'panel',
				'entry-content',
				'wc-tab',
				'is-layout-constrained',
			);

			if (
				( 'description' === $key || 'additional_information' === $key )
				&& function_exists( 'blocksy_get_theme_mod' )
				&& (
					'type-4' === blocksy_get_theme_mod( 'woo_tabs_type', 'type-1' )
					|| 'yes' === blocksy_get_theme_mod( 'woo_has_product_tabs_description', 'no' )
				)
			) {
				$classes[] = 'ct-has-heading';
			}
			?>

			<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="tab-<?php echo esc_attr( $key ); ?>" role="tabpanel" aria-labelledby="tab-title-<?php echo esc_attr( $key ); ?>">
				<?php
				if ( isset( $product_tab['callback'] ) ) {
					call_user_func( $product_tab['callback'], $key, $product_tab );
				}
				?>
			</div>
		<?php endforeach; ?>
	</article>

	<?php do_action( 'woocommerce_product_after_tabs' ); ?>
</div>
