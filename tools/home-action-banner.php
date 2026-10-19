<?php
/** Replace the home archive button with an editable Elementor action banner. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$id = (int) get_option( 'page_on_front' );
$raw = get_post_meta( $id, '_elementor_data', true );
$nodes = json_decode( $raw, true );
$image = null;
$find = static function ( $items ) use ( &$find, &$image ) {
    foreach ( $items as $item ) {
        if ( ! $image && 'image' === ( $item['widgetType'] ?? '' ) ) { $image = $item['settings']['image']; }
        $find( $item['elements'] ?? array() );
    }
};
$find( $nodes );
if ( ! $image ) { WP_CLI::error( 'Home image not found.' ); }
$widget = static fn( $id, $type, $settings ) => array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
$banner = array( 'id' => 'chaction', 'elType' => 'container', 'isInner' => true, 'settings' => array( 'content_width' => 'full', 'css_classes' => 'ch-home-action' ), 'elements' => array(
    array( 'id' => 'chactcopy', 'elType' => 'container', 'isInner' => true, 'settings' => array( 'content_width' => 'full', 'css_classes' => 'ch-home-action-copy' ), 'elements' => array(
        $widget( 'chacteye', 'text-editor', array( 'editor' => '<p>از الهام تا چیدمان</p>', '_css_classes' => 'ch-home-action-eyebrow' ) ),
        $widget( 'chacthead', 'heading', array( 'title' => 'ایده‌ات را به خانه بیاور', 'header_size' => 'h2' ) ),
        $widget( 'chacttext', 'text-editor', array( 'editor' => '<p>چیدمان دلخواهت را پیدا کن؛ ترکیب رنگ‌ها، وسایل و جزئیاتش را ببین و برای خانه خودت ایده بگیر.</p>' ) ),
        $widget( 'chactlink', 'button', array( 'text' => 'چیدمان دلخواهم را پیدا کنم', 'link' => array( 'url' => home_url( '/shop-the-look/' ) ), '_css_classes' => 'ch-home-action-button' ) ),
    ) ),
    $widget( 'chactimg', 'image', array( 'image' => $image, 'image_size' => 'full', '_css_classes' => 'ch-home-action-image' ) ),
) );
$changed = false;
$replace = static function ( &$items ) use ( &$replace, &$changed, $banner ) {
    foreach ( $items as &$item ) {
        if ( 'chaction' === ( $item['id'] ?? '' ) ) { return; }
        if ( 'button' === ( $item['widgetType'] ?? '' ) && preg_match( '/همه.*مطالب/u', $item['settings']['text'] ?? '' ) ) { $item = $banner; $changed = true; }
        else { if ( ! empty( $item['elements'] ) ) { $replace( $item['elements'] ); } }
    }
};
$replace( $nodes );
if ( $changed ) {
    add_post_meta( $id, '_chidemoon_before_home_action_banner', $raw, true );
    $doc = \Elementor\Plugin::$instance->documents->get( $id );
    if ( ! $doc->save( array( 'elements' => $nodes ) ) ) { WP_CLI::error( 'Save failed.' ); }
    \Elementor\Plugin::$instance->files_manager->clear_cache();
    wp_cache_flush();
}
WP_CLI::success( $changed ? 'Home action banner saved.' : 'No changes needed.' );
