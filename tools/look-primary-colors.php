<?php
/** Basic scene colors reviewed from the existing cover images; never infer from products. */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_options')) { exit; }
$apply = in_array('apply', $args ?? array(), true);
foreach (Chidemoon_Core_Looks::COLORS as $slug=>$color) {
 if (!term_exists($slug,'chidemoon_look_color') && $apply) {
  $result=wp_insert_term($color[0],'chidemoon_look_color',array('slug'=>$slug));
  if (is_wp_error($result)) { WP_CLI::error($result->get_error_message()); }
 }
}
$reviewed=array(
 'look-basalam-lamps.jpg'=>array('cream','brown','black'),
 'fffc7153-b00f-45a8-ab11-40758bbe7b1e.jpg'=>array('white','gray','brown','blue','green'),
 'look-compact-home-office.jpg'=>array('green','brown','cream'),
 'look-reading-corner.jpg'=>array('cream','brown','green','orange'),
);
foreach (get_posts(array('post_type'=>array('chidemoon_look','reycob_look'),'post_status'=>'publish','posts_per_page'=>-1)) as $look) {
 $image_id=(int)(get_post_meta($look->ID,'_reycob_stl_image_id',true) ?: get_post_thumbnail_id($look->ID));
 $file=basename((string)get_attached_file($image_id));
 if (!isset($reviewed[$file])) { continue; }
 $existing=wp_get_object_terms($look->ID,'chidemoon_look_color',array('fields'=>'ids'));
 if (is_wp_error($existing)) { WP_CLI::error($existing->get_error_message()); }
 // Preserve subsequent editorial classification and keep repeated runs idempotent.
 if ($existing) { continue; }
 WP_CLI::log($look->ID.' '.$file.': '.implode(', ',$reviewed[$file]));
 if (!$apply) { continue; }
 add_post_meta($look->ID,'_chidemoon_before_primary_colors_20261103',array('terms'=>$existing,'image'=>$image_id),true);
 $result=wp_set_object_terms($look->ID,$reviewed[$file],'chidemoon_look_color');
 if (is_wp_error($result)) { WP_CLI::error($result->get_error_message()); }
 update_post_meta($look->ID,'_chidemoon_primary_colors_evidence',array('image'=>$image_id,'file'=>$file,'reviewed_at'=>gmdate('c')));
}
if ($apply) { wp_cache_flush(); }
WP_CLI::success($apply?'Primary colors saved.':'Preview only; pass apply to save.');
