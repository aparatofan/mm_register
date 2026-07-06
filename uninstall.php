<?php
/**
 * Uninstall handler.
 *
 * Per spec §3: keep data by default on uninstall. Data is only removed via the
 * explicit "Delete all plugin data" tool (Tools → Danger zone). Therefore this
 * file intentionally does NOT drop tables — it only removes the transient
 * import-result caches, which are ephemeral.
 *
 * @package MM_Register
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clean up any lingering per-user import-result transients.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_mmr\_import\_result\_%' OR option_name LIKE '\_transient\_timeout\_mmr\_import\_result\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery

// NOTE: mmr_* tables and the mmr_db_version option are deliberately preserved.
