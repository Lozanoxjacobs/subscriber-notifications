<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Notifications', 'subscriber-notifications'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=subscriber-notifications-create'); ?>" class="page-title-action">
        <?php _e('Add New', 'subscriber-notifications'); ?>
    </a>
    <hr class="wp-header-end">
    
    <!-- Search and Filter -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" style="display: inline-block;">
                <input type="hidden" name="page" value="subscriber-notifications-notifications">
                <input type="search" name="s" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>" placeholder="<?php _e('Search notifications...', 'subscriber-notifications'); ?>">
                <select name="status">
                    <option value=""><?php _e('All Statuses', 'subscriber-notifications'); ?></option>
                    <option value="pending" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'pending'); ?>><?php _e('Pending', 'subscriber-notifications'); ?></option>
                    <option value="active_recurring" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'active_recurring'); ?>><?php _e('Active Recurring', 'subscriber-notifications'); ?></option>
                    <option value="sent" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'sent'); ?>><?php _e('Sent', 'subscriber-notifications'); ?></option>
                    <option value="cancelled" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'cancelled'); ?>><?php _e('Cancelled', 'subscriber-notifications'); ?></option>
                </select>
                <input type="submit" class="button" value="<?php _e('Filter', 'subscriber-notifications'); ?>">
            </form>
        </div>
    </div>
    
    <!-- Notifications Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-title"><?php _e('Title', 'subscriber-notifications'); ?></th>
                <th scope="col" class="manage-column column-subject"><?php _e('Subject', 'subscriber-notifications'); ?></th>
                <th scope="col" class="manage-column column-frequency"><?php _e('Frequency', 'subscriber-notifications'); ?></th>
                <th scope="col" class="manage-column column-recurring"><?php _e('Recurring', 'subscriber-notifications'); ?></th>
                <th scope="col" class="manage-column column-categories"><?php _e('Categories', 'subscriber-notifications'); ?></th>
                <th scope="col" class="manage-column column-status"><?php _e('Status', 'subscriber-notifications'); ?></th>
                <th scope="col" class="manage-column column-created"><?php _e('Created', 'subscriber-notifications'); ?></th>
                <th scope="col" class="manage-column column-sent"><?php _e('Sent', 'subscriber-notifications'); ?></th>
                <th scope="col" class="manage-column column-next-send"><?php _e('Next Send', 'subscriber-notifications'); ?></th>
                <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'subscriber-notifications'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($notifications)): ?>
                <tr>
                    <td colspan="10" class="no-items">
                        <?php _e('No notifications found.', 'subscriber-notifications'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <tr>
                        <td class="column-title">
                            <strong><?php echo esc_html($notification->title); ?></strong>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="#" class="view-notification" data-id="<?php echo $notification->id; ?>">
                                        <?php _e('View', 'subscriber-notifications'); ?>
                                    </a>
                                </span>
                                <span class="edit"> | 
                                    <a href="<?php echo admin_url('admin.php?page=subscriber-notifications-edit&id=' . $notification->id); ?>">
                                        <?php _e('Edit', 'subscriber-notifications'); ?>
                                    </a>
                                </span>
                            </div>
                        </td>
                        <td class="column-subject">
                            <?php echo esc_html(wp_unslash($notification->subject)); ?>
                        </td>
                        <td class="column-frequency">
                            <?php 
                            if ($notification->frequency_target) {
                                echo esc_html(ucfirst(str_replace('_', ' ', $notification->frequency_target)));
                            } else {
                                echo '<em>' . __('All', 'subscriber-notifications') . '</em>';
                            }
                            ?>
                        </td>
                        <td class="column-recurring">
                            <?php 
                            if (isset($notification->is_recurring) && $notification->is_recurring) {
                                echo '<span class="recurring-yes">' . __('Yes', 'subscriber-notifications') . '</span>';
                                if (isset($notification->recurrence_count)) {
                                    echo '<br><small>' . sprintf(__('Sent %d times', 'subscriber-notifications'), $notification->recurrence_count) . '</small>';
                                }
                            } else {
                                echo '<span class="recurring-no">' . __('No', 'subscriber-notifications') . '</span>';
                            }
                            ?>
                        </td>
                        <td class="column-categories">
                            <?php
                            $categories = array();
                            if ($notification->news_categories) {
                                $news_cats = explode(',', $notification->news_categories);
                                foreach ($news_cats as $cat_id) {
                                    if ($cat_id) {
                                        $cat = get_category($cat_id);
                                        if ($cat) {
                                            $categories[] = $cat->name;
                                        }
                                    }
                                }
                            }
                            if ($notification->meeting_categories) {
                                $meeting_cats = explode(',', $notification->meeting_categories);
                                foreach ($meeting_cats as $cat_id) {
                                    if ($cat_id) {
                                        $cat = get_term($cat_id, 'tribe_events_cat');
                                        if ($cat) {
                                            $categories[] = $cat->name;
                                        }
                                    }
                                }
                            }
                            echo esc_html(implode(', ', $categories));
                            ?>
                        </td>
                        <td class="column-status">
                            <?php
                            $status_class = '';
                            $status_text = '';
                            
                            // For recurring notifications, check status first
                            if (isset($notification->is_recurring) && $notification->is_recurring) {
                                // If cancelled, show cancelled status regardless of recurrence_count
                                if ($notification->status === 'cancelled') {
                                    $status_class = 'status-cancelled';
                                    $status_text = __('Cancelled', 'subscriber-notifications');
                                } elseif (isset($notification->recurrence_count) && $notification->recurrence_count > 0) {
                                    $status_class = 'status-active';
                                    $status_text = __('Active Recurring', 'subscriber-notifications');
                                } else {
                                    $status_class = 'status-pending';
                                    $status_text = __('Pending', 'subscriber-notifications');
                                }
                            } else {
                                // One-time notifications use actual database status
                                switch ($notification->status) {
                                    case 'pending':
                                        $status_class = 'status-pending';
                                        $status_text = __('Pending', 'subscriber-notifications');
                                        break;
                                    case 'sent':
                                        $status_class = 'status-sent';
                                        $status_text = __('Sent', 'subscriber-notifications');
                                        break;
                                    case 'cancelled':
                                        $status_class = 'status-cancelled';
                                        $status_text = __('Cancelled', 'subscriber-notifications');
                                        break;
                                }
                            }
                            ?>
                            <span class="status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </td>
                        <td class="column-created">
                            <?php echo esc_html(mysql2date('M j, Y g:i A', $notification->created_date)); ?>
                        </td>
                        <td class="column-sent">
                            <?php 
                            // For recurring notifications, show last_sent_date
                            if (isset($notification->is_recurring) && $notification->is_recurring) {
                                if (isset($notification->last_sent_date) && $notification->last_sent_date) {
                                    echo esc_html(mysql2date('M j, Y g:i A', $notification->last_sent_date));
                                } else {
                                    echo '<em>' . __('Not sent yet', 'subscriber-notifications') . '</em>';
                                }
                            } else {
                                // For one-time notifications, show sent_date
                                if ($notification->sent_date) {
                                    echo esc_html(mysql2date('M j, Y g:i A', $notification->sent_date));
                                } else {
                                    echo '<em>' . __('Not sent', 'subscriber-notifications') . '</em>';
                                }
                            }
                            ?>
                        </td>
                        <td class="column-next-send">
                            <?php 
                            // Show N/A for cancelled recurring notifications, or if not recurring, or if no next_send_date
                            if (isset($notification->is_recurring) && $notification->is_recurring && 
                                $notification->status !== 'cancelled' && 
                                isset($notification->next_send_date) && $notification->next_send_date) {
                                $timezone = wp_timezone();
                                $datetime = new DateTime($notification->next_send_date, $timezone);
                                echo esc_html($datetime->format('M j, Y g:i A'));
                            } else {
                                echo '<em>' . __('N/A', 'subscriber-notifications') . '</em>';
                            }
                            ?>
                        </td>
                        <td class="column-actions">
                            <?php if ($notification->status === 'pending'): ?>
                                <form method="post" style="display: inline-block;">
                                    <?php wp_nonce_field('notification_action', 'notification_nonce'); ?>
                                    <input type="hidden" name="notification_id" value="<?php echo $notification->id; ?>">
                                    <input type="hidden" name="notification_action" value="cancel">
                                    <input type="submit" class="button button-small" value="<?php _e('Cancel', 'subscriber-notifications'); ?>" 
                                           onclick="return confirm('<?php _e('Are you sure you want to cancel this notification?', 'subscriber-notifications'); ?>');">
                                </form>
                            <?php elseif ($notification->status === 'sent'): ?>
                                <form method="post" style="display: inline-block;">
                                    <?php wp_nonce_field('notification_action', 'notification_nonce'); ?>
                                    <input type="hidden" name="notification_id" value="<?php echo $notification->id; ?>">
                                    <input type="hidden" name="notification_action" value="resend">
                                    <input type="submit" class="button button-small" value="<?php _e('Resend', 'subscriber-notifications'); ?>" 
                                           onclick="return confirm('<?php _e('Are you sure you want to resend this notification?', 'subscriber-notifications'); ?>');">
                                </form>
                            <?php elseif ($notification->status === 'cancelled'): ?>
                                <form method="post" style="display: inline-block;">
                                    <?php wp_nonce_field('notification_action', 'notification_nonce'); ?>
                                    <input type="hidden" name="notification_id" value="<?php echo $notification->id; ?>">
                                    <input type="hidden" name="notification_action" value="reactivate">
                                    <input type="submit" class="button button-small" value="<?php _e('Reactivate', 'subscriber-notifications'); ?>" 
                                           onclick="return confirm('<?php _e('Are you sure you want to reactivate this notification?', 'subscriber-notifications'); ?>');">
                                </form>
                            <?php endif; ?>
                            
                            <form method="post" style="display: inline-block;">
                                <?php wp_nonce_field('notification_action', 'notification_nonce'); ?>
                                <input type="hidden" name="notification_id" value="<?php echo $notification->id; ?>">
                                <input type="hidden" name="notification_action" value="delete">
                                <input type="submit" class="button button-small button-link-delete" value="<?php _e('Delete', 'subscriber-notifications'); ?>" 
                                       onclick="return confirm('<?php _e('Are you sure you want to delete this notification? This action cannot be undone.', 'subscriber-notifications'); ?>');">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if (isset($total_pages) && $total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                // WordPress core admin pagination pattern
                $current = $page;
                $removable_query_args = wp_removable_query_args();
                $current_url = admin_url('admin.php');
                $current_url = add_query_arg('page', 'subscriber-notifications-notifications', $current_url);
                $current_url = remove_query_arg($removable_query_args, $current_url);
                
                // Preserve filter parameters
                if (!empty($_GET['status'])) {
                    $current_url = add_query_arg('status', sanitize_text_field($_GET['status']), $current_url);
                }
                if (!empty($_GET['s'])) {
                    $current_url = add_query_arg('s', sanitize_text_field($_GET['s']), $current_url);
                }
                
                $output = '<span class="displaying-num">' . sprintf(
                    /* translators: %s: Number of items. */
                    _n('%s item', '%s items', $total_notifications, 'subscriber-notifications'),
                    number_format_i18n($total_notifications)
                ) . '</span>';
                
                $page_links = array();
                
                $disable_first = ($current == 1 || $current == 2);
                $disable_last = ($current == $total_pages || $current == $total_pages - 1);
                $disable_prev = ($current == 1);
                $disable_next = ($current == $total_pages);
                
                if ($disable_first) {
                    $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
                } else {
                    $page_links[] = sprintf(
                        "<a class='first-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                        esc_url(remove_query_arg('paged', $current_url)),
                        __('First page', 'subscriber-notifications'),
                        '&laquo;'
                    );
                }
                
                if ($disable_prev) {
                    $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
                } else {
                    $page_links[] = sprintf(
                        "<a class='prev-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                        esc_url(add_query_arg('paged', max(1, $current - 1), $current_url)),
                        __('Previous page', 'subscriber-notifications'),
                        '&lsaquo;'
                    );
                }
                
                $html_current_page = $current;
                $total_pages_before = '<span class="screen-reader-text">' . __('Current Page', 'subscriber-notifications') . '</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">';
                $total_pages_after = '</span></span>';
                $html_total_pages = sprintf("<span class='total-pages'>%s</span>", number_format_i18n($total_pages));
                $page_links[] = $total_pages_before . sprintf(
                    /* translators: 1: Current page, 2: Total pages. */
                    _x('%1$s of %2$s', 'paging', 'subscriber-notifications'),
                    $html_current_page,
                    $html_total_pages
                ) . $total_pages_after;
                
                if ($disable_next) {
                    $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
                } else {
                    $page_links[] = sprintf(
                        "<a class='next-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                        esc_url(add_query_arg('paged', min($total_pages, $current + 1), $current_url)),
                        __('Next page', 'subscriber-notifications'),
                        '&rsaquo;'
                    );
                }
                
                if ($disable_last) {
                    $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
                } else {
                    $page_links[] = sprintf(
                        "<a class='last-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                        esc_url(add_query_arg('paged', $total_pages, $current_url)),
                        __('Last page', 'subscriber-notifications'),
                        '&raquo;'
                    );
                }
                
                $output .= "\n<span class='pagination-links'>" . join("\n", $page_links) . '</span>';
                
                echo $output;
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Notification Preview Modal -->
<div id="notification-preview-modal" class="notification-modal" style="display: none;">
    <div class="notification-modal-content">
        <div class="notification-modal-header">
            <h2><?php _e('Notification Preview', 'subscriber-notifications'); ?></h2>
            <span class="notification-modal-close">&times;</span>
        </div>
        <div class="notification-modal-body">
            <div id="notification-preview-content"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle view notification
    $('.view-notification').on('click', function(e) {
        e.preventDefault();
        var notificationId = $(this).data('id');
        
        // Get notification data via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'get_notification_preview',
                notification_id: notificationId,
                nonce: '<?php echo wp_create_nonce('get_notification_preview'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#notification-preview-content').html(response.data);
                    $('#notification-preview-modal').show();
                }
            }
        });
    });
    
    // Close modal
    $('.notification-modal-close').on('click', function() {
        $('#notification-preview-modal').hide();
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(e) {
        if (e.target.id === 'notification-preview-modal') {
            $('#notification-preview-modal').hide();
        }
    });
});
</script>

<style>
.notification-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.notification-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #ccd0d4;
    width: 80%;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
}

.notification-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-modal-header h2 {
    margin: 0;
}

.notification-modal-close {
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    color: #666;
}

.notification-modal-close:hover {
    color: #000;
}

.notification-modal-body {
    padding: 20px;
}

.status {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-pending {
    background-color: #fff3cd;
    color: #856404;
}

.status-sent {
    background-color: #d4edda;
    color: #155724;
}

.status-cancelled {
    background-color: #f8d7da;
    color: #721c24;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
}

.recurring-yes {
    color: #155724;
    font-weight: bold;
}

.recurring-no {
    color: #6c757d;
}

.column-actions form {
    display: inline-block;
    margin-right: 5px;
}

.column-actions input[type="submit"] {
    margin-right: 5px;
}
</style>
