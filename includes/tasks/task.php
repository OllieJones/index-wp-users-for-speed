<?php

namespace IndexWpUsersForSpeed;

use Exception;

/** WP Cron hook to handle a task and reschedule it if need be
 *
 * @param $serializedTask
 *
 * @return void
 */
function index_wp_users_for_speed_do_task( $serializedTask ) {
  try {
    $task = Task::restore( $serializedTask );
    $task->log( 'started' );
    $done = $task->doChunk();
    if ( ! $done ) {
      $task->schedule();
      $task->log( 'rescheduled' );
    } else {
      $task->log( 'completed' );
    }
  } catch ( Exception $ex ) {
    error_log( 'index_wp_users_for_speed_task: cron hook exception: ' . $task->taskName . ' ' . $ex->getMessage() . ' ' . $ex->getTraceAsString() );
  }
}

add_action( 'index_wp_users_for_speed_task', __NAMESPACE__ . '\index_wp_users_for_speed_do_task' );
add_action( 'index_wp_users_for_speed_repeating_task', __NAMESPACE__ . '\index_wp_users_for_speed_do_task' );


class Task {
  public $taskName;
  public $lastTouch;
  public $useCount = 0;
  public $hookName = 'index_wp_users_for_speed_task';
  public $siteId;
  public $timeout;

  /** create a task
   *
   * @param int|null $siteId Site id for the task
   * @param int $timeout Runtime limit in seconds. Default = no limit.
   */
  public function __construct( $siteId = null, $timeout = 0 ) {
    $this->lastTouch = time();
    $siteId          = $siteId === null ? get_current_blog_id() : $siteId;
    $this->siteId    = $siteId;
    $this->timeout   = $timeout;
    $this->taskName  = ( new \ReflectionClass( $this ) )->getShortName();
  }

  public static function restore( $persisted ) {
    return unserialize( $persisted );
  }

  public function cancel() {
    wp_unschedule_hook( $this->hookName );
  }

  public function schedule( $time = 0, $frequency = false ) {
    if ( $frequency === false ) {
      $time = $time ? $time : time();
      wp_schedule_single_event( $time, $this->hookName, [ $this->persist( $this ) ] );
    } else {
      wp_schedule_event( $time, $frequency, $this->hookName, [ $this->persist( $this ) ] );
    }
    $msg = ( $frequency ?: 'one-off' ) . ' scheduled';
    $this->log( $msg, $time );
  }

  public function persist( $item ) {
    return serialize( $item );
  }

  public function log( $msg, $time = 0 ) {
    $words   = [];
    $words[] = 'Task';
    $words[] = $this->taskName;
    $words[] = '(' . $this->siteId . ')';
    $words[] = $msg;
    if ( $time ) {
      $words[] = 'for time';
      $words[] = date( 'Y-m-d H:i:s', $time );
    }
    $msg = implode( ' ', $words );
    Indexer::writeLog( $msg );
  }

  public function getResult() {
    return false;
  }

  public function reset() {

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
