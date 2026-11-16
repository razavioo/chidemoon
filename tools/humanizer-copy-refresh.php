<?php
require '/var/www/html/wp-load.php';
wp_set_current_user(1);
$home=(int)get_option('page_on_front');
$footer=get_page_by_path('chidemoon-site-footer',OBJECT,'elementor_library');
foreach(array_filter([$home,$footer->ID??0]) as $id){
$raw=get_post_meta($id,'_elementor_data',true);$nodes=json_decode($raw,true);$count=0;
$walk=function(&$items,$about=false)use(&$walk,&$count,$id,$home){foreach($items as &$n){
$about=$about||str_contains($n['settings']['css_classes']??'','ch-footer-about');
if($id===$home&&($n['id']??'')==='854f20c3'){$n['settings']['editor']='<p>از چیدمان‌ها ایده بگیر، محصولات رو مقایسه کن و برای خونه‌ات انتخاب کن.</p>';$count++;}
if($id===$home&&($n['id']??'')==='b384c405'){$n['settings']['text']='مقایسه محصولات';$count++;}
if($about&&($n['widgetType']??'')==='text-editor'&&!str_contains($n['settings']['_css_classes']??'','ch-footer-tagline')){$n['settings']['editor']='<p>چیدمون جاییه برای ایده گرفتن از چیدمان‌ها و مقایسهٔ وسایل خونه.</p>';$count++;}
if(!empty($n['elements']))$walk($n['elements'],$about);
}};$walk($nodes);
if($count){add_post_meta($id,'_chidemoon_before_humanizer_20261116',wp_slash($raw),true);$doc=\Elementor\Plugin::$instance->documents->get($id,false);if(!$doc||false===$doc->save(['elements'=>$nodes]))throw new Exception('Save failed');}
echo "$id: $count widgets updated\n";
}
\Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();
