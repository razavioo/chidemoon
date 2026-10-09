<?php
if ( 'catalog_test' !== DB_NAME || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Isolated catalog database required' ); }
wp_set_current_user( get_user_by( 'login', 'catalog_test_admin' )->ID );
$product = new WC_Product_Simple(); $product->set_name( 'Legacy identity fixture' ); $product->set_description( '<p>Editorial explanation stays.</p>' ); $product->set_status( 'publish' );
$id = $product->save();
$identity = array( 'provider' => 'generic', 'store' => 'store-a.example', 'product' => wp_generate_uuid4(), 'variant' => null );
$mapping = array( 'productId' => $id, 'identity' => $identity, 'sourceUrl' => 'https://store-a.example/product/' . $identity['product'], 'expectedModifiedAt' => get_post_field( 'post_modified_gmt', $id ), 'approved' => false );
Chidemoon_Core_Reconciliation::reconcile_identity( $mapping, false );
try { Chidemoon_Core_Reconciliation::reconcile_identity( $mapping, true ); throw new RuntimeException( 'Unapproved mapping was accepted' ); }
catch ( RuntimeException $exception ) { if ( 'Identity mapping requires explicit human approval' !== $exception->getMessage() ) { throw $exception; } }
$mapping['approved'] = true;
Chidemoon_Core_Reconciliation::reconcile_identity( $mapping, true );
if ( 'external' !== wc_get_product( $id )->get_type() || 'draft' !== get_post_status( $id ) || '<p>Editorial explanation stays.</p>' !== get_post_field( 'post_content', $id ) || $id !== Chidemoon_Core_Source_Importer::resolve( Chidemoon_Core_Source_Contract::source_key( $identity ) ) ) { throw new RuntimeException( 'Identity reconciliation failed: ' . wp_json_encode(array('type'=>wc_get_product($id)->get_type(),'status'=>get_post_status($id),'content'=>get_post_field('post_content',$id),'resolved'=>Chidemoon_Core_Source_Importer::resolve(Chidemoon_Core_Source_Contract::source_key($identity)),'id'=>$id)) ); }
Chidemoon_Core_Reconciliation::rollback_identity( $id, true );
if ( 'simple' !== wc_get_product( $id )->get_type() || 'publish' !== get_post_status( $id ) || get_post_meta( $id, Chidemoon_Core_Source_Importer::META_IDENTITY, true ) ) { throw new RuntimeException( 'Identity rollback failed: ' . wp_json_encode(array('type'=>wc_get_product($id)->get_type(),'status'=>get_post_status($id),'identity'=>get_post_meta($id,Chidemoon_Core_Source_Importer::META_IDENTITY,true),'backup'=>get_post_meta($id,'_chidemoon_identity_migration_backup_v2',true))) ); }
wp_delete_post( $id, true );
echo "PASS: explicit identity approval, original product IDs, external drafts and reversible conversion\n";
