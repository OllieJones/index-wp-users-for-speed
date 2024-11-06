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
    /* it's possible for the task, persisted in an option, to be gone. */
    if ( $task && method_exists( $task, 'doTaskStep' ) ) {
      $task->doTaskStep();
    } else {
      error_log( 'index_wp_users_for_speed_task: task missing, so cannot run: ' . $taskName );
    }
  } catch ( Exception $ex ) {
    $taskName = ( $task && $task->taskName ) ? $task->taskName : 'persisted ' . $taskName;
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
    $this->taskName  = $reflect->getShortName() . '_' . $siteId;
  }

  public static function restorePersisted( $taskName ) {
    $option = INDEX_WP_USERS_FOR_SPEED_PREFIX_TASK . $taskName;

    return get_option( $option );
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

  abstract public function doChunk();

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
    $jobName = self::toSnake( $this->taskName );
    $option  = INDEX_WP_USERS_FOR_SPEED_PREFIX_TASK . $jobName;
    update_option( $option, $this, false );

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
    $len = strlen( $symbol );
    for ( $i = 0; $i < $len; $i ++ ) {
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

  protected function generateCallTrace() {
    $e     = new Exception();
    $trace = explode( "\n", $e->getTraceAsString() );
    // reverse array to make steps line up chronologically
    $trace = array_reverse( $trace );
    array_shift( $trace ); // remove {main}
    array_pop( $trace ); // remove call to this method
    $result = [];

    foreach ( $trace as $i => $iValue ) {
      $result[] = ( $i + 1 ) . ')' . substr( $iValue, strpos( $iValue, ' ' ) ); // replace '#someNum' with '$i)', set the right ordering
    }

    return "\t" . implode( "\n\t", $result );
  }

  public function log(
    $msg, $time = 0
  ) {
    $words   = [];
    $words[] = 'Task';
    $words[] = $this->taskName;
    $words[] = '(' . $this->siteId . ')';
    $words[] = '#' . $this->useCount;
    $words[] = is_string( $msg ) ? $msg : serialize( $msg );
    if ( $time ) {
      $words[] = 'for time';
      $words[] = date( 'Y-m-d H:i:s', $time );
    }
    $words [] = $this->generateCallTrace();
    $msg      = implode( ' ', $words );
    Indexer::writeLog( $msg );
  }

  /** Update a task's status option..
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
    $jobResultName = INDEX_WP_USERS_FOR_SPEED_PREFIX_TASK . 'result' . self::toSnake( $this->taskName );
    update_option( $jobResultName, $status, false );
  }

  public function getStatus() {
    $jobResultName = INDEX_WP_USERS_FOR_SPEED_PREFIX_TASK . 'result' . self::toSnake( $this->taskName );

    return get_option( $jobResultName );
  }

  protected function clearPersisted() {
    $jobStatusName = self::toSnake( $this->taskName );
    $option        = INDEX_WP_USERS_FOR_SPEED_PREFIX_TASK . $jobStatusName;
    delete_option( $option );
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
    $jobResultName = INDEX_WP_USERS_FOR_SPEED_PREFIX_TASK . 'result' . self::toSnake( $this->taskName );
    delete_option( $jobResultName );
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

  public function setBlog() {
    if ( is_multisite() ) {
      switch_to_blog( $this->siteId );
    }
  }

  public function restoreBlog() {
    if ( is_multisite() ) {
      restore_current_blog();
    }
  }

  /** Do $wpdb->query, retrying if a deadlock is found.
   *
   * @param string $query The duly prepared query
   * @param int $retries The maximum number of times to retry before giving up, default 5.
   * @param float $delay The time, in seconds, to delay after a deadlock failure, default 0.1.
   *
   * @return bool|int|mixed|\mysqli_result|resource|null
   */
  public function doQuery( $query, $retries = 5, $delay = 0.1 ) {
    global $wpdb;
    $result = 0;

    /* deal with potential deadlocks deactivating */
    $retry = $retries;
    while ( $retry > 0 ) {
      $success = true;
      $result  = $wpdb->query( $query );
      if ( $result === false ) {
        $err     = $wpdb->error;
        $message = '';
        $message = is_string( $err ) ? $err : $message;
        $message = is_wp_error( $err ) ? $err->get_error_message() : $message;
        if ( false === stripos( $message, 'Deadlock found' ) ) {
          $success = false;
        }
      }
      if ( $success ) {
        break;
      }
      usleep( $delay * 1000000 );
      $retry --;
      if ( $retry <= 0 ) {
        error_log( "Deadlock after $retries retries: $query" );
      }
    }
    return $result;
  }

}
