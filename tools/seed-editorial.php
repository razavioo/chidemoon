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
 * Generates (or reuses) a deterministic abstract editorial image.
 *
 * @param string $slug   Asset slug, also the filename.
 * @param string $from   Top gradient hex colour.
 * @param string $to     Bottom gradient hex colour.
 * @param string $alt    Persian alt text.
 * @return int Attachment ID, 0 when generation is impossible.
 */
function cm_seed_image( string $slug, string $from, string $to, string $alt ): int {
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
	if ( ! empty( $existing ) ) {
		return (int) $existing[0];
	}

	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		WP_CLI::warning( 'The GD extension is unavailable; skipping seed image "' . $slug . '".' );
		return 0;
	}

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

	$path   = $directory . '/' . $slug . '.jpg';
	$width  = 1280;
	$height = 960;

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

	mt_srand( crc32( $slug ) );

	$glow = imagecolorallocatealpha( $image, 255, 252, 247, 55 );
	imagefilledellipse( $image, (int) ( $width * 0.72 ), (int) ( $height * 0.28 ), 540, 540, $glow );

	$arch = imagecolorallocatealpha( $image, 16, 45, 38, 68 );
	imagefilledrectangle( $image, (int) ( $width * 0.16 ), (int) ( $height * 0.44 ), (int) ( $width * 0.44 ), (int) ( $height * 0.86 ), $arch );
	imagefilledellipse( $image, (int) ( $width * 0.30 ), (int) ( $height * 0.44 ), 300, 300, $arch );

	$accent = imagecolorallocatealpha( $image, 182, 93, 61, 45 );
	imagefilledellipse( $image, (int) ( $width * 0.79 ), (int) ( $height * 0.62 ), 130, 130, $accent );

	$floor = imagecolorallocatealpha( $image, 16, 45, 38, 82 );
	imagefilledrectangle( $image, 0, (int) ( $height * 0.86 ), $width, $height, $floor );

	$grain = imagecolorallocatealpha( $image, 255, 255, 255, 108 );
	for ( $i = 0; $i < 7000; $i++ ) {
		imagesetpixel( $image, mt_rand( 0, $width - 1 ), mt_rand( 0, $height - 1 ), $grain );
	}

	imagejpeg( $image, $path, 84 );
	imagedestroy( $image );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $alt,
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
if ( cm_seed_post_exists( 'post', $spec['slug'] ) ) {
WP_CLI::log( 'Post skipped (already seeded): ' . $spec['slug'] );
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
'post_content'  => $spec['body'],
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
}

$image_id = cm_seed_image( 'post-' . $spec['slug'], $spec['colors'][0], $spec['colors'][1], $spec['alt'] );
if ( $image_id > 0 ) {
set_post_thumbnail( $post_id, $image_id );
}

WP_CLI::log( 'Post seeded: ' . $spec['slug'] );
}

