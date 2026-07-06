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
$not_settled = max( 0, $result['grand_total'] - $result['settled'] );

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
			<span class="mmr-total-chip"><?php esc_html_e( 'Grand total', 'mm-register' ); ?>: <strong><?php echo mmr_money_esc( $result['grand_total'] ); ?></strong></span>
			<span class="mmr-total-chip"><?php esc_html_e( 'Settled', 'mm-register' ); ?>: <strong><?php echo mmr_money_esc( $result['settled'] ); ?></strong></span>
			<span class="mmr-total-chip mmr-total-month"><?php esc_html_e( 'Outstanding', 'mm-register' ); ?>: <strong><?php echo mmr_money_esc( $not_settled ); ?></strong></span>
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
				<th><?php esc_html_e( 'Settle', 'mm-register' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $result['rows'] as $row ) :
				$fee_parts = array();
				foreach ( $row['fees_used'] as $fee => $count ) {
					$fee_parts[] = mmr_money( $fee ) . ' ×' . $count;
				}
				$settlement_json = wp_json_encode( array(
					'class_id'     => $row['class_id'],
					'class_name'   => $row['class_name'],
					'period'       => $period,
					'amount'       => $row['class_total'],
					'settle_method'=> $row['settle_method'],
					'members'      => $row['member_breakdown'],
					'is_group'     => $row['is_group'],
				) );
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
					<td>
						<?php if ( ! empty( $row['settled'] ) ) : ?>
							<span class="mmr-badge mmr-badge-ok">✓ <?php esc_html_e( 'settled', 'mm-register' ); ?></span>
							<a class="button-link" href="<?php echo esc_url( mmr_page_url( 'receivables', array( 'period' => $period ) ) ); ?>"><?php esc_html_e( 'view', 'mm-register' ); ?></a>
						<?php endif; ?>
						<button type="button" class="button button-small mmr-record-settlement" data-json="<?php echo esc_attr( $settlement_json ); ?>"><?php esc_html_e( 'Record', 'mm-register' ); ?></button>
					</td>
				</tr>
				<?php if ( $row['is_group'] ) : ?>
					<tr class="mmr-split-row" hidden>
						<td colspan="7">
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
				<th></th>
			</tr>
		</tfoot>
	</table>
	<?php endif; ?>

	<!-- Record settlement modal -->
	<div class="mmr-modal" id="mmr-settlement-modal" hidden>
		<div class="mmr-modal-box">
			<h2 class="mmr-modal-title"><?php esc_html_e( 'Record settlement', 'mm-register' ); ?> — <span id="mmr-settle-class"></span></h2>
			<form method="post">
				<?php wp_nonce_field( 'mmr_form' ); ?>
				<input type="hidden" name="mmr_action" value="record_settlement">
				<input type="hidden" name="class_id" value="0" data-field="class_id">
				<input type="hidden" name="period" value="<?php echo esc_attr( $period ); ?>">

				<p class="mmr-per-member-toggle" hidden>
					<label><input type="checkbox" name="per_member" value="1" id="mmr-per-member"> <?php esc_html_e( 'Create one entry per member', 'mm-register' ); ?></label>
				</p>

				<div id="mmr-settle-single">
					<p><label><?php esc_html_e( 'Doc type', 'mm-register' ); ?><br><input type="text" name="doc_type" class="regular-text" placeholder="invoice / rachunek / revolut"></label></p>
					<p><label><?php esc_html_e( 'Doc number', 'mm-register' ); ?><br><input type="text" name="doc_number" class="regular-text"></label></p>
					<p><label><?php esc_html_e( 'Doc date', 'mm-register' ); ?><br><input type="date" name="doc_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></label></p>
					<p><label><?php esc_html_e( 'Payer label', 'mm-register' ); ?><br><input type="text" name="payer_label" class="regular-text" data-field="payer_label"></label></p>
					<p><label><?php esc_html_e( 'Amount (zł)', 'mm-register' ); ?><br><input type="text" name="amount" class="regular-text" data-field="amount"></label></p>
					<p>
						<label><input type="checkbox" name="ksef" value="1"> <?php esc_html_e( 'KSeF', 'mm-register' ); ?></label>
						<label style="margin-left:1em;"><input type="checkbox" name="sent" value="1"> <?php esc_html_e( 'Sent', 'mm-register' ); ?></label>
					</p>
					<p><label><?php esc_html_e( 'Notes', 'mm-register' ); ?><br><textarea name="notes" rows="2" class="large-text"></textarea></label></p>
				</div>

				<div id="mmr-settle-permember" hidden>
					<p><label><?php esc_html_e( 'Doc type (all)', 'mm-register' ); ?><br><input type="text" name="doc_type_pm" class="regular-text" placeholder="invoice / rachunek"></label></p>
					<table class="mmr-subtable"><thead><tr><th><?php esc_html_e( 'Member', 'mm-register' ); ?></th><th><?php esc_html_e( 'Amount', 'mm-register' ); ?></th></tr></thead>
						<tbody id="mmr-settle-members-rows"></tbody>
					</table>
				</div>

				<p class="mmr-modal-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Record', 'mm-register' ); ?></button>
					<button type="button" class="button mmr-close-modal"><?php esc_html_e( 'Cancel', 'mm-register' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>
