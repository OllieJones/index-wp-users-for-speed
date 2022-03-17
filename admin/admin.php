<?php

/**  */

namespace OllieJones\index_wp_users_for_speed;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @link       https://github.com/OllieJones
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/admin
 * @author     Ollie Jones <oj@plumislandmedia.net>
 */
class Admin
  extends WordPressHooks {

  private static $messages;

  /**
   * The ID of this plugin.
   *
   * @since    1.0.0
   * @access   private
   * @var      string $plugin_name The ID of this plugin.
   */
  private $plugin_name;
  /**
   * The version of this plugin.
   *
   * @since    1.0.0
   * @access   private
   * @var      string $version The current version of this plugin.
   */
  private $version;
  private $indexer;
  private $pluginPath;
  private $message;

  /**
   * Initialize the class and set its properties.
   *
   * @since    1.0.0
   */
  public function __construct() {

    $this->plugin_name = INDEX_WP_USERS_FOR_SPEED_NAME;
    $this->version     = INDEX_WP_USERS_FOR_SPEED_VERSION;
    $this->pluginPath  = plugin_dir_path( dirname( __FILE__ ) );
    /* after a POST, we get a redirect with ?st=message */
    $this->message = isset( $_REQUEST['st'] ) ? sanitize_key( $_REQUEST['st'] ) : null;

    self::$messages = [
      'started'   => __( 'User Indexing Started', 'index-wp-users-for-speed' ),
      'removed'   => __( 'User Indexing Removed', 'index-wp-users-for-speed' ),
      /* translators: 1: fraction complete on index */
      'progress'  => __( 'User Indexing %1$s Complete', 'index-wp-users-for-speed' ),
      'completed' => __( 'User Indexing Complete', 'index-wp-users-for-speed' ),
      /* translators: 1: message id, like 'started' or 'removed' This is a warning */
      'default'   => null,
    ];

    /* postback handlers for form. */
    add_action( 'admin_post_index-wp-users-for-speed-action', [ $this, 'post_action_unverified' ] );
    add_action( 'index-wp-users-for-speed-post-filter', [ $this, 'post_filter' ] );

    /* action link for plugins page */
    add_filter( 'plugin_action_links_' . INDEX_WP_USERS_FOR_SPEED_FILENAME, [$this, 'action_link']);

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

  }

  public function render_admin_page() {
    /* avoid this overhead unless we actually USE the admin page */
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/indexer.php';
    $this->indexer = Indexer::getInstance();
    include_once $this->pluginPath . 'admin/views/page.php';
  }

  /** untrusted post action
   * @return void
   */
  public function post_action_unverified() {
    $valid = check_admin_referer( $this->plugin_name, 'reindex' );
    if ( $valid === 1 ) {
      if ( current_user_can( 'manage_options' ) ) {
        $params                    = $_REQUEST;
        $params['postback_status'] = 'default';
        $message                   = apply_filters( $this->plugin_name . '-post-filter', $params );
        $postbackStatus            = $message['postback_status'];
        wp_safe_redirect( add_query_arg( 'st', $postbackStatus, wp_get_referer() ) );

        return;
      }
    }
    status_header( 403 );
  }

  /** Form post handler filter, after verification.
   *
   * @param array $params
   *
   * @return array  containing a 'postback_status' item.
   */
  public function post_filter( $params ) {
    /* modify  $params['postback_status'] to get something else. */

    return $params;
  }

  /**
   * Register the stylesheets for the admin area.
   *
   * @since    1.0.0
   * @noinspection PhpUnused
   */
  public function action__admin_enqueue_scripts() {
    //TODO wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/index-wp-users-for-speed-admin.css', [], $this->version, 'all' );
    //TODO wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/index-wp-users-for-speed-admin.js', [ 'jquery' ], $this->version, false );
  }

  protected function getMessage() {
    if ( $this->message ) {
      $message = array_key_exists( $this->message, self::$messages ) ? $this->message : 'default';
      if ( $message !== 'default' ) {
        return sprintf( self::$messages[ $message ], $this->message );
      }
    }

    return false;
  }

  /**
   * Filters the list of action links displayed for a specific plugin in the Plugins list table.
   *
   * The dynamic portion of the hook name, `$plugin_file`, refers to the path
   * to the plugin file, relative to the plugins directory.
   *
   * @param string[] $actions     An array of plugin action links. By default this can include
   *                              'activate', 'deactivate', and 'delete'. With Multisite active
   *                              this can also include 'network_active' and 'network_only' items.
   * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
   * @param array    $plugin_data An array of plugin data. See `get_plugin_data()`
   *                              and the {@see 'plugin_row_meta'} filter for the list
   *                              of possible values.
   * @param string   $context     The plugin context. By default this can include 'all',
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