<?php

namespace IndexWpUsersForSpeed;

use WP_REST_Request;
use WP_REST_Response;
use WP_User;
use WP_User_Query;

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/indexer.php';

/**
 * The admin-specific hooks for handling users
 *
 * @link       https://github.com/OllieJones
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/user-handler
 * @author     Ollie Jones <oj@plumislandmedia.net>
 */
class UserHandler extends WordPressHooks {

  private $plugin_name;
  private $version;
  private $indexer;
  private $pluginPath;
  private $recursionLevelBySite = [];
  private $userCount = 0;
  private $options_name;

  public function __construct() {

    $this->plugin_name  = INDEX_WP_USERS_FOR_SPEED_NAME;
    $this->version      = INDEX_WP_USERS_FOR_SPEED_VERSION;
    $this->indexer      = Indexer::getInstance();
    $this->pluginPath   = plugin_dir_path( dirname( __FILE__ ) );
    $this->options_name = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'options';

    parent::__construct();
  }

  /**
   * Filters whether the site is considered large, based on its number of users.
   *
   * Here we declare that it's not large.
   *
   * @param bool $is_large_user_count Whether the site has a large number of users.
   * @param int $count The total number of users.
   * @param int|null $network_id ID of the network. `null` represents the current network.
   *
   * @noinspection PhpUnused
   *
   * @since 6.0.0
   *
   */
  public function filter__wp_is_large_user_count( $is_large_user_count, $count, $network_id ) {
    return false;
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
    $this->indexer->updateUserCounts( $role, + 1 );
    $this->indexer->updateEditors( $user_id );
    $this->indexer->updateIndexRole( $user_id, $role, $blog_id );
  }

  /**
   * Fires before a user is removed from a site in multisite
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
    $this->indexer->updateUserCounts( $roles, - 1 );
    $this->indexer->updateEditors( $user_id, true );
    $this->indexer->removeIndexRole( $user_id, $blog_id );
  }

  /**
   * Fires immediately after a user is deleted from the database in single-site
   *
   * The deleted user's wp_usermeta data is already gone by this action.
   *
   * @param int $id ID of the deleted user.
   * @param int|null $reassign ID of the user to reassign posts and links to.
   *                           Default null, for no reassignment.
   * @param WP_User $user WP_User object of the deleted user.
   *
   * @noinspection PhpUnused
   *
   * @since 5.5.0 Added the `$user` parameter.
   * @since 2.9.0
   */
  public function action__deleted_user( $id, $reassign, $user ) {
    $roles = $user->roles;
    $this->indexer->updateUserCounts( $roles, - 1 );
    $this->indexer->updateEditors( $id, true );
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
    $this->indexer->updateEditors( $user_id );
    $this->indexer->updateIndexRole( $user_id, $newRole, get_current_blog_id() );
  }

