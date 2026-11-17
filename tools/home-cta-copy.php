<?php
if(!defined('WP_CLI')||!current_user_can('manage_options'))exit;
$id=(int)get_option('page_on_front');$data=json_decode(get_post_meta($id,'_elementor_data',true),true);$count=0;
$walk=function(&$nodes,$inside=false)use(&$walk,&$count){foreach($nodes as &$n){$active=$inside||str_contains($n['settings']['css_classes']??'','ch-home-action');if($active&&($n['widgetType']??'')==='button'){$n['settings']['text']='دیدن ایده های چیدمون';$count++;}if(!empty($n['elements']))$walk($n['elements'],$active);}};$walk($data);if($count!==1)WP_CLI::error('Expected one about CTA, found '.$count);update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data,JSON_UNESCAPED_UNICODE)));Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();WP_CLI::success('About CTA updated.');
