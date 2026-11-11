<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Chidemoon_Core_Product_Recommendations {
 public static function rules(): array {
  return [
   'console-tables'=>['mirrors','wall-art','floor-bedside-lamps'],
   'telephone-tables'=>['floor-bedside-lamps','wall-art','mirrors'],
   'bookcases'=>['desk-lamps','wall-art','drawer-cabinet-organizer'],
   'dining-tables-chairs'=>['porcelain-tableware','spoon-fork-knife','tray'],
   'espresso-maker'=>['tea-sets','mug-jug','tray'], 'coffee-maker'=>['tea-sets','mug-jug','tray'],
   'tea-maker'=>['tea-sets','tray'], 'food-grinder'=>['canisters','tea-sets'],
   'tea-sets'=>['tray','coffee-maker','espresso-maker','tea-maker'], 'mug-jug'=>['tray','tea-sets','coffee-maker'],
   'curtains'=>['curtain-accessories','rugs-floor-mats'], 'curtain-accessories'=>['curtains'],
   'sofas'=>['coffee-side-tables-poufs','sofa-throws','rugs-floor-mats'],
   'coffee-side-tables-poufs'=>['sofas','tray','floor-bedside-lamps'], 'sofa-throws'=>['sofas','coffee-side-tables-poufs'],
   'rugs-floor-mats'=>['sofas','coffee-side-tables-poufs','curtains'],
   'bedspread'=>['blankets','floor-bedside-lamps'], 'blankets'=>['bedspread','floor-bedside-lamps'], 'bedding'=>['blankets','floor-bedside-lamps'],
   'mirrors'=>['wall-lights','coffee-side-tables-poufs'], 'wall-art'=>['floor-bedside-lamps','sofas'],
   'clocks'=>['wall-art','floor-bedside-lamps'], 'desk-lamps'=>['drawer-cabinet-organizer','wall-art'],
   'floor-bedside-lamps'=>['coffee-side-tables-poufs','sofas','bedspread'], 'wall-lights'=>['mirrors','wall-art'],
   'chandeliers-ceiling-lights'=>['wall-lights','curtains','rugs-floor-mats'],
   'canisters'=>['tray','tea-sets','drawer-cabinet-organizer'], 'tray'=>['tea-sets','mug-jug','condiment-and-appetizer-dishes'],
   'spoon-fork-knife'=>['porcelain-tableware','bowl','drawer-cabinet-organizer'],
   'porcelain-tableware'=>['spoon-fork-knife','tray','mug-jug'], 'crystalware'=>['tray','spoon-fork-knife'],
   'bowl'=>['spoon-fork-knife','porcelain-tableware','tray'], 'dinnerware-sets'=>['spoon-fork-knife','mug-jug','tray'],
   'condiment-and-appetizer-dishes'=>['tray','bowl','spoon-fork-knife'], 'bread-bins'=>['canisters','tray'],
   'drawer-cabinet-organizer'=>['spoon-fork-knife','canisters'], 'kitchen-faucets'=>['kitchen-organizers','canisters'],
   'dishwashers'=>['porcelain-tableware','spoon-fork-knife'], 'appliance-covers'=>['kitchen-organizers','canisters'],
   'chopper-food-processor'=>['canisters','bowl','manual-food-preparation'], 'manual-food-preparation'=>['bowl','canisters'],
   'heater'=>['blankets','rugs-floor-mats'], 'electric-heater'=>['blankets','rugs-floor-mats'],
   'haft-sin'=>['tray','mirrors'], 'decorative-accessories'=>['floor-bedside-lamps','coffee-side-tables-poufs'],
   'lighting'=>['wall-art','coffee-side-tables-poufs'], 'furniture'=>['rugs-floor-mats','floor-bedside-lamps'],
   'carpets'=>['curtains','sofas'], 'bedroom-essentials'=>['floor-bedside-lamps','blankets'],
   'serving'=>['tray','tea-sets','spoon-fork-knife'], 'kitchen-organizers'=>['canisters','spoon-fork-knife'],
   'home-appliance'=>['canisters','bowl','tea-sets'], 'kitchen-appliances'=>['tray','canisters','spoon-fork-knife'],
   'furniture-decoration'=>['floor-bedside-lamps','rugs-floor-mats'],
   'review-1f026a6b2ff4'=>['floor-bedside-lamps','sofas'],
   'review-507c6fc8e718'=>['wall-art','floor-bedside-lamps'],
   'review-f95c011bd3fa'=>['mirrors','tray'],
   'review-3813784df498'=>['drawer-cabinet-organizer'],
   'review-f5c45af5074f'=>['manual-food-preparation','bowl','canisters'],
   'review-b9eabe1ceebc'=>['sofas','coffee-side-tables-poufs','curtains'],
  ];
 }
 public static function categories( int $id ): array {
  $terms=wp_get_object_terms($id,'product_cat');if(is_wp_error($terms))return [];
  usort($terms,static fn($a,$b)=>count(get_ancestors($b->term_id,'product_cat'))<=>count(get_ancestors($a->term_id,'product_cat')));
  return $terms;
 }
 public static function select( int $id ): array {
  $terms=self::categories($id);$leaves=[];$ancestors=[];
  foreach($terms as $t)$ancestors=array_merge($ancestors,get_ancestors($t->term_id,'product_cat'));
  foreach($terms as $t)if(!in_array($t->term_id,$ancestors,true))$leaves[]=$t->slug;
  $rules=self::rules();$targets=[];
  foreach($terms as $t){foreach(array_merge([$t->term_id],get_ancestors($t->term_id,'product_cat')) as $tid){$term=get_term($tid,'product_cat');if($term&&isset($rules[$term->slug])){$targets=$rules[$term->slug];break 2;}}}
  $targets=array_values(array_diff($targets,$leaves));$result=['similar'=>[],'complementary'=>[]];
  $origin=wc_get_product($id);$facets=[];foreach(['chidemoon_style','chidemoon_room','pa_color','pa_material'] as $tax)$facets[$tax]=wp_get_object_terms($id,$tax,['fields'=>'ids']);
  $rank=static function($p)use($facets,$origin){$score=0;foreach($facets as $tax=>$ids){if(!is_wp_error($ids)&&$ids){$other=wp_get_object_terms($p->get_id(),$tax,['fields'=>'ids']);if(!is_wp_error($other))$score+=count(array_intersect($ids,$other))*10;}}$price=(float)$p->get_price();$base=(float)$origin->get_price();if($price>0&&$base>0)$score+=max(0,5-abs(log($price/$base)));return $score;};
  foreach(['similar'=>$leaves,'complementary'=>$targets] as $kind=>$cats){
   $pool=[];foreach($cats as $cat){$bucket=[];foreach(wc_get_products(['status'=>'publish','visibility'=>'visible','category'=>[$cat],'exclude'=>[$id],'limit'=>48,'orderby'=>'date','order'=>'DESC']) as $p){
     if(!Chidemoon_Core_Affiliate::is_offer_available($p)||!$p->get_image_id())continue;
     if($kind==='complementary'&&(in_array($p->get_id(),$result['similar'],true)||has_term($leaves,'product_cat',$p->get_id())))continue;
     if($kind==='complementary'&&in_array($cat,['tea-sets','mug-jug'],true)&&array_intersect($leaves,['coffee-maker','espresso-maker'])&&!preg_match('/فنجان|ماگ|لاته|لیوان|کاپ/u',$p->get_name()))continue;
     if($kind==='complementary'&&$cat==='floor-bedside-lamps'&&array_intersect($leaves,['bedspread','blankets','bedding'])&&!preg_match('/رومیزی|رو میزی|چراغ خواب|چراغخواب|کنار تخت/u',$p->get_name()))continue;
     $bucket[$p->get_id()]=$rank($p);
    }arsort($bucket);$pool[]=array_keys($bucket);}
   // Alternate categories so one large category cannot fill the whole row.
   for($i=0;$i<48&&count($result[$kind])<6;$i++)foreach($pool as $bucket){if(isset($bucket[$i])&&!in_array($bucket[$i],$result[$kind],true))$result[$kind][]=$bucket[$i];if(count($result[$kind])===6)break;}
  }
  return $result;
 }
 public static function markup( int $id ): string {
  $sets=self::select($id);$html='<div class="ch-product-recommendations">';
  foreach(['similar'=>'محصولات مشابه','complementary'=>'محصولات مکمل'] as $kind=>$title){
   $html.='<section class="ch-recommendation-section" aria-labelledby="ch-recommendation-'.$kind.'"><h2 id="ch-recommendation-'.$kind.'">'.$title.'</h2>';
   if(!$sets[$kind]){$html.='<p class="ch-recommendation-empty">در حال حاضر پیشنهاد موجودی برای این محصول نداریم.</p></section>';continue;}
   $html.='<ul class="ch-recommendation-row">';foreach($sets[$kind] as $pid){$p=wc_get_product($pid);$url=get_permalink($pid);$html.='<li class="ch-recommendation-card"><a href="'.esc_url($url).'">'.$p->get_image('woocommerce_thumbnail',['loading'=>'lazy','decoding'=>'async']).'<h3>'.esc_html($p->get_name()).'</h3></a><div class="ch-recommendation-price">'.$p->get_price_html().'</div><a class="ch-recommendation-link" href="'.esc_url($url).'">مشاهده محصول <span aria-hidden="true">←</span></a></li>';}
   $html.='</ul></section>';
  }return $html.'</div>';
 }
 public static function comparison( array $products ): string {
  if(!$products)return '';
  $selected=array_map(static fn($p)=>$p->get_id(),$products);$pools=[];$categories=[];
  foreach($selected as $id){$pools[]=self::select($id)['similar'];$terms=self::categories($id);$parents=[];foreach($terms as $t)$parents=array_merge($parents,get_ancestors($t->term_id,'product_cat'));foreach($terms as $t)if(!in_array($t->term_id,$parents,true))$categories[]=$t->term_id;}
  $suggestions=[];for($i=0;$i<6;$i++)foreach($pools as $pool){$id=$pool[$i]??0;if($id&&!in_array($id,$selected,true)&&!in_array($id,$suggestions,true))$suggestions[]=$id;if(count($suggestions)===6)break 2;}
  $html='<div class="ch-product-recommendations ch-comparison-recommendations" data-comparison-recommendations="'.esc_attr(implode(',',$selected)).'"><section class="ch-recommendation-section"><h2>محصولات مشابه برای این مقایسه</h2>';
  if($suggestions){$html.='<ul class="ch-recommendation-row">';foreach($suggestions as $id){$p=wc_get_product($id);$url=get_permalink($id);$html.='<li class="ch-recommendation-card"><a href="'.esc_url($url).'">'.$p->get_image('woocommerce_thumbnail',['loading'=>'lazy','decoding'=>'async']).'<h3>'.esc_html($p->get_name()).'</h3></a><div class="ch-recommendation-price">'.$p->get_price_html().'</div><a class="ch-recommendation-link" href="'.esc_url($url).'">مشاهده محصول <span aria-hidden="true">←</span></a></li>';}$html.='</ul>';}
  else{$html.='<p class="ch-recommendation-empty">در حال حاضر محصول مشابه موجودی برای این انتخاب نداریم.</p>';}
  $articles=[];
  // Existing editorial comparisons discuss these product types rather than SKUs.
  $topics=apply_filters('chidemoon_comparison_article_categories',[
   'round-or-rectangular-table'=>['dining-tables-chairs','coffee-side-tables-poufs'],
   'open-shelves-or-closed-storage'=>['shelves-wall-decor'],
  ]);
  $slugs=[];foreach(array_unique($categories) as $cid){$t=get_term($cid,'product_cat');if($t&&!is_wp_error($t))$slugs[]=$t->slug;}
  foreach(get_posts(['post_type'=>'post','post_status'=>'publish','has_password'=>false,'category_name'=>'comparisons','posts_per_page'=>-1]) as $post){
   $refs=Chidemoon_Core_Content_Relations::references($post->ID);$topic_match=array_intersect($slugs,$topics[$post->post_name]??[]);if(count($refs)<2&&!$topic_match)continue;$matches=0;
   foreach($refs as $ref){$terms=wp_get_object_terms($ref,'product_cat',['fields'=>'ids']);if(!is_wp_error($terms)&&array_intersect($terms,$categories))$matches++;}
   if($matches<2&&!$topic_match)continue;
   $articles[$post->ID]=count(array_intersect($selected,$refs))*100+$matches+count($topic_match);
  }
  arsort($articles);$html.='</section><section class="ch-recommendation-section"><h2>مقاله‌های مقایسه مرتبط</h2>';
  if($articles){$html.='<ul class="ch-recommendation-row ch-comparison-article-row">';foreach(array_slice(array_keys($articles),0,6) as $id){$html.='<li class="ch-recommendation-card"><a href="'.esc_url(get_permalink($id)).'">'.get_the_post_thumbnail($id,'medium_large',['loading'=>'lazy','decoding'=>'async']).'<h3>'.esc_html(get_the_title($id)).'</h3></a><a class="ch-recommendation-link" href="'.esc_url(get_permalink($id)).'">مشاهده مقایسه <span aria-hidden="true">←</span></a></li>';}$html.='</ul>';}
  else{$html.='<p class="ch-recommendation-empty">هنوز مقاله مقایسه مرتبطی با این محصولات منتشر نشده است.</p>';}
  return $html.'</section></div>';
 }
}
