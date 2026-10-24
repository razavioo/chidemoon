<?php
if (!defined('WP_CLI') || !WP_CLI) exit;
$terms=get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]);
$out=['categories'=>array_map(static fn($t)=>['id'=>$t->term_id,'name'=>$t->name,'slug'=>$t->slug,'parent'=>$t->parent],$terms),'products'=>[]];
foreach(get_posts(['post_type'=>'product','post_status'=>'any','posts_per_page'=>-1,'date_query'=>[['after'=>'2026-10-21 00:00:00','before'=>'2026-10-22 00:00:00','inclusive'=>false]],'orderby'=>'ID','order'=>'ASC']) as $p){
 $identity=get_post_meta($p->ID,'_chidemoon_source_identity',true);
 if(!$identity) continue;
 $out['products'][]=['id'=>$p->ID,'title'=>$p->post_title,'status'=>$p->post_status,'date'=>$p->post_date_gmt,'identity'=>$identity,'categories'=>wp_get_object_terms($p->ID,'product_cat',['fields'=>'names']),'enrichment'=>Chidemoon_Core_Enrichment::read($p->ID),'content'=>get_post_meta($p->ID,'_chidemoon_source_content',true)];
}
WP_CLI::log(wp_json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
