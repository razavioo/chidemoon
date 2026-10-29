<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$id=(int)get_option('page_on_front');$raw=get_post_meta($id,'_elementor_data',true);$nodes=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
$summary=static function($items)use(&$summary){$out=[];foreach($items as $n){$out[]=['id'=>$n['id'],'class'=>$n['settings']['css_classes']??'','widget'=>$n['widgetType']??'','text'=>$n['settings']['title']??$n['settings']['editor']??'','children'=>!empty($n['elements'])?$summary($n['elements']):[]];}return $out;};
if(!in_array('apply',$args??[],true)){WP_CLI::log(wp_json_encode($summary($nodes),JSON_UNESCAPED_UNICODE));return;}
$found=0;
$swap=static function(&$items)use(&$swap,&$found){$p=null;$m=null;foreach($items as $i=>$n){$classes=explode(' ',$n['settings']['css_classes']??'');if(in_array('ch-home-products-section',$classes,true))$p=$i;if(in_array('ch-magazine-section',$classes,true))$m=$i;}if($p!==null&&$m!==null){$tmp=$items[$p];$items[$p]=$items[$m];$items[$m]=$tmp;++$found;}else foreach($items as &$n)if(!empty($n['elements']))$swap($n['elements']);};
$swap($nodes);if($found!==1)WP_CLI::error('Expected one sibling product/magazine pair');
add_post_meta($id,'_chidemoon_before_section_swap_20261029',wp_slash($raw),true);
$doc=\Elementor\Plugin::$instance->documents->get($id,false);if(!$doc||false===$doc->save(['elements'=>$nodes]))WP_CLI::error('Save failed');
\Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();WP_CLI::success('Product and magazine sections swapped');
