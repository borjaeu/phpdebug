<?php
namespace DebugHelper\Tools;

use DebugHelper\Styles;
use DebugHelper\Tools\Helper\CommandParameters;
use DebugHelper\Tools\Model\Position;
use ReflectionClass;

class Output
{
    const MODE_NORMAL = 'normal';
    const MODE_CLI = 'cli';
    const MODE_FILE = 'file';

    /**
     * @var integer
     */
    protected $start = false;

    protected $open = false;

    /**
     * Foreground colors
     *
     * @var array
     */
    private $cliForeground = [
        'black'         => '0;30',
        'dark_gray'     => '1;30',
        'blue'          => '0;34',
        'light_blue'    => '1;34',
        'green'         => '0;32',
        'light_green'   => '1;32',
        'cyan'          => '0;36',
        'light_cyan'    => '1;36',
        'red'           => '0;31',
        'light_red'     => '1;31',
        'purple'        => '0;35',
        'light_purple'  => '1;35',
        'brown'         => '0;33',
        'yellow'        => '1;33',
        'light_gray'    => '0;37',
        'white'         => '1;37'
    ];

    /**
     * Background colors
     *
     * @var array
     */
    private $cliBackground = [
        'black'         => '40',
        'red'           => '41',
        'green'         => '42',
        'yellow'        => '43',
        'blue'          => '44',
        'magenta'       => '45',
        'cyan'          => '46',
        'light_gray'    => '47'
    ];

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var string
     */
    protected $file;

    /**
     * Output constructor.
     *
     * @param string $mode
     * @param string $file
     */
    public function __construct($mode = self::MODE_NORMAL, $file = '')
    {
        if ($mode == self::MODE_NORMAL && \DebugHelper::isCli()) {
            $mode = self::MODE_CLI;
        }
        $this->mode = $mode;
        $this->file = $file;
    }

    /**
     * Exports the filter debug info.
     *
     * @param Position $position Position information where the dump is made
     * @return string
     */
    public function getCallerDetails(Position $position)
    {
        $id = uniqid();

        $line = $position->getLine();
        $file = $position->getFile();
        $call = $position->getCall();
        $source = str_replace(array("\t", ' '), array('->', '.'), $position->getSource());

        switch ($this->mode) {
            case self::MODE_FILE:
            case self::MODE_CLI:
                return <<<POS
{$file}:{$line} in {$call}
POS;
                break;
            default:
                Styles::showHeader('getCallers');
                $filename = basename($file);
                $url = $this->buildUrl($file, $line);
                return <<<POS
<a class="debug_caller" name="$id" href="$url" title="in {$call}">{$source}
    <span class="line">{$filename}::{$line}</span>
</a>

POS;
        }
    }

    /**
     * Exports the filter debug info.
     *
     * @return Position
     */
    private function getCallerInfo()
    {
        $trace = debug_backtrace(false);

        $item = ['file'=> '', 'line' => 0];
        foreach ($trace as $item) {
            if (isset($item['file'])) {
                if (preg_match('/DebugHelper/', $item['file'])) {
                    continue;
                }
            } elseif (isset($item['class']) && preg_match('/DebugHelper/', $item['class'])) {
                continue;
            }
            break;
        }

        if (!isset($item['file'], $item['line'])) {
            var_dump($trace);
            exit;
        }

        $position = new Position($item['file'], $item['line']); // Demo
        $position->setCall($item['function']);
        return $position;
    }

    /** Displays the data passed as information.
     *
     * @return Output
     */
    public function open()
    {
        $position = $this->getCallerInfo();
        $this->open = true;
        if ($this->start === false) {
            $this->start = microtime(true);
            $split = 0;
        } else {
            $split = microtime(true) - $this->start;
        }
        $split = number_format($split, 6);

        $pos = self::getCallerDetails($position);
        $id = uniqid();

        switch ($this->mode) {
            case self::MODE_CLI:
                echo PHP_EOL . $this->getColoredString("$pos", 'black', 'yellow') . PHP_EOL;
                break;
            case self::MODE_FILE:
                error_log("$pos\n", 3, $this->file);
                break;
            default:
                Styles::showHeader('dump', 'objectToHtml');
                echo <<<DEBUG

<div id="$id" class="debug_dump">
    <div class="header">
        <span class="timer">$split</span>
        <span class="code">$pos</span>
     </div>

DEBUG;
        }
        return $this;
    }

