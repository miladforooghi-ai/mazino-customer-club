<?php
/**
 * sms.ir REST client.
 *
 * Endpoints follow https://sms.ir/rest-api (v1).
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_SMS_API' ) ) {
	return;
}

/**
 * Thin wrapper around the sms.ir API.
 */
class MCC_SMS_API {

	/**
	 * Base URL.
	 *
	 * @var string
	 */
	const BASE = 'https://api.sms.ir/v1/';

	/**
	 * Max recipients accepted by one request.
	 *
	 * @var int
	 */
	const CHUNK = 100;

	/**
	 * Human readable message for an API error code.
	 *
	 * @param int    $code    API status code.
	 * @param string $message Raw message.
	 * @return string
	 */
	private static function error_text( $code, $message ) {
		$map = array(
			11  => 'کلید وب‌سرویس غیرفعال است.',
			12  => 'این کلید فقط برای IPهای تعریف‌شده مجاز است.',
			13  => 'حساب کاربری غیرفعال است.',
			14  => 'حساب کاربری در حالت تعلیق است.',
			20  => 'تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.',
			101 => 'شمارهٔ خط نامعتبر است. آن را در تنظیمات پیامک بررسی کنید.',
			102 => 'اعتبار حساب پیامکی کافی نیست.',
			103 => 'متن پیامک خالی است.',
			104 => 'شماره موبایل نامعتبر است.',
			105 => 'تعداد شماره‌ها بیشتر از حد مجاز (۱۰۰) است.',
			106 => 'تعداد متن‌ها بیشتر از حد مجاز (۱۰۰) است.',
			107 => 'فهرست شماره‌ها خالی است.',
			108 => 'فهرست متن‌ها خالی است.',
			400 => 'درخواست نامعتبر است.',
			401 => 'کلید API نامعتبر است.',
		);

		if ( isset( $map[ (int) $code ] ) ) {
			return $map[ (int) $code ];
		}

		return '' !== (string) $message ? (string) $message : 'خطای نامشخص در ارتباط با سامانهٔ پیامک.';
	}

	/**
	 * Perform a request.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path   Path after /v1/.
	 * @param array|null $body   JSON body.
	 * @return array|WP_Error
	 */
	public static function request( $method, $path, $body = null ) {
		$key = MCC_Settings::api_key();

		if ( '' === $key ) {
			return new WP_Error( 'mcc_no_key', 'کلید API پیامک تنظیم نشده است. به «تنظیمات پیامک» بروید.' );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 25,
			'headers' => array(
				'X-API-KEY'    => $key,
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::BASE . ltrim( $path, '/' ), $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'mcc_http', 'ارتباط با سامانهٔ پیامک برقرار نشد: ' . $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'mcc_bad_response', 'پاسخ نامعتبر از سامانهٔ پیامک (کد ' . $status . ').' );
		}

		$api_status = isset( $data['status'] ) ? (int) $data['status'] : 0;

		if ( 1 !== $api_status ) {
			$code = 0;

			if ( isset( $data['status'] ) && 0 !== (int) $data['status'] ) {
				$code = (int) $data['status'];
			}

			$message = isset( $data['message'] ) ? (string) $data['message'] : '';

			return new WP_Error( 'mcc_api_' . $code, self::error_text( $code, $message ) );
		}

		return array(
			'status' => $api_status,
			'data'   => isset( $data['data'] ) ? $data['data'] : null,
			'raw'    => $data,
		);
	}

	/**
	 * List of approved templates.
	 *
	 * @param string|array $mobile    Mobile number.
	 * @param int          $template_id Template id.
	 * @param array        $params    List of ['name'=>..,'value'=>..].
	 * @return array|WP_Error
	 */
	public static function send_verify( $mobile, $template_id, array $params ) {
		$body = array(
			'mobile'     => (string) $mobile,
			'templateId' => (int) $template_id,
			'parameters' => array_values( $params ),
		);

		return self::request( 'POST', 'send/verify', $body );
	}

