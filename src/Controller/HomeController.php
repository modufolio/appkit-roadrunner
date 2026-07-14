<?php

declare(strict_types=1);

namespace App\Controller;

use App\Attributes\Template;
use Modufolio\Appkit\Core\AbstractController;
use Modufolio\Appkit\Security\Csrf\CsrfTokenManagerInterface;
use Modufolio\Appkit\Template\Template as TemplateEngine;
use Modufolio\Psr7\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(path: '/', name: 'home', methods: ['GET'])]
    public function index(#[Template('home')] TemplateEngine $template): ResponseInterface
    {
        return new Response(body: $template->render([
            'title' => 'Welcome',
            'user' => $this->getUser(),
            'logout_csrf' => $this->csrfTokenManager->getToken('logout')->getValue(),
        ]));
    }
}
