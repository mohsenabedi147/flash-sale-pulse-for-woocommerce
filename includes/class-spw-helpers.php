<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * توابع کمکی افزونه نبض حراجی.
 */
class SPW_Helpers {

	public static function get_settings() {
		$defaults = array(
			'timer_title'       => 'زمان باقی‌مانده تا پایان حراجی',
			'button_text'       => 'مشاهده محصول',
			'products_per_page' => 8,
			'default_orderby'   => 'ending_soon',
			'hide_oos'          => 'yes',
			'cleanup_batch'     => 25,
			'db_version'        => SPW_DB_VERSION,
		);

		$settings = get_option( 'spw_settings', array() );

		return wp_parse_args( $settings, $defaults );
	}

	public static function get_product_sale_window( $product_id ) {
		return array(
			'start' => (int) get_post_meta( $product_id, '_spw_sale_start', true ),
			'end'   => (int) get_post_meta( $product_id, '_spw_sale_end', true ),
		);
	}

	public static function is_valid_window( $start, $end ) {
		return $start > 0 && $end > 0 && $end > $start;
	}

	public static function format_seconds( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		$days    = floor( $seconds / DAY_IN_SECONDS );
		$hours   = floor( ( $seconds % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
		$minutes = floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		$secs    = $seconds % MINUTE_IN_SECONDS;

		return array(
			'days'    => $days,
			'hours'   => $hours,
			'minutes' => $minutes,
			'seconds' => $secs,
		);
	}

	public static function timestamp_to_jalali_datetime( $timestamp ) {
		$timestamp = (int) $timestamp;

		if ( $timestamp <= 0 ) {
			return '';
		}

		$year  = (int) wp_date( 'Y', $timestamp );
		$month = (int) wp_date( 'm', $timestamp );
		$day   = (int) wp_date( 'd', $timestamp );
		$hour  = wp_date( 'H', $timestamp );
		$min   = wp_date( 'i', $timestamp );

		$jalali = self::gregorian_to_jalali( $year, $month, $day );

		return sprintf(
			'%04d/%02d/%02d %s:%s',
			$jalali[0],
			$jalali[1],
			$jalali[2],
			$hour,
			$min
		);
	}

	public static function jalali_datetime_to_timestamp( $datetime ) {
		$datetime = trim( self::normalize_digits( (string) $datetime ) );

		if ( '' === $datetime ) {
			return 0;
		}

		if ( ! preg_match( '/^(\d{4})\/(\d{1,2})\/(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?$/', $datetime, $matches ) ) {
			return 0;
		}

		$jy     = absint( $matches[1] );
		$jm     = absint( $matches[2] );
		$jd     = absint( $matches[3] );
		$hour   = isset( $matches[4] ) ? absint( $matches[4] ) : 0;
		$minute = isset( $matches[5] ) ? absint( $matches[5] ) : 0;

		if ( $jm < 1 || $jm > 12 || $jd < 1 || $jd > 31 || $hour > 23 || $minute > 59 ) {
			return 0;
		}

		$gregorian = self::jalali_to_gregorian( $jy, $jm, $jd );

		return (int) get_date_from_gmt(
			gmdate( 'Y-m-d H:i:s', gmmktime( $hour, $minute, 0, $gregorian[1], $gregorian[2], $gregorian[0] ) ),
			'U'
		);
	}

	public static function normalize_digits( $value ) {
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$latin   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( $persian, $latin, $value );
	}

	public static function gregorian_to_jalali( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + floor( ( $gy2 + 3 ) / 4 ) - floor( ( $gy2 + 99 ) / 100 ) + floor( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * floor( $days / 12053 ) );
		$days %= 12053;
		$jy   += 4 * floor( $days / 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy   += floor( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + floor( $days / 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + floor( ( $days - 186 ) / 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array( $jy, $jm, $jd );
	}

	public static function jalali_to_gregorian( $jy, $jm, $jd ) {
		$jy += 1595;
		$days = -495164 + ( 365 * $jy ) + ( floor( $jy / 33 ) * 8 ) + floor( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd;
		if ( $jm < 7 ) {
			$days += ( $jm - 1 ) * 31;
		} else {
			$days += ( ( $jm - 7 ) * 30 ) + 186;
		}
		$gy = 400 * floor( $days / 146097 );
		$days %= 146097;
		if ( $days > 36524 ) {
			$gy += 100 * floor( --$days / 36524 );
			$days %= 36524;
			if ( $days >= 365 ) {
				$days++;
			}
		}
		$gy   += 4 * floor( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$gy += floor( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		$gd
