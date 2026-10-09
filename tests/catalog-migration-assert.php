<?php
if ( ! defined( 'DB_NAME' ) || 'catalog_test' !== DB_NAME || 'local' !== wp_get_environment_type() ) { throw new RuntimeException( 'Isolated catalog database required' ); }
$fixture = get_option( 'chidemoon_migration_fixture' );
if ( get_permalink( $fixture['pageId'] ) !== $fixture['url'] ) { throw new RuntimeException( 'Original editorial URL changed' ); }
$content = get_post_field( 'post_content', $fixture['pageId'] );
$elementor = get_post_meta( $fixture['elementorId'], '_elementor_data', true );
$rolled_back = in_array( 'rollback', $args ?? array(), true );
if ( $rolled_back ) {
 if ( $content !== $fixture['content'] || json_decode( $elementor, true ) !== json_decode( $fixture['elementor'], true ) ) { throw new RuntimeException( 'Rollback did not restore original scene data' ); }
} else {
 if ( ! str_contains( $content, '<p>Editorial content preserved.</p>' ) || ! str_contains( $content, 'look_id=' ) || ! str_contains( $content, 'lookId' ) || str_contains( $content, 'imageId' ) ) { throw new RuntimeException( 'Inline scenes were not replaced with independent references' ); }
 $data = json_decode( $elementor, true ); $scene = $data[0]['elements'][0];
 if ( empty( $scene['settings']['look_id'] ) || isset( $scene['settings']['hotspots'] ) || 8 !== (int) $scene['settings']['border_radius']['size'] ) { throw new RuntimeException( 'Elementor migration lost visual settings or retained inline hotspots' ); }
 $looks = get_posts( array( 'post_type' => 'chidemoon_look', 'post_status' => 'any', 'posts_per_page' => -1, 'meta_key' => '_chidemoon_scene_original_post_id', 'meta_value' => $fixture['pageId'] ) );
 if ( 2 !== count( $looks ) ) { throw new RuntimeException( 'Migration duplicated or lost scenes' ); }
 $unresolved = false;
 foreach ( $looks as $look ) { foreach ( Chidemoon_Core_Looks::relationships( $look->ID ) as $row ) { $unresolved = $unresolved || ( ! $row['product_id'] && str_starts_with( $row['source_key'], 'unresolved:' ) ); } }
 if ( ! $unresolved ) { throw new RuntimeException( 'Unresolved product reference was lost' ); }
}
echo 'PASS: ' . ( $rolled_back ? 'reversible scene migration' : 'independent scenes, stable URLs, visual settings and unresolved references' ) . "\n";
