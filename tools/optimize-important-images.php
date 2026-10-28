<?php
/** Non-destructive responsive derivatives for published catalogue/editorial imagery. */
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$ids=[];$galleryIds=[];$add=static function($id)use(&$ids){if((int)$id>0)$ids[(int)$id]=true;};
foreach(get_posts(['post_type'=>['product','post','page','chidemoon_look','reycob_look','elementor_library'],'post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids']) as $id){
 $add(get_post_thumbnail_id($id));$add(get_post_meta($id,'_reycob_stl_image_id',true));
 foreach(explode(',',get_post_meta($id,'_product_image_gallery',true)) as $gallery)if((int)$gallery>0){$galleryIds[(int)$gallery]=true;if($id===853)$add($gallery);}
 $walk=static function($nodes)use(&$walk,$add){foreach((array)$nodes as $n){if(!is_array($n))continue;if(isset($n['settings']['image']['id']))$add($n['settings']['image']['id']);$walk($n['elements']??[]);}};
 $walk(json_decode(get_post_meta($id,'_elementor_data',true),true));
}
foreach(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]) as $term)$add(get_term_meta($term->term_id,'thumbnail_id',true));
$primaryCount=count($ids);$allIds=$ids + $galleryIds;
if(in_array('all-media',$args??[],true))foreach(get_posts(['post_type'=>'attachment','post_status'=>'inherit','post_mime_type'=>'image','posts_per_page'=>-1,'fields'=>'ids']) as $media)$allIds[(int)$media]=true;
$ids=array_keys($allIds);$upload=wp_upload_dir();$manifest=Chidemoon_Core_Responsive_Images::index(get_option('chidemoon_responsive_images_v1',[]));$report=[];
$apply=in_array('apply',$args??[],true);$offset=(int)($args[1]??0);$limit=(int)($args[2]??($apply?$primaryCount+40:10000));
foreach(array_slice($ids,$offset,$limit) as $id){
 $file=get_attached_file($id);if(!$file||!is_file($file)||!preg_match('/\.(jpe?g|png|webp)$/i',$file))continue;
 $size=getimagesize($file);if(!$size)continue;
 $row=['id'=>$id,'file'=>basename($file),'width'=>$size[0],'height'=>$size[1],'originalBytes'=>filesize($file),'variants'=>[]];
 if($apply){
  $source=null;
  foreach(array_unique([min(320,$size[0]),min(640,$size[0]),min(960,$size[0]),min(1440,$size[0])]) as $width){
   $dest=dirname($file).'/ch-opt-'.$id.'-'.$width.'-v1.webp';
   if(!is_file($dest)){
    if(!$source)$source=@imagecreatefromstring(file_get_contents($file));
    if(!$source){
     $original=preg_replace('/\.(jpe?g|png)_\d+X\d+X\d+(?:-\d+)?\.webp$/i', '.$1', $file);
     $originalSize=$original!==$file&&is_file($original)?getimagesize($original):false;
     if($originalSize&&$originalSize[0]>=$size[0]&&abs($originalSize[0]/$originalSize[1]-$size[0]/$size[1])<0.001)$source=@imagecreatefromstring(file_get_contents($original));
    }
    if(!$source&&class_exists('Imagick')){
     try{$decoder=new Imagick($file);$decoder->setIteratorIndex(0);$decoder->setImageFormat('png');$source=@imagecreatefromstring($decoder->getImageBlob());$decoder->clear();}catch(Throwable $error){WP_CLI::warning('Cannot decode attachment '.$id.': '.$error->getMessage());}
    }
    if(!$source)continue;
    $height=max(1,(int)round($size[1]*$width/$size[0]));$target=imagecreatetruecolor($width,$height);imagealphablending($target,false);imagesavealpha($target,true);
    imagecopyresampled($target,$source,0,0,0,0,$width,$height,imagesx($source),imagesy($source));$saved=imagewebp($target,$dest,82);imagedestroy($target);if(!$saved)continue;
   }
   $dimensions=getimagesize($dest);$row['variants'][]=['url'=>str_replace($upload['basedir'],$upload['baseurl'],$dest),'width'=>$dimensions[0],'height'=>$dimensions[1],'bytes'=>filesize($dest)];
  }
  if($source)imagedestroy($source);
  if($row['variants']){$relative=str_replace(trailingslashit($upload['basedir']),'',$file);$entry=['id'=>$id,'width'=>$size[0],'height'=>$size[1],'variants'=>$row['variants']];$manifest['images'][$id]=$entry;$manifest['paths'][$relative]=$id;
   foreach((wp_get_attachment_metadata($id)['sizes']??[]) as $s)$manifest['paths'][dirname($relative).'/'.$s['file']]=$id;
  }
 }
 $report[]=$row;
 if($apply&&count($report)%25===0)update_option('chidemoon_responsive_images_v1',$manifest,false);
}
if($apply)update_option('chidemoon_responsive_images_v1',$manifest,false);
WP_CLI::log(wp_json_encode(['totalImages'=>count($ids),'primaryImages'=>$primaryCount,'offset'=>$offset,'images'=>$report],JSON_UNESCAPED_SLASHES));
