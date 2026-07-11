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
 * Human month label from a period, e.g. `2026-07` → `lipiec 2026`.
 * Uses Polish month names regardless of site locale for consistency.
 *
 * @param string $period `YYYY-MM`.
 * @return string
 */
function mmr_period_label( $period ) {
	static $names = array(
		1  => 'styczeń',
		2  => 'luty',
		3  => 'marzec',
		4  => 'kwiecień',
		5  => 'maj',
		6  => 'czerwiec',
		7  => 'lipiec',
		8  => 'sierpień',
		9  => 'wrzesień',
		10 => 'październik',
		11 => 'listopad',
		12 => 'grudzień',
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
 * Polish short weekday abbreviations, Monday-first index 0..6.
 *
 * @param int $dow 0=Mon .. 6=Sun.
 * @return string
 */
function mmr_weekday_abbr( $dow ) {
	static $days = array( 'PON', 'WT', 'ŚR', 'CZW', 'PT', 'SOB', 'NDZ' );
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
