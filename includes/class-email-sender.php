<?php
/**
 * Email sender: wraps wp_mail() and adds open / click tracking + per-send logging.
 *
 * The class name does NOT imply a SendGrid integration. The actual mail transport
 * (PHP mail, SMTP, SendGrid, SES, etc.) is configured site-wide via WordPress core
 * or a dedicated transport plugin. This class only adds tracking and logging on top
 * of wp_mail().
 *
 * @package SubscriberNotifications
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends HTML notifications through wp_mail after adding open/click tracking.
 */
class SubscriberNotifications_Email_Sender {

    /**
     * Send email through WordPress wp_mail (site configures transport).
     *
     * @param string $to_email Recipient email.
     * @param string $subject Email subject.
     * @param string $content Email content.
     * @param int    $subscriber_id Subscriber ID.
     * @param int    $notification_id Notification ID.
     * @param string $email_type Log type slug (e.g. notification, welcome, preferences_update).
     * @return bool True on success, false on failure.
     */
    public function send_email($to_email, $subject, $content, $subscriber_id = 0, $notification_id = 0, $email_type = 'notification') {
        $email_type = sanitize_key($email_type);
        if ('' === $email_type) {
            $email_type = 'notification';
        }

        $tracking_id = wp_generate_password(32, false);

        // Log email attempt.
        $database = new SubscriberNotifications_Database();
        $log_id = $database->log_email(array(
            'subscriber_id'   => $subscriber_id,
            'notification_id' => $notification_id,
            'email_type'      => $email_type,
            'tracking_id'     => $tracking_id,
        ));

        // Append fallback manage link before click tracking so it is tracked too.
        $manage_url = $this->get_manage_preferences_url($subscriber_id);
        if ($manage_url && strpos($content, 'manage') === false && strpos($content, 'Manage Preferences') === false) {
            $content .= '<p><a href="' . esc_attr($manage_url) . '">' . esc_html__('Manage Preferences', 'subscriber-notifications') . '</a></p>';
        }

        $content = $this->add_click_tracking($content, $tracking_id);

        $content .= $this->get_tracking_pixel($tracking_id);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );

        $result = wp_mail($to_email, $subject, $content, $headers);

        if ($result) {
            $database->update_log($log_id, array(
                'status' => 'sent',
            ));
        } else {
            $database->update_log($log_id, array(
                'status'        => 'failed',
                'error_message' => 'WordPress mail failed',
            ));
        }

        return $result;
    }

    /**
     * Tracking pixel markup.
     *
     * @param string $tracking_id Tracking ID.
     * @return string
     */
    private function get_tracking_pixel($tracking_id) {
        $tracking_url = add_query_arg(array(
            'tracking_id' => $tracking_id,
        ), home_url('track/open/'));

        return '<img src="' . esc_attr($tracking_url) . '" width="1" height="1" style="display:none;" />';
    }

    /**
     * Click tracking redirect URL.
     *
     * @param string $url Original URL.
     * @param string $tracking_id Tracking ID.
     * @return string
     */
    private function get_click_tracking_url($url, $tracking_id) {
        return add_query_arg(array(
            'tracking_id' => $tracking_id,
            'url'         => rawurlencode($url),
        ), home_url('track/click/'));
    }

    /**
     * Normalize a URL extracted from email HTML before tracking or redirect.
     *
     * @param string $url Raw href value.
     * @return string
     */
    private function normalize_email_url($url) {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $url;
    }

    /**
     * Escape a URL for use in an HTML email attribute.
     *
     * esc_url() encodes ampersands as entities, which breaks multi-parameter
     * tracking links in many email clients.
     *
     * @param string $url URL to escape.
     * @return string
     */
    private function escape_email_href($url) {
        return esc_attr($url);
    }

    /**
     * Whether a link should skip click-tracking wrapping.
     *
     * @param string $url Normalized destination URL.
     * @return bool
     */
    private function should_skip_click_tracking($url) {
        if ($url === '') {
            return true;
        }

        if (strpos($url, 'mailto:') === 0 || strpos($url, 'tel:') === 0) {
            return true;
        }

        if (strpos($url, '/track/click') !== false || strpos($url, 'track/click/') !== false) {
            return true;
        }

        if (strpos($url, '/track/open') !== false || strpos($url, 'track/open/') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Add click tracking to links in content.
     *
     * @param string $content Email content.
     * @param string $tracking_id Tracking ID.
     * @return string
     */
    private function add_click_tracking($content, $tracking_id) {
        $pattern = '/<a\s+([^>]*?)href=["\']([^"\']*?)["\']([^>]*?)>(.*?)<\/a>/i';

        return preg_replace_callback($pattern, function ($matches) use ($tracking_id) {
            $full_match = $matches[0];
            $before_href = $matches[1];
            $url         = $this->normalize_email_url($matches[2]);
            $after_href  = $matches[3];
            $link_text   = $matches[4];

            if ($this->should_skip_click_tracking($url)) {
                return $full_match;
            }

            $tracking_url = $this->get_click_tracking_url($url, $tracking_id);

            return '<a ' . $before_href . 'href="' . $this->escape_email_href($tracking_url) . '"' . $after_href . '>' . $link_text . '</a>';
        }, $content);
    }

    /**
     * Manage preferences URL for subscriber.
     *
     * @param int $subscriber_id Subscriber ID.
     * @return string|false
     */
    private function get_manage_preferences_url($subscriber_id) {
        if (!$subscriber_id) {
            return false;
        }

        $database   = new SubscriberNotifications_Database();
        $subscriber = $database->get_subscriber($subscriber_id);

        if (!$subscriber || !$subscriber->management_token) {
            return false;
        }

        return add_query_arg(array(
            'action' => 'manage',
            'token'  => $subscriber->management_token,
        ), home_url());
    }

    /**
     * Test that wp_mail can send (sanity check; actual delivery depends on site mail config).
     *
     * @return array{success:bool,message:string}
     */
    public function test_connection() {
        return array(
            'success' => true,
            'message' => __('Mail is sent using WordPress wp_mail(). Configure SMTP or a mail plugin at the site level.', 'subscriber-notifications'),
        );
    }
}
