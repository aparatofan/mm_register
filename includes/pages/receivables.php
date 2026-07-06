<?php
/**
 * Receivables page — the settlements register.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$filters = array(
	'period' => isset( $_GET['period'] ) ? mmr_sanitize_period( sanitize_text_field( wp_unslash( $_GET['period'] ) ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
	'payer'  => isset( $_GET['payer'] ) ? sanitize_text_field( wp_unslash( $_GET['payer'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
	'year'   => isset( $_GET['year'] ) ? (int) $_GET['year'] : 0, // phpcs:ignore WordPress.Security.NonceVerification
	'paid'   => isset( $_GET['paid'] ) ? sanitize_key( wp_unslash( $_GET['paid'] ) ) : 'all', // phpcs:ignore WordPress.Security.NonceVerification
);
$rows = MMR_Data::settlements( $filters );
list( $unpaid_count, $oldest_unpaid ) = MMR_Data::unpaid_summary();
$unpaid_total = MMR_Data::unpaid_total();
$classes = MMR_Data::classes( array( 'status' => 'all' ) );
?>
<div class="wrap mmr-wrap mmr-receivables">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Receivables', 'mm-register' ); ?></h1>

	<div class="mmr-cards">
		<div class="mmr-card mmr-card-accent">
			<div class="mmr-card-label"><?php esc_html_e( 'Unpaid total', 'mm-register' ); ?></div>
			<div class="mmr-card-value" id="mmr-unpaid-total"><?php echo mmr_money_esc( $unpaid_total ); ?></div>
		</div>
		<div class="mmr-card">
			<div class="mmr-card-label"><?php esc_html_e( 'Unpaid count', 'mm-register' ); ?></div>
			<div class="mmr-card-value"><?php echo (int) $unpaid_count; ?></div>
		</div>
		<div class="mmr-card">
			<div class="mmr-card-label"><?php esc_html_e( 'Oldest unpaid', 'mm-register' ); ?></div>
			<div class="mmr-card-value"><?php echo $oldest_unpaid ? esc_html( wp_date( 'j.m.Y', strtotime( $oldest_unpaid ) ) ) : '—'; ?></div>
		</div>
	</div>

	<form method="get" class="mmr-filterbar">
		<input type="hidden" name="page" value="mmr-receivables">
		<input type="text" name="payer" value="<?php echo esc_attr( $filters['payer'] ); ?>" placeholder="<?php esc_attr_e( 'Payer…', 'mm-register' ); ?>">
		<input type="month" name="period" value="<?php echo esc_attr( $filters['period'] ); ?>">
		<input type="number" name="year" value="<?php echo $filters['year'] ? esc_attr( $filters['year'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'Year', 'mm-register' ); ?>" class="small-text">
		<select name="paid">
			<option value="all" <?php selected( $filters['paid'], 'all' ); ?>><?php esc_html_e( 'All', 'mm-register' ); ?></option>
			<option value="unpaid" <?php selected( $filters['paid'], 'unpaid' ); ?>><?php esc_html_e( 'Unpaid', 'mm-register' ); ?></option>
			<option value="paid" <?php selected( $filters['paid'], 'paid' ); ?>><?php esc_html_e( 'Paid', 'mm-register' ); ?></option>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mm-register' ); ?></button>
		<a class="button" href="<?php echo esc_url( mmr_page_url( 'receivables' ) ); ?>"><?php esc_html_e( 'Reset', 'mm-register' ); ?></a>
		<button type="button" class="button button-primary mmr-open-modal" data-modal="mmr-receivable-modal" data-reset="1"><?php esc_html_e( '+ Add entry', 'mm-register' ); ?></button>
	</form>

	<table class="widefat striped mmr-table mmr-receivables-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Type', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Number', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Payer', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Period', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'KSeF', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Sent', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Paid', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Paid on', 'mm-register' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="11"><?php esc_html_e( 'No settlements match.', 'mm-register' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $row ) :
				$json = wp_json_encode( array(
					'id'          => (int) $row->id,
					'doc_type'    => $row->doc_type,
					'doc_number'  => $row->doc_number,
					'doc_date'    => $row->doc_date,
					'payer_label' => $row->payer_label,
					'class_id'    => (int) $row->class_id,
					'period'      => $row->period,
					'amount'      => $row->amount,
					'ksef'        => (int) $row->ksef,
					'sent'        => (int) $row->sent,
					'paid'        => (int) $row->paid,
					'paid_on'     => $row->paid_on,
					'notes'       => $row->notes,
				) );
				?>
				<tr class="<?php echo $row->paid ? 'mmr-paid' : 'mmr-unpaid'; ?>" data-id="<?php echo esc_attr( $row->id ); ?>">
					<td><?php echo $row->doc_date ? esc_html( wp_date( 'j.m.Y', strtotime( $row->doc_date ) ) ) : '—'; ?></td>
					<td><?php echo esc_html( $row->doc_type ); ?></td>
					<td class="mmr-muted"><?php echo esc_html( $row->doc_number ); ?></td>
					<td><strong><?php echo esc_html( $row->payer_label ); ?></strong></td>
					<td><?php echo esc_html( $row->period ); ?></td>
					<td><?php echo mmr_money_esc( $row->amount ); ?></td>
					<td><input type="checkbox" class="mmr-toggle" data-field="ksef" <?php checked( $row->ksef, 1 ); ?>></td>
					<td><input type="checkbox" class="mmr-toggle" data-field="sent" <?php checked( $row->sent, 1 ); ?>></td>
					<td><input type="checkbox" class="mmr-toggle" data-field="paid" <?php checked( $row->paid, 1 ); ?>></td>
					<td class="mmr-paid-on"><?php echo $row->paid_on ? esc_html( wp_date( 'j.m.Y', strtotime( $row->paid_on ) ) ) : '—'; ?></td>
					<td><button type="button" class="button button-small mmr-open-modal" data-modal="mmr-receivable-modal" data-json="<?php echo esc_attr( $json ); ?>"><?php esc_html_e( 'Edit', 'mm-register' ); ?></button></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Receivable modal -->
	<div class="mmr-modal" id="mmr-receivable-modal" hidden>
		<div class="mmr-modal-box">
			<h2 class="mmr-modal-title"><?php esc_html_e( 'Settlement entry', 'mm-register' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'mmr_form' ); ?>
				<input type="hidden" name="mmr_action" value="save_settlement">
				<input type="hidden" name="id" value="0" data-field="id">
				<div class="mmr-form-grid">
					<p><label><?php esc_html_e( 'Doc type', 'mm-register' ); ?><br><input type="text" name="doc_type" data-field="doc_type" class="regular-text"></label></p>
					<p><label><?php esc_html_e( 'Doc number', 'mm-register' ); ?><br><input type="text" name="doc_number" data-field="doc_number" class="regular-text"></label></p>
					<p><label><?php esc_html_e( 'Doc date', 'mm-register' ); ?><br><input type="date" name="doc_date" data-field="doc_date"></label></p>
					<p><label><?php esc_html_e( 'Period (YYYY-MM)', 'mm-register' ); ?><br><input type="month" name="period" data-field="period"></label></p>
					<p><label><?php esc_html_e( 'Payer label', 'mm-register' ); ?><br><input type="text" name="payer_label" data-field="payer_label" class="regular-text"></label></p>
					<p><label><?php esc_html_e( 'Amount (zł)', 'mm-register' ); ?><br><input type="text" name="amount" data-field="amount" class="regular-text"></label></p>
					<p><label><?php esc_html_e( 'Linked class (optional)', 'mm-register' ); ?><br>
						<select name="class_id" data-field="class_id">
							<option value="0">—</option>
							<?php foreach ( $classes as $class ) : ?>
								<option value="<?php echo esc_attr( $class->id ); ?>"><?php echo esc_html( $class->name ); ?></option>
							<?php endforeach; ?>
						</select></label></p>
					<p><label><?php esc_html_e( 'Paid on', 'mm-register' ); ?><br><input type="date" name="paid_on" data-field="paid_on"></label></p>
				</div>
				<p>
					<label><input type="checkbox" name="ksef" value="1" data-field="ksef"> <?php esc_html_e( 'KSeF', 'mm-register' ); ?></label>
					<label style="margin-left:1em;"><input type="checkbox" name="sent" value="1" data-field="sent"> <?php esc_html_e( 'Sent', 'mm-register' ); ?></label>
					<label style="margin-left:1em;"><input type="checkbox" name="paid" value="1" data-field="paid"> <?php esc_html_e( 'Paid', 'mm-register' ); ?></label>
				</p>
				<p><label><?php esc_html_e( 'Notes', 'mm-register' ); ?><br><textarea name="notes" data-field="notes" rows="2" class="large-text"></textarea></label></p>
				<p class="mmr-modal-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'mm-register' ); ?></button>
					<button type="button" class="button mmr-close-modal"><?php esc_html_e( 'Cancel', 'mm-register' ); ?></button>
					<button type="submit" class="button mmr-delete-btn" name="mmr_action" value="delete_settlement" formnovalidate onclick="return confirm('<?php echo esc_js( __( 'Delete this entry?', 'mm-register' ) ); ?>');"><?php esc_html_e( 'Delete', 'mm-register' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>
