<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

class WP_Query {
	public array $settings = array();
	public function set( string $key, $value ): void { $this->settings[ $key ] = $value; }
	public function get( string $key ) { return $this->settings[ $key ] ?? null; }
}
class Chidemoon_Core_Enrichment {
	public static function read( int $id ): array { return array( 'category' => array( 'value' => array( 10 ) ) ); }
}
class Chidemoon_Core_Discovery {
	public static int $calls = 0;
	public static bool $fail = false;
	public static function related_product_ids( int $id ): array {
		if ( ++self::$calls > 1 ) { throw new RuntimeException( 'Nested ranking recursively entered the Elementor filter.' ); }
		$lookup = new WP_Query();
		$lookup->set( 'fields', 'ids' );
		$before = $lookup->settings;
		// Elementor invokes the same custom-query action for the ranking WP_Query.
		Chidemoon_Core_Public_Design::related_products_query( $lookup );
		if ( $before !== $lookup->settings ) { throw new RuntimeException( 'Outer widget filters changed the candidate lookup.' ); }
		if ( self::$fail ) { throw new RuntimeException( 'ranking failed' ); }
		return array( 12, 14 );
	}
}
function get_queried_object_id(): int { return 8; }
function get_post_type( int $id ): string { return 'product'; }
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-public-design.php';
function check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }

$query = new WP_Query();
$query->set( 'post__in', array( 14, 18 ) );
Chidemoon_Core_Public_Design::related_products_query( $query );
check( array( 14 ) === $query->get( 'post__in' ), 'Ranked products must respect the widget inclusion list.' );
check( 'post__in' === $query->get( 'orderby' ), 'The outer widget retains the ranking order.' );

Chidemoon_Core_Discovery::$calls = 0;
Chidemoon_Core_Discovery::$fail = true;
try {
	Chidemoon_Core_Public_Design::related_products_query( new WP_Query() );
	throw new RuntimeException( 'Expected the ranking failure.' );
} catch ( RuntimeException $error ) {
	check( 'ranking failed' === $error->getMessage(), 'Unexpected lookup error.' );
}
Chidemoon_Core_Discovery::$calls = 0;
Chidemoon_Core_Discovery::$fail = false;
$next = new WP_Query();
Chidemoon_Core_Public_Design::related_products_query( $next );
check( array( 12, 14 ) === $next->get( 'post__in' ), 'A failed lookup must not disable filtering for later widgets.' );
echo "Related-product ranking reentrancy checks passed.\n";
