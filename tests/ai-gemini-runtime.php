<?php
/** WP CLI eval-file; transport is intercepted, with no billable API calls or DB writes. */
$checks = 0;
$check = static function ( bool $ok, string $message ) use ( &$checks ): void {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$checks;
};
$captured = array();
$reply = array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'inlineData' => array( 'mimeType' => 'image/png', 'data' => 'aW1hZ2U=' ) ) ) ) ) ), 'usageMetadata' => array( 'promptTokenCount' => 12, 'candidatesTokenCount' => 8 ) );
$status = 200;
$intercept = static function ( $pre, $args, $url ) use ( &$captured, &$reply, &$status ) {
	$captured = array( 'url' => $url, 'body' => json_decode( $args['body'], true ), 'redirects' => $args['redirection'] ?? null );
	return array( 'headers' => array(), 'body' => wp_json_encode( $reply ), 'response' => array( 'code' => $status, 'message' => 'Fixture' ) );
};
add_filter( 'pre_http_request', $intercept, 10, 3 );
try {
	$provider = new Chidemoon_AI_OpenAI_Compatible_Provider( 'https://ai.irkodalg.shop/antigravity/v1', 'fixture-key', 'gemini-3-flash', 'gemini-3.1-flash-image-preview', 60 );
	$result = $provider->generate_image( array( 'request_payload' => array( 'prompt' => 'A vase' ) ) );
	$check( ! is_wp_error( $result ) && 'aW1hZ2U=' === $result['b64_json'] && 12 === $result['_usage']['prompt_tokens'], 'Native image/usage not decoded.' );
	$check( str_ends_with( $captured['url'], '/antigravity/v1beta/models/gemini-3.1-flash-image-preview:generateContent' ) && 0 === $captured['redirects'], 'Wrong native route or unsafe redirect.' );
	$check( array( 'TEXT', 'IMAGE' ) === $captured['body']['generationConfig']['responseModalities'] && ! isset( $captured['body']['response_format'] ), 'OpenAI image options leaked into Gemini.' );
	$reply['candidates'][0]['content']['parts'] = array( array( 'text' => 'No image generated.' ) );
	$check( is_wp_error( $provider->generate_image( array() ) ), 'Text-only image output accepted.' );
	$status = 429;
	$check( is_wp_error( $provider->generate_image( array() ) ), 'Rate limit accepted.' );
	$status = 200;
	$reply = array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => '{"flagged":false}' ) ) ) ) ) );
	$decision = Chidemoon_AI_Moderation::review_input( array( 'request_payload' => array( 'prompt' => 'A calm interior.' ) ) );
	$check( is_wp_error( $decision ), 'Incomplete safety decision accepted.' );
	$categories = array_fill_keys( array( 'sexual', 'sexual/minors', 'hate', 'harassment', 'self-harm', 'violence', 'illicit' ), false );
	$categories['violence'] = true;
	$reply['candidates'][0]['content']['parts'][0]['text'] = wp_json_encode( array( 'flagged' => false, 'categories' => $categories ) );
	$blocked = Chidemoon_AI_Moderation::review_input( array( 'request_payload' => array( 'prompt' => 'Fixture only.' ) ) );
	$check( is_wp_error( $blocked ) && 'chidemoon_ai_moderation_blocked' === $blocked->get_error_code(), 'Positive category ignored.' );
	$check( is_wp_error( Chidemoon_AI_Gemini::image_part( 'https://example.com/private.jpg' ) ), 'Remote source accepted.' );
	$openai = new Chidemoon_AI_OpenAI_Compatible_Provider( 'https://api.openai.com/v1', 'fixture-key', 'gpt-4.1-mini', 'gpt-image-2', 60 );
	$reply = array( 'data' => array( array( 'b64_json' => 'aW1hZ2U=' ) ) );
	$legacy = $openai->generate_image( array() );
	$check( ! is_wp_error( $legacy ) && str_ends_with( $captured['url'], '/images/generations' ), 'Existing OpenAI image adapter regressed.' );
} finally {
	remove_filter( 'pre_http_request', $intercept, 10 );
}
echo "PASS: {$checks} Gemini transport and failure checks.\n";
