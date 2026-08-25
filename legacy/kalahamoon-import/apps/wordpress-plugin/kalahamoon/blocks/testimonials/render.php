<?php
/**
 * Kalahamoon Testimonials — server-side render.
 *
 * @var array $attributes
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$items       = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : array();
$layout      = in_array( ( $attributes['layout'] ?? 'grid' ), array( 'grid', 'slider' ), true ) ? $attributes['layout'] : 'grid';
$columns     = max( 1, min( 4, (int) ( $attributes['columns'] ?? 2 ) ) );
$show_rating = ! isset( $attributes['showRating'] ) || ! empty( $attributes['showRating'] );

if ( empty( $items ) ) return;

$wrapper = get_block_wrapper_attributes( array(
	'class' => 'kalahamoon-testimonials kalahamoon-testimonials-' . $layout . ( 'grid' === $layout ? ' kalahamoon-testimonials-cols-' . $columns : '' ),
) );
?>
<div <?php echo $wrapper; ?>>
	<div class="kalahamoon-testimonials-track">
		<?php foreach ( $items as $i => $item ) :
			$quote  = trim( (string) ( $item['quote']  ?? '' ) );
			$author = trim( (string) ( $item['author'] ?? '' ) );
			$role   = trim( (string) ( $item['role']   ?? '' ) );
			$avatar = trim( (string) ( $item['avatar'] ?? '' ) );
			$rating = max( 0, min( 5, (int) ( $item['rating'] ?? 5 ) ) );
			if ( '' === $quote ) continue;
		?>
			<figure class="kalahamoon-testimonial">
				<?php if ( $show_rating ) : ?>
					<div class="kalahamoon-testimonial-rating" aria-label="<?php echo esc_attr( sprintf( __( 'امتیاز: %d از ۵', 'kalahamoon' ), $rating ) ); ?>">
						<?php for ( $s = 1; $s <= 5; $s++ ) : ?>
							<span class="kalahamoon-testimonial-star <?php echo $s <= $rating ? 'is-filled' : ''; ?>" aria-hidden="true">★</span>
						<?php endfor; ?>
					</div>
				<?php endif; ?>
				<blockquote class="kalahamoon-testimonial-quote">
					<p>&ldquo;<?php echo esc_html( $quote ); ?>&rdquo;</p>
				</blockquote>
				<figcaption class="kalahamoon-testimonial-author">
					<?php if ( '' !== $avatar ) : ?>
						<img src="<?php echo esc_url( $avatar ); ?>" alt="" loading="lazy" class="kalahamoon-testimonial-avatar" />
					<?php endif; ?>
					<span class="kalahamoon-testimonial-author-meta">
						<?php if ( '' !== $author ) : ?>
							<strong class="kalahamoon-testimonial-author-name"><?php echo esc_html( $author ); ?></strong>
						<?php endif; ?>
						<?php if ( '' !== $role ) : ?>
							<span class="kalahamoon-testimonial-author-role"><?php echo esc_html( $role ); ?></span>
						<?php endif; ?>
					</span>
				</figcaption>
			</figure>
		<?php endforeach; ?>
	</div>
</div>
