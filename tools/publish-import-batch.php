<?php
/** Publish only the reviewed, identifiable members of the fixed import batch. */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('publish_products')) exit(1);
$apply=in_array('apply',$args??[],true);
$plan=json_decode(file_get_contents(__DIR__.'/new-product-categories-plan.json'),true,512,JSON_THROW_ON_ERROR);
if(count($plan['items'])!==135) WP_CLI::error('Unexpected batch size');
$report=['apply'=>$apply,'published'=>[],'held'=>[],'candidates'=>[]];
foreach($plan['items'] as $item){
 $id=(int)$item['id'];$p=wc_get_product($id);
 if(!$p || $p->get_meta('_chidemoon_source_identity',true)!==$item['identity']) WP_CLI::error('Identity changed '.$id);
 if($item['review']){$report['held'][]=['id'=>$id,'title'=>get_the_title($id),'reason'=>'Merchant name only; no identifiable product'];continue;}
 $issues=Chidemoon_Core_Enrichment::readiness($id);
 if(array_diff($issues,Chidemoon_Core_Enrichment::REQUIRED)) {
  $report['held'][]=['id'=>$id,'title'=>get_the_title($id),'reason'=>$issues,'imageId'=>$p->get_image_id(),'gallery'=>$p->get_gallery_image_ids(),'thumbnail'=>get_post_meta($id,'_thumbnail_id',true)];continue;
 }
 if(!in_array($p->get_status(),['draft','publish'],true)) WP_CLI::error('Unexpected status '.$id);
 if('1'===$p->get_meta('_chidemoon_source_withdrawn',true)) WP_CLI::error('Withdrawn '.$id);
 $report['candidates'][]=['id'=>$id,'title'=>get_the_title($id),'status'=>$p->get_status(),'visibility'=>$p->get_catalog_visibility(),'missingReviews'=>$issues];
}
if(!$apply){WP_CLI::log(wp_json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));return;}
foreach($report['candidates'] as $candidate){
 $id=$candidate['id'];$p=wc_get_product($id);$fields=Chidemoon_Core_Enrichment::read($id);
 add_post_meta($id,'_chidemoon_before_publish_20261022',['status'=>$p->get_status(),'visibility'=>$p->get_catalog_visibility(),'review'=>$p->get_meta(Chidemoon_Core_Affiliate::META_REVIEW_STATE,true),'enrichment'=>$fields],true);
 $changes=[];
 foreach(Chidemoon_Core_Enrichment::REQUIRED as $field){
  if(isset($fields[$field]['approvedBy'])) continue;
  $terms=wp_get_object_terms($id,Chidemoon_Core_Enrichment::TAXONOMIES[$field],['fields'=>'ids']);
  if(is_wp_error($terms)) WP_CLI::error('Term read failed '.$id);
  // Preserve existing selections; never invent unspecified styles/materials/colors.
  $changes[$field]=['state'=>$terms?'value':'unknown','evidence'=>['Publication review: existing product metadata; unspecified values remain unknown']];
  if($terms) $changes[$field]['value']=array_map('intval',$terms);
 }
 if($changes) Chidemoon_Core_Enrichment::apply($id,$changes,Chidemoon_Core_Enrichment::token($id),get_current_user_id());
 if(Chidemoon_Core_Enrichment::readiness($id)) WP_CLI::error('Readiness failed '.$id);
 $p=wc_get_product($id);
 $p->update_meta_data(Chidemoon_Core_Affiliate::META_REVIEW_STATE,'reviewed');
 $p->set_catalog_visibility('visible');
 $p->save();
 $p->set_status('publish');$p->save();
 clean_post_cache($id);wc_delete_product_transients($id);
 $p=wc_get_product($id);
 if('publish'!==get_post_status($id) || 'visible'!==$p->get_catalog_visibility() || !Chidemoon_Core_Affiliate::is_publicly_eligible($p) || !$p->is_visible()) WP_CLI::error('Publication verification failed '.$id);
 $report['published'][]=['id'=>$id,'title'=>get_the_title($id),'url'=>get_permalink($id),'status'=>get_post_status($id),'visible'=>$p->is_visible(),'offerAvailable'=>Chidemoon_Core_Affiliate::is_offer_available($p)];
}
unset($report['candidates']);
wc_delete_product_transients();
WP_CLI::log(wp_json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
