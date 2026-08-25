<?php
/** Reviewed article provenance and reading context. */

if ( ! defined( 'ABSPATH' ) || ! is_singular( 'post' ) ) {
	return;
}

$post_id = get_queried_object_id();
$types   = get_the_terms( $post_id, 'chidemoon_content_type' );
$type    = is_array( $types ) && isset( $types[0] ) && $types[0] instanceof WP_Term ? $types[0]->name : '';
?>
<div class="chidemoon-article-context" aria-label="<?php esc_attr_e( 'Article details', 'chidemoon-theme' ); ?>">
	<?php if ( '' !== $type ) : ?><span><?php echo esc_html( $type ); ?></span><?php endif; ?>
	<span><?php echo esc_html( sprintf( _n( '%d min read', '%d min read', chidemoon_editorial_reading_minutes( $post_id ), 'chidemoon-theme' ), chidemoon_editorial_reading_minutes( $post_id ) ) ); ?></span>
</div>
