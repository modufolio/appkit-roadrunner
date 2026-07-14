<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Entity\User;
use App\Tests\Case\AppTestCase;
use Modufolio\Appkit\Security\User\UserPasswordHasherInterface;

final class HomeTest extends AppTestCase
{
    protected function setUp(): void
    {
        $this->refreshDatabase();

        $em = $this->app()->entityManager();
        $hasher = $this->app()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('johndoe@example.com');
        $user->setPassword($hasher->hashPassword($user, 'secret'));

        $em->persist($user);
        $em->flush();
    }

    public function testHomePageRedirectsAnonymousVisitorsToLogin(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function testHomePageReturns200ForAuthenticatedUser(): void
    {
        $this->login();

        $this->get('/')->assertStatus(200);
    }
}
