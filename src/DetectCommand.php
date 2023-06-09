<?php

namespace Orchestra\DuskUpdater;

use Composer\Semver\Comparator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @copyright Originally created by Jonas Staudenmeir: https://github.com/staudenmeir/dusk-updater
 */
class DetectCommand extends Command
{
    use Concerns\DetectsChromeVersion;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this->ignoreValidationErrors();

        $directory = getcwd().'/vendor/wpstarter/dusk/bin/';

        $this->setName('detect')
                ->setDescription('Detect the installed Chrome/Chromium version.')
                ->addOption('chrome-dir', null, InputOption::VALUE_OPTIONAL, 'Detect the installed Chrome/Chromium version, optionally in a custom path')
                ->addOption('install-dir', null, InputOption::VALUE_OPTIONAL, 'Install a ChromeDriver binary in this directory', $directory)
                ->addOption('auto-update', null, InputOption::VALUE_NONE, 'Auto update ChromeDriver binary if outdated');
    }

    /**
     * Execute the command.
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $chromeDirectory = $input->getOption('chrome-dir');
        $driverDirectory = $input->getOption('install-dir');
        $autoUpdate = $input->getOption('auto-update');

        $currentOS = OperatingSystem::id();

        $chromeVersions = $this->installedChromeVersion($currentOS, $chromeDirectory);
        $driverVersions = $this->installedChromeDriverVersion($currentOS, $driverDirectory);

        $updated = Comparator::equalTo(
            isset($driverVersions['semver']) ? $driverVersions['semver'] : '',
            isset($chromeVersions['semver']) ? $chromeVersions['semver'] : ''
        );

        $io->table(['Tool', 'Version'], [
            ['Chrome/Chromium', $chromeVersions['semver'] ?? '<fg=yellow>✖ N/A</>'],
            ['ChromeDriver', $driverVersions['semver'] ?? '<fg=yellow>✖ N/A</>'],
        ]);

        if (! $updated) {
            if (! $autoUpdate) {
                $io->caution('ChromeDriver is outdated!');
            }

            if ($autoUpdate || $io->confirm('Do you want to update ChromeDriver?')) {
                $this->updateChromeDriver($output, $driverDirectory, $chromeVersions['major']);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Update ChromeDriver.
     */
    protected function updateChromeDriver(OutputInterface $output, string $directory, int $version): int
    {
        /** @var \Symfony\Component\Console\Application $console */
        $console = $this->getApplication();

        $command = $console->find('update');

        $arguments = [
            'command' => 'update',
            'version' => $version,
            '--install-dir' => $directory,
        ];

        return $command->run(new ArrayInput($arguments), $output);
    }
}
