<?php
/**
 * Archive presentation for public editorial taxonomies and dates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main chidemoon-archive<?php echo is_search() ? ' chidemoon-archive--search' : ''; ?>">
	<?php
	$archive_count = chidemoon_archive_record_count();

	// ?s= with no phrase makes WordPress list every post as "results";
	// present it as a prompt to search instead of a fake result set.
	$is_blank_search = is_search() && '' === trim( (string) get_query_var( 's' ) );
	?>

	<?php if ( is_search() ) : ?>
		<header class="chidemoon-archive__hero chidemoon-archive__hero--search chidemoon-section-shell">
			<?php if ( ! $is_blank_search ) : ?>
				<div class="chidemoon-archive__hero-meta">
					<p class="chidemoon-eyebrow"><?php esc_html_e( 'نتایج جستجو', 'chidemoon-blocksy-child' ); ?></p>
					<?php if ( $archive_count > 0 ) : ?>
						<span class="chidemoon-archive__hero-count"><strong><?php echo esc_html( chidemoon_fa_digits( $archive_count ) ); ?></strong><?php esc_html_e( 'نتیجه', 'chidemoon-blocksy-child' ); ?></span>
					<?php endif; ?>
				</div>
				<h1><?php printf( esc_html__( 'نتایج برای «%s»', 'chidemoon-blocksy-child' ), esc_html( get_search_query() ) ); ?></h1>
			<?php else : ?>
				<h1><?php esc_html_e( 'جستجو در چیدمون', 'chidemoon-blocksy-child' ); ?></h1>
			<?php endif; ?>
			<div class="chidemoon-archive__hero-search">
				<?php get_search_form(); ?>
			</div>
		</header>
	<?php else : ?>
		<header class="chidemoon-archive__hero chidemoon-section-shell">
			<p class="chidemoon-eyebrow"><?php esc_html_e( 'آرشیو مجله', 'chidemoon-blocksy-child' ); ?></p>
			<h1><?php the_archive_title(); ?></h1>
			<?php if ( get_the_archive_description() ) : ?>
				<div class="chidemoon-archive__description"><?php the_archive_description(); ?></div>
			<?php else : ?>
				<p><?php esc_html_e( 'مطالب بررسی‌شده و ایده‌های کاربردی از مجله چیدمون.', 'chidemoon-blocksy-child' ); ?></p>
			<?php endif; ?>
			<?php if ( $archive_count > 0 ) : ?>
				<p class="chidemoon-hero-facts">
					<span class="chidemoon-hero-facts__item"><strong><?php echo esc_html( chidemoon_fa_digits( $archive_count ) ); ?></strong><?php esc_html_e( 'مطلب منتشرشده', 'chidemoon-blocksy-child' ); ?></span>
				</p>
			<?php endif; ?>
		</header>
	<?php endif; ?>

	<section class="chidemoon-section-shell">
		<?php if ( $is_blank_search ) : ?>
			<div class="chidemoon-empty-state">
				<span class="chidemoon-empty-state__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg></span>
				<div>
					<h3><?php esc_html_e( 'هنوز چیزی جستجو نشده است.', 'chidemoon-blocksy-child' ); ?></h3>
					<p><?php esc_html_e( 'برای دیدن نتایج، عبارتی را در کادر بالا بنویسید؛ یا مستقیم از این‌جا ادامه دهید.', 'chidemoon-blocksy-child' ); ?></p>
					<?php chidemoon_search_quick_links(); ?>
				</div>
			</div>
		<?php elseif ( have_posts() ) : ?>
			<div class="chidemoon-card-grid chidemoon-card-grid--archive">
				<?php while ( have_posts() ) : ?>
					<?php the_post(); ?>
					<?php chidemoon_blocksy_render_post_card( get_the_ID(), 'compact', 2 ); ?>
				<?php endwhile; ?>
			</div>
			<?php chidemoon_the_posts_pagination( array( 'class' => 'chidemoon-pagination' ) ); ?>
		<?php elseif ( is_search() ) : ?>
			<div class="chidemoon-empty-state">
				<span class="chidemoon-empty-state__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg></span>
				<div>
					<h3><?php printf( esc_html__( 'چیزی برای «%s» پیدا نشد.', 'chidemoon-blocksy-child' ), esc_html( get_search_query() ) ); ?></h3>
					<p><?php esc_html_e( 'املای عبارت را بررسی کنید یا کلمه‌ی کوتاه‌تری امتحان کنید؛ یا مستقیم از این‌جا ادامه دهید.', 'chidemoon-blocksy-child' ); ?></p>
					<?php chidemoon_search_quick_links(); ?>
				</div>
			</div>
		<?php else : ?>
			<?php chidemoon_blocksy_render_empty_state( 'در این آرشیو مطلب منتشرشده‌ای نیست.', 'این بخش تا زمانی که مطلب بررسی‌شده‌ای اینجا منتشر شود ساکت می‌ماند.' ); ?>
		<?php endif; ?>
	</section>
</main>

<?php get_footer(); ?>
