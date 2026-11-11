<?php
/** Serve uncropped responsive WebP derivatives; keep source files for zoom and rollback. */
if(!defined('ABSPATH'))exit;
final class Chidemoon_Core_Responsive_Images {
 public static function register():void {
  add_filter('elementor/widget/render_content',[__CLASS__,'widget'],120,2);
  add_action('template_redirect',[__CLASS__,'start'],20);
 }
 public static function widget(string $html,$widget):string {
  $classes=(string)$widget->get_settings('_css_classes');$name=$widget->get_name();$sizes='';
  if(str_contains($classes,'ch-native-card-media'))$sizes='(max-width: 767px) 90vw, (max-width: 1024px) 45vw, 420px';
  if(str_contains($classes,'ch-home-products-row'))$sizes='(max-width: 767px) 58vw, (max-width: 1024px) 30vw, 250px';
  if(str_contains($classes,'ch-home-magazine-row'))$sizes='(max-width: 767px) 82vw, (max-width: 1024px) 45vw, 420px';
  if(str_contains($classes,'ch-hero-media')||str_contains($classes,'ch-home-action-image'))$sizes='(max-width: 767px) calc(100vw - 32px), (max-width: 1280px) 48vw, 640px';
  if(str_contains($classes,'ch-route-media'))$sizes='(max-width: 767px) calc(100vw - 58px), (max-width: 1024px) 30vw, 400px';
  if(str_contains($classes,'ch-article-media'))$sizes='(max-width: 767px) calc(100vw - 32px), 960px';
  if($name==='woocommerce-product-images')$sizes='(max-width: 767px) calc(100vw - 32px), (max-width: 1280px) 50vw, 640px';
  if(!$sizes)return $html;
  $tags=new WP_HTML_Tag_Processor($html);
  while($tags->next_tag('img')){
   $tags->set_attribute('sizes',$sizes);
   if(str_contains($classes,'ch-hero-media')){$tags->set_attribute('loading','eager');$tags->set_attribute('fetchpriority','high');}
  }
  return $tags->get_updated_html();
 }
 public static function index(array $manifest):array {
  if(($manifest['version']??null)===2)return $manifest;
  $index=['version'=>2,'images'=>[],'paths'=>[]];
  foreach($manifest as $path=>$entry){
   if(!is_array($entry)||empty($entry['id']))continue;
   $index['images'][$entry['id']]=$entry;$index['paths'][$path]=$entry['id'];
  }
  return $index;
 }
 public static function rewrite(string $html,array $manifest):string {
  $manifest=self::index($manifest);
  // Imports can include 50px/256px copies of the exact same source photo in galleries.
  // Prefer the known larger copy; never upscale or substitute a different photograph.
  $sources=[];
  foreach($manifest['paths'] as $key=>$id){
   $entry=$manifest['images'][$id];
   $identity=preg_replace('/\.(jpe?g|png)_\d+X\d+X\d+(?:-\d+)?\.webp$/i', '.$1', $key);
   if(!isset($sources[$identity])||$entry['width']>$sources[$identity]['width'])$sources[$identity]=$entry;
  }
  $base=trailingslashit(wp_upload_dir()['baseurl']);$tags=new WP_HTML_Tag_Processor($html);
  while($tags->next_tag('img')){
   $src=html_entity_decode((string)$tags->get_attribute('src'),ENT_QUOTES,'UTF-8');
   if(!str_starts_with($src,$base))continue;
   $key=rawurldecode(substr(strtok($src,'?'),strlen($base)));$entry=$manifest['images'][$manifest['paths'][$key]??0]??null;
   $identity=preg_replace('/\.(jpe?g|png)_\d+X\d+X\d+(?:-\d+)?\.webp$/i', '.$1', $key);
   if($entry&&isset($sources[$identity])&&$sources[$identity]['width']>$entry['width'])$entry=$sources[$identity];
   if(!$entry||empty($entry['variants']))continue;
   $variants=$entry['variants'];$largest=end($variants);$set=[];
   foreach($variants as $v)$set[]=$v['url'].' '.$v['width'].'w';
   $tags->set_attribute('src',$largest['url']);$tags->set_attribute('srcset',implode(', ',$set));
   if(!$tags->get_attribute('sizes'))$tags->set_attribute('sizes','(max-width: 767px) calc(100vw - 32px), 640px');
   $tags->set_attribute('width',(string)$entry['width']);$tags->set_attribute('height',(string)$entry['height']);
   $tags->set_attribute('decoding','async');
  }
  return $tags->get_updated_html();
 }
 public static function start():void {
  if(is_admin()||is_feed()||wp_is_json_request())return;
  $manifest=get_option('chidemoon_responsive_images_v1',[]);if(!$manifest)return;
  ob_start(static function(string $html)use($manifest):string {
   $html=self::rewrite($html,$manifest);$tags=new WP_HTML_Tag_Processor($html);$scene=0;
   while($tags->next_tag('img')){
    $classes=(string)$tags->get_attribute('class');
    if((is_front_page()&&str_contains($classes,'ch-scene-image')&&++$scene<=3)||(is_product()&&str_contains($classes,'wp-post-image'))){
     $tags->set_attribute('loading','eager');$tags->set_attribute('fetchpriority','high');
     $sizes=(string)$tags->get_attribute('sizes');$tags->set_attribute('sizes',preg_replace('/^auto,\s*/','',$sizes));
    }
   }
   return $tags->get_updated_html();
  });
 }
}
