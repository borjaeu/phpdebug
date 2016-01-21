<?php
namespace DebugHelper\Cli;

use DebugHelper\Cli\Util\Progress;
use DebugHelper\Gui\Processor;

class CleanCommand extends Abstracted
{
    protected $ignoreDepth;

    protected $ignoring;

    protected $ignoreNamespaces = [];

    protected $namespaces = [];

    protected $skipped = [];

    protected $stats;

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
        $this->namespaces = [];
        $this->progress = new Progress();
        if (!empty($this->arguments['help'])) {
            echo "./console clean [file] [--force] [--functions] [--namespaces='namespace1, namespace2]\n";
            return;
        }

        $this->loadArguments();
        if ($this->options['file']) {
            $this->cleanFile($this->options['file']);
        } else {
            $files = glob(\DebugHelper::getDebugDir() . '/*.xt');
            foreach($files as $file) {
                $this->cleanFile($file);
            }
        }
    }

    protected function loadArguments()
    {
        $this->options = [];
        $this->options['functions'] = !empty($this->arguments['functions']);
        $this->options['force'] = !empty($this->arguments['force']);
        $this->options['file'] = isset($this->arguments[2]) ? \DebugHelper::getDebugDir() . $this->arguments[2] : false;

        if (!empty($this->arguments['namespaces'])) {
            $this->ignoreNamespaces = preg_split('/\s*,\s*/', $this->arguments['namespaces']);
        }
    }

    protected function cleanFile($file)
    {
        $this->stats = [
            'invalid'   => 0,
            'functions' => 0,
            'skipped'   => 0
        ];
        $this->namespaces = [];
        $this->skipped = [];
        $this->ignoreDepth = false;
        $this->ignoring = false;
        preg_match('/^(.*\/)?(?P<id>.*?)(\.\w*)?$/', $file, $matches);
        $fileId = $matches['id'];

        if (!is_file('temp/' . $fileId . '.xt')) {
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

        $fileSize = filesize("temp/{$fileId}.xt");;
        echo "Starting $fileSize" . PHP_EOL;

        $totalPassed = 0;
        fseek($fileIn, 0);
        $fileOut = fopen("temp/{$fileId}.xt.clean", 'w');
        $size = 0;
        while (!feof($fileIn) && $count-- > 0) {
            $line = fgets($fileIn);
            $size += strlen($line);
            $lineNo++;
            if ($lineNo % 1000 == 0)  {
                $status = sprintf('ratio %0.2f%%; line %d', ($totalPassed/ $lineNo) * 100, $lineNo);
                $this->progress->showStatus($size, $fileSize, $status);
            }
            $outLine = $this->processInputLine($line);
            if ($outLine !== false) {
                fwrite($fileOut, $outLine);
                $totalPassed++;
            }
        }
        $this->namespaces = array_filter($this->namespaces, function($element){
            return $element > 10;
        });
        $this->skipped = array_filter($this->skipped, function($element){
            return $element > 0;
        });

        asort($this->namespaces);
        $namespaces = array_slice($this->namespaces, -20);
        echo PHP_EOL . PHP_EOL . "\033[32mMost used namespaces\033[0m" . PHP_EOL;
        foreach($namespaces as $namespace => $lines) {
            printf('%6d %s%s', $lines, $namespace, PHP_EOL);
        }

        asort($this->skipped);
        echo PHP_EOL . "\033[32mSkipped namespaces\033[0m" . PHP_EOL;
        foreach($this->skipped as $namespace => $lines) {
            printf('%6d %s%s', $lines, $namespace, PHP_EOL);
        }

        printf("
\033[32mStats\033[0m
%6d valid lines
%6d invalid lines
%6d functions skipped
%6d namespaces skipped
----
\033[31m%4d\033[0m Total lines
",
            $totalPassed,
            $this->stats['invalid'],
            $this->stats['functions'],
            $this->stats['skipped'],
            $lineNo
        );
        return $totalPassed;
    }

    protected function processInputLine($line)
    {
        $line_info =  $this->getLineInfo($line);
        if ($line_info) {
            if ($this->ignoreDepth) {
                if ($line_info['depth'] > $this->ignoreDepth) {
                    $this->skipped[$this->ignoring]++;
                    $this->stats['skipped']++;
                    return false;
                } else {
                    $this->ignoreDepth = false;
                }
            }
            if (preg_match('/^(?P<namespace>[^\(]+)(::|->)(?P<method>[^\(]+).*$/', $line_info['call'], $matches)) {
                if ($this->isIgnoredNamespace($matches['namespace'])) {
                    $this->ignoreDepth = $line_info['depth'];
                    $this->ignoring = $matches['namespace'];
                    if (!isset($this->skipped[$this->ignoring])) {
                        $this->skipped[$this->ignoring] = 0;
                    }
                }
            } else {
                $this->stats['functions']++;
                return false;
            }
            if ($matches['namespace'] !== $this->ignoring) {
                $this->registerNamespace($matches['namespace']);
            }
            return $line;
        } else {
            $this->stats['invalid']++;
            return false;
        }
    }

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

    protected function getLineInfo($line)
    {
        $reg_exp = '/(?P<time>\d+\.\d+)\s+(?P<memory>\d+)(?P<depth>\s+)->\s+(?P<call>.*)\s+(?P<path>[^\s+]+)$/';
        if (preg_match($reg_exp, $line, $matches)) {
            $matches['depth'] = ceil(strlen($matches['depth']) / 2);
            $matches['path_length'] = count(explode('/', $matches['path']));
            return $matches;
        } else {
            return false;
        }
    }

    protected function registerNamespace($namespace)
    {
        $levels = explode('\\', $namespace);
        while(!empty($levels)) {
            $namespace = implode('\\', $levels);
            if (!isset($this->namespaces[$namespace])) {
                $this->namespaces[$namespace] = 0;
            }
            array_pop($levels);
            $this->namespaces[$namespace]++;
        }
    }
}
