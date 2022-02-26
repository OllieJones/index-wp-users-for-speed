<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/OllieJones
 * @since      1.0.0
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/admin
 * @author     Ollie Jones <oj@plumislandmedia.net>
 */
class Index_Wp_Users_For_Speed_Admin {

  /**
   * @var int lifetime of transients.
   */
  public $timeToLive = 60;
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
  private $userCounts;

  /**
   * Initialize the class and set its properties.
   *
   * @param string $plugin_name The name of this plugin.
   * @param string $version The version of this plugin.
   *
   * @since    1.0.0
   */
  public function __construct( $plugin_name, $version ) {

    $this->plugin_name = $plugin_name;
    $this->version     = $version;

  }

  /**
   * Register the stylesheets for the admin area.
   *
   * @since    1.0.0
   */
  public function enqueue_styles() {

    /**
     * This function is provided for demonstration purposes only.
     *
     * An instance of this class should be passed to the run() function
     * defined in Index_Wp_Users_For_Speed_Loader as all of the hooks are defined
     * in that particular class.
     *
     * The Index_Wp_Users_For_Speed_Loader will then create the relationship
     * between the defined hooks and the functions defined in this
     * class.
     */

    wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/index-wp-users-for-speed-admin.css', [], $this->version, 'all' );
  }

  /**
   * Register the JavaScript for the admin area.
   *
   * @since    1.0.0
   */
  public function enqueue_scripts() {

    /**
     * This function is provided for demonstration purposes only.
     *
     * An instance of this class should be passed to the run() function
     * defined in Index_Wp_Users_For_Speed_Loader as all of the hooks are defined
     * in that particular class.
     *
     * The Index_Wp_Users_For_Speed_Loader will then create the relationship
     * between the defined hooks and the functions defined in this
     * class.
     */

    wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/index-wp-users-for-speed-admin.js', [ 'jquery' ], $this->version, false );
  }

  public function admin_init() {
    if ( wp_doing_ajax() || wp_doing_cron() || ! is_admin() ) {
      return;
    }
    $this->getUserCounts();
    /* once we have a user count cached, we can intercept further counting */
    add_filter( 'pre_count_users', [ $this, 'count_users_by_role' ], 10, 3 );
  }

  private function getUserCounts() {
    if ( is_array( $this->userCounts ) ) {
      return $this->userCounts;
    }
    $transientName = $this->plugin_name . "_user_counts";
    $userCounts    = get_transient( $transientName );
    if ( $userCounts === false ) {
      $userCounts = count_users();
      set_transient( $transientName, $userCounts, $this->timeToLive );
    }
    $this->userCounts = $userCounts;

    return $userCounts;

  }

  /** set a user role
   *
   * @param int $user_id
   * @param string $newRole
   * @param array $oldRoles
   *
   * @return void
   */
  public function set_user_role( $user_id, $newRole, $oldRoles ) {
    $userCounts = $this->getUserCounts();
    $this->updateUserCounts( $userCounts, $newRole, + 1 );
    foreach ( $oldRoles as $role ) {
      $this->updateUserCounts( $userCounts, $role, - 1 );
    }
    $this->setUserCounts( $userCounts );
  }

  private function updateUserCounts( &$userCounts, $role, $value ) {
    if ( is_array( $userCounts['avail_roles'] ) ) {
      if ( ! array_key_exists( $role, $userCounts['avail_roles'] ) ) {
        $userCounts['avail_roles'][ $role ] = 0;
      }
      $userCounts['avail_roles'][ $role ] += $value;
      if ( $userCounts['avail_roles'][ $role ] === 0 ) {
        unset ( $userCounts['avail_roles'][ $role ] );
      }

    }
  }

  private function setUserCounts( $userCounts ) {
    $transientName = $this->plugin_name . "_user_counts";
    set_transient( $transientName, $userCounts, $this->timeToLive );
    $this->userCounts = $userCounts;
  }

  /** count users.
   *
   * @param $result
   * @param $strategy
   * @param $site_id
   *
   * @return array
   */
  public function count_users_by_role( $result, $strategy, $site_id ) {
    return $this->getUserCounts();
  }

  /** filter the data going into the list of views -- the line with the user counts and links.
   *
   * @param array list of views
   *
   * @return array list of views, changed if necessary
   */
  public function filter_view_list( $result ) {
    return $result;
  }
}
