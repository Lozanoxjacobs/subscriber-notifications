<?php
/**
 * Seed aged email log rows for testing Settings > Data purge.
 *
 * wp eval-file wp-content/plugins/subscriber-notifications/tests/integration/seed-old-logs.php
 *
 * @package SubscriberNotifications
 */

if (!defined('ABSPATH')) {
    exit(1);
}

global $wpdb;

$database   = new SubscriberNotifications_Database();
$subscriber = $wpdb->get_row(
    "SELECT id, email FROM {$wpdb->prefix}subscriber_notifications ORDER BY id ASC LIMIT 1"
);

if (!$subscriber) {
    echo "ERROR: No subscribers found. Run seed-sample-data.php first.\n";
    exit(1);
}

$subscriber_id = (int) $subscriber->id;
$tz            = wp_timezone();

$specs = array(
    array('days' => 45,  'email_type' => 'notification'),
    array('days' => 45,  'email_type' => 'test'),
    array('days' => 100, 'email_type' => 'welcome'),
    array('days' => 200, 'email_type' => 'preferences_update'),
    array('days' => 400, 'email_type' => 'notification'),
);

echo "Seeding old logs for subscriber ID {$subscriber_id} ({$subscriber->email})\n";

foreach ($specs as $spec) {
    $sent_date = (new DateTimeImmutable('now', $tz))
        ->modify('-' . (int) $spec['days'] . ' days')
        ->format('Y-m-d H:i:s');

    $log_id = $database->log_email(
        array(
            'subscriber_id' => $subscriber_id,
            'email_type'    => $spec['email_type'],
            'status'        => 'sent',
            'sent_date'     => $sent_date,
        )
    );

    if (!$log_id) {
        echo "FAIL: could not insert {$spec['days']}-day {$spec['email_type']} log\n";
        continue;
    }

    echo "OK log #{$log_id}: {$spec['days']} days old ({$spec['email_type']}) sent {$sent_date}\n";
}

echo "\nPurge match counts:\n";
foreach (array(30, 90, 180, 365) as $days) {
    echo "  older than {$days} days: " . $database->count_logs_older_than($days) . "\n";
}
