<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var SubscriberNotifications_Subscribers_List_Table $list_table */
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Subscribers', 'subscriber-notifications'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=subscriber-notifications-import-export#export-subscribers')); ?>" class="page-title-action">
        <?php esc_html_e('Import/Export CSV', 'subscriber-notifications'); ?>
    </a>
    <hr class="wp-header-end">

    <div class="subscriber-notifications-subscribers">
        <form method="get">
            <input type="hidden" name="page" value="subscriber-notifications-subscribers">
            <?php
            $list_table->search_box(__('Search Subscribers', 'subscriber-notifications'), 'subscriber');
            $list_table->display();
            ?>
        </form>
    </div>
</div>
