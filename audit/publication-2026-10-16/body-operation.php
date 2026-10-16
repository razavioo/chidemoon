<?php
require_once WP_PLUGIN_DIR.'/chidemoon-core/includes/class-chidemoon-core-elementor-content.php';
$apply=in_array('apply',$args??[],true);$out=['apply'=>$apply,'converted'=>[],'sourceBodies'=>[],'preserved'=>[],'errors'=>[]];
foreach(get_posts(['post_type'=>'product','post_status'=>'publish','numberposts'=>-1]) as $p){$id=$p->ID;$plan=Chidemoon_Core_Elementor_Content::plan($id);
if($plan['status']==='planned'){$result=Chidemoon_Core_Elementor_Content::upgrade($id,$apply);if(in_array($result['status'],['updated','planned']))$out['converted'][]=$id;else $out['errors'][]=$result;continue;}
$raw=get_post_meta($id,'_elementor_data',true);
if($raw||trim($p->post_content)!==''){$out['preserved'][]=$id;continue;}
$source=(string)get_post_meta($id,'_chidemoon_source_description',true);if(!$source){$out['errors'][]=['id'=>$id,'error'=>'no_description'];continue;}
if(!$apply){$out['sourceBodies'][]=$id;continue;}
$backup=['content'=>$p->post_content,'meta'=>[]];foreach(['_elementor_data','_elementor_edit_mode','_elementor_template_type','_elementor_version','_elementor_pro_version'] as $k)$backup['meta'][$k]=['exists'=>metadata_exists('post',$id,$k),'value'=>get_post_meta($id,$k,true)];
if(!metadata_exists('post',$id,'_chidemoon_pre_publication_body_20261016'))add_post_meta($id,'_chidemoon_pre_publication_body_20261016',wp_slash($backup),true);
try{
$elements=[['id'=>'pubbody','elType'=>'container','settings'=>['content_width'=>'full','flex_direction'=>'column'],'elements'=>[['id'=>'pubsource','elType'=>'widget','widgetType'=>'text-editor','settings'=>['editor'=>'[chidemoon_source_description]'],'elements'=>[]]]]];
$doc=\Elementor\Plugin::$instance->documents->get($id,false);if(!$doc)throw new RuntimeException('document_unavailable');update_post_meta($id,'_elementor_edit_mode','builder');if(!$doc->save(['elements'=>$elements]))throw new RuntimeException('document_save_failed');$saved=json_decode((string)get_post_meta($id,'_elementor_data',true),true);if(($saved[0]['elements'][0]['settings']['editor']??'')!=='[chidemoon_source_description]')throw new RuntimeException('document_verification_failed');$out['sourceBodies'][]=$id;WP_CLI::log('body '.$id);
}catch(Throwable $e){foreach($backup['meta'] as $k=>$v){if($v['exists'])update_post_meta($id,$k,wp_slash($v['value']));else delete_post_meta($id,$k);}$out['errors'][]=['id'=>$id,'error'=>$e->getMessage()];}
}
if($apply){\Elementor\Plugin::$instance->files_manager->clear_cache();update_option('_chidemoon_publication_bodies_20261016',$out,false);}
echo 'BODY_REPORT '.wp_json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;