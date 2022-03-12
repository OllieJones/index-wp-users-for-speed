<?php

class CountUsers {

  private $siteId;
  private $chunkSize;
  private $roles;
  private $transientName;

  public function __construct( $siteId = null, $chunkSize = 100 ) {
    $this->siteId    = $siteId;
    $maxUserId       = $this->getMaxUserId();
    $chunkSize       = max( $maxUserId < $chunkSize ? intval( round( $maxUserId * 0.1 ) ) : $chunkSize, 2 );
    $this->chunkSize = $chunkSize;
    $siteId          = $siteId === null ? get_current_blog_id() : $siteId;
    $switchSite      = is_multisite() && get_current_blog_id() != $siteId;

    if ( $switchSite ) {
      switch_to_blog( $siteId );
    }
    $this->transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts";
    $userCounts          = get_transient( $this->transientName );
    if ( $userCounts === false || (isset($userCounts['complete']) && !$userCounts['complete']) ) {
      /* create the user count object */
      $avail_roles = [];
      $this->roles = wp_roles()->get_names();
      foreach ( $this->roles as $role => $name ) {
        $avail_roles[ $role ] = 0;
      }
      $avail_roles['none'] = 0;
      $userCounts          = [
        'total_users' => 0,
        'avail_roles' => & $avail_roles,
        'complete'    => false,
        'maxUsers'    => $maxUserId,
        'nextChunk'   => 0,
        'chunkSize'   => $this->chunkSize,
      ];
      set_transient( $this->transientName, $userCounts, INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME );
    }

    if ( $switchSite ) {
      restore_current_blog();
    }
  }

  private function getMaxUserId() {
    global $wpdb;
    /** @noinspection SqlNoDataSourceInspection */
    /** @noinspection SqlResolve */
    $q = /** @lang MySQL */
      "SELECT MAX(ID) FROM $wpdb->users";

    return $wpdb->get_var( $wpdb->prepare( $q, $wpdb->users ) );
  }

  /** @noinspection PhpUnused */
  public function doAll() {

    /** @noinspection PhpStatementHasEmptyBodyInspection */
    while ( ! $this->doChunk() ) {
    }
  }

  /** Retrieve a chunk of user counts, and update the transient.
   * @return boolean  done
   */
  public function doChunk() {
    global $wpdb;
    $switchSite = is_multisite() && get_current_blog_id() != $this->siteId;

    if ( $switchSite ) {
      switch_to_blog( $this->siteId );
    }
    /** @noinspection SqlNoDataSourceInspection */
    /** @noinspection SqlResolve */
    $query = /** @lang MySQL */
      "SELECT COUNT(*) num, meta_value val 
         FROM $wpdb->usermeta
        WHERE meta_key = %s
         AND user_id >= %d
         AND user_id < %d
       GROUP BY meta_value";

    $userCounts   = get_transient( $this->transientName );
    $maxUserId    = $this->getMaxUserId();
    $done         = ( $userCounts['nextChunk'] >= $maxUserId ) || $userCounts['complete'];
    $capabilities = $wpdb->prefix . 'capabilities';
    $start        = $userCounts['nextChunk'];
    $end          = $start + $userCounts['chunkSize'];
    if ( ! $done ) {
      $prepared = $wpdb->prepare( $query, $capabilities, $start, $end );
      $results  = $wpdb->get_results( $prepared );
      foreach ( $results as $result ) {
        $num                       = $result->num;
        $userCounts['total_users'] += $num;
        $caps                      = unserialize( $result->val );
        foreach ( $caps as $cap => $active ) {
          if ( array_key_exists( $cap, $userCounts['avail_roles'] ) && $active ) {
            $userCounts['avail_roles'][ $cap ] += $num;
          }
        }
      }
      $start                   = $end;
      $userCounts['nextChunk'] = $start;
      $done                    = $start >= $maxUserId;
    }

    $userCounts['complete'] = $done;
    set_transient( $this->transientName, $userCounts, INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME );

    if ( $switchSite ) {
      restore_current_blog();
    }

    return $done;
  }
}


