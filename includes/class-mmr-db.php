<?php
/**
 * Database schema & table-name helpers.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles table creation (dbDelta) and provides prefixed table names.
 */
class MMR_DB {

	/**
	 * Cache of resolved table names.
	 *
	 * @var array
	 */
	protected static $tables = array();

	/**
	 * Get a fully-qualified table name.
	 *
	 * @param string $key Logical table key (without prefix), e.g. 'students'.
	 * @return string
	 */
	public static function table( $key ) {
		global $wpdb;
		if ( ! isset( self::$tables[ $key ] ) ) {
			self::$tables[ $key ] = $wpdb->prefix . 'mmr_' . $key;
		}
		return self::$tables[ $key ];
	}

	/**
	 * Create / upgrade all tables. Called on activation.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$students = self::table( 'students' );
		$classes = self::table( 'classes' );
		$members = self::table( 'class_members' );
		$lessons = self::table( 'lessons' );
		$settlements = self::table( 'settlements' );
		$budget = self::table( 'budget_items' );
		$expenses = self::table( 'recurring_expenses' );
		$payments = self::table( 'expense_payments' );
		$projections = self::table( 'projections' );

		$sql = array();

		$sql[] = "CREATE TABLE {$students} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			email VARCHAR(190) NULL DEFAULT NULL,
			tbt_monthly_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			notes TEXT NULL,
			status ENUM('active','archived') NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY name (name)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$classes} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			client_type VARCHAR(60) NULL DEFAULT NULL,
			venue VARCHAR(60) NULL DEFAULT NULL,
			settle_method VARCHAR(60) NULL DEFAULT NULL,
			notes TEXT NULL,
			status ENUM('active','archived') NOT NULL DEFAULT 'active',
			sort_name VARCHAR(190) NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY name (name),
			KEY sort_name (sort_name)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$members} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id BIGINT UNSIGNED NOT NULL,
			student_id BIGINT UNSIGNED NOT NULL,
			share_per_lesson DECIMAL(10,2) NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY class_id (class_id),
			KEY student_id (student_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$lessons} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lesson_date DATE NOT NULL,
			slot TINYINT UNSIGNED NOT NULL DEFAULT 1,
			class_id BIGINT UNSIGNED NOT NULL,
			fee_snapshot DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			shares_snapshot TEXT NULL,
			note VARCHAR(190) NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY lesson_date (lesson_date),
			KEY class_id (class_id),
			KEY date_slot (lesson_date, slot)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$settlements} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			doc_type VARCHAR(30) NULL DEFAULT NULL,
			doc_number VARCHAR(120) NULL DEFAULT NULL,
			doc_date DATE NULL DEFAULT NULL,
			payer_label VARCHAR(190) NULL DEFAULT NULL,
			class_id BIGINT UNSIGNED NULL DEFAULT NULL,
			period CHAR(7) NULL DEFAULT NULL,
			amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			ksef TINYINT(1) NOT NULL DEFAULT 0,
			sent TINYINT(1) NOT NULL DEFAULT 0,
			paid TINYINT(1) NOT NULL DEFAULT 0,
			paid_on DATE NULL DEFAULT NULL,
			notes TEXT NULL,
			PRIMARY KEY  (id),
			KEY paid (paid),
			KEY period (period),
			KEY class_id (class_id),
			KEY doc_date (doc_date)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$budget} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			label VARCHAR(190) NOT NULL,
			amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			kind ENUM('asset','liability','reserve','note_only') NOT NULL DEFAULT 'asset',
			sort_order INT NOT NULL DEFAULT 0,
			notes TEXT NULL,
			updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY kind (kind),
			KEY sort_order (sort_order)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$expenses} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(190) NOT NULL,
			projected DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			pay_type ENUM('manual','subscription') NOT NULL DEFAULT 'manual',
			frequency ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
			category ENUM('critical','moderate','whim') NOT NULL DEFAULT 'moderate',
			due_note VARCHAR(190) NULL DEFAULT NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY active (active),
			KEY frequency (frequency)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$payments} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			expense_id BIGINT UNSIGNED NOT NULL,
			period CHAR(7) NOT NULL,
			spent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			paid_on DATE NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY expense_id (expense_id),
			KEY period (period),
			UNIQUE KEY expense_period (expense_id, period)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$projections} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			period CHAR(7) NOT NULL,
			projected DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			expenses DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			notes TEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY period (period)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'mmr_db_version', MMR_VERSION );
	}

	/**
	 * Return the list of logical table keys.
	 *
	 * @return string[]
	 */
	public static function all_keys() {
		return array(
			'students',
			'classes',
			'class_members',
			'lessons',
			'settlements',
			'budget_items',
			'recurring_expenses',
			'expense_payments',
			'projections',
		);
	}

	/**
	 * Drop every plugin table and delete options. Danger-zone use only.
	 */
	public static function wipe() {
		global $wpdb;
		foreach ( self::all_keys() as $key ) {
			$table = self::table( $key );
			// Table name comes from a fixed whitelist above; safe to interpolate.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		delete_option( 'mmr_db_version' );
	}

	/**
	 * True if every plugin table is empty. Used by the importer guard.
	 *
	 * @return bool
	 */
	public static function is_empty() {
		global $wpdb;
		foreach ( array( 'students', 'classes', 'lessons', 'settlements', 'budget_items', 'recurring_expenses', 'projections' ) as $key ) {
			$table = self::table( $key );
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
			if ( $count > 0 ) {
				return false;
			}
		}
		return true;
	}
}
