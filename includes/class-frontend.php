<?php
/**
 * Frontend functionality class.
 *
 * Renders the subscribe form and preferences page using the configurable Content Types
 * system introduced in v3.0. Markup leans on native HTML elements (`<details>` /
 * `<summary>`, plain inputs and buttons) so the active theme controls the
 * presentation. The plugin only contributes minimal layout CSS.
 *
 * @package SubscriberNotifications
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend class for managing public-facing functionality
 */
class SubscriberNotifications_Frontend {

    /**
     * Database instance.
     *
     * @var SubscriberNotifications_Database
     */
    private $database;

    /**
     * Constructor.
     *
     * @param SubscriberNotifications_Database $database Database instance.
     */
    public function __construct($database) {
        $this->database           = $database;
        $this->item_notifications = new SubscriberNotifications_Item_Notifications($database);
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        add_action('wp_ajax_subscriber_notifications_subscribe', array($this, 'handle_subscription'));
        add_action('wp_ajax_nopriv_subscriber_notifications_subscribe', array($this, 'handle_subscription'));
        add_action('wp_ajax_subscriber_notifications_update_preferences', array($this, 'handle_preferences_update'));
        add_action('wp_ajax_nopriv_subscriber_notifications_update_preferences', array($this, 'handle_preferences_update'));
        add_action('wp_ajax_subscriber_notifications_unsubscribe', array($this, 'handle_unsubscribe_action'));
        add_action('wp_ajax_nopriv_subscriber_notifications_unsubscribe', array($this, 'handle_unsubscribe_action'));
        add_action('wp_ajax_subscriber_notifications_post_subscribe', array($this, 'handle_post_subscribe'));
        add_action('wp_ajax_nopriv_subscriber_notifications_post_subscribe', array($this, 'handle_post_subscribe'));
        add_shortcode('subscriber_notifications_form', array($this, 'subscription_form_shortcode'));
        add_shortcode('subscriber_notifications_preferences', array($this, 'preferences_form_shortcode'));
        add_shortcode('subscriber_notifications_post_subscribe', array($this, 'post_subscribe_shortcode'));
    }

    /**
     * @var SubscriberNotifications_Item_Notifications|null
     */
    private $item_notifications;

    /**
     * Enqueue CSS/JS when subscription or preferences UI is rendered.
     *
     * @param array<string, mixed> $script_overrides Optional overrides for wp_localize_script.
     */
    private function enqueue_subscription_form_assets(array $script_overrides = array()) {
        static $enqueued = false;
        if ($enqueued) {
            return;
        }
        $enqueued = true;

        wp_enqueue_script('jquery');

        $site_key = subscriber_notifications_get_option('captcha_site_key', '');
        if ('' !== $site_key) {
            wp_enqueue_script(
                'google-recaptcha',
                'https://www.google.com/recaptcha/api.js',
                array(),
                null,
                true
            );
        }

        wp_enqueue_script(
            'subscriber-notifications-frontend',
            SUBSCRIBER_NOTIFICATIONS_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            SUBSCRIBER_NOTIFICATIONS_VERSION,
            true
        );

        $logged_in_contact = $this->get_logged_in_contact_for_form();
        $is_logged_in      = is_user_logged_in();

        $script_data = array(
            'ajaxUrl'          => admin_url('admin-ajax.php'),
            'homeUrl'          => home_url('/'),
            'nonce'            => wp_create_nonce('subscriber_notifications_nonce'),
            'unsubscribeNonce' => wp_create_nonce('subscriber_notifications_unsubscribe'),
            'siteKey'          => $site_key,
            'isLoggedIn'       => $is_logged_in,
            'lockedName'       => $logged_in_contact ? $logged_in_contact['name'] : '',
            'lockedEmail'      => $logged_in_contact ? $logged_in_contact['email'] : '',
            'preferencesProfileLocked' => false,
            'i18n'             => array(
                'subscribing'         => __('Subscribing...', 'subscriber-notifications'),
                'subscribe'           => __('Subscribe', 'subscriber-notifications'),
                'updating'            => __('Updating...', 'subscriber-notifications'),
                'update'              => __('Update Preferences', 'subscriber-notifications'),
                'reactivate'          => __('Reactivate Subscription', 'subscriber-notifications'),
                'unsubscribing'       => __('Unsubscribing...', 'subscriber-notifications'),
                'unsubscribe'         => __('Unsubscribe', 'subscriber-notifications'),
                'confirmUnsubscribe'  => __('Are you sure you want to unsubscribe? You will no longer receive any notifications.', 'subscriber-notifications'),
                'genericError'        => __('An error occurred. Please try again.', 'subscriber-notifications'),
                'errorAtLeastOneTerm' => __('Please select at least one option to subscribe to.', 'subscriber-notifications'),
                'errorNameLength'     => __('Name must be at least 2 characters long.', 'subscriber-notifications'),
                'errorEmail'          => __('Please enter a valid email address.', 'subscriber-notifications'),
                'errorFrequency'      => __('Please select a frequency preference.', 'subscriber-notifications'),
                'errorMissingProfileName' => __('Please add your name to your account profile before subscribing.', 'subscriber-notifications'),
            ),
        );

        $script_data = array_merge($script_data, $script_overrides);

        wp_localize_script(
            'subscriber-notifications-frontend',
            'subscriberNotifications',
            $script_data
        );

        wp_enqueue_style(
            'subscriber-notifications-frontend',
            SUBSCRIBER_NOTIFICATIONS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            SUBSCRIBER_NOTIFICATIONS_VERSION
        );
    }

