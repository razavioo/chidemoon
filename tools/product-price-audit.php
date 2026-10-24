<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
$out=['currency'=>get_woocommerce_currency(),'products'=>[]];
foreach(get_posts(['post_type'=>'product','post_status'=>['publish','draft'],'posts_per_page'=>-1,'orderby'=>'ID','order'=>'ASC']) as $post){$p=wc_get_product($post->ID);$c=json_decode((string)$p->get_meta('_chidemoon_source_content',true),true)?:[];$out['products'][]=['id'=>$post->ID,'title'=>$p->get_name(),'status'=>$p->get_status(),'identity'=>json_decode((string)$p->get_meta('_chidemoon_source_identity',true),true),'sourceUrl'=>$c['sourceUrl']??$p->get_meta('_chidemoon_source_url',true),'price'=>$p->get_price(),'regularPrice'=>$p->get_regular_price(),'salePrice'=>$p->get_sale_price(),'observations'=>Chidemoon_Core_Commercial::observations($p),'priceHtml'=>$p->get_price_html()];}
WP_CLI::log(wp_json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
