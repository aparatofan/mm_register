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

// Totals.
$day_totals = array_fill_keys( wp_list_pluck( $days, 'date' ), 0.0 );
$week_total = 0.0;
foreach ( $lessons as $lesson ) {
	$day_totals[ $lesson->lesson_date ] += (float) $lesson->fee_snapshot;
	$week_total += (float) $lesson->fee_snapshot;
}
$month_period = wp_date( 'Y-m', $monday_ts );
$month_total = MMR_Data::month_actual_revenue( $month_period );

$prev_week = wp_date( 'Y-m-d', strtotime( '-7 days', $monday_ts ) );
$next_week = wp_date( 'Y-m-d', strtotime( '+7 days', $monday_ts ) );
$today = current_time( 'Y-m-d' );
?>
<div class="wrap mmr-wrap mmr-calendar" id="mmr-calendar"
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
			<span class="mmr-total-chip"><?php esc_html_e( 'Week', 'mm-register' ); ?>: <strong id="mmr-week-total"><?php echo mmr_money_esc( $week_total ); ?></strong></span>
			<span class="mmr-total-chip mmr-total-month"><?php echo esc_html( mmr_period_label( $month_period ) ); ?>: <strong id="mmr-month-total"><?php echo mmr_money_esc( $month_total ); ?></strong></span>
		</div>
	</div>

	<div class="mmr-day-tabs" role="tablist">
		<?php foreach ( $days as $day ) : ?>
			<button type="button" class="mmr-day-tab<?php echo $day['is_today'] ? ' is-today' : ''; ?>" data-day="<?php echo esc_attr( $day['dow'] ); ?>">
				<?php echo esc_html( $day['label'] . ' ' . $day['short'] ); ?>
			</button>
		<?php endforeach; ?>
	</div>

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
						data-date="<?php echo esc_attr( $day['date'] ); ?>"
						data-slot="<?php echo esc_attr( $slot ); ?>"
						<?php if ( $lesson ) : ?>
							data-lesson-id="<?php echo esc_attr( $lesson->id ); ?>"
							data-class-id="<?php echo esc_attr( $lesson->class_id ); ?>"
							data-fee="<?php echo esc_attr( $lesson->fee_snapshot ); ?>"
							data-note="<?php echo esc_attr( $lesson->note ); ?>"
						<?php endif; ?>>
						<?php if ( $lesson ) : ?>
							<span class="mmr-slot-name"><?php echo esc_html( $lesson->class_name ); ?><?php if ( $lesson->note ) : ?><span class="mmr-slot-note" title="<?php echo esc_attr( $lesson->note ); ?>">*</span><?php endif; ?></span>
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

	<p class="mmr-hint"><?php esc_html_e( 'Click an empty slot to book a lesson. Click a filled slot to edit, override the fee, add a note, move or delete it. Deleting a lesson means it never happened financially.', 'mm-register' ); ?></p>
</div>
