<?php
/**
 * SMS domain logic: recipients, campaigns, templates, queue and birthday automation.
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_SMS' ) ) {
	return;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- dedicated tables, no core API available.

/**
 * Everything between the admin screens and the sms.ir API.
 */
class MCC_SMS {

	/**
	 * Hard cap for one campaign.
	 *
	 * @var int
	 */
	const MAX_RECIPIENTS = 500;

	/**
	 * Messages sent per worker run in template mode.
	 *
	 * @var int
	 */
	const WORKER_CHUNK = 25;

	/**
	 * Cron hook of the background worker.
	 *
	 * @var string
	 */
	const WORKER_HOOK = 'mcc_sms_worker';

	/**
	 * Campaigns table.
	 *
	 * @return string
	 */
	public static function campaigns_table() {
		global $wpdb;

		return $wpdb->prefix . 'mcc_sms_campaigns';
	}

	/**
	 * Messages table.
	 *
	 * @return string
	 */
	public static function messages_table() {
		global $wpdb;

		return $wpdb->prefix . 'mcc_sms_messages';
	}

	/**
	 * Audience groups.
	 *
	 * @return array<string,string>
	 */
	public static function groups() {
		return array(
			'all'            => 'همهٔ اعضای باشگاه',
			'with_birthday'  => 'اعضای دارای تاریخ تولد',
			'birthday_month' => 'متولدین ماه جاری',
			'birthday_today' => 'متولدین امروز',
			'manual'         => 'شماره‌های دلخواه',
		);
	}

