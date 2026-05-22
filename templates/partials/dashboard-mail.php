<?php
if (!defined('ABSPATH')) {
    exit;
}

$urls       = $snapshot['urls'] ?? array();
$test_email = $snapshot['test_email'] ?? get_option('admin_email');
?>

<div id="sn-dashboard-mail" class="postbox">
    <div class="postbox-header">
        <h2 class="hndle"><?php esc_html_e('Mail delivery', 'subscriber-notifications'); ?></h2>
    </div>
    <div class="inside">
        <p>
            <?php esc_html_e('Emails are sent through WordPress wp_mail(). Configure SMTP or a mail plugin on your site for reliable delivery.', 'subscriber-notifications'); ?>
        </p>
        <p class="sn-mail-test">
            <label for="sn-dashboard-test-email" class="screen-reader-text"><?php esc_html_e('Test email address', 'subscriber-notifications'); ?></label>
            <input type="email" id="sn-dashboard-test-email" class="regular-text" value="<?php echo esc_attr($test_email); ?>" placeholder="<?php esc_attr_e('you@example.com', 'subscriber-notifications'); ?>">
            <button type="button" class="button" id="sn-dashboard-test-wp-mail"><?php esc_html_e('Send Test Email', 'subscriber-notifications'); ?></button>
        </p>
        <div id="sn-dashboard-wp-mail-test-result" class="sn-test-result" aria-live="polite"></div>
        <p>
            <a href="<?php echo esc_url($urls['settings_general'] ?? '#'); ?>" class="button">
                <?php esc_html_e('General settings', 'subscriber-notifications'); ?>
            </a>
        </p>
    </div>
</div>
