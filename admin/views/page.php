<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://github.com/OllieJones
 * @since      1.0.0
 *
 * @package    Index_Wp_Users_For_Speed
 * @subpackage Index_Wp_Users_For_Speed/admin/views
 */

settings_errors($this->options_name);
?>

<div class="wrap index-users">
    <h2 class="wp-heading-inline"><?= get_admin_page_title(); ?></h2>
    <p>
        <span><?= esc_html__( 'Approximate number of users on this entire site', 'index-wp-users-for-speed' ) ?>: </span>
        <span><?= number_format_i18n( $this->indexer->getNetworkUserCount(), 0 ) ?></span>
    </p>
    <form id="index-users-form" method="post" action="options.php">
      <?php
      settings_fields( $this->options_name );
      do_settings_sections( $this->plugin_name );
      submit_button( 'Save Changes', 'primary' );
      ?>
    </form>
</div>


