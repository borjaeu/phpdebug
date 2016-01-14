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
    public function dump($data = null, $depth = 2)
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
        $pos = $this->getCallerDetails($depth);

        if (!is_null($data)) {
            $data = $this->objectToHtml($data);
        }
        $id = uniqid();
        if (\DebugHelper::isCli()) {
            echo "[Dump] var, $pos====================================\n$data====================================\n";
        } else {
            if (!is_null($data)) {
                $data = "<div class=\"data\">{$data}</div>";
            } else {
                $data = '';
            }

            echo <<<DEBUG

<div id="$id" class="debug_dump">
    <div class="header">
        <span class="timer">$split</span>
        <span class="code">$pos</span>
     </div>
     {$data}
</div>

DEBUG;
        }
    }

    /**
     * Shows the HTML trace.
     *
     * @return mixed
     */
    public function showtrace()
    {
        $trace = xdebug_get_function_stack();
        $trace = array_slice($trace, 0, count($trace) - 1);
        $debugTrace = self::getDebugTrace($trace);
        echo $debugTrace;
    }

    /**
     * Shows the HTML trace.
     *
     * @return string
     */
    public function getDebugTrace($trace)
    {
        if (!\DebugHelper::isCli()) {
            Styles::showHeader('showtrace');
            Styles::showHeader('objectToHtml');
        }

        print_r($trace);

        $debugBacktrace = array();
        foreach ($trace as $item) {
            $step = array();

            if (isset($item['function'])) {
                $step['function'] = isset($item['class'])
                    ? $item['class'] . '::' . $item['function']
                    : $item['function'];
            } else {
                $step['function'] = 'include: ' . $item['include_filename'];
            }
            $step['params'] = isset($item['params']) ? count($item['params']) : '-';
            $step['file'] = $item['file'];
            $step['line'] = $item['line'];
            $debugBacktrace[] = $step;
        }

        $pos = $this->getCallerDetails(3);
        if (\DebugHelper::isCli()) {
            $debugBacktrace = "Showtrace in " . $pos . $this->array2Text($debugBacktrace);
        } else {
            $debugBacktrace = $this->array2Html($debugBacktrace, 'showtrace');
            $debugBacktrace = "<pre>$pos$debugBacktrace</pre>";
        }
        return $debugBacktrace;
    }
}
