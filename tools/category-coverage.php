<?php
/** Apply the red Canva scope as a maximum three-level WooCommerce tree.
 * wp --user=1 eval-file /tools/category-coverage.php [apply]
 * Keeps existing IDs, slugs and product assignments. Does not delete terms.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_product_terms' ) ) { exit; }
$apply = in_array( 'apply', $args ?? array(), true );
global $wpdb;
$assignment_sql = "SELECT tr.object_id, tt.term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id=tt.term_taxonomy_id WHERE tt.taxonomy='product_cat' ORDER BY tr.object_id,tt.term_id";
$assignment_hash = hash('sha256',wp_json_encode($wpdb->get_results($assignment_sql,ARRAY_A)));
$rows = array(
 array('home-appliance','لوازم برقی خانه',''),
 array('chopper-food-processor','آماده‌سازی غذا','home-appliance'),
 array('food-grinder','آسیاب','chopper-food-processor'),
 array('drink-maker','نوشیدنی‌ساز','home-appliance'),
 array('tea-maker','چای‌ساز','drink-maker'),
 array('espresso-maker','اسپرسوساز','drink-maker'),
 array('coffee-maker','قهوه‌ساز','drink-maker'),
 array('heater','وسایل گرمایشی','home-appliance'),
 array('gas-heater','بخاری گازی','heater'),
 array('electric-room-heaters','بخاری برقی','heater'),
 array('kitchen-appliances','ملزومات آشپزخانه',''),
 array('dinnerware-sets','سرویس پذیرایی و غذاخوری','kitchen-appliances'),
 array('porcelain-tableware','ظروف چینی','dinnerware-sets'),
 array('arcopal-tableware','ظروف آرکوپال','dinnerware-sets'),
 array('crystalware','ظروف کریستال','dinnerware-sets'),
 array('ceramicware','ظروف سرامیکی','dinnerware-sets'),
 array('melamine-tableware','ظروف ملامین','dinnerware-sets'),
 array('serving','ظروف سرو و پذیرایی','kitchen-appliances'),
 array('spoon-fork-knife','سرویس قاشق، کارد و چنگال','serving'),
 array('tray','سینی','serving'),
 array('bowl','کاسه و پیاله','serving'),
 array('tea-sets','فنجان، قندان و سرویس چای‌خوری','serving'),
 array('mug-jug','پارچ، بطری و لیوان','serving'),
 array('condiment-and-appetizer-dishes','ظروف چاشنی و اردوخوری','serving'),
 array('sauce-dish','سس‌خوری، روغن‌ریز و بطری‌جات','serving'),
 array('kitchen-organizers','ابزار و نظم‌دهنده آشپزخانه','kitchen-appliances'),
 array('canisters','بانکه و جای ادویه','kitchen-organizers'),
 array('drawer-cabinet-organizer','نظم‌دهنده داخل کشو و کابینت','kitchen-organizers'),
 array('bedroom-essentials','ملزومات اتاق خواب',''),
 array('bedding','کالای خواب','bedroom-essentials'),
 array('bedspread','روتختی','bedding'),
 array('carpets','فرش و منسوجات',''),
 array('rugs-floor-mats','فرش و زیرانداز','carpets'),
 array('machine-made-carpet','فرش ماشینی','rugs-floor-mats'),
);
$backup_key = '_chidemoon_category_coverage_before_20261019';
if ( $apply && false === get_option( $backup_key, false ) ) {
 $terms = get_terms( array( 'taxonomy'=>'product_cat', 'hide_empty'=>false ) );
 if ( is_wp_error( $terms ) ) { WP_CLI::error( $terms->get_error_message() ); }
 add_option( $backup_key, array( 'terms'=>array_map( static fn($term)=>array('id'=>$term->term_id,'name'=>$term->name,'parent'=>$term->parent), $terms ), 'menu'=>get_option('chidemoon_category_coverage_ids',null) ), '', false );
}
$ids = array();
foreach ( $rows as [$slug,$name,$parent_slug] ) {
 $term = get_term_by( 'slug', $slug, 'product_cat' );
 $parent = $parent_slug ? ( $ids[$parent_slug] ?? 0 ) : 0;
 WP_CLI::log( ($apply?'Apply: ':'Plan: ') . $name . ' / ' . $parent_slug );
 if ( ! $apply ) { $ids[$slug] = $term ? (int)$term->term_id : -count($ids)-1; continue; }
 $result = $term ? wp_update_term( $term->term_id, 'product_cat', array('name'=>$name,'parent'=>$parent) ) : wp_insert_term( $name, 'product_cat', array('slug'=>$slug,'parent'=>$parent) );
 if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
 $ids[$slug] = (int)$result['term_id'];
}
if ( $apply ) {
 // The old heater term contains gas, electric and other fuels. Keep its identity
 // and assignments rather than relabeling those products as electric heaters.
 $legacy=get_term_by('slug','electric-heater','product_cat');
 if ($legacy) { $result=wp_update_term($legacy->term_id,'product_cat',array('name'=>'بخاری (گازی و برقی)','parent'=>$ids['heater'])); if(is_wp_error($result)) WP_CLI::error($result->get_error_message()); }
 foreach ( $ids as $id ) {
  if ( count(get_ancestors($id,'product_cat','taxonomy')) > 2 ) { WP_CLI::error('Tree exceeds three levels.'); }
 }
 update_option( 'chidemoon_category_coverage_ids', array_values($ids), false );
 wc_delete_product_transients();
 clean_term_cache( array_values($ids), 'product_cat' );
 wp_cache_flush();
 if ($assignment_hash !== hash('sha256',wp_json_encode($wpdb->get_results($assignment_sql,ARRAY_A)))) { WP_CLI::error('Product category assignments changed unexpectedly.'); }
 WP_CLI::log('Product assignments unchanged: '.$assignment_hash);
}
WP_CLI::success( count($rows) . ' scoped categories; existing product assignments preserved.' );
