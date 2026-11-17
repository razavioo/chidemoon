<?php
/** Update the native Elementor footer About box. Run: wp eval-file /tools/footer-about-refresh.php [apply] */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$apply = in_array( 'apply', $args ?? array(), true );
$post = get_page_by_path( 'chidemoon-site-footer', OBJECT, 'elementor_library' );
if ( ! $post ) { WP_CLI::error( 'Footer template not found.' ); }
$data = json_decode( (string) get_post_meta( $post->ID, '_elementor_data', true ), true );
if ( ! is_array( $data ) ) { WP_CLI::error( 'Invalid Elementor data.' ); }
$copy = 'چیدمون جاییه برای ایده گرفتن از چیدمان‌ها و مقایسهٔ وسایل خونه.';
$changed = 0;
$walk = static function ( array &$nodes ) use ( &$walk, &$changed, $copy ): void {
    foreach ( $nodes as &$node ) {
        if ( str_contains( (string) ( $node['settings']['css_classes'] ?? '' ), 'ch-footer-about' ) ) {
            foreach ( $node['elements'] ?? array() as &$child ) {
                if ( 'text-editor' === ( $child['widgetType'] ?? '' ) && ! empty( $child['settings']['editor'] ) && ! str_contains( (string) ( $child['settings']['_css_classes'] ?? '' ), 'ch-footer-tagline' ) ) {
                    $child['settings']['editor'] = '<p>' . esc_html( $copy ) . '</p>'; $changed++; break;
                }
            }
        }
        if ( ! empty( $node['elements'] ) ) { $walk( $node['elements'] ); }
    }
};
$walk( $data );
if ( $apply && $changed ) {
    update_post_meta( $post->ID, '_elementor_data', wp_slash( wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) ) );
    if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
}
WP_CLI::success( ( $apply ? 'Applied' : 'Preview' ) . ': ' . $changed . ' footer About text widget(s).' );
