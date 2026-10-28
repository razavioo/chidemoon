<?php
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options'))exit;
global $wpdb;
WP_CLI::log(wp_json_encode(['product'=>443,'clicks'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}chidemoon_clicks WHERE product_id=443"),'lastClick'=>$wpdb->get_row("SELECT product_id,merchant_host,clicked_at FROM {$wpdb->prefix}chidemoon_clicks WHERE product_id=443 ORDER BY id DESC LIMIT 1",ARRAY_A)]));
