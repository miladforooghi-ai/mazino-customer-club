<?php
/**
 * SMS screens: compose, history, birthday automation and settings.
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_SMS_Admin' ) ) {
	return;
}

/**
 * Admin UI for the SMS module.
 */
class MCC_SMS_Admin {

	/**
	 * Register form handlers.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_mcc_sms_send', array( __CLASS__, 'handle_send' ) );
		add_action( 'admin_post_mcc_sms_settings', array( __CLASS__, 'handle_settings' ) );
		add_action( 'admin_post_mcc_sms_birthday', array( __CLASS__, 'handle_birthday' ) );
		add_action( 'admin_post_mcc_sms_birthday_run', array( __CLASS__, 'handle_birthday_run' ) );
		add_action( 'admin_post_mcc_sms_campaign', array( __CLASS__, 'handle_campaign' ) );
		add_action( 'admin_post_mcc_sms_credit', array( __CLASS__, 'handle_credit' ) );
		add_action( 'admin_post_mcc_sms_lines', array( __CLASS__, 'handle_lines' ) );
		add_action( 'admin_post_mcc_sms_templates', array( __CLASS__, 'handle_templates' ) );
		add_action( 'admin_post_mcc_sms_send_one', array( __CLASS__, 'handle_send_one' ) );
	}

	/**
	 * Guard + capability check.
	 *
	 * @return void
	 */
	private static function guard() {
		if ( ! current_user_can( MCC_Admin::capability() ) ) {
			wp_die( 'دسترسی به این بخش مجاز نیست.' );
		}
	}

	/**
	 * Store a one-time message.
	 *
	 * @param string $type    success|error|warning.
	 * @param string $message Message.
	 * @return void
	 */
	private static function flash( $type, $message ) {
		set_transient( 'mcc_flash_' . get_current_user_id(), array( $type, $message ), 90 );
	}