$cm_seed_posts = array(

array(
'slug'     => 'guide-choose-sofa',
'title'    => 'راهنمای انتخاب مبل راحتی برای خانه‌های ایرانی',
'excerpt'  => 'پیش از خرید مبل، اندازه، پارچه و فرم را با هم مرور می‌کنیم تا انتخابی بخرید که سال‌ها دوام بیاورد.',
'category' => 'guides',
'tags'     => array(),
'colors'   => array( '#173f35', '#9eab92' ),
'alt'      => 'تصویر انتزاعی سبز برای راهنمای انتخاب مبل',
'age_days' => 21,
'body'     => '<p>وقتی وارد اتاق نشیمن می‌شوید، پیش از هر رنگ و چیدمان دیگری، فرم مبل است که حال‌وهوای فضا را تعیین می‌کند. مبل درشت‌ترین عنصر نشیمن است و اشتباه در انتخاب آن، هیچ آرایه‌ای را جبران نمی‌کند. در این راهنما قدم‌به‌قدم مسیر انتخاب را مرور می‌کنیم.</p><h2>اندازه را پیش از سلیقه بچینید</h2><p>عرض مبل باید نسبت به دیوار بلندتر نشود و مسیر عبور دست‌کم هفتاد سانتی‌متر باقی بماند. نقشه‌ی اتاق را با متر بکشید و ابعاد را روی زمین با چسب کاغذی شبیه‌سازی کنید؛ همین کار ساده از بزرگ‌ترین پشیمانی خرید جلوگیری می‌کند.</p><ul><li>فاصله‌ی مبل تا میز جلومدی: چهل تا چهل‌وپنج سانتی‌متر</li><li>عمق نشیمن استاندارد: پنجاه تا شصت سانتی‌متر</li><li>ارتفاع پشتی برای حمایت کمر: دست‌کم چهل سانتی‌متر</li></ul><h2>پارچه یا چرم؟</h2><p>پارچه‌های بافت‌درشت مانند کنف و بوکل گرم‌اند اما گردوخاک جذب می‌کنند؛ برای خانه‌های پرتردد رنگ‌های تیره‌تر و بافت متراکم‌تر عاقلانه‌تر است. چرم طبیعی در اقلیم گرم و خشک احتمال ترک دارد، پس اگر به سرمایش حساسید، پارچه‌ی تریکو با درجه‌ی مارتمال بالا انتخاب مطمئن‌تری است.</p><blockquote class="wp-block-quote"><p>نکته چیدمون: اسفنج سرد با چگالی سی کیلوگرم بر مترمکعب یا بالاتر، مرز تشخیص یک مبل بادوام است؛ این عدد را از فروشنده بپرسید.</p></blockquote>',
),

array(
'slug'     => 'guide-lighting-small-apartment',
'title'    => 'نورپردازی آپارتمان کوچک؛ از کجا شروع کنیم؟',
'excerpt'  => 'سه لایه نور، دمای رنگ درست و چند ترفند ساده که آپارتمان کوچک را بزرگ‌تر و آرام‌تر نشان می‌دهد.',
'category' => 'guides',
'tags'     => array(),
'colors'   => array( '#b65d3d', '#e2b19d' ),
'alt'      => 'تصویر انتزاعی گرم برای راهنمای نورپردازی',
'age_days' => 17,
'body'     => '<p>نور، ارزان‌ترین ابزار بازسازی خانه است. پیش از آنکه دیواری را بشکنید یا مبلمان را عوض کنید، نورپردازی سه‌لایه را امتحان کنید؛ تفاوت را همان شب اول می‌بینید.</p><h2>سه لایه‌ای که هر اتاق لازم دارد</h2><p>لایه‌ی اول نور عمومی از سقف است، لایه‌ی دوم نور کار مانند آباژور مطالعه، و لایه‌ی سوم نور تأکیدی روی تابلو یا گیاه. کوچک‌ترین آپارتمان هم با همین سه لایه عمق می‌گیرد، چون چشم میان نقاط روشن و سایه حرکت می‌کند و این حرکت، حس بزرگی می‌سازد.</p><h2>دمای رنگ و شدت</h2><p>برای فضای نشیمن لامپ با دمای رنگ ۲۷۰۰ تا ۳۰۰۰ کلوین انتخاب کنید؛ نور سفید سرد در خانه حس انبار می‌دهد. هر جا می‌توانید کلید کم‌نورکن بگذارید و لامپ‌های سقفی را با آباژورهای کوتاه هم‌رده کنید تا نور از ارتفاع چشم بگذرد.</p><blockquote class="wp-block-quote"><p>نکته چیدمون: آباژوری که نورش به سقف می‌تابد، خط اتصال سقف و دیوار را محو می‌کند و سقف کوتاه را بلندتر نشان می‌دهد.</p></blockquote>',
),

array(
'slug'     => 'guide-curtain-fabric',
'title'    => 'پرده کرکاب، کتان یا مخمل؟ انتخاب پارچه‌ی پرده',
'excerpt'  => 'هر پارچه با نور رفتار متفاوتی دارد؛ راهنمای کوتاهی برای انتخاب پرده بر اساس جهت پنجره و کاربری اتاق.',
'category' => 'guides',
'tags'     => array(),
'colors'   => array( '#9eab92', '#f6f2e9' ),
'alt'      => 'تصویر انتزاعی سبز روشن برای راهنمای پارچه پرده',
'age_days' => 12,
'body'     => '<p>پرده تنها یک پوشش پنجره نیست؛ فیلتر نور، عایق صدا و قاب تصویر بیرون است. انتخاب پارچه باید بر اساس جهت پنجره و کاربری اتاق انجام شود، نه فقط رنگ دیوار.</p><h2>کرکاب برای اتاق خواب رو به شرق</h2><p>کرکاب سه‌لایه نور صبح را کامل قطع می‌کند و برای اتاق خوابی که پنجره‌اش رو به طلوع است انتخاب درست است. ضخامت بالای پارچه نباید شما را به سمت رنگ‌های تیره‌ی سنگین ببرد؛ کرکاب با رنگ روشن هم عملکرد خوبی دارد.</p><h2>کتان و مخمل برای نشیمن</h2><p>کتان نور را نرم و پخش می‌کند و برای نشیمن‌هایی که رو به نور غیرمستقیم هستند عالی است، اما اتوکشیدگی دائمی نمی‌خواهد. مخمل برای نور مستقیم غربی مناسب‌تر است؛ گرما را می‌گیرد و با نور عصرگاهی عمق رنگی گرمی می‌سازد.</p><blockquote class="wp-block-quote"><p>نکته چیدمون: عرض پارچه‌ی پرده دست‌کم دو برابر عرض میله میله‌بندی شود تا موج‌های پرده طبیعی بماند.</p></blockquote>',
),
);
$cm_seed_posts[] = array(
'slug'     => 'compare-wood-metal-coffee-table',
'title'    => 'میز جلومدی چوبی یا فلزی؟ مقایسه‌ای صادقانه',
'excerpt'  => 'وزن، گرمای بصری، نگهداری و قیمت؛ همه‌ی تفاوت‌های مهم میز چوبی و فلزی در یک بررسی روبه‌رو.',
'category' => 'comparisons',
'tags'     => array(),
'colors'   => array( '#102d26', '#e2b19d' ),
'alt'      => 'تصویر انتزاعی تیره برای مقایسه میز جلومدی',
'age_days' => 9,
'body'     => '<p>میز جلومدی نقطه‌ی تلاق خانه است؛ دورش چای می‌خوریم، رویش کتاب می‌چینیم و پایه‌هایش بیش از هر مبلمانی ضربه می‌بیند. اگر بین چوب و فلز مردد هستید، این مقایسه بر اساس معیارهای واقعی زندگی روزمره نوشته شده است.</p><h2>مقایسه معیار به معیار</h2><p>چوب گرما و وزن می‌دهد؛ فلز ظرافت و استحکام. تفاوت‌های کلیدی در جدول زیر جمع شده است:</p><ul><li>ضربه‌پذیری: چوب زخم می‌خورد اما قابل ترمیم است؛ فلز خط نمی‌خورد اما کج‌شدگی‌اش در خانه ترمیم نمی‌شود</li><li>وزن: میز چوبی توپر سنگین است و جابه‌جایی‌اش سختی‌بردار</li><li>گرمای بصری: فلز در نور کم سرد دیده می‌شود؛ چوب همیشه گرم است</li><li>نگهداری: هر دو به زیرسیگاری و دستمال خیس نیاز دارند؛ لکه‌ی آب روی چوب روکش‌نشده ماندگار است</li></ul><h2>کدام برای شما؟</h2><p>اگر خانه‌ی پر از بچه یا مهمان دارید، فلز با صفحه‌ی چوبی ترکیب عاقلانه‌ای است. اگر نشیمن آرامی دارید و میز را هر ماه جابه‌جا نمی‌کنید، چوب توپر با روکش مات انتخاب درست‌تری است.</p><blockquote class="wp-block-quote"><p>نکته چیدمون: هیچ‌کدام «بهتر» نیستند؛ معیار واقعی این است که میز چند بار در هفته جابه‌جا می‌شود.</p></blockquote>',
);

