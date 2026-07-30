<?php
/**
 * Shared helper functions.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Format a monetary amount in Polish style: `1 234,56 zł`.
 * Space thousands separator, comma decimal separator.
 *
 * @param float|string|null $amount     Numeric amount.
 * @param bool              $with_suffix Append ` zł`.
 * @return string
 */
function mmr_money( $amount, $with_suffix = true ) {
	$amount = (float) $amount;
	$formatted = number_format( $amount, 2, ',', "\xc2\xa0" ); // non-breaking space thousands.
	return $with_suffix ? $formatted . "\xc2\xa0zł" : $formatted;
}

/**
 * Sanitize a decimal money value coming from a form.
 * Accepts both `1234,56` and `1234.56`. Returns a float.
 *
 * @param mixed $value Raw input.
 * @return float
 */
function mmr_sanitize_money( $value ) {
	if ( is_string( $value ) ) {
		$value = str_replace( array( "\xc2\xa0", ' ', 'zł', 'zl' ), '', $value );
		$value = str_replace( ',', '.', $value );
	}
	return round( (float) $value, 2 );
}

/**
 * Validate & normalise a period string `YYYY-MM`.
 *
 * @param string $period Raw period.
 * @return string|null Normalised `YYYY-MM` or null when invalid.
 */
function mmr_sanitize_period( $period ) {
	$period = trim( (string) $period );
	if ( preg_match( '/^(\d{4})-(\d{2})$/', $period, $m ) ) {
		$month = (int) $m[2];
		if ( $month >= 1 && $month <= 12 ) {
			return $period;
		}
	}
	return null;
}

/**
 * Validate a `Y-m-d` date string, calendar included.
 *
 * @param mixed $date Raw date.
 * @return bool
 */
