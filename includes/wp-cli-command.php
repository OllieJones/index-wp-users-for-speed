<?php
/** @noinspection PhpUnused */

namespace IndexWpUsersForSpeed;

// If WP-CLI is not available, bail.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
  return;
}

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/indexer.php';

/**
 * WP-CLI command for managing user speed indexes.
 *
 * Provides commands to rebuild, remove, and check the status of
 * the plugin's user metadata indexes.
 *
 * ## EXAMPLES
 *
 *     # Rebuild all user indexes.
 *     $ wp index-wp-users-for-speed rebuild
 *     Success: User counts updated.
 *     Success: Editor list updated.
 *     5/5 [============================] 100%
 *     Success: Role metadata indexes rebuilt successfully.
 *
 *     # Check index status.
 *     $ wp iufs status
 *     Meta index roles: available
 *     Completion: 100%
 *     Total users: 12,345
 *     Success: User indexes are complete and available.
 *
 *     # Remove all user indexes.
 *     $ wp iufs remove
 *     3/3 [============================] 100%
 *     Success: All user indexes removed.
 */
class CLI_Command extends \WP_CLI_Command {

  /**
   * Rebuild all user indexes synchronously.
   *
   * Runs the indexing tasks inline rather than via WP-Cron. Shows
   * a progress bar for the role metadata indexing step.
   *
   * ## OPTIONS
   *
   * [--batch-size=<size>]
   * : Number of users to process per batch. Overrides the
   * INDEX_WP_USERS_FOR_SPEED_BATCHSIZE constant.
   *
   * [--chunk-size=<size>]
   * : Number of users to process per database transaction.
   * Overrides the INDEX_WP_USERS_FOR_SPEED_CHUNKSIZE constant.
   *
   * ## EXAMPLES
   *
   *     # Rebuild with default batch and chunk sizes.
   *     $ wp index-wp-users-for-speed rebuild
   *
   *     # Rebuild with custom batch and chunk sizes.
   *     $ wp iufs rebuild --batch-size=1000 --chunk-size=25
   *
   * @param array $args       Positional arguments.
   * @param array $assoc_args Associative arguments (flags).
   * @return void
   */
  public function rebuild( $args, $assoc_args) {
    $batch_size = isset( $assoc_args['batch-size'] ) ? absint( $assoc_args['batch-size'] ) : 0;
    $chunk_size = isset( $assoc_args['chunk-size'] ) ? absint( $assoc_args['chunk-size'] ) : 0;

    WP_CLI::log( __( 'Starting index rebuild...', 'index-wp-users-for-speed' ) );

    // Step 1: Count users by role.
    WP_CLI::log( __( 'Counting users by role...', 'index-wp-users-for-speed' ) );
    $task = new CountUsers();
    $task->init();
    $task->doChunk();
    WP_CLI::success( __( 'User counts updated.', 'index-wp-users-for-speed' ) );

    // Step 2: Retrieve editor list.
    WP_CLI::log( __( 'Retrieving editor list...', 'index-wp-users-for-speed' ) );
    $task = new GetEditors();
    $task->init();
    $task->doChunk();
    WP_CLI::success( __( 'Editor list updated.', 'index-wp-users-for-speed' ) );

    // Step 3: Populate role metadata indexes.
    WP_CLI::log( __( 'Populating role metadata indexes...', 'index-wp-users-for-speed' ) );
    $task = new PopulateMetaIndexRoles();
    if ( $batch_size > 0 ) {
      $task->batchSize = $batch_size;
    }
    if ( $chunk_size > 0 ) {
      $task->chunkSize = $chunk_size;
    }
    $task->init();

    $estimated_chunks = (int) ceil( $task->maxUserId / $task->batchSize );
    $progress = \WP_CLI\Utils\make_progress_bar(
      sprintf( __( 'Processing batches (est. %d)', 'index-wp-users-for-speed' ), $estimated_chunks ),
      $estimated_chunks
    );

    $done = false;
    while ( ! $done ) {
      $done = $task->doChunk();
      $progress->tick();
    }
    $progress->finish();

    WP_CLI::success( __( 'Role metadata indexes rebuilt successfully.', 'index-wp-users-for-speed' ) );
  }

