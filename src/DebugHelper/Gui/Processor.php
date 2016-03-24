<?php
namespace DebugHelper\Gui;

use DebugHelper\Cli\Util\Progress;

class Processor
{
    protected $min_depth;

    protected $shorter_path;

    protected $last_time = 0;

    protected $lines;

    /**
     * Progress display for CLI
     *
     * @var Progress
     */
    protected $progress;

    /**
     * Sets the value of file.
     *
     * @param string $file the file
     */
    public function process($fileId, $run = true)
    {
        $fileIn     = \DebugHelper::getDebugDir() . '/' . $fileId . '.xt.clean';
        $fileOut    = \DebugHelper::getDebugDir() . '/' . $fileId . '.xt.json';

        if (!is_file($fileIn)) {
            throw new \Exception("Error Processing file {$fileIn}");
        }
        if ($run) {
            $this->generateFiles($fileIn, $fileOut);
        } elseif (is_file($fileOut)) {
            $this->lines = json_decode(file_get_contents($fileOut), true);
        } else {
            throw new \Exception("No processed file {$fileOut}");
        }
    }

    /**
     * @param Progress $progress
     */
    public function setProgress($progress)
    {
        $this->progress = $progress;
    }

    /**
     * Sets the value of file.
     */
    protected function generateFiles($filename, $fileOut)
    {
        $this->lines = array();

        $fileIn = fopen($filename, 'r');
        $this->min_depth = 65000;
        $this->shorter_path = 65000;
        $count = 320000000;
        $line_no = 0;
        $lineCount = 0;

        while (!feof($fileIn) && $count-- > 0) {
            fgets($fileIn);
            $lineCount++;
        }

        echo $lineCount;

        fseek($fileIn, 0);
        while (!feof($fileIn) && $count-- > 0) {
            $line = fgets($fileIn);
            $line_no++;
            if (!empty($this->progress) && $line_no % 100 === 0) {
                $this->progress->showStatus($line_no, $lineCount);
            }
            $this->preProcessInputLine($line, $line_no);
        }
        fclose($fileIn);

        foreach ($this->lines as $i => & $line) {
            $this->postProcessInputLine($i, $line);
        }
        file_put_contents($fileOut, json_encode($this->lines, JSON_PRETTY_PRINT));
    }

    public function getTree()
    {
        $children = $this->getChildren(1, $this->getMinDepth(), true);

        return array(
            'name' => 'root',
            'children' => $children
        );
    }

    public function getLines()
    {
        return $this->lines;
    }

    protected function getMinDepth()
    {
        $minDepth = 100;
        for ($i = 1; $i < 20; $i++) {
            if ($this->lines[$i]['depth'] < $minDepth) {
                $minDepth = $this->lines[$i]['depth'];
            }
        }
        return $minDepth;
    }

    protected function getChildren($index, $maxDepth, $force = false)
    {
        $result = array();

        $max_time_children = 0;
        $depth = 1000;
        for ($i = $index; $i < count($this->lines); $i++) {
            if ($this->lines[$i]['depth'] >= $maxDepth) {
                $depth = min($depth, $this->lines[$i]['depth']);
            }
        }
        for ($i = $index; $i < count($this->lines); $i++) {
            if ($this->lines[$i]['depth'] == $depth) {
                $children = $this->getChildren($i+1, $depth + 1);
                $element = array(
                    'call'           => $this->lines[$i]['call'],
                    'array'          => false,
                    'line_no'        => $this->lines[$i]['line_no'],
                    'time_children'  => $this->lines[$i]['time_children'],
                    'count_children' => $this->lines[$i]['count_children'],
                    'time_call'      => $this->lines[$i]['time_call'],
                    'path'           => $this->lines[$i]['path'],
                    'short_path'     => $this->lines[$i]['short_path'],
                    'relative'       => 0,
                    'children'       => array()
                );
                if ($children) {
                    $element['children'] = $children;
                }
                $result[] = $element;
                $max_time_children = max($this->lines[$i]['time_children'], $max_time_children);
                $force = false;
            } elseif ($this->lines[$i]['depth'] < $depth && !$force) {
                break;
            }
        }
        foreach ($result as &$item) {
            $item['relative'] = $max_time_children == 0 ? 0 : (int)(($item['time_children'] / $max_time_children)*100);
        }
        return $result;
    }

    protected function preProcessInputLine($line, $line_no)
    {
        static $count = 0;

        $line_info =  $this->getLineInfo($line);
        if ($line_info) {
            $time = (integer)($line_info['time'] * 1000000);
            if (!preg_match('/^(?P<namespace>[^\(]+)(::|->)(?P<method>[^\(]+).*$/', $line_info['call'], $matches)) {
                return;
            }

            $count++;
            $this->lines[$count] = array(
                'count'             => $count,
                'line_no'           => $line_no,
                'time_children'     => 0,
                'count_children'    => 0,
                'time_call'         => 0,
                'ignored_children'  => 0,
                'depth'             => $line_info['depth'],
                'path'              => $line_info['path'],
                'short_path'        => $this->getFilename($line_info['path']),
                'time'              => $time,
                'namespace'         => $matches['namespace'],
                'method'            => $matches['method'],
                'call'              => $line_info['call']
            );
            $this->updateTimes($count-1, $this->lines[$count]['time']);
            $this->min_depth = min($this->min_depth, $line_info['depth']);
            $this->shorter_path = min($this->shorter_path, $line_info['path_length']);
        }
    }

    protected function updateTimes($count, $time)
    {
        if ($count < 1) {
            return;
        }
        $elapsed = $time - $this->lines[$count]['time'];
        $this->lines[$count]['time_call'] = $elapsed;
        $this->lines[$count]['time_children'] = $elapsed;
        $depth = $this->lines[$count]['depth'];
        for ($i = $count; $i > 0; $i--) {
            if ($this->lines[$i]['depth'] < $depth) {
                $this->lines[$i]['time_children'] += $elapsed;
                $this->lines[$i]['count_children']++;
                $depth = $this->lines[$i]['depth'];
                if ($depth == 0) {
                    break;
                }
            }
        }
    }

    protected function postProcessInputLine($index, $line)
    {
        $indent = str_repeat(' ', $line['depth']);

        $this->last_time = $line['time'];
        $this->lines[$index]['depth'] -= $this->min_depth;
        return sprintf(
            '%05d %07d %02d %05d %s %s',
            $line['time_call'],
            $line['time_children'],
            $line['depth'] - $this->min_depth,
            $line['ignored_children'],
            $indent,
            $line['call']
        );
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

    protected function getFilename($path)
    {
        $path = explode('/', $path);
        $path = array_slice($path, -$this->shorter_path);
        return implode('/', $path);
    }
}
