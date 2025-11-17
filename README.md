# Subscriber Notifications Plugin

A comprehensive WordPress plugin for managing subscriber notifications with immediate subscription, scheduling, and analytics.

## Features

### Core Functionality
- **Subscriber Registration Form** - Frontend form with CAPTCHA protection
- **Immediate Subscription** - Subscribers are activated immediately upon form submission
- **Notification Management** - WYSIWYG editor for creating notifications
- **Flexible Email Delivery** - SendGrid integration with WordPress mail fallback
- **Smart Scheduling System** - Daily, weekly and monthly email scheduling
- **Recurring Notifications** - Send notifications repeatedly based on frequency schedule
- **Analytics Tracking** - Email open/click tracking
- **CSV Import/Export** - Bulk subscriber management
- **Rate Limiting** - Prevents email flooding for frequent notifications

### Admin Features
- **Dashboard** - Overview of subscribers, email statistics, and current settings
- **Notification Management** - View, edit, cancel, resend, and delete notifications
- **Recurring Notification Support** - Create and manage notifications that send repeatedly
- **Subscriber Management** - View, edit, activate/deactivate subscribers
- **Notification Creation** - Rich text editor with shortcodes and live preview
- **Email Logs** - Track all email activity with detailed analytics
- **Settings** - Configure email delivery, scheduling, CAPTCHA, templates, global footer, and custom CSS
- **Mail Method Selection** - Choose between SendGrid and WordPress default mail
- **Email Scheduling** - Set daily, weekly and monthly email delivery times
- **Test Functionality** - Test both SendGrid and WordPress mail delivery
- **Migration Tools** - Convert existing notifications to recurring format

