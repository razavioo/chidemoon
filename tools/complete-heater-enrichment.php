<?php
if(!defined('WP_CLI')||!WP_CLI)exit;
$term=get_term_by('name','گرمایش فضا','pa_features');if(!$term)WP_CLI::error('Missing reviewed vocabulary');
$result=[];
foreach([765,839,866,2738] as $id){$p=wc_get_product($id);$title=$p->get_name();if(!preg_match('/والور نفتی|شوفاژ برقی|کرسی برقی/u',$title)||get_post_status($id)!=='publish')WP_CLI::error('Identity mismatch');$old=Chidemoon_Core_Enrichment::read($id);if(!empty($old['features']['value']))WP_CLI::error('Existing features');Chidemoon_Core_Enrichment::apply($id,['features'=>['state'=>'value','value'=>[(int)$term->term_id],'evidence'=>['کاربرد گرمایشی بر اساس نوع محصول: '.$title],'confidence'=>.97]],Chidemoon_Core_Enrichment::token($id),get_current_user_id());wc_delete_product_transients($id);$result[]=['id'=>$id,'features'=>Chidemoon_Core_Enrichment::display_field('features',Chidemoon_Core_Enrichment::read($id)['features'])];}
WP_CLI::log(wp_json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
