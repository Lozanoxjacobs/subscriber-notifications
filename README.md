# Subscriber Notifications Plugin

A comprehensive WordPress plugin for managing subscriber notifications with immediate subscription, scheduling, and analytics.

## Features

### Core Functionality
- **Subscriber Registration Form** - Frontend form with CAPTCHA protection; guests enter name and email, logged-in users use readonly account fields
- **WordPress account linking** - Logged-in signups store the subscriber's WordPress `user_id` for admin visibility and reliable identity on resubscribe
- **Immediate Subscription** - Subscribers are activated immediately upon form submission
- **Notification Management** - WYSIWYG editor for creating notifications
- **Email Delivery** - Sends through WordPress `wp_mail()` (use your SMTP or mail plugin); open/click logging preserved
- **Smart Scheduling System** - Daily, weekly and monthly email scheduling
- **Recurring Notifications** - Send notifications repeatedly based on frequency schedule
- **Analytics Tracking** - Email open/click tracking
- **CSV Import/Export** - Bulk subscriber management; exports include `user_id` when linked to a WordPress account
- **Rate Limiting** - Prevents email flooding for frequent notifications

### Admin Features
- **Dashboard** - Overview of subscribers, email statistics, and current settings
- **Notification Management** - View, edit, cancel, resend, and delete notifications
- **Recurring Notification Support** - Create and manage notifications that send repeatedly
- **Subscriber Management** - View subscribers (including **WP User** link and ID), search, activate/deactivate, delete, and CSV import/export
- **Notification Creation** - Rich text editor with shortcodes and live preview
- **Email Logs** - Track all email activity with open/click counts and log type (notification, welcome, preferences update, test, etc.)
- **Content Types** - Enable public post types and taxonomies, term display rules (all / children of / include / exclude), and form labels
- **Settings** - Scheduling, CAPTCHA, email templates, Email Design branding, and general options (test email, hide empty terms on form, delete on uninstall)
- **Test email** - Send a test message to verify mail delivery (`wp_mail()`)

## Installation

