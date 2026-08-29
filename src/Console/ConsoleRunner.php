<?php

declare(strict_types=1);

namespace App\Console;

use App\AppFactory;
use App\Command\AddUserCommand;
use App\Command\JobPushCommand;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\Console as DBALConsole;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\CurrentCommand;
use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\DumpSchemaCommand;
use Doctrine\Migrations\Tools\Console\Command\ExecuteCommand;
use Doctrine\Migrations\Tools\Console\Command\GenerateCommand;
use Doctrine\Migrations\Tools\Console\Command\LatestCommand;
use Doctrine\Migrations\Tools\Console\Command\ListCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\RollupCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Doctrine\Migrations\Tools\Console\Command\SyncMetadataCommand;
use Doctrine\Migrations\Tools\Console\Command\UpToDateCommand;
use Doctrine\Migrations\Tools\Console\Command\VersionCommand;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\Console\Command\ClearCache\CollectionRegionCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\EntityRegionCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\MetadataCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\QueryRegionCommand;
use Doctrine\ORM\Tools\Console\Command\ClearCache\ResultCommand;
use Doctrine\ORM\Tools\Console\Command\GenerateProxiesCommand;
use Doctrine\ORM\Tools\Console\Command\InfoCommand;
use Doctrine\ORM\Tools\Console\Command\MappingDescribeCommand;
use Doctrine\ORM\Tools\Console\Command\RunDqlCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\DropCommand;
use Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand;
use Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\ConnectionFromManagerProvider;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;
use Modufolio\Appkit\Command\ControllersDebugCommand;
use Modufolio\Appkit\Command\FirewallDebugCommand;
use Modufolio\Appkit\Command\MakerCommand;
use Modufolio\Appkit\Command\RouterDebugCommand;
use Modufolio\Appkit\Command\SecurityValidateCommand;
use Modufolio\Appkit\Console\Doctrine\DoctrineHelper;
use Modufolio\Appkit\Console\Doctrine\EntityClassGenerator;
use Modufolio\Appkit\Console\FileManager;
use Modufolio\Appkit\Console\Generator;
use Modufolio\Appkit\Console\Maker\MakeEntity;
use Modufolio\Appkit\Core\AppInterface;
use Modufolio\Appkit\Doctrine\OrmConfigurator;
use Modufolio\Appkit\Routing\Loader\AttributeClassLoader;
use Modufolio\Appkit\Routing\Router;
use Modufolio\Appkit\Security\User\UserPasswordHasher;
use Modufolio\Appkit\Security\User\UserProviderInterface;
use Modufolio\Appkit\Util\AutoloaderUtil;
use Modufolio\Appkit\Util\MakerFileLinkFormatter;
use Modufolio\Appkit\Util\TemplateLinter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Validator\Validation;

final class ConsoleRunner
{
    public Application $cli;
    private Router $router;
    private ?EntityManagerInterface $entityManager = null;
    private ?AppInterface $app = null;
    /** @var array<string, string> */
    private array $fileMap = [];
    private ?string $env = null;

    /**
     * @param \Composer\Autoload\ClassLoader $classLoader
     * @param class-string                   $userClass
     */
    public function __construct(
        private $classLoader,
        private string $userClass,
        /** @var non-empty-string */
        private string $projectDir,
    ) {
        $input = new ArgvInput();
        $this->env = $this->getEnvironmentFromInput($input);

        // Propagate the resolved environment before anything reads APP_ENV:
        // AppFactory (via app()) and the doctrine config below both key off it.
        if (null !== $this->env) {
            putenv("APP_ENV={$this->env}");
            $_SERVER['APP_ENV'] = $this->env;
            $_ENV['APP_ENV'] = $this->env;
        }

        $output = new ConsoleOutput();
        $io = new SymfonyStyle($input, $output);

        $configFile = null === $this->env
            ? $this->projectDir.'/config/doctrine.php'
            : $this->projectDir."/config/{$this->env}/doctrine.php";

        if (!file_exists($configFile)) {
            $io->error("Configuration file not found: $configFile.");
            exit(1);
        }

        $this->fileMap['doctrine'] = $configFile;
        $this->cli = $this->createApplication();
        $this->router = $this->createRouter();
    }

    public function app(): AppInterface
    {
        if (null === $this->app) {
            $this->app = AppFactory::create($this->projectDir);
            $this->app->initializeConsoleState();
        }

        return $this->app;
    }

    public function entityManager(): EntityManagerInterface
    {
        if ($this->entityManager && $this->entityManager->isOpen()) {
            return $this->entityManager;
        }

        $configurator = new OrmConfigurator();
        (require $this->fileMap['doctrine'])($configurator);

        $cache = 'dev' === $this->env || 'test' === $this->env
            ? new ArrayAdapter()
            : new FilesystemAdapter('doctrine', 0, $this->projectDir.'/var/cache');

        $config = $configurator->ormConfig;
        $config->setMetadataCache($cache);
        $config->setQueryCache($cache);
        $config->setResultCache($cache);
        $config->setProxyDir($this->projectDir.'/var/proxies');
        $config->setProxyNamespace('DoctrineProxies');
        $config->setAutoGenerateProxyClasses(true);
        $config->setMetadataDriverImpl(new AttributeDriver($configurator->entityPaths));

        return $this->entityManager = new EntityManager(
            DriverManager::getConnection($configurator->connectionParams, $configurator->dbalConfig),
            $config,
        );
    }

