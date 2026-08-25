<?php
/**
 * Server-side render for kalahamoon/ai-compare block.
 * Reads the stored AI comparison JSON from attributes and renders static HTML.
 * No runtime AI calls — the heavy lifting happens in the editor when the user
 * clicks "Generate" and the result is persisted with the post.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$comparison_raw  = $attributes['comparison'] ?? '';
$products_raw    = $attributes['products'] ?? '';
$product_ids_raw = $attributes['productIds'] ?? '';
$cta_text        = Kalahamoon_Link_Builder::public_cta_label( $attributes['ctaText'] ?? '' );

$show_heads      = ! isset( $attributes['showHeads'] )    || ! empty( $attributes['showHeads'] );
$show_criteria   = ! isset( $attributes['showCriteria'] ) || ! empty( $attributes['showCriteria'] );
$show_proscons   = ! isset( $attributes['showProsCons'] ) || ! empty( $attributes['showProsCons'] );
$show_verdict    = ! isset( $attributes['showVerdict'] )  || ! empty( $attributes['showVerdict'] );

if ( empty( $comparison_raw ) || empty( $products_raw ) ) {
	echo Kalahamoon_Placeholder::editor_hint(
		__( 'هنوز مقایسه‌ای تولید نشده', 'kalahamoon' ),
		__( 'دو محصول انتخاب و روی «تولید با AI» کلیک کنید.', 'kalahamoon' )
	);
	return;
}

$comparison = json_decode( $comparison_raw, true );
$products   = json_decode( $products_raw, true );

if ( is_array( $products ) ) {
	$products = array_values( array_filter( $products, 'is_array' ) );
}

if ( ! is_array( $comparison ) || ! is_array( $products ) || count( $products ) < 2 ) {
	echo Kalahamoon_Placeholder::editor_hint(
		__( 'داده‌های مقایسه ناقص است', 'kalahamoon' ),
		__( 'به نظر می‌رسد خروجی AI معتبر نیست. دوباره با دو محصول سالم تولید کنید.', 'kalahamoon' )
	);
	return;
}

// Re-hydrate live product data from the local cache so prices/images are current,
// falling back to the snapshot stored when the comparison was generated.
$product_ids = array_values( array_filter( array_map( 'trim', explode( ',', $product_ids_raw ) ) ) );
$live        = array();
foreach ( $product_ids as $pid ) {
	$p = Kalahamoon_Product_Cache::get_for_public_render( $pid );
	if ( $p ) {
		$live[ $pid ] = $p;
	}
}

$p1        = $products[0];
$p2        = $products[1];
$p1_live   = $live[ $p1['id'] ?? '' ] ?? null;
$p2_live   = $live[ $p2['id'] ?? '' ] ?? null;

if ( ! $p1_live || ! $p2_live ) {
	echo Kalahamoon_Placeholder::editor_hint(
		__( 'The comparison products are not ready for publication.', 'kalahamoon' ),
		__( 'Refresh and verify both products before publishing this comparison.', 'kalahamoon' )
	);
	return;
}

$winner_overall = (int) ( $comparison['overallWinner'] ?? 0 );
$winner_overall = in_array( $winner_overall, array( 1, 2 ), true ) ? $winner_overall : 0;

$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'kalahamoon-ai-cmp' ) );

/**
 * Render a single product head cell.
 *
 * @param array      $snapshot Snapshot from AI response (always present).
 * @param array|null $live     Live product from cache (nullable).
 * @param bool       $is_winner Whether this product is the overall winner.
 */
