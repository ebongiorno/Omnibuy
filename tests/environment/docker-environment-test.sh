#!/usr/bin/env bash
#
# Docker environment tests for OmniBuy.
#
#   SCRUM-35  MySQL data persists across container recreation
#   SCRUM-36  Database healthcheck and startup readiness
#   SCRUM-37  Local source mounting (edits show up without a rebuild)
#   PHP-to-MySQL connectivity, including behavior during a database outage
#
# Usage (from the repo root, with the stack running):
#   docker compose up -d
#   bash tests/environment/docker-environment-test.sh
#
# The script restarts containers but never deletes the database volume.
# Any data it creates is removed before it exits. Exits 1 if any check fails.

set -u

cd "$(dirname "$0")/../.."
export MSYS_NO_PATHCONV=1  # stop Git Bash on Windows from rewriting /paths

APP_URL="${APP_URL:-http://localhost:8080}"
DB_CONTAINER=omnibuy-db
WEB_CONTAINER=omnibuy-web

passed=0
failed=0
marker=""
mount_file=""

pass() { echo "  PASS: $1"; passed=$((passed + 1)); }
fail() {
    echo "  FAIL: $1"
    if [ -n "${2:-}" ]; then echo "        $2"; fi
    failed=$((failed + 1))
}

cleanup() {
    if [ -n "$mount_file" ]; then rm -f "$mount_file"; fi
    if [ -n "$marker" ]; then
        db_query "DELETE FROM categories WHERE category_name = '$marker'" >/dev/null 2>&1
    fi
}
trap cleanup EXIT

# Run SQL as the app user over TCP, the same way PHP connects.
db_query() {
    docker compose exec -T db sh -c \
        'mysql --protocol=TCP -h 127.0.0.1 -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -Nse "$1" "$MYSQL_DATABASE" 2>/dev/null' \
        sh "$1"
}

health() { docker inspect -f '{{.State.Health.Status}}' "$DB_CONTAINER" 2>/dev/null; }

wait_for_healthy() {
    for _ in $(seq 1 60); do
        [ "$(health)" = "healthy" ] && return 0
        sleep 2
    done
    return 1
}

http_status() { curl -s -o /dev/null --max-time 30 -w '%{http_code}' "$1"; }

wait_for_web() {
    for _ in $(seq 1 30); do
        [ "$(http_status "$APP_URL/")" = "200" ] && return 0
        sleep 2
    done
    return 1
}

db_volume() {
    docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Name}}{{end}}{{end}}' "$DB_CONTAINER"
}

echo "OmniBuy Docker environment tests"
echo "App URL: $APP_URL"

if ! wait_for_healthy || ! wait_for_web; then
    echo "The stack is not running or not healthy. Start it with: docker compose up -d"
    exit 1
fi

# ---------------------------------------------------------------------------
echo
echo "[SCRUM-35 / SCRUM-36] Persistence and startup readiness across a restart"

if docker compose config | grep -q "condition: service_healthy"; then
    pass "web waits for the database to be healthy (depends_on: service_healthy)"
else
    fail "web waits for the database to be healthy (depends_on: service_healthy)"
fi


marker="envtest-$(date +%s)-$RANDOM"
volume_before=$(db_volume)
db_query "INSERT INTO categories (category_name) VALUES ('$marker')" >/dev/null

if [ "$(db_query "SELECT COUNT(*) FROM categories WHERE category_name = '$marker'")" = "1" ]; then
    pass "test row written before restart"
else
    fail "test row written before restart"
fi

echo "  ... docker compose down (volume kept), then up"
docker compose down >/dev/null 2>&1
up_output=$(docker compose up -d 2>&1)

db_healthy_line=$(echo "$up_output" | grep -n "$DB_CONTAINER Healthy" | head -1 | cut -d: -f1)
web_started_line=$(echo "$up_output" | grep -n "$WEB_CONTAINER Started" | head -1 | cut -d: -f1)
if [ -n "$db_healthy_line" ] && [ -n "$web_started_line" ] && [ "$db_healthy_line" -lt "$web_started_line" ]; then
    pass "on startup, web starts only after the database is healthy"
else
    fail "on startup, web starts only after the database is healthy" "compose output: $(echo "$up_output" | tr '\n' '|')"
fi

# Checked after the restart so it reflects the current docker-compose.yml.
healthcheck=$(docker inspect -f '{{join .Config.Healthcheck.Test " "}}' "$DB_CONTAINER")
if [[ "$healthcheck" == *"127.0.0.1"* && "$healthcheck" == *"SELECT 1"* ]]; then
    pass "healthcheck runs a real query over TCP"
else
    fail "healthcheck runs a real query over TCP" "configured: $healthcheck"
fi

# The moment compose reports the db healthy, real queries must work.
if [ "$(db_query "SELECT COUNT(*) FROM users" | grep -c '^[0-9]')" = "1" ]; then
    pass "database accepts queries as soon as it is reported healthy"
else
    fail "database accepts queries as soon as it is reported healthy"
fi

if [ "$(db_query "SELECT COUNT(*) FROM categories WHERE category_name = '$marker'")" = "1" ]; then
    pass "test row still exists after docker compose down/up"
else
    fail "test row still exists after docker compose down/up"
fi

if [ "$(db_volume)" = "$volume_before" ] && [ -n "$volume_before" ]; then
    pass "database uses the same named volume ($volume_before)"
else
    fail "database uses the same named volume" "before: $volume_before, after: $(db_volume)"
