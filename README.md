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
- **Email Logs** - Track all email activity with detailed analytics
- **Content Types** - Enable public post types and taxonomies, term display rules (all / children of / include / exclude), and form labels
- **Settings** - Scheduling, CAPTCHA, email templates, Email Design branding, and general options (test email, hide empty terms on form, delete on uninstall)
- **Test email** - Send a test message to verify mail delivery (`wp_mail()`)
- **Migration Tools** - Convert existing notifications to recurring format

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
5. **Delivery** — Mail is sent through WordPress (use SMTP or a mail plugin on your host if needed). Opens and clicks can be logged when tracking is enabled.

Posts can be marked **Notify subscribers** in the editor; that sets feed meta so digests pick them up on the next scheduled run.

## Shortcodes

Use in notification **subject** and **body**, welcome/preference emails, and global header/footer (Email Design).

| Shortcode | Use |
|-----------|-----|
| `[subscriber_name]` | Subscriber name |
| `[subscriber_email]` | Subscriber email |
| `[delivery_frequency]` | Daily / weekly / monthly label |
| `[selected_subscriptions]` | Formatted list of all selections (HTML in body; use `format="plain"` in **subject** only) |
| `[selected_terms post_type="…" taxonomy="…"]` | Comma-separated term names for one taxonomy |
| `[site_title]` | Site name |
| `[manage_preferences_link]` | Preferences URL (optional `text="…"`) |
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
1. Edit any post in an enabled content type
2. Check **Notify subscribers** in the meta box (includes the post in feed-flagged digests; does not send an immediate blast)
3. Save — the post is eligible for the next scheduled notification that matches subscriber preferences

## Data storage

The plugin stores subscribers, scheduled notifications, and email logs in the WordPress database. Data is created when the plugin is activated. You can optionally delete all plugin data on uninstall under **Settings > General**.

## Security and performance

Nonces, capability checks (`manage_options`), sanitized input, optional reCAPTCHA, rate limiting, and prepared SQL. Digests run on WordPress cron with batched sends for large lists; term lists are cached where appropriate.

## Analytics

- **Open Tracking** - Track email opens with tracking pixels
- **Click Tracking** - Track link clicks
- **Engagement Metrics** - Per-subscriber engagement statistics
- **Term performance** - See which configured terms perform best
- **Daily Statistics** - Track performance over time

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

## Support

For support and feature requests, please contact your site administrator or the plugin vendor.

## Changelog

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
