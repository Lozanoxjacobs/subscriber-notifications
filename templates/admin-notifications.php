<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var SubscriberNotifications_Notifications_List_Table $list_table */
?>
<div class="wrap subscriber-notifications-notifications">
    <h1 class="wp-heading-inline"><?php esc_html_e('Notifications', 'subscriber-notifications'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=subscriber-notifications-create')); ?>" class="page-title-action">
        <?php esc_html_e('Add New', 'subscriber-notifications'); ?>
    </a>
    <hr class="wp-header-end">

    <form method="get">
        <input type="hidden" name="page" value="subscriber-notifications-notifications">
        <?php
        $list_table->search_box(__('Search Notifications', 'subscriber-notifications'), 'notification');
        $list_table->display();
        ?>
    </form>
</div>

<div id="notification-preview-modal" class="notification-modal">
    <div class="notification-modal-content">
        <div class="notification-modal-header">
            <h2><?php esc_html_e('Notification Preview', 'subscriber-notifications'); ?></h2>
            <span class="notification-modal-close">&times;</span>
        </div>
        <div class="notification-modal-body">
            <div id="notification-preview-content"></div>
        </div>
    </div>
</div>
