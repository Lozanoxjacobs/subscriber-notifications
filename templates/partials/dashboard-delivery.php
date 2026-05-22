<?php
if (!defined('ABSPATH')) {
    exit;
}

$analytics = $snapshot['analytics'] ?? array();
$urls      = $snapshot['urls'] ?? array();
$period    = $snapshot['analytics_period'] ?? '30days';
$base_url  = $urls['dashboard'] ?? admin_url('admin.php?page=subscriber-notifications');
$periods   = SubscriberNotifications_Dashboard::get_analytics_periods();
?>

<div id="sn-dashboard-delivery" class="postbox">
    <div class="postbox-header">
        <h2 class="hndle"><?php esc_html_e('Email delivery', 'subscriber-notifications'); ?></h2>
    </div>
    <div class="inside">
        <div class="nav-tab-wrapper sn-dashboard-period-nav" role="tablist" aria-label="<?php esc_attr_e('Analytics period', 'subscriber-notifications'); ?>">
            <?php foreach ($periods as $key) : ?>
                <?php
                $url    = add_query_arg('period', $key, $base_url);
                $active = ($key === $period);
                ?>
                <a href="<?php echo esc_url($url); ?>" class="nav-tab<?php echo $active ? ' nav-tab-active' : ''; ?>">
                    <?php echo esc_html(SubscriberNotifications_Dashboard::get_analytics_period_label($key)); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <ul class="sn-stat-list sn-stat-list-inline">
            <li>
                <span class="sn-stat-value"><?php echo esc_html(number_format_i18n((int) ($analytics['sent_emails'] ?? 0))); ?></span>
                <span class="sn-stat-label"><?php esc_html_e('Sent', 'subscriber-notifications'); ?></span>
            </li>
            <li>
                <span class="sn-stat-value sn-stat-warn"><?php echo esc_html(number_format_i18n((int) ($analytics['failed_emails'] ?? 0))); ?></span>
                <span class="sn-stat-label">
                    <a href="<?php echo esc_url($urls['logs_failed'] ?? '#'); ?>"><?php esc_html_e('Failed', 'subscriber-notifications'); ?></a>
                </span>
            </li>
            <li>
                <span class="sn-stat-value"><?php echo esc_html(number_format_i18n((float) ($analytics['open_rate'] ?? 0), 1)); ?>%</span>
                <span class="sn-stat-label"><?php esc_html_e('Open rate', 'subscriber-notifications'); ?></span>
            </li>
            <li>
                <span class="sn-stat-value"><?php echo esc_html(number_format_i18n((float) ($analytics['click_rate'] ?? 0), 1)); ?>%</span>
                <span class="sn-stat-label"><?php esc_html_e('Click rate', 'subscriber-notifications'); ?></span>
            </li>
        </ul>

        <p class="description">
            <?php
            printf(
                /* translators: %s: period label */
                esc_html__('Showing stats for: %s. Rates use unique opens/clicks divided by delivered emails.', 'subscriber-notifications'),
                esc_html($snapshot['analytics_period_label'] ?? '')
            );
            ?>
        </p>
        <p>
            <a href="<?php echo esc_url($urls['logs'] ?? '#'); ?>" class="button">
                <?php esc_html_e('View email logs', 'subscriber-notifications'); ?>
            </a>
        </p>
    </div>
</div>
