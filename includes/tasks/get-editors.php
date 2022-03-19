<?php /** @noinspection PhpUnused */

namespace OllieJones\index_wp_users_for_speed;

use WP_User_Query;

/**
 * Task to count users by role.
 */
class GetEditors extends Task {

  /**
   * @param int|null $siteId Site id for task.
   * @param int $timeout Runtime limit of task. Default = no limit
   */
  public function __construct( $siteId = null, $timeout = 0 ) {

    parent::__construct( $siteId, $timeout );
  }

  public function needsDoing() {
    $this->editors = get_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "editors");
    return (!is_array($this->editors));
  }

  /** Retrieve the user counts and update the transient
   * @return boolean  done When this is false, schedule another chunk.
   */
  public function doChunk() {
    $this->startChunk();

    $editors = [];

    $userQuery = new WP_User_Query(
      [
        'capability' => [ 'edit_posts' ],
        'fields'     => [ 'ID' ],
      ] );
    $qresults  = $userQuery->get_results();
    if ( ! empty ( $qresults ) ) {
      foreach ( $qresults as $qresult ) {
        $editors[] = $qresult->ID;
      }
    }
    /* there's some chance that a long IN(1,2,3) clause
     * will run slightly more efficiently in MySQL
     * if it is presorted for them. */
    sort($editors, SORT_NUMERIC);
    set_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "editors", $editors, INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME );
    $this->endChunk();

    /* done in one chunk */
    return true;
  }

  public function getResult () {
    return get_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "editors");
  }

  public function reset() {
    delete_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "editors" );
  }

}


