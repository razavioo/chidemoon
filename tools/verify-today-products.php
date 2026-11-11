<?php
if(!defined('WP_CLI')||!current_user_can('manage_woocommerce'))exit(1);
$plan=json_decode(file_get_contents('/tools/today-import-plan.json'),true,512,JSON_THROW_ON_ERROR);
$report=['verified'=>0,'variants'=>0,'errors'=>[],'products'=>[]];$seen=[];
$nav=Chidemoon_Core_Discovery::category_tree();
foreach($plan['items'] as $item){
 $r=$item['record'];$id=Chidemoon_Core_Source_Importer::resolve($r['sourceKey'],$r['aliases']);$p=wc_get_product($id);$errors=[];
 if(isset($seen[$id]))$errors[]='duplicate';$seen[$id]=true;
 if(!$p||'publish'!==$p->get_status()||'visible'!==$p->get_catalog_visibility()||!$p->is_visible()||!Chidemoon_Core_Affiliate::is_publicly_eligible($p))$errors[]='not_public';
 if(Chidemoon_Core_Enrichment::readiness($id))$errors[]='not_ready';
 $slug=preg_match('/پرده جمع کن/u',$r['content']['title'])?'curtain-accessories':$item['category'];$term=get_term_by('slug',$slug,'product_cat');
 if(!$term||!has_term($term->term_id,'product_cat',$id))$errors[]='category';
 if($term&&!str_contains($nav,'cat-item-'.$term->term_id))$errors[]='navigation';
 $images=array_merge([$p->get_image_id()],$p->get_gallery_image_ids());foreach($images as $image){$file=get_attached_file($image);if(!$image||!$file||!is_file($file))$errors[]='missing_image_file';}
 if(count($r['content']['images']??[])>count($images))$errors[]='incomplete_gallery';
 $v=get_post_meta($id,'_chidemoon_source_catalog_variants',true);if(count((array)$v)!==count($item['catalogVariants']??[]))$errors[]='variants';
 if((int)$p->get_meta('_chidemoon_source_revision')<$r['revision'])$errors[]='source_revision';
 if(isset($r['observations']['price'])){$o=$r['observations']['price'];$expected=Chidemoon_Core_Source_Importer::convert_price($o['current'],$o['currency'],get_woocommerce_currency());if((float)$p->get_price()!==(float)$expected)$errors[]='price';}
 if($errors)$report['errors'][]=['id'=>$id,'title'=>$p->get_name(),'errors'=>$errors];else $report['verified']++;
 $report['variants']+=count((array)$v);$report['products'][]=['id'=>$id,'title'=>$p->get_name(),'url'=>get_permalink($id),'image'=>wp_get_attachment_url($p->get_image_id()),'category'=>$term->name??'','price'=>$p->get_price()];
}
$report['totalPublished']=(int)wp_count_posts('product')->publish;
echo wp_json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
if($report['errors'])WP_CLI::error('Verification errors');
