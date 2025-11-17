<?php /** @noinspection PhpUnused */

namespace IndexWpUsersForSpeed;

/**
 * Task to erase everything, in chunks.
 */
class DepopulateMetaIndexes extends Task {

  public $batchSize;
  public $currentStart = - 1;
  public $maxUserId;
  public $roles = [];

  /**
   * @param int|null $siteId Site id for task.
   * @param int $timeout Runtime limit of task. Default = no limit
   */
  public function __construct( $siteId = null, $timeout = 0 ) {

    parent::__construct( $siteId, $timeout );

    $indexer = Indexer::getInstance();

    $this->batchSize = defined( 'INDEX_WP_USERS_FOR_SPEED_BATCHSIZE' )
      ? intval( INDEX_WP_USERS_FOR_SPEED_BATCHSIZE )
      : intval( INDEX_WP_USERS_FOR_SPEED_BATCHSIZE_DEFAULT );

    $this->setBlog();
    $this->maxUserId = $indexer->getMaxUserId();
    $this->restoreBlog();
  }

  public function init() {
    parent::init();
    $this->setBlog();
    $this->setStatus( null, false, true, $this->fractionComplete );
    $this->restoreBlog();
  }

  /** Do a chunk of meta index deletion for roles.
   * @return boolean  done When this is false, schedule another chunk.
   */
  public function doChunk() {
    global $wpdb;
    $this->startChunk();

    $previouslyShowing     = $wpdb->hide_errors();
    $previouslySuppressing = $wpdb->suppress_errors( true );
    $currentStart          = $this->currentStart;
    $indexer               = Indexer::getInstance();
    $currentStart          = $indexer->getNextUserId( $currentStart );
    $currentEnd            = $currentStart + $this->batchSize;
    $keyPrefix             = $wpdb->prefix . INDEX_WP_USERS_FOR_SPEED_KEY_PREFIX;
    $queryTemplate         = /** @lang text */
      "DELETE FROM $wpdb->usermeta WHERE meta_key LIKE CONCAT(%s, '%%') AND user_id >= %d AND user_id < %d";
    $query                 = $wpdb->prepare( $queryTemplate, $wpdb->esc_like( $keyPrefix ), $currentStart, $currentEnd );

    $this->doQuery( $query );

    $this->currentStart = $currentEnd;
    $done               = $this->currentStart >= $this->maxUserId;

    $this->fractionComplete = max( 0, min( 1, $this->currentStart / $this->maxUserId ) );
    $wpdb->suppress_errors( $previouslySuppressing );
    $wpdb->show_errors( $previouslyShowing );

    $this->endChunk();

    return $done;
  }

  public function getResult() {
    return null;
  }

}
