<?php
/**
 * Shortcode system for dynamic content
 *
 * @package SubscriberNotifications
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcodes class for dynamic content.
 *
 * v3 shortcodes are content-type agnostic and driven by the configured post
 * types/taxonomies in {@see SubscriberNotifications_Content_Config}.
 */
class SubscriberNotifications_Shortcodes {

    public function __construct() {
        $this->register_shortcodes();
    }

    /**
     * Register shortcodes.
     */
    public function register_shortcodes() {
        add_shortcode('subscriber_name', array($this, 'subscriber_name_shortcode'));
        add_shortcode('subscriber_email', array($this, 'subscriber_email_shortcode'));
        add_shortcode('delivery_frequency', array($this, 'delivery_frequency_shortcode'));
        add_shortcode('selected_subscriptions', array($this, 'selected_subscriptions_shortcode'));
        add_shortcode('selected_terms', array($this, 'selected_terms_shortcode'));
        add_shortcode('content_feed', array($this, 'content_feed_shortcode'));
        add_shortcode('site_title', array($this, 'site_title_shortcode'));
        add_shortcode('manage_preferences_link', array($this, 'manage_preferences_link_shortcode'));
    }

    /**
     * Subscriber name shortcode.
     */
    public function subscriber_name_shortcode($atts, $content = '', $tag = '') {
        global $subscriber_notifications_current_subscriber;

        if (isset($subscriber_notifications_current_subscriber)) {
            return esc_html($subscriber_notifications_current_subscriber->name);
        }

        return __('[Subscriber Name]', 'subscriber-notifications');
    }

    /**
     * Subscriber email shortcode.
     */
    public function subscriber_email_shortcode($atts, $content = '', $tag = '') {
        global $subscriber_notifications_current_subscriber;

        if (isset($subscriber_notifications_current_subscriber)) {
            return esc_html($subscriber_notifications_current_subscriber->email);
        }

        return __('[Subscriber Email]', 'subscriber-notifications');
    }

    /**
     * Delivery frequency shortcode.
     */
    public function delivery_frequency_shortcode($atts, $content = '', $tag = '') {
        global $subscriber_notifications_current_subscriber;

        if (isset($subscriber_notifications_current_subscriber)) {
            return esc_html(ucfirst(str_replace('_', ' ', $subscriber_notifications_current_subscriber->frequency)));
        }

        return __('[Delivery Frequency]', 'subscriber-notifications');
    }

    /**
     * Render a full human-readable summary of the subscriber's selections.
     *
     * Replaces v2 [selected_news_categories] + [selected_meeting_categories].
     *
     * Attributes:
     * - format="html" (default) — post type heading and taxonomy lines with bold labels (email body).
     * - format="plain" — plain text with line breaks (email subject lines).
     */
    public function selected_subscriptions_shortcode($atts, $content = '', $tag = '') {
        global $subscriber_notifications_current_subscriber;

        $atts = shortcode_atts(array(
            'format' => 'html',
        ), $atts, 'selected_subscriptions');

        if (!isset($subscriber_notifications_current_subscriber)) {
            return __('[Selected Subscriptions]', 'subscriber-notifications');
        }

        $prefs = $subscriber_notifications_current_subscriber->subscription_preferences ?? '';

        if ($atts['format'] === 'plain') {
            $summary = SubscriberNotifications_Preferences::human_readable($prefs);
            return $summary !== '' ? esc_html($summary) : __('No subscriptions selected', 'subscriber-notifications');
        }

        $summary = SubscriberNotifications_Preferences::human_readable_html($prefs);
        return $summary !== '' ? wp_kses_post($summary) : __('No subscriptions selected', 'subscriber-notifications');
    }

