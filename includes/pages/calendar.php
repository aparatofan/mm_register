<?php
/**
 * Calendar page — the weekly grid.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Determine the displayed week (Monday).
$requested = isset( $_GET['week'] ) ? sanitize_text_field( wp_unslash( $_GET['week'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
if ( $requested && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $requested ) ) {
	$monday = mmr_week_monday( $requested );
} else {
	$monday = mmr_week_monday( current_time( 'Y-m-d' ) );
}

$monday_ts = strtotime( $monday );
$days = array();
for ( $i = 0; $i < MMR_DAY_COLUMNS; $i++ ) {
	$ts = strtotime( "+{$i} days", $monday_ts );
	$days[] = array(
		'date'  => wp_date( 'Y-m-d', $ts ),
		'dow'   => $i,
		'label' => mmr_weekday_abbr( $i ),
		'short' => wp_date( 'j.m', $ts ),
		'is_today' => wp_date( 'Y-m-d', $ts ) === current_time( 'Y-m-d' ),
	);
}
$week_end = $days[ count( $days ) - 1 ]['date'];
$lessons = MMR_Data::lessons_between( $monday, $week_end );

// Totals. One loop, two outputs: the week sum and the lesson count are the
// exact same set of rows, so the two numbers can never disagree.
$day_totals = array_fill_keys( wp_list_pluck( $days, 'date' ), 0.0 );
$week_total = 0.0;
$week_count = 0;
foreach ( $lessons as $lesson ) {
	$day_totals[ $lesson->lesson_date ] += (float) $lesson->fee_snapshot;
	$week_total += (float) $lesson->fee_snapshot;
	$week_count++;
}
// Month totals are grouped by each lesson's own date, never by the displayed
// week or by the week's start month. A week straddling a month boundary gets
// one badge per month it touches; derive that list from the rendered days
// rather than special-casing "one or two".
$month_periods = array();
foreach ( $days as $day ) {
	$period = substr( $day['date'], 0, 7 );
	if ( ! in_array( $period, $month_periods, true ) ) {
		$month_periods[] = $period;
	}
}
sort( $month_periods ); // `YYYY-MM` sorts lexicographically, i.e. chronologically.

// Two numbers per month: the full calendar-month total, and the slice of it
// that this week accounts for. JS needs the slice so a live edit can move the
// badge for the edited lesson's own month and leave the other one alone.
$month_totals      = array();
$month_week_totals = array_fill_keys( $month_periods, 0.0 );
foreach ( $month_periods as $period ) {
	$month_totals[ $period ] = MMR_Data::month_actual_revenue( $period );
}
foreach ( $days as $day ) {
	$month_week_totals[ substr( $day['date'], 0, 7 ) ] += $day_totals[ $day['date'] ];
}

// Conditional status for the two coloured pills (computed server-side).
$week_status  = mmr_status_for_amount( $week_total );
$count_status = mmr_status_for_count( $week_count );

$week_aria_tpl    = __( 'Week total %1$s — %2$s', 'mm-register' );
$lessons_aria_tpl = __( 'Lessons this week: %1$s — %2$s', 'mm-register' );
$week_aria    = sprintf( $week_aria_tpl, mmr_money( $week_total ), mmr_status_words( $week_status ) );
$lessons_aria = sprintf( $lessons_aria_tpl, $week_count, mmr_status_words( $count_status ) );

$prev_week = wp_date( 'Y-m-d', strtotime( '-7 days', $monday_ts ) );
$next_week = wp_date( 'Y-m-d', strtotime( '+7 days', $monday_ts ) );
$today = current_time( 'Y-m-d' );
?>
<div class="mmr-wrap mmr-calendar" id="mmr-calendar"
	data-monday="<?php echo esc_attr( $monday ); ?>"
	data-week-end="<?php echo esc_attr( $week_end ); ?>">

	<h1 class="mmr-page-title">
		<?php esc_html_e( 'Calendar', 'mm-register' ); ?>
		<span class="mmr-week-range"><?php echo esc_html( wp_date( 'j.m.Y', $monday_ts ) . ' – ' . wp_date( 'j.m.Y', strtotime( $week_end ) ) ); ?></span>
	</h1>

	<div class="mmr-toolbar">
		<div class="mmr-nav">
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'calendar', array( 'week' => $prev_week ) ) ); ?>">‹ <?php esc_html_e( 'Prev', 'mm-register' ); ?></a>
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'calendar', array( 'week' => $today ) ) ); ?>"><?php esc_html_e( 'Today', 'mm-register' ); ?></a>
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'calendar', array( 'week' => $next_week ) ) ); ?>"><?php esc_html_e( 'Next', 'mm-register' ); ?> ›</a>
			<label class="mmr-jump">
				<span class="screen-reader-text"><?php esc_html_e( 'Jump to date', 'mm-register' ); ?></span>
				<input type="date" id="mmr-jump" value="<?php echo esc_attr( $monday ); ?>">
			</label>
			<button type="button" class="button" id="mmr-copy-week"><?php esc_html_e( 'Copy week →', 'mm-register' ); ?></button>
		</div>
		<div class="mmr-totals">
			<span class="mmr-pill mmr-pill--lessons mmr-pill--<?php echo esc_attr( $count_status ); ?>" id="mmr-lesson-pill" aria-label="<?php echo esc_attr( $lessons_aria ); ?>">
				<span class="mmr-pill-label"><?php esc_html_e( 'Lessons', 'mm-register' ); ?>:</span> <strong class="mmr-pill-value" id="mmr-lesson-count"><?php echo (int) $week_count; ?></strong>
			</span>
			<span class="mmr-pill mmr-pill--week mmr-pill--<?php echo esc_attr( $week_status ); ?>" id="mmr-week-pill" aria-label="<?php echo esc_attr( $week_aria ); ?>">
				<span class="mmr-pill-label"><?php esc_html_e( 'Week', 'mm-register' ); ?>:</span> <strong class="mmr-pill-value" id="mmr-week-total"><?php echo mmr_money_esc( $week_total ); ?></strong>
			</span>
			<?php foreach ( $month_periods as $period ) : ?>
				<?php
				// data-month-base is the month total minus this week's share of
				// it, so JS can rebuild the badge from a fixed number plus the
				// live week slice instead of accumulating deltas.
				$month_base = $month_totals[ $period ] - $month_week_totals[ $period ];
				?>
				<span class="mmr-pill mmr-pill--month" data-period="<?php echo esc_attr( $period ); ?>">
					<span class="mmr-pill-label"><?php echo esc_html( mmr_period_label( $period ) ); ?>:</span> <strong class="mmr-pill-value mmr-month-total" data-period="<?php echo esc_attr( $period ); ?>" data-month-base="<?php echo esc_attr( number_format( $month_base, 2, '.', '' ) ); ?>"><?php echo mmr_money_esc( $month_totals[ $period ] ); ?></strong>
				</span>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="mmr-day-tabs" role="tablist">
		<?php foreach ( $days as $day ) : ?>
			<button type="button" class="mmr-day-tab<?php echo $day['is_today'] ? ' is-today' : ''; ?>" data-day="<?php echo esc_attr( $day['dow'] ); ?>">
				<?php echo esc_html( $day['label'] . ' ' . $day['short'] ); ?>
			</button>
		<?php endforeach; ?>
	</div>

	<div class="mmr-grid-scroll">
	<div class="mmr-grid" style="--mmr-cols: <?php echo (int) MMR_DAY_COLUMNS; ?>;">
		<?php foreach ( $days as $day ) : ?>
			<div class="mmr-col<?php echo $day['is_today'] ? ' is-today' : ''; ?>" data-day="<?php echo esc_attr( $day['dow'] ); ?>" data-date="<?php echo esc_attr( $day['date'] ); ?>">
				<div class="mmr-col-head">
					<span class="mmr-col-dow"><?php echo esc_html( $day['label'] ); ?></span>
					<span class="mmr-col-date"><?php echo esc_html( $day['short'] ); ?></span>
				</div>
				<?php for ( $slot = 1; $slot <= MMR_SLOTS_PER_DAY; $slot++ ) :
					$key = $day['date'] . '|' . $slot;
					$lesson = isset( $lessons[ $key ] ) ? $lessons[ $key ] : null;
					?>
					<div class="mmr-slot<?php echo $lesson ? ' is-filled' : ' is-empty'; ?>"
						tabindex="0" role="button"
						data-date="<?php echo esc_attr( $day['date'] ); ?>"
						data-slot="<?php echo esc_attr( $slot ); ?>"
						<?php if ( $lesson ) : ?>
							data-lesson-id="<?php echo esc_attr( $lesson->id ); ?>"
							data-class-id="<?php echo esc_attr( $lesson->class_id ); ?>"
							data-fee="<?php echo esc_attr( $lesson->fee_snapshot ); ?>"
							data-note="<?php echo esc_attr( $lesson->note ); ?>"
						<?php endif; ?>>
						<?php if ( $lesson ) : ?>
							<span class="mmr-slot-name" title="<?php echo esc_attr( $lesson->class_name ); ?>"><?php echo esc_html( $lesson->class_name ); ?><?php if ( $lesson->note ) : ?><span class="mmr-slot-note" title="<?php echo esc_attr( $lesson->note ); ?>">*</span><?php endif; ?></span>
							<span class="mmr-slot-fee"><?php echo mmr_money_esc( $lesson->fee_snapshot ); ?></span>
						<?php else : ?>
							<span class="mmr-slot-plus">+</span>
						<?php endif; ?>
					</div>
				<?php endfor; ?>
				<div class="mmr-col-total">
					<?php esc_html_e( 'Σ', 'mm-register' ); ?> <strong class="mmr-day-total" data-date="<?php echo esc_attr( $day['date'] ); ?>"><?php echo mmr_money_esc( $day_totals[ $day['date'] ] ); ?></strong>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	</div>

	<p class="mmr-hint"><?php esc_html_e( 'Click an empty slot to book a lesson. Click a filled slot to edit, override the fee, add a note, move or delete it. Deleting a lesson means it never happened financially.', 'mm-register' ); ?></p>
</div>
