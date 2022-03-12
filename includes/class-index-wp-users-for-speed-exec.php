<?php

/**
 * Executor for chunked jobs.
 */
class Index_Wp_Users_For_Speed_Exec {

  private static $queueOptionName;
  /**
   * @var string
   */
  private static $currentOptionName;

  private static $reschedulingHeadway = 10; /* seconds */
  private $query;
  private $chunkSize;
  private $firstIdTag;
  private $lastIdTag;
  private $firstId;
  private $currentId;
  private $startTime;

  public static function start( $query, $chunkSize, $firstIdTag = '%%firstid%%', $lastTag = '%%lastid%%' ) {
    self::staticinit();

    return new self ( $query, $chunkSize, $firstIdTag = '%%firstid%%', $lastTag = '%%lastid%%' );
  }

  static function staticinit() {
    self::$queueOptionName   = INDEX_WP_USERS_FOR_SPEED_PREFIX . '_exec_queue';
    self::$currentOptionName = INDEX_WP_USERS_FOR_SPEED_PREFIX . '_exec_current';

    set_transient( self::$queueOptionName, null, DAY_IN_SECONDS );
    set_transient( self::$currentOptionName, null, DAY_IN_SECONDS );
  }

  protected function __constructor( $query, $chunkSize, $firstId = 0, $firstTag = '%%firstid%%', $lastTag = '%%lastid%%' ) {
    $this->query      = $query;
    $this->chunkSize  = $chunkSize;
    $this->firstIdTag = $firstTag;
    $this->lastIdTag  = $lastTag;
    $this->firstId    = $firstId;
    $this->currentId = $firstId;
    $this->startTime = time();
  }

  /** Onetime setup.
   * @return $this (fluent)
   */
  public function enqueue  () {
    $queue = get_transient (self::$queueOptionName);
    if (! is_array($queue)) {
      $queue = [];
    }
    $queue[] = $this;
    set_transient(self::$queueOptionName, $queue, DAY_IN_SECONDS);
    self::kick ();
    return $this;
  }

  static function kick () {
    $current = get_transient( self::$currentOptionName );
    if ( ! isset( $current ) ) {
      $queue = get_transient( self::$queueOptionName );
      if ( is_array( $queue ) ) {
        $current = array_shift( $queue );
        set_transient( self::$queueOptionName, $queue, DAY_IN_SECONDS );
      }
    }
    $current->doChunk();
    set_transient( self::$currentOptionName, $current, DAY_IN_SECONDS );
    if (wp_next_scheduled())
    wp_schedule_single_event(time() +  self::$reschedulingHeadway, 'index_wp_mysql_for_speed_exec_event', ["id" => "Index_Wp_Users_For_Speed_Exec"]);
  }

  public function run () {
    /* TODO */

  }

}

function index_wp_mysql_for_speed_exec_event (){
  Index_Wp_Users_For_Speed_Exec::kick();
}