<?php
/** Add requested general categories to the shared site navigation scope. */
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_woocommerce'))exit;
$slugs=['living-room-furniture','dining-tables-chairs','coffee-side-tables-poufs','chandeliers-ceiling-lights','floor-bedside-lamps','wall-lights','decorative-lighting','mirrors','wall-art','shelves-wall-decor','clocks','vases'];
$before=get_option('chidemoon_category_coverage_ids',[]);
$ids=array_map('intval',(array)$before);
if(!$ids){$ids=get_terms(['taxonomy'=>'product_cat','hide_empty'=>true,'fields'=>'ids']);if(is_wp_error($ids))WP_CLI::error($ids->get_error_message());}
$requested=[];
foreach($slugs as $slug){
 $term=get_term_by('slug',$slug,'product_cat');if(!$term)WP_CLI::error('Missing category '.$slug);
 $requested[]=$term->term_id;$ids=array_merge($ids,[$term->term_id],get_ancestors($term->term_id,'product_cat','taxonomy'));
 WP_CLI::log($term->name.' -> '.get_term_link($term));
}
$ids=array_values(array_unique(array_map('intval',$ids)));
if(!in_array('apply',$args??[],true)){WP_CLI::success('Preview: '.count($ids).' public navigation categories.');return;}
add_option('chidemoon_category_scope_before_general_expansion_20261104',$before,'','no');
update_option('chidemoon_category_coverage_ids',$ids,false);
\Elementor\Plugin::$instance->files_manager->clear_cache();
// The header and category page both embed this shared shortcode.
delete_post_meta_by_key('_elementor_element_cache');wp_cache_flush();
$html=Chidemoon_Core_Discovery::category_tree();
foreach($requested as $id)if(!str_contains($html,'cat-item-'.$id))WP_CLI::error('Not in shared navigation: '.$id);
WP_CLI::success('All 12 categories appear in shared public site navigation.');
