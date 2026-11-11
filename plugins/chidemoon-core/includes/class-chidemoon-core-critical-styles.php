<?php
/** Inline small local styles in their original cascade position, avoiding serial mobile requests. */
if(!defined('ABSPATH'))exit;
final class Chidemoon_Core_Critical_Styles {
 public static function register():void {add_action('template_redirect',[__CLASS__,'start'],19);}
 public static function start():void {
  if(is_admin()||is_feed()||wp_is_json_request())return;
  ob_start([__CLASS__,'rewrite']);
 }
 public static function rewrite(string $html):string {
  $bytes=0;$host=parse_url(home_url(),PHP_URL_HOST);
  return preg_replace_callback('/<link\b[^>]*>/i',static function($match)use(&$bytes,$host){
   $tag=new WP_HTML_Tag_Processor($match[0]);if(!$tag->next_tag('link')||$tag->get_attribute('rel')!=='stylesheet')return $match[0];
   $url=html_entity_decode((string)$tag->get_attribute('href'),ENT_QUOTES,'UTF-8');$parts=parse_url($url);
   if(!$parts||($parts['host']??$host)!==$host||!str_ends_with($parts['path']??'','.css'))return $match[0];
   $file=realpath(ABSPATH.ltrim(rawurldecode($parts['path']),'/'));
   if(!$file||!str_starts_with($file,realpath(ABSPATH).DIRECTORY_SEPARATOR)||!is_readable($file)||filesize($file)>196608||$bytes+filesize($file)>524288)return $match[0];
   $css=file_get_contents($file);if(stripos($css,'@import')!==false||stripos($css,'</style')!==false)return $match[0];
   $base=home_url(dirname($parts['path']).'/');
   $css=preg_replace_callback('~url\(\s*([\'\"]?)([^)\'\"]+)\1\s*\)~i',static function($m)use($base){$value=trim($m[2]);return preg_match('~^(?:[a-z][a-z0-9+.-]*:|/|\#)~i',$value)?$m[0]:'url("'.$base.$value.'")';},$css);
   $css=preg_replace('/@charset\s+[\'\"][^\'\"]+[\'\"];?/i','',$css);$bytes+=strlen($css);
   $id=$tag->get_attribute('id');$media=$tag->get_attribute('media');return '<style'.($id?' id="'.esc_attr($id).'"':'').($media?' media="'.esc_attr($media).'"':'').' data-ch-inline="1">'.$css.'</style>';
  },$html);
 }
}
