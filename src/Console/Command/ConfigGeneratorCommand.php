<?php

declare(strict_types=1);

namespace Crunz\Console\Command;

use Crunz\Timezone\ProviderInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class ConfigGeneratorCommand extends Command
{
    /** @var ProviderInterface */
    private $timezoneProvider;
    /** @var Filesystem */
    private $filesystem;

    public function __construct(ProviderInterface $timezoneProvider, Filesystem $filesystem)
    {
        $this->timezoneProvider = $timezoneProvider;
        $this->filesystem = $filesystem;

        parent::__construct();
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this
            ->setName('publish:config')
            ->setDescription("Generates a config file within the project's root directory.")
            ->setHelp("This generates a config file in YML format within the project's root directory.")
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $symfonyStyleIo = new SymfonyStyle($input, $output);

        $path = getbase() . '/crunz.yml';
        $destination = \realpath($path) ?: $path;
        $configExists = $this->filesystem
            ->exists($destination)
        ;

        $output->writeln(
            "<info>Destination config file: '{$destination}'.</info>",
            OutputInterface::VERBOSITY_VERBOSE
        );

        if ($configExists) {
            $output->writeln(
                "<comment>The configuration file already exists at '{$destination}'.</comment>"
            );

            return 0;
        }

        $src = __DIR__ . '/../../../crunz.yml';
        $output->writeln(
            "<info>Source config file: '{$src}'.</info>",
            OutputInterface::VERBOSITY_VERBOSE
        );
        $defaultTimezone = $this->askForTimezone($symfonyStyleIo);
        $output->writeln(
            "<info>Provided timezone: '{$defaultTimezone}'.</info>",
            OutputInterface::VERBOSITY_VERBOSE
        );

        $this->updateTimezone(
            $destination,
            $src,
            $defaultTimezone
        );

        $output->writeln('<info>The configuration file was generated successfully.</info>');

        return 0;
    }

    /**
     * @return string
     */
    protected function askForTimezone(SymfonyStyle $symfonyStyleIo)
    {
        $defaultTimezone = $this->timezoneProvider
            ->defaultTimezone()
            ->getName()
        ;
        $question = new Question(
            '<question>Please provide default timezone for task run date calculations</question>',
            $defaultTimezone
        );
        $question->setAutocompleterValues(\DateTimeZone::listIdentifiers());
        $question->setValidator(
            function ($answer) {
                try {
                    new \DateTimeZone($answer);
                } catch (\Exception $exception) {
                    throw new \Exception("Timezone '{$answer}' is not valid. Please provide valid timezone.");
                }

                return $answer;
            }
        );

        return $symfonyStyleIo->askQuestion($question);
    }

    private function updateTimezone(
        $destination,
        $src,
        $timezone
    ) {
        $this->filesystem
            ->dumpFile(
                $destination,
                \str_replace(
                    'timezone: ~',
                    "timezone: {$timezone}",
                    \file_get_contents($src)
                )
            )
        ;
    }
}
