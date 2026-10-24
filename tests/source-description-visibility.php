<?php
define('ABSPATH',__DIR__);
$GLOBALS['filters']=[];$GLOBALS['admin']=false;$GLOBALS['meta']=[];
function add_filter($name,$callback,...$args){$GLOBALS['filters'][$name]=$callback;}
function add_action(...$args){} function remove_action(...$args){} function add_shortcode(...$args){}
function is_admin(){return $GLOBALS['admin'];}
function get_post_meta($id,$key,...$args){return $GLOBALS['meta'][$id][$key]??'';}
function metadata_exists($type,$id,$key){return isset($GLOBALS['meta'][$id][$key]);}
function add_post_meta($id,$key,$value,...$args){$GLOBALS['meta'][$id][$key]=$value;return true;}
function wp_slash($v){return $v;} function get_post($id){return new WP_Post($id);}
class WP_Post {public $ID,$post_content='';function __construct($id){$this->ID=$id;}}
class WC_Product {public $id,$body;function __construct($id,$body=''){$this->id=$id;$this->body=$body;} function get_id(){return $this->id;}function get_description(){return $this->body;}function set_description($s){$this->body=$s;}}
$core=getenv('CHIDEMOON_TEST_CORE')?:__DIR__.'/../plugins/chidemoon-core/includes';
require $core.'/class-chidemoon-core-reconciliation.php';
require $core.'/class-chidemoon-core-commercial.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach([0,123] as $id){$p=new WC_Product($id);Chidemoon_Core_Reconciliation::prepare_source_description($p,'Merchant details');check($p->body==='[chidemoon_source_description]','Empty body must render live source details');}
$p=new WC_Product(124,'Editor-written details');Chidemoon_Core_Reconciliation::prepare_source_description($p,'Merchant details');check($p->body==='Editor-written details','Preserve editorial content');
$p=new WC_Product(125);Chidemoon_Core_Reconciliation::prepare_source_description($p,'');check($p->body==='','Do not create empty description panels');
$p=new WC_Product(126,'Merchant details');Chidemoon_Core_Reconciliation::prepare_source_description($p,'Merchant details');check($p->body==='[chidemoon_source_description]','Copied source remains dynamic');
Chidemoon_Core_Commercial::register();$filter=$GLOBALS['filters']['get_the_terms'];$tags=[(object)['name'=>'Internal batch tag']];
$currency=$GLOBALS['filters']['woocommerce_currency_symbol'];
check('ریال'===$currency('rial','IRR'),'Rial amounts must be labelled rial');
check('تومان'===$currency('toman','IRT'),'Toman amounts must be labelled toman');
check('$'===$currency('$','USD'),'Preserve unrelated currencies');
check(false===$filter($tags,123,'product_tag'),'Hide public product tags');
check($tags===$filter($tags,123,'product_cat'),'Preserve categories');
$GLOBALS['admin']=true;check($tags===$filter($tags,123,'product_tag'),'Preserve editable admin tags');
echo "PASS: new/existing empty bodies, editorial preservation, empty source, copied source, public tags, categories, admin tags\n";
