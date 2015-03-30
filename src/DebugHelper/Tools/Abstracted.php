<?php
namespace DebugHelper\Tools;

use DebugHelper\Styles;
use ReflectionClass;

class Abstracted
{
    /**
     * Exports the filter debug info.
     *
     * @param integer $depth Depth of the callers to get.
     * @param boolean $show_header
     * @return string
     */
    protected function getCallerDetails($depth, $show_header = true)
    {
        $item = $this->getCallerInfo(false, $depth + 1);
        if (false === $item) {
            return false;
        }

        $title = $item['function'];
        if (isset($item['class']) && isset($item['type'])) {
            $title = $item['class'] . $item['type'] . $title;
        }

        $id = uniqid();

        if ($show_header) {
            Styles::showHeader('getCallers');
        }

        if (isset($item['file'])) {
            // Get code line.
            $code = self::getCodeLineInfo($item['file'], $item['line']);
            $line = trim(str_replace("\t", '→|', $code['source']));
            $file = $this->getShortenedPath($item['file'], 3);

            if (\DebugHelper::isCli()) {
                $link = <<<POS
caller: {$file}:{$item['line']} in {$code['class']}::{$code['method']}() "$line"

POS;

            } else {
                $link = <<<POS
<a class="debug_caller" name="$id" href="codebrowser:{$item['file']}:{$item['line']}" title="in {$code['class']}::{$code['method']}()">$line<span class="line">$file:{$item['line']}</span></a>

POS;
            }
        } else {
            $link = $title;
        }
        return $link;
    }

    /**
     * Exports the filter debug info.
     *
     * @param integer $depth Depth of the callers to get.
     * @return string
     */
    protected function getCallerSource($depth)
    {
        $item = $this->getCallerInfo(false, $depth + 1);
        if (isset($item['file'])) {
            // Get code line.
            $code = self::getCodeLineInfo($item['file'], $item['line']);
            return $code['source'];
        }
        return '';
    }

    /**
     * Returns the information of the function dirty_that called this one.
     *
     * @param boolean $key Return only one of the keys of the array.
     * @param integer $depth Numbers of method to go back.
     * @return array
     */
    protected function getCallerInfo($key = false, $depth = 2)
    {
        $trace = debug_backtrace(false);

        if (!isset($trace[$depth])) {
            return false;
        }
        $item = $trace[$depth];
        if ($key) {
            return $item[$key];
        }
        return $item;
    }

    /**
     * Gets information about a code file by opening the file and reading the PHP code.
     *
     * @param string $file Path to the file
     * @param integer $line Line number
     * @return array
     */
    protected function getCodeLineInfo($file, $line)
    {
        $result = array(
            'class' => false,
            'method' => false,
            'source' => ''
        );

        if (!is_file($file)) {
            return $result;
        }

        // Get code line.
        $fp = fopen($file, 'r');
        $line_no = 0;
        $class_reg_exp = '/^\s*(abstract)?\s*[cC]lass\s+([^\s]*)\s*(extends)?\s*([^\s]*)/';
        $function_reg_exp = '/^\s+(.*)function\s+([^\(]*)\((.*)\)/';
        while ($line_no++ < $line) {
            $result['source'] = fgets($fp);
            if (preg_match($class_reg_exp, $result['source'], $matches)) {
                $result['class'] = $matches[2];
            } elseif (preg_match($function_reg_exp, $result['source'], $matches)) {
                $result['method'] = $matches[2];
            }
        }
        return $result;
    }


    /**
     * Convert data to HTML.
     *
     * @param object $data Data to convert to array
     * @return mixed
     */
    protected function objectToHtml($data)
    {
        $stuff = $this->objectToArray($data);
        if (\DebugHelper::isCli()) {
            return $this->tree2Text($stuff);
        } else {
            return $this->tree2Html($stuff);
        }
    }

