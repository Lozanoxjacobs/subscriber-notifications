<?php
/**
 * Post-reinstall verification for B8 — run after wp plugin activate.
 *
 * @package SubscriberNotifications
 */

require_once __DIR__ . '/test-helpers.php';

global $wpdb;

$database = new SubscriberNotifications_Database();

sn_test_assert(
    'B8 schema version is 4 after reinstall',
    get_option('subscriber_notifications_db_version') === '4'
        && SUBSCRIBER_NOTIFICATIONS_DB_VERSION === '4'
);

$tables = $wpdb->get_col(
    $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'subscriber_%')
);
sn_test_assert('B8 four plugin tables recreated', count($tables) === 4);

$logs_table = $wpdb->prefix . 'subscriber_notification_logs';
$sent_date_index = $wpdb->get_results(
    $wpdb->prepare("SHOW INDEX FROM {$logs_table} WHERE Key_name = %s", 'sent_date')
);
sn_test_assert('B8 sent_date index exists after reinstall', !empty($sent_date_index));

$subs_table = $wpdb->prefix . 'subscriber_notifications';
$unique_token = $wpdb->get_row(
    "SHOW INDEX FROM {$subs_table} WHERE Column_name = 'management_token' AND Non_unique = 0"
);
sn_test_assert('B8 unique management_token after reinstall', !empty($unique_token));

$create_checks = array(
    $subs_table,
    $logs_table,
    $wpdb->prefix . 'subscriber_notifications_queue',
    $wpdb->prefix . 'subscriber_notifications_send_queue',
);
$no_mysql_defaults = true;
foreach ($create_checks as $table) {
    $ddl = $wpdb->get_row("SHOW CREATE TABLE {$table}", ARRAY_N);
    if (!$ddl || stripos($ddl[1], 'DEFAULT CURRENT_TIMESTAMP') !== false) {
        $no_mysql_defaults = false;
        break;
    }
}
sn_test_assert('B8 no DEFAULT CURRENT_TIMESTAMP after reinstall', $no_mysql_defaults);

$subscriber_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$subs_table}");
sn_test_assert('B8 subscriber table empty after reinstall', $subscriber_count === 0);

$delete_flag = (int) subscriber_notifications_get_option('delete_data_on_uninstall', 0);
sn_test_assert('B8 delete_data_on_uninstall reset to default', $delete_flag === 0);

$footer = subscriber_notifications_get_option('global_footer', '');
sn_test_assert('B8 default options seeded after reinstall', $footer !== '');

sn_test_finish();