  /**
   * Remove all user indexes synchronously.
   *
   * Deletes all indexing metadata from wp_usermeta and clears
   * the cached task statuses. Shows a progress bar.
   *
   * ## OPTIONS
   *
   * [--batch-size=<size>]
   * : Number of users to process per batch. Overrides the
   * INDEX_WP_USERS_FOR_SPEED_BATCHSIZE constant.
   *
   * ## EXAMPLES
   *
   *     # Remove all indexes.
   *     $ wp index-wp-users-for-speed remove
   *
   *     # Remove with custom batch size.
   *     $ wp iufs remove --batch-size=1000
   *
   * @param array $args       Positional arguments.
   * @param array $assoc_args Associative arguments (flags).
   * @return void
   */
  public function remove( $args, $assoc_args) {
    $batch_size = isset( $assoc_args['batch-size'] ) ? absint( $assoc_args['batch-size'] ) : 0;

    WP_CLI::log( __( 'Removing user indexes...', 'index-wp-users-for-speed' ) );

    $task = new DepopulateMetaIndexes();
    if ( $batch_size > 0 ) {
      $task->batchSize = $batch_size;
    }
    $task->init();

    $estimated_chunks = (int) ceil( $task->maxUserId / $task->batchSize );
    $progress = \WP_CLI\Utils\make_progress_bar(
      sprintf( __( 'Removing batches (est. %d)', 'index-wp-users-for-speed' ), $estimated_chunks ),
      $estimated_chunks
    );

    $done = false;
    while ( ! $done ) {
      $done = $task->doChunk();
      $progress->tick();
    }
    $progress->finish();

    // Clear all task statuses so the next rebuild starts fresh.
    $task = new DepopulateMetaIndexes();
    $task->clearStatus();
    $task = new CountUsers();
    $task->clearStatus();
    $task = new GetEditors();
    $task->clearStatus();
    $task = new PopulateMetaIndexRoles();
    $task->clearStatus();

    WP_CLI::success( __( 'All user indexes removed.', 'index-wp-users-for-speed' ) );
  }

  /**
   * Show the current status of user indexes.
   *
   * Displays whether the role meta index is available,
   * its completion percentage, and total user count if known.
   *
   * ## EXAMPLES
   *
   *     # Check current index status.
   *     $ wp index-wp-users-for-speed status
   *     Meta index roles: available
   *     Completion: 100%
   *     Total users: 12,345
   *     Success: User indexes are complete and available.
   *
   * @param array $args       Positional arguments.
   * @param array $assoc_args Associative arguments (flags).
   * @return void
   */
  public function status( $args, $assoc_args) {
    $indexer = Indexer::getInstance();

    $available = $indexer->isMetaIndexRoleAvailable();
    $fraction  = $indexer->metaIndexRoleFraction();
    $percent   = round( $fraction * 100, 1 );

    WP_CLI::log(
      sprintf(
        /* translators: %s: availability status (available / not available) */
        __( 'Meta index roles: %s', 'index-wp-users-for-speed' ),
        $available ? __( 'available', 'index-wp-users-for-speed' ) : __( 'not available', 'index-wp-users-for-speed' )
      )
    );
    WP_CLI::log(
      sprintf(
        /* translators: %s: completion percentage */
        __( 'Completion: %s%%', 'index-wp-users-for-speed' ),
        $percent
      )
    );

    $user_counts = $indexer->getUserCounts( false );
    if ( is_array( $user_counts ) && isset( $user_counts['total_users'] ) ) {
      WP_CLI::log(
        sprintf(
          /* translators: %s: total number of users */
          __( 'Total users: %s', 'index-wp-users-for-speed' ),
          number_format_i18n( $user_counts['total_users'] )
        )
      );
    }

    if ( $available && $fraction >= 1.0 ) {
      WP_CLI::success( __( 'User indexes are complete and available.', 'index-wp-users-for-speed' ) );
    } elseif ( $available ) {
      WP_CLI::warning( __( 'User indexes are partially built.', 'index-wp-users-for-speed' ) );
    } else {
      WP_CLI::warning( __( 'User indexes are not available.', 'index-wp-users-for-speed' ) );
    }
  }

}

WP_CLI::add_command( 'index-wp-users-for-speed', __NAMESPACE__ . '\CLI_Command' );
WP_CLI::add_command( 'iufs', __NAMESPACE__ . '\CLI_Command' );
