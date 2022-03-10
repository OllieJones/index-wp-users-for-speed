<?php

class Index_Wp_Users_For_Speed_Indexing {

  /* a simple singleton class */
  protected static $singleInstance;
  private $userCounts;

  private $recursionLevel = 0;

  protected function __construct() {
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
      $this->userCounts = count_users();
      $this->setUserCounts( $this->userCounts );
    }

    return $this->userCounts;
  }

  public function setUserCounts( $userCounts = null ) {
    if ( $userCounts === null ) {
      $userCounts = $this->userCounts;
    } else {
      $this->userCounts = $userCounts;
    }
    $transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts";
    set_transient( $transientName, $userCounts, INDEX_WP_USERS_FOR_SPEED_SHORT_LIFETIME );
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

  protected function __clone() {

  }

  /**
   * @throws Exception
   */
  protected function __wakeup() {
    throw new Exception( "cannot unserialize this singleton" );
  }

}