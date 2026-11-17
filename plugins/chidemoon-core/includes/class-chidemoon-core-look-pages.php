<?php
/** Persistent editorial landing pages, separate from shared product archives. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Look_Pages {
 public const META = '_chidemoon_look_term';
 public static function register(): void {
  add_shortcode( 'chidemoon_look_categories', array( __CLASS__, 'categories' ) );
  add_shortcode( 'chidemoon_look_menu', array( __CLASS__, 'menu' ) );
  add_shortcode( 'chidemoon_look_group', array( __CLASS__, 'group_tabs' ) );
  add_filter( 'body_class', static function( array $classes ): array {
   $parent = get_page_by_path( 'shop-the-look' );
   $directory = is_page( array( 'categories', 'rooms', 'styles' ) ) && $parent && (int) get_post_field( 'post_parent', get_queried_object_id() ) === (int) $parent->ID;
   if ( self::current_term() || $directory ) { $classes[] = 'ch-look-landing'; }
   return $classes;
  } );
  add_action( 'init', array( __CLASS__, 'sync' ), 40 );
  add_action( 'template_redirect', array( __CLASS__, 'redirect_filter' ), 1 );
  add_action( 'wp_head', array( __CLASS__, 'head' ), 5 );
  add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
  add_filter( 'rank_math/frontend/description', static function( $description ) { $term = self::current_term(); return $term ? self::description( $term ) : $description; } );
  add_filter( 'rank_math/frontend/robots', static function( $robots ) {
   if ( self::empty_landing() || ( is_page( 'shop-the-look' ) && array_intersect( array_keys( $_GET ), array( 'room', 'style', 'color' ) ) ) ) { $robots['index'] = 'noindex'; }
   return $robots;
  } );
  add_action( 'init', static function (): void {
   add_rewrite_rule( '^shopthelook/style/([^/]+)/?$', 'index.php?pagename=shop-the-look/style-$matches[1]', 'top' );
   add_rewrite_rule( '^shopthelook/room/([^/]+)/?$', 'index.php?pagename=shop-the-look/room-$matches[1]', 'top' );
   add_rewrite_rule( '^shopthelook/(styles|rooms)/?$', 'index.php?pagename=shop-the-look/$matches[1]', 'top' );
  }, 1 );
 }
 public static function sync(): void {
  global $wpdb;
  if ( '1' !== (string) $wpdb->get_var( "SELECT GET_LOCK('chidemoon_look_pages',0)" ) ) { return; }
  try {
  $parent = get_page_by_path( 'shop-the-look' );
  if ( ! $parent ) { return; }
  foreach ( array( 'rooms' => 'ÙØ¶Ø§Ù‡Ø§ÛŒ Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ÛŒ Ú†ÛŒØ¯Ù…ÙˆÙ†', 'styles' => 'Ø³Ø¨Ú©â€ŒÙ‡Ø§ÛŒ Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ÛŒ Ú†ÛŒØ¯Ù…ÙˆÙ†' ) as $slug => $title ) {
   $group_page = get_page_by_path( 'shop-the-look/' . $slug );
   if ( $group_page && '[chidemoon_look_menu]' === $group_page->post_content ) { wp_update_post( array( 'ID' => $group_page->ID, 'post_content' => '[chidemoon_look_group]' ) ); }
   if ( ! get_page_by_path( 'shop-the-look/' . $slug ) ) { wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $parent->ID, 'post_name' => $slug, 'post_title' => $title, 'post_content' => '[chidemoon_look_menu]' ) ); }
  }
  if ( ! get_page_by_path( 'shop-the-look/categories' ) ) {
   wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $parent->ID, 'post_name' => 'categories', 'post_title' => 'Ø¯Ø³ØªÙ‡â€ŒØ¨Ù†Ø¯ÛŒ Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ÛŒ Ú†ÛŒØ¯Ù…ÙˆÙ†', 'post_content' => '[chidemoon_look_categories]', 'post_excerpt' => 'Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ÛŒ Ú†ÛŒØ¯Ù…ÙˆÙ† Ø±Ø§ Ø¨Ø± Ø§Ø³Ø§Ø³ ÙØ¶Ø§ÛŒ Ø®Ø§Ù†Ù‡ Ùˆ Ø³Ø¨Ú© Ø¯Ú©ÙˆØ±Ø§Ø³ÛŒÙˆÙ† Ø§Ù†ØªØ®Ø§Ø¨ Ú©Ù†ÛŒØ¯.' ) );
  }
  $terms = get_terms( array( 'taxonomy' => array( 'chidemoon_room', 'chidemoon_style' ), 'hide_empty' => false ) );
  if ( is_wp_error( $terms ) ) { return; }
  foreach ( $terms as $term ) {
   if ( 'none' === $term->slug || str_starts_with( $term->slug, 'review-' ) ) { continue; }
   $id = (int) get_term_meta( $term->term_id, '_chidemoon_look_page', true );
   if ( $id && 'page' === get_post_type( $id ) && 'trash' !== get_post_status( $id ) ) {
    if ( '2' !== (string) get_post_meta( $id, '_chidemoon_look_layout', true ) ) {
     $content = (string) get_post_field( 'post_content', $id );
     $start = strpos( $content, '[chidemoon_look_gallery]' );
     if ( false !== $start ) {
      $updated = wp_update_post( array( 'ID' => $id, 'post_title' => self::title( $term ), 'post_content' => substr( $content, $start ) ), true );
      if ( ! is_wp_error( $updated ) && $updated ) { update_post_meta( $id, '_chidemoon_look_layout', 2 ); }
     }
    }
    continue;
   }
   $kind = 'chidemoon_room' === $term->taxonomy ? 'room' : 'style';
   $title = self::title( $term );
   $intro = self::description( $term );
   $content = '[chidemoon_look_gallery]';
   $content .= '<p><a href="' . esc_url( get_permalink( $parent ) ) . '">Ø¯ÛŒØ¯Ù† Ù‡Ù…Ù‡Ù” Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ÛŒ Ú†ÛŒØ¯Ù…ÙˆÙ† Ùˆ ÙÛŒÙ„ØªØ±Ù‡Ø§ÛŒ ÙØ¶Ø§ØŒ Ø³Ø¨Ú© Ùˆ Ø±Ù†Ú¯</a></p>';
   $created = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $parent->ID, 'post_name' => $kind . '-' . $term->slug, 'post_title' => $title, 'post_content' => $content, 'post_excerpt' => $intro, 'meta_input' => array( self::META => $term->term_id ) ), true );
   if ( ! is_wp_error( $created ) && $created ) { update_term_meta( $term->term_id, '_chidemoon_look_page', $created ); update_post_meta( $created, '_chidemoon_look_layout', 2 ); }
  }
  } finally { $wpdb->get_var( "SELECT RELEASE_LOCK('chidemoon_look_pages')" ); }
 }
 public static function url( WP_Term $term ): string {
  $id = (int) get_term_meta( $term->term_id, '_chidemoon_look_page', true );
  $kind = 'chidemoon_style' === $term->taxonomy ? 'style' : 'room';
  return home_url( '/shopthelook/' . $kind . '/' . $term->slug . '/' );
 }
 public static function title( WP_Term $term ): string {
  return 'chidemoon_room' === $term->taxonomy ? 'Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ÛŒ Ú†ÛŒØ¯Ù…ÙˆÙ† Ø¨Ø±Ø§ÛŒ ' . $term->name : 'Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ÛŒ Ú†ÛŒØ¯Ù…ÙˆÙ† Ø¨Ù‡ Ø³Ø¨Ú© ' . $term->name;
 }
 public static function category_link(): string {
  return '';
 }
 public static function menu(): string {
  $space_icon = '<svg class="ch-look-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10 12 3l9 7v11H3Z"/><path d="M8 21v-8h8v8M3 10h18"/></svg>';
  $style_icon = '<svg class="ch-look-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18h1a2 2 0 0 0 1-3.7 1.5 1.5 0 0 1 1-2.8h2A4 4 0 0 0 21 10c0-4-4-7-9-7Z"/><circle cx="7" cy="10" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/><circle cx="6.5" cy="14" r="1"/></svg>';
  return '<nav class="ch-look-menu ch-look-menu--simple" aria-label="Ø¯Ø³ØªÙ‡â€ŒØ¨Ù†Ø¯ÛŒ Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ÛŒ Ú†ÛŒØ¯Ù…ÙˆÙ†"><a class="ch-look-menu-heading" href="' . esc_url( home_url( '/shop-the-look/rooms/' ) ) . '">' . $space_icon . '<span>ÙØ¶Ø§</span></a><a class="ch-look-menu-heading" href="' . esc_url( home_url( '/shop-the-look/styles/' ) ) . '">' . $style_icon . '<span>Ø³Ø¨Ú©</span></a></nav>';
  $html = '<nav class="ch-look-menu" aria-label="Ø¯Ø³ØªÙ‡â€ŒØ¨Ù†Ø¯ÛŒ Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ÛŒ Ú†ÛŒØ¯Ù…ÙˆÙ†">';
  foreach ( array( 'chidemoon_room' => array( 'ÙØ¶Ø§Ù‡Ø§', 'rooms' ), 'chidemoon_style' => array( 'Ø³Ø¨Ú©â€ŒÙ‡Ø§', 'styles' ) ) as $taxonomy => $group ) {
   if ( is_page( 'rooms' ) && 'chidemoon_style' === $taxonomy || is_page( 'styles' ) && 'chidemoon_room' === $taxonomy ) { continue; }
   $html .= '<section><a class="ch-look-menu-heading" href="' . esc_url( home_url( '/shop-the-look/' . $group[1] . '/' ) ) . '">' . $group[0] . '</a><ul>';
   $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name' ) );
   if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) {
    if ( 'none' === $term->slug || str_starts_with( $term->slug, 'review-' ) ) { continue; }
    $html .= '<li><a href="' . esc_url( self::url( $term ) ) . '">' . esc_html( $term->name ) . '</a></li>';
   } }
   $html .= '</ul></section>';
  }
  return $html . '</nav>';
 }
 public static function group_tabs(): string {
  $taxonomy = is_page( 'styles' ) ? 'chidemoon_style' : 'chidemoon_room';
  $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name' ) );
  if ( is_wp_error( $terms ) ) { return ''; }
  $id = wp_unique_id( 'ch-idea-group-' ); $key = $id . '-all'; $index = 1;
  $nav = '<a class="is-active" href="' . esc_url( get_permalink() ) . '" id="' . $key . '-tab" data-panel="' . $key . '">Ù‡Ù…Ù‡</a>';
  $gallery = preg_replace( '/ch-scene-(?=\d)/', $key . '-scene-', Chidemoon_Core_Looks::gallery( array( 'group' => true ) ) );
  $gallery = str_replace( array( 'ch-look-title-', 'ch-look-results' ), array( $key . '-title-', $key . '-results' ), $gallery );
  $panels = '<section id="' . $key . '" class="ch-idea-group-panel" aria-labelledby="' . $key . '-tab">' . $gallery . '</section>';
  foreach ( $terms as $term ) {
   if ( 'none' === $term->slug || str_starts_with( $term->slug, 'review-' ) ) { continue; }
   $key = $id . '-' . $term->term_id;
   $nav .= '<a class="' . ( 0 === $index ? 'is-active' : '' ) . '" href="' . esc_url( self::url( $term ) ) . '" id="' . $key . '-tab" data-panel="' . $key . '">' . esc_html( $term->name ) . '</a>';
   $gallery = preg_replace( '/ch-scene-(?=\d)/', $key . '-scene-', Chidemoon_Core_Looks::gallery( array( 'term' => $term, 'preview_limit' => 3 ) ) );
   $gallery = str_replace( array( 'ch-look-title-', 'ch-look-results' ), array( $key . '-title-', $key . '-results' ), $gallery );
   $gallery .= '<p class="ch-idea-group-more"><a class="chidemoon-button" href="' . esc_url( self::url( $term ) ) . '">Ù†Ù…Ø§ÛŒØ´ Ø¨ÛŒØ´ØªØ± Ø§Ø² ' . esc_html( $term->name ) . '</a></p>';
   $panels .= '<section id="' . $key . '" class="ch-idea-group-panel" aria-labelledby="' . $key . '-tab"' . ( $index ? ' hidden' : '' ) . '><h2>' . esc_html( self::title( $term ) ) . '</h2>' . $gallery . '</section>';
   ++$index;
  }
  return '<div class="ch-idea-group-tabs"><nav class="ch-home-room-tabs" aria-label="' . ( 'chidemoon_room' === $taxonomy ? 'Ø§Ù†ØªØ®Ø§Ø¨ ÙØ¶Ø§' : 'Ø§Ù†ØªØ®Ø§Ø¨ Ø³Ø¨Ú©' ) . '">' . $nav . '</nav>' . $panels . '</div>';
 }
 public static function categories(): string {
  return self::menu();
  $html = '<div class="ch-look-categories">';
  foreach ( array( 'chidemoon_room' => 'Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ Ø¨Ø± Ø§Ø³Ø§Ø³ ÙØ¶Ø§', 'chidemoon_style' => 'Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ Ø¨Ø± Ø§Ø³Ø§Ø³ Ø³Ø¨Ú©' ) as $taxonomy => $title ) {
   $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name' ) );
   if ( is_wp_error( $terms ) ) { continue; }
   $cards = $empty = array();
   foreach ( $terms as $term ) {
    if ( 'none' === $term->slug || str_starts_with( $term->slug, 'review-' ) ) { continue; }
    $image = 0;
    $looks = get_posts( array( 'post_type' => array( 'reycob_look', Chidemoon_Core_Looks::POST_TYPE ), 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ), 'tax_query' => array( array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $term->term_id ) ) ) );
    foreach ( $looks as $look ) {
     if ( get_post_meta( $look->ID, '_chidemoon_reycob_scene', true ) ) { continue; }
     $image = 'reycob_look' === $look->post_type ? (int) get_post_meta( $look->ID, '_reycob_stl_image_id', true ) : (int) get_post_thumbnail_id( $look->ID );
     if ( $image ) { break; }
    }
    $card = '<a class="ch-look-category-card" href="' . esc_url( self::url( $term ) ) . '">' . ( $image ? wp_get_attachment_image( $image, 'medium_large', false, array( 'loading' => 'lazy', 'alt' => $term->name ) ) : '<span class="ch-look-category-placeholder" aria-hidden="true">' . esc_html( $term->name ) . '</span>' ) . '<span class="ch-look-category-name">' . esc_html( $term->name ) . '<span aria-hidden="true">â†</span></span></a>';
    if ( $image ) { $cards[] = $card; } else { $empty[] = $card; }
   }
   $html .= '<section><h2>' . esc_html( $title ) . '</h2><div class="ch-look-category-grid">' . implode( '', array_merge( $cards, $empty ) ) . '</div></section>';
  }
  return $html . '<a class="chidemoon-button" href="' . esc_url( home_url( '/shop-the-look/' ) ) . '">Ù…Ø´Ø§Ù‡Ø¯Ù‡ Ù‡Ù…Ù‡Ù” Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ÛŒ Ú†ÛŒØ¯Ù…ÙˆÙ†</a></div>';
 }
 public static function current_term(): ?WP_Term {
  if ( ! is_page() ) { return null; }
  $id = (int) get_post_meta( get_queried_object_id(), self::META, true );
  $term = $id ? get_term( $id ) : null;
  return $term instanceof WP_Term && in_array( $term->taxonomy, array( 'chidemoon_room', 'chidemoon_style' ), true ) ? $term : null;
 }
 public static function description( WP_Term $term ): string {
  return 'chidemoon_room' === $term->taxonomy
   ? 'Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ÛŒ Ú†ÛŒØ¯Ù…Ø§Ù† ' . $term->name . ' Ø±Ø§ Ø¨Ø§ ØªØµØ§ÙˆÛŒØ± ÙØ¶Ø§ Ùˆ Ø¬Ø²Ø¦ÛŒØ§Øª ÙˆØ³Ø§ÛŒÙ„ Ø¨Ø¨ÛŒÙ†ÛŒØ¯Ø› Ù†Ù…ÙˆÙ†Ù‡â€ŒÙ‡Ø§ Ø±Ø§ Ø¨Ø±Ø§ÛŒ Ø§Ù†ØªØ®Ø§Ø¨ Ø±Ù†Ú¯ØŒ Ù†ÙˆØ± Ùˆ Ù…Ø¨Ù„Ù…Ø§Ù† Ù…ØªÙ†Ø§Ø³Ø¨ Ø¨Ø§ Ø®Ø§Ù†Ù‡Ù” Ø®ÙˆØ¯ Ù…Ù‚Ø§ÛŒØ³Ù‡ Ú©Ù†ÛŒØ¯.'
   : 'Ø§ÛŒØ¯Ù‡â€ŒÙ‡Ø§ÛŒ Ø¯Ú©ÙˆØ±Ø§Ø³ÛŒÙˆÙ† Ø¨Ù‡ Ø³Ø¨Ú© ' . $term->name . ' Ø±Ø§ Ø¯Ø± ØªØµØ§ÙˆÛŒØ± Ú†ÛŒØ¯Ù…ÙˆÙ† Ø¨Ø¨ÛŒÙ†ÛŒØ¯Ø› ØªØ±Ú©ÛŒØ¨ Ø±Ù†Ú¯ØŒ ÙØ±Ù… Ù…Ø¨Ù„Ù…Ø§Ù† Ùˆ Ø¬Ø²Ø¦ÛŒØ§Øª Ù‡Ø± Ú†ÛŒØ¯Ù…Ø§Ù† Ø±Ø§ Ø¨Ø±Ø±Ø³ÛŒ Ùˆ Ø¨Ø±Ø§ÛŒ Ø®Ø§Ù†Ù‡Ù” Ø®ÙˆØ¯ Ø§ÛŒØ¯Ù‡ Ø¨Ú¯ÛŒØ±ÛŒØ¯.';
 }
 public static function advice( WP_Term $term ): string {
  $tips = array(
   'living-room' => 'Ø¯Ø± Ù¾Ø°ÛŒØ±Ø§ÛŒÛŒ Ùˆ Ù†Ø´ÛŒÙ…Ù†ØŒ Ø¬Ø§ÛŒ Ù†Ø´Ø³ØªÙ† Ø±Ø§ Ø¨Ø§ Ù…Ø³ÛŒØ± Ø±ÙØªâ€ŒÙˆØ¢Ù…Ø¯ Ùˆ Ù†Ù‚Ø·Ù‡Ù” Ú©Ø§Ù†ÙˆÙ†ÛŒ Ø§ØªØ§Ù‚ Ù‡Ù…Ø§Ù‡Ù†Ú¯ Ú©Ù†ÛŒØ¯. Ù‡Ù†Ú¯Ø§Ù… Ù…Ù‚Ø§ÛŒØ³Ù‡Ù” ØªØµØ§ÙˆÛŒØ±ØŒ ÙØ§ØµÙ„Ù‡Ù” Ù…Ø¨Ù„â€ŒÙ‡Ø§ØŒ Ø§Ù†Ø¯Ø§Ø²Ù‡Ù” ÙØ±Ø´ Ùˆ Ù†ÙˆØ± Ú©Ù†Ø§Ø± ØµÙ†Ø¯Ù„ÛŒ Ø±Ø§ Ø¯Ø± Ù†Ø¸Ø± Ø¨Ú¯ÛŒØ±ÛŒØ¯.',
   'bedroom' => 'Ø¨Ø±Ø§ÛŒ Ø§ØªØ§Ù‚ Ø®ÙˆØ§Ø¨ØŒ Ø§Ø¨ØªØ¯Ø§ Ø¬Ø§ÛŒ ØªØ®Øª Ùˆ Ø¯Ø³ØªØ±Ø³ÛŒ Ø¯Ùˆ Ø·Ø±Ù Ø¢Ù† Ø±Ø§ Ù…Ø´Ø®Øµ Ú©Ù†ÛŒØ¯. Ù†ÙˆØ± Ú©Ù†Ø§Ø± ØªØ®ØªØŒ ÙØ¶Ø§ÛŒ Ø¨Ø§Ø²Ø´Ø¯Ù† Ú©Ù…Ø¯ Ùˆ Ø±Ù†Ú¯ Ù¾Ø§Ø±Ú†Ù‡â€ŒÙ‡Ø§ Ø¨Ù‡ Ø§Ù†ØªØ®Ø§Ø¨ Ø¬Ø²Ø¦ÛŒØ§Øª Ú©Ù…Ú© Ù…ÛŒâ€ŒÚ©Ù†Ù†Ø¯.',
   'kitchen' => 'Ø¯Ø± Ø¢Ø´Ù¾Ø²Ø®Ø§Ù†Ù‡ØŒ Ø¯Ø³ØªØ±Ø³ÛŒ Ù…ÛŒØ§Ù† Ù…Ø­Ù„ Ø´Ø³Øªâ€ŒÙˆØ´ÙˆØŒ Ø¢Ù…Ø§Ø¯Ù‡â€ŒØ³Ø§Ø²ÛŒ Ùˆ Ù¾Ø®Øª ØºØ°Ø§ Ø±Ø§ Ø¨Ø±Ø±Ø³ÛŒ Ú©Ù†ÛŒØ¯. ÙˆØ³Ø§ÛŒÙ„ Ø±ÙˆÛŒ Ù¾ÛŒØ´Ø®ÙˆØ§Ù† Ø±Ø§ Ø¨Ø§ Ø³Ø·Ø­ Ú©Ø§Ø± Ùˆ ÙØ¶Ø§ÛŒ Ù†Ú¯Ù‡Ø¯Ø§Ø±ÛŒ Ù…ÙˆØ¬ÙˆØ¯ Ø¨Ø³Ù†Ø¬ÛŒØ¯.',
   'reading-corner' => 'Ø¨Ø±Ø§ÛŒ ÙØ¶Ø§ÛŒ Ù…Ø·Ø§Ù„Ø¹Ù‡ØŒ Ø¬Ø§ÛŒ Ù†Ø´Ø³ØªÙ† Ø±Ø§Ø­ØªØŒ Ù†ÙˆØ± Ù‚Ø§Ø¨Ù„ ØªÙ†Ø¸ÛŒÙ… Ùˆ Ø¯Ø³ØªØ±Ø³ÛŒ Ø¨Ù‡ Ú©ØªØ§Ø¨â€ŒÙ‡Ø§ Ø±Ø§ Ú©Ù†Ø§Ø± Ù‡Ù… Ø¨Ø±Ø±Ø³ÛŒ Ú©Ù†ÛŒØ¯. Ú†Ø±Ø§Øº Ø±Ø§ Ø·ÙˆØ±ÛŒ Ù‚Ø±Ø§Ø± Ø¯Ù‡ÛŒØ¯ Ú©Ù‡ Ø¨Ø§Ø²ØªØ§Ø¨ ÛŒØ§ Ø³Ø§ÛŒÙ‡ Ø±ÙˆÛŒ ØµÙØ­Ù‡ Ù…Ø²Ø§Ø­Ù… Ù…Ø·Ø§Ù„Ø¹Ù‡ Ù†Ø¨Ø§Ø´Ø¯.',
   'home-office' => 'Ø¯Ø± Ø§ØªØ§Ù‚ Ú©Ø§Ø±ØŒ Ø¬Ø§ÛŒ Ù…ÛŒØ² Ø±Ø§ Ø¨Ø§ Ù†ÙˆØ± Ù¾Ù†Ø¬Ø±Ù‡ Ùˆ Ù…Ø­Ù„ Ù¾Ø±ÛŒØ²Ù‡Ø§ Ù‡Ù…Ø§Ù‡Ù†Ú¯ Ú©Ù†ÛŒØ¯. Ø§Ø±ØªÙØ§Ø¹ Ø³Ø·Ø­ Ú©Ø§Ø±ØŒ ÙØ¶Ø§ÛŒ Ø­Ø±Ú©Øª ØµÙ†Ø¯Ù„ÛŒ Ùˆ Ù†Ø¸Ù… ÙˆØ³Ø§ÛŒÙ„ Ø±Ø§ Ù¾ÛŒØ´ Ø§Ø² Ø§Ù†ØªØ®Ø§Ø¨ ØªØ²Ø¦ÛŒÙ†Ø§Øª Ø¨Ø³Ù†Ø¬ÛŒØ¯.',
   'kids-room' => 'Ø¯Ø± Ø§ØªØ§Ù‚ Ú©ÙˆØ¯Ú©ØŒ Ù…Ø³ÛŒØ± Ø­Ø±Ú©Øª Ùˆ Ø¯Ø³ØªØ±Ø³ÛŒ Ø¨Ù‡ ÙˆØ³Ø§ÛŒÙ„ Ø±ÙˆØ²Ù…Ø±Ù‡ Ø±Ø§ Ø¨Ø§Ø² Ù†Ú¯Ù‡ Ø¯Ø§Ø±ÛŒØ¯. Ù…Ø¨Ù„Ù…Ø§Ù† Ùˆ Ù…Ø­Ù„ Ù†Ú¯Ù‡Ø¯Ø§Ø±ÛŒ Ø§Ø³Ø¨Ø§Ø¨â€ŒØ¨Ø§Ø²ÛŒ Ø±Ø§ Ù…ØªÙ†Ø§Ø³Ø¨ Ø¨Ø§ Ø³Ù† Ú©ÙˆØ¯Ú© Ùˆ Ø§Ù†Ø¯Ø§Ø²Ù‡Ù” Ø§ØªØ§Ù‚ Ø§Ù†ØªØ®Ø§Ø¨ Ú©Ù†ÛŒØ¯.',
   'terrace' => 'Ø¨Ø±Ø§ÛŒ ØªØ±Ø§Ø³ Ùˆ Ø¨Ø§Ù„Ú©Ù†ØŒ Ù…ÛŒØ²Ø§Ù† ØªØ§Ø¨Ø´ Ø¢ÙØªØ§Ø¨ØŒ Ø¨Ø§Ø±Ù†Ø¯Ú¯ÛŒ Ùˆ Ø¹Ø±Ø¶ Ù…Ø³ÛŒØ± Ø¹Ø¨ÙˆØ± Ø±Ø§ Ø¯Ø± Ù†Ø¸Ø± Ø¨Ú¯ÛŒØ±ÛŒØ¯. Ø§Ù†Ø¯Ø§Ø²Ù‡Ù” ØµÙ†Ø¯Ù„ÛŒâ€ŒÙ‡Ø§ Ùˆ Ø¯ÙˆØ§Ù… Ù…ØªØ±ÛŒØ§Ù„ Ø¯Ø± ÙØ¶Ø§ÛŒ Ø¨Ø§Ø² Ø±Ø§ Ø¨Ø§ Ø´Ø±Ø§ÛŒØ· Ù…Ø­Ù„ Ø¨Ø³Ù†Ø¬ÛŒØ¯.',
   'dining-room' => 'Ø¯Ø± ÙØ¶Ø§ÛŒ Ù†Ø§Ù‡Ø§Ø±Ø®ÙˆØ±ÛŒØŒ Ø¬Ø§ Ø¨Ø±Ø§ÛŒ Ø¹Ù‚Ø¨â€ŒÚ©Ø´ÛŒØ¯Ù† ØµÙ†Ø¯Ù„ÛŒâ€ŒÙ‡Ø§ Ùˆ Ø¹Ø¨ÙˆØ± Ø§Ø² Ú©Ù†Ø§Ø± Ù…ÛŒØ² Ø±Ø§ Ø¨Ø±Ø±Ø³ÛŒ Ú©Ù†ÛŒØ¯. Ø§Ù†Ø¯Ø§Ø²Ù‡Ù” Ù…ÛŒØ² Ùˆ Ù…Ø­Ù„ Ú†Ø±Ø§Øº Ø¨Ø§Ù„Ø§ÛŒ Ø¢Ù† Ø¨Ø§ÛŒØ¯ Ø¨Ø§ Ø§Ø¨Ø¹Ø§Ø¯ ÙˆØ§Ù‚Ø¹ÛŒ ÙØ¶Ø§ Ù‡Ù…Ø§Ù‡Ù†Ú¯ Ø¨Ø§Ø´Ø¯.',
   'entryway' => 'Ø¯Ø± ÙˆØ±ÙˆØ¯ÛŒ Ø®Ø§Ù†Ù‡ØŒ Ù…Ø­Ù„ Ú©ÙØ´ØŒ Ø¢ÙˆÛŒØ² Ù„Ø¨Ø§Ø³ Ùˆ Ø³Ø·Ø­ÛŒ Ø¨Ø±Ø§ÛŒ ÙˆØ³Ø§ÛŒÙ„ Ú©ÙˆÚ†Ú© Ø±Ø§ Ù…Ø´Ø®Øµ Ú©Ù†ÛŒØ¯. Ø¹Ù…Ù‚ Ú©Ù†Ø³ÙˆÙ„ ÛŒØ§ Ø¬Ø§Ú©ÙØ´ÛŒ Ø±Ø§ Ø·ÙˆØ±ÛŒ Ø§Ù†ØªØ®Ø§Ø¨ Ú©Ù†ÛŒØ¯ Ú©Ù‡ Ø¨Ø§Ø²Ø´Ø¯Ù† Ø¯Ø± Ùˆ Ø±ÙØªâ€ŒÙˆØ¢Ù…Ø¯ Ø±Ø§Ø­Øª Ø¨Ù…Ø§Ù†Ø¯.',
   'modern' => 'Ø¯Ø± Ø³Ø¨Ú© Ù…Ø¯Ø±Ù†ØŒ Ø®Ø·ÙˆØ· Ø±ÙˆØ´Ù† Ù…Ø¨Ù„Ù…Ø§Ù† Ùˆ Ø³Ø·ÙˆØ­ Ø³Ø§Ø¯Ù‡ Ø±Ø§ Ø¨Ø§ Ù†ÙˆØ±Ù¾Ø±Ø¯Ø§Ø²ÛŒ Ùˆ Ø¨Ø§ÙØª Ù…ØªØ±ÛŒØ§Ù„ Ù…Ù‚Ø§ÛŒØ³Ù‡ Ú©Ù†ÛŒØ¯. Ø§Ø² ØªØµØ§ÙˆÛŒØ± Ø¨Ø±Ø§ÛŒ Ù¾ÛŒØ¯Ø§ Ú©Ø±Ø¯Ù† ØªØ¹Ø§Ø¯Ù„ Ù…ÛŒØ§Ù† ÙØ¶Ø§ÛŒ Ø®Ø§Ù„ÛŒ Ùˆ ÙˆØ³Ø§ÛŒÙ„ Ø§ØµÙ„ÛŒ Ø§ÛŒØ¯Ù‡ Ø¨Ú¯ÛŒØ±ÛŒØ¯.',
   'classic' => 'Ø¯Ø± Ø³Ø¨Ú© Ú©Ù„Ø§Ø³ÛŒÚ©ØŒ Ø¨Ù‡ ØªÙ‚Ø§Ø±Ù†ØŒ Ø¬Ø²Ø¦ÛŒØ§Øª Ú†ÙˆØ¨ Ùˆ ØªÙ†Ø§Ø³Ø¨ Ø§Ù†Ø¯Ø§Ø²Ù‡Ù” Ù…Ø¨Ù„Ù…Ø§Ù† ØªÙˆØ¬Ù‡ Ú©Ù†ÛŒØ¯. Ù†Ù‚Ø´ ÙØ±Ø´ Ùˆ Ù¾Ø§Ø±Ú†Ù‡ Ø±Ø§ Ú©Ù†Ø§Ø± ØªØ²Ø¦ÛŒÙ†Ø§Øª Ø¨Ø³Ù†Ø¬ÛŒØ¯ ØªØ§ ÙØ¶Ø§ÛŒ Ø§ØªØ§Ù‚ Ø¨ÛŒØ´ Ø§Ø² Ø­Ø¯ Ø´Ù„ÙˆØº Ù†Ø´ÙˆØ¯.',
   'minimal' => 'Ø¯Ø± Ø³Ø¨Ú© Ù…ÛŒÙ†ÛŒÙ…Ø§Ù„ØŒ Ù‡Ø± ÙˆØ³ÛŒÙ„Ù‡ Ø¨Ø§ÛŒØ¯ Ú©Ø§Ø±Ø¨Ø±Ø¯ Ù…Ø´Ø®ØµÛŒ Ø¯Ø§Ø´ØªÙ‡ Ø¨Ø§Ø´Ø¯. Ù†Ù…ÙˆÙ†Ù‡â€ŒÙ‡Ø§ Ø±Ø§ Ø§Ø² Ù†Ø¸Ø± Ù†Ø¸Ù…ØŒ ÙØ¶Ø§ÛŒ Ø®Ø§Ù„ÛŒ Ùˆ ØªÙØ§ÙˆØª Ø¸Ø±ÛŒÙ Ø¨Ø§ÙØªâ€ŒÙ‡Ø§ Ø¨Ø±Ø±Ø³ÛŒ Ú©Ù†ÛŒØ¯Ø› Ø­Ø°Ù ÙˆØ³Ø§ÛŒÙ„ Ù†Ø¨Ø§ÛŒØ¯ Ø±Ø§Ø­ØªÛŒ Ø§Ø³ØªÙØ§Ø¯Ù‡ Ø±Ø§ Ú©Ù… Ú©Ù†Ø¯.',
   'scandinavian' => 'Ø¯Ø± Ø³Ø¨Ú© Ø§Ø³Ú©Ø§Ù†Ø¯ÛŒÙ†Ø§ÙˆÛŒØŒ Ú†ÙˆØ¨ Ø±ÙˆØ´Ù†ØŒ Ù†ÙˆØ± Ø·Ø¨ÛŒØ¹ÛŒ Ùˆ Ù¾Ø§Ø±Ú†Ù‡â€ŒÙ‡Ø§ÛŒ Ø³Ø§Ø¯Ù‡ Ø±Ø§ Ú©Ù†Ø§Ø± Ù‡Ù… Ø¨Ø±Ø±Ø³ÛŒ Ú©Ù†ÛŒØ¯. Ø¨Ø§ÙØªâ€ŒÙ‡Ø§ÛŒ Ú¯Ø±Ù… Ù…ÛŒâ€ŒØªÙˆØ§Ù†Ù†Ø¯ Ø¯Ø± Ú©Ù†Ø§Ø± Ø±Ù†Ú¯â€ŒÙ‡Ø§ÛŒ Ø±ÙˆØ´Ù† Ø¨Ù‡ ÙØ¶Ø§ÛŒ Ù†Ø´Ø³ØªÙ† ØªÙ†ÙˆØ¹ Ø¨Ø¯Ù‡Ù†Ø¯.',
   'boho' => 'Ø¯Ø± Ø³Ø¨Ú© Ø¨ÙˆÙ‡ÙˆØŒ Ø¨Ø§ÙØª Ø­ØµÛŒØ±ØŒ Ù¾Ø§Ø±Ú†Ù‡ Ùˆ Ú†ÙˆØ¨ Ø±Ø§ Ø¨Ø§ Ù†Ù‚Ø´â€ŒÙ‡Ø§ Ùˆ Ú¯ÛŒØ§Ù‡Ø§Ù† Ù…Ù‚Ø§ÛŒØ³Ù‡ Ú©Ù†ÛŒØ¯. Ø¨Ø±Ø§ÛŒ Ù‡Ù…Ø§Ù‡Ù†Ú¯ÛŒ ØªØ±Ú©ÛŒØ¨â€ŒÙ‡Ø§ÛŒ Ù…ØªÙ†ÙˆØ¹ØŒ Ú†Ù†Ø¯ Ø±Ù†Ú¯ Ù…Ø´ØªØ±Ú© Ù…ÛŒØ§Ù† ÙˆØ³Ø§ÛŒÙ„ Ø§Ù†ØªØ®Ø§Ø¨ Ú©Ù†ÛŒØ¯.',
   'rustic' => 'Ø¯Ø± Ø³Ø¨Ú© Ø±ÙˆØ³ØªÛŒÚ©ØŒ Ø¨Ø§ÙØª Ø·Ø¨ÛŒØ¹ÛŒ Ú†ÙˆØ¨ Ùˆ Ù…ØµØ§Ù„Ø­ Ø±Ø§ Ø¨Ø§ ÙØ±Ù… Ù…Ø¨Ù„Ù…Ø§Ù† Ùˆ Ù†ÙˆØ± Ú¯Ø±Ù… Ù…Ù‚Ø§ÛŒØ³Ù‡ Ú©Ù†ÛŒØ¯. Ø§Ø¨Ø¹Ø§Ø¯ Ùˆ ÙˆØ²Ù† Ø¨ØµØ±ÛŒ ÙˆØ³Ø§ÛŒÙ„ Ø±Ø§ Ø¨Ø§ ÙØ¶Ø§ÛŒ Ù‚Ø§Ø¨Ù„ Ø§Ø³ØªÙØ§Ø¯Ù‡Ù” Ø§ØªØ§Ù‚ Ø¨Ø³Ù†Ø¬ÛŒØ¯.',
  );
  return $tips[ $term->slug ] ?? 'Ø¯Ø± Ù†Ù…ÙˆÙ†Ù‡â€ŒÙ‡Ø§ÛŒ ' . $term->name . 'ØŒ ØªØ±Ú©ÛŒØ¨ Ø±Ù†Ú¯ Ùˆ Ø¨Ø§ÙØª Ø±Ø§ Ú©Ù†Ø§Ø± Ø§Ù†Ø¯Ø§Ø²Ù‡Ù” ÙˆØ³Ø§ÛŒÙ„ Ø¨Ø±Ø±Ø³ÛŒ Ú©Ù†ÛŒØ¯. Ù¾ÛŒØ´ Ø§Ø² Ø§Ù†ØªØ®Ø§Ø¨ØŒ Ø§Ø¨Ø¹Ø§Ø¯ ÙˆØ§Ù‚Ø¹ÛŒ ÙØ¶Ø§ Ùˆ Ù…Ø³ÛŒØ± Ø±ÙØªâ€ŒÙˆØ¢Ù…Ø¯ Ø±Ø§ Ø§Ù†Ø¯Ø§Ø²Ù‡ Ø¨Ú¯ÛŒØ±ÛŒØ¯Ø› Ø¹Ú©Ø³ Ø¨Ù‡â€ŒØªÙ†Ù‡Ø§ÛŒÛŒ Ù…Ù‚ÛŒØ§Ø³ Ø¯Ù‚ÛŒÙ‚ Ø§ØªØ§Ù‚ Ø±Ø§ Ù†Ø´Ø§Ù† Ù†Ù…ÛŒâ€ŒØ¯Ù‡Ø¯.';
 }
 public static function redirect_filter(): void {
  $path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
  $aliases = get_option( 'chidemoon_look_page_redirects', array() );
  if ( is_string( $path ) && isset( $aliases[$path] ) && 'publish' === get_post_status( (int) $aliases[$path] ) ) { wp_safe_redirect( get_permalink( (int) $aliases[$path] ), 301 ); exit; }
  if ( self::current_term() ) { remove_action( 'wp_head', 'hello_elementor_add_description_meta_tag' ); }
  if ( ! is_page( 'shop-the-look' ) || is_preview() ) { return; }
  $input = array_filter( $_GET, static fn( $v ) => '' !== $v );
  if ( count( $input ) !== 1 ) { return; }
  $key = array_key_first( $input );
  if ( ! in_array( $key, array( 'room', 'style' ), true ) || ! is_string( $input[$key] ) ) { return; }
  $term = get_term_by( 'slug', sanitize_title( wp_unslash( $input[$key] ) ), 'room' === $key ? 'chidemoon_room' : 'chidemoon_style' );
  if ( $term instanceof WP_Term && 'none' !== $term->slug ) { wp_safe_redirect( self::url( $term ), 301 ); exit; }
 }
 public static function head(): void {
  $term = self::current_term();
  if ( ! $term ) { return; }
  echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => get_the_title( get_queried_object_id() ), 'url' => get_permalink(), 'description' => self::description( $term ), 'inLanguage' => 'fa-IR' ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . '</script>';
 }
 public static function robots( array $robots ): array {
  if ( self::empty_landing() || ( is_page( 'shop-the-look' ) && array_intersect( array_keys( $_GET ), array( 'room', 'style', 'color' ) ) ) ) { $robots['noindex'] = true; }
  return $robots;
 }
 private static function empty_landing(): bool {
  if ( ! self::current_term() ) { return false; }
  static $empty = null;
  if ( null === $empty ) { $empty = ! str_contains( Chidemoon_Core_Looks::gallery(), 'class="ch-look-gallery-scene"' ); }
  return $empty;
 }
}


