<?php

declare(strict_types=1);

use App\Entity\User;
use App\Repository\UserRepository;

return [
    UserRepository::class => User::class,
];
