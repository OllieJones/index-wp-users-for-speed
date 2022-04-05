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
   * It doesn't make sense to keep the index metadata when the plugin isn't active
   * because they don't get maintained. Therefore we delete them on deactivation,
   * not plugin deletion.
   *
   */
  public static function deactivate() {

    wp_unschedule_hook( 'index_wp_users_for_speed_repeating_task' );
    wp_unschedule_hook( 'index_wp_users_for_speed_task' );

    Deactivator::depopulateIndexMetadata ();
    Deactivator::deleteTransients();
  }

  private static function deleteTransients() {
    global $wpdb;
    $transients = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT option_name FROM $wpdb->options WHERE option_name LIKE CONCAT(%s, '%%')",
        $wpdb->esc_like( '_transient_' . INDEX_WP_USERS_FOR_SPEED_PREFIX ) )
    );
    foreach ( $transients as $transient ) {
      $name = str_replace( '_transient_', '', $transient->option_name );
      delete_transient( $name );
    }
  }

  private static function depopulateIndexMetadata () {
    $depop = new DepopulateMetaIndexes();
    $depop->init();
    while (!$depop->doChunk()) {
      /* empty */
    }
  }

}
