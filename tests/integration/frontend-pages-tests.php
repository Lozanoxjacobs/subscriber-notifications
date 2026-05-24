<?php
/**
 * Frontend pages + preferences shortcode (v3.7.0) — run via:
 * wp eval-file wp-content/plugins/subscriber-notifications/tests/integration/frontend-pages-tests.php
 *
 * @package SubscriberNotifications
 */

require_once __DIR__ . '/test-helpers.php';

$database   = new SubscriberNotifications_Database();
$frontend   = new SubscriberNotifications_Frontend($database);
$reflection = new ReflectionClass($frontend);

$subscribe_option    = subscriber_notifications_option_name('subscribe_page_id');
$preferences_option  = subscriber_notifications_option_name('preferences_page_id');
$stored_subscribe    = (int) subscriber_notifications_get_option('subscribe_page_id', 0);
$stored_preferences  = (int) subscriber_notifications_get_option('preferences_page_id', 0);

/**
 * @param SubscriberNotifications_Frontend $frontend Frontend instance.
 * @param string                           $method   Private method name.
 * @return ReflectionMethod
 */
function sn_test_frontend_method(SubscriberNotifications_Frontend $frontend, string $method): ReflectionMethod {
    $reflection = new ReflectionClass($frontend);
    $callable   = $reflection->getMethod($method);
    $callable->setAccessible(true);
    return $callable;
}

$subscribe_page_id = wp_insert_post(
    array(
        'post_title'   => 'SN Test Subscribe ' . wp_generate_password(6, false),
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '[subscriber_notifications_form]',
    ),
    true
);
sn_test_assert('subscribe test page created', !is_wp_error($subscribe_page_id) && $subscribe_page_id > 0);

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

update_option($subscribe_option, (int) $subscribe_page_id);
update_option($preferences_option, (int) $preferences_page_id);

$subscribe_url = subscriber_notifications_get_subscribe_page_url();
sn_test_assert(
    'subscribe URL uses configured page permalink',
    strpos($subscribe_url, (string) get_permalink((int) $subscribe_page_id)) === 0
);

update_option($subscribe_option, 0);
sn_test_assert('subscribe URL empty when page unset', subscriber_notifications_get_subscribe_page_url() === '');
sn_test_assert(
    'frontend pages not configured when subscribe missing',
    !subscriber_notifications_frontend_pages_are_configured()
);

update_option($subscribe_option, (int) $subscribe_page_id);
update_option($preferences_option, 0);
sn_test_assert(
    'frontend pages not configured when preferences missing',
    !subscriber_notifications_frontend_pages_are_configured()
);

update_option($preferences_option, (int) $preferences_page_id);
sn_test_assert(
    'frontend pages configured when both pages set',
    subscriber_notifications_frontend_pages_are_configured()
);

$missing = array();
if (!subscriber_notifications_subscribe_page_is_configured()) {
    $missing[] = 'Subscribe page';
}
if (!subscriber_notifications_preferences_page_is_configured()) {
    $missing[] = 'Preferences page';
}
sn_test_assert('admin notice missing list empty when both configured', empty($missing));

update_option($subscribe_option, 0);
update_option($preferences_option, 0);
$missing = array();
if (!subscriber_notifications_subscribe_page_is_configured()) {
    $missing[] = 'Subscribe page';
}
if (!subscriber_notifications_preferences_page_is_configured()) {
    $missing[] = 'Preferences page';
}
sn_test_assert('admin notice lists both pages when unset', count($missing) === 2);

update_option($subscribe_option, (int) $subscribe_page_id);
update_option($preferences_option, (int) $preferences_page_id);

sn_test_assert(
    'legacy manage route method removed',
    !method_exists($frontend, 'handle_manage_preferences_route')
);

$linked_user_id = wp_insert_user(
    array(
        'user_login'   => 'sn-fe-linked-' . wp_generate_password(8, false),
        'user_email'   => 'sn-fe-linked-' . wp_generate_password(8, false) . '@example.com',
        'user_pass'    => wp_generate_password(16, true),
        'first_name'   => 'Profile',
        'last_name'    => 'Name',
        'display_name' => 'Profile Name',
    )
);
sn_test_assert('linked test user created', !is_wp_error($linked_user_id) && $linked_user_id > 0);

$linked_subscriber_id = $database->add_subscriber(
    array(
        'name'               => 'Stale DB Name',
        'email'              => 'stale-db@example.com',
        'frequency'          => 'weekly',
        'status'             => 'active',
        'management_token'   => wp_generate_password(32, false),
        'user_id'            => (int) $linked_user_id,
        'subscription_preferences' => array(),
    )
);

