<?php
/** Update homepage copy in native Elementor documents, retaining existing layout. */
require_once '/var/www/html/wp-load.php';
wp_set_current_user(1);
if (!current_user_can('manage_options')) throw new RuntimeException('Administrator required');
$home=(int)get_option('page_on_front');
$changes=[
 'cc3961ae'=>['title'=>'چیدمون؛ ایده برای خونمون','header_size'=>'h1'],
 '854f20c3'=>['editor'=>'<p>کمکت می‌کنیم تا از چیدمان‌ها ایده بگیری، به راحتی مقایسه کنی تا درنهایت محصولی طبق سلیقت بخری</p>'],
 '92528be1'=>['text'=>'ایده برای چیدمان'],
 'b384c405'=>['text'=>'مقایسه حرفه‌ای','link'=>['url'=>home_url('/comparisons/')]],
 'chlooktitle'=>['title'=>'بگرد و ببین و بچین'],
 'chlookmore'=>['text'=>'مشاهده ایده‌های چیدمان'],
 'chlookdesc'=>['editor'=>'<p>روی نقطه‌های تصویر بزن و مشخصات هرمحصول رو ببین</p>'],
 '29797ed8'=>['title'=>'جدیدترین مطالب و راهنماها'],
];
$ids=[$home];
$ids=array_merge($ids,get_posts(['post_type'=>'elementor_library','posts_per_page'=>-1,'fields'=>'ids']));
foreach($ids as $id){
 $raw=get_post_meta($id,'_elementor_data',true);if(!$raw)continue;
 $nodes=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$count=0;
 $walk=function(&$items)use(&$walk,&$count,$changes,$id,$home){foreach($items as &$n){
  if($id===$home && isset($changes[$n['id']])){$n['settings']=array_replace($n['settings'],$changes[$n['id']]);++$count;}
  if(($n['widgetType']??'')==='chidemoon-product-actions' && ($n['settings']['details_text']??'جزئیات محصول')==='جزئیات محصول'){$n['settings']['details_text']='مشاهده محصول';++$count;}
  if(!empty($n['elements']))$walk($n['elements']);
 }};$walk($nodes);
 if(!$count)continue;
 if(!in_array('apply',$_SERVER['argv']??[],true)){echo "Preview $id: $count widgets\n";continue;}
 add_post_meta($id,'_chidemoon_before_home_copy_20261107',wp_slash($raw),true);
 $doc=\Elementor\Plugin::$instance->documents->get($id,false);
 if(!$doc||false===$doc->save(['elements'=>$nodes]))throw new RuntimeException("Save failed: $id");
 echo "Saved $id: $count widgets\n";
}
\Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();