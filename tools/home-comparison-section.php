<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$id=(int)get_option('page_on_front');$raw=get_post_meta($id,'_elementor_data',true);$nodes=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
$widget=static fn($id,$type,$settings)=>['id'=>$id,'elType'=>'widget','widgetType'=>$type,'settings'=>$settings,'elements'=>[]];
$box=static fn($id,$class,$children)=>['id'=>$id,'elType'=>'container','isInner'=>false,'settings'=>['content_width'=>'full','flex_direction'=>'row','css_classes'=>$class],'elements'=>$children];
$found=0;
$walk=static function(&$items)use(&$walk,&$found,$widget,$box){foreach($items as &$n){
 $class=$n['settings']['css_classes']??'';
 if(str_contains($class,'ch-home-shoppable')){
  $children=$n['elements'];$heading=null;$button=null;
  foreach($children as $child){if(($child['id']??'')==='chlooktitle')$heading=$child;if(($child['id']??'')==='chlookmore')$button=$child;}
  if(!$heading||!$button)WP_CLI::error('Shoppable header missing');
  $button['settings']['align']='left';
  $n['elements']=[$children[0],$box('chlookhdr','ch-home-section-header',[$heading,$button])];
  foreach($children as $child)if(!in_array($child['id'],['chlookeye','chlooktitle','chlookmore'],true))$n['elements'][]=$child;
  ++$found;
 }
 if(!empty($n['elements']))$walk($n['elements']);
}
unset($n);
// Place the editorial comparison directly after the shoppable section.
$shoppable=null;foreach($items as $i=>$n)if(str_contains($n['settings']['css_classes']??'','ch-home-shoppable'))$shoppable=$i;
if($shoppable!==null){
 $items=array_values(array_filter($items,static fn($n)=>!str_contains($n['settings']['css_classes']??'','ch-home-comparison-section')));
 $section=$box('chhmcomp','ch-section ch-home-comparison-section',[
  $widget('chcompeye','text-editor',['editor'=>'<p>انتخاب با نگاه چیدمون</p>','_css_classes'=>'ch-eyebrow']),
  $box('chcomphdr','ch-home-section-header',[
   $widget('chcomptitle','heading',['title'=>'دو محصول، دو تجربهٔ متفاوت','header_size'=>'h2']),
   $widget('chcompmore','button',['text'=>'همهٔ مقایسه‌ها','link'=>['url'=>home_url('/comparisons/')],'align'=>'left','_css_classes'=>'ch-link-button'])]),
  $widget('chcomptable','shortcode',['shortcode'=>'[chidemoon_home_comparison]'])]);
 $section['settings']['flex_direction']='column';
 array_splice($items,$shoppable+1,0,[$section]);
}
};$walk($nodes);
if($found!==1)WP_CLI::error('Expected one shoppable section');
if(!in_array('apply',$args??[],true)){WP_CLI::success('Preview: header action and third-section comparison.');return;}
add_post_meta($id,'_chidemoon_before_home_comparison_20261104',wp_slash($raw),true);
$doc=\Elementor\Plugin::$instance->documents->get($id,false);if(!$doc||false===$doc->save(['elements'=>$nodes]))WP_CLI::error('Save failed');
\Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();WP_CLI::success('Homepage comparison saved');
