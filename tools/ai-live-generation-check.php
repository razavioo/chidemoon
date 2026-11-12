<?php
/** Explicit WP-CLI live probe: billable calls, no content publication or database writes. */
$provider = Chidemoon_AI_Provider_Factory::create();
if ( is_wp_error( $provider ) ) { WP_CLI::error( $provider->get_error_code() ); }
$evidence = array( array( 'source_id' => 'fixture-room', 'source_type' => 'test', 'freshness_at' => gmdate( 'Y-m-d H:i:s' ), 'source_excerpt' => 'آزمون: اتاقی با دیوار روشن، یک میز چوبی و چراغ مطالعه. هیچ محصول واقعی یا قیمت تأیید نشده است.' ) );
$job = array( 'request_payload' => array( 'instructions' => 'یک بند کوتاه فارسی درباره فضای توصیف شده بنویس. هیچ قیمت یا برند اضافه نکن.', 'length' => 'short', 'lang' => 'fa' ) );
$input = Chidemoon_AI_Moderation::review_input( $job, $evidence );
if ( is_wp_error( $input ) ) { WP_CLI::error( 'input: ' . $input->get_error_code() ); }
$text = $provider->generate_text( $job, $evidence );
if ( is_wp_error( $text ) ) { WP_CLI::error( 'text: ' . $text->get_error_code() ); }
$validate = new ReflectionMethod( Chidemoon_AI_Runner::class, 'validate_text_result' );
$valid = $validate->invoke( null, $text, $evidence );
if ( is_wp_error( $valid ) ) { WP_CLI::error( 'text-validation: ' . $valid->get_error_code() ); }
$review = Chidemoon_AI_Moderation::review_text_output( $text );
if ( is_wp_error( $review ) ) { WP_CLI::error( 'text-review: ' . $review->get_error_code() ); }
echo "PASS: real Persian text, output validation and content review.\n";
$image_job = array( 'request_payload' => array( 'prompt' => 'Editorial photograph of a simple light room, a plain wooden desk and a desk lamp. No people, text, logos or brands.' ) );
$input = Chidemoon_AI_Moderation::review_input( $image_job );
if ( is_wp_error( $input ) ) { WP_CLI::error( 'image-input: ' . $input->get_error_code() ); }
$image = $provider->generate_image( $image_job );
if ( is_wp_error( $image ) ) { WP_CLI::error( 'image: ' . $image->get_error_code() ); }
$review = Chidemoon_AI_Moderation::review_image_output( $image );
if ( is_wp_error( $review ) ) { WP_CLI::error( 'image-review: ' . $review->get_error_code() ); }
$bytes = base64_decode( $image['b64_json'] ?? '', true );
$info = $bytes ? getimagesizefromstring( $bytes ) : false;
if ( ! $info ) { WP_CLI::error( 'Image bytes invalid.' ); }
echo 'PASS: real image, ' . $info[0] . 'x' . $info[1] . ', ' . $info['mime'] . ', output content review.' . "\n";
if ( ! empty( $persist_probe ) ) {
 $attachment = Chidemoon_AI_Media::persist_generated_image( $image, 0, get_current_user_id() );
 if ( is_wp_error( $attachment ) ) { WP_CLI::error( 'persist: ' . $attachment->get_error_code() ); }
 update_post_meta( $attachment, '_chidemoon_ai_review_state', 'review_required' );
 echo 'PASS: generated asset persisted as attachment ' . $attachment . ".\n";
 $edit_job = array( 'request_payload' => array( 'mode' => 'edit', 'prompt' => 'Change only the desk lamp colour to dark blue. Preserve the desk, room, framing and lighting. No text or logos.', 'source_attachment_ids' => array( $attachment ) ) );
 $edited = $provider->generate_image( $edit_job );
 if ( is_wp_error( $edited ) ) { WP_CLI::error( 'edit: ' . $edited->get_error_code() ); }
 $review = Chidemoon_AI_Moderation::review_image_output( $edited );
 if ( is_wp_error( $review ) ) { WP_CLI::error( 'edit-review: ' . $review->get_error_code() ); }
 $edited_id = Chidemoon_AI_Media::persist_generated_image( $edited, 0, get_current_user_id() );
 if ( is_wp_error( $edited_id ) ) { WP_CLI::error( 'edit-persist: ' . $edited_id->get_error_code() ); }
 update_post_meta( $edited_id, '_chidemoon_ai_review_state', 'review_required' );
 echo 'PASS: Sunburst edit persisted as attachment ' . $edited_id . ".\n";
 echo wp_json_encode( array( 'generated_url' => wp_get_attachment_url( $attachment ), 'edited_url' => wp_get_attachment_url( $edited_id ) ) ) . "\n";
}