    /**
     * Render selected term names for one taxonomy.
     *
     * Usage: [selected_terms post_type="post" taxonomy="category"].
     * If post_type is omitted the term IDs are aggregated across all post types
     * for the given taxonomy.
     */
    public function selected_terms_shortcode($atts, $content = '', $tag = '') {
        global $subscriber_notifications_current_subscriber;

        $atts = shortcode_atts(array(
            'post_type' => '',
            'taxonomy' => '',
            'separator' => ', ',
        ), $atts, $tag);

        if (empty($atts['taxonomy'])) {
            return '';
        }

        if (!isset($subscriber_notifications_current_subscriber)) {
            return __('[Selected Terms]', 'subscriber-notifications');
        }

        $prefs = SubscriberNotifications_Preferences::decode(
            $subscriber_notifications_current_subscriber->subscription_preferences ?? ''
        );

        $term_ids = array();
        if (!empty($atts['post_type'])) {
            if (isset($prefs[$atts['post_type']][$atts['taxonomy']])) {
                $term_ids = (array) $prefs[$atts['post_type']][$atts['taxonomy']];
            }
        } else {
            foreach ($prefs as $post_type => $tax_map) {
                if (isset($tax_map[$atts['taxonomy']])) {
                    $term_ids = array_merge($term_ids, (array) $tax_map[$atts['taxonomy']]);
                }
            }
            $term_ids = array_values(array_unique(array_map('intval', $term_ids)));
        }

        if (empty($term_ids)) {
            return '';
        }

        $names = array();
        foreach ($term_ids as $term_id) {
            $term = get_term((int) $term_id, $atts['taxonomy']);
            if ($term && !is_wp_error($term)) {
                $names[] = $term->name;
            }
        }

        return esc_html(implode($atts['separator'], $names));
    }

