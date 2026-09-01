<?php

declare(strict_types=1);

namespace App\Tests\Case;

use App\App;
use App\AppFactory;
use Modufolio\Appkit\Testing\AppTestCase as BaseAppTestCase;

/**
 * The framework ships the whole feature-test harness — request dispatch,
 * session/CSRF continuity, database refreshing, auth helpers. This subclass
 * fills the one required seam: how this application is built.
 */
abstract class AppTestCase extends BaseAppTestCase
{
    private static ?App $app = null;

    protected function app(): App
    {
        if (self::$app === null) {
            $app = AppFactory::create(dirname(__DIR__, 2));
            assert($app instanceof App);
            self::$app = $app;
            self::$app->initializeConsoleState();
        }

        return self::$app;
    }

    protected function login(): void
    {
        $this->actingAs('johndoe@example.com', 'secret');
    }
}
