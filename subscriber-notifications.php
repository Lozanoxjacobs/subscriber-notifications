<?php
/**
 * Plugin Name: Subscriber Notifications
 * Plugin URI: https://github.com/Lozanoxjacobs/subscriber-notifications
 * Description: Configurable subscriber notification system with per-site Content Types (any public post type and taxonomy), JSON preferences, theme-native form, and brandable emails.
 * Version: 3.6.3
 * Author: Jackie Lozano
 * License: GPL v2 or later
 * Text Domain: subscriber-notifications
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SUBSCRIBER_NOTIFICATIONS_VERSION', '3.6.3');
define('SUBSCRIBER_NOTIFICATIONS_DB_VERSION', '4');
define('SUBSCRIBER_NOTIFICATIONS_PLUGIN_FILE', __FILE__);
define('SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SUBSCRIBER_NOTIFICATIONS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SUBSCRIBER_NOTIFICATIONS_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'includes/options-helpers.php';

/**
 * Main plugin class
 */
class SubscriberNotifications {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Plugin components
     */
    private $database;
    private $admin;
    private $content_types_admin;
    private $frontend;
    private $notifications;
    private $email_sender;
    private $shortcodes;
    private $scheduler;
    private $csv_handler;
    private $analytics;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Plugin activation/deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('SubscriberNotifications', 'uninstall'));

        // Initialize when WordPress is ready
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WordPress is ready
        if (!did_action('init')) {
            add_action('init', array($this, 'init'));
            return;
        }
        
        // Use static flag to prevent multiple initializations
        static $initialized = false;
        if ($initialized) {
            return;
        }
        $initialized = true;
        
        // Load text domain
        $this->load_textdomain();
        
        // Load dependencies
        $this->load_dependencies();

        $this->maybe_upgrade_database();

        // Initialize components
        $this->init_components();
        
        // Add rewrite rules for email tracking on init hook (only if not already added)
        if (!has_action('init', array($this, 'setup_rewrite_rules'))) {
            add_action('init', array($this, 'setup_rewrite_rules'), 20);
        }
    }

    /**
     * Register click/open tracking rewrite rules (must run before flush_rewrite_rules()).
     */
    private function register_tracking_rewrite_rules() {
        add_rewrite_rule('^track/click/?$', 'index.php?subscriber_track=click', 'top');
        add_rewrite_rule('^track/open/?$', 'index.php?subscriber_track=open', 'top');
    }

    /**
     * Whether tracking rewrite rules are missing from the saved ruleset.
     */
    private function tracking_rewrite_rules_missing() {
        $rules = get_option('rewrite_rules');
        if (!is_array($rules)) {
            return true;
        }

        return !array_key_exists('track/click/?$', $rules) || !array_key_exists('track/open/?$', $rules);
    }

    /**
     * Persist tracking rewrite rules when the plugin version changes or rules are absent.
     */
    private function maybe_flush_tracking_rewrite_rules() {
        $stored_version = get_option('subscriber_notifications_rewrite_version', '0.0.0');
        if (
            version_compare($stored_version, SUBSCRIBER_NOTIFICATIONS_VERSION, '<')
            || $this->tracking_rewrite_rules_missing()
        ) {
            flush_rewrite_rules(false);
            update_option('subscriber_notifications_rewrite_version', SUBSCRIBER_NOTIFICATIONS_VERSION);
        }
    }
    
    /**
     * Setup rewrite rules for email tracking
     */
    public function setup_rewrite_rules() {
        $this->register_tracking_rewrite_rules();
        
        // Use WordPress-native has_action() to prevent duplicate hook registrations
        // This checks WordPress's actual hook registry, which is more reliable than static flags
        if (!has_filter('query_vars', array($this, 'add_tracking_query_var'))) {
            add_filter('query_vars', array($this, 'add_tracking_query_var'));
        }
        
        if (!has_action('template_redirect', array($this, 'handle_tracking_request'))) {
            add_action('template_redirect', array($this, 'handle_tracking_request'));
        }

        $this->maybe_flush_tracking_rewrite_rules();
    }
    
    /**
     * Add tracking query var
     * 
     * @param array $vars Existing query vars
     * @return array Modified query vars
     */
    public function add_tracking_query_var($vars) {
        $vars[] = 'subscriber_track';
        return $vars;
    }
    
    /**
     * Handle tracking request from rewrite rule
     */
    public function handle_tracking_request() {
        $track_type = get_query_var('subscriber_track');
        
        if (empty($track_type)) {
            return;
        }
        
        // Get analytics instance - it should be initialized by now
        if (isset($this->analytics)) {
            if ($track_type === 'click') {
                $this->analytics->track_email_click();
            } elseif ($track_type === 'open') {
                $this->analytics->track_email_open();
            }
        }
    }
    
    /**
     * Load text domain
     */
    private function load_textdomain() {
        load_plugin_textdomain(
            'subscriber-notifications',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Run database migrations when the plugin version is newer than the stored DB version.
     */
    private function maybe_upgrade_database() {
        if (version_compare(get_option('subscriber_notifications_db_version', '0'), SUBSCRIBER_NOTIFICATIONS_DB_VERSION, '>=')) {
            return;
        }

        if (!class_exists('SubscriberNotifications_Database')) {
            return;
        }

        $database = new SubscriberNotifications_Database();
        $database->create_tables();
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {

        $files = array(
            // Core config + preferences must load before consumers.
            'includes/class-content-config.php',
            'includes/class-term-resolver.php',
            'includes/class-term-checklist.php',
            'includes/class-preferences.php',
            'includes/log-date-helpers.php',
            'includes/email-font-presets.php',
            'includes/class-database.php',
            'includes/class-email-formatter.php',
            'includes/class-content-types-admin.php',
            'includes/class-schedule-calculator.php',
            'includes/class-dashboard.php',
            'includes/class-subscribers-list-table.php',
            'includes/class-notifications-list-table.php',
            'includes/class-logs-list-table.php',
            'includes/class-admin.php',
            'includes/class-frontend.php',
            'includes/class-notifications.php',
            'includes/class-email-sender.php',
            'includes/class-shortcodes.php',
            'includes/class-scheduler.php',
            'includes/class-csv-handler.php',
            'includes/class-analytics.php'
        );

        foreach ($files as $file) {
            $file_path = SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }

        if (class_exists('SubscriberNotifications_Content_Config')) {
            SubscriberNotifications_Content_Config::register();
        }
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        try {
            // Initialize database
            $this->database = new SubscriberNotifications_Database();

            // Initialize other components
            $this->admin = new SubscriberNotifications_Admin($this->database);
            $this->content_types_admin = new SubscriberNotifications_Content_Types_Admin();
            $this->frontend = new SubscriberNotifications_Frontend($this->database);
            $this->notifications = new SubscriberNotifications_Notifications($this->database);
            $this->email_sender = new SubscriberNotifications_Email_Sender();
            $this->shortcodes = new SubscriberNotifications_Shortcodes();
            $this->scheduler = new SubscriberNotifications_Scheduler($this->database);
            $this->csv_handler = new SubscriberNotifications_CSV_Handler($this->database);
            $this->analytics = new SubscriberNotifications_Analytics($this->database);

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Subscriber Notifications initialization error: ' . $e->getMessage());
            }
        }
    }

    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Subscriber Notifications requires WordPress 5.0 or higher.', 'subscriber-notifications'));
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('Subscriber Notifications requires PHP 7.4 or higher.', 'subscriber-notifications'));
        }
        
        // Load dependencies
        $this->load_dependencies();
        
        // Create database tables
        if (class_exists('SubscriberNotifications_Database')) {
            $database = new SubscriberNotifications_Database();
            $database->create_tables();
        }

        // Ensure Content Types config option exists (greenfield default = empty array).
        if (get_option(SubscriberNotifications_Content_Config::OPTION_KEY, null) === null) {
            add_option(SubscriberNotifications_Content_Config::OPTION_KEY, array(), '', 'no');
        }

        // Set default options
        $this->set_default_options();

        // Register tracking routes before flush — init has not run yet at activation time.
        $this->register_tracking_rewrite_rules();
        $this->maybe_flush_tracking_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('subscriber_notifications_process_queue');
        wp_clear_scheduled_hook('subscriber_notifications_send_daily');
        wp_clear_scheduled_hook('subscriber_notifications_send_weekly');
        wp_clear_scheduled_hook('subscriber_notifications_send_monthly');
        wp_clear_scheduled_hook('subscriber_notifications_drain_queue');

        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall - cleanup all data
     * This is a static method because it's called when the plugin is deleted
     */
    public static function uninstall() {
        // Check if user has opted to delete data on uninstall
        // Default is 0 (preserve data) - user must explicitly check the box to delete
        $delete_data = (int) subscriber_notifications_get_option('delete_data_on_uninstall', 0);
        
        // Log what's happening (for debugging)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Subscriber Notifications: Uninstall called. delete_data_on_uninstall setting: %s. %s',
                $delete_data ? '1 (will delete all data)' : '0 (will preserve data)',
                $delete_data ? 'Proceeding with full data deletion.' : 'Preserving all data - only cleaning up temporary items.'
            ));
        }
        
        if (!$delete_data) {
            // User wants to preserve data - only clean up non-data items
            // Clear scheduled events
            wp_clear_scheduled_hook('subscriber_notifications_process_queue');
            wp_clear_scheduled_hook('subscriber_notifications_send_daily');
            wp_clear_scheduled_hook('subscriber_notifications_send_weekly');
            wp_clear_scheduled_hook('subscriber_notifications_send_monthly');
            wp_clear_scheduled_hook('subscriber_notifications_drain_queue');
            
            // Delete transients
            delete_transient('subscriber_notifications_tokens_checked');
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            return;
        }
        
        // User has opted to delete all data - proceed with full cleanup
        global $wpdb;
        
        // Drop database tables
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}subscriber_notifications");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}subscriber_notification_logs");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}subscriber_notifications_queue");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}subscriber_notifications_send_queue");
        
        // Delete all plugin options (prefixed plus legacy leftovers).
        $prefixed_suffixes = array(
            'welcome_email_subject',
            'welcome_email_content',
            'welcome_back_email_subject',
            'welcome_back_email_content',
            'preferences_update_email_subject',
            'preferences_update_email_content',
            'captcha_site_key',
            'captcha_secret_key',
            'global_header_logo',
            'global_header_content',
            'global_footer',
            'email_css',
            'email_font_body',
            'email_font_heading',
            'email_color_text',
            'email_color_link',
            'email_color_background',
            'email_color_content_bg',
            'email_color_link_hover',
            'email_color_footer_bg',
            'email_color_footer_text',
            'daily_send_time',
            'weekly_send_time',
            'weekly_send_day',
            'monthly_send_time',
            'monthly_send_day',
            'test_email',
            'delete_data_on_uninstall',
            'hide_terms_without_published_content',
        );
        $options_to_delete = array_merge(
            array(
                'subscriber_notifications_db_version',
                'subscriber_notifications_rewrite_version',
                'subscriber_notifications_content_config',
            ),
            array_map(
                'subscriber_notifications_option_name',
                $prefixed_suffixes
            ),
            array(
                'mail_method',
                'sendgrid_api_key',
                'sendgrid_from_email',
                'sendgrid_from_name',
            )
        );

        foreach (array_unique($options_to_delete) as $option) {
            delete_option($option);
        }
        
        // Clear all scheduled events
        wp_clear_scheduled_hook('subscriber_notifications_process_queue');
        wp_clear_scheduled_hook('subscriber_notifications_send_daily');
        wp_clear_scheduled_hook('subscriber_notifications_send_weekly');
        wp_clear_scheduled_hook('subscriber_notifications_send_monthly');
        wp_clear_scheduled_hook('subscriber_notifications_drain_queue');
        
        // Delete transients
        delete_transient('subscriber_notifications_tokens_checked');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $default_options = array(
            'welcome_email_subject'           => __('Welcome! Your subscription is confirmed', 'subscriber-notifications'),
            'welcome_email_content'            => __('Thank you for subscribing! You will receive [delivery_frequency] updates about [selected_subscriptions].', 'subscriber-notifications'),
            'welcome_back_email_subject'      => __('Welcome back! Your subscription has been reactivated', 'subscriber-notifications'),
            'welcome_back_email_content'      => __('Welcome back, [subscriber_name]! Your subscription has been reactivated. You will receive [delivery_frequency] updates about [selected_subscriptions].', 'subscriber-notifications'),
            'preferences_update_email_subject' => __('Your preferences have been updated', 'subscriber-notifications'),
            'preferences_update_email_content' => __('Hello [subscriber_name],', 'subscriber-notifications') . "\n\n" . __('Your notification preferences have been successfully updated.', 'subscriber-notifications') . "\n\n" . __('Your current preferences:', 'subscriber-notifications') . "\n" . __('Subscriptions: [selected_subscriptions]', 'subscriber-notifications') . "\n" . __('Frequency: [delivery_frequency]', 'subscriber-notifications') . "\n\n" . __('You can manage your preferences anytime using this link: [manage_preferences_link]', 'subscriber-notifications'),
            'captcha_site_key'                => '',
            'captcha_secret_key'              => '',
            'hide_terms_without_published_content' => 1,
            'global_header_logo'              => '',
            'global_header_content'           => '',
            'global_footer'                   => '[site_title] | [manage_preferences_link]',
            'email_font_body'                 => 'Arial, Helvetica, sans-serif',
            'email_font_heading'              => '',
            'email_color_text'                => '#333333',
            'email_color_link'                => '#0066cc',
            'email_color_background'          => '#f5f5f5',
            'email_color_content_bg'          => '#ffffff',
            'email_color_link_hover'          => '#004499',
            'email_color_footer_bg'           => '#1d2327',
            'email_color_footer_text'         => '#ffffff',
        );

        foreach ($default_options as $short_key => $value) {
            $prefixed = subscriber_notifications_option_name($short_key);
            if (get_option($prefixed) === false) {
                add_option($prefixed, $value);
            }
        }
    }
    
    /**
     * Get database instance
     */
    public function get_database() {
        return $this->database;
    }
    
    /**
     * Get admin instance
     */
    public function get_admin() {
        return $this->admin;
    }
    
    /**
     * Get frontend instance
     */
    public function get_frontend() {
        return $this->frontend;
    }
}

// Initialize the plugin
SubscriberNotifications::get_instance();