<?php
/**
 * Permissioned REST surface for editors and a narrow public assistant.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_REST_Controller {
	private const NAMESPACE = 'chidemoon-ai/v1';

	public static function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/jobs/text',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_text_job' ),
				'permission_callback' => array( __CLASS__, 'can_create_text_job' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/jobs/comparison',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_comparison_job' ),
				'permission_callback' => array( __CLASS__, 'can_create_comparison_job' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/jobs/image',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_image_job' ),
				'permission_callback' => array( __CLASS__, 'can_create_image_job' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_job' ),
				'permission_callback' => array( __CLASS__, 'can_view_job' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)/approve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'approve_job' ),
				'permission_callback' => array( __CLASS__, 'can_review_job' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)/reject',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'reject_job' ),
				'permission_callback' => array( __CLASS__, 'can_review_job' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/jobs/(?P<id>\d+)/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'apply_job' ),
				'permission_callback' => array( __CLASS__, 'can_review_job' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/assistant',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'assistant' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_text_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data       = self::data( $request );
		$kind       = sanitize_key( (string) ( $data['kind'] ?? '' ) );
		$allowed    = array( 'product_description', 'short_description', 'pros_cons', 'faq', 'buying_guide', 'article_outline', 'article_draft', 'seo_draft', 'captions', 'alt_text', 'internal_links', 'shop_the_look' );
		if ( ! self::valid_optional_id( $data['target_post_id'] ?? null ) ) {
			return self::error( 'chidemoon_ai_target_invalid', __( 'Choose a valid editable target post.', 'chidemoon-ai' ), 400 );
		}
		$target_id  = self::input_id( $data['target_post_id'] ?? null );
		if ( ! self::valid_id_list( $data['source_post_ids'] ?? array(), 0, 4 ) ) {
			return self::error( 'chidemoon_ai_source_list_invalid', __( 'Choose up to four valid WordPress evidence sources.', 'chidemoon-ai' ), 400 );
		}
		$source_ids = self::post_ids( $data['source_post_ids'] ?? array(), 0, 4 );
		if ( ! in_array( $kind, $allowed, true ) ) {
			return self::error( 'chidemoon_ai_kind_invalid', __( 'Choose a supported AI editorial request type.', 'chidemoon-ai' ), 400 );
		}
		if ( $target_id ) {
			$source_ids[] = $target_id;
		}
		$source_ids = array_values( array_unique( $source_ids ) );
		if ( empty( $source_ids ) ) {
			return self::error( 'chidemoon_ai_evidence_required', __( 'Select a current WordPress draft, page, or product as evidence before requesting AI output.', 'chidemoon-ai' ), 400 );
		}

		return self::queue_job(
			$request,
			'text',
			$target_id,
			$source_ids,
			array(
				'kind'            => $kind,
				'instructions'    => self::bounded_text( (string) ( $data['instructions'] ?? '' ), 2000 ),
				'source_post_ids' => $source_ids,
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_comparison_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data        = self::data( $request );
		if ( ! self::valid_id_list( $data['product_ids'] ?? array(), 2, 4 ) ) {
			return self::error( 'chidemoon_ai_comparison_products', __( 'A comparison needs between two and four distinct WooCommerce products.', 'chidemoon-ai' ), 400 );
		}
		$product_ids = self::post_ids( $data['product_ids'] ?? array(), 2, 4 );
		if ( ! self::valid_optional_id( $data['target_post_id'] ?? null ) ) {
			return self::error( 'chidemoon_ai_target_invalid', __( 'Choose a valid editable target post.', 'chidemoon-ai' ), 400 );
		}
		$target_id   = self::input_id( $data['target_post_id'] ?? null );
		if ( count( $product_ids ) < 2 || count( $product_ids ) > 4 ) {
			return self::error( 'chidemoon_ai_comparison_products', __( 'A comparison needs between two and four distinct WooCommerce products.', 'chidemoon-ai' ), 400 );
		}

		$source_ids = $product_ids;
		if ( $target_id ) {
			$source_ids[] = $target_id;
		}

		return self::queue_job(
			$request,
			'comparison',
			$target_id,
			$source_ids,
			array(
				'kind'         => 'comparison',
				'product_ids'  => $product_ids,
				'instructions' => self::bounded_text( (string) ( $data['instructions'] ?? '' ), 2000 ),
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_image_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data          = self::data( $request );
		$mode          = sanitize_key( (string) ( $data['mode'] ?? 'generate' ) );
		if ( ! self::valid_id_list( $data['source_attachment_ids'] ?? array(), 0, 4 ) ) {
			return self::error( 'chidemoon_ai_source_image_invalid', __( 'Choose up to four valid Media Library source images.', 'chidemoon-ai' ), 400 );
		}
		$attachments   = self::post_ids( $data['source_attachment_ids'] ?? array(), 0, 4 );
		if ( ! self::valid_optional_id( $data['target_post_id'] ?? null ) ) {
			return self::error( 'chidemoon_ai_target_invalid', __( 'Choose a valid editable target post.', 'chidemoon-ai' ), 400 );
		}
		$target_id     = self::input_id( $data['target_post_id'] ?? null );
		$allowed_modes = array( 'enhance', 'background', 'scene', 'aggregate', 'generate' );
		$prompt        = self::bounded_text( (string) ( $data['prompt'] ?? '' ), 1600 );
		if ( ! self::rights_attested( $data['rights_attestation'] ?? false ) ) {
			return self::error( 'chidemoon_ai_rights_attestation_required', __( 'Confirm that you have the rights to use every source image and requested image concept.', 'chidemoon-ai' ), 400 );
		}
		if ( ! in_array( $mode, $allowed_modes, true ) || '' === $prompt ) {
			return self::error( 'chidemoon_ai_image_request_invalid', __( 'Choose an image mode and provide a bounded editor request.', 'chidemoon-ai' ), 400 );
		}
		if ( in_array( $mode, array( 'enhance', 'background', 'scene', 'aggregate' ), true ) && empty( $attachments ) ) {
			return self::error( 'chidemoon_ai_source_image_required', __( 'This image mode requires at least one Media Library source image.', 'chidemoon-ai' ), 400 );
		}

		return self::queue_job(
			$request,
			'image',
			$target_id,
			array(),
			array(
				'mode'                  => $mode,
				'prompt'                => $prompt,
				'source_attachment_ids' => $attachments,
				'rights_attestation'    => true,
			)
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$job = Chidemoon_AI_Repository::find( absint( $request['id'] ) );
		if ( ! $job ) {
			return self::error( 'chidemoon_ai_job_not_found', __( 'The AI job was not found.', 'chidemoon-ai' ), 404 );
		}

		return new WP_REST_Response( self::job_response( $job ), 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function approve_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$job = self::reviewable_job( $request );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		if ( ! Chidemoon_AI_Repository::approve( (int) $job['id'], get_current_user_id() ) ) {
			return self::error( 'chidemoon_ai_state_conflict', __( 'This AI job is no longer awaiting review.', 'chidemoon-ai' ), 409 );
		}
		if ( 'image' === $job['job_type'] ) {
			$attachment_id = absint( $job['result_payload']['attachment_id'] ?? 0 );
			if ( $attachment_id ) {
				update_post_meta( $attachment_id, '_chidemoon_ai_review_state', Chidemoon_AI_State_Machine::APPROVED );
			}
		}

		return new WP_REST_Response( self::job_response( Chidemoon_AI_Repository::find( (int) $job['id'] ) ?: $job ), 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function reject_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$job = self::reviewable_job( $request );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		if ( ! in_array( $job['state'], array( Chidemoon_AI_State_Machine::REVIEW_REQUIRED, Chidemoon_AI_State_Machine::APPROVED ), true ) ) {
			return self::error( 'chidemoon_ai_state_conflict', __( 'This AI job cannot be rejected in its current state.', 'chidemoon-ai' ), 409 );
		}
		$data   = self::data( $request );
		$reason = self::bounded_text( (string) ( $data['reason'] ?? '' ), 500 );
		if ( ! Chidemoon_AI_Repository::reject( (int) $job['id'], (string) $job['state'], get_current_user_id(), $reason ) ) {
			return self::error( 'chidemoon_ai_state_conflict', __( 'This AI job changed while it was being reviewed.', 'chidemoon-ai' ), 409 );
		}
		if ( 'image' === $job['job_type'] ) {
			$attachment_id = absint( $job['result_payload']['attachment_id'] ?? 0 );
			if ( $attachment_id ) {
				update_post_meta( $attachment_id, '_chidemoon_ai_review_state', Chidemoon_AI_State_Machine::REJECTED );
			}
		}

		return new WP_REST_Response( self::job_response( Chidemoon_AI_Repository::find( (int) $job['id'] ) ?: $job ), 200 );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function apply_job( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$job = self::reviewable_job( $request );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		$result = Chidemoon_AI_Runner::apply( $job, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'job'       => self::job_response( Chidemoon_AI_Repository::find( (int) $job['id'] ) ?: $job ),
				'applied_id' => $result,
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public static function assistant( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data   = self::data( $request );
		$result = Chidemoon_AI_Assistant::answer( (string) ( $data['question'] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function can_create_text_job( WP_REST_Request $request ): true|WP_Error {
		if ( ! current_user_can( Chidemoon_AI_Capabilities::GENERATE ) ) {
			return self::forbidden();
		}
		$data   = self::data( $request );
		$target = self::input_id( $data['target_post_id'] ?? null );
		$ids    = self::post_ids( $data['source_post_ids'] ?? array(), 0, 4 );
		if ( $target ) {
			$ids[] = $target;
		}
		return self::can_edit_posts( $ids, array( 'post', 'page', 'product' ) );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function can_create_comparison_job( WP_REST_Request $request ): true|WP_Error {
		if ( ! current_user_can( Chidemoon_AI_Capabilities::GENERATE ) ) {
			return self::forbidden();
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return self::error( 'chidemoon_ai_woocommerce_required', __( 'WooCommerce is required for product comparisons.', 'chidemoon-ai' ), 503 );
		}
		$data        = self::data( $request );
		$product_ids = self::post_ids( $data['product_ids'] ?? array(), 2, 4 );
		if ( count( $product_ids ) < 2 || count( $product_ids ) > 4 ) {
			return self::error( 'chidemoon_ai_comparison_products', __( 'A comparison needs between two and four distinct WooCommerce products.', 'chidemoon-ai' ), 400 );
		}
		foreach ( $product_ids as $product_id ) {
			if ( 'product' !== get_post_type( $product_id ) || ! wc_get_product( $product_id ) ) {
				return self::error( 'chidemoon_ai_product_invalid', __( 'A selected comparison item is not a WooCommerce product.', 'chidemoon-ai' ), 400 );
			}
		}
		$target = self::input_id( $data['target_post_id'] ?? null );
		return self::can_edit_posts( $target ? array_merge( $product_ids, array( $target ) ) : $product_ids, array( 'post', 'page', 'product' ) );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function can_create_image_job( WP_REST_Request $request ): true|WP_Error {
		if ( ! current_user_can( Chidemoon_AI_Capabilities::GENERATE ) || ! current_user_can( 'upload_files' ) ) {
			return self::forbidden();
		}
		$data        = self::data( $request );
		$attachments = self::post_ids( $data['source_attachment_ids'] ?? array(), 0, 4 );
		foreach ( $attachments as $attachment_id ) {
			$valid = Chidemoon_AI_Media::validate_source_attachment( $attachment_id );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}
		$target = self::input_id( $data['target_post_id'] ?? null );
		return self::can_edit_posts( $target ? array( $target ) : array(), array( 'post', 'page', 'product' ) );
	}

	/**
	 * @return true|WP_Error
	 */
	public static function can_view_job( WP_REST_Request $request ): true|WP_Error {
		$job = Chidemoon_AI_Repository::find( absint( $request['id'] ) );
		if ( ! $job ) {
			return self::error( 'chidemoon_ai_job_not_found', __( 'The AI job was not found.', 'chidemoon-ai' ), 404 );
		}
		if ( current_user_can( Chidemoon_AI_Capabilities::REVIEW ) && self::can_edit_job_target( $job ) ) {
			return true;
		}
		if ( current_user_can( Chidemoon_AI_Capabilities::GENERATE ) && (int) $job['requested_by'] === get_current_user_id() && self::can_edit_job_target( $job ) ) {
			return true;
		}

		return self::forbidden();
	}

	/**
	 * @return true|WP_Error
	 */
	public static function can_review_job( WP_REST_Request $request ): true|WP_Error {
		if ( ! current_user_can( Chidemoon_AI_Capabilities::REVIEW ) ) {
			return self::forbidden();
		}
		$job = Chidemoon_AI_Repository::find( absint( $request['id'] ) );
		if ( ! $job ) {
			return self::error( 'chidemoon_ai_job_not_found', __( 'The AI job was not found.', 'chidemoon-ai' ), 404 );
		}

		return self::can_edit_job_target( $job ) ? true : self::forbidden();
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<int, int> $evidence_post_ids
	 * @return WP_REST_Response|WP_Error
	 */
	private static function queue_job( WP_REST_Request $request, string $job_type, int $target_post_id, array $evidence_post_ids, array $payload ): WP_REST_Response|WP_Error {
		$provider = Chidemoon_AI_Provider_Factory::create();
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}
		$moderation = Chidemoon_AI_Moderation::validate_configuration();
		if ( is_wp_error( $moderation ) ) {
			return $moderation;
		}

		$idempotency_key = self::idempotency_key( $request, $job_type );
		$existing        = Chidemoon_AI_Repository::find_by_idempotency_key( $idempotency_key );
		if ( $existing ) {
			return new WP_REST_Response( array( 'job' => self::job_response( $existing ), 'deduplicated' => true ), 200 );
		}

		$payload['target_post_id'] = $target_post_id;
		$payload['evidence_ids']   = array_values( array_unique( array_map( 'absint', $evidence_post_ids ) ) );
		$request_hash               = hash( 'sha256', wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$initial_provenance = array( 'request_version' => '1', 'review_required' => true );
		if ( 'image' === $job_type ) {
			$initial_provenance['rights_attestation'] = ! empty( $payload['rights_attestation'] );
		}
		$job = Chidemoon_AI_Repository::create(
			array(
				'idempotency_key' => $idempotency_key,
				'job_type'        => $job_type,
				'target_post_id'  => $target_post_id,
				'requested_by'    => get_current_user_id(),
				'request_hash'    => $request_hash,
				'request_payload' => $payload,
				'provenance'      => $initial_provenance,
			)
		);
		if ( is_wp_error( $job ) ) {
			return $job;
		}

		if ( in_array( $job_type, array( 'text', 'comparison' ), true ) ) {
			$captured = Chidemoon_AI_Evidence::capture_posts( (int) $job['id'], $evidence_post_ids );
			if ( is_wp_error( $captured ) ) {
				Chidemoon_AI_Repository::mark_failed( (int) $job['id'], Chidemoon_AI_State_Machine::QUEUED, $captured->get_error_code(), $captured->get_error_message() );
				return $captured;
			}
		}

		$reserved = Chidemoon_AI_Usage::reserve( (int) $job['id'], get_current_user_id(), $job_type );
		if ( is_wp_error( $reserved ) ) {
			Chidemoon_AI_Repository::mark_failed( (int) $job['id'], Chidemoon_AI_State_Machine::QUEUED, $reserved->get_error_code(), $reserved->get_error_message() );
			return $reserved;
		}

		Chidemoon_AI_Runner::enqueue( (int) $job['id'] );
		return new WP_REST_Response( array( 'job' => self::job_response( Chidemoon_AI_Repository::find( (int) $job['id'] ) ?: $job ), 'deduplicated' => false ), 202 );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function reviewable_job( WP_REST_Request $request ): array|WP_Error {
		$job = Chidemoon_AI_Repository::find( absint( $request['id'] ) );
		return $job ?: self::error( 'chidemoon_ai_job_not_found', __( 'The AI job was not found.', 'chidemoon-ai' ), 404 );
	}

	/**
	 * @param array<string, mixed> $job
	 * @return array<string, mixed>
	 */
	private static function job_response( array $job ): array {
		return array(
			'id'             => (int) $job['id'],
			'key'            => (string) $job['job_key'],
			'type'           => (string) $job['job_type'],
			'state'          => (string) $job['state'],
			'target_post_id' => absint( $job['target_post_id'] ?? 0 ),
			'created_at'     => (string) $job['created_at'],
			'updated_at'     => (string) $job['updated_at'],
			'result'         => is_array( $job['result_payload'] ?? null ) ? $job['result_payload'] : array(),
			'provenance'     => is_array( $job['provenance'] ?? null ) ? $job['provenance'] : array(),
			'error'          => ! empty( $job['error_code'] ) ? array( 'code' => (string) $job['error_code'], 'message' => (string) $job['error_message'] ) : null,
		);
	}

	/**
	 * @param array<string, mixed> $job
	 */
	private static function can_edit_job_target( array $job ): bool {
		$target = absint( $job['target_post_id'] ?? 0 );
		return ! $target || current_user_can( 'edit_post', $target );
	}

	/**
	 * @param array<int, int> $ids
	 * @param array<int, string> $allowed_types
	 * @return true|WP_Error
	 */
	private static function can_edit_posts( array $ids, array $allowed_types ): true|WP_Error {
		foreach ( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) as $post_id ) {
			if ( ! in_array( get_post_type( $post_id ), $allowed_types, true ) || ! current_user_can( 'edit_post', $post_id ) ) {
				return self::forbidden();
			}
		}

		return true;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function data( WP_REST_Request $request ): array {
		$data = $request->get_json_params();
		return is_array( $data ) ? $data : $request->get_params();
	}

	/**
	 * @param mixed $value
	 * @return array<int, int>
	 */
	private static function post_ids( $value, int $min, int $max ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
		return count( $ids ) < $min || count( $ids ) > $max ? array() : $ids;
	}

	/**
	 * Reject malformed or duplicate object IDs instead of silently dropping
	 * them, because a partial evidence selection changes the prompt boundary.
	 *
	 * @param mixed $value
	 */
	private static function valid_id_list( $value, int $min, int $max ): bool {
		if ( ! is_array( $value ) || count( $value ) < $min || count( $value ) > $max ) {
			return false;
		}
		$ids = array();
		foreach ( $value as $raw_id ) {
			if ( ! is_scalar( $raw_id ) || ! preg_match( '/^[1-9][0-9]*$/', (string) $raw_id ) ) {
				return false;
			}
			$ids[] = (int) $raw_id;
		}

		return count( array_unique( $ids ) ) === count( $ids );
	}

	/**
	 * @param mixed $value
	 */
	private static function valid_optional_id( $value ): bool {
		return null === $value || '' === $value || 0 === $value || '0' === $value || ( is_scalar( $value ) && 1 === preg_match( '/^[1-9][0-9]*$/', (string) $value ) );
	}

	/**
	 * @param mixed $value
	 */
	private static function input_id( $value ): int {
		return is_scalar( $value ) && 1 === preg_match( '/^[1-9][0-9]*$/', (string) $value ) ? (int) $value : 0;
	}

	/**
	 * @param mixed $value
	 */
	private static function rights_attested( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || ( is_scalar( $value ) && 'true' === strtolower( (string) $value ) );
	}

	private static function idempotency_key( WP_REST_Request $request, string $job_type ): string {
		$data = self::data( $request );
		$key  = (string) ( $request->get_header( 'Idempotency-Key' ) ?: ( $data['idempotency_key'] ?? '' ) );
		$key  = preg_replace( '/[^A-Za-z0-9._:-]/', '', $key ) ?? '';
		if ( '' === $key ) {
			$key = wp_generate_uuid4();
		}

		return substr( get_current_user_id() . ':' . $job_type . ':' . $key, 0, 191 );
	}

	private static function bounded_text( string $value, int $limit ): string {
		$value = trim( wp_strip_all_tags( $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit );
	}

	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	private static function forbidden(): WP_Error {
		return self::error( 'chidemoon_ai_forbidden', __( 'You do not have permission to perform this AI action.', 'chidemoon-ai' ), 403 );
	}
}
