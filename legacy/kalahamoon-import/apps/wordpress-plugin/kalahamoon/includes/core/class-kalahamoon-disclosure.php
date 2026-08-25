<?php
/**
 * Automatic affiliate disclosure insertion.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Disclosure {

	public static function init(): void {
		if ( class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled() ) {
			// Editorial disclosures remain available as explicit blocks. A connector
			// must not rewrite published page content at render time.
			return;
		}

		// Opt-in automatic disclosure. Disabled by default so disclosure remains
		// the author's choice; enable with the `kalahamoon_auto_disclosure` option
		// or the `kalahamoon_auto_disclosure` filter. A standalone
		// `kalahamoon/affiliate-disclosure` block is always available regardless.
		$enabled = (bool) get_option( 'kalahamoon_auto_disclosure', false );
		/** @param bool $enabled Whether to auto-prepend the disclosure note. */
		$enabled = (bool) apply_filters( 'kalahamoon_auto_disclosure', $enabled );
		if ( $enabled ) {
			// Run after dynamic blocks render so ordinary product links do not
			// receive an affiliate disclosure before their resolved state is known.
			add_filter( 'the_content', array( __CLASS__, 'maybe_add_disclosure' ), 11 );
		}
	}

	/**
	 * Return the disclosure note markup (shared by auto-insert and the block).
	 */
	public static function render( string $text = '' ): string {
		if ( '' === $text ) {
			$text = (string) get_option( 'kalahamoon_disclosure_text', '' );
		}
		if ( '' === $text ) {
			$text = __( 'این مطلب شامل لینک‌های همکاری در فروش است. در صورت خرید از طریق این لینک‌ها، ما بدون هزینه اضافی برای شما کمیسیون دریافت می‌کنیم.', 'kalahamoon' );
		}

		return '<div class="kalahamoon-disclosure" role="note" aria-label="' . esc_attr__( 'افشای همکاری در فروش', 'kalahamoon' ) . '">'
			. '<p>' . esc_html( $text ) . '</p>'
			. '</div>';
	}

	public static function maybe_add_disclosure( string $content ): string {
		if ( is_admin() || ! is_singular( 'post' ) ) {
			return $content;
		}

		// Only add if the content has affiliate links
		if ( false === strpos( $content, 'kalahamoon-affiliate-link' )
			&& false === strpos( $content, '/go/' ) ) {
			return $content;
		}

		return self::render() . $content;
	}
}
