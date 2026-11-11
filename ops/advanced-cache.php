<?php
/** Chidemoon: short-lived anonymous catalogue HTML only. Installed as WP advanced-cache.php. */
if (!defined('ABSPATH') || PHP_SAPI === 'cli') return;
$request=$_SERVER['REQUEST_URI']??'';$path=parse_url($request,PHP_URL_PATH);
if (($_SERVER['REQUEST_METHOD']??'')!=='GET'||!empty($_COOKIE)||!empty($_SERVER['QUERY_STRING'])||!empty($_SERVER['HTTP_AUTHORIZATION'])||!in_array(strtolower($_SERVER['HTTP_HOST']??''),['chidemoon.com','www.chidemoon.com'],true)||!is_string($path)||!preg_match('~^(?:/|/(?:product|product-category)/[^?]+|/shop/|/shop-the-look/)$~D',$path)) return;
$root=WP_CONTENT_DIR.'/cache/chidemoon-public';
if(!is_dir($root))return;
$generation=is_file($root.'/generation')?file_get_contents($root.'/generation'):'0';
$cache=$root.'/'.hash('sha256',$generation.'|'.$request).'.html';
if(is_file($cache)&&filemtime($cache)>time()-300){header('Content-Type: text/html; charset=UTF-8');header('X-Chidemoon-Cache: HIT');header('Cache-Control: no-cache, must-revalidate');readfile($cache);exit;}
header('X-Chidemoon-Cache: MISS');
ob_start(static function($html,$phase)use($cache,$root,$generation){
 if(!($phase&PHP_OUTPUT_HANDLER_FINAL)||http_response_code()!==200||strlen($html)<500||!str_contains($html,'</html>')||(defined('DONOTCACHEPAGE')&&DONOTCACHEPAGE))return $html;
 foreach(headers_list() as $header)if(preg_match('~^(Set-Cookie:|Location:)|^Cache-Control:.*(?:private|no-store)~i',$header))return $html;
 if((is_file($root.'/generation')?file_get_contents($root.'/generation'):'0')!==$generation)return $html;
 $temp=$cache.'.'.bin2hex(random_bytes(5)).'.tmp';if(file_put_contents($temp,$html,LOCK_EX)!==false)rename($temp,$cache);
 return $html;
});
