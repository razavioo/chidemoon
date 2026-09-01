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
			'<article class="chidemoon-article-product"><a class="chidemoon-article-product__image" href="%1$s" aria-label="%7$s">%2$s</a><div class="chidemoon-article-product__body"><h3><a href="%1$s">%3$s</a></h3><p>%4$s</p><strong>%5$s</strong><a class="chidemoon-text-link" href="%1$s">%6$s<span aria-hidden="true">←</span></a></div></article>',
			esc_url( get_permalink( $product_id ) ),
			$image,
			esc_html( $product->get_name() ),
			esc_html( wp_trim_words( $product->get_short_description(), 18 ) ),
			wp_kses_post( $product->get_price_html() ),
			esc_html__( 'دیدن جزئیات کالا', 'chidemoon-blocksy-child' ),
			esc_attr( sprintf( 'مشاهده %s', $product->get_name() ) )
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

WP_CLI::success( 'Chidemoon seed finished. Journal, catalogue, and curated pages are ready.' );
}

cm_seed_run();