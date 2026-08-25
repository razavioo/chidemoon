<?php
/**
 * Server-side render for kalahamoon/lead-form.
 *
 * Renders a CRM lead-capture form handled by public/js/kalahamoon-forms.js,
 * which POSTs to /wp-json/kalahamoon/v1/leads (bearer + honeypot on the panel).
 *
 * @var array $attributes
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$heading      = trim( (string) ( $attributes['heading'] ?? '' ) );
$description  = trim( (string) ( $attributes['description'] ?? '' ) );
$intent       = sanitize_key( (string) ( $attributes['intent'] ?? 'contact' ) );
$intent       = in_array( $intent, array( 'contact', 'consultation', 'issue' ), true ) ? $intent : 'contact';
$show_subject = ! isset( $attributes['showSubject'] ) || ! empty( $attributes['showSubject'] );
$show_name    = ! isset( $attributes['showName'] )    || ! empty( $attributes['showName'] );
$show_email   = ! isset( $attributes['showEmail'] )   || ! empty( $attributes['showEmail'] );
$show_phone   = ! isset( $attributes['showPhone'] )   || ! empty( $attributes['showPhone'] );
$show_message = ! isset( $attributes['showMessage'] ) || ! empty( $attributes['showMessage'] );

$button_text  = trim( (string) ( $attributes['buttonText'] ?? '' ) );
if ( '' === $button_text ) {
	$button_text = __( 'ارسال', 'kalahamoon' );
}
$success_text = trim( (string) ( $attributes['successText'] ?? '' ) );
if ( '' === $success_text ) {
	$success_text = __( 'پیام شما ارسال شد. به‌زودی با شما تماس می‌گیریم.', 'kalahamoon' );
}
$consent_text = trim( (string) ( $attributes['consentText'] ?? '' ) );
if ( '' === $consent_text ) {
	$consent_text = __( 'I agree that my contact details may be used to respond to this request.', 'kalahamoon' );
}
$consent_version = sanitize_text_field( (string) ( $attributes['consentVersion'] ?? '1' ) );
$reference_label = __( 'Request reference: %s', 'kalahamoon' );

$wrapper = get_block_wrapper_attributes( array( 'class' => 'kalahamoon-form kalahamoon-lead-form' ) );
?>
<form <?php echo $wrapper; ?>
	data-kalahamoon-form="lead"
	data-intent="<?php echo esc_attr( $intent ); ?>"
	data-consent-version="<?php echo esc_attr( $consent_version ); ?>"
	dir="<?php echo esc_attr( Kalahamoon_RTL::direction() ); ?>"
	data-success="<?php echo esc_attr( $success_text ); ?>"
	data-reference-label="<?php echo esc_attr( $reference_label ); ?>"
	data-error="<?php echo esc_attr__( 'ارسال نشد. لطفاً دوباره تلاش کنید.', 'kalahamoon' ); ?>"
	data-sending="<?php echo esc_attr__( 'در حال ارسال…', 'kalahamoon' ); ?>">

	<?php if ( '' !== $heading ) : ?>
		<h3 class="kalahamoon-form__heading"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>
	<?php if ( '' !== $description ) : ?>
		<p class="kalahamoon-form__desc"><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>

	<?php if ( $show_subject ) : ?>
		<label class="kalahamoon-form__field">
			<span><?php esc_html_e( 'Subject', 'kalahamoon' ); ?></span>
			<input type="text" name="subject" maxlength="200" />
		</label>
	<?php endif; ?>

	<?php if ( $show_name ) : ?>
		<label class="kalahamoon-form__field">
			<span><?php esc_html_e( 'نام', 'kalahamoon' ); ?></span>
			<input type="text" name="name" autocomplete="name" />
		</label>
	<?php endif; ?>

	<?php if ( $show_email ) : ?>
		<label class="kalahamoon-form__field">
			<span><?php esc_html_e( 'ایمیل', 'kalahamoon' ); ?></span>
			<input type="email" name="email" autocomplete="email" dir="ltr" />
		</label>
	<?php endif; ?>

	<?php if ( $show_phone ) : ?>
		<label class="kalahamoon-form__field">
			<span><?php esc_html_e( 'شماره تماس', 'kalahamoon' ); ?></span>
			<input type="tel" name="phoneNumber" autocomplete="tel" dir="ltr" />
		</label>
	<?php endif; ?>

	<?php if ( $show_message ) : ?>
		<label class="kalahamoon-form__field">
			<span><?php esc_html_e( 'پیام', 'kalahamoon' ); ?></span>
			<textarea name="message" rows="3"></textarea>
		</label>
	<?php endif; ?>

	<?php // Honeypot — hidden from humans, tempting to bots. ?>
	<div class="kalahamoon-form__hp" aria-hidden="true" style="position:absolute;inline-size:1px;block-size:1px;overflow:hidden;clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap">
		<label><?php esc_html_e( 'وب‌سایت', 'kalahamoon' ); ?><input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
	</div>

	<label class="kalahamoon-form__consent">
		<input type="checkbox" name="consent" value="1" required />
		<span><?php echo esc_html( $consent_text ); ?></span>
	</label>

	<button type="submit" class="kalahamoon-form__submit"><?php echo esc_html( $button_text ); ?></button>
	<div class="kalahamoon-form__status" role="status" aria-live="polite"></div>
</form>
