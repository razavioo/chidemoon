<?php
declare(strict_types=1);
define( 'ABSPATH', __DIR__ );
$GLOBALS['variants'] = '';
function wp_get_attachment_image( int $id, ...$args ): string { return $id ? '<img src="scene.jpg" alt="">' : ''; }
function get_post_meta( int $id, string $key, bool $single = false ) { return $GLOBALS['variants']; }
function esc_url( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES ); }
function esc_attr( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES ); }
require __DIR__ . '/../plugins/chidemoon-core/includes/class-chidemoon-core-looks.php';
foreach ( array( '', array(), array( '', null, array( 'width' => 400 ) ) ) as $variants ) {
 $GLOBALS['variants'] = $variants;
 if ( '<img src="scene.jpg" alt="">' !== Chidemoon_Core_Looks::scene_image( 1 ) ) { throw new RuntimeException( 'Missing variants must retain the original scene image.' ); }
}
$GLOBALS['variants'] = array( array( 'url' => 'scene.webp', 'width' => 800 ) );
if ( ! str_contains( Chidemoon_Core_Looks::scene_image( 1 ), 'scene.webp 800w' ) ) { throw new RuntimeException( 'Valid WebP variant was lost.' ); }
if ( '' !== Chidemoon_Core_Looks::scene_image( 0 ) ) { throw new RuntimeException( 'Missing attachment rendered an empty picture.' ); }
echo "Scene image fallback checks passed.\n";
