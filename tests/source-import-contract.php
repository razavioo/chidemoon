<?php
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_parse_url( $value, $component = -1 ) { return parse_url( $value, $component ); }
function esc_url_raw( $value, $protocols = array() ) { return $value; }
function sanitize_text_field( $value ) { return strip_tags( $value ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_kses_post( $value ) { return $value; }
function get_woocommerce_currency() { return 'IRT'; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function wc_get_product_id_by_sku( $sku ) { return 0; }
function wc_get_product( $id ) { return $GLOBALS['product']; }
function get_post_meta( $id, $key, $single = true ) { return 123 === (int) $id ? ( $GLOBALS['product']->meta[ $key ] ?? '' ) : ''; }
function get_posts( $query ) { return in_array( $query['meta_query'][0]['value'] ?? '', array( 'kalahamoon:crm-product:base', $GLOBALS['fixture']['sourceKey'] ?? '' ), true ) ? array( 123 ) : array(); }
class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WC_Product {
 public array $meta = array( '_chidemoon_review_state' => 'reviewed', '_chidemoon_product_facts' => 'approved facts' );
 public array $values = array( 'description' => '<p>Editorial Elementor explanation</p>', 'categories' => array( 42 ), 'attributes' => array( 'pa_color' => 'blue' ), 'status' => 'publish' );
 public function get_meta( $key, $single = true ) { return $this->meta[ $key ] ?? ''; }
 public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
 public function delete_meta_data( $key ) { unset( $this->meta[ $key ] ); }
 public function is_type( $type ) { return 'external' === $type; }
 public function get_id() { return 123; }
 public function save() { return 123; }
 public function __call( $method, $args ) { $this->values[ substr( $method, 4 ) ] = $args[0] ?? null; }
}
class WC_Product_External extends WC_Product {}
class FakeDatabase {
 public string $prefix = 'wp_';
 public function prepare( $query, ...$params ) { return array( $query, $params ); }
 public function get_var( $query ) {
  if ( str_contains( $query[0], 'GET_LOCK' ) || str_contains( $query[0], 'RELEASE_LOCK' ) ) { return '1'; }
  if ( str_contains( $query[0], 'SELECT product_id' ) ) { return 123; }
  if ( str_contains( $query[0], 'SELECT source_key' ) ) { return $GLOBALS['fixture']['sourceKey']; }
  return null;
 }
}
$GLOBALS['wpdb'] = new FakeDatabase();
$GLOBALS['product'] = new WC_Product_External();
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-affiliate.php';
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-importer.php';
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-source-contract.php';
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-source-importer.php';
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/source-v2.json' ), true, 512, JSON_THROW_ON_ERROR );
$GLOBALS['fixture'] = $fixture;
check( $fixture['sourceKey'] === Chidemoon_Core_Source_Contract::source_key( $fixture['identity'] ), 'Cross-language source key differs' );
check( $fixture === Chidemoon_Core_Source_Contract::validate_record( $fixture ), 'Contract fixture differs' );
check( '125000' === Chidemoon_Core_Source_Importer::convert_price( '1250000', 'IRR', 'IRT' ), 'Rial conversion incorrect' );
check( '1.2345' === Chidemoon_Core_Source_Importer::convert_price( '12.345', 'IRR', 'IRT' ), 'Decimal shift incorrect' );
check( '123.45' === Chidemoon_Core_Source_Importer::convert_price( '12.345', 'IRT', 'IRR' ), 'Toman conversion incorrect' );
check( Chidemoon_Core_Source_Contract::compare_decimals( '9999999999999999.2', '9999999999999999.1' ) > 0, 'Decimal price comparison lost precision' );
$snapshot = array( 'version' => 'v2', 'organization' => array( 'id' => 'org', 'slug' => 'chidemoon' ), 'snapshot' => array( 'id' => 'snapshot', 'revision' => str_repeat( 'a', 64 ), 'generatedAt' => '2026-10-09T08:00:00Z' ), 'items' => array( $fixture ), 'withdrawals' => array() );
// Media downloads are covered by the real WooCommerce fixture. This fixture verifies ownership without network IO.
unset( $snapshot['items'][0]['content']['images'] );
$result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
check( 'imported' === $result['records'][0]['status'], 'Source import failed' );
$product = $GLOBALS['product'];
check( 'publish' === $product->values['status'], 'Commercial import reset publication' );
check( '<p>Editorial Elementor explanation</p>' === $product->values['description'], 'Editorial content overwritten' );
check( array( 42 ) === $product->values['categories'] && array( 'pa_color' => 'blue' ) === $product->values['attributes'], 'Taxonomy overwritten' );
check( 'reviewed' === $product->meta['_chidemoon_review_state'] && 'approved facts' === $product->meta['_chidemoon_product_facts'], 'Review or facts overwritten' );
check( '150000' === $product->values['regular_price'] && '125000' === $product->values['sale_price'], 'Sale mapping incorrect' );
$snapshot['items'][0]['revision'] = 2;
$snapshot['items'][0]['observations']['price']['previous'] = null;
$snapshot['items'][0]['observations']['availability']['status'] = 'out_of_stock';
$result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
check( '' === $product->values['sale_price'], 'Ended sale remains active' );
$snapshot['items'][0]['revision'] = 3;
unset( $snapshot['items'][0]['observations']['price'] );
$snapshot['items'][0]['observations']['availability']['observedAt'] = '2026-10-09T09:00:00Z';
Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
check( '125000' === $product->values['regular_price'], 'Missing price deleted prior value' );
$observations = json_decode( $product->meta['_chidemoon_source_observations'], true );
check( '2026-10-09T08:00:00.000Z' === $observations['price']['observedAt'], 'Price freshness invented' );
check( '2026-10-09T09:00:00Z' === $observations['availability']['observedAt'], 'Availability not updated independently' );
$snapshot['items'][0]['revision'] = 2;
$result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
check( 'stale_revision' === $result['records'][0]['issues'][0], 'Stale source revision accepted' );
$snapshot['items'] = array();
$snapshot['withdrawals'] = array( array( 'sourceKey' => $fixture['sourceKey'], 'revision' => 4 ) );
Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
check( '1' === $product->meta['_chidemoon_source_withdrawn'] && 'approved facts' === $product->meta['_chidemoon_product_facts'], 'Withdrawal lost editorial data' );
check( 4 === get_option( 'chidemoon_source_withdrawal_' . $fixture['sourceKey'] ), 'Withdrawal was not durably retained' );
$snapshot['withdrawals'] = array(); $snapshot['items'] = array( $fixture );
check( 'stale_revision' === Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' )['records'][0]['issues'][0], 'A delayed snapshot reactivated a withdrawn offer' );
echo "Source contract, ownership, conversion, freshness, revision and withdrawal checks passed.\n";