wp_set_current_user((int) $linked_user_id);
unset($_GET['token']);
$form_html = do_shortcode('[subscriber_notifications_preferences]');
sn_test_assert(
    'preferences form omits redundant title heading',
    strpos($form_html, 'Manage Your Preferences') === false
);
sn_test_assert(
    'preferences form renders for linked session',
    strpos($form_html, 'Contact Information') !== false
);
sn_test_assert(
    'linked form shows live profile name',
    strpos($form_html, 'Profile Name') !== false
);

$linked_subscriber = $database->get_subscriber((int) $linked_subscriber_id);
$_POST['subscriber_name'] = 'Tampered Name';
$_POST['subscriber_email'] = 'tampered@example.com';

$resolve_name  = sn_test_frontend_method($frontend, 'resolve_preferences_contact_name');
$resolve_email = sn_test_frontend_method($frontend, 'resolve_preferences_contact_email_for_update');

sn_test_assert(
    'linked save ignores POSTed name',
    $resolve_name->invoke($frontend, $linked_subscriber) === 'Profile Name'
);
sn_test_assert(
    'linked save syncs profile email not POST',
    $resolve_email->invoke($frontend, $linked_subscriber) === get_userdata((int) $linked_user_id)->user_email
);

$guest_token = wp_generate_password(32, false);
$guest_subscriber_id = $database->add_subscriber(
    array(
        'name'             => 'Guest Subscriber',
        'email'            => 'guest-fe-' . wp_generate_password(8, false) . '@example.com',
        'frequency'        => 'weekly',
        'status'           => 'active',
        'management_token' => $guest_token,
    )
);
$guest_subscriber = $database->get_subscriber((int) $guest_subscriber_id);

wp_set_current_user(0);
$_POST['subscriber_name'] = 'Updated Guest Name';
sn_test_assert(
    'guest save accepts POSTed name',
    $resolve_name->invoke($frontend, $guest_subscriber) === 'Updated Guest Name'
);
sn_test_assert(
    'guest save does not update email from resolver',
    $resolve_email->invoke($frontend, $guest_subscriber) === null
);

wp_set_current_user(0);
unset($_GET['token']);
$guest_empty_html = do_shortcode('[subscriber_notifications_preferences]');
sn_test_assert(
    'guest empty state omits redundant title heading',
    strpos($guest_empty_html, 'Manage Your Preferences') === false
);
sn_test_assert(
    'guest empty state prompts for email link',
    strpos($guest_empty_html, 'Use the link from your email') !== false
);

$_GET['token'] = 'invalid-token-for-fe-test';
$invalid_html = do_shortcode('[subscriber_notifications_preferences]');
sn_test_assert(
    'invalid token empty state keeps Invalid Link heading',
    strpos($invalid_html, 'Invalid Link') !== false
);
unset($_GET['token']);

global $subscriber_notifications_current_subscriber;
$subscriber_notifications_current_subscriber = $linked_subscriber;
$shortcodes = new SubscriberNotifications_Shortcodes();

$manage_link = $shortcodes->manage_preferences_link_shortcode(array(), '', 'manage_preferences_link');
sn_test_assert(
    'manage_preferences_link is HTML anchor',
    strpos($manage_link, '<a href="') === 0
);
sn_test_assert(
    'manage_preferences_link uses preferences page URL',
    strpos($manage_link, esc_url(get_permalink((int) $preferences_page_id))) !== false
);
sn_test_assert(
    'manage_preferences_link includes token query arg',
    strpos($manage_link, 'token=') !== false
);
sn_test_assert(
    'manage_preferences_link does not use action=manage',
    strpos($manage_link, 'action=manage') === false
);

update_option($preferences_option, 0);
$subscriber_notifications_current_subscriber = $linked_subscriber;
$placeholder_link = $shortcodes->manage_preferences_link_shortcode(array(), '', 'manage_preferences_link');
sn_test_assert(
    'manage_preferences_link placeholder when preferences page unset',
    $placeholder_link === '[Manage Preferences Link]'
);
update_option($preferences_option, (int) $preferences_page_id);

$email_sender_reflection = new ReflectionClass('SubscriberNotifications_Email_Sender');
$get_manage_url          = $email_sender_reflection->getMethod('get_manage_preferences_url');
$get_manage_url->setAccessible(true);
$email_sender = new SubscriberNotifications_Email_Sender();
$email_manage_url = $get_manage_url->invoke($email_sender, (int) $linked_subscriber_id);
sn_test_assert(
    'email sender manage URL uses preferences page',
    is_string($email_manage_url)
    && strpos($email_manage_url, (string) get_permalink((int) $preferences_page_id)) === 0
);
sn_test_assert(
    'email sender manage URL has token only',
    strpos($email_manage_url, 'token=') !== false && strpos($email_manage_url, 'action=manage') === false
);

update_option($preferences_option, 0);
$email_manage_url_unset = $get_manage_url->invoke($email_sender, (int) $linked_subscriber_id);
sn_test_assert(
    'email sender manage URL false when preferences page unset',
    $email_manage_url_unset === false
);
update_option($preferences_option, (int) $preferences_page_id);

