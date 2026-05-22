<?php
if (!defined('ABSPATH')) {
    exit;
}

$notifs = $snapshot['notifications'] ?? array();
$urls   = $snapshot['urls'] ?? array();
?>

<div id="sn-dashboard-notifications" class="postbox">
    <div class="postbox-header">
        <h2 class="hndle"><?php esc_html_e('Notifications', 'subscriber-notifications'); ?></h2>
    </div>
    <div class="inside">
        <ul class="sn-stat-list sn-stat-list-compact">
            <li>
                <span class="sn-stat-value"><?php echo esc_html(number_format_i18n((int) ($notifs['pending'] ?? 0))); ?></span>
                <span class="sn-stat-label">
                    <a href="<?php echo esc_url($urls['notifications_pending'] ?? '#'); ?>"><?php esc_html_e('Pending', 'subscriber-notifications'); ?></a>
                </span>
            </li>
            <li>
                <span class="sn-stat-value"><?php echo esc_html(number_format_i18n((int) ($notifs['active_recurring'] ?? 0))); ?></span>
                <span class="sn-stat-label">
                    <a href="<?php echo esc_url($urls['notifications_recurring'] ?? '#'); ?>"><?php esc_html_e('Active recurring', 'subscriber-notifications'); ?></a>
                </span>
            </li>
            <li>
                <span class="sn-stat-value"><?php echo esc_html(number_format_i18n((int) ($notifs['sent'] ?? 0))); ?></span>
                <span class="sn-stat-label"><?php esc_html_e('Sent', 'subscriber-notifications'); ?></span>
            </li>
            <li>
                <span class="sn-stat-value"><?php echo esc_html(number_format_i18n((int) ($notifs['cancelled'] ?? 0))); ?></span>
                <span class="sn-stat-label"><?php esc_html_e('Cancelled', 'subscriber-notifications'); ?></span>
            </li>
        </ul>
        <p>
            <a href="<?php echo esc_url($urls['notifications'] ?? '#'); ?>" class="button">
                <?php esc_html_e('Manage notifications', 'subscriber-notifications'); ?>
            </a>
        </p>
    </div>
</div>
