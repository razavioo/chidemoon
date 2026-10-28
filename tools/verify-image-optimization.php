<?php
/** Read-only verification of every responsive derivative and gallery reference. */
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$manifest=Chidemoon_Core_Responsive_Images::index(get_option('chidemoon_responsive_images_v1',[]));$uploads=wp_upload_dir();$entries=[];$errors=[];$bytes=0;$variants=0;
$entries=$manifest['images'];
foreach($entries as $id=>$entry){foreach($entry['variants'] as $v){$file=str_replace($uploads['baseurl'],$uploads['basedir'],$v['url']);$size=is_file($file)?getimagesize($file):false;if(!$size||$size[0]!==$v['width']||$size[1]!==$v['height']||$size['mime']!=='image/webp')$errors[]=['id'=>$id,'url'=>$v['url']];$bytes+=is_file($file)?filesize($file):0;++$variants;}}
$missing=[];$galleryCount=0;
foreach(get_posts(['post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids']) as $id){foreach(array_merge([get_post_thumbnail_id($id)],explode(',',get_post_meta($id,'_product_image_gallery',true))) as $image){$image=(int)$image;if(!$image)continue;++$galleryCount;$file=get_attached_file($image);$relative=$file?str_replace(trailingslashit($uploads['basedir']),'',$file):'';if($file&&preg_match('/\.(jpe?g|png|webp)$/i',$file)&&!isset($manifest['paths'][$relative]))$missing[$image]=['product'=>$id,'image'=>$image,'file'=>basename($file)];}}
$allMissing=[];$allCount=0;
foreach(get_posts(['post_type'=>'attachment','post_status'=>'inherit','post_mime_type'=>'image','posts_per_page'=>-1,'fields'=>'ids']) as $image){$file=get_attached_file($image);if(!$file||!preg_match('/\.(jpe?g|png|webp)$/i',$file))continue;++$allCount;$relative=str_replace(trailingslashit($uploads['basedir']),'',$file);if(!isset($manifest['paths'][$relative]))$allMissing[]=$image;}
WP_CLI::log(wp_json_encode(['optimizedImages'=>count($entries),'variants'=>$variants,'variantBytes'=>$bytes,'invalidVariants'=>$errors,'productImageReferences'=>$galleryCount,'missingProductImages'=>array_values($missing),'rasterAttachments'=>$allCount,'missingMediaImages'=>$allMissing],JSON_UNESCAPED_SLASHES));
