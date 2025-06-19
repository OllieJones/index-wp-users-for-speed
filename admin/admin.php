<?php

/**  */

namespace IndexWpUsersForSpeed;

use DateTimeZone;
use Exception;

/**
 * The admin settings page of the plugin.
 *
 *
 * @link       https://github.com/OllieJones
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/admin
 * @author     Ollie Jones <oj@plumislandmedia.net>
 */
class Admin
    extends WordPressHooks {

 private $plugin_name;
 private $options_name;
 private $version;
 private $indexer;
 private $pluginPath;
 /** @var bool Sometimes sanitize() gets called twice. Avoid repeating operations. */
 private $didAnyOperations = false;

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

  /* action link for plugins page */
  add_filter( 'plugin_action_links_' . INDEX_WP_USERS_FOR_SPEED_FILENAME, [ $this, 'action_link' ] );
  add_filter( 'plugin_row_meta', array( $this, 'filter_plugin_row_meta' ), 10, 2 );


   parent::__construct();
 }

 /** @noinspection PhpUnused */
 public function action__admin_menu() {

  add_users_page(
      esc_html__( 'Index WP Users For Speed', 'index-wp-users-for-speed' ),
      esc_html__( 'Index For Speed', 'index-wp-users-for-speed' ),
      'manage_options',
      $this->plugin_name,
      [ $this, 'render_admin_page' ],
      12 );

  $this->addTimingSection();
 }

 private function addTimingSection() {

  $page = $this->plugin_name;
  add_settings_section( 'indexing',
      esc_html__( 'Rebuilding user indexes', 'index-wp-users-for-speed' ),
      [ $this, 'render_indexing_section' ],
      $page );

  add_settings_field( 'auto_rebuild',
      esc_html__( 'Rebuild indexes', 'index-wp-users-for-speed' ),
      [ $this, 'render_auto_rebuild_field' ],

      $page,
      'indexing' );

  add_settings_field( 'rebuild_time',
      esc_html__( '...at this time', 'index-wp-users-for-speed' ),
      [ $this, 'render_rebuild_time_field' ],
      $page,
      'indexing' );

  add_settings_section( 'quickedit',
      esc_html__( 'Choosing authors when editing posts and pages', 'index-wp-users-for-speed' ),
      [ $this, 'render_quickedit_section' ],
      $page );

  add_settings_field( 'quickedit_threshold',
      esc_html__( 'Use selection boxes', 'index-wp-users-for-speed' ),
      [ $this, 'render_quickedit_threshold_field' ],
      $page,
      'quickedit' );

  $option = get_option( $this->options_name );

  /* make sure default option is in place, to avoid double sanitize call */
  if ( $option === false ) {
   add_option( $this->options_name, [
       'auto_rebuild'              => 'on',
       'rebuild_time'              => '00:25',
       'quickedit_threshold_limit' => 50,
   ] );
  }

  register_setting(
      $this->options_name,
      $this->options_name,
      [ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ] );
 }

 /** @noinspection PhpRedundantOptionalArgumentInspection
  */
 public function sanitize_settings( $input ) {

  require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/indexer.php';
  $this->indexer = Indexer::getInstance();

  $didAnOperation = false;

  try {
   $autoRebuild = isset( $input['auto_rebuild'] ) && ( $input['auto_rebuild'] === 'on' || $input['auto_rebuild'] === 'nowon' );
   $nowRebuild  = isset( $input['auto_rebuild'] ) && ( $input['auto_rebuild'] === 'nowoff' || $input['auto_rebuild'] === 'nowon' );
   $time        = isset( $input['rebuild_time'] ) ? $input['rebuild_time'] : '';
   $timeString  = $this->formatTime( $time );

   if ( $timeString === false ) {
    add_settings_error(
        $this->options_name, 'rebuild',
        esc_html__( 'Incorrect time.', 'index-wp-users-for-speed' ),
        'error' );

    return $input;
   }

   if ( $nowRebuild ) {
    add_settings_error(
        $this->options_name, 'rebuild',
        esc_html__( 'User index rebuilding starting', 'index-wp-users-for-speed' ),
        'info' );
    if ( ! $this->didAnyOperations ) {
     $didAnOperation = true;
     $this->indexer->rebuildNow();
    }
   }

   if ( $autoRebuild ) {
    /* translators: 1: localized time like 1:22 PM or 13:22 */
    $format  = __( 'Automatic index rebuilding scheduled for %1$s each day', 'index-wp-users-for-speed' );
    $display = esc_html( sprintf( $format, $timeString ) );
    add_settings_error( $this->options_name, 'rebuild', $display, 'success' );
    if ( ! $this->didAnyOperations ) {
     $didAnOperation = true;
     $this->indexer->enableAutoRebuild( $this->timeToSeconds( $time ) );
    }
   } else {
    $display = esc_html__( 'Automatic index rebuilding disabled', 'index-wp-users-for-speed' );
    add_settings_error( $this->options_name, 'rebuild', $display, 'success' );
    if ( ! $this->didAnyOperations ) {
     $didAnOperation = true;
     $this->indexer->disableAutoRebuild();
    }
   }
  } catch ( Exception $ex ) {
   add_settings_error( $this->options_name, 'rebuild', esc_html( $ex->getMessage() ), 'error' );
  }
  if ( $didAnOperation ) {
   $this->didAnyOperations = true;
  }

  /* persist on and off */
  if ( isset( $input['auto_rebuild'] ) ) {
   $i                     = $input['auto_rebuild'];
   $i                     = $i === 'nowon' ? 'on' : $i;
   $i                     = $i === 'nowoff' ? 'off' : $i;
   $input['auto_rebuild'] = $i;
  }

  return $input;
 }

 /**
  * @param string $time like '16:42'
  *
  * @return string|false  time string or false if input was bogus.
  */
 private function formatTime( $time ) {
  $ts  = $this->timeToSeconds( $time );
  $utc = new DateTimeZone ( 'UTC' );

  return $ts === false ? $time : wp_date( get_option( 'time_format' ), $ts, $utc );
 }

 /**
  * @param string $time like '16:42'
  *
  * @return false|int
  */
 private function timeToSeconds( $time ) {
  try {
   if ( preg_match( '/^\d\d:\d\d$/', $time ) ) {
    $ts = intval( substr( $time, 0, 2 ) ) * HOUR_IN_SECONDS;
    $ts += intval( substr( $time, 3, 2 ) ) * MINUTE_IN_SECONDS;
    if ( $ts >= 0 && $ts < DAY_IN_SECONDS ) {
     return intval( $ts );
    }
   }
  } catch ( Exception $ex ) {
   return false;
  }

  return false;
 }

 public function render_admin_page() {
  /* avoid this overhead unless we actually USE the admin page */
  wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/admin.css', [], $this->version, 'all' );
  require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/indexer.php';
  $this->indexer = Indexer::getInstance();
  include_once $this->pluginPath . 'admin/views/page.php';
 }

 public function render_indexing_section() {
  ?>
  <p>
   <?php esc_html_e( 'You may rebuild your user indexes each day, or immediately.', 'index-wp-users-for-speed' ) ?>
   <?php esc_html_e( '(It is possible for them to become out-of-date.)', 'index-wp-users-for-speed' ) ?>
  </p>
  <?php
 }

 public function render_auto_rebuild_field() {
  $options     = get_option( $this->options_name );
  $autoRebuild = isset( $options['auto_rebuild'] ) ? $options['auto_rebuild'] : 'on';
  ?>
  <div>
          <span class="radioitem">
              <input type="radio"
                     id="auto_rebuild_yes"
                     name="<?php echo esc_attr( $this->options_name ) ?>[auto_rebuild]"
                     value="on"
                     <?php checked( $autoRebuild, 'on' ) ?> />
                <label for="auto_rebuild_yes"><?php esc_html_e( 'daily', 'index-wp-users-for-speed' ) ?></label>
          </span>
   <span class="radioitem">
              <input type="radio"
                     id="auto_rebuild_no"
                     name="<?php echo esc_attr( $this->options_name ) ?>[auto_rebuild]"
                     value="off"
                     <?php checked( $autoRebuild, 'off' ) ?> />
                <label for="auto_rebuild_no"><?php esc_html_e( 'never', 'index-wp-users-for-speed' ) ?></label>
          </span>
   <span class="radioitem">
              <input type="radio"
                     id="auto_rebuild_now_daily"
                     name="<?php echo esc_attr( $this->options_name ) ?>[auto_rebuild]"
                     value="nowon"
                     <?php checked( $autoRebuild, 'nowon' ) ?> />
                <label
                    for="auto_rebuild_now_daily"><?php esc_html_e( 'immediately, then daily', 'index-wp-users-for-speed' ) ?></label>
          </span>
   <span class="radioitem">
              <input type="radio"
                     id="auto_rebuild_now_only"
                     name="<?php echo esc_attr( $this->options_name ) ?>[auto_rebuild]"
                     value="nowoff"
                     <?php checked( $autoRebuild, 'nowoff' ) ?> />
                <label
                    for="auto_rebuild_now_only"><?php esc_html_e( 'immediately, but not daily', 'index-wp-users-for-speed' ) ?></label>
          </span>
  </div>
  <?php
 }

 public function render_rebuild_time_field() {
  $options     = get_option( $this->options_name );
  $rebuildTime = isset( $options['rebuild_time'] ) ? $options['rebuild_time'] : '00:25';
  ?>
  <div>
   <!--suppress HtmlFormInputWithoutLabel -->
   <input type="time"
          id="rebuild_time"
          name="<?php echo esc_attr( $this->options_name ) ?>[rebuild_time]"
          value="<?php echo esc_attr( $rebuildTime ) ?>">
  </div>
  <p>
   <?php esc_html_e( 'Avoid rebuilding exactly on the hour to avoid contending with other processing jobs.', 'index-wp-users-for-speed' ) ?>
  </p>
  <?php
 }

 public function render_now_rebuild_field() {
  ?>
  <div>
   <!--suppress HtmlFormInputWithoutLabel -->
   <input type="checkbox"
          id="rebuild_now"
          name="<?php echo esc_attr( $this->options_name ) ?>[now_rebuild]">
  </div>
  <?php
 }

 public function render_now_remove_field() {
  ?>
  <div>
   <!--suppress HtmlFormInputWithoutLabel -->
   <input type="checkbox"
          id="rebuild_now"
          name="<?php echo esc_attr( $this->options_name ) ?>[now_remove]">
  </div>
  <?php
 }

 public function render_quickedit_section() {
  ?>
  <p>
   <?php esc_html_e( 'Author-choice dropdown menus can be unwieldy when your site has many authors. If you have more than this number of authors, you will see selection boxes instead of dropdown menus. Choose an author by typing a few characters of the name into the selection box.
   
   ', 'index-wp-users-for-speed' ) ?>
  </p>
  <?php
 }

 public function render_quickedit_threshold_field() {
  $options = get_option( $this->options_name );
  $limit   = isset( $options['quickedit_threshold_limit'] ) ? $options['quickedit_threshold_limit'] : 50;
  ?>
  <div>
   <label
       for="quickedit_threshold_limit">
    <span><?php esc_html_e( 'when you have more than', 'index-wp-users-for-speed' ) ?></span>
    <input
        type="number"
        id="quickedit_threshold_limit"
        name="<?php echo esc_attr( $this->options_name ) ?>[quickedit_threshold_limit]"
        min="10" max="100"
        value="<?php echo esc_attr( $limit ) ?>">
    <span><?php esc_html_e( 'authors registered on your site.', 'index-wp-users-for-speed' ) ?></span>
   </label></div>
  <?php
 }

 /**
  * Filters the list of action links displayed for a specific plugin in the Plugins list table.
  *
  * The dynamic portion of the hook name, `$plugin_file`, refers to the path
  * to the plugin file, relative to the plugins directory.
  *
  * @param string[] $actions An array of plugin action links. By default, this can include
  *                              'activate', 'deactivate', and 'delete'. With Multisite active
  *                              this can also include 'network_active' and 'network_only' items.
  * @param string $plugin_file Path to the plugin file relative to the plugins directory.
  * @param array $plugin_data An array of plugin data. See `get_plugin_data()`
  *                              and the {@see 'plugin_row_meta'} filter for the list
  *                              of possible values.
  * @param string $context The plugin context. By default this can include 'all',
  *                              'active', 'inactive', 'recently_activated', 'upgrade',
  *                              'mustuse', 'dropins', and 'search'.
  *
  * @since 2.7.0
  * @since 4.9.0 The 'Edit' link was removed from the list of action links.
  *
  * @noinspection PhpDocSignatureInspection
  * @noinspection GrazieInspection
  */
 public function action_link( $actions ) {
   $mylinks = [
     '<a href="' . admin_url( 'users.php?page=' . $this->plugin_name ) . '">' . __( 'Settings' ) . '</a>',
   ];

  return array_merge( $mylinks, $actions );
 }

  /**
   * Filters the array of row meta for each plugin in the Plugins list table.
   *
   * @param array<int, string> $plugin_meta An array of the plugin's metadata.
   * @param string             $plugin_file Path to the plugin file relative to the plugins directory.
   * @return array<int, string> Updated array of the plugin's metadata.
   */
  public function filter_plugin_row_meta( array $plugin_meta, $plugin_file ) {
    if ( INDEX_WP_USERS_FOR_SPEED_FILENAME !== $plugin_file ) {
      return $plugin_meta;
    }

    $plugin_meta[] = sprintf(
      '<a href="%1$s"><span class="dashicons dashicons-star-filled" aria-hidden="true" style="font-size:14px;line-height:1.3"></span>%2$s</a>',
      'https://github.com/sponsors/OllieJones',
      esc_html_x( 'Sponsor', 'verb', 'index-wp-users-for-speed' )
    );

    return $plugin_meta;
  }




}

new Admin();
