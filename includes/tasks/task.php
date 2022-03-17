<?php

namespace OllieJones\index_wp_users_for_speed;
use Exception;

/** WP Cron hook to handle a task and reschedule it if need be
 *
 * @param $serializedTask
 *
 * @return void
 */
function index_wp_users_for_speed_do_task( $serializedTask ) {
  try {
    $task = unserialize( $serializedTask );
    $done = $task->doChunk();
    if ( ! $done ) {
      $task->schedule();
    }
  } catch ( Exception $ex ) {
    error_log( 'index_wp_users_for_speed_task: cron hook exception: ' . $task->taskName . ' ' . $ex->getMessage() . ' ' . $ex->getTraceAsString() );
  }
}

add_action( 'index_wp_users_for_speed_task',  __NAMESPACE__ . '\index_wp_users_for_speed_do_task' );


class Task {
  public $taskName;
  public $lastTouch;
  public $useCount = 0;
  private $siteId;
  private $timeout;

  /** create a task
   *
   * @param int|null $siteId Site id for the task
   * @param int $timeout Runtime limit in seconds. Default = no limit.
   */
  public function __construct( $siteId = null, $timeout = 0 ) {
    $this->taskName  = __CLASS__;
    $this->lastTouch = time();
    $siteId          = $siteId === null ? get_current_blog_id() : $siteId;
    $this->siteId    = $siteId;
    $this->timeout   = $timeout;
  }

  public function schedule( $delay = 0 ) {
    wp_schedule_single_event( time() + $delay, 'index_wp_users_for_speed_task', [ serialize( $this ) ] );
  }

  public function getResult () {
    return false;
  }

  protected function startChunk() {
    set_time_limit( $this->timeout );
    $this->lastTouch = time();
    if ( is_multisite() && get_current_blog_id() != $this->siteId ) {
      switch_to_blog( $this->siteId );
    }
  }

  protected function endChunk() {
    if ( is_multisite() && get_current_blog_id() != $this->siteId ) {
      restore_current_blog();
    }
    $this->useCount ++;
  }
}
