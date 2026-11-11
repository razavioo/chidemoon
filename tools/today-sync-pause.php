<?php
if(!defined('WP_CLI')||!current_user_can('manage_woocommerce'))exit(1);
$settings=get_option('chidemoon_catalog_settings');
if(in_array('restore',$args??[],true)){$saved=get_option('chidemoon_sync_before_import_20261105');if(is_array($saved)){$settings['enabled']=$saved['enabled'];update_option('chidemoon_catalog_settings',$settings);echo 'restored';}}
else {add_option('chidemoon_sync_before_import_20261105',['enabled'=>$settings['enabled']??false],'','no');$settings['enabled']=false;update_option('chidemoon_catalog_settings',$settings);echo 'paused';}
