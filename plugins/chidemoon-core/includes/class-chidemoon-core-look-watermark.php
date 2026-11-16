<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Bakes the brand into look media, preserving protected, unmodified originals. */
final class Chidemoon_Core_Look_Watermark {
 public static function register(): void {
  add_action( 'add_meta_boxes', static function(): void {
   foreach ( array('reycob_look','chidemoon_look') as $type ) add_meta_box('chidemoon-watermark','واترمارک چیدمون',array(__CLASS__,'editor'),$type,'side');
  });
  add_action( 'save_post', array(__CLASS__,'save_editor'), 90, 2 );
  add_action( 'wp_after_insert_post', static function($id,$post): void {
   if(!in_array($post->post_type,array('reycob_look','chidemoon_look'),true)||wp_is_post_revision($id))return;
   $image=(int)(get_post_meta($id,'_reycob_stl_image_id',true) ?: get_post_thumbnail_id($id));
   if($image&&!get_post_meta($image,'_chidemoon_watermarked_files',true))self::apply($image);
  }, 100, 2 );
  add_action( 'added_post_meta', array( __CLASS__, 'assigned' ), 20, 4 );
  add_action( 'updated_post_meta', array( __CLASS__, 'assigned' ), 20, 4 );
  add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'generated' ), 30, 2 );
  add_filter( 'wp_get_attachment_url', static function( $url, $id ) {
   return get_post_meta( $id, '_chidemoon_watermarked_files', true ) ? add_query_arg( 'chwm', '3', $url ) : $url;
  }, 20, 2 );
  add_filter( 'wp_calculate_image_srcset', static function( $sources, $size, $src, $meta, $id ) {
   if ( get_post_meta( $id, '_chidemoon_watermarked_files', true ) && is_array( $sources ) ) {
    foreach ( $sources as &$source ) { $source['url'] = add_query_arg( 'chwm', '3', $source['url'] ); } unset( $source );
   }
   return $sources;
  }, 20, 5 );
 }
 public static function editor( $post ): void {
  wp_nonce_field('chidemoon_watermark','chidemoon_watermark_nonce');
  $id = (int)(get_post_meta($post->ID,'_reycob_stl_image_id',true) ?: get_post_thumbnail_id($post->ID));
  $settings = (array)get_post_meta($id,'_chidemoon_watermark_settings',true);
  echo '<p>پترن روی خود تصویر ذخیره می‌شود. نسخهٔ اصلی محفوظ می‌ماند.</p>';
  foreach(array('size'=>array('اندازهٔ لوگو (درصد عرض تصویر)',22,5,25),'spacing'=>array('فاصلهٔ پترن (درصد)',100,60,180)) as $key=>$field){
   echo '<p><label>'.esc_html($field[0]).'<input style="width:100%" type="number" name="chwm_'.$key.'" min="'.$field[2].'" max="'.$field[3].'" value="'.esc_attr($settings[$key]??$field[1]).'"></label></p>';
  }
  echo '<p><label><input type="checkbox" name="chwm_apply" value="1"> اعمال یا بازسازی واترمارک هنگام ذخیره</label></p>';
 }
 public static function save_editor( $post_id, $post ): void {
  if(!in_array($post->post_type,array('reycob_look','chidemoon_look'),true)||wp_is_post_revision($post_id)||!current_user_can('edit_post',$post_id)||!isset($_POST['chidemoon_watermark_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['chidemoon_watermark_nonce'])),'chidemoon_watermark')||empty($_POST['chwm_apply'])) return;
  $id=(int)(get_post_meta($post_id,'_reycob_stl_image_id',true) ?: get_post_thumbnail_id($post_id));
  if(!$id)return;
  update_post_meta($id,'_chidemoon_watermark_settings',array('size'=>max(5,min(25,(int)($_POST['chwm_size']??22))),'spacing'=>max(60,min(180,(int)($_POST['chwm_spacing']??100)))));
  $result=self::apply($id);
  if(is_wp_error($result)) wp_die(esc_html($result->get_error_message()));
 }
 public static function assigned( $meta_id, $post_id, $key, $value ): void {
  if ( ! in_array( $key, array( '_reycob_stl_image_id', '_thumbnail_id' ), true ) || ! in_array( get_post_type( $post_id ), array( 'reycob_look', 'chidemoon_look' ), true ) ) { return; }
  $result = self::apply( (int) $value );
  if ( is_wp_error( $result ) ) { error_log( 'Chidemoon watermark: ' . $result->get_error_message() ); }
 }
 public static function generated( $metadata, $id ) {
  if ( get_post_meta( $id, '_chidemoon_watermarked_files', true ) ) {
   // WordPress regenerates thumbnails from the stamped full image. Rebuild
   // from the protected source first, so the logo never gets stamped twice.
   $file = get_attached_file( $id );
   $originals = get_post_meta( $id, '_chidemoon_watermark_originals', true );
   $source = $originals[ $file ] ?? '';
   if ( $source && is_file( $source ) ) {
    foreach ( $metadata['sizes'] ?? array() as $size ) {
     $editor = wp_get_image_editor( $source );
     if ( is_wp_error( $editor ) ) { continue; }
     $resized = $editor->resize( $size['width'], $size['height'], true );
     if ( ! is_wp_error( $resized ) ) { $editor->save( dirname( $file ) . '/' . $size['file'], $size['mime-type'] ?? null ); }
    }
   }
   $result = self::apply( (int) $id, $metadata );
   if ( is_wp_error( $result ) ) { error_log( 'Chidemoon watermark: ' . $result->get_error_message() ); }
  }
  return $metadata;
 }
 public static function apply( int $id, ?array $metadata = null ) {
  if ( ! function_exists( 'imagecreatefromstring' ) ) { return new WP_Error( 'watermark_gd', 'GD is unavailable.' ); }
  $file = get_attached_file( $id );
  if ( ! $file || ! is_file( $file ) ) { return new WP_Error( 'watermark_missing', 'Missing image: ' . $id ); }
  $metadata = $metadata ?? wp_get_attachment_metadata( $id );
  $files = array( $file );
  foreach ( $metadata['sizes'] ?? array() as $size ) { $files[] = dirname( $file ) . '/' . $size['file']; }
  if ( ! empty( $metadata['original_image'] ) ) { $files[] = dirname( $file ) . '/' . $metadata['original_image']; }
  $uploads = wp_get_upload_dir();
  $manifest = Chidemoon_Core_Responsive_Images::index( get_option( 'chidemoon_responsive_images_v1', array() ) );
  foreach ( $manifest['images'][ $id ]['variants'] ?? array() as $variant ) {
   if ( str_starts_with( wp_parse_url( $variant['url'], PHP_URL_PATH ), trailingslashit( wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) ) ) ) {
    $files[] = trailingslashit( $uploads['basedir'] ) . rawurldecode( substr( wp_parse_url( $variant['url'], PHP_URL_PATH ), strlen( trailingslashit( wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) ) ) ) );
   }
  }
  $backup_dir = WP_CONTENT_DIR . '/chidemoon-watermark-originals';
  if ( ! wp_mkdir_p( $backup_dir ) ) { return new WP_Error( 'watermark_backup', 'Cannot create originals directory.' ); }
  file_put_contents( $backup_dir . '/.htaccess', "Require all denied\nDeny from all\n" );
  file_put_contents( $backup_dir . '/index.php', "<?php // Private originals.\n" );
  $marked = get_post_meta( $id, '_chidemoon_watermarked_files', true );
  $marked = is_array( $marked ) ? $marked : array();
  $originals = get_post_meta( $id, '_chidemoon_watermark_originals', true );
  $originals = is_array( $originals ) ? $originals : array();
  $lock = fopen( $backup_dir . '/' . $id . '.lock', 'c' );
  if ( ! $lock || ! flock( $lock, LOCK_EX ) ) { return new WP_Error( 'watermark_lock', 'Cannot lock image.' ); }
  // Reload after locking: two saves of the same look can run concurrently.
  $marked = get_post_meta( $id, '_chidemoon_watermarked_files', true );
  $marked = is_array( $marked ) ? $marked : array();
  $versions = get_post_meta( $id, '_chidemoon_watermark_versions', true );
  $versions = is_array( $versions ) ? $versions : array();
  $settings = (array)get_post_meta($id,'_chidemoon_watermark_settings',true);
  $version = '6-'.md5(wp_json_encode($settings));
  foreach ( array_unique( $files ) as $path ) {
   if ( ! is_file( $path ) || ! str_starts_with( wp_normalize_path( $path ), wp_normalize_path( $uploads['basedir'] ) . '/' ) ) { continue; }
   $hash = hash_file( 'sha256', $path );
   if ( ( $marked[ $path ] ?? '' ) === $hash ) {
    // Every stamped file must retain its own clean counterpart, including
    // thumbnails and responsive variants. Never back up a stamped file as clean.
    if ( empty( $originals[ $path ] ) || ! is_file( $originals[ $path ] ) ) {
     flock( $lock, LOCK_UN ); fclose( $lock );
     return new WP_Error( 'watermark_original_missing', 'Clean image is missing: ' . basename( $path ) );
    }
    if ( ( $versions[ $path ] ?? 1 ) === $version ) { continue; }
    if ( empty( $originals[ $path ] ) || ! is_file( $originals[ $path ] ) || ! copy( $originals[ $path ], $path ) ) { return new WP_Error( 'watermark_restore', 'Cannot restore clean source.' ); }
    $hash = hash_file( 'sha256', $path );
   }
   $backup = $backup_dir . '/' . $id . '-' . $hash . '.' . pathinfo( $path, PATHINFO_EXTENSION );
   if ( ! is_file( $backup ) && ! copy( $path, $backup ) ) { return new WP_Error( 'watermark_backup', 'Cannot preserve original: ' . $id ); }
   $originals[ $path ] = $backup;
   update_post_meta( $id, '_chidemoon_watermark_originals', $originals );
   $result = self::stamp( $path, $settings );
   if ( is_wp_error( $result ) ) { return $result; }
   clearstatcache( true, $path );
   $marked[ $path ] = hash_file( 'sha256', $path );
   update_post_meta( $id, '_chidemoon_watermarked_files', $marked );
   $versions[ $path ] = $version; update_post_meta( $id, '_chidemoon_watermark_versions', $versions );
  }
  flock( $lock, LOCK_UN ); fclose( $lock );
  return count( $marked );
 }
 public static function stamp( string $path, array $settings = array() ) {
  $image = @imagecreatefromstring( file_get_contents( $path ) );
  if ( ! $image ) { return new WP_Error( 'watermark_format', 'Unsupported image: ' . basename( $path ) ); }
  $w = imagesx( $image ); $h = imagesy( $image );
  // Staggered diagonal signatures across the image, at low opacity.
  $width = max( 28, (int) round( $w * max(5,min(25,(int)($settings['size']??22))) / 100 ) );
  $sample = imagecolorsforindex( $image, imagecolorat( $image, (int) ( $w / 2 ), (int) ( $h / 2 ) ) );
  $light_background = ( .2126 * $sample['red'] + .7152 * $sample['green'] + .0722 * $sample['blue'] ) > 145;
  $logo = imagecreatefrompng( CHIDEMOON_CORE_DIR . 'assets/brand/chidemoon-logo-fa-' . ( $light_background ? 'mono' : 'white' ) . '.png' );
  $height = max( 1, (int) round( $width * imagesy( $logo ) / imagesx( $logo ) ) );
  $scaled = imagecreatetruecolor( $width, $height );
  imagealphablending( $scaled, false ); imagesavealpha( $scaled, true );
  imagefill( $scaled, 0, 0, imagecolorallocatealpha( $scaled, 0, 0, 0, 127 ) );
  imagecopyresampled( $scaled, $logo, 0, 0, 0, 0, $width, $height, imagesx( $logo ), imagesy( $logo ) );
  for ( $y = 0; $y < $height; ++$y ) { for ( $x = 0; $x < $width; ++$x ) {
   $c = imagecolorsforindex( $scaled, imagecolorat( $scaled, $x, $y ) );
   $alpha = (int) round( 127 - ( 127 - $c['alpha'] ) * .06 );
   imagesetpixel( $scaled, $x, $y, imagecolorallocatealpha( $scaled, $c['red'], $c['green'], $c['blue'], $alpha ) );
  } }
  $rotated = imagerotate( $scaled, 25, imagecolorallocatealpha( $scaled, 0, 0, 0, 127 ) );
  imagesavealpha( $rotated, true );
  imagealphablending( $image, true ); imagesavealpha( $image, true );
  $spacing = max(60,min(180,(int)($settings['spacing']??100)))/100;
  $step_x = max( 36, (int) round( $w * .58 * $spacing ) );
  $step_y = max( 36, (int) round( max( $h * .46, imagesy( $rotated ) * 1.8 ) * $spacing ) );
  $row = 0;
  for ( $y = -(int)(imagesy($rotated)/2); $y < $h; $y += $step_y, ++$row ) {
   for ( $x = -$step_x + ( $row % 2 ? (int)($step_x/2) : 0 ); $x < $w; $x += $step_x ) {
    imagecopy( $image, $rotated, $x, $y, 0, 0, imagesx( $rotated ), imagesy( $rotated ) );
   }
  }
  imagedestroy( $rotated );
  $mime = wp_get_image_mime( $path ); $tmp = $path . '.watermark-tmp';
  switch ( $mime ) {
   case 'image/jpeg': $ok = imagejpeg( $image, $tmp, 94 ); break;
   case 'image/png': $ok = imagepng( $image, $tmp, 6 ); break;
   case 'image/webp': $ok = imagewebp( $image, $tmp, 94 ); break;
   case 'image/avif': $ok = function_exists( 'imageavif' ) && imageavif( $image, $tmp, 90 ); break;
   default: $ok = false;
  }
  imagedestroy( $image ); imagedestroy( $logo ); imagedestroy( $scaled );
  if ( ! $ok || ! rename( $tmp, $path ) ) { @unlink( $tmp ); return new WP_Error( 'watermark_write', 'Could not write image: ' . basename( $path ) ); }
  return true;
 }
}
