<?php
/**
 * Tools page — import / export / danger zone.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$import_result = get_transient( 'mmr_import_result_' . get_current_user_id() );
if ( $import_result ) {
	delete_transient( 'mmr_import_result_' . get_current_user_id() );
}
$is_empty = MMR_DB::is_empty();
?>
<div class="mmr-wrap mmr-tools">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Tools', 'mm-register' ); ?></h1>

	<div class="mmr-tools-grid">

		<div class="mmr-tool-card">
			<h2><?php esc_html_e( 'Import', 'mm-register' ); ?></h2>
			<p class="mmr-hint"><?php esc_html_e( 'Upload a JSON file in the documented format (docs/import-format.md). Always dry-run first to review counts and warnings. Import refuses to run on non-empty tables unless "merge" is checked.', 'mm-register' ); ?></p>

			<?php if ( ! $is_empty ) : ?>
				<p class="mmr-inline-warning"><?php esc_html_e( 'Tables already contain data. Enable "merge" to import anyway.', 'mm-register' ); ?></p>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'mmr_form' ); ?>
				<input type="hidden" name="mmr_action" value="import_data">
				<p><input type="file" name="import_file" accept="application/json,.json"></p>
				<p><label><?php esc_html_e( 'Or paste JSON:', 'mm-register' ); ?><br><textarea name="import_json" rows="6" class="large-text code" placeholder='{ "students": [], "classes": [], ... }'></textarea></label></p>
				<p><label><input type="checkbox" name="merge" value="1"> <?php esc_html_e( 'Merge into existing data', 'mm-register' ); ?></label></p>
				<p>
					<button type="submit" class="button" name="mode" value="dryrun"><?php esc_html_e( 'Dry run', 'mm-register' ); ?></button>
					<button type="submit" class="button button-primary" name="mode" value="commit" onclick="return confirm('<?php echo esc_js( __( 'Commit this import to the database?', 'mm-register' ) ); ?>');"><?php esc_html_e( 'Commit import', 'mm-register' ); ?></button>
				</p>
			</form>

			<?php if ( is_array( $import_result ) ) : ?>
				<div class="mmr-import-result">
					<h3><?php echo ! empty( $import_result['committed'] ) ? esc_html__( 'Import committed', 'mm-register' ) : esc_html__( 'Dry-run result', 'mm-register' ); ?></h3>
					<?php if ( ! empty( $import_result['error'] ) ) : ?>
						<p class="mmr-inline-warning"><?php echo esc_html( $import_result['error'] ); ?></p>
					<?php endif; ?>
					<table class="mmr-summary-table">
						<?php foreach ( $import_result['counts'] as $key => $count ) :
							$done = isset( $import_result['imported'][ $key ] ) ? $import_result['imported'][ $key ] : null; ?>
							<tr>
								<td><?php echo esc_html( str_replace( '_', ' ', $key ) ); ?></td>
								<td class="mmr-num">
									<?php echo (int) $count; ?>
									<?php if ( null !== $done && ! empty( $import_result['committed'] ) ) : ?>
										<span class="mmr-muted">(<?php echo (int) $done; ?> <?php esc_html_e( 'imported', 'mm-register' ); ?>)</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
					<?php if ( ! empty( $import_result['warnings'] ) ) : ?>
						<h4><?php esc_html_e( 'Warnings', 'mm-register' ); ?></h4>
						<ul class="mmr-warnings">
							<?php foreach ( $import_result['warnings'] as $warning ) : ?>
								<li><?php echo esc_html( $warning ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( ! empty( $import_result['can_commit'] ) && empty( $import_result['committed'] ) ) : ?>
						<form method="post" class="mmr-commit-stored">
							<?php wp_nonce_field( 'mmr_form' ); ?>
							<input type="hidden" name="mmr_action" value="import_data">
							<input type="hidden" name="mode" value="commit">
							<input type="hidden" name="use_stored" value="1">
							<label><input type="checkbox" name="merge" value="1"> <?php esc_html_e( 'Merge into existing data', 'mm-register' ); ?></label>
							<p><button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Commit this dry-run to the database?', 'mm-register' ) ); ?>');"><?php esc_html_e( 'Commit this import', 'mm-register' ); ?></button></p>
							<p class="mmr-hint"><?php esc_html_e( 'Uses the file you just dry-ran — no need to re-select it.', 'mm-register' ); ?></p>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="mmr-tool-card">
			<h2><?php esc_html_e( 'Export', 'mm-register' ); ?></h2>
			<p class="mmr-hint"><?php esc_html_e( 'Download a full JSON backup of every table. This file can be re-imported into an empty install.', 'mm-register' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'mmr_form' ); ?>
				<input type="hidden" name="mmr_action" value="export_data">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Download JSON export', 'mm-register' ); ?></button>
			</form>
		</div>

		<div class="mmr-tool-card mmr-danger">
			<h2><?php esc_html_e( 'Danger zone', 'mm-register' ); ?></h2>
			<p class="mmr-hint"><?php esc_html_e( 'Delete ALL plugin data. This drops and recreates every table. There is no undo — export first.', 'mm-register' ); ?></p>
			<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'This permanently deletes all MM Register data. Continue?', 'mm-register' ) ); ?>');">
				<?php wp_nonce_field( 'mmr_form' ); ?>
				<input type="hidden" name="mmr_action" value="wipe_data">
				<p><label><?php esc_html_e( 'Type DELETE to confirm:', 'mm-register' ); ?><br><input type="text" name="confirm" class="regular-text" autocomplete="off" placeholder="DELETE"></label></p>
				<button type="submit" class="button mmr-delete-btn"><?php esc_html_e( 'Delete all plugin data', 'mm-register' ); ?></button>
			</form>
		</div>

	</div>
</div>
