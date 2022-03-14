<?php

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tasks/count-users.php';


/** WP Cron hook to handle a task and reschedule it if need be
 *
 * @param $serializedTask
 *
 * @return void
 */
function index_wp_users_for_speed_do_task( $serializedTask ) {
  index_wp_users_for_speed_error_log( 'index_wp_users_for_speed_task: start cron hook: ' . $serializedTask );
  try {
    $task = unserialize( $serializedTask );
    $done = $task->doChunk();
    if ( ! $done ) {
      $serializedTask = serialize( $task );
      index_wp_users_for_speed_error_log( 'index_wp_users_for_speed_task: reschedule cron hook: ' . $serializedTask );

      wp_schedule_single_event( time() + 1, 'index_wp_users_for_speed_task', [ $serializedTask ] );
    }
  } catch ( Exception $ex ) {
    error_log( 'index_wp_users_for_speed_task: cron hook exception: ' . $ex->getMessage() . $ex->getTraceAsString() );
  }
}

add_action( 'index_wp_users_for_speed_task', 'index_wp_users_for_speed_do_task' );

class Index_Wp_Users_For_Speed_Indexing {

  /* a simple singleton class */
  protected static $singleInstance;
  private static $sentinelCount;
  private $userCounts;
  private $networkUserCount;

  protected function __construct() {

    /* a magic count of users to indicate "don't know yet" */
    self::$sentinelCount = 1024 * 1024 * 1024 * 2 - 7;

  }

  public static function getInstance() {
    if ( ! isset( self::$singleInstance ) ) {
      self::$singleInstance = new self();
    }

    return self::$singleInstance;
  }

  public function getUserCounts() {
    if ( isset( $this->userCounts ) ) {
      return $this->userCounts;
    }
    $this->userCounts = get_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts" );
    if ( $this->userCounts === false || ( isset( $this->userCounts['complete'] ) && ! $this->userCounts['complete'] ) ) {
      /* no user counts yet. We will fake them until they're available */
      $this->userCounts = $this->fakeUserCounts();
      $this->setUserCounts();
      $this->startBackgroundTask( new CountUsers() );
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

  /** @noinspection SqlNoDataSourceInspection */

  private function startBackgroundTask( $task ) {
    $serializedTask = serialize( $task );
    wp_schedule_single_event( time() + 1, 'index_wp_users_for_speed_task', [ $serializedTask ] );
    index_wp_users_for_speed_error_log( 'index_wp_users_for_speed_task: schedule cron hook: ' . $serializedTask );
  }

  /** Update the user count for a particular role.
   *
   * @param string $role rolename to change
   * @param integer $value number of users to add or subtract
   *
   * @return void
   */
  public function updateUserCounts( $role, $value ) {
    if ( is_array( $this->userCounts['avail_roles'] ) ) {
      if ( ! array_key_exists( $role, $this->userCounts['avail_roles'] ) ) {
        $this->userCounts['avail_roles'][ $role ] = 0;
      }
      $this->userCounts['avail_roles'][ $role ] += $value;
      if ( $this->userCounts['avail_roles'][ $role ] === 0 ) {
        unset ( $this->userCounts['avail_roles'][ $role ] );
      }
    }
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

  protected function __clone() {

  }

  /**
   * @throws Exception
   */
  protected function __wakeup() {
    throw new Exception( "cannot unserialize this singleton" );
  }

}