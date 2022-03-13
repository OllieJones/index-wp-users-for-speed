<?php

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/tasks/count-users.php';

class Index_Wp_Users_For_Speed_Indexing {

  /* a simple singleton class */
  protected static $singleInstance;
  private static $sentinelCount;
  private $userCounts;
  private $networkUserCount;

  protected function __construct() {

    self::$sentinelCount = 1024 * 1024 * 1024 - 1;

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
    $transientName    = INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts";
    $this->userCounts = get_transient( $transientName );
    if ( $this->userCounts === false || ( isset( $this->userCounts['complete'] ) && ! $this->userCounts['complete'] ) ) {
      /* no user counts yet. We will fake them until they're available */
      $this->userCounts = $this->fakeUserCounts();
      $this->setUserCounts();
      $countAll = new CountUsers();
      $countAll->doAll();
      $foo = serialize($countAll);
      $xxx = unserialize($foo);
      $xxx->doAll();
    }

    return $this->userCounts;
  }

  public function setUserCounts( $userCounts = null ) {
    $userCounts    = $userCounts === null ? $this->userCounts : $userCounts;
    $transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts";
    set_transient( $transientName, $userCounts, INDEX_WP_USERS_FOR_SPEED_SHORT_LIFETIME );
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

  /** @noinspection SqlNoDataSourceInspection */
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