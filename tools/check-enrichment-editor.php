<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
$issues=[];
foreach([6653,447,6423,5838,2738] as $id){
 ob_start();Chidemoon_Core_Enrichment::render_editor(get_post($id));$html=ob_get_clean();$fields=Chidemoon_Core_Enrichment::read($id);
 foreach(['style','room','color','material','features','suitability'] as $f){if(!str_contains($html,'name="chidemoon_enrichment['.$f.'][state]"'))$issues[]=['id'=>$id,'field'=>$f,'issue'=>'missing editor'];if(($fields[$f]['state']??'')!=='value')continue;foreach($fields[$f]['value'] as $term){if(!preg_match('/<option\b[^>]*value="'.(int)$term.'"[^>]*selected[^>]*>/',$html))$issues[]=['id'=>$id,'field'=>$f,'issue'=>'saved value not selected'];}}
}
WP_CLI::log(wp_json_encode(['checked'=>5,'issues'=>$issues],JSON_PRETTY_PRINT));if($issues)WP_CLI::error('Editor verification failed');
