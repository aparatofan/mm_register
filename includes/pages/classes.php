<?php
/**
 * Classes page — standalone list + add/edit modal with members repeater.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification -- read-only GET filters.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'active';
$type_filter = isset( $_GET['client_type'] ) ? sanitize_text_field( wp_unslash( $_GET['client_type'] ) ) : '';
// phpcs:enable
if ( ! in_array( $status, array( 'active', 'archived', 'all' ), true ) ) {
	$status = 'active';
}

$classes = MMR_Data::classes( array(
	'status'      => $status,
	'search'      => $search,
	'client_type' => $type_filter,
) );
$all_active_students = MMR_Data::students( array( 'status' => 'active' ) );
$client_types = MMR_Data::class_field_values( 'client_type' );
$venues = MMR_Data::class_field_values( 'venue' );
$settle_methods = MMR_Data::class_field_values( 'settle_method' );

// Filters echoed back by row-action forms so archiving keeps the current view.
$filter_args = array( 's' => $search, 'status' => $status, 'client_type' => $type_filter );
?>
<div class="mmr-wrap mmr-classes">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Classes', 'mm-register' ); ?></h1>

	<div class="mmr-toolbar">
		<form method="get" class="mmr-filterbar">
			<input type="hidden" name="page" value="mmr-classes">
			<label class="screen-reader-text" for="mmr-class-search"><?php esc_html_e( 'Search classes', 'mm-register' ); ?></label>
			<input type="search" id="mmr-class-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search name…', 'mm-register' ); ?>">
			<select name="client_type">
				<option value=""><?php esc_html_e( 'All client types', 'mm-register' ); ?></option>
				<?php foreach ( $client_types as $ct ) : ?>
					<option value="<?php echo esc_attr( $ct ); ?>" <?php selected( $type_filter, $ct ); ?>><?php echo esc_html( $ct ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="status">
				<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'mm-register' ); ?></option>
				<option value="archived" <?php selected( $status, 'archived' ); ?>><?php esc_html_e( 'Archived', 'mm-register' ); ?></option>
				<option value="all" <?php selected( $status, 'all' ); ?>><?php esc_html_e( 'All', 'mm-register' ); ?></option>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'mm-register' ); ?></button>
		</form>
		<button type="button" class="button button-primary mmr-open-modal" data-modal="mmr-class-modal" data-reset="1"><?php esc_html_e( '+ Add class', 'mm-register' ); ?></button>
	</div>

	<div class="mmr-quickadd">
		<h3><?php esc_html_e( 'Quick add: new 1-on-1', 'mm-register' ); ?></h3>
		<form method="post" class="mmr-quickadd-form">
			<?php wp_nonce_field( 'mmr_form' ); ?>
			<input type="hidden" name="mmr_action" value="quick_add_1on1">
			<input type="text" name="name" placeholder="<?php esc_attr_e( 'Student / class name', 'mm-register' ); ?>" required>
			<input type="text" name="fee" class="mmr-input-fee" inputmode="decimal" placeholder="<?php esc_attr_e( 'Fee', 'mm-register' ); ?>">
			<input type="text" name="tbt_monthly_fee" class="mmr-input-fee" inputmode="decimal" placeholder="<?php esc_attr_e( 'TBT', 'mm-register' ); ?>">
			<input type="email" name="email" placeholder="<?php esc_attr_e( 'Email (optional)', 'mm-register' ); ?>">
			<input type="text" name="venue" list="mmr-venues" placeholder="<?php esc_attr_e( 'Venue', 'mm-register' ); ?>">
			<input type="text" name="settle_method" list="mmr-settle-methods" placeholder="<?php esc_attr_e( 'Settle', 'mm-register' ); ?>">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Create student + class', 'mm-register' ); ?></button>
		</form>
	</div>

	<table class="widefat striped mmr-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Fee', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Client type', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Venue', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Settle method', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Members', 'mm-register' ); ?></th>
				<th><?php esc_html_e( 'Status', 'mm-register' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $classes ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No classes found.', 'mm-register' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $classes as $class ) :
				$members = MMR_Data::class_members( $class->id );
				$member_names = wp_list_pluck( $members, 'student_name' );
				$is_archived = ( 'archived' === $class->status );
				$member_json = array();
				foreach ( $members as $member ) {
					$member_json[] = array(
						'student_id' => (int) $member->student_id,
						'name'       => $member->student_name,
						'share'      => null === $member->share_per_lesson ? '' : $member->share_per_lesson,
					);
				}
				$json = wp_json_encode( array(
					'id'            => (int) $class->id,
					'name'          => $class->name,
					'fee'           => $class->fee,
					'client_type'   => $class->client_type,
					'venue'         => $class->venue,
					'settle_method' => $class->settle_method,
					'notes'         => $class->notes,
					'status'        => $class->status,
					'sort_name'     => $class->sort_name,
					'members'       => $member_json,
				) );
				?>
				<tr class="<?php echo $is_archived ? 'mmr-archived' : ''; ?>">
					<td><strong><?php echo esc_html( $class->name ); ?></strong></td>
					<td><?php echo mmr_money_esc( $class->fee ); ?></td>
					<td><?php echo esc_html( $class->client_type ); ?></td>
					<td><?php echo esc_html( $class->venue ); ?></td>
					<td><?php echo esc_html( $class->settle_method ); ?></td>
					<td>
						<span title="<?php echo esc_attr( implode( ', ', $member_names ) ); ?>"><?php echo count( $members ); ?></span>
						<?php if ( $member_names ) : ?>
							<div class="mmr-muted mmr-small"><?php echo esc_html( implode( ', ', $member_names ) ); ?></div>
						<?php endif; ?>
					</td>
					<td><?php echo $is_archived ? esc_html__( 'archived', 'mm-register' ) : esc_html__( 'active', 'mm-register' ); ?></td>
					<td class="mmr-row-actions">
						<button type="button" class="button button-small mmr-open-modal" data-modal="mmr-class-modal" data-json="<?php echo esc_attr( $json ); ?>"><?php esc_html_e( 'Edit', 'mm-register' ); ?></button>
						<form method="post" class="mmr-inline-form">
							<?php wp_nonce_field( 'mmr_form' ); ?>
							<input type="hidden" name="mmr_action" value="toggle_class_status">
							<input type="hidden" name="id" value="<?php echo esc_attr( $class->id ); ?>">
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

	<datalist id="mmr-client-types">
		<?php foreach ( $client_types as $ct ) : ?>
			<option value="<?php echo esc_attr( $ct ); ?>"></option>
		<?php endforeach; ?>
	</datalist>
	<datalist id="mmr-venues">
		<?php foreach ( $venues as $v ) : ?>
			<option value="<?php echo esc_attr( $v ); ?>"></option>
		<?php endforeach; ?>
	</datalist>
	<datalist id="mmr-settle-methods">
		<?php foreach ( $settle_methods as $sm ) : ?>
			<option value="<?php echo esc_attr( $sm ); ?>"></option>
		<?php endforeach; ?>
	</datalist>

	<!-- Class modal -->
	<div class="mmr-modal-overlay" id="mmr-class-modal" hidden>
		<div class="mmr-modal" role="dialog" aria-modal="true" aria-labelledby="mmr-class-modal-title">
			<div class="mmr-modal__body">
				<h2 class="mmr-modal-title" id="mmr-class-modal-title"><?php esc_html_e( 'Class', 'mm-register' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'mmr_form' ); ?>
					<input type="hidden" name="mmr_action" value="save_class">
					<input type="hidden" name="id" value="0" data-field="id">
					<div class="mmr-form-grid">
						<div class="mmr-field">
							<label for="mmr-class-name"><?php esc_html_e( 'Name', 'mm-register' ); ?> *</label>
							<input type="text" id="mmr-class-name" name="name" data-field="name" required>
						</div>
						<div class="mmr-field">
							<label for="mmr-class-fee"><?php esc_html_e( 'Fee (zł)', 'mm-register' ); ?></label>
							<input type="text" id="mmr-class-fee" name="fee" data-field="fee" inputmode="decimal">
						</div>
						<div class="mmr-field">
							<label for="mmr-class-type"><?php esc_html_e( 'Client type', 'mm-register' ); ?></label>
							<input type="text" id="mmr-class-type" name="client_type" data-field="client_type" list="mmr-client-types">
						</div>
						<div class="mmr-field">
							<label for="mmr-class-venue"><?php esc_html_e( 'Venue', 'mm-register' ); ?></label>
							<input type="text" id="mmr-class-venue" name="venue" data-field="venue" list="mmr-venues" placeholder="online / office / other">
						</div>
						<div class="mmr-field">
							<label for="mmr-class-settle"><?php esc_html_e( 'Settle method', 'mm-register' ); ?></label>
							<input type="text" id="mmr-class-settle" name="settle_method" data-field="settle_method" list="mmr-settle-methods" placeholder="invoice / REVOLUT / rozliczenie">
						</div>
						<div class="mmr-field">
							<label for="mmr-class-sort"><?php esc_html_e( 'Sort name (optional)', 'mm-register' ); ?></label>
							<input type="text" id="mmr-class-sort" name="sort_name" data-field="sort_name">
						</div>
						<div class="mmr-field">
							<label for="mmr-class-status"><?php esc_html_e( 'Status', 'mm-register' ); ?></label>
							<select id="mmr-class-status" name="status" data-field="status">
								<option value="active"><?php esc_html_e( 'active', 'mm-register' ); ?></option>
								<option value="archived"><?php esc_html_e( 'archived', 'mm-register' ); ?></option>
							</select>
						</div>
						<div class="mmr-field" aria-hidden="true"></div>
						<div class="mmr-field mmr-field--full">
							<label for="mmr-class-notes"><?php esc_html_e( 'Notes', 'mm-register' ); ?></label>
							<textarea id="mmr-class-notes" name="notes" data-field="notes" rows="2"></textarea>
						</div>
					</div>

					<h3><?php esc_html_e( 'Members', 'mm-register' ); ?></h3>
					<p class="mmr-hint"><?php esc_html_e( 'Add each member student. Leave "share" empty for an equal split of the class fee.', 'mm-register' ); ?></p>
					<div class="mmr-member-row mmr-member-row--head">
						<span><?php esc_html_e( 'Student', 'mm-register' ); ?></span>
						<span><?php esc_html_e( 'Share/lesson (zł)', 'mm-register' ); ?></span>
						<span></span>
					</div>
					<div id="mmr-members-rows"></div>
					<p><button type="button" class="button" id="mmr-add-member"><?php esc_html_e( '+ Add member', 'mm-register' ); ?></button></p>

					<template id="mmr-member-row-template">
						<div class="mmr-member-row">
							<select name="member_student_id[]" class="mmr-member-select">
								<option value="0">—</option>
								<?php foreach ( $all_active_students as $s ) : ?>
									<option value="<?php echo esc_attr( $s->id ); ?>"><?php echo esc_html( $s->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" name="member_share[]" class="mmr-member-share" inputmode="decimal" placeholder="<?php esc_attr_e( 'equal', 'mm-register' ); ?>">
							<button type="button" class="button-link mmr-remove-member" aria-label="<?php esc_attr_e( 'Remove member', 'mm-register' ); ?>">✕</button>
						</div>
					</template>

					<p class="mmr-modal-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'mm-register' ); ?></button>
						<button type="button" class="button mmr-close-modal"><?php esc_html_e( 'Cancel', 'mm-register' ); ?></button>
					</p>
				</form>
			</div>
		</div>
	</div>
</div>