1. Upload the plugin files to `/wp-content/plugins/subscriber-notifications/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings in the 'Notifications' admin menu
4. Add the subscription form to your pages using `[subscriber_notifications_form]`

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+ (or MariaDB equivalent)
- Google reCAPTCHA v2 account (optional)
- **Tested up to WordPress 6.8**

> Subscriber Notifications is now content-type agnostic. Configure which public post types and taxonomies subscribers can choose from under **Notifications > Content Types** before publishing the subscription form. Any post type plugin (including The Events Calendar) is supported out of the box — there is no longer a hard dependency on a specific plugin.

## How it works

1. **Content Types** — You enable post types and taxonomies; that drives the subscribe form, notification target checklists, CSV import/export columns, and matching rules.
2. **Subscriber choices** — Each person’s selected terms and delivery frequency are saved when they subscribe or update preferences. Each notification stores which terms that send is about.
3. **Who gets an email** — Active subscribers are matched by frequency (daily, weekly, or monthly), then filtered to those who share at least one targeted term with the notification.
4. **What they see** — Email content uses shortcodes. `[content_feed]` builds a personalized list of posts per recipient. Global header, footer, and styling come from **Email Design** settings.
5. **Delivery** — Mail is sent through WordPress (use SMTP or a mail plugin on your host if needed). Opens and clicks are logged automatically for every send.

Posts can be marked **Notify subscribers** in the editor; that sets feed meta so digests pick them up on the next scheduled run.

## Shortcodes

> **In-admin reference:** **Notifications → Settings → Shortcodes** has the same reference plus an auto-generated panel listing the post type and taxonomy slugs configured on this site. Administrators should use that tab; this section is a developer-focused summary.

Use in notification **subject** and **body**, welcome/preference emails, and global header/footer (Email Design).

| Shortcode | Use |
|-----------|-----|
| `[subscriber_name]` | Subscriber name |
| `[subscriber_email]` | Subscriber email |
| `[delivery_frequency]` | Daily / weekly / monthly label |
| `[selected_subscriptions]` | Formatted list of all selections (HTML in body; use `format="plain"` in **subject** only) |
| `[selected_terms taxonomy="…"]` | Names of the subscriber's selected terms in one taxonomy. Optional `post_type="…"` (omit to aggregate across post types) and `separator="…"` (default `, `) |
| `[site_title]` | Site name |
| `[manage_preferences_link]` | Clickable HTML link to the subscriber's preferences page. Default link text **Manage Preferences**; override with `text="…"` |
| `[subscriber_notifications_form]` | Public subscribe form (`title="…"` optional) |

### `[content_feed]` (personalized post lists)

Only includes **published** posts flagged for the feed, notified within the `duration` window (`1day`, `1week`, `1month`).

| Attribute | Notes |
|-----------|--------|
| `post_type` | Required. Must be enabled in Content Types. |
| `taxonomy` | Optional. **One taxonomy** — filter on that dimension only. **Omit** — match the subscriber in **any** form taxonomy for that post type (OR). |
| `duration` | Default `1month`. |
| `limit` | Default `10`. |
| `format` | `list` (default) = bulleted title links; `summary` = linked title + short excerpt. |
| `terms` | Optional comma-separated **term slugs**; **requires** `taxonomy`. Alias: `term` (same value). Scopes the feed block; each subscriber sees only slugs they subscribed to (intersection). Unknown slugs skipped. |

**Slugs:** Use the exact slug from WordPress for each taxonomy and term (Posts → Categories / your taxonomy; edit a term to see its slug). Taxonomy slugs and term slugs may use different punctuation (e.g. `tribe_events_cat` vs `family-events`) — always copy from WordPress, do not guess.

**Personalization:** Without `terms`, the feed uses the subscriber’s selections and the notification’s targets together. With `terms`, the feed uses only the listed slugs that the subscriber also selected (notification targets still control who receives the email). Create one notification with broad targets; each subscriber sees only matching posts.

**Examples:**

```
[content_feed post_type="post" taxonomy="category" duration="1week" format="list"]
[content_feed post_type="tribe_events" taxonomy="tribe_events_cat" terms="family-events,concerts,kids-events" duration="1week" format="list"]
[content_feed post_type="project" duration="1day" limit="10" format="summary"]
```

**Community events example:** Notification targets Family Events, Concerts, and Kids Events (who receives the email). Shortcode `terms="family-events,concerts,kids-events"` scopes the feed. A subscriber who chose Family Events and Concerts only sees those two — not Kids Events.

**Multi-taxonomy example:** A subscriber excludes status “On Hold” but includes type “Commerce”. `[content_feed post_type="project" duration="1day"]` can still show a project tagged On Hold + Commerce, because Commerce matches.

## Configuration

### Email Delivery Setup
1. **WordPress mail** - All plugin emails go through `wp_mail()`. Configure **SMTP** or a mail plugin if your host does not send mail reliably.
2. **Test** - In **Notifications > Settings > General**, enter a test address and use **Send Test Email** to verify delivery.

### Email Scheduling
1. **Daily Emails** - Set time for daily email delivery (e.g., "9:00 AM")
2. **Weekly Emails** - Set day of week and time (e.g., "Tuesday at 2:00 PM")
3. **Monthly Emails** - Set day of month and time (e.g., "15th at 2:00 PM")
4. **Edge Case Handling** - Monthly day 31st automatically adjusts for shorter months
5. **Cron Scheduling** - Uses WordPress cron with "every_minute" schedule for precise timing

### CAPTCHA Setup
1. Create a Google reCAPTCHA v2 site (the "I'm not a robot" checkbox version)
2. Enter your site key and secret key in **Notifications > Settings > Security**
3. The CAPTCHA checkbox will appear on the subscription form

### Content Types
1. Go to **Notifications > Content Types**
2. Enable one or more public post types and set a display label for each
3. For each post type, enable taxonomies that should appear on the subscription form and choose how terms are listed:
   - **All** — every term in the taxonomy
   - **Children of** — terms under a parent (hierarchical taxonomies only)
   - **Include** — only selected term IDs
   - **Exclude** — all terms except selected IDs
4. Save via **options.php** (Settings API). The public form will not render until at least one post type and one taxonomy are enabled.

### General Settings
Under **Notifications > Settings > General**:

- **Test Email Address** — address used by **Send Test Email** (`wp_mail()`)
- **Hide Empty Terms on Subscription Form** (default **on**) — on the public subscribe and preferences forms, hide terms that have zero **published** posts for the configured post type (e.g. empty `Uncategorized`). Admin notification targets, Content Types configuration, and CSV import reference lists always show every configured term. Uncheck to show all configured terms on the public form regardless of post count.
- **Delete Data on Uninstall** — when checked, uninstall removes subscribers, logs, queue, and plugin options

### Email Templates
- Customize welcome email subject and content
- Welcome emails are sent immediately after subscription
- Customize welcome back email for reactivated subscribers
- Customize preferences update confirmation email

## Usage

### Frontend Form
Add the subscription form to any page or post:
```
[subscriber_notifications_form]
```

Optional parameters:
- `title="Custom Title"` - Custom form title

#### Form layout and behavior:
- **Collapsible sections** — one block per enabled post type; checklists per enabled taxonomy
- **Select all** — per-taxonomy control with indeterminate state when partially selected
- **Theme-native markup** — minimal plugin CSS; the active theme styles most of the form
- **Empty terms** — by default, terms with no published posts for that post type are hidden on the public form (see **Hide Empty Terms on Subscription Form** under General settings)
- **Validation** — at least one term must be selected across all sections; frequency is required

#### Logged-in users:
- **Name and email** are prefilled from the WordPress profile (`first_name`, `last_name`, `user_email`) and shown as **read-only** on the form
- **Server-side enforcement** — on submit, the plugin uses the account identity and ignores POSTed name/email for logged-in users
- **Account linking** — the subscriber row stores `user_id`; existing rows are matched by `user_id` first, then by email for legacy guest signups
- **Preferences form** — same term/frequency UI as subscribe (including hide-empty behavior); name/email on that form are not locked to the WP account
- **CAPTCHA** — still required when CAPTCHA is enabled (same as guests)

Guests continue to enter and edit name and email normally.

### Managing Notifications
1. **View All Notifications** - Go to Notifications > Notifications
2. **Search & Filter** - Find specific notifications by title, content, or status
3. **Notification Actions**:
   - **View** - Complete email preview with subject, content, footer, and styling
   - **Edit** - Edit any notification (title, content, target terms, frequency)
   - **Cancel** - Cancel pending notifications
   - **Resend** - Resend sent notifications
   - **Delete** - Permanently delete notifications
4. **Status Tracking** - See pending, sent, and cancelled notifications
5. **Content Reuse** - Edit sent notifications to reuse content for new notifications

### Creating Notifications
1. Go to Notifications > Create Notification
2. Enter title, email subject, and content using the WYSIWYG editor
3. Select target terms and frequency (same Content Types as the public form; admin lists include empty terms)
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
For thousands of subscribers, create notifications that target broad term sets but use personalized shortcodes:

```
Title: Weekly City Updates
Subject: Weekly Updates for [subscriber_name]
Content:
Hello [subscriber_name],

