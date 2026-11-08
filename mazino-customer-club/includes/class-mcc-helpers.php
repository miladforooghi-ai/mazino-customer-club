<?php
/**
 * Small shared helpers.
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_Helpers' ) ) {
	return;
}

/**
 * Formatting and sanitising helpers.
 */
class MCC_Helpers {

	/**
	 * Convert Persian/Arabic digits to Latin digits.
	 *
	 * @param string $value Input string.
	 * @return string
	 */
	public static function to_english_digits( $value ) {
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$arabic  = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$latin   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( array_merge( $persian, $arabic ), array_merge( $latin, $latin ), (string) $value );
	}

	/**
	 * Convert Latin digits to Persian digits (for display only).
	 *
	 * @param string $value Input string.
	 * @return string
	 */
	public static function to_persian_digits( $value ) {
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

		return str_replace( range( 0, 9 ), $persian, (string) $value );
	}

	/**
	 * Strip any HTML and normalise whitespace.
	 *
	 * @param string $value Input.
	 * @return string
	 */
	public static function plain_text( $value ) {
		$value = wp_strip_all_tags( (string) $value );

		return trim( preg_replace( '/\s+/u', ' ', $value ) );
	}

	/**
	 * Normalise an Iranian mobile number to the 09xxxxxxxxx form.
	 *
	 * Accepts Persian digits, +98, 0098, 98 and numbers without the leading zero.
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	public static function normalize_phone( $raw ) {
		$digits = preg_replace( '/\D+/', '', self::to_english_digits( (string) $raw ) );

		if ( '' === $digits ) {
			return '';
		}

		if ( 0 === strpos( $digits, '0098' ) ) {
			$digits = '0' . substr( $digits, 4 );
		} elseif ( 12 === strlen( $digits ) && 0 === strpos( $digits, '98' ) ) {
			$digits = '0' . substr( $digits, 2 );
		} elseif ( 10 === strlen( $digits ) && '9' === $digits[0] ) {
			$digits = '0' . $digits;
		}

		return $digits;
	}

	/**
	 * Whether a normalised number is a valid Iranian mobile number.
	 *
	 * @param string $phone Normalised phone.
	 * @return bool
	 */
	public static function is_valid_mobile( $phone ) {
		return (bool) preg_match( '/^09\d{9}$/', (string) $phone );
	}

	/**
	 * Pretty print a normalised phone number.
	 *
	 * @param string $phone Normalised phone.
	 * @return string
	 */
	public static function format_phone( $phone ) {
		if ( preg_match( '/^0(\d{3})(\d{3})(\d{4})$/', (string) $phone, $m ) ) {
			return $m[1] . ' ' . $m[2] . ' ' . $m[3];
		}

		return (string) $phone;
	}

	/**
	 * Current visitor IP address (best effort, never stored in clear).
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '0.0.0.0';
		}

		return $ip;
	}

	/**
	 * Hashed IP, with the site salts, so the raw address is never stored.
	 *
	 * @return string
	 */
	public static function ip_hash() {
		return hash( 'sha256', self::client_ip() . wp_salt( 'auth' ) );
	}

	/**
	 * Trimmed user agent, limited to the column length.
	 *
	 * @return string
	 */
	public static function user_agent() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return mb_substr( $ua, 0, 250 );
	}

	/**
	 * Format a MySQL datetime that was stored in site-local time.
	 *
	 * WordPress stores current_time( 'mysql' ) as a wall clock without offset,
	 * so the string is read back as UTC and printed as-is to avoid shifting.
	 *
	 * @param string $mysql  MySQL datetime.
	 * @param string $format PHP date format.
	 * @return string
	 */
	public static function local_date( $mysql, $format = 'Y/m/d' ) {
		$timestamp = strtotime( (string) $mysql . ' UTC' );

		if ( ! $timestamp ) {
			return '';
		}

		return gmdate( $format, $timestamp );
	}

	/**
	 * Source URL of the submission, limited to the column length.
	 *
	 * @param string $raw Raw value.
	 * @return string
	 */
	public static function source_url( $raw ) {
		$url = esc_url_raw( (string) $raw );

		if ( '' === $url ) {
			return '';
		}

		return mb_substr( $url, 0, 190 );
	}
}