$render_head = function ( $snapshot, $live, $is_winner ) use ( $cta_text ) {
	$image = $live['imageUrl']  ?? $snapshot['imageUrl']  ?? '';
	$title = $live['title']     ?? $snapshot['title']     ?? '';
	$price = ! empty( $live['priceVisible'] ) ? ( $live['price'] ?? 0 ) : 0;
	$currency = $live['currency'] ?? $snapshot['currency'] ?? 'IRR';
	$platform = strtolower( $live['platform'] ?? '' );
	$pid      = $snapshot['id'] ?? ( $live['id'] ?? '' );
	$destination = $live ? Kalahamoon_Link_Builder::resolve_product_destination( $live ) : array( 'url' => '', 'isAffiliate' => false, 'linkId' => '' );
	$url         = $destination['url'];
	$link_attrs  = Kalahamoon_Link_Builder::public_link_attributes( $destination );
	$has_link    = Kalahamoon_Link_Builder::is_clickable_url( $url );
	?>
	<div class="kalahamoon-ai-cmp-head <?php echo $is_winner ? 'is-winner' : ''; ?>">
		<?php if ( $is_winner ) : ?>
			<span class="kalahamoon-ai-cmp-head-badge">🏆 <?php esc_html_e( 'برنده', 'kalahamoon' ); ?></span>
		<?php endif; ?>
		<?php if ( $image ) : ?>
			<img class="kalahamoon-ai-cmp-head-img"
				src="<?php echo esc_url( $image ); ?>"
				alt="<?php echo esc_attr( $title ); ?>"
				loading="lazy" decoding="async"
				onerror="this.hidden=true;this.nextElementSibling.hidden=false" />
			<div class="kalahamoon-ai-cmp-head-img kalahamoon-ai-cmp-head-img--placeholder" aria-hidden="true" hidden>
				<?php echo Kalahamoon_Placeholder::image_fallback_svg( $platform, (string) $title ); ?>
			</div>
		<?php else : ?>
			<div class="kalahamoon-ai-cmp-head-img kalahamoon-ai-cmp-head-img--placeholder" aria-hidden="true">
				<?php echo Kalahamoon_Placeholder::image_fallback_svg( $platform, (string) $title ); ?>
			</div>
		<?php endif; ?>
		<h3 class="kalahamoon-ai-cmp-head-title"><?php echo esc_html( $title ); ?></h3>

		<?php $platform_label = Kalahamoon_RTL::platform_label( $platform ); ?>
		<?php if ( '' !== $platform_label ) : ?>
			<span class="kalahamoon-marketplace-badge kalahamoon-badge-<?php echo esc_attr( $platform ); ?>">
				<?php echo esc_html( $platform_label ); ?>
			</span>
		<?php endif; ?>

		<?php if ( $price > 0 ) : ?>
			<div class="kalahamoon-ai-cmp-head-price">
				<?php echo esc_html( Kalahamoon_RTL::format_price( $price, $currency ) ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $has_link ) : ?>
			<a href="<?php echo esc_url( $url ); ?>"
				class="kalahamoon-cta-button <?php echo esc_attr( $link_attrs['class'] ); ?>"
				target="_blank"
				rel="<?php echo esc_attr( $link_attrs['rel'] ); ?>"
				data-product-id="<?php echo esc_attr( $pid ); ?>"
				data-link-id="<?php echo esc_attr( $link_attrs['linkId'] ); ?>"
				data-link-kind="<?php echo esc_attr( $link_attrs['kind'] ); ?>"
				data-block-type="ai-compare">
				<?php echo esc_html( $cta_text ); ?>
			</a>
		<?php endif; ?>
	</div>
	<?php
};
?>

