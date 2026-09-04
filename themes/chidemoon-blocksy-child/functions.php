<?php
/**
 * Chidemoon Blocksy child theme bootstrap.
 *
 * Presentation modules live in includes/theme-*.php so product, affiliate,
 * and editorial rules stay portable in the Chidemoon Core plugin.
 * functions.php only loads them in dependency order.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require get_stylesheet_directory() . '/includes/category-art.php';
require get_stylesheet_directory() . '/includes/theme-setup.php';
require get_stylesheet_directory() . '/includes/theme-enqueue.php';
require get_stylesheet_directory() . '/includes/theme-navigation.php';
require get_stylesheet_directory() . '/includes/theme-formatting.php';
require get_stylesheet_directory() . '/includes/theme-i18n.php';
require get_stylesheet_directory() . '/includes/theme-rendering.php';
