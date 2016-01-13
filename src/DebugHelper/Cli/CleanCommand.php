<?php
namespace DebugHelper\Cli;

use DebugHelper\Gui\Processor;

class CleanCommand extends Abstracted
{
    protected $file;

    protected $ignoreDepth;

    protected $ignoring;

    protected $ignoreNamespaces = array(
        'Doctrine',
        'Composer',
        'DebugHelper',
        'Symfony\Component\Debug',
        'Symfony\Component\Config',
        'Symfony\Component\Yaml',
        //'Symfony\Component\EventDispatcher',
        'Symfony\Component\OptionsResolver',
//        'Symfony\Component\Form',
        'Symfony\Component\Debug',
//        'Monolog'
    );

    protected $namespaces = [];

    protected $skipped = [];

    protected $invalidLines;

    /**
     * Execute the command line
     */
    public function run()
    {
        if (isset($this->arguments[2])) {
            $file = \DebugHelper::getDebugDir() . $this->arguments[2];
            $this->cleanFile($file);
        } else {
            $files = glob(\DebugHelper::getDebugDir() . '/*.xt');
            foreach($files as $file) {
                $this->cleanFile($file);
            }
        }
    }

    protected function cleanFile($file)
    {
        $this->invalidLines = 0;
        $this->namespaces = [];
        $this->skipped = [];
        preg_match('/^(.*\/)?(?P<id>.*?)(\.\w*)?$/', $file, $matches);
        $fileId = $matches['id'];

        if (!is_file('temp/' . $fileId . '.xt')) {
            throw new \Exception("Error Processing file $fileId");
        }

        if (is_file("temp/{$fileId}.xt.clekkan") && empty($this->arguments[3])) {
            echo "Already exists {$fileId}.xt.clean\n";
        } else {
            echo "Generating file {$fileId}.xt.clean\n";
            $lines = $this->generateFiles($fileId);
            if ($lines < 10000) {
                $processor = new Processor();
                $processor->process($fileId);
            }
        }
    }

    /**
     * Sets the value of file.
     */
    protected function generateFiles($fileId)
    {
        $this->lines = array();

        $fileIn = fopen("temp/{$fileId}.xt", 'r');
        $count = 320000000;
        $lineNo = 0;
        $lineCount = 0;

        $fileSize = filesize("temp/{$fileId}.xt");;
        echo "Starting $fileSize" . PHP_EOL;
        /*while (!feof($fileIn) && $count-- > 0) {
            fgets($fileIn);
            $lineCount++;
        }
        echo "Total lines $lineCount" . PHP_EOL;*/

        $totalPassed = 0;
        fseek($fileIn, 0);
        $fileOut = fopen("temp/{$fileId}.xt.clean", 'w');
        $size = 0;
        while (!feof($fileIn) && $count-- > 0) {
            $line = fgets($fileIn);
            $size += strlen($line);
            $lineNo++;
            if ($lineNo % 1000 == 0) {
                printf('%0.2f%% %0.2f%% %d/%d %d/%d' . PHP_EOL, ($size / $fileSize) * 100, ($totalPassed/ $lineNo) * 100, $lineNo, $lineCount, $size, $fileSize);
                //printf('%0.2f%% %d/%d %d/%d' . PHP_EOL, ($lineNo / $lineCount) * 100, $lineNo, $lineCount, $size, $fileSize);
            }
            $outLine = $this->processInputLine($line);
            if ($outLine !== false) {
                fwrite($fileOut, $outLine);
                $totalPassed++;
            }
        }
        $this->namespaces = array_filter ($this->namespaces, function($element){
            return $element > 10;
        });
        $this->skipped = array_filter ($this->skipped, function($element){
            return $element > 0;
        });

        asort($this->namespaces);
        print_r($this->namespaces);
        print_r($this->skipped);
        echo <<<INFO
$totalPassed lines of $lineCount has gone through.
{$this->invalidLines} invalid lines.

INFO;

        return $totalPassed;
    }

    protected function processInputLine($line)
    {
        $line_info =  $this->getLineInfo($line);
        if ($line_info) {
            if ($this->ignoreDepth) {
                if ($line_info['depth'] > $this->ignoreDepth) {
                    $this->skipped[$this->ignoring]++;
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
                return false;
            }

            $this->registerNamespace($matches['namespace']);

            return $line;
        } else {
            $this->invalidLines++;
        }
    }

    protected function isIgnoredNamespace($namespace)
    {
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
