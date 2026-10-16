<?php
// Isolated regression: an import expires the token before the receipt is sent.
define('ABSPATH', __DIR__);
define('CHIDEMOON_CATALOG_CLIENT_SECRET', 'fixture-secret');
$options = ['chidemoon_catalog_settings'=>['origin'=>'https://example.com','clientId'=>'fixture','organizationId'=>'org','organizationSlug'=>'chidemoon','enabled'=>true]];
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function update_option($k,$v,$autoload=false){$GLOBALS['options'][$k]=$v;return true;}
function wp_salt($s){return 'fixture-'.$s;}
function wp_json_encode($v){return json_encode($v);}
function wp_parse_url($v){return parse_url($v);}
function wp_http_validate_url($v){return true;}
function is_wp_error($v){return false;}
function wp_remote_retrieve_response_code($v){return $v['code'];}
function wp_remote_retrieve_body($v){return json_encode($v['body']);}
$requests=[];
function wp_safe_remote_request($url,$args){
 global $requests;$requests[]=$url;
 if(str_ends_with($url,'/token')){
  if(($args['body']['grant_type']??'')!=='refresh_token'){throw new RuntimeException('Wrong grant');}
  return ['code'=>200,'body'=>['access_token'=>'fresh','refresh_token'=>'refresh','token_type'=>'Bearer','expires_in'=>3600,'scope'=>'catalog:source:read catalog:source:ack']];
 }
 if(str_ends_with($url,'/source-receipts')){
  if($args['headers']['Authorization']!=='Bearer fresh'){return ['code'=>401,'body'=>[]];}
  return ['code'=>200,'body'=>['imported'=>true,'publicationConfirmed'=>false]];
 }
 return ['code'=>200,'body'=>['organization'=>['id'=>'org','slug'=>'chidemoon'],'snapshot'=>['id'=>'snapshot','revision'=>'rev']]];
}
class FixtureDB {public $prefix='wp_';function prepare($q,...$args){return $q;}function get_var($q){return '1';}}
$wpdb=new FixtureDB();
class Chidemoon_Core_Source_Importer {
 static function import_snapshot($snapshot,$dry,$slug){
  $seal=new ReflectionMethod('Chidemoon_Catalog_Sync','seal');$seal->setAccessible(true);
  update_option('chidemoon_catalog_tokens',$seal->invoke(null,['access_token'=>'expired','refresh_token'=>'refresh','expiresAt'=>time()-1]));
  return ['failed'=>0,'records'=>[['sourceKey'=>'source','status'=>'imported']]];
 }
}
require __DIR__.'/../plugins/chidemoon-catalog-sync/includes/class-chidemoon-catalog-sync.php';
$seal=new ReflectionMethod('Chidemoon_Catalog_Sync','seal');$seal->setAccessible(true);
update_option('chidemoon_catalog_tokens',$seal->invoke(null,['access_token'=>'initial','refresh_token'=>'refresh','expiresAt'=>time()+3600]));
Chidemoon_Catalog_Sync::poll('manual');
$s=get_option('chidemoon_catalog_status');
if(empty($s['lastSuccessAt'])||!empty($s['blocked'])||get_option('chidemoon_catalog_outbox')!==[]||count($requests)!==3){throw new RuntimeException('Expired token interrupted receipt delivery');}
echo "PASS: access token renewed after long import and receipt delivered\n";
