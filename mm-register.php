<?php
/**
 * Plugin Name:       MM Register
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

define( 'MMR_VERSION', '2.1.0' );

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

/** Capability required for every part of this plugin. */
if ( ! defined( 'MMR_CAP' ) ) {
	define( 'MMR_CAP', 'manage_options' );
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
