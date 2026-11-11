<?php
if(!defined('WP_CLI')||!current_user_can('manage_woocommerce'))exit(1);
$plan=json_decode(file_get_contents('/tools/today-import-plan.json'),true,512,JSON_THROW_ON_ERROR);
$part=(int)($args[0]??0);$total=(int)($args[1]??4);
foreach($plan['items'] as $n=>$item){
 if($n%$total!==$part)continue;
 $r=$item['record'];$id=Chidemoon_Core_Source_Importer::resolve($r['sourceKey'],$r['aliases']);
 if(!$id)WP_CLI::error('Missing imported identity');
 if((int)get_post_meta($id,'_chidemoon_source_revision',true)>=$r['revision']&&empty($item['recoveredImages']))continue;
 $images=$item['recoveredImages']??$r['content']['images']??[];if(!$images)continue;
 $featured=Chidemoon_Core_Importer::ensure_product_image($id,['url'=>$images[0],'alt'=>$r['content']['title']]);
 $issues=Chidemoon_Core_Importer::ensure_gallery($id,array_slice($images,1),$r['content']['title']);
 if(!empty($item['recoveredEvidence'])&&!is_wp_error($featured))update_post_meta($id,'_chidemoon_recovered_image_evidence',$item['recoveredEvidence']);
 WP_CLI::log(wp_json_encode(['id'=>$id,'featured'=>is_wp_error($featured)?$featured->get_error_code():'ok','galleryIssues'=>$issues],JSON_UNESCAPED_UNICODE));
}
WP_CLI::log('MEDIA COMPLETE');
