<?php
/**
 * Settings → Shortcodes tab.
 *
 * Centralized reference for every shortcode the plugin provides, plus an
 * auto-generated panel listing the post type and taxonomy slugs configured
 * on this site so admins can copy them exactly.
 *
 * @package SubscriberNotifications
 * @since 3.1.4
 */

if (!defined('ABSPATH')) {
    exit;
}

$content_types_url   = admin_url('admin.php?page=subscriber-notifications-content-types');
$enabled_post_types  = SubscriberNotifications_Content_Config::get_enabled_post_types();
?>

<div class="sn-shortcodes-reference">
    <p>
        <?php esc_html_e('Shortcodes insert personalized, per-subscriber content into emails. Paste them into notification subjects and bodies, system emails (Welcome, Welcome back, Preferences update), and the global header and footer under Email Design.', 'subscriber-notifications'); ?>
    </p>

    <h2><?php esc_html_e('Subject lines', 'subscriber-notifications'); ?></h2>
    <p>
        <?php
        printf(
            esc_html__('In subject lines, prefer shortcodes that output plain text. When a shortcode supports a %s attribute, use the plain option (for example %s).', 'subscriber-notifications'),
            '<code>format</code>',
            '<code>[selected_subscriptions format="plain"]</code>'
        );
        ?>
    </p>

    <h2><?php esc_html_e("Your site's slugs", 'subscriber-notifications'); ?></h2>
    <p>
        <?php esc_html_e("Copy slugs from here when writing shortcode attributes — don't guess or retype them. Slug formats vary by source: ACF-created post types usually use dashes (for example glossary-term), while The Events Calendar uses underscores (for example tribe_events). Always copy the exact text below.", 'subscriber-notifications'); ?>
    </p>

    <?php if (empty($enabled_post_types)) : ?>
        <p>
            <em>
                <?php
                printf(
                    wp_kses(
                        __('No post types are enabled yet. Configure them under <a href="%s">Notifications &rarr; Content Types</a>.', 'subscriber-notifications'),
                        array('a' => array('href' => true))
                    ),
                    esc_url($content_types_url)
                );
                ?>
            </em>
        </p>
    <?php else : ?>
        <ul class="sn-slug-list">
            <?php foreach ($enabled_post_types as $post_type) :
                $post_type_label = SubscriberNotifications_Content_Config::get_post_type_label($post_type);
                $form_taxonomies = SubscriberNotifications_Content_Config::get_form_taxonomies($post_type);
                ?>
                <li>
                    <strong><?php echo esc_html($post_type_label); ?></strong> &mdash;
                    <code><?php echo esc_html($post_type); ?></code>
                    <?php if (!empty($form_taxonomies)) : ?>
                        <ul>
                            <?php foreach ($form_taxonomies as $taxonomy) :
                                $taxonomy_label = SubscriberNotifications_Content_Config::get_taxonomy_label($post_type, $taxonomy);
                                ?>
                                <li>
                                    <?php echo esc_html($taxonomy_label); ?> &mdash;
                                    <code><?php echo esc_html($taxonomy); ?></code>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p>
        <strong><?php esc_html_e('Term slugs', 'subscriber-notifications'); ?></strong>
        <?php esc_html_e('are not listed here. To find a term slug, edit the term in WordPress; the slug appears in the Slug field.', 'subscriber-notifications'); ?>
    </p>

    <h2><?php esc_html_e('Subscriber', 'subscriber-notifications'); ?></h2>
    <p>
        <?php esc_html_e('These have no attributes — just paste them as-is. All three output plain text (no HTML, no links).', 'subscriber-notifications'); ?>
    </p>
    <table class="widefat striped sn-shortcode-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Shortcode', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Output', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Example', 'subscriber-notifications'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[subscriber_name]</code></td>
                <td><?php esc_html_e("The subscriber's name, as stored on their profile", 'subscriber-notifications'); ?></td>
                <td><code>Jane Doe</code></td>
            </tr>
            <tr>
                <td><code>[subscriber_email]</code></td>
                <td><?php esc_html_e("The subscriber's email address (plain text, not a mailto: link)", 'subscriber-notifications'); ?></td>
                <td><code>jane@example.com</code></td>
            </tr>
            <tr>
                <td><code>[delivery_frequency]</code></td>
                <td><?php esc_html_e("The subscriber's chosen frequency, capitalized", 'subscriber-notifications'); ?></td>
                <td><code>Daily</code> / <code>Weekly</code> / <code>Monthly</code></td>
            </tr>
        </tbody>
    </table>
    <p><strong><?php esc_html_e('Example', 'subscriber-notifications'); ?>:</strong></p>
    <pre class="sn-shortcode-code">Hello [subscriber_name], here are your [delivery_frequency] updates.</pre>

    <h2><?php esc_html_e('Site and preferences', 'subscriber-notifications'); ?></h2>
    <table class="widefat striped sn-shortcode-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Shortcode', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Output', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Attributes', 'subscriber-notifications'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[site_title]</code></td>
                <td><?php esc_html_e("Your site's name", 'subscriber-notifications'); ?></td>
                <td><?php esc_html_e('None', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>[manage_preferences_link]</code></td>
                <td>
                    <?php
                    printf(
                        esc_html__("A clickable link to that subscriber's personal preferences page. Default link text is %s. Each subscriber's URL is unique.", 'subscriber-notifications'),
                        '<strong>' . esc_html__('Manage Preferences', 'subscriber-notifications') . '</strong>'
                    );
                    ?>
                </td>
                <td>
                    <code>text</code>
                    <em>(<?php esc_html_e('Optional, default:', 'subscriber-notifications'); ?> <code>Manage Preferences</code>)</em> &mdash;
                    <?php esc_html_e('custom link text', 'subscriber-notifications'); ?>
                </td>
            </tr>
        </tbody>
    </table>
    <p><strong><?php esc_html_e('Examples', 'subscriber-notifications'); ?>:</strong></p>
    <pre class="sn-shortcode-code">[manage_preferences_link]
[manage_preferences_link text="Manage your subscriptions"]</pre>

    <h2><code>[selected_subscriptions]</code></h2>
    <p>
        <?php esc_html_e('Inserts a formatted list of everything the subscriber selected — post types, taxonomies, and terms. Useful in welcome emails and notification bodies to remind the subscriber what they signed up for.', 'subscriber-notifications'); ?>
    </p>
    <h3><?php esc_html_e('Attributes', 'subscriber-notifications'); ?></h3>
    <table class="widefat striped sn-shortcode-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Attribute', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Values', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Notes', 'subscriber-notifications'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>format</code></td>
                <td><code>html</code> (<?php esc_html_e('default', 'subscriber-notifications'); ?>), <code>plain</code></td>
                <td><?php esc_html_e("Use html in the email body. Use plain in email subject lines or anywhere you don't want HTML.", 'subscriber-notifications'); ?></td>
            </tr>
        </tbody>
    </table>
    <p><strong><?php esc_html_e('Examples', 'subscriber-notifications'); ?>:</strong></p>
    <pre class="sn-shortcode-code">[selected_subscriptions]
[selected_subscriptions format="plain"]</pre>

    <h2><code>[selected_terms]</code></h2>
    <p>
        <?php esc_html_e('Inserts a comma-separated list of term names from one taxonomy that the subscriber selected. Use this when you only want the term names from a specific taxonomy (for example, just the post categories they chose) instead of the full subscription summary.', 'subscriber-notifications'); ?>
    </p>
    <h3><?php esc_html_e('Attributes', 'subscriber-notifications'); ?></h3>
    <table class="widefat striped sn-shortcode-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Attribute', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Values', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Notes', 'subscriber-notifications'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>taxonomy</code></td>
                <td><?php esc_html_e('taxonomy slug (e.g.', 'subscriber-notifications'); ?> <code>category</code>, <code>tribe_events_cat</code>)</td>
                <td>
                    <strong><?php esc_html_e('Required.', 'subscriber-notifications'); ?></strong>
                    <?php esc_html_e("Copy from the Your site's slugs panel above.", 'subscriber-notifications'); ?>
                </td>
            </tr>
            <tr>
                <td><code>post_type</code></td>
                <td><?php esc_html_e('post type slug (e.g.', 'subscriber-notifications'); ?> <code>post</code>, <code>tribe_events</code>)</td>
                <td><?php esc_html_e('Optional. If omitted, terms are combined across post types for that taxonomy.', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>separator</code></td>
                <td><?php esc_html_e('any text', 'subscriber-notifications'); ?></td>
                <td><?php esc_html_e('Optional. Default:', 'subscriber-notifications'); ?> <code>, </code> (<?php esc_html_e('comma + space', 'subscriber-notifications'); ?>)</td>
            </tr>
        </tbody>
    </table>
    <p><strong><?php esc_html_e('Examples', 'subscriber-notifications'); ?>:</strong></p>
    <pre class="sn-shortcode-code">[selected_terms taxonomy="category"]
[selected_terms post_type="post" taxonomy="category"]
[selected_terms post_type="tribe_events" taxonomy="tribe_events_cat" separator=" &#8226; "]</pre>

    <h2><code>[content_feed]</code></h2>
    <p>
        <?php
        printf(
            esc_html__('Builds a personalized list of posts for each subscriber, based on their selections, the notification\'s target terms, and posts marked %s in the editor. This is the main shortcode for sending a digest where each subscriber sees only what\'s relevant to them.', 'subscriber-notifications'),
            '<strong>' . esc_html__('Notify subscribers', 'subscriber-notifications') . '</strong>'
        );
        ?>
    </p>
    <h3><?php esc_html_e('Attributes', 'subscriber-notifications'); ?></h3>
    <table class="widefat striped sn-shortcode-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Attribute', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Values', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Notes', 'subscriber-notifications'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>post_type</code></td>
                <td><?php esc_html_e('post type slug (e.g.', 'subscriber-notifications'); ?> <code>post</code>, <code>tribe_events</code>)</td>
                <td>
                    <strong><?php esc_html_e('Required.', 'subscriber-notifications'); ?></strong>
                    <?php esc_html_e('Must be enabled under Notifications → Content Types.', 'subscriber-notifications'); ?>
                </td>
            </tr>
            <tr>
                <td><code>taxonomy</code></td>
                <td><?php esc_html_e('taxonomy slug (e.g.', 'subscriber-notifications'); ?> <code>category</code>, <code>tribe_events_cat</code>)</td>
                <td><?php esc_html_e('Optional. Filter the feed on one taxonomy. Omit to match any subscribed taxonomy for that post type.', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>terms</code></td>
                <td><?php esc_html_e('comma-separated term slugs (e.g.', 'subscriber-notifications'); ?> <code>news,announcements</code>)</td>
                <td>
                    <?php esc_html_e('Optional.', 'subscriber-notifications'); ?>
                    <strong><?php esc_html_e('Requires taxonomy.', 'subscriber-notifications'); ?></strong>
                    <?php esc_html_e('Each subscriber only sees slugs they also selected. Alias:', 'subscriber-notifications'); ?>
                    <code>term</code> (<?php esc_html_e('same value', 'subscriber-notifications'); ?>).
                </td>
            </tr>
            <tr>
                <td><code>duration</code></td>
                <td><code>1day</code>, <code>1week</code>, <code>1month</code></td>
                <td><?php esc_html_e('Optional. How far back to look. Default:', 'subscriber-notifications'); ?> <code>1month</code>.</td>
            </tr>
            <tr>
                <td><code>limit</code></td>
                <td><?php esc_html_e('number', 'subscriber-notifications'); ?></td>
                <td><?php esc_html_e('Optional. Maximum posts in the feed. Default:', 'subscriber-notifications'); ?> <code>10</code>.</td>
            </tr>
            <tr>
                <td><code>format</code></td>
                <td><code>list</code>, <code>summary</code></td>
                <td>
                    <?php esc_html_e('Optional.', 'subscriber-notifications'); ?>
                    <code>list</code> = <?php esc_html_e('bulleted title links;', 'subscriber-notifications'); ?>
                    <code>summary</code> = <?php esc_html_e('title link + short excerpt.', 'subscriber-notifications'); ?>
                    <?php esc_html_e('Default:', 'subscriber-notifications'); ?> <code>list</code>.
                </td>
            </tr>
        </tbody>
    </table>

    <h3><?php esc_html_e('How targeting works', 'subscriber-notifications'); ?></h3>
    <ul class="sn-targeting-rules">
        <li>
            <strong><?php esc_html_e('Notification targets', 'subscriber-notifications'); ?></strong>
            <?php esc_html_e('decide who receives the email.', 'subscriber-notifications'); ?>
        </li>
        <li>
            <code>[content_feed]</code>
            <?php esc_html_e('decides which posts appear in the message.', 'subscriber-notifications'); ?>
        </li>
        <li>
            <strong><?php esc_html_e('Without terms:', 'subscriber-notifications'); ?></strong>
            <?php esc_html_e("the feed uses the subscriber's choices together with the notification's target terms.", 'subscriber-notifications'); ?>
        </li>
        <li>
            <strong><?php esc_html_e('With terms:', 'subscriber-notifications'); ?></strong>
            <?php esc_html_e('the feed is limited to those slugs; each subscriber still only sees slugs they subscribed to.', 'subscriber-notifications'); ?>
        </li>
    </ul>

    <p><strong><?php esc_html_e('Examples', 'subscriber-notifications'); ?>:</strong></p>
    <pre class="sn-shortcode-code">[content_feed post_type="post" taxonomy="category" duration="1week" format="list"]

