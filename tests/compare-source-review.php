<?php
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
class WC_Product {
 public function __construct( public array $meta = array() ) {}
 public function get_id(): int { return 42; }
 public function get_meta( string $key, bool $single = true ) { return $this->meta[ $key ] ?? ''; }
}
class Chidemoon_Core_Affiliate { public const META_FACTS = '_facts'; }
class Chidemoon_Core_Source_Importer { public const META_IDENTITY = '_identity'; }
class Chidemoon_Core_Commercial {
 public static function observations( WC_Product $product ): array { return $GLOBALS['observations']; }
 public static function availability_label( WC_Product $product ): string { return 'Unavailable at merchant'; }
}
function __( string $value, string $domain ): string { return $value; }
function sanitize_text_field( string $value ): string { return strip_tags( $value ); }
function get_post_meta( int $id, string $key, bool $single = true ) { return $GLOBALS['product']->get_meta( $key ); }
function get_term( int $id, string $taxonomy ) { return (object) array( 'name' => 'Approved ' . $taxonomy ); }
function is_wp_error( $value ): bool { return false; }
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-enrichment.php';
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-compare.php';
function check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$GLOBALS['observations'] = array( 'availability' => array( 'status' => 'out_of_stock', 'observedAt' => '2026-10-10T08:00:00Z' ) );
$product = new WC_Product( array( '_identity' => array( 'product' => 'source-42' ), '_facts' => '[{"label":"Model","value":"EN101"}]' ) );
$GLOBALS['product'] = $product;
$facts = Chidemoon_Core_Compare::facts( $product );
check( 'EN101' === $facts['Model'], 'Legacy editorial facts were removed before approval' );
check( 'Unknown' === $facts['Width (cm)'] && 'Unknown' === $facts['Style'], 'Pending measurements or classification were invented' );
check( 'Unavailable at merchant' === $facts['Availability'] && '2026-10-10T08:00:00Z' === $facts['Availability observed'], 'Availability requires enrichment approval' );
check( 'Unknown' === $facts['Price observed'] && 'Unavailable' === $facts['Merchant rating'], 'Missing price or rating was invented' );
check( array( 'Model' => 'EN101' ) === Chidemoon_Core_Compare::visible_facts( $product ), 'Public specifications expose missing values or operational observations' );
$product->meta['_facts'] = 'invalid legacy JSON';
check( 'Unavailable at merchant' === Chidemoon_Core_Compare::facts( $product )['Availability'], 'Malformed legacy facts hide source observations' );
$product->meta['_chidemoon_enrichment'] = array( 'style' => array( 'state' => 'value', 'value' => array( 7 ) ), 'dimensions' => array( 'state' => 'value', 'unit' => 'cm', 'value' => array( 'width' => 45 ) ) );
$facts = Chidemoon_Core_Compare::facts( $product );
check( '45' === $facts['Width (cm)'] && 'Unknown' === $facts['Depth (cm)'] && 'Approved chidemoon_style' === $facts['Style'], 'Approved normalized fields were ignored' );
$visible = Chidemoon_Core_Compare::visible_facts( $product );
check( '45' === $visible['Width (cm)'] && ! isset( $visible['Depth (cm)'] ) && ! isset( $visible['Availability observed'] ) && ! isset( $visible['Merchant rating'] ), 'Public comparison lost measured values or retained empty fields' );
$product->meta = array( '_facts' => '[{"label":"Model","value":"Legacy"}]' );
check( array( 'Model' => 'Legacy' ) === Chidemoon_Core_Compare::facts( $product ), 'Unmapped legacy comparison changed' );
echo "Comparison source review contract passed.\n";
