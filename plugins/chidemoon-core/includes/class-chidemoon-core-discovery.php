<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Discovery {
 public static function register(): void {
  add_action( 'wp_enqueue_scripts', static function (): void {
   foreach ( array('css','js') as $type ) {
    $path = 'assets/' . $type . '/category-navigation.' . $type;
    if ( 'css' === $type ) { wp_enqueue_style('chidemoon-category-navigation',CHIDEMOON_CORE_URL.$path,array('chidemoon-public-design'),(string)filemtime(CHIDEMOON_CORE_DIR.$path)); }
    else { wp_enqueue_script('chidemoon-category-navigation',CHIDEMOON_CORE_URL.$path,array('chidemoon-public-design'),(string)filemtime(CHIDEMOON_CORE_DIR.$path),true); }
   }
  }, 40 );
  add_action( 'woocommerce_product_query', array( __CLASS__, 'filter_archive' ), 25 );
  add_action( 'elementor/query/chidemoon_catalog', array( __CLASS__, 'filter_archive' ), 25 );
  add_action( 'pre_get_posts', static function ( WP_Query $query ): void {
   if ( ! is_admin() && $query->is_main_query() && $query->is_search() && array( 'product' ) === (array) $query->get( 'post_type' ) ) { self::filter_archive( $query ); }
  }, 30 );
  add_shortcode( 'chidemoon_catalog_filters', array( __CLASS__, 'filters' ) );
  add_shortcode( 'chidemoon_catalog_sidebar', array( __CLASS__, 'sidebar_filters' ) );
  add_shortcode( 'chidemoon_category_tree', array( __CLASS__, 'category_tree' ) );
  add_shortcode( 'chidemoon_guided_chooser', array( __CLASS__, 'chooser' ) );
  add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
 }
 public static function category_tree(): string {
  $scope = array_values( array_filter( array_map( 'absint', (array) get_option( 'chidemoon_category_coverage_ids', array() ) ) ) );
  $query = array( 'taxonomy' => 'product_cat', 'hide_empty' => ! $scope, 'hierarchical' => true, 'title_li' => '', 'echo' => false, 'show_count' => false, 'orderby' => 'name' );
  if ( $scope ) { $query['include'] = $scope; }
  $items = wp_list_categories( $query );
  static $samples = null;
  if ( null === $samples ) {
   $samples = array();
   $products = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 60, 'no_found_rows' => true, 'orderby' => 'date', 'order' => 'DESC' ) );
   foreach ( $products as $post ) {
    $terms = get_the_terms( $post->ID, 'product_cat' );
    if ( ! is_array( $terms ) ) { continue; }
    $ids = array();
    foreach ( $terms as $term ) { $ids = array_merge( $ids, array( $term->term_id ), get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ); }
    foreach ( array_unique( $ids ) as $id ) {
     if ( count( $samples[ $id ] ?? array() ) >= 3 ) { continue; }
     $samples[ $id ][] = '<a href="' . esc_url( get_permalink( $post->ID ) ) . '">' . get_the_post_thumbnail( $post->ID, 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) . '<span>' . esc_html( get_the_title( $post->ID ) ) . '</span></a>';
    }
   }
  }
  $templates = '';
  foreach ( $samples as $id => $cards ) { $templates .= '<template data-category-samples="' . (int) $id . '"><div class="ch-category-samples">' . implode( '', $cards ) . '</div></template>'; }
  return '<nav class="ch-category-tree" aria-label="' . esc_attr__( 'دسته‌بندی محصولات', 'chidemoon-core' ) . '"><ul>' . $items . '</ul>' . $templates . '</nav>';
 }
 public static function register_widget( $manager ): void { require_once CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-elementor-discovery-widget.php'; $manager->register( new Chidemoon_Core_Elementor_Discovery_Widget() ); }
 public static function selection( string $key, array $input ): array {
  $value = $input[ $key ] ?? array();
  if ( is_string( $value ) ) { $value = explode( ',', $value ); }
  if ( ! is_array( $value ) ) { return array(); }
  return array_values( array_unique( array_filter( array_map( static fn( $entry ) => is_scalar( $entry ) ? sanitize_title( (string) $entry ) : '', array_slice( $value, 0, 30 ) ) ) ) );
 }
 public static function groups( string $taxonomy ): array {
  $groups = array(); $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
  foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
   foreach ( (array) get_term_meta( $term->term_id, '_chidemoon_term_groups', true ) as $group ) { if ( $group ) { $groups[ $group ][] = (int) $term->term_id; } }
  }
  return $groups;
 }
 public static function query_filters( array $input ): array {
  $tax = array( 'relation' => 'AND', array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => array( 'external' ) ) );
  foreach ( Chidemoon_Core_Enrichment::TAXONOMIES as $field => $taxonomy ) {
   $selected = self::selection( 'ch_' . $field, $input );
   $group_selected = self::selection( 'ch_' . $field . '_group', $input );
   if ( in_array( $field, array( 'color', 'material' ), true ) && $group_selected ) {
    $group_ids = array(); $groups = self::groups( $taxonomy );
    foreach ( $group_selected as $group ) { $group_ids = array_merge( $group_ids, $groups[ $group ] ?? array() ); }
    $direct = $selected ? get_terms( array( 'taxonomy' => $taxonomy, 'slug' => $selected, 'hide_empty' => false, 'fields' => 'ids' ) ) : array();
    $tax[] = array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => array_values( array_unique( array_merge( $group_ids, is_wp_error( $direct ) ? array() : $direct ) ) ) ?: array( 0 ), 'operator' => 'IN' );
   } elseif ( $selected ) { $tax[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $selected, 'operator' => 'IN' ); }
  }
  $meta = array( 'relation' => 'AND', array( 'key' => Chidemoon_Core_Affiliate::META_REVIEW_STATE, 'value' => 'reviewed' ) );
  $min = self::number_input( 'ch_min_price', $input ); $max = self::number_input( 'ch_max_price', $input );
  if ( null !== $min || null !== $max ) {
   $meta[] = array( 'key' => '_price', 'value' => array( $min ?? 0, $max ?? 999999999999999 ), 'compare' => 'BETWEEN', 'type' => 'DECIMAL(24,6)' );
  }
  $availability = self::selection( 'ch_availability', $input );
  if ( $availability ) {
   $clauses = array( 'relation' => 'OR' );
   foreach ( $availability as $status ) {
    if ( 'in_stock' === $status ) { $clauses[] = self::available_meta(); }
    elseif ( 'out_of_stock' === $status ) { $clauses[] = array( 'key' => '_chidemoon_availability_status', 'value' => 'out_of_stock' ); }
    elseif ( 'unknown' === $status ) { $clauses[] = array( 'relation' => 'OR', array( 'key' => '_chidemoon_availability_status', 'compare' => 'NOT EXISTS' ), array( 'key' => '_chidemoon_availability_status', 'value' => 'unknown' ), array( 'relation' => 'AND', array( 'key' => '_chidemoon_availability_status', 'value' => 'in_stock' ), array( 'relation' => 'OR', array( 'key' => '_chidemoon_availability_observed_unix', 'compare' => 'NOT EXISTS' ), array( 'key' => '_chidemoon_availability_observed_unix', 'value' => array( time() - Chidemoon_Core_Commercial::FRESHNESS_SECONDS, time() + 300 ), 'compare' => 'NOT BETWEEN', 'type' => 'NUMERIC' ) ) ) ); }
   }
   if ( count( $clauses ) > 1 ) { $meta[] = $clauses; }
  }
  return array( 'tax_query' => $tax, 'meta_query' => $meta );
 }
 private static function available_meta(): array { return array( 'relation' => 'AND', array( 'key' => '_chidemoon_availability_status', 'value' => 'in_stock' ), array( 'key' => '_chidemoon_availability_observed_unix', 'value' => array( time() - Chidemoon_Core_Commercial::FRESHNESS_SECONDS, time() + 300 ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ), array( 'relation' => 'OR', array( 'key' => Chidemoon_Core_Source_Importer::META_WITHDRAWN, 'value' => '0' ), array( 'key' => Chidemoon_Core_Source_Importer::META_WITHDRAWN, 'compare' => 'NOT EXISTS' ) ) ); }
 public static function filter_archive( WP_Query $query ): void {
  if ( is_admin() || $query->get( '_chidemoon_discovery_filtered' ) ) { return; }
  $query->set( '_chidemoon_discovery_filtered', true );
  $filters = self::query_filters( wp_unslash( $_GET ) );
  foreach ( $filters as $key => $clauses ) { $existing = (array) $query->get( $key ); $query->set( $key, $existing ? array( 'relation' => 'AND', $existing, $clauses ) : $clauses ); }
 }
 public static function number_input( string $key, array $input ): ?float { $value = $input[ $key ] ?? ''; return is_scalar( $value ) && '' !== (string) $value && is_numeric( $value ) && is_finite( (float) $value ) && $value >= 0 && $value <= 999999999999999 ? (float) $value : null; }
 public static function filters( array $attributes = array() ): string {
  $input = wp_unslash( $_GET );
  $html = '<form method="get" class="ch-catalog-filters" aria-label="' . esc_attr__( 'Product filters', 'chidemoon-core' ) . '">';
  foreach ( Chidemoon_Core_Enrichment::TAXONOMIES as $field => $taxonomy ) { $html .= self::facet( $field, $taxonomy, $input ); }
  foreach ( array( 'color', 'material' ) as $field ) {
   $groups = self::groups( Chidemoon_Core_Enrichment::TAXONOMIES[ $field ] );
   if ( ! $groups ) { continue; }
   $html .= '<fieldset><legend>' . esc_html__( 'color' === $field ? 'Color groups' : 'Material groups', 'chidemoon-core' ) . '</legend>';
   foreach ( array_keys( $groups ) as $group ) { $html .= self::checkbox( 'ch_' . $field . '_group', $group, $group, $input ); }
   $html .= '</fieldset>';
  }
  $html .= '<fieldset><legend>' . esc_html__( 'Availability', 'chidemoon-core' ) . '</legend>';
  foreach ( array( 'in_stock' => 'Available at merchant', 'out_of_stock' => 'Unavailable at merchant', 'unknown' => 'Unconfirmed availability' ) as $value => $label ) { $html .= self::checkbox( 'ch_availability', $value, __( $label, 'chidemoon-core' ), $input ); }
  $html .= '</fieldset>';
  foreach ( array( 'ch_min_price' => 'Minimum price', 'ch_max_price' => 'Maximum price' ) as $key => $label ) { $html .= '<label>' . esc_html__( $label, 'chidemoon-core' ) . ' (' . esc_html( get_woocommerce_currency() ) . ') <input type="number" min="0" step="0.01" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) ( self::number_input( $key, $input ) ?? '' ) ) . '"></label>'; }
  foreach ( array( 's', 'orderby', 'post_type' ) as $key ) { if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) ) { $html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( sanitize_text_field( $input[ $key ] ) ) . '">'; } }
  return $html . '<button type="submit">' . esc_html__( 'Apply filters', 'chidemoon-core' ) . '</button></form>';
 }
 public static function sidebar_filters( array $attributes = array() ): string {
  $input = wp_unslash( $_GET );
  $action = wc_get_page_permalink( 'shop' );
  if ( is_product_category() ) { $link = get_term_link( get_queried_object() ); if ( ! is_wp_error( $link ) ) { $action = $link; } }
  if ( is_search() ) {
   $action = home_url( '/' );
   $input['s'] = get_search_query( false );
   $input['content_type'] = 'product';
  }
  $reset = is_search() ? add_query_arg( array( 's' => $input['s'], 'content_type' => 'product' ), $action ) : $action;
  $html = '<form method="get" action="' . esc_url( $action ) . '" class="ch-sidebar-filters" aria-label="فیلتر محصولات"><fieldset><legend>قیمت (تومان)</legend>';
  foreach ( array( 'ch_min_price' => 'از قیمت', 'ch_max_price' => 'تا قیمت' ) as $key => $label ) {
   $html .= '<label>' . esc_html( $label ) . '<input type="number" inputmode="numeric" min="0" step="1" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) ( self::number_input( $key, $input ) ?? '' ) ) . '" placeholder="تومان"></label>';
  }
  $html .= '</fieldset><fieldset><legend>دسته‌بندی محصولات</legend><div class="ch-sidebar-categories">';
  $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'orderby' => 'name' ) );
  foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) { $html .= self::checkbox( 'ch_category', $term->slug, $term->name, $input ); }
  $html .= '</div></fieldset>';
  foreach ( array( 's', 'orderby', 'post_type', 'content_type' ) as $key ) { if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) ) { $html .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( sanitize_text_field( $input[ $key ] ) ) . '">'; } }
  return $html . '<button type="submit">اعمال فیلتر</button><a class="ch-sidebar-reset" href="' . esc_url( $reset ) . '">پاک کردن فیلترها</a></form>';
 }
 private static function checkbox( string $key, string $value, string $label, array $input ): string { return '<label><input type="checkbox" name="' . esc_attr( $key ) . '[]" value="' . esc_attr( $value ) . '" ' . checked( in_array( $value, self::selection( $key, $input ), true ), true, false ) . '> ' . esc_html( $label ) . '</label> '; }
 private static function facet( string $field, string $taxonomy, array $input ): string {
  $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
  if ( is_wp_error( $terms ) || ! $terms ) { return ''; }
  $html = '<fieldset><legend>' . esc_html( Chidemoon_Core_Enrichment::field_label( $field ) ) . '</legend>';
  foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) { $html .= self::checkbox( 'ch_' . $field, $term->slug, $term->name, $input ); }
  return $html . '</fieldset>';
 }
 public static function fit( array $dimensions, array $space ): string {
  $requested = array_filter( $space, static fn( $value ) => null !== $value );
  if ( ! $requested ) { return 'insufficient_data'; }
  $missing = false;
  foreach ( $requested as $axis => $limit ) { if ( ! isset( $dimensions[ $axis ] ) ) { $missing = true; } elseif ( $dimensions[ $axis ] > $limit ) { return 'exceeds'; } }
  return $missing ? 'insufficient_data' : 'fits';
 }
 public static function rank( array $ids, array $preferences, array $space = array(), ?float $budget = null ): array {
  $results = array();
  foreach ( $ids as $id ) {
   $product = wc_get_product( $id );
   if ( ! $product instanceof WC_Product || ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) || 'in_stock' !== Chidemoon_Core_Commercial::availability( $product ) ) { continue; }
   $observations = Chidemoon_Core_Commercial::observations( $product );
   if ( null !== $budget && ( ! Chidemoon_Core_Commercial::is_fresh( $observations['price']['observedAt'] ?? null ) || '' === $product->get_price() || (float) $product->get_price() > $budget ) ) { continue; }
   $approved = Chidemoon_Core_Enrichment::read( (int) $id );
   $fit = self::fit( $approved['dimensions']['value'] ?? array(), $space );
   if ( 'exceeds' === $fit ) { continue; }
   $matches = array();
   foreach ( $preferences as $field => $preferred ) {
    if ( ! isset( Chidemoon_Core_Enrichment::TAXONOMIES[ $field ] ) || ! $preferred || 'value' !== ( $approved[ $field ]['state'] ?? '' ) ) { continue; }
    $terms = array();
    foreach ( $approved[ $field ]['value'] ?? array() as $term_id ) { $term = get_term( $term_id, Chidemoon_Core_Enrichment::TAXONOMIES[ $field ] ); if ( $term && ! is_wp_error( $term ) && in_array( $term->slug, $preferred, true ) ) { $terms[] = $term->name; } }
    if ( $terms ) { $matches[ $field ] = $terms; }
   }
   $results[] = array( 'id' => (int) $id, 'score' => count( $matches ), 'matches' => $matches, 'fit' => $fit, 'freshness' => max( strtotime( $observations['price']['observedAt'] ?? '' ) ?: 0, strtotime( $observations['availability']['observedAt'] ?? '' ) ?: 0 ), 'identity' => (string) $product->get_meta( Chidemoon_Core_Affiliate::META_SOURCE_KEY, true ) );
  }
  usort( $results, static fn( $left, $right ) => $right['score'] <=> $left['score'] ?: $right['freshness'] <=> $left['freshness'] ?: strcmp( $left['identity'], $right['identity'] ) ?: $left['id'] <=> $right['id'] );
  return $results;
 }
 public static function chooser( array $attributes = array() ): string {
  $input = wp_unslash( $_GET );
  $html = '<form method="get" class="ch-guided-chooser" aria-label="راهنمای انتخاب محصول"><input type="hidden" name="ch_choose" value="1">';
  foreach ( array( 'room', 'style', 'color', 'material' ) as $field ) { $html .= self::facet( $field, Chidemoon_Core_Enrichment::TAXONOMIES[ $field ], $input ); }
  $html .= '<label>' . esc_html__( 'Budget', 'chidemoon-core' ) . ' (' . esc_html( get_woocommerce_currency_symbol() ) . ') <input type="number" inputmode="numeric" min="0" step="1" name="ch_budget" value="' . esc_attr( (string) ( self::number_input( 'ch_budget', $input ) ?? '' ) ) . '"></label>';
  $space = array();
  foreach ( array( 'width', 'depth', 'height' ) as $axis ) { $space[ $axis ] = self::number_input( 'ch_space_' . $axis, $input ); $html .= '<label>' . esc_html( Chidemoon_Core_Enrichment::field_label( $axis ) . ' (سانتی متر)' ) . '<input type="number" inputmode="decimal" min="0" step="0.01" name="ch_space_' . esc_attr( $axis ) . '" value="' . esc_attr( (string) ( $space[ $axis ] ?? '' ) ) . '"></label>'; }
  $html .= '<button type="submit">' . esc_html__( 'Find suitable products', 'chidemoon-core' ) . '</button></form>';
  if ( ! isset( $input['ch_choose'] ) ) { return $html; }
  $requirements = array( 'ch_room' => self::selection( 'ch_room', $input ), 'ch_availability' => array( 'in_stock' ) );
  $query = new WP_Query( array_merge( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ), self::query_filters( $requirements ) ) );
  $preferences = array(); foreach ( array( 'room', 'style', 'color', 'material' ) as $field ) { $preferences[ $field ] = self::selection( 'ch_' . $field, $input ); }
  $results = self::rank( $query->posts, $preferences, $space, self::number_input( 'ch_budget', $input ) );
  if ( ! $results ) { return $html . '<div class="ch-chooser-empty" role="status"><h2>محصولی با این شرایط پیدا نشد</h2><p>انتخاب ها را تغییر بده یا محصولات بیشتری را در فروشگاه ببین.</p><a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">دیدن همه محصولات</a></div>'; }
  $page = max( 1, min( 10000, absint( $input['ch_page'] ?? 1 ) ) );
  $html .= '<div class="ch-chooser-results" data-id="ch-chooser-results" aria-live="polite"><p>' . esc_html( number_format_i18n( count( $results ) ) ) . ' ' . esc_html__( 'matching products', 'chidemoon-core' ) . '</p><ul class="ch-chooser-list">';
  $fit_labels = array( 'fits' => __( 'Fits the supplied measurements', 'chidemoon-core' ), 'exceeds' => __( 'Exceeds the supplied measurements', 'chidemoon-core' ), 'insufficient_data' => __( 'Insufficient measurement data', 'chidemoon-core' ) );
  foreach ( array_slice( $results, ( $page - 1 ) * 12, 12 ) as $result ) {
   $product = wc_get_product( $result['id'] );
   $html .= '<li><a href="' . esc_url( get_permalink( $result['id'] ) ) . '">' . wp_get_attachment_image( $product->get_image_id(), 'woocommerce_thumbnail' ) . esc_html( $product->get_name() ) . '</a> ' . $product->get_price_html();
   if ( 'fits' === $result['fit'] && array_filter( $space, static fn( $value ) => null !== $value ) ) { $html .= '<p>' . esc_html( $fit_labels[ $result['fit'] ] ) . '</p>'; }
   $html .= '<ul>';
   foreach ( $result['matches'] as $field => $terms ) { $html .= '<li>' . esc_html( Chidemoon_Core_Enrichment::field_label( $field ) . ': ' . implode( ', ', $terms ) ) . '</li>'; }
   $html .= '</ul>' . Chidemoon_Core_Compare::single_control( $product ) . '</li>';
  }
  $html .= '</ul><nav class="ch-chooser-pagination" aria-label="صفحه های محصولات پیشنهادی">' . paginate_links( array( 'base' => add_query_arg( 'ch_page', '%#%' ), 'format' => '', 'current' => $page, 'total' => max( 1, (int) ceil( count( $results ) / 12 ) ) ) ) . '</nav></div>';
  return $html;
 }
 public static function related_product_ids( int $product_id, int $limit = 12 ): array {
  $fields = Chidemoon_Core_Enrichment::read( $product_id );
  $preferences = array();
  foreach ( array( 'style', 'room', 'color', 'material', 'category' ) as $field ) {
   foreach ( $fields[ $field ]['value'] ?? array() as $id ) { $term = get_term( $id, Chidemoon_Core_Enrichment::TAXONOMIES[ $field ] ); if ( $term && ! is_wp_error( $term ) ) { $preferences[ $field ][] = $term->slug; } }
  }
  $input = array( 'ch_availability' => array( 'in_stock' ) );
  if ( ! empty( $preferences['category'] ) ) { $input['ch_category'] = $preferences['category']; } else { return array(); }
  $query = new WP_Query( array_merge( array( 'post_type' => 'product', 'post_status' => 'publish', 'post__not_in' => array( $product_id ), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ), self::query_filters( $input ) ) );
  $ranked = self::rank( $query->posts, $preferences );
  return array_column( array_slice( $ranked, 0, $limit ), 'id' );
 }
 public static function alternatives( int $product_id ): string {
  $html = '';
  foreach ( self::related_product_ids( $product_id, 3 ) as $id ) { $html .= '<li><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></li>'; }
  return $html ? '<p>' . esc_html__( 'Available alternatives', 'chidemoon-core' ) . '</p><ul>' . $html . '</ul>' : '';
 }
}
