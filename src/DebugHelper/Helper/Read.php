<?php
namespace DebugHelper\Helper;

class Read
{
    /**
     * @var resource
     */
    protected $fileIn;

    /**
     * @var integer
     */
    protected $fileSize;

    /**
     * @param string $file File to clean
     * @throws \Exception
     */
    public function __construct($file)
    {
        if (!is_file($file)) {
            throw new \Exception("Invalid file {$file}");
        }

        $this->fileSize = filesize($file);
        $this->fileIn = fopen($file, 'r');
    }

    /**
     * Sets the value of file.
     *
     * @param integer $lineNumber Line number
     * @return integer
     */
    public function getDepth($lineNumber)
    {
        $currentLineNumber = 0;
        fseek($this->fileIn, 0);

        while (!feof($this->fileIn)) {
            $currentLineNumber++;
            $line = fgets($this->fileIn);
            if ($currentLineNumber == $lineNumber) {
                $outLine = $this->processInputLine($line);
                return $outLine['depth'];

            }
        }
        return $this->getOuterDepth();
    }

    /**
     * Sets the value of file.
     *
     * @return integer
     */
    public function getOuterDepth()
    {
        $depth = PHP_INT_MAX;

        fseek($this->fileIn, 0);
        $max = 1000;
        while (!feof($this->fileIn) && $max-- > 0) {
            $line = fgets($this->fileIn);
            $outLine = $this->processInputLine($line);
            if (!is_null($outLine['depth'])) {
                $depth = min($outLine['depth'], $depth);
            }
        }

        return $depth;
    }

    public function read($start, $depth)
    {
        $currentLineNumber = 0;

        fseek($this->fileIn, 0);

        $lines = [];
        $index = 0;
        $time = 0;
        while (!feof($this->fileIn)) {
            $line = fgets($this->fileIn);
            $currentLineNumber++;
            if ($start && $currentLineNumber < $start) {
                continue;
            }
            $lineInfo = $this->processInputLine($line);
            if ($lineInfo !== false) {
                $time = $lineInfo['time'];
                if ($lineInfo['depth'] == $depth) {
                    if ($index) {
                        $lines[$index]['length'] = ($lineInfo['time'] * 1000000) - $lines[$index]['time'];
                    }
                    $index++;
                    $lines[$index] = [
                        'line'       => $currentLineNumber,
                        'call'       => $lineInfo['call'],
                        'path'       => $lineInfo['path'],
                        'memory'     => $lineInfo['memory'],
                        'time'       => $lineInfo['time'] * 1000000,
                        'length'    => 0,
                        'children'   => 0,
                        'descendant' => 0
                    ];
                } elseif ($start && $lineInfo['depth'] < $depth) {
                    break;
                } elseif ($index && $lineInfo['depth'] == $depth + 1) {
                    $lines[$index]['children']++;
                } elseif ($index) {
                    $lines[$index]['descendant']++;
                }
            }
        }

        $lines[$index]['length'] = ($time * 1000000) - $lines[$index]['time'];

        return $lines;
    }

    /**
     * Process a single trace line
     *
     * @param string $line Contents of the line being processed
     * @return bool
     */
    private function processInputLine($line)
    {
        $reg_exp = '/(?P<time>\d+\.\d+)\s+(?P<memory>\d+)(?P<depth>\s+)->\s+(?P<call>.*)\s+(?P<path>[^\s+]+)$/';
        if (preg_match($reg_exp, $line, $matches)) {
            $matches['depth'] = ceil(strlen($matches['depth']) / 2);
            return $matches;
        } else {
            return false;
        }
    }
}
