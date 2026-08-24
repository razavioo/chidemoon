<?php
/**
 * Safe front-end shell for the published-content-only assistant endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chidemoon_AI_Assistant_Widget {
	public static function register(): void {
		add_shortcode( 'chidemoon_ai_assistant', array( __CLASS__, 'render' ) );
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public static function render( array $attributes = array() ): string {
		$attributes = shortcode_atts(
			array(
				'title' => __( 'Ask Chidemoon', 'chidemoon-ai' ),
			),
			$attributes,
			'chidemoon_ai_assistant'
		);
		self::enqueue_assets();

		$instance_id = 'chidemoon-ai-assistant-' . wp_unique_id();
		ob_start();
		?>
		<section id="<?php echo esc_attr( $instance_id ); ?>" class="chidemoon-ai-assistant" data-chidemoon-ai-assistant>
			<h2><?php echo esc_html( (string) $attributes['title'] ); ?></h2>
			<p class="chidemoon-ai-assistant__disclosure"><?php esc_html_e( 'This assistant searches only published Chidemoon articles, pages, and products. It does not provide live prices, shopping actions, or unreviewed claims.', 'chidemoon-ai' ); ?></p>
			<form class="chidemoon-ai-assistant__form">
				<label for="<?php echo esc_attr( $instance_id ); ?>-question"><?php esc_html_e( 'Your question', 'chidemoon-ai' ); ?></label>
				<textarea id="<?php echo esc_attr( $instance_id ); ?>-question" name="question" maxlength="500" required></textarea>
				<button type="submit"><?php esc_html_e( 'Search published sources', 'chidemoon-ai' ); ?></button>
			</form>
			<div class="chidemoon-ai-assistant__result" aria-live="polite" aria-atomic="true"></div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function enqueue_assets(): void {
		$handle = 'chidemoon-ai-assistant';
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script(
				$handle,
				CHIDEMOON_AI_URL . 'assets/js/assistant.js',
				array(),
				CHIDEMOON_AI_VERSION,
				true
			);
			wp_add_inline_script(
				$handle,
				'window.ChidemoonAiAssistant = ' . wp_json_encode(
					array(
						'endpoint' => esc_url_raw( rest_url( 'chidemoon-ai/v1/assistant' ) ),
						'error'    => __( 'The assistant could not retrieve published sources right now.', 'chidemoon-ai' ),
					)
				) . ';',
				'before'
			);
		}

		wp_enqueue_script( $handle );
	}
}
