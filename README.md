# AppKit Skeleton

A minimal starting point for [modufolio/appkit](https://github.com/modufolio/appkit) applications.

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

# Build assets
npm run build

# Create database schema (or use migrations)
php bin/console orm:schema-tool:create

# Run the dev server
composer start
# → http://localhost:8000
```

## Running under RoadRunner

The app ships with a [RoadRunner](https://roadrunner.dev) runtime for long-lived
PHP workers, giving much higher throughput than the per-request PHP dev server.

The server binary is in this repo (it is platform-specific and ~59 MB).
Download it once per environment with the bundled CLI:

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
  Controller/         HTTP controllers
  Entity/             Doctrine entities
  Repository/         Doctrine repositories
config/               DI, routes, security, doctrine, …
  routes.php          Route loaders
  security.php        Firewalls + role hierarchy
  controllers.php     Controller → dependency map
  factories.php       Service factories
  interfaces.php      Interface → kernel-method map
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
