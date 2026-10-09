<?php
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
function description_check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_current_user( $admin->ID );
update_option( 'elementor_cpt_support', array( 'post', 'page', 'product', 'chidemoon_look' ) );
add_post_type_support( 'product', 'elementor' );
update_option( 'woocommerce_currency', 'IRR' );
$record = json_decode( file_get_contents( '/tests/fixtures/source-v2.json' ), true );
$record['identity']['product'] = 'description-' . wp_generate_uuid4();
$record['sourceKey'] = Chidemoon_Core_Source_Contract::source_key( $record['identity'] );
$record['aliases'] = array( 'description-fixture:' . $record['identity']['product'] );
$record['content']['description'] = '<p>Original merchant description</p>';
foreach ( $record['observations'] as &$observation ) { $observation['observedAt'] = gmdate( 'c' ); } unset( $observation );
$file = wp_upload_bits( 'description-fixture.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQ0AAAAASUVORK5CYII=' ) );
$image = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Description fixture', 'post_status' => 'inherit' ), $file['file'] );
update_post_meta( $image, '_chidemoon_import_image_url', $record['content']['images'][0] );
$product = new WC_Product_External();
$product->set_name( 'Description migration fixture' ); $product->set_status( 'draft' );
$product->set_description( $record['content']['description'] ); $product->set_image_id( $image );
$product->update_meta_data( Chidemoon_Core_Affiliate::META_SOURCE_KEY, $record['aliases'][0] );
$id = $product->save();
update_post_meta( $id, '_elementor_edit_mode', 'builder' );
$document = \Elementor\Plugin::$instance->documents->get( $id );
$elements = array( array( 'id' => 'descwrap', 'elType' => 'container', 'settings' => array(), 'elements' => array(
 array( 'id' => 'merchant', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => $record['content']['description'] ), 'elements' => array() ),
 array( 'id' => 'editorial', 'elType' => 'widget', 'widgetType' => 'text-editor', 'settings' => array( 'editor' => '<p>Preserved Chidemoon explanation</p>' ), 'elements' => array() )
) ) );
$snapshot = array( 'version' => 'v2', 'organization' => array( 'id' => 'fixture-org', 'slug' => 'chidemoon' ), 'snapshot' => array( 'id' => wp_generate_uuid4(), 'revision' => str_repeat( 'a', 64 ), 'generatedAt' => gmdate( 'c' ) ), 'items' => array( $record ), 'withdrawals' => array() );
$result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
description_check( 'imported' === $result['records'][0]['status'], wp_json_encode( $result ) );
description_check( '[chidemoon_source_description]' === wc_get_product( $id )->get_description(), 'Copied description was not migrated to dynamic data' );
description_check( '<p>Original merchant description</p>' === get_post_meta( $id, Chidemoon_Core_Reconciliation::BACKUP, true )['content'], 'Original source description backup missing' );
description_check( false !== $document->save( array( 'elements' => $elements ) ), 'Elementor fixture save failed' );
$snapshot['items'][0]['revision']++; $snapshot['items'][0]['contentRevision']++;
$snapshot['items'][0]['content']['description'] = '<p>Updated merchant description</p>';
$result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
description_check( 'imported' === $result['records'][0]['status'], 'Description refresh failed' );
$saved = json_decode( get_post_meta( $id, '_elementor_data', true ), true );
description_check( '[chidemoon_source_description]' === $saved[0]['elements'][0]['settings']['editor'], 'Copied Elementor description remained static' );
description_check( '<p>Preserved Chidemoon explanation</p>' === $saved[0]['elements'][1]['settings']['editor'], 'Editorial explanation changed' );
description_check( '<p>Updated merchant description</p>' === wc_get_product( $id )->get_meta( Chidemoon_Core_Source_Importer::META_DESCRIPTION, true ), 'Dynamic source data did not refresh' );
description_check( ! str_contains( get_post_field( 'post_content', $id ), 'Original merchant description' ), 'Generated Elementor content retained the obsolete source description' );
description_check( '' === Chidemoon_Core_Commercial::source_description( array( 'product_id' => $id ) ), 'Unapproved draft source content became public' );
echo wp_json_encode( array( 'descriptionMigration' => 'passed', 'elementorEditorialPreserved' => true, 'updatedSourceStored' => true, 'publicationGatePreserved' => true, 'backupPreserved' => true ) ) . "\n";
