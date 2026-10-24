<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
$plan=json_decode(file_get_contents('/tools/chidemoon-price-plan.json'),true,512,JSON_THROW_ON_ERROR);
$rows=array_values(array_filter($plan,static fn($r)=>null===$r['listingId']));
if(count($rows)!==12)WP_CLI::error('Expected twelve offers outside the connected catalog');
foreach($rows as $r){$p=wc_get_product($r['id']);$identity=json_decode((string)$p->get_meta('_chidemoon_source_identity',true),true);if($identity!==$r['identity']||get_woocommerce_currency()!=='IRR')WP_CLI::error('Identity or currency mismatch');}
$out=[];
foreach($rows as $r){
 $p=wc_get_product($r['id']);$obs=Chidemoon_Core_Commercial::observations($p);
 if(strtotime($obs['price']['observedAt']??'1970-01-01')>strtotime($r['observedAt']))WP_CLI::error('Newer observation already exists');
 $obs['price']=['current'=>$r['current'],'previous'=>null,'currency'=>$r['currency'],'observedAt'=>$r['observedAt']];
 $obs['availability']=['status'=>$r['availability'],'observedAt'=>$r['observedAt']];
 $p->set_regular_price($r['current']);$p->set_sale_price('');$p->set_price($r['current']);$p->set_date_on_sale_from(null);$p->set_date_on_sale_to(null);
 $p->update_meta_data('_chidemoon_currency','IRR');$p->update_meta_data('_chidemoon_source_checked_at',$r['observedAt']);$p->update_meta_data('_chidemoon_price_observed_unix',strtotime($r['observedAt']));
 $p->update_meta_data('_chidemoon_availability_status',$r['availability']);$p->update_meta_data('_chidemoon_availability_observed_unix',strtotime($r['observedAt']));
 $p->update_meta_data('_chidemoon_source_observations',wp_json_encode($obs,JSON_UNESCAPED_SLASHES));$p->save();wc_delete_product_transients($p->get_id());$out[]=['id'=>$r['id'],'price'=>$p->get_price()];
}
WP_CLI::log(wp_json_encode($out,JSON_PRETTY_PRINT));
