<?php

declare(strict_types=1);

namespace App;

use App\Entity\User;
use App\Logger\FileLogger;
use App\Repository\UserRepository;
use Modufolio\Appkit\Core\AppInterface;
use Modufolio\Appkit\Routing\Loader\AttributeClassLoader;
use Modufolio\Appkit\Security\SecurityConfigurator;
use Modufolio\Appkit\Security\TokenUnserializer;
use Modufolio\Appkit\Toolkit\F;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\PhpFileLoader;

class AppFactory
{
    /**
     * @param class-string<App> $appClass Application class to instantiate (e.g. RoadRunnerApp for the RoadRunner runtime)
     */
    public static function create(string $baseDir, string $appClass = App::class): AppInterface
    {
        // Allow this app's User entity to be unserialized from session-stored
        // auth tokens. Without this, the framework's hardened TokenUnserializer
        // refuses to instantiate it. Register every class that may appear inside
        // a serialized token (typically just your User entity).
        TokenUnserializer::register(User::class);

        $locator = new FileLocator([$baseDir.'/config']);

        $routeLoader = new DelegatingLoader(new LoaderResolver([
            new PhpFileLoader($locator),
            new AttributeDirectoryLoader($locator, new AttributeClassLoader()),
        ]));

        $security = new SecurityConfigurator();
        (require $baseDir.'/config/security.php')($security);

        $env = env('APP_ENV');
        $doctrineConfig = null !== $env && is_file($baseDir."/config/{$env}/doctrine.php")
            ? $baseDir."/config/{$env}/doctrine.php"
            : $baseDir.'/config/doctrine.php';

        return (new $appClass(
            baseDir: $baseDir,
            routeLoader: $routeLoader,
            userProviderClass: UserRepository::class,
            logger: new FileLogger($baseDir.'/storage/logs'),
            authenticators: F::load($baseDir.'/config/authenticators.php', []),
            controllers: F::load($baseDir.'/config/controllers.php', []),
            factories: F::load($baseDir.'/config/factories.php', []),
            fileMap: [
                'doctrine' => $doctrineConfig,
                'interfaces' => $baseDir.'/config/interfaces.php',
            ],
            repositories: F::load($baseDir.'/config/repositories.php', []),
        ))->configureSecurity($security)->boot();
    }
}
