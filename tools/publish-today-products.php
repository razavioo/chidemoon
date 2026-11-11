<?php
if(!defined('WP_CLI')||!current_user_can('publish_products'))exit(1);
$plan=json_decode(file_get_contents('/tools/today-import-plan.json'),true,512,JSON_THROW_ON_ERROR);
$apply=in_array('apply',$args??[],true);$report=['published'=>[],'held'=>[],'categories'=>[],'variants'=>0];
if($apply){
 foreach([['curtains','پرده','carpets'],['curtain-accessories','لوازم و اکسسوری پرده','curtains']] as [$slug,$name,$parent]){
  if(!get_term_by('slug',$slug,'product_cat')){$p=get_term_by('slug',$parent,'product_cat');if(!$p)WP_CLI::error('Missing parent');$r=wp_insert_term($name,'product_cat',['slug'=>$slug,'parent'=>$p->term_id]);if(is_wp_error($r))WP_CLI::error($r->get_error_message());}
 }
}
$maps=[
 'style'=>['neoclassic'=>'/نئو\s*کلاسیک/u','minimal'=>'/مینیمال/u','modern'=>'/مدرن/u','classic'=>'/(?<!نئو)کلاسیک/u','boho'=>'/بوهو/u','rustic'=>'/روستیک/u','scandinavian'=>'/اسکاندیناوی/u','industrial'=>'/صنعتی/u'],
 'room'=>['kids-room'=>'/اتاق کودک/u','bedroom'=>'/اتاق خواب/u','living-room'=>'/پذیرایی|کنار سالنی|کنارسالنی/u','dining-room'=>'/ناهارخوری|غذاخوری/u','bathroom'=>'/سرویس بهداشتی|حمام/u'],
 'material'=>['wood'=>'/چوب/u','metal'=>'/فلز|آهن/u','velvet'=>'/مخمل/u','wicker'=>'/حصیر/u','fabric'=>'/حریر|پارچه|تور/u','mdf'=>'/ام دی اف|MDF|ام‌دی‌اف/ui'],
];
$names=['material'=>['بامبو'=>'/بامبو/u','برنز'=>'/برنز/u','استیل'=>'/استیل/u','کریستال'=>'/کریستال/u','سرامیک'=>'/سرامیک/u'],'color'=>['سفید'=>'/سفید/u','مشکی'=>'/مشکی/u','طلایی'=>'/طلایی|طلايي/u','نقره‌ای'=>'/نقره ای|نقره‌ای/u','سبز'=>'/سبز/u','کرم'=>'/کرم/u','قهوه‌ای'=>'/قهوه ای|قهوه‌ای|گردویی|فندقی/u','سرمه‌ای'=>'/سرمه ای|سرمه‌ای/u','آبی'=>'/آبی/u','صورتی'=>'/صورتی/u','طوسی'=>'/طوسی|خاکستری/u','زرشکی'=>'/زرشکی/u','قرمز'=>'/قرمز/u','بنفش'=>'/بنفش/u']];
foreach($plan['items'] as $item){
 $r=$item['record'];$id=Chidemoon_Core_Source_Importer::resolve($r['sourceKey'],$r['aliases']);if(!$id)WP_CLI::error('Missing source '.$r['sourceKey']);
 $p=wc_get_product($id);$title=$r['content']['title'];$slug=preg_match('/پرده جمع کن/u',$title)?'curtain-accessories':$item['category'];$term=get_term_by('slug',$slug,'product_cat');
 if(!$term){if($apply)WP_CLI::error('Missing category');else{$report['held'][]=['id'=>$id,'reason'=>'New category: '.$slug];continue;}}
 $fields=Chidemoon_Core_Enrichment::read($id);$changes=['category'=>['state'=>'value','value'=>[(int)$term->term_id],'evidence'=>['Reviewed merchant product title: '.$title,$r['content']['sourceUrl']]]];
 $variantText='';$options=[];
 foreach($item['catalogVariants']??[] as $v){if('ACTIVE'!==$v['status'])continue;foreach($v['options']??[] as $key=>$value){if(!is_scalar($value))continue;$value=(string)$value;if(''!==trim($value)){$options[$key][]=$value;if(preg_match('/رنگ/u',$key))$variantText.=' '.$value;}}}
 foreach(Chidemoon_Core_Enrichment::REQUIRED as $field){
  if(isset($fields[$field]['approvedBy']))continue;
  $ids=[];$taxonomy=Chidemoon_Core_Enrichment::TAXONOMIES[$field];$text=$title.('color'===$field?' '.$variantText:'');
  foreach($maps[$field]??[] as $slugValue=>$pattern){if(preg_match($pattern,$text)){$t=get_term_by('slug',$slugValue,$taxonomy);if($t)$ids[]=(int)$t->term_id;}}
  foreach($names[$field]??[] as $name=>$pattern){if(preg_match($pattern,$text)){$t=get_term_by('name',$name,$taxonomy);if($t)$ids[]=(int)$t->term_id;}}
  $changes[$field]=['state'=>$ids?'value':'unknown','evidence'=>[$ids?'Explicit merchant title / source variant options: '.$text:'Not explicitly specified in the merchant title or source variant options',$r['content']['sourceUrl']]];
  if($ids)$changes[$field]['value']=array_values(array_unique($ids));
 }
 if($apply){
  add_post_meta($id,'_chidemoon_before_publication_20261105',['status'=>$p->get_status(),'visibility'=>$p->get_catalog_visibility(),'enrichment'=>$fields,'attributes'=>$p->get_attributes()],true);
  Chidemoon_Core_Enrichment::apply($id,$changes,Chidemoon_Core_Enrichment::token($id),get_current_user_id());
  update_post_meta($id,'_chidemoon_source_variant_data',$item['variants']);update_post_meta($id,'_chidemoon_source_catalog_variants',$item['catalogVariants']??[]);
  update_post_meta($id,'_chidemoon_import_batch','2026-11-05');
  $p=wc_get_product($id);$attributes=$p->get_attributes();
  foreach($options as $key=>$values){$a=new WC_Product_Attribute();$a->set_name('گزینه‌های '.$key.' در فروشگاه');$a->set_options(array_values(array_unique($values)));$a->set_visible(true);$a->set_variation(false);$attributes[sanitize_title($a->get_name())]=$a;}
  $p->set_attributes($attributes);$p->save();
 }
 $needs=Chidemoon_Core_Enrichment::readiness($id);
 if($needs){$report['held'][]=['id'=>$id,'title'=>$title,'needs'=>$needs];continue;}
 if($apply){$p=wc_get_product($id);$p->update_meta_data(Chidemoon_Core_Affiliate::META_REVIEW_STATE,'reviewed');$p->set_catalog_visibility('visible');$p->save();$p->set_status('publish');$p->save();clean_post_cache($id);wc_delete_product_transients($id);$p=wc_get_product($id);if('publish'!==$p->get_status()||!$p->is_visible()||!Chidemoon_Core_Affiliate::is_publicly_eligible($p))WP_CLI::error('Publication failed '.$id);}
 $report['published'][]=['id'=>$id,'title'=>$title,'category'=>$term->name,'url'=>get_permalink($id),'image'=>$p->get_image_id()];$report['categories'][$term->name]=($report['categories'][$term->name]??0)+1;$report['variants']+=count($item['catalogVariants']??[]);
}
if($apply){$coverage=(array)get_option('chidemoon_category_coverage_ids',[]);foreach($plan['items'] as $item){$t=get_term_by('slug',$item['category'],'product_cat');if($t)$coverage=array_merge($coverage,[$t->term_id],get_ancestors($t->term_id,'product_cat','taxonomy'));} $t=get_term_by('slug','curtain-accessories','product_cat');if($t)$coverage[]=$t->term_id;update_option('chidemoon_category_coverage_ids',array_values(array_unique(array_map('intval',$coverage))),false);delete_post_meta_by_key('_elementor_element_cache');wc_delete_product_transients();wp_cache_flush();}
WP_CLI::log(wp_json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
