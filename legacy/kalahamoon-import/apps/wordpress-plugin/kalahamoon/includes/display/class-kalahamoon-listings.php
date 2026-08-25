<?php
/**
 * Cross-marketplace listing helpers for the price-comparison buy-box.
 *
 * Pure data transforms (no WP function calls) so they can be unit-tested in
 * isolation and reused by the block render template.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Listings {
	private const PUBLIC_PRICE_HOURS = 24;
	private const FUTURE_TOLERANCE   = 300;

	/**
	 * Keep only listings whose publication, availability, destination, and
	 * price timestamp are safe enough to expose in a public buy-box.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function normalize_public( array $product, ?int $now = null ): array {
		if ( ! empty( $product['catalogProjection'] ) ) {
			// Offers in a projection were selected and price-filtered upstream. This
			// renderer only normalizes their display order; it must not become a
			// second eligibility evaluator with a different freshness threshold.
			return self::normalize( $product );
		}

		$now      = $now ?? time();
		$listings = is_array( $product['listings'] ?? null ) ? $product['listings'] : array();
		$eligible = array();

		foreach ( $listings as $listing ) {
			if ( ! is_array( $listing ) ) {
				continue;
			}
			$timestamp_value = (string) ( $listing['lastSyncedAt'] ?? $listing['updatedAt'] ?? '' );
			$timestamp       = '' !== trim( $timestamp_value ) ? strtotime( $timestamp_value ) : false;
			$is_fresh        = false !== $timestamp
				&& $timestamp <= $now + self::FUTURE_TOLERANCE
				&& $timestamp >= $now - self::PUBLIC_PRICE_HOURS * 3600;

			if (
				'VERIFIED' !== strtoupper( trim( (string) ( $listing['publicationState'] ?? '' ) ) )
				|| 'ACTIVE' !== strtoupper( trim( (string) ( $listing['status'] ?? '' ) ) )
				|| ! $is_fresh
				|| ! self::is_public_https_url( (string) ( $listing['listingUrl'] ?? $listing['url'] ?? '' ) )
			) {
				continue;
			}
			$eligible[] = $listing;
		}

		$product['listings'] = $eligible;
		return self::normalize( $product );
	}

	/**
	 * Normalize a product's `listings[]` into comparison rows sorted cheapest
	 * first. Each row: platform, price (float), currency, seller, url, inStock,
	 * cheapest (bool). Listings with a non-positive price are dropped.
	 *
	 * @param array $product Formatted product (Kalahamoon_Product_Cache::format_product).
	 * @return array<int,array<string,mixed>>
	 */
	public static function normalize( array $product ): array {
		$listings = $product['listings'] ?? array();
		if ( ! is_array( $listings ) || empty( $listings ) ) {
			return array();
		}

		$rows = array();
		foreach ( $listings as $listing ) {
			if ( ! is_array( $listing ) ) {
				continue;
			}

			$price = isset( $listing['price'] ) ? (float) $listing['price'] : 0.0;
			if ( $price <= 0 ) {
				continue;
			}

			$url = (string) ( $listing['listingUrl'] ?? $listing['url'] ?? '' );

			$rows[] = array(
				'platform'  => strtolower( (string) ( $listing['platform'] ?? '' ) ),
				'price'     => $price,
				'currency'  => (string) ( $listing['currency'] ?? $product['currency'] ?? 'IRR' ),
				'seller'    => (string) ( $listing['sellerName'] ?? '' ),
				'url'       => $url,
				'inStock'   => (int) ( $listing['inventory'] ?? 0 ) > 0,
				'cheapest'  => false,
			);
		}

		if ( empty( $rows ) ) {
			return array();
		}

		usort( $rows, static function ( $a, $b ) {
			return $a['price'] <=> $b['price'];
		} );

		// Flag every row sharing the minimum price as cheapest.
		$min = $rows[0]['price'];
		foreach ( $rows as &$row ) {
			$row['cheapest'] = ( abs( $row['price'] - $min ) < 0.01 );
		}
		unset( $row );

		return $rows;
	}

	private static function is_public_https_url( string $url ): bool {
		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) {
			return false;
		}
		$host = strtolower( trim( (string) ( $parts['host'] ?? '' ), '[]' ) );
		if ( '' === $host || 'localhost' === $host || str_ends_with( $host, '.localhost' ) || str_ends_with( $host, '.local' ) ) {
			return false;
		}
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false !== filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}
		return true;
	}
}
