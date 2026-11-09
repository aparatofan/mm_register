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
	public function render_events() {
		$this->render( 'events.php' );
	}

	/** @return void */
	public function render_students() {
		$this->render( 'students.php' );
	}

	/** @return void */
	public function render_classes() {
		$this->render( 'classes.php' );
	}

	/** @return void */
	public function render_companies() {
		$this->render( 'companies.php' );
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
			'save_student'          => 'post_save_student',
			'toggle_student_status' => 'post_toggle_student_status',
			'save_class'            => 'post_save_class',
			'toggle_class_status'   => 'post_toggle_class_status',
			'quick_add_1on1'        => 'post_quick_add_1on1',
			'save_company'          => 'post_save_company',
			'toggle_company_status' => 'post_toggle_company_status',
			'save_event'            => 'post_save_event',
			'delete_event'          => 'post_delete_event',
			'settle_create_event'   => 'post_settle_create_event',
			'settle_create_remaining' => 'post_settle_create_remaining',
			'settle_event_action'   => 'post_settle_event_action',
			'settle_add_credit'     => 'post_settle_add_credit',
			'import_data'           => 'post_import_data',
			'export_data'           => 'post_export_data',
			'wipe_data'             => 'post_wipe_data',
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

	/* --- Students & classes ------------------------------------------- */

	/**
	 * Collect the filter_* fields a row-action form echoes back, so the
	 * redirect can restore the list view (search / status / type filter on
	 * students, classes and companies; year / type / state / sort on events).
	 *
	 * @return array
	 */
	protected function filter_args() {
		$args = array();
		foreach ( array( 's', 'status', 'client_type', 'company_id', 'year', 'type', 'state', 'orderby', 'order' ) as $key ) {
			$value = $this->text( 'filter_' . $key );
			if ( '' !== $value ) {
				$args[ $key ] = $value;
			}
		}
		return $args;
	}

	/** Save a student. */
	protected function post_save_student() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$email = trim( $this->text( 'email' ) );
		$email_2 = trim( $this->text( 'email_2' ) );
		$data = array(
			'name'            => $this->text( 'name' ),
			'email'           => '' === $email ? '' : sanitize_email( $email ),
			'email_2'         => '' === $email_2 ? '' : sanitize_email( $email_2 ),
			'phone'           => $this->text( 'phone' ),
			'level'           => $this->text( 'level' ),
			'tbt_monthly_fee' => $this->money( 'tbt_monthly_fee' ),
			'notes'           => $this->textarea( 'notes' ),
			'status'          => in_array( $this->text( 'status' ), array( 'active', 'archived' ), true ) ? $this->text( 'status' ) : 'active',
		);
		if ( '' === $data['name'] ) {
			$this->redirect( 'students', 'Name is required.', array(), true );
		}
		MMR_Data::save_student( $data, $id );
		$this->redirect( 'students', 'saved' );
	}

	/** Archive / restore a student. */
	protected function post_toggle_student_status() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$status = ( 'archived' === $this->text( 'status' ) ) ? 'archived' : 'active';
		if ( $id ) {
			MMR_Data::set_student_status( $id, $status );
		}
		$this->redirect( 'students', 'saved', $this->filter_args() );
	}

	/**
	 * Resolve the class form's Company field to a company_id.
	 * Empty name → 0 (no company). A non-empty name must match an existing
	 * company (any status, case-insensitive); otherwise null signals rejection.
	 *
	 * @return int|null 0 = none, >0 = resolved id, null = unknown name.
	 */
	protected function resolve_company() {
		$name = $this->text( 'company' );
		if ( '' === $name ) {
			return 0;
		}
		$company = MMR_Data::company_by_name( $name );
		return $company ? (int) $company->id : null;
	}

	/** Save a class + its members. */
	protected function post_save_class() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		$company_id = $this->resolve_company();
		if ( null === $company_id ) {
			$this->redirect( 'classes', 'Unknown company — add it on the Companies page first.', array(), true );
		}

		$data = array(
			'name'          => $this->text( 'name' ),
			'fee'           => $this->money( 'fee' ),
			'client_type'   => $this->text( 'client_type' ),
			'settle_method' => $this->text( 'settle_method' ),
			'company_id'    => $company_id,
			'pay_per_member' => $this->bool( 'pay_per_member' ),
			'notes'         => $this->textarea( 'notes' ),
			'status'        => in_array( $this->text( 'status' ), array( 'active', 'archived' ), true ) ? $this->text( 'status' ) : 'active',
		);
		if ( '' === $data['name'] ) {
			$this->redirect( 'classes', 'Name is required.', array(), true );
		}

		// Prepaid credit rows are parsed before anything is saved, so a bad
		// row saves nothing. null = leave the stored payments untouched (box
		// hidden / not a rozliczenie class / a form without the box).
		$credits = null;
		if ( ! empty( $_POST['credits_present'] ) && 'rozliczenie' === MMR_Data::event_type_hint( $data['settle_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$credits = $this->parse_credit_rows();
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

		// An empty list deletes every payment — that is what removing every row means.
		if ( null !== $credits ) {
			MMR_Data::set_class_credits( $class_id, $credits );
		}
		$this->redirect( 'classes', 'saved' );
	}

	/**
	 * Parse the class form's prepaid-credit rows, or redirect with an error.
	 *
	 * A row with neither an amount nor a date is skipped. A row with a date
	 * but no amount is skipped too: the box offers one row dated today, and
	 * leaving it empty must not block saving the class.
	 *
	 * @return array List of [ date, amount (float), note ].
	 */
	protected function parse_credit_rows() {
		$dates   = isset( $_POST['credit_date'] ) ? (array) wp_unslash( $_POST['credit_date'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$amounts = isset( $_POST['credit_amount'] ) ? (array) wp_unslash( $_POST['credit_amount'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$notes   = isset( $_POST['credit_note'] ) ? (array) wp_unslash( $_POST['credit_note'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification

		$rows = array();
		foreach ( $amounts as $i => $amount_raw ) {
			if ( '' === trim( (string) $amount_raw ) ) {
				continue;
			}
			$row = MMR_Data::normalize_credit_row(
				isset( $dates[ $i ] ) ? $dates[ $i ] : '',
				(string) $amount_raw,
				isset( $notes[ $i ] ) ? $notes[ $i ] : ''
			);
			if ( is_wp_error( $row ) ) {
				$this->redirect( 'classes', $row->get_error_message(), array(), true );
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/** Archive / restore a class. */
	protected function post_toggle_class_status() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$status = ( 'archived' === $this->text( 'status' ) ) ? 'archived' : 'active';
		if ( $id ) {
			MMR_Data::set_class_status( $id, $status );
		}
		$this->redirect( 'classes', 'saved', $this->filter_args() );
	}

	/** Quick-add a 1-on-1 student + same-named class + membership. */
	protected function post_quick_add_1on1() {
		$name = $this->text( 'name' );
		$fee = $this->money( 'fee' );
		$tbt = $this->money( 'tbt_monthly_fee' );
		$email = sanitize_email( $this->text( 'email' ) );
		if ( '' === $name ) {
			$this->redirect( 'classes', 'Name is required.', array(), true );
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
			'settle_method' => $this->text( 'settle_method' ),
			'company_id'    => 0,
			'pay_per_member' => 0,
			'notes'         => '',
			'status'        => 'active',
		) );
		MMR_Data::set_members( $class_id, array( array( 'student_id' => $student_id, 'share' => null ) ) );
		$this->redirect( 'classes', 'saved' );
	}

	/* --- Companies ----------------------------------------------------- */

	/** Save a company. */
	protected function post_save_company() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$name = $this->text( 'name' );
		if ( '' === $name ) {
			$this->redirect( 'companies', 'Name is required.', array(), true );
		}
		if ( MMR_Data::company_by_name( $name, $id ) ) {
			$this->redirect( 'companies', 'A company with that name already exists.', array(), true );
		}
		MMR_Data::save_company( array(
			'name'    => $name,
			'address' => $this->textarea( 'address' ),
			'nip'     => $this->text( 'nip' ),
			'notes'   => $this->textarea( 'notes' ),
			'status'  => in_array( $this->text( 'status' ), array( 'active', 'archived' ), true ) ? $this->text( 'status' ) : 'active',
		), $id );
		$this->redirect( 'companies', 'saved' );
	}

	/** Archive / restore a company. */
	protected function post_toggle_company_status() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$status = ( 'archived' === $this->text( 'status' ) ) ? 'archived' : 'active';
		if ( $id ) {
			MMR_Data::set_company_status( $id, $status );
		}
		$this->redirect( 'companies', 'saved', $this->filter_args() );
	}

	/* --- Events -------------------------------------------------------- */

	/**
	 * Save an event. Field-level rules (type whitelist, invoice-only KSeF,
	 * paid/paid_date coupling, source provenance) live in
	 * MMR_Data::normalize_event(), which every write path shares; who/type
	 * resolution lives in MMR_Data::resolve_event_who(), which discards class
	 * IDs that resolve to nothing and re-derives the type server-side.
	 *
	 * The posted `type` is therefore a fallback, not an input: it is used only
	 * where derivation cannot succeed (mixed or unmapped settle methods, or a
	 * free-text-only payer).
	 */
	protected function post_save_event() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		$class_ids = isset( $_POST['class_ids'] ) ? (array) wp_unslash( $_POST['class_ids'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$class_ids = array_values( array_unique( array_filter( array_map( 'absint', $class_ids ) ) ) );
		// Drop anything that does not resolve to a real class before the
		// required-check, so a payload of nothing but junk IDs is rejected
		// rather than saved as an event with no payer.
		$known = MMR_Data::classes_by_ids( $class_ids );
		$class_ids = array_values( array_filter( $class_ids, function ( $class_id ) use ( $known ) {
			return isset( $known[ $class_id ] );
		} ) );
		$who_text = $this->text( 'who_text' );

		if ( empty( $class_ids ) && '' === $who_text ) {
			$this->redirect( 'events', 'Who is required.', $this->filter_args(), true );
		}

		// The form has no is_draft / lines_total fields. On an update carry the
		// stored values through, so saving a draft (e.g. to fix its date) keeps
		// it a draft and keeps its drift baseline; normalize_event()'s
		// auto-issue rule then clears the draft if a number / Sent / Paid was
		// entered. New events made here are real documents: 0 and NULL.
		$is_draft = 0;
		$lines_total = null;
		if ( $id ) {
			$stored = MMR_Data::event( $id );
			if ( $stored ) {
				$is_draft = (int) $stored->is_draft;
				$lines_total = $stored->lines_total;
			}
		}

		MMR_Data::save_event( array(
			'type'          => $this->text( 'type' ),
			'doc_number'    => $this->text( 'doc_number' ),
			'issue_date'    => $this->text( 'issue_date' ),
			'class_ids'     => $class_ids,
			'who_text'      => $who_text,
			'amount'        => $this->money( 'amount' ),
			'ksef'          => $this->bool( 'ksef' ),
			'sent'          => $this->bool( 'sent' ),
			'paid'          => $this->bool( 'paid' ),
			'paid_date'     => $this->text( 'paid_date' ),
			'notes'         => $this->textarea( 'notes' ),
			'source_kind'   => $this->text( 'source_kind' ),
			'source_id'     => isset( $_POST['source_id'] ) ? (int) $_POST['source_id'] : 0, // phpcs:ignore WordPress.Security.NonceVerification
			'source_period' => $this->text( 'source_period' ),
			'is_draft'      => $is_draft,
			'lines_total'   => $lines_total,
		), $id );
		$this->redirect( 'events', 'saved', $this->filter_args() );
	}

	/** Delete an event — a real delete, see MMR_Data::delete_event(). */
	protected function post_delete_event() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $id ) {
			MMR_Data::delete_event( $id );
		}
		$this->redirect( 'events', 'deleted', $this->filter_args() );
	}

	/* --- Settlement: draft events (2.7) -------------------------------- */

	/**
	 * The posted settlement period, or a redirect back with an error.
	 *
	 * @return string Y-m.
	 */
	protected function settle_period() {
		$period = mmr_sanitize_period( $this->text( 'period' ) );
		if ( ! $period ) {
			$this->redirect( 'settlement', 'Unknown month.', array(), true );
		}
		return $period;
	}

	/** Create one draft event from one or more ticked lines. */
	protected function post_settle_create_event() {
		$period = $this->settle_period();
		$keys = isset( $_POST['line_keys'] ) ? (array) wp_unslash( $_POST['line_keys'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$keys = array_map( 'sanitize_text_field', $keys );

		$result = MMR_Data::create_event_from_lines( $period, $keys );
		if ( is_wp_error( $result ) ) {
			$this->redirect( 'settlement', $result->get_error_message(), array( 'period' => $period ), true );
		}
		/* translators: %d: event ID. */
		$this->redirect( 'settlement', sprintf( __( 'Draft event E-%d created.', 'mm-register' ), $result ), array( 'period' => $period, 'event' => $result ) );
	}

	/**
	 * One draft per uncovered eligible line. Coverage is read once here;
	 * create_event_from_lines() re-checks it per line, so a line covered in
	 * the meantime is skipped rather than drafted twice.
	 */
	protected function post_settle_create_remaining() {
		$period = $this->settle_period();
		$coverage = MMR_Data::events_for_period( $period );

		$created = 0;
		$skipped = array(); // reason => names.
		foreach ( MMR_Data::billable_lines( $period ) as $line ) {
			if ( ! empty( $coverage[ $line['key'] ] ) ) {
				continue;
			}
			if ( '' !== $line['ineligible_reason'] ) {
				$skipped[ $line['ineligible_reason'] ][] = $line['name'];
				continue;
			}
			$result = MMR_Data::create_event_from_lines( $period, array( $line['key'] ) );
			if ( is_wp_error( $result ) ) {
				$skipped[ $result->get_error_message() ][] = $line['name'];
				continue;
			}
			$created++;
		}

		/* translators: %d: number of draft events created. */
		$parts = array( sprintf( _n( 'Created %d draft event.', 'Created %d draft events.', $created, 'mm-register' ), $created ) );
		foreach ( $skipped as $reason => $names ) {
			/* translators: 1: number skipped, 2: line names, 3: reason. */
			$parts[] = sprintf( __( 'Skipped %1$d: %2$s — %3$s', 'mm-register' ), count( $names ), implode( ', ', $names ), $reason );
		}
		$this->redirect( 'settlement', implode( ' ', $parts ), array( 'period' => $period ), 0 === $created && ! empty( $skipped ) );
	}

	/**
	 * Add one prepaid-credit payment from the Settlement credit panel.
	 * Capability and nonce are checked by handle_post().
	 */
	protected function post_settle_add_credit() {
		$period = $this->settle_period();
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$back = array( 'period' => $period, 'credit' => $class_id );

		$row = MMR_Data::normalize_credit_row( $this->text( 'credit_date' ), $this->text( 'amount' ), $this->text( 'note' ) );
		if ( is_wp_error( $row ) ) {
			$this->redirect( 'settlement', $row->get_error_message(), $back, true );
		}
		$amount = $row['amount'];

		$first = ! MMR_Data::credits_for_classes( array( $class_id ) );
		$result = MMR_Data::add_class_credit( $class_id, $row['date'], $amount, $row['note'] );
		if ( is_wp_error( $result ) ) {
			$this->redirect( 'settlement', $result->get_error_message(), $back, true );
		}
		$message = $first
			/* translators: %s: amount, e.g. "3 600,00 zł". */
			? sprintf( __( 'Credit of %s added.', 'mm-register' ), mmr_money( $amount ) )
			/* translators: %s: amount, e.g. "500,00 zł". */
			: sprintf( __( 'Top-up of %s added.', 'mm-register' ), mmr_money( $amount ) );
		wp_safe_redirect( mmr_page_url( 'settlement', array_merge( $back, array( 'mmr_notice' => $message ) ) ) . '#mmr-credit-panel' );
		exit;
	}

	/** Act on one event from the Settlement page's event panel. */
	protected function post_settle_event_action() {
		$period = $this->settle_period();
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$do = $this->text( 'do' );
		$back = array( 'period' => $period, 'event' => $id );

		switch ( $do ) {
			case 'update':
				$result = MMR_Data::update_draft_from_settlement( $id );
				$notice = __( 'Draft updated to the settlement.', 'mm-register' );
				break;
			case 'remove_line':
				$result = MMR_Data::remove_line_from_draft( $id, $this->text( 'line_key' ) );
				$notice = __( 'Line removed from the event.', 'mm-register' );
				break;
			case 'issue':
				$result = MMR_Data::mark_event_issued( $id );
				$notice = __( 'Event issued — its amount is now locked.', 'mm-register' );
				break;
			case 'pay':
				$result = MMR_Data::mark_event_paid( $id );
				$notice = __( 'Event marked as paid.', 'mm-register' );
				break;
			case 'delete':
				// Drafts only from here; issued documents are deleted in Events.
				$event = MMR_Data::event( $id );
				if ( ! $event || ! (int) $event->is_draft ) {
					$result = new WP_Error( 'mmr_not_draft', __( 'Only drafts can be deleted from the Settlement page.', 'mm-register' ) );
				} else {
					MMR_Data::delete_event( $id );
					$result = 'deleted';
				}
				$notice = '';
				break;
			default:
				$this->redirect( 'settlement', 'nothing', $back, true );
				return;
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect( 'settlement', $result->get_error_message(), $back, true );
		}
		if ( 'deleted' === $result ) {
			/* translators: %d: event ID. */
			$this->redirect( 'settlement', sprintf( __( 'Draft E-%d deleted.', 'mm-register' ), $id ), array( 'period' => $period ) );
		}
		$this->redirect( 'settlement', $notice, $back );
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
