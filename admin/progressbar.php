<?php

/**  */

namespace IndexWpUsersForSpeed;

/**
 * Progress bar handler.
 *
 *
 * @link       https://github.com/OllieJones
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/admin
 * @author     Ollie Jones <oj@plumislandmedia.net>
 */
class ProgressBar {

  private $plugin_name;
  private $options_name;
  private $version;
  private $indexer;
  private $pluginPath;
  private $percentComplete = 1;

  /**
   * Initialize the class and set its properties.
   *
   */
  public function __construct() {

    $this->plugin_name  = INDEX_WP_USERS_FOR_SPEED_NAME;
    $this->version      = INDEX_WP_USERS_FOR_SPEED_VERSION;
    $this->pluginPath   = plugin_dir_path( dirname( __FILE__ ) );
    $this->options_name = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'options';
    $this->indexer      = Indexer::getInstance();

    $this->percentComplete = $this->indexer->metaIndexRoleFraction();
    if ( $this->percentComplete < 1.0 || wp_doing_ajax() ) {
      add_filter( 'heartbeat_received', [ $this, 'heartbeat' ], 10, 2 );
    }
    if ( $this->percentComplete < 1.0 ) {
      add_action( 'admin_notices', [ $this, 'percent_complete_notice' ] );
      add_filter( 'heartbeat_settings', [ $this, 'heartbeatSettings' ], 10, 1 );
    }
  }

  public function percent_complete_notice() {
    if ( $this->percentComplete < 1.0 ) {
      wp_enqueue_script( $this->plugin_name . '_percent', plugin_dir_url( __FILE__ ) . 'js/percent.js', [], $this->version );
      $prefix  = esc_html__( 'User index rebuilding in progress:', 'index-wp-users-for-speed' );
      $suffix  = esc_html__( '% complete', 'index-wp-users-for-speed' );
      $percent = esc_html( number_format( $this->percentComplete * 100.0, 0 ) );
      $percent = "$prefix <span class=\"percent\">$percent</span>$suffix";
      ?>
        <div class="notice notice-info index-wp-users-for-speed is-dismissible">
            <p><?php echo $percent ?></p>
        </div>
      <?php
    }
  }

  public function heartbeat( $response, $data ) {
    if ( empty ( $data['index_wp_users_for_speed_percent'] ) ) {
      return $response;
    }
    $this->percentComplete                        = $this->indexer->metaIndexRoleFraction();
    $response['index_wp_users_for_speed_percent'] = number_format( $this->percentComplete, 3 );
    return $response;
  }

  public function heartbeatSettings( $settings ) {
    $settings['interval'] = 15;
    return $settings;
  }
}

new ProgressBar();