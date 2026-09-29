<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

class Chidemoon_Core_Affiliate {
	public const META_MERCHANT_NAME = '_chidemoon_merchant_name';
	public const META_SOURCE_KEY = '_chidemoon_source_key';
	public static function is_publicly_eligible( WC_Product $product ): bool {
		return in_array( $product->get_id(), $GLOBALS['eligible_product_ids'], true );
	}
}

class Chidemoon_Core_Shop_The_Look {
	public const TAXONOMY = 'chidemoon_room';
}

class WC_Product {
	public function __construct( private int $id ) {}
	public function get_id(): int { return $this->id; }
	public function get_price_html(): string { return '<span>1,195,000&nbsp;<bdi>تومان</bdi></span>'; }
	public function get_meta( string $key ): string { return 'فروشندهٔ نمونه'; }
	public function get_name(): string { return 'چراغ نمونه'; }
	public function is_type( string $type ): bool { return 'external' === $type; }
}

class WP_Query {
	public array $settings = array();
	public function set( string $key, $value ): void { $this->settings[ $key ] = $value; }
}

class Test_Widget {
	public function __construct( private string $name, private string $classes = '' ) {}
	public function get_name(): string { return $this->name; }
	public function get_settings_for_display( string $name ): string { return '_css_classes' === $name ? $this->classes : ''; }
}

$GLOBALS['eligible_product_ids'] = array( 4 );
$GLOBALS['look_hotspots'] = array(
	2 => array(),
	3 => array( array( 'product_id' => 4 ) ),
	4 => array( array( 'product_id' => 5 ) ),
	5 => array( array( 'product_source_key' => 'seller:eligible' ) ),
	6 => array( array( 'product_id' => 5, 'product_source_key' => 'seller:eligible' ) ),
	7 => array( array( 'product_source_key' => 'seller:missing' ) ),
	8 => array( array( 'product_id' => 5 ), array( 'productId' => 4 ) ),
);

function get_post_type( int $id ): string { return 1 === $id ? 'product' : 'post'; }
function wc_get_product( int $id ) { return in_array( $id, array( 1, 4, 5 ), true ) ? new WC_Product( $id ) : false; }
function get_post_meta( int $id, string $key, bool $single = false ) {
	if ( '_chidemoon_native_look' === $key ) return array_key_exists( $id, $GLOBALS['look_hotspots'] );
	if ( '_elementor_data' === $key && isset( $GLOBALS['look_hotspots'][ $id ] ) ) {
		return json_encode( array( array( 'widgetType' => 'chidemoon-shop-the-look', 'settings' => array( 'hotspots' => $GLOBALS['look_hotspots'][ $id ] ) ) ) );
	}
	return '';
}
function wp_get_post_categories( int $id, array $args = array() ): array { return isset( $args['fields'] ) ? array( 'room-ideas' ) : array( 8 ); }
function wp_strip_all_tags( string $html ): string { return strip_tags( $html ); }
function get_the_title( int $id ): string { return 1 === $id ? 'چراغ نمونه' : 'ایدهٔ اتاق'; }
function get_queried_object_id(): int { return 2; }
function get_posts( array $args ): array {
	if ( isset( $args['meta_key'] ) ) {
		if ( 'product' !== $args['post_type'] || 'publish' !== $args['post_status'] || Chidemoon_Core_Affiliate::META_SOURCE_KEY !== $args['meta_key'] ) {
			throw new RuntimeException( 'Source-key lookup must use public products.' );
		}
		return 'seller:eligible' === $args['meta_value'] ? array( 4 ) : array();
	}
	return array( 3 );
}
function absint( $value ): int { return abs( (int) $value ); }
function sanitize_text_field( string $value ): string { return trim( $value ); }
function get_terms( array $args ): array { return array( (object) array( 'slug' => 'living-room', 'name' => 'نشیمن' ) ); }
function is_wp_error( $value ): bool { return false; }
function get_page_by_path( string $path ): object { return (object) array( 'ID' => 10 ); }
function get_permalink( $post ): string { return '/shop-the-look/'; }
function home_url( string $path ): string { return $path; }
function esc_url( string $value ): string { return $value; }
function esc_html( string $value ): string { return $value; }
function add_query_arg( string $key, string $value, string $url ): string { return $url . '?' . $key . '=' . $value; }
function sanitize_title( string $value ): string { return $value; }
function wp_unslash( string $value ): string { return $value; }

require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-public-design.php';

function check( bool $condition, string $message ): void {
	if ( ! $condition ) throw new RuntimeException( $message );
}

$html = '<div class="elementor-posts-container">'
	. '<article class="elementor-post post-1 type-product"><a class="elementor-post__thumbnail__link" href="/product/"><div class="elementor-post__thumbnail"><img src="/a.jpg" alt="چراغ"></div></a><div class="elementor-post__text"><h2 class="elementor-post__title"><a href="/product/">چراغ نمونه</a></h2><div class="elementor-post__excerpt">توضیح</div><a class="elementor-post__read-more" href="/product/">مشاهده</a></div></article>'
	. '<article class="elementor-post post-2 type-post"><div class="elementor-post__text"><h2 class="elementor-post__title"><a href="/idea/">ایدهٔ اتاق</a></h2><a class="elementor-post__read-more" href="/idea/">مشاهده</a></div></article>'
	. '</div>';
