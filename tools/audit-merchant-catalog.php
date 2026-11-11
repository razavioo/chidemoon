<?php
$dir='/tmp/chidemoon-catalog-check-20261107';@mkdir($dir,0700,true);@mkdir($dir.'/evidence',0700,true);
$inventory=json_decode(file_get_contents('/tmp/catalog-audit-inventory.json'),true,512,JSON_THROW_ON_ERROR);$done=[];$file=$dir.'/results.jsonl';if(is_file($file))foreach(file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){$r=json_decode($line,true);$done[$r['id']]=true;}
$queue=array_values(array_filter($inventory['products'],fn($p)=>!isset($done[$p['id']])));$multi=curl_multi_init();$active=[];$finished=count($done);
function add_job($multi,&$active,$p){$ch=curl_init($p['source']);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>4,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_ENCODING=>'',CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; ChidemoonCatalogCheck/1.0; +https://chidemoon.com/)',CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml'],CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);curl_multi_add_handle($multi,$ch);$active[spl_object_id($ch)]=[$ch,$p];}
while($queue||$active){while(count($active)<4&&$queue)add_job($multi,$active,array_shift($queue));do{$status=curl_multi_exec($multi,$running);}while($status===CURLM_CALL_MULTI_PERFORM);
 while($info=curl_multi_info_read($multi)){$ch=$info['handle'];[$unused,$old]=$active[spl_object_id($ch)];$h=curl_multi_getcontent($ch);$http=curl_getinfo($ch,CURLINFO_HTTP_CODE);$url=curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);$r=['id'=>$old['id'],'source'=>$old['source'],'finalUrl'=>$url,'http'=>$http,'at'=>gmdate('c'),'seconds'=>curl_getinfo($ch,CURLINFO_TOTAL_TIME),'state'=>'unverified','oldPrice'=>$old['price'],'error'=>curl_error($ch)?:null];
  if($http===200&&str_contains((string)parse_url($url,PHP_URL_HOST),'basalam.com')&&preg_match('/<script id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s',$h,$m)){
   $data=json_decode($m[1],true);$p=$data['props']['pageProps']['product']??null;
   if(is_array($p)&&isset($p['id'])&&preg_match('~/product/(\d+)~',$old['source'],$expected)&&$expected[1]==(string)$p['id']){
    $offers=null;preg_match_all('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s',$h,$ld);foreach($ld[1] as $v){$j=json_decode($v,true);if(isset($j['offers'])){$offers=$j['offers'];break;}}
    $r['productId']=$p['id'];$r['title']=$p['title']??'';$r['price']=$p['price']??null;$r['primaryPrice']=$p['primaryPrice']??null;$r['discount']=$p['discount']??null;$r['currency']=$offers['priceCurrency']??null;$r['offerPrice']=$offers['price']??null;
    foreach(['isAvailable','inventory','isSaleable','isShowable','canAddToCart','backendHttpStatusCode','status','variants'] as $key)$r[$key]=$p[$key]??null;
    $r['vendorStatus']=$p['vendor']['status']??null;$r['vendorId']=$p['vendor']['id']??null;$r['schemaAvailability']=$offers['availability']??null;
    $r['state']='verified';if($r['currency']!=='IRR'||!is_numeric($r['price'])||(float)$r['price']!==(float)$r['offerPrice'])$r['state']='price_unverified';
    $r['evidenceHash']=hash('sha256',$m[1]);file_put_contents($dir.'/evidence/'.$old['id'].'.json.gz',gzencode(json_encode($r,JSON_UNESCAPED_UNICODE)));
   }else{$r['error']='product_identity_missing_or_mismatched';$r['pageKeys']=array_keys($data['props']['pageProps']??[]);}
  }elseif(in_array($http,[404,410],true)){$r['state']='missing_candidate';}
  file_put_contents($file,json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX);unset($active[spl_object_id($ch)]);curl_multi_remove_handle($multi,$ch);curl_close($ch);++$finished;if($finished%25===0)echo "$finished checked\n";
 }
 if($running)curl_multi_select($multi,0.5);
}
echo "Finished $finished products\n";