Here are this week's updates based on your interests:

[content_feed post_type="post" taxonomy="category" duration="1week"]
[content_feed post_type="project" duration="1week"]
[content_feed post_type="tribe_events" taxonomy="tribe_events_cat" duration="1week"]

Your subscriptions: [selected_subscriptions]
Delivery frequency: [delivery_frequency]

[manage_preferences_link]
```

#### **How It Works:**
- **Target All Terms**: Select all relevant terms when creating the notification
- **Personalized Content**: Each subscriber automatically receives only content from their selected terms
- **Efficient Management**: One notification reaches everyone with personalized content
- **No Irrelevant Content**: Subscribers never see content they didn't subscribe to

#### **Benefits:**
- **Scalable**: Works perfectly for thousands of subscribers
- **Personalized**: Each email is customized to subscriber interests
- **Efficient**: No need to create individual notifications for each subscriber
- **Better Engagement**: Relevant content reduces unsubscribe rates

### Email Design (Settings tab)
1. Go to **Notifications > Settings** and open the **Email Design** tab
2. **Header & Footer** — global header logo (JPG, PNG, GIF; max 700×200px, 200KB), header content (WYSIWYG), and footer content (WYSIWYG). Header/footer are added to every notification email. Default footer: `[site_title] | [manage_preferences_link]` if empty
3. **Brand Colors** — body text, link, link hover (`a:hover` in clients that support it), outer background, content card background, footer background, footer text. CTAs use text links (e.g. `[manage_preferences_link]`), not button styles
4. **Typography** — body font and optional heading font (leave heading blank to use body font)
5. **Advanced** — custom CSS appended after generated branding CSS for fine-tuning
6. Shortcodes work in header/footer content: `[site_title]`, `[manage_preferences_link]`, `[subscriber_name]`, etc.

### Preview Functionality
1. **Live Preview** - Preview updates automatically as you type in create form
2. **Complete Preview** - Shows subject, content, global footer, and custom CSS
3. **Shortcode Processing** - Preview processes shortcodes with sample data
4. **Modal Preview** - View complete email preview in modal for existing notifications
5. **Realistic Preview** - Shows exactly what subscribers will receive

### Managing Subscribers
1. View all subscribers in **Notifications > Subscribers**
2. Filter by status; search by **name**, **email**, or a numeric **WordPress user ID**
3. **WP User** column — login linked to the user profile when `user_id` is set; em dash (—) for guests; **ID {n} (user not found)** if the linked WordPress user was deleted but the subscriber row still has `user_id`
4. Activate, deactivate, or delete subscribers from the list (admin actions do not set or change `user_id`; linking happens on logged-in frontend subscribe/reactivate only)
5. **Import/export** — see [CSV import and export](#csv-import-and-export) below

### CSV import and export
**Export** (Notifications > Import/Export):
- Includes columns: `id`, `name`, `email`, `user_id`, `frequency`, `status`, `date_added`, `last_notified`, followed by one column per configured `post_type:taxonomy` pair (e.g. `post:category`, `tribe_events:tribe_events_cat`).
- Term values are comma-separated term names. `user_id` is the WordPress user ID when the subscriber signed up while logged in; empty for guests.

**Import**:
- Required columns: `name`, `email`
- Optional: `frequency`, plus any `post_type:taxonomy` columns matching configured content types. Each subscriber row must select at least one term across the configured taxonomies.
- **Imports ignore `user_id`** even if present in the file (prevents incorrect account links from spreadsheets). Link accounts only via the public subscription form when the user is logged in.

### Subscriber Preference Management
1. Subscribers can manage their own preferences using a token-based link
2. Access preferences page via `?action=manage&token={management_token}`
3. Subscribers can update:
   - Name
   - Their selected terms for each configured post type / taxonomy
   - Delivery frequency
4. Unsubscribe option available on preferences page
5. Confirmation email sent after preference updates

### Flagging Posts for Notifications
1. Edit any post in an enabled content type
2. Check **Notify subscribers** in the meta box (includes the post in feed-flagged digests; does not send an immediate blast)
3. Save — the post is eligible for the next scheduled notification that matches subscriber preferences

## Data storage

The plugin stores subscribers, scheduled notifications, and email logs in the WordPress database. Data is created when the plugin is activated. You can optionally delete all plugin data on uninstall under **Settings > General**.

## Security and performance

Nonces, capability checks (`manage_options`), sanitized input, optional reCAPTCHA, rate limiting, and prepared SQL. Digests run on WordPress cron with batched sends for large lists; term lists are cached where appropriate.

## Analytics

Every email the plugin sends is tracked automatically (there is no on/off setting).

- **Open tracking** — A 1×1 pixel in each message requests `/track/open/` when the email is opened.
- **Click tracking** — Links in the email body (including `[content_feed]` post links and `[manage_preferences_link]`) are wrapped with `/track/click/` redirects so clicks are counted before the subscriber reaches the destination. `mailto:` and `tel:` links are not tracked.
- **Where to view logs** — **Notifications → Logs** (full list and CSV export) and the dashboard **Recent activity** panel.
- **Email log types** — Each row is labeled by purpose: **Notification** (digests), **Welcome**, **Welcome back**, **Preferences update**, and **Test** (admin test/preview sends).
- **Engagement metrics** — The dashboard **Email delivery** panel shows open and click rates for the selected period (7 / 30 / 90 days or all time), using unique opens and clicks divided by delivered emails.

## Troubleshooting

### Common Issues

1. **Emails not sending**
   - Confirm `wp_mail()` works on this site (SMTP plugin or host mail logs)
   - Use **Send Test Email** under Settings > General
   - Check server mail configuration or outbound SMTP blocking

2. **Scheduled emails not working**
   - Verify WordPress cron is functioning
   - Check scheduling settings in Settings
   - Ensure cron jobs are properly scheduled

3. **CAPTCHA not working**
   - Verify site key and secret key
   - Check domain configuration in reCAPTCHA v2 settings
   - Ensure you're using reCAPTCHA v2 (not v3) keys

4. **No terms (or too few) on the subscription form**
   - Visit **Notifications > Content Types** and confirm at least one post type is enabled
   - For each enabled post type, enable the taxonomies you want subscribers to choose from and set a valid term display mode (all, children of, include, or exclude)
   - Ensure the parent term (for "children of" mode) or each listed term still exists
   - If terms exist but have no published posts, they are hidden when **Hide Empty Terms on Subscription Form** is enabled (default). Uncheck that option under **Settings > General**, or publish content in those terms, or adjust Content Types include/exclude rules

5. **Unwanted terms still visible on the public form**
   - A term with at least one **published** post of that post type will always appear when configured. To hide it anyway, use **Content Types** exclude (or include-only) rules
   - Terms with zero published posts for that post type are hidden automatically when **Hide Empty Terms on Subscription Form** is on

6. **Shortcodes not working**
   - Ensure content is processed through the shortcode system
   - Check for proper subscriber context

7. **WordPress mail test failing**
   - Check server mail configuration
   - Verify SMTP settings if using SMTP plugin
   - Check server logs for mail errors

### Debug Mode

Enable WordPress debug mode to see detailed error messages:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Production cron

The scheduler registers five cron hooks (`subscriber_notifications_process_queue`, `subscriber_notifications_send_daily`, `subscriber_notifications_send_weekly`, `subscriber_notifications_send_monthly`, `subscriber_notifications_drain_queue`), all running every minute. WP-Cron only fires on incoming WordPress requests, so on low-traffic sites a notification can sit idle until someone visits the site. In production, disable WP-Cron and trigger it from a real system cron:

```php
// wp-config.php
define('DISABLE_WP_CRON', true);
```

```cron
# /etc/crontab or `crontab -e` for the web user
* * * * * curl -s https://example.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

