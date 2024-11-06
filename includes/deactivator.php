<?php

namespace IndexWpUsersForSpeed;

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
   * because it doesn't get maintained. Therefore, we delete it on deactivation,
   * not plugin deletion.
   *
   */
  public static function deactivate() {

    wp_unschedule_hook( 'index_wp_users_for_speed_repeating_task' );
    wp_unschedule_hook( 'index_wp_users_for_speed_task' );
    $sites = is_multisite() ? get_sites( [ 'fields' => 'ids' ] ) : [1];
    foreach ( $sites as $site_id ) {
      if ( is_multisite() ) {
        switch_to_blog( $site_id );
      }
      Deactivator::depopulateIndexMetadata();
      Deactivator::deleteCronOptions();
      if ( is_multisite() ) {
        restore_current_blog();
      }
    }
  }

  private static function depopulateIndexMetadata() {
    $depop = new DepopulateMetaIndexes();
    $depop->init();
    $done = false;
    while ( ! $done ) {
      $done = $depop->doChunk();
    }
  }

  private static function deleteCronOptions() {
    global $wpdb;
    $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM $wpdb->options WHERE option_name LIKE CONCAT(%s, '%%')",
        $wpdb->esc_like( INDEX_WP_USERS_FOR_SPEED_PREFIX_TASK ) )
    );
  }

}