    /**
     * Subscription form shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string Form HTML.
     */
    /**
     * On-page single-post subscription widget.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function post_subscribe_shortcode($atts) {
        $copy = SubscriberNotifications_Post_Subscribe_Display::parse_atts(
            shortcode_atts(SubscriberNotifications_Post_Subscribe_Display::default_atts(), $atts, 'subscriber_notifications_post_subscribe')
        );

        $post_id = get_queried_object_id();
        if (!is_singular() || $post_id < 1) {
            return '';
        }

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return '';
        }

        if (!SubscriberNotifications_Content_Config::is_post_eligible_for_single_item($post)) {
            return '';
        }

        $this->enqueue_subscription_form_assets(array(
            'postSubscribePostId' => $post_id,
        ));

        $subscribed = false;
        if (is_user_logged_in()) {
            $subscriber = $this->resolve_subscriber_for_logged_in_session();
            if ($subscriber && $subscriber->status === 'active') {
                $prefs      = SubscriberNotifications_Preferences::decode($subscriber->subscription_preferences ?? '');
                $subscribed = SubscriberNotifications_Preferences::has_item($prefs, $post_id);
            }
        }

        ob_start();
        $this->render_post_subscribe_widget($post, $subscribed, $copy);
        return subscriber_notifications_prepare_shortcode_html(ob_get_clean());
    }

    /**
     * Render post subscribe widget markup.
     *
     * @param WP_Post $post       Post object.
     * @param bool    $subscribed Whether the current visitor is subscribed to this post.
     * @param array<string, string> $copy       Copy overrides from parse_atts().
     */
    private function render_post_subscribe_widget($post, $subscribed = false, array $copy = array()) {
        $post_title    = get_the_title($post);
        $display_label = SubscriberNotifications_Content_Config::get_post_type_singular_label($post->post_type);
        $strings       = $this->get_post_subscribe_strings($post_title, $display_label);
        if (!empty($copy)) {
            $strings = SubscriberNotifications_Post_Subscribe_Display::apply_copy_overrides($strings, $copy);
        }

        $preferences_url = subscriber_notifications_get_preferences_page_url();
        ?>
        <div class="subscriber-notifications-form subscriber-notifications-post-subscribe" id="sn-post-subscribe" data-post-id="<?php echo esc_attr((string) $post->ID); ?>">
            <?php if ($subscribed) : ?>
                <h3 class="sn-post-subscribe-heading"><?php echo esc_html($strings['heading_subscribed']); ?></h3>
                <p class="sn-post-subscribe-description"><?php echo esc_html($strings['description_subscribed']); ?></p>
                <?php if (is_user_logged_in() && $preferences_url !== '') : ?>
                    <p class="sn-form-actions">
                        <a class="subscriber-notifications-submit wp-element-button" href="<?php echo esc_url($preferences_url); ?>"><?php echo esc_html($strings['button_manage']); ?></a>
                    </p>
                <?php endif; ?>
            <?php else : ?>
                <form class="sn-post-subscribe-form" method="post">
                    <h3 class="sn-post-subscribe-heading"><?php echo esc_html($strings['heading']); ?></h3>
                    <p class="sn-post-subscribe-description"><?php echo esc_html($strings['description']); ?></p>
                    <?php wp_nonce_field('subscriber_notifications_post_subscribe', 'post_subscribe_nonce'); ?>
                    <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $post->ID); ?>">
                    <?php if (!empty($copy)) : ?>
                        <input type="hidden" name="post_subscribe_display" value="<?php echo esc_attr(wp_json_encode($copy)); ?>">
                    <?php endif; ?>
                    <?php if (!is_user_logged_in()) : ?>
                        <p>
                            <label for="sn_post_subscribe_name"><?php esc_html_e('Name', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                            <input type="text" id="sn_post_subscribe_name" name="subscriber_name" required>
                        </p>
                        <p>
                            <label for="sn_post_subscribe_email"><?php esc_html_e('Email', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                            <input type="email" id="sn_post_subscribe_email" name="subscriber_email" required>
                        </p>
                        <?php if (!empty(subscriber_notifications_get_option('captcha_site_key', ''))) : ?>
                            <div class="sn-captcha">
                                <div class="g-recaptcha sn-post-subscribe-captcha" data-sitekey="<?php echo esc_attr(subscriber_notifications_get_option('captcha_site_key')); ?>"></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p class="sn-form-actions">
                        <button type="submit" class="subscriber-notifications-submit wp-element-button"><?php echo esc_html($strings['button_subscribe']); ?></button>
                    </p>
                </form>
            <?php endif; ?>
            <div class="sn-post-subscribe-message subscriber-message" style="display:none;" role="status"></div>
        </div>
        <?php
    }

    /**
     * Filterable strings for the post subscribe widget.
     *
     * @param string $post_title    Post title.
     * @param string $display_label Post type display label.
     * @return array<string, string>
     */
    private function get_post_subscribe_strings($post_title, $display_label) {
        $defaults = array(
            'heading'              => sprintf(
                /* translators: %s: content type label */
                __('Subscribe to %s updates', 'subscriber-notifications'),
                $display_label
            ),
            'description'          => sprintf(
                /* translators: %s: post title */
                __('We\'ll email you when %s is updated.', 'subscriber-notifications'),
                $post_title
            ),
            'button_subscribe'     => __('Subscribe', 'subscriber-notifications'),
            'heading_subscribed'   => sprintf(
                /* translators: %s: post title */
                __('You\'re subscribed to %s updates', 'subscriber-notifications'),
                $post_title
            ),
            'description_subscribed' => sprintf(
                /* translators: %s: post title */
                __('You\'ll receive an email when %s is updated.', 'subscriber-notifications'),
                $post_title
            ),
            'button_manage'        => __('Manage', 'subscriber-notifications'),
        );

        return apply_filters('subscriber_notifications_post_subscribe_strings', $defaults, $post_title, $display_label);
    }

    /**
     * AJAX: subscribe to a single post.
     */
    public function handle_post_subscribe() {
        $nonce = isset($_POST['post_subscribe_nonce']) ? sanitize_text_field(wp_unslash($_POST['post_subscribe_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'subscriber_notifications_post_subscribe')) {
            wp_send_json_error(__('Security check failed.', 'subscriber-notifications'));
            return;
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $post    = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            wp_send_json_error(__('This page is not available for subscriptions.', 'subscriber-notifications'));
            return;
        }

        if (!SubscriberNotifications_Content_Config::is_post_eligible_for_single_item($post)) {
            wp_send_json_error(__('Subscriptions are not available for this page.', 'subscriber-notifications'));
            return;
        }

        $display_copy = $this->get_post_subscribe_copy_from_request();

        if (!is_user_logged_in() && !empty(subscriber_notifications_get_option('captcha_site_key', ''))) {
            $captcha = isset($_POST['g-recaptcha-response']) ? wp_unslash($_POST['g-recaptcha-response']) : '';
            if (!$this->verify_captcha($captcha)) {
                wp_send_json_error(__('CAPTCHA verification failed.', 'subscriber-notifications'));
                return;
            }
        }

        $wp_user_id = 0;
        if (is_user_logged_in()) {
            $contact = $this->get_logged_in_contact_for_form();
            if (!$contact || '' === $contact['name'] || empty($contact['email']) || !is_email($contact['email'])) {
                wp_send_json_error(__('Unable to load your account information. Please try again.', 'subscriber-notifications'));
                return;
            }
            $name       = $contact['name'];
            $email      = $contact['email'];
            $wp_user_id = $contact['user_id'];
        } else {
            $name  = sanitize_text_field(wp_unslash($_POST['subscriber_name'] ?? ''));
            $email = sanitize_email(wp_unslash($_POST['subscriber_email'] ?? ''));
            if (empty($name) || empty($email) || !is_email($email)) {
                wp_send_json_error(__('Please provide a valid name and email address.', 'subscriber-notifications'));
                return;
            }
        }

        $default_frequency = apply_filters('subscriber_notifications_default_frequency', 'weekly');
        if (!in_array($default_frequency, array('daily', 'weekly', 'monthly'), true)) {
            $default_frequency = 'weekly';
        }

        $existing = $this->find_existing_subscriber_for_subscription($email, $wp_user_id);

        if ($existing && $existing->status === 'active') {
            $prefs = SubscriberNotifications_Preferences::decode($existing->subscription_preferences ?? '');
            if (SubscriberNotifications_Preferences::has_item($prefs, $post_id)) {
                wp_send_json_success(array(
                    'html' => $this->get_post_subscribe_success_html($post, $display_copy),
                ));
                return;
            }
            $prefs = SubscriberNotifications_Preferences::add_item($prefs, $post_id);
            $this->database->update_subscriber((int) $existing->id, array(
                'subscription_preferences' => $prefs,
            ));
            $updated = $this->database->get_subscriber((int) $existing->id);
            if ($updated) {
                $this->item_notifications->send_item_subscribe_email($updated, $post_id);
            }
            wp_send_json_success(array(
                'html' => $this->get_post_subscribe_success_html($post, $display_copy),
            ));
            return;
        }

        if ($existing && $existing->status === 'inactive') {
            $prefs = SubscriberNotifications_Preferences::decode($existing->subscription_preferences ?? '');
            $prefs = SubscriberNotifications_Preferences::add_item($prefs, $post_id);
            $update_data = array(
                'name'                     => $name,
                'email'                    => $email,
                'subscription_preferences' => $prefs,
                'status'                   => 'active',
                'management_token'         => wp_generate_password(32, false),
            );
            if ($wp_user_id > 0) {
                $update_data['user_id'] = $wp_user_id;
            }
            $this->database->update_subscriber((int) $existing->id, $update_data);
            $updated = $this->database->get_subscriber((int) $existing->id);
            if ($updated) {
                $this->item_notifications->send_item_subscribe_email($updated, $post_id);
            }
            wp_send_json_success(array(
                'html' => $this->get_post_subscribe_success_html($post, $display_copy),
            ));
            return;
        }

        if ($existing) {
            wp_send_json_error(__('This email address is already subscribed.', 'subscriber-notifications'));
            return;
        }

        $subscriber_data = array(
            'name'                     => $name,
            'email'                    => $email,
            'subscription_preferences' => SubscriberNotifications_Preferences::add_item(array(), $post_id),
            'frequency'                => $default_frequency,
            'status'                   => 'active',
            'management_token'         => wp_generate_password(32, false),
        );
        if ($wp_user_id > 0) {
            $subscriber_data['user_id'] = $wp_user_id;
        }

        $subscriber_id = $this->database->add_subscriber($subscriber_data);
        if ($subscriber_id) {
            $subscriber = $this->database->get_subscriber($subscriber_id);
            if ($subscriber) {
                $this->item_notifications->send_item_subscribe_email($subscriber, $post_id);
            }
            wp_send_json_success(array(
                'html' => $this->get_post_subscribe_success_html($post, $display_copy),
            ));
        }

        wp_send_json_error(__('An error occurred. Please try again.', 'subscriber-notifications'));
    }

    /**
     * HTML fragment after successful post subscribe (same session).
     *
     * @param WP_Post $post Post object.
     * @return string
     */
    private function get_post_subscribe_success_html($post, array $copy = array()) {
        ob_start();
        $this->render_post_subscribe_widget($post, true, $copy);
        return subscriber_notifications_prepare_shortcode_html(ob_get_clean());
    }

    /**
     * Copy overrides posted with the post subscribe AJAX form.
     *
     * @return array<string, string>
     */
    private function get_post_subscribe_copy_from_request() {
        if (empty($_POST['post_subscribe_display'])) {
            return array();
        }

        $raw     = wp_unslash((string) $_POST['post_subscribe_display']);
        $decoded = json_decode($raw, true);

        return SubscriberNotifications_Post_Subscribe_Display::sanitize_copy_from_request($decoded);
    }

    public function subscription_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Subscribe to Notifications', 'subscriber-notifications'),
        ), $atts);

        if (!SubscriberNotifications_Content_Config::is_configured()) {
            return $this->render_not_configured_message();
        }

        $reactivation_subscriber = null;
        if (is_user_logged_in()) {
            $subscriber = $this->resolve_subscriber_for_logged_in_session();
            if ($subscriber) {
                $subscriber = $this->ensure_linked_subscriber_integrity($subscriber);
                if (!$subscriber) {
                    // Orphan row removed; allow fresh subscribe.
                } elseif ($subscriber->status === 'active') {
                    return $this->render_subscribe_already_subscribed_state();
                } else {
                    $reactivation_subscriber = $subscriber;
                }
            }
        }

        $this->enqueue_subscription_form_assets();

        ob_start();
        $this->render_subscription_form($atts, $reactivation_subscriber);
        return subscriber_notifications_prepare_shortcode_html(ob_get_clean());
    }

