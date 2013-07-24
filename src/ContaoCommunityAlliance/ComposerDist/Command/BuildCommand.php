<?php

namespace ContaoCommunityAlliance\ComposerDist\Command;

use Guzzle\Http\Client;
use Guzzle\Http\EntityBody;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class BuildCommand extends Command
{
	protected function configure()
	{
		parent::configure();

		$this->setName('build');
		$this->setDescription('Build the dist archive.');
		$this->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'Define the environment to build.', 'prod');
		$this->addOption('zip', 'z', InputOption::VALUE_NONE, 'Build a ZIP archive (enabled by default, only present for sanity).');
		$this->addOption('tar', 't', InputOption::VALUE_NONE, 'Build a TAR archive.');
		$this->addOption('clean', null, InputOption::VALUE_NONE, 'Clean environment (enabled by default, only present for sanity).');
		$this->addOption('no-clean', null, InputOption::VALUE_NONE, 'Dont clean environment.');
		$this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Define output path. (default: dist/contao-composer-$env.zip)');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		// read and eval options
		$env = $input->getOption('env');
		$tar = $input->getOption('tar');
		$out = $input->getOption('output');
		$noClean = $input->getOption('no-clean');

		if ($tar) {
			$ext = 'tar';
		}
		else {
			$ext = 'zip';
		}

		$basepath = sprintf('%s/%s/', getcwd(), $env);

		$build = json_decode(file_get_contents($basepath . 'build.json'), true);

		if (!$out) {
			if (isset($build['filename'])) {
				$out = sprintf('%s/target/%s', getcwd(), $build['filename']);
			}
			else {
				$out = sprintf('%s/dist/contao-composer-%s.%s', getcwd(), $env, $ext);
			}
		}

		if (!file_exists($basepath . 'build.json')) {
			throw new \RuntimeException(sprintf('The environment %s does not contain a build.json', $env));
		}

		if (!$noClean) {
			$this->cleanup($output, $env);
		}
		$this->installComposer($output, $basepath);
		$this->installDependencies($output, $env, $basepath);

		$this->buildArchive($output, $build, $basepath, $out);
	}

	protected function cleanup(OutputInterface $output, $env)
	{
		// clean environment
		$clean = $this->getApplication()->find('clean');
		$arguments = array(
			'command' => 'clean',
			'--env'   => $env,
		);
		$returnCode = $clean->run(new ArrayInput($arguments), $output);
		if ($returnCode != 0) {
			throw new \RuntimeException('Could not clean environment');
		}
	}

	protected function installComposer(OutputInterface $output, $basepath)
	{
		$output->writeln('  - <info>Install composer.phar</info>');

		$url = (extension_loaded('openssl') ? 'https' : 'http').'://getcomposer.org/composer.phar';
		$client = new Client();
		$request = $client->get($url);
		$responseBody = EntityBody::factory(fopen($basepath . '/composer/composer.phar', 'w+'));
		$request->setResponseBody($responseBody);
		$request->send();
	}

	protected function installDependencies(OutputInterface $output, $env, $basepath)
	{
		$output->writeln('  - <info>Install dependencies with composer</info>');

		$cmd = 'php composer.phar install --verbose --no-interaction';
		if ($output->isDecorated()) {
			$cmd .= ' --ansi';
		}
		if (strpos($env, 'dev') !== false) {
			$cmd .= ' --prefer-source';
			$cmd .= ' --dev';
		}
		else {
			$cmd .= ' --prefer-dist';
			$cmd .= ' --no-dev';
		}
		$process = new Process($cmd, $basepath . 'composer');
		$process->setTimeout(3600);
		$process->run(
			function($type, $buffer) use ($output) {
				$output->write($buffer);
			}
		);
		if (!$process->isSuccessful()) {
			throw new \RuntimeException($process->getErrorOutput());
		}
	}

	protected function buildArchive(OutputInterface $output, $build, $basepath, $outfile)
	{
		$extension = strtolower(preg_replace('#.*\.(\w+)$#', '$1', $outfile));

		switch ($extension) {
			case 'zip':
				$this->buildZipArchive($output, $build, $basepath, $outfile);
				break;
			case 'tar':
				$this->buildTarArchive($output, $build, $basepath, $outfile);
				break;
			default:
				throw new \RuntimeException('');
		}
	}

	protected function buildZipArchive(OutputInterface $output, $build, $basepath, $outfile)
	{
		$output->writeln(sprintf('  - <info>Build ZIP archive %s</info>', $outfile));

		$zip = new \ZipArchive();
		$zip->open($outfile, \ZipArchive::OVERWRITE);

		$fileCount = 0;

		foreach (
			$build['contents'] as $path
		) {
			$fileCount += $this->addToZipArchive($zip, $basepath . $path, $path);
		}

		$zip->close();

		$output->writeln(sprintf('  - <info>%s files are stored in archive</info>', $fileCount));
	}

	protected function addToZipArchive(\ZipArchive $zip, $source, $target)
	{
		$fileCount = 0;
		if (is_dir($source)) {
			$zip->addEmptyDir($target);
			$iterator = new \FilesystemIterator($source, \FilesystemIterator::CURRENT_AS_PATHNAME);
			foreach ($iterator as $item) {
				$fileCount += $this->addToZipArchive($zip, $item, $target . '/' . basename($item));
			}
		}
		else {
			$zip->addFile($source, $target);
			$fileCount ++;
		}
		return $fileCount;
	}

	protected function buildTarArchive(OutputInterface $output, $build, $basepath, $outfile)
	{
		$output->writeln(sprintf('  - <info>Build TAR archive %s</info>', $outfile));

		$files = array();
		$fileCount = 0;

		foreach (
			$build['contents'] as $path
		) {
			$fileCount += $this->addToTarArchive($files, $basepath . $path, $path);
		}

		$processBuilder = new ProcessBuilder();
		$processBuilder->setWorkingDirectory($basepath);
		$processBuilder->add('tar');
		$processBuilder->add('--create');
		$processBuilder->add('--file');
		$processBuilder->add($outfile);
		foreach ($files as $file) {
			$processBuilder->add($file);
		}
		$process = $processBuilder->getProcess();
		$process->run();

		if (!$process->isSuccessful()) {
			$errorOutput = $process->getErrorOutput();
			$errorOutputLines = explode("\n", $errorOutput);

			$output->writeln(sprintf('  - <error>could not create tar archive %s</error>', $outfile));
			foreach ($errorOutputLines as $errorOutputLine) {
				$output->writeln(sprintf('    <error>%s</error>', $errorOutputLine));
			}
		}
		else {
			$output->writeln(sprintf('  - <info>%s files are stored in archive</info>', $fileCount));
		}
	}

	protected function addToTarArchive(array &$files, $source, $target)
	{
		$fileCount = 0;
		if (!is_link($source) && is_dir($source)) {
			$iterator = new \FilesystemIterator($source, \FilesystemIterator::CURRENT_AS_PATHNAME);
			foreach ($iterator as $item) {
				$fileCount += $this->addToTarArchive($files, $item, $target . '/' . basename($item));
			}
		}
		else {
			$files[] = $target;
			$fileCount ++;
		}
		return $fileCount;
	}
}
