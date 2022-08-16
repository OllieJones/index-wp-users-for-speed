<?php /** @noinspection PhpIncludeInspection */

namespace IndexWpUsersForSpeed;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tasks/task.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tasks/count-users.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tasks/get-editors.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tasks/reindex.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tasks/populate-meta-index-roles.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tasks/depopulate-meta-indexes.php';

class Indexer {

  /* a simple singleton class */
  protected static $singleInstance;
  private static $sentinelCount;

  protected function __construct() {

    /* a magic count of users to indicate "don't know yet" */
    self::$sentinelCount = 1024 * 1024 * 1024 * 2 - 7;

  }

  /** Write out a log (to a transient).
   *
   * @param string $msg Log entry.
   * @param int $maxlength Optional. Maximum number of entries in log. Default 256.
   * @param string $name Optional. Optional. The suffix of the transient name. Default "log".
   *
   * @return void
   */
  public static function writeLog( $msg, $maxlength = 256, $name = 'log' ) {
    global $wpdb;
    $wpdb->query( "LOCK TABLES $wpdb->options WRITE" );
    $log      = get_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . $name );
    $log      = is_string( $log ) ? $log : null;
    $logarray = explode( PHP_EOL, $log );
    $logarray = array_slice( $logarray, 0, $maxlength );
    array_unshift( $logarray, date( 'Y-m-d H:i:s' ) . ' ' . $msg );
    $log = implode( PHP_EOL, $logarray );
    set_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . $name, $log, INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME );
    $wpdb->query( 'UNLOCK TABLES' );
  }

  public static function getInstance() {
    if ( ! isset( self::$singleInstance ) ) {
      self::$singleInstance = new self();
      self::$singleInstance->maybeIndexEverything();
    }

    return self::$singleInstance;
  }

  public function maybeIndexEverything( $force = false ) {
    $task       = new CountUsers();
    $userCounts = $task->getStatus();
    if ( $force || $task->needsRunning( $userCounts ) ) {
      $task->init();
      $task->maybeSchedule( $userCounts );
    }

    $task    = new GetEditors();
    $editors = $task->getStatus();
    if ( $force || $task->needsRunning( $editors ) ) {
      $task->init();
      $task->maybeSchedule( $editors );
    }

    $task      = new PopulateMetaIndexRoles();
    $populated = $task->getStatus();
    if ( $force || $task->needsRunning( $populated ) ) {
      $task->init();
      $task->maybeSchedule();
    }
  }

  public function cleanupNow() {
    $pop = new DepopulateMetaIndexes();
    $pop->init();
    /** @noinspection PhpStatementHasEmptyBodyInspection */
    while ( ! $pop->doChunk() ) {
    }
  }

  public function rebuildNow() {
    $this->maybeIndexEverything( true );
  }

  /**
   * @return int one higher than the maximum user ID, irrespective of site in multisite.
   */
  public function getMaxUserId() {
    global $wpdb;

    return 1 + max( 1, intval( $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->users" ) ) );
  }

  /** Remove all indexing.
   * @return void
   */
  public function removeNow() {
    $task = new DepopulateMetaIndexes();
    $task->init();
    /** @noinspection PhpStatementHasEmptyBodyInspection */
    while ( ! $task->doChunk() ) {
    }
    $task->clearStatus();
    $task = new CountUsers();
    $task->clearStatus();
    $task = new GetEditors();
    $task->clearStatus();
  }

  public function enableAutoRebuild( $seconds ) {
    $task = new Reindex ();
    $task->init();
    $task->cancel();
    $whenToRun = $this->nextDailyTimestamp( $seconds );
    $task->schedule( $whenToRun, 'daily' );
  }

  /** @noinspection PhpSameParameterValueInspection */
  private function nextDailyTimestamp( $secondsAfterMidnight, $buffer = 30 ) {
    $midnight = $this->getTodayMidnightTimestamp();
    $when     = $secondsAfterMidnight + $midnight;
    if ( $when + $buffer < time() ) {
      /* time already passed today, do it tomorrow */
      $when += DAY_IN_SECONDS;
    }

    return $when;
  }

  /** Get the UNIX timestamp for midnight today, in local time.
   *
   * @return int timestamp
   */
  private function getTodayMidnightTimestamp() {
    try {
      $zone     = new DateTimeZone ( get_option( 'timezone_string', 'UTC' ) );
      $midnight = new DateTimeImmutable( 'today', $zone );

      return $midnight->getTimestamp();
    } catch ( Exception $ex ) {
      /* fallback if tz stuff fails: midnight UTC */
      $t = time();

      return $t - ( $t % DAY_IN_SECONDS );
    }
  }

  public function disableAutoRebuild() {
    $task = new Reindex ();
    $task->init();
    $task->cancel();
  }

  /**
   * Filters the list of available list table views.
   * Replaces sentinel counts in the user views.
   *
   * @param string[] $views An array of available list table views.
   *
   * @return array
   */
  public function fake_views_users( $views ) {

    $replacement = esc_attr__( 'Still counting users...', 'index-wp-users-for-speed' );
    $sentinel    = number_format_i18n( self::$sentinelCount );
    $replacement = '<span title="' . $replacement . '">...</span>';
    $result      = [];
    foreach ( $views as $view ) {
      $result[] = str_replace( $sentinel, $replacement, $view );
    }

    return $result;
  }

  /** Change the stored list of editors to add or remove a particular user as needed.
   *
   * @param int $user_id The user's ID
   * @param bool $removingUser True if the user is being removed. Default false.
   *
   * @return void
   */
  public function updateEditors( $user_id, $removingUser = false ) {
    $userdata = get_userdata( $user_id );
    $canEdit  = ! $removingUser && $userdata->has_cap( 'edit_posts' );
    $task     = new GetEditors();
    $editors  = $task->getStatus();
    if ( $task->isAvailable( $editors ) ) {
      $editorList = &$editors['editors'];
      if ( $canEdit ) {
        $editorList[]       = $user_id;
        $editorList         = array_unique( $editorList, SORT_NUMERIC );
        $editors['editors'] = $editorList;
      } else {
        $result = [];
        foreach ( $editorList as $editor ) {
          if ( $editor !== $user_id ) {
            $result[] = $editor;
          }
        }
        $editors['editors'] = $result;
      }
      $task->setStatus( $editors );
    }
  }

  public function getEditors() {
    $task   = new GetEditors();
    $status = $task->getStatus();
    if ( $task->isAvailable( $status ) ) {
      return $status['editors'];
    }

    return false;
  }

  public function metaIndexRoleFraction () {
    $task   = new PopulateMetaIndexRoles();
    $status = $task->getStatus();
    return $task->fractionComplete( $status );

  }
  public function isMetaIndexRoleAvailable() {
    $task   = new PopulateMetaIndexRoles();
    $status = $task->getStatus();
    return $task->isAvailable( $status );
  }

  /** WHen a user changes roles, update the user counts.
   *
   * @param $newRole
   * @param array $oldRoles
   *
   * @return void
   */
  public function updateUserCountsForRoleChange( $newRole, array $oldRoles ) {
    $this->updateUserCounts( $newRole, + 1 );
    $this->updateUserCounts( $oldRoles, - 1 );
  }

  /** Update the user count for a particular role.
   *
   * @param string[]|string $roles rolename or names to change
   * @param integer $value number of users to add or subtract
   *
   * @return void
   */
  public function updateUserCounts( $roles, $value ) {
    if ( is_string( $roles ) ) {
      $roles = [ $roles ];
    }
    $task       = new CountUsers();
    $userCounts = $task->getStatus();
    if ( $task->isAvailable( $userCounts ) ) {
      foreach ( $roles as $role ) {
        if ( ! array_key_exists( $role, $userCounts['avail_roles'] ) ) {
          $userCounts['avail_roles'][ $role ] = 0;
        }
        $userCounts['avail_roles'][ $role ] += $value;
      }

      if ( is_numeric( $userCounts['total_users'] ) ) {
        $userCounts['total_users'] += $value;
      }
      $task->setStatus( $userCounts );
    }
  }

  public function getUserCounts( $allowFakes = true) {
    $task = new CountUsers();

    $userCounts = $task->getStatus();
    if ( ! $task->isAvailable( $userCounts ) ) {
      if ($allowFakes) {
        /* no user counts yet. We will fake them until they're available */
        $userCounts = $this->fakeUserCounts();
      }
      if ( $task->needsRunning( $userCounts ) ) {
        $task->init();
        $task->maybeSchedule( $userCounts );
      }
    }

    return $userCounts;
  }

  /** @noinspection SqlNoDataSourceInspection */

  /** Generate fake user counts for the views list on the users page.
   * This is compatible with the structure handled by 'pre_count_users'.
   * It's a hack. We put in ludicrously high user counts,
   * then filter them out in the `views_users` filter.
   *
   * @return array
   */
  private function fakeUserCounts() {
    $total_users = is_multisite() ? self::$sentinelCount : self::getNetworkUserCount();
    $avail_roles = [];
    $roles       = wp_roles();
    $roles       = $roles->get_names();
    foreach ( $roles as $role => $name ) {
      $avail_roles[ $role ] = self::$sentinelCount;
    }
    $avail_roles['none'] = 0;
    $result              = [
      'total_users' => $total_users,
      'avail_roles' => & $avail_roles,
      'complete'    => false,
    ];

    add_filter( 'views_users', [ $this, 'fake_views_users' ] );

    return $result;
  }

  public static function getNetworkUserCount() {
    global $wpdb;
    $q = "SELECT t.TABLE_ROWS row_count
                 FROM information_schema.TABLES t
                 WHERE t.TABLE_SCHEMA = DATABASE()
                   AND t.TABLE_TYPE = 'BASE TABLE'
                   AND t.ENGINE IS NOT NULL
                   AND t.TABLE_NAME = %s";

    return $wpdb->get_var( $wpdb->prepare( $q, $wpdb->users ) );

  }

  public function removeIndexRole( $user_id, $blog_id ) {
    global $wpdb;

    if (is_multisite()) {
      switch_to_blog( $blog_id );
    }
    $prefix = $wpdb->prefix . INDEX_WP_USERS_FOR_SPEED_KEY_PREFIX . 'r:';
    $q      = "DELETE FROM $wpdb->usermeta m WHERE m.user_id = %d AND m.meta_key LIKE CONCAT('%s', '%%') ";
    $q      = $wpdb->prepare( $q, $user_id, $wpdb->esc_like( $prefix ) );
    $wpdb->query( $q );
    if (is_multisite()) {
      restore_current_blog();
    }
  }

  public function updateIndexRole( $user_id, $role, $blog_id ) {
    global $wpdb;

    if (is_multisite()) {
      switch_to_blog( $blog_id );
    }
    $prefix    = $wpdb->prefix . INDEX_WP_USERS_FOR_SPEED_KEY_PREFIX . 'r:';
    $indexRole = $prefix . $role;

    try {
      $wpdb->query( 'START TRANSACTION' );
      $q       = "SELECT umeta_id FROM $wpdb->usermeta m WHERE m.user_id = %d AND m.meta_key LIKE CONCAT('%s', '%%') FOR UPDATE";
      $q       = $wpdb->prepare( $q, $user_id, $wpdb->esc_like( $prefix ) );
      $results = $wpdb->get_results( $q );
      $count   = 0;
      foreach ( $results as $result ) {
        $count ++;
        if ( $count === 1 ) {
          /* update the first role item we found */
          $q1 = "UPDATE $wpdb->usermeta m SET meta_key = '%s' WHERE m.umeta_id = %d";
          $q1 = $wpdb->prepare( $q1, $indexRole, $result->umeta_id );
          $wpdb->query( $q1 );
        } else {
          /* clear out any duplicate role items, one per user only */
          $q2 = "DELETE FROM $wpdb->usermeta m WHERE m.umeta_id = %d";
          $q2 = $wpdb->prepare( $q2, $result->umeta_id );
          $wpdb->query( $q2 );
        }
      }
      if ( $count === 0 ) {
        /* no rows to update, insert one */
        $q3 = "INSERT INTO $wpdb->usermeta (user_id, meta_key) VALUES (%d, %s);";
        $q3 = $wpdb->prepare( $q3, $user_id, $indexRole );
        $wpdb->query( $q3 );
      }
      $wpdb->query( 'COMMIT' );
    } catch ( Exception $ex ) {
      $wpdb->query( 'ROLLBACK' );
      error_log( 'index_wp_users_for_speed: updateIndexRole exception: ' . $ex->getMessage() . ' ' . $ex->getTraceAsString() );
    } finally {
      if ( is_multisite() ) {
        restore_current_blog();
      }
    }
  }

  public function __clone() {

  }

  /**
   * @throws Exception
   */
  public function __wakeup() {
    throw new Exception( 'cannot unserialize this singleton' );
  }

}