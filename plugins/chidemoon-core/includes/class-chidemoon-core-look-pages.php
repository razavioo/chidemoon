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
   $directory = is_page( array( 'categories', 'room', 'styles' ) ) && $parent && (int) get_post_field( 'post_parent', get_queried_object_id() ) === (int) $parent->ID;
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
   add_rewrite_rule( '^shopthelook/(styles|room)/?$', 'index.php?pagename=shop-the-look/$matches[1]', 'top' );
  }, 1 );
 }
 public static function sync(): void {
  global $wpdb;
  if ( '1' !== (string) $wpdb->get_var( "SELECT GET_LOCK('chidemoon_look_pages',0)" ) ) { return; }
  try {
  $parent = get_page_by_path( 'shop-the-look' );
  if ( ! $parent ) { return; }
  foreach ( array( 'room' => 'فضاهای ایده‌های چیدمون', 'styles' => 'سبک‌های ایده‌های چیدمون' ) as $slug => $title ) {
   $group_page = get_page_by_path( 'shop-the-look/' . $slug );
   if ( $group_page && '[chidemoon_look_menu]' === $group_page->post_content ) { wp_update_post( array( 'ID' => $group_page->ID, 'post_content' => '[chidemoon_look_group]' ) ); }
   if ( ! get_page_by_path( 'shop-the-look/' . $slug ) ) { wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $parent->ID, 'post_name' => $slug, 'post_title' => $title, 'post_content' => '[chidemoon_look_group]' ) ); }
  }
  if ( ! get_page_by_path( 'shop-the-look/categories' ) ) {
   wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $parent->ID, 'post_name' => 'categories', 'post_title' => 'دسته‌بندی ایده‌های چیدمون', 'post_content' => '[chidemoon_look_categories]', 'post_excerpt' => 'ایده‌های چیدمون را بر اساس فضای خانه و سبک دکوراسیون انتخاب کنید.' ) );
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
   $content .= '<p><a href="' . esc_url( get_permalink( $parent ) ) . '">دیدن همهٔ ایده‌های چیدمون و فیلترهای فضا، سبک و رنگ</a></p>';
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
  return 'chidemoon_room' === $term->taxonomy ? 'ایده‌های چیدمون برای ' . $term->name : 'ایده‌های چیدمون به سبک ' . $term->name;
 }
 public static function category_link(): string {
  return '';
 }
 public static function menu(): string {
  $space_icon = '<svg class="ch-look-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10 12 3l9 7v11H3Z"/><path d="M8 21v-8h8v8M3 10h18"/></svg>';
  $style_icon = '<svg class="ch-look-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18h1a2 2 0 0 0 1-3.7 1.5 1.5 0 0 1 1-2.8h2A4 4 0 0 0 21 10c0-4-4-7-9-7Z"/><circle cx="7" cy="10" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/><circle cx="6.5" cy="14" r="1"/></svg>';
  return '<nav class="ch-look-menu ch-look-menu--simple" aria-label="دسته‌بندی ایده‌های چیدمون"><a class="ch-look-menu-heading" href="' . esc_url( home_url( '/shop-the-look/room/' ) ) . '">' . $space_icon . '<span>فضا</span></a><a class="ch-look-menu-heading" href="' . esc_url( home_url( '/shop-the-look/styles/' ) ) . '">' . $style_icon . '<span>سبک</span></a></nav>';
  $html = '<nav class="ch-look-menu" aria-label="دسته‌بندی ایده‌های چیدمون">';
  foreach ( array( 'chidemoon_room' => array( 'فضاها', 'room' ), 'chidemoon_style' => array( 'سبک‌ها', 'styles' ) ) as $taxonomy => $group ) {
   if ( is_page( 'room' ) && 'chidemoon_style' === $taxonomy || is_page( 'styles' ) && 'chidemoon_room' === $taxonomy ) { continue; }
   $html .= '<section><a class="ch-look-menu-heading" href="' . esc_url( home_url( '/shop-the-look/' . $group[1] . '/' ) ) . '">' . $group[0] . '</a><ul>';
   $terms = Chidemoon_Core_Primary_Facets::terms($taxonomy);
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
  $terms = Chidemoon_Core_Primary_Facets::terms($taxonomy);
  if ( is_wp_error( $terms ) ) { return ''; }
  $id = wp_unique_id( 'ch-idea-group-' ); $key = $id . '-all'; $index = 1;
  $nav = '<a class="is-active" href="' . esc_url( get_permalink() ) . '" id="' . $key . '-tab" data-panel="' . $key . '">همه</a>';
  $gallery = preg_replace( '/ch-scene-(?=\d)/', $key . '-scene-', Chidemoon_Core_Looks::gallery( array( 'group' => true ) ) );
  $gallery = str_replace( array( 'ch-look-title-', 'ch-look-results' ), array( $key . '-title-', $key . '-results' ), $gallery );
  $panels = '<section id="' . $key . '" class="ch-idea-group-panel" aria-labelledby="' . $key . '-tab">' . $gallery . '</section>';
  foreach ( $terms as $term ) {
   if ( 'none' === $term->slug || str_starts_with( $term->slug, 'review-' ) ) { continue; }
   $key = $id . '-' . $term->term_id;
   $nav .= '<a class="' . ( 0 === $index ? 'is-active' : '' ) . '" href="' . esc_url( self::url( $term ) ) . '" id="' . $key . '-tab" data-panel="' . $key . '">' . esc_html( $term->name ) . '</a>';
   $gallery = preg_replace( '/ch-scene-(?=\d)/', $key . '-scene-', Chidemoon_Core_Looks::gallery( array( 'term' => $term, 'preview_limit' => 4 ) ) );
   $gallery = str_replace( array( 'ch-look-title-', 'ch-look-results' ), array( $key . '-title-', $key . '-results' ), $gallery );
   $gallery .= '<p class="ch-idea-group-more"><a class="chidemoon-button" href="' . esc_url( self::url( $term ) ) . '">نمایش بیشتر از ' . esc_html( $term->name ) . '</a></p>';
   $panels .= '<section id="' . $key . '" class="ch-idea-group-panel" aria-labelledby="' . $key . '-tab"' . ( $index ? ' hidden' : '' ) . '><h2>' . esc_html( self::title( $term ) ) . '</h2>' . $gallery . '</section>';
   ++$index;
  }
  return '<div class="ch-idea-group-tabs"><nav class="ch-home-room-tabs" aria-label="' . ( 'chidemoon_room' === $taxonomy ? 'انتخاب فضا' : 'انتخاب سبک' ) . '">' . $nav . '</nav>' . $panels . '</div>';
 }
 public static function categories(): string {
  return self::menu();
  $html = '<div class="ch-look-categories">';
  foreach ( array( 'chidemoon_room' => 'ایده‌ها بر اساس فضا', 'chidemoon_style' => 'ایده‌ها بر اساس سبک' ) as $taxonomy => $title ) {
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
  return $html . '<a class="chidemoon-button" href="' . esc_url( home_url( '/shop-the-look/' ) ) . '">مشاهده همهٔ ایده‌های چیدمون</a></div>';
 }
 public static function current_term(): ?WP_Term {
  if ( ! is_page() ) { return null; }
  $id = (int) get_post_meta( get_queried_object_id(), self::META, true );
  $term = $id ? get_term( $id ) : null;
  return $term instanceof WP_Term && in_array( $term->taxonomy, array( 'chidemoon_room', 'chidemoon_style' ), true ) ? $term : null;
 }
 public static function description( WP_Term $term ): string {
  return 'chidemoon_room' === $term->taxonomy
   ? 'ایده‌های چیدمان ' . $term->name . ' را با تصاویر فضا و جزئیات وسایل ببینید؛ نمونه‌ها را برای انتخاب رنگ، نور و مبلمان متناسب با خانهٔ خود مقایسه کنید.'
   : 'ایده‌های دکوراسیون به سبک ' . $term->name . ' را در تصاویر چیدمون ببینید؛ ترکیب رنگ، فرم مبلمان و جزئیات هر چیدمان را بررسی و برای خانهٔ خود ایده بگیرید.';
 }
 public static function advice( WP_Term $term ): string {
  $tips = array(
   'living-room' => 'در پذیرایی و نشیمن، جای نشستن را با مسیر رفت‌وآمد و نقطهٔ کانونی اتاق هماهنگ کنید. هنگام مقایسهٔ تصاویر، فاصلهٔ مبل‌ها، اندازهٔ فرش و نور کنار صندلی را در نظر بگیرید.',
   'bedroom' => 'برای اتاق خواب، ابتدا جای تخت و دسترسی دو طرف آن را مشخص کنید. نور کنار تخت، فضای بازشدن کمد و رنگ پارچه‌ها به انتخاب جزئیات کمک می‌کنند.',
   'kitchen' => 'در آشپزخانه، دسترسی میان محل شست‌وشو، آماده‌سازی و پخت غذا را بررسی کنید. وسایل روی پیشخوان را با سطح کار و فضای نگهداری موجود بسنجید.',
   'reading-corner' => 'برای فضای مطالعه، جای نشستن راحت، نور قابل تنظیم و دسترسی به کتاب‌ها را کنار هم بررسی کنید. چراغ را طوری قرار دهید که بازتاب یا سایه روی صفحه مزاحم مطالعه نباشد.',
   'home-office' => 'در اتاق کار، جای میز را با نور پنجره و محل پریزها هماهنگ کنید. ارتفاع سطح کار، فضای حرکت صندلی و نظم وسایل را پیش از انتخاب تزئینات بسنجید.',
   'kids-room' => 'در اتاق کودک، مسیر حرکت و دسترسی به وسایل روزمره را باز نگه دارید. مبلمان و محل نگهداری اسباب‌بازی را متناسب با سن کودک و اندازهٔ اتاق انتخاب کنید.',
   'terrace' => 'برای تراس و بالکن، میزان تابش آفتاب، بارندگی و عرض مسیر عبور را در نظر بگیرید. اندازهٔ صندلی‌ها و دوام متریال در فضای باز را با شرایط محل بسنجید.',
   'dining-room' => 'در فضای ناهارخوری، جا برای عقب‌کشیدن صندلی‌ها و عبور از کنار میز را بررسی کنید. اندازهٔ میز و محل چراغ بالای آن باید با ابعاد واقعی فضا هماهنگ باشد.',
   'entryway' => 'در ورودی خانه، محل کفش، آویز لباس و سطحی برای وسایل کوچک را مشخص کنید. عمق میز کنسول یا جاکفشی را طوری انتخاب کنید که بازشدن در و رفت‌وآمد راحت بماند.',
   'modern' => 'در سبک مدرن، خطوط روشن مبلمان و سطوح ساده را با نورپردازی و بافت متریال مقایسه کنید. از تصاویر برای پیدا کردن تعادل میان فضای خالی و وسایل اصلی ایده بگیرید.',
   'classic' => 'در سبک کلاسیک، به تقارن، جزئیات چوب و تناسب اندازهٔ مبلمان توجه کنید. نقش فرش و پارچه را کنار تزئینات بسنجید تا فضای اتاق بیش از حد شلوغ نشود.',
   'minimal' => 'در سبک مینیمال، هر وسیله باید کاربرد مشخصی داشته باشد. نمونه‌ها را از نظر نظم، فضای خالی و تفاوت ظریف بافت‌ها بررسی کنید؛ حذف وسایل نباید راحتی استفاده را کم کند.',
   'scandinavian' => 'در سبک اسکاندیناوی، چوب روشن، نور طبیعی و پارچه‌های ساده را کنار هم بررسی کنید. بافت‌های گرم می‌توانند در کنار رنگ‌های روشن به فضای نشستن تنوع بدهند.',
   'boho' => 'در سبک بوهو، بافت حصیر، پارچه و چوب را با نقش‌ها و گیاهان مقایسه کنید. برای هماهنگی ترکیب‌های متنوع، چند رنگ مشترک میان وسایل انتخاب کنید.',
   'rustic' => 'در سبک روستیک، بافت طبیعی چوب و مصالح را با فرم مبلمان و نور گرم مقایسه کنید. ابعاد و وزن بصری وسایل را با فضای قابل استفادهٔ اتاق بسنجید.',
  );
  return $tips[ $term->slug ] ?? 'در نمونه‌های ' . $term->name . '، ترکیب رنگ و بافت را کنار اندازهٔ وسایل بررسی کنید. پیش از انتخاب، ابعاد واقعی فضا و مسیر رفت‌وآمد را اندازه بگیرید؛ عکس به‌تنهایی مقیاس دقیق اتاق را نشان نمی‌دهد.';
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
