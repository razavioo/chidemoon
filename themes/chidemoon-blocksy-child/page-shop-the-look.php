<?php
/** Landing template for editorial room combinations. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$core_ready = class_exists( 'Chidemoon_Core_Shop_The_Look' );
$taxonomy   = $core_ready ? Chidemoon_Core_Shop_The_Look::TAXONOMY : '';
$room_slug  = isset( $_GET['room'] ) ? sanitize_title( wp_unslash( (string) $_GET['room'] ) ) : '';
$rooms      = $core_ready ? get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) ) : array();
if ( is_wp_error( $rooms ) ) {
	$rooms = array();
}
$current_page = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$query_args   = array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'posts_per_page'      => 12,
	'paged'               => $current_page,
	'tag'                 => 'shop-the-look',
	'post__not_in'        => array( get_queried_object_id() ),
	'ignore_sticky_posts' => true,
);
if ( $core_ready && '' !== $room_slug ) {
	$query_args['tax_query'] = array( array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $room_slug ) );
}
$look_posts   = new WP_Query( $query_args );
$current_room = null;
foreach ( $rooms as $room ) {
	if ( $room->slug === $room_slug ) {
		$current_room = $room;
		break;
	}
}
?>
<main id="primary" class="site-main chidemoon-collection-page chidemoon-look-page">
	<?php while ( have_posts() ) : the_post(); ?>
		<header class="chidemoon-collection-page__hero chidemoon-section-shell<?php echo has_post_thumbnail() ? ' has-media' : ''; ?>">
			<div class="chidemoon-collection-page__hero-copy">
				<p class="chidemoon-eyebrow"><?php esc_html_e( 'یک اتاق، با دقت', 'chidemoon-blocksy-child' ); ?></p>
				<h1><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?><p><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
			</div>
			<?php if ( has_post_thumbnail() ) : ?><figure class="chidemoon-collection-page__hero-media"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager' ) ); ?></figure><?php endif; ?>
		</header>
		<?php if ( '' !== trim( wp_strip_all_tags( get_the_content() ) ) ) : ?><section class="chidemoon-collection-page__content chidemoon-collection-page__content--single chidemoon-section-shell"><div class="entry-content"><?php the_content(); ?></div></section><?php endif; ?>
		<?php if ( $core_ready && ! empty( $rooms ) ) : ?>
			<nav class="chidemoon-look-rooms chidemoon-section-shell" aria-label="<?php esc_attr_e( 'انتخاب فضای خانه', 'chidemoon-blocksy-child' ); ?>">
				<a class="chidemoon-look-room<?php echo '' === $room_slug ? ' is-active' : ''; ?>" href="<?php echo esc_url( get_permalink() ); ?>"<?php echo '' === $room_slug ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'همه فضاها', 'chidemoon-blocksy-child' ); ?></a>
				<?php foreach ( $rooms as $room ) : ?>
					<a class="chidemoon-look-room<?php echo $room->slug === $room_slug ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'room', $room->slug, get_permalink() ) ); ?>"<?php echo $room->slug === $room_slug ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $room->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	<?php endwhile; ?>
	<section class="chidemoon-section-shell chidemoon-collection-page__feed" aria-labelledby="chidemoon-looks-feed">
		<div class="chidemoon-section-heading"><div><p class="chidemoon-eyebrow"><?php echo $current_room instanceof WP_Term ? esc_html( $current_room->name ) : esc_html__( 'نکته‌های اتاق', 'chidemoon-blocksy-child' ); ?></p><h2 id="chidemoon-looks-feed"><?php esc_html_e( 'چیدمان‌های قابل خرید', 'chidemoon-blocksy-child' ); ?></h2></div></div>
		<?php if ( $look_posts->have_posts() ) : ?>
			<div class="chidemoon-look-list">
				<?php while ( $look_posts->have_posts() ) : $look_posts->the_post(); ?>
					<article class="chidemoon-look-card">
						<header class="chidemoon-look-card__header"><h3><?php echo esc_html( get_the_title() ); ?></h3><?php if ( has_excerpt() ) : ?><p><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?></header>
						<div class="chidemoon-look-card__content entry-content"><?php the_content(); ?></div>
					</article>
				<?php endwhile; ?>
			</div>
			<?php if ( $look_posts->max_num_pages > 1 ) : ?>
				<nav class="chidemoon-pagination" aria-label="<?php esc_attr_e( 'صفحه‌بندی چیدمان‌ها', 'chidemoon-blocksy-child' ); ?>">
					<?php echo wp_kses_post( paginate_links( array( 'current' => $current_page, 'total' => $look_posts->max_num_pages, 'type' => 'list', 'mid_size' => 1, 'add_args' => '' !== $room_slug ? array( 'room' => $room_slug ) : false ) ) ); ?>
				</nav>
			<?php endif; ?>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( 'در این فضا چیدمانی منتشر نشده است.', 'فضای دیگری را انتخاب کنید یا همهٔ فضاها را ببینید.' ); ?>
		<?php endif; ?>
	</section>
</main>
<?php wp_reset_postdata(); get_footer();
