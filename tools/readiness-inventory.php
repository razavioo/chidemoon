<?php
/** Read-only launch inventory. */
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$out=['pages'=>[],'categories'=>[],'products'=>[],'looks'=>[]];
foreach(get_posts(['post_type'=>['page','post','product','chidemoon_look','reycob_look'],'post_status'=>'publish','posts_per_page'=>-1]) as $p){
 $row=['id'=>$p->ID,'type'=>$p->post_type,'title'=>$p->post_title,'url'=>get_permalink($p)];
 if($p->post_type==='product'){$product=wc_get_product($p->ID);$row['price']=$product->get_price();$row['available']=Chidemoon_Core_Affiliate::is_offer_available($product);$row['destination']=Chidemoon_Core_Affiliate::get_affiliate_url($product);$out['products'][]=$row;}
 elseif(in_array($p->post_type,['chidemoon_look','reycob_look'])){$row['scene']=get_post_meta($p->ID,'_chidemoon_reycob_scene',true);$out['looks'][]=$row;}
 else $out['pages'][]=$row;
}
foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]) as $t)$out['categories'][]=['id'=>$t->term_id,'title'=>$t->name,'url'=>get_term_link($t),'count'=>$t->count];
WP_CLI::log(wp_json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
