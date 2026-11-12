<?php
/** Standalone checks for automatic scene acceptance and product point identity. */
define('ABSPATH',__DIR__);
class WP_Error { public function __construct(...$args){} }
function is_wp_error($v){return $v instanceof WP_Error;}
function sanitize_text_field($v){return strip_tags($v);}
function sanitize_textarea_field($v){return strip_tags($v);}
require __DIR__.'/../plugins/chidemoon-ai/includes/class-chidemoon-ai-look.php';
$valid=array('products_match'=>true,'title'=>'نشیمن با فرش قرمز','excerpt'=>'کنسول چوبی کنار آباژور.','hotspots'=>array(array('product_id'=>1,'x'=>30,'y'=>40),array('product_id'=>2,'x'=>70,'y'=>60)));
function check($ok){if(!$ok)throw new RuntimeException('Assessment regression');}
check(!is_wp_error(Chidemoon_AI_Look::validate_assessment($valid,array(1,2))));
foreach(array('mismatch','duplicate','unknown','missing','bounds','title','invalid') as $case){$v=$valid;switch($case){case 'mismatch':$v['products_match']=false;break;case 'duplicate':$v['hotspots'][1]['product_id']=1;break;case 'unknown':$v['hotspots'][1]['product_id']=99;break;case 'missing':array_pop($v['hotspots']);break;case 'bounds':$v['hotspots'][0]['x']=101;break;case 'title':$v['title']='AI styled look';break;case 'invalid':$v=new WP_Error();break;}check(is_wp_error(Chidemoon_AI_Look::validate_assessment($v,array(1,2))));}
echo "PASS automatic Look assessment accepts complete results and rejects mismatch, duplicate/unknown/missing points, invalid bounds and unsuitable titles.\n";
