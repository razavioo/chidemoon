<?php
/**
 * Favorites widget — classic WP_Widget.
 *
 * Renders the same empty container that the [kalahamoon_favorites] shortcode
 * already uses, so kalahamoon-favorites.js hydrates it at runtime with no
 * extra bootstrapping.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Widget_Favorites extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'kalahamoon_favorites',
			__( 'Kalahamoon — Favorites', 'kalahamoon' ),
			array(
				'description' => __( "Products the visitor saved to favorites. Client-side, no account needed.", 'kalahamoon' ),
				'classname'   => 'kalahamoon-widget kalahamoon-widget-favorites',
			)
		);
	}

	public function widget( $args, $instance ): void {
		$title = apply_filters( 'widget_title', $instance['title'] ?? __( 'علاقه‌مندی‌ها', 'kalahamoon' ), $instance, $this->id_base );

		echo $args['before_widget']; // phpcs:ignore

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore
		}

		echo do_shortcode( '[kalahamoon_favorites]' );

		echo $args['after_widget']; // phpcs:ignore
	}

	public function form( $instance ): void {
		$title = $instance['title'] ?? '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'عنوان', 'kalahamoon' ); ?></label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ): array {
		return array(
			'title' => sanitize_text_field( $new_instance['title'] ?? '' ),
		);
	}
}
