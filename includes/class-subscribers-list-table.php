<?php
/**
 * Subscribers admin list table.
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
 * WP_List_Table implementation for the Subscribers admin screen.
 */
class SubscriberNotifications_Subscribers_List_Table extends WP_List_Table {

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
                'singular' => 'subscriber',
                'plural'   => 'subscribers',
                'ajax'     => false,
                'screen'   => 'subscriber-notifications_page_subscriber-notifications-subscribers',
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
            'name'          => __('Name', 'subscriber-notifications'),
            'email'         => __('Email', 'subscriber-notifications'),
            'wp_user'       => __('WP User', 'subscriber-notifications'),
            'subscriptions' => __('Subscriptions', 'subscriber-notifications'),
            'frequency'     => __('Frequency', 'subscriber-notifications'),
            'status'        => __('Status', 'subscriber-notifications'),
            'date_added'    => __('Date Added', 'subscriber-notifications'),
            'actions'       => __('Actions', 'subscriber-notifications'),
        );
    }

    /**
     * Sortable columns.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    protected function get_sortable_columns(): array {
        return array(
            'name'       => array('name', false),
            'email'      => array('email', false),
            'status'     => array('status', false),
            'date_added' => array('date_added', true),
        );
    }

    /**
     * Primary column name.
     *
     * @return string
     */
    protected function get_primary_column_name(): string {
        return 'name';
    }

    /**
     * Empty-state message.
     */
    public function no_items(): void {
        esc_html_e('No subscribers found.', 'subscriber-notifications');
    }

    /**
     * Load subscriber rows and pagination metadata.
     */
    public function prepare_items(): void {
        $per_page     = $this->get_items_per_page('subscriber_notifications_subscribers_per_page', 20);
        $current_page = $this->get_pagenum();

        $orderby = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash($_REQUEST['orderby'])) : 'date_added';
        $order   = isset($_REQUEST['order']) ? strtoupper(sanitize_text_field(wp_unslash($_REQUEST['order']))) : 'DESC';
        $search  = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
        $status  = isset($_REQUEST['status']) ? sanitize_text_field(wp_unslash($_REQUEST['status'])) : '';
        $wp_user = isset($_REQUEST['wp_user']) ? sanitize_key(wp_unslash($_REQUEST['wp_user'])) : '';
        if (!in_array($wp_user, array('', 'linked', 'none'), true)) {
            $wp_user = '';
        }

        $args = array(
            'limit'   => $per_page,
            'offset'  => ($current_page - 1) * $per_page,
            'search'  => $search,
            'status'  => $status,
            'wp_user' => $wp_user,
            'orderby' => $orderby,
            'order'   => $order,
        );

        if ( ! $this->screen ) {
            $this->screen = get_current_screen();
        }

        $this->items = $this->database->get_subscribers($args);

        $total_items = $this->database->get_subscriber_count(
            array(
                'search'  => $search,
                'status'  => $status,
                'wp_user' => $wp_user,
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
     * Render default column output.
     *
     * @param object $item        Subscriber row.
     * @param string $column_name Column slug.
     * @return string
     */
    protected function column_default($item, $column_name): void {
        switch ($column_name) {
            case 'name':
                echo esc_html($item->name);
                return;
            case 'email':
                echo esc_html($item->email);
                return;
            case 'frequency':
                echo esc_html(ucfirst(str_replace('_', ' ', (string) $item->frequency)));
                return;
            default:
                return;
        }
    }

    /**
     * Render linked WordPress user column.
     *
     * @param object $item Subscriber row.
     * @return string
     */
    protected function column_wp_user($item): void {
        echo $this->format_wp_user_cell($item);
    }

    /**
     * Render subscription preferences summary.
     *
     * @param object $item Subscriber row.
     * @return string
     */
    protected function column_subscriptions($item): void {
        $prefs_summary = SubscriberNotifications_Preferences::human_readable_admin_html($item->subscription_preferences ?? '');

        if ($prefs_summary === '') {
            echo '<span class="description">' . esc_html__('—', 'subscriber-notifications') . '</span>';
            return;
        }

        echo wp_kses_post($prefs_summary);
    }

    /**
     * Render status column.
     *
     * @param object $item Subscriber row.
     * @return string
     */
    protected function column_status($item): void {
        $status_text = ($item->status === 'active')
            ? __('Subscribed', 'subscriber-notifications')
            : __('Unsubscribed', 'subscriber-notifications');

        printf(
            '<span class="status-%1$s">%2$s</span>',
            esc_attr($item->status),
            esc_html($status_text)
        );
    }

    /**
     * Render date added column.
     *
     * @param object $item Subscriber row.
     * @return string
     */
    protected function column_date_added($item): void {
        echo esc_html(sn_format_log_date_local($item->date_added));
    }

    /**
     * Render row action forms.
     *
     * @param object $item Subscriber row.
     * @return string
     */
    protected function column_actions($item): void {
        $output = '';

        if ($item->status === 'active') {
            $output .= $this->render_action_form(
                (int) $item->id,
                'unsubscribe',
                __('Unsubscribe', 'subscriber-notifications'),
                'button button-primary button-small'
            );
        } elseif ($item->status === 'inactive') {
            $output .= $this->render_action_form(
                (int) $item->id,
                'subscribe',
                __('Subscribe', 'subscriber-notifications'),
                'button button-secondary button-small'
            );
        }

        $output .= $this->render_action_form(
            (int) $item->id,
            'delete',
            __('Delete', 'subscriber-notifications'),
            'button button-link button-link-delete',
            true
        );

        echo $output;
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
        $wp_user = isset($_REQUEST['wp_user']) ? sanitize_key(wp_unslash($_REQUEST['wp_user'])) : '';
        if (!in_array($wp_user, array('', 'linked', 'none'), true)) {
            $wp_user = '';
        }
        ?>
        <div class="alignleft actions">
            <input type="hidden" name="page" value="subscriber-notifications-subscribers">
            <label class="screen-reader-text" for="filter-by-status"><?php esc_html_e('Filter by status', 'subscriber-notifications'); ?></label>
            <select name="status" id="filter-by-status">
                <option value=""><?php esc_html_e('All Statuses', 'subscriber-notifications'); ?></option>
                <option value="active" <?php selected($status, 'active'); ?>><?php esc_html_e('Subscribed', 'subscriber-notifications'); ?></option>
                <option value="inactive" <?php selected($status, 'inactive'); ?>><?php esc_html_e('Unsubscribed', 'subscriber-notifications'); ?></option>
            </select>
            <label class="screen-reader-text" for="filter-by-wp-user"><?php esc_html_e('Filter by WP user', 'subscriber-notifications'); ?></label>
            <select name="wp_user" id="filter-by-wp-user">
                <option value=""><?php esc_html_e('All WP Users', 'subscriber-notifications'); ?></option>
                <option value="linked" <?php selected($wp_user, 'linked'); ?>><?php esc_html_e('Linked WP user', 'subscriber-notifications'); ?></option>
                <option value="none" <?php selected($wp_user, 'none'); ?>><?php esc_html_e('No WP user', 'subscriber-notifications'); ?></option>
            </select>
            <?php submit_button(__('Filter', 'subscriber-notifications'), '', 'filter_action', false); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=subscriber-notifications-subscribers')); ?>" class="button"><?php esc_html_e('Clear Filters', 'subscriber-notifications'); ?></a>
        </div>
        <?php
    }

    /**
     * Build a POST action form for a subscriber row.
     *
     * @param int    $subscriber_id Subscriber ID.
     * @param string $action        Action slug.
     * @param string $label         Button label.
     * @param string $button_class  CSS classes for the submit button.
     * @param bool   $confirm       Whether to show a browser confirm dialog.
     * @return string
     */
    private function render_action_form(int $subscriber_id, string $action, string $label, string $button_class, bool $confirm = false): string {
        $confirm_attr = '';
        if ($confirm) {
            $confirm_attr = ' onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this subscriber?', 'subscriber-notifications')) . '\');"';
        }

        ob_start();
        ?>
        <form method="post" class="sn-row-action-form">
            <?php wp_nonce_field('subscriber_action', 'subscriber_nonce'); ?>
            <input type="hidden" name="subscriber_id" value="<?php echo esc_attr((string) $subscriber_id); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>">
            <button type="submit" class="<?php echo esc_attr($button_class); ?>"<?php echo $confirm_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                <?php echo esc_html($label); ?>
            </button>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Format the linked WordPress user cell.
     *
     * @param object $subscriber Subscriber row.
     * @return string
     */
    private function format_wp_user_cell($subscriber): string {
        $user_id = isset($subscriber->user_id) ? absint($subscriber->user_id) : 0;

        if ($user_id < 1) {
            return '<span class="description">' . esc_html__('—', 'subscriber-notifications') . '</span>';
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return '<span class="description">' . esc_html(
                sprintf(
                    /* translators: %d: WordPress user ID */
                    __('ID %d (user not found)', 'subscriber-notifications'),
                    $user_id
                )
            ) . '</span>';
        }

        $edit_link = get_edit_user_link($user_id);
        $label     = $user->user_login;

        if ($edit_link) {
            return sprintf(
                '<a href="%1$s">%2$s</a><br><span class="description">%3$s</span>',
                esc_url($edit_link),
                esc_html($label),
                esc_html(
                    sprintf(
                        /* translators: %d: WordPress user ID */
                        __('User ID %d', 'subscriber-notifications'),
                        $user_id
                    )
                )
            );
        }

        return esc_html(
            sprintf(
                /* translators: 1: login, 2: user ID */
                __('%1$s (ID %2$d)', 'subscriber-notifications'),
                $label,
                $user_id
            )
        );
    }
}