    /**
     * @param list<\Symfony\Component\Console\Command\Command> $commands
     */
    public function addCommands(array $commands = []): self
    {
        $this->cli->addCommands($commands);

        return $this;
    }

    public function addDefaultCommands(): self
    {
        $userRepo = $this->entityManager()->getRepository($this->userClass);
        assert($userRepo instanceof UserProviderInterface);

        return $this->addCommands([
            new AddUserCommand(
                $this->entityManager(),
                new UserPasswordHasher(),
                Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
                $userRepo,
            ),
            new JobPushCommand(),
            $this->createMakerCommand(),
            new ControllersDebugCommand($this->app(), $this->router),
            new RouterDebugCommand($this->router),
            new FirewallDebugCommand($this->app()),
            new SecurityValidateCommand($this->app()),
        ]);
    }

    public function addOrmCommands(): self
    {
        $emProvider = new SingleManagerProvider($this->entityManager());
        $connectionProvider = new ConnectionFromManagerProvider($emProvider);

        return $this->addCommands([
            new DBALConsole\Command\RunSqlCommand($connectionProvider),
            new CollectionRegionCommand($emProvider),
            new EntityRegionCommand($emProvider),
            new MetadataCommand($emProvider),
            new QueryCommand($emProvider),
            new QueryRegionCommand($emProvider),
            new ResultCommand($emProvider),
            new CreateCommand($emProvider),
            new UpdateCommand($emProvider),
            new DropCommand($emProvider),
            new GenerateProxiesCommand($emProvider),
            new RunDqlCommand($emProvider),
            new ValidateSchemaCommand($emProvider),
            new InfoCommand($emProvider),
            new MappingDescribeCommand($emProvider),
        ]);
    }

    public function addMigrationsCommands(): self
    {
        $factory = DependencyFactory::fromEntityManager(
            new PhpFile($this->projectDir.'/config/migrations.php'),
            new ExistingEntityManager($this->entityManager()),
        );

        return $this->addCommands([
            new CurrentCommand($factory),
            new DumpSchemaCommand($factory),
            new ExecuteCommand($factory),
            new GenerateCommand($factory),
            new LatestCommand($factory),
            new MigrateCommand($factory),
            new RollupCommand($factory),
            new StatusCommand($factory),
            new VersionCommand($factory),
            new UpToDateCommand($factory),
            new SyncMetadataCommand($factory),
            new ListCommand($factory),
            new DiffCommand($factory),
        ]);
    }

    public function run(): int
    {
        return $this->cli->run();
    }

    private function createApplication(): Application
    {
        $cli = new Application('AppKit Console');
        $cli->setCatchExceptions(true);

        $cli->getDefinition()->addOption(
            new InputOption('env', null, InputOption::VALUE_REQUIRED, 'The environment name', getenv('APP_ENV') ?: 'prod'),
        );
        $cli->getDefinition()->addOption(
            new InputOption('test', null, InputOption::VALUE_NONE, 'Shortcut for --env=test'),
        );

        return $cli;
    }

    private function createRouter(): Router
    {
        $locator = new FileLocator([$this->projectDir.'/config']);
        $phpFileLoader = new PhpFileLoader($locator);

        return new Router(new DelegatingLoader(new LoaderResolver([
            $phpFileLoader,
            new AttributeDirectoryLoader($locator, new AttributeClassLoader()),
        ])), 'routes.php');
    }

    private function createMakerCommand(): MakerCommand
    {
        $filesystem = new Filesystem();
        $autoloaderUtil = new AutoloaderUtil($this->classLoader);
        $linkFormatter = new MakerFileLinkFormatter('phpstorm');
        $fileManager = new FileManager($filesystem, $autoloaderUtil, $linkFormatter, $this->projectDir);

        $generator = new Generator($fileManager, 'App\\');
        $doctrineHelper = new DoctrineHelper('App\Entity', $this->entityManager());
        $entityGenerator = new EntityClassGenerator($generator, $doctrineHelper);
        $makeEntity = new MakeEntity($fileManager, $doctrineHelper, $entityGenerator);

        return new MakerCommand($makeEntity, $fileManager, $generator, new TemplateLinter());
    }

    private function getEnvironmentFromInput(InputInterface $input): ?string
    {
        if ($input->getParameterOption('--test')) {
            return 'test';
        }
        if ($input->hasParameterOption('--env', true)) {
            return $input->getParameterOption('--env', null);
        }

        return null;
    }
}
