<?php
/**
 * Admin screens: members list, deletion, exports and the about page.
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_Admin' ) ) {
	return;
}

/**
 * Everything that lives inside wp-admin.
 */
class MCC_Admin {

	/**
	 * Hook suffix of the members screen.
	 *
	 * @var string
	 */
	private static $hook = '';

	/**
	 * Hook suffix of the about screen.
	 *
	 * @var string
	 */
	private static $about_hook = '';

	/**
	 * Capability required to manage members.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filter the capability required to view and manage club members.
		 *
		 * @param string $capability Capability.
		 */
		return (string) apply_filters( 'mcc_capability', 'manage_options' );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_mcc_export', array( __CLASS__, 'handle_export' ) );
		add_filter( 'set-screen-option', array( __CLASS__, 'screen_option' ), 10, 3 );
	}

	/**
	 * Register the menu and submenus.
	 *
	 * @return void
	 */
	public static function menu() {
		self::$hook = add_menu_page(
			'باشگاه مشتریان',
			'باشگاه مشتریان',
			self::capability(),
			'mcc-members',
			array( __CLASS__, 'render_page' ),
			MCC_URL . 'assets/images/menu-icon.png',
			26
		);

		// Rename the auto-generated first submenu item instead of adding a duplicate.
		global $submenu;

		if ( isset( $submenu['mcc-members'][0] ) ) {
			$submenu['mcc-members'][0][0] = 'اعضای باشگاه';
		}

		self::$about_hook = '';

		$sms_pages = array(
			array( 'mcc-sms', 'ارسال پیامک', 'ارسال پیامک', array( 'MCC_SMS_Admin', 'render_compose' ) ),
			array( 'mcc-sms-log', 'پیامک‌های ارسال‌شده', 'پیامک‌های ارسال‌شده', array( 'MCC_SMS_Admin', 'render_log' ) ),
			array( 'mcc-birthday', 'تبریک تولد', 'تبریک تولد', array( 'MCC_SMS_Admin', 'render_birthday' ) ),
			array( 'mcc-sms-settings', 'تنظیمات پیامک', 'تنظیمات پیامک', array( 'MCC_SMS_Admin', 'render_settings' ) ),
		);

		foreach ( $sms_pages as $sms_page ) {
			add_submenu_page( 'mcc-members', $sms_page[1], $sms_page[2], self::capability(), $sms_page[0], $sms_page[3] );
		}

		add_submenu_page(
			'mcc-members',
			'درباره ما',
			'درباره ما',
			self::capability(),
			'mcc-about',
			array( __CLASS__, 'render_about' )
		);

		if ( self::$hook ) {
			add_action( 'load-' . self::$hook, array( __CLASS__, 'handle_actions' ) );
			add_action( 'load-' . self::$hook, array( __CLASS__, 'add_screen_options' ) );
		}
	}

	/**
	 * Screen option: rows per page.
	 *
	 * @return void
	 */
	public static function add_screen_options() {
		add_screen_option(
			'per_page',
			array(
				'label'   => 'تعداد در هر صفحه',
				'default' => 20,
				'option'  => 'mcc_members_per_page',
			)
		);
	}

	/**
	 * Sanitise the stored screen option.
	 *
	 * @param mixed  $status Value.
	 * @param string $option Option name.
	 * @param mixed  $value  Submitted value.
	 * @return mixed
	 */
	public static function screen_option( $status, $option, $value ) {
		if ( 'mcc_members_per_page' === $option ) {
			return max( 1, min( 500, (int) $value ) );
		}

		return $status;
	}

	/**
	 * Rows per page for the list.
	 *
	 * @return int
	 */
	private static function per_page() {
		$value = (int) get_user_option( 'mcc_members_per_page' );

		if ( $value < 1 ) {
			$value = 20;
		}

		return max( 1, min( 500, $value ) );
	}

	/**
	 * Enqueue admin assets on our screens.
	 *
	 * @param string $hook Current screen hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$pages = array( 'mcc-members', 'mcc-about', 'mcc-sms', 'mcc-sms-log', 'mcc-birthday', 'mcc-sms-settings' );

		if ( ! in_array( $page, $pages, true ) ) {
			return;
		}

		wp_enqueue_style( 'mcc-admin', MCC_URL . 'assets/css/admin.css', array(), MCC_VERSION );
		wp_enqueue_style( 'mcc-sms', MCC_URL . 'assets/css/sms.css', array( 'mcc-admin' ), MCC_VERSION );
		wp_enqueue_script( 'mcc-admin', MCC_URL . 'assets/js/admin.js', array(), MCC_VERSION, true );
		wp_enqueue_script( 'mcc-sms', MCC_URL . 'assets/js/sms.js', array(), MCC_VERSION, true );
	}

	/**
	 * Build a link to the members screen.
	 *
	 * @param array $args Extra query args.
	 * @return string
	 */
	private static function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'mcc-members' ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Handle single and bulk deletions before the page renders.
	 *
	 * @return void
	 */
	public static function handle_actions() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		if ( isset( $_GET['mcc_action'], $_GET['id'], $_GET['_wpnonce'] ) && 'delete' === $_GET['mcc_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$id = absint( wp_unslash( $_GET['id'] ) );

			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'mcc_delete_' . $id ) ) {
				wp_die( 'پیوند حذف معتبر نیست.' );
			}

			$deleted = MCC_DB::delete( array( $id ) );

			wp_safe_redirect(
				self::page_url(
					array(
						'mcc_notice' => 'deleted',
						'mcc_count'  => $deleted,
					)
				)
			);
			exit;
		}

		$action = '';

		if ( isset( $_POST['action'] ) && 'delete' === $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$action = 'delete';
		} elseif ( isset( $_POST['action2'] ) && 'delete' === $_POST['action2'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$action = 'delete';
		}

		if ( 'delete' !== $action ) {
			return;
		}

		check_admin_referer( 'bulk-members' );

		$ids = isset( $_POST['member'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['member'] ) ) : array();

		if ( empty( $ids ) ) {
			wp_safe_redirect( self::page_url( array( 'mcc_notice' => 'none' ) ) );
			exit;
		}

		$deleted = MCC_DB::delete( $ids );

		wp_safe_redirect(
			self::page_url(
				array(
					'mcc_notice' => 'deleted',
					'mcc_count'  => $deleted,
				)
			)
		);
		exit;
	}

	/**
	 * Stream the export file.
	 *
	 * @return void
	 */
	public static function handle_export() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( 'دسترسی به این بخش مجاز نیست.' );
		}

		check_admin_referer( 'mcc_export' );

		$search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		$format = isset( $_POST['format'] ) && 'xlsx' === $_POST['format'] ? 'xlsx' : 'csv';
		$rows   = MCC_DB::get_all( $search );

		MCC_Export::output( $rows, $format );
	}

	/**
	 * Requested sortable column.
	 *
	 * @return string
	 */
	private static function current_orderby() {
		$allowed = array( 'id', 'name', 'phone', 'birth_year', 'created_at' );
		$value   = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $value, $allowed, true ) ? $value : 'created_at';
	}

	/**
	 * Requested sort direction.
	 *
	 * @return string
	 */
	private static function current_order() {
		$value = isset( $_GET['order'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return 'asc' === $value ? 'ASC' : 'DESC';
	}

	/**
	 * Current search term.
	 *
	 * @return string
	 */
	private static function current_search() {
		return isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Sortable table header.
	 *
	 * @param string $key     Column key.
	 * @param string $label   Label.
	 * @param string $search  Current search.
	 * @param string $orderby Current orderby.
	 * @param string $order   Current order.
	 * @return string
	 */
	private static function sort_header( $key, $label, $search, $orderby, $order ) {
		$is_active = $orderby === $key;
		$next      = $is_active && 'ASC' === $order ? 'desc' : 'asc';
		$url       = self::page_url(
			array(
				'orderby' => $key,
				'order'   => $next,
				's'       => $search,
			)
		);

		$indicator = '';

		if ( $is_active ) {
			$indicator = 'ASC' === $order ? '<span class="mcc-arrow">▲</span>' : '<span class="mcc-arrow">▼</span>';
		}

		return sprintf(
			'<th scope="col" class="manage-column mcc-col-%1$s"><a href="%2$s">%3$s%4$s</a></th>',
			esc_attr( $key ),
			esc_url( $url ),
			esc_html( $label ),
			$indicator // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
		);
	}

	/**
	 * Render the members screen.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( 'دسترسی به این بخش مجاز نیست.' );
		}

		global $wpdb;

		$search   = self::current_search();
		$orderby  = self::current_orderby();
		$order    = self::current_order();
		$per_page = self::per_page();
		$total    = MCC_DB::count( $search );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $paged > $pages ) {
			$paged = $pages;
		}

		$rows = MCC_DB::get_members(
			array(
				'search'   => $search,
				'orderby'  => $orderby,
				'order'    => $order,
				'per_page' => $per_page,
				'page'     => $paged,
			)
		);

		$grand_total = MCC_DB::total();
		$today       = MCC_DB::count_since( current_time( 'Y-m-d' ) . ' 00:00:00' );
		$month       = MCC_DB::count_since( current_time( 'Y-m' ) . '-01 00:00:00' );

		$notice = isset( $_GET['mcc_notice'] ) ? sanitize_key( wp_unslash( $_GET['mcc_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count  = isset( $_GET['mcc_count'] ) ? absint( wp_unslash( $_GET['mcc_count'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap mcc-admin">
			<h1 class="mcc-admin-title">
				<img src="<?php echo esc_url( MCC_URL . 'assets/images/menu-icon.png' ); ?>" alt="" class="mcc-admin-logo" width="28" height="28">
				باشگاه مشتریان
				<span class="mcc-version">نسخه <?php echo esc_html( MCC_VERSION ); ?></span>
			</h1>

			<?php if ( 'deleted' === $notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $count ? sprintf( '%s عضو حذف شد.', MCC_Helpers::to_persian_digits( (string) $count ) ) : 'عضوی برای حذف پیدا نشد.' ); ?></p>
				</div>
			<?php elseif ( 'none' === $notice ) : ?>
				<div class="notice notice-warning is-dismissible">
					<p>هیچ عضوی انتخاب نشده بود.</p>
				</div>
			<?php endif; ?>

			<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $wpdb->last_error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $wpdb->last_error ); ?></p></div>
			<?php endif; ?>

			<div class="mcc-stats">
				<div class="mcc-stat">
					<span class="mcc-stat-label">کل اعضا</span>
					<span class="mcc-stat-value"><?php echo esc_html( MCC_Helpers::to_persian_digits( number_format_i18n( $grand_total ) ) ); ?></span>
				</div>
				<div class="mcc-stat">
					<span class="mcc-stat-label">امروز</span>
					<span class="mcc-stat-value"><?php echo esc_html( MCC_Helpers::to_persian_digits( number_format_i18n( $today ) ) ); ?></span>
				</div>
				<div class="mcc-stat">
					<span class="mcc-stat-label">این ماه</span>
					<span class="mcc-stat-value"><?php echo esc_html( MCC_Helpers::to_persian_digits( number_format_i18n( $month ) ) ); ?></span>
				</div>
			</div>

			<div class="mcc-toolbar">
				<form method="get" class="mcc-search-form" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="mcc-members">
					<label class="screen-reader-text" for="mcc-search-input">جستجوی اعضا</label>
					<input type="search" id="mcc-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="جستجو با نام یا شماره تلفن…">
					<button type="submit" class="button">جستجو</button>
					<?php if ( '' !== $search ) : ?>
						<a class="button-link" href="<?php echo esc_url( self::page_url() ); ?>">پاک کردن جستجو</a>
					<?php endif; ?>
				</form>

				<form method="post" class="mcc-export-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="mcc_export">
					<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
					<?php wp_nonce_field( 'mcc_export' ); ?>
					<span class="mcc-export-label">
						دانلود <?php echo esc_html( MCC_Helpers::to_persian_digits( number_format_i18n( $total ) ) ); ?> رکورد:
					</span>
					<button type="submit" name="format" value="xlsx" class="button button-primary">خروجی Excel</button>
					<button type="submit" name="format" value="csv" class="button">خروجی CSV</button>
				</form>
			</div>

			<?php if ( empty( $rows ) ) : ?>
				<div class="mcc-empty">
					<span class="dashicons dashicons-groups" aria-hidden="true"></span>
					<h2><?php echo esc_html( '' !== $search ? 'نتیجه‌ای برای این جستجو پیدا نشد.' : 'هنوز عضوی ثبت‌نام نکرده است.' ); ?></h2>
					<p>
						<?php if ( '' !== $search ) : ?>
							عبارت دیگری را امتحان کنید یا <a href="<?php echo esc_url( self::page_url() ); ?>">همهٔ اعضا</a> را ببینید.
						<?php else : ?>
							ویجت «باشگاه مشتریان» را در المنتور روی صفحه بگذارید؛ اعضای جدید بلافاصله اینجا نمایش داده می‌شوند.
						<?php endif; ?>
					</p>
				</div>
			<?php else : ?>
				<form method="post" class="mcc-table-form" action="<?php echo esc_url( self::page_url() ); ?>">
					<?php wp_nonce_field( 'bulk-members' ); ?>
					<input type="hidden" name="page" value="mcc-members">
					<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
					<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
					<input type="hidden" name="order" value="<?php echo esc_attr( strtolower( $order ) ); ?>">

					<div class="mcc-table-tools">
						<label class="screen-reader-text" for="mcc-bulk-action">عملیات گروهی</label>
						<select name="action" id="mcc-bulk-action">
							<option value="">عملیات گروهی</option>
							<option value="delete">حذف</option>
						</select>
						<button type="submit" class="button" name="doaction" value="1">اجرا</button>
						<span class="mcc-table-count">
							<?php
							echo esc_html(
								sprintf(
									'نمایش %1$s تا %2$s از %3$s عضو',
									MCC_Helpers::to_persian_digits( (string) ( ( $paged - 1 ) * $per_page + 1 ) ),
									MCC_Helpers::to_persian_digits( (string) min( $paged * $per_page, $total ) ),
									MCC_Helpers::to_persian_digits( (string) $total )
								)
							);
							?>
						</span>
					</div>

					<table class="wp-list-table widefat fixed striped mcc-table">
						<thead>
							<tr>
								<td class="manage-column column-cb check-column"><input type="checkbox" class="mcc-check-all" aria-label="انتخاب همه"></td>
								<?php
								echo self::sort_header( 'name', 'نام و نام خانوادگی', $search, $orderby, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo self::sort_header( 'phone', 'شماره تلفن همراه', $search, $orderby, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo self::sort_header( 'birth_year', 'تاریخ تولد', $search, $orderby, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo self::sort_header( 'created_at', 'تاریخ عضویت', $search, $orderby, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<th class="check-column" scope="row">
										<input type="checkbox" name="member[]" value="<?php echo (int) $row->id; ?>">
									</th>
									<td class="mcc-cell-name">
										<strong><?php echo esc_html( $row->name ); ?></strong>
										<?php
										$delete_url = wp_nonce_url(
											self::page_url(
												array(
													'mcc_action' => 'delete',
													'id'         => (int) $row->id,
												)
											),
											'mcc_delete_' . (int) $row->id
										);
										?>
										<div class="mcc-row-actions">
											<a href="<?php echo esc_url( $delete_url ); ?>" class="mcc-delete" data-mcc-confirm="این عضو حذف شود؟ این کار قابل بازگشت نیست.">حذف</a>
										</div>
									</td>
									<td>
										<a href="tel:<?php echo esc_attr( $row->phone ); ?>" class="mcc-phone"><?php echo esc_html( MCC_Helpers::format_phone( $row->phone ) ); ?></a>
										<?php if ( class_exists( 'MCC_Settings' ) && MCC_Settings::is_configured() ) : ?>
											<button type="button" class="button button-small mcc-send-one"
												data-mobile="<?php echo esc_attr( $row->phone ); ?>"
												data-name="<?php echo esc_attr( $row->name ); ?>"
												data-id="<?php echo (int) $row->id; ?>">پیام</button>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( '' === (string) $row->birth_date ) : ?>
											<span class="mcc-muted">ثبت نشده</span>
										<?php else : ?>
											<?php echo esc_html( MCC_Jalali::format( $row->birth_date, true ) ); ?>
											<?php
											$age = (int) $row->birth_year ? MCC_Jalali::age( $row->birth_year, $row->birth_month, $row->birth_day ) : 0;

											if ( $age > 0 ) :
												?>
												<span class="mcc-badge"><?php echo esc_html( MCC_Helpers::to_persian_digits( (string) $age ) ); ?> ساله</span>
											<?php endif; ?>
										<?php endif; ?>
									</td>
									<td>
										<span class="mcc-date-cell">
											<?php echo esc_html( MCC_Helpers::to_persian_digits( MCC_Helpers::local_date( $row->created_at, 'Y/m/d' ) ) ); ?>
											<span class="mcc-time"><?php echo esc_html( MCC_Helpers::to_persian_digits( MCC_Helpers::local_date( $row->created_at, 'H:i' ) ) ); ?></span>
										</span>
										<?php if ( ! empty( $row->source ) ) : ?>
											<span class="mcc-source" title="<?php echo esc_attr( $row->source ); ?>"><?php echo esc_html( (string) wp_parse_url( $row->source, PHP_URL_PATH ) ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php echo self::pagination( $paged, $pages, $total, $search, $orderby, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</form>

				<?php if ( class_exists( 'MCC_SMS_Admin' ) ) : ?>
					<?php MCC_SMS_Admin::render_one_modal(); ?>
				<?php endif; ?>
			<?php endif; ?>

			<div class="mcc-help">
				<h2>چطور فرم عضویت را نمایش بدهم؟</h2>
				<p>
					در ویرایشگر المنتور، از دستهٔ «باشگاه مشتریان» ویجت <strong>باشگاه مشتریان</strong> را روی صفحه بکشید.
					تمام متن‌ها، تصویر، برچسب‌ها، پیام تشکر و رنگ‌ها از پنل المنتور قابل تغییر است.
				</p>
				<p>
					اگر از المنتور استفاده نمی‌کنید، همین فرم را با شورت‌کد
					<code>[mazino_customer_club]</code>
					در هر برگه یا نوشته‌ای قرار دهید.
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Pagination markup.
	 *
	 * @param int    $paged   Current page.
	 * @param int    $pages   Total pages.
	 * @param int    $total   Total rows.
	 * @param string $search  Search term.
	 * @param string $orderby Sort column.
	 * @param string $order   Sort direction.
	 * @return string
	 */
	private static function pagination( $paged, $pages, $total, $search, $orderby, $order ) {
		if ( $pages < 2 ) {
			return '';
		}

		$link = static function ( $page, $label, $class = '' ) use ( $search, $orderby, $order ) {
			$url = self::page_url(
				array(
					'paged'   => $page,
					's'       => $search,
					'orderby' => $orderby,
					'order'   => strtolower( $order ),
				)
			);

			return sprintf( '<a class="mcc-page %1$s" href="%2$s">%3$s</a>', esc_attr( $class ), esc_url( $url ), esc_html( $label ) );
		};

		$html = '<div class="mcc-pagination">';

		if ( $paged > 1 ) {
			$html .= $link( $paged - 1, 'قبلی', 'mcc-page-prev' );
		}

		$numbers = array();

		for ( $i = 1; $i <= $pages; $i++ ) {
			if ( 1 === $i || $pages === $i || abs( $i - $paged ) <= 2 ) {
				$numbers[] = $i;
			}
		}

		$previous = 0;

		foreach ( $numbers as $number ) {
			if ( $previous && $number - $previous > 1 ) {
				$html .= '<span class="mcc-page-gap">…</span>';
			}

			if ( $number === $paged ) {
				$html .= sprintf( '<span class="mcc-page is-current" aria-current="page">%s</span>', esc_html( MCC_Helpers::to_persian_digits( (string) $number ) ) );
			} else {
				$html .= $link( $number, MCC_Helpers::to_persian_digits( (string) $number ) );
			}

			$previous = $number;
		}

		if ( $paged < $pages ) {
			$html .= $link( $paged + 1, 'بعدی', 'mcc-page-next' );
		}

		$html .= sprintf(
			'<span class="mcc-page-total">%s</span>',
			esc_html( sprintf( 'صفحهٔ %1$s از %2$s', MCC_Helpers::to_persian_digits( (string) $paged ), MCC_Helpers::to_persian_digits( (string) $pages ) ) )
		);

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render the about screen.
	 *
	 * @return void
	 */
	public static function render_about() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( 'دسترسی به این بخش مجاز نیست.' );
		}

		$site   = 'mazinograph.ir';
		$site_h = 'https://mazinograph.ir';
		$phone  = '09364753024';
		?>
		<div class="wrap mcc-admin mcc-about">
			<h1 class="mcc-admin-title">
				<img src="<?php echo esc_url( MCC_URL . 'assets/images/menu-icon.png' ); ?>" alt="" class="mcc-admin-logo" width="28" height="28">
				درباره ما
			</h1>

			<div class="mcc-about-hero">
				<img src="<?php echo esc_url( MCC_URL . 'assets/images/brand-banner.jpg' ); ?>" alt="مازینو گراف — طراحی وب‌سایت و راهکارهای دیجیتال">
			</div>

			<div class="mcc-about-grid">
				<div class="mcc-card-block mcc-brand-card">
					<img class="mcc-about-logo" src="<?php echo esc_url( MCC_URL . 'assets/images/logo.png' ); ?>" alt="لوگوی مازینو گراف" width="96" height="96">
					<div class="mcc-brand-text">
						<h2>گروه نرم‌افزاری مازینو گراف</h2>
						<p>طراحی وب‌سایت و راهکارهای دیجیتال برای کافه و رستوران</p>
					</div>
				</div>

				<div class="mcc-card-block">
					<h3>اطلاعات تماس</h3>
					<ul class="mcc-info-list">
						<li>
							<span class="mcc-info-label">وب‌سایت</span>
							<a href="<?php echo esc_url( $site_h ); ?>" target="_blank" rel="noopener noreferrer" dir="ltr"><?php echo esc_html( $site ); ?></a>
						</li>
						<li>
							<span class="mcc-info-label">توسعه‌دهنده</span>
							<strong>میلاد فروغی</strong>
						</li>
						<li>
							<span class="mcc-info-label">شماره تماس</span>
							<a href="tel:+98<?php echo esc_attr( substr( $phone, 1 ) ); ?>" dir="ltr"><?php echo esc_html( MCC_Helpers::to_persian_digits( $phone ) ); ?></a>
						</li>
					</ul>
				</div>

				<div class="mcc-card-block">
					<h3>این افزونه</h3>
					<p class="mcc-about-text">
						«باشگاه مشتریان مازینو» یک ویجت اختصاصی المنتور برای عضویت مشتریان است: فرم ثبت‌نام با نام، شماره تلفن همراه و
						تاریخ تولد شمسی، پیام تشکر، مدیریت کامل اعضا همراه با خروجی CSV و Excel، و ارسال پیامک تکی، گروهی و
						زمان‌بندی‌شده از طریق سامانهٔ <a href="https://sms.ir" target="_blank" rel="noopener noreferrer">sms.ir</a>
						به‌همراه تبریک تولد خودکار برای اعضای باشگاه.
					</p>
					<p class="mcc-about-meta">
						<span>نسخهٔ افزونه: <strong><?php echo esc_html( MCC_VERSION ); ?></strong></span>
						<span>سازگار با وردپرس <?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
					</p>
				</div>
			</div>

			<p class="mcc-about-footer">
				ساخته‌شده با ❤ توسط <a href="<?php echo esc_url( $site_h ); ?>" target="_blank" rel="noopener noreferrer">گروه نرم‌افزاری مازینو گراف</a>
			</p>
		</div>
		<?php
	}
}
