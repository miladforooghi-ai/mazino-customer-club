<?php
/**
 * Jalali (Shamsi) calendar helpers.
 *
 * Port of the well-known jalaali-js algorithm (Borkowski's break years).
 *
 * @package Mazino_Customer_Club
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'MCC_Jalali' ) ) {
	return;
}

/**
 * Gregorian <-> Jalali conversion and validation.
 */
class MCC_Jalali {

	/**
	 * Break years of the 33-year leap cycle.
	 *
	 * @var int[]
	 */
	private static $breaks = array( -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178 );

	/**
	 * Persian month names.
	 *
	 * @return string[]
	 */
	public static function months() {
		return array(
			1  => 'فروردین',
			2  => 'اردیبهشت',
			3  => 'خرداد',
			4  => 'تیر',
			5  => 'مرداد',
			6  => 'شهریور',
			7  => 'مهر',
			8  => 'آبان',
			9  => 'آذر',
			10 => 'دی',
			11 => 'بهمن',
			12 => 'اسفند',
		);
	}

	/**
	 * Truncating division, matching JavaScript's ~~(a / b).
	 *
	 * @param int $a Dividend.
	 * @param int $b Divisor.
	 * @return int
	 */
	private static function div( $a, $b ) {
		return (int) intdiv( (int) $a, (int) $b );
	}

	/**
	 * Truncating modulo, matching JavaScript's a - ~~(a / b) * b.
	 *
	 * @param int $a Dividend.
	 * @param int $b Divisor.
	 * @return int
	 */
	private static function mod( $a, $b ) {
		return (int) $a - self::div( $a, $b ) * (int) $b;
	}

	/**
	 * Compute the leap state and March day of a Jalali year.
	 *
	 * @param int $jy Jalali year.
	 * @return array{leap:int,gy:int,march:int}
	 * @throws InvalidArgumentException When the year is out of range.
	 */
	private static function jal_cal( $jy ) {
		$jy = (int) $jy;
		$bl = count( self::$breaks );

		if ( $jy < self::$breaks[0] || $jy >= self::$breaks[ $bl - 1 ] ) {
			throw new InvalidArgumentException( 'Invalid Jalali year.' );
		}

		$gy     = $jy + 621;
		$leap_j = -14;
		$jp     = self::$breaks[0];
		$jump   = 0;

		for ( $i = 1; $i < $bl; $i++ ) {
			$jm   = self::$breaks[ $i ];
			$jump = $jm - $jp;

			if ( $jy < $jm ) {
				break;
			}

			$leap_j += self::div( $jump, 33 ) * 8 + self::div( self::mod( $jump, 33 ), 4 );
			$jp      = $jm;
		}

		$n      = $jy - $jp;
		$leap_j += self::div( $n, 33 ) * 8 + self::div( self::mod( $n, 33 ) + 3, 4 );

		if ( 4 === self::mod( $jump, 33 ) && 4 === $jump - $n ) {
			$leap_j++;
		}

		$leap_g = self::div( $gy, 4 ) - self::div( ( self::div( $gy, 100 ) + 1 ) * 3, 4 ) - 150;
		$march  = 20 + $leap_j - $leap_g;

		if ( $jump - $n < 6 ) {
			$n = $n - $jump + self::div( $jump + 4, 33 ) * 33;
		}

		$leap = self::mod( self::mod( $n + 1, 33 ) - 1, 4 );

		if ( -1 === $leap ) {
			$leap = 4;
		}

		return array(
			'leap'  => $leap,
			'gy'    => $gy,
			'march' => $march,
		);
	}

	/**
	 * Gregorian date to Julian day number.
	 *
	 * @param int $gy Year.
	 * @param int $gm Month.
	 * @param int $gd Day.
	 * @return int
	 */
	private static function g2d( $gy, $gm, $gd ) {
		$d = self::div( ( $gy + self::div( $gm - 8, 6 ) + 100100 ) * 1461, 4 )
			+ self::div( 153 * self::mod( $gm + 9, 12 ) + 2, 5 )
			+ $gd - 34840408;

		return $d - self::div( self::div( $gy + 100100 + self::div( $gm - 8, 6 ), 100 ) * 3, 4 ) + 752;
	}

	/**
	 * Julian day number to Gregorian date.
	 *
	 * @param int $jdn Julian day number.
	 * @return array{gy:int,gm:int,gd:int}
	 */
	private static function d2g( $jdn ) {
		$j = 4 * $jdn + 139361631;
		$j = $j + self::div( self::div( 4 * $jdn + 183187720, 146097 ) * 3, 4 ) * 4 - 3908;

		$i  = self::div( self::mod( $j, 1461 ), 4 ) * 5 + 308;
		$gd = self::div( self::mod( $i, 153 ), 5 ) + 1;
		$gm = self::mod( self::div( $i, 153 ), 12 ) + 1;
		$gy = self::div( $j, 1461 ) - 100100 + self::div( 8 - $gm, 6 );

		return array(
			'gy' => $gy,
			'gm' => $gm,
			'gd' => $gd,
		);
	}

