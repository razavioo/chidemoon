<?php
/**
 * Chidemoon editorial and catalogue seed for local previews.
 *
 * WP-CLI only:
 *   docker compose --env-file .env -f compose.yml run --rm --no-deps wpcli \
 *     eval-file /tools/seed-editorial.php --allow-root
 *
 * The script is idempotent: existing slugs are skipped and seed imagery is
 * reused by filename. Product affiliate URLs point to public store search
 * pages as LOCAL PREVIEW placeholders; replace them with reviewed merchant
 * destinations before any non-local deployment. All images are generated
 * locally with GD in the Chidemoon palette, so the seed never needs network
 * access.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

// WP-CLI eval-file executes this file inside a function scope, so the seed
// data arrays must be bound to the global scope for the functions below.
global $cm_seed_posts, $cm_seed_products;
$cm_seed_posts    = array();
$cm_seed_products = array();

const CM_SEED_UPLOAD_DIR = 'chidemoon-seed';

/**
 * @param string $post_type Post type.
 * @param string $slug      Post slug.
 */
function cm_seed_post_exists( string $post_type, string $slug ): int {
	$found = get_page_by_path( $slug, OBJECT, $post_type );
	return $found instanceof WP_Post ? (int) $found->ID : 0;
}

/**
 * @param string $taxonomy    Taxonomy name.
 * @param string $slug        Term slug.
 * @param string $name        Term name.
 * @param string $description Term description.
 */
function cm_seed_term( string $taxonomy, string $slug, string $name, string $description = '' ): int {
	$existing = get_term_by( 'slug', $slug, $taxonomy );
	if ( $existing instanceof WP_Term ) {
		wp_update_term(
			$existing->term_id,
			$taxonomy,
			array(
				'name'        => $name,
				'description' => $description,
			)
		);
		return (int) $existing->term_id;
	}

	$result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug, 'description' => $description ) );
	if ( is_wp_error( $result ) ) {
		WP_CLI::warning( sprintf( 'Term "%s" could not be created: %s', $slug, $result->get_error_message() ) );
		return 0;
	}

	return (int) $result['term_id'];
}

/**
 * Draws a distinct, multi-layered architectural and editorial composition onto a GD canvas.
 *
 * @param resource|\GdImage $image  GD image resource.
 * @param int               $width  Canvas width in pixels.
 * @param int               $height Canvas height in pixels.
 * @param string            $slug   Asset identifier to determine scene composition.
 * @param string            $from   Top gradient hex colour.
 * @param string            $to     Bottom gradient hex colour.
 */
function cm_seed_draw_artwork( $image, int $width, int $height, string $slug, string $from, string $to ): void {
	mt_srand( crc32( $slug ) );
	list( $f_r, $f_g, $f_b ) = cm_seed_hex_to_rgb( $from );
	list( $t_r, $t_g, $t_b ) = cm_seed_hex_to_rgb( $to );

	// Calculate complementary and accent colors.
	$acc_r = (int) min( 255, max( 0, ( $f_r * 2 + $t_r ) / 3 + 40 ) );
	$acc_g = (int) min( 255, max( 0, ( $f_g + $t_g * 2 ) / 3 - 20 ) );
	$acc_b = (int) min( 255, max( 0, ( $f_b * 2 + 50 ) ) );

	$shadow_color = imagecolorallocatealpha( $image, (int) ( $f_r * 0.3 ), (int) ( $f_g * 0.3 ), (int) ( $f_b * 0.3 ), 75 );
	$light_glow   = imagecolorallocatealpha( $image, 255, 250, 240, 60 );
	$warm_gold    = imagecolorallocatealpha( $image, 230, 180, 110, 45 );
	$accent_fill  = imagecolorallocatealpha( $image, $acc_r, $acc_g, $acc_b, 40 );
	$deep_element = imagecolorallocatealpha( $image, (int) ( $f_r * 0.5 ), (int) ( $f_g * 0.5 ), (int) ( $f_b * 0.5 ), 25 );
	$soft_white   = imagecolorallocatealpha( $image, 255, 255, 255, 65 );
	$line_color   = imagecolorallocatealpha( $image, 255, 255, 255, 90 );

	// Detect scene mode from slug keywords.
	$is_lighting = str_contains( $slug, 'lighting' ) || str_contains( $slug, 'lamp' ) || str_contains( $slug, 'sconce' ) || str_contains( $slug, 'pendant' );
	$is_sofa     = str_contains( $slug, 'sofa' ) || str_contains( $slug, 'chair' ) || str_contains( $slug, 'seating' ) || str_contains( $slug, 'living-room' ) || str_contains( $slug, 'layout' );
	$is_textile  = str_contains( $slug, 'rug' ) || str_contains( $slug, 'curtain' ) || str_contains( $slug, 'linen' ) || str_contains( $slug, 'duvet' ) || str_contains( $slug, 'textile' ) || str_contains( $slug, 'bedding' );
	$is_decor    = str_contains( $slug, 'vase' ) || str_contains( $slug, 'mirror' ) || str_contains( $slug, 'tray' ) || str_contains( $slug, 'planter' ) || str_contains( $slug, 'decor' );
	$is_compare  = str_starts_with( $slug, 'post-compare-' ) || str_contains( $slug, 'compare' );
	$is_look     = str_starts_with( $slug, 'post-look-' ) || str_contains( $slug, 'look' ) || str_contains( $slug, 'room' );

	if ( $is_lighting ) {
		// --- LIGHTING / LAMPS SCENE ---
		$source_x = (int) ( $width * 0.5 );
		$source_y = (int) ( $height * 0.32 );

		// Radial light glow halo
		for ( $r = 400; $r > 30; $r -= 30 ) {
			$alpha = (int) min( 127, 85 + ( 400 - $r ) / 10 );
			$glow  = imagecolorallocatealpha( $image, 255, 248, 225, $alpha );
			imagefilledellipse( $image, $source_x, $source_y, $r * 2, $r * 2, $glow );
		}

		// Light cone projection
		$cone_points = array(
			$source_x - 30, $source_y + 10,
			$source_x + 30, $source_y + 10,
			(int) ( $width * 0.88 ), $height,
			(int) ( $width * 0.12 ), $height,
		);
		$cone_fill = imagecolorallocatealpha( $image, 255, 252, 235, 95 );
		imagefilledpolygon( $image, $cone_points, 4, $cone_fill );

		// Floor surface
		$floor_y   = (int) ( $height * 0.82 );
		$floor_col = imagecolorallocatealpha( $image, (int) ( $f_r * 0.4 ), (int) ( $f_g * 0.4 ), (int) ( $f_b * 0.4 ), 60 );
		imagefilledrectangle( $image, 0, $floor_y, $width, $height, $floor_col );

		if ( str_contains( $slug, 'pendant' ) || str_contains( $slug, 'arc' ) ) {
			// Pendant cord & bar
			imagesetthickness( $image, 4 );
			imageline( $image, $source_x, 0, $source_x, $source_y - 20, $deep_element );
			imagefilledellipse( $image, $source_x, $source_y, 110, 50, $warm_gold );
			imagefilledellipse( $image, $source_x, $source_y + 5, 80, 80, $soft_white );
		} elseif ( str_contains( $slug, 'floor' ) || str_contains( $slug, 'standing' ) ) {
			// Standing arched lamp
			imagesetthickness( $image, 5 );
			imageline( $image, (int) ( $width * 0.72 ), $floor_y, (int) ( $width * 0.72 ), (int) ( $height * 0.25 ), $deep_element );
			imagearc( $image, (int) ( $width * 0.62 ), (int) ( $height * 0.25 ), (int) ( $width * 0.35 ), (int) ( $height * 0.35 ), 180, 360, $deep_element );
			imagefilledellipse( $image, $source_x, $source_y, 90, 70, $warm_gold );
			imagefilledellipse( $image, (int) ( $width * 0.72 ), $floor_y, 140, 35, $deep_element );
		} else {
			// Table / ceramic lamp
			imagefilledellipse( $image, $source_x, (int) ( $height * 0.68 ), 160, 190, $accent_fill );
			imagefilledrectangle( $image, $source_x - 110, (int) ( $height * 0.38 ), $source_x + 110, (int) ( $height * 0.58 ), $soft_white );
			imagefilledellipse( $image, $source_x, (int) ( $height * 0.38 ), 220, 50, $soft_white );
			imagefilledellipse( $image, $source_x, (int) ( $height * 0.58 ), 220, 50, $soft_white );
		}
	} elseif ( $is_sofa ) {
		// --- FURNITURE & LIVING ROOM SCENE ---
		$floor_y   = (int) ( $height * 0.76 );
		$floor_col = imagecolorallocatealpha( $image, (int) ( $f_r * 0.35 ), (int) ( $f_g * 0.35 ), (int) ( $f_b * 0.35 ), 50 );
		imagefilledrectangle( $image, 0, $floor_y, $width, $height, $floor_col );

		// Floor perspective lines
		imagesetthickness( $image, 2 );
		$floor_line_col = imagecolorallocatealpha( $image, 255, 255, 255, 115 );
		for ( $lx = 0; $lx < $width; $lx += 120 ) {
			imageline( $image, $lx, $floor_y, $lx + 80, $height, $floor_line_col );
		}

		// Architectural wall arch in background
		$arch_w = (int) ( $width * 0.42 );
		$arch_x = (int) ( $width * 0.18 );
		$arch_y = (int) ( $height * 0.16 );
		imagefilledrectangle( $image, $arch_x, $arch_y + $arch_w / 2, $arch_x + $arch_w, $floor_y, $soft_white );
		imagefilledellipse( $image, $arch_x + $arch_w / 2, $arch_y + $arch_w / 2, $arch_w, $arch_w, $soft_white );

		// Sofa / armchair form
		$sofa_cx = (int) ( $width * 0.56 );
		$sofa_w  = (int) ( $width * 0.52 );
		$sofa_h  = (int) ( $height * 0.28 );
		$sofa_y  = (int) ( $floor_y - $sofa_h * 0.85 );

		// Drop shadow
		imagefilledellipse( $image, $sofa_cx, $floor_y + 8, (int) ( $sofa_w * 1.1 ), 45, $shadow_color );

		// Legs
		imagesetthickness( $image, 6 );
		$leg_col = imagecolorallocatealpha( $image, 40, 30, 25, 30 );
		imageline( $image, $sofa_cx - (int) ( $sofa_w * 0.42 ), $floor_y, $sofa_cx - (int) ( $sofa_w * 0.38 ), $floor_y - 25, $leg_col );
		imageline( $image, $sofa_cx + (int) ( $sofa_w * 0.42 ), $floor_y, $sofa_cx + (int) ( $sofa_w * 0.38 ), $floor_y - 25, $leg_col );
		imageline( $image, $sofa_cx - (int) ( $sofa_w * 0.15 ), $floor_y, $sofa_cx - (int) ( $sofa_w * 0.12 ), $floor_y - 25, $leg_col );
		imageline( $image, $sofa_cx + (int) ( $sofa_w * 0.15 ), $floor_y, $sofa_cx + (int) ( $sofa_w * 0.12 ), $floor_y - 25, $leg_col );

		// Backrest & seat cushions
		imagefilledrectangle( $image, $sofa_cx - (int) ( $sofa_w * 0.44 ), $sofa_y - 45, $sofa_cx + (int) ( $sofa_w * 0.44 ), $sofa_y + $sofa_h - 20, $deep_element );
		imagefilledellipse( $image, $sofa_cx, $sofa_y - 45, (int) ( $sofa_w * 0.88 ), 60, $deep_element );
		imagefilledrectangle( $image, $sofa_cx - (int) ( $sofa_w * 0.46 ), $sofa_y + 15, $sofa_cx + (int) ( $sofa_w * 0.46 ), $sofa_y + $sofa_h - 15, $accent_fill );
		imagefilledellipse( $image, $sofa_cx, $sofa_y + 15, (int) ( $sofa_w * 0.92 ), 40, $accent_fill );

		// Side table circle
		imagefilledellipse( $image, (int) ( $width * 0.18 ), (int) ( $floor_y - 20 ), 110, 110, $warm_gold );
		imagefilledellipse( $image, (int) ( $width * 0.18 ), (int) ( $floor_y + 10 ), 120, 30, $shadow_color );
	} elseif ( $is_textile ) {
		// --- TEXTILES & RUGS & BEDDING SCENE ---
		$center_x = (int) ( $width * 0.5 );
		$center_y = (int) ( $height * 0.5 );

		imagefilledellipse( $image, $center_x, $center_y, (int) ( $width * 0.85 ), (int) ( $height * 0.75 ), $light_glow );

		// Rug perspective polygon
		$rug_points = array(
			(int) ( $width * 0.22 ), (int) ( $height * 0.28 ),
			(int) ( $width * 0.78 ), (int) ( $height * 0.28 ),
			(int) ( $width * 0.88 ), (int) ( $height * 0.82 ),
			(int) ( $width * 0.12 ), (int) ( $height * 0.82 ),
		);
		imagefilledpolygon( $image, $rug_points, 4, $deep_element );

		// Inner decorative border
		$inner_rug_points = array(
			(int) ( $width * 0.27 ), (int) ( $height * 0.34 ),
			(int) ( $width * 0.73 ), (int) ( $height * 0.34 ),
			(int) ( $width * 0.81 ), (int) ( $height * 0.76 ),
			(int) ( $width * 0.19 ), (int) ( $height * 0.76 ),
		);
		imagefilledpolygon( $image, $inner_rug_points, 4, $accent_fill );

		// Diamond center medallion
		$medallion_points = array(
			$center_x, (int) ( $height * 0.40 ),
			(int) ( $width * 0.62 ), (int) ( $height * 0.55 ),
			$center_x, (int) ( $height * 0.70 ),
			(int) ( $width * 0.38 ), (int) ( $height * 0.55 ),
		);
		imagefilledpolygon( $image, $medallion_points, 4, $warm_gold );

		// Fringes / tassels lines
		imagesetthickness( $image, 2 );
		for ( $fx = (int) ( $width * 0.12 ); $fx <= (int) ( $width * 0.88 ); $fx += 10 ) {
			imageline( $image, $fx, (int) ( $height * 0.82 ), $fx - 4, (int) ( $height * 0.85 ), $soft_white );
		}
	} elseif ( $is_decor ) {
		// --- DECOR & CERAMICS SCENE ---
		$ped_w = (int) ( $width * 0.36 );
		$ped_x = (int) ( ( $width - $ped_w ) / 2 );
		$ped_y = (int) ( $height * 0.62 );

		// Halo backdrop
		imagefilledellipse( $image, (int) ( $width * 0.5 ), (int) ( $height * 0.42 ), (int) ( $width * 0.6 ), (int) ( $width * 0.6 ), $light_glow );

		// Pedestal block
		imagefilledrectangle( $image, $ped_x, $ped_y, $ped_x + $ped_w, $height, $deep_element );
		imagefilledellipse( $image, (int) ( $width * 0.5 ), $ped_y, $ped_w, 40, $accent_fill );

		// Ceramic Vase on pedestal
		$vase_cx = (int) ( $width * 0.5 );
		$vase_by = (int) ( $ped_y - 10 );
		imagefilledellipse( $image, $vase_cx, $vase_by - 80, 160, 180, $warm_gold );
		imagefilledellipse( $image, $vase_cx, $vase_by - 160, 80, 100, $warm_gold );
		imagefilledellipse( $image, $vase_cx, $vase_by - 210, 50, 30, $soft_white );

		// Botanical branch stems
		imagesetthickness( $image, 3 );
		$stem_col = imagecolorallocatealpha( $image, 40, 45, 35, 40 );
		imageline( $image, $vase_cx, $vase_by - 210, $vase_cx - 60, $vase_by - 340, $stem_col );
		imageline( $image, $vase_cx, $vase_by - 210, $vase_cx + 70, $vase_by - 360, $stem_col );
		imageline( $image, $vase_cx, $vase_by - 210, $vase_cx + 10, $vase_by - 390, $stem_col );

		// Leaves
		imagefilledellipse( $image, $vase_cx - 60, $vase_by - 340, 35, 18, $accent_fill );
		imagefilledellipse( $image, $vase_cx + 70, $vase_by - 360, 35, 18, $accent_fill );
		imagefilledellipse( $image, $vase_cx + 10, $vase_by - 390, 20, 35, $accent_fill );
	} elseif ( $is_compare ) {
		// --- COMPARISON DUAL SCENE ---
		$mid_x       = (int) ( $width * 0.5 );
		$right_panel = imagecolorallocatealpha( $image, (int) ( $t_r * 0.6 ), (int) ( $t_g * 0.6 ), (int) ( $t_b * 0.6 ), 35 );
		imagefilledrectangle( $image, $mid_x, 0, $width, $height, $right_panel );

		imagesetthickness( $image, 3 );
		imageline( $image, $mid_x, 0, $mid_x, $height, $soft_white );

		// Left side: Organic rounded geometry (Option A)
		imagefilledellipse( $image, (int) ( $width * 0.25 ), (int) ( $height * 0.5 ), 240, 240, $accent_fill );
		imagefilledrectangle( $image, (int) ( $width * 0.16 ), (int) ( $height * 0.60 ), (int) ( $width * 0.34 ), (int) ( $height * 0.78 ), $warm_gold );

		// Right side: Crisp linear geometry (Option B)
		imagefilledrectangle( $image, (int) ( $width * 0.65 ), (int) ( $height * 0.36 ), (int) ( $width * 0.85 ), (int) ( $height * 0.68 ), $deep_element );
		imagefilledellipse( $image, (int) ( $width * 0.75 ), (int) ( $height * 0.36 ), (int) ( $width * 0.20 ), 50, $soft_white );

		// VS badge at top center
		imagefilledellipse( $image, $mid_x, (int) ( $height * 0.20 ), 70, 70, $light_glow );
	} else {
		// --- ROOM INTERIORS & GENERAL EDITORIAL HEROES ---
		$floor_y   = (int) ( $height * 0.80 );
		$floor_col = imagecolorallocatealpha( $image, (int) ( $f_r * 0.4 ), (int) ( $f_g * 0.4 ), (int) ( $f_b * 0.4 ), 55 );
		imagefilledrectangle( $image, 0, $floor_y, $width, $height, $floor_col );

		// Window frame
		$win_x = (int) ( $width * 0.68 );
		$win_y = (int) ( $height * 0.14 );
		$win_w = (int) ( $width * 0.24 );
		$win_h = (int) ( $height * 0.48 );
		imagefilledrectangle( $image, $win_x, $win_y, $win_x + $win_w, $win_y + $win_h, $light_glow );

		imagesetthickness( $image, 3 );
		imageline( $image, $win_x + $win_w / 2, $win_y, $win_x + $win_w / 2, $win_y + $win_h, $soft_white );
		imageline( $image, $win_x, $win_y + $win_h / 2, $win_x + $win_w, $win_y + $win_h / 2, $soft_white );

		// Sunlight cast
		$beam = array(
			$win_x, $win_y + $win_h,
			$win_x + $win_w, $win_y + $win_h,
			(int) ( $width * 0.55 ), $height,
			(int) ( $width * 0.20 ), $height,
		);
		$beam_fill = imagecolorallocatealpha( $image, 255, 250, 230, 100 );
		imagefilledpolygon( $image, $beam, 4, $beam_fill );

		// Room arrangement
		imagefilledellipse( $image, (int) ( $width * 0.38 ), $floor_y, (int) ( $width * 0.45 ), 80, $accent_fill );
		imagefilledrectangle( $image, (int) ( $width * 0.28 ), $floor_y - 120, (int) ( $width * 0.48 ), $floor_y - 20, $deep_element );
		imagefilledellipse( $image, (int) ( $width * 0.38 ), $floor_y - 120, (int) ( $width * 0.20 ), 40, $deep_element );
	}

	// Editorial grain noise
	$grain = imagecolorallocatealpha( $image, 255, 255, 255, 112 );
	for ( $i = 0; $i < 6500; $i++ ) {
		imagesetpixel( $image, mt_rand( 0, $width - 1 ), mt_rand( 0, $height - 1 ), $grain );
	}

	// Subtle magazine branding badge in top corner
	$badge_bg = imagecolorallocatealpha( $image, 16, 45, 38, 70 );
	imagefilledrectangle( $image, (int) ( $width * 0.05 ), (int) ( $height * 0.06 ), (int) ( $width * 0.05 + 130 ), (int) ( $height * 0.06 + 28 ), $badge_bg );
	imagerectangle( $image, (int) ( $width * 0.05 ), (int) ( $height * 0.06 ), (int) ( $width * 0.05 + 130 ), (int) ( $height * 0.06 + 28 ), $line_color );
}

/**
 * Generates (or reuses) a deterministic abstract editorial image.
 *
 * @param string $slug    Asset slug, also the filename.
 * @param string $from    Top gradient hex colour.
 * @param string $to      Bottom gradient hex colour.
 * @param string $alt     Persian alt text.
 * @param string $caption Optional caption for the attachment.
 * @param int    $width   Image width in pixels.
 * @param int    $height  Image height in pixels.
 * @return int Attachment ID, 0 when generation is impossible.
 */
