<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_woocommerce'))exit;
$categories=[
 ['living-room-furniture','مبلمان پذیرایی','furniture'],
 ['dining-tables-chairs','میز و صندلی غذاخوری','furniture'],
 ['coffee-side-tables-poufs','میز جلو‌مبلی، عسلی و پاف','furniture'],
 ['chandeliers-ceiling-lights','لوستر و چراغ سقفی',''],
 ['floor-bedside-lamps','آباژور و چراغ خواب',''],
 ['wall-lights','چراغ دیواری و دیوارکوب',''],
 ['decorative-lighting','نورپردازی تزئینی',''],
 ['mirrors','آینه',''],['wall-art','تابلو',''],
 ['shelves-wall-decor','شلف و دیوارکوب',''],['clocks','ساعت',''],['vases','گلدان','']
];
$apply=in_array('apply',$args??[],true);
foreach($categories as [$slug,$name,$parent]){
 $existing=get_term_by('slug',$slug,'product_cat')?:get_term_by('name',$name,'product_cat');
 if($existing){WP_CLI::log('Existing: '.$name.' #'.$existing->term_id);continue;}
 WP_CLI::log(($apply?'Creating: ':'Preview: ').$name);
 if(!$apply)continue;
 $parent_term=$parent?get_term_by('slug',$parent,'product_cat'):false;
 $result=wp_insert_term($name,'product_cat',['slug'=>$slug,'parent'=>$parent_term?(int)$parent_term->term_id:0]);
 if(is_wp_error($result))WP_CLI::error($result->get_error_message());
 update_term_meta($result['term_id'],'_chidemoon_added_for_shop_look','2026-11-04');
}
if($apply)wp_cache_flush();
WP_CLI::success($apply?'Shop-the-look product categories ready.':'Preview only.');
