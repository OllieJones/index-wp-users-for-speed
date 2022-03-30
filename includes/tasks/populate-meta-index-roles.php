<?php /** @noinspection PhpUnused */

namespace IndexWpUsersForSpeed;


/**
 * Task to populate meta_key = 'whatever_role_administrator, etc.
 */
class PopulateMetaIndexRoles extends Task {

  public $batchSize;
  public $currentStart = - 1;
  public $roles;
  public $maxUserId;
  public $phase = 0;

  /**
   * @param int $batchSize number of users per batch (per chunk)
   * @param int|null $siteId Site id for task.
   * @param int $timeout Runtime limit of task. Default = no limit
   */
  public function __construct( $batchSize = INDEX_WP_USERS_FOR_SPEED_BATCHSIZE, $siteId = null, $timeout = 0 ) {

    parent::__construct( $siteId, $timeout );

    $this->batchSize = $batchSize;
    $this->roles     = [];
  }

  public function init() {
    parent::init();
    $indexer = Indexer::getInstance();
    $this->setBlog();
    $this->maxUserId = $indexer->getMaxUserId();
    $this->restoreBlog();
  }

  /** Do a chunk of meta index insertion for roles.
   * @return boolean  done When this is false, schedule another chunk.
   */
  public function doChunk() {
    global $wpdb;
    $this->startChunk();

    /* First phase: Collect any extra capabilities from usermeta. */
    if ( $this->phase === 0 ) {
      $currentEnd = $this->currentStart + $this->batchSize;
      $this->log( "phase $this->phase  start $this->currentStart  end $currentEnd" );
      $queryTemplate = /** @lang text */
        'SELECT DISTINCT meta_value caps FROM %1$s WHERE meta_key = \'%2$s\' AND user_id >= %3$d AND user_id < %4$d';
      $query         = sprintf( $queryTemplate, $wpdb->usermeta, $wpdb->prefix . 'capabilities', $this->currentStart, $currentEnd );
      $results       = $wpdb->get_results( $query );
      foreach ( $results as $result ) {
        $caps = unserialize( $result->caps );
        foreach ( $caps as $cap => $val ) {
          if ( $val ) {
            if ( ! in_array( $cap, $this->roles ) ) {
              $this->roles[] = $cap;
            }
          }
        }
      }
      $this->currentStart = $currentEnd;
      if ( $this->currentStart >= $this->maxUserId ) {
        $this->currentStart = - 1;
        $this->phase        = 1;
        $this->log( 'phase 0 complete' );
      }

      $this->fractionComplete = max( 0, min( 1, $this->currentStart / $this->maxUserId ) * 0.5 );
      $this->setStatus( null, null, true, $this->fractionComplete );

      $this->endChunk();

      return false;
    }

    /* Second phase: set up the usermeta index entries */
    if ( $this->phase === 1 ) {
      $queries    = $this->makeIndexerQueries( $this->roles );
      $currentEnd = $this->currentStart + $this->batchSize;

      foreach ( $queries as $query ) {
        $query .= ' WHERE a.user_id >= %d AND a.user_id < %d';
        $q     = $wpdb->prepare( $query, $this->currentStart, $currentEnd );

        $wpdb->query( $q );
      }
      $this->currentStart = $currentEnd;

      $done                   = $this->currentStart >= $this->maxUserId;
      $this->fractionComplete = max( 0, 0.5 + min( 1, $this->currentStart / $this->maxUserId ) * 0.5 );

      $this->setStatus( null, null, ! $done, $this->fractionComplete );

      $this->endChunk();

      return $done;
    }

    $this->log( 'unknown phase ' . $this->phase );

    return true;
  }

  private function makeIndexerQueries( $roles ) {
    global $wpdb;

    $results = [];

    $prefix          = $wpdb->prefix . INDEX_WP_USERS_FOR_SPEED_KEY_PREFIX . 'r:';
    $capabilitiesKey = $wpdb->prefix . 'capabilities';
    $insertUnions    = [];
    $deleteUnions    = [];
    /* This one finds the usermeta rows with wrong capabilities, to delete.
     * We want these delete and insert operations to be idempotent,
     * doing nothing if the proper row is already present.
     * Hence the antijoins (LEFT JOIN ... IS NULL). */
    $deleteTemplate = /** @lang text */
      'SELECT a.user_id, a.umeta_id 
         FROM %2$s a 
         LEFT JOIN %2$s b
                ON a.user_id = b.user_id 
               AND b.meta_key = \'%3$s\'
               AND b.meta_value LIKE \'%%"%4$s"%%\'
        WHERE a.meta_key = \'%1$s\'
          AND b.umeta_id IS NULL';

    /* This one finds the role index metakeys to insert into usermeta. This is idempotent. */
    $insertTemplate = /** @lang text */
      'SELECT a.user_id, \'%1$s\' meta_key
         FROM %2$s a
         LEFT JOIN %2$s b
                ON a.user_id = b.user_id 
               AND b.meta_key = \'%1$s\'
        WHERE a.meta_key = \'%3$s\'
          AND a.meta_value LIKE \'%%"%4$s"%%\'
          AND b.user_id IS NULL';
    foreach ( $roles as $role ) {
      $escapedRole    = str_replace( '%', '\\%', $role );
      $escapedRole    = str_replace( '_', '\\_', $escapedRole );
      $insertUnions[] = sprintf( $insertTemplate, $prefix . $role, $wpdb->usermeta, $capabilitiesKey, $escapedRole );
      $deleteUnions[] = sprintf( $deleteTemplate, $prefix . $role, $wpdb->usermeta, $capabilitiesKey, $escapedRole );
    }

    $deleteQueryTemplate = /** @lang text */
      'DELETE a FROM %1$s a JOIN (%2$s) b ON a.umeta_id = b.umeta_id';
    $union               = implode( ' UNION ALL ', $deleteUnions );
    $results[]           = sprintf( $deleteQueryTemplate, $wpdb->usermeta, $union );

    $insertQueryTemplate = /** @lang text */
      'INSERT INTO %1$s (user_id, meta_key) SELECT user_id, meta_key FROM (%2$s) a';
    $union               = implode( ' UNION ', $insertUnions );
    $results[]           = sprintf( $insertQueryTemplate, $wpdb->usermeta, $union );

    return $results;
  }

  public function getResult() {
    return null;
  }

  /** @noinspection SqlNoDataSourceInspection */

  public function reset() {
    //TODO
  }
}


