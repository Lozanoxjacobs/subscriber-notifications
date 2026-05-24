<?php
/**
 * Admin dashboard template.
 *
 * @var array $snapshot Dashboard data from SubscriberNotifications_Dashboard::get_snapshot().
 */

if (!defined('ABSPATH')) {
    exit;
}

$snapshot     = isset($snapshot) && is_array($snapshot) ? $snapshot : array();
$partial_dir  = SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/partials/';
$create_url   = $snapshot['urls']['create'] ?? admin_url('admin.php?page=subscriber-notifications-create');
?>

<div class="wrap subscriber-notifications-dashboard-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Dashboard', 'subscriber-notifications'); ?></h1>
    <a href="<?php echo esc_url($create_url); ?>" class="page-title-action">
        <?php esc_html_e('Add New Notification', 'subscriber-notifications'); ?>
    </a>
    <hr class="wp-header-end">

    <div class="subscriber-notifications-dashboard">
        <div class="sn-dashboard-columns">
            <div class="sn-dashboard-column sn-dashboard-column-primary">
                <?php include $partial_dir . 'dashboard-content-types.php'; ?>
                <?php include $partial_dir . 'dashboard-schedule.php'; ?>
                <?php include $partial_dir . 'dashboard-delivery.php'; ?>
                <?php include $partial_dir . 'dashboard-send-queue.php'; ?>
                <?php include $partial_dir . 'dashboard-upcoming.php'; ?>
                <?php include $partial_dir . 'dashboard-activity.php'; ?>
            </div>
            <div class="sn-dashboard-column sn-dashboard-column-secondary">
                <?php include $partial_dir . 'dashboard-health.php'; ?>
                <?php include $partial_dir . 'dashboard-notifications.php'; ?>
                <?php include $partial_dir . 'dashboard-subscribers.php'; ?>
                <?php include $partial_dir . 'dashboard-mail.php'; ?>
                <?php include $partial_dir . 'dashboard-quick-links.php'; ?>
            </div>
        </div>

        <p class="sn-dashboard-footer-meta description">
            <?php
            printf(
                /* translators: 1: plugin version, 2: site timezone */
                esc_html__('Subscriber Notifications %1$s · Site timezone: %2$s', 'subscriber-notifications'),
                esc_html($snapshot['plugin_version'] ?? ''),
                esc_html($snapshot['site_timezone'] ?? '')
            );
            ?>
        </p>
    </div>
</div>
