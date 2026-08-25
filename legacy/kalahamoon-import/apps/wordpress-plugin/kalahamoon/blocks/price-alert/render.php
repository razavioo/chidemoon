<?php
/**
 * Server-side render for kalahamoon/price-alert.
 *
 * Email subscribe form handled by public/js/kalahamoon-forms.js, which POSTs
 * to /wp-json/kalahamoon/v1/price-alerts.
 *
 * @var array $attributes
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$product_id = trim( (string) ( $attributes['productId'] ?? '' ) );
if ( '' === $product_id ) {
	echo Kalahamoon_Placeholder::editor_hint(
		__( 'محصولی انتخاب نشده', 'kalahamoon' ),
		__( 'یک محصول انتخاب کنید تا کاربران بتوانند برای کاهش قیمت آن مشترک شوند.', 'kalahamoon' )
	);
	return;
}

$product = Kalahamoon_Product_Cache::get_for_public_render( $product_id );
if ( ! $product ) {
	echo Kalahamoon_Placeholder::product_not_found( $product_id );
	return;
}

$heading = trim( (string) ( $attributes['heading'] ?? '' ) );
if ( '' === $heading ) {
	$heading = __( 'از کاهش قیمت باخبر شوید', 'kalahamoon' );
}
$button_text = trim( (string) ( $attributes['buttonText'] ?? '' ) );
if ( '' === $button_text ) {
	$button_text = __( 'اطلاع بده', 'kalahamoon' );
}
$success_text = trim( (string) ( $attributes['successText'] ?? '' ) );
if ( '' === $success_text ) {
	$success_text = __( 'Check your email to confirm this price alert.', 'kalahamoon' );
}

$wrapper = get_block_wrapper_attributes( array( 'class' => 'kalahamoon-form kalahamoon-price-alert' ) );
?>
<form <?php echo $wrapper; ?>
	data-kalahamoon-form="price-alert"
	data-consent-version="1"
	dir="<?php echo esc_attr( Kalahamoon_RTL::direction() ); ?>"
	data-success="<?php echo esc_attr( $success_text ); ?>"
	data-error="<?php echo esc_attr__( 'ثبت نشد. لطفاً دوباره تلاش کنید.', 'kalahamoon' ); ?>"
	data-sending="<?php echo esc_attr__( 'در حال ثبت…', 'kalahamoon' ); ?>">

	<h3 class="kalahamoon-form__heading"><?php echo esc_html( $heading ); ?></h3>
	<input type="hidden" name="productId" value="<?php echo esc_attr( $product['id'] ); ?>" />

	<label class="kalahamoon-form__field">
		<span><?php esc_html_e( 'ایمیل', 'kalahamoon' ); ?></span>
		<input type="email" name="email" autocomplete="email" dir="ltr" required />
	</label>

	<div class="kalahamoon-form__hp" aria-hidden="true" style="position:absolute;inline-size:1px;block-size:1px;overflow:hidden;clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap">
		<label><?php esc_html_e( 'وب‌سایت', 'kalahamoon' ); ?><input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
	</div>

	<label class="kalahamoon-form__consent">
		<input type="checkbox" name="consent" value="1" required />
		<span><?php esc_html_e( 'I agree to receive email about this product price. I can unsubscribe at any time.', 'kalahamoon' ); ?></span>
	</label>

	<button type="submit" class="kalahamoon-form__submit"><?php echo esc_html( $button_text ); ?></button>
	<div class="kalahamoon-form__status" role="status" aria-live="polite"></div>
</form>
