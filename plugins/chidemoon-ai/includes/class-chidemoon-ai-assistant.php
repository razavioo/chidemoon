<?php
/**
 * Public retrieval only: no provider call, tools, drafts, or write capability.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Assistant {
	private const WINDOW_SECONDS = 600;
	private const WINDOW_LIMIT   = 10;

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function answer( string $question ): array|WP_Error {
		$question = trim( preg_replace( '/\s+/', ' ', sanitize_textarea_field( $question ) ) ?? '' );
		if ( self::length( $question ) < 3 || self::length( $question ) > 500 ) {
			return new WP_Error( 'chidemoon_ai_assistant_question_invalid', __( 'Ask a question between 3 and 500 characters.', 'chidemoon-ai' ), array( 'status' => 400 ) );
		}

		$limited = self::check_rate_limit();
		if ( is_wp_error( $limited ) ) {
			return $limited;
		}

		// Identical questions are asked repeatedly (double submit, bots). Cache
		// the retrieval result briefly so a repeated LIKE search does not hit
		// the database every time.
		$cache_key = 'chidemoon_assist_' . substr( hash( 'sha256', mb_strtolower( $question ) ), 0, 40 );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['answer'], $cached['sources'], $cached['mode'] ) ) {
			return $cached;
		}

		// Bound the search term: WP search builds LIKE clauses per word, so cap
		// at 20 words / 120 chars to keep the query cheap while preserving intent.
		$words       = preg_split( '/\s+/u', $question, -1, PREG_SPLIT_NO_EMPTY );
		$search_term = is_array( $words ) ? implode( ' ', array_slice( $words, 0, 20 ) ) : $question;
		if ( function_exists( 'mb_substr' ) ) {
			$search_term = mb_substr( $search_term, 0, 120 );
		} else {
			$search_term = substr( $search_term, 0, 120 );
		}

		$query = new WP_Query(
			array(
				'post_type'              => array( 'post', 'page', 'product' ),
				'post_status'            => 'publish',
				's'                      => $search_term,
				'posts_per_page'         => 4,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$sources = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
				continue;
			}
			$excerpt = wp_strip_all_tags( $post->post_excerpt ?: $post->post_content );
			$sources[] = array(
				'id'      => (int) $post->ID,
				'type'    => $post->post_type,
				'title'   => get_the_title( $post ),
				'url'     => get_permalink( $post ),
				'excerpt' => self::excerpt( $excerpt, 280 ),
			);
		}

		if ( empty( $sources ) ) {
			$response = array(
				'answer'  => __( 'No published Chidemoon source matched that question yet.', 'chidemoon-ai' ),
				'sources' => array(),
				'mode'    => 'published-retrieval-only',
			);
			set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );
			return $response;
		}

		$response = array(
			'answer'  => __( 'These published Chidemoon sources are the available evidence for your question. Open a source for the full, reviewed details.', 'chidemoon-ai' ),
			'sources' => $sources,
			'mode'    => 'published-retrieval-only',
		);
		set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );
		return $response;
	}

	/**
	 * @return true|WP_Error
	 */
	private static function check_rate_limit(): true|WP_Error {
		$ip         = self::client_ip();
		$identifier = hash_hmac( 'sha256', $ip ?: 'unknown', wp_salt( 'nonce' ) );
		$key        = 'chidemoon_ai_assistant_' . substr( $identifier, 0, 36 );
		$record     = get_transient( $key );
		$now        = time();
		if ( ! is_array( $record ) || ! isset( $record['started_at'] ) || $now - (int) $record['started_at'] >= self::WINDOW_SECONDS ) {
			$record = array( 'started_at' => $now, 'count' => 0 );
		}
		if ( (int) $record['count'] >= self::WINDOW_LIMIT ) {
			return new WP_Error( 'chidemoon_ai_assistant_rate_limited', __( 'Too many assistant requests. Please wait before trying again.', 'chidemoon-ai' ), array( 'status' => 429 ) );
		}

		$record['count'] = (int) $record['count'] + 1;
		set_transient( $key, $record, self::WINDOW_SECONDS );
		return true;
	}

	private static function excerpt( string $value, int $length ): string {
		$value = trim( preg_replace( '/\s+/', ' ', $value ) ?? '' );
		if ( self::length( $value ) <= $length ) {
			return $value;
		}

		return ( function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length ) ) . '…';
	}

	private static function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	private static function client_ip(): string {
		$forwarded = (string) ( $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' );
		if ( '' !== $forwarded ) {
			$parts = explode( ',', $forwarded );
			$first = trim( (string) reset( $parts ) );
			if ( '' !== $first ) {
				return sanitize_text_field( $first );
			}
		}
		return sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	}
}
