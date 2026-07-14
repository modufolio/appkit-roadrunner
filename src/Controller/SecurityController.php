<?php

declare(strict_types=1);

namespace App\Controller;

use Modufolio\Appkit\Core\AbstractController;
use Modufolio\Appkit\Security\Authenticator\RememberMeAuthenticator;
use Modufolio\Appkit\Security\Csrf\CsrfTokenManagerInterface;
use Modufolio\Appkit\Template\Template;
use Modufolio\Psr7\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly FlashBagAwareSessionInterface $session,
        private readonly RememberMeAuthenticator $rememberMe,
    ) {
    }

    #[Route(path: '/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(ServerRequestInterface $request): ResponseInterface
    {
        // On a successful login POST the firewall has already authenticated the
        // user before this controller runs, so getUser() is set here. Issue the
        // remember-me cookie (when requested) on the redirect away from /login.
        if (null !== $this->getUser()) {
            $response = Response::redirect($this->urlGenerator->generate('home'));

            return $this->issueRememberMeCookie($response, $request);
        }

        $error = $this->session->getFlashBag()->get('error')[0] ?? null;

        $template = new Template(
            name: 'login',
            templatePaths: [dirname(__DIR__, 2).'/resources/views'],
            layoutPaths: [dirname(__DIR__, 2).'/resources/views/layouts'],
            request: $request,
        );

        return new Response(body: $template->render([
            'title' => 'Sign in',
            'error' => $error,
            'csrf_token' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
        ]));
    }

    #[Route(path: '/logout', name: 'logout', methods: ['POST'])]
    public function logout(): never
    {
        // Intercepted by the firewall logout listener before this method runs.
        // The route exists only so the URL generator can resolve `logout`.
        throw new \LogicException('This should never be reached.');
    }

    /**
     * Attach a remember-me Set-Cookie to the response when the user ticked the
     * "Remember me" box on a login POST.
     */
    private function issueRememberMeCookie(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        if ('POST' !== $request->getMethod()) {
            return $response;
        }

        $body = $request->getParsedBody();
        if (!is_array($body) || empty($body['_remember_me'])) {
            return $response;
        }

        $user = $this->getUser();
        if (null === $user) {
            return $response;
        }

        $value = $this->rememberMe->generateRememberMeCookie($user);
        /** @var array{expires:int,path:string,domain:?string,secure:bool,httponly:bool,samesite:string} $options */
        $options = $this->rememberMe->getCookieOptions();

        return $response->withAddedHeader(
            'Set-Cookie',
            $this->formatCookie($this->rememberMe->getCookieName(), $value, $options),
        );
    }

    /**
     * Build a Set-Cookie header value. The value is url-encoded so the base64
     * payload survives PHP's automatic cookie decoding on the way back in.
     *
     * @param array{expires:int,path:string,domain:?string,secure:bool,httponly:bool,samesite:string} $o
     */
    private function formatCookie(string $name, string $value, array $o): string
    {
        $parts = [
            $name.'='.urlencode($value),
            'Path='.$o['path'],
            'Expires='.gmdate('D, d M Y H:i:s T', $o['expires']),
            'Max-Age='.max(0, $o['expires'] - time()),
        ];

        if (!empty($o['domain'])) {
            $parts[] = 'Domain='.$o['domain'];
        }
        if ($o['secure']) {
            $parts[] = 'Secure';
        }
        if ($o['httponly']) {
            $parts[] = 'HttpOnly';
        }
        if (!empty($o['samesite'])) {
            $parts[] = 'SameSite='.ucfirst($o['samesite']);
        }

        return implode('; ', $parts);
    }
}
