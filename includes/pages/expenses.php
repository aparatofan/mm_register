<?php
/**
 * Expenses page — recurring expenses checklist + definitions CRUD.
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

$monthly = MMR_Data::expenses( array( 'frequency' => 'monthly', 'active' => 1 ) );
$yearly  = MMR_Data::expenses( array( 'frequency' => 'yearly' ) );
$inactive = MMR_Data::expenses( array( 'active' => 0 ) );

list( $projected_total, $spent_total, $remaining ) = MMR_Data::expenses_month_summary( $period );

$period_ts = strtotime( $period . '-01' );
$prev_period = wp_date( 'Y-m', strtotime( '-1 month', $period_ts ) );
$next_period = wp_date( 'Y-m', strtotime( '+1 month', $period_ts ) );

/** Category badge helper. */
function mmr_cat_badge( $cat ) {
	$labels = array( 'critical' => 'critical', 'moderate' => 'moderate', 'whim' => 'whim' );
	$label = isset( $labels[ $cat ] ) ? $labels[ $cat ] : $cat;
	return '<span class="mmr-cat mmr-cat-' . esc_attr( $cat ) . '">' . esc_html( $label ) . '</span>';
}
?>
<div class="wrap mmr-wrap mmr-expenses">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Expenses', 'mm-register' ); ?>
		<span class="mmr-week-range"><?php echo esc_html( mmr_period_label( $period ) ); ?></span>
	</h1>

	<div class="mmr-toolbar">
		<div class="mmr-nav">
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'expenses', array( 'period' => $prev_period ) ) ); ?>">‹ <?php echo esc_html( mmr_period_label( $prev_period ) ); ?></a>
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'expenses', array( 'period' => $next_period ) ) ); ?>"><?php echo esc_html( mmr_period_label( $next_period ) ); ?> ›</a>
		</div>
		<div class="mmr-totals">
			<span class="mmr-total-chip"><?php esc_html_e( 'Projected', 'mm-register' ); ?>: <strong><?php echo mmr_money_esc( $projected_total ); ?></strong></span>
			<span class="mmr-total-chip"><?php esc_html_e( 'Paid so far', 'mm-register' ); ?>: <strong><?php echo mmr_money_esc( $spent_total ); ?></strong></span>
			<span class="mmr-total-chip mmr-total-month"><?php esc_html_e( 'Remaining', 'mm-register' ); ?>: <strong><?php echo mmr_money_esc( $remaining ); ?></strong></span>
		</div>
	</div>

	<p><button type="button" class="button button-primary mmr-open-modal" data-modal="mmr-expense-modal" data-reset="1"><?php esc_html_e( '+ Add expense', 'mm-register' ); ?></button></p>

	<h2><?php esc_html_e( 'Monthly checklist', 'mm-register' ); ?></h2>
	<table class="widefat striped mmr-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Expense', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Category', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Projected', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Due', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Status this month', 'mm-register' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $monthly ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No active monthly expenses.', 'mm-register' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $monthly as $expense ) :
				$payment = MMR_Data::expense_payment( $expense->id, $period );
				$json = wp_json_encode( array(
					'id'        => (int) $expense->id,
					'title'     => $expense->title,
					'projected' => $expense->projected,
					'pay_type'  => $expense->pay_type,
					'frequency' => $expense->frequency,
					'category'  => $expense->category,
					'due_note'  => $expense->due_note,
					'active'    => (int) $expense->active,
				) );
				?>
				<tr class="<?php echo $payment ? 'mmr-paid' : ''; ?>">
					<td><strong><?php echo esc_html( $expense->title ); ?></strong> <span class="mmr-muted mmr-small"><?php echo esc_html( $expense->pay_type ); ?></span></td>
					<td><?php echo mmr_cat_badge( $expense->category ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td><?php echo mmr_money_esc( $expense->projected ); ?></td>
					<td class="mmr-muted"><?php echo esc_html( $expense->due_note ); ?></td>
					<td>
						<?php if ( $payment ) : ?>
							<span class="mmr-badge mmr-badge-ok">✓ <?php echo mmr_money_esc( $payment->spent ); ?></span>
							<span class="mmr-muted mmr-small"><?php echo $payment->paid_on ? esc_html( wp_date( 'j.m', strtotime( $payment->paid_on ) ) ) : ''; ?></span>
						<?php else : ?>
							<span class="mmr-muted"><?php esc_html_e( 'not paid', 'mm-register' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $payment ) : ?>
							<form method="post" class="mmr-inline-form">
								<?php wp_nonce_field( 'mmr_form' ); ?>
								<input type="hidden" name="mmr_action" value="unmark_expense_paid">
								<input type="hidden" name="expense_id" value="<?php echo esc_attr( $expense->id ); ?>">
								<input type="hidden" name="period" value="<?php echo esc_attr( $period ); ?>">
								<button type="submit" class="button button-small"><?php esc_html_e( 'Undo', 'mm-register' ); ?></button>
							</form>
						<?php else : ?>
							<form method="post" class="mmr-inline-form mmr-markpaid">
								<?php wp_nonce_field( 'mmr_form' ); ?>
								<input type="hidden" name="mmr_action" value="mark_expense_paid">
								<input type="hidden" name="expense_id" value="<?php echo esc_attr( $expense->id ); ?>">
								<input type="hidden" name="period" value="<?php echo esc_attr( $period ); ?>">
								<input type="text" name="spent" class="small-text" value="<?php echo esc_attr( number_format( (float) $expense->projected, 2, '.', '' ) ); ?>">
								<button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Mark paid', 'mm-register' ); ?></button>
							</form>
						<?php endif; ?>
						<button type="button" class="button button-small mmr-open-modal" data-modal="mmr-expense-modal" data-json="<?php echo esc_attr( $json ); ?>"><?php esc_html_e( 'Edit', 'mm-register' ); ?></button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Yearly items', 'mm-register' ); ?></h2>
	<table class="widefat striped mmr-table">
		<thead><tr><th><?php esc_html_e( 'Expense', 'mm-register' ); ?></th><th><?php esc_html_e( 'Category', 'mm-register' ); ?></th><th><?php esc_html_e( 'Projected', 'mm-register' ); ?></th><th><?php esc_html_e( 'Due', 'mm-register' ); ?></th><th><?php esc_html_e( 'Active', 'mm-register' ); ?></th><th></th></tr></thead>
		<tbody>
			<?php if ( empty( $yearly ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No yearly expenses.', 'mm-register' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $yearly as $expense ) :
				$json = wp_json_encode( array(
					'id'        => (int) $expense->id,
					'title'     => $expense->title,
					'projected' => $expense->projected,
					'pay_type'  => $expense->pay_type,
					'frequency' => $expense->frequency,
					'category'  => $expense->category,
					'due_note'  => $expense->due_note,
					'active'    => (int) $expense->active,
				) );
				?>
				<tr>
					<td><strong><?php echo esc_html( $expense->title ); ?></strong></td>
					<td><?php echo mmr_cat_badge( $expense->category ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td><?php echo mmr_money_esc( $expense->projected ); ?></td>
					<td class="mmr-muted"><?php echo esc_html( $expense->due_note ); ?></td>
					<td><?php echo $expense->active ? '✓' : '—'; ?></td>
					<td><button type="button" class="button button-small mmr-open-modal" data-modal="mmr-expense-modal" data-json="<?php echo esc_attr( $json ); ?>"><?php esc_html_e( 'Edit', 'mm-register' ); ?></button></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $inactive ) : ?>
		<h2><?php esc_html_e( 'Inactive', 'mm-register' ); ?></h2>
		<table class="widefat mmr-table">
			<tbody>
				<?php foreach ( $inactive as $expense ) :
					$json = wp_json_encode( array(
						'id'        => (int) $expense->id,
						'title'     => $expense->title,
						'projected' => $expense->projected,
						'pay_type'  => $expense->pay_type,
						'frequency' => $expense->frequency,
						'category'  => $expense->category,
						'due_note'  => $expense->due_note,
						'active'    => (int) $expense->active,
					) );
					?>
					<tr class="mmr-archived">
						<td><?php echo esc_html( $expense->title ); ?></td>
						<td><?php echo mmr_money_esc( $expense->projected ); ?></td>
						<td><button type="button" class="button button-small mmr-open-modal" data-modal="mmr-expense-modal" data-json="<?php echo esc_attr( $json ); ?>"><?php esc_html_e( 'Edit', 'mm-register' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<!-- Expense modal -->
	<div class="mmr-modal" id="mmr-expense-modal" hidden>
		<div class="mmr-modal-box">
			<h2 class="mmr-modal-title"><?php esc_html_e( 'Recurring expense', 'mm-register' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'mmr_form' ); ?>
				<input type="hidden" name="mmr_action" value="save_expense">
				<input type="hidden" name="id" value="0" data-field="id">
				<div class="mmr-form-grid">
					<p><label><?php esc_html_e( 'Title', 'mm-register' ); ?><br><input type="text" name="title" data-field="title" class="regular-text" required></label></p>
					<p><label><?php esc_html_e( 'Projected (zł)', 'mm-register' ); ?><br><input type="text" name="projected" data-field="projected" class="regular-text"></label></p>
					<p><label><?php esc_html_e( 'Pay type', 'mm-register' ); ?><br>
						<select name="pay_type" data-field="pay_type">
							<option value="manual"><?php esc_html_e( 'manual', 'mm-register' ); ?></option>
							<option value="subscription"><?php esc_html_e( 'subscription', 'mm-register' ); ?></option>
						</select></label></p>
					<p><label><?php esc_html_e( 'Frequency', 'mm-register' ); ?><br>
						<select name="frequency" data-field="frequency">
							<option value="monthly"><?php esc_html_e( 'monthly', 'mm-register' ); ?></option>
							<option value="yearly"><?php esc_html_e( 'yearly', 'mm-register' ); ?></option>
						</select></label></p>
					<p><label><?php esc_html_e( 'Category', 'mm-register' ); ?><br>
						<select name="category" data-field="category">
							<option value="critical"><?php esc_html_e( 'critical', 'mm-register' ); ?></option>
							<option value="moderate"><?php esc_html_e( 'moderate', 'mm-register' ); ?></option>
							<option value="whim"><?php esc_html_e( 'whim', 'mm-register' ); ?></option>
						</select></label></p>
					<p><label><?php esc_html_e( 'Due note', 'mm-register' ); ?><br><input type="text" name="due_note" data-field="due_note" class="regular-text" placeholder="20 dnia"></label></p>
				</div>
				<p><label><input type="checkbox" name="active" value="1" data-field="active" checked> <?php esc_html_e( 'Active', 'mm-register' ); ?></label></p>
				<p class="mmr-modal-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'mm-register' ); ?></button>
					<button type="button" class="button mmr-close-modal"><?php esc_html_e( 'Cancel', 'mm-register' ); ?></button>
					<button type="submit" class="button mmr-delete-btn" name="mmr_action" value="delete_expense" formnovalidate onclick="return confirm('<?php echo esc_js( __( 'Delete this expense and its payment history?', 'mm-register' ) ); ?>');"><?php esc_html_e( 'Delete', 'mm-register' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>
