<?php
if ( ! defined( 'DB_NAME' ) || 'catalog_test' !== DB_NAME || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Isolated catalog database required' ); }
function classification_check( bool $ok, string $message ): void { if ( ! $ok ) { throw new RuntimeException( $message ); } }
$product = new WC_Product_External();
$product->set_name( 'Classification editor fixture' );
$native = new WC_Product_Attribute(); $native->set_name( 'Native specification' ); $native->set_options( array( 'Preserved' ) ); $native->set_visible( true );
$product->set_attributes( array( $native ) );
$id = $product->save();
$post = get_post( $id );
$initial_token = Chidemoon_Core_Enrichment::token( $id );
$_POST = array( 'chidemoon_enrichment_nonce' => wp_create_nonce( 'chidemoon_enrichment_' . $id ), 'chidemoon_enrichment_revision' => $initial_token, 'chidemoon_enrichment' => array() );
foreach ( array( 'style' => 'سبک آزمایشی اول،سبک آزمایشی دوم', 'room' => 'فضای آزمایشی', 'color' => 'رنگ آزمایشی', 'features' => 'کاربرد آزمایشی' ) as $field => $names ) { $_POST['chidemoon_enrichment'][ $field ] = array( 'state' => '', 'new_terms' => $names ); }
Chidemoon_Core_Enrichment::save_editor( $id, $post );
$fields = Chidemoon_Core_Enrichment::read( $id );
classification_check( 2 === count( $fields['style']['value'] ?? array() ), 'New multi-value style was not persisted' );
foreach ( array( 'room', 'color', 'features' ) as $field ) { classification_check( 1 === count( $fields[ $field ]['value'] ?? array() ), 'New field was not persisted: ' . $field ); }
$product = wc_get_product( $id );
classification_check( isset( $product->get_attributes()['pa_color'], $product->get_attributes()['pa_features'], $product->get_attributes()['native-specification'] ), 'Visible WooCommerce attributes or native specifications were lost' );
$rows = Chidemoon_Core_Enrichment::display_attributes( array(), $product );
classification_check( isset( $rows['chidemoon_style'], $rows['chidemoon_room'] ), 'Editorial values are missing from public specifications' );
$_POST['chidemoon_enrichment_revision'] = Chidemoon_Core_Enrichment::token( $id );
$_POST['chidemoon_enrichment'] = array( 'style' => array( 'state' => 'value', 'value' => array( $fields['style']['value'][1] ) ), 'color' => array( 'state' => 'unknown' ) );
Chidemoon_Core_Enrichment::save_editor( $id, $post );
$edited = Chidemoon_Core_Enrichment::read( $id );
classification_check( array( $fields['style']['value'][1] ) === $edited['style']['value'], 'Editing selected values failed' );
classification_check( 'unknown' === $edited['color']['state'] && ! wp_get_object_terms( $id, 'pa_color' ), 'Clearing a reviewed attribute failed' );
classification_check( ! isset( wc_get_product( $id )->get_attributes()['pa_color'] ), 'Cleared attribute must not leave an empty specifications row' );
$_POST['chidemoon_enrichment_revision'] = $initial_token;
$_POST['chidemoon_enrichment'] = array( 'style' => array( 'state' => '', 'new_terms' => 'Stale editor must not create this term' ) );
Chidemoon_Core_Enrichment::save_editor( $id, $post );
classification_check( ! term_exists( 'Stale editor must not create this term', 'chidemoon_style' ) && $edited === Chidemoon_Core_Enrichment::read( $id ), 'Stale editor changed classification or vocabulary' );
$_POST = array();
echo "PASS: new product fields, inline vocabulary, multiple values, edits, clearing, native attributes, public specifications and stale-editor protection\n";