function cm_seed_image( string $slug, string $from, string $to, string $alt, string $caption = '', int $width = 1280, int $height = 960 ): int {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_chidemoon_seed_asset',
			'meta_value'     => $slug,
		)
	);

	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		WP_CLI::warning( 'Uploads directory error: ' . $uploads['error'] );
		return 0;
	}

	$directory = trailingslashit( $uploads['basedir'] ) . CM_SEED_UPLOAD_DIR;
	if ( ! wp_mkdir_p( $directory ) ) {
		WP_CLI::warning( 'Unable to create the seed uploads directory.' );
		return 0;
	}

	$path = $directory . '/' . $slug . '.jpg';

	if ( ! empty( $existing ) && file_exists( $path ) ) {
		return (int) $existing[0];
	}

	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		WP_CLI::warning( 'The GD extension is unavailable; skipping seed image "' . $slug . '".' );
		return 0;
	}

	list( $from_red, $from_green, $from_blue ) = cm_seed_hex_to_rgb( $from );
	list( $to_red, $to_green, $to_blue )       = cm_seed_hex_to_rgb( $to );

	$image = imagecreatetruecolor( $width, $height );
	for ( $y = 0; $y < $height; $y++ ) {
		$step  = $y / ( $height - 1 );
		$red   = (int) round( $from_red + ( $to_red - $from_red ) * $step );
		$green = (int) round( $from_green + ( $to_green - $from_green ) * $step );
		$blue  = (int) round( $from_blue + ( $to_blue - $from_blue ) * $step );
		imagefilledrectangle( $image, 0, $y, $width, $y + 1, imagecolorallocate( $image, $red, $green, $blue ) );
	}

	cm_seed_draw_artwork( $image, $width, $height, $slug, $from, $to );

	imagejpeg( $image, $path, 88 );
	imagedestroy( $image );

	if ( ! empty( $existing ) ) {
		$attachment_id = (int) $existing[0];
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $path ) );
		if ( '' !== $caption ) {
			wp_update_post(
				array(
					'ID'           => $attachment_id,
					'post_excerpt' => $caption,
				)
			);
		}
		return $attachment_id;
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $alt,
			'post_excerpt'   => $caption,
			'post_status'    => 'inherit',
		),
		$path
	);
	if ( is_wp_error( $attachment_id ) || 0 === (int) $attachment_id ) {
		WP_CLI::warning( 'Attachment for "' . $slug . '" could not be stored.' );
		return 0;
	}

	wp_update_attachment_metadata( (int) $attachment_id, wp_generate_attachment_metadata( (int) $attachment_id, $path ) );
	update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', $alt );
	update_post_meta( (int) $attachment_id, '_chidemoon_seed_asset', $slug );

	return (int) $attachment_id;
}

/**
 * Returns HTML for an inline captioned Gutenberg image block.
 *
 * @param string $slug    Asset slug.
 * @param string $from    Gradient start color.
 * @param string $to      Gradient end color.
 * @param string $alt     Alt text.
 * @param string $caption Caption text.
 * @return string Gutenberg HTML markup.
 */
function cm_seed_figure_html( string $slug, string $from, string $to, string $alt, string $caption = '' ): string {
	$attachment_id = cm_seed_image( $slug, $from, $to, $alt, $caption );
	if ( 0 === $attachment_id ) {
		return '';
	}

	$src = wp_get_attachment_image_url( $attachment_id, 'large' );
	if ( ! $src ) {
		$src = wp_get_attachment_url( $attachment_id );
	}

	$caption_html = '' !== $caption ? sprintf( '<figcaption class="wp-element-caption">%s</figcaption>', esc_html( $caption ) ) : '';

	return sprintf(
		'<!-- wp:image {"id":%d,"sizeSlug":"large","linkDestination":"none"} -->' .
		'<figure class="wp-block-image size-large"><img src="%s" alt="%s" class="wp-image-%d"/>%s</figure>' .
		'<!-- /wp:image -->',
		(int) $attachment_id,
		esc_url( (string) $src ),
		esc_attr( $alt ),
		(int) $attachment_id,
		$caption_html
	);
}

/**
 * @param string $hex Hex colour like #173f35.
 * @return int[] Red, green, blue.
 */
function cm_seed_hex_to_rgb( string $hex ): array {
	$hex = ltrim( $hex, '#' );
	return array(
		(int) hexdec( substr( $hex, 0, 2 ) ),
		(int) hexdec( substr( $hex, 2, 2 ) ),
		(int) hexdec( substr( $hex, 4, 2 ) ),
	);
}

/* __CM_SEED_APPEND__ */

/**
 * Creates one seed post with terms and a generated thumbnail.
 *
 * @param array<string, mixed> $spec Post specification.
 */
function cm_seed_post( array $spec ): void {
	$body = $spec['body'];
	if ( ! empty( $spec['inline_figure'] ) ) {
		$fig      = $spec['inline_figure'];
		$fig_html = cm_seed_figure_html(
			$fig['slug'],
			$fig['colors'][0],
			$fig['colors'][1],
			$fig['alt'],
			$fig['caption'] ?? ''
		);
		if ( ! empty( $fig_html ) ) {
			if ( str_contains( $body, '</h2>' ) ) {
				$body = preg_replace( '/(<\/h2>)/', '$1' . "\n\n" . $fig_html, $body, 1 );
			} else {
				$body = $fig_html . "\n\n" . $body;
			}
		}
	}

	$existing_id = cm_seed_post_exists( 'post', $spec['slug'] );
	if ( $existing_id > 0 ) {
		wp_update_post(
			array(
				'ID'           => $existing_id,
				'post_content' => $body,
				'post_excerpt' => $spec['excerpt'],
			)
		);
		wp_set_object_terms( $existing_id, array( $spec['category'] ), 'category', false );
		$thumb_id = get_post_thumbnail_id( $existing_id );
		if ( $thumb_id > 0 && ! empty( $spec['caption'] ) ) {
			wp_update_post(
				array(
					'ID'           => $thumb_id,
					'post_excerpt' => $spec['caption'],
				)
			);
		}
		if ( ! empty( $spec['tags'] ) ) {
			wp_set_object_terms( $existing_id, $spec['tags'], 'post_tag', false );
			if ( in_array( 'shop-the-look', $spec['tags'], true ) && class_exists( 'Chidemoon_Core_Shop_The_Look' ) ) {
				$room = Chidemoon_Core_Shop_The_Look::room_for_tags( $spec['tags'] );
				if ( '' !== $room ) {
					wp_set_object_terms( $existing_id, $room, Chidemoon_Core_Shop_The_Look::TAXONOMY, false );
				}
			}
		}
		WP_CLI::log( 'Post refreshed with figures: ' . $spec['slug'] );
		return;
	}

	$published_gmt = gmdate( 'Y-m-d H:i:s', time() - (int) $spec['age_days'] * DAY_IN_SECONDS );
	$post_id       = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_name'     => $spec['slug'],
			'post_title'    => $spec['title'],
			'post_excerpt'  => $spec['excerpt'],
			'post_content'  => $body,
			'post_date'     => $published_gmt,
			'post_date_gmt' => $published_gmt,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( 'Post "' . $spec['slug'] . '" failed: ' . $post_id->get_error_message() );
		return;
	}

	$post_id = (int) $post_id;
	wp_set_object_terms( $post_id, array( $spec['category'] ), 'category', false );
	if ( ! empty( $spec['tags'] ) ) {
		wp_set_object_terms( $post_id, $spec['tags'], 'post_tag', false );
		if ( in_array( 'shop-the-look', $spec['tags'], true ) && class_exists( 'Chidemoon_Core_Shop_The_Look' ) ) {
			$room = Chidemoon_Core_Shop_The_Look::room_for_tags( $spec['tags'] );
			if ( '' !== $room ) {
				wp_set_object_terms( $post_id, $room, Chidemoon_Core_Shop_The_Look::TAXONOMY, false );
			}
		}
	}

	$image_id = cm_seed_image(
		'post-' . $spec['slug'],
		$spec['colors'][0],
		$spec['colors'][1],
		$spec['alt'],
		$spec['caption'] ?? $spec['excerpt']
	);
	if ( $image_id > 0 ) {
		set_post_thumbnail( $post_id, $image_id );
	}

	WP_CLI::log( 'Post seeded: ' . $spec['slug'] );
}

