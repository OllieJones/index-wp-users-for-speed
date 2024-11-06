<?php /** @noinspection SpellCheckingInspection */

/** @noinspection PhpUnused */

namespace IndexWpUsersForSpeed;

use WP_User_Query;

/**
 * Task to count users by role.
 */
class GetEditors extends Task {

  /**
   * @param int|null $siteId Site id for task.
   * @param int $timeout Runtime limit of task. Default = no limit
   */
  public function __construct( $siteId = null, $timeout = 0 ) {

    parent::__construct( $siteId, $timeout );
    /* This may not be initialized in multisite. */
    global $wp_roles;
    $wp_roles = $wp_roles ?: new \WP_Roles();
  }

  /** Retrieve the user counts and update the option
   * The output of this is used to limit the number of users handled by
   * user-lookup queries when there are a small number, or when
   * the role-based indexing isn't ready yet.
   *
   * @return boolean  done When this is false, schedule another chunk.
   */
  public function doChunk() {

    $this->startChunk();
    $userCount = get_option( INDEX_WP_USERS_FOR_SPEED_PREFIX . 'options' )['quickedit_threshold_limit'];
    $editors   = [];

    $params = [
      'blog_id'        => $this->siteId,
      'capability__in' => [ 'edit_posts', 'edit_pages' ],
      'fields'         => 'ID',
      'orderby'        => 'ID',
      'number'         => $userCount + 1,
      'count_total'    => false,
    ];

    global $wpdb;
    $this->log( $wpdb->options );
    $this->log( serialize( $params ) );
    $userQuery = new WP_User_Query( $params );
    $qresults  = $userQuery->get_results();
    if ( ! empty ( $qresults ) ) {
      $editors = array_map( 'intval', array_filter( $qresults ) );
      $this->log( serialize( $editors ) );
    }

    $this->setStatus( [ 'editors' => $editors ], true, false, 1 );

    $this->endChunk();

    /* done in one chunk */

    return true;
  }

  public function getResult() {
    return get_option( INDEX_WP_USERS_FOR_SPEED_PREFIX_TASK . "editors" );
  }

  public function reset() {
    delete_option( INDEX_WP_USERS_FOR_SPEED_PREFIX_TASK . "editors" );
  }

}
