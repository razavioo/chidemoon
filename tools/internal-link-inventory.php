<?php
// Read-only inventory of published content and editorial product connections.
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_options')) exit;
$out = ['counts'=>[], 'products'=>[], 'editorial'=>[], 'categories'=>[]];
foreach (['product','post','chidemoon_look','reycob_look','page'] as $type) {
 $ids=get_posts(['post_type'=>$type,'post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids']);
 $out['counts'][$type]=count($ids);
 foreach($ids as $id){
  if($type==='product') $out['products'][]=['id'=>$id,'url'=>get_permalink($id),'context'=>Chidemoon_Core_Content_Relations::related_content($id)];
  elseif(in_array($type,['post','chidemoon_look','reycob_look'],true)) $out['editorial'][]=['id'=>$id,'type'=>$type,'title'=>get_the_title($id),'url'=>get_permalink($id),'products'=>Chidemoon_Core_Content_Relations::references($id)];
 }
}
foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]) as $term) $out['categories'][]=['name'=>$term->name,'count'=>$term->count,'url'=>get_term_link($term)];
WP_CLI::log(wp_json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
