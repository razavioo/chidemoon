<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$page=get_page_by_path('shop-the-look');$raw=get_post_meta($page->ID,'_elementor_data',true);$nodes=json_decode($raw,true);
$walk=static function(&$items)use(&$walk){foreach($items as $i=>&$n){if(($n['widgetType']??'')==='chidemoon-room-filters'){unset($items[$i]);continue;}if(!empty($n['elements']))$walk($n['elements']);}unset($n);$items=array_values($items);};
if(in_array('apply',$args??[],true)){
 add_post_meta($page->ID,'_chidemoon_before_look_sidebar_20261028',wp_slash($raw),true);$walk($nodes);
 $doc=\Elementor\Plugin::$instance->documents->get($page->ID,false);if(!$doc||false===$doc->save(['elements'=>$nodes]))WP_CLI::error('Save failed');
 \Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();
 // This scene explicitly identifies its style in its editorial title.
 $boho=get_term_by('name','بوهو','chidemoon_style');
 if($boho&&get_the_title(3976)==='اتاق خواب بوهمی'&&!wp_get_object_terms(3976,'chidemoon_style',['fields'=>'ids']))wp_set_object_terms(3976,[(int)$boho->term_id],'chidemoon_style');
}
foreach(get_posts(['post_type'=>['chidemoon_look','reycob_look'],'post_status'=>'publish','posts_per_page'=>-1]) as $look)WP_CLI::log(wp_json_encode(['id'=>$look->ID,'title'=>$look->post_title,'source'=>get_post_meta($look->ID,'_chidemoon_reycob_scene',true),'styles'=>wp_get_object_terms($look->ID,'chidemoon_style',['fields'=>'slugs'])],JSON_UNESCAPED_UNICODE));
