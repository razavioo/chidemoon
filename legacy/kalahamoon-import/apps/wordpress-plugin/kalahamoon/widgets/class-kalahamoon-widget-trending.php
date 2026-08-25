<?php
/**
 * Trending Products widget — classic WP_Widget.
 *
 * Queries kalahamoon_clicks for the top-clicked products of the last N days and
 * renders them using the shared [kalahamoon_products ids=""] shortcode so the
 * visual output matches blocks/widgets/patterns.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Widget_Trending extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'kalahamoon_trending',
			__( 'Kalahamoon — Trending Products', 'kalahamoon' ),
			array(
				'description' => __( 'Top-clicked Kalahamoon affiliate products over the last N days.', 'kalahamoon' ),
				'classname'   => 'kalahamoon-widget kalahamoon-widget-trending',
			)
		);
	}

	public function widget( $args, $instance ): void {
		$title  = apply_filters( 'widget_title', $instance['title'] ?? __( 'پرکلیک‌ترین محصولات', 'kalahamoon' ), $instance, $this->id_base );
		$days   = max( 1, min( 90, (int) ( $instance['days']  ?? 7 ) ) );
		$limit  = max( 1, min( 10, (int) ( $instance['limit'] ?? 5 ) ) );
		$cols   = max( 1, min( 2,  (int) ( $instance['cols']  ?? 1 ) ) );

		$ids = self::top_product_ids( $days, $limit );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore
		}

		if ( empty( $ids ) ) {
			echo '<p class="kalahamoon-widget-empty">' . esc_html__( 'هنوز آماری برای این بازه موجود نیست.', 'kalahamoon' ) . '</p>';
		} else {
			/**
			 * Filter the query args passed to Kalahamoon widget shortcode calls.
			 *
			 * @param array  $query_args Shortcode attribute map.
			 * @param string $widget_id  The current widget's base id.
			 */
			$query_args = apply_filters( 'kalahamoon_widget_query_args', array(
				'ids'     => implode( ',', $ids ),
				'columns' => $cols,
				'limit'   => $limit,
			), 'trending' );

			echo do_shortcode( sprintf(
				'[kalahamoon_products ids="%s" columns="%d" limit="%d"]',
				esc_attr( $query_args['ids'] ),
				(int) $query_args['columns'],
				(int) $query_args['limit']
			) );
		}

		echo $args['after_widget']; // phpcs:ignore
	}

	public function form( $instance ): void {
		$title = $instance['title'] ?? '';
		$days  = $instance['days']  ?? 7;
		$limit = $instance['limit'] ?? 5;
		$cols  = $instance['cols']  ?? 1;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'عنوان', 'kalahamoon' ); ?></label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'days' ) ); ?>"><?php esc_html_e( 'بازه (روز)', 'kalahamoon' ); ?></label>
			<input type="number" min="1" max="90" step="1"
				id="<?php echo esc_attr( $this->get_field_id( 'days' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'days' ) ); ?>"
				value="<?php echo esc_attr( (string) $days ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'تعداد محصول', 'kalahamoon' ); ?></label>
			<input type="number" min="1" max="10" step="1"
				id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>"
				value="<?php echo esc_attr( (string) $limit ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'cols' ) ); ?>"><?php esc_html_e( 'ستون‌ها', 'kalahamoon' ); ?></label>
			<select id="<?php echo esc_attr( $this->get_field_id( 'cols' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'cols' ) ); ?>">
				<option value="1" <?php selected( $cols, 1 ); ?>>1</option>
				<option value="2" <?php selected( $cols, 2 ); ?>>2</option>
			</select>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ): array {
		return array(
			'title' => sanitize_text_field( $new_instance['title'] ?? '' ),
			'days'  => max( 1, min( 90, (int) ( $new_instance['days']  ?? 7 ) ) ),
			'limit' => max( 1, min( 10, (int) ( $new_instance['limit'] ?? 5 ) ) ),
			'cols'  => max( 1, min( 2,  (int) ( $new_instance['cols']  ?? 1 ) ) ),
		);
	}

	/**
	 * @return string[] Product IDs (kalahamoon IDs), ordered by click count desc.
	 */
	private static function top_product_ids( int $days, int $limit ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_clicks';
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$cache_key = sprintf( 'kalahamoon_trending_%d_%d', $days, $limit );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT product_id FROM {$table}
			 WHERE product_id IS NOT NULL AND product_id != '' AND clicked_at >= %s
			 GROUP BY product_id
			 ORDER BY COUNT(*) DESC
			 LIMIT %d",
			$since,
			$limit
		) );

		$ids = array_values( array_filter( (array) $rows ) );
		set_transient( $cache_key, $ids, HOUR_IN_SECONDS );
		return $ids;
	}
}
