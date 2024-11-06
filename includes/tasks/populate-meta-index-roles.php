<?php /** @noinspection PhpUnused */

namespace IndexWpUsersForSpeed;

/**
 * Task to populate meta_key = 'whatever_role_administrator, etc.
 */
class PopulateMetaIndexRoles extends Task {

  public $batchSize;
  public $chunkSize;
  public $currentStart = - 1;
  public $roles;
  public $maxUserId;

  /**
   * @param int $batchSize number of users per batch (per chunk)
   * @param int $chunkSize number of users per transaction within each batch.
   * @param int|null $siteId Site id for task.
   * @param int $timeout Runtime limit of task. Default = no limit
   */
  public function __construct(
    $batchSize = INDEX_WP_USERS_FOR_SPEED_BATCHSIZE,
    $chunkSize = INDEX_WP_USERS_FOR_SPEED_CHUNKSIZE,
    $siteId = null, $timeout = 0
  ) {

    parent::__construct( $siteId, $timeout );

    $this->batchSize = $batchSize;
    $this->chunkSize = $chunkSize;
    $this->roles     = [];
  }

  public function init() {
    parent::init();
    $indexer = Indexer::getInstance();
    $this->setBlog();
    $this->maxUserId = $indexer->getMaxUserId();
    $roles           = wp_roles();
    $roles           = $roles->get_names();
    foreach ( $roles as $role => $name ) {
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

    $queries    = $this->makeIndexerQueries( $this->roles );
    $currentEnd = $this->currentStart + $this->batchSize;
    $transStart = $this->currentStart;

    $done = false;

    while ( $transStart < $currentEnd ) {

      $this->doQuery( 'BEGIN' );
      $transEnd = min( $transStart + $this->chunkSize, $currentEnd );
      $query    = "SELECT COUNT(*) FROM $wpdb->usermeta a WHERE a.meta_key = %s AND a.user_id >= %d AND a.user_id < %d ORDER BY a.user_id FOR UPDATE";
      $q        = $wpdb->prepare( $query, $wpdb->prefix . 'capabilities', $transStart, $transEnd );
      $this->doQuery( $q );

      foreach ( $queries as $query ) {
        $query .= ' WHERE a.user_id >= %d AND a.user_id < %d';
        $q     = $wpdb->prepare( $query, $transStart, $transEnd );
        $this->doQuery( $q );
      }
      $this->doQuery( 'COMMIT' );
      $transStart         = $transEnd;
      $this->currentStart = $transEnd;

    }

    $done                   = $this->currentStart >= $this->maxUserId;
    $this->fractionComplete = min( 1, $this->currentStart / $this->maxUserId );

    $this->setStatus( null, null, ! $done, $this->fractionComplete );
    /* update table stats at the end of the indexing */
    if ( $done ) {
      $this->doQuery( "ANALYZE TABLE $wpdb->usermeta;" );
    }

    $this->endChunk();

    return $done;
  }

  private function makeIndexerQueries( $roles ) {
    global $wpdb;

    $results = [];

    $prefix          = $wpdb->prefix . INDEX_WP_USERS_FOR_SPEED_KEY_PREFIX . 'r:';
    $capabilitiesKey = $wpdb->prefix . 'capabilities';
    $insertUnions    = [];
    $deleteUnions    = [];
    /* This one finds the role index metakeys to insert into usermeta. This is idempotent. */
    $insertTemplate = /** @lang text */
      "SELECT a.user_id, %s meta_key
         FROM $wpdb->usermeta a
         LEFT JOIN $wpdb->usermeta b
                ON a.user_id = b.user_id 
               AND b.meta_key = %s
        WHERE a.meta_key = %s
          AND a.meta_value LIKE CONCAT('%%', %s, '%%')
          AND b.user_id IS NULL";
    /* This one finds the usermeta rows with wrong capabilities, to delete.
     * We want these delete and insert operations to be idempotent,
     * doing nothing if the proper row is already present.
     * Hence the antijoins (LEFT JOIN ... IS NULL). */
    $deleteTemplate = /** @lang text */
      "SELECT a.user_id, a.umeta_id 
         FROM $wpdb->usermeta a 
         LEFT JOIN $wpdb->usermeta b
                ON a.user_id = b.user_id 
               AND b.meta_key = %s
               AND b.meta_value LIKE CONCAT('%%', %s, '%%')
        WHERE a.meta_key = %s
          AND b.umeta_id IS NULL";

    foreach ( $roles as $role ) {
      $prefixedRole   = $prefix . $role;
      $insertUnions[] = $wpdb->prepare( $insertTemplate, $prefixedRole, $prefixedRole, $capabilitiesKey, $wpdb->esc_like( $role ) );
      $deleteUnions[] = $wpdb->prepare( $deleteTemplate, $capabilitiesKey, $wpdb->esc_like( $role ), $prefixedRole );
    }

    $union = implode( ' UNION ALL ', $deleteUnions );
    /** @noinspection SqlResolve */
    $query     = "DELETE a FROM $wpdb->usermeta a JOIN ($union) b ON a.umeta_id = b.umeta_id";
    $results[] = $query;

    $union = implode( ' UNION ', $insertUnions );
    /** @noinspection SqlResolve */
    $query     = "INSERT INTO $wpdb->usermeta (user_id, meta_key) SELECT user_id, meta_key FROM ($union) a";
    $results[] = $query;

    return $results;
  }

  public function getResult() {
    return null;
  }

}
