<?php
namespace DebugHelper\Cli;

use DebugHelper\Cli\Util\Progress;
use DebugHelper\Cli\Util\Statistics;
use DebugHelper\Gui\Processor;

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
     * @var Progress
     */
    protected $progress;

    /**
     * Execute the command line
     */
    public function run()
    {
        $this->progress = new Progress();
        if (!empty($this->arguments['help'])) {
            echo "./console clean [file] [--force] [--skip-function] [--skip-namespace='namespace1, namespace2]\n";

            return;
        }

        $this->loadArguments();
        if ($this->options['file']) {
            $this->cleanFile($this->options['file']);
        } else {
            $files = glob(\DebugHelper::get('debug_dir').'/*.xt');
            foreach ($files as $file) {
                $this->cleanFile($file);
            }
        }
    }

    /**
     * Load the command line argument
     */
    protected function loadArguments()
    {
        $this->options = [];
        $this->options['functions'] = !empty($this->arguments['functions']);
        $this->options['force'] = !empty($this->arguments['force']);
        $this->options['file'] = isset($this->arguments[2]) ? \DebugHelper::get('debug_dir').$this->arguments[2] : false;

        if (!empty($this->arguments['skip-namespace'])) {
            $this->ignoreNamespaces = preg_split('/\s*,\s*/', $this->arguments['skip-namespace']);
        }
        if (!empty($this->arguments['skip-path'])) {
            $this->ignoreDirectories = preg_split('/\s*,\s*/', $this->arguments['skip-path']);
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

        if (!is_file('temp/'.$fileId.'.xt')) {
            throw new \Exception("Error Processing file $fileId");
        }

        if (is_file("temp/{$fileId}.xt.clean") && empty($this->options['force'])) {
            echo "Already exists {$fileId}.xt.clean\n";
        } else {
            echo "Generating file {$fileId}.xt.clean\n";
            $lines = $this->generateFiles($fileId);
            if ($lines < 10000) {
                $processor = new Processor();
                $processor->setProgress($this->progress);
                echo "Generating structure for {$fileId}\n";
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
        $fileIn = fopen("temp/{$fileId}.xt", 'r');
        $count = 320000000;
        $lineNo = 0;

        $fileSize = filesize("temp/{$fileId}.xt");
        echo "Starting $fileSize".PHP_EOL;

        $totalPassed = 0;
        fseek($fileIn, 0);
        $fileOut = fopen("temp/{$fileId}.xt.clean", 'w');
        $size = 0;
        while (!feof($fileIn) && $count-- > 0) {
            $line = fgets($fileIn);
            $size += strlen($line);
            $lineNo++;
            if ($lineNo % 1000 == 0) {
                $status = sprintf('ratio %0.2f%%; line %d', ($totalPassed/ $lineNo) * 100, $lineNo);
                $this->progress->showStatus($size, $fileSize, $status);
            }
            $outLine = $this->processInputLine($line);
            if ($outLine !== false) {
                fwrite($fileOut, $outLine);
                $totalPassed++;
            }
        }
        $this->stats->sort();

        $usedPaths = array_slice($this->stats->get('path.used', null, []), 0, 20);
        echo PHP_EOL.PHP_EOL."\033[32mMost used paths\033[0m".PHP_EOL;
        foreach ($usedPaths as $path => $lines) {
            printf('%6d %s%s', $lines, $path, PHP_EOL);
        }

        $usedNamespaces = array_slice($this->stats->get('namespaces.used', null, []), 0, 20);
        echo PHP_EOL.PHP_EOL."\033[32mMost used namespaces\033[0m".PHP_EOL;
        foreach ($usedNamespaces as $namespace => $lines) {
            printf('%6d %s%s', $lines, $namespace, PHP_EOL);
        }

        $skippedNamespaces = array_slice($this->stats->get('namespaces.skipped', null, []), 0, 20);
        echo PHP_EOL."\033[32mSkipped namespaces\033[0m".PHP_EOL;
        foreach ($skippedNamespaces as $namespace => $lines) {
            printf('%6d %s%s', $lines, $namespace, PHP_EOL);
        }

        $skippedPath = array_slice($this->stats->get('path.skipped', null, []), 0, 20);
        echo PHP_EOL."\033[32mSkipped paths\033[0m".PHP_EOL;
        foreach ($skippedPath as $path => $lines) {
            printf('%6d %s%s', $lines, $path, PHP_EOL);
        }

        printf(
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
        );

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
