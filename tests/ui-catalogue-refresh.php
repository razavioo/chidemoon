<?php
declare( strict_types=1 );

define( 'WP_CLI', true );

class WP_CLI {
	public static array $messages = array();
	public static function log( string $message ): void { self::$messages[] = $message; }
	public static function warning( string $message ): void { self::$messages[] = $message; }
	public static function success( string $message ): void { self::$messages[] = $message; }
	public static function error( string $message ): never { throw new RuntimeException( $message ); }
}

function current_user_can( string $capability ): bool { return 'edit_products' === $capability; }
function wp_json_encode( mixed $value, int $options = 0 ): string|false { return json_encode( $value, $options ); }
function wp_kses_post( string $text ): string { return $text; }
function wp_slash( array $post ): array { return $post; }
function is_wp_error( mixed $value ): bool { return false; }
function get_posts( array $query ): array {
	$ids = array();
	foreach ( $GLOBALS['posts'] as $id => $post ) {
		if ( $post['sourceKey'] === $query['meta_value'] ) {
			$ids[] = $id;
		}
	}
	return array_slice( $ids, 0, $query['posts_per_page'] );
}
function get_post( int $id ): object|false {
	if ( ! isset( $GLOBALS['posts'][ $id ] ) ) {
		return false;
	}
	$post = $GLOBALS['posts'][ $id ];
	return (object) array( 'post_type' => $post['type'], 'post_title' => $post['title'], 'post_content' => $post['content'] );
}
function wp_update_post( array $change, bool $error = false ): int {
	check( array_diff( array_keys( $change ), array( 'ID', 'post_title', 'post_content' ) ) === array(), 'A protected product field was written.' );
	$id = $change['ID'];
	if ( isset( $change['post_title'] ) ) {
		$GLOBALS['posts'][ $id ]['title'] = $change['post_title'];
	}
	if ( isset( $change['post_content'] ) ) {
		$GLOBALS['posts'][ $id ]['content'] = $change['post_content'];
	}
	$GLOBALS['writes'][] = $change;
	return $id;
}
function check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$export_path = __DIR__ . '/../tools/catalogue/verified-products.export.json';
$export = json_decode( (string) file_get_contents( $export_path ), true, 512, JSON_THROW_ON_ERROR );
$GLOBALS['posts'] = array();
$GLOBALS['writes'] = array();
foreach ( $export['items'] as $index => $item ) {
	$content = $item['description'];
	if ( 'digikala:563439' === $item['sourceKey'] ) {
		$content = str_replace(
			'این قیمت برای پیشنهاد هالی نور و رنگ صورتی در زمان بررسی ثبت شده است. تصویر اصلی چراغ قرمز را نشان می‌دهد؛ رنگ عکس با رنگ پیشنهاد قیمت‌گذاری‌شده یکی نیست. رنگ انتخابی، موجودی و فروشندهٔ منتخب ممکن است تغییر کنند؛ هزینهٔ ارسال در این مبلغ محاسبه نشده است.',
			'این قیمت برای پیشنهاد هالی نور و رنگ صورتی در زمان بررسی ثبت شده است. موجودی و فروشندهٔ منتخب ممکن است تغییر کند؛ هزینهٔ ارسال در این مبلغ محاسبه نشده است.',
			$content
		);
	}
	$GLOBALS['posts'][ $index + 10 ] = array(
		'type' => 'product', 'sourceKey' => $item['sourceKey'],
		'title' => $item['title'] . ' | ' . $item['merchant']['platform'],
		'content' => $content, 'price' => $item['price'], 'image' => $item['imageUrl'], 'editorMeta' => 'keep',
	);
}
$initial = $GLOBALS['posts'];
$args = array();
require __DIR__ . '/../tools/ui-catalogue-refresh.php';
check( $GLOBALS['posts'] === $initial && $GLOBALS['writes'] === array(), 'Default invocation must be a dry run.' );

$report = chidemoon_ui_catalogue_refresh( true );
check( $report === array( 'planned' => 0, 'updated' => 4, 'unchanged' => 0, 'manual' => 0 ), 'Expected four scoped updates.' );
check( count( $GLOBALS['writes'] ) === 4, 'Expected exactly four write calls.' );
foreach ( $export['items'] as $index => $item ) {
	$post = $GLOBALS['posts'][ $index + 10 ];
	check( $post['title'] === $item['title'], 'Expected updated title for ' . $item['sourceKey'] );
	check( $post['price'] === $initial[ $index + 10 ]['price'] && $post['image'] === $initial[ $index + 10 ]['image'] && 'keep' === $post['editorMeta'], 'Price, media, and metadata must remain untouched.' );
	check( $post['content'] === ( 'digikala:563439' === $item['sourceKey'] ? $item['description'] : $initial[ $index + 10 ]['content'] ), 'Only the specific description may change.' );
}
$report = chidemoon_ui_catalogue_refresh( true );
check( $report['updated'] === 0 && count( $GLOBALS['writes'] ) === 4, 'Repeated apply must be idempotent.' );

$GLOBALS['posts'] = $initial;
$GLOBALS['writes'] = array();
$GLOBALS['posts'][10]['title'] = 'عنوان اختصاصی سردبیر';
$GLOBALS['posts'][10]['content'] = '<p>توضیح اختصاصی سردبیر</p>';
$GLOBALS['posts'][11]['title'] = 'عنوان اختصاصی دیگر';
$GLOBALS['posts'][12]['sourceKey'] = 'missing:source';
$GLOBALS['posts'][14] = $initial[13];
$report = chidemoon_ui_catalogue_refresh( true );
check( $report['manual'] === 5, 'Editor edits, missing source, and duplicate source should all be reported.' );
check( $GLOBALS['posts'][10]['title'] === 'عنوان اختصاصی سردبیر' && $GLOBALS['posts'][10]['content'] === '<p>توضیح اختصاصی سردبیر</p>', 'Editor copy must be preserved.' );
check( $GLOBALS['posts'][11]['title'] === 'عنوان اختصاصی دیگر', 'Editor title must be preserved.' );
check( $GLOBALS['posts'][13] === $initial[13] && $GLOBALS['posts'][14] === $initial[13], 'Ambiguous products must be untouched.' );

$tampered_path = tempnam( sys_get_temp_dir(), 'chidemoon-catalogue-' );
check( false !== $tampered_path, 'Could not prepare tampered export fixture.' );
try {
	$export['items'][0]['title'] .= ' altered';
	file_put_contents( $tampered_path, json_encode( $export, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) );
	$write_count = count( $GLOBALS['writes'] );
	try {
		chidemoon_ui_catalogue_refresh( true, $tampered_path );
		throw new RuntimeException( 'Tampered export was accepted.' );
	} catch ( RuntimeException $exception ) {
		check( str_contains( $exception->getMessage(), 'checksum' ), 'Tampered export must fail on its checksum.' );
	}
	check( count( $GLOBALS['writes'] ) === $write_count, 'Invalid export must not write products.' );
} finally {
	unlink( $tampered_path );
}

echo "Catalogue UI refresh fixtures passed.\n";
