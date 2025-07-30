<?php

/**
 * Provide an admin area view for the plugin
 *
 * This file is used to present the admin-facing aspects of the plugin.
 *
 * @link       https://github.com/OllieJones
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/admin/views
 */
namespace IndexWpUsersForSpeed;

use IndexWpUsersForSpeed\Indexer;

/** @noinspection HtmlUnknownTarget */
$hyperlink  = '<a href="%s" target="_blank">%s</a>';
$supportUrl = "https://github.com/OllieJones/index-wp-users-for-speed/issues";
$reviewUrl  = "https://wordpress.org/support/plugin/index-wp-users-for-speed/reviews/";
$clickHere  = __( 'click here', 'index-wp-users-for-speed' );
$support    = sprintf( $hyperlink, $supportUrl, $clickHere );
$review     = sprintf( $hyperlink, $reviewUrl, $clickHere );
/* translators: 1: embeds "For help please ..."  2: hyperlink to review page on wp.org */
$supportString = '<p>' . __( 'For support please %1$s.  Please %2$s to rate this plugin. Your feedback helps make it better, faster, and more useful.', 'index-wp-users-for-speed' ) . '</p>';
$supportString = sprintf( $supportString, $support, $review );

settings_errors( $this->options_name );
?>

<div class="wrap index-users">
 <h2 class="wp-heading-inline"><?php echo get_admin_page_title(); ?></h2>

 <p><?php echo $supportString ?></p>
 <p>
  <span><?php esc_html_e( 'Approximate number of users on this entire site', 'index-wp-users-for-speed' ) ?>: </span>
  <span><?php echo esc_html( number_format_i18n( Indexer::getNetworkUserCount(), 0 ) ) ?></span>
 </p>
 <!--suppress HtmlUnknownTarget -->
 <form id="index-users-form" method="post" action="options.php">
  <?php
  settings_fields( $this->options_name );
  do_settings_sections( $this->plugin_name );
  submit_button();
  ?>
 </form>
</div>
