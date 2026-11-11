<?php
if(!defined('ABSPATH'))exit;
final class Chidemoon_Core_Page_Cache {
 public static function register():void {
  foreach(['save_post','deleted_post','edited_term','created_term','delete_term','woocommerce_update_product','elementor/core/files/clear_cache'] as $hook)add_action($hook,[__CLASS__,'purge'],999,0);
  add_action('updated_option',static function($name){if(in_array($name,['chidemoon_responsive_images_v1','elementor_active_kit','stylesheet','template'],true))self::purge();},999,1);
 }
 public static function purge():void {
  $root=WP_CONTENT_DIR.'/cache/chidemoon-public';if(!is_dir($root))return;
  $temp=$root.'/generation.'.bin2hex(random_bytes(5));file_put_contents($temp,bin2hex(random_bytes(16)),LOCK_EX);rename($temp,$root.'/generation');
  foreach(glob($root.'/*.html')?:[] as $file)if(filemtime($file)<time()-600)unlink($file);
 }
}
