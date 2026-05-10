<?php

namespace App\Command;

use App\Service\StreamAutoStartService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:streams:start',
    description: 'Start all active camera streams or restart stopped streams'
)]
class StartStreamsCommand extends Command
{
    private StreamAutoStartService $autoStartService;

    public function __construct(StreamAutoStartService $autoStartService)
    {
        parent::__construct();
        $this->autoStartService = $autoStartService;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'check-only',
                'c',
                InputOption::VALUE_NONE,
                'Only check stream status without starting them'
            )
            ->addOption(
                'restart-stopped',
                'r',
                InputOption::VALUE_NONE,
                'Restart only streams that have stopped (for cron/periodic checks)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Check-only mode: just show status
        if ($input->getOption('check-only')) {
            $io->title('Stream Status Check');
            $status = $this->autoStartService->getStreamStatus();
            
            if (empty($status)) {
                $io->warning('No cameras found in the system');
                return Command::SUCCESS;
            }

            $tableRows = [];
            foreach ($status as $camera) {
                $tableRows[] = [
                    $camera['id'],
                    $camera['name'],
                    $camera['dbStatus'],
                    $camera['transcoding'] ? '<fg=green>Yes</>' : '<fg=red>No</>',
                    $camera['hlsUrl'] ?: 'N/A'
                ];
            }

            $io->table(
                ['ID', 'Name', 'DB Status', 'Transcoding', 'HLS URL'],
                $tableRows
            );

            return Command::SUCCESS;
        }

        // Restart stopped streams mode
        if ($input->getOption('restart-stopped')) {
            $io->title('Checking and Restarting Stopped Streams');
            $results = $this->autoStartService->checkAndRestartStoppedStreams();

            $io->success(sprintf(
                'Restarted %d streams, %d still running',
                count($results['restarted']),
                count($results['still_running'])
            ));

            if (!empty($results['restarted'])) {
                $io->writeln('Restarted cameras: ' . implode(', ', $results['restarted']));
            }

            return Command::SUCCESS;
        }

        // Default: Start all active streams
        $io->title('Starting All Active Streams');
        $results = $this->autoStartService->startAllActiveStreams();

        $io->success(sprintf(
            'Started %d streams, %d already running, %d failed',
            count($results['started']),
            count($results['already_running']),
            count($results['failed'])
        ));

        if (!empty($results['started'])) {
            $io->writeln('Started cameras: ' . implode(', ', $results['started']));
        }

        if (!empty($results['already_running'])) {
            $io->writeln('Already running: ' . implode(', ', $results['already_running']));
        }

        if (!empty($results['failed'])) {
            $io->warning('Failed cameras:');
            foreach ($results['failed'] as $failed) {
                $io->writeln(sprintf(
                    '  - Camera %d: %s',
                    $failed['cameraId'],
                    $failed['reason']
                ));
            }
        }

        return Command::SUCCESS;
    }
}
