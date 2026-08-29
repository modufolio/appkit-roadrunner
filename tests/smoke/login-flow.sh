#!/usr/bin/env bash
#
# End-to-end smoke test against a running RoadRunner instance.
#
# Exercises the paths that only exist under a long-lived worker runtime and
# are invisible to unit tests: session cookie emission on the PSR-7 response,
# session id migration during login, and state isolation between requests on
# the same worker (run RoadRunner with http.pool.num_workers=1 to make the
# isolation checks deterministic).
#
# Usage: BASE_URL=http://127.0.0.1:8080 tests/smoke/login-flow.sh <email> <password>

set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
EMAIL="${1:?usage: login-flow.sh <email> <password>}"
PASSWORD="${2:?usage: login-flow.sh <email> <password>}"

WORK_DIR=$(mktemp -d)
JAR="$WORK_DIR/cookies.txt"
trap 'rm -rf "$WORK_DIR"' EXIT

fail() {
    echo "FAIL: $1" >&2
    exit 1
}

pass() {
    echo "ok: $1"
}

# ── 1. Login page renders and starts a session ──────────────────────────────
CSRF=$(curl -sf -c "$JAR" "$BASE_URL/login" | grep -o 'name="_csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
[ -n "$CSRF" ] || fail "no CSRF token on the login page"
SID_ANON=$(grep PHPSESSID "$JAR" | awk '{print $NF}')
[ -n "$SID_ANON" ] || fail "login page did not set a session cookie"
pass "login page renders with CSRF token and session cookie"

# ── 2. Login succeeds and the MIGRATED session id reaches the client ────────
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR" -c "$JAR" \
    -d "email=$EMAIL&password=$PASSWORD&_csrf_token=$CSRF" "$BASE_URL/login")
[ "$STATUS" = "302" ] || fail "login POST returned $STATUS, expected 302"
SID_AUTH=$(grep PHPSESSID "$JAR" | awk '{print $NF}')
[ -n "$SID_AUTH" ] || fail "no session cookie after login"
[ "$SID_AUTH" != "$SID_ANON" ] || fail "session id was not migrated on login (fixation risk), or the migrated id never reached the client"
pass "login migrates the session id and sends it to the client"

# ── 3. The migrated cookie authenticates ────────────────────────────────────
BODY=$(curl -sf -b "$JAR" "$BASE_URL/")
echo "$BODY" | grep -q "Signed in as $EMAIL" || fail "authenticated page does not show 'Signed in as $EMAIL'"
pass "authenticated page renders with the migrated cookie"

# ── 4. A stable session gets no redundant Set-Cookie ────────────────────────
if curl -sf -i -b "$JAR" "$BASE_URL/" | grep -qi "^set-cookie"; then
    fail "Set-Cookie sent on a stable session"
fi
pass "no redundant Set-Cookie on a stable session"

# ── 5. Worker state isolation: a cookieless request must be anonymous ───────
# With one worker this request reuses the process that just served an
# authenticated request — leaked auth state would show up right here.
STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/")
[ "$STATUS" = "302" ] || fail "cookieless request after an authenticated one returned $STATUS, expected 302 to /login — worker state leaked?"
pass "cookieless request on the same worker stays anonymous"

# ── 6. Logout invalidates the session ───────────────────────────────────────
LCSRF=$(curl -sf -b "$JAR" "$BASE_URL/" | grep -o 'name="_csrf_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR" -c "$JAR" \
    -d "_csrf_token=$LCSRF" "$BASE_URL/logout")
[ "$STATUS" = "302" ] || fail "logout POST returned $STATUS, expected 302"
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -b "$JAR" "$BASE_URL/")
[ "$STATUS" = "302" ] || fail "still authenticated after logout"
pass "logout invalidates the session"

echo "smoke test passed"
