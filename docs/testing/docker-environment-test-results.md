# Docker Environment Test Results

**Tester:** Kenneth Ly
**Date:** 2026-10-12
**Covers:** SCRUM-35 (MySQL persistence), SCRUM-36 (healthcheck and startup readiness), SCRUM-37 (source mounting), PHP-to-MySQL connectivity
**Environment:** Windows 11 + Docker Desktop, Docker Compose v5.5.1, MySQL 8.0.46, PHP 8.2 (Apache)

## How to re-run

```bash
docker compose up -d
bash tests/environment/docker-environment-test.sh
```

The script prints PASS/FAIL for each check and exits non-zero if any check fails. It restarts containers but never deletes the database volume, and it removes any test data it creates. It also runs in GitHub Actions on every pull request into `main` (step "Docker environment tests").

The database connection is also covered by PHPUnit: `tests/Integration/DatabaseConnectionTest.php`.

## Summary

| Area | Checks | Result |
|---|---|---|
| SCRUM-35 Persistence | 4 | ✅ Pass |
| SCRUM-36 Healthcheck / startup readiness | 4 (+1 manual, 36.5) | ✅ Pass after fix (defect D1) |
| SCRUM-37 Source mounting | 5 | ✅ Pass |
| PHP-to-MySQL connectivity | 8 | ✅ Pass after fix (defect D2) |
| **Total** | **21** | **21 passed, 0 failed** |

## Defects found

### D1: Database reported "healthy" before it was ready (SCRUM-36), severity: Major
- **Steps:** Start MySQL with a fresh (empty) volume using the original healthcheck (`mysqladmin ping -h localhost`). Poll the health status, and as soon as it is `healthy`, try to connect over TCP the way PHP does.
- **Expected:** `healthy` only once the schema and seed data are loaded and the server accepts connections.
- **Actual:** reported `healthy` after 11 s, while MySQL was still running its temporary setup server. A TCP connection failed with `ERROR 2003: Can't connect to MySQL server on '127.0.0.1:3306'`, and the root password was not even set yet (`ERROR 1045: Access denied`). The real server was ready about 10 s later.
- **Cause:** `mysqladmin ping` over the local socket also answers for the temporary setup server, and it counts "access denied" as alive. The web container could start, and serve errors, before the database was usable. This is likely why CI needed an extra "Wait for MySQL TCP connection" step.
- **Fix:** `docker-compose.yml` healthcheck now runs `SELECT 1` over TCP (`-h 127.0.0.1`) as the app user, plus a 60 s `start_period` for the first import.
- **Verified:** with a fresh volume, the db became `healthy` after 23 s, and at that moment all 21 tables and the seed users were queryable.

### D2: Database errors shown to visitors during an outage (Connectivity), severity: Major (security)
- **Steps:** Stop the database (`docker compose stop db`), then open `/pages/search.php?q=keyboard`.
- **Expected:** a server error status and a generic message. No internal details.
- **Actual:** HTTP **200** and the page showed `Connection failed: SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for db failed: No address associated with hostname`. This exposes internal details (database host, driver errors; a wrong password would also reveal the database user name).
- **Fix:** `db_connect.php` still logs the full error for developers, but now returns HTTP **503** with "OmniBuy is temporarily unavailable. Please try again in a few minutes." Command-line scripts (tests) exit with code 1 instead of 0, so a connection failure can no longer look like a passing test.
- **Verified:** during the outage the page returns 503 with no database details. After `docker compose start db` the site recovers without a restart.

## Detailed results

### SCRUM-35: MySQL persistence across container recreation

| # | Steps | Expected | Actual | Result |
|---|---|---|---|---|
| 35.1 | Insert a uniquely named test row into `categories` | Row exists | Row exists | ✅ |
| 35.2 | `docker compose down` (no `-v`), then `docker compose up -d`; query the row | Row still exists | Row still exists | ✅ |
| 35.3 | Compare the volume mounted at `/var/lib/mysql` before and after | Same named volume | `491_mysql_data` both times | ✅ |
| 35.4 | `docker compose up -d --no-deps --force-recreate db`; query the row | Row still exists | Row still exists | ✅ |

Note: `docker compose down -v` deletes the volume by design (documented in `DOCKER_SETUP.md`); the schema and seed data are then re-imported on the next start.

### SCRUM-36: Database healthcheck and startup readiness

| # | Steps | Expected | Actual | Result |
|---|---|---|---|---|
| 36.1 | Check `docker compose config` for the web service | `depends_on: db: condition: service_healthy` | Present | ✅ |
| 36.2 | `docker compose up -d` after `down`; compare event order in the output | `omnibuy-db Healthy` before `omnibuy-web Started` | db Healthy, then web Started | ✅ |
| 36.3 | Inspect the db healthcheck command | Real query over TCP | `SELECT 1` via `-h 127.0.0.1` | ✅ (after D1 fix) |
| 36.4 | Query as the app user the moment the db is healthy | Query succeeds | Query succeeds | ✅ |
| 36.5 | Fresh volume: poll health, query tables at the moment it is healthy (manual, see D1) | All tables and seed data present | 21 tables, 6 seed users | ✅ (after D1 fix) |

### SCRUM-37: Local development source mounting

| # | Steps | Expected | Actual | Result |
|---|---|---|---|---|
| 37.1 | Inspect the web container mounts | Project bind-mounted at `/var/www/html` | `bind` | ✅ |
| 37.2 | Create a new PHP file in `public/` on the host; request it | Served by the container | Served | ✅ |
| 37.3 | Edit the file on the host; request it again | New content, no rebuild/restart | New content | ✅ |
| 37.4 | Delete the file on the host; request it | 404 | 404 | ✅ |
| 37.5 | Compare the web container start time before and after | Unchanged (no restart) | Unchanged | ✅ |

### PHP-to-MySQL connectivity

| # | Steps | Expected | Actual | Result |
|---|---|---|---|---|
| C.1 | `printenv DB_HOST` in the web container | `db` (Compose service name, not localhost) | `db` | ✅ |
| C.2 | `require db_connect.php`; run `SELECT 1` | `1` | `1` | ✅ |
| C.3 | `SELECT CURRENT_USER()` | App user, not root | `omnibuy_user` | ✅ |
| C.4 | `SELECT @@character_set_connection` | `utf8mb4` | `utf8mb4` | ✅ |
| C.5 | Request `/pages/search.php?q=keyboard` | HTTP 200 | 200 | ✅ |
| C.6 | Stop the db; request the same page | HTTP 5xx | 503 | ✅ (after D2 fix; was 200) |
| C.7 | Same request: check the page body | No database details | Generic message only | ✅ (after D2 fix) |
| C.8 | Start the db; request the page again | HTTP 200 without restarting web | 200 | ✅ |
