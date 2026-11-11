<?php
if (!defined('WP_CLI') || !current_user_can('manage_woocommerce')) exit(1);
global $wpdb;
$ids=$wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_date_gmt>='2026-11-04 20:30:00' AND post_date_gmt<'2026-11-05 20:30:00' ORDER BY ID");
$rows=[];
foreach($ids as $id){$p=wc_get_product($id);$rows[]=['id'=>(int)$id,'title'=>$p->get_name(),'status'=>$p->get_status(),'identity'=>json_decode($p->get_meta('_chidemoon_source_identity'),true),'content'=>json_decode($p->get_meta('_chidemoon_source_content'),true),'image'=>$p->get_image_id(),'needs'=>Chidemoon_Core_Enrichment::readiness($id),'categories'=>wp_get_object_terms($id,'product_cat',['fields'=>'slugs'])];}
$terms=[];foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]) as $t)$terms[]=['id'=>$t->term_id,'slug'=>$t->slug,'name'=>$t->name,'parent'=>$t->parent];
echo wp_json_encode(['products'=>$rows,'categories'=>$terms],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
