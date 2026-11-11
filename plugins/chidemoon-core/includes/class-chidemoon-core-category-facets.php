<?php
/** Category-specific facets derived only from explicit catalog/source records. */
if ( ! defined('ABSPATH') ) { exit; }
final class Chidemoon_Core_Category_Facets {
 public static function normalize(string $text): string {
  return trim(preg_replace('/\s+/u',' ',strtr(wp_strip_all_tags($text),array('۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9','ي'=>'ی','ك'=>'ک'))));
 }
 public static function fields(): array {
  if (!is_product_category()) { return array(); }
  $term=get_queried_object();$names=array($term->name);
  foreach(get_ancestors($term->term_id,'product_cat','taxonomy') as $id){$t=get_term($id,'product_cat');if($t&&!is_wp_error($t))$names[]=$t->name;}
  $family=implode(' ',$names);
  if(preg_match('/تابلو/u',$family))return array('board_material'=>'جنس','board_style'=>'سبک','size'=>'ابعاد');
  if(preg_match('/آینه|فرش|زیرانداز/u',$family))return array('size'=>'ابعاد');
  if(preg_match('/لوازم برقی|نوشیدنی‌ساز|آماده‌سازی غذا|گرمایشی|ظرفشویی/u',$family))return array('brand'=>'برند');
  return array();
 }
 private static function sizes(string $text, bool $attribute=false): array {
  $text=self::normalize($text);$values=array();
  $n='[0-9]+(?:\.[0-9]+)?';
  // Parse the entire tuple, including thickness, before selecting display dimensions.
  $tuple='(?<![0-9.])'.$n.'(?:\s*(?:×|x|X|✕|در|\*)\s*'.$n.'){1,2}(?![0-9.])';
  preg_match_all('/'.$tuple.'/u',$text,$matches,PREG_OFFSET_CAPTURE);
  foreach($matches[0] as [$match,$offset]) {
   $before=mb_substr(substr($text,0,$offset),-45);
   $after=substr($text,$offset+strlen($match));
   $factor=null;
   if(preg_match('/^\s*\)?\s*(?:سانت(?:ی[\s‌-]*متر)?|cm)\b/iu',$after))$factor=1;
   elseif(preg_match('/^\s*\)?\s*(?:میلی[\s‌-]*متر|mm)\b/iu',$after))$factor=0.1;
   elseif(preg_match('/^\s*\)?\s*(?:متر|m)\b/iu',$after))$factor=100;
   elseif($attribute||preg_match('/(?:ابعاد|اندازه|سایز|size)\s*(?:کار|محصول|تابلو|آینه)?\s*[:：]?\s*\(?$/iu',$before))$factor=1;
   if(null===$factor)continue;
   preg_match_all('/'.$n.'/u',$match,$numbers);$s=array_map(static fn($v)=>(float)$v*$factor,$numbers[0]);rsort($s,SORT_NUMERIC);$s=array_slice($s,0,2);sort($s,SORT_NUMERIC);
   if(min($s)>=10&&max($s)<=2000)$values[]=implode(' × ',$s).' سانتی‌متر';
  }
  $number='([0-9]+(?:\.[0-9]+)?)';
  if(preg_match('/(?:سایز|ابعاد)\s*(?:کار)?\s*[:：]?\s*'.$number.'\s*\/\s*'.$number.'/u',$text,$m)){$s=array((float)$m[1],(float)$m[2]);if(min($s)>=10&&max($s)<=2000){sort($s,SORT_NUMERIC);$values[]=implode(' × ',$s).' سانتی‌متر';}}
  if(preg_match('/طول\s*(?:این\s*محصول|محصول|آن)?\s*[:：]?\s*'.$number.'\s*(?:cm|سانت).*?عرض\s*(?:آن|محصول)?\s*[:：]?\s*'.$number.'\s*(?:cm|سانت)/iu',$text,$m)){$s=array((float)$m[1],(float)$m[2]);if(min($s)>=10&&max($s)<=2000){sort($s,SORT_NUMERIC);$values[]=implode(' × ',$s).' سانتی‌متر';}}
  return array_values(array_unique($values));
 }
 public static function values(int $id,string $field): array {
  static $cache=array();if(isset($cache[$id][$field]))return $cache[$id][$field];
  $p=wc_get_product($id);if(!$p)return array();
  $title=self::normalize($p->get_name());
  $description=get_post_meta($id,Chidemoon_Core_Source_Importer::META_DESCRIPTION,true);
  $text=self::normalize($title.' '.(is_string($description)&&$description!==''?$description:$p->get_description()));
  $values=array();
  if($field==='brand'){
   $brand=get_post_meta($id,'_chidemoon_source_brand',true);if(is_string($brand)&&trim($brand)!=='')$values[]=self::normalize($brand);
   foreach(array('product_brand','pa_brand') as $tax){if(taxonomy_exists($tax)){foreach(wp_get_object_terms($id,$tax) as $t){if($t instanceof WP_Term)$values[]=$t->name;}}}
   foreach($p->get_attributes() as $a){if(!$a->is_taxonomy()&&preg_match('/brand|برند|مارک/iu',$a->get_name()))$values=array_merge($values,$a->get_options());}
   if(!$values){
    $brands=array('دلونگی'=>'دلونگی|delonghi|de.longhi','فیلیپس'=>'فیلیپس|philips','بوش'=>'بوش|bosch','مولینکس'=>'مولینکس|moulinex','پارس خزر'=>'پارس\s*خزر','نوا'=>'نوا|nova','مباشی'=>'مباشی|mebashi','فلر'=>'فلر|feller','دسینی'=>'دسینی|دیسینی|dessini','برفاب'=>'برفاب','نیک‌کالا'=>'نیک\s*[‌-]?کالا','آبسال'=>'آبسال','تفال'=>'تفال|tefal','میگل'=>'میگل|migel','سیلور کرست'=>'سیلور\s*کرست|silver\s*crest','کنوود'=>'کنوود|kenwood','ال‌جی'=>'ال\s*[‌-]?جی|\bLG\b','سامسونگ'=>'سامسونگ|samsung','گری'=>'\bگری\b|gree','ویداس'=>'ویداس|vidas','کاخلر'=>'کاخلر|kahler','عرشیا'=>'عرشیا|arshia','گوسونیک'=>'گوسونیک|gosonic','کاراجا'=>'کاراجا|karaca','کاراکال'=>'کاراکال|caracal','تکنو'=>'تکنو|techno','یونیک'=>'یونیک|unique','لونا'=>'لونا','هایبورگ'=>'هایبورگ','فلاویا'=>'فلاویا','سونیفر'=>'سونیفر|sonifer','بلک اند دکر'=>'بلک\s*اند\s*دکر|black.*decker','مدیا'=>'مدیا|midea','لوکسین'=>'لوکسین','آیسان'=>'آیسان','کاوه'=>'کاوه','ایران شرق'=>'ایران\s*شرق','خزر لاکان'=>'خزر\s*لاکان','لواک'=>'لواک','ووگاتی'=>'ووگاتی','کاستلو'=>'کاستلو','برنر'=>'برنر','مادالکتریک'=>'مادالکتریک','ولف'=>'ولف|\bwolf\b','تیتومکث'=>'تیتومکث','چیکو'=>'چیکو|chicco','اونت'=>'اونت|avent');
    foreach($brands as $label=>$pattern){if(preg_match('/'.$pattern.'/iu',$title)){$values[]=$label;break;}}
   }
  }elseif($field==='size'){
   foreach($p->get_attributes() as $a){if(preg_match('/size|dimension|ابعاد|اندازه|سایز/iu',$a->get_name())){foreach($a->get_options() as $option){if($a->is_taxonomy()){$t=get_term($option,$a->get_name());$option=$t&&!is_wp_error($t)?$t->name:'';}$values=array_merge($values,self::sizes((string)$option,true));}}}
   $values=array_merge($values,self::sizes($text));
   $e=get_post_meta($id,Chidemoon_Core_Enrichment::META,true);$d=is_array($e)?($e['dimensions']['value']??array()):array();
   $a=$d['length']??null;$b=$d['width']??null;
   if(is_numeric($a)&&is_numeric($b)&&$a>0&&$b>0){$s=array((float)$a,(float)$b);sort($s,SORT_NUMERIC);$values[]=implode(' × ',$s).' سانتی‌متر';}
   if(!$values&&$p->get_length()&&$p->get_width()){$s=array(wc_get_dimension((float)$p->get_length(),'cm'),wc_get_dimension((float)$p->get_width(),'cm'));sort($s,SORT_NUMERIC);$values[]=implode(' × ',$s).' سانتی‌متر';}
   if(preg_match('/فرش|قالی|گلیم|گبه/u',$title)){preg_match_all('/(?:^|[^0-9.])([1-9][0-9]?(?:\.[0-9]+)?)\s*(?:متری|متر\s*مربع)/u',$title,$m);foreach($m[1] as $area)$values[]=$area.' مترمربع';}
  }else{
   $taxonomy=$field==='board_material'?'pa_material':'chidemoon_style';
   foreach(wp_get_object_terms($id,$taxonomy) as $t){if($t instanceof WP_Term)$values[]=$t->name;}
   if($field==='board_material'){
    if(preg_match('/ام\s*دی\s*اف|\bmdf\b/iu',$text))$values[]='ام‌دی‌اف';
    if(preg_match('/پی\s*وی\s*سی|\bpvc\b/iu',$text))$values[]='پی‌وی‌سی';
    if(preg_match('/(?:قاب|تابلو)\s*چوبی|جنس\s*(?:قاب)?\s*[:：]?\s*چوب/u',$text))$values[]='چوب';
   }else{foreach(array('مدرن'=>'مدرن|modern','کلاسیک'=>'کلاسیک|classic','مینیمال'=>'مینیمال|minimal','بوهو'=>'بوهو|boho','روستیک'=>'روستیک|rustic','اسکاندیناوی'=>'اسکاندیناوی|scandinavian','کودکانه'=>'تابلو کودک|طرح کودک','آینه‌کاری'=>'آیینه کاری|آینه کاری|آینه‌کاری','تکسچر'=>'تکسچر') as $label=>$pattern){if(preg_match('/'.$pattern.'/iu',$text))$values[]=$label;}}
  }
  $values=array_values(array_unique(array_filter(array_map(static fn($v)=>self::normalize((string)$v),$values),static fn($v)=>!in_array(strtolower($v),array('','none','unknown','نامشخص','متفرقه','بدون برند'),true))));
  return $cache[$id][$field]=$values;
 }
 public static function token(string $value): string {return substr(hash('sha256',self::normalize($value)),0,24);}
 public static function render(array $ids,array $input): string {
  $html='';foreach(self::fields() as $field=>$label){$options=array();foreach($ids as $id){foreach(self::values((int)$id,$field) as $value)$options[self::token($value)]=$value;}asort($options,SORT_NATURAL);
   if(!$options)continue;
   $key='ch_'.$field;$html.='<fieldset><legend>'.esc_html($label).'</legend><label><input type="checkbox" class="ch-facet-all" '.checked(!Chidemoon_Core_Discovery::selection($key,$input),true,false).'> همه</label>';
   foreach($options as $token=>$value)$html.='<label><input type="checkbox" name="'.esc_attr($key).'[]" value="'.esc_attr($token).'" '.checked(in_array($token,Chidemoon_Core_Discovery::selection($key,$input),true),true,false).'> '.esc_html($value).'</label>';
   $html.='</fieldset>';
  }return $html;
 }
 public static function matching_ids(array $input): ?array {
  $selected=array();foreach(array('brand','size','board_material','board_style') as $field){$s=Chidemoon_Core_Discovery::selection('ch_'.$field,$input);if($s)$selected[$field]=$s;}
  if(!$selected)return null;
  $scope=Chidemoon_Core_Discovery::category_product_ids();
  if(null===$scope)$scope=get_posts(array_merge(array('post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true),Chidemoon_Core_Discovery::query_filters(array())));
  return array_values(array_filter($scope,static function($id)use($selected){foreach($selected as $field=>$tokens){$actual=array_map(array(__CLASS__,'token'),self::values((int)$id,$field));if(!array_intersect($tokens,$actual))return false;}return true;}));
 }
}