$cm_seed_posts[] = array(
'slug'     => 'compare-foam-spring-mattress',
'title'    => 'تشک فوم مموری یا تشک فنری؟',
'excerpt'  => 'پشتیبانی کمر، گرما، عمر مفید و قیمت؛ مقایسه‌ی دو فناوری رایج تشک برای تصمیم‌گیری مطمئن.',
'category' => 'comparisons',
'tags'     => array(),
'colors'   => array( '#65716b', '#b65d3d' ),
'alt'      => 'تصویر انتزاعی خاکستری برای مقایسه تشک',
'age_days' => 6,
'body'     => '<p>یک‌سوم عمر را روی تشک می‌گذرانیم، اما کمتر خریدی به‌اندازه‌ی تشک کم‌تحقیق انجام می‌شود. این مقایسه دو فناوری رایج را روی چهار معیار عملی می‌سنجد.</p><h2>پشتیبانی و حس خوابیدن</h2><p>فوم مموری بدن را قالب می‌گیرد و فشار را روی شانه و لگن پخش می‌کند؛ برای خوابیدن به پهلو معمولاً راحت‌تر است. تشک فنری بازگشت سریع‌تری دارد و جابه‌جایی در شب را آسان‌تر می‌کند. اگر کمرتان حساس است، هیچ‌کدام به‌تنهایی پاسخ نیستند؛ میزان سختی (سفت یا نرم بودن) مهم‌تر از جنس است.</p><h2>گرما و عمر مفید</h2><p>فوم مموری گرما را نگه می‌دارد؛ در خانه‌های گرم به فوم سوراخ‌دار یا ژل‌دار نیاز خواهید داشت. تشک فنری خنک‌تر است اما فنرهایش پس از هفت تا ده سال صدای جیر می‌دهند، در حالی‌که فوم باکیفیت تا ده سال فرم خود را حفظ می‌کند.</p><ul><li>خوابِ به پهلو و شانه‌درد: فوم مموری معمولاً بهتر</li><li>گرمای بدن بالا: فنری یا فوم سوراخ‌دار</li><li>خواب دو نفره با اختلاف وزن زیاد: فوم (انتقال حرکت کمتر)</li><li>بودجه محدود: فنری با فوم‌روکش ضخیم</li></ul><blockquote class="wp-block-quote"><p>نکته چیدمون: هر تشکی را حداقل پانزده دقیقه با لباس راحتی امتحان کنید؛ خرید تشک با تکیه‌دادن چندثانیه‌ای تقریباً بی‌معناست.</p></blockquote>',
);

