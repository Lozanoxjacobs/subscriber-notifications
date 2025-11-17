<?php
/**
 * Edit Notification Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Edit Notification', 'subscriber-notifications'); ?></h1>
    
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
    
    <form method="post" action="">
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
                    <p class="description">
                        <?php _e('Email subject line. You can use shortcodes like [subscriber_name], [selected_news_categories], etc.', 'subscriber-notifications'); ?>
                    </p>
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
                    <p class="description">
                        <?php _e('Available shortcodes:', 'subscriber-notifications'); ?><br>
                        <code>[subscriber_name]</code> - <?php _e('Subscriber\'s name', 'subscriber-notifications'); ?><br>
                        <code>[subscriber_email]</code> - <?php _e('Subscriber\'s email', 'subscriber-notifications'); ?><br>
                        <code>[selected_news_categories]</code> - <?php _e('Selected news categories', 'subscriber-notifications'); ?><br>
                        <code>[selected_meeting_categories]</code> - <?php _e('Selected meeting categories', 'subscriber-notifications'); ?><br>
                        <code>[delivery_frequency]</code> - <?php _e('Delivery frequency', 'subscriber-notifications'); ?><br>
                        <code>[news_feed duration="1day|1week|1month"]</code> - <?php _e('Personalized news feed (shows only subscriber\'s selected categories)', 'subscriber-notifications'); ?><br>
                        <code>[meetings_feed duration="1day|1week|1month"]</code> - <?php _e('Personalized events feed (shows only subscriber\'s selected categories)', 'subscriber-notifications'); ?><br>
                        <code>[site_title]</code> - <?php _e('Site title', 'subscriber-notifications'); ?><br>
                        <code>[manage_preferences_link]</code> - <?php _e('Manage preferences link', 'subscriber-notifications'); ?><br>
                        <code>[manage_preferences_link text="Custom Text"]</code> - <?php _e('Manage preferences link with custom text', 'subscriber-notifications'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="news_categories"><?php _e('Target News Categories', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <?php if (!empty($news_categories)): ?>
                        <label class="select-all-label" style="display: block; margin-bottom: 10px; padding: 8px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px;">
                            <input type="checkbox" id="select-all-news-admin-edit" class="select-all-checkbox" data-target="news_categories">
                            <strong><?php _e('Select All News Categories', 'subscriber-notifications'); ?></strong>
                        </label>
                        <div class="category-checkboxes" style="margin-left: 20px; border-left: 2px solid #e0e0e0; padding-left: 15px;">
                            <?php foreach ($news_categories as $category): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="news_categories[]" value="<?php echo $category->term_id; ?>" class="news-category-checkbox"
                                           <?php checked(in_array($category->term_id, $selected_news_categories)); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No news categories found.', 'subscriber-notifications'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="meeting_categories"><?php _e('Target Meeting Categories', 'subscriber-notifications'); ?></label>
                </th>
                <td>
                    <?php if (!empty($meeting_categories)): ?>
                        <label class="select-all-label" style="display: block; margin-bottom: 10px; padding: 8px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px;">
                            <input type="checkbox" id="select-all-meetings-admin-edit" class="select-all-checkbox" data-target="meeting_categories">
                            <strong><?php _e('Select All Meeting Categories', 'subscriber-notifications'); ?></strong>
                        </label>
                        <div class="category-checkboxes" style="margin-left: 20px; border-left: 2px solid #e0e0e0; padding-left: 15px;">
                            <?php foreach ($meeting_categories as $category): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="meeting_categories[]" value="<?php echo $category->term_id; ?>" class="meeting-category-checkbox"
                                           <?php checked(in_array($category->term_id, $selected_meeting_categories)); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p><?php _e('No meeting categories found. Make sure The Events Calendar is active.', 'subscriber-notifications'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="frequency_target"><?php _e('Target Frequency', 'subscriber-notifications'); ?></label>
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
                    <?php if (isset($notification->is_recurring) && $notification->is_recurring): ?>
                        <p class="description" style="color: #0073aa; font-weight: bold;">
                            <?php 
                            if (isset($notification->recurrence_count)) {
                                printf(__('This notification has been sent %d times.', 'subscriber-notifications'), $notification->recurrence_count);
                            }
                            if (isset($notification->next_send_date) && $notification->next_send_date) {
                                $timezone = wp_timezone();
                                $datetime = new DateTime($notification->next_send_date, $timezone);
                                printf(__(' Next send: %s', 'subscriber-notifications'), $datetime->format('M j, Y g:i A'));
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
