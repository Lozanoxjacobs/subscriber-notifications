<?php
/**
 * Notifications admin list table.
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
 * WP_List_Table implementation for the Notifications admin screen.
 */
class SubscriberNotifications_Notifications_List_Table extends WP_List_Table {

    /**
     * Admin instance.
     *
     * @var SubscriberNotifications_Admin
     */
    private $admin;

    /**
     * Constructor.
     *
     * @param SubscriberNotifications_Admin $admin Admin instance.
     */
    public function __construct(SubscriberNotifications_Admin $admin) {
        $this->admin = $admin;

        parent::__construct(
            array(
                'singular' => 'notification',
                'plural'   => 'notifications',
                'ajax'     => false,
                'screen'   => 'subscriber-notifications_page_subscriber-notifications-notifications',
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
            'title'      => __('Title', 'subscriber-notifications'),
            'subject'    => __('Subject', 'subscriber-notifications'),
            'frequency'  => __('Frequency', 'subscriber-notifications'),
            'recurring'  => __('Recurring', 'subscriber-notifications'),
            'targets'    => __('Targets', 'subscriber-notifications'),
            'status'     => __('Status', 'subscriber-notifications'),
            'created'    => __('Created', 'subscriber-notifications'),
            'sent'       => __('Sent', 'subscriber-notifications'),
            'next_send'  => __('Next Send', 'subscriber-notifications'),
            'actions'    => __('Actions', 'subscriber-notifications'),
        );
    }

    /**
     * Sortable columns.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    protected function get_sortable_columns(): array {
        return array(
            'title'   => array('title', false),
            'created' => array('created_date', true),
            'status'  => array('status', false),
        );
    }

    /**
     * Primary column name.
     *
     * @return string
     */
    protected function get_primary_column_name(): string {
        return 'title';
    }

    /**
     * Empty-state message.
     */
    public function no_items(): void {
        esc_html_e('No notifications found.', 'subscriber-notifications');
    }

    /**
     * Load notification rows and pagination metadata.
     */
    public function prepare_items(): void {
        $per_page     = $this->get_items_per_page('subscriber_notifications_notifications_per_page', 20);
        $current_page = $this->get_pagenum();

        $allowed_orderby = array('title', 'created_date', 'status');
        $orderby         = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash($_REQUEST['orderby'])) : 'created_date';
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'created_date';
        }

