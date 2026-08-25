<?php
/**
 * Kalahamoon FAQ — server-side render.
 *
 * Uses native <details> for keyboard / SR support. Emits FAQPage JSON-LD
 * when emitSchema=true so the FAQ block can drive Google rich results.
 *
 * @var array $attributes
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$items         = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : array();
$open_first    = ! empty( $attributes['openFirst'] );
$emit_schema   = ! isset( $attributes['emitSchema'] ) || ! empty( $attributes['emitSchema'] );
$heading_level = max( 2, min( 6, (int) ( $attributes['headingLevel'] ?? 3 ) ) );
$heading_tag   = 'h' . $heading_level;

$wrapper = get_block_wrapper_attributes( array( 'class' => 'kalahamoon-faq' ) );

if ( empty( $items ) ) {
	echo '<div ' . $wrapper . '>';
	echo Kalahamoon_Placeholder::editor_hint(
		__( 'هیچ سوالی اضافه نشده', 'kalahamoon' ),
		__( 'از سایدبار بلاک «Add row» را بزنید.', 'kalahamoon' )
	);
	echo '</div>';
	return;
}
?>

<div <?php echo $wrapper; ?>>
	<?php foreach ( $items as $i => $item ) :
		$q = trim( (string) ( $item['q'] ?? '' ) );
		$a = trim( (string) ( $item['a'] ?? '' ) );
		if ( '' === $q ) continue;
		$open = ( 0 === $i && $open_first );
	?>
		<details class="kalahamoon-faq-item" <?php if ( $open ) echo 'open'; ?>>
			<summary class="kalahamoon-faq-question">
				<<?php echo $heading_tag; ?> class="kalahamoon-faq-question-text"><?php echo esc_html( $q ); ?></<?php echo $heading_tag; ?>>
				<span class="kalahamoon-faq-question-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="14" height="14"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
				</span>
			</summary>
			<div class="kalahamoon-faq-answer">
				<?php echo wp_kses_post( wpautop( $a ) ); ?>
			</div>
		</details>
	<?php endforeach; ?>
</div>

<?php if ( $emit_schema ) :
	$schema_items = array();
	foreach ( $items as $item ) {
		$q = trim( (string) ( $item['q'] ?? '' ) );
		$a = trim( (string) ( $item['a'] ?? '' ) );
		if ( '' === $q || '' === $a ) continue;
		$schema_items[] = array(
			'@type'          => 'Question',
			'name'           => $q,
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $a,
			),
		);
	}
	if ( ! empty( $schema_items ) ) :
		$ld = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $schema_items,
		);
	?>
	<script type="application/ld+json">
	<?php echo wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>
	</script>
<?php endif; endif; ?>