    /**
     * Preferences management shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string Form HTML or empty-state message.
     */
    public function preferences_form_shortcode($atts) {
        $atts = shortcode_atts(array(), $atts);

        $subscriber = $this->resolve_subscriber_for_preferences_page();
        if (!$subscriber) {
            return $this->render_preferences_empty_state();
        }

        $subscriber = $this->ensure_linked_subscriber_integrity($subscriber);
        if (!$subscriber) {
            return $this->render_preferences_empty_state('logged_in');
        }

        $this->maybe_prevent_preferences_page_cache();

        $token = $this->ensure_management_token($subscriber);

        $is_linked = $this->subscriber_has_linked_account($subscriber);
        $linked_contact = $is_linked ? $this->get_linked_contact_for_subscriber($subscriber) : null;

        $script_overrides = array();
        if ($is_linked && $linked_contact) {
            $script_overrides = array(
                'preferencesProfileLocked' => true,
                'lockedName'               => $linked_contact['name'],
                'lockedEmail'              => $linked_contact['email'],
            );
        }

        $this->enqueue_subscription_form_assets($script_overrides);

        ob_start();
        $this->render_preferences_form_content($subscriber, $token, $atts);
        return subscriber_notifications_prepare_shortcode_html(ob_get_clean());
    }

    /**
     * Resolve subscriber for the preferences page (token URL or logged-in session).
     *
     * @return object|null
     */
    private function resolve_subscriber_for_preferences_page() {
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        if ($token !== '') {
            $subscriber = $this->database->get_subscriber_by_management_token($token);
            if ($subscriber) {
                $fresh = $this->database->get_subscriber($subscriber->id);
                if ($fresh && $fresh->management_token === $token) {
                    return $fresh;
                }
            }
            return null;
        }

        if (!is_user_logged_in()) {
            return null;
        }

        return $this->resolve_subscriber_for_logged_in_session();
    }

    /**
     * Prevent full-page caching for token-based preferences views.
     */
    private function maybe_prevent_preferences_page_cache() {
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        if ($token === '') {
            return;
        }

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        if (!headers_sent()) {
            nocache_headers();
        }
    }

    /**
     * Resolve subscriber for a logged-in visitor (user_id, then email auto-link).
     *
     * @return object|null
     */
    private function resolve_subscriber_for_logged_in_session() {
        $user_id = get_current_user_id();
        $subscriber = $this->database->get_subscriber_by_user_id($user_id);
        if ($subscriber) {
            return $subscriber;
        }

        $user = get_userdata($user_id);
        if (!$user instanceof WP_User) {
            return null;
        }

        $email = sanitize_email($user->user_email);
        if ($email === '' || !is_email($email)) {
            return null;
        }

        $by_email = $this->database->get_subscriber_by_email($email);
        if (!$by_email) {
            return null;
        }

        $this->database->update_subscriber((int) $by_email->id, array('user_id' => $user_id));

        return $this->database->get_subscriber((int) $by_email->id);
    }

    /**
     * Delete orphaned linked rows when the WordPress user no longer exists.
     *
     * @param object $subscriber Subscriber row.
     * @return object|null Fresh subscriber or null when removed.
     */
    private function ensure_linked_subscriber_integrity($subscriber) {
        if (!$this->subscriber_has_linked_account($subscriber)) {
            return $subscriber;
        }

        $user_id = absint($subscriber->user_id);
        if ($user_id < 1 || get_userdata($user_id) instanceof WP_User) {
            return $subscriber;
        }

        $this->database->delete_subscriber((int) $subscriber->id);
        return null;
    }

    /**
     * Whether the subscriber row is linked to a WordPress account.
     *
     * @param object $subscriber Subscriber row.
     * @return bool
     */
    private function subscriber_has_linked_account($subscriber) {
        return isset($subscriber->user_id) && absint($subscriber->user_id) > 0;
    }

    /**
     * Live name and email from the linked WordPress user.
     *
     * @param object $subscriber Subscriber row.
     * @return array{name: string, email: string}|null
     */
    private function get_linked_contact_for_subscriber($subscriber) {
        if (!$this->subscriber_has_linked_account($subscriber)) {
            return null;
        }

        $user = get_userdata(absint($subscriber->user_id));
        if (!$user instanceof WP_User) {
            return null;
        }

        $email = sanitize_email($user->user_email);
        if ($email === '' || !is_email($email)) {
            return null;
        }

        return array(
            'name'  => $this->build_display_name_from_user($user),
            'email' => $email,
        );
    }

