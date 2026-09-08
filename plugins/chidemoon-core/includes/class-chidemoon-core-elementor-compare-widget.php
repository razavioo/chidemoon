<?php
/**
 * Elementor widget for product comparison table.
 * Reuses the same rendering as the shortcode and Gutenberg block, so the design stays consistent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_Core_Elementor_Compare_Widget extends \Elementor\Widget_Base {

	public function get_name(): string { return 'chidemoon-compare-table'; }
	public function get_title(): string { return __( 'Chidemoon جدول مقایسه', 'chidemoon-core' ); }
	public function get_icon(): string { return 'eicon-table'; }
	public function get_categories(): array { return array( 'chidemoon-commerce', 'general' ); }
	public function get_keywords(): array { return array( 'compare', 'product', 'chidemoon', 'مقایسه' ); }

	protected function register_controls(): void {
		$this->start_controls_section( 'section_content', array(
			'label' => __( 'محصولات', 'chidemoon-core' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'products_help', array(
			'type' => \Elementor\Controls_Manager::RAW_HTML,
			'raw'  => '<p style="font-size:12px;color:#555">' . esc_html__( 'تا ۴ محصول را با شناسه وارد کنید. یا خالی بگذارید تا از پارامتر URL (?products=1,2) خوانده شود — مناسب صفحه /comparisons/.', 'chidemoon-core' ) . '</p>',
		) );

		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'product_id', array(
			'label' => __( 'شناسه محصول', 'chidemoon-core' ),
			'type'  => \Elementor\Controls_Manager::NUMBER,
			'min'   => 1,
			'step'  => 1,
		) );
		$this->add_control( 'products', array(
			'label'       => __( 'محصولات برای مقایسه', 'chidemoon-core' ),
			'type'        => \Elementor\Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'title_field' => 'محصول #{{ product_id }}',
		) );

		$this->add_control( 'ids_text', array(
			'label'       => __( 'یا شناسه‌ها با کاما', 'chidemoon-core' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => '12,34,56',
			'description' => __( 'اگر پر شود، بر لیست بالا اولویت دارد.', 'chidemoon-core' ),
		) );

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$ids = array();
		if ( ! empty( $settings['ids_text'] ) ) {
			$ids = array_map( 'absint', explode( ',', (string) $settings['ids_text'] ) );
		} elseif ( ! empty( $settings['products'] ) && is_array( $settings['products'] ) ) {
			foreach ( $settings['products'] as $row ) {
				$pid = absint( $row['product_id'] ?? 0 );
				if ( $pid ) $ids[] = $pid;
			}
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( count( $ids ) < 2 ) {
			// Try query var for preview on generic comparison page
			if ( empty( $ids ) ) {
				echo \Chidemoon_Core_Compare::render_compare_table_shortcode( array( 'products' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				return;
			}
			echo '<div class="elementor-alert elementor-alert-warning">' . esc_html__( 'حداقل دو محصول برای جدول مقایسه لازم است.', 'chidemoon-core' ) . '</div>';
			return;
		}
		echo \Chidemoon_Core_Compare::render_compare_table_shortcode( array( 'products' => implode( ',', array_slice( $ids, 0, 4 ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	protected function content_template(): void {
		?>
		<# if ( settings.ids_text ) { #>
			<div class="elementor-alert elementor-alert-info">جدول مقایسه: {{ settings.ids_text }}</div>
		<# } else if ( settings.products && settings.products.length ) { #>
			<div class="elementor-alert elementor-alert-info">
				جدول مقایسه: <# _.each(settings.products, function(p, i){ #>{{ p.product_id }}<# if(i < settings.products.length-1){ #>, <# } #><# }); #>
			</div>
		<# } else { #>
			<div class="elementor-alert elementor-alert-warning"><?php esc_html_e( 'حداقل دو محصول انتخاب کنید یا از ?products= در URL استفاده خواهد شد.', 'chidemoon-core' ); ?></div>
		<# } #>
		<?php
	}
}