	/**
	 * Julian day number of a Jalali date.
	 *
	 * @param int $jy Year.
	 * @param int $jm Month.
	 * @param int $jd Day.
	 * @return int
	 */
	private static function j2d( $jy, $jm, $jd ) {
		$r = self::jal_cal( $jy );

		return self::g2d( $r['gy'], 3, $r['march'] ) + ( $jm - 1 ) * 31 - self::div( $jm, 7 ) * ( $jm - 7 ) + $jd - 1;
	}

	/**
	 * Julian day number to Jalali date.
	 *
	 * @param int $jdn Julian day number.
	 * @return array{jy:int,jm:int,jd:int}
	 */
	private static function d2j( $jdn ) {
		$gy = self::d2g( $jdn )['gy'];
		$jy = $gy - 621;
		$r  = self::jal_cal( $jy );

		$jdn1f = self::g2d( $gy, 3, $r['march'] );
		$k     = $jdn - $jdn1f;

		if ( $k >= 0 ) {
			if ( $k <= 185 ) {
				return array(
					'jy' => $jy,
					'jm' => 1 + self::div( $k, 31 ),
					'jd' => self::mod( $k, 31 ) + 1,
				);
			}

			$k -= 186;
		} else {
			$jy--;
			$k += 179;

			if ( 1 === $r['leap'] ) {
				$k++;
			}
		}

		return array(
			'jy' => $jy,
			'jm' => 7 + self::div( $k, 30 ),
			'jd' => self::mod( $k, 30 ) + 1,
		);
	}

	/**
	 * Gregorian to Jalali.
	 *
	 * @param int $gy Year.
	 * @param int $gm Month.
	 * @param int $gd Day.
	 * @return array{jy:int,jm:int,jd:int}
	 */
	public static function to_jalali( $gy, $gm, $gd ) {
		return self::d2j( self::g2d( (int) $gy, (int) $gm, (int) $gd ) );
	}

	/**
	 * Jalali to Gregorian.
	 *
	 * @param int $jy Year.
	 * @param int $jm Month.
	 * @param int $jd Day.
	 * @return array{gy:int,gm:int,gd:int}
	 */
	public static function to_gregorian( $jy, $jm, $jd ) {
		return self::d2g( self::j2d( (int) $jy, (int) $jm, (int) $jd ) );
	}

	/**
	 * Whether a Jalali year is a leap year.
	 *
	 * @param int $jy Year.
	 * @return bool
	 */
	public static function is_leap( $jy ) {
		try {
			return 0 === self::jal_cal( (int) $jy )['leap'];
		} catch ( InvalidArgumentException $e ) {
			return false;
		}
	}

	/**
	 * Number of days of a Jalali month.
	 *
	 * @param int $jy Year.
	 * @param int $jm Month.
	 * @return int
	 */
	public static function month_length( $jy, $jm ) {
		$jm = (int) $jm;

		if ( $jm <= 6 ) {
			return 31;
		}

		if ( $jm <= 11 ) {
			return 30;
		}

		return self::is_leap( $jy ) ? 30 : 29;
	}

	/**
	 * Today as a Jalali date, in the site timezone.
	 *
	 * @return array{jy:int,jm:int,jd:int}
	 */
	public static function today() {
		$timestamp = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
		$gy        = (int) gmdate( 'Y', $timestamp );
		$gm        = (int) gmdate( 'n', $timestamp );
		$gd        = (int) gmdate( 'j', $timestamp );

		return self::to_jalali( $gy, $gm, $gd );
	}

	/**
	 * Current Jalali year in the site timezone.
	 *
	 * @return int
	 */
	public static function current_year() {
		return (int) self::today()['jy'];
	}

	/**
	 * Validate a Jalali date.
	 *
	 * @param int $jy Year.
	 * @param int $jm Month.
	 * @param int $jd Day.
	 * @return bool
	 */
	public static function is_valid_date( $jy, $jm, $jd ) {
		$jy = (int) $jy;
		$jm = (int) $jm;
		$jd = (int) $jd;

		if ( $jy < 1200 || $jy > 1700 || $jm < 1 || $jm > 12 || $jd < 1 ) {
			return false;
		}

		return $jd <= self::month_length( $jy, $jm );
	}

	/**
	 * Age in years from a Jalali birth date.
	 *
	 * @param int $jy Year.
	 * @param int $jm Month.
	 * @param int $jd Day.
	 * @return int
	 */
	public static function age( $jy, $jm, $jd ) {
		$today = self::today();
		$age   = $today['jy'] - (int) $jy;

		if ( $today['jm'] < (int) $jm || ( $today['jm'] === (int) $jm && $today['jd'] < (int) $jd ) ) {
			$age--;
		}

		return $age > 0 ? $age : 0;
	}

	/**
	 * Format a "YYYY/MM/DD" Jalali string for display.
	 *
	 * @param string $date Stored date.
	 * @param bool   $long Whether to use the month name.
	 * @return string
	 */
	public static function format( $date, $long = true ) {
		if ( ! preg_match( '#^(\d{4})/(\d{1,2})/(\d{1,2})$#', (string) $date, $m ) ) {
			return '';
		}

		$jy = (int) $m[1];
		$jm = (int) $m[2];
		$jd = (int) $m[3];
		$months = self::months();

		if ( $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31 ) {
			return '';
		}

		if ( $long ) {
			return sprintf( '%d %s %d', $jd, $months[ $jm ], $jy );
		}

		return sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
	}
}
