<?php
/** Regression checks for dimension extraction: units, thickness, model codes and variants. */
define('ABSPATH',__DIR__);
function wp_strip_all_tags($value){return strip_tags($value);}
require (getenv('CHIDEMOON_TEST_CORE') ?: dirname(__DIR__).'/plugins/chidemoon-core').'/includes/class-chidemoon-core-category-facets.php';
$method=new ReflectionMethod(Chidemoon_Core_Category_Facets::class,'sizes');
$cases=[
 ['آینه کد W-80-60',false,[]],
 ['ابعاد ۱۸۰×۸۰×۱.۶ سانتی متر',false,['80 × 180 سانتی‌متر']],
 ['فرش 2×3 متر',false,['200 × 300 سانتی‌متر']],
 ['فرش (207×322) سانتیمتر',false,['207 × 322 سانتی‌متر']],
 ['ابعاد 2×3',false,[]],
 ['20x30',true,['20 × 30 سانتی‌متر']],
 ['طول این محصول 88cm و عرض آن 59cm می باشد.',false,['59 × 88 سانتی‌متر']],
 ['سایز کار 120/80 هستش',false,['80 × 120 سانتی‌متر']],
];
foreach($cases as [$source,$variant,$expected]){if($method->invoke(null,$source,$variant)!==$expected)throw new RuntimeException('Incorrect dimension: '.$source);}
echo "Category dimension regressions passed.\n";