<?php
declare( strict_types=1 );

define( 'WP_CLI', true );
define( 'OBJECT', 'OBJECT' );

class WP_CLI {
	public static function log( string $message ): void {}
	public static function success( string $message ): void {}
	public static function error( string $message ): never { throw new RuntimeException( $message ); }
}

function current_user_can( string $capability ): bool { return 'publish_posts' === $capability; }
function get_page_by_path( string $slug, string $output, string $type ): object|false {
	foreach ( $GLOBALS['state']['posts'] as $id => $post ) {
		if ( $post['slug'] === $slug ) {
			return (object) array( 'ID' => (int) $id, 'post_content' => $post['content'] );
		}
	}
	return false;
}
function get_post_meta( int $id, string $key, bool $single = false ): mixed {
	return $GLOBALS['state']['posts'][ $id ]['meta'][ $key ]
		?? $GLOBALS['state']['assets'][ $id ]['meta'][ $key ]
		?? '';
}
function update_post_meta( int $id, string $key, mixed $value ): void {
	if ( isset( $GLOBALS['state']['posts'][ $id ] ) ) {
		throw new RuntimeException( 'Refresh must not change post metadata: ' . $key );
	}
	$GLOBALS['state']['assets'][ $id ]['meta'][ $key ] = $value;
}
function get_post_thumbnail_id( int $id ): int { return $GLOBALS['state']['posts'][ $id ]['thumbnail']; }
function set_post_thumbnail( int $id, int $image_id ): void {
	$GLOBALS['state']['posts'][ $id ]['thumbnail'] = $image_id;
	$GLOBALS['state']['thumbnail_writes'][] = array( $id, $image_id );
}
function get_attached_file( int $id ): string { return $GLOBALS['state']['assets'][ $id ]['file']; }
function wp_basename( string $path ): string { return basename( $path ); }
function get_posts( array $query ): array {
	$matches = array();
	foreach ( $GLOBALS['state']['assets'] as $id => $asset ) {
		$meta = $asset['meta'][ $query['meta_key'] ] ?? '';
		if ( '_wp_attached_file' === $query['meta_key'] ? str_contains( $meta, $query['meta_value'] ) : $meta === $query['meta_value'] ) {
			$matches[] = (int) $id;
		}
	}
	return array_slice( $matches, 0, $query['posts_per_page'] === -1 ? null : $query['posts_per_page'] );
}
function wp_insert_post( array $post, bool $return_error = false ): never { throw new RuntimeException( 'Refresh created a post.' ); }
function wp_update_post( array $post ): never { throw new RuntimeException( 'Refresh changed post content.' ); }

