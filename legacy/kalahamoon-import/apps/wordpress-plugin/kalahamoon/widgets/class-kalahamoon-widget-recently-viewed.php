<?php
/**
 * Recently Viewed widget — classic WP_Widget.
 *
 * Server renders an empty container; kalahamoon-favorites.js (already enqueued
 * globally and aware of both "favorites" and "recently viewed" keys in
 * localStorage) hydrates it at runtime.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Widget_Recently_Viewed extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'kalahamoon_recently_viewed',
			__( 'Kalahamoon — Recently Viewed', 'kalahamoon' ),
			array(
				'description' => __( 'Products the visitor has viewed recently (client-side, privacy-friendly).', 'kalahamoon' ),
				'classname'   => 'kalahamoon-widget kalahamoon-widget-recent',
			)
		);
	}

	public function widget( $args, $instance ): void {
		$title = apply_filters( 'widget_title', $instance['title'] ?? __( 'بازدید اخیر', 'kalahamoon' ), $instance, $this->id_base );
		$limit = max( 1, min( 10, (int) ( $instance['limit'] ?? 4 ) ) );

		wp_enqueue_script( 'kalahamoon-favorites', KALAHAMOON_PLUGIN_URL . 'public/js/kalahamoon-favorites.js', array(), KALAHAMOON_VERSION, true );

		echo $args['before_widget']; // phpcs:ignore

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore
		}
		?>
		<div class="kalahamoon-product-grid kalahamoon-grid-cols-1 kalahamoon-recently-viewed-container"
			data-kalahamoon-storage="recently_viewed"
			data-kalahamoon-limit="<?php echo esc_attr( (string) $limit ); ?>">
			<p class="kalahamoon-recently-viewed-empty">
				<?php esc_html_e( 'هنوز محصولی مشاهده نکرده‌اید.', 'kalahamoon' ); ?>
			</p>
		</div>
		<?php
		echo $args['after_widget']; // phpcs:ignore
	}

	public function form( $instance ): void {
		$title = $instance['title'] ?? '';
		$limit = $instance['limit'] ?? 4;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'عنوان', 'kalahamoon' ); ?></label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'تعداد', 'kalahamoon' ); ?></label>
			<input type="number" min="1" max="10" step="1"
				id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>"
				value="<?php echo esc_attr( (string) $limit ); ?>" />
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ): array {
		return array(
			'title' => sanitize_text_field( $new_instance['title'] ?? '' ),
			'limit' => max( 1, min( 10, (int) ( $new_instance['limit'] ?? 4 ) ) ),
		);
	}
}
