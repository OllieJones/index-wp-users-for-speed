<?php /** @noinspection PhpIncludeInspection */

namespace OllieJones\index_wp_users_for_speed;

use Exception;

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tasks/task.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tasks/count-users.php';
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tasks/get-editors.php';

class Indexer {

  /* a simple singleton class */
  protected static $singleInstance;
  private static $sentinelCount;
  private $userCounts = false;
  private $editors = false;
  private $networkUserCount;

  protected function __construct() {

    /* a magic count of users to indicate "don't know yet" */
    self::$sentinelCount = 1024 * 1024 * 1024 * 2 - 7;

    $this->maybeIndexEverything();

  }

  public function maybeIndexEverything() {
    $this->userCounts = get_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts" );
    if ( $this->userCounts === false || ( isset( $this->userCounts['complete'] ) && ! $this->userCounts['complete'] ) ) {
      $this->setUserCounts();
      $task = new CountUsers();
      $task->schedule();
      $this->userCounts = false;
    }

    $this->editors = get_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "editors" );
    if ( ! is_array( $this->editors ) ) {
      $task = new GetEditors();
      $task->schedule();
    }
  }

  public static function getInstance() {
    if ( ! isset( self::$singleInstance ) ) {
      self::$singleInstance = new self();
    }

    return self::$singleInstance;
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

    $canEdit = ! $removingUser && ( get_userdata( $user_id ) )->has_cap( 'edit_post' );
    $editors = $this->editors;
    if ( is_array( $editors ) ) {
      if ( $canEdit ) {
        $editors[] = $user_id;
        $editors   = array_unique( $editors, SORT_NUMERIC );
      } else {
        $result = [];
        foreach ( $editors as $editor ) {
          if ( $editor !== $user_id ) {
            $result = $editor;
          }
        }
        $editors = $result;
      }
      $this->editors = $result;
      set_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "editors", $editors, INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME );
    }
  }

  public function getEditors() {
    return $this->editors;
  }

  /** WHen a user changes roles, update the user counts.
   *
   * @param $newRole
   * @param array $oldRoles
   *
   * @return void
   */
  public function updateUserCountsForRoleChange( $newRole, array $oldRoles ) {
    $this->getUserCounts();
    $this->updateUserCounts( $newRole, + 1 );
    $this->updateUserCounts( $oldRoles, - 1 );
    $this->setUserCounts();
  }

  public function getUserCounts() {
    if ( $this->userCounts !== false ) {
      return $this->userCounts;
    }
    $this->userCounts = get_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts" );
    if ( $this->userCounts === false || ( isset( $this->userCounts['complete'] ) && ! $this->userCounts['complete'] ) ) {
      /* no user counts yet. We will fake them until they're available */
      $this->userCounts = $this->fakeUserCounts();
      $this->setUserCounts();
    }

    return $this->userCounts;
  }

  public function setUserCounts( $userCounts = null ) {
    $userCounts = $userCounts === null ? $this->userCounts : $userCounts;
    set_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts",
      $userCounts,
      INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME );
    $this->userCounts = $userCounts;
  }

  /** Generate fake user counts for the views list on the users page.
   * This is compatible with the structure handled by 'pre_count_users'.
   * It's a hack. We put in ludicrously high user counts,
   * then filter them out in the `views_users` filter.
   *
   * @return array
   */
  private function fakeUserCounts() {
    $roles       = wp_roles()->get_names();
    $total_users = is_multisite() ? self::$sentinelCount : $this->getNetworkUserCount();
    $avail_roles = [];
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

  public function getNetworkUserCount() {
    global $wpdb;
    if ( isset ( $this->networkUserCount ) ) {
      return $this->networkUserCount;
    }
    $q = "SELECT t.TABLE_ROWS row_count
                 FROM information_schema.TABLES t
                 WHERE t.TABLE_SCHEMA = DATABASE()
                   AND t.TABLE_TYPE = 'BASE TABLE'
                   AND t.ENGINE IS NOT NULL
                   AND t.TABLE_NAME = %s";

    $this->networkUserCount = $wpdb->get_var( $wpdb->prepare( $q, $wpdb->users ) );

    return $this->networkUserCount;
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
    if ( is_array( $this->userCounts['avail_roles'] ) ) {
      foreach ( $roles as $role ) {
        if ( ! array_key_exists( $role, $this->userCounts['avail_roles'] ) ) {
          $this->userCounts['avail_roles'][ $role ] = 0;
        }
        $this->userCounts['avail_roles'][ $role ] += $value;
        if ( $this->userCounts['avail_roles'][ $role ] === 0 ) {
          unset ( $this->userCounts['avail_roles'][ $role ] );
        }
      }
    }
    if ( is_numeric( $this->userCounts['total_users'] ) ) {
      $this->userCounts['total_users'] += $value;
    }
  }

  protected function __clone() {

  }

  /**
   * @throws Exception
   */
  protected function __wakeup() {
    throw new Exception( "cannot unserialize this singleton" );
  }

}