        $order  = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field(wp_unslash($_REQUEST['order']))) : 'DESC';
        $search = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
        $status = isset($_REQUEST['status']) ? sanitize_text_field(wp_unslash($_REQUEST['status'])) : '';

        $args = array(
            'limit'   => $per_page,
            'offset'  => ($current_page - 1) * $per_page,
            'search'  => $search,
            'status'  => $status,
            'orderby' => $orderby,
            'order'   => $order,
        );

        if (! $this->screen) {
            $this->screen = get_current_screen();
        }

        $this->items = $this->admin->get_notifications($args);

        $total_items = $this->admin->get_notification_count(
            array(
                'search' => $search,
                'status' => $status,
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
     * Render title column with row actions.
     *
     * @param object $item Notification row.
     */
    protected function column_title($item): void {
        $notification_id = (int) $item->id;

        echo '<strong>' . esc_html($item->title) . '</strong>';

        $actions = array(
            'view' => sprintf(
                '<a href="#" class="view-notification" data-id="%1$s">%2$s</a>',
                esc_attr((string) $notification_id),
                esc_html__('View', 'subscriber-notifications')
            ),
            'edit' => sprintf(
                '<a href="%1$s">%2$s</a>',
                esc_url(
                    admin_url(
                        'admin.php?page=subscriber-notifications-edit&id=' . $notification_id
                    )
                ),
                esc_html__('Edit', 'subscriber-notifications')
            ),
        );

        echo $this->row_actions($actions); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Render subject column.
     *
     * @param object $item Notification row.
     */
    protected function column_subject($item): void {
        echo esc_html(wp_unslash($item->subject));
    }

    /**
     * Render frequency column.
     *
     * @param object $item Notification row.
     */
    protected function column_frequency($item): void {
        if ($item->frequency_target) {
            echo esc_html(ucfirst(str_replace('_', ' ', (string) $item->frequency_target)));
            return;
        }

        echo '<em>' . esc_html__('All', 'subscriber-notifications') . '</em>';
    }

    /**
     * Render recurring column.
     *
     * @param object $item Notification row.
     */
    protected function column_recurring($item): void {
        if (isset($item->is_recurring) && $item->is_recurring) {
            echo '<span class="recurring-yes">' . esc_html__('Yes', 'subscriber-notifications') . '</span>';

            if (isset($item->recurrence_count)) {
                echo '<br><small>' . esc_html(
                    sprintf(
                        /* translators: %d: Number of times sent */
                        __('Sent %d times', 'subscriber-notifications'),
                        (int) $item->recurrence_count
                    )
                ) . '</small>';
            }

            return;
        }

        echo '<span class="recurring-no">' . esc_html__('No', 'subscriber-notifications') . '</span>';
    }

    /**
     * Render targets column.
     *
     * @param object $item Notification row.
     */
    protected function column_targets($item): void {
        $targets_summary = SubscriberNotifications_Preferences::human_readable(
            $item->target_preferences ?? ''
        );

        if ($targets_summary === '') {
            echo '<span class="description">' . esc_html__('—', 'subscriber-notifications') . '</span>';
            return;
        }

        echo nl2br(esc_html($targets_summary), false);
    }

    /**
     * Render status column.
     *
     * @param object $item Notification row.
     */
    protected function column_status($item): void {
        $status_display = $this->get_status_display($item);

        printf(
            '<span class="status %1$s">%2$s</span>',
            esc_attr($status_display['class']),
            esc_html($status_display['text'])
        );
    }

    /**
     * Render created column.
     *
     * @param object $item Notification row.
     */
    protected function column_created($item): void {
        echo esc_html(sn_format_log_date_local($item->created_date));
    }

    /**
     * Render sent column.
     *
     * @param object $item Notification row.
     */
    protected function column_sent($item): void {
        if (isset($item->is_recurring) && $item->is_recurring) {
            if (isset($item->last_sent_date) && $item->last_sent_date) {
                echo esc_html(sn_format_log_date_local($item->last_sent_date));
                return;
            }

            echo '<em>' . esc_html__('Not sent yet', 'subscriber-notifications') . '</em>';
            return;
        }

        if ($item->sent_date) {
            echo esc_html(sn_format_log_date_local($item->sent_date));
            return;
        }

        echo '<em>' . esc_html__('Not sent', 'subscriber-notifications') . '</em>';
    }

    /**
     * Render next send column.
     *
     * @param object $item Notification row.
     */
    protected function column_next_send($item): void {
        if ($item->status === 'pending' &&
            isset($item->next_send_date) &&
            $item->next_send_date) {
            $datetime = new DateTimeImmutable($item->next_send_date, wp_timezone());
            echo esc_html($datetime->format('M j, Y g:i A'));
            return;
        }

        echo '<em>' . esc_html__('N/A', 'subscriber-notifications') . '</em>';
    }

    /**
     * Render row action forms.
     *
     * @param object $item Notification row.
     */
    protected function column_actions($item): void {
        $notification_id = (int) $item->id;
        $output          = '';

        if ($item->status === 'pending') {
            $output .= $this->render_action_form(
                $notification_id,
                'cancel',
                __('Cancel', 'subscriber-notifications'),
                'button button-small',
                __('Are you sure you want to cancel this notification?', 'subscriber-notifications')
            );
        } elseif ($item->status === 'sent') {
            $output .= $this->render_action_form(
                $notification_id,
                'resend',
                __('Resend', 'subscriber-notifications'),
                'button button-small',
                __('Are you sure you want to resend this notification?', 'subscriber-notifications')
            );
        } elseif ($item->status === 'cancelled') {
            $output .= $this->render_action_form(
                $notification_id,
                'reactivate',
                __('Reactivate', 'subscriber-notifications'),
                'button button-small',
                __('Are you sure you want to reactivate this notification?', 'subscriber-notifications')
            );
        }

        $output .= $this->render_action_form(
            $notification_id,
            'delete',
            __('Delete', 'subscriber-notifications'),
            'button button-small button-link-delete',
            __('Are you sure you want to delete this notification? This action cannot be undone.', 'subscriber-notifications'),
            true
        );

        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Status filter above the list table.
     *
     * @param string $which Top or bottom table nav.
     */
    protected function extra_tablenav($which): void {
        if ($which !== 'top') {
            return;
        }

        $status = isset($_REQUEST['status']) ? sanitize_text_field(wp_unslash($_REQUEST['status'])) : '';
        ?>
        <div class="alignleft actions">
            <input type="hidden" name="page" value="subscriber-notifications-notifications">
            <label class="screen-reader-text" for="filter-by-status"><?php esc_html_e('Filter by status', 'subscriber-notifications'); ?></label>
            <select name="status" id="filter-by-status">
                <option value=""><?php esc_html_e('All', 'subscriber-notifications'); ?></option>
                <option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Pending', 'subscriber-notifications'); ?></option>
                <option value="active_recurring" <?php selected($status, 'active_recurring'); ?>><?php esc_html_e('Active Recurring', 'subscriber-notifications'); ?></option>
                <option value="sent" <?php selected($status, 'sent'); ?>><?php esc_html_e('Sent', 'subscriber-notifications'); ?></option>
                <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'subscriber-notifications'); ?></option>
            </select>
            <?php submit_button(__('Filter', 'subscriber-notifications'), '', 'filter_action', false); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=subscriber-notifications-notifications')); ?>" class="button"><?php esc_html_e('Clear Filters', 'subscriber-notifications'); ?></a>
        </div>
        <?php
    }

    /**
     * Resolve display class and label for a notification status.
     *
     * @param object $notification Notification row.
     * @return array{class: string, text: string}
     */
    private function get_status_display($notification): array {
        if (isset($notification->is_recurring) && $notification->is_recurring) {
            if ($notification->status === 'cancelled') {
                return array(
                    'class' => 'status-cancelled',
                    'text'  => __('Cancelled', 'subscriber-notifications'),
                );
            }

            if (isset($notification->recurrence_count) && $notification->recurrence_count > 0) {
                return array(
                    'class' => 'status-active',
                    'text'  => __('Active Recurring', 'subscriber-notifications'),
                );
            }

            return array(
                'class' => 'status-pending',
                'text'  => __('Pending', 'subscriber-notifications'),
            );
        }

        switch ($notification->status) {
            case 'pending':
                return array(
                    'class' => 'status-pending',
                    'text'  => __('Pending', 'subscriber-notifications'),
                );
            case 'sent':
                return array(
                    'class' => 'status-sent',
                    'text'  => __('Sent', 'subscriber-notifications'),
                );
            case 'cancelled':
                return array(
                    'class' => 'status-cancelled',
                    'text'  => __('Cancelled', 'subscriber-notifications'),
                );
            default:
                return array(
                    'class' => '',
                    'text'  => (string) $notification->status,
                );
        }
    }

    /**
     * Build a POST action form for a notification row.
     *
     * @param int    $notification_id Notification ID.
     * @param string $action          Action slug.
     * @param string $label           Button label.
     * @param string $button_class    CSS classes for the submit button.
     * @param string $confirm_message Browser confirm dialog message.
     * @param bool   $is_delete       Whether this is the delete action (adds left margin).
     * @return string
     */
    private function render_action_form(
        int $notification_id,
        string $action,
        string $label,
        string $button_class,
        string $confirm_message,
        bool $is_delete = false
    ): string {
        $confirm_attr = ' onclick="return confirm(\'' . esc_js($confirm_message) . '\');"';
        $style        = $is_delete ? ' style="display:inline-block;margin-left:5px;"' : ' style="display:inline-block;"';

        ob_start();
        ?>
        <form method="post"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
            <?php wp_nonce_field('notification_action', 'notification_nonce'); ?>
            <input type="hidden" name="notification_id" value="<?php echo esc_attr((string) $notification_id); ?>">
            <input type="hidden" name="notification_action" value="<?php echo esc_attr($action); ?>">
            <button type="submit" class="<?php echo esc_attr($button_class); ?>"<?php echo $confirm_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <?php echo esc_html($label); ?>
            </button>
        </form>
        <?php
        return (string) ob_get_clean();
    }
}
