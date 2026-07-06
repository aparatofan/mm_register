<?php
/**
 * Data access layer: queries and business calculations.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static query & calculation helpers. All SQL uses $wpdb->prepare.
 */
class MMR_Data {

	/* ---------------------------------------------------------------------
	 * Students
	 * ------------------------------------------------------------------ */

	/**
	 * Fetch students.
	 *
	 * @param array $args status => active|archived|all, search => string.
	 * @return array
	 */
	public static function students( $args = array() ) {
		global $wpdb;
		$table = MMR_DB::table( 'students' );
		$status = isset( $args['status'] ) ? $args['status'] : 'all';

		$where = '1=1';
		$params = array();
		if ( 'all' !== $status ) {
			$where .= ' AND status = %s';
			$params[] = $status;
		}
		if ( ! empty( $args['search'] ) ) {
			$where .= ' AND name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY name ASC";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Single student.
	 *
	 * @param int $id Student ID.
	 * @return object|null
	 */
	public static function student( $id ) {
		global $wpdb;
		$table = MMR_DB::table( 'students' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Insert or update a student.
	 *
	 * @param array $data Sanitised fields.
	 * @param int   $id   Existing ID or 0.
	 * @return int Student ID.
	 */
	public static function save_student( $data, $id = 0 ) {
		global $wpdb;
		$table = MMR_DB::table( 'students' );
		$now = current_time( 'mysql' );

		$row = array(
			'name'            => $data['name'],
			'email'           => $data['email'],
			'tbt_monthly_fee' => $data['tbt_monthly_fee'],
			'notes'           => $data['notes'],
			'status'          => $data['status'],
			'updated_at'      => $now,
		);
		$formats = array( '%s', '%s', '%f', '%s', '%s', '%s' );

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ), $formats, array( '%d' ) );
			return $id;
		}
		$row['created_at'] = $now;
		$formats[] = '%s';
		$wpdb->insert( $table, $row, $formats );
		return (int) $wpdb->insert_id;
	}

	/* ---------------------------------------------------------------------
	 * Classes & members
	 * ------------------------------------------------------------------ */

	/**
	 * Fetch classes.
	 *
	 * @param array $args status, search.
	 * @return array
	 */
	public static function classes( $args = array() ) {
		global $wpdb;
		$table = MMR_DB::table( 'classes' );
		$status = isset( $args['status'] ) ? $args['status'] : 'all';

		$where = '1=1';
		$params = array();
		if ( 'all' !== $status ) {
			$where .= ' AND status = %s';
			$params[] = $status;
		}
		if ( ! empty( $args['search'] ) ) {
			$where .= ' AND name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY COALESCE(NULLIF(sort_name,''), name) ASC";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Single class.
	 *
	 * @param int $id Class ID.
	 * @return object|null
	 */
	public static function class_row( $id ) {
		global $wpdb;
		$table = MMR_DB::table( 'classes' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Distinct existing client_type values (for datalist suggestions).
	 *
	 * @return string[]
	 */
	public static function client_types() {
		global $wpdb;
		$table = MMR_DB::table( 'classes' );
		$rows = $wpdb->get_col( "SELECT DISTINCT client_type FROM {$table} WHERE client_type <> '' ORDER BY client_type ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL
		return array_filter( (array) $rows );
	}

	/**
	 * Members of a class, joined with student data.
	 *
	 * @param int $class_id Class ID.
	 * @return array
	 */
	public static function class_members( $class_id ) {
		global $wpdb;
		$m = MMR_DB::table( 'class_members' );
		$s = MMR_DB::table( 'students' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT cm.*, st.name AS student_name, st.tbt_monthly_fee
			 FROM {$m} cm
			 JOIN {$s} st ON st.id = cm.student_id
			 WHERE cm.class_id = %d
			 ORDER BY st.name ASC",
			$class_id
		) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Count members of a class.
	 *
	 * @param int $class_id Class ID.
	 * @return int
	 */
	public static function member_count( $class_id ) {
		global $wpdb;
		$m = MMR_DB::table( 'class_members' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$m} WHERE class_id = %d", $class_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Insert / update a class.
	 *
	 * @param array $data Sanitised fields.
	 * @param int   $id   Existing ID or 0.
	 * @return int Class ID.
	 */
	public static function save_class( $data, $id = 0 ) {
		global $wpdb;
		$table = MMR_DB::table( 'classes' );

		$row = array(
			'name'          => $data['name'],
			'fee'           => $data['fee'],
			'client_type'   => $data['client_type'],
			'venue'         => $data['venue'],
			'settle_method' => $data['settle_method'],
			'notes'         => $data['notes'],
			'status'        => $data['status'],
			'sort_name'     => $data['sort_name'],
		);
		$formats = array( '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ), $formats, array( '%d' ) );
			return $id;
		}
		$wpdb->insert( $table, $row, $formats );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Replace the member set of a class.
	 *
	 * @param int   $class_id Class ID.
	 * @param array $members  List of [ student_id, share_per_lesson|null ].
	 */
	public static function set_members( $class_id, $members ) {
		global $wpdb;
		$m = MMR_DB::table( 'class_members' );
		$wpdb->delete( $m, array( 'class_id' => $class_id ), array( '%d' ) );
		foreach ( $members as $member ) {
			$student_id = (int) $member['student_id'];
			if ( ! $student_id ) {
				continue;
			}
			$share = ( null === $member['share'] || '' === $member['share'] ) ? null : (float) $member['share'];
			$wpdb->insert(
				$m,
				array(
					'class_id'         => $class_id,
					'student_id'       => $student_id,
					'share_per_lesson' => $share,
				),
				array( '%d', '%d', null === $share ? null : '%f' )
			);
		}
	}

	/**
	 * Class names each student belongs to.
	 *
	 * @param int $student_id Student ID.
	 * @return string[]
	 */
	public static function student_class_names( $student_id ) {
		global $wpdb;
		$m = MMR_DB::table( 'class_members' );
		$c = MMR_DB::table( 'classes' );
		return (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT c.name FROM {$m} cm JOIN {$c} c ON c.id = cm.class_id WHERE cm.student_id = %d ORDER BY c.name ASC",
			$student_id
		) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/* ---------------------------------------------------------------------
	 * Lessons
	 * ------------------------------------------------------------------ */

	/**
	 * Lessons for a date range, joined with class name.
	 *
	 * @param string $start Y-m-d inclusive.
	 * @param string $end   Y-m-d inclusive.
	 * @return array Keyed by "date|slot".
	 */
	public static function lessons_between( $start, $end ) {
		global $wpdb;
		$l = MMR_DB::table( 'lessons' );
		$c = MMR_DB::table( 'classes' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT le.*, c.name AS class_name
			 FROM {$l} le
			 JOIN {$c} c ON c.id = le.class_id
			 WHERE le.lesson_date BETWEEN %s AND %s
			 ORDER BY le.lesson_date ASC, le.slot ASC",
			$start,
			$end
		) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$map = array();
		foreach ( $rows as $row ) {
			$map[ $row->lesson_date . '|' . (int) $row->slot ] = $row;
		}
		return $map;
	}

	/**
	 * Sum of fee_snapshot for lessons in a month period.
	 *
	 * @param string $period Y-m.
	 * @return float
	 */
	public static function month_actual_revenue( $period ) {
		global $wpdb;
		$l = MMR_DB::table( 'lessons' );
		$total = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(fee_snapshot),0) FROM {$l} WHERE DATE_FORMAT(lesson_date, '%%Y-%%m') = %s",
			$period
		) ); // phpcs:ignore WordPress.DB.PreparedSQL
		return (float) $total;
	}

	/**
	 * Single lesson with class name.
	 *
	 * @param int $id Lesson ID.
	 * @return object|null
	 */
	public static function lesson( $id ) {
		global $wpdb;
		$l = MMR_DB::table( 'lessons' );
		$c = MMR_DB::table( 'classes' );
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT le.*, c.name AS class_name FROM {$l} le JOIN {$c} c ON c.id = le.class_id WHERE le.id = %d",
			$id
		) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Is a slot already occupied?
	 *
	 * @param string $date Y-m-d.
	 * @param int    $slot Slot number.
	 * @return bool
	 */
	public static function slot_taken( $date, $slot ) {
		global $wpdb;
		$l = MMR_DB::table( 'lessons' );
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$l} WHERE lesson_date = %s AND slot = %d",
			$date,
			$slot
		) ); // phpcs:ignore WordPress.DB.PreparedSQL
		return $count > 0;
	}

	/**
	 * Create a lesson, snapshotting fee and member shares.
	 *
	 * @param string   $date     Y-m-d.
	 * @param int      $slot     Slot.
	 * @param int      $class_id Class ID.
	 * @param float|null $fee    Explicit fee override, or null to use class fee.
	 * @param string   $note     Optional note.
	 * @return int Lesson ID.
	 */
	public static function create_lesson( $date, $slot, $class_id, $fee = null, $note = '' ) {
		global $wpdb;
		$l = MMR_DB::table( 'lessons' );
		$class = self::class_row( $class_id );
		if ( ! $class ) {
			return 0;
		}
		$fee_snapshot = ( null === $fee ) ? (float) $class->fee : (float) $fee;
		$shares = self::compute_share_snapshot( $class_id, $fee_snapshot );

		$wpdb->insert(
			$l,
			array(
				'lesson_date'     => $date,
				'slot'            => $slot,
				'class_id'        => $class_id,
				'fee_snapshot'    => $fee_snapshot,
				'shares_snapshot' => $shares ? wp_json_encode( $shares ) : null,
				'note'            => $note,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%f', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Compute a per-member share snapshot for a class at a given lesson fee.
	 * Returns map student_id => share amount. NULL member shares become an
	 * equal split of the lesson fee.
	 *
	 * @param int   $class_id Class ID.
	 * @param float $fee      Lesson fee.
	 * @return array
	 */
	public static function compute_share_snapshot( $class_id, $fee ) {
		$members = self::class_members( $class_id );
		$count = count( $members );
		if ( 0 === $count ) {
			return array();
		}
		$equal = round( $fee / $count, 2 );
		$shares = array();
		foreach ( $members as $member ) {
			$share = ( null === $member->share_per_lesson ) ? $equal : (float) $member->share_per_lesson;
			$shares[ (int) $member->student_id ] = $share;
		}
		return $shares;
	}

	/**
	 * Update a lesson's editable fields.
	 *
	 * @param int   $id   Lesson ID.
	 * @param array $data Fields: class_id, fee_snapshot, note, lesson_date, slot.
	 */
	public static function update_lesson( $id, $data ) {
		global $wpdb;
		$l = MMR_DB::table( 'lessons' );
		$row = array();
		$formats = array();
		if ( isset( $data['class_id'] ) ) {
			$row['class_id'] = (int) $data['class_id'];
			$formats[] = '%d';
			// Re-snapshot shares for the (possibly new) class + fee.
			$fee = isset( $data['fee_snapshot'] ) ? (float) $data['fee_snapshot'] : (float) self::lesson( $id )->fee_snapshot;
			$shares = self::compute_share_snapshot( (int) $data['class_id'], $fee );
			$row['shares_snapshot'] = $shares ? wp_json_encode( $shares ) : null;
			$formats[] = '%s';
		}
		if ( isset( $data['fee_snapshot'] ) ) {
			$row['fee_snapshot'] = (float) $data['fee_snapshot'];
			$formats[] = '%f';
		}
		if ( isset( $data['note'] ) ) {
			$row['note'] = $data['note'];
			$formats[] = '%s';
		}
		if ( isset( $data['lesson_date'] ) ) {
			$row['lesson_date'] = $data['lesson_date'];
			$formats[] = '%s';
		}
		if ( isset( $data['slot'] ) ) {
			$row['slot'] = (int) $data['slot'];
			$formats[] = '%d';
		}
		if ( $row ) {
			$wpdb->update( $l, $row, array( 'id' => $id ), $formats, array( '%d' ) );
		}
	}

	/**
	 * Delete a lesson.
	 *
	 * @param int $id Lesson ID.
	 */
	public static function delete_lesson( $id ) {
		global $wpdb;
		$l = MMR_DB::table( 'lessons' );
		$wpdb->delete( $l, array( 'id' => $id ), array( '%d' ) );
	}

	/* ---------------------------------------------------------------------
	 * Settlement computation
	 * ------------------------------------------------------------------ */

	/**
	 * Compute the settlement breakdown for a month.
	 * One entry per class with >=1 lesson in the period.
	 *
	 * @param string $period Y-m.
	 * @return array {
	 *     rows        => array of class breakdowns,
	 *     grand_total => float,
	 *     settled     => float already recorded (matched by class_id+period),
	 * }
	 */
	public static function settlement( $period ) {
		global $wpdb;
		$l = MMR_DB::table( 'lessons' );
		$c = MMR_DB::table( 'classes' );

		$lessons = $wpdb->get_results( $wpdb->prepare(
			"SELECT le.*, c.name AS class_name, c.settle_method
			 FROM {$l} le JOIN {$c} c ON c.id = le.class_id
			 WHERE DATE_FORMAT(le.lesson_date, '%%Y-%%m') = %s
			 ORDER BY c.name ASC, le.lesson_date ASC",
			$period
		) ); // phpcs:ignore WordPress.DB.PreparedSQL

		$classes = array();
		foreach ( $lessons as $lesson ) {
			$cid = (int) $lesson->class_id;
			if ( ! isset( $classes[ $cid ] ) ) {
				$classes[ $cid ] = array(
					'class_id'      => $cid,
					'class_name'    => $lesson->class_name,
					'settle_method' => $lesson->settle_method,
					'lesson_count'  => 0,
					'lessons_subtotal' => 0.0,
					'fees_used'     => array(),
					'member_totals' => array(), // student_id => amount from lessons.
				);
			}
			$classes[ $cid ]['lesson_count']++;
			$fee = (float) $lesson->fee_snapshot;
			$classes[ $cid ]['lessons_subtotal'] += $fee;
			$key = number_format( $fee, 2, '.', '' );
			$classes[ $cid ]['fees_used'][ $key ] = isset( $classes[ $cid ]['fees_used'][ $key ] ) ? $classes[ $cid ]['fees_used'][ $key ] + 1 : 1;

			// Per-member split from the snapshot (falls back to live equal split).
			$shares = array();
			if ( ! empty( $lesson->shares_snapshot ) ) {
				$decoded = json_decode( $lesson->shares_snapshot, true );
				if ( is_array( $decoded ) ) {
					$shares = $decoded;
				}
			}
			if ( empty( $shares ) ) {
				$shares = self::compute_share_snapshot( $cid, $fee );
			}
			foreach ( $shares as $sid => $amount ) {
				$sid = (int) $sid;
				$classes[ $cid ]['member_totals'][ $sid ] = isset( $classes[ $cid ]['member_totals'][ $sid ] )
					? $classes[ $cid ]['member_totals'][ $sid ] + (float) $amount
					: (float) $amount;
			}
		}

		// Add TBT surcharges and finalise per-class + per-member totals.
		$rows = array();
		$grand_total = 0.0;
		foreach ( $classes as $cid => $data ) {
			$members = self::class_members( $cid );
			$member_names = array();
			$member_tbt = array();
			foreach ( $members as $member ) {
				$member_names[ (int) $member->student_id ] = $member->student_name;
				$member_tbt[ (int) $member->student_id ] = (float) $member->tbt_monthly_fee;
			}

			$tbt_lines = array();
			$tbt_total = 0.0;
			// A member earns their TBT surcharge if they had >=1 lesson this month,
			// i.e. they appear in member_totals.
			foreach ( $data['member_totals'] as $sid => $amount ) {
				$fee = isset( $member_tbt[ $sid ] ) ? $member_tbt[ $sid ] : 0.0;
				if ( $fee > 0 ) {
					$tbt_lines[] = array(
						'student_id'   => $sid,
						'student_name' => isset( $member_names[ $sid ] ) ? $member_names[ $sid ] : ( '#' . $sid ),
						'amount'       => $fee,
					);
					$tbt_total += $fee;
				}
			}

			// Build per-member breakdown (lesson amounts + own TBT).
			$member_breakdown = array();
			foreach ( $data['member_totals'] as $sid => $amount ) {
				$tbt = ( isset( $member_tbt[ $sid ] ) && $member_tbt[ $sid ] > 0 ) ? $member_tbt[ $sid ] : 0.0;
				$member_breakdown[] = array(
					'student_id'   => $sid,
					'student_name' => isset( $member_names[ $sid ] ) ? $member_names[ $sid ] : ( '#' . $sid ),
					'lessons_amount' => round( $amount, 2 ),
					'tbt'          => $tbt,
					'total'        => round( $amount + $tbt, 2 ),
				);
			}
			usort( $member_breakdown, function ( $a, $b ) {
				return strcmp( $a['student_name'], $b['student_name'] );
			} );

			$class_total = round( $data['lessons_subtotal'] + $tbt_total, 2 );
			$grand_total += $class_total;

			$rows[] = array(
				'class_id'         => $cid,
				'class_name'       => $data['class_name'],
				'settle_method'    => $data['settle_method'],
				'lesson_count'     => $data['lesson_count'],
				'lessons_subtotal' => round( $data['lessons_subtotal'], 2 ),
				'fees_used'        => $data['fees_used'],
				'tbt_lines'        => $tbt_lines,
				'tbt_total'        => round( $tbt_total, 2 ),
				'class_total'      => $class_total,
				'member_breakdown' => $member_breakdown,
				'is_group'         => count( $members ) > 1,
				'settled'          => self::settlements_for_class_period( $cid, $period ),
			);
		}

		usort( $rows, function ( $a, $b ) {
			return strcmp( $a['class_name'], $b['class_name'] );
		} );

		$settled_amount = 0.0;
		foreach ( $rows as $row ) {
			foreach ( $row['settled'] as $s ) {
				$settled_amount += (float) $s->amount;
			}
		}

		return array(
			'rows'        => $rows,
			'grand_total' => round( $grand_total, 2 ),
			'settled'     => round( $settled_amount, 2 ),
		);
	}

	/**
	 * Existing settlement rows recorded for a class + period.
	 *
	 * @param int    $class_id Class ID.
	 * @param string $period   Y-m.
	 * @return array
	 */
	public static function settlements_for_class_period( $class_id, $period ) {
		global $wpdb;
		$t = MMR_DB::table( 'settlements' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t} WHERE class_id = %d AND period = %s ORDER BY id ASC",
			$class_id,
			$period
		) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/* ---------------------------------------------------------------------
	 * Settlements (receivables register)
	 * ------------------------------------------------------------------ */

	/**
	 * Query settlements with filters.
	 *
	 * @param array $args period, payer, paid (all|paid|unpaid), year.
	 * @return array
	 */
	public static function settlements( $args = array() ) {
		global $wpdb;
		$t = MMR_DB::table( 'settlements' );
		$where = '1=1';
		$params = array();

		if ( ! empty( $args['period'] ) ) {
			$where .= ' AND period = %s';
			$params[] = $args['period'];
		}
		if ( ! empty( $args['payer'] ) ) {
			$where .= ' AND payer_label LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['payer'] ) . '%';
		}
		if ( ! empty( $args['year'] ) ) {
			$where .= ' AND YEAR(doc_date) = %d';
			$params[] = (int) $args['year'];
		}
		if ( isset( $args['paid'] ) && 'all' !== $args['paid'] ) {
			$where .= ' AND paid = %d';
			$params[] = ( 'paid' === $args['paid'] ) ? 1 : 0;
		}

		$sql = "SELECT * FROM {$t} WHERE {$where} ORDER BY doc_date DESC, id DESC";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Single settlement.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function settlement_row( $id ) {
		global $wpdb;
		$t = MMR_DB::table( 'settlements' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Insert / update a settlement.
	 *
	 * @param array $data Sanitised fields.
	 * @param int   $id   Existing ID or 0.
	 * @return int
	 */
	public static function save_settlement( $data, $id = 0 ) {
		global $wpdb;
		$t = MMR_DB::table( 'settlements' );
		$row = array(
			'doc_type'    => $data['doc_type'],
			'doc_number'  => $data['doc_number'],
			'doc_date'    => $data['doc_date'] ? $data['doc_date'] : null,
			'payer_label' => $data['payer_label'],
			'class_id'    => ! empty( $data['class_id'] ) ? (int) $data['class_id'] : null,
			'period'      => ! empty( $data['period'] ) ? $data['period'] : null,
			'amount'      => $data['amount'],
			'ksef'        => ! empty( $data['ksef'] ) ? 1 : 0,
			'sent'        => ! empty( $data['sent'] ) ? 1 : 0,
			'paid'        => ! empty( $data['paid'] ) ? 1 : 0,
			'paid_on'     => ! empty( $data['paid_on'] ) ? $data['paid_on'] : null,
			'notes'       => $data['notes'],
		);
		$formats = array( '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%d', '%d', '%d', '%s', '%s' );

		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ), $formats, array( '%d' ) );
			return $id;
		}
		$wpdb->insert( $t, $row, $formats );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a settlement.
	 *
	 * @param int $id ID.
	 */
	public static function delete_settlement( $id ) {
		global $wpdb;
		$t = MMR_DB::table( 'settlements' );
		$wpdb->delete( $t, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Total of unpaid settlements — the receivables figure.
	 *
	 * @return float
	 */
	public static function unpaid_total() {
		global $wpdb;
		$t = MMR_DB::table( 'settlements' );
		return (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$t} WHERE paid = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Unpaid count + oldest unpaid doc_date.
	 *
	 * @return array [ count, oldest_date|null ]
	 */
	public static function unpaid_summary() {
		global $wpdb;
		$t = MMR_DB::table( 'settlements' );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE paid = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$oldest = $wpdb->get_var( "SELECT MIN(doc_date) FROM {$t} WHERE paid = 0 AND doc_date IS NOT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL
		return array( $count, $oldest );
	}

	/* ---------------------------------------------------------------------
	 * Budget
	 * ------------------------------------------------------------------ */

	/**
	 * Budget items, optionally by kind.
	 *
	 * @param string|null $kind asset|liability|reserve|note_only|null (all).
	 * @return array
	 */
	public static function budget_items( $kind = null ) {
		global $wpdb;
		$t = MMR_DB::table( 'budget_items' );
		if ( $kind ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE kind = %s ORDER BY sort_order ASC, id ASC", $kind ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY kind ASC, sort_order ASC, id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Sum of amounts for a budget kind.
	 *
	 * @param string $kind Kind.
	 * @return float
	 */
	public static function budget_sum( $kind ) {
		global $wpdb;
		$t = MMR_DB::table( 'budget_items' );
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$t} WHERE kind = %s", $kind ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Insert / update a budget item.
	 *
	 * @param array $data Fields.
	 * @param int   $id   ID or 0.
	 * @return int
	 */
	public static function save_budget_item( $data, $id = 0 ) {
		global $wpdb;
		$t = MMR_DB::table( 'budget_items' );
		$row = array(
			'label'      => $data['label'],
			'amount'     => $data['amount'],
			'kind'       => $data['kind'],
			'sort_order' => (int) $data['sort_order'],
			'notes'      => $data['notes'],
			'updated_at' => current_time( 'mysql' ),
		);
		$formats = array( '%s', '%f', '%s', '%d', '%s', '%s' );
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ), $formats, array( '%d' ) );
			return $id;
		}
		$wpdb->insert( $t, $row, $formats );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a budget item.
	 *
	 * @param int $id ID.
	 */
	public static function delete_budget_item( $id ) {
		global $wpdb;
		$t = MMR_DB::table( 'budget_items' );
		$wpdb->delete( $t, array( 'id' => $id ), array( '%d' ) );
	}

	/* ---------------------------------------------------------------------
	 * Recurring expenses
	 * ------------------------------------------------------------------ */

	/**
	 * Expense definitions.
	 *
	 * @param array $args frequency, active.
	 * @return array
	 */
	public static function expenses( $args = array() ) {
		global $wpdb;
		$t = MMR_DB::table( 'recurring_expenses' );
		$where = '1=1';
		$params = array();
		if ( isset( $args['frequency'] ) ) {
			$where .= ' AND frequency = %s';
			$params[] = $args['frequency'];
		}
		if ( isset( $args['active'] ) ) {
			$where .= ' AND active = %d';
			$params[] = (int) $args['active'];
		}
		$sql = "SELECT * FROM {$t} WHERE {$where} ORDER BY category ASC, title ASC";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Single expense.
	 *
	 * @param int $id ID.
	 * @return object|null
	 */
	public static function expense( $id ) {
		global $wpdb;
		$t = MMR_DB::table( 'recurring_expenses' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Insert / update an expense definition.
	 *
	 * @param array $data Fields.
	 * @param int   $id   ID or 0.
	 * @return int
	 */
	public static function save_expense( $data, $id = 0 ) {
		global $wpdb;
		$t = MMR_DB::table( 'recurring_expenses' );
		$row = array(
			'title'     => $data['title'],
			'projected' => $data['projected'],
			'pay_type'  => $data['pay_type'],
			'frequency' => $data['frequency'],
			'category'  => $data['category'],
			'due_note'  => $data['due_note'],
			'active'    => ! empty( $data['active'] ) ? 1 : 0,
		);
		$formats = array( '%s', '%f', '%s', '%s', '%s', '%s', '%d' );
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ), $formats, array( '%d' ) );
			return $id;
		}
		$wpdb->insert( $t, $row, $formats );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete an expense definition (and its payment rows).
	 *
	 * @param int $id ID.
	 */
	public static function delete_expense( $id ) {
		global $wpdb;
		$t = MMR_DB::table( 'recurring_expenses' );
		$p = MMR_DB::table( 'expense_payments' );
		$wpdb->delete( $t, array( 'id' => $id ), array( '%d' ) );
		$wpdb->delete( $p, array( 'expense_id' => $id ), array( '%d' ) );
	}

	/**
	 * Payment row for an expense in a period.
	 *
	 * @param int    $expense_id Expense ID.
	 * @param string $period     Y-m.
	 * @return object|null
	 */
	public static function expense_payment( $expense_id, $period ) {
		global $wpdb;
		$p = MMR_DB::table( 'expense_payments' );
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$p} WHERE expense_id = %d AND period = %s",
			$expense_id,
			$period
		) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Mark an expense paid (or update its spent amount) for a period.
	 *
	 * @param int    $expense_id Expense ID.
	 * @param string $period     Y-m.
	 * @param float  $spent      Actual spent.
	 * @param string $paid_on    Y-m-d.
	 */
	public static function mark_expense_paid( $expense_id, $period, $spent, $paid_on ) {
		global $wpdb;
		$p = MMR_DB::table( 'expense_payments' );
		$existing = self::expense_payment( $expense_id, $period );
		if ( $existing ) {
			$wpdb->update(
				$p,
				array( 'spent' => $spent, 'paid_on' => $paid_on ),
				array( 'id' => $existing->id ),
				array( '%f', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$p,
				array(
					'expense_id' => $expense_id,
					'period'     => $period,
					'spent'      => $spent,
					'paid_on'    => $paid_on,
				),
				array( '%d', '%s', '%f', '%s' )
			);
		}
	}

	/**
	 * Un-mark (delete) a payment row.
	 *
	 * @param int    $expense_id Expense ID.
	 * @param string $period     Y-m.
	 */
	public static function unmark_expense_paid( $expense_id, $period ) {
		global $wpdb;
		$p = MMR_DB::table( 'expense_payments' );
		$wpdb->delete( $p, array( 'expense_id' => $expense_id, 'period' => $period ), array( '%d', '%s' ) );
	}

	/**
	 * Remaining recurring monthly expense for a period:
	 * SUM(projected of active monthly) − SUM(spent recorded this period).
	 *
	 * @param string $period Y-m.
	 * @return array [ projected_total, spent_total, remaining ]
	 */
	public static function expenses_month_summary( $period ) {
		global $wpdb;
		$t = MMR_DB::table( 'recurring_expenses' );
		$p = MMR_DB::table( 'expense_payments' );

		$projected = (float) $wpdb->get_var( "SELECT COALESCE(SUM(projected),0) FROM {$t} WHERE active = 1 AND frequency = 'monthly'" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$spent = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(ep.spent),0) FROM {$p} ep JOIN {$t} e ON e.id = ep.expense_id
			 WHERE ep.period = %s AND e.active = 1 AND e.frequency = 'monthly'",
			$period
		) ); // phpcs:ignore WordPress.DB.PreparedSQL

		$remaining = max( 0, $projected - $spent );
		return array( round( $projected, 2 ), round( $spent, 2 ), round( $remaining, 2 ) );
	}

	/* ---------------------------------------------------------------------
	 * Projections
	 * ------------------------------------------------------------------ */

	/**
	 * All projections, newest period first.
	 *
	 * @return array
	 */
	public static function projections() {
		global $wpdb;
		$t = MMR_DB::table( 'projections' );
		return $wpdb->get_results( "SELECT * FROM {$t} ORDER BY period DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Insert / update a projection by period (upsert).
	 *
	 * @param array $data period, projected, expenses, notes.
	 * @param int   $id   ID or 0.
	 * @return int
	 */
	public static function save_projection( $data, $id = 0 ) {
		global $wpdb;
		$t = MMR_DB::table( 'projections' );
		$row = array(
			'period'    => $data['period'],
			'projected' => $data['projected'],
			'expenses'  => $data['expenses'],
			'notes'     => $data['notes'],
		);
		$formats = array( '%s', '%f', '%f', '%s' );
		if ( $id ) {
			$wpdb->update( $t, $row, array( 'id' => $id ), $formats, array( '%d' ) );
			return $id;
		}
		// Upsert on period.
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE period = %s", $data['period'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( $existing ) {
			$wpdb->update( $t, $row, array( 'id' => $existing ), $formats, array( '%d' ) );
			return (int) $existing;
		}
		$wpdb->insert( $t, $row, $formats );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete a projection.
	 *
	 * @param int $id ID.
	 */
	public static function delete_projection( $id ) {
		global $wpdb;
		$t = MMR_DB::table( 'projections' );
		$wpdb->delete( $t, array( 'id' => $id ), array( '%d' ) );
	}
}
