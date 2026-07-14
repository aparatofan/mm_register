<?php
/**
 * Students page — standalone list + add/edit modal.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification -- read-only GET filters.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'active';
$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'name';
// phpcs:enable
if ( ! in_array( $status, array( 'active', 'archived', 'all' ), true ) ) {
	$status = 'active';
}
if ( ! in_array( $orderby, array( 'name', 'level' ), true ) ) {
	$orderby = 'name';
}

$students = MMR_Data::students( array( 'status' => $status, 'search' => $search, 'orderby' => $orderby ) );
$cefr_levels = array( 'A1', 'A2', 'B1', 'B2', 'C1', 'C2' );

// Filters echoed back by row-action forms so archiving keeps the current view.
$filter_args = array( 's' => $search, 'status' => $status );
if ( 'name' !== $orderby ) {
	$filter_args['orderby'] = $orderby;
}

/**
 * Header link that toggles the sort column.
 *
 * @param string $key     Order key.
 * @param string $label   Column label.
 * @param string $search  Current search term.
 * @param string $status  Current status filter.
 * @param string $orderby Current sort column.
 * @return string Escaped anchor HTML.
 */
if ( ! function_exists( 'mmr_students_sort_link' ) ) {
	function mmr_students_sort_link( $key, $label, $search, $status, $orderby ) {
		$args = array( 's' => $search, 'status' => $status, 'orderby' => $key );
		$arrow = ( $orderby === $key ) ? ' ▾' : '';
		return '<a href="' . esc_url( mmr_page_url( 'students', $args ) ) . '">' . esc_html( $label ) . $arrow . '</a>';
	}
}
?>
<div class="mmr-wrap mmr-students">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Students', 'mm-register' ); ?></h1>

	<div class="mmr-toolbar">
		<form method="get" class="mmr-filterbar">
			<input type="hidden" name="page" value="mmr-students">
			<?php if ( 'name' !== $orderby ) : ?>
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>">
			<?php endif; ?>
			<label class="screen-reader-text" for="mmr-student-search"><?php esc_html_e( 'Search students', 'mm-register' ); ?></label>
			<input type="search" id="mmr-student-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name / email / phone…', 'mm-register' ); ?>">
			<select name="status">
				<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'mm-register' ); ?></option>
				<option value="archived" <?php selected( $status, 'archived' ); ?>><?php esc_html_e( 'Archived', 'mm-register' ); ?></option>
				<option value="all" <?php selected( $status, 'all' ); ?>><?php esc_html_e( 'All', 'mm-register' ); ?></option>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mm-register' ); ?></button>
		</form>
		<button type="button" class="button button-primary mmr-open-modal" data-modal="mmr-student-modal" data-reset="1"><?php esc_html_e( '+ Add student', 'mm-register' ); ?></button>
	</div>

	<table class="widefat striped mmr-table">
		<thead>
			<tr>
				<th><?php echo mmr_students_sort_link( 'name', __( 'Name', 'mm-register' ), $search, $status, $orderby ); // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
				<th><?php echo mmr_students_sort_link( 'level', __( 'Level', 'mm-register' ), $search, $status, $orderby ); // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
				<th><?php esc_html_e( 'Phone', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Email', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Status', 'mm-register' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $students ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No students found.', 'mm-register' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $students as $student ) :
				$is_archived = ( 'archived' === $student->status );
				$json = wp_json_encode( array(
					'id'              => (int) $student->id,
					'name'            => $student->name,
					'email'           => $student->email,
					'email_2'         => $student->email_2,
					'phone'           => $student->phone,
					'level'           => $student->level,
					'tbt_monthly_fee' => $student->tbt_monthly_fee,
					'notes'           => $student->notes,
					'status'          => $student->status,
				) );
				?>
				<tr class="<?php echo $is_archived ? 'mmr-archived' : ''; ?>">
					<td><strong><?php echo esc_html( $student->name ); ?></strong></td>
					<td><?php echo esc_html( $student->level ); ?></td>
					<td><?php echo esc_html( $student->phone ); ?></td>
					<td>
						<?php echo esc_html( $student->email ); ?>
						<?php if ( ! empty( $student->email_2 ) ) : ?>
							<div class="mmr-muted mmr-small"><?php echo esc_html( $student->email_2 ); ?></div>
						<?php endif; ?>
					</td>
					<td><?php echo $is_archived ? esc_html__( 'archived', 'mm-register' ) : esc_html__( 'active', 'mm-register' ); ?></td>
					<td class="mmr-row-actions">
						<button type="button" class="button button-small mmr-open-modal" data-modal="mmr-student-modal" data-json="<?php echo esc_attr( $json ); ?>"><?php esc_html_e( 'Edit', 'mm-register' ); ?></button>
						<form method="post" class="mmr-inline-form">
							<?php wp_nonce_field( 'mmr_form' ); ?>
							<input type="hidden" name="mmr_action" value="toggle_student_status">
							<input type="hidden" name="id" value="<?php echo esc_attr( $student->id ); ?>">
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

	<datalist id="mmr-levels">
		<?php foreach ( $cefr_levels as $lvl ) : ?>
			<option value="<?php echo esc_attr( $lvl ); ?>"></option>
		<?php endforeach; ?>
	</datalist>

	<!-- Student modal -->
	<div class="mmr-modal-overlay" id="mmr-student-modal" hidden>
		<div class="mmr-modal" role="dialog" aria-modal="true" aria-labelledby="mmr-student-modal-title">
			<div class="mmr-modal__body">
				<h2 class="mmr-modal-title" id="mmr-student-modal-title"><?php esc_html_e( 'Student', 'mm-register' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'mmr_form' ); ?>
					<input type="hidden" name="mmr_action" value="save_student">
					<input type="hidden" name="id" value="0" data-field="id">
					<div class="mmr-form-grid">
						<div class="mmr-field">
							<label for="mmr-student-name"><?php esc_html_e( 'Name', 'mm-register' ); ?> *</label>
							<input type="text" id="mmr-student-name" name="name" data-field="name" required>
						</div>
						<div class="mmr-field">
							<label for="mmr-student-level"><?php esc_html_e( 'Level', 'mm-register' ); ?></label>
							<input type="text" id="mmr-student-level" name="level" data-field="level" list="mmr-levels" placeholder="A1–C2, B2+, Matura…">
						</div>
						<div class="mmr-field">
							<label for="mmr-student-email"><?php esc_html_e( 'Email', 'mm-register' ); ?></label>
							<input type="email" id="mmr-student-email" name="email" data-field="email">
						</div>
						<div class="mmr-field">
							<label for="mmr-student-email2"><?php esc_html_e( 'Email 2', 'mm-register' ); ?></label>
							<input type="email" id="mmr-student-email2" name="email_2" data-field="email_2">
						</div>
						<div class="mmr-field">
							<label for="mmr-student-phone"><?php esc_html_e( 'Phone', 'mm-register' ); ?></label>
							<input type="text" id="mmr-student-phone" name="phone" data-field="phone" inputmode="tel">
						</div>
						<div class="mmr-field">
							<label for="mmr-student-tbt"><?php esc_html_e( 'TBT monthly fee (zł)', 'mm-register' ); ?></label>
							<input type="text" id="mmr-student-tbt" name="tbt_monthly_fee" data-field="tbt_monthly_fee" inputmode="decimal">
						</div>
						<div class="mmr-field">
							<label for="mmr-student-status"><?php esc_html_e( 'Status', 'mm-register' ); ?></label>
							<select id="mmr-student-status" name="status" data-field="status">
								<option value="active"><?php esc_html_e( 'active', 'mm-register' ); ?></option>
								<option value="archived"><?php esc_html_e( 'archived', 'mm-register' ); ?></option>
							</select>
						</div>
						<div class="mmr-field" aria-hidden="true"></div>
						<div class="mmr-field mmr-field--full">
							<label for="mmr-student-notes"><?php esc_html_e( 'Notes', 'mm-register' ); ?></label>
							<textarea id="mmr-student-notes" name="notes" data-field="notes" rows="3"></textarea>
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