$cm_seed_posts = array(
	// --- GUIDES & WALKTHROUGHS ---
	array(
		'slug'          => 'guide-choose-sofa',
		'title'         => 'راهنمای انتخاب مبل راحتی برای خانه‌های ایرانی',
		'excerpt'       => 'پیش از خرید مبل، اندازه، پارچه، تراکم فوم و فرم را با هم مرور می‌کنیم تا انتخابی بخرید که سال‌ها دوام بیاورد.',
		'category'      => 'guides',
		'tags'          => array( 'راهنمای خرید', 'مبلمان', 'نشیمن' ),
		'colors'        => array( '#173f35', '#9eab92' ),
		'alt'           => 'تصویر انتزاعی سبز برای راهنمای انتخاب مبل راحتی',
		'caption'       => 'تناسب مقیاس مبل راحتی و فضای آزاد عبور و مرور در نشیمن خانگی.',
		'inline_figure' => array(
			'slug'    => 'fig-guide-sofa-layout',
			'colors'  => array( '#173f35', '#9eab92' ),
			'alt'     => 'طرح شماتیک فاصله‌گذاری مبل راحتی',
			'caption' => 'نمودار فاصله‌گذاری استاندارد مبل راحتی تا میز وسط و مسیر عبور در نشیمن.',
		),
		'age_days'      => 42,
		'body'          => '<p>وقتی وارد اتاق نشیمن می‌شوید، پیش از هر رنگ و تزئین دیگری، فرم و مقیاس مبل است که شخصیت فضا را تعیین می‌کند. مبل بزرگ‌ترین عنصر مبلمان خانه است و اشتباه در ابعاد یا کیفیت نشیمن آن، با هیچ کوسن یا تابلویی جبران نمی‌شود.</p><h2>ابعاد و فاصله را پیش از سلیقه بسنجید</h2><p>بزرگ‌ترین پشیمانی در خرید مبل، مسدود شدن مسیرهای حرکتی خانه است. پیش از سفارش، ابعاد اتاق را با متر اندازه بگیرید و فضای خالی لازم برای رفت‌وآمد را رعایت کنید.</p><figure class="wp-block-table"><table><thead><tr><th>نوع مبلمان</th><th>طول استاندارد</th><th>عمق نشیمن</th><th>فاصله مفید تا میز</th></tr></thead><tbody><tr><td>مبل دونفره جمع‌وجور</td><td>۱۴۰ تا ۱۶۰ سانتی‌متر</td><td>۵۰ تا ۵۵ سانتی‌متر</td><td>۴۰ سانتی‌متر</td></tr><tr><td>مبل سه‌نفره خانوادگی</td><td>۱۹۰ تا ۲۲۰ سانتی‌متر</td><td>۵۵ تا ۶۵ سانتی‌متر</td><td>۴۵ سانتی‌متر</td></tr><tr><td>مبل ال‌شکل مدولار</td><td>۲۴۰ تا ۲۸۰ سانتی‌متر</td><td>۶۰ تا ۷۰ سانتی‌متر</td><td>۴۵ تا ۵۰ سانتی‌متر</td></tr></tbody></table></figure><h2>پارچه یا چرم؟ مقایسه‌ی جنس رویه</h2><p>پارچه و چرم در شرایط آب‌وهوایی و میزان استفاده روزمره رفتارهای کاملاً متفاوتی از خود نشان می‌دهند:</p><div class="wp-block-columns"><div class="wp-block-column"><h3>مزایای پارچه‌های بافت‌دار (کتان/بوکل)</h3><ul><li>حس لمس گرم و تنفس‌پذیر در تمام فصول</li><li>تنوع بالا در تناژهای خنثی و خاکی</li><li>عدم ایجاد حس چسبندگی در اقلیم‌های گرم</li></ul></div><div class="wp-block-column"><h3>ملاحظات و چالش‌های چرم</h3><ul><li>نیازمند نرم‌کننده‌های دوره‌ای برای جلوگیری از ترک</li><li>سرما در زمستان و گرما در تابستان</li><li>آسیب‌پذیری بیشتر در برابر ناخن حیوانات خانگی</li></ul></div></div><blockquote class="wp-block-quote"><p>نکته فنی چیدمون: اسفنج سرد ۳۵ کیلویی (HR) مرز تشخیص مبل باکیفیت است؛ هنگام خرید کتباً نوع فوم و ضمانت افت نشیمن را از فروشنده بخواهید.</p></blockquote><h2>پرسش‌های متداول پیش از پرداخت</h2><details class="wp-block-details"><summary>چگونه مبل را از ورودی‌های باریک و راه‌پله عبور دهیم؟</summary><p>اگر عرض راهرو یا درب ورودی کمتر از ۸۰ سانتی‌متر است، پایه‌های پیچی جداشونده و دسته‌های پیچ‌مهره‌ای را به سازنده سفارش دهید.</p></details><details class="wp-block-details"><summary>کوسن پر یا فوم فشرده؛ کدام راحت‌تر است؟</summary><p>کوسن پر حس راحتی اولیه دارد اما نیازمند مرتب‌سازی روزانه است؛ ترکیب هسته فوم با لایه رویی پر بهترین تعادل را ایجاد می‌کند.</p></details>',
	),

	array(
		'slug'          => 'guide-lighting-small-apartment',
		'title'         => 'نورپردازی آپارتمان کوچک؛ از کجا شروع کنیم؟',
		'excerpt'       => 'سه لایه نور، دمای رنگ درست و چند ترفند ساده که آپارتمان کوچک را بزرگ‌تر و آرام‌تر نشان می‌دهد.',
		'category'      => 'guides',
		'tags'          => array( 'روشنایی', 'آپارتمان کوچک', 'طراحی داخلی' ),
		'colors'        => array( '#b65d3d', '#e2b19d' ),
		'alt'           => 'تصویر انتزاعی گرم برای راهنمای نورپردازی چندلایه',
		'caption'       => 'نورپردازی لایه‌ای و تنظیم دمای رنگ در فضاهای فشرده آپارتمانی.',
		'inline_figure' => array(
			'slug'    => 'fig-guide-lighting-layers',
			'colors'  => array( '#b65d3d', '#e2b19d' ),
			'alt'     => 'نمودار سه‌لایه نورپردازی نشیمن',
			'caption' => 'لایه‌بندی نور عمومی، کاربری و تأکیدی برای بزرگ‌تر نشان دادن آپارتمان کوچک.',
		),
		'age_days'      => 38,
		'body'          => '<p>نور ارزان‌ترین و تأثیرگذارترین ابزار برای تغییر حس و حال خانه است. پیش از آنکه به فکر تخریب دیوار یا تعویض کلی مبلمان بیفتید، قاعده نورپردازی سه‌لایه را در خانه پیاده کنید.</p><h2>سه لایه‌ای که هر اتاق به آن نیاز دارد</h2><ol><li><strong>نور عمومی (Ambient):</strong> روشنایی پایه سقف برای دید کلی در فضا.</li><li><strong>نور کاربری (Task):</strong> آباژور مطالعه، چراغ رومیزی یا آویز بالای جزیره برای فعالیت‌های تمرکزی.</li><li><strong>نور تأکیدی (Accent):</strong> پرتوهای ملایم روی تابلوها، گلدان‌ها یا گوشه‌های دنج برای عمق‌بخشی به فضا.</li></ol><figure class="wp-block-table"><table><thead><tr><th>کاربری فضا</th><th>دمای رنگ پیشنهادی</th><th>شدت نور تقریبی (لوکس)</th></tr></thead><tbody><tr><td>نشیمن و استراحت</td><td>۲۷۰۰ تا ۳۰۰۰ کلوین (آفتابی گرم)</td><td>۱۵۰ تا ۲۰۰ لوکس</td></tr><tr><td>میز کار و مطالعه</td><td>۴۰۰۰ کلوین (طبیعی/خنثی)</td><td>۴۰۰ تا ۵۰۰ لوکس</td></tr><tr><td>اتاق خواب</td><td>۲۵۰۰ تا ۲۸۰۰ کلوین (بسیار گرم)</td><td>۱۰۰ تا ۱۵۰ لوکس</td></tr></tbody></table></figure><blockquote class="wp-block-quote"><p>نکته چیدمون: آباژور پایه‌بلندی که نورش به سمت سقف می‌تابد، مرز اتصال سقف و دیوار را محو کرده و سقف‌های کوتاه را بلندتر جلوه می‌دهد.</p></blockquote>',
	),

	array(
		'slug'          => 'guide-persian-rug-placement',
		'title'         => 'اصول چیدمان فرش و پادری دستباف در نشیمن مدرن',
		'excerpt'       => 'قوانین هندسی چیدمان فرش در نشیمن مدرن؛ پایه‌ها روی فرش باشند یا شناور؟ راهنمای انتخاب اندازه بر اساس مساحت اتاق.',
		'category'      => 'guides',
		'tags'          => array( 'منسوجات', 'فرش', 'نشیمن' ),
		'colors'        => array( '#102d26', '#b65d3d' ),
		'alt'           => 'تصویر انتزاعی خاکی و تیره برای چیدمان فرش مدرن',
		'caption'       => 'هماهنگی ابعاد فرش دستباف با چیدمان مبلمان نشیمن مدرن.',
		'inline_figure' => array(
			'slug'    => 'fig-guide-rug-patterns',
			'colors'  => array( '#102d26', '#b65d3d' ),
			'alt'     => 'طرح هندسی الگوهای چیدمان فرش',
			'caption' => 'نقشه استقرار پایه‌های مبل روی حاشیه فرش و حفظ فاصله تا دیوار.',
		),
		'age_days'      => 35,
		'body'          => '<p>فرش دستباف یا گلیم ایرانی تنها یک پوشش کف نیست؛ جزیره‌ای است که تک‌تک عناصر مبلمان را دور هم جمع می‌کند و حوزه بصری اتاق را می‌سازد.</p><h2>سه چیدمان کلاسیک برای پایه مبل و فرش</h2><p>برای حفظ تعادل و تناسب هندسی در نشیمن، یکی از این سه الگو را انتخاب کنید:</p><ol><li><strong>تمام پایه‌ها روی فرش:</strong> مناسب سالن‌های بزرگ (بیش از ۲۵ متر مربع)؛ تمام مبل‌ها و میزها داخل کادر فرش قرار می‌گیرند.</li><li><strong>فقط پایه‌های جلو روی فرش (قاعده طلایی):</strong> پایه‌های جلویی مبل روی حاشیه فرش و پایه‌های عقب روی کف‌پوش قرار می‌گیرند؛ فضا صمیمی و یکپارچه دیده می‌شود.</li><li><strong>مبلمان کاملاً شناور:</strong> فقط میز جلومدی وسط فرش است؛ مناسب آپارتمان‌های زیر ۵۰ متر با فرش‌های کوچک و قالیچه‌های متمرکز.</li></ol><figure class="wp-block-table"><table><thead><tr><th>مساحت نشیمن</th><th>ابعاد قالیچه پیشنهادی</th><th>فاصله از دیوار</th></tr></thead><tbody><tr><td>۱۰ تا ۱۵ متر مربع</td><td>۱٫۵ × ۲٫۲ متر (۴ متری)</td><td>۳۰ تا ۴۰ سانتی‌متر</td></tr><tr><td>۱۵ تا ۲۵ متر مربع</td><td>۲ × ۳ متر (۶ متری)</td><td>۴۰ تا ۵۰ سانتی‌متر</td></tr><tr><td>بیش از ۲۵ متر مربع</td><td>۲٫۵ × ۳٫۵ یا ۳ × ۴ متر (۹ یا ۱۲ متری)</td><td>دست‌کم ۶۰ سانتی‌متر</td></tr></tbody></table></figure><blockquote class="wp-block-quote"><p>توصیه چیدمون: فرش را هیچ‌گاه مماس با دیوار نچسبانید؛ حاشیه خالی کف‌پوش دور فرش، تنفس بصری ایجاد می‌کند.</p></blockquote>',
	),

	array(
		'slug'          => 'guide-curtain-fabric',
		'title'         => 'پرده کرکاب، کتان یا مخمل؟ انتخاب پارچه‌ی پرده',
		'excerpt'       => 'هر پارچه با نور رفتار متفاوتی دارد؛ راهنمای کوتاهی برای انتخاب پرده بر اساس جهت پنجره و کاربری اتاق.',
		'category'      => 'guides',
		'tags'          => array( 'منسوجات', 'پرده', 'دکوراسیون' ),
		'colors'        => array( '#9eab92', '#f6f2e9' ),
		'alt'           => 'تصویر انتزاعی سبز روشن برای راهنمای پارچه پرده',
		'caption'       => 'فیلتر نور و رفتار بصری بافت‌های کتان و مخمل در برابر پنجره.',
		'inline_figure' => array(
			'slug'    => 'fig-guide-curtain-folds',
			'colors'  => array( '#9eab92', '#f6f2e9' ),
			'alt'     => 'بافت پارچه و رفتار نور',
			'caption' => 'رفتار فیلتر نور در پارچه‌های کتان ارگانیک و مخمل.',
		),
		'age_days'      => 34,
		'body'          => '<p>پرده تنها یک پوشش پنجره نیست؛ فیلتر نور، عایق صدا و قاب تصویر بیرون است. انتخاب پارچه باید بر اساس جهت پنجره و کاربری اتاق انجام شود، نه فقط رنگ دیوار.</p><h2>کرکاب برای اتاق خواب رو به شرق</h2><p>کرکاب سه‌لایه نور صبح را کامل قطع می‌کند و برای اتاق خوابی که پنجره‌اش رو به طلوع است انتخاب درست است. ضخامت بالای پارچه نباید شما را به سمت رنگ‌های تیره‌ی سنگین ببرد؛ کرکاب با رنگ روشن هم عملکرد خوبی دارد.</p><h2>کتان و مخمل برای نشیمن</h2><p>کتان نور را نرم و پخش می‌کند و برای نشیمن‌هایی که رو به نور غیرمستقیم هستند عالی است، اما اتوکشیدگی دائمی نمی‌خواهد. مخمل برای نور مستقیم غربی مناسب‌تر است؛ گرما را می‌گیرد و با نور عصرگاهی عمق رنگی گرمی می‌سازد.</p><div class="wp-block-columns"><div class="wp-block-column"><h3>مزایای کتان طبیعی</h3><ul><li>عبور لطیف و پراکنده نور روز</li><li>بافت ارگانیک و غیررسمی</li><li>شست‌وشوی آسان خانگی</li></ul></div><div class="wp-block-column"><h3>مزایای مخمل ضخیم</h3><ul><li>عایق صوتی و حرارتی مناسب در زمستان</li><li>ایجاد وزن بصری و وقار در اتاق</li><li>جذب کامل پرتوهای خیره‌کننده آفتاب</li></ul></div></div><blockquote class="wp-block-quote"><p>نکته چیدمون: عرض پارچه‌ی پرده دست‌کم دو برابر عرض میله در نظر گرفته شود تا چین‌خوردگی‌های پرده پر و باوقار بایستد.</p></blockquote>',
	),

	array(
		'slug'          => 'guide-small-living-room-layout',
		'title'         => 'چطور نشیمن دوازده‌متری را بدون شلوغی بچینیم؟',
		'excerpt'       => 'یک راهنمای مرحله‌به‌مرحله برای اندازه‌گیری، انتخاب مسیر عبور و ساختن نشیمنی که هم مهمان‌پذیر باشد هم آرام.',
		'category'      => 'guides',
		'tags'          => array( 'نشیمن کوچک', 'اندازه‌گیری', 'چیدمان' ),
		'colors'        => array( '#102d26', '#9eab92' ),
		'alt'           => 'تصویر انتزاعی برای چیدمان نشیمن کوچک',
		'caption'       => 'چیدمان ارگونومیک و مدیریت مسیرهای حرکتی در نشیمن ۱۲ متری.',
		'inline_figure' => array(
			'slug'    => 'fig-guide-small-room-plan',
			'colors'  => array( '#102d26', '#9eab92' ),
			'alt'     => 'نقشه چیدمان نشیمن دوازده‌متری',
			'caption' => 'برش پلان و خطوط دید در چیدمان مبل و میز نشیمن کوچک.',
		),
		'age_days'      => 31,
		'body'          => '<p>نشیمن کوچک با خرید وسیله‌ی کمتر حل نمی‌شود؛ با تصمیم‌گیری دقیق درباره‌ی مسیر حرکت و حفظ مقیاس حل می‌شود. این راهنما برای فضاهای فشرده طراحی شده است.</p><h2>مرحله‌ی اول: نقشه بکشید</h2><ol><li>طول و عرض مفید اتاق را دقیقاً اندازه بگیرید.</li><li>درها، پنجره‌ها و رادیاتور را روی نقشه علامت بزنید.</li><li>مسیر عبور ۶۰ تا ۷۰ سانتی‌متری را بدون مانع نگه دارید.</li></ol><h2>مرحله‌ی دوم: یک نقطه‌ی کانونی مشخص</h2><p>به‌جای چیدن تلویزیون، مبل و میز در سه جهت جدا، ابتدا نقطه کانونی را انتخاب کنید. مبل دونفره کم‌عمق، یک صندلی تک‌نفره سبک و میز گرد انعطاف بسیار بیشتری نسبت به یک ست کامل سنگین دارند.</p><figure class="wp-block-table"><table><thead><tr><th>عنصر چیدمان</th><th>فاصله استاندارد</th><th>دلیل ارگونومیک</th></tr></thead><tbody><tr><td>مسیر عبور اصلی</td><td>۶۰ تا ۷۰ سانتی‌متر</td><td>عبور بدون برخورد بدن با لبه مبلمان</td></tr><tr><td>فاصله مبل تا میز وسط</td><td>۴۰ تا ۴۵ سانتی‌متر</td><td>دسترسی راحت به فنجان و وسایل بدون خم شدن بیش‌ازحد</td></tr><tr><td>فاصله مبل تا تلویزیون</td><td>۲ تا ۲٫۵ برابر قطر صفحه</td><td>جلوگیری از خستگی چشم در تماشای طولانی</td></tr></tbody></table></figure><blockquote class="wp-block-quote"><p>آزمون واقعی: پیش از خرید، جای هر وسیله را با روزنامه روی زمین مشخص کنید و یک روز در خانه رفت‌وآمد کنید.</p></blockquote>',
	),

	array(
		'slug'     => 'guide-home-office-lighting',
		'title'    => 'نورپردازی بهینه میز کار خانگی برای کاهش خستگی چشم',
		'excerpt'  => 'زاویه تابش نور کار، جلوگیری از انعکاس روی مانیتور و انتخاب شدت روشنایی مناسب برای روزهای کاری طولانی.',
		'category' => 'guides',
		'tags'     => array( 'روشنایی', 'کار در خانه', 'ارگونومی' ),
		'colors'   => array( '#b65d3d', '#173f35' ),
		'alt'      => 'تصویر انتزاعی برای نورپردازی اتاق کار خانگی',
		'caption'  => 'تنظیم موقعیت چراغ کار نسبت به مانیتور برای حفظ سلامت چشم.',
		'age_days' => 28,
		'body'     => '<p>ساعت‌های طولانی کار پشت کامپیوتر وقتی با نور نامناسب همراه شود، به سردرد و خستگی چشم ختم می‌شود. نورپردازی فضای کار خانگی قواعد مشخص و فنی دارد.</p><h2>سه قانون حیاتی برای زاویه نور</h2><ol><li><strong>منبع نور پشت مانیتور نباشد:</strong> کنتراست شدید نور پنجره یا لامپ با صفحه نمایش، چشم را به سرعت خسته می‌کند.</li><li><strong>نور از پهلو بتابد:</strong> چراغ مطالعه را در سمتی قرار دهید که سایه دست روی کاغذ یا کیبورد نیفتد (سمت چپ برای راست‌دست‌ها).</li><li><strong>لایه‌بندی با نور محیطی:</strong> هرگز در اتاق کاملاً تاریک فقط با نور مانیتور یا یک چراغ نقطه‌ای کار نکنید.</li></ol><div class="wp-block-columns"><div class="wp-block-column"><h3>چراغ خطی بالای مانیتور (ScreenBar)</h3><ul><li>بدون اشغال فضای روی میز</li><li>روشنایی مستقیم صفحه کیبورد بدون خیرگی روی مانیتور</li><li>دمای رنگ و شدت نور قابل تنظیم</li></ul></div><div class="wp-block-column"><h3>آباژور رومیزی با بازوی مفصلی</h3><ul><li>انعطاف در تغییر زاویه برای طراحی و نوشتن</li><li>افزودن بافت و زیبایی دکوراتیو به اتاق کار</li><li>امکان استفاده از لامپ‌های سرپیچ‌دار استاندارد</li></ul></div></div>',
	),

	array(
		'slug'     => 'checklist-buying-desk-chair',
		'title'    => 'چک‌لیست خرید میز و صندلی کار برای خانه',
		'excerpt'  => 'از ارتفاع دسته تا عمق نشیمن؛ مواردی که هنگام دیدن عکس محصول دیده نمی‌شوند اما روی استفاده‌ی روزانه اثر دارند.',
		'category' => 'guides',
		'tags'     => array( 'چک‌لیست', 'کار در خانه', 'ارگونومی' ),
		'colors'   => array( '#b65d3d', '#f6f2e9' ),
		'alt'      => 'تصویر انتزاعی برای میز کار خانگی',
		'caption'  => 'معیارهای ارگونومیک صندلی کار و تنظیم ارتفاع دسته‌ها.',
		'age_days' => 26,
		'body'     => '<p>میز کار فقط یک صفحه و چهار پایه نیست. قبل از خرید، این موارد را در استفاده واقعی بررسی کنید.</p><h2>پیش از پرداخت بررسی کنید</h2><ul><li>ارتفاع صفحه با آرنج در زاویه‌ی نزدیک به ۹۰ درجه هماهنگ باشد.</li><li>عمق صفحه دست‌کم ۶۰ سانتی‌متر برای قرارگیری مانیتور و کیبورد باشد.</li><li>پایه‌ها فضای زانو و چرخش پا را قطع نکنند.</li><li>چرخ‌های صندلی از جنس پلی‌اورتان نرم باشند تا روی کفپوش چوبی خط نیندازند.</li></ul><h2>سؤال‌هایی که از فروشنده بپرسید</h2><details class="wp-block-details"><summary>آیا ارتفاع دسته‌ها و گودی کمر قابل تنظیم است؟</summary><p>تنظیم گودی کمر برای نشستن‌های بیش از ۴ ساعت ضروری است. اگر صندلی ثابت است، پشتی آن باید انحنای ستون فقرات شما را کاملاً پر کند.</p></details><details class="wp-block-details"><summary>کلاس جک هیدرولیک صندلی چند است؟</summary><p>جک‌های کلاس ۴ استاندارد صنعتی محسوب می‌شوند و تحمل وزن تا ۱۵۰ کیلوگرم را بدون افت تدریجی تضمین می‌کنند.</p></details><blockquote class="wp-block-quote"><p>جمع‌بندی: راحتی نشستن مهم‌تر از ظاهر مینیمال در عکس است؛ محصول را ترجیحاً حضوری تست کنید.</p></blockquote>',
	),

	array(
		'slug'          => 'review-floor-lamp-reading',
		'title'         => 'بررسی تخصصی آباژور ایستاده برای گوشه‌ی مطالعه',
		'excerpt'       => 'یک بررسی ساختاریافته با معیار، امتیاز توضیح‌داده‌شده و محدودیت‌هایی که نباید در عکس محصول نادیده گرفت.',
		'category'      => 'guides',
		'tags'          => array( 'بررسی', 'روشنایی', 'گوشه مطالعه' ),
		'colors'        => array( '#9eab92', '#b65d3d' ),
		'alt'           => 'تصویر انتزاعی برای بررسی آباژور ایستاده',
		'caption'       => 'ارزیابی شعاع پخش نور و تعادل پایه آباژور ایستاده گوشه مطالعه.',
		'inline_figure' => array(
			'slug'    => 'fig-review-floor-lamp-angle',
			'colors'  => array( '#9eab92', '#b65d3d' ),
			'alt'     => 'زاویه تابش آباژور ایستاده',
			'caption' => 'شعاع پوشش نور آباژور ایستاده روی صندلی مطالعه و عدم ایجاد سایه دست.',
		),
		'age_days'      => 13,
		'body'          => '<p>این یک بررسی ساختاریافته در محیط آزمایشی است تا معیارهای انتخاب چراغ مطالعه ایستاده را در سناریوی واقعی بسنجیم.</p><div class="chidemoon-rating-box"><span class="chidemoon-rating-score">۷٫۵</span><div><h3>امتیاز ادیتوریال چیدمون: ۷٫۵ از ۱۰</h3><p>ارزیابی مبتنی بر کیفیت ساخت پایه، عدم لرزش، کنترل پخش نور و شعاع پوشش گوشه مطالعه.</p></div></div><figure class="wp-block-table"><table><thead><tr><th>معیار بررسی</th><th>امتیاز (از ۱۰)</th><th>توضیح تحریریه</th></tr></thead><tbody><tr><td>کیفیت بدنه و پایداری پایه</td><td>۸٫۵</td><td>پایه فلزی سنگین مانع واژگونی تصادفی می‌شود.</td></tr><tr><td>کنترل خیرگی نور</td><td>۷٫۰</td><td>کلاهک مات نور را نرم می‌کند اما هدایت جهت‌دار ندارد.</td></tr><tr><td>دسترسی به کلید روشن/خاموش</td><td>۶٫۵</td><td>کلید پایی روی سیم قرار دارد و گاهی زیر مبل پنهان می‌شود.</td></tr></tbody></table></figure><div class="wp-block-columns"><div class="wp-block-column"><h3>نقاط قوت</h3><ul><li>پایه‌ی کم‌جا با تعادل عالی</li><li>سازگاری با سرپیچ استاندارد E27</li><li>ارتفاع بهینه برای تابش نور از بالای شانه</li></ul></div><div class="wp-block-column"><h3>ملاحظات</h3><ul><li>فاقد دیمر داخلی تنظیم شدت نور</li><li>کلاهک پارچه‌ای مستعد جذب غبار در گذر زمان</li></ul></div></div><h2>سؤال‌های متداول</h2><details class="wp-block-details"><summary>چه لامپی برای این آباژور مناسب‌تر است؟</summary><p>لامپ ال‌ای‌دی فیلامنتی ۸ تا ۱۰ وات با دمای ۲۷۰۰ کلوین بهترین نور گرم و آرامش‌بخش را برای مطالعه شبانه می‌سازد.</p></details><div class="chidemoon-affiliate-disclosure"><h3>شفافیت ادیتوریال چیدمون</h3><p>بررسی‌ها مستقل از فروشندگان نوشته می‌شوند. در صورت خرید از طریق پیوندهای ما، ممکن است کمیسیون خریدی برای حفظ این پایگاه داده دریافت شود که تغییری در قیمت نهایی شما ایجاد نمی‌کند.</p></div><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	array(
		'slug'     => 'review-ergonomic-desk-chair',
		'title'    => 'بررسی صندلی کار ارگونومیک با پشتی تنفسی',
		'excerpt'  => 'بررسی میدانی صندلی کار ارگونومیک با پشتی مش تنفسی؛ دوام جک، زاویه نشیمن و راحتی ساعات طولانی کار.',
		'category' => 'guides',
		'tags'     => array( 'بررسی', 'کار در خانه', 'ارگونومی' ),
		'colors'   => array( '#173f35', '#65716b' ),
		'alt'      => 'تصویر انتزاعی بررسی صندلی کار ارگونومیک',
		'caption'  => 'پشتیبانی ارگونومیک انحنای ستون فقرات در ساعات مداوم کار.',
		'age_days' => 4,
		'body'     => '<p>این ارزیابی تخصصی برای سنجش قابلیت‌های صندلی‌های ارگونومیک خانگی در کاربری مداوم نگاشته شده است.</p><div class="chidemoon-rating-box"><span class="chidemoon-rating-score">۸٫۲</span><div><h3>امتیاز ادیتوریال چیدمون: ۸٫۲ از ۱۰</h3><p>امتیاز عالی در گردش هوای پشتی مش و مکانیسم قفل زاویه نشیمن؛ نیازمند بالشتک گردن ضخیم‌تر.</p></div></div><div class="wp-block-columns"><div class="wp-block-column"><h3>نقاط برجسته</h3><ul><li>پشتی توری ضدتعریق با فریم تقویت‌شده</li><li>دسته‌های سه‌بعدی با قابلیت تنظیم در سه جهت</li><li>چرخ‌های روان ژله‌ای بی‌صدا</li></ul></div><div class="wp-block-column"><h3>محدودیت‌ها</h3><ul><li>سفتی فوم نشیمن در هفته‌های اول استفاده</li><li>طراحی تماماً اداری که با برخی دکورهای سنتی هماهنگ نیست</li></ul></div></div><h2>پرسش‌های ارزیابی</h2><details class="wp-block-details"><summary>آیا برای افراد با قد بالای ۱۸۵ سانتی‌متر مناسب است؟</summary><p>کورس جک ۱۲ سانتی‌متری و تکیه‌گاه سر متحرک تا قد ۱۹۰ سانتی‌متر را به خوبی پوشش می‌دهد.</p></details><div class="chidemoon-affiliate-disclosure"><h3>شفافیت و سلب مسئولیت</h3><p>این ارزیابی صرفاً جنبه راهنمایی فنی دارد. قیمت، موجودی و گارانتی نهایی توسط فروشنده اصلی تأمین می‌شود.</p></div><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	// --- COMPARISONS ---
	array(
		'slug'          => 'compare-wood-metal-coffee-table',
		'title'         => 'میز جلومدی چوبی یا فلزی؟ مقایسه‌ای صادقانه',
		'excerpt'       => 'وزن، گرمای بصری، نگهداری و قیمت؛ همه‌ی تفاوت‌های مهم میز چوبی و فلزی در یک بررسی روبه‌رو.',
		'category'      => 'comparisons',
		'tags'          => array( 'مقایسه', 'میز جلومدی', 'نشیمن' ),
		'colors'        => array( '#102d26', '#e2b19d' ),
		'alt'           => 'تصویر انتزاعی تیره برای مقایسه میز جلومدی',
		'caption'       => 'مقایسه گرمای چوب طبیعی و استحکام پایه‌های فلزی در میز جلومدی.',
		'inline_figure' => array(
			'slug'    => 'fig-compare-wood-metal-materials',
			'colors'  => array( '#173f35', '#e2b19d' ),
			'alt'     => 'مقایسه بافت چوب و فلز',
			'caption' => 'تضاد بصری سطح چوب بلوط در برابر پایه فلزی با پوشش مات الکترواستاتیک.',
		),
		'age_days'      => 29,
		'body'          => '<p>میز جلومدی نقطه مرکزی ارتباط در نشیمن است؛ دور آن چای می‌نوشیم، کتاب می‌گذاریم و در طول روز مکرراً جابه‌جا می‌شود. این مقایسه بر اساس تجربه‌ی واقعی نگهداری نگاشته شده است.</p><figure class="wp-block-table"><table><thead><tr><th>معیار سنجش</th><th>میز تمام چوب طبیعی</th><th>میز فلزی با رنگ کوره‌ای</th></tr></thead><tbody><tr><td>گرمای بصری و حس لمس</td><td>بسیار گرم و اصیل</td><td>خنک، مدرن و صنعتی</td></tr><tr><td>مقاومت در برابر آب و لکه</td><td>نیازمند زیرلیوانی فوری و مراقبت</td><td>ضدلک و شست‌وشوی آسان</td></tr><tr><td>دوام در برابر ضربه و خط</td><td>امکان سنباده‌کاری و ترمیم خانگی</td><td>مقاوم به خط، غیرقابل ترمیم در خانه</td></tr><tr><td>وزن و جابه‌جایی روزمره</td><td>سنگین و نیازمند دو نفر برای جابه‌جایی</td><td>سبک، جابه‌جایی سریع و راحت</td></tr></tbody></table></figure><div class="wp-block-columns"><div class="wp-block-column"><h3>چه زمانی چوب بخریم؟</h3><ul><li>وقتی نشیمن شما نیازمند گرما و بافت طبیعی است.</li><li>وقتی میز وسط نقطه ثقل دائمی است و مدام جابه‌جا نمی‌شود.</li></ul></div><div class="wp-block-column"><h3>چه زمانی فلز بخریم؟</h3><ul><li>در خانه‌های پرتردد یا با حضور کودکان خردسال.</li><li>در فضاهای کوچک که پایه‌های نازک فلزی مانع انسداد نور می‌شوند.</li></ul></div></div><blockquote class="wp-block-quote"><p>نتیجه‌گیری چیدمون: اگر هر دو مزیت را می‌خواهید، ترکیب پایه‌های باریک فلزی مشکی با صفحه چوب بلوط روکش‌شده بهترین انتخاب میانه است.</p></blockquote>',
	),

	array(
		'slug'          => 'compare-bookshelf-open-closed',
		'title'         => 'کتابخانه‌ی باز یا دردار؛ کدام برای خانه‌ی شلوغ بهتر است؟',
		'excerpt'       => 'دسترسی، گردوغبار، انعطاف چیدمان و هزینه‌ی نگهداری را در یک مقایسه‌ی چهارمعیاره کنار هم گذاشته‌ایم.',
		'category'      => 'comparisons',
		'tags'          => array( 'مقایسه', 'نشیمن', 'کتابخانه' ),
		'colors'        => array( '#173f35', '#e2b19d' ),
		'alt'           => 'تصویر انتزاعی برای مقایسه کتابخانه باز و دردار',
		'caption'       => 'مدیریت نظم و انبار در مقایسه با نمایش دکوراتیو کتاب‌ها.',
		'inline_figure' => array(
			'slug'    => 'fig-compare-bookshelf-modular',
			'colors'  => array( '#102d26', '#9eab92' ),
			'alt'     => 'مقایسه قفسه باز و دردار',
			'caption' => 'تفکیک بخش نمایش دکوراتیو باز از فضای نگهداری وسایل دردار.',
		),
		'age_days'      => 24,
		'body'          => '<p>کتابخانه باز بخشی از دکوراسیون نمایشی است و مدل دردار بخشی از مدیریت نظم و انبار خانه. هیچ‌کدام برنده مطلق نیستند؛ پاسخ به حوصله شما در نظافت بستگی دارد.</p><figure class="wp-block-table"><table><thead><tr><th>معیار</th><th>کتابخانه باز (شلف)</th><th>کتابخانه دردار (کمدی/شیشه‌ای)</th></tr></thead><tbody><tr><td>دسترسی به کتاب‌ها</td><td>سریع و مستقیم با یک حرکت</td><td>نیازمند باز کردن در</td></tr><tr><td>حفاظت در برابر غبار</td><td>کم؛ نیازمند گردگیری هفتگی</td><td>عالی؛ نظافت ماهانه یا فصلی</td></tr><tr><td>تأثیر بر شلوغی بصری</td><td>نمایان شدن جلدها و اشیاء</td><td>سطح یکدست و آرام‌بخش</td></tr><tr><td>هزینه تمام‌شده</td><td>مقرون‌به‌صرفه با ساختار سبک</td><td>گران‌تر به دلیل یراق‌آلات و لولاها</td></tr></tbody></table></figure><blockquote class="wp-block-quote"><p>فرمول پیشنهادی چیدمون: دو طبقه پایینی دردار برای پوشه‌ها و جعبه‌ها، و سه طبقه بالایی باز برای کتاب‌های خواندنی و گلدان‌های کوچک.</p></blockquote>',
	),

	array(
		'slug'          => 'compare-cotton-linen-bedding',
		'title'         => 'روتختی کتان یا پنبه‌ای؟ انتخاب بر اساس فصل و عادت خواب',
		'excerpt'       => 'تنفس‌پذیری، چروک، شست‌وشو و حس لمس؛ چهار تفاوتی که در توضیح کوتاه محصول پنهان می‌ماند.',
		'category'      => 'comparisons',
		'tags'          => array( 'منسوجات', 'اتاق خواب', 'مقایسه' ),
		'colors'        => array( '#65716b', '#f6f2e9' ),
		'alt'           => 'تصویر انتزاعی برای مقایسه روتختی کتان و پنبه',
		'caption'       => 'تنفس‌پذیری و حس لمس الیاف طبیعی کتان در برابر پنبه ساتین.',
		'inline_figure' => array(
			'slug'    => 'fig-compare-bedding-textures',
			'colors'  => array( '#65716b', '#f6f2e9' ),
			'alt'     => 'مقایسه بافت کتان و پنبه',
			'caption' => 'تفاوت بافت متخلخل کتان ارگانیک در برابر بافت نرم و فشرده پنبه ساتین.',
		),
		'age_days'      => 18,
		'body'          => '<p>برای مقایسه‌ی منسوجات خواب، تنها درصد الیاف روی برچسب کافی نیست؛ بافت، رفتار با رطوبت و تغییر پس از شست‌وشو معیارهای اصلی هستند.</p><figure class="wp-block-table"><table><thead><tr><th>ویژگی</th><th>کتان طبیعی (Linen)</th><th>پنبه ساتین (Cotton Sateen)</th></tr></thead><tbody><tr><td>حس اولیه لمس</td><td>کمی زبر و بافت‌دار (با هر شست‌وشو نرم‌تر می‌شود)</td><td>فوق‌العاده نرم و صیقلی از روز اول</td></tr><tr><td>تنفس‌پذیری و خنکی</td><td>استثنایی؛ دفع سریع تعریق شبانه</td><td>خوب و مطبوع برای چهار فصل</td></tr><tr><td>رفتار در برابر چروک</td><td>چروک‌پذیری طبیعی و شیک</td><td>صاف‌تر و نیازمند اتوی سبک</td></tr></tbody></table></figure><div class="wp-block-columns"><div class="wp-block-column"><h3>کتان برای چه کسانی؟</h3><ul><li>افرادی که در خواب گرمشان می‌شود.</li><li>علاقه‌مندان به سبک زندگی روستیک و بوهمین.</li></ul></div><div class="wp-block-column"><h3>پنبه برای چه کسانی؟</h3><ul><li>علاقه‌مندان به ملحفه‌های صاف، نرم و اتوکشیده.</li><li>پوست‌های بسیار حساس به بافت‌های درشت.</li></ul></div></div>',
	),

	array(
		'slug'          => 'compare-dining-chair-wood-fabric',
		'title'         => 'صندلی ناهارخوری تمام‌چوب یا با روکش پارچه؟',
		'excerpt'       => 'دوام اسفنج، تمیزکاری لکه‌های غذا و راحتی در نشستن طولانی؛ مقایسه صندلی تمام‌چوب و صندلی پارچه‌ای.',
		'category'      => 'comparisons',
		'tags'          => array( 'مقایسه', 'ناهارخوری', 'صندلی' ),
		'colors'        => array( '#173f35', '#b65d3d' ),
		'alt'           => 'تصویر انتزاعی مقایسه صندلی ناهارخوری چوبی و پارچه‌ای',
		'caption'       => 'دوام در برابر لکه غذا در مقایسه با ارگونومی نشستن طولانی.',
		'inline_figure' => array(
			'slug'    => 'fig-compare-dining-chairs-detail',
			'colors'  => array( '#173f35', '#b65d3d' ),
			'alt'     => 'مقایسه ساختار صندلی چوبی و پارچه‌ای',
			'caption' => 'انحنای پشتی چوب راش در برابر نشیمن اسفنجی با پارچه ضدلک.',
		),
		'age_days'      => 14,
		'body'          => '<p>میز ناهارخوری محل گردهمایی خانواده و مهمانی‌های طولانی است؛ اما تفاوت صندلی تمام‌چوب و صندلی پارچه‌ای در چیست؟</p><figure class="wp-block-table"><table><thead><tr><th>معیار</th><th>صندلی چوبی فرم‌داده‌شده</th><th>صندلی با نشیمن پارچه‌ای</th></tr></thead><tbody><tr><td>راحتی در نشستن طولانی</td><td>متوسط (قابل حل با تشکچه جداشونده)</td><td>بسیار راحت و ارگونومیک</td></tr><tr><td>پاک‌کردن لکه غذا و چربی</td><td>فوری با دستمال مرطوب</td><td>نیازمند اسپری لکه‌بر و مراقبت</td></tr><tr><td>طول عمر مفید</td><td>ده‌ها سال بدون افت کیفیت</td><td>نیازمند رویه‌کوبی مجدد پس از ۵ سال</td></tr></tbody></table></figure><blockquote class="wp-block-quote"><p>توصیه ادیتوریال: اگر فرزند کوچک دارید، صندلی تمام‌چوب با تشکچه‌های بنددارِ قابل شست‌وشو در ماشین لباسشویی بهترین راه‌حل بدون استرس است.</p></blockquote>',
	),

	array(
		'slug'          => 'compare-foam-spring-mattress',
		'title'         => 'تشک فوم مموری یا تشک فنری منفصل؟',
		'excerpt'       => 'پشتیبانی کمر، گرما، عمر مفید و انتقال حرکت؛ مقایسه‌ی دو فناوری رایج تشک برای تصمیم‌گیری مطمئن.',
		'category'      => 'comparisons',
		'tags'          => array( 'مقایسه', 'اتاق خواب', 'تشک' ),
		'colors'        => array( '#65716b', '#b65d3d' ),
		'alt'           => 'تصویر انتزاعی خاکستری برای مقایسه تشک',
		'caption'       => 'تقسیم فشار وزن و انتقال حرکت در تشک‌های دونفره.',
		'inline_figure' => array(
			'slug'    => 'fig-compare-mattress-core',
			'colors'  => array( '#65716b', '#b65d3d' ),
			'alt'     => 'برش لایه‌های تشک فوم و فنر منفصل',
			'caption' => 'برش مهندسی لایه‌های فوم حافظه‌دار در برابر هسته فنرهای پاکتی منفصل.',
		),
		'age_days'      => 9,
		'body'          => '<p>یک‌سوم عمر را در خواب سپری می‌کنیم، با این حال خرید تشک اغلب با کمترین تحقیق ممکن انجام می‌شود. این بررسی دو فناوری مدرن را در ۴ شاخص می‌سنجد.</p><figure class="wp-block-table"><table><thead><tr><th>شاخص</th><th>فوم مموری با چگالی بالا</th><th>فنری پاکتی منفصل (Pocket Spring)</th></tr></thead><tbody><tr><td>تقسیم فشار وزن بدن</td><td>قالب‌گیری دقیق نقاط فشار شانه و باسن</td><td>حمایت ارتجاعی و فعال از ستون فقرات</td></tr><tr><td>انتقال حرکت در تخت دونفره</td><td>صفر؛ چرخش یک نفر دیگری را بیدار نمی‌کند</td><td>بسیار کم به دلیل استقلال فنرها</td></tr><tr><td>گردش هوا و دمای خواب</td><td>گرما را نگه می‌دارد (نیازمند ژل خنک‌کننده)</td><td>گردش هوای عالی و خنک</td></tr></tbody></table></figure><blockquote class="wp-block-quote"><p>نکته خرید: تشک را پیش از انتخاب نهایی حداقل ۱۵ دقیقه با لباس راحتی در وضعیت معمول خوابتان امتحان کنید.</p></blockquote>',
	),

	array(
		'slug'          => 'compare-round-rect-dining-table',
		'title'         => 'میز ناهارخوری گرد یا مستطیل؟',
		'excerpt'       => 'ظرفیت مهمان، مسیر عبور و صمیمیت سر سفره؛ راهنمای انتخاب فرم میز ناهارخوری بر اساس متراژ خانه.',
		'category'      => 'comparisons',
		'tags'          => array( 'مقایسه', 'ناهارخوری', 'چیدمان' ),
		'colors'        => array( '#173f35', '#f6f2e9' ),
		'alt'           => 'تصویر انتزاعی سبز و کرم برای مقایسه میز ناهارخوری',
		'caption'       => 'هندسه میز ناهارخوری و تأثیر آن بر گردش روان فضا.',
		'inline_figure' => array(
			'slug'    => 'fig-compare-dining-table-geometry',
			'colors'  => array( '#173f35', '#f6f2e9' ),
			'alt'     => 'پلان گردش صندلی دور میز گرد و مستطیل',
			'caption' => 'شعاع حرکت صندلی‌ها و فاصله ۹۰ سانتی‌متری تا دیوار در دو فرم میز.',
		),
		'age_days'      => 3,
		'body'          => '<p>فرم میز ناهارخوری مشخص می‌کند که آشپزخانه و نشیمن چقدر روان نفس بکشند. گرد یا مستطیل؟ این تصمیم بیش از سلیقه، به هندسه فضا وابسته است.</p><figure class="wp-block-table"><table><thead><tr><th>ویژگی</th><th>میز ناهارخوری گرد</th><th>میز ناهارخوری مستطیل</th></tr></thead><tbody><tr><td>صمیمیت و تعامل افراد</td><td>دید مستقیم و برابر همه به یکدیگر</td><td>حس سلسله‌مراتب در سر و کنار میز</td></tr><tr><td>انعطاف در فضاهای مربع</td><td>عالی؛ بدون گوشه‌های تیز و مسدودکننده</td><td>فضای کناری را محدود می‌کند</td></tr><tr><td>قابلیت اتصال به دیوار</td><td>ناممکن؛ باید وسط فضا بایستد</td><td>آسان؛ امکان چسباندن با نیمکت دیواری</td></tr></tbody></table></figure><blockquote class="wp-block-quote"><p>قاعده طلایی: دور تا دور هر میز ناهارخوری دست‌کم ۹۰ سانتی‌متر برای عقب کشیدن راحت صندلی‌ها در نظر بگیرید.</p></blockquote>',
	),

	// --- ROOM IDEAS & SHOP THE LOOK ---
	array(
		'slug'          => 'look-warm-bedroom-layering',
		'title'         => 'اتاق خواب گرم و آرام با سه لایه‌ی بافتی',
		'excerpt'       => 'ترکیب نور، بافت و ارتفاع برای اتاق خوابی که خلوت است اما سرد و بی‌روح دیده نمی‌شود.',
		'category'      => 'room-ideas',
		'tags'          => array( 'shop-the-look', 'اتاق خواب', 'رنگ خنثی' ),
		'colors'        => array( '#b65d3d', '#9eab92' ),
		'alt'           => 'تصویر انتزاعی برای اتاق خواب گرم',
		'caption'       => 'ترکیب ادیتوریال سه لایه منسوجات خاکی و نور گرم در اتاق خواب.',
		'inline_figure' => array(
			'slug'    => 'fig-look-bedroom-textiles',
			'colors'  => array( '#b65d3d', '#9eab92' ),
			'alt'     => 'ترکیب سه‌لایه منسوجات اتاق خواب',
			'caption' => 'هماهنگی کتان ارگانیک، شال مبل پشمی و نور ۲۷۰۰ کلوین پاتختی.',
		),
		'age_days'      => 25,
		'products'      => array( 'linen-duvet-sepidar', 'table-lamp-sunset', 'rug-kashan-runner' ),
		'body'          => '<p>این ترکیب ادیتوریال از یک اتاق خواب با دیوارهای خنثی شروع می‌شود. هدف، افزودن گرما بدون انباشتن وسایل است.</p><h2>سه لایه‌ی اصلی در این چیدمان</h2><ol><li><strong>لایه‌ی بستر:</strong> روتختی کتان ارگانیک با شید رنگی شنی و روبالشی‌های خاکی.</li><li><strong>لایه‌ی روشنایی:</strong> آباژور سرامیکی با نور ۲۷۰۰ کلوین در کنار تخت برای مطالعه قبل خواب.</li><li><strong>لایه‌ی بافت پای تخت:</strong> شال مبل پشمی بافت‌درشت و یک پادری پشمی کوچک برای گرمی تماس پا هنگام برخاستن.</li></ol><div class="chidemoon-affiliate-disclosure"><h3>خرید کالاهای این ترکیب</h3><p>کالاهای پیشنهادی زیر در فروشگاه چیدمون با برچسب «از تصویر بخر» دسته‌بندی شده‌اند و مستقیماً به پیشنهاد بررسی‌شده فروشنده وصل می‌شوند.</p></div><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	array(
		'slug'          => 'look-japandi-dining',
		'title'         => 'ناهارخوری دنج به سبک جپندی با چوب روشن',
		'excerpt'       => 'آرامش چوب بلوط روشن در کنار گلدان‌های سرامیکی دست‌ساز؛ گوشه‌ای گرم برای وعده‌های خانوادگی.',
		'category'      => 'room-ideas',
		'tags'          => array( 'shop-the-look', 'ناهارخوری', 'سبک جپندی' ),
		'colors'        => array( '#9eab92', '#e2b19d' ),
		'alt'           => 'تصویر انتزاعی سبک جپندی برای ناهارخوری',
		'caption'       => 'ترکیب آرامش‌بخش چوب بلوط و نور خطی در ناهارخوری به سبک جپندی.',
		'inline_figure' => array(
			'slug'    => 'fig-look-japandi-dining-scene',
			'colors'  => array( '#9eab92', '#e2b19d' ),
			'alt'     => 'ترکیب ناهارخوری جپندی',
			'caption' => 'فرم گرد میز بلوط و لوستر آویز خطی با پخش‌کننده نور اپال.',
		),
		'age_days'      => 19,
		'products'      => array( 'coffee-table-round-oak', 'pendant-linear-arc', 'ceramic-vase-tappeh' ),
		'body'          => '<p>سبک جپندی حاصل تلفیق مینیمالیسم ژاپنی با گرمای دکوراسیون اسکاندیناوی است. در این ناهارخوری، فرم‌های ساده با بافت‌های دست‌ساز طبیعی ترکیب شده‌اند.</p><h2>اجزای کلیدی این فضا</h2><ul><li>میز گرد بلوط مات با قطر ۹۰ سانتی‌متر</li><li>لوستر آویز خطی با پخش‌کننده نور اپال مات</li><li>گلدان سرامیکی با شاخه‌های خشک گندم و زیتون</li></ul><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	array(
		'slug'     => 'look-flexible-rental-living-room',
		'title'    => 'نشیمن اجاره‌ای؛ چیدمانی که با خانه‌ی بعدی هم می‌آید',
		'excerpt'  => 'بدون سوراخ‌کاری و بازسازی، با وسایل سبک و چند انتخاب قابل جابه‌جایی به خانه اجاره‌ای شخصیت بدهید.',
		'category' => 'room-ideas',
		'tags'     => array( 'shop-the-look', 'خانه‌ی اجاره‌ای', 'نشیمن' ),
		'colors'   => array( '#102d26', '#e2b19d' ),
		'alt'      => 'تصویر انتزاعی برای نشیمن خانه اجاره‌ای',
		'caption'  => 'چیدمان مدولار و جابه‌جایی‌پذیر برای خانه‌های استیجاری.',
		'age_days' => 10,
		'products' => array( 'floor-lamp-minimal-brass', 'rug-kashan-runner', 'wall-mirror-arch-bronze' ),
		'body'     => '<p>در خانه اجاره‌ای، هر خرید باید به این پرسش پاسخ دهد: آیا در خانه بعدی هم کاربرد دارد؟ به‌جای وسایل توکار سنگین، از عناصر سبک و مدولار بهره می‌بریم.</p><h2>سه قانون برای چیدمان خانه اجاره‌ای</h2><ul><li>تکیه دادن قاب‌های نقاشی به دیوار روی کنسول به جای سوراخ‌کاری مکرر دیوارها.</li><li>استفاده از آباژورهای ایستاده و رومیزی به جای تغییر سیم‌کشی لوسترهای سقفی.</li><li>انتخاب فرش‌های استاندارد ۶ متری که در اکثر نقشه‌های ساختمانی همخوانی دارند.</li></ul><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	array(
		'slug'          => 'look-reading-corner',
		'title'         => 'گوشه‌ی مطالعه‌ی دنج کنار پنجره',
		'excerpt'       => 'یک صندلی راحت، یک آباژور و یک میز کوچک؛ ترکیبی که گوشه‌ی خالی خانه را به محبوب‌ترین جای آن تبدیل می‌کند.',
		'category'      => 'room-ideas',
		'tags'          => array( 'shop-the-look', 'گوشه مطالعه', 'روشنایی' ),
		'colors'        => array( '#b65d3d', '#102d26' ),
		'alt'           => 'تصویر انتزاعی گرم و تیره برای گوشه مطالعه',
		'caption'       => 'گوشه دنج مطالعه با صندلی بوکل، آباژور سرامیکی و پادری دستباف.',
		'inline_figure' => array(
			'slug'    => 'fig-look-reading-corner-detail',
			'colors'  => array( '#b65d3d', '#102d26' ),
			'alt'     => 'گوشه مطالعه کنار پنجره',
			'caption' => 'صندلی بوکل، آباژور سرامیکی و پادری دستباف در قاب نور طبیعی.',
		),
		'age_days'      => 8,
		'products'      => array( 'sofa-armchair-nordic', 'table-lamp-sunset', 'rug-kashan-runner' ),
		'body'          => '<p>برای ساختن گوشه‌ی مطالعه، یک متر مربع کافی است. راز کار در سه انتخاب است: صندلی‌ای که یک ساعت در آن بنشینید و کمرتان خسته نشود، نوری که از روی شانه بتابد، و میزی برای فنجان و کتاب.</p><h2>چیدمان این فضا</h2><p>صندلی را با زاویه‌ی ۴۵ درجه رو به پنجره بگذارید تا در روز از نور طبیعی و در شب از چراغ کف‌خواب بهره ببرید. یک پادری گرد زیر صندلی مرز این گوشه را از بقیه‌ی اتاق جدا می‌کند.</p><ul><li>صندلی: تک‌نفره دسته‌دار با روکش بوکل</li><li>نور: لامپ ۲۷۰۰ کلوین با پخش غیرمستقیم</li><li>میز: میز گرد قطر ۳۵ تا ۴۰ سانتی‌متر</li><li>کف: پادری پشمی دستباف گرد</li></ul><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	array(
		'slug'     => 'look-minimal-entry',
		'title'    => 'ورودی مینیمال با جاکفشی دیواری',
		'excerpt'  => 'ورودی خانه اولین برخورد با فضای خصوصی شماست؛ ترکیبی جمع‌وجور با آینه، جاکفشی دیواری و یک نور گرم.',
		'category' => 'room-ideas',
		'tags'     => array( 'shop-the-look', 'ورودی', 'فضای کوچک' ),
		'colors'   => array( '#9eab92', '#102d26' ),
		'alt'      => 'تصویر انتزاعی سبز برای ورودی مینیمال',
		'caption'  => 'ترکیب فشرده آینه قدی قوسی و جاکفشی باریک دیواری.',
		'age_days' => 5,
		'products' => array( 'wall-mirror-arch-bronze', 'wall-sconce-warm-globe', 'wooden-tray-walnut' ),
		'body'     => '<p>ورودی کوچک معمولاً نقطه شلوغی خانه است: کفش، کلید و پالتو. راه‌حل در یک ترکیب دیواری ثابت و فشرده است.</p><h2>سه عنصر، یک دیوار</h2><p>جاکفشی دیواری باریک کف زمین را خالی نگه می‌دارد. بالای آن یک آینه تمام‌قد قوسی نصب کنید؛ هم فضا دوبرابر به چشم می‌آید و هم پیش از خروج آراستگی خود را چک می‌کنید.</p><ul><li>جاکفشی: دیواری با عمق کمتر از ۱۵ سانتی‌متر</li><li>آینه: قدی با فریم برنز مات</li><li>نور: دیوارکوب با حباب مات گرم</li></ul><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	array(
		'slug'     => 'look-balcony-refresh',
		'title'    => 'بالکن کوچک، حال‌وهوای کافه',
		'excerpt'  => 'با دو صندلی تاشو، ریسه‌ی نوری و گلدان‌های هم‌خانواده، بالکن دو متری به محبوب‌ترین کافه‌ی خانه تبدیل می‌شود.',
		'category' => 'room-ideas',
		'tags'     => array( 'shop-the-look', 'بالکن', 'فضای باز' ),
		'colors'   => array( '#e2b19d', '#9eab92' ),
		'alt'      => 'تصویر انتزاعی گرم برای بازسازی بالکن',
		'caption'  => 'میز و صندلی تاشو و گلدان‌های سرامیکی در بالکن کوچک.',
		'age_days' => 2,
		'products' => array( 'coffee-table-round-oak', 'ceramic-planter-sand', 'wooden-tray-walnut' ),
		'body'     => '<p>بالکن‌های کوچک با یک بازچینی ساده می‌توانند به دنج‌ترین فضای خانه برای صرف چای عصرانه تبدیل شوند.</p><h2>چیدمان کافه‌وار</h2><p>میز گرد کوچک با دو صندلی تاشو چوبی گزینه‌ای ایده‌آل هستند؛ تاشو بودن به شما اجازه می‌دهد در صورت نیاز فضا را باز کنید. ریسه نوری ملایم لبه سقف و گلدان‌های سرامیکی هم‌خانواده این ترکیب را کامل می‌کنند.</p><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	array(
		'slug'     => 'look-compact-home-office',
		'title'    => 'فضای کار جمع‌وجور در کنج اتاق خواب',
		'excerpt'  => 'چیدمان یک میز کار باریک، صندلی ارگونومیک جمع‌وجور و شلف دیواری در یک کنج یک‌متری از اتاق خواب.',
		'category' => 'room-ideas',
		'tags'     => array( 'shop-the-look', 'کار در خانه', 'اتاق خواب' ),
		'colors'   => array( '#173f35', '#f6f2e9' ),
		'alt'      => 'تصویر انتزاعی فضای کار جمع و جور',
		'caption'  => 'میز کار باریک و شلف دیواری برای تفکیک فضای کار در اتاق خواب.',
		'age_days' => 1,
		'products' => array( 'bookshelf-modular-teak', 'floor-lamp-minimal-brass', 'rocking-chair-classic' ),
		'body'     => '<p>وقتی اتاق مستقلی برای کار ندارید، کنج خالی اتاق خواب با انتخاب وسایل ظریف و مینیمال می‌تواند به یک ایستگاه کاری پربازده تبدیل شود.</p><h2>اصول تفکیک بصری</h2><p>میز کار باریک با عمق ۵۰ سانتی‌متر و پایه‌های ظریف را رو به دیوار قرار دهید. با نصب یک شلف دیواری برای لوازم‌التحریر و چراغ خطی بالای مانیتور، سطح میز را کاملاً آزاد نگه دارید.</p><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	// --- MORE BUYING GUIDES (real, criteria-driven editorial seed) ---
	array(
		'slug'          => 'guide-mattress-firmness',
		'title'         => 'راهنمای خرید تشک؛ سختی درست برای کمر شما کدام است؟',
		'excerpt'       => 'سختی تشک به وزن و حالت خواب شما وابسته است، نه به برچسب «اورتوپدیک»؛ معیارهای واقعی انتخاب هسته، اندازه و ضخامت را مرور می‌کنیم.',
		'category'      => 'guides',
		'tags'          => array( 'راهنمای خرید', 'تشک', 'اتاق خواب' ),
		'colors'        => array( '#65716b', '#f6f2e9' ),
		'alt'           => 'تصویر انتزاعی خاکستری برای راهنمای خرید تشک',
		'caption'       => 'تقسیم فشار بدن و نواحی پشتیبانی تشک بر اساس وزن و حالت خواب.',
		'inline_figure' => array(
			'slug'    => 'fig-guide-mattress-layers',
			'colors'  => array( '#65716b', '#f6f2e9' ),
			'alt'     => 'لایه‌های تشک و نواحی پشتیبانی بدن',
			'caption' => 'برش مفهومی لایه‌های راحتی و هسته‌ی پشتیبانی تشک در برابر نقاط فشار شانه و لگن.',
		),
		'age_days'      => 15,
		'body'          => '<p>تشک تنها کالای خانه است که هر شب هشت ساعت در تماس با بدن شماست؛ با این حال بیشتر خریدها با یک فشار دست روی ویترین انجام می‌شود. سختی درست، هسته‌ی مناسب و اندازه‌ی دقیق سه تصمیمی هستند که کیفیت خواب شما را برای هفت سال آینده تعیین می‌کنند.</p><h2>سختی تشک را با وزن بدن انتخاب کنید، نه با برچسب</h2><p>کلمه‌ی «اورتوپدیک» روی هیچ استاندارد مشخصی تکیه ندارد. معیار واقعی، سفتی سطح و تطابق آن با وزن و نقطه‌ی فشار بدن است: تشک نرم برای فرد سبک، فرورفتگی بیش از حد و کمر درد می‌سازد و تشک سفت برای فرد سنگین، شانه و لگن را بی‌پشتیبان رها می‌کند.</p><figure class="wp-block-table"><table><thead><tr><th>وزن بدن</th><th>سختی پیشنهادی</th><th>تجربه‌ی خواب مورد انتظار</th></tr></thead><tbody><tr><td>زیر ۶۰ کیلوگرم</td><td>نرم تا متوسط</td><td>قالب‌گیری نرم شانه بدون فشار روی لگن</td></tr><tr><td>۶۰ تا ۹۰ کیلوگرم</td><td>متوسط</td><td>تعادل پشتیبانی ستون فقرات و راحتی سطح</td></tr><tr><td>بالای ۹۰ کیلوگرم</td><td>متوسط تا سفت</td><td>مقاومت در برابر فرورفتگی و حفظ زاویه‌ی طبیعی کمر</td></tr></tbody></table></figure><h2>هسته‌ی تشک؛ فنری، فوم یا لاتکس؟</h2><div class="wp-block-columns"><div class="wp-block-column"><h3>فنر پاکتی منفصل</h3><ul><li>گردش هوای عالی برای گرم‌خواب‌ها</li><li>پشتیبانی فعال و وابسته به فنرهای مستقل</li><li>مناسب اتاق دونفره با خواب سبک به دلیل میرایی حرکت</li></ul></div><div class="wp-block-column"><h3>فوم سرد و مموری</h3><ul><li>بی‌صدا و بدون فنر، مناسب تخت‌های دوطبقه</li><li>قالب‌گیری از نقاط فشار برای دردهای مزمن</li><li>نیازمند لایه‌ی خنک‌کننده در اقلیم گرم</li></ul></div><div class="wp-block-column"><h3>لاتکس</h3><ul><li>بازگشت فوری و حس فنری طبیعی</li><li>ضدکنه و ضدقارچ به شکل طبیعی</li><li>عمر مفید طولانی‌تر با قیمت بالاتر</li></ul></div></div><h2>اندازه و ضخامت را روی کاغذ بررسی کنید</h2><p>تشک دونفره‌ی استاندارد ۱۶۰ در ۲۰۰ سانتی‌متر است؛ اگر اتاق کمتر از ۹ متر مربع فضای مفید دارد، تشک ۱۲۰ در ۲۰۰ مسیر عبور را نجات می‌دهد. ضخامت مفید همراه با زیرتشک دست‌کم ۲۲ و ترجیحاً ۲۵ تا ۳۰ سانتی‌متر باشد تا لایه‌های راحتی عمق واقعی داشته باشند.</p><blockquote class="wp-block-quote"><p>نکته چیدمون: هر تشک تازه دوره‌ی «شکستن» سی‌شبانه دارد؛ سیاست تعویض یا بازگشت ۳۰ تا ۱۰۰ شبی فروشنده را پیش از پرداخت کتباً دریافت کنید.</p></blockquote><h2>پرسش‌های متداول</h2><details class="wp-block-details"><summary>تشک را هر چند سال یک‌بار باید عوض کرد؟</summary><p>هفت تا نه سال، یا همان لحظه‌ای که فرورفتگی دائمی بیش از دو سانتی‌متر در محل خواب شما ماندگار شد؛ فرورفتگی نشانه‌ی از دست رفتن لایه‌ی پشتیبانی است، نه صمیمیت با بدن شما.</p></details><details class="wp-block-details"><summary>تشک دو رو (نرم و سفت) خرید هوشمندانه‌ای است؟</summary><p>برای مهمان‌نوازی عالی است، اما هر رو کاربری خودش را زودتر از نواحی مشترک از دست می‌دهد؛ برای استفاده‌ی شخصی روزانه هسته‌ی ثابت پاسخ قابل اعتمادتری است.</p></details>',
	),

	array(
		'slug'          => 'guide-kitchen-lighting-layers',
		'title'         => 'نورپردازی آشپزخانه؛ سه لایه‌ای که سر آشپزی چشم را خسته نمی‌کند',
		'excerpt'       => 'نور عمومی، نور کار زیر کابینت و آویز جزیره؛ دمای رنگ درست، ارتفاع آویزها و شاخص نمود رنگ برای آشپزخانه‌ای که هم کاربردی است هم دنج.',
		'category'      => 'guides',
		'tags'          => array( 'راهنمای خرید', 'روشنایی', 'آشپزخانه' ),
		'colors'        => array( '#b65d3d', '#f6f2e9' ),
		'alt'           => 'تصویر انتزاعی گرم برای راهنمای نورپردازی آشپزخانه',
		'caption'       => 'سه لایه‌ی نور آشپزخانه و ارتفاع استاندارد آویز جزیره.',
		'inline_figure' => array(
			'slug'    => 'fig-guide-kitchen-pendant-island',
			'colors'  => array( '#b65d3d', '#f6f2e9' ),
			'alt'     => 'آویز خطی بالای جزیره آشپزخانه',
			'caption' => 'ارتفاع استاندارد آویز جزیره و پخش نور روی صفحه‌ی کار بدون خیرگی.',
		),
		'age_days'      => 12,
		'body'          => '<p>آشپزخانه تنها فضای خانه است که هم محل کار دقیق است و هم نقطه‌ی دورهم‌نشینی. یک طرح نور واحد و سقفی، هم کار روی صفحه‌ی برش را سایه‌دار می‌کند و هم شام را به زیر نور عملیاتی سرد می‌فرستد. راه‌حل، لایه‌بندی نور است.</p><h2>سه لایه‌ی نور آشپزخانه</h2><ol><li><strong>نور عمومی:</strong> روشنایی پایه برای گردش در فضا؛ با چند چراغ توکار یا آویز پراکنده.</li><li><strong>نور کاربری:</strong> نوار ال‌ای‌دی زیر کابینت برای روشن کردن صفحه‌ی کار بدون سایه‌ی دست.</li><li><strong>نور تأکیدی:</strong> آویزهای بالای جزیره یا میز صبحانه برای صمیمیت و تعریف کانون فضا.</li></ol><figure class="wp-block-table"><table><thead><tr><th>لایه‌ی نور</th><th>محل نصب</th><th>دمای رنگ</th><th>نکته‌ی اجرایی</th></tr></thead><tbody><tr><td>عمومی</td><td>سقف، پراکنده در دو ردیف</td><td>۳۰۰۰ کلوین</td><td>فاصله‌ی چراغ‌ها نصف ارتفاع سقف</td></tr><tr><td>کاربری</td><td>زیر کابینت بالایی</td><td>۴۰۰۰ کلوین</td><td>نوار را نزدیک لبه‌ی جلویی کابینت بچسبانید</td></tr><tr><td>تأکیدی</td><td>آویز بالای جزیره</td><td>۲۷۰۰ تا ۳۰۰۰ کلوین</td><td>پایین‌ترین نقطه‌ی آویز ۷۵ تا ۹۰ سانتی‌متر بالای صفحه</td></tr></tbody></table></figure><h2>ارتفاع آویزها بالای جزیره</h2><p>پایین‌ترین نقطه‌ی آویز باید ۷۵ تا ۹۰ سانتی‌متر با صفحه‌ی جزیره فاصله داشته باشد تا هم نور روی سطح برخورد کند و هم دید مهمان‌های روبه‌رو قطع نشود. برای جزیره‌ی بلند، سر آویزها را با فاصله‌ای برابر یک‌سوم طول جزیره بچینید.</p><h2>شاخص نمود رنگ را جدی بگیرید</h2><p>در آشپزخانه باید رنگ غذا واقعی دیده شود؛ لامپ‌های با شاخص نمود رنگ (CRI) بالای ۹۰ تفاوت گوشت تازه و پخته را واقعی نشان می‌دهند. پخش‌کننده‌ی اپال مات هم خیرگی مستقیم لامپ را حذف می‌کند و فضای آشپزی را آرام می‌کند.</p><blockquote class="wp-block-quote"><p>نکته چیدمون: نوار ال‌ای‌دی را در انتهای جلویی کابینت نصب کنید تا نور روی صفحه‌ی کار بیفتد؛ نصب عقب‌تر فقط پشت کابینت را روشن می‌کند و سایه‌ی بدن روی میز کار می‌ماند.</p></blockquote><h2>پرسش‌های متداول</h2><details class="wp-block-details"><summary>چقدر وات برای نوار زیر کابینت کافی است؟</summary><p>ده تا چهارده وات بر متر با دمای ۴۰۰۰ کلوین؛ اگر نوار نزدیک سینک و آب است، درجه‌ی حفاظت IP44 یا بالاتر انتخاب کنید.</p></details><details class="wp-block-details"><summary>آیا آویز شیشه‌ای رنگی برای آشپزخانه مناسب است؟</summary><p>به‌عنوان لایه‌ی تأکیدی بله، اما هرگز جای نور کاربری را نگیرد؛ رنگ شیشه، دمای رنگ واقعی و نمود رنگ غذا را تغییر می‌دهد.</p></details>',
	),

	array(
		'slug'          => 'guide-bedroom-bedding-layers',
		'title'         => 'لایه‌های خواب؛ روتختی، پتو و لحاف را چطور با هم انتخاب کنیم؟',
		'excerpt'       => 'از ملحفه تا پتوی تزئینی؛ انتخاب لایه‌به‌لایه‌ی منسوجات خواب بر اساس فصل، دمای بدن و سبک شست‌وشو.',
		'category'      => 'guides',
		'tags'          => array( 'راهنمای خرید', 'منسوجات', 'اتاق خواب' ),
		'colors'        => array( '#9eab92', '#e2b19d' ),
		'alt'           => 'تصویر انتزاعی سبز و شنی برای لایه‌بندی منسوجات خواب',
		'caption'       => 'لایه‌بندی منسوجات خواب بر اساس فصل و دمای بدن.',
		'inline_figure' => array(
			'slug'    => 'fig-guide-bedding-layers',
			'colors'  => array( '#9eab92', '#e2b19d' ),
			'alt'     => 'لایه‌های منسوجات بستر خواب',
			'caption' => 'ترتیب لایه‌های بستر از ملحفه تا پتوی تزئینی و نقش هر لایه در تنظیم دما.',
		),
		'age_days'      => 9,
		'body'          => '<p>خواب راحت نتیجه‌ی یک خرید بزرگ نیست؛ حاصل چهار لایه‌ای است که هر کدام وظیفه‌ی تنظیم دما، نرمی و نظم بستر را دارند. اگر فقط یکی از این لایه‌ها را اشتباه بخرید، بقیه هم بی‌اثر می‌شوند.</p><h2>چهار لایه‌ی بستر</h2><ol><li><strong>ملحفه‌ی کش‌دار:</strong> تنها لایه‌ای که تمام شب با پوست در تماس است.</li><li><strong>رووتختی و ملحفه‌ی بالایی:</strong> لایه‌ی قابل شست‌وشوی اصلی که بهداشت بستر را می‌سازد.</li><li><strong>لایه‌ی گرمی فصلی:</strong> لحاف، پتو یا پتوی نخی بر اساس فصل.</li><li><strong>لایه‌ی تزئینی:</strong> پتوی بافت و روبالشی اضافه برای بافت و لایه‌بندی بصری.</li></ol><figure class="wp-block-table"><table><thead><tr><th>فصل</th><th>لایه‌ی اصلی پیشنهادی</th><th>پارچه</th><th>رفتار دمایی</th></tr></thead><tbody><tr><td>تابستان</td><td>ملحفه و رووتختی کتان</td><td>کتان ۱۰۰٪</td><td>خنک با دفع سریع رطوبت</td></tr><tr><td>چهارفصل</td><td>ست پنبه‌ای تراکم بالا</td><td>پنبه پرکال یا ساتن</td><td>تعادل دما در شب‌های متغیر</td></tr><tr><td>زمستان</td><td>فلال یا پتوی پشمی لایه‌ای</td><td>پنبه پرزساخته و پشم</td><td>گرمای پایدار بدون عرق‌کردن</td></tr></tbody></table></figure><h2>برچسب را بخوانید: وزن پارچه و تراکم تار</h2><p>کیفیت منسوجات خواب روی برچسب نوشته شده است، نه در ظاهر: برای کتان گراماژ دست‌کم ۱۶۰ و برای پنبه تراکم ۳۰۰ نخ در اینچ مربع معیار قابل اعتماد است. عبارت «پیش‌شسته‌شده» روی کتان و پنبه یعنی پس از اولین شست‌وشوی خانگی آب‌رفتگی ندارید.</p><div class="wp-block-columns"><div class="wp-block-column"><h3>اگر گرم‌خواب هستید</h3><ul><li>کتان یا پنبه‌ی ساده به‌جای ساتن براق</li><li>دور از فلال و لحاف‌های پرحجم</li><li>پتوی نخی نازک به‌عنوان تنها لایه‌ی گرمی</li></ul></div><div class="wp-block-column"><h3>اگر سردخواب هستید</h3><ul><li>فلال یا پتوی پشمی بافت‌درشت به‌عنوان لایه‌ی میانی</li><li>رووتختی دوم زیر ملحفه برای عایق بستر</li><li>لحاف با پوشش پنبه‌ای متراکم به‌جای الیاف مصنوعی سنگین</li></ul></div></div><blockquote class="wp-block-quote"><p>نکته چیدمون: ست‌های چهار تکه اقتصادی‌اند اما روبالشی‌شان معمولاً ضعیف‌ترین عضو ست است؛ پارچه‌ی روبالشی را جدا از برچسب کلی ست بسنجید.</p></blockquote><h2>پرسش‌های متداول</h2><details class="wp-block-details"><summary>چرا کتان بعد از چند بار شست‌وشو بهتر می‌شود؟</summary><p>الیاف کتان در هر شست‌وشو نرم‌تر و شل‌تر می‌شود؛ این رفتار طبیعی پارچه است و نشانه‌ی افت کیفیت نیست.</p></details><details class="wp-block-details"><summary>یک ست خوب چقدر باید دوام بیاورد؟</summary><p>با شست‌وشوی ۳۰ درجه و خشک‌کردن در سایه، ست پنبه‌ای خوب سه تا پنج سال کار می‌کند و کتان با هر شست‌وشو بهتر می‌شود.</p></details>',
	),

	array(
		'slug'          => 'guide-indoor-planter-buying',
		'title'         => 'راهنمای خرید گلدان گیاهان آپارتمانی؛ زهکش، جنس و اندازه',
		'excerpt'       => 'گلدان قشنگی که ریشه را خفه کند دوام نمی‌آورد؛ قاعده‌ی قطر دهانه، سوراخ زهکش و رفتار آب در سرامیک، سفال و پلاستیک.',
		'category'      => 'guides',
		'tags'          => array( 'راهنمای خرید', 'دکور', 'گیاهان آپارتمانی' ),
		'colors'        => array( '#102d26', '#9eab92' ),
		'alt'           => 'تصویر انتزاعی سبز تیره برای راهنمای خرید گلدان',
		'caption'       => 'تناسب قطر دهانه‌ی گلدان، زهکش و ارتفاع گیاه.',
		'inline_figure' => array(
			'slug'    => 'fig-guide-planter-drainage',
			'colors'  => array( '#102d26', '#9eab92' ),
			'alt'     => 'زهکش و تناسب اندازه گلدان',
			'caption' => 'تناسب قطر دهانه‌ی گلدان با ریشه، سوراخ تخلیه و زیرگلدانی.',
		),
		'age_days'      => 6,
		'body'          => '<p>بیشتر گیاهان آپارتمانی نه از کم‌آبی و نه از نور کم، بلکه از گلدان اشتباه از پا درمی‌آیند: گلدانی بدون زهکش، یا بزرگ‌تر از حدی که خاکش هفته‌ها خیس می‌ماند. انتخاب گلدان یک تصمیم فنی است که می‌توان شکل آن را هم شیک گرفت.</p><h2>قاعده‌ی اندازه: یک پله بزرگ‌تر، نه بیشتر</h2><p>قطر گلدان تازه فقط دو تا چهار سانتی‌متر بیشتر از قطر گلدان فعلی باشد؛ در گلدان بزرگ‌تر، ریشه نمی‌تواند رطوبت خاک را مصرف کند و پوسیدگی ریشه شروع می‌شود. گیاهان ریشه‌بلند مثل سانسوریا به گلدان عمیق و گیاهان پخش‌شونده مثل پوتوس به گلدان پهن‌تر نیاز دارند.</p><figure class="wp-block-table"><table><thead><tr><th>نوع گیاه</th><th>فرم گلدان</th><th>جنس پیشنهادی</th><th>نکته‌ی زهکش</th></tr></thead><tbody><tr><td>سانسوریا و آگلونما</td><td>باریک و بلند</td><td>سرامیک سنگین برای تعادل برگ‌های بلند</td><td>سوراخ تخلیه الزامی</td></tr><tr><td>فیکوس و کراسولا</td><td>عمق متوسط با قاعده‌ی پهن</td><td>سفال بدون لعاب برای تنفس ریشه</td><td>زیرگلدانی جداگانه</td></tr><tr><td>پوتوس و شاخه‌های آویز</td><td>آویز یا استوانه‌ای بلند</td><td>پلاستیک سبک برای جابه‌جایی</td><td>لایه‌ی لیکا در کف</td></tr></tbody></table></figure><h2>جنس گلدان رفتار آب را تغییر می‌دهد</h2><p>سفال بدون لعاب رطوبت را تبخیر می‌کند و برای گیاهانی که خیس‌خوبی را تحمل نمی‌کنند ایده‌آل است؛ سرامیک لعاب‌دار آب را نگه می‌دارد و برای گیاهان تشنه بهتر است؛ پلاستیک ارزان و سبک است اما در آفتاب مستقیم عمر کوتاه‌تری دارد.</p><h2>گلدان بدون سوراخ فقط کاور است</h2><p>گلدان‌های بدون سوراخ تخلیه را کاور در نظر بگیرید: گیاه را در گلدان داخلی سوراخ‌دار بکارید و کاور را دور آن بگذارید. لایه‌ی لیکا در کف گلدان بی‌سوراخ جای زهکش واقعی را نمی‌گیرد؛ رطوبت انباشته همان‌جا می‌ماند.</p><blockquote class="wp-block-quote"><p>نکته چیدمون: قبل از خرید، زیرگلدانی هم‌خانواده را هم بشمارید؛ گلدانی با زیرگلدانی یکپارچه به میز چوبی شما بیش از هر چیز وفادار می‌ماند.</p></blockquote><h2>پرسش‌های متداول</h2><details class="wp-block-details"><summary>هر چند وقت یک‌بار باید گلدان را بزرگ‌تر کرد؟</summary><p>گیاهان جوان هر سال و گیاهان بالغ هر دو سال، ترجیحاً در بهار؛ بیرون‌زدگی ریشه از سوراخ زهکش زنگ تعویض است.</p></details><details class="wp-block-details"><summary>گلدان سرامیکی دست‌ساز چرا گران‌تر است؟</summary><p>پخت دو مرحله‌ای و لعاب دستی هدررفت تولید را بالا می‌برد؛ در عوض لعاب مات آن سال‌ها براق‌زدگی نمی‌گیرد و بافتش دست‌نخورده می‌ماند.</p></details>',
	),

	// --- MORE COMPARISONS (evidence-based seed for archive pagination) ---
	array(
		'slug'          => 'compare-oak-walnut-wood',
		'title'         => 'مبلمان بلوط یا گردو؟ تفاوت رنگ، مقاومت و قیمت',
		'excerpt'       => 'هر دو چوب سخت و بادوام‌اند اما در رنگ، رفتار با پرداخت و قیمت تفاوت‌هایی دارند که در عکس فروشگاهی دیده نمی‌شود.',
		'category'      => 'comparisons',
		'tags'          => array( 'مقایسه', 'مبلمان', 'چوب' ),
		'colors'        => array( '#173f35', '#e2b19d' ),
		'alt'           => 'تصویر انتزاعی مقایسه چوب بلوط و گردو',
		'caption'       => 'مقایسه‌ی رگه‌ی مستقیم بلوط و رگه‌های مواج گردو.',
		'inline_figure' => array(
			'slug'    => 'fig-compare-oak-walnut-grain',
			'colors'  => array( '#173f35', '#e2b19d' ),
			'alt'     => 'مقایسه بافت چوب بلوط و گردو',
			'caption' => 'تفاوت رگه‌ی مستقیم بلوط و رگه‌های مواج گردو در پرداخت روغنی.',
		),
		'age_days'      => 17,
		'body'          => '<p>بلوط و گردو دو چوب محبوب مبلمان‌اند؛ هر دو سخت و بادوام‌اند اما در رنگ پایه، وزن و رفتار در برابر روغن و لاک تفاوت‌هایی دارند که پس از تحویل مبلمان مشخص می‌شوند، نه در ویترین.</p><figure class="wp-block-table"><table><thead><tr><th>معیار</th><th>بلوط</th><th>گردو</th></tr></thead><tbody><tr><td>رنگ پایه و رگه</td><td>کرم تا قهوه‌ای روشن با رگه‌های برجسته</td><td>شکلاتی تیره با رگه‌های مواج و نرم</td></tr><tr><td>رفتار با روغن و لاک</td><td>روغن‌خوردگی گرم و طبیعی می‌گیرد</td><td>با روغن شفاف عمق رنگ تیره‌تر و شیشه‌ای‌تر می‌شود</td></tr><tr><td>وزن و جابه‌جایی</td><td>سنگین‌تر و متراکم‌تر</td><td>کمی سبک‌تر با بافت راحت‌تر در خراطی</td></tr><tr><td>قیمت چوب خام</td><td>ارزان‌تر و دسترس‌پذیرتر</td><td>گران‌تر؛ گره‌های تزئینی قیمت را بالا می‌برد</td></tr></tbody></table></figure><div class="wp-block-columns"><div class="wp-block-column"><h3>چه زمانی بلوط بخریم؟</h3><ul><li>در فضاهای روشن با سبک اسکاندیناوی و مینیمال</li><li>برای سطح‌های پرمخاطب مثل میز ناهارخوری که ترمیم روغنی ساده دارد</li><li>وقتی بودجه باید میان چند قطعه تقسیم شود</li></ul></div><div class="wp-block-column"><h3>چه زمانی گردو بخریم؟</h3><ul><li>برای کنتراست با مبل کرم و دیوارهای روشن</li><li>در سبک کلاسیک‌مدرن و اشیای خراطی‌شده مثل سینی و کنسول</li><li>وقتی عمق رنگ و وقار مهم‌تر از قیمت است</li></ul></div></div><blockquote class="wp-block-quote"><p>جمع‌بندی چیدمون: اگر خانه نور کم و مبل تیره دارد، بلوط روغن‌خورده فضا را روشن‌تر می‌کند؛ اگر به دنبال عمق رنگ هستید، گردو تنها چوبی است که با گذر سال‌ها زیباتر می‌شود.</p></blockquote>',
	),

	array(
		'slug'     => 'compare-dimmer-smart-bulb',
		'title'    => 'دیمر یا لامپ هوشمند؟ دو راه برای نور تطبیقی خانه',
		'excerpt'  => 'هر دو شدت نور را تنظیم می‌کنند اما یکی با مدار دیواری و دیگری با اپ و صدا؛ تفاوت اصلی در هزینه، وابستگی و حس استفاده است.',
		'category' => 'comparisons',
		'tags'     => array( 'مقایسه', 'روشنایی', 'خانه هوشمند' ),
		'colors'   => array( '#b65d3d', '#102d26' ),
		'alt'      => 'تصویر انتزاعی مقایسه دیمر و لامپ هوشمند',
		'caption'  => 'دو مسیر کنترل شدت نور در خانه.',
		'age_days' => 11,
		'body'     => '<p>هر دو راه‌حل نور را تطبیقی می‌کنند اما یکی با اهرم روی دیوار کار می‌کند و دیگری با اپ و فرمان صوتی. تفاوت اصلی در هزینه‌ی راه‌اندازی، اتکا به شبکه و حس استفاده است.</p><figure class="wp-block-table"><table><thead><tr><th>معیار</th><th>دیمر دیواری</th><th>لامپ هوشمند</th></tr></thead><tbody><tr><td>هزینه‌ی راه‌اندازی</td><td>نیازمند تعویض کلید و گاهی سیم‌کشی</td><td>فقط تعویض لامپ یا سرپیچ</td></tr><tr><td>کنترل</td><td>اهرم یا لمس فیزیکی بدون واسطه</td><td>اپ، صدا و سناریوهای زمان‌بندی</td></tr><tr><td>وابستگی</td><td>به شبکه‌ی اینترنت وابسته نیست</td><td>بدون اینترنت پایدار بی‌فرمان می‌ماند</td></tr><tr><td>سازگاری لامپ</td><td>فقط لامپ‌های دیمردار</td><td>هر لامپ هم‌پروتکل وای‌فای یا زی‌بی</td></tr></tbody></table></figure><div class="wp-block-columns"><div class="wp-block-column"><h3>چه زمانی دیمر بخریم؟</h3><ul><li>در اتاق خواب و راهروهایی که در تاریکی با یک حرکت تنظیم می‌کنید</li><li>برای خانه‌هایی با مهمان‌های زیاد که با اپ آشنا نیستند</li><li>وقتی پایداری بدون اینترنت اولویت است</li></ul></div><div class="wp-block-column"><h3>چه زمانی لامپ هوشمند بخریم؟</h3><ul><li>در خانه‌ی اجاره‌ای بدون دست‌کاری کلیدها</li><li>برای سناریوهای صبح و شب و شبیه‌سازی حضور در سفر</li><li>وقتی کنترل از راه دور و صدا کاربرد واقعی دارد</li></ul></div></div><blockquote class="wp-block-quote"><p>نکته چیدمون: ترکیب هر دو عملی است؛ دیمر برای لایه‌ی نور عمومی و لامپ‌های هوشمند فقط در آباژورها و دیوارکوب‌های تأکیدی.</p></blockquote>',
	),

	array(
		'slug'          => 'compare-latex-memory-pillow',
		'title'         => 'بالش لاتکس یا مموری‌فوم؟ انتخاب بر اساس حالت خواب',
		'excerpt'       => 'هر دو پشتیبانی گردن را جدی می‌گیرند اما رفتارشان زیر سر شما متفاوت است: لاتکس فوری برمی‌گردد و مموری آرام قالب می‌گیرد.',
		'category'      => 'comparisons',
		'tags'          => array( 'مقایسه', 'منسوجات', 'اتاق خواب' ),
		'colors'        => array( '#65716b', '#9eab92' ),
		'alt'           => 'تصویر انتزاعی مقایسه بالش لاتکس و مموری‌فوم',
		'caption'       => 'رفتار دو فناوری بالش فومی در برابر فشار گردن.',
		'inline_figure' => array(
			'slug'    => 'fig-compare-pillow-support',
			'colors'  => array( '#65716b', '#9eab92' ),
			'alt'     => 'مقایسه پشتیبانی بالش لاتکس و مموری',
			'caption' => 'برگشت فوری لاتکس در برابر قالب‌گیری آهسته مموری‌فوم زیر گردن.',
		),
		'age_days'      => 7,
		'body'          => '<p>بالش کالایی است که هر شب امتحانش را پس می‌دهد؛ انتخاب بین لاتکس و مموری‌فوم بیش از آنکه به برند وابسته باشد به حالت خواب و دمای بدن شما وابسته است.</p><figure class="wp-block-table"><table><thead><tr><th>شاخص</th><th>لاتکس طبیعی</th><th>مموری‌فوم</th></tr></thead><tbody><tr><td>پاسخ به فشار</td><td>برگشت فوری و فنری</td><td>قالب‌گیری آهسته و ماندگار</td></tr><tr><td>گرما و تنفس</td><td>سلول‌های باز و خنک</td><td>گرما را بیشتر نگه می‌دارد</td></tr><tr><td>مقاومت در برابر حساسیت</td><td>ضدکنه و ضدقارچ طبیعی</td><td>با روکش ضدحساسیت قابل مدیریت</td></tr><tr><td>عمر مفید</td><td>پنج تا هفت سال با برگشت کامل</td><td>افت تدریجی پس از سه تا چهار سال</td></tr></tbody></table></figure><div class="wp-block-columns"><div class="wp-block-column"><h3>چه زمانی لاتکس بخریم؟</h3><ul><li>برای خواب پهلو و تغییر مداوم حالت در شب</li><li>برای گرم‌خواب‌ها و اتاق‌های گرم</li><li>وقتی عمر طولانی بالاتر از قیمت تمام‌شده اهمیت دارد</li></ul></div><div class="wp-block-column"><h3>چه زمانی مموری بخریم؟</h3><ul><li>برای گردن‌درد مزمن و خواب‌های ثابت</li><li>در اتاق‌های خنک و چهارفصل خنک</li><li>وقتی حس قالب‌گرفتن آرام‌بخش‌تر است</li></ul></div></div><blockquote class="wp-block-quote"><p>نکته خرید: ارتفاع بالش را با فاصله‌ی گردن تا سرشانه‌تان بسنجید؛ بالش درست همان است که سرتان را در خواب پهلو هم‌راستای ستون فقرات نگه دارد.</p></blockquote>',
	),

	array(
		'slug'          => 'compare-nordic-classic-armchair',
		'title'         => 'صندلی تک‌نفره اسکاندیناوی یا کلاسیک؟',
		'excerpt'       => 'انتخاب فرم تک‌نفره‌ی راحتی بر اساس متراژ نشیمن، سبک مبل اصلی و راحتی نشستن طولانی.',
		'category'      => 'comparisons',
		'tags'          => array( 'مقایسه', 'مبلمان', 'نشیمن' ),
		'colors'        => array( '#173f35', '#9eab92' ),
		'alt'           => 'تصویر انتزاعی مقایسه دو فرم صندلی تک‌نفره',
		'caption'       => 'مقایسه‌ی فرم باریک اسکاندیناوی و فرم دسته‌بلند کلاسیک.',
		'inline_figure' => array(
			'slug'    => 'fig-compare-armchair-forms',
			'colors'  => array( '#173f35', '#9eab92' ),
			'alt'     => 'مقایسه فرم اسکاندیناوی و کلاسیک تک‌نفره',
			'caption' => 'تفاوت اشغال فضا و عمق نشیمن در دو فرم صندلی تک‌نفره.',
		),
		'age_days'      => 16,
		'body'          => '<p>تک‌نفره راحتی گوشه‌ی نشیمن را شخصیت می‌دهد؛ انتخاب فرم باریک اسکاندیناوی یا کلاسیک دسته‌بلند بر اساس متراژ، سبک مبل اصلی و نوع نشستن شماست.</p><figure class="wp-block-table"><table><thead><tr><th>معیار</th><th>فرم اسکاندیناوی</th><th>فرم کلاسیک دسته‌دار</th></tr></thead><tbody><tr><td>حجم و اشغال فضا</td><td>پایه‌های باریک و نمای شناور</td><td>بدنه‌ی پهن با دسته‌های پر</td></tr><tr><td>هماهنگی سبک</td><td>با مبل خطی و دکور مدرن</td><td>با مبل راحتی سنتی و مخمل</td></tr><tr><td>نشستن طولانی</td><td>راحت برای نشستن فعال و صاف</td><td>عمیق‌تر و مناسب لم دادن</td></tr><tr><td>جابه‌جایی و نظافت</td><td>سبک‌تر؛ گردگیری از زیرش راحت‌تر</td><td>سنگین‌تر اما دسته‌ها تکیه‌گاه اضافه‌اند</td></tr></tbody></table></figure><div class="wp-block-columns"><div class="wp-block-column"><h3>چه زمانی اسکاندیناوی بخریم؟</h3><ul><li>در نشیمن زیر ۲۰ متر و خانه‌های مدرن</li><li>برای گوشه‌ی کنار پنجره با نمای باز</li></ul></div><div class="wp-block-column"><h3>چه زمانی کلاسیک بخریم؟</h3><ul><li>در سالن‌های بزرگ و دکور گرم و پرجزئیات</li><li>برای گوشه‌ی مطالعه‌ی لم‌دادنی و طولانی</li></ul></div></div><blockquote class="wp-block-quote"><p>آزمون چیدمون: تک‌نفره را کنار مبل اصلی بگذارید و یک شب از آن استفاده کنید؛ سازگاری دو فرم فقط در نور و کاربرد واقعی معلوم می‌شود.</p></blockquote>',
	),

	array(
		'slug'          => 'compare-pendant-floor-lamp',
		'title'         => 'لوستر آویز یا آباژور ایستاده؟ نور سقفی یا نور کنار مبل',
		'excerpt'       => 'هر دو لایه‌ی اصلی نشیمن را می‌سازند اما یکی سقف را اشغال می‌کند و دیگری کف را؛ تفاوت در سیم‌کشی، انعطاف و نوع پخش نور است.',
		'category'      => 'comparisons',
		'tags'          => array( 'مقایسه', 'روشنایی', 'نشیمن' ),
		'colors'        => array( '#102d26', '#e2b19d' ),
		'alt'           => 'تصویر انتزاعی مقایسه لوستر آویز و آباژور ایستاده',
		'caption'       => 'نور متقارن آویز در برابر نور جهت‌دار آباژور ایستاده.',
		'inline_figure' => array(
			'slug'    => 'fig-compare-pendant-floor-lighting',
			'colors'  => array( '#102d26', '#e2b19d' ),
			'alt'     => 'مقایسه نور آویز و آباژور ایستاده',
			'caption' => 'پخش متقارن نور آویز در برابر پخش جهت‌دار آباژور ایستاده.',
		),
		'age_days'      => 20,
		'body'          => '<p>هر دو نور گرم و لایه‌ی اصلی نشیمن را می‌سازند اما یکی سقف را اشغال می‌کند و دیگری کف را. تفاوت اصلی در سیم‌کشی، انعطاف چیدمان و نوع پخش نور است.</p><figure class="wp-block-table"><table><thead><tr><th>شاخص</th><th>لوستر آویز</th><th>آباژور ایستاده</th></tr></thead><tbody><tr><td>محل نصب</td><td>جعبه‌ی سقفی و سیم‌کشی ثابت</td><td>فقط یک پریز دیواری</td></tr><tr><td>پخش نور</td><td>عمومی و متقارن روی زیرین</td><td>جهت‌دار روی صندلی و کنار مبل</td></tr><tr><td>جابه‌جایی در چیدمان</td><td>ثابت؛ با تعویض چیدمان می‌ماند</td><td>آزاد؛ با مبل جابه‌جا می‌شود</td></tr><tr><td>مناسب برای</td><td>میز ناهارخوری، جزیره، کانون نشیمن</td><td>گوشه مطالعه، خانه اجاره‌ای، سقف کوتاه</td></tr></tbody></table></figure><div class="wp-block-columns"><div class="wp-block-column"><h3>چه زمانی آویز بخریم؟</h3><ul><li>بالای میز ناهارخوری یا جزیره‌ای که کانون ثابت خانه است</li><li>در سقف‌های بلند که فضا نور عمودی می‌خواهد</li></ul></div><div class="wp-block-column"><h3>چه زمانی ایستاده بخریم؟</h3><ul><li>در خانه‌ی اجاره‌ای بدون دست‌کاری سقف</li><li>برای نور مطالعه‌ی کنار مبل و گوشه‌های خالی</li></ul></div></div><blockquote class="wp-block-quote"><p>نکته چیدمون: آویز را بالای نقطه‌ی کانونی و آباژور ایستاده را کنار شانه‌ی صندلی بگذارید؛ این دو با هم لایه‌بندی طبیعی نشیمن را می‌سازند.</p></blockquote>',
	),

	// --- MORE ROOM IDEAS (shop-the-look seed for grid and pagination) ---
	array(
		'slug'     => 'look-cozy-dining-corner',
		'title'    => 'ناهارخوری دونفره‌ی دنج در کنج آشپزخانه',
		'excerpt'  => 'میز گرد کوچک، دو صندلی و یک آویز خطی؛ ترکیبی که کنج خالی آشپزخانه را به جای صبحانه و چای عصر تبدیل می‌کند.',
		'category' => 'room-ideas',
		'tags'     => array( 'shop-the-look', 'آشپزخانه', 'ناهارخوری' ),
		'colors'   => array( '#9eab92', '#e2b19d' ),
		'alt'      => 'تصویر انتزاعی برای ناهارخوری دونفره در کنج آشپزخانه',
		'caption'  => 'میز گرد کوچک و آویز خطی در کنج آشپزخانه.',
		'age_days' => 13,
		'products' => array( 'coffee-table-round-oak', 'pendant-linear-arc', 'ceramic-vase-tappeh' ),
		'body'     => '<p>کنج آشپزخانه با یک میز گرد هشتاد سانتی‌متری و دو صندلی، بدون برخورد با مثلث کار آشپزخانه، به ناهارخوری دوم خانه تبدیل می‌شود.</p><h2>سه عنصر این کنج</h2><ul><li>میز گرد مات با پایه‌ی باریک که مسیر عبور را باز نگه می‌دارد.</li><li>آویز خطی با نور ۳۰۰۰ کلوین که سطح میز را روشن و کانون فضا را تعریف می‌کند.</li><li>گلدان سرامیکی با شاخه‌های خشک برای نرم کردن خط آشپزخانه.</li></ul><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	array(
		'slug'          => 'look-calm-green-bedroom',
		'title'         => 'اتاق خواب آرام با پالت سبز و کتان',
		'excerpt'       => 'سبز تیره روی دیوار، روتختی کتان خاکی و نور گرم دیوارکوب؛ ترکیبی که سرعت شب‌ها را کم می‌کند.',
		'category'      => 'room-ideas',
		'tags'          => array( 'shop-the-look', 'اتاق خواب', 'رنگ خنثی' ),
		'colors'        => array( '#173f35', '#9eab92' ),
		'alt'           => 'تصویر انتزاعی سبز آرام برای اتاق خواب',
		'caption'       => 'پالت سبز و خاکی با منسوجات کتان در اتاق خواب.',
		'inline_figure' => array(
			'slug'    => 'fig-look-calm-green-bedroom',
			'colors'  => array( '#173f35', '#9eab92' ),
			'alt'     => 'اتاق خواب آرام با پالت سبز',
			'caption' => 'لایه‌بندی منسوجات کتان و نور گرم دیوارکوب در اتاق خواب سبز.',
		),
		'age_days'      => 10,
		'products'      => array( 'linen-duvet-sepidar', 'cushion-wool-geometric', 'wall-sconce-warm-globe' ),
		'body'          => '<p>پالت سبز تیره و خاکی، اتاق خواب را از حالت نمایشگاهی درمی‌آورد؛ به‌جای چند رنگ، سه تُن هم‌خانواده با بافت‌های متفاوت کار می‌کنیم.</p><h2>لایه‌های این اتاق</h2><ol><li>روتختی کتان پیش‌شسته با تُن خاکی به‌عنوان پایه.</li><li>دو کوسن پشمی بافت‌دار برای لایه‌ی بافتی و تکیه‌گاه مطالعه.</li><li>دیوارکوب حباب‌دار با نور ۲۷۰۰ کلوین به‌جای لوستر سقفی.</li></ol><p>پیش از رنگ‌زدن دیوار، پالت را از منسوجات بسازید؛ تعویض فصلی منسوجات ارزان‌تر از تعمیر رنگ است.</p><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	array(
		'slug'     => 'look-work-nook-bookshelf',
		'title'    => 'کنج کار خانگی با کتابخانه‌ی مدولار',
		'excerpt'  => 'میز کنار کتابخانه، آباژور رومیزی و صندلی راک؛ ترکیبی برای کسانی که اتاق کار مستقل ندارند.',
		'category' => 'room-ideas',
		'tags'     => array( 'shop-the-look', 'کار در خانه', 'کتابخانه' ),
		'colors'   => array( '#173f35', '#f6f2e9' ),
		'alt'      => 'تصویر انتزاعی کنج کار خانگی با کتابخانه',
		'caption'  => 'کنج کار با کتابخانه مدولار و نور گرم رومیزی.',
		'age_days' => 8,
		'products' => array( 'bookshelf-modular-teak', 'table-lamp-sunset', 'rocking-chair-classic' ),
		'body'     => '<p>کتابخانه‌ی مدولار هم انبار لوازم کار است و هم جداکننده‌ی بصری کنج کار از بقیه‌ی اتاق؛ درز پشت میز را می‌پوشاند و سیم‌ها را قایم می‌کند.</p><h2>چیدمان این کنج</h2><ul><li>میز باریک چسبیده به کتابخانه با عمق پنجاه سانتی‌متر.</li><li>آباژور سرامیکی با نور ۲۷۰۰ کلوین برای ساعت‌های پایانی روز.</li><li>صندلی راک برای بریک‌های بین کار؛ حرکت آرام آن لنگر ذهنی استراحت است.</li></ul><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	array(
		'slug'     => 'look-media-wall-minimal',
		'title'    => 'دیوار تلویزیون مینیمال بدون شلوغی',
		'excerpt'  => 'بدون دیوارکوب شلوغ؛ سطح باریک پایین، مبل خطی روبه‌رو و آباژور ایستاده برای نور شبانه.',
		'category' => 'room-ideas',
		'tags'     => array( 'shop-the-look', 'نشیمن', 'مینیمال' ),
		'colors'   => array( '#102d26', '#e2b19d' ),
		'alt'      => 'تصویر انتزاعی برای دیوار تلویزیون مینیمال',
		'caption'  => 'چیدمان مینیمال دیوار تلویزیون با کف آزاد و نور لایه‌ای.',
		'age_days' => 5,
		'products' => array( 'sofa-armchair-nordic', 'floor-lamp-minimal-brass', 'rug-kashan-runner' ),
		'body'     => '<p>دیوار تلویزیون وقتی شیک می‌شود که تجهیزات از دید خارج و کف فضا آزاد بماند؛ به‌جای قفسه‌های پر، یک سطح باریک و دو لایه‌ی نور کافی است.</p><h2>قاعده‌های این چیدمان</h2><ol><li>ارتفاع مرکز صفحه ۱۰۵ تا ۱۱۰ سانتی‌متر از کف برای دید نشسته.</li><li>مبل تک‌نفره و پادری گرد، مسیر دید را نرم و فضا را صمیمی می‌کند.</li><li>آباژور ایستاده کنار مبل با نور غیرمستقیم، تماشای شبانه را آرام می‌کند.</li></ul><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	array(
		'slug'     => 'look-guest-room-ready',
		'title'    => 'اتاق مهمان همیشه‌آماده با سه تُن خنثی',
		'excerpt'  => 'روتختی ست‌شده، آینه قدی و نور پاتختی؛ اتاقی که در هر روز هفته آماده‌ی مهمان است.',
		'category' => 'room-ideas',
		'tags'     => array( 'shop-the-look', 'اتاق خواب', 'فضای کوچک' ),
		'colors'   => array( '#e2b19d', '#f6f2e9' ),
		'alt'      => 'تصویر انتزاعی گرم برای اتاق مهمان',
		'caption'  => 'اتاق مهمان با روتختی ست‌شده و آینه قدی قوسی.',
		'age_days' => 3,
		'products' => array( 'linen-duvet-sepidar', 'wall-mirror-arch-bronze', 'table-lamp-sunset' ),
		'body'     => '<p>اتاق مهمان نباید انبار وسایل بی‌خانمان باشد؛ سه عنصر ثابت و پالت خنثی کافی است تا هر مهمانی با یک روتختی تمیز احساس مهم بودن کند.</p><h2>چیدمان پایه</h2><ul><li>ست روتختی کتان که پس از هر مهمان با یک بار شست‌وشو تازه می‌شود.</li><li>آینه قدی قوسی برای اتاقی که اغلب پنجره‌ی بزرگ ندارد؛ نور را دوبرابر می‌کند.</li><li>آباژور پاتختی با کلید سرسیم؛ مهمان بدون جست‌وجوی کلید سقف بخوابد.</li></ul><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),

	array(
		'slug'          => 'look-window-plant-corner',
		'title'         => 'گوشه‌ی سبز کنار پنجره با گلدان‌های هم‌خانواده',
		'excerpt'       => 'سه گلدان با ارتفاع‌های متفاوت، یک سینی چوبی و نور پنجره؛ ساده‌ترین راه ساختن گوشه‌ی سبز خانه.',
		'category'      => 'room-ideas',
		'tags'          => array( 'shop-the-look', 'دکور', 'گیاهان آپارتمانی' ),
		'colors'        => array( '#9eab92', '#102d26' ),
		'alt'           => 'تصویر انتزاعی سبز برای گوشه‌ی گیاهان کنار پنجره',
		'caption'       => 'گلدان‌های سرامیکی هم‌خانواده با ارتفاع پله‌ای کنار پنجره.',
		'inline_figure' => array(
			'slug'    => 'fig-look-window-plant-corner',
			'colors'  => array( '#9eab92', '#102d26' ),
			'alt'     => 'گوشه سبز گیاهان کنار پنجره',
			'caption' => 'ترکیب پله‌ای گلدان‌های سرامیکی و سینی چوبی کنار پنجره.',
		),
		'age_days'      => 2,
		'products'      => array( 'ceramic-planter-sand', 'ceramic-vase-tappeh', 'wooden-tray-walnut' ),
		'body'          => '<p>گوشه‌ی سبز از تکرار جنس و تنوع ارتفاع می‌آید، نه از تعداد گیاه؛ سه گلدان سرامیکی با ارتفاع پله‌ای حتی با دو گیاه هم ترکیب کامل می‌سازند.</p><h2>قاعده‌ی ترکیب</h2><ol><li>بلندترین گلدان عقب، کوتاه‌ترین جلو؛ هر برگ دیده شود.</li><li>زیرگلدانی‌های یکپارچه از آب‌زدگی به کف محافظت می‌کنند.</li><li>سینی چوبی گرد، ابزار آبیاری و هرس را در یک نقطه جمع می‌کند.</li></ol><!-- wp:shortcode -->[chidemoon_affiliate_cta]<!-- /wp:shortcode -->',
	),
);

/**
 * Seeds WooCommerce product categories with generated thumbnails.
 *
 * @return array<string, int> Slug to term ID map.
 */
function cm_seed_product_categories(): array {
$definitions = array(
'living-room' => array( 'مبل و نشیمن', 'مبلمان نشیمن انتخاب‌شده با تمرکز بر اسفنج، پارچه و اندازه‌ی استاندارد.', array( '#173f35', '#9eab92' ) ),
'lighting'    => array( 'روشنایی', 'آباژور و آویزهای با دمای رنگ گرم برای فضاهای خانگی.', array( '#b65d3d', '#f6f2e9' ) ),
'textiles'    => array( 'منسوجات', 'پادری، روتختی و پرده با پارچه‌های باکیفیت و ترکیب‌های رنگی آرام.', array( '#9eab92', '#e2b19d' ) ),
'decor'       => array( 'دکور و تزئین', 'گلدان و اشیای تزئینی سرامیکی برای لایه‌ی آخر چیدمان.', array( '#102d26', '#b65d3d' ) ),
);

$term_ids = array();
foreach ( $definitions as $slug => $definition ) {
$term_id = cm_seed_term( 'product_cat', $slug, $definition[0], $definition[1] );
if ( 0 === $term_id ) {
continue;
}
$term_ids[ $slug ] = $term_id;

$thumbnail_id = cm_seed_image( 'cat-' . $slug, $definition[2][0], $definition[2][1], 'تصویر دسته‌بندی ' . $definition[0] );
if ( $thumbnail_id > 0 && 0 === (int) get_term_meta( $term_id, 'thumbnail_id', true ) ) {
update_term_meta( $term_id, 'thumbnail_id', $thumbnail_id );
}
}

return $term_ids;
}

/**
 * Seeds one external affiliate product with complete Chidemoon Core metadata.
 *
 * @param array<string, mixed> $spec     Product specification.
 * @param array<string, int>   $term_ids Product category map.
 */
function cm_seed_product( array $spec, array $term_ids ): void {
if ( cm_seed_post_exists( 'product', $spec['slug'] ) ) {
WP_CLI::log( 'Product skipped (already seeded): ' . $spec['slug'] );
return;
}

if ( ! class_exists( 'WC_Product_External' ) ) {
WP_CLI::warning( 'WooCommerce is inactive; product skipped: ' . $spec['slug'] );
return;
}

$palette  = cm_seed_product_palette( $spec['category'] );
$image_id = cm_seed_image( 'product-' . $spec['slug'], $palette[0], $palette[1], 'تصویر محصول ' . $spec['name'] );

$product = new WC_Product_External();
$product->set_name( $spec['name'] );
$product->set_slug( $spec['slug'] );
$product->set_status( 'publish' );
$product->set_short_description( $spec['short'] );
$product->set_description( $spec['description'] );
$product->set_regular_price( (string) $spec['price'] );
$product->set_product_url( $spec['url'] );
$product->set_button_text( 'مشاهده در فروشگاه' );
if ( $image_id > 0 ) {
$product->set_image_id( (string) $image_id );
}
if ( isset( $term_ids[ $spec['category'] ] ) ) {
$product->set_category_ids( array( $term_ids[ $spec['category'] ] ) );
}
if ( ! empty( $spec['featured'] ) ) {
$product->set_featured( true );
}

$product->update_meta_data( '_chidemoon_affiliate_url', $spec['url'] );
$product->update_meta_data( '_chidemoon_merchant_name', $spec['merchant'] );
$product->update_meta_data( '_chidemoon_source_url', $spec['source'] );
$product->update_meta_data( '_chidemoon_source_checked_at', gmdate( DATE_ATOM ) );
$product->update_meta_data( '_chidemoon_review_state', 'reviewed' );
$product->delete_meta_data( '_chidemoon_disclosure' );
$product->update_meta_data( '_chidemoon_product_facts', wp_json_encode( $spec['facts'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
$product->update_meta_data( '_product_url', $spec['url'] );
$product->update_meta_data( '_button_text', 'مشاهده در فروشگاه' );

$saved_id = $product->save();
if ( $saved_id > 0 ) {
WP_CLI::log( 'Product seeded: ' . $spec['slug'] );
} else {
WP_CLI::warning( 'Product "' . $spec['slug'] . '" could not be saved.' );
}
}

function cm_seed_search_url( string $query ): string {
return 'https://www.digikala.com/search/?q=' . rawurlencode( $query );
}

/**
 * Replaces the generic article shortcode with local-preview product cards.
 * Product slugs keep these relationships stable across repeated seed runs.
 *
 * @param string[] $product_slugs Related product slugs.
 */
function cm_seed_related_product_markup( array $product_slugs ): string {
	$cards = array();

	foreach ( $product_slugs as $product_slug ) {
		$product_id = cm_seed_post_exists( 'product', $product_slug );
		if ( 0 === $product_id ) {
			continue;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			continue;
		}

		$image = $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) );
		$cards[] = sprintf(
			'<article class="chidemoon-article-product">' .
				'<a class="chidemoon-article-product__image" href="%1$s" tabindex="-1" aria-hidden="true">%2$s</a>' .
				'<div class="chidemoon-article-product__body">' .
					'<h3 class="chidemoon-article-product__title"><a href="%1$s">%3$s</a></h3>' .
					'<p class="chidemoon-article-product__excerpt">%4$s</p>' .
					'<footer class="chidemoon-article-product__footer">' .
						'<strong class="chidemoon-article-product__price">%5$s</strong>' .
						'<a class="chidemoon-article-product__cta" href="%1$s">%6$s</a>' .
					'</footer>' .
				'</div>' .
			'</article>',
			esc_url( get_permalink( $product_id ) ),
			$image,
			esc_html( $product->get_name() ),
			esc_html( wp_trim_words( $product->get_short_description(), 18 ) ),
			wp_kses_post( $product->get_price_html() ),
			esc_html__( 'مشاهده کالا', 'chidemoon-blocksy-child' )
		);
	}

	if ( empty( $cards ) ) {
		return '';
	}

	return '<section class="chidemoon-article-products"><h2>کالاهای این ترکیب</h2><p>این پیشنهادها فقط برای ارزیابی محلی‌اند؛ قیمت و مقصد فروشنده پیش از انتشار عمومی باید دوباره بررسی شوند.</p><div class="chidemoon-article-products__grid">' . implode( '', $cards ) . '</div></section>';
}

/**
 * Resolves related product cards after catalogue products have been seeded.
 */
function cm_seed_attach_related_products(): void {
	foreach ( $GLOBALS['cm_seed_posts'] as $post_spec ) {
		if ( empty( $post_spec['products'] ) ) {
			continue;
		}

		$post_id = cm_seed_post_exists( 'post', $post_spec['slug'] );
		if ( 0 === $post_id ) {
			continue;
		}

		$content     = (string) get_post_field( 'post_content', $post_id );
		$product_html = cm_seed_related_product_markup( $post_spec['products'] );
		$content      = preg_replace(
			'/<!-- wp:shortcode -->\s*\[chidemoon_affiliate_cta\]\s*<!-- \/wp:shortcode -->/',
			$product_html,
			$content
		);
		wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
	}
}

/**
 * Product imagery is derived from the category palette so every product gets
 * an image even though the specification only carries editorial data.
 *
 * @param string $category_slug Product category slug.
 * @return string[] From and to gradient colours.
 */
function cm_seed_product_palette( string $category_slug ): array {
$palettes = array(
'living-room' => array( '#173f35', '#9eab92' ),
'lighting'    => array( '#b65d3d', '#f6f2e9' ),
'textiles'    => array( '#9eab92', '#e2b19d' ),
'decor'       => array( '#102d26', '#b65d3d' ),
);

return $palettes[ $category_slug ] ?? array( '#173f35', '#b65d3d' );
}
$cm_seed_products = array(
	// --- LIVING ROOM ---
	array(
		'slug'        => 'sofa-armchair-nordic',
		'name'        => 'مبل تک‌نفره پارچه‌ای «نوردیک»',
		'price'       => 18400000,
		'category'    => 'living-room',
		'featured'    => true,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'تک‌نفره‌ای با اسفنج سرد ۳۵ کیلوگرم و روکش بوکل قابل‌شست‌وشو؛ مناسب گوشه‌ی مطالعه و نشیمن‌های کوچک.',
		'description' => 'این تک‌نفره با فریم چوب راش گرجستان و اسفنج سرد با چگالی ۳۵ کیلوگرم بر مترمکعب ساخته شده است. روکش بوکل با زیپ مخفی قابل جدا شدن است و پارچه‌ی آن درجه‌ی سایش مارتیندیل ۴۵ هزار سیکل دارد. ابعاد نشیمن ۶۰ در ۷۰ سانتی‌متر است.',
		'facts'       => array(
			array( 'label' => 'جنس بدنه', 'value' => 'چوب راش گرجستان' ),
			array( 'label' => 'چگالی اسفنج', 'value' => '۳۵ کیلوگرم بر مترمکعب (HR)' ),
			array( 'label' => 'ابعاد', 'value' => '۸۵×۸۵×۸۰ سانتی‌متر' ),
		),
	),

	array(
		'slug'        => 'rocking-chair-classic',
		'name'        => 'صندلی راک چوبی «کلاسیک»',
		'price'       => 9750000,
		'category'    => 'living-room',
		'featured'    => false,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'صندلی راک با قوس ملایم و تکیه‌گاه ارگونومیک؛ برای گوشه‌ی مطالعه و اتاق استراحت.',
		'description' => 'صندلی راک کلاسیک با چوب راش پرداخت‌شده و روکش پارچه‌ی مخمل شنی ساخته می‌شود. قوس پایه‌ها حدود ۳۰ سانتی‌متر است و حرکت آرام و بدون صدا دارد. وزن صندلی ۹ کیلوگرم است و جابه‌جایی آن آسان است.',
		'facts'       => array(
			array( 'label' => 'جنس بدنه', 'value' => 'چوب راش طبیعی' ),
			array( 'label' => 'قوس پایه', 'value' => '۳۰ سانتی‌متر' ),
			array( 'label' => 'تحمل وزن', 'value' => 'تا ۱۲۰ کیلوگرم' ),
		),
	),

	array(
		'slug'        => 'coffee-table-round-oak',
		'name'        => 'میز جلومدی گرد بلوط «هیوا»',
		'price'       => 6200000,
		'category'    => 'living-room',
		'featured'    => true,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'میز گرد با صفحه‌ی بلوط روکش‌شده مات و پایه‌ی فلزی مشکی؛ قطر ۸۰ سانتی‌متر برای نشیمن‌های جمع‌وجور.',
		'description' => 'صفحه‌ی این میز از MDF با روکش چوب بلوط طبیعی و پوشش پلی‌اورتان مات ضدآب است و پایه‌ی سه‌پایه‌ی فلزی آن با رنگ الکترواستاتیک مشکی پوشیده شده. قطر صفحه ۸۰ و ارتفاع ۴۵ سانتی‌متر است.',
		'facts'       => array(
			array( 'label' => 'قطر صفحه', 'value' => '۸۰ سانتی‌متر' ),
			array( 'label' => 'ارتفاع', 'value' => '۴۵ سانتی‌متر' ),
			array( 'label' => 'نوع پایه', 'value' => 'فلز با رنگ کوره‌ای مشکی مات' ),
		),
	),

	array(
		'slug'        => 'bookshelf-modular-teak',
		'name'        => 'کتابخانه مدولار چوب راش «آراد»',
		'price'       => 12800000,
		'category'    => 'living-room',
		'featured'    => false,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'کتابخانه مدولار ۵ طبقه با ترکیب بخش دردار پایینی و شلف‌های باز بالایی.',
		'description' => 'کتابخانه آراد با ساختار مدولار و فریم چوب راش طراحی شده است. دو طبقه پایینی مجهز به درب مگنتی بدون دستگیره برای پنهان‌سازی وسایل شخصی و سه طبقه بالایی برای نمایش کتاب و اشیای دکوراتیو هستند.',
		'facts'       => array(
			array( 'label' => 'ارتفاع کلی', 'value' => '۱۸۵ سانتی‌متر' ),
			array( 'label' => 'عرض', 'value' => '۸۰ سانتی‌متر' ),
			array( 'label' => 'تعداد طبقات', 'value' => '۵ طبقه (۲ طبقه دردار)' ),
		),
	),

	// --- LIGHTING ---
	array(
		'slug'        => 'table-lamp-sunset',
		'name'        => 'آباژور سرامیکی «غروب»',
		'price'       => 3450000,
		'category'    => 'lighting',
		'featured'    => true,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'آباژور میزی با پایه‌ی سرامیکی دست‌ساز لعاب‌دار و کلاهک کتان؛ نور گرم مناسب پاتختی و گوشه‌ی مطالعه.',
		'description' => 'پایه‌ی سرامیکی این آباژور با لعاب مات به رنگ خاکی پخته شده و کلاهک پارچه‌ای آن نور را به‌طور یکنواخت و ملایم پخش می‌کند. سوکت E27 با انواع لامپ‌های دیمردار سازگار است. ارتفاع کلی ۴۵ سانتی‌متر است.',
		'facts'       => array(
			array( 'label' => 'ارتفاع', 'value' => '۴۵ سانتی‌متر' ),
			array( 'label' => 'نوع سوکت', 'value' => 'E27 استاندارد' ),
			array( 'label' => 'جنس پایه', 'value' => 'سرامیک دست‌ساز لعاب مات' ),
		),
	),

	array(
		'slug'        => 'pendant-linear-arc',
		'name'        => 'لوستر آویز خطی «آرک»',
		'price'       => 7900000,
		'category'    => 'lighting',
		'featured'    => false,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'آویز خطی ۹۰ سانتی‌متری با بدنه‌ی آلومینیومی و نور ۳۰۰۰ کلوین؛ مناسب میز ناهارخوری و جزیره‌ی آشپزخانه.',
		'description' => 'لوستر آرک با بدنه آلومینیوم آنودایزشده به رنگ مشکی مات و دیفیوزر اپال برای نور بدون خیرگی طراحی شده است. طول بدنه ۹۰ سانتی‌متر و کابل آویز تا ۱۲۰ سانتی‌متر قابل تنظیم است.',
		'facts'       => array(
			array( 'label' => 'طول بدنه', 'value' => '۹۰ سانتی‌متر' ),
			array( 'label' => 'دمای رنگ', 'value' => '۳۰۰۰ کلوین (آفتابی گرم)' ),
			array( 'label' => 'مصرف برق', 'value' => '۲۸ وات ال‌ای‌دی' ),
		),
	),

	array(
		'slug'        => 'floor-lamp-minimal-brass',
		'name'        => 'آباژور ایستاده برنجی «سایه»',
		'price'       => 5600000,
		'category'    => 'lighting',
		'featured'    => true,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'آباژور پایه بلند با بدنه آبکاری برنج مات و هد قابل چرخش؛ مناسب کنار مبل تک‌نفره.',
		'description' => 'آباژور ایستاده سایه با بدنه فولادی و روکش برنج مات تولید شده است. زاویه سری چراغ تا ۱۸۰ درجه برای هدایت پرتو نور به سمت کتاب یا سقف قابل تنظیم است.',
		'facts'       => array(
			array( 'label' => 'ارتفاع', 'value' => '۱۴۵ سانتی‌متر' ),
			array( 'label' => 'پوشش بدنه', 'value' => 'آبکاری برنج مات ضدلک' ),
			array( 'label' => 'کلید', 'value' => 'پدالی پایی روی سیم' ),
		),
	),

	array(
		'slug'        => 'wall-sconce-warm-globe',
		'name'        => 'چراغ دیواری حباب‌دار «مدار»',
		'price'       => 2150000,
		'category'    => 'lighting',
		'featured'    => false,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'دیوارکوب با حباب شیشه‌ای شیری مات و پایه دایره‌ای مشکی؛ مناسب ورودی و راهرو.',
		'description' => 'چراغ دیواری مدار با حباب شیشه دوپوست اوپالین و پایه فلزی مات، نوری همگن و ۳۶۰ درجه در راهروها یا کنار آینه ورودی ایجاد می‌کند.',
		'facts'       => array(
			array( 'label' => 'قطر حباب', 'value' => '۱۵ سانتی‌متر' ),
			array( 'label' => 'سرپیچ', 'value' => 'G9 کم‌مصرف' ),
			array( 'label' => 'درجه حفاظت', 'value' => 'IP20 مناسب فضاهای داخلی' ),
		),
	),

	// --- TEXTILES ---
	array(
		'slug'        => 'rug-kashan-runner',
		'name'        => 'پادری دستباف «کاشان»',
		'price'       => 4100000,
		'category'    => 'textiles',
		'featured'    => true,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'پادری گرد دستباف با پشم دستریس مرغوب و رنگرزی گیاهی؛ مناسب زیر صندلی مطالعه و ورودی خانه.',
		'description' => 'این پادری گرد با پشم دستریس بهاره و ریشه‌های محکم بافته شده است. نقشه ساده هندسی با پالت خاکی و کرم برای هماهنگی با دکوراسیون مدرن و سنتی طراحی شده است. قطر ۱۲۰ سانتی‌متر است.',
		'facts'       => array(
			array( 'label' => 'قطر', 'value' => '۱۲۰ سانتی‌متر' ),
			array( 'label' => 'جنس تار و پود', 'value' => 'پشم دستریس طبیعی و پنبه' ),
			array( 'label' => 'نوع رنگرزی', 'value' => 'گیاهی سنتی' ),
		),
	),

	array(
		'slug'        => 'linen-duvet-sepidar',
		'name'        => 'روتختی کتان «سپیدار»',
		'price'       => 2850000,
		'category'    => 'textiles',
		'featured'    => false,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'ست روتختی کتان دونفره ۴ تکه با رنگ‌های طبیعی خاکی؛ نفس‌کش و مناسب چهارفصل.',
		'description' => 'روتختی سپیدار از کتان خالص ۱۰۰٪ با گراماژ ۱۶۵ تهیه شده است. پارچه از پیش شسته شده (Pre-washed) تا پس از شست‌وشوی خانگی آب‌رفت نداشته باشد. ست شامل کاور لحاف، ملحفه تشک کش‌دار و دو روبالشی است.',
		'facts'       => array(
			array( 'label' => 'جنس پارچه', 'value' => 'کتان ۱۰۰٪ شسته شده' ),
			array( 'label' => 'تعداد تکه', 'value' => '۴ تکه دونفره' ),
			array( 'label' => 'ابعاد کاور لحاف', 'value' => '۲۲۰ × ۲۴۰ سانتی‌متر' ),
		),
	),

	array(
		'slug'        => 'cushion-wool-geometric',
		'name'        => 'کوسن پشمی دست‌دوز «هور»',
		'price'       => 850000,
		'category'    => 'textiles',
		'featured'    => false,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'کوسن مربعی ۴۵×۴۵ با رویه پشمی بافت‌دار و بالشتک داخلی الیاف ضدحساسیت.',
		'description' => 'کوسن هور با الهام از نقوش گلیم‌های قشقایی به صورت دست‌دوز تهیه شده است. رویه پشتی از پارچه کتان ساده با زیپ مخفی برای شست‌وشوی آسان رویه است.',
		'facts'       => array(
			array( 'label' => 'ابعاد', 'value' => '۴۵ × ۴۵ سانتی‌متر' ),
			array( 'label' => 'جنس رویه', 'value' => 'پشم طبیعی و کتان' ),
			array( 'label' => 'پرکننده', 'value' => 'الیاف توپی میکروفایبر ضدحساسیت' ),
		),
	),

	array(
		'slug'        => 'curtain-linen-sheer',
		'name'        => 'پرده کتان بافت‌دار «نسیم»',
		'price'       => 1950000,
		'category'    => 'textiles',
		'featured'    => true,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'پرده حریر کتان نیمه‌مات با عرض ۲۸۰ سانتی‌متر؛ فیلتر ملایم نور خورشید بدون تاریک کردن اتاق.',
		'description' => 'پرده نسیم با تار و پود کتان و پلی‌استر تولید شده تا علاوه بر حس طبیعی، چروک‌پذیری کمی داشته باشد و در برابر نور خورشید تغییر رنگ ندهد.',
		'facts'       => array(
			array( 'label' => 'عرض پارچه', 'value' => '۲۸۰ سانتی‌متر' ),
			array( 'label' => 'درصد عبور نور', 'value' => 'حدود ۵۰٪ (نیمه‌مات)' ),
			array( 'label' => 'شست‌وشو', 'value' => 'ماشین لباسشویی در دمای ۳۰ درجه' ),
		),
	),

	// --- DECOR ---
	array(
		'slug'        => 'ceramic-vase-tappeh',
		'name'        => 'گلدان سرامیکی «تپه»',
		'price'       => 1290000,
		'category'    => 'decor',
		'featured'    => false,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'گلدان سرامیکی با فرم منحنی ارگانیک و لعاب مات خاکی؛ مناسب شلف، میز ناهارخوری و ورودی.',
		'description' => 'گلدان تپه با چرخ سفالگری دست‌ساز شکل گرفته و با لعاب مات خاکی پخته شده است. دهانه مناسب آن قرارگیری شاخه‌های بلند پامپاس یا برگ اکالیپتوس را به زیبایی نگه می‌دارد.',
		'facts'       => array(
			array( 'label' => 'ارتفاع', 'value' => '۲۸ سانتی‌متر' ),
			array( 'label' => 'جنس بدنه', 'value' => 'سرامیک لعاب مات پخته‌شده' ),
			array( 'label' => 'فرایند ساخت', 'value' => 'دست‌ساز با چرخ سفال' ),
		),
	),

	array(
		'slug'        => 'wall-mirror-arch-bronze',
		'name'        => 'آینه قدی قوسی با فریم فلزی «آوا»',
		'price'       => 4600000,
		'category'    => 'decor',
		'featured'    => true,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'آینه قدی قوسی با ابعاد ۱۷۰×۶۰ و فریم فلزی برنز مات؛ افزایش دوبرابری عمق نور در ورودی و نشیمن.',
		'description' => 'آینه قدی آوا از شیشه شفاف با جیوه نقره درجه‌یک بدون موج و فریم فلزی ظریف با رنگ کوره‌ای برنز ساخته شده است. امکان تکیه دادن ایمن به دیوار یا نصب با بست مخفی را دارد.',
		'facts'       => array(
			array( 'label' => 'ابعاد', 'value' => '۱۷۰ × ۶۰ سانتی‌متر' ),
			array( 'label' => 'ضخامت شیشه', 'value' => '۴ میلی‌متر بدون اعوجاج' ),
			array( 'label' => 'جنس فریم', 'value' => 'آهن با رنگ الکترواستاتیک برنز' ),
		),
	),

	array(
		'slug'        => 'wooden-tray-walnut',
		'name'        => 'سینی چوبی گرد گردو «روشن»',
		'price'       => 920000,
		'category'    => 'decor',
		'featured'    => false,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'سینی دکوراتیو تمام چوب گردوی طبیعی با پوشش روغن گیاهی آب‌گریز؛ قطر ۳۰ سانتی‌متر.',
		'description' => 'این سینی گرد از یک تکه چوب گردوی اعلای ایرانی خراطی شده و با روغن طبیعی هاردواکس پوشش داده شده است؛ مناسب نظم‌دهی عطر، شمع و ماگ روی میز جلومدی و کنسول.',
		'facts'       => array(
			array( 'label' => 'قطر', 'value' => '۳۰ سانتی‌متر' ),
			array( 'label' => 'جنس', 'value' => 'چوب گردو یکپارچه' ),
			array( 'label' => 'پوشش نهایی', 'value' => 'روغن گیاهی مونوکوت خوراکی' ),
		),
	),

	array(
		'slug'        => 'ceramic-planter-sand',
		'name'        => 'گلدان پایه‌دار خاکی «ارغوان»',
		'price'       => 780000,
		'category'    => 'decor',
		'featured'    => false,
		'merchant'    => 'دیجی‌کالا',
		'short'       => 'گلدان گیاه سرامیکی با زیرگلدانی متصل و سوراخ زهکش؛ مناسب گیاهان آپارتمانی.',
		'description' => 'گلدان ارغوان با بافت شنی و خاکی طبیعی ساخته شده و به لطف زیرگلدانی سرامیکی همرنگ، آب اضافی آبیاری را بدون کثیف کردن میز در خود نگه می‌دارد.',
		'facts'       => array(
			array( 'label' => 'قطر دهانه', 'value' => '۱۸ سانتی‌متر' ),
			array( 'label' => 'ارتفاع', 'value' => '۲۰ سانتی‌متر' ),
			array( 'label' => 'ویژگی زهکش', 'value' => 'دارای سوراخ خروجی و زیرگلدانی' ),
		),
	),
);
/**
 * Seeds or refreshes a curated page and keeps its editorial hero imagery.
 *
 * @param array<string, mixed> $spec Page specification.
 */
function cm_seed_page( array $spec ): void {
$page_id = cm_seed_post_exists( 'page', $spec['slug'] );
if ( 0 === $page_id ) {
WP_CLI::log( 'Page not found (run standalone-init first): ' . $spec['slug'] );
return;
}

$update = array( 'ID' => $page_id );
if ( isset( $spec['title'] ) ) {
$update['post_title'] = $spec['title'];
}
if ( isset( $spec['excerpt'] ) ) {
$update['post_excerpt'] = $spec['excerpt'];
}
if ( isset( $spec['content'] ) && '' === trim( (string) get_post_field( 'post_content', $page_id ) ) ) {
$update['post_content'] = $spec['content'];
}
wp_update_post( $update );

if ( ! empty( $spec['image'] ) && ! has_post_thumbnail( $page_id ) ) {
$image_id = cm_seed_image( 'page-' . $spec['slug'], $spec['image'][0], $spec['image'][1], 'تصویر صفحه‌ی ' . $spec['title'] );
if ( $image_id > 0 ) {
set_post_thumbnail( $page_id, $image_id );
}
}

WP_CLI::log( 'Page seeded: ' . $spec['slug'] );
}

function cm_seed_run(): void {
WP_CLI::log( 'Seeding Chidemoon editorial taxonomy…' );
cm_seed_term( 'category', 'guides', 'راهنمای خرید', 'راهنماهای قدم‌به‌قدم برای انتخاب درست مبلمان، نور و منسوجات خانه.' );
cm_seed_term( 'category', 'comparisons', 'مقایسه', 'مقایسه‌های معیار‌به‌معیار با شواهد روشن و بدون توصیه‌ی خودکار.' );
cm_seed_term( 'category', 'room-ideas', 'ایده‌های اتاق', 'ترکیب‌های چیدمان برای گوشه‌های واقعی خانه، همراه با کالاهای مرتبط.' );
cm_seed_term( 'post_tag', 'shop-the-look', 'از تصویر بخر', 'ترکیب‌های ادیتوریال که کالاهایشان مستقیم به فروشنده وصل می‌شود.' );

WP_CLI::log( 'Seeding journal posts…' );
foreach ( $GLOBALS['cm_seed_posts'] as $post_spec ) {
cm_seed_post( $post_spec );
}

WP_CLI::log( 'Seeding catalogue categories and products…' );
$term_ids = cm_seed_product_categories();
foreach ( $GLOBALS['cm_seed_products'] as $product_spec ) {
$product_spec['url']    = cm_seed_search_url( $product_spec['name'] );
$product_spec['source'] = $product_spec['url'];
cm_seed_product( $product_spec, $term_ids );
}

WP_CLI::log( 'Connecting editorial stories to catalogue products…' );
cm_seed_attach_related_products();

WP_CLI::log( 'Seeding curated pages…' );
cm_seed_page(
	array(
		'slug'     => 'home',
		'title'    => 'چیدمون',
		'excerpt'  => 'راهنمای خرید، مقایسه‌ی کالاها و پیشنهادهای کاربردی برای انتخاب و چیدمان وسایل خانه.',
		'content'  => '<p>چیدمون یک مجله‌ی خرید برای خانه است، نه یک فروشگاه. ما کالا را نمی‌فروشیم؛ بررسی می‌کنیم، مقایسه می‌کنیم و شما را مستقیم به فروشنده می‌رسانیم. هر راهنما با معیارهای روشن نوشته می‌شود و هر مقایسه، مبادلات نامعلوم را پنهان نمی‌کند.</p><ul><li><strong>راهنمای خرید:</strong> معیارهای واقعی برای انتخاب مبل، نور و منسوجات</li><li><strong>مقایسه:</strong> روبه‌رو کردن گزینه‌ها با شواهد و بدون توصیه‌ی خودکار</li><li><strong>از تصویر بخر:</strong> ترکیب‌های ادیتوریال که هر کالایش مقصد مستقل دارد</li></ul>',
		'image'    => array( '#173f35', '#b65d3d' ),
	)
);
cm_seed_page( array( 'slug' => 'guides', 'title' => 'راهنمای خرید', 'excerpt' => 'نکته‌های کاربردی برای انتخاب مبل، روشنایی، منسوجات و وسایل خانه.', 'image' => array( '#173f35', '#9eab92' ) ) );
cm_seed_page( array( 'slug' => 'comparisons', 'title' => 'مقایسه‌ها', 'excerpt' => 'مقایسه‌هایی که شواهدشان را نشان می‌دهند و مبادلات را پنهان نمی‌کنند.', 'image' => array( '#102d26', '#b65d3d' ) ) );
cm_seed_page( array( 'slug' => 'shop-the-look', 'title' => 'از تصویر بخر', 'excerpt' => 'اتاق‌ها را ببینید و هر کالا را مستقیم از فروشنده‌ی آن بخرید.', 'image' => array( '#b65d3d', '#e2b19d' ) ) );

WP_CLI::log( 'Seeding primary navigation…' );
cm_seed_nav_menu();

WP_CLI::success( 'Chidemoon seed finished. Journal, catalogue, and curated pages are ready.' );
}

/**
 * Create the primary nav menu from the public editorial pages and assign it
 * to the theme locations, so the header never falls back to a raw page list
 * with cart, checkout, account, and showcase links.
 */
function cm_seed_nav_menu(): void {
$menu_name = 'اصلی';
$existing  = wp_get_nav_menu_object( $menu_name );
$menu_id   = $existing ? (int) $existing->term_id : (int) wp_create_nav_menu( $menu_name );

if ( 0 === $menu_id ) {
WP_CLI::warning( 'Could not create the primary nav menu.' );
return;
}

$existing_items = wp_get_nav_menu_items( $menu_id ) ?: array();
$linked_ids     = array();
foreach ( $existing_items as $item ) {
$linked_ids[] = (int) $item->object_id;
}

foreach ( array( 'shop', 'magazine', 'guides', 'comparisons', 'shop-the-look' ) as $slug ) {
$page = get_page_by_path( $slug );
if ( ! $page instanceof WP_Post || 'publish' !== $page->post_status ) {
continue;
}
if ( in_array( (int) $page->ID, $linked_ids, true ) ) {
continue;
}
wp_update_nav_menu_item(
$menu_id,
0,
array(
'menu-item-title'     => $page->post_title,
'menu-item-object'    => 'page',
'menu-item-object-id' => (int) $page->ID,
'menu-item-type'      => 'post_type',
'menu-item-status'    => 'publish',
)
);
}

$locations          = (array) get_theme_mod( 'nav_menu_locations', array() );
$locations_modified = false;
foreach ( array( 'menu_1', 'menu_mobile' ) as $location ) {
if ( ( $locations[ $location ] ?? 0 ) !== $menu_id ) {
$locations[ $location ] = $menu_id;
$locations_modified     = true;
}
}
if ( $locations_modified ) {
set_theme_mod( 'nav_menu_locations', $locations );
WP_CLI::log( 'Nav menu assigned to header and mobile locations.' );
}
}

cm_seed_run();