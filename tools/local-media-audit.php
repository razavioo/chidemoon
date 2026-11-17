<?php
$missing=[];$total=0;
foreach(get_posts(['post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>-1,'fields'=>'ids']) as $id){$path=get_attached_file($id);$total++;if(!$path||!is_file($path))$missing[]=['id'=>$id,'file'=>get_post_meta($id,'_wp_attached_file',true)];}
WP_CLI::log(wp_json_encode(['attachments'=>$total,'missing'=>$missing],JSON_UNESCAPED_SLASHES));
