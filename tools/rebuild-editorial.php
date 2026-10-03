<?php
/** Publish the initial editorial collection once. refresh-seed-media only updates verified original thumbnails. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'publish_posts' ) ) {
	exit;
}

$collection = array(
	array( 'reading-corner-layout', 'یک گوشهٔ آرام برای مطالعه', 'room-ideas', 'reading-corner', 'look-reading-corner.jpg', 'گوشهٔ مطالعه به فضای بزرگی نیاز ندارد؛ جای نشستن، نور مناسب و دسترسی به کتاب‌ها سه جزء اصلی آن‌اند.', array(
		'جای نشستن را از مسیر عبور جدا کن' => 'صندلی را در بخشی قرار بده که رفت‌وآمد خانه از جلوی آن عبور نکند. قبل از جابه‌جایی وسایل، با نشستن در همان نقطه بررسی کن که دید و دسترسی راحت است. کنار پنجره بودن می‌تواند مفید باشد، اما بازشدن پنجره و پرده هم باید ممکن بماند.',
		'نور را در چند ساعت بررسی کن' => 'نور روز در طول روز تغییر می‌کند. برای مطالعه در عصر، یک چراغ با جهت نور قابل تنظیم در نظر بگیر و جای آن را طوری امتحان کن که سایهٔ دست روی صفحه نیفتد. روشنایی مطلوب به عادت مطالعه و شرایط چشم بستگی دارد؛ عکس یک فضا برای قضاوت دربارهٔ نور کافی نیست.',
		'وسایل کم، کاربرد روشن' => 'یک سطح کوچک برای کتاب یا نوشیدنی و محل مشخصی برای کتاب‌های در حال خواندن کافی است. اگر فضا محدود است، اول امکان حرکت و نشستن راحت را حفظ کن و بعد وسایل تزئینی را اضافه کن. رنگ و بافت کوسن یا روکش صندلی می‌تواند این گوشه را با بقیهٔ اتاق مرتبط کند.',
	), true ),
	array( 'compact-workspace-layout', 'میز کار در یک فضای کوچک', 'room-ideas', 'home-office', 'look-compact-home-office.jpg', 'برای ساختن یک فضای کار جمع‌وجور، از کاری که هر روز انجام می‌دهی شروع کن و وسایل را دور آن بچین.', array(
		'ابعاد کار را مشخص کن' => 'لپ‌تاپ، نمایشگر، دفتر و ابزارهایی را که هم‌زمان استفاده می‌کنی روی یک سطح موجود بچین. سطح لازم را اندازه بگیر و مسیر حرکت صندلی را هم حساب کن. عمق و عرض میز را از روی عکس انتخاب نکن؛ این اندازه‌ها باید با وسایل و موقعیت نشستن خودت هماهنگ شوند.',
		'پنجره و نمایشگر' => 'جای نمایشگر را با نور واقعی اتاق امتحان کن. پنجرهٔ روبه‌روی نمایشگر یا پشت سر می‌تواند دید را با بازتاب یا اختلاف روشنایی دشوار کند. پیش از خرید پرده یا چراغ تازه، با تغییر زاویهٔ میز و تنظیم نور راه‌حل‌های ساده را بررسی کن.',
		'کابل و نگهداری وسایل' => 'محل پریز، شارژر و مسیر کابل را پیش از قرار دادن میز مشخص کن. وسایلی را که روزانه لازم داری در دسترس نگه دار و برای باقی وسایل از قفسه یا کشوی نزدیک استفاده کن. بار مجاز قفسه و نوع اتصال آن به دیوار را طبق اطلاعات سازنده بررسی کن.',
	), true ),
	array( 'dining-table-size-guide', 'پیش از انتخاب میز غذاخوری چه چیزهایی را اندازه بگیریم؟', 'guides', '', 'look-cozy-dining-corner.jpg', 'اندازهٔ میز فقط به تعداد نفرات مربوط نیست؛ حرکت صندلی‌ها و مسیر عبور اطراف آن هم بخشی از انتخاب‌اند.', array(
		'فضای قابل استفاده را ثبت کن' => 'طول و عرض محدودهٔ غذاخوری را اندازه بگیر و محل در، کمد، رادیاتور و مسیر عبور را روی یک طرح ساده مشخص کن. محدودهٔ میز پیشنهادی را با کاغذ یا نوار روی زمین نشان بده. این کار اندازهٔ واقعی میز را بهتر از مشاهدهٔ یک عکس روشن می‌کند.',
		'صندلی را هم در اندازه‌گیری وارد کن' => 'عمق صندلی و فضای عقب‌کشیدن آن را در نظر بگیر. اگر دستهٔ صندلی باید زیر میز برود، ارتفاع زیر صفحه و دسته را مقایسه کن. پایه‌های میز هم می‌توانند جای پا یا تعداد صندلی‌های قابل استفاده را محدود کنند.',
		'استفادهٔ روزمره و مهمانی' => 'انتخاب را بر اساس استفادهٔ معمول خانه انجام بده. برای میز بازشو، ابعاد بسته و باز، محل نگهداری قطعهٔ اضافه و دستور نگهداری سازنده را جداگانه بررسی کن. تعداد صندلی اعلام‌شده را بدون دیدن ابعاد و شکل پایه‌ها معیار قطعی قرار نده.',
	), false ),
	array( 'bedroom-lighting-guide', 'نور اتاق خواب را لایه‌به‌لایه انتخاب کنیم', 'guides', '', 'look-calm-green-bedroom.jpg', 'نور عمومی، نور کنار تخت و نور موردی هرکدام کاربرد متفاوتی دارند. جای چراغ‌ها را بر اساس فعالیت‌های واقعی اتاق تعیین کن.', array(
		'کاربرد هر چراغ' => 'برای ورود و جابه‌جایی در اتاق به روشنایی عمومی نیاز داری. مطالعه یا کار کوتاه کنار تخت معمولاً به نور جهت‌دار جداگانه نیاز دارد. پیش از افزودن چراغ تزئینی مشخص کن کدام فعالیت با نور فعلی راحت انجام نمی‌شود.',
		'کنترل نور از جای درست' => 'محل کلید، پریز و دسترسی به کنترل چراغ را از حالت نشسته و درازکش بررسی کن. اگر چراغ دیواری می‌خواهی، موقعیت نصب باید با ارتفاع تخت و محل بالش هماهنگ باشد. اجرای برق و نصب را به فرد واجد صلاحیت بسپار.',
		'مشخصات را با محیط امتحان کن' => 'رنگ نور، پخش نور و امکان کم‌کردن روشنایی را در مشخصات چراغ و لامپ بررسی کن. اگر دیمر وجود دارد، سازگاری آن با لامپ مهم است. رنگ دیوار و پارچه‌ها هم بر حس نور اثر می‌گذارند؛ دربارهٔ نتیجه فقط از روی عکس فروشنده تصمیم نگیر.',
	), false ),
	array( 'round-or-rectangular-table', 'میز گرد یا مستطیل؛ کدام برای فضای تو مناسب‌تر است؟', 'comparisons', '', 'look-japandi-dining.jpg', 'هیچ شکل میزی برای همهٔ خانه‌ها بهتر نیست. تناسب با فضا، تعداد صندلی و مسیر عبور انتخاب را مشخص می‌کند.', array(
		'میز گرد' => 'میز گرد جهت غالب ندارد و در یک فضای تقریباً مربع می‌تواند چیدمان یکپارچه‌ای بسازد. با بزرگ‌شدن قطر، دسترسی به مرکز صفحه دشوارتر می‌شود. تعداد نفرات و محل پایه را از روی ابعاد دقیق مدل بررسی کن؛ گردبودن به‌تنهایی نشانهٔ کم‌جا بودن نیست.',
		'میز مستطیل' => 'فرم مستطیل با فضای کشیده و چیدمان خطی هماهنگ می‌شود. امکان نزدیک‌کردن یک سمت به دیوار در بعضی خانه‌ها کاربردی است، اما دسترسی آن سمت را محدود می‌کند. گوشه‌ها و فاصلهٔ صندلی‌های انتهایی را در مسیر عبور آزمایش کن.',
		'مقایسه روی زمین' => 'دو گزینه را با ابعاد واقعی روی زمین علامت بزن و صندلی‌ها را در حالت استفاده قرار بده. بازشدن درها، عبور هم‌زمان افراد و دسترسی به کمدها را امتحان کن. پس از این بررسی، جنس، کیفیت اتصال و شرایط نگهداری هر مدل را مقایسه کن.',
	), false ),
	array( 'open-shelves-or-closed-storage', 'قفسهٔ باز یا کمد بسته برای فضای کار؟', 'comparisons', '', 'look-work-nook-bookshelf.jpg', 'دسترسی، میزان گردوغبار، نوع وسایل و نظم روزمره در انتخاب فضای نگهداری از ظاهر آن مهم‌ترند.', array(
		'قفسهٔ باز' => 'وسایل پرکاربرد دیده می‌شوند و دسترسی سریع است. در مقابل، سطح‌ها و وسایل نیاز به نظافت منظم دارند و شلوغی بصری بیشتر دیده می‌شود. برای وسایل سنگین، بار مجاز هر طبقه و نوع اتصال قفسه را بررسی کن.',
		'کمد بسته' => 'درها وسایل را از دید پنهان می‌کنند و مرتب نگه‌داشتن ظاهر اتاق آسان‌تر می‌شود. بازشدن در، تهویهٔ لازم برای بعضی وسایل و دسترسی به کابل‌ها باید در طراحی حساب شوند. عمق زیاد همیشه مفید نیست؛ ممکن است دسترسی به انتهای طبقه سخت شود.',
		'ترکیب بر اساس استفاده' => 'وسایل روزانه را در بخش باز یا کشوی نزدیک و ذخیره‌ها را در بخش بسته قرار بده. قبل از انتخاب، وسایل را گروه‌بندی و حجمشان را اندازه بگیر. ابعاد داخلی، ضخامت طبقه، یراق و دستور نصب سازنده را با نیاز خودت مقایسه کن.',
	), false ),
);

$seed_image_alts = array(
	'look-reading-corner.jpg'      => 'چیدمان مفهومی گوشهٔ مطالعه کنار پنجره با صندلی و چراغ رومیزی',
	'look-compact-home-office.jpg' => 'چیدمان مفهومی میز کار کوچک با صندلی و قفسه در کنج اتاق',
	'look-cozy-dining-corner.jpg'  => 'چیدمان مفهومی میز گرد دونفره در کنج آشپزخانه',
	'look-calm-green-bedroom.jpg'  => 'چیدمان مفهومی اتاق خواب با دیوار سبز و روتختی روشن',
	'look-japandi-dining.jpg'      => 'چیدمان مفهومی میز گرد چوبی با دو صندلی و چراغ خطی',
	'look-work-nook-bookshelf.jpg' => 'چیدمان مفهومی قفسهٔ باز کتاب در کنار میز کار چوبی',
);
$previous_seed_images = array(
	'dining-table-size-guide'      => 'look-japandi-dining.jpg',
	'open-shelves-or-closed-storage' => 'look-compact-home-office.jpg',
);
$refresh_seed_media = in_array( 'refresh-seed-media', (array) ( $args ?? array() ), true );

function chidemoon_editorial_seed_image( string $file, string $alt ): int {
	$tagged = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'meta_key' => '_chidemoon_seed_image_file', 'meta_value' => $file, 'fields' => 'ids' ) );
	if ( $tagged ) {
		$current_alt = (string) get_post_meta( $tagged[0], '_wp_attachment_image_alt', true );
		if ( in_array( $current_alt, array( '', 'چیدمان مفهومی خانه' ), true ) ) {
			update_post_meta( $tagged[0], '_wp_attachment_image_alt', $alt );
		}
		return (int) $tagged[0];
	}
	$matches = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'meta_key' => '_wp_attached_file', 'meta_value' => $file, 'meta_compare' => 'LIKE', 'fields' => 'ids' ) );
	foreach ( $matches as $id ) {
		if ( wp_basename( (string) get_attached_file( $id ) ) === $file ) {
			$current_alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( in_array( $current_alt, array( '', 'چیدمان مفهومی خانه' ), true ) ) {
				update_post_meta( $id, '_wp_attachment_image_alt', $alt );
			}
			return (int) $id;
		}
	}
	$path = __DIR__ . '/seed-images/looks/' . $file;
	if ( ! is_file( $path ) ) {
		WP_CLI::error( 'Missing editorial image: ' . $path );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$temp = wp_tempnam( $file );
	if ( ! $temp || ! copy( $path, $temp ) ) {
		if ( $temp ) {
			@unlink( $temp );
		}
		WP_CLI::error( 'Could not prepare editorial image: ' . $file );
	}
	$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $temp ), 0, 'تصویر مفهومی چیدمان خانه' );
	if ( is_wp_error( $id ) ) {
		@unlink( $temp );
		WP_CLI::error( $id->get_error_message() );
	}
	update_post_meta( $id, '_chidemoon_seed_image_file', $file );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	return (int) $id;
}

foreach ( $collection as $item ) {
	list( $slug, $title, $category, $room, $file, $excerpt, $sections, $look ) = $item;
	$existing = get_page_by_path( $slug, OBJECT, 'post' );
	if ( $refresh_seed_media ) {
		if ( ! $existing || ! isset( $previous_seed_images[ $slug ] ) || ! get_post_meta( $existing->ID, '_chidemoon_rebuild_editorial', true ) ) {
			continue;
		}
		$current_image = get_post_thumbnail_id( $existing->ID );
		$previous_file = $previous_seed_images[ $slug ];
		$attached_path = $current_image ? (string) get_attached_file( $current_image ) : '';
		$source_path = __DIR__ . '/seed-images/looks/' . $previous_file;
		$seed_marker = $current_image ? (string) get_post_meta( $current_image, '_chidemoon_seed_image_file', true ) : '';
		$verified_legacy_file = '' === $seed_marker && is_file( $attached_path ) && is_file( $source_path ) && hash_equals( (string) hash_file( 'sha256', $source_path ), (string) hash_file( 'sha256', $attached_path ) );
		if ( $current_image && wp_basename( $attached_path ) === $previous_file && ( $seed_marker === $previous_file || $verified_legacy_file ) ) {
			$image_id = chidemoon_editorial_seed_image( $file, $seed_image_alts[ $file ] );
			set_post_thumbnail( $existing->ID, $image_id );
			WP_CLI::log( 'Updated seed thumbnail only: ' . $slug );
		}
		continue;
	}
	if ( $existing ) {
		$obsolete_notice = '<p><small>تصویر این مطلب یک چیدمان مفهومی است و معرفی محصول یا پروژهٔ اجراشده نیست.</small></p>';
		if ( str_contains( $existing->post_content, $obsolete_notice ) ) {
			wp_update_post( array( 'ID' => $existing->ID, 'post_content' => str_replace( $obsolete_notice, '', $existing->post_content ) ) );
		}
		$elementor_data = (string) get_post_meta( $existing->ID, '_elementor_data', true );
		if ( str_contains( $elementor_data, $obsolete_notice ) ) {
			update_post_meta( $existing->ID, '_elementor_data', str_replace( $obsolete_notice, '', $elementor_data ) );
		}
		WP_CLI::log( 'Preserved editorial post: ' . $slug );
		continue;
	}
	if ( ! class_exists( '\Elementor\Plugin' ) || ! \Elementor\Plugin::$instance ) {
		WP_CLI::error( 'Elementor must be active before creating native editorial content.' );
	}
	if ( ! class_exists( 'Chidemoon_Core_Elementor_Content' ) ) {
		require_once dirname( __DIR__ ) . '/plugins/chidemoon-core/includes/class-chidemoon-core-elementor-content.php';
	}
	$image_id = chidemoon_editorial_seed_image( $file, $seed_image_alts[ $file ] );
	$term = get_category_by_slug( $category );
	if ( ! $term ) {
		$created = wp_insert_term( array( 'guides' => 'راهنمای خرید', 'comparisons' => 'مقایسه‌ها', 'room-ideas' => 'ایده‌های چیدمان' )[ $category ], 'category', array( 'slug' => $category ) );
		if ( is_wp_error( $created ) ) {
			WP_CLI::error( $created->get_error_message() );
		}
		$term = get_term( $created['term_id'] );
	}
	$html = '<p>' . esc_html( $excerpt ) . '</p>';
	foreach ( $sections as $heading => $paragraph ) {
		$html .= '<h2>' . esc_html( $heading ) . '</h2><p>' . esc_html( $paragraph ) . '</p>';
	}
	// Publish only after the native Elementor document has saved successfully.
	$id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_name' => $slug, 'post_title' => $title, 'post_excerpt' => $excerpt, 'post_content' => $html, 'post_category' => array( $term->term_id ), 'comment_status' => 'closed' ), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	set_post_thumbnail( $id, $image_id );
	update_post_meta( $id, '_chidemoon_rebuild_editorial', '2026-09-29' );
	$elements = Chidemoon_Core_Elementor_Content::convert_html( $html, $slug . '-body' );
	if ( $look ) {
		wp_set_post_tags( $id, array( 'shop-the-look' ), true );
		wp_set_object_terms( $id, $room, Chidemoon_Core_Shop_The_Look::TAXONOMY );
		array_unshift( $elements, array( 'id' => substr( md5( $slug . '-look' ), 0, 8 ), 'elType' => 'widget', 'widgetType' => 'chidemoon-shop-the-look', 'settings' => array( 'image' => array( 'id' => $image_id, 'url' => wp_get_attachment_url( $image_id ) ), 'image_alt' => $seed_image_alts[ $file ], 'caption' => 'چیدمان مفهومی؛ محصولات این تصویر برای خرید معرفی نشده‌اند.', 'hotspots' => array() ), 'elements' => array() ) );
		update_post_meta( $id, '_chidemoon_native_look', true );
	}
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	$document = \Elementor\Plugin::$instance->documents->get( $id, false );
	if ( ! $document || ! $document->save( array( 'elements' => array( array( 'id' => substr( md5( $slug ), 0, 8 ), 'elType' => 'container', 'settings' => array( 'content_width' => 'full', 'flex_direction' => 'column', 'padding' => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0' ) ), 'elements' => $elements ) ) ) ) ) {
		WP_CLI::error( 'Could not save native editorial body #' . $id . '; draft preserved.' );
	}
	$published = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
	if ( is_wp_error( $published ) || ! $published ) {
		WP_CLI::error( 'Could not publish native editorial post #' . $id );
	}
	WP_CLI::success( 'Published editorial post #' . $id );
}
