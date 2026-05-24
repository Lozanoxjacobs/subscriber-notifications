<?php
/**
 * Email logs admin list table.
 *
 * @package SubscriberNotifications
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table implementation for the Email Logs admin screen.
 */
class SubscriberNotifications_Logs_List_Table extends WP_List_Table {

    /**
     * Database instance.
     *
     * @var SubscriberNotifications_Database
     */
    private $database;

    /**
     * Constructor.
     *
     * @param SubscriberNotifications_Database $database Database instance.
     */
    public function __construct(SubscriberNotifications_Database $database) {
        $this->database = $database;

        parent::__construct(
            array(
                'singular' => 'log',
                'plural'   => 'logs',
                'ajax'     => false,
                'screen'   => 'subscriber-notifications_page_subscriber-notifications-logs',
            )
        );
    }

    /**
     * Define list table columns.
     *
     * @return array<string, string>
     */
    public function get_columns(): array {
        return array(
            'subscriber'    => __('Subscriber', 'subscriber-notifications'),
            'email_type'    => __('Email Type', 'subscriber-notifications'),
            'status'        => __('Status', 'subscriber-notifications'),
            'sent_date'     => __('Sent Date', 'subscriber-notifications'),
            'open_count'    => __('Opens', 'subscriber-notifications'),
            'click_count'   => __('Clicks', 'subscriber-notifications'),
            'last_opened'   => __('Last Opened', 'subscriber-notifications'),
            'last_clicked'  => __('Last Clicked', 'subscriber-notifications'),
            'error_message' => __('Error Message', 'subscriber-notifications'),
        );
    }

    /**
     * Sortable columns.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    protected function get_sortable_columns(): array {
        return array(
            'sent_date' => array('sent_date', true),
        );
    }

    /**
     * Primary column name.
     *
     * @return string
     */
    protected function get_primary_column_name(): string {
        return 'subscriber';
    }

    /**
     * Empty-state message.
     */
    public function no_items(): void {
        esc_html_e('No logs found.', 'subscriber-notifications');
    }

