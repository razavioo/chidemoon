<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$id=(int)get_option('page_on_front');$raw=get_post_meta($id,'_elementor_data',true);$nodes=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
$widget=static fn($id,$type,$settings)=>['id'=>$id,'elType'=>'widget','widgetType'=>$type,'settings'=>$settings,'elements'=>[]];
$found=0;
$walk=static function(&$items)use(&$walk,&$found,$widget){foreach($items as &$n){
 $class=$n['settings']['css_classes']??'';
 if(str_contains($class,'ch-hero-visual')){$n['elements']=[$widget('chhmosaic','shortcode',['shortcode'=>'[chidemoon_home_looks mode="hero"]'])];++$found;}
 if(str_contains($class,'ch-discovery')){$n['settings']['css_classes']='ch-section ch-discovery ch-home-shoppable';$n['elements']=[
 $widget('chlookeye','text-editor',['editor'=>'<p>از تصویر تا انتخاب</p>','_css_classes'=>'ch-eyebrow']),
 $widget('chlooktitle','heading',['title'=>'چیدمان را ببین، جزئیاتش را پیدا کن','header_size'=>'h2']),
 $widget('chlookdesc','text-editor',['editor'=>'<p>روی نقطه‌های تصویر بزن و محصول هر گوشه را ببین.</p>']),
 $widget('chlookrow','shortcode',['shortcode'=>'[chidemoon_home_looks]']),
 $widget('chlookmore','button',['text'=>'همهٔ ایده‌های چیدمون','link'=>['url'=>home_url('/shop-the-look/')],'_css_classes'=>'ch-link-button'])];++$found;continue;}
 if(!empty($n['elements']))$walk($n['elements']);
}};$walk($nodes);if($found!==2)WP_CLI::error('Expected hero visual and discovery section.');
if(!in_array('apply',$args??[],true)){WP_CLI::success('Preview: asymmetric hero and horizontal shoppable scenes.');return;}
add_post_meta($id,'_chidemoon_before_home_shoppable_20261103',wp_slash($raw),true);
$doc=\Elementor\Plugin::$instance->documents->get($id,false);if(!$doc||false===$doc->save(['elements'=>$nodes]))WP_CLI::error('Save failed');
\Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();WP_CLI::success('Shoppable homepage saved.');