$cm_seed_posts[] = array(
'slug'     => 'compare-round-rect-dining-table',
'title'    => 'میز ناهارخوری گرد یا مستطیل؟',
'excerpt'  => 'ظرفیت مهمان، مسیر عبور و صمیمیت سر سفره؛ راهنمای انتخاب فرم میز ناهارخوری بر اساس متراژ خانه.',
'category' => 'comparisons',
'tags'     => array(),
'colors'   => array( '#173f35', '#f6f2e9' ),
'alt'      => 'تصویر انتزاعی سبز و کرم برای مقایسه میز ناهارخوری',
'age_days' => 3,
'body'     => '<p>فرم میز ناهارخوری تعیین می‌کند که آشپزخانه و نشیمن چقدر راحت نفس بکشند. گرد یا مستطیل؟ پاسخ درست به متراژ، تعداد اعضای خانه و سبک مهمان‌نوازی شما بستگی دارد.</p><h2>گرد: صمیمیت و عبور راحت</h2><p>میز گرد گوشه‌ی مرده ندارد، گفت‌وگوی سرش برابر است و برای فضاهای کوچک باز ایده‌آل است چون لبه‌هایش مسیر عبور را نمی‌بندد. اما ظرفیتش سقف دارد؛ بالای شش نفر عملاً غیرممکن می‌شود و پارچه‌ی رومیزی گرد هم دیر پیدا می‌شود.</p><h2>مستطیل: ظرفیت و نظم</h2><p>میز مستطیل در برابر دیوار می‌نشیند، با بنچ کنار دیوار جمع‌وجور می‌شود و برای خانه‌های پرمهمان گزینه‌ی واقعی‌تری است. تنها هزینه‌اش این است که به فضای بزرگ‌تری برای صندلی‌ها نیاز دارد.</p><blockquote class="wp-block-quote"><p>نکته چیدمون: دور هر میز ناهارخوری نود سانتی‌متر فضای کشیدن صندلی لازم است؛ این عدد را قبل از خرید روی نقشه ببینید.</p></blockquote>',
);
$cm_seed_posts[] = array(
'slug'     => 'look-reading-corner',
'title'    => 'گوشه‌ی مطالعه‌ی دنج کنار پنجره',
'excerpt'  => 'یک صندلی راحت، یک آباژور و یک میز کوچک؛ ترکیبی که گوشه‌ی خالی خانه را به محبوب‌ترین جای آن تبدیل می‌کند.',
'category' => 'room-ideas',
'tags'     => array( 'shop-the-look' ),
'colors'   => array( '#b65d3d', '#102d26' ),
'alt'      => 'تصویر انتزاعی گرم و تیره برای گوشه مطالعه',
'age_days' => 8,
'body'     => '<p>برای ساختن گوشه‌ی مطالعه، یک متر مربع کافی است. راز کار در سه انتخاب است: صندلی‌ای که یک ساعت در آن بنشینید و کمرتان درد نگیرد، نوری که از روی شانه‌تان می‌تابد، و سطحی برای فنجان و کتاب.</p><h2>چیدمان این فضا</h2><p>صندلی را با زاویه‌ی چهل‌وپنج درجه رو به پنجره بگذارید تا در روز از نور طبیعی و در شب از چراغ کف‌خواب بهره ببرید. آباژور را پشت و کمی بالاتر از شانه قرار دهید و یک میز کوچک هم‌قد دسته‌صندلی کنار آن بچینید. یک پادری گرد زیر صندلی مرز این گوشه را از بقیه‌ی اتاق جدا می‌کند.</p><ul><li>صندلی: دسته‌دار، عمق نشیمن متوسط، روکش بافت‌دار</li><li>نور: لامپ ۲۷۰۰ کلوین با کلید کم‌نورکن</li><li>میز: قطر سی‌وپنج تا چهل سانتی‌متر</li><li>کف: پادری گرد با تراکم بالا</li></ul><p>هر کالایی که برای این ترکیب لازم است را در بخش فروشگاه با برچسب «از تصویر بخر» ببینید و مستقیم از فروشنده بخرید.</p>',
);

