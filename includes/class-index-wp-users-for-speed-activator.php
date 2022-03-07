<?php

/**
 * Fired during plugin activation
 *
 * @link       https://github.com/OllieJones
 * @since      1.0.0
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/includes
 * @author     Ollie Jones <oj@plumislandmedia.net>
 */
class Index_Wp_Users_For_Speed_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
    global $iufs_db_version;
    $iufs_db_version = '1.0';

    Index_Wp_Users_For_Speed_Activator::createTables ();

	}

  /** Create necessary tables.
   * @see https://codex.wordpress.org/Creating_Tables_with_Plugins
   * @return void
   *
   */
  private static function createTables() {

      global $wpdb;
      global $iufs_db_version;

      $userattributes = $wpdb->prefix . 'iufs_userattributes';

      $charset_collate = $wpdb->get_charset_collate();

      $sql = "CREATE TABLE $userattributes (
		user_id bigint(20) NOT NULL,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		name tinytext NOT NULL,
		text text NOT NULL,
		url varchar(55) DEFAULT '' NOT NULL,
		PRIMARY KEY  (user_id)
	) $charset_collate;";

      require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
      dbDelta( $sql );

      add_option( 'iufs_db_version', $iufs_db_version );
    }

}
