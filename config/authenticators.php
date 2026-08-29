<?php

declare(strict_types=1);

use App\Repository\UserRepository;
use Modufolio\Appkit\Security\Authenticator\FormLoginAuthenticator;
use Modufolio\Appkit\Security\Authenticator\RememberMeAuthenticator;
use Modufolio\Appkit\Security\BruteForce\BruteForceProtectionInterface;
use Modufolio\Appkit\Security\Csrf\CsrfTokenManagerInterface;
use Modufolio\Appkit\Security\User\UserPasswordHasherInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

return [
    'form_login' => function (ContainerInterface $container) {
        return new FormLoginAuthenticator(
            userProvider: $container->get(UserRepository::class),
            csrfTokenManager: $container->get(CsrfTokenManagerInterface::class),
            session: $container->get(SessionInterface::class),
            passwordHasher: $container->get(UserPasswordHasherInterface::class),
            bruteForce: $container->get(BruteForceProtectionInterface::class),
            options: [
                'check_path' => '/login',
                'login_path' => '/login',
            ],
        );
    },
    'remember_me' => function (ContainerInterface $container) {
        return new RememberMeAuthenticator(
            userProvider: $container->get(UserRepository::class),
            options: [
                'secret' => env()->getRequired('REMEMBER_ME_SECRET'),
                'cookie_name' => 'REMEMBERME',
                'cookie_lifetime' => 2592000, // 30 days
                // Mirror the session cookie's Secure flag so both follow the
                // same HTTP/HTTPS policy (false in dev, true behind HTTPS).
                'cookie_secure' => env()->getBool('COOKIE_SECURE', true),
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
            ],
        );
    },
];
