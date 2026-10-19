<?php
/** Add specific categories only when the published product title is explicit.
 * Existing categories and other reviewed fields are preserved.
 * wp --user=1 eval-file /tools/category-coverage-products.php [apply]
 */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_product_terms')) { exit; }
$apply=in_array('apply',$args??array(),true);
$rules=array(
 'gas-heater'=>'/بخاری\s*(?:ژاپنی\s*)?گازی/u',
 'electric-room-heaters'=>'/(?:بخاری|هیتر)\s*برقی|فن[\s‌-]*هیتر/u',
 'food-grinder'=>'/آسیاب/u',
 'tea-maker'=>'/چای[\s‌-]*ساز/u',
 'espresso-maker'=>'/اسپرسو[\s‌-]*ساز/u',
 'coffee-maker'=>'/قهوه[\s‌-]*ساز/u',
);
$report=array();
foreach(get_posts(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1)) as $product) {
 $existing=wp_get_object_terms($product->ID,'product_cat',array('fields'=>'ids'));
 if(is_wp_error($existing)) WP_CLI::error($existing->get_error_message());
 $add=array();
 foreach($rules as $slug=>$pattern) {
  if(!preg_match($pattern,$product->post_title)) continue;
  if(preg_match('/دستی|نمک|فلفل/u',$product->post_title)) continue;
  // Mixed-fuel descriptions stay in the broad heater archive pending review.
  if('electric-room-heaters'===$slug && preg_match('/گازی|نفتی|هیزمی/u',$product->post_title)) continue;
  $term=get_term_by('slug',$slug,'product_cat');
  if(!$term) WP_CLI::error('Missing category: '.$slug);
  if(!in_array((int)$term->term_id,array_map('intval',$existing),true)) $add[]=(int)$term->term_id;
 }
 if(!$add) continue;
 $report[]=array('product'=>$product->ID,'title'=>$product->post_title,'add'=>$add);
 if($apply) {
  add_post_meta($product->ID,'_chidemoon_coverage_terms_before_20261019',$existing,true);
  $before=Chidemoon_Core_Enrichment::read($product->ID);
  add_post_meta($product->ID,'_chidemoon_coverage_enrichment_before_20261019',wp_slash($before),true);
  $categories=array_values(array_unique(array_merge(array_map('intval',$existing),$add)));
  $evidence=$before['category']['evidence']??array();
  $evidence[]='Published product title: '.$product->post_title;
  $evidence=array_slice(array_values(array_unique($evidence)),-20);
  Chidemoon_Core_Enrichment::apply($product->ID,array('category'=>array('state'=>'value','value'=>$categories,'evidence'=>$evidence)),Chidemoon_Core_Enrichment::token($product->ID),get_current_user_id());
  $actual=wp_get_object_terms($product->ID,'product_cat',array('fields'=>'ids'));
  if(is_wp_error($actual) || array_diff($categories,array_map('intval',$actual))) WP_CLI::error('Category update did not persist for '.$product->ID);
 }
}
if($apply) { wc_delete_product_transients(); wp_cache_flush(); }
WP_CLI::log(wp_json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
WP_CLI::success(($apply?'Updated ':'Would update ').count($report).' products with explicit title evidence; no existing assignments removed.');
