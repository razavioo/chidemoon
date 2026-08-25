<?php
/** Accessible section navigation for long-form editorial posts. */

if ( ! defined( 'ABSPATH' ) || ! is_singular( 'post' ) || ! function_exists( 'chidemoon_article_section_headings' ) ) {
	return;
}

$post = get_post();
if ( ! $post instanceof WP_Post ) {
	return;
}
$headings = chidemoon_article_section_headings( $post );
if ( count( $headings ) < 2 ) {
	return;
}
?>
<nav class="chidemoon-article-toc" aria-label="<?php esc_attr_e( 'On this page', 'chidemoon-theme' ); ?>">
	<strong><?php esc_html_e( 'On this page', 'chidemoon-theme' ); ?></strong>
	<ol><?php foreach ( $headings as $heading ) : ?><li><a href="#<?php echo esc_attr( (string) $heading['id'] ); ?>"><?php echo esc_html( (string) $heading['label'] ); ?></a></li><?php endforeach; ?></ol>
</nav>
