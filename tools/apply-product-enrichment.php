<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('chidemoon_manage_affiliate'))exit;
$plan=json_decode(file_get_contents('/tools/product-enrichment-plan.json'),true,512,JSON_THROW_ON_ERROR);
if(count($plan['products'])!==534||count(array_unique(array_column($plan['products'],'id')))!==534)WP_CLI::error('Unexpected product batch');
foreach($plan['products'] as $r){if(get_post_status($r['id'])!==$r['status']||wc_get_product($r['id'])->get_name()!==$r['title']||Chidemoon_Core_Enrichment::token($r['id'])!==$r['token'])WP_CLI::error('Product changed: '.$r['id']);}
$before=[];foreach($plan['products'] as $r){$p=wc_get_product($r['id']);$before[$r['id']]=['fields'=>Chidemoon_Core_Enrichment::read($r['id']),'token'=>$r['token'],'price'=>$p->get_price(),'status'=>$p->get_status(),'categories'=>$p->get_category_ids(),'tags'=>$p->get_tag_ids(),'description'=>$p->get_description()];}
if(file_put_contents('/var/www/html/wp-content/uploads/enrichment-before-20261022.json',wp_json_encode($before,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))===false)WP_CLI::error('Backup failed');
foreach($plan['definitions'] as $field=>$names){$taxonomy=Chidemoon_Core_Enrichment::TAXONOMIES[$field];foreach($names as $name){if(term_exists($name,$taxonomy))continue;$t=wp_insert_term($name,$taxonomy);if(is_wp_error($t))WP_CLI::error($t->get_error_message());}}
$resolved=[];
foreach($plan['products'] as $r){$fields=[];foreach($r['fields'] as $field=>$value){$ids=[];foreach($value['names'] as $name){$t=get_term_by('name',$name,Chidemoon_Core_Enrichment::TAXONOMIES[$field]);if(!$t)WP_CLI::error('Missing vocabulary: '.$field.' '.$name);$ids[]=(int)$t->term_id;}unset($value['names']);$value['value']=array_values(array_unique($ids));$fields[$field]=Chidemoon_Core_Enrichment::validate_field($field,$value)+['confidence'=>$value['confidence']];}$resolved[]=$r+['resolved'=>$fields];}
$done=[];$fieldCounts=[];
foreach($resolved as $r){
 $id=$r['id'];$old=$before[$id];add_post_meta($id,'_chidemoon_enrichment_before_20261022',$old,true);
 $result=Chidemoon_Core_Enrichment::apply($id,$r['resolved'],$r['token'],get_current_user_id());
 foreach($old['fields'] as $f=>$v)if(!isset($r['resolved'][$f])&&$result['fields'][$f]!==$v)WP_CLI::error('Unrelated field changed '.$id);
 $p=wc_get_product($id);if($p->get_price()!==$old['price']||$p->get_status()!==$old['status']||$p->get_category_ids()!==$old['categories']||$p->get_tag_ids()!==$old['tags']||$p->get_description()!==$old['description'])WP_CLI::error('Unrelated product content changed '.$id);
 foreach($r['resolved'] as $f=>$v){$actual=wp_get_object_terms($id,Chidemoon_Core_Enrichment::TAXONOMIES[$f],['fields'=>'ids']);sort($actual);$expected=$v['value'];sort($expected);if($actual!==$expected)WP_CLI::error('Term persistence mismatch '.$id.' '.$f);$fieldCounts[$f]=($fieldCounts[$f]??0)+1;}
 wc_delete_product_transients($id);$done[]=['id'=>$id,'fields'=>array_keys($r['resolved'])];
}
WP_CLI::log(wp_json_encode(['updated'=>count($done),'fields'=>$fieldCounts,'products'=>$done],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
