<?php
/**
 * Settlement page — monthly settlement workspace.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$period = isset( $_GET['period'] ) ? mmr_sanitize_period( sanitize_text_field( wp_unslash( $_GET['period'] ) ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification
if ( ! $period ) {
	$period = mmr_default_settlement_period();
}
$result = MMR_Data::settlement( $period );

// Prev/next month for navigation.
$period_ts = strtotime( $period . '-01' );
$prev_period = wp_date( 'Y-m', strtotime( '-1 month', $period_ts ) );
$next_period = wp_date( 'Y-m', strtotime( '+1 month', $period_ts ) );
?>
<div class="wrap mmr-wrap mmr-settlement">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Settlement', 'mm-register' ); ?>
		<span class="mmr-week-range"><?php echo esc_html( mmr_period_label( $period ) ); ?></span>
	</h1>

	<div class="mmr-toolbar">
		<div class="mmr-nav">
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'settlement', array( 'period' => $prev_period ) ) ); ?>">‹ <?php echo esc_html( mmr_period_label( $prev_period ) ); ?></a>
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'settlement', array( 'period' => $next_period ) ) ); ?>"><?php echo esc_html( mmr_period_label( $next_period ) ); ?> ›</a>
			<form method="get" class="mmr-inline-form">
				<input type="hidden" name="page" value="mmr-settlement">
				<input type="month" name="period" value="<?php echo esc_attr( $period ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Go', 'mm-register' ); ?></button>
			</form>
		</div>
		<div class="mmr-totals">
			<span class="mmr-total-chip mmr-total-month"><?php esc_html_e( 'Month total', 'mm-register' ); ?>: <strong><?php echo mmr_money_esc( $result['grand_total'] ); ?></strong></span>
		</div>
	</div>

	<?php if ( empty( $result['rows'] ) ) : ?>
		<p><?php esc_html_e( 'No lessons booked in this month.', 'mm-register' ); ?></p>
	<?php else : ?>
	<table class="widefat striped mmr-table mmr-settlement-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Class', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Lessons', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Fee(s)', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Lessons subtotal', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'TBT surcharges', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Class total', 'mm-register' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $result['rows'] as $row ) :
				$fee_parts = array();
				foreach ( $row['fees_used'] as $fee => $count ) {
					$fee_parts[] = mmr_money( $fee ) . ' ×' . $count;
				}
				?>
				<tr class="mmr-settle-row">
					<td>
						<strong><?php echo esc_html( $row['class_name'] ); ?></strong>
						<?php if ( $row['is_group'] ) : ?>
							<button type="button" class="button-link mmr-toggle-split" aria-expanded="false"><?php esc_html_e( 'split ▾', 'mm-register' ); ?></button>
						<?php endif; ?>
					</td>
					<td><?php echo (int) $row['lesson_count']; ?></td>
					<td class="mmr-muted"><?php echo esc_html( implode( ', ', $fee_parts ) ); ?></td>
					<td><?php echo mmr_money_esc( $row['lessons_subtotal'] ); ?></td>
					<td class="mmr-muted">
						<?php if ( $row['tbt_lines'] ) : ?>
							<?php foreach ( $row['tbt_lines'] as $line ) : ?>
								<div>+ TBT: <?php echo esc_html( $line['student_name'] ); ?> <?php echo mmr_money_esc( $line['amount'] ); ?></div>
							<?php endforeach; ?>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td><strong><?php echo mmr_money_esc( $row['class_total'] ); ?></strong></td>
				</tr>
				<?php if ( $row['is_group'] ) : ?>
					<tr class="mmr-split-row" hidden>
						<td colspan="6">
							<table class="mmr-subtable">
								<thead><tr><th><?php esc_html_e( 'Member', 'mm-register' ); ?></th><th><?php esc_html_e( 'Lessons', 'mm-register' ); ?></th><th><?php esc_html_e( 'TBT', 'mm-register' ); ?></th><th><?php esc_html_e( 'Total', 'mm-register' ); ?></th></tr></thead>
								<tbody>
									<?php foreach ( $row['member_breakdown'] as $mb ) : ?>
										<tr>
											<td><?php echo esc_html( $mb['student_name'] ); ?></td>
											<td><?php echo mmr_money_esc( $mb['lessons_amount'] ); ?></td>
											<td><?php echo $mb['tbt'] > 0 ? mmr_money_esc( $mb['tbt'] ) : '—'; ?></td>
											<td><strong><?php echo mmr_money_esc( $mb['total'] ); ?></strong></td>
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
				<th colspan="5" style="text-align:right;"><?php esc_html_e( 'Month grand total', 'mm-register' ); ?></th>
				<th><?php echo mmr_money_esc( $result['grand_total'] ); ?></th>
			</tr>
		</tfoot>
	</table>
	<?php endif; ?>
</div>