	/**
	 * Count members of a group without loading them.
	 *
	 * @param string $group Group key.
	 * @return int
	 */
	public static function group_count( $group ) {
		global $wpdb;

		$table = MCC_DB::table();
		$where = "WHERE phone <> ''";

		switch ( $group ) {
			case 'with_birthday':
				$where .= ' AND birth_year > 0';
				break;

			case 'birthday_month':
				$today  = MCC_Jalali::today();
				$where .= $wpdb->prepare( ' AND birth_year > 0 AND birth_month = %d', $today['jm'] );
				break;

			case 'birthday_today':
				$today  = MCC_Jalali::today();
				$where .= $wpdb->prepare( ' AND birth_year > 0 AND birth_month = %d AND birth_day = %d', $today['jm'], $today['jd'] );
				break;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i {$where}", $table ) );
	}

	/**
	 * Resolve the recipients of a campaign.
	 *
	 * @param string $group  Group key.
	 * @param string $manual Manual numbers, one per line.
	 * @return array|WP_Error
	 */
	public static function recipients( $group, $manual = '' ) {
		global $wpdb;

		if ( 'manual' === $group ) {
			$found = array();

			foreach ( preg_split( '/[\s,;]+/u', (string) $manual ) as $raw ) {
				$phone = MCC_Helpers::normalize_phone( $raw );

				if ( '' === $phone ) {
					continue;
				}

				if ( ! MCC_Helpers::is_valid_mobile( $phone ) ) {
					return new WP_Error( 'mcc_bad_number', sprintf( 'شمارهٔ «%s» معتبر نیست.', $raw ) );
				}

				$found[ $phone ] = array(
					'mobile'    => $phone,
					'member_id' => 0,
					'name'      => '',
				);
			}

			return array_values( $found );
		}

		if ( ! array_key_exists( $group, self::groups() ) ) {
			$group = 'all';
		}

		$table = MCC_DB::table();
		$where = "WHERE phone <> ''";

		switch ( $group ) {
			case 'with_birthday':
				$where .= ' AND birth_year > 0';
				break;

			case 'birthday_month':
				$today  = MCC_Jalali::today();
				$where .= $wpdb->prepare( ' AND birth_year > 0 AND birth_month = %d', $today['jm'] );
				break;

			case 'birthday_today':
				$today  = MCC_Jalali::today();
				$where .= $wpdb->prepare( ' AND birth_year > 0 AND birth_month = %d AND birth_day = %d', $today['jm'], $today['jd'] );
				break;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, name, phone FROM %i {$where} ORDER BY id ASC LIMIT %d", $table, self::MAX_RECIPIENTS + 1 ) );

		$recipients = array();

		foreach ( (array) $rows as $row ) {
			$recipients[] = array(
				'mobile'    => (string) $row->phone,
				'member_id' => (int) $row->id,
				'name'      => (string) $row->name,
			);
		}

		return $recipients;
	}

	/**
	 * Translate a template parameter name to its automatic value.
	 *
	 * #NAME# becomes the member name, #CAFE# the configured business name.
	 *
	 * @param string $name Parameter name.
	 * @return string Placeholder token or empty string.
	 */
	public static function auto_param_value( $name ) {
		$key = mb_strtolower( trim( (string) $name ) );
		$key = preg_replace( '/[^a-z0-9\x{0600}-\x{06FF}]+/u', '', $key );

		$first = array( 'firstname', 'fname', 'givenname', 'نامکوچک', 'اسم' );

		if ( in_array( $key, $first, true ) ) {
			return '{first_name}';
		}

		$full = array(
			'name',
			'names',
			'fullname',
			'userfullname',
			'username',
			'user',
			'customer',
			'customername',
			'member',
			'membername',
			'client',
			'clientname',
			'namefamily',
			'nameandfamily',
			'نام',
			'نامکامل',
			'نامخانوادگی',
			'نامونامخانوادگی',
			'نامکاربر',
			'مشتری',
			'کاربر',
		);

		if ( in_array( $key, $full, true ) ) {
			return '{name}';
		}

		$mobile = array( 'mobile', 'phone', 'phonenumber', 'mobilenumber', 'cellphone', 'موبایل', 'شماره', 'شمارهموبایل' );

		if ( in_array( $key, $mobile, true ) ) {
			return '{mobile}';
		}

		$business = array(
			'cafe',
			'cafename',
			'restaurant',
			'restaurantname',
			'business',
			'businessname',
			'shop',
			'shopname',
			'store',
			'storename',
			'brand',
			'brandname',
			'club',
			'clubname',
			'کافه',
			'نامکافه',
			'رستوران',
			'نامرستوران',
			'مجموعه',
			'ناممجموعه',
			'فروشگاه',
		);

		if ( in_array( $key, $business, true ) ) {
			return '{club}';
		}

		return '';
	}

	/**
	 * Placeholders usable inside texts and template parameters.
	 *
	 * @return array<string,string>
	 */
	public static function placeholders() {
		return array(
			'{name}'       => 'نام و نام خانوادگی',
			'{first_name}' => 'نام کوچک',
			'{mobile}'     => 'شماره موبایل',
			'{club}'       => 'نام سایت',
		);
	}

	/**
	 * Replace placeholders for one recipient.
	 *
	 * @param string $text      Template.
	 * @param array  $recipient Recipient data.
	 * @return string
	 */
	public static function render( $text, array $recipient ) {
		$aliases = array(
			'{نام}'      => '{name}',
			'{نام کوچک}' => '{first_name}',
			'{نام‌کوچک}' => '{first_name}',
			'{موبایل}'   => '{mobile}',
			'{سایت}'     => '{club}',
		);

		$text  = str_replace( array_keys( $aliases ), array_values( $aliases ), (string) $text );
		$name  = isset( $recipient['name'] ) ? (string) $recipient['name'] : '';
		$parts = preg_split( '/\s+/u', trim( $name ) );
		$first = ! empty( $parts[0] ) ? $parts[0] : 'همراه گرامی';

		return str_replace(
			array( '{name}', '{first_name}', '{mobile}', '{club}' ),
			array( '' !== $name ? $name : 'همراه گرامی', $first, (string) $recipient['mobile'], MCC_Settings::business_name() ),
			$text
		);
	}

	/**
	 * Whether a text uses placeholders.
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	public static function has_placeholders( $text ) {
		return (bool) preg_match( '/\{[^}]{1,30}\}/u', (string) $text );
	}

	/**
	 * Estimated number of SMS parts.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	public static function parts( $text ) {
		$length     = mb_strlen( (string) $text );
		$is_unicode = (bool) preg_match( '/[^\x00-\x7F]/u', (string) $text );

		if ( $is_unicode ) {
			return $length <= 70 ? 1 : (int) ceil( $length / 67 );
		}

		return $length <= 160 ? 1 : (int) ceil( $length / 153 );
	}

	/**
	 * Convert a local "Y-m-d H:i" string to a unix timestamp.
	 *
	 * @param string $local Local datetime.
	 * @return int
	 */
	public static function local_to_timestamp( $local ) {
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', (string) $local, wp_timezone() );

		if ( ! $date ) {
			return 0;
		}

		return $date->getTimestamp();
	}

	/**
	 * Approved templates, cached for ten minutes.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array|WP_Error
	 */
	public static function templates( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( 'mcc_sms_templates' );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$templates = MCC_SMS_API::templates();

		if ( is_wp_error( $templates ) ) {
			return $templates;
		}

		foreach ( $templates as $index => $template ) {
			foreach ( $template['params'] as $position => $parameter ) {
				$templates[ $index ]['params'][ $position ]['auto'] = self::auto_param_value( $parameter['name'] );
			}
		}

		set_transient( 'mcc_sms_templates', $templates, 600 );

		return $templates;
	}

	/**
	 * Render a template text with parameter values.
	 *
	 * @param string $text   Template text with #PARAM# tokens.
	 * @param array  $params name => value.
	 * @return string
	 */
	public static function template_preview( $text, array $params ) {
		$text = (string) $text;

		foreach ( $params as $name => $value ) {
			$text = str_replace( '#' . $name . '#', (string) $value, $text );
		}

		return $text;
	}

	/**
	 * Insert a campaign row.
	 *
	 * @param array $data Row.
	 * @return int
	 */
	private static function insert_campaign( array $data ) {
		global $wpdb;

		$defaults = array(
			'kind'         => 'bulk',
			'mode'         => MCC_Settings::mode(),
			'title'        => '',
			'message'      => '',
			'line'         => MCC_Settings::line(),
			'template_id'  => 0,
			'params'       => '',
			'total'        => 0,
			'sent_count'   => 0,
			'failed_count' => 0,
			'cost'         => 0,
			'status'       => 'draft',
			'pack_ids'     => '',
			'scheduled_at' => null,
			'sent_at'      => null,
			'error'        => '',
			'created_by'   => get_current_user_id(),
			'created_at'   => current_time( 'mysql' ),
		);

		$row     = array_merge( $defaults, $data );
		$formats = array();

		foreach ( $row as $key => $value ) {
			if ( in_array( $key, array( 'total', 'sent_count', 'failed_count', 'created_by', 'template_id' ), true ) ) {
				$formats[] = '%d';
			} elseif ( 'cost' === $key ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}

		$wpdb->insert( self::campaigns_table(), $row, $formats );

		return (int) $wpdb->insert_id;
	}

	/**
	 * Store one row per recipient.
	 *
	 * @param int    $campaign_id Campaign id.
	 * @param array  $recipients  Recipients with text/params.
	 * @param string $status      Initial message status.
	 * @return void
	 */
	private static function insert_messages( $campaign_id, array $recipients, $status ) {
		global $wpdb;

		foreach ( $recipients as $recipient ) {
			$wpdb->insert(
				self::messages_table(),
				array(
					'campaign_id' => (int) $campaign_id,
					'member_id'   => (int) $recipient['member_id'],
					'mobile'      => (string) $recipient['mobile'],
					'message'     => (string) ( $recipient['text'] ?? '' ),
					'params'      => isset( $recipient['params'] ) ? wp_json_encode( $recipient['params'] ) : '',
					'message_id'  => 0,
					'status'      => $status,
					'cost'        => 0,
					'error'       => '',
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%f', '%s', '%s' )
			);
		}
	}

	/**
	 * Create and dispatch a campaign using the configured mode.
	 *
	 * @param array $args Campaign arguments.
	 * @return int|WP_Error Campaign id.
	 */
	public static function send( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'kind'        => 'bulk',
				'title'       => '',
				'message'     => '',
				'group'       => 'all',
				'manual'      => '',
				'scheduled'   => '',
				'template_id' => 0,
				'params'      => array(),
			)
		);

		if ( ! MCC_Settings::is_configured() ) {
			return new WP_Error( 'mcc_not_configured', 'ابتدا کلید API را در «تنظیمات پیامک» وارد کنید.' );
		}

		$recipients = array();

		if ( ! empty( $args['single'] ) && is_array( $args['single'] ) ) {
			$mobile = MCC_Helpers::normalize_phone( isset( $args['single']['mobile'] ) ? $args['single']['mobile'] : '' );

			if ( ! MCC_Helpers::is_valid_mobile( $mobile ) ) {
				return new WP_Error( 'mcc_bad_number', 'شمارهٔ گیرنده معتبر نیست.' );
			}

			$recipients[] = array(
				'mobile'    => $mobile,
				'member_id' => isset( $args['single']['member_id'] ) ? (int) $args['single']['member_id'] : 0,
				'name'      => isset( $args['single']['name'] ) ? MCC_Helpers::plain_text( $args['single']['name'] ) : '',
			);
		} else {
			$recipients = self::recipients( $args['group'], $args['manual'] );

			if ( is_wp_error( $recipients ) ) {
				return $recipients;
			}
		}

		if ( empty( $recipients ) ) {
			return new WP_Error( 'mcc_no_recipients', 'هیچ گیرنده‌ای برای این گروه پیدا نشد.' );
		}

		if ( count( $recipients ) > self::MAX_RECIPIENTS ) {
			return new WP_Error(
				'mcc_too_many',
				sprintf( 'در هر ارسال حداکثر %s گیرنده مجاز است. گروه را محدودتر کنید.', MCC_Helpers::to_persian_digits( (string) self::MAX_RECIPIENTS ) )
			);
		}

		$scheduled_at = '';
		$send_at      = null;

		if ( '' !== trim( (string) $args['scheduled'] ) ) {
			$timestamp = self::local_to_timestamp( $args['scheduled'] );

			if ( ! $timestamp ) {
				return new WP_Error( 'mcc_bad_date', 'زمان ارسال معتبر نیست.' );
			}

			if ( $timestamp < time() + 60 ) {
				return new WP_Error( 'mcc_soon', 'زمان ارسال باید در آینده باشد.' );
			}

			if ( $timestamp > time() + ( 365 * 86400 ) ) {
				return new WP_Error( 'mcc_far', 'زمان ارسال حداکثر می‌تواند ۳۶۵ روز بعد باشد.' );
			}

			$scheduled_at = $args['scheduled'];
			$send_at      = $timestamp;
		}

		if ( MCC_Settings::is_template_mode() ) {
			return self::send_via_template( $args, $recipients, $scheduled_at, $send_at );
		}

		return self::send_via_line( $args, $recipients, $scheduled_at, $send_at );
	}

	/**
	 * Line mode: free text through bulk / likeToLike.
	 *
	 * @param array    $args        Args.
	 * @param array    $recipients  Recipients.
	 * @param string   $scheduled_at Local datetime or ''.
	 * @param int|null $send_at     Timestamp or null.
	 * @return int|WP_Error
	 */
	private static function send_via_line( $args, array $recipients, $scheduled_at, $send_at ) {
		$message = trim( (string) $args['message'] );

		if ( '' === $message ) {
			return new WP_Error( 'mcc_empty_message', 'متن پیامک را وارد کنید.' );
		}

		if ( mb_strlen( $message ) > 1000 ) {
			return new WP_Error( 'mcc_long_message', 'متن پیامک بیش از حد طولانی است.' );
		}

		if ( '' === MCC_Settings::line() ) {
			return new WP_Error( 'mcc_no_line', 'شمارهٔ خط ارسال در تنظیمات وارد نشده است.' );
		}

		if ( $send_at ) {
			if ( $send_at < time() + 3600 ) {
				return new WP_Error( 'mcc_soon', 'در حالت خط اختصاصی، زمان ارسال باید دست‌کم یک ساعت بعد باشد.' );
			}

			if ( self::has_placeholders( $message ) ) {
				return new WP_Error( 'mcc_scheduled_placeholder', 'برای ارسال زمان‌بندی‌شده با خط اختصاصی، متن باید بدون جایگذاری باشد.' );
			}
		}

		$prepared = array();

		foreach ( $recipients as $recipient ) {
			$recipient['text'] = self::render( $message, $recipient );
			$prepared[]        = $recipient;
		}

		$campaign_id = self::insert_campaign(
			array(
				'kind'         => $args['kind'],
				'mode'         => 'line',
				'title'        => '' !== trim( (string) $args['title'] ) ? sanitize_text_field( $args['title'] ) : mb_substr( $message, 0, 60 ),
				'message'      => $message,
				'total'        => count( $prepared ),
				'status'       => $send_at ? 'scheduled' : 'sending',
				'scheduled_at' => $send_at ? $scheduled_at : null,
			)
		);

		if ( ! $campaign_id ) {
			return new WP_Error( 'mcc_db', 'ثبت کمپین در دیتابیس انجام نشد.' );
		}

		self::insert_messages( $campaign_id, $prepared, $send_at ? 'scheduled' : 'pending' );

		return self::dispatch_line( $campaign_id, $prepared, $send_at );
	}

	/**
	 * Push prepared messages to the bulk/likeToLike endpoints.
	 *
	 * @param int      $campaign_id Campaign id.
	 * @param array    $prepared    Recipients with text.
	 * @param int|null $send_at     Timestamp.
	 * @return int|WP_Error
	 */
	private static function dispatch_line( $campaign_id, array $prepared, $send_at ) {
		global $wpdb;

		$messages_table = self::messages_table();
		$campaigns      = self::campaigns_table();

		$cost     = 0.0;
		$sent     = 0;
		$failed   = 0;
		$pack_ids = array();
		$errors   = array();

		foreach ( array_chunk( $prepared, MCC_SMS_API::CHUNK ) as $chunk ) {
			$mobiles = array();
			$texts   = array();

			foreach ( $chunk as $recipient ) {
				$mobiles[] = $recipient['mobile'];
				$texts[]   = $recipient['text'];
			}

			$response = 1 === count( array_unique( $texts ) )
				? MCC_SMS_API::send_bulk( $mobiles, $texts[0], $send_at )
				: MCC_SMS_API::send_like_to_like( $mobiles, $texts, $send_at );

			if ( is_wp_error( $response ) ) {
				$failed  += count( $chunk );
				$errors[] = $response->get_error_message();

				foreach ( $chunk as $recipient ) {
					$wpdb->query(
						$wpdb->prepare(
							'UPDATE %i SET status = %s, error = %s WHERE campaign_id = %d AND mobile = %s AND message_id = 0',
							$messages_table,
							'failed',
							mb_substr( $response->get_error_message(), 0, 250 ),
							$campaign_id,
							$recipient['mobile']
						)
					);
				}

				continue;
			}

			$data        = (array) $response['data'];
			$pack_id     = isset( $data['packId'] ) ? (string) $data['packId'] : '';
			$message_ids = isset( $data['messageIds'] ) ? array_values( (array) $data['messageIds'] ) : array();
			$cost       += isset( $data['cost'] ) ? (float) $data['cost'] : 0;

			if ( '' !== $pack_id ) {
				$pack_ids[] = $pack_id;
			}

			foreach ( $chunk as $index => $recipient ) {
				$message_id = isset( $message_ids[ $index ] ) ? (int) $message_ids[ $index ] : 0;

				if ( $message_id > 0 ) {
					$sent++;

					$wpdb->query(
						$wpdb->prepare(
							'UPDATE %i SET status = %s, message_id = %d WHERE campaign_id = %d AND mobile = %s AND message_id = 0',
							$messages_table,
							$send_at ? 'scheduled' : 'sent',
							$message_id,
							$campaign_id,
							$recipient['mobile']
						)
					);
				} else {
					$failed++;

					$wpdb->query(
						$wpdb->prepare(
							'UPDATE %i SET status = %s, error = %s WHERE campaign_id = %d AND mobile = %s AND message_id = 0',
							$messages_table,
							'failed',
							'شماره در لیست سیاه است یا متن پذیرفته نشد.',
							$campaign_id,
							$recipient['mobile']
						)
					);
				}
			}
		}

		$total  = count( $prepared );
		$status = 'sent';

		if ( $send_at ) {
			$status = $failed === $total ? 'failed' : 'scheduled';
		} elseif ( $failed === $total ) {
			$status = 'failed';
		} elseif ( $failed > 0 ) {
			$status = 'partial';
		}

		$wpdb->update(
			$campaigns,
			array(
				'status'       => $status,
				'sent_count'   => $sent,
				'failed_count' => $failed,
				'cost'         => $cost,
				'pack_ids'     => implode( ',', $pack_ids ),
				'sent_at'      => $send_at ? null : current_time( 'mysql' ),
				'error'        => implode( ' | ', array_unique( $errors ) ),
			),
			array( 'id' => (int) $campaign_id ),
			array( '%s', '%d', '%d', '%f', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return (int) $campaign_id;
	}

	/**
	 * Template mode: VERIFY calls queued per recipient.
	 *
	 * @param array    $args        Args.
	 * @param array    $recipients  Recipients.
	 * @param string   $scheduled_at Local datetime or ''.
	 * @param int|null $send_at     Timestamp or null.
	 * @return int|WP_Error
	 */
	private static function send_via_template( $args, array $recipients, $scheduled_at, $send_at ) {
		$template_id = (int) $args['template_id'];

		if ( $template_id <= 0 ) {
			return new WP_Error( 'mcc_no_template', 'یک قالب تأییدشده انتخاب کنید.' );
		}

		$template = MCC_SMS_API::template( $template_id );

		if ( is_wp_error( $template ) ) {
			return $template;
		}

		if ( 2 !== (int) $template['status'] ) {
			return new WP_Error( 'mcc_template_pending', 'این قالب هنوز تأیید نشده و قابل استفاده نیست.' );
		}

		$values = array();

		foreach ( $template['params'] as $parameter ) {
			$name  = $parameter['name'];
			$value = isset( $args['params'][ $name ] ) ? trim( (string) $args['params'][ $name ] ) : '';

			if ( '' === $value ) {
				$value = self::auto_param_value( $name );
			}

			if ( '' === $value ) {
				return new WP_Error( 'mcc_param_missing', sprintf( 'مقدار پارامتر «%s» را وارد کنید.', $name ) );
			}

			$values[ $name ] = $value;
		}

		$prepared = array();

		foreach ( $recipients as $recipient ) {
			$rendered = array();

			foreach ( $values as $name => $value ) {
				$rendered[ $name ] = self::render( $value, $recipient );
			}

			$recipient['params'] = $rendered;
			$recipient['text']   = self::template_preview( $template['text'], $rendered );

			$prepared[] = $recipient;
		}

		$campaign_id = self::insert_campaign(
			array(
				'kind'         => $args['kind'],
				'mode'         => 'template',
				'title'        => '' !== trim( (string) $args['title'] ) ? sanitize_text_field( $args['title'] ) : $template['title'],
				'message'      => $template['text'],
				'template_id'  => $template_id,
				'params'       => wp_json_encode( $values ),
				'total'        => count( $prepared ),
				'status'       => $send_at ? 'scheduled' : 'queued',
				'scheduled_at' => $send_at ? $scheduled_at : null,
			)
		);

		if ( ! $campaign_id ) {
			return new WP_Error( 'mcc_db', 'ثبت کمپین در دیتابیس انجام نشد.' );
		}

		self::insert_messages( $campaign_id, $prepared, $send_at ? 'scheduled' : 'pending' );

		if ( $send_at ) {
			self::schedule_worker( 120 );

			return (int) $campaign_id;
		}

		self::process_campaign( $campaign_id, self::WORKER_CHUNK );

		if ( self::has_pending() ) {
			self::schedule_worker( 60 );
		}

		return (int) $campaign_id;
	}

	/**
	 * Send a chunk of a template campaign.
	 *
	 * @param int $campaign_id Campaign id.
	 * @param int $limit       Max messages.
	 * @return int Handled messages.
	 */
	public static function process_campaign( $campaign_id, $limit = self::WORKER_CHUNK ) {
		global $wpdb;

		$campaign = self::campaign( $campaign_id );

		if ( ! $campaign || 'template' !== $campaign->mode ) {
			return 0;
		}

		if ( ! in_array( (string) $campaign->status, array( 'queued', 'sending' ), true ) ) {
			return 0;
		}

		$messages_table = self::messages_table();
		$campaigns      = self::campaigns_table();

		$wpdb->update( $campaigns, array( 'status' => 'sending' ), array( 'id' => (int) $campaign_id ), array( '%s' ), array( '%d' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE campaign_id = %d AND status = %s ORDER BY id ASC LIMIT %d',
				$messages_table,
				(int) $campaign_id,
				'pending',
				(int) $limit
			)
		);

		$handled = 0;

		foreach ( (array) $rows as $row ) {
			$params = json_decode( (string) $row->params, true );

			if ( ! is_array( $params ) ) {
				$params = array();
			}

			$list = array();

			foreach ( $params as $name => $value ) {
				$list[] = array(
					'name'  => (string) $name,
					'value' => (string) $value,
				);
			}

			$response = MCC_SMS_API::send_verify( $row->mobile, $campaign->template_id, $list );
			$handled++;

			if ( is_wp_error( $response ) ) {
				$wpdb->update(
					$messages_table,
					array(
						'status' => 'failed',
						'error'  => mb_substr( $response->get_error_message(), 0, 250 ),
					),
					array( 'id' => (int) $row->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);

				continue;
			}

			$data = (array) $response['data'];

			$wpdb->update(
				$messages_table,
				array(
					'status'     => 'sent',
					'message_id' => isset( $data['messageId'] ) ? (int) $data['messageId'] : 0,
					'cost'       => isset( $data['cost'] ) ? (float) $data['cost'] : 0,
					'error'      => '',
				),
				array( 'id' => (int) $row->id ),
				array( '%s', '%d', '%f', '%s' ),
				array( '%d' )
			);
		}

		self::recount_campaign( $campaign_id );

		return $handled;
	}

	/**
	 * Recalculate counters and status of a campaign.
	 *
	 * @param int $campaign_id Campaign id.
	 * @return void
	 */
	private static function recount_campaign( $campaign_id ) {
		global $wpdb;

		$messages_table = self::messages_table();
		$campaigns      = self::campaigns_table();
		$id             = (int) $campaign_id;

		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE campaign_id = %d', $messages_table, $id ) );

		$pending = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE campaign_id = %d AND status IN (%s,%s)', $messages_table, $id, 'pending', 'scheduled' ) );

		$sent = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE campaign_id = %d AND status IN (%s,%s,%s)', $messages_table, $id, 'sent', 'delivered', 'scheduled' ) );

		$failed = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE campaign_id = %d AND status = %s', $messages_table, $id, 'failed' ) );

		$cost = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(cost),0) FROM %i WHERE campaign_id = %d', $messages_table, $id ) );

		$campaign = self::campaign( $id );

		if ( ! $campaign ) {
			return;
		}

		if ( 'scheduled' === $campaign->status && $pending > 0 ) {
			$status = 'scheduled';
		} elseif ( $pending > 0 ) {
			$status = 'sending';
		} elseif ( $failed === $total && $total > 0 ) {
			$status = 'failed';
		} elseif ( $failed > 0 ) {
			$status = 'partial';
		} else {
			$status = 'sent';
		}

		$data = array(
			'status'       => $status,
			'sent_count'   => $sent,
			'failed_count' => $failed,
			'cost'         => $cost,
			'total'        => $total,
		);

		$formats = array( '%s', '%d', '%d', '%f', '%d' );

		if ( in_array( $status, array( 'sent', 'partial', 'failed' ), true ) && empty( $campaign->sent_at ) ) {
			$data['sent_at'] = current_time( 'mysql' );
			$formats[]       = '%s';
		}

		$wpdb->update( $campaigns, $data, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * Any message waiting in the queue?
	 *
	 * @return bool
	 */
	public static function has_pending() {
		global $wpdb;

		$messages_table = self::messages_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s LIMIT 1', $messages_table, 'pending' ) ) > 0;
	}

	/**
	 * Schedule the background worker.
	 *
	 * @param int $delay Seconds.
	 * @return void
	 */
	public static function schedule_worker( $delay = 120 ) {
		if ( wp_next_scheduled( self::WORKER_HOOK ) ) {
			return;
		}

		wp_schedule_single_event( time() + max( 30, (int) $delay ), self::WORKER_HOOK );
	}

	/**
	 * Promote local scheduled campaigns that are due.
	 *
	 * @return void
	 */
	private static function promote_due() {
		global $wpdb;

		$campaigns      = self::campaigns_table();
		$messages_table = self::messages_table();
		$now            = current_time( 'mysql' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE status = %s AND mode = %s AND scheduled_at IS NOT NULL AND scheduled_at <= %s',
				$campaigns,
				'scheduled',
				'template',
				$now
			)
		);

		foreach ( (array) $rows as $row ) {
			$id = (int) $row->id;

			$wpdb->update( $campaigns, array( 'status' => 'queued' ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
			$wpdb->update(
				$messages_table,
				array( 'status' => 'pending' ),
				array(
					'campaign_id' => $id,
					'status'      => 'scheduled',
				),
				array( '%s' ),
				array( '%d', '%s' )
			);
		}
	}

	/**
	 * Cron worker: promote due campaigns and send the next chunks.
	 *
	 * @return void
	 */
	public static function run_worker() {
		if ( get_transient( 'mcc_sms_worker_lock' ) ) {
			return;
		}

		set_transient( 'mcc_sms_worker_lock', 1, 120 );

		self::promote_due();

		global $wpdb;

		$messages_table = self::messages_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below.
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT campaign_id FROM %i WHERE status = %s ORDER BY campaign_id ASC LIMIT 5', $messages_table, 'pending' ) );

		foreach ( (array) $ids as $id ) {
			self::process_campaign( (int) $id, self::WORKER_CHUNK );
		}

		delete_transient( 'mcc_sms_worker_lock' );

		if ( self::has_pending() ) {
			self::schedule_worker( 120 );
		}
	}

	/**
	 * Campaigns list.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function campaigns( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'per_page' => 20,
				'page'     => 1,
			)
		);

		$table  = self::campaigns_table();
		$per    = max( 1, (int) $args['per_page'] );
		$offset = ( max( 1, (int) $args['page'] ) - 1 ) * $per;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below.
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', $table, $per, $offset ) );
	}

	/**
	 * Number of campaigns.
	 *
	 * @return int
	 */
	public static function campaigns_count() {
		global $wpdb;

		$table = self::campaigns_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/**
	 * One campaign.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function campaign( $id ) {
		global $wpdb;

		$table = self::campaigns_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below.
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, (int) $id ) );
	}

	/**
	 * Cancel a scheduled campaign (server side for line mode, local otherwise).
	 *
	 * @param int $id Campaign id.
	 * @return true|WP_Error
	 */
	public static function cancel( $id ) {
		global $wpdb;

		$campaign = self::campaign( $id );

		if ( ! $campaign ) {
			return new WP_Error( 'mcc_not_found', 'کمپین پیدا نشد.' );
		}

		if ( 'scheduled' !== $campaign->status ) {
			return new WP_Error( 'mcc_not_scheduled', 'این کمپین زمان‌بندی‌شده نیست.' );
		}

		if ( 'line' === $campaign->mode ) {
			$packs = array_filter( array_map( 'trim', explode( ',', (string) $campaign->pack_ids ) ) );

			if ( empty( $packs ) ) {
				return new WP_Error( 'mcc_no_pack', 'شناسهٔ ارسال برای لغو پیدا نشد.' );
			}

			foreach ( $packs as $pack ) {
				$result = MCC_SMS_API::cancel_scheduled( $pack );

				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}

		$wpdb->update(
			self::campaigns_table(),
			array(
				'status' => 'canceled',
				'error'  => 'توسط مدیر لغو شد.',
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$messages_table = self::messages_table();

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET status = %s WHERE campaign_id = %d AND status IN (%s, %s)', $messages_table, 'canceled', (int) $id, 'pending', 'scheduled' ) );

		return true;
	}

	/**
	 * Delete a campaign with its messages.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$wpdb->delete( self::messages_table(), array( 'campaign_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( self::campaigns_table(), array( 'id' => (int) $id ), array( '%d' ) );

		return true;
	}

	/**
	 * Refresh delivery states from the API.
	 *
	 * @param int $id Campaign id.
	 * @return int|WP_Error Updated rows.
	 */
	public static function refresh( $id ) {
		global $wpdb;

		$campaign = self::campaign( $id );

		if ( ! $campaign ) {
			return new WP_Error( 'mcc_not_found', 'کمپین پیدا نشد.' );
		}

		$messages_table = self::messages_table();
		$updated        = 0;
		$packs          = array_filter( array_map( 'trim', explode( ',', (string) $campaign->pack_ids ) ) );

		if ( ! empty( $packs ) ) {
			foreach ( $packs as $pack ) {
				$report = MCC_SMS_API::pack_report( $pack );

				if ( is_wp_error( $report ) ) {
					return $report;
				}

				foreach ( (array) $report['data'] as $row ) {
					$row = (array) $row;

					$message_id = isset( $row['messageId'] ) ? (int) $row['messageId'] : 0;
					$state      = isset( $row['deliveryState'] ) ? (int) $row['deliveryState'] : 0;

					if ( ! $message_id ) {
						continue;
					}

					$status = 1 === $state ? 'delivered' : ( 2 === $state ? 'failed' : 'sent' );

					$wpdb->query(
						$wpdb->prepare(
							'UPDATE %i SET status = %s WHERE campaign_id = %d AND message_id = %d',
							$messages_table,
							$status,
							(int) $id,
							$message_id
						)
					);

					$updated++;
				}
			}

			self::recount_campaign( $id );

			return $updated;
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, message_id FROM %i WHERE campaign_id = %d AND message_id > 0 AND status = %s ORDER BY id ASC LIMIT 50',
				$messages_table,
				(int) $id,
				'sent'
			)
		);

		foreach ( (array) $rows as $row ) {
			$report = MCC_SMS_API::message_report( (int) $row->message_id );

			if ( is_wp_error( $report ) ) {
				continue;
			}

			$data  = (array) $report['data'];
			$state = isset( $data['deliveryState'] ) ? (int) $data['deliveryState'] : 0;

			if ( ! in_array( $state, array( 1, 2 ), true ) ) {
				continue;
			}

			$wpdb->update(
				$messages_table,
				array( 'status' => 1 === $state ? 'delivered' : 'failed' ),
				array( 'id' => (int) $row->id ),
				array( '%s' ),
				array( '%d' )
			);

			$updated++;
		}

		if ( $updated ) {
			self::recount_campaign( $id );
		}

		return $updated;
	}

	/**
	 * Messages of a campaign.
	 *
	 * @param int $id Campaign id.
	 * @return array
	 */
	public static function messages( $id ) {
		global $wpdb;

		$table = self::messages_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared below.
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE campaign_id = %d ORDER BY id ASC LIMIT 500', $table, (int) $id ) );
	}

	/**
	 * Run the birthday campaign.
	 *
	 * @param bool $force Ignore the daily guard.
	 * @return int|WP_Error|string
	 */
	public static function birthday_run( $force = false ) {
		$today = MCC_Jalali::today();
		$key   = sprintf( '%04d/%02d/%02d', $today['jy'], $today['jm'], $today['jd'] );

		if ( ! $force && 'yes' !== MCC_Settings::get( 'birthday_enabled' ) ) {
			return 'disabled';
		}

		if ( ! $force && get_option( 'mcc_birthday_last' ) === $key ) {
			return 'already';
		}

		update_option( 'mcc_birthday_last', $key, false );

		$recipients = self::recipients( 'birthday_today' );

		if ( is_wp_error( $recipients ) ) {
			return $recipients;
		}

		if ( empty( $recipients ) ) {
			return 'nobody';
		}

		$args = array(
			'kind'    => 'birthday',
			'title'   => 'تبریک تولد ' . MCC_Jalali::format( $key, true ),
			'group'   => 'birthday_today',
			'message' => (string) MCC_Settings::get( 'birthday_template' ),
		);

		if ( MCC_Settings::is_template_mode() ) {
			$args['template_id'] = (int) MCC_Settings::get( 'birthday_template_id' );
			$args['params']      = (array) MCC_Settings::get( 'birthday_params' );
		}

		$result = self::send( $args );

		if ( is_wp_error( $result ) ) {
			delete_option( 'mcc_birthday_last' );

			return $result;
		}

		return $result;
	}

	/**
	 * Timestamp of the next birthday run.
	 *
	 * @return int
	 */
	public static function next_birthday_run() {
		$hour = max( 0, min( 23, (int) MCC_Settings::get( 'birthday_hour', 10 ) ) );
		$now  = new DateTimeImmutable( 'now', wp_timezone() );
		$next = $now->setTime( $hour, 0, 0 );

		if ( $next->getTimestamp() <= $now->getTimestamp() ) {
			$next = $next->modify( '+1 day' );
		}

		return $next->getTimestamp();
	}

	/**
	 * Keep the cron event in sync with the settings.
	 *
	 * @return void
	 */
	public static function sync_schedule() {
		$next    = wp_next_scheduled( 'mcc_birthday_cron' );
		$enabled = 'yes' === MCC_Settings::get( 'birthday_enabled' );

		if ( ! $enabled ) {
			if ( $next ) {
				wp_unschedule_event( $next, 'mcc_birthday_cron' );
			}

			return;
		}

		$desired = self::next_birthday_run();

		if ( ! $next ) {
			wp_schedule_single_event( $desired, 'mcc_birthday_cron' );

			return;
		}

		if ( abs( $next - $desired ) > 300 ) {
			wp_unschedule_event( $next, 'mcc_birthday_cron' );
			wp_schedule_single_event( $desired, 'mcc_birthday_cron' );
		}
	}

	/**
	 * Cron handler.
	 *
	 * @return void
	 */
	public static function handle_cron() {
		self::birthday_run();
		self::sync_schedule();
	}
}
