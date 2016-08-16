<?php
namespace DebugHelper\Cli;

use DebugHelper\Helper\Read;

class ReadCommand extends Abstracted
{
    /**
     * Command line options
     *
     * @var array
     */
    protected $inputFile;

    /**
     * Command line options
     *
     * @var integer
     */
    protected $inputLine;

    /**
     * @var resource
     */
    protected $fileIn;

    /**
     * @var integer
     */
    protected $fileSize;

    /**
     * Execute the command line
     */
    public function run()
    {
        if (!empty($this->arguments['help'])) {
            echo "./console read file\n";
            return;
        }

        $this->loadArguments();
        if (!$this->inputFile) {
            echo 'Invalid file' . PHP_EOL;
        } else {
            $this->readFile($this->inputFile);
        }
    }

    /**
     * Load the command line argument
     */
    protected function loadArguments()
    {
        $this->options = [];
        $this->inputFile = isset($this->arguments[2]) ? \DebugHelper::get('debug_dir') . $this->arguments[2] : false;
        $this->inputLine = isset($this->arguments[3]) ? $this->arguments[3] : 0;
    }

    /**
     * @param string $file File to clean
     * @throws \Exception
     */
    protected function readFile($file)
    {
        preg_match('/^(.*\/)?(?P<id>.*?)(\.\w*)?$/', $file, $matches);
        $fileId = $matches['id'];

        if (!is_file('temp/' . $fileId . '.xt')) {
            throw new \Exception("Error Processing file $fileId");
        }

        if (!is_file("temp/{$fileId}.xt.clean")) {
            throw new \Exception("Invalid file {$fileId}.xt.clean");
        }
        $reader = new Read("temp/{$fileId}.xt.clean");

        $this->fileSize = filesize("temp/{$fileId}.xt.clean");
        $this->fileIn = fopen("temp/{$fileId}.xt.clean", 'r');

        echo "Reading file {$fileId}.xt.clean\n";
        if ($this->inputLine) {
            $start = $this->inputLine + 1;
            $depth = $reader->getDepth($this->inputLine) + 1;
            echo "Depth $depth from $start\n";
        } else {
            $depth = $reader->getOuterDepth();
            $start = 0;
            echo "Outer depth $depth\n";
        }

        $lines = $reader->read($start, $depth);

        $maxWidth = 0;
        foreach($lines as $line) {
            $maxWidth = max($maxWidth, strlen($line['call']) + 1);
        }

        foreach ($lines as $lineInfo) {
            printf(
                '%6d %s %s [%03d/%-06d] [%08d/%-08d] %s' . PHP_EOL,
                $lineInfo['line'],
                $lineInfo['call'],
                str_repeat(' ', $maxWidth - strlen($lineInfo['call'])),
                $lineInfo['children'],
                $lineInfo['descendant'],
                $lineInfo['time'],
                $lineInfo['length'],
                $lineInfo['path']
            );
        }
    }
}
