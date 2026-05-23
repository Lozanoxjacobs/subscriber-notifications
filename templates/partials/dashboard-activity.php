<?php
if (!defined('ABSPATH')) {
    exit;
}

$recent_logs        = $snapshot['recent_logs'] ?? array();
$recent_subscribers = $snapshot['recent_subscribers'] ?? array();
$urls               = $snapshot['urls'] ?? array();

if (!function_exists('sn_dashboard_format_subscriber_date')) {
    /**
     * Format subscriber date_added (UTC) for dashboard display.
     *
     * @param string|null $date_value Raw datetime.
     * @return string
     */
    function sn_dashboard_format_subscriber_date($date_value) {
        if (empty($date_value)) {
            return '—';
        }
        try {
            $utc = new DateTimeZone('UTC');
            $dt  = new DateTime($date_value, $utc);
            $dt->setTimezone(wp_timezone());
            return $dt->format('M j, Y g:i A');
        } catch (Exception $e) {
            return $date_value;
        }
    }
}
?>

<div id="sn-dashboard-activity" class="postbox">
    <div class="postbox-header">
        <h2 class="hndle"><?php esc_html_e('Recent activity', 'subscriber-notifications'); ?></h2>
    </div>
    <div class="inside">
        <h3 class="sn-subheading"><?php esc_html_e('Email logs', 'subscriber-notifications'); ?></h3>
        <?php if (empty($recent_logs)) : ?>
            <p class="description"><?php esc_html_e('No email log entries yet.', 'subscriber-notifications'); ?></p>
        <?php else : ?>
            <table class="widefat striped sn-dashboard-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Subscriber', 'subscriber-notifications'); ?></th>
                        <th><?php esc_html_e('Type', 'subscriber-notifications'); ?></th>
                        <th><?php esc_html_e('Status', 'subscriber-notifications'); ?></th>
                        <th><?php esc_html_e('Sent', 'subscriber-notifications'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html($log->email ?: ($log->name ?: '—')); ?></td>
                            <td><?php echo esc_html(sn_format_email_log_type($log->email_type)); ?></td>
                            <td>
                                <span class="status-<?php echo esc_attr($log->status); ?>">
                                    <?php echo esc_html(ucfirst($log->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(sn_format_log_date_utc($log->sent_date)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3 class="sn-subheading"><?php esc_html_e('New subscribers', 'subscriber-notifications'); ?></h3>
        <?php if (empty($recent_subscribers)) : ?>
            <p class="description"><?php esc_html_e('No subscribers yet.', 'subscriber-notifications'); ?></p>
        <?php else : ?>
            <table class="widefat striped sn-dashboard-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'subscriber-notifications'); ?></th>
                        <th><?php esc_html_e('Email', 'subscriber-notifications'); ?></th>
                        <th><?php esc_html_e('Frequency', 'subscriber-notifications'); ?></th>
                        <th><?php esc_html_e('Added', 'subscriber-notifications'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_subscribers as $sub) : ?>
                        <tr>
                            <td><?php echo esc_html($sub->name); ?></td>
                            <td><?php echo esc_html($sub->email); ?></td>
                            <td><?php echo esc_html(ucfirst($sub->frequency)); ?></td>
                            <td><?php echo esc_html(sn_dashboard_format_subscriber_date($sub->date_added)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p>
            <a href="<?php echo esc_url($urls['logs'] ?? '#'); ?>" class="button"><?php esc_html_e('All logs', 'subscriber-notifications'); ?></a>
            <a href="<?php echo esc_url($urls['subscribers'] ?? '#'); ?>" class="button"><?php esc_html_e('All subscribers', 'subscriber-notifications'); ?></a>
        </p>
    </div>
</div>
