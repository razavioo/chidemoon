<?php
/** Read-only runtime diagnostic. Never prints credentials. */
$provider = Chidemoon_AI_Provider_Factory::create();
$out = array( 'provider_ready' => ! is_wp_error( $provider ), 'moderation_ready' => ! is_wp_error( Chidemoon_AI_Moderation::validate_configuration() ) );
if ( ! is_wp_error( $provider ) ) { $out['models'] = array( 'text' => $provider->text_model(), 'image' => $provider->image_model(), 'vision' => $provider->vision_model() ); }
echo wp_json_encode( $out, JSON_PRETTY_PRINT ) . "\n";

add_action( 'http_api_debug', static function( $response, $context, $class, $args, $url ) {
 if ( 'response' !== $context ) { return; }
 echo wp_json_encode( array( 'host' => wp_parse_url( $url, PHP_URL_HOST ), 'status' => is_wp_error( $response ) ? $response->get_error_code() : wp_remote_retrieve_response_code( $response ) ) ) . "\n";
}, 10, 5 );
$result = Chidemoon_AI_Moderation::review_input( array( 'request_payload' => array( 'prompt' => 'A plain wooden table in a room.' ) ) );
echo is_wp_error( $result ) ? $result->get_error_code() . "\n" : "moderation-passed\n";