### Shortcodes
- `[subscriber_name]` - Subscriber's name
- `[subscriber_email]` - Subscriber's email
- `[selected_news_categories]` - Selected news categories
- `[selected_meeting_categories]` - Selected meeting categories
- `[delivery_frequency]` - Delivery frequency preference
- `[news_feed duration="1day|1week|1month"]` - **Personalized news feed** (shows only subscriber's selected categories)
- `[meetings_feed duration="1day|1week|1month"]` - **Personalized events feed** (shows only subscriber's selected categories)
- `[site_title]` - Site title
- `[manage_preferences_link]` - Manage preferences link
- `[manage_preferences_link text="Custom Text"]` - Manage preferences link with custom text

#### **Personalized Content Behavior:**
The `[news_feed]` and `[meetings_feed]` shortcodes automatically show content based on each subscriber's selected categories, not the notification's target categories. This means:

- **Universal Notifications**: You can create notifications targeting all categories
- **Personalized Results**: Each subscriber sees only content from their selected categories
- **Efficient Management**: One notification reaches everyone with personalized content
- **Better User Experience**: No irrelevant content for subscribers

## Installation

1. Upload the plugin files to `/wp-content/plugins/subscriber-notifications/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings in the 'Notifications' admin menu
4. Add the subscription form to your pages using `[subscriber_notifications_form]`

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+ (or MariaDB equivalent)
- The Events Calendar plugin (for meeting categories)
- SendGrid account (recommended, optional)
- Google reCAPTCHA v2 account (optional)
- **Tested up to WordPress 6.4**

## Configuration

### Email Delivery Setup
1. **Choose Mail Method** - Select SendGrid (recommended) or WordPress default mail
2. **SendGrid Setup** (if using SendGrid):
   - Get your SendGrid API key from your SendGrid account
   - Enter the API key in Settings > SendGrid API Key
   - Configure your from email and name
   - Test the connection
3. **WordPress Mail** (if using WordPress default):
   - No additional setup required
   - Uses your server's mail configuration
   - Test functionality available

### Email Scheduling
1. **Daily Emails** - Set time for daily email delivery (e.g., "9:00 AM")
2. **Weekly Emails** - Set day of week and time (e.g., "Tuesday at 2:00 PM")
3. **Monthly Emails** - Set day of month and time (e.g., "15th at 2:00 PM")
4. **Edge Case Handling** - Monthly day 31st automatically adjusts for shorter months
5. **Cron Scheduling** - Uses WordPress cron with "every_minute" schedule for precise timing

### CAPTCHA Setup
1. Create a Google reCAPTCHA v2 site (the "I'm not a robot" checkbox version)
2. Enter your site key and secret key in Settings
3. The CAPTCHA checkbox will appear on the subscription form

### Email Templates
- Customize welcome email subject and content
- Welcome emails are sent immediately after subscription
- Customize welcome back email for reactivated subscribers
- Customize preferences update confirmation email

### Data Management
- **Delete Data on Uninstall** - Option to preserve or delete all plugin data when uninstalling
  - Default: Data is preserved (checkbox unchecked)
  - Check the box to delete all data when uninstalling
  - Includes: Subscribers, notifications, logs, and all settings

## Usage

### Frontend Form
Add the subscription form to any page or post:
```
[subscriber_notifications_form]
```

Optional parameters:
- `title="Custom Title"` - Custom form title

#### Form Features:
- **Select All Functionality**: "Select All" checkboxes for both news and meeting categories
- **Smart Selection**: Select all categories at once, then uncheck the ones you don't want
- **Visual Feedback**: "Select All" checkbox shows indeterminate state when some (but not all) categories are selected
- **Responsive Design**: Works perfectly on mobile devices
- **Accessibility**: Full keyboard navigation and screen reader support

### Managing Notifications
1. **View All Notifications** - Go to Notifications > Notifications
2. **Search & Filter** - Find specific notifications by title, content, or status
3. **Notification Actions**:
   - **View** - Complete email preview with subject, content, footer, and styling
   - **Edit** - Edit any notification (title, content, categories, frequency)
   - **Cancel** - Cancel pending notifications
   - **Resend** - Resend sent notifications
   - **Delete** - Permanently delete notifications
4. **Status Tracking** - See pending, sent, and cancelled notifications
5. **Content Reuse** - Edit sent notifications to reuse content for new notifications

### Creating Notifications
1. Go to Notifications > Create Notification
2. Enter title, email subject, and content using the WYSIWYG editor
3. Select target categories and frequency:
   - **Select All Categories**: Use "Select All" checkboxes to quickly select all categories, then uncheck unwanted ones
   - **Smart Selection**: Visual feedback shows indeterminate state when partially selected
   - **Efficient Management**: Perfect for sites with many categories
4. Choose notification type:
   - **One-time Notification**: Sent once based on subscriber frequency preferences
   - **Recurring Notification**: Sent repeatedly based on frequency schedule
5. Notifications are sent based on subscriber frequency preferences:
   - **Daily** - Sent on configured daily schedule
   - **Weekly** - Sent on configured weekly schedule
   - **Monthly** - Sent on configured monthly schedule
6. Use shortcodes for dynamic content in both subject and body
7. Global footer automatically added to all notifications
8. Live preview shows exactly what subscribers will receive
9. Preview includes subject, content, footer, and custom CSS styling

### Recurring Notifications

#### **What are Recurring Notifications?**
Recurring notifications are sent repeatedly based on your frequency schedule settings. Unlike one-time notifications that are sent once, recurring notifications continue to send at regular intervals.

#### **How Recurring Notifications Work:**
1. **Create Recurring Notification**: Check "Recurring Notification" when creating a notification
2. **Automatic Scheduling**: The system calculates the next send date based on your settings
3. **Repeated Sending**: After each send, the system calculates the next occurrence
4. **Settings Integration**: Changes to send day/time automatically update all recurring notifications
5. **Status Tracking**: View send count and next send date in the notifications list

#### **Recurring vs One-time Notifications:**
- **One-time**: Sent once when the scheduled time arrives
- **Recurring**: Sent repeatedly at the scheduled intervals
- **Status**: One-time notifications become "sent" after delivery; recurring notifications stay "pending"
- **Management**: Recurring notifications show send count and next send date

#### **Settings Integration:**
- **Dynamic Updates**: When you change send day/time in Settings, all recurring notifications update automatically
- **Consistent Schedule**: All recurring notifications follow the same schedule
- **Flexible Timing**: Change when notifications are sent without recreating them

#### **Use Cases:**
- **Weekly Newsletters**: Send weekly updates that continue indefinitely
- **Monthly Reports**: Send monthly reports that repeat each month
- **Daily Digests**: Send daily summaries that continue daily
- **Regular Updates**: Any notification that should repeat at regular intervals

### Managing Large Subscriber Bases (Thousands of Subscribers)

#### **Universal Notification Strategy:**
For thousands of subscribers, create notifications that target all categories but use personalized shortcodes:

```
Title: Weekly City Updates
Subject: Weekly Updates for [subscriber_name]
Content:
Hello [subscriber_name],

Here are this week's updates based on your interests:

[news_feed duration="1week"]
[meetings_feed duration="1week"]

Your selected categories: [selected_news_categories] and [selected_meeting_categories]
Delivery frequency: [delivery_frequency]

[manage_preferences_link]
```

#### **How It Works:**
- **Target All Categories**: Select all news and meeting categories when creating the notification
- **Personalized Content**: Each subscriber automatically receives only content from their selected categories
- **Efficient Management**: One notification reaches everyone with personalized content
- **No Irrelevant Content**: Subscribers never see content they didn't subscribe to

#### **Benefits:**
- **Scalable**: Works perfectly for thousands of subscribers
- **Personalized**: Each email is customized to subscriber interests
- **Efficient**: No need to create individual notifications for each subscriber
- **Better Engagement**: Relevant content reduces unsubscribe rates

### Global Email Settings
1. Go to Notifications > Settings
2. Scroll to "Global Email Settings" section
3. **Global Header Logo**: Upload a logo for email headers (JPG, PNG, GIF only, max 700x200px, 200KB)
4. **Global Header Content**: Add header content using WYSIWYG editor (appears on left, logo on right)
5. **Global Footer Content**: Add footer content using WYSIWYG editor
6. **Custom Email CSS**: Add custom CSS for email formatting
7. Use shortcodes like [site_title], [manage_preferences_link], [subscriber_name], etc.
8. Header and footer are automatically added to all notification emails
9. Default footer auto-populated with `[site_title] | [manage_preferences_link]` if empty
10. Recommended: Include manage preferences link, contact info, and legal disclaimers in footer

### Preview Functionality
1. **Live Preview** - Preview updates automatically as you type in create form
2. **Complete Preview** - Shows subject, content, global footer, and custom CSS
3. **Shortcode Processing** - Preview processes shortcodes with sample data
4. **Modal Preview** - View complete email preview in modal for existing notifications
5. **Realistic Preview** - Shows exactly what subscribers will receive

### Managing Subscribers
1. View all subscribers in Notifications > Subscribers
2. Filter by status, search by name/email
3. Activate/deactivate or delete subscribers
4. Import/export CSV files

### Subscriber Preference Management
1. Subscribers can manage their own preferences using a token-based link
2. Access preferences page via `?action=manage&token={management_token}`
3. Subscribers can update:
   - Name
   - News categories
   - Meeting categories
   - Delivery frequency
4. Unsubscribe option available on preferences page
5. Confirmation email sent after preference updates
6. Old unsubscribe links (`?action=unsubscribe&token=...`) automatically redirect to preferences page

#### Sticky Header Compatibility
If your theme uses a sticky header, you may need to adjust the top margin of the preferences form to prevent the header from covering the form content. The form's CSS includes margin-top values that account for sticky headers:

- **Desktop**: Default is `125px` (in `assets/css/frontend.css`, line 5)
- **Mobile**: Default is `70px` (in `assets/css/frontend.css`, line 183)

To customize for your theme's sticky header height:

1. Open `assets/css/frontend.css`
2. Find `.subscriber-notifications-form` (around line 3)
3. Adjust the top margin value (first value in `margin: Xpx auto 20px auto`) to match your sticky header's height
4. For mobile, find the `@media (max-width: 768px)` section (around line 181)
5. Adjust the top margin value (first value in `margin: Xpx auto 10px auto`) in the mobile media query

**Example**: If your sticky header is 150px on desktop and 90px on mobile:
```css
.subscriber-notifications-form {
    margin: 150px auto 20px auto; /* Top margin matches your sticky header height */
}

@media (max-width: 768px) {
    .subscriber-notifications-form {
        margin: 90px auto 10px auto; /* Top margin matches your mobile sticky header height */
    }
}
```

### Post/Event Updates
1. Edit any post or event
2. Check "Notify subscribers of update" in the meta box
3. Add custom message (optional)
4. Save to send immediate notifications

## Database Schema

### Subscribers Table
- `id` - Primary key
- `name` - Subscriber name
- `email` - Email address (unique)
- `news_categories` - Comma-separated category IDs
- `meeting_categories` - Comma-separated category IDs
- `frequency` - daily, weekly, monthly
- `status` - active, inactive
- `management_token` - Management token (used for preferences and unsubscribe)
- `date_added` - Registration date
- `last_notified` - Last email sent date

### Notifications Queue Table
- `id` - Primary key
- `title` - Notification title
- `subject` - Email subject line
- `content` - Email content
- `news_categories` - Comma-separated news category IDs
- `meeting_categories` - Comma-separated meeting category IDs
- `frequency_target` - daily, weekly, monthly
- `status` - pending, sent, cancelled
- `created_by` - User ID who created the notification
- `created_date` - When notification was created
- `sent_date` - When notification was sent
- `is_recurring` - Whether notification repeats (0 or 1)
- `next_send_date` - Next scheduled send date for recurring notifications
- `last_sent_date` - Last time notification was sent
- `recurrence_count` - Number of times notification has been sent

### Logs Table
- `id` - Primary key
- `subscriber_id` - Foreign key to subscribers
- `notification_id` - Notification ID
- `email_type` - Type of email sent
- `sent_date` - When email was sent
- `status` - sent, failed, pending
- `open_count` - Number of opens
- `click_count` - Number of clicks
- `tracking_id` - Unique tracking identifier

## Security Features

- **Nonce Verification** - All forms protected with WordPress nonces
- **Data Sanitization** - All input sanitized and validated using WordPress sanitization functions
- **CAPTCHA Protection** - Prevents spam registrations
- **Rate Limiting** - Prevents email flooding (100 requests per hour per IP)
- **SQL Injection Prevention** - Prepared statements used throughout with table/column name validation
- **Input Sanitization** - All `$_SERVER` and user input properly sanitized using `sanitize_text_field()`, `wp_unslash()`, and validation
- **IP Address Validation** - Remote IP addresses validated using `filter_var()` with `FILTER_VALIDATE_IP`
- **Capability Checks** - Admin functions require proper permissions (`manage_options`)
- **Access Controls** - Debug tools restricted to super administrators or when `WP_DEBUG` is enabled
- **URL Validation** - Redirect URLs validated to prevent open redirect vulnerabilities

## Performance Features

- **Batch Processing** - Large subscriber lists processed in batches (10 notifications per batch)
- **Queue System** - Email sending queued for better performance
- **Database Optimization** - Proper indexing for fast queries
- **Caching** - Category queries cached using WordPress transients
- **Background Processing** - Scheduled emails sent via WordPress cron
- **Cron Scheduling** - Uses "every_minute" schedule for precise email delivery timing
- **Efficient Queries** - Optimized database queries with proper WHERE clauses and indexes

## Analytics

- **Open Tracking** - Track email opens with tracking pixels
- **Click Tracking** - Track link clicks
- **Engagement Metrics** - Per-subscriber engagement statistics
- **Category Performance** - See which categories perform best
- **Daily Statistics** - Track performance over time

## Troubleshooting

### Common Issues

1. **Emails not sending**
   - Check mail delivery method in Settings
   - If using SendGrid: verify API key and test connection
   - If using WordPress mail: test WordPress mail functionality
   - Check server mail configuration

2. **Scheduled emails not working**
   - Verify WordPress cron is functioning
   - Check scheduling settings in Settings
   - Ensure cron jobs are properly scheduled

3. **CAPTCHA not working**
   - Verify site key and secret key
   - Check domain configuration in reCAPTCHA v2 settings
   - Ensure you're using reCAPTCHA v2 (not v3) keys

4. **Categories not showing**
   - Ensure The Events Calendar is active
   - Check for "meetings" parent category
   - Verify child categories exist

5. **Shortcodes not working**
   - Ensure content is processed through the shortcode system
   - Check for proper subscriber context

6. **WordPress mail test failing**
   - Check server mail configuration
   - Verify SMTP settings if using SMTP plugin
   - Check server logs for mail errors

### Debug Mode

Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For support and feature requests, please contact the plugin developer.

## Changelog

### Version 2.6.0

* **GitHub Repository**: Updated plugin URI to point to GitHub repository
* **Code Cleanup**: Removed token-debug.php file

### Version 2.5.1
- **Security Enhancements**: Fixed SQL injection vulnerabilities in scheduler and database classes
- **Input Sanitization**: Added proper sanitization for `$_SERVER['REMOTE_ADDR']` in analytics and frontend classes
- **Table Name Validation**: Added validation for table and column names in database operations
- **Code Quality**: Improved security checks and error handling

### Version 2.2.0
- **Email Formatter Class**: New dedicated `SubscriberNotifications_Email_Formatter` class for better code organization
- **Code Refactoring**: Moved email formatting logic to separate singleton class
- **Improved Maintainability**: Centralized email CSS and formatting functions

### Version 2.1.0
- **Daily Scheduling**: Added support for daily email scheduling
- **Enhanced Scheduling**: Improved scheduling system with daily, weekly, and monthly options
- **Settings Improvements**: Added daily send time configuration option

### Version 2.0.0
- **Recurring Notifications**: Send notifications repeatedly based on frequency schedule
- **Enhanced Database Schema**: Added recurring notification support with new columns
- **Settings Integration**: Changes to send day/time automatically update recurring notifications
- **Migration Tools**: Convert existing notifications to recurring format
- **Enhanced Admin Interface**: Recurring status display and management
- **Improved Debug Tools**: Enhanced logging and debugging for recurring notifications
- **Complete Recurring Support**: Full lifecycle management of recurring notifications

### Version 1.0.0
- Initial release
- Complete subscriber management system
- Flexible email delivery (SendGrid + WordPress mail)
- Smart scheduling system (weekly/monthly)
- Notification management (view, edit, cancel, resend, delete)
- Analytics tracking
- CSV import/export
- Shortcode system
- Admin interface with dashboard
- Email testing functionality
- Rate limiting for frequent notifications
- Edge case handling for monthly scheduling
- Search and filter notifications
- Modal preview functionality
