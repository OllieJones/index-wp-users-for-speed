<?php

/**  */

namespace IndexWpUsersForSpeed;

if ( ! defined( 'ABSPATH' ) ) die;

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
 private $version;
 private $indexer;
 private $percentComplete;
 private $available;

 /**
  * Initialize the class and set its properties.
  *
  */
 public function __construct() {

  $this->plugin_name = INDEX_WP_USERS_FOR_SPEED_NAME;
  $this->version     = INDEX_WP_USERS_FOR_SPEED_VERSION;
  $this->indexer     = Indexer::getInstance();

  $this->percentComplete = $this->indexer->metaIndexRoleFraction();
  $this->available       = $this->indexer->isMetaIndexRoleAvailable();
  if ( $this->percentComplete < 1.0 || wp_doing_ajax() ) {
   add_filter( 'heartbeat_received', [ $this, 'heartbeat' ], 10, 2 );
  }
  if ( $this->percentComplete < 1.0 ) {
   add_action( 'admin_notices', [ $this, 'percent_complete_notice' ] );
   add_filter( 'heartbeat_settings', [ $this, 'heartbeatSettings' ], 10, 1 );
  }
 }

 /** Display progress notice bar if need be, dashboard only.
  * @return void
  */
 public function percent_complete_notice() {
  if ( $this->percentComplete < 1.0 ) {
   wp_enqueue_script( $this->plugin_name . '_percent', plugin_dir_url( __FILE__ ) . 'js/percent.js', [], $this->version, array('in_footer' => true ) );
   $suffix = __( '% complete.', 'index-wp-users-for-speed' );
   if ( $this->available ) {
    $prefix   = __( 'Background user index refresh in progress:', 'index-wp-users-for-speed' );
    $sentence = __( 'You may use your site normally during index refreshing.', 'index-wp-users-for-speed' );
   } else {
    $prefix   = __( 'Background user index building in progress:', 'index-wp-users-for-speed' );
    $sentence = __( 'You may use your site normally during index building.', 'index-wp-users-for-speed' );
   }
   $percent = esc_html( number_format( $this->percentComplete * 100.0, 0 ) );
   $message = "$prefix <span class=\"percent\">$percent</span>$suffix $sentence";
   self::render_notice(
       $message,
       array(
           'type'               => 'info',
           'dismissible'        => true,
           'additional_classes' => array( 'index-wp-users-for-speed' ),
       ) );
  }
 }

 /** Render an admin notice, using the WordPress 6.4 admin notice functions when available.
  * Falls back to equivalent markup on older WordPress versions.
  *
  * @param string $message Notice message. May contain safe HTML.
  * @param array  $args    Notice arguments: type, dismissible, additional_classes.
  *
  * @return void
  */
 public static function render_notice( $message, $args = array() ) {
  if ( function_exists( 'wp_admin_notice' ) ) {
   wp_admin_notice( $message, $args );
   return;
  }
  $args    = wp_parse_args( $args, array(
      'type'               => '',
      'dismissible'        => false,
      'additional_classes' => array(),
  ) );
  $classes = 'notice';
  if ( '' !== $args['type'] ) {
   $classes .= ' notice-' . $args['type'];
  }
  if ( $args['dismissible'] ) {
   $classes .= ' is-dismissible';
  }
  if ( ! empty( $args['additional_classes'] ) ) {
   $classes .= ' ' . implode( ' ', $args['additional_classes'] );
  }
  ?>
  <div class="<?php echo esc_attr( $classes ); ?>">
   <p><?php echo wp_kses_post( $message ); ?></p>
  </div>
  <?php
 }

 /** Heartbeat filter to update percent complete in progress bar.
  *
  * @param array $response Response to heartbeat, to mung.
  * @param array $data Incoming request.
  *
  * @return array Updated response array.
  */
 public function heartbeat( $response, $data ) {
  if ( empty ( $data['index_wp_users_for_speed_percent'] ) ) {
   return $response;
  }
  $this->percentComplete                        = $this->indexer->metaIndexRoleFraction();
  $response['index_wp_users_for_speed_percent'] = number_format( $this->percentComplete, 3 );
  return $response;
 }

 /** Filter to set heartbeat to frequent during index build or refresh.
  * Each heartbeat kicks the cronjob, so this is a way to keep the job
  * going efficiently on quiet sites.
  * (On busy sites this doesn't matter.)
  *
  * @param array $settings Heartbeat settings.
  *
  * @return array
  */
 public function heartbeatSettings( $settings ) {
  $settings['interval'] = 15;
  return $settings;
 }
}

new ProgressBar();
