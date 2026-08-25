<?php
/**
 * Server-rendered public navigation shell.
 *
 * @var array $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$primary       = chidemoon_navigation_items( 'primary' );
$mobile        = chidemoon_navigation_items( 'mobile' );
$compare_url   = home_url( '/compare/' );
$brand_name    = chidemoon_public_brand_name();
$search_id     = wp_unique_id( 'chidemoon-header-search-' );
$mobile_search = wp_unique_id( 'chidemoon-mobile-search-' );
$catalog_ready    = chidemoon_public_catalog_available();
$comparison_ready = chidemoon_public_comparison_available();
$icon_map      = array( 'compare' => 'compare', 'shop' => 'shop', 'magazine' => 'book', 'home' => 'home' );
?>
<header class="chidemoon-site-header">
	<div class="chidemoon-header-inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="chidemoon-header-brand-link" aria-label="<?php echo esc_attr( chidemoon_public_copy( 'brand' ) . ' ' . chidemoon_public_copy( 'home' ) ); ?>">
			<span class="chidemoon-header-logo-box" aria-hidden="true"><svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"><path d="M24 50 50 14 92 84H8Z" /></svg></span>
			<strong class="chidemoon-header-title"><?php echo esc_html( $brand_name ); ?></strong>
		</a>

		<?php if ( ! empty( $primary ) ) : ?>
		<nav class="chidemoon-header-nav-menu" aria-label="<?php echo esc_attr( chidemoon_public_copy( 'primary_navigation' ) ); ?>">
			<ul class="chidemoon-header-nav-list">
				<?php foreach ( $primary as $item ) : ?>
					<li><a class="chidemoon-nav-pill<?php echo $item['current'] ? ' current' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $item['current'] ? ' aria-current="page"' : ''; ?>><?php echo chidemoon_icon( $icon_map[ $item['slug'] ] ?? 'info', 16 ); ?><span><?php echo esc_html( $item['label'] ); ?></span></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php endif; ?>

		<div class="chidemoon-header-actions-box">
			<?php if ( $catalog_ready ) : ?>
				<div class="chidemoon-header-search-wrap">
					<form role="search" method="get" class="chidemoon-header-searchform" action="<?php echo esc_url( home_url( '/shop/' ) ); ?>">
						<label class="screen-reader-text" for="<?php echo esc_attr( $search_id ); ?>"><?php echo esc_html( chidemoon_public_copy( 'search_products' ) ); ?></label>
						<input id="<?php echo esc_attr( $search_id ); ?>" type="search" name="kc_search" maxlength="120" placeholder="<?php echo esc_attr( chidemoon_public_copy( 'search_placeholder' ) ); ?>" autocomplete="off" />
						<button type="submit" aria-label="<?php echo esc_attr( chidemoon_public_copy( 'search' ) ); ?>"><?php echo chidemoon_icon( 'search', 17 ); ?></button>
					</form>
				</div>
				<?php if ( $comparison_ready ) : ?>
					<a class="chidemoon-header-compare-link" href="<?php echo esc_url( $compare_url ); ?>" aria-label="<?php echo esc_attr( chidemoon_public_copy( 'open_compare' ) ); ?>"><?php echo chidemoon_icon( 'compare', 18 ); ?><span class="chidemoon-compare-badge" hidden>0</span></a>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<div class="chidemoon-mobile-menu-shell">
			<button class="chidemoon-mobile-menu-toggle" type="button" aria-expanded="false" aria-controls="chidemoon-mobile-drawer" aria-label="<?php echo esc_attr( chidemoon_public_copy( 'open_menu' ) ); ?>"><?php echo chidemoon_icon( 'menu', 22 ); ?></button>
			<div class="chidemoon-mobile-menu-backdrop" hidden></div>
			<aside id="chidemoon-mobile-drawer" class="chidemoon-mobile-drawer" hidden aria-modal="true" role="dialog" aria-label="<?php echo esc_attr( chidemoon_public_copy( 'mobile_navigation' ) ); ?>" tabindex="-1">
				<div class="chidemoon-mobile-drawer-heading"><strong><?php echo esc_html( $brand_name ); ?></strong><button class="chidemoon-mobile-menu-close" type="button" aria-label="<?php echo esc_attr( chidemoon_public_copy( 'close_menu' ) ); ?>"><?php echo chidemoon_icon( 'close', 22 ); ?></button></div>
				<?php if ( $catalog_ready ) : ?>
					<form role="search" method="get" class="chidemoon-mobile-searchform" action="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><label class="screen-reader-text" for="<?php echo esc_attr( $mobile_search ); ?>"><?php echo esc_html( chidemoon_public_copy( 'search_products' ) ); ?></label><input id="<?php echo esc_attr( $mobile_search ); ?>" type="search" name="kc_search" maxlength="120" placeholder="<?php echo esc_attr( chidemoon_public_copy( 'search_placeholder' ) ); ?>" /><button type="submit" aria-label="<?php echo esc_attr( chidemoon_public_copy( 'search' ) ); ?>"><?php echo chidemoon_icon( 'search', 18 ); ?></button></form>
				<?php endif; ?>
				<nav aria-label="<?php echo esc_attr( chidemoon_public_copy( 'mobile_navigation' ) ); ?>"><ul><?php foreach ( $mobile as $item ) : ?><li><a href="<?php echo esc_url( $item['url'] ); ?>"<?php echo $item['current'] ? ' aria-current="page"' : ''; ?>><?php echo chidemoon_icon( $icon_map[ $item['slug'] ] ?? 'info', 20 ); ?><span><?php echo esc_html( $item['label'] ); ?></span></a></li><?php endforeach; ?></ul></nav>
			</aside>
		</div>
	</div>
</header>
