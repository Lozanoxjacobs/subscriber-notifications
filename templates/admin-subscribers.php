<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Format date from UTC (CURRENT_TIMESTAMP) - converts to site timezone
 * 
 * @param string|null $date_value Date value from database (UTC)
 * @return string Formatted date or '-' if empty/invalid
 */
function format_date_utc($date_value) {
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
?>

<div class="wrap">
    <h1><?php _e('Subscribers', 'subscriber-notifications'); ?></h1>
    
    <div class="subscriber-notifications-subscribers">
        <div class="subscribers-header">
            <div class="subscribers-actions">
                <a href="<?php echo admin_url('admin.php?page=subscriber-notifications-import-export'); ?>" class="button">
                    <?php _e('Import/Export CSV', 'subscriber-notifications'); ?>
                </a>
            </div>
            
            <div class="subscribers-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="subscriber-notifications-subscribers">
                    <input type="search" name="s" value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>" placeholder="<?php _e('Search subscribers...', 'subscriber-notifications'); ?>">
                    <select name="status">
                        <option value=""><?php _e('All Statuses', 'subscriber-notifications'); ?></option>
                        <option value="active" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'active'); ?>><?php _e('Subscribed', 'subscriber-notifications'); ?></option>
                        <option value="inactive" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', 'inactive'); ?>><?php _e('Unsubscribed', 'subscriber-notifications'); ?></option>
                    </select>
                    <input type="submit" class="button" value="<?php _e('Filter', 'subscriber-notifications'); ?>">
                </form>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Name', 'subscriber-notifications'); ?></th>
                    <th><?php _e('Email', 'subscriber-notifications'); ?></th>
                    <th><?php _e('News Categories', 'subscriber-notifications'); ?></th>
                    <th><?php _e('Meeting Categories', 'subscriber-notifications'); ?></th>
                    <th><?php _e('Frequency', 'subscriber-notifications'); ?></th>
                    <th><?php _e('Status', 'subscriber-notifications'); ?></th>
                    <th><?php _e('Date Added', 'subscriber-notifications'); ?></th>
                    <th><?php _e('Actions', 'subscriber-notifications'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscribers)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px;">
                        <?php _e('No subscribers found.', 'subscriber-notifications'); ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($subscribers as $subscriber): ?>
                    <tr>
                        <td><?php echo esc_html($subscriber->name); ?></td>
                        <td><?php echo esc_html($subscriber->email); ?></td>
                        <td>
                            <?php 
                            $news_cats = explode(',', $subscriber->news_categories);
                            $news_cat_names = array();
                            foreach ($news_cats as $cat_id) {
                                if ($cat_id) {
                                    $cat = get_category($cat_id);
                                    if ($cat) {
                                        $news_cat_names[] = $cat->name;
                                    }
                                }
                            }
                            echo esc_html(implode(', ', $news_cat_names));
                            ?>
                        </td>
                        <td>
                            <?php 
                            $meeting_cats = explode(',', $subscriber->meeting_categories);
                            $meeting_cat_names = array();
                            foreach ($meeting_cats as $cat_id) {
                                if ($cat_id) {
                                    $cat = get_term($cat_id, 'tribe_events_cat');
                                    if ($cat) {
                                        $meeting_cat_names[] = $cat->name;
                                    }
                                }
                            }
                            echo esc_html(implode(', ', $meeting_cat_names));
                            ?>
                        </td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $subscriber->frequency))); ?></td>
                        <td>
                            <span class="status-<?php echo esc_attr($subscriber->status); ?>">
                                <?php 
                                $status_text = ($subscriber->status === 'active') ? 'Subscribed' : 'Unsubscribed';
                                echo esc_html($status_text); 
                                ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(format_date_utc($subscriber->date_added)); ?></td>
                        <td>
                            <?php if ($subscriber->status === 'active'): ?>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('subscriber_action', 'subscriber_nonce'); ?>
                                    <input type="hidden" name="subscriber_id" value="<?php echo esc_attr($subscriber->id); ?>">
                                    <input type="hidden" name="action" value="unsubscribe">
                                    <input type="submit" class="button button-small" value="<?php _e('Unsubscribe', 'subscriber-notifications'); ?>">
                                </form>
                            <?php elseif ($subscriber->status === 'inactive'): ?>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('subscriber_action', 'subscriber_nonce'); ?>
                                    <input type="hidden" name="subscriber_id" value="<?php echo esc_attr($subscriber->id); ?>">
                                    <input type="hidden" name="action" value="subscribe">
                                    <input type="submit" class="button button-small" value="<?php _e('Subscribe', 'subscriber-notifications'); ?>">
                                </form>
                            <?php endif; ?>
                            
                            <form method="post" style="display: inline; margin-left: 5px;">
                                <?php wp_nonce_field('subscriber_action', 'subscriber_nonce'); ?>
                                <input type="hidden" name="subscriber_id" value="<?php echo esc_attr($subscriber->id); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="submit" class="button button-small button-link-delete" value="<?php _e('Delete', 'subscriber-notifications'); ?>" onclick="return confirm('<?php _e('Are you sure you want to delete this subscriber?', 'subscriber-notifications'); ?>')">
                            </form>
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
                $current_url = add_query_arg('page', 'subscriber-notifications-subscribers', $current_url);
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
                    _n('%s item', '%s items', $total_subscribers, 'subscriber-notifications'),
                    number_format_i18n($total_subscribers)
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
</div>

<style>
.subscriber-notifications-subscribers .subscribers-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
}

.subscriber-notifications-subscribers .subscribers-actions .button {
    margin-right: 10px;
}

.subscriber-notifications-subscribers .subscribers-filters form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.subscriber-notifications-subscribers .subscribers-filters input[type="search"] {
    width: 200px;
}

.subscriber-notifications-subscribers .status-active {
    color: #46b450;
    font-weight: bold;
}

.subscriber-notifications-subscribers .status-inactive {
    color: #dc3232;
    font-weight: bold;
}

.subscriber-notifications-subscribers .subscribers-pagination {
    margin-top: 20px;
    text-align: center;
}
</style>
