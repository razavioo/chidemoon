<?php
define( 'ABSPATH', __DIR__ );
function __( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function wp_list_pluck( $items, $key ) { return array_column( $items, $key ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_get_object_terms( $id, $taxonomy ) { return $GLOBALS['terms'][ $taxonomy ] ?? array(); }
class WP_Error {}
class WC_Product { public function get_id() { return 12; } }
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-enrichment.php';
function check( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
$product = new WC_Product();
$GLOBALS['terms'] = array( 'chidemoon_style' => array( (object) array( 'name' => 'مدرن <script>' ) ), 'chidemoon_room' => array( (object) array( 'name' => 'نشیمن' ) ) );
$tabs = Chidemoon_Core_Enrichment::classification_tab( array() );
check( isset( $tabs['additional_information'] ), 'Style/room-only product must have a specifications tab' );
$rows = Chidemoon_Core_Enrichment::display_attributes( array( 'native' => array( 'label' => 'Color', 'value' => 'Red' ) ), $product );
check( isset( $rows['native'], $rows['chidemoon_style'], $rows['chidemoon_room'] ), 'Native and editorial specifications must coexist' );
check( ! str_contains( $rows['chidemoon_style']['value'], '<script>' ), 'Term labels must be escaped' );
$existing = array( 'additional_information' => array( 'title' => 'Custom' ) );
check( $existing === Chidemoon_Core_Enrichment::classification_tab( $existing ), 'Preserve existing specifications tabs' );
$GLOBALS['terms'] = array( 'chidemoon_style' => new WP_Error() );
check( array() === Chidemoon_Core_Enrichment::classification_tab( array() ), 'Empty or failed lookups must not create empty tabs' );
echo "PASS: editorial specifications, native rows, escaping, existing tabs and empty lookups\n";
