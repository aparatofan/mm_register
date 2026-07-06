<?php
/**
 * Admin controller: POST handling and page rendering.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the plugin's admin pages and processes their form submissions.
 */
class MMR_Admin {

	/**
	 * Wire the POST handler.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_post' ) );
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * Render helper: guard capability and include a page template.
	 *
	 * @param string $template Filename under includes/pages/.
	 */
	protected function render( $template ) {
		if ( ! current_user_can( MMR_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mm-register' ) );
		}
		$this->render_notice();
		require MMR_PATH . 'includes/pages/' . $template;
	}

	/**
	 * Show a transient admin notice after a redirect.
	 */
	protected function render_notice() {
		if ( empty( $_GET['mmr_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$notice = sanitize_text_field( wp_unslash( $_GET['mmr_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$type = ! empty( $_GET['mmr_error'] ) ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification
		$messages = array(
			'saved'            => __( 'Saved.', 'mm-register' ),
			'deleted'          => __( 'Deleted.', 'mm-register' ),
			'settlement_saved' => __( 'Settlement recorded.', 'mm-register' ),
			'imported'         => __( 'Import complete.', 'mm-register' ),
			'wiped'            => __( 'All plugin data has been deleted.', 'mm-register' ),
			'import_blocked'   => __( 'Tables are not empty. Choose "merge" to import anyway.', 'mm-register' ),
			'import_error'     => __( 'Import failed — see details below.', 'mm-register' ),
			'nothing'          => __( 'Nothing to do.', 'mm-register' ),
		);
		$text = isset( $messages[ $notice ] ) ? $messages[ $notice ] : $notice;
		printf(
			'<div class="notice notice-%s is-dismissible mmr-notice"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $text )
		);
	}

	/** @return void */
	public function render_calendar() {
		$this->render( 'calendar.php' );
	}

	/** @return void */
	public function render_settlement() {
		$this->render( 'settlement.php' );
	}

	/** @return void */
	public function render_receivables() {
		$this->render( 'receivables.php' );
	}

	/** @return void */
	public function render_students_classes() {
		$this->render( 'students-classes.php' );
	}

	/** @return void */
	public function render_budget() {
		$this->render( 'budget.php' );
	}

	/** @return void */
	public function render_expenses() {
		$this->render( 'expenses.php' );
	}

	/** @return void */
	public function render_projections() {
		$this->render( 'projections.php' );
	}

	/** @return void */
	public function render_tools() {
		$this->render( 'tools.php' );
	}

	/* ---------------------------------------------------------------------
	 * POST handling
	 * ------------------------------------------------------------------ */

	/**
	 * Dispatch non-AJAX form submissions.
	 */
	public function handle_post() {
		if ( empty( $_POST['mmr_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! current_user_can( MMR_CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mm-register' ) );
		}
		$action = sanitize_key( wp_unslash( $_POST['mmr_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		check_admin_referer( 'mmr_form' );

		$map = array(
			'save_student'      => 'post_save_student',
			'save_class'        => 'post_save_class',
			'quick_add_1on1'    => 'post_quick_add_1on1',
			'record_settlement' => 'post_record_settlement',
			'save_settlement'   => 'post_save_settlement',
			'delete_settlement' => 'post_delete_settlement',
			'save_budget_item'  => 'post_save_budget_item',
			'delete_budget_item'=> 'post_delete_budget_item',
			'save_expense'      => 'post_save_expense',
			'delete_expense'    => 'post_delete_expense',
			'mark_expense_paid' => 'post_mark_expense_paid',
			'unmark_expense_paid' => 'post_unmark_expense_paid',
			'save_projection'   => 'post_save_projection',
			'delete_projection' => 'post_delete_projection',
			'import_data'       => 'post_import_data',
			'export_data'       => 'post_export_data',
			'wipe_data'         => 'post_wipe_data',
		);
		if ( isset( $map[ $action ] ) ) {
			$this->{$map[ $action ]}();
		}
	}

	/**
	 * Redirect back to a plugin page with a notice.
	 *
	 * @param string $page   Page slug suffix.
	 * @param string $notice Notice key.
	 * @param array  $args   Extra query args.
	 * @param bool   $error  Mark as error.
	 */
	protected function redirect( $page, $notice = 'saved', $args = array(), $error = false ) {
		$args['mmr_notice'] = $notice;
		if ( $error ) {
			$args['mmr_error'] = 1;
		}
		wp_safe_redirect( mmr_page_url( $page, $args ) );
		exit;
	}

	/**
	 * Read a POST field as trimmed text.
	 *
	 * @param string $key     Field name.
	 * @param string $default Default.
	 * @return string
	 */
	protected function text( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Read a POST field as multiline text.
	 *
	 * @param string $key Field name.
	 * @return string
	 */
	protected function textarea( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Read a POST field as money.
	 *
	 * @param string $key Field name.
	 * @return float
	 */
	protected function money( $key ) {
		return isset( $_POST[ $key ] ) ? mmr_sanitize_money( wp_unslash( $_POST[ $key ] ) ) : 0.0; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Read a checkbox.
	 *
	 * @param string $key Field name.
	 * @return int 0|1
	 */
	protected function bool( $key ) {
		return empty( $_POST[ $key ] ) ? 0 : 1; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Validate a date field or return empty string.
	 *
	 * @param string $key Field name.
	 * @return string Y-m-d or ''.
	 */
	protected function date( $key ) {
		$raw = $this->text( $key );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			$p = explode( '-', $raw );
			if ( checkdate( (int) $p[1], (int) $p[2], (int) $p[0] ) ) {
				return $raw;
			}
		}
		return '';
	}

	/* --- Students & classes ------------------------------------------- */

	/** Save a student. */
	protected function post_save_student() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$data = array(
			'name'            => $this->text( 'name' ),
			'email'           => sanitize_email( $this->text( 'email' ) ),
			'tbt_monthly_fee' => $this->money( 'tbt_monthly_fee' ),
			'notes'           => $this->textarea( 'notes' ),
			'status'          => in_array( $this->text( 'status' ), array( 'active', 'archived' ), true ) ? $this->text( 'status' ) : 'active',
		);
		if ( '' === $data['name'] ) {
			$this->redirect( 'students-classes', 'Name is required.', array( 'tab' => 'students' ), true );
		}
		MMR_Data::save_student( $data, $id );
		$this->redirect( 'students-classes', 'saved', array( 'tab' => 'students' ) );
	}

	/** Save a class + its members. */
	protected function post_save_class() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$data = array(
			'name'          => $this->text( 'name' ),
			'fee'           => $this->money( 'fee' ),
			'client_type'   => $this->text( 'client_type' ),
			'venue'         => $this->text( 'venue' ),
			'settle_method' => $this->text( 'settle_method' ),
			'notes'         => $this->textarea( 'notes' ),
			'status'        => in_array( $this->text( 'status' ), array( 'active', 'archived' ), true ) ? $this->text( 'status' ) : 'active',
			'sort_name'     => $this->text( 'sort_name' ),
		);
		if ( '' === $data['name'] ) {
			$this->redirect( 'students-classes', 'Name is required.', array( 'tab' => 'classes' ), true );
		}
		$class_id = MMR_Data::save_class( $data, $id );

		// Members repeater.
		$members = array();
		$student_ids = isset( $_POST['member_student_id'] ) ? (array) wp_unslash( $_POST['member_student_id'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$shares = isset( $_POST['member_share'] ) ? (array) wp_unslash( $_POST['member_share'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( $student_ids as $i => $sid ) {
			$sid = (int) $sid;
			if ( ! $sid ) {
				continue;
			}
			$share_raw = isset( $shares[ $i ] ) ? trim( (string) $shares[ $i ] ) : '';
			$members[] = array(
				'student_id' => $sid,
				'share'      => ( '' === $share_raw ) ? null : mmr_sanitize_money( $share_raw ),
			);
		}
		MMR_Data::set_members( $class_id, $members );
		$this->redirect( 'students-classes', 'saved', array( 'tab' => 'classes' ) );
	}

	/** Quick-add a 1-on-1 student + same-named class + membership. */
	protected function post_quick_add_1on1() {
		$name = $this->text( 'name' );
		$fee = $this->money( 'fee' );
		$tbt = $this->money( 'tbt_monthly_fee' );
		$email = sanitize_email( $this->text( 'email' ) );
		if ( '' === $name ) {
			$this->redirect( 'students-classes', 'Name is required.', array( 'tab' => 'classes' ), true );
		}
		$student_id = MMR_Data::save_student( array(
			'name'            => $name,
			'email'           => $email,
			'tbt_monthly_fee' => $tbt,
			'notes'           => '',
			'status'          => 'active',
		) );
		$class_id = MMR_Data::save_class( array(
			'name'          => $name,
			'fee'           => $fee,
			'client_type'   => 'private',
			'venue'         => $this->text( 'venue' ),
			'settle_method' => $this->text( 'settle_method' ),
			'notes'         => '',
			'status'        => 'active',
			'sort_name'     => '',
		) );
		MMR_Data::set_members( $class_id, array( array( 'student_id' => $student_id, 'share' => null ) ) );
		$this->redirect( 'students-classes', 'saved', array( 'tab' => 'classes' ) );
	}

	/* --- Settlement / receivables ------------------------------------- */

	/** Record one or more settlement rows from the settlement screen. */
	protected function post_record_settlement() {
		$period = mmr_sanitize_period( $this->text( 'period' ) );
		$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;
		$per_member = $this->bool( 'per_member' );
		if ( ! $period || ! $class_id ) {
			$this->redirect( 'settlement', 'nothing', array(), true );
		}
		$doc_date = $this->date( 'doc_date' );

		if ( $per_member ) {
			$doc_type = $this->text( 'doc_type_pm' );
			$names = isset( $_POST['member_name'] ) ? (array) wp_unslash( $_POST['member_name'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
			$amounts = isset( $_POST['member_amount'] ) ? (array) wp_unslash( $_POST['member_amount'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
			foreach ( $names as $i => $name ) {
				$amount = isset( $amounts[ $i ] ) ? mmr_sanitize_money( $amounts[ $i ] ) : 0;
				if ( $amount <= 0 ) {
					continue;
				}
				MMR_Data::save_settlement( array(
					'doc_type'    => $doc_type,
					'doc_number'  => '',
					'doc_date'    => $doc_date,
					'payer_label' => sanitize_text_field( $name ),
					'class_id'    => $class_id,
					'period'      => $period,
					'amount'      => $amount,
					'ksef'        => 0,
					'sent'        => 0,
					'paid'        => 0,
					'paid_on'     => '',
					'notes'       => '',
				) );
			}
		} else {
			MMR_Data::save_settlement( array(
				'doc_type'    => $this->text( 'doc_type' ),
				'doc_number'  => $this->text( 'doc_number' ),
				'doc_date'    => $doc_date,
				'payer_label' => $this->text( 'payer_label' ),
				'class_id'    => $class_id,
				'period'      => $period,
				'amount'      => $this->money( 'amount' ),
				'ksef'        => $this->bool( 'ksef' ),
				'sent'        => $this->bool( 'sent' ),
				'paid'        => 0,
				'paid_on'     => '',
				'notes'       => $this->textarea( 'notes' ),
			) );
		}
		$this->redirect( 'settlement', 'settlement_saved', array( 'period' => $period ) );
	}

	/** Save / add a settlement (Receivables manual entry). */
	protected function post_save_settlement() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$data = array(
			'doc_type'    => $this->text( 'doc_type' ),
			'doc_number'  => $this->text( 'doc_number' ),
			'doc_date'    => $this->date( 'doc_date' ),
			'payer_label' => $this->text( 'payer_label' ),
			'class_id'    => isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0,
			'period'      => mmr_sanitize_period( $this->text( 'period' ) ),
			'amount'      => $this->money( 'amount' ),
			'ksef'        => $this->bool( 'ksef' ),
			'sent'        => $this->bool( 'sent' ),
			'paid'        => $this->bool( 'paid' ),
			'paid_on'     => $this->date( 'paid_on' ),
			'notes'       => $this->textarea( 'notes' ),
		);
		if ( $data['paid'] && ! $data['paid_on'] ) {
			$data['paid_on'] = current_time( 'Y-m-d' );
		}
		MMR_Data::save_settlement( $data, $id );
		$this->redirect( 'receivables', 'saved' );
	}

	/** Delete a settlement. */
	protected function post_delete_settlement() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $id ) {
			MMR_Data::delete_settlement( $id );
		}
		$this->redirect( 'receivables', 'deleted' );
	}

	/* --- Budget -------------------------------------------------------- */

	/** Save a budget item. */
	protected function post_save_budget_item() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$kind = $this->text( 'kind' );
		if ( ! in_array( $kind, array( 'asset', 'liability', 'reserve', 'note_only' ), true ) ) {
			$kind = 'asset';
		}
		MMR_Data::save_budget_item( array(
			'label'      => $this->text( 'label' ),
			'amount'     => $this->money( 'amount' ),
			'kind'       => $kind,
			'sort_order' => isset( $_POST['sort_order'] ) ? (int) $_POST['sort_order'] : 0,
			'notes'      => $this->textarea( 'notes' ),
		), $id );
		$this->redirect( 'budget', 'saved' );
	}

	/** Delete a budget item. */
	protected function post_delete_budget_item() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $id ) {
			MMR_Data::delete_budget_item( $id );
		}
		$this->redirect( 'budget', 'deleted' );
	}

	/* --- Expenses ------------------------------------------------------ */

	/** Save an expense definition. */
	protected function post_save_expense() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$pay_type = in_array( $this->text( 'pay_type' ), array( 'manual', 'subscription' ), true ) ? $this->text( 'pay_type' ) : 'manual';
		$frequency = in_array( $this->text( 'frequency' ), array( 'monthly', 'yearly' ), true ) ? $this->text( 'frequency' ) : 'monthly';
		$category = in_array( $this->text( 'category' ), array( 'critical', 'moderate', 'whim' ), true ) ? $this->text( 'category' ) : 'moderate';
		MMR_Data::save_expense( array(
			'title'     => $this->text( 'title' ),
			'projected' => $this->money( 'projected' ),
			'pay_type'  => $pay_type,
			'frequency' => $frequency,
			'category'  => $category,
			'due_note'  => $this->text( 'due_note' ),
			'active'    => $this->bool( 'active' ),
		), $id );
		$this->redirect( 'expenses', 'saved' );
	}

	/** Delete an expense definition. */
	protected function post_delete_expense() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $id ) {
			MMR_Data::delete_expense( $id );
		}
		$this->redirect( 'expenses', 'deleted' );
	}

	/** Mark an expense paid for a period. */
	protected function post_mark_expense_paid() {
		$expense_id = isset( $_POST['expense_id'] ) ? (int) $_POST['expense_id'] : 0;
		$period = mmr_sanitize_period( $this->text( 'period' ) );
		if ( ! $expense_id || ! $period ) {
			$this->redirect( 'expenses', 'nothing', array(), true );
		}
		$spent = $this->money( 'spent' );
		$paid_on = $this->date( 'paid_on' );
		if ( ! $paid_on ) {
			$paid_on = current_time( 'Y-m-d' );
		}
		MMR_Data::mark_expense_paid( $expense_id, $period, $spent, $paid_on );
		$this->redirect( 'expenses', 'saved', array( 'period' => $period ) );
	}

	/** Un-mark an expense payment. */
	protected function post_unmark_expense_paid() {
		$expense_id = isset( $_POST['expense_id'] ) ? (int) $_POST['expense_id'] : 0;
		$period = mmr_sanitize_period( $this->text( 'period' ) );
		if ( $expense_id && $period ) {
			MMR_Data::unmark_expense_paid( $expense_id, $period );
		}
		$this->redirect( 'expenses', 'deleted', array( 'period' => $period ) );
	}

	/* --- Projections --------------------------------------------------- */

	/** Save a projection. */
	protected function post_save_projection() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$period = mmr_sanitize_period( $this->text( 'period' ) );
		if ( ! $period ) {
			$this->redirect( 'projections', 'Invalid month.', array(), true );
		}
		MMR_Data::save_projection( array(
			'period'    => $period,
			'projected' => $this->money( 'projected' ),
			'expenses'  => $this->money( 'expenses' ),
			'notes'     => $this->textarea( 'notes' ),
		), $id );
		$this->redirect( 'projections', 'saved' );
	}

	/** Delete a projection. */
	protected function post_delete_projection() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $id ) {
			MMR_Data::delete_projection( $id );
		}
		$this->redirect( 'projections', 'deleted' );
	}

	/* --- Tools --------------------------------------------------------- */

	/** Run the JSON importer (dry-run or commit). */
	protected function post_import_data() {
		$mode = $this->text( 'mode' ); // dryrun | commit.
		$merge = $this->bool( 'merge' );

		$json_key = 'mmr_import_json_' . get_current_user_id();

		$json = '';
		if ( ! empty( $_FILES['import_file']['tmp_name'] ) && is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
			$json = file_get_contents( $_FILES['import_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		} elseif ( ! empty( $_POST['import_json'] ) ) {
			$json = wp_unslash( $_POST['import_json'] ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		} elseif ( ! empty( $_POST['use_stored'] ) ) {
			// Committing a previously dry-run payload: browsers clear the file
			// input on reload, so reuse the JSON stashed during the dry run.
			$json = (string) get_transient( $json_key );
		}

		$importer = new MMR_Import();
		$result = $importer->run( $json, array(
			'commit' => ( 'commit' === $mode ),
			'merge'  => (bool) $merge,
		) );

		// Stash the result for display on the Tools page.
		set_transient( 'mmr_import_result_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );

		// Keep the raw JSON around after a clean dry run so the operator can
		// commit it without re-selecting the file; clear it once committed.
		if ( 'commit' === $mode ) {
			delete_transient( $json_key );
		} elseif ( '' !== $json && empty( $result['error'] ) ) {
			set_transient( $json_key, $json, 30 * MINUTE_IN_SECONDS );
			$result['can_commit'] = true;
			set_transient( 'mmr_import_result_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );
		}

		if ( ! empty( $result['blocked'] ) ) {
			$this->redirect( 'tools', 'import_blocked', array(), true );
		}
		if ( ! empty( $result['error'] ) ) {
			$this->redirect( 'tools', 'import_error', array(), true );
		}
		$notice = ( 'commit' === $mode ) ? 'imported' : 'saved';
		$this->redirect( 'tools', $notice );
	}

	/** Stream a full JSON export of all tables. */
	protected function post_export_data() {
		$importer = new MMR_Import();
		$data = $importer->export();
		$filename = 'mm-register-export-' . gmdate( 'Y-m-d-His' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/** Wipe all plugin data (typed-confirmation danger zone). */
	protected function post_wipe_data() {
		$confirm = $this->text( 'confirm' );
		if ( 'DELETE' !== $confirm ) {
			$this->redirect( 'tools', 'Type DELETE to confirm.', array(), true );
		}
		MMR_DB::wipe();
		MMR_DB::install(); // Recreate empty tables so the plugin stays usable.
		$this->redirect( 'tools', 'wiped' );
	}
}
