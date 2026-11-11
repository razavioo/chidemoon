<?php
if (!defined('WP_CLI') || !current_user_can('manage_woocommerce')) exit(1);
$plan=json_decode(file_get_contents('/tools/today-import-plan.json'),true,512,JSON_THROW_ON_ERROR);
$config=get_option('chidemoon_catalog_settings');
$apply=in_array('apply',$args??[],true);
if(in_array('defer-media',$args??[],true)) add_filter('pre_http_request',static function($pre,$options,$url){return str_contains($url,'statics.basalam.com/')?new WP_Error('batch_media_deferred'):$pre;},10,3);
$report=[];
foreach($plan['items'] as $item){
 $r=Chidemoon_Core_Source_Contract::validate_record($item['record']);
 $snapshot=['version'=>'v2','organization'=>['id'=>$config['organizationId'],'slug'=>$config['organizationSlug']],'snapshot'=>['id'=>'manual-today-20261105','revision'=>str_repeat('a',64),'generatedAt'=>gmdate('c')],'items'=>[$r],'withdrawals'=>[]];
 $result=Chidemoon_Core_Source_Importer::import_snapshot($snapshot,!$apply,$config['organizationSlug']);
 $report[]=$result;
 WP_CLI::log(wp_json_encode(['title'=>$r['content']['title'],'result'=>$result],JSON_UNESCAPED_UNICODE));
 if($apply && !empty($result['records'][0]['localProductId'])){
  $id=(int)$result['records'][0]['localProductId'];
  update_post_meta($id,'_chidemoon_source_variant_data',$item['variants']);
  update_post_meta($id,'_chidemoon_import_batch','2026-11-05');
 }
}
WP_CLI::log('COMPLETE '.wp_json_encode(['count'=>count($report),'created'=>array_sum(array_column($report,'created')),'updated'=>array_sum(array_column($report,'updated')),'failed'=>array_sum(array_column($report,'failed'))]));
