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

//http://ubu2010.plumislandmedia.local/wp-admin/tools.php?page=imfs_settings&tab=high_performance_keys

//http://ubu2010.plumislandmedia.local/wp-admin/admin-post.php" "="">
?>

<div class="wrap index-users">
    <h1 class="wp-heading-inline"><?= get_admin_page_title(); ?></h1>
  <?php if ( $this->getMessage() ) {
    include_once 'message.php';
  } ?>
    <h2><?=__('Information', 'index-wp-users-for-speed')?></h2>
    <table class="form-table" role="presentation">
        <tbody>
        <tr>
            <th scope="row"><?=__('Users', 'index-wp-users-for-speed')?></th>
            <td><?=sprintf(__('Approximately %s', 'index-wp-users-for-speed'), number_format_i18n($this->indexer->getNetworkUserCount()))?></td>
        </tr>
        </tbody>
    </table>

    <h2><?=__('Indexing Options', 'index-wp-users-for-speed')?></h2>
    <form id="index-users-form" method="post" action="<?= admin_url( 'admin-post.php' ) ?>">
        <?= wp_nonce_field($this->plugin_name, 'reindex', true, false) ?>
        <input type="hidden" name="action" value="<?= $this->plugin_name ?>-action">
        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row"><?=__('Add Indexes Now', 'index-wp-users-for-speed')?></th>
                <td><input type="submit" class="button button-primary" id="add-button" name="add-button" value="<?=__('Go!', 'index-wp-users-for-speed')?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><?=__('Remove Indexes Now', 'index-wp-users-for-speed')?></th>
                <td><input type="submit" class="button button-primary" id="remove-button" name="remove-button"
                           value="<?=__('Go!', 'index-wp-users-for-speed')?>">
                </td>
            </tr>
            </tbody>
        </table>
    </form>
</div>


