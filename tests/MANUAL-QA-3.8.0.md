# Manual QA — Subscriber Notifications 3.8.0

Use after deploying 3.8.0 and completing the Pantheon dev reset steps in the implementation plan (section 8).

## Admin

- [ ] Content Types: enable single-item for a post type without enabling global form — save persists
- [ ] Meta box shows two checkboxes; CB1 stays checked after save; CB2 is unchecked after save
- [ ] CB1 only — post appears in next digest test notification; no item emails sent
- [ ] CB2 only — item subscribers receive update email; post not in digest feed
- [ ] Both checked — item email immediate + post in digest
- [ ] Typo fix: second save without CB2 — no second item email
- [ ] 11+ item subscribers — save completes quickly; admin notice says background send; emails arrive within ~1–2 min
- [ ] Email Logs filter shows Item subscription / Item update types
- [ ] Settings → Email Templates: item templates editable

## Frontend — `[subscriber_notifications_post_subscribe]`

- [ ] Shortcode on published page renders subscribe UI
- [ ] Shortcode empty when single-item disabled for type
- [ ] Shortcode empty on configured subscribe and preferences pages (even via Pages block template)
- [ ] Logged-in: Subscribe → Manage link works; subscribed state on reload
- [ ] Guest: subscribe → success state same session; no Manage button
- [ ] Guest: CAPTCHA required when configured
- [ ] Guest: return later — subscribe form again (expected)

### Post subscribe shortcode attributes (block templates)

- [ ] `include="slug-a,slug-b"` — widget only on listed slugs
- [ ] `exclude="sitemap"` — widget hidden on listed slugs
- [ ] `include_terms` / `exclude_terms` — correct visibility for taxonomy terms (e.g. project status)
- [ ] Copy overrides (`heading`, `description`, `button`) render on subscribe form
- [ ] Subscribed-state copy overrides render after subscribe (same session AJAX)
- [ ] Subscribe button matches main form styling (no extra padding from template `wpautop`)

## Preferences page

- [ ] Topic digests accordions + flat Specific page updates list
- [ ] Frequency help text visible
- [ ] Uncheck item → save → item removed from preferences
- [ ] Unpublish subscribed page — item still listed as unavailable; no item email on admin notify
- [ ] Republish — item email works again

## Email content

- [ ] Item confirmation: correct subject, post link, manage preferences; no delivery frequency
- [ ] Item update: “updated on …” link text when CB2 used
- [ ] Global form signup still sends Welcome, not item confirmation
- [ ] Open/click tracking works on item emails

## Edge cases

- [ ] Subscriber with item + topic gets item email + digest when both meta boxes used
- [ ] Single-item disabled for type — widget hidden; existing subs do not receive item emails
