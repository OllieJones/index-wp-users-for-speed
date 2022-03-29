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
      $task->setStatus (null, true, false, 1);
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

  public function maybeSchedule( $status = null ) {
    if ( !isset($status) || $status === false ) {
      $status = $this->getStatus();
    }
    if ( ! $this->isActive( $status ) ) {
      $this->schedule();
    }
  }

  public function getStatus() {
    $transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'task' . self::toSnake( $this->taskName );
    $result = get_transient( $transientName );
    $this->log ('get status ' . serialize($result));
    return $result;
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

  /** Is a task active.
   *
   * This is true when a task is running, either
   * for the first time or in a way that updates it.
   * running in a way that updates it.
   *
   * Don't start the task again if this is true.
   *
   * @param array $status optional status.
   *
   * @return bool ready to use.
   */
  public function isActive( $status = null ) {
    $status = $status === null ? $this->getStatus() : $status;

    return is_array( $status ) && isset( $status['active'] ) && $status['active'];
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

  protected function persist( $item ) {
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

  public function clearStatus() {
    $transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'task' . self::toSnake( $this->taskName );
    delete_transient( $transientName );
  }

  public function setStatus( $status, $available = null, $active = null, $fraction = null ) {
    $transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'task' . self::toSnake( $this->taskName );
    if ( $status === null ) {
      $status = $this->getStatus();
    }
    if (!isset ($status) || $status === false ) {
      $status = [];
    }
    if ( isset( $available ) ) {
      $status['available'] = $available;
    }
    if ( isset( $active) ) {
      $status['active'] = $active;
    }
    if ( isset( $fraction ) ) {
      $status['fraction'] = $fraction;
    }
    set_transient( $transientName, $status, INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME );
    $this->log('set status ' . serialize($status));

  }

  /** Is a task's output completely missing.
   *
   * @param array $status optional status
   *
   * @return bool  true means it's missing.
   */
  public function isMissing( $status = null ) {
    $status = $status === null ? $this->getStatus() : $status;

    return $status === false;
  }

  /** Is a task's output ready to use.
   *
   * This can be true if the task has been run, and if it is actively
   * running in a way that updates it.
   *
   * @param array $status optional status.
   *
   * @return bool ready to use.
   */
  public function isAvailable( $status = null ) {
    $status = $status === null ? $this->getStatus() : $status;

    return is_array( $status ) && isset( $status['available'] ) && $status['available'];
  }

  protected function startChunk() {
    if ($this->useCount === 0 ) {
      $this->setStatus(null, null, true, 0.001);
    }
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
