<?php
if (!current_user_can('chidemoon_manage_affiliate')) throw new RuntimeException('forbidden');
$apply=in_array('apply',$args??[],true);
$changes=[92=>['parent'=>91],115=>['parent'=>113],165=>['parent'=>113],114=>['parent'=>113,'name'=>'زیرانداز']];
$report=['apply'=>$apply,'terms'=>[]];
foreach($changes as$id=>$change){
 $term=get_term($id,'product_cat');if(!$term||is_wp_error($term))throw new RuntimeException('missing term');
 $report['terms'][]=['id'=>$id,'before'=>['name'=>$term->name,'parent'=>$term->parent],'after'=>$change];
 if($apply){add_option('_chidemoon_hierarchy_before_20261017_'.$id,['name'=>$term->name,'parent'=>$term->parent],'',false);$r=wp_update_term($id,'product_cat',$change);if(is_wp_error($r))throw new RuntimeException($r->get_error_message());}
}
$p=get_page_by_path('chidemoon-category-cards',OBJECT,'elementor_library');
$raw=get_post_meta($p->ID,'_elementor_data',true);$elements=json_decode($raw,true);$replaced=0;
$walk=function(&$els)use(&$walk,&$replaced){foreach($els as&$e){if(($e['widgetType']??'')==='loop-grid'&&($e['settings']['_skin']??'')==='product_taxonomy'){$e['widgetType']='shortcode';$e['settings']=['shortcode'=>'[chidemoon_category_tree]'];++$replaced;}if(!empty($e['elements']))$walk($e['elements']);}};
$walk($elements);if($replaced!==1)throw new RuntimeException('expected exactly one category grid');
if($apply){add_post_meta($p->ID,'_chidemoon_hierarchy_before_20261017',$raw,true);$doc=\Elementor\Plugin::$instance->documents->get($p->ID,false);if(false===$doc->save(['elements'=>$elements]))throw new RuntimeException('save failed');wc_delete_product_transients();\Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();}
echo wp_json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
