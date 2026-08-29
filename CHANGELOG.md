# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versions track the [modufolio/appkit](https://github.com/modufolio/appkit) core
release they are built against.

## [0.14.0] - 2026-08-29

First tagged release.

### Added

- Initial RoadRunner runtime for AppKit: explicit worker loop in `worker.php`,
  `RoadRunnerApp` with per-request state reset, and worker-safe sessions via
  `RoadRunnerApplicationState` (PHP session cookie emission handled on the
  PSR-7 response, session closed after every request).

### Fixed

- **Session cookie lost after login.** Logging in succeeded server-side but the
  client never received the migrated session id, so the next request bounced
  back to `/login`. The firewall regenerates the session id *after* the session
  has started (fixation protection), which the start-time `newSession` flag in
  `RoadRunnerApplicationState` could not see — under the classic PHP SAPI the
  engine emits that cookie automatically, so only the worker runtime was
  affected. `isNewSession()` now compares the live `session_id()` against the
  id the client sent, at call time, so `attachSessionCookie()` sends the
  `Set-Cookie` whenever the id changed mid-request. Stable sessions still get
  no redundant cookie.

### Added

- Redis session storage: set `SESSION_DRIVER=redis` (and `REDIS_URL`) to keep
  sessions in Redis instead of `var/sessions` — the recommended setup for
  multiple servers or containers, where any worker on any host can serve any
  request and sessions survive redeploys. Works with the `phpredis` extension
  or `predis/predis`; the connection is opened once per worker and reused.
  `file` remains the zero-dependency default.
- GitHub Actions CI: unit tests + PHPStan, plus a RoadRunner smoke job that
  boots the real server (single worker, file and Redis session matrix) and
  runs `tests/smoke/login-flow.sh` — an end-to-end check of session cookie
  emission, login-time session id migration, worker state isolation between
  requests, and logout. These paths only exist under the worker runtime and
  are invisible to unit tests.
- `status` plugin in `.rr.yaml`: health/readiness endpoints on `:2114` for
  load balancers, orchestrators and the CI readiness wait.
- Background jobs proof of concept on RoadRunner's first-party jobs plugin:
  an in-memory pipeline consumed by the same `worker.php` (branching on
  `RR_MODE`), `config/jobs.php` for task-name → handler wiring,
  `src/Jobs/` with `JobHandlerInterface` and a `PingHandler` example, and an
  `app:job:push` console command. The queue API will change once the layer
  becomes its own package — treat it as a demonstration, not a stable surface.

### Changed

- Renamed the package `modufolio/appkit-skeleton` → `modufolio/appkit-roadrunner`.
- Updated `modufolio/appkit` `^0.3` → `^0.13` and `modufolio/http` `^0.1` → `^0.2`.
- Consolidated container wiring: `config/factories.php` and
  `config/interfaces.php` merged into `config/services.php`.
- `REMEMBER_ME_SECRET` is now required; the README documents generating it
  during setup.
- `bin/rr` is no longer committed (platform-specific, ~59 MB); download it per
  environment with `vendor/bin/rr get-binary`.
