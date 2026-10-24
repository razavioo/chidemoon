<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
$id=739;$out=['metaKeys'=>array_keys(get_post_meta($id)),'elementor'=>get_post_meta($id,'_elementor_data',true),'attachments'=>[]];
foreach(get_posts(['post_type'=>'attachment','post_status'=>'inherit','post_parent'=>$id,'posts_per_page'=>-1]) as $p)$out['attachments'][]=['id'=>$p->ID,'title'=>$p->post_title,'url'=>wp_get_attachment_url($p->ID)];
WP_CLI::log(wp_json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