[content_feed post_type="tribe_events" taxonomy="tribe_events_cat" terms="family-events,concerts,kids-events" duration="1week" format="summary"]

[content_feed post_type="project" duration="1day" limit="10" format="list"]</pre>

    <h2><code>[subscriber_notifications_form]</code></h2>
    <p>
        <?php
        printf(
            esc_html__('Displays the subscribe / preferences form on a public page so visitors can sign up. %s — do not use in notification email subject or body. Configure which post types and taxonomies appear on the form under %s.', 'subscriber-notifications'),
            '<strong>' . esc_html__('Public website only', 'subscriber-notifications') . '</strong>',
            '<a href="' . esc_url($content_types_url) . '"><strong>' . esc_html__('Notifications → Content Types', 'subscriber-notifications') . '</strong></a>'
        );
        ?>
    </p>
    <h3><?php esc_html_e('Attributes', 'subscriber-notifications'); ?></h3>
    <table class="widefat striped sn-shortcode-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Attribute', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Values', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Notes', 'subscriber-notifications'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>title</code></td>
                <td><?php esc_html_e('any text', 'subscriber-notifications'); ?></td>
                <td><?php esc_html_e('Optional. Heading shown above the form.', 'subscriber-notifications'); ?></td>
            </tr>
        </tbody>
    </table>
    <p><strong><?php esc_html_e('Example', 'subscriber-notifications'); ?>:</strong></p>
    <pre class="sn-shortcode-code">[subscriber_notifications_form title="Subscribe to updates"]</pre>

    <h2><?php esc_html_e('Where shortcodes work', 'subscriber-notifications'); ?></h2>
    <ul class="sn-where-list">
        <li><?php esc_html_e('Notifications → Add New / Edit (subject and body)', 'subscriber-notifications'); ?></li>
        <li><?php esc_html_e('Settings → Email Templates (welcome and preference emails)', 'subscriber-notifications'); ?></li>
        <li><?php esc_html_e('Settings → Email Design (global header and footer on all notification emails)', 'subscriber-notifications'); ?></li>
    </ul>
    <p>
        <?php
        printf(
            esc_html__('Use %s on a notification to test layout. Shortcodes are filled with sample subscriber data.', 'subscriber-notifications'),
            '<strong>' . esc_html__('Send Preview Email', 'subscriber-notifications') . '</strong>'
        );
        ?>
    </p>
</div>
