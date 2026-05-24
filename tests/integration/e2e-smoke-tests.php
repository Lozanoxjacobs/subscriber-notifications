<?php
/**
 * Smoke/regression tests — run via:
 * wp eval-file wp-content/plugins/subscriber-notifications/tests/integration/e2e-smoke-tests.php
 *
 * @package SubscriberNotifications
 */

require_once __DIR__ . '/test-helpers.php';

global $wpdb;

$database = new SubscriberNotifications_Database();

sn_test_assert(
    'B1 plugin version 3.7.0',
    defined('SUBSCRIBER_NOTIFICATIONS_VERSION') && SUBSCRIBER_NOTIFICATIONS_VERSION === '3.7.0'
);

$footer = subscriber_notifications_get_option('global_footer', '');
sn_test_assert('B2 global footer default populated', $footer !== '');

$today = wp_date('Y-m-d');
$subscriber_id = $database->add_subscriber(array(
    'name'  => 'Filter Test',
    'email' => 'filter-test-' . wp_generate_password(8, false) . '@example.com',
));
$database->log_email(array(
    'subscriber_id' => (int) $subscriber_id,
    'email_type'    => 'test',
    'status'        => 'sent',
    'sent_date'     => current_time('mysql'),
));
$logs = $database->get_logs(array(
    'date_from' => $today,
    'date_to'   => $today,
    'limit'     => 50,
    'offset'    => 0,
));
$found = false;
foreach ($logs as $log) {
    if ((int) $log->subscriber_id === (int) $subscriber_id) {
        $found = true;
        break;
    }
}
sn_test_assert('B3 log filter includes today row', $found);

$old_date = (new DateTimeImmutable('now', wp_timezone()))
    ->modify('-40 days')
    ->format('Y-m-d H:i:s');
$old_log_id = $database->log_email(array(
    'subscriber_id' => (int) $subscriber_id,
    'email_type'    => 'test',
    'status'        => 'sent',
    'sent_date'     => $old_date,
));
$count_before = $database->count_logs_older_than(30);
sn_test_assert('B4 purge count includes old row', $count_before >= 1);
$deleted = $database->delete_logs_older_than(30);
sn_test_assert('B4 purge deletes old rows', $deleted >= 1);
$still_there = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}subscriber_notification_logs WHERE id = %d",
    $old_log_id
));
sn_test_assert('B4 specific old log removed', $still_there === 0);

$database->delete_subscriber((int) $subscriber_id);

$token_subscriber_id = $database->add_subscriber(array(
    'name'  => 'Token Test',
    'email' => 'token-test-' . wp_generate_password(8, false) . '@example.com',
));
$token_row = $database->get_subscriber((int) $token_subscriber_id);
sn_test_assert('B7 token subscriber created', $token_row && !empty($token_row->management_token));

if (!subscriber_notifications_preferences_page_is_configured()) {
    $prefs_page_id = wp_insert_post(
        array(
            'post_title'   => 'Notification Preferences (Test)',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[subscriber_notifications_preferences]',
        )
    );
    if ($prefs_page_id && !is_wp_error($prefs_page_id)) {
        update_option(subscriber_notifications_option_name('preferences_page_id'), (int) $prefs_page_id);
    }
}
sn_test_assert(
    'B7 preferences page configured for manage URLs',
    subscriber_notifications_preferences_page_is_configured()
);

sn_test_finish();
