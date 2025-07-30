<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace IndexWpUsersForSpeed;

use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_User_Query;

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/indexer.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/selection-box.php';

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
  private $selectionBoxCache;
  private $doAutocomplete;
  private $requestedCapabilities;
  private $savedOrderby;
  private $doingRestQuery;

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
   * Here we declare that the site is not large.
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
   * Fires immediately before updating user metadata.
   *
   * We use this to watch for changes in the wp_capabilities metadata.
   * (It's named wp_2_capabilities etc in multisite).
   *
   * @param int $meta_id ID of the metadata entry to update.
   * @param int $user_id ID of the object metadata is for.
   * @param string $meta_key Metadata key.
   * @param mixed $meta_value Metadata value, not serialized.
   *
   * @return void
   * @since 2.9.0
   *
   */
  public function action__update_user_meta( $meta_id, $user_id, $meta_key, $meta_value ) {
    if ( ! $this->isCapabilitiesKey( $meta_key ) ) {
      return;
    }
    $newRoles = $this->sanitizeCapabilitiesOption( $meta_value );
    $oldRoles = $this->getCurrentUserRoles( $user_id, $meta_key );
    $this->userRoleChange( $user_id, $newRoles, $oldRoles );
  }

  /** Returns the capabilities meta key, or false if it's not the capabilities key.
   *
   * @param $meta_key
   *
   * @return false|string
   */
  private function isCapabilitiesKey( $meta_key ) {
    global $wpdb;
    return $meta_key === $wpdb->prefix . 'capabilities';
  }

  /** Make sure wp_capabilities option values don't contain unexpected junk.
   *
   * @param array $option Option value, from dbms.
   *
   * @return array Option value, cleaned up.
   */
  private function sanitizeCapabilitiesOption( $option ) {
    if ( ! is_array( $option ) ) {
      return [];
    }
    /* each array element must be 'string' => true in a valid option */
    return array_filter( $option, function ( $value, $key ) {
      return is_string( $key ) && $value === true;
    }, ARRAY_FILTER_USE_BOTH );
  }

  private function getCurrentUserRoles( $user_id, $meta_key = null ) {
    global $wpdb;
    if ( ! $meta_key ) {
      $meta_key = $wpdb->prefix . 'capabilities';
    }
    $roles = $this->sanitizeCapabilitiesOption( get_user_meta( $user_id, $meta_key, true ) );

    $metas  = get_user_meta( $user_id, '', false );
    $prefix = $wpdb->prefix . INDEX_WP_USERS_FOR_SPEED_KEY_PREFIX . 'r:';
    foreach ( $metas as $key => $value ) {
      if ( strpos( $key, $prefix ) === 0 ) {
        $role            = explode( ':', $key )[1];
        $roles [ $role ] = true;
      }
    }
    return $roles;
  }

  /**
   * @param int $user_id
   * @param array $newRoles
   * @param array $oldRoles
   *
   * @return void
   */
  private function userRoleChange( $user_id, $newRoles, $oldRoles ) {

    if ( $newRoles !== $oldRoles ) {
      $toAdd    = array_diff_key( $newRoles, $oldRoles );
      $toRemove = array_diff_key( $oldRoles, $newRoles );

      foreach ( array_keys( $toRemove ) as $role ) {
        $this->indexer->updateUserCounts( $role, - 1 );
        $this->indexer->updateEditors( $user_id, true );
        $this->indexer->removeIndexRole( $user_id, $role );
      }
      foreach ( array_keys( $toAdd ) as $role ) {
        $this->indexer->updateUserCounts( $role, + 1 );
        $this->indexer->updateEditors( $user_id, false );
        $this->indexer->addIndexRole( $user_id, $role );
      }
    }
  }

  /**
   * Fires immediately before user meta is added.
   *
   * We use this to watch a new wp_capabilities metadata item, meaning
   * a new user is added, overall or to a particular multisite blog.
   * It's named wp_2_capabilities etc in multisite.
   *
   * @param int $user_id ID of the object metadata is for.
   * @param string $meta_key Metadata key.
   * @param mixed $meta_value Metadata value.
   *
   * @since 3.1.0
   *
   */
  public function action__add_user_meta( $user_id, $meta_key, $meta_value ) {
    if ( ! $this->isCapabilitiesKey( $meta_key ) ) {
      return;
    }
    $newRoles = $this->sanitizeCapabilitiesOption( $meta_value );
    $this->indexer->updateUserCountsTotal( + 1 );
    $oldRoles = $this->getCurrentUserRoles( $user_id, $meta_key );
    $this->userRoleChange( $user_id, $newRoles, $oldRoles );
  }

  /**
   * Fires immediately before deleting user metadata.
   *
   * We use this to watch for deletion of the wp_capabilities metadata.
   * That means the user is being deleted.
   * It's named wp_2_capabilities etc in multisite.
   * This fires when a user is removed from a blog in a multisite setup.
   *
   * @param string[] $meta_ids An array of metadata entry IDs to delete.
   * @param int $user_id ID of the object metadata is for.
   * @param string $meta_key Metadata key.
   * @param mixed $meta_value Metadata value, not serialized.
   *
   * @since 3.1.0
   *
   */
  public function action__delete_user_meta( $meta_ids, $user_id, $meta_key, $meta_value ) {
    if ( ! $this->isCapabilitiesKey( $meta_key ) ) {
      return;
    }
    $oldRoles = $this->getCurrentUserRoles( $user_id, $meta_key );
    $this->indexer->updateUserCountsTotal( - 1 );
    $this->userRoleChange( $user_id, [], $oldRoles );
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
    if ( 'force_recount' === $strategy ) {
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
    /* Did some other plugin, maybe Co-Authors-Plus?, already give us a list of users ? */
    if ( is_array( $query_args ) && array_key_exists( 'include', $query_args ) && is_array( $query_args['include'] ) ) {
      /* Does this query call for include [0] ? */
      $include = $query_args['include'];
      if ( 1 === count( $include ) && array_key_exists( 0, $include ) && 0 === $include[0] ) {
        return $query_args;
      }
    }
    /* is this about posts or pages */
    if ( array_key_exists( 'capability', $query_args ) ) {
      $this->requestedCapabilities = $query_args['capability'];
    }
    /* Is our number of possible authors smaller than the threshold? */
    $threshold            = get_option( $this->options_name )['quickedit_threshold_limit'];
    $editors              = $this->indexer->getEditors();
    $this->doAutocomplete = true;
    if ( ! is_array( $editors ) || count( $editors ) <= $threshold ) {
      $this->doAutocomplete  = false;
      $query_args['include'] = $editors;
    } else if ( array_key_exists( 'include_selected', $parsed_args ) && $parsed_args['include_selected']
                && array_key_exists( 'name', $parsed_args ) && $parsed_args['name'] === 'post_author_override'
                && array_key_exists( 'selected', $parsed_args ) && is_numeric( $parsed_args['selected'] ) && $parsed_args['selected'] > 0 ) {
      /* Fetch just that single author, by ID, into the dropdown. The autocomplete code will then use it. */
      $query_args['include'] = [ $parsed_args['selected'] ];
      unset ( $query_args['capability'] );
      return $query_args;
    }
    $fixed_args = $this->filtered_query_args( $query_args, $parsed_args );
    /* This query is run twice, once for quickedit and again for bulkedit.
     * This suppresses most of the work in both runs.  */
    if ( $this->doAutocomplete ) {
      $fixed_args ['number'] = 1;
      $this->savedOrderby    = $fixed_args['orderby'];
      $fixed_args['orderby'] = 'ID';
    }
    return $fixed_args;
  }

  private function filtered_query_args( $query_args, $parsed_args ) {
    $capsFound = [];

    if ( array_key_exists( 'capability', $parsed_args ) || array_key_exists( 'capability__in', $parsed_args ) ) {
      /* deal with the possibility that we have either the capability or the capability__in arg */
      $cap     = array_key_exists( 'capability', $parsed_args ) ? $parsed_args['capability'] : [];
      $cap     = is_array( $cap ) ? $cap : [ $cap ];
      $caps    = array_key_exists( 'capability__in', $parsed_args ) ? $parsed_args['capability__in'] : [];
      $argsCap = array_unique( $cap + $caps );
      /* capabilites are edit_posts and/or edit_pages */
      foreach ( [ 'edit_posts', 'edit_pages' ] as $capToCheck ) {
        if ( in_array( $capToCheck, $argsCap ) ) {
          $capsFound [] = $capToCheck;
        }
      }
    } else if ( isset( $query_args['who'] ) && $query_args['who'] === 'authors' ) {
      /* Clean up the obsolete 'who' REST argument if it's there, thanks Gutenberg editor. */
      $capsFound = [ 'edit_posts', 'edit_pages' ];
      unset ( $query_args['who'] );
    } else {
      return $query_args;
    }
    /* count up the users if we can */
    $userCounts = $this->indexer->getUserCounts( false );
    $userCounts = is_array( $userCounts ) ? $userCounts : [];
    $roleCounts = array_key_exists( 'avail_roles', $userCounts ) ? $userCounts['avail_roles'] : [];
    if ( $this->indexer->isMetaIndexRoleAvailable() ) {
      /* the meta indexing is done. Use it. */
      /* Find the list of roles (administrator, contributor, etc.) with $capsFound capabilities */
      global $wp_roles;
      /* sometimes it isn't initialized in multisite. */
      $wp_roles = $wp_roles ?: new \WP_Roles();
      $wp_roles->for_site( get_current_blog_id() );
      $roleList = [];
      foreach ( $capsFound as $capFound ) {
        foreach ( $wp_roles->roles as $name => $role ) {
          $caps = &$role['capabilities'];
          if ( array_key_exists( $capFound, $caps ) && $caps[ $capFound ] === true ) {
            $userCount = array_key_exists( $name, $roleCounts ) ? $roleCounts[ $name ] : 0;
            if ( $userCount > 0 ) {
              $roleList[ $name ] = true;
            }
          }
        }
      }
      $metaQuery = [];
      foreach ( $roleList as $name => $_ ) {
        $metaQuery[]     = $this->makeRoleQueryArgs( $name );
        $userCount       = array_key_exists( $name, $roleCounts ) ? $roleCounts[ $name ] : 0;
        $this->userCount += $userCount;
      }
      if ( count( $metaQuery ) === 0 ) {
        return $query_args;
      }
      if ( count( $metaQuery ) > 1 ) {
        $metaQuery['relation'] = 'OR';
      }
      add_filter( 'get_meta_sql', [ $this, 'filter_meta_sql' ], 10, 6 );
      $query_args ['meta_query'] = $metaQuery;
      unset ( $query_args ['capability__in'] );
      unset ( $query_args['capability'] );
      $this->savedOrderby     = $query_args ['orderby'];
      $query_args ['orderby'] = 'ID';
    } else {
      /*  The meta indexing isn't yet done. Return partial list of editors. */
      $editors = $this->indexer->getEditors();
      if ( is_array( $editors ) ) {
        $query_args['include'] = $editors;
      }
    }
    return $query_args;
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
    if ( ! is_multisite() && ( ! array_key_exists( 'relation', $queries ) || $queries['relation'] !== 'OR' ) ) {
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
      $capabilityTags = array_map( function ( $key ) {
        global $wpdb;
        return $wpdb->prepare( "%s", $key );
      }, $keys );

      $where = PHP_EOL . " AND $wpdb->users.ID IN ( SELECT user_id FROM $wpdb->usermeta WHERE meta_key IN (" . implode( ',', $capabilityTags ) . '))' . PHP_EOL;
      /* only do this once per invocation of user query with metadata */
      remove_filter( 'get_meta_sql', [ $this, 'filter_meta_sql' ], 10 );

      $sql['join']  = '';
      $sql['where'] = $where;
    }
    return $sql;
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
   */
  public function filter__users_pre_query( $results, $query ) {
    global $wpdb;
    /* Do we want a result set of just one value because we're autocompleting?
     * We can just fake it and shortcut the query entirely. */
    if ( $this->doAutocomplete ) {
      if ( array_key_exists( 'number', $query->query_vars ) && $query->query_vars['number'] === 1 ) {
        $splits = explode( ' ', $query->query_fields );
        if ( count( $splits ) > 1 && strtoupper( $splits[0] ) === 'DISTINCT' ) {
          array_shift( $splits );
          $fields  = explode( ',', implode( ' ', $splits ) );
          $fakeRow = [];
          foreach ( $fields as $field ) {
            $components = explode( '.', $field );
            if ( count( $components ) > 1 && $components[0] === $wpdb->users ) {
              array_shift( $components );
              $field = implode( '.', $components );
            }
            $fakeRow[ $field ] = '-1';
          }
          $query->query_fields = $fields;
          return [ (object) $fakeRow ];
        }
      }
    }

    /* Are we doing a REST query? If so we can get rid of the ordering to speed things up. */
    if ( $this->doingRestQuery ) {
      $query->query_orderby = '';
      $this->doingRestQuery = false;
    }
    return $results;
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
  private function mungCountTotal( $query ) {
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
  private function getRoleFilterSets( array $qv ) {
    /* make a set of roles to include */
    $roleSet = [];
    if ( isset( $qv['role'] ) && $qv['role'] !== '' ) {
      $roleSet [] = $qv['role'];
    }

    if ( isset( $qv['role__in'] ) ) {
      $list    = is_array( $qv['role__in'] ) ? $qv['role__in'] : explode( ',', $qv['role__in'] );
      $roleSet = array_merge( $roleSet, $list );
    }
    $roleSet = array_unique( $roleSet );
    /* make a set of roles to exclude */
    $roleExclude = [];
    if ( isset( $qv['role__not_in'] ) ) {
      $list        = is_array( $qv['role__not_in'] ) ? $qv['role__not_in'] : explode( ',', $qv['role__not_in'] );
      $roleExclude = array_merge( $roleExclude, $list );
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
  private function mungRoleFilters( $query ) {
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
    if ( isset( $qv['meta_query'] ) && $meta ) {
      $new               = array();
      $new ['relation']  = 'AND';
      $new []            = $qv['meta_query'];
      $new []            = $meta;
      $qv ['meta_query'] = $new;
    } else {
      $qv ['meta_query'] = $meta;
    }
    /* and erase the role filters they replace */
    $qv['role']         = '';
    $qv['role__in']     = [];
    $qv['role__not_in'] = [];
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
    $threshold = get_option( $this->options_name )['quickedit_threshold_limit'];
    $editors   = $this->indexer->getEditors();
    if ( is_array( $editors ) && count( $editors ) <= $threshold ) {
      $query_args['include'] = $editors;
    }
    $this->doingRestQuery = true;
    return $this->filtered_query_args( $query_args, $query_args );
  }

  /**
   * Filters the response immediately after executing any REST API
   * callbacks.
   *
   * Allows plugins to perform any needed cleanup, for example,
   * to undo changes made during the {@see 'rest_request_before_callbacks'}
   * filter.
   *
   * Note that this filter will not be called for requests that
   * fail to authenticate or match to a registered route.
   *
   * Note that an endpoint's `permission_callback` can still be
   * called after this filter - see `rest_send_allow_header()`.
   *
   * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
   *                                                                   Usually a WP_REST_Response or WP_Error.
   * @param array $handler Route handler used for the request.
   * @param WP_REST_Request $request Request used to generate the response.
   *
   * @since 4.7.0
   *
   */
  public function filter__rest_request_after_callbacks( $response, $handler, $request ) {

    /* Is this a REST operation to fetch a list of users?
     * If so, we may have messed around with the orderby parameter
     * of the related query to make the DBMS do less work.
     * In that case, fix the order of the result set. */
    $params = $request->get_params();
    if ( $this->savedOrderby === 'display_name'
         && $request->get_route() === '/wp/v2/users'
         && $request->get_method() === 'GET'
         && is_array( $params )
         && array_key_exists( '_fields', $params )
         && false !== strpos( $params['_fields'], 'name' ) ) {
      usort( $response->data, function ( $a, $b ) {
        return strnatcasecmp( $a['name'], $b['name'] );
      } );
    }
    $this->savedOrderby   = null;
    $this->doingRestQuery = false;
    return $response;
  }

  /**
   * Filters the wp_dropdown_users() HTML output.
   *
   * @param string $html HTML output generated by wp_dropdown_users().
   *
   * @returns string HTML to use.
   * @since 2.3.0
   *
   */
  public function filter__wp_dropdown_users( $html ) {
    if ( ! $this->doAutocomplete ) {
      return $html;
    }

    wp_enqueue_script( 'jquery-ui-autocomplete' );
    wp_enqueue_script( 'iufs-ui-autocomplete',
      plugins_url( 'js/quick-edit-autocomplete.js', __FILE__ ),
      [ 'jquery-ui-autocomplete' ], INDEX_WP_USERS_FOR_SPEED_VERSION );
    wp_enqueue_style( 'iufs-ui-autocomplete-style',
      plugins_url( 'css/quick-edit-autocomplete.css', __FILE__ ),
      [], INDEX_WP_USERS_FOR_SPEED_VERSION );

    $selectionBox = new SelectionBox( $html, get_option( $this->options_name ) );
    $selectionBox->addClass( 'index-wp-users-for-speed' );

    if ( $this->selectionBoxCache ) {
      /* Already ran a version of this, look for the No Change entry and put it into the cached SelectionBox */
      if ( count( $selectionBox->users ) > 0
           && $selectionBox->users[0]->id === - 1
           && ( count( $this->selectionBoxCache->users ) === 0 || $this->selectionBoxCache->users[0]->id !== - 1 ) ) {
        $this->selectionBoxCache->prepend( $selectionBox->users[0] );
      }
    } else {
      $this->selectionBoxCache = $selectionBox;
    }
    $autocompleteHtml = $this->selectionBoxCache->generateAutocomplete( $this->requestedCapabilities, false );
    $selectHtml       = $this->selectionBoxCache->generateSelect( false );

    $this->savedOrderby = null;
    return $selectHtml . PHP_EOL . $autocompleteHtml;
  }
}

new UserHandler();
