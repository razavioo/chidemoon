<?php
if ( ! defined( 'DB_NAME' ) || 'catalog_test' !== DB_NAME || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Isolated catalog database required' ); }
function migration_check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$admin = get_user_by( 'login', 'catalog_test_admin' ); wp_set_current_user( $admin->ID );
$fixture = get_option( 'chidemoon_catalog_fixture' ); $product_id = $fixture['productId']; $cover = get_post_thumbnail_id( $product_id );
$spots = array( array( 'productId' => $product_id, 'x' => 0, 'y' => 100 ), array( 'x' => 60, 'y' => 20 ) );
$shortcode = '[chidemoon_shop_the_look image_id="' . $cover . '" hotspots="' . base64_encode( wp_json_encode( $spots ) ) . '"]';
$block = '<!-- wp:chidemoon/shop-the-look ' . wp_json_encode( array( 'imageId' => $cover, 'hotspots' => array( $spots[0] ) ) ) . ' /-->';
$original = '<p>Editorial content preserved.</p>' . $shortcode . $block;
$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Migration fixture ' . wp_generate_uuid4(), 'post_content' => $original ) );
$url = get_permalink( $id );
update_option( 'chidemoon_migration_fixture', array( 'pageId' => $id, 'url' => $url, 'content' => $original ) );
$elements = array( array( 'id' => 'testwrap', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'sceneone', 'elType' => 'widget', 'widgetType' => 'chidemoon-shop-the-look', 'settings' => array( 'image' => array( 'id' => $cover, 'url' => wp_get_attachment_url( $cover ) ), 'hotspots' => array( array( 'product_id' => $product_id, 'x' => array( 'size' => 20 ), 'y' => array( 'size' => 30 ) ) ), 'border_radius' => array( 'size' => 8 ) ), 'elements' => array() ) ) ) );
$elementor_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Elementor migration fixture ' . $id ) );
$elements[0]['elements'][] = array( 'id' => 'persiancopy', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => "\u{062e}\u{0627}\u{0646}\u{0647}\u{200c}\u{0627}\u{06cc}" ), 'elements' => array() );
update_post_meta( $elementor_id, '_elementor_edit_mode', 'builder' );
$document = \Elementor\Plugin::$instance->documents->get( $elementor_id );
migration_check( false !== $document->save( array( 'elements' => $elements ) ), 'Elementor fixture creation failed' );
$fixture = get_option( 'chidemoon_migration_fixture' ); $fixture['elementorId'] = $elementor_id; $fixture['elementor'] = get_post_meta( $elementor_id, '_elementor_data', true );
update_option( 'chidemoon_migration_fixture', $fixture );
echo "PASS: migration fixtures created\n";
