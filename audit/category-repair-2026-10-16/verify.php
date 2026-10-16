<?php
$errors=[];$serving=wc_get_products(['status'=>'publish','limit'=>-1,'category'=>['serving']]);foreach($serving as$p)if(preg_match('/قاشق|چنگال/u',$p->get_name()))$errors[]=['id'=>$p->get_id(),'reason'=>'cutlery_in_serving'];
if((int)get_term(96,'product_cat')->parent!==93)$errors[]='cutlery_parent';if((int)get_term(165,'product_cat')->parent!==114)$errors[]='handmade_carpet_parent';
foreach(wc_get_products(['status'=>'publish','limit'=>-1])as$p){$issues=Chidemoon_Core_Enrichment::readiness($p->get_id());if(!Chidemoon_Core_Affiliate::is_publicly_eligible($p))$issues[]='not_public';if($issues)$errors[]=['id'=>$p->get_id(),'issues'=>$issues];}
$changed=[];foreach([837,770,587,882,883]as$id)$changed[]=['id'=>$id,'cats'=>wp_get_object_terms($id,'product_cat',['fields'=>'names'])];
echo wp_json_encode(['errors'=>$errors,'servingCount'=>count($serving),'changes'=>$changed,'published'=>wp_count_posts('product')->publish],JSON_UNESCAPED_UNICODE)."\n";if($errors)throw new RuntimeException('verification failed');
