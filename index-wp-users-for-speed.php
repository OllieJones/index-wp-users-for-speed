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
 * Version:           1.0.0
 * Requires at least: 5.2
 * Tested up to:      5.9.1
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
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
  die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'INDEX_WP_USERS_FOR_SPEED_VERSION', '1.0.0' );
define( 'INDEX_WP_USERS_FOR_SPEED_PREFIX', 'index_wp_users_' );
define( 'INDEX_WP_USERS_FOR_SPEED_SHORT_LIFETIME', 60 );
define( 'INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME', 300 );

function index_wp_users_for_speed_error_log ($msg) {
  if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log ($msg);
  }
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-index- wp-users-for-speed-activator.php
 */
function activate_index_wp_users_for_speed() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-index-wp-users-for-speed-activator.php';
  Index_Wp_Users_For_Speed_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-index-wp-users-for-speed-deactivator.php
 */
function deactivate_index_wp_users_for_speed() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-index-wp-users-for-speed-deactivator.php';
  Index_Wp_Users_For_Speed_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_index_wp_users_for_speed' );
register_deactivation_hook( __FILE__, 'deactivate_index_wp_users_for_speed' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-index-wp-users-for-speed.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_index_wp_users_for_speed() {

  $plugin = new Index_Wp_Users_For_Speed();
  $plugin->run();

}

run_index_wp_users_for_speed();
