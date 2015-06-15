<?php
namespace DebugHelper\Tools;

use DebugHelper\Styles;

class Dump extends Abstracted
{
    /**
     * Displays the data passed as information.
     *
     * @param mixed $data Information to be dumped to the browser.
     */
    public function dump($data = '')
    {
        static $start = false;

        if ($start === false) {
            $start = microtime(true);
            $split = 0;
        } else {
            $split = microtime(true) - $start;
        }
        $split = number_format($split, 6);

        Styles::showHeader('dump', 'objectToHtml');
        $pos = $this->getCallerDetails(2);

        $data = $this->objectToHtml($data);
        $id = uniqid();
        if (\DebugHelper::isCli()) {
            echo "[Dump] var, $pos====================================\n$data====================================\n";
        } else {
            echo <<<DEBUG

<div id="$id" class="debug_dump">
    <div class="header">
        <span class="timer">$split</span>
        <span class="code">$pos</span>
     </div>
     <div class="data">{$data}</div>
</div>

DEBUG;
        }
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
        if (!($return_trace || \DebugHelper::isCli())) {
            Styles::showHeader('showtrace');
            Styles::showHeader('objectToHtml');
        }
        $trace = xdebug_get_function_stack();
        $trace = array_slice($trace, 0, count($trace) - 1);

        $debug_backtrace = array();
        foreach ($trace as $item) {
            $step = array();

            if (isset($item['function'])) {
                $step['function'] = isset($item['class'])
                    ? $item['class'] . '::' . $item['function']
                    : $item['function'];
            } else {
                $step['function'] = 'include: ' . $item['include_filename'];
            }
            $step['params'] = count($item['params']);
            $step['file'] = $item['file'];
            $step['line'] = $item['line'];
            $debug_backtrace[] = $step;
        }

        $pos = $this->getCallerDetails(2);
        if (\DebugHelper::isCli()) {
            $debug_backtrace = "Showtrace in " . $pos . $this->array2Text($debug_backtrace);
        } else {
            $debug_backtrace = $this->array2Html($debug_backtrace, 'showtrace');
            $debug_backtrace = "<pre>$pos$debug_backtrace</pre>";
        }

        if ($return_trace) {
            return $debug_backtrace;
        }

        echo $debug_backtrace;
        if ($finish) {
            die();
        }
        return '';
    }
}