wp_set_current_user((int) $linked_user_id);
$subscribe_html = do_shortcode('[subscriber_notifications_form]');
sn_test_assert(
    'active linked user sees already subscribed on subscribe page',
    strpos($subscribe_html, 'Already Subscribed') !== false
);
sn_test_assert(
    'already subscribed state links to preferences page',
    strpos($subscribe_html, esc_url(get_permalink((int) $preferences_page_id))) !== false
);
sn_test_assert(
    'already subscribed state hides subscribe form',
    strpos($subscribe_html, 'subscriber-notifications-form" method="post') === false
    && strpos($subscribe_html, 'id="subscriber-notifications-form"') === false
);

$database->update_subscriber((int) $linked_subscriber_id, array('status' => 'inactive'));
$inactive_html = do_shortcode('[subscriber_notifications_form]');
sn_test_assert(
    'inactive linked user sees subscribe form for reactivation',
    strpos($inactive_html, 'id="subscriber-notifications-form"') !== false
);
sn_test_assert(
    'inactive subscribe form shows reactivation notice',
    strpos($inactive_html, 'reactivate your subscription') !== false
);
$database->update_subscriber((int) $linked_subscriber_id, array('status' => 'inactive'));
$_GET['unsubscribed'] = '1';
wp_set_current_user((int) $linked_user_id);
$unsubscribed_html = do_shortcode('[subscriber_notifications_preferences]');
sn_test_assert(
    'unsubscribed confirmation shown after unsubscribe',
    strpos($unsubscribed_html, 'You have been unsubscribed') !== false
);
sn_test_assert(
    'unsubscribed confirmation includes reactivation guidance',
    strpos($unsubscribed_html, 'Update your preferences below and save to subscribe again') !== false
);
sn_test_assert(
    'unsubscribe section hidden when inactive',
    strpos($unsubscribed_html, 'id="unsubscribe-button"') === false
);
sn_test_assert(
    'inactive submit button offers reactivation',
    strpos($unsubscribed_html, 'Reactivate Subscription') !== false
);
unset($_GET['unsubscribed']);

$guest_token = wp_generate_password(32, false);
$guest_return_id = $database->add_subscriber(
    array(
        'name'             => 'Guest Return Test',
        'email'            => 'guest-return-' . wp_generate_password(8, false) . '@example.com',
        'frequency'        => 'weekly',
        'status'           => 'inactive',
        'management_token' => $guest_token,
    )
);
wp_set_current_user(0);
$_GET['token'] = $guest_token;
$token_return_html = do_shortcode('[subscriber_notifications_preferences]');
sn_test_assert(
    'inactive guest via email token sees unsubscribed notice',
    strpos($token_return_html, 'You are currently unsubscribed') !== false
);
sn_test_assert(
    'inactive guest via token does not see unsubscribe section',
    strpos($token_return_html, 'id="unsubscribe-button"') === false
);

$database->update_subscriber((int) $guest_return_id, array('status' => 'active'));
$_GET['reactivated'] = '1';
$reactivated_html = do_shortcode('[subscriber_notifications_preferences]');
sn_test_assert(
    'reactivated confirmation shown after successful reactivation',
    strpos($reactivated_html, 'Welcome back!') !== false
);
sn_test_assert(
    'reactivated state shows unsubscribe section',
    strpos($reactivated_html, 'id="unsubscribe-button"') !== false
);
sn_test_assert(
    'reactivated state does not show inactive notice',
    strpos($reactivated_html, 'You are currently unsubscribed') === false
);
unset($_GET['reactivated']);

$database->update_subscriber((int) $guest_return_id, array('status' => 'active'));
$_GET['unsubscribed'] = '1';
$stale_unsubscribed_html = do_shortcode('[subscriber_notifications_preferences]');
sn_test_assert(
    'active subscriber ignores stale unsubscribed query param',
    strpos($stale_unsubscribed_html, 'You have been unsubscribed') === false
);
sn_test_assert(
    'active subscriber with stale unsubscribed param shows unsubscribe section',
    strpos($stale_unsubscribed_html, 'id="unsubscribe-button"') !== false
);
unset($_GET['unsubscribed']);

unset($_GET['token']);
$database->delete_subscriber((int) $guest_return_id);

$database->update_subscriber((int) $linked_subscriber_id, array('status' => 'active'));

// Cleanup.
unset($subscriber_notifications_current_subscriber, $_POST['subscriber_name'], $_POST['subscriber_email']);
$database->delete_subscriber((int) $linked_subscriber_id);
$database->delete_subscriber((int) $guest_subscriber_id);
wp_delete_user((int) $linked_user_id);
wp_delete_post((int) $subscribe_page_id, true);
wp_delete_post((int) $preferences_page_id, true);
update_option($subscribe_option, $stored_subscribe);
update_option($preferences_option, $stored_preferences);

sn_test_finish();
