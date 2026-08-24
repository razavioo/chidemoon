<?php
/**
 * Explicit state transitions make retries and editorial approval auditable.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_State_Machine {
	public const DRAFT           = 'draft';
	public const QUEUED          = 'queued';
	public const GENERATING      = 'generating';
	public const REVIEW_REQUIRED = 'review_required';
	public const APPROVED        = 'approved';
	public const APPLIED         = 'applied';
	public const REJECTED        = 'rejected';
	public const FAILED          = 'failed';

	/**
	 * @return array<string, array<int, string>>
	 */
	public static function transitions(): array {
		return array(
			self::DRAFT           => array( self::QUEUED, self::REJECTED ),
			self::QUEUED          => array( self::GENERATING, self::FAILED, self::REJECTED ),
			self::GENERATING      => array( self::REVIEW_REQUIRED, self::FAILED ),
			self::REVIEW_REQUIRED => array( self::APPROVED, self::REJECTED ),
			self::APPROVED        => array( self::APPLIED, self::REJECTED ),
			self::APPLIED         => array(),
			self::REJECTED        => array(),
			self::FAILED          => array(),
		);
	}

	public static function can_transition( string $from, string $to ): bool {
		return in_array( $to, self::transitions()[ $from ] ?? array(), true );
	}

	/**
	 * @return array<int, string>
	 */
	public static function terminal_states(): array {
		return array( self::APPLIED, self::REJECTED, self::FAILED );
	}
}
