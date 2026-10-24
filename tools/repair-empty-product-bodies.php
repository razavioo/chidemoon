<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('edit_products'))exit;
$ids=[];
foreach(get_posts(['post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids']) as $id){
 $p=wc_get_product($id);$description=(string)$p->get_meta(Chidemoon_Core_Source_Importer::META_DESCRIPTION,true);
 if(!$description||trim($p->get_description())!=='')continue;
 Chidemoon_Core_Reconciliation::prepare_source_description($p,$description);$p->save();
 if(wc_get_product($id)->get_description()!=='[chidemoon_source_description]')WP_CLI::error('Body repair failed '.$id);
 $ids[]=$id;
}
WP_CLI::log(wp_json_encode(['repaired'=>count($ids),'ids'=>$ids]));
