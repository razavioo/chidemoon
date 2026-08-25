<?php
/**
 * Reviewed editorial discovery grid.
 *
 * @var array $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mode = sanitize_key( (string) ( $attributes['mode'] ?? 'magazine' ) );
if ( ! in_array( $mode, array( 'magazine', 'guides', 'shop-look', 'search' ), true ) ) {
	$mode = 'magazine';
}
$per_page    = max( 3, min( 24, absint( $attributes['perPage'] ?? 12 ) ) );
$show_filter = ! isset( $attributes['showFilters'] ) || ! empty( $attributes['showFilters'] );
$paged       = max( 1, absint( get_query_var( 'paged' ) ?: get_query_var( 'page' ) ?: 1 ) );
$topic       = isset( $_GET['topic'] ) ? sanitize_title( wp_unslash( $_GET['topic'] ) ) : '';
$query_args  = array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => $per_page,
	'paged'               => $paged,
	'orderby'             => 'date',
	'order'               => 'DESC',
	'ignore_sticky_posts' => true,
);
$tax_query = array();
if ( 'guides' === $mode ) {
	$tax_query[] = array( 'taxonomy' => 'chidemoon_content_type', 'field' => 'slug', 'terms' => array( 'guide' ) );
} elseif ( 'shop-look' === $mode ) {
	$tax_query[] = array( 'taxonomy' => 'chidemoon_content_type', 'field' => 'slug', 'terms' => array( 'shop-look' ) );
} elseif ( 'search' === $mode ) {
	$query_args['s'] = get_search_query( false );
}
if ( '' !== $topic && 'search' !== $mode ) {
	$tax_query[] = array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => array( $topic ) );
}
if ( ! empty( $tax_query ) ) {
	$query_args['tax_query'] = count( $tax_query ) > 1 ? array_merge( array( 'relation' => 'AND' ), $tax_query ) : $tax_query;
}

$editorial = new WP_Query( $query_args );
$categories = $show_filter && 'search' !== $mode
	? get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true, 'number' => 12, 'orderby' => 'count', 'order' => 'DESC' ) )
	: array();
$categories = is_wp_error( $categories ) || ! is_array( $categories ) ? array() : $categories;
$base_url   = get_permalink( get_queried_object_id() ) ?: home_url( '/' );
$wrapper    = get_block_wrapper_attributes( array( 'class' => 'chidemoon-editorial-index chidemoon-editorial-index--' . $mode ) );
$has_public_editorial = ! $editorial->have_posts() && chidemoon_public_editorial_available();
$has_public_guides    = ! $editorial->have_posts() && 'shop-look' === $mode && chidemoon_public_editorial_available( 'guide' );
?>
<section <?php echo $wrapper; ?>>
	<?php if ( is_front_page() ) : ?>
		<header class="chidemoon-editorial-index__header">
			<p class="chidemoon-kicker"><?php esc_html_e( 'Magazine', 'chidemoon-theme' ); ?></p>
			<h1><?php echo esc_html( (string) get_bloginfo( 'name' ) ); ?></h1>
		</header>
	<?php endif; ?>
	<?php if ( 'search' === $mode ) : ?>
		<header class="chidemoon-editorial-index__header"><p class="chidemoon-kicker"><?php esc_html_e( 'Search results', 'chidemoon-theme' ); ?></p><h1><?php echo esc_html( sprintf( __( 'Results for “%s”', 'chidemoon-theme' ), get_search_query() ) ); ?></h1></header>
		<form class="chidemoon-editorial-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>"><label class="screen-reader-text" for="chidemoon-editorial-query"><?php esc_html_e( 'Search articles', 'chidemoon-theme' ); ?></label><input id="chidemoon-editorial-query" name="s" type="search" value="<?php echo esc_attr( get_search_query() ); ?>" maxlength="120" /><button type="submit"><?php esc_html_e( 'Search', 'chidemoon-theme' ); ?></button></form>
	<?php endif; ?>

	<?php if ( ! empty( $categories ) ) : ?>
	<nav class="chidemoon-editorial-filters" aria-label="<?php esc_attr_e( 'Editorial topics', 'chidemoon-theme' ); ?>">
		<a href="<?php echo esc_url( remove_query_arg( 'topic', $base_url ) ); ?>"<?php echo '' === $topic ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'All topics', 'chidemoon-theme' ); ?></a>
		<?php foreach ( $categories as $category ) : ?><a href="<?php echo esc_url( add_query_arg( 'topic', $category->slug, $base_url ) ); ?>"<?php echo $topic === $category->slug ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $category->name ); ?></a><?php endforeach; ?>
	</nav>
	<?php endif; ?>

	<?php if ( $editorial->have_posts() ) : ?>
	<div class="chidemoon-editorial-index__grid">
		<?php while ( $editorial->have_posts() ) : $editorial->the_post(); ?>
		<?php
		$thumbnail_id  = get_post_thumbnail_id();
		$thumbnail_alt = $thumbnail_id ? trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ) : '';
		$media_label   = '' !== $thumbnail_alt ? $thumbnail_alt : get_the_title();
		$reading_minutes = chidemoon_editorial_reading_minutes( get_the_ID() );
		$published_date = chidemoon_public_post_date( get_the_ID() );
		$reading_time   = sprintf( _n( '%d min read', '%d min read', $reading_minutes, 'chidemoon-theme' ), $reading_minutes );
		$media         = $thumbnail_id
			? wp_get_attachment_image(
				$thumbnail_id,
				'large',
				false,
				array(
					'loading'  => 'lazy',
					'decoding' => 'async',
					'alt'      => $thumbnail_alt,
					'onerror'  => 'this.hidden=true;var fallback=this.nextElementSibling;if(fallback){fallback.hidden=false;}',
				)
			)
			: '';
		?>
		<article <?php post_class( 'chidemoon-editorial-card chidemoon-editorial-index__card' ); ?>>
			<a class="chidemoon-editorial-index__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
				<?php
				// Core builds this image markup from the selected attachment. Keeping its
				// native srcset intact prevents a fallback from becoming the normal path.
				echo $media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
				<span class="chidemoon-editorial-index__media-fallback"<?php echo '' !== $media ? ' hidden' : ''; ?>><span class="chidemoon-editorial-index__media-brand" aria-hidden="true"><?php echo esc_html( chidemoon_public_brand_name() ); ?></span><span class="chidemoon-editorial-index__media-label"><?php echo esc_html( $media_label ); ?></span></span>
			</a>
			<div class="chidemoon-editorial-index__body">
				<div class="chidemoon-editorial-index__meta"><span><?php echo wp_kses_post( get_the_category_list( ' · ' ) ); ?></span><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( $published_date ); ?></time><span><?php echo esc_html( chidemoon_public_numerals( $reading_time ) ); ?></span></div>
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<p><?php echo esc_html( get_the_excerpt() ); ?></p>
				<a class="chidemoon-editorial-index__read" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read article', 'chidemoon-theme' ); ?><?php echo chidemoon_icon( 'arrow', 15 ); ?></a>
			</div>
		</article>
		<?php endwhile; ?>
	</div>
	<?php else : ?>
	<div class="chidemoon-editorial-empty" role="status">
		<?php if ( 'shop-look' === $mode ) : ?>
			<h2><?php esc_html_e( 'Clickable room stories are being prepared.', 'chidemoon-theme' ); ?></h2>
			<p><?php echo esc_html( $has_public_guides || $has_public_editorial ? __( 'We publish a room story only after its product references are complete and current. Until then, use a buying guide or browse the magazine for practical ideas.', 'chidemoon-theme' ) : __( 'We publish magazine articles after their sources and recommendations have been checked.', 'chidemoon-theme' ) ); ?></p>
			<div class="chidemoon-editorial-empty__actions"><?php if ( $has_public_guides ) : ?><a href="<?php echo esc_url( home_url( '/guides/' ) ); ?>"><?php esc_html_e( 'Read buying guides', 'chidemoon-theme' ); ?></a><?php endif; ?><?php if ( $has_public_editorial ) : ?><a href="<?php echo esc_url( home_url( '/magazine/' ) ); ?>"><?php esc_html_e( 'Explore the magazine', 'chidemoon-theme' ); ?></a><?php endif; ?><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'chidemoon-theme' ); ?></a></div>
		<?php elseif ( 'guides' === $mode ) : ?>
			<h2><?php esc_html_e( 'Buying guides are being prepared.', 'chidemoon-theme' ); ?></h2>
			<p><?php echo esc_html( $has_public_editorial ? __( 'We publish buying advice only after its sources and recommendations are current. Explore the magazine while guides are prepared.', 'chidemoon-theme' ) : __( 'We publish magazine articles after their sources and recommendations have been checked.', 'chidemoon-theme' ) ); ?></p>
			<div class="chidemoon-editorial-empty__actions"><?php if ( $has_public_editorial ) : ?><a href="<?php echo esc_url( home_url( '/magazine/' ) ); ?>"><?php esc_html_e( 'Explore the magazine', 'chidemoon-theme' ); ?></a><?php endif; ?><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'chidemoon-theme' ); ?></a></div>
		<?php elseif ( 'search' === $mode ) : ?>
			<h2><?php esc_html_e( 'No articles match this search.', 'chidemoon-theme' ); ?></h2>
			<p><?php echo esc_html( $has_public_editorial ? __( 'Try a broader search or explore the magazine for related ideas.', 'chidemoon-theme' ) : __( 'We publish magazine articles after their sources and recommendations have been checked.', 'chidemoon-theme' ) ); ?></p>
			<div class="chidemoon-editorial-empty__actions"><?php if ( $has_public_editorial ) : ?><a href="<?php echo esc_url( home_url( '/magazine/' ) ); ?>"><?php esc_html_e( 'Explore the magazine', 'chidemoon-theme' ); ?></a><?php endif; ?><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'chidemoon-theme' ); ?></a></div>
		<?php else : ?>
			<h2><?php esc_html_e( 'No magazine articles are available yet.', 'chidemoon-theme' ); ?></h2>
			<p><?php esc_html_e( 'We publish magazine articles after their sources and recommendations have been checked.', 'chidemoon-theme' ); ?></p>
			<div class="chidemoon-editorial-empty__actions"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return home', 'chidemoon-theme' ); ?></a></div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( $editorial->max_num_pages > 1 ) : ?>
	<nav class="chidemoon-editorial-pagination" aria-label="<?php esc_attr_e( 'Editorial pages', 'chidemoon-theme' ); ?>"><?php echo wp_kses_post( paginate_links( array( 'total' => $editorial->max_num_pages, 'current' => $paged, 'mid_size' => 1, 'prev_text' => __( 'Previous', 'chidemoon-theme' ), 'next_text' => __( 'Next', 'chidemoon-theme' ), 'add_args' => '' !== $topic ? array( 'topic' => $topic ) : false ) ) ); ?></nav>
	<?php endif; ?>
</section>
<?php wp_reset_postdata(); ?>