function mmr_valid_date( $date ) {
	if ( ! is_string( $date ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
		return false;
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

/**
 * Human day label from a date, e.g. `2026-07-01` → `1 Jul 2026`.
 * English month abbreviations regardless of site locale, matching
 * mmr_period_label() and mmr_weekday_abbr().
 *
 * @param string $date `Y-m-d`.
 * @return string Empty string when the date is invalid or empty.
 */
function mmr_date_label( $date ) {
	static $names = array( 1 => 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' );
	if ( ! mmr_valid_date( $date ) ) {
		return '';
	}
	list( $year, $month, $day ) = explode( '-', $date );
	return (int) $day . ' ' . $names[ (int) $month ] . ' ' . $year;
}

/**
 * Human month label from a period, e.g. `2026-07` → `July 2026`.
 * Uses English month names regardless of site locale, matching the English
 * weekday abbreviations in mmr_weekday_abbr().
 *
 * @param string $period `YYYY-MM`.
 * @return string
 */
function mmr_period_label( $period ) {
	static $names = array(
		1  => 'January',
		2  => 'February',
		3  => 'March',
		4  => 'April',
		5  => 'May',
		6  => 'June',
		7  => 'July',
		8  => 'August',
		9  => 'September',
		10 => 'October',
		11 => 'November',
		12 => 'December',
	);
	$period = mmr_sanitize_period( $period );
	if ( ! $period ) {
		return '';
	}
	list( $year, $month ) = explode( '-', $period );
	$month = (int) $month;
	return $names[ $month ] . ' ' . $year;
}

/**
 * Short weekday abbreviations, Monday-first index 0..6.
 *
 * @param int $dow 0=Mon .. 6=Sun.
 * @return string
 */
function mmr_weekday_abbr( $dow ) {
	static $days = array( 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN' );
	return isset( $days[ $dow ] ) ? $days[ $dow ] : '';
}

/**
 * Given any date, return the Monday of that week (Y-m-d).
 *
 * @param string $date Y-m-d.
 * @return string Monday Y-m-d.
 */
function mmr_week_monday( $date ) {
	$ts = strtotime( $date );
	if ( false === $ts ) {
		$ts = current_time( 'timestamp' );
	}
	// ISO-8601 day of week: 1 (Mon) .. 7 (Sun).
	$dow = (int) wp_date( 'N', $ts );
	$monday_ts = strtotime( '-' . ( $dow - 1 ) . ' days', $ts );
	return wp_date( 'Y-m-d', $monday_ts );
}

/**
 * Escape helper that renders a money amount inside HTML safely.
 *
 * @param float|string $amount Amount.
 * @return string
 */
function mmr_money_esc( $amount ) {
	return esc_html( mmr_money( $amount ) );
}

/**
 * Build an admin URL for one of the plugin pages.
 *
 * @param string $page  Page slug suffix, e.g. 'calendar'.
 * @param array  $args  Extra query args.
 * @return string
 */
function mmr_page_url( $page, $args = array() ) {
	$args = array_merge( array( 'page' => 'mmr-' . $page ), $args );
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

/**
 * Week money thresholds for the calendar status pill, filterable so they can
 * be tuned without touching code. Below `warn` is danger; `warn`..`good`
 * inclusive is warn; above `good` is good.
 *
 * @return array{warn:float,good:float}
 */
function mmr_week_money_thresholds() {
	$thresholds = apply_filters( 'mm_register_week_money_thresholds', array( 'warn' => 2000, 'good' => 3000 ) );
	return array(
		'warn' => (float) $thresholds['warn'],
		'good' => (float) $thresholds['good'],
	);
}

/**
 * Week lesson-count thresholds, filterable. Same band semantics as money.
 *
 * @return array{warn:int,good:int}
 */
function mmr_week_lesson_thresholds() {
	$thresholds = apply_filters( 'mm_register_week_lesson_thresholds', array( 'warn' => 20, 'good' => 25 ) );
	return array(
		'warn' => (int) $thresholds['warn'],
		'good' => (int) $thresholds['good'],
	);
}

/**
 * Month lesson-count thresholds, filterable. Same band semantics as the week
 * ones, scaled by four — a month is roughly four teaching weeks. Tune with the
 * `mm_register_month_lesson_thresholds` filter rather than editing this.
 *
 * @return array{warn:int,good:int}
 */
function mmr_month_lesson_thresholds() {
	$thresholds = apply_filters( 'mm_register_month_lesson_thresholds', array( 'warn' => 80, 'good' => 100 ) );
	return array(
		'warn' => (int) $thresholds['warn'],
		'good' => (int) $thresholds['good'],
	);
}

/**
 * Status band for a month lesson count.
 *
 * @param int $n Number of booked lessons in the month.
 * @return string 'danger' | 'warn' | 'good'.
 */
function mmr_status_for_month_count( $n ) {
	$t = mmr_month_lesson_thresholds();
	if ( $n < $t['warn'] ) {
		return 'danger';
	}
	if ( $n <= $t['good'] ) {
		return 'warn';
	}
	return 'good';
}

/**
 * Status band for a week money total.
 *
 * @param float $total Week total in zł.
 * @return string 'danger' | 'warn' | 'good'.
 */
function mmr_status_for_amount( $total ) {
	$t = mmr_week_money_thresholds();
	if ( $total < $t['warn'] ) {
		return 'danger';
	}
	if ( $total <= $t['good'] ) {
		return 'warn';
	}
	return 'good';
}

/**
 * Status band for a week lesson count.
 *
 * @param int $n Number of booked lessons in the week.
 * @return string 'danger' | 'warn' | 'good'.
 */
function mmr_status_for_count( $n ) {
	$t = mmr_week_lesson_thresholds();
	if ( $n < $t['warn'] ) {
		return 'danger';
	}
	if ( $n <= $t['good'] ) {
		return 'warn';
	}
	return 'good';
}

/**
 * Human words for a status band, so colour is never the only signal.
 *
 * @param string $status 'danger' | 'warn' | 'good'.
 * @return string
 */
function mmr_status_words( $status ) {
	switch ( $status ) {
		case 'danger':
			return __( 'below target', 'mm-register' );
		case 'good':
			return __( 'above target', 'mm-register' );
		default:
			return __( 'on target', 'mm-register' );
	}
}
