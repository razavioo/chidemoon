<?php
/** Regression: category relationships and the cached Woo product must agree. */
define( 'ABSPATH', __DIR__ );
function __( $text, $domain ) { return $text; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function term_exists( $slug, $taxonomy ) { return array( 'term_id' => 42 ); }
function is_wp_error( $value ) { return false; }
function wp_set_object_terms( $id, $terms, $taxonomy, $append ) {
	if ( 'product_cat' === $taxonomy ) { $GLOBALS['categories'] = $terms; }
}
class WC_Product {
	public array $categories = array( 15 );
	public function __call( $name, $args ) {}
	public function set_category_ids( $ids ) { $this->categories = $ids; }
	public function save() { $GLOBALS['categories'] = $this->categories; return 123; }
}
class WC_Product_External extends WC_Product {}
function wc_get_product( $id ) { return $GLOBALS['product']; }
class Chidemoon_Core_Affiliate {
	const META_SOURCE_KEY = 'source';
	const META_AFFILIATE_URL = 'url';
	const META_MERCHANT_NAME = 'merchant';
	const META_SOURCE_URL = 'source_url';
	const META_SOURCE_CHECKED = 'checked';
	const META_REVIEW_STATE = 'state';
	const META_FACTS = 'facts';
}
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-importer.php';
$method = new ReflectionMethod( Chidemoon_Core_Importer::class, 'upsert_product' );
$record = array( 'title' => 'Lamp', 'sourceKey' => 'fixture', 'description' => '', 'shortDescription' => '', 'price' => 100, 'affiliateUrl' => 'https://example.com/lamp', 'merchantName' => 'Shop', 'sourceUrl' => 'https://example.com/lamp', 'sourceCheckedAt' => '', 'facts' => array(), 'currency' => 'IRT', 'categories' => array( array( 'name' => 'Lamps', 'slug' => 'lamps' ) ) );
$GLOBALS['product'] = new WC_Product_External();
$method->invoke( null, $record, array( 123 ) );
if ( array( 42 ) !== $GLOBALS['product']->categories ) { throw new RuntimeException( 'Cached product kept the default category.' ); }
$GLOBALS['product']->save();
if ( array( 42 ) !== $GLOBALS['categories'] ) { throw new RuntimeException( 'A later product save discarded imported categories.' ); }
echo "Imported categories survive subsequent WooCommerce saves.\n";
