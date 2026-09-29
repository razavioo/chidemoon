<?php
/**
 * Elementor widget for Shop the Look — uses same renderer as Gutenberg block and shortcode.
 * This ensures Elementor pages can embed shoppable images without duplicating template logic.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_Core_Elementor_Shop_Look_Widget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'chidemoon-shop-the-look';
	}

	public function get_title(): string {
		return __( 'Chidemoon Shop the Look', 'chidemoon-core' );
	}

	public function get_icon(): string {
		return 'eicon-image-hotspot';
	}

	public function get_categories(): array {
		return array( 'chidemoon-commerce', 'general' );
	}

	public function get_keywords(): array {
		return array( 'shop', 'look', 'hotspot', 'chidemoon', 'product', 'image' );
	}

	public function get_style_depends(): array {
		return array( 'chidemoon-shop-the-look' );
	}

	public function get_script_depends(): array {
		return array( 'chidemoon-shop-the-look-view' );
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'section_image', array(
			'label' => __( 'تصویر چیدمان', 'chidemoon-core' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'image', array(
			'label'   => __( 'تصویر', 'chidemoon-core' ),
			'type'    => \Elementor\Controls_Manager::MEDIA,
			'default' => array( 'url' => \Elementor\Utils::get_placeholder_image_src() ),
		) );

		$this->add_control( 'image_alt', array(
			'label'       => __( 'متن جایگزین', 'chidemoon-core' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => __( 'توضیح کوتاه تصویر برای دسترس‌پذیری', 'chidemoon-core' ),
		) );

		$this->add_control( 'caption', array(
			'label'       => __( 'توضیح تصویر', 'chidemoon-core' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => __( 'مثال: نشیمن مینیمال با چوب روشن', 'chidemoon-core' ),
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'section_hotspots', array(
			'label' => __( 'نقاط محصولات (Hotspots)', 'chidemoon-core' ),
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'hotspots_help', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => esc_html__( 'محصول را با نام جست‌وجو کنید. جایگاه افقی و عمودی از گوشهٔ بالا و چپ تصویر محاسبه می‌شود. فقط نقاط محصولات بررسی‌شده در خروجی عمومی فعال می‌شوند.', 'chidemoon-core' ),
			'content_classes' => 'elementor-descriptor',
		) );

		$repeater = new \Elementor\Repeater();

		$repeater->add_control( 'product_id', array(
			'label'       => __( 'محصول', 'chidemoon-core' ),
			'type'        => \Elementor\Controls_Manager::SELECT2,
			'options'     => Chidemoon_Core_Public_Design::product_options(),
			'label_block' => true,
			'description' => __( 'محصول باید منتشر شده، از نوع External/Affiliate و بررسی‌شده (Reviewed) باشد.', 'chidemoon-core' ),
		) );

		$repeater->add_control( 'label', array(
			'label'       => __( 'برچسب نقطه', 'chidemoon-core' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => __( 'پیش‌فرض: نام محصول', 'chidemoon-core' ),
		) );

		$repeater->add_control( 'x', array(
			'label'      => __( 'موقعیت افقی X (%)', 'chidemoon-core' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( '%' ),
			'range'      => array( '%' => array( 'min' => 2, 'max' => 98, 'step' => 0.5 ) ),
			'default'    => array( 'unit' => '%', 'size' => 50 ),
		) );

		$repeater->add_control( 'y', array(
			'label'      => __( 'موقعیت عمودی Y (%)', 'chidemoon-core' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( '%' ),
			'range'      => array( '%' => array( 'min' => 2, 'max' => 98, 'step' => 0.5 ) ),
			'default'    => array( 'unit' => '%', 'size' => 50 ),
		) );

		$this->add_control( 'hotspots', array(
			'label'       => __( 'نقاط', 'chidemoon-core' ),
			'type'        => \Elementor\Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'title_field' => '{{{ label || "نقطهٔ محصول" }}}',
			'prevent_empty' => false,
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'section_style_hotspot', array(
			'label' => __( 'ظاهر نقاط', 'chidemoon-core' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'point_color', array( 'label' => __( 'رنگ نقطه', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-shop-the-look__hotspot' => 'color: {{VALUE}}; border-color: {{VALUE}};' ) ) );
		$this->add_control( 'point_background', array( 'label' => __( 'پس‌زمینهٔ نقطه', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-shop-the-look__hotspot' => 'background: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'point_size', array( 'label' => __( 'اندازهٔ نقطه', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 44, 'max' => 72 ) ), 'selectors' => array( '{{WRAPPER}} .chidemoon-shop-the-look__hotspot' => 'width: {{SIZE}}px; height: {{SIZE}}px;' ) ) );
		$this->add_control( 'panel_background', array( 'label' => __( 'پس‌زمینهٔ اطلاعات محصول', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-shop-the-look__tooltip' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'panel_text', array( 'label' => __( 'رنگ عنوان و قیمت', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .chidemoon-shop-the-look__tooltip-body h3, {{WRAPPER}} .chidemoon-shop-the-look__price' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'panel_typography', 'selector' => '{{WRAPPER}} .chidemoon-shop-the-look__tooltip-body' ) );
		$this->add_responsive_control( 'image_radius', array( 'label' => __( 'گردی تصویر', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .chidemoon-shop-the-look__image' => 'border-radius: {{SIZE}}px;' ) ) );
		$this->add_control( 'caption_color', array( 'label' => __( 'رنگ زیرنویس', 'chidemoon-core' ), 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} figcaption' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'caption_typography', 'selector' => '{{WRAPPER}} figcaption' ) );

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$image_id = absint( $settings['image']['id'] ?? 0 );
		if ( ! $image_id && ! empty( $settings['image']['url'] ) ) {
			// If user picked placeholder URL without media id, try to find by url? Fallback to 0.
			$image_id = 0;
		}
		if ( $image_id <= 0 ) {
			echo '<div class="elementor-alert elementor-alert-info">' . esc_html__( 'یک تصویر برای Shop the Look انتخاب کنید.', 'chidemoon-core' ) . '</div>';
			return;
		}
		$hotspots = array();
		if ( ! empty( $settings['hotspots'] ) && is_array( $settings['hotspots'] ) ) {
			foreach ( $settings['hotspots'] as $spot ) {
				$pid = absint( $spot['product_id'] ?? 0 );
				if ( $pid <= 0 ) {
					continue;
				}
				$x = isset( $spot['x']['size'] ) ? (float) $spot['x']['size'] : ( isset( $spot['x'] ) ? (float) $spot['x'] : 50 );
				$y = isset( $spot['y']['size'] ) ? (float) $spot['y']['size'] : ( isset( $spot['y'] ) ? (float) $spot['y'] : 50 );
				$hotspots[] = array(
					'x'         => max( 2, min( 98, $x ) ),
					'y'         => max( 2, min( 98, $y ) ),
					'productId' => $pid,
					'label'     => sanitize_text_field( (string) ( $spot['label'] ?? '' ) ),
				);
			}
		}

		$attrs = array(
			'imageId'  => $image_id,
			'imageAlt' => sanitize_text_field( (string) ( $settings['image_alt'] ?? '' ) ),
			'caption'  => sanitize_text_field( (string) ( $settings['caption'] ?? '' ) ),
			'hotspots' => $hotspots,
		);

		Chidemoon_Core_Shop_The_Look::enqueue_assets();

		echo Chidemoon_Core_Shop_The_Look::render_look( $attrs, 'chidemoon-look-widget' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

}
