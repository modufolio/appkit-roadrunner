<?php

declare(strict_types=1);

use App\App;
use Modufolio\Appkit\DependencyInjection\ServiceConfigurator;
use Modufolio\Appkit\Security\Authenticator\RememberMeAuthenticator;
use Modufolio\Appkit\Security\BruteForce\BruteForceProtectionInterface;
use Modufolio\Appkit\Security\BruteForce\FileBruteForceProtection;

// The kernel wires its own core services (router, session, entity manager,
// CSRF, serializer, …); this file declares what the application adds. An
// entry here overrides the kernel default for the same id.
//
// Register your own services with set() (fresh instance per get()),
// shared() (resolved once per request, cleared by reset()), or alias():
//
//   $services->set(App\Service\Foo::class, fn (App $app) => new Foo($app->entityManager()));
//
return function (ServiceConfigurator $services): void {
    $services
        ->set(BruteForceProtectionInterface::class, fn (App $app) => new FileBruteForceProtection($app->baseDir.'/var/brute-force'))
        ->set(RememberMeAuthenticator::class, fn (App $app) => new RememberMeAuthenticator(
            userProvider: $app->userProvider(),
            options: [
                'secret' => env('REMEMBER_ME_SECRET'),
                'cookie_secure' => filter_var(env('COOKIE_SECURE'), FILTER_VALIDATE_BOOL),
            ],
        ));
};
