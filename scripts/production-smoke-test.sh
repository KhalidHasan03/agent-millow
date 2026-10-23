#!/usr/bin/env bash
#
# Agent Millow post-deployment smoke test.
#
# This script is intentionally read-mostly. It creates a conversation, a
# message, and a memory owned by the smoke-test user, then deletes the memory
# and conversation during cleanup. The low-risk echo tool call and its audit
# records remain. It does not call a model provider (generate=false), so it
# incurs no provider cost.
#
# Required environment:
#   SMOKE_BASE_URL   HTTPS origin, for example https://app.example.com
#   SMOKE_EMAIL      Existing smoke-test account email
#   SMOKE_PASSWORD   Password for that account (keep it in a secret manager)
#
# Optional approval-flow check:
#   SMOKE_APPROVAL_TOOL   Name of an existing high-risk tool
#   SMOKE_APPROVAL_INPUT  JSON object accepted by that tool (default {})
#
# The positive approval path is skipped when SMOKE_APPROVAL_TOOL is unset. In
# both cases the script verifies that approving an already-completed low-risk
# call is rejected with HTTP 409.
#
# Run from a trusted operator workstation, not from a public CI job. Do not
# paste the token, password, or full response bodies into tickets or logs.

set -euo pipefail
umask 077

fail() {
  printf 'FAIL: %s\n' "$1" >&2
  exit 1
}

pass() {
  printf 'PASS: %s\n' "$1"
}

assert_eq() {
  local actual="$1"
  local expected="$2"
  local label="$3"

  if [ "$actual" != "$expected" ]; then
    fail "$label (expected '$expected', got '$actual')"
  fi

  pass "$label"
}

: "${SMOKE_BASE_URL:?Set SMOKE_BASE_URL to the deployed HTTPS origin.}"
: "${SMOKE_EMAIL:?Set SMOKE_EMAIL to an existing smoke-test account.}"
: "${SMOKE_PASSWORD:?Set SMOKE_PASSWORD in the environment or a secret manager.}"

command -v curl >/dev/null 2>&1 || fail "curl is required."
command -v php >/dev/null 2>&1 || fail "php is required for JSON parsing."

BASE_URL="${SMOKE_BASE_URL%/}"

case "$BASE_URL" in
  https://*) ;;
  http://127.0.0.1|http://127.0.0.1:*|http://localhost|http://localhost:*) ;;
  *) fail "SMOKE_BASE_URL must use https except for loopback testing." ;;
esac

TMP_DIR="$(mktemp -d)"
BODY="$TMP_DIR/body.json"
CURL_CONFIG="$TMP_DIR/curl.conf"
CONVERSATION_ID=""
MEMORY_ID=""
AUTH_READY=0

cleanup() {
  if [ "$AUTH_READY" = "1" ] && [ -f "$CURL_CONFIG" ]; then
    if [ -n "$MEMORY_ID" ]; then
      curl -sS --max-time 20 --config "$CURL_CONFIG" -o /dev/null \
        -X DELETE "${BASE_URL}/api/v1/memories/${MEMORY_ID}" >/dev/null 2>&1 || true
    fi
    if [ -n "$CONVERSATION_ID" ]; then
      curl -sS --max-time 20 --config "$CURL_CONFIG" -o /dev/null \
        -X DELETE "${BASE_URL}/api/v1/conversations/${CONVERSATION_ID}" >/dev/null 2>&1 || true
    fi
  fi

  rm -rf "$TMP_DIR"
}

trap cleanup EXIT

request() {
  local method="$1"
  local path="$2"
  local payload="$3"
  local authenticated="$4"
  local -a args=(
    --silent
    --show-error
    --connect-timeout 10
    --max-time 60
    --output "$BODY"
    --write-out "%{http_code}"
    --request "$method"
    --header "Accept: application/json"
  )

  if [ -n "$payload" ]; then
    args+=(--header "Content-Type: application/json" --data-binary @-)
  fi

  if [ "$authenticated" = "1" ]; then
    args+=(--config "$CURL_CONFIG")
  fi

  if [ -n "$payload" ]; then
    printf '%s' "$payload" | curl "${args[@]}" "${BASE_URL}${path}"
  else
    curl "${args[@]}" "${BASE_URL}${path}"
  fi
}

