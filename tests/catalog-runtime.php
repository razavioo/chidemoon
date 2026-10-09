<?php
// Run with wp eval-file only in the isolated catalog integration database.
if ( ! defined( 'DB_NAME' ) || 'catalog_test' !== DB_NAME || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'This fixture requires the designated isolated catalog database' ); }
switch_to_locale( 'en_US' );
function catalog_check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$administrator = get_user_by( 'login', 'catalog_test_admin' );
wp_set_current_user( $administrator->ID );
update_option( 'woocommerce_currency', 'IRT' );
update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();
$record = json_decode( file_get_contents( '/tests/fixtures/source-v2.json' ), true, 512, JSON_THROW_ON_ERROR );
$record['identity']['product'] = 'runtime-' . wp_generate_uuid4();
$record['sourceKey'] = Chidemoon_Core_Source_Contract::source_key( $record['identity'] );
$record['aliases'] = array( 'fixture:' . $record['identity']['product'] );
foreach ( $record['observations'] as &$observation ) { $observation['observedAt'] = gmdate( 'c' ); } unset( $observation );
$snapshot = array( 'version' => 'v2', 'organization' => array( 'id' => 'fixture-org', 'slug' => 'chidemoon' ), 'snapshot' => array( 'id' => wp_generate_uuid4(), 'revision' => str_repeat( 'a', 64 ), 'generatedAt' => gmdate( 'c' ) ), 'items' => array( $record ), 'withdrawals' => array() );
$file = wp_upload_bits( 'catalog-fixture.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQ0AAAAASUVORK5CYII=' ) );
catalog_check( ! $file['error'], 'Fixture image could not be written' );
$attachment = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Catalog fixture cover', 'post_status' => 'inherit' ), $file['file'] );
update_post_meta( $attachment, '_chidemoon_import_image_url', $record['content']['images'][0] );
$legacy = new WC_Product_External();
$legacy->set_name( 'Legacy chair' ); $legacy->set_status( 'draft' ); $legacy->set_description( '<p>Chidemoon editorial explanation</p>' );
$legacy->set_product_url( $record['content']['affiliateUrl'] ); $legacy->set_image_id( $attachment );
$legacy->update_meta_data( Chidemoon_Core_Affiliate::META_SOURCE_KEY, $record['aliases'][0] );
$legacy->update_meta_data( Chidemoon_Core_Affiliate::META_FACTS, '[{"label":"Editorial fact","value":"Preserved"}]' );
$product_id = $legacy->save();
wp_update_post( array( 'ID' => $attachment, 'post_parent' => $product_id ) );
$result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
catalog_check( 'imported' === $result['records'][0]['status'], 'Initial import failed: ' . wp_json_encode( $result ) );
catalog_check( (string) $product_id === $result['records'][0]['localProductId'], 'Legacy WooCommerce ID was replaced' );
catalog_check( 'draft' === get_post_status( $product_id ), 'Import auto-published a product' );
$product = wc_get_product( $product_id );
catalog_check( '<p>Chidemoon editorial explanation</p>' === $product->get_description(), 'Editorial content changed during import' );
catalog_check( '125000' === $product->get_price() && '150000' === $product->get_regular_price(), 'Destination currency conversion failed' );
wp_update_post( array( 'ID' => $product_id, 'post_status' => 'publish' ) );
catalog_check( 'draft' === get_post_status( $product_id ), 'Publication readiness was bypassed' );
$approved = array(); $term_ids = array();
foreach ( array( 'category', 'style', 'room', 'color', 'material' ) as $field ) {
 $term = wp_insert_term( 'Fixture ' . $field . ' ' . $product_id, Chidemoon_Core_Enrichment::TAXONOMIES[ $field ] );
 catalog_check( ! is_wp_error( $term ), 'Fixture taxonomy could not be created: ' . $field );
 $term_ids[ $field ] = (int) $term['term_id'];
 $approved[ $field ] = array( 'state' => 'value', 'value' => array( (int) $term['term_id'] ), 'evidence' => array( $record['content']['sourceUrl'] ) );
}
$approved['dimensions'] = array( 'state' => 'value', 'value' => array( 'width' => 450, 'height' => 90 ), 'unit' => 'mm', 'measurementType' => 'product', 'evidence' => array( $record['content']['sourceUrl'] ) );
Chidemoon_Core_Enrichment::apply( $product_id, $approved, Chidemoon_Core_Enrichment::token( $product_id ), $administrator->ID );
catalog_check( 45.0 === Chidemoon_Core_Enrichment::read( $product_id )['dimensions']['value']['width'], 'Dimensions were not normalized to centimeters' );
try { Chidemoon_Core_Enrichment::apply( $product_id, $approved, '0:0', $administrator->ID ); throw new RuntimeException( 'Stale enrichment accepted' ); } catch ( RuntimeException $exception ) { catalog_check( 'revision_conflict' === $exception->getMessage(), $exception->getMessage() ); }
catalog_check( array() === Chidemoon_Core_Enrichment::readiness( $product_id ), 'Product remained unready after approval' );
$product = wc_get_product( $product_id ); $product->update_meta_data( Chidemoon_Core_Affiliate::META_REVIEW_STATE, 'reviewed' ); $product->save();
wp_update_post( array( 'ID' => $product_id, 'post_status' => 'publish' ) );
catalog_check( 'publish' === get_post_status( $product_id ), 'Reviewed product did not publish' );
$look_ids = array();
foreach ( array( 'main', 'accent' ) as $index => $role ) {
 $look_id = wp_insert_post( array( 'post_type' => Chidemoon_Core_Looks::POST_TYPE, 'post_title' => 'Fixture Look ' . $index . ' ' . $product_id, 'post_status' => 'publish' ) );
 set_post_thumbnail( $look_id, $attachment );
 Chidemoon_Core_Looks::set_relationships( $look_id, array( array( 'productId' => $product_id, 'role' => $role, 'position' => 0, 'x' => 20 + $index * 50, 'y' => 30 ) ), '0', true );
 update_post_meta( $look_id, Chidemoon_Core_Looks::PALETTE, Chidemoon_Core_Looks::palette( array( array( 'colorId' => $term_ids['color'], 'hex' => '#123456', 'proportion' => 1 ) ) ) );
 $look_ids[] = $look_id;
}
catalog_check( 'main' === Chidemoon_Core_Looks::relationships( $look_ids[0] )[0]['role'] && 'accent' === Chidemoon_Core_Looks::relationships( $look_ids[1] )[0]['role'], 'Look roles are not independent' );
catalog_check( 2 === substr_count( Chidemoon_Core_Looks::product_looks_shortcode( array( 'product_id' => $product_id ) ), '<li>' ), 'Reverse Look links are missing' );
foreach ( array( 'visible' => array(), 'hidden' => array( 'show_source' => '', 'show_looks' => '' ) ) as $visibility => $settings ) {
 $widget = \Elementor\Plugin::$instance->elements_manager->create_element_instance( array( 'id' => 'catalog-offer-' . $visibility, 'elType' => 'widget', 'widgetType' => 'chidemoon-product-offer', 'settings' => array_merge( array( 'product_id' => (string) $product_id ), $settings ), 'elements' => array() ) );
 catalog_check( $widget instanceof \Elementor\Widget_Base, 'Product offer widget is unavailable' );
 ob_start();
 try { ( new ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $offer_html = (string) ob_get_contents(); }
 finally { ob_end_clean(); }
 foreach ( $look_ids as $look_id ) { catalog_check( ( 'visible' === $visibility ) === str_contains( $offer_html, esc_url( get_permalink( $look_id ) ) ), 'Elementor offer ignores Look relationships or visibility controls' ); }
 catalog_check( ( 'visible' === $visibility ) === str_contains( $offer_html, 'ch-offer-observations' ), 'Elementor offer hides source observations or ignores visibility controls' );
}
$archive = new WP_Query();
$archive->set( 'category_name', 'obsolete-post-category' );
$archive->set( 'tax_query', array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => array( 'obsolete-look-tag' ) ) ) );
Chidemoon_Core_Public_Design::looks_query( $archive );
$archive->get_posts();
catalog_check( in_array( $look_ids[0], wp_list_pluck( $archive->posts, 'ID' ), true ), 'Legacy Elementor terms hide independent Looks' );
$review = wp_insert_comment( array( 'comment_post_ID' => $product_id, 'comment_content' => 'Community fixture review', 'comment_author' => 'Fixture reviewer', 'comment_author_email' => 'reviewer@example.invalid', 'comment_type' => 'review', 'comment_approved' => 0 ) );
catalog_check( $review && '0' === get_comment( $review )->comment_approved, 'Local review was not held for moderation' );
catalog_check( 0 === Chidemoon_Core_Commercial::moderate_review( 1, array( 'comment_post_ID' => $product_id ) ), 'Review submission bypasses moderation' );
$GLOBALS['post'] = get_post( $product_id );
update_option( 'woocommerce_review_rating_verification_required', 'yes' );
catalog_check( 'no' === get_option( 'woocommerce_review_rating_verification_required' ), 'Affiliate reviews require a nonexistent local purchase' );
unset( $GLOBALS['post'] );
$snapshot['items'][0]['revision'] = 2;
$snapshot['items'][0]['observations']['price']['previous'] = null;
$snapshot['items'][0]['observations']['availability']['status'] = 'out_of_stock';
$enrichment_before = Chidemoon_Core_Enrichment::read( $product_id );
$result = Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
catalog_check( 'imported' === $result['records'][0]['status'], 'Commercial update failed' );
$product = wc_get_product( $product_id );
catalog_check( '' === $product->get_sale_price(), 'Ended sale was not cleared' );
catalog_check( ! Chidemoon_Core_Affiliate::is_offer_available( $product ) && Chidemoon_Core_Affiliate::is_publicly_eligible( $product ), 'Unavailable product visibility / purchase rules failed' );
catalog_check( $enrichment_before === Chidemoon_Core_Enrichment::read( $product_id ) && 'main' === Chidemoon_Core_Looks::relationships( $look_ids[0] )[0]['role'], 'Commercial update changed enrichment or Look links' );
catalog_check( str_contains( Chidemoon_Core_Looks::render( $look_ids[0] ), 'Unavailable at merchant' ), 'Look does not disclose unavailable products' );
catalog_check( 'insufficient_data' === Chidemoon_Core_Discovery::fit( array( 'width' => 45 ), array( 'width' => 50, 'depth' => 60 ) ), 'Unknown depth manufactured a fit result' );
catalog_check( 'exceeds' === Chidemoon_Core_Discovery::fit( array( 'width' => 45 ), array( 'width' => 40 ) ), 'Oversized product marked as fitting' );
$comparison = Chidemoon_Core_Compare::facts( $product );
catalog_check( '45' === $comparison['Width (cm)'] && 'Unknown' === $comparison['Depth (cm)'], 'Comparison does not use normalized measurements' );
$visitor = wp_create_user( 'catalog_visitor_' . $product_id, 'isolated_visitor_123', 'visitor' . $product_id . '@example.invalid' );
wp_set_current_user( $visitor );
$request = new WP_REST_Request( 'PATCH', '/chidemoon-core/v1/products/' . $product_id . '/enrichment' );
$request->set_param( 'fields', $approved ); $request->set_param( 'revision', Chidemoon_Core_Enrichment::token( $product_id ) );
$response = rest_do_request( $request );
catalog_check( 403 === $response->get_status(), 'Unauthorized visitor could edit enrichment' );
wp_set_current_user( $administrator->ID );
$snapshot['items'][0]['revision'] = 3; $snapshot['items'][0]['observations']['availability']['status'] = 'in_stock';
Chidemoon_Core_Source_Importer::import_snapshot( $snapshot, false, 'chidemoon' );
$chooser_page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Catalog guided chooser ' . $product_id, 'post_content' => '[chidemoon_guided_chooser]' ) );
update_option( 'chidemoon_catalog_fixture', array( 'productId' => $product_id, 'lookIds' => $look_ids, 'chooserPageId' => $chooser_page, 'termIds' => $term_ids ) );
echo wp_json_encode( array( 'passed' => true, 'productId' => $product_id, 'lookIds' => $look_ids, 'chooserUrl' => get_permalink( $chooser_page ) ) ) . "\n";