    /**
     * Displays the data passed as information.
     *
     * @param mixed $data Information to be dumped to the browser.
     * @param integer $maxDepth Maximum depth for objects
     * @return Output
     */
    public function dump($data, $maxDepth = 5)
    {
        $mustClose = false;
        if (!$this->open) {
            $mustClose = true;
            $this->open();
        }
        if (!is_null($data) && !is_string($data)) {
            $data = self::objectToHtml($data, $maxDepth);
        }

        switch ($this->mode) {
            case self::MODE_CLI:
                echo $data;
                break;
            case self::MODE_FILE:
                error_log($data . PHP_EOL, 3, $this->file);
                break;
            default:
                if (!is_null($data)) {
                    $data = "<div class=\"data\">{$data}</div>";
                } else {
                    $data = '';
                }
                echo <<<DEBUG
     {$data}

DEBUG;
        }
        if ($mustClose) {
            $this->close();
        }
        return $this;
    }

    /**
     * @param array $data Data to convert to array
     */
    public function table(array $data)
    {
        $mustClose = false;
        if (!$this->open) {
            $mustClose = true;
            $this->open();
        }

        switch ($this->mode) {
            case self::MODE_CLI:
                echo $this->array2Text($data);
                break;
            case self::MODE_FILE:
                $table = $this->array2Text($data);
                error_log($table, 3, $this->file);
                break;
            default:
                echo $this->array2Html($data);
        }

        if ($mustClose) {
            $this->close();
        }
    }

    /**
     * Displays the data passed as information.
     *
     * @return Output
     */
    public function close()
    {
        $this->open = false;
        switch ($this->mode) {
            case self::MODE_CLI:
                echo "\n";
                break;
            case self::MODE_FILE:
                break;
            default:
                echo <<<DEBUG

</div>

DEBUG;
        }
    }

    /**
     * Creates a an url
     *
     * @param string $file
     * @param int $line
     * @return string
     */
    public function buildUrl($file, $line = null)
    {
        $commandParameters = new CommandParameters();

        $handlerInfo = \DebugHelper::getHandler();
        $file = str_replace($handlerInfo['source'], $handlerInfo['target'], $file);
        $handler = $commandParameters->parse($handlerInfo['handler'], ['file' => $file, 'line' => $line]);

        return $handler;
    }

    /**
     * Convert data to HTML.
     *
     * @param object $data Data to convert to array
     * @param integer $maxDepth Max level to return.
     * @return mixed
     */
    private function objectToHtml($data, $maxDepth = 5)
    {
        $stuff = $this->objectToArray($data, 0, $maxDepth);
        switch ($this->mode) {
            case self::MODE_CLI:
            case self::MODE_FILE:
                return $this->tree2Text($stuff);
            default:
                return $this->tree2Html($stuff);
        }
    }

