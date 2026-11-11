<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
if(in_array('products',$args??[],true)){foreach([451,455] as $id){$p=wc_get_product($id);WP_CLI::log(wp_json_encode(['id'=>$id,'name'=>$p->get_name(),'description'=>$p->get_description(),'short'=>$p->get_short_description(),'attributes'=>$p->get_attributes(),'meta'=>get_post_meta($id)],JSON_UNESCAPED_UNICODE));}return;}
$walk=static function($items,$depth=0)use(&$walk){foreach($items as $n){WP_CLI::log(wp_json_encode(['depth'=>$depth,'id'=>$n['id'],'class'=>$n['settings']['css_classes']??$n['settings']['_css_classes']??'','type'=>$n['widgetType']??'container','settings'=>$n['settings']],JSON_UNESCAPED_UNICODE));if(!empty($n['elements']))$walk($n['elements'],$depth+1);}};
$walk(json_decode(get_post_meta(get_option('page_on_front'),'_elementor_data',true),true));
foreach(get_posts(['post_type'=>'post','category_name'=>'comparisons','posts_per_page'=>5]) as $p)WP_CLI::log(wp_json_encode(['comparison'=>$p->ID,'title'=>$p->post_title,'content'=>$p->post_content,'elementor'=>get_post_meta($p->ID,'_elementor_data',true)],JSON_UNESCAPED_UNICODE));
