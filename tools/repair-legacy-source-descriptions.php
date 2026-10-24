<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_woocommerce'))exit;
$items=json_decode(file_get_contents('/tools/recovered-source-details.json'),true,512,JSON_THROW_ON_ERROR);$out=[];
foreach($items as $i){
 if(!in_array($i['id'],[455,451],true))continue;
 $identity=json_decode((string)get_post_meta($i['id'],'_chidemoon_source_identity',true),true);
 if($identity['product']!==$i['identity']['product']||!$i['description'])WP_CLI::error('Legacy identity mismatch');
 $p=wc_get_product($i['id']);add_post_meta($i['id'],'_chidemoon_description_before_20261022',['sourceDescription'=>$p->get_meta('_chidemoon_source_description',true),'content'=>$p->get_description()],true);
 $p->update_meta_data('_chidemoon_source_description',wp_kses_post($i['description']));
 Chidemoon_Core_Reconciliation::prepare_source_description($p,$i['description']);$p->save();
 $out[]=['id'=>$i['id'],'descriptionLength'=>strlen($i['description'])];
}
WP_CLI::log(wp_json_encode($out));
