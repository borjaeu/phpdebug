<?php
namespace DebugHelper\Helper;

use DebugHelper\Tools\Output;

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
     * @var array
     */
    private $lines;

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
     * Gets the depth for the given trace line number
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
     * Gets the minimum depth for the whole trace.
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

    /**
     * Reads the information a fetches stats for the given depth
     *
     * @param int $startLine
     * @param int $depth
     * @return array
     */
    public function read($startLine, $depth)
    {
        $output = new Output();
        $currentLineNumber = 0;

        fseek($this->fileIn, 0);

        $this->lines = [];
        $index = 0;

        $lastLineInfo = false;
        while (!feof($this->fileIn)) {
            $line = fgets($this->fileIn);
            $currentLineNumber++;
            if ($startLine && $currentLineNumber < $startLine) {
                continue;
            }
            $lineInfo = $this->processInputLine($line);
            if ($lineInfo !== false) {
                $lastLineInfo = [
                    'xt_line'    => $currentLineNumber,
                    'call'       => $lineInfo['call'],
                    'path'       => $lineInfo['path'],
                    'line'       => $lineInfo['line'],
                    'file'       => basename($lineInfo['path']),
                    'link'       => $output->buildUrl($lineInfo['path'], $lineInfo['line']),
                    'mem_acum'   => $lineInfo['memory'],
                    'mem_spent'  => 0,
                    'time_acum'  => ($lineInfo['time'] * 1000),
                    'time_spent' => 0,
                    'children'   => 0,
                    'descendant' => 0,
                ];
                if ($lineInfo['depth'] == $depth) {
                    $index++;
                    $this->lines[$index] = $lastLineInfo;
                    $this->calculateSpent($index-1, $lastLineInfo);
                } elseif ($startLine && $lineInfo['depth'] < $depth) {
                    break;
                } elseif ($index && $lineInfo['depth'] == $depth + 1) {
                    $this->lines[$index]['children']++;
                } elseif ($index) {
                    $this->lines[$index]['descendant']++;
                }
            }
        }

        $this->calculateSpent($index, $lastLineInfo);

        return $this->lines;
    }

    /**
     * @param integer $index
     * @param array   $latestInfo
     */
    private function calculateSpent($index, array $latestInfo)
    {
        if (isset($this->lines[$index])) {
            $this->lines[$index]['mem_spent'] = $latestInfo['mem_acum'] - $this->lines[$index]['mem_acum'];
            $this->lines[$index]['time_spent'] = $latestInfo['time_acum'] - $this->lines[$index]['time_acum'];
        }
    }

    /**
     * Process a single trace line
     *
     * @param string $line Contents of the line being processed
     * @return bool
     */
    private function processInputLine($line)
    {
        $reg_exp = '/(?P<time>\d+\.\d+)\s+(?P<memory>\d+)(?P<depth>\s+)->\s+(?P<call>.*)\s+(?P<path>[^\s+]+)(:(?P<line>[\d+]+))$/';
        if (preg_match($reg_exp, $line, $matches)) {
            $matches['depth'] = ceil(strlen($matches['depth']) / 2);

            return $matches;
        }

        return false;
    }
}
