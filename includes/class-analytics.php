<?php
/**
 * Analytics and tracking class
 * 
 * @package SubscriberNotifications
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analytics class for tracking email performance
 */
class SubscriberNotifications_Analytics {
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Constructor
     * 
     * @param SubscriberNotifications_Database $database Database instance
     */
    public function __construct($database) {
        $this->database = $database;
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Use static flag to prevent duplicate hook registrations
        static $hooks_registered = false;
        
        if ($hooks_registered) {
            return;
        }
        
        add_action('wp_ajax_track_email_open', array($this, 'track_email_open'));
        add_action('wp_ajax_nopriv_track_email_open', array($this, 'track_email_open'));
        add_action('wp_ajax_track_email_click', array($this, 'track_email_click'));
        add_action('wp_ajax_nopriv_track_email_click', array($this, 'track_email_click'));
        
        $hooks_registered = true;
    }
    
    /**
     * Check rate limit for tracking requests
     * 
     * @param string $type Type of tracking (open or click)
     * @return bool True if within limit, false if exceeded
     */
    private function check_rate_limit($type = 'open') {
        // Sanitize and validate IP address
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        // Validate IP format (basic check)
        if ($ip !== 'unknown' && !filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = 'unknown';
        }
        $transient_key = 'subscriber_track_' . $type . '_' . md5($ip);
        $rate_limit = get_transient($transient_key);
        
        // Limit: 100 requests per hour per IP
        if ($rate_limit && $rate_limit >= 100) {
            return false;
        }
        
        // Increment counter
        $new_count = $rate_limit ? $rate_limit + 1 : 1;
        set_transient($transient_key, $new_count, HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Track email open
     */
    public function track_email_open() {
        // Check rate limit
        if (!$this->check_rate_limit('open')) {
            // Return pixel even if rate limited (don't reveal we're blocking)
            header('Content-Type: image/gif');
            header('Content-Length: 43');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            exit;
        }
        
        // Add nonce verification for logged-in users (email clients are not logged in)
        if (is_user_logged_in() && isset($_REQUEST['_wpnonce'])) {
            if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'track_email_open')) {
                // Return pixel but don't track for invalid nonce
                header('Content-Type: image/gif');
                header('Content-Length: 43');
                echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
                exit;
            }
        }
        
        $tracking_id = isset($_REQUEST['tracking_id']) ? sanitize_text_field($_REQUEST['tracking_id']) : '';
        
        if (empty($tracking_id)) {
            // Return 1x1 transparent pixel even if tracking fails
            header('Content-Type: image/gif');
            header('Content-Length: 43');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            exit;
        }
        
        global $wpdb;
        
        // Find the log entry first (we need it to check if it exists)
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}subscriber_notification_logs WHERE tracking_id = %s",
            $tracking_id
        ));
        
        if (!$log) {
            // Return 1x1 transparent pixel even if log not found
            header('Content-Type: image/gif');
            header('Content-Length: 43');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            exit;
        }
        
        // Prevent duplicate tracking: Check if this tracking_id was already processed recently
        // This prevents email clients from making multiple requests (preloading, retries, etc.)
        // from counting as multiple opens
        // Use a combination of tracking_id and log_id for more specific tracking
        $duplicate_key = 'subscriber_track_open_' . md5($tracking_id . '_' . $log->id);
        $already_tracked = get_transient($duplicate_key);
        
        if ($already_tracked) {
            // Return pixel but don't update database (already counted)
            header('Content-Type: image/gif');
            header('Content-Length: 43');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            exit;
        }
        
        // Set transient IMMEDIATELY to prevent duplicate processing (10 second window)
        // This prevents rapid duplicate requests from email clients
        // Set it before processing to minimize race condition window
        set_transient($duplicate_key, true, 10);
        
        // Update open count and last opened time
        $wpdb->update(
            $wpdb->prefix . 'subscriber_notification_logs',
            array(
                'open_count' => $log->open_count + 1,
                'last_opened' => current_time('mysql')
            ),
            array('id' => $log->id),
            array('%d', '%s'),
            array('%d')
        );
        
        // Return 1x1 transparent pixel
        header('Content-Type: image/gif');
        header('Content-Length: 43');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }
    
    /**
     * Validate and sanitize URL for safe redirect
     * 
     * @param string $url Raw URL from request
     * @return string|false Validated and sanitized URL, or false if invalid
     */
    private function validate_redirect_url($url) {
        if (empty($url)) {
            return false;
        }
        
        // Decode URL
        $url = urldecode($url);
        
        // Check for dangerous protocols (even if encoded or double-encoded)
        $dangerous_patterns = array(
            '/^(javascript|data|vbscript|file|about|chrome|edge):/i',
            '/%3a%2f%2f/i', // Encoded ://
            '/%3A%2F%2F/i', // Encoded ://
            '/&#x3a;&#x2f;&#x2f;/i', // HTML entity encoded ://
            '/&#58;&#47;&#47;/i' // HTML entity encoded ://
        );
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }
        
        // Check for script injection attempts
        if (preg_match('/<script|on\w+\s*=/i', $url)) {
            return false;
        }
        
        // Parse URL to check scheme
        $parsed = parse_url($url);
        $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
        
        // Only allow http, https, or relative URLs (no scheme)
        if (!empty($scheme) && !in_array($scheme, array('http', 'https'))) {
            return false;
        }
        
        // Additional validation: ensure host is not localhost or private IP ranges (unless it's the same site)
        if (!empty($parsed['host'])) {
            $host = strtolower($parsed['host']);
            $site_host = strtolower(parse_url(home_url(), PHP_URL_HOST));
            
            // Allow same site, but be cautious with localhost/private IPs
            if ($host !== $site_host) {
                // Block localhost and private IP ranges for external redirects
                if (in_array($host, array('localhost', '127.0.0.1', '::1')) || 
                    preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)/', $host)) {
                    return false;
                }
            }
        }
        
        // Sanitize URL
        $url = esc_url_raw($url);
        
        // Validate using WordPress function
        $validated_url = wp_validate_redirect($url, home_url());
        
        return $validated_url;
    }
    
    /**
     * Track email click
     */
    public function track_email_click() {
        // Check rate limit
        if (!$this->check_rate_limit('click')) {
            // Redirect to home if rate limited
            wp_safe_redirect(home_url());
            exit;
        }
        
        // Add nonce verification for logged-in users (email clients are not logged in)
        if (is_user_logged_in() && isset($_REQUEST['_wpnonce'])) {
            if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'track_email_click')) {
                // Redirect to home for invalid nonce
                wp_safe_redirect(home_url());
                exit;
            }
        }
        
        $tracking_id = isset($_REQUEST['tracking_id']) ? sanitize_text_field($_REQUEST['tracking_id']) : '';
        
        if (empty($tracking_id) || strlen($tracking_id) < 10) {
            // Redirect to home if no tracking ID or invalid
            wp_safe_redirect(home_url());
            exit;
        }
        
        global $wpdb;
        
        // Find the log entry
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}subscriber_notification_logs WHERE tracking_id = %s",
            $tracking_id
        ));
        
        if ($log) {
            // Update click count and last clicked time
            $wpdb->update(
                $wpdb->prefix . 'subscriber_notification_logs',
                array(
                    'click_count' => $log->click_count + 1,
                    'last_clicked' => current_time('mysql')
                ),
                array('id' => $log->id),
                array('%d', '%s'),
                array('%d')
            );
        }
        
        // Get and validate URL
        $raw_url = isset($_REQUEST['url']) ? $_REQUEST['url'] : '';
        $url = $this->validate_redirect_url($raw_url);
        
        // Redirect to the original URL or home if no URL
        if ($url) {
            wp_safe_redirect($url);
        } else {
            wp_safe_redirect(home_url());
        }
        exit;
    }
    
    /**
     * Get analytics summary
     * 
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return array Analytics data
     */
    public function get_analytics_summary($date_from = '', $date_to = '') {
        $analytics = $this->database->get_analytics_data($date_from, $date_to);
        
        if (!$analytics) {
            return array(
                'total_emails' => 0,
                'sent_emails' => 0,
                'failed_emails' => 0,
                'delivered_emails' => 0,
                'open_rate' => 0,
                'click_rate' => 0,
                'total_opens' => 0,
                'unique_opens' => 0,
                'total_clicks' => 0,
                'unique_clicks' => 0
            );
        }
        
        // Calculate delivered emails (sent - failed)
        $delivered_emails = $analytics->sent_emails - $analytics->failed_emails;
        
        // Calculate rates using unique opens/clicks and delivered emails
        $open_rate = $delivered_emails > 0 ? ($analytics->unique_opens / $delivered_emails) * 100 : 0;
        $click_rate = $delivered_emails > 0 ? ($analytics->unique_clicks / $delivered_emails) * 100 : 0;
        
        return array(
            'total_emails' => $analytics->total_emails,
            'sent_emails' => $analytics->sent_emails,
            'failed_emails' => $analytics->failed_emails,
            'delivered_emails' => $delivered_emails,
            'open_rate' => round($open_rate, 2),
            'click_rate' => round($click_rate, 2),
            'total_opens' => $analytics->total_opens,
            'unique_opens' => $analytics->unique_opens,
            'total_clicks' => $analytics->total_clicks,
            'unique_clicks' => $analytics->unique_clicks
        );
    }
}