    /**
     * Convert data to HTML.
     *
     * @param object $data Data to convert to array
     * @param integer $level Depth of the current level.
     * @param integer $maxLevel Max level to return.

     * @return mixed
     */
    private function objectToArray($data, $level = 0, $maxLevel = 5)
    {
        static $id = 0;

        $debug = array('type' => 'array', 'value' => 'array');
        if (is_object($data)) {
            $reflection = new ReflectionClass($data);
            $properties = $reflection->getProperties();
            $properties_array = array();
            $debug['value'] = get_class($data);
            $debug['type'] = 'object(' . count($properties) . ')';
            $debug['class'] = 'object';
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $properties_array[$property->getName()] = $property->getValue($data);
            }
            $data = $properties_array;
        }
        if (is_array($data)) {
            $debug['sub_items'] = array();
            $debug['type'] .= '(' . count($data) . ')';
            $debug['class'] = 'array';
            if ($level < $maxLevel) {
                foreach ($data as $sub_key => $sub_value) {
                    $debug['sub_items'][$sub_key] = $this->objectToArray($sub_value, $level + 1, $maxLevel);
                }
            }
        } else {
            if (is_string($data)) {
                $size = strlen($data);
                $debug['type'] = 'string(' . $size . ')';
                $debug['class'] = 'string';
                $debug['value'] = $data;
                if ($size > 160) {
                    $debug['sub_items'] = $data;
                    $debug['value'] = substr($data, 0, 160) . '[...]';
                }
            } elseif (is_null($data)) {
                $debug['type'] = 'null';
                $debug['class'] = 'null';
                $debug['value'] = '';
            } elseif (is_integer($data)) {
                $debug['type'] = 'integer';
                $debug['class'] = 'integer';
                $debug['value'] = $data;
            } elseif (is_bool($data)) {
                $debug['type'] = 'boolean';
                $debug['class'] = 'boolean';
                $debug['value'] = $data ? 'true' : 'false';
            } elseif (is_float($data)) {
                $debug['type'] = 'float';
                $debug['class'] = 'float';
                $debug['value'] = $data;
            } else {
                echo 'Unknown type';
                var_dump($data);
                die(sprintf("<pre><a href=\"%s\">DIE</a></pre>", $this->buildUrl(__FILE__, __LINE__)));
            }
        }
        $id++;
        preg_match('/^\w+/', $debug['type'], $type_class);
        $debug['class'] = $type_class[0];
        return $debug;
    }

    /**
     * Convert data to array.
     *
     * @param array $data Data to convert to array
     * @param string|bool $key Key for the current level.
     * @param integer $level Depth of the current level.
     * @return string
     */
    private function tree2Html(array $data, $key = false, $level = 0)
    {
        static $id = 0;

        $debug = $level == 0 ? sprintf("<ul class=\"object_dump\">\n") : '';

        $indent = str_repeat('    ', $level);
        $extra = '';
        if (isset($data['sub_items'])) {
            if (is_array($data['sub_items']) && !empty($data['sub_items'])) {
                $extra = "$indent<ul>\n";
                foreach ($data['sub_items'] as $subKey => $subValue) {
                    $extra .= $this->tree2Html($subValue, $subKey, $level + 1);
                }
                $extra .= "$indent</ul>\n";
            } elseif (is_string($data['sub_items'])) {
                $extra = $data['sub_items'];
            }
            unset($data['sub_items']);
        }
        $id++;
        $class = 'null';
        if (!empty($data['class'])) {
            $class = $data['class'];
            unset($data['class']);
        }
        $content = '';
        foreach ($data as $field => $value) {
            $content .= "<span class=\"{$field}\">$value</span>";
        }

        $status = '';
        $script = '';
        if (!empty($extra)) {
            $status = \DebugHelper::isEnabled(\DebugHelper::OPTION_DUMP_COLLAPSED) ? 'collapsed' : 'expanded';
            $script = 'onclick="return toggleObjectToHtmlNode(' . $id . ');"';
        }
        $debug .= <<<HTML
$indent<li id="debug_node_$id" class="$status $class">
$indent    <span class="row" $script>
$indent        $key $content
$indent    </span>
$extra
$indent</li>

HTML;
        if ($level == 0) {
            $debug .= "\n</ul>\n";
        }

        return $debug;
    }

    /**
     * Convert data to array.
     *
     * @param array $data Data to convert to array
     * @param string|bool $key Key for the current level.
     * @param string $indent Depth indent of the current level.
     * @param bool $isLast Last item in the branch.
     * @return string
     */
    private function tree2Text(array $data, $key = false, $indent = '', $isLast = true)
    {
        $debug = '';

        $last = html_entity_decode('&#x2514;', ENT_NOQUOTES, 'UTF-8');
        $line = html_entity_decode('&#x2502;', ENT_NOQUOTES, 'UTF-8');
        $cross = html_entity_decode('&#x251C;', ENT_NOQUOTES, 'UTF-8');
        $dash = html_entity_decode('&#x2500;', ENT_NOQUOTES, 'UTF-8');

        $extra = '';
        if (isset($data['sub_items'])) {
            if (is_array($data['sub_items']) && !empty($data['sub_items'])) {
                $count = count($data['sub_items']);
                $extra = '';
                foreach ($data['sub_items'] as $subKey => $subValue) {
                    $extra .= $this->tree2Text(
                        $subValue,
                        $subKey,
                        $indent . ($isLast ? ' ' : $line) . '  ',
                        --$count == 0
                    );
                }
            } elseif (is_string($data['sub_items'])) {
                $extra = '=--' . $this->getColoredString($data['sub_items'], 'blue');
            }
            unset($data['sub_items']);
        }
        if (!empty($data['class'])) {
            unset($data['class']);
        }
        $content = '';
        foreach ($data as $field => $value) {
            $content .= "$field:" . $this->getColoredString($value, 'blue') . ' ';
        }

        if ($key !== false) {
            $key = $this->getColoredString($key, 'green') . $this->getColoredString(': ', 'red');
        }
        $indent .= $isLast ? $last : $cross;
        $debug .= <<<HTML
$indent$dash$key$content
$extra
HTML;
        return $debug;
    }

    private function array2Html(array $rows)
    {
        $html_table = "<table>\n\t<tr>\n";
        foreach ($rows[0] as $field => $value) {
            $html_table .= "\t\t<th>$field</th>\n";
        }
        $html_table .= "\t</tr>\n";
        foreach ($rows as $item) {
            $html_table .= "\t<tr>\n";
            foreach ($item as $field => $value) {
                if ($field == 'file') {
                    $file = $this->getShortenedPath($value, 4);
                    $url = $this->buildUrl($item['file'], isset($item['line']) ? $item['line'] : null);
                    $value = "<a href=\"$url}\" title=\"$value\">$file</a>";
                }
                $html_table .= "\t\t<td class=\"$field\">$value</td>\n";
            }
            $html_table .= "\t</tr>\n";
        }
        $html_table .= "\n</table>\n";
        return $html_table;
    }

    private function array2Text(array $rows)
    {
        $text_table = '';
        $headers = array();

        foreach ($rows as $item) {
            if (empty($headers)) {
                foreach ($item as $field => $value) {
                    $headers[$field] = strlen($field)+1;
                }
            }
            foreach ($item as $field => $value) {
                $headers[$field] = max(strlen($value) + 1, $headers[$field]);
            }
        }
        foreach ($headers as $field => $length) {
            $text_table .= "$field" . str_repeat(' ', $length - strlen($field));
        }
        $odd = true;
        foreach ($rows as $item) {
            $odd = !$odd;
            $row = '';
            foreach ($item as $field => $value) {
                $row .= $value . str_repeat(' ', $headers[$field] - strlen($value));
            }
            if ($odd) {
                $row = $this->getColoredString($row, '', 'magenta');
            }
            $text_table .= $row . "\n";
        }
        return $text_table;
    }


    /**
     * Colorized the string with the given colors
     *
     * @param $string string to colorize
     * @param string $foreground Foreground color
     * @param string $background Background color
     * @return string
     */
    private function getColoredString($string, $foreground = '', $background = '')
    {
        $coloredString = '';

        if (isset($this->cliForeground[$foreground])) {
            $coloredString .= "\033[" . $this->cliForeground[$foreground] . "m";
        }
        if (isset($this->cliBackground[$background])) {
            $coloredString .= "\033[" . $this->cliBackground[$background] . "m";
        }
        $coloredString .=  $string . "\033[0m";

        return $coloredString;
    }

    /**
     * @param string $path
     * @param int $length
     *
     * @return string
     */
    private function getShortenedPath($path, $length)
    {
        $steps = explode('/', $path);
        $path = array_slice($steps, -$length);
        return implode('/', $path);
    }
}
