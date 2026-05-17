<?php
/**
 * Email sending with tracking via wp_mail (transport — SMTP/SendGrid — is site-wide).
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
class SubscriberNotifications_SendGrid {

    /**
     * Send email through WordPress wp_mail (site configures transport).
     *
     * @param string $to_email Recipient email.
     * @param string $subject Email subject.
     * @param string $content Email content.
     * @param int    $subscriber_id Subscriber ID.
     * @param int    $notification_id Notification ID.
     * @return bool True on success, false on failure.
     */
    public function send_email($to_email, $subject, $content, $subscriber_id = 0, $notification_id = 0) {
        $tracking_id = wp_generate_password(32, false);

        // Log email attempt.
        $database = new SubscriberNotifications_Database();
        $log_id = $database->log_email(array(
            'subscriber_id'   => $subscriber_id,
            'notification_id' => $notification_id,
            'email_type'      => 'notification',
            'tracking_id'     => $tracking_id,
        ));

        // Add click tracking to links.
        $content = $this->add_click_tracking($content, $tracking_id);

        // Add tracking pixel.
        $tracking_pixel = $this->get_tracking_pixel($tracking_id);
        $content       .= $tracking_pixel;

        // Add manage preferences link only if not already present in global footer.
        $manage_url = $this->get_manage_preferences_url($subscriber_id);
        if ($manage_url && strpos($content, 'manage') === false && strpos($content, 'Manage Preferences') === false) {
            $content .= '<p><a href="' . esc_url($manage_url) . '">' . __('Manage Preferences', 'subscriber-notifications') . '</a></p>';
        }

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

        return '<img src="' . esc_url($tracking_url) . '" width="1" height="1" style="display:none;" />';
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
            $url         = $matches[2];
            $after_href  = $matches[3];
            $link_text   = $matches[4];

            if (strpos($url, 'unsubscribe') !== false || strpos($url, 'action=unsubscribe') !== false) {
                return $full_match;
            }
            if (strpos($url, 'action=manage') !== false || strpos($url, 'token=') !== false) {
                return $full_match;
            }
            if (strpos($url, 'mailto:') === 0 || strpos($url, 'tel:') === 0) {
                return $full_match;
            }

            $tracking_url = $this->get_click_tracking_url($url, $tracking_id);

            return '<a ' . $before_href . 'href="' . esc_url($tracking_url) . '"' . $after_href . '>' . $link_text . '</a>';
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
