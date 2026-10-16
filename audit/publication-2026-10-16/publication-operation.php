<?php
if (!defined('WP_CLI')) { exit; }
$apply=in_array('apply',$args??[],true);
if(!current_user_can('chidemoon_manage_affiliate')||!current_user_can('publish_products'))throw new RuntimeException('authorized editor required');
function pub_norm($s){return str_replace(['ي','ك',"\u{200c}"],['ی','ک',' '],$s);}
function pub_term($name,$tax,$slug,$apply){$t=get_term_by('name',$name,$tax);if($t)return (int)$t->term_id;if(!$apply)return $name;$r=wp_insert_term($name,$tax,['slug'=>$slug]);if(is_wp_error($r))throw new RuntimeException($r->get_error_message());return (int)$r['term_id'];}
function pub_field($names,$tax,$url,$apply){if(!$names)return ['state'=>'unknown','evidence'=>[$url]];$ids=[];foreach(array_unique($names) as $n){$ids[]=pub_term($n,$tax,'review-'.substr(hash('sha256',$tax.':'.$n),0,12),$apply);}return ['state'=>'value','value'=>$ids,'evidence'=>[$url]];}
function pub_classify($title){
$t=pub_norm($title);$cat=null;$rooms=[];
if(preg_match('/چراغ مطالعه/u',$t)){$cat='چراغ مطالعه';$rooms=['اتاق کار','گوشه مطالعه'];}
elseif(preg_match('/روتختی|سرویس خواب/u',$t)){$cat='روتختی';$rooms=['اتاق خواب'];}
elseif(preg_match('/فرش|قالیچه|پتو فرش/u',$t)){$cat=preg_match('/دستبافت/u',$t)?'فرش دستبافت':(preg_match('/فرشینه|مخمل|تدی|نمدی/u',$t)?'فرش و زیر انداز':'فرش ماشینی');$rooms=preg_match('/کودک|نوزاد|پرنسس|دخترانه/u',$t)?['اتاق کودک']:(preg_match('/آشپزخانه/u',$t)?['آشپزخانه']:(preg_match('/راهرو|کناره|پادری/u',$t)?['ورودی خانه']:['پذیرایی و نشیمن']));}
elseif(preg_match('/بخاری|هیتر|کرسی|شوفاژ|والور/u',$t)){$cat='بخاری و بخاری برقی';$rooms=['پذیرایی و نشیمن'];}
elseif(preg_match('/کولر/u',$t)){$cat='تهویه، سرمایش و گرمایش';$rooms=['پذیرایی و نشیمن'];}
elseif(preg_match('/قاشق|چنگال|چاپستیک|سرویس چاقو/u',$t)){$cat='سرویس قاشق، کارد و چنگال';$rooms=['آشپزخانه','ناهارخوری'];}
elseif(preg_match('/اسپرسو|اسپرسو|قهوه|قهوه|چای ساز|چایساز|چای ساز|سماور|پرسو|ایروپرس/u',$t)){$cat='نوشیدنی ساز';$rooms=['آشپزخانه'];}
elseif(preg_match('/غذاساز|غذا ساز|خردکن|خرد کن|سالادساز|سالاد ساز|رندبرقی/u',$t)){$cat='آماده سازی غذا';$rooms=['آشپزخانه'];}
elseif(preg_match('/ماهی تابه|قابلمه|دیزی/u',$t)){$cat='ظروف پخت و پز';$rooms=['آشپزخانه'];}
elseif(preg_match('/محافظ.*روغن/u',$t)){$cat='ابزار و نظم‌دهنده آشپزخانه';$rooms=['آشپزخانه'];}
elseif(preg_match('/سینی/u',$t)){$cat='سینی';$rooms=['آشپزخانه','ناهارخوری'];}
elseif(preg_match('/بانکه|ادویه|حبوبات|پاسماوری|جابرنجی|جا برنجی|جا نونی/u',$t)){$cat='بانکه و جای ادویه';$rooms=['آشپزخانه'];}
elseif(preg_match('/چای خوری/u',$t)){$cat='فنجون، قندون و سرویس چای‌خوری';$rooms=['آشپزخانه','ناهارخوری'];}
elseif(preg_match('/پذیرایی|رولت خوری/u',$t)){$cat='ظروف سرو و پذیرایی';$rooms=['ناهارخوری'];}
elseif(preg_match('/تابلو/u',$t)){$cat='تابلو و دکور دیوار';$rooms=['پذیرایی و نشیمن'];}
elseif(preg_match('/ساعت دیواری/u',$t)){$cat='ساعت دیواری';$rooms=['پذیرایی و نشیمن'];}
elseif(preg_match('/شمعدان/u',$t)){$cat='شمعدان و جاشمعی';$rooms=['پذیرایی و نشیمن'];}
elseif(preg_match('/سطل.*دستمال/u',$t)){$cat='سطل و جادستمال';$rooms=['پذیرایی و نشیمن'];}
elseif(preg_match('/جینجر جار/u',$t)){$cat='ظروف دکوراتیو';$rooms=['پذیرایی و نشیمن'];}
$styles=[];foreach(['مینیمال'=>'مینیمال','بوهو'=>'بوهو','اسکاندیناوی'=>'اسکاندیناوی','روستیک'=>'روستیک','نئوکلاسیک'=>'نئوکلاسیک','کلاسیک'=>'کلاسیک','مدرن'=>'مدرن','سنتی'=>'سنتی'] as $k=>$v){if(mb_strpos($t,$k)!==false)$styles[]=$v;}
return [$cat,$rooms,array_unique($styles)];
}
$report=['apply'=>$apply,'published'=>[],'blocked'=>[],'categories'=>[],'errors'=>[]];
foreach(get_posts(['post_type'=>'product','post_status'=>['publish','draft'],'numberposts'=>-1,'orderby'=>'ID','order'=>'ASC']) as $p){
$id=$p->ID;$x=wc_get_product($id);$url=(string)get_post_meta($id,Chidemoon_Core_Affiliate::META_SOURCE_URL,true);$identity=get_post_meta($id,Chidemoon_Core_Source_Importer::META_IDENTITY,true);
[$category,$rooms,$styles]=pub_classify($p->post_title);
$reasons=[];if(!$identity&&$id!==443)$reasons[]='missing_source_identity';if(!Chidemoon_Core_Source_Contract::https_url($url)||parse_url($url,PHP_URL_HOST)==='chidemoon.com')$reasons[]='missing_merchant_source';if(!$category)$reasons[]='category_unresolved';if($id===721)$reasons[]='duplicate_of_706';if(get_post_meta($id,'_chidemoon_source_withdrawn',true)==='1')$reasons[]='source_withdrawn';
if($reasons){$report['blocked'][]=['id'=>$id,'title'=>$p->post_title,'reasons'=>$reasons];continue;}
$content=json_decode((string)get_post_meta($id,'_chidemoon_source_content',true),true)?:[];
$fields=[];$fields['category']=pub_field([$category],'product_cat',$url,$apply);$fields['room']=pub_field($rooms,'chidemoon_room',$url,$apply);$fields['style']=pub_field($styles,'chidemoon_style',$url,$apply);
$colorNames=[];$materialNames=[];$title=pub_norm($p->post_title);
foreach(['سفید'=>'سفید','مشکی'=>'مشکی','سیاه'=>'مشکی','قرمز'=>'قرمز','لاکی'=>'لاکی','آبی'=>'آبی','سبز'=>'سبز','کرم'=>'کرم','کرمی'=>'کرم','قهوه ای'=>'قهوه‌ای','صورتی'=>'صورتی','بژ'=>'بژ','نقره ای'=>'نقره‌ای','طلایی'=>'طلایی','فیلی'=>'فیلی','طوسی'=>'طوسی','سرمه ای'=>'سرمه‌ای','بنفش'=>'بنفش','فیروزه ای'=>'فیروزه‌ای','زرشکی'=>'زرشکی','گلبهی'=>'گلبهی','دودی'=>'دودی'] as $word=>$name){if(preg_match('/(?<![\p{L}])'.preg_quote($word,'/').'(?![\p{L}])/u',$title))$colorNames[]=$name;}
foreach(['مخمل'=>'مخمل','پشم'=>'پشم','بامبو'=>'بامبو','استیل'=>'استیل','سرامیکی'=>'سرامیک','شیشه ای'=>'شیشه','چوبی'=>'چوب','پلاستیکی'=>'پلاستیک','پلی پروپیلن'=>'پلی‌پروپیلن','اکرولیک'=>'اکریلیک','آکریلیک'=>'اکریلیک','نمدی'=>'نمد','برنز'=>'برنز','سنگی'=>'سنگ'] as $word=>$name){if(mb_strpos($title,$word)!==false)$materialNames[]=$name;}
// Explicit source labels can supply facts; advertising text is never treated as a specification.
$desc=pub_norm(str_replace('\\n',"\n",wp_strip_all_tags($content['description']??'')));
if(preg_match_all('/(?:جنس(?:\s+(?:بدنه|پرز|نخ تار|نخ پود|نخ خاب))?)\s*[:：]\s*([^\n]{1,100})/u',$desc,$m)){
foreach($m[1] as $line){foreach(['پشم'=>'پشم','مخمل'=>'مخمل','بامبو'=>'بامبو','استیل'=>'استیل','سرامیک'=>'سرامیک','شیشه'=>'شیشه','چوب'=>'چوب','پلاستیک'=>'پلاستیک','پلی پروپیلن'=>'پلی‌پروپیلن','اکرولیک'=>'اکریلیک','آکریلیک'=>'اکریلیک','کتان'=>'کتان','فلز'=>'فلز'] as $word=>$name){if(mb_strpos($line,$word)!==false)$materialNames[]=$name;}}
}
$fields['color']=pub_field($colorNames,'pa_color',$url,$apply);$fields['material']=pub_field($materialNames,'pa_material',$url,$apply);
$report['categories'][$category]=($report['categories'][$category]??0)+1;
if(!$apply){$report['published'][]=['id'=>$id,'category'=>$category,'fields'=>$fields,'needsImage'=>!has_post_thumbnail($id),'identityMigration'=>$id===443];continue;}
try{
if(!metadata_exists('post',$id,'_chidemoon_pre_publication_20261016'))add_post_meta($id,'_chidemoon_pre_publication_20261016',wp_slash(['status'=>$p->post_status,'review'=>$x->get_meta(Chidemoon_Core_Affiliate::META_REVIEW_STATE),'visibility'=>$x->get_catalog_visibility(),'enrichment'=>Chidemoon_Core_Enrichment::read($id),'sourceUrl'=>$url,'actor'=>get_current_user_id(),'at'=>gmdate('c')]),true);
if($id===443&&!$identity){Chidemoon_Core_Reconciliation::reconcile_identity(['productId'=>443,'identity'=>['provider'=>'digikala','store'=>'digikala.com','product'=>'563439','variant'=>null],'sourceUrl'=>$url,'expectedModifiedAt'=>$p->post_modified_gmt,'approved'=>true],true);}
if(!has_post_thumbnail($id)){
require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/image.php';
foreach($content['images']??[] as $image){$r=media_sideload_image($image,$id,$p->post_title,'id');if(!is_wp_error($r)){set_post_thumbnail($id,$r);break;}}
}
if(!has_post_thumbnail($id))throw new RuntimeException('image_unavailable');
$existing=Chidemoon_Core_Enrichment::read($id);foreach($existing as $f=>$v){if(isset($v['approvedBy']))unset($fields[$f]);}
if($fields)Chidemoon_Core_Enrichment::apply($id,$fields,Chidemoon_Core_Enrichment::token($id),get_current_user_id());
$x=wc_get_product($id);
if(!Chidemoon_Core_Source_Contract::https_url((string)$x->get_meta(Chidemoon_Core_Affiliate::META_AFFILIATE_URL,true))){$x->update_meta_data(Chidemoon_Core_Affiliate::META_AFFILIATE_URL,$url);$x->set_product_url($url);$x->save();}
$issues=Chidemoon_Core_Enrichment::readiness($id);if($issues)throw new RuntimeException('not_ready:'.implode(',',$issues));
$x=wc_get_product($id);$x->update_meta_data(Chidemoon_Core_Affiliate::META_REVIEW_STATE,'reviewed');$x->save();
$x=wc_get_product($id);$x->set_catalog_visibility('visible');$x->set_status('publish');$x->save();
clean_post_cache($id);wc_delete_product_transients($id);$x=wc_get_product($id);if(get_post_status($id)!=='publish'||!Chidemoon_Core_Affiliate::is_publicly_eligible($x))throw new RuntimeException('publish_verification_failed');
$report['published'][]=$id;WP_CLI::log('published '.$id);
}catch(Throwable $e){$report['errors'][]=['id'=>$id,'error'=>$e->getMessage()];WP_CLI::warning($id.': '.$e->getMessage());}
}
if($apply){update_option('_chidemoon_publication_report_20261016',$report,false);}
echo 'PUBLICATION_REPORT '.wp_json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;