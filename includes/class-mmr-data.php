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
	 * @param array $args status, search, client_type, company_id.
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

		$sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY name ASC";
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
			'notes'         => $data['notes'],
			'status'        => $data['status'],
		);
		$formats = array( '%s', '%f', '%s', '%s', null === $company_id ? null : '%d', '%s', '%s' );

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
	 *                      members (per-student breakdown incl. TBT),
	 *     students      => per-student summary: student_id, name, company,
	 *                      lessons, lesson_total, tbt (added once per month),
	 *                      to_bill,
	 *     month_total   => Σ class totals (lesson fees only),
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
			        co.name AS company_name
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

		// Per-class rows with member breakdown.
		$class_rows = array();
		$month_total = 0.0;
		foreach ( $classes as $cid => $data ) {
			$members = array();
			foreach ( $data['member_totals'] as $sid => $mt ) {
				$tbt = $tbt_of( $sid );
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
			$data['members'] = $members;
			unset( $data['member_totals'] );
			$month_total += $data['class_total'];
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
		return array( 'invoice', 'revolut', 'cash' );
	}

	/**
	 * The list's status filter keys.
	 *
	 * @return string[]
	 */
	public static function event_states() {
		return array( 'all', 'unpaid', 'paid', 'unsent', 'no_ksef' );
	}

	/**
	 * Source kinds an event's amount may have been taken from.
	 *
	 * @return string[]
	 */
	public static function event_source_kinds() {
		return array( 'class', 'student', 'company' );
	}

	/**
	 * The single validator every write path runs through: the POST handler,
	 * the AJAX flag toggle and the importer all call it, so their rules can
	 * never drift apart.
	 *
	 * Enforced here, not in the UI:
	 * - `ksef` is an invoice-only fact — any other type forces it to 0;
	 * - a paid event always has a payment date (today when none is given);
	 * - an unpaid event never has one.
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

		$paid = empty( $data['paid'] ) ? 0 : 1;
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

		return array(
			'type'          => $type,
			'doc_number'    => isset( $data['doc_number'] ) ? sanitize_text_field( (string) $data['doc_number'] ) : '',
			'issue_date'    => $issue_date,
			'payer_label'   => isset( $data['payer_label'] ) ? sanitize_text_field( (string) $data['payer_label'] ) : '',
			'amount'        => isset( $data['amount'] ) ? mmr_sanitize_money( $data['amount'] ) : 0.0,
			'ksef'          => ( 'invoice' === $type && ! empty( $data['ksef'] ) ) ? 1 : 0,
			'sent'          => empty( $data['sent'] ) ? 0 : 1,
			'paid'          => $paid,
			'paid_date'     => $paid_date,
			'notes'         => isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : '',
			'source_kind'   => '' === $source_kind ? null : $source_kind,
			'source_id'     => $source_id ? $source_id : null,
			'source_period' => $source_period,
		);
	}

	/**
	 * Build the shared WHERE clause for the events list and its totals, so a
	 * chip can never be scoped differently from the rows below it.
	 *
	 * @param array $args year, type, state, search.
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

		$state = isset( $args['state'] ) ? (string) $args['state'] : 'all';
		switch ( $state ) {
			case 'unpaid':
				$where .= ' AND paid = 0';
				break;
			case 'paid':
				$where .= ' AND paid = 1';
				break;
			case 'unsent':
				$where .= ' AND sent = 0';
				break;
			case 'no_ksef':
				$where .= " AND type = 'invoice' AND ksef = 0";
				break;
		}

		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where .= ' AND (payer_label LIKE %s OR doc_number LIKE %s)';
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
	 * @param array $data Raw fields.
	 * @param int   $id   Existing ID or 0.
	 * @return int Event ID.
	 */
	public static function save_event( $data, $id = 0 ) {
		global $wpdb;
		$table = MMR_DB::table( 'events' );
		$now = current_time( 'mysql' );

		$row = self::normalize_event( $data );
		$row['updated_at'] = $now;
		$formats = array( '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' );

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
	 * Delete an event. Unlike students and classes an event is a record of a
	 * single document, and a wrong row must not linger inside a financial
	 * total — so this is a real delete, not an archive.
	 *
	 * @param int $id Event ID.
	 */
	public static function delete_event( $id ) {
		global $wpdb;
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
	 *
	 * @param string $method Free-text settle method.
	 * @return string invoice|revolut|cash, or '' when it does not map.
	 */
	protected static function event_type_hint( $method ) {
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
	 * @return array
	 */
	protected static function event_suggestion( $kind, $id, $label, $amount, $detail, $hint, $billed ) {
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

		// Classes — the class is what gets invoiced, so lesson fees only.
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
			$hint = self::event_type_hint( $row['settle_method'] );

			if ( $row['class_total'] > 0 ) {
				$out['classes'][] = self::event_suggestion( 'class', $row['class_id'], $row['class_name'], $row['class_total'], $detail, $hint, $billed );
			}

			$company = trim( (string) $row['company'] );
			if ( '' === $company ) {
				continue;
			}
			if ( ! isset( $companies[ $company ] ) ) {
				$companies[ $company ] = array( 'total' => 0.0, 'classes' => array(), 'hints' => array() );
			}
			$companies[ $company ]['total'] += (float) $row['class_total'];
			$companies[ $company ]['classes'][] = $row['class_name'] . ' (' . mmr_money( $row['class_total'] ) . ')';
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
}
