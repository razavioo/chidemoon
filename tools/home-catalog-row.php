<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$id=(int)get_option('page_on_front');$raw=get_post_meta($id,'_elementor_data',true);$nodes=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
$card=Chidemoon_Core_Elementor_Workspace::template('product-card');
if(!$card)WP_CLI::error('Product card template missing');
$found=0;$updated=false;
$walk=static function(array &$items)use(&$walk,&$found,&$updated,$card):void{
 foreach($items as &$n){
  if(str_contains($n['settings']['css_classes']??'','ch-magazine-section')){
   ++$found;
   foreach($n['elements'] as &$child)if(($child['widgetType']??'')==='loop-grid'){$child['settings']['posts_per_page']=3;$child['settings']['pagination_type']='';$child['settings']['_css_classes']='ch-editorial-feed ch-native-feed ch-home-magazine-row';}
   unset($child);
  }
  if(str_contains($n['settings']['css_classes']??'','ch-home-products-section'))$updated=true;
  if(!empty($n['elements']))$walk($n['elements']);
 }
};$walk($nodes);
if($found!==1)WP_CLI::error('Expected exactly one magazine section');
if($updated)WP_CLI::error('Product section already exists');
$widget=static fn($id,$type,$settings)=>['id'=>$id,'elType'=>'widget','widgetType'=>$type,'settings'=>$settings,'elements'=>[]];
$section=['id'=>'chprod25','elType'=>'container','isInner'=>false,'settings'=>['content_width'=>'full','flex_direction'=>'column','css_classes'=>'ch-section ch-home-products-section','padding'=>['unit'=>'px','top'=>'40','right'=>'24','bottom'=>'40','left'=>'24','isLinked'=>false],'padding_mobile'=>['unit'=>'px','top'=>'24','right'=>'16','bottom'=>'24','left'=>'16','isLinked'=>false]],'elements'=>[
 $widget('chprhead','heading',['title'=>'محصولات برای خانهٔ تو','header_size'=>'h2']),
 $widget('chprfeed','loop-grid',['_skin'=>'product','template_id'=>(string)$card->ID,'columns'=>'4','columns_tablet'=>'2','columns_mobile'=>'1','posts_per_page'=>8,'equal_height'=>'yes','pagination_type'=>'','post_query_post_type'=>'product','post_query_orderby'=>'date','post_query_order'=>'DESC','_css_classes'=>'ch-native-feed ch-home-products-row']),
 $widget('chprlink','button',['text'=>'دیدن همهٔ محصولات','link'=>['url'=>wc_get_page_permalink('shop')],'align'=>'right','_css_classes'=>'ch-link-button'])
]];
$insert=static function(array &$items)use(&$insert,$section):bool{foreach($items as $i=>&$n){if(str_contains($n['settings']['css_classes']??'','ch-magazine-section')){array_splice($items,$i+1,0,[$section]);return true;}if(!empty($n['elements'])&&$insert($n['elements']))return true;}return false;};
if(!$insert($nodes))WP_CLI::error('Insertion failed');
if(!in_array('apply',$args??[],true)){WP_CLI::log(wp_json_encode(['id'=>$id,'productTemplate'=>$card->ID,'magazineProducts'=>3,'products'=>8,'nodes'=>$nodes],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));return;}
if(!add_post_meta($id,'_chidemoon_before_home_products_20261025',wp_slash($raw),true))WP_CLI::error('Backup already exists or failed');
$doc=\Elementor\Plugin::$instance->documents->get($id,false);
if(!$doc||false===$doc->save(['elements'=>$nodes]))WP_CLI::error('Native Elementor save failed');
\Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();
WP_CLI::success('One magazine row and horizontal product row saved');
