<?php
if (!defined('ABSPATH')) {
    exit;
}

$sn_logs_filter_args = array();
if (!empty($_GET['status'])) {
    $sn_logs_filter_args['status'] = sanitize_text_field(wp_unslash($_GET['status']));
}
if (!empty($_GET['email_type'])) {
    $sn_logs_filter_args['email_type'] = sanitize_key(wp_unslash($_GET['email_type']));
}
if (!empty($_GET['date_from'])) {
    $sn_logs_filter_args['date_from'] = sanitize_text_field(wp_unslash($_GET['date_from']));
}
if (!empty($_GET['date_to'])) {
    $sn_logs_filter_args['date_to'] = sanitize_text_field(wp_unslash($_GET['date_to']));
}
if (!empty($_GET['subscriber_id'])) {
    $sn_logs_filter_args['subscriber_id'] = intval($_GET['subscriber_id']);
}

$sn_logs_export_url = admin_url('admin.php?page=subscriber-notifications-logs&action=export');
foreach ($sn_logs_filter_args as $key => $value) {
    $sn_logs_export_url = add_query_arg($key, $value, $sn_logs_export_url);
}
$sn_logs_export_url = wp_nonce_url($sn_logs_export_url, 'export_logs');

$sn_current_email_type = isset($_GET['email_type']) ? sanitize_key(wp_unslash($_GET['email_type'])) : '';
$sn_email_log_types    = sn_get_email_log_types();
if (!isset($sn_purge_presets)) {
    $sn_purge_presets = array(30, 90, 180, 365);
}
if (!isset($sn_purge_counts)) {
    $sn_purge_counts = array();
    foreach ($sn_purge_presets as $purge_days) {
        $sn_purge_counts[ $purge_days ] = 0;
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Email Logs', 'subscriber-notifications'); ?></h1>
    <a href="<?php echo esc_url($sn_logs_export_url); ?>" class="page-title-action">
        <?php esc_html_e('Export Logs', 'subscriber-notifications'); ?>
    </a>
    <hr class="wp-header-end">

    <div class="logs-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="subscriber-notifications-logs">

            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', 'subscriber-notifications'); ?></option>
                <option value="sent" <?php selected($sn_logs_filter_args['status'] ?? '', 'sent'); ?>><?php esc_html_e('Sent', 'subscriber-notifications'); ?></option>
                <option value="failed" <?php selected($sn_logs_filter_args['status'] ?? '', 'failed'); ?>><?php esc_html_e('Failed', 'subscriber-notifications'); ?></option>
                <option value="pending" <?php selected($sn_logs_filter_args['status'] ?? '', 'pending'); ?>><?php esc_html_e('Pending', 'subscriber-notifications'); ?></option>
            </select>

            <select name="email_type">
                <option value=""><?php esc_html_e('All Email Types', 'subscriber-notifications'); ?></option>
                <?php foreach ($sn_email_log_types as $type_slug => $type_label) : ?>
                    <option value="<?php echo esc_attr($type_slug); ?>" <?php selected($sn_current_email_type, $type_slug); ?>>
                        <?php echo esc_html($type_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date_from" value="<?php echo esc_attr($sn_logs_filter_args['date_from'] ?? ''); ?>" placeholder="<?php esc_attr_e('From Date', 'subscriber-notifications'); ?>">
            <input type="date" name="date_to" value="<?php echo esc_attr($sn_logs_filter_args['date_to'] ?? ''); ?>" placeholder="<?php esc_attr_e('To Date', 'subscriber-notifications'); ?>">

            <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'subscriber-notifications'); ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=subscriber-notifications-logs')); ?>" class="button"><?php esc_html_e('Clear Filters', 'subscriber-notifications'); ?></a>
        </form>
    </div>

    <div class="logs-maintenance">
        <h2 class="screen-reader-text"><?php esc_html_e('Log maintenance', 'subscriber-notifications'); ?></h2>
        <form id="sn-purge-logs-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="subscriber_notifications_purge_logs">
            <?php wp_nonce_field('sn_purge_logs', 'sn_purge_logs_nonce'); ?>
            <label for="sn-purge-days"><?php esc_html_e('Purge logs older than', 'subscriber-notifications'); ?></label>
            <select name="purge_days" id="sn-purge-days">
                <?php foreach ($sn_purge_presets as $days) : ?>
                    <option value="<?php echo esc_attr((string) $days); ?>" data-match-count="<?php echo esc_attr((string) (int) ($sn_purge_counts[ $days ] ?? 0)); ?>">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: number of days, 2: matching log count */
                                _n('%1$d day (%2$d match)', '%1$d days (%2$d match)', $days, 'subscriber-notifications'),
                                $days,
                                (int) ($sn_purge_counts[ $days ] ?? 0)
                            )
                        );
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span id="sn-purge-match-summary" class="description" aria-live="polite"></span>
            <button type="submit" class="button"><?php esc_html_e('Purge', 'subscriber-notifications'); ?></button>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Subscriber', 'subscriber-notifications'); ?></th>
                <th scope="col"><?php esc_html_e('Email Type', 'subscriber-notifications'); ?></th>
                <th scope="col"><?php esc_html_e('Status', 'subscriber-notifications'); ?></th>
                <th scope="col"><?php esc_html_e('Sent Date', 'subscriber-notifications'); ?></th>
                <th scope="col"><?php esc_html_e('Opens', 'subscriber-notifications'); ?></th>
                <th scope="col"><?php esc_html_e('Clicks', 'subscriber-notifications'); ?></th>
                <th scope="col"><?php esc_html_e('Last Opened', 'subscriber-notifications'); ?></th>
                <th scope="col"><?php esc_html_e('Last Clicked', 'subscriber-notifications'); ?></th>
                <th scope="col"><?php esc_html_e('Error Message', 'subscriber-notifications'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)) : ?>
            <tr>
                <td colspan="9" style="text-align: center; padding: 20px;">
                    <?php esc_html_e('No logs found.', 'subscriber-notifications'); ?>
                </td>
            </tr>
            <?php else : ?>
                <?php foreach ($logs as $log) : ?>
                <tr>
                    <td>
                        <?php if (empty($log->email) && !empty($log->subscriber_id)) : ?>
                            <em class="subscriber-deleted"><?php esc_html_e('Subscriber Deleted', 'subscriber-notifications'); ?></em><br>
                            <small><?php printf(esc_html__('ID: %d', 'subscriber-notifications'), intval($log->subscriber_id)); ?></small>
                        <?php elseif ($log->name) : ?>
                            <strong><?php echo esc_html($log->name); ?></strong><br>
                            <small><?php echo esc_html($log->email); ?></small>
                        <?php else : ?>
                            <?php echo esc_html($log->email); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html(sn_format_email_log_type($log->email_type)); ?></td>
                    <td>
                        <span class="status-<?php echo esc_attr($log->status); ?>">
                            <?php echo esc_html(ucfirst($log->status)); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(sn_format_log_date_utc($log->sent_date)); ?></td>
                    <td><?php echo esc_html($log->open_count); ?></td>
                    <td><?php echo esc_html($log->click_count); ?></td>
                    <td><?php echo esc_html(sn_format_log_date_local($log->last_opened)); ?></td>
                    <td><?php echo esc_html(sn_format_log_date_local($log->last_clicked)); ?></td>
                    <td>
                        <?php if ($log->error_message) : ?>
                            <span class="error-message" title="<?php echo esc_attr($log->error_message); ?>">
                                <?php echo esc_html(wp_trim_words($log->error_message, 10)); ?>
                            </span>
                        <?php else : ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if (isset($total_pages) && $total_pages > 1) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            $current = $page;
            $removable_query_args = wp_removable_query_args();
            $current_url = admin_url('admin.php');
            $current_url = add_query_arg('page', 'subscriber-notifications-logs', $current_url);
            $current_url = remove_query_arg($removable_query_args, $current_url);

            foreach ($sn_logs_filter_args as $key => $value) {
                $current_url = add_query_arg($key, $value, $current_url);
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
                    esc_html__('First page', 'subscriber-notifications'),
                    '&laquo;'
                );
            }

            if ($disable_prev) {
                $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
            } else {
                $page_links[] = sprintf(
                    "<a class='prev-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                    esc_url(add_query_arg('paged', max(1, $current - 1), $current_url)),
                    esc_html__('Previous page', 'subscriber-notifications'),
                    '&lsaquo;'
                );
            }

            $html_current_page = $current;
            $total_pages_before = '<span class="screen-reader-text">' . esc_html__('Current Page', 'subscriber-notifications') . '</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">';
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
                    esc_html__('Next page', 'subscriber-notifications'),
                    '&rsaquo;'
                );
            }

            if ($disable_last) {
                $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
            } else {
                $page_links[] = sprintf(
                    "<a class='last-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                    esc_url(add_query_arg('paged', $total_pages, $current_url)),
                    esc_html__('Last page', 'subscriber-notifications'),
                    '&raquo;'
                );
            }

            $output .= "
<span class='pagination-links'>" . join("
", $page_links) . '</span>';

            echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped fragments above
            ?>
        </div>
    </div>
    <?php endif; ?>

</div>
