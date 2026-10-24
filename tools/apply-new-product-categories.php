<?php
/** Fixed, reviewed import batch; preview by default, explicit `apply` writes. */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_product_terms')) exit;
$apply=in_array('apply',$args??[],true);
$plan=json_decode(file_get_contents(__DIR__.'/new-product-categories-plan.json'),true,512,JSON_THROW_ON_ERROR);
if(count($plan['items'])!==135 || count(array_unique(array_column($plan['items'],'id')))!==135) WP_CLI::error('Invalid batch');
$before=[];
foreach($plan['items'] as $item){
 $id=$item['id'];
 if(get_post_status($id)!=='draft' || get_the_title($id)!==$item['title'] || get_post_meta($id,'_chidemoon_source_identity',true)!==$item['identity']) WP_CLI::error('Product changed: '.$id);
 $before[$id]=['status'=>get_post_status($id),'categories'=>wp_get_object_terms($id,'product_cat',['fields'=>'ids']),'tags'=>wp_get_object_terms($id,'product_tag',['fields'=>'ids']),'enrichment'=>Chidemoon_Core_Enrichment::read($id),'token'=>Chidemoon_Core_Enrichment::token($id)];
 if(!empty($before[$id]['enrichment']['category']) && !get_post_meta($id,'_chidemoon_categories_batch_20261022',true)) WP_CLI::error('Preserve editor review: '.$id);
}
if(!$apply){ WP_CLI::log(wp_json_encode(['preview'=>true,'products'=>count($before),'review'=>array_column(array_filter($plan['items'],static fn($i)=>$i['review']),'id'),'before'=>$before],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); return; }
foreach($plan['definitions'] as $def){
 if(get_term_by('slug',$def['slug'],'product_cat')) continue;
 $parent=$def['parent']?get_term_by('slug',$def['parent'],'product_cat'):null;
 if($def['parent']&&!$parent) WP_CLI::error('Missing parent');
 $r=wp_insert_term($def['name'],'product_cat',['slug'=>$def['slug'],'parent'=>$parent?(int)$parent->term_id:0]);
 if(is_wp_error($r)) WP_CLI::error($r->get_error_message());
}
$tag=get_term_by('slug','kalahamoon-import-2026-10-21','product_tag');
if(!$tag){$r=wp_insert_term('واردشده از کالاهامون ۲۰۲۶-۱۰-۲۱','product_tag',['slug'=>'kalahamoon-import-2026-10-21']);if(is_wp_error($r)) WP_CLI::error($r->get_error_message());$tag=get_term($r['term_id'],'product_tag');}
$report=[];
foreach($plan['items'] as $item){
 $id=$item['id'];$categories=[];
 foreach($item['categories'] as $slug){$t=get_term_by('slug',$slug,'product_cat');if(!$t) WP_CLI::error('Missing category '.$slug);$categories[]=(int)$t->term_id;}
 // Keep any non-default editor assignments and all existing tags.
 $categories=array_values(array_unique(array_merge($categories,array_diff(array_map('intval',$before[$id]['categories']),[(int)get_option('default_product_cat')]))));
 $tags=array_values(array_unique(array_merge(array_map('intval',$before[$id]['tags']),[(int)$tag->term_id])));
 add_post_meta($id,'_chidemoon_categories_before_20261022',$before[$id],true);
 Chidemoon_Core_Enrichment::apply($id,['category'=>['state'=>'value','value'=>$categories,'evidence'=>[$item['evidence'],'Reviewed import title: '.$item['title']]],'tags'=>['state'=>'value','value'=>$tags,'evidence'=>['Kalahamoon import batch 2026-10-21']]],$before[$id]['token'],get_current_user_id());
 update_post_meta($id,'_chidemoon_categories_batch_20261022',true);
 $actual=wp_get_object_terms($id,'product_cat',['fields'=>'ids']);$actualTags=wp_get_object_terms($id,'product_tag',['fields'=>'ids']);
 if(is_wp_error($actual)||is_wp_error($actualTags)||array_diff($categories,$actual)||array_diff($actual,$categories)||array_diff($tags,$actualTags)||get_post_status($id)!=='draft') WP_CLI::error('Verification failed '.$id);
 $after=Chidemoon_Core_Enrichment::read($id);
 foreach($before[$id]['enrichment'] as $field=>$value) if(!in_array($field,['category','tags'],true)&&$after[$field]!==$value) WP_CLI::error('Unrelated field changed '.$id);
 $report[]=['id'=>$id,'title'=>$item['title'],'categories'=>wp_get_object_terms($id,'product_cat',['fields'=>'names']),'review'=>$item['review'],'status'=>get_post_status($id)];
}
wc_delete_product_transients();
$tagged=get_posts(['post_type'=>'product','post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids','tax_query'=>[['taxonomy'=>'product_tag','field'=>'term_id','terms'=>[(int)$tag->term_id]]]]);
if(count($tagged)!==135 || array_diff(array_column($plan['items'],'id'),$tagged)) WP_CLI::error('Batch filter mismatch');
WP_CLI::log(wp_json_encode(['verified'=>count($report),'classified'=>131,'needsReview'=>4,'tagId'=>$tag->term_id,'adminUrl'=>admin_url('edit.php?post_type=product&post_status=draft&product_tag=kalahamoon-import-2026-10-21'),'products'=>$report],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