request_json() {
  local method="$1"
  local path="$2"
  local expected="$3"
  local payload="${4:-}"
  local status

  status="$(request "$method" "$path" "$payload" 0)"

  if [ "$status" != "$expected" ]; then
    printf 'Response body: %s\n' "$(tr -d '\n' < "$BODY" | cut -c1-500)" >&2
    fail "$method $path returned HTTP $status (expected $expected)"
  fi

  HTTP_STATUS="$status"
}

request_auth() {
  local method="$1"
  local path="$2"
  local expected="$3"
  local payload="${4:-}"
  local status

  status="$(request "$method" "$path" "$payload" 1)"

  if [ "$status" != "$expected" ]; then
    printf 'Response body: %s\n' "$(tr -d '\n' < "$BODY" | cut -c1-500)" >&2
    fail "$method $path returned HTTP $status (expected $expected)"
  fi

  HTTP_STATUS="$status"
}

json_field() {
  local field="$1"

  php -r '
    $data = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($data)) {
      exit(1);
    }
    foreach (explode(".", $argv[1]) as $key) {
      if (!is_array($data) || !array_key_exists($key, $data)) {
        exit(1);
      }
      $data = $data[$key];
    }
    if (is_bool($data)) {
      echo $data ? "true" : "false";
    } elseif (is_scalar($data) || $data === null) {
      echo $data;
    } else {
      echo json_encode($data, JSON_THROW_ON_ERROR);
    }
  ' "$field" < "$BODY"
}

require_numeric_id() {
  local value="$1"
  local label="$2"

  case "$value" in
    ''|*[!0-9]*) fail "$label did not return a numeric id." ;;
  esac
}

printf 'Agent Millow production smoke test: %s\n' "$BASE_URL"

request_json GET /up 200 ""
pass "liveness endpoint responds"

request_json GET /api/v1/health 200 ""
assert_eq "$(json_field status)" "ok" "readiness status is ok"
assert_eq "$(json_field checks.database.status)" "ok" "database readiness check"
assert_eq "$(json_field checks.cache.status)" "ok" "cache readiness check"
assert_eq "$(json_field checks.queue.status)" "ok" "queue readiness check"

LOGIN_BODY="$(php -r '
  echo json_encode([
    "email" => getenv("SMOKE_EMAIL"),
    "password" => getenv("SMOKE_PASSWORD"),
    "device_name" => "production-smoke-test",
    "issue_token" => true,
  ], JSON_THROW_ON_ERROR);
')"

request_json POST /api/v1/auth/login 200 "$LOGIN_BODY"
LOGIN_BODY=""
TOKEN="$(json_field token)"

[ -n "$TOKEN" ] || fail "login did not return an API token."
{
  printf 'header = "Authorization: Bearer '
  printf '%s' "$TOKEN"
  printf '"\n'
} > "$CURL_CONFIG"
TOKEN=""
AUTH_READY=1
pass "authentication issued a bearer token"

request_auth GET /api/v1/auth/user 200 ""
assert_eq "$(json_field data.email)" "$(printf '%s' "$SMOKE_EMAIL" | tr '[:upper:]' '[:lower:]')" "authenticated user matches smoke account"

CONVERSATION_BODY="$(php -r '
  echo json_encode([
    "title" => "production smoke " . gmdate("Y-m-d\\TH:i:s\\Z"),
  ], JSON_THROW_ON_ERROR);
')"

request_auth POST /api/v1/conversations 201 "$CONVERSATION_BODY"
CONVERSATION_BODY=""
CONVERSATION_ID="$(json_field data.id)"
require_numeric_id "$CONVERSATION_ID" "conversation"
pass "conversation created"

MESSAGE_BODY="$(php -r '
  echo json_encode([
    "content" => "production smoke test message",
    "client_message_id" => "smoke-" . bin2hex(random_bytes(8)),
    "generate" => false,
  ], JSON_THROW_ON_ERROR);
')"

request_auth POST "/api/v1/conversations/${CONVERSATION_ID}/messages" 201 "$MESSAGE_BODY"
MESSAGE_BODY=""
MESSAGE_ID="$(json_field data.user_message.id)"
require_numeric_id "$MESSAGE_ID" "message"
pass "conversation message append (generation disabled, no provider call)"

