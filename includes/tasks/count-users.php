<?php /** @noinspection PhpUnused */

namespace OllieJones\index_wp_users_for_speed;

/**
 * Task to count users by role.
 */
class CountUsers extends Task {

  /**
   * @param int|null $siteId Site id for task.
   * @param int $timeout Runtime limit of task. Default = no limit
   */
  public function __construct( $siteId = null, $timeout = 0 ) {

    parent::__construct( $siteId, $timeout );
    $this->taskName = __CLASS__;

  }

  /** Retrieve the user counts and update the transient
   * @return boolean  done When this is false, schedule another chunk.
   */
  public function doChunk() {
    $this->startChunk();

    $userCounts             = count_users();
    $userCounts['complete'] = true;
    set_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts", $userCounts, INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME );

    $this->endChunk();

    return true;
  }

  public function getResult () {
    get_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts" );
  }
}


