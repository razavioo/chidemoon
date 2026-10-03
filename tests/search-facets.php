<?php
declare( strict_types=1 );

define( 'ABSPATH', __DIR__ );
define( 'CHIDEMOON_CORE_DIR', __DIR__ . '/../plugins/chidemoon-core/' );

class Test_Elementor_Widget_Base {}
class_alias( Test_Elementor_Widget_Base::class, 'Elementor\\Widget_Base' );

class WP_Query {
	public static array $count_queries = array();
	public array $settings = array();
	public int $found_posts = 0;

	public function __construct( array $args = array(), private bool $main = false, private bool $search = false ) {
		$this->settings = $args;
		if ( $args ) {
			self::$count_queries[] = $args;
			$this->found_posts = array( 'product' => 12, 'post' => 3 )[ $args['post_type'] ] ?? 0;
		}
	}

	public function is_main_query(): bool { return $this->main; }
	public function is_search(): bool { return $this->search; }
	public function set( string $key, $value ): void { $this->settings[ $key ] = $value; }
}

$GLOBALS['is_admin'] = false;
$GLOBALS['is_search'] = true;
$GLOBALS['search_term'] = 'مبل و میز';
$GLOBALS['registered_hooks'] = array();

function is_admin(): bool { return $GLOBALS['is_admin']; }
function is_search(): bool { return $GLOBALS['is_search']; }
function get_search_query( bool $escaped = true ): string { return $GLOBALS['search_term']; }
function get_search_link( string $term ): string { return 'https://chidemoon.test/?s=' . rawurlencode( $term ); }
function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_unslash( string $value ): string { return stripslashes( $value ); }
function add_query_arg( string $key, string $value, string $url ): string { return $url . '&' . rawurlencode( $key ) . '=' . rawurlencode( $value ); }
function esc_url( string $url ): string { return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ); }
function esc_html( string $text ): string { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function add_action( string $hook, callable $callback, int $priority ): void { $GLOBALS['registered_hooks'][] = array( $hook, $callback, $priority ); }

require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-search-facets.php';

function check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}

Chidemoon_Core_Search_Facets::register();
check( $GLOBALS['registered_hooks'][0][0] === 'pre_get_posts' && $GLOBALS['registered_hooks'][0][2] === 20, 'The main query filter is registered.' );
check( $GLOBALS['registered_hooks'][1][0] === 'elementor/widgets/register', 'The native Elementor widget is registered.' );
$manager = new class {
	public ?object $widget = null;
	public function register( object $widget ): void { $this->widget = $widget; }
};
Chidemoon_Core_Search_Facets::register_elementor_widget( $manager );
check( $manager->widget instanceof Chidemoon_Core_Elementor_Search_Facets_Widget && $manager->widget->get_name() === 'chidemoon-search-facets', 'The registered widget has the expected Elementor name.' );

$_GET['content_type'] = 'PRODUCT';
check( Chidemoon_Core_Search_Facets::selected_type() === 'product', 'A valid type is normalized.' );
$_GET['content_type'] = 'post';
check( Chidemoon_Core_Search_Facets::selected_type() === 'post', 'Post is a valid type.' );
$_GET['content_type'] = 'page';
check( Chidemoon_Core_Search_Facets::selected_type() === '', 'Unsupported types are rejected.' );
$_GET['content_type'] = 'product\\<script>';
check( Chidemoon_Core_Search_Facets::selected_type() === '', 'A modified type cannot become a valid filter.' );
$_GET['content_type'] = array( 'product' );
$warnings = array();
set_error_handler( static function ( int $severity, string $message ) use ( &$warnings ): bool {
	$warnings[] = $message;
	return true;
} );
$array_type = Chidemoon_Core_Search_Facets::selected_type();
restore_error_handler();
check( $array_type === '' && $warnings === array(), 'Array query parameters are rejected without a PHP warning.' );
unset( $_GET['content_type'] );
check( Chidemoon_Core_Search_Facets::selected_type() === '', 'A missing type selects all results.' );

