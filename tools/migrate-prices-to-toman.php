<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
global $wpdb;
if(get_woocommerce_currency()!=='IRR')WP_CLI::error('Expected IRR before one-time migration');
$lock='chidemoon_source_'.hash('sha256',$wpdb->prefix);
if('1'!==(string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock)))WP_CLI::error('Source import is busy');
$plan=json_decode(file_get_contents('/tools/chidemoon-price-plan.json'),true,512,JSON_THROW_ON_ERROR);$rows=[];
foreach($plan as $r){
 $p=wc_get_product($r['id']);$o=Chidemoon_Core_Commercial::observations($p);
 if(!$p||json_decode((string)$p->get_meta('_chidemoon_source_identity',true),true)!==$r['identity']||($o['price']['currency']??'')!=='IRR'||(float)$p->get_price()!==(float)$o['price']['current'])WP_CLI::error('Source price identity mismatch: '.$r['id']);
 $rows[]=[$p,Chidemoon_Core_Source_Importer::convert_price($p->get_regular_price(),'IRR','IRT'),$p->get_sale_price()===''?'':Chidemoon_Core_Source_Importer::convert_price($p->get_sale_price(),'IRR','IRT'),Chidemoon_Core_Source_Importer::convert_price($p->get_price(),'IRR','IRT')];
}
if(count($rows)!==532)WP_CLI::error('Expected 532 verified rial prices');
$wpdb->query('START TRANSACTION');
try{
 foreach($rows as [$p,$regular,$sale,$current]){$p->set_regular_price($regular);$p->set_sale_price($sale);$p->set_price($current);$p->update_meta_data('_chidemoon_currency','IRT');$p->save();}
 // The two manually entered Digikala amounts were already toman, as confirmed by the owner.
 foreach([443,447] as $id){$p=wc_get_product($id);$p->update_meta_data('_chidemoon_currency','IRT');$p->save();}
 update_option('woocommerce_currency','IRT');
 if($wpdb->last_error)throw new RuntimeException($wpdb->last_error);
 $wpdb->query('COMMIT');
}catch(Throwable $e){$wpdb->query('ROLLBACK');wp_cache_flush();throw $e;}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
wp_cache_flush();$issues=[];
foreach($rows as [$p,$regular,$sale,$current]){$fresh=wc_get_product($p->get_id());if($fresh->get_price()!==$current||!str_contains($fresh->get_price_html(),'تومان'))$issues[]=$p->get_id();wc_delete_product_transients($p->get_id());}
WP_CLI::log(wp_json_encode(['converted'=>count($rows),'currency'=>get_woocommerce_currency(),'examplePrice'=>wc_get_product(6653)->get_price(),'exampleHtml'=>wc_get_product(6653)->get_price_html(),'unchangedManualPrices'=>[443=>wc_get_product(443)->get_price(),447=>wc_get_product(447)->get_price()],'issues'=>$issues],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
if($issues)WP_CLI::error('Verification failed');
