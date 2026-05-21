<?php
if (!defined('ABSPATH')) {
    exit;
}

$notification_form = isset($notification_form) ? $notification_form : array(
    'title'            => '',
    'subject'          => '',
    'content'          => '',
    'frequency_target' => '',
    'is_recurring'     => 0,
    'selected_targets' => array(),
);
$selected_targets = isset($selected_targets) ? $selected_targets : $notification_form['selected_targets'];
?>

<div class="wrap">
    <h1><?php _e('Create Notification', 'subscriber-notifications'); ?></h1>

    <?php settings_errors('subscriber_notifications'); ?>
    
    <form method="post" action="" id="create-notification-form" class="notification-form">
        <?php wp_nonce_field('create_notification', 'notification_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="notification_title"><?php _e('Notification Title', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" id="notification_title" name="notification_title" class="regular-text" value="<?php echo esc_attr($notification_form['title']); ?>" required>
                    <p class="description"><?php _e('Internal title for this notification.', 'subscriber-notifications'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="notification_subject"><?php _e('Email Subject', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" id="notification_subject" name="notification_subject" class="regular-text" value="<?php echo esc_attr($notification_form['subject']); ?>" required>
                    <p class="description">
                        <?php _e('Email subject line. You can use shortcodes like [subscriber_name], [selected_subscriptions], etc.', 'subscriber-notifications'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="notification_content"><?php _e('Email Content', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <?php
                    wp_editor(
                        $notification_form['content'],
                        'notification_content',
                        array(
                            'textarea_name' => 'notification_content',
                            'media_buttons' => false,
                            'textarea_rows' => 15,
                            'teeny'         => false,
                        )
                    );
                    ?>
                    <p class="description">
                        <?php _e('Available shortcodes:', 'subscriber-notifications'); ?><br>
                        <code>[subscriber_name]</code> - <?php _e('Subscriber\'s name', 'subscriber-notifications'); ?><br>
                        <code>[subscriber_email]</code> - <?php _e('Subscriber\'s email', 'subscriber-notifications'); ?><br>
                        <code>[selected_subscriptions]</code> - <?php _e('Formatted list of selections (HTML in body). Use format="plain" in subject lines only.', 'subscriber-notifications'); ?><br>
                        <code>[selected_terms taxonomy="..."]</code> - <?php _e('Term names from a specific taxonomy', 'subscriber-notifications'); ?><br>
                        <code>[delivery_frequency]</code> - <?php _e('Delivery frequency', 'subscriber-notifications'); ?><br>
                        <code>[content_feed post_type="..." taxonomy="..." terms="term-slug,other-slug" duration="1day|1week|1month" format="list|summary"]</code> - <?php _e('Personalized feed. Optional terms (plural) = comma-separated term slugs; requires taxonomy. Omit taxonomy to match any form taxonomy for that post type.', 'subscriber-notifications'); ?><br>
                        <code>[site_title]</code> - <?php _e('Site title', 'subscriber-notifications'); ?><br>
                        <code>[manage_preferences_link]</code> - <?php _e('Manage preferences link', 'subscriber-notifications'); ?><br>
                        <code>[manage_preferences_link text="Custom Text"]</code> - <?php _e('Manage preferences link with custom text', 'subscriber-notifications'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php esc_html_e('Target Content', 'subscriber-notifications'); ?> <span class="required">*</span>
                </th>
                <td>
                    <?php
                    if (empty($is_configured)) {
                        echo '<p>' . esc_html__('Content Types are not configured. Set them up to define notification targets.', 'subscriber-notifications') . '</p>';
                    } else {
                        require SUBSCRIBER_NOTIFICATIONS_PLUGIN_DIR . 'templates/partials/admin-notification-targets.php';
                    }
                    ?>
                </td>
            </tr>

            
            <tr>
                <th scope="row">
                    <label for="frequency_target"><?php _e('Target Frequency', 'subscriber-notifications'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select name="frequency_target" id="frequency_target" required>
                        <option value=""><?php esc_html_e('Select frequency', 'subscriber-notifications'); ?></option>
                        <option value="daily" <?php selected($notification_form['frequency_target'], 'daily'); ?>><?php esc_html_e('Daily', 'subscriber-notifications'); ?></option>
                        <option value="weekly" <?php selected($notification_form['frequency_target'], 'weekly'); ?>><?php esc_html_e('Weekly', 'subscriber-notifications'); ?></option>
                        <option value="monthly" <?php selected($notification_form['frequency_target'], 'monthly'); ?>><?php esc_html_e('Monthly', 'subscriber-notifications'); ?></option>
                    </select>
                    <p class="description"><?php _e('Only subscribers with this frequency preference will receive this notification.', 'subscriber-notifications'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="is_recurring"><?php _e('Recurring Notification', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="is_recurring" id="is_recurring" value="1" <?php checked(!empty($notification_form['is_recurring'])); ?>>
                        <?php _e('Make this notification recurring', 'subscriber-notifications'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Check this box to send this notification repeatedly based on the target frequency schedule. Unchecked notifications are sent only once.', 'subscriber-notifications'); ?>
                    </p>
                </td>
            </tr>
            
        </table>
        
        <div class="notification-actions">
            <input type="submit" name="create_notification" class="button button-primary" value="<?php esc_attr_e('Create Notification', 'subscriber-notifications'); ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=subscriber-notifications')); ?>" class="button"><?php esc_html_e('Cancel', 'subscriber-notifications'); ?></a>
        </div>
    </form>
    
    <div class="notification-preview-email" style="margin-top: 30px;">
        <h3><?php _e('Send Preview Email', 'subscriber-notifications'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="preview_email"><?php _e('Preview Email Address', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <input type="email" id="preview_email" name="preview_email" class="regular-text" placeholder="test@example.com">
                    <button type="button" id="send-preview-email" class="button button-secondary">
                        <?php _e('Send Preview Email', 'subscriber-notifications'); ?>
                    </button>
                    <p class="description">
                        <?php _e('Send a test email to see how the notification will look. This email will only be sent to the address you specify.', 'subscriber-notifications'); ?>
                    </p>
                    <div id="preview-email-result" style="margin-top: 10px;"></div>
                </td>
            </tr>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Send preview email functionality
    $('#send-preview-email').on('click', function(e) {
        e.preventDefault();
        
        var email = $('#preview_email').val();
        var subject = $('#notification_subject').val();
        var content = '';
        
        // Get content from TinyMCE editor if it exists, otherwise from textarea
        if (typeof tinymce !== 'undefined' && tinymce.get('notification_content')) {
            content = tinymce.get('notification_content').getContent();
        } else {
            content = $('#notification_content').val();
        }
        
        if (!email) {
            alert('<?php echo esc_js(__('Please enter an email address.', 'subscriber-notifications')); ?>');
            return;
        }
        
        if (!subject) {
            alert('<?php echo esc_js(__('Please enter a subject.', 'subscriber-notifications')); ?>');
            return;
        }
        
        if (!content) {
            alert('<?php echo esc_js(__('Please enter content.', 'subscriber-notifications')); ?>');
            return;
        }
        
        var button = $(this);
        var resultDiv = $('#preview-email-result');
        
        button.prop('disabled', true).text('<?php echo esc_js(__('Sending...', 'subscriber-notifications')); ?>');
        resultDiv.html('<p style="color: #666;"><?php echo esc_js(__('Sending preview email...', 'subscriber-notifications')); ?></p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'send_preview_email',
                nonce: '<?php echo esc_js(wp_create_nonce('send_preview_email')); ?>',
                email: email,
                subject: subject,
                content: content
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<p style="color: #46b450;"><?php echo esc_js(__('Preview email sent successfully!', 'subscriber-notifications')); ?></p>');
                } else {
                    resultDiv.html('<p style="color: #dc3232;"><?php echo esc_js(__('Failed to send preview email: ', 'subscriber-notifications')); ?>' + response.data + '</p>');
                }
            },
            error: function() {
                resultDiv.html('<p style="color: #dc3232;"><?php echo esc_js(__('Failed to send preview email due to an error.', 'subscriber-notifications')); ?></p>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php echo esc_js(__('Send Preview Email', 'subscriber-notifications')); ?>');
            }
        });
    });
});
</script>

<style>
.notification-form .form-table th {
    width: 200px;
}

.notification-form .required {
    color: #d63638;
}

.notification-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.notification-actions .button {
    margin-right: 10px;
}

.notification-preview h3 {
    margin-bottom: 10px;
}

#preview-content {
    min-height: 100px;
    max-height: 300px;
    overflow-y: auto;
}
</style>
