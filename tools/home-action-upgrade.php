<?php
/** Restore the approved homepage action banner without rebuilding the page. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$post = get_post( (int) get_option( 'page_on_front' ) );
if ( ! $post ) { WP_CLI::error( 'Homepage missing.' ); }
$raw = get_post_meta( $post->ID, '_elementor_data', true );
$nodes = json_decode( $raw, true );
if ( ! is_array( $nodes ) ) { WP_CLI::error( 'Invalid homepage data.' ); }
$image = null;
$existing = false;
$scan = static function ( array $items ) use ( &$scan, &$image, &$existing ): void {
 foreach ( $items as $item ) {
  if ( str_contains( $item['settings']['css_classes'] ?? '', 'ch-home-action' ) ) { $existing = true; }
  if ( null === $image && 'image' === ( $item['widgetType'] ?? '' ) && ! empty( $item['settings']['image']['id'] ) ) { $image = $item['settings']['image']; }
  $scan( $item['elements'] ?? array() );
 }
};
$scan( $nodes );
if ( $existing ) { WP_CLI::success( 'Approved homepage banner already present.' ); return; }
if ( ! $image || ! wp_attachment_is_image( (int) $image['id'] ) ) { WP_CLI::error( 'Homepage scene image missing.' ); }
$widget = static function ( string $type, array $settings ): array {
 return array( 'id' => substr( md5( wp_generate_uuid4() ), 0, 8 ), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
};
$box = static function ( string $class, array $children ): array {
 return array( 'id' => substr( md5( wp_generate_uuid4() ), 0, 8 ), 'elType' => 'container', 'isInner' => false, 'settings' => array( 'content_width' => 'full', 'flex_direction' => 'column', 'css_classes' => $class ), 'elements' => $children );
};
$banner = $box( 'ch-home-action', array(
 $box( 'ch-home-action-copy', array(
  $widget( 'text-editor', array( 'editor' => '<p>از الهام تا چیدمان</p>', '_css_classes' => 'ch-home-action-eyebrow' ) ),
  $widget( 'heading', array( 'title' => 'ایده‌ات را به خانه بیاور', 'header_size' => 'h2' ) ),
  $widget( 'text-editor', array( 'editor' => '<p>چیدمان دلخواهت را پیدا کن؛ ترکیب رنگ‌ها، وسایل و جزئیاتش را ببین و برای خانه خودت ایده بگیر.</p>' ) ),
  $widget( 'button', array( 'text' => 'چیدمان دلخواهم را پیدا کنم', 'link' => array( 'url' => home_url( '/shop-the-look/' ) ), 'align' => 'right', '_css_classes' => 'ch-home-action-button' ) ),
 ) ),
 $widget( 'image', array( 'image' => $image, 'image_size' => 'full', 'open_lightbox' => 'no', '_css_classes' => 'ch-home-action-image' ) ),
) );
$matches = 0;
$replace = static function ( array &$items ) use ( &$replace, &$matches, $banner ): void {
 foreach ( $items as &$item ) {
  if ( 'button' === ( $item['widgetType'] ?? '' ) && in_array( trim( $item['settings']['text'] ?? '' ), array( 'همه مطالب', 'همهٔ مطالب' ), true ) ) { $item = $banner; ++$matches; }
  else { if ( ! empty( $item['elements'] ) ) { $replace( $item['elements'] ); } }
 }
};
$replace( $nodes );
if ( 1 !== $matches ) { WP_CLI::error( 'Expected exactly one homepage archive button; found ' . $matches ); }
if ( in_array( 'apply', $args ?? array(), true ) ) {
 $key = '_chidemoon_before_home_action_20261020';
 if ( ! metadata_exists( 'post', $post->ID, $key ) && ! add_post_meta( $post->ID, $key, wp_slash( $raw ), true ) ) { WP_CLI::error( 'Backup failed.' ); }
 $doc = \Elementor\Plugin::$instance->documents->get( $post->ID, false );
 if ( ! $doc || false === $doc->save( array( 'elements' => $nodes ) ) ) { WP_CLI::error( 'Native save failed.' ); }
 \Elementor\Plugin::$instance->files_manager->clear_cache();
 wp_cache_flush();
 WP_CLI::success( 'Approved homepage action banner installed.' );
} else { WP_CLI::success( 'Preview: replace one archive button with the approved native banner.' ); }
