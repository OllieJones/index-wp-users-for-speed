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
   * @noinspection PhpIncludeInspection
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

    $admin = new Index_Wp_Users_For_Speed_Admin( $this->get_plugin_name(), $this->get_version() );

    /* Wake up when loading admin. */
    $this->loader->add_action_byname( 'admin_menu', $admin,1 );

    /* Handle styles and scripts for the admin page */
    $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
    $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );

    /* handle POSTs from the admin page form. They go to admin-post.php where these fire, then redirect to repaint */
    $this->loader->add_action_byname( 'admin_post_index-wp-users-for-speed-action', $admin,1 );
    $this->loader->add_filter_byname( $this->plugin_name . '-post-filter', $admin,2);

    /* all sorts of stuff to monitor users' coming and going */
    $this->loader->add_action_byname( 'set_user_role', $admin,3 );
    /* fires immediately before a user is deleted from the database */
    $this->loader->add_action_byname( 'delete_user', $admin,3 );
    /* fires immediately after a user is deleted from the database */
    $this->loader->add_action_byname( 'deleted_user', $admin, 3 );

    /* Fires immediately after a user is deleted via the REST API. */
    $this->loader->add_action_byname( 'rest_delete_user', $admin,3 );
    /* Fires immediately after a user is created or updated via the REST API. */
    $this->loader->add_action_byname( 'rest_insert_user', $admin,3 );
    /* Fires immediately after a user is completey created or updated via the REST API. */
    $this->loader->add_action_byname( 'rest_after_insert_user', $admin,3 );

    $this->loader->add_action_byname( 'add_user_to_blog', $admin,3 );
    $this->loader->add_action_byname( 'wpmu_delete_user', $admin,2 );
    $this->loader->add_action_byname( 'wpmu_activate_user', $admin,  3 );
    $this->loader->add_action_byname( 'added_existing_user', $admin,2 );
    $this->loader->add_action_byname( 'network_site_new_created_user', $admin,1 );
    $this->loader->add_action_byname( 'network_site_users_created_user', $admin,1 );

    /* Filters the query arguments used to retrieve users for the current users list table. */
    $this->loader->add_filter_byname( 'users_list_table_query_args', $admin,1 );
    /* Filters the user count before queries are run. */
    $this->loader->add_filter_byname( 'pre_count_users', $admin,  3, 1 );
    /* Filters the query arguments for the list of users in the dropdown (classic editor, quick edit) */
    $this->loader->add_filter_byname( 'wp_dropdown_users_args', $admin,2 );
    /* Filters the arguments used to generate the Quick Edit authors drop-down.  TODO how does this differ from wp_dropdown_users_args?  */
    $this->loader->add_filter_byname( 'quick_edit_dropdown_authors_args', $admin,2 );
    /* Filters WP_User_Query arguments when querying users via the REST API. (Gutenberg author-selection box) */
    $this->loader->add_filter_byname( 'rest_user_query', $admin,  2 );
    /* Fires after the WP_User_Query has been parsed, and before the query is executed. */
    $this->loader->add_filter_byname( 'pre_user_query', $admin,   2 );
    /* Filters the users array before the query takes place. */
    $this->loader->add_filter_byname( 'users_pre_query', $admin,2 );
    /* Filters SELECT FOUND_ROWS() query for the current WP_User_Query instance. */
    $this->loader->add_filter_byname( 'found_users_query', $admin,2 );
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

    //TODO add this back if we need it on the front end.
//    $plugin_public = new Index_Wp_Users_For_Speed_Public( $this->get_plugin_name(), $this->get_version() );
//
//    $this->loader->add_action_byname( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
//    $this->loader->add_action_byname( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

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
