<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$p=Chidemoon_Core_Elementor_Workspace::template('product-archive');
if(!$p)WP_CLI::error('Archive missing');
$raw=get_post_meta($p->ID,'_elementor_data',true);$nodes=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$found=0;
$walk=static function(array &$items)use(&$walk,&$found):void{foreach($items as &$n){if(($n['widgetType']??'')==='loop-grid'){++$found;$n['settings']['pagination_page_limit']='';$n['settings']['pagination_type']='numbers_and_prev_next';$n['settings']['pagination_numbers_shorten']='yes';$n['settings']['pagination_prev_label']='قبلی';$n['settings']['pagination_next_label']='بعدی';}if(!empty($n['elements']))$walk($n['elements']);}};$walk($nodes);
if($found!==1)WP_CLI::error('Unexpected archive loops');
if(!in_array('apply',$args??[],true)){WP_CLI::log('Validated archive pagination');return;}
if(!add_post_meta($p->ID,'_chidemoon_before_structural_links_20261027',wp_slash($raw),true))WP_CLI::error('Backup failed or exists');
$doc=\Elementor\Plugin::$instance->documents->get($p->ID,false);if(!$doc||false===$doc->save(['elements'=>$nodes]))WP_CLI::error('Save failed');
\Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();WP_CLI::success('Unlimited archive pagination saved');
