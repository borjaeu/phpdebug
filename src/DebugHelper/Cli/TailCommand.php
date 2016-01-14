<?php
namespace DebugHelper\Cli;

use DebugHelper\Cli\Processor\Abstracted as AbstractProcessor;

class TailCommand extends Abstracted
{
    /**
     * @var AbstractProcessor[]
     */
    protected $processors;

    /**
     * Execute the command line
     */
    public function run()
    {
        $this->loadProcessors();
        if (!isset($this->arguments[2])) {
            throw new \Exception('A file is required');
        }
        $file = $this->arguments[2];
        $this->monitorFile($file);
    }

    protected function loadProcessors()
    {
        $this->processors = [];
        $availableProcessors = glob(__DIR__ . '/Processor/*.php');

        foreach($availableProcessors as $processorFile) {
            $processorName = preg_replace('/^.+\/(\w+)\.php$/', 'DebugHelper\\Cli\\Processor\\\\$1', $processorFile);
            if (!in_array($processorName, ['DebugHelper\Cli\Processor\Abstracted', 'DebugHelper\Cli\Processor\Clean'])) {
                $this->processors[] = new $processorName();
            }
        }
    }

    /**
     * Monitors the file for changes
     *
     * @param string $file File path
     */
    protected function monitorFile($file)
    {
        $size = 0;
        $lineNumber = 0;
        while (true) {
            clearstatcache();
            $currentSize = filesize($file);
            if ($size == $currentSize) {
                usleep(100);
                continue;
            }

            $fh = fopen($file, "r");
            fseek($fh, $size);

            while ($line = fgets($fh)) {
                $lineNumber++;
                $output = $this->processLine($line);
                if ($output) {
                    echo $output . PHP_EOL;
                }
            }

            fclose($fh);
            $size = $currentSize;
        }
    }

    protected function processLine($line)
    {
        foreach($this->processors as $processor) {
            if ($processor->matches($line)) {
                return $processor->process($line);
            }
        }
        if (isset($this->arguments[3]) && $this->arguments[3] == 'clean') {
            return false;
        }
        return $line;
    }
}