  /**
   * Filters the user count before queries are run.
   *
   * Return a non-null value to cause count_users() to return early.
   * We may have pre-accumulated the user counts. If so we can
   * skip the expensive query to do that again.
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

    if ( is_multisite() ) {
      switch_to_blog( $site_id );
    }
    $this->recursionLevelBySite[ $site_id ] ++;
    $output = $this->indexer->getUserCounts();
    $this->recursionLevelBySite[ $site_id ] --;

    if ( is_multisite() ) {
      restore_current_blog();
    }

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
    $options    = get_option( $this->options_name );
    $fixed_args = $this->filtered_query_args( $query_args, $parsed_args );

    if ( isset ( $options ['quickedit_threshold_on'] ) && 'on' === $options ['quickedit_threshold_on'] ) {
      $fixed_args ['number'] = $options ['quickedit_threshold_limit'];
    }
    if ( isset ( $options ['quickedit_mostposts_on'] ) && 'on' === $options ['quickedit_mostposts_on'] ) {
      unset ( $fixed_args['order'] );
      $fixed_args ['orderby'] = [ 'post_count' => 'DESC', 'display_name' => 'ASC' ];
    }
    return $fixed_args;
  }

  private function filtered_query_args( $query_args, $parsed_args ) {
    $capFound = null;

    /* if the query is already restricted with an "include" item, do nothing */
    if ( is_array( $query_args['include'] ) && count( $query_args['include'] ) > 0 ) {
      return $query_args;
    }
    /* Notice that the JSON ajax requests for lists of users have the deprecated who=authors
     * query syntax. This code allows both that and the new capability=[edit_posts] syntax */
    if ( isset( $query_args['who'] ) && $query_args['who'] === 'authors' ) {
      $capFound = 'edit_posts';
      unset ( $query_args['who'] );
    } else if ( isset( $parsed_args['capability'] ) ) {
      $argsCap = is_array( $parsed_args['capability'] ) ? $parsed_args['capability'] : [ $parsed_args['capability'] ];
      /* capability item is set to either edit_posts or edit_pages */
      foreach ( [ 'edit_posts', 'edit_pages' ] as $capToCheck ) {
        if ( in_array( $capToCheck, $argsCap ) ) {
          $capFound = $capToCheck;
        }
      }
    } else {
      return $query_args;
    }
    /* get the pre-stored list of editors */
    $editors = $this->indexer->getEditors();
    /* count up the users if we can */
    $userCounts = $this->indexer->getUserCounts( false );
    $roleCounts = array_key_exists( 'avail_roles', $userCounts ) ? $userCounts['avail_roles'] : [];
    if ( $this->indexer->isMetaIndexRoleAvailable() &&
         is_array( $editors ) &&
         count( $editors ) >= INDEX_WP_USERS_FOR_SPEED_USER_COUNT_LIMIT ) {
      /* We have many registered editors and the meta indexing is done. Use it. */
      /* Find the list of roles (administrator, contributor, etc.) with the $capFound capability */
      global $wp_roles;
      $wp_roles->for_site( get_current_blog_id() );
      $metaQuery = [];
      foreach ( $wp_roles->roles as $name => $role ) {
        $caps = &$role['capabilities'];
        if ( array_key_exists( $capFound, $caps ) && $caps[ $capFound ] === true ) {
          $userCount = array_key_exists( $name, $roleCounts ) ? $roleCounts[ $name ] : 0;
          if ( $userCount > 0 ) {
            $metaQuery[]     = $this->makeRoleQueryArgs( $name );
            $this->userCount += $userCount;
          }
        }
      }
      if ( count( $metaQuery ) === 0 ) {
        return $query_args;
      }
      if ( count( $metaQuery ) > 1 ) {
        $metaQuery['relation'] = 'OR';
      }
      add_filter( 'get_meta_sql', [ $this, 'filter_meta_sql' ], 10, 6 );
      $query_args ['meta_query'] = $metaQuery;
      unset ( $query_args['capability'] );
    } else {
      $editors = $this->indexer->getEditors();
      if ( is_array( $editors ) ) {
        $query_args['include'] = $editors;
      }
    }
    return $query_args;
  }

  /** Traverse a nested WP_Query_Meta query array flattening the
   *  real terms in it.
   *
   * @param array $query
   *
   * @return array Flattened array.
   */
  private function parseQuery( $query ) {
    $result = [];

    foreach ( $query as $k => $q ) {
      if ( $k !== 'relation' ) {
        if ( array_key_exists( 'key', $q ) && array_key_exists( 'compare', $q ) ) {
          $result [] = $q;
        } else {
          $result = array_merge( $result, $this->parseQuery( $q ) );
        }
      }
    }
    return $result;
  }

  /** Flatten out the meta query terms we get from a multisite setup,
   *   allowing their interpretation as if from a single site.
   *  This is kludgey because it removes AND exists(wp_capabilities key).
   *
   * @param array $query
   * @param string $keyToRemove Something like wp_2_capapabilities or wp_capabilities
   *
   * @return string[]
   */
  private function flattenQuery( $query, $keyToRemove ) {
    $r = $this->parseQuery( $query );
    $q = [ 'relation' => 'OR' ];
    foreach ( $r as $item ) {
      if ( $item['key'] !== $keyToRemove ) {
        $q [] = $item;
      }
    }
    return $q;
  }

  /**
   * Filters the meta query's generated SQL.  'get_meta_sql'
   * We must intervene in query generation here due to a defect in WP Core's
   * generation of postmeta key 'a' EXISTS OR key 'b' EXISTS OR key 'c' EXISTS ...
   *
   * @param string[] $sql Array containing the query's JOIN and WHERE clauses.
   * @param array $queries Array of meta queries.
   * @param string $type Type of meta. Possible values include but are not limited
   *                                    to 'post', 'comment', 'blog', 'term', and 'user'.
   * @param string $primary_table Primary table.
   * @param string $primary_id_column Primary column ID.
   * @param object $context The main query object that corresponds to the type, for
   *                                    example a `WP_Query`, `WP_User_Query`, or `WP_Site_Query`.
   *
   * @since 3.1.0
   *
   */
  public function filter_meta_sql(
    $sql, $queries, $type, $primary_table, $primary_id_column, $context
  ) {
    global $wpdb;
    if ( $type !== 'user' ) {
      return $sql;
    }
    /* single meta query that doesn't look like one of ours. */
    if ( ! is_multisite() && $queries['relation'] !== 'OR' ) {
      return $sql;
    }

    if ( is_multisite() ) {
      /* fix up the meta query */
      $queries = $this->flattenQuery( $queries, $wpdb->prefix . 'capabilities' );
    }

    if ( $queries['relation'] === 'OR' ) {
      $keys = [];
      foreach ( $queries as $query ) {
        if ( is_array( $query ) ) {
          if ( ! array_key_exists( 'compare', $query ) || $query ['compare'] !== 'EXISTS' ) {
            return $sql;
          }
          if ( ! array_key_exists( 'key', $query ) || ! is_string( $query['key'] ) ) {
            return $sql;
          }
          $keys[] = $query['key'];
        }
      }
      $joins  = [];
      $wheres = [];
      $k      = 1;
      foreach ( $keys as $key ) {
        $join      = "wp_usermeta um$k ON $primary_table.$primary_id_column = um$k.user_id AND um$k.meta_key = %s";
        $join      = $wpdb->prepare( $join, $key );
        $joins[]   = $join;
        $wheres [] = "um$k.umeta_id IS NOT NULL";
        $k ++;
      }
      $join  = PHP_EOL . ' LEFT JOIN ' . implode( PHP_EOL . ' LEFT JOIN ', $joins );
      $where = PHP_EOL . ' AND (' . implode( PHP_EOL . ' OR ', $wheres ) . ')';
      /* only do this once per invocation of user query with metadata */
      remove_filter( 'get_meta_sql', [ $this, 'filter_meta_sql' ], 10 );
      return [ 'join' => $join, 'where' => $where ];
    } else {
      return $sql;
    }
  }

  /**
   * Fires before the WP_User_Query has been parsed.
   *
   * The passed WP_User_Query object contains the query variables,
   * not yet passed into SQL. We change the variables here,
   * if we have the data, to use the index roles and
   * count. That meanse the query doesn't need
   * meta_value LIKE '%someting%' or the
   *
   * @param WP_User_Query $query Current instance of WP_User_Query (passed by reference).
   *
   * @noinspection PhpUnused
   * @since 4.0.0
   *
   */
  public
  function action__pre_get_users(
    $query
  ) {

    /* the order of these is important: mungCountTotal won't work after mungRoleFilters */
    $this->mungCountTotal( $query );
    $this->mungRoleFilters( $query );
  }

  /**
   * Here we figure out whether we already know the total users, and
   * switch off 'count_total' if we do. That saves the performance-killing
   * and deprecated SELECT SQL_CALC_FOUND_ROWS modifier.
   *
   * @see https://dev.mysql.com/doc/refman/8.0/en/information-functions.html#function_found-rows
   *
   * Do this before mungRoleFilters.
   *
   * @param WP_User_Query $query Current instance of WP_User_Query (passed by reference).
   *
   */
  private
  function mungCountTotal(
    $query
  ) {
    /* we will bash $qv in place, so take it by ref */
    $qv = &$query->query_vars;
    if ( ! isset( $qv['count_total'] ) || $qv['count_total'] === false ) {
      /* not trying to count total, no intervention needed */
      return;
    }
    /* did we already count up the users ? */
    if ( $this->userCount > 0 ) {
      $qv['count_total']  = false;
      $query->total_users = $this->userCount;
      $this->userCount    = 0;
      return;
    }
    /* look for filters other than role filters, if present we can't intervene in user count */
    $filterList = [
      'meta_key',
      'meta_value',
      'meta_compare',
      'meta_compare_key',
      'meta_type',
      'meta_type_key',
      'meta_query',
      'capability',
      'capability__in',
      'capability__not_in',
      'include',
      'exclude',
      'search',
      'search_columns',
      'has_published_posts',
      'nicename',
      'nicename__in',
      'nicename__not_in',
      'login',
      'login__in',
      'login__not_in',
    ];
    foreach ( $filterList as $filter ) {
      if ( array_key_exists( $filter, $qv ) &&
           ( ( is_string( $qv[ $filter ] ) && strlen( $qv[ $filter ] ) > 0 ) ||
             ( is_array( $qv[ $filter ] ) && count( $qv[ $filter ] ) > 0 ) ) ) {
        return;
      }
    }

    /* we can't handle any complex role filtering. One included role only. */
    list( $roleSet, $roleExclude ) = $this->getRoleFilterSets( $qv );
    if ( count( $roleSet ) > 1 && count( $roleExclude ) > 0 ) {
      return;
    }

    /* OK, let's see if we have the counts */
    $task   = new  CountUsers();
    $counts = $task->getStatus();
    if ( ! $task->isAvailable( $counts ) ) {
      return;
    }
    $count = - 1;
    if ( count( $roleSet ) === 0 && isset( $counts['total_users'] ) ) {
      $count = $counts['total_users'];
    } else if ( is_array( $counts['avail_roles'] ) ) {
      $availRoles = $counts['avail_roles'];
      $role       = $roleSet[0];
      if ( isset( $availRoles[ $role ] ) ) {
        $count = $availRoles[ $role ];
      }
    }
    if ( $count >= 0 ) {
      $qv['count_total']  = false;
      $query->total_users = $count;
    }
  }

  /**
   * @param array $qv
   *
   * @return array
   */
  private
  function getRoleFilterSets(
    array $qv
  ) {
    /* make a set of roles to include */
    $roleSet = [];
    if ( isset( $qv['role'] ) && $qv['role'] !== '' ) {
      $roleSet [] = $qv['role'];
    }
    if ( isset( $qv['role__in'] ) ) {
      $roleSet = array_merge( $roleSet, $qv['role__in'] );
    }
    $roleSet = array_unique( $roleSet );
    /* make a set of roles to exclude */
    $roleExclude = [];
    if ( isset( $qv['role__not_in'] ) ) {
      $roleExclude = array_merge( $roleExclude, $qv['role__not_in'] );
    }
    $roleExclude = array_unique( $roleExclude );

    return [ $roleSet, $roleExclude ];
  }

  /**
   * Here we look at the query object to see whether it filters by role.
   * If it does, we add meta filters to filter by our
   * 'wp_index-wp-users-for-speed-role-ROLENAME' meta_key items
   * and remove the role filters. This gets us away from
   * the nasty and inefficient
   *     meta_value LIKE '%ROLENAME%'
   * query pattern and into a more sargable pattern.
   *
   * @param WP_User_Query $query Current instance of WP_User_Query (passed by reference).
   *
   */
  private
  function mungRoleFilters(
    $query
  ) {
    /* we will bash $qv in place, so take it by ref */
    $qv = &$query->query_vars;
    list( $roleSet, $roleExclude ) = $this->getRoleFilterSets( $qv );

    /* if the present query doesn't filter by any roles, don't do anything extra */
    if ( count( $roleSet ) === 0 && count( $roleExclude ) === 0 ) {
      return;
    }

    $task = new PopulateMetaIndexRoles();
    if ( ! $task->isAvailable() ) {
      return;
    }

    /* assemble some meta query args per
     * https://developer.wordpress.org/reference/classes/wp_meta_query/__construct/
     */
    $includes = [];
    foreach ( $roleSet as $role ) {
      $includes[] = $this->makeRoleQueryArgs( $role );
    }
    if ( count( $includes ) > 1 ) {
      $includes = [ 'relation' => 'OR', $includes ];
    }
    $excludes = [];
    foreach ( $roleExclude as $role ) {
      $excludes[] = $this->makeRoleQueryArgs( $role, 'NOT EXISTS' );
    }
    if ( count( $excludes ) > 1 ) {
      $excludes = [ 'relation' => 'AND', $excludes ];
    }
    if ( count( $includes ) > 0 && count( $excludes ) > 0 ) {
      $meta = [ 'relation' => 'AND', $includes, $excludes ];
    } else if ( count( $includes ) > 0 ) {
      $meta = $includes;
    } else if ( count( $excludes ) > 0 ) {
      $meta = $excludes;
    } else {
      $meta = false;
    }
    /* stash those meta query args in the query variables we got */
    $qv ['meta_query'] = $meta;
    /* and erase the role filters they replace */
    $qv['role']         = '';
    $qv['role__in']     = [];
    $qv['role__not_in'] = [];
  }

  /** Create a meta arg for looking for an exsisting role tag
   *
   * @param string $role
   * @param string $compare 'NOT EXISTS' or 'EXISTS' (the default).
   *
   * @return array meta query arg array
   */
  private
  function makeRoleQueryArgs(
    $role, $compare = 'EXISTS'
  ) {
    global $wpdb;
    $roleMetaPrefix = $wpdb->prefix . INDEX_WP_USERS_FOR_SPEED_KEY_PREFIX . 'r:';
    $roleMetaKey    = $roleMetaPrefix . $role;

    return [ 'key' => $roleMetaKey, 'compare' => $compare ];
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
    return $this->filtered_query_args( $query_args, $query_args );
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
  public
  function action__rest_after_insert_user(
    $user, $request, $creating
  ) {
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
  public
  function action__rest_delete_user(
    $user, $response, $request
  ) {
    //TODO
    $a = $user;
  }
}

new UserHandler();