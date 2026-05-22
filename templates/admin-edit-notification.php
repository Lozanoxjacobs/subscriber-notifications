<?php
/**
 * Edit Notification Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Edit Notification', 'subscriber-notifications'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=subscriber-notifications-create')); ?>" class="page-title-action">
        <?php _e('Add New Notification', 'subscriber-notifications'); ?>
    </a>
    <hr class="wp-header-end">

    <?php settings_errors('subscriber_notifications'); ?>
    
    <?php if ($notification->status === 'sent'): ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('Warning:', 'subscriber-notifications'); ?></strong>
                <?php _e('This notification has already been sent. Editing it will not affect the emails that were already delivered, but will update the notification for future reference.', 'subscriber-notifications'); ?>
            </p>
        </div>
    <?php elseif ($notification->status === 'cancelled'): ?>
        <div class="notice notice-info">
            <p>
                <strong><?php _e('Info:', 'subscriber-notifications'); ?></strong>
                <?php _e('This notification was cancelled. You can edit it to reuse the content or make corrections.', 'subscriber-notifications'); ?>
            </p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="" class="notification-form" id="edit-notification-form">
        <?php wp_nonce_field('update_notification', 'notification_nonce'); ?>
        <input type="hidden" name="notification_id" value="<?php echo $notification->id; ?>">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="notification_title"><?php _e('Notification Title', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <input type="text" id="notification_title" name="notification_title" 
                           value="<?php echo esc_attr($notification->title); ?>" 
                           class="regular-text" required>
                    <p class="description"><?php _e('Internal title for this notification.', 'subscriber-notifications'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="notification_subject"><?php _e('Email Subject', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <input type="text" id="notification_subject" name="notification_subject" 
                           value="<?php echo esc_attr(wp_unslash($notification->subject)); ?>" 
                           class="regular-text" required>
                    <p class="description"><?php _e('Email subject line. Shortcodes are supported.', 'subscriber-notifications'); ?></p>
                    <?php SubscriberNotifications_Admin::render_shortcode_reference_description(); ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="notification_content"><?php _e('Notification Content', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <?php
                    wp_editor(wp_unslash($notification->content), 'notification_content', array(
                        'textarea_name' => 'notification_content',
                        'media_buttons' => true,
                        'textarea_rows' => 10,
                        'teeny' => false
                    ));
                    ?>
                    <?php SubscriberNotifications_Admin::render_shortcode_reference_description(); ?>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Target Content', 'subscriber-notifications'); ?> <span class="required">*</span></th>
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
                    <select id="frequency_target" name="frequency_target" required>
                        <option value=""><?php _e('Select frequency', 'subscriber-notifications'); ?></option>
                        <option value="daily" <?php selected($notification->frequency_target, 'daily'); ?>>
                            <?php _e('Daily', 'subscriber-notifications'); ?>
                        </option>
                        <option value="weekly" <?php selected($notification->frequency_target, 'weekly'); ?>>
                            <?php _e('Weekly', 'subscriber-notifications'); ?>
                        </option>
                        <option value="monthly" <?php selected($notification->frequency_target, 'monthly'); ?>>
                            <?php _e('Monthly', 'subscriber-notifications'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('Only subscribers with this frequency preference will receive this notification.', 'subscriber-notifications'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="is_recurring"><?php _e('Recurring Notification', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="is_recurring" id="is_recurring" value="1" 
                               <?php checked(isset($notification->is_recurring) && $notification->is_recurring); ?>>
                        <?php _e('Make this notification recurring', 'subscriber-notifications'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Check this box to send this notification repeatedly based on the target frequency schedule. Unchecked notifications are sent only once.', 'subscriber-notifications'); ?>
                    </p>
                    <?php
                    $show_schedule_line = (isset($notification->is_recurring) && $notification->is_recurring) ||
                        (isset($notification->status) && $notification->status === 'pending' &&
                            !empty($notification->next_send_date));
                    if ($show_schedule_line):
                    ?>
                        <p class="description" style="color: #0073aa; font-weight: bold;">
                            <?php 
                            if (isset($notification->is_recurring) && $notification->is_recurring && isset($notification->recurrence_count)) {
                                printf(__('This notification has been sent %d times.', 'subscriber-notifications'), $notification->recurrence_count);
                            }
                            if (isset($notification->next_send_date) && $notification->next_send_date &&
                                isset($notification->status) && $notification->status === 'pending') {
                                $datetime = new DateTimeImmutable($notification->next_send_date, wp_timezone());
                                printf(__(' Next send: %s', 'subscriber-notifications'), esc_html($datetime->format('M j, Y g:i A')));
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="update_notification" class="button-primary" 
                   value="<?php _e('Update Notification', 'subscriber-notifications'); ?>">
            <a href="<?php echo admin_url('admin.php?page=subscriber-notifications-notifications'); ?>" 
               class="button"><?php _e('Cancel', 'subscriber-notifications'); ?></a>
        </p>
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
            alert('<?php _e('Please enter an email address.', 'subscriber-notifications'); ?>');
            return;
        }
        
        if (!subject) {
            alert('<?php _e('Please enter a subject.', 'subscriber-notifications'); ?>');
            return;
        }
        
        if (!content) {
            alert('<?php _e('Please enter content.', 'subscriber-notifications'); ?>');
            return;
        }
        
        var button = $(this);
        var resultDiv = $('#preview-email-result');
        
        button.prop('disabled', true).text('<?php _e('Sending...', 'subscriber-notifications'); ?>');
        resultDiv.html('<p style="color: #666;"><?php _e('Sending preview email...', 'subscriber-notifications'); ?></p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'send_preview_email',
                nonce: '<?php echo wp_create_nonce('send_preview_email'); ?>',
                email: email,
                subject: subject,
                content: content
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<p style="color: #46b450;"><?php _e('Preview email sent successfully!', 'subscriber-notifications'); ?></p>');
                } else {
                    resultDiv.html('<p style="color: #dc3232;"><?php _e('Failed to send preview email: ', 'subscriber-notifications'); ?>' + response.data + '</p>');
                }
            },
            error: function() {
                resultDiv.html('<p style="color: #dc3232;"><?php _e('Failed to send preview email due to an error.', 'subscriber-notifications'); ?></p>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Send Preview Email', 'subscriber-notifications'); ?>');
            }
        });
    });
});
</script>