This guarantees the send queue drains on a strict one-minute cadence regardless of site traffic.

## Changelog

### Version 3.5.0

- **Refactor — Subscribers admin list table** — Subscribers screen now uses WordPress `WP_List_Table` for columns, sortable headers, search, status filter, pagination, and screen options. Row actions (subscribe, unsubscribe, delete) keep the existing POST + PRG behavior
- **Admin — Clear Filters** — Subscribers and Notifications list tables now include a Clear Filters button consistent with Email Logs
- **Refactor — Notifications and Email Logs list tables** — Notifications and Email Logs screens now use `WP_List_Table` with the same screen/form wiring as Subscribers (GET form wrapper, explicit screen id, column headers). Logs filters and purge UI unchanged

### Version 3.4.12

- **Fix — Email Logs purge summary text** — JavaScript now substitutes `%1$s` / `%2$s` placeholders correctly (was showing raw `%1$d` format strings)
- **Fix — Email Logs purge** — purge form now posts via `admin-post.php` (reliable WordPress admin POST handler). Each age preset shows how many entries match; **Purge** is disabled when the count is zero. Success and warning notices explain whether rows were deleted or none matched the selected age

### Version 3.4.11

- **Admin — Email Logs filters and maintenance** — added **Email Type** filter (notification, welcome, welcome back, preferences update, test) wired through list, pagination, and CSV export
- **Refactor — shared log date helpers** — consolidated UTC and site-timezone formatters and email type labels into `includes/log-date-helpers.php`; dashboard recent activity uses the same helpers
- **Admin — Email Logs accessibility** — table header cells now use `scope="col"`
- **Admin — purge old logs** — manual purge of entries older than 30 / 90 / 180 / 365 days with browser confirm, PRG flash notice showing deleted count; CSV export date columns formatted in site timezone

