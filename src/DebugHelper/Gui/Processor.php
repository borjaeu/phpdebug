<?php
namespace DebugHelper\Gui;

class Processor
{
    protected $file;

    protected $min_depth;

    protected $shorter_path;

    protected $last_time = 0;

    protected $lines;

    protected $ignore_depth;

    protected $ignored_namespace = array(
        'Doctrine',
        'Composer',
        'DebugHelper',
        'QaamGo\RestApiBundle\Serializer\Entity',
        'QaamGo\RestApiBundle\Api\Validation\Schema\Constraint\Schema'
    );

    /**
     * Sets the value of file.
     *
     * @param string $file the file
     */
    public function process($file)
    {
        $this->lines = array();
        if (!is_file($file)) {
            throw new \Exception("Error Processing file $file");
        }
        $this->file = $file;

        $file_in = fopen($this->file, 'r');
        $this->min_depth = 65000;
        $this->shorter_path = 65000;
        $count = 320000000;
        $line_no = 0;
        while (!feof($file_in) && $count-- > 0) {
            $line = fgets($file_in);
            $line_no++;
            $this->preProcessInputLine($line, $line_no);
        }
        fclose($file_in);

        $file_out = fopen($this->file . '.out', 'w');
        foreach ($this->lines as $i => $line) {
            $line = $this->postProcessInputLine($i, $line);
            if ($line) {
                fwrite($file_out, $line . "\n");
            }
        }
        fclose($file_out);

        $children = $this->getChildren(1, 0);

        return array(
            'name' => 'root',
            'children' => $children
        );
    }

    protected function getChildren($index, $depth)
    {
        $result = array();

        $max_time_children = 0;
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
            } elseif ($this->lines[$i]['depth'] < $depth) {
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
            if ($this->ignore_depth) {
                if ($line_info['depth'] > $this->ignore_depth) {
                    $this->lines[$count]['ignored_children']++;
                    return;
                } else {
                    $this->ignore_depth = false;
                }
            }
            $time = (integer)($line_info['time'] * 1000000);
            if (preg_match('/^(?P<namespace>.*)(::|->).*$/', $line_info['call'], $matches)) {
                if ($this->isIgnoredNamespace($matches['namespace'])) {
                    $this->ignore_depth = $line_info['depth'];
                }
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
                'call'              => $line_info['call']
            );
            $this->updateTimes($count-1, $this->lines[$count]['time']);
            $this->min_depth = min($this->min_depth, $line_info['depth']);
            $this->shorter_path = min($this->shorter_path, $line_info['path_length']);
        }
    }

    protected function isIgnoredNamespace($namespace)
    {
        foreach ($this->ignored_namespace as $ignored_namespace) {
            if (substr($namespace, 0, strlen($ignored_namespace)) == $ignored_namespace) {
                return true;
            }
        }
        return false;
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
        $indent = str_repeat(' ', $line['depth'] - $this->min_depth);

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
            $matches['depth'] = strlen($matches['depth']) / 2;
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
