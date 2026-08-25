<?php
/**
 * Technical deployment readiness for the Chidemoon presentation surface.
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

final class Chidemoon_Launch_Readiness {
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'chidemoon launch-readiness', array( __CLASS__, 'command' ) );
		}
	}

	/**
	 * @param list<string>          $args
	 * @param array<string,string> $assoc_args
	 */
	public static function command( array $args, array $assoc_args ): void {
		unset( $args );

		$report = self::report();
		WP_CLI::line( wp_json_encode( $report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );

		$require_ready = array_key_exists( 'require-ready', $assoc_args ) && false !== $assoc_args['require-ready'];
		if ( $require_ready && ! $report['ready'] ) {
			WP_CLI::error( 'Chidemoon technical readiness checks are not satisfied.' );
		}

		if ( $report['ready'] ) {
			WP_CLI::success( 'Chidemoon technical readiness checks are satisfied.' );
		}
	}

	/**
	 * Catalog contents and editorial publication are intentionally absent from
	 * this report. Their source systems own those decisions; deployment only
	 * verifies that this site can render the approved projection.
	 *
	 * @return array{ready:bool,generatedAt:string,gates:array<string,array<string,mixed>>}
	 */
	public static function report(): array {
		$theme          = wp_get_theme();
		$theme_active   = 'chidemoon-theme' === $theme->get_stylesheet();
		$consumer_mode  = class_exists( 'Kalahamoon_Catalog_Consumer' )
			&& Kalahamoon_Catalog_Consumer::is_enabled();
		$cache_adapter  = $consumer_mode
			&& class_exists( 'Kalahamoon_Product_Cache' )
			&& method_exists( 'Kalahamoon_Product_Cache', 'get_all' );
		$auth_available = class_exists( 'Kalahamoon_Auth' );
		$connector_configuration = $consumer_mode
			&& $auth_available
			&& Kalahamoon_Auth::has_catalog_connector_configuration();
		$connector_grant = $connector_configuration
			&& Kalahamoon_Auth::is_connected();
		$origin_proof = $consumer_mode
			&& class_exists( 'Kalahamoon_Catalog_Consumer' )
			&& Kalahamoon_Catalog_Consumer::has_origin_proof_configuration();
		$active_snapshot = $consumer_mode
			&& Kalahamoon_Catalog_Consumer::has_valid_active_snapshot();
		$delivery_confirmed = $active_snapshot
			&& Kalahamoon_Catalog_Consumer::has_confirmed_active_delivery();

		$gates = array(
			'theme' => array(
				'ready'   => $theme_active,
				'details' => array( 'stylesheet' => $theme->get_stylesheet() ),
			),
			'catalogConsumer' => array(
				'ready'   => $cache_adapter,
				'details' => array(
					'consumerModeEnabled' => $consumer_mode,
					'cacheAdapterAvailable' => $cache_adapter,
				),
			),
			'connectorConfiguration' => array(
				'ready'   => $connector_configuration,
				'details' => array( 'authAdapterAvailable' => $auth_available ),
			),
			'connectorGrant' => array(
				'ready'   => $connector_grant,
				'details' => array( 'dedicatedCatalogGrantActive' => $connector_grant ),
			),
			'originProof' => array(
				'ready'   => $origin_proof,
				'details' => array( 'configuredChallengeAvailable' => $origin_proof ),
			),
			'activeSnapshot' => array(
				'ready'   => $active_snapshot,
				'details' => array( 'activePointerValid' => $active_snapshot ),
			),
			'deliveryReceipt' => array(
				'ready'   => $delivery_confirmed,
				'details' => array( 'activeRevisionConfirmed' => $delivery_confirmed ),
			),
		);

		return array(
			'ready'       => $theme_active && $cache_adapter && $connector_configuration && $connector_grant && $origin_proof && $active_snapshot && $delivery_confirmed,
			'generatedAt' => gmdate( 'c' ),
			'gates'       => $gates,
		);
	}
}

add_action( 'plugins_loaded', array( 'Chidemoon_Launch_Readiness', 'register' ), 20 );
