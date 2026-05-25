<?php
/**
 * Restore Pantheon dev defaults after uninstall/reinstall (3.8.0).
 *
 * wp eval-file wp-content/plugins/subscriber-notifications/tests/integration/bootstrap-pantheon-dev.php
 *
 * @package SubscriberNotifications
 */

if (!defined('ABSPATH')) {
    exit(1);
}

$tax_defaults = static function () {
    return array(
        'enabled_on_form'  => true,
        'label'            => '',
        'term_display'     => 'all',
        'parent_term_id'   => 0,
        'include_term_ids' => array(),
        'exclude_term_ids' => array(),
    );
};

$raw = array();

$map = array(
    'post'         => array('category'),
    'faq'          => array('faq-category'),
    'place'        => array('amenity', 'place-category'),
    'project'      => array('project-status', 'project-type'),
    'tribe_events' => array('tribe_events_cat'),
);

foreach ($map as $post_type => $taxonomies) {
    if (!post_type_exists($post_type)) {
        echo "SKIP post type (missing): {$post_type}\n";
        continue;
    }
    $pt = get_post_type_object($post_type);
    $raw[ $post_type ] = array(
        'enabled'                         => true,
        'allow_single_item_subscriptions' => false,
        'label'                           => $pt && !empty($pt->labels->name) ? $pt->labels->name : $post_type,
        'taxonomies'                      => array(),
    );
    foreach ($taxonomies as $tax) {
        if (!taxonomy_exists($tax)) {
            echo "SKIP taxonomy {$tax} on {$post_type}\n";
            continue;
        }
        $raw[ $post_type ]['taxonomies'][ $tax ] = $tax_defaults();
    }
}

// Single-item on pages (and place for digest QA per dev plan).
foreach (array('page', 'place') as $single_type) {
    if (!post_type_exists($single_type)) {
        continue;
    }
    $pt = get_post_type_object($single_type);
    if (!isset($raw[ $single_type ])) {
        $raw[ $single_type ] = array(
            'enabled'                         => false,
            'allow_single_item_subscriptions' => true,
            'label'                           => $pt && !empty($pt->labels->name) ? $pt->labels->name : $single_type,
            'taxonomies'                      => array(),
        );
    } else {
        $raw[ $single_type ]['allow_single_item_subscriptions'] = true;
    }
}

$config = SubscriberNotifications_Content_Config::sanitize($raw);
update_option(SubscriberNotifications_Content_Config::OPTION_KEY, $config);
SubscriberNotifications_Content_Config::clear_cache();

echo 'Content config saved: ' . count($config) . " post type(s).\n";
echo 'is_configured: ' . (SubscriberNotifications_Content_Config::is_configured() ? 'yes' : 'no') . "\n";

$subscribe_id    = (int) subscriber_notifications_get_option('subscribe_page_id', 0);
$preferences_id  = (int) subscriber_notifications_get_option('preferences_page_id', 0);

if ($subscribe_id < 1 || !get_post($subscribe_id)) {
    $subscribe_id = wp_insert_post(
        array(
            'post_title'   => 'Subscribe to Notifications',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[subscriber_notifications_form]',
        ),
        true
    );
    if (!is_wp_error($subscribe_id)) {
        update_option(subscriber_notifications_option_name('subscribe_page_id'), (int) $subscribe_id);
        echo "Created subscribe page ID {$subscribe_id}\n";
    }
} else {
    echo "Subscribe page OK: {$subscribe_id}\n";
}

if ($preferences_id < 1 || !get_post($preferences_id)) {
    $preferences_id = wp_insert_post(
        array(
            'post_title'   => 'Manage Notification Preferences',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[subscriber_notifications_preferences]',
        ),
        true
    );
    if (!is_wp_error($preferences_id)) {
        update_option(subscriber_notifications_option_name('preferences_page_id'), (int) $preferences_id);
        echo "Created preferences page ID {$preferences_id}\n";
    }
} else {
    echo "Preferences page OK: {$preferences_id}\n";
}

echo 'frontend_pages_configured: ' . (subscriber_notifications_frontend_pages_are_configured() ? 'yes' : 'no') . "\n";
echo "Done.\n";
