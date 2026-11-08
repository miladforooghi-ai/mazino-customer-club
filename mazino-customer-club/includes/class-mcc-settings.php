<?php
/**
 * SMS settings.
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_Settings' ) ) {
	return;
}

/**
 * Reads and writes the plugin options.
 */
class MCC_Settings {

	/**
	 * Option holding the SMS configuration.
	 *
	 * @var string
	 */
	const OPTION = 'mcc_sms_settings';

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'api_key'           => '',
			'line_number'       => '',
			'business_name'     => '',
			'mode'              => 'template',
			'birthday_enabled'  => 'no',
			'birthday_hour'     => 10,
			'birthday_template' => 'سلام {name} عزیز، زادروزتان مبارک! 🎂
به همین بهانه از طرف {club} برایتان تخفیف ویژه در نظر گرفته‌ایم.',
			'birthday_template_id' => 0,
			'birthday_params'   => array(),
			'default_title'     => '{club}',
		);
	}

	/**
	 * All settings.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist a subset of settings.
	 *
	 * @param array $data Values.
	 * @return void
	 */
	public static function update( array $data ) {
		$all = self::all();

		foreach ( $data as $key => $value ) {
			if ( array_key_exists( $key, $all ) ) {
				$all[ $key ] = $value;
			}
		}

		update_option( self::OPTION, $all );

		delete_transient( 'mcc_sms_credit' );
	}

	/**
	 * API key.
	 *
	 * @return string
	 */
	public static function api_key() {
		return trim( (string) self::get( 'api_key' ) );
	}

	/**
	 * Sender line.
	 *
	 * @return string
	 */
	public static function line() {
		return preg_replace( '/\D+/', '', (string) self::get( 'line_number' ) );
	}

	/**
	 * Whether a dedicated line is configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		if ( '' === self::api_key() ) {
			return false;
		}

		if ( self::is_template_mode() ) {
			return true;
		}

		return '' !== self::line();
	}

	/**
	 * Name of the café / restaurant, used in messages.
	 *
	 * @return string
	 */
	public static function business_name() {
		$name = trim( (string) self::get( 'business_name' ) );

		if ( '' !== $name ) {
			return $name;
		}

		return (string) get_bloginfo( 'name' );
	}

	/**
	 * Sending mode: template (verify) or line (bulk).
	 *
	 * @return string
	 */
	public static function mode() {
		$mode = (string) self::get( 'mode', 'template' );

		return 'line' === $mode ? 'line' : 'template';
	}

	/**
	 * Whether template mode is active.
	 *
	 * @return bool
	 */
	public static function is_template_mode() {
		return 'template' === self::mode();
	}
}
