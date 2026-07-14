<?php
/**
 * Settlement page — read-only, computed monthly report.
 *
 * Nothing is stored: every number is derived from mmr_lessons (fee and
 * member-share snapshots), so any month, past or future, is reproducible.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$period = isset( $_GET['period'] ) ? mmr_sanitize_period( sanitize_text_field( wp_unslash( $_GET['period'] ) ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification
if ( ! $period ) {
	$period = current_time( 'Y-m' );
}
$report = MMR_Data::settlement( $period );

// Prev/next month for navigation.
$period_ts = strtotime( $period . '-01' );
$prev_period = wp_date( 'Y-m', strtotime( '-1 month', $period_ts ) );
$next_period = wp_date( 'Y-m', strtotime( '+1 month', $period_ts ) );

// Payload for the copy / CSV buttons.
$summary_json = array(
	'period'  => $period,
	'headers' => array(
		__( 'Student', 'mm-register' ),
		__( 'Company', 'mm-register' ),
		__( 'Lessons', 'mm-register' ),
		__( 'Lesson total', 'mm-register' ),
		__( 'TBT surcharge', 'mm-register' ),
		__( 'To bill', 'mm-register' ),
	),
	'rows'    => array(),
	'total'   => $report['to_bill_total'],
);
foreach ( $report['students'] as $s ) {
	$summary_json['rows'][] = array(
		'student'      => $s['student_name'],
		'company'      => $s['company'],
		'lessons'      => $s['lessons'],
		'lesson_total' => $s['lesson_total'],
		'tbt'          => $s['tbt'],
		'to_bill'      => $s['to_bill'],
	);
}
?>
<div class="mmr-wrap mmr-settlement">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Settlement', 'mm-register' ); ?>
		<span class="mmr-week-range"><?php echo esc_html( mmr_period_label( $period ) ); ?></span>
	</h1>

	<div class="mmr-toolbar">
		<div class="mmr-nav">
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'settlement', array( 'period' => $prev_period ) ) ); ?>">‹ <?php echo esc_html( mmr_period_label( $prev_period ) ); ?></a>
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'settlement', array( 'period' => $next_period ) ) ); ?>"><?php echo esc_html( mmr_period_label( $next_period ) ); ?> ›</a>
			<form method="get" class="mmr-inline-form">
				<input type="hidden" name="page" value="mmr-settlement">
				<label class="screen-reader-text" for="mmr-period-jump"><?php esc_html_e( 'Jump to month', 'mm-register' ); ?></label>
				<input type="month" id="mmr-period-jump" name="period" value="<?php echo esc_attr( $period ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Go', 'mm-register' ); ?></button>
			</form>
		</div>
		<div class="mmr-totals">
			<span class="mmr-total-chip"><?php esc_html_e( 'Month total', 'mm-register' ); ?>: <strong><?php echo mmr_money_esc( $report['month_total'] ); ?></strong></span>
			<span class="mmr-total-chip mmr-total-month"><?php esc_html_e( 'To bill', 'mm-register' ); ?>: <strong><?php echo mmr_money_esc( $report['to_bill_total'] ); ?></strong></span>
		</div>
	</div>

	<?php if ( empty( $report['classes'] ) ) : ?>
		<p><?php esc_html_e( 'No lessons booked in this month.', 'mm-register' ); ?></p>
	<?php else : ?>

	<h2 class="mmr-section-title"><?php esc_html_e( 'Per class', 'mm-register' ); ?></h2>
	<div class="mmr-table-scroll">
		<table class="widefat striped mmr-table mmr-settlement-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Class', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Client type', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Company', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Settle method', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php esc_html_e( 'Lessons', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Rate (snapshot)', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php esc_html_e( 'Class total', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Members', 'mm-register' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $report['classes'] as $row ) :
					$fee_parts = array();
					foreach ( $row['fees_used'] as $fee => $count ) {
						$fee_parts[] = mmr_money( $fee ) . ' ×' . $count;
					}
					?>
					<tr class="mmr-settle-row">
						<td><strong><?php echo esc_html( $row['class_name'] ); ?></strong></td>
						<td><?php echo esc_html( $row['client_type'] ); ?></td>
						<td><?php echo esc_html( $row['company'] ); ?></td>
						<td><?php echo esc_html( $row['settle_method'] ); ?></td>
						<td class="mmr-num"><?php echo (int) $row['lesson_count']; ?></td>
						<td class="mmr-muted"><?php echo esc_html( implode( ', ', $fee_parts ) ); ?></td>
						<td class="mmr-num"><strong><?php echo mmr_money_esc( $row['class_total'] ); ?></strong></td>
						<td>
							<?php echo count( $row['members'] ); ?>
							<?php if ( $row['members'] ) : ?>
								<button type="button" class="button-link mmr-toggle-split" aria-expanded="false"><?php esc_html_e( 'split ▾', 'mm-register' ); ?></button>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( $row['members'] ) : ?>
						<tr class="mmr-split-row" hidden>
							<td colspan="8">
								<table class="mmr-subtable">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Member', 'mm-register' ); ?></th>
											<th class="mmr-num"><?php esc_html_e( 'Lessons', 'mm-register' ); ?></th>
											<th class="mmr-num"><?php esc_html_e( 'Lesson total', 'mm-register' ); ?></th>
											<th class="mmr-num"><?php esc_html_e( 'TBT', 'mm-register' ); ?></th>
											<th class="mmr-num"><?php esc_html_e( 'Total', 'mm-register' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $row['members'] as $mb ) : ?>
											<tr>
												<td><?php echo esc_html( $mb['student_name'] ); ?></td>
												<td class="mmr-num"><?php echo (int) $mb['lessons']; ?></td>
												<td class="mmr-num"><?php echo mmr_money_esc( $mb['lessons_amount'] ); ?></td>
												<td class="mmr-num"><?php echo $mb['tbt'] > 0 ? mmr_money_esc( $mb['tbt'] ) : '—'; ?></td>
												<td class="mmr-num"><strong><?php echo mmr_money_esc( $mb['total'] ); ?></strong></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="6" class="mmr-foot-label"><?php esc_html_e( 'Month total', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php echo mmr_money_esc( $report['month_total'] ); ?></th>
					<th></th>
				</tr>
			</tfoot>
		</table>
	</div>

	<h2 class="mmr-section-title">
		<?php esc_html_e( 'Per student — to bill', 'mm-register' ); ?>
	</h2>
	<div class="mmr-toolbar">
		<div class="mmr-nav">
			<button type="button" class="button" id="mmr-copy-summary"><?php esc_html_e( 'Copy to clipboard', 'mm-register' ); ?></button>
			<button type="button" class="button" id="mmr-download-csv"><?php esc_html_e( 'Download CSV', 'mm-register' ); ?></button>
			<span class="mmr-muted mmr-small" id="mmr-copy-feedback" role="status" aria-live="polite"></span>
		</div>
	</div>
	<div class="mmr-table-scroll">
		<table class="widefat striped mmr-table mmr-student-summary-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Student', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Company', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php esc_html_e( 'Lessons', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php esc_html_e( 'Lesson total', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php esc_html_e( 'TBT surcharge', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php esc_html_e( 'To bill', 'mm-register' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $report['students'] as $s ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $s['student_name'] ); ?></strong></td>
						<td><?php echo esc_html( $s['company'] ); ?></td>
						<td class="mmr-num"><?php echo (int) $s['lessons']; ?></td>
						<td class="mmr-num"><?php echo mmr_money_esc( $s['lesson_total'] ); ?></td>
						<td class="mmr-num"><?php echo $s['tbt'] > 0 ? mmr_money_esc( $s['tbt'] ) : '—'; ?></td>
						<td class="mmr-num"><strong><?php echo mmr_money_esc( $s['to_bill'] ); ?></strong></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="5" class="mmr-foot-label"><?php esc_html_e( 'To bill total', 'mm-register' ); ?></th>
					<th class="mmr-num"><?php echo mmr_money_esc( $report['to_bill_total'] ); ?></th>
				</tr>
			</tfoot>
		</table>
	</div>

	<script type="application/json" id="mmr-settlement-summary-data"><?php echo wp_json_encode( $summary_json ); ?></script>

	<?php endif; ?>
</div>
