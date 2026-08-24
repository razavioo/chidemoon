<?php
/**
 * Dependency-free contract test for the review workflow.
 *
 * Run with: php tests/state-machine-test.php
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );
require_once dirname( __DIR__ ) . '/includes/class-chidemoon-ai-state-machine.php';

/**
 * @param mixed $actual
 * @param mixed $expected
 */
function chidemoon_ai_assert_same( $expected, $actual, string $message ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message );
	}
}

chidemoon_ai_assert_same(
	true,
	Chidemoon_AI_State_Machine::can_transition( Chidemoon_AI_State_Machine::QUEUED, Chidemoon_AI_State_Machine::GENERATING ),
	'A queued job must be claimable exactly into generating.'
);
chidemoon_ai_assert_same(
	true,
	Chidemoon_AI_State_Machine::can_transition( Chidemoon_AI_State_Machine::GENERATING, Chidemoon_AI_State_Machine::REVIEW_REQUIRED ),
	'A successful generation must require a human review.'
);
chidemoon_ai_assert_same(
	false,
	Chidemoon_AI_State_Machine::can_transition( Chidemoon_AI_State_Machine::QUEUED, Chidemoon_AI_State_Machine::APPLIED ),
	'A queued job must never skip review and apply itself.'
);
chidemoon_ai_assert_same(
	false,
	Chidemoon_AI_State_Machine::can_transition( Chidemoon_AI_State_Machine::APPROVED, Chidemoon_AI_State_Machine::REVIEW_REQUIRED ),
	'An approved job cannot silently re-enter the review queue.'
);
chidemoon_ai_assert_same(
	false,
	Chidemoon_AI_State_Machine::can_transition( Chidemoon_AI_State_Machine::APPLIED, Chidemoon_AI_State_Machine::REJECTED ),
	'Applied output is terminal and cannot be rewritten by a later reviewer.'
);

echo "Chidemoon AI state-machine contracts passed.\n";
