<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
$out=[];
foreach(['header','product-archive','product-single'] as $key){$p=Chidemoon_Core_Elementor_Workspace::template($key);if($p)$out[$key]=['id'=>$p->ID,'nodes'=>json_decode(get_post_meta($p->ID,'_elementor_data',true),true)];}
WP_CLI::log(wp_json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