    /**
     * Generic content feed shortcode.
     *
     * Usage: [content_feed post_type="post" taxonomy="category" duration="1month" limit="10" format="list"]
     *        [content_feed post_type="project" duration="1day" limit="5" format="list"]
     *        format="list" (title links) or format="summary" (title + excerpt per post).
     *
     * With taxonomy: posts matching that taxonomy's subscriber (or notification) terms.
     * With taxonomy + terms: comma-separated term slugs scope the feed; output is slugs ∩ subscriber.
     * Without taxonomy: posts matching subscriber/notification terms in any form taxonomy
     * for the post type (OR — a match in one taxonomy is enough).
     */
    public function content_feed_shortcode($atts, $content = '', $tag = '') {
        $atts = shortcode_atts(array(
            'post_type' => 'post',
            'taxonomy' => '',
            'terms' => '',
            'term' => '',
            'duration' => '1month',
            'limit' => 10,
            'format' => 'list',
        ), $atts, $tag);

        // Accept singular `term` (common typo); canonical attribute is `terms`.
        if ($atts['terms'] === '' && $atts['term'] !== '') {
            $atts['terms'] = $atts['term'];
        }

        $post_type = sanitize_key($atts['post_type']);
        $taxonomy = sanitize_key($atts['taxonomy']);

        if (empty($post_type)) {
            return '';
        }

        if (!empty($atts['terms']) && empty($taxonomy)) {
            return '';
        }

        if (!class_exists('SubscriberNotifications_Content_Config')
            || !in_array($post_type, SubscriberNotifications_Content_Config::get_enabled_post_types(), true)) {
            return '';
        }

        $cutoff_date = $this->get_cutoff_date($atts['duration']);

        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => max(1, (int) $atts['limit']),
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_subscriber_notifications_include_in_feed',
                    'value' => '1',
                    'compare' => '=',
                ),
                array(
                    'key' => '_subscriber_notifications_last_notification_date',
                    'value' => $cutoff_date,
                    'compare' => '>=',
                    // Keep this as a plain string comparison for cross-DB compatibility.
                    // Values are stored as Y-m-d H:i:s, which sorts lexicographically in date order.
                ),
            ),
        );

        if (!empty($taxonomy)) {
            $term_ids = $this->resolve_feed_term_ids($atts, $post_type, $taxonomy);
            if (empty($term_ids)) {
                return '';
            }
            $args['tax_query'] = array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => array_map('intval', $term_ids),
                    'include_children' => false,
                ),
            );
        } else {
            $tax_query = $this->build_multi_taxonomy_feed_tax_query($atts, $post_type);
            if ($tax_query === null) {
                return '';
            }
            $args['tax_query'] = $tax_query;
        }

        $posts = get_posts($args);

        if (empty($posts)) {
            return '';
        }

        $format = $this->normalize_feed_format($atts['format']);
        return $this->render_feed_output($posts, $format);
    }

    /**
     * Site title shortcode.
     */
    public function site_title_shortcode($atts, $content = '', $tag = '') {
        return esc_html(get_bloginfo('name'));
    }

    /**
     * Manage preferences link shortcode.
     */
    public function manage_preferences_link_shortcode($atts, $content = '', $tag = '') {
        global $subscriber_notifications_current_subscriber;

        $atts = shortcode_atts(array(
            'text' => __('Manage Preferences', 'subscriber-notifications'),
        ), $atts, $tag);

        if (!isset($subscriber_notifications_current_subscriber) || empty($subscriber_notifications_current_subscriber->id)) {
            return __('[Manage Preferences Link]', 'subscriber-notifications');
        }

        if (!subscriber_notifications_preferences_page_is_configured()) {
            return __('[Manage Preferences Link]', 'subscriber-notifications');
        }

        $subscriber = $subscriber_notifications_current_subscriber;
        $database = new SubscriberNotifications_Database();
        $fresh_subscriber = $database->get_subscriber($subscriber->id);
        if (!$fresh_subscriber) {
            return __('[Manage Preferences Link]', 'subscriber-notifications');
        }

        $token = $fresh_subscriber->management_token;
        if (empty($token)) {
            $token = wp_generate_password(32, false);
            $database->update_subscriber($subscriber->id, array('management_token' => $token));
        }

        $manage_url = subscriber_notifications_get_preferences_page_url(array(
            'token' => $token,
        ));

        if ($manage_url === '') {
            return __('[Manage Preferences Link]', 'subscriber-notifications');
        }

        return '<a href="' . esc_url($manage_url) . '">' . esc_html($atts['text']) . '</a>';
    }

    /**
     * Build an OR tax_query across all form taxonomies for a post type (omit taxonomy attribute).
     *
     * @param array  $atts      Shortcode attributes.
     * @param string $post_type Post type slug.
     * @return array|null tax_query array, or null when no taxonomy has matching term IDs.
     */
    private function build_multi_taxonomy_feed_tax_query($atts, $post_type) {
        $taxonomies = SubscriberNotifications_Content_Config::get_form_taxonomies($post_type);
        if (empty($taxonomies)) {
            return null;
        }

        $tax_query = array('relation' => 'OR');
        $has_clause = false;

        foreach ($taxonomies as $taxonomy) {
            $term_ids = $this->resolve_feed_term_ids($atts, $post_type, $taxonomy);
            if (empty($term_ids)) {
                continue;
            }
            $tax_query[] = array(
                'taxonomy'         => $taxonomy,
                'field'            => 'term_id',
                'terms'            => array_map('intval', $term_ids),
                'include_children' => false,
            );
            $has_clause = true;
        }

        if (!$has_clause) {
            return null;
        }

        return $tax_query;
    }

    /**
     * Resolve term IDs for a content_feed call based on attributes and globals.
     */
    private function resolve_feed_term_ids($atts, $post_type, $taxonomy) {
        global $subscriber_notifications_current_subscriber, $subscriber_notifications_current_notification;

        if (empty($taxonomy)) {
            return array();
        }

        $subscriber_ids = array();
        if (isset($subscriber_notifications_current_subscriber)) {
            $subscriber_ids = $this->extract_term_ids(
                $subscriber_notifications_current_subscriber->subscription_preferences ?? '',
                $post_type,
                $taxonomy
            );
        }

        if (!empty($atts['terms'])) {
            $scope_ids = $this->resolve_term_slugs_to_ids($atts['terms'], $taxonomy);
            if (empty($scope_ids) || empty($subscriber_ids)) {
                return array();
            }
            return array_values(array_intersect($scope_ids, $subscriber_ids));
        }

        $notification_ids = array();

        if (isset($subscriber_notifications_current_notification)
            && !empty($subscriber_notifications_current_notification->target_preferences)) {
            $notification_ids = $this->extract_term_ids(
                $subscriber_notifications_current_notification->target_preferences,
                $post_type,
                $taxonomy
            );
        }

        // If both sides are present, narrow to the intersection so the feed mirrors targeting.
        if (!empty($subscriber_ids) && !empty($notification_ids)) {
            return array_values(array_intersect($subscriber_ids, $notification_ids));
        }

        if (!empty($subscriber_ids)) {
            return $subscriber_ids;
        }

        return $notification_ids;
    }

    /**
     * Resolve comma-separated term slugs to term IDs for a taxonomy.
     * Unknown slugs are skipped.
     *
     * @param string $terms_attr Comma-separated term slugs.
     * @param string $taxonomy   Taxonomy slug.
     * @return int[]
     */
    private function resolve_term_slugs_to_ids($terms_attr, $taxonomy) {
        if ($terms_attr === '' || $terms_attr === null || empty($taxonomy)) {
            return array();
        }

        $ids = array();
        foreach (array_map('trim', explode(',', (string) $terms_attr)) as $raw_slug) {
            if ($raw_slug === '') {
                continue;
            }
            $slug = sanitize_title($raw_slug);
            if ($slug === '') {
                continue;
            }
            $term = get_term_by('slug', $slug, $taxonomy);
            if ($term && !is_wp_error($term)) {
                $ids[(int) $term->term_id] = (int) $term->term_id;
            }
        }

        return array_values($ids);
    }

    /**
     * Decode preferences and pluck term IDs for a (post_type, taxonomy) pair.
     */
    private function extract_term_ids($prefs, $post_type, $taxonomy) {
        $decoded = SubscriberNotifications_Preferences::decode($prefs);
        if (empty($decoded[$post_type][$taxonomy]) || !is_array($decoded[$post_type][$taxonomy])) {
            return array();
        }
        return array_values(array_unique(array_map('intval', $decoded[$post_type][$taxonomy])));
    }

    /**
     * Convert a duration label to a MySQL cutoff datetime string.
     */
    private function get_cutoff_date($duration) {
        $tz = wp_timezone();
        switch ($duration) {
            case '1day':
                $cutoff = (new DateTimeImmutable('now', $tz))->modify('-1 day');
                break;
            case '1week':
                $cutoff = (new DateTimeImmutable('now', $tz))->modify('-1 week');
                break;
            case '1month':
            default:
                $cutoff = (new DateTimeImmutable('now', $tz))->modify('-1 month');
        }
        return $cutoff->format('Y-m-d H:i:s');
    }

    /**
     * Allowed values for the content_feed format attribute.
     *
     * @param string $format Raw format attribute.
     * @return string list|summary
     */
    private function normalize_feed_format($format) {
        $format = sanitize_key($format);
        return ($format === 'summary') ? 'summary' : 'list';
    }

    /**
     * Render the feed posts as HTML in the requested format.
     *
     * @param array  $posts  Post objects.
     * @param string $format list|summary
     */
    private function render_feed_output(array $posts, $format) {
        if ($format === 'list') {
            $out = '<ul>';
            foreach ($posts as $post) {
                $title = $this->format_post_title_with_update_date($post);
                $out .= '<li><a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html($title) . '</a></li>';
            }
            $out .= '</ul>';
            return $out;
        }

        $out = '';
        foreach ($posts as $post) {
            $title = $this->format_post_title_with_update_date($post);
            $out .= '<p><a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html($title) . '</a></p>';
            $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 20, '…');
            if ($excerpt !== '') {
                $out .= '<p>' . esc_html($excerpt) . '</p>';
            }
        }
        return $out;
    }

    /**
     * Append an update date to titles for posts that have been modified since publish.
     */
    private function format_post_title_with_update_date($post) {
        $post_date_ts = strtotime($post->post_date);
        $post_modified_ts = strtotime($post->post_modified);
        $is_new_post = abs($post_modified_ts - $post_date_ts) <= 5;

        if ($is_new_post) {
            return $post->post_title;
        }

        $last_notified = get_post_meta($post->ID, '_subscriber_notifications_last_notification_date', true);
        $timezone = wp_timezone();

        if ($last_notified) {
            try {
                $datetime = new DateTime($last_notified, $timezone);
                $formatted_date = $datetime->format('M j, Y');
            } catch (Exception $e) {
                $formatted_date = mysql2date('M j, Y', $last_notified);
            }
            return $post->post_title . ' (updated on ' . $formatted_date . ')';
        }

        try {
            $datetime = new DateTime($post->post_modified, $timezone);
            $formatted_date = $datetime->format('M j, Y');
        } catch (Exception $e) {
            $formatted_date = mysql2date('M j, Y', $post->post_modified);
        }
        return $post->post_title . ' (updated on ' . $formatted_date . ')';
    }

    /**
     * Process shortcodes in content using the provided subscriber/notification globals.
     */
    public function process_shortcodes($content, $subscriber = null, $notification = null) {
        global $subscriber_notifications_current_subscriber, $subscriber_notifications_current_notification;

        $content = wp_unslash($content);

        $subscriber_notifications_current_subscriber = $subscriber;
        $subscriber_notifications_current_notification = $notification;

        $content = do_shortcode($content);

        $subscriber_notifications_current_subscriber = null;
        $subscriber_notifications_current_notification = null;

        return $content;
    }
}
