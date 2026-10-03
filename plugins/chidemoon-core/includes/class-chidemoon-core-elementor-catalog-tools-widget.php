<?php
/**
 * Editable labels and styles for WooCommerce's current archive controls.
 * Query, counts, ordering options and preserved filters remain owned by WooCommerce.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Elementor_Catalog_Tools_Widget extends \Elementor\Widget_Base {
	public function get_name(): string { return 'chidemoon-catalog-tools'; }
	public function get_title(): string { return 'چیدمون | تعداد و مرتب‌سازی محصولات'; }
	public function get_icon(): string { return 'eicon-filter'; }
	public function get_categories(): array { return array( 'general' ); }
	public function get_keywords(): array { return array( 'product', 'archive', 'sort', 'count', 'محصول', 'مرتب‌سازی', 'چیدمون' ); }
	public function get_style_depends(): array { return array( 'chidemoon-public-design' ); }
	public function get_script_depends(): array { return array( 'woocommerce' ); }

	protected function register_controls(): void {
		$this->start_controls_section( 'content', array( 'label' => 'فهرست محصولات' ) );
		$this->add_control( 'data_help', array(
			'type' => \Elementor\Controls_Manager::RAW_HTML,
			'raw' => 'این ابزار تعداد و مرتب‌سازی نتایج فهرست جاری ووکامرس را نمایش می‌دهد. آن را پیش از Loop Grid محصولات قرار دهید و منبع Query کارت‌ها را «Current Query» نگه دارید تا دسته‌بندی، فیلترها و شمارهٔ صفحه حفظ شوند.',
		) );
		$this->add_control( 'show_count', array( 'label' => 'نمایش تعداد نتایج', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		foreach ( array(
			'count_single' => array( 'متن یک نتیجه', 'نمایش یک محصول', '' ),
			'count_all' => array( 'متن همهٔ نتایج', 'نمایش همهٔ {total} محصول', 'از {total} برای تعداد کل استفاده کنید.' ),
			'count_range' => array( 'متن نتایج صفحه', 'نمایش {first} تا {last} از {total} محصول', 'از {first}، {last} و {total} برای بازه و تعداد کل استفاده کنید.' ),
		) as $key => $field ) {
			$this->add_control( $key, array( 'label' => $field[0], 'type' => \Elementor\Controls_Manager::TEXT, 'default' => $field[1], 'description' => $field[2], 'dynamic' => array( 'active' => true ), 'condition' => array( 'show_count' => 'yes' ), 'label_block' => true ) );
		}
		$this->add_control( 'show_sorting', array( 'label' => 'نمایش مرتب‌سازی', 'type' => \Elementor\Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		foreach ( array(
			'order_label' => array( 'عنوان مرتب‌سازی', 'مرتب‌سازی' ),
			'order_default' => array( 'گزینهٔ ترتیب پیش‌فرض', 'پیشنهادی' ),
			'order_popularity' => array( 'گزینهٔ محبوبیت', 'پرفروش‌ترین' ),
			'order_rating' => array( 'گزینهٔ امتیاز', 'بالاترین امتیاز' ),
			'order_latest' => array( 'گزینهٔ تازه‌ترین', 'جدیدترین' ),
			'order_price_asc' => array( 'گزینهٔ قیمت صعودی', 'ارزان‌ترین' ),
			'order_price_desc' => array( 'گزینهٔ قیمت نزولی', 'گران‌ترین' ),
			'order_relevance' => array( 'گزینهٔ ارتباط با جست‌وجو', 'مرتبط‌ترین' ),
		) as $key => $field ) {
			$this->add_control( $key, array( 'label' => $field[0], 'type' => \Elementor\Controls_Manager::TEXT, 'default' => $field[1], 'dynamic' => array( 'active' => true ), 'condition' => array( 'show_sorting' => 'yes' ) ) );
		}
		$this->end_controls_section();

		$this->start_controls_section( 'style', array( 'label' => 'ظاهر کنترل‌های فهرست', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'direction', array( 'label' => 'چیدمان', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( 'row' => 'کنار هم', 'column' => 'زیر هم' ), 'selectors' => array( '{{WRAPPER}} .ch-catalog-tools' => 'flex-direction: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'alignment', array( 'label' => 'تراز', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => array( 'flex-start' => 'ابتدا', 'center' => 'وسط', 'flex-end' => 'انتها', 'space-between' => 'دو سوی فهرست' ), 'selectors' => array( '{{WRAPPER}} .ch-catalog-tools' => 'justify-content: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'فاصلهٔ کنترل‌ها', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 64 ) ), 'selectors' => array( '{{WRAPPER}} .ch-catalog-tools' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'count_typography', 'selector' => '{{WRAPPER}} .woocommerce-result-count' ) );
		$this->add_control( 'count_color', array( 'label' => 'رنگ تعداد نتایج', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .woocommerce-result-count' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'sorting_typography', 'selector' => '{{WRAPPER}} .woocommerce-ordering' ) );
		foreach ( array(
			'label_color' => array( 'رنگ عنوان مرتب‌سازی', '.woocommerce-ordering label', 'color' ),
			'select_color' => array( 'رنگ گزینهٔ انتخاب‌شده', '.woocommerce-ordering select', 'color' ),
			'select_background' => array( 'پس‌زمینهٔ انتخاب', '.woocommerce-ordering select', 'background-color' ),
			'select_border_color' => array( 'رنگ کادر انتخاب', '.woocommerce-ordering select', 'border-color' ),
		) as $key => $field ) {
			$this->add_control( $key, array( 'label' => $field[0], 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} ' . $field[1] => $field[2] . ': {{VALUE}};' ) ) );
		}
		$this->add_responsive_control( 'select_width', array( 'label' => 'عرض انتخاب', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => 120, 'max' => 480 ), '%' => array( 'min' => 20, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .woocommerce-ordering select' => 'width: {{SIZE}}{{UNIT}}; max-width: 100%;' ) ) );
		$this->add_responsive_control( 'select_radius', array( 'label' => 'گردی کادر انتخاب', 'type' => \Elementor\Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 32 ) ), 'selectors' => array( '{{WRAPPER}} .woocommerce-ordering select' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'select_padding', array( 'label' => 'فاصلهٔ داخل انتخاب', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'selectors' => array( '{{WRAPPER}} .woocommerce-ordering select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render(): void {
		if ( ! function_exists( 'woocommerce_result_count' ) || ! function_exists( 'woocommerce_catalog_ordering' ) ) { return; }
		$settings = $this->get_settings_for_display();
		$show_count = 'yes' === ( $settings['show_count'] ?? 'yes' );
		$show_sorting = 'yes' === ( $settings['show_sorting'] ?? 'yes' );
		if ( ! $show_count && ! $show_sorting ) { return; }

		// Filters are installed only while Woo renders this instance, so edits cannot
		// change another widget, Woo screen or response later in the request.
		$translate = static function ( string $translation, string $text ) use ( $settings ): string {
			if ( 'Showing the single result' === $text ) {
				return esc_html( self::text( $settings, 'count_single', 'نمایش یک محصول' ) );
			}
			if ( in_array( $text, array( 'Sort by', 'Shop order' ), true ) ) {
				return self::text( $settings, 'order_label', 'مرتب‌سازی' );
			}
			if ( 'Relevance' === $text ) { return self::text( $settings, 'order_relevance', 'مرتبط‌ترین' ); }
			return $translation;
		};
		$plural = static function ( string $translation, string $single ) use ( $settings ): string {
			if ( in_array( $single, array( 'Showing all %1$d result', 'Showing all %d result' ), true ) ) {
				return self::count_format( self::text( $settings, 'count_all', 'نمایش همهٔ {total} محصول' ), array( '{total}' => '%1$d' ) );
			}
			if ( in_array( $single, array( 'Showing %1$d&ndash;%2$d of %3$d result', 'Showing %1$d–%2$d of %3$d result' ), true ) ) {
				return self::count_format( self::text( $settings, 'count_range', 'نمایش {first} تا {last} از {total} محصول' ), array( '{first}' => '%1$d', '{last}' => '%2$d', '{total}' => '%3$d' ) );
			}
			return $translation;
		};
		$options = static function ( array $options ) use ( $settings ): array {
			foreach ( array(
				'menu_order' => array( 'order_default', 'پیشنهادی' ),
				'popularity' => array( 'order_popularity', 'پرفروش‌ترین' ),
				'rating' => array( 'order_rating', 'بالاترین امتیاز' ),
				'date' => array( 'order_latest', 'جدیدترین' ),
				'price' => array( 'order_price_asc', 'ارزان‌ترین' ),
				'price-desc' => array( 'order_price_desc', 'گران‌ترین' ),
				'relevance' => array( 'order_relevance', 'مرتبط‌ترین' ),
			) as $key => $field ) {
				if ( isset( $options[ $key ] ) ) { $options[ $key ] = self::text( $settings, $field[0], $field[1] ); }
			}
			return $options;
		};
		add_filter( 'gettext_woocommerce', $translate, 100, 2 );
		add_filter( 'ngettext_woocommerce', $plural, 100, 2 );
		add_filter( 'ngettext_with_context_woocommerce', $plural, 100, 2 );
		add_filter( 'woocommerce_catalog_orderby', $options, 100 );
		add_filter( 'woocommerce_catalog_orderedby', $options, 100 );
		try {
			echo '<div class="ch-catalog-tools">';
			if ( $show_count ) { woocommerce_result_count(); }
			if ( $show_sorting ) { self::render_ordering(); }
			echo '</div>';
		} finally {
			remove_filter( 'gettext_woocommerce', $translate, 100 );
			remove_filter( 'ngettext_woocommerce', $plural, 100 );
			remove_filter( 'ngettext_with_context_woocommerce', $plural, 100 );
			remove_filter( 'woocommerce_catalog_orderby', $options, 100 );
			remove_filter( 'woocommerce_catalog_orderedby', $options, 100 );
		}
	}

	private static function text( array $settings, string $key, string $fallback ): string {
		$text = trim( (string) ( $settings[ $key ] ?? '' ) );
		return '' !== $text ? $text : $fallback;
	}

	private static function count_format( string $text, array $placeholders ): string {
		// Percent signs entered as copy must never become printf instructions.
		return strtr( str_replace( '%', '%%', esc_html( $text ) ), $placeholders );
	}

	private static function render_ordering(): void {
		// Woo resets its own page field, but otherwise preserves Elementor's old
		// e-page-* field. Sorting a Loop Grid must also restart on its first page.
		$request = $_GET;
		foreach ( array_keys( $_GET ) as $key ) {
			if ( str_starts_with( (string) $key, 'e-page-' ) ) { unset( $_GET[ $key ] ); }
		}
		try { woocommerce_catalog_ordering( array( 'useLabel' => true ) ); }
		finally { $_GET = $request; }
	}
}
