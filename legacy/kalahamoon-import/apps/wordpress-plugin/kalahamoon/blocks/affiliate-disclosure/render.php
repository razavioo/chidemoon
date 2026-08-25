<?php
/**
 * Server-side render for kalahamoon/affiliate-disclosure.
 *
 * @var array $attributes
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$text    = trim( (string) ( $attributes['text'] ?? '' ) );
$wrapper = get_block_wrapper_attributes();

echo '<div ' . $wrapper . ' dir="' . esc_attr( Kalahamoon_RTL::direction() ) . '">'
	. Kalahamoon_Disclosure::render( $text )
	. '</div>';
