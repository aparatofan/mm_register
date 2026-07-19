<?php
/**
 * Plugin Name:       TBT Register
 * Plugin URI:        https://mariuszmirecki.pl/
 * Description:       Private back-office register for NET ENGLISH: weekly lesson calendar, students / classes / companies database and a computed monthly settlement report. No front-end output.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Mariusz Mirecki
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mm-register
 *
 * @package MM_Register
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'tbt_is_owner' ) ) {
	/**
	 * Grant a virtual `tbt_owner` capability to the owner account and strip it
	 * from everyone else.
	 *
	 * @param array   $allcaps All capabilities of the user.
	 * @param array   $caps    Required capabilities being checked.
	 * @param array   $args    Arguments passed to the check.
	 * @param WP_User $user    The user object.
	 * @return array
	 */
	function tbt_hub_grant_owner_cap( $allcaps, $caps, $args, $user ) {
		$email = isset( $user->user_email ) ? strtolower( $user->user_email ) : '';

		if ( $email && $email === strtolower( TBT_OWNER_EMAIL ) ) {
			$allcaps['tbt_owner'] = true;
		} else {
			unset( $allcaps['tbt_owner'] );
		}

		return $allcaps;
	}
	add_filter( 'user_has_cap', 'tbt_hub_grant_owner_cap', 10, 4 );

	/**
	 * Convenience wrapper for use inside TBT plugins (menus, AJAX, REST).
	 */
	function tbt_is_owner() {
		return current_user_can( 'tbt_owner' );
	}
}

define( 'MMR_VERSION', '2.2.0' );

/** Stored in the mmr_db_version option; bumping it triggers the upgrade routine. */
define( 'MMR_DB_VERSION', '2.1' );
define( 'MMR_FILE', __FILE__ );
define( 'MMR_PATH', plugin_dir_path( __FILE__ ) );
define( 'MMR_URL', plugin_dir_url( __FILE__ ) );
define( 'MMR_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Number of weekday columns shown in the calendar grid.
 * Business week is Monday–Friday (5). Kept as a constant so a future
 * iteration can add Saturday without hunting through the codebase.
 */
if ( ! defined( 'MMR_DAY_COLUMNS' ) ) {
	define( 'MMR_DAY_COLUMNS', 5 );
}

/**
 * Number of lesson slot rows per day column.
 */
if ( ! defined( 'MMR_SLOTS_PER_DAY' ) ) {
	define( 'MMR_SLOTS_PER_DAY', 12 );
}

/**
 * Capability required for every part of this plugin.
 *
 * This is `tbt_owner` — a virtual capability granted only to the site owner
 * (see the owner-only block above). Because financial and personal client
 * data lives here, no other administrator may reach any page, form action or
 * AJAX handler. Enforced unconditionally, independent of TBT Hub.
 */
if ( ! defined( 'MMR_CAP' ) ) {
	define( 'MMR_CAP', 'tbt_owner' );
}

require_once MMR_PATH . 'includes/helpers.php';
require_once MMR_PATH . 'includes/class-mmr-db.php';
require_once MMR_PATH . 'includes/class-mmr-data.php';
require_once MMR_PATH . 'includes/class-mmr-import.php';
require_once MMR_PATH . 'includes/class-mmr-ajax.php';
require_once MMR_PATH . 'includes/class-mmr-admin.php';
require_once MMR_PATH . 'includes/class-mmr-plugin.php';

register_activation_hook( __FILE__, array( 'MMR_DB', 'activate' ) );

/**
 * Boot the plugin.
 */
function mmr_boot() {
	MMR_Plugin::instance();
}
add_action( 'plugins_loaded', 'mmr_boot' );

/**
 * Register this plugin on the TBT Hub Overview page.
 *
 * Loaded unconditionally. The card is owner-only (`tbt_owner`), so it is
 * hidden from every other administrator. Register keeps its own top-level
 * menu, so the card links to the standard admin.php?page=mmr-calendar route.
 *
 * @param array $items Existing hub items.
 * @return array
 */
function mmr_register_hub_item( $items ) {
	$items[] = array(
		'slug'        => 'mmr-calendar',
		'title'       => 'TBT Register',
		'description' => 'Private owner-only back-office: weekly lesson calendar, students/classes/companies database, and monthly settlement reports.',
		'capability'  => 'tbt_owner',
	);
	return $items;
}
add_filter( 'tbt_hub_items', 'mmr_register_hub_item' );
