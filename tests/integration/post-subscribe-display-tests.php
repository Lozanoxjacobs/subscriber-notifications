<?php
/**
 * Post subscribe shortcode display rules tests.
 *
 * wp eval-file wp-content/plugins/subscriber-notifications/tests/integration/post-subscribe-display-tests.php
 *
 * @package SubscriberNotifications
 */

require_once __DIR__ . '/test-helpers.php';

$page_a = wp_insert_post(
    array(
        'post_title'  => 'SN Include Me ' . wp_generate_password(4, false),
        'post_name'   => 'sn-include-me-' . wp_generate_password(4, false),
        'post_status' => 'publish',
        'post_type'   => 'page',
    ),
    true
);
$page_b = wp_insert_post(
    array(
        'post_title'  => 'SN Exclude Me ' . wp_generate_password(4, false),
        'post_name'   => 'sn-exclude-me-' . wp_generate_password(4, false),
        'post_status' => 'publish',
        'post_type'   => 'page',
    ),
    true
);
sn_test_assert('fixture pages created', !is_wp_error($page_a) && !is_wp_error($page_b));

$post_a = get_post((int) $page_a);
$post_b = get_post((int) $page_b);

$include_rules = SubscriberNotifications_Post_Subscribe_Display::parse_atts(
    array('include' => $post_a->post_name)
);
sn_test_assert(
    'include slug allowlist matches',
    SubscriberNotifications_Post_Subscribe_Display::is_visible($post_a, $include_rules)
    && !SubscriberNotifications_Post_Subscribe_Display::is_visible($post_b, $include_rules)
);

$exclude_rules = SubscriberNotifications_Post_Subscribe_Display::parse_atts(
    array('exclude' => $post_b->post_name)
);
sn_test_assert(
    'exclude slug denylist matches',
    SubscriberNotifications_Post_Subscribe_Display::is_visible($post_a, $exclude_rules)
    && !SubscriberNotifications_Post_Subscribe_Display::is_visible($post_b, $exclude_rules)
);

$copy_rules = SubscriberNotifications_Post_Subscribe_Display::parse_atts(
    array(
        'heading'     => 'Custom heading',
        'description' => 'Custom description',
        'button'      => 'Custom button',
    )
);
$strings = SubscriberNotifications_Post_Subscribe_Display::apply_copy_overrides(
    array(
        'heading'              => 'Default heading',
        'description'          => 'Default description',
        'button_subscribe'     => 'Default button',
        'heading_subscribed'   => 'Default subscribed heading',
        'description_subscribed' => 'Default subscribed description',
        'button_manage'        => 'Default manage',
    ),
    $copy_rules['copy']
);
sn_test_assert('copy override heading', $strings['heading'] === 'Custom heading');
sn_test_assert('copy override description', $strings['description'] === 'Custom description');
sn_test_assert('copy override button maps to button_subscribe', $strings['button_subscribe'] === 'Custom button');

$sanitized = SubscriberNotifications_Post_Subscribe_Display::sanitize_copy_from_request(
    array(
        'heading' => 'Safe heading',
        'button'  => 'Safe button',
        'include' => 'ignored-key',
    )
);
sn_test_assert('sanitize copy ignores unknown keys', !isset($sanitized['include']) && $sanitized['heading'] === 'Safe heading');

wp_delete_post((int) $page_a, true);
wp_delete_post((int) $page_b, true);

sn_test_finish();
