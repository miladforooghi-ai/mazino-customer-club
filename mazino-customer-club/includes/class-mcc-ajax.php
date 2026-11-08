<?php
/**
 * AJAX endpoint for the signup form.
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_Ajax' ) ) {
	return;
}

/**
 * Handles wp_ajax_mcc_register.
 */
class MCC_Ajax {

	/**
	 * Max submissions per IP inside the window.
	 *
	 * @var int
	 */
	const RATE_LIMIT = 10;

	/**
	 * Rate limit window in seconds.
	 *
	 * @var int
	 */
	const RATE_WINDOW = 600;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_mcc_register', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_mcc_register', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Send a JSON error and stop.
	 *
	 * @param string $code    Machine readable code.
	 * @param string $message Human readable message.
	 * @param int    $status  HTTP status.
	 * @return void
	 */
	private static function fail( $code, $message, $status = 200 ) {
		wp_send_json_error(
			array(
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Process one submission.
	 *
	 * @return void
	 */
	public static function handle() {
		if ( ! check_ajax_referer( 'mcc_register', 'nonce', false ) ) {
			self::fail( 'bad_nonce', 'نشست شما منقضی شده است. لطفاً صفحه را دوباره بارگذاری کنید.', 403 );
		}

		// Honeypot: real visitors never fill this field. Pretend success for bots.
		$honeypot = isset( $_POST['mcc_website'] ) ? sanitize_text_field( wp_unslash( $_POST['mcc_website'] ) ) : '';

		if ( '' !== $honeypot ) {
			wp_send_json_success(
				array(
					'code' => 'ok',
					'id'   => 0,
				)
			);
		}

		$rate_key = 'mcc_rl_' . md5( MCC_Helpers::ip_hash() );
		$hits     = (int) get_transient( $rate_key );

		if ( $hits >= self::RATE_LIMIT ) {
			self::fail( 'rate_limited', 'تعداد تلاش‌ها بیش از حد مجاز است. لطفاً چند دقیقه بعد دوباره امتحان کنید.', 429 );
		}

		set_transient( $rate_key, $hits + 1, self::RATE_WINDOW );

		$posted     = isset( $_POST['fields'] ) ? sanitize_text_field( wp_unslash( $_POST['fields'] ) ) : 'name,phone,birth';
		$fields     = array_filter( array_map( 'trim', explode( ',', $posted ) ) );
		$needs      = static function ( $field ) use ( $fields ) {
			return in_array( $field, $fields, true );
		};

		$name  = isset( $_POST['name'] ) ? MCC_Helpers::plain_text( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
		$raw_phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$phone = MCC_Helpers::normalize_phone( $raw_phone );

		if ( $needs( 'name' ) ) {
			if ( mb_strlen( $name ) < 2 ) {
				self::fail( 'name_invalid', 'لطفاً نام و نام خانوادگی خود را کامل وارد کنید.' );
			}

			if ( mb_strlen( $name ) > 120 ) {
				self::fail( 'name_too_long', 'نام وارد شده بیش از حد طولانی است.' );
			}
		}

		if ( $needs( 'phone' ) && ! MCC_Helpers::is_valid_mobile( $phone ) ) {
			self::fail( 'phone_invalid', 'شماره تلفن همراه معتبر نیست. نمونهٔ درست: ۰۹۱۲۱۲۳۴۵۶۷' );
		}

		if ( '' !== $phone && ! MCC_Helpers::is_valid_mobile( $phone ) ) {
			self::fail( 'phone_invalid', 'شماره تلفن همراه معتبر نیست. نمونهٔ درست: ۰۹۱۲۱۲۳۴۵۶۷' );
		}

		$birth_year  = isset( $_POST['byear'] ) ? absint( wp_unslash( $_POST['byear'] ) ) : 0;
		$birth_month = isset( $_POST['bmonth'] ) ? absint( wp_unslash( $_POST['bmonth'] ) ) : 0;
		$birth_day   = isset( $_POST['bday'] ) ? absint( wp_unslash( $_POST['bday'] ) ) : 0;

		if ( $needs( 'birth' ) ) {
			if ( ! $birth_year || ! $birth_month || ! $birth_day ) {
				self::fail( 'birth_incomplete', 'لطفاً تاریخ تولد خود را کامل انتخاب کنید.' );
			}

			if ( ! MCC_Jalali::is_valid_date( $birth_year, $birth_month, $birth_day ) ) {
				self::fail( 'birth_invalid', 'تاریخ تولد وارد شده معتبر نیست.' );
			}

			if ( $birth_year > MCC_Jalali::current_year() ) {
				self::fail( 'birth_invalid', 'تاریخ تولد نمی‌تواند در آینده باشد.' );
			}
		} elseif ( ! MCC_Jalali::is_valid_date( $birth_year, $birth_month, $birth_day ) ) {
			$birth_year  = 0;
			$birth_month = 0;
			$birth_day   = 0;
		}

		if ( $needs( 'consent' ) ) {
			$consent = isset( $_POST['consent'] ) ? sanitize_text_field( wp_unslash( $_POST['consent'] ) ) : '';

			if ( '1' !== $consent ) {
				self::fail( 'consent_required', 'برای تکمیل عضویت، باید موافقت خود را اعلام کنید.' );
			}
		}

		if ( '' === $phone ) {
			self::fail( 'phone_invalid', 'لطفاً شماره تلفن همراه خود را وارد کنید.' );
		}

		if ( MCC_DB::phone_exists( $phone ) ) {
			self::fail( 'duplicate', 'این شماره تلفن قبلاً در باشگاه مشتریان ثبت شده است.' );
		}

		$source = isset( $_POST['source'] ) ? MCC_Helpers::source_url( sanitize_text_field( wp_unslash( $_POST['source'] ) ) ) : '';

		$row = array(
			'name'        => $name,
			'phone'       => $phone,
			'birth_date'  => ( $birth_year ? sprintf( '%04d/%02d/%02d', $birth_year, $birth_month, $birth_day ) : '' ),
			'birth_year'  => $birth_year,
			'birth_month' => $birth_month,
			'birth_day'   => $birth_day,
			'source'      => $source,
			'status'      => 'active',
			'ip_hash'     => MCC_Helpers::ip_hash(),
			'user_agent'  => MCC_Helpers::user_agent(),
			'created_at'  => current_time( 'mysql' ),
		);

		$result = MCC_DB::insert( $row );

		if ( is_wp_error( $result ) ) {
			if ( MCC_DB::phone_exists( $phone ) ) {
				self::fail( 'duplicate', 'این شماره تلفن قبلاً در باشگاه مشتریان ثبت شده است.' );
			}

			self::fail( 'db_error', 'ثبت‌نام انجام نشد. لطفاً دوباره تلاش کنید.', 500 );
		}

		/**
		 * Fires after a member successfully joins the club.
		 *
		 * @param int   $id  Member id.
		 * @param array $row Stored row.
		 */
		do_action( 'mcc_member_registered', (int) $result, $row );

		wp_send_json_success(
			array(
				'code' => 'ok',
				'id'   => (int) $result,
			)
		);
	}
}
