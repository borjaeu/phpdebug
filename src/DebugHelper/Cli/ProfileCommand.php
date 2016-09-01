<?php
namespace DebugHelper\Cli;

use DebugHelper\Cli\Util\Graphviz;
use DebugHelper\Cli\Util\Progress;

/**
 * Class ProfileCommand
 * @package DebugHelper\Cli
 */
class ProfileCommand extends Abstracted
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
     * @var Graphviz
     */
    private $graphviz;

    /**
     * @var array
     */
    private $namespaces;

    /**
     * @var string
     */
    private $lastNamespace;

    /**
     * @var array
     */
    private $history;

    /**
     * @var array
     */
    private $knownNamespaces = [
        'Doctrine' => true,
        'Symfony\Component\HttpKernel' => true,
        'GuzzleHttp' => true,
        'Sensio\Bundle\FrameworkExtraBundle' => true,
        'SplFileInfo' => false,
        'Monolog' => false,
        'DebugHelper' => true,
        'Symfony\Component\EventDispatcher' => true,
        'Symfony\Component\Console\Output' => true,
        'Symfony\Component\Console\Input' => true,
    ];

    /**
     * Execute the command line
     */
    public function run()
    {
        $this->progress = new Progress();
        $this->namespaces = [];
        $this->history = [];
        if (!empty($this->arguments['help'])) {
            echo "./console profile [file]\n";

            return;
        }

        $this->loadArguments();
        if ($this->options['file']) {
            $this->calcProfile($this->options['file']);
        }
    }

    /**
     * Load the command line argument
     */
    protected function loadArguments()
    {
        $this->options = [];
        $this->options['file'] = isset($this->arguments[2]) ? \DebugHelper::get('debug_dir').$this->arguments[2] : false;
    }

    /**
     * @param string $file File to profile
     * @throws \Exception
     */
    protected function calcProfile($file)
    {
        $this->graphviz = new Graphviz();

        $this->graphviz->addNode('root');

        $this->ignoreDepth = false;
        $this->ignoring = false;
        preg_match('/^(.*\/)?(?P<id>.*?)(\.\w*)?$/', $file, $matches);
        $fileId = $matches['id'];

        if (!is_file("temp/{$fileId}.xt.clean") && empty($this->options['force'])) {
            throw new \Exception("Error Processing file $fileId there is not clean file");
        }

        echo "Generating file {$fileId}.xt.dot\n";

        $fileIn = fopen("temp/{$fileId}.xt.clean", 'r');
        $count = 320000000;
        $lineNo = 0;

        $fileSize = filesize("temp/{$fileId}.xt.clean");
        echo "Starting $fileSize".PHP_EOL;

        fseek($fileIn, 0);

        $size = 0;
        while (!feof($fileIn) && $count-- > 0) {
            $line = fgets($fileIn);
            $size += strlen($line);
            $lineNo++;
            if ($lineNo % 1000 == 0) {
                $status = sprintf('line %d', $lineNo);
                //$this->progress->showStatus($size, $fileSize, $status);
            }
            $this->processInputLine($line);
        }
        //print_r($this->namespaces);
        print_r(count($this->namespaces));

        file_put_contents('profile.dot', $this->graphviz->getDotCode());

        $command = 'dot profile.dot -Tpng -Kcirco -o profile.png';
        echo $command . PHP_EOL;
        shell_exec($command);
    }

    /**
     * Process a single trace line
     *
     * @param string $line Contents of the line being processed
     */
    protected function processInputLine($line)
    {
        $lineInfo =  $this->getLineInfo($line);
        if (!$lineInfo) {
            echo 'Invalid ' . $line . PHP_EOL;

            return;
        }

        if (!preg_match('/^(?P<namespace>[^\(]+)(::|->)(?P<method>[^\(]+).*$/', $lineInfo['call'], $matches)) {
            echo 'Invalid ' . $line . PHP_EOL;

            return;
        }

        list($calledNamespace, $known) = $this->checkNamespace($matches['namespace']);

        if (!$calledNamespace) {
            return;
        }

        if ($this->ignoreDepth) {
            if ($lineInfo['depth'] > $this->ignoreDepth) {
                return;
            } else {
                $this->ignoreDepth = false;
            }
        }

        $label = '';
        if ($known) {
            $this->ignoreDepth = $lineInfo['depth'];
            $label = ' IGNORING ' . $lineInfo['depth'];
        }

        if (!isset($this->namespaces[$calledNamespace])) {
            $this->namespaces[$calledNamespace] = 0;
        }
        $this->namespaces[$calledNamespace]++;

        if ($this->lastNamespace == $calledNamespace) {
            return;
        }
        $this->lastNamespace = $calledNamespace;
        $currentNamespace = $this->whereAmI($lineInfo['depth']);
        array_unshift($this->history, ['namespace' => $calledNamespace, 'depth' => $lineInfo['depth']]);

        $this->graphviz->addNode($calledNamespace);
        $this->graphviz->addEdge($currentNamespace, $calledNamespace);

        echo sprintf(
            '%s%s%s (%s) %s%s',
            str_repeat('.', $lineInfo['depth']),
            $calledNamespace,
            $label,
            $currentNamespace,
            basename($lineInfo['path']),
            PHP_EOL
        );
    }

    private function checkNamespace($namespace)
    {
        foreach ($this->knownNamespaces as $knownNamespace => $shouldBeKept) {
            if (substr($namespace, 0, strlen($knownNamespace)) == $knownNamespace) {
                $namespace = $shouldBeKept ? $knownNamespace : '';

                return [$namespace, true];
            }
        }

        return [$namespace, false];
    }

    /**
     * Extract information from a trace line
     *
     * @param string $line Line to process
     * @return bool|array
     */
    protected function getLineInfo($line)
    {
        $regExp = '/(?P<time>\d+\.\d+)\s+(?P<memory>\d+)(?P<depth>\s+)->\s+(?P<call>.*)\s+(?P<path>[^\s+]+)$/';
        if (preg_match($regExp, $line, $matches)) {
            return [
                'time' => $matches['time'],
                'depth' => ceil(strlen($matches['depth']) / 2),
                'call' => $matches['call'],
                'path' => $matches['path'],
                'path_length' => count(explode('/', $matches['path'])),
            ];
        }

        return false;
    }

    private function whereAmI($depth)
    {
        foreach ($this->history as $item) {
            if ($item['depth'] < $depth) {
                return $item['namespace'];
            }
        }

        return 'root';
    }
}