### Version 3.4.10

- **Fix — email log date filters** — date range filters on **Email Logs** now include the full end day and convert site-calendar dates to UTC before querying `sent_date` (stored as UTC). Previously `date_to=YYYY-MM-DD` was compared as midnight, which excluded almost all rows on that day; filters also did not match dates shown in the site timezone

### Version 3.4.9

- **Admin — consistent page headers** — Subscribers, Email Logs, Import/Export, Settings, Create Notification, and Content Types screens now use the WordPress `wp-heading-inline`, `page-title-action`, and `wp-header-end` layout. Import/Export CSV and Export Logs moved into the page title bar; duplicate Export Logs footer control removed

### Version 3.4.8

- **Refactor — admin inline assets** — moved inline `<style>` and `<script>` blocks from admin templates into enqueued `assets/css/admin.css` and `assets/js/admin.js`. PHP values (nonces, i18n strings) passed via `wp_localize_script()`. No intentional UI changes

### Version 3.4.7

- **Admin — Subscribers Import/Export link** — the Subscribers screen **Import/Export CSV** button now opens the Import/Export page at the **Export Subscribers** section (`#export-subscribers`) so users are not left at the long import instructions at the top of the page

### Version 3.4.6

- **Cleanup — remove dead admin code** — dropped unused notification AJAX handlers (`save_notification`, `update_notification`), dead subscriber-action and bulk-action JavaScript in `admin.js`, and unused Thickbox script/style enqueues. Notification create/edit continue to use form POST; subscribers list uses form POST with PRG flash notices

