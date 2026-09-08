<?php
/**
 * Portable dynamic components for Elementor-owned editorial landing pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Landing_Components {
	private const DEFAULT_STORY_LIMIT = 6;
	private const DEFAULT_LOOK_LIMIT = 12;

	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_shortcodes(): void {
		add_shortcode( 'chidemoon_guides_feed', array( __CLASS__, 'render_guides_feed' ) );
		add_shortcode( 'chidemoon_comparisons_feed', array( __CLASS__, 'render_comparisons_feed' ) );
		add_shortcode( 'chidemoon_shop_the_look_feed', array( __CLASS__, 'render_shop_the_look_feed' ) );
	}

	public static function register_assets(): void {
		$path = CHIDEMOON_CORE_DIR . 'assets/css/landing-components.css';
		wp_register_style(
			'chidemoon-core-landing-components',
			CHIDEMOON_CORE_URL . 'assets/css/landing-components.css',
			array(),
			file_exists( $path ) ? (string) filemtime( $path ) : CHIDEMOON_CORE_VERSION
		);

		if ( is_page( array( 'guides', 'comparisons', 'shop-the-look' ) ) ) {
			self::enqueue_assets();
		}
	}

	public static function enqueue_assets(): void {
		if ( wp_style_is( 'chidemoon-core-landing-components', 'registered' ) ) {
			wp_enqueue_style( 'chidemoon-core-landing-components' );
		}
	}

	/** @param array<string, string> $atts */
	public static function render_guides_feed( $atts = array() ): string {
		$atts  = shortcode_atts( array( 'limit' => (string) self::DEFAULT_STORY_LIMIT ), $atts, 'chidemoon_guides_feed' );
		$limit = self::bounded_limit( $atts['limit'], self::DEFAULT_STORY_LIMIT, 12 );
		self::enqueue_assets();

		return self::render_story_feed(
			'guides',
			$limit,
			__( 'راهنماهای خرید', 'chidemoon-core' ),
			__( 'هنوز راهنمایی منتشر نشده است.', 'chidemoon-core' ),
			__( 'به‌زودی راهنماهای بررسی‌شده اینجا منتشر می‌شوند.', 'chidemoon-core' )
		);
	}

	/** @param array<string, string> $atts */
	public static function render_comparisons_feed( $atts = array() ): string {
		$atts  = shortcode_atts( array( 'limit' => (string) self::DEFAULT_STORY_LIMIT ), $atts, 'chidemoon_comparisons_feed' );
		$limit = self::bounded_limit( $atts['limit'], self::DEFAULT_STORY_LIMIT, 12 );
		self::enqueue_assets();

		return self::render_story_feed(
			'comparisons',
			$limit,
			__( 'راهنماهای مقایسه', 'chidemoon-core' ),
			__( 'هنوز مقایسه‌ای منتشر نشده است.', 'chidemoon-core' ),
			__( 'مقایسه‌های بررسی‌شده به‌زودی اینجا نمایش داده می‌شوند.', 'chidemoon-core' )
		);
	}

	/** @param array<string, string> $atts */
	public static function render_shop_the_look_feed( $atts = array() ): string {
		$atts     = shortcode_atts( array( 'per_page' => (string) self::DEFAULT_LOOK_LIMIT ), $atts, 'chidemoon_shop_the_look_feed' );
		$per_page = self::bounded_limit( $atts['per_page'], self::DEFAULT_LOOK_LIMIT, 24 );
		self::enqueue_assets();
		Chidemoon_Core_Shop_The_Look::enqueue_assets();

		$taxonomy = Chidemoon_Core_Shop_The_Look::TAXONOMY;
		$rooms    = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		$rooms = is_wp_error( $rooms ) ? array() : $rooms;
		$room  = isset( $_GET['room'] ) ? sanitize_title( wp_unslash( (string) $_GET['room'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_room = null;
		foreach ( $rooms as $term ) {
			if ( $term instanceof WP_Term && $term->slug === $room ) {
				$current_room = $term;
				break;
			}
		}
		if ( ! $current_room ) {
			$room = '';
		}

		$page     = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$query    = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'tag'                 => 'shop-the-look',
			'ignore_sticky_posts' => true,
		);
		if ( '' !== $room ) {
			$query['tax_query'] = array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $room,
				),
			);
		}
		$looks    = new WP_Query( $query );
		$page_url = get_permalink( get_queried_object_id() );
		if ( ! is_string( $page_url ) || '' === $page_url ) {
			$page_url = home_url( '/shop-the-look/' );
		}

		ob_start();
		?>
		<div class="chidemoon-core-looks" data-chidemoon-look-feed>
			<?php if ( ! empty( $rooms ) ) : ?>
				<nav class="chidemoon-core-looks__rooms" aria-label="<?php esc_attr_e( 'انتخاب فضای خانه', 'chidemoon-core' ); ?>">
					<a class="chidemoon-core-looks__room<?php echo '' === $room ? ' is-active' : ''; ?>" href="<?php echo esc_url( $page_url ); ?>"<?php echo '' === $room ? ' aria-current="page"' : ''; ?>><?php esc_html_e( 'همه فضاها', 'chidemoon-core' ); ?></a>
					<?php foreach ( $rooms as $term ) : ?>
						<?php if ( ! $term instanceof WP_Term ) { continue; } ?>
						<a class="chidemoon-core-looks__room<?php echo $term->slug === $room ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'room', $term->slug, $page_url ) ); ?>"<?php echo $term->slug === $room ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $term->name ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>

			<?php if ( $looks->have_posts() ) : ?>
				<div class="chidemoon-core-looks__list">
					<?php while ( $looks->have_posts() ) : $looks->the_post(); ?>
						<article class="chidemoon-core-look-card">
							<header class="chidemoon-core-look-card__header">
								<h2><a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a></h2>
								<?php if ( has_excerpt() ) : ?><p><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
							</header>
							<div class="chidemoon-core-look-card__content entry-content"><?php echo apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						</article>
					<?php endwhile; ?>
				</div>
				<?php if ( $looks->max_num_pages > 1 ) : ?>
					<nav class="chidemoon-core-pagination" aria-label="<?php esc_attr_e( 'صفحه‌بندی چیدمان‌ها', 'chidemoon-core' ); ?>">
						<?php
						$links = paginate_links(
							array(
								'current'  => $page,
								'total'    => $looks->max_num_pages,
								'type'     => 'list',
								'mid_size' => 1,
								'prev_text'=> __( 'قبلی', 'chidemoon-core' ),
								'next_text'=> __( 'بعدی', 'chidemoon-core' ),
								'add_args' => '' !== $room ? array( 'room' => $room ) : false,
							)
						);
						echo self::persian_digits_in_markup( (string) $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</nav>
				<?php endif; ?>
			<?php else : ?>
				<?php echo self::empty_state( __( 'در این فضا چیدمانی منتشر نشده است.', 'chidemoon-core' ), __( 'فضای دیگری را انتخاب کنید یا همهٔ فضاها را ببینید.', 'chidemoon-core' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();
		return (string) ob_get_clean();
	}

	private static function bounded_limit( string $value, int $fallback, int $maximum ): int {
		$limit = absint( $value );
		return $limit > 0 ? min( $limit, $maximum ) : $fallback;
	}

	private static function render_story_feed( string $category, int $limit, string $label, string $empty_title, string $empty_copy ): string {
		$stories = new WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => $limit,
				'category_name'       => $category,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		ob_start();
		?>
		<section class="chidemoon-core-story-feed" aria-label="<?php echo esc_attr( $label ); ?>">
			<?php if ( $stories->have_posts() ) : ?>
				<div class="chidemoon-core-story-feed__grid">
					<?php while ( $stories->have_posts() ) : $stories->the_post(); ?>
						<?php echo self::story_card( get_post() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endwhile; ?>
				</div>
			<?php else : ?>
				<?php echo self::empty_state( $empty_title, $empty_copy ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</section>
		<?php
		wp_reset_postdata();
		return (string) ob_get_clean();
	}

	private static function story_card( $post ): string {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return '';
		}
		$title     = get_the_title( $post );
		$permalink = get_permalink( $post );
		$excerpt   = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 22 );
		$categories = get_the_category( $post->ID );
		$category   = ! empty( $categories ) && $categories[0] instanceof WP_Term ? $categories[0] : null;

		ob_start();
		?>
		<article class="chidemoon-core-story-card">
			<a class="chidemoon-core-story-card__media" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
				<?php if ( has_post_thumbnail( $post ) ) : ?>
					<?php echo get_the_post_thumbnail( $post, 'large', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<span class="chidemoon-core-story-card__media-empty" aria-hidden="true"></span>
				<?php endif; ?>
				<?php if ( $category instanceof WP_Term ) : ?><span class="chidemoon-core-story-card__category"><?php echo esc_html( $category->name ); ?></span><?php endif; ?>
			</a>
			<div class="chidemoon-core-story-card__body">
				<h3><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></h3>
				<?php if ( '' !== $excerpt ) : ?><p><?php echo esc_html( wp_trim_words( $excerpt, 18 ) ); ?></p><?php endif; ?>
				<div class="chidemoon-core-story-card__footer"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $post ) ); ?>"><?php echo esc_html( get_the_date( '', $post ) ); ?></time><a href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'ادامه مطلب', 'chidemoon-core' ); ?></a></div>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	private static function empty_state( string $title, string $description ): string {
		return sprintf(
			'<div class="chidemoon-core-empty-state"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M12 4v16M3 9.5h18"/></svg><div><h3>%1$s</h3><p>%2$s</p></div></div>',
			esc_html( $title ),
			esc_html( $description )
		);
	}

	private static function persian_digits_in_markup( string $markup ): string {
		$digits = array_combine( range( '0', '9' ), preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY ) );
		return (string) preg_replace_callback(
			'/>([^<>]+)</',
			static fn( array $matches ): string => '>' . strtr( $matches[1], $digits ) . '<',
			$markup
		);
	}
}
