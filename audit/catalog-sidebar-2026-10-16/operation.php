<?php
if(!current_user_can('manage_woocommerce'))throw new RuntimeException('forbidden');
global $wpdb;$lock='chidemoon_catalog_'.hash('sha256',$wpdb->prefix);if('1'!==(string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock)))throw new RuntimeException('sync is running');
try{
$currency=get_woocommerce_currency();$report=['currencyBefore'=>$currency,'amountsChanged'=>false];
$prices=[];foreach(wc_get_products(['status'=>array_keys(get_post_stati()),'limit'=>-1])as$p)$prices[$p->get_id()]=[$p->get_price('edit'),$p->get_regular_price('edit'),$p->get_sale_price('edit')];
add_option('_chidemoon_before_currency_label_20261016',['position'=>get_option('woocommerce_currency_pos'),'currency'=>$currency,'prices'=>$prices],'',false);
update_option('woocommerce_currency_pos','right_space');
foreach(['chidemoon-category-cards','chidemoon-product-archive']as$slug){$post=get_page_by_path($slug,OBJECT,'elementor_library');$raw=get_post_meta($post->ID,'_elementor_data',true);add_post_meta($post->ID,'_chidemoon_before_sidebar_20261016',$raw,true);$els=json_decode($raw,true);
if($slug==='chidemoon-category-cards'){$els[0]['elements']=array_values(array_filter($els[0]['elements'],fn($e)=>!(($e['widgetType']??'')==='loop-grid'&&($e['settings']['_skin']??'')==='post_taxonomy')&&!(($e['widgetType']??'')==='heading'&&($e['settings']['title']??'')==='مطالب و ایده‌ها')));}
else{
$section=&$els[0]['elements'][0];$children=$section['elements'];$tools=[];$remaining=[];foreach($children as$e){if(in_array($e['widgetType']??'',['chidemoon-catalog-tools','loop-grid'],true))$tools[]=$e;else$remaining[]=$e;}
if(count($tools)!==2)throw new RuntimeException('unexpected archive structure');
$box=function($id,$classes,$elements,$tag='div'){return ['id'=>$id,'elType'=>'container','settings'=>['content_width'=>'full','flex_direction'=>'column','css_classes'=>$classes,'html_tag'=>$tag,'padding'=>['unit'=>'px','top'=>'0','right'=>'0','bottom'=>'0','left'=>'0','isLinked'=>true]],'elements'=>$elements,'isInner'=>true];};
$aside=$box('chside01','ch-catalog-sidebar', [['id'=>'chside02','elType'=>'widget','settings'=>['title'=>'فیلتر محصولات','header_size'=>'h2','typography_typography'=>'custom','typography_font_size'=>['unit'=>'px','size'=>16]],'elements'=>[],'widgetType'=>'heading'],['id'=>'chside03','elType'=>'widget','settings'=>['mode'=>'sidebar'],'elements'=>[],'widgetType'=>'chidemoon-discovery']],'aside');unset($aside['settings']['padding']);
$remaining[]=$box('chlayout','ch-catalog-layout',[$aside,$box('chresult','ch-catalog-results',$tools)]);$section['elements']=$remaining;unset($section);
}
$doc=\Elementor\Plugin::$instance->documents->get($post->ID,false);if(false===$doc->save(['elements'=>$els]))throw new RuntimeException('document save failed');$report['documents'][]=$post->ID;
}
wc_delete_product_transients();\Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();$report['currencyAfter']=get_woocommerce_currency();update_option('_chidemoon_sidebar_report_20261016',$report,false);echo wp_json_encode($report)."\n";
}finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
