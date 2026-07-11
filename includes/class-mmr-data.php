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
			);
		}

		usort( $rows, function ( $a, $b ) {
			return strcmp( $a['class_name'], $b['class_name'] );
		} );

		return array(
			'rows'        => $rows,
			'grand_total' => round( $grand_total, 2 ),
		);
	}
}
