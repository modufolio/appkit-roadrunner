<?php

declare(strict_types=1);

use Modufolio\Appkit\Security\SecurityConfigurator;

return function (SecurityConfigurator $security): void {
    $security->firewall('main', [
        'pattern' => '/',
        'authenticators' => ['form_login', 'remember_me'],
        'entry_point' => '/login',
        'logout' => [
            'path' => '/logout',
            'target' => '/',
        ],
    ]);

    $security->roleHierarchy([
        'ROLE_ADMIN' => ['ROLE_USER'],
    ]);
};
