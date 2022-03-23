<?php /** @noinspection PhpUnused */

namespace IndexWpUsersForSpeed;


/**
 * Task to erase everything, in chunks.
 */
class DepopulateMetaIndexes extends Task {

  public $batchSize;
  public $currentStart = - 1;
  public $maxUserId;
  public $oneOffsNeedDoing = true;
  public $roles = [];

  /**
   * @param int $batchSize number of users per batch (per chunk)
   * @param int|null $siteId Site id for task.
   * @param int $timeout Runtime limit of task. Default = no limit
   */
  public function __construct( $batchSize = INDEX_WP_USERS_FOR_SPEED_BATCHSIZE, $siteId = null, $timeout = 0 ) {

    parent::__construct( $siteId, $timeout );

    $indexer = Indexer::getInstance();

    $this->batchSize = $batchSize;
    $this->setBlog();
    $this->maxUserId = max( 1, $indexer->getMaxUserId() + 1 );
    $this->restoreBlog();
  }

  public function init() {
    parent::init();
    $indexer = Indexer::getInstance();
    $this->setBlog();
    $this->setStatus( [], false, $this->fractionComplete );
    $this->maxUserId = max( 1, $indexer->getMaxUserId() + 1 );
    foreach ( wp_roles()->get_names() as $role => $name ) {
      $this->roles[] = $role;
    }
    $this->restoreBlog();
  }

  /** Do a chunk of meta index insertion for roles.
   * @return boolean  done When this is false, schedule another chunk.
   */
  public function doChunk() {
    global $wpdb;
    $this->startChunk();

    if ( $this->oneOffsNeedDoing ) {
      delete_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "user_counts" );
      delete_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . "editors" );
      $this->oneOffsNeedDoing = false;
    }

    $currentEnd    = $this->currentStart + $this->batchSize;
    $queryTemplate = /** @lang text */
      'DELETE FROM %1$s WHERE meta_key LIKE \'%2$s%\' AND user_id >= %3$d AND user_id < %4$d';
    $query         = sprintf( $queryTemplate, $wpdb->usermeta, $wpdb->prefix . INDEX_WP_USERS_FOR_SPEED_PREFIX, $this->currentStart, $currentEnd );
    $wpdb->query( $query );
    $this->currentStart = $currentEnd;
    $done               = ! $this->needsDoing();

    $this->fractionComplete = max( 0, min( 1, $this->currentStart / $this->maxUserId ) * 0.5 );
    $this->endChunk();

    return $done;


  }

  public function needsDoing() {
    return ( $this->currentStart < $this->maxUserId );
  }


  public function getResult() {
    return null;
  }

  /** @noinspection SqlNoDataSourceInspection */

  public function reset() {
    //TODO
  }
}


