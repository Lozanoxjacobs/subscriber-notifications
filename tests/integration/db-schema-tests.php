<?php
/**
 * Schema integration tests — run via:
 * wp eval-file wp-content/plugins/subscriber-notifications/tests/integration/db-schema-tests.php
 *
 * @package SubscriberNotifications
 */

require_once __DIR__ . '/test-helpers.php';

global $wpdb;

$database = new SubscriberNotifications_Database();

sn_test_assert(
    'A1 schema version is 4',
    get_option('subscriber_notifications_db_version') === SUBSCRIBER_NOTIFICATIONS_DB_VERSION
        && SUBSCRIBER_NOTIFICATIONS_DB_VERSION === '4'
);

$tables = $wpdb->get_col(
    $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'subscriber_%')
);
sn_test_assert('A2 four plugin tables exist', count($tables) === 4);

$logs_table = $wpdb->prefix . 'subscriber_notification_logs';
$index = $wpdb->get_results(
    $wpdb->prepare("SHOW INDEX FROM {$logs_table} WHERE Key_name = %s", 'sent_date')
);
sn_test_assert('A3 sent_date index exists', !empty($index));

$subs_table = $wpdb->prefix . 'subscriber_notifications';
$unique_token = $wpdb->get_row(
    "SHOW INDEX FROM {$subs_table} WHERE Column_name = 'management_token' AND Non_unique = 0"
);
sn_test_assert('A4 unique management_token index exists', !empty($unique_token));

$subscriber_id = $database->add_subscriber(array(
    'name'  => 'Schema Test',
    'email' => 'schema-test-' . wp_generate_password(8, false) . '@example.com',
));
sn_test_assert('A7 add_subscriber returns ID', $subscriber_id > 0);

if ($subscriber_id) {
    $row = $database->get_subscriber((int) $subscriber_id);
    sn_test_assert(
        'A7 date_added is site time',
        $row && sn_test_datetime_near($row->date_added, current_time('mysql'))
    );
}

$log_id = $database->log_email(array(
    'subscriber_id' => (int) $subscriber_id,
    'email_type'    => 'test',
    'status'        => 'sent',
));
if ($log_id) {
    $log = $wpdb->get_row($wpdb->prepare("SELECT sent_date FROM {$logs_table} WHERE id = %d", $log_id));
    sn_test_assert(
        'A7 log sent_date is site time',
        $log && sn_test_datetime_near($log->sent_date, current_time('mysql'))
    );
}

$queue_table = $wpdb->prefix . 'subscriber_notifications_send_queue';
$wpdb->insert(
    $wpdb->prefix . 'subscriber_notifications_queue',
    array(
        'title'            => 'Purge Test',
        'subject'          => 'Subject',
        'content'          => 'Body',
        'frequency_target' => 'daily',
        'status'           => 'pending',
        'created_date'     => current_time('mysql'),
    )
);
$notification_id = (int) $wpdb->insert_id;

$purge_subscribers = array();
foreach (array('sent', 'skipped', 'failed') as $status) {
    $purge_sub_id = $database->add_subscriber(array(
        'name'  => 'Purge ' . $status,
        'email' => 'purge-' . $status . '-' . wp_generate_password(6, false) . '@example.com',
    ));
    $purge_subscribers[] = (int) $purge_sub_id;
    $wpdb->insert(
        $queue_table,
        array(
            'notification_id' => $notification_id,
            'subscriber_id'   => (int) $purge_sub_id,
            'status'          => $status,
            'enqueued_at'     => current_time('mysql'),
        )
    );
}

$database->purge_send_queue_for_notification($notification_id);
$remaining = $wpdb->get_col(
    $wpdb->prepare("SELECT status FROM {$queue_table} WHERE notification_id = %d ORDER BY status", $notification_id)
);
sn_test_assert(
    'A6 purge keeps failed rows only',
    $remaining === array('failed')
);

foreach ($purge_subscribers as $purge_sub_id) {
    $database->delete_subscriber($purge_sub_id);
}

$wpdb->insert(
    $queue_table,
    array(
        'notification_id' => $notification_id,
        'subscriber_id'   => (int) $subscriber_id,
        'status'          => 'pending',
        'enqueued_at'     => current_time('mysql'),
    )
);

$database->delete_subscriber((int) $subscriber_id);
$orphan_logs = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$logs_table} WHERE subscriber_id = %d",
    $subscriber_id
));
$orphan_queue = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$queue_table} WHERE subscriber_id = %d",
    $subscriber_id
));
$subscriber_gone = $database->get_subscriber((int) $subscriber_id);
sn_test_assert('A5 cascade delete removes subscriber', !$subscriber_gone);
sn_test_assert('A5 cascade delete removes logs', $orphan_logs === 0);
sn_test_assert('A5 cascade delete removes send-queue rows', $orphan_queue === 0);

$wpdb->delete($queue_table, array('notification_id' => $notification_id), array('%d'));
$wpdb->delete($wpdb->prefix . 'subscriber_notifications_queue', array('id' => $notification_id), array('%d'));

$database->create_tables();
sn_test_assert(
    'A8 dbDelta idempotent',
    get_option('subscriber_notifications_db_version') === '4'
);

$create_checks = array(
    $subs_table,
    $logs_table,
    $wpdb->prefix . 'subscriber_notifications_queue',
    $queue_table,
);
$no_mysql_defaults = true;
foreach ($create_checks as $table) {
    $ddl = $wpdb->get_row("SHOW CREATE TABLE {$table}", ARRAY_N);
    if (!$ddl || stripos($ddl[1], 'DEFAULT CURRENT_TIMESTAMP') !== false) {
        $no_mysql_defaults = false;
        break;
    }
}
sn_test_assert('A9 no DEFAULT CURRENT_TIMESTAMP on datetime columns', $no_mysql_defaults);

sn_test_finish();
