<?php
/**
 * Preferences page helpers and session cleanup — run via:
 * wp eval-file wp-content/plugins/subscriber-notifications/tests/integration/preferences-page-tests.php
 *
 * @package SubscriberNotifications
 */

require_once __DIR__ . '/test-helpers.php';

$database = new SubscriberNotifications_Database();
$frontend = new SubscriberNotifications_Frontend($database);

$preferences_option = subscriber_notifications_option_name('preferences_page_id');
$stored_preferences = (int) subscriber_notifications_get_option('preferences_page_id', 0);

$preferences_page_id = wp_insert_post(
    array(
        'post_title'   => 'SN Test Preferences ' . wp_generate_password(6, false),
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '[subscriber_notifications_preferences]',
    ),
    true
);

sn_test_assert('preferences test page created', !is_wp_error($preferences_page_id) && $preferences_page_id > 0);

update_option($preferences_option, (int) $preferences_page_id);

$token     = 'testtoken' . wp_generate_password(16, false);
$manage_url = subscriber_notifications_get_preferences_page_url(array('token' => $token));

sn_test_assert(
    'preferences URL uses configured page permalink',
    strpos($manage_url, (string) get_permalink((int) $preferences_page_id)) === 0
);
sn_test_assert('preferences URL includes token query arg', strpos($manage_url, 'token=' . rawurlencode($token)) !== false);

update_option($preferences_option, 0);
sn_test_assert(
    'preferences URL empty when page unset',
    subscriber_notifications_get_preferences_page_url(array('token' => $token)) === ''
);

update_option($preferences_option, (int) $preferences_page_id);

$user_id = wp_insert_user(
    array(
        'user_login' => 'sn-prefs-test-' . wp_generate_password(8, false),
        'user_email' => 'sn-prefs-test-' . wp_generate_password(8, false) . '@example.com',
        'user_pass'  => wp_generate_password(16, true),
        'first_name' => 'Prefs',
        'last_name'  => 'Tester',
    )
);
sn_test_assert('test WP user created', !is_wp_error($user_id) && $user_id > 0);

$linked_email = 'linked-sub-' . wp_generate_password(8, false) . '@example.com';
$linked_id    = $database->add_subscriber(
    array(
        'name'             => 'Linked Subscriber',
        'email'            => $linked_email,
        'frequency'        => 'weekly',
        'status'           => 'active',
        'management_token' => wp_generate_password(32, false),
        'user_id'          => (int) $user_id,
    )
);

$by_user = $database->get_subscriber_by_user_id((int) $user_id);
sn_test_assert('session lookup by user_id', $by_user && (int) $by_user->id === (int) $linked_id);

$reflection = new ReflectionClass($frontend);
$resolve    = $reflection->getMethod('resolve_subscriber_for_preferences_page');
$resolve->setAccessible(true);

wp_set_current_user((int) $user_id);
unset($_GET['token']);
$resolved = $resolve->invoke($frontend);
sn_test_assert('logged-in session resolves linked subscriber', $resolved && (int) $resolved->id === (int) $linked_id);

$guest_email = 'guest-link-' . wp_generate_password(8, false) . '@example.com';
$guest_id    = $database->add_subscriber(
    array(
        'name'             => 'Guest Link Test',
        'email'            => $guest_email,
        'frequency'        => 'weekly',
        'status'           => 'active',
        'management_token' => wp_generate_password(32, false),
    )
);

$link_user_id = wp_insert_user(
    array(
        'user_login' => 'sn-guest-link-' . wp_generate_password(8, false),
        'user_email' => $guest_email,
        'user_pass'  => wp_generate_password(16, true),
        'first_name' => 'Guest',
        'last_name'  => 'Linked',
    )
);
sn_test_assert('guest-link WP user created', !is_wp_error($link_user_id) && $link_user_id > 0);

wp_set_current_user((int) $link_user_id);
unset($_GET['token']);
$resolved_guest = $resolve->invoke($frontend);
sn_test_assert(
    'email fallback auto-links user_id on preferences page visit',
    $resolved_guest
    && (int) $resolved_guest->id === (int) $guest_id
    && (int) $resolved_guest->user_id === (int) $link_user_id
);

$stored_content_config = get_option(SubscriberNotifications_Content_Config::OPTION_KEY, array());
update_option(
    SubscriberNotifications_Content_Config::OPTION_KEY,
    array(
        'page' => array(
            'enabled'                         => false,
            'allow_single_item_subscriptions' => true,
            'label'                           => 'Pages',
            'taxonomies'                      => array(),
        ),
    )
);
SubscriberNotifications_Content_Config::clear_cache();

$item_post_id = wp_insert_post(
    array(
        'post_title'  => 'SN Prefs Item ' . wp_generate_password(4, false),
        'post_status' => 'publish',
        'post_type'   => 'page',
    ),
    true
);
sn_test_assert('item prefs test post created', !is_wp_error($item_post_id) && $item_post_id > 0);

$item_prefs = SubscriberNotifications_Preferences::add_item(array(), (int) $item_post_id);
$pruned     = SubscriberNotifications_Preferences::prune_for_save($item_prefs, 'public');
sn_test_assert(
    'prune_for_save keeps published item subscription',
    SubscriberNotifications_Preferences::has_item($pruned, (int) $item_post_id)
);
sn_test_assert(
    'has_any_subscription true for item-only prefs',
    SubscriberNotifications_Preferences::has_any_subscription($pruned)
);

$render_items = $reflection->getMethod('render_item_preferences_section');
$render_items->setAccessible(true);
ob_start();
$render_items->invoke($frontend, $item_prefs);
$item_markup = ob_get_clean();
sn_test_assert(
    'preferences item section renders when _items present',
    strpos($item_markup, 'sn-item-subscriptions') !== false
    && strpos($item_markup, (string) $item_post_id) !== false
);

$plugin = SubscriberNotifications::get_instance();
$plugin->handle_deleted_wordpress_user((int) $user_id);
sn_test_assert('delete_user removes linked subscriber row', !$database->get_subscriber_by_user_id((int) $user_id));

wp_delete_post((int) $item_post_id, true);
update_option(SubscriberNotifications_Content_Config::OPTION_KEY, $stored_content_config);
SubscriberNotifications_Content_Config::clear_cache();

// Cleanup.
$database->delete_subscriber((int) $guest_id);
wp_delete_user((int) $link_user_id);
wp_delete_post((int) $preferences_page_id, true);
update_option($preferences_option, $stored_preferences);

sn_test_finish();
