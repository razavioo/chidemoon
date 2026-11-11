<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$id=(int)get_option('page_on_front');$raw=get_post_meta($id,'_elementor_data',true);$nodes=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$count=0;
$walk=static function(&$items)use(&$walk,&$count){foreach($items as &$n){
 if(str_contains($n['settings']['css_classes']??'','ch-home-comparison-section')){
  $n['settings']['content_width']='boxed';$n['settings']['boxed_width']=['unit'=>'px','size'=>1280,'sizes'=>[]];++$count;
 }
 if(!empty($n['elements']))$walk($n['elements']);
}};$walk($nodes);if($count!==1)WP_CLI::error('Expected one comparison section');
if(!in_array('apply',$args??[],true)){WP_CLI::success('Preview: shared boxed section structure.');return;}
add_post_meta($id,'_chidemoon_before_home_spacing_20261104',wp_slash($raw),true);
$doc=\Elementor\Plugin::$instance->documents->get($id,false);if(!$doc||false===$doc->save(['elements'=>$nodes]))WP_CLI::error('Save failed');
\Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();WP_CLI::success('Homepage section spacing unified.');
