<?php
/** Update the native Elementor footer About box. Run: wp eval-file /tools/footer-about-refresh.php [apply] */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$apply = in_array( 'apply', $args ?? array(), true );
$post = get_page_by_path( 'chidemoon-site-footer', OBJECT, 'elementor_library' );
if ( ! $post ) { WP_CLI::error( 'Footer template not found.' ); }
$data = json_decode( (string) get_post_meta( $post->ID, '_elementor_data', true ), true );
if ( ! is_array( $data ) ) { WP_CLI::error( 'Invalid Elementor data.' ); }
$copy = 'Ù…Ø§ Ø¯Ø± Ú†ÛŒØ¯Ù…ÙˆÙ† Ù…Ø¹ØªÙ‚Ø¯ÛŒÙ… Ø²ÛŒØ¨Ø§ÛŒÛŒ Ø®Ø§Ù†Ù‡ Ø¯Ø± Ø§Ù†ØªØ®Ø§Ø¨ Ù‡ÙˆØ´Ù…Ù†Ø¯Ø§Ù†Ù‡ Ùˆ Ú†ÛŒØ¯Ù…Ø§Ù†Ù Ø¢Ú¯Ø§Ù‡Ø§Ù†Ù‡ Ø§Ø³Øª. Ú†ÛŒØ¯Ù…ÙˆÙ† Ø¨Ù‡ Ø¹Ù†ÙˆØ§Ù† Ù‡Ù…Ø±Ø§Ù‡ Ø´Ù…Ø§ØŒ Ø³Ø±Ø¯Ø±Ú¯Ù…ÛŒ Ø¯Ø± Ø¨Ø§Ø²Ø§Ø± Ø´Ù„ÙˆØº Ù„ÙˆØ§Ø²Ù… Ø®Ø§Ù†Ú¯ÛŒ Ø±Ø§ Ø¨Ù‡ Ù„Ø°ØªÙ Ø®Ù„Ù‚ ÛŒÚ© ÙØ¶Ø§ÛŒ Ø¯ÙˆØ³Øª Ø¯Ø§Ø´ØªÙ†ÛŒ ØªØ¨Ø¯ÛŒÙ„ Ù…ÛŒâ€ŒÚ©Ù†Ø¯. Ù…Ø§ Ø¨Ù‡ Ø¬Ø§ÛŒ Ø´Ù…Ø§ ØªØ­Ù‚ÛŒÙ‚ Ù…ÛŒâ€ŒÚ©Ù†ÛŒÙ…ØŒ Ø¨Ù‡ØªØ±ÛŒÙ†â€ŒÙ‡Ø§ Ø±Ø§ Ø¨Ø± Ø§Ø³Ø§Ø³ Ø³Ø¨Ú© Ø²Ù†Ø¯Ú¯ÛŒ Ùˆ Ø¨ÙˆØ¯Ø¬Ù‡â€ŒØªØ§Ù† ÙÛŒÙ„ØªØ± Ù…ÛŒâ€ŒÚ©Ù†ÛŒÙ… Ùˆ Ø¨Ù‡ Ø´Ù…Ø§ Ù†Ø´Ø§Ù† Ù…ÛŒâ€ŒØ¯Ù‡ÛŒÙ… Ú©Ù‡ Ù‡Ø± ÙˆØ³ÛŒÙ„Ù‡ØŒ Ú†Ø·ÙˆØ± Ø¯Ø± Ú©Ù†Ø§Ø± Ø¨Ù‚ÛŒÙ‡ Ø§Ø¬Ø²Ø§ÛŒ Ø®Ø§Ù†Ù‡â€ŒØªØ§Ù† Ù…ÛŒâ€ŒÙ†Ø´ÛŒÙ†Ø¯. Ø¯Ø± Ú†ÛŒØ¯Ù…ÙˆÙ†ØŒ Ù…Ø§ ÙÙ‚Ø· Ù…Ø­ØµÙˆÙ„ Ù…Ø¹Ø±ÙÛŒ Ù†Ù…ÛŒâ€ŒÚ©Ù†ÛŒÙ…Ø› Ù…Ø§ Ø¨Ù‡ Ø´Ù…Ø§ Ú©Ù…Ú© Ù…ÛŒâ€ŒÚ©Ù†ÛŒÙ… ØªØ§ Ø¯Ø§Ø³ØªØ§Ù†ÛŒ Ù…Ù†Ø­ØµØ±Ø¨Ù‡â€ŒÙØ±Ø¯ Ø¨Ø±Ø§ÛŒ ÙØ¶Ø§ÛŒ Ø²Ù†Ø¯Ú¯ÛŒâ€ŒØªØ§Ù† Ø¨Ù†ÙˆÛŒØ³ÛŒØ¯.';
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

