<?php
/**
 * Companies page — standalone list + add/edit modal.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification -- read-only GET filters.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'active';
// phpcs:enable
if ( ! in_array( $status, array( 'active', 'archived', 'all' ), true ) ) {
	$status = 'active';
}

$companies = MMR_Data::companies( array( 'status' => $status, 'search' => $search ) );

// Filters echoed back by row-action forms so archiving keeps the current view.
$filter_args = array( 's' => $search, 'status' => $status );
?>
<div class="mmr-wrap mmr-companies">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Companies', 'mm-register' ); ?></h1>

	<div class="mmr-toolbar">
		<form method="get" class="mmr-filterbar">
			<input type="hidden" name="page" value="mmr-companies">
			<label class="screen-reader-text" for="mmr-company-search"><?php esc_html_e( 'Search companies', 'mm-register' ); ?></label>
			<input type="search" id="mmr-company-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name / NIP…', 'mm-register' ); ?>">
			<select name="status">
				<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'mm-register' ); ?></option>
				<option value="archived" <?php selected( $status, 'archived' ); ?>><?php esc_html_e( 'Archived', 'mm-register' ); ?></option>
				<option value="all" <?php selected( $status, 'all' ); ?>><?php esc_html_e( 'All', 'mm-register' ); ?></option>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mm-register' ); ?></button>
		</form>
		<button type="button" class="button button-primary mmr-open-modal" data-modal="mmr-company-modal" data-reset="1"><?php esc_html_e( '+ Add company', 'mm-register' ); ?></button>
	</div>

	<table class="widefat striped mmr-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'NIP', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Address', 'mm-register' ); ?></th>
				<th class="mmr-num"><?php esc_html_e( 'Classes', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Status', 'mm-register' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $companies ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No companies found.', 'mm-register' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $companies as $company ) :
				$is_archived = ( 'archived' === $company->status );
				$class_count = MMR_Data::company_class_count( $company->id );
				$class_names = $class_count ? MMR_Data::company_class_names( $company->id ) : array();
				$address_first = trim( strtok( (string) $company->address, "\r\n" ) );
				$json = wp_json_encode( array(
					'id'      => (int) $company->id,
					'name'    => $company->name,
					'nip'     => $company->nip,
					'address' => $company->address,
					'notes'   => $company->notes,
					'status'  => $company->status,
				) );
				// Warn before archiving a company still used by active classes.
				$archive_confirm = ( ! $is_archived && $class_count > 0 )
					? sprintf(
						/* translators: 1: company name, 2: comma-separated class names. */
						__( '%1$s is used by these classes: %2$s. Archive it anyway? It will disappear from the class picker but stay on existing classes.', 'mm-register' ),
						$company->name,
						implode( ', ', $class_names )
					)
					: '';
				?>
				<tr class="<?php echo $is_archived ? 'mmr-archived' : ''; ?>">
					<td><strong><?php echo esc_html( $company->name ); ?></strong></td>
					<td><?php echo esc_html( $company->nip ); ?></td>
					<td>
						<span class="mmr-ellipsis" title="<?php echo esc_attr( $company->address ); ?>"><?php echo esc_html( $address_first ); ?></span>
					</td>
					<td class="mmr-num"><?php echo (int) $class_count; ?></td>
					<td><?php echo $is_archived ? esc_html__( 'archived', 'mm-register' ) : esc_html__( 'active', 'mm-register' ); ?></td>
					<td class="mmr-row-actions">
						<button type="button" class="button button-small mmr-open-modal" data-modal="mmr-company-modal" data-json="<?php echo esc_attr( $json ); ?>"><?php esc_html_e( 'Edit', 'mm-register' ); ?></button>
						<form method="post" class="mmr-inline-form"<?php echo $archive_confirm ? ' data-confirm="' . esc_attr( $archive_confirm ) . '"' : ''; ?>>
							<?php wp_nonce_field( 'mmr_form' ); ?>
							<input type="hidden" name="mmr_action" value="toggle_company_status">
							<input type="hidden" name="id" value="<?php echo esc_attr( $company->id ); ?>">
							<input type="hidden" name="status" value="<?php echo $is_archived ? 'active' : 'archived'; ?>">
							<?php foreach ( $filter_args as $fk => $fv ) : ?>
								<input type="hidden" name="filter_<?php echo esc_attr( $fk ); ?>" value="<?php echo esc_attr( $fv ); ?>">
							<?php endforeach; ?>
							<button type="submit" class="button-link"><?php echo $is_archived ? esc_html__( 'Restore', 'mm-register' ) : esc_html__( 'Archive', 'mm-register' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Company modal -->
	<div class="mmr-modal-overlay" id="mmr-company-modal" hidden>
		<div class="mmr-modal" role="dialog" aria-modal="true" aria-labelledby="mmr-company-modal-title">
			<div class="mmr-modal__body">
				<h2 class="mmr-modal-title" id="mmr-company-modal-title"><?php esc_html_e( 'Company', 'mm-register' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'mmr_form' ); ?>
					<input type="hidden" name="mmr_action" value="save_company">
					<input type="hidden" name="id" value="0" data-field="id">
					<div class="mmr-form-grid">
						<div class="mmr-field mmr-field--full">
							<label for="mmr-company-name"><?php esc_html_e( 'Name', 'mm-register' ); ?> *</label>
							<input type="text" id="mmr-company-name" name="name" data-field="name" required>
						</div>
						<div class="mmr-field">
							<label for="mmr-company-nip"><?php esc_html_e( 'NIP', 'mm-register' ); ?></label>
							<input type="text" id="mmr-company-nip" name="nip" data-field="nip">
						</div>
						<div class="mmr-field">
							<label for="mmr-company-status"><?php esc_html_e( 'Status', 'mm-register' ); ?></label>
							<select id="mmr-company-status" name="status" data-field="status">
								<option value="active"><?php esc_html_e( 'active', 'mm-register' ); ?></option>
								<option value="archived"><?php esc_html_e( 'archived', 'mm-register' ); ?></option>
							</select>
						</div>
						<div class="mmr-field mmr-field--full">
							<label for="mmr-company-address"><?php esc_html_e( 'Address', 'mm-register' ); ?></label>
							<textarea id="mmr-company-address" name="address" data-field="address" rows="4" placeholder="<?php esc_attr_e( 'As it should appear on an invoice', 'mm-register' ); ?>"></textarea>
						</div>
						<div class="mmr-field mmr-field--full">
							<label for="mmr-company-notes"><?php esc_html_e( 'Notes', 'mm-register' ); ?></label>
							<textarea id="mmr-company-notes" name="notes" data-field="notes" rows="3" placeholder="<?php esc_attr_e( 'PO numbers, invoice portal, contact person…', 'mm-register' ); ?>"></textarea>
						</div>
					</div>
					<p class="mmr-modal-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'mm-register' ); ?></button>
						<button type="button" class="button mmr-close-modal"><?php esc_html_e( 'Cancel', 'mm-register' ); ?></button>
					</p>
				</form>
			</div>
		</div>
	</div>
</div>
