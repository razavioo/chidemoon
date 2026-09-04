<?php
/**
 * Chidemoon Blocksy child theme module.
 *
 * Loaded by functions.php; do not load directly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	static function (): void {
		load_child_theme_textdomain( 'chidemoon-blocksy-child', get_stylesheet_directory() . '/languages' );
		add_theme_support( 'editor-styles' );
		add_editor_style( array( 'assets/css/typography.css', 'assets/css/editor.css' ) );
	}
);
/**
 * Keep keyboard users out of the Blocksy navigation chrome when they want the
 * page content. The target is shared by every public template in this theme.
 */
function chidemoon_blocksy_render_skip_link(): void {
	?>
	<a class="chidemoon-skip-link" href="#primary"><?php esc_html_e( 'رفتن به محتوای اصلی', 'chidemoon-blocksy-child' ); ?></a>
	<?php
}
add_action( 'wp_body_open', 'chidemoon_blocksy_render_skip_link', 5 );

function chidemoon_blocksy_setup(): void {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
}
add_action( 'after_setup_theme', 'chidemoon_blocksy_setup' );
