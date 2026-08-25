<?php
/**
 * Public product detail route for a policy-approved Kalahamoon cache record.
 * The route is theme-owned; product facts and merchant destinations remain
 * read-only data from the synchronized catalog.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$product = chidemoon_current_public_product();
if ( ! is_array( $product ) ) {
	return;
}

$destination = class_exists( 'Kalahamoon_Link_Builder' )
	? Kalahamoon_Link_Builder::resolve_product_destination( $product )
	: array( 'url' => '', 'isAffiliate' => false, 'linkId' => '' );
$affiliate_url = $destination['url'];
$link_attrs    = class_exists( 'Kalahamoon_Link_Builder' )
	? Kalahamoon_Link_Builder::public_link_attributes( $destination )
	: array( 'class' => 'kalahamoon-product-link', 'rel' => 'noopener', 'linkId' => '', 'kind' => 'direct' );
$price_visible = ! empty( $product['priceVisible'] );
// The upstream projection deliberately removes stale offer prices. Respect that
// decision before touching any legacy listing renderer on the presentation site.
$offers        = $price_visible && class_exists( 'Kalahamoon_Listings' )
	? Kalahamoon_Listings::normalize_public( $product )
	: array();
$specs         = is_array( $product['specs'] ?? null ) ? $product['specs'] : array();
$related       = array();
if ( class_exists( 'Kalahamoon_Product_Cache' ) && '' !== (string) ( $product['category'] ?? '' ) ) {
	$category_products = Kalahamoon_Product_Cache::get_all(
		array(
			'category'     => sanitize_title( (string) $product['category'] ),
			'public_ready' => true,
			'limit'        => 5,
		)
	);
	foreach ( $category_products['items'] ?? array() as $candidate ) {
		if ( is_array( $candidate ) && (string) ( $candidate['id'] ?? '' ) !== (string) $product['id'] ) {
			$related[] = $candidate;
		}
	}
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'chidemoon-product-detail-page' ); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
	<?php block_template_part( 'header' ); ?>
	<main id="chidemoon-main" class="chidemoon-product-detail" tabindex="-1">
		<nav class="chidemoon-product-detail__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'chidemoon-theme' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'chidemoon-theme' ); ?></a>
			<span aria-hidden="true">/</span>
			<a href="<?php echo esc_url( home_url( '/shop/' ) ); ?>"><?php esc_html_e( 'Products', 'chidemoon-theme' ); ?></a>
			<span aria-hidden="true">/</span>
			<span aria-current="page"><?php echo esc_html( $product['title'] ); ?></span>
		</nav>

		<section class="chidemoon-product-detail__hero">
			<div class="chidemoon-product-detail__media">
				<?php if ( '' !== (string) ( $product['imageUrl'] ?? '' ) ) : ?>
					<img src="<?php echo esc_url( $product['imageUrl'] ); ?>" alt="<?php echo esc_attr( $product['title'] ); ?>" decoding="async" fetchpriority="high">
				<?php endif; ?>
			</div>
			<div class="chidemoon-product-detail__summary">
				<p class="chidemoon-product-detail__eyebrow"><?php echo esc_html( $product['brand'] ?: $product['category'] ?: $product['platform'] ); ?></p>
				<h1><?php echo esc_html( $product['title'] ); ?></h1>
				<?php if ( '' !== trim( wp_strip_all_tags( (string) $product['description'] ) ) ) : ?>
					<div class="chidemoon-product-detail__description"><?php echo wp_kses_post( wpautop( $product['description'] ) ); ?></div>
				<?php endif; ?>
				<?php if ( $price_visible && (float) $product['price'] > 0 ) : ?>
					<p class="chidemoon-product-detail__price"><?php echo esc_html( Kalahamoon_RTL::format_price( (float) $product['price'], (string) $product['currency'] ) ); ?></p>
				<?php else : ?>
					<p class="chidemoon-product-detail__freshness"><?php esc_html_e( 'Price is temporarily hidden until the next verified refresh.', 'chidemoon-theme' ); ?></p>
				<?php endif; ?>
				<p class="chidemoon-product-detail__freshness"><?php echo esc_html( 'stale' === ( $product['priceFreshness'] ?? '' ) ? __( 'Price was checked more than 12 hours ago.', 'chidemoon-theme' ) : __( 'Details come from a verified catalog record.', 'chidemoon-theme' ) ); ?></p>
				<div class="chidemoon-product-detail__actions">
					<?php if ( '' !== $affiliate_url ) : ?><a class="chidemoon-btn chidemoon-btn--primary <?php echo esc_attr( $link_attrs['class'] ); ?>" href="<?php echo esc_url( $affiliate_url ); ?>" target="_blank" rel="<?php echo esc_attr( $link_attrs['rel'] ); ?>" data-product-id="<?php echo esc_attr( $product['id'] ); ?>" data-link-id="<?php echo esc_attr( $link_attrs['linkId'] ); ?>" data-link-kind="<?php echo esc_attr( $link_attrs['kind'] ); ?>" data-block-type="product-detail"><?php esc_html_e( 'View product', 'chidemoon-theme' ); ?></a><?php endif; ?>
					<?php if ( is_array( $product['comparisonType'] ?? null ) && '' !== (string) ( $product['comparisonType']['key'] ?? $product['comparisonType']['slug'] ?? '' ) ) : ?><a class="chidemoon-btn" href="<?php echo esc_url( add_query_arg( 'products', rawurlencode( (string) $product['id'] ), home_url( '/compare/' ) ) ); ?>"><?php esc_html_e( 'Compare', 'chidemoon-theme' ); ?></a><?php endif; ?>
				</div>
			</div>
		</section>

		<?php if ( ! empty( $offers ) ) : ?>
		<section class="chidemoon-product-detail__section" aria-labelledby="product-offers">
			<h2 id="product-offers"><?php esc_html_e( 'Available offers', 'chidemoon-theme' ); ?></h2>
			<div class="chidemoon-product-detail__offers">
				<?php foreach ( $offers as $offer ) :
					$offer_destination = class_exists( 'Kalahamoon_Link_Builder' )
						? Kalahamoon_Link_Builder::resolve_direct_destination( $offer['url'] ?? '' )
						: array( 'url' => '', 'isAffiliate' => false, 'linkId' => '' );
					$offer_link_attrs = class_exists( 'Kalahamoon_Link_Builder' )
						? Kalahamoon_Link_Builder::public_link_attributes( $offer_destination )
						: array( 'class' => 'kalahamoon-product-link', 'rel' => 'noopener', 'linkId' => '', 'kind' => 'direct' );
				?>
					<article><div><strong><?php echo esc_html( $offer['seller'] ?: Kalahamoon_RTL::platform_label( $offer['platform'] ) ); ?></strong><span><?php echo esc_html( $offer['inStock'] ? __( 'In stock', 'chidemoon-theme' ) : __( 'Availability not confirmed', 'chidemoon-theme' ) ); ?></span></div><p><?php echo esc_html( Kalahamoon_RTL::format_price( $offer['price'], $offer['currency'] ) ); ?></p><?php if ( '' !== $offer_destination['url'] ) : ?><a class="<?php echo esc_attr( $offer_link_attrs['class'] ); ?>" href="<?php echo esc_url( $offer_destination['url'] ); ?>" target="_blank" rel="<?php echo esc_attr( $offer_link_attrs['rel'] ); ?>" data-product-id="<?php echo esc_attr( $product['id'] ); ?>" data-link-id="<?php echo esc_attr( $offer_link_attrs['linkId'] ); ?>" data-link-kind="<?php echo esc_attr( $offer_link_attrs['kind'] ); ?>" data-block-type="product-detail-offer"><?php esc_html_e( 'View product', 'chidemoon-theme' ); ?></a><?php endif; ?></article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

		<?php if ( ! empty( $specs ) ) : ?>
		<section class="chidemoon-product-detail__section" aria-labelledby="product-specifications">
			<h2 id="product-specifications"><?php esc_html_e( 'Specifications', 'chidemoon-theme' ); ?></h2>
			<dl class="chidemoon-product-detail__specifications">
				<?php foreach ( $specs as $key => $spec ) :
					$label = is_array( $spec ) ? (string) ( $spec['label'] ?? $spec['name'] ?? $key ) : (string) $key;
					$value = is_array( $spec ) ? (string) ( $spec['value'] ?? $spec['text'] ?? '' ) : (string) $spec;
				?>
					<?php if ( '' !== trim( $label ) && '' !== trim( $value ) ) : ?><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd><?php endif; ?>
				<?php endforeach; ?>
			</dl>
		</section>
		<?php endif; ?>

		<?php if ( ! empty( $related ) ) : ?>
		<section class="chidemoon-product-detail__section" aria-labelledby="related-products">
			<h2 id="related-products"><?php esc_html_e( 'Related products', 'chidemoon-theme' ); ?></h2>
			<div class="chidemoon-product-detail__related"><?php foreach ( array_slice( $related, 0, 4 ) as $candidate ) : ?><?php $url = apply_filters( 'kalahamoon_product_public_url', '', $candidate ); ?><article><?php if ( '' !== (string) ( $candidate['imageUrl'] ?? '' ) ) : ?><img src="<?php echo esc_url( $candidate['imageUrl'] ); ?>" alt="" loading="lazy" decoding="async"><?php endif; ?><h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $candidate['title'] ); ?></a></h3><?php if ( ! empty( $candidate['priceVisible'] ) ) : ?><p><?php echo esc_html( Kalahamoon_RTL::format_price( $candidate['price'], $candidate['currency'] ) ); ?></p><?php endif; ?></article><?php endforeach; ?></div>
		</section>
		<?php endif; ?>
	</main>
	<?php block_template_part( 'footer' ); ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
