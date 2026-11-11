<?php
/** Reviewed scene classifications and removal of two unshoppable demo cards. */
require '/var/www/html/wp-load.php';
$report=[];
foreach([591,588] as $id){
 if(get_post_type($id)!=='chidemoon_look')throw new RuntimeException('Unexpected scene');
 add_post_meta($id,'_chidemoon_before_card_removal_20261109',['status'=>get_post_status($id)],true);
 wp_update_post(['ID'=>$id,'post_status'=>'draft']);
 $report['removed'][]=$id;
}
foreach([8623=>'modern',8608=>'modern',8607=>'classic',3979=>'scandinavian',3976=>'boho'] as $id=>$style){
 $before=wp_get_object_terms($id,'chidemoon_style',['fields'=>'slugs']);
 if(is_wp_error($before))throw new RuntimeException($before->get_error_message());
 add_post_meta($id,'_chidemoon_before_card_style_20261109',$before,true);
 // Preserve any prior editorial choice; fill only unclassified scenes.
 if(!$before){$r=wp_set_object_terms($id,[$style],'chidemoon_style');if(is_wp_error($r))throw new RuntimeException($r->get_error_message());}
 $report['styles'][$id]=wp_get_object_terms($id,'chidemoon_style',['fields'=>'names']);
}
wp_cache_flush();
echo wp_json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
