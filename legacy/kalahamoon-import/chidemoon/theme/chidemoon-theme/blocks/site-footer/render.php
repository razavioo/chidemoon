<?php
/**
 * Server-rendered footer and mobile navigation.
 *
 * @var array $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$footer       = chidemoon_navigation_items( 'footer', false );
$mobile       = chidemoon_navigation_items( 'mobile' );
$icon_map     = array( 'home' => 'home', 'shop' => 'shop', 'magazine' => 'book', 'compare' => 'compare' );
$brand_name   = chidemoon_public_brand_name();
$mobile_count = max( 1, count( $mobile ) );
?>
<footer class="chidemoon-site-footer">
	<div class="chidemoon-footer-inner">
		<div class="chidemoon-footer-row">
			<p class="chidemoon-footer-brand">&copy; <?php echo esc_html( gmdate( 'Y' ) . ' ' . $brand_name ); ?></p>
			<?php if ( ! empty( $footer ) ) : ?>
			<nav class="chidemoon-footer-navigation" aria-label="<?php echo esc_attr( $brand_name ); ?>">
				<ul><?php foreach ( $footer as $item ) : ?><li><a href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $item['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $item['label'] ); ?></a></li><?php endforeach; ?></ul>
			</nav>
			<?php endif; ?>
		</div>
	</div>
</footer>

<nav class="chidemoon-mobile-bottom-nav" aria-label="<?php echo esc_attr( chidemoon_public_copy( 'mobile_shortcuts' ) ); ?>" style="--chidemoon-mobile-nav-items: <?php echo esc_attr( (string) $mobile_count ); ?>">
	<?php foreach ( $mobile as $item ) : ?>
	<a href="<?php echo esc_url( $item['url'] ); ?>" class="chidemoon-mobile-nav-item<?php echo $item['current'] ? ' active' : ''; ?>" id="chidemoon-mob-nav-<?php echo esc_attr( $item['slug'] ); ?>"<?php echo $item['current'] ? ' aria-current="page"' : ''; ?>><?php echo chidemoon_icon( $icon_map[ $item['slug'] ] ?? 'info', 22 ); ?><span><?php echo esc_html( $item['label'] ); ?></span><?php if ( 'compare' === $item['slug'] ) : ?><span class="chidemoon-compare-badge" hidden>0</span><?php endif; ?></a>
	<?php endforeach; ?>
</nav>
