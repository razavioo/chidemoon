<?php
/**
 * Asynchronous processor that never applies generated output automatically.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Runner {
	public const HOOK  = 'chidemoon_ai_run_job';
	private const GROUP = 'chidemoon-ai';

	public static function register(): void {
		add_action( self::HOOK, array( __CLASS__, 'run' ), 10, 1 );
	}

	public static function enqueue( int $job_id ): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array( $job_id ), self::GROUP );
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK, array( $job_id ) ) ) {
			wp_schedule_single_event( time() + 5, self::HOOK, array( $job_id ) );
		}
	}

	/**
	 * @return array{driver: string, next_run: int|false}
	 */
	public static function scheduler_health(): array {
		return array(
			'driver'   => function_exists( 'as_enqueue_async_action' ) ? 'action-scheduler' : 'wp-cron-fallback',
			'next_run' => wp_next_scheduled( self::HOOK ),
		);
	}

	public static function run( $job_id ): void {
		$job_id = absint( is_array( $job_id ) ? ( $job_id['job_id'] ?? 0 ) : $job_id );
		if ( ! $job_id ) {
			return;
		}

		$job = Chidemoon_AI_Repository::claim( $job_id );
		if ( ! $job ) {
			return;
		}

		$provider = Chidemoon_AI_Provider_Factory::create();
		if ( is_wp_error( $provider ) ) {
			self::fail( $job, $provider );
			return;
		}

		if ( 'text' === $job['job_type'] || 'comparison' === $job['job_type'] ) {
			self::run_text_job( $job, $provider );
			return;
		}

		if ( 'image' === $job['job_type'] ) {
			self::run_image_job( $job, $provider );
			return;
		}

		self::fail( $job, new WP_Error( 'chidemoon_ai_job_type_invalid', __( 'The AI job type is unsupported.', 'chidemoon-ai' ) ) );
	}

	/**
	 * Applies only a reviewer-approved result to a draft. This code path never
	 * publishes a post or changes a product's affiliate fields/featured image.
	 *
	 * @return int|WP_Error The affected draft post or media attachment ID.
	 */
	public static function apply( array $job, int $actor_id ): int|WP_Error {
		if ( Chidemoon_AI_State_Machine::APPROVED !== (string) $job['state'] ) {
			return new WP_Error( 'chidemoon_ai_not_approved', __( 'Only approved AI output can be applied.', 'chidemoon-ai' ), array( 'status' => 409 ) );
		}

		if ( 'image' === $job['job_type'] ) {
			return self::apply_image( $job );
		}

		if ( ! in_array( $job['job_type'], array( 'text', 'comparison' ), true ) ) {
			return new WP_Error( 'chidemoon_ai_apply_invalid', __( 'This AI job cannot be applied.', 'chidemoon-ai' ), array( 'status' => 400 ) );
		}

		$result = is_array( $job['result_payload'] ?? null ) ? $job['result_payload'] : array();
		$draft  = self::safe_draft_target( $job, $actor_id );
		if ( is_wp_error( $draft ) ) {
			return $draft;
		}

		$post_data = array(
			'ID'           => $draft->ID,
			'post_title'   => sanitize_text_field( (string) ( $result['title'] ?? $draft->post_title ) ),
			'post_content' => wp_kses_post( (string) ( $result['content'] ?? '' ) ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $result['excerpt'] ?? '' ) ),
			'post_status'  => 'draft',
		);
		$updated = wp_update_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $updated ) ) {
			return new WP_Error( 'chidemoon_ai_apply_failed', __( 'The approved AI result could not be saved to a draft.', 'chidemoon-ai' ) );
		}

		update_post_meta( $draft->ID, '_chidemoon_ai_last_job_id', (int) $job['id'] );
		update_post_meta( $draft->ID, '_chidemoon_ai_provenance', wp_json_encode( $job['provenance'] ?? array(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		if ( ! Chidemoon_AI_Repository::mark_applied( (int) $job['id'], $draft->ID ) ) {
			return new WP_Error( 'chidemoon_ai_apply_state_conflict', __( 'The AI job changed while its draft was being saved.', 'chidemoon-ai' ), array( 'status' => 409 ) );
		}

		return $draft->ID;
	}

	/**
	 * @param array<string, mixed> $job
	 */
	private static function run_text_job( array $job, Chidemoon_AI_Provider_Interface $provider ): void {
		$evidence = Chidemoon_AI_Evidence::for_job( (int) $job['id'] );
		if ( empty( $evidence ) ) {
			self::fail( $job, new WP_Error( 'chidemoon_ai_evidence_empty', __( 'The AI job has no approved local evidence.', 'chidemoon-ai' ) ) );
			return;
		}
		$input_moderation = Chidemoon_AI_Moderation::review_input( $job, $evidence );
		if ( is_wp_error( $input_moderation ) ) {
			self::record_moderation_error( $job, $input_moderation );
			self::fail( $job, $input_moderation, $provider );
			return;
		}
		Chidemoon_AI_Repository::record_moderation( (int) $job['id'], $input_moderation );

		$result = $provider->generate_text( $job, $evidence );
		if ( is_wp_error( $result ) ) {
			self::fail( $job, $result, $provider );
			return;
		}

		$validated = self::validate_text_result( $result, $evidence );
		if ( is_wp_error( $validated ) ) {
			self::fail( $job, $validated, $provider );
			return;
		}
		$output_moderation = Chidemoon_AI_Moderation::review_text_output( $validated );
		if ( is_wp_error( $output_moderation ) ) {
			self::record_moderation_error( $job, $output_moderation );
			self::fail( $job, $output_moderation, $provider );
			return;
		}
		Chidemoon_AI_Repository::record_moderation( (int) $job['id'], $output_moderation );

		$usage      = is_array( $result['_usage'] ?? null ) ? $result['_usage'] : array();
		$provenance = self::provenance( $job, $provider, Chidemoon_AI_Evidence::source_ids( $evidence ) );
		if ( ! Chidemoon_AI_Repository::mark_review_required( (int) $job['id'], $validated, $provenance ) ) {
			self::fail( $job, new WP_Error( 'chidemoon_ai_state_conflict', __( 'The AI job changed before review could begin.', 'chidemoon-ai' ) ), $provider );
			return;
		}

		Chidemoon_AI_Usage::complete(
			(int) $job['id'],
			Chidemoon_AI_State_Machine::REVIEW_REQUIRED,
			$provider->name(),
			$provider->text_model(),
			isset( $usage['prompt_tokens'] ) ? absint( $usage['prompt_tokens'] ) : null,
			isset( $usage['completion_tokens'] ) ? absint( $usage['completion_tokens'] ) : null
		);
	}

	/**
	 * @param array<string, mixed> $job
	 */
	private static function run_image_job( array $job, Chidemoon_AI_Provider_Interface $provider ): void {
		$input_moderation = Chidemoon_AI_Moderation::review_input( $job );
		if ( is_wp_error( $input_moderation ) ) {
			self::record_moderation_error( $job, $input_moderation );
			self::fail( $job, $input_moderation, $provider );
			return;
		}
		Chidemoon_AI_Repository::record_moderation( (int) $job['id'], $input_moderation );

		$result = $provider->generate_image( $job );
		if ( is_wp_error( $result ) ) {
			self::fail( $job, $result, $provider );
			return;
		}
		$output_moderation = Chidemoon_AI_Moderation::review_image_output( $result );
		if ( is_wp_error( $output_moderation ) ) {
			self::record_moderation_error( $job, $output_moderation );
			self::fail( $job, $output_moderation, $provider );
			return;
		}
		Chidemoon_AI_Repository::record_moderation( (int) $job['id'], $output_moderation );

		$attachment_id = Chidemoon_AI_Media::persist_generated_image( $result, (int) $job['id'], (int) $job['requested_by'] );
		if ( is_wp_error( $attachment_id ) ) {
			self::fail( $job, $attachment_id, $provider );
			return;
		}

		$usage      = is_array( $result['_usage'] ?? null ) ? $result['_usage'] : array();
		$payload    = is_array( $job['request_payload'] ?? null ) ? $job['request_payload'] : array();
		$provenance = self::provenance( $job, $provider, array_map( 'strval', $payload['source_attachment_ids'] ?? array() ) );
		$image      = array(
			'attachment_id'  => $attachment_id,
			'revised_prompt' => sanitize_text_field( (string) ( $result['revised_prompt'] ?? '' ) ),
		);
		if ( ! Chidemoon_AI_Repository::mark_review_required( (int) $job['id'], $image, $provenance ) ) {
			self::fail( $job, new WP_Error( 'chidemoon_ai_state_conflict', __( 'The AI image job changed before review could begin.', 'chidemoon-ai' ) ), $provider );
			return;
		}

		Chidemoon_AI_Usage::complete(
			(int) $job['id'],
			Chidemoon_AI_State_Machine::REVIEW_REQUIRED,
			$provider->name(),
			$provider->image_model(),
			isset( $usage['prompt_tokens'] ) ? absint( $usage['prompt_tokens'] ) : null,
			isset( $usage['completion_tokens'] ) ? absint( $usage['completion_tokens'] ) : null
		);
	}

	/**
	 * @param array<string, mixed> $result
	 * @param array<int, array<string, mixed>> $evidence
	 * @return array<string, mixed>|WP_Error
	 */
	private static function validate_text_result( array $result, array $evidence ): array|WP_Error {
		$title   = sanitize_text_field( (string) ( $result['title'] ?? '' ) );
		$content = wp_kses_post( (string) ( $result['content'] ?? '' ) );
		$excerpt = sanitize_textarea_field( (string) ( $result['excerpt'] ?? '' ) );
		$facts   = is_array( $result['facts_needing_review'] ?? null ) ? $result['facts_needing_review'] : null;
		$citations = is_array( $result['citation_source_ids'] ?? null ) ? $result['citation_source_ids'] : null;
		if ( '' === $title || '' === trim( wp_strip_all_tags( $content ) ) || ! is_array( $facts ) || ! is_array( $citations ) ) {
			return new WP_Error( 'chidemoon_ai_output_invalid', __( 'The AI output did not match the required editorial review format.', 'chidemoon-ai' ) );
		}
		if ( self::length( $title ) > 220 || self::length( $content ) > 30000 || self::length( $excerpt ) > 600 || count( $facts ) > 20 || count( $citations ) > 12 ) {
			return new WP_Error( 'chidemoon_ai_output_bounds', __( 'The AI output exceeded the safe editorial bounds.', 'chidemoon-ai' ) );
		}

		$allowed_sources = Chidemoon_AI_Evidence::source_ids( $evidence );
		$clean_facts     = array();
		foreach ( $facts as $fact ) {
			if ( ! is_string( $fact ) || self::length( $fact ) > 500 ) {
				return new WP_Error( 'chidemoon_ai_output_invalid', __( 'The AI output contained an invalid review flag.', 'chidemoon-ai' ) );
			}
			$clean_facts[] = sanitize_text_field( $fact );
		}
		$clean_citations = array();
		foreach ( $citations as $citation ) {
			$citation = sanitize_text_field( (string) $citation );
			if ( ! in_array( $citation, $allowed_sources, true ) ) {
				return new WP_Error( 'chidemoon_ai_output_citation', __( 'The AI output cited a source outside the reviewed evidence.', 'chidemoon-ai' ) );
			}
			$clean_citations[] = $citation;
		}

		return array(
			'title'                => $title,
			'content'              => $content,
			'excerpt'              => $excerpt,
			'facts_needing_review' => array_values( array_unique( $clean_facts ) ),
			'citation_source_ids'  => array_values( array_unique( $clean_citations ) ),
		);
	}

	/**
	 * @param array<string, mixed> $job
	 * @return int|WP_Error
	 */
	private static function apply_image( array $job ): int|WP_Error {
		$result        = is_array( $job['result_payload'] ?? null ) ? $job['result_payload'] : array();
		$attachment_id = absint( $result['attachment_id'] ?? 0 );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error( 'chidemoon_ai_image_missing', __( 'The approved generated image is unavailable.', 'chidemoon-ai' ) );
		}

		update_post_meta( $attachment_id, '_chidemoon_ai_review_state', Chidemoon_AI_State_Machine::APPROVED );
		$target_post_id = absint( $job['target_post_id'] ?? 0 );
		if ( $target_post_id ) {
			$target = get_post( $target_post_id );
			if ( $target && 'draft' === $target->post_status ) {
				$attachments   = get_post_meta( $target_post_id, '_chidemoon_ai_media_attachment_ids', true );
				$attachments   = is_array( $attachments ) ? array_map( 'absint', $attachments ) : array();
				$attachments[] = $attachment_id;
				update_post_meta( $target_post_id, '_chidemoon_ai_media_attachment_ids', array_values( array_unique( $attachments ) ) );
			}
		}

		if ( ! Chidemoon_AI_Repository::mark_applied( (int) $job['id'], $target_post_id ) ) {
			return new WP_Error( 'chidemoon_ai_apply_state_conflict', __( 'The image job changed while it was being applied.', 'chidemoon-ai' ), array( 'status' => 409 ) );
		}

		return $attachment_id;
	}

	/**
	 * @param array<string, mixed> $job
	 * @return WP_Post|WP_Error
	 */
	private static function safe_draft_target( array $job, int $actor_id ): WP_Post|WP_Error {
		$target_post_id = absint( $job['target_post_id'] ?? 0 );
		$target         = $target_post_id ? get_post( $target_post_id ) : null;
		if ( $target && current_user_can( 'edit_post', $target->ID ) && in_array( $target->post_status, array( 'draft', 'auto-draft', 'pending' ), true ) ) {
			return $target;
		}

		$result = is_array( $job['result_payload'] ?? null ) ? $job['result_payload'] : array();
		$post_id = wp_insert_post(
			array(
				'post_author'  => $actor_id,
				'post_status'  => 'draft',
				'post_type'    => 'post',
				'post_title'   => sanitize_text_field( (string) ( $result['title'] ?? __( 'AI editorial draft', 'chidemoon-ai' ) ) ),
				'post_content' => '',
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new WP_Error( 'chidemoon_ai_draft_create_failed', __( 'A safe editorial draft could not be created.', 'chidemoon-ai' ) );
		}

		$draft = get_post( $post_id );
		return $draft instanceof WP_Post ? $draft : new WP_Error( 'chidemoon_ai_draft_missing', __( 'The new editorial draft could not be loaded.', 'chidemoon-ai' ) );
	}

	/**
	 * @param array<string, mixed> $job
	 * @param array<int, string> $source_ids
	 * @return array<string, mixed>
	 */
	private static function provenance( array $job, Chidemoon_AI_Provider_Interface $provider, array $source_ids ): array {
		$persisted  = Chidemoon_AI_Repository::find( (int) $job['id'] );
		$previous   = is_array( $persisted['provenance'] ?? null ) ? $persisted['provenance'] : array();
		$provenance = array(
			'provider'          => $provider->name(),
			'model'             => 'image' === $job['job_type'] ? $provider->image_model() : $provider->text_model(),
			'job_type'          => (string) $job['job_type'],
			'generated_at'      => current_time( 'mysql', true ),
			'source_ids'        => array_values( $source_ids ),
			'review_required'   => true,
			'prompt_version'    => '1',
		);
		if ( ! empty( $previous['moderation'] ) && is_array( $previous['moderation'] ) ) {
			$provenance['moderation'] = $previous['moderation'];
		}
		if ( 'image' === $job['job_type'] ) {
			$payload = is_array( $job['request_payload'] ?? null ) ? $job['request_payload'] : array();
			$provenance['rights_attestation'] = ! empty( $payload['rights_attestation'] );
		}

		return $provenance;
	}

	/**
	 * @param array<string, mixed> $job
	 */
	private static function fail( array $job, WP_Error $error, ?Chidemoon_AI_Provider_Interface $provider = null ): void {
		Chidemoon_AI_Repository::mark_failed(
			(int) $job['id'],
			Chidemoon_AI_State_Machine::GENERATING,
			$error->get_error_code(),
			$error->get_error_message()
		);
		Chidemoon_AI_Usage::complete(
			(int) $job['id'],
			Chidemoon_AI_State_Machine::FAILED,
			$provider ? $provider->name() : '',
			$provider ? ( 'image' === $job['job_type'] ? $provider->image_model() : $provider->text_model() ) : ''
		);
	}

	/**
	 * @param array<string, mixed> $job
	 */
	private static function record_moderation_error( array $job, WP_Error $error ): void {
		$data    = $error->get_error_data();
		$outcome = is_array( $data ) && is_array( $data['outcome'] ?? null ) ? $data['outcome'] : array(
			'stage'      => 'unknown',
			'status'     => 'unavailable',
			'model'      => '',
			'categories' => array(),
			'checked_at' => current_time( 'mysql', true ),
		);
		Chidemoon_AI_Repository::record_moderation( (int) $job['id'], $outcome );
	}

	private static function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
