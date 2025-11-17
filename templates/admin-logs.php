<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Format log date from UTC (CURRENT_TIMESTAMP) - converts to site timezone
 * 
 * @param string|null $date_value Date value from database (UTC)
 * @return string Formatted date or '-' if empty/invalid
 */
function format_log_date_utc($date_value) {
    if (empty($date_value)) {
        return '-';
    }
    
    try {
        // Create DateTime assuming the date string is in UTC
        $utc_timezone = new DateTimeZone('UTC');
        $site_timezone = wp_timezone();
        $datetime = new DateTime($date_value, $utc_timezone);
        // Convert to site timezone
        $datetime->setTimezone($site_timezone);
        return $datetime->format('M j, Y g:i A');
    } catch (Exception $e) {
        // Fallback to mysql2date
        return mysql2date('M j, Y g:i A', $date_value) ?: '-';
    }
}

/**
 * Format log date already in site timezone (current_time('mysql')) - no conversion needed
 * 
 * @param string|null $date_value Date value from database (already in site timezone)
 * @return string Formatted date or '-' if empty/invalid
 */
function format_log_date_local($date_value) {
    if (empty($date_value)) {
        return '-';
    }
    // Date is already in WordPress site timezone, just format it
    try {
        $timezone = wp_timezone();
        $datetime = new DateTime($date_value, $timezone);
        return $datetime->format('M j, Y g:i A');
    } catch (Exception $e) {
        return date('M j, Y g:i A', strtotime($date_value)) ?: '-';
    }
}
?>

