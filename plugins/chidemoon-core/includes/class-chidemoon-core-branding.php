<?php
/** Approved direction 03 assets and their public presentation. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Chidemoon_Core_Branding {

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 40 );
		add_action( 'login_enqueue_scripts', array( self::class, 'login_style' ) );
		add_filter( 'login_headerurl', static fn() => home_url( '/' ) );
		add_filter( 'login_headertext', static fn() => 'چیدمون — خانه، به سلیقهٔ تو' );
		add_action( 'wp_head', array( self::class, 'home_share_image' ), 20 );
	}

	public static function asset_url( string $file ): string {
		return CHIDEMOON_CORE_URL . 'assets/brand/' . $file;
	}

	public static function enqueue(): void {
		$path = CHIDEMOON_CORE_DIR . 'assets/css/branding.css';
		wp_enqueue_style( 'chidemoon-branding', CHIDEMOON_CORE_URL . 'assets/css/branding.css', array( 'chidemoon-public-design' ), (string) filemtime( $path ) );
	}

	public static function login_style(): void {
		wp_enqueue_style( 'chidemoon-login-brand', CHIDEMOON_CORE_URL . 'assets/css/branding.css', array(), (string) filemtime( CHIDEMOON_CORE_DIR . 'assets/css/branding.css' ) );
		wp_add_inline_style( 'chidemoon-login-brand', '.login h1 a { background-image: url("' . esc_url( self::asset_url( 'chidemoon-logo-fa.svg' ) ) . '"); }' );
	}

	/** Editorial and product share images remain with their own content. */
	public static function home_share_image(): void {
		if ( ! is_front_page() || defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) { return; }
		$image = self::asset_url( 'chidemoon-share.jpg' );
		echo '<meta property="og:type" content="website">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( home_url( '/' ) ) . '">' . "\n";
		echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
		echo '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n";
		echo '<meta property="og:image:alt" content="چیدمون — خانه، به سلیقهٔ تو">' . "\n";
	}
}
