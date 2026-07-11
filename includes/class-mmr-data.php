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
}