<div class="wrap">
    <h1><?php _e('Email Logs', 'subscriber-notifications'); ?></h1>
    
    <div class="logs-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="subscriber-notifications-logs">
            
            <select name="status">
                <option value=""><?php _e('All Statuses', 'subscriber-notifications'); ?></option>
                <option value="sent" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'sent'); ?>><?php _e('Sent', 'subscriber-notifications'); ?></option>
                <option value="failed" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'failed'); ?>><?php _e('Failed', 'subscriber-notifications'); ?></option>
                <option value="pending" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'pending'); ?>><?php _e('Pending', 'subscriber-notifications'); ?></option>
            </select>
            
            <input type="date" name="date_from" value="<?php echo esc_attr(isset($_GET['date_from']) ? $_GET['date_from'] : ''); ?>" placeholder="<?php _e('From Date', 'subscriber-notifications'); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr(isset($_GET['date_to']) ? $_GET['date_to'] : ''); ?>" placeholder="<?php _e('To Date', 'subscriber-notifications'); ?>">
            
            <input type="submit" class="button" value="<?php _e('Filter', 'subscriber-notifications'); ?>">
            <a href="<?php echo admin_url('admin.php?page=subscriber-notifications-logs'); ?>" class="button"><?php _e('Clear Filters', 'subscriber-notifications'); ?></a>
        </form>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Subscriber', 'subscriber-notifications'); ?></th>
                <th><?php _e('Email Type', 'subscriber-notifications'); ?></th>
                <th><?php _e('Status', 'subscriber-notifications'); ?></th>
                <th><?php _e('Sent Date', 'subscriber-notifications'); ?></th>
                <th><?php _e('Opens', 'subscriber-notifications'); ?></th>
                <th><?php _e('Clicks', 'subscriber-notifications'); ?></th>
                <th><?php _e('Last Opened', 'subscriber-notifications'); ?></th>
                <th><?php _e('Last Clicked', 'subscriber-notifications'); ?></th>
                <th><?php _e('Error Message', 'subscriber-notifications'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="9" style="text-align: center; padding: 20px;">
                    <?php _e('No logs found.', 'subscriber-notifications'); ?>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td>
                        <?php if (empty($log->email) && !empty($log->subscriber_id)): ?>
                            <em class="subscriber-deleted"><?php _e('Subscriber Deleted', 'subscriber-notifications'); ?></em><br>
                            <small><?php printf(__('ID: %d', 'subscriber-notifications'), intval($log->subscriber_id)); ?></small>
                        <?php elseif ($log->name): ?>
                            <strong><?php echo esc_html($log->name); ?></strong><br>
                            <small><?php echo esc_html($log->email); ?></small>
                        <?php else: ?>
                            <?php echo esc_html($log->email); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(ucfirst($log->email_type)); ?></td>
                    <td>
                        <span class="status-<?php echo esc_attr($log->status); ?>">
                            <?php echo esc_html(ucfirst($log->status)); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(format_log_date_utc($log->sent_date)); ?></td>
                    <td><?php echo esc_html($log->open_count); ?></td>
                    <td><?php echo esc_html($log->click_count); ?></td>
                    <td><?php echo esc_html(format_log_date_local($log->last_opened)); ?></td>
                    <td><?php echo esc_html(format_log_date_local($log->last_clicked)); ?></td>
                    <td>
                        <?php if ($log->error_message): ?>
                            <span class="error-message" title="<?php echo esc_attr($log->error_message); ?>">
                                <?php echo esc_html(wp_trim_words($log->error_message, 10)); ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if (isset($total_pages) && $total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            // WordPress core admin pagination pattern
            $current = $page;
            $removable_query_args = wp_removable_query_args();
            $current_url = admin_url('admin.php');
            $current_url = add_query_arg('page', 'subscriber-notifications-logs', $current_url);
            $current_url = remove_query_arg($removable_query_args, $current_url);
            
            // Preserve filter parameters
            if (!empty($_GET['status'])) {
                $current_url = add_query_arg('status', sanitize_text_field($_GET['status']), $current_url);
            }
            if (!empty($_GET['date_from'])) {
                $current_url = add_query_arg('date_from', sanitize_text_field($_GET['date_from']), $current_url);
            }
            if (!empty($_GET['date_to'])) {
                $current_url = add_query_arg('date_to', sanitize_text_field($_GET['date_to']), $current_url);
            }
            if (!empty($_GET['subscriber_id'])) {
                $current_url = add_query_arg('subscriber_id', intval($_GET['subscriber_id']), $current_url);
            }
            
            $output = '<span class="displaying-num">' . sprintf(
                /* translators: %s: Number of items. */
                _n('%s item', '%s items', $total_logs, 'subscriber-notifications'),
                number_format_i18n($total_logs)
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
    
    <div class="logs-actions">
        <?php
        // Build export URL with filters preserved
        $export_url = admin_url('admin.php?page=subscriber-notifications-logs&action=export');
        
        // Preserve filter parameters
        if (!empty($_GET['status'])) {
            $export_url = add_query_arg('status', sanitize_text_field($_GET['status']), $export_url);
        }
        if (!empty($_GET['date_from'])) {
            $export_url = add_query_arg('date_from', sanitize_text_field($_GET['date_from']), $export_url);
        }
        if (!empty($_GET['date_to'])) {
            $export_url = add_query_arg('date_to', sanitize_text_field($_GET['date_to']), $export_url);
        }
        if (!empty($_GET['subscriber_id'])) {
            $export_url = add_query_arg('subscriber_id', intval($_GET['subscriber_id']), $export_url);
        }
        
        // Add nonce for security
        $export_url = wp_nonce_url($export_url, 'export_logs');
        ?>
        <a href="<?php echo esc_url($export_url); ?>" class="button">
            <?php _e('Export Logs', 'subscriber-notifications'); ?>
        </a>
    </div>
</div>

<style>
.logs-filters {
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.logs-filters form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.logs-filters select,
.logs-filters input[type="date"] {
    padding: 5px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.logs-filters select {
    min-width: 150px;
    padding-right: 30px;
}

.status-sent {
    color: #46b450;
    font-weight: bold;
}

.status-failed {
    color: #dc3232;
    font-weight: bold;
}

.status-pending {
    color: #ffb900;
    font-weight: bold;
}

.error-message {
    color: #dc3232;
    cursor: help;
}

.subscriber-deleted {
    color: #999;
    font-style: italic;
}

.logs-actions {
    margin-top: 20px;
}
</style>
