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

## Project layout

```
src/                  Application code (PSR-4: App\)
  App.php             Kernel subclass
  AppFactory.php      Boots the kernel
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
