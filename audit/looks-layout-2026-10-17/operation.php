<?php
if(!current_user_can('chidemoon_manage_affiliate'))throw new RuntimeException('forbidden');
$apply=in_array('apply',$args??[],true);$page=62;$raw=get_post_meta($page,'_elementor_data',true);$tree=json_decode($raw,true);if(!is_array($tree))throw new RuntimeException('missing tree');
$source=594;$image=get_post_thumbnail_id($source);$points=[];
foreach(Chidemoon_Core_Looks::relationships($source)as$row){if($row['hotspot_approved']&&$row['product_id']&&null!==$row['hotspot_x']&&null!==$row['hotspot_y'])$points[]=['product_id'=>(int)$row['product_id'],'x'=>(float)$row['hotspot_x'],'y'=>(float)$row['hotspot_y']];}
if(count($points)!==2||!$image)throw new RuntimeException('unexpected legacy scene');
$scene=(int)get_post_meta($source,'_chidemoon_reycob_scene',true);
if($apply){
 add_post_meta($page,'_chidemoon_before_looks_layout_20261017',$raw,true);
 if(!$scene){$scene=wp_insert_post(['post_type'=>'reycob_look','post_status'=>'publish','post_title'=>'گوشهٔ مطالعه با چراغ‌های رومیزی']);if(is_wp_error($scene))throw new RuntimeException($scene->get_error_message());update_post_meta($source,'_chidemoon_reycob_scene',$scene);update_post_meta($scene,'_chidemoon_original_look',$source);}
 update_post_meta($scene,'_reycob_stl_image_id',$image);update_post_meta($scene,'_reycob_stl_points',$points);update_post_meta($scene,'_reycob_stl_navigation','both');
 $card=get_page_by_path('chidemoon-product-card',OBJECT,'elementor_library');if(!$card)throw new RuntimeException('missing product card');
 foreach([$scene,3976]as$id){add_post_meta($id,'_chidemoon_before_gallery_card_20261017',get_post_meta($id,'_reycob_stl_loop_template',true),true);update_post_meta($id,'_reycob_stl_loop_template',$card->ID);}
 foreach([$scene=>'reading-corner',3976=>'bedroom']as$id=>$slug){$term=get_term_by('slug',$slug,'chidemoon_room');if(!$term)throw new RuntimeException('missing room '.$slug);$r=wp_set_object_terms($id,[(int)$term->term_id],'chidemoon_room');if(is_wp_error($r))throw new RuntimeException($r->get_error_message());}
}
// Keep the existing editable heading and top filter, replace the two competing feeds.
$root=$tree[0];$intro=$root['elements'][0];$intro['settings']['css_classes']='ch-section ch-looks-intro';
$intro['elements']=array_values(array_filter($intro['elements'],fn($e)=>in_array($e['widgetType']??'',['heading','text-editor','chidemoon-room-filters'],true)));
foreach($intro['elements']as&$e){if(($e['widgetType']??'')==='chidemoon-room-filters')$e['settings']['nav_id']='ch-room-filters';}unset($e);
$gallery=['id'=>'7d50af21','elType'=>'container','isInner'=>false,'settings'=>['content_width'=>'boxed','boxed_width'=>['unit'=>'px','size'=>1280],'css_classes'=>'ch-section ch-looks-gallery-section','flex_direction'=>'column','padding'=>['unit'=>'px','top'=>'0','right'=>'24','bottom'=>'40','left'=>'24','isLinked'=>false],'padding_mobile'=>['unit'=>'px','top'=>'0','right'=>'16','bottom'=>'24','left'=>'16','isLinked'=>false]],'elements'=>[['id'=>'9b40fa21','elType'=>'widget','widgetType'=>'shortcode','settings'=>['shortcode'=>'[chidemoon_look_gallery]'],'elements'=>[]]]];
$root['elements']=[$intro,$gallery];$elements=[$root];
if($apply){$doc=\Elementor\Plugin::$instance->documents->get($page,false);if(false===$doc->save(['elements'=>$elements]))throw new RuntimeException('save failed');\Elementor\Plugin::$instance->files_manager->clear_cache();wp_cache_flush();}
echo wp_json_encode(['apply'=>$apply,'scene'=>$scene,'points'=>$points,'image'=>$image,'room_filters'=>1],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
