<?php
if(!defined('WP_CLI')||!WP_CLI) exit;
$out=['drafts'=>[],'descriptions'=>[]];
foreach(get_posts(['post_type'=>'product','post_status'=>['draft','publish'],'posts_per_page'=>-1]) as $post){
 $id=$post->ID;$p=wc_get_product($id);$c=json_decode((string)get_post_meta($id,'_chidemoon_source_content',true),true)?:[];
 $row=['id'=>$id,'title'=>$post->post_title,'status'=>$post->post_status,'identity'=>get_post_meta($id,'_chidemoon_source_identity',true),'content'=>$post->post_content,'excerpt'=>$post->post_excerpt,'sourceDescription'=>get_post_meta($id,'_chidemoon_source_description',true),'snapshotDescription'=>$c['description']??'','url'=>$c['sourceUrl']??$p->get_product_url(),'images'=>$c['images']??[]];
 if('draft'===$post->post_status){$row['sourceMeta']=[];foreach(get_post_meta($id) as $key=>$values){if(preg_match('/source|affiliate|product_url|thumbnail/i',$key))$row['sourceMeta'][$key]=$values;}$row['needs']=Chidemoon_Core_Enrichment::readiness($id);$row['enrichment']=Chidemoon_Core_Enrichment::read($id);$row['imageId']=$p->get_image_id();$row['gallery']=$p->get_gallery_image_ids();$out['drafts'][]=$row;}
 $row['merchantSourceUrl']=get_post_meta($id,Chidemoon_Core_Affiliate::META_SOURCE_URL,true);
 $out['descriptions'][]=$row;
}
WP_CLI::log(wp_json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
