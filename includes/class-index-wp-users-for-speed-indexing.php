<?php

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
    if ( $this->userCounts === false ) {
      /* no user counts yet. We will fake them until they're available */
      $this->userCounts = $this->fakeUserCounts();
      $this->setUserCounts(  );
    }

    return $this->userCounts;
  }

  public function setUserCounts( $userCounts = null ) {
    $userCounts = $userCounts === null ? $this->userCounts : $userCounts;
    $transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts";
    set_transient( $transientName, $userCounts, INDEX_WP_USERS_FOR_SPEED_SHORT_LIFETIME );
    $this->userCounts = $userCounts;
  }

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

  /** @noinspection PhpUnused */

  /** Filter to replace sentinel counts in the user view line
   *
   * @param array $lines
   *
   * @return array
   */
  public function fake_views_users( $lines ) {

    $replacement = esc_attr__( 'Still counting users...', 'index-wp-users-for-speed' );
    $replacement = '<span title="' . $replacement . '">...</span>';
    $result      = [];
    foreach ( $lines as $line ) {
      $result[] = str_replace( number_format_i18n( self::$sentinelCount ), $replacement, $line );
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