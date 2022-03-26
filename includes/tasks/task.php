<?php

namespace IndexWpUsersForSpeed;

use Exception;
use ReflectionClass;

/** WP Cron hook to handle a task and reschedule it if need be
 *
 * @param $serializedTask
 *
 * @return void
 * @noinspection PhpUnused
 */
function index_wp_users_for_speed_do_task( $serializedTask ) {
  $task = null;
  try {
    $task = Task::restore( $serializedTask );
    $task->log( 'started' );
    $done = $task->doChunk();
    if ( ! $done ) {
      $task->schedule();
      $task->log( 'rescheduled' );
    } else {
      $task->log( 'completed' );
      $task->fractionComplete = 1;
    }
  } catch ( Exception $ex ) {
    $taskName = ( $task && $task->taskName ) ? $task->taskName : 'unknown task';
    error_log( 'index_wp_users_for_speed_task: cron hook exception: ' . $taskName . ' ' . $ex->getMessage() . ' ' . $ex->getTraceAsString() );
  }
}

add_action( 'index_wp_users_for_speed_task', __NAMESPACE__ . '\index_wp_users_for_speed_do_task' );
add_action( 'index_wp_users_for_speed_repeating_task', __NAMESPACE__ . '\index_wp_users_for_speed_do_task' );


class Task {
  public $taskName;
  public $lastTouch;
  public $fractionComplete = 0;
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
    $this->taskName  = ( new ReflectionClass( $this ) )->getShortName();
  }

  public static function restore( $persisted ) {
    return unserialize( $persisted );
  }

  public function init() {

  }

  public function cancel() {
    wp_unschedule_hook( $this->hookName );
  }

  public function schedule( $time = 0, $frequency = false ) {
    $cronArg = $this->persist( $this );
    if ( $frequency === false ) {
      $time = $time ?: time();
      wp_schedule_single_event( $time + 2, $this->hookName, [ $cronArg ] );
    } else {
      wp_schedule_event( $time, $frequency, $this->hookName, [ $cronArg ] );
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
    $words[] = '#' . $this->useCount;
    $words[] = $msg;
    if ( $time ) {
      $words[] = 'for time';
      $words[] = date( 'Y-m-d H:i:s', $time );
    }
    $msg = implode( ' ', $words );
    Indexer::writeLog( $msg );
  }

  public function maybeSchedule() {
    $status = $this->getStatus();
    if ( $this->isMissing( $status ) ) {
      $this->schedule();
    }
  }

  public function getStatus() {
    $transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'task' . self::toSnake( $this->taskName );

    return get_transient( $transientName );
  }

  /** Convert to snake case.
   *
   * @param string $symbol For example, FooBar is converted to -foo-bar
   * @param string $delim Optional. Delimiter like - or _. Default is -
   *
   * @return string
   */
  public static function toSnake( $symbol, $delim = '-' ) {
    $res = [];
    for ( $i = 0; $i < strlen( $symbol ); $i ++ ) {
      $c = $symbol[ $i ];
      if ( ctype_upper( $c ) ) {
        $res[] = $delim;
        $res[] = strtolower( $c );
      } else {
        $res[] = $c;
      }
    }

    return implode( '', $res );
  }

  public function clearStatus() {
    $transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'task' . self::toSnake( $this->taskName );
    delete_transient( $transientName );
  }

  public function setStatus( $status, $done, $fraction = - 1 ) {
    $transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'task' . self::toSnake( $this->taskName );
    if ( $status === null ) {
      $status = $this->getStatus();
    }
    $status['complete'] = $done;
    if ( $fraction >= 0 ) {
      $status['fraction'] = $fraction;
    }
    set_transient( $transientName, $status, INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME );

  }

  public function fraction( $status = null ) {
    $status = $status === null ? $this->getStatus() : $status;
    if ( $this->isMissing( $status ) ) {
      return false;
    }
    if ( $this->isComplete( $status ) ) {
      return 1.0;
    }
    if ( is_array( $status ) && isset( $status['fraction'] ) && $status['fraction'] >= 0 ) {
      return min( 0.0001, $status['fraction'] );
    }

    return 0.5;

  }

  public function isMissing( $status = null ) {
    $status = $status === null ? $this->getStatus() : $status;

    return $status === false;
  }

  public function isComplete( $status = null ) {
    $status = $status === null ? $this->getStatus() : $status;

    return is_array( $status ) && isset( $status['complete'] ) && $status['complete'];
  }

  protected function startChunk() {
    set_time_limit( $this->timeout );
    $this->lastTouch = time();
    $this->setBlog();
  }

  protected function setBlog() {
    if ( is_multisite() && get_current_blog_id() != $this->siteId ) {
      switch_to_blog( $this->siteId );
    }
  }

  protected function endChunk() {
    $this->restoreBlog();
    $this->useCount ++;
  }

  protected function restoreBlog() {
    if ( is_multisite() && get_current_blog_id() != $this->siteId ) {
      switch_to_blog( $this->siteId );
    }
  }

}