$_GET['content_type'] = 'product';
$query = new WP_Query( array(), true, true );
Chidemoon_Core_Search_Facets::filter_query( $query );
check( $query->settings['post_type'] === 'product', 'The selected type filters the main search query.' );
foreach ( array( new WP_Query( array(), false, true ), new WP_Query( array(), true, false ) ) as $other_query ) {
	Chidemoon_Core_Search_Facets::filter_query( $other_query );
	check( ! isset( $other_query->settings['post_type'] ), 'Secondary and non-search queries stay untouched.' );
}
$GLOBALS['is_admin'] = true;
$admin_query = new WP_Query( array(), true, true );
Chidemoon_Core_Search_Facets::filter_query( $admin_query );
check( ! isset( $admin_query->settings['post_type'] ), 'Admin search queries stay untouched.' );
$GLOBALS['is_admin'] = false;
unset( $_GET['content_type'] );
$all_query = new WP_Query( array(), true, true );
Chidemoon_Core_Search_Facets::filter_query( $all_query );
check( ! isset( $all_query->settings['post_type'] ), 'The all-results view preserves the default search query.' );

$GLOBALS['is_search'] = false;
check( Chidemoon_Core_Search_Facets::render() === '' && WP_Query::$count_queries === array(), 'Facets do not render or query outside search.' );
$GLOBALS['is_search'] = true;
$_GET['content_type'] = 'post';
$html = Chidemoon_Core_Search_Facets::render();
check( count( WP_Query::$count_queries ) === 2, 'Each content type receives one count query.' );
foreach ( WP_Query::$count_queries as $count_query ) {
	check( $count_query['s'] === $GLOBALS['search_term'], 'Counts use the current search term.' );
	check( $count_query['post_status'] === 'publish' && $count_query['posts_per_page'] === 1 && $count_query['fields'] === 'ids', 'Counts avoid fetching complete posts and drafts.' );
	check( $count_query['ignore_sticky_posts'] === true, 'Sticky posts do not distort counts.' );
}
check( array_column( WP_Query::$count_queries, 'post_type' ) === array( 'product', 'post' ), 'Both result types are counted.' );

$document = new DOMDocument();
@$document->loadHTML( '<?xml encoding="UTF-8">' . $html );
$xpath = new DOMXPath( $document );
$links = $xpath->query( '//nav[@class="ch-search-facets"]/a' );
check( $links->length === 3, 'The facets expose all, product, and article choices.' );
$expected = array(
	array( 'همه', '۱۵', get_search_link( $GLOBALS['search_term'] ), false ),
	array( 'محصولات', '۱۲', get_search_link( $GLOBALS['search_term'] ) . '&content_type=product', false ),
	array( 'مطالب', '۳', get_search_link( $GLOBALS['search_term'] ) . '&content_type=post', true ),
);
foreach ( $expected as $index => list( $label, $count, $url, $current ) ) {
	$link = $links->item( $index );
	check( $link->getAttribute( 'href' ) === $url, "Facet $index preserves the search term and destination." );
	check( $xpath->evaluate( 'string(./span[1])', $link ) === $label, "Facet $index has the expected name." );
	check( $xpath->evaluate( 'string(./span[2])', $link ) === $count, "Facet $index has the expected Persian count." );
	check( ( $link->getAttribute( 'aria-current' ) === 'page' ) === $current, "Facet $index marks current state accurately." );
}

$query_count = count( WP_Query::$count_queries );
$custom = Chidemoon_Core_Search_Facets::render( array( 'all_label' => 'همهٔ نتایج', 'product_label' => '<کالا>', 'hide_counts' => 'yes' ) );
check( str_contains( $custom, 'همهٔ نتایج' ) && str_contains( $custom, '&lt;کالا&gt;' ), 'Editor labels are applied and escaped.' );
check( ! str_contains( $custom, 'ch-search-facets__count' ) && count( WP_Query::$count_queries ) === $query_count, 'Hidden counters skip count queries.' );
$GLOBALS['is_search'] = false;
$preview = Chidemoon_Core_Search_Facets::render( array( 'post_label' => 'مقاله‌ها' ), true );
check( str_contains( $preview, 'مقاله‌ها' ) && ! str_contains( $preview, 'ch-search-facets__count' ) && count( WP_Query::$count_queries ) === $query_count, 'Editor preview shows labels without querying or inventing counts.' );

echo "Search facets query and render checks passed.\n";
