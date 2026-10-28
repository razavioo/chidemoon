<?php
/** Update the homepage promise and route illustrations using existing site photography. */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_options')) exit;
$id = (int) get_option('page_on_front');
$raw = get_post_meta($id, '_elementor_data', true);
$nodes = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
$image = static function (int $product): string {
    $src = wp_get_attachment_image_url(get_post_thumbnail_id($product), 'medium_large');
    if (!$src) WP_CLI::error('Product image missing: ' . $product);
    return esc_url($src);
};
$lampA = $image(443);
$lampB = $image(447);
$counts = ['hero' => 0, 'discovery' => 0, 'routes' => 0];
$walk = static function (array &$items, string $context = '') use (&$walk, &$counts, $lampA, $lampB): void {
    foreach ($items as &$node) {
        $classes = explode(' ', $node['settings']['css_classes'] ?? '');
        $nodeContext = $context;
        if (in_array('ch-home-hero', $classes, true)) { $nodeContext = 'hero'; ++$counts['hero']; }
        if (in_array('ch-discovery', $classes, true)) {
            ++$counts['discovery'];
            foreach ($node['elements'] as &$child) {
                if (($child['widgetType'] ?? '') === 'heading') $child['settings']['title'] = 'برای خانه‌ات، راحت‌تر تصمیم بگیر';
                if (($child['widgetType'] ?? '') === 'text-editor') $child['settings']['editor'] = '<p>از ایده تا انتخاب مطمئن</p>';
            }
            unset($child);
        }
        if ($nodeContext === 'hero') {
            if (($node['widgetType'] ?? '') === 'heading') $node['settings']['title'] = 'خانه‌ات را آگاهانه بچین';
            if (str_contains($node['settings']['_css_classes'] ?? '', 'ch-intro-copy')) {
                $node['settings']['editor'] = '<p>چیدمون کمکت می‌کند برای خانه‌ات راحت‌تر تصمیم بگیری؛ از چیدمان‌ها ایده بگیر، با راهنماها انتخاب‌هایت را بشناس و محصولات را کنار هم مقایسه کن.</p>';
            }
        }
        if (in_array('ch-route', $classes, true)) {
            $index = $counts['routes']++;
            $copy = [
                ['چیدمان‌های قابل خرید', 'فضا را یک‌جا ببین؛ وسایل و ترکیب‌هایش را بشناس و برای خانه‌ات انتخاب کن.', 'دیدن چیدمان‌ها'],
                ['راهنمای انتخاب', 'بدان قبل از خرید، کدام اندازه، جنس و کاربرد برای تو مهم‌تر است.', 'خواندن راهنماها'],
                ['مقایسه محصولات', 'دو گزینه را کنار هم ببین و تفاوت‌هایشان را برای انتخاب روشن‌تر بشناس.', 'دیدن مقایسه‌ها'],
            ][$index] ?? null;
            if (!$copy) WP_CLI::error('Unexpected extra route');
            foreach ($node['elements'] as &$child) {
                $type = $child['widgetType'] ?? '';
                if ($type === 'heading') $child['settings']['title'] = $copy[0];
                if ($type === 'text-editor') $child['settings']['editor'] = '<p>' . $copy[1] . '</p>';
                if ($type === 'button') $child['settings']['text'] = $copy[2];
                if ($type !== 'image') continue;
                $scene = esc_url($child['settings']['image']['url'] ?? '');
                if (!$scene) WP_CLI::error('Route scene missing');
                if ($index === 0) {
                    $html = '<figure class="ch-route-art ch-route-art--look" aria-label="چیدمان اتاق با نشانه‌های انتخاب وسایل"><img src="' . $scene . '" alt="چیدمان گوشه مطالعه با مبل، چراغ و میز کنار مبل" loading="lazy"><span class="ch-route-art-pin ch-route-art-pin--chair" aria-hidden="true">+</span><span class="ch-route-art-pin ch-route-art-pin--lamp" aria-hidden="true">+</span><span class="ch-route-art-pin ch-route-art-pin--table" aria-hidden="true">+</span><figcaption>یک چیدمان، چند انتخاب هماهنگ</figcaption></figure>';
                } elseif ($index === 1) {
                    $html = '<figure class="ch-route-art ch-route-art--guide" aria-label="راهنمای انتخاب با بررسی اندازه، جنس و کاربرد"><img src="' . $scene . '" alt="فضای کار خانگی برای بررسی انتخاب وسایل" loading="lazy"><figcaption><strong>قبل از خرید، بررسی کن</strong><span>✓ اندازه مناسب فضا</span><span>✓ جنس و دوام</span><span>✓ کاربرد روزمره</span></figcaption></figure>';
                } else {
                    $html = '<figure class="ch-route-art ch-route-art--compare" aria-label="مقایسه چراغ مطالعه رومیزی و گیره‌ای"><div><img src="' . $lampA . '" alt="چراغ مطالعه رومیزی کریم‌زاده EN-101" loading="lazy"><span>رومیزی</span></div><span class="ch-route-art-versus" aria-hidden="true">↔</span><div><img src="' . $lampB . '" alt="چراغ مطالعه گیره‌ای C801" loading="lazy"><span>گیره‌ای</span></div><figcaption>دو محصول؛ تفاوت‌ها در یک نگاه</figcaption></figure>';
                }
                $child['widgetType'] = 'html';
                $child['settings'] = ['html' => $html, '_css_classes' => 'ch-route-media ch-route-visual'];
            }
            unset($child);
        }
        if (!empty($node['elements'])) $walk($node['elements'], $nodeContext);
    }
};
$walk($nodes);
if ($counts !== ['hero' => 1, 'discovery' => 1, 'routes' => 3]) WP_CLI::error('Unexpected homepage structure');
if (!in_array('apply', $args ?? [], true)) { WP_CLI::log('Validated homepage copy and three route graphics'); return; }
if (!add_post_meta($id, '_chidemoon_before_decision_routes_20261025', wp_slash($raw), true)) WP_CLI::error('Backup failed or already exists');
$doc = \Elementor\Plugin::$instance->documents->get($id, false);
if (!$doc || false === $doc->save(['elements' => $nodes])) WP_CLI::error('Elementor save failed');
\Elementor\Plugin::$instance->files_manager->clear_cache();
wp_cache_flush();
WP_CLI::success('Homepage decision copy and route graphics updated');
