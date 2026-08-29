<?php

declare(strict_types=1);

namespace App\Command;

use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Jobs\Jobs;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Push a task onto a RoadRunner jobs pipeline over the RPC socket.
 *
 * Requires a running `rr serve` (the RPC listener from .rr.yaml); the jobs
 * pool consumes the task through worker.php's jobs branch.
 */
#[AsCommand(name: 'app:jobs:push', description: 'Push a task onto a RoadRunner jobs pipeline')]
final class JobPushCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Task name (must have a registered handler in worker.php)')
            ->addArgument('payload', InputArgument::OPTIONAL, 'Task payload', '')
            ->addOption('pipeline', null, InputOption::VALUE_REQUIRED, 'Pipeline to push onto', 'local')
            ->addOption('rpc', null, InputOption::VALUE_REQUIRED, 'RoadRunner RPC address', 'tcp://127.0.0.1:6001');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $payload = $input->getArgument('payload');
        $pipeline = $input->getOption('pipeline');
        $rpcAddress = $input->getOption('rpc');
        \assert(\is_string($name) && '' !== $name);
        \assert(\is_string($payload));
        \assert(\is_string($pipeline) && '' !== $pipeline);
        \assert(\is_string($rpcAddress) && '' !== $rpcAddress);

        try {
            $queue = (new Jobs(RPC::create($rpcAddress)))->connect($pipeline);
            $task = $queue->dispatch($queue->create($name, $payload));
        } catch (\Throwable $e) {
            $io->error(sprintf('Push failed: %s — is `rr serve` running with an RPC listener on %s?', $e->getMessage(), $rpcAddress));

            return Command::FAILURE;
        }

        $io->success(sprintf('Pushed task "%s" (id %s) onto pipeline "%s".', $name, $task->getId(), $pipeline));

        return Command::SUCCESS;
    }
}
