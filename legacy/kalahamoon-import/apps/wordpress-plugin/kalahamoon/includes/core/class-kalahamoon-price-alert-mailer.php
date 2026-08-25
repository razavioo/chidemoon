<?php
/**
 * Sends price drop email notifications to subscribers.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Price_Alert_Mailer {

	public static function init(): void {
		if ( self::is_catalog_consumer() ) {
			return;
		}

		add_action( 'kalahamoon_check_price_alerts', array( __CLASS__, 'run' ) );
	}

	/**
	 * Check all active price alerts and send emails where the price has dropped.
	 */
	public static function run(): void {
		// A residual legacy cron entry or direct call must not turn a read-only
		// projection into a local price-policy decision or mail workflow.
		if ( self::is_catalog_consumer() ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_price_alerts';
		// A worker crash must not strand a subscription forever. The timestamp is
		// only a delivery lease; it never changes consent or notification history.
		$wpdb->query(
			"UPDATE {$table} SET status = 'active', processing_at = NULL
			 WHERE status = 'processing' AND processing_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 MINUTE)"
		);

		// Only confirmed subscriptions can emit mail. Triggered alerts are one-shot
		// so a static price cannot generate the same notification every day.
		$alerts = $wpdb->get_results(
			"SELECT * FROM {$table}
			 WHERE status = 'active' AND confirmed_at IS NOT NULL",
			ARRAY_A
		);

		if ( empty( $alerts ) ) {
			return;
		}

		foreach ( $alerts as $alert ) {
			self::process_alert( $alert );
		}
	}

	private static function process_alert( array $alert ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'kalahamoon_price_alerts';
		$claimed = $wpdb->update(
			$table,
			array( 'status' => 'processing', 'processing_at' => current_time( 'mysql', true ) ),
			array( 'id' => $alert['id'], 'status' => 'active' )
		);
		if ( 1 !== $claimed ) {
			return;
		}

		$product = Kalahamoon_Product_Cache::get_by_kalahamoon_id( $alert['product_id'] );
		$product = $product ? Kalahamoon_Catalog_Policy::apply( $product ) : null;
		if ( ! $product || empty( $product['publicReady'] ) || empty( $product['priceVisible'] ) ) {
			self::release_claim( $alert );
			return;
		}

		$current_price = (float) $product['price'];
		$target_price  = $alert['target_price'] !== null ? (float) $alert['target_price'] : null;

		// If no target set, notify on any price drop vs last notified price
		$should_notify = false;
		if ( $target_price !== null ) {
			$should_notify = $current_price <= $target_price;
		} else {
			// Check if price dropped vs last recorded price history entry before the alert
			$last_price = (float) $wpdb->get_var( $wpdb->prepare(
				"SELECT price FROM {$wpdb->prefix}kalahamoon_price_history
				 WHERE product_id = %s AND captured_at < %s
				 ORDER BY captured_at DESC LIMIT 1",
				$alert['product_id'],
				$alert['created_at']
			) );
			$should_notify = $last_price > 0 && $current_price < $last_price;
		}

		if ( ! $should_notify ) {
			self::release_claim( $alert );
			return;
		}

		$sent = self::send_email( $alert, $product, $current_price );

		if ( $sent ) {
			$wpdb->update(
				$table,
				array(
					'notified_at'       => current_time( 'mysql', true ),
					'last_notified_price' => $current_price,
					'status'            => 'triggered',
					'processing_at'     => null,
					'subscription_key'  => null,
				),
				array( 'id' => $alert['id'], 'status' => 'processing' )
			);
			return;
		}

		self::release_claim( $alert );
	}

	private static function is_catalog_consumer(): bool {
		return class_exists( 'Kalahamoon_Catalog_Consumer' ) && Kalahamoon_Catalog_Consumer::is_enabled();
	}

	private static function release_claim( array $alert ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'kalahamoon_price_alerts',
			array( 'status' => 'active', 'processing_at' => null ),
			array( 'id' => $alert['id'], 'status' => 'processing' )
		);
	}

	private static function send_email( array $alert, array $product, float $current_price ): bool {
		$to      = $alert['email'];
		$title   = $product['title'] ?? '';
		$subject = sprintf( __( 'Price drop: %s', 'kalahamoon' ), $title );

		// Build affiliate URL
		global $wpdb;
		$slug = $wpdb->get_var( $wpdb->prepare(
			"SELECT slug FROM {$wpdb->prefix}kalahamoon_affiliate_links WHERE product_id = %s AND status = 'active' LIMIT 1",
			$alert['product_id']
		) );
		$buy_url = $slug ? home_url( '/go/' . $slug ) : ( $product['listingUrl'] ?? '' );

		$formatted_price = Kalahamoon_RTL::format_price( $current_price, $product['currency'] ?? 'IRR' );

		$message = self::build_email_html( $title, $formatted_price, $buy_url, self::unsubscribe_url( $alert ) );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		return wp_mail( $to, $subject, $message, $headers );
	}

	public static function unsubscribe_token( array $alert ): string {
		$payload = implode( '|', array(
			(int) ( $alert['id'] ?? 0 ),
			strtolower( (string) ( $alert['email'] ?? '' ) ),
			(string) ( $alert['product_id'] ?? '' ),
			(string) ( $alert['created_at'] ?? '' ),
		) );
		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}

	public static function unsubscribe_url( array $alert ): string {
		return add_query_arg(
			array(
				'id'    => (int) ( $alert['id'] ?? 0 ),
				'token' => self::unsubscribe_token( $alert ),
			),
			rest_url( Kalahamoon_REST_Controller::NAMESPACE . '/price-alerts/unsubscribe' )
		);
	}

	private static function build_email_html( string $title, string $price, string $url, string $unsubscribe_url ): string {
		$site_name = get_bloginfo( 'name' );
		$direction = Kalahamoon_RTL::direction();
		$language  = Kalahamoon_RTL::language();

		ob_start();
		?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $language ); ?>" dir="<?php echo esc_attr( $direction ); ?>">
