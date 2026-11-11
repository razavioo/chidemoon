<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$apply=in_array('apply',$args??[],true);
$ensure=static function($slug,$name,$parent=0)use($apply){$term=get_term_by('slug',$slug,'product_cat');if(!$apply)return $term?(int)$term->term_id:0;if($term){add_option('chidemoon_hierarchy_before_20261104_'.$term->term_id,['name'=>$term->name,'parent'=>$term->parent],'','no');$r=wp_update_term($term->term_id,'product_cat',['name'=>$name,'parent'=>$parent]);}else{$r=wp_insert_term($name,'product_cat',['slug'=>$slug,'parent'=>$parent]);}if(is_wp_error($r))WP_CLI::error($r->get_error_message());return (int)$r['term_id'];};
$root=$ensure('furniture-decoration','مبلمان و دکوراسیون');
$furniture=$ensure('furniture','مبلمان اصلی',$root);$lighting=$ensure('lighting','نور و روشنایی',$root);$decor=$ensure('decorative-accessories','دکوراتیو',$root);
$groups=[[$furniture,['living-room-furniture','dining-tables-chairs','coffee-side-tables-poufs']],[$lighting,['chandeliers-ceiling-lights','floor-bedside-lamps','wall-lights','decorative-lighting']],[$decor,['mirrors','wall-art','shelves-wall-decor','clocks','vases']]];
foreach($groups as [$parent,$slugs])foreach($slugs as $slug){$t=get_term_by('slug',$slug,'product_cat');if(!$t)WP_CLI::error('Missing '.$slug);$ensure($slug,$t->name,$parent);}
if(!$apply){WP_CLI::success('Preview: requested hierarchy and homepage eyebrow removal.');return;}
$scope=(array)get_option('chidemoon_category_coverage_ids',[]);add_option('chidemoon_scope_before_hierarchy_20261104',$scope,'','no');update_option('chidemoon_category_coverage_ids',array_values(array_unique(array_merge(array_map('intval',$scope),[$root,$furniture,$lighting,$decor]))));
$id=(int)get_option('page_on_front');$raw=get_post_meta($id,'_elementor_data',true);$nodes=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$removed=0;
$walk=static function(&$items)use(&$walk,&$removed){$items=array_values(array_filter($items,static function($n)use(&$removed){$c=$n['settings']['_css_classes']??$n['settings']['css_classes']??'';if(str_contains($c,'eyebrow')){++$removed;return false;}return true;}));foreach($items as &$n)if(!empty($n['elements']))$walk($n['elements']);};$walk($nodes);
add_post_meta($id,'_chidemoon_before_home_eyebrow_removal_20261104',wp_slash($raw),true);
$doc=\Elementor\Plugin::$instance->documents->get($id,false);if(!$doc||false===$doc->save(['elements'=>$nodes]))WP_CLI::error('Homepage save failed');
\Elementor\Plugin::$instance->files_manager->clear_cache();delete_post_meta_by_key('_elementor_element_cache');wp_cache_flush();
WP_CLI::success('Hierarchy saved; removed '.$removed.' homepage eyebrows.');
