<?php
/**
 * Plugin Name: Subscriber Notifications
 * Plugin URI: https://github.com/Lozanoxjacobs/subscriber-notifications
 * Description: Enterprise-grade email notification system featuring personalized content delivery, unique engagement analytics, flexible scheduling options, recurring notifications, and comprehensive subscriber management with preference controls.
 * Version: 2.6.0
 * Author: Jackie Lozano
 * License: GPL v2 or later
 * Text Domain: subscriber-notifications
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SUBSCRIBER_NOTIFICATIONS_VERSION', '2.6.0');
define('SUBSCRIBER_NOTIFICATIONS_PLUGIN_FILE', __FILE__);
define('SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SUBSCRIBER_NOTIFICATIONS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SUBSCRIBER_NOTIFICATIONS_PLUGIN_BASENAME', plugin_basename(__FILE__));

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
    private $frontend;
    private $notifications;
    private $sendgrid;
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
        
        // Check dependencies
        add_action('admin_init', array($this, 'check_dependencies'));
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
        
        // Initialize components
        $this->init_components();
        
        // Add rewrite rules for email tracking on init hook (only if not already added)
        if (!has_action('init', array($this, 'setup_rewrite_rules'))) {
            add_action('init', array($this, 'setup_rewrite_rules'), 20);
        }
    }
    
    /**
     * Setup rewrite rules for email tracking
     */
    public function setup_rewrite_rules() {
        // Add rewrite rules (these can be added multiple times safely)
        add_rewrite_rule('^track/click/?$', 'index.php?subscriber_track=click', 'top');
        add_rewrite_rule('^track/open/?$', 'index.php?subscriber_track=open', 'top');
        
        // Use WordPress-native has_action() to prevent duplicate hook registrations
        // This checks WordPress's actual hook registry, which is more reliable than static flags
        if (!has_filter('query_vars', array($this, 'add_tracking_query_var'))) {
            add_filter('query_vars', array($this, 'add_tracking_query_var'));
        }
        
        if (!has_action('template_redirect', array($this, 'handle_tracking_request'))) {
            add_action('template_redirect', array($this, 'handle_tracking_request'));
        }
        
        // Flush rewrite rules once after version update (for existing sites)
        // Use transient to avoid flushing on every page load
        $flush_transient = get_transient('subscriber_notifications_rewrite_flush_' . SUBSCRIBER_NOTIFICATIONS_VERSION);
        if (!$flush_transient) {
            $last_flush_version = get_option('subscriber_notifications_rewrite_version', '0.0.0');
            if (version_compare($last_flush_version, SUBSCRIBER_NOTIFICATIONS_VERSION, '<')) {
                flush_rewrite_rules(false);
                update_option('subscriber_notifications_rewrite_version', SUBSCRIBER_NOTIFICATIONS_VERSION);
                // Set transient to prevent flushing again for this version (24 hour expiry)
                set_transient('subscriber_notifications_rewrite_flush_' . SUBSCRIBER_NOTIFICATIONS_VERSION, true, DAY_IN_SECONDS);
            }
        }
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
     * Load plugin dependencies
     */
    private function load_dependencies() {
        
        $files = array(
            'includes/class-database.php',
            'includes/class-email-formatter.php',
            'includes/class-admin.php',
            'includes/class-frontend.php',
            'includes/class-notifications.php',
            'includes/class-sendgrid.php',
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
            $this->frontend = new SubscriberNotifications_Frontend($this->database);
            $this->notifications = new SubscriberNotifications_Notifications($this->database);
            $this->sendgrid = new SubscriberNotifications_SendGrid();
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
     * Check plugin dependencies
     */
    public function check_dependencies() {
        if (!class_exists('Tribe__Events__Main')) {
            add_action('admin_notices', array($this, 'events_calendar_notice'));
        }
    }
    
    /**
     * Events Calendar notice
     */
    public function events_calendar_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Subscriber Notifications requires The Events Calendar plugin to be installed and activated.', 'subscriber-notifications'); ?></p>
        </div>
        <?php
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
            // IMPORTANT: Run migrations FIRST (before create_tables) to handle column renames
            $database->run_migrations();
            // Then create/update tables (dbDelta will see correct schema after migration)
            $database->create_tables();
        }
        
        // Set default options
        $this->set_default_options();
        
        // Clean up obsolete options
        $this->cleanup_obsolete_options();
        
        // Auto-populate global footer if empty
        $this->auto_populate_global_footer();
        
        // Schedule events
        $this->schedule_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('subscriber_notifications_process_queue');
        
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
        $delete_data = get_option('delete_data_on_uninstall', 0);
        
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
        
        // Delete all plugin options
        $options_to_delete = array(
            'sendgrid_api_key',
            'sendgrid_from_email',
            'sendgrid_from_name',
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
            'daily_send_time',
            'weekly_send_time',
            'weekly_send_day',
            'monthly_send_time',
            'monthly_send_day',
            'subscriber_notifications_db_version',
            'subscriber_notifications_rewrite_version',
            'subscriber_notifications_feed_migration_version',
            'subscriber_notifications_phone_removal_version',
            'subscriber_notifications_settings',
            'mail_method',
            'delete_data_on_uninstall',
            'test_email'
        );
        
        foreach ($options_to_delete as $option) {
            delete_option($option);
        }
        
        // Clear all scheduled events
        wp_clear_scheduled_hook('subscriber_notifications_process_queue');
        wp_clear_scheduled_hook('subscriber_notifications_send_daily');
        wp_clear_scheduled_hook('subscriber_notifications_send_weekly');
        wp_clear_scheduled_hook('subscriber_notifications_send_monthly');
        
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
            'sendgrid_api_key' => '',
            'sendgrid_from_email' => get_option('admin_email'),
            'sendgrid_from_name' => get_bloginfo('name'),
            'welcome_email_subject' => __('Welcome! Your subscription is confirmed', 'subscriber-notifications'),
            'welcome_email_content' => __('Thank you for subscribing! You will receive [delivery_frequency] updates about [selected_news_categories] and [selected_meeting_categories].', 'subscriber-notifications'),
            'welcome_back_email_subject' => __('Welcome back! Your subscription has been reactivated', 'subscriber-notifications'),
            'welcome_back_email_content' => __('Welcome back, [subscriber_name]! Your subscription has been reactivated. You will receive [delivery_frequency] updates about [selected_news_categories] and [selected_meeting_categories].', 'subscriber-notifications'),
            'preferences_update_email_subject' => __('Your preferences have been updated', 'subscriber-notifications'),
            'preferences_update_email_content' => __('Hello [subscriber_name],', 'subscriber-notifications') . "\n\n" . __('Your notification preferences have been successfully updated.', 'subscriber-notifications') . "\n\n" . __('Your current preferences:', 'subscriber-notifications') . "\n" . __('News Categories: [selected_news_categories]', 'subscriber-notifications') . "\n" . __('Meeting Categories: [selected_meeting_categories]', 'subscriber-notifications') . "\n" . __('Frequency: [delivery_frequency]', 'subscriber-notifications') . "\n\n" . __('You can manage your preferences anytime using this link: [manage_preferences_link]', 'subscriber-notifications'),
            'captcha_site_key' => '',
            'captcha_secret_key' => '',
            'global_header_logo' => '',
            'global_header_content' => ''
        );
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Clean up obsolete options
     */
    private function cleanup_obsolete_options() {
        // Delete obsolete unsubscribe page options (replaced by manage preferences page)
        delete_option('unsubscribe_page_title');
        delete_option('unsubscribe_page_content');
    }
    
    /**
     * Auto-populate global footer if empty
     */
    private function auto_populate_global_footer() {
        $global_footer = get_option('global_footer', '');
        
        if (empty($global_footer)) {
            $default_footer = '[site_title] | [manage_preferences_link]';
            update_option('global_footer', $default_footer);
        }
    }
    
    /**
     * Schedule WordPress events
     */
    private function schedule_events() {
        if (!wp_next_scheduled('subscriber_notifications_process_queue')) {
            wp_schedule_event(time(), 'hourly', 'subscriber_notifications_process_queue');
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