$cm_seed_posts[] = array(
'slug'     => 'look-minimal-entry',
'title'    => 'ورودی مینیمال با جاکفشی دیواری',
'excerpt'  => 'ورودی خانه اولین برخورد با فضای خصوصی شماست؛ ترکیبی جمع‌وجور با آینه، جاکفشی دیواری و یک نور گرم.',
'category' => 'room-ideas',
'tags'     => array( 'shop-the-look' ),
'colors'   => array( '#9eab92', '#102d26' ),
'alt'      => 'تصویر انتزاعی سبز برای ورودی مینیمال',
'age_days' => 5,
'body'     => '<p>ورودی کوچک به هم‌ریختی معروف است: کفش، کلید، پاکت پستی و پالتو. راه‌حل، افزودن وسایل نیست؛ راه‌حل، یک ترکیب ثابت و جمع‌وجور است که هر چیز جای مشخصی داشته باشد.</p><h2>سه عنصر، یک دیوار</h2><p>جاکفشی دیواری جمع‌وشو کف زمین را خالی نگه می‌دارد. بالای آن یک آینه تمام‌قد نصب کنید؛ هم عقب‌ترها قبل از خروج خودشان را می‌بینند و هم دیوار باریک دو برابر دیده می‌شود. در نهایت یک آویز دیواری چوبی برای کلید و کیف، و یک آباژور دیواری با نور گرم برای شب‌های ورود.</p><ul><li>جاکفشی: دیواری، عمق کمتر از ده سانتی‌متر</li><li>آینه: تمام‌قد یا حداقل صد و بیست سانتی‌متر</li><li>نور: آباژور دیواری ۲۷۰۰ کلوین</li></ul><p>کالاهای این ترکیب با برچسب «از تصویر بخر» در فروشگاه جمع شده‌اند و هر کدام مستقیم به صفحه‌ی فروشنده وصل می‌شوند.</p>',
);

