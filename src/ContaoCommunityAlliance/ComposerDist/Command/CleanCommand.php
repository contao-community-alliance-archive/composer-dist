<?php

namespace ContaoCommunityAlliance\ComposerDist\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class CleanCommand extends Command
{
	protected function configure()
	{
		parent::configure();

		$this->setName('clean');
		$this->setDescription('Clean the temporary files.');
		$this->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'Define the environment to build.', 'prod');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$env = $input->getOption('env');

		$paths = array(
			sprintf(
				'%s/%s/composer/cache',
				getcwd(),
				$env
			),
			sprintf(
				'%s/%s/composer/vendor',
				getcwd(),
				$env
			),
			sprintf(
				'%s/%s/composer/composer.lock',
				getcwd(),
				$env
			),
			sprintf(
				'%s/%s/composer/composer.phar',
				getcwd(),
				$env
			),
			sprintf(
				'%s/%s/system/modules',
				getcwd(),
				$env
			),
		);

		$fs = new Filesystem();

		$output->writeln(sprintf('  - <info>Clean %s environment</info>', $env));

		foreach ($paths as $path) {
			$output->writeln(sprintf('    <comment>remove %s</comment>', $path));

			$fs->remove($path);
		}
	}
}
