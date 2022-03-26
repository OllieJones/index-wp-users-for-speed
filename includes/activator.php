<?php

namespace IndexWpUsersForSpeed;

/**
 * Fired during plugin activation
 *
 * @link       https://github.com/OllieJones
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/includes
 * @author     Ollie Jones <oj@plumislandmedia.net>
 */
class Activator {

  /**
   * Short Description. Activate the plugin.
   *
   */
  public static function activate() {

    Activator::startIndexing();
  }


  /**
   * @return void
   */
  private static function startIndexing() {

  }

}
