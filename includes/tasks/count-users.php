<?php /** @noinspection PhpUnused */

class CountUsers {

  public $taskName = 'count-users';
  public $lastTouch;
  private $siteId;
  private $transientName;

  public function __construct( $siteId = null ) {
    $this->lastTouch = time();
    $this->transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts";
    $siteId          = $siteId === null ? get_current_blog_id() : $siteId;
    $this->siteId    = $siteId;
    $switchSite      = is_multisite() && get_current_blog_id() != $siteId;

    if ( $switchSite ) {
      switch_to_blog( $siteId );
    }
    $userCounts          = get_transient( $this->transientName );
    if ( $userCounts === false || (isset($userCounts['complete']) && !$userCounts['complete']) ) {
      /* create the user count object */
      $avail_roles = [];
      $roles = wp_roles()->get_names();
      foreach ( $roles as $role => $name ) {
        $avail_roles[ $role ] = 0;
      }
      $avail_roles['none'] = 0;
      $userCounts          = [
        'total_users' => 0,
        'avail_roles' => & $avail_roles,
        'complete'    => false,
      ];
      set_transient( $this->transientName, $userCounts, INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME );
    }

    if ( $switchSite ) {
      restore_current_blog();
    }
  }

  /** Retrieve a chunk of user counts, and update the transient.
   * We'll do this in one chunk for now.
   * It doesn't make sense to use multiple chunks without KEY (meta_key, user_id)
   * @return boolean  done When this is false, schedule another chunk.
   */
  public function doChunk() {
    global $wpdb;
    $this->lastTouch = time();

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
       GROUP BY meta_value";

    $userCounts   = get_transient( $this->transientName );
    $done         = $userCounts['complete'];
    $capabilities = $wpdb->prefix . 'capabilities';
    if ( ! $done ) {
      $wpdb->query("SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;");
      $prepared = $wpdb->prepare( $query, $capabilities );
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
      $done = true;
    }

    $userCounts['complete'] = $done;
    set_transient( $this->transientName, $userCounts, INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME );

    if ( $switchSite ) {
      restore_current_blog();
    }

    return $done;
  }
}


