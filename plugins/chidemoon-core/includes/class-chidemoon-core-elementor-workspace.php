<?php
/** A visible map of content sources and the Elementor documents that render them. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Chidemoon_Core_Elementor_Workspace {
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'toolbar' ), 100 );
		add_filter( 'page_row_actions', array( __CLASS__, 'page_actions' ), 10, 2 );
	}

	public static function menu(): void {
		add_submenu_page( 'chidemoon-readiness', 'ویرایش سایت و منبع داده‌ها', 'ویرایش سایت و داده‌ها', 'edit_pages', 'chidemoon-editing', array( __CLASS__, 'render' ) );
	}

	public static function template( string $slug ): ?WP_Post {
		$post = get_page_by_path( 'chidemoon-' . $slug, OBJECT, 'elementor_library' );
		return $post instanceof WP_Post ? $post : null;
	}

	public static function edit_url( int $id ): string {
		return add_query_arg( array( 'post' => $id, 'action' => 'elementor' ), admin_url( 'post.php' ) );
	}

	public static function page_actions( array $actions, WP_Post $post ): array {
		$slug = array( 'shop' => 'product-archive', 'magazine' => 'post-archive' )[ $post->post_name ] ?? '';
		$template = $slug ? self::template( $slug ) : null;
		if ( $template && current_user_can( 'edit_post', $template->ID ) ) {
			$actions['chidemoon-template'] = '<a href="' . esc_url( self::edit_url( (int) $template->ID ) ) . '">ویرایش قالب فهرست با المنتور</a>';
		}
		return $actions;
	}

	public static function toolbar( $bar ): void {
		if ( is_admin() || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$slug = '';
		if ( is_search() ) {
			$slug = 'search-results';
		} elseif ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) {
			$slug = 'product-archive';
		} elseif ( function_exists( 'is_product' ) && is_product() ) {
			$slug = 'product-single';
		} elseif ( is_home() || is_archive() ) {
			$slug = 'post-archive';
		} elseif ( is_singular( 'post' ) ) {
			$slug = 'post-single';
		}
		$template = $slug ? self::template( $slug ) : null;
		if ( $template && current_user_can( 'edit_post', $template->ID ) ) {
			$bar->add_node( array( 'id' => 'chidemoon-edit-template', 'title' => 'ویرایش قالب این صفحه', 'href' => self::edit_url( (int) $template->ID ) ) );
		}
		$bar->add_node( array( 'id' => 'chidemoon-editing', 'title' => 'ویرایش سایت و داده‌ها', 'href' => admin_url( 'admin.php?page=chidemoon-editing' ) ) );
	}

	public static function render(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>ویرایش سایت و منبع داده‌ها</h1><p>ظاهر کارت‌ها در قالب Loop تغییر می‌کند و روی همهٔ موارد اعمال می‌شود. محتوای هر مقاله و توضیح کامل هر محصول را با المنتور همان مورد ویرایش کنید.</p>';
		echo '<h2>صفحه‌ها و قالب‌های قابل ویرایش</h2><table class="widefat striped"><thead><tr><th>بخش</th><th>محل ویرایش</th><th>منبع محتوا</th></tr></thead><tbody>';
			foreach ( array( 'site-header' => array( 'سربرگ و دسته‌بندی', 'عناصر منو در همین قالب؛ کارت‌های پنل از قالب مشترک «کارت‌های دسته‌بندی»' ), 'site-footer' => array( 'پاورقی', 'عناصر همین قالب' ), 'post-single' => array( 'قالب جزئیات مقاله', 'نوشته‌ها ← ویرایش با المنتور؛ عنوان، خلاصه و تصویر شاخص در تنظیمات نوشته' ), 'post-archive' => array( 'مجله و دسته‌های مطلب', 'نوشته‌های منتشرشده و دسته‌های وردپرس؛ منبع Loop Grid را Current Query نگه دارید' ), 'search-results' => array( 'نتایج جست‌وجو', 'محصولات و نوشته‌های منتشرشده از جست‌وجوی جاری؛ منبع Loop Grid: Current Query؛ قالب کارت نتیجه برای ظاهر' ), 'product-single' => array( 'جزئیات محصول', 'نام، قیمت و تصویر در محصول؛ فروشنده، منبع و مشخصات مقایسه در اطلاعات افیلیت؛ مشخصات تکمیلی از ویژگی‌ها و وزن/ابعاد ووکامرس، دیدگاه‌ها از نظرهای محصول؛ توضیح کامل با المنتور همان محصول' ), 'product-archive' => array( 'فهرست محصولات', 'فهرست جاری ووکامرس با Current Query؛ ظاهر کارت از قالب کارت محصول؛ متن و ظاهر تعداد و مرتب‌سازی در ویجت «تعداد و مرتب‌سازی محصولات»' ), 'article-card' => array( 'کارت مقاله، راهنما و چیدمان', 'عنوان، تصویر شاخص و خلاصهٔ همان نوشته' ), 'product-card' => array( 'کارت محصول', 'رکورد همان محصول در ووکامرس؛ متن و ظاهر دکمه‌ها در ویجت «دکمه‌های کارت محصول»' ), 'search-card' => array( 'کارت نتیجهٔ جست‌وجو', 'رکورد محصول یا نوشته؛ برچسب نوع و قیمت از دادهٔ همان مورد' ), 'product-category-card' => array( 'کارت دستهٔ محصول', 'نام، تصویر و لینک از دسته‌های محصول؛ ظاهر کارت در همین قالب' ), 'post-category-card' => array( 'کارت دستهٔ مطلب', 'نام و لینک از دسته‌های وردپرس؛ ظاهر کارت در همین قالب' ), 'category-cards' => array( 'پنل دسته‌بندی سربرگ و برگهٔ دسته‌بندی‌ها', 'عنوان بخش‌ها و فهرست دسته‌ها در Loop Gridهای این قالب مشترک؛ ظاهر هر کارت در قالب کارت دسته' ) ) as $slug => $details ) {
			$post = self::template( $slug );
			echo '<tr><td>' . esc_html( $details[0] ) . '</td><td>';
			if ( $post && current_user_can( 'edit_post', $post->ID ) ) {
				echo '<a class="button" href="' . esc_url( self::edit_url( (int) $post->ID ) ) . '">ویرایش با المنتور</a>';
			} else {
				echo 'قالب هنوز ساخته نشده است';
			}
			echo '</td><td>' . esc_html( $details[1] ) . '</td></tr>';
		}
			$page_sources = array(
				'home' => 'متن صفحه در همین سند؛ فهرست مطالب از بخش Query ویجت Loop Grid',
				'guides' => 'نوشته‌های منتشرشدهٔ دستهٔ «راهنمای خرید» (guides)؛ متن صفحه و Query فهرست در همین سند، متن هر راهنما در المنتور همان نوشته',
				'comparisons' => 'فقط نوشته‌های منتشرشدهٔ دستهٔ «مقایسه‌ها» (comparisons)؛ متن صفحه و Query فهرست در همین سند؛ ابزار محصولات در برگهٔ جداگانهٔ «ابزار مقایسهٔ محصولات»',
				'shop-the-look' => 'نوشته‌های منتشرشدهٔ دستهٔ «ایده‌های چیدمان» (room-ideas) با برچسب shop-the-look؛ برای فیلتر، «فضاها» را در نوشته انتخاب کنید؛ متن و نقاط خرید در المنتور همان نوشته',
				'categories' => 'عنوان صفحه در همین سند؛ ویجت Template به قالب مشترک «کارت‌های دسته‌بندی» وصل است؛ فهرست را در Loop Gridهای آن قالب ویرایش کنید',
				'product-comparison' => 'متن صفحه و تنظیمات مقایسه در همین سند؛ مشخصات از اطلاعات افیلیت محصولات انتخاب‌شده',
			);
			foreach ( array( 'home' => 'صفحهٔ اصلی', 'guides' => 'راهنمای خرید', 'comparisons' => 'مقالات مقایسه', 'shop-the-look' => 'ایده‌های چیدمان', 'categories' => 'دسته‌بندی‌ها', 'product-comparison' => 'ابزار مقایسهٔ محصولات' ) as $slug => $title ) {
			$post = get_page_by_path( $slug );
			if ( $post && current_user_can( 'edit_post', $post->ID ) ) {
					echo '<tr><td>' . esc_html( $title ) . '</td><td><a class="button" href="' . esc_url( self::edit_url( (int) $post->ID ) ) . '">ویرایش با المنتور</a></td><td>' . esc_html( $page_sources[ $slug ] ) . '</td></tr>';
			}
		}
		echo '</tbody></table><h2>مدیریت داده‌ها</h2><p>حذف اتصال پویا در یک عنصر، آن را به متن ثابت تبدیل می‌کند. برای تغییر اطلاعات یک مورد، رکورد اصلی آن را ویرایش کنید تا همهٔ کارت‌ها و صفحهٔ جزئیات هماهنگ بمانند.</p><p>';
			foreach ( array( 'edit.php' => 'نوشته‌ها', 'edit.php?post_type=product' => 'محصولات و فروشنده‌ها', 'edit-tags.php?taxonomy=product_cat&post_type=product' => 'دسته‌های محصول', 'edit-tags.php?taxonomy=category' => 'دسته‌های مطلب', 'edit-tags.php?taxonomy=post_tag' => 'برچسب‌های مطلب', 'edit-tags.php?taxonomy=chidemoon_room' => 'فضاها' ) as $url => $label ) {
			echo '<a class="button" style="margin-inline-end:8px" href="' . esc_url( admin_url( $url ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</p><p>افزودن محصول با واردسازی فایل کاتالوگ یا «محصولات ← افزودن» انجام می‌شود. قیمت، موجودی و مشخصات ثبت‌شده‌اند و خودکار از فروشنده به‌روز نمی‌شوند. وضعیت بررسی و لینک خرید در بخش اطلاعات افیلیت محصول مدیریت می‌شود.</p></div>';
	}
}
