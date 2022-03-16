<?php

/**  */

namespace OllieJones\index_wp_users_for_speed;

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/wordpress-hooks.php';

use WP_REST_Request;
use WP_REST_Response;
use WP_User;
use WP_User_Query;

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/indexer.php';

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
  /** List of author IDs.
   * @var array
   */
  public $authorIdKludge = [];
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
  private $recursionLevelBySite = [];
  private $message;

  /**
   * Initialize the class and set its properties.
   *
   * @since    1.0.0
   */
  public function __construct() {

    $this->plugin_name    = INDEX_WP_USERS_FOR_SPEED_NAME;
    $this->version        = INDEX_WP_USERS_FOR_SPEED_VERSION;
    $this->indexer        = Indexer::getInstance();
    $this->authorIdKludge = range( 0, 20 );  //TODO get this right.
    $this->pluginPath     = plugin_dir_path( dirname( __FILE__ ) );
    /* after a POST, we get a redirect with ?st=message */
    $this->message = isset( $_REQUEST['st'] ) ? sanitize_key( $_REQUEST['st'] ) : null;

    self::$messages = [
      'started'   => __( 'User Indexing Started', 'index-wp-users-for-speed' ),
      'removed'   => __( 'User Indexing Removed', 'index-wp-users-for-speed' ),
      /* translators: 1: fraction complete on index */
      'progress'  => __( 'User Indexing %1$s Complete', 'index-wp-users-for-speed' ),
      'completed' => __( 'User Indexing Complete', 'index-wp-users-for-speed' ),
      /* translators: 1: message id, like 'started' or 'removed' This is a warning */
      'default'   => __( 'Unknown message id %1$s', 'index-wp-users-for-speed' ),
    ];

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
    include_once $this->pluginPath . 'admin/views/page.php';
  }

  /** untrusted post action
   * @return void
   */
  public function action__post_action_unverified() {
    $valid = check_admin_referer( $this->plugin_name, 'reindex' );
    if ( $valid === 1 ) {
      if ( current_user_can( 'update_options' ) ) {
        $message = apply_filters( $this->plugin_name . '-post-filter', $_REQUEST, 'default' );
        wp_safe_redirect( add_query_arg( 'st', $message, wp_get_referer() ) );

        return;
      }
    }
    status_header( 403 );
  }

  /** Form post filter, after verification.
   *
   * @param array $params
   *
   * @return void
   */
  public function filter__post_filter( $params, $message ) {
    $q = $params;

    return $message;
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

  /**
   * Fires immediately after a user is added to a site.
   *
   * @param int $user_id User ID.
   * @param string $role User role.
   * @param int $blog_id Blog ID.
   *
   * @noinspection PhpUnused
   * @since MU (3.0.0)
   *
   */
  public function action__add_user_to_blog( $user_id, $role, $blog_id ) {
    $this->indexer->getUserCounts();
    $this->indexer->updateUserCounts( $role, + 1 );
    $this->indexer->setUserCounts();
    $this->indexer->updateEditors( $user_id );
  }

  /**
   * Fires before a user is removed from a site.
   *
   * @param int $user_id ID of the user being removed.
   * @param int $blog_id ID of the blog the user is being removed from.
   * @param int $reassign ID of the user to whom to reassign posts.
   *
   * @noinspection PhpUnused
   *
   * @since 5.4.0 Added the `$reassign` parameter.
   *
   * @since MU (3.0.0)
   */
  public function action__remove_user_from_blog( $user_id, $blog_id, $reassign ) {
    $user  = get_userdata( $user_id );
    $roles = $user->roles;
    $this->indexer->getUserCounts();
    $this->indexer->updateUserCounts( $roles, - 1 );
    $this->indexer->setUserCounts();
    $this->indexer->updateEditors( $user_id, true );
  }

  /**
   * Fires after the user's role has changed.
   *
   * @param int $user_id The user ID.
   * @param string $newRole The new role.
   * @param string[] $oldRoles An array of the user's previous roles.
   *
   * @noinspection PhpUnused
   * @since 3.6.0 Added $old_roles to include an array of the user's previous roles.
   *
   * @since 2.9.0
   */
  public function action__set_user_role( $user_id, $newRole, $oldRoles ) {

    $this->indexer->updateUserCountsForRoleChange( $newRole, $oldRoles );
    $this->indexer->updateEditors ($user_id);
  }

  /**
   * Filters the user count before queries are run.
   *
   * Return a non-null value to cause count_users() to return early.
   *
   * @param null|string $result The value to return instead. Default null to continue with the query.
   * @param string $strategy Optional. The computational strategy to use when counting the users.
   *                              Accepts either 'time' or 'memory'. Default 'time'. (ignored)
   * @param int|null $site_id Optional. The site ID to count users for. Defaults to the current site.
   *
   * @noinspection PhpUnused
   * @since 5.1.0
   *
   */
  public function filter__pre_count_users( $result, $strategy, $site_id ) {
    /* cron jobs use this; don't intervene with this filter there. */
    if ( wp_doing_cron() ) {
      return $result;
    }
    /* this bad boy gets called recursively, the way we cache user counts. */
    if ( ! array_key_exists( $site_id, $this->recursionLevelBySite ) ) {
      $this->recursionLevelBySite[ $site_id ] = 0;
    }
    if ( $this->recursionLevelBySite[ $site_id ] > 0 ) {
      return $result;
    }
    $previousId = get_current_blog_id();
    switch_to_blog( $site_id );

    $this->recursionLevelBySite[ $site_id ] ++;
    $output = $this->indexer->getUserCounts();
    $this->recursionLevelBySite[ $site_id ] --;

    switch_to_blog( $previousId );

    return $output;
  }

  /**
   * Filters the query arguments for the list of users in the dropdown (classic editor, quick edit)
   *
   * @param array $query_args The query arguments for get_users().
   * @param array $parsed_args The arguments passed to wp_dropdown_users() combined with the defaults.
   *
   * @returns array Updated $query_args
   * @since 4.4.0
   *
   * @noinspection PhpUnused
   */
  public function filter__wp_dropdown_users_args( $query_args, $parsed_args ) {

    if (! is_array($parsed_args['include'])
        && ! (isset($parsed_args['capability']) &&  $parsed_args['capability'] === 'edit_users')) {
      $editors = $this->indexer->getEditors();
      if ( is_array( $editors ) ) {
        $query_args['include'] = $editors;
      }
    }
    return $query_args;

  }

  /**
   * Fires before the WP_User_Query has been parsed.
   *
   * The passed WP_User_Query object contains the query variables,
   * not yet passed into SQL.
   *
   * @param WP_User_Query $query Current instance of WP_User_Query (passed by reference).
   *
   * @noinspection PhpUnused*
   * @since 4.0.0
   *
   */
  public function action__pre_get_users( $query ) {
    $a = $query;
  }

  /**
   * Filters the arguments used to generate the Quick Edit authors drop-down.
   *
   * @param array $users_opt An array of arguments passed to wp_dropdown_users().
   * @param bool $bulk A flag to denote if it's a bulk action.
   *
   * @since 5.6.0
   *
   * @see wp_dropdown_users()
   *
   * @noinspection PhpUnused
   */
  public function filter__quick_edit_dropdown_authors_args( $users_opt, $bulk ) {
    $o = $users_opt;

    return $users_opt;
  }

  /**
   * Fires after the WP_User_Query has been parsed, and before
   * the query is executed.
   *
   * @param WP_User_Query $query Current instance of WP_User_Query (passed by reference).
   *
   * @since 3.1.0
   *
   * @see WP_User_Query
   *
   * The passed WP_User_Query object contains SQL parts formed
   * from parsing the given query.
   *
   * @noinspection PhpUnused
   */
  public function action__pre_user_query( $query ) {
    /* Here we have $query->query_fields, query_from, query_where, query_orderby, query_limit
     *  and serveral other members of the WP_User_Query object.
     * We can alter them as needed to change the query before it runs */
    $q = $query;
  }

  /**
   * Filters WP_User_Query arguments when querying users via the REST API. (Gutenberg author-selection box)
   *
   * @link https://developer.wordpress.org/reference/classes/wp_user_query/
   *
   * @since 4.7.0
   *
   * @param array $query_args Array of arguments for WP_User_Query.
   * @param WP_REST_Request $request The REST API request.
   *
   * @noinspection PhpUnused
   */
  public function filter__rest_user_query( $query_args, $request ) {
    if (! is_array($query_args['include'])
        && ! (isset($query_args['capability']) &&  $query_args['capability'] === 'edit_users')) {
      $editors = $this->indexer->getEditors();
      if ( is_array( $editors ) ) {
        $query_args['include'] = $editors;
      }
    }
    return $query_args;
  }

  /**
   * Fires immediately after a user is created or updated via the REST API.
   *
   * @param WP_User $user Inserted or updated user object.
   * @param WP_REST_Request $request Request object.
   * @param bool $creating True when creating a user, false when updating.
   *
   * @noinspection PhpUnused
   * @noinspection PhpUnusedParameterInspection
   * @since 4.7.0
   *
   */
  public function action__rest_insert_user( $user, $request, $creating ) {
    // TODO
    $a = $user;
  }


  /**
   * Fires after a user is completely created or updated via the REST API.
   *
   * @param WP_User $user Inserted or updated user object.
   * @param WP_REST_Request $request Request object.
   * @param bool $creating True when creating a user, false when updating.
   *
   * @noinspection PhpUnused
   * @since 5.0.0
   *
   */
  public function action__rest_after_insert_user( $user, $request, $creating ) {
    $a = $user;
  }

  /**
   * Fires immediately after a user is deleted via the REST API.
   *
   * @param WP_User $user The user data.
   * @param WP_REST_Response $response The response returned from the API.
   * @param WP_REST_Request $request The request sent to the API.
   *
   * @noinspection PhpUnused
   * @since 4.7.0
   *
   */
  public function action__rest_delete_user( $user, $response, $request ) {
    //TODO
    $a = $user;
  }

  /**
   * Filters the users array before the query takes place.
   *
   * Return a non-null value to bypass WordPress' default user queries.
   *
   * Filtering functions that require pagination information are encouraged to set
   * the `total_users` property of the WP_User_Query object, passed to the filter
   * by reference. If WP_User_Query does not perform a database query, it will not
   * have enough information to generate these values itself.
   *
   * @param array|null $results Return an array of user data to short-circuit WP's user query
   *                               or null to allow WP to run its normal queries.
   * @param WP_User_Query $query The WP_User_Query instance (passed by reference).
   *
   * @since 5.1.0
   *
   * @noinspection PhpUnused
   */
  public function filter__users_pre_query( $results, $query ) {
    if (wp_doing_cron()) {
      return $results;
    }
    return $results;  /* unmodified, this is null */
  }

  /**
   * Filters SELECT FOUND_ROWS() query for the current WP_User_Query instance.
   *
   * @param string $sql The SELECT FOUND_ROWS() query for the current WP_User_Query.
   * @param WP_User_Query $query The current WP_User_Query instance.
   *
   * @since 3.2.0
   * @since 5.1.0 Added the `$this` parameter.
   *
   * @noinspection PhpUnused
   */
  public function filter__found_users_query( $sql, $query ) {
    if (wp_doing_cron()) {
      return $sql;
    }

    return $sql;
  }

  protected function getMessage() {
    if ( $this->message ) {
      $message = array_key_exists( $this->message, self::$messages ) ? $this->message : 'default';

      return sprintf( self::$messages[ $message ], $this->message );
    }

    return false;
  }


}

new Admin();