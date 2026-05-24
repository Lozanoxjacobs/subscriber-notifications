<?php
/**
 * Seed feed-test notifications (all frequencies + content_feed shortcodes).
 *
 * wp eval-file wp-content/plugins/subscriber-notifications/tests/integration/seed-feed-test-notifications.php
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
$now        = current_time('mysql');
$due_now    = wp_date('Y-m-d H:i:s', time() - 120);

/**
 * @param string $duration 1day|1week|1month
 */
function sn_seed_feed_shortcode_block($duration) {
    return implode(
        "\n\n",
        array(
            '<h2>News</h2>',
            '[content_feed post_type="post" taxonomy="category" duration="' . $duration . '" format="list" limit="5"]',
            '<h2>FAQs</h2>',
            '[content_feed post_type="faq" taxonomy="faq-category" duration="' . $duration . '" format="list" limit="5"]',
            '<h2>Places</h2>',
            '[content_feed post_type="place" duration="' . $duration . '" format="summary" limit="3"]',
            '<h2>Projects</h2>',
            '[content_feed post_type="project" duration="' . $duration . '" format="list" limit="5"]',
            '<h2>Events</h2>',
            '[content_feed post_type="tribe_events" taxonomy="tribe_events_cat" duration="' . $duration . '" format="list" limit="5"]',
        )
    );
}

$broad_target_prefs = array(
    'post'         => array(
        'category' => array(11, 12, 1),
    ),
    'faq'          => array(
        'faq-category' => array(15, 16, 17, 18),
    ),
    'place'        => array(
        'amenity'        => array(21, 24, 26, 37, 39),
        'place-category' => array(23, 25, 30),
    ),
    'project'      => array(
        'project-status' => array(38, 43, 49, 65, 70, 71),
        'project-type'   => array(31, 40, 42, 54, 72),
    ),
    'tribe_events' => array(
        'tribe_events_cat' => array(5, 6, 7, 8, 9),
    ),
);

// Ensure Jackie (and sample subs) can see every feed section.
$full_prefs = $broad_target_prefs;
foreach ($database->get_subscribers(array('status' => 'active', 'limit' => 50)) as $subscriber) {
    $database->update_subscriber((int) $subscriber->id, array(
        'subscription_preferences' => $full_prefs,
    ));
    echo "UPDATE subscriber prefs: {$subscriber->name} <{$subscriber->email}>\n";
}

// Flag recent published posts so content_feed returns linkable items.
$content_types = array('post', 'faq', 'place', 'project', 'tribe_events');
foreach ($content_types as $post_type) {
    $posts = get_posts(array(
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => 5,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ));
    foreach ($posts as $post) {
        update_post_meta($post->ID, '_subscriber_notifications_include_in_feed', '1');
        update_post_meta($post->ID, '_subscriber_notifications_last_notification_date', $now);
    }
    echo "FLAG {$post_type}: " . count($posts) . " posts for feed\n";
}

$notifications_table = $wpdb->prefix . 'subscriber_notifications_queue';

$feed_tests = array(
    array(
        'title'            => 'Feed Test — Daily',
        'subject'          => 'Feed test (daily): [subscriber_name]',
        'duration'         => '1day',
        'frequency_target' => 'daily',
    ),
    array(
        'title'            => 'Feed Test — Weekly',
        'subject'          => 'Feed test (weekly): [subscriber_name]',
        'duration'         => '1week',
        'frequency_target' => 'weekly',
    ),
    array(
        'title'            => 'Feed Test — Monthly',
        'subject'          => 'Feed test (monthly): [subscriber_name]',
        'duration'         => '1month',
        'frequency_target' => 'monthly',
    ),
);

$notification_ids = array();

foreach ($feed_tests as $feed_test) {
    $wpdb->delete(
        $notifications_table,
        array(
            'title'  => $feed_test['title'],
            'status' => 'pending',
        ),
        array('%s', '%s')
    );

    $content = '<p>Hello [subscriber_name],</p>'
        . '<p>Testing content feeds and tracked links for every configured content type.</p>'
        . sn_seed_feed_shortcode_block($feed_test['duration'])
        . '<p>Subscriptions: [selected_subscriptions]</p>'
        . '<p>[manage_preferences_link]</p>';

    $result = $wpdb->insert(
        $notifications_table,
        array(
            'title'              => $feed_test['title'],
            'subject'            => $feed_test['subject'],
            'content'            => $content,
            'target_preferences' => SubscriberNotifications_Preferences::encode($broad_target_prefs),
            'frequency_target'   => $feed_test['frequency_target'],
            'status'             => 'pending',
            'created_by'         => $admin_id,
            'created_date'       => $now,
            'is_recurring'       => 0,
            'next_send_date'     => $due_now,
            'recurrence_count'   => 0,
        )
    );

    if ($result === false) {
        echo "FAIL notification: {$feed_test['title']}\n";
        continue;
    }

    $id = (int) $wpdb->insert_id;
    $notification_ids[] = $id;
    echo "ADD notification: {$feed_test['title']} (ID {$id}, {$feed_test['frequency_target']}, due now)\n";
}

// Enqueue and drain so test emails send immediately.
$scheduler = new SubscriberNotifications_Scheduler($database);
foreach ($notification_ids as $notification_id) {
    $scheduler->send_scheduled_notification($notification_id);
}

for ($i = 0; $i < 30; $i++) {
    $pending = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}subscriber_notifications_send_queue WHERE status = 'pending'"
    );
    if ($pending === 0) {
        break;
    }
    $scheduler->drain_send_queue();
}

$sent = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}subscriber_notifications_send_queue WHERE status = 'sent'"
);
$skipped = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}subscriber_notifications_send_queue WHERE status = 'skipped'"
);
$failed = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}subscriber_notifications_send_queue WHERE status = 'failed'"
);

echo "\nSend queue: sent={$sent} skipped={$skipped} failed={$failed}\n";
echo "Check inboxes: daily→Alex, weekly→Jackie + weekly subs, monthly→Taylor\n";
echo "Each email includes content_feed links for post, faq, place, project, and tribe_events.\n";