MEMORY_BODY="$(php -r '
  echo json_encode([
    "title" => "production smoke memory",
    "content" => "temporary smoke-test memory; safe to delete",
    "category" => "context",
    "source" => "user",
  ], JSON_THROW_ON_ERROR);
')"

request_auth POST /api/v1/memories 201 "$MEMORY_BODY"
MEMORY_BODY=""
MEMORY_ID="$(json_field data.id)"
require_numeric_id "$MEMORY_ID" "memory"

request_auth GET "/api/v1/memories/${MEMORY_ID}" 200 ""
assert_eq "$(json_field data.title)" "production smoke memory" "memory inspection"
request_auth DELETE "/api/v1/memories/${MEMORY_ID}" 204 ""
MEMORY_ID=""
pass "memory create, inspect, and cleanup"

request_auth GET /api/v1/tools 200 ""
printf '      available tools checked\n'
request_auth POST /api/v1/tools/call 200 "$(php -r '
  echo json_encode([
    "tool" => "echo",
    "input" => ["message" => "production smoke tool"],
  ], JSON_THROW_ON_ERROR);
')"
assert_eq "$(json_field status)" "succeeded" "low-risk tool execution"
TOOL_CALL_ID="$(json_field tool_call_id)"
require_numeric_id "$TOOL_CALL_ID" "tool call"
assert_eq "$(json_field result.echo)" "production smoke tool" "tool output"
pass "low-risk tool execution"

request_auth POST "/api/v1/tools/calls/${TOOL_CALL_ID}/approve" 409 ""
pass "approving an already-completed low-risk call is rejected"

if [ -n "${SMOKE_APPROVAL_TOOL:-}" ]; then
  APPROVAL_INPUT_JSON="${SMOKE_APPROVAL_INPUT:-}"
  if [ -z "$APPROVAL_INPUT_JSON" ]; then
    APPROVAL_INPUT_JSON='{}'
  fi
  APPROVAL_BODY="$(SMOKE_TOOL="$SMOKE_APPROVAL_TOOL" SMOKE_INPUT="$APPROVAL_INPUT_JSON" php -r '
    $input = json_decode(getenv("SMOKE_INPUT"), true);
    if (!is_array($input)) {
      fwrite(STDERR, "SMOKE_APPROVAL_INPUT must be a JSON object.\n");
      exit(1);
    }
    echo json_encode([
      "tool" => getenv("SMOKE_TOOL"),
      "input" => $input,
    ], JSON_THROW_ON_ERROR);
  ')"
  request_auth POST /api/v1/tools/call 200 "$APPROVAL_BODY"
  APPROVAL_BODY=""
  assert_eq "$(json_field status)" "pending_approval" "high-risk tool requires approval"
  APPROVAL_ID="$(json_field tool_call_id)"
  require_numeric_id "$APPROVAL_ID" "approval tool call"
  request_auth POST "/api/v1/tools/calls/${APPROVAL_ID}/reject" 200 '{"reason":"production smoke test"}'
  assert_eq "$(json_field status)" "rejected" "approval rejection"
  request_auth GET "/api/v1/tools/calls/${APPROVAL_ID}" 200 ""
  assert_eq "$(json_field data.status)" "rejected" "rejected tool call state"
  pass "approval flow propose, pending, and reject"
else
  printf 'SKIP: positive approval flow (set SMOKE_APPROVAL_TOOL and SMOKE_APPROVAL_INPUT to exercise it).\n'
fi

request_auth GET "/api/v1/audit?event=tool.succeeded&per_page=5" 200 ""
AUDIT_COUNT="$(php -r '
  $data = json_decode(stream_get_contents(STDIN), true);
  echo is_array($data["data"] ?? null) ? count($data["data"]) : 0;
' < "$BODY")"

[ "$AUDIT_COUNT" -gt 0 ] || fail "audit history did not contain tool.succeeded."
pass "audit history contains the tool execution"

request_auth DELETE "/api/v1/conversations/${CONVERSATION_ID}" 204 ""
CONVERSATION_ID=""
pass "conversation cleanup"

request_auth POST /api/v1/auth/logout 204 ""
AUTH_READY=0
pass "token revoked"

printf 'Smoke test completed successfully. Tool-call and audit records remain by design.\n'
