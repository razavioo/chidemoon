<?php
define('ABSPATH',__DIR__);
class Chidemoon_Core_Source_Importer {const META_IDENTITY='identity';const META_OBSERVATIONS='observations';}
class WC_Product {public $identity='source';public $amount='980000';public $observations=[];function get_meta($k,$single=true){return $k==='identity'?$this->identity:json_encode($this->observations);}function get_price(){return $this->amount;}}
function esc_html__($s,$domain){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function wc_price($p){return '<bdi>'.number_format((float)$p).' ریال</bdi>';}
require __DIR__.'/../plugins/chidemoon-core/includes/class-chidemoon-core-commercial.php';
function check_price($ok,$why){if(!$ok)throw new RuntimeException($why);}
$p=new WC_Product();$p->observations=['price'=>['observedAt'=>gmdate('c',time()-172800)]];
$html=Chidemoon_Core_Commercial::price_html('<del>old sale</del>',$p);
check_price(str_contains($html,'980,000')&&str_contains($html,'آخرین قیمت ثبت‌شده')&&!str_contains($html,'<del>'),'Recorded price hidden or stale discount shown');
$p->amount='0';check_price(str_contains(Chidemoon_Core_Commercial::price_html('',$p),'<bdi>0 '),'Zero treated as missing');
$p->amount='';check_price(str_contains(Chidemoon_Core_Commercial::price_html('',$p),'ch-price-unknown'),'Missing amount fabricated');
$p->amount='980000';$p->observations=['price'=>['observedAt'=>gmdate('c')]];check_price(Chidemoon_Core_Commercial::price_html('fresh html',$p)==='fresh html','Fresh Woo price formatting changed');
$p->identity='';check_price(Chidemoon_Core_Commercial::price_html('native html',$p)==='native html','Native product changed');
echo "PASS: recorded, fresh, missing and zero prices\n";
