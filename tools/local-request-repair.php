<?php
if (!defined('WP_CLI') || !current_user_can('manage_options')) exit;
$id=(int)get_option('page_on_front'); $data=json_decode(get_post_meta($id,'_elementor_data',true),true);
$copy='ما در چیدمون معتقدیم زیبایی خانه در انتخاب هوشمندانه و چیدمانِ آگاهانه است. چیدمون به عنوان همراه شما، سردرگمی در بازار شلوغ لوازم خانگی را به لذتِ خلق یک فضای دوست داشتنی تبدیل می‌کند. ما به جای شما تحقیق می‌کنیم، بهترین‌ها را بر اساس سبک زندگی و بودجه‌تان فیلتر می‌کنیم و به شما نشان می‌دهیم که هر وسیله، چطور در کنار بقیه اجزای خانه‌تان می‌نشیند. در چیدمون، ما فقط محصول معرفی نمی‌کنیم؛ ما به شما کمک می‌کنیم تا داستانی منحصربه‌فرد برای فضای زندگی‌تان بنویسید.';
$count=0;
$walk=function(&$nodes,$context='')use(&$walk,&$count,$copy){foreach($nodes as &$n){$class=$n['settings']['css_classes']??'';$ctx=$class?:$context;if(($n['widgetType']??'')==='text-editor'&&str_contains($context,'ch-home-action-copy')){$n['settings']['editor']='<p>'.esc_html($copy).'</p>'; $count++;}if(($n['widgetType']??'')==='heading'&&(str_contains($context,'comparison')||($n['id']??'')==='chcomptitle')){$n['settings']['title']='مقایسه محصولات';$count++;}if(!empty($n['elements']))$walk($n['elements'],$ctx);}};
$walk($data);update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($data,JSON_UNESCAPED_UNICODE)));if(class_exists('Elementor\Plugin')) Elementor\Plugin::$instance->files_manager->clear_cache(); wp_cache_flush(); WP_CLI::success('Updated homepage widgets: '.$count);

Chidemoon_Core_Primary_Facets::sync();
$p=get_page_by_path('shop-the-look/room');if($p)wp_update_post(['ID'=>$p->ID,'post_content'=>'[chidemoon_look_group]']);
$p=get_page_by_path('shop-the-look/styles');if($p)wp_update_post(['ID'=>$p->ID,'post_content'=>'[chidemoon_look_group]']);
flush_rewrite_rules();
WP_CLI::success('Primary facets and dedicated pages synchronized.');
