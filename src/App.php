<?php

declare(strict_types=1);

namespace App;

use App\Resolver\TemplateResolver;
use Modufolio\Appkit\Core\Kernel;
use Modufolio\Appkit\Core\NativeApplicationState;
use Modufolio\Appkit\Exception\ExceptionHandler;
use Modufolio\Appkit\Exception\ExceptionHandlerInterface;
use Modufolio\Appkit\Resolver\AssociativeArrayResolver;
use Modufolio\Appkit\Resolver\AttributeParameterResolver;
use Modufolio\Appkit\Resolver\DefaultValueResolver;
use Modufolio\Appkit\Resolver\MapEntityResolver;
use Modufolio\Appkit\Resolver\MapQueryParameterResolver;
use Modufolio\Appkit\Resolver\MapRequestPayloadResolver;
use Modufolio\Appkit\Resolver\ParameterResolverInterface;
use Modufolio\Appkit\Resolver\ResolverPipeline;
use Modufolio\Appkit\Resolver\TypeHintContainerResolver;
use Modufolio\Appkit\Resolver\TypeHintResolver;
use Modufolio\Appkit\Resolver\UserResolver;
use Modufolio\Appkit\Security\Csrf\CsrfTokenManager;
use Modufolio\Appkit\Security\Csrf\CsrfTokenManagerInterface;
use Modufolio\Appkit\Security\User\UserProviderInterface;
use Modufolio\Appkit\Template\Template;
use Modufolio\Psr7\Http\Response;
use Modufolio\Psr7\Http\ServerRequest;
use Modufolio\Psr7\Http\Stream;
use Modufolio\Psr7\Http\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class App extends Kernel
{
    private ?CsrfTokenManagerInterface $csrfTokenManager = null;
    private ?UserProviderInterface $userProvider = null;

    /**
     * @param array<string, mixed> $authenticators
     * @param array<string, mixed> $controllers
     * @param array<string, mixed> $factories
     * @param array<string, mixed> $fileMap
     * @param array<string, mixed> $repositories
     */
    public function __construct(
        string $baseDir,
        LoaderInterface $routeLoader,
        private string $userProviderClass,
        ?LoggerInterface $logger = null,
        array $authenticators = [],
        array $controllers = [],
        array $factories = [],
        array $fileMap = [],
        array $repositories = [],
    ) {
        $this->baseDir = $baseDir;
        $this->routeLoader = $routeLoader;
        $this->logger = $logger ?? new NullLogger();
        $this->authenticators = $authenticators;
        $this->controllers = $controllers;
        $this->factories = $factories;
        $this->fileMap = $fileMap;
        $this->repositories = $repositories;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->state?->reset();
        $this->state = null;

        $this->state = new NativeApplicationState($request, $this->baseDir, $this->firewallConfig);

        try {
            $response = $this->handleAuthentication($request);
        } catch (\Throwable $e) {
            $response = $this->exceptionHandler()->handle($e, $request);
        }

        return $this->prepareResponse()->prepare($request, $response);
    }

    public function initializeTestState(): self
    {
        if (null === $this->state) {
            $request = new ServerRequest(
                method: 'GET',
                uri: new Uri('http://localhost'),
                headers: [],
                body: Stream::create(''),
                version: '1.1',
                serverParams: [
                    'HTTP_HOST' => 'localhost',
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/',
                    'SERVER_PROTOCOL' => 'HTTP/1.1',
                ]
            );
            $this->state = new NativeApplicationState($request, $this->baseDir, $this->firewallConfig);
        }

        return $this;
    }

    public function reset(): void
    {
        $this->state?->reset();
        $this->state = null;
        $this->debugStack->resetQueries();
        $this->entityManagerFactory?->reset();
        $this->emitter = null;
        $this->environment = null;
        $this->instances = [];
        $this->userProvider = null;
        $this->csrfTokenManager = null;
        $this->parameterResolver = null;
    }

    public function userProvider(): UserProviderInterface
    {
        if (null === $this->userProvider) {
            $repo = $this->getRepository($this->userProviderClass);
            assert($repo instanceof UserProviderInterface);
            $this->userProvider = $repo;
        }

        return $this->userProvider;
    }

    public function csrfTokenManager(): CsrfTokenManagerInterface
    {
        return $this->csrfTokenManager ??= new CsrfTokenManager($this->session());
    }

    public function serializer(): SerializerInterface
    {
        return $this->serializer ??= new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()]
        );
    }

    public function parameterResolver(): ParameterResolverInterface
    {
        $serializer = $this->serializer();
        assert($serializer instanceof DenormalizerInterface);

        return $this->parameterResolver ??= (new ResolverPipeline())
            ->addResolver(new AssociativeArrayResolver())
            ->addResolver(new TypeHintResolver())
            ->addResolver(new AttributeParameterResolver([
                new UserResolver($this->tokenStorage()),
                new MapQueryParameterResolver($this->request()),
                new MapEntityResolver($this->entityManager()),
                new MapRequestPayloadResolver(
                    $serializer,
                    $this->request(),
                    $this->validator()
                ),
                new TemplateResolver(
                    [$this->baseDir.'/resources/views'],
                    [$this->baseDir.'/resources/views/layouts'],
                    $this->request()
                ),
            ]))
            ->addResolver(new TypeHintContainerResolver($this))
            ->addResolver(new DefaultValueResolver());
    }

    public function validator(): ValidatorInterface
    {
        return $this->validator ??= Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function exceptionHandler(): ExceptionHandlerInterface
    {
        if (null !== $this->exceptionHandler) {
            return $this->exceptionHandler;
        }

        $handler = new ExceptionHandler($this->environment(), $this->logger);

        $handler->registerFormatter(
            'text/html',
            function (array $data): Response {
                $status = (int) ($data['status'] ?? 500);

                $body = (new Template(
                    name: 'errors/default',
                    templatePaths: [$this->baseDir.'/resources/views'],
                    layoutPaths: [$this->baseDir.'/resources/views/layouts'],
                    request: $this->request(),
                ))->render([
                    'title' => $data['title'] ?? 'Error',
                    'detail' => $data['detail'] ?? null,
                    'status' => $status,
                ]);

                return new Response($status, ['Content-Type' => 'text/html; charset=utf-8'], $body);
            },
        );

        return $this->exceptionHandler = $handler;
    }
}
