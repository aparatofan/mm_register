<?php
/**
 * Projections page — projected vs actual revenue by month.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$projections = MMR_Data::projections();
?>
<div class="wrap mmr-wrap mmr-projections">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Projections', 'mm-register' ); ?></h1>

	<p><button type="button" class="button button-primary mmr-open-modal" data-modal="mmr-projection-modal" data-reset="1"><?php esc_html_e( '+ Add / edit month', 'mm-register' ); ?></button></p>

	<table class="widefat striped mmr-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Month', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Projected revenue', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Projected expenses', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Projected diff', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Actual revenue', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Notes', 'mm-register' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $projections ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No projections yet.', 'mm-register' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $projections as $p ) :
				$diff = (float) $p->projected - (float) $p->expenses;
				$actual = MMR_Data::month_actual_revenue( $p->period );
				$json = wp_json_encode( array(
					'id'        => (int) $p->id,
					'period'    => $p->period,
					'projected' => $p->projected,
					'expenses'  => $p->expenses,
					'notes'     => $p->notes,
				) );
				?>
				<tr>
					<td><strong><?php echo esc_html( mmr_period_label( $p->period ) ); ?></strong> <span class="mmr-muted mmr-small"><?php echo esc_html( $p->period ); ?></span></td>
					<td><?php echo mmr_money_esc( $p->projected ); ?></td>
					<td><?php echo mmr_money_esc( $p->expenses ); ?></td>
					<td class="<?php echo $diff < 0 ? 'mmr-neg' : ''; ?>"><?php echo mmr_money_esc( $diff ); ?></td>
					<td><strong><?php echo mmr_money_esc( $actual ); ?></strong></td>
					<td class="mmr-muted"><?php echo esc_html( $p->notes ); ?></td>
					<td><button type="button" class="button button-small mmr-open-modal" data-modal="mmr-projection-modal" data-json="<?php echo esc_attr( $json ); ?>"><?php esc_html_e( 'Edit', 'mm-register' ); ?></button></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Projection modal -->
	<div class="mmr-modal" id="mmr-projection-modal" hidden>
		<div class="mmr-modal-box">
			<h2 class="mmr-modal-title"><?php esc_html_e( 'Projection', 'mm-register' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'mmr_form' ); ?>
				<input type="hidden" name="mmr_action" value="save_projection">
				<input type="hidden" name="id" value="0" data-field="id">
				<p><label><?php esc_html_e( 'Month', 'mm-register' ); ?><br><input type="month" name="period" data-field="period" required></label></p>
				<p><label><?php esc_html_e( 'Projected revenue (zł)', 'mm-register' ); ?><br><input type="text" name="projected" data-field="projected" class="regular-text"></label></p>
				<p><label><?php esc_html_e( 'Projected expenses (zł)', 'mm-register' ); ?><br><input type="text" name="expenses" data-field="expenses" class="regular-text"></label></p>
				<p><label><?php esc_html_e( 'Notes', 'mm-register' ); ?><br><textarea name="notes" data-field="notes" rows="2" class="large-text"></textarea></label></p>
				<p class="mmr-modal-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'mm-register' ); ?></button>
					<button type="button" class="button mmr-close-modal"><?php esc_html_e( 'Cancel', 'mm-register' ); ?></button>
					<button type="submit" class="button mmr-delete-btn" name="mmr_action" value="delete_projection" formnovalidate onclick="return confirm('<?php echo esc_js( __( 'Delete this projection?', 'mm-register' ) ); ?>');"><?php esc_html_e( 'Delete', 'mm-register' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>
