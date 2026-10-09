<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Enrichment {
 public const META = '_chidemoon_enrichment';
 public const REVISION = '_chidemoon_enrichment_revision';
 public const TAXONOMIES = array( 'category' => 'product_cat', 'style' => 'chidemoon_style', 'room' => 'chidemoon_room', 'color' => 'pa_color', 'material' => 'pa_material', 'features' => 'pa_features', 'suitability' => 'pa_space_size', 'tags' => 'product_tag' );
 public const REQUIRED = array( 'style', 'room', 'color', 'material' );
 private static bool $applying = false;
 public static function field_label( string $field ): string {
  $labels = array( 'category' => 'Category', 'style' => 'Style', 'room' => 'Room', 'color' => 'Color', 'material' => 'Material', 'features' => 'Features', 'suitability' => 'Space suitability', 'tags' => 'Tags', 'dimensions' => 'Dimensions', 'length' => 'Length', 'width' => 'Width', 'height' => 'Height', 'depth' => 'Depth' );
  return __( $labels[ $field ] ?? $field, 'chidemoon-core' );
 }
 public static function display_field( string $field, ?array $value ): string {
  if ( ! $value || 'value' !== ( $value['state'] ?? '' ) ) { return __( 'not_applicable' === ( $value['state'] ?? '' ) ? 'Not applicable' : 'Unknown', 'chidemoon-core' ); }
  $labels = array();
  if ( 'dimensions' === $field ) {
   foreach ( $value['value'] ?? array() as $axis => $measurement ) { if ( null !== $measurement ) { $labels[] = self::field_label( $axis ) . ': ' . $measurement . ' ' . ( $value['unit'] ?? 'cm' ); } }
  } elseif ( isset( self::TAXONOMIES[ $field ] ) ) {
   foreach ( $value['value'] ?? array() as $id ) { $term = get_term( (int) $id, self::TAXONOMIES[ $field ] ); if ( $term && ! is_wp_error( $term ) ) { $labels[] = $term->name; } }
  }
  return $labels ? implode( ', ', $labels ) : __( 'Unknown', 'chidemoon-core' );
 }

 public static function register(): void {
  add_action( 'init', array( __CLASS__, 'register_model' ), 20 );
  add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
  add_action( 'admin_notices', static function(): void {
   $key = 'chidemoon_enrichment_error_' . get_current_user_id(); $error = get_transient( $key );
   if ( $error ) { delete_transient( $key ); echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; }
  } );
  add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_editor' ) );
  add_action( 'save_post_product', array( __CLASS__, 'save_editor' ), 20, 2 );
  add_filter( 'wp_insert_post_data', array( __CLASS__, 'publication_gate' ), 20, 2 );
  add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'review_gate' ), 110 );
  add_action( 'edited_term', array( __CLASS__, 'invalidate_groups' ), 10, 3 );
  add_action( 'set_object_terms', array( __CLASS__, 'preserve_approved_terms' ), 100, 4 );
  foreach ( array( 'pa_color', 'pa_material', 'product_cat' ) as $taxonomy ) {
   add_action( $taxonomy . '_edit_form_fields', array( __CLASS__, 'term_fields' ), 10, 2 );
   add_action( 'edited_' . $taxonomy, array( __CLASS__, 'save_term_fields' ) );
  }
 }

 public static function register_model(): void {
  register_taxonomy( 'chidemoon_style', array( 'product', 'chidemoon_look' ), array( 'label' => __( 'Styles', 'chidemoon-core' ), 'public' => true, 'show_in_rest' => true, 'hierarchical' => false, 'capabilities' => array( 'manage_terms' => 'chidemoon_manage_affiliate', 'edit_terms' => 'chidemoon_manage_affiliate', 'delete_terms' => 'chidemoon_manage_affiliate', 'assign_terms' => 'edit_products' ) ) );
  register_taxonomy_for_object_type( 'chidemoon_room', 'product' );
  if ( ! function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) { return; }
  foreach ( array( 'color' => 'Color', 'material' => 'Material', 'features' => 'Features', 'space_size' => 'Space suitability' ) as $name => $label ) {
   if ( ! wc_attribute_taxonomy_id_by_name( $name ) ) { wc_create_attribute( array( 'name' => $label, 'slug' => $name, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => true ) ); }
   if ( ! taxonomy_exists( 'pa_' . $name ) ) { register_taxonomy( 'pa_' . $name, array( 'product' ), array( 'label' => $label, 'public' => true, 'show_ui' => true, 'show_in_rest' => true, 'hierarchical' => false ) ); }
  }
  register_post_meta( 'product', self::META, array( 'single' => true, 'type' => 'object', 'show_in_rest' => false, 'auth_callback' => static fn( $allowed, $key, $id ) => current_user_can( 'chidemoon_manage_affiliate' ) && current_user_can( 'edit_post', $id ) ) );
 }

 public static function read( int $id ): array { $value = get_post_meta( $id, self::META, true ); return is_array( $value ) ? $value : array(); }
 public static function token( int $id ): string { return (string) (int) get_post_meta( $id, self::REVISION, true ) . ':' . (string) (int) get_post_meta( $id, Chidemoon_Core_Source_Importer::META_CONTENT_REVISION, true ) . ':' . hash( 'sha256', (string) get_post_meta( $id, '_chidemoon_source_content', true ) ); }

 public static function validate_field( string $field, array $proposal ): array {
  if ( ! isset( self::TAXONOMIES[ $field ] ) && 'dimensions' !== $field ) { throw new InvalidArgumentException( 'Source fields cannot be enriched' ); }
  $state = $proposal['state'] ?? '';
  if ( ! in_array( $state, array( 'value', 'unknown', 'not_applicable' ), true ) ) { throw new InvalidArgumentException( 'Choose a reviewed value or explicit unknown/not applicable' ); }
  $evidence = $proposal['evidence'] ?? array();
  if ( ! is_array( $evidence ) || count( $evidence ) > 20 ) { throw new InvalidArgumentException( 'Invalid evidence references' ); }
  foreach ( $evidence as $reference ) { if ( ! is_string( $reference ) || '' === $reference || strlen( $reference ) > 2048 ) { throw new InvalidArgumentException( 'Invalid evidence references' ); } }
  $clean = array( 'state' => $state, 'evidence' => array_values( array_unique( $evidence ) ), 'judgment' => in_array( $field, array( 'style', 'room', 'suitability', 'tags' ), true ) ? 'editorial' : 'factual' );
  if ( 'value' !== $state ) { return $clean; }
  if ( 'dimensions' === $field ) {
   $measurements = $proposal['value'] ?? null;
   $unit = $proposal['unit'] ?? 'cm';
   if ( ! is_array( $measurements ) || array_diff( array_keys( $measurements ), array( 'length', 'width', 'height', 'depth' ) ) || ! in_array( $unit, array( 'cm', 'mm', 'm', 'in' ), true ) || 'product' !== ( $proposal['measurementType'] ?? 'product' ) || ! $evidence ) { throw new InvalidArgumentException( 'Product dimensions require evidence and a supported unit' ); }
   $factor = array( 'cm' => 1, 'mm' => 0.1, 'm' => 100, 'in' => 2.54 )[ $unit ];
   $values = array_fill_keys( array( 'length', 'width', 'height', 'depth' ), null );
   foreach ( $measurements as $axis => $measurement ) {
    if ( null === $measurement ) { continue; }
    if ( ! is_numeric( $measurement ) || ! is_finite( (float) $measurement ) || (float) $measurement <= 0 || (float) $measurement * $factor > 100000 ) { throw new InvalidArgumentException( 'Invalid product dimensions' ); }
    $values[ $axis ] = round( (float) $measurement * $factor, 4 );
   }
   if ( ! array_filter( $values, static fn( $value ) => null !== $value ) ) { throw new InvalidArgumentException( 'At least one measured axis is required' ); }
   $clean['value'] = $values; $clean['unit'] = 'cm'; $clean['measurementType'] = 'product';
  } else {
   $values = $proposal['value'] ?? null;
   if ( ! is_array( $values ) || ! $values || count( $values ) > 30 ) { throw new InvalidArgumentException( 'Select approved vocabulary terms' ); }
   foreach ( $values as $term_id ) { if ( ! is_int( $term_id ) || $term_id < 1 || ! term_exists( $term_id, self::TAXONOMIES[ $field ] ) ) { throw new InvalidArgumentException( 'Term belongs to another vocabulary or is missing' ); } }
   $clean['value'] = array_values( array_unique( $values ) );
  }
  return $clean;
 }

 public static function apply( int $id, array $fields, string $expected_token, int $actor_id, bool $manage_transaction = true ): array {
  global $wpdb;
  if ( 'product' !== get_post_type( $id ) || ! user_can( $actor_id, 'edit_post', $id ) || ! user_can( $actor_id, 'chidemoon_manage_affiliate' ) ) { throw new RuntimeException( 'forbidden' ); }
  if ( ! Chidemoon_Core_Source_Importer::acquire_lock() ) { throw new RuntimeException( 'revision_conflict' ); }
  if ( ! self::acquire( $id ) ) { Chidemoon_Core_Source_Importer::release_lock(); throw new RuntimeException( 'revision_conflict' ); }
  try {
   wp_cache_delete( $id, 'post_meta' );
   if ( ! hash_equals( self::token( $id ), $expected_token ) ) { throw new RuntimeException( 'revision_conflict' ); }
   if ( ! $fields || count( $fields ) > 9 ) { throw new InvalidArgumentException( 'Select fields to approve' ); }
   $approved = self::read( $id );
   $clean = array();
   foreach ( $fields as $field => $proposal ) {
    if ( ! is_array( $proposal ) ) { throw new InvalidArgumentException( 'Invalid field proposal' ); }
    $clean[ $field ] = self::validate_field( $field, $proposal );
    $clean[ $field ]['approvedBy'] = $actor_id; $clean[ $field ]['approvedAt'] = gmdate( 'c' );
    if ( isset( $proposal['confidence'] ) ) {
     if ( ! is_numeric( $proposal['confidence'] ) || $proposal['confidence'] < 0 || $proposal['confidence'] > 1 ) { throw new InvalidArgumentException( 'Invalid field confidence' ); }
     $clean[ $field ]['confidence'] = (float) $proposal['confidence'];
    }
   }
   $category = $clean['category']['value'] ?? $approved['category']['value'] ?? wp_get_object_terms( $id, 'product_cat', array( 'fields' => 'ids' ) );
   $dimension_values = $clean['dimensions']['value'] ?? null;
   if ( $dimension_values ) {
    foreach ( is_array( $category ) ? $category : array() as $category_id ) {
     $axes = get_term_meta( $category_id, '_chidemoon_dimension_axes', true );
     if ( is_array( $axes ) && $axes && array_diff( array_keys( array_filter( $dimension_values, static fn( $value ) => null !== $value ) ), $axes ) ) { throw new InvalidArgumentException( 'Measurements include axes that do not apply to this category' ); }
    }
   }
   // All fields are validated before applying taxonomy or metadata changes.
   self::$applying = true;
   if ( $manage_transaction ) { $wpdb->query( 'START TRANSACTION' ); }
   try {
    foreach ( $clean as $field => $value ) {
     if ( isset( self::TAXONOMIES[ $field ] ) ) {
      $taxonomy = self::TAXONOMIES[ $field ];
      $result = wp_set_object_terms( $id, $value['value'] ?? array(), $taxonomy, false );
      if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Term assignment failed' ); }
     }
     $approved[ $field ] = $value;
    }
    update_post_meta( $id, self::META, $approved );
    update_post_meta( $id, self::REVISION, (int) get_post_meta( $id, self::REVISION, true ) + 1 );
    self::sync_attributes( $id, $approved );
    if ( $manage_transaction ) { $wpdb->query( 'COMMIT' ); }
   } catch ( Throwable $exception ) {
    if ( $manage_transaction ) { $wpdb->query( 'ROLLBACK' ); }
    clean_post_cache( $id ); clean_object_term_cache( $id, 'product' ); wc_delete_product_transients( $id );
    throw $exception;
   }
   return array( 'revision' => self::token( $id ), 'fields' => $approved );
  } finally { self::$applying = false; self::release( $id ); Chidemoon_Core_Source_Importer::release_lock(); }
 }

 public static function preserve_approved_terms( int $id, $terms, array $term_taxonomy_ids, string $taxonomy ): void {
  static $restoring = false;
  if ( self::$applying || $restoring || 'product' !== get_post_type( $id ) || ! get_post_meta( $id, Chidemoon_Core_Source_Importer::META_IDENTITY, true ) ) { return; }
  $field = array_search( $taxonomy, self::TAXONOMIES, true );
  if ( false === $field ) { return; }
  $expected = self::read( $id )[ $field ]['value'] ?? array();
  $actual = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'ids' ) );
  if ( is_wp_error( $actual ) ) { return; }
  sort( $expected ); sort( $actual );
  if ( $expected === $actual ) { return; }
  $restoring = true;
  try { wp_set_object_terms( $id, $expected, $taxonomy, false ); }
  finally { $restoring = false; }
 }

 private static function sync_attributes( int $id, array $approved ): void {
  $product = wc_get_product( $id );
  if ( ! $product instanceof WC_Product ) { throw new RuntimeException( 'Product unavailable' ); }
  $product->set_attributes( self::attributes( $product->get_attributes(), $approved ) ); $product->save();
 }
 public static function attributes( array $attributes, array $approved ): array {
  foreach ( array( 'color', 'material', 'features', 'suitability' ) as $field ) {
   if ( ! isset( $approved[ $field ] ) ) { continue; }
   $taxonomy = self::TAXONOMIES[ $field ];
   $attribute = new WC_Product_Attribute();
   $attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
   $attribute->set_name( $taxonomy ); $attribute->set_options( $approved[ $field ]['value'] ?? array() );
   $attribute->set_visible( true ); $attribute->set_variation( false ); $attributes[ $taxonomy ] = $attribute;
  }
  return $attributes;
 }

 public static function readiness( int $id ): array {
  $issues = array();
  $product = wc_get_product( $id );
  if ( ! $product instanceof WC_Product || ! $product->is_type( 'external' ) || ! $product->get_meta( Chidemoon_Core_Source_Importer::META_IDENTITY, true ) ) { $issues[] = 'verified_identity'; }
  if ( ! $product || ! Chidemoon_Core_Source_Contract::https_url( Chidemoon_Core_Affiliate::get_affiliate_url( $product ) ) ) { $issues[] = 'destination'; }
  if ( ! has_post_thumbnail( $id ) ) { $issues[] = 'image'; }
  $fields = self::read( $id );
  if ( empty( $fields['category']['value'] ) || 'value' !== ( $fields['category']['state'] ?? '' ) ) { $issues[] = 'category'; }
  foreach ( self::REQUIRED as $field ) { if ( ! isset( $fields[ $field ]['approvedBy'] ) ) { $issues[] = $field; } }
  return $issues;
 }

 public static function publication_gate( array $data, array $postarr ): array {
  $id = (int) ( $postarr['ID'] ?? 0 );
  if ( 'product' !== ( $data['post_type'] ?? '' ) || 'publish' !== ( $data['post_status'] ?? '' ) || ! $id || ! get_post_meta( $id, Chidemoon_Core_Source_Importer::META_IDENTITY, true ) || 'publish' === get_post_status( $id ) ) { return $data; }
  if ( ! current_user_can( 'chidemoon_manage_affiliate' ) || self::readiness( $id ) || 'reviewed' !== get_post_meta( $id, Chidemoon_Core_Affiliate::META_REVIEW_STATE, true ) ) { $data['post_status'] = 'draft'; }
  else { update_post_meta( $id, '_chidemoon_first_publication_approved_by', get_current_user_id() ); update_post_meta( $id, '_chidemoon_first_publication_approved_at', gmdate( 'c' ) ); }
  return $data;
 }

 public static function review_gate( WC_Product $product ): void {
  if ( $product->get_meta( Chidemoon_Core_Source_Importer::META_IDENTITY, true ) && 'reviewed' === $product->get_meta( Chidemoon_Core_Affiliate::META_REVIEW_STATE, true ) && self::readiness( $product->get_id() ) && ! get_post_meta( $product->get_id(), '_chidemoon_first_publication_approved_at', true ) ) { $product->update_meta_data( Chidemoon_Core_Affiliate::META_REVIEW_STATE, 'draft' ); }
 }

 public static function register_routes(): void {
  register_rest_route( 'chidemoon-core/v1', '/products/(?P<id>\d+)/enrichment', array(
   array( 'methods' => 'GET', 'permission_callback' => array( __CLASS__, 'can_edit' ), 'callback' => static fn( $request ) => array( 'revision' => self::token( (int) $request['id'] ), 'fields' => self::read( (int) $request['id'] ), 'readiness' => self::readiness( (int) $request['id'] ) ) ),
   array( 'methods' => 'PATCH', 'permission_callback' => array( __CLASS__, 'can_edit' ), 'callback' => static function( $request ) {
    try { return self::apply( (int) $request['id'], (array) $request->get_param( 'fields' ), (string) $request->get_param( 'revision' ), get_current_user_id() ); }
    catch ( Throwable $exception ) { return new WP_Error( 'enrichment_rejected', $exception->getMessage(), array( 'status' => 'revision_conflict' === $exception->getMessage() ? 409 : 422 ) ); }
   } ),
  ) );
 }
 public static function can_edit( $request ): bool { return current_user_can( 'chidemoon_manage_affiliate' ) && current_user_can( 'edit_post', (int) $request['id'] ); }
 private static function acquire( int $id ): bool { global $wpdb; return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', 'ch_enrichment_' . $id ) ); }
 private static function release( int $id ): void { global $wpdb; $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'ch_enrichment_' . $id ) ); }

 public static function add_editor(): void { add_meta_box( 'chidemoon-enrichment', __( 'Reviewed classification and measurements', 'chidemoon-core' ), array( __CLASS__, 'render_editor' ), 'product', 'normal', 'high' ); }
 public static function render_editor( WP_Post $post ): void {
  wp_nonce_field( 'chidemoon_enrichment_' . $post->ID, 'chidemoon_enrichment_nonce' );
  echo '<input type="hidden" name="chidemoon_enrichment_revision" value="' . esc_attr( self::token( $post->ID ) ) . '">';
  $fields = self::read( $post->ID );
  foreach ( self::TAXONOMIES as $field => $taxonomy ) {
   $current = $fields[ $field ] ?? array();
   echo '<fieldset><legend><strong>' . esc_html( Chidemoon_Core_Enrichment::field_label( $field ) ) . '</strong></legend><select name="chidemoon_enrichment[' . esc_attr( $field ) . '][state]">';
   foreach ( array( '' => 'Pending review', 'value' => 'Reviewed values', 'unknown' => 'Unknown', 'not_applicable' => 'Not applicable' ) as $state => $label ) { echo '<option value="' . esc_attr( $state ) . '" ' . selected( $current['state'] ?? '', $state, false ) . '>' . esc_html__( $label, 'chidemoon-core' ) . '</option>'; }
   echo '</select> <select multiple name="chidemoon_enrichment[' . esc_attr( $field ) . '][value][]" aria-label="' . esc_attr( $field ) . '" style="min-width:220px;max-width:100%">';
   $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
   foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) { echo '<option value="' . (int) $term->term_id . '" ' . selected( in_array( (int) $term->term_id, $current['value'] ?? array(), true ), true, false ) . '>' . esc_html( $term->name ) . '</option>'; }
   echo '</select></fieldset>';
  }
  $dimensions = $fields['dimensions'] ?? array();
  echo '<fieldset><legend><strong>' . esc_html__( 'Product measurements (cm)', 'chidemoon-core' ) . '</strong></legend>';
  echo '<select aria-label="' . esc_attr__( 'Measurement review', 'chidemoon-core' ) . '" name="chidemoon_dimension_state">';
  foreach ( array( '' => 'Pending review', 'value' => 'Reviewed values', 'unknown' => 'Unknown', 'not_applicable' => 'Not applicable' ) as $state => $label ) { echo '<option value="' . esc_attr( $state ) . '" ' . selected( $dimensions['state'] ?? '', $state, false ) . '>' . esc_html__( $label, 'chidemoon-core' ) . '</option>'; }
  echo '</select> ';
  foreach ( array( 'length', 'width', 'height', 'depth' ) as $axis ) { echo '<label>' . esc_html( Chidemoon_Core_Enrichment::field_label( $axis ) ) . ' <input type="number" min="0.0001" step="0.0001" name="chidemoon_dimensions[' . esc_attr( $axis ) . ']" value="' . esc_attr( (string) ( $dimensions['value'][ $axis ] ?? '' ) ) . '"></label> '; }
  echo '<p><label>' . esc_html__( 'Measurement evidence URL or reference', 'chidemoon-core' ) . ' <input class="widefat" name="chidemoon_dimension_evidence" value="' . esc_attr( implode( ', ', $dimensions['evidence'] ?? array() ) ) . '"></label></p></fieldset>';
  echo '<p>' . esc_html__( 'Categories are hierarchical WooCommerce terms. Create vocabulary terms in their taxonomy screens. Optional empty measurements stay unknown.', 'chidemoon-core' ) . '</p>';
  $issues = self::readiness( $post->ID );
  if ( $issues ) { echo '<p role="status">' . esc_html__( 'First publication still needs:', 'chidemoon-core' ) . ' ' . esc_html( implode( ', ', $issues ) ) . '</p>'; }
 }
 public static function save_editor( int $id, WP_Post $post ): void {
  static $saving = false;
  if ( $saving ) { return; }
  if ( wp_is_post_revision( $id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! isset( $_POST['chidemoon_enrichment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['chidemoon_enrichment_nonce'] ) ), 'chidemoon_enrichment_' . $id ) || ! current_user_can( 'chidemoon_manage_affiliate' ) || ! current_user_can( 'edit_post', $id ) ) { return; }
  $fields = array();
  foreach ( (array) ( $_POST['chidemoon_enrichment'] ?? array() ) as $field => $value ) {
   if ( ! is_array( $value ) || empty( $value['state'] ) ) { continue; }
   $fields[ $field ] = array( 'state' => sanitize_key( $value['state'] ), 'value' => array_map( 'intval', $value['value'] ?? array() ), 'evidence' => array( 'editor:' . get_current_user_id() ) );
  }
  $measurements = array();
  foreach ( (array) ( $_POST['chidemoon_dimensions'] ?? array() ) as $axis => $value ) { if ( '' !== trim( (string) $value ) ) { $measurements[ $axis ] = (string) $value; } }
  if ( $measurements ) { $fields['dimensions'] = array( 'state' => 'value', 'value' => $measurements, 'unit' => 'cm', 'measurementType' => 'product', 'evidence' => array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['chidemoon_dimension_evidence'] ?? '' ) ) ) ) ) ); }
  $dimension_state = sanitize_key( $_POST['chidemoon_dimension_state'] ?? '' );
  if ( in_array( $dimension_state, array( 'unknown', 'not_applicable' ), true ) ) { $fields['dimensions'] = array( 'state' => $dimension_state ); }
  if ( ! $fields ) { return; }
  $saving = true;
  try { self::apply( $id, $fields, sanitize_text_field( wp_unslash( $_POST['chidemoon_enrichment_revision'] ?? '' ) ), get_current_user_id() ); }
  catch ( Throwable $exception ) { set_transient( 'chidemoon_enrichment_error_' . get_current_user_id(), $exception->getMessage(), 60 ); }
  finally { $saving = false; }
 }
 public static function term_fields( $term, string $taxonomy ): void {
  wp_nonce_field( 'chidemoon_term_' . $term->term_id, 'chidemoon_term_nonce' );
  $key = 'product_cat' === $taxonomy ? '_chidemoon_dimension_axes' : '_chidemoon_term_groups';
  echo '<tr class="form-field"><th>' . esc_html__( 'Groups / applicable measurement axes', 'chidemoon-core' ) . '</th><td><input name="chidemoon_term_mapping" value="' . esc_attr( implode( ',', (array) get_term_meta( $term->term_id, $key, true ) ) ) . '"><p>' . esc_html__( 'Comma-separated group slugs; category axes: length,width,height,depth.', 'chidemoon-core' ) . '</p></td></tr>';
 }
 public static function save_term_fields( int $id ): void {
  if ( ! current_user_can( 'chidemoon_manage_affiliate' ) || ! isset( $_POST['chidemoon_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['chidemoon_term_nonce'] ) ), 'chidemoon_term_' . $id ) ) { return; }
  $term = get_term( $id ); if ( ! $term || is_wp_error( $term ) ) { return; }
  $values = array_values( array_filter( array_map( 'sanitize_key', explode( ',', wp_unslash( $_POST['chidemoon_term_mapping'] ?? '' ) ) ) ) );
  if ( 'product_cat' === $term->taxonomy ) { $values = array_values( array_intersect( $values, array( 'length', 'width', 'height', 'depth' ) ) ); }
  update_term_meta( $id, 'product_cat' === $term->taxonomy ? '_chidemoon_dimension_axes' : '_chidemoon_term_groups', $values );
 }
 public static function invalidate_groups( int $id, int $tt_id, string $taxonomy ): void { if ( in_array( $taxonomy, array( 'pa_color', 'pa_material' ), true ) ) { delete_transient( 'chidemoon_groups_' . $taxonomy ); } }
}
