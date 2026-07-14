<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Modufolio\Appkit\Security\Exception\UserNotFoundException;
use Modufolio\Appkit\Security\User\UserPasswordHasherInterface;
use Modufolio\Appkit\Security\User\UserProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:add-user', description: 'Creates a user and stores it in the database')]
class AddUserCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly UserProviderInterface $userProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email of the new user')
            ->addArgument('password', InputArgument::OPTIONAL, 'Plain password of the new user')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Create the user as ROLE_ADMIN')
            ->addOption('roles', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Custom roles');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if ($input->getArgument('email') && $input->getArgument('password')) {
            return;
        }

        $this->io->title('Add User');
        $this->io->text([
            'Skip the wizard with:',
            '  $ php bin/console app:add-user user@example.com secret [--admin]',
            '',
        ]);

        $this->promptForEmail($input);
        $this->promptForPassword($input);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $password = (string) $input->getArgument('password');
        $roles = $this->collectRoles($input);

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles($roles);

        $errors = $this->validator->validate($user);
        if ($errors->count() > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = sprintf('%s: %s', $error->getPropertyPath(), $error->getMessage());
            }
            $this->io->error($messages);

            return Command::FAILURE;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->io->success(sprintf(
            'User created: %s [%s]',
            $user->getEmail(),
            implode(', ', $user->getRoles()),
        ));

        return Command::SUCCESS;
    }

    private function promptForEmail(InputInterface $input): void
    {
        if ($email = $input->getArgument('email')) {
            $this->ensureEmailAvailable($email);
            $this->io->text(' > <info>Email</info>: '.$email);

            return;
        }

        $email = $this->io->ask('Email', null, function (?string $value): string {
            if (!$value || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Please enter a valid email address.');
            }
            $this->ensureEmailAvailable($value);

            return $value;
        });

        $input->setArgument('email', $email);
    }

    private function promptForPassword(InputInterface $input): void
    {
        if ($password = $input->getArgument('password')) {
            $this->io->text(' > <info>Password</info>: '.str_repeat('*', mb_strlen($password)));

            return;
        }

        $question = new Question('Password (hidden)');
        $question->setHidden(true);
        $question->setValidator(function (?string $value): string {
            if (!$value || strlen($value) < 8) {
                throw new \RuntimeException('Password must be at least 8 characters.');
            }

            return $value;
        });

        $input->setArgument('password', $this->io->askQuestion($question));
    }

    private function ensureEmailAvailable(string $email): void
    {
        try {
            $this->userProvider->loadUserByIdentifier($email);
            throw new \RuntimeException(sprintf('A user with email "%s" already exists.', $email));
        } catch (UserNotFoundException) {
            // ok
        }
    }

    /** @return list<string> */
    private function collectRoles(InputInterface $input): array
    {
        $roles = $input->getOption('roles') ?? [];
        if ($input->getOption('admin')) {
            $roles[] = 'ROLE_ADMIN';
        }

        return array_values(array_unique($roles));
    }
}
