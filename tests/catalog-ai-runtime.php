<?php
if ( ! defined( 'DB_NAME' ) || 'catalog_test' !== DB_NAME || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Isolated catalog database required' ); }
switch_to_locale( 'en_US' );
function ai_catalog_check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$admin = get_user_by( 'login', 'catalog_test_admin' ); wp_set_current_user( $admin->ID );
$fixture = get_option( 'chidemoon_catalog_fixture' ); $id = (int) $fixture['productId'];
$product = wc_get_product( $id ); $source_before = array( $product->get_name(), $product->get_description(), $product->get_price(), Chidemoon_Core_Affiliate::get_affiliate_url( $product ) );
$proposal = array( 'field' => 'dimensions', 'state' => 'value', 'termIds' => array(), 'measurements' => array( 'length' => null, 'width' => 480, 'height' => null, 'depth' => null ), 'unit' => 'mm', 'confidence' => 0.92, 'evidence' => array( 'fixture-source' ), 'judgment' => 'factual' );
$result = array( 'fields' => array( $proposal, array_merge( $proposal, array( 'field' => 'material', 'termIds' => array( $fixture['termIds']['material'] ) ) ) ), 'citation_source_ids' => array( 'fixture-source' ) );
ai_catalog_check( ! is_wp_error( Chidemoon_AI_Enrich::validate_result( $result, array( 'fixture-source' ) ) ), 'Valid structured AI proposal rejected' );
$invalid = $result; $invalid['fields'][0]['evidence'] = array( 'invented-source' );
ai_catalog_check( is_wp_error( Chidemoon_AI_Enrich::validate_result( $invalid, array( 'fixture-source' ) ) ), 'Invalid AI evidence accepted' );
$invalid = $result; $invalid['fields'][0]['field'] = 'title';
ai_catalog_check( is_wp_error( Chidemoon_AI_Enrich::validate_result( $invalid, array( 'fixture-source' ) ) ), 'AI can propose source fields' );
function catalog_ai_job( string $type, int $target, array $request, array $result, int $admin_id ): array {
 $job = Chidemoon_AI_Repository::create( array( 'job_type' => $type, 'target_post_id' => $target, 'requested_by' => $admin_id, 'idempotency_key' => 'fixture-' . wp_generate_uuid4(), 'request_hash' => str_repeat( 'a', 64 ), 'request_payload' => $request ) );
 ai_catalog_check( ! is_wp_error( $job ), 'AI fixture job failed' );
 Chidemoon_AI_Repository::claim( $job['id'] );
 Chidemoon_AI_Evidence::persist_external( $job['id'], array( 'source_id' => 'fixture-source', 'source_url' => 'https://example.com/product', 'source_excerpt' => 'Product width 480 mm' ) );
 ai_catalog_check( Chidemoon_AI_Repository::mark_review_required( $job['id'], $result, array() ), 'AI fixture review transition failed' );
 return Chidemoon_AI_Repository::find( $job['id'] );
}
$request_payload = array( 'enrichment_revision' => Chidemoon_Core_Enrichment::token( $id ) );
$job = catalog_ai_job( 'enrich', $id, $request_payload, $result, $admin->ID );
$approve = new WP_REST_Request( 'POST', '/chidemoon-ai/v1/jobs/' . $job['id'] . '/approve' );
ai_catalog_check( 422 === rest_do_request( $approve )->get_status(), 'AI approval did not require individual fields' );
$approve->set_param( 'approvedFields', array( 'dimensions' ) );
ai_catalog_check( 200 === rest_do_request( $approve )->get_status(), 'Selective AI approval failed' );
$material_before = Chidemoon_Core_Enrichment::read( $id )['material'];
$applied = Chidemoon_AI_Runner::apply( Chidemoon_AI_Repository::find( $job['id'] ), $admin->ID );
ai_catalog_check( $id === $applied, 'Approved AI application failed: ' . ( is_wp_error( $applied ) ? $applied->get_error_message() : 'wrong ID' ) );
ai_catalog_check( 48.0 === Chidemoon_Core_Enrichment::read( $id )['dimensions']['value']['width'] && $material_before === Chidemoon_Core_Enrichment::read( $id )['material'], 'AI applied unselected fields' );
$product = wc_get_product( $id );
ai_catalog_check( $source_before === array( $product->get_name(), $product->get_description(), $product->get_price(), Chidemoon_Core_Affiliate::get_affiliate_url( $product ) ) && 'publish' === get_post_status( $id ), 'AI altered source fields or publication state' );
$stale = catalog_ai_job( 'enrich', $id, $request_payload, $result, $admin->ID );
$approve = new WP_REST_Request( 'POST', '/chidemoon-ai/v1/jobs/' . $stale['id'] . '/approve' ); $approve->set_param( 'approvedFields', array( 'dimensions' ) );
ai_catalog_check( 409 === rest_do_request( $approve )->get_status(), 'Stale AI proposal approved' );
$look_job = catalog_ai_job( 'look', 0, array(), array( 'attachment_id' => $product->get_image_id(), 'product_ids' => array( $id ), 'hotspots_proposal' => array( array( 'productId' => $id, 'x' => 35, 'y' => 60 ) ), 'revised_prompt' => 'AI fixture Look' ), $admin->ID );
Chidemoon_AI_Repository::approve( $look_job['id'], $admin->ID );
$look_id = Chidemoon_AI_Runner::apply( Chidemoon_AI_Repository::find( $look_job['id'] ), $admin->ID );
ai_catalog_check( is_int( $look_id ) && Chidemoon_Core_Looks::POST_TYPE === get_post_type( $look_id ) && 'draft' === get_post_status( $look_id ), 'AI did not create an independent Look draft' );
$rows = Chidemoon_Core_Looks::relationships( $look_id );
ai_catalog_check( 1 === count( $rows ) && ! $rows[0]['hotspot_approved'] && 35.0 === (float) $rows[0]['hotspot_x'], 'Suggested hotspots bypassed confirmation' );
$duplicate = Chidemoon_AI_Runner::apply( array_merge( $look_job, array( 'state' => Chidemoon_AI_State_Machine::APPROVED ) ), $admin->ID );
ai_catalog_check( is_wp_error( $duplicate ), 'AI application replay created another Look' );
echo "PASS: selective AI fields, evidence, stale revisions, source ownership, independent Look drafts and hotspot confirmation\n";