	/**
	 * Approved templates of the account.
	 *
	 * @param int $page_size Page size (max 100).
	 * @return array|WP_Error
	 */
	public static function templates( $page_size = 100 ) {
		$response = self::request( 'GET', 'templates?pageNumber=1&pageSize=' . (int) $page_size . '&status=2' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$templates = array();

		foreach ( (array) $response['data'] as $item ) {
			$item = (array) $item;

			$params = array();

			foreach ( (array) ( $item['parameters'] ?? array() ) as $parameter ) {
				$parameter = (array) $parameter;

				if ( ! empty( $parameter['name'] ) ) {
					$params[] = array(
						'name'        => (string) $parameter['name'],
						'description' => (string) ( $parameter['description'] ?? '' ),
					);
				}
			}

			$text = (string) ( $item['templateText'] ?? '' );

			if ( empty( $params ) && preg_match_all( '/#([A-Za-z0-9_]+)#/', $text, $matches ) ) {
				foreach ( array_unique( $matches[1] ) as $name ) {
					$params[] = array(
						'name'        => $name,
						'description' => '',
					);
				}
			}

			$templates[] = array(
				'id'     => (int) ( $item['id'] ?? 0 ),
				'title'  => (string) ( $item['title'] ?? '' ),
				'text'   => $text,
				'params' => $params,
				'type'   => (int) ( $item['type'] ?? 0 ),
			);
		}

		return $templates;
	}

	/**
	 * One template.
	 *
	 * @param int $template_id Template id.
	 * @return array|WP_Error
	 */
	public static function template( $template_id ) {
		$response = self::request( 'GET', 'templates/' . (int) $template_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$item = (array) $response['data'];
		$text = (string) ( $item['templateText'] ?? '' );

		$params = array();

		foreach ( (array) ( $item['parameters'] ?? array() ) as $parameter ) {
			$parameter = (array) $parameter;

			if ( ! empty( $parameter['name'] ) ) {
				$params[] = array(
					'name'        => (string) $parameter['name'],
					'description' => (string) ( $parameter['description'] ?? '' ),
				);
			}
		}

		if ( empty( $params ) && preg_match_all( '/#([A-Za-z0-9_]+)#/', $text, $matches ) ) {
			foreach ( array_unique( $matches[1] ) as $name ) {
				$params[] = array(
					'name'        => $name,
					'description' => '',
				);
			}
		}

		return array(
			'id'     => (int) ( $item['id'] ?? 0 ),
			'title'  => (string) ( $item['title'] ?? '' ),
			'text'   => $text,
			'params' => $params,
			'status' => (int) ( $item['status'] ?? 0 ),
		);
	}

	/**
	 * Current credit.
	 *
	 * @return float|WP_Error
	 */
	public static function credit() {
		$response = self::request( 'GET', 'credit' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return (float) $response['data'];
	}

	/**
	 * Available lines.
	 *
	 * @return array|WP_Error
	 */
	public static function lines() {
		$response = self::request( 'GET', 'line' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return array_map( 'strval', (array) $response['data'] );
	}

	/**
	 * Send one message to one recipient.
	 *
	 * @param string   $mobile     Mobile number.
	 * @param string   $text       Message.
	 * @param int|null $send_at    Unix timestamp for scheduled sends.
	 * @return array|WP_Error
	 */
	public static function send_single( $mobile, $text, $send_at = null ) {
		return self::send_bulk( array( $mobile ), $text, $send_at );
	}

	/**
	 * Send the same text to a group.
	 *
	 * @param string[] $mobiles Recipients (max 100).
	 * @param string   $text    Message.
	 * @param int|null $send_at Unix timestamp.
	 * @return array|WP_Error
	 */
	public static function send_bulk( array $mobiles, $text, $send_at = null ) {
		$mobiles = array_values( array_filter( array_map( 'strval', $mobiles ) ) );

		if ( empty( $mobiles ) ) {
			return new WP_Error( 'mcc_empty', 'هیچ شماره‌ای برای ارسال وجود ندارد.' );
		}

		$body = array(
			'lineNumber'  => (int) MCC_Settings::line(),
			'messageText' => (string) $text,
			'mobiles'     => $mobiles,
		);

		if ( $send_at ) {
			$body['sendDateTime'] = (int) $send_at;
		}

		return self::request( 'POST', 'send/bulk', $body );
	}

	/**
	 * Send a different text to each recipient (max 100).
	 *
	 * @param string[] $mobiles Recipients.
	 * @param string[] $texts   Messages, same order as mobiles.
	 * @param int|null $send_at Unix timestamp.
	 * @return array|WP_Error
	 */
	public static function send_like_to_like( array $mobiles, array $texts, $send_at = null ) {
		$mobiles = array_values( array_map( 'strval', $mobiles ) );
		$texts   = array_values( array_map( 'strval', $texts ) );

		if ( count( $mobiles ) !== count( $texts ) ) {
			return new WP_Error( 'mcc_mismatch', 'تعداد شماره‌ها و متن‌ها یکسان نیست.' );
		}

		if ( empty( $mobiles ) ) {
			return new WP_Error( 'mcc_empty', 'هیچ شماره‌ای برای ارسال وجود ندارد.' );
		}

		$body = array(
			'lineNumber'   => (int) MCC_Settings::line(),
			'messageTexts' => $texts,
			'mobiles'      => $mobiles,
		);

		if ( $send_at ) {
			$body['sendDateTime'] = (int) $send_at;
		}

		return self::request( 'POST', 'send/likeToLike', $body );
	}

	/**
	 * Cancel a scheduled pack.
	 *
	 * @param string $pack_id Pack id.
	 * @return array|WP_Error
	 */
	public static function cancel_scheduled( $pack_id ) {
		return self::request( 'DELETE', 'send/scheduled/' . rawurlencode( (string) $pack_id ) );
	}

	/**
	 * Delivery report of a pack.
	 *
	 * @param string $pack_id Pack id.
	 * @return array|WP_Error
	 */
	public static function pack_report( $pack_id ) {
		return self::request( 'GET', 'send/pack/' . rawurlencode( (string) $pack_id ) );
	}

	/**
	 * Delivery report of a single message.
	 *
	 * @param int $message_id Message id.
	 * @return array|WP_Error
	 */
	public static function message_report( $message_id ) {
		return self::request( 'GET', 'send/' . (int) $message_id );
	}
}
