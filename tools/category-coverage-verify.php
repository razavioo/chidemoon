<?php
if ( ! defined('WP_CLI') || ! WP_CLI ) { exit; }
$ids = get_option('chidemoon_category_coverage_ids',array());
if (count($ids)!==34) { WP_CLI::error('Expected 34 covered categories.'); }
$max = 0;
foreach ($ids as $id) {
 $term=get_term($id,'product_cat');
 if (!$term || is_wp_error($term)) { WP_CLI::error('Missing covered category.'); }
 $depth=1+count(get_ancestors($id,'product_cat','taxonomy')); $max=max($max,$depth);
 if ($depth>3) { WP_CLI::error('Category deeper than three levels.'); }
}
$html=do_shortcode('[chidemoon_category_tree]');
foreach ($ids as $id) { if (false===strpos($html,'cat-item-'.$id.'"') && false===strpos($html,'cat-item-'.$id.' ')) { WP_CLI::error('Covered category missing in navigation: '.$id); } }
global $wpdb;
$reviewed=0;
foreach(get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1)) as $post) {
 $actual=wp_get_object_terms($post->ID,'product_cat',array('fields'=>'ids'));
 $before=get_post_meta($post->ID,'_chidemoon_coverage_terms_before_20261019',true);
 if(is_array($before) && array_diff(array_map('intval',$before),array_map('intval',$actual))) WP_CLI::error('Original category assignment lost: '.$post->ID);
 if(metadata_exists('post',$post->ID,'_chidemoon_coverage_enrichment_before_20261019')) {
  $approved=Chidemoon_Core_Enrichment::read($post->ID)['category']['value']??array();
  $a=array_map('intval',$actual); $b=array_map('intval',$approved); sort($a);sort($b);
  if($a!==$b) WP_CLI::error('Reviewed category mismatch: '.$post->ID);
  if(!Chidemoon_Core_Affiliate::is_publicly_eligible(wc_get_product($post->ID))) WP_CLI::error('Updated product is not publicly eligible: '.$post->ID);
  ++$reviewed;
 }
}
$assignments=$wpdb->get_results("SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id=tt.term_taxonomy_id WHERE tt.taxonomy='product_cat' ORDER BY tr.object_id,tt.term_id",ARRAY_A);
WP_CLI::success(wp_json_encode(array('categories'=>count($ids),'maximum_depth'=>$max,'published_products'=>(int)wp_count_posts('product')->publish,'reviewed_product_updates'=>$reviewed,'category_assignment_hash'=>hash('sha256',wp_json_encode($assignments))),JSON_UNESCAPED_UNICODE));
