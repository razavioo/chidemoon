<?php
/**
 * Public-catalog authority, completeness, and freshness policy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Kalahamoon_Catalog_Policy {
	private const FRESH_HOURS        = 12;
	private const STALE_HOURS        = 24;
	private const EXPIRE_HOURS       = 72;
	private const FUTURE_TOLERANCE   = 300;
	private const DEFAULT_AUTHORITY  = 'hybrid';
	private const ALLOWED_AUTHORITIES = array( 'remote', 'hybrid', 'local' );

	public static function normalize_authority( $authority ): string {
		$normalized = strtolower( trim( (string) $authority ) );
		return in_array( $normalized, self::ALLOWED_AUTHORITIES, true )
			? $normalized
			: self::DEFAULT_AUTHORITY;
	}

	public static function source_allowed( string $source, string $authority ): bool {
		$authority = self::normalize_authority( $authority );
		$is_local  = 'manual' === strtolower( trim( $source ) );

		if ( 'remote' === $authority ) {
			return ! $is_local;
		}
		if ( 'local' === $authority ) {
			return $is_local;
		}

		return true;
	}

	/**
	 * Evaluate whether a normalized product may be rendered publicly.
	 *
	 * The policy intentionally separates product visibility from price
	 * visibility: a usable product may remain openable after 24 hours while its
	 * unverified price is suppressed. After 72 hours the entire product fails
	 * closed until a successful catalog refresh occurs.
	 *
	 * @return array{publicReady: bool, priceVisible: bool, freshness: string, ageSeconds: int|null, readinessIssues: string[]}
	 */
	public static function evaluate( array $product, ?int $now = null, ?string $authority = null ): array {
		if ( ! empty( $product['catalogProjection'] ) ) {
			// A catalog projection has already been evaluated by Kalahamoon. WordPress
			// adapts its published price-visibility field for old renderers but never
			// repeats source, offer, approval, or freshness eligibility here.
			$ready = ! empty( $product['publicReady'] );
			$price_visible = $ready
				&& ! empty( $product['priceVisible'] )
				&& is_numeric( $product['price'] ?? null )
				&& (float) $product['price'] > 0;
			return array(
				'publicReady'     => $ready,
				'priceVisible'    => $price_visible,
				'freshness'       => (string) ( $product['priceFreshness'] ?? ( $price_visible ? 'visible' : 'hidden_stale' ) ),
				'ageSeconds'      => null,
				'readinessIssues' => is_array( $product['readinessIssues'] ?? null ) ? $product['readinessIssues'] : array(),
			);
		}

		$now       = $now ?? time();
		$authority = self::normalize_authority(
			$authority ?? ( function_exists( 'get_option' ) ? get_option( 'kalahamoon_catalog_authority', self::DEFAULT_AUTHORITY ) : self::DEFAULT_AUTHORITY )
		);
		$issues = array();

		foreach ( array( 'id', 'title', 'imageUrl', 'listingUrl' ) as $required ) {
			if ( '' === trim( (string) ( $product[ $required ] ?? '' ) ) ) {
				$issues[] = 'missing_' . $required;
			}
		}

		if ( ! self::is_https_url( (string) ( $product['imageUrl'] ?? '' ) ) ) {
			$issues[] = 'invalid_image_url';
		}
		if ( ! self::is_https_url( (string) ( $product['listingUrl'] ?? '' ) ) ) {
			$issues[] = 'invalid_listing_url';
		}
		if ( 'VERIFIED' !== strtoupper( trim( (string) ( $product['publicationState'] ?? '' ) ) ) ) {
			$issues[] = 'not_verified';
		}
		if ( 'active' !== strtolower( trim( (string) ( $product['status'] ?? '' ) ) ) ) {
			$issues[] = 'not_active';
		}
		if ( ! self::source_allowed( (string) ( $product['source'] ?? 'synced' ), $authority ) ) {
			$issues[] = 'source_not_allowed';
		}
		if ( ! is_numeric( $product['price'] ?? null ) || (float) $product['price'] <= 0 ) {
			$issues[] = 'missing_price';
		}
		$publication_issues = $product['publicationReadinessIssues'] ?? array();
		if ( is_array( $publication_issues ) && ! empty( $publication_issues ) ) {
			// The cache may receive a listing that changed after approval, so the
			// public surface must fail closed until the upstream review is clean again.
			$issues[] = 'listing_needs_review';
		}

		$freshness_value = (string) ( $product['priceUpdatedAt'] ?? $product['lastSynced'] ?? '' );
		$timestamp       = self::parse_timestamp( $freshness_value );
		$age             = null;
		$freshness       = 'expired';
		$price_visible   = false;

		if ( null === $timestamp ) {
			$issues[] = 'missing_freshness';
		} elseif ( $timestamp > $now + self::FUTURE_TOLERANCE ) {
			$issues[] = 'future_freshness';
		} else {
			$age = max( 0, $now - $timestamp );
			if ( $age <= self::FRESH_HOURS * 3600 ) {
				$freshness     = 'fresh';
				$price_visible = true;
			} elseif ( $age <= self::STALE_HOURS * 3600 ) {
				$freshness     = 'stale';
				$price_visible = true;
			} elseif ( $age <= self::EXPIRE_HOURS * 3600 ) {
				$freshness = 'price_hidden';
			} else {
				$issues[] = 'expired';
			}
		}

		$issues = array_values( array_unique( $issues ) );

		return array(
			'publicReady'    => empty( $issues ),
			'priceVisible'   => $price_visible && ! in_array( 'missing_price', $issues, true ),
			'freshness'      => $freshness,
			'ageSeconds'     => $age,
			'readinessIssues' => $issues,
		);
	}

	public static function apply( array $product, ?int $now = null, ?string $authority = null ): array {
		$policy = self::evaluate( $product, $now, $authority );
		if ( ! $policy['priceVisible'] ) {
			$product['price']    = null;
			$product['oldPrice'] = null;
		}

		$product['publicReady']    = $policy['publicReady'];
		$product['priceVisible']   = $policy['priceVisible'];
		$product['priceFreshness'] = $policy['freshness'];
		$product['readinessIssues'] = $policy['readinessIssues'];

		return $product;
	}

	private static function parse_timestamp( string $value ): ?int {
		if ( '' === trim( $value ) ) {
			return null;
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? null : $timestamp;
	}

	private static function is_https_url( string $url ): bool {
		if ( '' === trim( $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		return 'https' === strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );
	}
}
