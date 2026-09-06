<?php
/**
 * Free-first external web evidence: direct fetch + DuckDuckGo, no key.
 *
 * Priority:
 *  1. Direct fetch of the editor-supplied source URL (always free).
 *  2. DuckDuckGo HTML search (free, no key) + fetch of top results.
 *  3. Optional paid search key (Tavily/Brave) ONLY when search_mode is
 *     free_plus_key and a host env key exists.
 *  4. Model-native web search ONLY when search_mode is model_native
 *     (handled inside the provider, fail-open).
 *
 * Every fetch is SSRF-guarded (https only, no credentials, no private IPs,
 * bounded size/timeout) and cached in transients.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Web {
	public const MAX_FETCH_BYTES = 1572864;
	public const FETCH_TIMEOUT    = 12;

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect_for_product( int $job_id, string $product_title, string $merchant, string $source_url, bool $use_web ): array {
		$collected = array();

		if ( '' !== $source_url ) {
			$fetched = self::fetch_url( $source_url );
			if ( ! is_wp_error( $fetched ) ) {
				$item = array(
					'source_type'    => 'external_url',
					'source_id'      => 'url-' . substr( hash( 'sha256', $source_url ), 0, 12 ),
					'source_url'     => $source_url,
					'source_excerpt' => $fetched['excerpt'],
					'content_hash'   => $fetched['hash'],
				);
				Chidemoon_AI_Evidence::persist_external( $job_id, $item );
				$item['freshness_at'] = current_time( 'mysql', true );
				$collected[]          = $item;
			}
		}

		if ( $use_web && self::search_enabled() ) {
			foreach ( self::free_search( $product_title, $merchant ) as $result ) {
				$fetched = self::fetch_url( (string) ( $result['url'] ?? '' ) );
				$excerpt = '';
				$hash    = '';
				if ( ! is_wp_error( $fetched ) ) {
					$excerpt = $fetched['excerpt'];
					$hash    = $fetched['hash'];
				} else {
					$excerpt = (string) ( $result['snippet'] ?? '' );
					$hash    = hash( 'sha256', $excerpt );
				}
				if ( '' === trim( $excerpt ) ) {
					continue;
				}
				$item = array(
					'source_type'    => 'web_search',
					'source_id'      => 'web-' . substr( hash( 'sha256', (string) ( $result['url'] ?? $excerpt ) ), 0, 12 ),
					'source_url'     => (string) ( $result['url'] ?? '' ),
					'source_excerpt' => 'Result title: ' . (string) ( $result['title'] ?? '' ) . "\nSnippet: " . $excerpt,
					'content_hash'   => $hash,
				);
				Chidemoon_AI_Evidence::persist_external( $job_id, $item );
				$item['freshness_at'] = current_time( 'mysql', true );
				$collected[]          = $item;
			}

			if ( self::want_paid_search() ) {
				foreach ( self::paid_search( $product_title, $merchant ) as $result ) {
					$item = array(
						'source_type'    => 'web_search',
						'source_id'      => 'web-' . substr( hash( 'sha256', (string) ( $result['url'] ?? ( $result['snippet'] ?? '' ) ) ), 0, 12 ),
						'source_url'     => (string) ( $result['url'] ?? '' ),
						'source_excerpt' => 'Result title: ' . (string) ( $result['title'] ?? '' ) . "\nSnippet: " . (string) ( $result['snippet'] ?? '' ),
						'content_hash'   => hash( 'sha256', (string) ( $result['snippet'] ?? '' ) ),
					);
					Chidemoon_AI_Evidence::persist_external( $job_id, $item );
					$item['freshness_at'] = current_time( 'mysql', true );
					$collected[]          = $item;
				}
			}
		}

		return $collected;
	}

	public static function search_enabled(): bool {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			return Chidemoon_AI_Settings::is_search_enabled();
		}

		return 'off' !== strtolower( trim( (string) getenv( 'CHIDEMOON_AI_SEARCH_MODE' ) ) );
	}

	private static function want_paid_search(): bool {
		$mode = class_exists( 'Chidemoon_AI_Settings' ) ? Chidemoon_AI_Settings::get_string( 'search_mode' ) : (string) getenv( 'CHIDEMOON_AI_SEARCH_MODE' );
		if ( 'free_plus_key' !== $mode ) {
			return false;
		}

		return class_exists( 'Chidemoon_AI_Settings' ) ? Chidemoon_AI_Settings::search_has_key() : '' !== trim( (string) getenv( 'CHIDEMOON_AI_SEARCH_KEY' ) );
	}

	private static function max_results(): int {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			return Chidemoon_AI_Settings::get_int( 'search_max_results' );
		}

		return 5;
	}

	private static function cache_hours(): int {
		if ( class_exists( 'Chidemoon_AI_Settings' ) ) {
			return Chidemoon_AI_Settings::get_int( 'search_cache_hours' );
		}

		return 24;
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function free_search( string $title, string $merchant ): array {
		$query = trim( $title . ' ' . $merchant );
		$query = function_exists( 'mb_substr' ) ? mb_substr( $query, 0, 160 ) : substr( $query, 0, 160 );
		if ( '' === $query ) {
			return array();
		}
		$cache_key = 'chidemoon_web_ddg_' . md5( strtolower( $query ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		// Global 5s rate limit so concurrent enrich jobs never hammer DDG.
		$lock = get_transient( 'chidemoon_web_ddg_lock' );
		if ( false !== $lock ) {
			return array();
		}
		set_transient( 'chidemoon_web_ddg_lock', 1, 5 );

		$url      = 'https://html.duckduckgo.com/html/?q=' . rawurlencode( $query );
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'            => 12,
				'redirection'        => 0,
				'limit_response_size' => 262144,
				'reject_unsafe_urls' => true,
				'user-agent'         => 'Mozilla/5.0 (compatible; ChidemoonBot/1.0; +https://chidemoon.com/)',
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		$html = (string) wp_remote_retrieve_body( $response );
		if ( '' === $html ) {
			return array();
		}

		$results = array();
		if ( preg_match_all( '#<a[^>]+class="[^"]*result__a[^"]*"[^>]+href="([^"]+)"[^>]*>(.*?)</a>#is', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$href = html_entity_decode( (string) ( $match[1] ?? '' ), ENT_QUOTES, 'UTF-8' );
				// DDG wraps in //duckduckgo.com/l/?uddg=<encoded>.
				if ( 0 === strpos( $href, '//duckduckgo.com/l/' ) || false !== strpos( $href, 'uddg=' ) ) {
					parse_str( (string) wp_parse_url( $href, PHP_URL_QUERY ), $qs );
					if ( ! empty( $qs['uddg'] ) ) {
						$href = (string) $qs['uddg'];
					}
				}
				if ( ! self::is_safe_public_url( $href ) ) {
					continue;
				}
				$title_text = trim( wp_strip_all_tags( (string) ( $match[2] ?? '' ) ) );
				if ( '' === $title_text ) {
					continue;
				}
				$results[] = array(
					'title'   => function_exists( 'mb_substr' ) ? mb_substr( $title_text, 0, 220 ) : substr( $title_text, 0, 220 ),
					'url'     => $href,
					'snippet' => $title_text,
				);
				if ( count( $results ) >= self::max_results() ) {
					break;
				}
			}
		}

		set_transient( $cache_key, $results, self::cache_hours() * HOUR_IN_SECONDS );

		return $results;
	}

	/**
	 * Best-effort Tavily-compatible paid search. Only runs with an explicit
	 * host key; never required for the free path.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function paid_search( string $title, string $merchant ): array {
		$api_key = trim( (string) getenv( 'CHIDEMOON_AI_SEARCH_KEY' ) );
		$endpoint = trim( (string) getenv( 'CHIDEMOON_AI_SEARCH_ENDPOINT' ) );
		if ( '' === $api_key || '' === $endpoint ) {
			return array();
		}
		if ( ! Chidemoon_AI_Provider_Factory::is_safe_provider_url( $endpoint ) ) {
			return array();
		}
		$response = wp_safe_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'query'        => function_exists( 'mb_substr' ) ? mb_substr( trim( $title . ' ' . $merchant ), 0, 200 ) : substr( trim( $title . ' ' . $merchant ), 0, 200 ),
						'max_results'  => self::max_results(),
						'search_depth' => 'basic',
					)
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return array();
		}
		$items   = is_array( $body['results'] ?? null ) ? $body['results'] : ( is_array( $body['data'] ?? null ) ? $body['data'] : array() );
		$results = array();
		foreach ( array_slice( $items, 0, self::max_results() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$url = (string) ( $item['url'] ?? '' );
			if ( ! self::is_safe_public_url( $url ) ) {
				continue;
			}
			$results[] = array(
				'title'   => sanitize_text_field( (string) ( $item['title'] ?? $url ) ),
				'url'     => $url,
				'snippet' => sanitize_textarea_field( (string) ( $item['content'] ?? ( $item['snippet'] ?? '' ) ) ),
			);
		}

		return $results;
	}

	/**
	 * @return array{excerpt: string, hash: string}|WP_Error
	 */
	public static function fetch_url( string $url ): array|WP_Error {
		$url = esc_url_raw( trim( $url ), array( 'http', 'https' ) );
		if ( '' === $url || ! self::is_safe_public_url( $url ) ) {
			return new WP_Error( 'chidemoon_ai_web_url_unsafe', __( 'The evidence URL is not a safe public URL.', 'chidemoon-ai' ) );
		}
		$cache_key = 'chidemoon_web_fetch_' . md5( strtolower( $url ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['excerpt'], $cached['hash'] ) ) {
			return $cached;
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => self::FETCH_TIMEOUT,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_FETCH_BYTES,
				'reject_unsafe_urls'  => true,
				'user-agent'          => 'Mozilla/5.0 (compatible; ChidemoonBot/1.0; +https://chidemoon.com/)',
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'chidemoon_ai_web_fetch_failed', __( 'The evidence page could not be fetched.', 'chidemoon-ai' ) );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'chidemoon_ai_web_fetch_failed', __( 'The evidence page did not return a readable response.', 'chidemoon-ai' ) );
		}
		$content_type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
		if ( '' !== $content_type && false === strpos( $content_type, 'html' ) && false === strpos( $content_type, 'text' ) ) {
			return new WP_Error( 'chidemoon_ai_web_fetch_type', __( 'The evidence URL is not a readable page.', 'chidemoon-ai' ) );
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === trim( $body ) ) {
			return new WP_Error( 'chidemoon_ai_web_fetch_empty', __( 'The evidence page was empty.', 'chidemoon-ai' ) );
		}
		// Drop scripts/styles before stripping tags.
		$body    = preg_replace( '#<(script|style|noscript)[^>]*?>.*?</\\1>#is', ' ', $body ) ?? $body;
		$text    = html_entity_decode( wp_strip_all_tags( $body ), ENT_QUOTES, 'UTF-8' );
		$text    = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
		$text    = trim( $text );
		$excerpt = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 8000 ) : substr( $text, 0, 8000 );
		if ( '' === $excerpt ) {
			return new WP_Error( 'chidemoon_ai_web_fetch_empty', __( 'The evidence page contained no readable text.', 'chidemoon-ai' ) );
		}
		$result = array(
			'excerpt' => $excerpt,
			'hash'    => hash( 'sha256', $excerpt ),
		);
		set_transient( $cache_key, $result, self::cache_hours() * HOUR_IN_SECONDS );

		return $result;
	}

	public static function is_safe_public_url( string $url ): bool {
		$parts = wp_parse_url( trim( $url ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			return false;
		}
		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return false;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}
		if ( isset( $parts['port'] ) && ! in_array( (int) $parts['port'], array( 80, 443 ), true ) ) {
			return false;
		}
		$host = strtolower( (string) $parts['host'] );
		if ( 'localhost' === $host || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.internal' ) || str_ends_with( $host, '.invalid' ) ) {
			return false;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}
		$addresses = gethostbynamel( $host );
		if ( false === $addresses || empty( $addresses ) ) {
			return false;
		}
		foreach ( $addresses as $address ) {
			if ( ! filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return false;
			}
		}

		return (bool) esc_url_raw( $url, array( 'http', 'https' ) );
	}
}
