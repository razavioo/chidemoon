<?php
/**
 * Centralized fallback renderers for edge-case UI: missing images,
 * unavailable prices, missing products, and empty grids.
 *
 * Every block routes through these helpers so the visual language stays
 * consistent and edge cases never produce empty divs or raw comments.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Placeholder {

	/**
	 * Render a product image with a graceful SVG fallback when the source
	 * URL is missing. Uses the marketplace platform to pick an accent tint
	 * so the placeholder still signals brand identity.
	 *
	 * @param array  $product    Product data (may have imageUrl or not).
	 * @param string $extra_class Extra class names to add to wrapper.
	 * @param bool   $show_fav   Whether to emit the favorite toggle.
	 */
	public static function image( array $product, string $extra_class = '', bool $show_fav = true ): string {
		$image_url = (string) ( $product['imageUrl'] ?? '' );
		$title     = (string) ( $product['title'] ?? '' );
		$platform  = strtolower( (string) ( $product['platform'] ?? '' ) );
		$pid       = (string) ( $product['id'] ?? '' );

		$wrapper_class = trim( 'kalahamoon-product-image ' . $extra_class );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
			<?php if ( '' !== $image_url ) : ?>
				<img src="<?php echo esc_url( $image_url ); ?>"
					alt="<?php echo esc_attr( $title ); ?>"
					loading="lazy" decoding="async"
					onerror="this.hidden=true;this.nextElementSibling.hidden=false" />
				<div class="kalahamoon-image-placeholder" aria-hidden="true" hidden>
					<?php echo self::image_fallback_svg( $platform, $title ); ?>
				</div>
			<?php else : ?>
				<?php echo self::image_fallback_svg( $platform, $title ); ?>
			<?php endif; ?>

			<?php if ( $show_fav && '' !== $pid ) : ?>
				<button class="kalahamoon-favorite-btn"
					type="button"
					data-product-id="<?php echo esc_attr( $pid ); ?>"
					aria-label="<?php esc_attr_e( 'افزودن به علاقه‌مندی‌ها', 'kalahamoon' ); ?>">♡</button>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Inline SVG placeholder with a platform-tinted background and a
	 * first-character monogram of the product title. Self-contained — no
	 * external assets, CSP-safe.
	 */
	public static function image_fallback_svg( string $platform, string $title ): string {
		$tints = array(
			'bakalahamoon'     => '#ff6b35',
			'digikala'    => '#ef394e',
			'torob'       => '#00b4d8',
			'shopify'     => '#95bf47',
			'woocommerce' => '#7f54b3',
		);
		$tint = $tints[ $platform ] ?? 'currentColor';

		// Monogram from title: first grapheme (works with Persian/Arabic too).
		$mono = '';
		if ( '' !== $title ) {
			if ( function_exists( 'mb_substr' ) ) {
				$mono = mb_substr( $title, 0, 1, 'UTF-8' );
			} else {
				$mono = substr( $title, 0, 1 );
			}
		}
		if ( '' === $mono ) {
			$mono = '•';
		}

		$aria = sprintf(
			/* translators: %s: product title */
			esc_html__( 'تصویر در دسترس نیست: %s', 'kalahamoon' ),
			$title !== '' ? $title : __( 'محصول', 'kalahamoon' )
		);

		ob_start();
		?>
		<svg class="kalahamoon-image-placeholder"
			viewBox="0 0 200 200"
			xmlns="http://www.w3.org/2000/svg"
			role="img"
			aria-label="<?php echo esc_attr( $aria ); ?>"
			preserveAspectRatio="xMidYMid slice">
			<defs>
				<linearGradient id="kalahamoon-ph-bg-<?php echo esc_attr( md5( $title . $platform ) ); ?>" x1="0" y1="0" x2="1" y2="1">
					<stop offset="0" stop-color="<?php echo esc_attr( $tint ); ?>" stop-opacity=".18" />
					<stop offset="1" stop-color="<?php echo esc_attr( $tint ); ?>" stop-opacity=".05" />
				</linearGradient>
			</defs>
			<rect width="200" height="200" fill="url(#kalahamoon-ph-bg-<?php echo esc_attr( md5( $title . $platform ) ); ?>)" />
			<circle cx="100" cy="100" r="52" fill="<?php echo esc_attr( $tint ); ?>" fill-opacity=".22" />
			<text x="100" y="100"
				font-family="YekanBakh, Vazirmatn, system-ui, sans-serif"
				font-size="54" font-weight="700"
				text-anchor="middle"
				dominant-baseline="central"
				fill="<?php echo esc_attr( $tint ); ?>">
				<?php echo esc_html( $mono ); ?>
			</text>
		</svg>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Format a price cell that degrades gracefully to "contact for details"
	 * when the cached price is zero or negative.
	 */
	public static function price( array $product, bool $show_old = true ): string {
		$price    = (float) ( $product['price']    ?? 0 );
		$old      = (float) ( $product['oldPrice'] ?? 0 );
		$currency = (string) ( $product['currency'] ?? 'IRR' );

		if ( $price <= 0 ) {
			return '<div class="kalahamoon-product-price kalahamoon-price-unavailable">'
				. '<span class="kalahamoon-price-unavailable-label">'
				. esc_html__( 'جزئیات قیمت در صفحه فروشنده', 'kalahamoon' )
				. '</span></div>';
		}

		ob_start();
		?>
		<div class="kalahamoon-product-price">
			<?php if ( $show_old && $old > $price ) :
				$discount = (int) round( ( 1 - $price / $old ) * 100 );
			?>
				<span class="kalahamoon-old-price">
					<del><?php echo esc_html( Kalahamoon_RTL::format_price( $old, $currency ) ); ?></del>
				</span>
				<?php if ( $discount >= 1 ) : ?>
					<span class="kalahamoon-discount-badge"
						aria-label="<?php echo esc_attr( sprintf(
							/* translators: %s: discount percent */
							__( '%s درصد تخفیف', 'kalahamoon' ),
							Kalahamoon_RTL::format_number( $discount )
						) ); ?>">
						<?php echo esc_html( Kalahamoon_RTL::format_number( $discount ) ); ?>%
					</span>
				<?php endif; ?>
			<?php endif; ?>
			<span class="kalahamoon-current-price">
				<?php echo esc_html( Kalahamoon_RTL::format_price( $price, $currency ) ); ?>
			</span>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Show an editor-only warning when a block references a product that
	 * no longer exists in the local cache. Logged-out visitors see nothing.
	 *
	 * @param string $product_id The kalahamoon product id that failed to resolve.
	 */
	public static function product_not_found( string $product_id ): string {
		if ( ! self::current_user_is_editor() ) {
			return '';
		}

		ob_start();
		?>
		<div class="kalahamoon-editor-hint kalahamoon-editor-hint--warn" role="note">
			<strong><?php esc_html_e( 'محصول پیدا نشد', 'kalahamoon' ); ?></strong>
			<span><?php
				echo esc_html( sprintf(
					/* translators: %s: product id */
					__( 'شناسه %s در cache محلی موجود نیست. لطفاً همگام‌سازی محصولات را اجرا کنید یا محصول دیگری انتخاب کنید.', 'kalahamoon' ),
					$product_id
				) );
			?></span>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Generic empty-state for grids/carousels/tables with zero items.
	 */
	public static function empty_state( string $title, string $hint = '', string $icon = 'grid' ): string {
		ob_start();
		?>
		<div class="kalahamoon-empty-state" role="status">
			<?php echo self::empty_state_icon( $icon ); ?>
			<p class="kalahamoon-empty-state-title"><?php echo esc_html( $title ); ?></p>
			<?php if ( '' !== $hint ) : ?>
				<p class="kalahamoon-empty-state-hint"><?php echo esc_html( $hint ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Editor-only note shown when a block's inputs don't meet the minimum
	 * shape (e.g., fewer than 2 products for a comparison).
	 */
	public static function editor_hint( string $title, string $body = '' ): string {
		if ( ! self::current_user_is_editor() ) {
			return '';
		}

		ob_start();
		?>
		<div class="kalahamoon-editor-hint" role="note">
			<strong><?php echo esc_html( $title ); ?></strong>
			<?php if ( '' !== $body ) : ?>
				<span><?php echo esc_html( $body ); ?></span>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function empty_state_icon( string $icon ): string {
		$paths = array(
			'grid'  => '<path d="M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm11 0h7v7h-7v-7z"/>',
			'list'  => '<path d="M3 5h18v2H3V5zm0 6h18v2H3v-2zm0 6h18v2H3v-2z"/>',
			'image' => '<path d="M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zm1 2v7.382l3.72-3.718 4.28 4.28 2-2L19 17V7H5zm11 3a2 2 0 110-4 2 2 0 010 4z"/>',
			'tag'   => '<path d="M12.586 2.586A2 2 0 0014 2H20a2 2 0 012 2v6a2 2 0 01-.586 1.414L11.414 21.414a2 2 0 01-2.828 0L2.586 15.414a2 2 0 010-2.828L12.586 2.586zM17 7a2 2 0 100-4 2 2 0 000 4z"/>',
		);
		$path = $paths[ $icon ] ?? $paths['grid'];

		return '<svg class="kalahamoon-empty-state-icon" viewBox="0 0 24 24" width="40" height="40" fill="currentColor" aria-hidden="true">' . $path . '</svg>';
	}

	private static function current_user_is_editor(): bool {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		return current_user_can( 'edit_posts' );
	}
}