<section <?php echo $wrapper_attrs; ?>>

	<?php if ( $show_heads ) : ?>
		<div class="kalahamoon-ai-cmp-heads">
			<?php $render_head( $p1, $p1_live, 1 === $winner_overall ); ?>
			<?php $render_head( $p2, $p2_live, 2 === $winner_overall ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $show_criteria && ! empty( $comparison['criteria'] ) && is_array( $comparison['criteria'] ) ) : ?>
	<div class="kalahamoon-ai-cmp-criteria-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'جدول معیارهای مقایسه', 'kalahamoon' ); ?>">
		<table class="kalahamoon-ai-cmp-criteria">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'معیار', 'kalahamoon' ); ?></th>
					<th scope="col"><?php echo esc_html( $p1['title'] ?? 'Product 1' ); ?></th>
					<th scope="col"><?php echo esc_html( $p2['title'] ?? 'Product 2' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $comparison['criteria'] as $c ) :
					if ( ! is_array( $c ) ) continue;
					$name    = $c['name'] ?? '';
					$s1      = $c['product1Score'] ?? '—';
					$s2      = $c['product2Score'] ?? '—';
					$winner  = (int) ( $c['winner'] ?? 0 );
					$expl    = $c['explanation'] ?? '';
				?>
					<tr>
						<td class="kalahamoon-ai-cmp-crit-name">
							<?php echo esc_html( $name ); ?>
							<?php if ( $expl ) : ?>
								<span class="kalahamoon-ai-cmp-crit-expl"><?php echo esc_html( $expl ); ?></span>
							<?php endif; ?>
						</td>
						<td class="kalahamoon-ai-cmp-crit-score <?php echo 1 === $winner ? 'is-winner' : ''; ?>">
							<?php echo esc_html( $s1 ); ?>
							<?php if ( 1 === $winner ) : ?><span aria-hidden="true">✓</span><?php endif; ?>
						</td>
						<td class="kalahamoon-ai-cmp-crit-score <?php echo 2 === $winner ? 'is-winner' : ''; ?>">
							<?php echo esc_html( $s2 ); ?>
							<?php if ( 2 === $winner ) : ?><span aria-hidden="true">✓</span><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<?php if ( $show_proscons ) : ?>
	<div class="kalahamoon-ai-cmp-pc-cols">
		<?php
		$render_pc = function ( $title, $pros, $cons ) {
			?>
			<div class="kalahamoon-ai-cmp-pc-col">
				<h4><?php echo esc_html( $title ); ?></h4>
				<?php if ( is_array( $pros ) && ! empty( $pros ) ) : ?>
					<div class="kalahamoon-ai-cmp-pc-section kalahamoon-ai-cmp-pros">
						<strong>✅ <?php esc_html_e( 'مزایا', 'kalahamoon' ); ?></strong>
						<ul>
							<?php foreach ( $pros as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<?php if ( is_array( $cons ) && ! empty( $cons ) ) : ?>
					<div class="kalahamoon-ai-cmp-pc-section kalahamoon-ai-cmp-cons">
						<strong>❌ <?php esc_html_e( 'معایب', 'kalahamoon' ); ?></strong>
						<ul>
							<?php foreach ( $cons as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
			<?php
		};

		$render_pc( $p1['title'] ?? '', $comparison['product1Pros'] ?? array(), $comparison['product1Cons'] ?? array() );
		$render_pc( $p2['title'] ?? '', $comparison['product2Pros'] ?? array(), $comparison['product2Cons'] ?? array() );
		?>
	</div>
	<?php endif; ?>

	<?php if ( $show_verdict && ! empty( $comparison['verdict'] ) ) : ?>
		<div class="kalahamoon-ai-cmp-verdict">
			<?php if ( $winner_overall ) :
				$winner_title = 1 === $winner_overall ? ( $p1['title'] ?? '' ) : ( $p2['title'] ?? '' );
			?>
				<div class="kalahamoon-ai-cmp-verdict-badge">
					🏆 <?php echo esc_html( sprintf( __( 'انتخاب نهایی: %s', 'kalahamoon' ), $winner_title ) ); ?>
				</div>
			<?php endif; ?>
			<p class="kalahamoon-ai-cmp-verdict-text"><?php echo esc_html( $comparison['verdict'] ); ?></p>
		</div>
	<?php endif; ?>

	<p class="kalahamoon-ai-cmp-footer-note">
		<?php esc_html_e( 'این مقایسه به‌کمک هوش مصنوعی تولید شده است.', 'kalahamoon' ); ?>
	</p>

</section>
