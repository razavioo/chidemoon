<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$product_id  = trim( (string) ( $attributes['productId']   ?? '' ) );
$product_name= trim( (string) ( $attributes['productName'] ?? '' ) );
$heading     = trim( (string) ( $attributes['heading']     ?? '' ) );
$score       = (float) ( $attributes['score']      ?? 8.0 );
$score_max   = (int)   ( $attributes['scoreMax']   ?? 10 );
$score_max   = in_array( $score_max, array( 5, 10 ), true ) ? $score_max : 10;
$score       = max( 0.0, min( (float) $score_max, $score ) );
$score_label = trim( (string) ( $attributes['scoreLabel']  ?? '' ) );
$verdict     = trim( (string) ( $attributes['verdict']     ?? '' ) );
$show_stars  = ! isset( $attributes['showStars'] ) || ! empty( $attributes['showStars'] );
$show_cta    = ! empty( $attributes['showCta'] );
$cta_text    = Kalahamoon_Link_Builder::public_cta_label( $attributes['ctaText'] ?? '' );
$show_schema = ! isset( $attributes['showSchema'] ) || ! empty( $attributes['showSchema'] );

// Parse criteria: each line is "Label:Score" e.g. "قیمت:8.5"
$criteria_raw  = (string) ( $attributes['criteria'] ?? '' );
$criteria_list = array();
foreach ( preg_split( '/[\r\n]+/', $criteria_raw ) as $line ) {
	$line = trim( $line );
	if ( '' === $line ) continue;
	$parts = explode( ':', $line, 2 );
	if ( count( $parts ) === 2 ) {
		$label    = trim( $parts[0] );
		$raw_score = trim( $parts[1] );
		$crit_val = (float) $raw_score;
		if ( $label !== '' && is_numeric( $raw_score ) ) {
			$crit_val = max( 0.0, min( (float) $score_max, $crit_val ) );
			$criteria_list[] = array( 'label' => $label, 'score' => $crit_val );
		}
	}
}

// Resolve product for CTA
$product  = $product_id ? Kalahamoon_Product_Cache::get_for_public_render( $product_id ) : null;
$destination = $product ? Kalahamoon_Link_Builder::resolve_product_destination( $product ) : array( 'url' => '', 'isAffiliate' => false, 'linkId' => '' );
$cta_url     = $destination['url'];
$link_attrs  = Kalahamoon_Link_Builder::public_link_attributes( $destination );
$item_name = $product ? $product['title'] : ( $product_name ?: __( 'محصول', 'kalahamoon' ) );

// Score percentage for bar/ring
$score_pct = round( ( $score / $score_max ) * 100 );

// Colour band: <60% red-ish, 60-80% amber, >80% green
$color_class = $score_pct >= 80 ? 'kalahamoon-rating-high' : ( $score_pct >= 60 ? 'kalahamoon-rating-mid' : 'kalahamoon-rating-low' );

// Star rendering (out of 5, scale from scoreMax)
$stars_out_of_5 = round( ( $score / $score_max ) * 5 * 2 ) / 2; // half-star precision

if ( ! function_exists( 'kalahamoon_render_stars' ) ) :
function kalahamoon_render_stars( float $rating ): string {
	$html = '<span class="kalahamoon-stars" aria-hidden="true">';
	for ( $i = 1; $i <= 5; $i++ ) {
		if ( $rating >= $i ) {
			$html .= '<span class="kalahamoon-star kalahamoon-star-full">★</span>';
		} elseif ( $rating >= $i - 0.5 ) {
			$html .= '<span class="kalahamoon-star kalahamoon-star-half">½</span>';
		} else {
			$html .= '<span class="kalahamoon-star kalahamoon-star-empty">☆</span>';
		}
	}
	$html .= '</span>';
	return $html;
}
endif;

// Visible score numerals follow the Persian-numerals setting (e.g. ۸٫۰ / ۱۰)
// so a Farsi review doesn't show Latin digits next to Persian copy.
$display_score = Kalahamoon_RTL::format_number( $score, null, 1 );
$display_max   = Kalahamoon_RTL::to_persian_digits( (string) $score_max );

