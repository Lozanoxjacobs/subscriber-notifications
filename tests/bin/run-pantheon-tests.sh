#!/usr/bin/env bash
set -euo pipefail

SITE="${1:-subscriber-alerts.dev}"
PLUGIN_SRC="/Users/jackie/Desktop/cursor/Subscriber Notification Plugin/subscriber-notifications"
REMOTE="wp-content/plugins/subscriber-notifications"
SFTP_CMD=$(terminus connection:info "${SITE}" --field=sftp_command 2>/dev/null)
SSH_TARGET=$(echo "${SFTP_CMD}" | awk '{print $NF}')
REMOTE_PATH="code/${REMOTE}/"

echo "==> Rsync plugin to ${SITE}"
rsync -rlvz --delete --ipv4 -e "ssh -p 2222 -o StrictHostKeyChecking=accept-new" \
  "${PLUGIN_SRC}/" "${SSH_TARGET}:${REMOTE_PATH}"

echo "==> Drop plugin tables for greenfield schema"
terminus wp "${SITE}" -- db query "DROP TABLE IF EXISTS wp_subscriber_notifications_send_queue, wp_subscriber_notification_logs, wp_subscriber_notifications_queue, wp_subscriber_notifications;" 2>/dev/null || true

echo "==> Recreate schema via deactivate/activate"
terminus wp "${SITE}" -- plugin deactivate subscriber-notifications
terminus wp "${SITE}" -- plugin activate subscriber-notifications

echo "==> Schema tests"
terminus wp "${SITE}" -- eval-file "${REMOTE}/tests/integration/db-schema-tests.php"

echo "==> Smoke tests"
terminus wp "${SITE}" -- eval-file "${REMOTE}/tests/integration/e2e-smoke-tests.php"

echo "==> Preferences page tests"
terminus wp "${SITE}" -- eval-file "${REMOTE}/tests/integration/preferences-page-tests.php"

echo "==> Frontend pages tests (v3.7)"
terminus wp "${SITE}" -- eval-file "${REMOTE}/tests/integration/frontend-pages-tests.php"

echo "==> Item subscriptions tests (v3.8)"
terminus wp "${SITE}" -- eval-file "${REMOTE}/tests/integration/item-subscriptions-tests.php"

echo "==> Post subscribe display tests (v3.8)"
terminus wp "${SITE}" -- eval-file "${REMOTE}/tests/integration/post-subscribe-display-tests.php"

BASE_URL=$(terminus wp "${SITE}" -- option get siteurl 2>/dev/null | head -1 | tr -d '[:space:]')
TOKEN=$(terminus wp "${SITE}" -- db query "SELECT management_token FROM wp_subscriber_notifications WHERE email LIKE 'token-test-%' ORDER BY id DESC LIMIT 1" --skip-column-names 2>/dev/null | head -1 | tr -d '[:space:]')

if [[ -n "${TOKEN}" && -n "${BASE_URL}" ]]; then
  MANAGE_URL=$(terminus wp "${SITE}" -- eval "echo esc_url_raw(subscriber_notifications_get_preferences_page_url(array('token' => '${TOKEN}')));" 2>/dev/null | head -1 | tr -d '[:space:]')
  if [[ -n "${MANAGE_URL}" ]]; then
    echo "==> HTTP manage token tests"
    VALID_HTML=$(curl -sSL "${MANAGE_URL}")
    echo "${VALID_HTML}" | grep -q "Contact Information" && echo "PASS: B7 valid manage URL" || { echo "FAIL: B7 valid manage URL"; exit 1; }
    INVALID_HTML=$(curl -sSL "$(terminus wp "${SITE}" -- eval "echo esc_url_raw(subscriber_notifications_get_preferences_page_url(array('token' => 'invalidtoken123')));" 2>/dev/null | head -1 | tr -d '[:space:]')")
    echo "${INVALID_HTML}" | grep -qi "invalid" && echo "PASS: B7 invalid manage token rejected" || { echo "FAIL: B7 invalid manage token rejected"; exit 1; }
  else
    echo "SKIP: B7 HTTP manage token tests (preferences page not configured)"
  fi
fi

echo "==> B8 uninstall/reinstall (delete data on uninstall)"
terminus wp "${SITE}" -- eval ' $db = new SubscriberNotifications_Database(); $db->add_subscriber(array("name" => "B8 Seed", "email" => "b8-seed-" . wp_generate_password(8, false) . "@example.com")); '
terminus wp "${SITE}" -- option update subscriber_notifications_delete_data_on_uninstall 1

TABLES_BEFORE=$(terminus wp "${SITE}" -- db query "SHOW TABLES LIKE 'wp_subscriber_%'" --skip-column-names 2>/dev/null | grep -c 'wp_subscriber_' || true)
[[ "${TABLES_BEFORE}" -eq 4 ]] && echo "PASS: B8 four tables exist before uninstall" || { echo "FAIL: B8 four tables exist before uninstall (found ${TABLES_BEFORE})"; exit 1; }

terminus wp "${SITE}" -- plugin uninstall subscriber-notifications --deactivate --skip-delete

TABLES_AFTER=$(terminus wp "${SITE}" -- db query "SHOW TABLES LIKE 'wp_subscriber_%'" --skip-column-names 2>/dev/null | grep -c 'wp_subscriber_' || true)
[[ "${TABLES_AFTER}" -eq 0 ]] && echo "PASS: B8 tables removed on uninstall" || { echo "FAIL: B8 tables removed on uninstall (found ${TABLES_AFTER})"; exit 1; }

if terminus wp "${SITE}" -- option get subscriber_notifications_db_version >/dev/null 2>&1; then
  echo "FAIL: B8 db_version option removed on uninstall"
  exit 1
else
  echo "PASS: B8 db_version option removed on uninstall"
fi

terminus wp "${SITE}" -- plugin activate subscriber-notifications
terminus wp "${SITE}" -- eval-file "${REMOTE}/tests/integration/b8-post-reinstall-tests.php"

echo "==> All Pantheon tests passed"
