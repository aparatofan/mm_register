<?php
/**
 * Students & Classes page — two-tab CRUD.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'students'; // phpcs:ignore WordPress.Security.NonceVerification
if ( ! in_array( $tab, array( 'students', 'classes' ), true ) ) {
	$tab = 'students';
}
$show_archived = ! empty( $_GET['archived'] ); // phpcs:ignore WordPress.Security.NonceVerification
$status_filter = $show_archived ? 'all' : 'active';

$students = MMR_Data::students( array( 'status' => $status_filter ) );
$classes = MMR_Data::classes( array( 'status' => $status_filter ) );
$all_active_students = MMR_Data::students( array( 'status' => 'active' ) );
$client_types = MMR_Data::client_types();
?>
<div class="wrap mmr-wrap mmr-students-classes">
	<h1 class="mmr-page-title"><?php esc_html_e( 'Students & Classes', 'mm-register' ); ?></h1>

	<h2 class="nav-tab-wrapper mmr-tabs">
		<a href="<?php echo esc_url( mmr_page_url( 'students-classes', array( 'tab' => 'students' ) ) ); ?>" class="nav-tab <?php echo 'students' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Students', 'mm-register' ); ?></a>
		<a href="<?php echo esc_url( mmr_page_url( 'students-classes', array( 'tab' => 'classes' ) ) ); ?>" class="nav-tab <?php echo 'classes' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Classes', 'mm-register' ); ?></a>
	</h2>

	<p class="mmr-filterline">
		<?php if ( $show_archived ) : ?>
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'students-classes', array( 'tab' => $tab ) ) ); ?>"><?php esc_html_e( 'Hide archived', 'mm-register' ); ?></a>
		<?php else : ?>
			<a class="button" href="<?php echo esc_url( mmr_page_url( 'students-classes', array( 'tab' => $tab, 'archived' => 1 ) ) ); ?>"><?php esc_html_e( 'Show archived', 'mm-register' ); ?></a>
		<?php endif; ?>
	</p>

	<?php if ( 'students' === $tab ) : ?>

		<p><button type="button" class="button button-primary mmr-open-modal" data-modal="mmr-student-modal" data-reset="1"><?php esc_html_e( '+ Add student', 'mm-register' ); ?></button></p>

		<table class="widefat striped mmr-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Email', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'TBT monthly', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Classes', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Status', 'mm-register' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $students ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No students yet.', 'mm-register' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $students as $student ) :
					$class_names = MMR_Data::student_class_names( $student->id );
					$json = wp_json_encode( array(
						'id'              => (int) $student->id,
						'name'            => $student->name,
						'email'           => $student->email,
						'tbt_monthly_fee' => $student->tbt_monthly_fee,
						'notes'           => $student->notes,
						'status'          => $student->status,
					) );
					?>
					<tr class="<?php echo 'archived' === $student->status ? 'mmr-archived' : ''; ?>">
						<td><strong><?php echo esc_html( $student->name ); ?></strong></td>
						<td><?php echo esc_html( $student->email ); ?></td>
						<td><?php echo mmr_money_esc( $student->tbt_monthly_fee ); ?></td>
						<td class="mmr-muted"><?php echo esc_html( implode( ', ', $class_names ) ); ?></td>
						<td><?php echo esc_html( $student->status ); ?></td>
						<td><button type="button" class="button button-small mmr-open-modal" data-modal="mmr-student-modal" data-json="<?php echo esc_attr( $json ); ?>"><?php esc_html_e( 'Edit', 'mm-register' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Student modal -->
		<div class="mmr-modal" id="mmr-student-modal" hidden>
			<div class="mmr-modal-box">
				<h2 class="mmr-modal-title"><?php esc_html_e( 'Student', 'mm-register' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'mmr_form' ); ?>
					<input type="hidden" name="mmr_action" value="save_student">
					<input type="hidden" name="id" value="0" data-field="id">
					<p><label><?php esc_html_e( 'Name', 'mm-register' ); ?><br><input type="text" name="name" data-field="name" class="regular-text" required></label></p>
					<p><label><?php esc_html_e( 'Email', 'mm-register' ); ?><br><input type="email" name="email" data-field="email" class="regular-text"></label></p>
					<p><label><?php esc_html_e( 'TBT monthly fee (zł)', 'mm-register' ); ?><br><input type="text" name="tbt_monthly_fee" data-field="tbt_monthly_fee" class="small-text"></label></p>
					<p><label><?php esc_html_e( 'Notes', 'mm-register' ); ?><br><textarea name="notes" data-field="notes" rows="3" class="large-text"></textarea></label></p>
					<p><label><?php esc_html_e( 'Status', 'mm-register' ); ?><br>
						<select name="status" data-field="status">
							<option value="active"><?php esc_html_e( 'active', 'mm-register' ); ?></option>
							<option value="archived"><?php esc_html_e( 'archived', 'mm-register' ); ?></option>
						</select></label></p>
					<p class="mmr-modal-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'mm-register' ); ?></button>
						<button type="button" class="button mmr-close-modal"><?php esc_html_e( 'Cancel', 'mm-register' ); ?></button>
					</p>
				</form>
			</div>
		</div>

	<?php else : // classes tab. ?>

		<div class="mmr-quickadd">
			<h3><?php esc_html_e( 'Quick add: new 1-on-1', 'mm-register' ); ?></h3>
			<form method="post" class="mmr-quickadd-form">
				<?php wp_nonce_field( 'mmr_form' ); ?>
				<input type="hidden" name="mmr_action" value="quick_add_1on1">
				<input type="text" name="name" placeholder="<?php esc_attr_e( 'Student / class name', 'mm-register' ); ?>" required>
				<input type="text" name="fee" class="small-text" placeholder="<?php esc_attr_e( 'Fee', 'mm-register' ); ?>">
				<input type="text" name="tbt_monthly_fee" class="small-text" placeholder="<?php esc_attr_e( 'TBT', 'mm-register' ); ?>">
				<input type="email" name="email" placeholder="<?php esc_attr_e( 'Email (optional)', 'mm-register' ); ?>">
				<input type="text" name="venue" class="small-text" placeholder="<?php esc_attr_e( 'Venue', 'mm-register' ); ?>">
				<input type="text" name="settle_method" class="small-text" placeholder="<?php esc_attr_e( 'Settle', 'mm-register' ); ?>">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Create student + class', 'mm-register' ); ?></button>
			</form>
		</div>

		<p><button type="button" class="button button-primary mmr-open-modal" data-modal="mmr-class-modal" data-reset="1"><?php esc_html_e( '+ Add class', 'mm-register' ); ?></button></p>

		<table class="widefat striped mmr-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Fee', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Type', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Venue', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Settle', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Members', 'mm-register' ); ?></th>
					<th><?php esc_html_e( 'Status', 'mm-register' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $classes ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No classes yet.', 'mm-register' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $classes as $class ) :
					$members = MMR_Data::class_members( $class->id );
					$member_names = wp_list_pluck( $members, 'student_name' );
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
					<tr class="<?php echo 'archived' === $class->status ? 'mmr-archived' : ''; ?>">
						<td><strong><?php echo esc_html( $class->name ); ?></strong></td>
						<td><?php echo mmr_money_esc( $class->fee ); ?></td>
						<td><?php echo esc_html( $class->client_type ); ?></td>
						<td><?php echo esc_html( $class->venue ); ?></td>
						<td><?php echo esc_html( $class->settle_method ); ?></td>
						<td class="mmr-muted"><?php echo esc_html( implode( ', ', $member_names ) ); ?></td>
						<td><?php echo esc_html( $class->status ); ?></td>
						<td><button type="button" class="button button-small mmr-open-modal" data-modal="mmr-class-modal" data-json="<?php echo esc_attr( $json ); ?>"><?php esc_html_e( 'Edit', 'mm-register' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<datalist id="mmr-client-types">
			<?php foreach ( $client_types as $ct ) : ?>
				<option value="<?php echo esc_attr( $ct ); ?>"></option>
			<?php endforeach; ?>
		</datalist>

		<!-- Class modal -->
		<div class="mmr-modal" id="mmr-class-modal" hidden>
			<div class="mmr-modal-box mmr-modal-wide">
				<h2 class="mmr-modal-title"><?php esc_html_e( 'Class', 'mm-register' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'mmr_form' ); ?>
					<input type="hidden" name="mmr_action" value="save_class">
					<input type="hidden" name="id" value="0" data-field="id">
					<div class="mmr-form-grid">
						<p><label><?php esc_html_e( 'Name', 'mm-register' ); ?><br><input type="text" name="name" data-field="name" class="regular-text" required></label></p>
						<p><label><?php esc_html_e( 'Fee (zł)', 'mm-register' ); ?><br><input type="text" name="fee" data-field="fee" class="small-text"></label></p>
						<p><label><?php esc_html_e( 'Client type', 'mm-register' ); ?><br><input type="text" name="client_type" data-field="client_type" list="mmr-client-types" class="regular-text"></label></p>
						<p><label><?php esc_html_e( 'Venue', 'mm-register' ); ?><br><input type="text" name="venue" data-field="venue" class="regular-text" placeholder="online / office / other"></label></p>
						<p><label><?php esc_html_e( 'Settle method', 'mm-register' ); ?><br><input type="text" name="settle_method" data-field="settle_method" class="regular-text" placeholder="invoice / REVOLUT / rozliczenie"></label></p>
						<p><label><?php esc_html_e( 'Sort name (optional)', 'mm-register' ); ?><br><input type="text" name="sort_name" data-field="sort_name" class="regular-text"></label></p>
						<p><label><?php esc_html_e( 'Status', 'mm-register' ); ?><br>
							<select name="status" data-field="status">
								<option value="active"><?php esc_html_e( 'active', 'mm-register' ); ?></option>
								<option value="archived"><?php esc_html_e( 'archived', 'mm-register' ); ?></option>
							</select></label></p>
					</div>
					<p><label><?php esc_html_e( 'Notes', 'mm-register' ); ?><br><textarea name="notes" data-field="notes" rows="2" class="large-text"></textarea></label></p>

					<h3><?php esc_html_e( 'Members', 'mm-register' ); ?></h3>
					<p class="mmr-hint"><?php esc_html_e( 'Add each member student. Leave "share" empty for an equal split of the class fee.', 'mm-register' ); ?></p>
					<table class="mmr-members-table">
						<thead><tr><th><?php esc_html_e( 'Student', 'mm-register' ); ?></th><th><?php esc_html_e( 'Share/lesson (zł)', 'mm-register' ); ?></th><th></th></tr></thead>
						<tbody id="mmr-members-rows"></tbody>
					</table>
					<p><button type="button" class="button" id="mmr-add-member"><?php esc_html_e( '+ Add member', 'mm-register' ); ?></button></p>

					<template id="mmr-member-row-template">
						<tr class="mmr-member-row">
							<td>
								<select name="member_student_id[]" class="mmr-member-select">
									<option value="0">—</option>
									<?php foreach ( $all_active_students as $s ) : ?>
										<option value="<?php echo esc_attr( $s->id ); ?>"><?php echo esc_html( $s->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input type="text" name="member_share[]" class="small-text mmr-member-share" placeholder="<?php esc_attr_e( 'equal', 'mm-register' ); ?>"></td>
							<td><button type="button" class="button-link mmr-remove-member">✕</button></td>
						</tr>
					</template>

					<p class="mmr-modal-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'mm-register' ); ?></button>
						<button type="button" class="button mmr-close-modal"><?php esc_html_e( 'Cancel', 'mm-register' ); ?></button>
					</p>
				</form>
			</div>
		</div>

	<?php endif; ?>
</div>
