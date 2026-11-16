<?php
namespace Elementor {
	class Widget_Base {
		public static array $settings = array();

		protected function get_settings_for_display(): array {
			return self::$settings;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );

	class WC_Product {
		public function __construct( private array $meta ) {}

		public function get_id(): int { return 42; }
		public function get_meta( string $key ): string { return $this->meta[ $key ] ?? ''; }
	}

	class Chidemoon_Core_Affiliate {
		public const META_MERCHANT_NAME = '_merchant';
		public const META_SOURCE_CHECKED = '_checked';
		public const META_SOURCE_URL = '_source';
		public const META_FACTS = '_facts';
		public static bool $eligible = true;

		public static function is_publicly_eligible( WC_Product $product ): bool {
			return self::$eligible;
		}

		public static function render_affiliate_cta( array $attributes ): string {
			return '<a class="chidemoon-affiliate-cta" href="/go/42/">' . esc_html( $attributes['label'] ) . '</a>';
		}
	}

	class Chidemoon_Core_Compare {
		public static array $labels = array();
		public static function single_control( WC_Product $product, array $labels = array() ): string {
			self::$labels = $labels;
			return '<button>' . esc_html( $labels['label'] ?? 'مقایسه' ) . '</button>';
		}
	}
	class Chidemoon_Core_Enrichment {
		public static function read( int $id ): array { return array(); }
	}
	class Chidemoon_Core_Looks { public static function product_looks_shortcode( array $attributes ): string { return ''; } }

	class Chidemoon_Core_Commercial {
		public static function availability( WC_Product $product ): string { return 'unknown'; }
		public static function merchant_inventory_html( WC_Product $product ): string { return ''; }
	}
