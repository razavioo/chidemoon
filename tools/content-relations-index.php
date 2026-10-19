<?php
/** Rebuild the exact-reference index for existing content; never changes editorial bodies. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) { exit; }
$ids = get_posts( array( 'post_type' => Chidemoon_Core_Content_Relations::TYPES, 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
foreach ( $ids as $id ) { Chidemoon_Core_Content_Relations::index( (int) $id ); }
WP_CLI::success( 'Indexed product references for ' . count( $ids ) . ' content records.' );
