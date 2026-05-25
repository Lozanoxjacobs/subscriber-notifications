<?php
/**
 * Item subscription feature tests.
 *
 * wp eval-file wp-content/plugins/subscriber-notifications/tests/integration/item-subscriptions-tests.php
 *
 * @package SubscriberNotifications
 */

require_once __DIR__ . '/test-helpers.php';

// Content config: single-item flag.
$config = array(
    'page' => array(
        'enabled'                         => false,
        'allow_single_item_subscriptions' => true,
        'label'                           => 'Pages',
        'taxonomies'                      => array(),
    ),
);
update_option(SubscriberNotifications_Content_Config::OPTION_KEY, $config);
SubscriberNotifications_Content_Config::clear_cache();

sn_test_assert('is_single_item_available for page', SubscriberNotifications_Content_Config::is_single_item_available('page'));
sn_test_assert('is_configured with single-item only', SubscriberNotifications_Content_Config::is_configured());
sn_test_assert('get_single_item_post_types', in_array('page', SubscriberNotifications_Content_Config::get_single_item_post_types(), true));

// Preferences _items helpers.
$post_id = wp_insert_post(
    array(
        'post_title'  => 'SN Item Test ' . wp_generate_password(4, false),
        'post_status' => 'publish',
        'post_type'   => 'page',
    ),
    true
);
sn_test_assert('test post created', !is_wp_error($post_id) && $post_id > 0);

$prefs = SubscriberNotifications_Preferences::add_item(array(), $post_id);
sn_test_assert('has_item after add', SubscriberNotifications_Preferences::has_item($prefs, $post_id));
sn_test_assert('has_any_subscription items only', SubscriberNotifications_Preferences::has_any_subscription($prefs));
sn_test_assert('has_at_least_one_term false for items only', !SubscriberNotifications_Preferences::has_at_least_one_term($prefs));

$encoded = SubscriberNotifications_Preferences::encode($prefs);
$decoded = SubscriberNotifications_Preferences::decode($encoded);
sn_test_assert('encode/decode preserves _items', SubscriberNotifications_Preferences::has_item($decoded, $post_id));

sn_test_assert(
    'can_send_item_notifications when published',
    SubscriberNotifications_Preferences::can_send_item_notifications($post_id)
);

wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
sn_test_assert(
    'can_send_item_notifications false when draft',
    !SubscriberNotifications_Preferences::can_send_item_notifications($post_id)
);
wp_update_post(array('ID' => $post_id, 'post_status' => 'publish'));

// Meta simulation.
update_post_meta($post_id, SubscriberNotifications_Preferences::META_INCLUDE_IN_FEED, 1);
update_post_meta($post_id, SubscriberNotifications_Preferences::META_FEED_SINCE, current_time('mysql'));
sn_test_assert('feed_since set', (bool) get_post_meta($post_id, SubscriberNotifications_Preferences::META_FEED_SINCE, true));
delete_post_meta($post_id, SubscriberNotifications_Preferences::META_INCLUDE_IN_FEED);
delete_post_meta($post_id, SubscriberNotifications_Preferences::META_FEED_SINCE);
sn_test_assert('feed_since cleared', get_post_meta($post_id, SubscriberNotifications_Preferences::META_FEED_SINCE, true) === '');

// Email log types.
$types = sn_get_email_log_types();
sn_test_assert('item_subscribe log type', isset($types['item_subscribe']));
sn_test_assert('item_update log type', isset($types['item_update']));

// Item update sender.
$database = new SubscriberNotifications_Database();
$item_sender = new SubscriberNotifications_Item_Notifications($database);
$email       = 'item-test-' . wp_generate_password(8, false) . '@example.com';
$sub_id      = $database->add_subscriber(
    array(
        'name'                     => 'Item Tester',
        'email'                    => $email,
        'frequency'                => 'weekly',
        'status'                   => 'active',
        'subscription_preferences' => SubscriberNotifications_Preferences::encode($prefs),
        'management_token'         => wp_generate_password(32, false),
    )
);
sn_test_assert('subscriber created', (int) $sub_id > 0);

$matched = $item_sender->get_subscribers_for_post($post_id);
sn_test_assert('get_subscribers_for_post finds row', count($matched) === 1);

// Shortcode class loads.
sn_test_assert('Item_Notifications class exists', class_exists('SubscriberNotifications_Item_Notifications'));

// Cleanup test post/subscriber.
$database->delete_subscriber((int) $sub_id);
wp_delete_post($post_id, true);

sn_test_finish();
