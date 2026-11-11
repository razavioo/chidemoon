<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_woocommerce'))exit;
		$rows = array(
			array( 'روی میز کوچک', 'نصب روی لبهٔ میز؛ جای پایه روی سطح میز را آزاد می‌گذارد. بازشدگی گیره تا ۵ سانتی‌متر اعلام شده است.', 'گیرهٔ اتصال دارد؛ پیش از انتخاب، محل نصب و فضای اطراف گردنی را بررسی کن.' ),
			array( 'تنظیم نور هنگام مطالعه', 'بازو می‌چرخد و جهت نور تغییر می‌کند؛ انتخاب رنگ و شدت نور به لامپ E27 انتخابی بستگی دارد.', 'سه رنگ نور و تنظیم شدت با کنترل لمسی؛ برای تغییر تنظیمات، از کنترل خود چراغ استفاده می‌کنی.' ),
			array( 'جابه‌جایی بین فضاها', 'برای میز ثابت و تنظیم موقعیت با بازو قابل بررسی است؛ سازگاری گیره با لبهٔ میز را چک کن.', 'شارژی است؛ مدت کارکرد و ظرفیت باتری در اطلاعات بررسی‌شده مشخص نشده است.' ),
			array( 'جزئیات کاربردی', 'سرپیچ E27 امکان انتخاب لامپ را می‌دهد؛ سازگاری لامپ را پیش از خرید بررسی کن.', 'گردنی انعطاف‌پذیر و نگهدارندهٔ موبایل در مشخصات فروشنده ذکر شده است.' ),
			array( 'جمع‌بندی چیدمون', 'اگر اولویتت بازوی قابل تنظیم و انتخاب مستقل لامپ برای یک میز ثابت است، این مدل را بررسی کن.', 'اگر کنترل لمسی، تغییر رنگ نور و شارژی‌بودن برایت مهم‌تر است، این مدل را بررسی کن.' ),
		);
$keys=array_keys(Chidemoon_Core_Compare::experience_fields());
foreach([451,455] as $index=>$id){
 $product=wc_get_product($id);if(!$product)WP_CLI::error('Missing product '.$id);
 if($product->get_meta('_chidemoon_usage_comparison',true)){WP_CLI::log('Existing usage data '.$id);continue;}
 $values=[];foreach($keys as $i=>$key)$values[$key]=$rows[$i][$index+1];
 if(!in_array('apply',$args??[],true))continue;
 $product->update_meta_data('_chidemoon_usage_comparison',['values'=>$values,'source'=>get_permalink($id),'reviewed_by'=>get_current_user_id(),'reviewed_at'=>gmdate('c'),'basis'=>'Existing reviewed product description; editorial use-case analysis, not hands-on testing']);$product->save_meta_data();
}
WP_CLI::success('Usage comparison seed complete.');
