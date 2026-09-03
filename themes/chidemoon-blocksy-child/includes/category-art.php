<?php
/**
 * Category identity art for Chidemoon.
 *
 * Each public product category gets a hand-drawn editorial illustration in
 * the shared visual language: forest line work, soft sage/clay fills, and a
 * consistent 96×96 grid. Colors never live in the markup; every shape reads
 * its color from the `.catart-*` classes backed by semantic design tokens in
 * editorial-refresh.css, so the art always follows the theme palette.
 *
 * Unknown or future categories fall back to the neutral home mark, so new
 * terms stay presentable without code changes.
 *
 * @package chidemoon-blocksy-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The category art library, keyed by product_cat slug.
 *
 * Every SVG is static, self-contained markup with aria-hidden set: it is
 * decorative identity art, and the adjacent text always carries the meaning.
 *
 * @return array<string, string>
 */
function chidemoon_category_art_definitions(): array {
	static $art = null;

	if ( is_array( $art ) ) {
		return $art;
	}

	$art = array(
		'living-room' => '<svg xmlns="http://www.w3.org/2000/svg" class="chidemoon-cat-art" viewBox="0 0 96 96" width="96" height="96" aria-hidden="true" focusable="false"><ellipse class="catart-fill-sage" cx="48" cy="74" rx="33" ry="4" stroke="none"/><path class="catart-fill-cream catart-ink" d="M27 62 V40 c0-5 4-9 9-9 h24 c5 0 9 4 9 9 v22" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><rect class="catart-fill-cream catart-ink" x="13" y="42" width="14" height="23" rx="7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><rect class="catart-fill-cream catart-ink" x="69" y="42" width="14" height="23" rx="7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><rect class="catart-fill-sage" x="30.5" y="45" width="16.5" height="12" rx="3.5" stroke="none"/><rect class="catart-fill-sage" x="49" y="45" width="16.5" height="12" rx="3.5" stroke="none"/><rect class="catart-fill-accent catart-accent" x="51" y="42.5" width="12.5" height="12.5" rx="3.5" transform="rotate(-10 57.25 48.75)" stroke-width="2.5" stroke-linejoin="round"/><path class="catart-ink" d="M22 65 v6 M74 65 v6" fill="none" stroke-width="3" stroke-linecap="round"/></svg>',
		'lighting'    => '<svg xmlns="http://www.w3.org/2000/svg" class="chidemoon-cat-art" viewBox="0 0 96 96" width="96" height="96" aria-hidden="true" focusable="false"><rect class="catart-fill-ink" x="43" y="14" width="10" height="4.5" rx="2.25" stroke="none"/><path class="catart-ink" d="M48 18.5 V33" fill="none" stroke-width="2.5" stroke-linecap="round"/><path class="catart-fill-warm catart-ink" d="M31 54 a17 17 0 0 1 34 0 z" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><circle class="catart-fill-accent" cx="48" cy="59" r="3.5" stroke="none"/><path class="catart-accent" d="M36.5 63.5 l-3.5 4.5 M48 66.5 v5.5 M59.5 63.5 l3.5 4.5" fill="none" stroke-width="2.5" stroke-linecap="round"/></svg>',
		'textiles'    => '<svg xmlns="http://www.w3.org/2000/svg" class="chidemoon-cat-art" viewBox="0 0 96 96" width="96" height="96" aria-hidden="true" focusable="false"><path class="catart-ink" d="M20 22 H76" fill="none" stroke-width="3" stroke-linecap="round"/><circle class="catart-fill-ink" cx="19" cy="22" r="2.4" stroke="none"/><circle class="catart-fill-ink" cx="77" cy="22" r="2.4" stroke="none"/><path class="catart-fill-warm catart-ink" d="M27 24 V61 c0 3.6 3 6.5 6.6 6.5 h28.8 c3.6 0 6.6 -2.9 6.6 -6.5 V24" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path class="catart-ink" d="M38 27.5 c-1.3 11 -1.3 22 0 32.5 M48 27.5 c-1.3 11.5 -1.3 23 0 34 M58 27.5 c-1.3 11 -1.3 22 0 32.5" fill="none" stroke-width="2.2" stroke-linecap="round"/><ellipse class="catart-fill-sage" cx="48" cy="76" rx="27" ry="3.8" stroke="none"/></svg>',
		'decor'       => '<svg xmlns="http://www.w3.org/2000/svg" class="chidemoon-cat-art" viewBox="0 0 96 96" width="96" height="96" aria-hidden="true" focusable="false"><ellipse class="catart-fill-sage" cx="48" cy="77" rx="26" ry="4" stroke="none"/><path class="catart-ink" d="M45 44 C45 36 41 31 35 27.5" fill="none" stroke-width="2.5" stroke-linecap="round"/><path class="catart-ink" d="M51 44 C51 34 56 29 62 25" fill="none" stroke-width="2.5" stroke-linecap="round"/><path class="catart-ink" d="M48 44 V31.5" fill="none" stroke-width="2.5" stroke-linecap="round"/><ellipse class="catart-fill-sage catart-ink" cx="32.5" cy="25" rx="4.6" ry="2.6" transform="rotate(-38 32.5 25)" stroke-width="2"/><ellipse class="catart-fill-sage catart-ink" cx="64.5" cy="22.5" rx="4.6" ry="2.6" transform="rotate(30 64.5 22.5)" stroke-width="2"/><circle class="catart-fill-accent" cx="48" cy="28" r="2.6" stroke="none"/><path class="catart-fill-cream catart-ink" d="M39.5 46 c-2 6.5 -7.5 10.5 -7.5 17 a16 16 0 0 0 32 0 c0 -6.5 -5.5 -10.5 -7.5 -17 z" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><ellipse class="catart-fill-cream catart-ink" cx="48" cy="46" rx="8.5" ry="2.4" stroke-width="2.4"/></svg>',
		'default'     => '<svg xmlns="http://www.w3.org/2000/svg" class="chidemoon-cat-art" viewBox="0 0 96 96" width="96" height="96" aria-hidden="true" focusable="false"><ellipse class="catart-fill-sage" cx="48" cy="77" rx="30" ry="4" stroke="none"/><path class="catart-ink" d="M26 73 V46 a22 22 0 0 1 44 0 V73" fill="none" stroke-width="3" stroke-linecap="round"/><rect class="catart-fill-cream catart-ink" x="38" y="47" width="20" height="15" rx="2.5" stroke-width="2.5" stroke-linejoin="round"/><circle class="catart-fill-accent" cx="48" cy="54.5" r="2.6" stroke="none"/></svg>',
	);

	return $art;
}

/**
 * Resolve the art key for a term: its slug when a drawing exists, otherwise
 * the neutral home mark.
 *
 * @param WP_Term|null $term Category or other taxonomy term.
 */
function chidemoon_category_art_key( ?WP_Term $term ): string {
	$slug = $term instanceof WP_Term ? (string) $term->slug : '';
	$art  = chidemoon_category_art_definitions();

	if ( '' !== $slug && isset( $art[ $slug ] ) ) {
		return $slug;
	}

	return 'default';
}

/**
 * Inline SVG identity art for a term. Safe static markup defined in code;
 * echo without escaping, keeping the phpcs output ignore in place.
 *
 * @param WP_Term|null $term Category or other taxonomy term.
 */
function chidemoon_category_art( ?WP_Term $term ): string {
	$art = chidemoon_category_art_definitions();

	return $art[ chidemoon_category_art_key( $term ) ];
}