$editorial_widget = new Test_Widget( 'archive-posts', 'ch-editorial-feed ch-search-results-feed' );
$result = Chidemoon_Core_Public_Design::content_card_details( $html, $editorial_widget );
check( str_contains( $result, 'ch-content-type--product">محصول</span>' ), 'Product type is visible.' );
check( str_contains( $result, 'ch-content-type--post">ایدهٔ مفهومی</span>' ), 'Conceptual look is labelled before opening.' );
check( str_contains( $result, '۱,۱۹۵,۰۰۰' ), 'Product price uses Persian digits.' );
check( str_contains( $result, 'فروشنده: فروشندهٔ نمونه' ), 'Merchant is visible on the result card.' );
check( str_contains( $result, 'aria-label="دیدن محصول: چراغ نمونه"' ), 'Destination action names the product.' );
check( $html === Chidemoon_Core_Public_Design::content_card_details( $html, new Test_Widget( 'heading' ) ), 'Other widget HTML is unchanged.' );
check( $html === Chidemoon_Core_Public_Design::content_card_details( $html, new Test_Widget( 'posts' ) ), 'Unmarked posts widgets are unchanged.' );
check( $html === Chidemoon_Core_Public_Design::content_card_details( $html, new Test_Widget( 'posts', 'other-ch-editorial-feed' ) ), 'Only the exact editorial class is enhanced.' );

function look_card( int $id, Test_Widget $widget ): string {
	$html = '<article class="elementor-post post-' . $id . ' type-post"><div class="elementor-post__text"><h2 class="elementor-post__title">ایدهٔ اتاق</h2></div></article>';
	return Chidemoon_Core_Public_Design::content_card_details( $html, $widget );
}

check( str_contains( look_card( 3, $editorial_widget ), 'چیدمان قابل خرید' ), 'An eligible direct hotspot makes a look buyable.' );
check( str_contains( look_card( 4, $editorial_widget ), 'ایدهٔ مفهومی' ), 'An ineligible direct hotspot stays conceptual.' );
check( str_contains( look_card( 5, $editorial_widget ), 'چیدمان قابل خرید' ), 'A published source-key product can make a look buyable.' );
check( str_contains( look_card( 6, $editorial_widget ), 'ایدهٔ مفهومی' ), 'An explicit ineligible product takes precedence over a valid source key.' );
check( str_contains( look_card( 7, $editorial_widget ), 'ایدهٔ مفهومی' ), 'An unresolved source key stays conceptual.' );
check( str_contains( look_card( 8, $editorial_widget ), 'چیدمان قابل خرید' ), 'One eligible hotspot among several is enough.' );
check( 'دیدن جزئیات محصول' === Chidemoon_Core_Public_Design::loop_offer_text( 'خرید', new WC_Product( 1 ) ), 'Unreviewed product uses a detail action.' );
check( 'خرید' === Chidemoon_Core_Public_Design::loop_offer_text( 'خرید', new WC_Product( 4 ) ), 'Reviewed offer retains its action.' );
check( str_contains( Chidemoon_Core_Public_Design::loop_offer_label( array(), new WC_Product( 1 ) )['attributes']['aria-label'], 'جزئیات محصول' ), 'Unreviewed product label names the detail destination.' );
check( str_contains( Chidemoon_Core_Public_Design::loop_offer_label( array(), new WC_Product( 4 ) )['attributes']['aria-label'], 'پیشنهاد فروشنده' ), 'Reviewed product label names the seller offer.' );
$GLOBALS['eligible_product_ids'] = array();
check( str_contains( look_card( 3, $editorial_widget ), 'ایدهٔ مفهومی' ), 'A hotspot loses its buyable label when eligibility changes.' );

$query = new WP_Query();
Chidemoon_Core_Public_Design::related_posts_query( $query );
check( array( 2 ) === $query->settings['post__not_in'], 'Related posts exclude the current article.' );
check( array( 8 ) === $query->settings['category__in'], 'Related posts prefer the same category.' );
check( 2 === $query->settings['posts_per_page'], 'Related posts remain compact.' );

$filters = Chidemoon_Core_Public_Design::room_filters();
check( str_contains( $filters, 'id="ch-room-filters"' ), 'Room filters have a stable target.' );
check( str_contains( $filters, 'href="/shop-the-look/#ch-room-filters"' ), 'All rooms link returns to the filter row.' );
check( str_contains( $filters, 'href="/shop-the-look/?room=living-room#ch-room-filters"' ), 'Room links return to the filter row.' );
$_GET['room'] = 'living-room';
check( str_contains( Chidemoon_Core_Public_Design::room_filters(), 'href="/shop-the-look/?room=living-room#ch-room-filters" aria-current="page"' ), 'The chosen room stays selected after navigation.' );
unset( $_GET['room'] );

echo "Public design card and related-query checks passed.\n";
