<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
$plan=json_decode(file_get_contents('/tools/product-enrichment-plan.json'),true,512,JSON_THROW_ON_ERROR);$issues=[];$coverage=[];$targets=[];
foreach($plan['products'] as $r){$id=$r['id'];$p=wc_get_product($id);$approved=Chidemoon_Core_Enrichment::read($id);$facts=Chidemoon_Core_Compare::facts($p);$expect=[];
 foreach(['style','room','color','material','features','suitability'] as $f)if(($approved[$f]['state']??'')==='value')$coverage[$f]=($coverage[$f]??0)+1;
 foreach($r['fields'] as $f=>$v){$actual=Chidemoon_Core_Enrichment::display_field($f,$approved[$f]??null);foreach($v['names'] as $name)if(!str_contains($actual,$name))$issues[]=['id'=>$id,'field'=>$f,'issue'=>'stored value'];$label=Chidemoon_Core_Enrichment::field_label($f);if(($facts[$label]??null)!==$actual)$issues[]=['id'=>$id,'field'=>$f,'issue'=>'facts rendering'];$expect[$label]=$v['names'];
 if(in_array($f,['color','material','features','suitability'],true)){$a=$p->get_attributes()[Chidemoon_Core_Enrichment::TAXONOMIES[$f]]??null;if(!$a||!$a->get_visible())$issues[]=['id'=>$id,'field'=>$f,'issue'=>'invisible attribute'];}}
 if($p->get_status()!=='publish'||!Chidemoon_Core_Affiliate::is_publicly_eligible($p))$issues[]=['id'=>$id,'issue'=>'publication'];
 $targets[]=['id'=>$id,'url'=>get_permalink($id),'expect'=>$expect];
}
WP_CLI::log(wp_json_encode(['verified'=>count($targets),'coverage'=>$coverage,'issues'=>$issues,'targets'=>$targets],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));if($issues)WP_CLI::error('Enrichment verification failed');