function check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function asset( string $filename, bool $seed = true ): array {
	return array( 'file' => '/uploads/' . $filename, 'meta' => array(
		'_wp_attached_file' => '/uploads/' . $filename,
		'_chidemoon_seed_image_file' => $seed ? $filename : '',
		'_wp_attachment_image_alt' => 'چیدمان مفهومی خانه',
	) );
}
function post( string $slug, int $thumbnail, bool $seed = true ): array {
	return array(
		'slug' => $slug,
		'thumbnail' => $thumbnail,
		'content' => '<p>متن ویرایش‌شدهٔ سردبیر</p>',
		'meta' => array(
			'_chidemoon_rebuild_editorial' => $seed ? '2026-09-29' : '',
			'_elementor_data' => '[{"editor":"edited"}]',
		),
	);
}
function initial_state(): array {
	return array(
		'posts' => array(
			1 => post( 'dining-table-size-guide', 11 ),
			2 => post( 'open-shelves-or-closed-storage', 12 ),
			3 => post( 'round-or-rectangular-table', 11 ),
		),
		'assets' => array(
			11 => asset( 'look-japandi-dining.jpg' ),
			12 => asset( 'look-compact-home-office.jpg' ),
			21 => asset( 'look-cozy-dining-corner.jpg' ),
			22 => asset( 'look-work-nook-bookshelf.jpg' ),
			31 => asset( 'editor-choice.jpg', false ),
			41 => asset( 'look-japandi-dining.jpg', false ),
		),
		'thumbnail_writes' => array(),
	);
}
function run_refresh( array $state ): array {
	$path = tempnam( sys_get_temp_dir(), 'chidemoon-editorial-' );
	if ( false === $path ) {
		throw new RuntimeException( 'Could not prepare test state.' );
	}
	try {
		file_put_contents( $path, json_encode( $state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE ) );
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --child ' . escapeshellarg( $path ) . ' 2>&1';
		exec( $command, $output, $status );
		check( 0 === $status, 'Refresh process failed: ' . implode( "\n", $output ) );
		return json_decode( (string) file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	} finally {
		unlink( $path );
	}
}

if ( ( $argv[1] ?? '' ) === '--child' ) {
	$GLOBALS['state'] = json_decode( (string) file_get_contents( $argv[2] ), true, 512, JSON_THROW_ON_ERROR );
	$args = array( 'refresh-seed-media' );
	require __DIR__ . '/../tools/rebuild-editorial.php';
	file_put_contents( $argv[2], json_encode( $GLOBALS['state'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE ) );
	exit( 0 );
}

$initial = initial_state();
$first = run_refresh( $initial );
check( $first['thumbnail_writes'] === array( array( 1, 21 ), array( 2, 22 ) ), 'Only the two original seed thumbnails should change.' );
foreach ( $initial['posts'] as $id => $before ) {
	check( $first['posts'][ $id ]['content'] === $before['content'], 'Post content changed.' );
	check( $first['posts'][ $id ]['meta'] === $before['meta'], 'Post metadata or Elementor data changed.' );
}
check( count( $first['posts'] ) === count( $initial['posts'] ), 'A post was created.' );
$second = run_refresh( $first );
check( $second['thumbnail_writes'] === $first['thumbnail_writes'], 'Repeated refresh changed thumbnails again.' );

$edited = initial_state();
$edited['posts'][1]['thumbnail'] = 31;
$edited['posts'][2]['meta']['_chidemoon_rebuild_editorial'] = '';
$preserved = run_refresh( $edited );
check( $preserved['thumbnail_writes'] === array(), 'An editor-selected image or unmarked post was changed.' );
check( $preserved['posts'] === $edited['posts'], 'Editorial posts changed without an eligible seed image.' );

$same_name = initial_state();
$same_name['posts'][1]['thumbnail'] = 41;
$same_name['posts'][2]['thumbnail'] = 31;
$preserved = run_refresh( $same_name );
check( $preserved['thumbnail_writes'] === array(), 'A different attachment with the old filename must remain editor-controlled.' );

$legacy_dir = sys_get_temp_dir() . '/chidemoon-seed-' . bin2hex( random_bytes( 6 ) );
check( mkdir( $legacy_dir ) && mkdir( $legacy_dir . '/original' ) && mkdir( $legacy_dir . '/edited' ), 'Could not prepare legacy attachments.' );
$seed_file = __DIR__ . '/../tools/seed-images/looks/look-japandi-dining.jpg';
$original_file = $legacy_dir . '/original/look-japandi-dining.jpg';
$edited_file = $legacy_dir . '/edited/look-japandi-dining.jpg';
try {
	check( copy( $seed_file, $original_file ), 'Could not prepare matching legacy attachment.' );
	check( false !== file_put_contents( $edited_file, 'An editor replacement with the same filename.' ), 'Could not prepare edited attachment.' );
	$legacy = initial_state();
	$legacy['posts'][2]['thumbnail'] = 31;
	$legacy['assets'][11]['file'] = $original_file;
	$legacy['assets'][11]['meta']['_wp_attached_file'] = $original_file;
	$legacy['assets'][11]['meta']['_chidemoon_seed_image_file'] = '';
	$legacy['assets'][41]['file'] = $edited_file;
	$legacy['assets'][41]['meta']['_wp_attached_file'] = $edited_file;
	$matching = run_refresh( $legacy );
	check( $matching['thumbnail_writes'] === array( array( 1, 21 ) ), 'Byte-identical legacy seed image should refresh without a marker.' );
	$legacy['posts'][1]['thumbnail'] = 41;
	$different = run_refresh( $legacy );
	check( $different['thumbnail_writes'] === array(), 'Same filename with different image content must stay editor-controlled.' );
} finally {
	if ( is_file( $original_file ) ) { unlink( $original_file ); }
	if ( is_file( $edited_file ) ) { unlink( $edited_file ); }
	rmdir( $legacy_dir . '/original' );
	rmdir( $legacy_dir . '/edited' );
	rmdir( $legacy_dir );
}

echo "Editorial seed-media refresh fixtures passed.\n";
