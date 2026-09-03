#!/usr/bin/env bash
set -euo pipefail

PORT="${PORT:-8080}"
BASE_URL="http://127.0.0.1:${PORT}"
TOKEN="${SNIPPEN_SMS_API_TOKEN:-test-integration-token}"

echo "Performing healthcheck against ${BASE_URL}..."

# 1. Verify WordPress responds
WP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/" || echo "failed")
if [ "$WP_STATUS" != "200" ] && [ "$WP_STATUS" != "301" ] && [ "$WP_STATUS" != "302" ]; then
    echo "Healthcheck FAILED: WordPress root returned HTTP ${WP_STATUS}"
    exit 1
fi
echo " - WordPress HTTP root: OK (HTTP ${WP_STATUS})"

# 2. Verify SMS outbox endpoint responds
SMS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer ${TOKEN}" "${BASE_URL}/wp-json/snippen/v1/sms/outbox" || echo "failed")
if [ "$SMS_STATUS" != "200" ]; then
    if [ "$SMS_STATUS" = "401" ] || [ "$SMS_STATUS" = "403" ]; then
        echo " - SMS outbox endpoint: ACTIVE (HTTP ${SMS_STATUS} - auth required/guard active)"
    else
        echo "Healthcheck FAILED: SMS outbox endpoint returned HTTP ${SMS_STATUS}"
        exit 1
    fi
else
    echo " - SMS outbox endpoint: OK (HTTP ${SMS_STATUS} authenticated)"
fi

echo "Healthcheck PASSED: All services operational."
exit 0
