<?php
/**
 * Installation and non-destructive upgrades.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Activator {
	public static function activate(): void {
		self::create_tables();
		Chidemoon_AI_Capabilities::add();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( Chidemoon_AI_Runner::HOOK );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade(): void {
		// Capability grants are idempotent, so they run on every load. This
		// heals roles on sites that already carry the current DB version.
		Chidemoon_AI_Capabilities::add();

		if ( CHIDEMOON_AI_VERSION === get_option( 'chidemoon_ai_db_version' ) ) {
			return;
		}

		self::create_tables();
	}

	private static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$jobs = "CREATE TABLE {$wpdb->prefix}chidemoon_ai_jobs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_key char(36) NOT NULL,
			idempotency_key varchar(191) NOT NULL,
			job_type varchar(32) NOT NULL,
			state varchar(32) NOT NULL DEFAULT 'draft',
			target_post_id bigint(20) unsigned DEFAULT NULL,
			requested_by bigint(20) unsigned NOT NULL,
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			request_hash char(64) NOT NULL,
			request_payload longtext DEFAULT NULL,
			result_payload longtext DEFAULT NULL,
			provenance longtext DEFAULT NULL,
			error_code varchar(96) DEFAULT NULL,
			error_message text DEFAULT NULL,
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY job_key (job_key),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY state_created (state, created_at),
			KEY job_type_state (job_type, state),
			KEY target_post_id (target_post_id),
			KEY requested_by (requested_by)
		) $charset;";
		dbDelta( $jobs );

		$evidence = "CREATE TABLE {$wpdb->prefix}chidemoon_ai_evidence (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			source_type varchar(32) NOT NULL,
			source_id varchar(191) NOT NULL,
			source_url text DEFAULT NULL,
			source_excerpt longtext DEFAULT NULL,
			content_hash char(64) NOT NULL,
			freshness_at datetime NOT NULL,
			created_at datetime NOT NULL,
			reviewed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY job_id (job_id),
			KEY freshness_at (freshness_at)
		) $charset;";
		dbDelta( $evidence );

		$usage = "CREATE TABLE {$wpdb->prefix}chidemoon_ai_usage (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned DEFAULT NULL,
			user_id bigint(20) unsigned NOT NULL,
			operation varchar(32) NOT NULL,
			provider varchar(64) DEFAULT NULL,
			model varchar(128) DEFAULT NULL,
			request_state varchar(32) NOT NULL,
			estimated_cost decimal(12,4) NOT NULL DEFAULT 0,
			input_units int(11) unsigned DEFAULT NULL,
			output_units int(11) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY job_id (job_id),
			KEY user_date (user_id, created_at),
			KEY state_date (request_state, created_at)
		) $charset;";
		dbDelta( $usage );

		update_option( 'chidemoon_ai_db_version', CHIDEMOON_AI_VERSION, false );
	}
}