### Version 3.4.5

- **Fix — subscriber admin flash notices not displaying** — flash notice handlers now run on the `admin_notices` hook (before page output) and render the notice directly instead of registering a nested `admin_notices` callback from the page callback, which ran too late to display

### Version 3.4.4

- **Fix — subscriber admin success notices** — activate, subscribe, unsubscribe, and delete actions on the Subscribers list now use Post-Redirect-Get flash messages (`?message=...`) so success notices appear after redirect instead of being lost when `wp_redirect()` runs in the same request
- **Docs — Analytics and email logs** — expanded Analytics section (open/click behavior, log types, where to view logs); Email Logs feature bullet and How it works delivery step updated to reflect automatic tracking

### Version 3.4.3

- **Fix — email click tracking** — click-tracking links now use literal `&` separators in HTML href attributes (`esc_attr()` instead of `esc_url()`) so multi-parameter `/track/click/` URLs work reliably in email clients. Manage-preferences links and other in-body links are wrapped for click tracking again (only `mailto:`, `tel:`, and existing tracking URLs are skipped). Fallback manage links are appended before click tracking so they are counted too. Redirect validation decodes HTML entities in the destination URL

### Version 3.4.2

- **Fix — email log type for transactional emails** — `SubscriberNotifications_Email_Sender::send_email()` no longer logs every send as `notification`. Welcome, welcome back, preferences update, and admin test/preview emails now write distinct `email_type` values (`welcome`, `welcome_back`, `preferences_update`, `test`). Notification digests continue to log as `notification`. Email logs and CSV export display types with spaces instead of underscores

### Version 3.4.1

- **Cleanup — remove legacy unsubscribe URL and migration code** — dropped the `?action=unsubscribe&token=...` redirect to the manage-preferences page; the frontend route handler is now `handle_manage_preferences_route()` and only serves `?action=manage&token=...`. Removed the `unsubscribe_token` → `management_token` database migration, activation-time cleanup of obsolete `unsubscribe_page_*` options, and the email click-tracking skip for old unsubscribe URLs. Preferences-page unsubscribe (AJAX) and admin unsubscribe are unchanged

### Version 3.4.0

- **Admin — dashboard UI polish** — dashboard action links use standard core `button` sizing (not `button-small`); analytics period filters use `nav-tab-wrapper` / `nav-tab` like plugin Settings; Content Types CTA matches other secondary buttons; postbox titles get core-aligned header padding on custom admin pages
- **Admin — dashboard layout** — primary column ordered for daily operations (email delivery → send queue → upcoming sends → recent activity; setup & health first only when required, otherwise last). Sidebar: notifications and subscribers, then schedule, content types, and mail delivery. Quick links trimmed to Import/Export, Email Design, and Shortcodes (duplicates removed)
- **Admin — dashboard overhaul** — rebuilt **Notifications → Dashboard** with a WordPress-native postbox layout: setup & health checklist (Content Types, subscribers, cron hooks, optional reCAPTCHA), subscriber and notification summaries, time-windowed email delivery stats (7 / 30 / 90 days / all time), upcoming sends table, per-recipient send queue status with recent failures, recent activity (logs + signups), schedule summary, inline test email, Content Types snapshot, and quick links. New `SubscriberNotifications_Dashboard` class and database helpers aggregate snapshot data; dashboard styles moved from inline template CSS to `assets/css/admin.css`
- **Admin — Add New Notification on edit screen** — the Edit Notification screen now includes an **Add New Notification** `page-title-action` link beside the heading, matching the standard WordPress admin pattern used on custom post type edit screens and the plugin's Notifications list table. Uses `wp-heading-inline`, `page-title-action`, and `wp-header-end` for correct layout and sticky-header behavior

### Version 3.3.3

- **Fix — Resend / Reactivate buttons now recompute `next_send_date`** — the admin Resend (one-time) and Reactivate (recurring) actions previously only flipped `status` back to `pending` without recalculating `next_send_date`, so the notification kept whatever stale send time it had from its prior run. If that timestamp was already in the past (the common case), the every-minute `process_queue` cron picked it up on the next tick and sent it immediately, ignoring the configured global send time. The action handler now calls `SubscriberNotifications_Schedule_Calculator::next_one_time()` (one-time) or `next_recurring()` (recurring) and writes the result to `next_send_date`. For one-time notifications, the prior `sent_date` is also cleared so the admin "Sent" column doesn't show a stale timestamp on a notification that is now awaiting a fresh send. The success-notice condition was also tightened from `if ($result)` to `if ($result !== false)` so a no-op update (e.g., resending when fields are already correct) is not misreported as a failure

