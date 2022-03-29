<?php

/**
 * Index WP MySQL For Speed
 *
 * @author: Oliver Jones
 * @copyright: 2022 Oliver Jones
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin0
 * Plugin Name: Index WP Users For Speed
 * Plugin URI:  https://plumislandmedia.org/index-wp-users-for-speed/
 * Description: Speed up your WordPress site with many users.
 * Requires at least: 5.2
 * Tested up to:      5.9.2
 * Requires PHP:      5.6
 * Author:       OllieJones
 * Author URI:   https://github.com/OllieJones
 * License:      GPL v2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  index-wp-users-for-speed
 * Domain Path:  /languages
 * Network:      true
 * Tags:         users, performance
 */

use IndexWpUsersForSpeed\Activator;
use IndexWpUsersForSpeed\Deactivator;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
  die;
}

const INDEX_WP_USERS_FOR_SPEED_NAME = 'index-wp-users-for-speed';
define( 'INDEX_WP_USERS_FOR_SPEED_FILENAME', plugin_basename( __FILE__ ) );
const INDEX_WP_USERS_FOR_SPEED_VERSION        = '1.0.0';
const INDEX_WP_USERS_FOR_SPEED_PREFIX         = 'index-wp-users-for-speed-';
const INDEX_WP_USERS_FOR_SPEED_KEY_PREFIX     = 'iufs';
const INDEX_WP_USERS_FOR_SPEED_SHORT_LIFETIME = 60;
const INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME  = 300;
const INDEX_WP_USERS_FOR_SPEED_BATCHSIZE      = 50;   //TODO make it bigger.

/** Error logging, useful for caught errors in cronjobs.
 *
 * @param $msg
 *
 * @return void
 * @noinspection PhpUnused
 */
function index_wp_users_for_speed_error_log( $msg ) {
  error_log( $msg );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/activator.php
 * @noinspection PhpIncludeInspection
 */
function activate_index_wp_users_for_speed() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/activator.php';
  Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/deactivator.php
 * @noinspection PhpIncludeInspection
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
/** @noinspection PhpIncludeInspection */
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
