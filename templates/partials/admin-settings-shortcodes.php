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
    <h2><?php esc_html_e('Where shortcodes work', 'subscriber-notifications'); ?></h2>
    <p>
        <?php esc_html_e('Shortcodes insert personalized, per-subscriber content. Paste them into the fields below — not into public pages unless the shortcode is listed under Public website shortcodes.', 'subscriber-notifications'); ?>
    </p>
    <ul class="sn-where-list">
        <li><?php esc_html_e('Notifications → Add New / Edit (subject and body)', 'subscriber-notifications'); ?></li>
        <li><?php esc_html_e('Settings → Email Templates (welcome, preference, and on-page subscription emails)', 'subscriber-notifications'); ?></li>
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

    <h2><?php esc_html_e('Topic notifications and subscription lists', 'subscriber-notifications'); ?></h2>
    <p>
        <?php esc_html_e('Use these in welcome emails, topic notification bodies, and other messages about taxonomy-based subscriptions. They do not require a single-post context.', 'subscriber-notifications'); ?>
    </p>

    <h3><code>[selected_subscriptions]</code></h3>
    <p>
        <?php esc_html_e('Inserts a formatted list of what the subscriber selected. By default, output includes both topic notifications (taxonomies and terms) and on-page subscriptions (linked page titles). Use the sections attribute to limit what appears — for example, topic digests should use sections="topics" so on-page items are not listed alongside new feed content.', 'subscriber-notifications'); ?>
    </p>
    <h4><?php esc_html_e('Attributes', 'subscriber-notifications'); ?></h4>
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
            <tr>
                <td><code>sections</code></td>
                <td><code>topics</code>, <code>items</code> (<?php esc_html_e('alias', 'subscriber-notifications'); ?>: <code>on-page</code>)</td>
                <td><?php esc_html_e('Optional. Comma-separated. Default: both sections. Use topics alone in topic notification emails so on-page subscriptions are not shown with the feed.', 'subscriber-notifications'); ?></td>
            </tr>
        </tbody>
    </table>
    <p><strong><?php esc_html_e('Examples', 'subscriber-notifications'); ?>:</strong></p>
    <pre class="sn-shortcode-code">[selected_subscriptions]