### Version 3.3.2

- **Fix — subscriber `last_notified` column is now updated on every successful send** — `SubscriberNotifications_Database::update_subscriber_last_notified()` existed but was never called from the send path, so the `wp_subscriber_notifications.last_notified` column stayed `NULL` for every subscriber even after successful deliveries. The drain-send-queue handler now calls it on every row that transitions to `sent`. Admin subscriber list, CSV export, and any code that filters on `last_notified` now reflect actual notification activity

### Version 3.3.1

- **Fix — one-time notifications respect changes to global send-time settings** — when the `daily_send_time`, `weekly_send_day`, `weekly_send_time`, `monthly_send_day`, or `monthly_send_time` option changes, the recalculation handler now updates `next_send_date` for **all** pending notifications of the affected frequency (recurring and one-time), not just recurring rows. Previously a one-time notification queued before the option change kept its original send time and ignored the new global setting. The internal handler was renamed from `update_recurring_notifications_schedule()` to `update_pending_notifications_schedule()` to reflect its broader scope

### Version 3.3.0

- **Fix — one-time notifications send on time** — the `subscriber_notifications_process_queue` cron now runs every minute (was hourly), and one-time notifications get a `next_send_date` at creation so they're picked up by the same SQL filter (`next_send_date <= NOW()`) used by recurring notifications. Previously a one-time notification could sit up to an hour past its target send time
- **Fix — notification "Created" column shown in site timezone** — the Notifications admin table converts `created_date` (stored as UTC via MySQL `CURRENT_TIMESTAMP`) through `get_date_from_gmt()` instead of `mysql2date()`, so the displayed timestamp matches the site's WordPress timezone
- **Scheduling refactor — single source of truth for date math** — the three duplicate `calculate_next_send_date` implementations across `class-admin.php`, `class-scheduler.php`, and `class-database.php` have been consolidated into `SubscriberNotifications_Schedule_Calculator` (`next_one_time()` and `next_recurring()`). Both private `get_wordpress_timezone()` helpers were also deleted; `wp_timezone()` is used everywhere
- **Scheduling refactor — modern WordPress timezone APIs** — every `current_time('timestamp')` call (deprecated since WP 5.3) and every bare `date()` / `strtotime()` used for timezone-sensitive output now goes through `wp_timezone()` + `DateTimeImmutable` or `wp_date()`
- **Scheduling refactor — per-recipient send queue** — `send_scheduled_notification()` no longer loops and calls `wp_mail()` inline. Instead it enqueues one row per eligible subscriber into `wp_subscriber_notifications_send_queue` via `INSERT IGNORE` (the `(notification_id, subscriber_id)` UNIQUE KEY makes the enqueue idempotent under retry). A new `subscriber_notifications_drain_queue` cron handler drains up to N rows per minute, recording `attempts` / `last_error` on each row, and finalizes the notification (`sent_date`, `next_send_date` recompute for recurring, `recurrence_count++`) only once every row is processed. The expensive per-subscriber `has_relevant_content()` `WP_Query` now runs at drain time so the work is spread across cron ticks instead of blocking the cron handler that fires the send
- **Scheduling refactor — cancellation skips queued recipients** — cancelling a notification flips its remaining queue rows from `pending` to `skipped` so the drain handler ignores them. Deleting a notification cascades a `DELETE` over its queue rows. Resend / reactivate clears prior queue rows so the next enqueue starts fresh
- **Class rename — `SubscriberNotifications_SendGrid` → `SubscriberNotifications_Email_Sender`** — the class only wraps `wp_mail()` and adds open / click tracking; it has never spoken to SendGrid directly (transport is configured site-wide via WordPress core, SMTP, or a dedicated transport plugin). The file moved from `includes/class-sendgrid.php` to `includes/class-email-sender.php`
- **Filter — `subscriber_notifications_queue_batch_size`** — controls how many queue rows the drain handler processes per cron tick (default `50`)
- **Internal cleanup** — consolidated cron registration into `SubscriberNotifications_Scheduler::schedule_cron_jobs()` and removed the now-redundant `schedule_events()` helper / activation call from the main plugin file. Deleted three dead methods (`get_next_daily_time` / `get_next_weekly_time` / `get_next_monthly_time` in `class-scheduler.php`) and one dead helper (`calculate_next_send_date_for_existing` in `class-database.php`)
- **Settings** — reCAPTCHA field helper text now specifies v2 (the "I'm not a robot" checkbox version) so administrators know which key type to create
- **Documentation** — added a "Production cron" section recommending `DISABLE_WP_CRON` + system cron for sites that need strict one-minute scheduling regardless of traffic
- **Documentation** — corrected the `[selected_terms]` and `[manage_preferences_link]` shortcode reference rows to match actual output (selected-only terms with `taxonomy` required; HTML link with default text "Manage Preferences")
- **Documentation** — removed the obsolete "Sticky Header Compatibility" section that referenced CSS rules and line numbers no longer present in `assets/css/frontend.css`
- **Documentation** — tightened the analytics summary: dropped the unimplemented "Term performance" bullet and clarified "Engagement Metrics" as aggregate open/click statistics with date-range filtering
- **Documentation** — renamed the "Post/Event Updates" section to "Flagging Posts for Notifications" so it reads correctly for any configured content type (the plugin is no longer event-specific)
- **Documentation** — removed the "Support" section (boilerplate; not actionable)

