<?php
/**
 * Shop the Look room taxonomy and editorial content registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Shop_The_Look {
	public const TAXONOMY = 'chidemoon_room';
	private const MIGRATION_OPTION = 'chidemoon_core_room_terms_migrated';

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 5 );
		add_action( 'init', array( __CLASS__, 'migrate_existing_look_terms' ), 20 );
		add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_elementor_support' ) );
	}

	public static function register_assets(): void {
		$style_path  = CHIDEMOON_CORE_DIR . 'blocks/shop-the-look/style.css';
		$script_path = CHIDEMOON_CORE_DIR . 'blocks/shop-the-look/view.js';
		wp_register_style( 'chidemoon-shop-the-look', CHIDEMOON_CORE_URL . 'blocks/shop-the-look/style.css', array(), file_exists( $style_path ) ? (string) filemtime( $style_path ) : CHIDEMOON_CORE_VERSION );
		wp_register_script( 'chidemoon-shop-the-look-view', CHIDEMOON_CORE_URL . 'blocks/shop-the-look/view.js', array(), file_exists( $script_path ) ? (string) filemtime( $script_path ) : CHIDEMOON_CORE_VERSION, true );
		if ( is_page( 'shop-the-look' ) || has_block( 'chidemoon/shop-the-look' ) ) {
			self::enqueue_assets();
		}
	}

	public static function enqueue_assets(): void {
		if ( wp_style_is( 'chidemoon-shop-the-look', 'registered' ) ) {
			wp_enqueue_style( 'chidemoon-shop-the-look' );
		}
		if ( wp_script_is( 'chidemoon-shop-the-look-view', 'registered' ) ) {
			wp_enqueue_script( 'chidemoon-shop-the-look-view' );
		}
	}

	public static function register_shortcode(): void {
		add_shortcode( 'chidemoon_shop_the_look', array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( 'chidemoon_shop_look', array( __CLASS__, 'render_shortcode' ) );
	}

	public static function register_elementor_support(): void {
		// Shortcode is the fallback for any page builder; full Elementor widget loads when Elementor is active.
		if ( did_action( 'elementor/loaded' ) ) {
			add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor_widget' ) );
		} else {
			add_action( 'plugins_loaded', function (): void {
				if ( did_action( 'elementor/loaded' ) ) {
					add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor_widget' ) );
				}
			} );
		}
	}

	/**
	 * @param \Elementor\Widgets_Manager $manager
	 */
	public static function register_elementor_widget( $manager ): void {
		$path = CHIDEMOON_CORE_DIR . 'includes/class-chidemoon-core-elementor-shop-look-widget.php';
		if ( file_exists( $path ) ) {
			require_once $path;
			if ( class_exists( 'Chidemoon_Core_Elementor_Shop_Look_Widget' ) ) {
				$manager->register( new Chidemoon_Core_Elementor_Shop_Look_Widget() );
			}
		}
	}

	/**
	 * Shortcode: [chidemoon_shop_the_look image_id="123" caption="..." hotspots='[{"x":20,"y":30,"productId":12,"label":"مبل"}]']
	 * Also accepts imageId / image_id and hotspots as JSON string or base64.
	 *
	 * @param array<string, string> $atts
	 */
	public static function render_shortcode( $atts = array(), $content = null ): string {
		$atts = shortcode_atts( array(
			'image_id'  => '0',
			'imageId'   => '0',
			'image_alt' => '',
			'imageAlt'  => '',
			'caption'   => '',
			'hotspots'  => '[]',
		), $atts, 'chidemoon_shop_the_look' );

		$image_id = absint( $atts['image_id'] ) ?: absint( $atts['imageId'] );
		$caption  = sanitize_text_field( (string) $atts['caption'] );
		$alt      = sanitize_text_field( (string) ( $atts['image_alt'] ?: $atts['imageAlt'] ) );
		$raw      = $atts['hotspots'];
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				// Try base64 + json
				$maybe = json_decode( base64_decode( $raw ), true );
				$decoded = is_array( $maybe ) ? $maybe : array();
			}
			$hotspots = $decoded;
		} elseif ( is_array( $raw ) ) {
			$hotspots = $raw;
		} else {
			$hotspots = array();
		}

		return self::render_look( array(
			'imageId'  => $image_id,
			'imageAlt' => $alt,
			'caption'  => $caption,
			'hotspots' => $hotspots,
		) );
	}

	/**
	 * Shared server render for Gutenberg block, shortcode and Elementor widget.
	 * Returned string is already sanitized for direct echo.
	 *
	 * @param array<string, mixed> $attributes
	 */
	public static function render_look( array $attributes, string $wrapper_extra_class = '' ): string {
		self::enqueue_assets();
		$image_id = absint( $attributes['imageId'] ?? $attributes['image_id'] ?? 0 );
		if ( $image_id <= 0 ) {
			return '';
		}
		$image_alt = sanitize_text_field( (string) ( $attributes['imageAlt'] ?? $attributes['image_alt'] ?? get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) );
		$caption   = sanitize_text_field( (string) ( $attributes['caption'] ?? '' ) );
		$raw       = $attributes['hotspots'] ?? array();
		$hotspots  = is_array( $raw ) ? array_values( array_filter( $raw, 'is_array' ) ) : array();
		$instance  = wp_unique_id( 'chidemoon-look-' );
		$products  = array();

		foreach ( $hotspots as $index => $spot ) {
			$product_id = absint( $spot['productId'] ?? $spot['product_id'] ?? 0 );
			$x          = max( 2, min( 98, (float) ( $spot['x'] ?? 50 ) ) );
			$y          = max( 2, min( 98, (float) ( $spot['y'] ?? 50 ) ) );
			$hotspots[ $index ]['x'] = $x;
			$hotspots[ $index ]['y'] = $y;
			if ( $product_id <= 0 || isset( $products[ $product_id ] ) ) {
				continue;
			}
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
			if ( ! $product instanceof WC_Product || ! Chidemoon_Core_Affiliate::is_publicly_eligible( $product ) ) {
				continue;
			}
			$products[ $product_id ] = array(
				'id'       => $product_id,
				'title'    => $product->get_name(),
				'price'    => $product->get_price_html(),
				'image_id' => $product->get_image_id(),
				'url'      => Chidemoon_Core_Affiliate::tracking_url( $product_id ),
				'product'  => $product,
			);
		}

		// Wrap attributes for Elementor / shortcode contexts that don't provide block wrapper.
		$extra = $wrapper_extra_class ? ' ' . sanitize_html_class( $wrapper_extra_class ) : '';
		$wrapper = function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes( array( 'class' => 'chidemoon-shop-the-look' . $extra, 'data-look-instance' => $instance ) )
			: 'class="chidemoon-shop-the-look' . esc_attr( $extra ) . '" data-look-instance="' . esc_attr( $instance ) . '"';

		ob_start();
		?>
		<figure <?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="chidemoon-shop-the-look__canvas">
				<?php echo wp_get_attachment_image( $image_id, 'full', false, array( 'class' => 'chidemoon-shop-the-look__image', 'alt' => $image_alt, 'loading' => 'lazy', 'decoding' => 'async' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php foreach ( $hotspots as $index => $spot ) :
					$product_id = absint( $spot['productId'] ?? $spot['product_id'] ?? 0 );
					$product    = $products[ $product_id ] ?? null;
					if ( ! $product ) {
						continue;
					}
					$tooltip_id = $instance . '-product-' . $index;
					$label      = sanitize_text_field( (string) ( $spot['label'] ?? $product['title'] ) );
				?>
					<button type="button" class="chidemoon-shop-the-look__hotspot" style="left:<?php echo esc_attr( $spot['x'] ); ?>%;top:<?php echo esc_attr( $spot['y'] ); ?>%" aria-expanded="false" aria-controls="<?php echo esc_attr( $tooltip_id ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" data-tooltip="<?php echo esc_attr( $tooltip_id ); ?>">
						<span aria-hidden="true"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
					</button>
					<div id="<?php echo esc_attr( $tooltip_id ); ?>" class="chidemoon-shop-the-look__tooltip" hidden>
						<button type="button" class="chidemoon-shop-the-look__close" aria-label="<?php esc_attr_e( 'بستن', 'chidemoon-core' ); ?>">×</button>
						<?php if ( $product['image_id'] > 0 ) : ?>
							<?php echo wp_get_attachment_image( $product['image_id'], 'woocommerce_thumbnail', false, array( 'class' => 'chidemoon-shop-the-look__product-image', 'alt' => $product['title'], 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
						<div class="chidemoon-shop-the-look__tooltip-body">
							<h3><?php echo esc_html( $product['title'] ); ?></h3>
							<div class="chidemoon-shop-the-look__price"><?php echo wp_kses_post( $product['price'] ); ?></div>
							<a class="chidemoon-button" href="<?php echo esc_url( $product['url'] ); ?>" target="_blank" rel="nofollow sponsored noopener" data-product-id="<?php echo esc_attr( $product_id ); ?>"><?php esc_html_e( 'خرید از فروشگاه', 'chidemoon-core' ); ?></a>
							<?php echo Chidemoon_Core_Compare::control( $product['product'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $caption ) : ?><figcaption><?php echo esc_html( $caption ); ?></figcaption><?php endif; ?>
			<?php if ( ! empty( $products ) ) : ?>
				<ol class="chidemoon-shop-the-look__fallback" aria-label="<?php esc_attr_e( 'محصولات این تصویر', 'chidemoon-core' ); ?>">
				<?php foreach ( $products as $product ) : ?><li><span><?php echo esc_html( $product['title'] ); ?></span><span><?php echo wp_kses_post( $product['price'] ); ?></span><a href="<?php echo esc_url( $product['url'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php esc_html_e( 'خرید از فروشگاه', 'chidemoon-core' ); ?></a></li><?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</figure>
		<?php
		return (string) ob_get_clean();
	}

	public static function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( 'post' ),
			array(
				'labels' => array(
					'name'          => __( 'فضاها', 'chidemoon-core' ),
					'singular_name' => __( 'فضا', 'chidemoon-core' ),
					'add_new_item'  => __( 'افزودن فضای جدید', 'chidemoon-core' ),
					'edit_item'     => __( 'ویرایش فضا', 'chidemoon-core' ),
				),
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'rewrite'           => array( 'slug' => 'rooms' ),
			)
		);
	}

	public static function migrate_existing_look_terms(): void {
		if ( get_option( self::MIGRATION_OPTION, false ) ) {
			return;
		}

		$terms = array(
			'living-room'   => 'پذیرایی و نشیمن',
			'bedroom'       => 'اتاق خواب',
			'kitchen'       => 'آشپزخانه',
			'kids-room'     => 'اتاق کودک',
			'terrace'       => 'تراس و بالکن',
			'dining-room'   => 'ناهارخوری',
			'home-office'   => 'اتاق کار',
			'entryway'      => 'ورودی خانه',
			'reading-corner'=> 'گوشه مطالعه',
		);
		$term_ids = array();
		$complete = true;
		foreach ( $terms as $slug => $name ) {
			$term = term_exists( $slug, self::TAXONOMY );
			if ( ! $term ) {
				$term = wp_insert_term( $name, self::TAXONOMY, array( 'slug' => $slug ) );
			}
			if ( is_wp_error( $term ) || ! $term ) {
				$complete = false;
				continue;
			}
			$term_ids[ $slug ] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
		}

		$posts = get_posts(
			array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tag'            => 'shop-the-look',
			'tax_query'      => array(
				array(
					'taxonomy' => self::TAXONOMY,
					'operator' => 'NOT EXISTS',
				),
			),
			'no_found_rows' => true,
			)
		);
		foreach ( $posts as $post_id ) {
			$tags = wp_get_post_tags( (int) $post_id, array( 'fields' => 'slugs' ) );
			$room = self::room_for_tags( $tags );
			if ( $room && isset( $term_ids[ $room ] ) ) {
				$assigned = wp_set_post_terms( (int) $post_id, array( $term_ids[ $room ] ), self::TAXONOMY, false );
				if ( is_wp_error( $assigned ) ) {
					$complete = false;
				}
			} elseif ( $room ) {
				$complete = false;
			}
		}

		if ( $complete ) {
			update_option( self::MIGRATION_OPTION, 1, false );
		}
	}

	/**
	 * @param string[] $tags
	 */
	public static function room_for_tags( array $tags ): string {
		$map = array(
			'اتاق خواب'      => 'bedroom',
			'خواب'           => 'bedroom',
			'نشیمن'          => 'living-room',
			'پذیرایی'        => 'living-room',
			'بالکن'          => 'terrace',
			'تراس'           => 'terrace',
			'ناهارخوری'      => 'dining-room',
			'کار در خانه'    => 'home-office',
			'گوشه مطالعه'    => 'reading-corner',
			'ورودی'          => 'entryway',
			'اتاق کودک'      => 'kids-room',
			'آشپزخانه'       => 'kitchen',
		);
		foreach ( $tags as $tag ) {
			$term = get_term_by( 'slug', sanitize_title( (string) $tag ), 'post_tag' );
			$name = $term instanceof WP_Term ? $term->name : (string) $tag;
			if ( isset( $map[ $name ] ) ) {
				return $map[ $name ];
			}
		}

		return '';
	}
}
