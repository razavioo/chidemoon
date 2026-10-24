<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
$plan=json_decode(file_get_contents('/tools/recovered-drafts-publish-plan.json'),true,512,JSON_THROW_ON_ERROR);$issues=[];
foreach($plan as $i){$id=$i['id'];$p=wc_get_product($id);if(!$p||get_post_status($id)!=='publish'||!$p->is_visible()||!Chidemoon_Core_Affiliate::is_publicly_eligible($p)||Chidemoon_Core_Enrichment::readiness($id))$issues[]=['id'=>$id,'issue'=>'publication'];foreach(array_merge([$p->get_image_id()],$p->get_gallery_image_ids()) as $image)if(!$image||!is_file(get_attached_file($image)))$issues[]=['id'=>$id,'issue'=>'image'];if(isset(Chidemoon_Core_Compare::facts($p)[Chidemoon_Core_Enrichment::field_label('tags')]))$issues[]=['id'=>$id,'issue'=>'public tags'];}
$empty=[];foreach(get_posts(['post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1]) as $post){if(trim($post->post_content)===''&&get_post_meta($post->ID,'_chidemoon_source_description',true))$empty[]=$post->ID;}
$drafts=get_posts(['post_type'=>'product','post_status'=>'draft','posts_per_page'=>-1,'fields'=>'ids']);
foreach([6551,5388,4933,4784] as $id){if(Chidemoon_Core_Affiliate::is_offer_available(wc_get_product($id)))$issues[]=['id'=>$id,'issue'=>'source unavailable offer remains enabled'];}
WP_CLI::log(wp_json_encode(['verified'=>count($plan),'issues'=>$issues,'remainingDrafts'=>$drafts,'publishedProductsWithSourceButEmptyBody'=>$empty,'counts'=>wp_count_posts('product'),'internalBatchTagMembership'=>count(get_posts(['post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','tax_query'=>[['taxonomy'=>'product_tag','field'=>'slug','terms'=>['completed-drafts-2026-10-22']]]]))],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
if($issues||$empty)WP_CLI::error('Verification failed');
