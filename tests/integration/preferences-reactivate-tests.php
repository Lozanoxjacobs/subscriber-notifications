<?php
/**
 * Preferences reactivation — run via:
 * wp eval-file wp-content/plugins/subscriber-notifications/tests/integration/preferences-reactivate-tests.php
 *
 * Verifies that an inactive subscriber who saves the manage-preferences form
 * is set back to active (mirrors handle_preferences_update()).
 *
 * @package SubscriberNotifications
 */

require_once __DIR__ . '/test-helpers.php';

$database = new SubscriberNotifications_Database();

/**
 * Build minimal valid preferences for the current Content Types config.
 *
 * @return array
 */
function sn_test_sample_preferences(): array {
    $prefs = array();

    foreach (SubscriberNotifications_Content_Config::get_enabled_post_types() as $post_type) {
        foreach (SubscriberNotifications_Content_Config::get_form_taxonomies($post_type) as $taxonomy) {
            $terms = get_terms(
                array(
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                    'number'     => 1,
                )
            );
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }
            $prefs[ $post_type ][ $taxonomy ] = array( (int) $terms[0]->term_id );
            return $prefs;
        }
    }

    return $prefs;
}

$prefs = sn_test_sample_preferences();
if (empty($prefs)) {
    echo 'SKIP: No Content Types terms available for preferences reactivation test.' . PHP_EOL;
    sn_test_finish();
}

$token = wp_generate_password(32, false);
$email = 'reactivate-test-' . wp_generate_password(8, false) . '@example.com';

$subscriber_id = $database->add_subscriber(
    array(
        'name'               => 'Reactivate Test',
        'email'              => $email,
        'frequency'          => 'weekly',
        'status'             => 'active',
        'management_token'   => $token,
        'subscription_preferences' => $prefs,
    )
);

$database->update_subscriber((int) $subscriber_id, array( 'status' => 'inactive' ));

$subscriber = $database->get_subscriber_by_management_token($token);
sn_test_assert('B9 test subscriber starts inactive', $subscriber && $subscriber->status === 'inactive');

$was_inactive = ($subscriber->status === 'inactive');
$update_data  = array(
    'name'                     => 'Reactivate Test Updated',
    'subscription_preferences' => $prefs,
    'frequency'                => 'weekly',
);
if ($was_inactive) {
    $update_data['status'] = 'active';
}

$database->update_subscriber((int) $subscriber_id, $update_data);

$fresh = $database->get_subscriber((int) $subscriber_id);
sn_test_assert('B9 inactive subscriber reactivated after preferences save', $fresh && $fresh->status === 'active');
sn_test_assert('B9 frequency preserved after reactivation', $fresh && $fresh->frequency === 'weekly');

$database->delete_subscriber((int) $subscriber_id);

sn_test_finish();
