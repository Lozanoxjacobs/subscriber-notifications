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
?>

<div class="wrap subscriber-notifications-logs">
    <h1 class="wp-heading-inline"><?php esc_html_e('Email Logs', 'subscriber-notifications'); ?></h1>
    <a href="<?php echo esc_url($sn_logs_export_url); ?>" class="page-title-action">
        <?php esc_html_e('Export Logs', 'subscriber-notifications'); ?>
    </a>
    <hr class="wp-header-end">

    <form method="get">
        <input type="hidden" name="page" value="subscriber-notifications-logs">
        <?php $list_table->display(); ?>
    </form>
</div>
