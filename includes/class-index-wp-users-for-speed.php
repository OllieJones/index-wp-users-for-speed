<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/OllieJones
 * @since      1.0.0
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/includes
 * @author     Ollie Jones <oj@plumislandmedia.net>
 */
class Index_Wp_Users_For_Speed {

  /**
   * The loader that's responsible for maintaining and registering all hooks that power
   * the plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      Index_Wp_Users_For_Speed_Loader $loader Maintains and registers all hooks for the plugin.
   */
  protected $loader;

  /**
   * The unique identifier of this plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      string $plugin_name The string used to uniquely identify this plugin.
   */
  protected $plugin_name;

  /**
   * The current version of the plugin.
   *
   * @since    1.0.0
   * @access   protected
   * @var      string $version The current version of the plugin.
   */
  protected $version;

  /**
   * Define the core functionality of the plugin.
   *
   * Set the plugin name and the plugin version that can be used throughout the plugin.
   * Load the dependencies, define the locale, and set the hooks for the admin area and
   * the public-facing side of the site.
   *
   * @since    1.0.0
   */
  public function __construct() {
    if ( defined( 'INDEX_WP_USERS_FOR_SPEED_VERSION' ) ) {
      $this->version = INDEX_WP_USERS_FOR_SPEED_VERSION;
    } else {
      $this->version = '1.0.0';
    }
    $this->plugin_name = 'index-wp-users-for-speed';

    $this->load_dependencies();
    $this->set_locale();
    $this->define_admin_hooks();
    $this->define_public_hooks();

  }

  /**
   * Load the required dependencies for this plugin.
   *
   * Include the following files that make up the plugin:
   *
   * - Index_Wp_Users_For_Speed_Loader. Orchestrates the hooks of the plugin.
   * - Index_Wp_Users_For_Speed_i18n. Defines internationalization functionality.
   * - Index_Wp_Users_For_Speed_Admin. Defines all hooks for the admin area.
   * - Index_Wp_Users_For_Speed_Public. Defines all hooks for the public side of the site.
   *
   * Create an instance of the loader which will be used to register the hooks
   * with WordPress.
   *
   * @since    1.0.0
   * @access   private
   */
  private function load_dependencies() {

    /**
     * The class responsible for orchestrating the actions and filters of the
     * core plugin.
     */
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-index-wp-users-for-speed-loader.php';

    /**
     * The class responsible for defining internationalization functionality
     * of the plugin.
     */
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-index-wp-users-for-speed-i18n.php';

    /**
     * The class responsible for defining all actions that occur in the admin area.
     */
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-index-wp-users-for-speed-admin.php';

    /**
     * The class responsible for defining all actions that occur in the public-facing
     * side of the site.
     */
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-index-wp-users-for-speed-public.php';

    $this->loader = new Index_Wp_Users_For_Speed_Loader();

  }

  /**
   * Define the locale for this plugin for internationalization.
   *
   * Uses the Index_Wp_Users_For_Speed_i18n class in order to set the domain and to register the hook
   * with WordPress.
   *
   * @since    1.0.0
   * @access   private
   */
  private function set_locale() {

    $plugin_i18n = new Index_Wp_Users_For_Speed_i18n();

    $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

  }

  /**
   * Register all the hooks related to the admin area functionality
   * of the plugin.
   *
   * @since    1.0.0
   * @access   private
   */
  private function define_admin_hooks() {

    $plugin_admin = new Index_Wp_Users_For_Speed_Admin( $this->get_plugin_name(), $this->get_version() );

    /* admin page stuff */
    $this->loader->add_action( 'admin_menu', $plugin_admin, 'admin_menu' );


    $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
    $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

    $this->loader->add_action( 'admin_post_index-wp-users-for-speed-action', $plugin_admin, 'post_action_unverified' );
    $this->loader->add_filter( $this->plugin_name . '-post-filter', $plugin_admin, 'post_filter', 10, 2);

    $this->loader->add_action( 'set_user_role', $plugin_admin, 'set_user_role', 10, 3 );
    $this->loader->add_action( 'delete_user', $plugin_admin, 'delete_user', 10, 3 );
    $this->loader->add_action( 'add_user_to_blog', $plugin_admin, 'add_user_to_blog', 10, 3 );

    $this->loader->add_action( 'wpmu_delete_user', $plugin_admin, 'wpmu_delete_user', 10, 2 );
    $this->loader->add_action( 'wpmu_activate_user', $plugin_admin, 'wpmu_activate_user', 10, 3 );
    $this->loader->add_action( 'added_existing_user', $plugin_admin, 'added_existing_user', 10, 2 );
    $this->loader->add_action( 'network_site_new_created_user', $plugin_admin, 'network_site_new_created_user', 10, 1 );
    $this->loader->add_action( 'network_site_users_created_user', $plugin_admin, 'network_site_users_created_user', 10, 1 );
    $this->loader->add_filter( 'users_list_table_query_args', $plugin_admin, 'users_list_table_query_args', 10, 1 );

    $this->loader->add_filter( 'pre_count_users', $plugin_admin, 'pre_count_users', 1, 3 );
    $this->loader->add_filter( 'wp_dropdown_users_args', $plugin_admin, 'wp_dropdown_users_args', 10, 2 );
    $this->loader->add_filter( 'quick_edit_dropdown_authors_args', $plugin_admin, 'quick_edit_dropdown_authors_args', 10, 2 );
    $this->loader->add_filter( 'rest_user_query', $plugin_admin, 'rest_user_query', 10, 2 );
    $this->loader->add_filter( 'pre_user_query', $plugin_admin, 'pre_user_query', 10, 2 );
    $this->loader->add_filter( 'users_pre_query', $plugin_admin, 'users_pre_query', 10, 2 );

  }

  /**
   * The name of the plugin used to uniquely identify it within the context of
   * WordPress and to define internationalization functionality.
   *
   * @return    string    The name of the plugin.
   * @since     1.0.0
   */
  public function get_plugin_name() {
    return $this->plugin_name;
  }

  /**
   * Retrieve the version number of the plugin.
   *
   * @return    string    The version number of the plugin.
   * @since     1.0.0
   */
  public function get_version() {
    return $this->version;
  }

  /**
   * Register all the hooks related to the public-facing functionality
   * of the plugin.
   *
   * @since    1.0.0
   * @access   private
   */
  private function define_public_hooks() {

    //TODO add this back if we need it.
//    $plugin_public = new Index_Wp_Users_For_Speed_Public( $this->get_plugin_name(), $this->get_version() );
//
//    $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
//    $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

  }

  /**
   * Run the loader to execute all the hooks with WordPress.
   *
   * @since    1.0.0
   */
  public function run() {
    $this->loader->run();
  }

  /**
   * The reference to the class that orchestrates the hooks with the plugin.
   *
   * @return    Index_Wp_Users_For_Speed_Loader    Orchestrates the hooks of the plugin.
   * @since     1.0.0
   */
  public function get_loader() {
    return $this->loader;
  }

}
