<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('publish_products'))exit;
$plan=json_decode(file_get_contents('/tools/recovered-drafts-publish-plan.json'),true,512,JSON_THROW_ON_ERROR);
$apply=in_array('apply',$args??[],true);$out=[];
if(count($plan)!==27)WP_CLI::error('Expected 27 verified drafts');
if($apply&&!get_term_by('slug','bread-bins','product_cat')){
 $parent=get_term_by('slug','kitchen-organizers','product_cat');$r=wp_insert_term('جای نان','product_cat',['slug'=>'bread-bins','parent'=>$parent->term_id]);if(is_wp_error($r))WP_CLI::error($r->get_error_message());
}
foreach($plan as $i){
 $id=$i['id'];$p=wc_get_product($id);
 if(!$p||!(get_post_status($id)==='draft'||get_post_status($id)==='publish'&&metadata_exists('post',$id,'_chidemoon_before_recovered_publication_20261022'))||$p->get_meta('_chidemoon_source_identity',true)!==$i['identity']||get_the_title($id)!==$i['title'])WP_CLI::error('Draft changed '.$id);
 if(array_diff(Chidemoon_Core_Enrichment::readiness($id),array_merge(Chidemoon_Core_Enrichment::REQUIRED,['category'])))WP_CLI::error('Essential data missing '.$id);
 foreach(array_merge([$p->get_image_id()],$p->get_gallery_image_ids()) as $image)if(!$image||!is_file(get_attached_file($image)))WP_CLI::error('Missing local image '.$id);
 $out[]=['id'=>$id,'title'=>$i['title'],'categories'=>$i['categories'],'imageId'=>$p->get_image_id()];
}
if(!$apply){WP_CLI::log(wp_json_encode(['preview'=>true,'products'=>$out],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));return;}
$tag=get_term_by('slug','completed-drafts-2026-10-22','product_tag');if(!$tag){$r=wp_insert_term('پیش‌نویس‌های تکمیل‌شده ۲۰۲۶-۱۰-۲۲','product_tag',['slug'=>'completed-drafts-2026-10-22']);if(is_wp_error($r))WP_CLI::error($r->get_error_message());$tag=get_term($r['term_id'],'product_tag');}
$out=[];
foreach($plan as $i){
 $id=$i['id'];$p=wc_get_product($id);$before=Chidemoon_Core_Enrichment::read($id);
 add_post_meta($id,'_chidemoon_before_recovered_publication_20261022',['status'=>$p->get_status(),'visibility'=>$p->get_catalog_visibility(),'review'=>$p->get_meta(Chidemoon_Core_Affiliate::META_REVIEW_STATE,true),'enrichment'=>$before],true);
 $categories=[];foreach((array)$i['categories'] as $slug){$t=get_term_by('slug',$slug,'product_cat');if(!$t)WP_CLI::error('Missing category '.$slug);$categories[]=(int)$t->term_id;}
 $tags=array_map('intval',wp_get_object_terms($id,'product_tag',['fields'=>'ids']));$tags[]=(int)$tag->term_id;
 $changes=['category'=>['state'=>'value','value'=>$categories,'evidence'=>[$i['sourceUrl'],'Verified source title: '.$i['title']]],'tags'=>['state'=>'value','value'=>array_values(array_unique($tags)),'evidence'=>['Recovered drafts batch 2026-10-22']]];
 foreach(Chidemoon_Core_Enrichment::REQUIRED as $field){if(isset($before[$field]['approvedBy']))continue;$terms=wp_get_object_terms($id,Chidemoon_Core_Enrichment::TAXONOMIES[$field],['fields'=>'ids']);$changes[$field]=['state'=>$terms?'value':'unknown','evidence'=>['Reviewed existing metadata; unspecified classifications remain unknown']];if($terms)$changes[$field]['value']=array_map('intval',$terms);}
 Chidemoon_Core_Enrichment::apply($id,$changes,Chidemoon_Core_Enrichment::token($id),get_current_user_id());
 if(Chidemoon_Core_Enrichment::readiness($id))WP_CLI::error('Readiness failed '.$id);
 $p=wc_get_product($id);$p->update_meta_data(Chidemoon_Core_Affiliate::META_REVIEW_STATE,'reviewed');$p->set_catalog_visibility('visible');$p->save();$p->set_status('publish');$p->save();
 clean_post_cache($id);wc_delete_product_transients($id);$p=wc_get_product($id);
 if(get_post_status($id)!=='publish'||!$p->is_visible()||!Chidemoon_Core_Affiliate::is_publicly_eligible($p))WP_CLI::error('Publication failed '.$id);
 $out[]=['id'=>$id,'title'=>get_the_title($id),'url'=>get_permalink($id),'status'=>$p->get_status(),'visible'=>$p->is_visible(),'descriptionLength'=>strlen($p->get_meta('_chidemoon_source_description',true))];
}
WP_CLI::log(wp_json_encode(['published'=>count($out),'held'=>[739],'products'=>$out],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
