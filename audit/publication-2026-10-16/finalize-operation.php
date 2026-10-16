<?php
$report=get_option('_chidemoon_publication_report_20261016');if(!is_array($report)||empty($report['published']))throw new RuntimeException('publication report missing');
if(!current_user_can('chidemoon_manage_affiliate'))throw new RuntimeException('forbidden');
$corrections=[];
foreach($report['published'] as $id){
$p=get_post($id);$title=str_replace(['ي','ك',"\u{200c}"],['ی','ک',' '],$p->post_title);
if(preg_match('/فرش|قالیچه/u',$title)){
$category=(preg_match('/دستبافت/u',$title)&&!preg_match('/دستبافت گونه/u',$title))?'فرش دستبافت':(preg_match('/ماشینی|شانه|BCF/u',$title)?'فرش ماشینی':'فرش و زیر انداز');
$t=get_term_by('name',$category,'product_cat');if(!$t)throw new RuntimeException('category missing');
$existing=Chidemoon_Core_Enrichment::read($id);
if(($existing['category']['value']??[])!==[(int)$t->term_id]){Chidemoon_Core_Enrichment::apply($id,['category'=>['state'=>'value','value'=>[(int)$t->term_id],'evidence'=>[(string)get_post_meta($id,'_chidemoon_source_url',true)]]],Chidemoon_Core_Enrichment::token($id),get_current_user_id());$corrections[]=$id;}
}
}
$blocked=[];
foreach($report['blocked'] as $entry){$id=$entry['id'];$p=wc_get_product($id);if(!$p)continue;
if($p->get_status()==='publish'){
if(!metadata_exists('post',$id,'_chidemoon_pre_publication_20261016'))add_post_meta($id,'_chidemoon_pre_publication_20261016',['status'=>$p->get_status(),'review'=>$p->get_meta('_chidemoon_review_state'),'visibility'=>$p->get_catalog_visibility(),'reason'=>$entry['reasons'],'at'=>gmdate('c')],true);
$p->set_status('draft');$p->set_catalog_visibility('hidden');$p->save();
}
$blocked[]=$entry;
}
if(!get_option('_chidemoon_pre_store_launch_20261016'))add_option('_chidemoon_pre_store_launch_20261016',['comingSoon'=>get_option('woocommerce_coming_soon'),'storePagesOnly'=>get_option('woocommerce_store_pages_only'),'at'=>gmdate('c')],'','no');
update_option('woocommerce_coming_soon','no');
$checks=['counts'=>wp_count_posts('product'),'comingSoon'=>get_option('woocommerce_coming_soon'),'published'=>count($report['published']),'blocked'=>$blocked,'errors'=>$report['errors'],'categoryCorrections'=>$corrections,'integrityErrors'=>[]];
foreach($report['published'] as $id){$p=wc_get_product($id);$file=get_attached_file($p->get_image_id());$issues=Chidemoon_Core_Enrichment::readiness($id);if(!$file||!file_exists($file))$issues[]='missing_image_file';if(get_post_status($id)!=='publish'||$p->get_catalog_visibility()!=='visible'||!Chidemoon_Core_Affiliate::is_publicly_eligible($p))$issues[]='publication_state';if($issues)$checks['integrityErrors'][]=['id'=>$id,'issues'=>$issues];}
wc_delete_product_transients();wp_cache_flush();
echo wp_json_encode($checks,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;