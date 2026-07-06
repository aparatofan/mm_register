<?php
/**
 * Budget page — live dashboard mirroring the BUDGET sheet.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$assets      = MMR_Data::budget_items( 'asset' );
$liabilities = MMR_Data::budget_items( 'liability' );
$reserves    = MMR_Data::budget_items( 'reserve' );
$notes_items = MMR_Data::budget_items( 'note_only' );

$sum_assets      = MMR_Data::budget_sum( 'asset' );
$sum_liabilities = MMR_Data::budget_sum( 'liability' );
$sum_reserves    = MMR_Data::budget_sum( 'reserve' );
$receivables     = MMR_Data::unpaid_total();

$current_period = current_time( 'Y-m' );
list( , , $remaining_expenses ) = MMR_Data::expenses_month_summary( $current_period );

$subtotal1     = $sum_assets;
$subtotal2     = $subtotal1 + $receivables;
$total         = $subtotal2 - $sum_liabilities - $remaining_expenses;
$freely_avail  = $total - $sum_reserves;

/**
 * Render a group of editable budget items.
 *
 * @param array  $items Rows.
 * @param string $kind  Kind label.
 */
function mmr_render_budget_group( $items, $kind ) {
	foreach ( $items as $item ) {
		$json = wp_json_encode( array(
			'id'         => (int) $item->id,
			'label'      => $item->label,
			'amount'     => $item->amount,
			'kind'       => $item->kind,
			'sort_order' => (int) $item->sort_order,
			'notes'      => $item->notes,
		) );
		?>
		<tr>
			<td><?php echo esc_html( $item->label ); ?><?php if ( $item->notes ) : ?> <span class="mmr-muted">— <?php echo esc_html( $item->notes ); ?></span><?php endif; ?></td>
			<td class="mmr-num"><?php echo mmr_money_esc( $item->amount ); ?></td>
			<td class="mmr-muted mmr-small"><?php echo $item->updated_at && '1970-01-01 00:00:00' !== $item->updated_at ? esc_html( wp_date( 'j.m.Y', strtotime( $item->updated_at ) ) ) : '—'; ?></td>
			<td><button type="button" class="button button-small mmr-open-modal" data-modal="mmr-budget-modal" data-json="<?php echo esc_attr( $json ); ?>"><?php esc_html_e( 'Edit', 'mm-register' ); ?></button></td>
		</tr>
		<?php
	}
}
?>
<div class="wrap mmr-wrap mmr-budget">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Budget', 'mm-register' ); ?></h1>

	<div class="mmr-budget-layout">
		<div class="mmr-budget-col">
			<h2><?php esc_html_e( 'Assets', 'mm-register' ); ?> <button type="button" class="button button-small mmr-open-modal" data-modal="mmr-budget-modal" data-reset="1" data-kind="asset">+</button></h2>
			<table class="widefat mmr-table">
				<thead><tr><th><?php esc_html_e( 'Account / asset', 'mm-register' ); ?></th><th class="mmr-num"><?php esc_html_e( 'Amount', 'mm-register' ); ?></th><th><?php esc_html_e( 'Updated', 'mm-register' ); ?></th><th></th></tr></thead>
				<tbody><?php mmr_render_budget_group( $assets, 'asset' ); ?></tbody>
			</table>

			<h2><?php esc_html_e( 'Liabilities', 'mm-register' ); ?> <button type="button" class="button button-small mmr-open-modal" data-modal="mmr-budget-modal" data-reset="1" data-kind="liability">+</button></h2>
			<table class="widefat mmr-table">
				<thead><tr><th><?php esc_html_e( 'Liability', 'mm-register' ); ?></th><th class="mmr-num"><?php esc_html_e( 'Amount', 'mm-register' ); ?></th><th><?php esc_html_e( 'Updated', 'mm-register' ); ?></th><th></th></tr></thead>
				<tbody><?php mmr_render_budget_group( $liabilities, 'liability' ); ?></tbody>
			</table>

			<h2><?php esc_html_e( 'Reserves', 'mm-register' ); ?> <button type="button" class="button button-small mmr-open-modal" data-modal="mmr-budget-modal" data-reset="1" data-kind="reserve">+</button></h2>
			<table class="widefat mmr-table">
				<thead><tr><th><?php esc_html_e( 'Reserve / earmarked', 'mm-register' ); ?></th><th class="mmr-num"><?php esc_html_e( 'Amount', 'mm-register' ); ?></th><th><?php esc_html_e( 'Updated', 'mm-register' ); ?></th><th></th></tr></thead>
				<tbody><?php mmr_render_budget_group( $reserves, 'reserve' ); ?></tbody>
			</table>

			<?php if ( $notes_items ) : ?>
				<h2><?php esc_html_e( 'Notes', 'mm-register' ); ?></h2>
				<table class="widefat mmr-table">
					<tbody><?php mmr_render_budget_group( $notes_items, 'note_only' ); ?></tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="mmr-budget-col mmr-budget-summary">
			<h2><?php esc_html_e( 'Summary', 'mm-register' ); ?></h2>
			<table class="mmr-summary-table">
				<tr><td><?php esc_html_e( 'SUB-TOTAL 1', 'mm-register' ); ?> <span class="mmr-muted">(<?php esc_html_e( 'available money', 'mm-register' ); ?>)</span></td><td class="mmr-num"><?php echo mmr_money_esc( $subtotal1 ); ?></td></tr>
				<tr><td><?php esc_html_e( '+ receivables', 'mm-register' ); ?> <span class="mmr-muted">(<?php esc_html_e( 'unpaid settlements', 'mm-register' ); ?>)</span></td><td class="mmr-num"><?php echo mmr_money_esc( $receivables ); ?></td></tr>
				<tr class="mmr-summary-strong"><td><?php esc_html_e( 'SUB-TOTAL 2', 'mm-register' ); ?> <span class="mmr-muted">(<?php esc_html_e( 'when all money arrives', 'mm-register' ); ?>)</span></td><td class="mmr-num"><?php echo mmr_money_esc( $subtotal2 ); ?></td></tr>
				<tr><td><?php esc_html_e( '− liabilities', 'mm-register' ); ?></td><td class="mmr-num">−<?php echo mmr_money_esc( $sum_liabilities ); ?></td></tr>
				<tr><td><?php esc_html_e( '− remaining expenses this month', 'mm-register' ); ?></td><td class="mmr-num">−<?php echo mmr_money_esc( $remaining_expenses ); ?></td></tr>
				<tr class="mmr-summary-strong"><td><?php esc_html_e( 'TOTAL', 'mm-register' ); ?> <span class="mmr-muted">(<?php esc_html_e( 'tyle mi zostaje na koniec miesiąca', 'mm-register' ); ?>)</span></td><td class="mmr-num"><?php echo mmr_money_esc( $total ); ?></td></tr>
				<tr><td><?php esc_html_e( '− reserves', 'mm-register' ); ?></td><td class="mmr-num">−<?php echo mmr_money_esc( $sum_reserves ); ?></td></tr>
				<tr class="mmr-summary-final"><td><?php esc_html_e( 'FREELY AVAILABLE', 'mm-register' ); ?></td><td class="mmr-num"><?php echo mmr_money_esc( $freely_avail ); ?></td></tr>
			</table>
			<p class="mmr-hint"><?php esc_html_e( 'Receivables and remaining expenses update automatically. Assets, liabilities and reserves are edited by hand.', 'mm-register' ); ?></p>
		</div>
	</div>

	<!-- Budget item modal -->
	<div class="mmr-modal" id="mmr-budget-modal" hidden>
		<div class="mmr-modal-box">
			<h2 class="mmr-modal-title"><?php esc_html_e( 'Budget item', 'mm-register' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'mmr_form' ); ?>
				<input type="hidden" name="mmr_action" value="save_budget_item">
				<input type="hidden" name="id" value="0" data-field="id">
				<p><label><?php esc_html_e( 'Label', 'mm-register' ); ?><br><input type="text" name="label" data-field="label" class="regular-text" required></label></p>
				<p><label><?php esc_html_e( 'Amount (zł)', 'mm-register' ); ?><br><input type="text" name="amount" data-field="amount" class="regular-text"></label></p>
				<p><label><?php esc_html_e( 'Kind', 'mm-register' ); ?><br>
					<select name="kind" data-field="kind">
						<option value="asset"><?php esc_html_e( 'asset', 'mm-register' ); ?></option>
						<option value="liability"><?php esc_html_e( 'liability', 'mm-register' ); ?></option>
						<option value="reserve"><?php esc_html_e( 'reserve', 'mm-register' ); ?></option>
						<option value="note_only"><?php esc_html_e( 'note only', 'mm-register' ); ?></option>
					</select></label></p>
				<p><label><?php esc_html_e( 'Sort order', 'mm-register' ); ?><br><input type="number" name="sort_order" data-field="sort_order" class="small-text" value="0"></label></p>
				<p><label><?php esc_html_e( 'Notes', 'mm-register' ); ?><br><textarea name="notes" data-field="notes" rows="2" class="large-text"></textarea></label></p>
				<p class="mmr-modal-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'mm-register' ); ?></button>
					<button type="button" class="button mmr-close-modal"><?php esc_html_e( 'Cancel', 'mm-register' ); ?></button>
					<button type="submit" class="button mmr-delete-btn" name="mmr_action" value="delete_budget_item" formnovalidate onclick="return confirm('<?php echo esc_js( __( 'Delete this item?', 'mm-register' ) ); ?>');"><?php esc_html_e( 'Delete', 'mm-register' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>
