<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_woocommerce'))exit;
$items=json_decode(file_get_contents('/tools/legacy-draft-recovery-plan.json'),true,512,JSON_THROW_ON_ERROR);
if(count($items)!==10)WP_CLI::error('Unexpected recovery batch');
foreach($items as $i){
 if(get_post_status($i['id'])!=='draft'||get_the_title($i['id'])!==$i['expectedTitle']||hash('sha256',get_post_field('post_content',$i['id']))!==$i['expectedContentHash'])WP_CLI::error('Legacy product changed '.$i['id']);
 $key=Chidemoon_Core_Source_Contract::source_key($i['identity']);$mapped=Chidemoon_Core_Source_Importer::resolve($key);
 if($mapped&&$mapped!==$i['id'])WP_CLI::error('Source already mapped '.$i['id']);
}
$records=[];$now=gmdate('c');
foreach($items as $i){
 Chidemoon_Core_Reconciliation::reconcile_identity(['productId'=>$i['id'],'identity'=>$i['identity'],'sourceUrl'=>$i['sourceUrl'],'expectedModifiedAt'=>get_post_field('post_modified_gmt',$i['id']),'approved'=>true],true);
 $record=['sourceKey'=>Chidemoon_Core_Source_Contract::source_key($i['identity']),'identity'=>$i['identity'],'aliases'=>[],'revision'=>1,'contentRevision'=>1,'content'=>['title'=>$i['title'],'description'=>$i['description'],'sourceUrl'=>$i['sourceUrl'],'affiliateUrl'=>$i['sourceUrl'],'images'=>$i['images'],'specifications'=>[],'seller'=>$i['seller']],'observations'=>['availability'=>['status'=>$i['availability'],'observedAt'=>$now]],'removals'=>[]];
 if($i['price']&&$i['currency']==='IRR')$record['observations']['price']=['current'=>$i['price'],'previous'=>null,'currency'=>'IRR','observedAt'=>$now];
 $records[]=$record;
}
$snapshot=['version'=>'v2','organization'=>['id'=>'81742f20-f6d8-4a3f-aa32-4bdc11aee0cc','slug'=>'chidemoon'],'snapshot'=>['id'=>wp_generate_uuid4(),'revision'=>hash('sha256',wp_json_encode($records)),'generatedAt'=>$now],'items'=>$records,'withdrawals'=>[]];
$result=Chidemoon_Core_Source_Importer::import_snapshot($snapshot,false,'chidemoon');
WP_CLI::log(wp_json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
if(($result['failed']??0)>0)WP_CLI::error('Legacy import had failures');
