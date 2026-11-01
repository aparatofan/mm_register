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
	 * @param array $args status => active|archived|all, search => string
	 *                    (name/email/email_2/phone), orderby => name|level.
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
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= ' AND (name LIKE %s OR email LIKE %s OR email_2 LIKE %s OR phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$orderby = ( isset( $args['orderby'] ) && 'level' === $args['orderby'] )
			? 'level ASC, name ASC'
			: 'name ASC';
		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby}";
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
			'email_2'         => isset( $data['email_2'] ) ? $data['email_2'] : '',
			'phone'           => isset( $data['phone'] ) ? $data['phone'] : '',
			'level'           => isset( $data['level'] ) ? $data['level'] : '',
			'tbt_monthly_fee' => $data['tbt_monthly_fee'],
			'notes'           => $data['notes'],
			'status'          => $data['status'],
			'updated_at'      => $now,
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' );

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ), $formats, array( '%d' ) );
			return $id;
		}
		$row['created_at'] = $now;
		$formats[] = '%s';
		$wpdb->insert( $table, $row, $formats );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Archive / restore a student.
	 *
	 * @param int    $id     Student ID.
	 * @param string $status active|archived.
	 */
	public static function set_student_status( $id, $status ) {
		global $wpdb;
		$table = MMR_DB::table( 'students' );
		$wpdb->update(
			$table,
			array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/* ---------------------------------------------------------------------
	 * Classes & members
	 * ------------------------------------------------------------------ */

	/**
	 * Fetch classes.
	 *
	 * @param array $args status, search, client_type, company_id, orderby
	 *                   ('status' puts active classes before archived ones).
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
		if ( ! empty( $args['client_type'] ) ) {
			$where .= ' AND client_type = %s';
			$params[] = $args['client_type'];
		}
		if ( ! empty( $args['company_id'] ) ) {
			$where .= ' AND company_id = %d';
			$params[] = (int) $args['company_id'];
		}

		// 'active' sorts before 'archived', so the enum's own order is the one
		// the picker wants: still-running classes first, history below.
		$order_sql = ( isset( $args['orderby'] ) && 'status' === $args['orderby'] )
			? 'status ASC, name ASC'
			: 'name ASC';

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$order_sql}";
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
	 * Distinct existing values of a classes column (for datalist suggestions).
	 *
	 * @param string $field client_type|settle_method.
	 * @return string[]
	 */
	public static function class_field_values( $field ) {
		global $wpdb;
		if ( ! in_array( $field, array( 'client_type', 'settle_method' ), true ) ) {
			return array();
		}
		$table = MMR_DB::table( 'classes' );
		// Column name comes from the whitelist above; safe to interpolate.
		$rows = $wpdb->get_col( "SELECT DISTINCT {$field} FROM {$table} WHERE {$field} IS NOT NULL AND {$field} <> '' ORDER BY {$field} ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL
		return array_filter( (array) $rows );
	}

	/**
	 * Fetch class rows keyed by ID, in one query.
	 *
	 * Any status: an event may legitimately be raised against an archived
	 * class, and a historical event must stay editable.
	 *
	 * @param int[] $ids Class IDs.
	 * @return array id => row object.
	 */
	public static function classes_by_ids( $ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		if ( empty( $ids ) ) {
			return array();
		}
		$table = MMR_DB::table( 'classes' );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ({$placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->id ] = $row;
		}
		return $map;
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

		$company_id = ! empty( $data['company_id'] ) ? (int) $data['company_id'] : null;
		$row = array(
			'name'          => $data['name'],
			'fee'           => $data['fee'],
			'client_type'   => $data['client_type'],
			'settle_method' => $data['settle_method'],
			'company_id'    => $company_id,
			// Every writer (form, import, quick-add) goes through here; absent = 0.
			'pay_per_member' => empty( $data['pay_per_member'] ) ? 0 : 1,
			'notes'         => $data['notes'],
			'status'        => $data['status'],
		);
		$formats = array( '%s', '%f', '%s', '%s', null === $company_id ? null : '%d', '%d', '%s', '%s' );

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ), $formats, array( '%d' ) );
			return $id;
		}
		$wpdb->insert( $table, $row, $formats );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Archive / restore a class.
	 *
	 * @param int    $id     Class ID.
	 * @param string $status active|archived.
	 */
	public static function set_class_status( $id, $status ) {
		global $wpdb;
		$table = MMR_DB::table( 'classes' );
		$wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
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
	 * Companies
	 * ------------------------------------------------------------------ */

	/**
	 * Fetch companies.
	 *
	 * @param array $args status => active|archived|all, search => string (name/nip).
	 * @return array
	 */
	public static function companies( $args = array() ) {
		global $wpdb;
		$table = MMR_DB::table( 'companies' );
		$status = isset( $args['status'] ) ? $args['status'] : 'all';

		$where = '1=1';
		$params = array();
		if ( 'all' !== $status ) {
			$where .= ' AND status = %s';
			$params[] = $status;
		}
		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= ' AND (name LIKE %s OR nip LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY name ASC";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Single company.
	 *
	 * @param int $id Company ID.
	 * @return object|null
	 */
	public static function company( $id ) {
		global $wpdb;
		$table = MMR_DB::table( 'companies' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Find a company by exact name (case-insensitive).
	 *
	 * @param string   $name       Company name.
	 * @param int      $exclude_id Optional ID to exclude (for duplicate checks on edit).
	 * @return object|null
	 */
	public static function company_by_name( $name, $exclude_id = 0 ) {
		global $wpdb;
		$table = MMR_DB::table( 'companies' );
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE LOWER(name) = LOWER(%s) AND id <> %d LIMIT 1",
			$name,
			$exclude_id
		) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * id => name map for companies (all statuses), for resolving links in lists.
	 *
	 * @return array
	 */
	public static function company_names() {
		global $wpdb;
		$table = MMR_DB::table( 'companies' );
		$rows = $wpdb->get_results( "SELECT id, name FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->id ] = $row->name;
		}
		return $map;
	}

	/**
	 * Insert / update a company.
	 *
	 * @param array $data Sanitised fields.
	 * @param int   $id   Existing ID or 0.
	 * @return int Company ID.
	 */
	public static function save_company( $data, $id = 0 ) {
		global $wpdb;
		$table = MMR_DB::table( 'companies' );
		$now = current_time( 'mysql' );

		$row = array(
			'name'       => $data['name'],
			'address'    => $data['address'],
			'nip'        => $data['nip'],
			'notes'      => $data['notes'],
			'status'     => $data['status'],
			'updated_at' => $now,
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ), $formats, array( '%d' ) );
			return $id;
		}
		$row['created_at'] = $now;
		$formats[] = '%s';
		$wpdb->insert( $table, $row, $formats );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Archive / restore a company.
	 *
	 * @param int    $id     Company ID.
	 * @param string $status active|archived.
	 */
	public static function set_company_status( $id, $status ) {
		global $wpdb;
		$table = MMR_DB::table( 'companies' );
		$wpdb->update(
			$table,
			array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Number of classes linked to a company.
	 *
	 * @param int $company_id Company ID.
	 * @return int
	 */
	public static function company_class_count( $company_id ) {
		global $wpdb;
		$c = MMR_DB::table( 'classes' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$c} WHERE company_id = %d", $company_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Names of classes linked to a company (for the archive warning).
	 *
	 * @param int $company_id Company ID.
	 * @return string[]
	 */
	public static function company_class_names( $company_id ) {
		global $wpdb;
		$c = MMR_DB::table( 'classes' );
		return (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT name FROM {$c} WHERE company_id = %d ORDER BY name ASC",
			$company_id
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
	 * Settlement computation (read-only, derived from lessons)
	 * ------------------------------------------------------------------ */

	/**
	 * Compute the monthly settlement report. Nothing is stored — everything
	 * is derived from mmr_lessons (fee + share snapshots), so any month is
	 * reproducible.
	 *
	 * @param string $period Y-m.
	 * @return array {
	 *     classes       => one row per class with >=1 lesson: class_id,
	 *                      class_name, client_type, settle_method, company,
	 *                      lesson_count, fees_used, class_total (Σ fee_snapshot),
	 *                      tbt_total (surcharges attributed to this class),
	 *                      to_bill (class_total + tbt_total — what to invoice),
	 *                      members (per-student breakdown incl. TBT),
	 *     students      => per-student summary: student_id, name, company,
	 *                      lessons, lesson_total, tbt (added once per month),
	 *                      to_bill,
	 *     month_total   => Σ class totals (lesson fees only),
	 *     month_to_bill => Σ class to_bill (lesson fees + TBT surcharges),
	 *     to_bill_total => Σ per-student to_bill (lesson shares + TBT),
	 * }
	 */
	public static function settlement( $period ) {
		global $wpdb;
		$l = MMR_DB::table( 'lessons' );
		$c = MMR_DB::table( 'classes' );
		$co = MMR_DB::table( 'companies' );

		$lessons = $wpdb->get_results( $wpdb->prepare(
			"SELECT le.*, c.name AS class_name, c.client_type, c.settle_method,
			        c.pay_per_member, co.name AS company_name
			 FROM {$l} le
			 JOIN {$c} c ON c.id = le.class_id
			 LEFT JOIN {$co} co ON co.id = c.company_id
			 WHERE DATE_FORMAT(le.lesson_date, '%%Y-%%m') = %s
			 ORDER BY c.name ASC, le.lesson_date ASC",
			$period
		) ); // phpcs:ignore WordPress.DB.PreparedSQL

		$classes = array();
		$per_student = array(); // student_id => [ lessons, lesson_total, companies ].
		foreach ( $lessons as $lesson ) {
			$cid = (int) $lesson->class_id;
			$company = (string) $lesson->company_name;
			if ( ! isset( $classes[ $cid ] ) ) {
				$classes[ $cid ] = array(
					'class_id'      => $cid,
					'class_name'    => $lesson->class_name,
					'client_type'   => $lesson->client_type,
					'settle_method' => $lesson->settle_method,
					'company'       => $company,
					// Billing switch only — no figure below depends on it.
					'pay_per_member' => (int) $lesson->pay_per_member,
					'lesson_count'  => 0,
					'class_total'   => 0.0,
					'fees_used'     => array(),
					'member_totals' => array(), // student_id => [ lessons, amount ].
				);
			}
			$classes[ $cid ]['lesson_count']++;
			$fee = (float) $lesson->fee_snapshot;
			$classes[ $cid ]['class_total'] += $fee;
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
				$amount = (float) $amount;
				if ( ! isset( $classes[ $cid ]['member_totals'][ $sid ] ) ) {
					$classes[ $cid ]['member_totals'][ $sid ] = array( 'lessons' => 0, 'amount' => 0.0 );
				}
				$classes[ $cid ]['member_totals'][ $sid ]['lessons']++;
				$classes[ $cid ]['member_totals'][ $sid ]['amount'] += $amount;

				if ( ! isset( $per_student[ $sid ] ) ) {
					$per_student[ $sid ] = array( 'lessons' => 0, 'lesson_total' => 0.0, 'companies' => array() );
				}
				$per_student[ $sid ]['lessons']++;
				$per_student[ $sid ]['lesson_total'] += $amount;
				if ( '' !== $company ) {
					$per_student[ $sid ]['companies'][ $company ] = true;
				}
			}
		}

		// Names + TBT fees for every student that appears in a share snapshot
		// (fetched directly so archived / since-removed members still resolve).
		$student_rows = self::students_by_ids( array_keys( $per_student ) );

		$name_of = function ( $sid ) use ( $student_rows ) {
			return isset( $student_rows[ $sid ] ) ? $student_rows[ $sid ]->name : ( '#' . $sid );
		};
		$tbt_of = function ( $sid ) use ( $student_rows ) {
			return isset( $student_rows[ $sid ] ) ? (float) $student_rows[ $sid ]->tbt_monthly_fee : 0.0;
		};

		// Which class carries each student's TBT surcharge this month.
		//
		// The surcharge is a flat monthly amount per student, charged once
		// however many classes they attend (§3.2) — so it cannot simply be
		// added to every class the student appears in. It is attributed to the
		// class where they had the most lessons this month; ties go to the
		// first class by name, then by id, so the same month always resolves
		// the same way. Every class row below then bills the lessons plus
		// whatever surcharges landed on it, and the per-student table still
		// shows each student's own surcharge exactly once.
		$tbt_class = array();
		foreach ( array_keys( $per_student ) as $sid ) {
			if ( $tbt_of( $sid ) <= 0 ) {
				continue;
			}
			$best = null;
			foreach ( $classes as $cid => $data ) {
				if ( ! isset( $data['member_totals'][ $sid ] ) ) {
					continue;
				}
				$lessons = (int) $data['member_totals'][ $sid ]['lessons'];
				if ( null === $best
					|| $lessons > $best['lessons']
					|| ( $lessons === $best['lessons'] && strcmp( $data['class_name'], $best['name'] ) < 0 )
					|| ( $lessons === $best['lessons'] && $data['class_name'] === $best['name'] && $cid < $best['id'] )
				) {
					$best = array( 'lessons' => $lessons, 'name' => $data['class_name'], 'id' => $cid );
				}
			}
			if ( $best ) {
				$tbt_class[ $sid ] = $best['id'];
			}
		}

		// Per-class rows with member breakdown.
		$class_rows = array();
		$month_total = 0.0;
		$month_to_bill = 0.0;
		foreach ( $classes as $cid => $data ) {
			$members = array();
			$tbt_total = 0.0;
			foreach ( $data['member_totals'] as $sid => $mt ) {
				$tbt = ( isset( $tbt_class[ $sid ] ) && $tbt_class[ $sid ] === $cid ) ? $tbt_of( $sid ) : 0.0;
				$tbt_total += $tbt;
				$members[] = array(
					'student_id'   => $sid,
					'student_name' => $name_of( $sid ),
					'lessons'      => $mt['lessons'],
					'lessons_amount' => round( $mt['amount'], 2 ),
					'tbt'          => $tbt,
					'total'        => round( $mt['amount'] + $tbt, 2 ),
				);
			}
			usort( $members, function ( $a, $b ) {
				return strcmp( $a['student_name'], $b['student_name'] );
			} );

			$data['class_total'] = round( $data['class_total'], 2 );
			$data['tbt_total'] = round( $tbt_total, 2 );
			$data['to_bill'] = round( $data['class_total'] + $tbt_total, 2 );
			$data['members'] = $members;
			unset( $data['member_totals'] );
			$month_total += $data['class_total'];
			$month_to_bill += $data['to_bill'];
			$class_rows[] = $data;
		}

		usort( $class_rows, function ( $a, $b ) {
			return strcmp( $a['class_name'], $b['class_name'] );
		} );

		// Per-student summary: TBT surcharge added ONCE per month when the
		// student had >=1 lesson, regardless of how many classes they attend.
		$student_rows_out = array();
		$to_bill_total = 0.0;
		foreach ( $per_student as $sid => $ps ) {
			$tbt = $tbt_of( $sid );
			$to_bill = round( $ps['lesson_total'] + $tbt, 2 );
			$company_names = array_keys( $ps['companies'] );
			sort( $company_names );
			$student_rows_out[] = array(
				'student_id'   => $sid,
				'student_name' => $name_of( $sid ),
				'company'      => implode( ', ', $company_names ),
				'lessons'      => $ps['lessons'],
				'lesson_total' => round( $ps['lesson_total'], 2 ),
				'tbt'          => $tbt,
				'to_bill'      => $to_bill,
			);
			$to_bill_total += $to_bill;
		}
		usort( $student_rows_out, function ( $a, $b ) {
			return strcmp( $a['student_name'], $b['student_name'] );
		} );

		return array(
			'classes'       => $class_rows,
			'students'      => $student_rows_out,
			'month_total'   => round( $month_total, 2 ),
			'month_to_bill' => round( $month_to_bill, 2 ),
			'to_bill_total' => round( $to_bill_total, 2 ),
		);
	}

	/**
	 * Fetch student rows keyed by ID.
	 *
	 * @param int[] $ids Student IDs.
	 * @return array id => row object.
	 */
	public static function students_by_ids( $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'intval', (array) $ids ) );
		if ( empty( $ids ) ) {
			return array();
		}
		$table = MMR_DB::table( 'students' );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ({$placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->id ] = $row;
		}
		return $map;
	}

	/* ---------------------------------------------------------------------
	 * Events — the receivables register
	 *
	 * A flat, chronological list of billing documents: what was issued, to
	 * whom, for how much, and whether the money arrived. Amounts are
	 * snapshots (see normalize_event) — they never re-derive from lessons.
	 * ------------------------------------------------------------------ */

	/**
	 * The fixed event type whitelist.
	 *
	 * @return string[]
	 */
	public static function event_types() {
		// `rozliczenie` (2.7) records prepaid lessons drawn down — never money
		// owed, so it has no KSeF / sent / paid and stays out of the totals.
		return array( 'invoice', 'revolut', 'cash', 'rozliczenie' );
	}

	/**
	 * The list's status filter keys.
	 *
	 * @return string[]
	 */
	public static function event_states() {
		return array( 'all', 'draft', 'unpaid', 'paid', 'unsent', 'no_ksef' );
	}

	/**
	 * Source kinds an event's amount may have been taken from.
	 *
	 * @return string[]
	 */
	public static function event_source_kinds() {
		return array( 'class', 'student', 'company' );
	}

	/* --- Who: classes + free text -------------------------------------- */

	/**
	 * Class IDs an event was raised for, in class-name order.
	 *
	 * @param int $event_id Event ID.
	 * @return int[]
	 */
	public static function event_class_ids( $event_id ) {
		global $wpdb;
		$ec = MMR_DB::table( 'event_classes' );
		$c = MMR_DB::table( 'classes' );
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ec.class_id FROM {$ec} ec JOIN {$c} c ON c.id = ec.class_id WHERE ec.event_id = %d ORDER BY c.name ASC",
			$event_id
		) ); // phpcs:ignore WordPress.DB.PreparedSQL
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Classes of many events at once, so a list screen never runs one query
	 * per row.
	 *
	 * @param int[] $event_ids Event IDs.
	 * @return array event_id => list of [ id, name, settle_method, company ].
	 */
	public static function event_classes_map( $event_ids ) {
		global $wpdb;
		$event_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $event_ids ) ) ) );
		if ( empty( $event_ids ) ) {
			return array();
		}
		$ec = MMR_DB::table( 'event_classes' );
		$c = MMR_DB::table( 'classes' );
		$co = MMR_DB::table( 'companies' );
		$placeholders = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ec.event_id, c.id, c.name, c.settle_method, co.name AS company
			 FROM {$ec} ec
			 JOIN {$c} c ON c.id = ec.class_id
			 LEFT JOIN {$co} co ON co.id = c.company_id
			 WHERE ec.event_id IN ({$placeholders})
			 ORDER BY c.name ASC",
			$event_ids
		) ); // phpcs:ignore WordPress.DB.PreparedSQL

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->event_id ][] = self::class_ref_payload( $row );
		}
		return $map;
	}

	/**
	 * Shape one class as the form and the picker consume it: the name to show,
	 * the company to disambiguate it, and the settle method behind the derived
	 * type — so the user can always see why Type resolved as it did.
	 *
	 * @param object $class Class row (needs id, name, settle_method, and either
	 *                      a company alias or company_id).
	 * @return array
	 */
	public static function class_ref_payload( $class ) {
		$company = '';
		if ( isset( $class->company ) ) {
			$company = (string) $class->company;
		} elseif ( ! empty( $class->company_id ) ) {
			$row = self::company( (int) $class->company_id );
			$company = $row ? $row->name : '';
		}
		return array(
			'id'            => (int) $class->id,
			'name'          => (string) $class->name,
			'company'       => $company,
			'settle_method' => (string) $class->settle_method,
			'type_hint'     => self::event_type_hint( $class->settle_method ),
			'status'        => isset( $class->status ) ? (string) $class->status : '',
		);
	}

	/**
	 * Replace the class set of an event.
	 *
	 * @param int   $event_id  Event ID.
	 * @param int[] $class_ids Verified class IDs.
	 */
	public static function set_event_classes( $event_id, $class_ids ) {
		global $wpdb;
		$ec = MMR_DB::table( 'event_classes' );
		$wpdb->delete( $ec, array( 'event_id' => $event_id ), array( '%d' ) );
		foreach ( array_unique( array_map( 'absint', (array) $class_ids ) ) as $class_id ) {
			if ( ! $class_id ) {
				continue;
			}
			$wpdb->insert( $ec, array( 'event_id' => $event_id, 'class_id' => $class_id ), array( '%d', '%d' ) );
		}
	}

	/**
	 * Derive the event type from the settle methods of the selected classes.
	 *
	 * Resolution succeeds only when every selected class maps to the same
	 * event type. Anything else is reported as unresolved with the offending
	 * class names, so the form can say what is wrong instead of guessing —
	 * a wrong type on a financial record is worse than one extra click.
	 *
	 * @param int[] $class_ids Class IDs.
	 * @param array $map       Optional id => class row map, to save a query.
	 * @return array {
	 *     type     => resolved event type, '' when unresolved,
	 *     resolved => bool,
	 *     reason   => ok|none|mixed|unmapped,
	 *     classes  => names behind the reason (conflicting or unmapped),
	 * }
	 */
	public static function derive_event_type( $class_ids, $map = null ) {
		$class_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $class_ids ) ) ) );
		if ( empty( $class_ids ) ) {
			return array( 'type' => '', 'resolved' => false, 'reason' => 'none', 'classes' => array() );
		}
		if ( null === $map ) {
			$map = self::classes_by_ids( $class_ids );
		}

		$hints = array();
		$unmapped = array();
		foreach ( $class_ids as $class_id ) {
			if ( ! isset( $map[ $class_id ] ) ) {
				continue;
			}
			$class = $map[ $class_id ];
			$hint = self::event_type_hint( $class->settle_method );
			if ( '' === $hint ) {
				$unmapped[] = $class->name;
				continue;
			}
			$hints[ $hint ][] = $class->name;
		}

		if ( $unmapped ) {
			return array( 'type' => '', 'resolved' => false, 'reason' => 'unmapped', 'classes' => $unmapped );
		}
		if ( count( $hints ) > 1 ) {
			$conflicting = array();
			foreach ( $hints as $names ) {
				$conflicting = array_merge( $conflicting, $names );
			}
			return array( 'type' => '', 'resolved' => false, 'reason' => 'mixed', 'classes' => $conflicting );
		}
		if ( 1 === count( $hints ) ) {
			$type = key( $hints );
			return array( 'type' => $type, 'resolved' => true, 'reason' => 'ok', 'classes' => array() );
		}
		return array( 'type' => '', 'resolved' => false, 'reason' => 'none', 'classes' => array() );
	}

	/**
	 * The readable "who" written to `payer_label`: every selected class, then
	 * the free-text remainder. Kept because the whole register — search, sort,
	 * export, the list column — reads that one column, and a snapshot records
	 * who was billed rather than who the class is called today.
	 *
	 * @param string[] $names    Class names.
	 * @param string   $who_text Free-text remainder.
	 * @return string
	 */
	public static function compose_payer_label( $names, $who_text ) {
		$parts = array_filter( array_map( 'trim', (array) $names ) );
		$who_text = trim( (string) $who_text );
		if ( '' !== $who_text ) {
			$parts[] = $who_text;
		}
		$label = implode( ', ', $parts );
		// The column is VARCHAR(190); truncate rather than let MySQL do it.
		return ( function_exists( 'mb_substr' ) && mb_strlen( $label ) > 190 ) ? mb_substr( $label, 0, 190 ) : $label;
	}

	/**
	 * Turn posted class references into the fields the validator stores.
	 *
	 * Orphan IDs are discarded, never saved. Where derivation succeeds the
	 * resolved type replaces whatever the client posted — the client value is
	 * a display convenience, not an input.
	 *
	 * @param array $data Raw fields including `class_ids` and `who_text`.
	 * @return array Same data with class_ids verified, payer_label composed and
	 *               type overwritten when it could be derived.
	 */
	public static function resolve_event_who( $data ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $data['class_ids'] ) ) ) );
		$map = self::classes_by_ids( $ids );

		$verified = array();
		$names = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $map[ $id ] ) ) {
				continue; // Unknown class — drop it rather than store a dangling ID.
			}
			$verified[] = $id;
			$names[] = $map[ $id ]->name;
		}

		$data['class_ids'] = $verified;
		$data['payer_label'] = self::compose_payer_label( $names, isset( $data['who_text'] ) ? $data['who_text'] : '' );

		$derived = self::derive_event_type( $verified, $map );
		if ( $derived['resolved'] ) {
			$data['type'] = $derived['type'];
		}
		return $data;
	}

	/**
	 * The single validator every write path runs through: the POST handler,
	 * the AJAX flag toggle and the importer all call it, so their rules can
	 * never drift apart.
	 *
	 * Who and type are resolved before this runs (see resolve_event_who(),
	 * called by save_event whenever the caller passes class references), so
	 * everything below sees an already-derived `type` and `payer_label`.
	 *
	 * Enforced here, not in the UI:
	 * - `ksef` is an invoice-only fact — any other type forces it to 0;
	 * - a paid event always has a payment date (today when none is given);
	 * - an unpaid event never has one;
	 * - `rozliczenie` is never money owed: ksef, sent and paid are forced to 0;
	 * - auto-issue: a document number, or Sent / Paid ticked, clears `is_draft`
	 *   — typing the invoice number in Events is the act of issuing a draft.
	 *
	 * @param array $data Raw fields from any source.
	 * @return array Normalised row ready for the database.
	 */
	public static function normalize_event( $data ) {
		$type = isset( $data['type'] ) ? strtolower( trim( (string) $data['type'] ) ) : '';
		if ( ! in_array( $type, self::event_types(), true ) ) {
			$type = 'invoice';
		}

		$issue_date = isset( $data['issue_date'] ) ? trim( (string) $data['issue_date'] ) : '';
		if ( ! mmr_valid_date( $issue_date ) ) {
			$issue_date = current_time( 'Y-m-d' );
		}

		$is_rozliczenie = ( 'rozliczenie' === $type );
		$paid = ( $is_rozliczenie || empty( $data['paid'] ) ) ? 0 : 1;
		$sent = ( $is_rozliczenie || empty( $data['sent'] ) ) ? 0 : 1;
		$paid_date = isset( $data['paid_date'] ) ? trim( (string) $data['paid_date'] ) : '';
		if ( ! mmr_valid_date( $paid_date ) ) {
			$paid_date = '';
		}
		if ( $paid ) {
			if ( '' === $paid_date ) {
				$paid_date = current_time( 'Y-m-d' );
			}
		} else {
			// A payment date on an unpaid row is nonsense.
			$paid_date = null;
		}

		$source_kind = isset( $data['source_kind'] ) ? strtolower( trim( (string) $data['source_kind'] ) ) : '';
		if ( ! in_array( $source_kind, self::event_source_kinds(), true ) ) {
			$source_kind = '';
		}
		$source_period = isset( $data['source_period'] ) ? mmr_sanitize_period( $data['source_period'] ) : null;
		$source_id = isset( $data['source_id'] ) ? (int) $data['source_id'] : 0;
		if ( '' === $source_kind ) {
			$source_id = 0;
			$source_period = null;
		}

		$doc_number = isset( $data['doc_number'] ) ? sanitize_text_field( (string) $data['doc_number'] ) : '';

		// Drafts (2.7): only the Settlement page creates them; every other
		// writer either omits the flag (a real document) or replays the stored
		// row. The auto-issue rule runs last so it applies to every source.
		$is_draft = empty( $data['is_draft'] ) ? 0 : 1;
		if ( '' !== trim( $doc_number ) || $sent || $paid ) {
			$is_draft = 0;
		}

		// Drift baseline: NULL means "never checked" and must stay NULL — never
		// coerce it to 0.00, which would flag every manual event as drifted.
		$lines_total = null;
		if ( isset( $data['lines_total'] ) && '' !== $data['lines_total'] ) {
			$lines_total = number_format( mmr_sanitize_money( $data['lines_total'] ), 2, '.', '' );
		}

		return array(
			'type'          => $type,
			'doc_number'    => $doc_number,
			'issue_date'    => $issue_date,
			'payer_label'   => isset( $data['payer_label'] ) ? sanitize_text_field( (string) $data['payer_label'] ) : '',
			'who_text'      => isset( $data['who_text'] ) ? sanitize_text_field( (string) $data['who_text'] ) : '',
			'amount'        => isset( $data['amount'] ) ? mmr_sanitize_money( $data['amount'] ) : 0.0,
			'ksef'          => ( 'invoice' === $type && ! empty( $data['ksef'] ) ) ? 1 : 0,
			'sent'          => $sent,
			'paid'          => $paid,
			'paid_date'     => $paid_date,
			'notes'         => isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : '',
			'source_kind'   => '' === $source_kind ? null : $source_kind,
			'source_id'     => $source_id ? $source_id : null,
			'source_period' => $source_period,
			'is_draft'      => $is_draft,
			'lines_total'   => $lines_total,
		);
	}

	/**
	 * Build the shared WHERE clause for the events list and its totals, so a
	 * chip can never be scoped differently from the rows below it.
	 *
	 * @param array $args year, type, state, search, and exclude_non_receivables
	 *                    (totals only: drop drafts and rozliczenie, which are
	 *                    never money issued or owed — the list still shows them).
	 * @return array [ where sql, params ]
	 */
	protected static function event_where( $args ) {
		global $wpdb;

		$where = '1=1';
		$params = array();

		$year = isset( $args['year'] ) ? (int) $args['year'] : 0;
		if ( $year > 0 ) {
			$where .= ' AND YEAR(issue_date) = %d';
			$params[] = $year;
		}

		$type = isset( $args['type'] ) ? (string) $args['type'] : '';
		if ( in_array( $type, self::event_types(), true ) ) {
			$where .= ' AND type = %s';
			$params[] = $type;
		}

		if ( ! empty( $args['exclude_non_receivables'] ) ) {
			$where .= " AND is_draft = 0 AND type <> 'rozliczenie'";
		}

		$state = isset( $args['state'] ) ? (string) $args['state'] : 'all';
		switch ( $state ) {
			case 'draft':
				$where .= ' AND is_draft = 1';
				break;
			case 'unpaid':
				// A draft is not unpaid yet; a rozliczenie is never unpaid.
				$where .= " AND paid = 0 AND is_draft = 0 AND type <> 'rozliczenie'";
				break;
			case 'paid':
				$where .= ' AND paid = 1';
				break;
			case 'unsent':
				$where .= " AND sent = 0 AND is_draft = 0 AND type <> 'rozliczenie'";
				break;
			case 'no_ksef':
				$where .= " AND type = 'invoice' AND ksef = 0";
				break;
		}

		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			// The label is a snapshot, so a class renamed after the document
			// was issued would stop matching: the third clause matches an
			// event when ANY of its classes matches under its current name.
			$ec = MMR_DB::table( 'event_classes' );
			$c = MMR_DB::table( 'classes' );
			$where .= " AND (payer_label LIKE %s OR doc_number LIKE %s"
				. " OR id IN (SELECT ec.event_id FROM {$ec} ec JOIN {$c} c ON c.id = ec.class_id WHERE c.name LIKE %s))";
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return array( $where, $params );
	}

	/**
	 * Fetch events.
	 *
	 * @param array $args year, type, state, search, orderby, order.
	 * @return array
	 */
	public static function events( $args = array() ) {
		global $wpdb;
		$table = MMR_DB::table( 'events' );
		list( $where, $params ) = self::event_where( $args );

		// Newest first by default: the working end of this register is the
		// recent unpaid rows.
		$columns = array( 'issue_date', 'payer_label', 'amount' );
		$orderby = isset( $args['orderby'] ) ? (string) $args['orderby'] : '';
		if ( in_array( $orderby, $columns, true ) ) {
			$order = ( isset( $args['order'] ) && 'asc' === strtolower( (string) $args['order'] ) ) ? 'ASC' : 'DESC';
			$order_sql = $orderby . ' ' . $order . ', id DESC';
		} else {
			$order_sql = 'issue_date DESC, id DESC';
		}

		// $where carries placeholders only; $order_sql is built from the
		// whitelist above.
		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$order_sql}";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Single event.
	 *
	 * @param int $id Event ID.
	 * @return object|null
	 */
	public static function event( $id ) {
		global $wpdb;
		$table = MMR_DB::table( 'events' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Insert / update an event. Every field passes through normalize_event().
	 *
	 * Pass `class_ids` to set the event's classes; the label and the type are
	 * then derived from them and the link rows rewritten. Omit the key
	 * entirely — as the flag toggle does, replaying a stored row — and the
	 * existing classes are left exactly as they are.
	 *
	 * @param array $data Raw fields.
	 * @param int   $id   Existing ID or 0.
	 * @return int Event ID.
	 */
	public static function save_event( $data, $id = 0 ) {
		global $wpdb;
		$table = MMR_DB::table( 'events' );
		$now = current_time( 'mysql' );

		$class_ids = null;
		if ( isset( $data['class_ids'] ) ) {
			$data = self::resolve_event_who( $data );
			$class_ids = $data['class_ids'];
		}

		$row = self::normalize_event( $data );
		$row['updated_at'] = $now;
		// Positional, in normalize_event()'s key order, then updated_at.
		// `lines_total` is %s so a NULL stays NULL (wpdb writes NULL for null
		// values whatever the format) rather than becoming 0.00.
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' );

		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ), $formats, array( '%d' ) );
		} else {
			$row['created_at'] = $now;
			$formats[] = '%s';
			$wpdb->insert( $table, $row, $formats );
			$id = (int) $wpdb->insert_id;
		}

		if ( null !== $class_ids && $id ) {
			self::set_event_classes( $id, $class_ids );
		}
		return $id;
	}

	/**
	 * Delete an event. Unlike students and classes an event is a record of a
	 * single document, and a wrong row must not linger inside a financial
	 * total — so this is a real delete, not an archive.
	 *
	 * @param int $id Event ID.
	 */
	public static function delete_event( $id ) {
		global $wpdb;
		$wpdb->delete( MMR_DB::table( 'event_classes' ), array( 'event_id' => $id ), array( '%d' ) );
		$wpdb->delete( MMR_DB::table( 'events' ), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Flip one of the three inline flags, re-running the shared validator so
	 * the ksef / paid_date rules apply exactly as they do on a form save.
	 *
	 * @param int    $id    Event ID.
	 * @param string $flag  ksef|sent|paid.
	 * @param int    $value 0|1.
	 * @return object|null The updated row, or null when the event is unknown.
	 */
	public static function set_event_flag( $id, $flag, $value ) {
		if ( ! in_array( $flag, array( 'ksef', 'sent', 'paid' ), true ) ) {
			return null;
		}
		$event = self::event( $id );
		if ( ! $event ) {
			return null;
		}
		$data = (array) $event;
		$data[ $flag ] = $value ? 1 : 0;
		self::save_event( $data, (int) $event->id );
		return self::event( $id );
	}

	/**
	 * Issued / received / outstanding for a filter set, in one grouped query.
	 *
	 * @param array $args Same filter args as events().
	 * @return array issued, received, outstanding.
	 */
	public static function event_totals( $args = array() ) {
		global $wpdb;
		$table = MMR_DB::table( 'events' );
		// Drafts and rozliczenie are listed but never counted as issued/owed.
		$args['exclude_non_receivables'] = true;
		list( $where, $params ) = self::event_where( $args );

		$sql = "SELECT paid, COALESCE(SUM(amount),0) AS total FROM {$table} WHERE {$where} GROUP BY paid";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL

		$received = 0.0;
		$outstanding = 0.0;
		foreach ( $rows as $row ) {
			if ( (int) $row->paid ) {
				$received += (float) $row->total;
			} else {
				$outstanding += (float) $row->total;
			}
		}
		return array(
			'issued'      => round( $received + $outstanding, 2 ),
			'received'    => round( $received, 2 ),
			'outstanding' => round( $outstanding, 2 ),
		);
	}

	/**
	 * Years that have at least one event, newest first.
	 *
	 * @return int[]
	 */
	public static function event_years() {
		global $wpdb;
		$table = MMR_DB::table( 'events' );
		$years = $wpdb->get_col( "SELECT DISTINCT YEAR(issue_date) FROM {$table} ORDER BY 1 DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL
		return array_map( 'intval', (array) $years );
	}

	/* --- Amount picker -------------------------------------------------- */

	/**
	 * Map a class settle_method onto an event type, case-insensitively.
	 * `settle_method` is free text with a datalist, not an enum, so this is
	 * the one place that decides what a method means — the Who field's type
	 * derivation and the amount picker both go through it.
	 *
	 * @param string $method Free-text settle method.
	 * @return string invoice|revolut|cash|rozliczenie, or '' when it does not map.
	 */
	public static function event_type_hint( $method ) {
		$method = strtolower( trim( (string) $method ) );
		if ( '' === $method ) {
			return '';
		}
		if ( false !== strpos( $method, 'invoice' ) ) {
			return 'invoice';
		}
		if ( false !== strpos( $method, 'revolut' ) ) {
			return 'revolut';
		}
		if ( false !== strpos( $method, 'cash' ) || false !== strpos( $method, 'gotówka' ) ) {
			return 'cash';
		}
		if ( false !== strpos( $method, 'rozlicz' ) ) {
			return 'rozliczenie';
		}
		return '';
	}

	/**
	 * Events already recorded against a source for a period, keyed
	 * `kind|id` — one query, so the picker can flag a possible double-bill.
	 *
	 * @param string $period Y-m.
	 * @return array
	 */
	protected static function events_by_source( $period ) {
		global $wpdb;
		$table = MMR_DB::table( 'events' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT source_kind, source_id, COALESCE(SUM(amount),0) AS total, MAX(issue_date) AS last_date
			 FROM {$table}
			 WHERE source_period = %s AND source_kind IS NOT NULL
			 GROUP BY source_kind, source_id",
			$period
		) ); // phpcs:ignore WordPress.DB.PreparedSQL

		$map = array();
		foreach ( $rows as $row ) {
			$map[ $row->source_kind . '|' . (int) $row->source_id ] = $row;
		}
		return $map;
	}

	/**
	 * Build one picker entry, attaching the already-billed hint when the same
	 * source has already produced an event for the period.
	 *
	 * @param string $kind   class|student|company.
	 * @param int    $id     Source ID.
	 * @param string $label  Payer label.
	 * @param float  $amount Suggested amount.
	 * @param string $detail Human explanation of the figure.
	 * @param string $hint   Type hint or ''.
	 * @param array  $billed Map from events_by_source().
	 * @param string $method Settle method behind the hint, for the class chip.
	 * @return array
	 */
	protected static function event_suggestion( $kind, $id, $label, $amount, $detail, $hint, $billed, $method = '' ) {
		$key = $kind . '|' . (int) $id;
		$already = isset( $billed[ $key ] ) ? $billed[ $key ] : null;
		return array(
			'kind'           => $kind,
			'id'             => (int) $id,
			'label'          => $label,
			'amount'         => round( (float) $amount, 2 ),
			'amount_display' => mmr_money( $amount ),
			'detail'         => $detail,
			'type_hint'      => $hint,
			'settle_method'  => (string) $method,
			'billed'         => (bool) $already,
			// A hint, never a block: splitting one settlement across several
			// documents is normal and must stay frictionless.
			'billed_note'    => $already
				? sprintf(
					/* translators: 1: amount already billed, 2: date of the last event. */
					__( 'already billed — %1$s on %2$s', 'mm-register' ),
					mmr_money( $already->total ),
					mmr_date_label( $already->last_date )
				)
				: '',
		);
	}

	/**
	 * Billing suggestions for a month, in three groups, so no number is ever
	 * retyped from the Settlement page into an event.
	 *
	 * Reads MMR_Data::settlement() without altering it; company entries are
	 * built by grouping that report's classes by their company name.
	 *
	 * @param string $period Y-m.
	 * @return array classes, students, companies.
	 */
	public static function event_suggestions( $period ) {
		$period = mmr_sanitize_period( $period );
		$out = array( 'classes' => array(), 'students' => array(), 'companies' => array() );
		if ( ! $period ) {
			return $out;
		}

		$report = self::settlement( $period );
		$billed = self::events_by_source( $period );

		// Classes — the class is what gets invoiced, so the suggestion is what
		// that class is owed for the month: its lessons plus the TBT
		// surcharges attributed to it. The detail names the surcharge, so the
		// figure is never a number the lesson rates alone cannot explain.
		$companies = array();
		foreach ( $report['classes'] as $row ) {
			$fee_parts = array();
			foreach ( $row['fees_used'] as $fee => $count ) {
				$fee_parts[] = mmr_money( $fee ) . ' ×' . $count;
			}
			$detail = sprintf(
				/* translators: 1: lesson count, 2: rates used, e.g. "200,00 zł ×11". */
				_n( '%1$s lesson · %2$s', '%1$s lessons · %2$s', (int) $row['lesson_count'], 'mm-register' ),
				number_format_i18n( (int) $row['lesson_count'] ),
				implode( ', ', $fee_parts )
			);
			if ( $row['tbt_total'] > 0 ) {
				/* translators: %s: TBT monthly surcharge. */
				$detail .= ' · ' . sprintf( __( 'TBT %s', 'mm-register' ), mmr_money( $row['tbt_total'] ) );
			}
			$hint = self::event_type_hint( $row['settle_method'] );

			if ( $row['to_bill'] > 0 ) {
				$out['classes'][] = self::event_suggestion( 'class', $row['class_id'], $row['class_name'], $row['to_bill'], $detail, $hint, $billed, (string) $row['settle_method'] );
			}

			$company = trim( (string) $row['company'] );
			if ( '' === $company ) {
				continue;
			}
			if ( ! isset( $companies[ $company ] ) ) {
				$companies[ $company ] = array( 'total' => 0.0, 'classes' => array(), 'hints' => array() );
			}
			$companies[ $company ]['total'] += (float) $row['to_bill'];
			$companies[ $company ]['classes'][] = $row['class_name'] . ' (' . mmr_money( $row['to_bill'] ) . ')';
			$companies[ $company ]['hints'][ $hint ] = true;
		}

		// Students — lesson shares plus the TBT surcharge, added once a month.
		foreach ( $report['students'] as $row ) {
			if ( $row['to_bill'] <= 0 ) {
				continue;
			}
			$detail = sprintf(
				/* translators: 1: lesson count, 2: lesson total. */
				_n( '%1$s lesson · %2$s', '%1$s lessons · %2$s', (int) $row['lessons'], 'mm-register' ),
				number_format_i18n( (int) $row['lessons'] ),
				mmr_money( $row['lesson_total'] )
			);
			if ( $row['tbt'] > 0 ) {
				/* translators: %s: TBT monthly surcharge. */
				$detail .= ' · ' . sprintf( __( 'TBT %s', 'mm-register' ), mmr_money( $row['tbt'] ) );
			}
			$out['students'][] = self::event_suggestion( 'student', $row['student_id'], $row['student_name'], $row['to_bill'], $detail, '', $billed );
		}

		// Companies — Σ of their classes this month. The detail names the
		// classes rolled up, so a company suggestion is never an opaque number.
		foreach ( $companies as $name => $data ) {
			if ( $data['total'] <= 0 ) {
				continue;
			}
			$company = self::company_by_name( $name );
			// A type hint only survives when every rolled-up class agrees.
			$hints = array_keys( $data['hints'] );
			$hint = ( 1 === count( $hints ) && '' !== $hints[0] ) ? $hints[0] : '';
			$out['companies'][] = self::event_suggestion(
				'company',
				$company ? (int) $company->id : 0,
				$name,
				$data['total'],
				implode( ', ', $data['classes'] ),
				$hint,
				$billed
			);
		}

		foreach ( $out as $group => $entries ) {
			usort( $entries, function ( $a, $b ) {
				return strcmp( $a['label'], $b['label'] );
			} );
			$out[ $group ] = $entries;
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Drafts from Settlement (2.7)
	 *
	 * A *billable line* is the smallest thing one payer pays for in a month:
	 * normally a whole class, or one member of a class with "Members pay
	 * separately" ticked. Line amounts are read from settlement() — never
	 * recomputed here. An event *covers* a line when its source_period is the
	 * month and its classes / source say so (see line_keys_for_event()).
	 *
	 * "One event per line" is a rule of this automation only: nothing in the
	 * schema or the Events form stops a second event covering the same line,
	 * so splitting one month across several documents keeps working.
	 * ------------------------------------------------------------------ */

	/**
	 * Billable lines of a month, in the settlement table's order.
	 *
	 * @param string     $period Y-m.
	 * @param array|null $report settlement() output, when the caller has it.
	 * @return array List of line arrays (see the 2.7 spec §2.4 for the keys).
	 */
	public static function billable_lines( $period, $report = null ) {
		$period = mmr_sanitize_period( $period );
		if ( ! $period ) {
			return array();
		}
		if ( null === $report ) {
			$report = self::settlement( $period );
		}

		// settlement() carries the company name only; the payer key needs the ID.
		$class_rows = self::classes_by_ids( wp_list_pluck( $report['classes'], 'class_id' ) );

		$lines = array();
		foreach ( $report['classes'] as $row ) {
			$cid = (int) $row['class_id'];
			$company_id = ( isset( $class_rows[ $cid ] ) && ! empty( $class_rows[ $cid ]->company_id ) ) ? (int) $class_rows[ $cid ]->company_id : 0;
			$type = self::event_type_hint( $row['settle_method'] );
			$base = array(
				'period'        => $period,
				'class_id'      => $cid,
				'class_name'    => (string) $row['class_name'],
				'company_id'    => $company_id,
				'company'       => (string) $row['company'],
				'settle_method' => (string) $row['settle_method'],
				'type'          => $type,
			);

			$per_member = ! empty( $row['pay_per_member'] ) && count( $row['members'] ) >= 2;
			if ( ! $per_member ) {
				$line = array_merge( $base, array(
					'key'          => (string) $cid,
					'student_id'   => 0,
					'name'         => (string) $row['class_name'],
					'payer_key'    => $company_id ? 'company:' . $company_id : 'class:' . $cid,
					'amount'       => round( (float) $row['to_bill'], 2 ),
					'lesson_count' => (int) $row['lesson_count'],
				) );
				$line['ineligible_reason'] = self::line_ineligible_reason( $line );
				$lines[] = $line;
				continue;
			}

			foreach ( $row['members'] as $member ) {
				$sid = (int) $member['student_id'];
				$line = array_merge( $base, array(
					'key'          => $cid . ':' . $sid,
					'student_id'   => $sid,
					'name'         => (string) $member['student_name'],
					'payer_key'    => 'student:' . $sid,
					'amount'       => round( (float) $member['total'], 2 ),
					'lesson_count' => (int) $member['lessons'],
				) );
				$line['ineligible_reason'] = self::line_ineligible_reason( $line );
				$lines[] = $line;
			}
		}
		return $lines;
	}

	/**
	 * Why a line cannot be drafted, or '' when it can.
	 *
	 * @param array $line Billable line.
	 * @return string
	 */
	protected static function line_ineligible_reason( $line ) {
		if ( '' === $line['type'] ) {
			/* translators: %s: the class's settle method as typed. */
			return sprintf( __( 'Settle method "%s" isn\'t recognised.', 'mm-register' ), $line['settle_method'] );
		}
		// KSeF needs a company; a member line bills the student, not a company.
		if ( 'invoice' === $line['type'] && 0 === (int) $line['student_id'] && ! $line['company_id'] ) {
			return __( 'No company on this class — set one before invoicing.', 'mm-register' );
		}
		if ( $line['amount'] <= 0 ) {
			return __( 'Nothing to bill.', 'mm-register' );
		}
		return '';
	}

	/**
	 * Line keys an event covers, from its source and its classes.
	 *
	 * - source_kind `student` → the member lines "{class}:{student}";
	 * - source_kind `class` / `company` → the class lines "{class}".
	 * Events without a source cover nothing (they are invisible here).
	 *
	 * @param object $event      Event row.
	 * @param array  $class_list Its classes (event_classes_map() entries).
	 * @return string[]
	 */
	public static function line_keys_for_event( $event, $class_list ) {
		$keys = array();
		$kind = (string) $event->source_kind;
		foreach ( (array) $class_list as $class ) {
			$cid = (int) $class['id'];
			if ( 'student' === $kind ) {
				$keys[] = $cid . ':' . (int) $event->source_id;
			} elseif ( 'class' === $kind || 'company' === $kind ) {
				$keys[] = (string) $cid;
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * Coverage for a month: line_key => events covering it, oldest first.
	 * Each event row gets `line_keys` (every line it covers) attached.
	 *
	 * @param string $period Y-m.
	 * @return array
	 */
	public static function events_for_period( $period ) {
		global $wpdb;
		$period = mmr_sanitize_period( $period );
		if ( ! $period ) {
			return array();
		}
		$table = MMR_DB::table( 'events' );
		$events = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE source_period = %s ORDER BY id ASC",
			$period
		) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( empty( $events ) ) {
			return array();
		}
		$classes = self::event_classes_map( wp_list_pluck( $events, 'id' ) );

		$map = array();
		foreach ( $events as $event ) {
			$list = isset( $classes[ (int) $event->id ] ) ? $classes[ (int) $event->id ] : array();
			$event->line_keys = self::line_keys_for_event( $event, $list );
			foreach ( $event->line_keys as $key ) {
				$map[ $key ][] = $event;
			}
		}
		return $map;
	}

	/**
	 * Create one draft event for a set of lines of one month.
	 *
	 * Validated here, never trusting the client: every key must be a current,
	 * eligible, uncovered line, and all lines must share one type and payer.
	 *
	 * @param string   $period Y-m.
	 * @param string[] $keys   Line keys.
	 * @return int|WP_Error New event ID.
	 */
	public static function create_event_from_lines( $period, $keys ) {
		$period = mmr_sanitize_period( $period );
		$keys = array_values( array_unique( array_filter( array_map( 'strval', (array) $keys ) ) ) );
		if ( ! $period || empty( $keys ) ) {
			return new WP_Error( 'mmr_no_lines', __( 'Select at least one line.', 'mm-register' ) );
		}

		$by_key = array();
		foreach ( self::billable_lines( $period ) as $line ) {
			$by_key[ $line['key'] ] = $line;
		}
		$coverage = self::events_for_period( $period );

		foreach ( $keys as $key ) {
			if ( ! isset( $by_key[ $key ] ) ) {
				return new WP_Error( 'mmr_unknown_line', __( 'One of the selected lines is no longer in this month\'s settlement. Reload the page and try again.', 'mm-register' ) );
			}
		}

		// Walk the lines in settlement order (not click order), so the classes
		// and the composed payer label always read the same way.
		$lines = array();
		foreach ( $by_key as $key => $line ) {
			if ( ! in_array( (string) $key, $keys, true ) ) {
				continue;
			}
			if ( '' !== $line['ineligible_reason'] ) {
				return new WP_Error( 'mmr_ineligible', $line['name'] . ': ' . $line['ineligible_reason'] );
			}
			if ( ! empty( $coverage[ $key ] ) ) {
				/* translators: %s: line name. */
				return new WP_Error( 'mmr_covered', sprintf( __( '%s already has an event this month.', 'mm-register' ), $line['name'] ) );
			}
			$lines[] = $line;
		}

		$types = array_unique( wp_list_pluck( $lines, 'type' ) );
		if ( count( $types ) > 1 ) {
			return new WP_Error( 'mmr_mixed_type', __( 'Different settle methods can\'t share one event.', 'mm-register' ) );
		}
		$payers = array_unique( wp_list_pluck( $lines, 'payer_key' ) );
		if ( count( $payers ) > 1 ) {
			return new WP_Error( 'mmr_mixed_payer', __( 'Different payers can\'t share one event.', 'mm-register' ) );
		}

		$first = $lines[0];
		$class_ids = array_values( array_unique( array_map( 'intval', wp_list_pluck( $lines, 'class_id' ) ) ) );
		$total = 0.0;
		foreach ( $lines as $line ) {
			$total += (float) $line['amount'];
		}
		$total = round( $total, 2 );

		if ( $first['student_id'] ) {
			$source_kind = 'student';
			$source_id = (int) $first['student_id'];
		} elseif ( 1 === count( $lines ) ) {
			$source_kind = 'class';
			$source_id = (int) $first['class_id'];
		} else {
			// Several class lines with one payer key share one company.
			$source_kind = 'company';
			$source_id = (int) $first['company_id'];
		}

		$id = self::save_event( array(
			'type'          => $first['type'],
			'issue_date'    => current_time( 'Y-m-d' ),
			'class_ids'     => $class_ids,
			'who_text'      => $first['student_id'] ? $first['name'] : '',
			'amount'        => $total,
			'lines_total'   => $total,
			'source_period' => $period,
			'source_kind'   => $source_kind,
			'source_id'     => $source_id,
			'is_draft'      => 1,
			'notes'         => '',
		) );
		if ( ! $id ) {
			return new WP_Error( 'mmr_save_failed', __( 'The event could not be saved.', 'mm-register' ) );
		}
		return $id;
	}

	/**
	 * Load an event and refuse unless it is a draft.
	 *
	 * @param int $id Event ID.
	 * @return object|WP_Error
	 */
	protected static function draft_or_error( $id ) {
		$event = self::event( (int) $id );
		if ( ! $event ) {
			return new WP_Error( 'mmr_no_event', __( 'That event no longer exists.', 'mm-register' ) );
		}
		if ( ! (int) $event->is_draft ) {
			return new WP_Error( 'mmr_not_draft', __( 'This event has been issued — its amount is locked.', 'mm-register' ) );
		}
		return $event;
	}

	/**
	 * Re-save a draft with a given class set, its amount re-read from the
	 * current settlement. Classes whose line has gone from the settlement are
	 * dropped; with none left the draft is deleted.
	 *
	 * @param object $event     Draft event row.
	 * @param int[]  $class_ids Classes to keep.
	 * @return string updated|deleted
	 */
	protected static function rebuild_draft( $event, $class_ids ) {
		$by_key = array();
		foreach ( self::billable_lines( $event->source_period ) as $line ) {
			$by_key[ $line['key'] ] = $line;
		}

		$keep = array();
		$total = 0.0;
		foreach ( $class_ids as $cid ) {
			$key = ( 'student' === $event->source_kind ) ? $cid . ':' . (int) $event->source_id : (string) $cid;
			if ( ! isset( $by_key[ $key ] ) ) {
				continue; // Lessons gone (or the class switched billing mode).
			}
			$keep[] = (int) $cid;
			$total += (float) $by_key[ $key ]['amount'];
		}

		if ( empty( $keep ) ) {
			self::delete_event( (int) $event->id );
			return 'deleted';
		}

		$data = (array) $event;
		$data['class_ids'] = $keep;
		$data['amount'] = round( $total, 2 );
		$data['lines_total'] = round( $total, 2 );
		if ( 'student' !== $event->source_kind ) {
			if ( 1 === count( $keep ) ) {
				$data['source_kind'] = 'class';
				$data['source_id'] = $keep[0];
			} else {
				$data['source_kind'] = 'company';
				$data['source_id'] = $by_key[ (string) $keep[0] ]['company_id'];
			}
		}
		self::save_event( $data, (int) $event->id );
		return 'updated';
	}

	/**
	 * Refresh a draft's amount to the current settlement.
	 *
	 * @param int $id Event ID.
	 * @return string|WP_Error updated|deleted
	 */
	public static function update_draft_from_settlement( $id ) {
		$event = self::draft_or_error( $id );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		if ( ! mmr_sanitize_period( $event->source_period ) ) {
			return new WP_Error( 'mmr_no_period', __( 'This event is not linked to a settlement month.', 'mm-register' ) );
		}
		return self::rebuild_draft( $event, self::event_class_ids( (int) $event->id ) );
	}

	/**
	 * Take one line out of a draft. The last line out deletes the draft.
	 *
	 * @param int    $id  Event ID.
	 * @param string $key Line key.
	 * @return string|WP_Error updated|deleted
	 */
	public static function remove_line_from_draft( $id, $key ) {
		$event = self::draft_or_error( $id );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		$classes = self::event_classes_map( array( (int) $event->id ) );
		$list = isset( $classes[ (int) $event->id ] ) ? $classes[ (int) $event->id ] : array();
		$key = (string) $key;
		if ( ! in_array( $key, self::line_keys_for_event( $event, $list ), true ) ) {
			return new WP_Error( 'mmr_not_in_event', __( 'That line is not part of this event.', 'mm-register' ) );
		}
		$parts = explode( ':', $key );
		$remove = (int) $parts[0];
		$keep = array();
		foreach ( $list as $class ) {
			if ( (int) $class['id'] !== $remove ) {
				$keep[] = (int) $class['id'];
			}
		}
		if ( empty( $keep ) ) {
			self::delete_event( (int) $event->id );
			return 'deleted';
		}
		return self::rebuild_draft( $event, $keep );
	}

	/**
	 * Issue a draft from the Settlement page.
	 *
	 * - invoice: refused — the invoice number, typed in Events, is the issue act;
	 * - revolut: Sent (the request went out);
	 * - cash: Paid — handing over cash is the payment, so there is no sent step;
	 * - rozliczenie: confirmed (is_draft = 0).
	 * Sent / Paid clear the draft through normalize_event()'s auto-issue rule.
	 *
	 * @param int $id Event ID.
	 * @return true|WP_Error
	 */
	public static function mark_event_issued( $id ) {
		$event = self::event( (int) $id );
		if ( ! $event ) {
			return new WP_Error( 'mmr_no_event', __( 'That event no longer exists.', 'mm-register' ) );
		}
		if ( ! (int) $event->is_draft ) {
			return new WP_Error( 'mmr_not_draft', __( 'This event has already been issued.', 'mm-register' ) );
		}
		switch ( $event->type ) {
			case 'invoice':
				return new WP_Error( 'mmr_invoice_number', __( 'Add the invoice number in Events to issue it.', 'mm-register' ) );
			case 'revolut':
				self::set_event_flag( (int) $event->id, 'sent', 1 );
				return true;
			case 'cash':
				self::set_event_flag( (int) $event->id, 'paid', 1 );
				return true;
			case 'rozliczenie':
				$data = (array) $event;
				$data['is_draft'] = 0;
				self::save_event( $data, (int) $event->id );
				return true;
		}
		return new WP_Error( 'mmr_bad_type', __( 'This event type cannot be issued here.', 'mm-register' ) );
	}

	/**
	 * Mark an issued event paid. Never a draft, never a rozliczenie.
	 *
	 * @param int $id Event ID.
	 * @return true|WP_Error
	 */
	public static function mark_event_paid( $id ) {
		$event = self::event( (int) $id );
		if ( ! $event ) {
			return new WP_Error( 'mmr_no_event', __( 'That event no longer exists.', 'mm-register' ) );
		}
		if ( (int) $event->is_draft ) {
			return new WP_Error( 'mmr_is_draft', __( 'Issue the event before marking it paid.', 'mm-register' ) );
		}
		if ( ! in_array( $event->type, array( 'invoice', 'revolut', 'cash' ), true ) ) {
			return new WP_Error( 'mmr_never_paid', __( 'A rozliczenie is never paid — it records prepaid lessons.', 'mm-register' ) );
		}
		if ( (int) $event->paid ) {
			return new WP_Error( 'mmr_already_paid', __( 'This event is already paid.', 'mm-register' ) );
		}
		self::set_event_flag( (int) $event->id, 'paid', 1 );
		return true;
	}

	/**
	 * Drift: the current settlement figure when it differs from the one the
	 * event was drafted from, else null. Read-only — never writes.
	 *
	 * @param object $event               Event row.
	 * @param float  $current_lines_total Σ current amounts of its lines.
	 * @return float|null
	 */
	public static function event_drift( $event, $current_lines_total ) {
		if ( null === $event->lines_total || '' === $event->lines_total ) {
			return null;
		}
		$current = round( (float) $current_lines_total, 2 );
		if ( abs( $current - (float) $event->lines_total ) > 0.005 ) {
			return $current;
		}
		return null;
	}

	/**
	 * Status of an event as the Settlement page shows it.
	 *
	 * @param object $event Event row.
	 * @return array [ key => draft|issued|paid, label ]
	 */
	public static function event_status( $event ) {
		if ( (int) $event->is_draft ) {
			return array( 'key' => 'draft', 'label' => __( 'Draft', 'mm-register' ) );
		}
		if ( (int) $event->paid ) {
			return array( 'key' => 'paid', 'label' => __( 'Paid', 'mm-register' ) );
		}
		$labels = array(
			'revolut'     => __( 'Requested', 'mm-register' ),
			'rozliczenie' => __( 'Recorded', 'mm-register' ),
		);
		return array(
			'key'   => 'issued',
			'label' => isset( $labels[ $event->type ] ) ? $labels[ $event->type ] : __( 'Issued', 'mm-register' ),
		);
	}
}
