<?php
/** Server render for the Chidemoon Shop the Look block. Delegates to shared renderer for Elementor/shortcode parity. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'Chidemoon_Core_Shop_The_Look' ) && method_exists( 'Chidemoon_Core_Shop_The_Look', 'render_look' ) ) {
	echo Chidemoon_Core_Shop_The_Look::render_look( is_array( $attributes ) ? $attributes : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

// Fallback if class not loaded (should not happen).
$image_id = absint( $attributes['imageId'] ?? 0 );
if ( $image_id <= 0 ) {
	return;
}
$caption = sanitize_text_field( (string) ( $attributes['caption'] ?? '' ) );
echo '<figure class="chidemoon-shop-the-look"><div class="chidemoon-shop-the-look__canvas">' . wp_get_attachment_image( $image_id, 'full', false, array( 'class' => 'chidemoon-shop-the-look__image', 'alt' => sanitize_text_field( (string) ( $attributes['imageAlt'] ?? '' ) ), 'loading' => 'lazy' ) ) . '</div>' . ( $caption ? '<figcaption>' . esc_html( $caption ) . '</figcaption>' : '' ) . '</figure>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
