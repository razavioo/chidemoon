<?php
/**
 * Server-side render for kalahamoon/comparison-table block.
 *
 * Wrapped with get_block_wrapper_attributes() so block supports (color,
 * spacing, border) and block styles (is-style-striped/bordered/minimal)
 * land on the outer element. Inner rendering delegates to [kalahamoon_compare].
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$wrapper_attrs = get_block_wrapper_attributes( array(
	'class' => 'kalahamoon-comparison-table-block',
) );

$inner = do_shortcode( sprintf(
	'[kalahamoon_compare ids="%s" specs="%s"]',
	esc_attr( $attributes['productIds'] ?? '' ),
	esc_attr( $attributes['specs'] ?? '' )
) );

echo '<div ' . $wrapper_attrs . '>' . $inner . '</div>';