$display_label = $score_label ?: ( $score_pct >= 80
	? __( 'عالی', 'kalahamoon' )
	: ( $score_pct >= 60 ? __( 'خوب', 'kalahamoon' ) : __( 'متوسط', 'kalahamoon' ) ) );
?>

<?php if ( $show_schema && $item_name ) : ?>
<script type="application/ld+json">
<?php echo wp_json_encode( array(
	'@context'    => 'https://schema.org',
	'@type'       => 'Review',
	'name'        => $heading ?: sprintf( __( 'نقد %s', 'kalahamoon' ), $item_name ),
	'reviewBody'  => $verdict ?: null,
	'reviewRating' => array(
		'@type'       => 'Rating',
		'ratingValue' => $score,
		'bestRating'  => $score_max,
		'worstRating' => 0,
	),
	'itemReviewed' => array(
		'@type' => 'Product',
		'name'  => $item_name,
	),
), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); ?>
</script>
<?php endif; ?>

<div <?php echo get_block_wrapper_attributes( array( 'class' => 'kalahamoon-rating-box ' . $color_class ) ); ?>>

	<?php if ( $heading ) : ?>
		<h3 class="kalahamoon-rating-heading"><?php echo esc_html( $heading ); ?></h3>
	<?php endif; ?>

	<div class="kalahamoon-rating-scoreline">
		<div class="kalahamoon-rating-badge <?php echo esc_attr( $color_class ); ?>">
			<span class="kalahamoon-rating-number"><?php echo esc_html( $display_score ); ?></span>
			<span class="kalahamoon-rating-denom">/<?php echo esc_html( $display_max ); ?></span>
		</div>

		<div class="kalahamoon-rating-meta">
			<?php if ( $show_stars ) : ?>
				<?php echo kalahamoon_render_stars( $stars_out_of_5 ); ?>
			<?php endif; ?>
			<span class="kalahamoon-rating-label"><?php echo esc_html( $display_label ); ?></span>
		</div>
	</div>

	<?php if ( ! empty( $criteria_list ) ) : ?>
	<ul class="kalahamoon-rating-criteria" role="list">
		<?php foreach ( $criteria_list as $crit ) :
			$pct = round( ( $crit['score'] / $score_max ) * 100 );
			$bar_class = $pct >= 80 ? 'kalahamoon-rating-high' : ( $pct >= 60 ? 'kalahamoon-rating-mid' : 'kalahamoon-rating-low' );
		?>
		<li class="kalahamoon-rating-criterion">
			<span class="kalahamoon-criterion-label"><?php echo esc_html( $crit['label'] ); ?></span>
			<div class="kalahamoon-criterion-track" role="progressbar"
				aria-valuenow="<?php echo esc_attr( $crit['score'] ); ?>"
				aria-valuemin="0" aria-valuemax="<?php echo esc_attr( $score_max ); ?>"
				aria-label="<?php echo esc_attr( $crit['label'] ) . ': ' . esc_attr( $crit['score'] ) . '/' . esc_attr( $score_max ); ?>">
				<div class="kalahamoon-criterion-fill <?php echo esc_attr( $bar_class ); ?>" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
			</div>
			<span class="kalahamoon-criterion-score"><?php echo esc_html( Kalahamoon_RTL::format_number( $crit['score'], null, 1 ) ); ?></span>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>

	<?php if ( $verdict ) : ?>
	<p class="kalahamoon-rating-verdict"><?php echo esc_html( $verdict ); ?></p>
	<?php endif; ?>

	<?php if ( $show_cta && $cta_url ) : ?>
	<div class="kalahamoon-rating-cta">
		<a href="<?php echo esc_url( $cta_url ); ?>"
			class="kalahamoon-cta-button <?php echo esc_attr( $link_attrs['class'] ); ?> kalahamoon-cta-medium"
			target="_blank"
			rel="<?php echo esc_attr( $link_attrs['rel'] ); ?>"
			data-product-id="<?php echo esc_attr( $product_id ); ?>"
			data-link-id="<?php echo esc_attr( $link_attrs['linkId'] ); ?>"
			data-link-kind="<?php echo esc_attr( $link_attrs['kind'] ); ?>"
			data-block-type="rating-box">
			<?php echo esc_html( $cta_text ); ?>
		</a>
	</div>
	<?php endif; ?>

</div>
