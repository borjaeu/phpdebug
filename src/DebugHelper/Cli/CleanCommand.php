<?php
namespace DebugHelper\Cli;

use DebugHelper\Cli\Util\Statistics;
use DebugHelper\Gui\Processor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Class CleanCommand
 * @package DebugHelper\Cli
 */
class CleanCommand extends Abstracted
{
    /**
     * Minimum depth for the current ignore namespace
     *
     * @var integer
     */
    protected $ignoreDepth;

    /**
     * Currently ignoring
     *
     * @var bool
     */
    protected $ignoring;

    /**
     * Namespaces to be excluded from the trace
     *
     * @var array
     */
    protected $ignoreNamespaces = [];

    /**
     * Namespaces to be excluded from the trace
     *
     * @var array
     */
    protected $ignoreDirectories = [];

    /**
     * Numeric statistics
     *
     * @var Statistics
     */
    protected $stats;

    /**
     * Command line options
     *
     * @var array
     */
    protected $options;

    /**
     * Progress display for CLI
     *
     * @var ProgressBar
     */
    protected $progress;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('trace:clean')
            ->setDescription('Clean and simplify trace files')
            ->addArgument('file', InputArgument::OPTIONAL)
            ->addOption('functions', null, InputOption::VALUE_REQUIRED)
            ->addOption('force', null, InputOption::VALUE_NONE)
            ->addOption('skip-namespace', null, InputOption::VALUE_REQUIRED)
            ->addOption('skip-path', null, InputOption::VALUE_REQUIRED)
            ->addOption('process', null, InputOption::VALUE_REQUIRED, 'Number of lines for which the file should be processed', 500)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $this->loadArguments($input);
        if ($this->options['file']) {
            $this->cleanFile($this->options['file']);
        } else {
            $files = glob($this->getPathFromId('*', 'xt'));
            foreach ($files as $file) {
                $this->cleanFile(realpath($file));
            }
        }
    }

    /**
     * Load the command line argument
     */
    protected function loadArguments(InputInterface $input)
    {
        $this->options = [];
        $this->options['functions'] = $input->getOption('functions');
        $this->options['force'] = $input->getOption('force');
        $this->options['file'] = $input->getArgument('file');
        $this->options['process'] = $input->getOption('process');

        if (!empty($input->getOption('skip-namespace'))) {
            $this->ignoreNamespaces = preg_split('/\s*,\s*/', $input->getOption('skip-namespace'));
        }
        if (!empty($input->getOption('skip-path'))) {
            $this->ignoreDirectories = preg_split('/\s*,\s*/', $input->getOption('skip-path'));
        }
    }

    /**
     * @param string $file File to clean
     * @throws \Exception
     */
    protected function cleanFile($file)
    {
        var_dump($file);

        $this->stats = new Statistics();
        $this->ignoreDepth = false;
        $this->ignoring = false;
        preg_match('/^(.*\/)?(?P<id>.*?)(\.\w*)?$/', $file, $matches);
        $fileId = $matches['id'];

        if (!is_file($this->getPathFromId($fileId, 'xt'))) {
            throw new \Exception("Error Processing file $fileId");
        }

        if (is_file($this->getPathFromId($fileId, 'xt.clean')) && empty($this->options['force'])) {
            $this->output->writeln("Already exists {$fileId}.xt.clean");
        } else {
            $this->output->writeln("Generating file {$fileId}.xt.clean");
            $lines = $this->generateFiles($fileId);
            $this->output->writeln($lines);
            if ($lines < $this->options['process']) {
                $processor = new Processor();
                $processor->setProgress($this->progress);
                $this->output->writeln("Generating structure for {$fileId}");
                $processor->process($fileId);
            }
        }
    }

    /**
     * Sets the value of file.
     *
     * @param string $fileId Identifier for the file
     * @return integer
     */
    protected function generateFiles($fileId)
    {
        $fileIn = fopen($this->getPathFromId($fileId, 'xt'), 'r');
        $count = 320000000;
        $lineNo = 0;

        $fileSize = filesize($this->getPathFromId($fileId, 'xt'));
        $this->output->writeln("Starting $fileSize");

        $this->progress = new ProgressBar($this->output, $fileSize);
        $totalPassed = 0;
        fseek($fileIn, 0);
        $fileOut = fopen($this->getPathFromId($fileId, 'xt.clean'), 'w');
        $size = 0;
        while (!feof($fileIn) && $count-- > 0) {
            $line = fgets($fileIn);
            $size += strlen($line);
            $lineNo++;
            if ($lineNo % 1000 == 0) {
                $this->progress->setProgress($size);
            }
            $outLine = $this->processInputLine($line);
            if ($outLine !== false) {
                fwrite($fileOut, $outLine);
                $totalPassed++;
            }
        }
        $this->stats->sort();

        $usedPaths = array_slice($this->stats->get('path.used', null, []), 0, 20);
        $this->output->writeln('<info>Most used paths</info>');
        foreach ($usedPaths as $path => $lines) {
            $this->output->writeln(sprintf('%6d %s', $lines, $path));
        }

        $usedNamespaces = array_slice($this->stats->get('namespaces.used', null, []), 0, 20);
        $this->output->writeln('<info>Most used namespaces</info>');

        foreach ($usedNamespaces as $namespace => $lines) {
            $this->output->writeln(sprintf('%6d %s', $lines, $namespace));
        }

        $skippedNamespaces = array_slice($this->stats->get('namespaces.skipped', null, []), 0, 20);
        $this->output->writeln('<info>Skipped namespaces</info>');
        foreach ($skippedNamespaces as $namespace => $lines) {
            $this->output->writeln(sprintf('%6d %s', $lines, $namespace));
        }

        $skippedPath = array_slice($this->stats->get('path.skipped', null, []), 0, 20);
        $this->output->writeln('<info>Skipped paths</info>');
        foreach ($skippedPath as $path => $lines) {
            $this->output->writeln(sprintf('%6d %s', $lines, $path));
        }

        $this->output->writeln(sprintf(
            "
\033[32mStats\033[0m
%6d valid lines
%6d invalid lines
%6d functions skipped
%6d files excluded
%6d namespaces skipped
------
\033[31m%6d\033[0m Total lines
",
            $totalPassed,
            $this->stats->get('skipped_by.invalid', null, 0),
            $this->stats->get('skipped_by.functions', null, 0),
            $this->stats->get('skipped_by.path', null, 0),
            $this->stats->get('skipped_by.namespace', null, 0),
            $lineNo
        ));

        return $totalPassed;
    }

    /**
     * Process a single trace line
     *
     * @param string $line Contents of the line being processed
     * @return bool
     */
    protected function processInputLine($line)
    {
        $lineInfo =  $this->getLineInfo($line);
        if ($lineInfo) {
            if ($this->ignoreDepth) {
                if ($lineInfo['depth'] > $this->ignoreDepth) {
                    $this->stats->increment('namespaces.skipped', $this->ignoring);
                    $this->stats->increment('skipped_by.namespace');

                    return false;
                } else {
                    $this->ignoreDepth = false;
                }
            }
            if (preg_match('/^(?P<namespace>[^\(]+)(::|->)(?P<method>[^\(]+).*$/', $lineInfo['call'], $matches)) {
                if ($this->isIgnoredNamespace($matches['namespace'])) {
                    $this->ignoreDepth = $lineInfo['depth'];
                    $this->ignoring = $matches['namespace'];
                } elseif ($this->isIgnoredDirectory($lineInfo['path'])) {
                    $this->stats->increment('skipped_by.path');

                    return false;
                }
            } else {
                $this->stats->increment('skipped_by.functions');

                return false;
            }
            if ($matches['namespace'] !== $this->ignoring) {
                $this->registerNamespace($matches['namespace']);
            }
            $this->registerPath($lineInfo['path']);

            return $line;
        } else {
            $this->stats->increment('skipped_by.invalid');

            return false;
        }
    }

    /**
     * Check if the given namespace should be ignored with the current filters
     *
     * @param string $namespace
     * @return bool
     */
    protected function isIgnoredNamespace($namespace)
    {
        if ($this->options['functions']) {
            return false;
        }

        foreach ($this->ignoreNamespaces as $ignoreNamespaces) {
            if (substr($namespace, 0, strlen($ignoreNamespaces)) == $ignoreNamespaces) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the given path should be ignored with the current filters
     *
     * @param string $path
     * @return bool
     */
    protected function isIgnoredDirectory($path)
    {

        foreach ($this->ignoreDirectories as $ignoreDirectory) {
            if (strpos($path, $ignoreDirectory) !== false) {
                $this->stats->increment('path.skipped', $ignoreDirectory);

                return true;
            }
        }

        return false;
    }

    /**
     * Extract information from a trace line
     *
     * @param string $line Line to process
     * @return bool
     */
    protected function getLineInfo($line)
    {
        $regExp = '/(?P<time>\d+\.\d+)\s+(?P<memory>\d+)(?P<depth>\s+)->\s+(?P<call>.*)\s+(?P<path>[^\s+]+)$/';
        if (preg_match($regExp, $line, $matches)) {
            $matches['depth'] = ceil(strlen($matches['depth']) / 2);
            $matches['path_length'] = count(explode('/', $matches['path']));

            return $matches;
        }

        return false;
    }

    /**
     * Save a namespace for statistics
     *
     * @param string $namespace
     */
    protected function registerNamespace($namespace)
    {
        $levels = explode('\\', $namespace);
        while (!empty($levels)) {
            $namespace = implode('\\', $levels);
            $this->stats->increment('namespaces.used', $namespace);
            array_pop($levels);
        }
    }

    /**
     * Save a namespace for statistics
     *
     * @param string $path
     */
    protected function registerPath($path)
    {
        $levels = explode('/', $path);
        while (!empty($levels)) {
            $namespace = implode('/', $levels);
            $this->stats->increment('path.used', $namespace);
            array_pop($levels);
        }
    }
}