fi

echo "  ... force-recreating only the db container"
docker compose up -d --no-deps --force-recreate db >/dev/null 2>&1
wait_for_healthy

if [ "$(db_query "SELECT COUNT(*) FROM categories WHERE category_name = '$marker'")" = "1" ]; then
    pass "test row still exists after the db container is recreated"
else
    fail "test row still exists after the db container is recreated"
fi

db_query "DELETE FROM categories WHERE category_name = '$marker'" >/dev/null
marker=""
wait_for_web

# ---------------------------------------------------------------------------
echo
echo "[SCRUM-37] Local development source mounting"

mount_type=$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html"}}{{.Type}}{{end}}{{end}}' "$WEB_CONTAINER")
if [ "$mount_type" = "bind" ]; then
    pass "project folder is bind-mounted at /var/www/html"
else
    fail "project folder is bind-mounted at /var/www/html" "mount type: '$mount_type'"
fi

web_started_at=$(docker inspect -f '{{.State.StartedAt}}' "$WEB_CONTAINER")
mount_name="__mount_check_$$_$RANDOM.php"
mount_file="public/$mount_name"

fetch_until() {  # fetch_until <url> <expected text>
    for _ in $(seq 1 10); do
        curl -s --max-time 10 "$1" | grep -q "$2" && return 0
        sleep 1
    done
    return 1
}

echo "<?php echo 'first-version';" > "$mount_file"
if fetch_until "$APP_URL/$mount_name" "first-version"; then
    pass "new file created on the host is served by the container"
else
    fail "new file created on the host is served by the container"
fi

echo "<?php echo 'second-version';" > "$mount_file"
if fetch_until "$APP_URL/$mount_name" "second-version"; then
    pass "edit on the host shows up without a rebuild or restart"
else
    fail "edit on the host shows up without a rebuild or restart"
fi

rm -f "$mount_file"
mount_file=""
sleep 1
if [ "$(http_status "$APP_URL/$mount_name")" = "404" ]; then
    pass "file deleted on the host is gone from the container"
else
    fail "file deleted on the host is gone from the container"
fi

if [ "$(docker inspect -f '{{.State.StartedAt}}' "$WEB_CONTAINER")" = "$web_started_at" ]; then
    pass "web container was not restarted during the edits"
else
    fail "web container was not restarted during the edits"
fi

# ---------------------------------------------------------------------------
echo
echo "[Connectivity] PHP to MySQL"

db_host=$(docker compose exec -T web printenv DB_HOST | tr -d '\r')
if [ "$db_host" = "db" ]; then
    pass "PHP connects using the Compose service name (DB_HOST=db)"
else
    fail "PHP connects using the Compose service name (DB_HOST=db)" "DB_HOST=$db_host"
fi

php_check=$(docker compose exec -T web php -r '
    require "db_connect.php";
    echo $pdo->query("SELECT 1")->fetchColumn(), "|";
    echo $pdo->query("SELECT SUBSTRING_INDEX(CURRENT_USER(), \"@\", 1)")->fetchColumn(), "|";
    echo $pdo->query("SELECT @@character_set_connection")->fetchColumn();
' 2>&1)
app_user=$(docker compose exec -T web printenv DB_USER | tr -d '\r')

if [ "$(echo "$php_check" | cut -d'|' -f1)" = "1" ]; then
    pass "db_connect.php connects and runs a query"
else
    fail "db_connect.php connects and runs a query" "output: $php_check"
fi

if [ "$(echo "$php_check" | cut -d'|' -f2)" = "$app_user" ]; then
    pass "PHP connects as the app user ($app_user), not root"
else
    fail "PHP connects as the app user, not root" "output: $php_check"
fi

if [ "$(echo "$php_check" | cut -d'|' -f3)" = "utf8mb4" ]; then
    pass "connection uses the utf8mb4 character set"
else
    fail "connection uses the utf8mb4 character set" "output: $php_check"
fi

if [ "$(http_status "$APP_URL/pages/search.php?q=keyboard")" = "200" ]; then
    pass "a database-backed page loads (search)"
else
    fail "a database-backed page loads (search)"
fi

echo "  ... stopping the database to simulate an outage"
docker compose stop db >/dev/null 2>&1

outage_body=$(curl -s --max-time 60 -w '\n%{http_code}' "$APP_URL/pages/search.php?q=keyboard")
outage_status=$(echo "$outage_body" | tail -1)

if [ "$outage_status" -ge 500 ] 2>/dev/null; then
    pass "during an outage the page returns a server error status ($outage_status)"
else
    fail "during an outage the page returns a server error status" "got HTTP $outage_status"
fi

if echo "$outage_body" | grep -qiE "SQLSTATE|PDOException|getaddrinfo|Access denied|Connection failed: |$app_user"; then
    fail "during an outage no database details are shown to the user" \
        "page showed: $(echo "$outage_body" | grep -iE -m1 "SQLSTATE|PDOException|getaddrinfo|Access denied|Connection failed" | head -c 200)"
else
    pass "during an outage no database details are shown to the user"
fi

echo "  ... starting the database again"
docker compose start db >/dev/null 2>&1

if wait_for_healthy && [ "$(http_status "$APP_URL/pages/search.php?q=keyboard")" = "200" ]; then
    pass "site recovers by itself once the database is back"
else
    fail "site recovers by itself once the database is back"
fi

# ---------------------------------------------------------------------------
echo
echo "$passed passed, $failed failed"
[ "$failed" -eq 0 ]
