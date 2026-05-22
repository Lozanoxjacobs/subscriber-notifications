<?php
if (!defined('ABSPATH')) {
    exit;
}

$send_queue = $snapshot['send_queue'] ?? array();
$counts     = $send_queue['counts'] ?? array();
$failed     = $send_queue['recent_failed'] ?? array();
?>

<div id="sn-dashboard-send-queue" class="postbox">
    <div class="postbox-header">
        <h2 class="hndle"><?php esc_html_e('Send queue', 'subscriber-notifications'); ?></h2>
    </div>
    <div class="inside">
        <ul class="sn-stat-list sn-stat-list-inline">
            <li>
                <span class="sn-stat-value"><?php echo esc_html(number_format_i18n((int) ($counts['pending'] ?? 0))); ?></span>
                <span class="sn-stat-label"><?php esc_html_e('Pending', 'subscriber-notifications'); ?></span>
            </li>
            <li>
                <span class="sn-stat-value sn-stat-warn"><?php echo esc_html(number_format_i18n((int) ($counts['failed'] ?? 0))); ?></span>
                <span class="sn-stat-label"><?php esc_html_e('Failed', 'subscriber-notifications'); ?></span>
            </li>
            <li>
                <span class="sn-stat-value"><?php echo esc_html(number_format_i18n((int) ($counts['skipped'] ?? 0))); ?></span>
                <span class="sn-stat-label"><?php esc_html_e('Skipped', 'subscriber-notifications'); ?></span>
            </li>
            <li>
                <span class="sn-stat-value"><?php echo esc_html(number_format_i18n((int) ($counts['sent'] ?? 0))); ?></span>
                <span class="sn-stat-label"><?php esc_html_e('Sent (history)', 'subscriber-notifications'); ?></span>
            </li>
        </ul>

        <?php if (!empty($failed)) : ?>
            <h3 class="sn-subheading"><?php esc_html_e('Recent failures', 'subscriber-notifications'); ?></h3>
            <table class="widefat striped sn-dashboard-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Notification', 'subscriber-notifications'); ?></th>
                        <th><?php esc_html_e('Subscriber', 'subscriber-notifications'); ?></th>
                        <th><?php esc_html_e('Error', 'subscriber-notifications'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($failed as $row) : ?>
                        <tr>
                            <td><?php echo esc_html($row->notification_title ?: '#' . (int) $row->notification_id); ?></td>
                            <td><?php echo esc_html($row->subscriber_email ?: '#' . (int) $row->subscriber_id); ?></td>
                            <td>
                                <?php
                                $err = $row->last_error ?: __('Unknown error', 'subscriber-notifications');
                                echo esc_html(wp_trim_words($err, 12, '…'));
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif ((int) ($counts['failed'] ?? 0) === 0) : ?>
            <p class="description"><?php esc_html_e('No failed send-queue rows.', 'subscriber-notifications'); ?></p>
        <?php endif; ?>
    </div>
</div>
