<?php
/**
 * Block pattern registry for Kalahamoon affiliate content.
 *
 * Patterns are authored as individual PHP files in /patterns/. Each file
 * carries a header block (Title, Slug, Categories, Keywords, Viewport
 * Width, Block Types, Post Types, Template Types, Inserter, Description)
 * and its markup as body — same shape that core WordPress themes use.
 *
 * Themes and add-ons can extend the pattern set via the
 * `kalahamoon_pattern_files` filter and the `kalahamoon_pattern_categories` filter.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Kalahamoon_Patterns {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_categories' ), 9 );
		add_action( 'init', array( __CLASS__, 'register_patterns' ), 11 );
	}

	public static function register_categories(): void {
		if ( ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		$categories = array(
			'kalahamoon-product'    => __( 'Kalahamoon · Product Showcases', 'kalahamoon' ),
			'kalahamoon-comparison' => __( 'Kalahamoon · Comparisons & Tables', 'kalahamoon' ),
			'kalahamoon-review'     => __( 'Kalahamoon · Reviews & Verdicts', 'kalahamoon' ),
			'kalahamoon-deal'       => __( 'Kalahamoon · Deals & Offers', 'kalahamoon' ),
			'kalahamoon-editorial'  => __( 'Kalahamoon · Editorial & Guides', 'kalahamoon' ),
		);

		/**
		 * Filter the map of Kalahamoon pattern categories (slug => label).
		 *
		 * @param array<string,string> $categories
		 */
		$categories = apply_filters( 'kalahamoon_pattern_categories', $categories );

		foreach ( $categories as $slug => $label ) {
			register_block_pattern_category( $slug, array( 'label' => $label ) );
		}

		// Back-compat: legacy `kalahamoon` category still referenced by older posts.
		register_block_pattern_category( 'kalahamoon', array(
			'label' => __( 'Kalahamoon (legacy)', 'kalahamoon' ),
		) );
	}

	public static function register_patterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		$files = glob( KALAHAMOON_PLUGIN_DIR . 'patterns/*.php' ) ?: array();

		/**
		 * Filter the list of pattern files to register.
		 *
		 * Use this to add patterns from outside the plugin directory (e.g.
		 * a theme-bundled patterns folder) or to remove a bundled pattern.
		 *
		 * @param string[] $files Absolute filesystem paths.
		 */
		$files = apply_filters( 'kalahamoon_pattern_files', $files );

		foreach ( $files as $file ) {
			self::register_pattern_from_file( (string) $file );
		}
	}

	private static function register_pattern_from_file( string $file ): void {
		if ( ! is_readable( $file ) ) {
			return;
		}

		$headers = get_file_data( $file, array(
			'title'       => 'Title',
			'slug'        => 'Slug',
			'categories'  => 'Categories',
			'keywords'    => 'Keywords',
			'viewport'    => 'Viewport Width',
			'blockTypes'  => 'Block Types',
			'postTypes'   => 'Post Types',
			'templateTypes' => 'Template Types',
			'inserter'    => 'Inserter',
			'description' => 'Description',
		) );

		if ( empty( $headers['slug'] ) ) {
			return;
		}

		ob_start();
		include $file;
		$content = (string) ob_get_clean();

		if ( '' === trim( $content ) ) {
			return;
		}

		$pattern = array(
			'title'         => $headers['title'] ?: $headers['slug'],
			'content'       => $content,
			'categories'    => self::split_csv( $headers['categories'] ),
			'keywords'      => self::split_csv( $headers['keywords'] ),
			'description'   => $headers['description'],
			'source'        => 'plugin',
		);

		if ( ! empty( $headers['viewport'] ) ) {
			$pattern['viewportWidth'] = (int) $headers['viewport'];
		}

		$block_types = self::split_csv( $headers['blockTypes'] );
		if ( ! empty( $block_types ) ) {
			$pattern['blockTypes'] = $block_types;
		}

		$post_types = self::split_csv( $headers['postTypes'] );
		if ( ! empty( $post_types ) ) {
			$pattern['postTypes'] = $post_types;
		}

		$template_types = self::split_csv( $headers['templateTypes'] );
		if ( ! empty( $template_types ) ) {
			$pattern['templateTypes'] = $template_types;
		}

		$inserter = self::parse_bool_header( $headers['inserter'] ?? null );
		if ( null !== $inserter ) {
			$pattern['inserter'] = $inserter;
		}

		register_block_pattern( $headers['slug'], $pattern );
	}

	/**
	 * @return string[]
	 */
	private static function split_csv( ?string $value ): array {
		if ( ! $value ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
	}

	private static function parse_bool_header( ?string $value ): ?bool {
		if ( null === $value ) {
			return null;
		}

		$normalized = strtolower( trim( $value ) );
		if ( '' === $normalized ) {
			return null;
		}

		if ( in_array( $normalized, array( '0', 'false', 'no' ), true ) ) {
			return false;
		}

		if ( in_array( $normalized, array( '1', 'true', 'yes' ), true ) ) {
			return true;
		}

		return null;
	}
}
