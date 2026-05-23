<?php
if (!defined('ABSPATH')) {
    exit;
}

/** @var SubscriberNotifications_Logs_List_Table $list_table */

$sn_logs_filter_args = array();
if (!empty($_GET['status'])) {
    $sn_logs_filter_args['status'] = sanitize_text_field(wp_unslash($_GET['status']));
}
if (!empty($_GET['email_type'])) {
    $sn_logs_filter_args['email_type'] = sanitize_key(wp_unslash($_GET['email_type']));
}
if (!empty($_GET['date_from'])) {
    $sn_logs_filter_args['date_from'] = sanitize_text_field(wp_unslash($_GET['date_from']));
}
if (!empty($_GET['date_to'])) {
    $sn_logs_filter_args['date_to'] = sanitize_text_field(wp_unslash($_GET['date_to']));
}
if (!empty($_GET['subscriber_id'])) {
    $sn_logs_filter_args['subscriber_id'] = intval($_GET['subscriber_id']);
}

$sn_logs_export_url = admin_url('admin.php?page=subscriber-notifications-logs&action=export');
foreach ($sn_logs_filter_args as $key => $value) {
    $sn_logs_export_url = add_query_arg($key, $value, $sn_logs_export_url);
}
$sn_logs_export_url = wp_nonce_url($sn_logs_export_url, 'export_logs');

$sn_current_email_type = isset($_GET['email_type']) ? sanitize_key(wp_unslash($_GET['email_type'])) : '';
$sn_email_log_types    = sn_get_email_log_types();
if (!isset($sn_purge_presets)) {
    $sn_purge_presets = array(30, 90, 180, 365);
}
if (!isset($sn_purge_counts)) {
    $sn_purge_counts = array();
    foreach ($sn_purge_presets as $purge_days) {
        $sn_purge_counts[ $purge_days ] = 0;
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Email Logs', 'subscriber-notifications'); ?></h1>
    <a href="<?php echo esc_url($sn_logs_export_url); ?>" class="page-title-action">
        <?php esc_html_e('Export Logs', 'subscriber-notifications'); ?>
    </a>
    <hr class="wp-header-end">

    <div class="logs-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="subscriber-notifications-logs">

            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', 'subscriber-notifications'); ?></option>
                <option value="sent" <?php selected($sn_logs_filter_args['status'] ?? '', 'sent'); ?>><?php esc_html_e('Sent', 'subscriber-notifications'); ?></option>
                <option value="failed" <?php selected($sn_logs_filter_args['status'] ?? '', 'failed'); ?>><?php esc_html_e('Failed', 'subscriber-notifications'); ?></option>
                <option value="pending" <?php selected($sn_logs_filter_args['status'] ?? '', 'pending'); ?>><?php esc_html_e('Pending', 'subscriber-notifications'); ?></option>
            </select>

            <select name="email_type">
                <option value=""><?php esc_html_e('All Email Types', 'subscriber-notifications'); ?></option>
                <?php foreach ($sn_email_log_types as $type_slug => $type_label) : ?>
                    <option value="<?php echo esc_attr($type_slug); ?>" <?php selected($sn_current_email_type, $type_slug); ?>>
                        <?php echo esc_html($type_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date_from" value="<?php echo esc_attr($sn_logs_filter_args['date_from'] ?? ''); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr($sn_logs_filter_args['date_to'] ?? ''); ?>">

            <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'subscriber-notifications'); ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=subscriber-notifications-logs')); ?>" class="button"><?php esc_html_e('Clear Filters', 'subscriber-notifications'); ?></a>
        </form>
    </div>

    <div class="logs-maintenance">
        <h2 class="screen-reader-text"><?php esc_html_e('Log maintenance', 'subscriber-notifications'); ?></h2>
        <form id="sn-purge-logs-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="subscriber_notifications_purge_logs">
            <?php wp_nonce_field('sn_purge_logs', 'sn_purge_logs_nonce'); ?>
            <label for="sn-purge-days"><?php esc_html_e('Purge logs older than', 'subscriber-notifications'); ?></label>
            <select name="purge_days" id="sn-purge-days">
                <?php foreach ($sn_purge_presets as $days) : ?>
                    <option value="<?php echo esc_attr((string) $days); ?>" data-match-count="<?php echo esc_attr((string) (int) ($sn_purge_counts[ $days ] ?? 0)); ?>">
                        <?php
                        echo esc_html(
                            sprintf(
                                _n('%1$d day (%2$d match)', '%1$d days (%2$d match)', $days, 'subscriber-notifications'),
                                $days,
                                (int) ($sn_purge_counts[ $days ] ?? 0)
                            )
                        );
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span id="sn-purge-match-summary" class="description" aria-live="polite"></span>
            <button type="submit" class="button"><?php esc_html_e('Purge', 'subscriber-notifications'); ?></button>
        </form>
    </div>

    <form method="get">
        <input type="hidden" name="page" value="subscriber-notifications-logs">
        <?php foreach ($sn_logs_filter_args as $key => $value) : ?>
            <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>">
        <?php endforeach; ?>
        <?php $list_table->display(); ?>
    </form>
</div>
