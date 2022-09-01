<?php

namespace IndexWpUsersForSpeed;

use Exception;
use ReflectionClass;

/** WP Cron hook to handle a task and reschedule it if need be.
 *
 * @param string $taskName Name of persisted task
 *
 * @return void
 * @noinspection PhpUnused
 */
function index_wp_users_for_speed_do_task( $taskName ) {
  $task = null;
  try {
    $task = Task::restorePersisted( $taskName );
    $task->doTaskStep();
  } catch ( Exception $ex ) {
    $taskName = ( $task && $task->taskName ) ? $task->taskName : 'unknown task';
    error_log( 'index_wp_users_for_speed_task: cron hook exception: ' . $taskName . ' ' . $ex->getMessage() . ' ' . $ex->getTraceAsString() );
  }
}

add_action( 'index_wp_users_for_speed_task', __NAMESPACE__ . '\index_wp_users_for_speed_do_task', 10, 2 );
add_action( 'index_wp_users_for_speed_repeating_task', __NAMESPACE__ . '\index_wp_users_for_speed_do_task', 10, 2 );

abstract class Task {
  public $taskName;
  public $lastTouch;
  public $fractionComplete = 0;
  public $useCount = 0;
  public $hookName = INDEX_WP_USERS_FOR_SPEED_HOOKNAME;
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
    $reflect         = new ReflectionClass( $this );
    $this->taskName  = $reflect->getShortName();
  }

  public static function restorePersisted( $taskName ) {
    $transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'task' . $taskName;

    return get_transient( $transientName );
  }

  public function init() {

  }

  public function doTaskStep() {
    $done = $this->doChunk();
    if ( ! $done ) {
      $this->schedule();
    } else {
      $this->fractionComplete = 1;
      $this->setStatus( null, true, false, 1 );
      $this->clearPersisted();
    }
  }

  public abstract function doChunk();

  public function schedule( $time = 0, $frequency = false ) {
    $cronArg = $this->persist();
    if ( $frequency === false ) {
      $time = $time ?: time();
      wp_schedule_single_event( $time, $this->hookName, [ $cronArg, $this->useCount ] );
    } else {
      wp_schedule_event( $time, $frequency, $this->hookName, [ $cronArg, $this->useCount ] );
    }
    $msg = ( $frequency ?: 'one-off' ) . ' scheduled';
  }

  protected function persist() {
    $jobName       = self::toSnake( $this->taskName );
    $transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'task' . $jobName;
    set_transient( $transientName, $this, INDEX_WP_USERS_FOR_SPEED_SHORT_LIFETIME );

    return $jobName;
  }

  /** Convert to snake case.
   *
   * @param string $symbol For example, FooBar is converted to -foo-bar
   * @param string $delim Optional. Delimiter like - or _. Default is -
   *
   * @return string
   */
  public static function toSnake(
    $symbol, $delim = '-'
  ) {
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

  public function log(
    $msg, $time = 0
  ) {
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

  /** Update a task's status transient.
   *
   * @param null|array $status If the status is known give it here, otherwise give null
   * @param null|bool $available Set the available flag, or ignore it if null
   * @param null|bool $active Set the active flag, or ignore it if null
   * @param null|float $fraction Set the fraction-complete flag, or ignore it if null.
   *
   * @return void
   */
  public function setStatus(
    $status, $available = null, $active = null, $fraction = null
  ) {
    if ( $status === null ) {
      $status = $this->getStatus();
    }
    if ( ! isset ( $status ) || $status === false ) {
      $status = [];
    }
    if ( isset( $available ) ) {
      $status['available'] = $available;
    }
    if ( isset( $active ) ) {
      $status['active'] = $active;
    }
    if ( isset( $fraction ) ) {
      $status['fraction'] = $fraction;
    }
    $jobResultName = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'result' . self::toSnake( $this->taskName );
    set_transient( $jobResultName, $status, INDEX_WP_USERS_FOR_SPEED_LONG_LIFETIME );
  }

  public function getStatus() {
    $jobResultName = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'result' . self::toSnake( $this->taskName );

    return get_transient( $jobResultName );
  }

  protected function clearPersisted() {
    $jobStatusName = self::toSnake( $this->taskName );
    $transientName = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'task' . $jobStatusName;
    delete_transient( $transientName );
  }

  public function cancel() {
    wp_unschedule_hook( $this->hookName );
  }

  public function maybeSchedule(
    $status = null
  ) {
    if ( ! isset( $status ) || $status === false ) {
      $status = $this->getStatus();
    }
    if ( ! $this->isActive( $status ) ) {
      $this->schedule();
    }
  }

  public function needsRunning(
    $status = null
  ) {
    if ( ! isset( $status ) || $status === false ) {
      $status = $this->getStatus();
    }
    if ( $this->isMissing( $status ) ) {
      return true;
    }
    if ( $this->isAvailable( $status ) ) {
      return false;
    }

    return true;
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
  public function isActive(
    $status = null
  ) {
    $status = $status === null ? $this->getStatus() : $status;

    return is_array( $status ) && isset( $status['active'] ) && $status['active'];
  }

  public function clearStatus() {
    $jobResultName = INDEX_WP_USERS_FOR_SPEED_PREFIX . 'result' . self::toSnake( $this->taskName );
    delete_transient( $jobResultName );
  }

  /** Is a task's output completely missing.
   *
   * @param array $status optional status
   *
   * @return bool  true means it's missing.
   */
  public function isMissing(
    $status = null
  ) {
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
  public function isAvailable(
    $status = null
  ) {
    $status = $status === null ? $this->getStatus() : $status;

    return is_array( $status ) && isset( $status['available'] ) && $status['available'];
  }

  public function fractionComplete(
    $status = null
  ) {
    $status = $status === null ? $this->getStatus() : $status;
    return is_array( $status ) && isset( $status['fraction'] ) && is_numeric( $status['fraction'] ) ? $status['fraction'] : 1.0;
  }

  protected function startChunk() {
    if ( $this->useCount === 0 ) {
      $this->setStatus( null, null, true, 0.001 );
    }
    set_time_limit( $this->timeout );
    $this->lastTouch = time();
    $this->setBlog();
  }

  protected function endChunk() {
    $this->restoreBlog();
    $this->useCount ++;
  }

  protected function setBlog() {
    if ( is_multisite() ) {
      switch_to_blog( $this->siteId );
    }
  }

  protected function restoreBlog() {
    if ( is_multisite() ) {
      restore_current_blog();
    }
  }
}