    /**
     * Ensure management token exists on subscriber row.
     *
     * @param object $subscriber Subscriber row.
     * @return string Token string.
     */
    private function ensure_management_token($subscriber) {
        $token = isset($subscriber->management_token) ? trim((string) $subscriber->management_token) : '';
        if ($token !== '') {
            return $token;
        }

        $token = wp_generate_password(32, false);
        $this->database->update_subscriber((int) $subscriber->id, array('management_token' => $token));

        return $token;
    }

    /**
     * Empty-state markup when no subscriber could be resolved.
     *
     * @param string $context logged_in|guest|invalid_token
     * @return string
     */
    private function render_preferences_empty_state($context = 'guest') {
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        if ($token !== '') {
            $context = 'invalid_token';
        } elseif (is_user_logged_in()) {
            $context = 'logged_in';
        }

        ob_start();
        ?>
        <div class="subscriber-notifications-form subscriber-notifications-empty">
            <?php if ($context === 'invalid_token') : ?>
                <h2><?php esc_html_e('Invalid Link', 'subscriber-notifications'); ?></h2>
                <p><?php esc_html_e('This management link is invalid or has expired. Please use the most recent link from your email.', 'subscriber-notifications'); ?></p>
            <?php elseif ($context === 'logged_in') : ?>
                <h2><?php esc_html_e('Not Subscribed', 'subscriber-notifications'); ?></h2>
                <p><?php esc_html_e('You do not have an active notification subscription.', 'subscriber-notifications'); ?></p>
            <?php else : ?>
                <p><?php esc_html_e('Use the link from your email to manage your notification preferences.', 'subscriber-notifications'); ?></p>
                <?php if (!is_user_logged_in()) : ?>
                    <p>
                        <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">
                            <?php esc_html_e('Log in', 'subscriber-notifications'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            $subscribe_url = subscriber_notifications_get_subscribe_page_url();
            if ($subscribe_url !== '') :
                ?>
                <p>
                    <a href="<?php echo esc_url($subscribe_url); ?>">
                        <?php esc_html_e('Subscribe to notifications', 'subscriber-notifications'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Message when a logged-in user with an active subscription visits the subscribe page.
     *
     * @return string
     */
    private function render_subscribe_already_subscribed_state() {
        ob_start();
        ?>
        <div class="subscriber-notifications-form subscriber-notifications-empty">
            <h2><?php esc_html_e('Already Subscribed', 'subscriber-notifications'); ?></h2>
            <p><?php esc_html_e('You already have an active notification subscription.', 'subscriber-notifications'); ?></p>
            <?php
            $preferences_url = subscriber_notifications_get_preferences_page_url();
            if ($preferences_url !== '') :
                ?>
                <p>
                    <a href="<?php echo esc_url($preferences_url); ?>">
                        <?php esc_html_e('Manage your preferences', 'subscriber-notifications'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render preferences form markup (theme layout; no get_header/get_footer).
     *
     * @param object $subscriber Subscriber row.
     * @param string $token      Management token.
     * @param array  $atts       Shortcode attributes.
     */
    private function render_preferences_form_content($subscriber, $token, $atts = array()) {
        $current_prefs = SubscriberNotifications_Preferences::decode($subscriber->subscription_preferences ?? '');
        $is_linked     = $this->subscriber_has_linked_account($subscriber);
        $linked_contact = $is_linked ? $this->get_linked_contact_for_subscriber($subscriber) : null;

        if ($is_linked && !$linked_contact) {
            echo $this->render_preferences_empty_state('logged_in');
            return;
        }

        $name_value  = $is_linked && $linked_contact ? $linked_contact['name'] : $subscriber->name;
        $email_value = $is_linked && $linked_contact ? $linked_contact['email'] : $subscriber->email;
        $locked_class = $is_linked ? 'subscriber-notifications-field--locked' : '';
        $profile_locked_attr = $is_linked ? ' data-profile-locked="1"' : '';
        $is_inactive                    = ($subscriber->status === 'inactive');
        $show_unsubscribed_confirmation = isset($_GET['unsubscribed']) && sanitize_text_field(wp_unslash($_GET['unsubscribed'])) === '1' && $is_inactive;
        $show_reactivated_confirmation  = isset($_GET['reactivated']) && sanitize_text_field(wp_unslash($_GET['reactivated'])) === '1' && !$is_inactive;
        ?>
        <div class="subscriber-notifications-form">
            <?php if ($show_reactivated_confirmation) : ?>
                <div class="subscriber-notifications-notice subscriber-notifications-notice--success" role="status">
                    <p class="subscriber-notifications-notice__title"><?php esc_html_e('Welcome back!', 'subscriber-notifications'); ?></p>
                    <p><?php esc_html_e('Your subscription has been reactivated. You will receive notifications according to your preferences below.', 'subscriber-notifications'); ?></p>
                </div>
            <?php elseif ($show_unsubscribed_confirmation) : ?>
                <div class="subscriber-notifications-notice subscriber-notifications-notice--success" role="status">
                    <p class="subscriber-notifications-notice__title"><?php esc_html_e('You have been unsubscribed', 'subscriber-notifications'); ?></p>
                    <p><?php esc_html_e('You will no longer receive notifications. Update your preferences below and save to subscribe again.', 'subscriber-notifications'); ?></p>
                </div>
            <?php elseif ($is_inactive) : ?>
                <div class="subscriber-notifications-notice subscriber-notifications-notice--inactive" role="status">
                    <p class="subscriber-notifications-notice__title"><?php esc_html_e('You are currently unsubscribed', 'subscriber-notifications'); ?></p>
                    <p><?php esc_html_e('You will not receive notifications until you update your preferences below and save to reactivate your subscription.', 'subscriber-notifications'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!SubscriberNotifications_Content_Config::is_configured()) : ?>
                <p><?php esc_html_e('Subscriptions are not currently configured. You can still unsubscribe below.', 'subscriber-notifications'); ?></p>
            <?php endif; ?>

            <form id="subscriber-preferences-form" method="post"<?php echo $profile_locked_attr . ($is_inactive ? ' data-reactivating="1"' : ''); ?>>
                <?php wp_nonce_field('subscriber_notifications_update_preferences', 'preferences_nonce'); ?>
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

                <h3><?php esc_html_e('Contact Information', 'subscriber-notifications'); ?></h3>

                <p>
                    <label for="subscriber_name"><?php esc_html_e('Name', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                    <input type="text" id="subscriber_name" name="subscriber_name"
                        value="<?php echo esc_attr($name_value); ?>"
                        class="<?php echo esc_attr($locked_class); ?>"
                        <?php echo $is_linked ? 'readonly' : ''; ?> required>
                    <?php if ($is_linked) : ?>
                        <small class="description"><?php esc_html_e('Taken from your account profile.', 'subscriber-notifications'); ?></small>
                    <?php endif; ?>
                </p>

                <p>
                    <label for="subscriber_email"><?php esc_html_e('Email', 'subscriber-notifications'); ?></label>
                    <input type="email" id="subscriber_email" name="subscriber_email"
                        value="<?php echo esc_attr($email_value); ?>"
                        class="subscriber-notifications-field--locked"
                        readonly>
                    <?php if ($is_linked) : ?>
                        <small class="description"><?php esc_html_e('Taken from your account email address.', 'subscriber-notifications'); ?></small>
                    <?php else : ?>
                        <small class="description"><?php esc_html_e('Email address cannot be changed.', 'subscriber-notifications'); ?></small>
                    <?php endif; ?>
                </p>

                <?php if (SubscriberNotifications_Content_Config::is_configured()) : ?>
                    <?php $this->render_preferences_subscription_sections($current_prefs); ?>
                <?php endif; ?>

                <h3><?php esc_html_e('How often would you like to receive topic notifications?', 'subscriber-notifications'); ?></h3>
                <p class="description sn-frequency-help">
                    <?php esc_html_e('On-page subscriptions are emailed immediately when content is updated. Delivery frequency applies to topic notifications only.', 'subscriber-notifications'); ?>
                </p>
                <?php $this->render_frequency_fieldset($subscriber->frequency); ?>

                <p class="sn-form-actions">
                    <button type="submit" class="subscriber-notifications-submit wp-element-button"><?php
                        echo esc_html(
                            $is_inactive
                                ? __('Reactivate Subscription', 'subscriber-notifications')
                                : __('Update Preferences', 'subscriber-notifications')
                        );
                    ?></button>
                </p>

                <div id="preferences-message" class="subscriber-message" style="display: none;"></div>
            </form>

            <?php if ($subscriber->status === 'active') : ?>
            <div class="unsubscribe-section">
                <hr>
                <h3><?php esc_html_e('Unsubscribe', 'subscriber-notifications'); ?></h3>
                <p><?php esc_html_e('If you no longer wish to receive notifications, you can unsubscribe below.', 'subscriber-notifications'); ?></p>
                <button type="button" id="unsubscribe-button" class="subscriber-notifications-submit wp-element-button unsubscribe-button">
                    <?php esc_html_e('Unsubscribe', 'subscriber-notifications'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a friendly placeholder when Content Types has no enabled post type + taxonomy.
     */
    private function render_not_configured_message() {
        $message = current_user_can('manage_options')
            ? __('Subscriptions are not configured. Visit Notifications → Content Types to enable post types and taxonomies for subscription.', 'subscriber-notifications')
            : __('Subscriptions are not available at this time. Please check back soon.', 'subscriber-notifications');

        return '<div class="subscriber-notifications-form subscriber-notifications-empty"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Render the subscription form (logged-in or anonymous).
     *
     * @param array       $atts                    Form attributes.
     * @param object|null $reactivation_subscriber Inactive subscriber to prefill and reactivate.
     */
    private function render_subscription_form($atts, $reactivation_subscriber = null) {
        $logged_in_contact = $this->get_logged_in_contact_for_form();
        $is_locked         = is_user_logged_in() && null !== $logged_in_contact;
        $name_value        = $is_locked ? $logged_in_contact['name'] : '';
        $email_value       = $is_locked ? $logged_in_contact['email'] : '';
        $locked_class      = $is_locked ? 'subscriber-notifications-field--locked' : '';
        $current_prefs     = array();
        $frequency         = '';

        if ($reactivation_subscriber) {
            $current_prefs = SubscriberNotifications_Preferences::decode($reactivation_subscriber->subscription_preferences ?? '');
            $frequency     = isset($reactivation_subscriber->frequency) ? (string) $reactivation_subscriber->frequency : '';
        }

        ?>
        <div class="subscriber-notifications-form">
            <?php if ($reactivation_subscriber && $reactivation_subscriber->status === 'inactive') : ?>
                <p class="subscriber-notifications-notice">
                    <?php esc_html_e('You are currently unsubscribed. Submitting this form will reactivate your subscription.', 'subscriber-notifications'); ?>
                </p>
            <?php endif; ?>

            <form id="subscriber-notifications-form" method="post">
                <?php wp_nonce_field('subscriber_notifications_subscribe', 'subscriber_nonce'); ?>

                <h3><?php esc_html_e('Contact Information', 'subscriber-notifications'); ?></h3>

                <p>
                    <label for="subscriber_name"><?php esc_html_e('Name', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                    <input type="text" id="subscriber_name" name="subscriber_name"
                        value="<?php echo esc_attr($name_value); ?>"
                        class="<?php echo esc_attr($locked_class); ?>"
                        <?php echo $is_locked ? 'readonly' : ''; ?> required>
                    <?php if ($is_locked) : ?>
                        <small class="description"><?php esc_html_e('Taken from your account profile.', 'subscriber-notifications'); ?></small>
                    <?php endif; ?>
                </p>

                <p>
                    <label for="subscriber_email"><?php esc_html_e('Email', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                    <input type="email" id="subscriber_email" name="subscriber_email"
                        value="<?php echo esc_attr($email_value); ?>"
                        class="<?php echo esc_attr($locked_class); ?>"
                        <?php echo $is_locked ? 'readonly' : ''; ?> required>
                    <?php if ($is_locked) : ?>
                        <small class="description"><?php esc_html_e('Taken from your account email address.', 'subscriber-notifications'); ?></small>
                    <?php endif; ?>
                </p>

                <h3><?php esc_html_e('Choose your subscriptions', 'subscriber-notifications'); ?></h3>
                <?php $this->render_preferences_sections($current_prefs); ?>

                <h3><?php esc_html_e('How often would you like to receive topic notifications?', 'subscriber-notifications'); ?></h3>
                <p class="description sn-frequency-help">
                    <?php esc_html_e('Delivery frequency applies to topic notifications only. On-page subscriptions are emailed immediately when content is updated.', 'subscriber-notifications'); ?>
                </p>
                <?php $this->render_frequency_fieldset($frequency); ?>

                <?php if (!empty(subscriber_notifications_get_option('captcha_site_key', ''))): ?>
                    <div class="sn-captcha">
                        <div class="g-recaptcha" data-sitekey="<?php echo esc_attr(subscriber_notifications_get_option('captcha_site_key')); ?>"></div>
                    </div>
                <?php endif; ?>

                <p class="sn-form-actions">
                    <button type="submit" class="subscriber-notifications-submit wp-element-button"><?php esc_html_e('Subscribe', 'subscriber-notifications'); ?></button>
                </p>

                <div id="subscriber-message" class="subscriber-message" style="display: none;"></div>
            </form>
        </div>
        <?php
    }

    /**
     * Subscription topics + specific page updates on the preferences form.
     *
     * @param array $current_prefs Existing subscriber preferences.
     */
    private function render_preferences_subscription_sections(array $current_prefs) {
        $has_topic_sections = false;
        foreach (SubscriberNotifications_Content_Config::get_enabled_post_types() as $post_type) {
            if (!empty(SubscriberNotifications_Content_Config::get_form_taxonomies($post_type))) {
                $has_topic_sections = true;
                break;
            }
        }
        if ($has_topic_sections) {
            echo '<h3>' . esc_html__('Choose your subscriptions', 'subscriber-notifications') . '</h3>';
            $this->render_preferences_sections($current_prefs);
        }
        $this->render_item_preferences_section($current_prefs);
    }

    /**
     * Accordion checklist of single-post subscriptions grouped by post type.
     *
     * @param array $current_prefs Existing preferences.
     */
    private function render_item_preferences_section(array $current_prefs) {
        $groups = array();
        foreach (SubscriberNotifications_Content_Config::get_single_item_post_types() as $post_type) {
            $items = SubscriberNotifications_Preferences::get_items($current_prefs, $post_type);
            if (!empty($items)) {
                $groups[ $post_type ] = $items;
            }
        }
        if (empty($groups)) {
            return;
        }

        echo '<h3>' . esc_html__('On-page subscriptions', 'subscriber-notifications') . '</h3>';
        echo '<p class="description">' . esc_html__('Uncheck a page to stop receiving immediate update emails for that content.', 'subscriber-notifications') . '</p>';

        foreach ($groups as $post_type => $items) {
            $post_type_label = SubscriberNotifications_Content_Config::get_post_type_label($post_type);
            $select_all_target = 'preferences[' . $post_type . '][' . SubscriberNotifications_Preferences::ITEMS_KEY . ']';
            $field_name        = $select_all_target . '[]';

            $posts = array();
            foreach ($items as $post_id) {
                $post = get_post((int) $post_id);
                if ($post) {
                    $posts[] = $post;
                }
            }
            usort(
                $posts,
                function ($a, $b) {
                    return strcasecmp(get_the_title($a), get_the_title($b));
                }
            );
            if (empty($posts)) {
                continue;
            }
            ?>
            <details class="sn-section">
                <summary><strong><?php echo esc_html($post_type_label); ?></strong></summary>
                <div class="sn-section__body">
                    <fieldset class="sn-taxonomy sn-item-subscriptions" data-target="<?php echo esc_attr($select_all_target); ?>">
                        <label class="sn-select-all-label">
                            <input type="checkbox" class="sn-select-all" data-target="<?php echo esc_attr($select_all_target); ?>" />
                            <?php
                            /* translators: %s: Post type label */
                            printf(esc_html__('Select all %s', 'subscriber-notifications'), esc_html($post_type_label));
                            ?>
                        </label>
                        <ul class="sn-term-list sn-item-subscriptions-list">
                            <?php foreach ($posts as $post) :
                                $post_id = (int) $post->ID;
                                ?>
                                <li class="sn-term-item">
                                    <label>
                                        <input type="checkbox"
                                            name="<?php echo esc_attr($field_name); ?>"
                                            value="<?php echo esc_attr((string) $post_id); ?>"
                                            checked />
                                        <?php if ($post->post_status === 'publish') : ?>
                                            <a href="<?php echo esc_url(get_permalink($post)); ?>"><?php echo esc_html(get_the_title($post)); ?></a>
                                        <?php else : ?>
                                            <?php echo esc_html(SubscriberNotifications_Preferences::get_item_label($post_id)); ?>
                                        <?php endif; ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </fieldset>
                </div>
            </details>
            <?php
        }
    }

    /**
     * Render one `<details>` block per enabled post type with a checklist per
     * `enabled_on_form` taxonomy inside.
     *
     * @param array $current_prefs Existing subscriber preferences (preferences shape).
     */
    private function render_preferences_sections(array $current_prefs) {
        $enabled_post_types = SubscriberNotifications_Content_Config::get_enabled_post_types();
        foreach ($enabled_post_types as $post_type) {
            $form_taxonomies = SubscriberNotifications_Content_Config::get_form_taxonomies($post_type);
            if (empty($form_taxonomies)) {
                continue;
            }
            $post_type_label = SubscriberNotifications_Content_Config::get_post_type_label($post_type);
            ?>
            <details class="sn-section">
                <summary><strong><?php echo esc_html($post_type_label); ?></strong></summary>
                <div class="sn-section__body">
                <?php foreach ($form_taxonomies as $taxonomy) :
                    $tax_label = SubscriberNotifications_Content_Config::get_taxonomy_label($post_type, $taxonomy);
                    $terms = SubscriberNotifications_Term_Resolver::get_terms_for_public_form($post_type, $taxonomy);
                    if (empty($terms)) {
                        continue;
                    }
                    $field_name = 'preferences[' . $post_type . '][' . $taxonomy . '][]';
                    $select_all_target = 'preferences[' . $post_type . '][' . $taxonomy . ']';
                    $selected_ids = isset($current_prefs[$post_type][$taxonomy]) && is_array($current_prefs[$post_type][$taxonomy])
                        ? array_map('intval', $current_prefs[$post_type][$taxonomy])
                        : array();
                ?>
                    <fieldset class="sn-taxonomy" data-target="<?php echo esc_attr($select_all_target); ?>">
                        <legend><?php echo esc_html($tax_label); ?></legend>
                        <label class="sn-select-all-label">
                            <input type="checkbox" class="sn-select-all" data-target="<?php echo esc_attr($select_all_target); ?>" />
                            <?php
                            /* translators: %s: Taxonomy label */
                            printf(esc_html__('Select all %s', 'subscriber-notifications'), esc_html($tax_label));
                            ?>
                        </label>
                        <?php
                        SubscriberNotifications_Term_Checklist::render(
                            $terms,
                            $field_name,
                            $selected_ids,
                            $taxonomy
                        );
                        ?>
                    </fieldset>
                <?php endforeach; ?>
                </div>
            </details>
            <?php
        }
    }

    /**
     * Render the frequency field label and radio fieldset.
     *
     * @param string $current Current frequency value.
     */
    private function render_frequency_fieldset($current) {
        $options = array(
            'daily'   => __('Daily', 'subscriber-notifications'),
            'weekly'  => __('Weekly', 'subscriber-notifications'),
            'monthly' => __('Monthly', 'subscriber-notifications'),
        );
        $is_first = true;
        ?>
        <p>
            <label id="sn-frequency-label"><?php esc_html_e('Delivery Frequency', 'subscriber-notifications'); ?> <span class="required">*</span></label>
        </p>
        <fieldset class="sn-frequency" aria-required="true" aria-labelledby="sn-frequency-label">
            <legend class="screen-reader-text"><?php esc_html_e('Delivery Frequency', 'subscriber-notifications'); ?></legend>
            <?php foreach ($options as $value => $label) : ?>
                <label>
                    <input type="radio" name="frequency" value="<?php echo esc_attr($value); ?>"
                        <?php checked($current, $value); ?>
                        <?php echo $is_first ? 'required aria-required="true"' : ''; ?>>
                    <?php echo esc_html($label); ?>
                </label>
                <?php
                $is_first = false;
            endforeach;
            ?>
        </fieldset>
        <?php
    }

    /**
     * Handle subscription form submission.
     */
    public function handle_subscription() {
        $nonce = isset($_POST['subscriber_nonce']) ? sanitize_text_field(wp_unslash($_POST['subscriber_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'subscriber_notifications_subscribe')) {
            wp_send_json_error(__('Security check failed.', 'subscriber-notifications'));
            return;
        }

        if (!SubscriberNotifications_Content_Config::is_configured()) {
            wp_send_json_error(__('Subscriptions are not configured.', 'subscriber-notifications'));
            return;
        }

        if (!empty(subscriber_notifications_get_option('captcha_site_key', ''))) {
            $captcha = isset($_POST['g-recaptcha-response']) ? wp_unslash($_POST['g-recaptcha-response']) : '';
            if (!$this->verify_captcha($captcha)) {
                wp_send_json_error(__('CAPTCHA verification failed.', 'subscriber-notifications'));
                return;
            }
        }

        $raw_prefs = isset($_POST['preferences']) ? wp_unslash($_POST['preferences']) : array();
        $prefs     = SubscriberNotifications_Preferences::sanitize_from_post($raw_prefs);
        $prefs     = SubscriberNotifications_Preferences::prune_to_allowed_terms($prefs, 'public');

        if (!SubscriberNotifications_Preferences::has_at_least_one_term($prefs)) {
            wp_send_json_error(__('Please select at least one option to subscribe to.', 'subscriber-notifications'));
            return;
        }

        $frequency = sanitize_text_field(wp_unslash($_POST['frequency'] ?? ''));
        if (!in_array($frequency, array('daily', 'weekly', 'monthly'), true)) {
            wp_send_json_error(__('Please select a frequency preference.', 'subscriber-notifications'));
            return;
        }

        $wp_user_id = 0;

        if (is_user_logged_in()) {
            $contact = $this->get_logged_in_contact_for_form();
            if (!$contact) {
                wp_send_json_error(__('Unable to load your account information. Please try again.', 'subscriber-notifications'));
                return;
            }
            if ('' === $contact['name']) {
                wp_send_json_error(__('Please add your first or last name to your profile before subscribing.', 'subscriber-notifications'));
                return;
            }
            if (empty($contact['email']) || !is_email($contact['email'])) {
                wp_send_json_error(__('Your account does not have a valid email address.', 'subscriber-notifications'));
                return;
            }
            $name       = $contact['name'];
            $email      = $contact['email'];
            $wp_user_id = $contact['user_id'];
        } else {
            $name  = sanitize_text_field(wp_unslash($_POST['subscriber_name'] ?? ''));
            $email = sanitize_email(wp_unslash($_POST['subscriber_email'] ?? ''));

            if (empty($name) || empty($email) || !is_email($email)) {
                wp_send_json_error(__('Please provide a valid name and email address.', 'subscriber-notifications'));
                return;
            }
        }

        $existing_subscriber = $this->find_existing_subscriber_for_subscription($email, $wp_user_id);

        if ($existing_subscriber) {
            if ($existing_subscriber->status === 'inactive') {
                $update_data = array(
                    'name'                     => $name,
                    'email'                    => $email,
                    'subscription_preferences' => $prefs,
                    'frequency'                => $frequency,
                    'status'                   => 'active',
                    'management_token'         => wp_generate_password(32, false),
                );

                if ($wp_user_id > 0) {
                    $update_data['user_id'] = $wp_user_id;
                }

                $updated = $this->database->update_subscriber($existing_subscriber->id, $update_data);

                if ($updated !== false) {
                    $subscriber = $this->database->get_subscriber($existing_subscriber->id);
                    if ($subscriber) {
                        $this->send_welcome_back_email($subscriber);
                    }
                    wp_send_json_success(__('Welcome back! Your subscription has been reactivated.', 'subscriber-notifications'));
                } else {
                    wp_send_json_error(__('An error occurred. Please try again.', 'subscriber-notifications'));
                }
                return;
            }

            wp_send_json_error(__('This email address is already subscribed.', 'subscriber-notifications'));
            return;
        }

        $subscriber_data = array(
            'name'                     => $name,
            'email'                    => $email,
            'subscription_preferences' => $prefs,
            'frequency'                => $frequency,
            'status'                   => 'active',
            'management_token'         => wp_generate_password(32, false),
        );

        if ($wp_user_id > 0) {
            $subscriber_data['user_id'] = $wp_user_id;
        }

        $subscriber_id = $this->database->add_subscriber($subscriber_data);

        if ($subscriber_id) {
            $subscriber = $this->database->get_subscriber($subscriber_id);
            if ($subscriber) {
                $this->send_welcome_email($subscriber);
            }
            wp_send_json_success(__('Thank you for subscribing! You will now receive notifications according to your preferences.', 'subscriber-notifications'));
        } else {
            wp_send_json_error(__('An error occurred. Please try again.', 'subscriber-notifications'));
        }
    }

    /**
     * Verify reCAPTCHA response.
     *
     * @param string $response CAPTCHA response token.
     * @return bool
     */
    private function verify_captcha($response) {
        $secret_key = subscriber_notifications_get_option('captcha_secret_key', '');
        if (empty($secret_key)) {
            return true;
        }

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if (!empty($remote_ip) && !filter_var($remote_ip, FILTER_VALIDATE_IP)) {
            $remote_ip = '';
        }
        $data = array(
            'secret'   => $secret_key,
            'response' => $response,
            'remoteip' => $remote_ip,
        );

        $http = wp_remote_post($url, array(
            'body'    => $data,
            'timeout' => 30,
        ));

        if (is_wp_error($http)) {
            return false;
        }

        $body   = wp_remote_retrieve_body($http);
        $result = json_decode($body, true);

        return isset($result['success']) && $result['success'] === true;
    }

    /**
     * Send welcome email after subscribe.
     *
     * @param object $subscriber Subscriber row.
     */
    private function send_welcome_email($subscriber) {
        $subject = subscriber_notifications_get_option('welcome_email_subject', __('Welcome! Your subscription is confirmed', 'subscriber-notifications'));
        $content = subscriber_notifications_get_option('welcome_email_content', __('Thank you for subscribing! You will receive [delivery_frequency] updates about [selected_subscriptions].', 'subscriber-notifications'));

        $shortcodes = new SubscriberNotifications_Shortcodes();
        $processed_subject = $shortcodes->process_shortcodes($subject, $subscriber);
        $processed_content = $shortcodes->process_shortcodes($content, $subscriber);

        $email_css = subscriber_notifications_get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $processed_content = $formatter->wrap_content_with_css($processed_content, $email_css, $subscriber);

        $mailer = new SubscriberNotifications_Email_Sender();
        $mailer->send_email($subscriber->email, $processed_subject, $processed_content, $subscriber->id, 0, 'welcome');
    }

    /**
     * Send welcome-back email after reactivating a subscription.
     *
     * @param object $subscriber Subscriber row.
     */
    private function send_welcome_back_email($subscriber) {
        $subject = subscriber_notifications_get_option('welcome_back_email_subject', __('Welcome back! Your subscription has been reactivated', 'subscriber-notifications'));
        $content = subscriber_notifications_get_option('welcome_back_email_content', __('Welcome back, [subscriber_name]! Your subscription has been reactivated. You will receive [delivery_frequency] updates about [selected_subscriptions].', 'subscriber-notifications'));

        $shortcodes = new SubscriberNotifications_Shortcodes();
        $processed_subject = $shortcodes->process_shortcodes($subject, $subscriber);
        $processed_content = $shortcodes->process_shortcodes($content, $subscriber);

        $email_css = subscriber_notifications_get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $processed_content = $formatter->wrap_content_with_css($processed_content, $email_css, $subscriber);

        $mailer = new SubscriberNotifications_Email_Sender();
        $mailer->send_email($subscriber->email, $processed_subject, $processed_content, $subscriber->id, 0, 'welcome_back');
    }


    /**
     * Resolve subscriber for preferences AJAX from token and/or logged-in session.
     *
     * @return object|null
     */
    private function resolve_subscriber_for_preferences_request() {
        $token      = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $subscriber = null;

        if ($token !== '') {
            $subscriber = $this->database->get_subscriber_by_management_token($token);
        }

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $by_user = $this->database->get_subscriber_by_user_id($user_id);

            if ($by_user) {
                if ($subscriber && (int) $subscriber->id !== (int) $by_user->id) {
                    return null;
                }
                return $by_user;
            }

            if ($subscriber && $this->subscriber_has_linked_account($subscriber)) {
                if ((int) $subscriber->user_id !== $user_id) {
                    return null;
                }
            }
        }

        return $subscriber;
    }

    /**
     * Name for preferences update (profile for linked accounts, POST for guests).
     *
     * @param object $subscriber Subscriber row.
     * @return string
     */
    private function resolve_preferences_contact_name($subscriber) {
        if ($this->subscriber_has_linked_account($subscriber)) {
            $linked = $this->get_linked_contact_for_subscriber($subscriber);
            if ($linked && $linked['name'] !== '') {
                return $linked['name'];
            }
            return '';
        }

        return isset($_POST['subscriber_name']) ? sanitize_text_field(wp_unslash($_POST['subscriber_name'])) : '';
    }

    /**
     * Email for preferences update (profile for linked accounts; unchanged for guests).
     *
     * @param object $subscriber Subscriber row.
     * @return string|null Email when linked account should sync; null to omit from update.
     */
    private function resolve_preferences_contact_email_for_update($subscriber) {
        if (!$this->subscriber_has_linked_account($subscriber)) {
            return null;
        }

        $linked = $this->get_linked_contact_for_subscriber($subscriber);
        if (!$linked || $linked['email'] === '') {
            return null;
        }

        return $linked['email'];
    }

    /**
     * Handle preferences update AJAX request.
     */
    public function handle_preferences_update() {
        $nonce = isset($_POST['preferences_nonce']) ? sanitize_text_field(wp_unslash($_POST['preferences_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'subscriber_notifications_update_preferences')) {
            wp_send_json_error(__('Security check failed.', 'subscriber-notifications'));
            return;
        }

        $subscriber = $this->resolve_subscriber_for_preferences_request();

        if (!$subscriber) {
            wp_send_json_error(__('Invalid management link.', 'subscriber-notifications'));
            return;
        }

        $subscriber = $this->ensure_linked_subscriber_integrity($subscriber);
        if (!$subscriber) {
            wp_send_json_error(__('Invalid management link.', 'subscriber-notifications'));
            return;
        }

        $name      = $this->resolve_preferences_contact_name($subscriber);
        $frequency = isset($_POST['frequency']) ? sanitize_text_field(wp_unslash($_POST['frequency'])) : '';

        if (!in_array($frequency, array('daily', 'weekly', 'monthly'), true)) {
            wp_send_json_error(__('Please select a frequency preference.', 'subscriber-notifications'));
            return;
        }

        if ($name === '') {
            if ($this->subscriber_has_linked_account($subscriber)) {
                wp_send_json_error(__('Please add your first or last name to your profile before updating preferences.', 'subscriber-notifications'));
            } else {
                wp_send_json_error(__('Please provide a valid name.', 'subscriber-notifications'));
            }
            return;
        }

        $raw_prefs = isset($_POST['preferences']) ? wp_unslash($_POST['preferences']) : array();
        $prefs     = SubscriberNotifications_Preferences::sanitize_from_post($raw_prefs);
        $prefs     = SubscriberNotifications_Preferences::prune_for_save($prefs, 'public');

        if (SubscriberNotifications_Content_Config::is_configured() && !SubscriberNotifications_Preferences::has_any_subscription($prefs)) {
            if ($subscriber->status === 'inactive') {
                wp_send_json_error(__('Please select at least one option to reactivate your subscription.', 'subscriber-notifications'));
            } else {
                wp_send_json_error(__('Please select at least one option, or use the Unsubscribe button below.', 'subscriber-notifications'));
            }
            return;
        }

        $was_inactive = ($subscriber->status === 'inactive');

        $update_data = array(
            'name'                     => $name,
            'subscription_preferences' => $prefs,
            'frequency'                => $frequency,
        );

        $sync_email = $this->resolve_preferences_contact_email_for_update($subscriber);
        if ($sync_email !== null) {
            $update_data['email'] = $sync_email;
        }

        if ($was_inactive) {
            $update_data['status'] = 'active';
        }

        $result = $this->database->update_subscriber($subscriber->id, $update_data);

        if ($result !== false) {
            $updated_subscriber = $this->database->get_subscriber($subscriber->id);
            if ($updated_subscriber) {
                if ($was_inactive) {
                    $this->send_welcome_back_email($updated_subscriber);
                } else {
                    $this->send_preferences_update_email($updated_subscriber);
                }
            }
            if ($was_inactive) {
                wp_send_json_success(__('Welcome back! Your subscription has been reactivated.', 'subscriber-notifications'));
            } else {
                wp_send_json_success(__('Your preferences have been updated successfully.', 'subscriber-notifications'));
            }
        } else {
            wp_send_json_error(__('An error occurred while updating your preferences. Please try again.', 'subscriber-notifications'));
        }
    }

    /**
     * Handle unsubscribe AJAX request.
     */
    public function handle_unsubscribe_action() {
        $nonce = isset($_POST['unsubscribe_nonce']) ? sanitize_text_field(wp_unslash($_POST['unsubscribe_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'subscriber_notifications_unsubscribe')) {
            wp_send_json_error(__('Security check failed.', 'subscriber-notifications'));
            return;
        }

        $subscriber = $this->resolve_subscriber_for_preferences_request();

        if (!$subscriber) {
            wp_send_json_error(__('Invalid management link.', 'subscriber-notifications'));
            return;
        }

        $result = $this->database->update_subscriber($subscriber->id, array('status' => 'inactive'));

        if ($result !== false) {
            wp_send_json_success(__('You have been successfully unsubscribed from our notifications.', 'subscriber-notifications'));
        } else {
            wp_send_json_error(__('An error occurred while unsubscribing. Please try again.', 'subscriber-notifications'));
        }
    }

    /**
     * Send preferences update confirmation email.
     *
     * @param object $subscriber Subscriber row.
     */
    private function send_preferences_update_email($subscriber) {
        $subject = subscriber_notifications_get_option('preferences_update_email_subject', __('Your preferences have been updated', 'subscriber-notifications'));
        $default_content = subscriber_notifications_default_preferences_update_email_content();
        $content = subscriber_notifications_get_option('preferences_update_email_content', $default_content);

        $shortcodes = new SubscriberNotifications_Shortcodes();
        $processed_subject = $shortcodes->process_shortcodes($subject, $subscriber);
        $processed_content = $shortcodes->process_shortcodes($content, $subscriber);

        $email_css = subscriber_notifications_get_option('email_css', '');
        $formatter = SubscriberNotifications_Email_Formatter::get_instance();
        $processed_content = $formatter->wrap_content_with_css($processed_content, $email_css, $subscriber);

        $mailer = new SubscriberNotifications_Email_Sender();
        $mailer->send_email($subscriber->email, $processed_subject, $processed_content, $subscriber->id, 0, 'preferences_update');
    }

    /**
     * Build display name from WordPress first_name / last_name user meta.
     *
     * @param int|WP_User $user User ID or object.
     * @return string Trimmed display name (may be empty).
     */
    private function build_display_name_from_user($user) {
        if (is_numeric($user)) {
            $user = get_userdata((int) $user);
        }

        if (!$user instanceof WP_User) {
            return '';
        }

        $first = trim((string) get_user_meta($user->ID, 'first_name', true));
        $last  = trim((string) get_user_meta($user->ID, 'last_name', true));

        if ('' !== $first && '' !== $last) {
            return trim($first . ' ' . $last);
        }

        if ('' !== $first) {
            return $first;
        }

        return $last;
    }

    /**
     * Contact fields for the subscription form when the visitor is logged in.
     *
     * @return array{user_id: int, name: string, email: string}|null Null when not logged in.
     */
    private function get_logged_in_contact_for_form() {
        if (!is_user_logged_in()) {
            return null;
        }

        $user_id = get_current_user_id();
        $user    = get_userdata($user_id);

        if (!$user instanceof WP_User) {
            return null;
        }

        $email = sanitize_email($user->user_email);

        return array(
            'user_id' => $user_id,
            'name'    => $this->build_display_name_from_user($user),
            'email'   => $email,
        );
    }

    /**
     * Find an existing subscriber row for a subscription attempt.
     *
     * @param string $email     Account or submitted email.
     * @param int    $wp_user_id WordPress user ID when logged in (0 for guests).
     * @return object|null
     */
    private function find_existing_subscriber_for_subscription($email, $wp_user_id) {
        if ($wp_user_id > 0) {
            $by_user = $this->database->get_subscriber_by_user_id($wp_user_id);
            if ($by_user) {
                return $by_user;
            }
        }

        if (!empty($email) && is_email($email)) {
            return $this->database->get_subscriber_by_email($email);
        }

        return null;
    }
}
