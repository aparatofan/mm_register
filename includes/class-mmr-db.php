<?php
/**
 * Database schema, upgrades & table-name helpers.
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles table creation (dbDelta), version upgrades and prefixed table names.
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
	 * Activation entry point: run pending upgrades, then create tables.
	 */
	public static function activate() {
		self::upgrade_legacy();
		self::upgrade_v21();
		self::install();
	}

	/**
	 * Run on plugin load: upgrade + dbDelta when the stored version is behind.
	 * Idempotent — does nothing once mmr_db_version matches MMR_DB_VERSION.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'mmr_db_version' ) === MMR_DB_VERSION ) {
			return;
		}
		self::upgrade_legacy();
		self::upgrade_v21();
		self::install();
	}

	/**
	 * v1 → v2 upgrade: back up the legacy tables to a JSON file, then drop them.
	 *
	 * The backup is best effort — if the uploads directory cannot be written,
	 * the failure is logged and the upgrade continues. Only tables that
	 * actually exist are dumped/dropped, so re-running is harmless.
	 */
	protected static function upgrade_legacy() {
		global $wpdb;

		$existing = array();
		foreach ( self::legacy_keys() as $key ) {
			$table = self::table( $key );
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( $found === $table ) {
				$existing[ $key ] = $table;
			}
		}
		if ( empty( $existing ) ) {
			return;
		}

		// 1. Backup first.
		$dump = array(
			'reason'      => 'pre-v2 upgrade — these tables were dropped by MM Register 2.0',
			'exported_at' => current_time( 'mysql' ),
			'tables'      => array(),
		);
		foreach ( $existing as $key => $table ) {
			$dump['tables'][ $key ] = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		}
		self::write_backup( $dump );

		// 2. Then drop.
		foreach ( $existing as $table ) {
			// Table names come from the fixed whitelist above; safe to interpolate.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * v2 → v2.1 upgrade: the classes table loses `venue` and `sort_name`.
	 * dbDelta never drops columns, so this backs up mmr_classes first (best
	 * effort) and then drops whichever of the two columns still exist. New
	 * columns (student phone/email_2/level, class company_id) and the new
	 * companies table are added by install()/dbDelta, not here.
	 *
	 * Idempotent: if neither column is present, it does nothing.
	 */
	protected static function upgrade_v21() {
		global $wpdb;

		$classes = self::table( 'classes' );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $classes ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $found !== $classes ) {
			return; // Fresh install — install() will create the current schema.
		}

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$classes}" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		$to_drop = array_values( array_intersect( array( 'venue', 'sort_name' ), (array) $columns ) );
		if ( empty( $to_drop ) ) {
			return;
		}

		// 1. Backup mmr_classes first.
		$dump = array(
			'reason'      => 'pre-v2.1 upgrade — the classes columns venue and sort_name were dropped',
			'exported_at' => current_time( 'mysql' ),
			'dropped'     => $to_drop,
			'tables'      => array(
				'classes' => $wpdb->get_results( "SELECT * FROM {$classes}", ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
			),
		);
		self::write_backup( $dump, 'pre-v2.1' );

		// 2. Then drop. Column names come from the fixed whitelist above.
		foreach ( $to_drop as $column ) {
			$wpdb->query( "ALTER TABLE {$classes} DROP COLUMN {$column}" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Write the pre-drop dump into wp-content/uploads/mm-register-backups/.
	 * Best effort: logs and returns on any failure instead of aborting.
	 *
	 * @param array  $dump   Backup payload.
	 * @param string $prefix Filename prefix, e.g. 'pre-v2' or 'pre-v2.1'.
	 */
	protected static function write_backup( $dump, $prefix = 'pre-v2' ) {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			error_log( 'MM Register: pre-v2 backup skipped — ' . $uploads['error'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return;
		}
		$dir = trailingslashit( $uploads['basedir'] ) . 'mm-register-backups';
		if ( ! wp_mkdir_p( $dir ) ) {
			error_log( 'MM Register: pre-v2 backup skipped — could not create ' . $dir ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return;
		}
		// Keep the directory out of casual listings.
		if ( ! file_exists( $dir . '/index.php' ) ) {
			@file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore
		}
		$file = $dir . '/' . $prefix . '-' . gmdate( 'Ymd-His' ) . '.json';
		$json = wp_json_encode( $dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		if ( false === @file_put_contents( $file, $json ) ) { // phpcs:ignore
			error_log( 'MM Register: ' . $prefix . ' backup skipped — could not write ' . $file ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	/**
	 * Create / upgrade the plugin tables.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$students = self::table( 'students' );
		$classes = self::table( 'classes' );
		$members = self::table( 'class_members' );
		$lessons = self::table( 'lessons' );
		$companies = self::table( 'companies' );
		$events = self::table( 'events' );

		$sql = array();

		$sql[] = "CREATE TABLE {$students} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			email VARCHAR(190) NULL DEFAULT NULL,
			email_2 VARCHAR(190) NULL DEFAULT NULL,
			phone VARCHAR(40) NULL DEFAULT NULL,
			level VARCHAR(20) NULL DEFAULT NULL,
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
			settle_method VARCHAR(60) NULL DEFAULT NULL,
			company_id BIGINT UNSIGNED NULL DEFAULT NULL,
			notes TEXT NULL,
			status ENUM('active','archived') NOT NULL DEFAULT 'active',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY name (name),
			KEY company_id (company_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$companies} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(190) NOT NULL,
			address TEXT NULL,
			nip VARCHAR(20) NULL DEFAULT NULL,
			notes TEXT NULL,
			status ENUM('active','archived') NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY name (name)
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

		// Events — the receivables register (v2.2). NOTE: this is deliberately
		// NOT the v1 `mmr_settlements` table, which upgrade_legacy() still
		// drops; `events` must never appear in legacy_keys().
		$sql[] = "CREATE TABLE {$events} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			type VARCHAR(20) NOT NULL DEFAULT 'invoice',
			doc_number VARCHAR(190) NULL DEFAULT NULL,
			issue_date DATE NOT NULL,
			payer_label VARCHAR(190) NOT NULL,
			amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			ksef TINYINT(1) NOT NULL DEFAULT 0,
			sent TINYINT(1) NOT NULL DEFAULT 0,
			paid TINYINT(1) NOT NULL DEFAULT 0,
			paid_date DATE NULL DEFAULT NULL,
			notes TEXT NULL,
			source_kind VARCHAR(10) NULL DEFAULT NULL,
			source_id BIGINT UNSIGNED NULL DEFAULT NULL,
			source_period CHAR(7) NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			KEY issue_date (issue_date),
			KEY paid (paid),
			KEY type (type),
			KEY source (source_kind, source_id)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'mmr_db_version', MMR_DB_VERSION );
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
			'companies',
			'events',
		);
	}

	/**
	 * Logical keys of the v1 tables removed in v2.
	 *
	 * `settlements` here is the v1 Receivables table, unrelated to the v2.2
	 * `events` table — never add `events` to this list, it is dropped on every
	 * version bump.
	 *
	 * @return string[]
	 */
	public static function legacy_keys() {
		return array(
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
		foreach ( array( 'students', 'classes', 'class_members', 'lessons', 'companies', 'events' ) as $key ) {
			$table = self::table( $key );
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
			if ( $count > 0 ) {
				return false;
			}
		}
		return true;
	}
}
