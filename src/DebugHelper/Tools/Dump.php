<?php
namespace DebugHelper\Tools;

class Dump extends Abstracted
{
    /**
     * Displays the data passed as information.
     *
     * @param mixed $data Information to be dumped to the browser.
     */
    public function dump($data = '', $offset = 0, $raw = false)
    {
        static $start = false;

        if ($start === false) {
            $start = microtime(true);
            $split = 0;
        } else {
            $split = microtime(true) - $start;
        }
        $split = number_format($split, 6);

        \DebugHelper\Styles::showHeader('dump', 'objectToHtml');
        $pos = $this->getCallerHtml(2+$offset);

        if (!$raw) {
            $data = $this->objectToHtml($data);
        }
        $id = uniqid();
        echo <<<DEBUG
<div id="$id" class="debug_dump">
	<div class="header">
		<span class="timer">$split</span>
		<span class="code">$pos</span>
	</div>
	<div class="data">{$data}</div>
</div>

DEBUG;
        ob_flush();
    }

    /**
     * Shows the HTML trace.
     *
     * @param boolean $finish Finish the script execution.
     * @param boolean $return_trace Returns the trace instead of printing it.
     *
     * @return mixed
     */
    public function showtrace($finish = true, $return_trace = false)
    {
        if (!$return_trace) {
            \DebugHelper\Styles::showHeader('showtrace');
            \DebugHelper\Styles::showHeader('objectToHtml');
        }
        $trace = xdebug_get_function_stack();
        $trace = array_slice($trace, 0, count($trace) - 1);

        $debug_backtrace = "<table id=\"showtrace\">\n";
        foreach ($trace as $item) {
            $params = $this->objectToHtml($item['params'], false);
            if (isset($item['function'])) {
                $function = isset($item['class']) ? $item['class'] . '::' . $item['function'] : $item['function'];
            } else {
                $function = 'inlcude: ' . $item['include_filename'];
            }
            $file = $this->getShortenedPath($item['file'], 4);

            $count = count($item['params']);
            $debug_backtrace
                .= <<<ROW
	<tr class="showtrace_row">
		<td><a href="codebrowser:{$item['file']}:{$item['line']}">$file</a></td>
		<td>{$item['line']}</td>
		<td>$function()</td>
		<td><div class="params">$params</div>array($count) </td>
	</tr>

ROW;
        }
        $debug_backtrace .= "\n</table>\n";

        if ($return_trace) {
            return $debug_backtrace;
        }

        $pos = '';
        $debug_backtrace
            = <<<TRACE
	<pre>
$pos
$debug_backtrace
	</pre>
TRACE;

        echo $debug_backtrace;
        if ($finish) {
            die();
        }
    }

    protected function getShortenedPath($path, $length)
    {
        $steps = explode('/', $path);
        $path = array_slice($steps, -$length);
        return implode('/', $path);
    }
}