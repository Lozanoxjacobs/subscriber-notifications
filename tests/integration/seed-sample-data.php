<?php
/**
 * Seed sample subscribers and notifications for manual testing on dev.
 *
 * wp eval-file wp-content/plugins/subscriber-notifications/tests/integration/seed-sample-data.php
 *
 * @package SubscriberNotifications
 */

if (!defined('ABSPATH')) {
    exit(1);
}

global $wpdb;

$database   = new SubscriberNotifications_Database();
$calculator = new SubscriberNotifications_Schedule_Calculator();
$admin_id   = 1;

// -------------------------------------------------------------------------
// Sample subscribers
// -------------------------------------------------------------------------
$subscribers = array(
    array(
        'name'      => 'Alex Rivera',
        'email'     => 'alex.rivera+sn-test@example.com',
        'frequency' => 'daily',
        'prefs'     => array(
            'post' => array(
                'category' => array(11, 12),
            ),
        ),
    ),
    array(
        'name'      => 'Sam Chen',
        'email'     => 'sam.chen+sn-test@example.com',
        'frequency' => 'weekly',
        'prefs'     => array(
            'faq' => array(
                'faq-category' => array(15, 16, 17),
            ),
        ),
    ),
    array(
        'name'      => 'Jordan Blake',
        'email'     => 'jordan.blake+sn-test@example.com',
        'frequency' => 'weekly',
        'prefs'     => array(
            'project' => array(
                'project-status' => array(38, 43, 49),
                'project-type'   => array(31, 40, 42),
            ),
        ),
    ),
    array(
        'name'      => 'Taylor Morgan',
        'email'     => 'taylor.morgan+sn-test@example.com',
        'frequency' => 'monthly',
        'prefs'     => array(
            'place' => array(
                'amenity'        => array(21, 24, 26),
                'place-category' => array(23, 25, 30),
            ),
            'tribe_events' => array(
                'tribe_events_cat' => array(5, 6, 7),
            ),
        ),
    ),
);

$created_subscribers = array();

foreach ($subscribers as $sub) {
    $existing = $database->get_subscriber_by_email($sub['email']);
    if ($existing) {
        $created_subscribers[] = (int) $existing->id;
        echo "SKIP subscriber (exists): {$sub['name']} <{$sub['email']}> (ID {$existing->id})\n";
        continue;
    }

    $id = $database->add_subscriber(array(
        'name'                     => $sub['name'],
        'email'                    => $sub['email'],
        'frequency'                => $sub['frequency'],
        'subscription_preferences' => $sub['prefs'],
        'status'                   => 'active',
    ));

    if (!$id) {
        echo "FAIL subscriber: {$sub['email']}\n";
        continue;
    }

    $created_subscribers[] = (int) $id;
    echo "ADD subscriber: {$sub['name']} <{$sub['email']}> (ID {$id}, {$sub['frequency']})\n";
}

// -------------------------------------------------------------------------
// Sample notifications
// -------------------------------------------------------------------------
$notifications_table = $wpdb->prefix . 'subscriber_notifications_queue';
$now                 = current_time('mysql');
$due_now             = wp_date('Y-m-d H:i:s', time() - 120);

$notifications = array(
    array(
        'title'            => 'Daily News Brief',
        'subject'          => 'Your daily news update',
        'content'          => "<p>Here are the latest posts from your selected news categories.</p>\n<p>[subscriber_name], check out what's new today.</p>",
        'frequency_target' => 'daily',
        'target_prefs'     => array(
            'post' => array(
                'category' => array(11, 12),
            ),
        ),
        'is_recurring'     => 0,
        'next_send_date'   => $due_now,
        'note'             => 'due now — cron should pick this up on next process_queue run',
    ),
    array(
        'title'            => 'Project Status Update',
        'subject'          => 'Weekly project digest',
        'content'          => "<p>Updates on projects matching your interests.</p>\n<p>Review status changes and new project posts from the past week.</p>",
        'frequency_target' => 'weekly',
        'target_prefs'     => array(
            'project' => array(
                'project-status' => array(38, 43, 49, 65),
                'project-type'   => array(31, 40, 42),
            ),
        ),
        'is_recurring'     => 0,
        'next_send_date'   => null,
        'note'             => 'scheduled for next weekly slot',
    ),
    array(
        'title'            => 'FAQ Weekly Roundup',
        'subject'          => 'New FAQs this week',
        'content'          => "<p>Recently published FAQs from your subscribed categories.</p>",
        'frequency_target' => 'weekly',
        'target_prefs'     => array(
            'faq' => array(
                'faq-category' => array(15, 16, 17, 18),
            ),
        ),
        'is_recurring'     => 0,
        'next_send_date'   => null,
        'note'             => 'scheduled for next weekly slot',
    ),
    array(
        'title'            => 'Places & Amenities Digest',
        'subject'          => 'New places and amenities',
        'content'          => "<p>Recurring digest of place listings and amenities you follow.</p>",
        'frequency_target' => 'weekly',
        'target_prefs'     => array(
            'place' => array(
                'amenity'        => array(21, 24, 26, 37),
                'place-category' => array(23, 25, 30),
            ),
        ),
        'is_recurring'     => 1,
        'next_send_date'   => null,
        'note'             => 'recurring weekly — matches Jackie + Taylor',
    ),
    array(
        'title'            => 'Monthly Events & Places',
        'subject'          => 'Your monthly events summary',
        'content'          => "<p>Events and place updates from the past month.</p>",
        'frequency_target' => 'monthly',
        'target_prefs'     => array(
            'place' => array(
                'amenity' => array(21, 24),
            ),
            'tribe_events' => array(
                'tribe_events_cat' => array(5, 6, 7),
            ),
        ),
        'is_recurring'     => 0,
        'next_send_date'   => null,
        'note'             => 'scheduled for next monthly slot',
    ),
);

foreach ($notifications as $notification) {
    $existing_id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$notifications_table} WHERE title = %s AND status = 'pending' LIMIT 1",
            $notification['title']
        )
    );

    if ($existing_id > 0) {
        echo "SKIP notification (exists): {$notification['title']} (ID {$existing_id})\n";
        continue;
    }

    $next_send = $notification['next_send_date'];
    if ($next_send === null) {
        $next_send = $notification['is_recurring']
            ? $calculator->next_recurring($notification['frequency_target'])
            : $calculator->next_one_time($notification['frequency_target']);
    }

    $result = $wpdb->insert(
        $notifications_table,
        array(
            'title'              => $notification['title'],
            'subject'            => $notification['subject'],
            'content'            => $notification['content'],
            'target_preferences' => SubscriberNotifications_Preferences::encode($notification['target_prefs']),
            'frequency_target'   => $notification['frequency_target'],
            'status'             => 'pending',
            'created_by'         => $admin_id,
            'created_date'       => $now,
            'is_recurring'       => (int) $notification['is_recurring'],
            'next_send_date'     => $next_send,
            'recurrence_count'   => 0,
        )
    );

    if ($result === false) {
        echo "FAIL notification: {$notification['title']}\n";
        continue;
    }

    $id = (int) $wpdb->insert_id;
    echo "ADD notification: {$notification['title']} (ID {$id}, {$notification['frequency_target']}, next: {$next_send}) — {$notification['note']}\n";
}

echo "\nDone. Subscribers: " . count($created_subscribers) . " ready. Check WP Admin → Subscriber Notifications.\n";
