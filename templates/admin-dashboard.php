<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Subscriber Notifications Dashboard', 'subscriber-notifications'); ?></h1>
    
    <div class="subscriber-notifications-dashboard">
        <div class="dashboard-stats">
            <div class="stat-box">
                <h3><?php _e('Active Subscribers', 'subscriber-notifications'); ?></h3>
                <div class="stat-number"><?php echo number_format($total_subscribers ?? 0); ?></div>
            </div>
            
            <div class="stat-box">
                <h3><?php _e('Emails Sent', 'subscriber-notifications'); ?></h3>
                <div class="stat-number"><?php echo number_format($analytics->sent_emails ?? 0); ?></div>
            </div>
            
            <div class="stat-box">
                <h3><?php _e('Open Rate', 'subscriber-notifications'); ?></h3>
                <div class="stat-number">
                    <?php 
                    $sent_emails = $analytics->sent_emails ?? 0;
                    $failed_emails = $analytics->failed_emails ?? 0;
                    $unique_opens = $analytics->unique_opens ?? 0;
                    $delivered_emails = $sent_emails - $failed_emails;
                    $open_rate = $delivered_emails > 0 ? ($unique_opens / $delivered_emails) * 100 : 0;
                    echo number_format($open_rate, 1) . '%';
                    ?>
                </div>
            </div>
            
            <div class="stat-box">
                <h3><?php _e('Click-Through Rate', 'subscriber-notifications'); ?></h3>
                <div class="stat-number">
                    <?php 
                    $sent_emails = $analytics->sent_emails ?? 0;
                    $failed_emails = $analytics->failed_emails ?? 0;
                    $unique_clicks = $analytics->unique_clicks ?? 0;
                    $delivered_emails = $sent_emails - $failed_emails;
                    $click_rate = $delivered_emails > 0 ? ($unique_clicks / $delivered_emails) * 100 : 0;
                    echo number_format($click_rate, 1) . '%';
                    ?>
                </div>
            </div>
        </div>
        
        <div class="dashboard-status">
            <div class="status-box">
                <h3><?php _e('Mail Delivery Method', 'subscriber-notifications'); ?></h3>
                <div class="status-info">
                    <?php 
                    $mail_method = get_option('mail_method', 'sendgrid');
                    if ($mail_method === 'wp_mail') {
                        echo '<span class="status-wp-mail">' . __('WordPress Default Mail', 'subscriber-notifications') . '</span>';
                    } else {
                        echo '<span class="status-sendgrid">' . __('SendGrid', 'subscriber-notifications') . '</span>';
                    }
                    ?>
                </div>
                <a href="<?php echo admin_url('admin.php?page=subscriber-notifications-settings'); ?>" class="button button-small">
                    <?php _e('Change Settings', 'subscriber-notifications'); ?>
                </a>
            </div>
            
            <div class="status-box">
                <h3><?php _e('Email Schedule', 'subscriber-notifications'); ?></h3>
                <div class="status-info">
                    <?php
                    $daily_time = get_option('daily_send_time', '09:00');
                    $weekly_day = get_option('weekly_send_day', 'tuesday');
                    $weekly_time = get_option('weekly_send_time', '14:00');
                    $monthly_day = get_option('monthly_send_day', 15);
                    $monthly_time = get_option('monthly_send_time', '14:00');
                    
                    echo '<div class="schedule-info">';
                    echo '<strong>' . __('Daily:', 'subscriber-notifications') . '</strong> ' . date('g:i A', strtotime($daily_time)) . '<br>';
                    echo '<strong>' . __('Weekly:', 'subscriber-notifications') . '</strong> ' . ucfirst($weekly_day) . ' at ' . date('g:i A', strtotime($weekly_time)) . '<br>';
                    echo '<strong>' . __('Monthly:', 'subscriber-notifications') . '</strong> ' . $monthly_day . date('S', strtotime('2000-01-' . $monthly_day)) . ' at ' . date('g:i A', strtotime($monthly_time));
                    echo '</div>';
                    ?>
                </div>
                <a href="<?php echo admin_url('admin.php?page=subscriber-notifications-settings&tab=scheduling'); ?>" class="button button-small">
                    <?php _e('Change Schedule', 'subscriber-notifications'); ?>
                </a>
            </div>
        </div>
        
        <div class="dashboard-actions">
            <a href="<?php echo admin_url('admin.php?page=subscriber-notifications-create'); ?>" class="button button-primary">
                <?php _e('Create New Notification', 'subscriber-notifications'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=subscriber-notifications-notifications'); ?>" class="button">
                <?php _e('Manage Notifications', 'subscriber-notifications'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=subscriber-notifications-subscribers'); ?>" class="button">
                <?php _e('Manage Subscribers', 'subscriber-notifications'); ?>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=subscriber-notifications-logs'); ?>" class="button">
                <?php _e('View Email Logs', 'subscriber-notifications'); ?>
            </a>
        </div>
        
        <?php if (($analytics->total_emails ?? 0) > 0): ?>
        <div class="dashboard-analytics">
            <h2><?php _e('Analytics', 'subscriber-notifications'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Metric', 'subscriber-notifications'); ?></th>
                        <th><?php _e('Count', 'subscriber-notifications'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php _e('Total Emails Sent', 'subscriber-notifications'); ?></td>
                        <td><?php echo number_format($analytics->sent_emails ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Failed Emails', 'subscriber-notifications'); ?></td>
                        <td><?php echo number_format($analytics->failed_emails ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Total Opens', 'subscriber-notifications'); ?></td>
                        <td><?php echo number_format($analytics->total_opens ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Unique Opens', 'subscriber-notifications'); ?></td>
                        <td><?php echo number_format($analytics->unique_opens ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Total Clicks', 'subscriber-notifications'); ?></td>
                        <td><?php echo number_format($analytics->total_clicks ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Unique Clicks', 'subscriber-notifications'); ?></td>
                        <td><?php echo number_format($analytics->unique_clicks ?? 0); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.subscriber-notifications-dashboard .dashboard-stats {
    display: flex;
    gap: 20px;
    margin: 20px 0;
}

.subscriber-notifications-dashboard .stat-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    flex: 1;
}

.subscriber-notifications-dashboard .stat-box h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
}

.subscriber-notifications-dashboard .stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
}

.subscriber-notifications-dashboard .dashboard-actions {
    margin: 20px 0;
}

.subscriber-notifications-dashboard .dashboard-actions .button {
    margin-right: 10px;
}

.subscriber-notifications-dashboard .dashboard-analytics {
    margin-top: 30px;
}

.subscriber-notifications-dashboard .dashboard-status {
    margin: 20px 0;
    display: flex;
    gap: 20px;
}

.subscriber-notifications-dashboard .status-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    flex: 1;
}

.subscriber-notifications-dashboard .status-box h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #666;
}

.subscriber-notifications-dashboard .status-info {
    flex: 1;
}

.subscriber-notifications-dashboard .status-wp-mail {
    color: #d63638;
    font-weight: bold;
}

.subscriber-notifications-dashboard .status-sendgrid {
    color: #00a32a;
    font-weight: bold;
}

.subscriber-notifications-dashboard .schedule-info {
    line-height: 1.4;
}

.subscriber-notifications-dashboard .status-box .button {
    margin-top: 10px;
}
</style>
