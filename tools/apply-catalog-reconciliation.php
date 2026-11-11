<?php
/** Run only through authenticated server CLI, after scoped SQL and database backups. */
require '/var/www/html/wp-load.php';
$plan=json_decode(file_get_contents('/tmp/reconciliation-plan.json'),true,512,JSON_THROW_ON_ERROR);
$config=get_option('chidemoon_catalog_settings',[]);
$snapshot=['version'=>'v2','organization'=>['id'=>$config['organizationId'],'slug'=>$config['organizationSlug']],'snapshot'=>['id'=>'merchant-audit-20261107','revision'=>hash('sha256',json_encode($plan)),'generatedAt'=>gmdate('c')],'items'=>$plan['items'],'withdrawals'=>[]];
$dry=in_array('--dry-run',$argv,true);
$report=Chidemoon_Core_Source_Importer::import_snapshot($snapshot,$dry,$config['organizationSlug']);
if($report['failed']){echo json_encode($report);exit(1);}
if(!$dry){
 foreach($plan['legacy'] as $item){
  $p=wc_get_product($item['id']);$obs=$item['observations'];$price=$obs['price'];
  $current=Chidemoon_Core_Source_Importer::convert_price($price['current'],'IRR',get_woocommerce_currency());
  $previous=$price['previous']===null?null:Chidemoon_Core_Source_Importer::convert_price($price['previous'],'IRR',get_woocommerce_currency());
  $p->set_regular_price($previous??$current);$p->set_sale_price($previous===null?'':$current);$p->set_price($current);$p->set_date_on_sale_from(null);$p->set_date_on_sale_to(null);
  $p->update_meta_data(Chidemoon_Core_Source_Importer::META_OBSERVATIONS,wp_json_encode($obs));
  $p->update_meta_data('_chidemoon_price_observed_unix',strtotime($price['observedAt']));
  $p->update_meta_data(Chidemoon_Core_Affiliate::META_SOURCE_CHECKED,$price['observedAt']);
  $p->update_meta_data('_chidemoon_availability_status',$obs['availability']['status']);
  $p->update_meta_data('_chidemoon_availability_observed_unix',strtotime($obs['availability']['observedAt']));$p->save();
 }
 foreach($plan['remove'] as $id){$p=wc_get_product($id);$p->update_meta_data('_chidemoon_merchant_audit_20261107',['priorStatus'=>$p->get_status(),'priorVisibility'=>$p->get_catalog_visibility(),'reason'=>'merchant_not_saleable','at'=>gmdate('c')]);$p->set_status('draft');$p->set_catalog_visibility('hidden');$p->save();}
 wc_delete_product_transients();
}
echo json_encode(['dryRun'=>$dry,'updated'=>$report['updated'],'legacy'=>count($plan['legacy']),'removed'=>count($plan['remove']),'failed'=>$report['failed']])."\n";
