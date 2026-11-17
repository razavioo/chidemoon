<?php
$map=json_decode(file_get_contents('/tools/local-text-map.json'),true);
$fix=function($s)use($map){return strtr(strtr($s,$map),$map);};
$walk=function($v)use(&$walk,$fix){if(is_string($v))return $fix($v);if(is_array($v))foreach($v as &$x)$x=$walk($x);return $v;};
$count=0;foreach(get_posts(['post_type'=>['page','elementor_library','chidemoon_look'],'post_status'=>'any','posts_per_page'=>-1]) as $post){$update=['ID'=>$post->ID];foreach(['post_title','post_content','post_excerpt'] as $field){$v=$fix($post->$field);if($v!==$post->$field)$update[$field]=$v;}if(count($update)>1){wp_update_post(wp_slash($update));$count++;}$raw=get_post_meta($post->ID,'_elementor_data',true);if($raw){$data=json_decode($raw,true);if(is_array($data)){$new=wp_json_encode($walk($data),JSON_UNESCAPED_UNICODE);if(json_decode($new,true)!==$data){update_post_meta($post->ID,'_elementor_data',wp_slash($new));$count++;}}}}
foreach(['room'=>'فضاهای ایده‌های چیدمون','styles'=>'سبک‌های ایده‌های چیدمون'] as $slug=>$title){$p=get_page_by_path('shop-the-look/'.$slug);if($p)wp_update_post(['ID'=>$p->ID,'post_title'=>$title]);}
Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();WP_CLI::success('Repaired documents: '.$count);
