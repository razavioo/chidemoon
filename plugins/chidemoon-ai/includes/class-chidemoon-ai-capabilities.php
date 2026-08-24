<?php
/**
 * Capability registration for the AI review boundary.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Capabilities {
	public const GENERATE   = 'chidemoon_ai_generate';
	public const REVIEW     = 'chidemoon_ai_review';
	public const MANAGE     = 'chidemoon_ai_manage';
	public const VIEW_AUDIT = 'chidemoon_ai_view_audit';

	/**
	 * Editors can prepare and review editorial suggestions. Provider and budget
	 * policy remain administrator-only because they affect the site's spend.
	 */
	public static function add(): void {
		$editor = get_role( 'editor' );
		if ( $editor ) {
			$editor->add_cap( self::GENERATE );
			$editor->add_cap( self::REVIEW );
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( self::GENERATE );
			$administrator->add_cap( self::REVIEW );
			$administrator->add_cap( self::MANAGE );
			$administrator->add_cap( self::VIEW_AUDIT );
		}
	}
}