[selected_subscriptions sections="topics"]
[selected_subscriptions sections="items"]
[selected_subscriptions format="plain" sections="topics"]</pre>

    <h3><code>[selected_terms]</code></h3>
    <p>
        <?php esc_html_e('Inserts a comma-separated list of term names from one taxonomy that the subscriber selected. Use this when you only want the term names from a specific taxonomy (for example, just the post categories they chose) instead of the full subscription summary.', 'subscriber-notifications'); ?>
    </p>
    <h4><?php esc_html_e('Attributes', 'subscriber-notifications'); ?></h4>
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

    <h3><code>[content_feed]</code></h3>
    <p>
        <?php
        printf(
            esc_html__('Builds a personalized list of posts for each subscriber, based on their selections, the notification\'s target terms, and posts marked %s in the editor. This is the main shortcode for topic notifications where each subscriber sees only what\'s relevant to them.', 'subscriber-notifications'),
            '<strong>' . esc_html__('Include in topic notifications', 'subscriber-notifications') . '</strong>'
        );
        ?>
    </p>
    <h4><?php esc_html_e('Attributes', 'subscriber-notifications'); ?></h4>
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

    <hr class="sn-shortcodes-section-break">

    <h2><?php esc_html_e('On-page subscription emails (single post)', 'subscriber-notifications'); ?></h2>
    <p>
        <?php esc_html_e('Use only in On-page subscription confirmation and On-page update email templates (Settings → Email Templates → On-page subscriptions). The plugin sets the current post when those emails send.', 'subscriber-notifications'); ?>
    </p>
    <table class="widefat striped sn-shortcode-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Shortcode', 'subscriber-notifications'); ?></th>
                <th><?php esc_html_e('Output', 'subscriber-notifications'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>[post_title]</code></td>
                <td><?php esc_html_e('Current post title (plain text)', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>[post_link]</code></td>
                <td><?php esc_html_e('Linked post title; adds “(updated on …)” when applicable', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>[post_permalink]</code></td>
                <td><?php esc_html_e('Post URL only', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>[post_type_label]</code></td>
                <td><?php esc_html_e('Content Types display label for the post type', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>[post_excerpt]</code></td>
                <td><?php esc_html_e('Short plain excerpt', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>[selected_item_subscriptions]</code></td>
                <td><?php esc_html_e('List of posts the subscriber follows via on-page subscriptions', 'subscriber-notifications'); ?></td>
            </tr>
        </tbody>
    </table>

    <hr class="sn-shortcodes-section-break">

    <h2><?php esc_html_e('Public website shortcodes', 'subscriber-notifications'); ?></h2>
    <p>
        <?php esc_html_e('Place these on WordPress pages. Do not use them in email subjects or bodies.', 'subscriber-notifications'); ?>
    </p>

    <h3><code>[subscriber_notifications_form]</code></h3>
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

    <h3><code>[subscriber_notifications_preferences]</code></h3>
    <p>
        <?php
        printf(
            esc_html__('Displays the manage-preferences form on a public page. %s — do not use in notification email subject or body. Select the page under %s. Email manage links use that page with a unique %s query parameter.', 'subscriber-notifications'),
            '<strong>' . esc_html__('Public website only', 'subscriber-notifications') . '</strong>',
            '<a href="' . esc_url(admin_url('admin.php?page=subscriber-notifications-settings&tab=general')) . '"><strong>' . esc_html__('Settings → General → Frontend pages', 'subscriber-notifications') . '</strong></a>',
            '<code>token</code>'
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
                <td><?php esc_html_e('Optional. Rarely needed when the page already has a title.', 'subscriber-notifications'); ?></td>
            </tr>
        </tbody>
    </table>
    <p><strong><?php esc_html_e('Example', 'subscriber-notifications'); ?>:</strong></p>
    <pre class="sn-shortcode-code">[subscriber_notifications_preferences]</pre>

    <h3><code>[subscriber_notifications_post_subscribe]</code></h3>
    <p>
        <?php
        printf(
            wp_kses(
                __('On-page widget to subscribe to updates for the current post. Place on singular block templates where on-page subscriptions are enabled under %s. Never shown on the subscribe or preferences pages configured under Settings → General → Frontend pages.', 'subscriber-notifications'),
                array('a' => array('href' => true))
            ),
            '<a href="' . esc_url($content_types_url) . '"><strong>' . esc_html__('Notifications → Content Types', 'subscriber-notifications') . '</strong></a>'
        );
        ?>
    </p>
    <h4><?php esc_html_e('Content eligibility', 'subscriber-notifications'); ?></h4>
    <p>
        <?php esc_html_e('Configure where the on-page subscribe widget appears under Notifications → Content Types. Choose By content rules to match taxonomy term rules with optional Except on these posts, or Only specific posts I choose for an explicit pick list.', 'subscriber-notifications'); ?>
    </p>
    <h4><?php esc_html_e('Copy overrides (plain text)', 'subscriber-notifications'); ?></h4>
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
                <td><code>heading</code></td>
                <td><?php esc_html_e('any text', 'subscriber-notifications'); ?></td>
                <td><?php esc_html_e('Subscribe form heading. Leave empty for the default.', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>description</code></td>
                <td><?php esc_html_e('any text', 'subscriber-notifications'); ?></td>
                <td><?php esc_html_e('Subscribe form description. Leave empty for the default.', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>button</code></td>
                <td><?php esc_html_e('any text', 'subscriber-notifications'); ?></td>
                <td><?php esc_html_e('Subscribe button label. Leave empty for the default.', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>heading_subscribed</code></td>
                <td><?php esc_html_e('any text', 'subscriber-notifications'); ?></td>
                <td><?php esc_html_e('Heading when the visitor is already subscribed.', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>description_subscribed</code></td>
                <td><?php esc_html_e('any text', 'subscriber-notifications'); ?></td>
                <td><?php esc_html_e('Description when the visitor is already subscribed.', 'subscriber-notifications'); ?></td>
            </tr>
            <tr>
                <td><code>button_manage</code></td>
                <td><?php esc_html_e('any text', 'subscriber-notifications'); ?></td>
                <td><?php esc_html_e('Manage link label for logged-in subscribers.', 'subscriber-notifications'); ?></td>
            </tr>
        </tbody>
    </table>
    <p class="description">
        <?php esc_html_e('Leave copy attributes empty to use the plugin defaults (post title and singular content type label, e.g. “Post”). Overrides are plain text only — no shortcodes or tokens.', 'subscriber-notifications'); ?>
    </p>
    <p><strong><?php esc_html_e('Examples', 'subscriber-notifications'); ?>:</strong></p>
    <pre class="sn-shortcode-code">[subscriber_notifications_post_subscribe]
[subscriber_notifications_post_subscribe heading="Get updates" description="We will email you when this page changes." button="Sign up"]</pre>

</div>
