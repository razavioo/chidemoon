<?php
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_options')) exit;
$id = (int) get_option('page_on_front');
$raw = get_post_meta($id, '_elementor_data', true);
$nodes = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
$found = 0;
$walk = static function (array &$items) use (&$walk, &$found): void {
    foreach ($items as &$node) {
        if (in_array('ch-home-products-section', explode(' ', $node['settings']['css_classes'] ?? ''), true)) {
            $buttons = array_filter($node['elements'], static fn($child) => ($child['widgetType'] ?? '') === 'button');
            $feeds = array_filter($node['elements'], static fn($child) => ($child['widgetType'] ?? '') === 'loop-grid');
            if (count($buttons) !== 1 || count($feeds) !== 1) WP_CLI::error('Unexpected product section structure');
            $button = reset($buttons);
            $children = array_values(array_filter($node['elements'], static fn($child) => ($child['widgetType'] ?? '') !== 'button'));
            foreach ($children as $index => $child) {
                if (($child['widgetType'] ?? '') === 'loop-grid') {
                    array_splice($children, $index, 0, [$button]);
                    break;
                }
            }
            $node['elements'] = $children;
            ++$found;
        } elseif (!empty($node['elements'])) {
            $walk($node['elements']);
        }
    }
};
$walk($nodes);
if ($found !== 1) WP_CLI::error('Expected one product section');
if (!in_array('apply', $args ?? [], true)) { WP_CLI::log('Validated: heading, shop button, product row'); return; }
if (!add_post_meta($id, '_chidemoon_before_product_button_order_20261025', wp_slash($raw), true)) WP_CLI::error('Backup failed or already exists');
$doc = \Elementor\Plugin::$instance->documents->get($id, false);
if (!$doc || false === $doc->save(['elements' => $nodes])) WP_CLI::error('Elementor save failed');
\Elementor\Plugin::$instance->files_manager->clear_cache();
wp_cache_flush();
WP_CLI::success('Shop button moved above product row');
