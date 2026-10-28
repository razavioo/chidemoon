<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$id=(int)get_option('page_on_front');$raw=get_post_meta($id,'_elementor_data',true);$nodes=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
$banner=null;$products=null;$counts=['banner'=>0,'products'=>0];
$extract=static function(array &$items)use(&$extract,&$banner,&$products,&$counts):void{
 foreach($items as $i=>&$n){$classes=explode(' ',$n['settings']['css_classes']??'');
  if(in_array('ch-home-action',$classes,true)){$banner=$n;++$counts['banner'];unset($items[$i]);}
  elseif(in_array('ch-home-products-section',$classes,true)){$products=$n;++$counts['products'];unset($items[$i]);}
  elseif(!empty($n['elements']))$extract($n['elements']);
 }unset($n);$items=array_values($items);
};$extract($nodes);
if($counts!==['banner'=>1,'products'=>1])WP_CLI::error('Expected one product section and one banner');
$products['settings']['content_width']='boxed';$products['settings']['boxed_width']=['unit'=>'px','size'=>1280,'sizes'=>[]];
$products['settings']['flex_gap']=['unit'=>'px','size'=>20,'column'=>'20','row'=>'20','isLinked'=>true];
$banner['settings']['css_classes'].=' ch-home-action-after-products';
$insert=static function(array &$items)use(&$insert,$products,$banner):bool{foreach($items as $i=>&$n){if(in_array('ch-magazine-section',explode(' ',$n['settings']['css_classes']??''),true)){array_splice($items,$i+1,0,[$products,$banner]);return true;}if(!empty($n['elements'])&&$insert($n['elements']))return true;}return false;};
if(!$insert($nodes))WP_CLI::error('Magazine anchor missing');
if(!in_array('apply',$args??[],true)){WP_CLI::log(wp_json_encode(['id'=>$id,'order'=>'magazine, products, banner','nodes'=>$nodes],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));return;}
if(!add_post_meta($id,'_chidemoon_before_home_spacing_20261025',wp_slash($raw),true))WP_CLI::error('Backup failed or already exists');
$doc=\Elementor\Plugin::$instance->documents->get($id,false);if(!$doc||false===$doc->save(['elements'=>$nodes]))WP_CLI::error('Elementor save failed');
\Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();WP_CLI::success('Products positioned directly above banner');
