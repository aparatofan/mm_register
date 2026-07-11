<?php
/**
 * AJAX endpoints for the calendar and inline toggles.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles all admin-ajax actions.
 */
class MMR_Ajax {

	/**
	 * Wire the AJAX actions (all admin-only).
	 */
	public function register() {
		$actions = array(
			'mmr_search_classes',
			'mmr_create_lesson',
			'mmr_update_lesson',
			'mmr_delete_lesson',
			'mmr_copy_week',
		);
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, $action ) );
		}
	}

	/**
	 * Shared guard: capability + nonce + no-cache headers.
	 */
	protected function guard() {
		if ( ! current_user_can( MMR_CAP ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'mmr_ajax', 'nonce' );
		nocache_headers();
	}

	/**
	 * Type-ahead over active classes.
	 */
	public function mmr_search_classes() {
		$this->guard();
		$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
		$classes = MMR_Data::classes( array( 'status' => 'active', 'search' => $term ) );
		$out = array();
		foreach ( $classes as $class ) {
			$out[] = array(
				'id'   => (int) $class->id,
				'name' => $class->name,
				'fee'  => (float) $class->fee,
				'fee_display' => mmr_money( $class->fee ),
			);
		}
		wp_send_json_success( array( 'classes' => $out ) );
	}

	/**
	 * Create a lesson in a slot.
	 */
	public function mmr_create_lesson() {
		$this->guard();
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$slot = isset( $_POST['slot'] ) ? (int) $_POST['slot'] : 0;
		$class_id = isset( $_POST['class_id'] ) ? (int) $_POST['class_id'] : 0;

		if ( ! $this->valid_date( $date ) || $slot < 1 || $slot > MMR_SLOTS_PER_DAY || ! $class_id ) {
			wp_send_json_error( array( 'message' => 'bad_request' ), 400 );
		}
		$class = MMR_Data::class_row( $class_id );
		if ( ! $class ) {
			wp_send_json_error( array( 'message' => 'class_not_found' ), 404 );
		}
		$id = MMR_Data::create_lesson( $date, $slot, $class_id );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'create_failed' ), 500 );
		}
		wp_send_json_success( array( 'lesson' => $this->lesson_payload( MMR_Data::lesson( $id ) ) ) );
	}

	/**
	 * Update a lesson (class / fee / note / move).
	 */
	public function mmr_update_lesson() {
		$this->guard();
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$lesson = $id ? MMR_Data::lesson( $id ) : null;
		if ( ! $lesson ) {
			wp_send_json_error( array( 'message' => 'not_found' ), 404 );
		}

		$data = array();
		if ( isset( $_POST['class_id'] ) && (int) $_POST['class_id'] ) {
			$data['class_id'] = (int) $_POST['class_id'];
		}
		if ( isset( $_POST['fee'] ) && '' !== $_POST['fee'] ) {
			$data['fee_snapshot'] = mmr_sanitize_money( wp_unslash( $_POST['fee'] ) );
		}
		if ( isset( $_POST['note'] ) ) {
			$data['note'] = sanitize_text_field( wp_unslash( $_POST['note'] ) );
		}
		if ( isset( $_POST['move_date'] ) && $this->valid_date( sanitize_text_field( wp_unslash( $_POST['move_date'] ) ) ) ) {
			$move_date = sanitize_text_field( wp_unslash( $_POST['move_date'] ) );
			$move_slot = isset( $_POST['move_slot'] ) ? (int) $_POST['move_slot'] : (int) $lesson->slot;
			if ( $move_slot < 1 || $move_slot > MMR_SLOTS_PER_DAY ) {
				wp_send_json_error( array( 'message' => 'bad_slot' ), 400 );
			}
			// Refuse to move onto a different occupied slot.
			$target_occupied = MMR_Data::slot_taken( $move_date, $move_slot );
			$same_slot = ( $move_date === $lesson->lesson_date && $move_slot === (int) $lesson->slot );
			if ( $target_occupied && ! $same_slot ) {
				wp_send_json_error( array( 'message' => 'slot_taken' ), 409 );
			}
			$data['lesson_date'] = $move_date;
			$data['slot'] = $move_slot;
		}

		MMR_Data::update_lesson( $id, $data );
		wp_send_json_success( array( 'lesson' => $this->lesson_payload( MMR_Data::lesson( $id ) ) ) );
	}

	/**
	 * Delete a lesson.
	 */
	public function mmr_delete_lesson() {
		$this->guard();
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! $id || ! MMR_Data::lesson( $id ) ) {
			wp_send_json_error( array( 'message' => 'not_found' ), 404 );
		}
		MMR_Data::delete_lesson( $id );
		wp_send_json_success( array( 'deleted' => $id ) );
	}

	/**
	 * Copy an entire week's lessons to a target week, skipping filled slots.
	 */
	public function mmr_copy_week() {
		$this->guard();
		$source = isset( $_POST['source_monday'] ) ? sanitize_text_field( wp_unslash( $_POST['source_monday'] ) ) : '';
		$target = isset( $_POST['target_monday'] ) ? sanitize_text_field( wp_unslash( $_POST['target_monday'] ) ) : '';
		if ( ! $this->valid_date( $source ) || ! $this->valid_date( $target ) ) {
			wp_send_json_error( array( 'message' => 'bad_request' ), 400 );
		}
		$source = mmr_week_monday( $source );
		$target = mmr_week_monday( $target );
		if ( $source === $target ) {
			wp_send_json_error( array( 'message' => 'same_week' ), 400 );
		}

		$source_end = wp_date( 'Y-m-d', strtotime( $source . ' +' . ( MMR_DAY_COLUMNS - 1 ) . ' days' ) );
		$lessons = MMR_Data::lessons_between( $source, $source_end );
		$day_offset = ( strtotime( $target ) - strtotime( $source ) ) / DAY_IN_SECONDS;

		$copied = 0;
		$skipped = 0;
		foreach ( $lessons as $lesson ) {
			$new_date = wp_date( 'Y-m-d', strtotime( $lesson->lesson_date . ' +' . (int) $day_offset . ' days' ) );
			$slot = (int) $lesson->slot;
			if ( MMR_Data::slot_taken( $new_date, $slot ) ) {
				$skipped++;
				continue;
			}
			// Copy with the same fee snapshot rather than re-deriving from current class fee.
			MMR_Data::create_lesson( $new_date, $slot, (int) $lesson->class_id, (float) $lesson->fee_snapshot, (string) $lesson->note );
			$copied++;
		}
		wp_send_json_success( array( 'copied' => $copied, 'skipped' => $skipped ) );
	}

	/**
	 * Validate a Y-m-d date string.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	protected function valid_date( $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
			return false;
		}
		$parts = explode( '-', $date );
		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
	}

	/**
	 * Serialise a lesson row for the calendar JS.
	 *
	 * @param object $lesson Lesson row.
	 * @return array
	 */
	protected function lesson_payload( $lesson ) {
		return array(
			'id'          => (int) $lesson->id,
			'date'        => $lesson->lesson_date,
			'slot'        => (int) $lesson->slot,
			'class_id'    => (int) $lesson->class_id,
			'class_name'  => $lesson->class_name,
			'fee'         => (float) $lesson->fee_snapshot,
			'fee_display' => mmr_money( $lesson->fee_snapshot ),
			'note'        => (string) $lesson->note,
		);
	}
}
