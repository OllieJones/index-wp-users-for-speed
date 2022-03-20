<?php

/**  */

namespace OllieJones\index_wp_users_for_speed;

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
   * @since    1.0.0
   */
  public function __construct() {

    $this->plugin_name  = INDEX_WP_USERS_FOR_SPEED_NAME;
    $this->version      = INDEX_WP_USERS_FOR_SPEED_VERSION;
    $this->pluginPath   = plugin_dir_path( dirname( __FILE__ ) );
    $this->options_name = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'options';

    /* action link for plugins page */
    add_filter( 'plugin_action_links_' . INDEX_WP_USERS_FOR_SPEED_FILENAME, [ $this, 'action_link' ] );

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
    $this->addRebuildNowSection();
    $this->addRemoveNowSection();

  }

  private function addTimingSection() {

    $sectionId = 'timing';
    $page      = $this->plugin_name;
    add_settings_section( $sectionId,
      esc_html__( 'Rebuilding user indexes', 'index-wp-users-for-speed' ),
      [ $this, 'render_timing_section' ],
      $page );

    add_settings_field( 'auto_rebuild',
      esc_html__( 'Rebuild indexes', 'index-wp-users-for-speed' ),
      [ $this, 'render_auto_rebuild_field' ],

      $page,
      $sectionId );

    add_settings_field( 'rebuild_time',
      esc_html__( '...at this time', 'index-wp-users-for-speed' ),
      [ $this, 'render_rebuild_time_field' ],
      $page,
      $sectionId );

    $option = get_option($this->options_name);

    /* make sure default option is in place, to avoid double santize call */
    if ($option === false) {
        add_option ( $this->options_name, [
          'auto_rebuild' => 'on',
          'rebuild_time' => '00:25'
        ]);
    }

    register_setting(
      $this->options_name,
      $this->options_name,
      [ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ] );
  }

  /**
   * @return void
   */
  private function addRebuildNowSection() {

    $sectionId = 'rebuild-now';
    $page      = $this->plugin_name . '-rebuild-now';
    add_settings_section( $sectionId,
      esc_html__( 'Rebuild indexes', 'index-wp-users-for-speed' ),
      [ $this, 'render_empty' ],
      $page );

    $optionGroup = $this->options_name . '-rebuild';
    $optionName = $this->options_name . '-rebuild';
    $option = get_option($optionName);
    /* make sure default option is in place, to avoid double santize call */
    if ($option === false) {
      add_option ( $optionName, []);
    }

    register_setting( $optionGroup, $optionName );
  }

  /**
   * @return void
   */
  private function addRemoveNowSection() {

    $sectionId = 'remove-now';
    $page      = $this->plugin_name . '-remove-now';
    add_settings_section( $sectionId,
      esc_html__( 'Remove indexes', 'index-wp-users-for-speed' ),
      [ $this, 'render_empty' ],
      $page );


    $optionGroup = $this->options_name . '-remove';
    $optionName = $this->options_name . '-remove';
    $option = get_option($optionName);
    /* make sure default option is in place, to avoid double santize call */
    if ($option === false) {
      add_option ( $optionName, []);
    }

    register_setting( $optionGroup, $optionName );
  }

  public function render_empty() {
    echo '';
  }

  public function sanitize_settings( $input ) {

    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/indexer.php';
    $this->indexer = Indexer::getInstance();

    $didAnOperation = false;

    try {
      $autoRebuild = isset( $input['auto_rebuild'] ) && $input['auto_rebuild'] === 'on';
      $time        = isset( $input['rebuild_time'] ) ? $input['rebuild_time'] : '';
      $timeString  = $this->formatTime( $time );

      if ( $timeString === false ) {
        add_settings_error( $this->options_name, 'rebuild',
          esc_html__( 'Incorrect time.', 'index-wp-users-for-speed' ),
          'error' );

        return $input;
      }

      $rebuildNow = isset( $input['now_rebuild'] ) && $input['now_rebuild'] === 'on';
      $removeNow  = isset( $input['now_remove'] ) && $input['now_remove'] === 'on';


      if ( $rebuildNow && $removeNow ) {
        add_settings_error( $this->options_name, 'rebuild',
          esc_html__( 'You may rebuild or remove indexes immediately, but not both. Please choose just one.', 'index-wp-users-for-speed' ),
          'error' );

        return $input;
      } else if ( $rebuildNow ) {
        add_settings_error( $this->options_name, 'rebuild',
          esc_html__( 'Index rebuilding process starting', 'index-wp-users-for-speed' ),
          'info' );
        if ( ! $this->didAnyOperations ) {
          $didAnOperation = true;
          $this->indexer->rebuildNow();
        }
      } else if ( $removeNow ) {
        add_settings_error( $this->options_name, 'rebuild',
          esc_html__( 'Index removing process starting', 'index-wp-users-for-speed' ),
          'info' );
        if ( ! $this->didAnyOperations ) {
          $didAnOperation = true;
          $this->indexer->removeNow();
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
        $display = esc_html( 'Automatic index rebuilding disabled', 'index-wp-users-for-speed' );
        add_settings_error( $this->options_name, 'rebuild', $display, 'success' );
        if ( ! $this->didAnyOperations ) {
          $didAnOperation = true;
          $this->indexer->disableAutoRebuild( $time );
        }
      }
    } catch ( Exception $ex ) {
      add_settings_error( $this->options_name, 'rebuild', $ex->getMessage(), 'error' );
    }
    if ( $didAnOperation ) {
      $this->didAnyOperations = true;
    }

    return $input;
  }

  /**
   * @param string $time like '16:42'
   *
   * @return string  time string or false if input was bogus.
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
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/indexer.php';
    $this->indexer = Indexer::getInstance();
    include_once $this->pluginPath . 'admin/views/page.php';
  }

  public function render_timing_section() {
    ?>
      <p>
        <?= esc_html__( 'You may rebuild your user indexes each day, or immediately.', 'index-wp-users-for-speed' ) ?>
        <?= esc_html__( '(It is possible for them to become out-of-date.)', 'index-wp-users-for-speed' ) ?>
      </p>
    <?php
  }

  public function render_auto_rebuild_field() {
    $options     = get_option( $this->options_name );
    $autoRebuild = isset( $options['auto_rebuild'] ) ? $options['auto_rebuild'] : "on";
    ?>
      <div>
      <span class="radioitem">
          <input type="radio"
                 id="auto_rebuild_yes"
                 name="<?= $this->options_name ?>[auto_rebuild]"
                 value="on"
                 <?= $autoRebuild === 'on' ? 'checked' : '' ?> >
            <label for="auto_rebuild_yes">
                <?= esc_html__( 'daily', 'index-wp-users-for-speed' ) ?>
            </label>
      </span>
          <span class="radioitem">
          <input type="radio"
                 id="auto_rebuild_no"
                 name="<?= $this->options_name ?>[auto_rebuild]"
                 value="off"
                 <?= $autoRebuild !== 'on' ? 'checked' : '' ?> >
            <label for="auto_rebuild_no">
                <?= esc_html__( 'never', 'index-wp-users-for-speed' ) ?>
            </label>
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
                 name="<?= $this->options_name ?>[rebuild_time]"
                 value="<?= $rebuildTime ?>">
      </div>
      <p>
        <?= esc_html__( 'Avoid rebuilding exactly on the hour to avoid contending with other processing jobs.' ) ?>
      </p>
    <?php
  }

  public function render_now_rebuild_field() {
    ?>
      <div>
          <!--suppress HtmlFormInputWithoutLabel -->
          <input type="checkbox"
                 id="rebuild_now"
                 name="<?= $this->options_name ?>[now_rebuild]">
      </div>
    <?php
  }

  public function render_now_remove_field() {
    ?>
      <div>
          <!--suppress HtmlFormInputWithoutLabel -->
          <input type="checkbox"
                 id="rebuild_now"
                 name="<?= $this->options_name ?>[now_remove]">
      </div>
    <?php
  }

  /**
   * Register the stylesheets for the admin area.
   *
   * @since    1.0.0
   * @noinspection PhpUnused
   */
  public function action__admin_enqueue_scripts() {
    wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/admin.css', [], $this->version, 'all' );
    //TODO wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/index-wp-users-for-speed-admin.js', [ 'jquery' ], $this->version, false );
  }

  /**
   * Filters the list of action links displayed for a specific plugin in the Plugins list table.
   *
   * The dynamic portion of the hook name, `$plugin_file`, refers to the path
   * to the plugin file, relative to the plugins directory.
   *
   * @param string[] $actions An array of plugin action links. By default this can include
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
   */
  public function action_link( $actions ) {
    $mylinks = [
      '<a href="' . admin_url( 'users.php?page=' . $this->plugin_name ) . '">' . __( 'Settings' ) . '</a>',
    ];

    return array_merge( $mylinks, $actions );
  }


}

new Admin();