<head><meta charset="UTF-8"><title><?php esc_html_e( 'Price drop', 'kalahamoon' ); ?></title></head>
<body style="font-family:YekanBakh,Tahoma,Arial,sans-serif;direction:<?php echo esc_attr( $direction ); ?>;background:#f5f5f5;margin:0;padding:20px">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;border:1px solid #e0e0e0">
	<div style="background:#2271b1;padding:20px 24px">
		<h2 style="color:#fff;margin:0;font-size:16px"><?php echo esc_html( $site_name ); ?></h2>
	</div>
	<div style="padding:24px">
		<h3 style="margin-top:0;font-size:15px"><?php esc_html_e( 'The product you were watching dropped in price', 'kalahamoon' ); ?></h3>
		<p style="font-size:15px;font-weight:bold;color:#1d2327" dir="auto"><?php echo esc_html( $title ); ?></p>
		<p style="font-size:22px;font-weight:bold;color:#2271b1;margin:4px 0 16px"><?php echo esc_html( $price ); ?></p>
		<?php if ( $url ) : ?>
		<a href="<?php echo esc_url( $url ); ?>" style="display:inline-block;background:#2271b1;color:#fff;padding:10px 24px;border-radius:6px;text-decoration:none;font-size:14px"><?php esc_html_e( 'View product', 'kalahamoon' ); ?></a>
		<?php endif; ?>
		<hr style="border:none;border-top:1px solid #eee;margin:24px 0 16px">
		<p style="font-size:12px;color:#999;margin:0"><?php esc_html_e( 'This email was sent because you requested a price-drop alert.', 'kalahamoon' ); ?></p>
		<p style="font-size:12px;margin:8px 0 0"><a href="<?php echo esc_url( $unsubscribe_url ); ?>"><?php esc_html_e( 'Unsubscribe from this alert', 'kalahamoon' ); ?></a></p>
	</div>
</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}
}