    /**
     * Convert data to HTML.
     *
     * @param object $data Data to convert to array
     * @return mixed
     */
    protected function objectToArray($data, $level = 0)
    {
        static $id = 0;

        $debug = array('type' => 'array', 'value' => 'array');
        if (is_object($data)) {
            $debug['value'] = get_class($data);
            $debug['type'] = 'object(' . count($data) . ')';
            $reflection = new ReflectionClass($data);
            $properties = $reflection->getProperties();
            $properties_array = array();
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $properties_array[$property->getName()] = $property->getValue($data);
            }
            $data = $properties_array;
        }
        if (is_array($data)) {
            $debug['sub_items'] = array();
            $debug['type'] .= '(' . count($data) . ')';
            if ($level<3) {
                foreach ($data as $sub_key => $sub_value) {
                    $debug['sub_items'][$sub_key] = $this->objectToArray($sub_value, $level + 1);
                }
            } else {
                $debug['value'] = $debug['type'];
            }
        } else {
            if (is_string($data)) {
                $size = strlen($data);
                $debug['type'] = 'string(' . $size . ')';
                $debug['value'] = $data;
                if ($size > 160) {
                    $debug['sub_items'] = $data;
                    $debug['value'] = substr($data, 0, 160) . '[...]';
                }
            } elseif (is_null($data)) {
                $debug['type'] = 'null';
                $debug['value'] = '';
            } elseif (is_integer($data)) {
                $debug['type'] = 'integer';
                $debug['value'] = $data;
            } elseif (is_bool($data)) {
                $debug['type'] = 'boolean';
                $debug['value'] = $data ? 'true' : 'false';
            } elseif (is_float($data)) {
                $debug['type'] = 'float';
                $debug['value'] = $data;
            } else {
                echo 'Unknown type';
                var_dump($data);
                die(sprintf("<pre><a href=\"codebrowser:%s:%d\">DIE</a></pre>", __FILE__, __LINE__));
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
     * @param object $data Data to convert to array
     * @return string
     */
    protected function tree2Html(array $data, $key = false, $level = 0)
    {
        static $id = 0;

        $debug = $level == 0 ? sprintf("<ul class=\"object_dump\">\n") : '';

        $indent = str_repeat('    ', $level);
        $extra = '';
        if (isset($data['sub_items'])) {
            if (is_array($data['sub_items']) && !empty($data['sub_items'])) {
                $extra = "$indent<ul>\n";
                foreach ($data['sub_items'] as $sub_key => $sub_value) {
                    $extra .= $this->tree2Html($sub_value, $sub_key, $level + 1);
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
            $status = \DebugHelper::isEnabled(\DebugHelper::DUMP_COLLAPSED) ? 'collapsed' : 'expanded';
            $script = 'onclick="return toggleObjectToHtmlNode(' . $id . ');"';
        }
        $debug .= <<<HTML
$indent<li id="debug_node_$id" class="$status $class">
$indent    <span class="row" $script>
$indent        $key$content
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
     * @param object $data Data to convert to array
     * @param string $key Key for the current level.
     * @param integer $level Depth of the current level.
     * @return string
     */
    protected function tree2Text(array $data, $key = false, $level = 0)
    {
        static $id = 0;

        $debug = '';

        $indent = str_repeat('|   ', $level);
        $extra = '';
        if (isset($data['sub_items'])) {
            if (is_array($data['sub_items']) && !empty($data['sub_items'])) {
                $extra = '';
                foreach ($data['sub_items'] as $sub_key => $sub_value) {
                    $extra .= $this->tree2Text($sub_value, $sub_key, $level + 1);
                }
            } elseif (is_string($data['sub_items'])) {
                $extra = '|--' . $data['sub_items'];
            }
            unset($data['sub_items']);
        }
        $id++;
        if (!empty($data['class'])) {
            unset($data['class']);
        }
        $content = '';
        foreach ($data as $field => $value) {
            $content .= "$field: $value ";
        }

        if ($key !== false) {
            $key .= ' -> ';
        }
        $debug .= <<<HTML
$indent|-- $key$content
$extra
HTML;
        return $debug;
    }

    protected function array2Html(array $rows, $id)
    {
        $html_table = "<table id=\"$id\">\n";
        foreach ($rows as $item) {
            $html_table .= "<tr>";
            foreach ($item as $field => $value) {
                $html_table .= "<td class=\"$field\">$value</td>";
            }
            $html_table .= "</tr>";
        }
        $html_table .= "\n</table>\n";
        return $html_table;
    }

    protected function array2Text(array $rows)
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
        foreach ($rows as $item) {
            foreach ($item as $field => $value) {
                $text_table .= $value . str_repeat(' ', $headers[$field] - strlen($value));
            }
            $text_table .= "\n";
        }
        return $text_table;
    }

    protected function getShortenedPath($path, $length)
    {
        $steps = explode('/', $path);
        $path = array_slice($steps, -$length);
        return implode('/', $path);
    }
}
