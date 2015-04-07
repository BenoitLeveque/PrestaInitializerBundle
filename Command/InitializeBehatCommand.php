<?php

namespace Presta\InitializerBundle\Command;

use RuntimeException;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Process\Process;

class InitializeBehatCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('presta:initializer:behat')
            ->setDescription('Initialize behat inside your projet')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command initialize behat with

- Add requirement inside your composer.json
- Create a behat.yml.dist at the top of your project
EOF
);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = $this->getContainer()->getParameter('kernel.root_dir') . '/../';

        $applicationUrl = $this
            ->getDialog()
            ->ask(
                $output,
                sprintf(
                    '<question>%s</question> (<comment>%s</comment>)',
                    'Your application url',
                    'http://application.dev/'
                ),
                ''
            );

        $addProfileForJenkins = $this
            ->getDialog()
            ->select(
                $output,
                sprintf('<question>%s</question>', 'Create a behat profile for jenkins'),
                [true => 'yes', false => 'no', ],
                false
            );

        $jenkinsApplicationUrl = '';

        if ($addProfileForJenkins) {
            $jenkinsApplicationUrl = $this->getDialog()->ask(
                $output,
                sprintf('<question>%s</question>', 'Your jenkins application url'),
                ''
            );
        }

        $output->writeln('Create the behat.yml.dist file');

        $this->getFilesystem()->dumpFile(
            $directory . 'behat.yml.dist',
            $this->getTwigEngine()->render(
                $this->getKernel()->locateResource('@PrestaInitializerBundle/Resources/skeleton/behat.yml.dist.twig'),
                [
                    'use_jenkins'             => $addProfileForJenkins,
                    'application_local_url'   => $applicationUrl,
                    'application_jenkins_url' => $jenkinsApplicationUrl,
                ]
            )
        );

        $output->writeln('Add Behat requirements to your composer.json');

        $behatRequirements = [
            'behat/behat',
            'behat/symfony2-extension',
            'behat/mink-extension',
            'behat/mink-goutte-driver',
            'behat/mink-selenium2-driver',
            'knplabs/friendly-contexts',
        ];

        $process = new Process(sprintf('composer require --dev %s', implode(' ', $behatRequirements)));
        $process->run(
            function ($type, $buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }
    }

    /**
     * @return DialogHelper
     */
    private function getDialog()
    {
        return $this->getHelperSet()->get('dialog');
    }

    /**
     * @return TwigEngine
     */
    private function getTwigEngine()
    {
        return $this->getContainer()->get('templating');
    }

    /**
     * @return Filesystem
     */
    private function getFilesystem()
    {
        return new Filesystem();
    }

    /**
     * @return Kernel
     */
    private function getKernel()
    {
        return $this->getContainer()->get('kernel');
    }
}