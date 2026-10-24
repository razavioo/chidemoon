<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
$plan=json_decode(file_get_contents('/tools/chidemoon-price-plan.json'),true,512,JSON_THROW_ON_ERROR);$issues=[];$out=[];
foreach($plan as $r){
 $p=wc_get_product($r['id']);$o=Chidemoon_Core_Commercial::observations($p);
 if(!$p||$p->get_status()!=='publish'||(string)$p->get_price()!==$r['current']||$p->get_sale_price()!==''||$o['price']['current']!==$r['current']||$o['price']['currency']!=='IRR'||strtotime($o['price']['observedAt'])!==strtotime($r['observedAt'])||$o['availability']['status']!==$r['availability']||!str_contains($p->get_price_html(),'ریال'))$issues[]=['id'=>$r['id'],'issue'=>'Price, freshness, unit, stock, or publication mismatch','actualPrice'=>$p?$p->get_price():null,'observations'=>$o];
 $out[]=['id'=>$r['id'],'url'=>get_permalink($r['id']),'price'=>$p->get_price(),'priceHtml'=>$p->get_price_html()];
}
$missing=[];foreach(get_posts(['post_type'=>'product','post_status'=>['publish','draft'],'posts_per_page'=>-1]) as $post){$p=wc_get_product($post->ID);if(''===$p->get_price()||(float)$p->get_price()<=0)$missing[]=['id'=>$p->get_id(),'title'=>$p->get_name(),'status'=>$p->get_status()];}
$result=['verified'=>count($plan),'currency'=>get_woocommerce_currency(),'currencyLabel'=>get_woocommerce_currency_symbol(),'issues'=>$issues,'remainingMissing'=>$missing,'counts'=>wp_count_posts('product'),'products'=>$out];
WP_CLI::log(wp_json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));if($issues)WP_CLI::error('Price verification failed');