    /**
     * Load log rows and pagination metadata.
     */
    public function prepare_items(): void {
        $per_page     = $this->get_items_per_page('subscriber_notifications_logs_per_page', 20);
        $current_page = $this->get_pagenum();

        $orderby = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash($_REQUEST['orderby'])) : 'sent_date';
        $order   = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field(wp_unslash($_REQUEST['order']))) : 'DESC';
        $status  = isset($_REQUEST['status']) ? sanitize_text_field(wp_unslash($_REQUEST['status'])) : '';
        $email_type = isset($_REQUEST['email_type']) ? sanitize_key(wp_unslash($_REQUEST['email_type'])) : '';
        $date_from  = isset($_REQUEST['date_from']) ? sanitize_text_field(wp_unslash($_REQUEST['date_from'])) : '';
        $date_to    = isset($_REQUEST['date_to']) ? sanitize_text_field(wp_unslash($_REQUEST['date_to'])) : '';
        $subscriber_id = isset($_REQUEST['subscriber_id']) ? intval($_REQUEST['subscriber_id']) : 0;

        $args = array(
            'limit'         => $per_page,
            'offset'        => ($current_page - 1) * $per_page,
            'subscriber_id' => $subscriber_id,
            'status'        => $status,
            'email_type'    => $email_type,
            'date_from'     => $date_from,
            'date_to'       => $date_to,
            'orderby'       => $orderby,
            'order'         => $order,
        );

        if ( ! $this->screen ) {
            $this->screen = get_current_screen();
        }

        $this->items = $this->database->get_logs($args);

        $total_items = $this->database->get_logs_count(
            array(
                'subscriber_id' => $subscriber_id,
                'status'        => $status,
                'email_type'    => $email_type,
                'date_from'     => $date_from,
                'date_to'       => $date_to,
            )
        );

        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => (int) ceil($total_items / $per_page),
            )
        );

        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
            $this->get_primary_column_name(),
        );
    }

    /**
     * Status, email type, and date filters above the list table.
     *
     * @param string $which Top or bottom table nav.
     */
    protected function extra_tablenav($which): void {
        if ($which !== 'top') {
            return;
        }

        $status        = isset($_REQUEST['status']) ? sanitize_text_field(wp_unslash($_REQUEST['status'])) : '';
        $email_type    = isset($_REQUEST['email_type']) ? sanitize_key(wp_unslash($_REQUEST['email_type'])) : '';
        $date_from     = isset($_REQUEST['date_from']) ? sanitize_text_field(wp_unslash($_REQUEST['date_from'])) : '';
        $date_to       = isset($_REQUEST['date_to']) ? sanitize_text_field(wp_unslash($_REQUEST['date_to'])) : '';
        $subscriber_id = isset($_REQUEST['subscriber_id']) ? intval($_REQUEST['subscriber_id']) : 0;
        $email_types   = sn_get_email_log_types();
        ?>
        <div class="alignleft actions sn-logs-filter-actions">
            <?php if ($subscriber_id > 0) : ?>
                <input type="hidden" name="subscriber_id" value="<?php echo esc_attr((string) $subscriber_id); ?>">
            <?php endif; ?>
            <label class="screen-reader-text" for="filter-by-status"><?php esc_html_e('Filter by status', 'subscriber-notifications'); ?></label>
            <select name="status" id="filter-by-status">
                <option value=""><?php esc_html_e('All Statuses', 'subscriber-notifications'); ?></option>
                <option value="sent" <?php selected($status, 'sent'); ?>><?php esc_html_e('Sent', 'subscriber-notifications'); ?></option>
                <option value="failed" <?php selected($status, 'failed'); ?>><?php esc_html_e('Failed', 'subscriber-notifications'); ?></option>
                <option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Pending', 'subscriber-notifications'); ?></option>
            </select>
            <label class="screen-reader-text" for="filter-by-email-type"><?php esc_html_e('Filter by email type', 'subscriber-notifications'); ?></label>
            <select name="email_type" id="filter-by-email-type">
                <option value=""><?php esc_html_e('All Email Types', 'subscriber-notifications'); ?></option>
                <?php foreach ($email_types as $type_slug => $type_label) : ?>
                    <option value="<?php echo esc_attr($type_slug); ?>" <?php selected($email_type, $type_slug); ?>>
                        <?php echo esc_html($type_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label class="screen-reader-text" for="filter-date-from"><?php esc_html_e('Filter from date', 'subscriber-notifications'); ?></label>
            <input type="date" name="date_from" id="filter-date-from" value="<?php echo esc_attr($date_from); ?>">
            <label class="screen-reader-text" for="filter-date-to"><?php esc_html_e('Filter to date', 'subscriber-notifications'); ?></label>
            <input type="date" name="date_to" id="filter-date-to" value="<?php echo esc_attr($date_to); ?>">
            <?php submit_button(__('Filter', 'subscriber-notifications'), '', 'filter_action', false); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=subscriber-notifications-logs')); ?>" class="button"><?php esc_html_e('Clear Filters', 'subscriber-notifications'); ?></a>
        </div>
        <?php
    }

    /**
     * Render subscriber column.
     *
     * @param object $item Log row.
     */
    protected function column_subscriber($item): void {
        if (empty($item->email) && !empty($item->subscriber_id)) {
            echo '<em class="subscriber-deleted">' . esc_html__('Subscriber Deleted', 'subscriber-notifications') . '</em><br>';
            echo '<small>' . esc_html(
                sprintf(
                    /* translators: %d: subscriber ID */
                    __('ID: %d', 'subscriber-notifications'),
                    (int) $item->subscriber_id
                )
            ) . '</small>';
            return;
        }

        if (!empty($item->name)) {
            echo '<strong>' . esc_html($item->name) . '</strong><br>';
            echo '<small>' . esc_html($item->email) . '</small>';
            return;
        }

        echo esc_html($item->email);
    }

    /**
     * Render email type column.
     *
     * @param object $item Log row.
     */
    protected function column_email_type($item): void {
        echo esc_html(sn_format_email_log_type($item->email_type));
    }

    /**
     * Render status column.
     *
     * @param object $item Log row.
     */
    protected function column_status($item): void {
        printf(
            '<span class="status-%1$s">%2$s</span>',
            esc_attr($item->status),
            esc_html(ucfirst((string) $item->status))
        );
    }

    /**
     * Render sent date column.
     *
     * @param object $item Log row.
     */
    protected function column_sent_date($item): void {
        echo esc_html(sn_format_log_date_local($item->sent_date));
    }

    /**
     * Render open count column.
     *
     * @param object $item Log row.
     */
    protected function column_open_count($item): void {
        echo esc_html((string) $item->open_count);
    }

    /**
     * Render click count column.
     *
     * @param object $item Log row.
     */
    protected function column_click_count($item): void {
        echo esc_html((string) $item->click_count);
    }

    /**
     * Render last opened column.
     *
     * @param object $item Log row.
     */
    protected function column_last_opened($item): void {
        echo esc_html(sn_format_log_date_local($item->last_opened));
    }

    /**
     * Render last clicked column.
     *
     * @param object $item Log row.
     */
    protected function column_last_clicked($item): void {
        echo esc_html(sn_format_log_date_local($item->last_clicked));
    }

    /**
     * Render error message column.
     *
     * @param object $item Log row.
     */
    protected function column_error_message($item): void {
        if (empty($item->error_message)) {
            echo '-';
            return;
        }

        printf(
            '<span class="error-message" title="%1$s">%2$s</span>',
            esc_attr($item->error_message),
            esc_html(wp_trim_words($item->error_message, 10))
        );
    }
}
