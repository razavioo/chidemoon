<?php
/**
 * Useful, localized recovery for a missing route.
 *
 * @var array $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$catalog_ready = chidemoon_public_catalog_available();
$guides_ready  = chidemoon_public_editorial_available( 'guide' );
?>
<section class="chidemoon-recovery" aria-labelledby="chidemoon-recovery-title">
	<p class="chidemoon-recovery__code" aria-hidden="true">404</p>
	<p class="chidemoon-kicker"><?php esc_html_e( 'The requested route is unavailable', 'chidemoon-theme' ); ?></p>
	<h1 id="chidemoon-recovery-title"><?php esc_html_e( 'This page could not be found.', 'chidemoon-theme' ); ?></h1>
	<p><?php esc_html_e( 'The address may have changed. Search articles or return to one of the primary discovery routes.', 'chidemoon-theme' ); ?></p>
	<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>"><label class="screen-reader-text" for="chidemoon-recovery-search"><?php esc_html_e( 'Search articles', 'chidemoon-theme' ); ?></label><input id="chidemoon-recovery-search" type="search" name="s" maxlength="120" placeholder="<?php esc_attr_e( 'Search the magazine...', 'chidemoon-theme' ); ?>" /><button type="submit"><?php esc_html_e( 'Search', 'chidemoon-theme' ); ?></button></form>
	<div class="chidemoon-recovery__links"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'chidemoon-theme' ); ?></a><?php if ( $catalog_ready ) : ?><a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Browse verified products', 'chidemoon-theme' ); ?></a><?php endif; ?><?php if ( $guides_ready ) : ?><a href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'Read buying guides', 'chidemoon-theme' ); ?></a><?php endif; ?></div>
</section>
