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
 * Shortcodes class for dynamic content
 */
class SubscriberNotifications_Shortcodes {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->register_shortcodes();
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('subscriber_name', array($this, 'subscriber_name_shortcode'));
        add_shortcode('subscriber_email', array($this, 'subscriber_email_shortcode'));
        add_shortcode('selected_news_categories', array($this, 'selected_news_categories_shortcode'));
        add_shortcode('selected_meeting_categories', array($this, 'selected_meeting_categories_shortcode'));
        add_shortcode('delivery_frequency', array($this, 'delivery_frequency_shortcode'));
        add_shortcode('news_feed', array($this, 'news_feed_shortcode'));
        add_shortcode('meetings_feed', array($this, 'meetings_feed_shortcode'));
        add_shortcode('site_title', array($this, 'site_title_shortcode'));
        add_shortcode('manage_preferences_link', array($this, 'manage_preferences_link_shortcode'));
    }
    
    /**
     * Subscriber name shortcode
     */
    public function subscriber_name_shortcode($atts, $content = '', $tag = '') {
        global $subscriber_notifications_current_subscriber;
        
        if (isset($subscriber_notifications_current_subscriber)) {
            return esc_html($subscriber_notifications_current_subscriber->name);
        }
        
        return __('[Subscriber Name]', 'subscriber-notifications');
    }
    
    /**
     * Subscriber email shortcode
     */
    public function subscriber_email_shortcode($atts, $content = '', $tag = '') {
        global $subscriber_notifications_current_subscriber;
        
        if (isset($subscriber_notifications_current_subscriber)) {
            return esc_html($subscriber_notifications_current_subscriber->email);
        }
        
        return __('[Subscriber Email]', 'subscriber-notifications');
    }
    
    /**
     * Selected news categories shortcode
     */
    public function selected_news_categories_shortcode($atts, $content = '', $tag = '') {
        global $subscriber_notifications_current_subscriber;
        
        if (isset($subscriber_notifications_current_subscriber)) {
            $category_ids = explode(',', $subscriber_notifications_current_subscriber->news_categories);
            $category_names = array();
            
            foreach ($category_ids as $cat_id) {
                if ($cat_id) {
                    $category = get_category($cat_id);
                    if ($category) {
                        $category_names[] = $category->name;
                    }
                }
            }
            
            return esc_html(implode(', ', $category_names));
        }
        
        return __('[Selected News Categories]', 'subscriber-notifications');
    }
    
    /**
     * Selected meeting categories shortcode
     */
    public function selected_meeting_categories_shortcode($atts, $content = '', $tag = '') {
        global $subscriber_notifications_current_subscriber;
        
        if (isset($subscriber_notifications_current_subscriber)) {
            $category_ids = explode(',', $subscriber_notifications_current_subscriber->meeting_categories);
            $category_names = array();
            
            foreach ($category_ids as $cat_id) {
                if ($cat_id) {
                    $category = get_term($cat_id, 'tribe_events_cat');
                    if ($category) {
                        $category_names[] = $category->name;
                    }
                }
            }
            
            return esc_html(implode(', ', $category_names));
        }
        
        return __('[Selected Meeting Categories]', 'subscriber-notifications');
    }
    
    /**
     * Delivery frequency shortcode
     */
    public function delivery_frequency_shortcode($atts, $content = '', $tag = '') {
        global $subscriber_notifications_current_subscriber;
        
        if (isset($subscriber_notifications_current_subscriber)) {
            return esc_html(ucfirst(str_replace('_', ' ', $subscriber_notifications_current_subscriber->frequency)));
        }
        
        return __('[Delivery Frequency]', 'subscriber-notifications');
    }
    
    /**
     * News feed shortcode
     */
    public function news_feed_shortcode($atts, $content = '', $tag = '') {
        $atts = shortcode_atts(array(
            'category' => '',
            'duration' => '1month',
            'limit' => 10,
            'format' => 'list'
        ), $atts);
        
        global $subscriber_notifications_current_subscriber, $subscriber_notifications_current_notification;
        
        // Determine categories to show
        $categories = array();
        if (!empty($atts['category'])) {
            $categories[] = $atts['category'];
        } elseif (isset($subscriber_notifications_current_subscriber)) {
            // Use subscriber's selected categories (personalized content)
            $category_ids = explode(',', $subscriber_notifications_current_subscriber->news_categories);
            foreach ($category_ids as $cat_id) {
                if ($cat_id) {
                    $category = get_category($cat_id);
                    if ($category) {
                        $categories[] = $category->slug;
                    }
                }
            }
        } elseif (isset($subscriber_notifications_current_notification) && !empty($subscriber_notifications_current_notification->news_categories)) {
            // Fallback to notification's target categories
            $category_ids = explode(',', $subscriber_notifications_current_notification->news_categories);
            foreach ($category_ids as $cat_id) {
                if ($cat_id) {
                    $category = get_category($cat_id);
                    if ($category) {
                        $categories[] = $category->slug;
                    }
                }
            }
        }
        
        if (empty($categories)) {
            return __('No news categories selected.', 'subscriber-notifications');
        }
        
        // Calculate date range and convert to MySQL datetime format
        $cutoff_timestamp = 0;
        if ($atts['duration'] === '1day') {
            $cutoff_timestamp = strtotime('1 day ago');
        } elseif ($atts['duration'] === '1week') {
            $cutoff_timestamp = strtotime('1 week ago');
        } elseif ($atts['duration'] === '1month') {
            $cutoff_timestamp = strtotime('1 month ago');
        }
        $cutoff_date = date('Y-m-d H:i:s', $cutoff_timestamp);
        
        // Query posts - filter by notification date (when checkbox was checked)
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'category_name' => implode(',', $categories),
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_subscriber_notifications_include_in_feed',
                    'value' => '1',
                    'compare' => '='
                ),
                array(
                    'key' => '_subscriber_notifications_last_notification_date',
                    'value' => $cutoff_date,
                    'compare' => '>=',
                    'type' => 'DATETIME'
                )
            )
        );
        
        $posts = get_posts($args);
        
        if (empty($posts)) {
            return __('No recent posts found.', 'subscriber-notifications');
        }
        
        // Format output with update indication
        if ($atts['format'] === 'list') {
            $output = '<ul>';
            foreach ($posts as $post) {
                $title = $this->format_post_title_with_update_date($post);
                $output .= '<li><a href="' . get_permalink($post->ID) . '">' . esc_html($title) . '</a></li>';
            }
            $output .= '</ul>';
        } else {
            $output = '';
            foreach ($posts as $post) {
                $title = $this->format_post_title_with_update_date($post);
                $output .= '<h3><a href="' . get_permalink($post->ID) . '">' . esc_html($title) . '</a></h3>';
                $output .= '<p>' . wp_trim_words($post->post_content, 20) . '</p>';
            }
        }
        
        return $output;
    }
    
    /**
     * Meetings feed shortcode
     */
    public function meetings_feed_shortcode($atts, $content = '', $tag = '') {
        $atts = shortcode_atts(array(
            'category' => '',
            'duration' => '1month',
            'limit' => 10,
            'format' => 'list'
        ), $atts);
        
        if (!class_exists('Tribe__Events__Main')) {
            return __('Events Calendar plugin not active.', 'subscriber-notifications');
        }
        
        global $subscriber_notifications_current_subscriber, $subscriber_notifications_current_notification;
        
        // Determine categories to show
        $categories = array();
        if (!empty($atts['category'])) {
            $categories[] = $atts['category'];
        } elseif (isset($subscriber_notifications_current_subscriber)) {
            // Use subscriber's selected categories (personalized content)
            $category_ids = explode(',', $subscriber_notifications_current_subscriber->meeting_categories);
            foreach ($category_ids as $cat_id) {
                if ($cat_id) {
                    $category = get_term($cat_id, 'tribe_events_cat');
                    if ($category) {
                        $categories[] = $category->slug;
                    }
                }
            }
        } elseif (isset($subscriber_notifications_current_notification) && !empty($subscriber_notifications_current_notification->meeting_categories)) {
            // Fallback to notification's target categories
            $category_ids = explode(',', $subscriber_notifications_current_notification->meeting_categories);
            foreach ($category_ids as $cat_id) {
                if ($cat_id) {
                    $category = get_term($cat_id, 'tribe_events_cat');
                    if ($category) {
                        $categories[] = $category->slug;
                    }
                }
            }
        }
        
        if (empty($categories)) {
            return __('No meeting categories selected.', 'subscriber-notifications');
        }
        
        // Calculate date range and convert to MySQL datetime format
        $cutoff_timestamp = 0;
        if ($atts['duration'] === '1day') {
            $cutoff_timestamp = strtotime('1 day ago');
        } elseif ($atts['duration'] === '1week') {
            $cutoff_timestamp = strtotime('1 week ago');
        } elseif ($atts['duration'] === '1month') {
            $cutoff_timestamp = strtotime('1 month ago');
        }
        $cutoff_date = date('Y-m-d H:i:s', $cutoff_timestamp);
        
        // Query events - filter by notification date (when checkbox was checked), not event start date
        // Suppress TEC's query filters so we can query by post metadata instead of event dates
        $args = array(
            'post_type' => 'tribe_events',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'tribe_suppress_query_filters' => true,
            'tax_query' => array(
                array(
                    'taxonomy' => 'tribe_events_cat',
                    'field' => 'slug',
                    'terms' => $categories
                )
            ),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_subscriber_notifications_include_in_feed',
                    'value' => '1',
                    'compare' => '='
                ),
                array(
                    'key' => '_subscriber_notifications_last_notification_date',
                    'value' => $cutoff_date,
                    'compare' => '>=',
                    'type' => 'DATETIME'
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $events = get_posts($args);
        
        if (empty($events)) {
            return __('No recent events found.', 'subscriber-notifications');
        }
        
        // Format output with update indication
        if ($atts['format'] === 'list') {
            $output = '<ul>';
            foreach ($events as $event) {
                $start_date = get_post_meta($event->ID, '_EventStartDate', true);
                $formatted_date = date('M j, Y', strtotime($start_date));
                $title = $this->format_post_title_with_update_date($event);
                $output .= '<li><a href="' . get_permalink($event->ID) . '">' . esc_html($title) . '</a> - ' . $formatted_date . '</li>';
            }
            $output .= '</ul>';
        } else {
            $output = '';
            foreach ($events as $event) {
                $start_date = get_post_meta($event->ID, '_EventStartDate', true);
                $formatted_date = date('M j, Y', strtotime($start_date));
                $title = $this->format_post_title_with_update_date($event);
                $output .= '<h3><a href="' . get_permalink($event->ID) . '">' . esc_html($title) . '</a></h3>';
                $output .= '<p><strong>Date:</strong> ' . $formatted_date . '</p>';
                $output .= '<p>' . wp_trim_words($event->post_content, 20) . '</p>';
            }
        }
        
        return $output;
    }
    
    /**
     * Site title shortcode
     */
    public function site_title_shortcode($atts, $content = '', $tag = '') {
        return esc_html(get_bloginfo('name'));
    }
    
    /**
     * Manage preferences link shortcode
     */
    public function manage_preferences_link_shortcode($atts, $content = '', $tag = '') {
        global $subscriber_notifications_current_subscriber;
        
        $atts = shortcode_atts(array(
            'text' => __('Manage Preferences', 'subscriber-notifications')
        ), $atts);
        
        if (!isset($subscriber_notifications_current_subscriber) || empty($subscriber_notifications_current_subscriber->id)) {
            return __('[Manage Preferences Link]', 'subscriber-notifications');
        }
        
        $subscriber = $subscriber_notifications_current_subscriber;
        $database = new SubscriberNotifications_Database();
        
        // Fetch fresh subscriber data to ensure we have the token
        $fresh_subscriber = $database->get_subscriber($subscriber->id);
        if (!$fresh_subscriber) {
            return __('[Manage Preferences Link]', 'subscriber-notifications');
        }
        
        // Get token from database
        $token = $fresh_subscriber->management_token;
        
        // Generate token if missing
        if (empty($token)) {
            $token = wp_generate_password(32, false);
            $database->update_subscriber($subscriber->id, array('management_token' => $token));
        }
        
        // Generate URL
        $manage_url = add_query_arg(array(
            'action' => 'manage',
            'token' => $token
        ), home_url());
        
        return '<a href="' . esc_url($manage_url) . '">' . esc_html($atts['text']) . '</a>';
    }
    
    /**
     * Format post title with update date if applicable
     * 
     * @param WP_Post $post Post object
     * @return string Formatted title
     */
    private function format_post_title_with_update_date($post) {
        // Determine if post is new vs updated by comparing post_date and post_modified
        $post_date_timestamp = strtotime($post->post_date);
        $post_modified_timestamp = strtotime($post->post_modified);
        $time_difference = abs($post_modified_timestamp - $post_date_timestamp);
        
        // If dates are within 5 seconds, consider it a new post
        $is_new_post = ($time_difference <= 5);
        
        // For new posts, return only the title
        if ($is_new_post) {
            return $post->post_title;
        }
        
        // For updated posts, show title with update date
        $last_notified = get_post_meta($post->ID, '_subscriber_notifications_last_notification_date', true);
        
        if ($last_notified) {
            // Use WordPress timezone for date formatting
            $timezone = wp_timezone();
            try {
                $datetime = new DateTime($last_notified, $timezone);
                $formatted_date = $datetime->format('M j, Y');
            } catch (Exception $e) {
                // Fallback
                $formatted_date = mysql2date('M j, Y', $last_notified);
            }
            return $post->post_title . ' (updated on ' . $formatted_date . ')';
        }
        
        // Fallback: if no last_notified date but post was updated, use post_modified
        $timezone = wp_timezone();
        try {
            $datetime = new DateTime($post->post_modified, $timezone);
            $formatted_date = $datetime->format('M j, Y');
        } catch (Exception $e) {
            // Fallback
            $formatted_date = mysql2date('M j, Y', $post->post_modified);
        }
        return $post->post_title . ' (updated on ' . $formatted_date . ')';
    }
    
    /**
     * Process shortcodes for content
     * 
     * @param string $content Content to process
     * @param object $subscriber Subscriber object
     * @param object $notification Notification object
     * @return string Processed content
     */
    public function process_shortcodes($content, $subscriber = null, $notification = null) {
        global $subscriber_notifications_current_subscriber, $subscriber_notifications_current_notification;
        
        // Unescape content to fix apostrophe escaping from previous saves
        $content = wp_unslash($content);
        
        // Set current subscriber and notification for shortcode processing
        $subscriber_notifications_current_subscriber = $subscriber;
        $subscriber_notifications_current_notification = $notification;
        
        // Process all shortcodes
        $content = do_shortcode($content);
        
        // Clear globals
        $subscriber_notifications_current_subscriber = null;
        $subscriber_notifications_current_notification = null;
        
        return $content;
    }
    
}