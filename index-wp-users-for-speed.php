<?php

/**
 * Index WP MySQL For Speed
 * @author: Oliver Jones
 * @copyright: 2022-2024 Oliver Jones
 * @license  GPL-2.0-or-later
 * @wordpress-plugin0
 * Plugin Name: Index WP Users For Speed
 * Version:     1.1.11
 * Stable tag:  1.1.11
 * Plugin URI:  https://plumislandmedia.org/index-wp-users-for-speed/
 * Description: Speed up your WordPress site with thousands of users.
 * Requires at least: 5.2
 * Tested up to:      6.8.2
 * Requires PHP:      5.6
 * Author:       Oliver Jones
 * Author URI:   https://github.com/OllieJones
 * License:      GPL v2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  index-wp-users-for-speed
 * Domain Path:  /languages
 * Network:      true
 * Tags:         users, database, index, performance, largesite
 */

use IndexWpUsersForSpeed\Activator;
use IndexWpUsersForSpeed\Deactivator;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
  die;
}

const INDEX_WP_USERS_FOR_SPEED_NAME = 'index-wp-users-for-speed';
define( 'INDEX_WP_USERS_FOR_SPEED_FILENAME', plugin_basename( __FILE__ ) );
const INDEX_WP_USERS_FOR_SPEED_VERSION        = '1.1.11';
const INDEX_WP_USERS_FOR_SPEED_PREFIX         = 'index-wp-users-for-speed-';
const INDEX_WP_USERS_FOR_SPEED_PREFIX_TASK    = 'index-wp-users-for-speed-task';
const INDEX_WP_USERS_FOR_SPEED_HOOKNAME       = 'index_wp_users_for_speed_task';
const INDEX_WP_USERS_FOR_SPEED_KEY_PREFIX     = 'iufs';
const INDEX_WP_USERS_FOR_SPEED_SHORT_LIFETIME = DAY_IN_SECONDS * 15;
const INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME  = MONTH_IN_SECONDS * 3;
const INDEX_WP_USERS_FOR_SPEED_DELAY_CRONKICK = 2;

/**
 * The number of users we process at a time when creating index entries in wp_usermeta.
 *
 * This number is limited to avoid swamping MariaDB / MySQL with vast transactions
 * when manipulating large numbers of users. The batches run with wpcron.
 */
const INDEX_WP_USERS_FOR_SPEED_BATCHSIZE = 5000;
/**
 * The number of users we process per transaction when creating index entries in wp_usermeta.
 * This must be smaller than INDEX_WP_USERS_FOR_SPEED_BATCHSIZE.
 */
const INDEX_WP_USERS_FOR_SPEED_CHUNKSIZE = 50;

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/activator.php
 */
function activate_index_wp_users_for_speed() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/activator.php';
  Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/deactivator.php
 */
function deactivate_index_wp_users_for_speed() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/deactivator.php';
  Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_index_wp_users_for_speed' );
register_deactivation_hook( __FILE__, 'deactivate_index_wp_users_for_speed' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */

$plugin = new IndexWpUsersForSpeed\Index_Wp_Users_For_Speed();
$plugin->run();
