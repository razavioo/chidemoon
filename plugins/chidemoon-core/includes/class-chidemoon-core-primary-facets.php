<?php
if (!defined('ABSPATH')) exit;
final class Chidemoon_Core_Primary_Facets {
 public const VALUES = [
'chidemoon_style'=>['classic'=>'کلاسیک','neoclassical'=>'نئو کلاسیک','modern'=>'مدرن','iranian'=>'ایرانی','minimal'=>'مینیمال','french'=>'فرانسوی','boho'=>'بوهو'],
'chidemoon_room'=>['living-room'=>'پذیرایی','kitchen'=>'آشپزخانه','dining-room'=>'غذاخوری','bedroom'=>'اتاق خواب','kids-room'=>'اتاق کودک و نوجوان','entryway'=>'راهرو','home-office'=>'اتاق کار','outdoor'=>'فضای باز','bathroom'=>'حمام و سرویس بهداشتی','laundry'=>'لاندری'],
'pa_color'=>['navy'=>'سرمه‌ای','cream'=>'کرم','white'=>'سفید','burgundy'=>'زرشکی','red'=>'قرمز','blue'=>'آبی','black'=>'مشکی','pink'=>'صورتی','yellow'=>'زرد','orange'=>'نارنجی','purple'=>'بنفش','green'=>'سبز','brown'=>'قهوه‌ای','gray'=>'طوسی'] ];
 public static function values($tax){return self::VALUES[$tax==='chidemoon_look_color'?'pa_color':$tax]??[];}
 public static function terms($tax){$values=self::values($tax);return get_terms(['taxonomy'=>$tax,'hide_empty'=>false,'slug'=>array_keys($values),'orderby'=>'include']);}
 public static function canonical($tax,$name,$slug){$values=self::values($tax);if(isset($values[$slug]))return $slug;
 $name=str_replace(['‌','ي','ك'],[' ','ی','ک'],$name);
 if(in_array($tax,['pa_color','chidemoon_look_color'],true)){
 foreach(['سرمه'=>'navy','زرشک'=>'burgundy','فیروزه'=>'blue','پسته'=>'green','یشم'=>'green','زیتون'=>'green','بژ'=>'cream','خاکستری'=>'gray','طوسی'=>'gray'] as $word=>$key)if(str_contains($name,$word))return $key;
 }
 foreach($values as $key=>$label)if(str_contains($name,str_replace('?',' ',$label)))return $key;
 foreach(['neoclassic'=>'neoclassical','minimalist'=>'minimal','entrance'=>'entryway','hallway'=>'entryway','balcony'=>'outdoor','terrace'=>'outdoor','classic-french'=>'french','iranian-boho'=>'boho'] as $old=>$key)if($slug===$old&&isset($values[$key]))return $key;
 return null; }
 public static function sync(){foreach(array_merge(array_keys(self::VALUES),['chidemoon_look_color']) as $tax){if(!taxonomy_exists($tax))continue;foreach(self::values($tax) as $slug=>$label){ $existing=get_term_by('slug',$slug,$tax);if(!$existing)wp_insert_term($label,$tax,['slug'=>$slug]);else wp_update_term($existing->term_id,$tax,['name'=>$label]); }
 $terms=get_terms(['taxonomy'=>$tax,'hide_empty'=>false]);foreach($terms as $term){$key=self::canonical($tax,$term->name,$term->slug);if(!$key||$key===$term->slug)continue;$target=get_term_by('slug',$key,$tax);$ids=get_objects_in_term($term->term_id,$tax);foreach(is_wp_error($ids)?[]:$ids as $id)wp_set_object_terms((int)$id,[(int)$target->term_id],$tax,true);}}
 Chidemoon_Core_Look_Pages::sync(); }
}

add_action('set_object_terms',static function($object,$terms,$tt,$tax){static $busy=false;if($busy||!Chidemoon_Core_Primary_Facets::values($tax))return;$busy=true;foreach(wp_get_object_terms($object,$tax) as $term){$key=Chidemoon_Core_Primary_Facets::canonical($tax,$term->name,$term->slug);if($key&&$key!==$term->slug){$target=get_term_by('slug',$key,$tax);if($target)wp_set_object_terms($object,[(int)$target->term_id],$tax,true);}}$busy=false;},30,4);
