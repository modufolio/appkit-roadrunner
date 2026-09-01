# AppKit RoadRunner

A starting point for [modufolio/appkit](https://github.com/modufolio/appkit)
applications running on [RoadRunner](https://roadrunner.dev) — long-lived PHP
workers, worker-safe sessions, and a proof-of-concept jobs pipeline.

## Documentation

Full framework documentation lives in the AppKit repository:
**[modufolio/appkit/docs](https://github.com/modufolio/appkit/tree/main/docs)**.

Useful starting points:

- [Getting started](https://github.com/modufolio/appkit/blob/main/docs/getting-started.md)
- [Controllers](https://github.com/modufolio/appkit/blob/main/docs/controllers.md) · [Routing](https://github.com/modufolio/appkit/blob/main/docs/routing.md) · [Templates](https://github.com/modufolio/appkit/blob/main/docs/templates.md)
- [Database](https://github.com/modufolio/appkit/blob/main/docs/database.md) · [Forms](https://github.com/modufolio/appkit/blob/main/docs/forms.md) · [File uploads](https://github.com/modufolio/appkit/blob/main/docs/file-uploads.md)
- [Security](https://github.com/modufolio/appkit/blob/main/docs/security.md) · [Authenticators](https://github.com/modufolio/appkit/blob/main/docs/authenticators.md)
- [Configuration](https://github.com/modufolio/appkit/blob/main/docs/configuration.md) · [Dependency injection](https://github.com/modufolio/appkit/blob/main/docs/dependency-injection.md) · [Console](https://github.com/modufolio/appkit/blob/main/docs/console.md)
- [Testing](https://github.com/modufolio/appkit/blob/main/docs/testing.md) · [Deployment](https://github.com/modufolio/appkit/blob/main/docs/deployment.md)

## What's in the box

- A single `HomeController` rendering a plain PHP template
- A `User` entity + `UserRepository` wired into the security firewall
- Form-login authenticator on `/login`
- SQLite via Doctrine ORM + Doctrine Migrations
- [RoadRunner](https://roadrunner.dev) application server with a worker-safe
  session runtime (`RoadRunnerApp` + `RoadRunnerApplicationState`), alongside
  the built-in PHP dev server
- Tailwind CSS 4 + esbuild for assets
- PHPUnit, PHPStan, PHP-CS-Fixer

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ (only if you want to compile assets)

## Getting started

```bash
composer install
npm install
cp .env.example .env

# Generate the remember-me cookie secret (required — requests fail without it)
php -r "echo 'REMEMBER_ME_SECRET=' . bin2hex(random_bytes(32)) . PHP_EOL;" >> .env

# Build assets
npm run build

# Create database schema (or use migrations)
php bin/console orm:schema-tool:create

# Create a user to sign in with (everything except /login requires auth)
php bin/console app:add-user you@example.com your-password

# Run the dev server
composer start
# → http://localhost:8000
```

## Running under RoadRunner

The app ships with a [RoadRunner](https://roadrunner.dev) runtime for long-lived
PHP workers, giving much higher throughput than the per-request PHP dev server.

The server binary is **not** committed (it is platform-specific and ~59 MB —
`bin/rr` is gitignored). Download it once per environment with the bundled CLI:

```bash
vendor/bin/rr get-binary   # writes bin/rr for your OS/arch
```

Then serve:

```bash
./bin/rr serve   # reads .rr.yaml → http://localhost:8080
```

How it fits together:

```
worker.php                       RoadRunner PSR-7 worker entrypoint
.rr.yaml                         Server config (workers, static files, logs)
src/RoadRunnerApp.php            Kernel subclass — attaches Set-Cookie on new sessions
src/RoadRunnerApplicationState.php   Worker-safe session + request state
```

Because workers are long-lived, the runtime uses a dedicated
`RoadRunnerApplicationState` that disables PHP's automatic session-cookie
handling and resets session globals between requests; `RoadRunnerApp` then
attaches `Set-Cookie` to the PSR-7 response on new sessions.

### Session storage

Sessions are stored in `var/sessions` by default — zero dependencies, right
for a single server. Set `SESSION_DRIVER=redis` (and `REDIS_URL`) to store
them in Redis instead:

```bash
SESSION_DRIVER=redis
REDIS_URL=redis://127.0.0.1:6379
```

Choose Redis as soon as you run more than one server or deploy in containers:
any worker on any host can then serve any request, and sessions survive
redeploys where a local `var/` does not. It also removes the per-session file
lock, so parallel requests from one client (AJAX bursts) no longer queue
behind each other — with the flip side that the Redis handler is lockless
(last write wins), so those parallel requests can overwrite each other's
session writes. Requires the `phpredis` extension or `predis/predis`; the
connection is opened once per worker and reused across requests.

### Background jobs (proof of concept)

This app also wires RoadRunner's first-party **jobs plugin** end to end, as
a proof of concept — the API here will change once the queue layer becomes its
own package, so treat it as a demonstration, not a stable surface.

One worker script serves both pools: `worker.php` branches on `RR_MODE` —
`http` runs the PSR-7 loop, `jobs` consumes tasks from the in-memory `local`
pipeline declared in `.rr.yaml` and routes them by task name to a handler
(`src/Jobs/`, mapped in `config/jobs.php`). No separate queue daemon:
RoadRunner pushes tasks into the same PHP worker pool it supervises.

Jobs are the application's own deferred work, so the jobs branch boots the
same `App` as the HTTP branch: handlers draw their dependencies from it in
`config/jobs.php`, and the worker calls `$app->reset()` after every task —
the same reset contract as the HTTP loop. (The console stays isolated from
the app container because it is repair tooling; jobs are not.)

Try it with the server running:

```bash
bin/console app:jobs:push ping '{"hello":"roadrunner"}'
tail var/log/jobs.log   # the ping handler's side effect
```

Tasks are pushed over the RPC socket (`tcp://127.0.0.1:6001`); unknown task
names are failed rather than silently dropped. Swapping `driver: memory` for
redis/amqp/sqs in `.rr.yaml` changes durability without touching PHP.

### Production notes

- Set **`APP_ENV=prod`** in `.env`. This enables the compiled route cache
  (`var/cache/router`) and the Doctrine metadata/query filesystem cache.
- `.rr.yaml` runs workers with **`XDEBUG_MODE=off`** — Xdebug loaded in
  long-lived workers roughly halves throughput, so keep it disabled there.
- `var/cache/*` is a build artifact. Clear it (or warm it during deploy)
  whenever routes or entities change, since prod caches don't auto-invalidate.
- Commit `composer.lock` for a deployable app so production installs the exact
  tested dependency versions.

## Project layout

```
worker.php            RoadRunner PSR-7 worker entrypoint
.rr.yaml              RoadRunner server config
bin/rr                RoadRunner binary (gitignored; `vendor/bin/rr get-binary`)
bin/console           CLI entrypoint
src/                  Application code (PSR-4: App\)
  App.php             Kernel subclass
  AppFactory.php      Boots the kernel (optional app class, e.g. RoadRunnerApp)
  RoadRunnerApp.php   Kernel subclass for the RoadRunner runtime
  RoadRunnerApplicationState.php  Worker-safe session + request state
  Command/            Console commands (app:add-user, app:jobs:push)
  Console/            ConsoleRunner — wires bin/console
  Controller/         HTTP controllers
  Entity/             Doctrine entities
  Jobs/               Task handlers for the jobs pool (proof of concept)
  Repository/         Doctrine repositories
config/               DI, routes, security, doctrine, …
  routes.php          Route loaders
  security.php        Firewalls + role hierarchy
  controllers.php     Controller → dependency map
  services.php        Application services (ServiceConfigurator)
  jobs.php            Task name → handler map for the jobs pool
  repositories.php    Repository → entity map
  authenticators.php  Authenticator factories
  doctrine.php        ORM connection + entity paths
  migrations.php      Doctrine Migrations config
  console.php         Bootstrap for bin/console
database/migrations/  Doctrine migration classes
public/               Web root (index.php, compiled assets)
resources/views/      PHP templates + layouts
assets/               Source CSS/JS (compiled into public/assets)
storage/logs/         Application logs
tests/                PHPUnit tests
var/                  Cache, proxies (gitignored)
```

## Adding a controller

```php
// src/Controller/AboutController.php
namespace App\Controller;

use Modufolio\Appkit\Core\AbstractController;
use Modufolio\Psr7\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\Attribute\Route;

class AboutController extends AbstractController
{
    #[Route(path: '/about', name: 'about', methods: ['GET'])]
    public function index(): ResponseInterface
    {
        return new Response(body: '<h1>About</h1>');
    }
}
```

If the controller takes constructor dependencies, list them in `config/controllers.php`.

## License

MIT
