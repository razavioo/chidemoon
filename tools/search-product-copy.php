<?php
require '/var/www/html/wp-load.php';
$changed=[];
foreach(get_posts(['post_type'=>'elementor_library','post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids'])as$id){
 $raw=get_post_meta($id,'_elementor_data',true);if(!is_string($raw)||!$raw)continue;$decoded=json_decode($raw,true);if(!is_array($decoded))continue;$data=wp_json_encode($decoded,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if(!str_contains($data,'محصولات و مطالب'))continue;
 $new=str_replace(['جستجو در محصولات و مطالب','جست‌وجو در محصولات و مطالب'],['محصول مورد علاقه‌ات را پیدا کن','محصول مورد علاقه‌ات را پیدا کن'],$data);
 if($new!==$data){if(!metadata_exists('post',$id,'_ch_search_product_copy_backup'))update_post_meta($id,'_ch_search_product_copy_backup',$data);update_post_meta($id,'_elementor_data',wp_slash($new));$changed[]=$id;}
}
if(class_exists('Elementor\Plugin'))Elementor\Plugin::$instance->files_manager->clear_cache();
echo json_encode(['updated'=>$changed]);
