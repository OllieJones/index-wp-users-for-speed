<?php /** @noinspection PhpUnused */

namespace IndexWpUsersForSpeed;

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
  }

  /** Retrieve the user counts and update the option
   * @return boolean  done When this is false, schedule another chunk.
   */
  public function doChunk() {
    $this->startChunk();

    $userCounts = count_users('force_recount');
    $this->setStatus( $userCounts, true, false, 1 );

    $this->endChunk();

    return true;
  }

}
