<?php

namespace IndexWpUsersForSpeed;

use WP_Http;

function need_cron() {
  $cronDisabled = defined( 'DISABLE_WP_CRON' ) && true === DISABLE_WP_CRON;
  if ( $cronDisabled ) {
    $needJob = false;
    $jobs    = wp_get_ready_cron_jobs();
    foreach ( $jobs as $time => $job ) {
      if ( array_key_exists( INDEX_WP_USERS_FOR_SPEED_HOOKNAME, $job ) ) {
        $needJob = true;
        break;
      }
    }
    if ( $needJob ) {
      $now  = time();
      $next = get_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . 'next-job' );
      if ( ! $next ) {
        $next = $now + INDEX_WP_USERS_FOR_SPEED_DELAY_CRONKICK;
        set_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . 'next-job', $next, 86400 );
      }
      if ( $now >= $next ) {
        $next = $now + INDEX_WP_USERS_FOR_SPEED_DELAY_CRONKICK;
        set_transient( INDEX_WP_USERS_FOR_SPEED_PREFIX . 'next-job', $next, 86400 );
        add_action( 'shutdown', 'IndexWpUsersForSpeed\kick_cron', 9999, 0 );
      }
    }
  }
}

need_cron();

function kick_cron() {
  if ( wp_doing_cron() ) {
    /* NEVER hit the cron endpoint when doing cron, or you'll break lots of things */
    return;
  }
  $url = get_site_url( null, 'wp-cron.php' );
  $req = new WP_Http();
  $res = $req->get( $url );
}