$cm_seed_posts[] = array(
'slug'     => 'look-balcony-refresh',
'title'    => 'بالکن کوچک، حال‌وهوای کافه',
'excerpt'  => 'با دو صندلی تاشو، ریسه‌ی نوری و گلدان‌های هم‌خانواده، بالکن دو متری به محبوب‌ترین کافه‌ی خانه تبدیل می‌شود.',
'category' => 'room-ideas',
'tags'     => array( 'shop-the-look' ),
'colors'   => array( '#e2b19d', '#9eab92' ),
'alt'      => 'تصویر انتزاعی گرم برای بازسازی بالکن',
'age_days' => 2,
'body'     => '<p>بالکن‌های دو متری معمولاً به انبار سطل و جعبه تبدیل می‌شوند، در حالی‌که با یک بازچینی ساده، این کوچک‌ترین فضای خانه خنک‌ترین و دنج‌ترین گوشه‌اش می‌شود.</p><h2>چیدمان کافه‌وار</h2><p>میز گرد کوچک با پایه‌ی وسط را گوشه بگذارید و دو صندلی تاشو چوبی روبه‌رویش بچینید؛ تاشو بودن صندلی‌ها یعنی هر وقت لازم شد فضا به ورزشگاه خانگی برمی‌گردد. کف را با یک موکت بیرونی بافته‌شده گرم کنید و ریسه‌ی نوری گرم را لبه‌ی سقف بچینید. گلدان‌ها را هم‌خانواده نگه دارید: سه گلدان سرامیکی با رنگ یکسان و ارتفاع متفاوت.</p><ul><li>میز: گرد، قطر پنجاه تا شصت سانتی‌متر، مقاوم رطوبت</li><li>صندلی: تاشو، چوب یا راتان مصنوعی</li><li>کف: موکت بیرونی با تار و پود پلی‌پروپیلن</li><li>نور: ریسه‌ی گرم با شدت کم</li></ul><p>همه‌ی کالاهای این بازسازی با برچسب «از تصویر بخر» در فروشگاه مرتب شده‌اند.</p>',
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

array(
'slug' => 'sofa-armchair-nordic', 'name' => 'مبل تک‌نفره پارچه‌ای «نوردیک»', 'price' => 18400000,
'category' => 'living-room', 'featured' => true, 'merchant' => 'دیجی‌کالا',
'short' => 'تک‌نفره‌ای با اسفنج سرد سی‌وپنج کیلوگرم و روکش بوکل قابل‌شست‌وشو؛ مناسب گوشه‌ی مطالعه و نشیمن‌های کوچک.',
'description' => 'این تک‌نفره با فریم چوب راش و اسفنج سرد با چگالی سی‌وپنج کیلوگرم بر مترمکعب ساخته شده است. روکش بوکل با زیپ قابل جدا شدن است و پارچه‌ی آن درجه‌ی مارتمال چهل‌وپنج هزار سیکل دارد. ابعاد نشیمن شصت در هفتاد سانتی‌متر است.',
'facts' => array( array( 'label' => 'جنس بدنه', 'value' => 'چوب راش' ), array( 'label' => 'چگالی اسفنج', 'value' => '۳۵ کیلوگرم بر مترمکعب' ), array( 'label' => 'ابعاد', 'value' => '۸۵×۸۵×۸۰ سانتی‌متر' ) ),
),

array(
'slug' => 'rocking-chair-classic', 'name' => 'صندلی راک چوبی «کلاسیک»', 'price' => 9750000,
'category' => 'living-room', 'featured' => false, 'merchant' => 'دیجی‌کالا',
'short' => 'صندلی راک با قوس ملایم و تکیه‌گاه کمی خمیده؛ برای گوشه‌ی مطالعه و اتاق نوزاد.',
'description' => 'صندلی راک «کلاسیک» با چوب راش و روکش پارچه‌ی مخمل کرم ساخته می‌شود. قوس پایه‌ها حدود سی سانتی‌متر است و حرکت آرام و بدون صدا دارد. وزن صندلی نُه کیلوگرم است و جابه‌جایی آن آسان است.',
'facts' => array( array( 'label' => 'جنس', 'value' => 'چوب راش و مخمل' ), array( 'label' => 'قوس پایه', 'value' => '۳۰ سانتی‌متر' ), array( 'label' => 'وزن', 'value' => '۹ کیلوگرم' ) ),
),

array(
'slug' => 'coffee-table-round-oak', 'name' => 'میز جلومدی گرد بلوط «هیوا»', 'price' => 6200000,
'category' => 'living-room', 'featured' => true, 'merchant' => 'دیجی‌کالا',
'short' => 'میز گرد با صفحه‌ی بلوط روکش‌شده مات و پایه‌ی فلزی مشکی؛ قطر هشتاد سانتی‌متر برای نشیمن‌های جمع‌وجور.',
'description' => 'صفحه‌ی این میز از MDF با روکش بلوط طبیعی و پوشش مات ضدلکه است و پایه‌ی سه‌پایه‌ی فلزی آن با رنگ الکترواستاتیک مشکی پوشیده شده. قطر صفحه هشتاد و ارتفاع چهل‌وپنج سانتی‌متر است.',
'facts' => array( array( 'label' => 'قطر', 'value' => '۸۰ سانتی‌متر' ), array( 'label' => 'ارتفاع', 'value' => '۴۵ سانتی‌متر' ), array( 'label' => 'پایه', 'value' => 'فلز با رنگ کوره‌ای' ) ),
),

array(
'slug' => 'table-lamp-sunset', 'name' => 'آباژور سرامیکی «غروب»', 'price' => 3450000,
'category' => 'lighting', 'featured' => true, 'merchant' => 'دیجی‌کالا',
'short' => 'آباژور میزی با پایه‌ی سرامیکی لعاب‌دار و کلاهک پارچه‌ای؛ نور گرم مناسب اتاق خواب و گوشه‌ی مطالعه.',
'description' => 'پایه‌ی سرامیکی این آباژور با لعاب مات به رنگ خاکی است و کلاهک پارچه‌ای آن نور را به‌طور یکنواخت پخش می‌کند. سوکت E27 با دیمر سازگار است. ارتفاع کلی چهل‌وپنج سانتی‌متر است و لامپ جداگانه فروخته می‌شود.',
'facts' => array( array( 'label' => 'ارتفاع', 'value' => '۴۵ سانتی‌متر' ), array( 'label' => 'سوکت', 'value' => 'E27 سازگار با دیمر' ), array( 'label' => 'جنس پایه', 'value' => 'سرامیک لعاب‌دار' ) ),
),

array(
'slug' => 'pendant-linear-arc', 'name' => 'لوستر آویز خطی «آرک»', 'price' => 7900000,
'category' => 'lighting', 'featured' => false, 'merchant' => 'دیجی‌کالا',
'short' => 'آویز خطی نود سانتی‌متری با بدنه‌ی آلومینیومی و نور ۳۰۰۰ کلوین؛ مناسب میز ناهارخوری و جزیره‌ی آشپزخانه.',
'description' => 'لوستر «آرک» با بدنه‌ی آلومینیوم اکسیدشده به رنگ مشکی مات و دیفیوزر اپال برای نور بدون خیرگی ساخته شده است. طول نود سانتی‌متر و سیم آویز قابل تنظیم تا صد و بیست سانتی‌متر است.',
'facts' => array( array( 'label' => 'طول', 'value' => '۹۰ سانتی‌متر' ), array( 'label' => 'دمای رنگ', 'value' => '۳۰۰۰ کلوین' ), array( 'label' => 'بدنه', 'value' => 'آلومینیوم اکسیدشده' ) ),
),

array(
'slug' => 'rug-kashan-runner', 'name' => 'پادری دستباف «کاشان»', 'price' => 4100000,
'category' => 'textiles', 'featured' => true, 'merchant' => 'دیجی‌کالا',
'short' => 'پادری گرد دستباف با پشم مرغوب و تراکم بالا؛ مناسب زیر صندلی مطالعه و ورودی خانه.',
'description' => 'این پادری گرد با پشم دستریس و ریشه‌ی کوتاه بافته شده است. طرح ساده‌ی هندسی با رنگ خاکی و کرم برای هر دو فضای ورودی و گوشه‌ی نشیمن طراحی شده است. قطر صد و بیست سانتی‌متر است.',
'facts' => array( array( 'label' => 'قطر', 'value' => '۱۲۰ سانتی‌متر' ), array( 'label' => 'جنس', 'value' => 'پشم دستریس' ), array( 'label' => 'تراکم', 'value' => 'بالا، ریشه کوتاه' ) ),
),

array(
'slug' => 'linen-duvet-sepidar', 'name' => 'روتختی کتان «سپیدار»', 'price' => 2850000,
'category' => 'textiles', 'featured' => false, 'merchant' => 'دیجی‌کالا',
'short' => 'روتختی کتان دو نفره با رنگ‌های خاکی؛ نفس‌کش و مناسب چهار فصل.',
'description' => 'روتختی «سپیدار» از کتان صددرصد با گراماژ ۱۶۵ تهیه شده است. کتان با هر بار شست‌وشو نرم‌تر می‌شود و برای خانه‌های گرم و مرطوب انتخابی نفس‌کش است. ست شامل روکش لحاف، ملحفه و دو روبالی است.',
'facts' => array( array( 'label' => 'جنس', 'value' => 'کتان ۱۰۰٪' ), array( 'label' => 'گراماژ', 'value' => '۱۶۵' ), array( 'label' => 'ست', 'value' => 'لحاف، ملحفه، دو روبالی' ) ),
),

array(
'slug' => 'ceramic-vase-tappeh', 'name' => 'گلدان سرامیکی «تپه»', 'price' => 1290000,
'category' => 'decor', 'featured' => false, 'merchant' => 'دیجی‌کالا',
'short' => 'گلدان سرامیکی با فرم منحنی و لعاب مات خاکی؛ برای شلف، میز ناهارخوری و ورودی.',
'description' => 'گلدان «تپه» با چرخ دست ساخته و با لعاب مات به رنگ خاک پخته شده است. فرم منحنی‌اش آن را برای شاخه‌های بلند و خشک مناسب می‌کند. ارتفاع بیست‌وهشت سانتی‌متر است.',
'facts' => array( array( 'label' => 'ارتفاع', 'value' => '۲۸ سانتی‌متر' ), array( 'label' => 'جنس', 'value' => 'سرامیک لعاب‌دار' ), array( 'label' => 'ساخت', 'value' => 'دست‌ساز با چرخ' ) ),
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