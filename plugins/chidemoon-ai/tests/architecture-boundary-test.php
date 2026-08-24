<?php
/**
 * Static guardrails for the standalone and review-only boundaries.
 *
 * Run with: php tests/architecture-boundary-test.php
 */

declare( strict_types=1 );

$root  = dirname( __DIR__ );
$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes', FilesystemIterator::SKIP_DOTS ) );
$source = '';
foreach ( $files as $file ) {
	if ( 'php' === $file->getExtension() ) {
		$source .= file_get_contents( $file->getPathname() ) . "\n";
	}
}

function chidemoon_ai_architecture_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

chidemoon_ai_architecture_assert(
	! preg_match( '/kalahamoon/i', $source ),
	'The standalone AI plugin must not depend on the former platform.'
);
chidemoon_ai_architecture_assert(
	! preg_match( '/(?:add|update)_option\s*\([^\n]*(?:api[_-]?key|provider[_-]?url)/i', $source ),
	'Provider credentials and endpoint configuration must never be persisted in WordPress options.'
);
chidemoon_ai_architecture_assert(
	false !== strpos( $source, "'strict' => true" ),
	'Text generation must use strict structured output.'
);
chidemoon_ai_architecture_assert(
	false !== strpos( $source, 'Chidemoon_AI_State_Machine::REVIEW_REQUIRED' ),
	'Generated output must pass through the review-required state.'
);
chidemoon_ai_architecture_assert(
	false !== strpos( $source, "'post_status'  => 'draft'" ),
	'Applying generated text must save only a draft.'
);
chidemoon_ai_architecture_assert(
	false !== strpos( $source, 'CHIDEMOON_AI_MODERATION_MODEL' ),
	'Every generation must have an environment-configured moderation gate.'
);
chidemoon_ai_architecture_assert(
	false !== strpos( $source, "CHIDEMOON_AI_IMAGE_MODEL', 'gpt-image-2'" ),
	'The default image model must remain GPT Image 2 while allowing an environment override.'
);
chidemoon_ai_architecture_assert(
	false !== strpos( $source, 'rights_attestation' ),
	'Image jobs must preserve a rights attestation in their provenance.'
);

$widget_source = file_get_contents( $root . '/includes/class-chidemoon-ai-assistant-widget.php' );
chidemoon_ai_architecture_assert(
	false !== strpos( $widget_source, "add_shortcode( 'chidemoon_ai_assistant'" ),
	'The published-content assistant needs a usable shortcode shell.'
);

echo "Chidemoon AI architecture contracts passed.\n";