### Version 3.2.0

- **Settings API parity** — General, Email Templates, Scheduling, Security, and Email Design tabs now all save through WordPress core's Settings API (`options.php`), matching the pattern used by Content Types. Removes the legacy custom save handler so every settings tab persists, sanitizes, and surfaces success / validation notices through core. Tab markup is now driven by `do_settings_sections()` for consistency with WordPress admin conventions
- **Scheduling side effects** — recalculating `next_send_date` for recurring notifications now hooks into per-option `update_option_*` / `add_option_*` actions, so the recalculation runs whether the option is changed from the Settings page, WP-CLI, or any other code path (still scoped to only the changed frequency)
- **Internal cleanup** — removed the no-op `reschedule_cron_jobs()` method (scheduler runs every minute regardless)

### Version 3.1.4

- **Settings → Shortcodes** — new tab with a full shortcode reference, including an auto-generated panel listing the post type and taxonomy slugs configured on this site so administrators can copy them exactly
- **Admin forms** — removed the duplicated inline shortcode help from the create / edit notification screens and from the Email Templates and Email Design fields; each field now links to the Shortcodes reference tab

### Version 3.1.3

- **Create notification** — after a successful save, you are taken to the edit screen for that notification (same pattern as creating a post), with a success notice
- **Required notification fields** — Target Content (at least one term) and Target Frequency are required on create and edit, in addition to notification title and email subject
- **Form errors** — if validation fails, entered values stay on the form (title, subject, content, targets, and frequency) so nothing is lost while you fix the issue

### Version 3.1.2

- **Subscription form term lists** — subscribe, preferences, and notification target pickers use a single-column checklist. Hierarchical taxonomies show a nested tree (A–Z at each level); non-hierarchical taxonomies show a flat A–Z list. Post type sections start collapsed
- **Form styling** — removed dashed separators between taxonomies on the same post type and removed the default fieldset border around taxonomy blocks

### Version 3.1.1

- **`[content_feed]` `terms` attribute** — comma-separated **term slugs**; requires `taxonomy`. Use `terms=` (singular `term` is also accepted). Feed lists only scoped slugs the subscriber selected. Notification targets still control who receives the email

### Version 3.1.0

- **`[content_feed]` omit `taxonomy`** — personalized feed across all form taxonomies for a post type (OR match)
- **`format="summary"`** — linked title + excerpt per post. `format="list"` unchanged (bulleted title links). Unknown format values fall back to `list`
- **`[selected_subscriptions]`** — HTML in email body (bold post type and taxonomy labels, line breaks per taxonomy). `[selected_subscriptions format="plain"]` for subject lines

### Version 3.0.0

- **Configurable content types** — choose post types and taxonomies in admin; dynamic subscribe form and notification targeting
- **Generic shortcodes** — `[selected_subscriptions]`, `[selected_terms]`, `[content_feed]`; removed v2 news/meetings shortcodes
- **Email Design** — brand colors, typography, header/footer, custom CSS
- **Hide empty terms on public form** (default on)
- **No automatic migration** from older 2.x category-based storage

### Version 2.8.0

- Logged-in subscribe form (readonly name/email, `user_id` linking), WP User column, CSV `user_id` export

### Version 2.7.0

- All mail via WordPress mail; tested up to WordPress 6.8
