<?php
namespace DebugHelper\Cli;

use DebugHelper\Cli\Util\Statistics;
use DebugHelper\Gui\Processor;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Yaml\Yaml;

/**
 * Class CleanCommand
 * @package DebugHelper\Cli
 */
class CleanCommand extends Abstracted
{
    const NAMESPACE_KEEP = 'keep';
    const NAMESPACE_COLLAPSE = 'collapse';
    const NAMESPACE_IGNORE = 'ignore';

    const MATCH_SUBSTR = 'substr';
    const MATCH_REG_EXP = 'regexp';

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
     * Namespaces to be excluded from the trace either fully (IGNORE) of just the children (COLLPASE)
     *
     * @var array
     */
    protected $skipNamespaces = [];

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
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED)
            ->addOption('force', null, InputOption::VALUE_NONE)
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->loadArguments($input);
        $output->writeln(Yaml::dump($this->options, 4));
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
        $config = $input->getOption('config');
        if (!is_file($config)) {
            throw new InvalidArgumentException('There is not file ' . $config);
        }
        $options = Yaml::parse(file_get_contents($config));
        $this->options['force'] = $input->getOption('force');
        $this->options['file'] = $input->getArgument('file');
        $this->options['functions'] = isset($options['functions']) ? $options['functions'] : [];
        $this->options['process'] = isset($options['process']) ? $options['functions'] : 500;
        $this->options['ignore-namespace'] = isset($options['ignore-namespace']) ? $options['ignore-namespace'] : [];
        $this->options['collapse-namespace'] = isset($options['collapse-namespace']) ? $options['collapse-namespace'] : [];
        $this->ignoreNamespaces = [];
        $this->addSkipNamespaceOptions($this->options['ignore-namespace'], self::NAMESPACE_IGNORE);
        $this->addSkipNamespaceOptions($this->options['collapse-namespace'], self::NAMESPACE_COLLAPSE);
        $this->options['skip-path'] = isset($options['skip-path']) ? $options['skip-path'] : [];
    }

    /**
     * @param array $skipNamespaces
     * @param string $type
     */
    private function addSkipNamespaceOptions($skipNamespaces, $type)
    {
        if (!empty($skipNamespaces)) {
            foreach ($skipNamespaces as $namespace) {
                $match = self::MATCH_SUBSTR;
                if (preg_match('/^\/.*\/$/', $namespace)) {
                    $match = self::MATCH_REG_EXP;
                }
                $this->skipNamespaces[] = [
                    'namespace' => $namespace,
                    'type'      => $type,
                    'match'     => $match,
                ];
            }
        }
    }

    /**
     * @param string $file File to clean
     * @throws \Exception
     */
    protected function cleanFile($file)
    {
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
                $processor->setProgress($this->buildProgressBar($fileId));
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
        $progress = $this->buildProgressBar($fileId);
        fseek($fileIn, 0);
        $fileOut = fopen($this->getPathFromId($fileId, 'xt.clean'), 'w');
        $size = 0;
        while (!feof($fileIn) && $count-- > 0) {
            $line = fgets($fileIn);
            $size += strlen($line);
            $lineNo++;
            if ($lineNo % 1000 == 0) {
                $progress->setProgress($size);
            }
            $outLine = $this->processInputLine($line);
            if ($outLine !== false) {
                fwrite($fileOut, $outLine);
                $this->stats->increment('passed');
            }
        }
        $this->stats->sort();

        $usedPaths = array_slice($this->stats->get('path.used', null, []), 0, 20);
        $this->output->writeln('');
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
            $this->stats->get('passed', null, 0),
            $this->stats->get('skipped_by.invalid', null, 0),
            $this->stats->get('skipped_by.functions', null, 0),
            $this->stats->get('skipped_by.path', null, 0),
            $this->stats->get('skipped_by.namespace', null, 0),
            $lineNo
        ));

        return $this->stats->get('passed');
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
        if (!$lineInfo) {
            $this->stats->increment('skipped_by.invalid');

            return false;
        }

        if ($this->ignoreDepth) {
            if ($lineInfo['depth'] > $this->ignoreDepth) {
                $this->stats->increment('namespaces.skipped', $this->ignoring);
                $this->stats->increment('skipped_by.namespace');

                return false;
            } else {
                $this->ignoreDepth = false;
            }
        }
        if (!preg_match('/^(?P<namespace>[^\(]+)(::|->)(?P<method>[^\(]+).*$/', $lineInfo['call'], $matches)) {
            $this->stats->increment('skipped_by.functions');

            return false;
        }

        $skipNamespace = $this->getSkipNamespace($matches['namespace']);
        if ($skipNamespace !== self::NAMESPACE_KEEP) {
            $this->ignoreDepth = $lineInfo['depth'];
            $this->ignoring = $matches['namespace'];
            if ($skipNamespace == self::NAMESPACE_IGNORE) {
                return false;
            }
        } elseif ($this->isIgnoredDirectory($lineInfo['path'])) {
            $this->stats->increment('skipped_by.path');

            return false;
        }

        if ($matches['namespace'] !== $this->ignoring) {
            $this->registerNamespace($matches['namespace']);
        }
        $this->registerPath($lineInfo['path']);

        return $line;

    }

    /**
     * Check if the given namespace should be ignored with the current filters
     *
     * @param string $namespace
     * @return bool
     */
    protected function getSkipNamespace($namespace)
    {
        if ($this->options['functions']) {
            return self::NAMESPACE_KEEP;
        }

        foreach ($this->skipNamespaces as $skipNamespace) {
            if ($skipNamespace['match'] == self::MATCH_SUBSTR) {
                if (substr($namespace, 0, strlen($skipNamespace['namespace'])) == $skipNamespace['namespace']) {
                    return $skipNamespace['type'];
                }
            } else if ($skipNamespace['match'] == self::MATCH_REG_EXP) {
                if (preg_match($skipNamespace['namespace'], $namespace)) {
                    return $skipNamespace['type'];
                }
            }
        }

        return self::NAMESPACE_KEEP;
    }

    /**
     * Check if the given path should be ignored with the current filters
     *
     * @param string $path
     * @return bool
     */
    protected function isIgnoredDirectory($path)
    {
        $result = false;
        foreach ($this->options['skip-path'] as $ignoreDirectory) {
            if (preg_match($ignoreDirectory, $path)) {
                $this->stats->increment('path.skipped', $ignoreDirectory);
                $result = true;
                break;
            }
        }

        return $result;
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

    /**
     * @param string $fileId
     * @return ProgressBar
     */
    private function buildProgressBar($fileId)
    {
        $fileSize = filesize($this->getPathFromId($fileId, 'xt'));
        $progress = new ProgressBar($this->output, $fileSize);

        return $progress;
    }
}
