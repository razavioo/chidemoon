<?php
define( 'ABSPATH', __DIR__ );
function __( $value, $domain = '' ) { return $value; }
class WP_Error { public function __construct( public string $code, public string $message, public array $data = array() ) {} }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function current_user_can( $cap ) { return $GLOBALS['allowed'] ?? true; }
function term_exists( $id, $taxonomy ) { return 12 === $id && 'product_cat' === $taxonomy; }
function wc_get_product( $id ) { return $GLOBALS['existing'] ?? null; }
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-source-importer.php';
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-enrichment.php';
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-commercial.php';
class DraftProduct {
 public array $meta = array(); public string $status = 'publish';
 public function __construct( public string $type = 'simple', public int $id = 0 ) {}
 public function get_id() { return $this->id; }
 public function is_type( $type ) { return $type === $this->type; }
 public function get_meta( $key, $single = true ) { return $this->meta[ $key ] ?? ''; }
 public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
 public function set_status( $status ) { $this->status = $status; }
}
class DraftRequest {
 public function __construct( public array $params ) {}
 public function get_param( $key ) { return $this->params[ $key ] ?? null; }
 public function get_params() { return $this->params; }
}
function check_draft( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
$product = new DraftProduct();
$request = new DraftRequest( array( 'name' => 'Manual product', 'meta_data' => array( array( 'key' => Chidemoon_Core_Enrichment::DRAFT, 'value' => array( 'category' => array(12,12) ) ) ) ) );
Chidemoon_Core_Enrichment::receive_classification_draft( $product, $request, true );
check_draft( 'draft' === $product->status && array(12) === $product->meta[Chidemoon_Core_Enrichment::DRAFT]['category'], 'Suggestions stay drafts and deduplicate terms' );
check_draft( ! isset( $product->meta[Chidemoon_Core_Enrichment::META] ), 'Suggestions cannot approve enrichment' );
check_draft( $product === Chidemoon_Core_Commercial::protect_source_write( $product, $request, true ), 'Ordinary products can be created' );
check_draft( is_wp_error( Chidemoon_Core_Commercial::protect_source_write( new DraftProduct('external'), $request, true ) ), 'External offers require source profile' );
$GLOBALS['existing'] = new DraftProduct('external', 9);
check_draft( is_wp_error( Chidemoon_Core_Commercial::protect_source_write( new DraftProduct('simple',9), $request, false ) ), 'Changing the type cannot bypass source protection' );
$GLOBALS['allowed'] = false;
check_draft( is_wp_error( Chidemoon_Core_Enrichment::receive_classification_draft( $product, $request, true ) ), 'Unauthorized draft writes are rejected' );
$GLOBALS['allowed'] = true;
$request->params['meta_data'][0]['value'] = array('style'=>array(12));
check_draft( is_wp_error( Chidemoon_Core_Enrichment::receive_classification_draft( $product, $request, true ) ), 'Terms from another vocabulary are rejected' );
$request->params['meta_data'][0]['value'] = array('price'=>array(12));
check_draft( is_wp_error( Chidemoon_Core_Enrichment::receive_classification_draft( $product, $request, true ) ), 'Source fields cannot be proposed as classification' );
echo "8 classification draft checks passed\n";
