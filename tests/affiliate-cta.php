<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

class WC_Product {
	public function __construct( private int $id, private string $review_state = 'reviewed' ) {}
	public function get_id(): int { return $this->id; }
	public function is_type( string $type ): bool { return 'external' === $type; }
	public function get_meta( string $key, bool $single = true ): string {
		return match ( $key ) {
			Chidemoon_Core_Affiliate::META_REVIEW_STATE => $this->review_state,
			Chidemoon_Core_Affiliate::META_AFFILIATE_URL => 'https://seller.example/product',
			default => '',
		};
	}
}

function get_post_status( int $id ): string { return 'publish'; }
function get_permalink( int $id ): string { return '/product/' . $id . '/'; }
function home_url( string $path ): string { return $path; }
function wp_parse_url( string $url ) { return parse_url( $url ); }
function esc_url_raw( string $url, array $protocols ): string { return $url; }

require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-affiliate.php';

function check( bool $condition, string $message ): void {
	if ( ! $condition ) throw new RuntimeException( $message );
}

$reviewed = new WC_Product( 10 );
$unreviewed = new WC_Product( 11, 'draft' );
check( '/go/10/' === Chidemoon_Core_Affiliate::use_tracking_url_for_product_cta( 'https://seller.example/product', $reviewed ), 'Reviewed offer uses tracked seller destination.' );
check( '_blank' === Chidemoon_Core_Affiliate::open_product_cta_in_new_tab( array(), $reviewed )['attributes']['target'], 'Reviewed offer opens the seller in a new tab.' );
check( '/product/11/' === Chidemoon_Core_Affiliate::use_tracking_url_for_product_cta( 'https://seller.example/product', $unreviewed ), 'Unreviewed offer opens its detail page rather than a rejected redirect.' );
check( array() === Chidemoon_Core_Affiliate::open_product_cta_in_new_tab( array(), $unreviewed ), 'Unreviewed detail destination stays in the current tab.' );

echo "Affiliate archive CTA checks passed.\n";
