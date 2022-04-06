<?php /** @noinspection PhpUnused */

namespace IndexWpUsersForSpeed;

/**
 * Task to reindex everything. Can be scheduled daily, etc.
 */
class Reindex extends Task {

  /**
   * @param int|null $siteId Site id for task.
   * @param int $timeout Runtime limit of task. Default = no limit
   */
  public function __construct( $siteId = null, $timeout = 0 ) {

    parent::__construct( $siteId, $timeout );
    /* different hook name for repeating events */
    $this->hookName = 'index_wp_users_for_speed_repeating_task';
  }

  public function needsDoing() {
    return true;
  }

  /** Kick off the other tasks.
   * @return boolean  done When this is false, schedule another chunk.
   */
  public function doChunk() {
    $this->startChunk();

    $task = new CountUsers();
    $task->init();
    $task->schedule();

    $task = new GetEditors();
    $task->init();
    $task->schedule();

    $task = new PopulateMetaIndexRoles();
    $task->init();
    $task->schedule();

    $this->endChunk();

    /* done in one chunk */

    return true;
  }

}