	/**
	 * Print and clear the stored message.
	 *
	 * @return void
	 */
	private static function print_flash() {
		$key   = 'mcc_flash_' . get_current_user_id();
		$flash = get_transient( $key );

		if ( ! $flash ) {
			return;
		}

		delete_transient( $key );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $flash[0] ),
			esc_html( $flash[1] )
		);
	}

	/**
	 * Redirect helper.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra args.
	 * @return void
	 */
	private static function redirect( $page, $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Read a posted array of values.
	 *
	 * @param string $key POST key.
	 * @return array
	 */
	private static function posted_array( $key ) {
		if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
			return array();
		}

		$values = array();

		foreach ( wp_unslash( $_POST[ $key ] ) as $name => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified by the caller.
			$values[ sanitize_text_field( (string) $name ) ] = sanitize_text_field( (string) $value );
		}

		return $values;
	}

	/**
	 * Send one message to one member from the members table.
	 *
	 * @return void
	 */
	public static function handle_send_one() {
		self::guard();
		check_admin_referer( 'mcc_sms_one' );

		$mobile = isset( $_POST['mobile'] ) ? MCC_Helpers::normalize_phone( sanitize_text_field( wp_unslash( $_POST['mobile'] ) ) ) : '';

		if ( ! MCC_Helpers::is_valid_mobile( $mobile ) ) {
			self::flash( 'error', 'شمارهٔ گیرنده معتبر نیست.' );
			self::redirect( 'mcc-members' );
		}

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		$result = MCC_SMS::send(
			array(
				'kind'        => 'single',
				'title'       => 'پیام تکی به ' . ( '' !== $name ? $name : $mobile ),
				'message'     => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
				'group'       => 'manual',
				'single'      => array(
					'mobile'    => $mobile,
					'name'      => $name,
					'member_id' => isset( $_POST['member_id'] ) ? absint( wp_unslash( $_POST['member_id'] ) ) : 0,
				),
				'template_id' => isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0,
				'params'      => self::posted_array( 'params' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
		} else {
			self::flash( 'success', sprintf( 'پیام برای %s در صف/ارسال ثبت شد.', '' !== $name ? $name : $mobile ) );
		}

		self::redirect( 'mcc-members' );
	}

	/**
	 * Handle the composer form.
	 *
	 * @return void
	 */
	public static function handle_send() {
		self::guard();
		check_admin_referer( 'mcc_sms_send' );

		$scheduled = '';

		if ( isset( $_POST['schedule'] ) && '1' === $_POST['schedule'] ) {
			$year  = isset( $_POST['s_year'] ) ? absint( wp_unslash( $_POST['s_year'] ) ) : 0;
			$month = isset( $_POST['s_month'] ) ? absint( wp_unslash( $_POST['s_month'] ) ) : 0;
			$day   = isset( $_POST['s_day'] ) ? absint( wp_unslash( $_POST['s_day'] ) ) : 0;
			$time  = isset( $_POST['s_time'] ) ? sanitize_text_field( wp_unslash( $_POST['s_time'] ) ) : '10:00';

			if ( ! MCC_Jalali::is_valid_date( $year, $month, $day ) || ! preg_match( '/^\d{1,2}:\d{2}$/', $time ) ) {
				self::flash( 'error', 'تاریخ یا ساعت زمان‌بندی معتبر نیست.' );
				self::redirect( 'mcc-sms' );
			}

			$gregorian = MCC_Jalali::to_gregorian( $year, $month, $day );
			$scheduled = sprintf( '%04d-%02d-%02d %s', $gregorian['gy'], $gregorian['gm'], $gregorian['gd'], str_pad( $time, 5, '0', STR_PAD_LEFT ) );
		}

		$result = MCC_SMS::send(
			array(
				'kind'        => 'bulk',
				'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'message'     => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
				'group'       => isset( $_POST['group'] ) ? sanitize_key( wp_unslash( $_POST['group'] ) ) : 'all',
				'manual'      => isset( $_POST['manual'] ) ? sanitize_textarea_field( wp_unslash( $_POST['manual'] ) ) : '',
				'scheduled'   => $scheduled,
				'template_id' => isset( $_POST['template_id'] ) ? absint( wp_unslash( $_POST['template_id'] ) ) : 0,
				'params'      => self::posted_array( 'params' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
			self::redirect( 'mcc-sms' );
		}

		self::flash( 'success', $scheduled ? 'کمپین زمان‌بندی شد و در فهرست پیامک‌ها قابل پیگیری است.' : 'کمپین ساخته شد؛ وضعیت ارسال را در «پیامک‌های ارسال‌شده» ببینید.' );
		self::redirect( 'mcc-sms-log' );
	}

	/**
	 * Save API settings.
	 *
	 * @return void
	 */
	public static function handle_settings() {
		self::guard();
		check_admin_referer( 'mcc_sms_settings' );

		MCC_Settings::update(
			array(
				'api_key'       => isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '',
				'line_number'   => isset( $_POST['line_number'] ) ? preg_replace( '/\D+/', '', sanitize_text_field( wp_unslash( $_POST['line_number'] ) ) ) : '',
				'business_name' => isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '',
				'mode'          => isset( $_POST['mode'] ) && 'line' === $_POST['mode'] ? 'line' : 'template',
			)
		);

		delete_transient( 'mcc_sms_templates' );

		self::flash( 'success', 'تنظیمات پیامک ذخیره شد.' );
		self::redirect( 'mcc-sms-settings' );
	}

	/**
	 * Save birthday automation.
	 *
	 * @return void
	 */
	public static function handle_birthday() {
		self::guard();
		check_admin_referer( 'mcc_sms_birthday' );

		MCC_Settings::update(
			array(
				'birthday_enabled'     => isset( $_POST['birthday_enabled'] ) ? 'yes' : 'no',
				'birthday_hour'        => isset( $_POST['birthday_hour'] ) ? max( 0, min( 23, absint( wp_unslash( $_POST['birthday_hour'] ) ) ) ) : 10,
				'birthday_template'    => isset( $_POST['birthday_template'] ) ? sanitize_textarea_field( wp_unslash( $_POST['birthday_template'] ) ) : '',
				'birthday_template_id' => isset( $_POST['birthday_template_id'] ) ? absint( wp_unslash( $_POST['birthday_template_id'] ) ) : 0,
				'birthday_params'      => self::posted_array( 'birthday_params' ),
			)
		);

		MCC_SMS::sync_schedule();

		self::flash( 'success', 'تنظیمات تبریک تولد ذخیره شد.' );
		self::redirect( 'mcc-birthday' );
	}

	/**
	 * Manual birthday run.
	 *
	 * @return void
	 */
	public static function handle_birthday_run() {
		self::guard();
		check_admin_referer( 'mcc_sms_birthday_run' );

		$result = MCC_SMS::birthday_run( true );

		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
		} elseif ( 'nobody' === $result ) {
			self::flash( 'warning', 'امروز هیچ عضوی تولد ندارد.' );
		} elseif ( 'disabled' === $result ) {
			self::flash( 'warning', 'ارسال خودکار غیرفعال است.' );
		} else {
			self::flash( 'success', 'پیام تبریک برای متولدین امروز در صف ارسال قرار گرفت.' );
		}

		self::redirect( 'mcc-birthday' );
	}

	/**
	 * Campaign row actions: cancel, delete, refresh.
	 *
	 * @return void
	 */
	public static function handle_campaign() {
		self::guard();

		$id     = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$action = isset( $_GET['mcc_action'] ) ? sanitize_key( wp_unslash( $_GET['mcc_action'] ) ) : '';

		check_admin_referer( 'mcc_campaign_' . $id . '_' . $action );

		if ( ! $id ) {
			self::redirect( 'mcc-sms-log' );
		}

		if ( 'cancel' === $action ) {
			$result = MCC_SMS::cancel( $id );

			if ( is_wp_error( $result ) ) {
				self::flash( 'error', $result->get_error_message() );
			} else {
				self::flash( 'success', 'ارسال زمان‌بندی‌شده لغو شد.' );
			}
		} elseif ( 'delete' === $action ) {
			MCC_SMS::delete( $id );
			self::flash( 'success', 'کمپین از تاریخچه حذف شد.' );
		} elseif ( 'refresh' === $action ) {
			$result = MCC_SMS::refresh( $id );

			if ( is_wp_error( $result ) ) {
				self::flash( 'error', $result->get_error_message() );
			} else {
				self::flash( 'success', sprintf( 'وضعیت %s پیامک به‌روزرسانی شد.', MCC_Helpers::to_persian_digits( (string) $result ) ) );
			}
		}

		self::redirect( 'mcc-sms-log' );
	}

	/**
	 * Refresh the cached credit.
	 *
	 * @return void
	 */
	public static function handle_credit() {
		self::guard();
		check_admin_referer( 'mcc_sms_credit' );

		delete_transient( 'mcc_sms_credit' );

		$credit = MCC_SMS_API::credit();

		if ( is_wp_error( $credit ) ) {
			self::flash( 'error', $credit->get_error_message() );
		} else {
			self::flash( 'success', 'اعتبار فعلی: ' . MCC_Helpers::to_persian_digits( number_format( (float) $credit, 2 ) ) );
		}

		self::redirect( 'mcc-sms-settings' );
	}

	/**
	 * Fetch available lines once and show them.
	 *
	 * @return void
	 */
	public static function handle_lines() {
		self::guard();
		check_admin_referer( 'mcc_sms_lines' );

		$lines = MCC_SMS_API::lines();

		if ( is_wp_error( $lines ) ) {
			self::flash( 'error', $lines->get_error_message() );
		} elseif ( empty( $lines ) ) {
			self::flash( 'warning', 'هیچ خط اختصاصی روی این حساب فعال نیست؛ از حالت «قالب» استفاده کنید.' );
		} else {
			self::flash( 'success', 'خطوط فعال: ' . implode( ' , ', array_map( 'strval', $lines ) ) );
		}

		self::redirect( 'mcc-sms-settings' );
	}

	/**
	 * Refresh the cached template list.
	 *
	 * @return void
	 */
	public static function handle_templates() {
		self::guard();
		check_admin_referer( 'mcc_sms_templates' );

		delete_transient( 'mcc_sms_templates' );

		$templates = MCC_SMS::templates( true );

		if ( is_wp_error( $templates ) ) {
			self::flash( 'error', $templates->get_error_message() );
		} else {
			self::flash( 'success', sprintf( '%s قالب تأییدشده دریافت شد.', MCC_Helpers::to_persian_digits( (string) count( $templates ) ) ) );
		}

		self::redirect( 'mcc-sms-settings' );
	}

	/**
	 * Current credit, cached for five minutes.
	 *
	 * @return float|WP_Error|null
	 */
	private static function credit() {
		$cached = get_transient( 'mcc_sms_credit' );

		if ( false !== $cached ) {
			return null === $cached ? null : (float) $cached;
		}

		if ( ! MCC_Settings::is_configured() ) {
			return null;
		}

		$credit = MCC_SMS_API::credit();

		if ( is_wp_error( $credit ) ) {
			set_transient( 'mcc_sms_credit', null, 120 );

			return $credit;
		}

		set_transient( 'mcc_sms_credit', $credit, 300 );

		return $credit;
	}

	/**
	 * Jalali date selector.
	 *
	 * @param string $prefix Field name prefix.
	 * @param array  $value  Default jy/jm/jd.
	 * @return void
	 */
	private static function date_selects( $prefix, $value ) {
		$months = MCC_Jalali::months();
		$today  = MCC_Jalali::today();
		?>
		<div class="mcc-date">
			<select name="<?php echo esc_attr( $prefix ); ?>_day" aria-label="روز">
				<?php for ( $day = 1; $day <= 31; $day++ ) : ?>
					<option value="<?php echo (int) $day; ?>" <?php selected( (int) $value['day'], $day ); ?>><?php echo esc_html( MCC_Helpers::to_persian_digits( (string) $day ) ); ?></option>
				<?php endfor; ?>
			</select>
			<select name="<?php echo esc_attr( $prefix ); ?>_month" aria-label="ماه">
				<?php foreach ( $months as $index => $label ) : ?>
					<option value="<?php echo (int) $index; ?>" <?php selected( (int) $value['month'], $index ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="<?php echo esc_attr( $prefix ); ?>_year" aria-label="سال">
				<?php for ( $year = $today['jy']; $year <= $today['jy'] + 1; $year++ ) : ?>
					<option value="<?php echo (int) $year; ?>" <?php selected( (int) $value['year'], $year ); ?>><?php echo esc_html( MCC_Helpers::to_persian_digits( (string) $year ) ); ?></option>
				<?php endfor; ?>
			</select>
		</div>
		<?php
	}

	/**
	 * Template selector plus its parameter fields.
	 *
	 * @param string $select_name Field name of the select.
	 * @param string $params_name Field name prefix of the parameter inputs.
	 * @param int    $selected    Selected template id.
	 * @param array  $values      Saved parameter values.
	 * @return void
	 */
	private static function template_picker( $select_name, $params_name, $selected, $values ) {
		$templates = MCC_Settings::is_configured() ? MCC_SMS::templates() : array();

		if ( is_wp_error( $templates ) ) {
			printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $templates->get_error_message() ) );

			return;
		}

		if ( empty( $templates ) ) {
			echo '<div class="notice notice-warning inline"><p>هیچ قالب تأییدشده‌ای روی حساب شما نیست. در پنل sms.ir یک قالب بسازید و پس از تأیید، از «تنظیمات پیامک» فهرست را به‌روزرسانی کنید.</p></div>';

			return;
		}

		$map = array();

		foreach ( $templates as $template ) {
			$map[ $template['id'] ] = $template['params'];
		}
		?>
		<label class="mcc-label" for="mcc-template">قالب تأییدشده</label>
		<select name="<?php echo esc_attr( $select_name ); ?>" id="mcc-template" class="mcc-input" data-mcc-template>
			<option value="">— انتخاب قالب —</option>
			<?php foreach ( $templates as $template ) : ?>
				<option
					value="<?php echo (int) $template['id']; ?>"
					data-params="<?php echo esc_attr( wp_json_encode( $template['params'] ) ); ?>"
					data-text="<?php echo esc_attr( $template['text'] ); ?>"
					<?php selected( (int) $selected, (int) $template['id'] ); ?>
				>
					<?php echo esc_html( $template['title'] . ' (#' . $template['id'] . ')' ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<div class="mcc-template-preview" data-mcc-preview hidden></div>

		<div class="mcc-params" data-mcc-params data-prefix="<?php echo esc_attr( $params_name ); ?>" data-values="<?php echo esc_attr( wp_json_encode( (object) $values ) ); ?>"></div>

		<p class="description">پارامترهای «نام»، «نام کوچک»، «موبایل» و «نام کافه» خودکار پر می‌شوند و نیازی به ورود دستی ندارند؛ بقیه را خودتان وارد کنید.</p>
		<?php
	}

	/**
	 * Modal used by the «send SMS» button of the members table.
	 *
	 * @return void
	 */
	public static function render_one_modal() {
		if ( ! MCC_Settings::is_configured() ) {
			return;
		}

		$is_template = MCC_Settings::is_template_mode();
		?>
		<div class="mcc-modal" data-mcc-modal hidden>
			<div class="mcc-modal-backdrop" data-mcc-close></div>

			<div class="mcc-modal-card" role="dialog" aria-modal="true" aria-labelledby="mcc-modal-title">
				<button type="button" class="mcc-modal-close" data-mcc-close aria-label="بستن">×</button>

				<h2 id="mcc-modal-title">ارسال پیامک</h2>
				<p class="mcc-modal-to" data-mcc-to></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mcc-modal-form">
					<input type="hidden" name="action" value="mcc_sms_send_one">
					<?php wp_nonce_field( 'mcc_sms_one' ); ?>
					<input type="hidden" name="mobile" value="">
					<input type="hidden" name="name" value="">
					<input type="hidden" name="member_id" value="0">

					<?php if ( $is_template ) : ?>
						<?php self::template_picker( 'template_id', 'params', 0, array() ); ?>
					<?php else : ?>
						<label class="mcc-label" for="mcc-one-message">متن پیامک</label>
						<textarea id="mcc-one-message" name="message" rows="4" class="mcc-input" data-mcc-message></textarea>

						<div class="mcc-placeholders">
							<span>جایگذاری:</span>
							<?php foreach ( MCC_SMS::placeholders() as $token => $label ) : ?>
								<button type="button" class="button button-small mcc-insert" data-token="<?php echo esc_attr( $token ); ?>"><?php echo esc_html( $label ); ?></button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<p class="mcc-submit">
						<button type="submit" class="button button-primary">ارسال پیامک</button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Compose screen.
	 *
	 * @return void
	 */
	public static function render_compose() {
		self::guard();

		$groups      = MCC_SMS::groups();
		$credit      = self::credit();
		$mode        = MCC_Settings::mode();
		$is_template = 'template' === $mode;
		$tomorrow    = new DateTimeImmutable( 'tomorrow', wp_timezone() );
		$tomorrow_j  = MCC_Jalali::to_jalali( (int) $tomorrow->format( 'Y' ), (int) $tomorrow->format( 'n' ), (int) $tomorrow->format( 'j' ) );
		?>
		<div class="wrap mcc-admin mcc-sms-page">
			<h1 class="mcc-admin-title">
				<img src="<?php echo esc_url( MCC_URL . 'assets/images/menu-icon.png' ); ?>" alt="" class="mcc-admin-logo" width="28" height="28">
				ارسال پیامک
				<span class="mcc-version">نسخه <?php echo esc_html( MCC_VERSION ); ?></span>
			</h1>

			<?php self::print_flash(); ?>

			<?php if ( ! MCC_Settings::is_configured() ) : ?>
				<div class="notice notice-warning">
					<p>برای ارسال پیامک، ابتدا <a href="<?php echo esc_url( admin_url( 'admin.php?page=mcc-sms-settings' ) ); ?>">کلید API</a> را وارد کنید.</p>
				</div>
			<?php endif; ?>

			<div class="mcc-mode-badge">
				<?php if ( $is_template ) : ?>
					حالت ارسال: <strong>قالب تأییدشده (VERIFY)</strong> — بدون نیاز به خط اختصاصی.
				<?php else : ?>
					حالت ارسال: <strong>خط اختصاصی (متن آزاد)</strong>.
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mcc-sms-settings' ) ); ?>">تغییر حالت</a>
			</div>

			<div class="mcc-sms-layout">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mcc-sms-compose">
					<input type="hidden" name="action" value="mcc_sms_send">
					<?php wp_nonce_field( 'mcc_sms_send' ); ?>

					<div class="mcc-card-block">
						<h3>۱. گیرندگان</h3>

						<label class="screen-reader-text" for="mcc-group">گروه گیرندگان</label>
						<select name="group" id="mcc-group" class="mcc-input" data-mcc-group>
							<?php foreach ( $groups as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>">
									<?php
									if ( 'manual' === $key ) {
										echo esc_html( $label );
									} else {
										echo esc_html( $label . ' (' . MCC_Helpers::to_persian_digits( number_format_i18n( MCC_SMS::group_count( $key ) ) ) . ' نفر)' );
									}
									?>
								</option>
							<?php endforeach; ?>
						</select>

						<div class="mcc-manual-wrap" data-mcc-manual hidden>
							<label class="mcc-label" for="mcc-manual">شماره‌ها (هر خط یک شماره)</label>
							<textarea id="mcc-manual" name="manual" rows="4" class="mcc-input mcc-ltr" placeholder="09121234567&#10;09364753024"></textarea>
						</div>
					</div>

					<div class="mcc-card-block">
						<h3>۲. متن پیامک</h3>

						<label class="mcc-label" for="mcc-title">عنوان (فقط برای فهرست خودتان)</label>
						<input type="text" id="mcc-title" name="title" class="mcc-input" placeholder="مثلاً تخفیف آخر هفته">

						<?php if ( $is_template ) : ?>
							<?php self::template_picker( 'template_id', 'params', 0, array() ); ?>
						<?php else : ?>
							<label class="mcc-label" for="mcc-message">متن پیامک</label>
							<textarea id="mcc-message" name="message" rows="6" class="mcc-input" maxlength="1000" data-mcc-message></textarea>

							<div class="mcc-placeholders">
								<span>جایگذاری:</span>
								<?php foreach ( MCC_SMS::placeholders() as $token => $label ) : ?>
									<button type="button" class="button button-small mcc-insert" data-token="<?php echo esc_attr( $token ); ?>"><?php echo esc_html( $label ); ?></button>
								<?php endforeach; ?>
							</div>

							<div class="mcc-counter" data-mcc-counter>
								<span data-mcc-chars>۰ کاراکتر</span>
								<span data-mcc-parts>۱ پیامک</span>
							</div>
						<?php endif; ?>
					</div>

					<div class="mcc-card-block">
						<h3>۳. زمان ارسال</h3>

						<label class="mcc-switch">
							<input type="checkbox" name="schedule" value="1" data-mcc-schedule>
							<span>ارسال در زمان مشخص</span>
						</label>

						<div class="mcc-schedule-fields" data-mcc-schedule-fields hidden>
							<?php
							self::date_selects(
								's',
								array(
									'day'   => $tomorrow_j['jd'],
									'month' => $tomorrow_j['jm'],
									'year'  => $tomorrow_j['jy'],
								)
							);
							?>
							<input type="time" name="s_time" value="10:00" class="mcc-time">
							<p class="description">
								<?php if ( $is_template ) : ?>
									ارسال زمان‌بندی‌شدهٔ قالب، با کرون وردپرس در همان ساعت انجام می‌شود؛ برای اطمینان، بازدید سایت در آن بازه لازم است.
								<?php else : ?>
									سامانهٔ پیامک زمان‌بندی را از یک ساعت بعد تا ۳۶۵ روز آینده می‌پذیرد و متن نباید جایگذاری داشته باشد.
								<?php endif; ?>
							</p>
						</div>
					</div>

					<p class="mcc-submit">
						<button type="submit" class="button button-primary button-hero" <?php disabled( ! MCC_Settings::is_configured() ); ?>>ارسال پیامک</button>
						<span class="mcc-hint">حداکثر <?php echo esc_html( MCC_Helpers::to_persian_digits( (string) MCC_SMS::MAX_RECIPIENTS ) ); ?> گیرنده در هر ارسال</span>
					</p>
				</form>

				<aside class="mcc-sms-side">
					<div class="mcc-card-block">
						<h3>وضعیت حساب پیامکی</h3>
						<?php if ( is_wp_error( $credit ) ) : ?>
							<p class="mcc-alert-inline"><?php echo esc_html( $credit->get_error_message() ); ?></p>
						<?php elseif ( null === $credit ) : ?>
							<p class="mcc-muted">برای نمایش اعتبار، کلید API را تنظیم کنید.</p>
						<?php else : ?>
							<p class="mcc-credit"><?php echo esc_html( MCC_Helpers::to_persian_digits( number_format( (float) $credit, 2 ) ) ); ?> <span>اعتبار</span></p>
						<?php endif; ?>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="mcc_sms_credit">
							<?php wp_nonce_field( 'mcc_sms_credit' ); ?>
							<button type="submit" class="button button-small">به‌روزرسانی اعتبار</button>
						</form>
					</div>

					<div class="mcc-card-block">
						<h3>راهنما</h3>
						<ul class="mcc-tips">
							<?php if ( $is_template ) : ?>
								<li>در حالت قالب، متن پیام همان متن تأییدشده در sms.ir است و فقط پارامترها را پر می‌کنید.</li>
								<li>هر پیامک با متد VERIFY جداگانه ارسال می‌شود؛ کمپین‌های بزرگ در پس‌زمینه ادامه پیدا می‌کنند.</li>
								<li>برای قالب‌های اطلاع‌رسانی می‌توانید از پارامترهایی مثل تاریخ یا نام استفاده کنید.</li>
							<?php else : ?>
								<li>پیام‌ها از خط اختصاصی تنظیم‌شده ارسال می‌شوند (نیازمند خط اختصاصی).</li>
								<li>اگر از جایگذاری استفاده کنید، برای هر نفر متن جداگانه ارسال می‌شود.</li>
								<li>هر پیامک فارسی حدود ۷۰ کاراکتر در یک صفحه جا می‌گیرد.</li>
							<?php endif; ?>
							<li>گزارش تحویل را می‌توانید در «پیامک‌های ارسال‌شده» به‌روزرسانی کنید.</li>
						</ul>
					</div>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * Campaign history.
	 *
	 * @return void
	 */
	public static function render_log() {
		self::guard();

		$per_page = 20;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$total    = MCC_SMS::campaigns_count();
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$paged    = min( $paged, $pages );
		$rows     = MCC_SMS::campaigns(
			array(
				'per_page' => $per_page,
				'page'     => $paged,
			)
		);

		$labels = array(
			'queued'    => 'در صف ارسال',
			'sending'   => 'در حال ارسال',
			'scheduled' => 'زمان‌بندی‌شده',
			'sent'      => 'ارسال شد',
			'partial'   => 'ارسال جزئی',
			'failed'    => 'ناموفق',
			'canceled'  => 'لغو شد',
			'draft'     => 'پیش‌نویس',
		);

		$kinds = array(
			'bulk'     => 'گروهی',
			'birthday' => 'تبریک تولد',
		);
		?>
		<div class="wrap mcc-admin mcc-sms-page">
			<h1 class="mcc-admin-title">
				<img src="<?php echo esc_url( MCC_URL . 'assets/images/menu-icon.png' ); ?>" alt="" class="mcc-admin-logo" width="28" height="28">
				پیامک‌های ارسال‌شده
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mcc-sms' ) ); ?>" class="page-title-action">ارسال پیامک جدید</a>
			</h1>

			<?php self::print_flash(); ?>

			<?php if ( empty( $rows ) ) : ?>
				<div class="mcc-empty">
					<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
					<h2>هنوز پیامکی ارسال نکرده‌اید.</h2>
					<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=mcc-sms' ) ); ?>">اولین پیامک را بفرستید</a> یا <a href="<?php echo esc_url( admin_url( 'admin.php?page=mcc-birthday' ) ); ?>">تبریک تولد خودکار</a> را فعال کنید.</p>
				</div>
			<?php else : ?>
				<div class="mcc-table-form">
					<table class="wp-list-table widefat fixed striped mcc-table">
						<thead>
							<tr>
								<th class="mcc-col-title">عنوان</th>
								<th>گیرندگان</th>
								<th>وضعیت</th>
								<th>نتیجه</th>
								<th>اعتبار</th>
								<th>زمان</th>
								<th class="mcc-col-actions">عملیات</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<?php
								$status_label = isset( $labels[ $row->status ] ) ? $labels[ $row->status ] : $row->status;
								$kind_label   = isset( $kinds[ $row->kind ] ) ? $kinds[ $row->kind ] : $row->kind;
								$mode_label   = 'template' === $row->mode ? 'قالب' : 'خط';
								$when         = $row->scheduled_at ? $row->scheduled_at : $row->created_at;
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( $row->title ); ?></strong>
										<span class="mcc-kind"><?php echo esc_html( $kind_label . ' · ' . $mode_label ); ?></span>
										<div class="mcc-preview"><?php echo esc_html( mb_substr( (string) $row->message, 0, 70 ) ); ?></div>
									</td>
									<td class="mcc-num"><?php echo esc_html( MCC_Helpers::to_persian_digits( (string) (int) $row->total ) ); ?></td>
									<td>
										<span class="mcc-status mcc-status-<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( $status_label ); ?></span>
										<?php if ( ! empty( $row->error ) ) : ?>
											<div class="mcc-err" title="<?php echo esc_attr( $row->error ); ?>"><?php echo esc_html( mb_substr( (string) $row->error, 0, 60 ) ); ?></div>
										<?php endif; ?>
									</td>
									<td>
										<span class="mcc-ok"><?php echo esc_html( MCC_Helpers::to_persian_digits( (string) (int) $row->sent_count ) ); ?></span> /
										<span class="mcc-bad"><?php echo esc_html( MCC_Helpers::to_persian_digits( (string) (int) $row->failed_count ) ); ?></span>
									</td>
									<td><?php echo esc_html( MCC_Helpers::to_persian_digits( number_format( (float) $row->cost, 2 ) ) ); ?></td>
									<td>
										<?php echo esc_html( MCC_Helpers::to_persian_digits( MCC_Helpers::local_date( $when, 'Y/m/d' ) ) ); ?>
										<span class="mcc-time"><?php echo esc_html( MCC_Helpers::to_persian_digits( MCC_Helpers::local_date( $when, 'H:i' ) ) ); ?></span>
									</td>
									<td class="mcc-actions">
										<?php
										$actions = array();

										if ( 'scheduled' === $row->status ) {
											$actions['cancel'] = 'لغو';
										}

										if ( in_array( $row->status, array( 'sent', 'partial' ), true ) ) {
											$actions['refresh'] = 'بررسی وضعیت';
										}

										$actions['delete'] = 'حذف';

										foreach ( $actions as $action => $label ) {
											$url = wp_nonce_url(
												add_query_arg(
													array(
														'page'       => 'mcc-sms-log',
														'mcc_action' => $action,
														'id'         => (int) $row->id,
													),
													admin_url( 'admin-post.php?action=mcc_sms_campaign' )
												),
												'mcc_campaign_' . (int) $row->id . '_' . $action
											);

											printf(
												'<a href="%1$s" class="mcc-row-link%2$s">%3$s</a>',
												esc_url( $url ),
												'delete' === $action ? ' mcc-delete' : '',
												esc_html( $label )
											);
										}
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php if ( $pages > 1 ) : ?>
					<div class="mcc-pagination">
						<?php for ( $page = 1; $page <= $pages; $page++ ) : ?>
							<?php if ( $page === $paged ) : ?>
								<span class="mcc-page is-current"><?php echo esc_html( MCC_Helpers::to_persian_digits( (string) $page ) ); ?></span>
							<?php else : ?>
								<a class="mcc-page" href="<?php echo esc_url( add_query_arg( array( 'page' => 'mcc-sms-log', 'paged' => $page ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( MCC_Helpers::to_persian_digits( (string) $page ) ); ?></a>
							<?php endif; ?>
						<?php endfor; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Birthday automation screen.
	 *
	 * @return void
	 */
	public static function render_birthday() {
		self::guard();

		$today       = MCC_Jalali::today();
		$enabled     = 'yes' === MCC_Settings::get( 'birthday_enabled' );
		$hour        = (int) MCC_Settings::get( 'birthday_hour', 10 );
		$template    = (string) MCC_Settings::get( 'birthday_template' );
		$is_template = MCC_Settings::is_template_mode();
		$tpl_id      = (int) MCC_Settings::get( 'birthday_template_id' );
		$tpl_params  = (array) MCC_Settings::get( 'birthday_params' );
		$people      = MCC_SMS::recipients( 'birthday_today' );
		$next        = wp_next_scheduled( 'mcc_birthday_cron' );
		$month_count = MCC_SMS::group_count( 'birthday_month' );
		?>
		<div class="wrap mcc-admin mcc-sms-page">
			<h1 class="mcc-admin-title">
				<img src="<?php echo esc_url( MCC_URL . 'assets/images/menu-icon.png' ); ?>" alt="" class="mcc-admin-logo" width="28" height="28">
				تبریک تولد خودکار
			</h1>

			<?php self::print_flash(); ?>

			<?php if ( $enabled && ! $next ) : ?>
				<div class="notice notice-warning">
					<p>ارسال خودکار فعال است اما زمان‌بندی کرون ثبت نشده؛ یک‌بار تنظیمات این صفحه را ذخیره کنید تا زمان‌بندی ساخته شود.</p>
				</div>
			<?php endif; ?>

			<div class="mcc-sms-layout">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mcc-card-block">
					<input type="hidden" name="action" value="mcc_sms_birthday">
					<?php wp_nonce_field( 'mcc_sms_birthday' ); ?>

					<label class="mcc-switch">
						<input type="checkbox" name="birthday_enabled" value="1" <?php checked( $enabled ); ?>>
						<span>ارسال خودکار پیام تبریک در روز تولد</span>
					</label>

					<label class="mcc-label" for="mcc-birthday-hour">ساعت ارسال (به وقت سایت)</label>
					<select name="birthday_hour" id="mcc-birthday-hour" class="mcc-input mcc-input-small">
						<?php for ( $i = 0; $i <= 23; $i++ ) : ?>
							<option value="<?php echo (int) $i; ?>" <?php selected( $hour, $i ); ?>><?php echo esc_html( MCC_Helpers::to_persian_digits( str_pad( (string) $i, 2, '0', STR_PAD_LEFT ) ) . ':۰۰' ); ?></option>
						<?php endfor; ?>
					</select>

					<?php if ( $is_template ) : ?>
						<?php self::template_picker( 'birthday_template_id', 'birthday_params', $tpl_id, $tpl_params ); ?>
					<?php else : ?>
						<label class="mcc-label" for="mcc-birthday-template">متن پیام</label>
						<textarea id="mcc-birthday-template" name="birthday_template" rows="5" class="mcc-input"><?php echo esc_textarea( $template ); ?></textarea>
						<p class="description">می‌توانید از {name} (نام کامل) و {club} (نام سایت) استفاده کنید.</p>
					<?php endif; ?>

					<p class="mcc-submit">
						<button type="submit" class="button button-primary">ذخیرهٔ تنظیمات</button>
					</p>
				</form>

				<aside class="mcc-sms-side">
					<div class="mcc-card-block">
						<h3>وضعیت</h3>
						<ul class="mcc-tips">
							<li>حالت ارسال: <strong><?php echo $is_template ? 'قالب تأییدشده' : 'خط اختصاصی'; ?></strong></li>
							<li>تاریخ امروز: <strong><?php echo esc_html( MCC_Jalali::format( sprintf( '%04d/%02d/%02d', $today['jy'], $today['jm'], $today['jd'] ), true ) ); ?></strong></li>
							<li>متولدین امروز: <strong><?php echo esc_html( MCC_Helpers::to_persian_digits( (string) count( (array) $people ) ) ); ?> نفر</strong></li>
							<li>متولدین این ماه: <strong><?php echo esc_html( MCC_Helpers::to_persian_digits( (string) $month_count ) ); ?> نفر</strong></li>
							<li>
								اجرای بعدی:
								<strong>
									<?php
									echo $next
										? esc_html( MCC_Helpers::to_persian_digits( wp_date( 'Y/m/d H:i', $next ) ) )
										: 'زمان‌بندی نشده';
									?>
								</strong>
							</li>
							<li>
								آخرین اجرا:
								<strong><?php echo esc_html( (string) get_option( 'mcc_birthday_last', '—' ) ); ?></strong>
							</li>
						</ul>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="mcc_sms_birthday_run">
							<?php wp_nonce_field( 'mcc_sms_birthday_run' ); ?>
							<button type="submit" class="button">ارسال دستی تبریک امروز</button>
						</form>
					</div>

					<?php if ( ! empty( $people ) && ! is_wp_error( $people ) ) : ?>
						<div class="mcc-card-block">
							<h3>متولدین امروز</h3>
							<ul class="mcc-people">
								<?php foreach ( array_slice( $people, 0, 12 ) as $person ) : ?>
									<li>
										<strong><?php echo esc_html( $person['name'] ); ?></strong>
										<span class="mcc-phone"><?php echo esc_html( MCC_Helpers::format_phone( $person['mobile'] ) ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * API settings screen.
	 *
	 * @return void
	 */
	public static function render_settings() {
		self::guard();

		$key         = MCC_Settings::api_key();
		$line        = MCC_Settings::line();
		$mode        = MCC_Settings::mode();
		$credit      = self::credit();
		$templates   = MCC_Settings::is_configured() ? MCC_SMS::templates() : array();
		$template_count = is_wp_error( $templates ) ? $templates : count( (array) $templates );
		?>
		<div class="wrap mcc-admin mcc-sms-page">
			<h1 class="mcc-admin-title">
				<img src="<?php echo esc_url( MCC_URL . 'assets/images/menu-icon.png' ); ?>" alt="" class="mcc-admin-logo" width="28" height="28">
				تنظیمات پیامک
			</h1>

			<?php self::print_flash(); ?>

			<div class="mcc-sms-layout">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mcc-card-block">
					<input type="hidden" name="action" value="mcc_sms_settings">
					<?php wp_nonce_field( 'mcc_sms_settings' ); ?>

					<label class="mcc-label" for="mcc-api-key">کلید API (X-API-KEY)</label>
					<input type="text" id="mcc-api-key" name="api_key" value="<?php echo esc_attr( $key ); ?>" class="mcc-input mcc-ltr" autocomplete="off" placeholder="از پنل sms.ir ← برنامه‌نویسان ← کلیدهای API">
					<p class="description">این کلید فقط در دیتابیس همین سایت ذخیره می‌شود.</p>

					<label class="mcc-label" for="mcc-business">نام کافه یا رستوران</label>
					<input type="text" id="mcc-business" name="business_name" value="<?php echo esc_attr( MCC_Settings::get( 'business_name' ) ); ?>" class="mcc-input" placeholder="مثلاً کافه رستوران مازینو">
					<p class="description">در پیامک‌ها، <code>{club}</code> و پارامترهایی مثل <code>#CAFE#</code> با این نام پر می‌شوند. اگر خالی باشد از نام سایت استفاده می‌شود.</p>

					<h3 class="mcc-section-title">حالت ارسال</h3>

					<label class="mcc-radio">
						<input type="radio" name="mode" value="template" <?php checked( 'template', $mode ); ?>>
						<span>
							<strong>قالب تأییدشده (VERIFY)</strong>
							<em>بدون نیاز به خط اختصاصی؛ متن از قالب ساخته‌شده در پنل sms.ir می‌آید و فقط پارامترها پر می‌شوند.</em>
						</span>
					</label>

					<label class="mcc-radio">
						<input type="radio" name="mode" value="line" <?php checked( 'line', $mode ); ?>>
						<span>
							<strong>خط اختصاصی (متن آزاد)</strong>
							<em>نیازمند خط اختصاصی خریداری‌شده (اشخاص حقوقی)؛ امکان ارسال متن دلخواه و زمان‌بندی سمت sms.ir.</em>
						</span>
					</label>

					<label class="mcc-label" for="mcc-line">شمارهٔ خط ارسال (فقط در حالت خط اختصاصی)</label>
					<input type="text" id="mcc-line" name="line_number" value="<?php echo esc_attr( $line ); ?>" class="mcc-input mcc-ltr" placeholder="30004505000017">

					<p class="mcc-submit">
						<button type="submit" class="button button-primary">ذخیرهٔ تنظیمات</button>
					</p>
				</form>

				<aside class="mcc-sms-side">
					<div class="mcc-card-block">
						<h3>اتصال</h3>
						<ul class="mcc-tips">
							<li>حالت: <strong><?php echo 'template' === $mode ? 'قالب تأییدشده' : 'خط اختصاصی'; ?></strong></li>
							<li>کلید API: <strong><?php echo '' !== $key ? 'ثبت شده' : 'ثبت نشده'; ?></strong></li>
							<li>
								قالب‌های تأییدشده:
								<strong>
									<?php
									if ( is_wp_error( $template_count ) ) {
										echo esc_html( $template_count->get_error_message() );
									} else {
										echo esc_html( MCC_Helpers::to_persian_digits( (string) $template_count ) );
									}
									?>
								</strong>
							</li>
							<li>
								اعتبار:
								<strong>
									<?php
									if ( is_wp_error( $credit ) ) {
										echo esc_html( $credit->get_error_message() );
									} elseif ( null === $credit ) {
										echo '—';
									} else {
										echo esc_html( MCC_Helpers::to_persian_digits( number_format( (float) $credit, 2 ) ) );
									}
									?>
								</strong>
							</li>
						</ul>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mcc-inline-form">
							<input type="hidden" name="action" value="mcc_sms_credit">
							<?php wp_nonce_field( 'mcc_sms_credit' ); ?>
							<button type="submit" class="button button-small">تست اتصال و اعتبار</button>
						</form>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mcc-inline-form">
							<input type="hidden" name="action" value="mcc_sms_templates">
							<?php wp_nonce_field( 'mcc_sms_templates' ); ?>
							<button type="submit" class="button button-small">به‌روزرسانی فهرست قالب‌ها</button>
						</form>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mcc-inline-form">
							<input type="hidden" name="action" value="mcc_sms_lines">
							<?php wp_nonce_field( 'mcc_sms_lines' ); ?>
							<button type="submit" class="button button-small">دریافت لیست خطوط</button>
						</form>
					</div>

					<div class="mcc-card-block">
						<h3>راهنما</h3>
						<ul class="mcc-tips">
							<li>کلید API را از پنل sms.ir در بخش «برنامه‌نویسان ← کلیدهای API» بسازید.</li>
							<li>اگر خط اختصاصی ندارید، حالت «قالب» را انتخاب کنید و در پنل sms.ir قالب بسازید.</li>
							<li>فقط قالب‌های <strong>تأییدشده</strong> در این فهرست دیده می‌شوند.</li>
							<li>برای ارسال با قالب، اعتبار کافی در حساب لازم است.</li>
						</ul>
					</div>
				</aside>
			</div>
		</div>
		<?php
	}
}
