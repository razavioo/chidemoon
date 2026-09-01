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
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 5 );
		add_action( 'init', array( __CLASS__, 'migrate_existing_look_terms' ), 20 );
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
