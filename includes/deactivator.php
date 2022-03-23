<?php

namespace IndexWpUsersForSpeed;

/**
 * Fired during plugin deactivation
 *
 * @link       https://github.com/OllieJones
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/includes
 * @author     Ollie Jones <oj@plumislandmedia.net>
 */
class Deactivator {

  /**
   * We wipe out stashed indexes on deactivation, not deletion.
   *
   * It doesn't make sense to keep the indexes when the plugin isn't active
   * because they don't get maintained.
   *
   */
  public static function deactivate() {

    wp_unschedule_hook( 'index_wp_users_for_speed_repeating_task' );
    wp_unschedule_hook( 'index_wp_users_for_speed_task' );  // TODO not until we run all the deletes.
    delete_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts" );
    delete_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "editors" );


  }

}
